<?php
declare(strict_types=1);

require_once __DIR__ . '/_early.php';

define('IMPORT_ROOT', dirname(__DIR__));
define('IMPORT_SRC', IMPORT_ROOT . DIRECTORY_SEPARATOR . 'src');
define('IMPORT_UPLOADS_TEMP', IMPORT_ROOT . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'temp');
define('IMPORT_REPORTS', IMPORT_ROOT . DIRECTORY_SEPARATOR . 'reports');
define('IMPORT_SUPPLIERS_LEGACY_DIR', IMPORT_ROOT . DIRECTORY_SEPARATOR . 'suppliers');
define('IMPORT_STATE_DIR', IMPORT_ROOT . DIRECTORY_SEPARATOR . 'state');
define('IMPORT_CONFIG', IMPORT_ROOT . DIRECTORY_SEPARATOR . 'config');

require_once __DIR__ . '/furnizori-bridge.php';

if (!defined('IMPORT_SUPPLIERS_DIR')) {
    define('IMPORT_SUPPLIERS_DIR', import_motor_canonical_feed_base_dir());
}

import_motor_boot_erp_stack();

function import_motor_boot_erp_stack(): void
{
    static $booted = false;
    if ($booted) {
        return;
    }

    $candidates = [];
    if (defined('BESOIU_APP')) {
        $candidates[] = BESOIU_APP . '/Import/bootstrap.php';
    }
    if (defined('BESOIU_ROOT')) {
        $candidates[] = BESOIU_ROOT . '/app/Import/bootstrap.php';
    }
    $candidates[] = dirname(__DIR__, 2) . '/bootstrap.php';

    foreach ($candidates as $path) {
        if (is_file($path)) {
            require_once $path;
            $booted = true;
            return;
        }
    }
}

function import_motor_data_root(): string
{
    if (class_exists(\Besoiu\Import\Support\ImportPathResolver::class)) {
        return \Besoiu\Import\Support\ImportPathResolver::dataRoot();
    }

    $env = trim((string) (getenv('BESOIU_IMPORT_DATA_ROOT') ?: ''));
    if ($env !== '' && is_dir($env)) {
        return rtrim(str_replace('\\', '/', $env), '/');
    }

    return rtrim(str_replace('\\', '/', dirname(IMPORT_ROOT)), '/');
}

function import_motor_prelucrare_api_dir(): string
{
    if (class_exists(\Besoiu\Import\Support\ImportPathResolver::class)) {
        return \Besoiu\Import\Support\ImportPathResolver::prelucrareRoot() . '/api';
    }

    return import_motor_data_root() . '/Prelucrare fisiere bovsoft-base/api';
}

function import_motor_fetch_product_api_dir(): string
{
    if (class_exists(\Besoiu\Import\Support\ImportPathResolver::class)) {
        return \Besoiu\Import\Support\ImportPathResolver::fetchProductRoot() . '/api';
    }

    return import_motor_data_root() . '/fetch product/api';
}

function import_require_prelucrare_lib(string $file): void
{
    $path = import_motor_prelucrare_api_dir() . '/lib/' . ltrim(str_replace('\\', '/', $file), '/');
    if (!is_file($path)) {
        throw new RuntimeException('Lipseste biblioteca Prelucrare: ' . $path);
    }
    require_once $path;
}

function import_require_fetch_product_lib(string $file): void
{
    $path = import_motor_fetch_product_api_dir() . '/lib/' . ltrim(str_replace('\\', '/', $file), '/');
    if (!is_file($path)) {
        throw new RuntimeException('Lipseste biblioteca Fetch Product: ' . $path);
    }
    require_once $path;
}

function import_motor_product_matcher_path(): string
{
    return import_motor_prelucrare_api_dir() . '/lib/ProductMatcher.php';
}

function import_motor_proxy_url(string $proxyScript): string
{
    $webBase = defined('BESOIU_IMPORT_WEB_BASE') ? trim((string) BESOIU_IMPORT_WEB_BASE) : '';
    if ($webBase !== '') {
        return rtrim($webBase, '/') . '/_proxy/' . ltrim($proxyScript, '/');
    }

    if (class_exists(\Besoiu\Import\Support\ImportPathResolver::class)) {
        return rtrim(\Besoiu\Import\Support\ImportPathResolver::matchingProPublicUrl(), '/')
            . '/_proxy/' . ltrim($proxyScript, '/');
    }

    return '/Prelucrare%20fisiere%20bovsoft-base/api/product-image.php';
}

function import_motor_poze_dir(): string
{
    return import_motor_data_root() . '/Poze';
}

function import_motor_autopartner_cache(): string
{
    return import_motor_prelucrare_api_dir() . '/cache/autopartner.sqlite';
}

function import_motor_prelucrare_libs_booted(): void
{
    static $booted = false;
    if ($booted) {
        return;
    }
    import_require_prelucrare_lib('PozeFolderResolver.php');
    import_require_prelucrare_lib('AutopartnerImageResolver.php');
    $booted = true;
}

/**
 * @param array<string, mixed> $card
 */
function import_build_card_image_display_url(array $card): string
{
    if (!empty($card['scrapedImagePath'])) {
        return import_motor_proxy_url('scraped-image.php')
            . '?path=' . rawurlencode((string) $card['scrapedImagePath']);
    }

    if (!empty($card['scrapedImageUrl'])) {
        return (string) $card['scrapedImageUrl'];
    }

    $source = strtolower(trim((string) ($card['imageSource'] ?? '')));
    $base = import_motor_proxy_url('product-image.php');

    if ($source === 'autopartner') {
        $code = trim((string) ($card['autopartnerCode'] ?? ''));
        if ($code === '') {
            return '';
        }
        return $base . '?source=autopartner&code=' . rawurlencode($code);
    }

    if ($source !== 'poze') {
        return '';
    }

    $ttcId = trim((string) ($card['ttcArtId'] ?? ''));
    if ($ttcId === '' || !preg_match('/^\d+$/', $ttcId)) {
        return '';
    }

    $brand = trim((string) ($card['pozeFolder'] ?? $card['pozeBrand'] ?? $card['brand'] ?? ''));
    $brand = ltrim($brand, '=');
    if ($brand === '') {
        return '';
    }

    return $base . '?source=poze&brand=' . rawurlencode($brand) . '&id=' . rawurlencode($ttcId);
}

/**
 * Verifică dacă fișierul imagine există pe disc (Poze / Autopartner).
 *
 * @param array<string, mixed> $card
 */
function import_verify_card_image_servable(array $card): bool
{
    if (!empty($card['scrapedImagePath'])) {
        if (!class_exists('ImportScrapedImageStore', false)) {
            require_once __DIR__ . '/lib/ImportScrapedImageStore.php';
        }

        return ImportScrapedImageStore::resolveAbsolutePath((string) $card['scrapedImagePath']) !== null;
    }

    if (!empty($card['scrapedImageUrl'])) {
        return true;
    }
    if (empty($card['hasImage'])) {
        return false;
    }

    import_motor_prelucrare_libs_booted();

    $source = strtolower(trim((string) ($card['imageSource'] ?? '')));
    if ($source === 'autopartner') {
        $code = trim((string) ($card['autopartnerCode'] ?? ''));
        if ($code === '') {
            return false;
        }
        $resolver = new AutopartnerImageResolver();
        return $resolver->getImageByApCode($code) !== null;
    }

    if ($source !== 'poze') {
        return false;
    }

    $ttcId = trim((string) ($card['ttcArtId'] ?? ''));
    if ($ttcId === '' || !preg_match('/^\d+$/', $ttcId)) {
        return false;
    }

    $brand = trim((string) ($card['pozeFolder'] ?? $card['pozeBrand'] ?? $card['brand'] ?? ''));
    $brand = ltrim($brand, '=');
    if ($brand === '') {
        return false;
    }

    return PozeFolderResolver::findImagePath($brand, $ttcId) !== null;
}

/**
 * @param array<string, mixed> $card
 * @return array<string, mixed>
 */
function import_normalize_card_image_fields(array $card): array
{
    if (!import_verify_card_image_servable($card)) {
        $card['hasImage'] = false;
        $card['imageSource'] = '';
        $card['ttcArtId'] = '';
        $card['autopartnerCode'] = '';
        $card['imageUrl'] = '';
        $card['imageDisplayUrl'] = '';
        $card['pozeFolder'] = '';
        return $card;
    }

    $card['hasImage'] = true;
    $card['imageDisplayUrl'] = import_build_card_image_display_url($card);
    if ($card['imageDisplayUrl'] === '') {
        $card['hasImage'] = false;
    }

    return $card;
}

/**
 * @param list<array<string, mixed>> $cards
 * @return list<array<string, mixed>>
 */
function import_filter_verified_image_cards(array $cards): array
{
    $out = [];
    foreach ($cards as $card) {
        if (!is_array($card)) {
            continue;
        }
        $normalized = import_normalize_card_image_fields($card);
        if (!empty($normalized['hasImage']) && !empty($normalized['imageDisplayUrl'])) {
            $out[] = $normalized;
        }
    }
    return $out;
}

/**
 * Determină corect dacă mai există carduri cu imagine verificată după pagina curentă.
 */
function import_build_stored_page_has_more(
    string $path,
    array $header,
    string $filename,
    int $offset,
    int $limit,
    int $pageCount,
    int $totalBuilt,
    int $buildLimit,
    bool $onlyWithImage
): bool {
    if ($pageCount <= 0) {
        return false;
    }
    if ($totalBuilt > ($offset + $pageCount)) {
        return true;
    }
    if ($pageCount < $limit) {
        return false;
    }

    $probeLimit = min(500000, $offset + $limit + $limit);
    if ($probeLimit <= $buildLimit) {
        return false;
    }

    import_require_fetch_product_lib('SupplierPriceListBuilder.php');
    import_require_fetch_product_lib('SupplierEnrichedCardBuilder.php');

    $probeCards = [];
    if (SupplierPriceListBuilder::isBaseCsvHeader($header)) {
        require_once import_motor_product_matcher_path();
        $matcher = new ProductMatcher();
        [$probeCards] = $matcher->buildCardsFromUploadedCsv($path, $probeLimit, !$onlyWithImage);
    } elseif (SupplierPriceListBuilder::detectSupplierType($header, $filename) !== null) {
        $builder = new SupplierEnrichedCardBuilder();
        [$probeCards] = $builder->buildCardsFromFile($path, $onlyWithImage ? $limit : $probeLimit, $onlyWithImage);
    } else {
        return false;
    }

    $probeFiltered = import_filter_verified_image_cards($probeCards);

    return count($probeFiltered) > ($offset + $pageCount);
}

/**
 * @return array{0: string, 1: list<string>}
 */
function import_python_cmd(): array
{
    $candidates = [
        ['py', '-3'],
        ['python', ''],
        ['python3', ''],
        ['py', ''],
    ];

    foreach ($candidates as [$bin, $flag]) {
        $args = $flag !== '' ? [$flag, '--version'] : ['--version'];
        $result = import_proc_run($bin, $args, 5);
        if ($result['exit_code'] === 0) {
            return [$bin, $flag !== '' ? [$flag] : []];
        }
    }

    return ['py', ['-3']];
}

/**
 * @param list<string> $args
 */
function import_proc_run(string $bin, array $args, int $timeoutSec = 120): array
{
    $nullDevice = 'NUL';
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['file', $nullDevice, 'w'],
    ];

    $cmd = $bin;
    foreach ($args as $arg) {
        $cmd .= ' ' . escapeshellarg($arg);
    }

    $process = @proc_open($cmd, $descriptors, $pipes, IMPORT_SRC);
    if (!is_resource($process)) {
        return [
            'exit_code' => 127,
            'stdout' => '',
            'stderr' => 'proc_open eșuat pentru: ' . $cmd,
            'command' => $cmd,
        ];
    }

    fclose($pipes[0]);
    stream_set_blocking($pipes[1], true);

    $stdout = '';
    $start = time();
    while (!feof($pipes[1])) {
        $chunk = fread($pipes[1], 65536);
        if ($chunk === false) {
            break;
        }
        $stdout .= $chunk;
        if ((time() - $start) > $timeoutSec) {
            proc_terminate($process);
            fclose($pipes[1]);
            proc_close($process);
            return [
                'exit_code' => 124,
                'stdout' => $stdout,
                'stderr' => "Timeout după {$timeoutSec}s",
                'command' => $cmd,
            ];
        }
    }
    fclose($pipes[1]);
    $exitCode = proc_close($process);

    return [
        'exit_code' => $exitCode,
        'stdout' => $stdout,
        'stderr' => '',
        'command' => $cmd,
    ];
}

