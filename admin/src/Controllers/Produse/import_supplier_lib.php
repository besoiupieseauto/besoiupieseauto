<?php
declare(strict_types=1);

require_once dirname(__DIR__, 4) . '/system/product-code-normalize.php';

function import_supplier_priority_map_defaults(): array
{
    return [
        'AUTOTOTAL' => 1,
        'AUTONET' => 2,
        'MATEROM' => 3,
        'AUTOPARTNER' => 4,
        'ELIT' => 5,
        'INTERCARS' => 6,
    ];
}

/** @var array<string, int>|null */
$GLOBALS['__import_price_logic_priority_map'] = null;

function import_price_logic_reset_cache(): void
{
    $GLOBALS['__import_price_logic_priority_map'] = null;
    $GLOBALS['__import_price_logic_service'] = null;
}

function import_price_logic_service(): ?\Evasystem\Controllers\Furnizori\PriceFormationLogicService
{
    $cached = $GLOBALS['__import_price_logic_service'] ?? null;
    if ($cached instanceof \Evasystem\Controllers\Furnizori\PriceFormationLogicService) {
        return $cached;
    }

    $serviceClass = \Evasystem\Controllers\Furnizori\PriceFormationLogicService::class;
    if (!class_exists($serviceClass)) {
        $servicePath = dirname(__DIR__) . '/Furnizori/PriceFormationLogicService.php';
        if (is_file($servicePath)) {
            require_once $servicePath;
        }
    }

    if (!class_exists($serviceClass)) {
        return null;
    }

    $GLOBALS['__import_price_logic_service'] = new $serviceClass();

    return $GLOBALS['__import_price_logic_service'];
}

function import_supplier_priority_map(): array
{
    if (is_array($GLOBALS['__import_price_logic_priority_map'] ?? null)) {
        return $GLOBALS['__import_price_logic_priority_map'];
    }

    static $loading = false;
    if ($loading) {
        return import_supplier_priority_map_defaults();
    }

    $service = import_price_logic_service();
    if ($service !== null) {
        try {
            $loading = true;
            $map = $service->getPriorityMap();
            $loading = false;
            if ($map !== []) {
                $GLOBALS['__import_price_logic_priority_map'] = $map;

                return $map;
            }
        } catch (Throwable $e) {
            $loading = false;
            // fallback la valorile implicite
        }
    }

    $GLOBALS['__import_price_logic_priority_map'] = import_supplier_priority_map_defaults();

    return $GLOBALS['__import_price_logic_priority_map'];
}

function import_supplier_priority_rank(string $supplier, array $priorityMap): int
{
    $key = strtoupper(trim(str_replace([' ', '-'], '', $supplier)));
    if ($key === 'AUTOTOTAL' || $key === 'AUTOTOT') {
        return (int) ($priorityMap['AUTOTOTAL'] ?? 99);
    }

    return (int) ($priorityMap[$key] ?? 99);
}

/** @param array{price?:float,supplier?:string,brand?:string}|null $existing */
function import_price_index_should_replace(?array $existing, float $newPrice, string $newSupplier, array $priorityMap, ?string $newBrand = null): bool
{
    if (import_furnizor_is_blocked($newSupplier)) {
        return false;
    }

    $service = import_price_logic_service();
    if ($service !== null) {
        try {
            if ($service->isSupplierOmitted($newSupplier)) {
                return false;
            }

            return $service->shouldReplacePrice($existing, $newPrice, $newSupplier, $newBrand);
        } catch (Throwable $e) {
            // fallback la logica veche
        }
    }

    if ($existing === null) {
        return true;
    }

    $existingPrice = (float) ($existing['price'] ?? 0);
    if ($newPrice + 0.0001 < $existingPrice) {
        return true;
    }

    if (abs($newPrice - $existingPrice) <= 0.0001) {
        return import_supplier_priority_rank($newSupplier, $priorityMap)
            < import_supplier_priority_rank((string) ($existing['supplier'] ?? ''), $priorityMap);
    }

    return false;
}

function import_supplier_row_brand(array $row, string $supplierType): string
{
    return match ($supplierType) {
        'AUTOTOTAL' => import_normalize_supplier_brand(import_row_value($row, ['sup_brand', 'sup brand'])),
        'AUTONET' => import_normalize_supplier_brand(import_row_value($row, ['producator', 'producător'])),
        'MATEROM' => import_normalize_supplier_brand(import_row_value($row, ['brand'])),
        'ELIT' => import_normalize_supplier_brand(import_row_value($row, ['lkq brand name', 'lkq brand name'])),
        'INTERCARS' => import_normalize_supplier_brand(import_row_value($row, ['manufacturer name', 'manufacturer name'])),
        'AUTOPARTNER' => import_normalize_supplier_brand(import_row_value($row, ['brand code tecdoc', 'brand code auto partner'])),
        default => '',
    };
}

function import_supplier_row_passes_logic_filters(array $row, string $supplierType): bool
{
    if (import_furnizor_is_blocked($supplierType)) {
        return false;
    }

    $service = import_price_logic_service();
    if ($service === null) {
        return true;
    }

    try {
        if ($service->isSupplierOmitted($supplierType)) {
            return false;
        }

        $brand = import_supplier_row_brand($row, $supplierType);
        if ($brand !== '' && $service->isBrandIgnoredForSupplier($supplierType, $brand)) {
            return false;
        }

        $config = $service->getConfig();
        $stockStatus = import_supplier_stock_status($row);
        if (!$service->passesStockStatus($stockStatus, (string) ($config['stock_verify'] ?? 'skip_zero'))) {
            return false;
        }
    } catch (Throwable $e) {
        return true;
    }

    return true;
}

/** @return array<string, array{name:string,priority:int,vat_rule:string,price_columns:string,connection_type:string}> */
function import_supplier_definitions(): array
{
    $priorities = import_supplier_priority_map();

    return [
        'AUTOTOTAL' => [
            'name' => 'Autototal',
            'priority' => $priorities['AUTOTOTAL'],
            'vat_rule' => 'net_plus_tva',
            'price_columns' => 'Pret',
            'connection_type' => 'ftp',
        ],
        'AUTONET' => [
            'name' => 'Autonet',
            'priority' => $priorities['AUTONET'],
            'vat_rule' => 'net_plus_tva',
            'price_columns' => 'Pret unitar',
            'connection_type' => 'ftp',
        ],
        'MATEROM' => [
            'name' => 'Materom',
            'priority' => $priorities['MATEROM'],
            'vat_rule' => 'net_plus_tva',
            'price_columns' => 'Pret',
            'connection_type' => 'ftp',
        ],
        'ELIT' => [
            'name' => 'Elit (LKQ)',
            'priority' => $priorities['ELIT'],
            'vat_rule' => 'net_plus_tva',
            'price_columns' => 'Net price',
            'connection_type' => 'ftp',
        ],
        'INTERCARS' => [
            'name' => 'Inter Cars',
            'priority' => $priorities['INTERCARS'],
            'vat_rule' => 'price_final',
            'price_columns' => 'Unit price',
            'connection_type' => 'api',
        ],
        'AUTOPARTNER' => [
            'name' => 'Auto Partner',
            'priority' => $priorities['AUTOPARTNER'],
            'vat_rule' => 'net_plus_tva',
            'price_columns' => 'Purchase price',
            'connection_type' => 'api',
        ],
    ];
}

function import_supplier_vat_rule_label(string $rule): string
{
    return match ($rule) {
        'price_final' => 'Pret final (fara TVA suplimentar)',
        'net_plus_tva' => 'Pret net + 21% TVA',
        default => $rule,
    };
}

/** @return array<string, array<string, mixed>> */
function import_furnizori_load_secrets(): array
{
    $localPath = dirname(__DIR__, 3) . '/config/furnizori_connections.local.php';
    if (!is_file($localPath)) {
        return [];
    }

    $secrets = require $localPath;

    return is_array($secrets) ? $secrets : [];
}

/** @var array<string, array<string, mixed>>|null */
$GLOBALS['__import_furnizori_catalog_cache'] = null;

function import_furnizori_catalog_reset_cache(): void
{
    $GLOBALS['__import_furnizori_catalog_cache'] = null;
}

function import_furnizori_normalize_code(string $code): string
{
    $code = trim($code);

    return function_exists('mb_strtoupper') ? mb_strtoupper($code, 'UTF-8') : strtoupper($code);
}

function import_furnizori_search_slug(string $code): string
{
    return strtolower(import_furnizori_normalize_code($code));
}

/** @return list<string> */
function import_furnizori_search_supported_slugs(): array
{
    static $known = ['materom', 'elit', 'autopartner', 'autonet', 'autototal'];

    return $known;
}

/** @return list<array{key:string,label:string,active:bool}> */
function import_furnizori_destinations_for_code(string $code): array
{
    $code = import_furnizori_normalize_code($code);
    if ($code === '') {
        return [];
    }

    $destinations = [
        ['key' => 'piese_auto', 'label' => 'Generator Piese Auto', 'active' => true],
        ['key' => 'baselinker', 'label' => 'Feed BaseLinker', 'active' => true],
    ];

    $slug = import_furnizori_search_slug($code);
    if (in_array($slug, import_furnizori_search_supported_slugs(), true)) {
        $destinations[] = ['key' => 'supplier_search', 'label' => 'Supplier Search', 'active' => true];
    }

    return $destinations;
}

/** @param array<string, mixed> $row @param array<string, mixed> $seed @param array<string, int> $priorities @return array<string, mixed> */
function import_furnizori_row_to_catalog_entry(array $row, array $seed, array $priorities): array
{
    $code = import_furnizori_normalize_code((string) ($row['code'] ?? ''));
    $entry = array_merge($seed, [
        'name' => (string) ($row['name'] ?? $seed['name'] ?? $code),
        'code' => $code,
        'status' => (string) ($row['status'] ?? $seed['status'] ?? 'active'),
        'randomn_id' => (int) ($row['randomn_id'] ?? 0),
        'price_markup_type' => (string) ($row['price_markup_type'] ?? $seed['price_markup_type'] ?? 'percentage'),
        'price_markup_value' => (float) ($row['price_markup_value'] ?? $seed['price_markup_value'] ?? 0),
        'connection_type' => (string) ($row['connection_type'] ?? $seed['connection_type'] ?? 'ftp'),
        'scan_interval_minutes' => (int) ($row['scan_interval_minutes'] ?? $seed['scan_interval_minutes'] ?? 360),
        'priority' => $priorities[$code] ?? (int) ($seed['priority'] ?? 99),
        'vat_rule' => (string) ($seed['vat_rule'] ?? 'net_plus_tva'),
        'price_columns' => (string) ($seed['price_columns'] ?? ''),
        'notes' => (string) ($row['notes'] ?? $seed['notes'] ?? ''),
    ]);

    foreach ([
        'conn_host', 'conn_port', 'conn_username', 'conn_password', 'conn_remote_path',
        'conn_passive', 'conn_email', 'conn_email_inbox', 'conn_imap_host', 'conn_imap_port',
        'conn_email_password', 'api_base_url', 'api_token',
        'stock_zero_mode', 'scan_include_zero_stock', 'scan_skip_unavailable',
        'scan_schedule_mode', 'scan_schedule_time', 'scan_window_start', 'scan_window_end', 'scan_auto_enabled',
    ] as $field) {
        if (array_key_exists($field, $row) && $row[$field] !== null && trim((string) $row[$field]) !== '') {
            $entry[$field] = $row[$field];
        }
    }

    return $entry;
}

