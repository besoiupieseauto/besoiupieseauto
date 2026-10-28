<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

@ini_set('memory_limit', '512M');
@set_time_limit(120);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    import_json_response(['success' => false, 'error' => 'GET obligatoriu'], 405);
}

/** @return list<list<string>> */
function ip_preview_csv_rows(string $path, int $limit): array
{
    $fh = fopen($path, 'rb');
    if ($fh === false) {
        return [];
    }
    $sample = (string) fread($fh, 4096);
    rewind($fh);
    $delim = ';';
    $counts = [
        ';' => substr_count($sample, ';'),
        ',' => substr_count($sample, ','),
        "\t" => substr_count($sample, "\t"),
    ];
    arsort($counts);
    $delim = (string) array_key_first($counts);
    $rows = [];
    while (count($rows) < $limit && ($row = fgetcsv($fh, 0, $delim, '"', '\\')) !== false) {
        $rows[] = array_map(static fn($v): string => trim((string) $v), $row);
    }
    fclose($fh);

    return $rows;
}

/** @return list<list<string>> */
function ip_preview_xlsx_rows(string $path, int $limit): array
{
    if (!class_exists('ZipArchive')) {
        return [];
    }
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        return [];
    }
    $shared = [];
    $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
    if (is_string($sharedXml) && $sharedXml !== '') {
        $xml = @simplexml_load_string($sharedXml);
        if ($xml !== false) {
            foreach ($xml->si as $si) {
                $parts = [];
                if (isset($si->t)) {
                    $parts[] = (string) $si->t;
                }
                foreach ($si->r ?? [] as $run) {
                    $parts[] = (string) ($run->t ?? '');
                }
                $shared[] = implode('', $parts);
            }
        }
    }
    $sheetName = 'xl/worksheets/sheet1.xml';
    $sheetXml = $zip->getFromName($sheetName);
    if (!is_string($sheetXml) || $sheetXml === '') {
        $zip->close();
        return [];
    }
    $xml = @simplexml_load_string($sheetXml);
    if ($xml === false) {
        $zip->close();
        return [];
    }
    $rows = [];
    foreach ($xml->sheetData->row ?? [] as $row) {
        $cells = [];
        foreach ($row->c ?? [] as $cell) {
            $type = (string) ($cell['t'] ?? '');
            $value = (string) ($cell->v ?? '');
            if ($type === 's') {
                $value = $shared[(int) $value] ?? '';
            } elseif ($type === 'inlineStr') {
                $value = (string) ($cell->is->t ?? '');
            }
            $cells[] = trim($value);
        }
        $rows[] = $cells;
        if (count($rows) >= $limit) {
            break;
        }
    }
    $zip->close();

    return $rows;
}

try {
    $supplier = strtolower(trim((string) ($_GET['supplier'] ?? '')));
    $filename = trim((string) ($_GET['filename'] ?? ''));
    $limit = max(2, min(250, (int) ($_GET['limit'] ?? 200)));
    import_motor_require_allowed_supplier($supplier);
    $path = import_resolve_stored_file($supplier, $filename);
    if ($path === null || !is_file($path)) {
        import_json_response(['success' => false, 'error' => 'Fișier invalid sau inexistent'], 404);
    }
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $rows = $ext === 'xlsx' ? ip_preview_xlsx_rows($path, $limit) : ip_preview_csv_rows($path, $limit);
    import_json_response([
        'success' => true,
        'supplier' => $supplier,
        'filename' => basename($path),
        'rows' => $rows,
        'truncated' => count($rows) >= $limit,
    ]);
} catch (Throwable $e) {
    import_json_response(['success' => false, 'error' => 'preview-file.php: ' . $e->getMessage()], 500);
}
