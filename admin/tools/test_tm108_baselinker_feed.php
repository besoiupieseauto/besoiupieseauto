<?php
declare(strict_types=1);

/**
 * Smoke test tm_108 — Feed permanent XML/JSON BaseLinker
 */
require __DIR__ . '/php_cli.php';

$admin = dirname(__DIR__);
$root = dirname($admin);
require_once $admin . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable($admin);
$dotenv->safeLoad();
$config = require $admin . '/config/config.php';
\Config\Database::getInstance(
    $config['db_host'],
    $config['db_name'],
    $config['db_user'],
    $config['db_pass']
);

$fail = static function (string $msg): void {
    fwrite(STDERR, "FAIL: {$msg}\n");
    exit(1);
};

$ok = static function (string $msg): void {
    echo "OK: {$msg}\n";
};

$feedLib = $root . '/system/baselinker-feed.php';
$apiFile = $root . '/api/baselinker-feed.php';
$regenFile = $admin . '/cron_cli/baselinker_feed_regen.php';
$endpointFile = $admin . '/public/api/marketplace_endpoint.php';
$uiFile = $admin . '/Templates/admin/pages/marketplace/baselinker.php';
$importAction = $admin . '/src/Controllers/Produse/importproduse_action.php';
$queueWorker = $admin . '/cron_cli/queue_worker.php';
$serviceFile = $admin . '/src/Controllers/Marketplace/MarketplaceService.php';

foreach ([$feedLib, $apiFile, $regenFile, $endpointFile, $uiFile, $importAction, $queueWorker, $serviceFile] as $path) {
    if (!is_file($path)) {
        $fail('Lipseste fisier: ' . $path);
    }
}
$ok('Fisiere tm_108 prezente');

$feedSrc = file_get_contents($feedLib) ?: '';
foreach ([
    'baselinker_feed_regenerate',
    'baselinker_feed_queue_regenerate',
    'baselinker_feed_public_urls',
    'BASELINKER_FEED_MAX_FRAGMENT_BYTES',
] as $needle) {
    if (!str_contains($feedSrc, $needle)) {
        $fail("baselinker-feed.php — lipsa: {$needle}");
    }
}
$ok('baselinker-feed.php — functii core');

$importSrc = file_get_contents($importAction) ?: '';
if (!str_contains($importSrc, 'baselinker_feed_queue_regenerate')) {
    $fail('importproduse_action.php — hook publicare feed lipsa');
}
$ok('importproduse_action.php — hook dupa publicare import');

$queueSrc = file_get_contents($queueWorker) ?: '';
if (!str_contains($queueSrc, 'baselinker_feed_regenerate')) {
    $fail('queue_worker.php — job baselinker_feed_regenerate lipsa');
}
$ok('queue_worker.php — job feed regenerate');

$endpointSrc = file_get_contents($endpointFile) ?: '';
foreach (['baselinker_feed_info', 'baselinker_feed_regenerate'] as $needle) {
    if (!str_contains($endpointSrc, $needle)) {
        $fail("marketplace_endpoint — actiune lipsa: {$needle}");
    }
}
$ok('marketplace_endpoint — actiuni feed');

$serviceSrc = file_get_contents($serviceFile) ?: '';
foreach (['getBaseLinkerFeedInfo', 'regenerateBaseLinkerFeed'] as $needle) {
    if (!str_contains($serviceSrc, $needle)) {
        $fail("MarketplaceService — metoda lipsa: {$needle}");
    }
}
$ok('MarketplaceService — metode feed');

$uiSrc = file_get_contents($uiFile) ?: '';
foreach (['id="blFeedXmlUrl"', 'id="blRegenFeedBtn"', 'tm_108'] as $needle) {
    if (!str_contains($uiSrc, $needle)) {
        $fail("baselinker.php — UI feed lipsa: {$needle}");
    }
}
$ok('baselinker.php — panou URL feed');

require_once $feedLib;

$sample = baselinker_feed_product_entry([
    'pName' => 'Filtru ulei',
    'pCode' => 'FL-108',
    'pPrice' => '120.5',
    'pStock' => '4',
    'pBrand' => 'Mann',
    'pNoteMarketplace' => 'Test feed',
    'pImages' => '["https://example.test/img.jpg"]',
    'randomn_id' => 'abc108',
], baselinker_feed_default_mapping(), 'http://besoiupieseauto.ro.test');

$xml = baselinker_feed_product_to_xml($sample);
if (!str_contains($xml, '<sku>FL-108</sku>') || !str_contains($xml, '<price_brutto>120.50</price_brutto>')) {
    $fail('XML produs feed invalid');
}
$ok('baselinker-feed — XML produs sample');

$pdo = \Config\Database::getDB();
$result = baselinker_feed_regenerate($pdo);
if (($result['success'] ?? false) !== true) {
    $fail('baselinker_feed_regenerate esuat');
}
$ok('baselinker_feed_regenerate — ' . (int) ($result['product_count'] ?? 0) . ' produse, ' . (int) ($result['parts'] ?? 0) . ' fragmente');

$info = baselinker_feed_info($pdo);
$token = trim((string) ($info['urls']['token'] ?? ''));
if ($token === '') {
    $fail('Token feed lipsa');
}
$ok('baselinker_feed_info — token generat');

$storageDir = baselinker_feed_storage_dir();
if (!is_readable($storageDir . '/catalog-001.xml') || !is_readable($storageDir . '/catalog.json')) {
    $fail('Fisiere feed statice lipsa dupa regenerare');
}
$ok('Storage feed — catalog-001.xml + catalog.json');

$xmlSize = filesize($storageDir . '/catalog-001.xml');
if ($xmlSize === false || $xmlSize > BASELINKER_FEED_MAX_FRAGMENT_BYTES) {
    $fail('Fragment XML depaseste limita configurata');
}
$ok('Fragment XML sub limita — ' . number_format((int) $xmlSize, 0, ',', '.') . ' bytes');

echo "\nTM_108 BaseLinker feed permanent: toate verificarile au trecut.\n";
