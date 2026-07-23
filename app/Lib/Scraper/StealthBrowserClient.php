<?php

declare(strict_types=1);

require_once __DIR__ . '/ScraperPaths.php';
require_once __DIR__ . '/ScraperLogger.php';
require_once __DIR__ . '/StealthBrowserMutex.php';
require_once __DIR__ . '/StealthBrowserProfile.php';

/**
 * Fetch HTML local via nodriver (stealth-browser-mcp venv) — alternativă la scrape.do.
 */
final class StealthBrowserClient
{
    private const DEFAULT_WAIT_SEC = 3.0;
    private const DEFAULT_TIMEOUT_SEC = 120.0;

    public static function isAvailable(): bool
    {
        return self::pythonBinary() !== null && is_file(self::fetchScriptPath());
    }

    public static function pythonBinary(): ?string
    {
        $candidates = [
            ScraperPaths::projectRoot() . '/tools/stealth-browser-mcp/venv/Scripts/python.exe',
            ScraperPaths::projectRoot() . '/tools/stealth-browser-mcp/venv/bin/python',
        ];

        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        $env = trim((string) (getenv('STEALTH_BROWSER_PYTHON') ?: ''));
        if ($env !== '' && is_file($env)) {
            return $env;
        }

        return null;
    }

    public static function fetchScriptPath(): string
    {
        return ScraperPaths::projectRoot() . '/tools/stealth_browser_fetch.py';
    }

    /**
     * @param array<string, mixed> $options wait_sec, timeout_sec, save_raw
     * @return array<string, mixed>
     */
    public static function fetch(string $url, array $options = []): array
    {
        $url = trim($url);
        if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException('URL invalid pentru stealth browser.');
        }

        $python = self::pythonBinary();
        if ($python === null) {
            throw new RuntimeException(
                'Stealth browser indisponibil — rulează: py -3 -m venv tools/stealth-browser-mcp/venv && pip install nodriver'
            );
        }

        $script = self::fetchScriptPath();
        if (!is_file($script)) {
            throw new RuntimeException('Lipsește tools/stealth_browser_fetch.py');
        }

        $wait = max(0.0, min(30.0, (float) ($options['wait_sec'] ?? self::DEFAULT_WAIT_SEC)));
        $requestedTimeout = isset($options['timeout_sec']) ? (float) $options['timeout_sec'] : null;
        $timeout = max(15.0, min(180.0, (float) ($options['timeout_sec'] ?? self::DEFAULT_TIMEOUT_SEC)));
        $marker = trim((string) ($options['html_marker'] ?? ''));
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if ($marker === '' && str_contains($host, 'autodoc')) {
            $marker = 'listing-item__wrap';
            $wait = max($wait, 12.0);
            if ($requestedTimeout === null || $requestedTimeout >= 90.0) {
                $timeout = max($timeout, 150.0);
            }
        } elseif ($marker === '' && str_contains($host, 'epiesa')) {
            $marker = 'prod-card';
            $wait = max($wait, 10.0);
            if ($requestedTimeout === null || $requestedTimeout >= 60.0) {
                $timeout = max($timeout, 75.0);
            }
        } elseif ($marker === '' && str_contains($host, 'emag')) {
            $marker = 'card-v2';
            $wait = max($wait, 8.0);
        }

        ScraperPaths::ensureDirs();
        $slug = preg_replace('/[^a-z0-9_-]+/i', '_', parse_url($url, PHP_URL_HOST) . '_' . date('Ymd_His')) ?? 'stealth';
        $htmlFile = ScraperPaths::rawDir() . '/stealth_' . $slug . '.html';

        $prevEnv = getenv('STEALTH_BROWSER_URL');
        putenv('STEALTH_BROWSER_URL=' . $url);

        $prevCfWait = getenv('STEALTH_BROWSER_CF_WAIT');
        if (str_contains($host, 'autodoc') && $prevCfWait === false) {
            $cfWait = ($requestedTimeout !== null && $requestedTimeout <= 60.0) ? 40 : 120;
            putenv('STEALTH_BROWSER_CF_WAIT=' . $cfWait);
        }

        $headlessFlag = self::headlessCliFlag($host, $options);

        $sourceId = trim((string) ($options['source_id'] ?? ''));
        $browserProfile = StealthBrowserProfile::resolve($sourceId !== '' ? $sourceId : null, $host);
        $profileJson = json_encode(
            StealthBrowserProfile::toJsonPayload($browserProfile),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        ) ?: '{}';

