<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
require $root . '/app/Import/bootstrap.php';
require $root . '/app/Import/MatchingPro/api/bootstrap.php';
require_once import_motor_product_matcher_path();
import_require_fetch_product_lib('SupplierPriceListBuilder.php');
import_require_fetch_product_lib('SupplierEnrichedCardBuilder.php');

$supplier = $argv[1] ?? 'elit';
$filename = $argv[2] ?? 'Lista pret Elit 16.01.2026.csv';
$limit = (int) ($argv[3] ?? 20);

$path = import_resolve_stored_file($supplier, $filename);
if ($path === null) {
    fwrite(STDERR, "File not found: {$supplier}/{$filename}\n");
    exit(1);
}

$headerHandle = fopen($path, 'rb');
$header = $headerHandle !== false ? fgetcsv($headerHandle, 0, ';') : false;
if ($headerHandle) {
    fclose($headerHandle);
}

$buildLimit = $limit;
if (SupplierPriceListBuilder::isBaseCsvHeader($header)) {
    $matcher = new ProductMatcher();
    [$cards, $stats] = $matcher->buildCardsFromUploadedCsv($path, $buildLimit, true);
    $fileType = 'base';
} else {
    $builder = new SupplierEnrichedCardBuilder();
    [$cards, $stats] = $builder->buildCardsFromFile($path, $buildLimit, false);
    $fileType = 'supplier_enriched';
}

echo "File: {$supplier}/{$filename} type={$fileType} limit={$limit}\n";
echo "Strat 3 build-stored raw built: " . count($cards) . "\n";
echo "  rowsScanned: " . ($stats['rowsScanned'] ?? '?') . "\n";

$filtered = import_filter_verified_image_cards($cards);
echo "Strat 4 after import_filter_verified_image_cards: " . count($filtered) . "\n";

$page = array_slice($filtered, 0, $limit);
echo "Strat 3 page slice (limit={$limit}): " . count($page) . "\n";
$hasMore = count($filtered) > $limit || (($stats['rowsScanned'] ?? 0) >= $buildLimit);
echo "  has_more: " . ($hasMore ? 'yes' : 'no') . "\n";

$args = ['scan', '--file', $path, '--supplier', $supplier, '--no-archive', '--force', '--sample', (string) $limit];
$result = import_run_python($args, 540);
$payload = null;
if ($result['json']) {
    $items = $result['json'];
    $last = $items[array_key_last($items)] ?? null;
    $payload = is_array($last) ? ($last['payload'] ?? $last) : null;
}
if (is_array($payload)) {
    $products = $payload['products'] ?? [];
    $summary = $payload['summary'] ?? [];
    echo "Strat 3 matching sample={$limit} products: " . count($products) . "\n";
    echo "  summary: " . json_encode($summary, JSON_UNESCAPED_UNICODE) . "\n";
    $matchOk = array_values(array_filter(
        $products,
        static fn (array $p): bool => in_array((string) ($p['status'] ?? ''), ['exact', 'probable', 'conflict'], true)
    ));
    echo "Strat 4 tecdoc match statuses: " . count($matchOk) . "\n";
}
