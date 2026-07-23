<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $state = import_read_scan_checkpoints();
    $files = $state['files'] ?? [];
    if (!is_array($files)) {
        $files = [];
    }

    $filterRaw = trim((string) ($_GET['files'] ?? ''));
    if ($filterRaw !== '') {
        $wanted = [];
        foreach (explode(',', $filterRaw) as $part) {
            $part = trim(urldecode($part));
            if ($part !== '') {
                $wanted[$part] = true;
            }
        }
        if ($wanted !== []) {
            $files = array_intersect_key($files, $wanted);
        }
    }

    $list = [];
    foreach ($files as $key => $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $list[] = array_merge(['key' => (string) $key], $entry);
    }
    usort($list, static fn (array $a, array $b): int => strcmp((string) ($b['updated_at'] ?? ''), (string) ($a['updated_at'] ?? '')));

    import_json_response([
        'success' => true,
        'files' => $files,
        'entries' => $list,
    ]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    import_json_response(['success' => false, 'error' => 'GET sau POST'], 405);
}

import_motor_api_run(static function (): void {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '', true);
    if (!is_array($data)) {
        $data = $_POST;
    }

    $action = trim((string) ($data['action'] ?? 'set_offset'));
    $supplier = trim((string) ($data['supplier'] ?? ''));
    $filename = trim((string) ($data['filename'] ?? ''));
    $offset = max(0, (int) ($data['offset'] ?? 0));

    if ($action === 'reset_all') {
        $items = $data['files'] ?? [];
        if (!is_array($items) || $items === []) {
            import_json_response(['success' => false, 'error' => 'Lipsește lista files'], 422);
        }
        $updated = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $sup = trim((string) ($item['supplier'] ?? ''));
            $file = trim((string) ($item['filename'] ?? ''));
            if ($sup === '' || $file === '') {
                continue;
            }
            $updated[] = import_reset_scan_checkpoint($sup, $file, 'reset_all');
        }
        import_json_response(['success' => true, 'updated' => $updated]);
    }

    if ($supplier === '' || $filename === '') {
        import_json_response(['success' => false, 'error' => 'Furnizor și fișier obligatorii'], 422);
    }

    if (!import_motor_supplier_is_allowed(strtolower($supplier))) {
        import_json_response(['success' => false, 'error' => 'Furnizor invalid'], 403);
    }

    if ($action === 'reset') {
        $entry = import_reset_scan_checkpoint($supplier, $filename, 'reset');
        import_json_response(['success' => true, 'entry' => $entry]);
    }

    if ($action === 'set_offset') {
        $entry = import_set_scan_checkpoint_offset($supplier, $filename, $offset, 'manual');
        import_json_response(['success' => true, 'entry' => $entry]);
    }

    import_json_response(['success' => false, 'error' => 'Acțiune invalidă'], 422);
}, 'scan-checkpoints.php');
