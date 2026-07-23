<?php

declare(strict_types=1);

namespace Besoiu\Services\AiIntelligence;

use InvalidArgumentException;
use RuntimeException;

/**
 * Validează payload tracking și publică în coadă (fără scriere directă în events).
 */
final class EventIngestService
{
    private const MAX_EVENTS_PER_REQUEST = 40;
    private const MAX_EVENTS_PER_HOUR = 400;

    public function __construct(
        private readonly EventTrackingStore $store,
        private readonly EventQueueService $queue,
        private readonly ?string $projectRoot = null,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed>|null $visitorContext
     * @return array{queued:int,session_id:string,transport:string}
     */
    public function ingest(array $payload, ?array $visitorContext = null): array
    {
        $sessionId = $this->sanitizeSessionId((string) ($payload['session_id'] ?? ''));
        if ($sessionId === '') {
            $sessionId = $this->generateSessionId();
        }

        $userId = $this->sanitizeUserId($payload['user_id'] ?? null);
        $source = $this->sanitizeSource((string) ($payload['source'] ?? 'web'));

        $rawEvents = $payload['events'] ?? [];
        if (!is_array($rawEvents) || $rawEvents === []) {
            throw new InvalidArgumentException('Lista evenimente goală.');
        }
        if (count($rawEvents) > self::MAX_EVENTS_PER_REQUEST) {
            throw new InvalidArgumentException('Prea multe evenimente într-un singur request.');
        }

        if (!$this->rateLimitOk($sessionId, count($rawEvents))) {
            throw new RuntimeException('rate_limit');
        }

        $events = [];
        foreach ($rawEvents as $event) {
            if (!is_array($event)) {
                continue;
            }
            $normalized = $this->normalizeEvent($event);
            if ($normalized !== null) {
                $events[] = $normalized;
            }
        }

        if ($events === []) {
            throw new InvalidArgumentException('Niciun eveniment valid în batch.');
        }

        $streamId = $this->queue->enqueueBatch($sessionId, $userId, $source, $events, $visitorContext);

        $transport = 'redis';
        if (!$this->queue->isRedisActive()) {
            $transport = $this->queue->isFileFallbackActive() ? 'file' : 'unknown';
        }

        return [
            'queued' => count($events),
            'session_id' => $sessionId,
            'stream_id' => $streamId,
            'transport' => $transport,
        ];
    }

    /** @param array<string, mixed> $event @return array<string, mixed>|null */
    private function normalizeEvent(array $event): ?array
    {
        $eventType = trim((string) ($event['event_type'] ?? $event['action'] ?? ''));
        if ($eventType === '') {
            return null;
        }

        // Compat tracker vechi → tipuri canonice
        $eventType = $this->mapLegacyAction($eventType, $event);

        if (!$this->store->isEventTypeAllowed($eventType)) {
            return null;
        }

        $entityType = isset($event['entity_type']) ? trim((string) $event['entity_type']) : null;
        $entityId = isset($event['entity_id']) ? trim((string) $event['entity_id']) : null;

        if ($entityType === '') {
            $entityType = null;
        }
        if ($entityId === '') {
            $entityId = null;
        }

        $metadata = $event['metadata'] ?? $event['meta'] ?? [];
        if (!is_array($metadata)) {
            $metadata = [];
        }

        // Propagă subject vechi în metadata dacă lipsește
        if (!isset($metadata['subject']) && isset($event['subject'])) {
            $metadata['subject'] = mb_substr((string) $event['subject'], 0, 200);
        }

        $ts = trim((string) ($event['ts'] ?? ''));
        if ($ts === '') {
            $ts = gmdate('Y-m-d\TH:i:s.v\Z');
        }

        return [
            'event_type' => $eventType,
            'entity_type' => $entityType,
            'entity_id' => $entityId !== null ? mb_substr($entityId, 0, 100) : null,
            'metadata' => $metadata,
            'ts' => $ts,
        ];
    }

    /** @param array<string, mixed> $event */
    private function mapLegacyAction(string $action, array $event): string
    {
        $meta = is_array($event['meta'] ?? null) ? $event['meta'] : [];
        $metadata = is_array($event['metadata'] ?? null) ? $event['metadata'] : [];
        $ctx = array_merge($meta, $metadata);

        return match ($action) {
            'search' => 'search_query',
            'product_click' => isset($ctx['from_search']) ? 'search_result_click' : 'recommendation_click',
            'cart_view' => 'page_view',
            default => $action,
        };
    }

    public function sanitizeSessionId(string $sid): string
    {
        $sid = preg_replace('/[^a-zA-Z0-9\-_]/', '', trim($sid)) ?? '';

        return mb_substr($sid, 0, 36);
    }

    private function sanitizeUserId(mixed $userId): ?string
    {
        if ($userId === null || $userId === '') {
            return null;
        }
        $id = preg_replace('/[^a-zA-Z0-9\-_]/', '', trim((string) $userId)) ?? '';

        return $id !== '' ? mb_substr($id, 0, 64) : null;
    }

    private function sanitizeSource(string $source): string
    {
        $source = preg_replace('/[^a-zA-Z0-9_\-]/', '', strtolower(trim($source))) ?? '';

        return $source !== '' ? mb_substr($source, 0, 50) : 'web';
    }

    private function generateSessionId(): string
    {
        return $this->uuidV4();
    }

    private function rateLimitOk(string $sessionId, int $incomingCount): bool
    {
        if ($incomingCount < 1) {
            return false;
        }

        $dir = ($this->projectRoot ?? (defined('BESOIU_ROOT') ? (string) BESOIU_ROOT : dirname(__DIR__, 4)))
            . '/app/Storage/ai_intel/rate';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $file = $dir . '/' . md5($sessionId) . '.json';
        $now = time();
        $window = 3600;

        $state = ['count' => 0, 'reset_at' => $now + $window];
        if (is_file($file)) {
            $json = json_decode((string) file_get_contents($file), true);
            if (is_array($json)) {
                $state = $json;
            }
        }

        if (($state['reset_at'] ?? 0) < $now) {
            $state = ['count' => 0, 'reset_at' => $now + $window];
        }

        if ((int) ($state['count'] ?? 0) + $incomingCount > self::MAX_EVENTS_PER_HOUR) {
            return false;
        }

        $state['count'] = (int) ($state['count'] ?? 0) + $incomingCount;
        @file_put_contents($file, json_encode($state));

        return true;
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
