<?php

declare(strict_types=1);

namespace Besoiu\Services;

use Besoiu\Core\Categorii\CategoriiModel;
use Config\Database;
use PDO;
use RuntimeException;

/**
 * Sincronizează modelele vehicul (type=model) din TecDoc în categorii, legate de marcă (parent_id).
 */
final class TecdocVehicleModelSyncService
{
    public const CACHE_JSON = 'tecdoc-vehicle-models.json';

    private CategoriiModel $model;
    private TaxonomySyncService $taxonomySync;

    public function __construct(?CategoriiModel $model = null, ?TaxonomySyncService $taxonomySync = null)
    {
        $this->model = $model ?? new CategoriiModel();
        $this->taxonomySync = $taxonomySync ?? new TaxonomySyncService($this->model);
    }

    public static function cachePath(): string
    {
        return TaxonomySyncService::storageDir() . '/' . self::CACHE_JSON;
    }

    /** @return array<string, mixed> */
    public function sync(bool $allowApi = true, bool $updateExisting = true): array
    {
        $marcaIndex = $this->indexMarci();
        if ($marcaIndex['by_label'] === [] && $marcaIndex['by_tecdoc'] === []) {
            throw new RuntimeException('Nu există mărci în DB. Rulează mai întâi sincronizarea mărcilor TecDoc.');
        }

        $models = $this->collectModels($allowApi, $marcaIndex);
        if ($models === []) {
            throw new RuntimeException(
                'Nu am găsit modele TecDoc. Verifică tecdoc_product_compatibilities sau API-ul TecDoc.'
            );
        }

        $existing = $this->indexExistingModels();
        $imported = 0;
        $updated = 0;
        $skipped = 0;
        $orphansLinked = 0;
        $sortByParent = $this->nextSortOrdersByParent();

        foreach ($models as $entry) {
            $label = trim((string) ($entry['label'] ?? ''));
            if ($label === '') {
                continue;
            }

            $marcaDbId = (int) ($entry['marca_db_id'] ?? 0);
            if ($marcaDbId <= 0) {
                continue;
            }

            $tecdocId = (int) ($entry['tecdoc_id'] ?? 0);
            $norm = $this->normalizeLabel($label);
            $key = $marcaDbId . '|' . $norm;
            $match = $existing['by_key'][$key] ?? null;

            if ($match === null && $tecdocId > 0 && isset($existing['by_tecdoc'][$tecdocId])) {
                $match = $existing['by_tecdoc'][$tecdocId];
            }

            if ($match === null && isset($existing['by_label_orphan'][$norm])) {
                $match = $existing['by_label_orphan'][$norm];
            }

            if ($match !== null) {
                $needsUpdate = false;
                $patch = [];
                $matchParent = (int) ($match['parent_id'] ?? 0);
                if ($updateExisting && $matchParent !== $marcaDbId) {
                    $patch['parent_id'] = $marcaDbId;
                    $needsUpdate = true;
                    if ($matchParent <= 0) {
                        ++$orphansLinked;
                    }
                }
                $matchTecdoc = (int) ($match['tecdoc_id'] ?? 0);
                if ($updateExisting && $tecdocId > 0 && $matchTecdoc !== $tecdocId) {
                    $patch['tecdoc_id'] = $tecdocId;
                    $needsUpdate = true;
                }
                if ($needsUpdate) {
                    $patch['meta'] = json_encode([
                        'source' => 'tecdoc',
                        'synced_at' => date('c'),
                        'sync_source' => (string) ($entry['source'] ?? 'tecdoc'),
                        'marca_label' => (string) ($entry['marca_label'] ?? ''),
                    ], JSON_UNESCAPED_UNICODE);
                    $this->model->update((int) $match['id'], $patch);
                    ++$updated;
                } else {
                    ++$skipped;
                }
                continue;
            }

            $marcaSlug = (string) ($entry['marca_slug'] ?? 'marca');
            $payload = [
                'label' => $label,
                'slug' => $this->slugify($marcaSlug . '-' . $label),
                'type' => 'model',
                'parent_id' => $marcaDbId,
                'is_active' => 1,
                'sort_order' => $sortByParent[$marcaDbId] ?? 100,
                'tecdoc_id' => $tecdocId > 0 ? $tecdocId : null,
                'meta' => json_encode([
                    'source' => 'tecdoc',
                    'synced_at' => date('c'),
                    'sync_source' => (string) ($entry['source'] ?? 'tecdoc'),
                    'marca_label' => (string) ($entry['marca_label'] ?? ''),
                ], JSON_UNESCAPED_UNICODE),
            ];

            $payload = $this->taxonomySync->enrichPayload($payload);
            if (!$this->model->insert($payload)) {
                continue;
            }

            $newId = (int) Database::getDB()->lastInsertId();
            $entryRow = [
                'id' => $newId,
                'parent_id' => $marcaDbId,
                'tecdoc_id' => $tecdocId,
                'label' => $label,
            ];
            $existing['by_key'][$key] = $entryRow;
            if ($tecdocId > 0) {
                $existing['by_tecdoc'][$tecdocId] = $entryRow;
            }
            $sortByParent[$marcaDbId] = ($sortByParent[$marcaDbId] ?? 100) + 10;
            ++$imported;
        }

        if ($imported > 0 || $updated > 0) {
            $this->taxonomySync->syncAll(null);
        }

        $totalDb = (int) Database::getDB()->query("SELECT COUNT(*) FROM categorii WHERE type = 'model'")->fetchColumn();
        $apiUsed = !empty($models[0]['api_batch']);

        return [
            'ok' => true,
            'source' => $apiUsed ? 'tecdoc_api+cache+db' : 'tecdoc_db+cache',
            'imported' => $imported,
            'updated' => $updated,
            'skipped' => $skipped,
            'orphans_linked' => $orphansLinked,
            'total_sources' => count($models),
            'total_db' => $totalDb,
            'api_used' => $apiUsed,
            'message' => sprintf(
                'Sincronizare modele TecDoc: %d noi, %d actualizate, %d existente, %d orfane legate (%d surse → %d în DB).',
                $imported,
                $updated,
                $skipped,
                $orphansLinked,
                count($models),
                $totalDb
            ),
        ];
    }

