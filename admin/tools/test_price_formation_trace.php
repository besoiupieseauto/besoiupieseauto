<?php

declare(strict_types=1);

/**
 * Verifică tab log formare preț (tm_084).
 * Usage: php admin/tools/test_price_formation_trace.php
 */

require __DIR__ . '/php_cli.php';

$admin = dirname(__DIR__);
$failures = 0;

echo "=== test_price_formation_trace (tm_084) ===\n\n";

$phpBin = admin_php_cli_binary();
$lintTargets = [
    'src/Controllers/AdaosComercial/PriceFormationTraceService.php',
    'src/Controllers/AdaosComercial/AdaosComercialService.php',
    'src/Controllers/AdaosComercial/AdaosComercial.php',
    'src/Controllers/AdaosComercial/crudu.php',
    'Templates/admin/pages/adaoscomercial/_price-formation-log-tab.php',
    'Templates/admin/pages/adaoscomercial/adaoscomercial.php',
];

foreach ($lintTargets as $rel) {
    $path = $admin . '/' . str_replace('/', DIRECTORY_SEPARATOR, $rel);
    $out = [];
    $code = 0;
    exec('"' . $phpBin . '" -l ' . escapeshellarg($path) . ' 2>&1', $out, $code);
    $line = trim(implode(' ', $out));
    if ($code !== 0 || !str_contains($line, 'No syntax errors')) {
        echo "FAIL php -l {$rel}: {$line}\n";
        $failures++;
    } else {
        echo "OK  php -l {$rel}\n";
    }
}

require_once $admin . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable($admin);
$dotenv->safeLoad();
$config = require $admin . '/config/config.php';
\Config\Database::getInstance(
    $config['db_host'],
    $config['db_name'],
    $config['db_user'],
    $config['db_pass']
);

require_once $admin . '/src/Controllers/Produse/import_supplier_lib.php';

use Evasystem\Controllers\AdaosComercial\PriceFormationTraceService;

$service = new PriceFormationTraceService();
$trace = $service->traceByProductIdentifier('__nonexistent_product_xyz__');
if (($trace['success'] ?? null) !== false) {
    echo "FAIL trace produs inexistent trebuia success=false\n";
    $failures++;
} else {
    echo "OK  trace produs inexistent → eroare așteptată\n";
}

$sampleProduct = [
    'pCode' => 'TEST-TRACE-001',
    'pName' => 'Produs test trace',
    'pBrand' => 'BOSCH',
    'pSupplier' => 'ELIT',
    'pBasePrice' => '105',
    'pPrice' => '150',
    'pCategory' => 'Test',
];

$reflection = new ReflectionClass($service);
$build = $reflection->getMethod('buildTrace');
$build->setAccessible(true);
/** @var array<string, mixed> $built */
$built = $build->invoke($service, $sampleProduct, 'product');
$stepKeys = array_column($built['steps'] ?? [], 'key');
$required = ['feed', 'compensator', 'purchase', 'markup_global', 'markup_brand', 'vat', 'final'];
foreach ($required as $key) {
    if (!in_array($key, $stepKeys, true)) {
        echo "FAIL lipsește pasul {$key} din trace\n";
        $failures++;
    }
}
if ($failures === 0 || !in_array('feed', $stepKeys, true)) {
    // only print OK if we got here without step failures above
}
$missingSteps = array_diff($required, $stepKeys);
if ($missingSteps === []) {
    echo "OK  pași trace completi: " . implode(', ', $stepKeys) . "\n";
} else {
    echo "FAIL pași lipsă: " . implode(', ', $missingSteps) . "\n";
    $failures++;
}

$feedStep = null;
foreach ($built['steps'] ?? [] as $step) {
    if (($step['key'] ?? '') === 'feed') {
        $feedStep = $step;
        break;
    }
}
if ($feedStep !== null && (float) ($feedStep['amount_raw'] ?? 0) > 0) {
    echo "OK  preț feed calculat: " . ($feedStep['amount'] ?? '?') . " lei\n";
} else {
    echo "FAIL preț feed invalid\n";
    $failures++;
}

$uiPath = $admin . '/Templates/admin/pages/adaoscomercial/adaoscomercial.php';
$tabPath = $admin . '/Templates/admin/pages/adaoscomercial/_price-formation-log-tab.php';
$uiHtml = (string) file_get_contents($uiPath);
$tabHtml = is_file($tabPath) ? (string) file_get_contents($tabPath) : '';
$uiNeedles = [
    'data-adaos-page-tab="price-log"' => $uiHtml,
    'data-adaos-page-pane="price-log"' => $uiHtml,
    'Log formare preț' => $uiHtml,
    '_price-formation-log-tab.php' => $uiHtml,
    'id="price-formation-log-panel"' => $tabHtml,
    'id="pflProductQuery"' => $tabHtml,
    'id="pflProductSearch"' => $tabHtml,
    "type_product: 'price_formation_trace'" => $tabHtml,
];

$importReviewPath = $admin . '/Templates/admin/pages/import/importreview.php';
$importHtml = is_file($importReviewPath) ? (string) file_get_contents($importReviewPath) : '';
$importNeedles = [
    'tab=price-log',
    'import-price-formation-log-link',
    'Log preț',
];
foreach ($importNeedles as $needle) {
    if (!str_contains($importHtml, $needle)) {
        echo "FAIL importreview lipsește: {$needle}\n";
        $failures++;
    } else {
        echo "OK  importreview conține: {$needle}\n";
    }
}
foreach ($uiNeedles as $needle => $haystack) {
    if (!str_contains($haystack, $needle)) {
        echo "FAIL UI lipsește: {$needle}\n";
        $failures++;
    } else {
        echo "OK  UI conține: {$needle}\n";
    }
}

if ($failures === 0) {
    echo "\nPRICE_FORMATION_TRACE_OK\n";
    exit(0);
}

echo "\nPRICE_FORMATION_TRACE_FAILED ({$failures})\n";
exit(1);
