<?php
declare(strict_types=1);

define('IMPORT_PRODUCE_SKIP_HTTP', true);

ini_set('display_errors', '1');
error_reporting(E_ALL);
ini_set('memory_limit', '2G');
set_time_limit(600);

require_once dirname(__DIR__) . '/src/Controllers/Produse/importproduse.php';
echo "loaded importproduse\n";

$importsDir = dirname(__DIR__) . '/storage/imports';
$metas = [];
foreach (glob($importsDir . '/*.part.json') ?: [] as $metaPath) {
    $meta = json_decode((string)file_get_contents($metaPath), true);
    if (!is_array($meta)) {
        continue;
    }
    $fileId = basename($metaPath, '.part.json');
    $metas[] = [
        'file_id' => $fileId,
        'original_name' => (string)($meta['original_name'] ?? ''),
    ];
}

echo 'Files: ' . count($metas) . PHP_EOL;
$start = microtime(true);

try {
    echo 'Step 1: price index...' . PHP_EOL;
    $supplierFiles = [];
    $tecdocFiles = [];
    foreach ($metas as $meta) {
        $path = import_temp_file_path((string)$meta['file_id']);
        $kind = import_classify_file($path, (string)$meta['original_name']);
        $entry = ['path' => $path, 'name' => (string)$meta['original_name']];
        if ($kind === 'tecdoc') {
            $tecdocFiles[] = $entry;
        } elseif (str_starts_with($kind, 'supplier:')) {
            $supplierFiles[] = $entry;
        }
    }
    $t0 = microtime(true);
    $priceIndex = import_build_price_index($supplierFiles);
    echo 'Price index: ' . count($priceIndex) . ' in ' . round(microtime(true) - $t0, 2) . 's mem=' . round(memory_get_peak_usage(true)/1024/1024, 1) . 'MB' . PHP_EOL;

    echo 'Step 2: preview first tecdoc only...' . PHP_EOL;
    $t1 = microtime(true);
    $preview = preview_products_from_file($tecdocFiles[0]['path'], $tecdocFiles[0]['name'], 50, $priceIndex, null);
    echo 'Preview: ' . count($preview['products']) . ' products, rows=' . $preview['total_rows'] . ' in ' . round(microtime(true) - $t1, 2) . 's mem=' . round(memory_get_peak_usage(true)/1024/1024, 1) . 'MB' . PHP_EOL;

    echo 'Step 3: skipped full preview (needs DB autoload)' . PHP_EOL;
    exit(0);

    $result = import_preview_uploaded_files($metas, 100, []);
    echo 'OK in ' . round(microtime(true) - $start, 2) . 's' . PHP_EOL;
    echo 'Products: ' . count($result['products']) . PHP_EOL;
    echo 'Mode: ' . ($result['import_mode'] ?? '?') . PHP_EOL;
    echo 'Total rows: ' . ($result['total_rows'] ?? 0) . PHP_EOL;
} catch (Throwable $e) {
    echo 'ERROR: ' . $e->getMessage() . PHP_EOL;
    echo $e->getTraceAsString() . PHP_EOL;
    exit(1);
}
