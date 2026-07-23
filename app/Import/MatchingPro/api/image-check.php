<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once import_motor_product_matcher_path();
$prelucrareConfig = import_motor_prelucrare_api_dir() . '/config.php';
if (is_file($prelucrareConfig)) {
    require_once $prelucrareConfig;
}

$brand = trim((string) ($_GET['brand'] ?? ''));
$code = trim((string) ($_GET['code'] ?? ''));
$supplier = strtolower(trim((string) ($_GET['supplier'] ?? '')));
$allowAutopartner = import_supplier_slug_allows_autopartner_images($supplier);

$pozeDir = import_motor_poze_dir();
$apDb = import_motor_autopartner_cache();

$stats = [
    'poze_folder' => is_dir($pozeDir),
    'autopartner_sqlite' => is_file($apDb),
    'autopartner_sqlite_mb' => is_file($apDb) ? round(filesize($apDb) / 1048576, 2) : 0,
    'note' => 'Imaginile NU sunt în price-index.sqlite — sunt în Poze/ + autopartner.sqlite + fișiere JPG. Catalog Autopartner doar pentru supplier=autopartner.',
];

if ($brand === '' || $code === '') {
    import_json_response([
        'success' => true,
        'stats' => $stats,
        'usage' => 'Adaugă ?brand=ELRING&code=000162&supplier=elit pentru verificare produs.',
        'example' => 'api/image-check.php?brand=ELSTOCK&code=28-0585&supplier=elit',
    ]);
}

$matcher = new ProductMatcher();
$card = [
    'sku' => $code,
    'brand' => $brand,
    'hasImage' => false,
    'ttcArtId' => '',
    'autopartnerCode' => '',
    'imageSource' => '',
];
$updated = $matcher->attachImageToCard($card, $brand, $code, $allowAutopartner);

$previewUrl = '';
if (!empty($updated['hasImage'])) {
    $base = import_motor_proxy_url('product-image.php');
    if (($updated['imageSource'] ?? '') === 'autopartner' && ($updated['autopartnerCode'] ?? '') !== '') {
        $previewUrl = $base . '?source=autopartner&code=' . rawurlencode((string) $updated['autopartnerCode']);
    } elseif (($updated['ttcArtId'] ?? '') !== '') {
        $previewUrl = $base . '?source=poze&brand=' . rawurlencode($brand) . '&id=' . rawurlencode((string) $updated['ttcArtId']);
    }
}

import_json_response([
    'success' => true,
    'stats' => $stats,
    'query' => ['brand' => $brand, 'code' => $code],
    'result' => [
        'hasImage' => !empty($updated['hasImage']),
        'imageSource' => $updated['imageSource'] ?? '',
        'ttcArtId' => $updated['ttcArtId'] ?? '',
        'autopartnerCode' => $updated['autopartnerCode'] ?? '',
        'previewUrl' => $previewUrl,
        'verdict' => !empty($updated['hasImage'])
            ? 'Imagine GĂSITĂ în baza locală (' . ($updated['imageSource'] ?? '') . ')'
            : 'Imagine LIPSĂ din Poze + Autopartner — cartela „Fără imagine” e corectă; folosește Scraping.',
    ],
]);
