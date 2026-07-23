<?php
declare(strict_types=1);

require_once __DIR__ . '/import_image_pipeline_lib.php';

/** Append live log line to import image job meta (visible in UI). */
function import_image_job_push_log(string $jobId, string $line, string $level = 'info'): void
{
    $jobId = import_job_sanitize_id($jobId);
    $line = trim($line);
    if ($jobId === '' || $line === '') {
        return;
    }

    $meta = import_job_load_meta($jobId);
    if ($meta === null) {
        return;
    }

    $log = is_array($meta['live_log'] ?? null) ? $meta['live_log'] : [];
    $log[] = [
        't' => date('H:i:s'),
        'level' => $level,
        'msg' => $line,
    ];
    if (count($log) > 50) {
        $log = array_slice($log, -50);
    }

    $meta['live_log'] = $log;
    $meta['message'] = $line;
    import_job_save_meta($jobId, $meta);

    if (($meta['status'] ?? '') === 'running') {
        $total = max(0, (int) ($meta['total'] ?? 0));
        if ($total > 0 && !str_contains($line, 'Runner CLI')) {
            $state = import_job_load_state($jobId);
            $offset = is_array($state) ? max(0, (int) ($state['offset'] ?? 0)) : 0;
            $basePct = ($offset / $total) * 100;
            $livePct = min(95.0, $basePct + min(90.0, count($log) * 2.5));
            if ($livePct > (float) ($meta['progress'] ?? 0)) {
                import_job_update($jobId, ['progress' => round($livePct, 1)]);
            }
        }
    }
}

/** @return array<string, mixed> */
function import_image_job_runner_cli_path(): ?string
{
    $path = dirname(__DIR__, 3) . '/tools/import_image_job_runner_cli.php';

    return is_file($path) ? $path : null;
}

function import_image_job_php_binary(): string
{
    $env = trim((string) (getenv('IMPORT_IMAGE_PHP_BINARY') ?: ($_ENV['IMPORT_IMAGE_PHP_BINARY'] ?? '')));
    if ($env !== '' && is_file($env)) {
        return $env;
    }

    if (defined('PHP_BINARY') && PHP_BINARY !== '' && is_file(PHP_BINARY)) {
        $base = strtolower(basename(str_replace('\\', '/', PHP_BINARY)));
        if (
            str_starts_with($base, 'php')
            && !str_contains($base, 'httpd')
            && !str_contains($base, 'apache')
            && !str_contains($base, 'nginx')
        ) {
            return PHP_BINARY;
        }
    }

    $candidates = [
        'D:\\laragon\\bin\\php\\php-8.3.30-Win32-vs16-x64\\php.exe',
        'D:\\laragon\\bin\\php\\php-8.3.16-Win32-vs16-x64\\php.exe',
        'F:\\laragon\\bin\\php\\php-8.3.30-Win32-vs16-x64\\php.exe',
        'F:\\laragon\\bin\\php\\php-8.3.16-Win32-vs16-x64\\php.exe',
        'C:\\laragon\\bin\\php\\php-8.3.30-Win32-vs16-x64\\php.exe',
    ];
    foreach ($candidates as $candidate) {
        if (is_file($candidate)) {
            return $candidate;
        }
    }

    foreach (['D:\\laragon\\bin\\php\\php-*\\php.exe', 'F:\\laragon\\bin\\php\\php-*\\php.exe', 'C:\\laragon\\bin\\php\\php-*\\php.exe'] as $pattern) {
        $glob = glob($pattern) ?: [];
        rsort($glob);
        foreach ($glob as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }
    }

    return 'php';
}

