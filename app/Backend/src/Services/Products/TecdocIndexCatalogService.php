<?php

declare(strict_types=1);

namespace Besoiu\Services\Products;

use Config\Database;
use PDO;
use Throwable;

/**
 * Catalog produse indexate TecDoc — aceeași bază cu magazinul (unificat).
 */
final class TecdocIndexCatalogService
{
    private ?PDO $pdo = null;

    private bool $availabilityChecked = false;

    private bool $available = false;

    /** @var array<string, mixed>|null */
    private static ?array $statsCache = null;

    public function isAvailable(): bool
    {
        if ($this->availabilityChecked) {
            return $this->available;
        }

        $this->availabilityChecked = true;

        if (!function_exists('besoiupieseimport_tecdoc_table_exists')) {
            $lib = dirname(__DIR__, 3) . '/tools/besoiupieseimport_tecdoc_db.php';
            if (is_file($lib)) {
                require_once $lib;
            }
        }

        try {
            $pdo = $this->pdo();
            if (function_exists('besoiupieseimport_tecdoc_catalog_has_data')) {
                $this->available = besoiupieseimport_tecdoc_catalog_has_data($pdo);
            } elseif (function_exists('besoiupieseimport_tecdoc_catalog_ready')) {
                $this->available = besoiupieseimport_tecdoc_catalog_ready($pdo)
                    && (bool) $pdo->query('SELECT 1 FROM tecdoc_brands LIMIT 1')->fetchColumn();
            } else {
                $this->available = besoiupieseimport_tecdoc_table_exists($pdo, 'tecdoc_brands')
                    && besoiupieseimport_tecdoc_table_exists($pdo, 'tecdoc_products');
            }
        } catch (Throwable) {
            $this->available = false;
            $this->pdo = null;
        }

        return $this->available;
    }

    /** @return array{total:int,with_image:int,without_image:int,brands:int,connected:bool,unified?:bool,database?:string} */
    public function stats(): array
    {
        if (self::$statsCache !== null) {
            return self::$statsCache;
        }

        if (!$this->isAvailable()) {
            return self::$statsCache = $this->emptyStats(false);
        }

        try {
            $pdo = $this->pdo();
            $brands = $this->approximateRowCount($pdo, 'tecdoc_brands');
            $total = $this->approximateProductCount($pdo);
            $withImage = min($total, $this->approximateRowCount($pdo, 'tecdoc_product_images'));

            $target = function_exists('besoiupieseimport_tecdoc_db_target')
                ? besoiupieseimport_tecdoc_db_target()
                : ['unified' => true, 'name' => ''];

            return self::$statsCache = [
                'total' => $total,
                'with_image' => $withImage,
                'without_image' => max(0, $total - $withImage),
                'brands' => $brands,
                'connected' => true,
                'unified' => (bool) ($target['unified'] ?? true),
                'database' => (string) ($target['name'] ?? ''),
                'approximate' => true,
            ];
        } catch (Throwable) {
            return self::$statsCache = $this->emptyStats(false);
        }
    }

    /** Statistici rapide — fără numărări grele pe imagini. */
    public function statsFast(): array
    {
        if (!$this->isAvailable()) {
            return $this->emptyStats(false);
        }

        try {
            $pdo = $this->pdo();
            $brands = $this->approximateRowCount($pdo, 'tecdoc_brands');
            $total = $this->approximateProductCount($pdo);

            return [
                'total' => $total,
                'with_image' => 0,
                'without_image' => $total,
                'brands' => $brands,
                'connected' => true,
                'approximate' => true,
                'images_approximate' => true,
            ];
        } catch (Throwable) {
            return $this->emptyStats(false);
        }
    }

    /** @return list<array{name:string,count:int}> */
    public function listBrands(int $limit = 200): array
    {
        if (!$this->isAvailable()) {
            return [];
        }

        $limit = max(1, min(500, $limit));
        $stmt = $this->prepareWithTimeout(
            $this->pdo(),
            'SELECT name FROM tecdoc_brands ORDER BY name ASC LIMIT ' . $limit,
            2000
        );
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map(static fn (array $row): array => [
            'name' => (string) ($row['name'] ?? ''),
            'count' => 0,
        ], $rows);
    }

