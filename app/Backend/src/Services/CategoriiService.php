<?php

declare(strict_types=1);

namespace Besoiu\Services;

use Besoiu\Core\Categorii\CategoriiModel;
use Besoiu\Services\CategoryIconService;
use Config\Database;
use PDO;

final class CategoriiService
{
    /** tm_021: amânat până la popularea bazei de produse — TecDoc rămâne doar referință în admin. */
    public const TECDOC_STRUCTURE_IMPORT_DEFERRED = true;

    private CategoriiModel $model;
    private CategoryIconService $iconService;

    public function __construct(?CategoriiModel $model = null, ?CategoryIconService $iconService = null)
    {
        $this->model = $model ?? new CategoriiModel();
        $this->iconService = $iconService ?? new CategoryIconService();
    }

    public function isTecdocStructureImportEnabled(): bool
    {
        return !self::TECDOC_STRUCTURE_IMPORT_DEFERRED;
    }

    public function tecdocStructureImportBlockedMessage(): string
    {
        return 'Importul structurilor TecDoc (categorii, mărci, modele) este amânat până când baza principală de produse este populată. TecDoc rămâne disponibil în admin doar ca referință.';
    }

    public function getAll(): array
    {
        return $this->model->findAll();
    }

    /** @return array{items:array<int,array<string,mixed>>,total:int,page:int,per_page:int,total_pages:int} */
    public function getPaginated(int $page = 1, int $perPage = 10, array $filters = []): array
    {
        return $this->model->findPaginated($page, $perPage, $filters);
    }

    public function getActive(): array
    {
        return $this->model->findActive();
    }

    public function getByType(string $type): array
    {
        return $this->model->findByType($type);
    }

    public function getById(int $id): ?array
    {
        return $this->model->findById($id);
    }

    public function getChildren(int $parentId): array
    {
        return $this->model->findByParentId($parentId);
    }

    public function getRootCategories(): array
    {
        return $this->model->findByParentId(null);
    }

    /**
     * Returnează arborele complet de categorii (pentru frontend).
     */
    public function getTree(): array
    {
        $all = $this->model->findActive();
        $index = $this->iconService->buildIndex($all);

        return $this->buildTree($all, null, $index);
    }

    /**
     * Returnează categoriile formatate pentru popup-ul din index.php.
     */
    public function getForPopup(): array
    {
        $categories = $this->model->findByType('categorie');
        $index = $this->iconService->buildIndex($categories);
        $result = [];
        foreach ($categories as $cat) {
            if ((int)($cat['parent_id'] ?? 0) !== 0) continue;
            $result[] = [
                'id'    => (int)$cat['id'],
                'slug'  => $cat['slug'],
                'label' => $cat['label'],
                'icon'  => $this->iconService->resolve($cat, $index),
                'count' => $this->countChildren((int)$cat['id'], $categories),
            ];
        }
        return $result;
    }

    /**
     * Returnează mărcile active.
     */
    public function getMarci(): array
    {
        return $this->model->findByType('marca');
    }

    public function create(array $data): bool
    {
        $data = $this->iconService->applyToPayload($data, $this->model);
        $data = $this->taxonomySync()->enrichPayload($data);

        $ok = $this->model->insert($data);
        if ($ok) {
            $newId = (int) \Config\Database::getDB()->lastInsertId();
            $this->syncTaxonomy($newId > 0 ? $newId : null);
        }

        return $ok;
    }

    public function update(int $id, array $data): bool
    {
        $existing = $this->model->findById($id);

        if (!array_key_exists('icon', $data) || trim((string) ($data['icon'] ?? '')) === '') {
            if ($existing && trim((string) ($existing['icon'] ?? '')) === '') {
                $merged = array_merge($existing, $data);
                $data['icon'] = $this->iconService->resolve(
                    $merged,
                    $this->iconService->buildIndex($this->model->findAll())
                );
            }
        }

        $data = $this->taxonomySync()->enrichPayload($data, is_array($existing) ? $existing : null);

        $ok = $this->model->update($id, $data);
        if ($ok) {
            $this->syncTaxonomy($id);
        }

        return $ok;
    }

    public function delete(int $id): bool
    {
        $ok = $this->model->delete($id);
        if ($ok) {
            $this->syncTaxonomy(null);
        }

        return $ok;
    }

