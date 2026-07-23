<?php

declare(strict_types=1);

namespace Besoiu\Services\AiRag;

use Besoiu\Services\AiAgentContextLibraryService;
use Config\Database;
use PDO;
use Throwable;

/**
 * Seed 500 elemente RAG din DB, ePiesa index, furnizori, context agenți.
 */
final class AiRagBulkSeedService
{
    private string $root;

    public function __construct(?string $projectRoot = null)
    {
        $this->root = $projectRoot ?? (defined('BESOIU_ROOT') ? (string) BESOIU_ROOT : dirname(__DIR__, 4));
    }

    /**
     * @param array<string, mixed> $options target, clear, index_embeddings, agent_slug
     * @return array<string, mixed>
     */
    public function seed(array $options = []): array
    {
        $target = max(50, min(2000, (int) ($options['target'] ?? 500)));
        $clear = !empty($options['clear']);
        $corpus = new AiRagCorpusService($this->root);
        if ($clear) {
            $corpus->clear();
        }

        $pdo = $this->pdo();
        $entries = [];
        $entries = array_merge($entries, $this->fromProduse($pdo, (int) round($target * 0.5)));
        $entries = array_merge($entries, $this->fromCategories($pdo, (int) round($target * 0.1)));
        $entries = array_merge($entries, $this->fromEpiesaRag((int) round($target * 0.24)));
        $entries = array_merge($entries, $this->fromFurnizori($pdo, (int) round($target * 0.06)));
        $entries = array_merge($entries, $this->fromAgentLibraries((int) round($target * 0.1)));

        $entries = array_slice($entries, 0, $target);
        $result = $corpus->appendMany($entries);

        $suppliersIndexed = AiSupplierCatalogRagIndexerService::create($this->root)->indexAllFromDatabase($pdo);

        $embedding = null;
        if (!empty($options['index_embeddings'])) {
            $store = new AiProductEmbeddingStore($pdo, $this->root);
            $embedding = $store->indexFromProduse(min(500, $target));
        }

        $agentSlug = trim((string) ($options['agent_slug'] ?? 'agent-produse'));
        if ($agentSlug !== '') {
            $library = new AiAgentContextLibraryService($this->root);
            $synced = 0;
            foreach (array_slice($entries, 0, 80) as $e) {
                if ($library->appendEntry($agentSlug, [
                    'text' => (string) ($e['text'] ?? ''),
                    'source' => 'operator',
                    'tags' => array_merge(['corpus-seed'], (array) ($e['tags'] ?? [])),
                    'pinned' => !empty($e['pinned']),
                ]) !== null) {
                    ++$synced;
                }
            }
            $result['agent_library_synced'] = $synced;
        }

        return array_merge($result, [
            'ok' => true,
            'target' => $target,
            'prepared' => count($entries),
            'status' => $corpus->status(),
            'embeddings' => $embedding,
            'suppliers_indexed' => (int) ($suppliersIndexed['indexed'] ?? 0),
        ]);
    }

