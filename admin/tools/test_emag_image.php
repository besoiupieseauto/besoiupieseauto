<?php

declare(strict_types=1);

/**
 * Test rapid eMAG + scrape.do pentru un cod produs din coada import.
 * Rulează: php admin/tools/test_emag_image.php 101088
 */

$code = trim((string) ($argv[1] ?? '101088'));
$root = dirname(__DIR__, 2);

require_once $root . '/admin/vendor/autoload.php';
if (is_file($root . '/admin/.env')) {
    Dotenv\Dotenv::createImmutable($root . '/admin')->safeLoad();
}

require_once $root . '/admin/config/Database.php';
require_once $root . '/admin/config/config.php';
$config = require $root . '/admin/config/config.php';
Config\Database::getInstance(
    (string) $config['db_host'],
    (string) $config['db_name'],
    (string) $config['db_user'],
    (string) $config['db_pass']
);

require_once $root . '/system/tecdoc_stock.php';
require_once $root . '/system/emag_image_search.php';
require_once $root . '/lib/Scraper/ScrapeDoConfig.php';

$pdo = Config\Database::getDB();
$stmt = $pdo->prepare("SELECT * FROM import_produse WHERE pCode = ? AND status = 'pending' ORDER BY id DESC LIMIT 1");
$stmt->execute([$code]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!is_array($row)) {
    fwrite(STDERR, "Produs pending cu pCode={$code} negăsit.\n");
    exit(1);
}

echo 'Token scrape.do: ' . (ScrapeDoConfig::hasToken() ? 'DA' : 'NU') . PHP_EOL;
echo 'Denumire: ' . ($row['pName'] ?? '') . PHP_EOL;
echo 'Query-uri: ' . implode(' | ', EmagSearch::queriesFromProduct($row)) . PHP_EOL;

$result = emag_find_image_for_product($row);
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
