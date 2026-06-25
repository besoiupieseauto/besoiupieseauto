<?php

declare(strict_types=1);

namespace Evasystem\Controllers\Furnizori;

/**
 * Client FTP/SFTP compatibil cu hosting fara extensia PHP ftp.
 * Incearca mai multe moduri de conexiune (pasiv, activ, FTPS) ca FileZilla.
 */
final class FtpConnectionClient
{
    private string $host = '';
    private string $user = '';
    private string $pass = '';
    private int $port = 21;
    private bool $passive = true;
    private string $scheme = 'ftp';

    /** @var array<string, mixed>|null */
    private ?array $winningStrategy = null;

    /** @var resource|null */
    private $nativeConn = null;

    public static function isAvailable(): bool
    {
        return function_exists('curl_init') || function_exists('ftp_connect');
    }

    /** @param array<string, mixed> $furnizor */
    public function configure(array $furnizor): self
    {
        $this->host = trim((string) ($furnizor['conn_host'] ?? ''));
        $this->user = trim((string) ($furnizor['conn_username'] ?? ''));
        $this->pass = (string) ($furnizor['conn_password'] ?? '');
        $connectionType = (string) ($furnizor['connection_type'] ?? 'ftp');
        $this->scheme = $connectionType === 'sftp' ? 'sftp' : 'ftp';
        $this->port = (int) ($furnizor['conn_port'] ?? ($this->scheme === 'sftp' ? 22 : 21));
        $this->passive = !empty($furnizor['conn_passive']);
        $this->winningStrategy = null;

        return $this;
    }

    /** @return array{ok:bool,message:string} */
    public function testRemotePath(string $remotePath = ''): array
    {
        if ($this->host === '' || $this->user === '') {
            return ['ok' => false, 'message' => 'Lipsesc host sau utilizator.'];
        }

        if ($remotePath === '') {
            return $this->ping();
        }

        if ($this->isFilePath($remotePath)) {
            $preview = $this->download($remotePath, 512);

            return $preview !== null
                ? ['ok' => true, 'message' => 'OK — fisier accesibil (' . $remotePath . ').']
                : ['ok' => false, 'message' => 'Fisierul remote nu poate fi descarcat: ' . $remotePath];
        }

        $listing = $this->listDirectory($remotePath);

        return $listing['success']
            ? ['ok' => true, 'message' => 'OK — director accesibil (' . $listing['path'] . ').']
            : ['ok' => false, 'message' => $listing['message']];
    }

    /** @return array{ok:bool,message:string} */
    public function ping(): array
    {
        $listing = $this->listDirectory('/');
        if ($listing['success']) {
            $mode = (string) ($listing['mode'] ?? '');

            return ['ok' => true, 'message' => $mode !== '' ? ('OK (' . $mode . ')') : 'OK'];
        }

        return ['ok' => false, 'message' => $listing['message']];
    }

    /** @return array{success:bool,message:string,path:string,entries:array<int,array<string,mixed>>,mode?:string} */
    public function listDirectory(string $path): array
    {
        $path = $this->normalizeDirectory($path);

        if ($this->host === '' || $this->user === '') {
            return [
                'success' => false,
                'message' => 'Lipsesc host sau utilizator.',
                'path' => $path,
                'entries' => [],
            ];
        }

        if ($this->pass === '') {
            return [
                'success' => false,
                'message' => 'Parola FTP lipseste. Reintrodu parola in profil, salveaza configurarea, apoi incearca din nou.',
                'path' => $path,
                'entries' => [],
            ];
        }

        if ($this->preferCurl()) {
            return $this->curlList($path);
        }

        return $this->nativeList($path);
    }

    /** @return array{path:string,bytes:int,content:string,mime:string}|null */
    public function previewFile(string $remotePath, int $maxBytes = 4096): ?array
    {
        $remotePath = $this->normalizeRemotePath($remotePath);
        $binary = $this->download($remotePath, $maxBytes);
        if ($binary === null) {
            return null;
        }

        $content = strlen($binary) > $maxBytes ? substr($binary, 0, $maxBytes) : $binary;

        return [
            'path' => $remotePath,
            'bytes' => strlen($binary),
            'content' => $content,
            'mime' => str_ends_with(strtolower($remotePath), '.csv') ? 'text/csv' : 'application/octet-stream',
        ];
    }

    /** Descarca un fisier remote intreg (0 = fara limita). */
    public function downloadFile(string $remotePath, int $maxBytes = 0): ?string
    {
        return $this->download($this->normalizeRemotePath($remotePath), $maxBytes);
    }

