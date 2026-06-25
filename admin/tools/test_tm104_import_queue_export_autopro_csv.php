<?php
declare(strict_types=1);

/**
 * tm_104 — Export CSV format Piese Autopro din coada import validată.
 */

$root = dirname(__DIR__, 2);
$failures = 0;

$importReviewPath = $root . '/admin/Templates/admin/pages/import/importreview.php';
$html = is_file($importReviewPath) ? (string) file_get_contents($importReviewPath) : '';
$needles = [
    'exportAutoproCsv',
    'Export CSV Piese Autopro',
    'export_autopro_csv',
    'exportAutoproCsv()',
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
if (!str_contains($actionSrc, "'export_autopro_csv'")) {
    echo "FAIL importproduse_action fără acțiune export_autopro_csv\n";
    ++$failures;
} else {
    echo "OK  importproduse_action acțiune export_autopro_csv\n";
}

$exportLibPath = $root . '/system/import-queue-export.php';
if (!is_file($exportLibPath)) {
    echo "FAIL system/import-queue-export.php lipsește\n";
    ++$failures;
} else {
    require_once $exportLibPath;
    echo "OK  system/import-queue-export.php există\n";

    $expectedHeaders = ['ID', 'titlu', 'categorie', 'descriere', 'monedă', 'preț', 'cantitate'];
    $headers = import_queue_export_autopro_csv_headers();
    if ($headers !== $expectedHeaders) {
        echo "FAIL antete Autopro neașteptate: " . implode('|', $headers) . "\n";
        ++$failures;
    } else {
        echo "OK  antete Autopro exacte (7 coloane)\n";
    }

    $sampleRow = [
        'id' => 42,
        'pCode' => 'ABC-123',
        'pName' => 'Filtru ulei',
        'pCategory' => 'Filtre',
        'pSubcategory' => 'Filtre ulei',
        'pNote' => '<p>Descriere test</p>',
        'pNoteWebsite' => '',
        'pPrice' => '99.50',
        'pStock' => '3',
    ];
    $values = import_queue_export_autopro_row_values($sampleRow);
    if ($values !== ['ABC-123', 'Filtru ulei', 'Filtre>Filtre ulei', 'Descriere test', 'RON', '99.50', '3']) {
        echo "FAIL mapare rând Autopro: " . json_encode($values, JSON_UNESCAPED_UNICODE) . "\n";
        ++$failures;
    } else {
        echo "OK  mapare rând Autopro (ID, titlu, categorie, descriere, monedă, preț, cantitate)\n";
    }

    $csv = import_queue_export_autopro_csv_content([$sampleRow]);
    if (!str_contains($csv, 'monedă') || !str_contains($csv, 'ABC-123')) {
        echo "FAIL CSV Autopro invalid\n";
        ++$failures;
    } else {
        echo "OK  CSV Autopro generat\n";
    }
}

if ($failures === 0) {
    echo "\nTM104_IMPORT_QUEUE_EXPORT_AUTOPRO_CSV_OK\n";
    exit(0);
}

echo "\nTM104_IMPORT_QUEUE_EXPORT_AUTOPRO_CSV_FAILED ({$failures})\n";
exit(1);
