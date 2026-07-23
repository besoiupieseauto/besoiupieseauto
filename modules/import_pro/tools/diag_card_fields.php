<?php
declare(strict_types=1);
$root = dirname(__DIR__, 3);
require $root . '/app/Import/bootstrap.php';
require $root . '/app/Import/MatchingPro/api/bootstrap.php';
import_require_fetch_product_lib('SupplierEnrichedCardBuilder.php');

$path = import_resolve_stored_file('elit', 'Lista pret Elit 16.01.2026.csv');
$builder = new SupplierEnrichedCardBuilder();
[$cards] = $builder->buildCardsFromFile($path, 20, false);
$cards = import_filter_verified_image_cards($cards);
foreach (array_slice($cards, 0, 5) as $i => $c) {
    echo ($i+1) . ". sku=" . ($c['sku']??'') 
        . " cardBuild=" . ($c['cardBuild']??'(empty)')
        . " title_len=" . strlen($c['title']??'')
        . " params=" . count($c['parameters']??[])
        . " compat=" . ($c['compatCount']??0)
        . " desc_len=" . strlen(strip_tags($c['description']??''))
        . "\n";
}
