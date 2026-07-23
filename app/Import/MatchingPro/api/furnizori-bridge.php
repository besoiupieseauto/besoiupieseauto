<?php
declare(strict_types=1);

/**
 * Legătură obligatorie Import Pro (MatchingPro) ↔ modul Furnizori (tabel furnizori).
 * Fișierele sunt acceptate doar pentru furnizori înregistrați, activi, neblocați.
 */

function import_motor_project_root(): string
{
    if (defined('BESOIU_ROOT')) {
        return rtrim(str_replace('\\', '/', (string) BESOIU_ROOT), '/');
    }

    return rtrim(str_replace('\\', '/', dirname(__DIR__, 4)), '/');
}

function import_motor_canonical_feed_base_dir(): string
{
    import_motor_boot_admin_stack();
    if (defined('BESOIU_ADMIN')) {
        return rtrim(str_replace('\\', '/', (string) BESOIU_ADMIN), '/') . '/storage/supplier_feeds';
    }

    return import_motor_project_root() . '/admin/storage/supplier_feeds';
}

function import_motor_supplier_feed_slug(string $code): string
{
    $code = trim($code);

    return function_exists('import_furnizori_search_slug')
        ? import_furnizori_search_slug($code)
        : strtolower($code);
}

/**
 * Mută CSV-uri din folderul legacy MatchingPro/suppliers/ în admin/storage/supplier_feeds/.
 *
 * @return array{moved:list<array{from:string,to:string}>,skipped:list<string>,errors:list<string>}
 */
function import_motor_migrate_legacy_supplier_files(): array
{
    static $ran = false;
    if ($ran) {
        return ['moved' => [], 'skipped' => [], 'errors' => []];
    }
    $ran = true;

    $legacyRoot = defined('IMPORT_SUPPLIERS_LEGACY_DIR')
        ? IMPORT_SUPPLIERS_LEGACY_DIR
        : (dirname(__DIR__) . DIRECTORY_SEPARATOR . 'suppliers');
    if (!is_dir($legacyRoot)) {
        return ['moved' => [], 'skipped' => [], 'errors' => []];
    }

    $canonicalBase = str_replace('\\', '/', import_motor_canonical_feed_base_dir());
    if (!is_dir($canonicalBase)) {
        @mkdir($canonicalBase, 0775, true);
    }

    $result = ['moved' => [], 'skipped' => [], 'errors' => []];

    foreach (scandir($legacyRoot) ?: [] as $slug) {
        if ($slug === '.' || $slug === '..') {
            continue;
        }
        $legacyDir = $legacyRoot . DIRECTORY_SEPARATOR . $slug;
        if (!is_dir($legacyDir)) {
            continue;
        }

        $profile = import_motor_supplier_by_slug(strtolower($slug));
        $targetDir = $profile !== null
            ? (string) ($profile['feed_dir'] ?? '')
            : ($canonicalBase . '/' . strtolower($slug));
        if ($targetDir === '') {
            continue;
        }
        $targetDir = str_replace('\\', '/', $targetDir);
        $legacyNorm = str_replace('\\', '/', $legacyDir);
        if (rtrim($legacyNorm, '/') === rtrim($targetDir, '/')) {
            continue;
        }
        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0775, true);
        }

        foreach (scandir($legacyDir) ?: [] as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $src = $legacyDir . DIRECTORY_SEPARATOR . $file;
            if (!is_file($src)) {
                continue;
            }
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if (!in_array($ext, ['csv', 'txt'], true)) {
                continue;
            }

            $dst = $targetDir . '/' . $file;
            if (is_file($dst)) {
                $sameSize = (int) filesize($src) === (int) filesize($dst);
                if ($sameSize) {
                    @unlink($src);
                    import_motor_remap_processed_state_path($src, $dst);
                    $result['skipped'][] = $file . ' (duplicat în destinație)';
                    continue;
                }
                $dst = $targetDir . '/' . pathinfo($file, PATHINFO_FILENAME) . '_legacy_' . date('Ymd_His') . '.' . $ext;
            }

            if (@rename($src, $dst) || (@copy($src, $dst) && @unlink($src))) {
                import_motor_remap_processed_state_path($src, $dst);
                $result['moved'][] = ['from' => $src, 'to' => $dst];
            } else {
                $result['errors'][] = 'Nu pot muta: ' . $src;
            }
        }

        $remaining = array_diff(scandir($legacyDir) ?: [], ['.', '..']);
        if ($remaining === []) {
            @rmdir($legacyDir);
        }
    }

    return $result;
}

