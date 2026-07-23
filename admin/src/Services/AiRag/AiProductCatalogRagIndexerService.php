<?php

declare(strict_types=1);

namespace Besoiu\Services\AiRag;

use Besoiu\Services\AdminDatabaseResolver;
use Besoiu\Services\AiAgentContextLibraryService;
use PDO;
use Throwable;

/**
 * Indexează automat în corpus RAG + biblioteca agent-produse după publicare din coada import.
 */
final class AiProductCatalogRagIndexerService
{
    private string $root;

    /** @var list<int> */
    private static array $batchQueue = [];

    private static bool $batchMode = false;

    public function __construct(?string $projectRoot = null)
    {
        $this->root = $projectRoot
            ?? (defined('BESOIU_ROOT') ? (string) BESOIU_ROOT : dirname(__DIR__, 4));
    }

    public static function create(?string $projectRoot = null): self
    {
        return new self($projectRoot);
    }

    public static function beginBatch(): void
    {
        self::$batchMode = true;
        self::$batchQueue = [];
    }

    public static function endBatch(?PDO $pdo = null): array
    {
        self::$batchMode = false;
        $ids = array_values(array_unique(array_filter(self::$batchQueue, static fn (int $id): bool => $id > 0)));
        self::$batchQueue = [];
        if ($ids === [] || $pdo === null) {
            return ['ok' => true, 'indexed' => 0, 'corpus' => 0, 'library' => 0, 'embeddings' => 0];
        }

        return self::create()->indexProductIds($pdo, $ids, ['source' => 'import_publish_batch']);
    }

    public function queueProductId(PDO $pdo, int $productId): void
    {
        if ($productId <= 0 || !$this->isEnabled()) {
            return;
        }

        if (self::$batchMode) {
            self::$batchQueue[] = $productId;

            return;
        }

        $this->indexProductIds($pdo, [$productId], ['source' => 'import_publish']);
    }

    /**
     * @param list<int> $productIds
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function indexProductIds(PDO $pdo, array $productIds, array $context = []): array
    {
        if (!$this->isEnabled()) {
            return ['ok' => true, 'indexed' => 0, 'skipped' => count($productIds), 'reason' => 'disabled'];
        }

        $productIds = array_values(array_unique(array_filter(array_map('intval', $productIds), static fn (int $id): bool => $id > 0)));
        if ($productIds === []) {
            return ['ok' => true, 'indexed' => 0];
        }

        $rows = $this->loadProducts($pdo, $productIds);
        if ($rows === []) {
            return ['ok' => true, 'indexed' => 0, 'missing' => count($productIds)];
        }

        $entries = [];
        foreach ($rows as $row) {
            $entry = $this->buildCorpusEntry($row, $context);
            if ($entry !== null) {
                $entries[] = $entry;
            }
        }

        if ($entries === []) {
            return ['ok' => true, 'indexed' => 0];
        }

        $corpus = new AiRagCorpusService($this->root);
        $corpusResult = $corpus->replaceManyBySource($entries);

        $library = new AiAgentContextLibraryService($this->root);
        $librarySynced = 0;
        if (count($entries) <= 50) {
            foreach ($entries as $entry) {
                $text = trim((string) ($entry['text'] ?? ''));
                if ($text === '') {
                    continue;
                }
                if ($library->appendEntry('agent-produse', [
                    'text' => $text,
                    'source' => 'operator',
                    'tags' => array_values(array_unique(array_merge(
                        ['import-publish', 'produse', 'catalog'],
                        is_array($entry['tags'] ?? null) ? $entry['tags'] : []
                    ))),
                ]) !== null) {
                    ++$librarySynced;
                }
            }
        }

        $embeddings = 0;
        if ($this->embedOnPublish() && count($productIds) <= 25) {
            $embeddings = $this->indexEmbeddings($pdo, $rows);
        }

        return [
            'ok' => true,
            'indexed' => count($entries),
            'corpus_replaced' => (int) ($corpusResult['replaced'] ?? 0),
            'corpus_added' => (int) ($corpusResult['added'] ?? 0),
            'library_synced' => $librarySynced,
            'embeddings' => $embeddings,
            'source' => (string) ($context['source'] ?? 'import_publish'),
        ];
    }

    /** @param array<string, mixed> $context @return array<string, mixed>|null */
    private function buildCorpusEntry(array $row, array $context = []): ?array
    {
        $name = trim((string) ($row['pName'] ?? ''));
        if ($name === '') {
            return null;
        }

        $productId = (string) ($row['id'] ?? '');
        $code = trim((string) ($row['pCode'] ?? ''));
        $oem = trim((string) ($row['pOem'] ?? ''));
        $cat = trim((string) ($row['pCategory'] ?? ''));
        $sub = trim((string) ($row['pSubcategory'] ?? ''));
        $brand = trim((string) ($row['pBrand'] ?? ''));
        $price = trim((string) ($row['pPrice'] ?? ''));
        $note = trim(strip_tags((string) ($row['pNote'] ?? '')));
        $importId = (int) ($context['import_id'] ?? 0);

        $parts = array_filter([
            'Produs Besoiu: ' . $name,
            $code !== '' ? 'Cod: ' . $code : '',
            $oem !== '' ? 'OEM: ' . $oem : '',
            $brand !== '' ? 'Brand: ' . $brand : '',
            $cat !== '' ? 'Categorie: ' . $cat : '',
            $sub !== '' ? 'Subcategorie: ' . $sub : '',
            $price !== '' ? 'Preț: ' . $price . ' RON' : '',
            !empty($row['pVitrina']) ? 'Vitrină: da' : '',
            $note !== '' ? 'Notă: ' . mb_substr($note, 0, 280) : '',
            $importId > 0 ? 'Import coadă #' . $importId : '',
        ]);

        return [
            'source_type' => 'produse',
            'source_id' => $productId,
            'title' => $name,
            'text' => implode(' | ', $parts),
            'tags' => ['produse', 'mysql', 'catalog', 'import-publish'],
            'keywords' => array_values(array_filter([$code, $oem, $cat, $sub, $brand])),
        ];
    }

