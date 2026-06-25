<?php

declare(strict_types=1);

namespace Evasystem\Controllers\Scan;

use Evasystem\Controllers\Furnizori\SupplierScanScheduleService;

/**
 * Date pentru panoul Cron Sync + jurnal activitate scanări furnizori.
 */
final class SupplierScanDashboardService
{
    private const ACTIVITY_FILE = '/storage/supplier_scan_activity.json';
    private const PROGRESS_FILE = '/storage/supplier_scan_progress.json';
    private const AGENT_STATE = '/storage/supplier_sync_agent_state.json';
    private const SCAN_STATE = '/storage/supplier_scan_dashboard.json';
    private const MAX_ACTIVITY = 80;

    /** @return array<string, mixed> */
    public function buildCronDashboard(array $suppliers = []): array
    {
        require_once dirname(__DIR__, 3) . '/config/cron_tasks.php';

        $tasks = admin_cron_dashboard_tasks();
        $modules = admin_cron_modules_registry();
        $agentState = $this->loadJsonFile(self::AGENT_STATE);
        $scanState = $this->loadJsonFile(self::SCAN_STATE);
        $scriptsOk = $this->checkScriptHealth();

        $supplierJobs = [];
        $dueCount = 0;
        $nextRuns = [];
        $now = new \DateTimeImmutable('now');

        foreach ($suppliers as $row) {
            if (!is_array($row)) {
                continue;
            }
            $code = strtoupper(trim((string) ($row['code'] ?? $row['supplier_code'] ?? '')));
            if ($code === '') {
                continue;
            }

            $agent = is_array($agentState[$code] ?? null) ? $agentState[$code] : [];
            $status = (string) ($row['validation_status'] ?? 'pending');
            $progress = match ($status) {
                'ok' => 100,
                'warn' => 72,
                'error' => 28,
                default => 45,
            };

            $isDue = SupplierScanScheduleService::shouldRunAuto($row, $agent, $now);
            if ($isDue) {
                ++$dueCount;
            }

            $nextAt = SupplierScanScheduleService::estimateNextRunAt($row, $agent, $now);
            if ($nextAt instanceof \DateTimeImmutable) {
                $nextRuns[] = $nextAt;
            }

            $lastScanRaw = trim((string) ($row['last_scan_at'] ?? ''));
            if ($lastScanRaw === '' && trim((string) ($agent['synced_at'] ?? '')) !== '') {
                $lastScanRaw = (string) $agent['synced_at'];
            }

            $supplierJobs[] = [
                'code' => $code,
                'name' => (string) ($row['name'] ?? $code),
                'randomn_id' => (int) ($row['randomn_id'] ?? 0),
                'profile_url' => (string) ($row['profile_url'] ?? '/admin/furnizori'),
                'connection' => (string) ($row['connection_label'] ?? '—'),
                'schedule_label' => (string) ($row['scan_schedule_label'] ?? SupplierScanScheduleService::formatLabel($row)),
                'progress' => $progress,
                'status' => $status,
                'status_label' => (string) ($row['validation_label'] ?? '—'),
                'validation_detail' => (string) ($row['validation_detail'] ?? ''),
                'found_label' => $this->buildFoundLabel($row),
                'file' => (string) ($row['scan_file'] ?? ($agent['filename'] ?? '')),
                'files_count' => (int) ($row['files_count'] ?? 0),
                'synced_at' => (string) ($agent['synced_at'] ?? ''),
                'synced_at_label' => $this->formatIso((string) ($agent['synced_at'] ?? '')),
                'last_scan_label' => $this->formatDateTime($lastScanRaw),
                'next_scan_label' => SupplierScanScheduleService::formatNextRunLabel($row, $agent, $now),
                'is_due' => $isDue,
                'source' => (string) ($agent['source'] ?? ($row['sync_source_label'] ?? '')),
                'step' => $this->resolveSupplierStep($row, $agent),
                'new_models_label' => (string) ($row['new_models_label'] ?? '—'),
            ];
        }

        $cronRows = $tasks;
        $activity = $this->listActivity(60);

        return [
            'engine_online' => true,
            'engine_label' => 'Motor sincronizare activ',
            'last_scan_at' => (string) ($scanState['scanned_at'] ?? ''),
            'last_scan_label' => $this->formatDateTime((string) ($scanState['scanned_at'] ?? '')),
            'overview' => is_array($scanState['overview'] ?? null) ? $scanState['overview'] : [],
            'cron_tasks' => $cronRows,
            'cron_modules' => $modules,
            'supplier_pipeline' => $supplierJobs,
            'live_status' => $this->buildLiveStatus(
                $scanState,
                $supplierJobs,
                $dueCount,
                $nextRuns,
                $scriptsOk,
                $now,
                $cronRows,
                $activity !== []
            ),
            'activity' => $activity,
            'scripts_health' => $scriptsOk,
        ];
    }

