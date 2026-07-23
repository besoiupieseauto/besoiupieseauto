<?php
declare(strict_types=1);

/**
 * Worker CLI — procesează un produs din coada import (scanare imagine).
 * Usage: php app/Backend/tools/import_image_worker_cli.php {import_id} [force=0|1]
 */
$importId = (int) ($argv[1] ?? 0);
$force = !empty($argv[2]) && (string) $argv[2] !== '0';

if ($importId <= 0) {
    fwrite(STDERR, "Usage: php import_image_worker_cli.php {import_id} [force]\n");
    exit(2);
}

$root = dirname(__DIR__, 3);
require $root . '/admin/bootstrap.php';
require BESOIU_BACKEND . '/vendor/autoload.php';

$envDir = is_file($root . '/.env') ? $root : BESOIU_CONFIG;
if (class_exists(\Dotenv\Dotenv::class) && is_file($envDir . '/.env')) {
    \Dotenv\Dotenv::createImmutable($envDir)->safeLoad();
}

require_once BESOIU_LEGACY . '/shop-db.php';

$config = require BESOIU_CONFIG . '/config.php';
\Config\Database::getInstance(
    (string) ($config['db_host'] ?? '127.0.0.1'),
    (string) ($config['db_name'] ?? 'besoiupieseauto.ro'),
    (string) ($config['db_user'] ?? 'root'),
    (string) ($config['db_pass'] ?? '')
);

use Besoiu\Services\Import\ImportLibLoader;
use Config\Database;

ImportLibLoader::bootFull(true);

$pdo = Database::getDB();
$boot = import_image_pipeline_boot();
if (empty($boot['ok'])) {
    echo json_encode([
        'import_id' => $importId,
        'updated' => 0,
        'failed' => 1,
        'kept' => 0,
        'scanned' => 0,
        'errors' => [(string) ($boot['message'] ?? 'Boot pipeline eșuat')],
        'stop_early' => false,
        'api_status' => 'error',
        'code' => '#' . $importId,
    ], JSON_UNESCAPED_UNICODE);
    exit(1);
}

$state = ['force' => $force, 'api_status' => 'ok'];
$delta = import_image_job_process_one($pdo, $importId, $state);
$delta['import_id'] = $importId;

echo json_encode($delta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit(!empty($delta['updated']) || !empty($delta['kept']) ? 0 : 1);