    /**
     * @param array{image?:string,brand?:string,search?:string} $filters
     * @return array{items:list<array<string,mixed>>,total:int,page:int,per_page:int,total_pages:int,approximate_total?:bool}
     */
    public function paginated(int $page = 1, int $perPage = 24, array $filters = []): array
    {
        if (!$this->isAvailable()) {
            return [
                'items' => [],
                'total' => 0,
                'page' => 1,
                'per_page' => $perPage,
                'total_pages' => 0,
            ];
        }

        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;
        $pdo = $this->pdo();

        [$whereSql, $params] = $this->buildWhere($filters);
        $filtered = trim($whereSql) !== '';

        $sql = 'SELECT
                p.id, p.art_code_1, p.art_code_2, p.art_name, p.art_ean,
                p.ttc_art_id, p.compat_count, b.name AS brand
            FROM tecdoc_products p
            INNER JOIN tecdoc_brands b ON b.id = p.brand_id
            ' . $whereSql . '
            ORDER BY p.id DESC
            LIMIT ' . (int) ($perPage + 1) . ' OFFSET ' . (int) $offset;

        $stmt = $this->prepareWithTimeout($pdo, $sql, 4000);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $hasMore = count($rows) > $perPage;
        if ($hasMore) {
            array_pop($rows);
        }

        $items = array_map([$this, 'normalizeRow'], $rows);
        $visibleLowerBound = $offset + count($items) + ($hasMore ? 1 : 0);
        $total = $filtered
            ? $visibleLowerBound
            : max($visibleLowerBound, $this->approximateProductCount($pdo));

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => $perPage > 0 ? (int) ceil($total / $perPage) : 0,
            'approximate_total' => true,
        ];
    }

    public static function imageBaseUrl(): string
    {
        $syncPath = dirname(__DIR__, 3) . '/config/besoiupieseimport_sync.php';
        $base = 'http://besoiupieseimport.test';
        if (is_file($syncPath)) {
            $sync = require $syncPath;
            if (is_array($sync) && !empty($sync['public_url'])) {
                $base = rtrim((string) $sync['public_url'], '/');
            }
        }

        return $base . '/Prelucrare%20fisiere%20bovsoft-base/api/product-image.php';
    }

    /** @return array{total:int,with_image:int,without_image:int,brands:int,connected:bool} */
    private function emptyStats(bool $connected): array
    {
        return [
            'total' => 0,
            'with_image' => 0,
            'without_image' => 0,
            'brands' => 0,
            'connected' => $connected,
        ];
    }

    private function approximateProductCount(PDO $pdo): int
    {
        try {
            return $this->approximateRowCount($pdo, 'tecdoc_products');
        } catch (Throwable) {
            return 0;
        }
    }

    private function approximateRowCount(PDO $pdo, string $table): int
    {
        if (function_exists('besoiupieseimport_tecdoc_approximate_row_count')) {
            return besoiupieseimport_tecdoc_approximate_row_count($pdo, $table);
        }

        $stmt = $this->prepareWithTimeout(
            $pdo,
            'SELECT CASE
                        WHEN COALESCE(TABLE_ROWS, 0) > 0 THEN TABLE_ROWS
                        ELSE GREATEST(0, COALESCE(AUTO_INCREMENT, 1) - 1)
                    END
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1',
            1500
        );
        $stmt->execute([$table]);

        return max(0, (int) $stmt->fetchColumn());
    }

    private function prepareWithTimeout(PDO $pdo, string $sql, int $milliseconds): \PDOStatement
    {
        if (function_exists('besoiupieseimport_tecdoc_prepare_with_timeout')) {
            return besoiupieseimport_tecdoc_prepare_with_timeout($pdo, $sql, $milliseconds);
        }

        return $pdo->prepare($sql);
    }

    /** @param array<string, mixed> $row */
    private function normalizeRow(array $row): array
    {
        $ttcId = trim((string) ($row['ttc_art_id'] ?? ''));

        return [
            'id' => (int) ($row['id'] ?? 0),
            'sku' => (string) ($row['art_code_1'] ?? ''),
            'code2' => (string) ($row['art_code_2'] ?? ''),
            'title' => (string) ($row['art_name'] ?? ''),
            'ean' => (string) ($row['art_ean'] ?? ''),
            'brand' => (string) ($row['brand'] ?? ''),
            'ttc_art_id' => $ttcId,
            'compat_count' => (int) ($row['compat_count'] ?? 0),
            'has_image' => $ttcId !== '',
            'image_source' => $ttcId !== '' ? 'poze' : '',
            'image_path' => '',
            'autopartner_code' => '',
        ];
    }

    /** @param array{image?:string,brand?:string,search?:string} $filters
     * @return array{0:string,1:array<string,mixed>}
     */
    private function buildWhere(array $filters): array
    {
        $clauses = [];
        $params = [];

        $image = strtolower(trim((string) ($filters['image'] ?? 'all')));
        if ($image === 'with') {
            $clauses[] = "(p.ttc_art_id IS NOT NULL AND TRIM(p.ttc_art_id) <> '')";
        } elseif ($image === 'without') {
            $clauses[] = "(p.ttc_art_id IS NULL OR TRIM(p.ttc_art_id) = '')";
        }

        $brand = trim((string) ($filters['brand'] ?? ''));
        if ($brand !== '') {
            $clauses[] = 'b.name = :brand';
            $params[':brand'] = $brand;
        }

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $clauses[] = '(p.art_code_1 LIKE :q OR p.art_code_2 LIKE :q OR p.art_name LIKE :q OR p.art_ean LIKE :q)';
            $params[':q'] = '%' . $search . '%';
        }

        return [$clauses !== [] ? 'WHERE ' . implode(' AND ', $clauses) : '', $params];
    }

    private function pdo(): PDO
    {
        if ($this->pdo instanceof PDO) {
            return $this->pdo;
        }

        $lib = dirname(__DIR__, 3) . '/tools/besoiupieseimport_tecdoc_db.php';
        if (is_file($lib)) {
            require_once $lib;
        }

        if (function_exists('besoiupieseimport_tecdoc_use_unified_db')
            && besoiupieseimport_tecdoc_use_unified_db()
            && Database::hasConnection()) {
            return $this->pdo = Database::getDB();
        }

        if (function_exists('besoiupieseimport_tecdoc_pdo')) {
            return $this->pdo = besoiupieseimport_tecdoc_pdo();
        }

        throw new \RuntimeException('Nu pot conecta la baza TecDoc index.');
    }
}
