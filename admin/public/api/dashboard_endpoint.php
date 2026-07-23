<?php
declare(strict_types=1);

/**
 * Dashboard overview — folosit de modules/dashboard (refresh live).
 */
require_once __DIR__ . '/_autoload.php';

use Besoiu\Core\Bootstrap\ApiBootstrap;
use Besoiu\Services\DashboardService;

ApiBootstrap::bootJsonApi();
ApiBootstrap::registerJsonFatalGuard('dashboard_endpoint');

try {
    ApiBootstrap::requireAuthenticatedSession();
    ApiBootstrap::beginBoundedJsonWork(45);

    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    $payload = [];
    if ($method === 'POST') {
        $decoded = json_decode(file_get_contents('php://input') ?: '', true);
        $payload = is_array($decoded) ? $decoded : ($_POST ?: []);
    } else {
        $payload = $_GET ?: [];
    }

    $action = trim((string) ($payload['type_product'] ?? $payload['action'] ?? 'overview'));
    if ($action === '') {
        $action = 'overview';
    }

    if ($action !== 'overview') {
        ApiBootstrap::json([
            'success' => false,
            'message' => 'Acțiune necunoscută pentru dashboard: ' . $action,
        ], 422);
    }

    $forceRefresh = !empty($payload['refresh']) || !empty($payload['force']);
    $data = (new DashboardService())->overview($forceRefresh);

    ApiBootstrap::json([
        'success' => true,
        'message' => 'Dashboard actualizat.',
        'source' => $forceRefresh ? 'live' : 'cache',
        'data' => $data,
    ]);
} catch (Throwable $e) {
    ApiBootstrap::respondInternalError('dashboard_endpoint', $e);
}
