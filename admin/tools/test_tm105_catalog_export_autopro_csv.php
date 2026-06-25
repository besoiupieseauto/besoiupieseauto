<?php
declare(strict_types=1);

/**
 * tm_105 — Buton export catalog CSV format Piese Autopro (admin/export).
 */

$root = dirname(__DIR__, 2);
$failures = 0;

$exportPagePath = $root . '/admin/Templates/admin/pages/export/export.php';
$html = is_file($exportPagePath) ? (string) file_get_contents($exportPagePath) : '';
$needles = [
    'exportCatalogAutoproBtn',
    'Generare fișier export produse Piese Autopro',
    'export_catalog_autopro_csv',
    'exportCatalogAutoproCsv',
];
foreach ($needles as $needle) {
    if (!str_contains($html, $needle)) {
        echo "FAIL export.php lipsește: {$needle}\n";
        ++$failures;
    } else {
        echo "OK  export.php conține: {$needle}\n";
    }
}

$endpointPath = $root . '/admin/public/api/export_action_endpoint.php';
if (!is_file($endpointPath)) {
    echo "FAIL export_action_endpoint.php lipsește\n";
    ++$failures;
} else {
    echo "OK  export_action_endpoint.php există\n";
}

$actionPath = $root . '/admin/src/Controllers/Export/exportproduse_action.php';
$actionSrc = is_file($actionPath) ? (string) file_get_contents($actionPath) : '';
if (!str_contains($actionSrc, "'export_catalog_autopro_csv'")) {
    echo "FAIL exportproduse_action fără acțiune export_catalog_autopro_csv\n";
    ++$failures;
} else {
    echo "OK  exportproduse_action acțiune export_catalog_autopro_csv\n";
}

$catalogLibPath = $root . '/system/catalog-export-autopro.php';
if (!is_file($catalogLibPath)) {
    echo "FAIL system/catalog-export-autopro.php lipsește\n";
    ++$failures;
} else {
    require_once $root . '/system/import-queue-export.php';
    require_once $catalogLibPath;
    echo "OK  system/catalog-export-autopro.php există\n";

    $expectedHeaders = ['ID', 'titlu', 'categorie', 'descriere', 'monedă', 'preț', 'cantitate'];
    if (import_queue_export_autopro_csv_headers() !== $expectedHeaders) {
        echo "FAIL antete Autopro neașteptate\n";
        ++$failures;
    } else {
        echo "OK  antete Autopro exacte (7 coloane)\n";
    }

    $sampleRow = [
        'id' => 99,
        'pCode' => 'PA-777',
        'pName' => 'Disc frana',
        'pCategory' => 'Frane',
        'pSubcategory' => 'Discuri',
        'pNoteWebsite' => '<p>Compatibil VW</p>',
        'pPrice' => '120.00',
        'pStock' => '5',
    ];
    $values = import_queue_export_autopro_row_values($sampleRow);
    if ($values !== ['PA-777', 'Disc frana', 'Frane>Discuri', 'Compatibil VW', 'RON', '120.00', '5']) {
        echo "FAIL mapare rând catalog Autopro\n";
        ++$failures;
    } else {
        echo "OK  mapare rând catalog Autopro\n";
    }

    $filename = catalog_export_autopro_filename();
    if (!str_starts_with($filename, 'produse_piese_autopro_') || !str_ends_with($filename, '.csv')) {
        echo "FAIL filename catalog Autopro: {$filename}\n";
        ++$failures;
    } else {
        echo "OK  filename catalog Autopro\n";
    }
}

$resolverPath = $root . '/admin/src/Core/AdminPageResolver.php';
$resolverSrc = is_file($resolverPath) ? (string) file_get_contents($resolverPath) : '';
if (!str_contains($resolverSrc, "'export' => '/admin/Templates/admin/pages/export/export.php'")) {
    echo "FAIL AdminPageResolver fără ruta export\n";
    ++$failures;
} else {
    echo "OK  AdminPageResolver ruta export\n";
}

if ($failures === 0) {
    echo "\nTM105_CATALOG_EXPORT_AUTOPRO_CSV_OK\n";
    exit(0);
}

echo "\nTM105_CATALOG_EXPORT_AUTOPRO_CSV_FAILED ({$failures})\n";
exit(1);
