<?php

declare(strict_types=1);

namespace Besoiu\Services\AiRag;

use Besoiu\Services\AdminDatabaseResolver;
use Besoiu\Services\SectionAssistantSupplierQueries;
use PDO;
use Throwable;

/** Indexează furnizorii activi din MySQL în corpus RAG + biblioteca agent-produse. */
final class AiSupplierCatalogRagIndexerService
{
    private string $root;

    public function __construct(?string $projectRoot = null)
    {
        $this->root = $projectRoot
            ?? (defined('BESOIU_ROOT') ? (string) BESOIU_ROOT : dirname(__DIR__, 4));
    }

    public static function create(?string $projectRoot = null): self
    {
        return new self($projectRoot);
    }

    /** @return array<string, mixed> */
    public function indexAllFromDatabase(?PDO $pdo = null): array
    {
        if (!$this->isEnabled()) {
            return ['ok' => true, 'indexed' => 0, 'reason' => 'disabled'];
        }

        try {
            $pdo = $pdo ?? AdminDatabaseResolver::pdo();
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage(), 'indexed' => 0];
        }

        if (!AdminDatabaseResolver::hasTable($pdo, 'furnizori')) {
            return ['ok' => true, 'indexed' => 0, 'reason' => 'no_table'];
        }

        $rows = $this->loadSuppliers($pdo);
        if ($rows === []) {
            return ['ok' => true, 'indexed' => 0];
        }

        $entries = [];
        foreach ($rows as $row) {
            $entry = $this->buildCorpusEntry($row);
            if ($entry !== null) {
                $entries[] = $entry;
            }
        }

        if ($entries === []) {
            return ['ok' => true, 'indexed' => 0];
        }

        $corpus = new AiRagCorpusService($this->root);
        $corpusResult = $corpus->replaceManyBySource($entries);

        return [
            'ok' => true,
            'indexed' => count($entries),
            'total_active' => SectionAssistantSupplierQueries::countSuppliers(),
            'corpus_replaced' => (int) ($corpusResult['replaced'] ?? 0),
            'corpus_added' => (int) ($corpusResult['added'] ?? 0),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function loadSuppliers(PDO $pdo): array
    {
        try {
            $cols = 'id, name, code, status';
            if (AdminDatabaseResolver::hasColumn($pdo, 'furnizori', 'randomn_id')) {
                $cols .= ', randomn_id';
            }
            if (AdminDatabaseResolver::hasColumn($pdo, 'furnizori', 'type')) {
                $cols .= ', type';
            }
            if (AdminDatabaseResolver::hasColumn($pdo, 'furnizori', 'notes')) {
                $cols .= ', notes';
            }

            $stmt = $pdo->query(
                "SELECT {$cols} FROM furnizori
                 WHERE LOWER(TRIM(COALESCE(status, 'active'))) NOT IN ('deleted', 'sters', '0')
                 ORDER BY name ASC
                 LIMIT 500"
            );
            $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

            return is_array($rows) ? $rows : [];
        } catch (Throwable) {
            return [];
        }
    }

    /** @param array<string, mixed> $row @return array<string, mixed>|null */
    private function buildCorpusEntry(array $row): ?array
    {
        $name = trim((string) ($row['name'] ?? ''));
        if ($name === '') {
            return null;
        }

        $code = trim((string) ($row['code'] ?? ''));
        $status = trim((string) ($row['status'] ?? 'active'));
        $type = trim((string) ($row['type'] ?? ''));
        $notes = trim(strip_tags((string) ($row['notes'] ?? '')));
        $id = (string) ($row['id'] ?? '');

        $parts = array_filter([
            'Furnizor Besoiu (intern): ' . $name,
            $code !== '' ? 'Cod furnizor: ' . $code : '',
            $type !== '' ? 'Tip: ' . $type : '',
            'Status: ' . ($status !== '' ? $status : 'active'),
            $notes !== '' ? 'Notă: ' . mb_substr($notes, 0, 200) : '',
        ]);

        return [
            'source_type' => 'supplier',
            'source_id' => $id,
            'title' => $name,
            'text' => implode(' | ', $parts),
            'tags' => ['furnizor', 'supplier', 'mysql', 'intern'],
            'keywords' => array_values(array_filter([$name, $code, $type, 'furnizor intern'])),
        ];
    }

    private function isEnabled(): bool
    {
        $raw = strtolower(trim((string) (getenv('AI_RAG_AUTO_INDEX_SUPPLIERS') ?: ($_ENV['AI_RAG_AUTO_INDEX_SUPPLIERS'] ?? '1'))));

        return !in_array($raw, ['0', 'false', 'no', 'off'], true);
    }
}