function import_extract_json(string $text): ?array
{
    $text = trim($text);
    if ($text === '') {
        return null;
    }

    $decoded = json_decode($text, true);
    if (is_array($decoded)) {
        return $decoded;
    }

    // Python poate lăsa loguri înainte de JSON — extrage ultimul bloc [ sau {
    foreach (['[', '{'] as $start) {
        $pos = strrpos($text, $start);
        if ($pos === false) {
            continue;
        }
        $chunk = substr($text, $pos);
        $decoded = json_decode($chunk, true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }

    return null;
}

function import_run_python(array $args, int $timeoutSec = 180): array
{
    [$bin, $binArgs] = import_python_cmd();
    $script = IMPORT_SRC . DIRECTORY_SEPARATOR . 'run_import.py';
    $runArgs = array_merge($binArgs, [$script], array_map('strval', $args));

    $result = import_proc_run($bin, $runArgs, $timeoutSec);
    $combined = trim($result['stdout'] . "\n" . $result['stderr']);
    $json = import_extract_json($combined);

    return [
        'exit_code' => $result['exit_code'],
        'raw' => $combined,
        'json' => $json,
        'command' => $result['command'],
        'stdout' => $result['stdout'],
        'stderr' => $result['stderr'],
    ];
}

function import_report_list_item(string $path): ?array
{
    $id = pathinfo($path, PATHINFO_FILENAME);
    $size = filesize($path);
    if ($size === false) {
        return null;
    }

    $item = [
        'id' => $id,
        'file' => '',
        'supplier' => '',
        'generated_at' => '',
        'summary' => [],
        'json_path' => $path,
    ];

    if ($size > 102400) {
        $chunk = (string) @file_get_contents($path, false, null, 0, 32768);
        if ($chunk === '') {
            return null;
        }
        if (preg_match('/"file"\s*:\s*"((?:\\\\.|[^"\\\\])*)"/', $chunk, $m)) {
            $item['file'] = stripcslashes($m[1]);
        }
        if (preg_match('/"supplier"\s*:\s*"((?:\\\\.|[^"\\\\])*)"/', $chunk, $m)) {
            $item['supplier_key'] = stripcslashes($m[1]);
        }
        if (preg_match('/"supplier_label"\s*:\s*"((?:\\\\.|[^"\\\\])*)"/', $chunk, $m)) {
            $item['supplier_label'] = stripcslashes($m[1]);
        }
        $item['supplier'] = $item['supplier_label'] ?? $item['supplier_key'] ?? '';
        if (preg_match('/"generated_at"\s*:\s*"((?:\\\\.|[^"\\\\])*)"/', $chunk, $m)) {
            $item['generated_at'] = stripcslashes($m[1]);
        }
        if (preg_match('/"summary"\s*:\s*(\{[^}]*\})/', $chunk, $m)) {
            $summary = json_decode($m[1], true);
            if (is_array($summary)) {
                $item['summary'] = $summary;
            }
        }
        $item['large'] = true;
        return $item;
    }

    $raw = @file_get_contents($path);
    if ($raw === false) {
        return null;
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        return null;
    }
    $item['file'] = $data['file'] ?? '';
    $item['supplier_key'] = $data['supplier'] ?? '';
    $item['supplier_label'] = $data['supplier_label'] ?? '';
    $item['supplier'] = $data['supplier_label'] ?? $data['supplier'] ?? '';
    $item['generated_at'] = $data['generated_at'] ?? '';
    $item['summary'] = $data['summary'] ?? [];

    return $item;
}

function import_list_reports(int $limit = 15): array
{
    if (!is_dir(IMPORT_REPORTS)) {
        return [];
    }

    $files = glob(IMPORT_REPORTS . DIRECTORY_SEPARATOR . '*.json') ?: [];
    usort($files, static fn(string $a, string $b): int => filemtime($b) <=> filemtime($a));

    $out = [];
    foreach (array_slice($files, 0, $limit) as $path) {
        $item = import_report_list_item($path);
        if ($item !== null) {
            $out[] = $item;
        }
    }

    return $out;
}

function import_read_report(string $id, int $maxProducts = 100): ?array
{
    $safe = preg_replace('/[^A-Za-z0-9._-]+/', '', $id) ?? '';
    if ($safe === '') {
        return null;
    }
    $maxProducts = max(1, min(500, $maxProducts));
    $jsonPath = IMPORT_REPORTS . DIRECTORY_SEPARATOR . $safe . '.json';
    $csvPath = IMPORT_REPORTS . DIRECTORY_SEPARATOR . $safe . '.csv';

    if (is_file($csvPath)) {
        return import_read_report_from_csv($csvPath, $safe, $maxProducts, is_file($jsonPath) ? $jsonPath : null);
    }

    if (!is_file($jsonPath)) {
        return null;
    }

    $size = filesize($jsonPath);
    if ($size !== false && $size > 102400) {
        $meta = import_report_list_item($jsonPath);
        if ($meta === null) {
            return null;
        }
        $meta['products'] = [];
        $meta['note'] = 'Raport prea mare — lipsește CSV-ul companion din import/reports/.';

        return $meta;
    }

    $data = json_decode((string) file_get_contents($jsonPath), true);
    if (!is_array($data)) {
        return null;
    }

    return import_trim_report_products($data, $maxProducts);
}

function import_trim_report_products(array $data, int $maxProducts): array
{
    $products = $data['products'] ?? [];
    if (is_array($products) && count($products) > $maxProducts) {
        $total = $data['summary']['total'] ?? count($products);
        $data['products'] = array_slice($products, 0, $maxProducts);
        $data['note'] = 'Afișăm primele ' . $maxProducts . ' din ' . $total . ' produse.';
    }
    return $data;
}

function import_strip_bom(string $value): string
{
    if (str_starts_with($value, "\xEF\xBB\xBF")) {
        return substr($value, 3);
    }

    return $value;
}

function import_csv_delimiter(string $sampleLine): string
{
    $sampleLine = import_strip_bom($sampleLine);
    $semicolons = substr_count($sampleLine, ';');
    $commas = substr_count($sampleLine, ',');
    $tabs = substr_count($sampleLine, "\t");
    $best = max($semicolons, $commas, $tabs);
    if ($best <= 0) {
        return ',';
    }
    if ($tabs === $best) {
        return "\t";
    }
    if ($commas === $best) {
        return ',';
    }

    return ';';
}

function import_read_report_from_csv(string $csvPath, string $id, int $maxProducts = 100, ?string $jsonPath = null): ?array
{
    $maxProducts = max(1, min(500, $maxProducts));
    $meta = ($jsonPath !== null && is_file($jsonPath)) ? import_report_list_item($jsonPath) : null;
    $summary = is_array($meta['summary'] ?? null) ? $meta['summary'] : null;

    $handle = fopen($csvPath, 'rb');
    if ($handle === false) {
        return null;
    }
    $firstLine = fgets($handle);
    if ($firstLine === false) {
        fclose($handle);
        return null;
    }
    $delimiter = import_csv_delimiter($firstLine);
    rewind($handle);
    $header = fgetcsv($handle, 0, $delimiter);
    if ($header === false) {
        fclose($handle);
        return null;
    }
    $header = array_map(static fn($h) => import_strip_bom(trim((string) $h)), $header);
    $sourceLabel = ($meta['supplier'] ?? '') !== '' && ($meta['file'] ?? '') !== ''
        ? (trim((string) ($meta['supplier_label'] ?? $meta['supplier'])) . '/' . $meta['file'])
        : ($meta['file'] ?? basename($csvPath, '.csv'));
    $products = [];
    $rowNum = 0;
    while (($row = fgetcsv($handle, 0, $delimiter)) !== false && count($products) < $maxProducts) {
        $rowNum++;
        if (count($row) < count($header)) {
            continue;
        }
        $item = array_combine($header, $row);
        if (!is_array($item)) {
            continue;
        }
        $status = strtolower((string) ($item['status'] ?? $item['match_status'] ?? 'no_match'));
        if (!in_array($status, ['exact', 'probable', 'no_match', 'conflict'], true)) {
            $status = 'no_match';
        }
        $priceRaw = $item['price'] ?? '';
        $products[] = [
            'row' => isset($item['row']) && is_numeric($item['row']) ? (int) $item['row'] : $rowNum,
            'source_file' => $sourceLabel,
            'sku_supplier' => trim((string) ($item['sku_supplier'] ?? $item['sku'] ?? '')),
            'ean' => trim((string) ($item['ean'] ?? '')),
            'name' => trim((string) ($item['name'] ?? $item['nume'] ?? '')),
            'price' => $priceRaw !== '' && is_numeric(str_replace(',', '.', (string) $priceRaw))
                ? (float) str_replace(',', '.', (string) $priceRaw)
                : null,
            'status' => $status,
            'matched_name' => trim((string) ($item['matched_name'] ?? '')),
            'match_method' => trim((string) ($item['match_method'] ?? '')),
            'confidence' => isset($item['confidence']) && is_numeric($item['confidence'])
                ? (float) $item['confidence']
                : null,
            'matched_internal_sku' => trim((string) ($item['matched_internal_sku'] ?? '')),
        ];
    }
    fclose($handle);

    if ($summary === null) {
        $summary = ['total' => count($products), 'exact' => 0, 'probable' => 0, 'no_match' => 0, 'conflict' => 0];
        foreach ($products as $p) {
            $summary[$p['status']]++;
        }
    }

    $out = [
        'id' => $id,
        'file' => $meta['file'] ?? basename($csvPath, '.csv'),
        'supplier' => $meta['supplier_label'] ?? $meta['supplier'] ?? '',
        'supplier_key' => $meta['supplier_key'] ?? $meta['supplier'] ?? '',
        'generated_at' => $meta['generated_at'] ?? date('Y-m-d H:i:s', filemtime($csvPath) ?: time()),
        'summary' => $summary,
        'products' => $products,
        'source' => 'csv',
    ];
    $total = (int) ($summary['total'] ?? 0);
    if ($total > count($products)) {
        $out['note'] = 'Afișăm primele ' . count($products) . ' din ' . $total . ' produse (raport mare).';
    }
    return $out;
}

function import_normalize_upload_files(): array
{
    if (isset($_FILES['files']) && is_array($_FILES['files'])) {
        return $_FILES['files'];
    }

    // Compat: files[] din FormData
    foreach ($_FILES as $key => $value) {
        if (str_starts_with($key, 'files')) {
            return $value;
        }
    }

    return [];
}

function import_supplier_keys(): array
{
    return import_motor_allowed_supplier_slugs();
}

function import_normalize_state_data(array $state): array
{
    $files = $state['files'] ?? [];
    if (!is_array($files) || array_is_list($files)) {
        $state['files'] = [];
    }

    return $state;
}

function import_encode_processed_state(array $state): string
{
    $state = import_normalize_state_data($state);
    $files = $state['files'] ?? [];
    $payload = [
        'files' => $files === [] ? (object) [] : $files,
    ];

    return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '{"files":{}}';
}

function import_load_state(): array
{
    $path = IMPORT_STATE_DIR . DIRECTORY_SEPARATOR . 'processed_files.json';
    if (!is_file($path)) {
        return ['files' => []];
    }
    $data = json_decode((string) file_get_contents($path), true);
    if (!is_array($data)) {
        return ['files' => []];
    }

    return import_normalize_state_data($data);
}

function import_save_processed_state(array $state): bool
{
    $path = IMPORT_STATE_DIR . DIRECTORY_SEPARATOR . 'processed_files.json';
    if (!is_dir(IMPORT_STATE_DIR)) {
        @mkdir(IMPORT_STATE_DIR, 0755, true);
    }

    return file_put_contents($path, import_encode_processed_state($state)) !== false;
}

function import_file_signature(string $path): string
{
    if (!is_file($path)) {
        return '';
    }
    $stat = stat($path);
    if ($stat === false) {
        return '';
    }
    return $stat['size'] . ':' . (int) $stat['mtime'] . ':' . basename($path);
}

function import_state_index(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $cache = [];
    foreach (import_load_state()['files'] ?? [] as $storedKey => $info) {
        $resolved = @realpath($storedKey);
        if ($resolved !== false) {
            $cache[$resolved] = $info;
        }
    }
    return $cache;
}

function import_file_status(string $fullPath): array
{
    $resolved = realpath($fullPath);
    if ($resolved === false) {
        return ['status' => 'missing', 'processed_at' => null, 'report_id' => null];
    }
    $entry = import_state_index()[$resolved] ?? null;
    if ($entry === null) {
        return ['status' => 'new', 'processed_at' => null, 'report_id' => null];
    }
    if (!isset($entry['signature'])) {
        return ['status' => 'processed', 'processed_at' => $entry['processed_at'] ?? null, 'report_id' => $entry['report_id'] ?? null];
    }
    $sig = import_file_signature($resolved);
    if (($entry['signature'] ?? '') !== $sig) {
        return ['status' => 'modified', 'processed_at' => $entry['processed_at'] ?? null, 'report_id' => $entry['report_id'] ?? null];
    }
    return ['status' => 'processed', 'processed_at' => $entry['processed_at'] ?? null, 'report_id' => $entry['report_id'] ?? null];
}

function import_resolve_stored_file(string $supplier, string $filename): ?string
{
    $supplier = strtolower(trim(preg_replace('/[^a-z0-9_]+/i', '', $supplier) ?? ''));
    $filename = basename(str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $filename));
    if ($supplier === '' || $filename === '' || $filename === '.' || $filename === '..') {
        return null;
    }
    if (!import_motor_supplier_is_allowed($supplier)) {
        return null;
    }

    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if (!in_array($ext, ['csv', 'txt'], true)) {
        return null;
    }

    foreach (import_motor_supplier_storage_dirs($supplier) as $dir) {
        if (!is_dir($dir)) {
            continue;
        }
        $path = $dir . DIRECTORY_SEPARATOR . $filename;
        if (!is_file($path)) {
            continue;
        }
        $real = realpath($path);
        $base = realpath($dir);
        if ($real !== false && $base !== false && str_starts_with($real, $base)) {
            return $real;
        }
    }

    $processedRoot = IMPORT_ROOT . DIRECTORY_SEPARATOR . 'processed';
    if (is_dir($processedRoot)) {
        $supplierDir = $processedRoot . DIRECTORY_SEPARATOR . $supplier;
        if (is_dir($supplierDir)) {
            foreach (glob($supplierDir . DIRECTORY_SEPARATOR . '*' . DIRECTORY_SEPARATOR . $filename) ?: [] as $candidate) {
                if (is_file($candidate)) {
                    $real = realpath($candidate);
                    $base = realpath($processedRoot);
                    if ($real !== false && $base !== false && str_starts_with($real, $base)) {
                        return $real;
                    }
                }
            }
        }
    }

    return null;
}

