<?php
declare(strict_types=1);

/**
 * P6 — CLI smoke test for /admin/product button backends:
 * seed product → set_badge → delete → confirm DB.
 *
 * php app/Backend/tools/test_admin_product_buttons.php
 */

$root = dirname(__DIR__, 3);
require $root . '/admin/bootstrap.php';
require BESOIU_BACKEND . '/vendor/autoload.php';

$envDir = is_file($root . '/.env') ? $root : BESOIU_CONFIG;
if (class_exists(Dotenv\Dotenv::class) && is_file($envDir . '/.env')) {
    Dotenv\Dotenv::createImmutable($envDir)->safeLoad();
}

$config = require BESOIU_CONFIG . '/config.php';
Config\Database::getInstance(
    (string) ($config['db_host'] ?? '127.0.0.1'),
    (string) ($config['db_name'] ?? 'besoiupieseauto.ro'),
    (string) ($config['db_user'] ?? 'root'),
    (string) ($config['db_pass'] ?? '')
);

use Besoiu\Core\Produse\ProduseModel;
use Besoiu\Services\Products\ProduseService;

$fail = static function (string $msg): void {
    fwrite(STDERR, "FAIL: {$msg}\n");
    exit(1);
};

$ok = static function (string $msg): void {
    echo "OK: {$msg}\n";
};

echo "══════════════════════════════════════════════════════════\n";
echo " P6 — test butoane admin product (seed / badge / delete)\n";
echo "══════════════════════════════════════════════════════════\n\n";

$service = new ProduseService();
$model = new ProduseModel();
$marker = 'P6-TEST-' . bin2hex(random_bytes(4));
$randomId = bin2hex(random_bytes(8));

$seed = [
    'randomn_id' => $randomId,
    'pName' => $marker . ' Filtru ulei test',
    'pCode' => 'P6-' . strtoupper(substr($randomId, 0, 8)),
    'pBrand' => 'TESTBRAND',
    'pCategory' => 'Filtre',
    'pSubcategory' => 'Filtru ulei',
    'pPrice' => '99.50',
    'pBasePrice' => '70.00',
    'pStock' => '3',
    'pBadge' => '',
    'status' => 1,
    'pImages' => '[]',
    'pNote' => 'Produs temporar P6 CLI test — safe to delete.',
];

$createdId = trim((string) $service->addProduse($seed));
if ($createdId === '') {
    $fail('addProduse nu a returnat ID.');
}
$ok('Produs seed creat: ' . $createdId);

$loaded = $service->getIdProduses($createdId);
if (!$loaded) {
    $fail('Produsul seed nu poate fi citit din DB.');
}
$ok('Citire DB după seed');

if (!$service->setProductBadge($createdId, 'hot')) {
    $fail('setProductBadge(hot) a eșuat.');
}
$afterBadge = $service->getIdProduses($createdId);
if (trim((string) ($afterBadge['pBadge'] ?? '')) !== 'hot') {
    $fail('pBadge nu este hot după setProductBadge.');
}
$ok('Badge HOT aplicat (set_badge)');

$bulk = $model->updateMany([$createdId], ['pBadge' => 'promo']);
if ((int) ($bulk['updated'] ?? 0) < 1) {
    $fail('updateMany/set_badge_bulk a returnat updated=0.');
}
$afterBulk = $service->getIdProduses($createdId);
if (trim((string) ($afterBulk['pBadge'] ?? '')) !== 'promo') {
    $fail('pBadge nu este promo după updateMany.');
}
$ok('Badge PROMO aplicat (set_badge_bulk path)');

$deleted = $service->deleteProduse($createdId);
if (!$deleted) {
    $fail('deleteProduse a eșuat.');
}
$gone = $service->getIdProduses($createdId);
if ($gone !== null) {
    $fail('Produsul încă există după delete.');
}
$ok('Produs șters (delete)');

$bulkSeedId = bin2hex(random_bytes(8));
$service->addProduse([
    'randomn_id' => $bulkSeedId,
    'pName' => $marker . ' Bulk delete',
    'pCode' => 'P6B-' . strtoupper(substr($bulkSeedId, 0, 8)),
    'pBrand' => 'TESTBRAND',
    'pPrice' => '12.00',
    'pBasePrice' => '10.00',
    'status' => 1,
    'pImages' => '[]',
]);
$bulkDelete = $model->deleteMany([$bulkSeedId]);
if ((int) ($bulkDelete['deleted'] ?? 0) < 1) {
    $fail('deleteMany a returnat deleted=0.');
}
if ($service->getIdProduses($bulkSeedId) !== null) {
    $fail('Produsul bulk încă există după deleteMany.');
}
$ok('Ștergere bulk (delete_bulk path)');

// Cleanup any leftover marker rows from interrupted runs.
$pdo = Config\Database::getDB();
$cleanup = $pdo->prepare("DELETE FROM produse WHERE pName LIKE :m OR pCode LIKE 'P6-%' OR pCode LIKE 'P6B-%'");
$cleanup->execute(['m' => $marker . '%']);

echo "\nALL P6 CHECKS PASSED\n";
exit(0);
