<?php
declare(strict_types=1);

/**
 * tm_101 — modal editare produs din coada import (fără navigare).
 */

$root = dirname(__DIR__, 2);
$failures = 0;

$importReviewPath = $root . '/admin/Templates/admin/pages/import/importreview.php';
$html = is_file($importReviewPath) ? (string) file_get_contents($importReviewPath) : '';
$needles = [
    'importQueueEditModal',
    'import-row--queue-edit',
    'data-queue-edit',
    'importQueueEditReprocess',
    'Re-proceseaza',
    'importQueueEditSyncTecdoc',
    'Sync TecDoc',
    'importQueueEditName',
    'importQueueEditBrand',
    'importQueueEditMarca',
    'importQueueEditModel',
    'importQueueEditMotorizare',
    'importQueueEditCategory',
    'importQueueEditSubcategory',
    'importQueueEditPrice',
    'importQueueEditBasePrice',
    'importQueueEditStock',
    'importQueueEditNote',
    'importQueueEditOem',
    'importQueueEditCompatibilitati',
    'importQueueEditImage',
    'import-queue-edit-section',
    'pBrand',
    'pMotorizare',
    'pBasePrice',
    'pStock',
    'pNote',
    'pOem',
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
    "'queue_row_save'",
    "'reprocess_one'",
    "'sync_tecdoc_one'",
    'import_action_reprocess_queue_row',
    'import_action_sync_tecdoc_queue_row',
    "'pBrand'",
    "'pStock'",
    "'pNote'",
    "'pOem'",
    'import_action_queue_row_input_string',
];
foreach ($actionNeedles as $needle) {
    if (!str_contains($actionSrc, $needle)) {
        echo "FAIL importproduse_action lipseste: {$needle}\n";
        ++$failures;
    } else {
        echo "OK  importproduse_action contine: {$needle}\n";
    }
}

$importProdusePath = $root . '/admin/src/Controllers/Produse/importproduse.php';
$importProduseSrc = is_file($importProdusePath) ? (string) file_get_contents($importProdusePath) : '';
if (!str_contains($importProduseSrc, "'pStock = ?'")) {
    echo "FAIL importproduse lipseste: pStock in import_sync_prepared_row\n";
    ++$failures;
} else {
    echo "OK  importproduse contine: pStock in import_sync_prepared_row\n";
}

if ($failures === 0) {
    echo "\nTM101_IMPORT_QUEUE_EDIT_MODAL_OK\n";
    exit(0);
}

echo "\nTM101_IMPORT_QUEUE_EDIT_MODAL_FAILED ({$failures})\n";
exit(1);