    /** @return array<int, array<string, mixed>> */
    public function pickRemoteFiles(string $directory, string $pattern = '*.csv'): array
    {
        $listing = $this->listDirectory($directory);
        if (!$listing['success']) {
            return [];
        }

        $regex = '/^' . str_replace('\*', '.*', preg_quote($pattern, '/')) . '$/i';
        $matches = [];
        foreach ($listing['entries'] as $entry) {
            if (($entry['type'] ?? '') !== 'file') {
                continue;
            }
            $name = (string) ($entry['name'] ?? '');
            if ($name === '' || !preg_match($regex, $name)) {
                continue;
            }
            $matches[] = $entry + [
                'remote_path' => rtrim($listing['path'], '/') . '/' . ltrim($name, '/'),
            ];
        }

        usort($matches, static function (array $a, array $b): int {
            return strcmp((string) ($b['name'] ?? ''), (string) ($a['name'] ?? ''));
        });

        return $matches;
    }

    /** @return array{success:bool,message:string,path:string,entries:array<int,array<string,mixed>>,preview?:array<string,mixed>,engine?:string,mode?:string} */
    public function browse(string $requestedPath, string $configuredPath = ''): array
    {
        $path = trim($requestedPath);
        if ($path === '') {
            $path = $configuredPath !== '' ? $this->normalizeDirectory($configuredPath) : '/';
        }

        $preview = null;
        $listPath = $path;
        if ($this->isFilePath($path)) {
            $preview = $this->previewFile($path);
            $listPath = $this->normalizeDirectory(dirname(str_replace('\\', '/', $path)));
        }

        $listing = $this->listDirectory($listPath);
        if (!$listing['success']) {
            return [
                'success' => false,
                'message' => $listing['message'],
                'path' => $listPath,
                'entries' => [],
                'preview' => $preview,
                'configured_path' => $configuredPath,
            ];
        }

        return [
            'success' => true,
            'message' => 'OK',
            'path' => $listing['path'],
            'configured_path' => $configuredPath,
            'entries' => $listing['entries'],
            'preview' => $preview,
            'engine' => $this->preferCurl() ? 'curl' : 'ftp',
            'mode' => $listing['mode'] ?? null,
        ];
    }

    private function preferCurl(): bool
    {
        return function_exists('curl_init');
    }

    /** @return array{success:bool,message:string,path:string,entries:array<int,array<string,mixed>>,mode?:string} */
    private function curlList(string $path): array
    {
        $path = $this->normalizeDirectory($path);
        $result = $this->curlTransfer($this->buildUrl($path, true), 0);

        if (!$result['success']) {
            return [
                'success' => false,
                'message' => $result['error'],
                'path' => $path,
                'entries' => [],
            ];
        }

        return [
            'success' => true,
            'message' => 'OK',
            'path' => $path,
            'entries' => $this->parseListingBody((string) $result['body']),
            'mode' => $result['mode'],
        ];
    }

    /** @return array{success:bool,body:string,error:string,mode:string} */
    private function curlTransfer(string $url, int $maxBytes = 0): array
    {
        $strategies = $this->winningStrategy !== null
            ? [$this->winningStrategy]
            : $this->buildCurlStrategies();

        $lastError = 'Conexiune FTP esuata.';
        $authFailed = false;
        foreach ($strategies as $strategy) {
            $ch = curl_init($url);
            if ($ch === false) {
                continue;
            }

            $options = [
                CURLOPT_USERPWD => $this->user . ':' . $this->pass,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 45,
                CURLOPT_CONNECTTIMEOUT => 25,
            ] + $strategy['options'];

            if ($maxBytes > 0) {
                $options[CURLOPT_RANGE] = '0-' . max(0, $maxBytes - 1);
            }

            curl_setopt_array($ch, $options);
            $body = curl_exec($ch);
            $error = trim((string) curl_error($ch));
            $httpCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            curl_close($ch);

            if ($body !== false && $error === '') {
                $this->winningStrategy = $strategy;

                return [
                    'success' => true,
                    'body' => (string) $body,
                    'error' => '',
                    'mode' => (string) $strategy['label'],
                ];
            }

            if ($this->isAuthError($error) || $httpCode === 530 || $httpCode === 531) {
                $authFailed = true;
                $lastError = $this->authHelpMessage();
                break;
            }

            if ($error !== '') {
                $lastError = $this->humanizeCurlError($error);
            } elseif ($httpCode >= 400) {
                $lastError = 'Raspuns FTP neasteptat (cod ' . $httpCode . ').';
            }
        }

        if ($authFailed) {
            return [
                'success' => false,
                'body' => '',
                'error' => $lastError,
                'mode' => '',
            ];
        }

        return [
            'success' => false,
            'body' => '',
            'error' => $lastError,
            'mode' => '',
        ];
    }

