<?php
declare(strict_types=1);

/**
 * Reset panou Cron Sync — oprește scanări, golește jurnal, statistici la zero.
 *
 *   php admin/tools/reset_cron_scan_cli.php
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

use Evasystem\Controllers\Scan\ScanService;

$result = (new ScanService())->resetForTesting();

echo 'success=' . ($result['success'] ? 'yes' : 'no') . PHP_EOL;
echo 'message=' . ($result['message'] ?? '') . PHP_EOL;
echo 'feeds_deleted=' . (int) ($result['feeds_deleted'] ?? 0) . PHP_EOL;
echo 'cancelled_jobs=' . (int) ($result['cancelled_jobs'] ?? 0) . PHP_EOL;
echo 'queue_cleared=' . (int) ($result['queue_cleared'] ?? 0) . PHP_EOL;
echo 'overview=' . json_encode($result['overview'] ?? [], JSON_UNESCAPED_UNICODE) . PHP_EOL;
