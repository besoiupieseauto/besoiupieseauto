<?php
declare(strict_types=1);

function import_jobs_dir(): string
{
    $dir = import_temp_dir() . '/jobs';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    return $dir;
}

function import_job_sanitize_id(string $jobId): string
{
    return preg_replace('/[^A-Za-z0-9_-]/', '', $jobId) ?: '';
}

function import_job_meta_path(string $jobId): string
{
    return import_jobs_dir() . '/' . import_job_sanitize_id($jobId) . '.json';
}

function import_job_state_path(string $jobId): string
{
    return import_jobs_dir() . '/' . import_job_sanitize_id($jobId) . '.state.json';
}

function import_job_create(string $type, array $meta): string
{
    $jobId = $type . '_' . bin2hex(random_bytes(8));
    $meta['job_id'] = $jobId;
    $meta['type'] = $type;
    $meta['status'] = 'running';
    $meta['progress'] = 0.0;
    if (trim((string)($meta['message'] ?? '')) === '') {
        $meta['message'] = 'Pornire job...';
    }
    $meta['error'] = null;
    $meta['created_at'] = date('c');
    $meta['updated_at'] = $meta['created_at'];
    import_job_save_meta($jobId, $meta);

    return $jobId;
}

function import_job_load_meta(string $jobId): ?array
{
    $path = import_job_meta_path($jobId);
    if (!is_file($path)) {
        return null;
    }

    $data = json_decode((string)file_get_contents($path), true);

    return is_array($data) ? $data : null;
}

function import_job_save_meta(string $jobId, array $meta): void
{
    $meta['updated_at'] = date('c');
    file_put_contents(
        import_job_meta_path($jobId),
        json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    );
}

function import_job_load_state(string $jobId): ?array
{
    $path = import_job_state_path($jobId);
    if (!is_file($path)) {
        return null;
    }

    $data = json_decode((string)file_get_contents($path), true);

    return is_array($data) ? $data : null;
}

function import_job_save_state(string $jobId, array $state): void
{
    file_put_contents(
        import_job_state_path($jobId),
        json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    );
}

function import_job_update(string $jobId, array $patch): array
{
    $meta = import_job_load_meta($jobId);
    if ($meta === null) {
        throw new RuntimeException('Job inexistent.');
    }

    foreach ($patch as $key => $value) {
        $meta[$key] = $value;
    }

    import_job_save_meta($jobId, $meta);

    return $meta;
}

function import_job_extend_runtime(int $seconds = 900): void
{
    @ini_set('max_execution_time', (string)$seconds);
    @set_time_limit($seconds);
}

function import_job_public_status(array $meta): array
{
    $status = (string)($meta['status'] ?? '');

    return [
        'job_id' => (string)($meta['job_id'] ?? ''),
        'type' => (string)($meta['type'] ?? ''),
        'status' => $status,
        'phase' => (string)($meta['phase'] ?? ''),
        'progress' => round((float)($meta['progress'] ?? 0), 1),
        'message' => (string)($meta['message'] ?? ''),
        'error' => $meta['error'] ?? null,
        'done' => in_array($status, ['done', 'cancelled'], true),
        'failed' => $status === 'error',
        'cancelled' => $status === 'cancelled',
    ];
}

function import_job_is_cancelled(string $jobId): bool
{
    $meta = import_job_load_meta($jobId);

    return is_array($meta) && ($meta['status'] ?? '') === 'cancelled';
}

function import_job_cancel(string $jobId): bool
{
    $jobId = import_job_sanitize_id($jobId);
    if ($jobId === '') {
        return false;
    }

    $meta = import_job_load_meta($jobId);
    if ($meta === null) {
        return false;
    }

    if (in_array((string)($meta['status'] ?? ''), ['done', 'cancelled'], true)) {
        return true;
    }

    import_job_update($jobId, [
        'status' => 'cancelled',
        'message' => 'Job oprit de utilizator.',
    ]);
    import_job_save_state($jobId, []);

    return true;
}

function import_job_cancel_all(): int
{
    $cancelled = 0;
    foreach (glob(import_jobs_dir() . '/*.json') ?: [] as $path) {
        if (str_ends_with($path, '.state.json')) {
            continue;
        }

        $meta = json_decode((string)file_get_contents($path), true);
        if (!is_array($meta) || ($meta['status'] ?? '') !== 'running') {
            continue;
        }

        $jobId = (string)($meta['job_id'] ?? '');
        if ($jobId !== '' && import_job_cancel($jobId)) {
            $cancelled++;
        }
    }

    return $cancelled;
}

