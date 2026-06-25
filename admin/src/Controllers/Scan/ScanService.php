<?php

declare(strict_types=1);

namespace Evasystem\Controllers\Scan;

use Config\Database;
use Evasystem\Controllers\Furnizori\FurnizoriDailySyncReportService;
use Evasystem\Controllers\Furnizori\FurnizoriRemoteSyncService;
use Evasystem\Controllers\Furnizori\FurnizoriStatsService;
use Evasystem\Controllers\Furnizori\SupplierFeedFolderService;
use Evasystem\Controllers\Furnizori\SupplierScanScheduleService;
use Evasystem\Core\Furnizori\FurnizoriModel;
use PDO;

require_once dirname(__DIR__) . '/Produse/import_supplier_lib.php';

/**
 * Panou scanare sincronizare furnizori — fișiere, validare CSV, modele noi.
 */
final class ScanService
{
    private const STATE_FILE = '/storage/supplier_scan_dashboard.json';
    private const RUN_LOCK_FILE = '/storage/supplier_scan_running.lock';
    private const CANCEL_FILE = '/storage/supplier_scan_cancel.flag';
    private const LAST_RUN_FILE = '/storage/supplier_scan_last_run.json';

    public function __construct(
        private readonly ?FurnizoriStatsService $statsService = null,
        private readonly ?SupplierCsvScanHelper $csvHelper = null,
        private readonly ?FurnizoriDailySyncReportService $syncReport = null,
        private readonly ?SupplierFeedFolderService $feedFolder = null,
        private readonly ?SupplierScanDashboardService $dashboardService = null,
    ) {
    }

    /**
     * @return array{
     *   overview:array<string,mixed>,
     *   suppliers:array<int,array<string,mixed>>,
     *   scanned_at:string,
     *   scanned_at_label:string
     * }
     */
    public function buildDashboard(bool $mirrorFiles = true, bool $analyzeCsv = true): array
    {
        $stats = $this->stats();
        $stats->ensureImportSuppliersSynced(true);

        $catalog = import_furnizori_catalog();
        $rows = $stats->listWithStats(false);
        $suppliers = [];
        $totals = [
            'suppliers' => 0,
            'files_ready' => 0,
            'validated_ok' => 0,
            'needs_attention' => 0,
            'new_models_total' => 0,
        ];

        foreach ($rows as $row) {
            $code = strtoupper(trim((string) ($row['code'] ?? '')));
            if ($code === '') {
                continue;
            }

            $randomnId = (int) ($row['randomn_id'] ?? 0);
            if ($mirrorFiles && $randomnId > 0) {
                $this->feed()->mirrorImportFilesForSupplier($code, $randomnId);
            }

            $supplier = $this->buildSupplierRow(
                $row,
                is_array($catalog[$code] ?? null) ? $catalog[$code] : [],
                $analyzeCsv
            );
            $suppliers[] = $supplier;

            ++$totals['suppliers'];
            if (!empty($supplier['files_ready'])) {
                ++$totals['files_ready'];
            }
            if (($supplier['validation_status'] ?? '') === 'ok') {
                ++$totals['validated_ok'];
            }
            if (in_array($supplier['validation_status'] ?? '', ['warn', 'error'], true)) {
                ++$totals['needs_attention'];
            }
            $totals['new_models_total'] += (int) ($supplier['new_models_count'] ?? 0);
        }

        usort($suppliers, static function (array $a, array $b): int {
            $prio = ['error' => 0, 'warn' => 1, 'pending' => 2, 'ok' => 3];
            $sa = $prio[$a['validation_status'] ?? 'pending'] ?? 9;
            $sb = $prio[$b['validation_status'] ?? 'pending'] ?? 9;
            if ($sa !== $sb) {
                return $sa <=> $sb;
            }

            return strcmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
        });

        $scannedAt = date('Y-m-d H:i:s');
        $dash = $this->dashboard();
        $payload = [
            'overview' => $totals,
            'suppliers' => $suppliers,
            'scanned_at' => $scannedAt,
            'scanned_at_label' => $this->formatDateTime($scannedAt),
            'cron_dashboard' => $dash->buildCronDashboard($suppliers),
        ];

        $this->saveState($payload);

        return $payload;
    }

