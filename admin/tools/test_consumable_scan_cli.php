<?php

declare(strict_types=1);

define('IMPORT_PRODUCE_SKIP_HTTP', true);

$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['CONTENT_TYPE'] = 'application/json';

require dirname(__DIR__) . '/vendor/autoload.php';
Dotenv\Dotenv::createImmutable(dirname(__DIR__))->safeLoad();
$config = require dirname(__DIR__) . '/config/config.php';
Config\Database::getInstance($config['db_host'], $config['db_name'], $config['db_user'], $config['db_pass']);

$feed = dirname(__DIR__) . '/storage/supplier_feeds/autototal/test_cron_import.csv';
if (!is_file($feed)) {
    require __DIR__ . '/seed_cron_test_csv.php';
}

$importsDir = dirname(__DIR__) . '/storage/imports';
if (!is_dir($importsDir)) {
    mkdir($importsDir, 0775, true);
}

$fileId = 'cli_consumable_' . date('YmdHis');
$dest = $importsDir . '/' . preg_replace('/[^A-Za-z0-9_-]/', '_', $fileId) . '.part';
copy($feed, $dest);
file_put_contents($dest . '.json', json_encode([
    'file_id' => $fileId,
    'original_name' => 'test_consumable.csv',
    'completed' => true,
    'total_chunks' => 1,
    'last_chunk' => 0,
    'upload_role' => 'supplier',
    'file_kind' => 'supplier:AUTOTOTAL',
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

$filesMeta = [[
    'file_id' => $fileId,
    'original_name' => 'test_consumable.csv',
    'file_kind' => 'supplier:AUTOTOTAL',
    'upload_role' => 'supplier',
]];

require_once dirname(__DIR__) . '/src/Controllers/Produse/import_uploaded_files_lib.php';
require_once dirname(__DIR__) . '/src/Controllers/Produse/import_lib.php';
require_once dirname(__DIR__) . '/src/Controllers/Produse/import_supplier_lib.php';
require_once dirname(__DIR__) . '/src/Controllers/Produse/import_identity_lib.php';
require_once dirname(__DIR__) . '/src/Controllers/Produse/import_consumable_scan_lib.php';
require_once dirname(__DIR__) . '/src/Controllers/AdaosComercial/AdaosComercialService.php';
require_once dirname(__DIR__, 2) . '/system/tecdoc_stock.php';

$preview = import_consumable_scan_preview($filesMeta, [
    'categories' => ['ulei', 'lichide', 'electrice'],
    'max_preview' => 50,
]);

echo "=== PREVIEW ===\n";
echo json_encode([
    'success' => $preview['success'] ?? false,
    'message' => $preview['message'] ?? '',
    'count' => count($preview['products'] ?? []),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

if (empty($preview['products'])) {
    exit(1);
}

$pdo = Config\Database::getDB();
$publish = import_consumable_scan_publish($pdo, $preview['products'], [
    'limit' => 10,
    'check_epiesa' => false,
]);

echo "\n=== PUBLISH ===\n";
echo json_encode([
    'success' => $publish['success'] ?? false,
    'message' => $publish['message'] ?? '',
    'stats' => $publish['stats'] ?? [],
    'log_lines' => count($publish['log'] ?? []),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
