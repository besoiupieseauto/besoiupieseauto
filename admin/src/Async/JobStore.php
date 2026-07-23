<?php

declare(strict_types=1);

namespace Besoiu\Async;

/**
 * Persistență job: Redis hash + fallback fișier JSON per job.
 * Workerii și SSE citesc/scriu EXCLUSIV aici (nu comunică direct între ei).
 */
final class JobStore
{
    private ?\Redis $redis = null;
    private bool $useRedis = false;
    private string $prefix;

    public function __construct()
    {
        $this->prefix = (string) AsyncConfig::get('redis_prefix', 'besoiu:async:');
        $this->bootRedis();
    }

    /** @param array<string, mixed> $job */
    public function save(array $job): void
    {
        $id = (string) ($job['id'] ?? '');
        if ($id === '') {
            throw new \InvalidArgumentException('Job fără id.');
        }

        $job['updated_at'] = time();
        $encoded = json_encode($job, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($encoded)) {
            throw new \RuntimeException('Serializare job eșuată.');
        }

        if ($this->useRedis && $this->redis instanceof \Redis) {
            $this->redis->hSet($this->prefix . 'job:' . $id, 'data', $encoded);
            $this->redis->expire($this->prefix . 'job:' . $id, 86400 * 7);
        }

        $path = $this->filePath($id);
        file_put_contents($path, $encoded, LOCK_EX);
    }

    /** @return array<string, mixed>|null */
    public function get(string $jobId): ?array
    {
        if ($this->useRedis && $this->redis instanceof \Redis) {
            $raw = $this->redis->hGet($this->prefix . 'job:' . $jobId, 'data');
            if (is_string($raw) && $raw !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    return $decoded;
                }
            }
        }

        $path = $this->filePath($jobId);
        if (!is_file($path)) {
            return null;
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : null;
    }

    public function updateProgress(string $jobId, int $progress, ?string $message = null): void
    {
        $job = $this->get($jobId);
        if ($job === null) {
            return;
        }

        $job['progress'] = max(0, min(100, $progress));
        if ($message !== null) {
            $job['progress_message'] = $message;
        }

        $this->save($job);
        JobLogger::log('job_progress', ['job_id' => $jobId, 'progress' => $job['progress'], 'message' => $message]);
    }

    public function markProcessing(string $jobId): void
    {
        $job = $this->requireJob($jobId);
        $job['status'] = 'processing';
        $job['started_at'] = time();
        $job['worker_heartbeat_at'] = time();
        $job['worker_pid'] = getmypid();
        $this->save($job);
        JobLogger::log('job_processing', ['job_id' => $jobId]);
    }

    /** @param array<string, mixed>|null $result */
    public function markDone(string $jobId, ?array $result = null): void
    {
        $job = $this->requireJob($jobId);
        $job['status'] = 'done';
        $job['progress'] = 100;
        $job['result'] = $result;
        $job['completed_at'] = time();
        $this->save($job);
        JobLogger::log('job_done', ['job_id' => $jobId]);
    }

    public function markFailed(string $jobId, string $error): void
    {
        $job = $this->requireJob($jobId);
        $job['status'] = 'failed';
        $job['error'] = $error;
        $job['failed_at'] = time();
        $this->save($job);
        JobLogger::log('job_failed', ['job_id' => $jobId, 'error' => $error]);
    }

    public function isTimedOut(array $job): bool
    {
        $timeoutAt = (int) ($job['timeout_at'] ?? 0);
        if ($timeoutAt > 0 && time() > $timeoutAt) {
            return true;
        }

        if (($job['status'] ?? '') === 'processing') {
            $started = (int) ($job['started_at'] ?? 0);
            $limit = (int) ($job['timeout_sec'] ?? AsyncConfig::get('default_job_timeout_sec', 60));
            if ($started > 0 && (time() - $started) > $limit) {
                return true;
            }

            $heartbeatAt = (int) ($job['worker_heartbeat_at'] ?? 0);
            $heartbeatSec = (int) AsyncConfig::get('worker_heartbeat_sec', 15);
            if ($heartbeatAt > 0 && $heartbeatSec > 0 && (time() - $heartbeatAt) > ($heartbeatSec * 2)) {
                return true;
            }
        }

        return false;
    }

    public function isPendingTimedOut(array $job): bool
    {
        if (($job['status'] ?? '') !== 'pending') {
            return false;
        }

        $timeoutAt = (int) ($job['timeout_at'] ?? 0);

        return $timeoutAt > 0 && time() > $timeoutAt;
    }

    public function touchHeartbeat(string $jobId, string $consumer): void
    {
        $job = $this->get($jobId);
        if ($job === null) {
            return;
        }

        $job['worker_heartbeat_at'] = time();
        $job['worker_pid'] = getmypid();
        $job['worker_consumer'] = $consumer;
        $this->save($job);
    }

    /** @return list<string> */
    public function listAllJobIds(): array
    {
        $ids = [];

        $dir = AsyncConfig::storageDir() . '/jobs';
        if (is_dir($dir)) {
            foreach (glob($dir . '/*.json') ?: [] as $path) {
                if (!is_file($path)) {
                    continue;
                }
                $decoded = json_decode((string) file_get_contents($path), true);
                if (is_array($decoded) && isset($decoded['id'])) {
                    $ids[(string) $decoded['id']] = true;
                }
            }
        }

        if ($this->useRedis && $this->redis instanceof \Redis) {
            $pattern = $this->prefix . 'job:*';
            $keys = $this->redis->keys($pattern);
            if (is_array($keys)) {
                foreach ($keys as $key) {
                    $suffix = substr((string) $key, strlen($this->prefix . 'job:'));
                    if ($suffix !== '') {
                        $ids[$suffix] = true;
                    }
                }
            }
        }

        return array_keys($ids);
    }

    public function requestCancel(string $jobId): void
    {
        $job = $this->get($jobId);
        if ($job === null) {
            return;
        }

        $job['cancel_requested'] = true;
        $job['cancel_requested_at'] = time();
        $this->save($job);
    }

    public function isCancelRequested(string $jobId): bool
    {
        $job = $this->get($jobId);
        if ($job === null) {
            return false;
        }

        return !empty($job['cancel_requested']);
    }

    /** @return array<string, mixed> */
    private function requireJob(string $jobId): array
    {
        $job = $this->get($jobId);
        if ($job === null) {
            throw new \RuntimeException('Job inexistent: ' . $jobId);
        }

        return $job;
    }

    private function filePath(string $jobId): string
    {
        $safe = preg_replace('/[^a-zA-Z0-9-]/', '', $jobId) ?: md5($jobId);
        $dir = AsyncConfig::storageDir() . '/jobs';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        return $dir . '/' . $safe . '.json';
    }

    private function bootRedis(): void
    {
        if (!class_exists(\Redis::class)) {
            return;
        }

        try {
            $redis = new \Redis();
            $connected = $redis->connect(
                (string) AsyncConfig::get('redis_host', '127.0.0.1'),
                (int) AsyncConfig::get('redis_port', 6379),
                (float) AsyncConfig::get('redis_timeout', 2.0)
            );
            if ($connected) {
                $this->redis = $redis;
                $this->useRedis = true;
            }
        } catch (\Throwable) {
            $this->useRedis = false;
        }
    }
}
