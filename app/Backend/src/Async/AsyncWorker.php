<?php



declare(strict_types=1);



namespace Besoiu\Async;



/**

 * Worker CLI — rulează ÎN AFARA PHP-FPM.

 * Usage: php admin/workers/async_worker.php --queue=products --consumer=products-1

 */

final class AsyncWorker

{

    private static bool $shutdownRequested = false;



    public function __construct(

        private readonly string $queue,

        private readonly string $consumer,

        private readonly JobStore $store = new JobStore(),

        private readonly JobQueue $jobQueue = new JobQueue(),

        private readonly CircuitBreaker $circuitBreaker = new CircuitBreaker(),

    ) {

    }



    public static function requestShutdown(): void

    {

        self::$shutdownRequested = true;

    }



    public static function isShutdownRequested(): bool

    {

        return self::$shutdownRequested;

    }



    public function run(int $maxJobs = 0): void

    {

        $workerCounts = $this->jobQueue->countActiveWorkers($this->queue);

        if ($workerCounts['max'] > 0 && $workerCounts['active'] >= $workerCounts['max']) {

            if (!$this->jobQueue->registerWorkerSlot($this->queue, $this->consumer)) {

                JobLogger::log('worker_max_reached', [

                    'queue' => $this->queue,

                    'consumer' => $this->consumer,

                    'active' => $workerCounts['active'],

                    'max' => $workerCounts['max'],

                ]);

                fwrite(STDERR, "Max workers ({$workerCounts['max']}) atins pentru coada {$this->queue}.\n");



                return;

            }

        } else {

            $this->jobQueue->registerWorkerSlot($this->queue, $this->consumer);

        }



        $processed = 0;

        $idleMs = (int) AsyncConfig::get('worker_idle_sleep_ms', 250);

        $heartbeatSec = (int) AsyncConfig::get('worker_heartbeat_sec', 15);

        $lastHeartbeat = 0;

        $maxIdleSec = $maxJobs > 0 ? (int) AsyncConfig::get('worker_max_jobs_idle_sec', 8) : 0;

        $idleSince = time();



        JobLogger::log('worker_start', [

            'queue' => $this->queue,

            'consumer' => $this->consumer,

            'pid' => getmypid(),

        ]);



        while (($maxJobs === 0 || $processed < $maxJobs) && !self::isShutdownRequested()) {

            $this->dispatchSignals();



            if ($heartbeatSec > 0 && (time() - $lastHeartbeat) >= $heartbeatSec) {

                $this->jobQueue->heartbeatWorkerSlot($this->queue, $this->consumer);

                $lastHeartbeat = time();

            }



            $item = $this->jobQueue->pop($this->queue, $this->consumer, 1000);

            if ($item === null) {

                $item = $this->jobQueue->reclaimStale($this->queue, $this->consumer);

            }



            if ($item === null) {

                if (self::isShutdownRequested()) {

                    break;

                }

                if ($maxJobs > 0 && $processed === 0 && $maxIdleSec > 0 && (time() - $idleSince) >= $maxIdleSec) {

                    JobLogger::log('worker_idle_exit', [

                        'queue' => $this->queue,

                        'consumer' => $this->consumer,

                        'max_jobs' => $maxJobs,

                        'idle_sec' => time() - $idleSince,

                    ]);

                    break;

                }

                usleep($idleMs * 1000);

                continue;

            }



            $idleSince = time();



            if (self::isShutdownRequested()) {

                // Nu porni job nou după semnal — mesajul revine în PEL pentru reclaim.

                break;

            }



            $envelope = $item['envelope'];

            $jobId = (string) ($envelope['job_id'] ?? '');

            $type = (string) ($envelope['type'] ?? '');

            $stream = isset($item['stream']) ? (string) $item['stream'] : null;



            if ($jobId === '' || $type === '') {

                $this->jobQueue->ack($this->queue, $item['stream_id'], $this->consumer, $stream);

                continue;

            }



            $existing = $this->store->get($jobId);

            if ($existing !== null && in_array((string) ($existing['status'] ?? ''), ['done', 'failed'], true)) {

                $this->jobQueue->ack($this->queue, $item['stream_id'], $this->consumer, $stream);

                continue;

            }



            try {

                $this->executeJob($jobId, $type);

            } catch (\Throwable $e) {

                if ($this->shouldRetry($jobId, $e)) {

                    $this->requeueJob($jobId, $type, $envelope);

                } else {

                    $this->store->markFailed($jobId, $e->getMessage());

                    $this->writeDeadLetter($jobId, $e->getMessage());

                }

                JobLogger::log('worker_job_error', ['job_id' => $jobId, 'error' => $e->getMessage()]);

            } finally {

                $this->jobQueue->ack($this->queue, $item['stream_id'], $this->consumer, $stream);

            }



            ++$processed;

            $this->dispatchSignals();

        }



        JobLogger::log('worker_stop', [

            'queue' => $this->queue,

            'processed' => $processed,

            'shutdown' => self::isShutdownRequested(),

        ]);

    }



    private function executeJob(string $jobId, string $type): void

