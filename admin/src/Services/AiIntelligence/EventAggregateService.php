<?php

declare(strict_types=1);

namespace Besoiu\Services\AiIntelligence;

use Config\Database;
use DateTimeImmutable;
use PDO;
use Throwable;

/**
 * Recalculează ai_intel_aggregates din evenimente brute (job orar).
 */
final class EventAggregateService
{
    private PDO $pdo;

    public function __construct(
        private readonly EventTrackingStore $store,
        ?PDO $pdo = null,
        private readonly ?string $projectRoot = null,
    ) {
        $this->pdo = $pdo ?? $this->resolvePdo();
    }

    /** @return array{period_date:string,rows:int,events_scanned:int} */
    public function runForDate(?string $periodDate = null): array
    {
        $this->store->ensureTables();

        $date = $periodDate ?? (new DateTimeImmutable('today'))->format('Y-m-d');
        $start = $date . ' 00:00:00.000';
        $end = $date . ' 23:59:59.999';

        /** @var array<string, array{entity_id:string,entity_type:string,views:int,clicks:int,purchases:int}> $buckets */
        $buckets = [];

        $stmt = $this->pdo->prepare(
            'SELECT event_type, entity_type, entity_id, metadata
             FROM ' . EventTrackingStore::TABLE_EVENTS . '
             WHERE ts >= :start AND ts <= :end'
        );
        $stmt->execute([':start' => $start, ':end' => $end]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $eventsScanned = count($rows);

        foreach ($rows as $row) {
            $eventType = (string) ($row['event_type'] ?? '');
            $entityType = (string) ($row['entity_type'] ?? '');
            $entityId = (string) ($row['entity_id'] ?? '');
            $metadata = $this->decodeMetadata($row['metadata'] ?? null);

            if ($eventType === 'purchase') {
                $this->applyPurchase($buckets, $metadata);
                continue;
            }

            if ($entityId === '' || $entityType === '') {
                if ($eventType === 'search_query') {
                    $query = (string) ($metadata['query'] ?? $metadata['query_normalized'] ?? '');
                    if ($query !== '') {
                        $this->bump($buckets, 'search', $this->entityKey($query), 'views', 1);
                    }
                }
                continue;
            }

            match ($eventType) {
                'page_view', 'product_view' => $this->bump($buckets, $entityType, $entityId, 'views', 1),
                'search_result_click', 'recommendation_click' => $this->bump($buckets, $entityType, $entityId, 'clicks', 1),
                default => null,
            };
        }

        $written = $this->persistAggregates($date, $buckets);

        return [
            'period_date' => $date,
            'rows' => $written,
            'events_scanned' => $eventsScanned,
        ];
    }

    /** @return array{days:int,total_rows:int} */
    public function runRolling(int $days = 1): array
    {
        $days = max(1, min(7, $days));
        $totalRows = 0;
        for ($i = 0; $i < $days; ++$i) {
            $date = (new DateTimeImmutable('today'))->modify("-{$i} days")->format('Y-m-d');
            $result = $this->runForDate($date);
            $totalRows += (int) ($result['rows'] ?? 0);
        }

        return ['days' => $days, 'total_rows' => $totalRows];
    }

    /**
     * @param array<string, array{entity_id:string,entity_type:string,views:int,clicks:int,purchases:int}> $buckets
     */
    private function applyPurchase(array &$buckets, array $metadata): void
    {
        $productIds = $metadata['product_ids'] ?? [];
        if (!is_array($productIds)) {
            return;
        }
        foreach ($productIds as $pid) {
            $pid = trim((string) $pid);
            if ($pid === '') {
                continue;
            }
            $this->bump($buckets, 'product', $pid, 'purchases', 1);
        }
    }

    /**
     * @param array<string, array{entity_id:string,entity_type:string,views:int,clicks:int,purchases:int}> $buckets
     */
    private function bump(array &$buckets, string $entityType, string $entityId, string $field, int $amount): void
    {
        $key = $entityType . '|' . $entityId;
        if (!isset($buckets[$key])) {
            $buckets[$key] = [
                'entity_id' => $entityId,
                'entity_type' => $entityType,
                'views' => 0,
                'clicks' => 0,
                'purchases' => 0,
            ];
        }
        $buckets[$key][$field] += $amount;
    }

    /**
     * @param array<string, array{entity_id:string,entity_type:string,views:int,clicks:int,purchases:int}> $buckets
     */
    private function persistAggregates(string $periodDate, array $buckets): int
    {
        if ($buckets === []) {
            return 0;
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO ' . EventTrackingStore::TABLE_AGGREGATES . '
            (entity_id, entity_type, period_date, views, clicks, purchases, ctr)
            VALUES (:entity_id, :entity_type, :period_date, :views, :clicks, :purchases, :ctr)
            ON DUPLICATE KEY UPDATE
                views = VALUES(views),
                clicks = VALUES(clicks),
                purchases = VALUES(purchases),
                ctr = VALUES(ctr)'
        );

        $written = 0;
        foreach ($buckets as $bucket) {
            $views = (int) $bucket['views'];
            $clicks = (int) $bucket['clicks'];
            $ctr = $views > 0 ? round($clicks / $views, 4) : null;

            $stmt->execute([
                ':entity_id' => $bucket['entity_id'],
                ':entity_type' => $bucket['entity_type'],
                ':period_date' => $periodDate,
                ':views' => $views,
                ':clicks' => $clicks,
                ':purchases' => (int) $bucket['purchases'],
                ':ctr' => $ctr,
            ]);
            ++$written;
        }

        return $written;
    }

    /** @return array<string, mixed> */
    private function decodeMetadata(mixed $raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }
        if (!is_string($raw) || $raw === '') {
            return [];
        }
        $json = json_decode($raw, true);

        return is_array($json) ? $json : [];
    }

    private function entityKey(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');

        return 'q_' . substr(md5($value), 0, 16);
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
}
