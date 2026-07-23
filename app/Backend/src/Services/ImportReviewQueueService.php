<?php

declare(strict_types=1);

namespace Besoiu\Services;

use Besoiu\Core\AppCache;
use Besoiu\Core\Categorii\CategoriiHooks;
use Besoiu\Services\CategoriiService;
use Config\Database;
use PDO;
use Throwable;

/**
 * Date pentru pagina /admin/importreview — fără SQL în Templates.
 */
final class ImportReviewQueueService
{
    public function __construct(
        private readonly ?\PDO $pdo = null,
        private readonly ?CategoriiService $categoriiService = null,
    ) {
    }

    private function db(): PDO
    {
        return $this->pdo ?? Database::getDB();
    }

    /**
     * @return array{
     *   supplier: string,
     *   status: string,
     *   page: int,
     *   per_page: int,
     *   total: int,
     *   total_pages: int,
     *   suppliers: list<array{supplier_code: string, supplier_label: string}>,
     *   rows: list<array<string, mixed>>,
     *   categories: list<array{id: int, label: string}>,
     *   subcategories_by_category: array<string, list<array{id: int, label: string, parent_id: int}>>
     * }
     */
    public function loadPage(array $query): array
    {
        $supplier = trim((string) ($query['supplier'] ?? ''));
        // status „all” = toate statusurile; default pending dacă lipsește din query
        if (array_key_exists('status', $query)) {
            $status = trim((string) $query['status']);
            if ($status === '') {
                $status = 'all';
            }
        } else {
            $status = 'pending';
        }
        $statusFilter = ($status === 'all') ? '' : $status;
        $lane = trim((string) ($query['lane'] ?? 'standard'));
        $marca = trim((string) ($query['marca'] ?? ''));
        $model = trim((string) ($query['model'] ?? ''));
        $motorizare = trim((string) ($query['motorizare'] ?? ''));
        $brand = trim((string) ($query['brand'] ?? ''));
        $an = trim((string) ($query['an'] ?? ''));
        $page = max(1, (int) ($query['page'] ?? 1));
        $perPage = 10;

        $extraFilters = [
            'marca' => $marca,
            'model' => $model,
            'motorizare' => $motorizare,
            'brand' => $brand,
            'an' => $an,
        ];

        // Facets (5× DISTINCT + scan ani) — NU pe GET: blochează pagina 5–15s pe tabele mari.
        // Se încarcă async după first paint (action=queue_filter_facets).
        $withFacets = !empty($query['with_facets']) || !empty($query['facets']);
        // COUNT(*) exact pe JSON lane = full scan — pe GET folosim LIMIT+1 (rapid), total exact async.
        $exactCount = !empty($query['exact_count']);
        $suppliers = $this->listSuppliers();
        $pageData = $this->listQueueRows(
            $statusFilter,
            $supplier,
            $page,
            $perPage,
            $lane,
            $extraFilters,
            !$exactCount
        );
        $filterFacets = $withFacets
            ? $this->listQueueFilterFacets($statusFilter, $lane, $supplier)
            : ['marci' => [], 'modele' => [], 'motorizari' => [], 'brands' => [], 'ani' => []];
        $taxonomy = $this->loadTaxonomyOptions();

        // Nu hidrata sincron la GET — hydratePendingQueueRows() încarcă monolitul import
        // + TecDoc/RapidAPI per rând și blochează sesiunea PHP minute întregi.
        $rows = $pageData['rows'];

        return [
            'supplier' => $supplier,
            'status' => $status,
            'lane' => $lane,
            'marca' => $marca,
            'model' => $model,
            'motorizare' => $motorizare,
            'brand' => $brand,
            'an' => $an,
            'page' => $page,
            'per_page' => $perPage,
            'total' => $pageData['total'],
            'total_pages' => $pageData['total_pages'],
            'total_approx' => !empty($pageData['total_approx']),
            'has_more' => !empty($pageData['has_more']),
            'lane_counts' => $this->countQueueRowsByLane($statusFilter, $supplier, $extraFilters),
            'suppliers' => $suppliers,
            'filter_facets' => $filterFacets,
            'facets_deferred' => !$withFacets,
            'rows' => $rows,
            'categories' => $taxonomy['categories'],
            'subcategories_by_category' => $taxonomy['subcategories_by_category'],
        ];
    }

