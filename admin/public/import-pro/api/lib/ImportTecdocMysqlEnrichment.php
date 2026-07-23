<?php
declare(strict_types=1);

require_once __DIR__ . '/ImportTecdocMysqlCardBuilder.php';

/**
 * Enrichment unic din MySQL TecDoc (besoiu_tecdoc_base / tabele unificate).
 * Fără match în DB → null / produs nemodificat. Imagine opțională (Poze/Autopartner).
 */
final class ImportTecdocMysqlEnrichment
{
    /**
     * @return list<array<string, string>>|null
     */
    public static function lookupEntries(string $brand, string $code, int $productId = 0): ?array
    {
        import_require_prelucrare_lib('BaseIndexLookup.php');
        import_require_prelucrare_lib('CoreDbLookup.php');

        if ($productId > 0 && CoreDbLookup::isAvailable()) {
            $entries = CoreDbLookup::findEntriesByProductId($productId);
            if ($entries !== null && $entries !== []) {
                return self::enrichEntriesFromOeCompat($entries);
            }
        }

        $variants = self::codeVariants($code);
        if ($brand === '' || $variants === []) {
            return null;
        }

        $entries = BaseIndexLookup::findEntries($brand, $variants);
        if ($entries === null || $entries === []) {
            return null;
        }

        return self::enrichEntriesFromOeCompat($entries);
    }

    /**
     * Dacă produsul e în TecDoc dar fără aplicații auto, completează compat
     * din brandurile echivalente (ART_CROSS / OE) — cartă full ca în Base.html.
     *
     * @param list<array<string, string>> $entries
     * @return list<array<string, string>>
     */
    public static function enrichEntriesFromOeCompat(array $entries): array
    {
        if ($entries === [] || self::entriesHaveVehicleCompat($entries)) {
            return $entries;
        }

        $first = $entries[0];
        $cross = trim((string) ($first['ART_CROSS'] ?? ''));
        if ($cross === '') {
            return $entries;
        }

        $pairs = self::parseArtCrossPairs($cross);
        if ($pairs === []) {
            return $entries;
        }

        // Prioritate: mărci auto OEM, apoi branduri piese din whitelist.
        usort($pairs, static function (array $a, array $b): int {
            return ($b['is_car'] <=> $a['is_car']) ?: strcmp($a['brand'], $b['brand']);
        });

        $primaryArt = [
            'ART_BRAND' => (string) ($first['ART_BRAND'] ?? ''),
            'ART_CODE_1' => (string) ($first['ART_CODE_1'] ?? ''),
            'ART_CODE_2' => (string) ($first['ART_CODE_2'] ?? ''),
            'ART_NAME' => (string) ($first['ART_NAME'] ?? ''),
            'ART_EAN' => (string) ($first['ART_EAN'] ?? ''),
            'TTC_ART_ID' => (string) ($first['TTC_ART_ID'] ?? ''),
            'PARTS_INFO' => (string) ($first['PARTS_INFO'] ?? ''),
            'TERMS_OF_USE' => (string) ($first['TERMS_OF_USE'] ?? ''),
            'ART_CROSS' => $cross,
        ];

        $merged = [];
        $seen = [];
        $donorsUsed = 0;
        $maxDonors = 10;
        $maxRows = 250;

        foreach ($pairs as $pair) {
            if ($donorsUsed >= $maxDonors || count($merged) >= $maxRows) {
                break;
            }
            $donorBrand = $pair['brand'];
            $donorCode = $pair['code'];
            if ($donorBrand === '' || $donorCode === '') {
                continue;
            }
            // Nu căuta același produs.
            if (strcasecmp($donorBrand, $primaryArt['ART_BRAND']) === 0
                && self::normCodeKey($donorCode) === self::normCodeKey($primaryArt['ART_CODE_1'])
            ) {
                continue;
            }

            try {
                $donor = BaseIndexLookup::findEntries($donorBrand, self::codeVariants($donorCode));
            } catch (\Throwable) {
                $donor = null;
            }
            if (!is_array($donor) || $donor === [] || !self::entriesHaveVehicleCompat($donor)) {
                continue;
            }

            ++$donorsUsed;
            // Specs mai bogate de la donor (doar chei lipsă).
            $donorParts = trim((string) ($donor[0]['PARTS_INFO'] ?? ''));
            if ($donorParts !== '' && strlen($donorParts) > strlen($primaryArt['PARTS_INFO'])) {
                $primaryArt['PARTS_INFO'] = self::mergePartsInfo($primaryArt['PARTS_INFO'], $donorParts);
            }

            foreach ($donor as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $carBrand = trim((string) ($row['CAR_BRAND'] ?? ''));
                $carModel = trim((string) ($row['CAR_MODEL'] ?? ''));
                if ($carBrand === '' || $carModel === '') {
                    continue;
                }
                $dedupe = strtoupper($carBrand . '|' . $carModel . '|' . trim((string) ($row['CAR_TYP'] ?? ''))
                    . '|' . trim((string) ($row['CAR_OF_YEAR'] ?? '')) . '|' . trim((string) ($row['CAR_TO_YEAR'] ?? ''))
                    . '|' . trim((string) ($row['CAR_KW'] ?? '')));
                if (isset($seen[$dedupe])) {
                    continue;
                }
                $seen[$dedupe] = true;
                $merged[] = array_merge($row, $primaryArt, [
                    '_compat_from_oe' => $donorBrand . ':' . $donorCode,
                ]);
                if (count($merged) >= $maxRows) {
                    break;
                }
            }
        }

        if ($merged === []) {
            return $entries;
        }

        return $merged;
    }