function import_list_supplier_files(): array
{
    $files = [];
    foreach (import_motor_registered_suppliers() as $slug => $profile) {
        $supplier = $slug;
        $seenPaths = [];
        foreach (import_motor_supplier_storage_dirs($slug) as $dir) {
            if (!is_dir($dir)) {
                continue;
            }
            foreach (scandir($dir) ?: [] as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }
                $path = $dir . DIRECTORY_SEPARATOR . $entry;
                if (!is_file($path)) {
                    continue;
                }
                $real = realpath($path);
                if ($real === false || isset($seenPaths[$real])) {
                    continue;
                }
                $seenPaths[$real] = true;

                $ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
                if (!in_array($ext, ['csv', 'txt'], true)) {
                    continue;
                }
                $stat = stat($path);
                $st = import_file_status($path);
                $reportSummary = null;
                $cardsTotal = null;
                $reportId = $st['report_id'] ?? null;
                if ($reportId !== null && $reportId !== '') {
                    $reportPath = IMPORT_REPORTS . DIRECTORY_SEPARATOR . $reportId . '.json';
                    if (is_file($reportPath)) {
                        $reportItem = import_report_list_item($reportPath);
                        if ($reportItem !== null) {
                            $reportSummary = $reportItem['summary'] ?? [];
                            $cardsTotal = (int) ($reportSummary['total'] ?? 0);
                        }
                    }
                }
                $files[] = [
                    'supplier' => $supplier,
                    'supplier_code' => (string) ($profile['code'] ?? ''),
                    'supplier_name' => (string) ($profile['name'] ?? $supplier),
                    'filename' => basename($path),
                    'path' => $path,
                    'size' => $stat['size'] ?? 0,
                    'size_mb' => round(($stat['size'] ?? 0) / 1024 / 1024, 2),
                    'size_kb' => round(($stat['size'] ?? 0) / 1024, 1),
                    'modified_at' => date('Y-m-d H:i:s', $stat['mtime'] ?? 0),
                    'status' => $st['status'],
                    'processed_at' => $st['processed_at'],
                    'report_id' => $reportId,
                    'report_summary' => $reportSummary,
                    'cards_total' => $cardsTotal,
                    'feed_folder' => (string) ($profile['feed_folder_rel'] ?? ''),
                    'markup_percent' => (float) ($profile['price_markup_percent'] ?? 0),
                ];
            }
        }
    }
    usort($files, static fn(array $a, array $b): int => strcmp($b['modified_at'], $a['modified_at']));

    return $files;
}

function import_all_supplier_dirs(): array
{
    return import_motor_allowed_supplier_slugs();
}

function import_supplier_folder_rel(string $supplier): string
{
    $profile = import_motor_supplier_by_slug($supplier);

    return $profile !== null
        ? (string) ($profile['feed_folder_rel'] ?? ('admin/storage/supplier_feeds/' . $supplier . '/'))
        : 'admin/storage/supplier_feeds/' . $supplier . '/';
}

function import_list_supplier_folders(bool $includeEmpty = false): array
{
    $files = import_list_supplier_files();
    $grouped = [];
    foreach ($files as $file) {
        $grouped[$file['supplier']][] = $file;
    }
    if ($includeEmpty) {
        foreach (import_all_supplier_dirs() as $supplier) {
            if (!isset($grouped[$supplier])) {
                $grouped[$supplier] = [];
            }
        }
    }
    $folders = [];
    foreach ($grouped as $supplier => $items) {
        if (!$includeEmpty && $items === []) {
            continue;
        }
        usort($items, static fn(array $a, array $b): int => strcmp($b['modified_at'], $a['modified_at']));
        $folders[] = [
            'supplier' => $supplier,
            'folder' => import_supplier_folder_rel($supplier),
            'file_count' => count($items),
            'files' => $items,
        ];
    }
    usort($folders, static fn(array $a, array $b): int => strcmp($a['supplier'], $b['supplier']));
    return $folders;
}

function import_remove_state_for_path(string $absolutePath): void
{
    $state = import_load_state();
    $target = str_replace('/', DIRECTORY_SEPARATOR, $absolutePath);
    $resolved = @realpath($target);
    $changed = false;
    foreach (array_keys($state['files'] ?? []) as $key) {
        $keyResolved = @realpath($key);
        if ($resolved !== false && $keyResolved === $resolved) {
            unset($state['files'][$key]);
            $changed = true;
            continue;
        }
        if (strcasecmp(str_replace('/', DIRECTORY_SEPARATOR, $key), $target) === 0) {
            unset($state['files'][$key]);
            $changed = true;
        }
    }
    if ($changed) {
        import_save_processed_state($state);
    }
}

function import_motor_boot_upload_files_lib(): void
{
    static $booted = false;
    if ($booted) {
        return;
    }

    import_motor_boot_furnizori_libs();
    $lib = import_motor_backend_produse_dir() . '/import_uploaded_files_lib.php';
    if (is_file($lib)) {
        require_once $lib;
    }

    $booted = true;
}

/** @return list<string> */
function import_purge_staging_for_supplier_file(string $supplierSlug, string $filename): array
{
    import_motor_boot_upload_files_lib();

    $supplierSlug = strtolower(trim($supplierSlug));
    $filename = basename(str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $filename));
    $targetName = strtolower($filename);
    if ($supplierSlug === '' || $targetName === '') {
        return [];
    }

    $supplierCode = import_motor_supplier_code_from_slug($supplierSlug);
    if ($supplierCode === '') {
        return [];
    }

    if (!function_exists('import_upload_temp_dir') || !function_exists('import_upload_temp_meta_path')) {
        return [];
    }

    $purged = [];
    foreach (glob(import_upload_temp_dir() . '/*.json') ?: [] as $metaPath) {
        $raw = @file_get_contents($metaPath);
        if (!is_string($raw) || $raw === '') {
            continue;
        }
        $meta = json_decode($raw, true);
        if (!is_array($meta)) {
            continue;
        }

        $originalName = (string) ($meta['original_name'] ?? '');
        if ($originalName === '' || strtolower(basename($originalName)) !== $targetName) {
            continue;
        }

        $kind = (string) ($meta['file_kind'] ?? $meta['upload_role'] ?? $meta['resolved_role'] ?? '');
        if (!function_exists('import_supplier_file_matches_code')
            || !import_supplier_file_matches_code($supplierCode, $originalName, $kind)) {
            continue;
        }

        $fileId = (string) ($meta['file_id'] ?? '');
        if ($fileId === '') {
            continue;
        }

        $part = function_exists('import_temp_file_path') ? import_temp_file_path($fileId) : '';
        $metaFile = import_upload_temp_meta_path($fileId);
        $removed = false;
        if ($part !== '' && is_file($part) && @unlink($part)) {
            $removed = true;
        }
        if (is_file($metaFile) && @unlink($metaFile)) {
            $removed = true;
        }
        if ($removed) {
            $purged[] = $fileId;
        }
    }

    return $purged;
}

/** @return list<string> */
function import_purge_all_staging_for_supplier(string $supplierSlug): array
{
    import_motor_boot_upload_files_lib();

    $supplierSlug = strtolower(trim($supplierSlug));
    $supplierCode = import_motor_supplier_code_from_slug($supplierSlug);
    if ($supplierCode === '' || !function_exists('import_upload_temp_dir')) {
        return [];
    }

    $purged = [];
    foreach (glob(import_upload_temp_dir() . '/*.json') ?: [] as $metaPath) {
        $raw = @file_get_contents($metaPath);
        if (!is_string($raw) || $raw === '') {
            continue;
        }
        $meta = json_decode($raw, true);
        if (!is_array($meta)) {
            continue;
        }

        $originalName = (string) ($meta['original_name'] ?? '');
        if ($originalName === '') {
            continue;
        }

        $kind = (string) ($meta['file_kind'] ?? $meta['upload_role'] ?? $meta['resolved_role'] ?? '');
        if (!function_exists('import_supplier_file_matches_code')
            || !import_supplier_file_matches_code($supplierCode, $originalName, $kind)) {
            continue;
        }

        $fileId = (string) ($meta['file_id'] ?? '');
        if ($fileId === '') {
            continue;
        }

        $part = function_exists('import_temp_file_path') ? import_temp_file_path($fileId) : '';
        $metaFile = function_exists('import_upload_temp_meta_path') ? import_upload_temp_meta_path($fileId) : '';
        $removed = false;
        if ($part !== '' && is_file($part) && @unlink($part)) {
            $removed = true;
        }
        if ($metaFile !== '' && is_file($metaFile) && @unlink($metaFile)) {
            $removed = true;
        }
        if ($removed) {
            $purged[] = $fileId;
        }
    }

    return $purged;
}

function import_delete_archived_supplier_file(string $supplier, string $filename): ?string
{
    $supplier = strtolower(trim(preg_replace('/[^a-z0-9_]+/i', '', $supplier) ?? ''));
    $filename = basename(str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $filename));
    if ($supplier === '' || $filename === '') {
        return null;
    }

    $processedRoot = IMPORT_ROOT . DIRECTORY_SEPARATOR . 'processed';
    if (!is_dir($processedRoot)) {
        return null;
    }

    $supplierDir = $processedRoot . DIRECTORY_SEPARATOR . $supplier;
    if (!is_dir($supplierDir)) {
        return null;
    }

    foreach (glob($supplierDir . DIRECTORY_SEPARATOR . '*' . DIRECTORY_SEPARATOR . $filename) ?: [] as $candidate) {
        if (!is_file($candidate)) {
            continue;
        }
        if (@unlink($candidate)) {
            return (string) $candidate;
        }
    }

    return null;
}

function import_delete_stored_file(string $supplier, string $filename): array
{
    $supplier = strtolower(trim(preg_replace('/[^a-z0-9_]+/i', '', $supplier) ?? ''));
    $filename = basename(str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $filename));
    if ($supplier === '' || $filename === '' || !import_motor_supplier_is_allowed($supplier)) {
        return ['success' => false, 'error' => 'Fișier invalid sau furnizor neînregistrat'];
    }

    $deletedPaths = [];
    $path = import_resolve_stored_file($supplier, $filename);
    if ($path !== null && is_file($path)) {
        if (!@unlink($path)) {
            return ['success' => false, 'error' => 'Nu pot șterge fișierul din folderul furnizorului'];
        }
        $deletedPaths[] = $path;
        import_remove_state_for_path($path);
    }

    $archived = import_delete_archived_supplier_file($supplier, $filename);
    if ($archived !== null) {
        $deletedPaths[] = $archived;
        import_remove_state_for_path($archived);
    }

    $stagingPurged = import_purge_staging_for_supplier_file($supplier, $filename);

    if ($deletedPaths === [] && $stagingPurged === []) {
        return ['success' => false, 'error' => 'Fișier invalid sau inexistent'];
    }

    return [
        'success' => true,
        'deleted' => [
            'supplier' => $supplier,
            'filename' => $filename,
            'paths' => $deletedPaths,
            'path' => $deletedPaths[0] ?? null,
        ],
        'staging_purged' => $stagingPurged,
    ];
}

