<?php
declare(strict_types=1);

function import_image_job_resolve_targets(PDO $pdo, array $filterIds, string $supplier, bool $force = false): array
{
    $sql = "SELECT * FROM import_produse WHERE status='pending'";
    $params = [];
    if ($filterIds !== []) {
        $placeholders = implode(',', array_fill(0, count($filterIds), '?'));
        $sql .= " AND id IN ($placeholders)";
        $params = $filterIds;
    } elseif ($supplier !== '') {
        $sql .= ' AND pSupplier=?';
        $params[] = $supplier;
    }
    $sql .= ' ORDER BY id ASC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $ids = [];
    $skipped = 0;
    foreach ($rows as $row) {
        if ($force || import_should_fetch_tecdoc_image($row)) {
            $ids[] = (int)($row['id'] ?? 0);
        } else {
            $skipped++;
        }
    }

    $ids = array_values(array_filter($ids));

    return ['ids' => $ids, 'skipped' => $skipped, 'total_rows' => count($rows)];
}

function import_image_job_detect_api_status(array $errors): string
{
    $apiStatus = 'ok';
    foreach ($errors as $error) {
        $lower = mb_strtolower((string)$error, 'UTF-8');
        if (str_contains($lower, '429') || str_contains($lower, 'limit')) {
            return 'rate_limit';
        }
        if (str_contains($lower, 'abonat') || str_contains($lower, '403') || str_contains($lower, 'subscribed')) {
            $apiStatus = 'not_subscribed';
        }
    }

    return $apiStatus;
}

function import_image_job_finalize_meta(array $state, int $total, int $skipped): array
{
    $updated = (int)($state['updated'] ?? 0);
    $failed = (int)($state['failed'] ?? 0);
    $kept = (int)($state['kept'] ?? 0);
    $scanned = (int)($state['scanned'] ?? 0);
    $errors = is_array($state['errors'] ?? null) ? $state['errors'] : [];
    $apiStatus = (string)($state['api_status'] ?? 'ok');
    if ($apiStatus === 'ok') {
        $apiStatus = import_image_job_detect_api_status($errors);
    }
    if ($apiStatus === 'ok' && $updated === 0 && $kept === 0 && $scanned > 0 && $errors !== []) {
        $apiStatus = 'error';
    } elseif ($apiStatus === 'ok' && ($updated > 0 || $kept > 0) && $failed > 0) {
        $apiStatus = 'partial';
    } elseif ($apiStatus === 'ok' && $updated === 0 && $kept > 0 && $failed === 0) {
        $apiStatus = 'ok';
    } elseif ($apiStatus === 'ok' && $updated === 0 && $kept === 0 && $scanned > 0) {
        $apiStatus = 'not_found';
    }

    $message = $updated > 0
        ? "Imagini găsite pentru $updated produse."
        : ($kept > 0
            ? "Păstrate $kept imagini existente (pipeline n-a găsit mai bună)."
            : ($scanned === 0
                ? 'Toate produsele filtrate au deja imagine.'
                : 'Scanare finalizată — nu s-au găsit imagini noi.'));

    return [
        'status' => 'done',
        'progress' => 100.0,
        'message' => $message,
        'api_status' => $apiStatus,
        'result' => [
            'updated' => $updated,
            'failed' => $failed,
            'kept' => $kept,
            'scanned' => $scanned,
            'skipped' => $skipped,
            'total_needing' => $total,
            'errors' => array_values(array_unique($errors)),
            'api_status' => $apiStatus,
            'log_file' => '/admin/storage/logs/image_pipeline.log',
        ],
    ];
}

