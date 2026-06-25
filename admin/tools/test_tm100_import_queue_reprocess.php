<?php
declare(strict_types=1);

/**
 * tm_100 — buton Re-procesează pe produs incomplet din coada import.
 */

$root = dirname(__DIR__, 2);
$failures = 0;

$importReviewPath = $root . '/admin/Templates/admin/pages/import/importreview.php';
$html = is_file($importReviewPath) ? (string) file_get_contents($importReviewPath) : '';
$needles = [
    'review_row_needs_reprocess',
    'data-besoiu-action="reprocess-one"',
    'reprocess-one',
    'Re-procesează',
    'Re-triggerează TecDoc/RapidAPI',
    "'reprocess_one'",
    'importQueueEditReprocess',
];
foreach ($needles as $needle) {
    if (!str_contains($html, $needle)) {
        echo "FAIL importreview lipseste: {$needle}\n";
        ++$failures;
    } else {
        echo "OK  importreview contine: {$needle}\n";
    }
}

$actionPath = $root . '/admin/src/Controllers/Produse/importproduse_action.php';
$actionSrc = is_file($actionPath) ? (string) file_get_contents($actionPath) : '';
$actionNeedles = [
    "'reprocess_one'",
    'import_action_reprocess_queue_row',
    'import_enrich_row_before_live_publish',
];
foreach ($actionNeedles as $needle) {
    if (!str_contains($actionSrc, $needle)) {
        echo "FAIL importproduse_action lipseste: {$needle}\n";
        ++$failures;
    } else {
        echo "OK  importproduse_action contine: {$needle}\n";
    }
}

if ($failures === 0) {
    echo "\nTM100_IMPORT_QUEUE_REPROCESS_OK\n";
    exit(0);
}

echo "\nTM100_IMPORT_QUEUE_REPROCESS_FAILED ({$failures})\n";
exit(1);
