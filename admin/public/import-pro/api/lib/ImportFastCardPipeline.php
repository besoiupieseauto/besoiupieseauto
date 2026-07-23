<?php
declare(strict_types=1);

/**
 * Generare rapidă carduri: scan Python (TecDoc match) + enrichment PHP pe match-uri.
 * ~10–50× mai rapid decât SupplierEnrichedCardBuilder pe CSV-uri mari.
 */
final class ImportFastCardPipeline
{
    /** @var list<string> */
    private const MATCH_STATUSES = ['exact', 'probable', 'conflict'];

    /**
     * @return array{cards: list<array<string, mixed>>, skipped: int, skippedDetails: list<array<string, mixed>>, stats: array<string, mixed>}
     */
    public static function enrichProductsToCards(array $products, bool $requireImage = true): array
    {
        $matcherPath = import_motor_product_matcher_path();
        if (!is_file($matcherPath)) {
            throw new RuntimeException('ProductMatcher lipsă: ' . $matcherPath);
        }
        require_once $matcherPath;
        import_require_prelucrare_lib('BaseIndexLookup.php');
        import_require_prelucrare_lib('CoreDbLookup.php');
        import_require_fetch_product_lib('BrandCatalogResolver.php');

        $matcher = new ProductMatcher();
        $brands = new BrandCatalogResolver();
        $cards = [];
        $skipped = [];

        foreach ($products as $p) {
            if (!is_array($p)) {
                continue;
            }
            $status = (string) ($p['status'] ?? '');
            if (!in_array($status, self::MATCH_STATUSES, true)) {
                continue;
            }

            $sku = trim((string) ($p['sku_supplier'] ?? ''));
            $artNr = trim((string) ($p['art_nr'] ?? ''));
            $refNr = trim((string) ($p['ref_nr'] ?? ''));
            $forceBrand = trim((string) ($p['force_brand'] ?? ''));
            $csvBrand = trim((string) ($p['brand'] ?? ''));
            $tecdocBrand = trim((string) ($p['matched_brand'] ?? ''));
            $productId = (int) ($p['matched_product_id'] ?? 0);
            // Autonet QWP: identitate produs = ArtNr; match TecDoc = RefNr + ReferenceBrand
            $outputSku = $artNr !== '' ? $artNr : $sku;
            $matchSku = $refNr !== '' ? $refNr : $sku;

            if ($productId <= 0 && $tecdocBrand === '' && $matchSku === '' && $sku === '') {
                $skipped[] = ['sku' => $outputSku !== '' ? $outputSku : $sku, 'reason' => 'Lipsă match TecDoc (fără product_id)'];
                continue;
            }

            $sourceFile = trim((string) ($p['source_file'] ?? ''));
            $supplierKey = strtolower(trim((string) ($p['supplier'] ?? '')));
            if ($supplierKey === '' && $sourceFile !== '' && str_contains($sourceFile, '/')) {
                $supplierKey = strtolower(explode('/', $sourceFile)[0] ?? 'generic');
            }
            if ($supplierKey === '') {
                $supplierKey = 'generic';
            }
            // Normalizare: staging folosește chei sku|supplier|supplier/fișier.csv
            if ($sourceFile !== '' && !str_contains($sourceFile, '/')) {
                $sourceFile = $supplierKey . '/' . $sourceFile;
            }
            if ($supplierKey === 'autonet_qwp') {
                $supplierKey = 'autonet';
            }
            if (!import_motor_supplier_is_allowed($supplierKey)) {
                $skipped[] = ['sku' => $outputSku !== '' ? $outputSku : $sku, 'reason' => 'Furnizor neînregistrat/blocat'];
                continue;
            }

            $supplierType = import_motor_supplier_code_from_slug($supplierKey);
            if ($supplierType === '') {
                $supplierType = match ($supplierKey) {
                    'elit' => 'ELIT',
                    'autonet' => 'AUTONET',
                    'autopartner' => 'AUTOPARTNER',
                    'autototal' => 'AUTOTOTAL',
                    'materom' => 'MATEROM',
                    'intercars' => 'INTERCARS',
                    default => strtoupper($supplierKey),
                };
            }

            $canonicalBrand = $tecdocBrand !== '' ? $tecdocBrand : $csvBrand;
            if ($canonicalBrand === '' && $csvBrand !== '') {
                $catalog = $brands->resolve($csvBrand);
                if ($catalog !== null) {
                    $canonicalBrand = $catalog['brand'];
                }
            }

            $variants = self::codeVariants($p);
            $entries = null;

            if ($productId > 0 && CoreDbLookup::isAvailable()) {
                $entries = CoreDbLookup::findEntriesByProductId($productId);
                if ($entries !== null && $entries !== []) {
                    $entryBrand = trim((string) ($entries[0]['ART_BRAND'] ?? ''));
                    if ($entryBrand !== '') {
                        $canonicalBrand = $entryBrand;
                    }
                }
            }

            if (($entries === null || $entries === []) && $canonicalBrand !== '' && $variants !== []) {
                $entries = BaseIndexLookup::findEntries($canonicalBrand, $variants);
            }

            if ($entries === null || $entries === []) {
                $skipped[] = ['sku' => $outputSku !== '' ? $outputSku : $sku, 'reason' => 'Negăsit în besoiu_tecdoc_base pentru enrichment'];
                continue;
            }

            $entryBrand = trim((string) ($entries[0]['ART_BRAND'] ?? ''));
            if ($entryBrand !== '') {
                $canonicalBrand = $entryBrand;
            }

            $code1 = (string) ($entries[0]['ART_CODE_1'] ?? $matchSku);
            // QWP: dacă scanul n-a umplut prețul, ia-l din Lista pret Autonet (ArtNr = COD ARTICOL)
            self::fillQwpPriceFromAutonetList($p);
            $priceNet = isset($p['price_purchase_net']) && is_numeric($p['price_purchase_net'])
                ? (float) $p['price_purchase_net']
                : (import_motor_apply_feed_price($p['price'] ?? null, $supplierKey)
                    ?? (isset($p['price']) && is_numeric($p['price']) ? (float) $p['price'] : 0.0));

            // Întâi template TecDoc (brand+cod match) ca să meargă imaginile/enrichment;
            // abia după applyForcedProductIdentity → produs nou QWP + ArtNr.
            $card = $matcher->buildTecdocImportCard(
                $canonicalBrand,
                $code1,
                $entries,
                $priceNet,
                $supplierType
            );

            if ($card === null) {
                $card = $matcher->buildEnrichedSupplierCard(
                    $canonicalBrand,
                    $code1,
                    $entries,
                    $priceNet,
                    $supplierType,
                    true
                );
            }

            if ($card === null) {
                require_once __DIR__ . '/ImportTecdocMysqlCardBuilder.php';
                $card = ImportTecdocMysqlCardBuilder::fromEntries(
                    $entries,
                    $canonicalBrand,
                    $code1,
                    $priceNet,
                    $supplierType,
                    $p
                );
            }

            if ($card === null && !$requireImage) {
                $card = self::minimalCardFromMatch($p, $supplierType, $supplierKey, $sourceFile, $status);
            }
            if ($card === null) {
                $skipped[] = ['sku' => $outputSku !== '' ? $outputSku : $sku, 'reason' => 'Produs negăsit / incomplet în TecDoc MySQL'];
                continue;
            }

            if (function_exists('import_attach_local_image_to_card')) {
                $card = import_attach_local_image_to_card($card);
            }
            if ($requireImage && (empty($card['hasImage']) || empty($card['imageDisplayUrl']))) {
                $skipped[] = ['sku' => $outputSku !== '' ? $outputSku : $sku, 'reason' => 'Match TecDoc OK — imagine indisponibilă în Poze/Autopartner'];
                continue;
            }

            $card = import_normalize_card_image_fields($card);
            $card = self::applyForcedProductIdentity($card, $p, $canonicalBrand, $code1);

            $card['matchStatus'] = $status;
            $card['matchMethod'] = (string) ($p['match_method'] ?? '');
            $card['matchedName'] = (string) ($p['matched_name'] ?? '');
            $card['matchedTecdocSku'] = (string) ($p['matched_internal_sku'] ?? $code1);
            $card['sourceFile'] = $sourceFile !== '' ? $sourceFile : ($supplierKey . '/' . ($p['source_filename'] ?? ''));
            $card['sourceSupplier'] = $supplierKey;
            $finalSku = $outputSku !== '' ? $outputSku : $sku;
            if ($finalSku !== '') {
                $card['supplierSku'] = $finalSku;
            }
            if (!empty($card['sku']) && trim((string) ($card['tecdocSku'] ?? '')) === '') {
                $card['tecdocSku'] = trim((string) ($code1 !== '' ? $code1 : $card['sku']));
            }
            $card['sku'] = $finalSku !== '' ? $finalSku : ($card['sku'] ?? '');
            $card['cardBuild'] = 'mysql_besoiu_tecdoc_base';
            if (!empty($card['parameters']) || (int) ($card['compatCount'] ?? 0) > 0
                || mb_strlen((string) ($card['description'] ?? '')) > 40) {
                $card['tecdocDataComplete'] = true;
            }
            if (isset($p['price_csv']) && is_numeric($p['price_csv'])) {
                $card['priceCsv'] = round((float) $p['price_csv'], 2);
            }
            $cards[] = import_motor_normalize_card_purchase_prices($card);
        }

        return [
            'cards' => import_motor_normalize_card_list($cards),
            'skipped' => count($skipped),
            'skippedDetails' => array_slice($skipped, 0, 30),
            'stats' => [
                'fileType' => 'fast_scan_enrich',
                'inputProducts' => count($products),
                'cardsBuilt' => count($cards),
                'skipped' => count($skipped),
            ],
        ];
    }

