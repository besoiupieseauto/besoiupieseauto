<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    import_json_response(['success' => false, 'error' => 'GET obligatoriu'], 405);
}

import_motor_api_run(static function (): void {
    $lines = max(20, min(200, (int) ($_GET['lines'] ?? 80)));
    $progress = import_read_cron_progress();
    $logLines = import_tail_log($lines, is_string($progress['started_at'] ?? null) ? $progress['started_at'] : null);
    $running = ($progress['status'] ?? '') === 'running';

    import_json_response([
        'success' => true,
        'running' => $running,
        'progress' => $progress,
        'log_lines' => $logLines,
        'cron' => import_cron_status(false),
    ]);
}, 'cron-progress.php');
