<?php
declare(strict_types=1);

/**
 * tm_107 — Export produse validate din coada import către BaseLinker via API.
 */

$root = dirname(__DIR__, 2);
$failures = 0;

$importReviewPath = $root . '/admin/Templates/admin/pages/import/importreview.php';
$html = is_file($importReviewPath) ? (string) file_get_contents($importReviewPath) : '';
$needles = [
    'exportBaselinkerBtn',
    'Exportă produse spre BaseLinker',
    'export_baselinker',
    'exportBaselinkerProducts()',
];
foreach ($needles as $needle) {
    if (!str_contains($html, $needle)) {
        echo "FAIL importreview lipsește: {$needle}\n";
        ++$failures;
    } else {
        echo "OK  importreview conține: {$needle}\n";
    }
}

$actionPath = $root . '/admin/src/Controllers/Produse/importproduse_action.php';
$actionSrc = is_file($actionPath) ? (string) file_get_contents($actionPath) : '';
if (!str_contains($actionSrc, "'export_baselinker'")) {
    echo "FAIL importproduse_action fără acțiune export_baselinker\n";
    ++$failures;
} else {
    echo "OK  importproduse_action acțiune export_baselinker\n";
}

$exportLibPath = $root . '/system/import-queue-baselinker-export.php';
if (!is_file($exportLibPath)) {
    echo "FAIL system/import-queue-baselinker-export.php lipsește\n";
    ++$failures;
} else {
    echo "OK  system/import-queue-baselinker-export.php există\n";
    $exportSrc = (string) file_get_contents($exportLibPath);
    foreach (['import_queue_baselinker_export', 'import_queue_baselinker_prepare_row', 'syncImportQueueRowsToBaseLinker'] as $fn) {
        if (!str_contains($exportSrc, $fn)) {
            echo "FAIL import-queue-baselinker-export.php lipsește: {$fn}\n";
            ++$failures;
        } else {
            echo "OK  import-queue-baselinker-export.php conține: {$fn}\n";
        }
    }
}

$servicePath = $root . '/admin/src/Controllers/Marketplace/MarketplaceService.php';
$serviceSrc = is_file($servicePath) ? (string) file_get_contents($servicePath) : '';
if (!str_contains($serviceSrc, 'function syncImportQueueRowsToBaseLinker')) {
    echo "FAIL MarketplaceService fără syncImportQueueRowsToBaseLinker\n";
    ++$failures;
} else {
    echo "OK  MarketplaceService syncImportQueueRowsToBaseLinker\n";
}

if ($failures === 0) {
    echo "\nTM107_IMPORT_QUEUE_BASELINKER_EXPORT_OK\n";
    exit(0);
}

echo "\nTM107_IMPORT_QUEUE_BASELINKER_EXPORT_FAILED ({$failures})\n";
exit(1);
