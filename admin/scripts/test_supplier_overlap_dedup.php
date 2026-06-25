<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();
$config = require dirname(__DIR__) . '/config/config.php';

\Config\Database::getInstance(
    $config['db_host'],
    $config['db_name'],
    $config['db_user'],
    $config['db_pass']
);

require_once dirname(__DIR__, 2) . '/system/tecdoc_stock.php';

$daicoCode = 'TESTDAICO001';
$products = [
    [
        'id' => 1,
        'randomn_id' => 'a1',
        'code' => $daicoCode,
        'brand' => 'DAICO',
        'supplier' => 'AUTONET',
        'price_numeric' => 120.0,
        'stock' => '5',
    ],
    [
        'id' => 2,
        'randomn_id' => 'a2',
        'code' => $daicoCode,
        'brand' => 'DAICO',
        'supplier' => 'AUTOTOTAL',
        'price_numeric' => 115.0,
        'stock' => '2',
    ],
];

$winners = tecdoc_deduplicate_products_by_supplier_price($products);

if (count($winners) !== 1) {
    fwrite(STDERR, "FAIL: expected 1 winner, got " . count($winners) . "\n");
    exit(1);
}

$winner = $winners[0];
$winnerSupplier = strtoupper((string) ($winner['supplier'] ?? ''));

if ($winnerSupplier !== 'AUTOTOTAL') {
    fwrite(STDERR, "FAIL: expected AUTOTOTAL winner (lower price), got {$winnerSupplier}\n");
    exit(1);
}

echo "SUPPLIER_OVERLAP_DEDUP_OK\n";
echo "winner={$winnerSupplier}\n";
echo "price=" . (string) ($winner['price_numeric'] ?? '') . "\n";
