<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

@ini_set('memory_limit', '512M');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    import_json_response(['success' => false, 'error' => 'Metoda GET obligatorie'], 405);
}

$supplier = trim((string) ($_GET['supplier'] ?? ''));
$filename = trim((string) ($_GET['filename'] ?? ''));
$sample = isset($_GET['sample']) && $_GET['sample'] !== ''
    ? max(1, min(30, (int) $_GET['sample']))
    : 8;
$forceSupplier = trim((string) ($_GET['force_supplier'] ?? ''));

if ($supplier === '' || $filename === '') {
    import_json_response(['success' => false, 'error' => 'Lipsesc supplier și filename'], 400);
}

if (!import_motor_supplier_is_allowed(strtolower($supplier))) {
    import_json_response([
        'success' => false,
        'error' => 'Furnizor neînregistrat sau blocat în modulul Furnizori.',
        'code' => 'supplier_not_registered',
    ], 403);
}

$path = import_resolve_stored_file($supplier, $filename);
if ($path === null) {
    import_json_response(['success' => false, 'error' => 'Fișier negăsit în bibliotecă'], 404);
}

$args = ['inspect', '--file', $path, '--sample', (string) $sample];
if ($forceSupplier !== '') {
    $args[] = '--supplier';
    $args[] = $forceSupplier;
}

$result = import_run_python($args, 240);

if ($result['json'] === null) {
    import_json_response([
        'success' => false,
        'error' => 'Python nu a returnat JSON valid',
        'raw' => mb_substr($result['raw'], 0, 2000),
        'command' => $result['command'] ?? null,
    ], 500);
}

import_json_response([
    'success' => $result['exit_code'] === 0,
    'payload' => import_enrich_inspect_payload($result['json']),
    'ollama_available' => import_ollama_available(),
    'ollama_model' => import_ollama_model(),
    'command' => $result['command'] ?? null,
]);