    /**
     * @return array<string, mixed> Răspuns compatibil build-stored.php
     */
    public static function buildPage(
        string $supplier,
        string $filename,
        string $path,
        int $limit,
        int $offset,
        bool $onlyWithImage,
        string $scanMode
    ): array {
        $limit = max(1, min(200, $limit));
        $offset = max(0, min(500000, $offset));

        $checkpoint = import_get_scan_checkpoint($supplier, $filename);
        $csvSkipRows = self::resolveCsvSkipRows($scanMode, $offset, $checkpoint);
        $skipMatched = 0;
        if (
            $scanMode === 'continue'
            && $offset > 0
            && is_array($checkpoint)
            && !array_key_exists('csv_row_offset', $checkpoint)
        ) {
            // Checkpoint vechi (fără poziție CSV) — evită duplicatele la primul run după upgrade.
            $skipMatched = $offset;
        }

        $scanResult = self::scanUntilMatchedProducts(
            $supplier,
            $path,
            $limit + $skipMatched,
            $csvSkipRows,
            $onlyWithImage
        );

        $matched = $scanResult['matched'];
        if ($skipMatched > 0) {
            $matched = array_slice($matched, $skipMatched);
        }
        $pageProducts = array_slice($matched, 0, $limit);
        $label = strtolower($supplier) . '/' . $filename;
        foreach ($pageProducts as &$prod) {
            if (is_array($prod) && empty($prod['source_file'])) {
                $prod['source_file'] = $label;
            }
        }
        unset($prod);

        $enriched = self::enrichProductsToCards($pageProducts, $onlyWithImage);
        $pageCards = $enriched['cards'];
        $pageCount = count($pageCards);

        $nextCsvOffset = $scanResult['next_csv_row_offset'];
        $nextMatchOffset = $offset + $pageCount;
        $hasMore = $pageCount >= $limit
            ? ($scanResult['file_has_more'] || count($matched) > $limit)
            : ($scanResult['file_has_more'] && ($pageCount > 0 || ($onlyWithImage && $scanResult['rows_scanned'] > 0)));
        // Nu bloca continuarea dacă pagina e goală doar din lipsa Poze — mai sunt rânduri CSV.
        if ($pageCount === 0 && !$scanResult['file_has_more']) {
            $hasMore = false;
        }
        if ($pageCount === 0 && $onlyWithImage && $scanResult['file_has_more']) {
            $hasMore = true;
        }

        if ($scanMode === 'restart' && $offset === 0 && $pageCount > 0) {
            import_reset_scan_checkpoint($supplier, $filename, 'restart');
        }
        if ($pageCount > 0 || $nextCsvOffset > $csvSkipRows) {
            import_record_scan_checkpoint(
                $supplier,
                $filename,
                $offset,
                $nextMatchOffset,
                $limit,
                $pageCount,
                $scanMode !== '' ? $scanMode : 'continue',
                $nextCsvOffset
            );
        }

        $summary = is_array($scanResult['summary'] ?? null) ? $scanResult['summary'] : [];
        $matchedTotal = (int) ($summary['exact'] ?? 0) + (int) ($summary['probable'] ?? 0) + (int) ($summary['conflict'] ?? 0);
        $hint = '';
        if ($pageCount === 0 && $onlyWithImage && ($matchedTotal > 0 || (int) ($scanResult['rows_scanned'] ?? 0) > 0)) {
            $hint = ($matchedTotal > 0 ? $matchedTotal . ' match TecDoc' : 'Match TecDoc pe segment')
                . ', dar 0 imagini în Poze pentru acest furnizor (Autopartner e blocat ca să nu amestece SKU-uri).'
                . ' Continuă scanarea mai adânc sau treci pe «Toate produsele» + Scraping.';
        } elseif ($pageCount === 0 && !$onlyWithImage && $scanResult['rows_scanned'] > 0) {
            $hint = 'Scanat ' . $scanResult['rows_scanned'] . ' rânduri — 0 match TecDoc în acest segment.'
                . ' Verifică maparea CSV (Inspect) sau dezactivează fișierul fără match (ex. Autonet).';
        }

        return [
            'success' => true,
            'count' => $pageCount,
            'requested' => $limit,
            'offset' => $offset,
            'has_more' => $hasMore,
            'next_offset' => $nextMatchOffset,
            'checkpoint' => import_get_scan_checkpoint($supplier, $filename),
            'fileType' => 'fast_scan_enrich',
            'buildMode' => 'mysql_tecdoc',
            'source' => ['supplier' => $supplier, 'filename' => $filename],
            'stats' => array_merge(
                is_array($enriched['stats'] ?? null) ? $enriched['stats'] : [],
                [
                    'fileType' => 'fast_scan_enrich',
                    'scanSample' => $scanResult['rows_scanned'],
                    'rowsParsed' => $scanResult['rows_scanned'],
                    'csvRowOffset' => $nextCsvOffset,
                    'matchSummary' => $summary,
                    'cardsBuilt' => $pageCount,
                    'enrichMode' => 'match_enrich',
                    'matchedCollected' => count($matched),
                    'enrichSkipped' => (int) ($enriched['skipped'] ?? 0),
                ]
            ),
            'summary' => [
                'withImage' => count(array_filter($pageCards, static fn ($c): bool => is_array($c) && !empty($c['hasImage']))),
                'withoutImage' => max(0, $pageCount - count(array_filter($pageCards, static fn ($c): bool => is_array($c) && !empty($c['hasImage'])))),
                'tecdocMatched' => $matchedTotal,
            ],
            'onlyWithImage' => $onlyWithImage,
            'hint' => $hint,
            'cards' => $pageCards,
        ];
    }