function import_delete_supplier_files(string $supplier): array
{
    $supplier = strtolower(trim(preg_replace('/[^a-z0-9_]+/i', '', $supplier) ?? ''));
    if ($supplier === '' || !import_motor_supplier_is_allowed($supplier)) {
        return ['success' => false, 'error' => 'Furnizor invalid sau neînregistrat'];
    }

    $deleted = [];
    $errors = [];
    foreach (import_motor_supplier_storage_dirs($supplier) as $realDir) {
        if (!is_dir($realDir)) {
            continue;
        }
        foreach (scandir($realDir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $realDir . DIRECTORY_SEPARATOR . $entry;
            if (!is_file($path)) {
                continue;
            }
            $ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
            if (!in_array($ext, ['csv', 'txt'], true)) {
                continue;
            }
            if (@unlink($path)) {
                import_remove_state_for_path($path);
                $deleted[] = $entry;
            } else {
                $errors[] = $entry;
            }
        }
    }

    if ($deleted === [] && $errors === []) {
        return ['success' => false, 'error' => 'Folder furnizor inexistent sau gol'];
    }

    $stagingPurged = import_purge_all_staging_for_supplier($supplier);

    return [
        'success' => true,
        'supplier' => $supplier,
        'folder' => import_supplier_folder_rel($supplier),
        'deleted' => $deleted,
        'errors' => $errors,
        'staging_purged' => $stagingPurged,
    ];
}

function import_cron_suppliers_schedule(): array
{
    import_motor_boot_admin_stack();

    $cronControl = import_read_cron_control();
    $cronSuppliers = is_array($cronControl['suppliers'] ?? null) ? $cronControl['suppliers'] : [];
    $controlMode = (string) ($cronControl['mode'] ?? 'running');

    $pendingBySupplier = [];
    $totalBySupplier = [];
    foreach (import_list_supplier_files() as $file) {
        $slug = strtolower(trim((string) ($file['supplier'] ?? '')));
        if ($slug === '') {
            continue;
        }
        $totalBySupplier[$slug] = ($totalBySupplier[$slug] ?? 0) + 1;
        if (in_array((string) ($file['status'] ?? ''), ['new', 'modified'], true)) {
            $pendingBySupplier[$slug] = ($pendingBySupplier[$slug] ?? 0) + 1;
        }
    }

    $now = new \DateTimeImmutable('now');
    $items = [];
    $hooksAvailable = class_exists(\Besoiu\Core\Supplier\SupplierHooks::class);

    foreach (import_motor_load_furnizori_rows() as $row) {
        if (!is_array($row)) {
            continue;
        }

        $code = function_exists('import_furnizori_normalize_code')
            ? import_furnizori_normalize_code((string) ($row['code'] ?? ''))
            : strtoupper(trim((string) ($row['code'] ?? '')));
        if ($code === '') {
            continue;
        }

        $status = strtolower(trim((string) ($row['status'] ?? 'active')));
        if (in_array($status, ['deleted', 'blocked', 'inactive'], true)) {
            continue;
        }

        $slug = function_exists('import_furnizori_search_slug')
            ? import_furnizori_search_slug($code)
            : strtolower($code);
        $slug = strtolower(trim($slug));

        $agentState = [];
        $cronEntry = $cronSuppliers[$slug] ?? null;
        if (is_array($cronEntry) && !empty($cronEntry['last_run_at'])) {
            $agentState['synced_at'] = (string) $cronEntry['last_run_at'];
        }

        $autoEnabled = !in_array((string) ($row['scan_auto_enabled'] ?? '1'), ['0', 'false', 'off', 'no'], true);
        $mode = strtolower(trim((string) ($row['scan_schedule_mode'] ?? 'interval')));
        if (!in_array($mode, ['interval', 'daily', 'window', 'manual'], true)) {
            $mode = 'interval';
        }

        if ($hooksAvailable) {
            try {
                \Besoiu\Core\Supplier\SupplierHooks::ensureHooks();
            } catch (\Throwable) {
                // optional
            }
            $scheduleLabel = \Besoiu\Core\Supplier\SupplierHooks::scanScheduleFormatLabel($row);
            $dueNow = $autoEnabled && $mode !== 'manual'
                && \Besoiu\Core\Supplier\SupplierHooks::scanScheduleShouldRunAuto($row, $agentState, $now);
            $nextRun = \Besoiu\Core\Supplier\SupplierHooks::scanScheduleEstimateNextRunAt($row, $agentState, $now);
            $nextLabel = \Besoiu\Core\Supplier\SupplierHooks::scanScheduleFormatNextRunLabel($row, $agentState, $now);
        } else {
            $scheduleLabel = \Besoiu\Core\Supplier\ScanScheduleFallback::formatLabel($row);
            $dueNow = false;
            $nextRun = null;
            $nextLabel = '—';
        }

        $pendingFiles = (int) ($pendingBySupplier[$slug] ?? 0);
        $runStatus = 'idle';
        if ($controlMode === 'paused') {
            $runStatus = 'paused';
        } elseif ($controlMode === 'stopped') {
            $runStatus = 'stopped';
        } elseif (!$autoEnabled || $mode === 'manual') {
            $runStatus = 'manual';
        } elseif ($dueNow) {
            $runStatus = 'due';
        } elseif ($pendingFiles > 0) {
            $runStatus = 'waiting';
        }

        $lastRunAt = $agentState['synced_at'] ?? null;
        if ($lastRunAt === null || $lastRunAt === '') {
            $dbLast = trim((string) ($row['last_scan_at'] ?? ''));
            $lastRunAt = $dbLast !== '' ? $dbLast : null;
        }

        $randomnId = (int) ($row['randomn_id'] ?? 0);

        $items[] = [
            'slug' => $slug,
            'code' => $code,
            'name' => trim((string) ($row['name'] ?? $code)),
            'randomn_id' => $randomnId,
            'schedule_mode' => $mode,
            'schedule_label' => $scheduleLabel,
            'interval_minutes' => max(5, (int) ($row['scan_interval_minutes'] ?? 60)),
            'auto_enabled' => $autoEnabled,
            'due_now' => $dueNow,
            'next_run_at' => $nextRun instanceof \DateTimeImmutable ? $nextRun->format('c') : null,
            'next_run_label' => $nextLabel,
            'next_run_seconds' => $nextRun instanceof \DateTimeImmutable
                ? max(0, $nextRun->getTimestamp() - $now->getTimestamp())
                : null,
            'last_run_at' => $lastRunAt,
            'pending_files' => $pendingFiles,
            'total_files' => (int) ($totalBySupplier[$slug] ?? 0),
            'status' => $runStatus,
            'profile_url' => $randomnId > 0
                ? '/admin/profilefurnizori?randomn_id=' . $randomnId . '&tab=conexiune'
                : null,
        ];
    }

    usort($items, static fn(array $a, array $b): int => strcmp((string) $a['name'], (string) $b['name']));

    $activeCount = count(array_filter(
        $items,
        static fn(array $item): bool => $item['auto_enabled'] && $item['schedule_mode'] !== 'manual'
    ));
    $dueCount = count(array_filter($items, static fn(array $item): bool => $item['due_now']));

    return [
        'suppliers' => $items,
        'cron_control_mode' => $controlMode,
        'watcher_hint' => 'Programează cron_watcher.bat la fiecare 5–15 min (Task Scheduler). '
            . 'Fiecare furnizor respectă propriul «Program sincronizare» din profil.',
        'active_count' => $activeCount,
        'due_count' => $dueCount,
        'server_time' => $now->format('c'),
    ];
}

function import_cron_status(bool $includeFileScan = true): array
{
    $files = $includeFileScan ? import_list_supplier_files() : [];
    $pending = count(array_filter($files, static fn(array $f): bool => in_array($f['status'], ['new', 'modified'], true)));
    $settings = json_decode((string) @file_get_contents(IMPORT_CONFIG . DIRECTORY_SEPARATOR . 'settings.json'), true) ?: [];
    $interval = (int) ($settings['cron_interval_minutes'] ?? 30);

    $lastLog = '';
    $logDir = IMPORT_ROOT . DIRECTORY_SEPARATOR . 'logs';
    $today = $logDir . DIRECTORY_SEPARATOR . 'import_' . date('Y-m-d') . '.log';
    if (is_file($today)) {
        $lines = file($today, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (is_array($lines) && $lines !== []) {
            $lastLog = $lines[array_key_last($lines)];
        }
    }

    return [
        'pending_files' => $pending,
        'total_files' => count($files),
        'cron_interval_minutes' => $interval,
        'cron_bat' => IMPORT_ROOT . DIRECTORY_SEPARATOR . 'cron_watcher.bat',
        'last_log_line' => $lastLog,
        'reports_recent' => import_list_reports(5),
        'suppliers_schedule' => import_cron_suppliers_schedule(),
    ];
}

function import_progress_path(): string
{
    return IMPORT_STATE_DIR . DIRECTORY_SEPARATOR . 'cron_progress.json';
}

function import_default_cron_progress(): array
{
    return [
        'status' => 'idle',
        'phase' => '',
        'percent' => 0,
        'message' => 'Inactiv',
        'current_file' => '',
        'current_supplier' => '',
        'file_index' => 0,
        'total_files' => 0,
        'started_at' => null,
        'finished_at' => null,
        'steps' => [],
        'summary' => [],
    ];
}

function import_sanitize_cron_progress(array $data): array
{
    $steps = $data['steps'] ?? [];
    if (is_array($steps) && count($steps) > 100) {
        $data['steps'] = array_slice($steps, -100);
    }

    $summary = $data['summary'] ?? [];
    if (is_array($summary)) {
        unset($summary['payload']);
        if (isset($summary['results']) && is_array($summary['results'])) {
            $summary['results'] = array_map(static function ($item): array {
                if (!is_array($item)) {
                    return [];
                }
                unset($item['payload'], $item['products']);
                return $item;
            }, $summary['results']);
        }
        $data['summary'] = $summary;
    }

    return $data;
}

function import_write_cron_progress_compact(array $data): void
{
    $path = import_progress_path();
    $dir = dirname($path);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $payload = import_sanitize_cron_progress($data);
    @file_put_contents($path, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

function import_recover_cron_progress_light(string $path, int $size): array
{
    $data = import_default_cron_progress();
    $chunk = (string) @file_get_contents($path, false, null, 0, 16384);
    if ($chunk !== '') {
        foreach ([
            'status' => '/"status"\s*:\s*"([^"]+)"/',
            'phase' => '/"phase"\s*:\s*"([^"]+)"/',
            'message' => '/"message"\s*:\s*"((?:\\\\.|[^"\\\\])*)"/',
            'current_file' => '/"current_file"\s*:\s*"((?:\\\\.|[^"\\\\])*)"/',
            'current_supplier' => '/"current_supplier"\s*:\s*"((?:\\\\.|[^"\\\\])*)"/',
            'started_at' => '/"started_at"\s*:\s*"([^"]+)"/',
            'finished_at' => '/"finished_at"\s*:\s*"([^"]+)"/',
        ] as $key => $pattern) {
            if (preg_match($pattern, $chunk, $m)) {
                $data[$key] = stripcslashes($m[1]);
            }
        }
        foreach (['percent' => '/"percent"\s*:\s*(\d+)/', 'file_index' => '/"file_index"\s*:\s*(\d+)/', 'total_files' => '/"total_files"\s*:\s*(\d+)/'] as $key => $pattern) {
            if (preg_match($pattern, $chunk, $m)) {
                $data[$key] = (int) $m[1];
            }
        }
        $summary = [];
        foreach (['pending', 'processed', 'skipped'] as $key) {
            if (preg_match('/"' . $key . '"\s*:\s*(\d+)/', $chunk, $m)) {
                $summary[$key] = (int) $m[1];
            }
        }
        if ($summary !== []) {
            $data['summary'] = $summary;
        }
    }

    $backup = $path . '.bloated.' . date('YmdHis');
    @rename($path, $backup);
    $data['message'] = trim((string) ($data['message'] ?: 'Inactiv'))
        . ' — progres resetat (fișier ' . round($size / 1048576, 1) . ' MB)';
    import_write_cron_progress_compact($data);

    return import_sanitize_cron_progress($data);
}

function import_read_cron_progress(): array
{
    $path = import_progress_path();
    if (!is_file($path)) {
        return import_default_cron_progress();
    }

    $size = filesize($path);
    if ($size === false) {
        return import_default_cron_progress();
    }
    if ($size > 524288) {
        return import_recover_cron_progress_light($path, $size);
    }

    $raw = @file_get_contents($path);
    if ($raw === false) {
        return import_default_cron_progress();
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        return import_default_cron_progress();
    }

    if (($data['status'] ?? '') === 'running' && !empty($data['started_at'])) {
        $started = strtotime((string) $data['started_at']);
        if ($started !== false && (time() - $started) > 7200) {
            $data['status'] = 'idle';
            $data['message'] = 'Rulare expirată (posibil blocaj) — repornește cron';
        }
    }

    return import_sanitize_cron_progress($data);
}

function import_reset_cron_progress(string $message = 'Inactiv'): void
{
    $data = import_default_cron_progress();
    $data['message'] = $message;
    $data['finished_at'] = date('c');
    import_write_cron_progress_compact($data);
}

/**
 * Scrie sincron (din PHP, înainte de a porni procesul Python) un progres "proaspăt"
 * pentru o rulare nouă de cron/test — elimină fereastra în care primul poll de pe UI
 * mai afișează încă datele vechi ale rulării anterioare (percent/steps/summary stale).
 */
function import_start_fresh_cron_progress(string $message, string $runId): array
{
    $data = import_default_cron_progress();
    $data['status'] = 'running';
    $data['phase'] = 'init';
    $data['percent'] = 0;
    $data['message'] = $message;
    $data['started_at'] = date('c');
    $data['finished_at'] = null;
    $data['run_id'] = $runId;
    $data['steps'] = [[
        'at' => date('c'),
        'message' => $message,
        'level' => 'info',
    ]];
    import_write_cron_progress_compact($data);

    return $data;
}

function import_clear_processed_cache(): bool
{
    return import_save_processed_state(['files' => []]);
}

/**
 * @return list<int>
 */
function import_kill_cron_processes(): array
{
    $killed = [];

    if (PHP_OS_FAMILY === 'Windows') {
        $script = IMPORT_ROOT . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'kill_cron.ps1';
        if (!is_file($script)) {
            return $killed;
        }
        $cmd = 'powershell -NoProfile -ExecutionPolicy Bypass -File '
            . escapeshellarg($script) . ' 2>NUL';
        $out = shell_exec($cmd);
        if (is_string($out) && preg_match_all('/\d+/', trim($out), $m)) {
            foreach ($m[0] as $pid) {
                $killed[] = (int) $pid;
            }
        }
        return array_values(array_unique(array_filter($killed, static fn(int $p): bool => $p > 0)));
    }

    exec('pkill -f "cron_watcher.py" 2>/dev/null');
    exec('pkill -f "run_import.py cron" 2>/dev/null');

    return $killed;
}

function import_stop_cron(bool $clearCache = false): array
{
    $killed = import_kill_cron_processes();
    import_reset_cron_progress('Oprit manual — cron inactiv');
    $cacheCleared = false;
    if ($clearCache) {
        $cacheCleared = import_clear_processed_cache();
    }

    return [
        'killed_pids' => $killed,
        'cache_cleared' => $cacheCleared,
        'progress' => import_read_cron_progress(),
    ];
}

/**
 * @param string|null $sinceIso Dacă e setat (ISO 8601, ex. "2026-07-17T12:54:32"),
 *                               se elimină liniile de log scrise ÎNAINTE de acest
 *                               moment — astfel logul afișat pe UI conține doar
 *                               rularea curentă, nu se mai amestecă cu rulările
 *                               anterioare din aceeași zi calendaristică.
 */
/**
 * Scrie o linie în același fișier de log zilnic folosit de Python (`logger.py`),
 * cu format identic — folosit pentru narațiunea per-produs din `stage_cron_batch.php`,
 * ca liniile PHP și cele Python să apară intercalate cronologic în „Log activitate cron (live)”.
 */
function import_motor_append_log(string $message, string $level = 'INFO'): void
{
    $logDir = IMPORT_ROOT . DIRECTORY_SEPARATOR . 'logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0775, true);
    }
    $path = $logDir . DIRECTORY_SEPARATOR . 'import_' . date('Y-m-d') . '.log';
    $line = '[' . date('c') . '] [' . $level . '] ' . $message . "\n";
    @file_put_contents($path, $line, FILE_APPEND | LOCK_EX);
}

function import_tail_log(int $maxLines = 80, ?string $sinceIso = null): array
{
    $logDir = IMPORT_ROOT . DIRECTORY_SEPARATOR . 'logs';
    $today = $logDir . DIRECTORY_SEPARATOR . 'import_' . date('Y-m-d') . '.log';
    if (!is_file($today)) {
        return [];
    }
    $lines = file($today, FILE_IGNORE_NEW_LINES);
    if (!is_array($lines)) {
        return [];
    }
    $lines = array_values(array_filter($lines, static fn(string $l): bool => trim($l) !== ''));

    if ($sinceIso !== null && $sinceIso !== '') {
        // Formatul liniei e "[2026-07-17T12:54:32] [INFO] mesaj" — comparație lexicografică
        // funcționează direct pe ISO 8601 fără parsare (aceeași lungime/format/timezone).
        $sinceCompact = substr($sinceIso, 0, 19);
        $filtered = array_values(array_filter($lines, static function (string $l) use ($sinceCompact): bool {
            if (preg_match('/^\[([\d\-T:]{19})/', $l, $m)) {
                return $m[1] >= $sinceCompact;
            }
            return true;
        }));
        // Dacă filtrarea a eliminat tot (ex. ceasul serverului diferă puțin), preferăm
        // să arătăm ceva decât un log complet gol.
        if ($filtered !== []) {
            $lines = $filtered;
        }
    }

    return array_slice($lines, -$maxLines);
}

function import_start_background_python(array $args): bool
{
    [$bin, $binArgs] = import_python_cmd();
    $script = IMPORT_SRC . DIRECTORY_SEPARATOR . 'run_import.py';
    $parts = array_merge([$bin], $binArgs, [$script], array_map('strval', $args));

    $quoted = array_map(static function (string $p): string {
        if ($p === '') {
            return '""';
        }
        if (preg_match('/[\s"]/', $p)) {
            return '"' . str_replace('"', '\\"', $p) . '"';
        }
        return $p;
    }, $parts);

    $cmdLine = implode(' ', $quoted);
    if (PHP_OS_FAMILY === 'Windows') {
        $full = 'start /B "" ' . $cmdLine . ' > NUL 2>&1';
        $handle = @popen($full, 'r');
        if ($handle === false) {
            return false;
        }
        pclose($handle);
        return true;
    }

    $full = $cmdLine . ' > /dev/null 2>&1 &';
    exec($full);
    return true;
}

/**
 * @return array<string, mixed>
 */
function import_tecdoc_mysql_source(): array
{
    $source = [
        'id' => 'tecdoc_mysql',
        'label' => 'MySQL besoiu_tecdoc_base (TecDoc)',
        'path' => 'MySQL 127.0.0.1 / besoiu_tecdoc_base',
        'table' => 'brands · products · product_codes · product_compatibilities',
        'columns' => ['brand', 'art_code_1', 'art_code_2', 'art_name', 'code_norm', 'ttc_art_id'],
        'match_field' => 'brand + code_norm',
        'match_how' => 'Cod OEM + producător → product_codes → products (titlu, EAN, compatibilități)',
        'active' => false,
        'rows' => null,
        'used_in' => 'Generează carduri (Pasul 3) — NU la matching scan Python',
        'stats' => [],
    ];

    try {
        $pdo = new PDO(
            'mysql:host=127.0.0.1;dbname=besoiu_tecdoc_base;charset=utf8mb4',
            'root',
            '',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 3]
        );
        $stmt = $pdo->query(
            "SELECT table_name, table_rows FROM information_schema.tables "
            . "WHERE table_schema = DATABASE() "
            . "AND table_name IN ('products', 'product_codes', 'brands')"
        );
        $rows = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $rows[(string) ($row['table_name'] ?? '')] = (int) ($row['table_rows'] ?? 0);
        }
        $brands = (int) ($rows['brands'] ?? 0);
        $products = (int) ($rows['products'] ?? 0);
        $codes = (int) ($rows['product_codes'] ?? 0);
        if ($products <= 0 && $codes <= 0 && $brands <= 0) {
            $brands = (int) $pdo->query('SELECT COUNT(*) FROM brands')->fetchColumn();
        }
        $source['active'] = $products > 0;
        $source['rows'] = $products;
        $source['stats'] = [
            'brands' => $brands,
            'products' => $products,
            'product_codes' => $codes,
            'approximate' => true,
        ];
    } catch (Throwable) {
        $source['active'] = false;
        $source['match_how'] .= ' — MySQL indisponibil sau neindexat';
    }

    return $source;
}

/**
 * @param array<string, mixed> $product
 * @param array<string, mixed> $extras
 * @return array<string, mixed>
 */
function import_build_tecdoc_audit(array $product, array $extras = []): array
{
    $method = trim((string) ($product['match_method'] ?? $extras['matchMethod'] ?? ''));
    $db = trim((string) ($product['tecdoc_db'] ?? 'besoiu_tecdoc_base'));
    $tables = trim((string) ($product['tecdoc_tables'] ?? ''));
    if ($tables === '') {
        $tables = $method === 'tecdoc_ean'
            ? 'products · brands'
            : ($method !== '' ? 'product_codes · products · brands' : '');
    }

    $internalSku = trim((string) ($product['matched_internal_sku'] ?? $extras['matchedTecdocSku'] ?? ''));
    $productId = $product['matched_product_id'] ?? null;
    if ($productId === null && preg_match('/^TEC-(\d+)$/', $internalSku, $m)) {
        $productId = (int) $m[1];
    }

    $methodLabels = [
        'tecdoc_brand_code' => 'Brand + cod OEM',
        'tecdoc_ean' => 'EAN',
        'tecdoc_code' => 'Doar cod (fără brand confirmat)',
    ];

    $status = (string) ($product['status'] ?? $extras['matchStatus'] ?? '');
    $found = in_array($status, ['exact', 'probable', 'conflict'], true);

    $fieldsUsed = [];
    if (!empty($extras['titleFrom'])) {
        $fieldsUsed[] = 'titlu: ' . $extras['titleFrom'];
    }
    if (!empty($extras['compatCount'])) {
        $fieldsUsed[] = 'compatibilități vehicule (product_compatibilities)';
    }
    if (!empty($extras['imageFrom'])) {
        $fieldsUsed[] = 'imagine: ' . $extras['imageFrom'];
    }
    if ($found && ($product['matched_name'] ?? '') !== '') {
        $fieldsUsed[] = 'ART_NAME (nume articol)';
    }
    if ($found && ($product['matched_ttc_art_id'] ?? '') !== '') {
        $fieldsUsed[] = 'TTC_ART_ID (poze)';
    }

    return [
        'found' => $found,
        'db' => $db,
        'tables' => $tables,
        'matchStatus' => $status,
        'matchMethod' => $method,
        'matchMethodLabel' => $methodLabels[$method] ?? ($method !== '' ? $method : '—'),
        'confidence' => isset($product['confidence']) && is_numeric($product['confidence'])
            ? (float) $product['confidence']
            : null,
        'productId' => $productId,
        'internalSku' => $internalSku,
        'brand' => trim((string) ($product['matched_brand'] ?? '')),
        'name' => trim((string) ($product['matched_name'] ?? $extras['matchedName'] ?? '')),
        'codes' => array_values(array_filter(
            is_array($product['matched_codes'] ?? null)
                ? $product['matched_codes']
                : [],
            static fn ($c) => trim((string) $c) !== ''
        )),
        'ttcArtId' => trim((string) ($product['matched_ttc_art_id'] ?? $extras['ttcArtId'] ?? '')),
        'ean' => trim((string) ($product['matched_ean'] ?? '')),
        'cardBuild' => trim((string) ($extras['cardBuild'] ?? '')),
        'titleFrom' => trim((string) ($extras['titleFrom'] ?? '')),
        'fieldsUsed' => $fieldsUsed,
        'supplierSku' => trim((string) ($product['sku_supplier'] ?? $extras['supplierSku'] ?? '')),
        'supplierBrand' => trim((string) ($product['brand'] ?? $extras['supplierBrand'] ?? '')),
        'supplierName' => trim((string) ($product['name'] ?? $extras['supplierName'] ?? '')),
        'notes' => is_array($product['notes'] ?? null) ? $product['notes'] : [],
    ];
}

/**
 * @param array<string, mixed> $payload
 * @return array<string, mixed>
 */
function import_filter_scan_payload_by_image(array $payload, int $targetLimit = 20): array
{
    $products = $payload['products'] ?? null;
    if (!is_array($products) || $products === []) {
        return $payload;
    }

    $matcherPath = import_motor_product_matcher_path();
    if (!is_file($matcherPath)) {
        return $payload;
    }
    require_once $matcherPath;
    $matcher = new ProductMatcher();
    $allowAutopartner = import_payload_allows_autopartner_images($payload);

    $filtered = [];
    foreach ($products as $product) {
        if (!is_array($product)) {
            continue;
        }
        $status = (string) ($product['status'] ?? '');
        if (!in_array($status, ['exact', 'probable', 'conflict'], true)) {
            continue;
        }
        if (count($filtered) >= $targetLimit) {
            break;
        }
        $brand = trim((string) ($product['matched_brand'] ?? $product['brand'] ?? ''));
        $sku = trim((string) ($product['sku_supplier'] ?? ''));
        if ($brand === '' || $sku === '') {
            continue;
        }
        $productAllowAp = $allowAutopartner || import_product_allows_autopartner_images($product);
        $matchedCodes = is_array($product['matched_codes'] ?? null) ? $product['matched_codes'] : [];
        [$updated] = import_select_verified_local_image_card(
            $matcher,
            $brand,
            $sku,
            $matchedCodes,
            $productAllowAp,
            import_product_vision_query($product),
            (string) ($product['matched_ttc_art_id'] ?? '')
        );
        if ($updated === null || empty($updated['hasImage']) || empty($updated['imageDisplayUrl'])) {
            continue;
        }
        $product['has_image'] = true;
        $product['image_source'] = $updated['imageSource'] ?? '';
        $product['ttc_art_id'] = $updated['ttcArtId'] ?? ($product['matched_ttc_art_id'] ?? '');
        $product['autopartner_code'] = $updated['autopartnerCode'] ?? '';
        $product['poze_folder'] = (string) ($updated['pozeFolder'] ?? '');
        $product['image_url'] = (string) ($updated['imageDisplayUrl'] ?? '');
        $product['image_quality_score'] = $updated['imageQualityScore'] ?? null;
        $product['image_quality_reason'] = $updated['imageQualityReason'] ?? '';
        $filtered[] = $product;
    }

    $payload['products'] = $filtered;
    if (isset($payload['summary']) && is_array($payload['summary'])) {
        $payload['summary']['total'] = count($filtered);
        $payload['summary']['only_with_image'] = true;
        $payload['summary']['image_filtered_from'] = count($products);
    }
    $payload['only_with_image'] = true;

    return $payload;
}

/**
 * Atașează imagini locale (Poze / Autopartner) pe produsele din raportul de matching.
 *
 * @param array<string, mixed> $payload
 * @return array<string, mixed>
 */
function import_enrich_match_payload_images(array $payload): array
{
    $products = $payload['products'] ?? null;
    if (!is_array($products) || $products === []) {
        return $payload;
    }

    $matcherPath = import_motor_product_matcher_path();
    if (!is_file($matcherPath)) {
        return $payload;
    }
    require_once $matcherPath;
    $matcher = new ProductMatcher();
    $allowAutopartner = import_payload_allows_autopartner_images($payload);

    foreach ($products as $idx => $product) {
        if (!is_array($product)) {
            continue;
        }
        $productAllowAp = $allowAutopartner || import_product_allows_autopartner_images($product);
        // Coliziune SKU cross-supplier: nu reutiliza imagini Autopartner pe ELIT/etc.
        if (!$productAllowAp && ($product['image_source'] ?? '') === 'autopartner') {
            $product['image_source'] = '';
            $product['autopartner_code'] = '';
            $product['has_image'] = false;
            $product['image_url'] = '';
        }

        $brand = trim((string) ($product['matched_brand'] ?? $product['brand'] ?? ''));
        $code = trim((string) ($product['sku_supplier'] ?? ''));
        if ($brand === '' || $code === '') {
            $products[$idx] = $product;
            continue;
        }
        $matchedCodes = is_array($product['matched_codes'] ?? null) ? $product['matched_codes'] : [];
        [$updated, $verify] = import_select_verified_local_image_card(
            $matcher,
            $brand,
            $code,
            $matchedCodes,
            $productAllowAp,
            import_product_vision_query($product),
            (string) ($product['matched_ttc_art_id'] ?? '')
        );
        if ($updated === null || empty($updated['hasImage']) || empty($updated['imageDisplayUrl'])) {
            $product['image_quality_score'] = $verify['score'] ?? null;
            $product['image_quality_reason'] = $verify['reason'] ?? '';
            $product['image_rejected'] = empty($verify['skipped']) && empty($verify['ok']);
            $products[$idx] = $product;
            continue;
        }
        $product['has_image'] = true;
        $product['image_source'] = $updated['imageSource'] ?? '';
        $product['ttc_art_id'] = $updated['ttcArtId'] ?? ($product['matched_ttc_art_id'] ?? '');
        $product['autopartner_code'] = $updated['autopartnerCode'] ?? '';
        $product['poze_folder'] = (string) ($updated['pozeFolder'] ?? '');
        $product['image_url'] = (string) ($updated['imageDisplayUrl'] ?? '');
        $product['image_quality_score'] = $updated['imageQualityScore'] ?? null;
        $product['image_quality_reason'] = $updated['imageQualityReason'] ?? '';
        $product['image_rejected'] = false;
        $products[$idx] = $product;
    }

    $payload['products'] = $products;

    return $payload;
}

/**
 * Imagini Autopartner doar pentru feed-ul Autopartner (SKU-urile lor ≠ SKU ELIT/etc.).
 */
function import_payload_allows_autopartner_images(array $payload): bool
{
    $supplier = strtolower(trim((string) ($payload['supplier'] ?? '')));
    if ($supplier === '' && isset($payload['source']) && is_array($payload['source'])) {
        $supplier = strtolower(trim((string) ($payload['source']['supplier'] ?? '')));
    }
    if ($supplier === '' && isset($payload['file'])) {
        $file = strtolower(str_replace('\\', '/', (string) $payload['file']));
        if (str_contains($file, 'autopartner')) {
            $supplier = 'autopartner';
        }
    }

    return import_supplier_slug_allows_autopartner_images($supplier);
}

/** @param array<string, mixed> $product */
function import_product_allows_autopartner_images(array $product): bool
{
    $supplier = strtolower(trim((string) ($product['supplier'] ?? $product['source_supplier'] ?? '')));
    if ($supplier === '') {
        $sourceFile = strtolower(str_replace('\\', '/', (string) ($product['source_filename'] ?? $product['sourceFile'] ?? '')));
        if (str_contains($sourceFile, 'autopartner')) {
            return true;
        }
    }

    return import_supplier_slug_allows_autopartner_images($supplier);
}

function import_supplier_slug_allows_autopartner_images(string $supplier): bool
{
    $supplier = strtolower(trim($supplier));
    return $supplier === 'autopartner' || $supplier === 'ap';
}

function import_env_flag_enabled(string $key, bool $default = false): bool
{
    $raw = strtolower(trim((string) ($_ENV[$key] ?? $_SERVER[$key] ?? getenv($key) ?: '')));
    if ($raw === '') {
        return $default;
    }

    return !in_array($raw, ['0', 'false', 'no', 'off'], true);
}

/** Flag din .env — IMPORT_OLLAMA_QUALITY_CHECK=1 (scrape / verificare explicită). */
function import_ollama_quality_check_enabled(): bool
{
    if (class_exists(\Besoiu\Import\Support\OllamaEnvBridge::class)) {
        \Besoiu\Import\Support\OllamaEnvBridge::apply();
    }
    import_scraper_llm_config();

    return import_env_flag_enabled('IMPORT_OLLAMA_QUALITY_CHECK', false);
}

/**
 * Vision pe scan bulk („Generez carduri”) — OFF by default.
 * llava ~20–30s/card blochează pipeline-ul; se activează doar cu
 * IMPORT_OLLAMA_QUALITY_ON_SCAN=1 sau import_ollama_image_verify_runtime(true).
 */
function import_ollama_image_verify_runtime(?bool $enable = null): bool
{
    static $override = null;
    if ($enable !== null) {
        $override = $enable;
    }
    if ($override !== null) {
        return $override;
    }
    if (class_exists(\Besoiu\Import\Support\OllamaEnvBridge::class)) {
        \Besoiu\Import\Support\OllamaEnvBridge::apply();
    }

    return import_env_flag_enabled('IMPORT_OLLAMA_QUALITY_ON_SCAN', false);
}

/** Vision activ doar dacă flag-ul general e on ȘI runtime/scan o cere. */
function import_ollama_local_image_verify_active(): bool
{
    return import_ollama_quality_check_enabled() && import_ollama_image_verify_runtime();
}

function import_ollama_quality_min_score(): int
{
    if (class_exists(\Besoiu\Import\Support\OllamaEnvBridge::class)) {
        \Besoiu\Import\Support\OllamaEnvBridge::apply();
    }
    $raw = (int) ($_ENV['OLLAMA_QUALITY_MIN_SCORE'] ?? getenv('OLLAMA_QUALITY_MIN_SCORE') ?: 50);

    return max(20, min(90, $raw > 0 ? $raw : 50));
}

function import_boot_scraper_ollama_client(): bool
{
    static $booted = false;
    if ($booted) {
        return class_exists('ScraperOllamaClient', false);
    }
    $booted = true;
    $path = dirname(IMPORT_ROOT) . DIRECTORY_SEPARATOR . 'Scraper' . DIRECTORY_SEPARATOR . 'lib'
        . DIRECTORY_SEPARATOR . 'ScraperOllamaClient.php';
    if (!is_file($path)) {
        return false;
    }
    require_once $path;

    return class_exists('ScraperOllamaClient', false);
}

function import_boot_orchestr_vision_client(): bool
{
    static $booted = false;
    if ($booted) {
        return class_exists('OrchestrOllamaVisionClient', false);
    }
    $booted = true;
    $orchestrBoot = dirname(IMPORT_ROOT) . DIRECTORY_SEPARATOR . 'modulOrchestr' . DIRECTORY_SEPARATOR . 'bootstrap.php';
    if (!is_file($orchestrBoot)) {
        return false;
    }
    require_once $orchestrBoot;

    return class_exists('OrchestrOllamaVisionClient', false);
}

/** @param array<string, mixed> $product */
function import_product_vision_query(array $product): string
{
    $name = trim((string) (
        $product['name']
        ?? $product['title']
        ?? $product['denumire']
        ?? $product['matched_name']
        ?? $product['tecdoc_name']
        ?? ''
    ));
    $brand = trim((string) ($product['matched_brand'] ?? $product['brand'] ?? ''));
    $sku = trim((string) ($product['sku_supplier'] ?? $product['sku'] ?? ''));

    return trim(implode(' ', array_filter([$name, $brand, $sku], static fn(string $p): bool => $p !== '')));
}

/** @param array<string, mixed> $card */
function import_card_local_image_absolute_path(array $card): ?string
{
    import_motor_prelucrare_libs_booted();
    $source = strtolower(trim((string) ($card['imageSource'] ?? '')));
    if ($source === 'autopartner') {
        $code = trim((string) ($card['autopartnerCode'] ?? ''));
        if ($code === '') {
            return null;
        }
        $resolver = new AutopartnerImageResolver();
        $img = $resolver->getImageByApCode($code);
        $path = is_array($img) ? (string) ($img['fullPath'] ?? '') : '';

        return ($path !== '' && is_file($path)) ? $path : null;
    }
    if ($source === 'poze') {
        $ttcId = trim((string) ($card['ttcArtId'] ?? ''));
        $brand = trim((string) ($card['pozeFolder'] ?? $card['pozeBrand'] ?? $card['brand'] ?? ''));
        $brand = ltrim($brand, '=');
        if ($ttcId === '' || $brand === '') {
            return null;
        }

        return PozeFolderResolver::findImagePath($brand, $ttcId);
    }

    return null;
}

/**
 * Vision Ollama: imaginea arată produsul din query?
 * Folosește strict vision (fără fallback text) — altfel Alternator+miner haion trecea pe text.
 * Fail-open dacă Ollama/vision e oprit (nu blochează scanul).
 *
 * @param array<string, mixed> $card
 * @return array{ok: bool, skipped: bool, score: int, reason: string, source: string}
 */
function import_ollama_verify_local_image(string $query, array $card): array
{
    // Scan bulk: fără vision (altfel Generez carduri rămâne la 0 carduri zeci de secunde).
    if (!import_ollama_local_image_verify_active()) {
        return ['ok' => true, 'skipped' => true, 'score' => 100, 'reason' => 'vision off pe scan bulk', 'source' => 'disabled'];
    }
    $query = trim($query);
    if ($query === '') {
        return ['ok' => true, 'skipped' => true, 'score' => 100, 'reason' => 'query gol', 'source' => 'disabled'];
    }

    $path = import_card_local_image_absolute_path($card);
    $imageRef = $path ?? trim((string) ($card['imageDisplayUrl'] ?? ''));
    if ($imageRef === '') {
        return ['ok' => false, 'skipped' => false, 'score' => 0, 'reason' => 'imagine fără path', 'source' => 'local'];
    }

    if (!import_boot_orchestr_vision_client()) {
        return ['ok' => true, 'skipped' => true, 'score' => 0, 'reason' => 'orchestr vision lipsă', 'source' => 'unavailable'];
    }

    $ready = OrchestrOllamaVisionClient::readiness();
    if (empty($ready['ready'])) {
        return [
            'ok' => true,
            'skipped' => true,
            'score' => 0,
            'reason' => (string) ($ready['reason'] ?? 'Ollama vision indisponibil'),
            'source' => 'unavailable',
        ];
    }

    try {
        // requireVision=true: NU acceptă fallback text (care ar da match pe denumire fără a vedea poza).
        $result = OrchestrOllamaVisionClient::analyzeProductRelevance($query, $query, $imageRef, true);
    } catch (Throwable $e) {
        return ['ok' => true, 'skipped' => true, 'score' => 0, 'reason' => $e->getMessage(), 'source' => 'error'];
    }

    $source = (string) ($result['source'] ?? '');
    if ($source !== 'ollama_vision') {
        return [
            'ok' => true,
            'skipped' => true,
            'score' => (int) ($result['score'] ?? 0),
            'reason' => (string) ($result['reason_ro'] ?? 'vision eșuat — skip'),
            'source' => $source !== '' ? $source : 'unavailable',
        ];
    }

    $score = (int) ($result['score'] ?? 0);
    $min = import_ollama_quality_min_score();
    $verdict = strtolower(trim((string) ($result['verdict'] ?? '')));
    $relevant = !empty($result['relevant']) && $score >= $min && $verdict !== 'mismatch';

    return [
        'ok' => $relevant,
        'skipped' => false,
        'score' => $score,
        'reason' => (string) ($result['reason_ro'] ?? ''),
        'source' => 'ollama_vision',
    ];
}

/**
 * Caută în Poze/Autopartner pe mai multe coduri; respinge candidații pe care vision îi consideră greșiți.
 *
 * @param list<string> $codeCandidates
 * @return array{0: ?array<string, mixed>, 1: array{ok: bool, skipped: bool, score: int, reason: string, source: string}}
 */
function import_select_verified_local_image_card(
    ProductMatcher $matcher,
    string $brand,
    string $primaryCode,
    array $codeCandidates,
    bool $allowAutopartner,
    string $visionQuery,
    string $ttcArtId = ''
): array {
    $tried = [];
    $codes = [];
    foreach ($codeCandidates as $c) {
        $c = trim((string) $c);
        if ($c !== '') {
            $codes[$c] = true;
        }
    }
    $primaryCode = trim($primaryCode);
    if ($primaryCode !== '') {
        $codes[$primaryCode] = true;
    }
    $codeNorm = strtoupper(preg_replace('/[^A-Z0-9]/', '', $primaryCode) ?? '');
    if ($codeNorm !== '') {
        $codes[$codeNorm] = true;
        $stripped = ltrim($codeNorm, '0');
        if ($stripped !== '') {
            $codes[$stripped] = true;
        }
    }

    $lastReject = [
        'ok' => false,
        'skipped' => false,
        'score' => 0,
        'reason' => 'niciun candidat în Poze/Autopartner',
        'source' => 'none',
    ];

    $extraCodes = array_keys($codes);
    foreach (array_keys($codes) as $codeRaw) {
        $code = (string) $codeRaw;
        $probe = [
            'sku' => $code,
            'brand' => $brand,
            'hasImage' => false,
            'ttcArtId' => $ttcArtId,
            'autopartnerCode' => '',
            'imageSource' => '',
        ];
        // Un film (brand+cod) — încearcă toate „biletele” de cod (matched_codes / OEM / sku).
        $card = import_normalize_card_image_fields(
            $matcher->attachImageToCard($probe, $brand, $code, $allowAutopartner, $extraCodes)
        );
        if (empty($card['hasImage']) || empty($card['imageDisplayUrl'])) {
            continue;
        }
        if (!$allowAutopartner && ($card['imageSource'] ?? '') === 'autopartner') {
            continue;
        }

        $key = ($card['imageSource'] ?? '') . '|' . ($card['ttcArtId'] ?? '') . '|' . ($card['autopartnerCode'] ?? '');
        if (isset($tried[$key])) {
            continue;
        }
        $tried[$key] = true;

        $verify = import_ollama_verify_local_image($visionQuery, $card);
        if (!$verify['ok']) {
            $lastReject = $verify;
            continue;
        }

        $card['imageQualityScore'] = $verify['score'];
        $card['imageQualityReason'] = $verify['reason'];
        $card['imageQualitySource'] = $verify['source'];
        $card['imageQualitySkipped'] = !empty($verify['skipped']);

        return [$card, $verify];
    }

    return [null, $lastReject];
}

/**
 * Caută imagine locală (Poze / Autopartner / TecDoc) pe un card vitrină sau import.
 * Cu IMPORT_OLLAMA_QUALITY_CHECK=1, respinge pozele care nu corespund denumirii.
 *
 * @param array<string, mixed> $card
 * @return array<string, mixed>
 */
function import_attach_local_image_to_card(array $card): array
{
    if (!empty($card['hasImage']) && !empty($card['imageDisplayUrl'])) {
        $normalized = import_normalize_card_image_fields($card);
        if (empty($normalized['hasImage'])) {
            return $normalized;
        }
        $query = import_product_vision_query($card);
        $verify = import_ollama_verify_local_image($query, $normalized);
        if (!$verify['ok']) {
            $normalized['hasImage'] = false;
            $normalized['imageSource'] = '';
            $normalized['ttcArtId'] = '';
            $normalized['autopartnerCode'] = '';
            $normalized['imageUrl'] = '';
            $normalized['imageDisplayUrl'] = '';
            $normalized['pozeFolder'] = '';
            $normalized['imageQualityScore'] = $verify['score'];
            $normalized['imageQualityReason'] = $verify['reason'];
            $normalized['imageRejected'] = true;

            return $normalized;
        }
        $normalized['imageQualityScore'] = $verify['score'];
        $normalized['imageQualityReason'] = $verify['reason'];
        $normalized['imageQualitySkipped'] = !empty($verify['skipped']);

        return $normalized;
    }

    $brand = trim((string) ($card['brand'] ?? ''));
    $code = trim((string) ($card['sku'] ?? ''));
    if ($brand === '' || $code === '') {
        return $card;
    }

    $matcherPath = import_motor_product_matcher_path();
    if (!is_file($matcherPath)) {
        return $card;
    }
    require_once $matcherPath;
    static $matcher = null;
    if ($matcher === null) {
        $matcher = new ProductMatcher();
    }

    $allowAutopartner = import_product_allows_autopartner_images($card)
        || import_supplier_slug_allows_autopartner_images(
            strtolower(trim((string) ($card['sourceSupplier'] ?? $card['supplier'] ?? '')))
        );

    [$updated] = import_select_verified_local_image_card(
        $matcher,
        $brand,
        $code,
        is_array($card['matchedCodes'] ?? null) ? $card['matchedCodes'] : [],
        $allowAutopartner,
        import_product_vision_query($card),
        (string) ($card['ttcArtId'] ?? '')
    );

    if ($updated === null) {
        return $card;
    }

    $card['hasImage'] = true;
    $card['ttcArtId'] = (string) ($updated['ttcArtId'] ?? $card['ttcArtId'] ?? '');
    $card['autopartnerCode'] = (string) ($updated['autopartnerCode'] ?? $card['autopartnerCode'] ?? '');
    $card['imageSource'] = (string) ($updated['imageSource'] ?? '');
    $card['pozeFolder'] = (string) ($updated['pozeFolder'] ?? '');
    $card['imageUrl'] = (string) ($updated['imageDisplayUrl'] ?? ($updated['imageUrl'] ?? ''));
    $card['imageDisplayUrl'] = (string) ($updated['imageDisplayUrl'] ?? '');
    $card['imageQualityScore'] = $updated['imageQualityScore'] ?? null;
    $card['imageQualityReason'] = $updated['imageQualityReason'] ?? '';
    $card['imageQualitySkipped'] = !empty($updated['imageQualitySkipped']);

    return import_normalize_card_image_fields($card);
}

/**
 * @param list<array<string, mixed>> $cards
 * @return list<array<string, mixed>>
 */
function import_enrich_showcase_cards_images(array $cards): array
{
    $out = [];
    foreach ($cards as $card) {
        if (!is_array($card)) {
            continue;
        }
        $out[] = import_attach_local_image_to_card($card);
    }

    return $out;
}

/**
 * @param array<string, mixed> $payload
 * @return array<string, mixed>
 */
function import_enrich_inspect_payload(array $payload): array
{
    if (!empty($payload['data_sources']) && ($payload['settings']['use_tecdoc_only'] ?? true)) {
        return $payload;
    }
    $sources = is_array($payload['data_sources'] ?? null) ? $payload['data_sources'] : [];
    $sources[] = import_tecdoc_mysql_source();
    $payload['data_sources'] = $sources;
    return $payload;
}

function import_scraper_llm_config(): void
{
    static $loaded = false;
    if ($loaded || class_exists('ScraperLlmConfig', false)) {
        $loaded = true;

        return;
    }
    $lib = dirname(IMPORT_ROOT) . DIRECTORY_SEPARATOR . 'Scraper' . DIRECTORY_SEPARATOR . 'lib';
    $cfg = $lib . DIRECTORY_SEPARATOR . 'ScraperLlmConfig.php';
    if (is_file($cfg)) {
        require_once $cfg;
    }
    $loaded = true;
}

function import_ollama_available(): bool
{
    import_scraper_llm_config();
    if (!class_exists('ScraperLlmConfig') || !ScraperLlmConfig::ollamaEnabled()) {
        return false;
    }
    $url = ScraperLlmConfig::ollamaBaseUrl() . '/api/tags';
    $ch = curl_init($url);
    if ($ch === false) {
        return false;
    }
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 4,
        CURLOPT_CONNECTTIMEOUT => 2,
    ]);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $body !== false && $code >= 200 && $code < 300;
}

