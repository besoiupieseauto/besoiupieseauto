<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
require_once $root . '/admin/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable($root . '/admin');
$dotenv->safeLoad();

$config = require $root . '/admin/config/config.php';
require_once $root . '/admin/config/Database.php';

Config\Database::getInstance(
    $config['db_host'],
    $config['db_name'],
    $config['db_user'],
    $config['db_pass']
);
$pdo = Config\Database::getDB();

require_once $root . '/lib/Scraper/ImageSearchService.php';

$rid = $argv[1] ?? '4d9e8186b2ddda29';
$testMode = in_array('--test', $argv, true);

$stmt = $pdo->prepare(
    "SELECT id, randomn_id, pName, pCode, pBrand, pOem, pCategory, pSubcategory, pNote, pImages, pImageSource
     FROM produse WHERE randomn_id = ? LIMIT 1"
);
$stmt->execute([$rid]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!is_array($row)) {
    fwrite(STDERR, "Produs negăsit: {$rid}\n");
    exit(1);
}

$cats = [];
foreach (['pCategory', 'pSubcategory'] as $col) {
    $v = strtolower(trim((string) ($row[$col] ?? '')));
    if ($v !== '') {
        $cats[] = $v;
    }
}

$opts = ['categories' => $cats, 'force' => true];
if ($testMode) {
    $opts['test_mode'] = true;
    $opts['skip_vision'] = true;
    $opts['listing_fallback'] = true;
}

$t0 = microtime(true);
$result = ImageSearchService::resolve($row, $opts);
$ms = (int) round((microtime(true) - $t0) * 1000);

echo "mode=" . ($testMode ? 'test' : 'prod') . " ms={$ms}\n";
echo 'tried=' . count($result['tried'] ?? []) . "\n";
foreach ($result['tried'] ?? [] as $t) {
    if (!is_array($t)) {
        continue;
    }
    echo ' - ' . ($t['source_id'] ?? '?') . ' [' . ($t['status'] ?? '') . '] ' . ($t['message'] ?? '') . "\n";
}
$hitUrl = trim((string) ($result['hit']['url'] ?? ''));
echo $hitUrl !== '' ? "HIT: {$hitUrl}\n" : "HIT: none\n";
