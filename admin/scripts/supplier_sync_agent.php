<?php

declare(strict_types=1);

/**
 * Agent local: descarca liste furnizor (FTP sau folder FileZilla) si le incarca pe server.
 *
 * Usage:
 *   php scripts/supplier_sync_agent.php
 *   php scripts/supplier_sync_agent.php AUTOPARTNER
 *   php scripts/supplier_sync_agent.php --ping
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/src/Controllers/Produse/import_supplier_lib.php';
require_once __DIR__ . '/supplier_rclone_lib.php';

use Evasystem\Controllers\Furnizori\FtpConnectionClient;
use Evasystem\Controllers\Furnizori\SupplierScanScheduleService;
use Evasystem\Core\Furnizori\FurnizoriModel;

/** @return array<string, mixed> */
function supplier_sync_load_config(): array
{
    $localPath = dirname(__DIR__) . '/config/supplier_sync_agent.local.php';
    $examplePath = dirname(__DIR__) . '/config/supplier_sync_agent.example.php';
    $path = is_file($localPath) ? $localPath : $examplePath;

    if (!is_file($path)) {
        throw new RuntimeException('Lipseste config/supplier_sync_agent.local.php');
    }

    $config = require $path;

    return is_array($config) ? $config : [];
}

/** @param array<string, mixed> $config @return array<string, mixed> */
function supplier_sync_merge_supplier_config(array $config, string $code): array
{
    $suppliers = $config['suppliers'] ?? [];
    if (!isset($suppliers[$code]) || !is_array($suppliers[$code])) {
        throw new RuntimeException('Furnizor negasit in config: ' . $code);
    }

    $merged = $suppliers[$code];
    $merged['code'] = $code;

    $row = (new FurnizoriModel())->findByCode($code);
    if (is_array($row)) {
        foreach ($row as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            if (!array_key_exists($key, $merged) || $merged[$key] === '' || $merged[$key] === null) {
                $merged[$key] = $value;
            }
        }
    }

    $secrets = import_furnizori_load_secrets();
    if (isset($secrets[$code]) && is_array($secrets[$code])) {
        foreach ($secrets[$code] as $key => $value) {
            if ($value !== null && $value !== '') {
                if (!array_key_exists($key, $merged) || $merged[$key] === '' || $merged[$key] === null) {
                    $merged[$key] = $value;
                }
            }
        }
    }

    return import_furnizori_resolve_credentials($merged);
}

/** @return array<string, mixed> */
function supplier_sync_load_state(string $stateFile): array
{
    if (!is_file($stateFile)) {
        return [];
    }

    $raw = file_get_contents($stateFile);
    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $decoded = json_decode($raw, true);

    return is_array($decoded) ? $decoded : [];
}

/** @param array<string, mixed> $state */
function supplier_sync_save_state(string $stateFile, array $state): void
{
    $dir = dirname($stateFile);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    file_put_contents(
        $stateFile,
        json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    );
}

/** @return array<int, array<string, mixed>> */
function supplier_sync_local_candidates(string $folder, string $pattern): array
{
    if (!is_dir($folder)) {
        return [];
    }

    $regex = '/^' . str_replace('\*', '.*', preg_quote($pattern, '/')) . '$/i';
    $matches = [];

    foreach (scandir($folder) ?: [] as $name) {
        if ($name === '.' || $name === '..') {
            continue;
        }
        $path = rtrim($folder, '/\\') . DIRECTORY_SEPARATOR . $name;
        if (!is_file($path) || !preg_match($regex, $name)) {
            continue;
        }
        $matches[] = [
            'name' => $name,
            'path' => $path,
            'size' => filesize($path) ?: 0,
            'mtime' => filemtime($path) ?: 0,
        ];
    }

    usort($matches, static function (array $a, array $b): int {
        $mtime = ((int) ($b['mtime'] ?? 0)) <=> ((int) ($a['mtime'] ?? 0));
        if ($mtime !== 0) {
            return $mtime;
        }

        return strcmp((string) ($b['name'] ?? ''), (string) ($a['name'] ?? ''));
    });

    return $matches;
}

