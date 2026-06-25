<?php

declare(strict_types=1);

namespace Evasystem\Controllers\Backup;

/**
 * Backup MySQL — mysqldump + retenție locală.
 */
final class BackupService
{
    private string $backupDir;
    private int $retentionDays;

    public function __construct(?string $backupDir = null, int $retentionDays = 7)
    {
        $this->backupDir = $backupDir ?: dirname(__DIR__, 2) . '/storage/backups';
        $this->retentionDays = max(1, min(30, $retentionDays));
        $this->ensureDirectory();
    }

    /** @return array<string, mixed> */
    public function stats(): array
    {
        $items = $this->listBackups();

        return [
            'directory' => $this->backupDir,
            'count' => count($items),
            'total_bytes' => array_sum(array_column($items, 'size_bytes')),
            'latest' => $items[0] ?? null,
            'retention_days' => $this->retentionDays,
            'mysqldump_path' => $this->resolveMysqldump(),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function listBackups(): array
    {
        $files = glob($this->backupDir . '/*.sql') ?: [];
        $files = array_merge($files, glob($this->backupDir . '/*.sql.gz') ?: []);

        $items = [];
        foreach ($files as $path) {
            if (!is_file($path)) {
                continue;
            }
            $items[] = [
                'filename' => basename($path),
                'path' => $path,
                'size_bytes' => (int) filesize($path),
                'size_human' => $this->formatBytes((int) filesize($path)),
                'created_at' => date('Y-m-d H:i:s', (int) filemtime($path)),
            ];
        }

        usort($items, static fn (array $a, array $b): int => strcmp((string) $b['created_at'], (string) $a['created_at']));

        return $items;
    }

    /** @return array<string, mixed> */
    public function runBackup(array $config): array
    {
        $mysqldump = $this->resolveMysqldump();
        if ($mysqldump === null) {
            throw new \RuntimeException('mysqldump nu a fost găsit. Setează MYSQLDUMP_PATH în admin/.env.');
        }

        $filename = 'besoiu_' . date('Y-m-d_His') . '.sql';
        $target = $this->backupDir . '/' . $filename;

        $host = (string) ($config['db_host'] ?? '127.0.0.1');
        $dbname = (string) ($config['db_name'] ?? '');
        $user = (string) ($config['db_user'] ?? '');
        $pass = (string) ($config['db_pass'] ?? '');

        if ($dbname === '' || $user === '') {
            throw new \RuntimeException('Configurare BD incompletă pentru backup.');
        }

        $command = sprintf(
            '%s --host=%s --user=%s %s --single-transaction --routines --triggers %s > %s',
            escapeshellarg($mysqldump),
            escapeshellarg($host),
            escapeshellarg($user),
            $pass !== '' ? '--password=' . escapeshellarg($pass) : '',
            escapeshellarg($dbname),
            escapeshellarg($target)
        );

        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);

        if ($exitCode !== 0 || !is_file($target) || filesize($target) < 128) {
            if (is_file($target)) {
                @unlink($target);
            }
            throw new \RuntimeException('Backup eșuat (mysqldump exit ' . $exitCode . ').');
        }

        $removed = $this->cleanupOldBackups();

        return [
            'filename' => $filename,
            'path' => $target,
            'size_bytes' => (int) filesize($target),
            'size_human' => $this->formatBytes((int) filesize($target)),
            'created_at' => date('Y-m-d H:i:s'),
            'removed_old' => $removed,
        ];
    }

    public function resolveBackupPath(string $filename): ?string
    {
        $safe = basename($filename);
        if ($safe === '' || !preg_match('/^besoiu_[0-9]{4}-[0-9]{2}-[0-9]{2}_[0-9]{6}\.sql(\.gz)?$/', $safe)) {
            return null;
        }

        $path = $this->backupDir . '/' . $safe;

        return is_file($path) ? $path : null;
    }

    private function ensureDirectory(): void
    {
        if (!is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0775, true);
        }
    }

    private function resolveMysqldump(): ?string
    {
        $candidates = [];

        $envPath = trim((string) ($_ENV['MYSQLDUMP_PATH'] ?? getenv('MYSQLDUMP_PATH') ?: ''));
        if ($envPath !== '') {
            $candidates[] = $envPath;
        }

        foreach (['E:/laragon/bin/mysql', 'C:/laragon/bin/mysql'] as $base) {
            foreach (glob($base . '/*/bin/mysqldump.exe') ?: [] as $path) {
                $candidates[] = $path;
            }
        }

        $candidates[] = 'mysqldump';
        $candidates[] = 'mysqldump.exe';

        foreach ($candidates as $candidate) {
            if ($candidate === 'mysqldump' || $candidate === 'mysqldump.exe') {
                $output = [];
                $exit = 0;
                exec('where ' . $candidate . ' 2>nul', $output, $exit);
                if ($exit === 0 && !empty($output[0]) && is_file($output[0])) {
                    return $output[0];
                }
                continue;
            }

            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function cleanupOldBackups(): int
    {
        $threshold = time() - ($this->retentionDays * 86400);
        $removed = 0;

        foreach ($this->listBackups() as $item) {
            $path = (string) ($item['path'] ?? '');
            if ($path === '' || !is_file($path)) {
                continue;
            }
            if (filemtime($path) < $threshold) {
                if (@unlink($path)) {
                    $removed++;
                }
            }
        }

        return $removed;
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2, '.', '') . ' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 2, '.', '') . ' KB';
        }

        return $bytes . ' B';
    }
}