    /** @param array<string, mixed>|null $checkpoint */
    private static function resolveCsvSkipRows(string $scanMode, int $offset, ?array $checkpoint): int
    {
        if ($scanMode === 'restart') {
            return 0;
        }
        if ($scanMode === 'manual') {
            return max(0, $offset);
        }

        return max(0, (int) ($checkpoint['csv_row_offset'] ?? 0));
    }

    /**
     * Scanează CSV în bucăți până la $targetMatches produse cu match TecDoc (nu doar N rânduri).
     *
     * @return array{
     *   matched: list<array<string, mixed>>,
     *   rows_scanned: int,
     *   next_csv_row_offset: int,
     *   file_has_more: bool,
     *   summary: array<string, mixed>
     * }
     */
    private static function scanUntilMatchedProducts(
        string $supplier,
        string $path,
        int $targetMatches,
        int $csvSkipRows,
        bool $onlyWithImage
    ): array {
        $targetMatches = max(1, $targetMatches);
        // „Doar cu imagine”: Poze pot apărea abia după mii de rânduri (ELIT ~1920, Autototal și mai adânc).
        $maxScanRows = $onlyWithImage
            ? min(80000, max(5000, $targetMatches * 120))
            : min(1000, max(200, $targetMatches * 25));

        $csvCursor = max(0, $csvSkipRows);
        $matched = [];
        $seenKeys = [];
        $rowsScannedTotal = 0;
        $fileHasMore = false;
        $lastSummary = [];
        $rowsWithoutTecdocGain = 0;
        $rowsWithoutImageGain = 0;

        while (count($matched) < $targetMatches && $rowsScannedTotal < $maxScanRows) {
            $remaining = $maxScanRows - $rowsScannedTotal;
            $batchRows = min($onlyWithImage ? 200 : 120, max(40, $targetMatches * 4), $remaining);
            if ($batchRows <= 0) {
                break;
            }

            $batch = self::runPythonScanBatch($supplier, $path, $csvCursor, $batchRows, $onlyWithImage);
            $matchedBefore = count($matched);
            foreach ($batch['matched'] as $product) {
                if (!is_array($product)) {
                    continue;
                }
                $key = strtolower((string) ($product['sku_supplier'] ?? ''))
                    . '|' . strtolower((string) ($product['supplier'] ?? $supplier));
                if ($key === '|' || isset($seenKeys[$key])) {
                    continue;
                }
                $seenKeys[$key] = true;
                $matched[] = $product;
                if (count($matched) >= $targetMatches) {
                    break;
                }
            }

            $rowsInBatch = max(0, (int) ($batch['rows_total'] ?? 0));
            $tecdocInBatch = max(0, (int) ($batch['tecdoc_matched_count'] ?? 0));
            $rowsScannedTotal += $rowsInBatch;
            $csvCursor += $rowsInBatch;
            $fileHasMore = (bool) ($batch['file_has_more'] ?? false);
            if (is_array($batch['summary'] ?? null) && $batch['summary'] !== []) {
                $lastSummary = $batch['summary'];
            }

            // Early-exit doar pe lipsa match TecDoc — NU pe „match OK dar fără Poze”
            // (altfel Autototal/ELIT se opresc la 320 rânduri cu 0 carduri deși există match).
            if ($tecdocInBatch === 0 && $rowsInBatch > 0) {
                $rowsWithoutTecdocGain += $rowsInBatch;
            } else {
                $rowsWithoutTecdocGain = 0;
            }

            if (count($matched) === $matchedBefore && $rowsInBatch > 0) {
                $rowsWithoutImageGain += $rowsInBatch;
            } else {
                $rowsWithoutImageGain = 0;
            }

            // CSV fără match TecDoc deloc (ex. Autonet): oprește rapid.
            if ($rowsWithoutTecdocGain >= 320 && count($matched) === 0) {
                $fileHasMore = false;
                break;
            }
            if ($rowsWithoutTecdocGain >= 480 && count($matched) < $targetMatches) {
                $fileHasMore = false;
                break;
            }

            // „Doar cu imagine”: match TecDoc există, dar Poze rare — scanează adânc, apoi renunță.
            if ($onlyWithImage && count($matched) === 0 && $rowsWithoutImageGain >= 4000) {
                $fileHasMore = false;
                break;
            }
            if ($onlyWithImage && count($matched) < $targetMatches && $rowsWithoutImageGain >= 6000) {
                $fileHasMore = false;
                break;
            }

            if ($rowsInBatch <= 0 || !$fileHasMore) {
                break;
            }
        }

        if (count($matched) > $targetMatches) {
            $matched = array_slice($matched, 0, $targetMatches);
        }

        return [
            'matched' => $matched,
            'rows_scanned' => $rowsScannedTotal,
            'next_csv_row_offset' => $csvCursor,
            'file_has_more' => $fileHasMore,
            'summary' => $lastSummary,
        ];
    }

