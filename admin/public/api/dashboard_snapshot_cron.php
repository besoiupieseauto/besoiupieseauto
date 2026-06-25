<?php

declare(strict_types=1);

require_once __DIR__ . '/_autoload.php';

use Evasystem\Controllers\Dashboard\DashboardSnapshotService;
use Evasystem\Core\Bootstrap\ApiBootstrap;

/**
 * Cron HTTP — refresh snapshot dashboard (fără sesiune admin).
 * POST + header X-Dashboard-Cron-Key (preferat) sau GET ?key= (compat cron legacy).
 */
ApiBootstrap::bootJsonApi();
ApiBootstrap::requireHttpMethod('POST', 'GET');

try {
    ApiBootstrap::requireSharedSecret('DASHBOARD_CRON_KEY', 'X-Dashboard-Cron-Key', true);

    $started = microtime(true);
    $data = DashboardSnapshotService::refresh(300, false);
    $elapsed = round((microtime(true) - $started) * 1000);

    ApiBootstrap::json([
        'success' => true,
        'message' => 'Snapshot dashboard regenerat.',
        'generated_at' => $data['generated_at'] ?? null,
        'elapsed_ms' => $elapsed,
    ]);
} catch (Throwable $e) {
    ApiBootstrap::respondInternalError('dashboard_snapshot_cron', $e);
}
