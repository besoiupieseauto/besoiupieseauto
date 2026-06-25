<?php
declare(strict_types=1);

/**
 * Test rulare scan + import cron (max 10 produse).
 *
 *   php admin/tools/test_cron_import_cli.php
 */
require dirname(__DIR__) . '/vendor/autoload.php';

Dotenv\Dotenv::createImmutable(dirname(__DIR__))->safeLoad();
$config = require dirname(__DIR__) . '/config/config.php';

\Config\Database::getInstance(
    (string) $config['db_host'],
    (string) $config['db_name'],
    (string) $config['db_user'],
    (string) $config['db_pass']
);

require_once dirname(__DIR__) . '/src/Controllers/Scan/ScanService.php';

$result = (new Evasystem\Controllers\Scan\ScanService())->runFullSync(false);

echo json_encode([
    'success' => $result['success'] ?? false,
    'message' => $result['message'] ?? '',
    'import' => $result['import_summary'] ?? ($result['data']['import_summary'] ?? []),
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
