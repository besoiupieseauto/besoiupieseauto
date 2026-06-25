<?php

declare(strict_types=1);

namespace Evasystem\Services;

/**
 * Client PHP → Cursor SDK (Python sau Node, agent local Composer 2.5).
 */
final class CursorImageAuditClient
{
    public function __construct(
        private readonly string $projectRoot,
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->apiKey() !== '';
    }

    public function apiKey(): string
    {
        $key = trim((string) ($_ENV['CURSOR_API_KEY'] ?? getenv('CURSOR_API_KEY') ?: ''));
        $key = trim($key, "\"'");
        if ($key !== '') {
            return $key;
        }

        $envFile = rtrim($this->projectRoot, '/\\') . '/admin/.env';
        if (!is_file($envFile)) {
            return '';
        }

        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines)) {
            return '';
        }

        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (str_starts_with($line, 'CURSOR_API_KEY=')) {
                return trim(trim(substr($line, 15)), "\"'");
            }
        }

        return '';
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

    private function cursorModel(): string
    {
        $envFile = rtrim($this->projectRoot, '/\\') . '/admin/system/env_settings.php';
        if (is_file($envFile)) {
            require_once $envFile;
            if (function_exists('besoiu_env_model_value')) {
                return besoiu_env_model_value('CURSOR_MODEL', 'composer-2.5');
            }
        }

        return trim((string) ($_ENV['CURSOR_MODEL'] ?? getenv('CURSOR_MODEL') ?: 'composer-2.5')) ?: 'composer-2.5';
    }

    /** @param array<string, string> $env */
    private function applyCursorEnv(array $env, string $apiKey): array
    {
        $env['CURSOR_API_KEY'] = $apiKey;
        $env['CURSOR_MODEL'] = $this->cursorModel();

        return $env;
    }

    /**
     * Pornește audit în fundal (pentru progress bar în admin).
     *
     * @return array{ok:bool,error?:string}
     */
    public function spawnBatchAudit(string $batchPath, string $jobPath): array
    {
        $apiKey = $this->apiKey();
        if ($apiKey === '') {
            return ['ok' => false, 'error' => 'CURSOR_API_KEY lipsește.'];
        }

        if (!is_file($batchPath)) {
            return ['ok' => false, 'error' => 'Lot batch negăsit.'];
        }

        if (!is_file($jobPath)) {
            return ['ok' => false, 'error' => 'Fișier job negăsit.'];
        }

        $runnerDir = $this->runnerDir();
        $projectRoot = rtrim($this->projectRoot, '/\\');
        $cmd = $this->buildRunnerCommand($runnerDir, $batchPath, $projectRoot, $jobPath, $apiKey);

        if ($cmd === null) {
            return [
                'ok' => false,
                'error' => 'Cursor SDK neinstalat. Rulează admin/tools/cursor-audit/install.bat',
            ];
        }

        $paths = (new ProductImageAuditService($this->projectRoot))->storagePaths();
        $logsDir = (string) ($paths['logs'] ?? $runnerDir);
        if (!is_dir($logsDir)) {
            mkdir($logsDir, 0775, true);
        }
        $logFile = $logsDir . '/cursor_audit_spawn.log';

        if (PHP_OS_FAMILY === 'Windows') {
            $batFile = $logsDir . '/spawn_' . bin2hex(random_bytes(4)) . '.bat';
            $batBody = '@echo off' . "\r\n"
                . 'set "CURSOR_API_KEY=' . str_replace(['"', '%'], '', $apiKey) . '"' . "\r\n"
                . 'set "CURSOR_MODEL=' . str_replace(['"', '%'], '', $this->cursorModel()) . '"' . "\r\n"
                . 'cd /d ' . escapeshellarg($runnerDir) . "\r\n"
                . $cmd . ' >> ' . escapeshellarg($logFile) . ' 2>&1' . "\r\n"
                . 'del "%~f0"' . "\r\n";
            if (@file_put_contents($batFile, $batBody) === false) {
                return ['ok' => false, 'error' => 'Nu am putut crea scriptul de pornire Cursor.'];
            }
            $bg = 'cmd /C start /B "" ' . escapeshellarg($batFile);
            $handle = @popen($bg, 'r');
            if ($handle === false) {
                return ['ok' => false, 'error' => 'Nu am putut porni procesul Cursor în fundal.'];
            }
            pclose($handle);
        } else {
            $full = 'CURSOR_API_KEY=' . escapeshellarg($apiKey)
                . ' CURSOR_MODEL=' . escapeshellarg($this->cursorModel()) . ' ' . $cmd
                . ' >> ' . escapeshellarg($logFile) . ' 2>&1 &';
            exec($full);
        }

        return ['ok' => true];
    }

    /**
     * @return array{ok:bool,error?:string,status?:string,summary?:string,engine?:string}
     */
    public function runBatchAudit(string $batchPath, int $timeoutSec = 600, string $jobPath = ''): array
    {
        $apiKey = $this->apiKey();
        if ($apiKey === '') {
            return [
                'ok' => false,
                'error' => 'CURSOR_API_KEY lipsește din admin/.env — generează cheie la cursor.com/dashboard → Integrations / API Keys.',
            ];
        }

        if (!is_file($batchPath)) {
            return ['ok' => false, 'error' => 'Lot batch negăsit: ' . $batchPath];
        }

        $runnerDir = $this->runnerDir();
        $projectRoot = rtrim($this->projectRoot, '/\\');
        $cmd = $this->buildRunnerCommand($runnerDir, $batchPath, $projectRoot, $jobPath, $apiKey);

        if ($cmd === null) {
            return [
                'ok' => false,
                'error' => 'Cursor SDK neinstalat. Rulează: py -3 -m pip install cursor-sdk --target admin/tools/cursor-audit/pydeps',
            ];
        }

        $env = $this->applyCursorEnv($_ENV, $apiKey);

        return $this->executeCommand($cmd, $runnerDir, $env, $timeoutSec);
    }

    private function runnerDir(): string
    {
        return rtrim($this->projectRoot, '/\\') . '/admin/tools/cursor-audit';
    }

    private function hasPythonSdk(string $runnerDir): bool
    {
        return is_dir($runnerDir . '/pydeps/cursor_sdk')
            || is_file($runnerDir . '/pydeps/cursor_sdk/__init__.py');
    }

    private function hasNodeSdk(string $runnerDir): bool
    {
        return is_dir($runnerDir . '/node_modules/@cursor/sdk');
    }

    private function buildRunnerCommand(
        string $runnerDir,
        string $batchPath,
        string $projectRoot,
        string $jobPath = '',
        string $apiKey = ''
    ): ?string {
        $jobArg = ' ' . escapeshellarg($jobPath !== '' ? $jobPath : '-');
        $keyArg = $apiKey !== '' ? (' ' . escapeshellarg($apiKey)) : '';

        $pyScript = $runnerDir . DIRECTORY_SEPARATOR . 'run.py';
        if (is_file($pyScript) && $this->hasPythonSdk($runnerDir)) {
            $python = $this->findPythonBinary();
            if ($python !== '') {
                $pyBin = str_contains($python, ' ') ? $python : escapeshellarg($python);

                return $pyBin . ' '
                    . escapeshellarg($pyScript) . ' '
                    . escapeshellarg($batchPath) . ' '
                    . escapeshellarg($projectRoot)
                    . $jobArg
                    . $keyArg;
            }
        }

        $nodeScript = $runnerDir . DIRECTORY_SEPARATOR . 'run.mjs';
        if (is_file($nodeScript) && $this->hasNodeSdk($runnerDir)) {
            $node = $this->findNodeBinary();
            if ($node !== '') {
                return escapeshellarg($node) . ' '
                    . escapeshellarg($nodeScript) . ' '
                    . escapeshellarg($batchPath) . ' '
                    . escapeshellarg($projectRoot)
                    . $jobArg
                    . $keyArg;
            }
        }

        return null;
    }

    /**
     * @param array<string, string> $env
     * @return array{ok:bool,error?:string,status?:string,summary?:string,engine?:string}
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
                    'error' => 'Timeout Cursor (' . $timeoutSec . 's). Verifică în Cursor IDE dacă agentul încă rulează.',
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
                $this->logApiUsage('image_audit');
                return $parsed;
            }

            return [
                'ok' => false,
                'error' => (string) ($parsed['error'] ?? 'Agent Cursor a eșuat.'),
                'status' => (string) ($parsed['status'] ?? ''),
                'summary' => (string) ($parsed['summary'] ?? ''),
            ];
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

    private function findNodeBinary(): string
    {
        foreach (['C:/Program Files/nodejs/node.exe', 'C:/laragon/bin/nodejs/node.exe'] as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        $localAppData = getenv('LOCALAPPDATA');
        if (is_string($localAppData) && $localAppData !== '') {
            $glob = glob($localAppData . '/Programs/*/node.exe') ?: [];
            foreach ($glob as $path) {
                if (is_file($path) && stripos($path, 'nodejs') !== false) {
                    return $path;
                }
            }
        }

        foreach (['node', 'node.exe'] as $bin) {
            $out = [];
            $code = 1;
            @exec(escapeshellarg($bin) . ' -v 2>&1', $out, $code);
            if ($code === 0) {
                return $bin;
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

        if (preg_match('/\{[\s\S]*\}$/', $stdout, $m)) {
            $decoded = json_decode($m[0], true);

            return is_array($decoded) ? $decoded : null;
        }

        return null;
    }
}
