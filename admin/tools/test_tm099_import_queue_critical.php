<?php
declare(strict_types=1);

/**
 * tm_099 — verificare flag-uri date critice în coada import + blocare auto-publish.
 */

$root = dirname(__DIR__, 2);
$failures = 0;

require_once $root . '/system/import-queue-critical.php';

$cases = [
    [
        'label' => 'complet',
        'row' => [
            'pCategory' => 'Frane',
            'pBrand' => 'BOSCH',
            'pPrice' => '120.50',
            'pImages' => '["https://cdn.example.com/part.jpg"]',
            'pImageSource' => 'tecdoc',
        ],
        'expectFlags' => [],
        'expectBlock' => false,
    ],
    [
        'label' => 'fara categorie',
        'row' => [
            'pCategory' => '',
            'pBrand' => 'BOSCH',
            'pPrice' => '10',
            'pImages' => '["https://cdn.example.com/part.jpg"]',
            'pImageSource' => 'tecdoc',
        ],
        'expectFlags' => ['missing_category'],
        'expectBlock' => true,
    ],
    [
        'label' => 'pret zero',
        'row' => [
            'pCategory' => 'Frane',
            'pBrand' => 'BOSCH',
            'pPrice' => '0',
            'pImages' => '["https://cdn.example.com/part.jpg"]',
            'pImageSource' => 'tecdoc',
        ],
        'expectFlags' => ['zero_price'],
        'expectBlock' => true,
    ],
    [
        'label' => 'fara imagine',
        'row' => [
            'pCategory' => 'Frane',
            'pBrand' => 'BOSCH',
            'pPrice' => '99',
            'pImages' => '[]',
            'pImageSource' => 'missing',
        ],
        'expectFlags' => ['missing_image'],
        'expectBlock' => true,
    ],
    [
        'label' => 'multiplu',
        'row' => [
            'pCategory' => '',
            'pBrand' => '',
            'pPrice' => '',
            'pImages' => '[]',
        ],
        'expectFlags' => ['missing_category', 'missing_brand', 'zero_price', 'missing_image'],
        'expectBlock' => true,
    ],
];

foreach ($cases as $case) {
    $flags = besoiu_import_row_critical_flags($case['row']);
    $codes = array_column($flags, 'code');
    sort($codes);
    $expected = $case['expectFlags'];
    sort($expected);

    if ($codes !== $expected) {
        echo 'FAIL ' . $case['label'] . ' flags: got ' . implode(',', $codes) . ' expected ' . implode(',', $expected) . "\n";
        ++$failures;
    } else {
        echo 'OK  flags ' . $case['label'] . "\n";
    }

    $blocked = besoiu_import_row_blocks_auto_publish($case['row']);
    if ($blocked !== $case['expectBlock']) {
        echo 'FAIL ' . $case['label'] . ' block auto-publish=' . ($blocked ? '1' : '0') . "\n";
        ++$failures;
    } else {
        echo 'OK  block ' . $case['label'] . "\n";
    }
}

$filter = besoiu_import_filter_auto_publishable_rows([
    ['id' => 1, 'pCategory' => 'X', 'pBrand' => 'Y', 'pPrice' => '1', 'pImages' => '["https://cdn.example.com/a.jpg"]', 'pImageSource' => 'tecdoc'],
    ['id' => 2, 'pCategory' => '', 'pBrand' => 'Y', 'pPrice' => '1', 'pImages' => '["https://cdn.example.com/a.jpg"]', 'pImageSource' => 'tecdoc'],
]);
if (count($filter['publishable']) !== 1 || ($filter['blocked'] ?? 0) !== 1) {
    echo "FAIL filter_auto_publishable_rows\n";
    ++$failures;
} else {
    echo "OK  filter_auto_publishable_rows\n";
}

$importReviewPath = $root . '/admin/Templates/admin/pages/import/importreview.php';
$html = is_file($importReviewPath) ? (string) file_get_contents($importReviewPath) : '';
$needles = [
    'import-queue-critical.php',
    'import-critical-badge',
    'import-row--critical-gaps',
    'Alerte date',
    'Auto-publish blocat',
    'import-queue-critical-banner',
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
if (!str_contains($actionSrc, 'besoiu_import_filter_auto_publishable_rows')) {
    echo "FAIL importproduse_action fără filtru auto-publish\n";
    ++$failures;
} else {
    echo "OK  importproduse_action filtru auto-publish\n";
}

if ($failures === 0) {
    echo "\nTM099_IMPORT_QUEUE_CRITICAL_OK\n";
    exit(0);
}

echo "\nTM099_IMPORT_QUEUE_CRITICAL_FAILED ({$failures})\n";
exit(1);
