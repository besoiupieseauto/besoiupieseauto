<?php
declare(strict_types=1);

/**
 * Test: titlu ATE „set placute…”, vehicul unic, lane no_image.
 * php app/Backend/tools/test_importreview_title_vehicle_lane.php
 */

$root = dirname(__DIR__, 3);
require $root . '/admin/bootstrap.php';
require BESOIU_BACKEND . '/vendor/autoload.php';

$envDir = is_file($root . '/.env') ? $root : BESOIU_CONFIG;
if (class_exists(\Dotenv\Dotenv::class) && is_file($envDir . '/.env')) {
    \Dotenv\Dotenv::createImmutable($envDir)->safeLoad();
}

$config = require BESOIU_CONFIG . '/config.php';
\Config\Database::getInstance(
    (string) ($config['db_host'] ?? '127.0.0.1'),
    (string) ($config['db_name'] ?? 'besoiupieseauto.ro'),
    (string) ($config['db_user'] ?? 'root'),
    (string) ($config['db_pass'] ?? '')
);

if (!defined('IMPORT_PRODUCE_SKIP_HTTP')) {
    define('IMPORT_PRODUCE_SKIP_HTTP', true);
}
if (!defined('IMPORT_ACTION_SKIP_HTTP')) {
    define('IMPORT_ACTION_SKIP_HTTP', true);
}
\Besoiu\Services\Import\ImportLibLoader::bootForQueueActions();
require_once dirname(__DIR__, 2) . '/Import/MatchingPro/api/lib/ImportCardStaging.php';

use Besoiu\Services\ProductCardFormationService;

$errors = [];

$svc = new ProductCardFormationService();
$product = [
    'pName' => 'set placute frana,frana disc',
    'pCode' => '13.0460-4804.2',
    'pBrand' => 'ATE',
    'pCategory' => 'Frânare',
    'pSubcategory' => 'Discuri și plăcuțe',
    'pMarca' => 'RENAULT',
    'pModel' => 'MEGANE III',
    'pMotorizare' => "cupe (DZ0/1_) 2.0 R.S.\n2.0 TCe (DZ1N)",
];
$raw = [
    '__tecdoc_art_name' => 'set placute frana,frana disc',
    'product_summary' => ['tecdoc_art_name' => 'set placute frana,frana disc'],
];
$applied = $svc->applyToImportProduct($product, $raw);
$title = trim((string) ($applied['product']['pName'] ?? ''));
$mp = trim((string) ($applied['product']['pNameMarketplace'] ?? ''));

echo "WEB: {$title}\nMP:  {$mp}\n";

if ($title === '' || preg_match('/^set\b/iu', $title) || preg_match('/placute\s+frana\s*,/iu', $title)) {
    $errors[] = "Titlu WEB neformatat: {$title}";
}
if (preg_match('/RENAULT\s*,/iu', $title) || preg_match('/\bVOLVO\b/iu', $title)) {
    $errors[] = "Titlu WEB cu dump multi-marcă: {$title}";
}
if (!preg_match('/ATE/i', $title) || !str_contains(str_replace(' ', '', $title), '13.0460-4804.2')) {
    $errors[] = "Titlu WEB fără brand/cod: {$title}";
}
if (!preg_match('/\bpentru\b/iu', $title)) {
    $errors[] = "Titlu WEB fără 'pentru': {$title}";
}
if (preg_match('/\b(frana|frână)\b.*\b(frana|frână)\b/iu', $title)) {
    $errors[] = "Titlu WEB cu frână duplicat: {$title}";
}

// Staging: vehicul din compat multi-marcă → doar primul (format Base / coadă)
$compat = "RENAULT: MEGANE III cupe (DZ0/1_) 2.0 R.S. (2008-2016); MEGANE III cupe (DZ0/1_) 2.0 TCe (DZ1N) (2009-2015)\n"
    . "VOLVO: S60 I (384) R 2,5 T AWD (2003-2010); V70 II (285) R 2,5 T AWD (2003-2007)";
$fromCompat = ImportCardStaging::primaryVehicleFromCompatText($compat);
$marca = trim((string) ($fromCompat['marca'] ?? ''));
$model = trim((string) ($fromCompat['model'] ?? ''));
echo "Vehicul: {$marca} / {$model}\n";
if ($marca !== 'RENAULT') {
    $errors[] = "Marcă așteptată RENAULT, got: {$marca}";
}
if (!preg_match('/^MEGANE\s+III$/iu', $model)) {
    $errors[] = "Model așteptat MEGANE III, got: {$model}";
}
if (str_contains($marca, ',') || stripos($model, 'VOLVO') !== false) {
    $errors[] = "Dump multi-marcă rămas: {$marca} / {$model}";
}

$card = [
    'title' => 'Placute Frana ATE',
    'name' => 'set placute frana,frana disc',
    'supplierSku' => '13.0460-4804.2',
    'brand' => 'ATE',
    'compatText' => $compat,
    'images' => ['https://example.com/pad.jpg'],
    'stock' => '1',
    'category' => 'Frânare',
    'subcategory' => 'Discuri și plăcuțe',
];
$staged = ImportCardStaging::cardToProduct($card, 'standard', ['skip_category_match' => true]);
$stagedMarca = trim((string) ($staged['pMarca'] ?? ''));
$stagedModel = trim((string) ($staged['pModel'] ?? ''));
echo "Staging: {$stagedMarca} / {$stagedModel}\n";
if ($stagedMarca !== 'RENAULT' || !preg_match('/MEGANE/iu', $stagedModel)) {
    $errors[] = "Staging vehicul greșit: {$stagedMarca} / {$stagedModel}";
}

// Lane: fără imagine → no_image
$rowNoImg = [
    'pName' => $title,
    'pCode' => '13.0460-4804.2',
    'pBrand' => 'ATE',
    'pImages' => '[]',
    'raw_json' => json_encode(['import_lane' => 'standard'], JSON_UNESCAPED_UNICODE),
];
$reconciled = import_reconcile_import_lane($rowNoImg);
$laneRaw = json_decode((string) ($reconciled['raw_json'] ?? '{}'), true);
$lane = is_array($laneRaw) ? trim((string) ($laneRaw['import_lane'] ?? '')) : '';
echo "Lane fără imagine: {$lane}\n";
if ($lane !== 'no_image') {
    $errors[] = "Lane așteptat no_image, got: {$lane}";
}

if ($errors === []) {
    echo "\nOK: titlu + vehicul + lane.\n";
    exit(0);
}

echo "\nFAIL:\n- " . implode("\n- ", $errors) . "\n";
exit(1);
