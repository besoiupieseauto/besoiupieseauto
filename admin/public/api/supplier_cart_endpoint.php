<?php

declare(strict_types=1);

require_once __DIR__ . '/_autoload.php';

use Config\Database;
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

    $userId = (int) $_SESSION['user_id'];
    $service = new SupplierCartService(SupplierSearchConfig::legacyPdo());

    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    $action = trim((string) ($_GET['action'] ?? ''));

    if ($method === 'GET') {
        if ($action === '' || $action === 'show') {
            $result = $service->show($userId);
            ApiBootstrap::json(['success' => true] + $result);
        }
        if ($action === 'count') {
            $cart = $service->getCart($userId);
            ApiBootstrap::json(['success' => true, 'summary' => $service->summarizeCart($cart)]);
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
        case 'add':
            $result = $service->addItem($userId, $payload);
            ApiBootstrap::json(['success' => true] + $result);

        case 'update':
            $result = $service->updateQty(
                $userId,
                (string) ($payload['supplier'] ?? ''),
                (string) ($payload['key'] ?? ''),
                (int) ($payload['qty'] ?? 1)
            );
            ApiBootstrap::json(['success' => true] + $result);

        case 'remove':
            $result = $service->removeItem(
                $userId,
                (string) ($payload['supplier'] ?? ''),
                (string) ($payload['key'] ?? '')
            );
            ApiBootstrap::json(['success' => true] + $result);

        default:
            throw new InvalidArgumentException('Acțiune necunoscută: ' . $action);
    }
} catch (InvalidArgumentException $exception) {
    ApiBootstrap::json(['success' => false, 'message' => $exception->getMessage()], 400);
} catch (Throwable $exception) {
    ApiBootstrap::respondInternalError('supplier_cart_endpoint', $exception);
}
