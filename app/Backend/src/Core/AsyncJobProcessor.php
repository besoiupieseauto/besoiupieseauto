<?php declare(strict_types=1);

namespace Besoiu\Core;

use Besoiu\Services\Import\JobManagementService;

/**
 * Processor pentru joburi asincrone în background
 * 
 * Responsabilități:
 * - Procesare continuă joburi din cozi
 * - Management procese worker în background
 * - Monitorizare și recovery pentru joburi blocate
 * - Load balancing și distribuție sarcină
 * - Graceful shutdown și cleanup
 * 
 * @version 2.0.0
 * @created 2026-07-02
 */
class AsyncJobProcessor
{
    private QueueManager $queueManager;
    private JobManagementService $jobService;
    private array $config;
    private array $workers = [];
    private bool $running = false;
    private int $processedJobs = 0;
    private float $startTime;

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'max_workers' => 3,
            'queue_names' => ['import', 'tecdoc', 'images', 'reports'],
            'worker_timeout' => 300, // seconds
            'memory_limit' => '256M',
            'max_jobs_per_worker' => 50,
            'sleep_between_jobs' => 1, // seconds
            'heartbeat_interval' => 30, // seconds
            'shutdown_timeout' => 60, // seconds
            'enable_monitoring' => true
        ], $config);

        $this->queueManager = new QueueManager();
        $this->jobService = new JobManagementService();
        $this->startTime = microtime(true);

        ImportLogger::info('AsyncJobProcessor initialized', [
            'max_workers' => $this->config['max_workers'],
            'queues' => $this->config['queue_names'],
            'memory_limit' => $this->config['memory_limit']
        ]);
    }

    /**
     * Pornește procesorul în mod daemon
     */
    public function start(): void
    {
        throw new \RuntimeException('DEPRECATED: use admin/workers/async_worker.php — AsyncJobProcessor nu mai este suportat.');
    }

    /**
     * Oprește procesorul graceful
     */
    public function stop(): void
    {
        if (!$this->running) {
            return;
        }

        ImportLogger::info('Stopping AsyncJobProcessor...');
        $this->running = false;

        // Oprire workers
        $this->stopAllWorkers();

        // Cleanup și statistici finale
        $this->cleanup();
        
        $runtime = microtime(true) - $this->startTime;
        ImportLogger::info('AsyncJobProcessor stopped', [
            'runtime' => round($runtime, 2) . 's',
            'processed_jobs' => $this->processedJobs,
            'avg_jobs_per_second' => $runtime > 0 ? round($this->processedJobs / $runtime, 2) : 0
        ]);
    }

    /**
     * Procesează un singur job (pentru testare sau rulare manuală)
     */
    public function processNextJob(string $queue = null): ?array
    {
        $queues = $queue ? [$queue] : $this->config['queue_names'];
        
        foreach ($queues as $queueName) {
            $job = $this->queueManager->pop($queueName);
            
            if ($job) {
                return $this->executeJob($job);
            }
        }

        return null;
    }

    /**
     * Obține statusul procesatorului
     */
    public function getStatus(): array
    {
        return [
            'running' => $this->running,
            'pid' => getmypid(),
            'uptime' => round(microtime(true) - $this->startTime, 2),
            'processed_jobs' => $this->processedJobs,
            'active_workers' => count($this->workers),
            'queue_status' => $this->getQueuesStatus(),
            'memory_usage' => [
                'current' => memory_get_usage(true),
                'peak' => memory_get_peak_usage(true),
                'limit' => $this->parseMemoryLimit($this->config['memory_limit'])
            ],
            'last_heartbeat' => date('c')
        ];
    }

    /**
     * Procesează joburi din toate cozile configurate
     */
    public function processQueues(int $maxJobs = null): array
    {
        $results = [
            'processed' => 0,
            'failed' => 0,
            'skipped' => 0,
            'errors' => []
        ];

        $jobCount = 0;
        $maxJobs = $maxJobs ?? $this->config['max_jobs_per_worker'];

        ImportLogger::info('Starting queue processing', [
            'max_jobs' => $maxJobs,
            'queues' => $this->config['queue_names']
        ]);

        while ($jobCount < $maxJobs) {
            $hasJobs = false;

            foreach ($this->config['queue_names'] as $queue) {
                if ($jobCount >= $maxJobs) break;

                $job = $this->queueManager->pop($queue);
                
                if ($job) {
                    $hasJobs = true;
                    $jobCount++;

                    try {
                        $result = $this->executeJob($job);
                        
                        if ($result['success']) {
                            $results['processed']++;
                        } else {
                            $results['failed']++;
                            $results['errors'][] = [
                                'job_id' => $job['id'],
                                'error' => $result['error'] ?? 'Unknown error'
                            ];
                        }

                    } catch (\Exception $e) {
                        $results['failed']++;
                        $results['errors'][] = [
                            'job_id' => $job['id'],
                            'error' => $e->getMessage()
                        ];
                        
                        ImportLogger::error('Job execution failed', $e, [
                            'job_id' => $job['id'],
                            'queue' => $queue
                        ]);
                    }

                    // Sleep între joburi pentru a nu suprasolicita sistemul
                    if ($this->config['sleep_between_jobs'] > 0) {
                        usleep($this->config['sleep_between_jobs'] * 1000000);
                    }
                }
            }

            // Dacă nu mai sunt joburi, ieșim din loop
            if (!$hasJobs) {
                break;
            }

            // Verificare memorie
            $this->checkMemoryUsage();
        }

        ImportLogger::info('Queue processing completed', $results);
        return $results;
    }

    /**
     * Funcții private pentru procesare
     */
    private function mainLoop(): void
    {
        $lastHeartbeat = time();
        $lastCleanup = time();

        while ($this->running) {
            try {
                // Procesare joburi
                $this->processWorkerCycle();

                // Heartbeat periodic
                if ((time() - $lastHeartbeat) >= $this->config['heartbeat_interval']) {
                    $this->sendHeartbeat();
                    $lastHeartbeat = time();
                }

                // Cleanup periodic
                if ((time() - $lastCleanup) >= 300) { // 5 minute
                    $this->performPeriodicCleanup();
                    $lastCleanup = time();
                }

                // Monitoring și recovery
                if ($this->config['enable_monitoring']) {
                    $this->monitorAndRecover();
                }

                // Sleep scurt pentru a nu consuma CPU excesiv
                usleep(100000); // 0.1 seconds

            } catch (\Exception $e) {
                ImportLogger::error('Error in main processing loop', $e);
                
                // În caz de eroare critică, sleep mai mult înainte de retry
                sleep(5);
            }
        }
    }

    private function processWorkerCycle(): void
    {
        $this->manageWorkers();
        $this->cleanupCompletedWorkers();
    }

    private function manageWorkers(): void
    {
        $activeWorkers = count($this->workers);
        $maxWorkers = $this->config['max_workers'];

        // Pornire workers noi dacă sunt joburi în așteptare
        if ($activeWorkers < $maxWorkers && $this->hasWaitingJobs()) {
            $this->startWorker();
        }
    }

    private function startWorker(): void
    {
        $workerId = 'worker_' . uniqid();
        
        $worker = [
            'id' => $workerId,
            'started_at' => time(),
            'jobs_processed' => 0,
            'status' => 'starting',
            'queue' => $this->selectBestQueue()
        ];

        $this->workers[$workerId] = $worker;

        ImportLogger::info('Starting new worker', [
            'worker_id' => $workerId,
            'queue' => $worker['queue'],
            'total_workers' => count($this->workers)
        ]);

        // Simulare worker process - în implementarea reală ar fi un proces separat
        $this->simulateWorkerProcess($workerId);
    }

    private function simulateWorkerProcess(string $workerId): void
    {
        // În implementarea reală, aceasta ar lansa un proces separat
        // Pentru demonstrație, procesez câteva joburi sincron
        
        $worker = &$this->workers[$workerId];
        $worker['status'] = 'running';
        
        for ($i = 0; $i < 3; $i++) {
            if (!$this->running) break;

            $job = $this->queueManager->pop($worker['queue']);
            if ($job) {
                $result = $this->executeJob($job);
                $worker['jobs_processed']++;
                $this->processedJobs++;
                
                if (!$result['success']) {
                    ImportLogger::warning('Worker job failed', [
                        'worker_id' => $workerId,
                        'job_id' => $job['id'],
                        'error' => $result['error'] ?? 'Unknown'
                    ]);
                }
            } else {
                // Nu mai sunt joburi, worker se oprește
                break;
            }
        }

        $worker['status'] = 'completed';
        $worker['completed_at'] = time();
    }

    private function executeJob(array $job): array
    {
        $startTime = microtime(true);
        
        try {
            ImportLogger::info('Executing job', [
                'job_id' => $job['id'],
                'class' => $job['class'],
                'queue' => $job['queue']
            ]);

            // Verificare timeout
            if (isset($job['options']['timeout'])) {
                set_time_limit($job['options']['timeout']);
            }

            // Instanțiere și executare job class
            $result = $this->executeJobClass($job);

            $processingTime = microtime(true) - $startTime;

            if ($result['success']) {
                $this->queueManager->complete($job, $result);
                
                ImportLogger::info('Job completed successfully', [
                    'job_id' => $job['id'],
                    'processing_time' => round($processingTime, 2) . 's'
                ]);
            } else {
                $this->queueManager->fail($job, $result['error'] ?? 'Job execution failed');
            }

            return $result;

        } catch (\Exception $e) {
            $processingTime = microtime(true) - $startTime;
            
            $this->queueManager->fail($job, $e->getMessage());
            
            ImportLogger::error('Job execution exception', $e, [
                'job_id' => $job['id'],
                'processing_time' => round($processingTime, 2) . 's'
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'processing_time' => $processingTime
            ];
        }
    }

    private function executeJobClass(array $job): array
    {
        $className = $job['class'];
        $payload = $job['payload'] ?? [];

        // Verificare existență clasă
        if (!class_exists($className)) {
            throw new \RuntimeException('Job class not found: ' . $className);
        }

        // Instanțiere și executare
        $jobInstance = new $className();
        
        if (!method_exists($jobInstance, 'handle')) {
            throw new \RuntimeException('Job class must have handle() method: ' . $className);
        }

        // Executare job
        $result = $jobInstance->handle($payload);
        
        // Verificare format rezultat
        if (!is_array($result)) {
            $result = ['success' => true, 'data' => $result];
        }
        
        if (!isset($result['success'])) {
            $result['success'] = true;
        }

        return $result;
    }

    private function hasWaitingJobs(): bool
    {
        foreach ($this->config['queue_names'] as $queue) {
            $status = $this->queueManager->getQueueStatus($queue);
            if ($status['pending'] > 0) {
                return true;
            }
        }
        return false;
    }

    private function selectBestQueue(): string
    {
        $bestQueue = $this->config['queue_names'][0];
        $maxPending = 0;

        foreach ($this->config['queue_names'] as $queue) {
            $status = $this->queueManager->getQueueStatus($queue);
            if ($status['pending'] > $maxPending) {
                $maxPending = $status['pending'];
                $bestQueue = $queue;
            }
        }

        return $bestQueue;
    }

    private function cleanupCompletedWorkers(): void
    {
        foreach ($this->workers as $workerId => $worker) {
            if (in_array($worker['status'], ['completed', 'failed', 'timeout'])) {
                ImportLogger::info('Cleaning up worker', [
                    'worker_id' => $workerId,
                    'status' => $worker['status'],
                    'jobs_processed' => $worker['jobs_processed']
                ]);
                
                unset($this->workers[$workerId]);
            }
        }
    }

    private function stopAllWorkers(): void
    {
        ImportLogger::info('Stopping all workers', ['count' => count($this->workers)]);
        
        foreach ($this->workers as $workerId => &$worker) {
            if (in_array($worker['status'], ['running', 'starting'])) {
                $worker['status'] = 'stopping';
                // În implementarea reală, ar trimite semnal de oprire la proces
            }
        }

        // Așteptare finalizare cu timeout
        $timeout = time() + $this->config['shutdown_timeout'];
        
        while (time() < $timeout && !empty($this->workers)) {
            $this->cleanupCompletedWorkers();
            usleep(500000); // 0.5 seconds
        }

        // Force cleanup workers care nu s-au oprit
        if (!empty($this->workers)) {
            ImportLogger::warning('Force terminating remaining workers', [
                'count' => count($this->workers)
            ]);
            $this->workers = [];
        }
    }

    private function setupEnvironment(): void
    {
        // Configurare limită memorie
        ini_set('memory_limit', $this->config['memory_limit']);
        
        // Configurare error handling
        set_error_handler([$this, 'handleError']);
        set_exception_handler([$this, 'handleException']);
        
        // Ignore user abort
        ignore_user_abort(true);
    }

    private function registerSignalHandlers(): void
    {
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGTERM, [$this, 'handleShutdownSignal']);
            pcntl_signal(SIGINT, [$this, 'handleShutdownSignal']);
            pcntl_signal(SIGHUP, [$this, 'handleReloadSignal']);
        }
    }

    private function sendHeartbeat(): void
    {
        $heartbeatData = [
            'processor_id' => getmypid(),
            'status' => $this->getStatus(),
            'timestamp' => time()
        ];

        ImportLogger::info('Processor heartbeat', $heartbeatData);
    }

    private function performPeriodicCleanup(): void
    {
        // Cleanup memorie
        gc_collect_cycles();
        
        // Cleanup cozi
        $this->queueManager->cleanup();
        
        // Verificare health
        $this->checkSystemHealth();
    }

    private function monitorAndRecover(): void
    {
        $issues = $this->queueManager->monitor();
        
        if (!empty($issues)) {
            ImportLogger::warning('Queue issues detected, attempting recovery', $issues);
            // Implementare recovery logic
        }
    }

    private function checkMemoryUsage(): void
    {
        $memoryUsage = memory_get_usage(true);
        $memoryLimit = $this->parseMemoryLimit($this->config['memory_limit']);
        
        if ($memoryUsage > ($memoryLimit * 0.9)) {
            ImportLogger::warning('High memory usage detected', [
                'usage' => $memoryUsage,
                'limit' => $memoryLimit,
                'percentage' => round(($memoryUsage / $memoryLimit) * 100, 1)
            ]);
            
            // Force garbage collection
            gc_collect_cycles();
        }
    }

    private function checkSystemHealth(): void
    {
        $health = [
            'memory_ok' => memory_get_usage(true) < ($this->parseMemoryLimit($this->config['memory_limit']) * 0.8),
            'queues_ok' => true,
            'workers_ok' => count($this->workers) <= $this->config['max_workers']
        ];

        if (!$health['memory_ok'] || !$health['queues_ok'] || !$health['workers_ok']) {
            ImportLogger::warning('System health check failed', $health);
        }
    }

    private function getQueuesStatus(): array
    {
        $status = [];
        foreach ($this->config['queue_names'] as $queue) {
            $status[$queue] = $this->queueManager->getQueueStatus($queue);
        }
        return $status;
    }

    private function parseMemoryLimit(string $limit): int
    {
        $limit = strtoupper($limit);
        $value = intval($limit);
        
        if (strpos($limit, 'G') !== false) {
            return $value * 1024 * 1024 * 1024;
        } elseif (strpos($limit, 'M') !== false) {
            return $value * 1024 * 1024;
        } elseif (strpos($limit, 'K') !== false) {
            return $value * 1024;
        }
        
        return $value;
    }

    private function cleanup(): void
    {
        ImportLogger::info('Performing final cleanup');
        
        // Cleanup handlers
        restore_error_handler();
        restore_exception_handler();
        
        // Final statistics
        ImportLogger::audit('processor_shutdown', [
            'pid' => getmypid(),
            'uptime' => microtime(true) - $this->startTime,
            'processed_jobs' => $this->processedJobs
        ]);
    }

    // Signal handlers
    public function handleShutdownSignal(int $signal): void
    {
        ImportLogger::info('Received shutdown signal', ['signal' => $signal]);
        $this->stop();
    }

    public function handleReloadSignal(int $signal): void
    {
        ImportLogger::info('Received reload signal', ['signal' => $signal]);
        // Implementare reload config
    }

    public function handleError(int $errno, string $errstr, string $errfile = '', int $errline = 0): bool
    {
        ImportLogger::error('PHP Error in processor', null, [
            'errno' => $errno,
            'message' => $errstr,
            'file' => $errfile,
            'line' => $errline
        ]);
        return true;
    }

    public function handleException(\Throwable $exception): void
    {
        ImportLogger::error('Unhandled exception in processor', $exception);
        $this->stop();
    }
}