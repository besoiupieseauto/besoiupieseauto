<?php
declare(strict_types=1);

function import_resolve_preview_mode(array $options, array $tecdocFiles, array $supplierFiles): string
{
    $explicit = strtolower(trim((string)($options['import_mode'] ?? '')));
    if (in_array($explicit, ['tecdoc_master', 'supplier_master'], true)) {
        return $explicit;
    }

    if ($tecdocFiles !== [] && $supplierFiles !== []) {
        return 'tecdoc_master';
    }

    if ($tecdocFiles !== []) {
        return 'tecdoc_master';
    }

    return 'supplier_master';
}

function import_product_group_key(array $mapped, bool $includeBrand = false): string
{
    $code = trim((string)($mapped['pCode'] ?? ''));
    $codeNorm = $code !== ''
        ? strtoupper(preg_replace('/[^A-Z0-9]/i', '', $code) ?? $code)
        : '';

    if ($codeNorm !== '') {
        if ($includeBrand) {
            $brandNorm = str_replace(' ', '', import_normalize_supplier_brand((string)($mapped['pBrand'] ?? '')));

            return $codeNorm . '|' . $brandNorm;
        }

        return $codeNorm;
    }

    return md5(trim((string)($mapped['pBrand'] ?? '')) . '|' . trim((string)($mapped['pName'] ?? '')));
}

function import_filter_tecdoc_files_by_brand(array $tecdocFiles, string $brandFilter): array
{
    $brandFilterNorm = str_replace(' ', '', import_normalize_supplier_brand($brandFilter));
    if ($brandFilterNorm === '') {
        return $tecdocFiles;
    }

    $matched = [];
    foreach ($tecdocFiles as $file) {
        $hint = import_tecdoc_file_brand_hint((string)($file['name'] ?? ''));
        if ($hint === '' || str_contains($hint, $brandFilterNorm) || str_contains($brandFilterNorm, $hint)) {
            $matched[] = $file;
        }
    }

    return $matched !== [] ? $matched : $tecdocFiles;
}

function import_product_has_supplier_price(array $product): bool
{
    $price = trim((string)($product['pPrice'] ?? ''));
    $basePrice = trim((string)($product['pBasePrice'] ?? ''));

    return ($price !== '' && (float)$price > 0) || ($basePrice !== '' && (float)$basePrice > 0);
}

function import_filter_products_require_price(array $products): array
{
    return array_values(array_filter(
        $products,
        static fn(array $product): bool => import_product_has_supplier_price($product)
    ));
}

function import_preview_products_from_tecdoc_file(
    string $path,
    string $filename,
    int $maxPreview,
    array $priceIndex = [],
    ?\Evasystem\Controllers\AdaosComercial\AdaosComercialService $markupService = null,
    bool $groupIncludeBrand = true
): array {
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
            if (($mapped['pName'] ?? '') === '' && ($mapped['pCode'] ?? '') === '') {
                continue;
            }
            $key = import_product_group_key($mapped, $groupIncludeBrand);
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
        $headers = array_map('normalize_key', import_strip_bom_from_row($headers));

        while (($values = fgetcsv($handle, 0, $delimiter)) !== false) {
            $totalRows++;
            $row = [];
            foreach ($headers as $idx => $header) {
                $row[$header] = $values[$idx] ?? '';
            }
            $mapped = map_product_row($row, '');
            if (($mapped['pName'] ?? '') === '' && ($mapped['pCode'] ?? '') === '') {
                continue;
            }
            $key = import_product_group_key($mapped, $groupIncludeBrand);
            if (count($seenGroupKeys) >= $maxPreview && !isset($seenGroupKeys[$key])) {
                $truncated = true;
                break;
            }
            $seenGroupKeys[$key] = true;
            $rowsForPreview[] = $row;
        }
        fclose($handle);
    }

    $products = import_aggregate_rows($rowsForPreview, $maxPreview, [], $markupService);
    if ($priceIndex !== []) {
        foreach ($products as $index => $product) {
            $products[$index] = import_apply_supplier_pricing($product, $priceIndex, $markupService);
        }
    }
    if (count($products) >= $maxPreview) {
        $truncated = true;
        $products = array_slice($products, 0, $maxPreview);
    }

    return ['products' => $products, 'total_rows' => $totalRows, 'truncated' => $truncated];
}

