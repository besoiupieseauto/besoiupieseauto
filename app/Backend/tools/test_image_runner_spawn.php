<?php
declare(strict_types=1);

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

$importId = (int) ($argv[1] ?? 11);
echo "=== Test spawn runner CLI ===\n";

$start = import_image_job_start($pdo, [$importId], '', true);
if (empty($start['ok'])) {
    echo 'START FAIL: ' . ($start['message'] ?? '') . "\n";
    exit(2);
}

$jobId = (string) ($start['job_id'] ?? '');
echo "Job: $jobId spawned=" . (!empty($start['spawned']) ? 'yes' : 'no') . "\n";

$deadline = time() + 240;
while (time() < $deadline) {
    $meta = import_job_load_meta($jobId);
    if ($meta === null) {
        break;
    }
    $status = (string) ($meta['status'] ?? '');
    $active = import_image_job_runner_is_active($jobId) ? 'active' : 'idle';
    $logCount = count(is_array($meta['live_log'] ?? null) ? $meta['live_log'] : []);
    echo date('H:i:s') . " status=$status runner=$active progress=" . ($meta['progress'] ?? 0) . " log=$logCount msg=" . ($meta['message'] ?? '') . "\n";
    if ($status === 'done' || $status === 'error') {
        $result = is_array($meta['result'] ?? null) ? $meta['result'] : [];
        echo "RESULT updated=" . ($result['updated'] ?? 0) . " failed=" . ($result['failed'] ?? 0) . "\n";
        exit((int) ($result['updated'] ?? 0) > 0 ? 0 : 4);
    }
    sleep(3);
}

echo "TIMEOUT waiting for job\n";
exit(5);
