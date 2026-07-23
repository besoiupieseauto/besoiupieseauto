<?php

declare(strict_types=1);

namespace Besoiu\Services\AiRag;

use Config\Database;
use PDO;
use Throwable;

/**
 * Index embeddings produse — MySQL JSON + cosine similarity (Faza 2, fără pgvector obligatoriu).
 */
final class AiProductEmbeddingStore
{
    private PDO $pdo;
    private string $root;

    public function __construct(?PDO $pdo = null, ?string $projectRoot = null)
    {
        $this->root = $projectRoot ?? (defined('BESOIU_ROOT') ? (string) BESOIU_ROOT : dirname(__DIR__, 4));
        $this->pdo = $pdo ?? $this->resolvePdo();
        $this->ensureTable();
    }

    private function resolvePdo(): PDO
    {
        if (class_exists(\Config\Database::class) && \Config\Database::hasConnection()) {
            return \Config\Database::getDB();
        }
        $config = require $this->root . '/admin/config/config.php';
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
            "CREATE TABLE IF NOT EXISTS ai_product_embeddings (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                source_type VARCHAR(32) NOT NULL DEFAULT 'produse',
                source_id VARCHAR(64) NOT NULL,
                label VARCHAR(500) NOT NULL DEFAULT '',
                sku VARCHAR(120) NULL,
                oem VARCHAR(120) NULL,
                embedding_json LONGTEXT NOT NULL,
                model VARCHAR(64) NOT NULL DEFAULT '',
                dims INT NOT NULL DEFAULT 0,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_ai_emb_source (source_type, source_id),
                KEY idx_ai_emb_sku (sku),
                KEY idx_ai_emb_oem (oem)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    /** @param list<float> $vector */
    public function upsert(string $sourceType, string $sourceId, string $label, array $vector, string $model, ?string $sku = null, ?string $oem = null): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO ai_product_embeddings (source_type, source_id, label, sku, oem, embedding_json, model, dims)
             VALUES (:st, :sid, :label, :sku, :oem, :emb, :model, :dims)
             ON DUPLICATE KEY UPDATE label=VALUES(label), sku=VALUES(sku), oem=VALUES(oem),
             embedding_json=VALUES(embedding_json), model=VALUES(model), dims=VALUES(dims)'
        );
        $stmt->execute([
            ':st' => $sourceType,
            ':sid' => $sourceId,
            ':label' => mb_substr($label, 0, 500),
            ':sku' => $sku,
            ':oem' => $oem,
            ':emb' => json_encode($vector),
            ':model' => $model,
            ':dims' => count($vector),
        ]);
    }

    /** @return array{indexed:int,errors:int} */
    public function indexFromProduse(int $limit = 400, ?OllamaEmbeddingsClient $client = null): array
    {
        $client = $client ?? new OllamaEmbeddingsClient($this->root);
        $limit = max(1, min(2000, $limit));
        $stmt = $this->pdo->query(
            "SELECT id, pName, pCode, pOem FROM produse WHERE pName IS NOT NULL AND pName != '' ORDER BY id DESC LIMIT {$limit}"
        );
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        $indexed = 0;
        $errors = 0;
        foreach ($rows as $row) {
            $text = trim((string) ($row['pName'] ?? ''));
            $code = trim((string) ($row['pCode'] ?? ''));
            $oem = trim((string) ($row['pOem'] ?? ''));
            if ($code !== '') {
                $text .= ' ' . $code;
            }
            if ($oem !== '') {
                $text .= ' OEM:' . $oem;
            }
            $emb = $client->embed($text);
            if (empty($emb['ok'])) {
                $errors++;

                continue;
            }
            $this->upsert(
                'produse',
                (string) ($row['id'] ?? ''),
                (string) ($row['pName'] ?? ''),
                $emb['vector'],
                (string) $emb['model'],
                $code !== '' ? $code : null,
                $oem !== '' ? $oem : null
            );
            $indexed++;
        }

        return ['indexed' => $indexed, 'errors' => $errors];
    }

    /**
     * @param list<float> $queryVector
     * @return list<array<string,mixed>>
     */
    public function searchSimilar(array $queryVector, int $limit = 5): array
    {
        if ($queryVector === []) {
            return [];
        }
        $limit = max(1, min(20, $limit));
        $stmt = $this->pdo->query('SELECT source_type, source_id, label, sku, oem, embedding_json, model FROM ai_product_embeddings ORDER BY updated_at DESC LIMIT 3000');
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        $scored = [];
        foreach ($rows as $row) {
            $vec = json_decode((string) ($row['embedding_json'] ?? ''), true);
            if (!is_array($vec) || $vec === []) {
                continue;
            }
            $score = self::cosineSimilarity($queryVector, array_map('floatval', $vec));
            $scored[] = [
                'source_type' => (string) ($row['source_type'] ?? ''),
                'source_id' => (string) ($row['source_id'] ?? ''),
                'label' => (string) ($row['label'] ?? ''),
                'sku' => (string) ($row['sku'] ?? ''),
                'oem' => (string) ($row['oem'] ?? ''),
                'score' => round($score, 4),
                'model' => (string) ($row['model'] ?? ''),
            ];
        }
        usort($scored, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return array_slice($scored, 0, $limit);
    }

    /** @return array<string, mixed> */
    public function status(): array
    {
        $count = (int) $this->pdo->query('SELECT COUNT(*) FROM ai_product_embeddings')->fetchColumn();
        $last = $this->pdo->query('SELECT MAX(updated_at) FROM ai_product_embeddings')->fetchColumn();

        return [
            'backend' => 'mysql_json_cosine',
            'documents_indexed' => $count,
            'last_index_at' => (string) ($last ?: ''),
            'embeddings_enabled' => true,
            'embedding_model' => (new OllamaEmbeddingsClient($this->root))->defaultModel(),
        ];
    }

    /** @param list<float> $a @param list<float> $b */
    public static function cosineSimilarity(array $a, array $b): float
    {
        $n = min(count($a), count($b));
        if ($n === 0) {
            return 0.0;
        }
        $dot = 0.0;
        $na = 0.0;
        $nb = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $dot += $a[$i] * $b[$i];
            $na += $a[$i] * $a[$i];
            $nb += $b[$i] * $b[$i];
        }
        if ($na <= 0.0 || $nb <= 0.0) {
            return 0.0;
        }

        return $dot / (sqrt($na) * sqrt($nb));
    }
}
