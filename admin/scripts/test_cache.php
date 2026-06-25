<?php
define('IMPORT_PRODUCE_SKIP_HTTP', true);
require dirname(__DIR__) . '/src/Controllers/Produse/importproduse.php';

$files = [
    ['path' => import_temp_file_path('f_1779449840544_bw41f026'), 'name' => 'Elit.csv'],
    ['path' => import_temp_file_path('f_1779451269414_sa1196o9'), 'name' => 'Autonet.csv'],
];
$path = import_price_index_cache_path($files);
echo "cache path: $path\n";
echo 'exists: ' . (is_file($path) ? 'yes' : 'no') . ' size=' . (is_file($path) ? filesize($path) : 0) . "\n";
$cached = import_price_index_open_cached_store($path);
echo 'opened: ' . ($cached !== null ? 'yes' : 'no') . "\n";
if ($cached) {
    echo 'count=' . import_price_index_size($cached) . "\n";
}
