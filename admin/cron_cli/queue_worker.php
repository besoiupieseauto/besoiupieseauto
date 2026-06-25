<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/system/JobQueue.php';

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();
$config = require dirname(__DIR__) . '/config/config.php';

\Config\Database::getInstance(
    $config['db_host'],
    $config['db_name'],
    $config['db_user'],
    $config['db_pass']
);

$lockPath = dirname(__DIR__) . '/storage/queue_worker_running.lock';
$lockHandle = fopen($lockPath, 'c+');
if ($lockHandle === false || !flock($lockHandle, LOCK_EX | LOCK_NB)) {
    echo "Queue worker already running — skip.\n";
    exit(0);
}

register_shutdown_function(static function () use ($lockHandle, $lockPath): void {
    if (is_resource($lockHandle)) {
        flock($lockHandle, LOCK_UN);
        fclose($lockHandle);
    }
    @unlink($lockPath);
});

$pdo = \Config\Database::getDB();
$queue = new JobQueue($pdo, 'default');
$maxJobs = max(1, (int) ($argv[1] ?? 20));

for ($i = 0; $i < $maxJobs; $i++) {
    $job = $queue->pop();
    if ($job === null) {
        break;
    }

    $jobId = (int) ($job['id'] ?? 0);
    $jobType = (string) ($job['job_type'] ?? '');
    $payload = is_array($job['payload'] ?? null) ? $job['payload'] : [];

    try {
        switch ($jobType) {
            case 'baselinker_sync_order':
                require_once __DIR__ . '/baselinker_sync.php';
                baselinker_sync_order($pdo, $payload);
                break;
            case 'baselinker_sync_products':
                require_once __DIR__ . '/baselinker_sync.php';
                baselinker_sync_products($pdo, $payload);
                break;
            case 'baselinker_feed_regenerate':
                require_once __DIR__ . '/baselinker_feed_regen.php';
                baselinker_feed_regen_job($pdo, $payload);
                break;
            default:
                throw new RuntimeException('Tip job necunoscut: ' . $jobType);
        }

        if ($jobId > 0) {
            $queue->markDone($jobId);
        }
        echo "OK job #{$jobId} ({$jobType})\n";
    } catch (Throwable $exception) {
        if ($jobId > 0) {
            $queue->markFailed($jobId, $exception->getMessage());
        }
        if (is_file(dirname(__DIR__, 2) . '/system/system_errors.php')) {
            require_once dirname(__DIR__, 2) . '/system/system_errors.php';
            besoiu_system_error_log('error', 'cron', $exception->getMessage(), [
                'job_id' => $jobId,
                'job_type' => $jobType,
            ]);
        }
        echo "FAIL job #{$jobId}: " . $exception->getMessage() . "\n";
    }
}

echo "Queue worker finished.\n";
