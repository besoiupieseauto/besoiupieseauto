<?php

declare(strict_types=1);

require_once __DIR__ . '/_autoload.php';

use Besoiu\Core\Bootstrap\ApiBootstrap;
use Besoiu\Modules\SupplierSearch\Service\SupplierCartService;
use Besoiu\Modules\SupplierSearch\Service\SupplierSearchConfig;
use Besoiu\Services\Orders\OrderTmpService;
use Besoiu\Services\Orders\SupplierCartToTmpImporter;

$config = ApiBootstrap::bootJsonApi();
ApiBootstrap::registerJsonFatalGuard('order_tmp');

try {
    ApiBootstrap::requireAuthenticatedSession();
    ApiBootstrap::beginBoundedJsonWork(30);

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
    if ($action !== 'import_supplier_cart') {
        throw new InvalidArgumentException('Acțiune necunoscută.');
    }

    $payload = json_decode(file_get_contents('php://input') ?: '', true);
    if (!is_array($payload)) {
        $payload = $_POST;
    }

    $pdo = SupplierSearchConfig::legacyPdo();
    $cart = (new SupplierCartService($pdo))->getCart($userId);
    $cart = order_tmp_filter_selected_cart($cart, $payload['order_item_keys'] ?? null);
    if ($cart === []) {
        throw new InvalidArgumentException('Niciun articol de importat. Selectează linii din coș.');
    }
    $result = (new SupplierCartToTmpImporter($pdo, new OrderTmpService($pdo)))
        ->importFromSupplierCart(session_id(), $cart, (bool) ($payload['clear_first'] ?? true));

    ApiBootstrap::json([
        'success' => true,
        'imported' => $result['imported'],
        'products' => $result['product_ids'],
    ]);
} catch (InvalidArgumentException $exception) {
    ApiBootstrap::json(['success' => false, 'message' => $exception->getMessage()], 400);
} catch (Throwable $exception) {
    ApiBootstrap::respondInternalError('order_tmp_endpoint', $exception);
}

/**
 * @param array<string, array<string, array<string, mixed>>> $cart
 * @param mixed $keysFilter
 * @return array<string, array<string, array<string, mixed>>>
 */
function order_tmp_filter_selected_cart(array $cart, mixed $keysFilter): array
{
    if (!is_array($keysFilter) || $keysFilter === []) {
        return $cart;
    }

    $allowed = [];
    foreach ($keysFilter as $entry) {
        if (is_string($entry) && str_contains($entry, '|')) {
            [$supplier, $key] = explode('|', $entry, 2);
            $allowed[strtolower(trim($supplier)) . '|' . trim($key)] = true;
        }
    }
    if ($allowed === []) {
        return $cart;
    }

    $filtered = [];
    foreach ($cart as $supplier => $items) {
        if (!is_array($items)) {
            continue;
        }
        foreach ($items as $key => $item) {
            if (!is_array($item)) {
                continue;
            }
            if (isset($allowed[strtolower((string) $supplier) . '|' . (string) $key])) {
                $filtered[$supplier][$key] = $item;
            }
        }
    }

    return $filtered;
}
