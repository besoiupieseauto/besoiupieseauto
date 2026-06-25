<?php
define('IMPORT_PRODUCE_SKIP_HTTP', true);
require dirname(__DIR__) . '/src/Controllers/Produse/importproduse.php';

$files = [
    ['path' => import_temp_file_path('f_1779449840544_bw41f026'), 'name' => 'Lista pret Elit 16.01.2026.csv'],
    ['path' => import_temp_file_path('f_1779451269414_sa1196o9'), 'name' => 'Lista pret Autonet 27.01.2026.csv'],
];

foreach ($files as $f) {
    echo $f['name'] . ' => ' . import_classify_file($f['path'], $f['name']) . PHP_EOL;
}

$catalog = import_build_supplier_catalog($files, 10, '');
echo 'catalog entries: ' . count($catalog['entries']) . ' total=' . $catalog['total_unique'] . PHP_EOL;
if ($catalog['entries']) {
    print_r($catalog['entries'][0]);
}