    /**
     * @param array{by_label:array<string,array<string,mixed>>,by_tecdoc:array<int,array<string,mixed>>}|null $marcaIndex
     * @return list<array<string,mixed>>
     */
    public function collectModels(bool $allowApi, ?array $marcaIndex = null): array
    {
        $marcaIndex ??= $this->indexMarci();
        $merged = [];
        $apiUsed = false;

        if ($allowApi) {
            $fromApi = $this->fetchFromApi($marcaIndex);
            if ($fromApi !== []) {
                $apiUsed = true;
                foreach ($fromApi as $row) {
                    $this->mergeModel($merged, $row, true);
                }
            }
        }

        foreach ($this->fetchFromApiCacheDb($marcaIndex) as $row) {
            $this->mergeModel($merged, $row, false);
        }

        if ($merged === []) {
            foreach ($this->readCache($marcaIndex) as $row) {
                $this->mergeModel($merged, $row, false);
            }
        }

        foreach ($this->fetchFromCompatTables($marcaIndex) as $row) {
            $this->mergeModel($merged, $row, false);
        }

        $result = array_values($merged);
        if ($apiUsed && $result !== []) {
            $result[0]['api_batch'] = true;
        }

        usort($result, static function (array $a, array $b): int {
            $brand = strcasecmp((string) ($a['marca_label'] ?? ''), (string) ($b['marca_label'] ?? ''));

            return $brand !== 0 ? $brand : strcasecmp((string) ($a['label'] ?? ''), (string) ($b['label'] ?? ''));
        });

        return $result;
    }

    /** @param array{by_label:array<string,array<string,mixed>>,by_tecdoc:array<int,array<string,mixed>>} $marcaIndex */
    private function fetchFromApi(array $marcaIndex): array
    {
        if (!defined('BESOIU_LEGACY')) {
            return [];
        }

        $legacyFile = BESOIU_LEGACY . '/tecdoc_stock.php';
        if (!is_file($legacyFile)) {
            return [];
        }

        require_once $legacyFile;

        if (function_exists('besoiu_api_automation_blocks_tecdoc_live') && besoiu_api_automation_blocks_tecdoc_live()) {
            return [];
        }

        if (!function_exists('tecdoc_catalog_lang_id') || !function_exists('tecdoc_cached_response')) {
            return [];
        }

        $lang = tecdoc_catalog_lang_id();
        $country = function_exists('tecdoc_catalog_country_id') ? tecdoc_catalog_country_id() : 63;
        $host = defined('BESOiu_TECDOC_HOST') ? BESOiu_TECDOC_HOST : 'auto-parts-catalog.p.rapidapi.com';
        $items = [];

        foreach ($marcaIndex['by_tecdoc'] as $manuId => $marcaRow) {
            $url = "https://{$host}/models/list/type-id/1/manufacturer-id/{$manuId}/lang-id/{$lang}/country-filter-id/{$country}";
            $body = tecdoc_cached_response($url, 86400 * 30);
            $decoded = json_decode($body, true);
            if (!is_array($decoded) || isset($decoded['error'])) {
                continue;
            }
            foreach ($this->parseApiModels($decoded, $marcaRow, (int) $manuId) as $row) {
                $items[] = $row;
            }
        }

        if ($items !== []) {
            $this->writeCache($items);
        }

        return $items;
    }