    /** @return list<array<string, mixed>> */
    private function fromProduse(PDO $pdo, int $limit): array
    {
        $limit = max(1, $limit);
        $cols = 'id, pName, pCode, pOem, pCategory, pNote, status';
        if ($this->hasColumn($pdo, 'produse', 'pVitrina')) {
            $cols .= ', pVitrina';
        }
        try {
            $stmt = $pdo->query(
                "SELECT {$cols}
                 FROM produse
                 WHERE pName IS NOT NULL AND TRIM(pName) <> '' AND status <> '0'
                 ORDER BY id DESC
                 LIMIT {$limit}"
            );
            $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        } catch (Throwable) {
            return [];
        }
        $out = [];
        foreach ($rows as $row) {
            $name = trim((string) ($row['pName'] ?? ''));
            $code = trim((string) ($row['pCode'] ?? ''));
            $oem = trim((string) ($row['pOem'] ?? ''));
            $cat = trim((string) ($row['pCategory'] ?? ''));
            $note = trim(strip_tags((string) ($row['pNote'] ?? '')));
            $parts = array_filter([
                'Produs Besoiu: ' . $name,
                $code !== '' ? 'Cod: ' . $code : '',
                $oem !== '' ? 'OEM: ' . $oem : '',
                $cat !== '' ? 'Categorie: ' . $cat : '',
                !empty($row['pVitrina']) ? 'Vitrină: da' : '',
                $note !== '' ? 'Notă: ' . mb_substr($note, 0, 200) : '',
            ]);
            $out[] = [
                'source_type' => 'produse',
                'source_id' => (string) ($row['id'] ?? ''),
                'title' => $name,
                'text' => implode(' | ', $parts),
                'tags' => ['produse', 'mysql', 'catalog'],
                'keywords' => array_filter([$code, $oem, $cat]),
            ];
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    private function fromCategories(PDO $pdo, int $limit): array
    {
        $limit = max(1, $limit);
        try {
            $stmt = $pdo->query(
                "SELECT pCategory AS cat, COUNT(*) AS cnt
                 FROM produse
                 WHERE status <> '0' AND pCategory IS NOT NULL AND TRIM(pCategory) <> ''
                 GROUP BY pCategory
                 ORDER BY cnt DESC
                 LIMIT {$limit}"
            );
            $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        } catch (Throwable) {
            return [];
        }
        $out = [];
        foreach ($rows as $row) {
            $cat = trim((string) ($row['cat'] ?? ''));
            $cnt = (int) ($row['cnt'] ?? 0);
            if ($cat === '') {
                continue;
            }
            $out[] = [
                'source_type' => 'category',
                'source_id' => $cat,
                'title' => $cat,
                'text' => "Categorie catalog Besoiu: {$cat} — {$cnt} produse active.",
                'tags' => ['category', 'mysql'],
            ];
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    private function fromEpiesaRag(int $limit): array
    {
        $path = $this->root . '/app/Import/MatchingPro/config/rag/showcase_epiesa_rag.json';
        if (!is_file($path)) {
            return [];
        }
        $json = json_decode((string) file_get_contents($path), true);
        $chunks = is_array($json['chunks'] ?? null) ? $json['chunks'] : [];
        $out = [];
        foreach ($chunks as $chunk) {
            if (!is_array($chunk)) {
                continue;
            }
            if ((string) ($chunk['verdict'] ?? '') !== 'include') {
                continue;
            }
            $text = trim((string) ($chunk['text'] ?? ''));
            if ($text === '') {
                continue;
            }
            $out[] = [
                'source_type' => 'epiesa',
                'source_id' => (string) ($chunk['id'] ?? ''),
                'title' => (string) ($chunk['epiesa_subcategory'] ?? $chunk['showcase_type'] ?? 'ePiesa'),
                'text' => $text,
                'url' => (string) ($chunk['epiesa_url'] ?? ''),
                'tags' => ['epiesa', 'vitrina', (string) ($chunk['showcase_type'] ?? '')],
                'keywords' => is_array($chunk['keywords'] ?? null) ? $chunk['keywords'] : [],
            ];
            if (count($out) >= $limit) {
                break;
            }
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    private function fromFurnizori(PDO $pdo, int $limit): array
    {
        $limit = max(1, $limit);
        try {
            $stmt = $pdo->query(
                "SELECT id, name, code, status FROM furnizori
                 WHERE name IS NOT NULL AND TRIM(name) <> ''
                 ORDER BY id DESC LIMIT {$limit}"
            );
            $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        } catch (Throwable) {
            return [];
        }
        $out = [];
        foreach ($rows as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            $code = trim((string) ($row['code'] ?? ''));
            $out[] = [
                'source_type' => 'supplier',
                'source_id' => (string) ($row['id'] ?? ''),
                'title' => $name,
                'text' => 'Furnizor Besoiu: ' . $name . ($code !== '' ? ' | Cod: ' . $code : '') . ' | Status: ' . ($row['status'] ?? 'active'),
                'tags' => ['furnizor', 'mysql'],
            ];
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    private function fromAgentLibraries(int $limit): array
    {
        $library = new AiAgentContextLibraryService($this->root);
        $slugs = ['agent-produse', 'agent-statistici', 'agent-clienti', 'agent-imagini', 'context-master'];
        $out = [];
        foreach ($slugs as $slug) {
            foreach ($library->listEntries($slug, 40) as $entry) {
                $text = trim((string) ($entry['text'] ?? ''));
                if ($text === '' || mb_strlen($text) < 12) {
                    continue;
                }
                $out[] = [
                    'source_type' => 'learned',
                    'source_id' => (string) ($entry['id'] ?? ''),
                    'title' => $slug,
                    'text' => $text,
                    'tags' => array_merge(['agent', $slug], (array) ($entry['tags'] ?? [])),
                    'pinned' => !empty($entry['pinned']),
                ];
                if (count($out) >= $limit) {
                    return $out;
                }
            }
        }

        return $out;
    }

    private function pdo(): PDO
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

    private function hasColumn(PDO $pdo, string $table, string $column): bool
    {
        try {
            $stmt = $pdo->prepare('SHOW COLUMNS FROM `' . str_replace('`', '', $table) . '` LIKE ?');
            $stmt->execute([$column]);

            return (bool) $stmt->fetchColumn();
        } catch (Throwable) {
            return false;
        }
    }
}