/** Sablon initial — folosit doar la seed BD, nu ca filtru de disponibilitate. */
function import_furnizori_catalog_seeds(): array
{
    $priorities = import_supplier_priority_map();

    return [
        'AUTOTOTAL' => [
            'name' => 'Autototal',
            'code' => 'AUTOTOTAL',
            'status' => 'active',
            'price_markup_type' => 'percentage',
            'price_markup_value' => 0,
            'priority' => $priorities['AUTOTOTAL'],
            'vat_rule' => 'net_plus_tva',
            'price_columns' => 'Pret',
            'connection_type' => 'email',
            'scan_interval_minutes' => 1440,
            'conn_email_inbox' => 'autototal@besoiupieseauto.ro',
            'notes' => 'Primeste fisierul pe email la 1-2 zile. Transmite furnizorului adresa inbox-ului dedicat unde doresti sa ajunga exportul.',
        ],
        'AUTONET' => [
            'name' => 'Autonet',
            'code' => 'AUTONET',
            'status' => 'active',
            'price_markup_type' => 'percentage',
            'price_markup_value' => 0,
            'priority' => $priorities['AUTONET'],
            'vat_rule' => 'net_plus_tva',
            'price_columns' => 'Pret unitar',
            'connection_type' => 'ftp',
            'scan_interval_minutes' => 360,
            'conn_host' => 'caietcomenzi.ro',
            'conn_port' => 21,
            'conn_username' => 'feedautonet@caietcomenzi.ro',
            'conn_passive' => 1,
            'conn_remote_path' => '',
            'notes' => 'FTP Autonet / caietcomenzi.',
        ],
        'MATEROM' => [
            'name' => 'Materom',
            'code' => 'MATEROM',
            'status' => 'active',
            'price_markup_type' => 'percentage',
            'price_markup_value' => 0,
            'priority' => $priorities['MATEROM'],
            'vat_rule' => 'net_plus_tva',
            'price_columns' => 'Pret',
            'connection_type' => 'ftp',
            'scan_interval_minutes' => 720,
            'conn_host' => '',
            'conn_port' => 21,
            'conn_username' => '',
            'conn_passive' => 1,
            'conn_remote_path' => '/materom',
            'notes' => 'Materom incarca fisierele pe FTP-ul nostru. Configureaza host-ul public si transmite-l furnizorului pentru primire export.',
        ],
        'ELIT' => [
            'name' => 'Elit (LKQ)',
            'code' => 'ELIT',
            'status' => 'active',
            'priority' => $priorities['ELIT'],
            'vat_rule' => 'net_plus_tva',
            'price_columns' => 'Net price',
            'connection_type' => 'sftp',
            'scan_interval_minutes' => 360,
            'conn_host' => 'sftp.lkqcee.com',
            'conn_port' => 22,
            'conn_username' => 'ro1000056',
            'conn_passive' => 0,
            'conn_remote_path' => '',
            'notes' => 'SFTP Elit / LKQ.',
        ],
        'AUTOPARTNER' => [
            'name' => 'Auto Partner',
            'code' => 'AUTOPARTNER',
            'status' => 'active',
            'priority' => $priorities['AUTOPARTNER'],
            'vat_rule' => 'net_plus_tva',
            'price_columns' => 'Purchase price',
            'connection_type' => 'api',
            'scan_interval_minutes' => 360,
            'api_base_url' => 'https://customerapi.autopartner.dev/CustomerAPI.svc/rest',
            'conn_host' => 'ftp.autopartner.dev',
            'conn_port' => 21,
            'conn_username' => '3208129',
            'conn_passive' => 1,
            'conn_remote_path' => '',
            'notes' => 'Auto Partner: API Customer (recomandat). Pentru CSV mare foloseste sync agent local.',
        ],
    ];
}

/**
 * Catalog central furnizori — sursa de adevar: cartela furnizor (tabel furnizori).
 * Alimenteaza: import/generator Piese Auto, feed BaseLinker (produse), Supplier Search.
 */
function import_furnizori_catalog(): array
{
    if (is_array($GLOBALS['__import_furnizori_catalog_cache'] ?? null)) {
        return $GLOBALS['__import_furnizori_catalog_cache'];
    }

    $priorities = import_supplier_priority_map();
    $secrets = import_furnizori_load_secrets();
    $seeds = import_furnizori_catalog_seeds();
    $catalog = [];

    try {
        $modelPath = dirname(__DIR__) . '/Core/Furnizori/FurnizoriModel.php';
        if (is_file($modelPath)) {
            require_once $modelPath;
            $model = new \Evasystem\Core\Furnizori\FurnizoriModel();
            foreach ($model->findAll() as $row) {
                $code = import_furnizori_normalize_code((string) ($row['code'] ?? ''));
                $status = strtolower(trim((string) ($row['status'] ?? 'active')));
                if ($code === '' || $status === 'deleted') {
                    continue;
                }

                $seed = is_array($seeds[$code] ?? null) ? $seeds[$code] : [];
                $catalog[$code] = import_furnizori_row_to_catalog_entry($row, $seed, $priorities);
            }
        }
    } catch (Throwable) {
        $catalog = [];
    }

    foreach ($seeds as $code => $seed) {
        if (!isset($catalog[$code])) {
            $catalog[$code] = array_merge($seed, [
                'priority' => $priorities[$code] ?? (int) ($seed['priority'] ?? 99),
            ]);
        }
    }

    foreach ($secrets as $code => $fields) {
        if (!is_array($fields) || !isset($catalog[$code])) {
            continue;
        }
        foreach ($fields as $key => $value) {
            if ($value !== null && $value !== '') {
                $catalog[$code][$key] = $value;
            }
        }
    }

    $GLOBALS['__import_furnizori_catalog_cache'] = $catalog;

    return $catalog;
}

/** Completeaza credentialele lipsa din furnizori_connections.local.php (dev). */
function import_furnizori_resolve_credentials(array $furnizor): array
{
    $code = function_exists('mb_strtoupper')
        ? mb_strtoupper(trim((string) ($furnizor['code'] ?? '')), 'UTF-8')
        : strtoupper(trim((string) ($furnizor['code'] ?? '')));

    if ($code === '') {
        return $furnizor;
    }

    $secrets = import_furnizori_load_secrets();
    if (!isset($secrets[$code]) || !is_array($secrets[$code])) {
        return $furnizor;
    }

    foreach ($secrets[$code] as $key => $value) {
        if ($value === null || $value === '') {
            continue;
        }
        if (!array_key_exists($key, $furnizor) || trim((string) ($furnizor[$key] ?? '')) === '') {
            $furnizor[$key] = $value;
        }
    }

    return $furnizor;
}

/** @return array<int, string> */
function import_furnizori_catalog_codes(): array
{
    return array_keys(import_furnizori_catalog());
}

function import_furnizori_reset_blocked_cache(): void
{
    unset($GLOBALS['__import_furnizori_blocked_codes']);
}

/** @return array<int, string> */
function import_furnizori_blocked_codes(): array
{
    if (isset($GLOBALS['__import_furnizori_blocked_codes']) && is_array($GLOBALS['__import_furnizori_blocked_codes'])) {
        return $GLOBALS['__import_furnizori_blocked_codes'];
    }

    $blocked = [];
    try {
        $modelPath = dirname(__DIR__) . '/Core/Furnizori/FurnizoriModel.php';
        if (is_file($modelPath)) {
            require_once $modelPath;
            $model = new \Evasystem\Core\Furnizori\FurnizoriModel();
            foreach ($model->findAll() as $row) {
                if (($row['status'] ?? '') !== 'blocked') {
                    continue;
                }
                $code = function_exists('mb_strtoupper')
                    ? mb_strtoupper(trim((string) ($row['code'] ?? '')), 'UTF-8')
                    : strtoupper(trim((string) ($row['code'] ?? '')));
                if ($code !== '') {
                    $blocked[] = $code;
                }
            }
        }
    } catch (Throwable $e) {
        $blocked = [];
    }

    $GLOBALS['__import_furnizori_blocked_codes'] = $blocked;

    return $blocked;
}

function import_furnizor_is_blocked(string $supplierCode): bool
{
    $code = function_exists('mb_strtoupper')
        ? mb_strtoupper(trim(str_replace([' ', '-'], '', $supplierCode)), 'UTF-8')
        : strtoupper(trim(str_replace([' ', '-'], '', $supplierCode)));
    if ($code === 'AUTOTOT') {
        $code = 'AUTOTOTAL';
    }

    return $code !== '' && in_array($code, import_furnizori_blocked_codes(), true);
}

function import_normalize_product_code(string $value): string
{
    return besoiu_normalize_product_code($value);
}

function import_normalize_supplier_brand(string $value): string
{
    $value = trim($value);
    $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

    return function_exists('mb_strtoupper')
        ? mb_strtoupper($value, 'UTF-8')
        : strtoupper($value);
}

/**
 * Coduri producător TecDoc / Autonet / Autopartner → denumire brand (ex. 0030 → BOSCH).
 *
 * @return array<string, string>
 */
function import_supplier_tecdoc_brand_code_map(): array
{
    return [
        '30' => 'BOSCH',
        '0030' => 'BOSCH',
        '21' => 'VALEO',
        '0021' => 'VALEO',
        '35' => 'CONTINENTAL',
        '0035' => 'CONTINENTAL',
        '32' => 'BERU',
        '0032' => 'BERU',
        '41' => 'HELLA',
        '0041' => 'HELLA',
        '2' => 'BOSCH',
        '0002' => 'BOSCH',
        '101' => 'FEBI BILSTEIN',
        '0101' => 'FEBI BILSTEIN',
        '127' => 'TRW',
        '0127' => 'TRW',
        '144' => 'SACHS',
        '0144' => 'SACHS',
        '155' => 'LUK',
        '0155' => 'LUK',
        '161' => 'MANN-FILTER',
        '0161' => 'MANN-FILTER',
        '204' => 'NGK',
        '0204' => 'NGK',
        '287' => 'MAHLE',
        '0287' => 'MAHLE',
        '338' => 'GATES',
        '0338' => 'GATES',
        '401' => 'SKF',
        '0401' => 'SKF',
        '433' => 'FAG',
        '0433' => 'FAG',
        '442' => 'INA',
        '0442' => 'INA',
    ];
}

/** Nume brand canonic pentru BD / match (BOSCH, nu 0030). */
function import_resolve_brand_canonical_name(string $brand, string $articleCode = ''): string
{
    $brand = trim($brand);
    if ($brand === '') {
        return '';
    }

    $compact = preg_replace('/\s+/u', '', $brand) ?? $brand;
    if ($compact !== '' && preg_match('/[A-Z]/i', $compact) && !preg_match('/^\d{2,5}$/', $compact)) {
        return import_normalize_supplier_brand($brand);
    }

    $digits = preg_replace('/\D+/', '', $compact) ?? '';
    $map = import_supplier_tecdoc_brand_code_map();
    foreach (array_unique(array_filter([$digits, ltrim($digits, '0'), str_pad($digits, 4, '0', STR_PAD_LEFT)])) as $key) {
        if ($key !== '' && isset($map[$key])) {
            return $map[$key];
        }
    }

    if (preg_match('/^\d{2,5}$/', $compact)) {
        return '';
    }

    return import_normalize_supplier_brand($brand);
}

/** Denumire afișată în titlu (Bosch, nu 0030). */
function import_resolve_brand_display_name(string $brand, string $articleCode = ''): string
{
    $canonical = import_resolve_brand_canonical_name($brand, $articleCode);
    if ($canonical === '') {
        return '';
    }

    return function_exists('import_base_brand_title_case')
        ? import_base_brand_title_case($canonical)
        : $canonical;
}

/** Formatare cod articol pentru titlu (ex. Bosch 0986424268 → 0 986 424 268). */
function import_format_article_code_display(string $code, string $brand = ''): string
{
    $code = trim($code);
    if ($code === '') {
        return '';
    }

    $digits = preg_replace('/\D+/', '', $code) ?? '';
    $canonical = import_resolve_brand_canonical_name($brand, $code);
    $looksBosch = $canonical === 'BOSCH' || preg_match('/^0\d{9}$/', $digits);

    if ($looksBosch && strlen($digits) === 10) {
        return $digits[0] . ' ' . substr($digits, 1, 3) . ' ' . substr($digits, 4, 3) . ' ' . substr($digits, 7, 3);
    }

    return $code;
}

/** Verifică dacă brandul din index furnizor corespunde brandului piesei (ex. FAG). */
function import_supplier_price_match_product_brand(string $productBrand, string $supplierBrand): bool
{
    $productBrand = import_normalize_supplier_brand($productBrand);
    $supplierBrand = import_normalize_supplier_brand($supplierBrand);

    if ($productBrand === '' || $supplierBrand === '') {
        return false;
    }

    $service = import_price_logic_service();
    if ($service !== null) {
        try {
            $config = $service->getConfig();

            return $service->passesBrandVerification(
                $supplierBrand,
                $productBrand,
                (string) ($config['brand_verify'] ?? 'exact')
            );
        } catch (Throwable) {
            // fallback
        }
    }

    return $productBrand === $supplierBrand
        || str_contains($supplierBrand, $productBrand)
        || str_contains($productBrand, $supplierBrand);
}

