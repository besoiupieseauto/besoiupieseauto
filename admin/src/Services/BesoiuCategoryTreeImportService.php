<?php

declare(strict_types=1);

namespace Besoiu\Services;

use Besoiu\Core\Categorii\CategoriiModel;
use Config\Database;
use PDO;
use RuntimeException;

/**
 * Import arbore Besoiu (629 noduri) în tabela categorii.
 * Regulă: frunză.Nume == produs.ART_NAME (match exact, fără normalizare).
 */
final class BesoiuCategoryTreeImportService
{
    public const SOURCE = 'besoiu_tree';

    private CategoriiModel $model;
    private BesoiuCategoryTreeParser $parser;
    private TaxonomySyncService $taxonomySync;

    public function __construct(
        ?CategoriiModel $model = null,
        ?BesoiuCategoryTreeParser $parser = null,
        ?TaxonomySyncService $taxonomySync = null
    ) {
        $this->model = $model ?? new CategoriiModel();
        $this->parser = $parser ?? new BesoiuCategoryTreeParser();
        $this->taxonomySync = $taxonomySync ?? new TaxonomySyncService($this->model);
    }

    public static function parsedJsonPath(): string
    {
        return BesoiuCategoryTreeParser::projectRoot() . '/categorie/categorii_tree_parsed.json';
    }

    public static function secondaryJsonPath(): string
    {
        return BesoiuCategoryTreeParser::projectRoot() . '/categorie/categorii_secundare.json';
    }

    public static function synonymsJsonPath(): string
    {
        return BesoiuCategoryTreeParser::projectRoot() . '/categorie/sinonime_cautare.json';
    }

    public static function excelPath(): string
    {
        return BesoiuCategoryTreeExcelReader::defaultExcelPath();
    }

    /**
     * @return array<string, mixed>
     */
    public function importFromBestSource(bool $replaceExisting = true): array
    {
        if (BesoiuCategoryTreeExcelReader::isAvailable()) {
            try {
                return $this->importFromExcel($replaceExisting);
            } catch (\Throwable $e) {
                // Excel corupt / invalid → fallback pe arborele vizual
            }
        }

        return $this->importFromVisualTree($replaceExisting);
    }

