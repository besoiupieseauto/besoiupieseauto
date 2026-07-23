<?php
declare(strict_types=1);

function import_tecdoc_file_brand_hint(string $filename): string
{
    if (preg_match('/-([A-Z0-9]+)-ro\.csv$/i', $filename, $matches)) {
        return str_replace(' ', '', import_normalize_supplier_brand($matches[1]));
    }

    return '';
}

function import_tecdoc_lookup_key(string $code, string $brand = ''): string
{
    return import_normalize_product_code($code) . '|' . str_replace(' ', '', import_normalize_supplier_brand($brand));
}

function import_tecdoc_record_from_row(array $row): array
{
    $row = array_change_key_case($row, CASE_LOWER);

    return [
        'art_code_1' => trim((string)($row['art code 1'] ?? '')),
        'art_code_2' => trim((string)($row['art code 2'] ?? '')),
        'art_brand' => trim((string)($row['art brand'] ?? '')),
        'art_name' => trim((string)($row['art name'] ?? '')),
        'art_ean' => trim((string)($row['art ean'] ?? '')),
        'parts_info' => trim((string)($row['parts info'] ?? '')),
        'art_cross' => trim((string)($row['art cross'] ?? '')),
        'ttc_art_id' => trim((string)($row['ttc art id'] ?? '')),
        'car_brand' => trim((string)($row['car brand'] ?? '')),
        'car_model' => trim((string)($row['car model'] ?? '')),
        'car_typ' => trim((string)($row['car typ'] ?? '')),
        'car_of_year' => trim((string)($row['car of year'] ?? '')),
        'car_to_year' => trim((string)($row['car to year'] ?? '')),
        'car_kw' => trim((string)($row['car kw'] ?? '')),
    ];
}

function import_tecdoc_record_to_raw_row(array $record): array
{
    return array_change_key_case([
        'art code 1' => (string)($record['art_code_1'] ?? ''),
        'art code 2' => (string)($record['art_code_2'] ?? ''),
        'art brand' => (string)($record['art_brand'] ?? ''),
        'art name' => (string)($record['art_name'] ?? ''),
        'art ean' => (string)($record['art_ean'] ?? ''),
        'parts info' => (string)($record['parts_info'] ?? ''),
        'art cross' => (string)($record['art_cross'] ?? ''),
        'ttc art id' => (string)($record['ttc_art_id'] ?? ''),
        'car brand' => (string)($record['car_brand'] ?? ''),
        'car model' => (string)($record['car_model'] ?? ''),
        'car typ' => (string)($record['car_typ'] ?? ''),
        'car of year' => (string)($record['car_of_year'] ?? ''),
        'car to year' => (string)($record['car_to_year'] ?? ''),
        'car kw' => (string)($record['car_kw'] ?? ''),
    ], CASE_LOWER);
}

function import_tecdoc_db_lookup_enabled(): bool
{
    $env = $_ENV['TECDOC_IMPORT_DB_LOOKUP'] ?? getenv('TECDOC_IMPORT_DB_LOOKUP');
    if ($env !== false && $env !== null && trim((string)$env) !== '') {
        return filter_var($env, FILTER_VALIDATE_BOOLEAN);
    }

    $configPath = dirname(__DIR__, 3) . '/config/besoiupieseimport_sync.php';
    $config = is_file($configPath) ? require $configPath : [];

    return is_array($config) && !empty($config['tecdoc_import_db_lookup']);
}

function import_tecdoc_db_bootstrap(): bool
{
    if (!import_tecdoc_db_lookup_enabled()) {
        return false;
    }

    if (!function_exists('besoiupieseimport_tecdoc_pdo')) {
        $lib = dirname(__DIR__, 3) . '/tools/besoiupieseimport_tecdoc_db.php';
        if (is_file($lib)) {
            require_once $lib;
        }
    }

    return function_exists('besoiupieseimport_tecdoc_pdo')
        && function_exists('besoiupieseimport_tecdoc_table_exists');
}

/**
 * Returnează null numai când DB nu poate fi folosit, pentru a permite fallback CSV.
 *
 * @return array{lookup:array<string,array<string,mixed>>,row_groups:array<string,list<array<string,mixed>>>}|null
 */
