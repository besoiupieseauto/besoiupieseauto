<?php

declare(strict_types=1);

require_once __DIR__ . '/ScraperPaths.php';
require_once __DIR__ . '/ScraperLogger.php';
require_once __DIR__ . '/StealthBrowserProfile.php';
require_once __DIR__ . '/EpiesaCategoryParser.php';

/**
 * Fetch HTML via ScraperCore Python (curl_cffi TLS fingerprint) — fără Chrome.
 * Fallback rapid pentru ePiesa / eMAG; Autodoc rămâne pe StealthBrowserClient.
 */
final class HttpScraperClient
{
    private const DEFAULT_TIMEOUT_SEC = 60.0;

    /** @var bool|null */
    private static ?bool $availableCache = null;

    public static function isEnabled(): bool
    {
        $raw = strtolower(trim((string) (getenv('SCRAPER_HTTP_ENABLED') ?: $_ENV['SCRAPER_HTTP_ENABLED'] ?? '1')));

        return !in_array($raw, ['0', 'false', 'no', 'off'], true);
    }

    public static function isAvailable(): bool
    {
        if (!self::isEnabled()) {
            return false;
        }
        if (self::$availableCache !== null) {
            return self::$availableCache;
        }

        $python = self::pythonBinary();
        if ($python === null) {
            self::$availableCache = false;

            return false;
        }

        $coreDir = self::coreDir();
        $cmd = escapeshellarg($python) . ' -c "from scraper_core.core import ScraperCore"';
        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $proc = proc_open($cmd, $descriptors, $pipes, $coreDir, self::minimalPythonEnv());
        if (!is_resource($proc)) {
            self::$availableCache = false;

            return false;
        }
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($proc);
        self::$availableCache = $code === 0;

        return self::$availableCache;
    }