function import_motor_remap_processed_state_path(string $oldPath, string $newPath): void
{
    $state = import_load_state();
    $files = $state['files'] ?? [];
    if (!is_array($files) || $files === []) {
        return;
    }

    $oldKeys = [
        str_replace('/', DIRECTORY_SEPARATOR, $oldPath),
        str_replace('\\', '/', $oldPath),
        $oldPath,
    ];
    $newKey = str_replace('/', DIRECTORY_SEPARATOR, $newPath);
    $newReal = @realpath($newPath);
    if ($newReal !== false) {
        $newKey = $newReal;
    }

    $changed = false;
    foreach ($oldKeys as $oldKey) {
        if (!isset($files[$oldKey])) {
            continue;
        }
        $entry = $files[$oldKey];
        unset($files[$oldKey]);
        $files[$newKey] = $entry;
        $changed = true;
        break;
    }

    if ($changed) {
        $state['files'] = $files;
        import_save_processed_state($state);
    }
}

function import_motor_backend_produse_dir(): string
{
    $candidates = [];
    if (defined('BESOIU_ROOT')) {
        $root = rtrim(str_replace('\\', '/', (string) BESOIU_ROOT), '/');
        $candidates[] = $root . '/app/Backend/src/Controllers/Produse';
        $candidates[] = $root . '/Backend/src/Controllers/Produse';
    }
    if (defined('BESOIU_APP')) {
        $candidates[] = rtrim(str_replace('\\', '/', (string) BESOIU_APP), '/') . '/Backend/src/Controllers/Produse';
    }
    $candidates[] = dirname(__DIR__, 3) . '/Backend/src/Controllers/Produse';
    $candidates[] = dirname(__DIR__, 4) . '/app/Backend/src/Controllers/Produse';

    foreach ($candidates as $dir) {
        if (is_file($dir . '/import_supplier_lib.php')) {
            return str_replace('\\', '/', $dir);
        }
    }

    return dirname(__DIR__, 3) . '/Backend/src/Controllers/Produse';
}

function import_motor_boot_admin_stack(): void
{
    static $booted = false;
    if ($booted) {
        return;
    }

    $root = import_motor_project_root();

    if (!defined('BESOIU_ROOT')) {
        $adminBootstrap = $root . '/admin/bootstrap.php';
        if (is_file($adminBootstrap)) {
            require_once $adminBootstrap;
        } else {
            define('BESOIU_ROOT', $root);
            define('BESOIU_ADMIN', $root . '/admin');
            define('BESOIU_APP', $root . '/app');
            define('BESOIU_BACKEND', BESOIU_APP . '/Backend');
            define('BESOIU_CONFIG', BESOIU_APP . '/Config');
            define('BESOIU_ADMIN_MODULES', $root . '/modules');
        }
    }

    if (!defined('BESOIU_ADMIN')) {
        define('BESOIU_ADMIN', $root . '/admin');
    }
    if (!defined('BESOIU_APP')) {
        define('BESOIU_APP', $root . '/app');
    }
    if (!defined('BESOIU_BACKEND')) {
        define('BESOIU_BACKEND', BESOIU_APP . '/Backend');
    }
    if (!defined('BESOIU_CONFIG')) {
        define('BESOIU_CONFIG', BESOIU_APP . '/Config');
    }
    if (!defined('BESOIU_ADMIN_MODULES')) {
        define('BESOIU_ADMIN_MODULES', $root . '/modules');
    }
    if (!defined('BESOIU_LIB')) {
        define('BESOIU_LIB', BESOIU_APP . '/Lib');
    }
    if (!defined('BESOIU_LEGACY')) {
        define('BESOIU_LEGACY', BESOIU_APP . '/Legacy');
    }
    if (!defined('BESOIU_STORAGE')) {
        define('BESOIU_STORAGE', BESOIU_APP . '/Storage');
    }

    $autoloadCandidates = [
        BESOIU_BACKEND . '/vendor/autoload.php',
        BESOIU_ADMIN . '/vendor/autoload.php',
        $root . '/vendor/autoload.php',
    ];

    foreach ($autoloadCandidates as $autoload) {
        if (is_file($autoload)) {
            require_once $autoload;
            break;
        }
    }

    import_motor_load_env();
    $booted = true;
}

