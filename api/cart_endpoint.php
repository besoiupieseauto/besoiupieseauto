<?php
declare(strict_types=1);

require_once __DIR__ . '/../system/public-api-init.php';

ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

require_once __DIR__ . '/../system/shop-cart.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = shop_cart_bootstrap_db();
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    $action = trim((string) ($_GET['action'] ?? ''));

    if ($method === 'GET') {
        $items = shop_cart_get_items($pdo);
        echo json_encode([
            'success' => true,
            'data' => [
                'items' => $items,
                'count' => array_sum(array_map(static fn (array $item): int => (int) ($item['quantity'] ?? 0), $items)),
            ],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Metoda nepermisa.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $payload = json_decode(file_get_contents('php://input') ?: '{}', true);
    if (!is_array($payload)) {
        throw new InvalidArgumentException('Payload invalid.');
    }

    $action = trim((string) ($payload['action'] ?? $action));

    switch ($action) {
        case 'add':
            $randomId = trim((string) ($payload['randomn_id'] ?? $payload['product_id'] ?? ''));
            $quantity = max(1, (int) ($payload['quantity'] ?? 1));
            $result = shop_cart_add_item($pdo, $randomId, $quantity);
            break;

        case 'update':
            $randomId = trim((string) ($payload['randomn_id'] ?? $payload['product_id'] ?? ''));
            $quantity = max(0, (int) ($payload['quantity'] ?? 1));
            $result = shop_cart_update_quantity($pdo, $randomId, $quantity);
            break;

        case 'remove':
            $randomId = trim((string) ($payload['randomn_id'] ?? $payload['product_id'] ?? ''));
            $result = shop_cart_remove_item($pdo, $randomId);
            break;

        case 'clear':
            shop_cart_clear($pdo);
            $result = ['items' => [], 'count' => 0];
            break;

        case 'sync':
            $localItems = is_array($payload['items'] ?? null) ? $payload['items'] : [];
            $result = shop_cart_sync_from_client($pdo, $localItems);
            break;

        default:
            throw new InvalidArgumentException('Actiune cos necunoscuta.');
    }

    echo json_encode([
        'success' => true,
        'message' => 'Cos actualizat.',
        'data' => $result,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $exception->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