function import_preview_tecdoc_master(
    array $tecdocFiles,
    array $supplierFiles,
    int $maxPreview = 500,
    array $options = []
): array {
    $brandFilter = trim((string)($options['brand_filter'] ?? ''));
    $requireSupplierPrice = !array_key_exists('require_supplier_price', $options)
        || !empty($options['require_supplier_price']);

    if ($tecdocFiles === []) {
        return [
            'products' => [],
            'total_rows' => 0,
            'truncated' => false,
            'price_index_size' => 0,
            'tecdoc_files' => 0,
            'supplier_files' => count($supplierFiles),
            'import_mode' => 'error',
            'error' => 'no_tecdoc_files',
        ];
    }

    if ($supplierFiles === []) {
        return [
            'products' => [],
            'total_rows' => 0,
            'truncated' => false,
            'price_index_size' => 0,
            'tecdoc_files' => count($tecdocFiles),
            'supplier_files' => 0,
            'import_mode' => 'error',
            'error' => 'no_supplier_files',
        ];
    }

    $validation = import_validate_uploaded_files($supplierFiles, $tecdocFiles, []);
    if (!$validation['ok']) {
        return [
            'products' => [],
            'total_rows' => 0,
            'truncated' => false,
            'price_index_size' => 0,
            'tecdoc_files' => count($tecdocFiles),
            'supplier_files' => count($supplierFiles),
            'import_mode' => 'error',
            'error' => 'validation_failed',
            'validation_errors' => $validation['errors'],
        ];
    }

    $markupService = null;
    if (class_exists(\Evasystem\Controllers\AdaosComercial\AdaosComercialService::class)) {
        try {
            $markupService = new \Evasystem\Controllers\AdaosComercial\AdaosComercialService();
        } catch (Throwable $e) {
            $markupService = null;
        }
    }

    $priceIndex = import_build_price_index($supplierFiles);
    $tecdocFiles = import_filter_tecdoc_files_by_brand($tecdocFiles, $brandFilter);

    $products = [];
    $totalRows = 0;
    $truncated = false;
    $beforePriceFilter = 0;
    $skippedNoPrice = 0;

    foreach ($tecdocFiles as $file) {
        if (count($products) >= $maxPreview) {
            $truncated = true;
            break;
        }

        $preview = import_preview_products_from_tecdoc_file(
            (string)$file['path'],
            (string)$file['name'],
            max(1, $maxPreview - count($products)),
            $priceIndex,
            $markupService,
            true
        );

        foreach ($preview['products'] as $product) {
            if (count($products) >= $maxPreview) {
                $truncated = true;
                break 2;
            }
            $beforePriceFilter++;
            if ($requireSupplierPrice && !import_product_has_supplier_price($product)) {
                $skippedNoPrice++;
                continue;
            }
            $products[] = $product;
        }

        $totalRows += (int)$preview['total_rows'];
        if (!empty($preview['truncated'])) {
            $truncated = true;
            break;
        }
    }

    return [
        'products' => $products,
        'total_rows' => $totalRows,
        'truncated' => $truncated,
        'price_index_size' => import_price_index_size($priceIndex),
        'tecdoc_files' => count($tecdocFiles),
        'supplier_files' => count($supplierFiles),
        'import_mode' => 'tecdoc_master',
        'tecdoc_master_built' => count($products),
        'tecdoc_skipped_no_price' => $skippedNoPrice,
        'tecdoc_candidates_before_price_filter' => $beforePriceFilter,
        'file_validation' => $validation,
        'brand_filter' => $brandFilter,
        'require_supplier_price' => $requireSupplierPrice,
    ];
}