function import_motor_load_env(): void
{
    static $loaded = false;
    if ($loaded) {
        return;
    }

    if (!class_exists(\Dotenv\Dotenv::class)) {
        $loaded = true;

        return;
    }

    foreach ([
        BESOIU_BACKEND,
        BESOIU_CONFIG,
        BESOIU_ADMIN,
        import_motor_project_root(),
    ] as $dir) {
        $envFile = rtrim(str_replace('\\', '/', $dir), '/') . '/.env';
        if (!is_file($envFile)) {
            continue;
        }
        try {
            \Dotenv\Dotenv::createImmutable(dirname($envFile))->safeLoad();
        } catch (Throwable) {
            // .env opțional / parțial
        }
    }

    $loaded = true;
}

/** @return array<string, mixed>|null */
function import_motor_app_config(): ?array
{
    static $config = null;
    if (is_array($config)) {
        return $config;
    }

    import_motor_load_env();

    $candidates = [];
    if (defined('BESOIU_BACKEND')) {
        $candidates[] = BESOIU_BACKEND . '/config/config.php';
    }
    if (defined('BESOIU_CONFIG')) {
        $candidates[] = BESOIU_CONFIG . '/config.php';
    }
    $candidates[] = import_motor_project_root() . '/app/Backend/config/config.php';
    $candidates[] = import_motor_project_root() . '/app/Config/config.php';

    foreach ($candidates as $path) {
        if ($path !== '' && is_file($path)) {
            $loaded = require $path;
            if (is_array($loaded)) {
                $config = $loaded;

                return $config;
            }
        }
    }

    return null;
}

function import_motor_ensure_database_class(): bool
{
    if (class_exists(\Config\Database::class)) {
        return true;
    }

    $dbCandidates = [];
    if (defined('BESOIU_BACKEND')) {
        $dbCandidates[] = BESOIU_BACKEND . '/config/Database.php';
    }
    if (defined('BESOIU_CONFIG')) {
        $dbCandidates[] = BESOIU_CONFIG . '/Database.php';
    }
    $dbCandidates[] = import_motor_project_root() . '/app/Backend/config/Database.php';
    $dbCandidates[] = import_motor_project_root() . '/app/Config/Database.php';

    foreach ($dbCandidates as $path) {
        if ($path !== '' && is_file($path)) {
            require_once $path;
            break;
        }
    }

    return class_exists(\Config\Database::class);
}

function import_motor_ensure_database(): bool
{
    static $ready = false;
    if ($ready) {
        return true;
    }

    import_motor_boot_admin_stack();
    if (!import_motor_ensure_database_class()) {
        return false;
    }

    if (\Config\Database::hasConnection('default')) {
        $ready = true;

        return true;
    }

    $config = import_motor_app_config();
    if ($config === null) {
        return false;
    }

    try {
        \Config\Database::getInstance(
            (string) ($config['db_host'] ?? '127.0.0.1'),
            (string) ($config['db_name'] ?? ''),
            (string) ($config['db_user'] ?? 'root'),
            (string) ($config['db_pass'] ?? ''),
        );
        $ready = true;

        return true;
    } catch (Throwable) {
        return false;
    }
}

/** @return array<int, array<string, mixed>> */
function import_motor_fetch_furnizori_rows(): array
{
    if (!import_motor_ensure_database()) {
        return [];
    }

    try {
        $pdo = \Config\Database::getDB();
        $stmt = $pdo->query(
            "SELECT * FROM furnizori WHERE code IS NOT NULL AND TRIM(code) <> '' ORDER BY id DESC"
        );
        if ($stmt === false) {
            return [];
        }
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    } catch (Throwable) {
        return [];
    }
}

/** @return array<int, array<string, mixed>> */
function import_motor_load_furnizori_rows(): array
{
    $rows = import_motor_fetch_furnizori_rows();
    if ($rows !== []) {
        return $rows;
    }

    import_motor_boot_admin_stack();

    if (!class_exists(\Besoiu\Core\Supplier\SupplierCatalogRepository::class)) {
        return [];
    }

    try {
        $repo = new \Besoiu\Core\Supplier\SupplierCatalogRepository();

        return $repo->findAll();
    } catch (Throwable) {
        return [];
    }
}

