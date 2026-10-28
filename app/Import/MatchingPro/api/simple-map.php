<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/ImportTecdocMysqlConnect.php';
require_once __DIR__ . '/lib/ImportSimpleFeedMapper.php';

@ini_set('memory_limit', '1024M');
@set_time_limit(180);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    import_json_response(['success' => false, 'error' => 'POST obligatoriu'], 405);
}

import_motor_api_run(static function (): void {
    $supplier = strtolower(trim((string) ($_POST['supplier'] ?? '')));
    $filename = trim((string) ($_POST['filename'] ?? ''));
    $offset = max(0, min(500000, (int) ($_POST['offset'] ?? 0)));
    $limit = max(1, min(50, (int) ($_POST['limit'] ?? 20)));

    import_motor_require_allowed_supplier($supplier);
    $path = import_resolve_stored_file($supplier, $filename);
    if ($path === null || !is_file($path)) {
        import_json_response(['success' => false, 'error' => 'Fișier invalid sau inexistent'], 404);
    }
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if (!in_array($ext, ['csv', 'txt', 'tsv'], true)) {
        import_json_response(['success' => false, 'error' => 'Maparea simplă acceptă momentan CSV/TXT/TSV. Deschide XLSX în Excel și salvează CSV.'], 415);
    }

    $result = (new ImportSimpleFeedMapper())->mapFile($supplier, $filename, $path, $offset, $limit);
    $result['success'] = true;
    $result['buildMode'] = 'simple_brand_code';

    import_json_response($result);
}, 'simple-map.php');
