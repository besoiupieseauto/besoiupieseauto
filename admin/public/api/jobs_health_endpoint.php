<?php
declare(strict_types=1);

/**
 * Healthcheck Besoiu Async — JSON pentru monitoring / dashboard.
 * GET /admin/api/jobs_health_endpoint.php
 */

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use Besoiu\Async\AsyncConfig;
use Besoiu\Async\JobQueue;
use Besoiu\Async\JobStore;

header('Content-Type: application/json; charset=utf-8');

$queue = new JobQueue();
$store = new JobStore();
$redisOk = $queue->isRedisActive();
$redisStreamsOk = false;
$fileFallback = $queue->isFileFallbackActive();

$workersActive = [];
/** @var array<string, array{max_workers:int,timeout_sec:int}> $queues */
$queues = AsyncConfig::get('queues', []);
foreach (array_keys($queues) as $queueName) {
    $counts = $queue->countActiveWorkers($queueName);
    $workersActive[$queueName] = $counts['active'];
    if ($redisOk) {
        $redisStreamsOk = true;
    }
}

$pending = 0;
$processing = 0;
$oldestPendingAge = 0;
$now = time();

foreach ($store->listAllJobIds() as $jobId) {
    $job = $store->get($jobId);
    if ($job === null) {
        continue;
    }
    $status = (string) ($job['status'] ?? '');
    if ($status === 'pending') {
        ++$pending;
        $created = (int) ($job['created_at'] ?? 0);
        if ($created > 0) {
            $age = $now - $created;
            if ($age > $oldestPendingAge) {
                $oldestPendingAge = $age;
            }
        }
    } elseif ($status === 'processing') {
        ++$processing;
    }
}

$statusLabel = 'ok';
if (!$redisOk && !$fileFallback) {
    $statusLabel = 'down';
} elseif (!$redisOk || $processing > 20 || $oldestPendingAge > 300) {
    $statusLabel = 'degraded';
}

echo json_encode([
    'success' => true,
    'status' => $statusLabel,
    'redis_ok' => $redisOk,
    'redis_streams_ok' => $redisStreamsOk,
    'file_fallback_active' => $fileFallback,
    'workers_active' => $workersActive,
    'pending_jobs_count' => $pending,
    'processing_jobs_count' => $processing,
    'oldest_pending_age_sec' => $oldestPendingAge,
    'checked_at' => date('c'),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