/** @param array<string, mixed> $supplier */
function supplier_sync_acquire_file(array $supplier, string $cacheDir): array
{
    $source = strtolower(trim((string) ($supplier['source'] ?? 'ftp')));
    $pattern = trim((string) ($supplier['file_pattern'] ?? '*.csv'));
    if ($pattern === '') {
        $pattern = '*.csv';
    }

    if ($source === 'local_folder') {
        $folder = trim((string) ($supplier['local_folder'] ?? ''));
        if ($folder === '' || !is_dir($folder)) {
            throw new RuntimeException('local_folder invalid sau inexistent: ' . $folder);
        }

        $candidates = supplier_sync_local_candidates($folder, $pattern);
        if ($candidates === []) {
            throw new RuntimeException('Niciun fisier ' . $pattern . ' in ' . $folder);
        }

        $pick = $candidates[0];

        return [
            'source' => 'local_folder',
            'name' => (string) $pick['name'],
            'path' => (string) $pick['path'],
            'size' => (int) $pick['size'],
        ];
    }

    if ($source === 'rclone') {
        $code = strtoupper(trim((string) ($supplier['code'] ?? '')));
        $syncDir = rtrim($cacheDir, '/\\') . DIRECTORY_SEPARATOR . strtolower($code);
        supplier_rclone_sync_supplier($supplier, $syncDir);

        $candidates = supplier_sync_local_candidates($syncDir, $pattern);
        if ($candidates === []) {
            throw new RuntimeException('rclone: niciun fisier ' . $pattern . ' dupa sync in ' . $syncDir);
        }

        $pick = $candidates[0];

        return [
            'source' => 'rclone',
            'name' => (string) $pick['name'],
            'path' => (string) $pick['path'],
            'size' => (int) $pick['size'],
            'remote_path' => (string) ($supplier['remote_dir'] ?? '/'),
        ];
    }

    $remoteFile = trim((string) ($supplier['remote_file'] ?? ''));
    $remoteDir = trim((string) ($supplier['remote_dir'] ?? '/'));
    if ($remoteDir === '') {
        $remoteDir = '/';
    }

    $client = (new FtpConnectionClient())->configure($supplier);

    if ($remoteFile === '') {
        $candidates = $client->pickRemoteFiles($remoteDir, $pattern);
        if ($candidates === []) {
            throw new RuntimeException('Niciun fisier remote ' . $pattern . ' in ' . $remoteDir);
        }
        $remoteFile = (string) ($candidates[0]['remote_path'] ?? $candidates[0]['name'] ?? '');
    }

    if ($remoteFile === '') {
        throw new RuntimeException('Nu s-a putut determina fisierul remote.');
    }

    $binary = $client->downloadFile($remoteFile);
    if ($binary === null || $binary === '') {
        throw new RuntimeException('Download FTP esuat: ' . $remoteFile);
    }

    if (!is_dir($cacheDir)) {
        mkdir($cacheDir, 0775, true);
    }

    $baseName = basename(str_replace('\\', '/', $remoteFile));
    $localPath = rtrim($cacheDir, '/\\') . DIRECTORY_SEPARATOR . $baseName;
    file_put_contents($localPath, $binary);

    return [
        'source' => 'ftp',
        'name' => $baseName,
        'path' => $localPath,
        'size' => strlen($binary),
        'remote_path' => $remoteFile,
    ];
}