function import_image_job_start(PDO $pdo, array $filterIds, string $supplier, bool $force = false): array
{
    $servicePath = dirname(__DIR__, 4) . '/lib/Scraper/ImageSearchService.php';
    if (!is_file($servicePath)) {
        return [
            'ok' => false,
            'error' => 'missing_pipeline',
            'message' => 'Pipeline imagini indisponibil (ImageSearchService).',
        ];
    }
    require_once $servicePath;

    if (!\ImageSearchService::hasActiveImagePlans()) {
        return [
            'ok' => false,
            'error' => 'no_image_plans',
            'message' => 'Niciun plan activ în /admin/scraper (Pipeline imagini).',
        ];
    }

    $targets = import_image_job_resolve_targets($pdo, $filterIds, $supplier, $force);
    $ids = $targets['ids'];
    $skipped = (int)($targets['skipped'] ?? 0);

    if ($ids === []) {
        return [
            'ok' => false,
            'error' => 'no_targets',
            'skipped' => $skipped,
            'message' => $force
                ? 'Nu am găsit produsele selectate în coada de publicat.'
                : ($skipped > 0
                    ? 'Toate produsele filtrate au deja imagine. Selectează un produs pentru rescanare forțată.'
                    : 'Nu există produse de scanat.'),
        ];
    }

    $total = count($ids);
    $jobId = import_job_create('refresh_images', [
        'phase' => 'scan_images',
        'progress' => 0.0,
        'message' => "Scanare imagini: 0 / $total",
        'supplier' => $supplier,
        'filter_ids' => $filterIds,
        'force' => $force,
        'total' => $total,
        'skipped' => $skipped,
    ]);

    import_job_save_state($jobId, [
        'import_ids' => $ids,
        'offset' => 0,
        'updated' => 0,
        'failed' => 0,
        'scanned' => 0,
        'kept' => 0,
        'errors' => [],
        'stop_early' => false,
        'api_status' => 'ok',
        'skipped' => $skipped,
        'force' => $force,
    ]);

    return [
        'ok' => true,
        'job_id' => $jobId,
        'total' => $total,
        'skipped' => $skipped,
    ];
}

