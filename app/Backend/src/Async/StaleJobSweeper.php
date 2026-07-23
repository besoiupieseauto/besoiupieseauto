<?php

declare(strict_types=1);

namespace Besoiu\Async;

/**
 * Marchează joburi blocate (processing/pending) ca failed — rulat din cron la 60s.
 */
final class StaleJobSweeper
{
    public function __construct(
        private readonly JobStore $store = new JobStore(),
    ) {
    }

    /**
     * @return list<array{job_id:string,status:string,reason:string}>
     */
    public function sweep(bool $dryRun = false): array
    {
        $swept = [];

        foreach ($this->store->listAllJobIds() as $jobId) {
            $job = $this->store->get($jobId);
            if ($job === null) {
                continue;
            }

            $status = (string) ($job['status'] ?? '');
            $reason = null;

            if ($status === 'processing' && $this->store->isTimedOut($job)) {
                $heartbeatAt = (int) ($job['worker_heartbeat_at'] ?? 0);
                $heartbeatSec = (int) AsyncConfig::get('worker_heartbeat_sec', 15);
                if ($heartbeatAt > 0 && $heartbeatSec > 0 && (time() - $heartbeatAt) > ($heartbeatSec * 2)) {
                    $reason = 'worker_lost';
                } else {
                    $reason = 'timeout';
                }
            } elseif ($status === 'pending' && $this->store->isPendingTimedOut($job)) {
                $reason = 'timeout_pending_no_worker';
            }

            if ($reason === null) {
                continue;
            }

            if (!$dryRun) {
                $this->store->markFailed($jobId, $reason);
                JobLogger::log('stale_job_swept', [
                    'job_id' => $jobId,
                    'status' => $status,
                    'reason' => $reason,
                ]);
            }

            $swept[] = [
                'job_id' => $jobId,
                'status' => $status,
                'reason' => $reason,
            ];
        }

        return $swept;
    }
}
