<?php

declare(strict_types=1);

namespace Besoiu\Services\AiRag;

use PDO;
use Throwable;

/**
 * Bibliotecă research piață — date structurate + brut (trasabilitate sursă).
 */
final class AiMarketResearchStore
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null, ?string $projectRoot = null)
    {
        $root = $projectRoot ?? (defined('BESOIU_ROOT') ? (string) BESOIU_ROOT : dirname(__DIR__, 4));
        $this->pdo = $pdo ?? $this->resolvePdo($root);
        $this->ensureTable();
    }

    private function resolvePdo(string $root): PDO
    {
        $dbClass = $root . '/app/Config/Database.php';
        if (is_file($dbClass)) {
            require_once $dbClass;
        }
        if (class_exists(\Config\Database::class) && \Config\Database::hasConnection()) {
            return \Config\Database::getDB();
        }
        $config = require $root . '/admin/config/config.php';
        \Config\Database::getInstance(
            (string) ($config['db_host'] ?? '127.0.0.1'),
            (string) ($config['db_name'] ?? ''),
            (string) ($config['db_user'] ?? ''),
            (string) ($config['db_pass'] ?? '')
        );

        return \Config\Database::getDB();
    }

    private function ensureTable(): void
    {
        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS ai_market_research_entries (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                entry_uuid VARCHAR(36) NOT NULL,
                tip_sursa VARCHAR(64) NOT NULL,
                source_id VARCHAR(64) NULL,
                url_sursa VARCHAR(2000) NOT NULL,
                data_scraping DATETIME NOT NULL,
                continut_brut LONGTEXT NULL,
                continut_procesat_json LONGTEXT NULL,
                embedding_id VARCHAR(64) NULL,
                confidence_extractie DECIMAL(6,4) DEFAULT 0,
                status_validare VARCHAR(32) NOT NULL DEFAULT 'auto',
                seo_json LONGTEXT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_market_uuid (entry_uuid),
                KEY idx_market_tip (tip_sursa),
                KEY idx_market_source (source_id),
                KEY idx_market_scraped (data_scraping)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    /** @param array<string, mixed> $row */
    public function insert(array $row): string
    {
        $uuid = (string) ($row['entry_uuid'] ?? bin2hex(random_bytes(16)));
        $stmt = $this->pdo->prepare(
            'INSERT INTO ai_market_research_entries
            (entry_uuid, tip_sursa, source_id, url_sursa, data_scraping, continut_brut,
             continut_procesat_json, embedding_id, confidence_extractie, status_validare, seo_json)
            VALUES (:uuid, :tip, :sid, :url, :scraped, :brut, :proc, :emb, :conf, :st, :seo)'
        );
        $stmt->execute([
            ':uuid' => $uuid,
            ':tip' => (string) ($row['tip_sursa'] ?? 'other'),
            ':sid' => (string) ($row['source_id'] ?? ''),
            ':url' => mb_substr((string) ($row['url_sursa'] ?? ''), 0, 2000),
            ':scraped' => (string) ($row['data_scraping'] ?? date('Y-m-d H:i:s')),
            ':brut' => (string) ($row['continut_brut'] ?? ''),
            ':proc' => json_encode($row['continut_procesat'] ?? null, JSON_UNESCAPED_UNICODE),
            ':emb' => (string) ($row['embedding_id'] ?? ''),
            ':conf' => (float) ($row['confidence_extractie'] ?? 0),
            ':st' => (string) ($row['status_validare'] ?? 'auto'),
            ':seo' => json_encode($row['seo'] ?? null, JSON_UNESCAPED_UNICODE),
        ]);

        return $uuid;
    }

    /** @return array<string, mixed>|null */
    public function getByUuid(string $uuid): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM ai_market_research_entries WHERE entry_uuid = :uuid LIMIT 1');
        $stmt->execute([':uuid' => $uuid]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->mapRow($row) : null;
    }

    public function updateValidationStatus(string $uuid, string $status): bool
    {
        if (!in_array($status, ['validat_manual', 'respins', 'auto'], true)) {
            return false;
        }
        $stmt = $this->pdo->prepare(
            'UPDATE ai_market_research_entries SET status_validare = :st WHERE entry_uuid = :uuid LIMIT 1'
        );
        $stmt->execute([':st' => $status, ':uuid' => $uuid]);

        return $stmt->rowCount() > 0;
    }

    /** @return list<array<string, mixed>> */
    public function search(string $query, int $limit = 10, ?string $tipSursa = null): array
    {
        $limit = max(1, min(50, $limit));
        $sql = 'SELECT * FROM ai_market_research_entries WHERE 1=1';
        $params = [];
        if ($tipSursa !== null && $tipSursa !== '') {
            $sql .= ' AND tip_sursa = :tip';
            $params[':tip'] = $tipSursa;
        }
        if (trim($query) !== '') {
            $sql .= ' AND (continut_brut LIKE :q OR continut_procesat_json LIKE :q OR url_sursa LIKE :q)';
            $params[':q'] = '%' . trim($query) . '%';
        }
        $sql .= ' ORDER BY data_scraping DESC LIMIT ' . $limit;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map([$this, 'mapRow'], $rows);
    }

    /** @return array<string, mixed> */
    public function auditSummary(): array
    {
        $stmt = $this->pdo->query(
            "SELECT source_id, tip_sursa,
                    COUNT(*) AS total,
                    MAX(data_scraping) AS last_ok,
                    SUM(CASE WHEN status_validare = 'respins' THEN 1 ELSE 0 END) AS respinse
             FROM ai_market_research_entries
             GROUP BY source_id, tip_sursa
             ORDER BY last_ok DESC"
        );
        $bySource = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

        return [
            'total_entries' => (int) $this->pdo->query('SELECT COUNT(*) FROM ai_market_research_entries')->fetchColumn(),
            'by_source' => $bySource ?: [],
        ];
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function mapRow(array $row): array
    {
        foreach (['continut_procesat_json', 'seo_json'] as $key) {
            if (!empty($row[$key]) && is_string($row[$key])) {
                $decoded = json_decode($row[$key], true);
                if (is_array($decoded)) {
                    $row[str_replace('_json', '', $key)] = $decoded;
                }
            }
        }

        return $row;
    }
}