    public function log(
        string $message,
        string $supplierCode = '',
        string $level = 'info',
        string $step = '',
        string $detail = ''
    ): void {
        $path = $this->activityPath();
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $items = $this->listActivity(self::MAX_ACTIVITY);
        $entry = [
            'at' => date('c'),
            'at_label' => date('d.m.Y H:i:s'),
            'message' => trim($message),
            'supplier' => strtoupper(trim($supplierCode)),
            'level' => $level,
            'step' => $step,
        ];
        $detail = trim($detail);
        if ($detail !== '') {
            $entry['detail'] = $detail;
        }
        array_unshift($items, $entry);
        $items = array_slice($items, 0, self::MAX_ACTIVITY);

        @file_put_contents($path, json_encode($items, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
    }

    /**
     * Progres structurat pentru bara live (Cron Sync).
     *
     * @param array<string, mixed> $data
     */
    public function updateProgress(array $data): void
    {
        $path = $this->progressPath();
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $current = $this->readProgress();
        $pct = isset($data['pct']) ? max(0, min(100, (int) $data['pct'])) : (int) ($current['pct'] ?? 0);
        $payload = array_merge($current, $data, [
            'pct' => $pct,
            'running' => (bool) ($data['running'] ?? true),
            'updated_at' => date('c'),
        ]);

        @file_put_contents($path, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
    }

    /** @return array<string, mixed> */
    public function readProgress(): array
    {
        $raw = $this->loadJsonFile(self::PROGRESS_FILE);
        if ($raw === []) {
            return [
                'running' => false,
                'pct' => 0,
                'phase' => '',
                'phase_label' => '',
                'message' => '',
                'supplier' => '',
                'supplier_index' => 0,
                'supplier_total' => 0,
            ];
        }

        return $raw;
    }

    public function clearProgress(): void
    {
        $path = $this->progressPath();
        if (is_file($path)) {
            @unlink($path);
        }
    }

    /** @return array<int, array<string, mixed>> */
    public function listActivity(int $limit = 40): array
    {
        $path = $this->activityPath();
        if (!is_file($path)) {
            return [];
        }

        $raw = $this->loadJsonFile(self::ACTIVITY_FILE);
        if (!is_array($raw)) {
            return [];
        }

        $items = [];
        foreach ($raw as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $items[] = $entry;
        }

        return array_slice($items, 0, max(1, $limit));
    }

    public function clearActivity(): void
    {
        $path = $this->activityPath();
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        @file_put_contents($path, "[]\n");
    }

    /** @return array<string, mixed> */
    public function emptyOverview(): array
    {
        return [
            'suppliers' => 0,
            'files_ready' => 0,
            'validated_ok' => 0,
            'needs_attention' => 0,
            'new_models_total' => 0,
        ];
    }

    public function resetStateFiles(): void
    {
        $base = dirname(__DIR__, 3) . '/storage';
        if (!is_dir($base)) {
            mkdir($base, 0775, true);
        }

        $this->clearActivity();
        $this->clearProgress();

        @file_put_contents(
            $base . '/supplier_sync_agent_state.json',
            "{}\n"
        );

        @file_put_contents(
            $base . '/supplier_cron_import_state.json',
            "{}\n"
        );

        @file_put_contents(
            $base . '/supplier_scan_dashboard.json',
            json_encode([
                'scanned_at' => '',
                'overview' => $this->emptyOverview(),
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );

        @unlink($base . '/supplier_scan_running.lock');
    }

    /** @return array<string, bool> */
    private function checkScriptHealth(): array
    {
        $base = dirname(__DIR__, 3);
        $files = [
            'supplier_rclone' => 'scripts/run_supplier_rclone_sync.bat',
            'supplier_sync' => 'scripts/run_supplier_sync.bat',
            'queue_worker' => 'scripts/run_queue_worker.bat',
            'dashboard_snapshot' => 'scripts/run_refresh_dashboard_snapshot.bat',
            'daily_backup' => 'scripts/run_daily_backup.bat',
        ];
        $out = [];
        foreach ($files as $key => $rel) {
            $out[$key] = is_file($base . '/' . str_replace('/', DIRECTORY_SEPARATOR, $rel));
        }

        return $out;
    }

    /** @param array<string, mixed> $row */
    private function buildFoundLabel(array $row): string
    {
        $status = (string) ($row['validation_status'] ?? 'pending');
        $detail = trim((string) ($row['validation_detail'] ?? ''));
        $file = trim((string) ($row['scan_file'] ?? ''));
        $filesCount = (int) ($row['files_count'] ?? 0);
        $rows = (int) ($row['rows_sampled'] ?? 0);
        $newModels = trim((string) ($row['new_models_label'] ?? ''));

        if ($status === 'ok') {
            $parts = ['CSV valid'];
            if ($rows > 0) {
                $parts[] = $rows . ' rânduri verificate';
            }
            if ($newModels !== '' && $newModels !== '—') {
                $parts[] = $newModels;
            }

            return implode(' · ', $parts);
        }

        if ($status === 'warn') {
            return $detail !== '' ? $detail : 'Validare cu avertismente';
        }

        if ($status === 'error') {
            return $detail !== '' ? $detail : 'Eroare la validare sau lipsă fișier';
        }

        if ($file !== '') {
            return 'Fișier: ' . $file . ($rows > 0 ? (' · ' . $rows . ' rânduri') : '');
        }

        if ($filesCount > 0) {
            return $filesCount . ' fișier(e) în folder — validare pending';
        }

        return 'Niciun CSV găsit încă';
    }

    /**
     * @param array<string, mixed> $scanState
     * @param array<int, array<string, mixed>> $supplierJobs
     * @param array<int, \DateTimeImmutable> $nextRuns
     * @param array<string, bool> $scriptsOk
     * @return array<string, mixed>
     */
    private function buildLiveStatus(
        array $scanState,
        array $supplierJobs,
        int $dueCount,
        array $nextRuns,
        array $scriptsOk,
        \DateTimeImmutable $now,
        array $cronTasks = [],
        bool $hasActivity = false
    ): array {
        $okCount = 0;
        $warnCount = 0;
        $errCount = 0;
        foreach ($supplierJobs as $job) {
            match ((string) ($job['status'] ?? 'pending')) {
                'ok' => ++$okCount,
                'warn' => ++$warnCount,
                'error' => ++$errCount,
                default => null,
            };
        }

        $earliestNext = null;
        foreach ($nextRuns as $dt) {
            if ($earliestNext === null || $dt < $earliestNext) {
                $earliestNext = $dt;
            }
        }

        $nextLabel = '—';
        if ($dueCount > 0) {
            $nextLabel = 'Acum (' . $dueCount . ' furnizor(i) programat(i))';
        } elseif ($earliestNext instanceof \DateTimeImmutable) {
            $diff = $earliestNext->getTimestamp() - $now->getTimestamp();
            if ($diff <= 90) {
                $nextLabel = 'În curând (' . $earliestNext->format('H:i') . ')';
            } else {
                $nextLabel = $earliestNext->format('d.m.Y H:i');
            }
        }

        $motorOk = $cronTasks !== [] && !empty($scriptsOk['supplier_rclone']);
        $lastScan = trim((string) ($scanState['scanned_at'] ?? ''));

        if ($cronTasks === [] && !$hasActivity && $lastScan === '') {
            $motorLabel = 'În așteptare — jurnal gol, fără joburi Task Scheduler';
            $summary = '—';
        } elseif ($cronTasks === []) {
            $motorLabel = 'Fără joburi Task Scheduler configurate în panou';
            $summary = $okCount . ' OK · ' . $warnCount . ' atenție · ' . $errCount . ' erori';
        } else {
            $motorLabel = $motorOk ? 'Motor cron activ pe server' : 'Verifică scripturile .bat pe server';
            $summary = $okCount . ' OK · ' . $warnCount . ' atenție · ' . $errCount . ' erori';
        }

        return [
            'last_scan_label' => $this->formatDateTime($lastScan),
            'next_scan_label' => $nextLabel,
            'suppliers_due' => $dueCount,
            'suppliers_total' => count($supplierJobs),
            'suppliers_ok' => $okCount,
            'suppliers_warn' => $warnCount,
            'suppliers_error' => $errCount,
            'motor_ok' => $motorOk,
            'motor_label' => $motorLabel,
            'summary' => $summary,
        ];
    }

    /** @param array<string, mixed> $row @param array<string, mixed> $agent */
    private function resolveSupplierStep(array $row, array $agent): string
    {
        if ((string) ($row['validation_status'] ?? '') === 'ok') {
            return 'validat · ' . (int) ($row['rows_sampled'] ?? 0) . ' rânduri';
        }
        if (!empty($row['files_ready'])) {
            return 'validare CSV';
        }
        if ($agent !== []) {
            return 'agent: ' . (string) ($agent['source'] ?? 'sync');
        }

        return 'așteaptă fișier';
    }

    private function normalizeBatchPath(string $batch): string
    {
        $batch = trim($batch);
        if ($batch === '' || str_starts_with($batch, '—')) {
            return '';
        }

        return preg_replace('#^admin/#', '', $batch) ?? $batch;
    }

  /** @return array<string, mixed>|array<int, mixed> */
    private function loadJsonFile(string $relative): array
    {
        $path = dirname(__DIR__, 3) . $relative;
        if (!is_file($path)) {
            return [];
        }
        $raw = file_get_contents($path);
        $decoded = is_string($raw) ? json_decode($raw, true) : null;

        return is_array($decoded) ? $decoded : [];
    }

    private function activityPath(): string
    {
        return dirname(__DIR__, 3) . self::ACTIVITY_FILE;
    }

    private function progressPath(): string
    {
        return dirname(__DIR__, 3) . self::PROGRESS_FILE;
    }

    private function formatIso(string $iso): string
    {
        if ($iso === '') {
            return '—';
        }
        $ts = strtotime($iso);

        return $ts ? date('d.m.Y H:i', $ts) : $iso;
    }

    private function formatDateTime(string $value): string
    {
        if ($value === '') {
            return '—';
        }
        $ts = strtotime($value);

        return $ts ? date('d.m.Y H:i', $ts) : $value;
    }
}