function import_parse_supplier_price($value): ?float
{
    if ($value === null || $value === '') {
        return null;
    }

    $normalized = normalize_amount_text((string)$value);
    if ($normalized === '' || !is_numeric($normalized)) {
        return null;
    }

    $num = (float)$normalized;

    return $num > 0 ? $num : null;
}

/** Furnizori cu return 10% lunar la target → adaos compensator pe feed = 0%. */
function import_supplier_return10_codes(): array
{
    return ['AUTOTOTAL', 'AUTONET', 'MATEROM'];
}

/** @return array<int, float> */
function import_supplier_feed_markup_presets(): array
{
    return [0.0, 5.0, 10.0];
}

/** @return array<string, float> */
function import_supplier_feed_markup_defaults(): array
{
    $defaults = [
        'ELIT' => 5.0,
        'AUTOPARTNER' => 10.0,
    ];
    foreach (import_supplier_return10_codes() as $code) {
        $defaults[$code] = 0.0;
    }

    return $defaults;
}

function import_supplier_normalize_code(string $supplier): string
{
    $code = function_exists('mb_strtoupper')
        ? mb_strtoupper(trim(str_replace([' ', '-'], '', $supplier)), 'UTF-8')
        : strtoupper(trim(str_replace([' ', '-'], '', $supplier)));

    if ($code === 'AUTOTOT') {
        return 'AUTOTOTAL';
    }

    return $code;
}

/** @return array<string, float> */
function import_supplier_feed_markup_map(): array
{
    if (is_array($GLOBALS['__import_supplier_feed_markup_map'] ?? null)) {
        return $GLOBALS['__import_supplier_feed_markup_map'];
    }

    $cache = import_supplier_feed_markup_defaults();

    if (class_exists(\Config\Database::class)) {
        try {
            $pdo = \Config\Database::getDB();
            $stmt = $pdo->query(
                "SELECT UPPER(TRIM(code)) AS code, price_markup_type, price_markup_value
                 FROM furnizori
                 WHERE code IS NOT NULL AND TRIM(code) <> ''"
            );
            if ($stmt !== false) {
                while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                    $code = import_supplier_normalize_code((string) ($row['code'] ?? ''));
                    if ($code === '') {
                        continue;
                    }
                    if ((string) ($row['price_markup_type'] ?? 'percentage') !== 'percentage') {
                        continue;
                    }
                    $cache[$code] = max(0.0, (float) ($row['price_markup_value'] ?? 0));
                }
            }
        } catch (\Throwable $e) {
            // fallback la valorile implicite
        }
    }

    $GLOBALS['__import_supplier_feed_markup_map'] = $cache;

    return $cache;
}

function import_supplier_feed_markup_reset_cache(): void
{
    unset($GLOBALS['__import_supplier_feed_markup_map']);
}

function import_supplier_reverse_feed_csv_price(float $netAfterMarkup, float $markupPercent): float
{
    if ($netAfterMarkup <= 0) {
        return 0.0;
    }

    $divisor = 1 + (max(0.0, $markupPercent) / 100);
    if ($divisor <= 0.0001) {
        return $netAfterMarkup;
    }

    return $netAfterMarkup / $divisor;
}

/**
 * Recalculează pBasePrice și pPrice pentru produsele unui furnizor după schimbarea adaosului feed %.
 *
 * @return array{updated:int,skipped:int}
 */
function import_supplier_reprice_products_after_markup_change(
    string $supplierCode,
    float $oldMarkupPercent,
    float $newMarkupPercent
): array {
    $supplierCode = import_supplier_normalize_code($supplierCode);
    if ($supplierCode === '' || abs($oldMarkupPercent - $newMarkupPercent) < 0.0001) {
        return ['updated' => 0, 'skipped' => 0];
    }

    if (!class_exists(\Config\Database::class)) {
        return ['updated' => 0, 'skipped' => 0];
    }

    $markupService = null;
    if (class_exists(\Evasystem\Controllers\AdaosComercial\AdaosComercialService::class)) {
        try {
            $markupService = new \Evasystem\Controllers\AdaosComercial\AdaosComercialService();
        } catch (\Throwable $e) {
            $markupService = null;
        }
    }

    $pdo = \Config\Database::getDB();
    $stmt = $pdo->prepare(
        'SELECT id, randomn_id, pBasePrice, pPrice, pSupplier, pMarkupRuleId, pMarkupRuleName
         FROM produse
         WHERE UPPER(TRIM(pSupplier)) = :code'
    );
    $stmt->execute([':code' => $supplierCode]);

    $updated = 0;
    $skipped = 0;

    while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
        $basePrice = (float) ($row['pBasePrice'] ?? 0);
        if ($basePrice <= 0) {
            $skipped++;
            continue;
        }

        $supplier = import_supplier_normalize_code((string) ($row['pSupplier'] ?? $supplierCode));
        $csvPrice = import_supplier_reverse_feed_csv_price($basePrice, $oldMarkupPercent);
        $newNet = import_supplier_apply_feed_markup($csvPrice, $supplier);
        if ($newNet === null || $newNet <= 0) {
            $skipped++;
            continue;
        }

        $newBase = import_supplier_net_to_base($newNet, $supplier);
        $product = $row;
        $product['pBasePrice'] = rtrim(rtrim(number_format($newBase, 2, '.', ''), '0'), '.');

        $updateFields = [
            'pBasePrice' => $product['pBasePrice'],
        ];

        if ($markupService !== null) {
            $pricing = $markupService->applyAutomaticMarkup($product, $product);
            foreach (['pPrice', 'pMarkupRuleId', 'pMarkupRuleName', 'pMarkupAppliedAt'] as $field) {
                if (array_key_exists($field, $pricing['data'])) {
                    $updateFields[$field] = $pricing['data'][$field];
                }
            }
        } else {
            $updateFields['pPrice'] = $product['pBasePrice'];
        }

        $sets = [];
        $params = [':row_id' => (int) $row['id']];
        foreach ($updateFields as $field => $value) {
            $sets[] = '`' . $field . '` = :' . $field;
            $params[':' . $field] = $value;
        }

        $updateStmt = $pdo->prepare('UPDATE produse SET ' . implode(', ', $sets) . ' WHERE id = :row_id');
        if ($updateStmt->execute($params)) {
            $updated++;
        } else {
            $skipped++;
        }
    }

    return ['updated' => $updated, 'skipped' => $skipped];
}

function import_supplier_feed_markup_percent(string $supplier): float
{
    $code = import_supplier_normalize_code($supplier);
    if ($code === '') {
        return 0.0;
    }

    $map = import_supplier_feed_markup_map();

    return (float) ($map[$code] ?? 0.0);
}

function import_supplier_apply_feed_markup(?float $feedPrice, string $supplier): ?float
{
    if ($feedPrice === null) {
        return null;
    }

    $markupPercent = import_supplier_feed_markup_percent($supplier);
    if ($markupPercent <= 0.0001) {
        return round($feedPrice, 4);
    }

    return round($feedPrice * (1 + ($markupPercent / 100)), 4);
}

function import_supplier_parse_feed_price($value, string $supplierType): ?float
{
    return import_supplier_apply_feed_markup(
        import_parse_supplier_price($value),
        $supplierType
    );
}

function import_header_key(string $key): string
{
    $key = import_strip_bom($key);
    $key = function_exists('mb_strtoupper') ? mb_strtoupper(trim($key), 'UTF-8') : strtoupper(trim($key));
    $key = preg_replace('/\s+/u', '', $key) ?? $key;
    $key = preg_replace('/[^A-Z0-9]/', '', $key) ?? $key;

    return $key;
}

function import_strip_bom(string $text): string
{
    if ($text === '') {
        return '';
    }

    if (str_starts_with($text, "\xEF\xBB\xBF")) {
        return substr($text, 3);
    }

    return $text;
}

function import_strip_bom_from_row(array $row): array
{
    if ($row === []) {
        return $row;
    }

    $firstKey = array_key_first($row);
    if ($firstKey === 0 || $firstKey === '0') {
        $row[0] = import_strip_bom((string)($row[0] ?? ''));
    }

    return $row;
}

function import_resolve_upload_file_kind(string $path, string $filename, array $fileMeta = []): string
{
    $kind = import_classify_file($path, $filename);
    if ($kind === 'tecdoc' || str_starts_with($kind, 'supplier:')) {
        return $kind;
    }

    $metaKind = trim((string)($fileMeta['file_kind'] ?? ''));
    if ($metaKind === 'tecdoc' || str_starts_with($metaKind, 'supplier:')) {
        return $metaKind;
    }

    $uploadRole = trim((string)($fileMeta['upload_role'] ?? ''));
    if ($uploadRole === 'tecdoc') {
        return 'tecdoc';
    }

    if ($uploadRole === 'supplier' || $metaKind !== '') {
        $sample = file_get_contents($path, false, null, 0, 8192) ?: '';
        $delimiter = detect_delimiter($sample);
        $handle = fopen($path, 'r');
        if ($handle) {
            $firstRow = fgetcsv($handle, 0, $delimiter);
            fclose($handle);
            if (is_array($firstRow)) {
                $firstRow = import_strip_bom_from_row($firstRow);
                $supplierType = import_detect_supplier_type($firstRow, $filename);
                if ($supplierType !== null) {
                    return 'supplier:' . $supplierType;
                }
            }
        }

        $lowerName = function_exists('mb_strtolower') ? mb_strtolower($filename, 'UTF-8') : strtolower($filename);
        foreach (['autonet' => 'AUTONET', 'elit' => 'ELIT', 'autototal' => 'AUTOTOTAL', 'materom' => 'MATEROM', 'intercars' => 'INTERCARS', 'autopartner' => 'AUTOPARTNER'] as $needle => $type) {
            if (str_contains($lowerName, $needle)) {
                return 'supplier:' . $type;
            }
        }
    }

    return $kind;
}

