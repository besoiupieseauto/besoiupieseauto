<?php

declare(strict_types=1);

require_once __DIR__ . '/ScraperLlmConfig.php';

/**
 * Client PHP → Cursor SDK (Composer 2.5) pentru analiză HTML scraper.
 */
final class CursorScraperAgentClient
{
    public function __construct(
        private readonly string $projectRoot,
    ) {
    }

    public function isConfigured(): bool
    {
        return ScraperLlmConfig::cursorKey() !== '';
    }

    /**
     * @param array<string, mixed> $request
     * @return array<string, mixed>
     */
    public function analyzeHtml(array $request, int $timeoutSec = 180): array
    {
        $apiKey = ScraperLlmConfig::cursorKey();
        if ($apiKey === '') {
            return [
                'ok' => false,
                'error' => 'CURSOR_API_KEY lipsește din admin/.env.',
            ];
        }

        $runnerDir = $this->runnerDir();
        $pyScript = $runnerDir . DIRECTORY_SEPARATOR . 'run.py';
        if (!is_file($pyScript) || !$this->hasPythonSdk($runnerDir)) {
            return [
                'ok' => false,
                'error' => 'Cursor SDK neinstalat. Rulează admin/tools/cursor-audit/install.bat',
            ];
        }

        $python = $this->findPythonBinary();
        if ($python === '') {
            return ['ok' => false, 'error' => 'Python 3 nu a fost găsit pe server.'];
        }

        $reqDir = rtrim($this->projectRoot, '/\\') . '/storage/scraper/cursor_requests';
        if (!is_dir($reqDir)) {
            mkdir($reqDir, 0775, true);
        }

        $reqPath = $reqDir . '/req_' . bin2hex(random_bytes(8)) . '.json';
        $encoded = json_encode($request, JSON_UNESCAPED_UNICODE);
        if ($encoded === false || @file_put_contents($reqPath, $encoded) === false) {
            return ['ok' => false, 'error' => 'Nu am putut scrie fișierul request Cursor.'];
        }

        $projectRoot = rtrim($this->projectRoot, '/\\');
        $pyBin = str_contains($python, ' ') ? $python : escapeshellarg($python);
        $cmd = $pyBin . ' '
            . escapeshellarg($pyScript) . ' '
            . escapeshellarg($reqPath) . ' '
            . escapeshellarg($projectRoot) . ' '
            . escapeshellarg($apiKey);

        $env = $_ENV;
        $env['CURSOR_API_KEY'] = $apiKey;
        $env['CURSOR_MODEL'] = ScraperLlmConfig::cursorModel();

        try {
            return $this->executeCommand($cmd, $runnerDir, $env, $timeoutSec);
        } finally {
            @unlink($reqPath);
        }
    }

    private function logApiUsage(string $source): void
    {
        $budgetFile = rtrim($this->projectRoot, '/\\') . '/admin/system/api_token_budget.php';
        if (!is_file($budgetFile)) {
            return;
        }
        require_once $budgetFile;
        if (function_exists('api_token_budget_log')) {
            api_token_budget_log('cursor', 1, $source);
        }
    }

    private function runnerDir(): string
    {
        return rtrim($this->projectRoot, '/\\') . '/admin/tools/cursor-scraper';
    }

    private function hasPythonSdk(string $runnerDir): bool
    {
        if (is_dir($runnerDir . '/pydeps/cursor_sdk')) {
            return true;
        }

        $auditDeps = dirname($runnerDir) . '/cursor-audit/pydeps/cursor_sdk';

        return is_dir($auditDeps);
    }

    /**
     * @param array<string, string> $env
     * @return array<string, mixed>
     */
    private function executeCommand(string $cmd, string $cwd, array $env, int $timeoutSec): array
    {
        $descriptor = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $proc = proc_open($cmd, $descriptor, $pipes, $cwd, $env);
        if (!is_resource($proc)) {
            return ['ok' => false, 'error' => 'Nu am putut porni agentul Cursor.'];
        }

        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $stdout = '';
        $stderr = '';
        $start = time();

        while (true) {
            $stdout .= (string) stream_get_contents($pipes[1]);
            $stderr .= (string) stream_get_contents($pipes[2]);

            $status = proc_get_status($proc);
            if (!$status['running']) {
                $stdout .= (string) stream_get_contents($pipes[1]);
                $stderr .= (string) stream_get_contents($pipes[2]);
                break;
            }

            if ((time() - $start) > $timeoutSec) {
                proc_terminate($proc);
                fclose($pipes[1]);
                fclose($pipes[2]);
                proc_close($proc);

                return [
                    'ok' => false,
                    'error' => 'Timeout Cursor (' . $timeoutSec . 's). Verifică Cursor IDE / rețea.',
                ];
            }

            usleep(250000);
        }

        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($proc);

        $parsed = $this->parseRunnerJson($stdout);
        if ($parsed !== null) {
            if (!empty($parsed['ok'])) {
                $this->logApiUsage('scraper_agent');
            }
            return $parsed;
        }

        $err = trim($stderr !== '' ? $stderr : $stdout);

        return [
            'ok' => false,
            'error' => $err !== '' ? mb_substr($err, 0, 500, 'UTF-8') : ('Cursor exit code ' . $exitCode),
        ];
    }

    private function findPythonBinary(): string
    {
        foreach (['py -3', 'python', 'python3'] as $bin) {
            $out = [];
            $code = 1;
            @exec($bin . ' --version 2>&1', $out, $code);
            if ($code === 0) {
                return $bin;
            }
        }

        $localAppData = getenv('LOCALAPPDATA');
        if (is_string($localAppData) && $localAppData !== '') {
            $glob = glob($localAppData . '/Programs/Python/Python*/python.exe') ?: [];
            rsort($glob);
            foreach ($glob as $path) {
                if (is_file($path)) {
                    return $path;
                }
            }
        }

        foreach (['C:/laragon/bin/python/python/python.exe'] as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return '';
    }

    /** @return array<string, mixed>|null */
    private function parseRunnerJson(string $stdout): ?array
    {
        $stdout = trim($stdout);
        if ($stdout === '') {
            return null;
        }

        $decoded = json_decode($stdout, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        if (preg_match('/\{[\s\S]*\}$/u', $stdout, $m)) {
            $decoded = json_decode($m[0], true);

            return is_array($decoded) ? $decoded : null;
        }

        return null;
    }
}
