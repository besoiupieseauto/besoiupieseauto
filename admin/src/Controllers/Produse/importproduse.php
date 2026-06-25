<?php
declare(strict_types=1);

use Config\Database;

ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
ini_set('memory_limit', '2G');
ini_set('max_execution_time', '900');
set_time_limit(900);
if (!defined('IMPORT_PRODUCE_SKIP_HTTP')) {
if (ob_get_level() === 0) {
    ob_start();
}
}

register_shutdown_function(static function (): void {
    $error = error_get_last();
    if (!$error || !in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        return;
    }

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    if (headers_sent()) {
        return;
    }

    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'message' => 'Eroare server la import: ' . $error['message'],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
});

require_once __DIR__ . '/import_lib.php';
require_once __DIR__ . '/import_supplier_lib.php';
require_once __DIR__ . '/import_identity_lib.php';
require_once __DIR__ . '/import_tecdoc_master_lib.php';
require_once __DIR__ . '/import_base_lib.php';
require_once __DIR__ . '/import_tecdoc_lib.php';
require_once __DIR__ . '/import_job_lib.php';
require_once dirname(__DIR__, 4) . '/system/tecdoc_stock.php';
require_once dirname(__DIR__, 4) . '/system/tecdoc_description.php';
require_once dirname(__DIR__, 4) . '/system/emag_image_search.php';
require_once __DIR__ . '/import_image_job_lib.php';
require_once __DIR__ . '/import_consumable_scan_lib.php';
require_once dirname(__DIR__) . '/AdaosComercial/AdaosComercialService.php';

function out_json(array $payload, int $status = 200): void
{
    while (ob_get_level() > 0) ob_end_clean();
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (!function_exists('import_temp_dir')) {
function import_temp_dir(): string
{
    $dir = dirname(__DIR__, 3) . '/storage/imports';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    return $dir;
}
}

if (!function_exists('import_temp_file_path')) {
function import_temp_file_path(string $fileId): string
{
    $safe = preg_replace('/[^A-Za-z0-9_-]/', '_', $fileId) ?: md5($fileId);
    return import_temp_dir() . '/' . $safe . '.part';
}
}

if (!function_exists('import_temp_meta_path')) {
function import_temp_meta_path(string $fileId): string
{
    return import_temp_file_path($fileId) . '.json';
}
}

if (!function_exists('list_uploaded_import_files')) {
function list_uploaded_import_files(): array
{
    $dir = import_temp_dir();
    $files = glob($dir . '/*.json') ?: [];
    $result = [];
    foreach ($files as $metaPath) {
        $raw = file_get_contents($metaPath);
        $meta = json_decode((string)$raw, true);
        if (!is_array($meta)) continue;
        $fileId = (string)($meta['file_id'] ?? '');
        if ($fileId === '') continue;
        $partPath = import_temp_file_path($fileId);
        if (!is_file($partPath)) continue;
        $originalName = (string)($meta['original_name'] ?? '');
        $kind = (string)($meta['file_kind'] ?? '');
        if ($kind === '') {
            try {
                $kind = import_classify_file($partPath, $originalName);
            } catch (Throwable $e) {
                $kind = 'unknown';
            }
        }
        try {
            $resolvedRole = import_resolve_upload_role($meta, $partPath, $originalName);
        } catch (Throwable $e) {
            $resolvedRole = (string)($meta['upload_role'] ?? '');
        }
        $result[] = [
            'file_id' => $fileId,
            'original_name' => $originalName,
            'size' => (int)(filesize($partPath) ?: 0),
            'completed' => !empty($meta['completed']),
            'total_chunks' => (int)($meta['total_chunks'] ?? 0),
            'last_chunk' => (int)($meta['last_chunk'] ?? 0),
            'updated_at' => date('Y-m-d H:i:s', filemtime($metaPath) ?: time()),
            'upload_role' => (string)($meta['upload_role'] ?? ''),
            'resolved_role' => $resolvedRole,
            'file_kind' => $kind,
            'file_kind_label' => import_file_kind_label($kind),
        ];
    }
    usort($result, static fn(array $a, array $b) => strcmp($b['updated_at'], $a['updated_at']));
    return $result;
}
}

function import_resolve_uploaded_tecdoc_files(): array
{
    $files = [];
    foreach (list_uploaded_import_files() as $meta) {
        if (empty($meta['completed'])) {
            continue;
        }

        $kind = (string)($meta['file_kind'] ?? '');
        $role = (string)($meta['resolved_role'] ?? $meta['upload_role'] ?? '');
        $name = (string)($meta['original_name'] ?? '');
        $isTecdoc = $kind === 'tecdoc'
            || $role === 'tecdoc'
            || stripos($name, 'tableusecarsforparts') !== false
            || stripos($name, 'tecdoc') !== false;

        if (!$isTecdoc) {
            continue;
        }

        $path = import_temp_file_path((string)($meta['file_id'] ?? ''));
        if (!is_file($path)) {
            continue;
        }

        $files[] = [
            'path' => $path,
            'name' => $name,
            'file_id' => (string)($meta['file_id'] ?? ''),
        ];
    }

    return $files;
}

function delete_uploaded_import_file(string $fileId): bool
{
    $part = import_temp_file_path($fileId);
    $meta = import_temp_meta_path($fileId);
    $deleted = false;
    if (is_file($part)) {
        unlink($part);
        $deleted = true;
    }
    if (is_file($meta)) {
        unlink($meta);
        $deleted = true;
    }
    return $deleted;
}

function import_file_kind_label(string $kind): string
{
    if ($kind === 'tecdoc') {
        return 'TecDoc';
    }
    if (str_starts_with($kind, 'supplier:')) {
        return 'Furnizor: ' . substr($kind, 9);
    }

    return 'Fișier import';
}

function save_chunk_upload(string $fileId, string $originalName, int $chunkIndex, int $totalChunks, string $tmpChunkPath, string $uploadRole = ''): array
{
    $target = import_temp_file_path($fileId);
    $metaPath = import_temp_meta_path($fileId);

    if ($chunkIndex === 0 && is_file($target)) {
        unlink($target);
    }

    $in = fopen($tmpChunkPath, 'rb');
    $out = fopen($target, $chunkIndex === 0 ? 'wb' : 'ab');
    if (!$in || !$out) {
        if ($in) fclose($in);
        if ($out) fclose($out);
        throw new RuntimeException('Nu am putut scrie chunk-ul pe disc.');
    }

    stream_copy_to_stream($in, $out);
    fclose($in);
    fclose($out);

    $meta = [
        'file_id' => $fileId,
        'original_name' => $originalName,
        'total_chunks' => $totalChunks,
        'last_chunk' => $chunkIndex,
        'size' => filesize($target) ?: 0,
        'completed' => ($chunkIndex + 1) >= $totalChunks,
        'upload_role' => $uploadRole,
    ];
    if ($meta['completed']) {
        $meta['file_kind'] = import_classify_file($target, $originalName);
    }
    file_put_contents($metaPath, json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

    return $meta;
}

function preview_products_from_file(
    string $path,
    string $filename,
    int $maxPreview = 500,
    array $priceIndex = [],
    ?\Evasystem\Controllers\AdaosComercial\AdaosComercialService $markupService = null
): array
{
    $rowsForPreview = [];
    $totalRows = 0;
    $truncated = false;
    $isXlsx = str_ends_with(strtolower($filename), '.xlsx');
    $seenGroupKeys = [];

    if ($isXlsx) {
        $rows = read_import_rows($path, $filename);
        foreach ($rows as $row) {
            $totalRows++;
            $mapped = map_product_row($row, '');
            if (($mapped['pName'] ?? '') === '' && ($mapped['pCode'] ?? '') === '') continue;
            $key = import_group_key($mapped);
            if (count($seenGroupKeys) >= $maxPreview && !isset($seenGroupKeys[$key])) {
                $truncated = true;
                break;
            }
            $seenGroupKeys[$key] = true;
            $rowsForPreview[] = $row;
        }
    } else {
        $first = file_get_contents($path, false, null, 0, 4096) ?: '';
        $delimiter = detect_delimiter($first);
        $handle = fopen($path, 'r');
        if (!$handle) {
            return ['products' => [], 'total_rows' => 0, 'truncated' => false];
        }
        $headers = fgetcsv($handle, 0, $delimiter);
        if (!$headers) {
            fclose($handle);
            return ['products' => [], 'total_rows' => 0, 'truncated' => false];
        }
        $headers = array_map('normalize_key', $headers);

        while (($values = fgetcsv($handle, 0, $delimiter)) !== false) {
            $totalRows++;
            $row = [];
            foreach ($headers as $idx => $header) $row[$header] = $values[$idx] ?? '';
            $mapped = map_product_row($row, '');
            if (($mapped['pName'] ?? '') === '' && ($mapped['pCode'] ?? '') === '') continue;
            $key = import_group_key($mapped);
            if (count($seenGroupKeys) >= $maxPreview && !isset($seenGroupKeys[$key])) {
                $truncated = true;
                break;
            }
            $seenGroupKeys[$key] = true;
            $rowsForPreview[] = $row;
        }
        fclose($handle);
    }

    $products = import_aggregate_rows($rowsForPreview, $maxPreview, $priceIndex, $markupService);
    if (count($products) >= $maxPreview) {
        $truncated = true;
        $products = array_slice($products, 0, $maxPreview);
    }

    return ['products' => $products, 'total_rows' => $totalRows, 'truncated' => $truncated];
}

function import_oem_codes_from_product(array $product): array
{
    $codes = [];
    $seen = [];

    $addCode = static function (string $code) use (&$codes, &$seen): void {
        $code = trim($code);
        if ($code === '') {
            return;
        }
        $key = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $code) ?? $code);
        if ($key === '' || isset($seen[$key])) {
            return;
        }
        $seen[$key] = true;
        $codes[] = $code;
    };

    $oemText = trim((string)($product['pOem'] ?? ''));
    if ($oemText !== '') {
        foreach (preg_split('/\s*,\s*/', str_replace("\n", ', ', $oemText)) ?: [] as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            if (preg_match('/^([^:]+):\s*(.+)$/u', $part, $matches)) {
                $addCode(trim($matches[2]));
                continue;
            }
            foreach (import_extract_codes_from_text($part) as $code) {
                $addCode($code);
            }
        }
    }

    $raw = json_decode((string)($product['raw_json'] ?? '{}'), true);
    if (is_array($raw)) {
        $rawRows = isset($raw['rows']) && is_array($raw['rows']) ? $raw['rows'] : [$raw];
        foreach ($rawRows as $rawRow) {
            if (!is_array($rawRow)) {
                continue;
            }
            foreach (import_extract_oem_candidates($rawRow) as $code) {
                $addCode($code);
            }
        }
        foreach (['product_summary', 'tecdoc_file', 'tecdoc_api'] as $section) {
            if (!isset($raw[$section]) || !is_array($raw[$section])) {
                continue;
            }
            foreach ($raw[$section] as $value) {
                if (!is_string($value)) {
                    continue;
                }
                foreach (import_extract_codes_from_text($value) as $code) {
                    $addCode($code);
                }
            }
        }
    }

    $primaryCode = trim((string)($product['pCode'] ?? ''));
    if ($primaryCode !== '') {
        $addCode($primaryCode);
    }

    return $codes;
}

