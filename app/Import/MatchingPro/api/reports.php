<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$id = trim((string) ($_GET['id'] ?? ''));
$limit = max(1, min(50, (int) ($_GET['limit'] ?? 15)));
$maxProducts = max(1, min(500, (int) ($_GET['sample'] ?? 100)));

import_motor_api_run(static function () use ($id, $limit, $maxProducts): void {
    if ($id !== '') {
        $report = import_read_report($id, $maxProducts);
        if ($report === null) {
            import_json_response(['success' => false, 'error' => 'Raport inexistent: ' . $id], 404);
        }
        import_json_response(['success' => true, 'report' => $report]);
    }

    import_json_response([
        'success' => true,
        'reports' => import_list_reports($limit),
    ]);
}, 'reports.php');
