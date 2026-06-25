<?php

declare(strict_types=1);

namespace Evasystem\Controllers\SearchLogs;

/**
 * Serviciu admin pentru jurnalul căutărilor VIN/OEM.
 */
final class SearchLogsService
{
    /** @param array<string, mixed> $filters @return array<string, mixed> */
    public function list(array $filters = []): array
    {
        require_once dirname(__DIR__, 4) . '/system/tecdoc_stock.php';

        $pdo = tecdoc_db();
        $queryFilters = $this->normalizeFilters($filters);

        return [
            'items' => search_logs_query($pdo, $queryFilters),
            'total' => search_logs_count($pdo, $queryFilters),
            'stats' => search_logs_stats($pdo),
            'top_missing' => search_logs_top_missing($pdo, 10),
            'top_found' => search_logs_top_found($pdo, 10),
        ];
    }

    /** @return array<string, int> */
    public function stats(): array
    {
        require_once dirname(__DIR__, 4) . '/system/tecdoc_stock.php';

        return search_logs_stats(tecdoc_db());
    }

    /** @return array{items: array<int, array<string, mixed>>, stats: array<string, int>, codes_count: int} */
    public function topMissing(int $limit = 100): array
    {
        require_once dirname(__DIR__, 4) . '/system/tecdoc_stock.php';

        $pdo = tecdoc_db();
        $limit = max(1, min(500, $limit));

        return [
            'items' => search_logs_top_missing($pdo, $limit),
            'stats' => search_logs_stats($pdo),
            'codes_count' => search_logs_missing_codes_count($pdo),
        ];
    }

    /** @param array<string, mixed> $filters */
    public function exportCsv(array $filters = []): string
    {
        require_once dirname(__DIR__, 4) . '/system/tecdoc_stock.php';

        $pdo = tecdoc_db();
        $queryFilters = $this->normalizeFilters($filters);
        $queryFilters['limit'] = max(1, min(5000, (int) ($filters['limit'] ?? 2000)));

        return search_logs_csv_content(search_logs_export_rows($pdo, $queryFilters));
    }

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    private function normalizeFilters(array $filters): array
    {
        return [
            'limit' => max(1, min(500, (int) ($filters['limit'] ?? 100))),
            'offset' => max(0, (int) ($filters['offset'] ?? 0)),
            'not_found_only' => !empty($filters['not_found_only']) || (string) ($filters['not_found'] ?? '') === '1',
            'found' => array_key_exists('found', $filters) ? (string) $filters['found'] : '',
            'query_type' => trim((string) ($filters['query_type'] ?? '')),
            'q' => trim((string) ($filters['q'] ?? '')),
            'date_from' => trim((string) ($filters['date_from'] ?? '')),
            'date_to' => trim((string) ($filters['date_to'] ?? '')),
        ];
    }
}