    /**
     * Citește panoul salvat fără scanare / validare CSV (poll Cron Sync).
     *
     * @return array{
     *   overview:array<string,mixed>,
     *   suppliers:array<int,array<string,mixed>>,
     *   scanned_at:string,
     *   scanned_at_label:string,
     *   cron_dashboard:array<string,mixed>
     * }
     */
    public function loadSavedDashboard(): array
    {
        $path = dirname(__DIR__, 3) . self::STATE_FILE;
        $state = [];
        if (is_file($path)) {
            $decoded = json_decode((string) file_get_contents($path), true);
            if (is_array($decoded)) {
                $state = $decoded;
            }
        }

        $overview = is_array($state['overview'] ?? null)
            ? $state['overview']
            : $this->dashboard()->emptyOverview();
        $scannedAt = (string) ($state['scanned_at'] ?? '');

        $savedPipeline = is_array($state['supplier_pipeline'] ?? null) ? $state['supplier_pipeline'] : [];
        $savedCronDash = is_array($state['cron_dashboard'] ?? null) ? $state['cron_dashboard'] : null;

        if ($savedCronDash !== null) {
            $cronDashboard = $savedCronDash;
            $cronDashboard['activity'] = $this->dashboard()->listActivity(60);
            if ($savedPipeline !== []) {
                $cronDashboard['supplier_pipeline'] = $savedPipeline;
            }
        } else {
            $cronDashboard = $this->dashboard()->buildCronDashboard([]);
            if ($savedPipeline !== []) {
                $cronDashboard['supplier_pipeline'] = $savedPipeline;
            }
        }

        $suppliers = is_array($cronDashboard['supplier_pipeline'] ?? null)
            ? $cronDashboard['supplier_pipeline']
            : $savedPipeline;

        return [
            'overview' => $overview,
            'suppliers' => $suppliers,
            'scanned_at' => $scannedAt,
            'scanned_at_label' => $scannedAt !== '' ? $this->formatDateTime($scannedAt) : '—',
            'cron_dashboard' => $cronDashboard,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getActivityFeed(): array
    {
        return $this->dashboard()->listActivity(60);
    }

    public function isScanRunning(): bool
    {
        $path = $this->runLockPath();
        if (!is_file($path)) {
            return false;
        }

        require_once dirname(__DIR__, 3) . '/config/cron_import.php';

        $maxAge = admin_cron_scan_lock_max_age();
        $raw = (string) file_get_contents($path);
        $decoded = json_decode($raw, true);
        $startedAt = is_array($decoded) ? strtotime((string) ($decoded['started_at'] ?? '')) : false;
        $age = $startedAt > 0 ? (time() - $startedAt) : (time() - (int) filemtime($path));

        if ($age > $maxAge) {
            $this->releaseScanLock();

            return false;
        }

        $pid = is_array($decoded) ? (int) ($decoded['pid'] ?? 0) : 0;
        if ($pid > 0 && !$this->isProcessAlive($pid)) {
            $this->releaseScanLock();

            return false;
        }

        return true;
    }

    private function isProcessAlive(int $pid): bool
    {
        if ($pid <= 0) {
            return true;
        }

        if (PHP_OS_FAMILY === 'Windows') {
            $out = [];
            @exec('tasklist /FI "PID eq ' . $pid . '" /NH', $out, $code);

            return $code === 0 && isset($out[0]) && stripos($out[0], (string) $pid) !== false;
        }

        if (function_exists('posix_kill')) {
            return @posix_kill($pid, 0);
        }

        return true;
    }

    public function forceReleaseScanLock(): void
    {
        $this->releaseScanLock();
    }

    /** @deprecated folosește forceReleaseScanLock */
    public function releaseStaleScanLock(): bool
    {
        if (!is_file($this->runLockPath())) {
            return false;
        }

        $this->releaseScanLock();

        return true;
    }

    public function tryAcquireScanLock(): bool
    {
        if ($this->isScanRunning()) {
            return false;
        }

        $path = $this->runLockPath();
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $handle = fopen($path, 'c');
        if ($handle === false) {
            return false;
        }

        if (!flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);

            return false;
        }

        $payload = json_encode([
            'started_at' => date('c'),
            'pid' => getmypid(),
        ], JSON_UNESCAPED_UNICODE);

        if (ftruncate($handle, 0) === false || fwrite($handle, $payload) === false) {
            flock($handle, LOCK_UN);
            fclose($handle);
            @unlink($path);

            return false;
        }

        fflush($handle);
        flock($handle, LOCK_UN);
        fclose($handle);

        return true;
    }

    public function releaseScanLock(): void
    {
        $path = $this->runLockPath();
        if (is_file($path)) {
            @unlink($path);
        }
    }

    public static function isStopRequested(): bool
    {
        return is_file(dirname(__DIR__, 3) . self::CANCEL_FILE);
    }

    public static function clearStopRequest(): void
    {
        $path = dirname(__DIR__, 3) . self::CANCEL_FILE;
        if (is_file($path)) {
            @unlink($path);
        }
    }

    public static function requestStop(): void
    {
        $path = dirname(__DIR__, 3) . self::CANCEL_FILE;
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        @file_put_contents($path, json_encode([
            'requested_at' => date('c'),
        ], JSON_UNESCAPED_UNICODE), LOCK_EX);
    }

    public static function assertNotStopped(): void
    {
        if (self::isStopRequested()) {
            throw new ScanCancelledException('Scan oprit de utilizator.');
        }
    }

    /**
     * Oprește scanarea în curs: semnal cancel, termină procesul PHP, eliberează lock.
     *
     * @return array{success:bool,message:string,killed:bool,cancelled_jobs:int}
     */
    public function stopRunningScan(): array
    {
        $wasRunning = $this->isScanRunning();
        self::requestStop();

        $lock = $this->readLockPayload();
        $pid = (int) ($lock['pid'] ?? 0);
        $killed = $this->tryTerminateScanProcess($pid);
        $cancelledJobs = $this->cancelRunningImportJobs();

        $dash = $this->dashboard();
        $dash->log(
            'Scan oprit manual' . ($killed ? ' — proces terminat' : ($wasRunning ? ' — aștept oprire…' : '')),
            '',
            'warn',
            'run'
        );
        $dash->updateProgress([
            'running' => false,
            'pct' => 0,
            'phase' => 'stopped',
            'phase_label' => 'Oprit',
            'message' => 'Scan oprit de utilizator',
            'supplier' => '',
        ]);

        $this->releaseScanLock();

        if (!$wasRunning && !$killed && $cancelledJobs === 0) {
            return [
                'success' => true,
                'message' => 'Niciun scan activ — flag-ul de oprire a fost setat oricum.',
                'killed' => false,
                'cancelled_jobs' => 0,
            ];
        }

        return [
            'success' => true,
            'message' => 'Scan oprit.'
                . ($killed ? ' Procesul a fost întrerupt.' : '')
                . ($cancelledJobs > 0 ? ' ' . $cancelledJobs . ' job(uri) import anulate.' : ''),
            'killed' => $killed,
            'cancelled_jobs' => $cancelledJobs,
        ];
    }

    /** @return array<string, mixed> */
    private function readLockPayload(): array
    {
        $path = $this->runLockPath();
        if (!is_file($path)) {
            return [];
        }
        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    private function tryTerminateScanProcess(int $pid): bool
    {
        if ($pid <= 0) {
            return false;
        }

        if (PHP_OS_FAMILY === 'Windows') {
            $out = [];
            @exec('taskkill /PID ' . $pid . ' /F /T 2>&1', $out, $code);

            return $code === 0;
        }

        if (function_exists('posix_kill')) {
            if (@posix_kill($pid, 15)) {
                usleep(200000);
                if (@posix_kill($pid, 0)) {
                    @posix_kill($pid, 9);
                }

                return true;
            }
            @posix_kill($pid, 9);

            return true;
        }

        return false;
    }

    /** @param array<string, mixed> $result */
    public function saveLastRunResult(array $result): void
    {
        $path = dirname(__DIR__, 3) . self::LAST_RUN_FILE;
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        file_put_contents($path, json_encode(array_merge($result, [
            'finished_at' => date('c'),
        ]), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
    }

    /**
     * @return array{success:bool,message:string,data:array<string,mixed>}
     */
    public function runFullSync(bool $remoteFtp = false, bool $manageLock = true): array
    {
        if ($manageLock && !$this->tryAcquireScanLock()) {
            return [
                'success' => false,
                'message' => 'Un scan rulează deja. Așteaptă finalizarea în jurnal.',
                'data' => [],
            ];
        }

        try {
            self::clearStopRequest();

            return $this->runFullSyncBody($remoteFtp);
        } catch (ScanCancelledException $e) {
            $dash = $this->dashboard();
            $dash->log('Scan întrerupt — ' . $e->getMessage(), '', 'warn', 'run');
            $dash->updateProgress([
                'running' => false,
                'pct' => 0,
                'phase' => 'stopped',
                'phase_label' => 'Oprit',
                'message' => 'Scan oprit de utilizator',
            ]);

            return [
                'success' => false,
                'message' => 'Scan oprit de utilizator.',
                'data' => [],
                'cancelled' => true,
            ];
        } finally {
            self::clearStopRequest();
            if ($manageLock) {
                $this->releaseScanLock();
            }
        }
    }

    /**
     * @return array{success:bool,message:string,data:array<string,mixed>}
     */
    private function runFullSyncBody(bool $remoteFtp = false): array
    {
        @set_time_limit(0);

        $dash = $this->dashboard();
        $modeLabel = $remoteFtp ? 'Sync FTP + validare + import' : 'Scan local (folder) + validare + import';
        $dash->updateProgress([
            'running' => true,
            'pct' => 5,
            'phase' => 'run',
            'phase_label' => 'Pornire',
            'message' => $modeLabel,
            'supplier' => '',
            'supplier_index' => 0,
            'supplier_total' => 0,
        ]);
        $dash->log(
            'Pornire: ' . $modeLabel,
            '',
            'info',
            'run',
            'Pași: ① folder local → ② FTP (opțional) → ③ validare CSV → ④ import consumabile (ulei/lichide/electrice) → vitrină'
        );

        $stats = $this->stats();
        $stats->ensureImportSuppliersSynced(true);
        $supplierRows = $stats->listWithStats(false);
        $supplierTotal = count($supplierRows);
        $dash->updateProgress([
            'pct' => 8,
            'supplier_total' => $supplierTotal,
            'message' => 'Furnizori în catalog: ' . $supplierTotal,
        ]);
        $dash->log('Furnizori în catalog: ' . $supplierTotal, '', 'info', 'run');

        $syncedRemote = 0;
        $mirrored = 0;
        $errors = [];
        $ftpWarnings = [];
        $supplierIndex = 0;

        foreach ($supplierRows as $row) {
            self::assertNotStopped();

            $code = strtoupper(trim((string) ($row['code'] ?? '')));
            $randomnId = (int) ($row['randomn_id'] ?? 0);
            if ($code === '' || $randomnId <= 0) {
                continue;
            }

            ++$supplierIndex;
            $folderPct = 10 + (int) round(20 * ($supplierIndex / max(1, $supplierTotal)));
            $dash->updateProgress([
                'pct' => $folderPct,
                'phase' => $remoteFtp ? 'ftp' : 'validate',
                'phase_label' => $remoteFtp ? 'Foldere + FTP' : 'Foldere locale',
                'message' => 'Verific CSV pentru ' . $code,
                'supplier' => $code,
                'supplier_index' => $supplierIndex,
                'supplier_total' => $supplierTotal,
            ]);

            $folderRel = $this->feed()->folderRelative($code, $randomnId);
            $folderAbs = $this->feed()->folderPath($code, $randomnId);

            $dash->log(
                'Pas 1 — verific folder local',
                $code,
                'info',
                'validate',
                'Cale: admin/' . $folderRel
            );

            $mirror = $this->feed()->mirrorImportFilesForSupplier($code, $randomnId);
            $copied = $mirror['copied'] ?? [];
            $mirrored += count($copied);
            if ($copied !== []) {
                $dash->log(
                    'Copiat din staging: ' . implode(', ', $copied),
                    $code,
                    'ok',
                    'validate',
                    $folderAbs
                );
            }

            $localCsv = $this->feed()->listFeedCsvFiles($code, $randomnId);
            if ($localCsv !== []) {
                $names = array_map(
                    static fn (array $f): string => (string) ($f['name'] ?? ''),
                    array_slice($localCsv, 0, 4)
                );
                $latest = $localCsv[0] ?? [];
                $sizeKb = round(((int) ($latest['size'] ?? 0)) / 1024, 1);
                $dash->log(
                    count($localCsv) . ' CSV găsit(e): ' . implode(', ', $names),
                    $code,
                    'ok',
                    'validate',
                    'admin/' . $folderRel . ' · ultimul: ' . $sizeKb . ' KB'
                );
            } else {
                $dash->log(
                    'Folder gol — lipsă CSV',
                    $code,
                    'warn',
                    'validate',
                    'Încarcă în admin/' . $folderRel . ' sau configurează FTP'
                );
            }

            if (!$remoteFtp) {
                continue;
            }

            $connection = strtolower(trim((string) ($row['connection_type'] ?? '')));
            if (!in_array($connection, ['ftp', 'sftp'], true)) {
                $dash->log(
                    'FTP sărit — tip conexiune: ' . ($connection !== '' ? strtoupper($connection) : 'manual'),
                    $code,
                    'info',
                    'ftp'
                );
                continue;
            }

            $host = trim((string) ($row['conn_host'] ?? ''));
            if ($host === '') {
                if ($localCsv === []) {
                    $ftpWarnings[] = $code . ': Host FTP/SFTP neconfigurat.';
                    $dash->log('FTP sărit — host lipsă în profil', $code, 'warn', 'ftp');
                }
                continue;
            }

            $port = (int) ($row['conn_port'] ?? ($connection === 'sftp' ? 22 : 21));
            $remotePath = trim((string) ($row['conn_remote_path'] ?? ''));
            $remoteLabel = $remotePath !== '' ? $remotePath : ($connection === 'sftp' ? '(setează cale SFTP)' : '/');
            $user = trim((string) ($row['conn_username'] ?? ''));
            $proto = $connection === 'sftp' ? 'SFTP' : 'FTP';

            try {
                $dash->log(
                    'Pas 2 — conectare ' . $proto,
                    $code,
                    'info',
                    'ftp',
                    $proto . ' ' . $host . ':' . $port . ' · user ' . ($user !== '' ? $user : '—') . ' · dir ' . $remoteLabel
                );

                $t0 = microtime(true);
                $result = (new FurnizoriRemoteSyncService())->syncFromFtp($row);
                $elapsed = (int) round((microtime(true) - $t0) * 1000);
                $ftpDetail = $this->formatFtpDebugDetail($result['debug'] ?? [], $elapsed);

                if (!empty($result['success'])) {
                    ++$syncedRemote;
                    $this->feed()->mirrorImportFilesForSupplier($code, $randomnId);
                    $dash->log(
                        (string) ($result['message'] ?? 'Sync FTP reușit'),
                        $code,
                        'ok',
                        'ftp',
                        $ftpDetail
                    );
                } else {
                    $msg = (string) ($result['message'] ?? 'sync esuat');
                    if ($localCsv !== []) {
                        $dash->log('FTP eșuat — folosesc CSV local', $code, 'warn', 'ftp', $msg . ' | ' . $ftpDetail);
                        $ftpWarnings[] = $code . ': ' . $msg;
                    } else {
                        $errors[] = $code . ': ' . $msg;
                        $dash->log($msg, $code, 'error', 'ftp', $ftpDetail);
                    }
                }
            } catch (\Throwable $e) {
                $msg = $e->getMessage();
                if ($localCsv !== []) {
                    $dash->log('FTP excepție — folosesc CSV local', $code, 'warn', 'ftp', $msg);
                    $ftpWarnings[] = $code . ': ' . $msg;
                } else {
                    $errors[] = $code . ': ' . $msg;
                    $dash->log('FTP excepție: ' . $msg, $code, 'error', 'ftp');
                }
            }
        }

        self::assertNotStopped();

        $dash->updateProgress([
            'pct' => 32,
            'phase' => 'validate',
            'phase_label' => 'Validare CSV',
            'message' => 'Analizez structura fișierelor furnizor…',
            'supplier' => '',
        ]);
        $dash->log('Pas 3 — validare structură CSV', '', 'info', 'validate');
        $dashboard = $this->buildDashboard(true, true);
        $this->touchLastScanAll();

        foreach ($dashboard['suppliers'] ?? [] as $supplierRow) {
            if (!is_array($supplierRow)) {
                continue;
            }
            $code = strtoupper(trim((string) ($supplierRow['supplier_code'] ?? $supplierRow['code'] ?? '')));
            if ($code === '') {
                continue;
            }
            $lvl = (string) ($supplierRow['validation_status'] ?? 'info');
            if (!in_array($lvl, ['ok', 'warn', 'error'], true)) {
                $lvl = 'info';
            }
            $file = trim((string) ($supplierRow['scan_file'] ?? ''));
            $found = trim((string) ($supplierRow['validation_detail'] ?? ''));
            $msg = (string) ($supplierRow['validation_label'] ?? 'Scanat');
            if ($file !== '') {
                $msg .= ' — ' . $file;
            }
            if ($found !== '') {
                $msg .= ' · ' . $found;
            }
            $dash->log($msg, $code, $lvl, 'validate');
        }

        self::assertNotStopped();

        $dash->updateProgress([
            'pct' => 38,
            'phase' => 'sync',
            'phase_label' => 'Import produse',
            'message' => 'Pornesc import dual (vitrină + catalog)…',
            'supplier' => '',
        ]);

        $importResult = (new SupplierCronImportService())->run(
            is_array($dashboard['suppliers'] ?? null) ? $dashboard['suppliers'] : [],
            $dash,
            true
        );
        $dash->log('Pas 4 — import consumabile (ulei · lichide · electrice) + vitrină', '', 'info', 'sync', (string) ($importResult['import_message'] ?? ''));
        $dashboard['import_summary'] = $importResult['import_summary'] ?? [];
        $dashboard['import_message'] = (string) ($importResult['import_message'] ?? '');
        $dashboard['cron_dashboard'] = $dash->buildCronDashboard(
            is_array($dashboard['suppliers'] ?? null) ? $dashboard['suppliers'] : []
        );
        if (is_array($dashboard['cron_dashboard'])) {
            $dashboard['cron_dashboard']['import_summary'] = $dashboard['import_summary'];
            $dashboard['cron_dashboard']['import_message'] = $dashboard['import_message'];
        }

        $message = 'Scan complet: ' . $mirrored . ' fisier(e) copiate local';
        if ($remoteFtp) {
            $message .= ', ' . $syncedRemote . ' sync FTP/SFTP';
        }
        $filesReady = (int) ($dashboard['overview']['files_ready'] ?? 0);
        $published = (int) (($importResult['import_summary']['published'] ?? 0));
        if ($errors !== []) {
            $message .= '. Erori: ' . implode('; ', array_slice($errors, 0, 3));
        } elseif ($ftpWarnings !== [] && $remoteFtp) {
            $message .= '. FTP (fișiere locale folosite): ' . implode('; ', array_slice($ftpWarnings, 0, 2));
        }
        if (!empty($importResult['import_message'])) {
            $message .= ' | ' . $importResult['import_message'];
        }
        if ($filesReady === 0 && $published === 0) {
            $message .= ' | Pune CSV în admin/storage/supplier_feeds/{cod}/ apoi «Scanează furnizori» (fără FTP).';
        }

        $dash->updateProgress([
            'running' => false,
            'pct' => 100,
            'phase' => 'done',
            'phase_label' => 'Finalizat',
            'message' => $message,
            'supplier' => '',
        ]);

        return [
            'success' => $filesReady > 0 || $published > 0 || $mirrored > 0 || $syncedRemote > 0 || ($errors === [] && !$remoteFtp),
            'message' => $message,
            'data' => $dashboard,
            'import_summary' => $importResult['import_summary'] ?? [],
            'import_message' => $importResult['import_message'] ?? '',
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function getAllScans(): array
    {
        $saved = $this->loadSavedDashboard();

        return is_array($saved['suppliers'] ?? null) ? $saved['suppliers'] : [];
    }

    /** @param array<string, mixed> $row @param array<string, mixed> $catalogDef */
    private function buildSupplierRow(array $row, array $catalogDef, bool $analyzeCsv): array
    {
        $code = strtoupper(trim((string) ($row['code'] ?? '')));
        $randomnId = (int) ($row['randomn_id'] ?? 0);
        $report = $this->syncReports()->buildReportForCode($code, $row);

        $files = is_array($report['sync_files'] ?? null) ? $report['sync_files'] : [];
        $filesReady = (bool) ($report['files_ready'] ?? false) || $files !== [];
        $latestFile = $this->resolveLatestFilePath($code, $randomnId, $files);

        $validation = [
            'validation_status' => $filesReady ? 'pending' : 'error',
            'validation_label' => $filesReady ? 'In asteptare' : 'Fara fisier',
            'validation_detail' => $filesReady ? 'Fisier gasit — ruleaza validarea.' : 'Niciun CSV in folder local sau import.',
            'rows_sampled' => 0,
            'price_column_found' => false,
            'new_marci' => [],
            'new_modele' => [],
            'new_models_count' => 0,
            'new_models_label' => '—',
            'scan_file' => '',
        ];

        if ($analyzeCsv && $latestFile !== null) {
            $analysis = $this->csv()->analyzeFile(
                $latestFile,
                $code,
                (string) ($catalogDef['price_columns'] ?? $row['price_columns'] ?? '')
            );
            $validation = array_merge($validation, $this->mapAnalysisToValidation($analysis));
        } elseif (!$filesReady) {
            $connection = strtolower(trim((string) ($row['connection_type'] ?? '')));
            if (in_array($connection, ['ftp', 'sftp'], true) && trim((string) ($row['conn_host'] ?? '')) === '') {
                $validation['validation_detail'] = 'Configureaza host FTP/SFTP sau incarca manual CSV in folderul local.';
            }
        }

        $newModelsCount = (int) ($validation['new_models_count'] ?? 0);
        $newMarciCount = count($validation['new_marci'] ?? []);

        return array_merge($row, $report, $validation, [
            'supplier_code' => $code,
            'slug' => $this->feed()->slugFromSupplier($code, $randomnId),
            'files_ready' => $filesReady,
            'files_count' => (int) ($report['sync_files_count'] ?? count($files)),
            'connection_label' => $this->connectionLabel((string) ($row['connection_type'] ?? '')),
            'scan_interval_label' => SupplierScanScheduleService::formatLabel($row),
            'scan_schedule_label' => SupplierScanScheduleService::formatLabel($row),
            'profile_url' => $randomnId > 0 ? ('/admin/profilefurnizori?randomn_id=' . $randomnId) : '/admin/furnizori',
            'categorii_url' => '/admin/categorii',
            'import_url' => '/admin/importproduse',
            'new_marci_count' => $newMarciCount,
            'new_models_label' => $newModelsCount > 0
                ? ($newModelsCount . ' model(e) de adaugat')
                : ($newMarciCount > 0 ? ($newMarciCount . ' marca(i) noi') : '—'),
            'status_badge' => $this->statusBadge($validation['validation_status']),
        ]);
    }

    /** @param array<string, mixed> $analysis @return array<string, mixed> */
    private function mapAnalysisToValidation(array $analysis): array
    {
        $ok = (bool) ($analysis['ok'] ?? false);
        $newModele = is_array($analysis['new_modele'] ?? null) ? $analysis['new_modele'] : [];
        $newMarci = is_array($analysis['new_marci'] ?? null) ? $analysis['new_marci'] : [];
        $newCount = count($newModele);

        $status = 'ok';
        $label = 'Validat';
        $detail = (string) ($analysis['message'] ?? '');

        if (!$ok) {
            $status = 'error';
            $label = 'Eroare validare';
        } elseif ($newCount > 0 || $newMarci !== []) {
            $status = 'warn';
            $label = 'Modele noi';
            $detail = $newCount > 0
                ? ('Au fost gasite ' . $newCount . ' modele noi fata de catalog.')
                : ('Au fost gasite ' . count($newMarci) . ' marci noi fata de CMS.');
        } elseif (!($analysis['price_column_found'] ?? false)) {
            $status = 'warn';
            $label = 'Fara coloana pret';
            $detail = 'CSV citit, dar coloana de pret nu a fost detectata automat.';
        }

        return [
            'validation_status' => $status,
            'validation_label' => $label,
            'validation_detail' => $detail,
            'rows_sampled' => (int) ($analysis['rows_sampled'] ?? 0),
            'price_column_found' => (bool) ($analysis['price_column_found'] ?? false),
            'new_marci' => $newMarci,
            'new_modele' => $newModele,
            'new_models_count' => $newCount,
            'new_models_label' => $newCount > 0 ? ($newCount . ' model(e)') : '—',
            'scan_file' => (string) ($analysis['file'] ?? ''),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $files
     */
    private function resolveLatestFilePath(string $code, int $randomnId, array $files): ?string
    {
        if (!function_exists('list_uploaded_import_files')) {
            require_once dirname(__DIR__) . '/Produse/import_uploaded_files_lib.php';
        }
        if (!function_exists('import_supplier_file_matches_code')) {
            require_once dirname(__DIR__) . '/Produse/import_supplier_lib.php';
        }

        $candidates = [];

        foreach ($this->feed()->listFeedCsvFiles($code, $randomnId) as $entry) {
            $path = (string) ($entry['local_path'] ?? '');
            $name = (string) ($entry['name'] ?? basename($path));
            if ($path === '' || !is_file($path) || !$this->isAnalyzableFeedFile($code, $name)) {
                continue;
            }
            $candidates[] = [
                'path' => $path,
                'size' => (int) (filesize($path) ?: 0),
                'mtime' => (int) (filemtime($path) ?: 0),
            ];
        }

        foreach ($files as $file) {
            $name = (string) ($file['name'] ?? '');
            if ($name !== '' && !$this->isAnalyzableFeedFile($code, $name)) {
                continue;
            }
            $fileId = trim((string) ($file['file_id'] ?? ''));
            if ($fileId !== '' && function_exists('import_upload_temp_file_path')) {
                $path = import_upload_temp_file_path($fileId);
                if (is_file($path)) {
                    $candidates[] = [
                        'path' => $path,
                        'size' => (int) (filesize($path) ?: 0),
                        'mtime' => (int) (filemtime($path) ?: 0),
                    ];
                }
            }
        }

        if ($candidates === []) {
            return null;
        }

        usort($candidates, static function (array $a, array $b): int {
            $csvA = str_ends_with(strtolower($a['path']), '.csv') ? 1 : 0;
            $csvB = str_ends_with(strtolower($b['path']), '.csv') ? 1 : 0;
            if ($csvA !== $csvB) {
                return $csvB <=> $csvA;
            }
            if ($a['size'] !== $b['size']) {
                return $b['size'] <=> $a['size'];
            }

            return $b['mtime'] <=> $a['mtime'];
        });

        return (string) ($candidates[0]['path'] ?? '');
    }

    private function isAnalyzableFeedFile(string $code, string $filename): bool
    {
        $lower = strtolower($filename);
        if (!str_ends_with($lower, '.csv') && !str_ends_with($lower, '.txt')) {
            return false;
        }
        if ($code === 'AUTONET' && str_contains($lower, 'autopartner')) {
            return false;
        }
        if ($code === 'AUTOPARTNER' && str_contains($lower, 'autonet') && !str_contains($lower, 'autopartner')) {
            return false;
        }

        return import_supplier_file_matches_code($code, $filename, 'supplier:' . $code)
            || import_supplier_file_matches_code($code, $filename, '');
    }

    private function connectionLabel(string $type): string
    {
        return match (strtolower(trim($type))) {
            'sftp' => 'SFTP',
            'ftp' => 'FTP',
            'email' => 'Email',
            'api' => 'API',
            'local' => 'Local',
            default => '—',
        };
    }

    private function formatInterval(int $minutes): string
    {
        if ($minutes <= 0) {
            return '—';
        }
        if ($minutes % 1440 === 0) {
            return (int) ($minutes / 1440) . ' zile';
        }
        if ($minutes % 60 === 0) {
            return (int) ($minutes / 60) . ' ore';
        }

        return $minutes . ' min';
    }

    private function statusBadge(string $status): string
    {
        return match ($status) {
            'ok' => 'ok',
            'warn' => 'warn',
            'error' => 'error',
            default => 'pending',
        };
    }

    private function formatDateTime(string $value): string
    {
        $ts = strtotime($value);

        return $ts ? date('d.m.Y H:i', $ts) : $value;
    }

    /** @param array<string, mixed> $payload */
    private function saveState(array $payload): void
    {
        $path = dirname(__DIR__, 3) . self::STATE_FILE;
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $cronDash = is_array($payload['cron_dashboard'] ?? null) ? $payload['cron_dashboard'] : [];
        $pipeline = is_array($cronDash['supplier_pipeline'] ?? null) ? $cronDash['supplier_pipeline'] : [];

        @file_put_contents($path, json_encode([
            'scanned_at' => $payload['scanned_at'] ?? '',
            'overview' => $payload['overview'] ?? [],
            'supplier_pipeline' => $pipeline,
            'cron_dashboard' => $cronDash,
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
    }

    private function touchLastScanAll(): void
    {
        try {
            $pdo = Database::getDB();
            $now = date('Y-m-d H:i:s');
            $pdo->exec(
                "UPDATE furnizori SET last_scan_at = " . $pdo->quote($now) . ",
                 last_scan_status = 'ok',
                 last_scan_message = 'Scan panou furnizori'"
            );
        } catch (\Throwable) {
            // optional columns
        }
    }

    private function stats(): FurnizoriStatsService
    {
        return $this->statsService ?? new FurnizoriStatsService(new FurnizoriModel());
    }

    private function csv(): SupplierCsvScanHelper
    {
        return $this->csvHelper ?? new SupplierCsvScanHelper();
    }

    private function syncReports(): FurnizoriDailySyncReportService
    {
        return $this->syncReport ?? new FurnizoriDailySyncReportService();
    }

    private function feed(): SupplierFeedFolderService
    {
        return $this->feedFolder ?? new SupplierFeedFolderService();
    }

    private function dashboard(): SupplierScanDashboardService
    {
        return $this->dashboardService ?? new SupplierScanDashboardService();
    }

    /** @param array<string, mixed> $debug */
    private function formatFtpDebugDetail(array $debug, int $elapsedMs): string
    {
        $parts = [];
        if ($elapsedMs > 0) {
            $parts[] = $elapsedMs . ' ms';
        }
        $found = (int) ($debug['candidates'] ?? 0);
        if ($found > 0) {
            $parts[] = $found . ' CSV pe server';
        }
        $names = is_array($debug['candidate_names'] ?? null) ? $debug['candidate_names'] : [];
        if ($names !== []) {
            $parts[] = 'listă: ' . implode(', ', array_slice($names, 0, 3));
        }
        $downloaded = is_array($debug['downloaded'] ?? null) ? $debug['downloaded'] : [];
        if ($downloaded !== []) {
            $parts[] = 'descărcat: ' . implode(', ', $downloaded);
        }

        return $parts !== [] ? implode(' · ', $parts) : 'fără detalii suplimentare';
    }

    /**
     * Oprește scanările automate, golește jurnalul și resetează panoul Cron la zero.
     *
     * @return array<string, mixed>
     */
    public function resetForTesting(bool $clearFeedFiles = false): array
    {
        $this->releaseScanLock();
        self::clearStopRequest();

        $cancelledJobs = $this->cancelRunningImportJobs();
        $feedsDeleted = $clearFeedFiles ? $this->clearSupplierFeedFiles() : 0;
        $this->stopAutoScansAndClearFurnizoriState();
        $queueCleared = $this->clearPendingQueueJobs();

        $dash = $this->dashboard();
        $dash->resetStateFiles();

        $overview = $dash->emptyOverview();
        $this->saveState([
            'scanned_at' => '',
            'overview' => $overview,
            'cron_dashboard' => $dash->buildCronDashboard([]),
        ]);

        $hint = $clearFeedFiles
            ? ' CSV-urile din supplier_feeds au fost șterse.'
            : ' CSV-urile din supplier_feeds au fost păstrate.';

        return [
            'success' => true,
            'message' => 'Cron resetat: scanări oprite, jurnal golit, statistici la zero.' . $hint,
            'cancelled_jobs' => $cancelledJobs,
            'feeds_deleted' => $feedsDeleted,
            'queue_cleared' => $queueCleared,
            'overview' => $overview,
            'cron_dashboard' => $dash->buildCronDashboard([]),
        ];
    }

    private function stopAutoScansAndClearFurnizoriState(): void
    {
        try {
            $pdo = Database::getDB();
            $pdo->exec(
                'UPDATE furnizori SET
                    scan_auto_enabled = 0,
                    last_scan_at = NULL,
                    last_scan_status = NULL,
                    last_scan_message = NULL,
                    products_count = 0'
            );
        } catch (\Throwable) {
            // optional
        }
    }

    private function clearPendingQueueJobs(): int
    {
        try {
            $pdo = Database::getDB();
            return (int) $pdo->exec(
                "DELETE FROM queue_jobs WHERE status IN ('pending', 'processing')"
            );
        } catch (\Throwable) {
            return 0;
        }
    }

    private function cancelRunningImportJobs(): int
    {
        $jobsDir = dirname(__DIR__, 3) . '/storage/imports/jobs';
        if (!is_dir($jobsDir)) {
            return 0;
        }

        $cancelled = 0;
        foreach (glob($jobsDir . '/*.json') ?: [] as $path) {
            if (str_ends_with($path, '.state.json')) {
                continue;
            }
            $meta = json_decode((string) file_get_contents($path), true);
            if (!is_array($meta) || ($meta['status'] ?? '') !== 'running') {
                continue;
            }
            $meta['status'] = 'cancelled';
            $meta['cancelled_at'] = date('c');
            if (@file_put_contents($path, json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) !== false) {
                ++$cancelled;
            }
        }

        return $cancelled;
    }

    private function clearSupplierFeedFiles(): int
    {
        $base = dirname(__DIR__, 3) . '/storage/supplier_feeds';
        if (!is_dir($base)) {
            return 0;
        }

        $deleted = 0;
        foreach (scandir($base) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $dir = $base . DIRECTORY_SEPARATOR . $entry;
            if (!is_dir($dir)) {
                if ($entry !== '.gitkeep' && is_file($dir) && @unlink($dir)) {
                    ++$deleted;
                }
                continue;
            }
            foreach (scandir($dir) ?: [] as $file) {
                if ($file === '.' || $file === '..' || $file === '.gitkeep') {
                    continue;
                }
                $path = $dir . DIRECTORY_SEPARATOR . $file;
                if (is_file($path) && @unlink($path)) {
                    ++$deleted;
                }
            }
        }

        return $deleted;
    }

    private function runLockPath(): string
    {
        return dirname(__DIR__, 3) . self::RUN_LOCK_FILE;
    }
}