    public function toggleActive(int $id, bool $active): bool
    {
        $ok = $this->model->toggleActive($id, $active ? 1 : 0);
        if ($ok) {
            $this->syncTaxonomy($id);
        }

        return $ok;
    }

    /**
     * Import categorii inițiale (cele 8 din popup) + actualizează iconițe lipsă.
     */
    public function importDefaults(): int
    {
        $defaults = [
            ['slug' => 'frane',      'label' => 'Frâne',          'icon' => 'img/icons/01_frane.svg',          'sort_order' => 10],
            ['slug' => 'filtre',     'label' => 'Filtre',         'icon' => 'img/icons/02_filtre.svg',         'sort_order' => 20],
            ['slug' => 'ulei',       'label' => 'Ulei & Lichide', 'icon' => 'img/icons/03_ulei_lichide.svg',   'sort_order' => 30],
            ['slug' => 'suspensie',  'label' => 'Suspensie',      'icon' => 'img/icons/04_suspensie.svg',      'sort_order' => 40],
            ['slug' => 'motor',      'label' => 'Motor',          'icon' => 'img/icons/05_motor.svg',          'sort_order' => 50],
            ['slug' => 'electric',   'label' => 'Electric',       'icon' => 'img/icons/06_electric.svg',       'sort_order' => 60],
            ['slug' => 'caroserie',  'label' => 'Caroserie',      'icon' => 'img/icons/07_caroserie.svg',      'sort_order' => 70],
            ['slug' => 'transmisie', 'label' => 'Transmisie',     'icon' => 'img/icons/08_transmisie.svg',     'sort_order' => 80],
        ];

        $existingBySlug = [];
        foreach ($this->model->findAll() as $row) {
            $existingBySlug[(string) ($row['slug'] ?? '')] = $row;
        }

        $count = 0;
        foreach ($defaults as $cat) {
            $slug = (string) $cat['slug'];
            if (isset($existingBySlug[$slug])) {
                $existing = $existingBySlug[$slug];
                if (trim((string) ($existing['icon'] ?? '')) === '') {
                    $this->model->update((int) $existing['id'], ['icon' => $cat['icon']]);
                    $count++;
                }
                continue;
            }

            $cat['type'] = 'categorie';
            $cat['is_active'] = 1;
            $cat['parent_id'] = null;
            if ($this->model->insert($cat)) {
                $count++;
            }
        }

        if ($count > 0) {
            $this->syncTaxonomy(null);
        }

        return $count;
    }

    /** @return array{updated:int,skipped:int,details:array<int,array{id:int,label:string,icon:string}>} */
    public function backfillMissingIcons(): array
    {
        return $this->iconService->backfillMissingIcons($this->model);
    }

    /** @return array<string, int|string|bool> */
    public function getPieseAutoCatalogStats(): array
    {
        return $this->taxonomySync()->syncStats();
    }

    /** @return array<string, mixed> */
    public function syncTaxonomy(?int $triggerId = null): array
    {
        return $this->taxonomySync()->syncAll($triggerId);
    }

    /**
     * @return array{imported_categories:int,imported_subcategories:int,updated:int,skipped:int}
     */
    public function importPieseAutoCatalog(bool $onlyMissing = true): array
    {
        $result = (new PieseAutoCategoryCatalog($this->model))->importToDatabase($onlyMissing);
        $this->syncTaxonomy(null);

        return $result;
    }

    /**
     * @return array{path:string,categories:int,subcategories:int}
     */
    public function parsePieseAutoHtml(string $html, bool $keepSnapshot = true): array
    {
        return (new PieseAutoCategoryCatalog($this->model))->parseAndSave($html, $keepSnapshot);
    }

    /**
     * @param array<string, mixed> $product
     * @return array<string, mixed>
     */
    public function matchProductCategory(array $product): array
    {
        return (new CategoryMatchService($this->model))->match($product);
    }

    /** @return array<string, mixed> */
    public function categoryMatchOllamaStatus(): array
    {
        return (new CategoryMatchService($this->model))->ollamaStatus();
    }

    /** @return array<string, mixed> */
    public function previewBesoiuCategoryTree(?string $path = null): array
    {
        return (new BesoiuCategoryTreeImportService($this->model))->preview($path);
    }

