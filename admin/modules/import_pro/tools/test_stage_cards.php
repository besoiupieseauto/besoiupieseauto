<?php
declare(strict_types=1);

/**
 * Simulează fluxul stage-cards.php fără a defini manual BESOIU_LIB.
 */
$root = dirname(__DIR__, 3);
if (!defined('BESOIU_ROOT')) {
    define('BESOIU_ROOT', $root);
}

require_once $root . '/app/Import/MatchingPro/api/bootstrap.php';
require_once $root . '/app/Import/MatchingPro/api/lib/ImportCardStaging.php';

if (defined('BESOIU_LIB')) {
    fwrite(STDERR, "WARN: BESOIU_LIB already defined before boot\n");
}

import_motor_boot_admin_stack();

if (!defined('BESOIU_LIB')) {
    fwrite(STDERR, "FAIL: BESOIU_LIB still undefined after import_motor_boot_admin_stack\n");
    exit(2);
}

if (!import_motor_ensure_database()) {
    fwrite(STDERR, "FAIL: database unavailable\n");
    exit(3);
}

$sampleCard = [
    'title' => 'Ulei motor TEST STAGE ' . date('His'),
    'sku' => 'TEST-STAGE-' . date('His'),
    'brand' => 'TEST',
    'supplier' => 'AUTONET',
    'sourceSupplier' => 'AUTONET',
    'pricePurchaseNet' => 45.5,
    'pricePurchaseVat' => 55.06,
    'hasImage' => true,
    'scrapedImageUrl' => '/admin/dist/images/fakers/preview-12.jpg',
    'imageSource' => 'test',
];

import_motor_boot_furnizori_libs();
$cards = import_motor_normalize_card_list([$sampleCard]);
$products = ImportCardStaging::cardsToProducts($cards, 'standard');
if ($products === []) {
    fwrite(STDERR, "FAIL: no products from card\n");
    exit(4);
}

try {
    if (!function_exists('import_stage_products_for_review')) {
        \Besoiu\Services\Import\ImportLibLoader::bootFull(skipHttp: true);
    }
} catch (Throwable $e) {
    fwrite(STDERR, 'FAIL ImportLibLoader: ' . $e->getMessage() . "\n");
    exit(5);
}

if (!function_exists('import_stage_products_for_review')) {
    fwrite(STDERR, "FAIL: import_stage_products_for_review missing\n");
    exit(6);
}

$pdo = \Config\Database::getDB();
$markup = new \Besoiu\Services\AdaosComercial\AdaosComercialService();
$priceIndex = function_exists('import_build_multi_supplier_price_index')
    ? import_build_multi_supplier_price_index($pdo)
    : [];

$stats = import_stage_products_for_review($pdo, $products, $markup, [
    'epiesa_special_products' => false,
    'import_lane' => 'standard',
    'price_index' => $priceIndex,
]);

$queued = (int) ($stats['queued'] ?? 0);
echo 'BESOIU_LIB=' . BESOIU_LIB . PHP_EOL;
echo 'queued=' . $queued . PHP_EOL;

if ($queued >= 1) {
    $pdo->prepare('DELETE FROM import_produse WHERE pCode = ? ORDER BY id DESC LIMIT 1')
        ->execute([$sampleCard['sku']]);
    exit(0);
}

fwrite(STDERR, "FAIL: queued=0\n");
exit(7);
