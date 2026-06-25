<?php
declare(strict_types=1);

/**
 * tm_064 — Corelare RapidAPI ↔ BD locală (cod normalizat + brand) înainte de afișare client.
 */

require_once dirname(__DIR__, 2) . '/system/tecdoc_stock.php';

$errors = [];
$pdo = tecdoc_db();

echo "=== tm_064 helpers exist ===\n";
$required = [
    'tecdoc_correlate_article_to_local_product',
    'tecdoc_find_web_products_for_article',
    'tecdoc_find_catalog_rows_by_code_brand',
];
foreach ($required as $fn) {
    if (!function_exists($fn)) {
        $errors[] = "Missing function: {$fn}";
    }
}
echo json_encode(['functions_ok' => $errors === []], JSON_UNESCAPED_UNICODE) . "\n\n";

echo "=== tm_064 sample product from BD ===\n";
$sample = $pdo->query(
    "SELECT pCode, pBrand, pName, pPrice, pImages
     FROM produse
     WHERE status <> '0'
       AND TRIM(COALESCE(pCode, '')) <> ''
       AND TRIM(COALESCE(pBrand, '')) <> ''
     ORDER BY id DESC
     LIMIT 1"
)->fetch(PDO::FETCH_ASSOC);

$tempProductId = 0;
if (!is_array($sample)) {
    $tempRandomId = 'tm064_' . bin2hex(random_bytes(4));
    $insert = $pdo->prepare(
        "INSERT INTO produse (randomn_id, pName, pCode, pBrand, pPrice, pImages, pNote, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, '1')"
    );
    $insert->execute([
        $tempRandomId,
        'Filtru ulei TM064 TEST',
        'TM064TEST',
        'MANN',
        '99.50',
        json_encode(['/uploads/products/tecdoc/tm064test.jpg'], JSON_UNESCAPED_SLASHES),
        '<p>Descriere locala TM064</p>',
    ]);
    $tempProductId = (int) $pdo->lastInsertId();
    $sample = [
        'pCode' => 'TM064TEST',
        'pBrand' => 'MANN',
        'pName' => 'Filtru ulei TM064 TEST',
        'pPrice' => '99.50',
        'pImages' => json_encode(['/uploads/products/tecdoc/tm064test.jpg']),
    ];
    echo json_encode(['seeded_temp_product' => true, 'id' => $tempProductId], JSON_UNESCAPED_UNICODE) . "\n";
}

if (is_array($sample)) {
    $code = (string) ($sample['pCode'] ?? '');
    $brand = (string) ($sample['pBrand'] ?? '');
    $article = [
        'articleNumber' => $code,
        'articleNo' => $code,
        'brandName' => $brand,
        'supplierName' => $brand,
        'articleProductName' => 'RapidAPI summary only',
        'urlImage' => 'https://example.com/tecdoc-only.jpg',
    ];

    $linked = tecdoc_correlate_article_to_local_product($pdo, $article);
    $web = tecdoc_find_web_products_for_article($pdo, $article);

    echo json_encode([
        'sample_code' => $code,
        'sample_brand' => $brand,
        'linked' => $linked !== null,
        'local_linked_flag' => $linked['local_linked'] ?? null,
        'uses_local_name' => ($linked['name'] ?? '') === trim((string) ($sample['pName'] ?? '')),
        'uses_local_price' => ($linked['price'] ?? '') === trim((string) ($sample['pPrice'] ?? '')),
        'rapidapi_code' => $linked['rapidapi_code'] ?? null,
        'rapidapi_brand' => $linked['rapidapi_brand'] ?? null,
        'web_products_count' => count($web),
    ], JSON_UNESCAPED_UNICODE) . "\n\n";

    if ($linked === null) {
        $errors[] = 'correlate returned null for known local code+brand';
    } elseif (empty($linked['local_linked'])) {
        $errors[] = 'local_linked flag missing on correlated product';
    } elseif (($linked['name'] ?? '') === 'RapidAPI summary only') {
        $errors[] = 'product name taken from RapidAPI instead of local pName';
    } elseif (trim((string) ($linked['image'] ?? '')) === 'https://example.com/tecdoc-only.jpg') {
        $errors[] = 'product image taken from RapidAPI instead of local pImages';
    }
}

echo "\n";

echo "=== tm_064 foreign brand rejected ===\n";
$foreignArticle = [
    'articleNumber' => is_array($sample) ? (string) ($sample['pCode'] ?? 'TEST000') : 'TEST000',
    'brandName' => 'ZZZ-NOT-IN-STOCK-999',
    'supplierName' => 'ZZZ-NOT-IN-STOCK-999',
];
$foreignLinked = tecdoc_correlate_article_to_local_product($pdo, $foreignArticle);
echo json_encode([
    'foreign_brand_rejected' => $foreignLinked === null,
], JSON_UNESCAPED_UNICODE) . "\n\n";

if ($foreignLinked !== null && is_array($sample) && tecdoc_normalize_code((string) ($sample['pCode'] ?? '')) !== '') {
    $errors[] = 'foreign brand should not correlate to local product';
}

echo "=== tm_064 search_stock local_linked on BD products ===\n";
$search = tecdoc_public_search(['category' => 'Filtre']);
$products = is_array($search['products'] ?? null) ? $search['products'] : [];
$first = $products[0] ?? null;
echo json_encode([
    'success' => $search['success'] ?? false,
    'count' => count($products),
    'first_local_linked' => is_array($first) ? ($first['local_linked'] ?? null) : null,
], JSON_UNESCAPED_UNICODE) . "\n\n";

if ($products !== [] && empty($first['local_linked'])) {
    $errors[] = 'BD search product missing local_linked=true';
}

if ($tempProductId > 0) {
    $pdo->prepare('DELETE FROM produse WHERE id = ?')->execute([$tempProductId]);
}

if ($errors !== []) {
    fwrite(STDERR, implode("\n", $errors) . "\n");
    exit(1);
}

echo "TM064_RAPIDAPI_LOCAL_CORRELATION_OK\n";