    /**
     * @return array{
     *   matched: list<array<string, mixed>>,
     *   tecdoc_matched_count: int,
     *   rows_total: int,
     *   file_has_more: bool,
     *   summary: array<string, mixed>
     * }
     */
    private static function runPythonScanBatch(
        string $supplier,
        string $path,
        int $skipRows,
        int $sampleRows,
        bool $onlyWithImage
    ): array {
        $sampleRows = max(1, min(5000, $sampleRows));
        $args = ['scan', '--file', $path, '--supplier', $supplier, '--no-archive', '--force'];
        $args[] = '--sample';
        $args[] = (string) $sampleRows;
        if ($skipRows > 0) {
            $args[] = '--skip-rows';
            $args[] = (string) $skipRows;
        }

        $pyTimeout = min(180, max(30, (int) ceil($sampleRows / 2) + 25));
        $result = import_run_python($args, $pyTimeout);
        if ($result['exit_code'] === 124) {
            throw new RuntimeException(
                'Scan Python timeout (' . $pyTimeout . 's) — reduce nr. produse/fișier sau folosește Cron.'
            );
        }
        if ($result['json'] === null) {
            throw new RuntimeException('Scan Python fără JSON: ' . mb_substr($result['raw'], 0, 400));
        }

        $items = $result['json'];
        $last = is_array($items) && $items !== [] ? $items[array_key_last($items)] : null;
        if (!is_array($last)) {
            throw new RuntimeException('Scan fără rezultat valid');
        }

        $payload = is_array($last['payload'] ?? null) ? $last['payload'] : null;
        if (!is_array($payload)) {
            throw new RuntimeException('Scan fără payload valid');
        }

        $payload = import_motor_apply_supplier_profile_to_payload($payload, strtolower($supplier));

        $productsAll = is_array($payload['products'] ?? null) ? $payload['products'] : [];
        $tecdocMatched = array_values(array_filter(
            $productsAll,
            static fn ($p): bool => is_array($p) && in_array((string) ($p['status'] ?? ''), self::MATCH_STATUSES, true)
        ));
        $tecdocMatchedCount = count($tecdocMatched);

        if ($onlyWithImage) {
            $payload = import_enrich_match_payload_images($payload);
            $payload = import_filter_scan_payload_by_image($payload, $sampleRows);
        }

        $products = is_array($payload['products'] ?? null) ? $payload['products'] : [];
        $matched = array_values(array_filter(
            $products,
            static fn ($p): bool => is_array($p) && in_array((string) ($p['status'] ?? ''), self::MATCH_STATUSES, true)
        ));

        $parse = is_array($last['parse'] ?? null) ? $last['parse'] : [];
        $rowsTotal = max(
            (int) ($parse['rows_total'] ?? 0),
            (int) ($payload['rows_parsed'] ?? 0)
        );
        $fileHasMore = (bool) ($parse['has_more'] ?? false);

        return [
            'matched' => $matched,
            'tecdoc_matched_count' => $tecdocMatchedCount,
            'rows_total' => $rowsTotal,
            'file_has_more' => $fileHasMore,
            'summary' => is_array($payload['summary'] ?? null) ? $payload['summary'] : [],
        ];
    }

