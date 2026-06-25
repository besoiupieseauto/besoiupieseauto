<?php
declare(strict_types=1);

require 'F:/laragon/www/besoiupieseauto.ro/admin/tools/php_cli.php';

$admin = 'F:/laragon/www/besoiupieseauto.ro/admin';
$failures = 0;
$phpBin = admin_php_cli_binary();

echo "=== verify_tm068_brand_filter ===\n\n";

$lintTargets = [
    'src/Controllers/Produse/ProductFacetsService.php',
    'Templates/admin/pages/produse/produse.php',
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

$servicePath = $admin . '/src/Controllers/Produse/ProductFacetsService.php';
$serviceSrc = is_file($servicePath) ? (string) file_get_contents($servicePath) : '';
if (!str_contains($serviceSrc, "'brands' => \$this->getBrands()")) {
    echo "FAIL ProductFacetsService::getListFilters nu expune brands\n";
    $failures++;
} else {
    echo "OK  ProductFacetsService expune brands in getListFilters\n";
}

$produsePath = $admin . '/Templates/admin/pages/produse/produse.php';
$produseHtml = is_file($produsePath) ? (string) file_get_contents($produsePath) : '';
$uiSelectors = [
    'id="brandFilter"',
    'id="sortFilter"',
    'Toate brandurile',
    'Brand piesă A–Z',
    'Brand piesă Z–A',
    'data-brand=',
    'matchesBrand',
    'function applySort',
    'productFacets[\'brands\']',
];
foreach ($uiSelectors as $needle) {
    if (!str_contains($produseHtml, $needle)) {
        echo "FAIL selector lipsește din produse.php: {$needle}\n";
        $failures++;
    } else {
        echo "OK  selector {$needle} in produse.php\n";
    }
}

if ($failures > 0) {
    echo "\nFAILED: {$failures}\n";
    exit(1);
}

echo "\nTM068 BRAND FILTER OK\n";
exit(0);