    /**
     * Șterge tot catalogul piese (categorie/subcategorie/familie) și reimportă din Excel
     * (sau fallback vizual.txt dacă Excel lipsește).
     *
     * @return array<string, mixed>
     */
    public function renewCatalogFromExcel(?string $path = null): array
    {
        $this->ensureSchema();
        $pdo = Database::getDB();

        $pdo->beginTransaction();
        try {
            $this->purgeAllCatalogParts($pdo);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        CategorySearchSynonymService::clearCache();

        if (BesoiuCategoryTreeExcelReader::isAvailable($path)) {
            return $this->importFromExcel(true, $path);
        }

        $result = $this->importFromVisualTree(true);
        $result['fallback'] = 'visual_tree';
        $result['message_hint'] = 'Excel lipsă — import din categorii_tree_vizual.txt';

        return $result;
    }

    /**
     * Șterge intrările de catalog care NU provin din arborele Besoiu (PieseAuto, manual, vechi).
     *
     * @return array{deleted:int,remaining_besoiu:int}
     */
    public function purgeAlternateCatalog(): array
    {
        $this->ensureSchema();
        $pdo = Database::getDB();

        $pdo->beginTransaction();
        try {
            $deleted = $this->purgeAlternateCatalogParts($pdo);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        CategorySearchSynonymService::clearCache();
        $this->taxonomySync->syncAll(null);

        $remaining = $this->countBesoiuTreeRows($pdo);

        return [
            'deleted' => $deleted,
            'remaining_besoiu' => $remaining,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function importFromExcel(bool $replaceExisting = true, ?string $path = null): array
    {
        $reader = new BesoiuCategoryTreeExcelReader();
        $payload = $reader->load($path);
        $nodes = $payload['nodes'];
        $validation = $payload['stats'];

        if (($validation['duplicate_tree_ids'] ?? []) !== []) {
            throw new RuntimeException(
                'ID-uri duplicate în Excel: ' . implode(', ', $validation['duplicate_tree_ids'])
            );
        }

        $exports = $reader->exportJsonSidecars($payload['secondary'], $payload['synonyms']);
        $this->writeParsedJson($nodes, $validation, 'categorii_tree_final.xlsx');

        $result = $this->importNodes($nodes, $replaceExisting, $validation);
        $result['source'] = 'excel';
        $result['excel_path'] = (string) ($payload['path'] ?? '');
        $result['sheets'] = $payload['sheets'] ?? [];
        $result['json_exports'] = $exports;

        return $result;
    }

    /**
     * @return array{
     *   ok:bool,
     *   imported:int,
     *   updated:int,
     *   deleted:int,
     *   leaves:int,
     *   validation:array<string,mixed>,
     *   secondary:array<string,int>,
     *   synonyms:array<string,int>,
     *   parsed_path:string
     * }
     */
    public function importFromVisualTree(bool $replaceExisting = true, ?string $path = null): array
    {
        $this->ensureSchema();

        $nodes = $this->parser->parseFile($path);
        $validation = $this->parser->validate($nodes);

        if ($validation['duplicate_tree_ids'] !== []) {
            throw new RuntimeException(
                'ID-uri duplicate în arbore: ' . implode(', ', $validation['duplicate_tree_ids'])
            );
        }

        $this->writeParsedJson($nodes, $validation, 'categorii_tree_vizual.txt');
        $result = $this->importNodes($nodes, $replaceExisting, $validation);
        $result['source'] = 'visual_tree';

        return $result;
    }

    /**
     * @param list<array<string, mixed>> $nodes
     * @param array<string, mixed> $validation
     * @return array<string, mixed>
     */
    private function importNodes(array $nodes, bool $replaceExisting, array $validation): array
    {
        $this->ensureSchema();

        $pdo = Database::getDB();
        $pdo->beginTransaction();

        try {
            $deleted = 0;
            if ($replaceExisting) {
                $deleted = $this->purgeAllCatalogParts($pdo);
            }

            $treeIdToDbId = [];
            $imported = 0;
            $updated = 0;
            $nameByTreeId = [];
            $parentByTreeId = [];
            foreach ($nodes as $node) {
                $treeId = (int) $node['tree_id'];
                $nameByTreeId[$treeId] = (string) $node['name'];
                $parentByTreeId[$treeId] = (int) $node['parent_tree_id'];
            }

            foreach ($nodes as $node) {
                $treeId = (int) $node['tree_id'];
                $parentTreeId = (int) $node['parent_tree_id'];
                $parentDbId = $parentTreeId > 0 ? ($treeIdToDbId[$parentTreeId] ?? null) : null;
                $pathLabels = trim((string) ($node['path'] ?? '')) !== ''
                    ? array_values(array_filter(array_map('trim', preg_split('/\s*>\s*/u', (string) $node['path']) ?: [])))
                    : $this->buildPathLabels($treeId, $nameByTreeId, $parentByTreeId);

                $payload = $this->buildCategoryPayload($node, $parentDbId, $pathLabels);
                $existingId = $this->findExistingBesoiuRowId($pdo, $treeId);

                if ($existingId > 0) {
                    $this->model->update($existingId, $payload);
                    $treeIdToDbId[$treeId] = $existingId;
                    $updated++;
                    continue;
                }

                if (!$this->model->insert($payload)) {
                    throw new RuntimeException('Insert eșuat pentru tree_id ' . $treeId);
                }

                $newId = (int) $pdo->lastInsertId();
                $treeIdToDbId[$treeId] = $newId;
                $imported++;
            }

            $secondary = $this->importSecondaryCategories($pdo, $treeIdToDbId);
            $synonyms = $this->importSearchSynonyms($pdo);

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        CategorySearchSynonymService::clearCache();
        $this->taxonomySync->syncAll(null);

        return [
            'ok' => true,
            'imported' => $imported,
            'updated' => $updated,
            'deleted' => $deleted,
            'leaves' => (int) ($validation['leaves'] ?? 0),
            'validation' => $validation,
            'secondary' => $secondary,
            'synonyms' => $synonyms,
            'parsed_path' => self::parsedJsonPath(),
        ];
    }

    /**
     * @return array{total:int,roots:int,leaves:int,max_depth:int,duplicate_tree_ids:list<int>,source:string,excel_available:bool}
     */
    public function preview(?string $path = null): array
    {
        if ($path === null && BesoiuCategoryTreeExcelReader::isAvailable()) {
            $payload = (new BesoiuCategoryTreeExcelReader())->load();
            $stats = $payload['stats'];
            $stats['source'] = 'excel';
            $stats['excel_available'] = true;
            $stats['secondary_count'] = count($payload['secondary']);
            $stats['synonyms_count'] = count($payload['synonyms']);

            return $stats;
        }

        $nodes = $this->parser->parseFile($path);
        $stats = $this->parser->validate($nodes);
        $stats['source'] = 'visual_tree';
        $stats['excel_available'] = BesoiuCategoryTreeExcelReader::isAvailable();

        return $stats;
    }

    /**
     * @return array{path:string,stats:array<string,mixed>}
     */
    public function exportParsedJson(?string $path = null): array
    {
        $nodes = $this->parser->parseFile($path);
        $validation = $this->parser->validate($nodes);
        $this->writeParsedJson($nodes, $validation);

        return [
            'path' => self::parsedJsonPath(),
            'stats' => $validation,
        ];
    }

    /**
     * @param list<array<string, mixed>> $nodes
     * @param array<string, mixed> $validation
     */
    private function writeParsedJson(array $nodes, array $validation, string $sourceFile = 'categorii_tree_vizual.txt'): void
    {
        $payload = [
            'title' => 'Arbore categorii Besoiu — parsat din ' . $sourceFile,
            'source' => self::SOURCE,
            'source_file' => $sourceFile,
            'parsed_at' => date('c'),
            'stats' => $validation,
            'nodes' => array_map(static function (array $node): array {
                return [
                    'tree_id' => (int) $node['tree_id'],
                    'name' => (string) $node['name'],
                    'display_label' => (string) ($node['display_label'] ?? $node['name']),
                    'parent_tree_id' => (int) $node['parent_tree_id'],
                    'depth' => (int) ($node['depth'] ?? 0),
                    'level' => (int) ($node['level'] ?? 0),
                    'is_leaf' => !empty($node['is_leaf']),
                    'sort_order' => (int) ($node['sort_order'] ?? 0),
                    'slug' => (string) ($node['slug'] ?? ''),
                    'path' => (string) ($node['path'] ?? ''),
                ];
            }, $nodes),
        ];

        $path = self::parsedJsonPath();
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        file_put_contents(
            $path,
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );
    }

    /**
     * @param array<string, mixed> $node
     * @param list<string> $pathLabels
     * @return array<string, mixed>
     */
    private function buildCategoryPayload(array $node, ?int $parentDbId, array $pathLabels): array
    {
        $name = (string) $node['name'];
        $displayLabel = trim((string) ($node['display_label'] ?? $name));
        if ($displayLabel === '') {
            $displayLabel = $name;
        }
        $isLeaf = !empty($node['is_leaf']);
        $depth = (int) ($node['depth'] ?? 0);
        $treeId = (int) ($node['tree_id'] ?? 0);
        $parentTreeId = (int) ($node['parent_tree_id'] ?? 0);
        $path = trim((string) ($node['path'] ?? ''));
        if ($path === '') {
            $path = implode(' > ', $pathLabels);
        }

        $type = 'categorie';
        if ($isLeaf) {
            $type = 'subcategorie';
        }

        $meta = [
            'source' => self::SOURCE,
            'tree_id' => $treeId,
            'parent_tree_id' => $parentTreeId,
            'level' => (int) ($node['level'] ?? ($depth + 1)),
            'depth' => $depth,
            'path' => $path,
            'is_leaf' => $isLeaf,
            'display_label' => $displayLabel,
            'updated_at' => date('c'),
        ];

        if ($isLeaf) {
            $meta['art_name'] = $name;
        }

        $slug = trim((string) ($node['slug'] ?? ''));
        if ($slug === '') {
            $slug = $this->uniqueSlug($name, $treeId);
        }

        return [
            'label' => $displayLabel,
            'slug' => $slug,
            'type' => $type,
            'parent_id' => $parentDbId,
            'sort_order' => (int) ($node['sort_order'] ?? 0) * 10,
            'is_active' => 1,
            'meta' => json_encode($meta, JSON_UNESCAPED_UNICODE),
        ];
    }

    /**
     * @param array<int, string> $nameByTreeId
     * @param array<int, int> $parentByTreeId
     * @return list<string>
     */
    private function buildPathLabels(int $treeId, array $nameByTreeId, array $parentByTreeId): array
    {
        $labels = [];
        $currentId = $treeId;
        $guard = 0;

        while ($currentId > 0 && $guard < 24) {
            $label = trim((string) ($nameByTreeId[$currentId] ?? ''));
            if ($label !== '') {
                array_unshift($labels, $label);
            }
            $currentId = (int) ($parentByTreeId[$currentId] ?? 0);
            $guard++;
        }

        return $labels;
    }

    private function uniqueSlug(string $name, int $treeId): string
    {
        $slug = $this->slugify($name);
        if ($slug === '') {
            $slug = 'categorie';
        }

        return $slug . '-' . $treeId;
    }

    private function slugify(string $label): string
    {
        $slug = mb_strtolower($label, 'UTF-8');
        $slug = str_replace(
            ['ă', 'â', 'î', 'ș', 'ț', 'ş', 'ţ'],
            ['a', 'a', 'i', 's', 't', 's', 't'],
            $slug
        );
        $slug = preg_replace('/[^a-z0-9\-]+/', '-', $slug) ?? $slug;

        return trim($slug, '-');
    }

    private function purgeAllCatalogParts(PDO $pdo): int
    {
        $pdo->exec('DELETE FROM categorii_secundare');
        $pdo->exec('DELETE FROM categorii_sinonime');

        $ids = $pdo->query(
            "SELECT id FROM categorii WHERE type IN ('categorie', 'subcategorie', 'familie')"
        )->fetchAll(PDO::FETCH_COLUMN);

        $count = 0;
        foreach ($ids as $id) {
            if ($this->model->delete((int) $id)) {
                $count++;
            }
        }

        return $count;
    }

    private function purgeAlternateCatalogParts(PDO $pdo): int
    {
        $stmt = $pdo->query(
            "SELECT id FROM categorii
             WHERE type IN ('categorie', 'subcategorie', 'familie')
               AND COALESCE(JSON_UNQUOTE(JSON_EXTRACT(meta, '$.source')), '') <> 'besoiu_tree'"
        );
        $ids = $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
        $count = 0;

        foreach ($ids as $id) {
            if ($this->model->delete((int) $id)) {
                $count++;
            }
        }

        return $count;
    }

    private function countBesoiuTreeRows(PDO $pdo): int
    {
        $stmt = $pdo->query(
            "SELECT COUNT(*) FROM categorii
             WHERE type IN ('categorie', 'subcategorie', 'familie')
               AND JSON_UNQUOTE(JSON_EXTRACT(meta, '$.source')) = 'besoiu_tree'"
        );

        return (int) ($stmt ? $stmt->fetchColumn() : 0);
    }

    private function findExistingBesoiuRowId(PDO $pdo, int $treeId): int
    {
        if ($treeId <= 0) {
            return 0;
        }

        $stmt = $pdo->prepare(
            "SELECT id FROM categorii
             WHERE type IN ('categorie', 'subcategorie', 'familie')
               AND JSON_UNQUOTE(JSON_EXTRACT(meta, '$.tree_id')) = :tree_id
             ORDER BY id ASC
             LIMIT 1"
        );
        $stmt->execute([':tree_id' => (string) $treeId]);

        return (int) ($stmt->fetchColumn() ?: 0);
    }

    /**
     * @param array<int, int> $treeIdToDbId
     * @return array{imported:int,skipped:int}
     */
    private function importSecondaryCategories(PDO $pdo, array $treeIdToDbId): array
    {
        $path = self::secondaryJsonPath();
        if (!is_file($path)) {
            return ['imported' => 0, 'skipped' => 0];
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        $rows = is_array($decoded['items'] ?? null) ? $decoded['items'] : (is_array($decoded) ? $decoded : []);
        $imported = 0;
        $skipped = 0;

        $stmt = $pdo->prepare(
            'INSERT IGNORE INTO categorii_secundare (art_name, category_id, tree_id)
             VALUES (:art_name, :category_id, :tree_id)'
        );

        foreach ($rows as $row) {
            if (!is_array($row)) {
                $skipped++;
                continue;
            }

            $artName = trim((string) ($row['art_name'] ?? $row['ART_NAME'] ?? ''));
            $treeId = (int) ($row['category_tree_id'] ?? $row['tree_id'] ?? 0);
            $categoryId = (int) ($row['category_id'] ?? ($treeIdToDbId[$treeId] ?? 0));

            if ($categoryId <= 0 && !empty($row['category_name'])) {
                $categoryId = $this->resolveCategoryIdByArtNameOrLabel(
                    $pdo,
                    (string) $row['category_name'],
                    $treeIdToDbId
                );
            }

            if ($artName === '' || $categoryId <= 0) {
                $skipped++;
                continue;
            }

            $stmt->execute([
                ':art_name' => $artName,
                ':category_id' => $categoryId,
                ':tree_id' => $treeId > 0 ? $treeId : null,
            ]);

            if ($stmt->rowCount() > 0) {
                $imported++;
            } else {
                $skipped++;
            }
        }

        return ['imported' => $imported, 'skipped' => $skipped];
    }

    /** @return array{imported:int,skipped:int} */
    private function importSearchSynonyms(PDO $pdo): array
    {
        $path = self::synonymsJsonPath();
        if (!is_file($path)) {
            return ['imported' => 0, 'skipped' => 0];
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        $rows = is_array($decoded['items'] ?? null) ? $decoded['items'] : (is_array($decoded) ? $decoded : []);
        $imported = 0;
        $skipped = 0;

        $stmt = $pdo->prepare(
            'INSERT INTO categorii_sinonime (term, target_art_name, is_active)
             VALUES (:term, :target_art_name, 1)
             ON DUPLICATE KEY UPDATE target_art_name = VALUES(target_art_name), is_active = 1'
        );

        foreach ($rows as $row) {
            if (!is_array($row)) {
                $skipped++;
                continue;
            }

            $term = trim((string) ($row['term'] ?? $row['sinonim'] ?? ''));
            $target = trim((string) ($row['target_art_name'] ?? $row['target'] ?? $row['ART_NAME'] ?? ''));

            if ($term === '' || $target === '') {
                $skipped++;
                continue;
            }

            $stmt->execute([
                ':term' => mb_strtolower($term, 'UTF-8'),
                ':target_art_name' => $target,
            ]);
            $imported++;
        }

        return ['imported' => $imported, 'skipped' => $skipped];
    }

    /**
     * @param array<int, int> $treeIdToDbId
     */
    private function resolveCategoryIdByArtNameOrLabel(PDO $pdo, string $needle, array $treeIdToDbId): int
    {
        $needle = trim($needle);
        if ($needle === '') {
            return 0;
        }

        $stmt = $pdo->prepare(
            "SELECT id, meta FROM categorii
             WHERE meta LIKE '%\"source\":\"besoiu_tree\"%'
               AND (label = :label OR meta LIKE :art_pattern)
             LIMIT 20"
        );
        $stmt->execute([
            ':label' => $needle,
            ':art_pattern' => '%"art_name":"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $needle) . '"%',
        ]);

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            return (int) ($row['id'] ?? 0);
        }

        return 0;
    }

    public function ensureSchema(): void
    {
        $migration = BesoiuCategoryTreeParser::projectRoot() . '/modules/categorii/migrations/002_besoiu_category_tree.sql';
        if (!is_file($migration)) {
            return;
        }

        $pdo = Database::getDB();
        $sql = (string) file_get_contents($migration);
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
            if ($statement !== '') {
                $pdo->exec($statement);
            }
        }
    }
}