    /**
     * Carduri ușoare din payload Python — fără ProductMatcher greu.
     *
     * @param list<array<string, mixed>> $products
     * @return list<array<string, mixed>>
     */
    public static function cardsFromMatchProducts(
        array $products,
        string $supplier,
        string $filename,
        bool $onlyWithImage
    ): array {
        $supplierKey = strtolower($supplier);
        $supplierType = import_motor_supplier_code_from_slug($supplierKey);
        if ($supplierType === '') {
            $supplierType = strtoupper($supplierKey);
        }
        $sourceFile = $supplierKey . '/' . $filename;
        $cards = [];

        foreach ($products as $p) {
            if (!is_array($p)) {
                continue;
            }
            $status = (string) ($p['status'] ?? '');
            if (!in_array($status, self::MATCH_STATUSES, true)) {
                continue;
            }
            if (empty($p['source_file'])) {
                $p['source_file'] = $sourceFile;
            }
            $card = self::minimalCardFromMatch($p, $supplierType, $supplierKey, $sourceFile, $status);
            if ($card === null) {
                continue;
            }
            if ($onlyWithImage && !self::cardHasImage($card)) {
                continue;
            }
            $cards[] = import_motor_normalize_card_purchase_prices($card);
        }

        return import_motor_normalize_card_list($cards);
    }

