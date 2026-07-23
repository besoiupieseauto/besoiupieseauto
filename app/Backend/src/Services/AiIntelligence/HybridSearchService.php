<?php

declare(strict_types=1);

namespace Besoiu\Services\AiIntelligence;

use Besoiu\Services\AiRag\AiProductEmbeddingStore;
use Config\Database;
use PDO;
use Throwable;

/**
 * Hybrid search — keyword SQL + embedding cosine (Pas 5).
 */
final class HybridSearchService
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

    /**
     * @param array<string, mixed> $options
     * @return array{ok:bool,query:string,hits:list<array<string,mixed>>,meta:array<string,mixed>}
     */
    public function hybridSearch(string $query, int $limit = 20, array $options = []): array
    {
        $query = trim($query);
        if ($query === '') {
            return ['ok' => false, 'query' => '', 'hits' => [], 'meta' => ['error' => 'Query gol']];
        }

        $limit = max(1, min(50, $limit));
        $candidateLimit = max($limit * 3, 30);
        $semanticWeight = (float) ($options['semantic_weight'] ?? 0.45);
        $keywordWeight = (float) ($options['keyword_weight'] ?? 0.55);
        $totalW = $semanticWeight + $keywordWeight;
        if ($totalW <= 0) {
            $semanticWeight = 0.45;
            $keywordWeight = 0.55;
            $totalW = 1.0;
        }
        $semanticWeight /= $totalW;
        $keywordWeight /= $totalW;

        $keywordHits = $this->keywordSearch($query, $candidateLimit);
        $semanticHits = $this->semanticSearch($query, $candidateLimit);

        $merged = [];
        foreach ($keywordHits as $hit) {
            $pid = (string) ($hit['product_id'] ?? '');
            if ($pid === '') {
                continue;
            }
            $merged[$pid] = $hit;
            $merged[$pid]['scores']['keyword'] = (float) ($hit['scores']['keyword'] ?? 0);
            $merged[$pid]['scores']['semantic'] = 0.0;
        }

        foreach ($semanticHits as $hit) {
            $pid = (string) ($hit['product_id'] ?? '');
            if ($pid === '') {
                continue;
            }
            if (!isset($merged[$pid])) {
                $merged[$pid] = $hit;
                $merged[$pid]['scores']['keyword'] = 0.0;
            }
            $merged[$pid]['scores']['semantic'] = max(
                (float) ($merged[$pid]['scores']['semantic'] ?? 0),
                (float) ($hit['scores']['semantic'] ?? 0)
            );
            if (($merged[$pid]['name'] ?? '') === '' && ($hit['name'] ?? '') !== '') {
                $merged[$pid]['name'] = $hit['name'];
            }
        }

        $hits = [];
        foreach ($merged as $row) {
            $kw = (float) ($row['scores']['keyword'] ?? 0);
            $sem = (float) ($row['scores']['semantic'] ?? 0);
            $hybrid = ($kw * $keywordWeight) + ($sem * $semanticWeight);
            $row['scores']['hybrid'] = round($hybrid, 4);
            $hits[] = $row;
        }

        usort($hits, static fn (array $a, array $b): int => ($b['scores']['hybrid'] ?? 0) <=> ($a['scores']['hybrid'] ?? 0));

        return [
            'ok' => true,
            'query' => $query,
            'hits' => array_slice($hits, 0, $limit),
            'meta' => [
                'backend' => 'mysql_keyword+embedding',
                'keyword_candidates' => count($keywordHits),
                'semantic_candidates' => count($semanticHits),
                'semantic_weight' => round($semanticWeight, 3),
                'keyword_weight' => round($keywordWeight, 3),
                'embed_model' => $this->ollama->embedModel(),
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    private function keywordSearch(string $query, int $limit): array
    {
        $terms = $this->extractTerms($query);
        $like = '%' . $query . '%';
        $sql = "SELECT id, randomn_id, pName, pCode, pOem, pBrand, pCategory, pSubcategory, pPrice, pStock, pImages
                FROM produse
                WHERE status <> '0'
                  AND (
                    pName LIKE :q1 OR pCode LIKE :q2 OR pOem LIKE :q3 OR pBrand LIKE :q4
                    OR pCategory LIKE :q5 OR pSubcategory LIKE :q6 OR pNote LIKE :q7
                  )
                ORDER BY id DESC
                LIMIT " . (int) $limit;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':q1' => $like,
            ':q2' => $like,
            ':q3' => $like,
            ':q4' => $like,
            ':q5' => $like,
            ':q6' => $like,
            ':q7' => $like,
        ]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $hits = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $score = $this->scoreKeywordRow($row, $query, $terms);
            $hits[] = $this->normalizeHit($row, ['keyword' => round($score, 4)]);
        }

        usort($hits, static fn (array $a, array $b): int => ($b['scores']['keyword'] ?? 0) <=> ($a['scores']['keyword'] ?? 0));

        return $hits;
    }

    /** @return list<array<string, mixed>> */
    private function semanticSearch(string $query, int $limit): array
    {
        try {
            $vector = $this->ollama->getEmbedding($query);
        } catch (Throwable) {
            return [];
        }

        $similar = $this->embeddingStore->searchSimilar($vector, $limit);
        if ($similar === []) {
            return [];
        }

        $ids = array_map(static fn (array $r): string => (string) ($r['source_id'] ?? ''), $similar);
        $ids = array_values(array_filter($ids, static fn (string $id): bool => $id !== ''));
        $products = $this->loadProductsByIds($ids);

        $hits = [];
        foreach ($similar as $row) {
            $pid = (string) ($row['source_id'] ?? '');
            if ($pid === '' || !isset($products[$pid])) {
                continue;
            }
            $hit = $this->normalizeHit($products[$pid], [
                'semantic' => (float) ($row['score'] ?? 0),
            ]);
            $hits[] = $hit;
        }

        return $hits;
    }

    /**
     * @param list<string> $ids
     * @return array<string, array<string, mixed>>
     */
    private function loadProductsByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT id, randomn_id, pName, pCode, pOem, pBrand, pCategory, pSubcategory, pPrice, pStock, pImages
             FROM produse WHERE id IN ({$placeholders}) AND status <> '0'"
        );
        $stmt->execute(array_map('intval', $ids));
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $out[(string) ($row['id'] ?? '')] = $row;
        }

        return $out;
    }

    /** @param array<string, mixed> $row @param array{keyword?:float,semantic?:float} $scores */
    private function normalizeHit(array $row, array $scores): array
    {
        $stock = (int) ($row['pStock'] ?? 0);
        $randomnId = (string) ($row['randomn_id'] ?? '');

        return [
            'product_id' => (string) ($row['id'] ?? ''),
            'randomn_id' => $randomnId,
            'name' => (string) ($row['pName'] ?? ''),
            'code' => (string) ($row['pCode'] ?? ''),
            'oem' => (string) ($row['pOem'] ?? ''),
            'brand' => (string) ($row['pBrand'] ?? ''),
            'category' => (string) ($row['pCategory'] ?? ''),
            'subcategory' => (string) ($row['pSubcategory'] ?? ''),
            'price_ron' => (float) ($row['pPrice'] ?? 0),
            'stock' => $stock,
            'in_stock' => $stock > 0,
            'image' => $this->firstImage($row['pImages'] ?? ''),
            'url' => $randomnId !== '' ? '/produs?id=' . rawurlencode($randomnId) : '',
            'scores' => [
                'keyword' => (float) ($scores['keyword'] ?? 0),
                'semantic' => (float) ($scores['semantic'] ?? 0),
                'hybrid' => 0.0,
                'popularity' => 0.0,
                'final' => 0.0,
            ],
        ];
    }

    /** @param array<string, mixed> $row @param list<string> $terms */
    private function scoreKeywordRow(array $row, string $query, array $terms): float
    {
        $qLower = mb_strtolower($query, 'UTF-8');
        $code = mb_strtolower((string) ($row['pCode'] ?? ''), 'UTF-8');
        $oem = mb_strtolower((string) ($row['pOem'] ?? ''), 'UTF-8');
        $name = mb_strtolower((string) ($row['pName'] ?? ''), 'UTF-8');

        if ($code !== '' && $code === $qLower) {
            return 1.0;
        }
        if ($oem !== '' && $oem === $qLower) {
            return 0.95;
        }
        if ($code !== '' && str_contains($code, $qLower)) {
            return 0.9;
        }
        if ($oem !== '' && str_contains($oem, $qLower)) {
            return 0.88;
        }

        if ($terms === []) {
            return str_contains($name, $qLower) ? 0.65 : 0.4;
        }

        $matched = 0;
        foreach ($terms as $term) {
            if (str_contains($name, $term) || str_contains($code, $term) || str_contains($oem, $term)) {
                $matched++;
            }
        }

        return min(0.85, 0.35 + ($matched / max(1, count($terms))) * 0.5);
    }

    /** @return list<string> */
    private function extractTerms(string $query): array
    {
        $q = mb_strtolower(trim($query), 'UTF-8');
        $parts = preg_split('/\s+/u', $q) ?: [];
        $terms = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part !== '' && mb_strlen($part, 'UTF-8') >= 2) {
                $terms[] = $part;
            }
        }

        return array_values(array_unique($terms));
    }

    private function firstImage(mixed $value): string
    {
        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                foreach ($decoded as $img) {
                    $img = trim((string) $img);
                    if ($img !== '') {
                        return $img;
                    }
                }
            }

            return $value;
        }

        return '';
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
