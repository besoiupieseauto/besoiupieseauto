<?php
require 'F:/laragon/www/besoiupieseauto.ro/admin/bootstrap.php';
require BESOIU_BACKEND . '/vendor/autoload.php';
require_once BESOIU_LEGACY . '/shop-db.php';
$c = require BESOIU_CONFIG . '/config.php';
\Config\Database::getInstance($c['db_host'], $c['db_name'], $c['db_user'], $c['db_pass']);
require_once BESOIU_ROOT . '/app/Import/Scraper/lib/EpiesaHtmlFetcher.php';
require_once BESOIU_ROOT . '/app/Import/Scraper/lib/EpiesaCategoryParser.php';

$q = '255-102';
$url = 'https://www.epiesa.ro/cautare-piesa/?q=' . rawurlencode($q);
echo "Direct fetch test: $q\n";
try {
    $html = EpiesaHtmlFetcher::fetch($url, 30);
    echo 'stealth/html len=' . strlen($html) . "\n";
    $items = EpiesaCategoryParser::parse($html, 5, false, $q);
    echo 'items=' . count($items) . "\n";
    foreach (array_slice($items, 0, 3) as $i) {
        echo '- ' . ($i['title'] ?? '') . ' | ' . ($i['code'] ?? '') . ' | ' . ($i['image'] ?? '') . "\n";
    }
} catch (Throwable $e) {
    echo 'stealth error: ' . $e->getMessage() . "\n";
}

$pages = EpiesaHtmlFetcher::fetchSearchParallel([$q, 'ELSTOCK 255-102'], 2, 20);
echo 'parallel pages=' . count($pages) . "\n";
foreach ($pages as $query => $html) {
    $items = EpiesaCategoryParser::parse($html, 5, false, $query);
    echo "query=$query items=" . count($items) . " htmlLen=" . strlen($html) . "\n";
}