    /** @param array<string, mixed> $card */
    private static function cardHasImage(array $card): bool
    {
        if (!empty($card['hasImage']) || !empty($card['scrapedImageUrl']) || !empty($card['imageDisplayUrl'])) {
            return true;
        }
        $url = trim((string) ($card['imageUrl'] ?? ''));

        return $url !== '' && !str_contains(strtolower($url), 'placeholder');
    }

    /**
     * QWP cross-ref nu are coloană de preț — umple din cache Lista pret Autonet (ArtNr).
     *
     * @param array<string, mixed> $p
     */
    private static function fillQwpPriceFromAutonetList(array &$p): void
    {
        $existing = $p['price'] ?? $p['price_csv'] ?? null;
        if (is_numeric($existing) && (float) $existing > 0) {
            return;
        }

        $forceBrand = strtoupper(trim((string) ($p['force_brand'] ?? '')));
        $profile = strtolower(trim((string) ($p['supplier_profile'] ?? '')));
        $sourceFile = strtolower((string) ($p['source_file'] ?? $p['source_filename'] ?? ''));
        $isQwp = $forceBrand === 'QWP'
            || $profile === 'autonet_qwp'
            || str_contains($sourceFile, 'qwp');
        if (!$isQwp) {
            return;
        }

        $artNr = trim((string) ($p['art_nr'] ?? $p['sku_supplier'] ?? ''));
        if ($artNr === '') {
            return;
        }

        $price = self::lookupAutonetListPrice($artNr);
        if ($price === null) {
            return;
        }

        $p['price'] = $price;
        $p['price_csv'] = $price;
        $p['price_source'] = 'autonet_list';
    }

