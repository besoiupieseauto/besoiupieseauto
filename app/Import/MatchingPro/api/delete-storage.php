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

$action = trim((string) ($_POST['action'] ?? 'file'));
$supplier = trim((string) ($_POST['supplier'] ?? ''));
$filename = trim((string) ($_POST['filename'] ?? ''));

if ($action === 'supplier') {
    if ($supplier === '') {
        import_json_response(['success' => false, 'error' => 'Furnizor obligatoriu'], 400);
    }
    $result = import_delete_supplier_files($supplier);
    import_json_response($result, ($result['success'] ?? false) ? 200 : 500);
}

if ($action === 'selected') {
    $raw = $_POST['files'] ?? '[]';
    $list = is_string($raw) ? json_decode($raw, true) : $raw;
    if (!is_array($list) || $list === []) {
        import_json_response(['success' => false, 'error' => 'Niciun fișier selectat'], 400);
    }
    $deleted = [];
    $errors = [];
    foreach ($list as $item) {
        if (!is_array($item)) {
            continue;
        }
        $sup = trim((string) ($item['supplier'] ?? ''));
        $file = trim((string) ($item['filename'] ?? ''));
        $res = import_delete_stored_file($sup, $file);
        if ($res['success'] ?? false) {
            $deleted[] = $res['deleted'];
        } else {
            $errors[] = ['supplier' => $sup, 'filename' => $file, 'error' => $res['error'] ?? ''];
        }
    }
    import_json_response([
        'success' => $deleted !== [],
        'deleted' => $deleted,
        'errors' => $errors,
    ], $deleted !== [] ? 200 : 404);
}

if ($supplier === '' || $filename === '') {
    import_json_response(['success' => false, 'error' => 'Furnizor și fișier obligatorii'], 400);
}

$result = import_delete_stored_file($supplier, $filename);
import_json_response($result, ($result['success'] ?? false) ? 200 : 404);
