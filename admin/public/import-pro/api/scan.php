<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

@set_time_limit(600);
@ini_set('max_execution_time', '600');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    import_json_response(['success' => false, 'error' => 'Metoda POST obligatorie'], 405);
}

$mode = trim((string) ($_POST['mode'] ?? 'uploads'));
$force = filter_var($_POST['force'] ?? false, FILTER_VALIDATE_BOOLEAN);
$sample = isset($_POST['sample']) && $_POST['sample'] !== '' ? max(1, (int) $_POST['sample']) : null;
$supplier = trim((string) ($_POST['supplier'] ?? ''));

$args = [];

if ($mode === 'cron') {
    $args = ['cron'];
    if ($force) {
        $args[] = '--force';
    }
    if ($sample !== null) {
        $args[] = '--sample';
        $args[] = (string) $sample;
    }
} elseif ($mode === 'demo' || $mode === 'cron_test') {
    // Test cron pe fișiere reale din admin/storage/supplier_feeds/ (nu catalog demo).
    $testBatch = $sample !== null ? max(1, min(5000, $sample)) : 10;
    import_write_cron_control([
        'mode' => 'running',
        'batch_size' => $testBatch,
        'display_sample' => min(200, max(1, $testBatch)),
    ]);
    $args = ['cron', '--force', '--sample', (string) $testBatch];
} else {
    $args = ['scan', '--uploads'];
    if ($force) {
        $args[] = '--force';
    }
    if ($sample !== null) {
        $args[] = '--sample';
        $args[] = (string) $sample;
    }
    if ($supplier !== '') {
        $args[] = '--supplier';
        $args[] = $supplier;
    }
}

$result = import_run_python($args, 540);

if ($result['json'] === null) {
    import_json_response([
        'success' => false,
        'mode' => $mode,
        'error' => 'Python nu a returnat JSON valid',
        'exit_code' => $result['exit_code'],
        'command' => $result['command'],
        'raw' => mb_substr($result['raw'], 0, 4000),
    ], 500);
}

$payload = null;
$summary = null;
if ($mode === 'demo' || $mode === 'cron_test' || $mode === 'cron') {
    $summary = is_array($result['json']) ? $result['json'] : null;
} elseif ($mode === 'uploads') {
    $items = $result['json'];
    if (is_array($items) && $items !== []) {
        $last = $items[array_key_last($items)];
        $payload = is_array($last) ? ($last['payload'] ?? $last) : null;
    }
}

import_json_response([
    'success' => $result['exit_code'] === 0,
    'mode' => $mode === 'demo' ? 'cron_test' : $mode,
    'payload' => $payload,
    'summary' => $summary,
    'exit_code' => $result['exit_code'],
]);