    /** @param list<int> $productIds @return list<array<string, mixed>> */
    private function loadProducts(PDO $pdo, array $productIds): array
    {
        if (!AdminDatabaseResolver::hasTable($pdo, 'produse')) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($productIds), '?'));
        $cols = 'id, pName, pCode, pOem, pCategory, pSubcategory, pBrand, pPrice, pNote, status';
        if (AdminDatabaseResolver::hasColumn($pdo, 'produse', 'pVitrina')) {
            $cols .= ', pVitrina';
        }

        try {
            $stmt = $pdo->prepare(
                "SELECT {$cols} FROM produse WHERE id IN ({$placeholders}) AND (status IS NULL OR status <> '0')"
            );
            $stmt->execute($productIds);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return is_array($rows) ? $rows : [];
        } catch (Throwable) {
            return [];
        }
    }

    /** @param list<array<string, mixed>> $rows */
    private function indexEmbeddings(PDO $pdo, array $rows): int
    {
        try {
            $store = new AiProductEmbeddingStore($pdo, $this->root);
            $client = new OllamaEmbeddingsClient($this->root);
            $indexed = 0;
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
                if ($text === '') {
                    continue;
                }
                $emb = $client->embed($text);
                if (empty($emb['ok']) || !is_array($emb['vector'] ?? null)) {
                    continue;
                }
                $store->upsert(
                    'produse',
                    (string) ($row['id'] ?? ''),
                    (string) ($row['pName'] ?? ''),
                    $emb['vector'],
                    (string) ($emb['model'] ?? ''),
                    $code !== '' ? $code : null,
                    $oem !== '' ? $oem : null
                );
                ++$indexed;
            }

            return $indexed;
        } catch (Throwable) {
            return 0;
        }
    }

    private function isEnabled(): bool
    {
        $raw = strtolower(trim((string) (getenv('AI_RAG_AUTO_INDEX_ON_PUBLISH') ?: ($_ENV['AI_RAG_AUTO_INDEX_ON_PUBLISH'] ?? '1'))));

        return !in_array($raw, ['0', 'false', 'no', 'off'], true);
    }

    private function embedOnPublish(): bool
    {
        $raw = strtolower(trim((string) (getenv('AI_RAG_EMBED_ON_PUBLISH') ?: ($_ENV['AI_RAG_EMBED_ON_PUBLISH'] ?? '0'))));

        return in_array($raw, ['1', 'true', 'yes', 'on'], true);
    }
}
