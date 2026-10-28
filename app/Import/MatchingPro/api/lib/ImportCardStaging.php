<?php
declare(strict_types=1);

/**
 * Convertește carduri Import Pro → rânduri pentru import_stage_products_for_review.
 */
final class ImportCardStaging
{
    /**
     * @param list<array<string, mixed>> $cards
     * @param array<string, mixed> $options use_ollama (default false — staging rapid, fără blocaj LLM)
     * @return list<array<string, mixed>>
     */
    public static function cardsToProducts(array $cards, string $importLane = 'standard', array $options = []): array
    {
        return self::cardsToProductsDetailed($cards, $importLane, $options)['products'];
    }

    /**
     * @param list<array<string, mixed>> $cards
     * @param array<string, mixed> $options
     * @return array{products: list<array<string, mixed>>, rejected: list<array{key: string, reason: string}>}
     */
    public static function cardsToProductsDetailed(array $cards, string $importLane = 'standard', array $options = []): array
    {
        $products = [];
        $rejected = [];
        foreach ($cards as $card) {
            if (!is_array($card)) {
                $rejected[] = ['key' => '—', 'reason' => 'Card invalid (non-array)'];
                continue;
            }
            $product = self::cardToProduct($card, $importLane, $options);
            if ($product !== null) {
                $products[] = $product;
                continue;
            }
            $rejected[] = [
                'key' => self::cardStagingKey($card),
                'reason' => self::cardRejectReason($card),
            ];
        }

        return ['products' => $products, 'rejected' => $rejected];
    }

    /** @param array<string, mixed> $card */
    public static function cardStagingKey(array $card): string
    {
        $sku = trim((string) ($card['supplierSku'] ?? $card['sku'] ?? ''));
        $src = trim((string) ($card['sourceFile'] ?? ''));
        $brand = trim((string) ($card['brand'] ?? ''));

        return ($src !== '' ? $src . ' · ' : '') . ($brand !== '' ? $brand . ' · ' : '') . ($sku !== '' ? $sku : '?');
    }

    /** @param array<string, mixed> $card */
    public static function cardRejectReason(array $card): string
    {
        $name = trim((string) ($card['title'] ?? $card['matchedName'] ?? $card['name'] ?? ''));
        $code = self::resolveTecdocProductCode($card);
        if ($name === '' && $code === '') {
            return 'Lipsește titlu și cod TecDoc';
        }
        if ($name === '') {
            return 'Lipsește titlul produsului';
        }
        if ($code === '') {
            return 'Lipsește codul TecDoc (tecdocSku/ART_CODE_1)';
        }
        foreach (['imageDisplayUrl', 'scrapedImageUrl', 'imageUrl'] as $key) {
            if (trim((string) ($card[$key] ?? '')) !== '') {
                return 'Card invalid pentru staging';
            }
        }
        if (!empty($card['hasImage']) && !empty($card['imagePath'])) {
            return 'Card invalid pentru staging';
        }

        return 'Fără imagine (Poze/Autopartner) — nu se trimite în coadă';
    }