    private static function lookupAutonetListPrice(string $artNr): ?float
    {
        static $byCode = null;
        static $byNorm = null;

        $code = trim($artNr);
        if ($code === '') {
            return null;
        }

        if ($byCode === null) {
            $cachePath = (defined('IMPORT_STATE_DIR') ? IMPORT_STATE_DIR : dirname(__DIR__, 2) . '/state')
                . DIRECTORY_SEPARATOR . 'autonet-qwp-prices.json';
            if (!is_file($cachePath)) {
                $byCode = [];
                $byNorm = [];

                return null;
            }
            $raw = @file_get_contents($cachePath);
            $data = is_string($raw) ? json_decode($raw, true) : null;
            $byCode = is_array($data['by_code'] ?? null) ? $data['by_code'] : [];
            $byNorm = is_array($data['by_norm'] ?? null) ? $data['by_norm'] : [];
        }

        if (isset($byCode[$code]) && is_numeric($byCode[$code]) && (float) $byCode[$code] > 0) {
            return round((float) $byCode[$code], 4);
        }

        $norm = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $code) ?? '');
        if ($norm !== '' && isset($byNorm[$norm]) && is_numeric($byNorm[$norm]) && (float) $byNorm[$norm] > 0) {
            return round((float) $byNorm[$norm], 4);
        }

        return null;
    }

    /**
     * Autonet QWP: produs nou cu brand forțat + ArtNr; datele rămân din template-ul TecDoc.
     *
     * @param array<string, mixed> $card
     * @param array<string, mixed> $p
     * @return array<string, mixed>
     */
    private static function applyForcedProductIdentity(
        array $card,
        array $p,
        string $tecdocBrand,
        string $tecdocCode
    ): array {
        $forceBrand = trim((string) ($p['force_brand'] ?? ''));
        $artNr = trim((string) ($p['art_nr'] ?? ''));
        if ($forceBrand === '' && $artNr === '') {
            return $card;
        }

        if ($tecdocCode !== '' && trim((string) ($card['tecdocSku'] ?? '')) === '') {
            $card['tecdocSku'] = $tecdocCode;
        }
        if ($tecdocBrand !== '') {
            $card['tecdocBrand'] = $tecdocBrand;
            $card['matchedTecdocBrand'] = $tecdocBrand;
        }

        if ($forceBrand !== '') {
            $oldBrand = trim((string) ($card['brand'] ?? $tecdocBrand));
            $card['brand'] = $forceBrand;
            $card['forceBrand'] = $forceBrand;
            $card['title'] = self::swapBrandInText((string) ($card['title'] ?? ''), $oldBrand, $forceBrand);
            if (isset($card['description']) && is_string($card['description']) && $oldBrand !== '') {
                $card['description'] = self::swapBrandInText($card['description'], $oldBrand, $forceBrand);
            }
        }

        if ($artNr !== '') {
            $card['sku'] = $artNr;
            $card['supplierSku'] = $artNr;
            $card['productOem'] = $artNr;
            $card['artNr'] = $artNr;
            if (!empty($p['ref_nr'])) {
                $card['matchRefNr'] = trim((string) $p['ref_nr']);
            }
        }

        $card['isClonedFromTecdoc'] = true;

        return $card;
    }

    private static function swapBrandInText(string $text, string $oldBrand, string $newBrand): string
    {
        $text = trim($text);
        $oldBrand = trim($oldBrand);
        $newBrand = trim($newBrand);
        if ($text === '' || $newBrand === '') {
            return $text;
        }
        if ($oldBrand !== '' && strcasecmp($oldBrand, $newBrand) !== 0) {
            $replaced = preg_replace('/\b' . preg_quote($oldBrand, '/') . '\b/iu', $newBrand, $text);
            if (is_string($replaced) && $replaced !== '') {
                $text = $replaced;
            }
        }
        if (!str_contains(mb_strtoupper($text), mb_strtoupper($newBrand))) {
            $text = trim($text . ' ' . $newBrand);
        }

        return $text;
    }

    /** @return list<string> */
    private static function codeVariants(array $p): array
    {
        $sku = trim((string) ($p['sku_supplier'] ?? ''));
        $refNr = trim((string) ($p['ref_nr'] ?? ''));
        $codeNorm = strtoupper(preg_replace('/[^A-Z0-9]/', '', $sku) ?? '');
        $refNorm = strtoupper(preg_replace('/[^A-Z0-9]/', '', $refNr) ?? '');
        $internalSku = trim((string) ($p['matched_internal_sku'] ?? ''));
        $internalNorm = strtoupper(preg_replace('/[^A-Z0-9]/', '', $internalSku) ?? '');
        $variants = array_values(array_unique(array_filter([
            $refNr,
            $refNorm,
            ltrim($refNorm, '0'),
            $sku,
            $codeNorm,
            ltrim($codeNorm, '0'),
            $internalSku,
            $internalNorm,
            ltrim($internalNorm, '0'),
        ])));

        $matchedCodes = is_array($p['matched_codes'] ?? null) ? $p['matched_codes'] : [];
        foreach ($matchedCodes as $mc) {
            $mc = trim((string) $mc);
            if ($mc === '') {
                continue;
            }
            $mcNorm = strtoupper(preg_replace('/[^A-Z0-9]/', '', $mc) ?? '');
            $variants[] = $mc;
            if ($mcNorm !== '') {
                $variants[] = $mcNorm;
                $trimmed = ltrim($mcNorm, '0');
                if ($trimmed !== '' && $trimmed !== $mcNorm) {
                    $variants[] = $trimmed;
                }
            }
        }

        return array_values(array_unique(array_filter($variants)));
    }

    /** Card minimal din match Python — fără enrichment Base complet. */
    private static function minimalCardFromMatch(
        array $p,
        string $supplierType,
        string $supplierKey,
        string $sourceFile,
        string $status
    ): ?array {
        $sku = trim((string) ($p['sku_supplier'] ?? ''));
        $artNr = trim((string) ($p['art_nr'] ?? ''));
        $forceBrand = trim((string) ($p['force_brand'] ?? ''));
        $outputSku = $artNr !== '' ? $artNr : $sku;
        if ($outputSku === '') {
            return null;
        }
        $title = trim((string) ($p['matched_name'] ?? $p['name'] ?? $outputSku));
        if (mb_strlen($title) < 4) {
            return null;
        }
        self::fillQwpPriceFromAutonetList($p);
        $priceNet = isset($p['price_purchase_net']) && is_numeric($p['price_purchase_net'])
            ? (float) $p['price_purchase_net']
            : (import_motor_apply_feed_price($p['price'] ?? null, $supplierKey)
                ?? (isset($p['price']) && is_numeric($p['price']) ? (float) $p['price'] : 0.0));

        $tecdocBrand = (string) ($p['matched_brand'] ?? $p['brand'] ?? '');
        $card = [
            'sku' => $outputSku,
            'supplierSku' => $outputSku,
            'brand' => $forceBrand !== '' ? $forceBrand : $tecdocBrand,
            'title' => $title,
            'name' => (string) ($p['name'] ?? $title),
            'supplier' => $supplierType,
            'sourceSupplier' => $supplierKey,
            'sourceFile' => $sourceFile,
            'matchStatus' => $status,
            'matchMethod' => (string) ($p['match_method'] ?? ''),
            'cardBuild' => 'fast_tecdoc_match',
            'hasImage' => !empty($p['has_image']),
            'imageUrl' => (string) ($p['image_url'] ?? ''),
            'pricePurchaseNet' => $priceNet,
            'description' => (string) ($p['matched_name'] ?? ''),
            'parameters' => [],
            'compatCount' => 0,
        ];
        $card = self::applyForcedProductIdentity(
            $card,
            $p,
            $tecdocBrand,
            trim((string) ($p['matched_internal_sku'] ?? $p['ref_nr'] ?? ''))
        );
        $card = import_normalize_card_image_fields($card);
        $card = import_motor_normalize_card_purchase_prices($card);

        return $card;
    }
}