function import_image_job_runner_is_active(string $jobId): bool
{
    $meta = import_job_load_meta($jobId);
    if ($meta === null) {
        return false;
    }

    if ((string) ($meta['status'] ?? '') === 'done') {
        return false;
    }

    $heartbeat = strtotime((string) ($meta['runner_heartbeat_at'] ?? ''));
    $pid = (int) ($meta['runner_pid'] ?? 0);
    if ($pid > 0 && $heartbeat > 0 && (time() - $heartbeat) < 45) {
        return true;
    }

    if ($pid > 0 && DIRECTORY_SEPARATOR === '\\') {
        $out = [];
        @exec('tasklist /FI ' . escapeshellarg('PID eq ' . $pid) . ' /NH', $out);
        $line = trim((string) ($out[0] ?? ''));
        if ($line !== '' && str_contains($line, (string) $pid)) {
            return true;
        }
    }
    if ($pid > 0 && function_exists('posix_kill')) {
        return @posix_kill($pid, 0);
    }

    return false;
}

function import_image_job_runner_spawn_cooldown_ok(string $jobId, int $cooldownSec = 30): bool
{
    $meta = import_job_load_meta($jobId);
    if ($meta === null) {
        return true;
    }

    $lastSpawn = strtotime((string) ($meta['runner_spawn_requested_at'] ?? ''));
    if ($lastSpawn <= 0) {
        return true;
    }

    return (time() - $lastSpawn) >= max(5, $cooldownSec);
}

function import_image_job_spawn_background_runner(string $jobId): bool
{
    $jobId = import_job_sanitize_id($jobId);
    if ($jobId === '') {
        return false;
    }

    if (import_image_job_runner_is_active($jobId)) {
        return true;
    }

    if (!import_image_job_runner_spawn_cooldown_ok($jobId, 30)) {
        return false;
    }

    $cli = import_image_job_runner_cli_path();
    if ($cli === null) {
        return false;
    }

    $phpBin = import_image_job_php_binary();
    if (!is_file($phpBin)) {
        return false;
    }

    $logFile = import_jobs_dir() . DIRECTORY_SEPARATOR . $jobId . '.runner.log';
    $batFile = import_jobs_dir() . DIRECTORY_SEPARATOR . $jobId . '.runner.bat';

    import_job_update($jobId, [
        'runner_spawn_requested_at' => date('c'),
        'runner_spawn_count' => (int) (import_job_load_meta($jobId)['runner_spawn_count'] ?? 0) + 1,
    ]);

    $bat = '@echo off' . "\r\n"
        . 'cd /d ' . escapeshellarg(dirname($cli)) . "\r\n"
        . escapeshellarg($phpBin) . ' ' . escapeshellarg($cli) . ' ' . escapeshellarg($jobId)
        . ' >> ' . escapeshellarg($logFile) . ' 2>&1' . "\r\n";
    if (@file_put_contents($batFile, $bat) === false) {
        import_image_job_push_log($jobId, 'Runner CLI: nu pot crea scriptul .bat', 'error');

        return false;
    }

    if (DIRECTORY_SEPARATOR === '\\') {
        $cmd = 'cmd /c start "" /B ' . escapeshellarg($phpBin)
            . ' ' . escapeshellarg($cli)
            . ' ' . escapeshellarg($jobId)
            . ' >> ' . escapeshellarg($logFile) . ' 2>&1';
        @pclose(@popen($cmd, 'r'));

        $spawnCount = (int) (import_job_load_meta($jobId)['runner_spawn_count'] ?? 1);
        if ($spawnCount <= 1) {
            import_image_job_push_log($jobId, 'Runner CLI lansat în fundal (PHP: ' . basename($phpBin) . ').', 'info');
        }

        return true;
    }

    $cmd = escapeshellarg($phpBin)
        . ' ' . escapeshellarg($cli)
        . ' ' . escapeshellarg($jobId)
        . ' >> ' . escapeshellarg($logFile)
        . ' 2>&1 &';
    exec($cmd);

    return true;
}

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
            'batch_size' => import_image_job_batch_size(),
        ],
    ];
}

