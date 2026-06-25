<?php

declare(strict_types=1);

/**
 * Teste algoritm normalizare cod produs furnizori (besoiu_normalize_product_code).
 * Usage: php tools/test_product_code_normalize.php
 */

require __DIR__ . '/php_cli.php';

$root = dirname(__DIR__, 2);
require_once $root . '/system/product-code-normalize.php';

$failed = 0;

$cases = [
    ['0 986 424 098', '0986424098'],
    ['bosch-123', 'BOSCH123'],
    ['ABC/123.45', 'ABC12345'],
    ['  test-code_  ', 'TESTCODE'],
    ['', ''],
    ['***', ''],
    ['W 712/75', 'W71275'],
    ['0986.424.098', '0986424098'],
];

foreach ($cases as [$input, $expected]) {
    $actual = besoiu_normalize_product_code($input);
    if ($actual !== $expected) {
        fwrite(STDERR, "FAIL normalize: input={$input} expected={$expected} got={$actual}\n");
        $failed++;
    }
}

$sqlExpr = besoiu_sql_normalized_pcode_expr('pCode');
if (!str_contains($sqlExpr, "REPLACE(TRIM(pCode), ' ', '')") || !str_contains($sqlExpr, "'_', ''")) {
    fwrite(STDERR, "FAIL sql expr: {$sqlExpr}\n");
    $failed++;
}

$variants = besoiu_product_code_search_variants('0 986 424 098');
if (!in_array('0986424098', $variants, true) || $variants === []) {
    fwrite(STDERR, 'FAIL variants: ' . json_encode($variants) . "\n");
    $failed++;
}

require_once $root . '/admin/src/Controllers/Produse/import_supplier_lib.php';
if (import_normalize_product_code('A-B C') !== 'ABC') {
    fwrite(STDERR, "FAIL import_normalize_product_code delegate\n");
    $failed++;
}

require_once $root . '/system/products_oem.php';
if (products_oem_normalize('x.y-z') !== 'XYZ') {
    fwrite(STDERR, "FAIL products_oem_normalize delegate\n");
    $failed++;
}

if ($failed > 0) {
    fwrite(STDERR, "FAILED: {$failed} assertion(s)\n");
    exit(1);
}

echo "test_product_code_normalize OK (" . count($cases) . " cases)\n";
exit(0);