function import_ollama_model(): string
{
    $settings = json_decode((string) @file_get_contents(IMPORT_CONFIG . DIRECTORY_SEPARATOR . 'settings.json'), true) ?: [];
    $fromSettings = trim((string) ($settings['ollama_model'] ?? ''));
    if ($fromSettings !== '') {
        return $fromSettings;
    }

    import_scraper_llm_config();
    if (class_exists('ScraperLlmConfig')) {
        $importModel = trim(ScraperLlmConfig::readEnv('OLLAMA_IMPORT_MODEL'));
        if ($importModel !== '') {
            return $importModel;
        }
        $m = trim(ScraperLlmConfig::readEnv('OLLAMA_MODEL'));
        if ($m !== '') {
            return $m;
        }
    }

    return 'qwen2.5:7b';
}

function import_ollama_timeout_sec(): int
{
    $settings = json_decode((string) @file_get_contents(IMPORT_CONFIG . DIRECTORY_SEPARATOR . 'settings.json'), true) ?: [];
    $fromSettings = (int) ($settings['ollama_timeout_sec'] ?? 0);
    if ($fromSettings > 0) {
        return min(240, max(30, $fromSettings));
    }

    import_scraper_llm_config();
    if (class_exists('ScraperLlmConfig')) {
        $t = (int) ScraperLlmConfig::readEnv('OLLAMA_IMPORT_TIMEOUT_SEC');
        if ($t > 0) {
            return min(240, max(30, $t));
        }
        $t = (int) ScraperLlmConfig::readEnv('OLLAMA_TIMEOUT_SEC');
        if ($t > 0) {
            return min(240, max(30, $t));
        }
    }

    return 150;
}

