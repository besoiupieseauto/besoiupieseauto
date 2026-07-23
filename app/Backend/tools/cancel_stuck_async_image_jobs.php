<?php
declare(strict_types=1);

/**
 * Marchează job-uri import.refresh_images blocate în pending (fără worker).
 * php app/Backend/tools/cancel_stuck_async_image_jobs.php
 */

$root = dirname(__DIR__, 3);
require $root . '/admin/bootstrap.php';
require BESOIU_BACKEND . '/vendor/autoload.php';

use Besoiu\Async\JobStore;

$store = new JobStore();
$now = time();
$cancelled = 0;

foreach ($store->listAllJobIds() as $jobId) {
    $job = $store->get($jobId);
    if (!is_array($job)) {
        continue;
    }
    if (($job['type'] ?? '') !== 'import.refresh_images') {
        continue;
    }
    if (($job['status'] ?? '') !== 'pending') {
        continue;
    }
    $age = $now - (int) ($job['created_at'] ?? $now);
    if ($age < 20) {
        continue;
    }
    $store->requestCancel($jobId);
    $job = $store->get($jobId) ?? $job;
    $job['status'] = 'failed';
    $job['error'] = 'cancelled_no_images_worker';
    $job['failed_at'] = $now;
    $job['updated_at'] = $now;
    $store->save($job);
    ++$cancelled;
    echo "cancelled {$jobId} (age={$age}s)\n";
}

echo "Done: cancelled={$cancelled}\n";
