<?php

declare(strict_types=1);

use Config\Database;
use Evasystem\Controllers\AdaosComercial\AdaosComercialService;

/**
 * Scanare + import consumabile (ulei, lichide, electrice) din fișiere furnizor,
 * cu verificare ePiesa și publicare directă în magazin.
 */

/** @return array<string, array{label:string,needles:array<int,string>}> */
function import_consumable_category_defs(): array
{
    return [
        'ulei' => [
            'label' => 'Uleiuri și lubrifianți auto',
            'needles' => [
                'ulei motor', 'ulei cutie', 'ulei transmisie', 'ulei servo', 'ulei hidraulic',
                'ulei universal', 'ulei sintetic', 'ulei mineral', 'ulei total', 'ulei parțial',
                'lubrifiant', 'lubrif', 'vaselin', 'vaselină', 'grease', 'motor oil', 'atf ',
                'ulei 0w', 'ulei 5w', 'ulei 10w', 'ulei 15w', 'ulei 20w',
                'transmission oil', 'gear oil', 'hypoid',
            ],
        ],
        'lichide' => [
            'label' => 'Lichide auto',
            'needles' => [
                'lichid de frana', 'lichid de frână', 'lichid frana', 'lichid frână',
                'lichid parbriz', 'lichid stergator', 'lichid ștergător', 'lichid spalator parbriz',
                'lichid servo directie', 'lichid servo direcție',
                'lichid racire', 'lichid răcire', 'lichid antigel',
                'adblue', 'ad blue',
                'dot 3', 'dot 4', 'dot3', 'dot4', 'dot 5', 'dot5',
                'lichid frana dot', 'lichid frână dot', 'concentrat antigel',
            ],
        ],
        'electrice' => [
            'label' => 'Electrice auto',
            'needles' => [
                'bec ', ' bec', 'becuri', 'bec auto', 'bec halogen', 'bec led', 'bec xenon',
                'baterie auto', 'baterie ', 'acumulator auto', 'acumulator ',
                'siguranta auto', 'siguranță auto', 'set sigurante', 'set siguranțe',
                'cutie sigurante', 'cutie siguranțe', 'fusible', 'blade fuse',
                'siguranta fuzibila', 'siguranță fuzibilă', 'fuzibil auto', 'siguranta plat',
            ],
        ],
    ];
}

/**
 * Piese electrice (nu consumabile) — excluse din toate categoriile.
 *
 * @return array<int, string>
 */
function import_consumable_electrical_part_exclusions(): array
{
    return [
        'lampa', 'far ', 'faza ', 'sticla far', 'stop lampa', 'semnalizare', 'indicatoare',
        'lampa de', 'lampa interioara', 'lampa interioară', 'lampa avarie', 'lampa stop',
        'lampa mers', 'elemente lampa', 'elemente de lampa',
        'alternator', 'demaror', 'demaro', 'electromotor', 'starter ',
        'motor ventilator', 'pompa combustibil', 'bobina aprindere', 'bobina inductie',
        'releu', 'senzor', 'comutator', 'intrerupator', 'întrerupator',
        'maneta stergator', 'manetă ștergător', 'maneta stergatoare',
        'valva releu', 'valvă releu', 'modul ', 'control unit', 'ecu ',
        'cablaj', 'contact ', 'pornire ',
    ];
}

/**
 * Piese mecanice/hidraulice care nu sunt consumabile (ulei/lichide/electrice).
 *
 * @return array<int, string>
 */
function import_consumable_exclusion_needles(): array
{
    return [
        'sabot frana', 'sabot frână', 'placa frana', 'placă frână', 'placute frana', 'plăcuțe frână',
        'disc frana', 'disc frână', 'tambur frana', 'tambur frână', 'cilindru frana', 'cilindru de frana',
        'cilindru frână', 'pompa centrala frana', 'pompa centrală frână', 'linie franare', 'linie frânare',
        'kit de montaj', 'kit montaj', 'ghidaj placa', 'ghidaj placă', 'arc placa', 'arc placă',
        'regulator frana', 'regulator frână', 'servo motor frana', 'servo motor frână',
        'valva frana', 'valvă frână', 'supapa frana', 'supapă frână', 'furca servo', 'furcă servo',
        'diafragma servo', 'capac tambur', 'radiator motor', 'suport radiator',
        'filtru ulei', 'garnitura pompa ulei', 'garnitură pompă ulei', 'garnitura filtru ulei',
        'garnitura radiator ulei', 'pompa ulei', 'senzor sonda ulei', 'senzor ulei', 'etansare ulei',
        'etanșare ulei', 'conector furtun', 'conector furtune', 'conector control',
        'lamela stergator', 'lamelă ștergător', 'brat stergator', 'braț ștergător',
        'articulatie stergator', 'articulație ștergător', 'timonerie stergator', 'timonerie ștergător',
        'temporizator stergator', 'temporizator ștergător', 'diuza spalator', 'duză spălător',
        'kit reparatie servosuspensie', 'kit reparație servosuspensie',
        'kit reparatie sistem franare', 'kit reparație sistem frânare',
        'peri demaror', 'perie demaror',
        'bujie incandescent', 'bujie incandescenta', 'bujie incandescentă', 'glow plug',
        'bujie ', 'bujii ', 'bujie scanteie', 'bujie scânteie', 'spark plug', 'bougie',
        'papuc fisa', 'papuc fișă', 'fisa bujie', 'fișă bujie', 'fise bujie', 'fișe bujie',
        'conector bujie', 'manson bujie', 'cablaj bujie', 'electrod bujie',
        'termostat', 'termostate', 'senzor temperatura', 'senzor temperatură',
        'conducta', 'conductă', 'furtun', 'racord', 'teava', 'țeavă', 'tub ',
        'flansa', 'flanșă', 'vas expansiune', 'rezervor lichid',
        'supapa siguranta', 'supapă siguranță', 'supapa de siguranta',
    ];
}

/** @param array<string, mixed> $product */
function import_consumable_is_excluded_part(array $product): bool
{
    $hay = mb_strtolower(implode(' ', [
        (string) ($product['pName'] ?? ''),
        (string) ($product['pSubcategory'] ?? ''),
        import_consumable_supplier_raw_name($product),
    ]), 'UTF-8');

    foreach (import_consumable_exclusion_needles() as $needle) {
        if ($needle !== '' && str_contains($hay, mb_strtolower($needle, 'UTF-8'))) {
            return true;
        }
    }

    foreach (import_consumable_electrical_part_exclusions() as $needle) {
        if ($needle !== '' && str_contains($hay, mb_strtolower($needle, 'UTF-8'))) {
            return true;
        }
    }

    // Bujii și accesorii aprindere — piese motor, nu consumabile vitrină.
    if (preg_match('/\bbujii?\b/u', $hay)
        || preg_match('/\bspark\s+plug\b/u', $hay)
        || preg_match('/\bbougie\b/u', $hay)) {
        return true;
    }
    if (preg_match('/\b(papuc|fisa|fișă|fise|fișe)\b/u', $hay) && preg_match('/\bbujii?\b/u', $hay)) {
        return true;
    }

    // Bujie incandescentă = bougie diesel (piesă motor), nu consumabil «electrice» tip bec/baterie.
    if (preg_match('/bujie\s+incandescent/u', $hay)) {
        return true;
    }

    // Senzor / comutator legat de frână — piesă, nu lichid.
    if (str_contains($hay, 'senzor') || str_contains($hay, 'comutator')) {
        return true;
    }

    // „ulei” în denumirea unei piese (filtru/garnitură) — nu e lubrifiant.
    if (preg_match('/\bulei\b/u', $hay)
        && !preg_match('/\bulei\s+(motor|cutie|transmisie|servo|hidraulic|universal|sintetic|mineral|\d+w)/u', $hay)
        && !str_contains($hay, 'lubrif')
        && !preg_match('/\d+\s*l\b/u', $hay)) {
        return true;
    }

    return false;
}

/** Piese care conțin «antigel/apă» în nume dar nu sunt lichid de vânzare. */
function import_consumable_lichide_part_exclusions(): array
{
    return [
        'conducta', 'conductă', 'furtun', 'racord', 'teava', 'țeavă', 'tub ',
        'flansa', 'flanșă', 'brida', 'pompa apa', 'pompă apă', 'pompa de apa',
        'radiator', 'vas expansiune', 'rezervor', 'termoconducator', 'termostat',
        'senzor', 'garnitura', 'garnitură', 'etansare', 'etanșare',
    ];
}

function import_consumable_matches_lichide_consumable(string $hay): bool
{
    foreach (import_consumable_lichide_part_exclusions() as $needle) {
        if ($needle !== '' && str_contains($hay, mb_strtolower($needle, 'UTF-8'))) {
            return false;
        }
    }

    if (preg_match('/\blichid\s+(de\s+)?(fran|frân|parbriz|ster|șter|racire|răci|servo|spalator|spălător)/u', $hay)) {
        return true;
    }
    if (preg_match('/\bad\s*blue\b/u', $hay) || str_contains($hay, 'adblue')) {
        return true;
    }
    if (preg_match('/\b(concentrat\s+)?antigel\b/u', $hay)) {
        return true;
    }
    if (str_contains($hay, 'coolant') || str_contains($hay, 'antifriz')) {
        return true;
    }
    if (preg_match('/\bdot\s*[345]\b/u', $hay) && str_contains($hay, 'lichid')) {
        return true;
    }

    return false;
}

