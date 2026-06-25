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

$code = 'TM065TEST';
$brand = 'BOSCH';
$products = [
    [
        'id' => 1,
        'randomn_id' => 'tm065-a',
        'pCode' => $code,
        'pBrand' => $brand,
        'pSupplier' => 'AUTOTOTAL',
        'pPrice' => '11.00',
        'pStock' => '2',
    ],
    [
        'id' => 2,
        'randomn_id' => 'tm065-b',
        'pCode' => $code,
        'pBrand' => $brand,
        'pSupplier' => 'AUTONET',
        'pPrice' => '9.00',
        'pStock' => '3',
    ],
    [
        'id' => 3,
        'randomn_id' => 'tm065-c',
        'pCode' => $code,
        'pBrand' => $brand,
        'pSupplier' => 'MATEROM',
        'pPrice' => '10.00',
        'pStock' => '1',
    ],
];

$winners = tecdoc_deduplicate_catalog_rows_by_supplier_price($products);

if (count($winners) !== 1) {
    fwrite(STDERR, 'FAIL: expected 1 winner, got ' . count($winners) . PHP_EOL);
    exit(1);
}

$winner = $winners[0];
$winnerSupplier = strtoupper(trim(str_replace([' ', '-'], '', (string) ($winner['pSupplier'] ?? ''))));
$winnerPrice = tecdoc_row_price_numeric($winner);

if ($winnerSupplier !== 'AUTONET') {
    fwrite(STDERR, "FAIL: expected AUTONET winner, got {$winnerSupplier}" . PHP_EOL);
    exit(1);
}

if (abs($winnerPrice - 9.0) > 0.001) {
    fwrite(STDERR, "FAIL: expected price 9.00, got {$winnerPrice}" . PHP_EOL);
    exit(1);
}

echo "TM065_MIN_SUPPLIER_PRICE_OK\n";
echo "winner={$winnerSupplier}\n";
echo 'price=' . number_format($winnerPrice, 2, '.', '') . "\n";