function import_image_job_step(PDO $pdo, string $jobId): array
{
    @set_time_limit(120);
    @ini_set('max_execution_time', '120');
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }

    $meta = import_job_load_meta($jobId);
    if ($meta === null) {
        return ['ok' => false, 'error' => 'Job inexistent.'];
    }

    if ((string)($meta['status'] ?? '') === 'done') {
        return [
            'ok' => true,
            'status' => import_job_public_status($meta),
            'result' => is_array($meta['result'] ?? null) ? $meta['result'] : [],
        ];
    }

    if (import_job_is_cancelled($jobId)) {
        return [
            'ok' => true,
            'cancelled' => true,
            'status' => import_job_public_status($meta),
        ];
    }

    $state = import_job_load_state($jobId);
    if ($state === null) {
        import_job_update($jobId, ['status' => 'error', 'error' => 'Stare job lipsă.']);
        return ['ok' => false, 'error' => 'Stare job lipsă.'];
    }

    $importIds = is_array($state['import_ids'] ?? null) ? $state['import_ids'] : [];
    $offset = (int)($state['offset'] ?? 0);
    $total = count($importIds);
    $skipped = (int)($state['skipped'] ?? ($meta['skipped'] ?? 0));

    if ($total === 0 || $offset >= $total || !empty($state['stop_early'])) {
        $final = import_image_job_finalize_meta($state, $total, $skipped);
        import_job_update($jobId, $final);
        import_job_save_state($jobId, []);

        return [
            'ok' => true,
            'status' => import_job_public_status(import_job_load_meta($jobId) ?? $meta),
            'result' => $final['result'],
        ];
    }

    $importId = (int)$importIds[$offset];
    $stmt = $pdo->prepare("SELECT * FROM import_produse WHERE id=? AND status='pending' LIMIT 1");
    $stmt->execute([$importId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    $code = is_array($row) ? (string)($row['pCode'] ?? '') : ('#' . $importId);
    $processingNum = $offset + 1;
    $preProgress = $total > 0 ? round(($offset / $total) * 100, 1) : 0.0;
    import_job_update($jobId, [
        'progress' => $preProgress,
        'message' => 'Scanare imagini: ' . $processingNum . ' / ' . $total . ' (' . $code . ')...',
    ]);

    $state['scanned'] = (int)($state['scanned'] ?? 0) + 1;

    if (is_array($row)) {
        $force = !empty($state['force']);
        $tecdocFiles = import_resolve_uploaded_tecdoc_files();
        $sharedLookup = [];
        $found = import_find_image_for_product($row, $tecdocFiles, $sharedLookup, false, $force, [
            'background_job' => true,
            'step_budget_sec' => 90,
        ]);
        $imageUrl = trim((string)($found['url'] ?? ''));
        $oldUrl = import_row_image_url($row);
        $foundSource = (string)($found['source'] ?? '');
        $oldSource = (string)($row['pImageSource'] ?? '');
        $foundTrusted = import_image_url_is_trusted($imageUrl, $foundSource);
        $oldTrusted = import_image_url_is_trusted($oldUrl, $oldSource);
        $shouldUpdate = $imageUrl !== ''
            && !import_image_is_placeholder($imageUrl)
            && (
                !str_starts_with($imageUrl, '/uploads/')
                || import_image_url_is_trusted($imageUrl, $foundSource)
            )
            && (
                ($force && ($imageUrl !== $oldUrl || ($foundTrusted && !$oldTrusted)))
                || (!$force && !($imageUrl === $oldUrl && !$foundTrusted))
            );

        if ($shouldUpdate) {
            $prepared = import_apply_image_lookup_result($row, $found);
            import_sync_prepared_row($pdo, $importId, $prepared);
            $state['updated'] = (int)($state['updated'] ?? 0) + 1;
        } elseif ($oldTrusted && $oldUrl !== '') {
            $state['kept'] = (int)($state['kept'] ?? 0) + 1;
        } else {
            $state['failed'] = (int)($state['failed'] ?? 0) + 1;
            if (is_array($row) && $oldUrl !== '' && !import_image_url_is_trusted($oldUrl, $oldSource)) {
                $cleared = $row;
                $cleared['pImages'] = '[]';
                $cleared['pImageSource'] = 'missing';
                import_sync_prepared_row($pdo, $importId, $cleared);
            }
            $apiError = trim((string)($found['api_error'] ?? ''));
            if ($apiError === '' && function_exists('tecdoc_last_api_error')) {
                $last = tecdoc_last_api_error();
                $apiError = is_array($last) ? (string)($last['message'] ?? '') : '';
            }
            if ($apiError !== '') {
                $errors = is_array($state['errors'] ?? null) ? $state['errors'] : [];
                $errors[] = (string)($row['pCode'] ?? '—') . ': ' . $apiError;
                $state['errors'] = array_values(array_unique($errors));
            } elseif ($force) {
                $errors = is_array($state['errors'] ?? null) ? $state['errors'] : [];
                $errors[] = (string)($row['pCode'] ?? '—') . ': pipeline: fără imagine nouă';
                $state['errors'] = array_values(array_unique($errors));
                $apiError = end($errors) ?: '';
            }
            if ($apiError !== '') {
                $lower = mb_strtolower($apiError, 'UTF-8');
                if (
                    str_contains($lower, '429')
                    || str_contains($lower, 'limit')
                    || str_contains($lower, 'abonat')
                    || str_contains($lower, '403')
                    || str_contains($lower, 'subscribed')
                ) {
                    $state['stop_early'] = true;
                    $state['api_status'] = import_image_job_detect_api_status($state['errors']);
                }
            }
        }
    } else {
        $state['failed'] = (int)($state['failed'] ?? 0) + 1;
    }

    $state['offset'] = $offset + 1;
    import_job_save_state($jobId, $state);

    $done = (int)$state['offset'] >= $total || !empty($state['stop_early']);
    $progress = $total > 0 ? round(((int)$state['offset'] / $total) * 100, 1) : 100.0;
    $message = $done
        ? 'Finalizare scanare imagini...'
        : 'Scanare imagini: ' . (int)$state['offset'] . ' / ' . $total . ' (' . $code . ')';

    if ($done) {
        $final = import_image_job_finalize_meta($state, $total, $skipped);
        import_job_update($jobId, array_merge($final, [
            'message' => (string)$final['message'],
        ]));
        import_job_save_state($jobId, []);

        return [
            'ok' => true,
            'status' => import_job_public_status(import_job_load_meta($jobId) ?? $meta),
            'result' => $final['result'],
        ];
    }

    import_job_update($jobId, [
        'progress' => $progress,
        'message' => $message,
    ]);

    return [
        'ok' => true,
        'status' => import_job_public_status(import_job_load_meta($jobId) ?? $meta),
        'result' => [
            'updated' => (int)($state['updated'] ?? 0),
            'failed' => (int)($state['failed'] ?? 0),
            'scanned' => (int)($state['scanned'] ?? 0),
            'skipped' => $skipped,
            'total_needing' => $total,
            'api_status' => (string)($state['api_status'] ?? 'ok'),
        ],
    ];
}
