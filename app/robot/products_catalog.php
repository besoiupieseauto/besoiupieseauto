<?php

declare(strict_types=1);

/**
 * Catalog produse robot — sursă primară MySQL (Besoiu Admin), fallback products.json.
 * Cache scurt pe disc pentru webhook/widget (TTL configurabil).
 */

require_once __DIR__ . '/oem_lib.php';

function robot_products_catalog_ttl(): int
{
    return max(60, (int) env('ROBOT_PRODUCTS_CACHE_TTL', 300));
}

/** @param array<string, mixed> $row */
function robot_products_map_row(array $row): array
{
    $images = json_decode((string) ($row['pImages'] ?? '[]'), true);
    $image = '';
    if (is_array($images) && isset($images[0])) {
        $image = (string) $images[0];
    }

    $note = (string) ($row['pNote'] ?? '');
    if ($note !== '' && str_contains($note, '<')) {
        $note = trim(preg_replace('/\s+/', ' ', strip_tags($note)) ?? '');
    }

    return [
        'name' => (string) ($row['pName'] ?? ''),
        'code' => (string) ($row['pCode'] ?? ''),
        'oem' => (string) ($row['pOem'] ?? ''),
        'price' => (string) ($row['pPrice'] ?? ''),
        'stock' => (int) ($row['pStock'] ?? 0),
        'brand' => (string) ($row['pBrand'] ?? ''),
        'description' => $note,
        'image' => $image,
        'randomn_id' => (string) ($row['randomn_id'] ?? ''),
        'category' => (string) ($row['pCategory'] ?? ''),
        'subcategory' => (string) ($row['pSubcategory'] ?? ''),
        'specs' => (string) ($row['pSpecs'] ?? ''),
        'car' => (string) ($row['pCar'] ?? ''),
        'marca' => (string) ($row['pMarca'] ?? ''),
        'model' => (string) ($row['pModel'] ?? ''),
    ];
}

/** @return list<array<string, mixed>> */
function robot_products_load_from_db(int $limit = 800): array
{
    $pdo = fb_db_pdo();
    if ($pdo === null) {
        return [];
    }

    $limit = max(1, min(2000, $limit));

    try {
        $sql = "SELECT pName, pCode, pOem, pPrice, pStock, pBrand, pNote, pImages, randomn_id,
                       pCategory, pSubcategory, pSpecs, pCar, pMarca, pModel
                FROM produse
                WHERE status <> '0'
                ORDER BY id DESC
                LIMIT {$limit}";
        $rows = $pdo->query($sql)->fetchAll() ?: [];

        return array_values(array_map('robot_products_map_row', $rows));
    } catch (Throwable $e) {
        return [];
    }
}

/** @return list<array<string, mixed>> */
function robot_products_load_json_fallback(): array
{
    $path = __DIR__ . '/products.json';
    if (!is_file($path)) {
        return [];
    }

    $decoded = json_decode((string) file_get_contents($path), true);

    return is_array($decoded) ? $decoded : [];
}

/**
 * Încarcă catalogul pentru robot (memorie + cache disc + BD).
 *
 * @return list<array<string, mixed>>
 */
function robot_products_load_catalog(bool $forceRefresh = false): array
{
    static $memory = null;
    if (!$forceRefresh && is_array($memory)) {
        return $memory;
    }

    $cacheFile = __DIR__ . '/data/products_catalog_cache.json';
    $ttl = robot_products_catalog_ttl();

    if (!$forceRefresh && is_file($cacheFile)) {
        $cache = json_decode((string) file_get_contents($cacheFile), true);
        if (is_array($cache) && isset($cache['generated_at'], $cache['items']) && is_array($cache['items'])) {
            $generatedAt = strtotime((string) $cache['generated_at']);
            $staleSchema = !isset($cache['schema']) || (int) ($cache['schema'] ?? 0) < 4;
            if (!$staleSchema && $generatedAt !== false && (time() - $generatedAt) < $ttl) {
                $memory = $cache['items'];

                return $memory;
            }
        }
    }

    $items = robot_products_load_from_db();
    $source = 'database';

    if ($items === []) {
        $items = robot_products_load_json_fallback();
        $source = 'json_fallback';
    } else {
        $dataDir = __DIR__ . '/data';
        if (!is_dir($dataDir)) {
            @mkdir($dataDir, 0775, true);
        }
        @file_put_contents($cacheFile, json_encode([
            'generated_at' => date('c'),
            'source' => $source,
            'schema' => 4,
            'count' => count($items),
            'items' => $items,
        ], JSON_UNESCAPED_UNICODE));
        @file_put_contents(__DIR__ . '/products.json', json_encode($items, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    $memory = $items;

    return $items;
}