    /**
     * @param array<string, string> $extraFilters
     * @return array{where: list<string>, params: list<mixed>}
     */
    public function buildQueueWhere(
        string $status,
        string $supplier,
        string $lane = 'standard',
        array $extraFilters = []
    ): array {
        $where = [];
        $params = [];

        if ($status !== '') {
            $where[] = 'status = ?';
            $params[] = $status;
        }
        if ($supplier !== '') {
            $where[] = 'pSupplier = ?';
            $params[] = $supplier;
        }
        // Preferă coloana fizică import_lane (dacă există) — evită JSON_EXTRACT full-scan.
        $laneCol = $this->hasImportLaneColumn();
        $emptyImagesSql = "(pImages IS NULL OR TRIM(pImages) = '' OR pImages IN ('[]', 'null', '\"[]\"'))";
        $hasImagesSql = "(pImages IS NOT NULL AND TRIM(pImages) <> '' AND pImages NOT IN ('[]', 'null', '\"[]\"'))";

        if ($lane === 'showcase') {
            $where[] = $laneCol
                ? "import_lane = 'showcase'"
                : "JSON_UNQUOTE(JSON_EXTRACT(raw_json, '$.import_lane')) = 'showcase'";
        } elseif ($lane === 'no_image') {
            // Include și rânduri vechi cu lane NULL/standard dar fără imagini (încă nerutate).
            if ($laneCol) {
                $where[] = "(import_lane = 'no_image' OR (("
                    . "import_lane IS NULL OR import_lane = '' OR import_lane = 'standard'"
                    . ") AND {$emptyImagesSql}))";
            } else {
                $where[] = "(JSON_UNQUOTE(JSON_EXTRACT(raw_json, '$.import_lane')) = 'no_image' OR (("
                    . "JSON_EXTRACT(raw_json, '$.import_lane') IS NULL"
                    . " OR JSON_UNQUOTE(JSON_EXTRACT(raw_json, '$.import_lane')) = 'standard'"
                    . ") AND {$emptyImagesSql}))";
            }
            $where[] = $emptyImagesSql;
        } elseif ($lane === 'standard') {
            $where[] = $laneCol
                ? "(import_lane IS NULL OR import_lane = '' OR import_lane = 'standard')"
                : "(JSON_EXTRACT(raw_json, '$.import_lane') IS NULL OR JSON_UNQUOTE(JSON_EXTRACT(raw_json, '$.import_lane')) = 'standard')";
            // Produsele fără imagine apar doar în tab-ul „Produse fără imagine”.
            $where[] = $hasImagesSql;
        }

        $marca = trim((string) ($extraFilters['marca'] ?? ''));
        if ($marca !== '') {
            $where[] = 'TRIM(pMarca) = ?';
            $params[] = $marca;
        }

        $model = trim((string) ($extraFilters['model'] ?? ''));
        if ($model !== '') {
            $where[] = 'TRIM(pModel) LIKE ?';
            $params[] = '%' . $model . '%';
        }

        $motorizare = trim((string) ($extraFilters['motorizare'] ?? ''));
        if ($motorizare !== '') {
            $where[] = 'TRIM(pMotorizare) LIKE ?';
            $params[] = '%' . $motorizare . '%';
        }

        $brand = trim((string) ($extraFilters['brand'] ?? ''));
        if ($brand !== '') {
            $where[] = 'TRIM(pBrand) = ?';
            $params[] = $brand;
        }

        $an = trim((string) ($extraFilters['an'] ?? ''));
        if ($an !== '') {
            $yearPattern = '%' . $an . '%';
            $where[] = '(pCompatibilitati LIKE ? OR pName LIKE ? OR pModel LIKE ? OR pMarca LIKE ? OR pMotorizare LIKE ?)';
            array_push($params, $yearPattern, $yearPattern, $yearPattern, $yearPattern, $yearPattern);
        }

        return ['where' => $where, 'params' => $params];
    }