    /**
     * Metrici reale din baza de date (produse, import, dimensiune DB) — nu doar arbore Excel.
     *
     * @return array<string, mixed>
     */
    public function getBesoiuDashboardMetrics(): array
    {
        try {
            $pdo = Database::getDB();
        } catch (\Throwable) {
            return $this->emptyBesoiuDashboardMetrics();
        }

        $activeWhere = "COALESCE(status, 1) <> 0 AND status <> '0'";
        $classifiedL1 = "pCategory IS NOT NULL AND TRIM(pCategory) <> '' AND pCategory <> '0'";
        $classifiedL2 = "pSubcategory IS NOT NULL AND TRIM(pSubcategory) <> '' AND pSubcategory <> '0'";

        return [
            'db_size_gb' => $this->dashboardScalar($pdo, 'SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024 / 1024, 2) FROM information_schema.tables WHERE table_schema = DATABASE()', true),
            'produse_active' => $this->dashboardScalar($pdo, "SELECT COUNT(*) FROM produse WHERE {$activeWhere}"),
            'produse_total' => $this->dashboardScalar($pdo, 'SELECT COUNT(*) FROM produse'),
            'import_total' => $this->dashboardScalar($pdo, 'SELECT COUNT(*) FROM import_produse'),
            'import_pending' => $this->dashboardScalar($pdo, "SELECT COUNT(*) FROM import_produse WHERE status = 'pending'"),
            'produse_with_l1' => $this->dashboardScalar($pdo, "SELECT COUNT(*) FROM produse WHERE {$activeWhere} AND {$classifiedL1}"),
            'produse_with_l2' => $this->dashboardScalar($pdo, "SELECT COUNT(*) FROM produse WHERE {$activeWhere} AND {$classifiedL2}"),
            'produse_classified_both' => $this->dashboardScalar($pdo, "SELECT COUNT(*) FROM produse WHERE {$activeWhere} AND {$classifiedL1} AND {$classifiedL2}"),
            'produse_vitrina' => $this->dashboardScalar($pdo, "SELECT COUNT(*) FROM produse WHERE {$activeWhere} AND pVitrina = 1"),
            'synonyms_db' => $this->dashboardTableCount($pdo, 'categorii_sinonime', 'is_active = 1'),
            'secondary_db' => $this->dashboardTableCount($pdo, 'categorii_secundare'),
        ];
    }

    /** @return array<string, mixed> */
    private function emptyBesoiuDashboardMetrics(): array
    {
        return [
            'db_size_gb' => 0.0,
            'produse_active' => 0,
            'produse_total' => 0,
            'import_total' => 0,
            'import_pending' => 0,
            'produse_with_l1' => 0,
            'produse_with_l2' => 0,
            'produse_classified_both' => 0,
            'produse_vitrina' => 0,
            'synonyms_db' => 0,
            'secondary_db' => 0,
        ];
    }

    private function dashboardScalar(PDO $pdo, string $sql, bool $float = false): int|float
    {
        try {
            $value = $pdo->query($sql)->fetchColumn();
            if (!is_numeric($value)) {
                return $float ? 0.0 : 0;
            }

            return $float ? (float) $value : (int) $value;
        } catch (\Throwable) {
            return $float ? 0.0 : 0;
        }
    }

    private function dashboardTableCount(PDO $pdo, string $table, string $where = '1=1'): int
    {
        try {
            return $this->dashboardScalar($pdo, "SELECT COUNT(*) FROM {$table} WHERE {$where}");
        } catch (\Throwable) {
            return 0;
        }
    }

    /** @return array<string, mixed> */
    public function renewBesoiuCategoryTree(?string $path = null): array
    {
        return (new BesoiuCategoryTreeImportService($this->model))->renewCatalogFromExcel($path);
    }

    /** @return array{deleted:int,remaining_besoiu:int} */
    public function purgeAlternateCatalog(): array
    {
        return (new BesoiuCategoryTreeImportService($this->model))->purgeAlternateCatalog();
    }

    /** @return array<string, mixed> */
    public function importBesoiuCategoryTree(bool $replaceExisting = true, ?string $path = null): array
    {
        CategorySearchSynonymService::clearCache();

        if ($path !== null && str_ends_with(strtolower($path), '.xlsx')) {
            return (new BesoiuCategoryTreeImportService($this->model))->importFromExcel($replaceExisting, $path);
        }

        return (new BesoiuCategoryTreeImportService($this->model))->importFromBestSource($replaceExisting);
    }