function import_consumable_matches_ulei_consumable(string $hay): bool
{
    if (preg_match('/\b(filtru|garnitura|garnitură|pompa|pompă|etansare|etanșare|sonda|senzor)\b/u', $hay)) {
        return false;
    }

    if (preg_match('/\bulei\s+(motor|cutie|transmisie|servo|hidraulic|universal|sintetic|mineral)/u', $hay)) {
        return true;
    }
    if (preg_match('/\b(olej|oil)\b/u', $hay) && preg_match('/\b\d+w\d+\b/u', $hay)) {
        return true;
    }
    if (preg_match('/\bulei\s+\d+w/u', $hay) || preg_match('/\b\d+w\d+\b/u', $hay)) {
        return true;
    }
    if (str_contains($hay, 'lubrifiant') || str_contains($hay, 'lubrif')) {
        return true;
    }
    if (preg_match('/\batf\b/u', $hay) && str_contains($hay, 'ulei')) {
        return true;
    }
    if (str_contains($hay, 'motor oil') || str_contains($hay, 'gear oil') || str_contains($hay, 'hypoid')) {
        return true;
    }
    if (preg_match('/\bulei\b/u', $hay) && preg_match('/\d+\s*l\b/u', $hay)) {
        return true;
    }

    return false;
}

/** Ulei sau lichid real (prioritate cron / vitrină). */
function import_consumable_is_fluid_consumable(array $product): bool
{
    $cats = import_consumable_detect_categories($product);

    return in_array('ulei', $cats, true) || in_array('lichide', $cats, true);
}

/**
 * Pentru categoria electrice: doar becuri, baterii, siguranțe fuzibile (fără bujii/piese montate).
 */
function import_consumable_matches_electrice_consumable(string $hay): bool
{
    if (preg_match('/\bbujii?\b/u', $hay)
        || preg_match('/\bspark\s+plug\b/u', $hay)
        || preg_match('/\b(scanteie|scânteie)\b/u', $hay)
        || preg_match('/\bpapuc\b/u', $hay)) {
        return false;
    }

    if (import_consumable_electrical_part_exclusions() !== []) {
        foreach (import_consumable_electrical_part_exclusions() as $needle) {
            if ($needle !== '' && str_contains($hay, mb_strtolower($needle, 'UTF-8'))) {
                return false;
            }
        }
    }
    if (str_contains($hay, 'senzor') || str_contains($hay, 'comutator') || str_contains($hay, 'releu')) {
        return false;
    }

    $defs = import_consumable_category_defs();
    foreach ($defs['electrice']['needles'] ?? [] as $needle) {
        if ($needle !== '' && str_contains($hay, mb_strtolower($needle, 'UTF-8'))) {
            return true;
        }
    }

    return false;
}

/** @return array<int, string> */
function import_consumable_normalize_categories(array $selected): array
{
    $defs = import_consumable_category_defs();
    $out = [];
    foreach ($selected as $key) {
        $key = strtolower(trim((string) $key));
        if ($key !== '' && isset($defs[$key])) {
            $out[] = $key;
        }
    }

    return $out !== [] ? array_values(array_unique($out)) : array_keys($defs);
}

/** @param array<string, mixed> $product @return array<int, string> */
function import_consumable_detect_categories(array $product): array
{
    if (import_consumable_is_excluded_part($product)) {
        return [];
    }

    $hay = mb_strtolower(implode(' ', [
        (string) ($product['pName'] ?? ''),
        (string) ($product['pCategory'] ?? ''),
        (string) ($product['pSubcategory'] ?? ''),
        import_consumable_supplier_raw_name($product),
    ]), 'UTF-8');

    $matched = [];
    foreach (import_consumable_category_defs() as $key => $def) {
        if ($key === 'electrice') {
            if (import_consumable_matches_electrice_consumable($hay)) {
                $matched[] = $key;
            }
            continue;
        }
        if ($key === 'lichide') {
            if (import_consumable_matches_lichide_consumable($hay)) {
                $matched[] = $key;
            }
            continue;
        }
        if ($key === 'ulei') {
            if (import_consumable_matches_ulei_consumable($hay)) {
                $matched[] = $key;
            }
            continue;
        }
        foreach ($def['needles'] as $needle) {
            if ($needle !== '' && str_contains($hay, mb_strtolower($needle, 'UTF-8'))) {
                $matched[] = $key;
                break;
            }
        }
    }

    return array_values(array_unique($matched));
}

/** @param array<string, mixed> $product */
function import_consumable_matches_selection(array $product, array $selectedCategories): bool
{
    $detected = import_consumable_detect_categories($product);

    return array_intersect($detected, $selectedCategories) !== [];
}

/** @param array<string, mixed> $product */
function import_consumable_has_supplier_price(array $product): bool
{
    return trim((string) ($product['pPrice'] ?? '')) !== '';
}

/**
 * @param array<int, array<string, mixed>> $filesMeta
 * @return array{supplier_files:array<int,array{path:string,name:string}>,missing:array<int,string>}
 */
function import_consumable_resolve_supplier_files(array $filesMeta): array
{
    if (!function_exists('import_temp_file_path')) {
        require_once __DIR__ . '/import_uploaded_files_lib.php';
    }

    $supplierFiles = [];
    $missing = [];

    foreach ($filesMeta as $fileMeta) {
        $fileId = (string) ($fileMeta['file_id'] ?? '');
        $originalName = (string) ($fileMeta['original_name'] ?? '');
        if ($fileId === '' || $originalName === '') {
            continue;
        }

        $path = import_temp_file_path($fileId);
        if (!is_file($path)) {
            $missing[] = $originalName;
            continue;
        }

        $kind = function_exists('import_resolve_upload_file_kind')
            ? import_resolve_upload_file_kind($path, $originalName, $fileMeta)
            : (string) ($fileMeta['file_kind'] ?? '');

        if ($kind === 'tecdoc') {
            continue;
        }
        if (str_starts_with($kind, 'supplier:') || ($fileMeta['upload_role'] ?? '') === 'supplier') {
            $supplierFiles[] = ['path' => $path, 'name' => $originalName];
        }
    }

    return ['supplier_files' => $supplierFiles, 'missing' => $missing];
}

