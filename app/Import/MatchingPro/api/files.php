<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    import_json_response(['success' => false, 'error' => 'GET obligatoriu'], 405);
}

try {
    $includeEmpty = filter_var($_GET['empty'] ?? false, FILTER_VALIDATE_BOOLEAN);
    $migration = import_motor_migrate_legacy_supplier_files();
    $registered = import_motor_registered_suppliers_for_ui();
    $basePath = 'admin/storage/supplier_feeds/';

    import_json_response([
        'success' => true,
        'suppliers' => import_all_supplier_dirs(),
        'registered_suppliers' => $registered,
        'files' => import_list_supplier_files(),
        'folders' => import_list_supplier_folders($includeEmpty),
        'base_path' => $basePath,
        'storage_root' => import_motor_canonical_feed_base_dir(),
        'legacy_migration' => $migration,
        'furnizori_module_required' => true,
        'db_ready' => import_motor_ensure_database(),
        'cron' => import_cron_status(),
    ]);
} catch (Throwable $e) {
    import_json_response([
        'success' => false,
        'error' => 'files.php: ' . $e->getMessage(),
        'registered_suppliers' => [],
        'files' => [],
        'folders' => [],
    ], 500);
}