    /** @return array<int, array{label:string,options:array<int, mixed>}> */
    private function buildCurlStrategies(): array
    {
        if ($this->scheme === 'sftp') {
            return [[
                'label' => 'SFTP',
                'options' => [CURLOPT_PROTOCOLS => CURLPROTO_SFTP],
            ]];
        }

        $passive = $this->passive;
        $strategies = [
            [
                'label' => 'FTP pasiv (EPSV)',
                'options' => [
                    CURLOPT_FTP_USE_EPSV => true,
                    CURLOPT_FTP_USE_EPRT => false,
                ],
            ],
            [
                'label' => 'FTP pasiv (PASV)',
                'options' => [
                    CURLOPT_FTP_USE_EPSV => false,
                    CURLOPT_FTP_USE_EPRT => false,
                ],
            ],
            [
                'label' => 'FTP activ',
                'options' => [
                    CURLOPT_FTP_USE_EPSV => false,
                    CURLOPT_FTP_USE_EPRT => true,
                ],
            ],
        ];

        if (defined('CURLUSESSL_ALL')) {
            $strategies[] = [
                'label' => 'FTPS explicit (TLS pasiv)',
                'options' => [
                    CURLOPT_USE_SSL => CURLUSESSL_ALL,
                    CURLOPT_FTP_USE_EPSV => $passive,
                    CURLOPT_FTP_USE_EPRT => false,
                ],
            ];
            $strategies[] = [
                'label' => 'FTPS explicit (TLS activ)',
                'options' => [
                    CURLOPT_USE_SSL => CURLUSESSL_ALL,
                    CURLOPT_FTP_USE_EPSV => false,
                    CURLOPT_FTP_USE_EPRT => true,
                ],
            ];
            if (defined('CURLUSESSL_TRY')) {
                $strategies[] = [
                    'label' => 'FTP cu TLS optional',
                    'options' => [
                        CURLOPT_USE_SSL => CURLUSESSL_TRY,
                        CURLOPT_FTP_USE_EPSV => $passive,
                    ],
                ];
            }
        }

        if (!$passive) {
            $strategies = array_reverse($strategies);
        }

        return $strategies;
    }

    private function download(string $remotePath, int $maxBytes = 0): ?string
    {
        $remotePath = $this->normalizeRemotePath($remotePath);

        if ($this->preferCurl()) {
            $result = $this->curlTransfer($this->buildUrl($remotePath, false), $maxBytes);

            return $result['success'] ? $result['body'] : null;
        }

        if (!$this->nativeConnect()) {
            return null;
        }

        $tmp = tempnam(sys_get_temp_dir(), 'ftp_dl_');
        if ($tmp === false) {
            $this->nativeDisconnect();

            return null;
        }

        $remoteFile = ltrim($remotePath, '/');
        $ok = @ftp_get($this->nativeConn, $tmp, $remoteFile, FTP_BINARY);
        $content = null;
        if ($ok) {
            $content = file_get_contents($tmp, false, null, 0, $maxBytes > 0 ? $maxBytes : null) ?: '';
        }
        @unlink($tmp);
        $this->nativeDisconnect();

        return $content;
    }

    /** @return array{success:bool,message:string,path:string,entries:array<int,array<string,mixed>>} */
    private function nativeList(string $path): array
    {
        if (!function_exists('ftp_connect')) {
            return [
                'success' => false,
                'message' => 'Nici cURL, nici extensia PHP ftp nu sunt disponibile pe server.',
                'path' => $path,
                'entries' => [],
            ];
        }

        if (!$this->nativeConnect()) {
            return [
                'success' => false,
                'message' => $this->authHelpMessage(),
                'path' => $path,
                'entries' => [],
            ];
        }

        if ($path !== '/' && !@ftp_chdir($this->nativeConn, $path)) {
            $this->nativeDisconnect();

            return [
                'success' => false,
                'message' => 'Directorul remote nu este accesibil: ' . $path,
                'path' => $path,
                'entries' => [],
            ];
        }

        $entries = [];
        $raw = @ftp_rawlist($this->nativeConn, '.', true);
        if (is_array($raw)) {
            foreach ($raw as $line) {
                $parsed = $this->parseRawListLine((string) $line);
                if ($parsed !== null) {
                    $entries[] = $parsed;
                }
            }
        } else {
            $names = @ftp_nlist($this->nativeConn, '.');
            if (is_array($names)) {
                foreach ($names as $name) {
                    $base = basename(str_replace('\\', '/', (string) $name));
                    if ($base === '' || $base === '.' || $base === '..') {
                        continue;
                    }
                    $size = @ftp_size($this->nativeConn, $base);
                    $entries[] = [
                        'name' => $base,
                        'type' => $size === -1 ? 'dir' : 'file',
                        'size' => $size >= 0 ? $size : null,
                    ];
                }
            }
        }

        $this->nativeDisconnect();
        usort($entries, static fn ($a, $b) => strcmp((string) $a['name'], (string) $b['name']));

        return [
            'success' => true,
            'message' => 'OK',
            'path' => $path,
            'entries' => $entries,
        ];
    }