function import_tecdoc_build_catalog_bundle_from_db(
    array $catalogEntries,
    int $maxRowsPerCode = 40,
    bool $collectRows = true
): ?array {
    if (!import_tecdoc_db_bootstrap()) {
        return null;
    }

    try {
        $pdo = besoiupieseimport_tecdoc_pdo();
        foreach (['tecdoc_product_codes', 'tecdoc_products', 'tecdoc_brands'] as $table) {
            if (!besoiupieseimport_tecdoc_table_exists($pdo, $table)) {
                return null;
            }
        }
        if (
            function_exists('besoiupieseimport_tecdoc_table_has_rows')
            && (
                !besoiupieseimport_tecdoc_table_has_rows($pdo, 'tecdoc_product_codes')
                || !besoiupieseimport_tecdoc_table_has_rows($pdo, 'tecdoc_products')
            )
        ) {
            return null;
        }
    } catch (Throwable) {
        return null;
    }

    $pending = [];
    $groupsByCode = [];
    $rowGroups = [];
    foreach ($catalogEntries as $entry) {
        $groupCode = import_normalize_product_code((string)($entry['code'] ?? ''));
        if ($groupCode !== '') {
            $rowGroups[$groupCode] = [];
        }
        foreach (import_tecdoc_pending_keys_for_entry($entry) as $key) {
            $pending[$key] = true;
            $codeNorm = (string)strtok($key, '|');
            if ($codeNorm !== '' && $groupCode !== '') {
                $groupsByCode[$codeNorm][$groupCode] = true;
            }
        }
    }

    if ($pending === []) {
        return ['lookup' => [], 'row_groups' => $rowGroups];
    }

    $codes = array_values(array_unique(array_map(
        static fn(string $key): string => (string)strtok($key, '|'),
        array_keys($pending)
    )));
    $lookup = [];
    $products = [];
    $productGroups = [];

    try {
        foreach (array_chunk($codes, 400) as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));
            $sql = 'SELECT c.code_norm AS matched_code_norm, c.product_id,
                           p.art_code_1, p.art_code_2, p.art_name, p.art_ean,
                           p.parts_info, p.art_cross, p.ttc_art_id, b.name AS art_brand
                    FROM tecdoc_product_codes c
                    INNER JOIN tecdoc_products p ON p.id = c.product_id
                    INNER JOIN tecdoc_brands b ON b.id = p.brand_id
                    WHERE c.code_norm IN (' . $placeholders . ')
                    ORDER BY c.code_type ASC, c.product_id ASC';
            $stmt = function_exists('besoiupieseimport_tecdoc_prepare_with_timeout')
                ? besoiupieseimport_tecdoc_prepare_with_timeout($pdo, $sql, 5000)
                : $pdo->prepare($sql);
            $stmt->execute($chunk);

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $record = import_tecdoc_record_from_row([
                    'art code 1' => $row['art_code_1'] ?? '',
                    'art code 2' => $row['art_code_2'] ?? '',
                    'art brand' => $row['art_brand'] ?? '',
                    'art name' => $row['art_name'] ?? '',
                    'art ean' => $row['art_ean'] ?? '',
                    'parts info' => $row['parts_info'] ?? '',
                    'art cross' => $row['art_cross'] ?? '',
                    'ttc art id' => $row['ttc_art_id'] ?? '',
                ]);
                $productId = (int)($row['product_id'] ?? 0);
                $matchedCode = import_normalize_product_code((string)($row['matched_code_norm'] ?? ''));
                $brand = str_replace(' ', '', import_normalize_supplier_brand((string)$record['art_brand']));

                foreach ([
                    import_tecdoc_lookup_key($matchedCode, $brand),
                    import_tecdoc_lookup_key($matchedCode, ''),
                ] as $key) {
                    if (isset($pending[$key]) && !isset($lookup[$key])) {
                        $lookup[$key] = $record;
                    }
                }
                foreach (import_tecdoc_row_match_keys($record) as $key) {
                    if (isset($pending[$key]) && !isset($lookup[$key])) {
                        $lookup[$key] = $record;
                    }
                }

                if ($productId > 0) {
                    $products[$productId] = $record;
                    foreach (array_keys($groupsByCode[$matchedCode] ?? []) as $groupCode) {
                        $productGroups[$productId][$groupCode] = true;
                    }
                }
            }
        }

        if (!$collectRows || $maxRowsPerCode <= 0 || $products === []) {
            return ['lookup' => $lookup, 'row_groups' => $rowGroups];
        }
        if (!besoiupieseimport_tecdoc_table_exists($pdo, 'tecdoc_product_compatibilities')
            && !besoiupieseimport_tecdoc_table_exists($pdo, 'tecdoc_product_compatibilities_new')) {
            return ['lookup' => $lookup, 'row_groups' => $rowGroups];
        }

        $compatTable = function_exists('besoiupieseimport_tecdoc_active_compat_table')
            ? besoiupieseimport_tecdoc_active_compat_table($pdo)
            : 'tecdoc_product_compatibilities';

        foreach (array_chunk(array_keys($products), 400) as $productIds) {
            $placeholders = implode(',', array_fill(0, count($productIds), '?'));
            $sql = 'SELECT product_id, car_brand, car_model, car_typ,
                           car_of_year, car_to_year, car_kw
                    FROM `' . str_replace('`', '``', $compatTable) . '`
                    WHERE product_id IN (' . $placeholders . ')
                    ORDER BY product_id ASC, id ASC';
            $stmt = function_exists('besoiupieseimport_tecdoc_prepare_with_timeout')
                ? besoiupieseimport_tecdoc_prepare_with_timeout($pdo, $sql, 5000)
                : $pdo->prepare($sql);
            $stmt->execute($productIds);

            while ($compat = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $productId = (int)($compat['product_id'] ?? 0);
                if (!isset($products[$productId])) {
                    continue;
                }
                $record = array_merge($products[$productId], [
                    'car_brand' => trim((string)($compat['car_brand'] ?? '')),
                    'car_model' => trim((string)($compat['car_model'] ?? '')),
                    'car_typ' => trim((string)($compat['car_typ'] ?? '')),
                    'car_of_year' => trim((string)($compat['car_of_year'] ?? '')),
                    'car_to_year' => trim((string)($compat['car_to_year'] ?? '')),
                    'car_kw' => trim((string)($compat['car_kw'] ?? '')),
                ]);
                foreach (array_keys($productGroups[$productId] ?? []) as $groupCode) {
                    if (count($rowGroups[$groupCode] ?? []) < $maxRowsPerCode) {
                        $rowGroups[$groupCode][] = import_tecdoc_record_to_raw_row($record);
                    }
                }
            }
        }

        return ['lookup' => $lookup, 'row_groups' => $rowGroups];
    } catch (Throwable) {
        return null;
    }
}

function import_tecdoc_row_groups_satisfied(array $rowGroups, int $maxRowsPerCode): bool
{
    if ($rowGroups === [] || $maxRowsPerCode <= 0) {
        return true;
    }

    foreach ($rowGroups as $rows) {
        if (count($rows) < $maxRowsPerCode) {
            return false;
        }
    }

    return true;
}

function import_tecdoc_filter_files_for_catalog(array $tecdocFiles, array $catalogEntries, bool $enabled = true): array
{
    if (!$enabled || $tecdocFiles === [] || $catalogEntries === []) {
        return $tecdocFiles;
    }

    $brands = [];
    foreach ($catalogEntries as $entry) {
        $brand = str_replace(' ', '', import_normalize_supplier_brand((string)($entry['brand'] ?? '')));
        if ($brand !== '') {
            $brands[$brand] = true;
        }
    }

    if ($brands === []) {
        return $tecdocFiles;
    }

    $matched = [];
    $generic = [];
    foreach ($tecdocFiles as $file) {
        $hint = import_tecdoc_file_brand_hint((string)($file['name'] ?? ''));
        if ($hint === '') {
            $generic[] = $file;
            continue;
        }
        if (isset($brands[$hint])) {
            $matched[] = $file;
        }
    }

    if ($matched === []) {
        return $tecdocFiles;
    }

    return array_merge($matched, $generic);
}

function import_tecdoc_order_files_for_catalog(array $tecdocFiles, array $catalogEntries): array
{
    if ($tecdocFiles === []) {
        return [];
    }

    $brandPriority = [];
    foreach ($catalogEntries as $entry) {
        $brand = str_replace(' ', '', import_normalize_supplier_brand((string)($entry['brand'] ?? '')));
        if ($brand !== '') {
            $brandPriority[$brand] = ($brandPriority[$brand] ?? 0) + 1;
        }
    }

    if ($brandPriority === []) {
        return import_tecdoc_order_files($tecdocFiles);
    }

    arsort($brandPriority);
    $priorityHints = array_keys($brandPriority);

    $byHint = [];
    $generic = [];
    foreach ($tecdocFiles as $file) {
        $hint = import_tecdoc_file_brand_hint((string)($file['name'] ?? ''));
        if ($hint === '') {
            $generic[] = $file;
            continue;
        }
        $byHint[$hint][] = $file;
    }

    $ordered = [];
    foreach ($priorityHints as $hint) {
        if (!isset($byHint[$hint])) {
            continue;
        }
        foreach ($byHint[$hint] as $file) {
            $ordered[] = $file;
        }
        unset($byHint[$hint]);
    }

    foreach ($byHint as $group) {
        foreach ($group as $file) {
            $ordered[] = $file;
        }
    }

    return array_merge($ordered, $generic);
}

function import_tecdoc_build_catalog_bundle(
    array $tecdocFiles,
    array $catalogEntries,
    int $maxRowsPerCode = 40,
    bool $collectRows = true,
    bool $filterFilesByBrand = true
): array {
    if ($catalogEntries === []) {
        return ['lookup' => [], 'row_groups' => []];
    }

    $dbBundle = import_tecdoc_build_catalog_bundle_from_db(
        $catalogEntries,
        $maxRowsPerCode,
        $collectRows
    );
    if ($dbBundle !== null) {
        return $dbBundle;
    }

    if ($tecdocFiles === []) {
        return ['lookup' => [], 'row_groups' => []];
    }

    $collectRows = $collectRows && $maxRowsPerCode > 0;

    $pending = [];
    foreach ($catalogEntries as $entry) {
        foreach (import_tecdoc_pending_keys_for_entry($entry) as $key) {
            $pending[$key] = true;
        }
    }

    $rowGroups = [];
    foreach ($catalogEntries as $entry) {
        $codeNorm = import_normalize_product_code((string)($entry['code'] ?? ''));
        if ($codeNorm !== '') {
            $rowGroups[$codeNorm] = [];
        }
    }

    if ($pending === [] && $rowGroups === []) {
        return ['lookup' => [], 'row_groups' => []];
    }

    $lookup = [];
    $files = import_tecdoc_order_files_for_catalog(
        import_tecdoc_filter_files_for_catalog($tecdocFiles, $catalogEntries, $filterFilesByBrand),
        $catalogEntries
    );

    foreach ($files as $file) {
        if ($pending === [] && (!$collectRows || import_tecdoc_row_groups_satisfied($rowGroups, $maxRowsPerCode))) {
            break;
        }

        $path = (string)($file['path'] ?? '');
        if ($path === '' || !is_file($path)) {
            continue;
        }

        $sample = file_get_contents($path, false, null, 0, 4096) ?: '';
        $delimiter = detect_delimiter($sample);
        $handle = fopen($path, 'r');
        if (!$handle) {
            continue;
        }

        $headers = fgetcsv($handle, 0, $delimiter);
        if (!is_array($headers)) {
            fclose($handle);
            continue;
        }
        $headers = array_map('normalize_key', import_strip_bom_from_row($headers));

        while (($values = fgetcsv($handle, 0, $delimiter)) !== false) {
            if ($pending === [] && (!$collectRows || import_tecdoc_row_groups_satisfied($rowGroups, $maxRowsPerCode))) {
                break;
            }

            $row = [];
            foreach ($headers as $idx => $header) {
                $row[$header] = $values[$idx] ?? '';
            }

            $record = import_tecdoc_record_from_row($row);

            if ($pending !== []) {
                foreach (import_tecdoc_row_match_keys($record) as $key) {
                    if (!isset($pending[$key]) || isset($lookup[$key])) {
                        continue;
                    }
                    $lookup[$key] = $record;
                    unset($pending[$key]);
                }
            }

            if ($collectRows) {
                $codeNorm = import_normalize_product_code((string)$record['art_code_1']);
                if ($codeNorm !== ''
                    && array_key_exists($codeNorm, $rowGroups)
                    && count($rowGroups[$codeNorm]) < $maxRowsPerCode
                ) {
                    $rowGroups[$codeNorm][] = array_change_key_case($row, CASE_LOWER);
                }
            }
        }

        fclose($handle);
    }

    return ['lookup' => $lookup, 'row_groups' => $rowGroups];
}

function import_tecdoc_create_scan_state(
    array $tecdocFiles,
    array $catalogEntries,
    int $maxRowsPerCode = 30,
    bool $filterFilesByBrand = true
): array {
    $collectRows = $maxRowsPerCode > 0;

    $pending = [];
    foreach ($catalogEntries as $entry) {
        foreach (import_tecdoc_pending_keys_for_entry($entry) as $key) {
            $pending[$key] = true;
        }
    }

    $rowGroups = [];
    foreach ($catalogEntries as $entry) {
        $codeNorm = import_normalize_product_code((string)($entry['code'] ?? ''));
        if ($codeNorm !== '') {
            $rowGroups[$codeNorm] = [];
        }
    }

    $orderedFiles = import_tecdoc_order_files_for_catalog(
        import_tecdoc_filter_files_for_catalog($tecdocFiles, $catalogEntries, $filterFilesByBrand),
        $catalogEntries
    );

    $bytesTotal = 0;
    foreach ($orderedFiles as $file) {
        $path = (string)($file['path'] ?? '');
        if ($path !== '' && is_file($path)) {
            $bytesTotal += (int)(filesize($path) ?: 0);
        }
    }

    return [
        'files' => array_values(array_map(static function (array $file): array {
            return [
                'path' => (string)($file['path'] ?? ''),
                'name' => (string)($file['name'] ?? ''),
            ];
        }, $orderedFiles)),
        'file_index' => 0,
        'offset' => 0,
        'headers' => [],
        'delimiter' => ';',
        'pending' => $pending,
        'lookup' => [],
        'row_groups' => $rowGroups,
        'max_rows_per_code' => max(0, $maxRowsPerCode),
        'collect_rows' => $collectRows,
        'bytes_total' => max(1, $bytesTotal),
        'bytes_read' => 0,
        'rows_processed' => 0,
        'done' => false,
    ];
}

function import_tecdoc_scan_is_complete(array $state): bool
{
    if (!empty($state['done'])) {
        return true;
    }

    $pending = is_array($state['pending'] ?? null) ? $state['pending'] : [];
    $collectRows = !empty($state['collect_rows']);
    $maxRows = (int)($state['max_rows_per_code'] ?? 0);
    $rowGroups = is_array($state['row_groups'] ?? null) ? $state['row_groups'] : [];
    $files = is_array($state['files'] ?? null) ? $state['files'] : [];
    $fileIndex = (int)($state['file_index'] ?? 0);

    if ($pending === [] && (!$collectRows || import_tecdoc_row_groups_satisfied($rowGroups, $maxRows))) {
        return true;
    }

    return $fileIndex >= count($files) && (int)($state['offset'] ?? 0) === 0;
}

function import_tecdoc_scan_progress_percent(array $state): float
{
    $bytesTotal = max(1, (int)($state['bytes_total'] ?? 1));
    $bytesRead = (int)($state['bytes_read'] ?? 0);
    $pct = ($bytesRead / $bytesTotal) * 100.0;

    if (import_tecdoc_scan_is_complete($state)) {
        return 100.0;
    }

    return min(99.0, max(0.0, $pct));
}

function import_tecdoc_scan_current_file_label(array $state): string
{
    $files = is_array($state['files'] ?? null) ? $state['files'] : [];
    $fileIndex = (int)($state['file_index'] ?? 0);
    if (!isset($files[$fileIndex])) {
        return 'finalizare';
    }

    return (string)($files[$fileIndex]['name'] ?? ('fișier ' . ($fileIndex + 1)));
}

function import_tecdoc_lookup_from_scan_state(array $state): array
{
    $lookup = is_array($state['lookup'] ?? null) ? $state['lookup'] : [];
    $normalized = [];
    foreach ($lookup as $record) {
        if (!is_array($record)) {
            continue;
        }
        $norm = import_normalize_product_code((string)($record['art_code_1'] ?? ''));
        if ($norm !== '' && !isset($normalized[$norm])) {
            $normalized[$norm] = $record;
        }
    }

    return $normalized;
}

function import_tecdoc_scan_step(array &$state, float $maxSeconds = 7.0, int $maxRowsPerStep = 20000): array
{
    if (import_tecdoc_scan_is_complete($state)) {
        $state['done'] = true;

        return ['done' => true, 'rows' => 0];
    }

    $start = microtime(true);
    $rowsThisStep = 0;
    $files = is_array($state['files'] ?? null) ? $state['files'] : [];
    $fileIndex = (int)($state['file_index'] ?? 0);
    $offset = (int)($state['offset'] ?? 0);
    $pending = is_array($state['pending'] ?? null) ? $state['pending'] : [];
    $lookup = is_array($state['lookup'] ?? null) ? $state['lookup'] : [];
    $rowGroups = is_array($state['row_groups'] ?? null) ? $state['row_groups'] : [];
    $collectRows = !empty($state['collect_rows']);
    $maxRowsPerCode = (int)($state['max_rows_per_code'] ?? 0);
    $headers = is_array($state['headers'] ?? null) ? $state['headers'] : [];
    $delimiter = (string)($state['delimiter'] ?? ';');

    while ($fileIndex < count($files)) {
        if ($pending === [] && (!$collectRows || import_tecdoc_row_groups_satisfied($rowGroups, $maxRowsPerCode))) {
            break;
        }

        if (microtime(true) - $start >= $maxSeconds || $rowsThisStep >= $maxRowsPerStep) {
            break;
        }

        $path = (string)($files[$fileIndex]['path'] ?? '');
        if ($path === '' || !is_file($path)) {
            $fileIndex++;
            $offset = 0;
            $headers = [];
            continue;
        }

        $handle = fopen($path, 'r');
        if (!$handle) {
            $fileIndex++;
            $offset = 0;
            $headers = [];
            continue;
        }

        if ($offset > 0) {
            fseek($handle, $offset);
        } else {
            $sample = file_get_contents($path, false, null, 0, 4096) ?: '';
            $delimiter = detect_delimiter($sample);
            $headers = fgetcsv($handle, 0, $delimiter);
            if (!is_array($headers)) {
                fclose($handle);
                $fileIndex++;
                $offset = 0;
                $headers = [];
                continue;
            }
            $headers = array_map('normalize_key', import_strip_bom_from_row($headers));
            $offset = (int)ftell($handle);
        }

        while (($values = fgetcsv($handle, 0, $delimiter)) !== false) {
            if ($pending === [] && (!$collectRows || import_tecdoc_row_groups_satisfied($rowGroups, $maxRowsPerCode))) {
                break;
            }
            if (microtime(true) - $start >= $maxSeconds || $rowsThisStep >= $maxRowsPerStep) {
                $offset = (int)ftell($handle);
                fclose($handle);
                break 2;
            }

            $row = [];
            foreach ($headers as $idx => $header) {
                $row[$header] = $values[$idx] ?? '';
            }

            $record = import_tecdoc_record_from_row($row);

            if ($pending !== []) {
                foreach (import_tecdoc_row_match_keys($record) as $key) {
                    if (!isset($pending[$key]) || isset($lookup[$key])) {
                        continue;
                    }
                    $lookup[$key] = $record;
                    unset($pending[$key]);
                }
            }

            if ($collectRows && $maxRowsPerCode > 0) {
                $codeNorm = import_normalize_product_code((string)$record['art_code_1']);
                if ($codeNorm !== ''
                    && array_key_exists($codeNorm, $rowGroups)
                    && count($rowGroups[$codeNorm]) < $maxRowsPerCode
                ) {
                    $rowGroups[$codeNorm][] = array_change_key_case($row, CASE_LOWER);
                }
            }

            $rowsThisStep++;
            $state['rows_processed'] = (int)($state['rows_processed'] ?? 0) + 1;
        }

        if (feof($handle)) {
            $state['bytes_read'] = (int)($state['bytes_read'] ?? 0) + (int)(filesize($path) ?: 0);
            fclose($handle);
            $fileIndex++;
            $offset = 0;
            $headers = [];
            continue;
        }

        if (is_resource($handle)) {
            fclose($handle);
        }
        break;
    }

    $state['file_index'] = $fileIndex;
    $state['offset'] = $offset;
    $state['headers'] = $headers;
    $state['delimiter'] = $delimiter;
    $state['pending'] = $pending;
    $state['lookup'] = $lookup;
    $state['row_groups'] = $rowGroups;
    $state['done'] = import_tecdoc_scan_is_complete($state);

    return [
        'done' => !empty($state['done']),
        'rows' => $rowsThisStep,
    ];
}

function import_tecdoc_collect_rows_for_codes(array $tecdocFiles, array $searchCodes, int $maxRowsPerCode = 250): array
{
    if ($tecdocFiles === [] || $searchCodes === []) {
        return [];
    }

    $entries = [];
    foreach ($searchCodes as $searchCode) {
        $entries[] = ['code' => (string)$searchCode, 'brand' => ''];
    }

    return import_tecdoc_build_catalog_bundle(
        $tecdocFiles,
        $entries,
        $maxRowsPerCode,
        true,
        false
    )['row_groups'];
}

function import_tecdoc_pending_keys_for_entry(array $entry): array
{
    $code = import_normalize_product_code((string)($entry['code'] ?? ''));
    $brand = str_replace(' ', '', import_normalize_supplier_brand((string)($entry['brand'] ?? '')));
    if ($code === '') {
        return [];
    }

    $keys = [
        import_tecdoc_lookup_key($code, $brand),
        import_tecdoc_lookup_key($code, ''),
    ];

    if ($brand !== '') {
        $shortBrand = substr($brand, 0, 3);
        if ($shortBrand !== '' && str_starts_with($code, $shortBrand)) {
            $keys[] = import_tecdoc_lookup_key(substr($code, strlen($shortBrand)), $brand);
            $keys[] = import_tecdoc_lookup_key(substr($code, strlen($shortBrand)), '');
        }
        if ($shortBrand !== '' && str_ends_with($code, $shortBrand)) {
            $keys[] = import_tecdoc_lookup_key(substr($code, 0, -strlen($shortBrand)), $brand);
            $keys[] = import_tecdoc_lookup_key(substr($code, 0, -strlen($shortBrand)), '');
        }
    }

    return array_values(array_unique($keys));
}

function import_tecdoc_row_match_keys(array $record): array
{
    $brand = str_replace(' ', '', import_normalize_supplier_brand((string)($record['art_brand'] ?? '')));
    $codes = [];
    foreach (['art_code_1', 'art_code_2'] as $field) {
        $code = import_normalize_product_code((string)($record[$field] ?? ''));
        if ($code === '') {
            continue;
        }
        $codes[] = import_tecdoc_lookup_key($code, $brand);
        $codes[] = import_tecdoc_lookup_key($code, '');
    }

    return array_values(array_unique($codes));
}

function import_tecdoc_build_lookup_for_catalog(array $tecdocFiles, array $catalogEntries): array
{
    return import_tecdoc_build_catalog_bundle(
        $tecdocFiles,
        $catalogEntries,
        0,
        false,
        true
    )['lookup'];
}

function import_tecdoc_lookup_catalog_entry(array $lookup, array $entry): ?array
{
    if ($lookup === []) {
        return null;
    }

    $code = import_normalize_product_code((string)($entry['code'] ?? ''));
    $brand = str_replace(' ', '', import_normalize_supplier_brand((string)($entry['brand'] ?? '')));
    if ($code === '') {
        return null;
    }

    $brandKey = import_tecdoc_lookup_key($code, $brand);
    if (isset($lookup[$brandKey])) {
        return $lookup[$brandKey];
    }

    $codeKey = import_tecdoc_lookup_key($code, '');
    if (isset($lookup[$codeKey])) {
        return $lookup[$codeKey];
    }

    foreach (import_tecdoc_pending_keys_for_entry($entry) as $key) {
        if (isset($lookup[$key])) {
            return $lookup[$key];
        }
    }

    return null;
}

function import_tecdoc_cross_to_oem_list(string $artCross): array
{
    $processed = import_base_process_oem_codes($artCross);
    if ($processed === '') {
        return [];
    }

    $items = [];
    foreach (preg_split('/\r?\n/u', $processed) ?: [] as $line) {
        $line = trim($line);
        if ($line !== '') {
            $items[] = $line;
        }
    }

    return $items;
}

function import_tecdoc_record_matches_search_code(array $record, string $searchCode): bool
{
    $norm = import_normalize_product_code($searchCode);
    if ($norm === '') {
        return false;
    }

    foreach (['art_code_1', 'art_code_2'] as $field) {
        if (import_normalize_product_code((string)($record[$field] ?? '')) === $norm) {
            return true;
        }
    }

    foreach (import_extract_codes_from_text((string)($record['art_cross'] ?? '')) as $crossCode) {
        if (import_normalize_product_code($crossCode) === $norm) {
            return true;
        }
    }

    return false;
}

function import_tecdoc_order_files(array $tecdocFiles): array
{
    $filesByBrand = [];
    $genericFiles = [];
    foreach ($tecdocFiles as $file) {
        $hint = import_tecdoc_file_brand_hint((string)($file['name'] ?? ''));
        if ($hint !== '') {
            $filesByBrand[$hint][] = $file;
        } else {
            $genericFiles[] = $file;
        }
    }

    $ordered = [];
    foreach ($filesByBrand as $group) {
        foreach ($group as $file) {
            $ordered[] = $file;
        }
    }
    foreach ($genericFiles as $file) {
        $ordered[] = $file;
    }

    return $ordered !== [] ? $ordered : $tecdocFiles;
}

function import_tecdoc_normalize_lookup_bundle(?array $sharedLookup): array
{
    if ($sharedLookup === null || $sharedLookup === []) {
        return ['lookup' => [], 'row_groups' => []];
    }

    if (isset($sharedLookup['lookup']) && is_array($sharedLookup['lookup'])) {
        return [
            'lookup' => $sharedLookup['lookup'],
            'row_groups' => is_array($sharedLookup['row_groups'] ?? null) ? $sharedLookup['row_groups'] : [],
        ];
    }

    return ['lookup' => $sharedLookup, 'row_groups' => []];
}

function import_tecdoc_build_lookup_bundle_for_search_codes(array $tecdocFiles, array $searchCodes): array
{
    if ($searchCodes === []) {
        return ['lookup' => [], 'row_groups' => []];
    }

    $dbEntries = array_map(
        static fn($code): array => ['code' => (string)$code, 'brand' => ''],
        $searchCodes
    );
    $dbBundle = import_tecdoc_build_catalog_bundle_from_db($dbEntries, 40, true);
    if ($dbBundle !== null) {
        $found = [];
        foreach ($dbEntries as $entry) {
            $record = import_tecdoc_lookup_catalog_entry($dbBundle['lookup'], $entry);
            $norm = import_normalize_product_code((string)$entry['code']);
            if ($norm !== '' && $record !== null) {
                $found[$norm] = $record;
            }
        }

        return [
            'lookup' => $found,
            'row_groups' => is_array($dbBundle['row_groups'] ?? null) ? $dbBundle['row_groups'] : [],
        ];
    }

    if ($tecdocFiles === []) {
        return ['lookup' => [], 'row_groups' => []];
    }

    $pending = [];
    foreach ($searchCodes as $searchCode) {
        $searchCode = trim((string)$searchCode);
        $norm = import_normalize_product_code($searchCode);
        if ($norm === '' || isset($pending[$norm])) {
            continue;
        }
        $pending[$norm] = $searchCode;
    }

    if ($pending === []) {
        return ['lookup' => [], 'row_groups' => []];
    }

    $found = [];
    $rowGroups = [];
    foreach (import_tecdoc_order_files($tecdocFiles) as $file) {
        if ($pending === []) {
            break;
        }

        $path = (string)($file['path'] ?? '');
        if ($path === '' || !is_file($path)) {
            continue;
        }

        $sample = file_get_contents($path, false, null, 0, 4096) ?: '';
        $delimiter = detect_delimiter($sample);
        $handle = fopen($path, 'r');
        if (!$handle) {
            continue;
        }

        $headers = fgetcsv($handle, 0, $delimiter);
        if (!is_array($headers)) {
            fclose($handle);
            continue;
        }
        $headers = array_map('normalize_key', import_strip_bom_from_row($headers));

        while (($values = fgetcsv($handle, 0, $delimiter)) !== false) {
            if ($pending === []) {
                break;
            }

            $row = [];
            foreach ($headers as $idx => $header) {
                $row[$header] = $values[$idx] ?? '';
            }

            $record = import_tecdoc_record_from_row($row);
            foreach ($pending as $norm => $originalCode) {
                if (isset($found[$norm])) {
                    continue;
                }
                if (import_tecdoc_record_matches_search_code($record, $originalCode)) {
                    $found[$norm] = $record;
                    $rowGroups[$norm][] = import_tecdoc_record_to_raw_row($record);
                    unset($pending[$norm]);
                }
            }
        }

        fclose($handle);
    }

    return ['lookup' => $found, 'row_groups' => $rowGroups];
}

function import_tecdoc_build_lookup_for_search_codes(array $tecdocFiles, array $searchCodes): array
{
    return import_tecdoc_build_lookup_bundle_for_search_codes($tecdocFiles, $searchCodes)['lookup'];
}

function import_tecdoc_find_record_for_product(array $product, array $tecdocFiles, ?array $sharedLookup = null): ?array
{
    $searchCodes = import_oem_codes_from_product($product);
    if ($searchCodes === []) {
        return null;
    }

    if ($sharedLookup !== null) {
        $bundle = import_tecdoc_normalize_lookup_bundle($sharedLookup);
        foreach ($searchCodes as $searchCode) {
            $norm = import_normalize_product_code($searchCode);
            if ($norm !== '' && isset($bundle['lookup'][$norm])) {
                return $bundle['lookup'][$norm];
            }
        }

        return null;
    }

    if ($tecdocFiles === []) {
        $bundle = import_tecdoc_build_lookup_bundle_for_search_codes([], $searchCodes);
        foreach ($searchCodes as $searchCode) {
            $norm = import_normalize_product_code($searchCode);
            if ($norm !== '' && isset($bundle['lookup'][$norm])) {
                return $bundle['lookup'][$norm];
            }
        }

        return null;
    }

    $lookup = import_tecdoc_build_lookup_for_search_codes($tecdocFiles, $searchCodes);
    foreach ($searchCodes as $searchCode) {
        $norm = import_normalize_product_code($searchCode);
        if ($norm !== '' && isset($lookup[$norm])) {
            return $lookup[$norm];
        }
    }

    return null;
}

function import_tecdoc_matched_query_for_product(array $product, array $lookupOrBundle): string
{
    $bundle = import_tecdoc_normalize_lookup_bundle($lookupOrBundle);
    foreach (import_oem_codes_from_product($product) as $searchCode) {
        $norm = import_normalize_product_code($searchCode);
        if ($norm !== '' && isset($bundle['lookup'][$norm])) {
            return $searchCode;
        }
    }

    return trim((string)($product['pCode'] ?? ''));
}

function import_tecdoc_row_groups_for_product(array $product, array $lookupOrBundle): array
{
    $bundle = import_tecdoc_normalize_lookup_bundle($lookupOrBundle);
    foreach (import_oem_codes_from_product($product) as $searchCode) {
        $norm = import_normalize_product_code($searchCode);
        if ($norm !== '' && !empty($bundle['row_groups'][$norm]) && is_array($bundle['row_groups'][$norm])) {
            return $bundle['row_groups'][$norm];
        }
    }

    return [];
}
