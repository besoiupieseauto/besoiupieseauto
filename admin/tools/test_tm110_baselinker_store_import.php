<?php
declare(strict_types=1);

/**
 * Smoke test tm_110 — Import din magazin BaseLinker (Shops API + investigare + tichet suport)
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

$shopLib = $root . '/system/baselinker-shop-integration.php';
$shopApi = $root . '/api/baselinker-shop-integration.php';
$investigationFile = $admin . '/src/Services/Marketplace/BaseLinkerStoreImportInvestigation.php';
$limitsFile = $admin . '/src/Services/Marketplace/BaseLinkerImportLimits.php';
$serviceFile = $admin . '/src/Controllers/Marketplace/MarketplaceService.php';
$endpointFile = $admin . '/public/api/marketplace_endpoint.php';
$uiFile = $admin . '/Templates/admin/pages/marketplace/baselinker.php';
$htaccess = $root . '/.htaccess';

foreach ([$shopLib, $shopApi, $investigationFile, $limitsFile, $serviceFile, $endpointFile, $uiFile, $htaccess] as $path) {
    if (!is_file($path)) {
        $fail('Lipseste fisier: ' . $path);
    }
}
$ok('Fisiere tm_110 prezente');

$shopSrc = file_get_contents($shopLib) ?: '';
foreach (
    [
        'baselinker_shop_dispatch',
        'baselinker_shop_info',
        'baselinker_shop_validate_bl_pass',
        'ProductsList',
        'BASELINKER_SHOP_PRODUCTS_PER_PAGE',
    ] as $needle
) {
    if (!str_contains($shopSrc, $needle)) {
        $fail("baselinker-shop-integration.php — lipsa: {$needle}");
    }
}
$ok('baselinker-shop-integration.php — functii Shops API');

$investigationClass = \Evasystem\Services\Marketplace\BaseLinkerStoreImportInvestigation::class;
$report = $investigationClass::report(100000);
if (($report['task_id'] ?? '') !== 'tm_110' || ($report['status'] ?? '') !== 'investigated') {
    $fail('BaseLinkerStoreImportInvestigation — report invalid');
}
if (!is_array($report['findings'] ?? null) || count($report['findings']) < 3) {
    $fail('BaseLinkerStoreImportInvestigation — findings incomplete');
}
$ok('BaseLinkerStoreImportInvestigation — raport investigare');

$limits = \Evasystem\Services\Marketplace\BaseLinkerImportLimits::class;
$storeSummary = $limits::storeImportSummary(100000);
if (($storeSummary['bypasses_file_limit'] ?? false) !== true) {
    $fail('BaseLinkerImportLimits — storeImportSummary invalid');
}
$ok('BaseLinkerImportLimits — import_din_magazin documentat');

require_once $shopLib;
$pdo = \Config\Database::getDB();
$shopInfo = baselinker_shop_info($pdo);
if (trim((string) ($shopInfo['bl_pass'] ?? '')) === '') {
    $fail('baselinker_shop_info — bl_pass lipsa');
}
$ticket = $investigationClass::buildSupportTicket($shopInfo, (int) ($shopInfo['active_products'] ?? 0));
if (!str_contains($ticket, 'Custom store integration') || !str_contains($ticket, (string) ($shopInfo['bl_pass'] ?? ''))) {
    $fail('Tichet suport BaseLinker incomplet');
}
$ok('Tichet suport BaseLinker generat');

$blPass = (string) $shopInfo['bl_pass'];
$supported = baselinker_shop_dispatch($pdo, ['action' => 'SupportedMethods', 'bl_pass' => $blPass]);
if (!is_array($supported['methods'] ?? null) || !in_array('ProductsList', $supported['methods'], true)) {
    $fail('SupportedMethods — ProductsList lipsa');
}
$ok('SupportedMethods — ProductsList inclus');

$fileVersion = baselinker_shop_dispatch($pdo, ['action' => 'FileVersion', 'bl_pass' => $blPass]);
if (trim((string) ($fileVersion['platform'] ?? '')) === '') {
    $fail('FileVersion — platform lipsa');
}
$ok('FileVersion — platform custom');

$productsList = baselinker_shop_dispatch($pdo, ['action' => 'ProductsList', 'bl_pass' => $blPass, 'page' => 1]);
if (!is_array($productsList['products'] ?? null)) {
    $fail('ProductsList — products array lipsa');
}
$ok('ProductsList — ' . count($productsList['products']) . ' produse pagina 1');

$invalid = baselinker_shop_dispatch($pdo, ['action' => 'ProductsList', 'bl_pass' => 'invalid']);
if (($invalid['error_code'] ?? '') !== 'invalid_password') {
    $fail('Validare bl_pass esuata');
}
$ok('Validare bl_pass — respinge parola invalida');

$serviceSrc = file_get_contents($serviceFile) ?: '';
if (!str_contains($serviceSrc, 'getBaseLinkerStoreImportInfo')) {
    $fail('MarketplaceService — getBaseLinkerStoreImportInfo lipsa');
}
$ok('MarketplaceService — getBaseLinkerStoreImportInfo');

$endpointSrc = file_get_contents($endpointFile) ?: '';
if (!str_contains($endpointSrc, 'baselinker_store_import_info')) {
    $fail('marketplace_endpoint — actiune baselinker_store_import_info lipsa');
}
$ok('marketplace_endpoint — actiune tm_110');

$uiSrc = file_get_contents($uiFile) ?: '';
foreach (
    [
        'id="blStoreImportCard"',
        'Import din magazin — investigare tm_110',
        'id="blSupportTicket"',
        'id="blCopyTicketBtn"',
        'blShopIntegrationUrl',
        'tm_110, investigat',
    ] as $needle
) {
    if (!str_contains($uiSrc, $needle)) {
        $fail("baselinker.php — UI tm_110 lipsa: {$needle}");
    }
}
$ok('baselinker.php — panou investigare + tichet suport');

$htaccessSrc = file_get_contents($htaccess) ?: '';
if (!str_contains($htaccessSrc, 'baselinker-shop-integration.php')) {
    $fail('.htaccess — rewrite shop integration lipsa');
}
$ok('.htaccess — alias fișier integrare magazin');

$service = new \Evasystem\Controllers\Marketplace\MarketplaceService(new \Evasystem\Core\Marketplace\MarketplaceModel());
$apiInfo = $service->getBaseLinkerStoreImportInfo();
if (!is_array($apiInfo['investigation'] ?? null) || !is_string($apiInfo['support_ticket'] ?? null)) {
    $fail('getBaseLinkerStoreImportInfo — structura invalida');
}
$ok('getBaseLinkerStoreImportInfo — date complete');

echo "\nTM_110 BaseLinker import din magazin: toate verificarile au trecut.\n";
