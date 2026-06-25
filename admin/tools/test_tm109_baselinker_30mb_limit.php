<?php
declare(strict_types=1);

/**
 * Smoke test tm_109 — Soluție limită 30MB BaseLinker (API direct + coadă batch)
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

$limitsFile = $admin . '/src/Services/Marketplace/BaseLinkerImportLimits.php';
$serviceFile = $admin . '/src/Controllers/Marketplace/MarketplaceService.php';
$endpointFile = $admin . '/public/api/marketplace_endpoint.php';
$uiFile = $admin . '/Templates/admin/pages/marketplace/baselinker.php';
$syncFile = $admin . '/cron_cli/baselinker_sync.php';

foreach ([$limitsFile, $serviceFile, $endpointFile, $uiFile, $syncFile] as $path) {
    if (!is_file($path)) {
        $fail('Lipseste fisier: ' . $path);
    }
}
$ok('Fisiere tm_109 prezente');

$limits = \Evasystem\Services\Marketplace\BaseLinkerImportLimits::class;
$catalog = $limits::catalog();
if (($catalog['xml_max_mb'] ?? 0) !== 30 || ($catalog['csv_max_mb'] ?? 0) !== 5) {
    $fail('Limite BaseLinker documentate incorect');
}
$ok('BaseLinkerImportLimits — 5MB CSV / 30MB XML / 100MB zi');

$strategy = $limits::recommendStrategy(100000, 50);
if (($strategy['recommended'] ?? '') !== 'api_direct' || ($strategy['file_import_feasible'] ?? true) !== false) {
    $fail('Strategie recomandata invalida pentru 100k produse');
}
if (($strategy['estimated_api_batches'] ?? 0) !== 2000) {
    $fail('Estimare batch-uri API incorecta pentru 100k / batch 50');
}
$ok('BaseLinkerImportLimits — strategie api_direct pentru catalog mare');

$serviceSrc = file_get_contents($serviceFile) ?: '';
foreach (
    [
        'getBaseLinkerCatalogStats',
        'enqueueBaseLinkerCatalogSync',
        'continueBaseLinkerCatalogSync',
        'countActiveProductsForBaseLinker',
        'pushBaseLinkerCatalogJob',
        'has_more',
        'next_offset',
    ] as $needle
) {
    if (!str_contains($serviceSrc, $needle)) {
        $fail("MarketplaceService lipseste: {$needle}");
    }
}
$ok('MarketplaceService — sync catalog cu offset + enqueue coada');

$endpointSrc = file_get_contents($endpointFile) ?: '';
foreach (['baselinker_catalog_stats', 'baselinker_enqueue_catalog'] as $needle) {
    if (!str_contains($endpointSrc, $needle)) {
        $fail("marketplace_endpoint lipseste actiunea {$needle}");
    }
}
$ok('marketplace_endpoint — actiuni tm_109');

$syncSrc = file_get_contents($syncFile) ?: '';
if (!str_contains($syncSrc, 'auto_continue') || !str_contains($syncSrc, 'continueBaseLinkerCatalogSync')) {
    $fail('baselinker_sync.php — auto-continue batch lipsa');
}
$ok('baselinker_sync.php — auto-continue catalog in coada');

$uiSrc = file_get_contents($uiFile) ?: '';
foreach (
    [
        'id="blLimitsPanel"',
        'id="blEnqueueBtn"',
        'MB XML',
        'baselinker_enqueue_catalog',
        'baselinker_catalog_stats',
        'Pune tot catalogul',
    ] as $needle
) {
    if (!str_contains($uiSrc, $needle)) {
        $fail("baselinker.php — UI tm_109 lipsa: {$needle}");
    }
}
$ok('baselinker.php — panou limite + buton enqueue catalog');

$service = new \Evasystem\Controllers\Marketplace\MarketplaceService(new \Evasystem\Core\Marketplace\MarketplaceModel());
$count = $service->countActiveProductsForBaseLinker();
if ($count < 0) {
    $fail('countActiveProductsForBaseLinker return invalid');
}
$ok('countActiveProductsForBaseLinker — ' . $count . ' produse active');

echo "\nTM_109 BaseLinker 30MB limit: toate verificarile statice au trecut.\n";
