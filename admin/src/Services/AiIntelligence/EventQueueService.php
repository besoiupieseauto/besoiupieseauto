<?php

declare(strict_types=1);

namespace Besoiu\Services\AiIntelligence;

use Besoiu\Async\JobQueue;
use RuntimeException;

/**
 * Publică batch-uri de evenimente în Redis Streams (coadă ai-events).
 */
final class EventQueueService
{
    public const QUEUE_NAME = 'ai-events';
    public const ENVELOPE_KIND = 'ai_events_batch';

    public function __construct(
        private readonly JobQueue $jobQueue = new JobQueue(),
    ) {
    }

    /** @param list<array<string, mixed>> $events @param array<string, mixed>|null $visitor */
    public function enqueueBatch(string $sessionId, ?string $userId, string $source, array $events, ?array $visitor = null): string
    {
        if ($events === []) {
            throw new RuntimeException('Batch evenimente gol.');
        }

        $envelope = [
            'kind' => self::ENVELOPE_KIND,
            'session_id' => $sessionId,
            'user_id' => $userId,
            'source' => $source,
            'events' => $events,
            'received_at' => time(),
        ];
        if ($visitor !== null && $visitor !== []) {
            $envelope['visitor'] = $visitor;
        }

        return $this->jobQueue->push(self::QUEUE_NAME, $envelope, 'normal');
    }

    public function isRedisActive(): bool
    {
        return $this->jobQueue->isRedisActive();
    }

    public function isFileFallbackActive(): bool
    {
        return $this->jobQueue->isFileFallbackActive();
    }

    /** @return array{stream_id:string,envelope:array<string,mixed>,stream?:string}|null */
    public function pop(string $consumer = 'event-writer-1', int $blockMs = 1000): ?array
    {
        return $this->jobQueue->pop(self::QUEUE_NAME, $consumer, $blockMs);
    }

    /** @return array{stream_id:string,envelope:array<string,mixed>,stream?:string}|null */
    public function reclaimStale(string $consumer = 'event-writer-1', int $minIdleMs = 60000): ?array
    {
        return $this->jobQueue->reclaimStale(self::QUEUE_NAME, $consumer, $minIdleMs);
    }

    public function ack(string $streamId, string $consumer, ?string $stream = null): void
    {
        $this->jobQueue->ack(self::QUEUE_NAME, $streamId, $consumer, $stream);
    }
}