function import_image_job_start(PDO $pdo, array $filterIds, string $supplier, bool $force = false): array
{
    $boot = import_image_pipeline_boot();
    if (empty($boot['ok'])) {
        return [
            'ok' => false,
            'error' => (string)($boot['error'] ?? 'boot_failed'),
            'message' => (string)($boot['message'] ?? 'Pipeline imagini indisponibil.'),
        ];
    }

    if (!class_exists('ImageSearchService', false) || !\ImageSearchService::hasActiveImagePlans()) {
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
    $batchSize = import_image_job_batch_size();
    $jobId = import_job_create('refresh_images', [
        'phase' => 'scan_images',
        'progress' => 0.0,
        'message' => "Scanare imagini: 0 / $total (batch $batchSize, scraper_web + Ollama)",
        'supplier' => $supplier,
        'filter_ids' => $filterIds,
        'force' => $force,
        'total' => $total,
        'skipped' => $skipped,
        'batch_size' => $batchSize,
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
        'batch_size' => $batchSize,
    ]);

    import_image_job_push_log($jobId, 'Job creat — pornesc runner CLI în fundal (fără timeout browser).', 'info');
    $spawned = import_image_job_spawn_background_runner($jobId);

    return [
        'ok' => true,
        'job_id' => $jobId,
        'total' => $total,
        'skipped' => $skipped,
        'batch_size' => $batchSize,
        'spawned' => $spawned,
    ];
}

/**
 * Procesează un produs din coadă — returnează delta pentru state job.
 *
 * @return array{updated:int,failed:int,kept:int,scanned:int,errors:list<string>,stop_early:bool,api_status:string,code:string}
 */
function import_image_job_process_one(PDO $pdo, int $importId, array $state, string $jobId = ''): array
{
    $delta = [
        'updated' => 0,
        'failed' => 0,
        'kept' => 0,
        'scanned' => 1,
        'errors' => [],
        'stop_early' => false,
        'api_status' => (string)($state['api_status'] ?? 'ok'),
        'code' => '#' . $importId,
    ];

    $stmt = $pdo->prepare("SELECT * FROM import_produse WHERE id=? AND status='pending' LIMIT 1");
    $stmt->execute([$importId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!is_array($row)) {
        $delta['failed'] = 1;

        return $delta;
    }

    $delta['code'] = (string)($row['pCode'] ?? ('#' . $importId));
    $force = !empty($state['force']);
    $oldUrl = import_row_image_url($row);
    $oldSource = (string)($row['pImageSource'] ?? '');

    $jobId = trim($jobId);
    if ($jobId !== '') {
        $brand = trim((string)($row['pBrand'] ?? ''));
        import_image_job_push_log(
            $jobId,
            'Produs ' . $delta['code'] . ($brand !== '' ? ' (' . $brand . ')' : '') . ': pornesc scraper_web + Ollama',
            'info'
        );
    }

    if (
        $force
        && $oldUrl !== ''
        && !import_image_is_placeholder($oldUrl)
        && !import_image_url_is_trusted($oldUrl, $oldSource)
    ) {
        $cleared = $row;
        $cleared['pImages'] = '[]';
        $cleared['pImageSource'] = 'missing';
        import_sync_prepared_row($pdo, $importId, $cleared);
        $row = $cleared;
        $oldUrl = '';
        $oldSource = 'missing';
    }

    $tecdocFiles = import_resolve_uploaded_tecdoc_files();
    $sharedLookup = [];
    $lookupOpts = import_image_job_lookup_opts($force, $jobId !== '' ? $jobId : null);
    $found = import_find_image_for_product($row, $tecdocFiles, $sharedLookup, false, $force, $lookupOpts);
    $imageUrl = trim((string)($found['url'] ?? ''));

    if ($oldUrl === '') {
        $oldUrl = import_row_image_url($row);
        $oldSource = (string)($row['pImageSource'] ?? '');
    }

    $foundSource = (string)($found['source'] ?? '');
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
        $delta['updated'] = 1;
        if ($jobId !== '') {
            import_image_job_push_log(
                $jobId,
                'Imagine găsită: ' . ($foundSource !== '' ? $foundSource : 'sursă necunoscută'),
                'ok'
            );
        }

        return $delta;
    }

    if ($oldTrusted && $oldUrl !== '') {
        $delta['kept'] = 1;
        if ($jobId !== '') {
            import_image_job_push_log($jobId, 'Imagine existentă păstrată (deja validă)', 'info');
        }

        return $delta;
    }

    $delta['failed'] = 1;
    if ($oldUrl !== '' && !import_image_url_is_trusted($oldUrl, $oldSource)) {
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
        $delta['errors'][] = (string)($row['pCode'] ?? '—') . ': ' . $apiError;
    } elseif ($force) {
        $delta['errors'][] = (string)($row['pCode'] ?? '—') . ': pipeline: fără imagine nouă';
        $apiError = end($delta['errors']) ?: '';
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
            $delta['stop_early'] = true;
            $delta['api_status'] = import_image_job_detect_api_status($delta['errors']);
        }
    }

    if ($jobId !== '') {
        $failMsg = end($delta['errors']);
        import_image_job_push_log(
            $jobId,
            is_string($failMsg) && $failMsg !== '' ? $failMsg : 'Fără imagine nouă',
            'warn'
        );
    }

    return $delta;
}