function import_preview_job_start_tecdoc_master(array $filesMeta, int $maxPreview, array $options = []): array
{
    $tecdocFiles = [];
    $supplierFiles = [];
    $missingFiles = [];
    $brandFilter = trim((string)($options['brand_filter'] ?? ''));

    foreach ($filesMeta as $fileMeta) {
        $fileId = (string)($fileMeta['file_id'] ?? '');
        $originalName = (string)($fileMeta['original_name'] ?? '');
        if ($fileId === '' || $originalName === '') {
            continue;
        }

        $path = import_temp_file_path($fileId);
        if (!is_file($path)) {
            $missingFiles[] = $originalName;
            continue;
        }

        $kind = import_resolve_upload_file_kind($path, $originalName, $fileMeta);
        $entry = ['path' => $path, 'name' => $originalName, 'file_id' => $fileId];

        if ($kind === 'tecdoc') {
            $tecdocFiles[] = $entry;
        } elseif (str_starts_with($kind, 'supplier:')) {
            $supplierFiles[] = $entry;
        }
    }

    if ($missingFiles !== []) {
        return ['ok' => false, 'error' => 'missing_files', 'missing_files' => $missingFiles];
    }

    if ($tecdocFiles === []) {
        return ['ok' => false, 'error' => 'no_tecdoc_files'];
    }

    if ($supplierFiles === []) {
        return ['ok' => false, 'error' => 'no_supplier_files'];
    }

    $validation = import_validate_uploaded_files($supplierFiles, $tecdocFiles, []);
    if (!$validation['ok']) {
        return [
            'ok' => false,
            'error' => 'validation_failed',
            'validation_errors' => $validation['errors'],
        ];
    }

    $tecdocFiles = import_filter_tecdoc_files_by_brand($tecdocFiles, $brandFilter);
    $requireSupplierPrice = !array_key_exists('require_supplier_price', $options)
        || !empty($options['require_supplier_price']);

    $jobId = import_job_create('preview', [
        'phase' => 'tecdoc_master_price_index',
        'import_mode' => 'tecdoc_master',
        'max_preview' => $maxPreview,
        'brand_filter' => $brandFilter,
        'tecdoc_files_count' => count($tecdocFiles),
        'supplier_files_count' => count($supplierFiles),
        'validation' => $validation,
    ]);

    import_job_save_state($jobId, [
        'tecdoc_files' => $tecdocFiles,
        'supplier_files' => $supplierFiles,
        'file_index' => 0,
        'products' => [],
        'total_rows' => 0,
        'skipped_no_price' => 0,
        'max_preview' => $maxPreview,
        'brand_filter' => $brandFilter,
        'require_supplier_price' => $requireSupplierPrice,
        'price_index_cache_path' => import_price_index_cache_path($supplierFiles),
        'price_index_progress' => ['supplier_index' => 0, 'file_offset' => 0],
    ]);

    import_job_update($jobId, [
        'message' => 'Construiesc indexul de prețuri din listele furnizor...',
        'progress' => 1.0,
    ]);

    return ['ok' => true, 'job_id' => $jobId];
}