function import_job_cleanup(string $jobId): void
{
    $metaPath = import_job_meta_path($jobId);
    $statePath = import_job_state_path($jobId);
    if (is_file($metaPath)) {
        @unlink($metaPath);
    }
    if (is_file($statePath)) {
        @unlink($statePath);
    }
}

function import_preview_job_start(array $filesMeta, int $maxPreview, array $options = []): array
{
    $tecdocFiles = [];
    $supplierFiles = [];
    $genericFiles = [];
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
        } else {
            $genericFiles[] = $entry;
        }
    }

    if ($missingFiles !== []) {
        return [
            'ok' => false,
            'error' => 'missing_files',
            'missing_files' => $missingFiles,
        ];
    }

    $previewMode = import_resolve_preview_mode($options, $tecdocFiles, $supplierFiles);
    if ($previewMode === 'tecdoc_master' && $tecdocFiles !== [] && $supplierFiles !== []) {
        return import_preview_job_start_tecdoc_master($filesMeta, $maxPreview, $options);
    }

    if ($supplierFiles === []) {
        return ['ok' => false, 'error' => 'no_supplier_files'];
    }

    $validation = import_validate_uploaded_files($supplierFiles, $tecdocFiles, $genericFiles);
    if (!$validation['ok']) {
        return [
            'ok' => false,
            'error' => 'validation_failed',
            'validation_errors' => $validation['errors'],
        ];
    }

    $catalog = import_build_supplier_catalog($supplierFiles, $maxPreview, $brandFilter);
    if ($catalog['entries'] === []) {
        return ['ok' => false, 'error' => 'empty_supplier_catalog'];
    }

    $tecdocMaxRowsPerCode = max(1, min(250, (int)($options['tecdoc_max_rows_per_code'] ?? 30)));
    $scanState = $tecdocFiles !== []
        ? import_tecdoc_create_scan_state($tecdocFiles, $catalog['entries'], $tecdocMaxRowsPerCode, true)
        : null;

    $jobId = import_job_create('preview', [
        'phase' => $tecdocFiles !== [] ? 'tecdoc_scan' : 'build_products',
        'max_preview' => $maxPreview,
        'brand_filter' => $brandFilter,
        'tecdoc_files_count' => count($tecdocFiles),
        'supplier_files_count' => count($supplierFiles),
        'catalog_total' => (int)$catalog['total_unique'],
        'catalog_truncated' => !empty($catalog['truncated']),
        'validation' => $validation,
    ]);

    import_job_save_state($jobId, [
        'catalog' => $catalog,
        'tecdoc_files' => $tecdocFiles,
        'supplier_files' => $supplierFiles,
        'scan_state' => $scanState,
        'build_index' => 0,
        'products' => [],
        'tecdoc_file_hits' => 0,
        'skipped_no_compat' => 0,
        'tecdoc_max_rows_per_code' => $tecdocMaxRowsPerCode,
    ]);

    import_job_update($jobId, [
        'message' => $tecdocFiles !== []
            ? 'Pregătire scanare CSV TecDoc (' . count($tecdocFiles) . ' fișiere)...'
            : 'Construiesc produse din listele furnizor...',
        'progress' => 1.0,
    ]);

    return ['ok' => true, 'job_id' => $jobId];
}

