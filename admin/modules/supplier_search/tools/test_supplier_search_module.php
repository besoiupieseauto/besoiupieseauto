<?php
declare(strict_types=1);

/**
 * Test modul supplier_search — structură, autoload, API handler, paritate Backend.
 * Usage: php modules/supplier_search/tools/test_supplier_search_module.php
 */

$erpRoot = dirname(__DIR__, 3);
$moduleRoot = $erpRoot . '/modules/supplier_search';
$backendRoot = $erpRoot . '/app/Backend/src/Services/SupplierSearch';
$failures = 0;

function ok(string $msg): void
{
    echo "[OK] {$msg}\n";
}

function fail(string $msg): void
{
    global $failures;
    $failures++;
    echo "[FAIL] {$msg}\n";
}

echo "=== Test Supplier Search Module ===\n";
echo "ERP: {$erpRoot}\n\n";

$required = [
    'module.json',
    'pages/supplier-search.php',
    'pages/supplier-cart.php',
    'pages/searching.php',
    'src/Handler/SupplierSearchApiHandler.php',
    'src/Handler/SupplierCartApiHandler.php',
    'src/Service/SupplierSearchService.php',
    'api/supplier_search_endpoint.php',
];

foreach ($required as $rel) {
    is_file($moduleRoot . '/' . $rel) ? ok("Fișier: {$rel}") : fail("Lipsește: {$rel}");
}

$endpoint = file_get_contents($moduleRoot . '/api/supplier_search_endpoint.php') ?: '';
if (str_contains($endpoint, 'SupplierSearchApiHandler')) {
    ok('api/supplier_search_endpoint.php → SupplierSearchApiHandler');
} else {
    fail('api/supplier_search_endpoint.php folosește handler greșit');
}

$publicEndpoint = $erpRoot . '/admin/public/api/supplier_search_endpoint.php';
if (is_file($publicEndpoint) && str_contains((string) file_get_contents($publicEndpoint), 'SupplierSearchApiHandler')) {
    ok('admin/public/api/supplier_search_endpoint.php corect');
} else {
    fail('admin/public/api/supplier_search_endpoint.php invalid');
}

$compareFiles = [
    'Parsers/MateromParser.php',
    'Parsers/ElitParser.php',
    'Parsers/AutopartnerParser.php',
    'Parsers/AutonetParser.php',
    'Parsers/AutototalParser.php',
    'Clients/AutonetClient.php',
    'Clients/AutototalClient.php',
    'Builders/AutonetSearchBuilder.php',
    'Builders/AutototalSearchBuilder.php',
    'ProductAggregator.php',
    'PricingApplier.php',
];

$parity = 0;
foreach ($compareFiles as $rel) {
    $mod = $moduleRoot . '/src/Service/' . $rel;
    $back = $backendRoot . '/' . $rel;
    if (!is_file($mod)) {
        fail("Modul lipsă: {$rel}");
        continue;
    }
    if (!is_file($back)) {
        ok("Modul extins (fără echivalent Backend): {$rel}");
        continue;
    }
    $modBody = preg_replace('/namespace\s+Besoiu\\\\Modules\\\\SupplierSearch\\\\Service/', 'NS', (string) file_get_contents($mod)) ?? '';
    $backBody = preg_replace('/namespace\s+Besoiu\\\\Services\\\\SupplierSearch/', 'NS', (string) file_get_contents($back)) ?? '';
    $modBody = preg_replace('/Besoiu\\\\Modules\\\\SupplierSearch\\\\Service\\\\/', '', $modBody);
    $backBody = preg_replace('/Besoiu\\\\Services\\\\SupplierSearch\\\\/', '', $backBody);
    if (trim($modBody) === trim($backBody)) {
        $parity++;
    } else {
        fail("Diferență logică: {$rel}");
    }
}
ok("Paritate Backend parsers/clients: {$parity}/" . count($compareFiles));

$serviceSrc = (string) file_get_contents($moduleRoot . '/src/Service/SupplierSearchService.php');
foreach (['function search(', 'function list(', 'function findById('] as $needle) {
    str_contains($serviceSrc, $needle) ? ok("SupplierSearchService::{$needle}") : fail("Lipsește {$needle}");
}

$bootSrc = (string) file_get_contents($moduleRoot . '/src/SupplierSearchModule.php');
str_contains($bootSrc, 'SupplierSearchModuleHooks::register()')
    ? ok('SupplierSearchModule înregistrează hook-uri')
    : fail('SupplierSearchModule fără hooks');

echo "\n";
if ($failures === 0) {
    echo "REZULTAT: TOATE TESTELE OK.\n";
    exit(0);
}

echo "REZULTAT: {$failures} eșec(uri).\n";
exit(1);
