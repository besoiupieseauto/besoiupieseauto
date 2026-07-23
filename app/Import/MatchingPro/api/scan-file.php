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

if (!isset($_FILES['file']) || !is_array($_FILES['file'])) {
    import_json_response(['success' => false, 'error' => 'Lipsește câmpul file'], 400);
}

$upload = $_FILES['file'];
if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    import_json_response(['success' => false, 'error' => 'Upload eșuat'], 400);
}

$original = (string) ($upload['name'] ?? 'upload.csv');
$ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
if (!in_array($ext, ['csv', 'txt'], true)) {
    import_json_response(['success' => false, 'error' => 'Accept doar CSV/TXT'], 400);
}

if (!is_dir(IMPORT_UPLOADS_TEMP) && !mkdir(IMPORT_UPLOADS_TEMP, 0777, true) && !is_dir(IMPORT_UPLOADS_TEMP)) {
    import_json_response(['success' => false, 'error' => 'Nu pot crea temp'], 500);
}

$safe = preg_replace('/[^A-Za-z0-9._-]+/', '_', $original) ?: 'upload.csv';
$dest = IMPORT_UPLOADS_TEMP . DIRECTORY_SEPARATOR . uniqid('scan_', true) . '_' . $safe;

if (!move_uploaded_file((string) $upload['tmp_name'], $dest)) {
    import_json_response(['success' => false, 'error' => 'Nu pot salva fișierul'], 500);
}

$sample = isset($_POST['sample']) && $_POST['sample'] !== '' ? max(1, (int) $_POST['sample']) : null;
$supplier = trim((string) ($_POST['supplier'] ?? ''));
$force = filter_var($_POST['force'] ?? false, FILTER_VALIDATE_BOOLEAN);

$args = ['scan', '--file', $dest, '--no-archive'];
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

$result = import_run_python($args, 540);

@unlink($dest);

if ($result['json'] === null) {
    import_json_response([
        'success' => false,
        'error' => 'Python nu a returnat JSON valid',
        'raw' => mb_substr($result['raw'], 0, 2000),
    ], 500);
}

$items = $result['json'];
$last = is_array($items) && $items !== [] ? $items[array_key_last($items)] : null;
$payload = is_array($last) ? ($last['payload'] ?? $last) : null;

if (is_array($payload)) {
    $payload = import_enrich_match_payload_images($payload);
}

import_json_response([
    'success' => $result['exit_code'] === 0,
    'payload' => $payload,
    'result' => $items,
]);
