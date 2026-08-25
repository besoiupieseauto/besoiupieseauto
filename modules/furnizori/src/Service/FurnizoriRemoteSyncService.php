<?php
declare(strict_types=1);

namespace Besoiu\Modules\Furnizori\Service;

/**
 * Descarca fisierele de pret de pe FTP/SFTP in folderul local al furnizorului.
 */
class FurnizoriRemoteSyncService
{
    private const STATE_FILE = '/storage/supplier_sync_agent_state.json';
    private const MAX_FILES = 40;

    /**
     * @param array<string, mixed> $furnizor
     * @return array{
     *   success:bool,
     *   message:string,
     *   files:array<int,array<string,mixed>>,
     *   skipped:array<int,array<string,mixed>>,
     *   folder:string,
     *   debug?:array<string,mixed>
     * }
     */
    public function syncFromFtp(array $furnizor): array
    {
        $started = microtime(true);
        $this->bootImportLibrary();

        $furnizor = import_furnizori_resolve_credentials($furnizor);
        $code = $this->normalizeCode((string) ($furnizor['code'] ?? ''));
        $randomnId = (int) ($furnizor['randomn_id'] ?? 0);
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
            'skipped' => [],
            'folder' => '',
            'elapsed_ms' => 0,
        ];

        if ($code === '') {
            return $this->fail('Cod furnizor lipsa.', $debug, $started);
        }

        $host = (string) $debug['host'];
        if ($host === '') {
            return $this->fail('Host FTP/SFTP neconfigurat.', $debug, $started);
        }

        $remoteDir = trim((string) ($furnizor['conn_remote_path'] ?? ''));
        if ($remoteDir === '') {
            $remoteDir = '/';
        }
        $debug['remote_dir'] = $remoteDir;

        $folderService = new SupplierFeedFolderService();
        $folder = $folderService->ensureFolder($code, $randomnId);
        $debug['folder'] = (string) ($folder['relative'] ?? '');
        if (empty($folder['exists'])) {
            return $this->fail('Nu pot crea folderul local ' . $debug['folder'] . '.', $debug, $started);
        }

        $client = (new FtpConnectionClient())->configure($furnizor);
        $candidates = $client->pickRemoteFeedFiles($remoteDir);
        $debug['candidates'] = count($candidates);
        $debug['candidate_names'] = array_values(array_map(
            static fn (array $c): string => (string) ($c['name'] ?? $c['remote_path'] ?? ''),
            array_slice($candidates, 0, 12)
        ));

        if ($candidates === []) {
            return $this->fail(
                'Niciun fisier de pret in ' . $remoteDir . ' pe ' . $debug['protocol'] . ' ' . $host . '.',
                $debug,
                $started
            );
        }

        $synced = [];
        $skipped = [];
        foreach (array_slice($candidates, 0, self::MAX_FILES) as $candidate) {
            $remotePath = trim((string) ($candidate['remote_path'] ?? $candidate['name'] ?? ''));
            if ($remotePath === '') {
                continue;
            }

            $baseName = basename(str_replace('\\', '/', $remotePath));
            $tmpPath = tempnam(sys_get_temp_dir(), 'fzsync_');
            if ($tmpPath === false) {
                continue;
            }

            $downloaded = false;
            try {
                $downloaded = $client->downloadFileToPath($remotePath, $tmpPath) === true;
            } catch (\Throwable $e) {
                $downloaded = false;
            }
            if (!$downloaded || !is_file($tmpPath) || filesize($tmpPath) <= 0) {
                @unlink($tmpPath);
                continue;
            }

            $saved = $folderService->saveDownloadedFile($code, $randomnId, $tmpPath, $baseName);
            @unlink($tmpPath);

            $entry = [
                'name' => (string) ($saved['name'] ?? $baseName),
                'size' => (int) ($saved['size'] ?? 0),
                'remote_path' => $remotePath,
                'local_path' => (string) ($saved['path'] ?? ''),
                'folder' => (string) ($saved['relative'] ?? $debug['folder']),
            ];

            if (!empty($saved['skipped'])) {
                $skipped[] = $entry;
                $debug['skipped'][] = $entry['name'] . ' (identic)';
                continue;
            }

            if (empty($saved['saved'])) {
                continue;
            }

            $synced[] = $entry;
            $debug['downloaded'][] = $entry['name'] . ' (' . $this->formatBytes((int) $entry['size']) . ')';
        }

        if ($synced === [] && $skipped === []) {
            return $this->fail(
                'Conexiune OK, dar download esuat pentru ' . count($candidates) . ' fisier(e) gasite.',
                $debug,
                $started
            );
        }

        $this->updateAgentState($code, $synced[0] ?? $skipped[0] ?? [], $debug['folder']);
        $debug['elapsed_ms'] = (int) round((microtime(true) - $started) * 1000);

        $proto = (string) $debug['protocol'];
        if ($synced !== []) {
            $message = 'Descarcat ' . count($synced) . ' fisier(e) de pe ' . $proto . ' ' . $host
                . ' → ' . $debug['folder'];
            if ($skipped !== []) {
                $message .= ' · ' . count($skipped) . ' neschimbat(e)';
            }
        } else {
            $message = 'Nimic nou — ' . count($skipped) . ' fisier(e) deja pe server in ' . $debug['folder'];
        }

        return [
            'success' => true,
            'message' => $message,
            'files' => $synced,
            'skipped' => $skipped,
            'folder' => $debug['folder'],
            'debug' => $debug,
        ];
    }

    /** @param array<string, mixed> $file */
    private function updateAgentState(string $code, array $file, string $folder = ''): void
    {
        $path = (defined('BESOIU_ADMIN') ? BESOIU_ADMIN : dirname(__DIR__, 5) . '/admin') . self::STATE_FILE;
        $state = [];
        if (is_file($path)) {
            $decoded = json_decode((string) file_get_contents($path), true);
            if (is_array($decoded)) {
                $state = $decoded;
            }
        }

        $localPath = (string) ($file['local_path'] ?? '');
        $hash = ($localPath !== '' && is_file($localPath)) ? hash_file('sha256', $localPath) : '';

        $state[$code] = [
            'sha256' => $hash !== false ? $hash : '',
            'filename' => (string) ($file['name'] ?? ''),
            'size' => (int) ($file['size'] ?? 0),
            'source' => 'ftp',
            'remote_path' => (string) ($file['remote_path'] ?? ''),
            'folder' => $folder,
            'file_id' => '',
            'synced_at' => date('c'),
            'pulled_at' => date('c'),
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
            'skipped' => [],
            'folder' => (string) ($debug['folder'] ?? ''),
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
        if (!function_exists('import_furnizori_resolve_credentials')) {
            require_once (defined('BESOIU_BACKEND') ? BESOIU_BACKEND : dirname(__DIR__, 4) . '/app/Backend') . '/src/Controllers/Produse/import_supplier_lib.php';
        }
    }

    private function normalizeCode(string $value): string
    {
        $value = trim($value);

        return $value === ''
            ? ''
            : (function_exists('mb_strtoupper') ? mb_strtoupper($value, 'UTF-8') : strtoupper($value));
    }
}
