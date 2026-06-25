<?php

declare(strict_types=1);

/**
 * tm_052 — adaos comercial centralizat; furnizor = doar compensator pre-import (0/5/10%).
 * Usage: php admin/tools/verify_tm052_single_source_markup.php
 */

require __DIR__ . '/php_cli.php';

$admin = dirname(__DIR__);
require_once $admin . '/src/Controllers/Produse/import_supplier_lib.php';

$failures = 0;

echo "=== verify_tm052_single_source_markup ===\n\n";

$phpBin = admin_php_cli_binary();
$lintTargets = [
    'Templates/admin/pages/furnizori/profilefurnizori.php',
    'Templates/admin/pages/furnizori/furnizori.php',
    'Templates/admin/pages/adaoscomercial/adaoscomercial.php',
    'src/Controllers/Furnizori/Furnizori.php',
    'src/Controllers/Produse/import_supplier_lib.php',
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

$profilePath = $admin . '/Templates/admin/pages/furnizori/profilefurnizori.php';
$profileHtml = is_file($profilePath) ? (string) file_get_contents($profilePath) : '';

$profileSelectors = [
    'id="furnizor-feed-markup-select"',
    'Compensator pre-import',
    'id="furnizor-feed-markup-override"',
    'id="furnizor-price-markup-value"',
    '/admin/adaoscomercial',
    'Doar compensator pre-import aici',
];
foreach ($profileSelectors as $needle) {
    if (!str_contains($profileHtml, $needle)) {
        echo "FAIL selector lipsește din profilefurnizori.php: {$needle}\n";
        $failures++;
    } else {
        echo "OK  selector {$needle} in profilefurnizori.php\n";
    }
}

$forbiddenOnProfile = [
    'name="price_round_to"',
    'name="price_min_margin"',
    'name="adaos_template_rule_id"',
    'Adaos local',
];
foreach ($forbiddenOnProfile as $needle) {
    if (str_contains($profileHtml, $needle)) {
        echo "FAIL câmp vechi încă în profilefurnizori.php: {$needle}\n";
        $failures++;
    } else {
        echo "OK  absent {$needle} din profilefurnizori.php\n";
    }
}

$adaosPath = $admin . '/Templates/admin/pages/adaoscomercial/adaoscomercial.php';
$adaosHtml = is_file($adaosPath) ? (string) file_get_contents($adaosPath) : '';
$adaosSelectors = [
    'Formare preț în doi pași',
    'id="global_commercial_markup_percent"',
    'Singurul loc pentru adaosul comercial',
];
foreach ($adaosSelectors as $needle) {
    if (!str_contains($adaosHtml, $needle)) {
        echo "FAIL selector lipsește din adaoscomercial.php: {$needle}\n";
        $failures++;
    } else {
        echo "OK  selector {$needle} in adaoscomercial.php\n";
    }
}

$furnizoriListPath = $admin . '/Templates/admin/pages/furnizori/furnizori.php';
$furnizoriListHtml = is_file($furnizoriListPath) ? (string) file_get_contents($furnizoriListPath) : '';
if (!str_contains($furnizoriListHtml, 'Compensator pre-import')) {
    echo "FAIL furnizori.php: etichetă Compensator pre-import\n";
    $failures++;
} else {
    echo "OK  furnizori.php Compensator pre-import\n";
}

$furnizoriCtrl = (string) file_get_contents($admin . '/src/Controllers/Furnizori/Furnizori.php');
if (!str_contains($furnizoriCtrl, "'price_round_to', 'price_min_margin', 'adaos_template_rule_id'")) {
    echo "FAIL Furnizori.php: FORBIDDEN_INPUT_KEYS incomplet\n";
    $failures++;
} else {
    echo "OK  Furnizori.php blochează câmpuri comerciale vechi\n";
}

$presets = import_supplier_feed_markup_presets();
if ($presets !== [0.0, 5.0, 10.0]) {
    echo 'FAIL import_supplier_feed_markup_presets: așteptat [0,5,10], primit ' . json_encode($presets) . "\n";
    $failures++;
} else {
    echo "OK  import_supplier_feed_markup_presets [0,5,10]\n";
}

if ($failures > 0) {
    echo "\nFAILED: {$failures}\n";
    exit(1);
}

echo "\nTM_052 SINGLE SOURCE MARKUP OK\n";
exit(0);
