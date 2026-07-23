<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once BESOIU_LEGACY . '/public-api-init.php';
require_once BESOIU_BACKEND . '/vendor/autoload.php';

use Besoiu\Services\CartAbandonmentService;

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

    $helper = BESOIU_LEGACY . '/ai_action_events.php';
    if (is_file($helper)) {
        require_once $helper;
        if (function_exists('ai_action_event_record')) {
            ai_action_event_record('client', 'cart_abandon', (string) ($payload['phone'] ?? ''), [
                'items' => count($cart),
                'total' => $payload['total'] ?? null,
            ]);
        }
    }

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
