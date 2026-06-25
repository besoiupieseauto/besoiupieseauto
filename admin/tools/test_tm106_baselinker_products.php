<?php
declare(strict_types=1);

/**
 * Smoke test tm_106 — Integrare API BaseLinker (produse)
 */
require __DIR__ . '/php_cli.php';

$admin = dirname(__DIR__);
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

$serviceFile = $admin . '/src/Controllers/Marketplace/MarketplaceService.php';
$mapperFile = $admin . '/src/Services/Marketplace/BaseLinkerProductMapper.php';
$endpointFile = $admin . '/public/api/marketplace_endpoint.php';
$uiFile = $admin . '/Templates/admin/pages/marketplace/baselinker.php';
$hubFile = $admin . '/Templates/admin/pages/marketplace/marketplace.php';

foreach ([$serviceFile, $mapperFile, $endpointFile, $uiFile, $hubFile] as $path) {
    if (!is_file($path)) {
        $fail('Lipseste fisier: ' . $path);
    }
}
$ok('Fisiere BaseLinker produse prezente');

$serviceSrc = file_get_contents($serviceFile) ?: '';
foreach (['syncProductsToBaseLinker', 'testBaseLinkerConnection', 'saveBaseLinkerFieldMapping'] as $needle) {
    if (!str_contains($serviceSrc, $needle)) {
        $fail("MarketplaceService lipseste metoda {$needle}");
    }
}
$ok('MarketplaceService — metode catalog produse');

$endpointSrc = file_get_contents($endpointFile) ?: '';
foreach (['baselinker_test', 'baselinker_sync_products', 'baselinker_save_mapping'] as $needle) {
    if (!str_contains($endpointSrc, $needle)) {
        $fail("marketplace_endpoint lipseste actiunea {$needle}");
    }
}
$ok('marketplace_endpoint — actiuni BaseLinker produse');

$mapper = \Evasystem\Services\Marketplace\BaseLinkerProductMapper::class;
$defaults = $mapper::defaultMapping();
if (($defaults['name'] ?? '') !== 'pName' || ($defaults['sku'] ?? '') !== 'pCode') {
    $fail('Mapare default BaseLinker invalida');
}
$ok('BaseLinkerProductMapper — mapare default');

$payload = $mapper::toBaseLinkerPayload([
    'pName' => 'Filtru ulei',
    'pCode' => 'FL-001',
    'pPrice' => '99.5',
    'pStock' => '3',
    'pBrand' => 'Mann',
    'pNoteMarketplace' => 'Descriere test',
    'randomn_id' => 'abc123',
], $defaults, 'http://besoiupieseauto.ro.test');

if (($payload['name'] ?? '') !== 'Filtru ulei' || (float) ($payload['price_brutto'] ?? 0) !== 99.5) {
    $fail('Payload BaseLinker generat incorect');
}
$ok('BaseLinkerProductMapper — payload produs');

$pdo = \Config\Database::getDB();
$cols = $pdo->query("SHOW COLUMNS FROM marketplace LIKE 'bl_inventory_id'")->fetch(PDO::FETCH_ASSOC);
if (!$cols) {
    $fail('Coloana marketplace.bl_inventory_id lipseste — ruleaza migrarea 061.');
}
$ok('Migrare 061 — coloane BaseLinker in marketplace');

$hubSrc = file_get_contents($hubFile) ?: '';
foreach (['id="mpBlCard"', 'href="/admin/marketplace-baselinker"'] as $needle) {
    if (!str_contains($hubSrc, $needle)) {
        $fail("marketplace.php — selector lipsa: {$needle}");
    }
}
$ok('marketplace.php — card BaseLinker (#mpBlCard)');

$uiSrc = file_get_contents($uiFile) ?: '';
foreach (
    [
        'id="blCreateBtn"',
        'id="blTestBtn"',
        'id="blSaveMappingBtn"',
        'id="blSyncBtn"',
        'class="bl-map-field"',
        'id="blInventory"',
        '/admin/api/marketplace_endpoint.php',
    ] as $needle
) {
    if (!str_contains($uiSrc, $needle)) {
        $fail("baselinker.php — selector/endpoint lipsa: {$needle}");
    }
}
$ok('baselinker.php — selectori UI + endpoint JS');

echo "\nTM_106 BaseLinker produse: toate verificarile statice au trecut.\n";