    /**
     * @param array<string, mixed> $card
     * @param array<string, mixed> $options use_ollama (default false)
     * @return array<string, mixed>|null
     */
    public static function cardToProduct(array $card, string $importLane = 'standard', array $options = []): ?array
    {
        $name = trim((string) ($card['title'] ?? $card['matchedName'] ?? $card['name'] ?? ''));
        // Provizoriu: acceptă și SKU ca să nu respingem cardul înainte de lookup TecDoc.
        $code = self::resolveTecdocProductCode($card);
        if ($code === '') {
            $code = trim((string) ($card['supplierSku'] ?? $card['sku'] ?? ''));
        }
        if ($name === '' || $code === '') {
            return null;
        }

        if (mb_strlen($name) > 490) {
            $name = mb_substr($name, 0, 490);
        }

        $rawArtName = trim((string) ($card['name'] ?? ''));
        if ($rawArtName === '') {
            $audit = is_array($card['tecdocAudit'] ?? null) ? $card['tecdocAudit'] : null;
            if ($audit !== null) {
                $rawArtName = trim((string) ($audit['name'] ?? ''));
            }
        }
        if ($rawArtName === '') {
            $rawArtName = trim((string) ($card['matchedName'] ?? ''));
        }

        $supplier = strtoupper(trim((string) ($card['sourceSupplier'] ?? $card['supplier'] ?? '')));
        $brand = trim((string) ($card['forceBrand'] ?? $card['brand'] ?? ''));
        $images = self::resolveImages($card);
        // Imaginea obligatorie se cere explicit (require_image / reject_no_image).
        // `matching_pro_fast` înseamnă doar staging rapid (fără Ollama / scrape) — nu politică
        // de conținut: produsele fără imagine intră în coadă pe banda no_image (țintă vitrină)
        // și sunt oprite la publicare, nu pierdute la scanare.
        // Regula de bază: încearcă mereu biblioteca Poze/AP (+ art_cross) înainte de a rămâne fără imagine.
        if ($images === []) {
            try {
                if (function_exists('import_apply_base_scan_image_policy')) {
                    $attached = import_apply_base_scan_image_policy($card);
                    if (is_array($attached)) {
                        $card = $attached;
                        $images = self::resolveImages($card);
                    }
                } elseif (function_exists('import_attach_local_image_to_card')) {
                    $attached = import_attach_local_image_to_card($card);
                    if (is_array($attached)) {
                        $card = $attached;
                        $images = self::resolveImages($card);
                    }
                }
            } catch (\Throwable) {
                // Lipsa bibliotecii de imagini nu trebuie să blocheze coada; produsul intră pe fluxul fără imagine.
            }
        }
        $requireImageHard = !empty($options['require_image'])
            || !empty($options['reject_no_image']);
        if ($requireImageHard && $images === []) {
            return null; // require_image_hard_gate — deja încercat attach mai sus
        }
        if ($requireImageHard && $images !== []) {
            $src = strtolower(trim((string) ($card['imageSource'] ?? '')));
            $images = array_values(array_filter(
                $images,
                static fn ($u): bool => is_string($u) && self::stagingImageIsTrusted($u, $src)
            ));
        }
        if ($requireImageHard && $images === []) {
            return null; // require_image_hard_gate
        }
        $purchaseNet = self::resolvePurchaseNet($card);

        $nameHay = $name;
        $category = trim((string) ($card['category'] ?? ''));
        $subcategory = trim((string) ($card['subcategory'] ?? ''));
        $categoryMatch = null;
        // QWP: OEM/cod produs = ArtNr din fișier; matchedTecdocSku rămâne doar audit TecDoc
        $oemCode = trim((string) ($card['productOem'] ?? $card['artNr'] ?? $card['sku'] ?? $code));
        // Staging / cron: fără Ollama by default — altfel fiecare card blochează request-ul (TimeOut pe volum).
        $useOllama = array_key_exists('use_ollama', $options)
            ? !empty($options['use_ollama'])
            : false;
        $skipCategoryMatch = !empty($options['skip_category_match'])
            || !empty($options['matching_pro_fast'])
            || !empty($card['showcaseType']);
        $matchArtName = $rawArtName !== '' ? $rawArtName : trim((string) ($card['matchedName'] ?? ''));
        $audit = is_array($card['tecdocAudit'] ?? null) ? $card['tecdocAudit'] : null;
        if ($matchArtName === '' && $audit !== null) {
            $matchArtName = trim((string) ($audit['name'] ?? ''));
        }
        if (!$skipCategoryMatch && ($category === '' || $subcategory === '') && class_exists('Besoiu\\Services\\CategoryMatchService')) {
            try {
                $categoryMatch = (new \Besoiu\Services\CategoryMatchService())->match([
                    'name' => $matchArtName !== '' ? $matchArtName : $nameHay,
                    'art_name' => $matchArtName,
                    'brand' => $brand,
                    'description' => trim((string) ($card['description'] ?? '')),
                    'specs' => trim((string) ($card['specs'] ?? '')),
                    'oem' => $oemCode,
                    'use_ollama' => $useOllama,
                    'use_tecdoc' => !$useOllama,
                ]);
                if (!empty($categoryMatch['ok'])) {
                    if ($category === '' && !empty($categoryMatch['category'])) {
                        $category = (string) $categoryMatch['category'];
                    }
                    if ($subcategory === '' && !empty($categoryMatch['subcategory'])) {
                        $subcategory = (string) $categoryMatch['subcategory'];
                    }
                }
            } catch (\Throwable) {
                // fallback la reguli locale
            }
        }
        if (!$skipCategoryMatch && ($category === '' || $subcategory === '') && function_exists('import_apply_taxonomy_gaps')) {
            $stub = import_apply_taxonomy_gaps([
                'pName' => $matchArtName !== '' ? $matchArtName : $nameHay,
                'pBrand' => $brand,
                'pOem' => $oemCode,
                'pCategory' => $category,
                'pSubcategory' => $subcategory,
                'raw_json' => json_encode([
                    '__tecdoc_art_name' => $matchArtName,
                    'product_summary' => ['tecdoc_art_name' => $matchArtName],
                ], JSON_UNESCAPED_UNICODE),
            ]);
            if ($category === '') {
                $category = trim((string) ($stub['pCategory'] ?? ''));
            }
            if ($subcategory === '') {
                $subcategory = trim((string) ($stub['pSubcategory'] ?? ''));
            }
        }

        $imageSource = self::resolveImageSource($card, $images);
        $sourceRows = self::extractSourceRows($card);
        // Marketplace = HTML Base.html complet; Website = antet + specs (taburi titlu rămân separate).
        $descriptionMarketplace = self::resolveBaseDescriptionHtml($card, $sourceRows);
        if (mb_strlen($descriptionMarketplace) > 50000) {
            $descriptionMarketplace = mb_substr($descriptionMarketplace, 0, 50000) . '…';
        }
        $descriptionWebsite = self::resolveWebsiteDescriptionHtml($card, $sourceRows, $descriptionMarketplace);
        if (mb_strlen($descriptionWebsite) > 20000) {
            $descriptionWebsite = mb_substr($descriptionWebsite, 0, 20000) . '…';
        }
        $description = $descriptionMarketplace;
        $vehicle = function_exists('import_extract_vehicle_from_title')
            ? import_extract_vehicle_from_title($name)
            : self::extractVehicleFromTitle($name);
        $compatText = trim((string) ($card['compatText'] ?? ''));
        // REGULA COMPAT FULL: nu tăia lista — toate fitment-urile rămân în coadă.
        if ($compatText === '' && $descriptionMarketplace !== '') {
            $compatText = function_exists('import_compat_text_from_description_html')
                ? import_compat_text_from_description_html($descriptionMarketplace)
                : self::extractCompatTextFromDescription($descriptionMarketplace);
        }

        // Completează marcă/model din lista Compatibil cu (layout vehicul), nu din Specificații.
        if (($vehicle['marca'] === '' || $vehicle['model'] === '') && $compatText !== '') {
            $fromCompat = self::extractVehicleFromCompatLines($compatText);
            if ($vehicle['marca'] === '' && $fromCompat['marca'] !== '') {
                $vehicle['marca'] = $fromCompat['marca'];
            }
            if ($vehicle['model'] === '' && $fromCompat['model'] !== '') {
                $vehicle['model'] = $fromCompat['model'];
            }
        }

        $motorizare = $vehicle['motorizare'];
        if ($motorizare === '' && $compatText !== '' && function_exists('import_extract_motorizare_from_compat_sources')) {
            $motorizare = import_extract_motorizare_from_compat_sources([
                'pMarca' => $vehicle['marca'],
                'pModel' => $vehicle['model'],
                'pCompatibilitati' => $compatText,
                // Fără description HTML — evită „Specificații tehnice” în pMotorizare.
                'raw_json' => '{}',
            ]);
        }
        if (function_exists('import_sanitize_motorizare_value')) {
            $motorizare = import_sanitize_motorizare_value($motorizare);
        }

        // Fallback solid: primul entry TecDoc cu CAR_BRAND/CAR_MODEL/CAR_TYP.

        if ($vehicle['marca'] === '' || $vehicle['model'] === '' || trim((string) $motorizare) === '') {

            $fromRows = self::vehicleFromSourceRows($sourceRows);

            if ($vehicle['marca'] === '' && $fromRows['marca'] !== '') {

                $vehicle['marca'] = $fromRows['marca'];

            }

            if ($vehicle['model'] === '' && $fromRows['model'] !== '') {

                $vehicle['model'] = $fromRows['model'];

            }

            if (trim((string) $motorizare) === '' && $fromRows['motorizare'] !== '') {

                $motorizare = $fromRows['motorizare'];

            }

        }

        // Layout coadă: un singur vehicul principal (nu dump multi-marcă / motorizări).
        $vehicle = self::normalizePrimaryVehicle($vehicle, $motorizare);
        $motorizare = $vehicle['motorizare'];

        // Denumire de bază = ART_NAME TecDoc; titlurile pe canal se formează după template.
        $displayName = $rawArtName !== '' ? $rawArtName : $name;

        // Cod canonic = TecDoc (ART_CODE_1), nu SKU Autonet tip 0.1954.1.
        $tecdocCode = self::resolveTecdocProductCode($card, $sourceRows);
        if ($tecdocCode !== '') {
            $code = $tecdocCode;
        }
        $supplierSku = trim((string) ($card['supplierSku'] ?? $card['sku'] ?? ''));

        $raw = [
            'schema' => 'product_import_v2',
            'import_mode' => 'import_pro_manual',
            'import_lane' => $importLane,
            'import_pro_card' => true,
            '__tecdoc_art_name' => $rawArtName !== '' ? $rawArtName : $displayName,
            'product_summary' => [
                'title' => $name,
                'tecdoc_art_name' => $rawArtName !== '' ? $rawArtName : $displayName,
                'parameters' => $card['parameters'] ?? [],
                'description' => $descriptionMarketplace,
                'description_website' => $descriptionWebsite,
                'description_marketplace' => $descriptionMarketplace,
                'compat_text' => $compatText,
                'compat_count' => (int) ($card['compatCount'] ?? 0),
                'vehicle' => $vehicle,
            ],
            // Rânduri TecDoc pentru regenerare generateDescription() Base.html
            'source_rows' => $sourceRows,
            /* MATCHED_PRODUCT_ID_RAW */
            'matched_product_id' => (int) ($card['productId'] ?? $card['matched_product_id'] ?? $card['matchedProductId'] ?? 0),
            'product_id' => (int) ($card['productId'] ?? $card['matched_product_id'] ?? $card['matchedProductId'] ?? 0),
            'match_status' => (string) ($card['matchStatus'] ?? ''),
            'match_method' => (string) ($card['matchMethod'] ?? ''),
            'category_match' => $categoryMatch,
            'source_file' => (string) ($card['sourceFile'] ?? ''),
            'source_supplier_sku' => $supplierSku !== '' ? $supplierSku : trim((string) ($card['supplierSku'] ?? '')),
            'tecdoc_sku' => trim((string) ($card['tecdocSku'] ?? $card['matchedTecdocSku'] ?? $code)),
            'tecdoc_brand' => trim((string) ($card['tecdocBrand'] ?? $card['matchedTecdocBrand'] ?? '')),
            'force_brand' => trim((string) ($card['forceBrand'] ?? '')),
            'art_nr' => trim((string) ($card['artNr'] ?? '')),
            'match_ref_nr' => trim((string) ($card['matchRefNr'] ?? '')),
            'cloned_from_tecdoc' => !empty($card['isClonedFromTecdoc']),
            'tecdoc_audit' => $card['tecdocAudit'] ?? null,
            '__image_source' => $imageSource,
            '__image_query' => $code,
        ];

        if ($importLane === 'showcase') {
            $raw['__vitrina_candidate'] = true;
            $raw['__showcase_type'] = (string) ($card['showcaseType'] ?? '');
            if ($category === '' || $subcategory === '') {
                $showcaseTax = self::resolveShowcaseTaxonomy((string) ($card['showcaseType'] ?? ''));
                if ($category === '' && $showcaseTax['category'] !== '') {
                    $category = $showcaseTax['category'];
                }
                if ($subcategory === '' && $showcaseTax['subcategory'] !== '') {
                    $subcategory = $showcaseTax['subcategory'];
                }
            }
        }

        if ($purchaseNet !== null) {
            $raw['price_purchase_net'] = round($purchaseNet, 2);
            $raw['product_summary']['price_purchase_net'] = round($purchaseNet, 2);
        }
        $purchaseVat = self::resolvePurchaseVat($card, $purchaseNet);
        if ($purchaseVat !== null) {
            $raw['price_purchase_vat'] = $purchaseVat;
            $raw['product_summary']['price_purchase_vat'] = $purchaseVat;
        }
        if (isset($card['priceNet']) && is_numeric($card['priceNet'])) {
            $raw['price_csv'] = round((float) $card['priceNet'], 2);
        }
        if ($supplier !== '') {
            $raw['source_supplier'] = $supplier;
        }

        $nameMarketplace = trim((string) ($card['titleMarketplace'] ?? $card['title_pieseauto'] ?? ''));

        return [
            'pName' => $displayName,
            'pNameMarketplace' => $nameMarketplace !== '' ? $nameMarketplace : $displayName,
            'pCode' => $code,
            'pBrand' => $brand,
            'pMarca' => $vehicle['marca'],
            'pModel' => $vehicle['model'],
            'pMotorizare' => $motorizare,
            'pOem' => (($oeFromCross = self::oemCodesFromArtCross($sourceRows)) !== [] ? implode(', ', $oeFromCross) : ($oemCode !== '' ? $oemCode : $code)),
            'pBasePrice' => $purchaseNet !== null ? (string) round($purchaseNet, 2) : '',
            // Preț final doar după Adaos comercial (manual, în coadă) — nu din achiziție+TVA.
            'pPrice' => '',
            'pStock' => trim((string) ($card['stock'] ?? '1')),
            'pSupplier' => $supplier,
            'pImages' => json_encode($images, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'pImageSource' => $images !== [] ? $imageSource : 'missing',
            'pCategory' => $category,
            'pSubcategory' => $subcategory,
            'pCompatibilitati' => $compatText,
            // pNote / Marketplace = Base.html complet; Website = fără Compatibil nested / OE.
            'pNote' => $descriptionMarketplace,
            'pNoteWebsite' => $descriptionWebsite,
            'pNoteMarketplace' => $descriptionMarketplace,
            'raw_json' => json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];
    }

    /**
     * Rânduri TecDoc (ART_*) din card — pentru generateDescription Base.html.
     *
     * @param array<string, mixed> $card
     * @return list<array<string, mixed>>
     */
    private static function extractSourceRows(array $card): array
    {
        foreach (['sourceRows', 'source_rows', 'tecdocEntries', 'entries'] as $key) {
            if (empty($card[$key]) || !is_array($card[$key])) {
                continue;
            }
            $rows = [];
            foreach ($card[$key] as $row) {
                if (is_array($row)) {
                    $rows[] = $row;
                }
            }
            if ($rows !== []) {
                return $rows; // full — fără plafon 250
            }
        }

        return [];
    }

    /** True dacă HTML-ul e deja în formatul Base.html / htmlviewer (nested ul). */
    public static function isBaseDescriptionFormat(string $html): bool
    {
        $html = trim($html);
        if ($html === '') {
            return false;
        }
        // Format vechi Import Pro: o linie „MARCĂ: model1; model2”
        if (preg_match('/<p><b>\s*Compatibil cu:\s*<\/b><\/p>/iu', $html)) {
            return false;
        }
        if (preg_match('/Compatibil\s+cu\s+urmatoarele\s+modele\s+auto/iu', $html)
            && preg_match('/<li><b>[^<]+<\/b>\s*<ul>/i', $html)
        ) {
            return true;
        }

        return str_contains($html, 'Specificatii tehnice:')
            && (bool) preg_match('/\(Cod:\s*[^)]+\)/u', $html)
            && !str_contains($html, 'Compatibil cu:');
    }

    /**
     * Descriere HTML = generateDescription() din Procesare fisier import Base.html.
     * Regenerare din sourceRows / TecDoc dacă e format vechi plat.
     *
     * @param array<string, mixed> $card
     * @param list<array<string, mixed>> $sourceRows
     */
    private static function resolveBaseDescriptionHtml(array $card, array &$sourceRows): string
    {
        $description = trim((string) ($card['description'] ?? ''));
        if (self::isBaseDescriptionFormat($description)) {
            return $description;
        }

        require_once __DIR__ . '/ImportTecdocMysqlCardBuilder.php';

        if ($sourceRows !== []) {
            $html = trim(ImportTecdocMysqlCardBuilder::descriptionFromEntries($sourceRows));
            if ($html !== '') {
                return $html;
            }
        }

        $brand = trim((string) ($card['forceBrand'] ?? $card['brand'] ?? $card['tecdocBrand'] ?? ''));
        $code = trim((string) ($card['sku'] ?? $card['supplierSku'] ?? $card['tecdocSku'] ?? $card['artNr'] ?? ''));
        if ($brand === '' || $code === '') {
            return $description;
        }

        try {
            require_once __DIR__ . '/ImportTecdocMysqlEnrichment.php';
            $entries = ImportTecdocMysqlEnrichment::lookupEntries($brand, $code);
        } catch (\Throwable) {
            $entries = null;
        }

        if (!is_array($entries) || $entries === []) {
            return $description;
        }

        $sourceRows = $entries; // full — fără plafon 250
        $html = trim(ImportTecdocMysqlCardBuilder::descriptionFromEntries($entries));

        return $html !== '' ? $html : $description;
    }

    /**
     * Descriere Website = același HTML Base.html (specs + compat + OE).
     *
     * @param array<string, mixed> $card
     * @param list<array<string, mixed>> $sourceRows
     */
    private static function resolveWebsiteDescriptionHtml(
        array $card,
        array $sourceRows,
        string $marketplaceHtml
    ): string {
        self::ensureImportBaseLib();

        if ($sourceRows !== [] && function_exists('import_base_generate_description')) {
            $html = trim((string) import_base_generate_description($sourceRows));
            if ($html !== '') {
                return $html;
            }
        }

        if ($marketplaceHtml !== '') {
            return $marketplaceHtml;
        }

        return trim((string) ($card['descriptionWebsite'] ?? $card['description_website'] ?? ''));
    }

    private static function ensureImportBaseLib(): void
    {
        if (function_exists('import_base_generate_description_website')) {
            return;
        }
        $candidates = [
            dirname(__DIR__, 4) . '/Backend/src/Controllers/Produse/import_base_lib.php',
            dirname(__DIR__, 5) . '/app/Backend/src/Controllers/Produse/import_base_lib.php',
        ];
        if (defined('BESOIU_ROOT')) {
            array_unshift(
                $candidates,
                rtrim((string) BESOIU_ROOT, '/\\') . '/app/Backend/src/Controllers/Produse/import_base_lib.php'
            );
        }
        foreach ($candidates as $path) {
            if (is_file($path)) {
                require_once $path;
                return;
            }
        }
    }

    /**
     * @return array{marca:string,model:string,motorizare:string}
     */
    private static function extractVehicleFromTitle(string $title): array
    {
        if (function_exists('import_extract_vehicle_from_title')) {
            return import_extract_vehicle_from_title($title);
        }

        return ['marca' => '', 'model' => '', 'motorizare' => ''];
    }

    private static function extractCompatTextFromDescription(string $html): string
    {
        if (function_exists('import_compat_text_from_description_html')) {
            return import_compat_text_from_description_html($html);
        }
        if ($html === '') {
            return '';
        }

        if (!preg_match(
            '/Compatibil\s+cu(?:\s+urmatoarele\s+modele\s+auto)?\s*:?\s*<\/(?:b|strong|p|span)[^>]*>\s*<ul>(.*)$/is',
            $html,
            $matches
        )) {
            return '';
        }

        $block = (string) $matches[1];
        if (preg_match('/^(.*?)(?:<p[^>]*>\s*<b>\s*Coduri\s+OE)/is', $block, $cut)) {
            $block = (string) $cut[1];
        }

        $lines = [];
        if (preg_match_all('/<li[^>]*>(.*?)<\/li>/is', $block, $items)) {
            foreach ($items[1] as $item) {
                $line = trim(html_entity_decode(strip_tags((string) $item), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
                $line = trim((string) preg_replace('/\s+/u', ' ', $line));
                if ($line !== '' && !str_starts_with($line, '…')) {
                    $lines[] = $line;
                }
            }
        }

        return implode("\n", array_slice($lines, 0, 20));
    }

    /**
     * Vehicul principal din text compat (public pentru repair CLI).
     *
     * @return array{marca:string,model:string,motorizare:string}
     */
    public static function primaryVehicleFromCompatText(string $compatText): array
    {
        return self::normalizePrimaryVehicle(self::extractVehicleFromCompatLines($compatText));
    }

    /**
     * Din linii „TOYOTA C-HR 1.8 …” → marcă / model pentru layout vehicul.
     *
     * @return array{marca:string,model:string,motorizare:string}
     */

    /**
     * @param list<array<string, mixed>> $sourceRows
     * @return array{marca:string,model:string,motorizare:string}
     */
    private static function vehicleFromSourceRows(array $sourceRows): array
    {
        foreach ($sourceRows as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $brand = trim((string) ($entry['CAR_BRAND'] ?? ''));
            $model = trim((string) ($entry['CAR_MODEL'] ?? ''));
            $typ = trim((string) ($entry['CAR_TYP'] ?? ''));
            if ($brand === '') {
                continue;
            }
            $model = trim((string) preg_replace('/\([^)]*\)/u', '', $model));
            $model = trim((string) preg_replace('/\s+/u', ' ', $model));
            $typ = trim((string) preg_replace('/\([^)]*\)/u', '', $typ));
            $typ = trim((string) preg_replace('/\s+/u', ' ', $typ));

            return [
                'marca' => $brand,
                'model' => $model,
                'motorizare' => $typ,
            ];
        }

        return ['marca' => '', 'model' => '', 'motorizare' => ''];
    }
    private static function extractVehicleFromCompatLines(string $compatText): array
    {
        $empty = ['marca' => '', 'model' => '', 'motorizare' => ''];
        $compatText = trim($compatText);
        if ($compatText === '') {
            return $empty;
        }

        $allowed = function_exists('import_base_allowed_car_brands')
            ? import_base_allowed_car_brands()
            : [];
        $marci = [];
        $modele = [];
        $motorizari = [];

        foreach (preg_split('/\r?\n|;/u', $compatText) ?: [] as $line) {
            $line = trim(html_entity_decode($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
            $line = trim((string) preg_replace('/\s*[·•].*$/u', '', $line));
            $line = trim((string) preg_replace('/\s*\([^)]*\)\s*$/u', '', $line));
            if ($line === '') {
                continue;
            }

            // Format tip „LAND ROVER: FREELANDER …” / „ALFA ROMEO: …” / „RENAULT: MEGANE III …”.
            $brandToken = '';
            $rest = $line;
            $resolvedBrand = '';
            $multiBrandCandidates = [];
            if (preg_match('/^(.+?)\s*:\s*(.+)$/u', $line, $m)) {
                $left = trim((string) $m[1]);
                $rest = trim((string) $m[2]);
                $leftTokens = preg_split('/\s+/u', $left) ?: [];
                for ($n = min(3, count($leftTokens)); $n >= 1; $n--) {
                    $multiBrandCandidates[] = trim(implode(' ', array_slice($leftTokens, 0, $n)));
                }
            } else {
                $tokens = preg_split('/\s+/u', $line) ?: [];
                for ($n = min(3, count($tokens)); $n >= 1; $n--) {
                    $cand = trim(implode(' ', array_slice($tokens, 0, $n)));
                    if ($cand !== '') {
                        $multiBrandCandidates[] = $cand;
                    }
                }
            }
            foreach ($multiBrandCandidates as $cand) {
                $candKey = function_exists('mb_strtoupper')
                    ? mb_strtoupper($cand, 'UTF-8')
                    : strtoupper($cand);
                $candKey = rtrim($candKey, ':');
                if ($candKey === 'VOLKSWAGEN' && isset($allowed['VW'])) {
                    $candKey = 'VW';
                } elseif ($candKey === 'MERCEDES' && isset($allowed['MERCEDES-BENZ'])) {
                    $candKey = 'MERCEDES-BENZ';
                }
                if ($allowed === [] || isset($allowed[$candKey])) {
                    $resolvedBrand = $candKey;
                    if (preg_match('/^(.+?)\s*:\s*(.+)$/u', $line, $mm)) {
                        $left = trim((string) $mm[1]);
                        $rest = trim((string) $mm[2]);
                        $leftTokens = preg_split('/\s+/u', $left) ?: [];
                        $brandParts = preg_split('/\s+/u', $cand) ?: [];
                        $extraLeft = array_slice($leftTokens, count($brandParts));
                        if ($extraLeft !== []) {
                            $rest = trim(implode(' ', $extraLeft) . ' ' . $rest);
                        }
                    } else {
                        $tokens = preg_split('/\s+/u', $line) ?: [];
                        $brandParts = preg_split('/\s+/u', $cand) ?: [];
                        $rest = trim(implode(' ', array_slice($tokens, count($brandParts))));
                    }
                    break;
                }
            }
            if ($resolvedBrand === '') {
                continue;
            }
            $brandKey = $resolvedBrand;
            $marci[$brandKey] = true;

            // Model = token(i) (+ generație); restul = motorizare.
            // Dacă restul e doar listă de mărci (AUDI FIAT FORD…), nu seta model.
            if ($rest !== '') {
                $restTokens = array_values(array_filter(array_map('trim', preg_split('/\s+/u', $rest) ?: [])));
                $restOnlyBrands = $restTokens !== [];
                if ($restOnlyBrands && $allowed !== []) {
                    $i = 0;
                    $nTok = count($restTokens);
                    while ($i < $nTok) {
                        $joined = null;
                        for ($w = min(3, $nTok - $i); $w >= 1; $w--) {
                            $cand = trim(implode(' ', array_slice($restTokens, $i, $w)));
                            $ck = function_exists('mb_strtoupper') ? mb_strtoupper($cand, 'UTF-8') : strtoupper($cand);
                            if ($ck === 'VOLKSWAGEN') {
                                $ck = 'VW';
                            } elseif ($ck === 'MERCEDES') {
                                $ck = 'MERCEDES-BENZ';
                            }
                            if (isset($allowed[$ck])) {
                                $joined = $ck;
                                $i += $w;
                                break;
                            }
                        }
                        if ($joined === null) {
                            $restOnlyBrands = false;
                            break;
                        }
                    }
                } else {
                    $restOnlyBrands = false;
                }

                if (!$restOnlyBrands) {
                    $model = trim((string) ($restTokens[0] ?? ''));
                    $consumed = 0;
                    if ($model !== '' && !preg_match('/^\d/', $model)) {
                        $consumed = 1;
                        // Cuvinte alpha suplimentare (SANTA FE, NEW BEETLE, RANGE ROVER SPORT)
                        while (
                            isset($restTokens[$consumed])
                            && preg_match('/^[A-Za-z][A-Za-z\'\-]*$/u', (string) $restTokens[$consumed])
                            && !preg_match('/^(I{1,3}|IV|V|VI|VII|VIII|IX|X)$/u', (string) $restTokens[$consumed])
                            && !preg_match('/^(TDI|TSI|CDI|HDI|MPI|LPG|CRDI|TD4|SD4|ED4|GTS|CVVT|VVT|TFSI|BLUEHDI|XDRIVE|SDRIVE|4MOTION|4MATIC|QUATTRO)$/iu', (string) $restTokens[$consumed])
                            && !preg_match('/^(CAROSERIE|LIMUZINA|HATCHBACK|COMBI|BUS|VAN|BREAK|COUPE|CABRIO|CABRIOLET|SPORTBACK|TOURING|SW)$/iu', (string) $restTokens[$consumed])
                        ) {
                            $next = (string) $restTokens[$consumed];
                            $after = $restTokens[$consumed + 1] ?? null;
                            $afterIsGenOrEngine = $after !== null && (
                                preg_match('/^(I{1,3}|IV|V|VI|VII|VIII|IX|X|[A-G]|[1-9])$/u', (string) $after)
                                || preg_match('/^\d+\.\d+/u', (string) $after)
                                || preg_match('/^(TDI|TSI|CDI|HDI|MPI|LPG|CRDI)/iu', (string) $after)
                            );
                            $looksCompound = preg_match('/^(FE|ROVER|BEETLE|CHEROKEE|SPORT|CLASS|CROSSER|TEPEE)$/iu', $next);
                            if ($afterIsGenOrEngine || $looksCompound) {
                                $model .= ' ' . $next;
                                $consumed++;
                                if ($consumed >= 3) {
                                    break;
                                }
                                continue;
                            }
                            break;
                        }
                        // Generație: romane, literă A-G (Insignia A), sau cifră 1-9 — nu „28”/„150”.
                        if (isset($restTokens[$consumed]) && preg_match('/^(I{1,3}|IV|V|VI|VII|VIII|IX|X|[A-G]|[1-9]|B[4-9]|MK?[1-9]|C[1-9]|T[4-7])$/iu', (string) $restTokens[$consumed])) {
                            $model .= ' ' . trim((string) $restTokens[$consumed]);
                            $consumed++;
                        }
                        $modele[$model] = true;
                        $motorRest = trim(implode(' ', array_slice($restTokens, $consumed)));
                        if ($motorRest !== '' && !isset($motorizari[$model])) {
                            $motorizari[$model] = $motorRest;
                        }
                    }
                }
            }
        }

        $brandList = array_keys($marci);
        $modelList = array_keys($modele);
        $primaryModel = (string) ($modelList[0] ?? '');
        $primaryMotor = $primaryModel !== '' ? (string) ($motorizari[$primaryModel] ?? '') : '';

        // Un singur vehicul principal pentru coloanele pMarca/pModel (nu listă multi-marcă).
        return [
            'marca' => (string) ($brandList[0] ?? ''),
            'model' => $primaryModel,
            'motorizare' => $primaryMotor,
        ];
    }

    /**
     * @param array{marca?:string,model?:string,motorizare?:string} $vehicle
     * @return array{marca:string,model:string,motorizare:string}
     */
    private static function normalizePrimaryVehicle(array $vehicle, string $motorizare = ''): array
    {
        $marca = trim((string) ($vehicle['marca'] ?? ''));
        $model = trim((string) ($vehicle['model'] ?? ''));
        $motor = trim($motorizare !== '' ? $motorizare : (string) ($vehicle['motorizare'] ?? ''));

        // Dump greșit tip „RENAULT, MEGANE” + „III,, VOLVO” → RENAULT / MEGANE III.
        if (str_contains($marca, ',')) {
            $marcaParts = array_values(array_filter(array_map('trim', explode(',', $marca))));
            $marca = (string) ($marcaParts[0] ?? '');
            if ($model === '' || preg_match('/^(I{1,3}|IV|V|\d+)$/u', $model)) {
                $maybeModel = (string) ($marcaParts[1] ?? '');
                if ($maybeModel !== '' && !preg_match('/^(VOLVO|AUDI|BMW|VW|FORD|OPEL|TOYOTA|SKODA|SEAT|DACIA)$/iu', $maybeModel)) {
                    $model = trim($maybeModel . ($model !== '' ? ' ' . $model : ''));
                }
            }
        }
        if (str_contains($model, ',')) {
            $model = trim((string) explode(',', $model)[0]);
        }
        // Model contaminat cu alte mărci (ex. „MEGANE III,, VOLVO”).
        if (preg_match('/\b(VOLVO|AUDI|BMW|VW|FORD|OPEL|RENAULT|TOYOTA|SKODA|SEAT|DACIA|PEUGEOT|CITROEN|MERCEDES)\b/iu', $model, $m)
            && stripos($model, (string) $m[1]) !== 0
        ) {
            $model = trim((string) preg_replace('/,+\s*' . preg_quote((string) $m[1], '/') . '.*$/iu', '', $model));
        }
        // Fragmente din branduri multi-cuvânt greșit luate ca model: ROVER (din LAND ROVER), ROMEO, BENZ.
        $modelFold = function_exists('mb_strtoupper') ? mb_strtoupper($model, 'UTF-8') : strtoupper($model);
        if (in_array($modelFold, ['ROVER', 'ROMEO', 'BENZ', 'LAND', 'ALFA', 'RANGE'], true)) {
            $model = '';
        }
        // Dacă motorizarea începe cu modelul (C4 II 2.0…), completează modelul din motor.
        if ($model === '' && $motor !== '' && preg_match('/^([A-Za-z0-9][A-Za-z0-9\-]*(?:\s+(?:I{1,3}|IV|V|VI|VII|VIII|IX|X|\d+))?)/u', $motor, $mm)) {
            $maybe = trim((string) $mm[1]);
            if ($maybe !== '' && !preg_match('/^\d/', $maybe)) {
                $model = $maybe;
            }
        }
        // Evită dublura model în motor: „C4 II” + „C4 II 2.0 HDi” → motor fără prefix model.
        if ($model !== '' && $motor !== '') {
            $prefix = preg_quote($model, '/');
            $motor = trim((string) preg_replace('/^' . $prefix . '\s+/iu', '', $motor));
        }

        if ($motor !== '') {
            $motorLines = array_values(array_filter(array_map(
                static function (string $line): string {
                    $line = trim($line);
                    $line = ltrim($line, ": \t");

                    return $line;
                },
                preg_split('/\r?\n|;/u', $motor) ?: []
            )));
            // Preferă o linie cu tip motor, nu resturi „: I (384)”.
            $motor = '';
            foreach ($motorLines as $line) {
                if ($line === '' || preg_match('/^(I{1,3}|IV|V)\b/u', $line)) {
                    continue;
                }
                $motor = $line;
                break;
            }
            if ($motor === '' && $motorLines !== []) {
                $motor = (string) $motorLines[0];
            }
            if (function_exists('mb_strlen') && mb_strlen($motor, 'UTF-8') > 48) {
                $motor = mb_substr($motor, 0, 45, 'UTF-8') . '…';
            }
        }

        return [
            'marca' => $marca,
            'model' => $model,
            'motorizare' => $motor,
        ];
    }

    /** Imagine acceptată în coadă doar dacă e utilă (fișier local / URL trusted). */
    private static function stagingImageIsTrusted(string $url, string $source = ''): bool
    {
        $url = trim($url);
        if ($url === '') {
            return false;
        }
        $validate = dirname(__DIR__, 4) . '/Legacy/import-image-validate.php';
        if (is_file($validate)) {
            require_once $validate;
        }
        if (function_exists('besoiu_import_image_url_is_trusted')) {
            return besoiu_import_image_url_is_trusted($url, $source);
        }
        if (function_exists('besoiu_import_proxy_url_has_local_file')
            && function_exists('besoiu_import_image_is_admin_proxy_url')
            && besoiu_import_image_is_admin_proxy_url($url)
        ) {
            return besoiu_import_proxy_url_has_local_file($url);
        }

        return !str_contains(strtolower($url), 'preview-12') && !str_contains(strtolower($url), 'fakers/');
    }
    /** @param array<string, mixed> $card @return list<string> */
    /**
     * OE originale (doar mărci auto) din ART_CROSS — toate, fără AM.
     *
     * @param list<array<string, mixed>> $sourceRows
     * @return list<string>
     */
    private static function oemCodesFromArtCross(array $sourceRows): array
    {
        if (!function_exists('import_base_oem_li_from_art_cross')) {
            $lib = dirname(__DIR__, 4) . '/Backend/src/Controllers/Produse/import_base_lib.php';
            if (is_file($lib)) {
                require_once $lib;
            }
        }
        if (!function_exists('import_base_oem_li_from_art_cross')) {
            return [];
        }

        $blob = '';
        foreach ($sourceRows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $cross = trim((string) ($row['ART_CROSS'] ?? ''));
            if ($cross !== '') {
                $blob .= ($blob !== '' ? ' | ' : '') . $cross;
            }
        }
        if ($blob === '') {
            return [];
        }

        $out = [];
        $seen = [];
        foreach (import_base_oem_li_from_art_cross($blob) as $li) {
            $text = trim(html_entity_decode(strip_tags((string) $li), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if ($text === '') {
                continue;
            }
            $fold = function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
            if (isset($seen[$fold])) {
                continue;
            }
            $seen[$fold] = true;
            $out[] = $text;
        }

        return $out;
    }
    /**
     * Cod produs = TecDoc normalizat (ART_CODE_1 / tecdocSku), NU SKU furnizor (ex. 0.1954.1).
     *
     * @param array<string, mixed> $card
     * @param list<array<string, mixed>> $sourceRows
     */
    private static function resolveTecdocProductCode(array $card, array $sourceRows = []): string
    {
        $candidates = [
            trim((string) ($card['tecdocSku'] ?? '')),
            trim((string) ($card['matchedTecdocSku'] ?? '')),
            trim((string) ($card['artNr'] ?? '')),
        ];
        foreach ($sourceRows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $candidates[] = trim((string) ($row['ART_CODE_1'] ?? ''));
            $candidates[] = trim((string) ($row['ART_CODE_2'] ?? ''));
        }
        foreach ($candidates as $code) {
            if ($code !== '') {
                return $code;
            }
        }

        // Ultimul fallback: SKU furnizor doar dacă TecDoc lipsește cu totul.
        return trim((string) ($card['supplierSku'] ?? $card['sku'] ?? ''));
    }
    private static function resolveImages(array $card): array
    {
        $isBad = static function (string $url): bool {
            $url = trim($url);
            if ($url === '') {
                return true;
            }
            if (function_exists('besoiu_import_image_is_placeholder') && besoiu_import_image_is_placeholder($url)) {
                return true;
            }
            $lower = strtolower($url);

            return str_contains($lower, 'product-1.jpg')
                || str_contains($lower, 'placeholder')
                || str_contains($lower, 'no-image')
                || str_contains($lower, 'noimage');
        };

        // Preferă URL-uri care conțin SKU/cod produs (reflectă titlul/codul).
        $identity = [];
        foreach (['tecdocSku', 'matchedTecdocSku', 'artNr', 'supplierSku', 'sku', 'productOem'] as $key) {
            $v = strtoupper(preg_replace('/[^A-Z0-9]+/', '', (string) ($card[$key] ?? '')) ?? '');
            if ($v !== '' && strlen($v) >= 4) {
                $identity[$v] = true;
            }
        }
        $identityKeys = array_keys($identity);

        $rank = static function (string $url) use ($identityKeys): int {
            $score = 0;
            $hay = strtoupper(preg_replace('/[^A-Z0-9]+/', '', $url) ?? '');
            foreach ($identityKeys as $code) {
                if ($code !== '' && str_contains($hay, $code)) {
                    $score += 100;
                    break;
                }
            }
            if (preg_match('/[?&]code=([^&]+)/i', $url, $m)) {
                $pc = strtoupper(preg_replace('/[^A-Z0-9]+/', '', rawurldecode($m[1])) ?? '');
                $matchedProxy = false;
                foreach ($identityKeys as $code) {
                    if ($pc !== '' && ($pc === $code || str_contains($pc, $code) || str_contains($code, $pc))) {
                        $score += 120;
                        $matchedProxy = true;
                        break;
                    }
                }
                // cod= din proxy diferit de SKU card → penalizare puternică
                if ($pc !== '' && $identityKeys !== [] && !$matchedProxy) {
                    $score -= 200;
                }
            }
            $lower = strtolower($url);
            if (str_contains($lower, 'tecdoc') || str_contains($lower, 'poze') || str_contains($lower, 'tecalliance')) {
                $score += 20;
            }

            return $score;
        };

        $picked = [];
        foreach (['imageDisplayUrl', 'scrapedImageUrl', 'imageUrl'] as $key) {
            $url = trim((string) ($card[$key] ?? ''));
            if ($url === '' || $isBad($url)) {
                continue;
            }
            $picked[] = $url;
        }

        $relative = trim((string) ($card['scrapedImagePath'] ?? ''));
        if ($relative !== '' && !str_contains($relative, '..')) {
            if (function_exists('import_motor_proxy_url')) {
                $proxy = import_motor_proxy_url('scraped-image.php') . '?path=' . rawurlencode($relative);
                if (!$isBad($proxy)) {
                    $picked[] = $proxy;
                }
            } elseif (!$isBad($relative)) {
                $picked[] = $relative;
            }
        }

        if (!empty($card['hasImage']) && !empty($card['imagePath'])) {
            $path = trim((string) $card['imagePath']);
            if (!$isBad($path)) {
                $picked[] = $path;
            }
        }

        $picked = array_values(array_unique($picked));
        if ($picked === []) {
            return [];
        }

        usort($picked, static function (string $a, string $b) use ($rank): int {
            return $rank($b) <=> $rank($a);
        });

        // Un singur URL — cel mai relevant față de cod/titlu; evită mapare greșită.
        return [$picked[0]];
    }

    /** @param array<string, mixed> $card @param list<string> $images */
    private static function resolveImageSource(array $card, array $images): string
    {
        if ($images === []) {
            return 'missing';
        }

        $source = strtolower(trim((string) ($card['imageSource'] ?? '')));
        return match ($source) {
            'poze' => 'local_ttc_poze',
            'autopartner' => 'autopartner_local',
            'tecdoc', 'tecdoc_api' => 'tecdoc_api',
            '' => 'import_pro',
            default => $source,
        };
    }

    /** @param array<string, mixed> $card */
    private static function resolvePurchaseNet(array $card): ?float
    {
        foreach (['pricePurchaseNet', 'purchaseNet', 'priceNet', 'price_csv'] as $key) {
            if (isset($card[$key]) && is_numeric($card[$key])) {
                $value = (float) $card[$key];
                if ($value > 0) {
                    return $value;
                }
            }
        }
        if (isset($card['price']) && is_numeric($card['price'])) {
            $value = (float) $card['price'];
            if ($value > 0) {
                return $value;
            }
        }

        return null;
    }

    /** @return array{category:string,subcategory:string} */
    private static function resolveShowcaseTaxonomy(string $showcaseType): array
    {
        $showcaseType = strtolower(trim($showcaseType));
        if ($showcaseType === '') {
            return ['category' => '', 'subcategory' => ''];
        }

        require_once __DIR__ . '/ImportShowcaseConfig.php';
        $config = ImportShowcaseConfig::load();
        foreach ($config['type_defs'] ?? [] as $def) {
            if (!is_array($def)) {
                continue;
            }
            if (strtolower(trim((string) ($def['key'] ?? ''))) !== $showcaseType) {
                continue;
            }
            $sub = trim((string) ($def['epiesa_subcategory'] ?? ''));
            if ($sub === '') {
                $sub = trim((string) ($def['label'] ?? ''));
            }

            return [
                'category' => 'Produse auto universale',
                'subcategory' => $sub !== '' ? $sub : ucfirst($showcaseType),
            ];
        }

        return [
            'category' => 'Produse auto universale',
            'subcategory' => ucfirst($showcaseType),
        ];
    }

    /** @param array<string, mixed> $card */
    private static function resolvePurchaseVat(array $card, ?float $net): ?float
    {
        if (isset($card['pricePurchaseVat']) && is_numeric($card['pricePurchaseVat'])) {
            return (float) $card['pricePurchaseVat'];
        }
        if (isset($card['priceDisplay']) && is_numeric($card['priceDisplay'])) {
            return (float) $card['priceDisplay'];
        }
        if ($net !== null && isset($card['vatPercent']) && is_numeric($card['vatPercent'])) {
            return round($net * (1 + ((float) $card['vatPercent'] / 100)), 2);
        }

        return $net;
    }

    /**
     * @param array<string, mixed> $card
     * @param list<string> $types
     * @return array{type:string,score:int}|null
     */
    public static function matchShowcaseType(array $card, array $types, int $minScore = 72): ?array
    {
        require_once __DIR__ . '/ImportShowcaseTypeMatcher.php';

        return ImportShowcaseTypeMatcher::matchKeyword($card, $types, $minScore, true);
    }
}
