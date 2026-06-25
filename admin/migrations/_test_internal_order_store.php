<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Config\Database;
use Evasystem\Services\Orders\InternalOrderService;
use Evasystem\Services\Orders\OrderTmpService;
use Evasystem\Services\Orders\SupplierCartToTmpImporter;
use Evasystem\Services\SupplierSearch\SupplierCartService;
use Evasystem\Services\SupplierSearch\SupplierSearchConfig;

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();
$config = require dirname(__DIR__) . '/config/config.php';

Database::getInstance($config['db_host'], $config['db_name'], $config['db_user'], $config['db_pass']);
if (!empty($config['legacy_db_name'])) {
    Database::getInstance(
        $config['legacy_db_host'],
        $config['legacy_db_name'],
        $config['legacy_db_user'],
        $config['legacy_db_pass'],
        'legacy'
    );
}

$legacyPdo = SupplierSearchConfig::legacyPdo();
if ($legacyPdo === null) {
    fwrite(STDERR, "LEGACY_DB not configured\n");
    exit(1);
}

$userId = max(1, (int) ($argv[1] ?? 1));
$sessionId = 'test-internal-order-' . $userId;

$cartService = new SupplierCartService($legacyPdo);
$tmpService = new OrderTmpService($legacyPdo);
$importer = new SupplierCartToTmpImporter($legacyPdo, $tmpService);
$orderService = new InternalOrderService($legacyPdo, $tmpService);

try {
    $cartService->saveCart($userId, []);
    $cartService->addItem($userId, [
        'supplier' => 'autopartner',
        'product_code' => 'GDB1330',
        'product_name' => 'Placute frana TRW',
        'manufacturer' => 'TRW',
        'variant_code' => 'GDB1330',
        'searched_code' => 'GDB1330',
        'qty' => 1,
        'price' => 108,
        'raw_price' => 61.43,
        'currency' => 'RON',
        'livrare' => 'Maine 8:00',
        'depozit' => 'CN',
        'departamentcode' => 'CN',
    ]);

    $importer->importFromSupplierCart($sessionId, $cartService->getCart($userId), true);

    $order = $orderService->createFromTmp($sessionId, $userId, [
        'id_client' => 1,
        'data' => date('Y-m-d'),
        'idstare' => 1,
        'locatie_mgz' => 1,
        'observations' => 'Test M1b CLI',
    ]);

    echo json_encode($order, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;

    $remaining = $tmpService->listProducts($sessionId);
    echo 'TMP lines after store=' . count($remaining['products']) . PHP_EOL;
    echo "Internal order test completed.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'ERROR: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