    public static function pythonBinary(): ?string
    {
        $root = ScraperPaths::projectRoot();
        $candidates = [
            trim((string) (getenv('SCRAPER_CORE_PYTHON') ?: '')),
            $root . '/tools/scraper-core/venv/Scripts/python.exe',
            $root . '/tools/scraper-core/venv/bin/python',
            trim((string) (getenv('STEALTH_BROWSER_PYTHON') ?: '')),
            $root . '/tools/stealth-browser-mcp/venv/Scripts/python.exe',
            $root . '/tools/stealth-browser-mcp/venv/bin/python',
        ];

        foreach ($candidates as $path) {
            if ($path !== '' && is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    public static function coreDir(): string
    {
        return ScraperPaths::projectRoot() . '/tools/scraper-core';
    }

    /**
     * Surse/host-uri unde HTTP (curl_cffi) e preferat înainte de Chrome.
     */
    public static function prefersHttpTransport(string $host, string $sourceId = ''): bool
    {
        if (str_contains(strtolower($host), 'autodoc')) {
            return false;
        }

        $sid = strtolower(trim($sourceId));
        if (in_array($sid, ['epiesa', 'emag', 'pieseauto'], true)) {
            return true;
        }

        $host = strtolower($host);
        foreach (['epiesa', 'emag', 'pieseauto'] as $needle) {
            if (str_contains($host, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $options source_id, timeout_sec, save_raw, html_marker, transport
     * @return array<string, mixed>
     */
    public static function fetch(string $url, array $options = []): array
    {
        $url = trim($url);
        if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException('URL invalid pentru HttpScraperClient.');
        }

        $python = self::pythonBinary();
        if ($python === null) {
            throw new RuntimeException(
                'ScraperCore indisponibil — rulează: php admin/tools/setup_scraper_core.php'
            );
        }

        $timeout = max(10.0, min(120.0, (float) ($options['timeout_sec'] ?? self::DEFAULT_TIMEOUT_SEC)));
        $sourceId = trim((string) ($options['source_id'] ?? ''));
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        require_once __DIR__ . '/EpiesaCategoryParser.php';
        $marker = trim((string) ($options['html_marker'] ?? ''));
        if ($marker === '' && str_contains($host, 'epiesa')) {
            $marker = EpiesaCategoryParser::stealthHtmlMarker();
        } elseif ($marker === '' && str_contains($host, 'emag')) {
            $marker = 'card-v2';
        }

        ScraperPaths::ensureDirs();
        $slug = preg_replace('/[^a-z0-9_-]+/i', '_', parse_url($url, PHP_URL_HOST) . '_' . date('Ymd_His')) ?? 'http';
        $htmlFile = ScraperPaths::rawDir() . '/http_' . $slug . '.html';

        $profile = StealthBrowserProfile::resolve($sourceId !== '' ? $sourceId : null, $host);

        $transport = trim((string) ($options['transport'] ?? 'curl_cffi'));
        $proxy = trim((string) ($options['proxy'] ?? getenv('SCRAPER_PROXY') ?: ''));

        $prevCoreUrl = getenv('SCRAPER_CORE_URL');
        putenv('SCRAPER_CORE_URL=' . $url);

        $cmd = escapeshellarg($python) . ' -m scraper_core.cli '
            . '--source-id ' . escapeshellarg($sourceId) . ' '
            . '--timeout ' . escapeshellarg((string) $timeout) . ' '
            . '--transport ' . escapeshellarg($transport) . ' '
            . '--no-jitter '
            . '--json '
            . '--html-file ' . escapeshellarg($htmlFile);

        if ($proxy !== '') {
            $cmd .= ' --proxy ' . escapeshellarg($proxy);
        }

        $started = microtime(true);
        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        try {
            $proc = proc_open($cmd, $descriptors, $pipes, self::coreDir(), self::pythonEnv($profile));
            if (!is_resource($proc)) {
                throw new RuntimeException('Nu am putut porni scraper_core.cli');
            }

            $stdout = stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $exitCode = proc_close($proc);
        } finally {
            if ($prevCoreUrl === false) {
                putenv('SCRAPER_CORE_URL');
            } else {
                putenv('SCRAPER_CORE_URL=' . $prevCoreUrl);
            }
        }

        if ($stderr !== '' && trim($stderr) !== '') {
            ScraperLogger::log('warn', 'HttpScraper stderr: ' . substr(trim($stderr), 0, 400));
        }

        $decoded = self::decodeJson($stdout);
        if (!is_array($decoded)) {
            throw new RuntimeException(
                'ScraperCore: răspuns invalid'
                . ($stdout !== '' ? ' — ' . substr(trim($stdout), 0, 300) : '')
            );
        }

        $html = (string) ($decoded['html'] ?? '');
        if ($html === '' && is_file($htmlFile)) {
            $html = (string) file_get_contents($htmlFile);
        }

        $success = !empty($decoded['success']) && $html !== '';
        $statusCode = (int) ($decoded['status_code'] ?? 0);

        if ($success && str_contains($host, 'epiesa')) {
            $success = EpiesaCategoryParser::htmlLooksLikeSearchPage($html);
        } elseif ($success && $marker !== '' && !str_contains($html, $marker)) {
            $success = false;
        }

        if (!$success) {
            $err = trim((string) ($decoded['error'] ?? ''));
            if ($err === '' && $marker !== '') {
                $err = "Marker «{$marker}» lipsă în HTML ({$statusCode})";
            }
            if ($err === '') {
                $err = 'fetch eșuat HTTP ' . $statusCode;
            }
            throw new RuntimeException('ScraperCore: ' . $err);
        }

        $durationMs = (int) ($decoded['duration_ms'] ?? round((microtime(true) - $started) * 1000));
        $engine = 'scraper_core_' . (string) ($decoded['transport'] ?? $transport);

        ScraperLogger::log(
            'info',
            'HttpScraper OK ' . $url . ' (' . strlen($html) . ' bytes, ' . $durationMs . 'ms, ' . $profile['label'] . ')'
        );

        return [
            'url' => $url,
            'final_url' => (string) ($decoded['final_url'] ?? $url),
            'title' => '',
            'html' => $html,
            'html_length' => strlen($html),
            'duration_ms' => $durationMs,
            'engine' => $engine,
            'raw_saved' => str_replace(ScraperPaths::projectRoot(), '', $htmlFile),
            'browser_profile' => [
                'source' => (string) ($profile['source'] ?? $sourceId),
                'label' => (string) ($profile['label'] ?? ''),
                'viewport' => 'http',
                'transport' => (string) ($decoded['transport'] ?? $transport),
            ],
            'status_code' => $statusCode,
            'attempts' => (int) ($decoded['attempts'] ?? 1),
        ];
    }

    /** @param array<string, mixed>|null $profile */
    private static function pythonEnv(?array $profile = null): array
    {
        $env = self::minimalPythonEnv();

        if (is_array($profile)) {
            $json = json_encode(StealthBrowserProfile::toJsonPayload($profile), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($json !== false) {
                $env['STEALTH_BROWSER_PROFILE'] = $json;
            }
        }

        $proxy = trim((string) (getenv('SCRAPER_PROXY') ?: ''));
        if ($proxy !== '') {
            $env['SCRAPER_PROXY'] = $proxy;
        }

        return $env;
    }

    /** @return array<string, string> */
    private static function minimalPythonEnv(): array
    {
        $python = self::pythonBinary() ?? '';
        $venvBin = $python !== '' ? dirname($python) : '';
        $path = $venvBin !== '' ? $venvBin . PATH_SEPARATOR . (getenv('PATH') ?: '') : (getenv('PATH') ?: '');

        $env = [
            'PATH' => $path,
            'SYSTEMROOT' => getenv('SYSTEMROOT') ?: 'C:\\Windows',
            'PYTHONPATH' => self::coreDir(),
            'PYTHONIOENCODING' => 'utf-8',
        ];

        $systemRoot = getenv('SystemRoot');
        if (is_string($systemRoot) && $systemRoot !== '') {
            $env['SystemRoot'] = $systemRoot;
        }

        return $env;
    }

    /** @return array<string, mixed>|null */
    private static function decodeJson(string $raw): ?array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        $decoded = json_decode($raw, true, 512, JSON_INVALID_UTF8_SUBSTITUTE);

        return is_array($decoded) ? $decoded : null;
    }
}
