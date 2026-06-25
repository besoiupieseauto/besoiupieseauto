<?php

declare(strict_types=1);

require_once __DIR__ . '/_autoload.php';

use Config\Database;
use Evasystem\Services\Orders\InternalOrderService;
use Evasystem\Services\Orders\OrderTmpService;
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
    $orderService = new InternalOrderService($legacyPdo, $tmpService);

    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    $action = trim((string) ($_GET['action'] ?? ''));

    if ($method === 'GET') {
        if ($action === '' || $action === 'meta') {
            ApiBootstrap::json([
                'success' => true,
                'statuses' => InternalOrderService::statusOptions(),
                'locations' => InternalOrderService::locationOptions(),
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
        $action = trim((string) ($payload['action'] ?? 'create_from_tmp'));
    }

    if ($action === 'create_from_tmp') {
        $result = $orderService->createFromTmp($sessionId, $userId, $payload);
        ApiBootstrap::json([
            'success' => true,
            'message' => 'Comanda internă a fost creată cu succes.',
            'order' => $result,
        ]);
    }

    throw new InvalidArgumentException('Acțiune necunoscută: ' . $action);
} catch (InvalidArgumentException $exception) {
    ApiBootstrap::json(['success' => false, 'message' => $exception->getMessage()], 400);
} catch (Throwable $exception) {
    ApiBootstrap::respondInternalError('internal_order_endpoint', $exception);
}