    {

        $job = $this->store->get($jobId);

        if ($job === null) {

            throw new \RuntimeException('Job inexistent în worker.');

        }



        if ($this->store->isTimedOut($job)) {

            $this->store->markFailed($jobId, 'timeout');



            return;

        }



        if ($this->store->isCancelRequested($jobId)) {

            $this->store->markFailed($jobId, 'cancelled');



            return;

        }



        $serviceName = (string) ($job['payload']['circuit_service'] ?? '');

        if ($serviceName !== '') {

            $this->circuitBreaker->assertClosed($serviceName);

        }



        $this->store->markProcessing($jobId);

        $this->store->touchHeartbeat($jobId, $this->consumer);



        $timeoutSec = (int) ($job['timeout_sec'] ?? AsyncConfig::get('default_job_timeout_sec', 60));

        set_time_limit(max(30, $timeoutSec + 15));



        $deadline = time() + $timeoutSec;

        $handler = JobRegistry::resolve($type);

        $payload = is_array($job['payload'] ?? null) ? $job['payload'] : [];
        $payload['_async_job_id'] = $jobId;



        $report = function (int $progress, string $message = '') use ($jobId, $deadline): void {

            if (time() > $deadline) {

                throw new \RuntimeException('timeout');

            }

            if ($this->store->isCancelRequested($jobId)) {

                throw new \RuntimeException('cancelled');

            }

            $this->store->updateProgress($jobId, $progress, $message !== '' ? $message : null);

            $this->store->touchHeartbeat($jobId, $this->consumer);

        };



        try {

            $result = $this->withDeadline(

                static fn () => $handler->handle($payload, $report),

                $deadline

            );

            $this->store->markDone($jobId, $result);

            if ($serviceName !== '') {

                $this->circuitBreaker->recordSuccess($serviceName);

            }

        } catch (\Throwable $e) {

            if ($serviceName !== '') {

                $this->circuitBreaker->recordFailure($serviceName);

            }

            $reason = $e->getMessage() === 'timeout' ? 'timeout' : $e->getMessage();

            throw new \RuntimeException($reason, 0, $e);

        }

    }



    /**

     * @template T

     * @param callable(): T $callback

     * @return T

     */

    private function withDeadline(callable $callback, int $deadline)

    {

        if (function_exists('pcntl_alarm') && function_exists('pcntl_signal')) {

            $remaining = max(1, $deadline - time());

            pcntl_signal(SIGALRM, static function (): void {

                throw new \RuntimeException('timeout');

            });

            pcntl_alarm($remaining);

        }



        try {

            return $callback();

        } finally {

            if (function_exists('pcntl_alarm')) {

                pcntl_alarm(0);

            }

        }

    }



    private function shouldRetry(string $jobId, \Throwable $e): bool

    {

        $job = $this->store->get($jobId);

        if ($job === null) {

            return false;

        }



        $attempt = (int) ($job['attempt'] ?? 1);

        $maxAttempts = (int) ($job['max_attempts'] ?? AsyncConfig::get('job_max_attempts', 3));

        if ($attempt >= $maxAttempts) {

            return false;

        }



        $message = strtolower($e->getMessage());

        $transient = str_contains($message, 'redis')

            || str_contains($message, 'timeout')

            || str_contains($message, 'connection')

            || str_contains($message, 'temporarily');



        return $transient;

    }



    /** @param array<string, mixed> $envelope */

    private function requeueJob(string $jobId, string $type, array $envelope): void

    {

        $job = $this->store->get($jobId);

        if ($job === null) {

            return;

        }



        $attempt = (int) ($job['attempt'] ?? 1);

        $backoffs = [5, 15, 45];

        $delay = $backoffs[min($attempt - 1, count($backoffs) - 1)] ?? 45;



        $job['attempt'] = $attempt + 1;

        $job['status'] = 'pending';

        $job['started_at'] = null;

        $job['error'] = null;

        $job['next_retry_at'] = time() + $delay;

        $this->store->save($job);



        sleep($delay);



        $this->jobQueue->push(

            $this->queue,

            [

                'job_id' => $jobId,

                'type' => $type,

                'queue' => $this->queue,

                'retry' => true,

            ],

            (string) ($job['priority'] ?? 'normal')

        );



        JobLogger::log('job_retry', ['job_id' => $jobId, 'attempt' => $job['attempt'], 'delay' => $delay]);

    }



    private function writeDeadLetter(string $jobId, string $error): void

    {

        $job = $this->store->get($jobId);

        if ($job === null) {

            return;

        }



        $dir = AsyncConfig::storageDir() . '/dead_letter';

        if (!is_dir($dir)) {

            mkdir($dir, 0775, true);

        }



        $job['dead_letter_error'] = $error;

        $job['dead_letter_at'] = time();

        file_put_contents(

            $dir . '/' . preg_replace('/[^a-zA-Z0-9-]/', '', $jobId) . '.json',

            json_encode($job, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),

            LOCK_EX

        );

    }



    private function dispatchSignals(): void

    {

        if (function_exists('pcntl_signal_dispatch')) {

            pcntl_signal_dispatch();

        }

    }

}

