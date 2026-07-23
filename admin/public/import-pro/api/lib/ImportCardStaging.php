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
        $code = trim((string) ($card['supplierSku'] ?? $card['sku'] ?? $card['matchedTecdocSku'] ?? ''));
        if ($name === '' && $code === '') {
            return 'Lipsește titlu și cod SKU';
        }
        if ($name === '') {
            return 'Lipsește titlul produsului';
        }

        return 'Lipsește codul SKU (supplierSku/sku)';
    }

    /**
     * @param array<string, mixed> $card
     * @param array<string, mixed> $options use_ollama (default false)
     * @return array<string, mixed>|null
     */
    public static function cardToProduct(array $card, string $importLane = 'standard', array $options = []): ?array
    {
        $name = trim((string) ($card['title'] ?? $card['matchedName'] ?? $card['name'] ?? ''));
        $code = trim((string) ($card['supplierSku'] ?? $card['sku'] ?? $card['artNr'] ?? $card['matchedTecdocSku'] ?? ''));
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
        if (mb_strlen($compatText) > 8000) {
            $compatText = mb_substr($compatText, 0, 8000) . '…';
        }
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

        // Layout coadă: un singur vehicul principal (nu dump multi-marcă / motorizări).
        $vehicle = self::normalizePrimaryVehicle($vehicle, $motorizare);
        $motorizare = $vehicle['motorizare'];

        // Denumire de bază = ART_NAME TecDoc; titlurile pe canal se formează după template.
        $displayName = $rawArtName !== '' ? $rawArtName : $name;

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
            'match_status' => (string) ($card['matchStatus'] ?? ''),
            'match_method' => (string) ($card['matchMethod'] ?? ''),
            'category_match' => $categoryMatch,
            'source_file' => (string) ($card['sourceFile'] ?? ''),
            'source_supplier_sku' => trim((string) ($card['supplierSku'] ?? $code)),
            'tecdoc_sku' => trim((string) ($card['tecdocSku'] ?? $card['matchedTecdocSku'] ?? '')),
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

        return [
            'pName' => $displayName,
            'pCode' => $code,
            'pBrand' => $brand,
            'pMarca' => $vehicle['marca'],
            'pModel' => $vehicle['model'],
            'pMotorizare' => $motorizare,
            'pOem' => $oemCode !== '' ? $oemCode : $code,
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
                return array_slice($rows, 0, 250);
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

        $sourceRows = array_slice($entries, 0, 250);
        $html = trim(ImportTecdocMysqlCardBuilder::descriptionFromEntries($entries));

        return $html !== '' ? $html : $description;
    }

    /**
     * Descriere Website: antet + specificații (nu formatul nested Base pentru marketplace).
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

        if ($sourceRows !== [] && function_exists('import_base_generate_description_website')) {
            $html = trim((string) import_base_generate_description_website($sourceRows));
            if ($html !== '') {
                return $html;
            }
        }

        if ($marketplaceHtml !== '' && function_exists('import_base_description_website_from_marketplace')) {
            $fromMp = trim((string) import_base_description_website_from_marketplace($marketplaceHtml));
            if ($fromMp !== '') {
                return $fromMp;
            }
        }

        $existing = trim((string) ($card['descriptionWebsite'] ?? $card['description_website'] ?? ''));
        if ($existing !== '' && !self::isBaseDescriptionFormat($existing)) {
            return $existing;
        }

        return $marketplaceHtml !== '' && function_exists('import_base_description_website_from_marketplace')
            ? trim((string) import_base_description_website_from_marketplace($marketplaceHtml))
            : '';
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

        foreach (preg_split('/\r?\n|;/u', $compatText) ?: [] as $line) {
            $line = trim(html_entity_decode($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
            $line = trim((string) preg_replace('/\s*[·•].*$/u', '', $line));
            $line = trim((string) preg_replace('/\s*\([^)]*\)\s*$/u', '', $line));
            if ($line === '') {
                continue;
            }

            // Format tip „RENAULT: MEGANE III cupe …” sau „RENAULT MEGANE III …”.
            $brandToken = '';
            $rest = $line;
            if (preg_match('/^([A-Za-zÀ-ÿ0-9\-]+)\s*:\s*(.+)$/u', $line, $m)) {
                $brandToken = (string) $m[1];
                $rest = trim((string) $m[2]);
            } else {
                $tokens = preg_split('/\s+/u', $line) ?: [];
                $brandToken = (string) ($tokens[0] ?? '');
                $rest = trim(implode(' ', array_slice($tokens, 1)));
            }

            $brandKey = function_exists('mb_strtoupper')
                ? mb_strtoupper($brandToken, 'UTF-8')
                : strtoupper($brandToken);
            $brandKey = rtrim($brandKey, ':');
            if ($allowed !== [] && !isset($allowed[$brandKey])) {
                if ($brandKey === 'VOLKSWAGEN' && isset($allowed['VW'])) {
                    $brandKey = 'VW';
                } elseif ($brandKey === 'MERCEDES' && isset($allowed['MERCEDES-BENZ'])) {
                    $brandKey = 'MERCEDES-BENZ';
                } else {
                    continue;
                }
            }
            $marci[$brandKey] = true;

            // Model = primul token (+ generație II/III dacă urmează).
            if ($rest !== '') {
                $restTokens = preg_split('/\s+/u', $rest) ?: [];
                $model = trim((string) ($restTokens[0] ?? ''));
                if ($model !== '' && !preg_match('/^\d/', $model)) {
                    if (isset($restTokens[1]) && preg_match('/^(I{1,3}|IV|V|VI|VII|VIII|IX|X|\d+)$/u', (string) $restTokens[1])) {
                        $model .= ' ' . trim((string) $restTokens[1]);
                    }
                    $modele[$model] = true;
                }
            }
        }

        $brandList = array_keys($marci);
        $modelList = array_keys($modele);

        // Un singur vehicul principal pentru coloanele pMarca/pModel (nu listă multi-marcă).
        return [
            'marca' => (string) ($brandList[0] ?? ''),
            'model' => (string) ($modelList[0] ?? ''),
            'motorizare' => '',
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

    /** @param array<string, mixed> $card @return list<string> */
    private static function resolveImages(array $card): array
    {
        foreach (['imageDisplayUrl', 'scrapedImageUrl', 'imageUrl'] as $key) {
            $url = trim((string) ($card[$key] ?? ''));
            if ($url !== '') {
                return [$url];
            }
        }

        $relative = trim((string) ($card['scrapedImagePath'] ?? ''));
        if ($relative !== '' && !str_contains($relative, '..')) {
            if (function_exists('import_motor_proxy_url')) {
                return [import_motor_proxy_url('scraped-image.php') . '?path=' . rawurlencode($relative)];
            }

            return [$relative];
        }

        if (!empty($card['hasImage']) && !empty($card['imagePath'])) {
            return [(string) $card['imagePath']];
        }

        return [];
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
