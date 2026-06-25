<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/Controllers/Produse/import_supplier_lib.php';

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();
$dbConfig = require dirname(__DIR__) . '/config/config.php';

\Config\Database::getInstance(
    $dbConfig['db_host'],
    $dbConfig['db_name'],
    $dbConfig['db_user'],
    $dbConfig['db_pass']
);

use Evasystem\Controllers\Furnizori\PriceFormationLogicService;

$service = new PriceFormationLogicService();
$testConfig = [
    'scan_order' => ['AUTOTOTAL', 'AUTONET', 'MATEROM', 'AUTOPARTNER', 'ELIT', 'INTERCARS'],
    'omit_suppliers' => [],
    'brand_verify' => 'exact',
    'stock_verify' => 'skip_zero',
    'price_strategy' => 'hierarchical_top3_lowest',
    'compare_tier_size' => 3,
];

$config = $service->saveConfig($testConfig);
$priorityMap = $service->getPriorityMap();

// Tier 0: AUTOTOTAL câștigă față de INTERCARS (tier 3) chiar dacă prețul e mai mare.
if ($service->shouldReplacePrice(
    ['price' => 80.0, 'supplier' => 'AUTOTOTAL', 'brand' => 'MANN'],
    75.0,
    'INTERCARS',
    'MANN-FILTER'
)) {
    fwrite(STDERR, "FAIL: INTERCARS nu trebuie să înlocuiască AUTOTOTAL din tier superior\n");
    exit(1);
}

// Tier 0: AUTONET (120) rămâne față de ELIT (118) din tier inferior.
if ($service->shouldReplacePrice(
    ['price' => 120.0, 'supplier' => 'AUTONET', 'brand' => 'BOSCH'],
    118.0,
    'ELIT',
    'BOSCH'
)) {
    fwrite(STDERR, "FAIL: ELIT nu trebuie să înlocuiască AUTONET din același tier superior\n");
    exit(1);
}

// În același tier 0, prețul mai mic câștigă.
if (!$service->shouldReplacePrice(
    ['price' => 120.0, 'supplier' => 'AUTONET', 'brand' => 'BOSCH'],
    115.0,
    'MATEROM',
    'BOSCH'
)) {
    fwrite(STDERR, "FAIL: MATEROM cu preț mai mic trebuie să înlocuiască AUTONET în tier 0\n");
    exit(1);
}

$tierAutonet = $service->getSupplierCompareTier('AUTONET', $config, $priorityMap);
$tierElit = $service->getSupplierCompareTier('ELIT', $config, $priorityMap);
if ($tierAutonet !== 0 || $tierElit !== 2) {
    fwrite(STDERR, "FAIL: tier incorect AUTONET={$tierAutonet} ELIT={$tierElit}\n");
    exit(1);
}

echo "HIERARCHICAL_COMPARE_LOGIC_PASSED\n";