    /** @return array<string, mixed> */
    public function importBesoiuCategoryTreeFromExcel(bool $replaceExisting = true, ?string $path = null): array
    {
        CategorySearchSynonymService::clearCache();

        return (new BesoiuCategoryTreeImportService($this->model))->importFromExcel($replaceExisting, $path);
    }

    /** @return array<string, mixed> */
    public function exportBesoiuExcelSidecars(?string $path = null): array
    {
        $reader = new BesoiuCategoryTreeExcelReader();
        $payload = $reader->load($path);

        return $reader->exportJsonSidecars($payload['secondary'], $payload['synonyms']);
    }

    /** @return array<string, mixed> */
    public function syncTecdocMarci(bool $allowApi = true, bool $updateExisting = true): array
    {
        if (!defined('BESOIU_API_MANUAL_CALL')) {
            define('BESOIU_API_MANUAL_CALL', true);
        }

        require_once dirname(__DIR__, 2) . '/tools/besoiupieseimport_tecdoc_db.php';

        return (new TecdocVehicleMarcaSyncService($this->model))->sync($allowApi, $updateExisting);
    }

    /** @return array<string, mixed> */
    public function previewTecdocMarci(bool $allowApi = true): array
    {
        if (!defined('BESOIU_API_MANUAL_CALL')) {
            define('BESOIU_API_MANUAL_CALL', true);
        }

        require_once dirname(__DIR__, 2) . '/tools/besoiupieseimport_tecdoc_db.php';

        $items = (new TecdocVehicleMarcaSyncService($this->model))->collectManufacturers($allowApi);

        return [
            'total' => count($items),
            'with_tecdoc_id' => count(array_filter($items, static fn (array $r): bool => (int) ($r['tecdoc_id'] ?? 0) > 0)),
            'items' => array_slice($items, 0, 50),
        ];
    }

    /** @return array<string, mixed> */
    public function syncTecdocModele(bool $allowApi = true, bool $updateExisting = true): array
    {
        if (!defined('BESOIU_API_MANUAL_CALL')) {
            define('BESOIU_API_MANUAL_CALL', true);
        }

        require_once dirname(__DIR__, 2) . '/tools/besoiupieseimport_tecdoc_db.php';

        return (new TecdocVehicleModelSyncService($this->model))->sync($allowApi, $updateExisting);
    }

    /** @return array<string, mixed> */
    public function previewTecdocModele(bool $allowApi = true): array
    {
        if (!defined('BESOIU_API_MANUAL_CALL')) {
            define('BESOIU_API_MANUAL_CALL', true);
        }

        require_once dirname(__DIR__, 2) . '/tools/besoiupieseimport_tecdoc_db.php';

        $items = (new TecdocVehicleModelSyncService($this->model))->collectModels($allowApi);

        return [
            'total' => count($items),
            'with_tecdoc_id' => count(array_filter($items, static fn (array $r): bool => (int) ($r['tecdoc_id'] ?? 0) > 0)),
            'brands' => count(array_unique(array_map(static fn (array $r): int => (int) ($r['marca_db_id'] ?? 0), $items))),
            'items' => array_slice($items, 0, 50),
        ];
    }

    private function buildTree(array $items, ?int $parentId, array $indexById = []): array
    {
        $tree = [];
        foreach ($items as $item) {
            $itemParent = $item['parent_id'] === null ? null : (int)$item['parent_id'];
            if ($itemParent === $parentId) {
                $children = $this->buildTree($items, (int)$item['id'], $indexById);
                $node = [
                    'id'       => (int)$item['id'],
                    'slug'     => $item['slug'],
                    'label'    => $item['label'],
                    'icon'     => $this->iconService->resolve($item, $indexById),
                    'type'     => $item['type'] ?? 'categorie',
                    'tecdoc_id'=> $item['tecdoc_id'] ?? null,
                ];
                if (!empty($children)) {
                    $node['children'] = $children;
                }
                $tree[] = $node;
            }
        }
        return $tree;
    }

    private function countChildren(int $parentId, array $allCategories): int
    {
        $count = 0;
        foreach ($allCategories as $cat) {
            if ((int)($cat['parent_id'] ?? 0) === $parentId) {
                $count++;
            }
        }
        return $count;
    }

    private function taxonomySync(): TaxonomySyncService
    {
        return new TaxonomySyncService($this->model);
    }
}