/**
 * @param list<int> $importIds
 * @return list<array<string, mixed>>
 */
function import_image_job_process_batch(PDO $pdo, array $importIds, array $state, string $jobId = ''): array
{
    $importIds = array_values(array_filter(array_map('intval', $importIds)));
    if ($importIds === []) {
        return [];
    }

    $batchSize = count($importIds);
    $useProc = import_image_job_proc_parallel_enabled() && $batchSize > 1;
    $worker = import_image_job_worker_cli_path();

    if ($useProc && $worker !== null) {
        $phpBin = import_image_job_php_binary();
        $forceFlag = !empty($state['force']) ? '1' : '0';
        $processes = [];

        foreach ($importIds as $importId) {
            $cmd = escapeshellarg($phpBin)
                . ' ' . escapeshellarg($worker)
                . ' ' . (int) $importId
                . ' ' . $forceFlag;
            $descriptors = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ];
            $proc = @proc_open($cmd, $descriptors, $pipes, dirname(__DIR__, 3));
            if (!is_resource($proc)) {
                $useProc = false;
                break;
            }
            fclose($pipes[0]);
            stream_set_blocking($pipes[1], false);
            stream_set_blocking($pipes[2], false);
            $processes[] = [
                'import_id' => $importId,
                'proc' => $proc,
                'stdout' => $pipes[1],
                'stderr' => $pipes[2],
            ];
        }

        if ($useProc && $processes !== []) {
            $deadline = microtime(true) + max(120.0, 95.0 * $batchSize);
            $results = [];

            while ($processes !== [] && microtime(true) < $deadline) {
                foreach ($processes as $idx => $entry) {
                    $status = proc_get_status($entry['proc']);
                    if (!$status['running']) {
                        $out = stream_get_contents($entry['stdout']);
                        $err = stream_get_contents($entry['stderr']);
                        fclose($entry['stdout']);
                        fclose($entry['stderr']);
                        proc_close($entry['proc']);

                        $decoded = json_decode(trim((string) $out), true);
                        if (is_array($decoded)) {
                            $results[] = $decoded;
                        } else {
                            $results[] = import_image_job_process_one($pdo, (int) $entry['import_id'], $state, $jobId);
                            if ($err !== '') {
                                $results[count($results) - 1]['errors'][] = trim($err);
                            }
                        }
                        unset($processes[$idx]);
                    }
                }
                if ($processes !== []) {
                    usleep(150000);
                }
            }

            foreach ($processes as $entry) {
                @proc_terminate($entry['proc']);
                fclose($entry['stdout']);
                fclose($entry['stderr']);
                proc_close($entry['proc']);
                $results[] = import_image_job_process_one($pdo, (int) $entry['import_id'], $state, $jobId);
            }

            usort($results, static fn (array $a, array $b): int => ((int)($a['import_id'] ?? 0)) <=> ((int)($b['import_id'] ?? 0)));

            return $results;
        }
    }

    $results = [];
    foreach ($importIds as $importId) {
        $delta = import_image_job_process_one($pdo, (int) $importId, $state, $jobId);
        $delta['import_id'] = (int) $importId;
        $results[] = $delta;
    }

    return $results;
}

