<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
require_once $root . '/app/Import/MatchingPro/api/bootstrap.php';

$supplier = 'autototal';
$file = 'test_checkpoint.csv';

import_reset_scan_checkpoint($supplier, $file, 'test_reset');
import_record_scan_checkpoint($supplier, $file, 0, 20, 20, 20, 'continue');
$ck1 = import_get_scan_checkpoint($supplier, $file);
import_record_scan_checkpoint($supplier, $file, 20, 40, 20, 20, 'continue');
$ck2 = import_get_scan_checkpoint($supplier, $file);

echo 'after pass1 offset=' . ($ck1['offset'] ?? '?') . PHP_EOL;
echo 'after pass2 offset=' . ($ck2['offset'] ?? '?') . PHP_EOL;

import_reset_scan_checkpoint($supplier, $file, 'test_cleanup');
$ck3 = import_get_scan_checkpoint($supplier, $file);
echo 'after reset offset=' . ($ck3['offset'] ?? '?') . PHP_EOL;

$ok = ((int) ($ck1['offset'] ?? -1) === 20)
    && ((int) ($ck2['offset'] ?? -1) === 40)
    && ((int) ($ck3['offset'] ?? -1) === 0);

exit($ok ? 0 : 1);
