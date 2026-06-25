<?php

declare(strict_types=1);

namespace Evasystem\Controllers\Produse;

use Config\Database;
use Evasystem\Core\Categorii\CategoriiModel;
use PDO;

/**
 * Facete de filtrare derivate exclusiv din produsele publicate in BD.
 */
final class ProductFacetsService
{
    private PDO $pdo;
    private CategoriiModel $categoriiModel;

    public function __construct(?PDO $pdo = null, ?CategoriiModel $categoriiModel = null)
    {
        $this->pdo = $pdo ?? Database::getDB();
        $this->categoriiModel = $categoriiModel ?? new CategoriiModel();
    }

    /** @return array{categories:array<int,array<string,mixed>>,subcategories:array<int,array<string,mixed>>,marci:array<int,array<string,mixed>>,brands:array<int,array<string,mixed>>} */
    public function getListFilters(): array
    {
        $cachePath = dirname(__DIR__, 3) . '/storage/cache/product_facets_admin.json';
        $ttl = 300;

        if (is_file($cachePath) && (time() - (int) filemtime($cachePath)) < $ttl) {
            $cached = json_decode((string) file_get_contents($cachePath), true);
            if (is_array($cached)
                && isset($cached['categories'], $cached['subcategories'], $cached['marci'], $cached['brands'])) {
                return $cached;
            }
        }

        $data = [
            'categories' => $this->getCategories(),
            'subcategories' => $this->getSubcategories(),
            'marci' => $this->getMarci(),
            'brands' => $this->getBrands(),
        ];

        $dir = dirname($cachePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        file_put_contents(
            $cachePath,
            json_encode($data, JSON_UNESCAPED_UNICODE),
            LOCK_EX
        );

        return $data;
    }

    /** @return array{categories:array<int,array<string,mixed>>,subcategories:array<int,array<string,mixed>>,marci:array<int,array<string,mixed>>,brands:array<int,array<string,mixed>>,modele:array<int,array<string,mixed>>} */
    public function getAll(): array
    {
        $cachePath = dirname(__DIR__, 3) . '/storage/cache/product_facets_public.json';
        $ttl = 300;

        if (is_file($cachePath) && (time() - (int) filemtime($cachePath)) < $ttl) {
            $cached = json_decode((string) file_get_contents($cachePath), true);
            if (is_array($cached)
                && isset($cached['categories'], $cached['subcategories'], $cached['marci'], $cached['brands'], $cached['modele'])) {
                return $cached;
            }
        }

        $data = [
            'categories' => $this->getCategories(),
            'subcategories' => $this->getSubcategories(),
            'marci' => $this->getMarci(),
            'brands' => $this->getBrands(),
            'modele' => $this->getModele(),
        ];

        $dir = dirname($cachePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        file_put_contents(
            $cachePath,
            json_encode($data, JSON_UNESCAPED_UNICODE),
            LOCK_EX
        );

        return $data;
    }

    /** @return array<int, array{label:string,slug:string,icon:string,count:int}> */
    public function getCategories(): array
    {
        $rows = $this->fetchGroupedColumn('pCategory');
        $icons = $this->categoryIconMap();

        $result = [];
        foreach ($rows as $row) {
            $label = trim((string) ($row['label'] ?? ''));
            if ($label === '' || !$this->isValidFacetLabel($label)) {
                continue;
            }
            $slug = $this->slugify($label);
            $result[] = [
                'label' => $label,
                'slug' => $slug,
                'icon' => $icons[$this->normalizeKey($label)] ?? $icons[$slug] ?? '',
                'count' => (int) ($row['count'] ?? 0),
            ];
        }

        return $this->boostWithSearchInsights($result, 'categories');
    }

    /** @return array<int, array{label:string,slug:string,category:string,count:int}> */
    public function getSubcategories(?string $category = null): array
    {
        $sql = "
            SELECT TRIM(pSubcategory) AS label, TRIM(pCategory) AS category, COUNT(*) AS count
            FROM produse
            WHERE status <> '0'
              AND TRIM(COALESCE(pSubcategory, '')) <> ''
        ";
        $params = [];
        if ($category !== null && trim($category) !== '') {
            $sql .= " AND TRIM(pCategory) = :category";
            $params['category'] = trim($category);
        }
        $sql .= " GROUP BY TRIM(pSubcategory), TRIM(pCategory) ORDER BY label ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $result = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $label = trim((string) ($row['label'] ?? ''));
            if ($label === '' || !$this->isValidFacetLabel($label)) {
                continue;
            }
            $result[] = [
                'label' => $label,
                'slug' => $this->slugify($label),
                'category' => trim((string) ($row['category'] ?? '')),
                'count' => (int) ($row['count'] ?? 0),
            ];
        }

        return $result;
    }

    /** @return array<int, array{label:string,slug:string,tecdoc_id:?int,count:int}> */
    public function getMarci(): array
    {
        $rows = $this->fetchGroupedColumn('pMarca');
        $tecdocMap = $this->marcaTecdocMap();

        $result = [];
        foreach ($rows as $row) {
            $label = trim((string) ($row['label'] ?? ''));
            if ($label === '' || !$this->isValidFacetLabel($label)) {
                continue;
            }
            $key = $this->normalizeKey($label);
            $meta = $tecdocMap[$key] ?? null;
            $result[] = [
                'label' => $label,
                'slug' => $this->slugify($label),
                'tecdoc_id' => isset($meta['tecdoc_id']) ? (int) $meta['tecdoc_id'] : null,
                'id' => isset($meta['id']) ? (int) $meta['id'] : null,
                'count' => (int) ($row['count'] ?? 0),
            ];
        }

        return $this->boostWithSearchInsights($result, 'marci');
    }

    /** @return array<int, array{label:string,slug:string,count:int}> */
    public function getBrands(): array
    {
        $rows = $this->fetchGroupedColumn('pBrand');
        $result = [];
        foreach ($rows as $row) {
            $label = trim((string) ($row['label'] ?? ''));
            if ($label === '' || !$this->isValidFacetLabel($label)) {
                continue;
            }
            $result[] = [
                'label' => $label,
                'slug' => $this->slugify($label),
                'count' => (int) ($row['count'] ?? 0),
            ];
        }

        return $result;
    }

    /** @return array<int, array{label:string,slug:string,marca:string,count:int}> */
    public function getModele(): array
    {
        $sql = "
            SELECT TRIM(pModel) AS label, TRIM(pMarca) AS marca, COUNT(*) AS count
            FROM produse
            WHERE status <> '0'
              AND TRIM(COALESCE(pModel, '')) <> ''
            GROUP BY TRIM(pModel), TRIM(pMarca)
            ORDER BY label ASC
        ";
        $stmt = $this->pdo->query($sql);
        $result = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $label = trim((string) ($row['label'] ?? ''));
            if ($label === '' || !$this->isValidFacetLabel($label)) {
                continue;
            }
            $result[] = [
                'label' => $label,
                'slug' => $this->slugify($label),
                'marca' => trim((string) ($row['marca'] ?? '')),
                'count' => (int) ($row['count'] ?? 0),
            ];
        }

        return $this->boostWithSearchInsights($result, 'modele');
    }