function import_motor_boot_furnizori_libs(): void
{
    static $booted = false;
    if ($booted) {
        return;
    }

    import_motor_boot_admin_stack();

    $dir = import_motor_backend_produse_dir();
    $importLib = $dir . '/import_lib.php';
    $lib = $dir . '/import_supplier_lib.php';
    $scanLib = $dir . '/import_supplier_stock_zero_lib.php';
    if (is_file($importLib)) {
        require_once $importLib;
    }
    if (is_file($lib)) {
        require_once $lib;
    }
    if (is_file($scanLib)) {
        require_once $scanLib;
    }

    if (class_exists(\Besoiu\Core\Supplier\SupplierHooks::class)) {
        try {
            \Besoiu\Core\Supplier\SupplierHooks::ensureHooks();
        } catch (Throwable) {
            // optional hooks
        }
    }

    $booted = true;
}

/** @return array<string, array<string, mixed>> slug => profil */
function import_motor_registered_suppliers(bool $refresh = false, bool $withScanRules = false): array
{
    static $cache = null;
    static $cacheLite = null;

    if (!$refresh) {
        if (!$withScanRules && is_array($cacheLite)) {
            return $cacheLite;
        }
        if ($withScanRules && is_array($cache)) {
            return $cache;
        }
    }

    import_motor_boot_admin_stack();

    $out = [];

    try {
        foreach (import_motor_load_furnizori_rows() as $row) {
            if (!is_array($row)) {
                continue;
            }
            $code = function_exists('import_furnizori_normalize_code')
                ? import_furnizori_normalize_code((string) ($row['code'] ?? ''))
                : strtoupper(trim((string) ($row['code'] ?? '')));
            if ($code === '') {
                continue;
            }

            $status = strtolower(trim((string) ($row['status'] ?? 'active')));
            if (in_array($status, ['deleted', 'blocked', 'inactive'], true)) {
                continue;
            }

            if ($withScanRules) {
                try {
                    import_motor_boot_furnizori_libs();
                    if (function_exists('import_furnizor_is_blocked') && import_furnizor_is_blocked($code)) {
                        continue;
                    }
                } catch (Throwable) {
                    // verificări opționale
                }
            }

            $slug = function_exists('import_furnizori_search_slug')
                ? import_furnizori_search_slug($code)
                : strtolower($code);
            $randomnId = (int) ($row['randomn_id'] ?? 0);
            $feedDir = import_motor_supplier_feed_dir($code, $randomnId);

            $markupPercent = (float) ($row['price_markup_value'] ?? 0);
            if ($withScanRules && function_exists('import_supplier_feed_markup_percent')) {
                try {
                    import_motor_boot_furnizori_libs();
                    $markupPercent = import_supplier_feed_markup_percent($code);
                } catch (Throwable) {
                    // păstrează valoarea din rând
                }
            }

            $scanRules = [];
            if ($withScanRules && function_exists('import_supplier_scan_rules_for')) {
                try {
                    import_motor_boot_furnizori_libs();
                    $scanRules = import_supplier_scan_rules_for($code);
                } catch (Throwable) {
                    $scanRules = [];
                }
            }

            $out[$slug] = [
                'slug' => $slug,
                'code' => $code,
                'name' => trim((string) ($row['name'] ?? $code)),
                'randomn_id' => $randomnId,
                'status' => $status,
                'price_markup_type' => (string) ($row['price_markup_type'] ?? 'percentage'),
                'price_markup_value' => (float) ($row['price_markup_value'] ?? 0),
                'price_markup_percent' => $markupPercent,
                'feed_dir' => $feedDir,
                'feed_folder_rel' => import_motor_feed_folder_rel($code, $randomnId),
                'scan_rules' => $scanRules,
                'scan_interval_minutes' => (int) ($row['scan_interval_minutes'] ?? 0),
                'has_column_config' => import_motor_supplier_has_json_config($slug),
            ];
        }
    } catch (Throwable) {
        $out = [];
    }

    if ($withScanRules) {
        $cache = $out;
    } else {
        $cacheLite = $out;
    }

    return $out;
}

function import_motor_supplier_has_json_config(string $slug): bool
{
    $path = IMPORT_CONFIG . DIRECTORY_SEPARATOR . 'suppliers.json';
    if (!is_file($path)) {
        return false;
    }
    $data = json_decode((string) file_get_contents($path), true);

    return is_array($data) && isset($data[$slug]) && is_array($data[$slug]);
}

function import_motor_feed_folder_service(): ?object
{
    if (!class_exists(\Besoiu\Core\Module\OptionalModuleBridge::class)) {
        return null;
    }
    try {
        $class = \Besoiu\Core\Module\OptionalModuleBridge::resolveClass('furnizori', 'Service\\SupplierFeedFolderService');
        if ($class === null) {
            return null;
        }

        return new $class();
    } catch (Throwable) {
        return null;
    }
}

