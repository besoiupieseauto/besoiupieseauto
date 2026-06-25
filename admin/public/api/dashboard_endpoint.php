<?php

declare(strict_types=1);

require_once __DIR__ . '/_autoload.php';

use Evasystem\Controllers\Dashboard\DashboardSnapshotService;
use Evasystem\Core\Bootstrap\ApiBootstrap;
ApiBootstrap::bootJsonApi();

try {
    ApiBootstrap::requireAuthenticatedSession();

    header('Cache-Control: private, max-age=30');

    $forceRefresh = false;
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $forceRefresh = isset($_GET['refresh']) && $_GET['refresh'] === '1';
    } else {
        $payload = json_decode(file_get_contents('php://input') ?: '', true);
        if (is_array($payload) && !empty($payload['refresh'])) {
            $forceRefresh = true;
        }
    }

    $resolved = DashboardSnapshotService::resolve($forceRefresh);

    if (!$forceRefresh && $resolved['source'] === 'snapshot' && $resolved['stale']) {
        register_shutdown_function(static function (): void {
            try {
                DashboardSnapshotService::refresh();
            } catch (Throwable) {
                // ignoră — următorul cron/refresh va reîncerca
            }
        });
    }

    ApiBootstrap::json([
        'success' => true,
        'message' => $resolved['source'] === 'snapshot' ? 'Dashboard din snapshot.' : 'Dashboard regenerat.',
        'source' => $resolved['source'],
        'stale' => $resolved['stale'],
        'data' => $resolved['data'],
    ]);
} catch (Throwable $exception) {
    ApiBootstrap::respondInternalError('dashboard_endpoint', $exception);
}
