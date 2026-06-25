<?php
declare(strict_types=1);

ini_set('display_errors', '1');
error_reporting(E_ALL);

require_once dirname(__DIR__) . '/src/Controllers/Produse/import_lib.php';
require_once dirname(__DIR__) . '/src/Controllers/Produse/import_supplier_lib.php';
require_once dirname(__DIR__) . '/src/Controllers/Produse/import_tecdoc_lib.php';

$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tecdoc_bundle_test_' . getmypid() . '.csv';
$csv = <<<CSV
art code 1;art brand;art name;parts info;art cross;car brand;car model;car of year
K015578XS;GATES;Set ambreiaj;info;OEM123;AUDI;A4;2005
K015578XS;GATES;Set ambreiaj;info;OEM123;VW;PASSAT;2008
K015578XS;GATES;Set ambreiaj;info;OEM123;SKODA;OCTAVIA;2010
OTHER999;BOSCH;Filtru;info;;BMW;X5;2012
CSV;
file_put_contents($tmp, $csv);

$files = [['path' => $tmp, 'name' => 'UTF8-test-GATES-ro.csv']];
$entries = [
    ['code' => 'K015578XS', 'brand' => 'GATES'],
    ['code' => 'OTHER999', 'brand' => 'BOSCH'],
];

$bundle = import_tecdoc_build_catalog_bundle($files, $entries, 2, true, false);
$lookupHits = count($bundle['lookup']);
$rowCounts = array_map('count', $bundle['row_groups']);

echo "lookup_keys={$lookupHits}\n";
echo 'row_groups=' . json_encode($rowCounts) . "\n";

@unlink($tmp);

if ($lookupHits < 2 || ($rowCounts['K015578XS'] ?? 0) !== 2) {
    fwrite(STDERR, "FAIL\n");
    exit(1);
}

echo "OK\n";
