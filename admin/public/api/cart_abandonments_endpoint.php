<?php

declare(strict_types=1);

require_once __DIR__ . '/_autoload.php';

use Evasystem\Core\Bootstrap\ApiBootstrap;
use Evasystem\Services\CartAbandonmentService;

ApiBootstrap::bootJsonApi();

try {
    ApiBootstrap::requireAuthenticatedSession();

    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

    if ($method === 'GET') {
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $status = trim((string) ($_GET['status'] ?? 'open'));
        $result = CartAbandonmentService::listForAdmin($page, 20, $status);

        ApiBootstrap::json([
            'success' => true,
            'message' => 'Lista coșuri abandonate.',
            'data' => $result,
        ]);
    }

    if ($method !== 'POST') {
        ApiBootstrap::json(['success' => false, 'message' => 'Metodă nepermisă.'], 405);
    }

    $payload = json_decode(file_get_contents('php://input') ?: '{}', true);
    if (!is_array($payload)) {
        throw new InvalidArgumentException('Payload invalid.');
    }

    $action = trim((string) ($payload['action'] ?? ''));
    if ($action === 'set_status') {
        $ok = CartAbandonmentService::updateStatus(
            (int) ($payload['id'] ?? 0),
            (string) ($payload['status'] ?? ''),
            (string) ($payload['notes'] ?? '')
        );
        ApiBootstrap::json([
            'success' => $ok,
            'message' => $ok ? 'Status actualizat.' : 'Nu s-a putut actualiza.',
        ]);
    }

    ApiBootstrap::json(['success' => false, 'message' => 'Acțiune necunoscută.'], 400);
} catch (Throwable $exception) {
    ApiBootstrap::respondInternalError('cart_abandonments_endpoint', $exception);
}
