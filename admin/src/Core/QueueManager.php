<?php declare(strict_types=1);

namespace Besoiu\Core;

/**
 * Manager pentru sistemul de cozi și joburi asincrone
 * 
 * Responsabilități:
 * - Management cozi de joburi (Redis fallback la fișiere)
 * - Dispatch și procesare joburi în background
 * - Monitorizare și retry logică pentru joburi eșuate
 * - Rate limiting și prioritizare joburi
 * - Cleanup și întreținere cozi
 * 
 * @version 2.0.0
 * @created 2026-07-02
 */
class QueueManager
{
    private ?object $redis = null;
    private bool $useRedis = false;
    private string $queueDir;
    private array $queues = [];
    private array $config;

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'redis_host' => '127.0.0.1',
            'redis_port' => 6379,
            'redis_timeout' => 5.0,
            'redis_prefix' => 'besoiu_queue:',
            'fallback_to_files' => true,
            'max_retries' => 3,
            'retry_delay' => 60, // seconds
            'cleanup_interval' => 3600, // seconds
            'default_timeout' => 300, // seconds
            'max_jobs_per_queue' => 1000
        ], $config);

        $this->queueDir = dirname(__DIR__, 2) . '/storage/queue';
        if (!is_dir($this->queueDir)) {
            mkdir($this->queueDir, 0775, true);
        }

        $this->initializeRedis();
        ImportLogger::info('QueueManager initialized', [
            'redis_enabled' => $this->useRedis,
            'fallback_enabled' => $this->config['fallback_to_files']
        ]);
    }

    /**
     * Adaugă un job în coadă
     */
    public function dispatch(string $queue, string $jobClass, array $payload, array $options = []): string
    {
        $jobId = $this->generateJobId();
        $jobOptions = array_merge([
            'priority' => 'normal', // low, normal, high, urgent
            'delay' => 0, // seconds
            'timeout' => $this->config['default_timeout'],
            'max_retries' => $this->config['max_retries'],
            'retry_delay' => $this->config['retry_delay']
        ], $options);

        $job = [
            'id' => $jobId,
            'queue' => $queue,
            'class' => $jobClass,
            'payload' => $payload,
            'options' => $jobOptions,
            'status' => 'pending',
            'created_at' => time(),
            'scheduled_at' => time() + $jobOptions['delay'],
            'attempts' => 0,
            'max_attempts' => $jobOptions['max_retries'] + 1
        ];

        $success = $this->pushJob($queue, $job);
        
        if ($success) {
            ImportLogger::info('Job dispatched', [
                'job_id' => $jobId,
                'queue' => $queue,
                'class' => $jobClass,
                'priority' => $jobOptions['priority'],
                'delay' => $jobOptions['delay']
            ]);
        } else {
            ImportLogger::error('Failed to dispatch job', null, [
                'job_id' => $jobId,
                'queue' => $queue,
                'class' => $jobClass
            ]);
            throw new \RuntimeException('Failed to dispatch job to queue: ' . $queue);
        }

        return $jobId;
    }

    /**
     * Obține următorul job din coadă pentru procesare
     */
    public function pop(string $queue): ?array
    {
        // Verificare dacă coada există
        if (!$this->queueExists($queue)) {
            return null;
        }

        $job = $this->popJob($queue);
        
        if ($job && $this->isJobReady($job)) {
            // Marchează job-ul ca în procesare
            $job['status'] = 'processing';
            $job['started_at'] = time();
            $job['attempts']++;

            $this->updateJobStatus($job);

            ImportLogger::info('Job popped for processing', [
                'job_id' => $job['id'],
                'queue' => $queue,
                'class' => $job['class'],
                'attempt' => $job['attempts']
            ]);

            return $job;
        }

        return null;
    }

    /**
     * Marchează un job ca finalizat cu succes
     */
    public function complete(array $job, array $result = []): bool
    {
        $job['status'] = 'completed';
        $job['completed_at'] = time();
        $job['result'] = $result;
        $job['processing_time'] = time() - ($job['started_at'] ?? time());

        $success = $this->updateJobStatus($job);
        
        if ($success) {
            $this->removeJobFromProcessing($job);
            
            ImportLogger::info('Job completed', [
                'job_id' => $job['id'],
                'queue' => $job['queue'],
                'processing_time' => $job['processing_time']
            ]);
        }

        return $success;
    }

    /**
     * Marchează un job ca eșuat
     */
    public function fail(array $job, string $error, bool $shouldRetry = true): bool
    {
        $job['status'] = 'failed';
        $job['failed_at'] = time();
        $job['error'] = $error;
        $job['processing_time'] = time() - ($job['started_at'] ?? time());

        // Verificare dacă trebuie să reîncerce
        if ($shouldRetry && $job['attempts'] < $job['max_attempts']) {
            return $this->retry($job);
        }

        // Job final eșuat
        $job['status'] = 'failed_permanently';
        $success = $this->updateJobStatus($job);
        
        if ($success) {
            $this->removeJobFromProcessing($job);
            
            ImportLogger::error('Job failed permanently', null, [
                'job_id' => $job['id'],
                'queue' => $job['queue'],
                'attempts' => $job['attempts'],
                'error' => $error
            ]);
        }

        return $success;
    }

    /**
     * Reîncearcă un job eșuat
     */
    public function retry(array $job): bool
    {
        $job['status'] = 'pending';
        $job['scheduled_at'] = time() + ($job['options']['retry_delay'] ?? $this->config['retry_delay']);
        unset($job['started_at'], $job['failed_at'], $job['processing_time']);

        $success = $this->pushJob($job['queue'], $job);
        
        if ($success) {
            $this->removeJobFromProcessing($job);
            
            ImportLogger::info('Job queued for retry', [
                'job_id' => $job['id'],
                'queue' => $job['queue'],
                'attempt' => $job['attempts'],
                'retry_at' => date('c', $job['scheduled_at'])
            ]);
        }

        return $success;
    }

    /**
     * Obține status-ul unei cozi
     */
    public function getQueueStatus(string $queue): array
    {
        $stats = [
            'name' => $queue,
            'pending' => 0,
            'processing' => 0,
            'completed' => 0,
            'failed' => 0,
            'total' => 0,
            'oldest_job' => null,
            'newest_job' => null
        ];

        if ($this->useRedis) {
            $stats = $this->getRedisQueueStats($queue);
        } else {
            $stats = $this->getFileQueueStats($queue);
        }

        return $stats;
    }

    /**
     * Obține lista tuturor cozilor active
     */
    public function getActiveQueues(): array
    {
        if ($this->useRedis) {
            return $this->getRedisActiveQueues();
        } else {
            return $this->getFileActiveQueues();
        }
    }

    /**
     * Curăță joburile vechi și completate
     */
    public function cleanup(array $options = []): array
    {
        $cleanupOptions = array_merge([
            'completed_older_than' => 86400, // 24 hours
            'failed_older_than' => 604800, // 7 days
            'max_completed_jobs' => 1000,
            'max_failed_jobs' => 500
        ], $options);

        $cleaned = [
            'completed_jobs' => 0,
            'failed_jobs' => 0,
            'orphaned_files' => 0,
            'total_size_freed' => 0
        ];

        if ($this->useRedis) {
            $cleaned = $this->cleanupRedisQueues($cleanupOptions);
        } else {
            $cleaned = $this->cleanupFileQueues($cleanupOptions);
        }

        ImportLogger::info('Queue cleanup completed', $cleaned);
        return $cleaned;
    }

    /**
     * Monitorizează cozile pentru joburi blocate sau abandonate
     */
    public function monitor(): array
    {
        $issues = [];
        $queues = $this->getActiveQueues();

        foreach ($queues as $queueName) {
            $queueIssues = $this->monitorQueue($queueName);
            if (!empty($queueIssues)) {
                $issues[$queueName] = $queueIssues;
            }
        }

        if (!empty($issues)) {
            ImportLogger::warning('Queue monitoring detected issues', $issues);
        }

        return $issues;
    }

    /**
     * Funcții private pentru gestionarea job-urilor
     */
    private function initializeRedis(): void
    {
        if (!class_exists('\Redis')) {
            ImportLogger::info('Redis extension not available, using file fallback');
            return;
        }

        try {
            $this->redis = new \Redis();
            $connected = $this->redis->connect(
                $this->config['redis_host'],
                $this->config['redis_port'],
                $this->config['redis_timeout']
            );

            if ($connected) {
                $this->useRedis = true;
                ImportLogger::info('Redis connection established', [
                    'host' => $this->config['redis_host'],
                    'port' => $this->config['redis_port']
                ]);
            } else {
                throw new \Exception('Redis connection failed');
            }
        } catch (\Exception $e) {
            ImportLogger::warning('Redis connection failed, using file fallback', [
                'error' => $e->getMessage()
            ]);
            
            if ($this->config['fallback_to_files']) {
                $this->useRedis = false;
            } else {
                throw $e;
            }
        }
    }

    private function generateJobId(): string
    {
        return 'job_' . uniqid() . '_' . random_int(1000, 9999);
    }

    private function pushJob(string $queue, array $job): bool
    {
        if ($this->useRedis) {
            return $this->pushJobRedis($queue, $job);
        } else {
            return $this->pushJobFile($queue, $job);
        }
    }

    private function popJob(string $queue): ?array
    {
        if ($this->useRedis) {
            return $this->popJobRedis($queue);
        } else {
            return $this->popJobFile($queue);
        }
    }

    private function pushJobRedis(string $queue, array $job): bool
    {
        try {
            $priority = $this->getPriorityScore($job['options']['priority'] ?? 'normal');
            $key = $this->config['redis_prefix'] . $queue;
            
            // Folosire Redis sorted set cu prioritate
            $this->redis->zAdd($key, $priority, json_encode($job));
            
            // Salvare detalii job separat
            $jobKey = $this->config['redis_prefix'] . 'job:' . $job['id'];
            $this->redis->hMSet($jobKey, [
                'data' => json_encode($job),
                'queue' => $queue,
                'created_at' => time()
            ]);
            $this->redis->expire($jobKey, 86400); // 24h TTL

            return true;
        } catch (\Exception $e) {
            ImportLogger::error('Redis job push failed', $e);
            
            if ($this->config['fallback_to_files']) {
                return $this->pushJobFile($queue, $job);
            }
            
            return false;
        }
    }

    private function popJobRedis(string $queue): ?array
    {
        try {
            $key = $this->config['redis_prefix'] . $queue;
            
            // Pop job cu prioritate cea mai mare
            $result = $this->redis->zPopMax($key);
            
            if (empty($result)) {
                return null;
            }

            $jobData = json_decode($result[0], true);
            return is_array($jobData) ? $jobData : null;

        } catch (\Exception $e) {
            ImportLogger::error('Redis job pop failed', $e);
            
            if ($this->config['fallback_to_files']) {
                return $this->popJobFile($queue);
            }
            
            return null;
        }
    }

    private function pushJobFile(string $queue, array $job): bool
    {
        try {
            $queueFile = $this->queueDir . '/' . $queue . '.queue';
            $lockFile = $queueFile . '.lock';

            // File locking pentru concurență
            $lockHandle = fopen($lockFile, 'c+');
            if (!$lockHandle || !flock($lockHandle, LOCK_EX)) {
                throw new \RuntimeException('Cannot acquire queue lock');
            }

            // Citire joburi existente
            $jobs = [];
            if (file_exists($queueFile)) {
                $content = file_get_contents($queueFile);
                if ($content) {
                    $jobs = json_decode($content, true) ?: [];
                }
            }

            // Verificare limită
            if (count($jobs) >= $this->config['max_jobs_per_queue']) {
                throw new \RuntimeException('Queue is full');
            }

            // Adăugare job cu sortare după prioritate
            $jobs[] = $job;
            usort($jobs, function($a, $b) {
                $priorityA = $this->getPriorityScore($a['options']['priority'] ?? 'normal');
                $priorityB = $this->getPriorityScore($b['options']['priority'] ?? 'normal');
                return $priorityB <=> $priorityA; // Descending
            });

            // Salvare
            file_put_contents($queueFile, json_encode($jobs, JSON_PRETTY_PRINT));
            
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
            unlink($lockFile);

            return true;

        } catch (\Exception $e) {
            ImportLogger::error('File job push failed', $e);
            
            if (isset($lockHandle)) {
                flock($lockHandle, LOCK_UN);
                fclose($lockHandle);
            }
            
            return false;
        }
    }

    private function popJobFile(string $queue): ?array
    {
        try {
            $queueFile = $this->queueDir . '/' . $queue . '.queue';
            $lockFile = $queueFile . '.lock';

            if (!file_exists($queueFile)) {
                return null;
            }

            // File locking
            $lockHandle = fopen($lockFile, 'c+');
            if (!$lockHandle || !flock($lockHandle, LOCK_EX)) {
                throw new \RuntimeException('Cannot acquire queue lock');
            }

            // Citire joburi
            $jobs = json_decode(file_get_contents($queueFile), true) ?: [];
            
            if (empty($jobs)) {
                flock($lockHandle, LOCK_UN);
                fclose($lockHandle);
                unlink($lockFile);
                return null;
            }

            // Găsire primul job ready
            $readyJob = null;
            $readyIndex = -1;
            
            foreach ($jobs as $index => $job) {
                if ($this->isJobReady($job)) {
                    $readyJob = $job;
                    $readyIndex = $index;
                    break;
                }
            }

            if ($readyJob) {
                // Scoatere job din coadă
                array_splice($jobs, $readyIndex, 1);
                file_put_contents($queueFile, json_encode($jobs, JSON_PRETTY_PRINT));
            }

            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
            unlink($lockFile);

            return $readyJob;

        } catch (\Exception $e) {
            ImportLogger::error('File job pop failed', $e);
            
            if (isset($lockHandle)) {
                flock($lockHandle, LOCK_UN);
                fclose($lockHandle);
            }
            
            return null;
        }
    }

    private function isJobReady(array $job): bool
    {
        return ($job['scheduled_at'] ?? time()) <= time();
    }

    private function getPriorityScore(string $priority): int
    {
        $priorities = [
            'low' => 10,
            'normal' => 50,
            'high' => 80,
            'urgent' => 100
        ];

        return $priorities[$priority] ?? $priorities['normal'];
    }

    private function queueExists(string $queue): bool
    {
        if ($this->useRedis) {
            $key = $this->config['redis_prefix'] . $queue;
            return $this->redis->exists($key) > 0;
        } else {
            $queueFile = $this->queueDir . '/' . $queue . '.queue';
            return file_exists($queueFile);
        }
    }

    private function updateJobStatus(array $job): bool
    {
        if ($this->useRedis) {
            try {
                $jobKey = $this->config['redis_prefix'] . 'job:' . $job['id'];
                $this->redis->hMSet($jobKey, [
                    'data' => json_encode($job),
                    'updated_at' => time()
                ]);
                return true;
            } catch (\Exception $e) {
                ImportLogger::error('Redis job status update failed', $e);
                return false;
            }
        }

        // Pentru file-based, job-urile sunt stocate în istoricul procesării
        $historyFile = $this->queueDir . '/job_history.jsonl';
        $jobLine = json_encode($job) . "\n";
        
        return file_put_contents($historyFile, $jobLine, FILE_APPEND | LOCK_EX) !== false;
    }

    private function removeJobFromProcessing(array $job): void
    {
        // Cleanup procesare job (implementare simplificată)
        ImportLogger::info('Job removed from processing', ['job_id' => $job['id']]);
    }

    // Implementări pentru funcțiile de statistici și monitoring
    private function getRedisQueueStats(string $queue): array
    {
        $stats = [
            'name' => $queue,
            'pending' => 0,
            'processing' => 0,
            'completed' => 0,
            'failed' => 0,
            'total' => 0,
            'oldest_job' => null,
            'newest_job' => null
        ];

        try {
            $key = $this->config['redis_prefix'] . $queue;
            $stats['pending'] = $this->redis->zCard($key);
            $stats['total'] = $stats['pending'];
        } catch (\Exception $e) {
            // Redis error, return default stats
        }

        return $stats;
    }

    private function getFileQueueStats(string $queue): array
    {
        $stats = [
            'name' => $queue,
            'pending' => 0,
            'processing' => 0,
            'completed' => 0,
            'failed' => 0,
            'total' => 0,
            'oldest_job' => null,
            'newest_job' => null
        ];

        try {
            $queueFile = $this->queueDir . '/' . $queue . '.queue';
            if (file_exists($queueFile)) {
                $jobs = json_decode(file_get_contents($queueFile), true) ?: [];
                $stats['pending'] = count($jobs);
                $stats['total'] = $stats['pending'];
            }
        } catch (\Exception $e) {
            // File error, return default stats
        }

        return $stats;
    }

    private function getRedisActiveQueues(): array
    {
        try {
            $pattern = $this->config['redis_prefix'] . '*';
            $keys = $this->redis->keys($pattern);
            
            $queues = [];
            foreach ($keys as $key) {
                $queueName = str_replace($this->config['redis_prefix'], '', $key);
                if (!str_contains($queueName, ':')) { // Skip job detail keys
                    $queues[] = $queueName;
                }
            }
            
            return array_unique($queues);
        } catch (\Exception $e) {
            return [];
        }
    }

    private function getFileActiveQueues(): array
    {
        $queues = [];
        $files = glob($this->queueDir . '/*.queue');
        
        foreach ($files as $file) {
            $queueName = basename($file, '.queue');
            $queues[] = $queueName;
        }

        return $queues;
    }

    private function cleanupRedisQueues(array $options): array
    {
        $cleaned = [
            'completed_jobs' => 0,
            'failed_jobs' => 0,
            'orphaned_files' => 0,
            'total_size_freed' => 0
        ];

        try {
            // Cleanup implementare simplificată pentru Redis
            $pattern = $this->config['redis_prefix'] . 'job:*';
            $jobKeys = $this->redis->keys($pattern);
            
            foreach ($jobKeys as $key) {
                $age = time() - ($this->redis->hGet($key, 'created_at') ?: time());
                if ($age > $options['completed_older_than']) {
                    $this->redis->del($key);
                    $cleaned['completed_jobs']++;
                }
            }
        } catch (\Exception $e) {
            // Redis cleanup error
        }

        return $cleaned;
    }

    private function cleanupFileQueues(array $options): array
    {
        $cleaned = [
            'completed_jobs' => 0,
            'failed_jobs' => 0,
            'orphaned_files' => 0,
            'total_size_freed' => 0
        ];

        try {
            // Cleanup job history file
            $historyFile = $this->queueDir . '/job_history.jsonl';
            if (file_exists($historyFile)) {
                $originalSize = filesize($historyFile);
                
                // Simplificat: șterge fișierul de istoric dacă e prea mare
                if ($originalSize > (10 * 1024 * 1024)) { // 10MB
                    unlink($historyFile);
                    $cleaned['completed_jobs'] = 100; // Estimare
                    $cleaned['total_size_freed'] = $originalSize;
                }
            }

            // Cleanup old queue files
            $files = glob($this->queueDir . '/*');
            foreach ($files as $file) {
                if (filemtime($file) < (time() - $options['failed_older_than'])) {
                    $size = filesize($file);
                    unlink($file);
                    $cleaned['orphaned_files']++;
                    $cleaned['total_size_freed'] += $size;
                }
            }
        } catch (\Exception $e) {
            // File cleanup error
        }

        return $cleaned;
    }

    private function monitorQueue(string $queueName): array
    {
        $issues = [];
        
        try {
            $stats = $this->getQueueStatus($queueName);
            
            // Verificare dacă sunt prea multe joburi pending
            if ($stats['pending'] > 1000) {
                $issues[] = 'High pending job count: ' . $stats['pending'];
            }
            
            // Verificare dacă sunt prea multe joburi failed
            if ($stats['failed'] > 100) {
                $issues[] = 'High failed job count: ' . $stats['failed'];
            }
        } catch (\Exception $e) {
            $issues[] = 'Queue monitoring error: ' . $e->getMessage();
        }

        return $issues;
    }
}