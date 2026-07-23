<?php
require 'f:/laragon/www/besoiupieseauto.ro/admin/bootstrap.php';
require BESOIU_BACKEND . '/vendor/autoload.php';
require_once BESOIU_LEGACY . '/shop-db.php';
$c = require BESOIU_CONFIG . '/config.php';
\Config\Database::getInstance($c['db_host'], $c['db_name'], $c['db_user'], $c['db_pass']);
use Besoiu\Services\Import\ImportLibLoader;
use Config\Database;
ImportLibLoader::bootFull(true);
$pdo = Database::getDB();

$php = import_image_job_php_binary();
echo "php_binary=$php\n";
echo "is_php=" . (str_contains(strtolower(basename($php)), 'php') ? 'yes' : 'no') . "\n";

$importId = (int) ($argv[1] ?? 10);
echo "\n=== Spawn + scan test product #$importId ===\n";
$start = import_image_job_start($pdo, [$importId], '', true);
echo 'job=' . ($start['job_id'] ?? '') . ' spawned=' . (!empty($start['spawned']) ? 'yes' : 'no') . "\n";

$jobId = (string) ($start['job_id'] ?? '');
$bat = import_jobs_dir() . '/' . $jobId . '.runner.bat';
if (is_file($bat)) {
    echo "bat_first_line:\n" . strtok(file_get_contents($bat), "\n") . "\n";
    echo "bat_uses_php=" . (str_contains(file_get_contents($bat), 'php.exe') ? 'yes' : 'NO') . "\n";
}

$deadline = time() + 240;
while (time() < $deadline) {
    $meta = import_job_load_meta($jobId);
    if ($meta === null) break;
    $st = (string) ($meta['status'] ?? '');
    echo date('H:i:s') . " status=$st progress=" . ($meta['progress'] ?? 0) . " logs=" . count($meta['live_log'] ?? []) . "\n";
    if ($st === 'done' || $st === 'error') {
        $r = is_array($meta['result'] ?? null) ? $meta['result'] : [];
        echo 'RESULT updated=' . ($r['updated'] ?? 0) . ' failed=' . ($r['failed'] ?? 0) . "\n";
        exit((int) ($r['updated'] ?? 0) > 0 ? 0 : 4);
    }
    sleep(4);
}
echo "TIMEOUT\n";
exit(5);
