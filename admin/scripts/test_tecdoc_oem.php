<?php
define('IMPORT_PRODUCE_SKIP_HTTP', true);
require dirname(__DIR__) . '/src/Controllers/Produse/importproduse.php';

$catalogEntries = [
    ['code' => '012800025mm', 'brand' => 'GLYCO', 'name' => 'test', 'net_price' => 10, 'supplier' => 'TEST'],
];
$tecdocFiles = [['path' => import_temp_file_path('f_1779451183638_zqs4zocg'), 'name' => 'GLYCO-ro.csv']];
$lookup = import_tecdoc_build_lookup_for_catalog($tecdocFiles, $catalogEntries);
echo 'lookup hits: ' . count($lookup) . PHP_EOL;
if ($lookup) {
    print_r(array_values($lookup)[0]);
}

foreach ($catalogEntries as $entry) {
    $rec = import_tecdoc_lookup_catalog_entry($lookup, $entry);
    $product = import_build_product_from_supplier_tecdoc($entry, null, false, $rec);
    echo ($entry['code'] ?? '') . ' | OEM: ' . ($product['pOem'] ?? '') . ' | TecDoc: ' . ($rec ? 'yes' : 'no') . PHP_EOL;
}