/** @param list<array<string, mixed>> $deltas */
function import_image_job_merge_deltas(array $state, array $deltas): array
{
    foreach ($deltas as $delta) {
        if (!is_array($delta)) {
            continue;
        }
        $state['scanned'] = (int)($state['scanned'] ?? 0) + (int)($delta['scanned'] ?? 0);
        $state['updated'] = (int)($state['updated'] ?? 0) + (int)($delta['updated'] ?? 0);
        $state['failed'] = (int)($state['failed'] ?? 0) + (int)($delta['failed'] ?? 0);
        $state['kept'] = (int)($state['kept'] ?? 0) + (int)($delta['kept'] ?? 0);
        if (!empty($delta['stop_early'])) {
            $state['stop_early'] = true;
        }
        if (!empty($delta['api_status']) && (string) $delta['api_status'] !== 'ok') {
            $state['api_status'] = (string) $delta['api_status'];
        }
        $errors = is_array($state['errors'] ?? null) ? $state['errors'] : [];
        foreach ((array)($delta['errors'] ?? []) as $err) {
            $err = trim((string) $err);
            if ($err !== '') {
                $errors[] = $err;
            }
        }
        $state['errors'] = array_values(array_unique($errors));
    }

    return $state;
}

function import_image_job_step(PDO $pdo, string $jobId): array
{
    $batchSize = import_image_job_batch_size();
    if (PHP_SAPI === 'cli') {
        @set_time_limit(0);
        @ini_set('max_execution_time', '0');
    } else {
        $timeLimit = min(360, max(120, 95 * max(1, $batchSize)));
        @set_time_limit($timeLimit);
        @ini_set('max_execution_time', (string) $timeLimit);
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }

    $boot = import_image_pipeline_boot();
    if (empty($boot['ok'])) {
        return ['ok' => false, 'error' => (string)($boot['message'] ?? 'Pipeline indisponibil.')];
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
    $batchSize = max(1, (int)($state['batch_size'] ?? $meta['batch_size'] ?? import_image_job_batch_size()));

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

    $batchIds = array_slice($importIds, $offset, $batchSize);
    $batchIds = array_values(array_filter(array_map('intval', $batchIds)));
    $lastCode = '';

    $preProgress = $total > 0 ? round(($offset / $total) * 100, 1) : 0.0;
    if ($preProgress <= 0 && count($batchIds) > 0) {
        $preProgress = 5.0;
    }
    import_job_update($jobId, [
        'progress' => $preProgress,
        'message' => 'Scanare imagini: ' . $offset . ' / ' . $total
            . ' (batch ' . count($batchIds) . ', scraper_web + Ollama)...',
        'runner_heartbeat_at' => date('c'),
    ]);
    import_image_job_push_log(
        $jobId,
        'Batch ' . count($batchIds) . ' produs(e) — planuri: BD → ePiesa → TecDoc → Autodoc (+ Ollama)',
        'info'
    );

    $deltas = import_image_job_process_batch($pdo, $batchIds, $state, $jobId);
    $state = import_image_job_merge_deltas($state, $deltas);

    foreach ($deltas as $delta) {
        if (is_array($delta) && trim((string)($delta['code'] ?? '')) !== '') {
            $lastCode = (string) $delta['code'];
        }
    }

    $state['offset'] = $offset + count($batchIds);
    import_job_save_state($jobId, $state);

    $done = (int)$state['offset'] >= $total || !empty($state['stop_early']);
    $progress = $total > 0 ? round(((int)$state['offset'] / $total) * 100, 1) : 100.0;
    $message = $done
        ? 'Finalizare scanare imagini...'
        : 'Scanare imagini: ' . (int)$state['offset'] . ' / ' . $total
            . ($lastCode !== '' ? ' (' . $lastCode . ')' : '');

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
            'batch_size' => $batchSize,
        ],
    ];
}
