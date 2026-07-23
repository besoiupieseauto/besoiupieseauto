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
    $clearCache = filter_var($_POST['clear_cache'] ?? true, FILTER_VALIDATE_BOOLEAN);
    $result = import_stop_cron($clearCache);
    import_write_cron_control(['mode' => 'stopped']);

    import_json_response([
        'success' => true,
        'message' => $clearCache
            ? 'Cron oprit și cache șters — fișierele apar din nou ca „nou”.'
            : 'Cron oprit.',
        'killed_pids' => $result['killed_pids'],
        'cache_cleared' => $result['cache_cleared'],
        'progress' => $result['progress'],
    ]);
}, 'cron-stop.php');
