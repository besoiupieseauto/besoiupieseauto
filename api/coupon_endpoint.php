<?php
declare(strict_types=1);

require_once __DIR__ . '/../system/public-api-init.php';

ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

require_once __DIR__ . '/../system/shop-cart.php';
require_once __DIR__ . '/../system/shop-coupon.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = shop_db_bootstrap();
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

    if ($method === 'GET') {
        $code = trim((string) ($_GET['code'] ?? $_GET['coupon'] ?? ''));
        $subtotal = round(max(0, (float) ($_GET['subtotal'] ?? 0)), 2);

        if ($code === '') {
            throw new InvalidArgumentException('Codul promotional lipseste.');
        }

        $result = shop_coupon_validate($pdo, $code, $subtotal);
        echo json_encode([
            'success' => $result['valid'],
            'message' => $result['message'],
            'data' => [
                'code' => shop_coupon_normalize_code($code),
                'discount' => round((float) ($result['discount'] ?? 0), 2),
                'total_after' => round((float) ($result['total_after'] ?? $subtotal), 2),
                'subtotal' => $subtotal,
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

    $code = trim((string) ($payload['code'] ?? $payload['coupon_code'] ?? ''));
    $subtotal = round(max(0, (float) ($payload['subtotal'] ?? 0)), 2);

    if ($code === '') {
        throw new InvalidArgumentException('Codul promotional lipseste.');
    }

    $result = shop_coupon_validate($pdo, $code, $subtotal);
    echo json_encode([
        'success' => $result['valid'],
        'message' => $result['message'],
        'data' => [
            'code' => shop_coupon_normalize_code($code),
            'discount' => round((float) ($result['discount'] ?? 0), 2),
            'total_after' => round((float) ($result['total_after'] ?? $subtotal), 2),
            'subtotal' => $subtotal,
        ],
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $exception->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
