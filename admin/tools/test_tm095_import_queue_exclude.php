<?php
declare(strict_types=1);

/**
 * tm_095 — buton Exclude per rând în coada import (elimină draft înainte de publicare).
 */

$root = dirname(__DIR__, 2);
$failures = 0;

$importReviewPath = $root . '/admin/Templates/admin/pages/import/importreview.php';
$html = is_file($importReviewPath) ? (string) file_get_contents($importReviewPath) : '';
$needles = [
    'exclude-one',
    '>Exclude<',
    'exclude_one',
    'Excluzi acest produs din coada de import',
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
if (!str_contains($actionSrc, "'exclude_one'")) {
    echo "FAIL importproduse_action fără acțiune exclude_one\n";
    ++$failures;
} else {
    echo "OK  importproduse_action acțiune exclude_one\n";
}

if (!str_contains($actionSrc, "status='pending'")) {
    echo "FAIL importproduse_action exclude fără filtru pending\n";
    ++$failures;
} else {
    echo "OK  importproduse_action filtru status pending\n";
}

if ($failures === 0) {
    echo "\nTM095_IMPORT_QUEUE_EXCLUDE_OK\n";
    exit(0);
}

echo "\nTM095_IMPORT_QUEUE_EXCLUDE_FAILED ({$failures})\n";
exit(1);
