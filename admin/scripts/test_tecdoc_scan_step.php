<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Controllers/Produse/import_lib.php';
require_once dirname(__DIR__) . '/src/Controllers/Produse/import_supplier_lib.php';
require_once dirname(__DIR__) . '/src/Controllers/Produse/import_tecdoc_lib.php';

$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tecdoc_scan_step_' . getmypid() . '.csv';
$rows = ["art code 1;art brand;art name;parts info;art cross;car brand;car model;car of year\n"];
for ($i = 0; $i < 5000; $i++) {
    $rows[] = "CODE{$i};GATES;Piesa {$i};info;OEM{$i};AUDI;A4;2005\n";
}
$rows[] = "TARGET001;GATES;Target;info;OEMT;AUDI;A6;2010\n";
file_put_contents($tmp, implode('', $rows));

$files = [['path' => $tmp, 'name' => 'UTF8-test-GATES-ro.csv']];
$entries = [['code' => 'TARGET001', 'brand' => 'GATES']];
$state = import_tecdoc_create_scan_state($files, $entries, 5, false);

$steps = 0;
while (!$state['done'] && $steps < 200) {
    import_tecdoc_scan_step($state, 0.05, 500);
    $steps++;
}

$hit = import_tecdoc_lookup_catalog_entry($state['lookup'], $entries[0]);
@unlink($tmp);

if ($hit === null) {
    fwrite(STDERR, "FAIL: no lookup hit after {$steps} steps\n");
    exit(1);
}

echo "OK steps={$steps} rows={$state['rows_processed']}\n";
