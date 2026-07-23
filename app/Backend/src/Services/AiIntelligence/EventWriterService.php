<?php

declare(strict_types=1);

namespace Besoiu\Services\AiIntelligence;

use Config\Database;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PDOException;
use Throwable;

/**
 * Scrie batch-uri validate în ai_intel_sessions + ai_intel_events.
 */
final class EventWriterService
{
    private PDO $pdo;

    public function __construct(
        private readonly EventTrackingStore $store,
        ?PDO $pdo = null,
        private readonly ?string $projectRoot = null,
    ) {
        $this->pdo = $pdo ?? $this->resolvePdo();
    }

    /**
     * @param array<string, mixed> $envelope
     * @return array{written:int,session_id:string}
     */
    public function writeEnvelope(array $envelope): array
    {
        if (($envelope['kind'] ?? '') !== EventQueueService::ENVELOPE_KIND) {
            throw new \InvalidArgumentException('Envelope necunoscut.');
        }

        $sessionId = trim((string) ($envelope['session_id'] ?? ''));
        if ($sessionId === '') {
            throw new \InvalidArgumentException('session_id lipsă.');
        }

        $events = $envelope['events'] ?? [];
        if (!is_array($events) || $events === []) {
            throw new \InvalidArgumentException('Batch evenimente gol.');
        }

        $userId = isset($envelope['user_id']) && $envelope['user_id'] !== ''
            ? (string) $envelope['user_id']
            : null;
        $source = trim((string) ($envelope['source'] ?? 'web'));
        if ($source === '') {
            $source = 'web';
        }

        $visitor = is_array($envelope['visitor'] ?? null) ? $envelope['visitor'] : null;

        return $this->writeBatch($sessionId, $userId, $source, $events, $visitor);
    }

