<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

@ini_set('memory_limit', '768M');
@set_time_limit(600);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    import_json_response(['success' => false, 'error' => 'POST obligatoriu'], 405);
}

$supplier = trim((string) ($_POST['supplier'] ?? ''));
$filename = trim((string) ($_POST['filename'] ?? ''));
$sample = max(1, (int) ($_POST['sample'] ?? 20));
$force = filter_var($_POST['force'] ?? false, FILTER_VALIDATE_BOOLEAN);
$onlyWithImage = filter_var($_POST['only_with_image'] ?? false, FILTER_VALIDATE_BOOLEAN);

import_motor_require_allowed_supplier(strtolower($supplier));

$path = import_resolve_stored_file($supplier, $filename);
if ($path === null) {
    import_json_response(['success' => false, 'error' => 'Fișier invalid'], 404);
}

$args = ['scan', '--file', $path, '--supplier', $supplier, '--no-archive'];
if ($force) {
    $args[] = '--force';
}
$scanSample = $onlyWithImage ? min(2000, max($sample * 15, $sample)) : $sample;
$args[] = '--sample';
$args[] = (string) $scanSample;

$result = import_run_python($args, 540);
if ($result['json'] === null) {
    import_json_response([
        'success' => false,
        'error' => 'Python nu a returnat JSON',
        'raw' => mb_substr($result['raw'], 0, 2000),
    ], 500);
}

$items = $result['json'];
$last = is_array($items) && $items !== [] ? $items[array_key_last($items)] : null;
$payload = is_array($last) ? ($last['payload'] ?? $last) : null;

if (is_array($payload)) {
    $payload = import_enrich_match_payload_images($payload);
    $payload = import_motor_apply_supplier_profile_to_payload($payload, strtolower($supplier));
}

if ($onlyWithImage && is_array($payload)) {
    $payload = import_filter_scan_payload_by_image($payload, $sample);
}

import_json_response([
    'success' => $result['exit_code'] === 0,
    'payload' => $payload,
    'onlyWithImage' => $onlyWithImage,
    'result' => $items,
    'source' => ['supplier' => $supplier, 'filename' => $filename],
]);
