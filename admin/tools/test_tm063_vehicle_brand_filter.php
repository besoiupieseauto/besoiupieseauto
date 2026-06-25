<?php
declare(strict_types=1);

/**
 * tm_063 — RapidAPI vehicul/categorie: listă piese + branduri filtrate la stoc local.
 */

require_once dirname(__DIR__, 2) . '/system/tecdoc_stock.php';

$errors = [];
$pdo = tecdoc_db();

echo "=== tm_063 local brand helpers ===\n";
$localBrands = tecdoc_local_stock_brand_labels($pdo, true);
$sampleApiBrands = ['BOSCH', 'MANN-FILTER', 'ZZZ-NOT-IN-STOCK-999'];
$matched = tecdoc_filter_brand_labels_to_local_stock($sampleApiBrands, $pdo);
echo json_encode([
    'local_brands_count' => count($localBrands),
    'sample_api_brands' => $sampleApiBrands,
    'matched_stock_brands' => $matched,
    'foreign_rejected' => !in_array('ZZZ-NOT-IN-STOCK-999', $matched, true),
], JSON_UNESCAPED_UNICODE) . "\n\n";

if ($matched === [] && $localBrands !== []) {
    $errors[] = 'filter_brand_labels_to_local_stock returned empty for known local brand sample';
}

echo "=== tm_063 search_stock (category + car_id) ===\n";
$search = tecdoc_public_search([
    'category' => 'Filtre',
    'car_id' => '1',
]);
if (empty($search['success'])) {
    $errors[] = 'search_stock failed';
}
echo json_encode([
    'success' => $search['success'] ?? false,
    'count' => $search['count'] ?? 0,
    'stock_brands' => $search['stock_brands'] ?? null,
    'has_stock_brands_key' => array_key_exists('stock_brands', $search),
], JSON_UNESCAPED_UNICODE) . "\n\n";

if (!array_key_exists('stock_brands', $search)) {
    $errors[] = 'search_stock missing stock_brands key';
}

echo "=== tm_063 get_articles BD path ===\n";
$bdSearch = tecdoc_bd_stock_search([
    'category' => 'Filtre',
    'subcategory' => 'Filtru ulei',
], 20, 0, 0);
if (empty($bdSearch['success'])) {
    $errors[] = 'bd_stock_search failed';
}
echo json_encode([
    'success' => $bdSearch['success'] ?? false,
    'count' => $bdSearch['count'] ?? 0,
    'stock_brands' => $bdSearch['stock_brands'] ?? null,
    'has_stock_brands_key' => array_key_exists('stock_brands', $bdSearch),
], JSON_UNESCAPED_UNICODE) . "\n\n";

if (!array_key_exists('stock_brands', $bdSearch)) {
    $errors[] = 'bd_stock_search missing stock_brands key';
}

if ($errors !== []) {
    fwrite(STDERR, implode("\n", $errors) . "\n");
    exit(1);
}

echo "TM063_VEHICLE_BRAND_FILTER_OK\n";
