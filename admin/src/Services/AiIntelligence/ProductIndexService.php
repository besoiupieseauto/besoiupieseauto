<?php

declare(strict_types=1);

namespace Besoiu\Services\AiIntelligence;

use Besoiu\Services\AiRag\AiProductEmbeddingStore;
use Config\Database;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Indexare embeddings produse la create/update (Pas 5).
 */
final class ProductIndexService
{
    private PDO $pdo;
    private string $root;

    public function __construct(
        private readonly OllamaClient $ollama,
        private readonly AiProductEmbeddingStore $embeddingStore,
        ?PDO $pdo = null,
        ?string $projectRoot = null,
    ) {
        $this->root = $projectRoot ?? (defined('BESOIU_ROOT') ? (string) BESOIU_ROOT : dirname(__DIR__, 4));
        $this->pdo = $pdo ?? $this->resolvePdo();
    }

    public static function create(?string $projectRoot = null): self
    {
        $root = $projectRoot ?? (defined('BESOIU_ROOT') ? (string) BESOIU_ROOT : dirname(__DIR__, 4));

        return new self(
            OllamaClient::create($root),
            new AiProductEmbeddingStore(null, $root),
            null,
            $root,
        );
    }

    public static function autoIndexEnabled(): bool
    {
        $raw = $_ENV['AI_INTEL_AUTO_INDEX'] ?? getenv('AI_INTEL_AUTO_INDEX');
        if ($raw === false || $raw === null || $raw === '') {
            return true;
        }

        return in_array(strtolower(trim((string) $raw)), ['1', 'true', 'yes', 'on'], true);
    }

    public function indexById(int $productId): bool
    {
        $row = $this->fetchProductRow('id', (string) $productId);

        return $row !== null && $this->indexRow($row);
    }

    public function indexByRandomnId(string $randomnId): bool
    {
        $row = $this->fetchProductRow('randomn_id', $randomnId);

        return $row !== null && $this->indexRow($row);
    }

    /** @return array{indexed:int,errors:int,skipped:int} */
    public function indexBatch(int $limit = 400, ?int $sinceId = null): array
    {
        $limit = max(1, min(2000, $limit));
        $sql = "SELECT id, randomn_id, pName, pCode, pOem, pBrand, pCategory, pPrice, pStock
                FROM produse
                WHERE status <> '0' AND pName IS NOT NULL AND pName != ''";
        if ($sinceId !== null && $sinceId > 0) {
            $sql .= ' AND id >= ' . (int) $sinceId;
        }
        $sql .= ' ORDER BY id DESC LIMIT ' . $limit;

        $rows = $this->pdo->query($sql)?->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $indexed = 0;
        $errors = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            try {
                if ($this->indexRow($row)) {
                    $indexed++;
                } else {
                    $skipped++;
                }
            } catch (Throwable) {
                $errors++;
            }
        }

        return ['indexed' => $indexed, 'errors' => $errors, 'skipped' => $skipped];
    }

    /** @param array<string, mixed> $row */
    private function indexRow(array $row): bool
    {
        $text = $this->buildEmbeddingText($row);
        if ($text === '') {
            return false;
        }

        $vector = $this->ollama->getEmbedding($text);
        $this->embeddingStore->upsert(
            'produse',
            (string) ($row['id'] ?? ''),
            (string) ($row['pName'] ?? ''),
            $vector,
            $this->ollama->embedModel(),
            trim((string) ($row['pCode'] ?? '')) ?: null,
            trim((string) ($row['pOem'] ?? '')) ?: null,
        );

        return true;
    }

    /** @param array<string, mixed> $row */
    private function buildEmbeddingText(array $row): string
    {
        $parts = array_filter([
            trim((string) ($row['pName'] ?? '')),
            trim((string) ($row['pBrand'] ?? '')),
            trim((string) ($row['pCategory'] ?? '')),
            trim((string) ($row['pCode'] ?? '')),
            trim((string) ($row['pOem'] ?? '')) !== '' ? 'OEM:' . trim((string) ($row['pOem'] ?? '')) : '',
        ]);

        return trim(implode(' ', $parts));
    }

    /** @return array<string, mixed>|null */
    private function fetchProductRow(string $column, string $value): ?array
    {
        $allowed = ['id', 'randomn_id'];
        if (!in_array($column, $allowed, true)) {
            return null;
        }
        $sql = "SELECT id, randomn_id, pName, pCode, pOem, pBrand, pCategory, pPrice, pStock
                FROM produse WHERE {$column} = :val LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        if ($column === 'id') {
            $stmt->bindValue(':val', (int) $value, PDO::PARAM_INT);
        } else {
            $stmt->bindValue(':val', $value);
        }
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    private function resolvePdo(): PDO
    {
        if (class_exists(Database::class) && Database::hasConnection()) {
            return Database::getDB();
        }
        $config = require $this->root . '/admin/config/config.php';
        Database::getInstance(
            (string) ($config['db_host'] ?? '127.0.0.1'),
            (string) ($config['db_name'] ?? ''),
            (string) ($config['db_user'] ?? ''),
            (string) ($config['db_pass'] ?? '')
        );

        return Database::getDB();
    }
}
