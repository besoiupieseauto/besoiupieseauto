<?php
declare(strict_types=1);

require __DIR__ . '/../api/bootstrap.php';
require_once __DIR__ . '/../api/lib/ImportTecdocMysqlEnrichment.php';
require_once __DIR__ . '/../api/lib/ImportFastCardPipeline.php';

import_motor_boot_furnizori_libs();

echo "=== Showcase supplier row ===\n";
$card = ImportTecdocMysqlEnrichment::cardFromSupplierRow(
    ['code' => '558 0092 10', 'brand' => 'INA', 'name' => 'Set lant', 'priceNet' => 316.08],
    'ELIT',
    'elit/test.csv'
);
echo $card ? ('OK params=' . count($card['parameters'] ?? []) . ' compat=' . (int) ($card['compatCount'] ?? 0)) : "NULL\n";

echo "\n=== Cron enrich batch ===\n";
$products = [[
    'status' => 'exact',
    'sku_supplier' => '821 0850 10',
    'brand' => 'FAG',
    'matched_brand' => 'FAG',
    'matched_name' => 'Brat, suspensie roata',
    'supplier' => 'elit',
    'source_file' => 'elit/test.csv',
    'price_purchase_net' => 312.3,
]];
$result = ImportFastCardPipeline::enrichProductsToCards($products, false);
echo 'cards=' . count($result['cards']) . ' body=' . (ImportTecdocMysqlEnrichment::cardHasTecdocBody($result['cards'][0] ?? []) ? 'yes' : 'no') . "\n";

echo "\n=== Staging enrich ===\n";
$product = [
    'pCode' => '558 0092 10',
    'pBrand' => 'INA',
    'pName' => 'Set lant',
    'pBasePrice' => '316.08',
    'pSupplier' => 'ELIT',
    'raw_json' => '{}',
];
$enriched = ImportTecdocMysqlEnrichment::enrichStagingProduct($product);
$raw = json_decode((string) ($enriched['raw_json'] ?? '{}'), true);
echo 'mysql=' . (($raw['tecdoc_import_enrichment']['source'] ?? '') === 'mysql_tecdoc' ? 'yes' : 'no');
echo ' compat=' . mb_strlen((string) ($enriched['pCompatibilitati'] ?? '')) . "\n";
