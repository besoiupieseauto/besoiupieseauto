<?php

declare(strict_types=1);

namespace Besoiu\Services;

use Config\Database;
use PDO;
use Throwable;

/**
 * Jurnal căutări fără rezultat — chat, WhatsApp, site.
 */
final class ShopSearchMissLogService
{
    public static function isEnabled(): bool
    {
        $raw = strtolower(trim((string) ($_ENV['SHOP_SEARCH_MISS_LOG'] ?? getenv('SHOP_SEARCH_MISS_LOG') ?: '1')));

        return !in_array($raw, ['0', 'false', 'no', 'off'], true);
    }

    public function logMiss(
        string $query,
        string $channel = 'chat',
        int $resultCount = 0,
        ?string $visitorKey = null,
        ?PDO $pdo = null
    ): void {
        if (!self::isEnabled()) {
            return;
        }

        $query = trim($query);
        if ($query === '' || $resultCount > 0) {
            return;
        }

        if (mb_strlen($query, 'UTF-8') < 3) {
            return;
        }

        $pdo = $pdo ?? Database::getDB();
        $type = $this->detectQueryType($query);
        $value = mb_substr($query, 0, 128, 'UTF-8');
        $notice = mb_substr('channel=' . $channel . ($visitorKey ? ';visitor=' . $visitorKey : ''), 0, 500, 'UTF-8');
        $ipHash = hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? 'cli'));

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO search_logs (query_type, query_value, found, result_count, notice, ip_hash, created_at)
                 VALUES (?, ?, 0, 0, ?, ?, NOW())'
            );
            $stmt->execute([$type, $value, $notice, $ipHash]);
        } catch (Throwable) {
            // tabel lipsă — ignorăm
        }
    }

    /** @return list<array<string, mixed>> */
    public function getTopMisses(int $days = 7, int $limit = 10, ?PDO $pdo = null): array
    {
        $pdo = $pdo ?? Database::getDB();
        $days = max(1, min(90, $days));
        $limit = max(1, min(50, $limit));

        try {
            $stmt = $pdo->prepare(
                "SELECT query_type, query_value, COUNT(*) AS miss_count, MAX(created_at) AS last_at
                 FROM search_logs
                 WHERE found = 0 AND created_at >= DATE_SUB(NOW(), INTERVAL {$days} DAY)
                 GROUP BY query_type, query_value
                 ORDER BY miss_count DESC, last_at DESC
                 LIMIT {$limit}"
            );
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return is_array($rows) ? $rows : [];
        } catch (Throwable) {
            return [];
        }
    }

    public function formatTopMissesContext(int $days = 7, int $limit = 10, ?PDO $pdo = null): string
    {
        $rows = $this->getTopMisses($days, $limit, $pdo);
        if ($rows === []) {
            return "### Căutări fără rezultat (ultimele {$days} zile)\nNicio înregistrare.";
        }

        $lines = ['### Căutări fără rezultat (ultimele ' . $days . ' zile)'];
        foreach ($rows as $row) {
            $lines[] = '- [' . ($row['query_type'] ?? '?') . '] '
                . ($row['query_value'] ?? '')
                . ' — ' . ($row['miss_count'] ?? 0) . '× (ultima: ' . ($row['last_at'] ?? '') . ')';
        }

        return implode("\n", $lines);
    }

    private function detectQueryType(string $query): string
    {
        if (preg_match('/\b([A-HJ-NPR-Z0-9]{17})\b/i', $query)) {
            return 'vin';
        }
        if (preg_match('/\b(?:oem|cod)\b/iu', $query) || preg_match('/\b[0-9]{5,}[A-Za-z0-9\-_.\/]*\b/u', $query)) {
            return 'oem';
        }

        return 'name';
    }
}
