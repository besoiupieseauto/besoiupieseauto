<?php
declare(strict_types=1);

ini_set('display_errors', '1');
error_reporting(E_ALL);
ini_set('memory_limit', '2G');
set_time_limit(600);

register_shutdown_function(static function (): void {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        fwrite(STDERR, "FATAL: {$err['message']} in {$err['file']}:{$err['line']}\n");
    }
});

define('IMPORT_PRODUCE_SKIP_HTTP', true);
require_once dirname(__DIR__) . '/src/Controllers/Produse/importproduse.php';

echo "boot ok\n";

$supplierFiles = [
    ['path' => import_temp_file_path('f_1779449840544_bw41f026'), 'name' => 'Elit.csv'],
    ['path' => import_temp_file_path('f_1779451269414_sa1196o9'), 'name' => 'Autonet.csv'],
];

echo "building price index...\n";
$t0 = microtime(true);
$priceIndex = import_build_price_index($supplierFiles);
echo 'index=' . import_price_index_size($priceIndex) . ' time=' . round(microtime(true)-$t0,1) . 's mem=' . round(memory_get_peak_usage(true)/1024/1024,1) . "MB\n";

$path = import_temp_file_path('f_1779451183638_zqs4zocg');
echo "preview glyco...\n";
$t1 = microtime(true);
$preview = preview_products_from_file($path, 'GLYCO.csv', 20, $priceIndex, null);
echo 'products=' . count($preview['products']) . ' rows=' . $preview['total_rows'] . ' time=' . round(microtime(true)-$t1,1) . 's mem=' . round(memory_get_peak_usage(true)/1024/1024,1) . "MB\n";
