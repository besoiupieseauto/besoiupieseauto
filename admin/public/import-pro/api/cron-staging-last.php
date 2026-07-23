<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/CronStagingJournal.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    import_json_response(['success' => false, 'error' => 'GET obligatoriu'], 405);
}

import_motor_api_run(static function (): void {
    $journal = CronStagingJournal::read();
    import_json_response([
        'success' => true,
        'journal' => $journal,
        'stats' => $journal['stats'] ?? [],
        'items' => $journal['items'] ?? [],
        'count' => count(is_array($journal['items'] ?? null) ? $journal['items'] : []),
    ]);
}, 'cron-staging-last.php');
