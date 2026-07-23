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
        $description = trim((string) ($card['description'] ?? ''));
        if (mb_strlen($description) > 12000) {
            $description = mb_substr($description, 0, 12000) . '…';
        }
        $vehicle = function_exists('import_extract_vehicle_from_title')
            ? import_extract_vehicle_from_title($name)
            : self::extractVehicleFromTitle($name);
        $compatText = trim((string) ($card['compatText'] ?? ''));
        if (mb_strlen($compatText) > 8000) {
            $compatText = mb_substr($compatText, 0, 8000) . '…';
        }
        if ($compatText === '' && $description !== '') {
            $compatText = function_exists('import_compat_text_from_description_html')
                ? import_compat_text_from_description_html($description)
                : self::extractCompatTextFromDescription($description);
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
                'description' => $description,
                'compat_text' => $compatText,
                'compat_count' => (int) ($card['compatCount'] ?? 0),
                'vehicle' => $vehicle,
            ],
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
            'raw_json' => json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];
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

        if (!preg_match('/Compatibil\s+cu:\s*<\/[^>]+>\s*<ul>(.*?)<\/ul>/is', $html, $matches)) {
            return '';
        }

        $lines = [];
        if (preg_match_all('/<li[^>]*>(.*?)<\/li>/is', (string) $matches[1], $items)) {
            foreach ($items[1] as $item) {
                $line = trim(html_entity_decode(strip_tags((string) $item), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
                if ($line !== '') {
                    $lines[] = $line;
                }
            }
        }

        return implode("\n", array_slice($lines, 0, 20));
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
            $tokens = preg_split('/\s+/u', $line) ?: [];
            if ($tokens === []) {
                continue;
            }
            $brandToken = (string) $tokens[0];
            $brandKey = function_exists('mb_strtoupper')
                ? mb_strtoupper($brandToken, 'UTF-8')
                : strtoupper($brandToken);
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
            if (isset($tokens[1])) {
                $model = trim((string) $tokens[1]);
                // Sară tipuri motor lipite imediat după model (ex. 1.8).
                if ($model !== '' && !preg_match('/^\d/', $model)) {
                    $modele[$model] = true;
                }
            }
        }

        return [
            'marca' => implode(', ', array_keys($marci)),
            'model' => implode(', ', array_slice(array_keys($modele), 0, 8)),
            'motorizare' => '',
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
