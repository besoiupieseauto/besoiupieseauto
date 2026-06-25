<?php
/**
 * robot/toggle2.php — control run/stop pentru module robot (FB, parser, etc.)
 */
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth_guard.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$target = isset($_GET['target']) ? (string) $_GET['target'] : '';
$mode   = isset($_GET['set']) ? (string) $_GET['set'] : '';

if ($mode !== 'run' && $mode !== 'stop') {
    echo json_encode(['status' => 'error', 'message' => 'Mod invalid (run|stop).']);
    exit;
}

$statusMap = [
    'fb'     => __DIR__ . '/data/status_fb.txt',
    'parser' => __DIR__ . '/data/status.txt',
];

$fileName = $statusMap[$target] ?? (__DIR__ . '/data/status_' . preg_replace('/[^a-z0-9_]/', '', $target) . '.txt');

if ($target === '' || !preg_match('/^[a-z0-9_]{1,20}$/', $target)) {
    echo json_encode(['status' => 'error', 'message' => 'Target invalid.']);
    exit;
}

$dataDir = robot_data_dir();
if (!is_dir($dataDir)) {
    @mkdir($dataDir, 0775, true);
}

if (@file_put_contents($fileName, $mode) === false) {
    echo json_encode(['status' => 'error', 'message' => 'Eroare la scriere status.']);
    exit;
}

echo json_encode([
    'status' => 'success',
    'message' => "Modulul {$target} setat pe {$mode}",
    'current_mode' => $mode,
    'file' => basename($fileName),
]);