/**
 * @return array<string, mixed>
 */
function import_ollama_chat_json(string $prompt, ?int $timeoutSec = null): array
{
    import_scraper_llm_config();
    if (!class_exists('ScraperLlmConfig') || !ScraperLlmConfig::ollamaEnabled()) {
        throw new RuntimeException('Ollama dezactivat (OLLAMA_ENABLED=0)');
    }
    if (!import_ollama_available()) {
        throw new RuntimeException('Ollama indisponibil — rulează ollama serve');
    }

    $model = import_ollama_model();
    $timeout = $timeoutSec ?? import_ollama_timeout_sec();

    $payload = json_encode([
        'model' => $model,
        'messages' => [['role' => 'user', 'content' => $prompt]],
        'stream' => false,
        'format' => 'json',
    ], JSON_UNESCAPED_UNICODE);
    if ($payload === false) {
        throw new RuntimeException('Payload JSON invalid');
    }

    $base = class_exists('ScraperLlmConfig') ? ScraperLlmConfig::ollamaBaseUrl() : 'http://127.0.0.1:11434';
    $started = hrtime(true);
    $ch = curl_init($base . '/api/chat');
    if ($ch === false) {
        throw new RuntimeException('curl_init eșuat');
    }
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => 5,
    ]);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($body === false || $code >= 400) {
        $msg = 'Ollama HTTP ' . $code . ($err !== '' ? ': ' . $err : '');
        import_ollama_log_event('import.chat_json', false, $msg, $model, $base, 0);
        throw new RuntimeException($msg);
    }

    $json = json_decode((string) $body, true);
    $content = trim((string) ($json['message']['content'] ?? ''));
    if ($content === '') {
        import_ollama_log_event('import.chat_json', false, 'Răspuns Ollama gol', $model, $base, 0);
        throw new RuntimeException('Răspuns Ollama gol');
    }

    if (preg_match('/\{[\s\S]*\}/', $content, $m)) {
        $content = $m[0];
    }
    $parsed = json_decode($content, true);
    if (!is_array($parsed)) {
        import_ollama_log_event('import.chat_json', false, 'JSON Ollama invalid', $model, $base, 0, [
            'input_excerpt' => mb_substr(trim($prompt), 0, 180),
        ]);
        throw new RuntimeException('JSON Ollama invalid: ' . mb_substr($content, 0, 200));
    }

    $latencyMs = (int) round((hrtime(true) - $started) / 1_000_000);
    import_ollama_log_event('import.chat_json', true, '', $model, $base, $latencyMs, [
        'input_excerpt' => mb_substr(trim($prompt), 0, 180),
        'output_excerpt' => mb_substr(json_encode($parsed, JSON_UNESCAPED_UNICODE) ?: $content, 0, 240),
        'task_label' => 'Import Pro · JSON',
    ]);

    return $parsed;
}