function import_motor_supplier_feed_dir(string $code, int $randomnId): string
{
    $svc = import_motor_feed_folder_service();
    if ($svc !== null && method_exists($svc, 'folderPath')) {
        $dir = (string) $svc->folderPath($code, $randomnId);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        return $dir;
    }

    $slug = import_motor_supplier_feed_slug($code);
    $dir = import_motor_canonical_feed_base_dir() . DIRECTORY_SEPARATOR . $slug;
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    return $dir;
}

function import_motor_feed_folder_rel(string $code, int $randomnId): string
{
    $svc = import_motor_feed_folder_service();
    if ($svc !== null && method_exists($svc, 'folderRelative')) {
        return 'admin/' . ltrim((string) $svc->folderRelative($code, $randomnId), '/');
    }

    $slug = import_motor_supplier_feed_slug($code);

    return 'admin/storage/supplier_feeds/' . $slug;
}

/** @return array<string, mixed>|null */
function import_motor_supplier_by_slug(string $slug, bool $withScanRules = false): ?array
{
    $slug = strtolower(trim($slug));
    if ($slug === '') {
        return null;
    }

    return import_motor_registered_suppliers(false, $withScanRules)[$slug] ?? null;
}

function import_motor_supplier_is_allowed(string $slug): bool
{
    return import_motor_supplier_by_slug($slug) !== null;
}

function import_motor_supplier_code_from_slug(string $slug): string
{
    $profile = import_motor_supplier_by_slug($slug);

    return $profile !== null ? (string) ($profile['code'] ?? '') : '';
}

/** @return list<string> */
function import_motor_allowed_supplier_slugs(): array
{
    return array_keys(import_motor_registered_suppliers());
}

function import_motor_supplier_storage_dirs(string $slug): array
{
    $profile = import_motor_supplier_by_slug($slug);
    if ($profile === null) {
        return [];
    }

    $feedDir = (string) ($profile['feed_dir'] ?? '');
    if ($feedDir === '') {
        return [];
    }

    return [$feedDir];
}

function import_motor_apply_feed_price(mixed $rawPrice, string $supplierSlug): ?float
{
    import_motor_boot_furnizori_libs();
    $code = import_motor_supplier_code_from_slug($supplierSlug);
    if ($code === '' || !function_exists('import_supplier_parse_feed_price')) {
        return is_numeric($rawPrice) ? (float) $rawPrice : null;
    }

    return import_supplier_parse_feed_price($rawPrice, $code);
}

function import_motor_purchase_vat_percent(): float
{
    import_motor_boot_furnizori_libs();

    return function_exists('import_supplier_commercial_vat_percent')
        ? import_supplier_commercial_vat_percent()
        : 21.0;
}

function import_motor_card_supplier_code(array $card): string
{
    $candidates = [
        (string) ($card['sourceSupplier'] ?? ''),
        (string) ($card['supplier'] ?? ''),
    ];
    $sourceFile = (string) ($card['sourceFile'] ?? '');
    if ($sourceFile !== '' && str_contains($sourceFile, '/')) {
        $candidates[] = explode('/', $sourceFile, 2)[0];
    }

    foreach ($candidates as $candidate) {
        $candidate = trim($candidate);
        if ($candidate === '') {
            continue;
        }
        $fromSlug = import_motor_supplier_code_from_slug(strtolower($candidate));
        if ($fromSlug !== '') {
            return $fromSlug;
        }
        if (function_exists('import_supplier_normalize_code')) {
            $normalized = import_supplier_normalize_code($candidate);
            if ($normalized !== '') {
                return $normalized;
            }
        }
    }

    return '';
}

/**
 * Preț Import Pro: aceleași reguli ca profil furnizor (import_supplier_form_initial_purchase_prices).
 *
 * @param array<string, mixed> $card
 * @return array<string, mixed>
 */
function import_motor_normalize_card_purchase_prices(array $card): array
{
    import_motor_boot_furnizori_libs();
    if (!function_exists('import_supplier_apply_initial_prices_to_card')) {
        return $card;
    }

    return import_supplier_apply_initial_prices_to_card($card, import_motor_card_supplier_code($card));
}

/** @param list<array<string, mixed>> $cards @return list<array<string, mixed>> */
function import_motor_normalize_card_list(array $cards): array
{
    $out = [];
    foreach ($cards as $card) {
        $out[] = is_array($card) ? import_motor_normalize_card_purchase_prices($card) : $card;
    }

    return $out;
}