/** @param array<string, mixed> $entry @return array<string, mixed>|null */
function import_consumable_entry_to_product(
    array $entry,
    AdaosComercialService $markupService,
    array $priceIndex = []
): ?array {
    $code = trim((string) ($entry['code'] ?? ''));
    $brand = trim((string) ($entry['brand'] ?? ''));
    $name = trim((string) ($entry['name'] ?? ''));
    $supplier = trim((string) ($entry['supplier'] ?? ''));
    $netPrice = (float) ($entry['net_price'] ?? 0);

    if ($code === '') {
        return null;
    }

    $matchedVia = 'catalog';
    if ($priceIndex !== [] && function_exists('import_lookup_supplier_price')) {
        $lookupRow = [
            'art code 1' => $code,
            'art brand' => $brand,
            'art_code_1' => $code,
            'art_brand' => $brand,
        ];
        $priceInfo = import_lookup_supplier_price($priceIndex, $lookupRow);
        if ($priceInfo !== null) {
            $netPrice = (float) ($priceInfo['price'] ?? $netPrice);
            $supplier = trim((string) ($priceInfo['supplier'] ?? $supplier));
            $matchedVia = (string) ($priceInfo['matched_via'] ?? 'price_index');
        }
    }

    $basePrice = function_exists('import_supplier_net_to_base')
        ? import_supplier_net_to_base($netPrice, $supplier)
        : round($netPrice, 2);

    if ($basePrice <= 0) {
        return null;
    }

    if ($name === '') {
        $name = trim($brand . ' ' . $code);
    }

    $canonicalBrand = function_exists('import_resolve_brand_canonical_name')
        ? import_resolve_brand_canonical_name($brand, $code)
        : import_normalize_supplier_brand($brand);
    if ($canonicalBrand === '' && $brand !== '') {
        $canonicalBrand = import_normalize_supplier_brand($brand);
    }

    $displayName = function_exists('import_base_normalize_product_name')
        ? import_base_normalize_product_name($name)
        : $name;
    $productTitle = function_exists('import_base_build_display_title')
        ? import_base_build_display_title($displayName, $brand, $code)
        : trim($displayName . ' ' . $canonicalBrand . ' ' . $code);

    $row = [
        'pName' => $productTitle,
        'pCode' => $code,
        'pBrand' => $canonicalBrand !== '' ? $canonicalBrand : import_normalize_supplier_brand($brand),
        'pMarca' => '',
        'pModel' => '',
        'pMotorizare' => '',
        'pCar' => $brand,
        'pBasePrice' => number_format($basePrice, 2, '.', ''),
        'pStock' => '1',
        'pCategory' => '',
        'pSubcategory' => '',
        'pCompatibilitati' => '',
        'pOem' => '',
        'pSupplier' => $supplier,
        'pState' => 'Nou',
        'pCity' => '',
        'pNote' => '',
        'pImages' => '[]',
        'pImageSource' => 'csv',
        'pSpecs' => '',
    ];

    $cats = import_consumable_detect_categories($row);
    $defs = import_consumable_category_defs();
    if ($cats !== []) {
        $row['pCategory'] = $defs[$cats[0]]['label'] ?? 'Consumabile auto';
    }

    if (!function_exists('import_supplier_apply_stock_zero_to_product')) {
        require_once __DIR__ . '/import_supplier_stock_zero_lib.php';
    }
    import_supplier_apply_stock_zero_to_product($row, $entry);

    $pricing = $markupService->applyAutomaticMarkup($row);
    $row = array_merge($row, $pricing['data']);
    $row['raw_json'] = json_encode([
        'schema' => 'product_import_v2',
        'import_mode' => 'consumable_scan_preview',
        'supplier_price' => [
            'supplier' => $supplier,
            'net_price' => $netPrice,
            'purchase_base' => $basePrice,
            'matched_via' => $matchedVia,
        ],
        'rows' => [[
            'art code 1' => $code,
            'art brand' => $canonicalBrand !== '' ? $canonicalBrand : $brand,
            'art name' => $name,
        ]],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    return $row;
}

/** @param array<string, mixed> $product */
function import_consumable_has_trusted_image(array $product): bool
{
    if (!function_exists('import_row_image_url') || !function_exists('import_image_url_is_trusted')) {
        return trim((string) ($product['pImages'] ?? '')) !== '' && (string) ($product['pImages'] ?? '') !== '[]';
    }

    $url = import_row_image_url($product);

    return $url !== '' && import_image_url_is_trusted($url, (string) ($product['pImageSource'] ?? ''));
}

/** @param array<string, mixed> $product */
function import_consumable_needs_review(array $product): bool
{
    if (!import_consumable_has_supplier_price($product)) {
        return true;
    }

    return !import_consumable_has_trusted_image($product);
}

/**
 * Enrich OEM din TecDoc API când lipsește fișierul CSV local.
 *
 * @param array<string, mixed> $product
 * @return array<string, mixed>
 */
function import_consumable_enrich_oem_via_api(array $product): array
{
    if (trim((string) ($product['pOem'] ?? '')) !== '') {
        return $product;
    }

    $code = trim((string) ($product['pCode'] ?? ''));
    $brand = trim((string) ($product['pBrand'] ?? ''));
    if ($code === '') {
        return $product;
    }

    $tecdocPath = dirname(__DIR__, 4) . '/system/tecdoc_stock.php';
    if (!is_file($tecdocPath)) {
        return $product;
    }
    require_once $tecdocPath;

    if (!function_exists('tecdoc_find_article_for_import') || !function_exists('import_supplier_format_oem_field')) {
        return $product;
    }

    $article = tecdoc_find_article_for_import($code, $brand);
    if (!is_array($article) || $article === []) {
        return $product;
    }

    $oemCodes = function_exists('tecdoc_article_oem_codes')
        ? tecdoc_article_oem_codes($article)
        : [];
    $oem = import_supplier_format_oem_field($oemCodes, $brand, $code);
    if ($oem === '') {
        return $product;
    }

    $product['pOem'] = $oem;

    return $product;
}

/** Denumirea originală din CSV furnizor (nu titlul SEO deja construit). */
function import_consumable_supplier_raw_name(array $product): string
{
    $raw = json_decode((string) ($product['raw_json'] ?? '{}'), true);
    if (!is_array($raw)) {
        return '';
    }

    $rows = $raw['rows'] ?? null;
    if (is_array($rows) && isset($rows[0]) && is_array($rows[0])) {
        $name = trim((string) ($rows[0]['art name'] ?? $rows[0]['art_name'] ?? ''));
        if ($name !== '') {
            return $name;
        }
    }

    return trim((string) ($raw['supplier_price']['supplier_name'] ?? ''));
}

/**
 * Toate rândurile CSV TecDoc pentru codul produs (compatibilități complete Base.html).
 *
 * @param array<string, mixed> $product
 * @return array<int, array<string, mixed>>
 */
function import_consumable_collect_tecdoc_group_rows(array $product, ?array $tecdocRecord = null, bool $allowFileScan = true): array
{
    if (!$allowFileScan) {
        if ($tecdocRecord !== null && function_exists('import_tecdoc_record_to_raw_row')) {
            return [import_tecdoc_record_to_raw_row($tecdocRecord)];
        }

        return [];
    }
    if (!function_exists('import_tecdoc_collect_rows_for_codes')) {
        require_once __DIR__ . '/import_tecdoc_lib.php';
    }

    $searchCodes = [];
    if (function_exists('import_oem_codes_from_product')) {
        $searchCodes = import_oem_codes_from_product($product);
    }
    $code = trim((string) ($product['pCode'] ?? ''));
    if ($code !== '') {
        array_unshift($searchCodes, $code);
    }
    $searchCodes = array_values(array_unique(array_filter($searchCodes, static fn (string $c): bool => $c !== '')));

    if ($searchCodes !== [] && function_exists('import_resolve_uploaded_tecdoc_files')) {
        $tecdocFiles = import_resolve_uploaded_tecdoc_files();
        if ($tecdocFiles !== []) {
            $rowGroups = import_tecdoc_collect_rows_for_codes($tecdocFiles, $searchCodes);
            foreach ($searchCodes as $searchCode) {
                $norm = function_exists('import_normalize_product_code')
                    ? import_normalize_product_code($searchCode)
                    : $searchCode;
                if ($norm !== '' && !empty($rowGroups[$norm]) && is_array($rowGroups[$norm])) {
                    return array_values($rowGroups[$norm]);
                }
            }
        }
    }

    if ($tecdocRecord !== null && function_exists('import_tecdoc_record_to_raw_row')) {
        return [import_tecdoc_record_to_raw_row($tecdocRecord)];
    }

    return [];
}

/**
 * Descriere TecDoc API format catalog (dl/dt/dd) — ca pe pagina produs.
 *
 * @param array<string, mixed> $product
 */
function import_consumable_apply_tecdoc_api_description(array &$product): bool
{
    $code = trim((string) ($product['pCode'] ?? ''));
    if ($code === '') {
        return false;
    }

    $tecdocPath = dirname(__DIR__, 4) . '/system/tecdoc_description.php';
    if (is_file($tecdocPath)) {
        require_once $tecdocPath;
    }
    $dualPath = dirname(__DIR__, 4) . '/system/product_dual_description.php';
    if (is_file($dualPath)) {
        require_once $dualPath;
    }

    if (!function_exists('tecdoc_build_product_description')) {
        return false;
    }

    if (function_exists('import_throttle_tecdoc_api_call')) {
        import_throttle_tecdoc_api_call();
    }

    $articleId = 0;
    $raw = json_decode((string) ($product['raw_json'] ?? '{}'), true);
    if (is_array($raw)) {
        $articleId = (int) ($raw['tecdoc_api']['article_id'] ?? $raw['tecdoc_file']['ttc_art_id'] ?? 0);
    }

    $descResult = tecdoc_build_product_description(
        $code,
        trim((string) ($product['pBrand'] ?? '')),
        trim((string) ($product['pName'] ?? '')),
        $articleId,
        trim((string) ($product['pOem'] ?? ''))
    );

    $html = trim((string) ($descResult['html'] ?? ''));
    if ($html === '') {
        return false;
    }

    if (!is_array($raw)) {
        $raw = [];
    }
    $raw['tecdoc_api'] = array_merge(is_array($raw['tecdoc_api'] ?? null) ? $raw['tecdoc_api'] : [], [
        'found' => true,
        'article_id' => (int) ($descResult['article_id'] ?? 0),
        'description_source' => 'tecdoc_api',
        'query_code' => $code,
        'query_brand' => trim((string) ($product['pBrand'] ?? '')),
    ]);
    $product['raw_json'] = json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if (function_exists('besoiu_apply_product_description')) {
        besoiu_apply_product_description($product, $html);
    } else {
        $product['pNote'] = $html;
    }

    return true;
}

/**
 * Foaie dl/dt/dd din denumirea furnizor când lipsește TecDoc (ulei/lichide).
 *
 * @param array<string, mixed> $product
 */
function import_consumable_build_supplier_sheet_description(array $product): string
{
    $notePath = dirname(__DIR__, 4) . '/system/tecdoc_description.php';
    if (is_file($notePath)) {
        require_once $notePath;
    }
    if (!function_exists('tecdoc_desc_build_sheet_html')) {
        return '';
    }

    $name = import_consumable_supplier_raw_name($product);
    if ($name === '') {
        $name = trim((string) ($product['pName'] ?? ''));
    }
    $brand = trim((string) ($product['pBrand'] ?? ''));
    $code = trim((string) ($product['pCode'] ?? ''));
    $hay = mb_strtolower($name, 'UTF-8');

    $rows = [];
    $seen = [];
    if ($name !== '') {
        tecdoc_desc_rows_append($rows, $seen, 'Descrierea', $name);
    }
    if ($brand !== '') {
        tecdoc_desc_rows_append($rows, $seen, 'Producătorul', $brand);
    }
    if ($code !== '') {
        tecdoc_desc_rows_append($rows, $seen, 'Număr articol', $code);
    }
    if (preg_match('/(\d+w\d+)/i', $name, $viscosity)) {
        tecdoc_desc_rows_append($rows, $seen, 'Vâscozitate', strtoupper($viscosity[1]));
    }
    if (preg_match('/(\d+)\s*l\b/i', $name, $volume)) {
        tecdoc_desc_rows_append($rows, $seen, 'Volum', $volume[1] . ' L');
    }
    if (str_contains($hay, 'antigel') || str_contains($hay, 'coolant')) {
        tecdoc_desc_rows_append($rows, $seen, 'Tip produs', 'Antigel');
    }
    if (str_contains($hay, 'lichid') && str_contains($hay, 'fran')) {
        tecdoc_desc_rows_append($rows, $seen, 'Tip produs', 'Lichid de frână');
        if (preg_match('/dot\s*([345])/i', $name, $dot)) {
            tecdoc_desc_rows_append($rows, $seen, 'Standard DOT', 'DOT ' . $dot[1]);
        }
    }
    tecdoc_desc_rows_append($rows, $seen, 'Condiție', 'Nou');

    $oemLines = [];
    foreach (preg_split('/\s*,\s*/', trim((string) ($product['pOem'] ?? ''))) ?: [] as $oemCode) {
        $oemCode = trim($oemCode);
        if ($oemCode !== '') {
            $oemLines[] = ['brand' => 'OEM', 'number' => $oemCode];
        }
    }

    return tecdoc_desc_build_sheet_html($rows, $oemLines);
}

/** @param array<int, array<string, mixed>> $rows */
function import_consumable_apply_supplier_fallback_rows(array &$product, array $rows): void
{
    if (!function_exists('import_base_apply_without_vehicle')) {
        require_once __DIR__ . '/import_base_lib.php';
    }
    if ($rows === []) {
        $supplierName = import_consumable_supplier_raw_name($product);
        $rows = [[
            'art code 1' => trim((string) ($product['pCode'] ?? '')),
            'art brand' => trim((string) ($product['pBrand'] ?? '')),
            'art name' => $supplierName !== '' ? $supplierName : trim((string) ($product['pName'] ?? '')),
            'art cross' => trim((string) ($product['pOem'] ?? '')),
            'parts info' => trim((string) ($product['pSpecs'] ?? '')),
        ]];
    }

    import_base_apply_without_vehicle($product, $rows);
}

/**
 * Titlu SEO + descriere duală Base (fără suprascriere HTML complet existent).
 *
 * @param array<string, mixed> $product
 * @param array<string, mixed>|null $tecdocRecord
 * @param array<string, mixed> $opts light, skip_csv_scan, skip_tecdoc_api
 * @return array<string, mixed>
 */
function import_consumable_enrich_base_content(array $product, ?array $tecdocRecord = null, array $opts = []): array
{
    $light = !empty($opts['light']);
    $allowCsvScan = !$light && empty($opts['skip_csv_scan']);
    $allowApi = !$light && empty($opts['skip_tecdoc_api']);
    $dualPath = dirname(__DIR__, 4) . '/system/product_dual_description.php';
    if (is_file($dualPath)) {
        require_once $dualPath;
    }

    $notePath = dirname(__DIR__, 4) . '/system/tecdoc_description.php';
    if (is_file($notePath)) {
        require_once $notePath;
    }

    if (!function_exists('import_base_has_vehicle_data')) {
        require_once __DIR__ . '/import_base_lib.php';
    }

    if (function_exists('tecdoc_note_looks_complete') && tecdoc_note_looks_complete((string) ($product['pNote'] ?? ''))) {
        return $product;
    }

    $groupRows = import_consumable_collect_tecdoc_group_rows($product, $tecdocRecord, $allowCsvScan);

    // 1) Descriere completă Base.html din CSV TecDoc (specs + compatibilități + OEM).
    if ($groupRows !== [] && import_base_has_vehicle_data($groupRows)) {
        $base = $product;
        if (function_exists('import_base_apply_to_product')
            && import_base_apply_to_product($base, ['rows' => $groupRows], [])) {
            return $base;
        }
    }

    // 2) TecDoc RapidAPI — foaie dl/dt/dd (criterii + OEM), ca product.php.
    $base = $product;
    if ($allowApi && import_consumable_apply_tecdoc_api_description($base)) {
        $savedNote = (string) ($base['pNote'] ?? '');
        import_consumable_apply_supplier_fallback_rows($base, $groupRows !== [] ? $groupRows : []);
        if ($savedNote !== '') {
            besoiu_apply_product_description($base, $savedNote);
        }

        return $base;
    }

    // 3) CSV TecDoc fără vehicul (specs/OEM) sau doar furnizor.
    $base = $product;
    import_consumable_apply_supplier_fallback_rows($base, $groupRows);

    $sheet = import_consumable_build_supplier_sheet_description($base);
    if ($sheet !== '' && function_exists('tecdoc_note_looks_complete') && tecdoc_note_looks_complete($sheet)) {
        if (function_exists('besoiu_apply_product_description')) {
            besoiu_apply_product_description($base, $sheet);
        } else {
            $base['pNote'] = $sheet;
        }
    }

    return $base;
}

/**
 * @param array<string, mixed> $product
 * @param array<string, mixed>|null $tecdocRecord
 * @return array<string, mixed>
 */
function import_consumable_enrich_product_row(
    array $product,
    ?array $tecdocRecord = null,
    bool $skipTecdocCsv = true,
    array $opts = []
): array {
    $light = !empty($opts['light']);
    if (!$light && $skipTecdocCsv) {
        $product = import_consumable_enrich_oem_via_api($product);
    }

    return import_consumable_enrich_base_content($product, $tecdocRecord, $opts);
}

/**
 * Reaplică prețul din index multi-furnizor (brand+cod) înainte de publicare.
 *
 * @param array<string, mixed> $product
 * @return array<string, mixed>
 */
function import_consumable_apply_price_index(
    array $product,
    array $priceIndex,
    AdaosComercialService $markupService
): array {
    if ($priceIndex === [] || !function_exists('import_apply_supplier_pricing')) {
        return $product;
    }

    return import_apply_supplier_pricing($product, $priceIndex, $markupService);
}

/**
 * Inserează un produs incomplet în import_produse pentru review manual.
 *
 * @param array<string, mixed> $staging
 * @return array{queued:int}
 */
function import_consumable_stage_for_review(PDO $pdo, array $staging, array $options = []): array
{
    if (!function_exists('import_prepare_staging_insert')) {
        require_once __DIR__ . '/import_identity_lib.php';
    }

    $staging['status'] = 'pending';
    $raw = json_decode((string) ($staging['raw_json'] ?? '{}'), true);
    if (!is_array($raw)) {
        $raw = [];
    }
    $raw['import_mode'] = 'consumable_staged_incomplete';
    $raw['cron_sync'] = !empty($options['cron_sync']);
    if (!empty($options['supplier_code'])) {
        $raw['supplier_code'] = strtoupper(trim((string) $options['supplier_code']));
    }
    $staging['raw_json'] = json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $prepared = import_prepare_staging_insert($pdo, $staging);
    $columns = array_keys($prepared);
    $placeholders = implode(',', array_map(static fn (string $c): string => ':' . $c, $columns));
    $stmt = $pdo->prepare(
        'INSERT INTO import_produse (`' . implode('`,`', $columns) . '`) VALUES (' . $placeholders . ')'
    );
    $stmt->execute($prepared);

    return ['queued' => 1];
}

/**
 * Produs minimal pentru potrivire categorie din rând furnizor (înainte de markup/TecDoc).
 *
 * @param array<string, mixed> $entry
 * @return array<string, mixed>
 */
function import_consumable_stub_from_entry(array $entry): array
{
    return [
        'pName' => trim((string) ($entry['name'] ?? '')),
        'pSubcategory' => '',
        'raw_json' => '{}',
    ];
}

/**
 * Parcurge integral CSV-urile furnizor și colectează consumabile după denumire.
 * Nu folosește import_build_supplier_catalog (care oprea la primele N rânduri oarecare).
 *
 * @param array<int, array{path:string,name:string}> $supplierFiles
 * @return array{
 *   products: array<int, array<string, mixed>>,
 *   total_scanned: int,
 *   matched: int,
 *   stopped_early: bool
 * }
 */
function import_consumable_scan_supplier_stream(
    array $supplierFiles,
    array $selectedCategories,
    AdaosComercialService $markupService,
    array $priceIndex,
    int $maxPreview,
    string $brandFilter = '',
    bool $priorityFluidsOnly = false
): array {
    if (!function_exists('import_stream_supplier_file')) {
        require_once __DIR__ . '/import_supplier_lib.php';
    }

    $priorityMap = import_supplier_priority_map();
    $brandFilterNorm = import_normalize_supplier_brand($brandFilter);
    $maxPreview = max(1, $maxPreview);
    $matched = [];
    $totalScanned = 0;
    $stoppedEarly = false;
    $bestByKey = [];

    foreach ($supplierFiles as $file) {
        $path = (string) ($file['path'] ?? '');
        $filename = (string) ($file['name'] ?? basename($path));
        if ($path === '' || !is_file($path)) {
            continue;
        }

        $kind = import_classify_file($path, $filename);
        $supplierType = str_starts_with($kind, 'supplier:') ? substr($kind, 9) : null;
        if ($supplierType === null) {
            continue;
        }

        $stop = false;
        import_stream_supplier_file(
            $path,
            $filename,
            $supplierType,
            static function (array $row, string $type) use (
                &$matched,
                &$totalScanned,
                &$stoppedEarly,
                &$bestByKey,
                &$stop,
                $selectedCategories,
                $markupService,
                $priceIndex,
                $maxPreview,
                $brandFilterNorm,
                $priorityMap,
                $priorityFluidsOnly
            ): void {
                if (!function_exists('import_supplier_row_passes_supplier_scan_rules')) {
                    require_once __DIR__ . '/import_supplier_stock_zero_lib.php';
                }
                if (!import_supplier_row_passes_supplier_scan_rules($row, $type)) {
                    return;
                }

                $entry = import_supplier_row_to_entry($row, $type);
                if ($entry === null) {
                    return;
                }

                if ($brandFilterNorm !== '') {
                    $entryBrand = import_normalize_supplier_brand((string) ($entry['brand'] ?? ''));
                    if ($entryBrand === '' || !str_contains($entryBrand, $brandFilterNorm)) {
                        return;
                    }
                }

                ++$totalScanned;

                $stub = import_consumable_stub_from_entry($entry);
                if (!import_consumable_matches_selection($stub, $selectedCategories)) {
                    return;
                }

                $product = import_consumable_entry_to_product($entry, $markupService, $priceIndex);
                if ($product === null
                    || !import_consumable_has_supplier_price($product)
                    || !import_consumable_matches_selection($product, $selectedCategories)) {
                    return;
                }

                if ($priorityFluidsOnly && !import_consumable_is_fluid_consumable($product)) {
                    return;
                }

                $key = (string) ($product['pCode'] ?? '') . '|' . str_replace(
                    ' ',
                    '',
                    import_normalize_supplier_brand((string) ($product['pBrand'] ?? ''))
                );
                $newPrice = (float) ($product['pBasePrice'] ?? 0);
                $existing = $bestByKey[$key] ?? null;
                if ($existing !== null) {
                    $oldPrice = (float) ($existing['pBasePrice'] ?? 0);
                    if (!import_price_index_should_replace(
                        [
                            'price' => $oldPrice,
                            'supplier' => (string) ($existing['pSupplier'] ?? ''),
                            'brand' => (string) ($existing['pBrand'] ?? ''),
                        ],
                        $newPrice,
                        (string) ($entry['supplier'] ?? ''),
                        $priorityMap,
                        (string) ($entry['brand'] ?? '')
                    )) {
                        return;
                    }
                    $matched = array_values(array_filter(
                        $matched,
                        static fn (array $p): bool => ((string) ($p['pCode'] ?? '') . '|' . str_replace(
                            ' ',
                            '',
                            import_normalize_supplier_brand((string) ($p['pBrand'] ?? ''))
                        )) !== $key
                    ));
                }

                $bestByKey[$key] = $product;
                $matched[] = $product;

                if (count($matched) >= $maxPreview) {
                    $stop = true;
                    $stoppedEarly = true;
                }
            },
            $stop
        );

        if ($stop) {
            break;
        }
    }

    return [
        'products' => array_slice($matched, 0, $maxPreview),
        'total_scanned' => $totalScanned,
        'matched' => count($matched),
        'stopped_early' => $stoppedEarly,
    ];
}

/**
 * @param array<int, array<string, mixed>> $filesMeta
 * @return array<string, mixed>
 */
function import_consumable_scan_preview(array $filesMeta, array $options = []): array
{
    $selected = import_consumable_normalize_categories((array) ($options['categories'] ?? []));
    $maxPreview = max(1, min(500, (int) ($options['max_preview'] ?? 200)));
    $brandFilter = trim((string) ($options['brand_filter'] ?? ''));

    $resolved = import_consumable_resolve_supplier_files($filesMeta);
    if ($resolved['missing'] !== []) {
        return [
            'success' => false,
            'message' => 'Fișierele încărcate nu mai sunt pe server. Reîncarcă listele furnizor.',
            'products' => [],
            'stats' => ['total_scanned' => 0, 'matched' => 0, 'with_price' => 0],
        ];
    }
    if ($resolved['supplier_files'] === []) {
        return [
            'success' => false,
            'message' => 'Încarcă cel puțin o listă furnizor (Autonet, Autototal, Elit etc.).',
            'products' => [],
            'stats' => ['total_scanned' => 0, 'matched' => 0, 'with_price' => 0],
        ];
    }

    $validation = import_validate_uploaded_files($resolved['supplier_files'], [], []);
    if (!$validation['ok']) {
        return [
            'success' => false,
            'message' => 'Validare fișiere eșuată: ' . implode(' ', array_map('strval', $validation['errors'] ?? [])),
            'products' => [],
            'stats' => ['total_scanned' => 0, 'matched' => 0, 'with_price' => 0],
        ];
    }

    $priceIndex = function_exists('import_build_price_index')
        ? import_build_price_index($resolved['supplier_files'])
        : [];
    $priceIndexSize = function_exists('import_price_index_size')
        ? import_price_index_size($priceIndex)
        : 0;

    $markupService = new AdaosComercialService();
    $stream = import_consumable_scan_supplier_stream(
        $resolved['supplier_files'],
        $selected,
        $markupService,
        $priceIndex,
        $maxPreview,
        $brandFilter
    );
    $matched = [];
    $defs = import_consumable_category_defs();

    foreach ($stream['products'] as $product) {
        if (!is_array($product)) {
            continue;
        }
        $cats = import_consumable_detect_categories($product);
        $labels = [];
        foreach ($cats as $c) {
            if (isset($defs[$c])) {
                $labels[] = $defs[$c]['label'];
            }
        }
        $product['__consumable_categories'] = $cats;
        $product['__consumable_labels'] = $labels;
        $product['__epiesa_status'] = 'pending';
        $matched[] = $product;
    }

    if ($matched === [] && ($stream['total_scanned'] ?? 0) === 0) {
        return [
            'success' => false,
            'message' => 'Nu am putut citi rânduri din listele furnizor. Verifică formatul CSV.',
            'products' => [],
            'stats' => ['total_scanned' => 0, 'matched' => 0, 'with_price' => 0],
        ];
    }

    if ($matched === []) {
        return [
            'success' => true,
            'message' => 'Am parcurs ' . (int) ($stream['total_scanned'] ?? 0)
                . ' rânduri din cataloage — niciun ulei/lichid/electric consumabil cu preț. '
                . 'Listele tale sunt în mare parte piese de schimb; uleiurile apar rar (ex. Autototal «ULEI MOTOR»).',
            'products' => [],
            'stats' => [
                'total_scanned' => (int) ($stream['total_scanned'] ?? 0),
                'matched' => 0,
                'with_price' => 0,
                'supplier_files' => count($resolved['supplier_files']),
                'price_index_size' => $priceIndexSize,
                'categories' => $selected,
            ],
        ];
    }

    return [
        'success' => true,
        'message' => count($matched) . ' consumabile găsite (parcurse '
            . (int) ($stream['total_scanned'] ?? 0) . ' rânduri din fișiere, cu preț).',
        'products' => $matched,
        'stats' => [
            'total_scanned' => (int) ($stream['total_scanned'] ?? 0),
            'matched' => count($matched),
            'with_price' => count($matched),
            'supplier_files' => count($resolved['supplier_files']),
            'price_index_size' => $priceIndexSize,
            'categories' => $selected,
        ],
    ];
}

/**
 * @param array<int, array<string, mixed>> $products
 * @return array<string, mixed>
 */
function import_consumable_scan_publish(PDO $pdo, array $products, array $options = []): array
{
    require_once dirname(__DIR__, 3) . '/config/cron_import.php';

    $limit = max(1, min(100, (int) ($options['limit'] ?? admin_cron_import_limit())));
    $publishMode = admin_cron_import_publish_mode();
    $checkEpiesa = !array_key_exists('check_epiesa', $options) || !empty($options['check_epiesa']);
    $markupService = new AdaosComercialService();

    $stats = [
        'limit' => $limit,
        'selected' => count($products),
        'published' => 0,
        'added' => 0,
        'updated' => 0,
        'skipped' => 0,
        'blocked_critical' => 0,
        'vitrina' => 0,
        'epiesa_checked' => 0,
        'epiesa_found' => 0,
    ];
    $log = [];

    $appendLog = static function (string $message, string $level = 'info') use (&$log): void {
        $log[] = [
            'at' => date('c'),
            'level' => $level,
            'message' => $message,
        ];
    };

    $appendLog('Import consumabile pornit — limită ' . $limit . ' produse.', 'info');

    foreach ($products as $product) {
        if ($stats['published'] >= $limit) {
            $appendLog('Limită atinsă (' . $limit . ' produse). Oprire.', 'warn');
            break;
        }
        if (!is_array($product)) {
            continue;
        }
        if (!import_consumable_has_supplier_price($product)) {
            ++$stats['skipped'];
            $appendLog('Sărit (fără preț furnizor): ' . mb_substr((string) ($product['pName'] ?? ''), 0, 60, 'UTF-8'), 'warn');
            continue;
        }

        $product = import_consumable_enrich_product_row($product, null, true);
        $product = import_consumable_resolve_image($product, static function (string $msg, string $level) use ($appendLog): void {
            $appendLog($msg, $level);
        }, import_consumable_image_source_mode($product));
        $staging = import_consumable_build_staging_row($product, $markupService);
        if (function_exists('import_cron_light_prepare_row')) {
            $staging = import_cron_light_prepare_row($staging);
        }
        $staging['__force_image_update'] = true;
        $pipelinePath = dirname(__DIR__, 4) . '/system/image_search_pipeline.php';
        if (is_file($pipelinePath)) {
            require_once $pipelinePath;
            $staging = besoiu_image_attach_audit_meta($staging);
            if (!empty($staging['__image_audit_retry'])) {
                $appendLog('Audit imagini: recomandare înlocuire imagine — re-caut surse.', 'warn');
                $staging = import_consumable_resolve_image($staging, static function (string $msg, string $level) use ($appendLog): void {
                    $appendLog($msg, $level);
                }, 'auto');
            }
        }
        $name = (string) ($staging['pName'] ?? '');
        $appendLog('Procesez: ' . mb_substr($name, 0, 72, 'UTF-8'), 'info');

        $epiesa = ['checked' => false, 'found' => false, 'vitrina' => false];
        if ($checkEpiesa) {
            $epiesa = import_consumable_enrich_epiesa($staging, static function (string $msg, string $level) use ($appendLog): void {
                $appendLog($msg, $level);
            });
            if ($epiesa['checked']) {
                ++$stats['epiesa_checked'];
            }
            if ($epiesa['found']) {
                ++$stats['epiesa_found'];
            }
        }

        if (!function_exists('besoiu_import_row_blocks_auto_publish')) {
            require_once dirname(__DIR__, 4) . '/system/import-queue-critical.php';
        }
        if (besoiu_import_row_blocks_auto_publish($staging)) {
            ++$stats['skipped'];
            ++$stats['blocked_critical'];
            $appendLog(
                'Sărit — date critice lipsă (categorie/brand/preț 0/imagine): '
                . mb_substr($name, 0, 60, 'UTF-8'),
                'warn'
            );
            continue;
        }

        $result = import_publish_prepared_row($pdo, $staging, $publishMode);
        $action = (string) ($result['action'] ?? 'skipped');
        ++$stats['published'];

        if ($action === 'inserted') {
            ++$stats['added'];
        } elseif ($action === 'updated') {
            ++$stats['updated'];
        } else {
            ++$stats['skipped'];
        }

        $productId = (int) ($result['product_id'] ?? $result['existing_id'] ?? 0);
        if ($productId > 0 && !empty($epiesa['vitrina'])) {
            import_consumable_set_vitrina($pdo, $productId);
            ++$stats['vitrina'];
            $appendLog('ePiesa OK → vitrină homepage: ' . mb_substr($name, 0, 60, 'UTF-8'), 'ok');
        }

        $price = (string) ($staging['pPrice'] ?? '');
        $appendLog(
            ucfirst($action) . ' în magazin' . ($price !== '' ? (' · ' . $price . ' RON') : ''),
            $action === 'skipped' ? 'warn' : 'ok'
        );
    }

    $summary = 'Import consumabile: ' . $stats['published'] . '/' . $limit
        . ' — +' . $stats['added'] . ' noi, ~' . $stats['updated'] . ' actualizate, '
        . $stats['vitrina'] . ' pe vitrină (ePiesa).';
    $appendLog($summary, $stats['published'] > 0 ? 'ok' : 'info');

    return [
        'success' => $stats['published'] > 0 || $stats['skipped'] > 0,
        'message' => $summary,
        'stats' => $stats,
        'log' => array_reverse($log),
    ];
}

/**
 * Filtrează lista de produse preview la consumabile (ulei · lichide · electrice).
 *
 * @param array<int, array<string, mixed>> $products
 * @return array<int, array<string, mixed>>
 */
function import_consumable_filter_products(array $products, ?array $categories = null, int $limit = 10): array
{
    $categories = import_consumable_normalize_categories($categories ?? []);
    $out = [];

    foreach ($products as $product) {
        if (!is_array($product)) {
            continue;
        }
        if (!import_consumable_has_supplier_price($product)) {
            continue;
        }
        if (!import_consumable_matches_selection($product, $categories)) {
            continue;
        }

        $product['__consumable_categories'] = import_consumable_detect_categories($product);
        $out[] = $product;

        if (count($out) >= $limit) {
            break;
        }
    }

    return $out;
}

/**
 * Pregătire minimă rând cron — fără TecDoc CSV / RapidAPI (evită blocaje).
 *
 * @param array<string, mixed> $row
 * @return array<string, mixed>
 */
function import_cron_light_prepare_row(array $row): array
{
    if (!function_exists('import_field_is_empty')) {
        return $row;
    }

    if (import_field_is_empty((string) ($row['pOem'] ?? '')) && function_exists('import_supplier_format_oem_field')) {
        $inferred = import_supplier_format_oem_field([], (string) ($row['pBrand'] ?? ''), (string) ($row['pCode'] ?? ''));
        if ($inferred !== '') {
            $row['pOem'] = $inferred;
        }
    }

    return $row;
}

/**
 * Curăță denumirea furnizor pentru căutare eMAG (ulei/lichide).
 */
function import_consumable_normalize_emag_name(string $name): string
{
    $name = mb_strtolower(trim($name), 'UTF-8');
    $name = preg_replace('/\s*-\s*o\.e\.[^\]]*/ui', '', $name) ?? $name;
    $name = preg_replace('/\bo\.e\.\s*fd\b/ui', '', $name) ?? $name;
    $name = preg_replace('/\bo\.e\.\b/ui', '', $name) ?? $name;
    $name = preg_replace('/\[(.*?)\]/u', ' $1 ', $name) ?? $name;
    $name = preg_replace('/\s+/u', ' ', $name) ?? $name;

    return trim($name);
}

/**
 * Interogări eMAG optimizate pentru uleiuri și lichide (denumiri furnizor ≠ titlu eMAG).
 *
 * @param array<string, mixed> $product
 * @return array<int, string>
 */
function import_consumable_emag_queries(array $product): array
{
    $cats = $product['__consumable_categories'] ?? import_consumable_detect_categories($product);
    if (array_intersect($cats, ['ulei', 'lichide']) === []) {
        return [];
    }

    $rawName = trim((string) ($product['pName'] ?? ''));
    $name = import_consumable_normalize_emag_name($rawName);
    $brand = mb_strtolower(trim((string) ($product['pBrand'] ?? '')), 'UTF-8');
    $queries = [];

    $add = static function (string $value) use (&$queries): void {
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
        if ($value !== '' && !in_array($value, $queries, true)) {
            $queries[] = $value;
        }
    };

    $add($name);
    if ($rawName !== '' && $rawName !== $name) {
        $add(import_consumable_normalize_emag_name($rawName));
    }

    if (in_array('ulei', $cats, true)) {
        $add('ulei motor auto');
        if ($brand !== '' && !in_array($brand, ['vw', 'fd', 'oe'], true)) {
            $add('ulei motor ' . $brand);
        }
        if (preg_match('/(\d+w\d+)/i', $name, $viscosity)) {
            $visc = strtolower($viscosity[1]);
            $add('ulei motor ' . $visc);
            if ($brand !== '') {
                $add('ulei motor ' . $visc . ' ' . $brand);
            }
            if (preg_match('/(\d+)\s*l\b/i', $name, $volume)) {
                $add('ulei motor ' . $visc . ' ' . $volume[1] . 'l');
            }
        }
        if (preg_match('/\batf\b/i', $name)) {
            $add('ulei cutie automata atf');
        }
    }

    if (in_array('lichide', $cats, true)) {
        if (str_contains($name, 'antigel') || str_contains($name, 'coolant') || str_contains($name, 'antifriz')) {
            $add('antigel auto');
            if ($brand !== '') {
                $add('antigel ' . $brand);
            }
        }
        if (str_contains($name, 'lichid') && (str_contains($name, 'fran') || str_contains($name, 'frân'))) {
            $add('lichid frana auto');
            if (preg_match('/dot\s*([345])/i', $name, $dot)) {
                $add('lichid frana dot ' . $dot[1]);
            }
        }
        if (str_contains($name, 'adblue') || str_contains($name, 'ad blue')) {
            $add('adblue');
        }
        if (str_contains($name, 'parbriz') || str_contains($name, 'stergator') || str_contains($name, 'ștergător')) {
            $add('lichid parbriz auto');
        }
    }

    return $queries;
}

/**
 * @param array<string, mixed> $product
 */
function import_consumable_image_source_mode(array $product): string
{
    unset($product);

    return 'auto';
}

/** @param callable(string,string):void|null $log */
function import_consumable_log_active_image_plans(?callable $log = null): void
{
    if (!is_callable($log)) {
        return;
    }

    $overlayPath = dirname(__DIR__, 4) . '/storage/scraper/image_pipeline_overlay.json';
    if (!is_file($overlayPath)) {
        $log('Pipeline imagini: fără overlay — folosesc config Scraper.', 'info');

        return;
    }

    $overlay = json_decode((string) file_get_contents($overlayPath), true);
    $plans = is_array($overlay['image_plans'] ?? null) ? $overlay['image_plans'] : [];
    $labels = [];
    foreach ($plans as $plan) {
        if (!is_array($plan) || empty($plan['enabled'])) {
            continue;
        }
        $src = trim((string) ($plan['source_id'] ?? ''));
        if ($src === '') {
            continue;
        }
        $labels[] = 'Plan ' . (int) ($plan['tier'] ?? 0) . ' → ' . $src;
    }

    if ($labels === []) {
        $log('Pipeline imagini: niciun plan activ în /admin/scraper.', 'warn');

        return;
    }

    $log('Pipeline imagini (Scraper): ' . implode(' · ', $labels), 'info');
}

/**
 * Pipeline imagine — **doar** planurile din /admin/scraper (Pipeline imagini Plan 1→N).
 * Nu mai forțează eMAG sau alte surse în afara configului operatorului.
 *
 * @param array<string, mixed> $product
 * @param callable(string,string):void|null $log
 * @return array<string, mixed>
 */
function import_consumable_resolve_image(array $product, ?callable $log = null, ?string $sourceMode = null): array
{
    $logFn = static function (string $msg, string $level = 'info') use ($log): void {
        if (is_callable($log)) {
            $log($msg, $level);
        }
    };

    unset($sourceMode);

    if (function_exists('import_row_image_url') && function_exists('import_image_url_is_trusted')) {
        $existing = import_row_image_url($product);
        if ($existing !== '' && import_image_url_is_trusted($existing, (string) ($product['pImageSource'] ?? ''))) {
            $logFn('Imagine validă deja în rând (' . (string) ($product['pImageSource'] ?? 'csv') . ').', 'info');

            return $product;
        }
    }

    $servicePath = dirname(__DIR__, 4) . '/lib/Scraper/ImageSearchService.php';
    if (!is_file($servicePath)) {
        $logFn('Pipeline imagini indisponibil (lipsește ImageSearchService).', 'warn');

        return import_consumable_mark_image_pipeline_done($product);
    }

    require_once $servicePath;
    $logFn('Imagine (pipeline Scraper Plan 1→N)…', 'info');
    $resolved = \ImageSearchService::resolve($product, [
        'log' => static function (string $msg, string $level = 'info') use ($logFn): void {
            $logFn($msg, $level === 'ok' ? 'ok' : $level);
        },
    ]);

    if (is_array($resolved['hit'] ?? null) && trim((string) ($resolved['hit']['url'] ?? '')) !== '') {
        $product = $resolved['product'];
        $lastTried = $resolved['tried'][count($resolved['tried']) - 1] ?? [];
        $logFn('Imagine găsită via plan scraper (tier ' . ($lastTried['tier'] ?? '?') . ').', 'ok');

        return import_consumable_mark_image_pipeline_done($product);
    }

    foreach ((array) ($resolved['tried'] ?? []) as $t) {
        if (!is_array($t)) {
            continue;
        }
        if (($t['status'] ?? '') === 'skipped') {
            $logFn('Plan ' . ($t['tier'] ?? '?') . ' ' . ($t['source_id'] ?? '') . ': ' . ($t['message'] ?? 'sărit'), 'warn');
            continue;
        }
        if (($t['status'] ?? '') === 'miss') {
            $logFn('Plan ' . ($t['tier'] ?? '?') . ' ' . ($t['source_id'] ?? '') . ': ' . ($t['message'] ?? 'fără rezultat'), 'warn');
        }
    }

    $logFn('Public fără imagine nouă (placeholder).', 'warn');

    return import_consumable_mark_image_pipeline_done($product);
}

/** @deprecated folosește import_consumable_resolve_image */
function import_cron_fetch_product_image(array $product, ?callable $log = null): array
{
    return import_consumable_resolve_image($product, $log);
}

/** @param array<string, mixed> $row @return array<string, mixed> */
function import_consumable_mark_image_pipeline_done(array $row): array
{
    $raw = json_decode((string) ($row['raw_json'] ?? '{}'), true);
    if (!is_array($raw)) {
        $raw = [];
    }
    $raw['cron_image_pipeline_done'] = date('c');
    $raw['cron_emag_attempted_at'] = $raw['cron_image_pipeline_done'];
    $row['raw_json'] = json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    return $row;
}

/** @deprecated */
function import_cron_mark_image_attempted(array $row): array
{
    return import_consumable_mark_image_pipeline_done($row);
}

/**
 * Cron Sync: același enrich ca Import, apoi publicare directă + vitrină.
 *
 * @param array<int, array<string, mixed>> $products
 * @param array<string, mixed> $options supplier_code, limit, check_epiesa, always_vitrina, logger
 * @return array<string, int>
 */
function import_consumable_cron_publish_batch(
    PDO $pdo,
    array $products,
    AdaosComercialService $markupService,
    array $options = []
): array {
    require_once dirname(__DIR__, 3) . '/config/cron_import.php';

    $limit = max(1, min(50, (int) ($options['limit'] ?? admin_cron_import_limit())));
    $checkEpiesa = !array_key_exists('check_epiesa', $options) || !empty($options['check_epiesa']);
    $alwaysVitrina = array_key_exists('always_vitrina', $options)
        ? !empty($options['always_vitrina'])
        : admin_cron_always_vitrina();
    $supplierCode = strtoupper(trim((string) ($options['supplier_code'] ?? '')));
    $logger = $options['logger'] ?? null;
    $publishMode = admin_cron_import_publish_mode();

    $stats = [
        'parsed' => count($products),
        'published' => 0,
        'added' => 0,
        'updated' => 0,
        'skipped' => 0,
        'vitrina' => 0,
        'with_image' => 0,
        'tecdoc_enriched' => 0,
        'epiesa_checked' => 0,
        'epiesa_found' => 0,
        'queued' => 0,
        'without_price' => 0,
        'vitrina_candidates' => 0,
    ];

    $skipTecdocCsv = array_key_exists('skip_tecdoc_csv', $options)
        ? !empty($options['skip_tecdoc_csv'])
        : admin_cron_skip_tecdoc_csv_lookup();
    $priceIndex = is_array($options['price_index'] ?? null) ? $options['price_index'] : [];
    $stageIncomplete = array_key_exists('stage_incomplete', $options)
        ? !empty($options['stage_incomplete'])
        : (function_exists('admin_cron_stage_incomplete') && admin_cron_stage_incomplete());

    $totalPlanned = min($limit, count($products));
    $log = static function (string $message, string $level = 'info', ?int $progressPct = null) use ($logger): void {
        if (!is_callable($logger)) {
            return;
        }
        if ($progressPct !== null && $progressPct >= 0 && $progressPct <= 100) {
            $message = '[PROGRESS:' . $progressPct . '%] ' . $message;
        }
        $logger($message, $level);
    };

    $log(
        'Pregătire publicare ' . $totalPlanned . ' consumabile'
        . ($skipTecdocCsv ? ' (mod rapid — fără scan CSV TecDoc)' : ' (scan CSV TecDoc)'),
        'info',
        2
    );

    $tecdocFiles = [];
    $sharedLookup = [];
    if (!$skipTecdocCsv) {
        $log('Încarc fișiere TecDoc locale…', 'info', 5);
        $tecdocFiles = function_exists('import_resolve_uploaded_tecdoc_files')
            ? import_resolve_uploaded_tecdoc_files()
            : [];
        $log(
            count($tecdocFiles) . ' fișier(e) TecDoc — construiesc index coduri (poate dura)…',
            'info',
            8
        );
        $sharedLookup = function_exists('import_build_tecdoc_lookup_for_products')
            ? import_build_tecdoc_lookup_for_products($products, $tecdocFiles)
            : [];
        $log('Index TecDoc gata — ' . count($sharedLookup) . ' potriviri.', 'ok', 12);
    } else {
        import_consumable_log_active_image_plans(static function (string $msg, string $level = 'info') use ($log): void {
            $log($msg, $level, 8);
        });
    }

    $lightEnrich = function_exists('admin_cron_light_enrich') && admin_cron_light_enrich();
    if ($lightEnrich) {
        $log('Mod rapid cron: descriere simplă + pipeline imagini din Scraper.', 'info', 9);
    }

    $itemIndex = 0;
    foreach ($products as $product) {
        if ($stats['published'] >= $limit) {
            $log('Limită atinsă (' . $limit . ' consumabile).', 'warn', 100);
            break;
        }

        ++$itemIndex;
        $pctBase = 12 + (int) floor((($itemIndex - 1) / max(1, $totalPlanned)) * 82);
        $codeLabel = trim((string) ($product['pCode'] ?? ''));
        $nameLabel = mb_substr((string) ($product['pName'] ?? $codeLabel), 0, 64, 'UTF-8');

        $log(
            'Procesez ' . $itemIndex . '/' . $totalPlanned . ': ' . $nameLabel
            . ($codeLabel !== '' ? ' [' . $codeLabel . ']' : ''),
            'info',
            $pctBase
        );

        if ($supplierCode !== '') {
            $product['pSupplier'] = trim((string) ($product['pSupplier'] ?? $supplierCode));
        }

        if ($priceIndex !== []) {
            $product = import_consumable_apply_price_index($product, $priceIndex, $markupService);
        }

        $tecdocRecord = null;
        if (!$skipTecdocCsv && $sharedLookup !== [] && function_exists('import_tecdoc_find_record_for_product')) {
            $tecdocRecord = import_tecdoc_find_record_for_product($product, [], $sharedLookup);
            if ($tecdocRecord !== null) {
                ++$stats['tecdoc_enriched'];
            }
        }

        $enrichOpts = [
            'light' => $lightEnrich,
            'skip_csv_scan' => $lightEnrich || $skipTecdocCsv,
            'skip_tecdoc_api' => $lightEnrich,
        ];

        try {
            $log('Descriere produs…', 'info', min(94, $pctBase + 4));
            $product = import_consumable_enrich_product_row($product, $tecdocRecord, $skipTecdocCsv, $enrichOpts);

            $log('Imagine (pipeline Scraper)…', 'info', min(94, $pctBase + 8));
            $product = import_consumable_resolve_image($product, static function (string $msg, string $level = 'info') use ($log, $pctBase): void {
                $log($msg, $level, min(95, $pctBase + 10));
            }, 'auto');
        } catch (Throwable $enrichError) {
            $log('Eroare enrich/imagine — continui fără: ' . $enrichError->getMessage(), 'error', $pctBase);
        }

        if (function_exists('import_row_image_url')) {
            $imgUrl = import_row_image_url($product);
            if ($imgUrl !== '') {
                ++$stats['with_image'];
            }
        }

        $staging = import_consumable_build_staging_row($product, $markupService);
        if (trim((string) ($staging['pPrice'] ?? '')) === '' && trim((string) ($staging['pBasePrice'] ?? '')) === '') {
            ++$stats['without_price'];
            ++$stats['skipped'];
            $log('Sărit — fără preț valid.', 'warn', $pctBase);
            continue;
        }

        if ($stageIncomplete && import_consumable_needs_review($staging)) {
            $staging = import_cron_light_prepare_row($staging);
            $stageResult = import_consumable_stage_for_review($pdo, $staging, [
                'supplier_code' => $supplierCode,
                'cron_sync' => true,
            ]);
            if (($stageResult['queued'] ?? 0) > 0) {
                ++$stats['queued'];
                $log('Trimis în importreview (preț/imagine incomplet).', 'warn', $pctBase);
            } else {
                ++$stats['skipped'];
            }
            continue;
        }

        $log('Pregătire rând pentru magazin…', 'info', min(96, $pctBase + 16));
        $staging = import_cron_light_prepare_row($staging);
        $staging['__force_image_update'] = true;

        $epiesa = ['checked' => false, 'found' => false, 'vitrina' => false];
        if ($checkEpiesa) {
            $epiesa = import_consumable_enrich_epiesa($staging, $log);
            if ($epiesa['checked']) {
                ++$stats['epiesa_checked'];
            }
            if ($epiesa['found']) {
                ++$stats['epiesa_found'];
            }
        }

        if (!function_exists('besoiu_import_row_blocks_auto_publish')) {
            require_once dirname(__DIR__, 4) . '/system/import-queue-critical.php';
        }
        if (besoiu_import_row_blocks_auto_publish($staging)) {
            ++$stats['skipped'];
            if (!isset($stats['blocked_critical'])) {
                $stats['blocked_critical'] = 0;
            }
            ++$stats['blocked_critical'];
            $log(
                'Sărit — date critice lipsă (categorie/brand/preț 0/imagine): '
                . mb_substr((string) ($staging['pName'] ?? ''), 0, 56, 'UTF-8'),
                'warn',
                min(98, $pctBase + 18)
            );
            continue;
        }

        $log('Public în magazin…', 'info', min(98, $pctBase + 18));
        $result = import_publish_prepared_row($pdo, $staging, $publishMode);
        $action = (string) ($result['action'] ?? 'skipped');
        ++$stats['published'];
        $pctDone = 12 + (int) floor(($itemIndex / max(1, $totalPlanned)) * 82);

        if ($action === 'inserted') {
            ++$stats['added'];
        } elseif ($action === 'updated') {
            ++$stats['updated'];
        } else {
            ++$stats['skipped'];
        }

        $productId = (int) ($result['product_id'] ?? $result['existing_id'] ?? 0);
        $toVitrina = $alwaysVitrina || !empty($epiesa['vitrina']);
        if ($productId > 0 && $toVitrina) {
            import_consumable_set_vitrina($pdo, $productId);
            ++$stats['vitrina'];
            ++$stats['vitrina_candidates'];
            $log('Vitrină homepage: ' . mb_substr((string) ($staging['pName'] ?? ''), 0, 60, 'UTF-8'), 'ok', min(99, $pctDone));
        }

        $log(
            ucfirst($action) . ' magazin: ' . mb_substr((string) ($staging['pName'] ?? ''), 0, 56, 'UTF-8'),
            $action === 'skipped' ? 'warn' : 'ok',
            min(99, $pctDone)
        );
    }

    $stats['queued'] = ($stats['published'] ?? 0) + ($stats['queued'] ?? 0);
    $log(
        'Finalizat: ' . $stats['published'] . ' publicate, ' . $stats['vitrina'] . ' vitrină, '
        . $stats['with_image'] . ' cu imagine.',
        'ok',
        100
    );

    return $stats;
}

/** @param array<string, mixed> $product @return array<string, mixed> */
function import_consumable_build_staging_row(array $product, AdaosComercialService $markupService): array
{
    $images = json_decode((string) ($product['pImages'] ?? '[]'), true);
    if (!is_array($images)) {
        $images = [];
    }

    $row = [
        'pName' => trim((string) ($product['pName'] ?? '')),
        'pCode' => trim((string) ($product['pCode'] ?? '')),
        'pBrand' => trim((string) ($product['pBrand'] ?? '')),
        'pMarca' => trim((string) ($product['pMarca'] ?? '')),
        'pModel' => trim((string) ($product['pModel'] ?? '')),
        'pMotorizare' => trim((string) ($product['pMotorizare'] ?? '')),
        'pCar' => trim((string) ($product['pCar'] ?? ($product['pBrand'] ?? ''))),
        'pBasePrice' => trim((string) ($product['pBasePrice'] ?? ($product['pPrice'] ?? ''))),
        'pStock' => trim((string) ($product['pStock'] ?? '')),
        'pCategory' => trim((string) ($product['pCategory'] ?? '')),
        'pSubcategory' => trim((string) ($product['pSubcategory'] ?? '')),
        'pCompatibilitati' => trim((string) ($product['pCompatibilitati'] ?? '')),
        'pOem' => trim((string) ($product['pOem'] ?? '')),
        'pSupplier' => trim((string) ($product['pSupplier'] ?? '')),
        'pState' => 'Nou',
        'pCity' => '',
        'pNote' => trim((string) ($product['pNote'] ?? '')),
        'pImages' => json_encode($images, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'pImageSource' => (string) ($product['pImageSource'] ?? 'csv'),
        'pShipping' => trim((string) ($product['pStock'] ?? '')),
        'pWarranty' => '',
        'pReturn' => '',
        'pWhatsapp' => '',
    ];

    $pricing = $markupService->applyAutomaticMarkup($row);
    $row = array_merge($row, $pricing['data']);

    $sourcePayload = json_decode((string) ($product['raw_json'] ?? '{}'), true);
    if (!is_array($sourcePayload)) {
        $sourcePayload = [];
    }

    $row['raw_json'] = json_encode(array_merge($sourcePayload, [
        'schema' => 'product_import_v2',
        'import_mode' => 'consumable_scan',
        'consumable_categories' => $product['__consumable_categories'] ?? import_consumable_detect_categories($product),
    ]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $row['status'] = 'pending';

    return $row;
}

/**
 * @param array<string, mixed> $row
 * @param callable(string,string):void|null $logger
 * @return array{checked:bool,found:bool,vitrina:bool}
 */
function import_consumable_enrich_epiesa(array &$row, ?callable $logger = null): array
{
    $result = ['checked' => false, 'found' => false, 'vitrina' => false];
    $log = static function (string $msg, string $level = 'info') use ($logger): void {
        if ($logger !== null) {
            $logger($msg, $level);
        }
    };

    $query = trim((string) ($row['pName'] ?? ''));
    if ($query === '') {
        return $result;
    }

    $result['checked'] = true;
    $log('Verific ePiesa: ' . mb_substr($query, 0, 64, 'UTF-8') . '…', 'info');

    $epiesaPath = dirname(__DIR__, 4) . '/lib/Scraper/EpiesaSearch.php';
    if (!is_file($epiesaPath)) {
        return $result;
    }

    require_once $epiesaPath;

    $match = \EpiesaSearch::findFirst($query);
    if ($match === null) {
        $log('ePiesa: produs negăsit în căutare.', 'warn');

        return $result;
    }

    $result['found'] = true;
    $result['vitrina'] = true;

    import_consumable_apply_epiesa_image($row, $match);

    $raw = json_decode((string) ($row['raw_json'] ?? '{}'), true);
    if (!is_array($raw)) {
        $raw = [];
    }
    $raw['epiesa_search'] = [
        'query' => $query,
        'title' => (string) ($match['title'] ?? ''),
        'url' => (string) ($match['url'] ?? ''),
        'price' => (string) ($match['price'] ?? ''),
        'image' => (string) ($match['image'] ?? ''),
        'image_remote' => (string) ($match['image_remote'] ?? ''),
        'mstrn_id' => \EpiesaImageCache::mstrnIdFromProduct($match),
        'found_at' => date('c'),
    ];
    $row['raw_json'] = json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if (!import_consumable_images_empty($row)) {
        $imgs = json_decode((string) ($row['pImages'] ?? '[]'), true);
        $imgPath = is_array($imgs) ? trim((string) ($imgs[0] ?? '')) : '';
        if ($imgPath !== '') {
            $log('Imagine ePiesa adăugată: ' . mb_substr($imgPath, 0, 80, 'UTF-8'), 'ok');
        }
    }

    return $result;
}

/**
 * @param array<string, mixed> $row
 * @param array<string, mixed> $match
 */
function import_consumable_apply_epiesa_image(array &$row, array $match): void
{
    if (!import_consumable_images_empty($row)) {
        return;
    }

    $image = trim((string) ($match['image'] ?? ''));
    if ($image === '') {
        return;
    }

    $row['pImages'] = json_encode([$image], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $source = trim((string) ($match['image_source'] ?? ''));
    $row['pImageSource'] = $source !== '' ? $source : 'epiesa_search';
}

/** @param array<string, mixed> $row */
function import_consumable_images_empty(array $row): bool
{
    $images = json_decode((string) ($row['pImages'] ?? '[]'), true);

    return !is_array($images) || trim((string) ($images[0] ?? '')) === '';
}

function import_consumable_set_vitrina(PDO $pdo, int $productId): void
{
    if ($productId <= 0) {
        return;
    }

    try {
        tecdoc_ensure_vitrina_column($pdo);
        $stmt = $pdo->prepare(
            'UPDATE produse SET pVitrina = 1 WHERE id = ? AND COALESCE(status, 1) <> 0'
        );
        $stmt->execute([$productId]);
    } catch (Throwable) {
        // coloană opțională
    }
}