    /**
     * @return array{
     *   marci: list<string>,
     *   modele: list<string>,
     *   motorizari: list<string>,
     *   brands: list<string>,
     *   ani: list<string>
     * }
     */
    public function listQueueFilterFacets(string $status, string $lane, string $supplier): array
    {
        $cacheKey = 'importreview_facets_v2|' . $status . '|' . $lane . '|' . $supplier;

        return AppCache::remember($cacheKey, 45, function () use ($status, $lane, $supplier): array {
            $pdo = $this->db();
            $whereData = $this->buildQueueWhere($status, $supplier, $lane, []);
            $whereSql = $whereData['where'] !== [] ? ' WHERE ' . implode(' AND ', $whereData['where']) : '';

            $fetchDistinct = static function (string $column) use ($pdo, $whereSql, $whereData): array {
                $extra = $column . " IS NOT NULL AND TRIM(" . $column . ") <> ''";
                $sql = 'SELECT DISTINCT TRIM(' . $column . ') AS value FROM import_produse'
                    . ($whereSql !== '' ? $whereSql . ' AND ' . $extra : ' WHERE ' . $extra)
                    . ' ORDER BY value ASC LIMIT 200';
                $stmt = $pdo->prepare($sql);
                $stmt->execute($whereData['params']);
                $values = [];
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                    $value = trim((string) ($row['value'] ?? ''));
                    if ($value !== '') {
                        $values[] = $value;
                    }
                }

                return $values;
            };

            // Ani: doar din pModel (evită scan pe pCompatibilitati — blob greu).
            $ani = [];
            $yearSql = 'SELECT DISTINCT TRIM(pModel) AS model FROM import_produse'
                . ($whereSql !== '' ? $whereSql . " AND TRIM(pModel) <> ''" : " WHERE TRIM(pModel) <> ''")
                . ' LIMIT 400';
            $yearStmt = $pdo->prepare($yearSql);
            $yearStmt->execute($whereData['params']);
            foreach ($yearStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $text = (string) ($row['model'] ?? '');
                if ($text !== '' && preg_match_all('/\b(19|20)\d{2}\b/', $text, $matches)) {
                    foreach ($matches[0] as $year) {
                        $ani[$year] = true;
                    }
                }
            }
            $aniList = array_keys($ani);
            rsort($aniList, SORT_NUMERIC);

            return [
                'marci' => $fetchDistinct('pMarca'),
                'modele' => $fetchDistinct('pModel'),
                'motorizari' => $fetchDistinct('pMotorizare'),
                'brands' => $fetchDistinct('pBrand'),
                'ani' => array_slice($aniList, 0, 40),
            ];
        });
    }

    /**
     * @return list<array{supplier_code: string, supplier_label: string}>
     */
    public function listSuppliers(): array
    {
        return AppCache::remember('importreview_suppliers_v2', 120, function (): array {
            $pdo = $this->db();
            $suppliersMap = [];

            foreach ($pdo->query(
                "SELECT UPPER(TRIM(code)) AS supplier_code, TRIM(name) AS supplier_label
                 FROM furnizori
                 WHERE code IS NOT NULL AND TRIM(code) <> ''"
            ) as $row) {
                $code = (string) ($row['supplier_code'] ?? '');
                if ($code === '') {
                    continue;
                }
                $suppliersMap[$code] = (string) ($row['supplier_label'] ?? $code);
            }

            // Doar furnizori din coadă (status pending) — DISTINCT pe toată tabela e lent.
            foreach ($pdo->query(
                "SELECT DISTINCT UPPER(TRIM(pSupplier)) AS supplier_code
                 FROM import_produse
                 WHERE status = 'pending' AND pSupplier IS NOT NULL AND TRIM(pSupplier) <> ''
                 LIMIT 200"
            ) as $row) {
                $code = (string) ($row['supplier_code'] ?? '');
                if ($code === '') {
                    continue;
                }
                $suppliersMap[$code] = $suppliersMap[$code] ?? $code;
            }

            $suppliers = [];
            foreach ($suppliersMap as $code => $label) {
                $suppliers[] = [
                    'supplier_code' => $code,
                    'supplier_label' => $label !== '' ? $label : $code,
                ];
            }
            usort(
                $suppliers,
                static fn ($a, $b) => strcasecmp((string) ($a['supplier_label'] ?? ''), (string) ($b['supplier_label'] ?? ''))
            );

            return $suppliers;
        });
    }

    /**
     * @return array{total: int, total_pages: int, total_approx?: bool, rows: list<array<string, mixed>>}
     */
    public function listQueueRows(
        string $status,
        string $supplier,
        int $page,
        int $perPage,
        string $lane = 'standard',
        array $extraFilters = [],
        bool $deferExactCount = false
    ): array {
        $pdo = $this->db();
        $whereData = $this->buildQueueWhere($status, $supplier, $lane, $extraFilters);
        $where = $whereData['where'];
        $params = $whereData['params'];

        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;
        $whereSql = $where !== [] ? ' WHERE ' . implode(' AND ', $where) : '';

        $limit = $deferExactCount ? ($perPage + 1) : $perPage;

        // Listă slim: fără tecdoc_api / rows[0] / audit (blob-uri grele) — edit le ia via queue_row_get.
        $sql = 'SELECT
            id, status, pCode, pName, pBrand, pMarca, pModel, pMotorizare,
            pPrice, pBasePrice, pStock, pCategory, pSubcategory, pNote, pOem,
            pCompatibilitati, pImages, pImageSource, pMarkupRuleName, pSupplier,
            JSON_EXTRACT(raw_json, \'$.product_summary\') AS _product_summary,
            JSON_UNQUOTE(JSON_EXTRACT(raw_json, \'$.__image_source\')) AS _image_source,
            JSON_UNQUOTE(JSON_EXTRACT(raw_json, \'$.__image_query\')) AS _image_query,
            JSON_UNQUOTE(JSON_EXTRACT(raw_json, \'$.__emag_search_url\')) AS _emag_search_url,
            JSON_UNQUOTE(JSON_EXTRACT(raw_json, \'$.__oem_matched_code\')) AS _oem_matched_code,
            JSON_UNQUOTE(JSON_EXTRACT(raw_json, \'$.__oem_matched_brand\')) AS _oem_matched_brand,
            JSON_EXTRACT(raw_json, \'$.supplier_price\') AS _supplier_price
            FROM import_produse' . $whereSql
            . ' ORDER BY id DESC LIMIT ' . $limit . ' OFFSET ' . $offset;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $fetched = array_map(
            fn(array $row): array => $this->hydrateQueueRowRawJson($row),
            $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []
        );

        $hasMore = false;
        if ($deferExactCount && count($fetched) > $perPage) {
            $hasMore = true;
            $fetched = array_slice($fetched, 0, $perPage);
        }
        $rows = $fetched;

        if ($lane === 'no_image') {
            if (!function_exists('besoiu_import_row_has_queue_image')) {
                $validatePath = dirname(__DIR__, 2) . '/Legacy/import-image-validate.php';
                if (is_file($validatePath)) {
                    require_once $validatePath;
                }
            }
            if (function_exists('besoiu_import_row_has_queue_image')) {
                $rows = array_values(array_filter(
                    $rows,
                    static fn(array $row): bool => !besoiu_import_row_has_queue_image($row)
                ));
            }
        }

        if ($deferExactCount) {
            $total = $hasMore ? ($offset + $perPage + 1) : ($offset + count($rows));
            $totalPages = $hasMore ? ($page + 1) : max(1, $page);

            return [
                'total' => $total,
                'total_pages' => $totalPages,
                'total_approx' => true,
                'has_more' => $hasMore,
                'rows' => $rows,
            ];
        }

        $countKey = 'importreview_count_v2|' . md5($whereSql . '|' . json_encode($params));
        $total = (int) AppCache::remember($countKey, 20, static function () use ($pdo, $whereSql, $params): int {
            $countStmt = $pdo->prepare('SELECT COUNT(*) FROM import_produse' . $whereSql);
            $countStmt->execute($params);

            return (int) $countStmt->fetchColumn();
        });
        $totalPages = max(1, (int) ceil($total / $perPage));

        return [
            'total' => $total,
            'total_pages' => $totalPages,
            'total_approx' => false,
            'has_more' => $page < $totalPages,
            'rows' => $rows,
        ];
    }

    /**
     * Invalidează count/facets/suppliers după TRUNCATE, delete, publish, exclude etc.
     * Fără asta UI-ul arată totaluri vechi (ex. „20”) pe coadă goală.
     */
    public function invalidateQueueCaches(): void
    {
        AppCache::flushPrefix('importreview_');
        // Intrări vechi (înainte ca AppCache să salveze „key”) — nu pot fi filtrate pe prefix.
        $dir = dirname(__DIR__, 2) . '/storage/cache';
        foreach (glob($dir . '/*.json') ?: [] as $file) {
            $base = basename($file);
            if (!preg_match('/^[a-f0-9]{64}\\.json$/', $base)) {
                continue;
            }
            $raw = json_decode((string) file_get_contents($file), true);
            if (!is_array($raw)) {
                @unlink($file);
                continue;
            }
            $storedKey = (string) ($raw['key'] ?? '');
            if ($storedKey === '' || str_starts_with($storedKey, 'importreview_')) {
                @unlink($file);
            }
        }
    }

    /**
     * Număr rânduri pe tip coadă (standard / vitrină / fără imagine) — pentru tab-uri UI.
     *
     * @param array<string, string> $extraFilters
     * @return array{standard: int, showcase: int, no_image: int}
     */
    public function countQueueRowsByLane(string $status, string $supplier, array $extraFilters = []): array
    {
        $cacheKey = 'importreview_lane_counts_v1|' . $status . '|' . $supplier . '|' . md5(json_encode($extraFilters));

        return AppCache::remember($cacheKey, 30, function () use ($status, $supplier, $extraFilters): array {
            $counts = ['standard' => 0, 'showcase' => 0, 'no_image' => 0];
            foreach (array_keys($counts) as $laneKey) {
                $counts[$laneKey] = $this->countQueueRows($status, $supplier, $laneKey, $extraFilters);
            }

            return $counts;
        });
    }

    /** COUNT exact (cu cache scurt) — pentru refresh async după first paint. */
    public function countQueueRows(
        string $status,
        string $supplier,
        string $lane = 'standard',
        array $extraFilters = []
    ): int {
        $whereData = $this->buildQueueWhere($status, $supplier, $lane, $extraFilters);
        $whereSql = $whereData['where'] !== [] ? ' WHERE ' . implode(' AND ', $whereData['where']) : '';
        $params = $whereData['params'];
        $countKey = 'importreview_count_v2|' . md5($whereSql . '|' . json_encode($params));

        return (int) AppCache::remember($countKey, 20, function () use ($whereSql, $params): int {
            $countStmt = $this->db()->prepare('SELECT COUNT(*) FROM import_produse' . $whereSql);
            $countStmt->execute($params);

            return (int) $countStmt->fetchColumn();
        });
    }

    /**
     * Reconstruiește un raw_json minimal pentru view — fără a transfera întregul blob din BD.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function hydrateQueueRowRawJson(array $row): array
    {
        $summary = null;
        $summaryRaw = $row['_product_summary'] ?? null;
        if (is_string($summaryRaw) && $summaryRaw !== '') {
            $decoded = json_decode($summaryRaw, true);
            if (is_array($decoded)) {
                $summary = $decoded;
            }
        }

        $supplierPrice = null;
        $supplierRaw = $row['_supplier_price'] ?? null;
        if (is_string($supplierRaw) && $supplierRaw !== '') {
            $decoded = json_decode($supplierRaw, true);
            if (is_array($decoded)) {
                $supplierPrice = $decoded;
            }
        }

        $rawJson = array_filter([
            'product_summary' => $summary,
            '__image_source' => $row['_image_source'] ?? null,
            '__image_query' => $row['_image_query'] ?? null,
            '__emag_search_url' => $row['_emag_search_url'] ?? null,
            '__oem_matched_code' => $row['_oem_matched_code'] ?? null,
            '__oem_matched_brand' => $row['_oem_matched_brand'] ?? null,
            'supplier_price' => $supplierPrice,
        ], static fn($value): bool => $value !== null && $value !== '' && $value !== []);

        $row['raw_json'] = json_encode($rawJson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        unset(
            $row['_product_summary'],
            $row['_image_source'],
            $row['_image_query'],
            $row['_emag_search_url'],
            $row['_oem_matched_code'],
            $row['_oem_matched_brand'],
            $row['_supplier_price']
        );

        return $row;
    }

    private function hasImportLaneColumn(): bool
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }

        $cached = (bool) AppCache::remember('import_produse_has_import_lane_col', 600, function (): bool {
            try {
                $stmt = $this->db()->query(
                    "SELECT 1 FROM information_schema.COLUMNS
                     WHERE TABLE_SCHEMA = DATABASE()
                       AND TABLE_NAME = 'import_produse'
                       AND COLUMN_NAME = 'import_lane'
                     LIMIT 1"
                );

                return (bool) ($stmt && $stmt->fetchColumn());
            } catch (Throwable) {
                return false;
            }
        });

        return $cached;
    }

    /**
     * Adaugă coloana indexată import_lane (o singură dată) — fără backfill greu.
     * NULL / '' = lane standard. Rândurile showcase/no_image se marchează la scriere.
     */
    public function ensureImportLaneColumn(): bool
    {
        if ($this->hasImportLaneColumn()) {
            return true;
        }

        try {
            $pdo = $this->db();
            $pdo->exec(
                "ALTER TABLE import_produse
                 ADD COLUMN import_lane VARCHAR(32) NULL DEFAULT NULL,
                 ADD INDEX idx_import_lane_status (status, import_lane)"
            );
            // Backfill ușor: doar lane-urile non-standard (de obicei puține).
            @$pdo->exec(
                "UPDATE import_produse
                 SET import_lane = 'showcase'
                 WHERE import_lane IS NULL
                   AND raw_json LIKE '%\"import_lane\":\"showcase\"%'"
            );
            @$pdo->exec(
                "UPDATE import_produse
                 SET import_lane = 'no_image'
                 WHERE import_lane IS NULL
                   AND raw_json LIKE '%\"import_lane\":\"no_image\"%'"
            );
            AppCache::forget('import_produse_has_import_lane_col');

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /** @return array<string, mixed>|null */
    private function decodeJsonColumn(mixed $value): ?array
    {
        if (!is_string($value) || $value === '') {
            return null;
        }
        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @return array{
     *   categories: list<array{id: int, label: string}>,
     *   subcategories_by_category: array<string, list<array{id: int, label: string, parent_id: int}>>
     * }
     */
    public function loadTaxonomyOptions(): array
    {
        return AppCache::remember('importreview_taxonomy_v1', 180, function (): array {
            $categories = [];
            $subcategoriesByCategory = [];

            try {
                $service = $this->categoriiService ?? CategoriiHooks::service();
                $taxonomyRows = $service->getActive();
                $catById = [];
                foreach ($taxonomyRows as $taxonomyRow) {
                    $catById[(int) ($taxonomyRow['id'] ?? 0)] = $taxonomyRow;
                }
                foreach ($taxonomyRows as $taxonomyRow) {
                    $taxonomyType = (string) ($taxonomyRow['type'] ?? '');
                    $taxonomyLabel = trim((string) ($taxonomyRow['label'] ?? ''));
                    if ($taxonomyLabel === '') {
                        continue;
                    }
                    if ($taxonomyType === 'categorie' && (int) ($taxonomyRow['parent_id'] ?? 0) === 0) {
                        $categories[] = [
                            'id' => (int) ($taxonomyRow['id'] ?? 0),
                            'label' => $taxonomyLabel,
                        ];
                        continue;
                    }
                    if ($taxonomyType !== 'subcategorie') {
                        continue;
                    }
                    $parentId = (int) ($taxonomyRow['parent_id'] ?? 0);
                    $parentLabel = trim((string) ($catById[$parentId]['label'] ?? ''));
                    if ($parentLabel === '') {
                        continue;
                    }
                    $subcategoriesByCategory[$parentLabel][] = [
                        'id' => (int) ($taxonomyRow['id'] ?? 0),
                        'label' => $taxonomyLabel,
                        'parent_id' => $parentId,
                    ];
                }
                usort(
                    $categories,
                    static fn ($a, $b) => strcasecmp((string) ($a['label'] ?? ''), (string) ($b['label'] ?? ''))
                );
                foreach ($subcategoriesByCategory as $parentLabel => $subRows) {
                    usort(
                        $subRows,
                        static fn ($a, $b) => strcasecmp((string) ($a['label'] ?? ''), (string) ($b['label'] ?? ''))
                    );
                    $subcategoriesByCategory[$parentLabel] = $subRows;
                }
            } catch (Throwable) {
                return [
                    'categories' => [],
                    'subcategories_by_category' => [],
                ];
            }

            return [
                'categories' => $categories,
                'subcategories_by_category' => $subcategoriesByCategory,
            ];
        });
    }

    /**
     * Completează categorie/subcategorie din TecDoc/taxonomie — fără a șterge imaginile din BD.
     *
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    public function hydratePendingQueueRows(array $rows): array
    {
        if ($rows === []) {
            return $rows;
        }

        $appRoot = dirname(__DIR__, 3);
        $validatePath = $appRoot . '/Legacy/import-image-validate.php';
        if (is_file($validatePath)) {
            require_once $validatePath;
        }

        if (!function_exists('import_apply_taxonomy_gaps')) {
            \Besoiu\Services\Import\ImportLibLoader::bootFull(skipHttp: true);
        }

        $pdo = $this->db();
        $updateTaxonomyStmt = $pdo->prepare(
            'UPDATE import_produse SET pCategory = ?, pSubcategory = ? WHERE id = ? AND status = ?'
        );
        $updateVehicleStmt = $pdo->prepare(
            'UPDATE import_produse SET pMarca = ?, pModel = ?, pMotorizare = ?, pCategory = ?, pSubcategory = ?, pCompatibilitati = ?, raw_json = ? WHERE id = ? AND status = ?'
        );
        $updateImageStmt = $pdo->prepare(
            "UPDATE import_produse SET pImages = ?, pImageSource = ?, raw_json = ? WHERE id = ? AND status = ?"
        );

        foreach ($rows as $index => $row) {
            if (!is_array($row)) {
                continue;
            }

            if (function_exists('besoiu_import_row_hydrate_missing_image')) {
                $beforeImages = (string) ($row['pImages'] ?? '[]');
                if ($beforeImages === '[]' || trim($beforeImages) === '') {
                    $fullStmt = $pdo->prepare('SELECT raw_json FROM import_produse WHERE id = ? LIMIT 1');
                    $fullStmt->execute([(int) ($row['id'] ?? 0)]);
                    $fullRaw = $fullStmt->fetchColumn();
                    if (is_string($fullRaw) && $fullRaw !== '') {
                        $row['raw_json'] = $fullRaw;
                    }
                }
                $row = besoiu_import_row_hydrate_missing_image($row);
                $afterImages = (string) ($row['pImages'] ?? '[]');
                if ($beforeImages !== $afterImages && $afterImages !== '[]') {
                    $updateImageStmt->execute([
                        $afterImages,
                        (string) ($row['pImageSource'] ?? 'local_ttc_poze'),
                        (string) ($row['raw_json'] ?? '{}'),
                        (int) ($row['id'] ?? 0),
                        'pending',
                    ]);
                }
            }

            if (function_exists('import_hydrate_pending_queue_row')) {
                $beforeMarca = trim((string) ($row['pMarca'] ?? ''));
                $beforeModel = trim((string) ($row['pModel'] ?? ''));
                $beforeMotorizare = trim((string) ($row['pMotorizare'] ?? ''));
                $beforeCompat = trim((string) ($row['pCompatibilitati'] ?? ''));
                $beforeRaw = (string) ($row['raw_json'] ?? '{}');
                $row = import_hydrate_pending_queue_row($row);
                $afterMarca = trim((string) ($row['pMarca'] ?? ''));
                $afterModel = trim((string) ($row['pModel'] ?? ''));
                $afterMotorizare = trim((string) ($row['pMotorizare'] ?? ''));
                $afterCompat = trim((string) ($row['pCompatibilitati'] ?? ''));
                $afterRaw = (string) ($row['raw_json'] ?? '{}');
                if (
                    $beforeMarca !== $afterMarca
                    || $beforeModel !== $afterModel
                    || $beforeMotorizare !== $afterMotorizare
                    || $beforeCompat !== $afterCompat
                    || $beforeRaw !== $afterRaw
                ) {
                    $updateVehicleStmt->execute([
                        $afterMarca,
                        $afterModel,
                        $afterMotorizare,
                        trim((string) ($row['pCategory'] ?? '')),
                        trim((string) ($row['pSubcategory'] ?? '')),
                        $afterCompat,
                        $afterRaw,
                        (int) ($row['id'] ?? 0),
                        'pending',
                    ]);
                }
            }

            $beforeCategory = trim((string) ($row['pCategory'] ?? ''));
            $beforeSubcategory = trim((string) ($row['pSubcategory'] ?? ''));
            $row = import_apply_taxonomy_gaps($row);

            $afterCategory = trim((string) ($row['pCategory'] ?? ''));
            $afterSubcategory = trim((string) ($row['pSubcategory'] ?? ''));
            if (
                ($beforeCategory !== $afterCategory || $beforeSubcategory !== $afterSubcategory)
                && ($afterCategory !== '' || $afterSubcategory !== '')
            ) {
                $updateTaxonomyStmt->execute([
                    $afterCategory,
                    $afterSubcategory,
                    (int) ($row['id'] ?? 0),
                    'pending',
                ]);
            }

            $rows[$index] = $row;
        }

        return $rows;
    }

    /**
     * @deprecated Nu mai șterge imaginile la încărcarea paginii — doar validare la publicare.
     *
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    public function clearUntrustedPendingImages(array $rows): array
    {
        $validatePath = dirname(__DIR__, 3) . '/Legacy/import-image-validate.php';
        if (is_file($validatePath)) {
            require_once $validatePath;
        }
        if (!function_exists('besoiu_import_row_has_trusted_image')) {
            return $rows;
        }

        $pdo = $this->db();
        $clearStmt = $pdo->prepare(
            "UPDATE import_produse SET pImages = '[]', pImageSource = 'missing' WHERE id = ? AND status = 'pending'"
        );

        foreach ($rows as $index => $row) {
            if (!is_array($row) || besoiu_import_row_has_trusted_image($row)) {
                continue;
            }
            $rawImages = json_decode((string) ($row['pImages'] ?? '[]'), true);
            if (!is_array($rawImages) || $rawImages === []) {
                continue;
            }
            $clearStmt->execute([(int) ($row['id'] ?? 0)]);
            $rows[$index]['pImages'] = '[]';
            $rows[$index]['pImageSource'] = 'missing';
        }

        return $rows;
    }

    /** Număr produse active (status <> '0') — folosit și de panoul BaseLinker. */
    public function countActiveProducts(): int
    {
        $stmt = $this->db()->prepare("SELECT COUNT(*) FROM produse WHERE status <> :inactive");
        $stmt->execute([':inactive' => '0']);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Date auxiliare BaseLinker (feed + shop) — PDO rămâne în Service, nu în view.
     *
     * @return array{feed: mixed, shop: mixed}
     */
    public function loadBaselinkerAux(int $connectionId): array
    {
        $pdo = $this->db();
        $feed = null;
        $shop = null;
        $root = dirname(__DIR__, 3);

        if ($connectionId > 0) {
            $feedLib = $root . '/Legacy/import-queue-baselinker-export.php';
            if (is_file($feedLib)) {
                require_once $feedLib;
                if (function_exists('baselinker_feed_info')) {
                    $feed = baselinker_feed_info($pdo);
                }
            }
        }

        $shopLib = $root . '/system/baselinker-shop-integration.php';
        if (is_file($shopLib)) {
            require_once $shopLib;
            if (function_exists('baselinker_shop_info')) {
                $shop = baselinker_shop_info($pdo);
            }
        }

        return ['feed' => $feed, 'shop' => $shop];
    }
}