function import_validate_uploaded_files(array $supplierFiles, array $tecdocFiles, array $genericFiles = []): array
{
    $result = [
        'ok' => true,
        'errors' => [],
        'suppliers' => [],
        'tecdoc' => [],
        'generic' => [],
    ];

    foreach ($supplierFiles as $file) {
        $path = (string)($file['path'] ?? '');
        $filename = (string)($file['name'] ?? basename($path));
        if ($path === '' || !is_file($path)) {
            $result['ok'] = false;
            $result['errors'][] = $filename . ': fișierul nu există pe server.';
            continue;
        }

        $kind = import_resolve_upload_file_kind($path, $filename, $file);
        if (!str_starts_with($kind, 'supplier:')) {
            $result['ok'] = false;
            $result['errors'][] = $filename . ': nu este recunoscut ca listă furnizor.';
            continue;
        }

        $supplierType = substr($kind, 9);
        $sample = file_get_contents($path, false, null, 0, 4096) ?: '';
        $delimiter = detect_delimiter($sample);
        $handle = fopen($path, 'r');
        $headers = $handle ? fgetcsv($handle, 0, $delimiter) : false;
        $dataRow = $handle ? fgetcsv($handle, 0, $delimiter) : false;
        if ($handle) {
            fclose($handle);
        }

        if (!is_array($headers) || !is_array($dataRow)) {
            $result['ok'] = false;
            $result['errors'][] = $filename . ': CSV gol sau invalid.';
            continue;
        }

        $headers = import_strip_bom_from_row($headers);
        $row = import_supplier_build_sample_row_for_validation($headers, $dataRow, $supplierType);
        if ($row === null) {
            $result['ok'] = false;
            $result['errors'][] = $filename . ': antet sau coloane necompatibile cu formatul ' . $supplierType . '.';
            continue;
        }

        $entry = import_supplier_row_to_entry($row, $supplierType);
        if ($entry === null) {
            $result['ok'] = false;
            $result['errors'][] = $filename . ': antet sau coloane necompatibile cu formatul ' . $supplierType . '.';
            continue;
        }

        $result['suppliers'][] = [
            'file' => $filename,
            'kind' => $kind,
            'size' => (int)(filesize($path) ?: 0),
            'sample_code' => (string)($entry['code'] ?? ''),
            'sample_brand' => (string)($entry['brand'] ?? ''),
        ];
    }

    foreach ($tecdocFiles as $file) {
        $path = (string)($file['path'] ?? '');
        $filename = (string)($file['name'] ?? basename($path));
        if ($path === '' || !is_file($path)) {
            $result['ok'] = false;
            $result['errors'][] = $filename . ': fișierul nu există pe server.';
            continue;
        }

        $sample = file_get_contents($path, false, null, 0, 8192) ?: '';
        $delimiter = detect_delimiter($sample);
        $handle = fopen($path, 'r');
        $headers = $handle ? fgetcsv($handle, 0, $delimiter) : false;
        if ($handle) {
            fclose($handle);
        }

        if (!is_array($headers)) {
            $result['ok'] = false;
            $result['errors'][] = $filename . ': CSV TecDoc invalid (antet lipsă).';
            continue;
        }

        $headers = import_strip_bom_from_row($headers);
        if (!import_is_tecdoc_headers($headers)) {
            $result['ok'] = false;
            $result['errors'][] = $filename . ': antet TecDoc nerecunoscut (lipsesc coloane art code / car brand).';
            continue;
        }

        $brandHint = '';
        if (preg_match('/-([A-Z0-9]+)-ro\.csv$/i', $filename, $matches)) {
            $brandHint = strtoupper($matches[1]);
        }

        $result['tecdoc'][] = [
            'file' => $filename,
            'size' => (int)(filesize($path) ?: 0),
            'brand_hint' => $brandHint,
            'validated' => true,
        ];
    }

    foreach ($genericFiles as $file) {
        $path = (string)($file['path'] ?? '');
        $filename = (string)($file['name'] ?? basename($path));
        if ($path === '' || !is_file($path)) {
            $result['ok'] = false;
            $result['errors'][] = $filename . ': fișierul nu există pe server.';
            continue;
        }

        $result['generic'][] = [
            'file' => $filename,
            'size' => (int)(filesize($path) ?: 0),
        ];
    }

    return $result;
}

function import_row_value(array $row, array $keys): string
{
    foreach ($keys as $key) {
        $normalizedKey = normalize_key($key);
        if (isset($row[$normalizedKey]) && trim((string)$row[$normalizedKey]) !== '') {
            return trim((string)$row[$normalizedKey]);
        }
    }

    $normalizedMap = [];
    foreach ($row as $column => $value) {
        $normalizedMap[import_header_key((string)$column)] = $value;
    }

    foreach ($keys as $key) {
        $headerKey = import_header_key($key);
        if (isset($normalizedMap[$headerKey]) && trim((string)$normalizedMap[$headerKey]) !== '') {
            return trim((string)$normalizedMap[$headerKey]);
        }
    }

    return '';
}

/** @return list<string> */
function import_supplier_stock_column_keys(): array
{
    return [
        'stock', 'stoc', 'stoc cantitativ', 'quantity', 'qty', 'cantitate',
        'available quantity', 'availablequantity', 'available', 'qty available',
        'ilosc', 'stan', 'stany', 'on hand', 'onhand', 'disp', 'disponibil',
        'stock qty', 'stockqty', 'disponible', 'magazyn',
    ];
}

function import_supplier_row_stock_raw(array $row): string
{
    return import_row_value($row, import_supplier_stock_column_keys());
}

function import_parse_supplier_stock(?string $raw): ?float
{
    if ($raw === null) {
        return null;
    }

    $raw = trim((string) $raw);
    if ($raw === '') {
        return null;
    }

    $lower = function_exists('mb_strtolower') ? mb_strtolower($raw, 'UTF-8') : strtolower($raw);
    foreach (['n/a', 'na', '-', '—', '?', 'null'] as $marker) {
        if ($lower === $marker) {
            return null;
        }
    }

    foreach (['da', 'yes', 'y', 'available', 'disponibil', 'in stock', 'pe stoc', 'full'] as $yes) {
        if ($lower === $yes || str_contains($lower, $yes)) {
            return 1.0;
        }
    }

    foreach (['nu', 'no', 'n', 'epuizat', 'out', 'indisponibil', 'unavailable'] as $no) {
        if ($lower === $no) {
            return 0.0;
        }
    }

    $normalized = normalize_amount_text($raw);
    if ($normalized === '' || !is_numeric($normalized)) {
        return null;
    }

    return (float) $normalized;
}

/** @return 'positive'|'zero'|'unknown' */
function import_supplier_stock_status(array $row): string
{
    $raw = import_supplier_row_stock_raw($row);
    if ($raw === '') {
        return 'unknown';
    }

    $parsed = import_parse_supplier_stock($raw);
    if ($parsed === null) {
        return 'unknown';
    }

    return $parsed > 0 ? 'positive' : 'zero';
}

function import_supplier_infer_oem_codes(string $brand, string $code): array
{
    $code = trim($code);
    if ($code === '') {
        return [];
    }

    $brandNorm = import_normalize_supplier_brand($brand);
    if ($brandNorm === '') {
        return [];
    }

    $brandKey = function_exists('mb_strtoupper') ? mb_strtoupper($brandNorm, 'UTF-8') : strtoupper($brandNorm);
    if (isset(import_base_allowed_car_brands()[$brandKey])) {
        return [import_base_process_oem_codes($brandNorm . ' : ' . $code)];
    }

    return [];
}

function import_supplier_format_oem_field(array $oemCodes, string $brand = '', string $code = ''): string
{
    $lines = [];
    foreach ($oemCodes as $oem) {
        $oem = trim((string)$oem);
        if ($oem !== '') {
            $lines[] = $oem;
        }
    }

    if ($lines === []) {
        foreach (import_supplier_infer_oem_codes($brand, $code) as $inferred) {
            $inferred = trim($inferred);
            if ($inferred !== '') {
                $lines[] = $inferred;
            }
        }
    }

    return implode(', ', array_values(array_unique($lines)));
}

function import_detect_supplier_type(array $headers, string $filename): ?string
{
    $fromHeaders = import_supplier_type_from_header_row($headers);
    if ($fromHeaders !== null) {
        return $fromHeaders;
    }

    $name = function_exists('mb_strtolower') ? mb_strtolower($filename, 'UTF-8') : strtolower($filename);
    if (str_contains($name, 'autototal')) return 'AUTOTOTAL';
    if (str_contains($name, 'autonet')) return 'AUTONET';
    if (str_contains($name, 'materom')) return 'MATEROM';
    if (str_contains($name, 'elit')) return 'ELIT';
    if (str_contains($name, 'intercars')) return 'INTERCARS';
    if (str_contains($name, 'autopartner')) return 'AUTOPARTNER';
    if (preg_match('/^3208129(?:_|\.|$)/i', basename($name))) return 'AUTOPARTNER';
    if (in_array(strtoupper(pathinfo($name, PATHINFO_FILENAME)), ['STANY', 'INDEKS_PARAMETR'], true)) {
        return 'AUTOPARTNER';
    }

    return null;
}

/**
 * Detectează furnizorul doar din antet (fără fallback pe nume fișier).
 * Folosit pentru a nu confunda prima linie de date cu antetul la AUTOPARTNER/AUTONET.
 */
function import_supplier_type_from_header_row(array $headers): ?string
{
    $headerSet = [];
    foreach ($headers as $header) {
        $headerSet[import_header_key((string)$header)] = true;
    }

    if (isset($headerSet['ARTARTICLENR']) && isset($headerSet['SUPBRAND'])) {
        return 'AUTOTOTAL';
    }
    if (isset($headerSet['CODARTICOL']) && isset($headerSet['PRODUCATOR'])) {
        return 'AUTONET';
    }
    if (isset($headerSet['CODNPF']) && isset($headerSet['BRAND'])) {
        return 'MATEROM';
    }
    if (isset($headerSet['SUPPLIERCATALOGNR']) && isset($headerSet['LKQBRANDNAME'])) {
        return 'ELIT';
    }
    if ((isset($headerSet['ACTIVENO']) || isset($headerSet['ACTIVENO_'])) && isset($headerSet['MANUFACTURERNAME'])) {
        return 'INTERCARS';
    }
    if (isset($headerSet['INDEXTECDOC']) && isset($headerSet['PURCHASEPRICE'])) {
        return 'AUTOPARTNER';
    }

    return null;
}

/**
 * @param array<int, string> $firstRow
 * @param array<int, string>|null $secondRow
 */
function import_supplier_build_sample_row_for_validation(array $firstRow, ?array $secondRow, string $supplierType): ?array
{
    $headerType = import_supplier_type_from_header_row($firstRow);
    if ($headerType === $supplierType && is_array($secondRow)) {
        $row = [];
        foreach (array_map('normalize_key', $firstRow) as $idx => $header) {
            $row[$header] = $secondRow[$idx] ?? '';
        }

        return $row;
    }

    if ($supplierType === 'AUTOPARTNER' && count($firstRow) >= 6) {
        return import_map_autopartner_row($firstRow);
    }

    if ($supplierType === 'AUTONET' && count($firstRow) >= 5) {
        return import_map_autonet_row($firstRow);
    }

    return null;
}

/** @return array<int, string> */
function import_supplier_file_match_tokens(string $supplierCode): array
{
    $code = strtoupper(trim($supplierCode));
    $tokens = [strtolower($code)];

    $catalog = import_furnizori_catalog();
    if (isset($catalog[$code])) {
        $username = trim((string) ($catalog[$code]['conn_username'] ?? ''));
        if ($username !== '') {
            $tokens[] = strtolower($username);
        }
    }

    return match ($code) {
        'AUTOPARTNER' => array_values(array_unique(array_merge($tokens, [
            'autopartner', '3208129', 'stany', 'indeks_parametr', 'kaucje',
        ]))),
        'AUTONET' => array_values(array_unique(array_merge($tokens, ['autonet']))),
        'ELIT' => array_values(array_unique(array_merge($tokens, ['elit', 'lkq']))),
        'AUTOTOTAL' => array_values(array_unique(array_merge($tokens, ['autototal']))),
        'MATEROM' => array_values(array_unique(array_merge($tokens, ['materom']))),
        default => $tokens,
    };
}

function import_supplier_file_matches_code(string $supplierCode, string $filename, string $fileKind = ''): bool
{
    $supplierCode = strtoupper(trim($supplierCode));
    if ($supplierCode === '') {
        return false;
    }

    $kindUpper = strtoupper(trim($fileKind));
    if ($kindUpper !== '' && str_contains($kindUpper, $supplierCode)) {
        return true;
    }
    if (preg_match('/supplier:\s*' . preg_quote($supplierCode, '/') . '\b/i', $fileKind)) {
        return true;
    }

    $haystack = strtolower($filename);
    if ($supplierCode === 'AUTONET' && str_contains($haystack, 'autopartner')) {
        return false;
    }
    if ($supplierCode === 'AUTOPARTNER' && str_contains($haystack, 'autonet') && !str_contains($haystack, 'autopartner')) {
        return false;
    }

    foreach (import_supplier_file_match_tokens($supplierCode) as $token) {
        if ($token === '') {
            continue;
        }
        if (import_supplier_filename_contains_token($haystack, $token)) {
            return true;
        }
    }

    return false;
}

function import_supplier_filename_contains_token(string $haystack, string $token): bool
{
    $token = strtolower(trim($token));
    if ($token === '') {
        return false;
    }

    $pattern = '/(?:^|[^a-z0-9])' . preg_quote($token, '/') . '(?:[^a-z0-9]|$)/';

    return (bool) preg_match($pattern, $haystack);
}

