<?php

declare(strict_types=1);

/**
 * Verifică selectori modal adaos selectiv (tm_022) — admin produse + backend.
 * Usage: php admin/tools/verify_produse_markup_modal.php
 */

require __DIR__ . '/php_cli.php';

$admin = dirname(__DIR__);
$failures = 0;

echo "=== verify_produse_markup_modal ===\n\n";

$phpBin = admin_php_cli_binary();
$lintTargets = [
    'src/Controllers/AdaosComercial/AdaosComercialService.php',
    'src/Controllers/Produse/crudu.php',
    'Templates/admin/pages/produse/produse.php',
];

foreach ($lintTargets as $rel) {
    $path = $admin . '/' . str_replace('/', DIRECTORY_SEPARATOR, $rel);
    if (!is_file($path)) {
        echo "FAIL missing: {$rel}\n";
        $failures++;
        continue;
    }
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

$produsePath = $admin . '/Templates/admin/pages/produse/produse.php';
$produseHtml = is_file($produsePath) ? (string) file_get_contents($produsePath) : '';
$uiSelectors = [
    'id="markupSelectModal"',
    'id="markupRuleSelect"',
    'id="markupModalProductList"',
    'id="confirmMarkupSelectModal"',
    'id="applySelectedMarkupBtn"',
    'id="addProductModal"',
    'id="openAddProduct"',
    'id="addProductFrame"',
    'open-markup-modal',
    'Aplică adaos selectiv',
    'openProductModal',
    "type_product: 'apply_markup_rule'",
    'applyMarkupRule(',
    'openMarkupSelectModal(',
];
foreach ($uiSelectors as $needle) {
    if (!str_contains($produseHtml, $needle)) {
        echo "FAIL selector lipsește din produse.php: {$needle}\n";
        $failures++;
    } else {
        echo "OK  selector {$needle} in produse.php\n";
    }
}

$servicePath = $admin . '/src/Controllers/AdaosComercial/AdaosComercialService.php';
$serviceSrc = is_file($servicePath) ? (string) file_get_contents($servicePath) : '';
if (!str_contains($serviceSrc, 'function applyRuleToProductIds')) {
    echo "FAIL AdaosComercialService::applyRuleToProductIds lipsește\n";
    $failures++;
} else {
    echo "OK  AdaosComercialService::applyRuleToProductIds\n";
}

$cruduPath = $admin . '/src/Controllers/Produse/crudu.php';
$cruduSrc = is_file($cruduPath) ? (string) file_get_contents($cruduPath) : '';
if (!str_contains($cruduSrc, "apply_markup_rule")) {
    echo "FAIL crudu.php acțiune apply_markup_rule lipsește\n";
    $failures++;
} else {
    echo "OK  crudu.php apply_markup_rule\n";
}

$cssPath = $admin . '/public/assets/css/admin-pages.css';
$cssSrc = is_file($cssPath) ? (string) file_get_contents($cssPath) : '';
$cssSelectors = [
    '#markupSelectModal.is-open',
    '#addProductModal.is-open',
    'produse-list-page',
    'pointer-events: auto !important',
    'z-index: 100000',
];
foreach ($cssSelectors as $needle) {
    if (!str_contains($cssSrc, $needle)) {
        echo "FAIL CSS lipsește din admin-pages.css: {$needle}\n";
        $failures++;
    } else {
        echo "OK  CSS {$needle} in admin-pages.css\n";
    }
}

if (!str_contains($produseHtml, "setOverlayModalOpen") || !str_contains($produseHtml, "is-open")) {
    echo "FAIL produse.php: toggle is-open pentru modal lipsește\n";
    $failures++;
} else {
    echo "OK  produse.php is-open modal toggle\n";
}

if ($failures > 0) {
    echo "\nFAILED: {$failures}\n";
    exit(1);
}

echo "\nPRODUSE MARKUP MODAL OK\n";
exit(0);
