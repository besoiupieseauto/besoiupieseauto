<?php

declare(strict_types=1);

require_once __DIR__ . '/_autoload.php';

use Config\Database;
use Evasystem\Core\CaietComenzi\CaietComenziModel;
use Evasystem\Services\Orders\LegacyOrderService;
use Evasystem\Services\Orders\OrderDeliveryColorMapper;
use Evasystem\Services\Orders\OrderTmpService;
use Evasystem\Services\Orders\SupplierCartToTmpImporter;
use Evasystem\Services\Orders\SupplierPlaceOrderService;
use Evasystem\Services\SupplierSearch\SupplierCartService;
use Evasystem\Services\SupplierSearch\SupplierSearchConfig;
use Evasystem\Core\Bootstrap\ApiBootstrap;
$config = ApiBootstrap::bootJsonApi();

try {
    ApiBootstrap::requireAuthenticatedSession();

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
    $sessionId = session_id();
    $userId = (int) $_SESSION['user_id'];
    $tmpService = new OrderTmpService($legacyPdo);
    $orderService = new LegacyOrderService($legacyPdo, $tmpService);
    $cartService = new SupplierCartService($legacyPdo);
    $importer = new SupplierCartToTmpImporter($legacyPdo, $tmpService, new OrderDeliveryColorMapper());
    $placeOrderService = new SupplierPlaceOrderService($tmpService, $orderService, $cartService, $importer);

    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    $action = trim((string) ($_GET['action'] ?? ''));

    if ($method === 'GET') {
        if ($action === '' || $action === 'meta') {
            ApiBootstrap::json([
                'success' => true,
                'statuses' => LegacyOrderService::statusOptions(),
                'locations' => LegacyOrderService::locationOptions(),
            ]);
        }

        if ($action === 'get') {
            $orderId = (int) ($_GET['order_id'] ?? $_GET['id'] ?? 0);
            $sourceType = (string) ($_GET['source_type'] ?? $_GET['source'] ?? 'interna');
            if ($orderId <= 0) {
                throw new InvalidArgumentException('order_id invalid.');
            }
            ApiBootstrap::json([
                'success' => true,
                'data' => $orderService->getOrder($orderId, $sourceType),
            ]);
        }

        if ($action === 'products') {
            $model = new CaietComenziModel();
            $search = trim((string) ($_GET['q'] ?? $_GET['search'] ?? ''));
            $limit = min(100, max(1, (int) ($_GET['limit'] ?? 30)));
            ApiBootstrap::json([
                'success' => true,
                'data' => $model->findProduse(['search' => $search, 'limit' => $limit]),
            ]);
        }

        throw new InvalidArgumentException('Acțiune GET necunoscută.');
    }

    if ($method !== 'POST') {
        ApiBootstrap::json(['success' => false, 'message' => 'Doar GET/POST sunt permise.'], 405);
    }

    $payload = json_decode(file_get_contents('php://input') ?: '', true);
    if (!is_array($payload)) {
        $payload = $_POST;
    }
    if ($action === '') {
        $action = trim((string) ($payload['action'] ?? ''));
    }

    switch ($action) {
        case 'create_from_tmp':
        case 'create_internal':
            $result = $orderService->createInternalFromTmp($sessionId, $userId, $payload);
            ApiBootstrap::json([
                'success' => true,
                'message' => 'Comanda internă a fost creată.',
                'order' => $result,
            ]);

        case 'create_external':
            $result = $orderService->createExternalFromTmp($sessionId, $userId, $payload);
            ApiBootstrap::json([
                'success' => true,
                'message' => 'Comanda externă a fost creată.',
                'order' => $result,
            ]);

        case 'update_header':
            $orderId = (int) ($payload['order_id'] ?? $payload['idcomanda'] ?? 0);
            $sourceType = (string) ($payload['source_type'] ?? $payload['source'] ?? 'interna');
            if ($orderId <= 0) {
                throw new InvalidArgumentException('order_id invalid.');
            }
            ApiBootstrap::json([
                'success' => true,
                'message' => 'Antet comandă actualizat.',
                'data' => $orderService->updateHeader($orderId, $sourceType, $payload),
            ]);

        case 'line_add':
            $orderId = (int) ($payload['order_id'] ?? 0);
            $sourceType = (string) ($payload['source_type'] ?? 'interna');
            if ($orderId <= 0) {
                throw new InvalidArgumentException('order_id invalid.');
            }
            ApiBootstrap::json([
                'success' => true,
                'message' => 'Linie adăugată.',
                'data' => $orderService->addLine($orderId, $sourceType, $payload),
            ]);

        case 'line_update':
            $orderId = (int) ($payload['order_id'] ?? 0);
            $lineId = (int) ($payload['line_id'] ?? $payload['iddetaliu'] ?? 0);
            $sourceType = (string) ($payload['source_type'] ?? 'interna');
            if ($orderId <= 0 || $lineId <= 0) {
                throw new InvalidArgumentException('order_id / line_id invalid.');
            }
            ApiBootstrap::json([
                'success' => true,
                'message' => 'Linie actualizată.',
                'data' => $orderService->updateLine($orderId, $sourceType, $lineId, $payload),
            ]);

        case 'line_delete':
            $orderId = (int) ($payload['order_id'] ?? 0);
            $lineId = (int) ($payload['line_id'] ?? $payload['iddetaliu'] ?? 0);
            $sourceType = (string) ($payload['source_type'] ?? 'interna');
            if ($orderId <= 0 || $lineId <= 0) {
                throw new InvalidArgumentException('order_id / line_id invalid.');
            }
            ApiBootstrap::json([
                'success' => true,
                'message' => 'Linie ștearsă.',
                'data' => $orderService->deleteLine($orderId, $sourceType, $lineId),
            ]);

        case 'place_from_supplier_cart':
            $result = $placeOrderService->placeFromCart($sessionId, $userId, $payload);
            ApiBootstrap::json(['success' => true] + $result);

        default:
            throw new InvalidArgumentException('Acțiune necunoscută: ' . $action);
    }
} catch (InvalidArgumentException $exception) {
    ApiBootstrap::json(['success' => false, 'message' => $exception->getMessage()], 400);
} catch (Throwable $exception) {
    ApiBootstrap::respondInternalError('legacy_orders_endpoint', $exception);
}
