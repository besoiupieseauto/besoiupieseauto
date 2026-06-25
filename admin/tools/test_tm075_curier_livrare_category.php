<?php
declare(strict_types=1);

/**
 * Smoke test tm_075 — Livrare curier Da/Nu + bulk pe categorie
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

use Evasystem\Controllers\Produse\ProduseService;
use Evasystem\Core\Produse\ProduseModel;

$fail = static function (string $msg): void {
    fwrite(STDERR, "FAIL: {$msg}\n");
    exit(1);
};

$ok = static function (string $msg): void {
    echo "OK: {$msg}\n";
};

$model = new ProduseModel();
$ref = new ReflectionMethod($model, 'hasColumn');
$ref->setAccessible(true);
if (!$ref->invoke($model, 'pCurierLivrare')) {
    $fail('Coloana produse.pCurierLivrare lipseste — ruleaza migrarea 057.');
}
$ok('Coloana pCurierLivrare exista');

$addTpl = file_get_contents($admin . '/Templates/admin/pages/produse/addproduse.php');
$editTpl = file_get_contents($admin . '/Templates/admin/pages/produse/editproduse.php');
foreach ([$addTpl, $editTpl] as $tpl) {
    if (str_contains($tpl, 'pShipping') && str_contains($tpl, 'shippingOptions')) {
        $fail('Formularul inca expune optiuni complexe pShipping.');
    }
    if (!str_contains($tpl, 'pCurierLivrare')) {
        $fail('Formularul nu expune pCurierLivrare Da/Nu.');
    }
}
$ok('Formulare add/edit — doar Livrare curier Da/Nu');

$cruduSrc = file_get_contents($admin . '/src/Controllers/Produse/crudu.php');
if (!str_contains($cruduSrc, 'set_curier_livrare_by_category')) {
    $fail('crudu.php lipseste endpoint set_curier_livrare_by_category');
}
$ok('Endpoint crudu set_curier_livrare_by_category');

$produseTpl = file_get_contents($admin . '/Templates/admin/pages/produse/produse.php');
foreach (['setCategoryCurierNu', 'setCategoryCurierDa', 'set_curier_livrare_by_category'] as $needle) {
    if (!str_contains($produseTpl, $needle)) {
        $fail("produse.php lipseste: {$needle}");
    }
}
$ok('UI bulk Livrare curier pe categorie in produse.php');

$service = new ProduseService();
$testCategory = 'TM075 Test Categorie ' . bin2hex(random_bytes(3));
$ids = [];
for ($i = 0; $i < 2; ++$i) {
    $rid = 'tm075_test_' . bin2hex(random_bytes(4));
    $service->addProduse([
        'randomn_id' => $rid,
        'pName' => 'TM075 test produs ' . $i,
        'pCategory' => $testCategory,
        'pPrice' => '10',
        'pBasePrice' => '10',
        'pStock' => '1',
        'pCurierLivrare' => 'Da',
        'status' => 1,
    ]);
    $ids[] = $rid;
}
$ok('Produse temporare create in categorie test');

$result = $service->setCurierLivrareByCategory($testCategory, 'Nu');
if ((int) ($result['updated'] ?? 0) < 2) {
    $fail('setCurierLivrareByCategory nu a actualizat toate produsele din categorie.');
}
$ok('setCurierLivrareByCategory Nu pe ' . (int) $result['updated'] . ' produse');

foreach ($ids as $id) {
    $row = $service->getIdProduses($id);
    if (($row['pCurierLivrare'] ?? '') !== 'Nu') {
        $fail('Produsul ' . $id . ' nu are pCurierLivrare=Nu dupa bulk categorie.');
    }
}
$ok('Valori Nu confirmate in BD pentru categorie');

$resultDa = $service->setCurierLivrareByCategory($testCategory, 'Da');
if ((int) ($resultDa['updated'] ?? 0) < 2) {
    $fail('setCurierLivrareByCategory revert Da a esuat.');
}
$ok('Revert Da pe categorie');

foreach ($ids as $id) {
    $service->deleteProduse($id);
}
$ok('Produse temporare sterse');

echo "ALL TESTS PASSED tm_075\n";
