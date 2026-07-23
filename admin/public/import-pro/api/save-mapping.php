<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    import_json_response(['success' => false, 'error' => 'Metoda POST obligatorie'], 405);
}

$raw = file_get_contents('php://input');
$input = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
if (!is_array($input)) {
    $input = $_POST;
}

$supplier = preg_replace('/[^a-z0-9_]+/i', '', (string) ($input['supplier'] ?? '')) ?? '';
$columns = $input['columns'] ?? null;
$extraCodes = $input['extra_codes'] ?? null;
$matchCodeOnly = array_key_exists('match_code_only', $input)
    ? filter_var($input['match_code_only'], FILTER_VALIDATE_BOOLEAN)
    : null;
$clear = filter_var($input['clear'] ?? false, FILTER_VALIDATE_BOOLEAN);

if ($supplier === '') {
    import_json_response(['success' => false, 'error' => 'Lipsește supplier'], 400);
}

$path = IMPORT_CONFIG . DIRECTORY_SEPARATOR . 'mapping_overrides.json';
$overrides = [];
if (is_file($path)) {
    $decoded = json_decode((string) file_get_contents($path), true);
    if (is_array($decoded)) {
        $overrides = $decoded;
    }
}

if ($clear) {
    unset($overrides[$supplier]);
} else {
    $entry = $overrides[$supplier] ?? [];
    $changed = false;

    if (is_array($columns)) {
        $allowed = ['sku', 'ean', 'name', 'price', 'stock', 'brand'];
        $clean = [];
        foreach ($allowed as $field) {
            if (!array_key_exists($field, $columns)) {
                continue;
            }
            $vals = $columns[$field];
            if (!is_array($vals)) {
                $vals = [];
            }
            $clean[$field] = array_values(array_filter(array_map(static fn ($v) => trim((string) $v), $vals), static fn ($v) => $v !== ''));
        }
        $entry['columns'] = $clean;
        $changed = true;
    }

    if (is_array($extraCodes)) {
        $entry['extra_codes'] = array_values(array_filter(array_map(static fn ($v) => trim((string) $v), $extraCodes), static fn ($v) => $v !== ''));
        $changed = true;
    }

    if ($matchCodeOnly !== null) {
        $entry['match_code_only'] = $matchCodeOnly;
        $changed = true;
    }

    if (!$changed) {
        import_json_response(['success' => false, 'error' => 'Nimic de salvat (columns sau extra_codes)'], 400);
    }

    $overrides[$supplier] = $entry;
}

$json = json_encode($overrides, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
if ($json === false || file_put_contents($path, $json . "\n") === false) {
    import_json_response(['success' => false, 'error' => 'Nu pot salva mapping_overrides.json'], 500);
}

import_json_response([
    'success' => true,
    'supplier' => $supplier,
    'mapping_override' => $overrides[$supplier] ?? null,
    'message' => $clear ? 'Override șters — revine la suppliers.json' : 'Mapping salvat pentru ' . $supplier,
]);