function import_preview_job_step_tecdoc_master(string $jobId, array $meta, array $state): array
{
    import_job_extend_runtime(900);

    $phase = (string)($meta['phase'] ?? '');
    $maxPreview = max(1, (int)($meta['max_preview'] ?? ($state['max_preview'] ?? 500)));
    $requireSupplierPrice = !array_key_exists('require_supplier_price', $state)
        || !empty($state['require_supplier_price']);

    if ($phase === 'tecdoc_master_price_index') {
        $supplierFiles = is_array($state['supplier_files'] ?? null) ? $state['supplier_files'] : [];
        $cachePath = (string)($state['price_index_cache_path'] ?? '');
        if ($cachePath === '' && $supplierFiles !== []) {
            $cachePath = import_price_index_cache_path($supplierFiles);
            $state['price_index_cache_path'] = $cachePath;
        }

        $priceProgress = is_array($state['price_index_progress'] ?? null)
            ? $state['price_index_progress']
            : ['supplier_index' => 0, 'file_offset' => 0];

        $step = import_price_index_build_step($cachePath, $supplierFiles, $priceProgress, 28.0);
        $state['price_index_progress'] = $step['progress'];
        import_job_save_state($jobId, $state);

        $supplierIndex = (int)($step['progress']['supplier_index'] ?? 0);
        $totalSuppliers = count($supplierFiles);
        $pct = $totalSuppliers > 0
            ? min(4.9, 1.0 + (($supplierIndex / $totalSuppliers) * 3.9))
            : 4.9;

        if (!empty($step['done'])) {
            import_job_update($jobId, [
                'phase' => 'tecdoc_master_files',
                'progress' => 5.0,
                'message' => 'Index preț construit. Citesc fișierele CSV TecDoc (UTF8)...',
            ]);
        } else {
            $label = (string)($step['label'] ?? '');
            import_job_update($jobId, [
                'phase' => 'tecdoc_master_price_index',
                'progress' => $pct,
                'message' => 'Index preț furnizor'
                    . ($totalSuppliers > 0 ? ' (' . min($supplierIndex + 1, $totalSuppliers) . '/' . $totalSuppliers . ')' : '')
                    . ($label !== '' && $label !== 'cache' ? ': ' . $label : '')
                    . ' — ' . number_format((int)($step['rows'] ?? 0), 0, '.', '.') . ' rânduri în acest pas...',
            ]);
        }

        return ['ok' => true, 'status' => import_job_public_status(import_job_load_meta($jobId) ?? $meta)];
    }

    if ($phase !== 'tecdoc_master_files') {
        import_job_update($jobId, [
            'status' => 'error',
            'error' => 'Fază necunoscută: ' . $phase,
        ]);

        return ['ok' => false, 'error' => 'unknown_phase'];
    }

    $tecdocFiles = is_array($state['tecdoc_files'] ?? null) ? $state['tecdoc_files'] : [];
    $supplierFiles = is_array($state['supplier_files'] ?? null) ? $state['supplier_files'] : [];
    $fileIndex = (int)($state['file_index'] ?? 0);
    $products = is_array($state['products'] ?? null) ? $state['products'] : [];
    $totalRows = (int)($state['total_rows'] ?? 0);
    $skippedNoPrice = (int)($state['skipped_no_price'] ?? 0);
    $truncated = !empty($state['truncated']);

    if ($fileIndex >= count($tecdocFiles) || count($products) >= $maxPreview) {
        $result = import_preview_job_finalize_tecdoc_master($meta, $state, $products);
        import_job_update($jobId, [
            'status' => 'done',
            'phase' => 'done',
            'progress' => 100.0,
            'message' => 'Preview finalizat: ' . count($products) . ' produse (mod TecDoc master).',
            'result' => $result,
        ]);
        import_job_save_state($jobId, []);

        return [
            'ok' => true,
            'status' => import_job_public_status(import_job_load_meta($jobId) ?? $meta),
            'result' => $result,
        ];
    }

    $markupService = null;
    if (class_exists(\Evasystem\Controllers\AdaosComercial\AdaosComercialService::class)) {
        try {
            $markupService = new \Evasystem\Controllers\AdaosComercial\AdaosComercialService();
        } catch (Throwable $e) {
            $markupService = null;
        }
    }

    $cachePath = (string)($state['price_index_cache_path'] ?? '');
    if ($cachePath === '' && $supplierFiles !== []) {
        $cachePath = import_price_index_cache_path($supplierFiles);
    }
    $priceIndex = $cachePath !== '' ? (import_price_index_open_cached_store($cachePath) ?? []) : [];
    if ($priceIndex === [] && $supplierFiles !== []) {
        $priceIndex = import_build_price_index($supplierFiles);
    }
    $file = $tecdocFiles[$fileIndex];
    $fileName = (string)($file['name'] ?? basename((string)($file['path'] ?? '')));

    $preview = import_preview_products_from_tecdoc_file(
        (string)$file['path'],
        $fileName,
        max(1, $maxPreview - count($products)),
        $priceIndex,
        $markupService,
        true
    );

    foreach ($preview['products'] as $product) {
        if (count($products) >= $maxPreview) {
            $truncated = true;
            break;
        }
        if ($requireSupplierPrice && !import_product_has_supplier_price($product)) {
            $skippedNoPrice++;
            continue;
        }
        $products[] = $product;
    }

    $totalRows += (int)$preview['total_rows'];
    if (!empty($preview['truncated'])) {
        $truncated = true;
    }

    $fileIndex++;
    $state['file_index'] = $fileIndex;
    $state['products'] = $products;
    $state['total_rows'] = $totalRows;
    $state['skipped_no_price'] = $skippedNoPrice;
    $state['truncated'] = $truncated;
    import_job_save_state($jobId, $state);

    $progress = count($tecdocFiles) > 0
        ? 5.0 + (($fileIndex / count($tecdocFiles)) * 94.0)
        : 99.0;

    if ($fileIndex >= count($tecdocFiles) || count($products) >= $maxPreview || $truncated) {
        $result = import_preview_job_finalize_tecdoc_master($meta, $state, $products);
        import_job_update($jobId, [
            'status' => 'done',
            'phase' => 'done',
            'progress' => 100.0,
            'message' => 'Preview finalizat: ' . count($products) . ' produse (mod TecDoc master).',
            'result' => $result,
        ]);
        import_job_save_state($jobId, []);

        return [
            'ok' => true,
            'status' => import_job_public_status(import_job_load_meta($jobId) ?? $meta),
            'result' => $result,
        ];
    }

    import_job_update($jobId, [
        'progress' => min(99.0, $progress),
        'message' => 'Procesez CSV TecDoc: ' . $fileName . ' (' . $fileIndex . '/' . count($tecdocFiles) . ', '
            . count($products) . ' produse)...',
    ]);

    return [
        'ok' => true,
        'status' => import_job_public_status(import_job_load_meta($jobId) ?? $meta),
    ];
}