/** @return array<string, mixed> */
function supplier_sync_upload_file(array $config, string $localPath, string $originalName, string $supplierCode): array
{
    $serverUrl = trim((string) ($config['server_url'] ?? ''));
    $token = trim((string) ($config['sync_token'] ?? ''));
    if ($serverUrl === '' || $token === '') {
        throw new RuntimeException('server_url sau sync_token lipsesc din config.');
    }

    if (!is_file($localPath)) {
        throw new RuntimeException('Fisier local inexistent: ' . $localPath);
    }

    $size = filesize($localPath);
    if ($size === false) {
        throw new RuntimeException('Nu pot citi marimea fisierului.');
    }

    $fileId = 'f_' . time() . '_' . bin2hex(random_bytes(4));
    $chunkSize = 2 * 1024 * 1024;
    $totalChunks = max(1, (int) ceil($size / $chunkSize));
    $lastResponse = [];

    for ($chunkIndex = 0; $chunkIndex < $totalChunks; $chunkIndex++) {
        $start = $chunkIndex * $chunkSize;
        $length = (int) min($chunkSize, $size - $start);
        $chunkPath = $localPath;
        $tmpChunk = null;

        if ($totalChunks > 1) {
            $tmpChunk = tempnam(sys_get_temp_dir(), 'sync_chunk_');
            if ($tmpChunk === false) {
                throw new RuntimeException('Nu pot crea chunk temporar.');
            }
            $in = fopen($localPath, 'rb');
            $out = fopen($tmpChunk, 'wb');
            if (!$in || !$out) {
                throw new RuntimeException('Nu pot citi fisierul pentru chunk.');
            }
            fseek($in, $start);
            stream_copy_to_stream($in, $out, $length);
            fclose($in);
            fclose($out);
            $chunkPath = $tmpChunk;
        }

        $post = [
            'action' => 'upload',
            'sync_token' => $token,
            'file_id' => $fileId,
            'original_name' => $originalName,
            'supplier_code' => $supplierCode,
            'upload_role' => 'supplier',
            'chunk_index' => (string) $chunkIndex,
            'total_chunks' => (string) $totalChunks,
            'file' => new CURLFile($chunkPath, 'application/octet-stream', $originalName),
        ];

        $ch = curl_init($serverUrl);
        if ($ch === false) {
            throw new RuntimeException('curl_init esuat.');
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $post,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 600,
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_POSTREDIR => CURL_REDIR_POST_ALL,
            CURLOPT_HTTPHEADER => ['X-Supplier-Sync-Token: ' . $token],
        ]);

        $body = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($tmpChunk !== null && is_file($tmpChunk)) {
            @unlink($tmpChunk);
        }

        if ($body === false || $error !== '') {
            throw new RuntimeException('Upload esuat (chunk ' . ($chunkIndex + 1) . '): ' . $error);
        }

        $decoded = json_decode((string) $body, true);
        if (!is_array($decoded) || empty($decoded['success'])) {
            $message = is_array($decoded) ? (string) ($decoded['message'] ?? 'Eroare necunoscuta') : 'Raspuns invalid de la server';
            throw new RuntimeException('Upload respins (HTTP ' . $status . '): ' . $message);
        }

        $lastResponse = $decoded;
    }

    return $lastResponse;
}

/** @return array<string, mixed> */
function supplier_sync_ping(array $config): array
{
    $serverUrl = trim((string) ($config['server_url'] ?? ''));
    $token = trim((string) ($config['sync_token'] ?? ''));
    if ($serverUrl === '' || $token === '') {
        throw new RuntimeException('server_url sau sync_token lipsesc din config.');
    }

    $ch = curl_init($serverUrl);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => ['action' => 'ping', 'sync_token' => $token],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_POSTREDIR => CURL_REDIR_POST_ALL,
        CURLOPT_HTTPHEADER => ['X-Supplier-Sync-Token: ' . $token],
    ]);
    $body = curl_exec($ch);
    curl_close($ch);

    $decoded = json_decode((string) $body, true);

    return is_array($decoded) ? $decoded : ['success' => false, 'message' => 'Raspuns invalid'];
}

