<?php



declare(strict_types=1);



namespace Besoiu\Async;



/**

 * Coadă centrală: Redis Streams (XADD/XREADGROUP) cu fallback fișier.

 * Workerii pop din coadă; nu comunică direct între ei.

 */

final class JobQueue

{

    private ?\Redis $redis = null;

    private bool $useRedis = false;

    private string $prefix;



    public function __construct()

    {

        $this->prefix = (string) AsyncConfig::get('redis_prefix', 'besoiu:async:');

        $this->bootRedis();

    }



    public function isRedisActive(): bool

    {

        return $this->useRedis && $this->redis instanceof \Redis;

    }



    public function isFileFallbackActive(): bool

    {

        return !$this->isRedisActive() && $this->fallbackToFilesEnabled();

    }



    /** @param array<string, mixed> $envelope */

    public function push(string $queue, array $envelope, string $priority = 'normal'): string

    {

        $envelope['priority'] = $priority;

        $envelope['queued_at'] = time();

        $payload = json_encode($envelope, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (!is_string($payload)) {

            throw new \RuntimeException('Nu pot serializa envelope job.');

        }



        if ($this->isRedisActive()) {

            $stream = $this->streamKey($queue, $priority);

            $this->ensureConsumerGroup($stream);

            $id = (string) $this->redis->xAdd($stream, '*', ['payload' => $payload]);



            return $id;

        }



        if (!$this->fallbackToFilesEnabled()) {

            throw new \RuntimeException('Redis indisponibil și ASYNC_FILE_FALLBACK=0 — nu pot enqueue job.');

        }



        return $this->pushFile($queue, $envelope, $priority);

    }



    /** @return array{stream_id:string,envelope:array<string,mixed>,stream?:string}|null */

    public function pop(string $queue, string $consumer = 'worker-1', int $blockMs = 1000): ?array

    {

        if ($this->isRedisActive()) {

            foreach ($this->priorityOrder() as $priority) {

                $stream = $this->streamKey($queue, $priority);

                $this->ensureConsumerGroup($stream);



                $messages = $this->redis->xReadGroup(

                    $this->consumerGroup(),

                    $consumer,

                    [$stream => '>'],

                    1,

                    $blockMs > 0 && $priority === $this->priorityOrder()[0] ? $blockMs : 1

                );



                if (!is_array($messages) || $messages === []) {

                    continue;

                }



                foreach ($messages as $entries) {

                    foreach ($entries as $entry) {

                        $id = (string) ($entry['id'] ?? '');

                        $raw = (string) ($entry['payload'] ?? '');

                        $decoded = json_decode($raw, true);

                        if (!is_array($decoded)) {

                            continue;

                        }



                        return ['stream_id' => $id, 'envelope' => $decoded, 'stream' => $stream];

                    }

                }

            }



            return null;

        }



        if (!$this->fallbackToFilesEnabled()) {

            throw new \RuntimeException('Redis indisponibil și ASYNC_FILE_FALLBACK=0 — nu pot pop job.');

        }



        return $this->popFile($queue);

    }



    /**

     * Reclaim mesaje stale din PEL (worker mort).

     *

     * @return array{stream_id:string,envelope:array<string,mixed>,stream?:string}|null

     */

    public function reclaimStale(string $queue, string $consumer, int $minIdleMs = 60000): ?array

    {

        if (!$this->isRedisActive()) {

            return null;

        }



        foreach ($this->priorityOrder() as $priority) {

            $stream = $this->streamKey($queue, $priority);

            $this->ensureConsumerGroup($stream);



            $claimed = $this->autoClaim($stream, $consumer, $minIdleMs);

            if ($claimed !== null) {

                $claimed['stream'] = $stream;



                return $claimed;

            }

        }



        return null;

    }



    public function ack(string $queue, string $streamId, string $consumer = 'worker-1', ?string $stream = null): void

    {

        if (!$this->isRedisActive()) {

            return;

        }



        $targetStream = $stream ?? $this->streamKey($queue);

        $this->redis->xAck($targetStream, $this->consumerGroup(), [$streamId]);

    }



    /** @return array{active:int,max:int} */

    public function countActiveWorkers(string $queue): array

    {

        $max = $this->queueMaxWorkers($queue);

        if (!$this->isRedisActive()) {

            return ['active' => 0, 'max' => $max];

        }



        $active = 0;

        foreach ($this->priorityOrder() as $priority) {

            $stream = $this->streamKey($queue, $priority);

            try {

                $info = $this->redis->xInfo('CONSUMERS', $stream, $this->consumerGroup());

                if (is_array($info)) {

                    $active += count($info);

                }

            } catch (\Throwable) {

                // Stream sau grup inexistent.

            }

        }



        return ['active' => $active, 'max' => $max];

    }



    public function registerWorkerSlot(string $queue, string $consumer): bool

    {

        if (!$this->isRedisActive()) {

            return true;

        }



        $max = $this->queueMaxWorkers($queue);

        $key = $this->prefix . 'workers:' . $queue;

        $this->redis->sAdd($key, $consumer);

        $this->redis->expire($key, (int) AsyncConfig::get('worker_heartbeat_sec', 15) * 4);

        $count = (int) $this->redis->sCard($key);



        return $count <= $max;

    }



    public function heartbeatWorkerSlot(string $queue, string $consumer): void

    {

        if (!$this->isRedisActive()) {

            return;

        }



        $key = $this->prefix . 'workers:' . $queue;

        $this->redis->sAdd($key, $consumer);

        $this->redis->expire($key, (int) AsyncConfig::get('worker_heartbeat_sec', 15) * 4);

    }



    /** @return array{stream_id:string,envelope:array<string,mixed>}|null */

    private function autoClaim(string $stream, string $consumer, int $minIdleMs): ?array

    {

        if (!$this->redis instanceof \Redis) {

            return null;

        }



        try {

            if (method_exists($this->redis, 'xAutoClaim')) {

                $result = $this->redis->xAutoClaim(

                    $stream,

                    $this->consumerGroup(),

                    $consumer,

                    $minIdleMs,

                    '0-0',

                    1

                );

                if (is_array($result)) {

                    $messages = $result[1] ?? $result['messages'] ?? null;

                    if (is_array($messages) && $messages !== []) {

                        return $this->decodeClaimedMessage($messages);

                    }

                }

            }

        } catch (\Throwable) {

            // Fallback XPENDING + XCLAIM.

        }



        try {

            $pending = $this->redis->xPending($stream, $this->consumerGroup(), '-', '+', 1);

            if (!is_array($pending) || $pending === []) {

                return null;

            }



            $entry = $pending[0] ?? null;

            if (!is_array($entry)) {

                return null;

            }



            $messageId = (string) ($entry[0] ?? '');

            $idleMs = (int) ($entry[2] ?? 0);

            if ($messageId === '' || $idleMs < $minIdleMs) {

                return null;

            }



            $claimed = $this->redis->xClaim(

                $stream,

                $this->consumerGroup(),

                $consumer,

                $minIdleMs,

                [$messageId]

            );

            if (!is_array($claimed) || $claimed === []) {

                return null;

            }



            return $this->decodeClaimedMessage($claimed);

        } catch (\Throwable) {

            return null;

        }

    }



    /** @param array<int, mixed> $messages */

    private function decodeClaimedMessage(array $messages): ?array

    {

        foreach ($messages as $entry) {

            if (!is_array($entry)) {

                continue;

            }

            $id = (string) ($entry[0] ?? $entry['id'] ?? '');

            $fields = $entry[1] ?? $entry['payload'] ?? null;

            $raw = '';

            if (is_array($fields)) {

                $raw = (string) ($fields['payload'] ?? $fields[0] ?? '');

            } elseif (is_string($fields)) {

                $raw = $fields;

            }

            $decoded = json_decode($raw, true);

            if ($id !== '' && is_array($decoded)) {

                return ['stream_id' => $id, 'envelope' => $decoded];

            }

        }



        return null;

    }



    private function streamKey(string $queue, string $priority = 'normal'): string

    {

        if (in_array($queue, ['import', 'products'], true) && $priority === 'high') {

            return $this->prefix . 'stream:' . $queue . ':high';

        }



        return $this->prefix . 'stream:' . $queue;

    }



    /** @return list<string> */

    private function priorityOrder(): array

    {

        return ['high', 'normal'];

    }



    private function consumerGroup(): string

    {

        return 'besoiu_workers';

    }



    private function ensureConsumerGroup(string $stream): void

    {

        if (!$this->redis instanceof \Redis) {

            return;

        }



        try {

            $this->redis->xGroup('CREATE', $stream, $this->consumerGroup(), '0', true);

        } catch (\Throwable) {

            // Grupul există deja.

        }

    }



    private function queueMaxWorkers(string $queue): int

    {

        /** @var array<string, array{max_workers:int,timeout_sec:int}> $queues */

        $queues = AsyncConfig::get('queues', []);



        return (int) ($queues[$queue]['max_workers'] ?? 1);

    }



    private function fallbackToFilesEnabled(): bool

    {

        return (bool) AsyncConfig::get('fallback_to_files', true);

    }



    /** @param array<string, mixed> $envelope */

    private function pushFile(string $queue, array $envelope, string $priority): string

    {

        $path = $this->queueFile($queue);

        $lock = $path . '.lock';

        $handle = fopen($lock, 'c+');

        if ($handle === false || !flock($handle, LOCK_EX)) {

            throw new \RuntimeException('Lock coadă indisponibil.');

        }



        $rows = [];

        if (is_file($path)) {

            $decoded = json_decode((string) file_get_contents($path), true);

            if (is_array($decoded)) {

                $rows = $decoded;

            }

        }



        $id = 'file-' . bin2hex(random_bytes(8));

        $rows[] = [

            'id' => $id,

            'priority' => $this->priorityScore($priority),

            'envelope' => $envelope,

        ];



        usort($rows, static fn (array $a, array $b): int => ($b['priority'] ?? 0) <=> ($a['priority'] ?? 0));

        file_put_contents($path, json_encode($rows, JSON_UNESCAPED_UNICODE), LOCK_EX);



        flock($handle, LOCK_UN);

        fclose($handle);



        return $id;

    }



    /** @return array{stream_id:string,envelope:array<string,mixed>}|null */

    private function popFile(string $queue): ?array

    {

        $path = $this->queueFile($queue);

        if (!is_file($path)) {

            return null;

        }



        $lock = $path . '.lock';

        $handle = fopen($lock, 'c+');

        if ($handle === false || !flock($handle, LOCK_EX)) {

            return null;

        }



        $rows = json_decode((string) file_get_contents($path), true);

        if (!is_array($rows) || $rows === []) {

            flock($handle, LOCK_UN);

            fclose($handle);



            return null;

        }



        $item = array_shift($rows);

        file_put_contents($path, json_encode($rows, JSON_UNESCAPED_UNICODE), LOCK_EX);

        flock($handle, LOCK_UN);

        fclose($handle);



        if (!is_array($item) || !is_array($item['envelope'] ?? null)) {

            return null;

        }



        return [

            'stream_id' => (string) ($item['id'] ?? ''),

            'envelope' => $item['envelope'],

        ];

    }



    private function queueFile(string $queue): string

    {

        $dir = AsyncConfig::storageDir() . '/queues';

        if (!is_dir($dir)) {

            mkdir($dir, 0775, true);

        }



        $safe = preg_replace('/[^a-z0-9_-]/i', '_', $queue) ?: 'default';



        return $dir . '/' . $safe . '.json';

    }



    private function priorityScore(string $priority): int

    {

        return match ($priority) {

            'high' => 90,

            'low' => 10,

            default => 50,

        };

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