/** @param array<string, mixed> $extra */
function import_ollama_log_event(string $source, bool $ok, string $error, string $model, string $baseUrl, int $latencyMs, array $extra = []): void
{
    $helper = dirname(__DIR__, 3) . '/Backend/system/ollama_error_log.php';
    if (!is_file($helper)) {
        return;
    }
    require_once $helper;

    $root = defined('BESOIU_ROOT') ? (string) BESOIU_ROOT : dirname(__DIR__, 4);
    ollama_telemetry_record([
        'module' => 'import',
        'url' => $baseUrl,
        'model' => $model,
        'ok' => $ok,
        'latency_ms' => $latencyMs,
        'error' => $error,
    ], $root);

    $inputExcerpt = mb_substr(trim((string) ($extra['input_excerpt'] ?? '')), 0, 180);
    $outputExcerpt = mb_substr(trim((string) ($extra['output_excerpt'] ?? '')), 0, 240);
    $taskLabel = trim((string) ($extra['task_label'] ?? 'Import Pro'));
    $summary = $ok
        ? ($inputExcerpt !== ''
            ? ($outputExcerpt !== '' ? $inputExcerpt . ' → ' . mb_substr($outputExcerpt, 0, 120) : $inputExcerpt)
            : ($taskLabel . ' OK · ' . $model))
        : ('Eșec import Ollama: ' . mb_substr($error, 0, 160));

    ollama_work_log_append(array_merge([
        'source' => $source,
        'section' => 'import',
        'action' => $taskLabel !== '' ? $taskLabel : $source,
        'ok' => $ok,
        'error' => $error,
        'model' => $model,
        'url' => $baseUrl,
        'latency_ms' => $latencyMs,
        'ollama_actuated' => true,
        'at' => date('c'),
        'summary' => $summary,
        'status' => $ok ? 'ok' : 'fail',
        'meta' => [
            'path' => '/admin/importreview',
            'input_excerpt' => $inputExcerpt,
            'output_excerpt' => $outputExcerpt,
            'task_label' => $taskLabel,
        ],
    ], $extra), $root);

    if (!$ok) {
        ollama_error_log_append(array_merge([
            'source' => $source,
            'ok' => false,
            'error' => $error,
            'model' => $model,
            'url' => $baseUrl,
            'latency_ms' => $latencyMs,
        ], $extra), $root);
    }
}

