<?php

declare(strict_types=1);

namespace Besoiu\Services;

/**
 * Serviciu admin — jurnal erori sistem (procesare fundal).
 */
final class SystemErrorsService
{
    private function boot(): void
    {
        require_once dirname(__DIR__, 3) . '/system/system_errors.php';
    }

    private function pdo(): \PDO
    {
        $this->boot();
        require_once dirname(__DIR__, 3) . '/system/tecdoc_stock.php';

        return tecdoc_db();
    }

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    public function list(array $filters = []): array
    {
        $pdo = $this->pdo();
        $queryFilters = $this->normalizeFilters($filters);

        return [
            'items' => \system_errors_query($pdo, $queryFilters),
            'total' => \system_errors_count($pdo, $queryFilters),
            'stats' => \system_errors_stats($pdo),
            'recent' => \system_errors_recent_by_channel($pdo, 10),
        ];
    }

    /** @return array<string, mixed> */
    public function archiveStatus(): array
    {
        return (new SystemErrorsArchiveService())->status();
    }

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    public function archiveForAi(string $period, array $filters = []): array
    {
        return (new SystemErrorsArchiveService())->archiveForAi($period, $this->normalizeFilters($filters));
    }

    /** @param array<string, mixed> $filters */
    public function countForArchive(string $period, array $filters = []): int
    {
        $this->boot();

        return \system_errors_count_for_archive($this->pdo(), $period, $this->normalizeFilters($filters));
    }

    /** @return array<string, mixed> */
    public function stats(): array
    {
        return \system_errors_stats($this->pdo());
    }

    public function markResolved(int $id, bool $resolved = true): bool
    {
        return \system_errors_mark_resolved($this->pdo(), $id, $resolved);
    }

    /** @param list<int> $ids */
    public function markResolvedBulk(array $ids, bool $resolved = true): int
    {
        $this->boot();

        return \system_errors_mark_resolved_bulk($this->pdo(), $ids, $resolved);
    }

    /** @param array<string, mixed> $filters */
    public function markAllResolved(array $filters = []): int
    {
        $this->boot();

        return \system_errors_mark_all_resolved($this->pdo(), $this->normalizeFilters($filters));
    }

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    private function normalizeFilters(array $filters): array
    {
        return [
            'limit' => max(1, min(500, (int) ($filters['limit'] ?? 100))),
            'offset' => max(0, (int) ($filters['offset'] ?? 0)),
            'unresolved_only' => !empty($filters['unresolved_only']) || (string) ($filters['resolved'] ?? '') === '0',
            'resolved_only' => !empty($filters['resolved_only']),
            'include_archived' => !empty($filters['include_archived']),
            'archived_only' => !empty($filters['archived_only']),
            'level' => trim((string) ($filters['level'] ?? '')),
            'channel' => trim((string) ($filters['channel'] ?? '')),
            'q' => trim((string) ($filters['q'] ?? '')),
            'date_from' => trim((string) ($filters['date_from'] ?? '')),
            'date_to' => trim((string) ($filters['date_to'] ?? '')),
            'date_preset' => trim((string) ($filters['date_preset'] ?? '')),
        ];
    }
}
