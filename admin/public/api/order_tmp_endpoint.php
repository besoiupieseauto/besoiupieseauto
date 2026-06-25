<?php

declare(strict_types=1);

require_once __DIR__ . '/_autoload.php';

use Config\Database;
use Evasystem\Services\Orders\OrderTmpService;
use Evasystem\Services\Orders\SupplierCartToTmpImporter;
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
    $tmpService = new OrderTmpService($legacyPdo);
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    $action = trim((string) ($_GET['action'] ?? ''));

    if ($method === 'GET') {
        if ($action === '' || $action === 'list') {
            $data = $tmpService->listProducts($sessionId);
            ApiBootstrap::json(['success' => true] + $data);
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
            $tmpService->addProduct($sessionId, $payload);
            $data = $tmpService->listProducts($sessionId);
            ApiBootstrap::json(['success' => true, 'message' => 'Produs adăugat în tmp.', ] + $data);

        case 'delete':
            $tmpService->deleteProduct($sessionId, (int) ($payload['id'] ?? $payload['id_tmp'] ?? 0));
            $data = $tmpService->listProducts($sessionId);
            ApiBootstrap::json(['success' => true, 'message' => 'Linie ștearsă din tmp.'] + $data);

        case 'update':
            $tmpService->updateProduct($sessionId, (int) ($payload['id'] ?? $payload['id_tmp'] ?? 0), $payload);
            $data = $tmpService->listProducts($sessionId);
            ApiBootstrap::json(['success' => true, 'message' => 'Linie tmp actualizată.'] + $data);

        case 'clear':
            $tmpService->clear($sessionId);
            ApiBootstrap::json(['success' => true, 'message' => 'Coș tmp golit.', 'products' => [], 'total' => 0]);

        case 'import_supplier_cart':
            $userId = (int) $_SESSION['user_id'];
            $cartService = new SupplierCartService($legacyPdo);
            $cart = $cartService->getCart($userId);
            if ($cart === []) {
                throw new RuntimeException('Coșul furnizori este gol.');
            }
            $clearFirst = !isset($payload['clear_first']) || !empty($payload['clear_first']);
            $importer = new SupplierCartToTmpImporter($legacyPdo, $tmpService);
            $result = $importer->importFromSupplierCart($sessionId, $cart, $clearFirst);
            $data = $tmpService->listProducts($sessionId);
            ApiBootstrap::json([
                'success' => true,
                'message' => 'Import din coș furnizori în tmp finalizat.',
                'imported' => $result['imported'],
            ] + $data);

        default:
            throw new InvalidArgumentException('Acțiune necunoscută: ' . $action);
    }
} catch (InvalidArgumentException $exception) {
    ApiBootstrap::json(['success' => false, 'message' => $exception->getMessage()], 400);
} catch (Throwable $exception) {
    ApiBootstrap::respondInternalError('order_tmp_endpoint', $exception);
}
