<?php
declare(strict_types=1);

/**
 * Oprește job-uri refresh_images blocate (running fără heartbeat recent).
 * php app/Backend/tools/cancel_stuck_refresh_image_jobs.php
 */

$root = dirname(__DIR__, 3);
require $root . '/admin/bootstrap.php';
require BESOIU_BACKEND . '/vendor/autoload.php';

if (!defined('IMPORT_PRODUCE_SKIP_HTTP')) {
    define('IMPORT_PRODUCE_SKIP_HTTP', true);
}
if (!defined('IMPORT_ACTION_SKIP_HTTP')) {
    define('IMPORT_ACTION_SKIP_HTTP', true);
}

\Besoiu\Services\Import\ImportLibLoader::bootForQueueActions();

$dir = import_jobs_dir();
$n = 0;
foreach (glob($dir . '/refresh_images_*.json') ?: [] as $file) {
    if (str_contains($file, '.state.')) {
        continue;
    }
    $meta = json_decode((string) file_get_contents($file), true);
    if (!is_array($meta)) {
        continue;
    }
    $status = (string) ($meta['status'] ?? '');
    if (!in_array($status, ['running', 'pending'], true)) {
        continue;
    }
    $jobId = (string) ($meta['job_id'] ?? basename($file, '.json'));
    $updated = strtotime((string) ($meta['updated_at'] ?? '')) ?: 0;
    $age = time() - $updated;
    if ($age < 45 && $status === 'running') {
        continue;
    }
    import_job_cancel($jobId);
    import_job_update($jobId, [
        'status' => 'error',
        'error' => 'Anulat: blocat pe plan (ex. ePiesa stealth). Repornește scanarea.',
        'message' => 'Anulat: blocat pe plan (ex. ePiesa stealth). Repornește scanarea.',
    ]);
    ++$n;
    echo "cancelled {$jobId} age={$age}s msg=" . ($meta['message'] ?? '') . PHP_EOL;
}
echo "Done cancelled={$n}\n";