        $cmd = escapeshellarg($python) . ' '
            . escapeshellarg($script) . ' '
            . '--wait ' . escapeshellarg((string) $wait) . ' '
            . '--timeout ' . escapeshellarg((string) $timeout) . ' '
            . ($marker !== '' ? '--marker ' . escapeshellarg($marker) . ' ' : '')
            . $headlessFlag
            . '--json '
            . '--html-file ' . escapeshellarg($htmlFile);

        $started = microtime(true);
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $procSlack = str_contains($host, 'autodoc') ? ($timeout * 2.0 + 180.0) : 45.0;
        $mutexWait = max(60, (int) ceil($timeout + $procSlack));

        $prevProfile = getenv('STEALTH_BROWSER_PROFILE');
        putenv('STEALTH_BROWSER_PROFILE=' . $profileJson);

        try {
            return StealthBrowserMutex::run(
                fn (): array => self::executeFetch(
                    $cmd,
                    $htmlFile,
                    $url,
                    $started,
                    $options,
                    $timeout,
                    $procSlack,
                    $browserProfile
                ),
                $mutexWait
            );
        } finally {
            if ($prevProfile === false) {
                putenv('STEALTH_BROWSER_PROFILE');
            } else {
                putenv('STEALTH_BROWSER_PROFILE=' . $prevProfile);
            }
            if ($prevCfWait === false) {
                putenv('STEALTH_BROWSER_CF_WAIT');
            } elseif ($prevCfWait !== false) {
                putenv('STEALTH_BROWSER_CF_WAIT=' . $prevCfWait);
            }
            if ($prevEnv === false) {
                putenv('STEALTH_BROWSER_URL');
            } else {
                putenv('STEALTH_BROWSER_URL=' . $prevEnv);
            }
        }
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    /** @param array<string, mixed> $browserProfile */
    private static function executeFetch(
        string $cmd,
        string $htmlFile,
        string $url,
        float $started,
        array $options,
        float $timeout,
        float $procSlack = 45.0,
        array $browserProfile = []
    ): array {
        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $proc = proc_open($cmd, $descriptors, $pipes);
        if (!is_resource($proc)) {
            throw new RuntimeException('Nu am putut porni stealth_browser_fetch.py');
        }

        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $deadline = microtime(true) + $timeout + $procSlack;
        $stdout = '';
        $stderr = '';
        while (microtime(true) < $deadline) {
            $stdout .= (string) stream_get_contents($pipes[1]);
            $stderr .= (string) stream_get_contents($pipes[2]);
            $status = proc_get_status($proc);
            if (!$status['running']) {
                break;
            }
            usleep(100000);
        }

        $stdout .= (string) stream_get_contents($pipes[1]);
        $stderr .= (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $status = proc_get_status($proc);
        if ($status['running']) {
            @proc_terminate($proc, 9);
            usleep(300000);
            @proc_close($proc);
            throw new RuntimeException(
                'Stealth browser: timeout proces după ' . (int) $timeout . 's — Chrome oprit forțat.'
            );
        }

        proc_close($proc);
        $rawOut = $stdout;

        if ($stderr !== '' && trim($stderr) !== '') {
            ScraperLogger::log('warn', 'StealthBrowser stderr: ' . substr(trim($stderr), 0, 500));
        }

        $decoded = self::decodeJsonPayload($rawOut);
        if (!is_array($decoded)) {
            throw new RuntimeException(
                'Stealth browser: răspuns invalid'
                . ($rawOut !== '' ? ' — ' . substr(trim($rawOut), 0, 400) : '')
            );
        }

        $html = (string) ($decoded['html'] ?? '');
        if (empty($decoded['success'])) {
            $err = trim((string) ($decoded['error'] ?? 'eșec necunoscut'));
            if (!empty($decoded['cloudflare_challenge'])) {
                $err = $err !== '' ? $err : 'Cloudflare Turnstile — conținut blocat';
            }
            $engine = (string) ($decoded['engine'] ?? '');
            if ($engine !== '') {
                $err .= ' [' . $engine . ']';
            }
            throw new RuntimeException('Stealth browser: ' . ($err !== '' ? $err : 'fetch eșuat'));
        }

        $htmlLength = (int) ($decoded['html_length'] ?? 0);
        if ($html !== '') {
            $htmlLength = strlen($html);
        } elseif (is_file($htmlFile)) {
            $htmlLength = (int) filesize($htmlFile);
            // HTML mare rămâne pe disc — încărcare lazy prin resolveHtmlBody().
            if (!empty($options['load_html_body'])) {
                $html = (string) file_get_contents($htmlFile);
                $htmlLength = strlen($html);
            }
        }

        $durationMs = (int) ($decoded['duration_ms'] ?? round((microtime(true) - $started) * 1000));

        $profileLabel = trim((string) ($browserProfile['label'] ?? ''));
        ScraperLogger::log(
            'info',
            'StealthBrowser OK ' . $url . ' (' . $htmlLength . ' bytes, ' . $durationMs . 'ms'
            . ($profileLabel !== '' ? ', UA: ' . $profileLabel : '') . ')'
        );

        self::logBudgetUsage($url, $durationMs);

        return [
            'url' => $url,
            'final_url' => (string) ($decoded['final_url'] ?? $url),
            'title' => (string) ($decoded['title'] ?? ''),
            'html' => $html,
            'html_length' => $htmlLength,
            'duration_ms' => $durationMs,
            'engine' => (string) ($decoded['engine'] ?? 'stealth_browser_mcp'),
            'raw_saved' => $htmlFile !== '' ? str_replace(ScraperPaths::projectRoot(), '', $htmlFile) : '',
            'browser_profile' => [
                'source' => (string) ($browserProfile['source'] ?? ''),
                'label' => $profileLabel,
                'viewport' => ((int) ($browserProfile['viewport_width'] ?? 0)) . 'x'
                    . ((int) ($browserProfile['viewport_height'] ?? 0)),
            ],
        ];
    }

    /**
     * Citește corp HTML din răspunsul fetch (inline sau fișier raw_saved).
     *
     * @param array<string, mixed> $fetch
     */
    public static function resolveHtmlBody(array $fetch, int $maxBytes = 0): string
    {
        $inline = (string) ($fetch['html'] ?? '');
        if ($inline !== '') {
            if ($maxBytes > 0 && strlen($inline) > $maxBytes) {
                return substr($inline, 0, $maxBytes);
            }

            return $inline;
        }

        $raw = trim((string) ($fetch['raw_saved'] ?? ''));
        if ($raw === '') {
            return '';
        }

        $path = ScraperPaths::projectRoot() . $raw;
        if (!is_file($path)) {
            return '';
        }

        if ($maxBytes <= 0) {
            return (string) file_get_contents($path);
        }

        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            return '';
        }
        $chunk = (string) fread($handle, $maxBytes);
        fclose($handle);

        return $chunk;
    }

