<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    import_json_response(['success' => false, 'error' => 'POST obligatoriu'], 405);
}

import_motor_api_run(static function (): void {
    $action = trim((string) ($_POST['action'] ?? ''));
    $clearCache = filter_var($_POST['clear_cache'] ?? false, FILTER_VALIDATE_BOOLEAN);

    switch ($action) {
        case 'start':
            import_write_cron_control(['mode' => 'running']);
            import_clear_stop_request_if_exists();
            import_json_response([
                'success' => true,
                'mode' => 'running',
                'message' => 'Cron activ — scanare permisă.',
                'control' => import_read_cron_control(),
            ]);
            break;

        case 'pause':
            import_write_cron_control(['mode' => 'paused']);
            import_json_response([
                'success' => true,
                'mode' => 'paused',
                'message' => 'Cron pe pauză — progresul rămâne salvat.',
                'control' => import_read_cron_control(),
            ]);
            break;

        case 'resume':
            import_write_cron_control(['mode' => 'running']);
            import_json_response([
                'success' => true,
                'mode' => 'running',
                'message' => 'Cron reluat din pauză.',
                'control' => import_read_cron_control(),
            ]);
            break;

        case 'stop':
            $kill = import_stop_cron($clearCache);
            import_write_cron_control(['mode' => 'stopped']);
            import_json_response([
                'success' => true,
                'mode' => 'stopped',
                'message' => 'Cron oprit definitiv — procese terminate.',
                'killed_pids' => $kill['killed_pids'],
                'cache_cleared' => $kill['cache_cleared'],
                'control' => import_read_cron_control(),
                'progress' => $kill['progress'],
            ]);
            break;

        default:
            import_json_response([
                'success' => true,
                'control' => import_read_cron_control(),
                'progress' => import_read_cron_progress(),
            ]);
    }
}, 'cron-control.php');

function import_clear_stop_request_if_exists(): void
{
    if (class_exists(\Besoiu\Services\Scan\ScanService::class)) {
        \Besoiu\Services\Scan\ScanService::clearStopRequest();
    }
}
