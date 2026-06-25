<?php
declare(strict_types=1);

/**
 * tm_097 — Export CSV unificat din coada import (produse validate).
 */

$root = dirname(__DIR__, 2);
$failures = 0;

$importReviewPath = $root . '/admin/Templates/admin/pages/import/importreview.php';
$html = is_file($importReviewPath) ? (string) file_get_contents($importReviewPath) : '';
$needles = [
    'exportValidatedCsv',
    'Export CSV validat',
    'export_validated_csv',
    'exportValidatedCsv()',
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
if (!str_contains($actionSrc, "'export_validated_csv'")) {
    echo "FAIL importproduse_action fără acțiune export_validated_csv\n";
    ++$failures;
} else {
    echo "OK  importproduse_action acțiune export_validated_csv\n";
}

$exportLibPath = $root . '/system/import-queue-export.php';
if (!is_file($exportLibPath)) {
    echo "FAIL system/import-queue-export.php lipsește\n";
    ++$failures;
} else {
    echo "OK  system/import-queue-export.php există\n";
    $exportSrc = (string) file_get_contents($exportLibPath);
    foreach (['import_queue_export_fetch_validated_rows', 'import_queue_export_csv_content', 'besoiu_import_row_has_critical_gaps'] as $fn) {
        if (!str_contains($exportSrc, $fn)) {
            echo "FAIL import-queue-export.php lipsește: {$fn}\n";
            ++$failures;
        } else {
            echo "OK  import-queue-export.php conține: {$fn}\n";
        }
    }
}

if ($failures === 0) {
    echo "\nTM097_IMPORT_QUEUE_EXPORT_CSV_OK\n";
    exit(0);
}

echo "\nTM097_IMPORT_QUEUE_EXPORT_CSV_FAILED ({$failures})\n";
exit(1);
