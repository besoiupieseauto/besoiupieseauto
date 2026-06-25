<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/Controllers/Produse/import_lib.php';
require_once __DIR__ . '/../src/Controllers/Produse/import_supplier_lib.php';
require_once __DIR__ . '/../src/Controllers/Produse/import_supplier_stock_zero_lib.php';

$failures = 0;

function assert_true(bool $cond, string $msg): void
{
    global $failures;
    if (!$cond) {
        echo "FAIL: {$msg}\n";
        $failures++;
        return;
    }
    echo "OK: {$msg}\n";
}

import_supplier_scan_rules_reset_cache();

$defaults = import_supplier_scan_rules_defaults();
assert_true($defaults['stock_zero_mode'] === 'full', 'default stock_zero_mode=full');

$rowZero = ['stoc' => '0'];
$rowPositive = ['stoc' => '5'];
$rowUnavailable = ['stoc' => 'indisponibil'];

assert_true(import_supplier_row_is_unavailable($rowUnavailable), 'detect unavailable row');
assert_true(!import_supplier_row_is_unavailable($rowZero), 'zero qty is not unavailable text');

$rulesHide = ['stock_zero_mode' => 'hide', 'scan_include_zero_stock' => 1, 'scan_skip_unavailable' => 0];
$rulesSkipZero = ['stock_zero_mode' => 'full', 'scan_include_zero_stock' => 0, 'scan_skip_unavailable' => 0];
$rulesSkipUnavailable = ['stock_zero_mode' => 'full', 'scan_include_zero_stock' => 1, 'scan_skip_unavailable' => 1];

$GLOBALS['__import_supplier_scan_rules_index'] = [
    'AUTONET' => $rulesHide,
    'ELIT' => $rulesSkipZero,
    'MATEROM' => $rulesSkipUnavailable,
];

assert_true(!import_supplier_row_passes_supplier_scan_rules($rowZero, 'AUTONET'), 'hide mode skips stock 0');
assert_true(import_supplier_row_passes_supplier_scan_rules($rowPositive, 'AUTONET'), 'hide mode keeps positive stock');
assert_true(!import_supplier_row_passes_supplier_scan_rules($rowZero, 'ELIT'), 'scan_include_zero_stock=0 skips zero');
assert_true(!import_supplier_row_passes_supplier_scan_rules($rowUnavailable, 'MATEROM'), 'skip unavailable when enabled');

$entry = [
    'supplier' => 'AUTONET',
    'stock_raw' => '0',
    'stock_status' => 'zero',
    'stock_zero_mode' => 'out_of_stock',
];
$product = ['pStock' => '1'];
import_supplier_apply_stock_zero_to_product($product, $entry);
assert_true(($product['pStock'] ?? '') === '0', 'out_of_stock sets pStock=0');

$entry['stock_zero_mode'] = 'full';
$product = ['pStock' => '1'];
import_supplier_apply_stock_zero_to_product($product, $entry);
assert_true(($product['pStock'] ?? '') === '1', 'full mode keeps pStock=1 on zero feed');

exit($failures > 0 ? 1 : 0);