function import_preview_job_finalize_tecdoc_master(array $meta, array $state, array $products): array
{
    $supplierFiles = is_array($state['supplier_files'] ?? null) ? $state['supplier_files'] : [];
    $priceIndex = import_build_price_index($supplierFiles);
    $skippedNoPrice = (int)($state['skipped_no_price'] ?? 0);
    $maxPreview = max(1, (int)($meta['max_preview'] ?? ($state['max_preview'] ?? 500)));

    $msg = count($products) . ' produse din CSV TecDoc (UTF8), preț doar de la furnizori.';
    if ($skippedNoPrice > 0) {
        $msg .= ' ' . $skippedNoPrice . ' omise fără preț în listele furnizor.';
    }
    if (!empty($state['truncated'])) {
        $msg .= ' Preview limitat la ' . $maxPreview . ' produse unice.';
    }

    return [
        'products' => $products,
        'count' => count($products),
        'total_rows' => (int)($state['total_rows'] ?? 0),
        'truncated' => !empty($state['truncated']),
        'price_index_size' => import_price_index_size($priceIndex),
        'tecdoc_files' => (int)($meta['tecdoc_files_count'] ?? count($state['tecdoc_files'] ?? [])),
        'supplier_files' => (int)($meta['supplier_files_count'] ?? count($supplierFiles)),
        'import_mode' => 'tecdoc_master',
        'tecdoc_skipped_no_price' => $skippedNoPrice,
        'message' => $msg,
    ];
}
