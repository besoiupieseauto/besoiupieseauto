<?php

declare(strict_types=1);

require_once __DIR__ . '/_autoload.php';

use Evasystem\Core\Bootstrap\ApiBootstrap;
use Evasystem\Services\AiTokenUsageService;

ApiBootstrap::bootJsonApi();

try {
    ApiBootstrap::requireAuthenticatedSession();

    $service = new AiTokenUsageService();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $payload = [
            'limit' => $_GET['limit'] ?? 25,
            'offset' => $_GET['offset'] ?? 0,
            'provider' => $_GET['provider'] ?? '',
            'date_from' => $_GET['date_from'] ?? '',
            'date_to' => $_GET['date_to'] ?? '',
        ];
        $result = $service->list($payload);
        ApiBootstrap::json([
            'success' => true,
            'count' => count($result['items']),
            'total' => $result['total'],
            'stats' => $result['stats'],
            'alerts' => $result['alerts'],
            'thresholds' => $result['thresholds'],
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
                'message' => 'Consum tokeni încărcat.',
                'count' => count($result['items']),
                'total' => $result['total'],
                'stats' => $result['stats'],
                'alerts' => $result['alerts'],
                'thresholds' => $result['thresholds'],
                'data' => $result['items'],
            ]);

        case 'stats':
            $result = $service->stats();
            ApiBootstrap::json([
                'success' => true,
                'message' => 'Statistici tokeni încărcate.',
                'stats' => $result['stats'],
                'alerts' => $result['alerts'],
                'thresholds' => $result['thresholds'],
            ]);

        case 'save_threshold':
            $service->saveThreshold($payload);
            $refreshed = $service->stats();
            ApiBootstrap::json([
                'success' => true,
                'message' => 'Prag alertă salvat.',
                'stats' => $refreshed['stats'],
                'alerts' => $refreshed['alerts'],
                'thresholds' => $refreshed['thresholds'],
            ]);

        default:
            ApiBootstrap::json(['success' => false, 'message' => 'Acțiune necunoscută.'], 422);
    }
} catch (Throwable $exception) {
    ApiBootstrap::respondInternalError('ai_tokens_endpoint', $exception);
}
