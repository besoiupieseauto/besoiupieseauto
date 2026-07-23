<?php
declare(strict_types=1);

$root = dirname(__DIR__, 3);
require $root . '/app/Import/bootstrap.php';
require $root . '/app/Import/MatchingPro/api/bootstrap.php';
require_once import_motor_product_matcher_path();
import_require_fetch_product_lib('SupplierEnrichedCardBuilder.php');

$supplier = $argv[1] ?? 'elit';
$filename = $argv[2] ?? 'Lista pret Elit 16.01.2026.csv';
$limit = (int) ($argv[3] ?? 20);
$path = import_resolve_stored_file($supplier, $filename);
$builder = new SupplierEnrichedCardBuilder();

$totalComplete = 0;
$offset = 0;
$pages = 0;
while ($totalComplete < $limit && $pages < 10) {
    $buildLimit = $offset + $limit;
    [$all, $stats] = $builder->buildCardsFromFile($path, $buildLimit, false);
    $filtered = import_filter_verified_image_cards($all);
    if ($offset > 0) {
        $filtered = array_slice($filtered, $offset);
    }
    $page = array_slice($filtered, 0, $limit);
    $count = count($page);
    $totalComplete += $count;
    echo "Page {$pages} offset={$offset} pageCards={$count} cumulative={$totalComplete}\n";
    if ($count === 0) break;
    $offset += $count;
    $pages++;
    if ($count < $limit && ($stats['rowsScanned'] ?? 0) < $buildLimit) break;
}

echo "Final complete cards toward limit {$limit}: {$totalComplete}\n";
