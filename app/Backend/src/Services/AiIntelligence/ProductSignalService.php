<?php

declare(strict_types=1);

namespace Besoiu\Services\AiIntelligence;

use Besoiu\Services\AiRag\AiProductEmbeddingStore;
use Config\Database;
use PDO;
use Throwable;

/**
 * Semnale agregate per produs (CTR, views) — input pentru re-ranker.
 */
final class ProductSignalService
{
    private PDO $pdo;

    public function __construct(
        private readonly EventTrackingStore $store,
        ?PDO $pdo = null,
        ?string $projectRoot = null,
    ) {
        $root = $projectRoot ?? (defined('BESOIU_ROOT') ? (string) BESOIU_ROOT : dirname(__DIR__, 4));
        $this->pdo = $pdo ?? $this->resolvePdo($root);
    }

    /**
     * @param list<string|int> $productIds
     * @return array<string, array{views:int,clicks:int,purchases:int,ctr:float,score:float}>
     */
    public function signalsForProducts(array $productIds, int $days = 7): array
    {
        $this->store->ensureTables();
        $ids = [];
        foreach ($productIds as $id) {
            $id = trim((string) $id);
            if ($id !== '') {
                $ids[$id] = true;
            }
        }
        if ($ids === []) {
            return [];
        }

        $days = max(1, min(30, $days));
        $since = (new \DateTimeImmutable('today'))->modify('-' . ($days - 1) . ' days')->format('Y-m-d');

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $params = array_merge(array_keys($ids), [$since]);
        $sql = 'SELECT entity_id,
                       SUM(views) AS views,
                       SUM(clicks) AS clicks,
                       SUM(purchases) AS purchases,
                       AVG(ctr) AS ctr
                FROM ' . EventTrackingStore::TABLE_AGGREGATES . "
                WHERE entity_type = 'product'
                  AND entity_id IN ({$placeholders})
                  AND period_date >= ?
                GROUP BY entity_id";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $maxViews = 1;
        $maxPurchases = 1;
        foreach ($rows as $row) {
            $maxViews = max($maxViews, (int) ($row['views'] ?? 0));
            $maxPurchases = max($maxPurchases, (int) ($row['purchases'] ?? 0));
        }

        $out = [];
        foreach ($rows as $row) {
            $id = (string) ($row['entity_id'] ?? '');
            if ($id === '') {
                continue;
            }
            $views = (int) ($row['views'] ?? 0);
            $clicks = (int) ($row['clicks'] ?? 0);
            $purchases = (int) ($row['purchases'] ?? 0);
            $ctr = (float) ($row['ctr'] ?? 0.0);
            if ($ctr <= 0.0 && $views > 0) {
                $ctr = $clicks / $views;
            }

            $normViews = $views / $maxViews;
            $normPurchases = $purchases / $maxPurchases;
            $score = min(1.0, ($normViews * 0.35) + (min(1.0, $ctr) * 0.40) + ($normPurchases * 0.25));

            $out[$id] = [
                'views' => $views,
                'clicks' => $clicks,
                'purchases' => $purchases,
                'ctr' => round($ctr, 4),
                'score' => round($score, 4),
            ];
        }

        return $out;
    }

    private function resolvePdo(string $root): PDO
    {
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