function import_apply_tecdoc_record_to_product(
    array $product,
    array $record,
    string $matchedQuery,
    array $searchCodes,
    array $tecdocRows = [],
    bool $collectFullCompatRows = false
): array
{
    if ($tecdocRows === [] && $collectFullCompatRows) {
        $codeNorm = import_normalize_product_code((string)($record['art_code_1'] ?? ($product['pCode'] ?? '')));
        $tecdocFiles = import_resolve_uploaded_tecdoc_files();
        if ($codeNorm !== '' && $tecdocFiles !== []) {
            $groups = import_tecdoc_collect_rows_for_codes($tecdocFiles, [$codeNorm]);
            $tecdocRows = $groups[$codeNorm] ?? [];
        }
    }
    if ($tecdocRows === []) {
        $tecdocRows = [import_tecdoc_record_to_raw_row($record)];
    }

    $rawPayload = json_decode((string)($product['raw_json'] ?? '{}'), true);
    if (!is_array($rawPayload)) {
        $rawPayload = [];
    }
    $netPrice = (float)($rawPayload['supplier_price']['net_price'] ?? 0);

    $supplierEntry = [
        'code' => trim((string)($product['pCode'] ?? '')),
        'brand' => trim((string)($product['pBrand'] ?? '')),
        'name' => trim((string)($product['pName'] ?? '')),
        'supplier' => trim((string)($product['pSupplier'] ?? '')),
        'net_price' => $netPrice,
    ];

    $markupService = null;
    if (class_exists(\Evasystem\Controllers\AdaosComercial\AdaosComercialService::class)) {
        try {
            $markupService = new \Evasystem\Controllers\AdaosComercial\AdaosComercialService();
        } catch (Throwable $e) {
            $markupService = null;
        }
    }

    $built = import_build_product_from_supplier_tecdoc(
        $supplierEntry,
        $markupService,
        false,
        $record,
        $tecdocRows
    );
    if ($built === null) {
        return $product;
    }

    foreach ([
        'pName', 'pBrand', 'pMarca', 'pModel', 'pMotorizare', 'pCar',
        'pCategory', 'pSubcategory', 'pOem', 'pNote', 'pSpecs', 'pImages',
        'pPrice', 'pBasePrice', 'pMarkupRuleId', 'pMarkupRuleName', 'pMarkupAppliedAt',
    ] as $field) {
        if (array_key_exists($field, $built) && trim((string)$built[$field]) !== '') {
            $product[$field] = $built[$field];
        }
    }

    $sourcePayload = json_decode((string)($product['raw_json'] ?? '{}'), true);
    if (!is_array($sourcePayload)) {
        $sourcePayload = [];
    }
    $builtRaw = json_decode((string)($built['raw_json'] ?? '{}'), true);
    if (!is_array($builtRaw)) {
        $builtRaw = [];
    }

    $product['raw_json'] = json_encode(array_merge($sourcePayload, $builtRaw, [
        'tecdoc_import_enrichment' => [
            'found' => true,
            'source' => 'csv_files',
            'query_codes' => $searchCodes,
            'matched_query' => $matchedQuery,
            'rows_count' => count($tecdocRows),
        ],
        '__image_source' => $builtRaw['__image_source'] ?? ($sourcePayload['__image_source'] ?? ''),
        '__image_query' => $matchedQuery !== '' ? $matchedQuery : ($searchCodes[0] ?? ''),
    ]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $product['__image_meta'] = [
        'source' => (string)($builtRaw['__image_source'] ?? 'missing'),
        'query' => $matchedQuery !== '' ? $matchedQuery : ($searchCodes[0] ?? ''),
    ];

    return $product;
}

function import_enrich_product_from_tecdoc(array $product, ?array $tecdocFiles = null, ?array $sharedLookup = null): array
{
    $code = trim((string)($product['pCode'] ?? ''));
    if ($code === '' && trim((string)($product['pOem'] ?? '')) === '') {
        return $product;
    }

    $tecdocFiles = $tecdocFiles ?? import_resolve_uploaded_tecdoc_files();
    if ($tecdocFiles === [] && $sharedLookup === null) {
        return $product;
    }

    $searchCodes = import_oem_codes_from_product($product);
    if ($searchCodes === []) {
        return $product;
    }

    $record = import_tecdoc_find_record_for_product($product, $tecdocFiles, $sharedLookup);
    if ($record === null) {
        return $product;
    }

    $matchedQuery = $sharedLookup !== null
        ? import_tecdoc_matched_query_for_product($product, $sharedLookup)
        : ($searchCodes[0] ?? $code);

    return import_apply_tecdoc_record_to_product($product, $record, $matchedQuery, $searchCodes, [], false);
}

function import_field_is_empty(?string $value): bool
{
    $value = trim((string)$value);
    return $value === '' || $value === '—' || $value === '-';
}

function import_row_image_url(array $row): string
{
    require_once dirname(__DIR__, 4) . '/system/import-image-validate.php';

    return besoiu_import_row_image_url($row);
}

function import_image_url_is_trusted(string $url, string $source = ''): bool
{
    require_once dirname(__DIR__, 4) . '/system/import-image-validate.php';

    return besoiu_import_image_url_is_trusted($url, $source);
}

function import_row_images_empty(array $row): bool
{
    return !import_image_url_is_trusted(
        import_row_image_url($row),
        (string)($row['pImageSource'] ?? '')
    );
}

function import_image_is_placeholder(string $url): bool
{
    require_once dirname(__DIR__, 4) . '/system/import-image-validate.php';

    return besoiu_import_image_is_placeholder($url);
}

function import_should_fetch_tecdoc_image(array $row): bool
{
    return import_row_images_empty($row);
}

function import_ttc_art_id_from_product(array $product): string
{
    $raw = json_decode((string)($product['raw_json'] ?? '{}'), true);
    if (!is_array($raw)) {
        return '';
    }

    $tecdocFile = is_array($raw['tecdoc_file'] ?? null) ? $raw['tecdoc_file'] : [];
    $id = trim((string)($tecdocFile['ttc_art_id'] ?? ''));
    if ($id !== '') {
        return $id;
    }

    foreach (['rows', 'source_rows'] as $key) {
        foreach ((array)($raw[$key] ?? []) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = import_base_row_get($row, 'ttc art id');
            if ($id !== '') {
                return $id;
            }
        }
    }

    foreach (['tecdoc_api', 'tecdoc_import_enrichment'] as $section) {
        $meta = $raw[$section] ?? null;
        if (!is_array($meta)) {
            continue;
        }
        $id = trim((string)($meta['ttc_art_id'] ?? ''));
        if ($id !== '') {
            return $id;
        }
    }

    return '';
}

function import_ttc_art_brand_from_product(array $product): string
{
    $raw = json_decode((string)($product['raw_json'] ?? '{}'), true);
    if (!is_array($raw)) {
        return trim((string)($product['pBrand'] ?? ''));
    }

    $tecdocFile = is_array($raw['tecdoc_file'] ?? null) ? $raw['tecdoc_file'] : [];
    $brand = trim((string)($tecdocFile['art_brand'] ?? ''));
    if ($brand !== '') {
        return $brand;
    }

    foreach (['rows', 'source_rows'] as $key) {
        foreach ((array)($raw[$key] ?? []) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $brand = import_base_row_get($row, 'art brand');
            if ($brand !== '') {
                return $brand;
            }
        }
    }

    return trim((string)($product['pBrand'] ?? ''));
}

function import_product_rows_from_raw(array $product): array
{
    $raw = json_decode((string)($product['raw_json'] ?? '{}'), true);
    if (!is_array($raw)) {
        return [];
    }

    $rows = $raw['rows'] ?? [];
    if (!is_array($rows)) {
        return [];
    }

    return $rows;
}

function import_apply_caietcomenzi_image(array $product): array
{
    $images = json_decode((string)($product['pImages'] ?? '[]'), true);
    $firstImage = is_array($images) ? trim((string)($images[0] ?? '')) : '';
    if ($firstImage !== '' && import_image_url_is_trusted($firstImage, (string)($product['pImageSource'] ?? 'caietcomenzi'))) {
        return $product;
    }

    $rows = import_product_rows_from_raw($product);
    if ($rows === []) {
        return $product;
    }

    $resolved = import_base_resolve_image_from_rows($rows);
    if ($resolved['url'] === '') {
        return $product;
    }

    $product['pImages'] = json_encode([$resolved['url']], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $product['pImageSource'] = 'caietcomenzi';

    $raw = json_decode((string)($product['raw_json'] ?? '{}'), true);
    if (is_array($raw)) {
        $raw['__image_source'] = 'caietcomenzi';
        $raw['__image_query'] = trim((string)($product['pCode'] ?? ''));
        if (!isset($raw['tecdoc_file']) || !is_array($raw['tecdoc_file'])) {
            $raw['tecdoc_file'] = [];
        }
        $raw['tecdoc_file']['found'] = true;
        $raw['tecdoc_file']['ttc_art_id'] = $resolved['ttc_art_id'];
        $raw['tecdoc_file']['art_brand'] = $resolved['brand'] !== ''
            ? $resolved['brand']
            : trim((string)($product['pBrand'] ?? ''));
        $product['raw_json'] = json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    return $product;
}

function import_resolve_image_from_tecdoc_api(array $product, string $query = '', bool $fast = false): array
{
    return import_find_image_from_oem_cross_list($product, $fast, $query);
}

function import_throttle_tecdoc_api_call(): void
{
    static $lastCallAt = 0.0;
    $minInterval = 0.35;
    $now = microtime(true);
    if ($lastCallAt > 0 && ($now - $lastCallAt) < $minInterval) {
        usleep((int)(($minInterval - ($now - $lastCallAt)) * 1000000));
    }
    $lastCallAt = microtime(true);
}

function import_normalize_image_hit(array $hit): array
{
    $url = trim((string) ($hit['url'] ?? ''));
    if ($url === '' || !preg_match('#^https?://#i', $url)) {
        return $hit;
    }

    $pipelinePath = dirname(__DIR__, 4) . '/system/image_search_pipeline.php';
    if (is_file($pipelinePath)) {
        require_once $pipelinePath;
        if (function_exists('besoiu_image_store_lookup_url_locally')) {
            $code = trim((string) ($hit['sku'] ?? $hit['query'] ?? ''));
            $local = besoiu_image_store_lookup_url_locally($url, $code);
            if ($local !== '') {
                $hit['remote_url'] = $url;
                $hit['url'] = $local;
            }
        }
    }

    return $hit;
}

function import_apply_image_lookup_result(array $product, array $found): array
{
    $imageUrl = trim((string)($found['url'] ?? ''));
    if ($imageUrl === '') {
        return $product;
    }

    $pipelinePath = dirname(__DIR__, 4) . '/system/image_search_pipeline.php';
    if (is_file($pipelinePath)) {
        require_once $pipelinePath;
        if (function_exists('besoiu_image_store_lookup_url_locally')) {
            $code = trim((string) ($product['pCode'] ?? ''));
            if ($code === '' && !empty($found['sku'])) {
                $code = (string) $found['sku'];
            }
            $imageUrl = besoiu_image_store_lookup_url_locally($imageUrl, $code);
            $found['url'] = $imageUrl;
            if (!empty($found['tecdoc_remote_url']) && preg_match('#^https?://#i', (string) $found['tecdoc_remote_url'])) {
                $found['tecdoc_remote_url'] = (string) $found['tecdoc_remote_url'];
            }
        }
    }

    $product['pImages'] = json_encode([$imageUrl], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $product['pImageSource'] = (string)($found['source'] ?? 'tecdoc_api');

    $raw = json_decode((string)($product['raw_json'] ?? '{}'), true);
    if (!is_array($raw)) {
        $raw = [];
    }

    $raw['__image_source'] = (string)($found['source'] ?? 'tecdoc_api');
    $raw['__image_query'] = (string)($found['query'] ?? ($product['pCode'] ?? ''));
    if (!empty($found['oem_matched_code'])) {
        $raw['__oem_matched_code'] = (string) $found['oem_matched_code'];
        $raw['__oem_matched_brand'] = (string) ($found['oem_matched_brand'] ?? '');
    }
    if (!empty($found['emag_search_url'])) {
        $raw['__emag_search_url'] = (string) $found['emag_search_url'];
        $raw['__emag_product_url'] = (string) ($found['emag_product_url'] ?? '');
        $raw['__emag_product_title'] = (string) ($found['emag_title'] ?? '');
        $raw['__emag_remote_url'] = (string) ($found['emag_remote_url'] ?? '');
    }
    if (!empty($found['ai_verdict'])) {
        $raw['image_pipeline_ai'] = [
            'verdict' => (string) ($found['ai_verdict'] ?? ''),
            'score' => (int) ($found['ai_score'] ?? 0),
            'title' => (string) ($found['title'] ?? ''),
            'url_product' => (string) ($found['url_product'] ?? ''),
            'sku' => (string) ($found['sku'] ?? ''),
        ];
    }
    if (!empty($found['url_product'])) {
        $raw['__scraper_product_url'] = (string) $found['url_product'];
    }
    $raw['tecdoc_api'] = array_merge(is_array($raw['tecdoc_api'] ?? null) ? $raw['tecdoc_api'] : [], [
        'found' => true,
        'image_url' => $imageUrl,
        'remote_url' => (string)($found['tecdoc_remote_url'] ?? $imageUrl),
        'ttc_art_id' => (string)($found['tecdoc_article_id'] ?? ''),
        'query_code' => (string)($product['pCode'] ?? ''),
        'query_brand' => (string)($product['pBrand'] ?? ''),
    ]);
    $product['raw_json'] = json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    return $product;
}

function import_merge_tecdoc_gaps(array $row, array $enriched): array
{
    foreach ([
        'pName', 'pBrand', 'pMarca', 'pModel', 'pMotorizare', 'pCar',
        'pCategory', 'pSubcategory', 'pOem', 'pNote', 'pCompatibilitati',
    ] as $field) {
        if (!import_field_is_empty((string)($row[$field] ?? ''))) {
            continue;
        }
        $value = trim((string)($enriched[$field] ?? ''));
        if ($value !== '') {
            $row[$field] = $value;
        }
    }

    $imageMeta = is_array($enriched['__image_meta'] ?? null) ? $enriched['__image_meta'] : [];
    if (import_should_fetch_tecdoc_image($row)) {
        $enrichedImages = json_decode((string)($enriched['pImages'] ?? '[]'), true);
        if (is_array($enrichedImages) && trim((string)($enrichedImages[0] ?? '')) !== '') {
            $row['pImages'] = $enriched['pImages'];
            $row['pImageSource'] = (string)($imageMeta['source'] ?? 'tecdoc');
        }
    }

    $enrichedRaw = json_decode((string)($enriched['raw_json'] ?? '{}'), true);
    if (is_array($enrichedRaw)) {
        $rowRaw = json_decode((string)($row['raw_json'] ?? '{}'), true);
        if (!is_array($rowRaw)) {
            $rowRaw = [];
        }
        $row['raw_json'] = json_encode(array_merge($rowRaw, [
            'tecdoc_import_enrichment' => $enrichedRaw['tecdoc_import_enrichment'] ?? null,
            'product_summary' => $enrichedRaw['product_summary'] ?? ($rowRaw['product_summary'] ?? []),
            '__image_source' => (string)($imageMeta['source'] ?? ($enrichedRaw['__image_source'] ?? '')),
            '__image_query' => (string)($imageMeta['query'] ?? ($enrichedRaw['__image_query'] ?? '')),
        ]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    return $row;
}

function import_prepare_row_for_add(array $row, ?array $tecdocFiles = null, ?array $sharedLookup = null): array
{
    $tecdocFiles = $tecdocFiles ?? import_resolve_uploaded_tecdoc_files();
    $row = import_apply_caietcomenzi_image($row);
    $enriched = import_enrich_product_from_tecdoc($row, $tecdocFiles, $sharedLookup);
    $enrichedRaw = json_decode((string)($enriched['raw_json'] ?? '{}'), true);
    if (is_array($enrichedRaw) && !empty($enrichedRaw['tecdoc_import_enrichment']['found'])) {
        $row = import_merge_tecdoc_gaps($row, $enriched);
    }

    if (import_field_is_empty((string)($row['pOem'] ?? ''))) {
        $inferred = import_supplier_format_oem_field([], (string)($row['pBrand'] ?? ''), (string)($row['pCode'] ?? ''));
        if ($inferred !== '') {
            $row['pOem'] = $inferred;
        }
    }

    if (import_should_fetch_tecdoc_image($row)) {
        $found = import_find_image_for_product($row, $tecdocFiles, $sharedLookup);
        if (trim((string)($found['url'] ?? '')) !== '') {
            $row = import_apply_image_lookup_result($row, $found);
        } elseif (!empty($found['api_error'])) {
            $rowRaw = json_decode((string)($row['raw_json'] ?? '{}'), true);
            if (!is_array($rowRaw)) {
                $rowRaw = [];
            }
            $rowRaw['tecdoc_api'] = array_merge(is_array($rowRaw['tecdoc_api'] ?? null) ? $rowRaw['tecdoc_api'] : [], [
                'found' => false,
                'error' => (string)$found['api_error'],
            ]);
            $row['raw_json'] = json_encode($rowRaw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
    }

    return $row;
}

/**
 * Același pipeline ca la publicare din importreview: TecDoc + imagine RapidAPI + descriere.
 */
function import_enrich_row_before_live_publish(array $row, bool $forceTecdocImage = true): array
{
    $row = import_prepare_row_for_add($row);

    $url = import_row_image_url($row);
    $source = (string) ($row['pImageSource'] ?? '');
    if ($forceTecdocImage && !import_image_url_is_trusted($url, $source)) {
        $found = import_find_image_for_product($row, null, null, false, true);
        if (trim((string) ($found['url'] ?? '')) !== '') {
            $row = import_apply_image_lookup_result($row, $found);
        }
    }

    return $row;
}

/**
 * Produse speciale (ulei, lichide, consumabile) — singura diferență față de import standard în Cron.
 */
function import_consumable_is_special_product(array $row): bool
{
    if (function_exists('import_consumable_detect_categories')) {
        return import_consumable_detect_categories($row) !== [];
    }

    return false;
}

/**
 * Același flux ca «Import selected» pe /admin/import → coadă importreview.
 *
 * @param array<int, array<string, mixed>> $products
 * @param array<string, mixed> $options epiesa_special_products(bool), supplier_code(string), logger(callable)
 * @return array{queued:int,with_image:int,without_price:int,tecdoc_enriched:int,epiesa_checked:int,epiesa_found:int,vitrina_candidates:int}
 */
function import_stage_products_for_review(PDO $pdo, array $products, \Evasystem\Controllers\AdaosComercial\AdaosComercialService $markupService, array $options = []): array
{
    $checkEpiesa = !array_key_exists('epiesa_special_products', $options) || !empty($options['epiesa_special_products']);
    $supplierCode = strtoupper(trim((string) ($options['supplier_code'] ?? '')));
    $logger = $options['logger'] ?? null;
    $priceIndex = is_array($options['price_index'] ?? null) ? $options['price_index'] : [];

    $stats = [
        'queued' => 0,
        'with_image' => 0,
        'without_price' => 0,
        'tecdoc_enriched' => 0,
        'epiesa_checked' => 0,
        'epiesa_found' => 0,
        'vitrina_candidates' => 0,
    ];

    if ($products === []) {
        return $stats;
    }

    $tecdocFiles = import_resolve_uploaded_tecdoc_files();
    $sharedLookup = import_build_tecdoc_lookup_for_products($products, $tecdocFiles);
    $columns = import_staging_insert_columns($pdo);
    $placeholders = implode(',', array_map(static fn (string $c): string => ':' . $c, $columns));
    $stmt = $pdo->prepare(
        'INSERT INTO import_produse (`' . implode('`,`', $columns) . '`) VALUES (' . $placeholders . ')'
    );

    foreach ($products as $product) {
        if (!is_array($product)) {
            continue;
        }

        if ($supplierCode !== '') {
            $product['pSupplier'] = trim((string) ($product['pSupplier'] ?? $supplierCode));
        }

        if ($priceIndex !== [] && function_exists('import_apply_supplier_pricing')) {
            $product = import_apply_supplier_pricing($product, $priceIndex, $markupService);
        }

        $product = import_apply_caietcomenzi_image($product);
        $product = import_enrich_product_from_tecdoc($product, $tecdocFiles, $sharedLookup);
        $enrichedRaw = json_decode((string) ($product['raw_json'] ?? '{}'), true);
        if (!empty($enrichedRaw['tecdoc_import_enrichment']['found'])) {
            ++$stats['tecdoc_enriched'];
        }

        $code = trim((string) ($product['pCode'] ?? ''));
        $brand = trim((string) ($product['pBrand'] ?? ''));
        $images = json_decode((string) ($product['pImages'] ?? '[]'), true);
        if (!is_array($images)) {
            $images = [];
        }

        $imageMeta = is_array($product['__image_meta'] ?? null)
            ? $product['__image_meta']
            : import_default_image_meta($product, $images);
        unset($product['__image_meta']);

        if (import_should_fetch_tecdoc_image([
            'pImages' => json_encode($images, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'pImageSource' => (string) ($imageMeta['source'] ?? ''),
            'raw_json' => (string) ($product['raw_json'] ?? '{}'),
        ])) {
            $found = import_find_image_for_product($product, $tecdocFiles, $sharedLookup);
            $imageMeta = [
                'source' => (string) ($found['source'] ?? 'missing'),
                'query' => (string) ($found['query'] ?? ''),
            ];
            $imageUrl = (string) ($found['url'] ?? '');
            if ($imageUrl !== '') {
                $product = import_apply_image_lookup_result($product, $found);
                $images = json_decode((string) ($product['pImages'] ?? '[]'), true);
                if (!is_array($images)) {
                    $images = [$imageUrl];
                }
                ++$stats['with_image'];
            }
        } elseif (!empty($images[0])) {
            ++$stats['with_image'];
        }

        $sourcePayload = json_decode((string) ($product['raw_json'] ?? '{}'), true);
        if (!is_array($sourcePayload)) {
            $sourcePayload = [];
        }

        $row = [
            'pName' => trim((string) ($product['pName'] ?? '')),
            'pCode' => $code,
            'pBrand' => $brand,
            'pMarca' => trim((string) ($product['pMarca'] ?? '')),
            'pModel' => trim((string) ($product['pModel'] ?? '')),
            'pMotorizare' => trim((string) ($product['pMotorizare'] ?? '')),
            'pCar' => trim((string) ($product['pCar'] ?? $brand)),
            'pBasePrice' => trim((string) ($product['pBasePrice'] ?? ($product['pPrice'] ?? ''))),
            'pStock' => trim((string) ($product['pStock'] ?? '')),
            'pCategory' => trim((string) ($product['pCategory'] ?? '')),
            'pSubcategory' => trim((string) ($product['pSubcategory'] ?? '')),
            'pCompatibilitati' => trim((string) ($product['pCompatibilitati'] ?? '')),
            'pOem' => trim((string) ($product['pOem'] ?? '')),
            'pSupplier' => trim((string) ($product['pSupplier'] ?? $brand)),
            'pState' => 'Nou',
            'pCity' => '',
            'pNote' => trim((string) ($product['pNote'] ?? '')),
            'pNoteWebsite' => trim((string) ($product['pNoteWebsite'] ?? '')),
            'pNoteMarketplace' => trim((string) ($product['pNoteMarketplace'] ?? '')),
            'pImages' => json_encode($images, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'pImageSource' => $imageMeta['source'],
            'pShipping' => trim((string) ($product['pStock'] ?? '')),
            'pWarranty' => '',
            'pReturn' => '',
            'pWhatsapp' => '',
        ];

        $pricing = $markupService->applyAutomaticMarkup($row);
        $row = array_merge($row, $pricing['data']);
        if (trim((string) ($row['pBasePrice'] ?? '')) === '' && trim((string) ($row['pPrice'] ?? '')) === '') {
            ++$stats['without_price'];
        }

        $row['raw_json'] = json_encode(array_merge($sourcePayload, [
            'schema' => 'product_import_v2',
            'import_mode' => !empty($options['cron_sync']) ? 'cron_sync' : 'import_page',
            'product' => [
                'name' => (string) $row['pName'],
                'code' => (string) $row['pCode'],
                'brand' => (string) $row['pBrand'],
                'marca' => (string) $row['pMarca'],
                'model' => (string) $row['pModel'],
                'motorizare' => (string) $row['pMotorizare'],
                'category' => (string) $row['pCategory'],
                'subcategory' => (string) $row['pSubcategory'],
                'price' => (string) $row['pPrice'],
                'base_price' => (string) ($row['pBasePrice'] ?? ''),
                'stock' => (string) $row['pStock'],
                'oem_codes' => (string) $row['pOem'],
            ],
            'product_summary' => $sourcePayload['product_summary'] ?? [],
            'source_rows' => $sourcePayload['rows'] ?? ($sourcePayload['source_rows'] ?? []),
            '__image_source' => $imageMeta['source'],
            '__image_query' => $imageMeta['query'],
        ]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $row['status'] = 'pending';

        if ($checkEpiesa && import_consumable_is_special_product($row)) {
            $epiesa = import_consumable_enrich_epiesa($row, is_callable($logger) ? $logger : null);
            if ($epiesa['checked']) {
                ++$stats['epiesa_checked'];
            }
            if ($epiesa['found']) {
                ++$stats['epiesa_found'];
            }
            if (!empty($epiesa['vitrina'])) {
                $raw = json_decode((string) ($row['raw_json'] ?? '{}'), true);
                if (!is_array($raw)) {
                    $raw = [];
                }
                $raw['__vitrina_candidate'] = true;
                $row['raw_json'] = json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                ++$stats['vitrina_candidates'];
            }
        }

        $stmt->execute(import_prepare_staging_insert($pdo, $row));
        ++$stats['queued'];
    }

    return $stats;
}

function import_apply_vitrina_if_staged(PDO $pdo, int $productId, array $stagingRow): void
{
    if ($productId <= 0) {
        return;
    }

    $raw = json_decode((string) ($stagingRow['raw_json'] ?? '{}'), true);
    if (!is_array($raw) || empty($raw['__vitrina_candidate'])) {
        return;
    }

    if (!function_exists('tecdoc_ensure_vitrina_column')) {
        require_once dirname(__DIR__, 4) . '/system/tecdoc_stock.php';
    }

    try {
        tecdoc_ensure_vitrina_column($pdo);
        $stmt = $pdo->prepare(
            'UPDATE produse SET pVitrina = 1 WHERE id = ? AND COALESCE(status, 1) <> 0'
        );
        $stmt->execute([$productId]);
    } catch (Throwable) {
        // coloană opțională
    }
}

function import_build_tecdoc_lookup_for_products(array $products, ?array $tecdocFiles = null): array
{
    $tecdocFiles = $tecdocFiles ?? import_resolve_uploaded_tecdoc_files();
    if ($tecdocFiles === [] || $products === []) {
        return [];
    }

    $searchCodes = [];
    foreach ($products as $product) {
        foreach (import_oem_codes_from_product($product) as $code) {
            $searchCodes[$code] = true;
        }
    }

    return import_tecdoc_build_lookup_for_search_codes($tecdocFiles, array_keys($searchCodes));
}

function import_sync_prepared_row(PDO $pdo, int $importId, array $row): void
{
    $sets = [
        'pName = ?', 'pBrand = ?', 'pMarca = ?', 'pModel = ?', 'pMotorizare = ?', 'pCar = ?',
        'pCategory = ?', 'pSubcategory = ?', 'pOem = ?', 'pNote = ?', 'pCompatibilitati = ?',
        'pPrice = ?', 'pBasePrice = ?', 'pStock = ?',
        'pImages = ?', 'pImageSource = ?', 'raw_json = ?',
    ];
    $values = [
        (string)($row['pName'] ?? ''),
        (string)($row['pBrand'] ?? ''),
        (string)($row['pMarca'] ?? ''),
        (string)($row['pModel'] ?? ''),
        (string)($row['pMotorizare'] ?? ''),
        (string)($row['pCar'] ?? ''),
        (string)($row['pCategory'] ?? ''),
        (string)($row['pSubcategory'] ?? ''),
        (string)($row['pOem'] ?? ''),
        (string)($row['pNote'] ?? ''),
        (string)($row['pCompatibilitati'] ?? ''),
        (string)($row['pPrice'] ?? ''),
        (string)($row['pBasePrice'] ?? ''),
        (string)($row['pStock'] ?? '0'),
        (string)($row['pImages'] ?? '[]'),
        (string)($row['pImageSource'] ?? ''),
        (string)($row['raw_json'] ?? '{}'),
    ];

    if (import_table_has_column($pdo, 'import_produse', 'pNoteWebsite')) {
        $sets[] = 'pNoteWebsite = ?';
        $values[] = (string)($row['pNoteWebsite'] ?? '');
    }
    if (import_table_has_column($pdo, 'import_produse', 'pNoteMarketplace')) {
        $sets[] = 'pNoteMarketplace = ?';
        $values[] = (string)($row['pNoteMarketplace'] ?? '');
    }

    $values[] = $importId;
    $stmt = $pdo->prepare(
        'UPDATE import_produse SET ' . implode(', ', $sets) . ' WHERE id = ?'
    );
    $stmt->execute($values);
}

function import_default_image_meta(array $product, array $images): array
{
    $raw = json_decode((string)($product['raw_json'] ?? '{}'), true);
    $rawSource = is_array($raw) ? trim((string)($raw['__image_source'] ?? '')) : '';
    $storedSource = trim((string)($product['pImageSource'] ?? ''));

    if ($storedSource !== '') {
        return [
            'source' => $storedSource,
            'query' => is_array($raw) ? trim((string)($raw['__image_query'] ?? ($product['pCode'] ?? ''))) : trim((string)($product['pCode'] ?? '')),
        ];
    }

    if ($rawSource !== '') {
        return [
            'source' => $rawSource,
            'query' => is_array($raw) ? trim((string)($raw['__image_query'] ?? ($product['pCode'] ?? ''))) : trim((string)($product['pCode'] ?? '')),
        ];
    }

    return [
        'source' => !empty($images) ? 'csv' : 'missing',
        'query' => trim((string)($product['pCode'] ?? '')),
    ];
}

function import_image_search_candidates(array $product): array
{
    return array_map(
        static fn(array $entry): string => (string) ($entry['code'] ?? ''),
        import_oem_cross_entries_from_product($product)
    );
}

/**
 * Împarte text OEM (virgulă, punct și virgulă, linie nouă).
 *
 * @return array<int, string>
 */
function import_oem_cross_split_blob(string $text): array
{
    $text = str_replace(["\r\n", "\r", "\n", ';', '|'], ',', $text);
    $parts = [];
    foreach (preg_split('/\s*,\s*/', $text) ?: [] as $part) {
        $part = trim($part);
        if ($part !== '') {
            $parts[] = $part;
        }
    }

    return $parts;
}

/**
 * Parsează o linie „BRAND : COD” sau cod simplu.
 *
 * @return array{code:string,brand:string,source:string}|null
 */
function import_oem_cross_parse_line(string $line, string $fallbackBrand = ''): ?array
{
    $line = trim($line);
    if ($line === '') {
        return null;
    }

    if (preg_match('/^([^:]+):\s*(.+)$/u', $line, $matches)) {
        $brand = trim($matches[1]);
        $code = trim(preg_replace('/\s+/u', '', $matches[2]) ?? $matches[2]);
        if ($code === '') {
            return null;
        }

        return ['code' => $code, 'brand' => $brand, 'source' => 'oem_line'];
    }

    $code = trim($line);
    if ($code === '') {
        return null;
    }

    return ['code' => $code, 'brand' => $fallbackBrand, 'source' => 'oem_plain'];
}

/**
 * Lista ordonată OEM / cross-reference pentru căutare imagine (importreview).
 *
 * @return array<int, array{code:string,brand:string,source:string}>
 */
function import_oem_cross_entries_from_product(array $product): array
{
    $entries = [];
    $seen = [];
    $productBrand = trim((string) ($product['pBrand'] ?? ''));

    $push = static function (?array $entry, string $source = '') use (&$entries, &$seen): void {
        if ($entry === null) {
            return;
        }
        $code = trim((string) ($entry['code'] ?? ''));
        if ($code === '') {
            return;
        }
        $norm = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $code) ?? '');
        if ($norm === '' || strlen($norm) < 3 || isset($seen[$norm])) {
            return;
        }
        $seen[$norm] = true;
        $entries[] = [
            'code' => $code,
            'brand' => trim((string) ($entry['brand'] ?? '')),
            'source' => $source !== '' ? $source : (string) ($entry['source'] ?? ''),
        ];
    };

    $pushLines = static function (array $lines, string $source) use ($push, $productBrand): void {
        foreach ($lines as $line) {
            if (!is_string($line)) {
                continue;
            }
            foreach (import_oem_cross_split_blob($line) as $part) {
                $push(import_oem_cross_parse_line($part, $productBrand), $source);
            }
        }
    };

    $raw = json_decode((string) ($product['raw_json'] ?? '{}'), true);
    if (is_array($raw)) {
        $summary = is_array($raw['product_summary'] ?? null) ? $raw['product_summary'] : [];
        $codesBlock = is_array($summary['codes'] ?? null) ? $summary['codes'] : [];
        foreach (['cod_principal', 'coduri_oem', 'toate_codurile', 'coduri_alternative'] as $key) {
            $value = $codesBlock[$key] ?? null;
            if (is_string($value) && $value !== '') {
                $pushLines([$value], 'summary_' . $key);
            } elseif (is_array($value)) {
                $pushLines($value, 'summary_' . $key);
            }
        }

        foreach (is_array($raw['rows'] ?? null) ? $raw['rows'] : [] as $rawRow) {
            if (!is_array($rawRow)) {
                continue;
            }
            foreach (import_extract_oem_candidates($rawRow) as $code) {
                $push(['code' => $code, 'brand' => $productBrand, 'source' => 'csv_row'], 'csv_row');
            }
        }
    }

    $pushLines(import_oem_cross_split_blob((string) ($product['pOem'] ?? '')), 'pOem');

    $primary = trim((string) ($product['pCode'] ?? ''));
    if ($primary !== '') {
        $push(['code' => $primary, 'brand' => $productBrand, 'source' => 'primary'], 'primary');
    }

    return $entries;
}

function import_oem_cross_article_matches(
    array $entry,
    ?array $article,
    string $productBrand
): bool {
    if ($article === null) {
        return false;
    }

    $entryBrand = trim((string) ($entry['brand'] ?? ''));
    $entryCode = trim((string) ($entry['code'] ?? ''));
    if ($entryCode === '') {
        return false;
    }

    if (!function_exists('tecdoc_normalize_code') || !function_exists('tecdoc_article_number')) {
        return true;
    }

    $articleCode = tecdoc_normalize_code(tecdoc_article_number($article));
    $wantedCode = tecdoc_normalize_code($entryCode);
    $codeOk = $wantedCode !== ''
        && ($articleCode === $wantedCode
            || str_contains($articleCode, $wantedCode)
            || str_contains($wantedCode, $articleCode));

    if (!$codeOk) {
        return false;
    }

    if ($entryBrand === '') {
        return true;
    }

    if (!function_exists('tecdoc_article_brand') || !function_exists('tecdoc_brand_matches')) {
        return true;
    }

    $articleBrand = tecdoc_article_brand($article);

    return tecdoc_brand_matches($entryBrand, $articleBrand)
        || ($productBrand !== '' && tecdoc_brand_matches($productBrand, $articleBrand));
}

/**
 * Caută imagine TecDoc iterând lista OEM / cross — cu concordanță cod + brand.
 *
 * @return array{url:string,source:string,query:string,api_error:?string,tecdoc_article_id?:string,tecdoc_remote_url?:string,oem_matched_code?:string,oem_matched_brand?:string}
 */
function import_find_image_from_oem_cross_list(array $product, bool $fast = false, string $queryLabel = ''): array
{
    $empty = [
        'url' => '',
        'source' => 'missing',
        'query' => $queryLabel,
        'api_error' => null,
    ];

    if (!function_exists('tecdoc_find_image_payload_from_search_codes')) {
        return $empty;
    }

    if (function_exists('tecdoc_clear_api_error')) {
        tecdoc_clear_api_error();
    }

    $entries = import_oem_cross_entries_from_product($product);
    if ($entries === []) {
        return $empty;
    }

    $productBrand = trim((string) ($product['pBrand'] ?? ''));
    $ttcBrand = import_ttc_art_brand_from_product($product);
    $lastError = null;
    $maxEntries = $fast ? 24 : 48;

    foreach (array_slice($entries, 0, $maxEntries) as $entry) {
        $code = trim((string) ($entry['code'] ?? ''));
        if ($code === '') {
            continue;
        }

        $entryBrand = trim((string) ($entry['brand'] ?? ''));
        $brandsToTry = array_values(array_unique([
            $entryBrand,
            $productBrand,
            $ttcBrand,
            '',
        ]));

        foreach ($brandsToTry as $tryBrand) {
            if (function_exists('tecdoc_api_should_stop') && tecdoc_api_should_stop()) {
                $msg = function_exists('tecdoc_api_unavailable_message') && tecdoc_api_unavailable_message() !== ''
                    ? tecdoc_api_unavailable_message()
                    : 'Cota RapidAPI depășită.';

                return array_merge($empty, ['api_error' => $msg]);
            }

            import_throttle_tecdoc_api_call();

            $searchCodes = function_exists('tecdoc_collect_image_search_codes')
                ? tecdoc_collect_image_search_codes($code, [])
                : [$code];

            $payload = tecdoc_find_image_payload_from_search_codes(
                $searchCodes,
                $code,
                $tryBrand,
                ['OENumber', 'IAMNumber', 'ArticleNumber', 'TradeNumber'],
                $fast ? 10 : 0
            );

            $apiError = function_exists('tecdoc_last_api_error') ? tecdoc_last_api_error() : null;
            if (is_array($apiError) && !empty($apiError['message'])) {
                $lastError = (string) $apiError['message'];
            }

            $article = is_array($payload['article'] ?? null) ? $payload['article'] : null;
            if (!import_oem_cross_article_matches($entry, $article, $productBrand)) {
                continue;
            }

            $apiUrl = trim((string) ($payload['url'] ?? ''));
            if ($apiUrl === '') {
                $articleBrand = trim((string) ($payload['article_brand'] ?? $tryBrand));
                $articleId = trim((string) ($payload['article_id'] ?? ''));
                if ($articleId !== '' && function_exists('import_base_image_url')) {
                    $apiUrl = import_base_image_url($articleBrand !== '' ? $articleBrand : $tryBrand, $articleId);
                }
            }

            if ($apiUrl === '' || import_image_is_placeholder($apiUrl)) {
                continue;
            }

            $matchedLabel = $entryBrand !== '' ? ($entryBrand . ' : ' . $code) : $code;
            $storedUrl = function_exists('tecdoc_download_image')
                ? tecdoc_download_image($apiUrl, $code)
                : '';

            return [
                'url' => $storedUrl !== '' ? $storedUrl : $apiUrl,
                'source' => 'tecdoc_api',
                'query' => $queryLabel !== '' ? $queryLabel : $matchedLabel,
                'api_error' => null,
                'tecdoc_article_id' => trim((string) ($payload['article_id'] ?? '')),
                'tecdoc_remote_url' => $apiUrl,
                'oem_matched_code' => $code,
                'oem_matched_brand' => $entryBrand,
            ];
        }
    }

    return array_merge($empty, ['api_error' => $lastError]);
}

function import_find_image_legacy_fallbacks(
    array $product,
    ?array $tecdocFiles = null,
    ?array $sharedLookup = null
): array {
    $servicePath = dirname(__DIR__, 4) . '/lib/Scraper/ImageSearchService.php';
    if (is_file($servicePath)) {
        require_once $servicePath;
        \ImageSearchService::boot();
    }

    $apiResult = [
        'url' => '',
        'source' => 'missing',
        'query' => trim((string) ($product['pCode'] ?? '')),
        'api_error' => null,
    ];

    $tecdocFiles = $tecdocFiles ?? import_resolve_uploaded_tecdoc_files();
    $brand = import_ttc_art_brand_from_product($product);
    $query = trim((string) ($product['pCode'] ?? ''));

    require_once dirname(__DIR__, 4) . '/system/import-image-validate.php';
    $searchQuery = trim($brand . ' ' . $query);
    if ($searchQuery !== '' && function_exists('serpapi_find_image_url')) {
        $serpUrl = serpapi_find_image_url($searchQuery . ' piese auto rulment');
        if ($serpUrl !== '' && !besoiu_import_image_url_host_blocked($serpUrl)) {
            $storedUrl = function_exists('tecdoc_download_image')
                ? tecdoc_download_image($serpUrl, $query !== '' ? $query : trim((string) ($product['pCode'] ?? '')))
                : '';
            if ($storedUrl !== '' && besoiu_import_image_local_upload_exists($storedUrl)) {
                return [
                    'url' => $storedUrl,
                    'source' => 'tecdoc_api',
                    'query' => $searchQuery,
                    'api_error' => null,
                ];
            }
        }
    }

    if (function_exists('besoiu_image_search_source_enabled') && besoiu_image_search_source_enabled('caietcomenzi')) {
        $product = import_apply_caietcomenzi_image($product);
        $existingUrl = import_row_image_url($product);
        if ($existingUrl !== '' && import_image_url_is_trusted($existingUrl, (string) ($product['pImageSource'] ?? 'caietcomenzi'))) {
            return [
                'url' => $existingUrl,
                'source' => (string) ($product['pImageSource'] ?? 'caietcomenzi'),
                'query' => trim((string) ($product['pCode'] ?? '')),
            ];
        }
    }

    $record = null;
    if ($tecdocFiles !== [] || $sharedLookup !== null) {
        $record = import_tecdoc_find_record_for_product($product, $tecdocFiles, $sharedLookup);
        if ($record !== null) {
            $query = import_tecdoc_matched_query_for_product($product, $sharedLookup ?? []);
            if ($query === '') {
                $query = trim((string) ($product['pCode'] ?? ''));
            }

            $recordBrand = trim((string) ($record['art_brand'] ?? $brand));
            $ttcId = trim((string) ($record['ttc_art_id'] ?? ''));
            if ($ttcId !== '') {
                $imageUrl = import_base_image_url($recordBrand, $ttcId);
                if ($imageUrl !== '') {
                    return [
                        'url' => $imageUrl,
                        'source' => 'caietcomenzi',
                        'query' => $query,
                    ];
                }
            }
        }
    }

    $ttcId = import_ttc_art_id_from_product($product);
    if ($ttcId !== '') {
        $imageUrl = import_base_image_url($brand, $ttcId);
        if ($imageUrl !== '') {
            return [
                'url' => $imageUrl,
                'source' => 'caietcomenzi',
                'query' => $query,
            ];
        }
    }

    return $apiResult;
}

function import_find_image_for_product(array $product, ?array $tecdocFiles = null, ?array $sharedLookup = null, bool $fastMode = false, bool $force = false, array $extraOpts = []): array
{
    $servicePath = dirname(__DIR__, 4) . '/lib/Scraper/ImageSearchService.php';
    if (!is_file($servicePath)) {
        return import_find_image_legacy_fallbacks($product, $tecdocFiles, $sharedLookup);
    }

    require_once $servicePath;

    return \ImageSearchService::findImage($product, array_merge([
        'force' => $force,
        'fast_mode' => $fastMode,
        'tecdoc_files' => $tecdocFiles,
        'shared_lookup' => $sharedLookup,
    ], $extraOpts));
}

function import_norm(string $value): string
{
    $value = trim(mb_strtolower($value, 'UTF-8'));
    $value = strtr($value, [
        'ă' => 'a', 'â' => 'a', 'î' => 'i', 'ș' => 's', 'ş' => 's', 'ț' => 't', 'ţ' => 't',
        '-' => ' ', '_' => ' ', '/' => ' ', '.' => ' ', '(' => ' ', ')' => ' ',
    ]);
    return preg_replace('/\s+/u', ' ', $value) ?? $value;
}

function import_norm_token(string $token): string
{
    $token = import_norm($token);
    $token = preg_replace('/[^a-z0-9]/', '', $token) ?? $token;
    if ($token === '') {
        return '';
    }

    foreach (['urilor', 'ilor', 'elor', 'urile', 'ile', 'ele', 'ului', 'ul', 'le', 'lor', 'ii', 'ia', 'ie', 'i', 'e'] as $suffix) {
        if (strlen($token) > 4 && str_ends_with($token, $suffix)) {
            $token = substr($token, 0, -strlen($suffix));
            break;
        }
    }

    return trim($token);
}

function import_tokens(string $text): array
{
    $norm = import_norm($text);
    if ($norm === '') {
        return [];
    }

    $parts = preg_split('/\s+/u', $norm) ?: [];
    $stopwords = [
        'si', 'sau', 'la', 'de', 'din', 'cu', 'pentru', 'pe', 'un', 'o', 'the', 'and', 'or',
        'car', 'auto', 'set', 'kit', 'nou', 'compatibilitati', 'compatibil', 'coduri', 'oem',
    ];
    $stopSet = array_fill_keys($stopwords, true);
    $tokens = [];
    $seen = [];
    foreach ($parts as $part) {
        $token = import_norm_token((string)$part);
        if ($token === '' || strlen($token) < 3 || isset($stopSet[$token])) {
            continue;
        }
        $seenKey = 't:' . $token;
        if (isset($seen[$seenKey])) {
            continue;
        }
        $seen[$seenKey] = true;
        $tokens[] = $token;
    }

    return $tokens;
}

function import_best_subcategory_fuzzy(string $text): ?array
{
    $textTokens = import_tokens($text);
    if (!$textTokens) {
        return null;
    }
    $textSet = array_fill_keys($textTokens, true);

    $best = null;
    foreach (import_taxonomy()['subcategories'] as $sub) {
        $subTokens = import_tokens((string)$sub['label'] . ' ' . (string)($sub['slug'] ?? ''));
        if (!$subTokens) {
            continue;
        }
        $common = 0;
        foreach ($subTokens as $token) {
            if (isset($textSet[$token])) {
                $common++;
            }
        }
        if ($common === 0) {
            continue;
        }

        $precision = $common / max(count($subTokens), 1);
        $coverage = $common / max(min(count($textTokens), count($subTokens)), 1);
        $score = ($precision * 0.7) + ($coverage * 0.3);

        if ($best === null || $score > $best['score']) {
            $best = [
                'subcategory' => (string)$sub['label'],
                'category' => (string)$sub['category'],
                'score' => $score,
                'common' => $common,
            ];
        }
    }

    if ($best === null) {
        return null;
    }

    if ($best['score'] >= 0.65 || ($best['score'] >= 0.5 && $best['common'] >= 2)) {
        return $best;
    }

    return null;
}

function import_taxonomy(): array
{
    static $cache = null;
    if (is_array($cache)) return $cache;

    $pdo = Database::getDB();
    $rows = $pdo->query("SELECT id,parent_id,label,slug,type FROM categorii WHERE is_active = 1 ORDER BY sort_order ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $categoriesById = [];
    $subcategories = [];
    foreach ($rows as $row) {
        $categoriesById[(int)$row['id']] = $row;
    }
    foreach ($rows as $row) {
        if (($row['type'] ?? '') !== 'subcategorie') continue;
        $parent = $categoriesById[(int)($row['parent_id'] ?? 0)] ?? null;
        $subcategories[] = [
            'label' => (string)$row['label'],
            'slug' => (string)($row['slug'] ?? ''),
            'norm' => import_norm((string)$row['label'] . ' ' . (string)($row['slug'] ?? '')),
            'category' => (string)($parent['label'] ?? ''),
        ];
    }
    $cache = ['subcategories' => $subcategories];
    return $cache;
}

function import_match_taxonomy(string $text): array
{
    $norm = import_norm($text);
    if ($norm === '') {
        return ['subcategory' => '', 'category' => ''];
    }
    foreach (import_taxonomy()['subcategories'] as $sub) {
        $subNorm = (string)$sub['norm'];
        if ($subNorm !== '' && (str_contains($norm, $subNorm) || str_contains($subNorm, $norm))) {
            return ['subcategory' => (string)$sub['label'], 'category' => (string)$sub['category']];
        }
        $labelNorm = import_norm((string)$sub['label']);
        if ($labelNorm !== '' && str_contains($norm, $labelNorm)) {
            return ['subcategory' => (string)$sub['label'], 'category' => (string)$sub['category']];
        }
    }

    $fuzzy = import_best_subcategory_fuzzy($text);
    if ($fuzzy !== null) {
        return ['subcategory' => (string)$fuzzy['subcategory'], 'category' => (string)$fuzzy['category']];
    }

    return ['subcategory' => '', 'category' => ''];
}

function import_category_for_subcategory(string $subcategory): string
{
    $subcategoryNorm = import_norm($subcategory);
    if ($subcategoryNorm === '') {
        return '';
    }

    foreach (import_taxonomy()['subcategories'] as $sub) {
        $labelNorm = import_norm((string)($sub['label'] ?? ''));
        if ($labelNorm !== '' && $labelNorm === $subcategoryNorm) {
            return trim((string)($sub['category'] ?? ''));
        }
    }

    return '';
}

function import_category_from_keywords(string $text): string
{
    $tokens = import_tokens($text);
    if (!$tokens) {
        return '';
    }
    $set = array_fill_keys($tokens, true);

    $rules = [
        'Frâne' => ['fran', 'disc', 'etrier', 'placut', 'sabot', 'tambur', 'abs', 'uzur'],
        'Filtre' => ['filtr', 'polen', 'habitaclu'],
        'Ulei & Lichide' => ['ulei', 'lichid', 'antigel'],
        'Suspensie' => ['amortiz', 'suspens', 'arc', 'bielet', 'articulat', 'rulment', 'brat'],
        'Motor' => ['termostat', 'turbo', 'chiulas', 'pompa', 'distribut', 'racir', 'incalz', 'furtun'],
        'Electric' => ['alternator', 'electromotor', 'buj', 'bobin', 'senzor', 'releu', 'sigurant', 'bater'],
        'Caroserie' => ['far', 'stop', 'oglind', 'capot', 'arip', 'stergator', 'bar'],
        'Transmisie' => ['ambreiaj', 'planetar', 'cardan', 'volant', 'sincron', 'cut', 'transmis'],
    ];

    $bestCategory = '';
    $bestScore = 0;
    foreach ($rules as $category => $keywords) {
        $score = 0;
        foreach ($keywords as $keyword) {
            foreach ($tokens as $token) {
                $tokenText = (string)$token;
                $keywordText = (string)$keyword;
                if ($tokenText === $keywordText || str_contains($tokenText, $keywordText) || str_contains($keywordText, $tokenText)) {
                    $score++;
                    break;
                }
            }
        }
        if ($score > $bestScore) {
            $bestScore = $score;
            $bestCategory = $category;
        }
    }

    return $bestScore >= 1 ? $bestCategory : '';
}

function import_extract_oem_candidates(array $raw): array
{
    $values = [];
    foreach ([
        'art cross', 'art code 2', 'art code 1', 'coduri echivalente',
        'oem', 'oe', 'oem number', 'oem numbers', 'oe number', 'oe numbers',
        'references', 'reference'
    ] as $key) {
        $value = trim((string)($raw[$key] ?? ''));
        if ($value !== '') $values[] = $value;
    }
    $codes = [];
    foreach ($values as $value) {
        if (preg_match_all('/[A-Z0-9][A-Z0-9.\-\/]{3,}/i', $value, $matches)) {
            foreach ($matches[0] as $match) $codes[] = trim((string)$match);
        }
    }
    $unique = [];
    foreach ($codes as $code) {
        $norm = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $code) ?? $code);
        if ($norm === '' || isset($unique[$norm])) continue;
        $unique[$norm] = $code;
    }
    return array_values($unique);
}

function import_extract_codes_from_text(string $value): array
{
    $value = trim($value);
    if ($value === '') {
        return [];
    }

    if (!preg_match_all('/[A-Z0-9][A-Z0-9.\-\/]{3,}/i', $value, $matches)) {
        return [];
    }

    $unique = [];
    foreach ($matches[0] as $code) {
        $code = trim((string)$code);
        $normalized = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $code) ?? $code);
        if ($normalized === '' || isset($unique[$normalized])) {
            continue;
        }
        $unique[$normalized] = $code;
    }

    return array_values($unique);
}

function import_extract_alternative_codes(array $raw): array
{
    $values = [];
    foreach ([
        'art code 1',
        'art code 2',
        'article code',
        'article no',
        'art cross',
        'coduri echivalente',
        'references',
        'reference',
        'oem',
        'oe',
        'oem number',
        'oe number',
    ] as $key) {
        $value = trim((string)($raw[$key] ?? ''));
        if ($value !== '') {
            $values[] = $value;
        }
    }

    $codes = [];
    foreach ($values as $value) {
        foreach (import_extract_codes_from_text($value) as $code) {
            $normalized = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $code) ?? $code);
            if ($normalized === '' || isset($codes[$normalized])) {
                continue;
            }
            $codes[$normalized] = $code;
        }
    }

    return array_values($codes);
}

function import_extract_mileage_km(array $raw): string
{
    foreach (['kilometraj', 'km', 'mileage', 'odometer'] as $key) {
        $value = trim((string)($raw[$key] ?? ''));
        if ($value === '') {
            continue;
        }
        $digits = preg_replace('/[^0-9]/', '', $value) ?? '';
        if ($digits !== '' && (int)$digits > 0) {
            return (string)((int)$digits);
        }
    }

    $text = implode(' | ', array_filter([
        (string)($raw['parts info'] ?? ''),
        (string)($raw['terms of use'] ?? ''),
        (string)($raw['description'] ?? ''),
        (string)($raw['descriere'] ?? ''),
    ]));

    if (preg_match('/\b([0-9]{1,3}(?:[ .][0-9]{3})+|[0-9]{4,7})\s*(km|kilometri|kilometraj)\b/ui', $text, $match)) {
        $digits = preg_replace('/[^0-9]/', '', (string)($match[1] ?? '')) ?? '';
        if ($digits !== '' && (int)$digits > 0) {
            return (string)((int)$digits);
        }
    }

    return '';
}

function import_short_text(string $value, int $limit = 180): string
{
    $value = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    if ($value === '') {
        return '';
    }

    if (mb_strlen($value, 'UTF-8') <= $limit) {
        return $value;
    }

    return mb_substr($value, 0, $limit, 'UTF-8') . '...';
}

function import_extract_technical_pairs(array $raw): array
{
    $pairs = [];
    $knownFields = [
        'car typ' => 'Tip motorizare',
        'car body' => 'Caroserie',
        'car of year' => 'An fabricație de la',
        'car to year' => 'An fabricație până la',
        'car kw' => 'Putere (kW)',
        'car pm' => 'Putere (CP)',
        'car cc' => 'Capacitate cilindrică (cc)',
        'parts info' => 'Info piesă',
        'terms of use' => 'Observații montaj',
    ];

    foreach ($knownFields as $key => $label) {
        $value = import_short_text((string)($raw[$key] ?? ''));
        if ($value === '') {
            continue;
        }
        $pairKey = import_norm($label) . '|' . import_norm($value);
        $pairs[$pairKey] = ['label' => $label, 'value' => $value];
    }

    foreach ($raw as $key => $value) {
        $normalizedKey = import_norm((string)$key);
        if ($normalizedKey === '') {
            continue;
        }

        if (
            !str_contains($normalizedKey, 'engine')
            && !str_contains($normalizedKey, 'fuel')
            && !str_contains($normalizedKey, 'emis')
            && !str_contains($normalizedKey, 'cyl')
        ) {
            continue;
        }

        $cleanValue = import_short_text((string)$value);
        if ($cleanValue === '') {
            continue;
        }

        $label = ucwords($normalizedKey);
        $pairKey = import_norm($label) . '|' . import_norm($cleanValue);
        $pairs[$pairKey] = ['label' => $label, 'value' => $cleanValue];
    }

    return array_values($pairs);
}

function import_description_header(array $base, array $mileageList): string
{
    $name = trim((string)($base['pName'] ?? ''));
    $brand = trim((string)($base['pBrand'] ?? ''));
    $marca = trim((string)($base['pMarca'] ?? ''));
    $subcategory = trim((string)($base['pSubcategory'] ?? ''));
    $category = trim((string)($base['pCategory'] ?? ''));
    $code = trim((string)($base['pCode'] ?? ''));
    $km = trim((string)($mileageList[0] ?? ''));

    $brandToken = $marca !== '' ? $marca : $brand;

    $pieceLabel = '';
    if ($subcategory !== '') {
        $pieceLabel = $subcategory;
    } elseif ($category !== '') {
        $pieceLabel = $category;
    } elseif ($name !== '') {
        $words = preg_split('/\s+/u', $name) ?: [];
        $pieceLabel = trim(implode(' ', array_slice($words, 0, 2)));
    }

    if ($pieceLabel !== '' && $brandToken !== '') {
        $title = trim($pieceLabel . ' ' . $brandToken);
    } elseif ($pieceLabel !== '') {
        $title = $pieceLabel;
    } elseif ($name !== '' && $brandToken !== '' && !str_contains(mb_strtolower($name, 'UTF-8'), mb_strtolower($brandToken, 'UTF-8'))) {
        $title = trim($name . ' ' . $brandToken);
    } else {
        $title = $name !== '' ? $name : ($brandToken !== '' ? 'Piesă auto ' . $brandToken : 'Piesă auto');
    }

    $title = function_exists('mb_strtoupper') ? mb_strtoupper($title, 'UTF-8') : strtoupper($title);
    $codeText = $code !== '' ? $code : 'N/A';
    $kmText = $km !== '' ? ($km . ' km') : 'nespecificat';

    return $title . ' - Cod produs: ' . $codeText . ' - Kilometraj: ' . $kmText;
}

function import_compatibility_line(array $raw): string
{
    $brand = trim((string)($raw['car brand'] ?? ''));
    $model = trim((string)($raw['car model'] ?? ''));
    $typ = trim((string)($raw['car typ'] ?? ''));
    $body = trim((string)($raw['car body'] ?? ''));
    $from = trim((string)($raw['car of year'] ?? ''));
    $to = trim((string)($raw['car to year'] ?? ''));
    $kw = trim((string)($raw['car kw'] ?? ''));
    $pm = trim((string)($raw['car pm'] ?? ''));
    $years = trim(($from !== '' ? $from : '?') . '-' . ($to !== '' ? $to : '?'));
    $power = trim(($kw !== '' ? $kw . ' kW' : '') . ($pm !== '' ? ' / ' . $pm . ' CP' : ''));
    return trim(implode(' | ', array_filter([$brand, $model, $typ, $body, $years, $power])));
}

function import_group_key(array $mapped): string
{
    $code = trim((string)($mapped['pCode'] ?? ''));
    if ($code !== '') return strtoupper(preg_replace('/[^A-Z0-9]/i', '', $code) ?? $code);
    return md5(trim((string)($mapped['pBrand'] ?? '')) . '|' . trim((string)($mapped['pName'] ?? '')));
}

function import_build_product_from_supplier_tecdoc(
    array $entry,
    ?\Evasystem\Controllers\AdaosComercial\AdaosComercialService $markupService = null,
    bool $useTecdocApi = true,
    ?array $tecdocRecord = null,
    array $tecdocRows = [],
    array $priceIndex = []
): ?array
{
    $code = trim((string)($entry['code'] ?? ''));
    $brand = trim((string)($entry['brand'] ?? ''));
    $supplierName = trim((string)($entry['name'] ?? ''));
    $supplierType = trim((string)($entry['supplier'] ?? ''));
    $netPrice = (float)($entry['net_price'] ?? 0);

    if ($tecdocRows === [] && $tecdocRecord !== null) {
        $tecdocRows = [import_tecdoc_record_to_raw_row($tecdocRecord)];
    }

    $fileCross = '';
    $fileSpecs = '';
    $fileName = '';
    $fileTtcId = '';
    $fileBrand = '';
    $fileCode = '';
    if ($tecdocRecord !== null) {
        $fileCross = trim((string)($tecdocRecord['art_cross'] ?? ''));
        $fileSpecs = trim((string)($tecdocRecord['parts_info'] ?? ''));
        $fileName = trim((string)($tecdocRecord['art_name'] ?? ''));
        $fileTtcId = trim((string)($tecdocRecord['ttc_art_id'] ?? ''));
        $fileBrand = trim((string)($tecdocRecord['art_brand'] ?? ''));
        $fileCode = trim((string)($tecdocRecord['art_code_1'] ?? ''));
    } elseif ($tecdocRows !== []) {
        $firstRecord = import_tecdoc_record_from_row($tecdocRows[0]);
        $fileCross = trim((string)($firstRecord['art_cross'] ?? ''));
        $fileSpecs = trim((string)($firstRecord['parts_info'] ?? ''));
        $fileName = trim((string)($firstRecord['art_name'] ?? ''));
        $fileTtcId = trim((string)($firstRecord['ttc_art_id'] ?? ''));
        $fileBrand = trim((string)($firstRecord['art_brand'] ?? ''));
        $fileCode = trim((string)($firstRecord['art_code_1'] ?? ''));
    }

    $oemCodes = import_tecdoc_cross_to_oem_list($fileCross);
    $finalCode = $fileCode !== '' ? $fileCode : $code;
    $finalBrand = $fileBrand !== '' ? $fileBrand : $brand;
    $rawName = $fileName !== '' ? $fileName : ($supplierName !== '' ? $supplierName : trim('Piesa ' . $finalBrand . ' ' . $finalCode));
    $specsText = $fileSpecs;

    $taxonomy = import_match_taxonomy($rawName . ' ' . $specsText);
    $category = $taxonomy['category'] !== '' ? $taxonomy['category'] : import_category_from_keywords($rawName . ' ' . $specsText);
    $subcategory = $taxonomy['subcategory'];

    $base = [
        'pName' => $rawName,
        'pCode' => $finalCode,
        'pBrand' => $finalBrand,
        'pMarca' => '',
        'pModel' => '',
        'pMotorizare' => '',
        'pCar' => $finalBrand,
        'pStock' => '0',
        'pCategory' => $category,
        'pSubcategory' => $subcategory,
        'pCompatibilitati' => '',
        'pOem' => implode(', ', array_slice($oemCodes, 0, 30)),
        'pSupplier' => $supplierType,
        'pState' => 'Nou',
        'pCity' => '',
        'pSpecs' => $specsText,
        'pImages' => '[]',
        'pNote' => '',
    ];

    $groupRows = $tecdocRows;
    if ($groupRows === []) {
        $crossForBase = $fileCross !== '' ? $fileCross : implode('|', $oemCodes);
        $groupRows = [[
            'art code 1' => $finalCode,
            'art brand' => $finalBrand,
            'art name' => $rawName,
            'parts info' => $specsText,
            'art cross' => $crossForBase,
            'ttc art id' => $fileTtcId,
        ]];
    }

    if ($netPrice <= 0 && $priceIndex !== [] && import_base_should_skip_without_price($groupRows, $priceIndex)) {
        return null;
    }

    $importBaseApplied = import_base_apply_to_product($base, ['rows' => $groupRows], $priceIndex);
    if (!$importBaseApplied) {
        return null;
    }

    if (trim((string)($base['pOem'] ?? '')) === '') {
        $base['pOem'] = import_supplier_format_oem_field($oemCodes, $finalBrand, $finalCode);
    }

    if ($useTecdocApi && !tecdoc_note_looks_complete((string)($base['pNote'] ?? ''))) {
        if (function_exists('import_throttle_tecdoc_api_call')) {
            import_throttle_tecdoc_api_call();
        }
        $descResult = tecdoc_build_product_description(
            $finalCode,
            $finalBrand,
            (string)($base['pName'] ?? $rawName),
            (int)$fileTtcId,
            (string)($base['pOem'] ?? '')
        );
        if (trim((string)($descResult['html'] ?? '')) !== '') {
            besoiu_apply_dual_product_descriptions($base, (string) $descResult['html']);
        }
    }

    if ($tecdocRows === [] && $tecdocRecord === null && trim((string)($base['pNote'] ?? '')) === '') {
        $base['pNote'] = '<p>Preview: nu s-a găsit match în fișierele CSV TecDoc încărcate pentru codul '
            . htmlspecialchars($code, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '.</p>';
    }

    $finalName = (string)($base['pName'] ?? $rawName);
    $basePrice = import_supplier_net_to_base($netPrice, $supplierType);
    $base['pBasePrice'] = rtrim(rtrim(number_format($basePrice, 2, '.', ''), '0'), '.');

    if ($markupService !== null) {
        $pricing = $markupService->applyAutomaticMarkup($base);
        $base = array_merge($base, $pricing['data']);
    } else {
        $base['pPrice'] = $base['pBasePrice'];
    }

    $imageSource = 'missing';
    $decodedImages = json_decode((string)($base['pImages'] ?? '[]'), true);
    if (is_array($decodedImages) && trim((string)($decodedImages[0] ?? '')) !== '') {
        $imageSource = str_contains((string)$decodedImages[0], 'caietcomenzi.ro/PozeEmag') ? 'caietcomenzi' : 'csv';
    }

    $base['raw_json'] = json_encode([
        'schema' => 'product_import_v2',
        'import_mode' => 'supplier_files',
        'import_base_applied' => true,
        'tecdoc_api' => [
            'found' => false,
            'skipped' => true,
            'disabled' => true,
            'reason' => 'csv_files_only',
            'query_code' => $code,
            'query_brand' => $brand,
        ],
        'tecdoc_file' => [
            'found' => $tecdocRecord !== null || $tecdocRows !== [],
            'rows_count' => count($tecdocRows),
            'art_code_1' => $fileCode !== '' ? $fileCode : null,
            'art_brand' => $fileBrand !== '' ? $fileBrand : null,
            'ttc_art_id' => $fileTtcId !== '' ? $fileTtcId : null,
        ],
        'supplier_price' => [
            'supplier' => $supplierType,
            'net_price' => $netPrice,
            'purchase_base' => $basePrice,
            'base_price_with_vat' => $basePrice,
            'matched_code' => $code,
            'matched_via' => 'supplier_catalog',
            'markup_rule_id' => $base['pMarkupRuleId'] ?? null,
            'markup_rule_name' => $base['pMarkupRuleName'] ?? null,
        ],
        'product_summary' => import_base_product_summary_from_rows($groupRows, $base),
        'rows' => $groupRows,
        '__image_source' => $imageSource,
        '__image_query' => $code,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    return $base;
}

function import_aggregate_rows(
    array $rows,
    int $maxPreview = 500,
    array $priceIndex = [],
    ?\Evasystem\Controllers\AdaosComercial\AdaosComercialService $markupService = null
): array
{
    $groups = [];
    foreach ($rows as $row) {
        $mapped = map_product_row($row, '');
        if (($mapped['pName'] ?? '') === '' && ($mapped['pCode'] ?? '') === '') continue;

        $key = import_group_key($mapped);
        if (!isset($groups[$key]) && count($groups) >= $maxPreview) {
            break;
        }
        if (!isset($groups[$key])) {
            $groups[$key] = [
                'base' => $mapped,
                'rows' => [],
                'compat' => [],
                'marca' => [],
                'model' => [],
                'motorizare' => [],
                'oem' => [],
                'alt_codes' => [],
                'mileages' => [],
                'technical' => [],
                'notes' => [],
                'prices' => [],
            ];
        }

        $raw = array_change_key_case($row, CASE_LOWER);
        $groups[$key]['rows'][] = $raw;

        $marca = trim((string)($raw['car brand'] ?? ''));
        $model = trim((string)($raw['car model'] ?? ''));
        $motorizare = trim((string)($raw['car typ'] ?? ''));
        if ($marca !== '') $groups[$key]['marca'][$marca] = true;
        if ($model !== '') $groups[$key]['model'][$model] = true;
        if ($motorizare !== '') $groups[$key]['motorizare'][$motorizare] = true;

        $compat = import_compatibility_line($raw);
        if ($compat !== '') $groups[$key]['compat'][$compat] = true;

        foreach (import_extract_oem_candidates($raw) as $oem) {
            $groups[$key]['oem'][$oem] = true;
        }
        foreach (import_extract_alternative_codes($raw) as $code) {
            $groups[$key]['alt_codes'][$code] = true;
        }

        $mileageKm = import_extract_mileage_km($raw);
        if ($mileageKm !== '') {
            $groups[$key]['mileages'][$mileageKm] = true;
        }

        foreach (import_extract_technical_pairs($raw) as $pair) {
            $pairLabel = trim((string)($pair['label'] ?? ''));
            $pairValue = trim((string)($pair['value'] ?? ''));
            if ($pairLabel === '' || $pairValue === '') {
                continue;
            }
            $pairKey = import_norm($pairLabel) . '|' . import_norm($pairValue);
            $groups[$key]['technical'][$pairKey] = [
                'label' => $pairLabel,
                'value' => $pairValue,
            ];
        }

        foreach ([
            trim((string)($raw['parts info'] ?? '')),
            trim((string)($raw['terms of use'] ?? '')),
            trim((string)($raw['art cross'] ?? '')),
        ] as $note) {
            if ($note !== '') $groups[$key]['notes'][$note] = true;
        }

        $mappedPrice = trim((string)($mapped['pPrice'] ?? ''));
        if ($mappedPrice !== '') {
            $groups[$key]['prices'][] = $mappedPrice;
        }
    }

    $products = [];
    foreach ($groups as $group) {
        $base = $group['base'];
        if (trim((string)($base['pPrice'] ?? '')) === '' && !empty($group['prices'][0])) {
            $base['pPrice'] = (string)$group['prices'][0];
        }
        $allText = implode(' | ', array_filter([
            (string)($base['pName'] ?? ''),
            (string)($base['pSubcategory'] ?? ''),
            (string)($base['pCategory'] ?? ''),
            ...array_keys($group['notes']),
        ]));
        $taxonomy = import_match_taxonomy($allText);
        $compatList = array_values(array_keys($group['compat']));
        $oemList = array_values(array_keys($group['oem']));
        $altCodesList = array_values(array_keys($group['alt_codes']));
        $mileageList = array_values(array_keys($group['mileages']));
        usort($mileageList, static fn(string $a, string $b): int => (int)$a <=> (int)$b);
        $technicalPairs = array_values($group['technical']);
        $marcaList = array_values(array_keys($group['marca']));
        $modelList = array_values(array_keys($group['model']));
        $motorizareList = array_values(array_keys($group['motorizare']));

        $fullDescription = [];
        if (!empty($group['notes'])) {
            $fullDescription[] = implode("\n\n", array_values(array_keys($group['notes'])));
        }
        if ($oemList) {
            $fullDescription[] = "Coduri OEM / echivalente:\n" . implode(', ', $oemList);
        }
        if ($altCodesList) {
            $fullDescription[] = "Coduri alternative:\n" . implode(', ', $altCodesList);
        }
        if ($mileageList) {
            $fullDescription[] = "Kilometraj declarat (dacă există în fișier):\n" . implode(', ', $mileageList) . ' km';
        }
        if ($technicalPairs) {
            $technicalLines = [];
            foreach (array_slice($technicalPairs, 0, 12) as $pair) {
                $technicalLines[] = '- ' . (string)$pair['label'] . ': ' . (string)$pair['value'];
            }
            if ($technicalLines) {
                $fullDescription[] = "Date tehnice:\n" . implode("\n", $technicalLines);
            }
        }
        if ($compatList) {
            $fullDescription[] = "Compatibilitati:\n- " . implode("\n- ", $compatList);
        }

        $base['pMarca'] = implode(', ', $marcaList);
        $base['pModel'] = implode(', ', $modelList);
        $base['pMotorizare'] = implode("\n", $motorizareList);
        $base['pSubcategory'] = $taxonomy['subcategory'] !== ''
            ? $taxonomy['subcategory']
            : trim((string)($base['pSubcategory'] ?? ''));
        $base['pCategory'] = $taxonomy['category'] !== ''
            ? $taxonomy['category']
            : trim((string)($base['pCategory'] ?? ''));
        if ($base['pCategory'] === '' && $base['pSubcategory'] !== '') {
            $base['pCategory'] = import_category_for_subcategory((string)$base['pSubcategory']);
        }
        if ($base['pCategory'] === '') {
            $base['pCategory'] = import_category_from_keywords($allText);
        }
        $base['pCompatibilitati'] = implode("\n", $compatList);

        $allCodes = [];
        foreach (array_merge($oemList, $altCodesList) as $code) {
            $cleanCode = trim((string)$code);
            if ($cleanCode === '') {
                continue;
            }
            $normalizedCode = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $cleanCode) ?? $cleanCode);
            if ($normalizedCode === '' || isset($allCodes[$normalizedCode])) {
                continue;
            }
            $allCodes[$normalizedCode] = $cleanCode;
        }
        $base['pOem'] = implode(', ', array_values($allCodes));

        $descriptionHeader = import_description_header($base, $mileageList);
        $bodyDescription = trim(implode("\n\n", $fullDescription));
        $marketplaceNote = trim($descriptionHeader . ($bodyDescription !== '' ? "\n\n" . $bodyDescription : ''));
        besoiu_apply_dual_product_descriptions($base, $marketplaceNote);
        $base['pCar'] = $base['pMarca'] ?: ($base['pBrand'] ?? '');

        $firstRawRow = $group['rows'][0] ?? [];
        $base['pSpecs'] = import_format_parts_info($firstRawRow);
        if (trim((string)($base['pStock'] ?? '')) === '') {
            $base['pStock'] = '0';
        }

        if (!import_base_apply_to_product($base, $group, $priceIndex)) {
            continue;
        }

        $base = import_apply_caietcomenzi_image($base);

        $imageSource = trim((string)($base['pImageSource'] ?? ''));
        if ($imageSource === '') {
            $imageSource = 'missing';
            $decodedImages = json_decode((string)($base['pImages'] ?? '[]'), true);
            if (is_array($decodedImages) && $decodedImages !== []) {
                $firstImage = trim((string)($decodedImages[0] ?? ''));
                if ($firstImage !== '') {
                    $imageSource = str_contains($firstImage, 'caietcomenzi.ro/PozeEmag') ? 'caietcomenzi' : 'import';
                }
            }
        }
        $base['pImageSource'] = $imageSource;

        $productSummary = [
            'identity' => [
                'name' => (string)($base['pName'] ?? ''),
                'brand_produs' => (string)($base['pBrand'] ?? ''),
                'cod_produs' => (string)($base['pCode'] ?? ''),
                'furnizor' => (string)($base['pSupplier'] ?? ''),
            ],
            'vehicle' => [
                'marca_auto' => (string)($base['pMarca'] ?? ''),
                'model_auto' => (string)($base['pModel'] ?? ''),
                'motorizare' => (string)($base['pMotorizare'] ?? ''),
                'kilometraj_km' => $mileageList[0] ?? null,
            ],
            'classification' => [
                'categorie' => (string)($base['pCategory'] ?? ''),
                'subcategorie' => (string)($base['pSubcategory'] ?? ''),
            ],
            'specs' => (string)($base['pSpecs'] ?? ''),
            'ean' => trim((string)($firstRawRow['art_ean'] ?? '')),
            'codes' => [
                'cod_principal' => (string)($base['pCode'] ?? ''),
                'coduri_oem' => $oemList,
                'coduri_alternative' => $altCodesList,
                'toate_codurile' => array_values($allCodes),
            ],
            'technical_data' => $technicalPairs,
        ];

        $base['raw_json'] = json_encode([
            'schema' => 'product_import_v2',
            'product_summary' => $productSummary,
            'rows' => $group['rows'],
            '__image_source' => $imageSource,
            '__image_query' => (string)($base['pCode'] ?? ''),
            'import_base_applied' => true,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($priceIndex !== []) {
            $base = import_apply_supplier_pricing($base, $priceIndex, $markupService);
        } elseif ($markupService !== null && trim((string)($base['pPrice'] ?? '')) !== '') {
            $base['pBasePrice'] = trim((string)($base['pBasePrice'] ?? $base['pPrice']));
            $pricing = $markupService->applyAutomaticMarkup($base);
            $base = array_merge($base, $pricing['data']);
        }

        $products[] = $base;
    }

    return $products;
}

if (!defined('IMPORT_PRODUCE_SKIP_HTTP')) {
try {
    $contentType = (string)($_SERVER['CONTENT_TYPE'] ?? '');
    $isJson = str_contains($contentType, 'application/json');

    if ($isJson) {
        $data = json_decode(file_get_contents('php://input'), true) ?: [];
        $mode = $data['mode'] ?? '';
    } else {
        $mode = (string)($_POST['mode'] ?? 'preview');
    }

    $pdo = Database::getDB();

    switch ($mode) {
        case 'list_uploaded':
            out_json(['success' => true, 'files' => list_uploaded_import_files()]);
            break;

        case 'delete_uploaded':
            $fileId = (string)($data['file_id'] ?? '');
            if ($fileId === '') {
                out_json(['success' => false, 'message' => 'Lipsește file_id.'], 422);
            }
            $ok = delete_uploaded_import_file($fileId);
            out_json([
                'success' => $ok,
                'message' => $ok ? 'Fișierul a fost șters.' : 'Fișierul nu a fost găsit.'
            ], $ok ? 200 : 404);
            break;

        case 'upload_chunk':
            if (empty($_POST['file_id']) || empty($_POST['original_name']) || !isset($_POST['chunk_index']) || !isset($_POST['total_chunks'])) {
                out_json(['success' => false, 'message' => 'Lipsesc datele chunk-ului.'], 422);
            }
            if (empty($_FILES['chunk']['tmp_name'])) {
                out_json(['success' => false, 'message' => 'Chunk lipsă.'], 422);
            }

            $meta = save_chunk_upload(
                (string)$_POST['file_id'],
                (string)$_POST['original_name'],
                (int)$_POST['chunk_index'],
                (int)$_POST['total_chunks'],
                (string)$_FILES['chunk']['tmp_name'],
                trim((string)($_POST['upload_role'] ?? ''))
            );
            out_json(['success' => true, 'meta' => $meta]);
            break;

        case 'preview_uploaded':
            $filesMeta = $data['uploaded_files'] ?? [];
            if (!$filesMeta || !is_array($filesMeta)) {
                out_json(['success' => false, 'message' => 'Nu există fișiere încărcate pentru preview.'], 422);
            }

            $maxPreview = max(1, min(500, (int)($data['max_preview'] ?? 500)));
            $previewOptions = [
                'brand_filter' => trim((string)($data['brand_filter'] ?? '')),
                'force_supplier_api' => !empty($data['force_supplier_api']),
                'skip_tecdoc_csv_scan' => !array_key_exists('skip_tecdoc_csv_scan', $data) || !empty($data['skip_tecdoc_csv_scan']),
                'import_mode' => trim((string)($data['import_mode'] ?? '')),
                'require_supplier_price' => !array_key_exists('require_supplier_price', $data) || !empty($data['require_supplier_price']),
            ];
            $result = import_preview_uploaded_files($filesMeta, $maxPreview, $previewOptions);
            $products = $result['products'];
            $totalRows = (int)$result['total_rows'];
            $truncated = !empty($result['truncated']);
            $importMode = (string)($result['import_mode'] ?? 'generic');

            if (empty($products)) {
                $error = (string)($result['error'] ?? '');
                if ($error === 'missing_files') {
                    $missing = implode(', ', array_map('strval', $result['missing_files'] ?? []));
                    out_json(['success' => false, 'message' => 'Fișierele încărcate nu mai sunt pe server. Reîncarcă: ' . $missing], 422);
                }
                if ($error === 'empty_supplier_catalog') {
                    out_json(['success' => false, 'message' => 'Nu am găsit produse valide în listele furnizor. Verifică formatul CSV (Autonet, Elit etc.).'], 422);
                }
                if ($error === 'validation_failed') {
                    $details = implode(' ', array_map('strval', $result['validation_errors'] ?? []));
                    out_json(['success' => false, 'message' => 'Validare fișiere eșuată: ' . $details], 422);
                }
                if ($error === 'no_tecdoc_files') {
                    out_json(['success' => false, 'message' => 'Mod TecDoc master: încarcă cel puțin un fișier CSV UTF8 (TableUseCarsForParts).'], 422);
                }
                if ($error === 'no_supplier_files') {
                    out_json(['success' => false, 'message' => 'Mod TecDoc master: încarcă listele furnizor pentru prețuri (Autonet, Elit etc.).'], 422);
                }
                if ($importMode === 'tecdoc_master' && (int)($result['tecdoc_skipped_no_price'] ?? 0) > 0) {
                    out_json([
                        'success' => false,
                        'message' => 'Niciun produs cu preț găsit la furnizori. '
                            . (int)$result['tecdoc_skipped_no_price'] . ' piese TecDoc omise (cod fără match în Autonet/Elit).',
                    ], 422);
                }
                out_json(['success' => false, 'message' => 'Nu am putut citi produse din fișierele încărcate.'], 422);
            }

            $msg = count($products) . ' produse afișate';
            if ($truncated) {
                $msg .= ' (preview limitat la primele ' . $maxPreview . ' produse unice)';
            }

            if ($importMode === 'tecdoc_master') {
                $msg .= '. Mod: CSV TecDoc (UTF8) = date produs, furnizor = doar preț.';
                $msg .= ' ' . number_format((int)($result['price_index_size'] ?? 0), 0, '.', '.') . ' coduri indexate în listele furnizor.';
                if (($result['tecdoc_skipped_no_price'] ?? 0) > 0) {
                    $msg .= ' ' . (int)$result['tecdoc_skipped_no_price'] . ' piese TecDoc omise (fără preț la furnizor).';
                }
                if ($previewOptions['brand_filter'] !== '') {
                    $msg .= ' Filtru brand: ' . $previewOptions['brand_filter'] . '.';
                }
            } elseif ($importMode === 'supplier_api' || $importMode === 'supplier_preview' || $importMode === 'supplier_files') {
                $msg .= '. Mod: listă furnizor + fișiere CSV TecDoc (fără API online)';
                $msg .= '. ' . number_format((int)($result['price_index_size'] ?? 0), 0, '.', '.') . ' produse unice în listele furnizor.';
                if (($result['tecdoc_files'] ?? 0) > 0) {
                    $msg .= ' ' . (int)$result['tecdoc_files'] . ' fișier(e) CSV TecDoc folosite local.';
                    if (($result['tecdoc_file_hits'] ?? 0) > 0) {
                        $msg .= ' ' . (int)$result['tecdoc_file_hits'] . ' produse îmbogățite din CSV (OEM, specs, titlu SEO, compatibilități).';
                    }
                    if (($result['tecdoc_skipped_no_compat'] ?? 0) > 0) {
                        $msg .= ' ' . (int)$result['tecdoc_skipped_no_compat'] . ' produse omise (fără compatibilități auto permise sau fără preț).';
                    }
                    if (($result['tecdoc_api_skipped'] ?? 0) > 0) {
                        $msg .= ' ' . (int)$result['tecdoc_api_skipped'] . ' produse rămân doar cu date furnizor (fără match în CSV).';
                    }
                }
                if ($previewOptions['brand_filter'] !== '') {
                    $msg .= ' Filtru brand: ' . $previewOptions['brand_filter'] . '.';
                }
            } elseif (($result['tecdoc_files'] ?? 0) > 0 && ($result['supplier_files'] ?? 0) > 0) {
                $msg .= '. Combinate ' . (int)$result['tecdoc_files'] . ' fișier(e) TecDoc CSV cu '
                    . (int)$result['supplier_files'] . ' listă/liste de preț furnizori ('
                    . number_format((int)($result['price_index_size'] ?? 0), 0, '.', '.') . ' coduri indexate).';
            }
            $productsWithPrice = count(array_filter($products, static function (array $product): bool {
                return trim((string)($product['pPrice'] ?? '')) !== '';
            }));
            $productsWithoutPrice = count($products) - $productsWithPrice;
            if ($productsWithoutPrice > 0) {
                $msg .= ' Atenție: ' . $productsWithoutPrice . ' produse nu au preț găsit la furnizori.';
            }
            if ($productsWithPrice > 0) {
                $msg .= ' ' . $productsWithPrice . ' produse au preț final calculat cu regulile de adaos comercial.';
            }

            out_json([
                'success' => true,
                'products' => $products,
                'count' => count($products),
                'total_rows' => $totalRows,
                'truncated' => $truncated,
                'products_without_price' => $productsWithoutPrice,
                'products_with_price' => $productsWithPrice,
                'price_index_size' => (int)($result['price_index_size'] ?? 0),
                'import_mode' => $importMode,
                'tecdoc_api_found' => (int)($result['tecdoc_api_found'] ?? 0),
                'tecdoc_api_missing' => (int)($result['tecdoc_api_missing'] ?? 0),
                'message' => $msg,
            ]);
            break;

        case 'preview_job_start':
            $filesMeta = $data['uploaded_files'] ?? [];
            if (!$filesMeta || !is_array($filesMeta)) {
                out_json(['success' => false, 'message' => 'Nu există fișiere încărcate pentru preview.'], 422);
            }

            $maxPreview = max(1, min(500, (int)($data['max_preview'] ?? 500)));
            $start = import_preview_job_start($filesMeta, $maxPreview, [
                'brand_filter' => trim((string)($data['brand_filter'] ?? '')),
                'tecdoc_max_rows_per_code' => max(1, min(250, (int)($data['tecdoc_max_rows_per_code'] ?? 30))),
                'import_mode' => trim((string)($data['import_mode'] ?? '')),
                'require_supplier_price' => !array_key_exists('require_supplier_price', $data) || !empty($data['require_supplier_price']),
            ]);

            if (empty($start['ok'])) {
                $error = (string)($start['error'] ?? '');
                if ($error === 'missing_files') {
                    $missing = implode(', ', array_map('strval', $start['missing_files'] ?? []));
                    out_json(['success' => false, 'message' => 'Fișierele încărcate nu mai sunt pe server. Reîncarcă: ' . $missing], 422);
                }
                if ($error === 'empty_supplier_catalog') {
                    out_json(['success' => false, 'message' => 'Nu am găsit produse valide în listele furnizor.'], 422);
                }
                if ($error === 'validation_failed') {
                    $details = implode(' ', array_map('strval', $start['validation_errors'] ?? []));
                    out_json(['success' => false, 'message' => 'Validare fișiere eșuată: ' . $details], 422);
                }
                if ($error === 'no_supplier_files') {
                    out_json(['success' => false, 'message' => 'Încarcă cel puțin o listă furnizor (Autonet, Elit etc.).'], 422);
                }
                if ($error === 'no_tecdoc_files') {
                    out_json(['success' => false, 'message' => 'Mod TecDoc master: încarcă fișiere CSV UTF8 TecDoc.'], 422);
                }
                out_json(['success' => false, 'message' => 'Nu am putut porni job-ul de preview.'], 422);
            }

            out_json([
                'success' => true,
                'job_id' => (string)$start['job_id'],
                'message' => 'Job preview pornit.',
            ]);
            break;

        case 'preview_job_step':
            $jobId = trim((string)($data['job_id'] ?? ''));
            if ($jobId === '') {
                out_json(['success' => false, 'message' => 'Lipsește job_id.'], 422);
            }

            $step = import_preview_job_step($jobId);
            if (empty($step['ok'])) {
                out_json([
                    'success' => false,
                    'message' => (string)($step['error'] ?? 'Eroare la procesarea job-ului.'),
                    'status' => $step['status'] ?? null,
                ], 422);
            }

            $payload = [
                'success' => true,
                'status' => $step['status'] ?? null,
            ];
            if (!empty($step['cancelled'])) {
                $payload['cancelled'] = true;
                $payload['message'] = 'Job oprit.';
                out_json($payload);
                break;
            }
            if (!empty($step['result'])) {
                $result = $step['result'];
                if (empty($result['products'])) {
                    $importMode = (string)($result['import_mode'] ?? '');
                    $msg = 'Nu am putut genera produse din fișierele încărcate.';
                    if ($importMode === 'tecdoc_master') {
                        $totalRows = (int)($result['total_rows'] ?? 0);
                        $skippedNoPrice = (int)($result['tecdoc_skipped_no_price'] ?? 0);
                        $priceIndexSize = (int)($result['price_index_size'] ?? 0);
                        $msg = '0 produse generate în mod TecDoc master.';
                        if ($totalRows > 0) {
                            $msg .= ' Rânduri TecDoc citite: ' . number_format($totalRows, 0, '.', '.') . '.';
                        }
                        if ($priceIndexSize > 0) {
                            $msg .= ' Index preț furnizor: ' . number_format($priceIndexSize, 0, '.', '.') . ' coduri.';
                        }
                        if ($skippedNoPrice > 0) {
                            $msg .= ' ' . $skippedNoPrice . ' piese omise fără preț la furnizor.';
                        } else {
                            $msg .= ' Verifică filtrul de compatibilități (an ≥ 2000) sau filtrul brand.';
                        }
                    }
                    out_json(['success' => false, 'message' => $msg, 'result' => $result], 422);
                }
                $payload['products'] = $result['products'];
                $payload['count'] = (int)($result['count'] ?? count($result['products']));
                $payload['total_rows'] = (int)($result['total_rows'] ?? 0);
                $payload['truncated'] = !empty($result['truncated']);
                $payload['price_index_size'] = (int)($result['price_index_size'] ?? 0);
                $payload['import_mode'] = (string)($result['import_mode'] ?? 'supplier_preview');
                $payload['message'] = (string)($result['message'] ?? 'Preview finalizat.');
            }

            out_json($payload);
            break;

        case 'import_job_start':
            $products = $data['products'] ?? [];
            if (empty($products) || !is_array($products)) {
                out_json(['success' => false, 'message' => 'Nu s-au trimis produse.'], 422);
            }

            $markupRuleId = (int) ($data['markup_rule_id'] ?? 0);
            $start = import_queue_job_start($products, $markupRuleId > 0 ? $markupRuleId : null);
            if (empty($start['ok'])) {
                out_json(['success' => false, 'message' => 'Nu am putut porni job-ul de import.'], 422);
            }

            out_json([
                'success' => true,
                'job_id' => (string)$start['job_id'],
                'message' => 'Job import pornit.',
            ]);
            break;

        case 'import_job_step':
            $jobId = trim((string)($data['job_id'] ?? ''));
            if ($jobId === '') {
                out_json(['success' => false, 'message' => 'Lipsește job_id.'], 422);
            }

            $step = import_queue_job_step($jobId, $pdo);
            if (empty($step['ok'])) {
                out_json([
                    'success' => false,
                    'message' => (string)($step['error'] ?? 'Eroare la procesarea job-ului.'),
                    'status' => $step['status'] ?? null,
                ], 422);
            }

            $payload = [
                'success' => true,
                'status' => $step['status'] ?? null,
            ];
            if (!empty($step['result'])) {
                $payload = array_merge($payload, $step['result']);
            }
            if (!empty($step['cancelled'])) {
                $payload['cancelled'] = true;
                $payload['message'] = 'Job oprit.';
            }

            out_json($payload);
            break;

        case 'import_job_cancel':
            $jobId = trim((string)($data['job_id'] ?? ''));
            if ($jobId === '') {
                out_json(['success' => false, 'message' => 'Lipsește job_id.'], 422);
            }
            import_job_cancel($jobId);
            out_json(['success' => true, 'message' => 'Job oprit.']);
            break;

        case 'import_job_cancel_all':
            $count = import_job_cancel_all();
            out_json([
                'success' => true,
                'message' => $count > 0 ? ($count . ' job(uri) oprite.') : 'Nu există joburi active.',
                'cancelled' => $count,
            ]);
            break;

        case 'preview':
            $files = $_FILES['import_file'] ?? null;
            if (!$files || empty($files['tmp_name'])) {
                out_json(['success' => false, 'message' => 'Alege cel puțin un fișier.'], 422);
            }

            $tmpNames = is_array($files['tmp_name']) ? $files['tmp_name'] : [$files['tmp_name']];
            $origNames = is_array($files['name']) ? $files['name'] : [$files['name']];

            $filesMeta = [];
            foreach ($tmpNames as $i => $tmp) {
                if (empty($tmp)) {
                    continue;
                }
                $filesMeta[] = [
                    'file_id' => 'direct_' . $i,
                    'original_name' => (string)($origNames[$i] ?? 'import.csv'),
                    '__path' => $tmp,
                ];
            }

            $maxPreview = 500;
            $tecdocFiles = [];
            $supplierFiles = [];
            $genericFiles = [];
            foreach ($filesMeta as $fileMeta) {
                $path = (string)($fileMeta['__path'] ?? '');
                $originalName = (string)($fileMeta['original_name'] ?? '');
                if ($path === '' || !is_file($path)) {
                    continue;
                }
                $kind = import_classify_file($path, $originalName);
                $entry = ['path' => $path, 'name' => $originalName];
                if ($kind === 'tecdoc') {
                    $tecdocFiles[] = $entry;
                } elseif (str_starts_with($kind, 'supplier:')) {
                    $supplierFiles[] = $entry;
                } else {
                    $genericFiles[] = $entry;
                }
            }

            $priceIndex = import_build_price_index($supplierFiles);
            $markupService = new \Evasystem\Controllers\AdaosComercial\AdaosComercialService();
            $products = [];
            $totalRows = 0;
            $truncated = false;
            $filesToProcess = $tecdocFiles !== [] ? $tecdocFiles : array_merge($genericFiles, $supplierFiles);

            foreach ($filesToProcess as $file) {
                $preview = preview_products_from_file(
                    $file['path'],
                    $file['name'],
                    max(1, $maxPreview - count($products)),
                    $priceIndex,
                    $markupService
                );
                $products = array_merge($products, $preview['products']);
                $totalRows += (int)$preview['total_rows'];
                if (!empty($preview['truncated']) || count($products) >= $maxPreview) {
                    $truncated = true;
                    $products = array_slice($products, 0, $maxPreview);
                    break;
                }
            }

            if (empty($products)) {
                out_json(['success' => false, 'message' => 'Nu am putut citi produse din fișiere.'], 422);
            }

            $msg = count($products) . ' produse afișate';
            if ($truncated) {
                $msg .= ' (preview limitat la primele ' . $maxPreview . ' rânduri pentru fișiere mari)';
            }
            if ($tecdocFiles !== [] && $supplierFiles !== []) {
                $msg .= '. Combinate ' . count($tecdocFiles) . ' fișier(e) TecDoc cu '
                    . count($supplierFiles) . ' listă/liste de preț furnizori.';
            }
            $productsWithoutPrice = count(array_filter($products, static function (array $product): bool {
                return trim((string)($product['pPrice'] ?? '')) === '';
            }));
            if ($productsWithoutPrice > 0) {
                $msg .= ' Atenție: ' . $productsWithoutPrice . ' produse nu au preț găsit la furnizori.';
            }

            out_json([
                'success' => true,
                'products' => $products,
                'count' => count($products),
                'total_rows' => $totalRows,
                'truncated' => $truncated,
                'products_without_price' => $productsWithoutPrice,
                'message' => $msg
            ]);
            break;

        case 'import_selected':
            $products = $data['products'] ?? [];
            if (empty($products) || !is_array($products)) {
                out_json(['success' => false, 'message' => 'Nu s-au trimis produse.'], 422);
            }

            $markupService = new \Evasystem\Controllers\AdaosComercial\AdaosComercialService();
            $stageStats = import_stage_products_for_review($pdo, $products, $markupService, [
                'epiesa_special_products' => false,
            ]);

            out_json([
                'success' => true,
                'message' => $stageStats['queued'] . ' produse pregatite in coada de publicare (' . $stageStats['with_image'] . ' cu imagine).',
                'count' => $stageStats['queued'],
                'tecdoc_enriched' => $stageStats['tecdoc_enriched'],
                'with_image' => $stageStats['with_image'],
                'without_price' => $stageStats['without_price'],
                'redirect' => '/admin/importreview?status=pending',
            ]);
            break;

        case 'consumable_scan_preview':
            $filesMeta = $data['uploaded_files'] ?? [];
            if (!$filesMeta || !is_array($filesMeta)) {
                out_json(['success' => false, 'message' => 'Încarcă listele furnizor înainte de scanare.'], 422);
            }
            $result = import_consumable_scan_preview($filesMeta, [
                'categories' => $data['categories'] ?? ['ulei', 'lichide', 'electrice'],
                'max_preview' => (int) ($data['max_preview'] ?? 200),
                'brand_filter' => trim((string) ($data['brand_filter'] ?? '')),
            ]);
            out_json($result, !empty($result['success']) ? 200 : 422);
            break;

        case 'consumable_scan_publish':
            require_once dirname(__DIR__, 3) . '/config/cron_import.php';
            $products = $data['products'] ?? [];
            if (!$products || !is_array($products)) {
                out_json(['success' => false, 'message' => 'Nu există produse de importat. Rulează mai întâi scanarea.'], 422);
            }
            $publish = import_consumable_scan_publish($pdo, $products, [
                'limit' => (int) ($data['limit'] ?? admin_cron_import_limit()),
                'check_epiesa' => !array_key_exists('check_epiesa', $data) || !empty($data['check_epiesa']),
            ]);
            out_json($publish, !empty($publish['success']) ? 200 : 422);
            break;

        default:
            out_json(['success' => false, 'message' => 'Mod necunoscut: ' . $mode], 400);
    }
} catch (Throwable $e) {
    out_json(['success' => false, 'message' => 'Eroare: ' . $e->getMessage()], 500);
}
}
