<?php

declare(strict_types=1);

namespace Evasystem\Controllers\Furnizori;

/**
 * Raport sincronizare zilnica per furnizor (feed FTP/API pe server).
 */
final class FurnizoriDailySyncReportService
{
    private const STATE_FILE = '/storage/supplier_sync_agent_state.json';

    private ?array $agentStateCache = null;

    /** @var array<int, array<string, mixed>>|null */
    private ?array $importFilesCache = null;

    /** @return array<string, mixed> */
    public static function emptyReport(): array
    {
        return [
            'sync_today' => false,
            'sync_today_label' => 'NU',
            'files_ready' => false,
            'files_ready_label' => 'NU',
            'sync_last_at' => null,
            'sync_last_at_label' => '—',
            'sync_source' => '',
            'sync_source_label' => '—',
            'sync_files' => [],
            'sync_files_label' => 'Niciun fisier',
            'sync_files_count' => 0,
        ];
    }

    /** @return array<string, array<string, mixed>> */
    public function buildAllReports(): array
    {
        if (!function_exists('import_furnizori_catalog_codes')) {
            return [];
        }

        $catalog = function_exists('import_furnizori_catalog') ? import_furnizori_catalog() : [];
        $reports = [];
        foreach (import_furnizori_catalog_codes() as $code) {
            $normalized = $this->normalizeCode((string) $code);
            if ($normalized === '') {
                continue;
            }
            $definition = is_array($catalog[$normalized] ?? null) ? $catalog[$normalized] : [];
            $reports[$normalized] = $this->buildReportForCode($normalized, [
                'connection_type' => (string) ($definition['connection_type'] ?? ''),
            ]);
        }

        return $reports;
    }

    /**
     * @param array<string, mixed> $furnizorRow
     * @return array<string, mixed>
     */
    public function buildReportForCode(string $supplierCode, array $furnizorRow = []): array
    {
        $code = $this->normalizeCode($supplierCode);
        if ($code === '') {
            return self::emptyReport();
        }

        $connectionType = strtolower(trim((string) ($furnizorRow['connection_type'] ?? '')));
        $agentEntry = $this->loadAgentState()[$code] ?? [];
        $files = $this->collectFilesForSupplier($code, $agentEntry, $connectionType, $furnizorRow);

        if ($files === []) {
            $report = self::emptyReport();
            $report['sync_source'] = $connectionType;
            $report['sync_source_label'] = $this->resolveSourceLabel($connectionType, (string) ($agentEntry['source'] ?? ''));

            return $report;
        }

        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
        $syncToday = false;
        $lastTimestamp = 0;

        foreach ($files as $file) {
            $receivedAt = (string) ($file['received_at'] ?? '');
            if ($receivedAt === '') {
                continue;
            }
            $timestamp = strtotime($receivedAt) ?: 0;
            if ($timestamp > $lastTimestamp) {
                $lastTimestamp = $timestamp;
            }
            if (substr($receivedAt, 0, 10) === $today) {
                $syncToday = true;
            }
        }

        $lastAt = $lastTimestamp > 0 ? date('Y-m-d H:i:s', $lastTimestamp) : null;
        $fileNames = array_values(array_filter(array_map(
            static fn (array $file): string => trim((string) ($file['name'] ?? '')),
            $files
        )));
        $filesReady = $files !== [];
        $primarySource = (string) ($files[0]['source'] ?? '');
        $primarySourceLabel = (string) ($files[0]['source_label'] ?? '');
        if ($primarySourceLabel === '') {
            $primarySourceLabel = $this->resolveSourceLabel(
                $connectionType,
                (string) ($agentEntry['source'] ?? '')
            );
        }

        return [
            'sync_today' => $syncToday,
            'sync_today_label' => $syncToday ? 'DA' : 'NU',
            'files_ready' => $filesReady,
            'files_ready_label' => $filesReady ? 'DA' : 'NU',
            'sync_last_at' => $lastAt,
            'sync_last_at_label' => $lastAt !== null ? $this->formatDateTimeLabel($lastAt) : '—',
            'sync_source' => $primarySource !== '' ? $primarySource : ($connectionType !== '' ? $connectionType : (string) ($agentEntry['source'] ?? '')),
            'sync_source_label' => $primarySourceLabel,
            'sync_files' => $files,
            'sync_files_label' => $fileNames !== [] ? implode(', ', array_slice($fileNames, 0, 4)) : 'Niciun fisier',
            'sync_files_count' => count($files),
        ];
    }

    /** @return array<string, array<string, mixed>> */
    private function loadAgentState(): array
    {
        if ($this->agentStateCache !== null) {
            return $this->agentStateCache;
        }

        $path = $this->stateFilePath();
        if (!is_file($path)) {
            $this->agentStateCache = [];

            return $this->agentStateCache;
        }

        $raw = file_get_contents($path);
        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        $state = is_array($decoded) ? $decoded : [];
        $normalized = [];

        foreach ($state as $code => $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $normalized[$this->normalizeCode((string) $code)] = $entry;
        }

        $this->agentStateCache = $normalized;

        return $this->agentStateCache;
    }

    /** @return array<int, array<string, mixed>> */
    private function loadImportFiles(): array
    {
        if ($this->importFilesCache !== null) {
            return $this->importFilesCache;
        }

        $this->bootImportLibrary();
        $this->importFilesCache = function_exists('list_uploaded_import_files')
            ? list_uploaded_import_files()
            : [];

        return $this->importFilesCache;
    }

