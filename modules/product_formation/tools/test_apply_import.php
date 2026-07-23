<?php
declare(strict_types=1);

/**
 * Smoke test: applyToImportProduct for Import-Pro → queue.
 */
$adminRoot = dirname(__DIR__, 3) . '/admin';
require_once $adminRoot . '/bootstrap.php';
require_once $adminRoot . '/vendor/autoload.php';

use Besoiu\Services\ProductCardFormationService;

$svc = new ProductCardFormationService();
$product = [
    'pName' => 'Placute frana BOSCH 0 986 424 268',
    'pBrand' => 'BOSCH',
    'pCode' => '0 986 424 268',
    'pMarca' => 'Audi',
    'pModel' => 'A3 Sportback',
    'pMotorizare' => '1.6 TDI',
    'pCategory' => 'Frane',
    'pSubcategory' => 'Placute frana',
    'pCompatibilitati' => 'AUDI A3, VW Golf, SEAT Leon',
];
$raw = [
    '__tecdoc_art_name' => 'Placute frana',
    'product_summary' => [
        'tecdoc_art_name' => 'Placute frana',
        'title' => 'Placute frana BOSCH',
    ],
];

$applied = $svc->applyToImportProduct($product, $raw);
$pf = $applied['product_formation'];

echo 'applied: ' . (!empty($pf['applied']) ? 'OK' : 'FAIL') . PHP_EOL;
echo 'pName(website): ' . ($applied['product']['pName'] ?? '') . PHP_EOL;
echo 'pNameMarketplace: ' . ($applied['product']['pNameMarketplace'] ?? '') . PHP_EOL;
echo 'title_website: ' . ($pf['title_website'] ?? '') . PHP_EOL;
echo 'title_pieseauto: ' . ($pf['title_pieseauto'] ?? '') . PHP_EOL;
echo 'title_oem: ' . ($pf['title_oem'] ?? '') . PHP_EOL;
echo 'source_name: ' . ($pf['source_name'] ?? '') . PHP_EOL;

$row = array_merge($applied['product'], [
    'raw_json' => json_encode(array_merge($raw, ['product_formation' => $pf]), JSON_UNESCAPED_UNICODE),
]);
$mp = ProductCardFormationService::resolveMarketplaceTitleFromRow($row);
echo 'resolveMarketplace: ' . $mp . PHP_EOL;

$ok = !empty($pf['applied'])
    && trim((string) ($pf['title_website'] ?? '')) !== ''
    && trim((string) ($pf['title_pieseauto'] ?? '')) !== ''
    && trim((string) ($applied['product']['pNameMarketplace'] ?? '')) === trim((string) ($pf['title_pieseauto'] ?? ''))
    && $mp === ($pf['title_pieseauto'] ?? '');
echo 'overall: ' . ($ok ? 'OK' : 'FAIL') . PHP_EOL;

exit($ok ? 0 : 1);