    private static function logBudgetUsage(string $url, int $durationMs): void
    {
        $budgetPath = dirname(__DIR__, 2) . '/admin/system/api_token_budget.php';
        if (!is_file($budgetPath)) {
            return;
        }
        require_once $budgetPath;
        if (!function_exists('api_token_budget_log')) {
            return;
        }
        $host = (string) parse_url($url, PHP_URL_HOST);
        $note = 'fetch ok' . ($host !== '' ? ' · ' . $host : '') . ' · ' . $durationMs . 'ms';
        api_token_budget_log('stealth_browser', 0, 'stealth_browser_client', $note);
    }

    private static function headlessCliFlag(string $host, array $options = []): string
    {
        if (!empty($options['force_headless']) || !empty($options['background_job'])) {
            return '--headless ';
        }

        $env = strtolower(trim((string) (getenv('STEALTH_BROWSER_HEADLESS') ?: '')));
        if ($env === '0' || $env === 'false' || $env === 'no') {
            return '--no-headless ';
        }
        if ($env === '1' || $env === 'true' || $env === 'yes') {
            return '--headless ';
        }

        // Autodoc vizibil doar la test manual din /admin/scraper (Turnstile).
        if (str_contains(strtolower($host), 'autodoc')) {
            return '--no-headless ';
        }

        return '--headless ';
    }

    /** @return array<string, mixed>|null */
    private static function decodeJsonPayload(string $raw): ?array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true, 512, JSON_INVALID_UTF8_SUBSTITUTE);
        if (is_array($decoded)) {
            return $decoded;
        }

        // JSON pe ultima linie (warning-uri nodriver pe stderr redirecționat)
        $lines = preg_split('/\r\n|\r|\n/', $raw) ?: [];
        for ($i = count($lines) - 1; $i >= 0; $i--) {
            $line = trim((string) $lines[$i]);
            if ($line === '' || $line[0] !== '{') {
                continue;
            }
            $try = json_decode($line, true, 512, JSON_INVALID_UTF8_SUBSTITUTE);
            if (is_array($try)) {
                return $try;
            }
        }

        $start = strpos($raw, '{');
        $end = strrpos($raw, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $try = json_decode(substr($raw, $start, $end - $start + 1), true, 512, JSON_INVALID_UTF8_SUBSTITUTE);

            return is_array($try) ? $try : null;
        }

        return null;
    }
}