    private function nativeConnect(): bool
    {
        if ($this->nativeConn !== null) {
            return true;
        }

        if (!function_exists('ftp_connect') || $this->scheme !== 'ftp') {
            return false;
        }

        $conn = @ftp_connect($this->host, $this->port, 20);
        if ($conn === false && function_exists('ftp_ssl_connect')) {
            $conn = @ftp_ssl_connect($this->host, $this->port, 20);
        }

        if ($conn === false || !@ftp_login($conn, $this->user, $this->pass)) {
            if ($conn !== false) {
                @ftp_close($conn);
            }

            return false;
        }

        @ftp_pasv($conn, $this->passive);
        $this->nativeConn = $conn;

        return true;
    }

    private function nativeDisconnect(): void
    {
        if ($this->nativeConn !== null) {
            @ftp_close($this->nativeConn);
            $this->nativeConn = null;
        }
    }

    /** @return array<int, array{name:string,type:string,size:?int}> */
    private function parseListingBody(string $body): array
    {
        $entries = [];
        foreach (preg_split('/\r\n|\n|\r/', trim($body)) ?: [] as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }

            $parsed = $this->parseRawListLine($line);
            if ($parsed !== null) {
                $entries[] = $parsed;
                continue;
            }

            $name = basename(str_replace('\\', '/', $line));
            if ($name === '' || $name === '.' || $name === '..') {
                continue;
            }

            $entries[] = [
                'name' => $name,
                'type' => $this->isFilePath($name) ? 'file' : 'dir',
                'size' => null,
            ];
        }

        usort($entries, static fn ($a, $b) => strcmp((string) $a['name'], (string) $b['name']));

        return $entries;
    }

    /** @return array{name:string,type:string,size:?int}|null */
    private function parseRawListLine(string $line): ?array
    {
        $line = trim($line);
        if ($line === '') {
            return null;
        }

        $parts = preg_split('/\s+/', $line, 9);
        if (!is_array($parts) || count($parts) < 4) {
            return null;
        }

        if (count($parts) >= 9) {
            $name = (string) $parts[8];
            if ($name === '.' || $name === '..') {
                return null;
            }
            $isDir = str_starts_with($parts[0], 'd');

            return [
                'name' => $name,
                'type' => $isDir ? 'dir' : 'file',
                'size' => $isDir ? null : (is_numeric($parts[4]) ? (int) $parts[4] : null),
            ];
        }

        return null;
    }

    private function buildUrl(string $path, bool $directory): string
    {
        $path = $this->normalizeRemotePath($path);
        if ($directory) {
            $path = rtrim($path, '/') . '/';
        }

        $portPart = ($this->scheme === 'ftp' && $this->port === 21) || ($this->scheme === 'sftp' && $this->port === 22)
            ? ''
            : ':' . $this->port;

        return $this->scheme . '://' . $this->host . $portPart . $path;
    }

    private function normalizeRemotePath(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));
        if ($path === '' || $path === '.') {
            return '/';
        }

        return str_starts_with($path, '/') ? $path : '/' . $path;
    }

    private function normalizeDirectory(string $path): string
    {
        $path = $this->normalizeRemotePath($path);
        if ($this->isFilePath($path)) {
            $path = dirname($path);
        }

        if ($path === '.' || $path === '') {
            return '/';
        }

        return $path;
    }

    private function isFilePath(string $path): bool
    {
        return str_contains(basename(str_replace('\\', '/', $path)), '.');
    }

    private function isAuthError(string $error): bool
    {
        $error = strtolower($error);

        return str_contains($error, '530')
            || str_contains($error, 'access denied')
            || str_contains($error, 'authentication')
            || str_contains($error, 'login failed');
    }

    private function authHelpMessage(): string
    {
        $serverIp = $this->detectOutboundIp();

        return 'Autentificare esuata (530). FileZilla de pe PC-ul tau foloseste IP-ul tau; site-ul foloseste IP-ul serverului'
            . ($serverIp !== '' ? ' (' . $serverIp . ')' : '')
            . '. Cere furnizorului sa autorizeze IP-ul serverului web. Verifica si parola salvata in profil (reintrodu-o si salveaza).';
    }

    private function detectOutboundIp(): string
    {
        $context = stream_context_create(['http' => ['timeout' => 4]]);
        $ip = @file_get_contents('https://api.ipify.org', false, $context);

        return is_string($ip) ? trim($ip) : '';
    }

    private function humanizeCurlError(string $error): string
    {
        $error = trim($error);
        if ($error === '') {
            return 'Conexiune FTP esuata.';
        }

        if ($this->isAuthError($error)) {
            return $this->authHelpMessage();
        }

        if (stripos($error, 'timeout') !== false) {
            return 'Timeout la conexiunea FTP: ' . $error;
        }

        return $error;
    }
}
