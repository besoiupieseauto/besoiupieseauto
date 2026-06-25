<?php
declare(strict_types=1);

/**
 * tm_102 — dropdown categorie + subcategorie în modal editare coadă import.
 */

$root = dirname(__DIR__, 2);
$failures = 0;

$importReviewPath = $root . '/admin/Templates/admin/pages/import/importreview.php';
$html = is_file($importReviewPath) ? (string) file_get_contents($importReviewPath) : '';

$needles = [
    'id="importQueueEditCategory"',
    'id="importQueueEditSubcategory"',
    'id="importQueueSubcategoriesByCategory"',
    'importQueueSubcategoriesMap',
    'importQueueEditPopulateSubcategories',
    'importQueueEditEnsureCategoryOption',
    'CategoriiService',
    '<select id="importQueueEditCategory"',
    '<select id="importQueueEditSubcategory"',
    'data-queue-field="category"',
    'data-queue-field="subcategory"',
    '— Alege categorie —',
    '— Alege subcategorie —',
];
foreach ($needles as $needle) {
    if (!str_contains($html, $needle)) {
        echo "FAIL importreview lipseste: {$needle}\n";
        ++$failures;
    } else {
        echo "OK  importreview contine: {$needle}\n";
    }
}

if (preg_match('/<input[^>]+id="importQueueEditCategory"/', $html) === 1) {
    echo "FAIL importQueueEditCategory inca este input text\n";
    ++$failures;
} else {
    echo "OK  importQueueEditCategory nu mai este input text\n";
}

if (preg_match('/<input[^>]+id="importQueueEditSubcategory"/', $html) === 1) {
    echo "FAIL importQueueEditSubcategory inca este input text\n";
    ++$failures;
} else {
    echo "OK  importQueueEditSubcategory nu mai este input text\n";
}

if ($failures === 0) {
    echo "\nTM102_IMPORT_QUEUE_CATEGORY_DROPDOWN_OK\n";
    exit(0);
}

echo "\nTM102_IMPORT_QUEUE_CATEGORY_DROPDOWN_FAILED ({$failures})\n";
exit(1);
