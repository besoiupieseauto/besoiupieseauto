<?php
declare(strict_types=1);

ini_set('display_errors', '1');
error_reporting(E_ALL);
ini_set('memory_limit', '512M');
set_time_limit(120);

define('IMPORT_PRODUCE_SKIP_HTTP', true);
require_once dirname(__DIR__) . '/src/Controllers/Produse/importproduse.php';

$codeFilter = $argv[1] ?? '012800025';

function line(string $msg): void
{
    echo $msg . PHP_EOL;
}

function inspect_csv_headers_and_sample(string $path, string $codeFilter): void
{
    line('=== CSV: ' . basename($path) . ' ===');
    $sample = file_get_contents($path, false, null, 0, 4096) ?: '';
    $delimiter = detect_delimiter($sample);
    $handle = fopen($path, 'r');
    if (!$handle) {
        line('Cannot open file');
        return;
    }

    $headers = fgetcsv($handle, 0, $delimiter);
    if (!$headers) {
        fclose($handle);
        line('No headers');
        return;
    }
    $headers = array_map('normalize_key', import_strip_bom_from_row($headers));
    line('Headers: ' . implode(' | ', $headers));

    $ttcKeys = array_values(array_filter($headers, static fn(string $h): bool => str_contains($h, 'ttc') || str_contains($h, 'art id')));
    line('TTC-related headers: ' . ($ttcKeys !== [] ? implode(', ', $ttcKeys) : 'NONE'));

    $found = 0;
    $rowNum = 1;
    while (($values = fgetcsv($handle, 0, $delimiter)) !== false && $found < 3) {
        $rowNum++;
        $row = [];
        foreach ($headers as $idx => $header) {
            $row[$header] = $values[$idx] ?? '';
        }
        $mapped = map_product_row($row, '');
        $pCode = (string)($mapped['pCode'] ?? '');
        if ($codeFilter !== '' && !str_contains(strtoupper($pCode), strtoupper($codeFilter))) {
            continue;
        }

        $found++;
        $ttc = import_base_row_get($row, 'ttc art id');
        $brand = import_base_row_get($row, 'art brand');
        $url = import_base_image_url($brand, $ttc);
        line("Row #$rowNum code=$pCode brand=$brand ttc_art_id=" . ($ttc !== '' ? $ttc : 'EMPTY'));
        line('  URL: ' . ($url !== '' ? $url : '—'));
        if ($url !== '') {
            $ctx = stream_context_create(['http' => ['method' => 'HEAD', 'timeout' => 8, 'ignore_errors' => true]]);
            $headersResp = @get_headers($url, true, $ctx);
            $status = is_array($headersResp) ? (string)($headersResp[0] ?? 'unknown') : 'no response';
            line('  HTTP: ' . $status);
        }
    }
    if ($found === 0) {
        line("No rows matched code filter: $codeFilter");
    }

    rewind($handle);
    fgetcsv($handle, 0, $delimiter);
    $total = 0;
    $nonEmptyTtc = 0;
    $samples = [];
    while (($values = fgetcsv($handle, 0, $delimiter)) !== false) {
        $total++;
        $row = [];
        foreach ($headers as $idx => $header) {
            $row[$header] = $values[$idx] ?? '';
        }
        $ttc = import_base_row_get($row, 'ttc art id');
        if ($ttc !== '') {
            $nonEmptyTtc++;
            if (count($samples) < 5) {
                $samples[] = import_base_row_get($row, 'art code 1') . ' | ' . import_base_row_get($row, 'art brand') . ' | ' . $ttc;
            }
        }
        if ($total >= 100000) {
            break;
        }
    }
    line("Scanned $total rows, non-empty ttc art id: $nonEmptyTtc");
    foreach ($samples as $sample) {
        line('  sample: ' . $sample);
    }
    fclose($handle);
}

$glycoPath = import_temp_file_path('f_1779451183638_zqs4zocg');
if (is_file($glycoPath)) {
    inspect_csv_headers_and_sample($glycoPath, $codeFilter);
} else {
    line('GLYCO file missing at ' . $glycoPath);
}

line('');
line('=== Preview aggregate (1 product) ===');
$supplierFiles = [
    ['path' => import_temp_file_path('f_1779449840544_bw41f026'), 'name' => 'Elit.csv'],
    ['path' => import_temp_file_path('f_1779451269414_sa1196o9'), 'name' => 'Autonet.csv'],
];
if (is_file($supplierFiles[0]['path']) && is_file($glycoPath)) {
    $priceIndex = import_build_price_index($supplierFiles);
    line('Price index size: ' . import_price_index_size($priceIndex));

    $preview = import_preview_products_from_tecdoc_file($glycoPath, 'GLYCO.csv', 500, $priceIndex, null, true);
    $matched = null;
    foreach ($preview['products'] as $product) {
        if (str_contains(strtoupper((string)($product['pCode'] ?? '')), strtoupper($codeFilter))) {
            $matched = $product;
            break;
        }
    }
    if ($matched === null && $preview['products'] !== []) {
        $matched = $preview['products'][0];
        line('Code not found, showing first product instead.');
    }

    if ($matched === null) {
        line('No products in preview.');
    } else {
        line('pCode: ' . ($matched['pCode'] ?? ''));
        line('pBrand: ' . ($matched['pBrand'] ?? ''));
        line('pImages: ' . ($matched['pImages'] ?? '[]'));
        line('pImageSource: ' . ($matched['pImageSource'] ?? ''));
        $raw = json_decode((string)($matched['raw_json'] ?? '{}'), true);
        $rows = is_array($raw) ? ($raw['rows'] ?? []) : [];
        line('raw rows count: ' . count($rows));
        if ($rows !== []) {
            $pair = import_base_first_ttc_pair($rows);
            line('first ttc pair: brand=' . ($pair['brand'] ?? '') . ' ttc=' . ($pair['ttc_art_id'] ?? ''));
        }
        $resolved = import_apply_caietcomenzi_image($matched);
        line('after apply_caietcomenzi pImages: ' . ($resolved['pImages'] ?? '[]'));
        line('after apply_caietcomenzi pImageSource: ' . ($resolved['pImageSource'] ?? ''));
    }
} else {
    line('Missing supplier or glyco files for preview test.');
}

line('');
line('=== DB import_produse sample ===');
try {
    $pdo = Config\Database::getDB();
    $stmt = $pdo->prepare("SELECT id, pCode, pBrand, pImages, pImageSource, LEFT(raw_json, 1200) AS raw_snip FROM import_produse WHERE pCode LIKE ? ORDER BY id DESC LIMIT 1");
    $stmt->execute(['%' . $codeFilter . '%']);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        line('No import_produse row for ' . $codeFilter);
    } else {
        line('id=' . $row['id'] . ' pImages=' . $row['pImages'] . ' source=' . $row['pImageSource']);
        $raw = json_decode((string)($row['raw_snip'] ?? ''), true);
        if (is_array($raw)) {
            $first = $raw['rows'][0] ?? [];
            line('raw ttc art id: ' . import_base_row_get(is_array($first) ? $first : [], 'ttc art id'));
        }
    }
} catch (Throwable $e) {
    line('DB error: ' . $e->getMessage());
}
