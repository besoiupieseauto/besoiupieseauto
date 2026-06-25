<?php

declare(strict_types=1);

require_once __DIR__ . '/_autoload.php';

use Evasystem\Core\Bootstrap\ApiBootstrap;
use Evasystem\Services\SystemErrorsService;

ApiBootstrap::bootJsonApi();

try {
    ApiBootstrap::requireAuthenticatedSession();

    $service = new SystemErrorsService();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $payload = [
            'limit' => $_GET['limit'] ?? 100,
            'offset' => $_GET['offset'] ?? 0,
            'unresolved_only' => ($_GET['unresolved'] ?? '') === '1',
            'level' => $_GET['level'] ?? '',
            'channel' => $_GET['channel'] ?? '',
            'q' => $_GET['q'] ?? '',
            'date_from' => $_GET['date_from'] ?? '',
            'date_to' => $_GET['date_to'] ?? '',
        ];
        $result = $service->list($payload);
        ApiBootstrap::json([
            'success' => true,
            'count' => count($result['items']),
            'total' => $result['total'],
            'stats' => $result['stats'],
            'recent' => $result['recent'],
            'data' => $result['items'],
        ]);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        ApiBootstrap::json(['success' => false, 'message' => 'Metodă nepermisă.'], 405);
    }

    $payload = json_decode(file_get_contents('php://input') ?: '', true);
    if (!is_array($payload)) {
        throw new InvalidArgumentException('JSON invalid.');
    }

    $action = (string) ($payload['type_product'] ?? $payload['action'] ?? 'list');

    switch ($action) {
        case 'list':
            $result = $service->list($payload);
            ApiBootstrap::json([
                'success' => true,
                'message' => 'Jurnal erori încărcat.',
                'count' => count($result['items']),
                'total' => $result['total'],
                'stats' => $result['stats'],
                'recent' => $result['recent'],
                'data' => $result['items'],
            ]);
            break;

        case 'stats':
            ApiBootstrap::json([
                'success' => true,
                'stats' => $service->stats(),
            ]);
            break;

        case 'resolve':
            $id = (int) ($payload['id'] ?? 0);
            $resolved = !isset($payload['resolved']) || (bool) $payload['resolved'];
            if ($id <= 0) {
                throw new InvalidArgumentException('ID invalid.');
            }
            $ok = $service->markResolved($id, $resolved);
            ApiBootstrap::json([
                'success' => $ok,
                'message' => $ok ? 'Eroare marcată.' : 'Înregistrare negăsită.',
            ]);
            break;

        default:
            ApiBootstrap::json(['success' => false, 'message' => 'Acțiune necunoscută.'], 400);
    }
} catch (Throwable $exception) {
    ApiBootstrap::json([
        'success' => false,
        'message' => $exception->getMessage(),
    ], 500);
}