/**
 * @param array<string, mixed> $payload
 * @return array<string, mixed>
 */
function import_motor_apply_supplier_profile_to_payload(array $payload, string $supplierSlug): array
{
    $profile = import_motor_supplier_by_slug($supplierSlug, true);
    if ($profile === null) {
        $payload['products'] = [];
        if (isset($payload['summary']) && is_array($payload['summary'])) {
            $payload['summary']['total'] = 0;
        }
        $payload['supplier_rejected'] = true;
        $payload['supplier_reject_reason'] = 'Furnizor neînregistrat sau blocat în modulul Furnizori.';

        return $payload;
    }

    $code = (string) $profile['code'];
    $products = $payload['products'] ?? [];
    if (!is_array($products)) {
        return $payload;
    }

    import_motor_boot_furnizori_libs();
    $filtered = [];
    foreach ($products as $product) {
        if (!is_array($product)) {
            continue;
        }

        if (isset($product['price'])) {
            if (!isset($product['price_csv'])) {
                $product['price_csv'] = import_parse_supplier_price($product['price']);
            }
            if (function_exists('import_supplier_form_initial_purchase_prices')) {
                $formed = import_supplier_form_initial_purchase_prices($product['price_csv'] ?? $product['price'], $code);
                if ($formed['price_purchase_net'] !== null) {
                    $product['price'] = $formed['price_purchase_net'];
                    $product['price_feed_markup_applied'] = true;
                }
                $product['price_purchase_net'] = $formed['price_purchase_net'];
                $product['price_purchase_vat'] = $formed['price_purchase_vat'];
                $product['feed_markup_percent'] = $formed['feed_markup_percent'];
                $product['vat_rule'] = $formed['vat_rule'];
                $product['vat_percent'] = $formed['vat_percent'];
                $product['vat_extra_applied'] = $formed['vat_extra_applied'];
            } else {
                $adjusted = import_motor_apply_feed_price($product['price'], $supplierSlug);
                if ($adjusted !== null) {
                    $product['price'] = $adjusted;
                    $product['price_feed_markup_applied'] = true;
                }
            }
        }

        if (function_exists('import_supplier_row_passes_supplier_scan_rules')) {
            $row = [
                'STOC' => $product['stock'] ?? $product['stock_raw'] ?? '',
                'Stoc' => $product['stock'] ?? '',
                'stock' => $product['stock'] ?? '',
            ];
            if (!import_supplier_row_passes_supplier_scan_rules($row, $code)) {
                continue;
            }
        }

        $filtered[] = $product;
    }

    $payload['products'] = $filtered;
    if (isset($payload['summary']) && is_array($payload['summary'])) {
        $payload['summary']['total'] = count($filtered);
    }

    $payload['supplier_profile'] = [
        'slug' => $profile['slug'],
        'code' => $code,
        'name' => $profile['name'],
        'markup_percent' => function_exists('import_supplier_feed_markup_percent')
            ? import_supplier_feed_markup_percent($code)
            : (float) ($profile['price_markup_percent'] ?? 0),
        'import_vat_rule' => function_exists('import_supplier_vat_rule')
            ? import_supplier_vat_rule($code)
            : 'net_plus_tva',
        'feed_folder' => $profile['feed_folder_rel'],
        'scan_rules' => $profile['scan_rules'],
    ];

    return $payload;
}

/** @return array<int, array<string, mixed>> */
function import_motor_registered_suppliers_for_ui(): array
{
    $out = [];
    foreach (import_motor_registered_suppliers() as $slug => $profile) {
        $out[] = [
            'slug' => $slug,
            'code' => $profile['code'],
            'name' => $profile['name'],
            'feed_folder' => $profile['feed_folder_rel'],
            'markup_percent' => $profile['price_markup_percent'],
            'has_column_config' => $profile['has_column_config'],
            'scan_rules' => $profile['scan_rules'],
        ];
    }
    usort($out, static fn(array $a, array $b): int => strcmp((string) $a['name'], (string) $b['name']));

    return $out;
}

function import_motor_require_allowed_supplier(string $slug, bool $withScanRules = false): array
{
    $profile = import_motor_supplier_by_slug($slug, $withScanRules);
    if ($profile === null) {
        import_json_response([
            'success' => false,
            'error' => 'Furnizor invalid: trebuie înregistrat și activ în modulul Furnizori (Admin → Furnizori).',
            'code' => 'supplier_not_registered',
        ], 403);
    }

    return $profile;
}