/** @param array<string, mixed> $config */
function supplier_sync_run_supplier(array $config, string $code, array &$state, bool $respectSchedule = true): void
{
    $supplier = supplier_sync_merge_supplier_config($config, $code);
    if (empty($supplier['enabled'])) {
        echo "[{$code}] sarit (disabled)\n";

        return;
    }

    $dbRow = (new FurnizoriModel())->findByCode($code);
    if (is_array($dbRow)) {
        $supplier = array_merge($supplier, $dbRow);
    }

    if ($respectSchedule && !SupplierScanScheduleService::shouldRunAuto($supplier, is_array($state[$code] ?? null) ? $state[$code] : [])) {
        echo '[' . $code . '] sarit (program: ' . SupplierScanScheduleService::formatLabel($supplier) . ")\n";

        return;
    }

    $cacheDir = rtrim((string) ($config['local_download_dir'] ?? (sys_get_temp_dir() . '/supplier-sync')), '/\\');
    $file = supplier_sync_acquire_file($supplier, $cacheDir);
    $hash = hash_file('sha256', (string) $file['path']);
    if ($hash === false) {
        throw new RuntimeException('Nu pot calcula hash pentru ' . $file['path']);
    }

    $prev = is_array($state[$code] ?? null) ? $state[$code] : [];
    if (($prev['sha256'] ?? '') === $hash) {
        echo "[{$code}] nemodificat: {$file['name']} (skip upload)\n";

        return;
    }

    echo "[{$code}] upload {$file['name']} (" . number_format((int) $file['size']) . " bytes)...\n";
    $response = supplier_sync_upload_file($config, (string) $file['path'], (string) $file['name'], $code);
    $data = is_array($response['data'] ?? null) ? $response['data'] : [];

    $state[$code] = [
        'sha256' => $hash,
        'filename' => (string) $file['name'],
        'size' => (int) $file['size'],
        'source' => (string) ($file['source'] ?? ''),
        'remote_path' => (string) ($file['remote_path'] ?? ''),
        'file_id' => (string) ($data['file_id'] ?? ''),
        'synced_at' => date('c'),
    ];

    echo "[{$code}] OK -> file_id=" . ($data['file_id'] ?? '-') . "\n";
}

function supplier_sync_main(array $argv): int
{
    $config = supplier_sync_load_config();
    $stateFile = (string) ($config['state_file'] ?? (dirname(__DIR__) . '/storage/supplier_sync_agent_state.json'));
    $state = supplier_sync_load_state($stateFile);

    if (in_array('--ping', $argv, true)) {
        $result = supplier_sync_ping($config);
        echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;

        return !empty($result['success']) ? 0 : 1;
    }

    if (in_array('--rclone-config', $argv, true)) {
        $php = PHP_BINARY;
        $script = dirname(__DIR__) . '/tools/generate_rclone_config.php';
        passthru(escapeshellarg($php) . ' ' . escapeshellarg($script), $code);

        return (int) $code;
    }

    $onlyCode = '';
    foreach ($argv as $arg) {
        if ($arg === basename(__FILE__) || str_starts_with($arg, '-') || str_contains($arg, '.php')) {
            continue;
        }
        $onlyCode = strtoupper(trim($arg));
        break;
    }

    $suppliers = $config['suppliers'] ?? [];
    if (!is_array($suppliers) || $suppliers === []) {
        throw new RuntimeException('Nu exista furnizori in config.');
    }

    $codes = $onlyCode !== '' ? [$onlyCode] : array_keys($suppliers);
    $errors = 0;

    $respectSchedule = $onlyCode === '';

    foreach ($codes as $code) {
        try {
            supplier_sync_run_supplier($config, (string) $code, $state, $respectSchedule);
        } catch (Throwable $exception) {
            $errors++;
            fwrite(STDERR, '[' . $code . '] EROARE: ' . $exception->getMessage() . PHP_EOL);
        }
    }

    supplier_sync_save_state($stateFile, $state);

    return $errors > 0 ? 1 : 0;
}

try {
    if (!function_exists('curl_init')) {
        throw new RuntimeException('Extensia PHP curl este necesara.');
    }

    $dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
    $dotenv->safeLoad();
    $appConfig = require dirname(__DIR__) . '/config/config.php';
    Config\Database::getInstance(
        $appConfig['db_host'],
        $appConfig['db_name'],
        $appConfig['db_user'],
        $appConfig['db_pass']
    );

    exit(supplier_sync_main($argv));
} catch (Throwable $exception) {
    fwrite(STDERR, 'FATAL: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