    /** @return array<int, array{id:int,slug:string,label:string,icon:string,count:int}> */
    public function getForPopup(): array
    {
        return array_map(static function (array $item): array {
            return [
                'id' => 0,
                'slug' => $item['slug'],
                'label' => $item['label'],
                'icon' => $item['icon'],
                'count' => $item['count'],
            ];
        }, $this->getCategories());
    }

    /** @return array<int, array<string, mixed>> */
    private function fetchGroupedColumn(string $column): array
    {
        if (!in_array($column, ['pCategory', 'pSubcategory', 'pMarca', 'pBrand'], true)) {
            return [];
        }

        $sql = "
            SELECT TRIM({$column}) AS label, COUNT(*) AS count
            FROM produse
            WHERE status <> '0'
              AND TRIM(COALESCE({$column}, '')) <> ''
            GROUP BY TRIM({$column})
            ORDER BY label ASC
        ";

        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array<string, string> */
    private function categoryIconMap(): array
    {
        $map = [];
        foreach ($this->categoriiModel->findByType('categorie') as $row) {
            $icon = trim((string) ($row['icon'] ?? ''));
            if ($icon === '') {
                continue;
            }
            $label = trim((string) ($row['label'] ?? ''));
            $slug = trim((string) ($row['slug'] ?? ''));
            if ($label !== '') {
                $map[$this->normalizeKey($label)] = $icon;
            }
            if ($slug !== '') {
                $map[$slug] = $icon;
            }
        }

        return $map;
    }

    /** @return array<string, array{id:int,tecdoc_id:?int}> */
    private function marcaTecdocMap(): array
    {
        $map = [];
        foreach ($this->categoriiModel->findByType('marca') as $row) {
            $label = trim((string) ($row['label'] ?? ''));
            if ($label === '') {
                continue;
            }
            $tecdocId = trim((string) ($row['tecdoc_id'] ?? ''));
            $map[$this->normalizeKey($label)] = [
                'id' => (int) ($row['id'] ?? 0),
                'tecdoc_id' => $tecdocId !== '' ? (int) $tecdocId : null,
            ];
        }

        return $map;
    }

    private function slugify(string $label): string
    {
        $slug = mb_strtolower($label, 'UTF-8');
        $slug = str_replace(
            ['ă', 'â', 'î', 'ș', 'ş', 'ț', 'ţ'],
            ['a', 'a', 'i', 's', 's', 't', 't'],
            $slug
        );
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
        return trim($slug, '-') ?: 'item';
    }

    private function normalizeKey(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        $value = str_replace(
            ['ă', 'â', 'î', 'ș', 'ş', 'ț', 'ţ'],
            ['a', 'a', 'i', 's', 's', 't', 't'],
            $value
        );
        return preg_replace('/[^a-z0-9]+/', '', $value) ?? $value;
    }

    private function isValidFacetLabel(string $label): bool
    {
        if (mb_strlen($label) > 80) {
            return false;
        }

        return !preg_match('~(https?://|www\.|/|\\\\|\.(jpg|jpeg|png|webp|gif)(\?|$))~i', $label);
    }

    /** @param array<int, array<string, mixed>> $items @return array<int, array<string, mixed>> */
    private function boostWithSearchInsights(array $items, string $insightKey): array
    {
        if ($items === []) {
            return $items;
        }

        $insightsPath = dirname(__DIR__, 4) . '/system/search_logs.php';
        $tecdocPath = dirname(__DIR__, 4) . '/system/tecdoc_stock.php';
        if (!is_file($insightsPath) || !is_file($tecdocPath)) {
            return $items;
        }

        require_once $insightsPath;
        require_once $tecdocPath;

        if (!function_exists('search_logs_storefront_insights') || !function_exists('search_logs_boost_facet_items')) {
            return $items;
        }

        try {
            $payload = search_logs_storefront_insights(tecdoc_db(), 12, 90);
        } catch (\Throwable) {
            return $items;
        }

        if (empty($payload['available'])) {
            return $items;
        }

        $insights = match ($insightKey) {
            'categories' => is_array($payload['categories'] ?? null) ? $payload['categories'] : [],
            'marci' => is_array($payload['marci'] ?? null) ? $payload['marci'] : [],
            'modele' => is_array($payload['modele'] ?? null) ? $payload['modele'] : [],
            default => [],
        };

        if ($insights === []) {
            return $items;
        }

        return search_logs_boost_facet_items($items, $insights);
    }
}