function import_preview_job_step(string $jobId): array
{
    import_job_extend_runtime(900);

    $meta = import_job_load_meta($jobId);
    if ($meta === null) {
        return ['ok' => false, 'error' => 'job_not_found'];
    }

    if (($meta['status'] ?? '') === 'done') {
        return [
            'ok' => true,
            'status' => import_job_public_status($meta),
            'result' => $meta['result'] ?? null,
        ];
    }

    if (($meta['status'] ?? '') === 'error') {
        return [
            'ok' => false,
            'status' => import_job_public_status($meta),
            'error' => (string)($meta['error'] ?? 'unknown'),
        ];
    }

    if (($meta['status'] ?? '') === 'cancelled') {
        return [
            'ok' => true,
            'status' => import_job_public_status($meta),
            'cancelled' => true,
        ];
    }

    $state = import_job_load_state($jobId);
    if ($state === null) {
        import_job_update($jobId, [
            'status' => 'error',
            'error' => 'Starea job-ului lipsește.',
            'message' => 'Eroare internă job.',
        ]);

        return ['ok' => false, 'error' => 'job_state_missing'];
    }

    try {
        $phase = (string)($meta['phase'] ?? '');
        $importMode = (string)($meta['import_mode'] ?? 'supplier_master');

        if ($importMode === 'tecdoc_master') {
            return import_preview_job_step_tecdoc_master($jobId, $meta, $state);
        }

        if ($phase === 'tecdoc_scan') {
            $scanState = $state['scan_state'] ?? null;
            if (!is_array($scanState)) {
                import_job_update($jobId, ['phase' => 'build_products', 'progress' => 75.0]);
                $meta = import_job_load_meta($jobId) ?? $meta;
                $phase = 'build_products';
            } else {
                $step = import_tecdoc_scan_step($scanState, 25.0, 12000);
                $state['scan_state'] = $scanState;
                import_job_save_state($jobId, $state);

                $pct = import_tecdoc_scan_progress_percent($scanState);
                $fileLabel = import_tecdoc_scan_current_file_label($scanState);
                import_job_update($jobId, [
                    'progress' => min(74.0, max(2.0, $pct * 0.74)),
                    'message' => $step['done']
                        ? 'Scanare CSV TecDoc finalizată. Construiesc produsele...'
                        : ('Scanare CSV TecDoc: ' . $fileLabel . ' (' . round($pct, 1) . '%)'),
                    'phase' => $step['done'] ? 'build_products' : 'tecdoc_scan',
                ]);

                $meta = import_job_load_meta($jobId) ?? $meta;

                return [
                    'ok' => true,
                    'status' => import_job_public_status($meta),
                    'rows_processed' => (int)($step['rows'] ?? 0),
                ];
            }
        }

        if ($phase === 'build_products') {
            $batchSize = 20;
            $catalog = $state['catalog'] ?? ['entries' => []];
            $entries = is_array($catalog['entries'] ?? null) ? $catalog['entries'] : [];
            $buildIndex = (int)($state['build_index'] ?? 0);
            $totalEntries = count($entries);

            if ($totalEntries === 0) {
                import_job_update($jobId, [
                    'status' => 'error',
                    'error' => 'Catalog furnizor gol.',
                    'message' => 'Nu am găsit produse în catalog.',
                ]);

                return ['ok' => false, 'error' => 'empty_catalog'];
            }

            $scanState = is_array($state['scan_state'] ?? null) ? $state['scan_state'] : null;
            $tecdocLookup = is_array($scanState['lookup'] ?? null) ? $scanState['lookup'] : [];
            $tecdocRowGroups = is_array($scanState['row_groups'] ?? null) ? $scanState['row_groups'] : [];

            $markupService = null;
            if (class_exists(\Evasystem\Controllers\AdaosComercial\AdaosComercialService::class)) {
                try {
                    $markupService = new \Evasystem\Controllers\AdaosComercial\AdaosComercialService();
                } catch (Throwable $e) {
                    $markupService = null;
                }
            }

            $products = is_array($state['products'] ?? null) ? $state['products'] : [];
            $tecdocFileHits = (int)($state['tecdoc_file_hits'] ?? 0);
            $skippedNoCompat = (int)($state['skipped_no_compat'] ?? 0);
            $end = min($totalEntries, $buildIndex + $batchSize);

            for ($i = $buildIndex; $i < $end; $i++) {
                $entry = $entries[$i];
                $codeNorm = import_normalize_product_code((string)($entry['code'] ?? ''));
                $tecdocRows = $codeNorm !== '' ? ($tecdocRowGroups[$codeNorm] ?? []) : [];
                $tecdocRecord = import_tecdoc_lookup_catalog_entry($tecdocLookup, $entry);
                if ($tecdocRows !== [] || $tecdocRecord !== null) {
                    $tecdocFileHits++;
                }

                $product = import_build_product_from_supplier_tecdoc(
                    $entry,
                    $markupService,
                    false,
                    $tecdocRecord,
                    $tecdocRows
                );
                if ($product === null) {
                    $skippedNoCompat++;
                    continue;
                }
                $products[] = $product;
            }

            $state['build_index'] = $end;
            $state['products'] = $products;
            $state['tecdoc_file_hits'] = $tecdocFileHits;
            $state['skipped_no_compat'] = $skippedNoCompat;
            import_job_save_state($jobId, $state);

            $buildPct = $totalEntries > 0 ? ($end / $totalEntries) : 1.0;
            $progress = 75.0 + ($buildPct * 24.0);

            if ($end >= $totalEntries) {
                $result = import_preview_job_finalize_result($meta, $state, $products);
                import_job_update($jobId, [
                    'status' => 'done',
                    'phase' => 'done',
                    'progress' => 100.0,
                    'message' => 'Preview finalizat: ' . count($products) . ' produse.',
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
                'progress' => $progress,
                'message' => 'Construiesc produse: ' . $end . ' / ' . $totalEntries . '...',
            ]);

            return [
                'ok' => true,
                'status' => import_job_public_status(import_job_load_meta($jobId) ?? $meta),
            ];
        }

        import_job_update($jobId, [
            'status' => 'error',
            'error' => 'Fază necunoscută: ' . $phase,
        ]);

        return ['ok' => false, 'error' => 'unknown_phase'];
    } catch (Throwable $e) {
        import_job_update($jobId, [
            'status' => 'error',
            'error' => $e->getMessage(),
            'message' => 'Eroare: ' . $e->getMessage(),
        ]);

        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

function import_preview_job_finalize_result(array $meta, array $state, array $products): array
{
    $catalog = $state['catalog'] ?? ['entries' => [], 'total_unique' => 0, 'truncated' => false];
    $maxPreview = (int)($meta['max_preview'] ?? 500);
    $tecdocFileHits = (int)($state['tecdoc_file_hits'] ?? 0);
    $skippedNoCompat = (int)($state['skipped_no_compat'] ?? 0);
    $catalogEntries = is_array($catalog['entries'] ?? null) ? $catalog['entries'] : [];

    $msg = count($products) . ' produse afișate';
    if (!empty($catalog['truncated'])) {
        $msg .= ' (preview limitat la primele ' . $maxPreview . ' produse unice)';
    }
    $msg .= '. Mod: listă furnizor + fișiere CSV TecDoc (fără API online)';
    $msg .= '. ' . number_format((int)($catalog['total_unique'] ?? 0), 0, '.', '.') . ' produse unice în listele furnizor.';
    if (($meta['tecdoc_files_count'] ?? 0) > 0) {
        $msg .= ' ' . (int)$meta['tecdoc_files_count'] . ' fișier(e) CSV TecDoc folosite local.';
        if ($tecdocFileHits > 0) {
            $msg .= ' ' . $tecdocFileHits . ' produse îmbogățite din CSV (OEM, specs, titlu SEO, compatibilități).';
        }
        if ($skippedNoCompat > 0) {
            $msg .= ' ' . $skippedNoCompat . ' produse omise (fără compatibilități auto permise sau fără preț).';
        }
        $apiSkipped = max(0, count($catalogEntries) - count($products));
        if ($apiSkipped > 0) {
            $msg .= ' ' . $apiSkipped . ' produse rămân doar cu date furnizor (fără match în CSV).';
        }
    }

    return [
        'products' => $products,
        'count' => count($products),
        'total_rows' => (int)($catalog['total_unique'] ?? 0),
        'truncated' => !empty($catalog['truncated']),
        'price_index_size' => (int)($catalog['total_unique'] ?? 0),
        'tecdoc_files' => (int)($meta['tecdoc_files_count'] ?? 0),
        'supplier_files' => (int)($meta['supplier_files_count'] ?? 0),
        'import_mode' => ($meta['tecdoc_files_count'] ?? 0) > 0 ? 'supplier_preview' : 'supplier_files',
        'tecdoc_file_hits' => $tecdocFileHits,
        'tecdoc_skipped_no_compat' => $skippedNoCompat,
        'tecdoc_api_found' => 0,
        'tecdoc_api_missing' => 0,
        'tecdoc_api_skipped' => max(0, count($catalogEntries) - count($products)),
        'message' => $msg,
    ];
}

function import_queue_job_start(array $products, ?int $markupRuleId = null): array
{
    if ($products === []) {
        return ['ok' => false, 'error' => 'no_products'];
    }

    $tecdocFiles = import_resolve_uploaded_tecdoc_files();
    $searchCodes = [];
    foreach ($products as $product) {
        foreach (import_oem_codes_from_product($product) as $code) {
            $searchCodes[$code] = true;
        }
        $primary = trim((string)($product['pCode'] ?? ''));
        if ($primary !== '') {
            $searchCodes[$primary] = true;
        }
    }

    $entries = [];
    foreach (array_keys($searchCodes) as $code) {
        $entries[] = ['code' => $code, 'brand' => ''];
    }

    $scanState = $tecdocFiles !== [] && $entries !== []
        ? import_tecdoc_create_scan_state($tecdocFiles, $entries, 0, false)
        : null;
    if (is_array($scanState)) {
        $scanState['collect_rows'] = false;
        $scanState['max_rows_per_code'] = 0;
    }

    $jobId = import_job_create('import', [
        'phase' => $tecdocFiles !== [] && $scanState !== null ? 'tecdoc_scan' : 'insert_products',
        'total_products' => count($products),
        'tecdoc_files_count' => count($tecdocFiles),
        'markup_rule_id' => ($markupRuleId !== null && $markupRuleId > 0) ? $markupRuleId : null,
    ]);

    import_job_save_state($jobId, [
        'products' => $products,
        'insert_index' => 0,
        'scan_state' => $scanState,
        'shared_lookup' => [],
        'stats' => [
            'queued' => 0,
            'with_image' => 0,
            'without_price' => 0,
            'tecdoc_enriched' => 0,
        ],
    ]);

    import_job_update($jobId, [
        'message' => $scanState !== null
            ? 'Pornesc indexare CSV TecDoc pentru produsele selectate...'
            : 'Trimit produsele in coada...',
        'progress' => 1.0,
    ]);

    return ['ok' => true, 'job_id' => $jobId];
}

function import_queue_job_step(string $jobId, PDO $pdo): array
{
    $meta = import_job_load_meta($jobId);
    if ($meta === null) {
        return ['ok' => false, 'error' => 'job_not_found'];
    }

    if (($meta['status'] ?? '') === 'done') {
        return [
            'ok' => true,
            'status' => import_job_public_status($meta),
            'result' => $meta['result'] ?? null,
        ];
    }

    if (($meta['status'] ?? '') === 'error') {
        return [
            'ok' => false,
            'status' => import_job_public_status($meta),
            'error' => (string)($meta['error'] ?? 'unknown'),
        ];
    }

    if (($meta['status'] ?? '') === 'cancelled') {
        return [
            'ok' => true,
            'status' => import_job_public_status($meta),
            'cancelled' => true,
        ];
    }

    $state = import_job_load_state($jobId);
    if ($state === null) {
        import_job_update($jobId, [
            'status' => 'error',
            'error' => 'Starea job-ului lipsește.',
        ]);

        return ['ok' => false, 'error' => 'job_state_missing'];
    }

    try {
        $phase = (string)($meta['phase'] ?? '');

        if ($phase === 'tecdoc_scan') {
            $scanState = $state['scan_state'] ?? null;
            if (!is_array($scanState)) {
                import_job_update($jobId, ['phase' => 'insert_products', 'progress' => 40.0]);
            } else {
                $step = import_tecdoc_scan_step($scanState, 25.0, 12000);
                $state['scan_state'] = $scanState;
                $state['shared_lookup'] = import_tecdoc_lookup_from_scan_state($scanState);
                import_job_save_state($jobId, $state);

                $pct = import_tecdoc_scan_progress_percent($scanState);
                import_job_update($jobId, [
                    'progress' => min(39.0, max(2.0, $pct * 0.39)),
                    'message' => $step['done']
                        ? 'Indexare CSV finalizată. Trimit produsele...'
                        : ('Indexare CSV TecDoc (' . round($pct, 1) . '%)...'),
                    'phase' => $step['done'] ? 'insert_products' : 'tecdoc_scan',
                ]);

                return [
                    'ok' => true,
                    'status' => import_job_public_status(import_job_load_meta($jobId) ?? $meta),
                ];
            }
        }

        if ($phase === 'insert_products') {
            $products = is_array($state['products'] ?? null) ? $state['products'] : [];
            $insertIndex = (int)($state['insert_index'] ?? 0);
            $stats = is_array($state['stats'] ?? null) ? $state['stats'] : [
                'queued' => 0, 'with_image' => 0, 'without_price' => 0, 'tecdoc_enriched' => 0,
            ];
            $total = count($products);
            if ($total === 0) {
                import_job_update($jobId, ['status' => 'error', 'error' => 'Lista de produse este goală.']);
                return ['ok' => false, 'error' => 'empty_products'];
            }

            $sharedLookup = is_array($state['shared_lookup'] ?? null) ? $state['shared_lookup'] : [];
            $tecdocFiles = import_resolve_uploaded_tecdoc_files();
            $batchSize = 12;
            $end = min($total, $insertIndex + $batchSize);

            $markupService = new \Evasystem\Controllers\AdaosComercial\AdaosComercialService();
            $explicitMarkupRuleId = (int) ($meta['markup_rule_id'] ?? 0);
            $explicitMarkupRuleId = $explicitMarkupRuleId > 0 ? $explicitMarkupRuleId : null;
            $columns = import_staging_insert_columns($pdo);
            $placeholders = implode(',', array_map(static fn(string $c): string => ':' . $c, $columns));
            $stmt = $pdo->prepare('INSERT INTO import_produse (`' . implode('`,`', $columns) . '`) VALUES (' . $placeholders . ')');

            for ($i = $insertIndex; $i < $end; $i++) {
                $product = $products[$i];
                $product = import_apply_caietcomenzi_image($product);
                $product = import_enrich_product_from_tecdoc($product, $tecdocFiles, $sharedLookup);
                $enrichedRaw = json_decode((string)($product['raw_json'] ?? '{}'), true);
                if (!empty($enrichedRaw['tecdoc_import_enrichment']['found'])) {
                    $stats['tecdoc_enriched']++;
                }

                $code = trim((string)($product['pCode'] ?? ''));
                $brand = trim((string)($product['pBrand'] ?? ''));
                $images = json_decode((string)($product['pImages'] ?? '[]'), true);
                if (!is_array($images) || empty($images[0])) {
                    $images = [];
                }

                $imageMeta = is_array($product['__image_meta'] ?? null)
                    ? $product['__image_meta']
                    : import_default_image_meta($product, $images);
                unset($product['__image_meta']);

                if (import_should_fetch_tecdoc_image([
                    'pImages' => json_encode($images, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'pImageSource' => (string)($imageMeta['source'] ?? ''),
                    'raw_json' => (string)($product['raw_json'] ?? '{}'),
                ])) {
                    $found = import_find_image_for_product($product, $tecdocFiles, $sharedLookup);
                    $imageMeta = [
                        'source' => (string)($found['source'] ?? 'missing'),
                        'query' => (string)($found['query'] ?? ''),
                    ];
                    $imageUrl = (string)($found['url'] ?? '');
                    if ($imageUrl !== '') {
                        $product = import_apply_image_lookup_result($product, $found);
                        $images = json_decode((string)($product['pImages'] ?? '[]'), true);
                        if (!is_array($images)) {
                            $images = [$imageUrl];
                        }
                        $imageMeta = [
                            'source' => (string)($found['source'] ?? 'tecdoc_api'),
                            'query' => (string)($found['query'] ?? ''),
                        ];
                        $stats['with_image']++;
                    }
                } elseif (!empty($images[0])) {
                    $stats['with_image']++;
                }

                $sourcePayload = json_decode((string)($product['raw_json'] ?? '{}'), true);
                if (!is_array($sourcePayload)) {
                    $sourcePayload = [];
                }

                $row = [
                    'pName' => trim((string)($product['pName'] ?? '')),
                    'pCode' => $code,
                    'pBrand' => $brand,
                    'pMarca' => trim((string)($product['pMarca'] ?? '')),
                    'pModel' => trim((string)($product['pModel'] ?? '')),
                    'pMotorizare' => trim((string)($product['pMotorizare'] ?? '')),
                    'pCar' => trim((string)($product['pCar'] ?? $brand)),
                    'pBasePrice' => trim((string)($product['pBasePrice'] ?? ($product['pPrice'] ?? ''))),
                    'pStock' => trim((string)($product['pStock'] ?? '')),
                    'pCategory' => trim((string)($product['pCategory'] ?? '')),
                    'pSubcategory' => trim((string)($product['pSubcategory'] ?? '')),
                    'pCompatibilitati' => trim((string)($product['pCompatibilitati'] ?? '')),
                    'pOem' => trim((string)($product['pOem'] ?? '')),
                    'pSupplier' => trim((string)($product['pSupplier'] ?? $brand)),
                    'pState' => 'Nou',
                    'pCity' => '',
                    'pNote' => trim((string)($product['pNote'] ?? '')),
                    'pNoteWebsite' => trim((string)($product['pNoteWebsite'] ?? '')),
                    'pNoteMarketplace' => trim((string)($product['pNoteMarketplace'] ?? '')),
                    'pImages' => json_encode($images, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    'pImageSource' => $imageMeta['source'],
                    'pShipping' => trim((string)($product['pStock'] ?? '')),
                    'pWarranty' => '',
                    'pReturn' => '',
                    'pWhatsapp' => '',
                ];

                $pricing = $markupService->applyAutomaticMarkup($row, null, false, $explicitMarkupRuleId);
                $row = array_merge($row, $pricing['data']);
                if (trim((string)($row['pBasePrice'] ?? '')) === '' && trim((string)($row['pPrice'] ?? '')) === '') {
                    $stats['without_price']++;
                }

                $row['raw_json'] = json_encode(array_merge($sourcePayload, [
                    'schema' => 'product_import_v2',
                    'product' => [
                        'name' => (string)$row['pName'],
                        'code' => (string)$row['pCode'],
                        'brand' => (string)$row['pBrand'],
                        'marca' => (string)$row['pMarca'],
                        'model' => (string)$row['pModel'],
                        'motorizare' => (string)$row['pMotorizare'],
                        'category' => (string)$row['pCategory'],
                        'subcategory' => (string)$row['pSubcategory'],
                        'price' => (string)$row['pPrice'],
                        'base_price' => (string)($row['pBasePrice'] ?? ''),
                        'stock' => (string)$row['pStock'],
                        'oem_codes' => (string)$row['pOem'],
                    ],
                    'product_summary' => $sourcePayload['product_summary'] ?? [],
                    'source_rows' => $sourcePayload['rows'] ?? ($sourcePayload['source_rows'] ?? []),
                    '__image_source' => $imageMeta['source'],
                    '__image_query' => $imageMeta['query'],
                ]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $row['status'] = 'pending';

                $prepared = import_prepare_staging_insert($pdo, $row);
                $executeData = [];
                foreach ($columns as $column) {
                    $executeData[$column] = $prepared[$column] ?? null;
                }
                $stmt->execute($executeData);
                $stats['queued']++;
            }

            $state['insert_index'] = $end;
            $state['stats'] = $stats;
            import_job_save_state($jobId, $state);

            $insertPct = $total > 0 ? ($end / $total) : 1.0;
            $progress = 40.0 + ($insertPct * 59.0);

            if ($end >= $total) {
                $queued = (int)$stats['queued'];
                $tecdocEnriched = (int)$stats['tecdoc_enriched'];
                $withImage = (int)$stats['with_image'];
                $withoutPrice = (int)$stats['without_price'];
                $result = [
                    'success' => true,
                    'message' => "$queued produse pregatite in coada de publicare ($withImage cu imagine).",
                    'count' => $queued,
                    'tecdoc_enriched' => $tecdocEnriched,
                    'with_image' => $withImage,
                    'without_price' => $withoutPrice,
                    'redirect' => '/admin/importreview?status=pending',
                ];
                import_job_update($jobId, [
                    'status' => 'done',
                    'phase' => 'done',
                    'progress' => 100.0,
                    'message' => $result['message'],
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
                'progress' => $progress,
                'message' => 'Trimit in coada: ' . $end . ' / ' . $total . ' produse...',
            ]);

            return [
                'ok' => true,
                'status' => import_job_public_status(import_job_load_meta($jobId) ?? $meta),
            ];
        }

        import_job_update($jobId, ['status' => 'error', 'error' => 'Fază necunoscută.']);
        return ['ok' => false, 'error' => 'unknown_phase'];
    } catch (Throwable $e) {
        import_job_update($jobId, [
            'status' => 'error',
            'error' => $e->getMessage(),
            'message' => 'Eroare: ' . $e->getMessage(),
        ]);

        return ['ok' => false, 'error' => $e->getMessage()];
    }
}