    /** @param array<string, mixed> $decoded @param array<string, mixed> $marcaRow @return list<array<string,mixed>> */
    private function parseApiModels(array $decoded, array $marcaRow, int $manuId): array
    {
        $rows = $decoded['models'] ?? $decoded['data'] ?? [];
        if (is_array($rows) && isset($rows['array']) && is_array($rows['array'])) {
            $rows = $rows['array'];
        }
        if (!is_array($rows)) {
            return [];
        }

        $items = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = (int) ($row['modelId'] ?? $row['model_id'] ?? 0);
            $name = trim((string) ($row['modelName'] ?? $row['model_name'] ?? $row['name'] ?? ''));
            if ($id <= 0 || $name === '') {
                continue;
            }
            $items[] = [
                'label' => $name,
                'tecdoc_id' => $id,
                'marca_label' => (string) ($marcaRow['label'] ?? ''),
                'marca_slug' => (string) ($marcaRow['slug'] ?? ''),
                'marca_db_id' => (int) ($marcaRow['id'] ?? 0),
                'marca_tecdoc_id' => $manuId,
                'source' => 'tecdoc_api',
            ];
        }

        return $items;
    }

    /** @param array{by_label:array<string,array<string,mixed>>,by_tecdoc:array<int,array<string,mixed>>} $marcaIndex @return list<array<string,mixed>> */
    private function fetchFromApiCacheDb(array $marcaIndex): array
    {
        try {
            $pdo = Database::getDB();
            if (!$this->tableExists($pdo, 'tecdoc_api_cache')) {
                return [];
            }
            $stmt = $pdo->query(
                "SELECT url, body FROM tecdoc_api_cache
                 WHERE url LIKE '%/models/list/%' AND expires_at > NOW()"
            );
            $items = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $url = (string) ($row['url'] ?? '');
                if (!preg_match('/manufacturer-id\/(\d+)/', $url, $match)) {
                    continue;
                }
                $manuId = (int) ($match[1] ?? 0);
                $marcaRow = $marcaIndex['by_tecdoc'][$manuId] ?? null;
                if (!is_array($marcaRow)) {
                    continue;
                }
                $decoded = json_decode((string) ($row['body'] ?? ''), true);
                if (!is_array($decoded)) {
                    continue;
                }
                foreach ($this->parseApiModels($decoded, $marcaRow, $manuId) as $item) {
                    $item['source'] = 'tecdoc_api_cache';
                    $items[] = $item;
                }
            }

            return $items;
        } catch (\Throwable) {
            return [];
        }
    }

    /** @param array{by_label:array<string,array<string,mixed>>,by_tecdoc:array<int,array<string,mixed>>} $marcaIndex @return list<array<string,mixed>> */
    private function fetchFromCompatTables(array $marcaIndex): array
    {
        $pairs = [];
        $this->collectCompatPairs(Database::getDB(), 'tecdoc_product_compatibilities', $pairs);

        try {
            if (function_exists('besoiupieseimport_tecdoc_legacy_pdo')) {
                $this->collectCompatPairs(besoiupieseimport_tecdoc_legacy_pdo(), 'product_compatibilities', $pairs);
            }
        } catch (\Throwable) {
        }

        $items = [];
        foreach ($pairs as $pair) {
            $brand = trim((string) ($pair['car_brand'] ?? ''));
            $model = trim((string) ($pair['car_model'] ?? ''));
            if ($brand === '' || $model === '') {
                continue;
            }
            $marcaRow = $this->resolveMarcaRow($brand, $marcaIndex);
            if ($marcaRow === null) {
                continue;
            }
            $items[] = [
                'label' => $model,
                'tecdoc_id' => null,
                'marca_label' => (string) ($marcaRow['label'] ?? $brand),
                'marca_slug' => (string) ($marcaRow['slug'] ?? ''),
                'marca_db_id' => (int) ($marcaRow['id'] ?? 0),
                'source' => 'tecdoc_mysql_compat',
            ];
        }

        return $items;
    }

    /** @param array<string, array{car_brand:string,car_model:string}> $pairs */
    private function collectCompatPairs(PDO $pdo, string $table, array &$pairs): void
    {
        if (!$this->tableExists($pdo, $table)) {
            return;
        }

        $safe = preg_replace('/[^a-z0-9_]/i', '', $table) ?? '';
        if ($safe === '') {
            return;
        }

        $stmt = $pdo->query(
            "SELECT DISTINCT TRIM(car_brand) AS car_brand, TRIM(car_model) AS car_model
             FROM `{$safe}`
             WHERE car_brand IS NOT NULL AND TRIM(car_brand) <> ''
               AND car_model IS NOT NULL AND TRIM(car_model) <> ''"
        );
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $brand = trim((string) ($row['car_brand'] ?? ''));
            $model = trim((string) ($row['car_model'] ?? ''));
            if ($brand === '' || $model === '') {
                continue;
            }
            $key = $this->normalizeLabel($brand) . '|' . $this->normalizeLabel($model);
            $pairs[$key] = ['car_brand' => $brand, 'car_model' => $model];
        }
    }

    /** @param array{by_label:array<string,array<string,mixed>>,by_tecdoc:array<int,array<string,mixed>>} $marcaIndex @return list<array<string,mixed>> */
    private function readCache(array $marcaIndex): array
    {
        if (!is_file(self::cachePath())) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents(self::cachePath()), true);
        if (!is_array($decoded)) {
            return [];
        }

        $items = $decoded['items'] ?? [];
        if (!is_array($items)) {
            return [];
        }

        $result = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $label = trim((string) ($item['label'] ?? ''));
            $marcaLabel = trim((string) ($item['marca_label'] ?? ''));
            if ($label === '' || $marcaLabel === '') {
                continue;
            }
            $marcaRow = $this->resolveMarcaRow($marcaLabel, $marcaIndex);
            if ($marcaRow === null) {
                continue;
            }
            $result[] = [
                'label' => $label,
                'tecdoc_id' => (int) ($item['tecdoc_id'] ?? 0) ?: null,
                'marca_label' => (string) ($marcaRow['label'] ?? $marcaLabel),
                'marca_slug' => (string) ($marcaRow['slug'] ?? ''),
                'marca_db_id' => (int) ($marcaRow['id'] ?? 0),
                'source' => 'tecdoc_cache',
            ];
        }

        return $result;
    }

    /** @param list<array<string,mixed>> $items */
    private function writeCache(array $items): void
    {
        $dir = TaxonomySyncService::storageDir();
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        file_put_contents(
            self::cachePath(),
            json_encode([
                'title' => 'TecDoc vehicle models',
                'synced_at' => date('c'),
                'count' => count($items),
                'items' => $items,
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );
    }

    /** @param array<string, array<string,mixed>> $merged @param array<string,mixed> $row */
    private function mergeModel(array &$merged, array $row, bool $preferIncoming): void
    {
        $label = trim((string) ($row['label'] ?? ''));
        $marcaDbId = (int) ($row['marca_db_id'] ?? 0);
        if ($label === '' || $marcaDbId <= 0) {
            return;
        }

        $key = $marcaDbId . '|' . $this->normalizeLabel($label);
        $incomingTecdoc = (int) ($row['tecdoc_id'] ?? 0);
        $entry = [
            'label' => $label,
            'tecdoc_id' => $incomingTecdoc > 0 ? $incomingTecdoc : null,
            'marca_label' => (string) ($row['marca_label'] ?? ''),
            'marca_slug' => (string) ($row['marca_slug'] ?? ''),
            'marca_db_id' => $marcaDbId,
            'marca_tecdoc_id' => (int) ($row['marca_tecdoc_id'] ?? 0) ?: null,
            'source' => (string) ($row['source'] ?? 'tecdoc'),
        ];

        if (!isset($merged[$key])) {
            $merged[$key] = $entry;

            return;
        }

        $current = $merged[$key];
        $currentTecdoc = (int) ($current['tecdoc_id'] ?? 0);
        if ($preferIncoming || ($entry['tecdoc_id'] !== null && $currentTecdoc <= 0)) {
            $merged[$key] = $entry;
        }
    }

    /** @param array{by_label:array<string,array<string,mixed>>,by_tecdoc:array<int,array<string,mixed>>} $marcaIndex @return array<string,mixed>|null */
    private function resolveMarcaRow(string $brandLabel, array $marcaIndex): ?array
    {
        $norm = $this->normalizeLabel($brandLabel);
        if (isset($marcaIndex['by_label'][$norm])) {
            return $marcaIndex['by_label'][$norm];
        }

        foreach ($marcaIndex['by_label'] as $marcaNorm => $row) {
            if (str_contains($norm, $marcaNorm) || str_contains($marcaNorm, $norm)) {
                return $row;
            }
        }

        return null;
    }

    /** @return array{by_label:array<string,array<string,mixed>>,by_tecdoc:array<int,array<string,mixed>>} */
    private function indexMarci(): array
    {
        $byLabel = [];
        $byTecdoc = [];

        foreach ($this->model->findAll() as $row) {
            if ((string) ($row['type'] ?? '') !== 'marca') {
                continue;
            }
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $entry = [
                'id' => $id,
                'label' => (string) ($row['label'] ?? ''),
                'slug' => (string) ($row['slug'] ?? ''),
                'tecdoc_id' => (int) ($row['tecdoc_id'] ?? 0),
            ];
            $label = trim($entry['label']);
            if ($label !== '') {
                $byLabel[$this->normalizeLabel($label)] = $entry;
            }
            if ($entry['tecdoc_id'] > 0) {
                $byTecdoc[$entry['tecdoc_id']] = $entry;
            }
        }

        return ['by_label' => $byLabel, 'by_tecdoc' => $byTecdoc];
    }

    /** @return array{by_key:array<string,array<string,mixed>>,by_tecdoc:array<int,array<string,mixed>>,by_label_orphan:array<string,array<string,mixed>>} */
    private function indexExistingModels(): array
    {
        $byKey = [];
        $byTecdoc = [];
        $byLabelOrphan = [];

        foreach ($this->model->findAll() as $row) {
            if ((string) ($row['type'] ?? '') !== 'model') {
                continue;
            }
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $label = trim((string) ($row['label'] ?? ''));
            $parentId = (int) ($row['parent_id'] ?? 0);
            $tecdocId = (int) ($row['tecdoc_id'] ?? 0);
            $entry = [
                'id' => $id,
                'parent_id' => $parentId,
                'tecdoc_id' => $tecdocId,
                'label' => $label,
            ];
            if ($parentId > 0 && $label !== '') {
                $byKey[$parentId . '|' . $this->normalizeLabel($label)] = $entry;
            } elseif ($label !== '') {
                $byLabelOrphan[$this->normalizeLabel($label)] = $entry;
            }
            if ($tecdocId > 0) {
                $byTecdoc[$tecdocId] = $entry;
            }
        }

        return [
            'by_key' => $byKey,
            'by_tecdoc' => $byTecdoc,
            'by_label_orphan' => $byLabelOrphan,
        ];
    }

    /** @return array<int, int> */
    private function nextSortOrdersByParent(): array
    {
        $sortByParent = [];
        try {
            $stmt = Database::getDB()->query(
                "SELECT parent_id, COALESCE(MAX(sort_order), 0) AS max_sort
                 FROM categorii
                 WHERE type = 'model' AND parent_id IS NOT NULL
                 GROUP BY parent_id"
            );
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $parentId = (int) ($row['parent_id'] ?? 0);
                if ($parentId > 0) {
                    $sortByParent[$parentId] = (int) ($row['max_sort'] ?? 0) + 10;
                }
            }
        } catch (\Throwable) {
        }

        return $sortByParent;
    }

    private function tableExists(PDO $pdo, string $table): bool
    {
        $safe = preg_replace('/[^a-z0-9_]/i', '', $table) ?? '';
        if ($safe === '') {
            return false;
        }

        try {
            $stmt = $pdo->prepare(
                'SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1'
            );
            $stmt->execute([$safe]);

            return (bool) $stmt->fetchColumn();
        } catch (\Throwable) {
            return false;
        }
    }

    private function normalizeLabel(string $value): string
    {
        $value = mb_strtoupper(trim($value), 'UTF-8');
        $value = str_replace(
            ['Ă', 'Â', 'Î', 'Ș', 'Ş', 'Ț', 'Ţ'],
            ['A', 'A', 'I', 'S', 'S', 'T', 'T'],
            $value
        );

        return preg_replace('/[^A-Z0-9]+/', '', $value) ?? $value;
    }

    private function slugify(string $label): string
    {
        $slug = mb_strtolower($label, 'UTF-8');
        $slug = str_replace(
            ['ă', 'â', 'î', 'ș', 'ş', 'ț', 'ţ'],
            ['a', 'a', 'i', 's', 's', 't', 't'],
            $slug
        );
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? $slug;
        $slug = trim($slug, '-');
        if (strlen($slug) > 180) {
            $slug = rtrim(substr($slug, 0, 180), '-');
        }

        return $slug !== '' ? $slug : 'model';
    }
}
