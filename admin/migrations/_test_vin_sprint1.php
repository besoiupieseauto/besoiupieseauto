<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/system/tecdoc_stock.php';

$testVin = $argv[1] ?? 'WDD1690311J875947';

echo "=== decode_vin ===\n";
$decode = tecdoc_decode_vin($testVin);
echo json_encode($decode, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

echo "=== search_stock (VIN) ===\n";
$search = tecdoc_public_search(['vin' => $testVin]);
echo json_encode([
    'success' => $search['success'] ?? false,
    'count' => $search['count'] ?? 0,
    'car_id' => $search['car_id'] ?? null,
    'vehicle' => $search['vehicle'] ?? null,
    'notice' => $search['notice'] ?? null,
    'first_product' => $search['products'][0] ?? null,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

echo "=== search_logs (ultimele 5) ===\n";
$pdo = tecdoc_db();
echo json_encode(search_logs_list($pdo, 5), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