function import_is_tecdoc_headers(array $headers): bool
{
    $normalized = array_map(static fn($header) => normalize_key((string)$header), $headers);
    $headerKeys = array_map(static fn($header) => import_header_key((string)$header), $headers);

    if (in_array('art code 1', $normalized, true)
        && (in_array('art name', $normalized, true) || in_array('car brand', $normalized, true))) {
        return true;
    }

    $hasCode = in_array('ARTCODE1', $headerKeys, true) || in_array('ARTCODE2', $headerKeys, true);
    $hasMeta = in_array('ARTNAME', $headerKeys, true)
        || in_array('ARTBRAND', $headerKeys, true)
        || in_array('CARBRAND', $headerKeys, true)
        || in_array('CARMODEL', $headerKeys, true)
        || in_array('TTCARTID', $headerKeys, true);

    return $hasCode && $hasMeta;
}

function import_is_tecdoc_filename(string $filename): bool
{
    $name = function_exists('mb_strtolower') ? mb_strtolower($filename, 'UTF-8') : strtolower($filename);

    foreach ([
        'tableusecarsforparts',
        'universal-csv-data',
        'tecdoc',
        'art_code_1',
        'get-table',
    ] as $needle) {
        if (str_contains($name, $needle)) {
            return true;
        }
    }

    return false;
}

function import_resolve_upload_role(array $meta, string $path, string $filename): string
{
    $uploadRole = trim((string)($meta['upload_role'] ?? ''));
    if ($uploadRole === 'tecdoc' || $uploadRole === 'supplier') {
        return $uploadRole;
    }

    $kind = trim((string)($meta['file_kind'] ?? ''));
    if ($kind === '') {
        $kind = import_classify_file($path, $filename);
    }

    if ($kind === 'tecdoc' || import_is_tecdoc_filename($filename)) {
        return 'tecdoc';
    }

    if (str_starts_with($kind, 'supplier:')) {
        return 'supplier';
    }

    return '';
}

function import_classify_file(string $path, string $filename): string
{
    if (!is_file($path)) {
        return 'unknown';
    }

    $sample = file_get_contents($path, false, null, 0, 8192) ?: '';
    $delimiter = detect_delimiter($sample);
    $handle = fopen($path, 'r');
    if (!$handle) {
        return 'unknown';
    }

    $firstRow = fgetcsv($handle, 0, $delimiter);
    if (!$firstRow) {
        fclose($handle);
        return 'unknown';
    }
    $firstRow = import_strip_bom_from_row($firstRow);

    if (import_is_tecdoc_headers($firstRow)) {
        fclose($handle);
        return 'tecdoc';
    }

    if (import_is_tecdoc_filename($filename)) {
        fclose($handle);
        return 'tecdoc';
    }

    $supplierType = import_detect_supplier_type($firstRow, $filename);
    if ($supplierType !== null) {
        fclose($handle);
        return 'supplier:' . $supplierType;
    }

    $lowerName = function_exists('mb_strtolower') ? mb_strtolower($filename, 'UTF-8') : strtolower($filename);
    if (import_supplier_file_matches_code('AUTOPARTNER', $filename) && count($firstRow) >= 8) {
        fclose($handle);
        return 'supplier:AUTOPARTNER';
    }

    $secondRow = fgetcsv($handle, 0, $delimiter);
    fclose($handle);

    if (!$secondRow) {
        return 'generic';
    }

    if (count($firstRow) >= 8 && preg_match('/^[A-Z0-9]/i', trim((string)($firstRow[0] ?? '')))) {
        if (str_contains($lowerName, 'autopartner')) {
            return 'supplier:AUTOPARTNER';
        }
    }

    return 'generic';
}

function import_price_index_is_store(array $priceIndex): bool
{
    return ($priceIndex['__price_index_store'] ?? '') === 'sqlite';
}

function import_price_index_size(array $priceIndex): int
{
    if ($priceIndex === []) {
        return 0;
    }
    if (import_price_index_is_store($priceIndex)) {
        $pdo = $priceIndex['pdo'] ?? null;
        if (!$pdo instanceof PDO) {
            return 0;
        }
        $count = $pdo->query('SELECT COUNT(*) FROM price_index')->fetchColumn();

        return (int)$count;
    }

    return count($priceIndex);
}

function import_price_index_cache_path(array $supplierFiles): string
{
    $parts = [];
    foreach ($supplierFiles as $file) {
        $path = (string)($file['path'] ?? '');
        if ($path === '' || !is_file($path)) {
            continue;
        }
        $parts[] = $path . '|' . filesize($path) . '|' . filemtime($path);
    }
    sort($parts);

    return import_temp_dir() . '/price_cache_' . md5(implode("\n", $parts)) . '.sqlite';
}

