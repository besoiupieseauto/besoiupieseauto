<?php
declare(strict_types=1);

/**
 * Test CLI coș furnizori (necesită LEGACY_DB_* + tabel supplier_carts).
 * Usage: php admin/migrations/_test_supplier_cart.php [user_id]
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Config\Database;
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

$userId = max(1, (int) ($argv[1] ?? 1));
$service = new SupplierCartService(SupplierSearchConfig::legacyPdo());

try {
    $added = $service->addItem($userId, [
        'supplier' => 'autopartner',
        'product_code' => 'GDB1330',
        'mfrpn' => 'GDB1330',
        'product_name' => 'Placute frana TRW',
        'manufacturer' => 'TRW',
        'variant_code' => 'GDB1330',
        'searched_code' => 'GDB1330',
        'qty' => 2,
        'price' => 108,
        'raw_price' => 61.43,
        'currency' => 'RON',
        'livrare' => 'Maine 8:00',
        'depozit' => 'CN',
        'departamentcode' => 'CN',
    ]);
    echo "ADD OK\n";
    echo json_encode($added['summary'], JSON_PRETTY_PRINT) . PHP_EOL;

    $show = $service->show($userId);
    echo "SHOW lines=" . ($show['summary']['lines'] ?? 0) . PHP_EOL;

    $cart = $show['cart']['autopartner'] ?? [];
    $key = array_key_first($cart);
    if ($key !== null) {
        $updated = $service->updateQty($userId, 'autopartner', (string) $key, 3);
        echo "UPDATE qty=3 items=" . ($updated['summary']['items'] ?? 0) . PHP_EOL;

        $removed = $service->removeItem($userId, 'autopartner', (string) $key);
        echo "REMOVE lines=" . ($removed['summary']['lines'] ?? 0) . PHP_EOL;
    }

    echo "Supplier cart test completed.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'ERROR: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
