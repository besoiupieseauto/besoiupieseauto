<?php
declare(strict_types=1);

/**
 * Smoke test tm_042 — bulk Livrare curier: Nu
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

$service = new ProduseService();
$paged = $service->getProdusesPaginated(1, 1);
$product = $paged['items'][0] ?? null;
$tempId = null;
if ($product === null) {
    $tempId = 'tm042_test_' . bin2hex(random_bytes(4));
    $service->addProduse([
        'randomn_id' => $tempId,
        'pName' => 'TM042 test produs',
        'pPrice' => '10',
        'pBasePrice' => '10',
        'pStock' => '1',
        'pCurierLivrare' => 'Da',
        'status' => 1,
    ]);
    $product = $service->getIdProduses($tempId);
    if ($product === null) {
        $fail('Nu am putut crea produs temporar pentru test.');
    }
    $ok('Produs temporar creat pentru test');
}

$id = (string) ($product['randomn_id'] ?? $product['id'] ?? '');
if ($id === '') {
    $fail('Produs fara ID valid.');
}

if (!$service->setProductCurierLivrare($id, 'Nu')) {
    $fail('setProductCurierLivrare a esuat.');
}
$ok('setProductCurierLivrare Nu');

$refreshed = $service->getIdProduses($id);
if (($refreshed['pCurierLivrare'] ?? '') !== 'Nu') {
    $fail('Valoarea pCurierLivrare nu a fost salvata ca Nu.');
}
$ok('Valoare Nu confirmata in BD');

$result = $service->setCurierLivrareBulk([$id], 'Da');
if ((int) ($result['updated'] ?? 0) !== 1) {
    $fail('setCurierLivrareBulk nu a actualizat produsul.');
}
$ok('setCurierLivrareBulk Da');

$refreshed = $service->getIdProduses($id);
if (($refreshed['pCurierLivrare'] ?? '') !== 'Da') {
    $fail('Bulk revert la Da a esuat.');
}
$ok('Revert Da confirmat');

$cruduSrc = file_get_contents($admin . '/src/Controllers/Produse/crudu.php');
if (!str_contains($cruduSrc, "set_curier_livrare_bulk")) {
    $fail('crudu.php lipseste endpoint set_curier_livrare_bulk');
}
$ok('Endpoint crudu set_curier_livrare_bulk');

$produseTpl = file_get_contents($admin . '/Templates/admin/pages/produse/produse.php');
foreach (['setFilteredCurierNu', 'setSelectedCurierNuBtn', 'set_curier_livrare_bulk'] as $needle) {
    if (!str_contains($produseTpl, $needle)) {
        $fail("produse.php lipseste: {$needle}");
    }
}
$ok('UI bulk Livrare curier in produse.php');

if ($tempId !== null) {
    $service->deleteProduse($tempId);
    $ok('Produs temporar sters');
}

echo "ALL TESTS PASSED tm_042\n";