function import_cron_control_path(): string
{
    return IMPORT_STATE_DIR . DIRECTORY_SEPARATOR . 'cron_control.json';
}

/** @return array<string, mixed> */
function import_scan_checkpoint_path(): string
{
    return IMPORT_STATE_DIR . DIRECTORY_SEPARATOR . 'scan_checkpoints.json';
}

/** @return array{files: array<string, array<string, mixed>>} */
function import_read_scan_checkpoints(): array
{
    $path = import_scan_checkpoint_path();
    if (!is_file($path)) {
        return ['files' => []];
    }
    $data = json_decode((string) file_get_contents($path), true);
    if (!is_array($data)) {
        return ['files' => []];
    }
    $files = $data['files'] ?? [];
    if (!is_array($files)) {
        $files = [];
    }

    return ['files' => $files];
}

/** @param array{files: array<string, array<string, mixed>>} $state */
function import_write_scan_checkpoints(array $state): bool
{
    if (!is_dir(IMPORT_STATE_DIR)) {
        mkdir(IMPORT_STATE_DIR, 0775, true);
    }

    return file_put_contents(
        import_scan_checkpoint_path(),
        json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n",
        LOCK_EX
    ) !== false;
}

function import_scan_checkpoint_key(string $supplier, string $filename): string
{
    $supplier = strtolower(trim(preg_replace('/[^a-z0-9_]+/i', '', $supplier) ?? ''));
    $filename = basename(str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $filename));

    return $supplier . '/' . $filename;
}

/** @return array<string, mixed>|null */
function import_get_scan_checkpoint(string $supplier, string $filename): ?array
{
    $key = import_scan_checkpoint_key($supplier, $filename);
    $state = import_read_scan_checkpoints();

    return is_array($state['files'][$key] ?? null) ? $state['files'][$key] : null;
}

function import_record_scan_checkpoint(
    string $supplier,
    string $filename,
    int $startOffset,
    int $nextOffset,
    int $limit,
    int $count,
    string $mode = 'continue',
    ?int $csvRowOffset = null
): array {
    $key = import_scan_checkpoint_key($supplier, $filename);
    $state = import_read_scan_checkpoints();
    $entry = is_array($state['files'][$key] ?? null) ? $state['files'][$key] : [];
    $history = is_array($entry['history'] ?? null) ? $entry['history'] : [];

    $history[] = [
        'at' => date('c'),
        'mode' => $mode,
        'start' => max(0, $startOffset),
        'end' => max(0, $nextOffset),
        'limit' => max(1, $limit),
        'count' => max(0, $count),
        'csv_row_offset' => $csvRowOffset !== null ? max(0, $csvRowOffset) : null,
    ];
    if (count($history) > 40) {
        $history = array_slice($history, -40);
    }

    $entry['offset'] = max(0, $nextOffset);
    $entry['last_start'] = max(0, $startOffset);
    $entry['last_end'] = max(0, $nextOffset);
    $entry['last_limit'] = max(1, $limit);
    $entry['last_count'] = max(0, $count);
    $entry['last_mode'] = $mode;
    if ($csvRowOffset !== null) {
        $entry['csv_row_offset'] = max(0, $csvRowOffset);
    }
    $entry['updated_at'] = date('c');
    $entry['history'] = $history;
    $entry['supplier'] = strtolower(trim($supplier));
    $entry['filename'] = basename($filename);

    $state['files'][$key] = $entry;
    import_write_scan_checkpoints($state);

    return $entry;
}

function import_reset_scan_checkpoint(string $supplier, string $filename, string $reason = 'reset'): array
{
    $key = import_scan_checkpoint_key($supplier, $filename);
    $state = import_read_scan_checkpoints();
    $prev = is_array($state['files'][$key] ?? null) ? $state['files'][$key] : null;

    $entry = [
        'offset' => 0,
        'csv_row_offset' => 0,
        'last_start' => 0,
        'last_end' => 0,
        'last_limit' => (int) ($prev['last_limit'] ?? 0),
        'last_count' => 0,
        'last_mode' => $reason,
        'updated_at' => date('c'),
        'supplier' => strtolower(trim($supplier)),
        'filename' => basename($filename),
        'history' => is_array($prev['history'] ?? null) ? $prev['history'] : [],
    ];
    $entry['history'][] = [
        'at' => date('c'),
        'mode' => $reason,
        'start' => 0,
        'end' => 0,
        'limit' => 0,
        'count' => 0,
    ];
    if (count($entry['history']) > 40) {
        $entry['history'] = array_slice($entry['history'], -40);
    }

    $state['files'][$key] = $entry;
    import_write_scan_checkpoints($state);

    return $entry;
}

function import_set_scan_checkpoint_offset(string $supplier, string $filename, int $offset, string $reason = 'manual'): array
{
    $offset = max(0, $offset);
    $key = import_scan_checkpoint_key($supplier, $filename);
    $state = import_read_scan_checkpoints();
    $entry = is_array($state['files'][$key] ?? null) ? $state['files'][$key] : [];
    $history = is_array($entry['history'] ?? null) ? $entry['history'] : [];

    $history[] = [
        'at' => date('c'),
        'mode' => $reason,
        'start' => $offset,
        'end' => $offset,
        'limit' => 0,
        'count' => 0,
    ];
    if (count($history) > 40) {
        $history = array_slice($history, -40);
    }

    $entry['offset'] = $offset;
    $entry['last_start'] = $offset;
    $entry['last_end'] = $offset;
    $entry['last_mode'] = $reason;
    $entry['updated_at'] = date('c');
    $entry['supplier'] = strtolower(trim($supplier));
    $entry['filename'] = basename($filename);
    $entry['history'] = $history;
    $state['files'][$key] = $entry;
    import_write_scan_checkpoints($state);

    return $entry;
}

function import_cron_control_defaults(): array
{
    return [
        'mode' => 'stopped',
        'batch_size' => 500,
        'display_sample' => 20,
        'updated_at' => null,
        'suppliers' => [],
        'file_offsets' => [],
    ];
}

/** @return array<string, mixed> */
function import_read_cron_control(): array
{
    $path = import_cron_control_path();
    if (!is_file($path)) {
        return import_cron_control_defaults();
    }
    $data = json_decode((string) file_get_contents($path), true);
    if (!is_array($data)) {
        return import_cron_control_defaults();
    }

    return array_merge(import_cron_control_defaults(), $data);
}

/** @param array<string, mixed> $patch */
function import_write_cron_control(array $patch): array
{
    $state = array_merge(import_read_cron_control(), $patch);
    $state['updated_at'] = date('c');
    if (!is_dir(IMPORT_STATE_DIR)) {
        mkdir(IMPORT_STATE_DIR, 0775, true);
    }
    file_put_contents(
        import_cron_control_path(),
        json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n",
        LOCK_EX
    );

    return $state;
}

function import_cron_assert_runnable(): void
{
    $mode = (string) (import_read_cron_control()['mode'] ?? 'stopped');
    if ($mode === 'stopped') {
        throw new RuntimeException('Cron oprit definitiv.');
    }
    if ($mode === 'paused') {
        throw new RuntimeException('Cron pe pauză.');
    }
}

function import_cron_supplier_due(string $supplierSlug, int $intervalMinutes): bool
{
    if ($intervalMinutes <= 0) {
        return true;
    }
    $state = import_read_cron_control();
    $suppliers = is_array($state['suppliers'] ?? null) ? $state['suppliers'] : [];
    $last = (string) ($suppliers[$supplierSlug]['last_run_at'] ?? '');
    if ($last === '') {
        return true;
    }
    $lastTs = strtotime($last);
    if ($lastTs === false) {
        return true;
    }

    return (time() - $lastTs) >= ($intervalMinutes * 60);
}

function import_cron_mark_supplier_run(string $supplierSlug): void
{
    $state = import_read_cron_control();
    $suppliers = is_array($state['suppliers'] ?? null) ? $state['suppliers'] : [];
    $suppliers[$supplierSlug] = ['last_run_at' => date('c')];
    import_write_cron_control(['suppliers' => $suppliers]);
}

function import_cron_batch_size(): int
{
    // Default 100 — loturi mari (500+) blochează staging/CLI pe volum.
    $size = (int) (import_read_cron_control()['batch_size'] ?? 100);

    return max(1, min(500, $size > 0 ? $size : 100));
}

/** Scrie intervalele scan_interval_minutes din profilul furnizorilor pentru cron Python. */
function import_motor_sync_supplier_intervals_for_cron(): void
{
    $map = [];
    foreach (import_motor_registered_suppliers() as $slug => $profile) {
        $map[strtolower((string) $slug)] = max(0, (int) ($profile['scan_interval_minutes'] ?? 0));
    }
    if (!is_dir(IMPORT_STATE_DIR)) {
        mkdir(IMPORT_STATE_DIR, 0775, true);
    }
    file_put_contents(
        IMPORT_STATE_DIR . DIRECTORY_SEPARATOR . 'supplier_intervals.json',
        json_encode($map, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n",
        LOCK_EX
    );
}
