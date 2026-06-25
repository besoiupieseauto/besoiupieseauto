<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Config\Database;
use Evasystem\Services\Orders\LegacyOrderService;
use Evasystem\Services\Orders\OrderTmpService;
use Evasystem\Services\Orders\SupplierCartToTmpImporter;
use Evasystem\Services\Orders\SupplierPlaceOrderService;
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
$sessionId = 'test-m1-full-' . $userId;

$tmpService = new OrderTmpService($legacyPdo);
$orderService = new LegacyOrderService($legacyPdo, $tmpService);
$cartService = new SupplierCartService($legacyPdo);
$importer = new SupplierCartToTmpImporter($legacyPdo, $tmpService);
$placeService = new SupplierPlaceOrderService($tmpService, $orderService, $cartService, $importer);

try {
    // M1c: add product manual to tmp
    $tmpService->clear($sessionId);
    $stmt = $legacyPdo->query('SELECT idprodus, pret FROM produse ORDER BY idprodus ASC LIMIT 1');
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$product) {
        throw new RuntimeException('No produse in legacy DB');
    }
    $tmpService->addProduct($sessionId, [
        'id_produs' => (int) $product['idprodus'],
        'cantitate' => 2,
        'pret' => (float) ($product['pret'] ?? 50),
        'furnizor' => '__',
    ]);
    echo "M1c tmp lines: " . count($tmpService->listProducts($sessionId)['products']) . PHP_EOL;

    // M1b/f internal order
    $internal = $orderService->createInternalFromTmp($sessionId, $userId, [
        'id_client' => 1,
        'data' => date('Y-m-d'),
        'idstare' => 1,
        'locatie_mgz' => 1,
    ]);
    echo "M1b internal order #" . $internal['idcomanda'] . PHP_EOL;

    // M1e: edit - add line, update header
    $orderId = (int) $internal['idcomanda'];
    $orderService->updateHeader($orderId, 'interna', ['observations' => 'Test M1e edit']);
    $orderService->addLine($orderId, 'interna', [
        'id_produs' => (int) $product['idprodus'],
        'cantitate' => 1,
        'pret' => 25.0,
    ]);
    $loaded = $orderService->getOrder($orderId, 'interna');
    echo "M1e lines after add: " . count($loaded['lines']) . ", total=" . $loaded['calculated_total'] . PHP_EOL;

    // M1f: external order from tmp
    $tmpService->addProduct($sessionId, [
        'id_produs' => (int) $product['idprodus'],
        'cantitate' => 1,
        'pret' => 99.0,
    ]);
    $external = $orderService->createExternalFromTmp($sessionId, $userId, [
        'id_client' => 1,
        'data' => date('Y-m-d'),
        'idstare' => 1,
    ]);
    echo "M1f external order #" . $external['idcomanda'] . PHP_EOL;

    // M1g: place from supplier cart
    $cartService->saveCart($userId, []);
    $cartService->addItem($userId, [
        'supplier' => 'autopartner',
        'product_code' => 'TESTM1G',
        'product_name' => 'Test place order',
        'manufacturer' => 'TEST',
        'variant_code' => 'TESTM1G',
        'qty' => 1,
        'price' => 55.0,
        'currency' => 'RON',
        'livrare' => 'Maine',
        'depozit' => 'CN',
    ]);
    $cart = $cartService->getCart($userId);
    $keys = [];
    foreach ($cart as $supplier => $items) {
        foreach ($items as $key => $item) {
            $keys[] = $supplier . '|' . $key;
        }
    }
    $placed = $placeService->placeFromCart($sessionId, $userId, [
        'import_from' => 'UTVIN',
        'id_client' => 1,
        'order_item_keys' => $keys,
        'idstare' => 1,
        'data' => date('Y-m-d'),
    ]);
    echo "M1g place order #" . ($placed['order']['idcomanda'] ?? '?') . " mode=" . $placed['mode'] . PHP_EOL;

    echo "M1 full test OK.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'ERROR: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