    /**
     * @param array<string, mixed> $agentEntry
     * @param array<string, mixed> $furnizorRow
     * @return array<int, array<string, mixed>>
     */
    private function collectFilesForSupplier(
        string $code,
        array $agentEntry,
        string $connectionType,
        array $furnizorRow = []
    ): array {
        $files = [];
        $seenNames = [];

        foreach ($this->loadImportFiles() as $meta) {
            if (empty($meta['completed'])) {
                continue;
            }

            $name = trim((string) ($meta['original_name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $kind = (string) ($meta['file_kind'] ?? $meta['resolved_role'] ?? '');
            if (!import_supplier_file_matches_code($code, $name, $kind)) {
                continue;
            }

            $receivedAt = trim((string) ($meta['updated_at'] ?? ''));
            $seenNames[strtolower($name)] = true;
            $files[] = [
                'name' => $name,
                'received_at' => $receivedAt,
                'received_at_label' => $receivedAt !== '' ? $this->formatDateTimeLabel($receivedAt) : '—',
                'source' => $connectionType !== '' ? $connectionType : 'import',
                'source_label' => $this->resolveSourceLabel($connectionType, ''),
                'size' => (int) ($meta['size'] ?? 0),
                'file_id' => (string) ($meta['file_id'] ?? ''),
            ];
        }

        $agentName = trim((string) ($agentEntry['filename'] ?? ''));
        if ($agentName !== '' && !isset($seenNames[strtolower($agentName)])) {
            $syncedAt = $this->normalizeAgentTimestamp((string) ($agentEntry['synced_at'] ?? ''));
            $files[] = [
                'name' => $agentName,
                'received_at' => $syncedAt,
                'received_at_label' => $syncedAt !== '' ? $this->formatDateTimeLabel($syncedAt) : '—',
                'source' => (string) ($agentEntry['source'] ?? $connectionType),
                'source_label' => $this->resolveSourceLabel($connectionType, (string) ($agentEntry['source'] ?? '')),
                'size' => (int) ($agentEntry['size'] ?? 0),
                'file_id' => (string) ($agentEntry['file_id'] ?? ''),
            ];
        }

        foreach ($this->collectLocalFeedFiles($code, $furnizorRow) as $localFile) {
            $localName = strtolower(trim((string) ($localFile['name'] ?? '')));
            if ($localName === '' || isset($seenNames[$localName])) {
                continue;
            }
            $seenNames[$localName] = true;
            $files[] = $localFile;
        }

        usort($files, static function (array $a, array $b): int {
            return strcmp((string) ($b['received_at'] ?? ''), (string) ($a['received_at'] ?? ''));
        });

        return $files;
    }

    /**
     * @param array<string, mixed> $furnizorRow
     * @return array<int, array<string, mixed>>
     */
    private function collectLocalFeedFiles(string $code, array $furnizorRow): array
    {
        $randomnId = (int) ($furnizorRow['randomn_id'] ?? 0);
        $entries = (new SupplierFeedFolderService())->listFeedCsvFiles($code, $randomnId);
        $files = [];

        foreach ($entries as $entry) {
            $name = trim((string) ($entry['name'] ?? ''));
            if ($name === '' || !SupplierFeedFolderService::isFeedFilename($name)) {
                continue;
            }

            $mtime = (int) ($entry['mtime'] ?? 0);
            $receivedAt = $mtime > 0 ? date('Y-m-d H:i:s', $mtime) : '';
            $files[] = [
                'name' => $name,
                'received_at' => $receivedAt,
                'received_at_label' => $receivedAt !== '' ? $this->formatDateTimeLabel($receivedAt) : '—',
                'source' => 'local_feed',
                'source_label' => 'Folder local',
                'size' => (int) ($entry['size'] ?? 0),
                'file_id' => '',
            ];
        }

        return $files;
    }

    private function bootImportLibrary(): void
    {
        if (function_exists('list_uploaded_import_files')) {
            return;
        }

        require_once dirname(__DIR__) . '/Produse/import_uploaded_files_lib.php';
        if (!function_exists('import_supplier_file_matches_code')) {
            require_once dirname(__DIR__) . '/Produse/import_supplier_lib.php';
        }
    }

    private function stateFilePath(): string
    {
        return dirname(__DIR__, 3) . self::STATE_FILE;
    }

    private function normalizeCode(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        return function_exists('mb_strtoupper')
            ? mb_strtoupper($value, 'UTF-8')
            : strtoupper($value);
    }

    private function normalizeAgentTimestamp(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $timestamp = strtotime($value);

        return $timestamp !== false ? date('Y-m-d H:i:s', $timestamp) : $value;
    }

    private function formatDateTimeLabel(string $value): string
    {
        $timestamp = strtotime($value);

        return $timestamp !== false ? date('d.m.Y H:i', $timestamp) : $value;
    }

    private function resolveSourceLabel(string $connectionType, string $agentSource): string
    {
        $agentSource = strtolower(trim($agentSource));
        if ($agentSource === 'rclone') {
            return 'Rclone';
        }
        if ($agentSource === 'local_folder') {
            return 'Folder local';
        }
        if ($agentSource === 'local_feed') {
            return 'Folder local';
        }
        if ($agentSource === 'ftp') {
            return 'FTP';
        }

        return match (strtolower(trim($connectionType))) {
            'ftp' => 'FTP',
            'sftp' => 'SFTP',
            'api' => 'API',
            'email' => 'Email',
            default => $connectionType !== '' ? strtoupper($connectionType) : 'Sync',
        };
    }
}