function import_price_index_open_cached_store(string $cachePath): ?array
{
    if (!is_file($cachePath) || filesize($cachePath) <= 0) {
        return null;
    }

    $pdo = new PDO('sqlite:' . $cachePath, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA query_only = ON');

    return [
        '__price_index_store' => 'sqlite',
        'pdo' => $pdo,
        'path' => $cachePath,
        'cached' => true,
    ];
}

function import_price_index_create_store(bool $inMemory = true, string $diskPath = ''): array
{
    $path = $diskPath;
    if ($inMemory) {
        $dsn = 'sqlite::memory:';
    } else {
        $path = $diskPath !== '' ? $diskPath : import_temp_dir() . '/price_index_' . bin2hex(random_bytes(8)) . '.sqlite';
        $dsn = 'sqlite:' . $path;
    }
    $pdo = new PDO($dsn, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    if (!$inMemory) {
        $pdo->exec('PRAGMA journal_mode = WAL');
    }
    $pdo->exec('PRAGMA synchronous = OFF');
    $pdo->exec('PRAGMA temp_store = MEMORY');
    $pdo->exec('PRAGMA cache_size = -128000');
    $pdo->exec('CREATE TABLE price_index (
        code TEXT PRIMARY KEY,
        price REAL NOT NULL,
        supplier TEXT NOT NULL,
        brand TEXT NOT NULL,
        priority INTEGER NOT NULL
    )');

    return [
        '__price_index_store' => 'sqlite',
        'pdo' => $pdo,
        'path' => $path,
    ];
}

function import_price_index_store_add(array &$store, string $code, ?float $price, string $supplierType, string $brand, array $priorityMap): void
{
    if ($code === '' || $price === null || !import_price_index_is_store($store)) {
        return;
    }

    $pdo = $store['pdo'];
    $priority = (int)($priorityMap[$supplierType] ?? 99);
    if (!isset($store['select_stmt']) || !$store['select_stmt'] instanceof PDOStatement) {
        $store['select_stmt'] = $pdo->prepare('SELECT price, supplier, brand FROM price_index WHERE code = :code LIMIT 1');
    }
    if (!isset($store['upsert_stmt']) || !$store['upsert_stmt'] instanceof PDOStatement) {
        $store['upsert_stmt'] = $pdo->prepare(
            'INSERT INTO price_index (code, price, supplier, brand, priority)
             VALUES (:code, :price, :supplier, :brand, :priority)
             ON CONFLICT(code) DO UPDATE SET
                price = excluded.price,
                supplier = excluded.supplier,
                brand = excluded.brand,
                priority = excluded.priority'
        );
    }

    $store['select_stmt']->execute([':code' => $code]);
    $row = $store['select_stmt']->fetch(PDO::FETCH_ASSOC);
    $existing = is_array($row)
        ? [
            'price' => (float) ($row['price'] ?? 0),
            'supplier' => (string) ($row['supplier'] ?? ''),
            'brand' => (string) ($row['brand'] ?? ''),
        ]
        : null;

    if (!import_price_index_should_replace($existing, $price, $supplierType, $priorityMap, $brand)) {
        return;
    }

    $store['upsert_stmt']->execute([
        ':code' => $code,
        ':price' => $price,
        ':supplier' => $supplierType,
        ':brand' => $brand,
        ':priority' => $priority,
    ]);
}

function import_price_index_add(array &$index, string $code, ?float $price, string $supplierType, string $brand, array $priorityMap): void
{
    if ($code === '' || $price === null) {
        return;
    }

    if (import_price_index_is_store($index)) {
        import_price_index_store_add($index, $code, $price, $supplierType, $brand, $priorityMap);
        return;
    }

    $existing = $index[$code] ?? null;
    if (import_price_index_should_replace($existing, $price, $supplierType, $priorityMap, $brand)) {
        $index[$code] = [
            'price' => $price,
            'supplier' => $supplierType,
            'brand' => $brand,
        ];
    }
}

function import_price_index_add_aliases(array &$index, string $code, ?float $price, string $supplierType, string $brand, array $priorityMap): void
{
    import_price_index_add($index, $code, $price, $supplierType, $brand, $priorityMap);

    if ($code === '' || $price === null) {
        return;
    }

    if (preg_match('/^[A-Z]{2,3}(.+)$/', $code, $matches) && strlen($matches[1]) >= 5) {
        import_price_index_add($index, $matches[1], $price, $supplierType, $brand, $priorityMap);
    }

    if (preg_match('/^(.+)[A-Z]{2,3}$/', $code, $matches) && strlen($matches[1]) >= 5) {
        import_price_index_add($index, $matches[1], $price, $supplierType, $brand, $priorityMap);
    }
}

function import_price_index_add_autonet(array &$index, array $row, array $priorityMap): void
{
    $codeRaw = import_row_value($row, ['cod articol', 'codarticol']);
    $brand = import_normalize_supplier_brand(import_row_value($row, ['producator', 'producător']));
    $price = import_supplier_parse_feed_price(import_row_value($row, ['pret unitar', 'pret unitar taxa', 'pret']), 'AUTONET');
    $code = import_normalize_product_code($codeRaw);

    import_price_index_add_aliases($index, $code, $price, 'AUTONET', $brand, $priorityMap);

    if ($brand !== '' && $code !== '') {
        $shortBrand = substr(str_replace(' ', '', $brand), 0, 3);
        if ($shortBrand !== '') {
            if (str_ends_with($code, $shortBrand)) {
                import_price_index_add($index, substr($code, 0, -strlen($shortBrand)), $price, 'AUTONET', $brand, $priorityMap);
            }
            if (str_starts_with($code, $shortBrand)) {
                import_price_index_add($index, substr($code, strlen($shortBrand)), $price, 'AUTONET', $brand, $priorityMap);
            }
        }
    }
}

function import_price_index_add_row(array &$index, array $row, string $supplierType, array $priorityMap): void
{
    if (!import_supplier_row_passes_logic_filters($row, $supplierType)) {
        return;
    }

    switch ($supplierType) {
        case 'AUTOTOTAL':
            $code = import_normalize_product_code(import_row_value($row, ['art_article_nr', 'art article nr']));
            $brand = import_normalize_supplier_brand(import_row_value($row, ['sup_brand', 'sup brand']));
            $price = import_supplier_parse_feed_price(import_row_value($row, ['pret', 'net price']), $supplierType);
            import_price_index_add_aliases($index, $code, $price, $supplierType, $brand, $priorityMap);
            $equiv = import_normalize_product_code(import_row_value($row, ['code_echiv', 'code echiv']));
            import_price_index_add_aliases($index, $equiv, $price, $supplierType, $brand, $priorityMap);
            break;

        case 'AUTONET':
            import_price_index_add_autonet($index, $row, $priorityMap);
            break;

        case 'MATEROM':
            $code = import_normalize_product_code(import_row_value($row, ['cod npf', 'codnpf']));
            $brand = import_normalize_supplier_brand(import_row_value($row, ['brand']));
            $price = import_supplier_parse_feed_price(import_row_value($row, ['pret']), $supplierType);
            import_price_index_add_aliases($index, $code, $price, $supplierType, $brand, $priorityMap);
            if ($brand !== '' && $code !== '') {
                import_price_index_add(
                    $index,
                    import_normalize_product_code(str_replace(' ', '', $brand) . $code),
                    $price,
                    $supplierType,
                    $brand,
                    $priorityMap
                );
            }
            $ean = import_normalize_product_code(import_row_value($row, ['ean']));
            import_price_index_add_aliases($index, $ean, $price, $supplierType, $brand, $priorityMap);
            break;

        case 'ELIT':
            $code = import_normalize_product_code(import_row_value($row, [
                'supplier catalog nr.',
                'supplier catalog nr',
                'supplier catalog nr',
            ]));
            $brand = import_normalize_supplier_brand(import_row_value($row, ['lkq brand name', 'lkq brand name']));
            $price = import_supplier_parse_feed_price(import_row_value($row, ['net price', 'net price']), $supplierType);
            import_price_index_add_aliases($index, $code, $price, $supplierType, $brand, $priorityMap);
            break;

        case 'INTERCARS':
            $codeClean = import_normalize_product_code(import_row_value($row, ['p2 code', 'p2 code']));
            $codeRaw = import_normalize_product_code(import_row_value($row, ['active no.', 'active no', 'active no']));
            $brand = import_normalize_supplier_brand(import_row_value($row, ['manufacturer name', 'manufacturer name']));
            $price = import_supplier_parse_feed_price(import_row_value($row, ['unit price', 'unit price']), $supplierType);
            import_price_index_add_aliases($index, $codeClean, $price, $supplierType, $brand, $priorityMap);
            import_price_index_add_aliases($index, $codeRaw, $price, $supplierType, $brand, $priorityMap);
            if ($brand !== '') {
                foreach ([$codeClean, $codeRaw] as $aliasCode) {
                    if ($aliasCode === '') {
                        continue;
                    }
                    import_price_index_add(
                        $index,
                        import_normalize_product_code(str_replace(' ', '', $brand) . $aliasCode),
                        $price,
                        $supplierType,
                        $brand,
                        $priorityMap
                    );
                }
            }
            break;

        case 'AUTOPARTNER':
            $code = import_normalize_product_code(import_row_value($row, ['index tecdoc']));
            $brand = import_normalize_supplier_brand(import_row_value($row, ['brand code tecdoc', 'brand code auto partner']));
            $price = import_supplier_parse_feed_price(import_row_value($row, ['purchase price']), $supplierType);
            import_price_index_add_aliases($index, $code, $price, $supplierType, $brand, $priorityMap);
            $codeAlt = import_normalize_product_code(import_row_value($row, ['index autopartner']));
            import_price_index_add_aliases($index, $codeAlt, $price, $supplierType, $brand, $priorityMap);
            break;
    }
}

function import_map_autopartner_row(array $values): array
{
    return [
        'index autopartner' => $values[0] ?? '',
        'name' => $values[1] ?? '',
        'index tecdoc' => $values[2] ?? '',
        'brand code auto partner' => $values[3] ?? '',
        'client code' => $values[4] ?? '',
        'purchase price' => $values[5] ?? '',
        'brand code tecdoc' => $values[6] ?? '',
        'currency' => $values[7] ?? '',
    ];
}

function import_map_autonet_row(array $values): array
{
    return [
        'denumire articol' => $values[0] ?? '',
        'cod articol' => $values[1] ?? '',
        'producator' => $values[2] ?? '',
        'stoc cantitativ' => $values[3] ?? '',
        'pret unitar' => $values[4] ?? '',
    ];
}

function import_stream_intercars_txt(string $path, callable $rowHandler, ?bool &$stop = null): int
{
    $handle = fopen($path, 'r');
    if (!$handle) {
        return 0;
    }

    $count = 0;
    while (($values = fgetcsv($handle, 0, '|')) !== false) {
        if ($stop) {
            break;
        }
        if (!is_array($values) || count($values) < 5) {
            continue;
        }

        if (count($values) >= 10) {
            $row = [
                'active no.' => $values[1] ?? '',
                'p2 code' => $values[3] ?? '',
                'unit price' => $values[4] ?? '',
                'manufacturer name' => $values[9] ?? '',
            ];
        } else {
            $row = [
                'active no.' => $values[1] ?? '',
                'unit price' => $values[4] ?? '',
            ];
        }

        $rowHandler($row, 'INTERCARS');
        $count++;
    }

    fclose($handle);

    return $count;
}

function import_stream_supplier_file(string $path, string $filename, string $supplierType, callable $rowHandler, ?bool &$stop = null): int
{
    if ($supplierType === 'INTERCARS' && str_ends_with(strtolower($filename), '.txt')) {
        return import_stream_intercars_txt($path, $rowHandler, $stop);
    }

    $sample = file_get_contents($path, false, null, 0, 4096) ?: '';
    $delimiter = detect_delimiter($sample);
    $handle = fopen($path, 'r');
    if (!$handle) {
        return 0;
    }

    $count = 0;
    $firstRow = fgetcsv($handle, 0, $delimiter);
    if (!$firstRow) {
        fclose($handle);
        return 0;
    }
    $firstRow = import_strip_bom_from_row($firstRow);

    $hasHeader = import_supplier_type_from_header_row($firstRow) === $supplierType;
    $headers = $hasHeader ? array_map('normalize_key', $firstRow) : [];

    $processValues = static function (array $values) use (&$count, $rowHandler, $supplierType): void {
        if ($supplierType === 'AUTOPARTNER') {
            if (count($values) < 6) {
                return;
            }
            $row = import_map_autopartner_row($values);
        } elseif ($supplierType === 'AUTONET' && count($values) >= 5 && !isset($values['cod articol'])) {
            $row = import_map_autonet_row($values);
        } else {
            return;
        }

        $rowHandler($row, $supplierType);
        $count++;
    };

    if (!$hasHeader) {
        if ($supplierType === 'AUTOPARTNER') {
            $processValues($firstRow);
        } elseif ($supplierType === 'AUTONET') {
            $processValues($firstRow);
        } else {
            $headers = array_map('normalize_key', $firstRow);
            $hasHeader = true;
        }
    }

    while (($values = fgetcsv($handle, 0, $delimiter)) !== false) {
        if ($stop) {
            break;
        }
        if ($hasHeader) {
            $row = [];
            foreach ($headers as $idx => $header) {
                $row[$header] = $values[$idx] ?? '';
            }
            $rowHandler($row, $supplierType);
            $count++;
            continue;
        }

        $processValues($values);
    }

    fclose($handle);

    return $count;
}

function import_format_parts_info(array $raw): string
{
    $raw = array_change_key_case($raw, CASE_LOWER);
    $parts = trim((string)($raw['parts info'] ?? ''));
    if ($parts === '') {
        return '';
    }

    $lines = [];
    foreach (preg_split('/\|/u', $parts) ?: [] as $part) {
        $part = trim(str_replace('::', ': ', $part));
        if ($part === '' || preg_match('/^conform brosurii\s*:?\s*$/ui', $part)) {
            continue;
        }
        $lines[] = $part;
    }

    return implode(' | ', array_slice($lines, 0, 6));
}

function import_price_index_open_for_build(string $cachePath, bool $reset = false): array
{
    if ($reset && is_file($cachePath)) {
        @unlink($cachePath);
    }

    if (is_file($cachePath) && filesize($cachePath) > 0) {
        $pdo = new PDO('sqlite:' . $cachePath, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        return [
            '__price_index_store' => 'sqlite',
            'pdo' => $pdo,
            'path' => $cachePath,
        ];
    }

    return import_price_index_create_store(false, $cachePath);
}

/**
 * @param array<string, mixed>|null $savedHeaders
 * @return array{rows:int,offset:int,file_done:bool,time_exhausted:bool,headers:?array}
 */
function import_price_index_stream_supplier_chunk(
    array &$store,
    string $path,
    string $filename,
    string $supplierType,
    array $priorityMap,
    int $startOffset = 0,
    ?array $savedHeaders = null,
    float $deadline = 0.0
): array {
    if ($supplierType === 'INTERCARS' && str_ends_with(strtolower($filename), '.txt')) {
        return [
            'rows' => 0,
            'offset' => 0,
            'file_done' => true,
            'time_exhausted' => false,
            'headers' => null,
        ];
    }

    $sample = file_get_contents($path, false, null, 0, 4096) ?: '';
    $delimiter = detect_delimiter($sample);
    $handle = fopen($path, 'r');
    if (!$handle) {
        return [
            'rows' => 0,
            'offset' => 0,
            'file_done' => true,
            'time_exhausted' => false,
            'headers' => null,
        ];
    }

    $rows = 0;
    $headers = $savedHeaders;
    $hasHeader = $headers !== null && $headers !== [];
    $timeExhausted = false;

    if ($startOffset > 0) {
        fseek($handle, $startOffset);
    } else {
        $firstRow = fgetcsv($handle, 0, $delimiter);
        if (!$firstRow) {
            fclose($handle);

            return [
                'rows' => 0,
                'offset' => 0,
                'file_done' => true,
                'time_exhausted' => false,
                'headers' => null,
            ];
        }
        $firstRow = import_strip_bom_from_row($firstRow);
        $hasHeader = import_supplier_type_from_header_row($firstRow) === $supplierType;
        $headers = $hasHeader ? array_map('normalize_key', $firstRow) : null;

        if (!$hasHeader) {
            if ($supplierType === 'AUTOPARTNER' || $supplierType === 'AUTONET') {
                $row = $supplierType === 'AUTOPARTNER'
                    ? import_map_autopartner_row($firstRow)
                    : import_map_autonet_row($firstRow);
                import_price_index_add_row($store, $row, $supplierType, $priorityMap);
                $rows++;
            } else {
                $headers = array_map('normalize_key', $firstRow);
                $hasHeader = true;
            }
        }
    }

    $pdo = $store['pdo'] ?? null;
    if ($pdo instanceof PDO) {
        $pdo->exec('BEGIN IMMEDIATE');
    }

    while (($values = fgetcsv($handle, 0, $delimiter)) !== false) {
        if ($deadline > 0 && microtime(true) >= $deadline) {
            $timeExhausted = true;
            break;
        }

        if ($hasHeader && is_array($headers)) {
            $row = [];
            foreach ($headers as $idx => $header) {
                $row[$header] = $values[$idx] ?? '';
            }
            import_price_index_add_row($store, $row, $supplierType, $priorityMap);
            $rows++;
            continue;
        }

        if ($supplierType === 'AUTOPARTNER') {
            if (count($values) < 6) {
                continue;
            }
            import_price_index_add_row($store, import_map_autopartner_row($values), $supplierType, $priorityMap);
            $rows++;
        } elseif ($supplierType === 'AUTONET' && count($values) >= 5) {
            import_price_index_add_row($store, import_map_autonet_row($values), $supplierType, $priorityMap);
            $rows++;
        }
    }

    if ($pdo instanceof PDO) {
        $pdo->exec('COMMIT');
    }

    $fileDone = feof($handle);
    $offset = $fileDone ? 0 : (int)ftell($handle);
    fclose($handle);

    return [
        'rows' => $rows,
        'offset' => $offset,
        'file_done' => $fileDone,
        'time_exhausted' => $timeExhausted,
        'headers' => $fileDone ? null : $headers,
    ];
}

/**
 * Construiește indexul de prețuri în pași mici (evită timeout Cloudflare 524).
 *
 * @param array<string, mixed>|null $progress
 * @return array{done:bool,progress:array<string,mixed>,rows:int,label:string}
 */
function import_price_index_build_step(
    string $cachePath,
    array $supplierFiles,
    ?array $progress,
    float $maxSeconds = 28.0
): array {
    $progress = is_array($progress) ? $progress : ['supplier_index' => 0, 'file_offset' => 0];

    if ($supplierFiles === []) {
        return ['done' => true, 'progress' => $progress, 'rows' => 0, 'label' => ''];
    }

    $supplierIndex = (int)($progress['supplier_index'] ?? 0);
    $fileOffset = (int)($progress['file_offset'] ?? 0);

    if ($supplierIndex === 0 && $fileOffset === 0) {
        $cached = import_price_index_open_cached_store($cachePath);
        if ($cached !== null) {
            return [
                'done' => true,
                'progress' => ['supplier_index' => count($supplierFiles), 'file_offset' => 0],
                'rows' => 0,
                'label' => 'cache',
            ];
        }
    }

    if ($supplierIndex >= count($supplierFiles)) {
        return ['done' => true, 'progress' => $progress, 'rows' => 0, 'label' => ''];
    }

    $buildPath = $cachePath . '.partial';
    $deadline = microtime(true) + max(5.0, $maxSeconds);
    $store = import_price_index_open_for_build(
        $buildPath,
        $supplierIndex === 0 && $fileOffset === 0 && !is_file($buildPath)
    );
    $priorityMap = import_supplier_priority_map();
    $rowsProcessed = 0;
    $currentLabel = '';

    while ($supplierIndex < count($supplierFiles) && microtime(true) < $deadline) {
        $file = $supplierFiles[$supplierIndex];
        $path = (string)($file['path'] ?? '');
        $filename = (string)($file['name'] ?? basename($path));
        $currentLabel = $filename;

        if ($path === '' || !is_file($path)) {
            $supplierIndex++;
            $fileOffset = 0;
            unset($progress['headers']);
            continue;
        }

        $kind = import_classify_file($path, $filename);
        $supplierType = str_starts_with($kind, 'supplier:') ? substr($kind, 9) : null;
        if ($supplierType === null) {
            $supplierIndex++;
            $fileOffset = 0;
            unset($progress['headers']);
            continue;
        }

        $savedHeaders = is_array($progress['headers'] ?? null) ? $progress['headers'] : null;
        $chunk = import_price_index_stream_supplier_chunk(
            $store,
            $path,
            $filename,
            $supplierType,
            $priorityMap,
            $fileOffset,
            $savedHeaders,
            $deadline
        );

        $rowsProcessed += (int)($chunk['rows'] ?? 0);
        $fileOffset = (int)($chunk['offset'] ?? 0);

        if (!empty($chunk['headers']) && is_array($chunk['headers'])) {
            $progress['headers'] = $chunk['headers'];
        } elseif (!empty($chunk['file_done'])) {
            unset($progress['headers']);
        }

        if (!empty($chunk['file_done'])) {
            $supplierIndex++;
            $fileOffset = 0;
            unset($progress['headers']);
            continue;
        }

        if (!empty($chunk['time_exhausted'])) {
            break;
        }
    }

    unset($store);

    $progress['supplier_index'] = $supplierIndex;
    $progress['file_offset'] = $fileOffset;
    $done = $supplierIndex >= count($supplierFiles);

    if ($done && is_file($buildPath)) {
        if (is_file($cachePath)) {
            @unlink($cachePath);
        }
        @rename($buildPath, $cachePath);
    }

    return [
        'done' => $done,
        'progress' => $progress,
        'rows' => $rowsProcessed,
        'label' => $currentLabel,
    ];
}

function import_build_price_index(array $supplierFiles): array
{
    if ($supplierFiles === []) {
        return [];
    }

    $cachePath = import_price_index_cache_path($supplierFiles);
    $cached = import_price_index_open_cached_store($cachePath);
    if ($cached !== null) {
        return $cached;
    }

    $index = import_price_index_create_store(false, $cachePath);
    $priorityMap = import_supplier_priority_map();

    foreach ($supplierFiles as $file) {
        $path = (string)($file['path'] ?? '');
        $filename = (string)($file['name'] ?? basename($path));
        if ($path === '' || !is_file($path)) {
            continue;
        }

        $kind = import_classify_file($path, $filename);
        $supplierType = str_starts_with($kind, 'supplier:') ? substr($kind, 9) : null;
        if ($supplierType === null) {
            continue;
        }

        $index['pdo']->exec('BEGIN IMMEDIATE');

        import_stream_supplier_file(
            $path,
            $filename,
            $supplierType,
            static function (array $row, string $type) use (&$index, $priorityMap): void {
                import_price_index_add_row($index, $row, $type, $priorityMap);
            }
        );

        $index['pdo']->exec('COMMIT');
    }

    return $index;
}

function import_supplier_net_to_base(float $net, string $supplier): float
{
    // Pret baza = pret CSV + adaos feed furnizor (fara TVA).
    // TVA se aplica la pasul Adaos Comercial, impreuna cu adaosul magazinului.
    unset($supplier);

    return round($net, 2);
}

function import_extract_cross_codes(array $row): array
{
    $row = array_change_key_case($row, CASE_LOWER);
    $cross = import_row_value($row, ['art cross', 'art_cross']);
    if ($cross === '') {
        return [];
    }

    $codes = [];
    foreach (preg_split('/\|/u', $cross) ?: [] as $part) {
        $part = trim($part);
        if ($part === '') {
            continue;
        }
        if (preg_match('/::\s*(.+)$/u', $part, $matches)) {
            $codes[] = trim($matches[1]);
            continue;
        }
        $codes[] = $part;
    }

    return $codes;
}

function import_supplier_row_to_entry(array $row, string $supplierType): ?array
{
    switch ($supplierType) {
        case 'AUTOTOTAL':
            $code = import_normalize_product_code(import_row_value($row, ['art_article_nr', 'art article nr']));
            $brand = import_normalize_supplier_brand(import_row_value($row, ['sup_brand', 'sup brand']));
            $name = trim(import_row_value($row, ['art_name', 'art name', 'description']));
            $netPrice = import_supplier_parse_feed_price(import_row_value($row, ['pret', 'net price']), $supplierType);
            break;

        case 'AUTONET':
            $code = import_normalize_product_code(import_row_value($row, ['cod articol', 'codarticol']));
            $brand = import_normalize_supplier_brand(import_row_value($row, ['producator', 'producător']));
            $name = trim(import_row_value($row, ['denumire articol', 'denumire']));
            $netPrice = import_supplier_parse_feed_price(import_row_value($row, ['pret unitar', 'pret unitar taxa', 'pret']), $supplierType);
            break;

        case 'MATEROM':
            $code = import_normalize_product_code(import_row_value($row, ['cod npf', 'codnpf']));
            $brand = import_normalize_supplier_brand(import_row_value($row, ['brand']));
            $name = trim(import_row_value($row, ['denumire', 'description']));
            $netPrice = import_supplier_parse_feed_price(import_row_value($row, ['pret']), $supplierType);
            break;

        case 'ELIT':
            $code = import_normalize_product_code(import_row_value($row, [
                'supplier catalog nr.',
                'supplier catalog nr',
            ]));
            $brand = import_normalize_supplier_brand(import_row_value($row, ['lkq brand name']));
            $name = trim(import_row_value($row, ['genart description - ro', 'description', 'denumire']));
            $netPrice = import_supplier_parse_feed_price(import_row_value($row, ['net price']), $supplierType);
            break;

        case 'INTERCARS':
            $code = import_normalize_product_code(import_row_value($row, ['p2 code', 'p2 code']));
            if ($code === '') {
                $code = import_normalize_product_code(import_row_value($row, ['active no.', 'active no']));
            }
            $brand = import_normalize_supplier_brand(import_row_value($row, ['manufacturer name']));
            $name = trim(import_row_value($row, ['description', 'denumire']));
            $netPrice = import_supplier_parse_feed_price(import_row_value($row, ['unit price']), $supplierType);
            break;

        case 'AUTOPARTNER':
            $code = import_normalize_product_code(import_row_value($row, ['index tecdoc']));
            $brandRaw = trim(import_row_value($row, ['brand code tecdoc', 'brand code auto partner']));
            $brand = import_resolve_brand_canonical_name($brandRaw, $code);
            $name = trim(import_row_value($row, ['name']));
            $netPrice = import_supplier_parse_feed_price(import_row_value($row, ['purchase price']), $supplierType);
            break;

        default:
            return null;
    }

    if ($code === '' || $netPrice === null) {
        return null;
    }

    $entry = [
        'code' => $code,
        'brand' => $brand,
        'name' => $name,
        'net_price' => $netPrice,
        'supplier' => $supplierType,
    ];

    if (!function_exists('import_supplier_enrich_entry_stock')) {
        require_once __DIR__ . '/import_supplier_stock_zero_lib.php';
    }
    import_supplier_enrich_entry_stock($entry, $row, $supplierType);

    return $entry;
}

function import_build_supplier_catalog(array $supplierFiles, int $maxProducts = 500, string $brandFilter = ''): array
{
    $catalog = [];
    $priorityMap = import_supplier_priority_map();
    $brandFilterNorm = import_normalize_supplier_brand($brandFilter);
    $maxProducts = max(1, $maxProducts);
    $stoppedEarly = false;

    foreach ($supplierFiles as $file) {
        $path = (string)($file['path'] ?? '');
        $filename = (string)($file['name'] ?? basename($path));
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
            static function (array $row, string $type) use (&$catalog, $priorityMap, $brandFilterNorm, $maxProducts, &$stop): void {
                $entry = import_supplier_row_to_entry($row, $type);
                if ($entry === null) {
                    return;
                }

                if ($brandFilterNorm !== '') {
                    $entryBrand = import_normalize_supplier_brand((string)$entry['brand']);
                    if ($entryBrand === '' || !str_contains($entryBrand, $brandFilterNorm)) {
                        return;
                    }
                }

                $key = $entry['code'] . '|' . str_replace(' ', '', import_normalize_supplier_brand((string)$entry['brand']));
                $existing = $catalog[$key] ?? null;
                $newPrice = (float) ($entry['net_price'] ?? 0);
                if (import_price_index_should_replace(
                    $existing === null ? null : [
                        'price' => (float) ($existing['net_price'] ?? 0),
                        'supplier' => (string) ($existing['supplier'] ?? ''),
                        'brand' => (string) ($existing['brand'] ?? ''),
                    ],
                    $newPrice,
                    $type,
                    $priorityMap,
                    (string) ($entry['brand'] ?? '')
                )) {
                    $catalog[$key] = $entry;
                }

                if (count($catalog) >= $maxProducts) {
                    $stop = true;
                }
            },
            $stop
        );

        if ($stop) {
            $stoppedEarly = true;
            break;
        }
    }

    $all = array_values($catalog);
    $totalUnique = count($all);
    $entries = array_slice($all, 0, $maxProducts);

    return [
        'entries' => $entries,
        'total_unique' => $totalUnique,
        'truncated' => $stoppedEarly || $totalUnique > count($entries),
    ];
}

function import_lookup_codes_for_row(array $rawRow): array
{
    $row = array_change_key_case($rawRow, CASE_LOWER);
    $brand = import_normalize_supplier_brand(import_row_value($row, ['art brand', 'art_brand']));
    $brandCodEntries = [];
    $plainEntries = [];
    $oemEntries = [];

    if ($brand !== '') {
        $brandKey = str_replace(' ', '', $brand);
        foreach ([
            import_row_value($row, ['art code 1', 'art_code_1']),
            import_row_value($row, ['art code 2', 'art_code_2']),
        ] as $value) {
            $normalized = import_normalize_product_code($brandKey . $value);
            if ($normalized !== '') {
                $brandCodEntries[] = ['code' => $normalized, 'via' => 'brand_cod'];
            }
        }
    }

    foreach (import_extract_cross_codes($row) as $crossCode) {
        $normalized = import_normalize_product_code($crossCode);
        if ($normalized !== '') {
            $oemEntries[] = ['code' => $normalized, 'via' => 'oem_cross'];
        }
    }

    foreach ([
        import_row_value($row, ['art code 1', 'art_code_1']),
        import_row_value($row, ['art code 2', 'art_code_2']),
        import_row_value($row, ['art ean', 'art_ean']),
    ] as $value) {
        $normalized = import_normalize_product_code($value);
        if ($normalized !== '') {
            $plainEntries[] = ['code' => $normalized, 'via' => 'cod_produs'];
        }
    }

    // brand_cod înainte de cod simplu — evită coliziuni (ex. 101117 FAG vs alt articol la același cod NPF)
    $ordered = array_merge($brandCodEntries, $oemEntries, $plainEntries);

    $unique = [];
    $result = [];
    foreach ($ordered as $entry) {
        $key = $entry['code'] . '|' . $entry['via'];
        if (isset($unique[$key])) {
            continue;
        }
        $unique[$key] = true;
        $result[] = $entry;
    }

    return $result;
}

/**
 * @param array<string, mixed> $rawRow
 * @param array<string, mixed>|null $match
 */
function import_supplier_price_lookup_is_valid(array $rawRow, ?array $match, string $via): bool
{
    if ($match === null) {
        return false;
    }

    $row = array_change_key_case($rawRow, CASE_LOWER);
    $productBrand = import_normalize_supplier_brand(import_row_value($row, ['art brand', 'art_brand']));
    $supplierBrand = import_normalize_supplier_brand((string) ($match['brand'] ?? ''));

    if ($via === 'brand_cod') {
        return $productBrand === '' || $supplierBrand === '' || import_supplier_price_match_product_brand($productBrand, $supplierBrand);
    }

    if ($productBrand !== '') {
        if ($supplierBrand === '') {
            return false;
        }

        return import_supplier_price_match_product_brand($productBrand, $supplierBrand);
    }

    return true;
}

function import_lookup_supplier_price(array $priceIndex, array $rawRow): ?array
{
    if ($priceIndex === []) {
        return null;
    }

    if (import_price_index_is_store($priceIndex)) {
        $pdo = $priceIndex['pdo'] ?? null;
        if (!$pdo instanceof PDO) {
            return null;
        }
        if (!isset($priceIndex['lookup_stmt']) || !$priceIndex['lookup_stmt'] instanceof PDOStatement) {
            $priceIndex['lookup_stmt'] = $pdo->prepare('SELECT price, supplier, brand FROM price_index WHERE code = :code LIMIT 1');
        }
        $lookupStmt = $priceIndex['lookup_stmt'];

        foreach (import_lookup_codes_for_row($rawRow) as $candidate) {
            $code = $candidate['code'];
            $via = (string) ($candidate['via'] ?? '');
            if ($code === '') {
                continue;
            }
            $lookupStmt->execute([':code' => $code]);
            $match = $lookupStmt->fetch(PDO::FETCH_ASSOC);
            if (!import_supplier_price_lookup_is_valid($rawRow, is_array($match) ? $match : null, $via)) {
                continue;
            }
            if (is_array($match)) {
                $match['matched_code'] = $code;
                $match['matched_via'] = $via;
                return $match;
            }
        }

        return null;
    }

    foreach (import_lookup_codes_for_row($rawRow) as $candidate) {
        $code = $candidate['code'];
        $via = (string) ($candidate['via'] ?? '');
        if ($code !== '' && isset($priceIndex[$code])) {
            $match = $priceIndex[$code];
            if (!import_supplier_price_lookup_is_valid($rawRow, $match, $via)) {
                continue;
            }
            $match['matched_code'] = $code;
            $match['matched_via'] = $via;
            return $match;
        }
    }

    return null;
}

function import_apply_supplier_pricing(array $product, array $priceIndex, ?\Evasystem\Controllers\AdaosComercial\AdaosComercialService $markupService = null): array
{
    if ($priceIndex === []) {
        return $product;
    }

    $rawPayload = json_decode((string)($product['raw_json'] ?? '{}'), true);
    $rows = is_array($rawPayload['rows'] ?? null) ? $rawPayload['rows'] : [];
    $firstRow = $rows[0] ?? [];

    if ($firstRow === []) {
        $rawPayload = json_decode((string)($product['raw_json'] ?? '{}'), true);
        if (is_array($rawPayload) && isset($rawPayload['schema'])) {
            // already aggregated
        }
    }

    $priceInfo = null;
    foreach ($rows as $row) {
        $priceInfo = import_lookup_supplier_price($priceIndex, $row);
        if ($priceInfo !== null) {
            break;
        }
    }
    if ($priceInfo === null && $firstRow !== []) {
        $priceInfo = import_lookup_supplier_price($priceIndex, $firstRow);
    }
    if ($priceInfo === null) {
        return $product;
    }

    $basePrice = import_supplier_net_to_base((float)$priceInfo['price'], (string)$priceInfo['supplier']);
    $product['pBasePrice'] = rtrim(rtrim(number_format($basePrice, 2, '.', ''), '0'), '.');
    $product['pSupplier'] = (string)$priceInfo['supplier'];

    if ($markupService !== null) {
        $pricing = $markupService->applyAutomaticMarkup($product);
        $product = array_merge($product, $pricing['data']);
    } else {
        $product['pPrice'] = $product['pBasePrice'];
    }

    $rawPayload = json_decode((string)($product['raw_json'] ?? '{}'), true);
    if (!is_array($rawPayload)) {
        $rawPayload = [];
    }
    $rawPayload['supplier_price'] = [
        'supplier' => (string)$priceInfo['supplier'],
        'net_price' => (float)$priceInfo['price'],
        'purchase_base' => $basePrice,
        'base_price_with_vat' => $basePrice,
        'matched_code' => (string)($priceInfo['matched_code'] ?? ''),
        'matched_via' => (string)($priceInfo['matched_via'] ?? ''),
        'markup_rule_id' => $product['pMarkupRuleId'] ?? null,
        'markup_rule_name' => $product['pMarkupRuleName'] ?? null,
    ];
    if (isset($rawPayload['product_summary']) && is_array($rawPayload['product_summary'])) {
        $rawPayload['product_summary']['identity']['furnizor'] = (string)$priceInfo['supplier'];
    }
    $product['raw_json'] = json_encode($rawPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    return $product;
}

function import_preview_uploaded_files(array $filesMeta, int $maxPreview = 500, array $options = []): array
{
    $tecdocFiles = [];
    $supplierFiles = [];
    $genericFiles = [];
    $missingFiles = [];
    $brandFilter = trim((string)($options['brand_filter'] ?? ''));
    $forceSupplierApi = !empty($options['force_supplier_api']);
    $skipTecdocCsvScan = !array_key_exists('skip_tecdoc_csv_scan', $options) || !empty($options['skip_tecdoc_csv_scan']);
    $scanTecdocCsv = !empty($options['scan_tecdoc_csv']);
    $apiEnrichmentLimit = max(0, min($maxPreview, (int)($options['api_enrichment_limit'] ?? 50)));

    foreach ($filesMeta as $fileMeta) {
        $fileId = (string)($fileMeta['file_id'] ?? '');
        $originalName = (string)($fileMeta['original_name'] ?? '');
        if ($fileId === '' || $originalName === '') {
            continue;
        }

        $path = import_temp_file_path($fileId);
        if (!is_file($path)) {
            $missingFiles[] = $originalName;
            continue;
        }

        $kind = import_resolve_upload_file_kind($path, $originalName, $fileMeta);
        $entry = ['path' => $path, 'name' => $originalName];

        if ($kind === 'tecdoc') {
            $tecdocFiles[] = $entry;
        } elseif (str_starts_with($kind, 'supplier:')) {
            $supplierFiles[] = $entry;
        } else {
            $genericFiles[] = $entry;
        }
    }

    if ($missingFiles !== []) {
        return [
            'products' => [],
            'total_rows' => 0,
            'truncated' => false,
            'price_index_size' => 0,
            'tecdoc_files' => count($tecdocFiles),
            'supplier_files' => count($supplierFiles),
            'import_mode' => 'error',
            'error' => 'missing_files',
            'missing_files' => $missingFiles,
        ];
    }

    $previewMode = import_resolve_preview_mode($options, $tecdocFiles, $supplierFiles);
    if ($previewMode === 'tecdoc_master' && $tecdocFiles !== [] && $supplierFiles !== []) {
        return import_preview_tecdoc_master($tecdocFiles, $supplierFiles, $maxPreview, array_merge($options, [
            'brand_filter' => $brandFilter,
            'require_supplier_price' => !array_key_exists('require_supplier_price', $options)
                || !empty($options['require_supplier_price']),
        ]));
    }

    $markupService = null;
    if (class_exists(\Evasystem\Controllers\AdaosComercial\AdaosComercialService::class)) {
        try {
            $markupService = new \Evasystem\Controllers\AdaosComercial\AdaosComercialService();
        } catch (Throwable $e) {
            $markupService = null;
        }
    }

    if ($supplierFiles !== [] && ($forceSupplierApi || $skipTecdocCsvScan || $tecdocFiles === [])) {
        $validation = import_validate_uploaded_files($supplierFiles, $tecdocFiles, $genericFiles);
        if (!$validation['ok']) {
            return [
                'products' => [],
                'total_rows' => 0,
                'truncated' => false,
                'price_index_size' => 0,
                'tecdoc_files' => count($tecdocFiles),
                'supplier_files' => count($supplierFiles),
                'import_mode' => 'error',
                'error' => 'validation_failed',
                'validation_errors' => $validation['errors'],
            ];
        }

        $catalog = import_build_supplier_catalog($supplierFiles, $maxPreview, $brandFilter);
        if ($catalog['entries'] === []) {
            return [
                'products' => [],
                'total_rows' => 0,
                'truncated' => false,
                'price_index_size' => 0,
                'tecdoc_files' => count($tecdocFiles),
                'supplier_files' => count($supplierFiles),
                'import_mode' => 'error',
                'error' => 'empty_supplier_catalog',
            ];
        }

        $tecdocMaxRowsPerCode = max(1, min(250, (int)($options['tecdoc_max_rows_per_code'] ?? 30)));
        $tecdocBundle = $tecdocFiles !== []
            ? import_tecdoc_build_catalog_bundle(
                $tecdocFiles,
                $catalog['entries'],
                $tecdocMaxRowsPerCode,
                true,
                true
            )
            : ['lookup' => [], 'row_groups' => []];
        $tecdocLookup = $tecdocBundle['lookup'];
        $tecdocRowGroups = $tecdocBundle['row_groups'];
        $tecdocFileHits = 0;
        $products = [];
        $skippedNoCompat = 0;

        foreach ($catalog['entries'] as $entry) {
            $codeNorm = import_normalize_product_code((string)($entry['code'] ?? ''));
            $tecdocRows = $codeNorm !== '' ? ($tecdocRowGroups[$codeNorm] ?? []) : [];
            $tecdocRecord = import_tecdoc_lookup_catalog_entry($tecdocLookup, $entry);
            if ($tecdocRows !== [] || $tecdocRecord !== null) {
                $tecdocFileHits++;
            }

            $product = import_build_product_from_supplier_tecdoc(
                $entry,
                $markupService,
                false,
                $tecdocRecord,
                $tecdocRows
            );
            if ($product === null) {
                $skippedNoCompat++;
                continue;
            }
            $products[] = $product;
        }

        return [
            'products' => $products,
            'total_rows' => (int)$catalog['total_unique'],
            'truncated' => !empty($catalog['truncated']),
            'price_index_size' => (int)$catalog['total_unique'],
            'tecdoc_files' => count($tecdocFiles),
            'supplier_files' => count($supplierFiles),
            'import_mode' => $tecdocFiles !== [] ? 'supplier_preview' : 'supplier_files',
            'tecdoc_deferred' => false,
            'tecdoc_file_hits' => $tecdocFileHits,
            'tecdoc_skipped_no_compat' => $skippedNoCompat,
            'file_validation' => $validation,
            'tecdoc_api_found' => 0,
            'tecdoc_api_missing' => 0,
            'tecdoc_api_skipped' => max(0, count($catalog['entries']) - count($products)),
            'brand_filter' => $brandFilter,
        ];
    }

    if (!$scanTecdocCsv || $tecdocFiles === []) {
        return [
            'products' => [],
            'total_rows' => 0,
            'truncated' => false,
            'price_index_size' => 0,
            'tecdoc_files' => count($tecdocFiles),
            'supplier_files' => count($supplierFiles),
            'import_mode' => 'error',
            'error' => 'no_supplier_files',
        ];
    }

    $priceIndex = import_build_price_index($supplierFiles);

    $products = [];
    $totalRows = 0;
    $truncated = false;
    $filesToProcess = $tecdocFiles !== [] ? $tecdocFiles : array_merge($genericFiles, $supplierFiles);

    foreach ($filesToProcess as $file) {
        $preview = preview_products_from_file(
            $file['path'],
            $file['name'],
            max(1, $maxPreview - count($products)),
            $priceIndex,
            $markupService
        );

        foreach ($preview['products'] as $product) {
            if (count($products) >= $maxPreview) {
                $truncated = true;
                break 2;
            }
            $products[] = $product;
        }

        $totalRows += (int)$preview['total_rows'];
        if (!empty($preview['truncated'])) {
            $truncated = true;
            break;
        }
    }

    return [
        'products' => $products,
        'total_rows' => $totalRows,
        'truncated' => $truncated,
        'price_index_size' => import_price_index_size($priceIndex),
        'tecdoc_files' => count($tecdocFiles),
        'supplier_files' => count($supplierFiles),
        'import_mode' => $tecdocFiles !== [] ? 'tecdoc_csv' : 'generic',
    ];
}
