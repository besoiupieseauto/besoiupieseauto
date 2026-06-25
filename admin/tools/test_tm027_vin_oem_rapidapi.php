<?php
declare(strict_types=1);

/**
 * tm_027 — VIN decode + OEM în stoc + filtrare branduri locale pe articole TecDoc.
 */

require_once dirname(__DIR__, 2) . '/system/tecdoc_stock.php';

$testVin = $argv[1] ?? 'WDD1690311J875947';
$testOem = $argv[2] ?? 'HU7262X';
$errors = [];

echo "=== tm_027 VIN decode ===\n";
$decode = tecdoc_decode_vin($testVin);
if (empty($decode['success'])) {
    $errors[] = 'decode_vin failed: ' . ($decode['message'] ?? 'unknown');
}
echo json_encode([
    'success' => $decode['success'] ?? false,
    'car_id' => $decode['car_id'] ?? null,
    'vehicle_label' => $decode['vehicle']['label'] ?? null,
], JSON_UNESCAPED_UNICODE) . "\n\n";

echo "=== tm_027 search_stock (VIN) ===\n";
$vinSearch = tecdoc_public_search(['vin' => $testVin]);
if (empty($vinSearch['success'])) {
    $errors[] = 'search_stock vin failed';
}
echo json_encode([
    'success' => $vinSearch['success'] ?? false,
    'count' => $vinSearch['count'] ?? 0,
    'vehicle' => $vinSearch['vehicle']['label'] ?? null,
    'stock_brands' => $vinSearch['stock_brands'] ?? [],
], JSON_UNESCAPED_UNICODE) . "\n\n";

echo "=== tm_027 search_oem ===\n";
$oemSearch = tecdoc_search_oem_in_stock($testOem);
if (empty($oemSearch['success'])) {
    $errors[] = 'search_oem failed: ' . ($oemSearch['message'] ?? 'unknown');
}
echo json_encode([
    'success' => $oemSearch['success'] ?? false,
    'query' => $oemSearch['query'] ?? '',
    'count' => $oemSearch['count'] ?? 0,
    'scanned' => $oemSearch['scanned'] ?? 0,
    'stock_brands' => $oemSearch['stock_brands'] ?? [],
    'notice' => $oemSearch['notice'] ?? null,
], JSON_UNESCAPED_UNICODE) . "\n\n";

echo "=== tm_027 local brand helpers ===\n";
$pdo = tecdoc_db();
$brands = tecdoc_local_stock_brand_labels($pdo, true);
$sampleArticle = ['supplierName' => $brands[0] ?? 'BOSCH', 'brandName' => $brands[0] ?? 'BOSCH'];
echo json_encode([
    'local_brands_count' => count($brands),
    'sample_matches_local' => tecdoc_article_has_local_stock_brand($sampleArticle, $brands),
    'foreign_rejected' => tecdoc_article_has_local_stock_brand(
        ['supplierName' => 'ZZZ-NOT-IN-STOCK-999', 'brandName' => 'ZZZ-NOT-IN-STOCK-999'],
        $brands
    ),
], JSON_UNESCAPED_UNICODE) . "\n\n";

if ($errors !== []) {
    fwrite(STDERR, implode("\n", $errors) . "\n");
    exit(1);
}

echo "TM027_VIN_OEM_RAPIDAPI_OK\n";
