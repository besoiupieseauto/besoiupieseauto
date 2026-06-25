<?php
declare(strict_types=1);

final class JobQueue
{
    private PDO $pdo;
    private string $queue;

    public function __construct(PDO $pdo, string $queue = 'default')
    {
        $this->pdo = $pdo;
        $this->queue = $queue;
    }

    /** @param array<string, mixed> $payload */
    public function push(string $jobType, array $payload, int $delaySeconds = 0): int
    {
        $this->ensureTable();

        $availableAt = date('Y-m-d H:i:s', time() + max(0, $delaySeconds));
        $stmt = $this->pdo->prepare(
            'INSERT INTO queue_jobs (queue, job_type, payload_json, available_at)
             VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([
            $this->queue,
            $jobType,
            json_encode($payload, JSON_UNESCAPED_UNICODE),
            $availableAt,
        ]);

        $id = (int) $this->pdo->lastInsertId();
        $this->pushRedis($jobType, $payload, $id);

        return $id;
    }

    /** @return array<string, mixed>|null */
    public function pop(): ?array
    {
        $this->ensureTable();

        $this->pdo->beginTransaction();
        $stmt = $this->pdo->prepare(
            'SELECT * FROM queue_jobs
             WHERE queue = ? AND status = \'pending\' AND available_at <= NOW()
             ORDER BY id ASC
             LIMIT 1
             FOR UPDATE'
        );
        $stmt->execute([$this->queue]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            $this->pdo->commit();

            return $this->popRedis();
        }

        $upd = $this->pdo->prepare(
            'UPDATE queue_jobs SET status = \'processing\', reserved_at = NOW(), attempts = attempts + 1 WHERE id = ?'
        );
        $upd->execute([(int) $row['id']]);
        $this->pdo->commit();

        $payload = json_decode((string) ($row['payload_json'] ?? '{}'), true);

        return [
            'id' => (int) $row['id'],
            'job_type' => (string) $row['job_type'],
            'payload' => is_array($payload) ? $payload : [],
            'attempts' => (int) ($row['attempts'] ?? 0) + 1,
        ];
    }

    public function markDone(int $jobId): void
    {
        $stmt = $this->pdo->prepare('UPDATE queue_jobs SET status = \'done\' WHERE id = ?');
        $stmt->execute([$jobId]);
    }

    public function markFailed(int $jobId, string $error): void
    {
        $stmt = $this->pdo->prepare('UPDATE queue_jobs SET status = \'failed\', last_error = ? WHERE id = ?');
        $stmt->execute([mb_substr($error, 0, 500), $jobId]);
        if (is_file(__DIR__ . '/system_errors.php')) {
            require_once __DIR__ . '/system_errors.php';
            besoiu_system_error_log('error', 'queue', $error, ['job_id' => $jobId]);
        }
    }

    private function ensureTable(): void
    {
        static $ready = false;
        if ($ready) {
            return;
        }

        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS queue_jobs (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                queue VARCHAR(60) NOT NULL DEFAULT \'default\',
                job_type VARCHAR(80) NOT NULL,
                payload_json JSON NOT NULL,
                status ENUM(\'pending\',\'processing\',\'done\',\'failed\') NOT NULL DEFAULT \'pending\',
                attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
                available_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                reserved_at DATETIME NULL DEFAULT NULL,
                last_error VARCHAR(500) NULL DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_queue_jobs_poll (queue, status, available_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $ready = true;
    }

    /** @param array<string, mixed> $payload */
    private function pushRedis(string $jobType, array $payload, int $jobId): void
    {
        if (!extension_loaded('redis')) {
            return;
        }

        $enabled = strtolower(trim((string) (getenv('REDIS_ENABLED') ?: '0')));
        if (!in_array($enabled, ['1', 'true', 'yes', 'on'], true)) {
            return;
        }

        try {
            $host = getenv('REDIS_HOST') ?: '127.0.0.1';
            $port = (int) (getenv('REDIS_PORT') ?: 6379);
            $redis = new Redis();
            if (!$redis->connect($host, $port, 0.2)) {
                return;
            }
            $redis->lPush('besoiu:queue:' . $this->queue, json_encode([
                'id' => $jobId,
                'job_type' => $jobType,
                'payload' => $payload,
            ], JSON_UNESCAPED_UNICODE));
        } catch (Throwable $exception) {
            error_log('[JobQueue] Redis push failed: ' . $exception->getMessage());
            if (is_file(__DIR__ . '/system_errors.php')) {
                require_once __DIR__ . '/system_errors.php';
                besoiu_system_error_log('warning', 'queue', 'Redis push failed: ' . $exception->getMessage(), [
                    'job_id' => $jobId,
                    'job_type' => $jobType,
                ]);
            }
        }
    }

    /** @return array<string, mixed>|null */
    private function popRedis(): ?array
    {
        if (!extension_loaded('redis')) {
            return null;
        }

        $enabled = strtolower(trim((string) (getenv('REDIS_ENABLED') ?: '0')));
        if (!in_array($enabled, ['1', 'true', 'yes', 'on'], true)) {
            return null;
        }

        try {
            $host = getenv('REDIS_HOST') ?: '127.0.0.1';
            $port = (int) (getenv('REDIS_PORT') ?: 6379);
            $redis = new Redis();
            if (!$redis->connect($host, $port, 0.2)) {
                return null;
            }

            $raw = $redis->rPop('besoiu:queue:' . $this->queue);
            if (!is_string($raw) || $raw === '') {
                return null;
            }

            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) {
                return null;
            }

            return [
                'id' => (int) ($decoded['id'] ?? 0),
                'job_type' => (string) ($decoded['job_type'] ?? ''),
                'payload' => is_array($decoded['payload'] ?? null) ? $decoded['payload'] : [],
                'attempts' => 1,
            ];
        } catch (Throwable $exception) {
            error_log('[JobQueue] Redis pop failed: ' . $exception->getMessage());
            if (is_file(__DIR__ . '/system_errors.php')) {
                require_once __DIR__ . '/system_errors.php';
                besoiu_system_error_log('warning', 'queue', 'Redis pop failed: ' . $exception->getMessage());
            }

            return null;
        }
    }
}
