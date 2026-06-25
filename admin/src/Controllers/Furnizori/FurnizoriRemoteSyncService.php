<?php

declare(strict_types=1);

namespace Evasystem\Controllers\Furnizori;

/**
 * Descarca fisiere CSV de pe FTP/SFTP si le salveaza in coada de import.
 */
final class FurnizoriRemoteSyncService
{
    private const STATE_FILE = '/storage/supplier_sync_agent_state.json';
    private const MAX_FILES = 3;

    /**
     * @param array<string, mixed> $furnizor
     * @return array{
     *   success:bool,
     *   message:string,
     *   files:array<int,array<string,mixed>>,
     *   debug?:array<string,mixed>
     * }
     */
    public function syncFromFtp(array $furnizor): array
    {
        $started = microtime(true);
        $this->bootImportLibrary();

        $furnizor = import_furnizori_resolve_credentials($furnizor);
        $code = $this->normalizeCode((string) ($furnizor['code'] ?? ''));
        $connectionType = strtolower(trim((string) ($furnizor['connection_type'] ?? 'ftp')));
        $port = (int) ($furnizor['conn_port'] ?? ($connectionType === 'sftp' ? 22 : 21));
        $user = trim((string) ($furnizor['conn_username'] ?? ''));

        $debug = [
            'code' => $code,
            'protocol' => $connectionType === 'sftp' ? 'SFTP' : 'FTP',
            'host' => trim((string) ($furnizor['conn_host'] ?? '')),
            'port' => $port,
            'user' => $user !== '' ? $user : '—',
            'remote_dir' => '',
            'candidates' => 0,
            'candidate_names' => [],
            'downloaded' => [],
            'elapsed_ms' => 0,
        ];

        if ($code === '') {
            return $this->fail('Cod furnizor lipsa.', $debug, $started);
        }

        $host = (string) $debug['host'];
        if ($host === '') {
            return $this->fail('Host FTP neconfigurat.', $debug, $started);
        }

        $remoteDir = trim((string) ($furnizor['conn_remote_path'] ?? ''));
        if ($remoteDir === '') {
            if ($connectionType === 'sftp') {
                return $this->fail(
                    'Cale SFTP neconfigurata — seteaza Folder remote in profil furnizor sau incarca CSV in folderul local.',
                    $debug,
                    $started
                );
            }
            $remoteDir = '/';
        }
        $debug['remote_dir'] = $remoteDir;

        $client = (new FtpConnectionClient())->configure($furnizor);
        $candidates = $client->pickRemoteFiles($remoteDir, '*.csv');
        $debug['candidates'] = count($candidates);
        $debug['candidate_names'] = array_values(array_map(
            static fn (array $c): string => (string) ($c['name'] ?? $c['remote_path'] ?? ''),
            array_slice($candidates, 0, 8)
        ));

        if ($candidates === []) {
            return $this->fail(
                'Niciun fisier CSV in ' . $remoteDir . ' pe ' . $debug['protocol'] . ' ' . $host . '.',
                $debug,
                $started
            );
        }

        $synced = [];
        foreach (array_slice($candidates, 0, self::MAX_FILES) as $candidate) {
            $remotePath = trim((string) ($candidate['remote_path'] ?? $candidate['name'] ?? ''));
            if ($remotePath === '') {
                continue;
            }

            $binary = $client->downloadFile($remotePath);
            if ($binary === null || $binary === '') {
                continue;
            }

            $baseName = basename(str_replace('\\', '/', $remotePath));
            $tmpPath = tempnam(sys_get_temp_dir(), 'fzsync_');
            if ($tmpPath === false) {
                continue;
            }

            file_put_contents($tmpPath, $binary);
            $fileId = 'f_' . time() . '_' . bin2hex(random_bytes(4));

            try {
                $meta = save_chunk_upload($fileId, $baseName, 0, 1, $tmpPath, 'supplier');
            } finally {
                @unlink($tmpPath);
            }

            if (empty($meta['completed'])) {
                continue;
            }

            $entry = [
                'name' => $baseName,
                'file_id' => $fileId,
                'size' => (int) ($meta['size'] ?? strlen($binary)),
                'remote_path' => $remotePath,
            ];
            $synced[] = $entry;
            $debug['downloaded'][] = $baseName . ' (' . $this->formatBytes((int) $entry['size']) . ')';
        }

        if ($synced === []) {
            return $this->fail(
                'Conexiune OK, dar download esuat pentru ' . count($candidates) . ' fisier(e) gasite.',
                $debug,
                $started
            );
        }

        $this->updateAgentState($code, $synced[0]);
        $debug['elapsed_ms'] = (int) round((microtime(true) - $started) * 1000);

        return [
            'success' => true,
            'message' => 'Descarcat ' . count($synced) . ' fisier(e) de pe ' . $debug['protocol'] . ' ' . $host,
            'files' => $synced,
            'debug' => $debug,
        ];
    }

    /** @param array<string, mixed> $debug @param array<string, mixed> $file */
    private function updateAgentState(string $code, array $file): void
    {
        $path = dirname(__DIR__, 3) . self::STATE_FILE;
        $state = [];
        if (is_file($path)) {
            $decoded = json_decode((string) file_get_contents($path), true);
            if (is_array($decoded)) {
                $state = $decoded;
            }
        }

        $localPath = '';
        $fileId = (string) ($file['file_id'] ?? '');
        if ($fileId !== '' && function_exists('import_temp_file_path')) {
            $localPath = import_temp_file_path($fileId);
        }

        $hash = ($localPath !== '' && is_file($localPath)) ? hash_file('sha256', $localPath) : '';

        $state[$code] = [
            'sha256' => $hash !== false ? $hash : '',
            'filename' => (string) ($file['name'] ?? ''),
            'size' => (int) ($file['size'] ?? 0),
            'source' => 'ftp',
            'remote_path' => (string) ($file['remote_path'] ?? ''),
            'file_id' => $fileId,
            'synced_at' => date('c'),
        ];

        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        file_put_contents($path, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

  /** @param array<string, mixed> $debug */
    private function fail(string $message, array $debug, float $started): array
    {
        $debug['elapsed_ms'] = (int) round((microtime(true) - $started) * 1000);

        return [
            'success' => false,
            'message' => $message,
            'files' => [],
            'debug' => $debug,
        ];
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }
        if ($bytes < 1048576) {
            return round($bytes / 1024, 1) . ' KB';
        }

        return round($bytes / 1048576, 1) . ' MB';
    }

    private function bootImportLibrary(): void
    {
        if (function_exists('save_chunk_upload')) {
            return;
        }

        define('IMPORT_PRODUCE_SKIP_HTTP', true);
        require_once dirname(__DIR__) . '/Produse/importproduse.php';
    }

    private function normalizeCode(string $value): string
    {
        $value = trim($value);

        return $value === ''
            ? ''
            : (function_exists('mb_strtoupper') ? mb_strtoupper($value, 'UTF-8') : strtoupper($value));
    }
}
