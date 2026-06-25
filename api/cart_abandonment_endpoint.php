<?php

declare(strict_types=1);

require_once __DIR__ . '/../system/public-api-init.php';
require_once __DIR__ . '/../admin/vendor/autoload.php';

use Evasystem\Services\CartAbandonmentService;

header('Content-Type: application/json; charset=utf-8');

try {
    if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Doar POST.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $payload = json_decode(file_get_contents('php://input') ?: '{}', true);
    if (!is_array($payload)) {
        throw new InvalidArgumentException('Payload invalid.');
    }

    $cart = $payload['cart'] ?? [];
    if (!is_array($cart) || $cart === []) {
        throw new InvalidArgumentException('Cos gol.');
    }

    $payload['session_id'] = session_id();
    $id = CartAbandonmentService::upsertLead($payload);

    echo json_encode([
        'success' => true,
        'message' => $id > 0 ? 'Lead salvat.' : 'Ignorat (fara contact).',
        'data' => ['id' => $id],
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $exception->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