    /**
     * @param list<array<string, mixed>> $events
     * @param array<string, mixed>|null $visitor
     * @return array{written:int,session_id:string}
     */
    public function writeBatch(string $sessionId, ?string $userId, string $source, array $events, ?array $visitor = null): array
    {
        $this->store->ensureTables();

        $written = 0;
        $landingPage = null;
        $this->pdo->beginTransaction();

        try {
            foreach ($events as $event) {
                if (!is_array($event)) {
                    continue;
                }
                if (($event['event_type'] ?? '') === 'page_view') {
                    $meta = is_array($event['metadata'] ?? null) ? $event['metadata'] : [];
                    $landingPage = (string) ($meta['path'] ?? $meta['url'] ?? '');
                    if ($landingPage !== '') {
                        break;
                    }
                }
            }

            $this->upsertSession($sessionId, $userId, $source, $visitor, $landingPage, count($events));

            $stmt = $this->pdo->prepare(
                'INSERT INTO ' . EventTrackingStore::TABLE_EVENTS . '
                (event_id, session_id, event_type, entity_type, entity_id, metadata, ts)
                VALUES (:event_id, :session_id, :event_type, :entity_type, :entity_id, :metadata, :ts)'
            );

            foreach ($events as $event) {
                if (!is_array($event)) {
                    continue;
                }
                $eventType = trim((string) ($event['event_type'] ?? ''));
                if ($eventType === '' || !$this->store->isEventTypeAllowed($eventType)) {
                    continue;
                }

                $entityType = isset($event['entity_type']) ? trim((string) $event['entity_type']) : null;
                $entityId = isset($event['entity_id']) ? trim((string) $event['entity_id']) : null;
                if ($entityType === '') {
                    $entityType = null;
                }
                if ($entityId === '') {
                    $entityId = null;
                }

                $metadata = $event['metadata'] ?? [];
                if (!is_array($metadata)) {
                    $metadata = [];
                }

                $ts = $this->normalizeTimestamp((string) ($event['ts'] ?? ''));

                $stmt->execute([
                    ':event_id' => $this->uuidV4(),
                    ':session_id' => $sessionId,
                    ':event_type' => $eventType,
                    ':entity_type' => $entityType,
                    ':entity_id' => $entityId !== null ? mb_substr($entityId, 0, 100) : null,
                    ':metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
                    ':ts' => $ts,
                ]);
                ++$written;
            }

            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        return ['written' => $written, 'session_id' => $sessionId];
    }

    /**
     * Actualizează context vizitator imediat (înainte/după coadă) — IP, geo, device.
     *
     * @param array<string, mixed> $visitor
     */
    public function upsertVisitorContext(
        string $sessionId,
        ?string $userId,
        string $source,
        array $visitor,
        ?string $landingPage = null,
    ): void {
        $this->store->ensureTables();
        $this->upsertSession($sessionId, $userId, $source, $visitor, $landingPage, 0);
    }

    /** @param array<string, mixed>|null $visitor */
    private function upsertSession(
        string $sessionId,
        ?string $userId,
        string $source,
        ?array $visitor,
        ?string $landingPage,
        int $newEvents,
    ): void {
        $this->store->ensureSessionVisitorColumns();

        $fields = [
            'ip_address' => null,
            'country_code' => null,
            'country_name' => null,
            'city' => null,
            'region' => null,
            'user_agent' => null,
            'device_type' => null,
            'browser' => null,
            'os' => null,
            'referrer' => null,
        ];
        if ($visitor !== null) {
            foreach (array_keys($fields) as $key) {
                $val = $visitor[$key] ?? null;
                if ($val === null || $val === '') {
                    continue;
                }
                $fields[$key] = mb_substr((string) $val, 0, $key === 'user_agent' ? 512 : 80);
            }
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO ' . EventTrackingStore::TABLE_SESSIONS . '
            (session_id, user_id, source, started_at, ip_address, country_code, country_name, city, region,
             user_agent, device_type, browser, os, referrer, landing_page, last_seen_at, event_count)
             VALUES (:sid, :uid, :source, NOW(3), :ip, :cc, :cn, :city, :region, :ua, :dev, :browser, :os, :ref, :land, NOW(3), :ecnt)
             ON DUPLICATE KEY UPDATE
                user_id = COALESCE(VALUES(user_id), user_id),
                source = VALUES(source),
                ip_address = COALESCE(NULLIF(VALUES(ip_address), ''), ip_address),
                country_code = COALESCE(NULLIF(VALUES(country_code), ''), country_code),
                country_name = COALESCE(NULLIF(VALUES(country_name), ''), country_name),
                city = COALESCE(NULLIF(VALUES(city), ''), city),
                region = COALESCE(NULLIF(VALUES(region), ''), region),
                user_agent = COALESCE(NULLIF(VALUES(user_agent), ''), user_agent),
                device_type = COALESCE(NULLIF(VALUES(device_type), ''), device_type),
                browser = COALESCE(NULLIF(VALUES(browser), ''), browser),
                os = COALESCE(NULLIF(VALUES(os), ''), os),
                referrer = COALESCE(NULLIF(VALUES(referrer), ''), referrer),
                landing_page = COALESCE(landing_page, VALUES(landing_page)),
                last_seen_at = NOW(3),
                event_count = event_count + VALUES(event_count)'
        );
        $stmt->execute([
            ':sid' => $sessionId,
            ':uid' => $userId,
            ':source' => mb_substr($source, 0, 50),
            ':ip' => $fields['ip_address'],
            ':cc' => $fields['country_code'] !== null ? mb_substr((string) $fields['country_code'], 0, 2) : null,
            ':cn' => $fields['country_name'],
            ':city' => $fields['city'],
            ':region' => $fields['region'],
            ':ua' => $fields['user_agent'],
            ':dev' => $fields['device_type'],
            ':browser' => $fields['browser'],
            ':os' => $fields['os'],
            ':ref' => $fields['referrer'] !== null ? mb_substr((string) $fields['referrer'], 0, 500) : null,
            ':land' => $landingPage !== null && $landingPage !== '' ? mb_substr($landingPage, 0, 500) : null,
            ':ecnt' => max(0, $newEvents),
        ]);
    }

    private function normalizeTimestamp(string $ts): string
    {
        if ($ts === '') {
            return (new DateTimeImmutable('now', new DateTimeZone('UTC')))
                ->format('Y-m-d H:i:s.v');
        }

        try {
            $dt = new DateTimeImmutable($ts);

            return $dt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s.v');
        } catch (Throwable) {
            return (new DateTimeImmutable('now', new DateTimeZone('UTC')))
                ->format('Y-m-d H:i:s.v');
        }
    }

    private function resolvePdo(): PDO
    {
        $root = $this->projectRoot ?? (defined('BESOIU_ROOT') ? (string) BESOIU_ROOT : dirname(__DIR__, 4));
        if (class_exists(Database::class) && Database::hasConnection()) {
            return Database::getDB();
        }
        $config = require $root . '/admin/config/config.php';
        Database::getInstance(
            (string) ($config['db_host'] ?? '127.0.0.1'),
            (string) ($config['db_name'] ?? ''),
            (string) ($config['db_user'] ?? ''),
            (string) ($config['db_pass'] ?? '')
        );

        return Database::getDB();
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
