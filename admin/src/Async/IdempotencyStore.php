<?php



declare(strict_types=1);



namespace Besoiu\Async;



final class IdempotencyStore

{

    private ?\Redis $redis = null;

    private bool $useRedis = false;

    private string $prefix;



    public function __construct()

    {

        $this->prefix = (string) AsyncConfig::get('redis_prefix', 'besoiu:async:');

        $this->bootRedis();

    }



    /**

     * Atomic: un singur job per cheie, chiar și la POST-uri paralele.

     *

     * @param callable(): array{job_id:string,status:string} $createJob

     * @return array{job_id:string,status:string,replayed:bool}

     */

    public function rememberOrGetExisting(string $key, callable $createJob): array

    {

        if ($this->useRedis && $this->redis instanceof \Redis) {

            return $this->rememberOrGetExistingRedis($key, $createJob);

        }



        return $this->rememberOrGetExistingFile($key, $createJob);

    }



    public function findExistingJobId(string $key): ?string

    {

        $record = $this->read($key);

        if ($record === null) {

            return null;

        }



        $expires = (int) ($record['expires_at'] ?? 0);

        if ($expires > 0 && $expires < time()) {

            $this->forget($key);



            return null;

        }



        $jobId = (string) ($record['job_id'] ?? '');

        if ($jobId === '' || $jobId === '__creating__') {

            return null;

        }



        return $jobId;

    }



    public function remember(string $key, string $jobId): void

    {

        $ttl = (int) AsyncConfig::get('idempotency_ttl_sec', 86400);

        $this->write($key, [

            'job_id' => $jobId,

            'expires_at' => time() + $ttl,

            'created_at' => time(),

        ]);

    }



    /**

     * @param callable(): array{job_id:string,status:string} $createJob

     * @return array{job_id:string,status:string,replayed:bool}

     */

    private function rememberOrGetExistingRedis(string $key, callable $createJob): array

    {

        if (!$this->redis instanceof \Redis) {

            return $this->rememberOrGetExistingFile($key, $createJob);

        }



        $redisKey = $this->redisKey($key);

        $ttl = (int) AsyncConfig::get('idempotency_ttl_sec', 86400);



        for ($attempt = 0; $attempt < 30; ++$attempt) {

            $existing = $this->redis->get($redisKey);

            if (is_string($existing) && $existing !== '' && $existing !== '__creating__') {

                return ['job_id' => $existing, 'status' => 'pending', 'replayed' => true];

            }



            if ($this->redis->set($redisKey, '__creating__', ['NX', 'EX' => 60])) {

                try {

                    $created = $createJob();

                    $jobId = (string) ($created['job_id'] ?? '');

                    if ($jobId === '') {

                        $this->redis->del($redisKey);

                        throw new \RuntimeException('Idempotency: job_id lipsă.');

                    }

                    $this->redis->set($redisKey, $jobId, ['EX' => $ttl]);



                    return [

                        'job_id' => $jobId,

                        'status' => (string) ($created['status'] ?? 'pending'),

                        'replayed' => false,

                    ];

                } catch (\Throwable $e) {

                    $this->redis->del($redisKey);

                    throw $e;

                }

            }



            usleep(50_000);

        }



        $final = $this->redis->get($redisKey);

        if (is_string($final) && $final !== '' && $final !== '__creating__') {

            return ['job_id' => $final, 'status' => 'pending', 'replayed' => true];

        }



        throw new \RuntimeException('Idempotency: timeout așteptare job paralel.');

    }



    /**

     * @param callable(): array{job_id:string,status:string} $createJob

     * @return array{job_id:string,status:string,replayed:bool}

     */

    private function rememberOrGetExistingFile(string $key, callable $createJob): array

    {

        $lockPath = $this->path($key) . '.lock';

        $dir = dirname($lockPath);

        if (!is_dir($dir)) {

            mkdir($dir, 0775, true);

        }



        $handle = fopen($lockPath, 'c+');

        if ($handle === false || !flock($handle, LOCK_EX)) {

            throw new \RuntimeException('Idempotency: lock indisponibil.');

        }



        try {

            $existing = $this->findExistingJobId($key);

            if ($existing !== null) {

                return ['job_id' => $existing, 'status' => 'pending', 'replayed' => true];

            }



            $created = $createJob();

            $jobId = (string) ($created['job_id'] ?? '');

            if ($jobId === '') {

                throw new \RuntimeException('Idempotency: job_id lipsă.');

            }



            $this->remember($key, $jobId);



            return [

                'job_id' => $jobId,

                'status' => (string) ($created['status'] ?? 'pending'),

                'replayed' => false,

            ];

        } finally {

            flock($handle, LOCK_UN);

            fclose($handle);

        }

    }



    /** @return array{job_id:string,expires_at:int,created_at:int}|null */

    private function read(string $key): ?array

    {

        if ($this->useRedis && $this->redis instanceof \Redis) {

            $raw = $this->redis->get($this->redisKey($key));

            if (!is_string($raw) || $raw === '' || $raw === '__creating__') {

                return null;

            }



            return [

                'job_id' => $raw,

                'expires_at' => 0,

                'created_at' => time(),

            ];

        }



        $path = $this->path($key);

        if (!is_file($path)) {

            return null;

        }



        $decoded = json_decode((string) file_get_contents($path), true);



        return is_array($decoded) ? $decoded : null;

    }



    /** @param array{job_id:string,expires_at:int,created_at:int} $record */

    private function write(string $key, array $record): void

    {

        if ($this->useRedis && $this->redis instanceof \Redis) {

            $ttl = max(1, (int) ($record['expires_at'] ?? 0) - time());

            $this->redis->set($this->redisKey($key), (string) $record['job_id'], ['EX' => $ttl]);



            return;

        }



        $dir = AsyncConfig::storageDir() . '/idempotency';

        if (!is_dir($dir)) {

            mkdir($dir, 0775, true);

        }



        file_put_contents($this->path($key), json_encode($record), LOCK_EX);

    }



    private function forget(string $key): void

    {

        if ($this->useRedis && $this->redis instanceof \Redis) {

            $this->redis->del($this->redisKey($key));



            return;

        }



        $path = $this->path($key);

        if (is_file($path)) {

            unlink($path);

        }

    }



    private function path(string $key): string

    {

        $hash = hash('sha256', $key);



        return AsyncConfig::storageDir() . '/idempotency/' . $hash . '.json';

    }



    private function redisKey(string $key): string

    {

        return $this->prefix . 'idem:' . hash('sha256', $key);

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