    /** @param list<array<string, mixed>> $entries */
    public static function entriesHaveVehicleCompat(array $entries): bool
    {
        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            if (trim((string) ($entry['CAR_BRAND'] ?? '')) !== ''
                && trim((string) ($entry['CAR_MODEL'] ?? '')) !== ''
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array{brand:string,code:string,is_car:int}>
     */
    private static function parseArtCrossPairs(string $cross): array
    {
        self::ensureImportBaseLib();
        $allowedCars = function_exists('import_base_allowed_car_brands')
            ? import_base_allowed_car_brands()
            : [];
        $allowedParts = function_exists('import_base_allowed_part_brands')
            ? import_base_allowed_part_brands()
            : [];

        $out = [];
        $seen = [];
        foreach (preg_split('/\s*\|\s*/u', $cross) ?: [] as $part) {
            $part = trim((string) $part);
            if ($part === '') {
                continue;
            }
            $sep = str_contains($part, '::') ? '::' : (str_contains($part, ':') ? ':' : null);
            if ($sep === null) {
                continue;
            }
            [$brand, $code] = array_pad(explode($sep, $part, 2), 2, '');
            $brand = strtoupper(trim($brand));
            $code = trim($code);
            if ($brand === '' || $code === '') {
                continue;
            }
            $isCar = isset($allowedCars[$brand]) ? 1 : 0;
            $isPart = isset($allowedParts[$brand]);
            if (!$isCar && !$isPart && $allowedCars !== []) {
                continue;
            }
            $key = $brand . '|' . self::normCodeKey($code);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = ['brand' => $brand, 'code' => $code, 'is_car' => $isCar];
        }

        return $out;
    }

    private static function normCodeKey(string $code): string
    {
        return strtoupper(preg_replace('/[^A-Z0-9]/', '', $code) ?? '');
    }

    private static function mergePartsInfo(string $primary, string $donor): string
    {
        $seen = [];
        $lines = [];
        foreach ([$primary, $donor] as $block) {
            foreach (preg_split('/\s*\|\s*/u', $block) ?: [] as $line) {
                $line = trim(str_replace('::', ': ', $line));
                if ($line === '') {
                    continue;
                }
                $key = function_exists('mb_strtolower')
                    ? mb_strtolower(preg_replace('/\s+/u', ' ', $line) ?? $line, 'UTF-8')
                    : strtolower(preg_replace('/\s+/u', ' ', $line) ?? $line);
                if (str_contains($line, ':')) {
                    $key = function_exists('mb_strtolower')
                        ? mb_strtolower(trim(explode(':', $line, 2)[0]), 'UTF-8')
                        : strtolower(trim(explode(':', $line, 2)[0]));
                }
                if ($key === '' || isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $lines[] = $line;
            }
        }

        return implode(' | ', $lines);
    }

    private static function ensureImportBaseLib(): void
    {
        if (function_exists('import_base_allowed_car_brands')) {
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
     * Card complet din rând furnizor (CSV) — null dacă nu există în TecDoc MySQL.
     *
     * @param array{code?:string,brand?:string,name?:string,priceNet?:float|null} $parsed
     * @return array<string, mixed>|null
     */
    public static function cardFromSupplierRow(array $parsed, string $supplierType, string $sourceFile = ''): ?array
    {
        $code = trim((string) ($parsed['code'] ?? ''));
        $brand = trim((string) ($parsed['brand'] ?? ''));
        $priceNet = isset($parsed['priceNet']) && is_numeric($parsed['priceNet']) ? (float) $parsed['priceNet'] : 0.0;

        if ($code === '' || $priceNet <= 0) {
            return null;
        }

        $name = trim((string) ($parsed['name'] ?? ''));
        $nameLower = mb_strtolower($name, 'UTF-8');
        if ($nameLower !== '' && (
            str_contains($nameLower, 'valoare piesa veche')
            || str_contains($nameLower, 'valoare de garantie')
            || str_contains($nameLower, 'core_value')
        )) {
            return null;
        }

        $entries = self::lookupEntries($brand, $code);
        if ($entries === null) {
            return null;
        }

        $entryBrand = trim((string) ($entries[0]['ART_BRAND'] ?? $brand));
        if ($entryBrand !== '') {
            $brand = $entryBrand;
        }
        $code1 = trim((string) ($entries[0]['ART_CODE_1'] ?? $code));
        if ($code1 === '') {
            $code1 = $code;
        }

        $card = ImportTecdocMysqlCardBuilder::fromEntries(
            $entries,
            $brand,
            $code1,
            $priceNet,
            $supplierType,
            [
                'status' => 'exact',
                'match_method' => 'tecdoc_mysql_brand_code',
                'sku_supplier' => $code,
                'source_file' => $sourceFile,
                'name' => $name,
            ]
        );

        if ($card === null || !self::cardHasTecdocBody($card)) {
            return null;
        }

        if ($sourceFile !== '') {
            $card['sourceFile'] = $sourceFile;
            $card['sourceSupplier'] = strtolower(explode('/', $sourceFile)[0] ?? '');
        }
        $card['cardBuild'] = 'mysql_besoiu_tecdoc_base';
        $card['tecdocDataComplete'] = true;

        return $card;
    }

    /**
     * Completează un card existent din MySQL TecDoc dacă lipsește corpul TecDoc.
     *
     * @param array<string, mixed> $card
     * @return array<string, mixed>|null null dacă nu există în MySQL
     */
    public static function enrichCardFromMysql(array $card): ?array
    {
        if (self::cardHasTecdocBody($card)) {
            return $card;
        }

        $brand = trim((string) ($card['brand'] ?? ''));
        $code = trim((string) ($card['sku'] ?? ''));
        $entries = self::lookupEntries($brand, $code);
        if ($entries === null) {
            return null;
        }

        $entryBrand = trim((string) ($entries[0]['ART_BRAND'] ?? $brand));
        $code1 = trim((string) ($entries[0]['ART_CODE_1'] ?? $code));
        $priceNet = isset($card['priceNet']) && is_numeric($card['priceNet'])
            ? (float) $card['priceNet']
            : (isset($card['pricePurchaseNet']) && is_numeric($card['pricePurchaseNet'])
                ? (float) $card['pricePurchaseNet']
                : 0.0);
        $supplier = trim((string) ($card['supplier'] ?? $card['sourceSupplier'] ?? ''));

        $built = ImportTecdocMysqlCardBuilder::fromEntries(
            $entries,
            $entryBrand !== '' ? $entryBrand : $brand,
            $code1 !== '' ? $code1 : $code,
            $priceNet,
            $supplier !== '' ? strtoupper($supplier) : 'GENERIC',
            [
                'status' => 'exact',
                'match_method' => 'tecdoc_mysql_brand_code',
                'sku_supplier' => $code,
                'source_file' => (string) ($card['sourceFile'] ?? ''),
            ]
        );

        if ($built === null || !self::cardHasTecdocBody($built)) {
            return null;
        }

        return array_merge($card, $built, [
            'cardBuild' => 'mysql_besoiu_tecdoc_base',
            'tecdocDataComplete' => true,
            'showcaseType' => $card['showcaseType'] ?? null,
            'showcaseScore' => $card['showcaseScore'] ?? null,
            'showcaseMatchMethod' => $card['showcaseMatchMethod'] ?? null,
            'sourceFile' => $card['sourceFile'] ?? ($built['sourceFile'] ?? ''),
            'sourceSupplier' => $card['sourceSupplier'] ?? ($built['sourceSupplier'] ?? ''),
        ]);
    }

    /**
     * Enrichment staging (import_produse) — MySQL TecDoc înainte de fișiere CSV upload.
     *
     * @param array<string, mixed> $product
     * @return array<string, mixed>
     */
    public static function enrichStagingProduct(array $product, bool $forceRefresh = false): array
    {
        $brand = trim((string) ($product['pBrand'] ?? ''));
        $code = trim((string) ($product['pCode'] ?? ''));
        if ($code === '') {
            return $product;
        }

        $rawPayload = json_decode((string) ($product['raw_json'] ?? '{}'), true);
        if (!is_array($rawPayload)) {
            $rawPayload = [];
        }
        if (!$forceRefresh
            && !empty($rawPayload['tecdoc_import_enrichment']['found'])
            && ($rawPayload['tecdoc_import_enrichment']['source'] ?? '') === 'mysql_tecdoc') {
            return $product;
        }

        $productId = (int) ($rawPayload['matched_product_id'] ?? 0);
        $entries = self::lookupEntries($brand, $code, $productId);
        if ($entries === null) {
            return $product;
        }

        $entryBrand = trim((string) ($entries[0]['ART_BRAND'] ?? $brand));
        $code1 = trim((string) ($entries[0]['ART_CODE_1'] ?? $code));
        $basePrice = trim((string) ($product['pBasePrice'] ?? ''));
        $priceNet = is_numeric($basePrice) && (float) $basePrice > 0
            ? (float) $basePrice
            : (is_numeric($product['pPrice'] ?? null) ? ((float) $product['pPrice']) / 1.21 : 0.0);
        $supplier = trim((string) ($product['pSupplier'] ?? ''));

        $card = ImportTecdocMysqlCardBuilder::fromEntries(
            $entries,
            $entryBrand !== '' ? $entryBrand : $brand,
            $code1 !== '' ? $code1 : $code,
            max(0.0, $priceNet),
            $supplier !== '' ? strtoupper($supplier) : 'GENERIC',
            [
                'status' => 'exact',
                'match_method' => 'tecdoc_mysql_staging',
                'sku_supplier' => $code,
            ]
        );

        if ($card === null || !self::cardHasTecdocBody($card)) {
            return $product;
        }

        require_once __DIR__ . '/ImportCardStaging.php';
        $lane = (string) ($rawPayload['import_lane'] ?? 'standard');
        $fromCard = ImportCardStaging::cardToProduct(array_merge($card, [
            'sourceSupplier' => $supplier,
            'matchStatus' => 'exact',
            'matchMethod' => 'tecdoc_mysql_staging',
        ]), $lane);

        if ($fromCard === null) {
            return $product;
        }

        foreach ([
            'pName', 'pBrand', 'pMarca', 'pModel', 'pMotorizare', 'pOem',
            'pCategory', 'pSubcategory', 'pCompatibilitati', 'pSpecs',
        ] as $field) {
            if (!array_key_exists($field, $fromCard)) {
                continue;
            }
            $newVal = trim((string) $fromCard[$field]);
            if ($newVal === '') {
                continue;
            }
            if (!$forceRefresh) {
                if (function_exists('import_field_is_empty') && !import_field_is_empty((string) ($product[$field] ?? ''))) {
                    continue;
                }
                if (!function_exists('import_field_is_empty') && trim((string) ($product[$field] ?? '')) !== '') {
                    continue;
                }
            }
            $product[$field] = $fromCard[$field];
        }

        $images = json_decode((string) ($fromCard['pImages'] ?? '[]'), true);
        $hasCardImages = is_array($images) && trim((string) ($images[0] ?? '')) !== '';
        $shouldApplyImages = $forceRefresh
            || (function_exists('import_field_is_empty') && import_field_is_empty((string) ($product['pImages'] ?? '[]')));
        if ($shouldApplyImages && $hasCardImages) {
            $product['pImages'] = json_encode($images, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $product['pImageSource'] = (string) ($fromCard['pImageSource'] ?? 'import_pro');
        }

        $fromRaw = json_decode((string) ($fromCard['raw_json'] ?? '{}'), true);
        if (!is_array($fromRaw)) {
            $fromRaw = [];
        }

        $product['raw_json'] = json_encode(array_merge($rawPayload, $fromRaw, [
            'tecdoc_import_enrichment' => [
                'found' => true,
                'source' => 'mysql_tecdoc',
                'ttc_art_id' => (string) ($card['ttcArtId'] ?? ''),
                'compat_count' => (int) ($card['compatCount'] ?? 0),
                'synced_at' => date('c'),
                'force_refresh' => $forceRefresh,
            ],
        ]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $product;
    }

    /** @param array<string, mixed> $card */
    public static function cardHasTecdocBody(array $card): bool
    {
        if (!empty($card['tecdocDataComplete'])) {
            return true;
        }

        return (is_array($card['parameters'] ?? null) && count($card['parameters']) > 0)
            || mb_strlen((string) ($card['description'] ?? '')) > 40
            || (int) ($card['compatCount'] ?? 0) > 0;
    }

    /** @return list<string> */
    public static function codeVariants(string $code): array
    {
        $code = trim($code);
        $norm = strtoupper(preg_replace('/[^A-Z0-9]/', '', $code) ?? '');

        return array_values(array_unique(array_filter([
            $code,
            $norm,
            ltrim($norm, '0'),
        ])));
    }

    /** @param array<string, mixed> $product */
    public static function cardLookupKey(array $product): string
    {
        $sku = strtolower(trim((string) ($product['sku_supplier'] ?? $product['sku'] ?? '')));
        $supplier = strtolower(trim((string) ($product['supplier'] ?? $product['sourceSupplier'] ?? '')));
        $file = strtolower(trim((string) ($product['source_file'] ?? $product['sourceFile'] ?? '')));
        if ($file !== '' && !str_contains($file, '/') && $supplier !== '') {
            $file = $supplier . '/' . $file;
        }

        return $sku . '|' . $supplier . '|' . $file;
    }

    /** @param array<string, mixed> $card */
    public static function cardResultKey(array $card): string
    {
        $sku = strtolower(trim((string) ($card['supplierSku'] ?? $card['sku'] ?? '')));
        $supplier = strtolower(trim((string) ($card['sourceSupplier'] ?? $card['supplier'] ?? '')));
        $file = strtolower(trim((string) ($card['sourceFile'] ?? '')));
        if ($file !== '' && !str_contains($file, '/') && $supplier !== '') {
            $file = $supplier . '/' . $file;
        }

        return $sku . '|' . $supplier . '|' . $file;
    }
}
