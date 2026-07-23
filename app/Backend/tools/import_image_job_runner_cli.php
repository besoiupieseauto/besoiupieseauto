<?php
declare(strict_types=1);

/**
 * Runner CLI — procesează un job refresh_images pas cu pas (fără HTTP).
 * Usage: php app/Backend/tools/import_image_job_runner_cli.php {job_id}
 */
$jobId = preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($argv[1] ?? '')) ?: '';

if ($jobId === '') {
    fwrite(STDERR, "Usage: php import_image_job_runner_cli.php {job_id}\n");
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

if (!function_exists('import_job_load_meta') || !function_exists('import_image_job_step')) {
    fwrite(STDERR, "import job libs unavailable\n");
    exit(3);
}

$meta = import_job_load_meta($jobId);
if ($meta === null) {
    fwrite(STDERR, "job not found: $jobId\n");
    exit(4);
}

if ((string) ($meta['status'] ?? '') === 'done') {
    exit(0);
}

import_job_update($jobId, [
    'runner_pid' => getmypid(),
    'runner_started_at' => date('c'),
    'runner_heartbeat_at' => date('c'),
]);

if (function_exists('import_image_job_push_log')) {
    import_image_job_push_log($jobId, 'Runner CLI pornit (PID ' . getmypid() . ') — procesez job-ul în fundal.', 'info');
}

@set_time_limit(0);
@ini_set('max_execution_time', '0');

$maxIterations = 600;
for ($i = 0; $i < $maxIterations; ++$i) {
    if (import_job_is_cancelled($jobId)) {
        exit(0);
    }

    import_job_update($jobId, [
        'runner_heartbeat_at' => date('c'),
    ]);

    $step = import_image_job_step($pdo, $jobId);
    if (empty($step['ok'])) {
        import_job_update($jobId, [
            'status' => 'error',
            'error' => (string) ($step['error'] ?? 'Eroare runner CLI.'),
        ]);
        exit(1);
    }

    if (!empty($step['cancelled'])) {
        exit(0);
    }

    $status = is_array($step['status'] ?? null) ? $step['status'] : [];
    if (!empty($status['done']) || !empty($status['failed'])) {
        exit(!empty($status['failed']) ? 1 : 0);
    }

    usleep(150000);
}

import_job_update($jobId, [
    'status' => 'error',
    'error' => 'Runner CLI: limită iterații atinsă.',
]);
exit(1);
