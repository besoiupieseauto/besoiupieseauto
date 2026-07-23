<?php
declare(strict_types=1);

require __DIR__ . '/../api/bootstrap.php';
require_once __DIR__ . '/../api/lib/ImportFastCardPipeline.php';

import_motor_boot_furnizori_libs();
import_require_prelucrare_lib('BaseIndexLookup.php');

$products = [
    [
        'status' => 'exact',
        'sku_supplier' => '558 0092 10',
        'brand' => 'INA',
        'matched_brand' => 'INA',
        'matched_name' => 'Set lant, antrenare pompa ulei',
        'match_method' => 'tecdoc_brand_code',
        'source_file' => 'elit/Lista pret Elit 16.01.2026.csv',
        'price_purchase_net' => 316.08,
        'price_csv' => 301.03,
    ],
    [
        'status' => 'exact',
        'sku_supplier' => '821 0850 10',
        'brand' => 'FAG',
        'matched_brand' => 'FAG',
        'matched_name' => 'Brat, suspensie roata',
        'match_method' => 'tecdoc_brand_code',
        'source_file' => 'elit/Lista pret Elit 16.01.2026.csv',
        'price_purchase_net' => 312.3,
        'price_csv' => 297.43,
    ],
];

$result = ImportFastCardPipeline::enrichProductsToCards($products, false);
echo 'Cards: ' . count($result['cards']) . ', skipped: ' . $result['skipped'] . "\n";
foreach ($result['cards'] as $card) {
    echo "\n--- {$card['sku']} ---\n";
    echo '  build: ' . ($card['cardBuild'] ?? '—') . "\n";
    echo '  params: ' . count($card['parameters'] ?? []) . "\n";
    echo '  compat: ' . (int) ($card['compatCount'] ?? 0) . "\n";
    echo '  desc: ' . mb_strlen((string) ($card['description'] ?? '')) . " chars\n";
    echo '  image: ' . (!empty($card['hasImage']) ? 'yes' : 'no') . "\n";
    echo '  tecdocDataComplete: ' . (!empty($card['tecdocDataComplete']) ? 'yes' : 'no') . "\n";
}
