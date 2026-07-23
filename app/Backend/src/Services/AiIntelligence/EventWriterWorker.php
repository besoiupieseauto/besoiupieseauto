<?php

declare(strict_types=1);

namespace Besoiu\Services\AiIntelligence;

/**
 * Worker dedicat — consumă coada ai-events și scrie batch-uri în MySQL.
 *
 * Rulează în afara PHP-FPM: php admin/workers/event_writer_worker.php
 */
final class EventWriterWorker
{
    private bool $shutdownRequested = false;

    private readonly EventWriterService $writer;

    public function __construct(
        private readonly EventQueueService $queue = new EventQueueService(),
        ?EventWriterService $writer = null,
        private readonly string $consumer = 'event-writer-1',
    ) {
        $store = new EventTrackingStore();
        $this->writer = $writer ?? new EventWriterService($store);
    }

    public function requestShutdown(): void
    {
        $this->shutdownRequested = true;
    }

    public function run(int $maxBatches = 0, int $idleExitSec = 8): int
    {
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGTERM, fn () => $this->requestShutdown());
            pcntl_signal(SIGINT, fn () => $this->requestShutdown());
        }

        $processed = 0;
        $idleSince = time();

        while (($maxBatches === 0 || $processed < $maxBatches) && !$this->shutdownRequested) {
            if (function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }

            $item = $this->queue->pop($this->consumer, 1000);
            if ($item === null) {
                $item = $this->queue->reclaimStale($this->consumer);
            }

            if ($item === null) {
                if ($maxBatches > 0 && (time() - $idleSince) >= $idleExitSec) {
                    break;
                }
                usleep(250_000);
                continue;
            }

            $idleSince = time();
            $streamId = (string) ($item['stream_id'] ?? '');
            $stream = isset($item['stream']) ? (string) $item['stream'] : null;
            $envelope = is_array($item['envelope'] ?? null) ? $item['envelope'] : [];

            try {
                $this->writer->writeEnvelope($envelope);
            } catch (\Throwable $e) {
                fwrite(
                    STDERR,
                    '[event-writer] batch failed: ' . $e->getMessage() . PHP_EOL
                );
            } finally {
                if ($streamId !== '') {
                    $this->queue->ack($streamId, $this->consumer, $stream);
                }
            }

            ++$processed;
        }

        return $processed;
    }
}
