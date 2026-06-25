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
    if ($tecdocFiles === [] || $catalogEntries === []) {
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

function import_tecdoc_build_lookup_for_search_codes(array $tecdocFiles, array $searchCodes): array
{
    if ($tecdocFiles === [] || $searchCodes === []) {
        return [];
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
        return [];
    }

    $found = [];
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
                    unset($pending[$norm]);
                }
            }
        }

        fclose($handle);
    }

    return $found;
}

function import_tecdoc_find_record_for_product(array $product, array $tecdocFiles, ?array $sharedLookup = null): ?array
{
    $searchCodes = import_oem_codes_from_product($product);
    if ($searchCodes === []) {
        return null;
    }

    if ($sharedLookup !== null) {
        foreach ($searchCodes as $searchCode) {
            $norm = import_normalize_product_code($searchCode);
            if ($norm !== '' && isset($sharedLookup[$norm])) {
                return $sharedLookup[$norm];
            }
        }

        return null;
    }

    if ($tecdocFiles === []) {
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

function import_tecdoc_matched_query_for_product(array $product, array $lookup): string
{
    foreach (import_oem_codes_from_product($product) as $searchCode) {
        $norm = import_normalize_product_code($searchCode);
        if ($norm !== '' && isset($lookup[$norm])) {
            return $searchCode;
        }
    }

    return trim((string)($product['pCode'] ?? ''));
}
