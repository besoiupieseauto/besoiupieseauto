<?php

declare(strict_types=1);

require_once __DIR__ . '/_autoload.php';

use Besoiu\Core\Bootstrap\ApiBootstrap;
use Besoiu\Modules\SupplierSearch\Service\SupplierSearchConfig;
use Besoiu\Services\Orders\LegacyOrderService;
use Besoiu\Services\Orders\OrderTmpService;
use Besoiu\Services\Orders\SupplierCartToTmpImporter;
use Besoiu\Services\Orders\SupplierPlaceOrderService;
use Besoiu\Services\SupplierSearch\SupplierCartService;

$config = ApiBootstrap::bootJsonApi();
ApiBootstrap::registerJsonFatalGuard('legacy_orders');

try {
    ApiBootstrap::requireAuthenticatedSession();
    ApiBootstrap::beginBoundedJsonWork(45);

    if (!empty($config['legacy_db_name'])) {
        \Config\Database::getInstance(
            (string) $config['legacy_db_host'],
            (string) $config['legacy_db_name'],
            (string) $config['legacy_db_user'],
            (string) $config['legacy_db_pass'],
            'legacy'
        );
    }

    $userId = (int) ($_SESSION['user_id'] ?? 0);
    $action = trim((string) ($_GET['action'] ?? ''));
    if ($action !== 'place_from_supplier_cart') {
        throw new InvalidArgumentException('Acțiune necunoscută.');
    }

    $payload = json_decode(file_get_contents('php://input') ?: '', true);
    if (!is_array($payload)) {
        $payload = $_POST;
    }

    $pdo = SupplierSearchConfig::legacyPdo();
    $tmp = new OrderTmpService($pdo);
    $result = (new SupplierPlaceOrderService(
        $tmp,
        new LegacyOrderService($pdo, $tmp),
        new SupplierCartService($pdo),
        new SupplierCartToTmpImporter($pdo, $tmp)
    ))->placeFromCart(session_id(), $userId, $payload);

    ApiBootstrap::json(['success' => true] + $result);
} catch (InvalidArgumentException $exception) {
    ApiBootstrap::json(['success' => false, 'message' => $exception->getMessage()], 400);
} catch (Throwable $exception) {
    ApiBootstrap::respondInternalError('legacy_orders_endpoint', $exception);
}
