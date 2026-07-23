<?php

declare(strict_types=1);

namespace Besoiu\Async;

/**
 * Orchestrare: creează job (202), enqueue, citește status pentru SSE.
 */
final class JobService
{
    public function __construct(
        private readonly JobStore $store = new JobStore(),
        private readonly JobQueue $queue = new JobQueue(),
        private readonly IdempotencyStore $idempotency = new IdempotencyStore(),
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{job_id:string,status:string,replayed:bool}
     */
    public function create(
        string $type,
        string $queue,
        array $payload,
        string $priority = 'normal',
        ?string $idempotencyKey = null,
        ?int $timeoutSec = null
    ): array {
        if ($idempotencyKey !== null && $idempotencyKey !== '') {
            return $this->idempotency->rememberOrGetExisting($idempotencyKey, function () use (
                $type,
                $queue,
                $payload,
                $priority,
                $timeoutSec
            ): array {
                return $this->createJobRecord($type, $queue, $payload, $priority, $timeoutSec);
            });
        }

        $created = $this->createJobRecord($type, $queue, $payload, $priority, $timeoutSec);

        return array_merge($created, ['replayed' => false]);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{job_id:string,status:string}
     */
    private function createJobRecord(
        string $type,
        string $queue,
        array $payload,
        string $priority,
        ?int $timeoutSec
    ): array {
        $jobId = $this->uuidV4();
        $timeout = $timeoutSec ?? $this->queueTimeout($queue);
        $now = time();

        $job = [
            'id' => $jobId,
            'type' => $type,
            'queue' => $queue,
            'status' => 'pending',
            'progress' => 0,
            'result' => null,
            'error' => null,
            'payload' => $payload,
            'priority' => $priority,
            'created_at' => $now,
            'updated_at' => $now,
            'timeout_at' => $now + $timeout,
            'timeout_sec' => $timeout,
            'started_at' => null,
            'completed_at' => null,
            'failed_at' => null,
            'attempt' => 1,
            'max_attempts' => (int) AsyncConfig::get('job_max_attempts', 3),
        ];

        $this->store->save($job);
        $this->queue->push($queue, [
            'job_id' => $jobId,
            'type' => $type,
            'queue' => $queue,
        ], $priority);

        JobLogger::log('job_created', ['job_id' => $jobId, 'type' => $type, 'queue' => $queue, 'priority' => $priority]);

        return ['job_id' => $jobId, 'status' => 'pending'];
    }

    /** @return array<string, mixed>|null */
    public function getPublicSnapshot(string $jobId): ?array
    {
        $job = $this->store->get($jobId);
        if ($job === null) {
            return null;
        }

        $status = (string) ($job['status'] ?? 'pending');

        if ($status === 'processing' && $this->store->isTimedOut($job)) {
            $heartbeatAt = (int) ($job['worker_heartbeat_at'] ?? 0);
            $heartbeatSec = (int) AsyncConfig::get('worker_heartbeat_sec', 15);
            $error = ($heartbeatAt > 0 && $heartbeatSec > 0 && (time() - $heartbeatAt) > ($heartbeatSec * 2))
                ? 'worker_lost'
                : 'timeout';
            $this->store->markFailed($jobId, $error);
            $job = $this->store->get($jobId);
        } elseif ($status === 'pending' && $this->store->isPendingTimedOut($job)) {
            $this->store->markFailed($jobId, 'timeout_pending_no_worker');
            $job = $this->store->get($jobId);
        }

        return [
            'job_id' => $job['id'] ?? $jobId,
            'status' => $job['status'] ?? 'pending',
            'progress' => (int) ($job['progress'] ?? 0),
            'message' => $job['progress_message'] ?? null,
            'live_log' => is_array($job['live_log'] ?? null) ? $job['live_log'] : [],
            'result' => $job['result'] ?? null,
            'error' => $job['error'] ?? null,
            'created_at' => $job['created_at'] ?? null,
            'updated_at' => $job['updated_at'] ?? null,
            'timeout_at' => $job['timeout_at'] ?? null,
            'legacy_job_id' => $job['legacy_job_id'] ?? null,
        ];
    }

    private function queueTimeout(string $queue): int
    {
        /** @var array<string, array{max_workers:int,timeout_sec:int}> $queues */
        $queues = AsyncConfig::get('queues', []);

        return (int) ($queues[$queue]['timeout_sec'] ?? AsyncConfig::get('default_job_timeout_sec', 60));
    }

    private function uuidV4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12)
        );
    }
}
