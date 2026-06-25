<?php

declare(strict_types=1);

namespace Evasystem\Services\PieseAuto;

/**
 * Pornește robot_pieseauto.py pe Windows (local).
 */
final class PieseAutoRobotLauncher
{
    /** @return array{success:bool,message:string,online?:bool,already_running?:bool,starting?:bool,robot?:string} */
    public function ensureRunning(bool $force = false): array
    {
        if (!PieseAutoRobotConfig::autoStartEnabled() && !$force) {
            return [
                'success' => false,
                'message' => 'Pornire automată dezactivată (ROBOT_AUTO_START=0).',
                'online' => PieseAutoRobotConfig::ping(),
                'robot' => 'pieseauto',
            ];
        }

        $baseUrl = PieseAutoRobotConfig::effectiveUrl();
        $host = strtolower((string) (parse_url($baseUrl, PHP_URL_HOST) ?? ''));
        if (!in_array($host, ['127.0.0.1', 'localhost', '::1'], true)) {
            return [
                'success' => false,
                'message' => 'Pornire automată doar pentru URL local (127.0.0.1).',
                'online' => PieseAutoRobotConfig::ping(),
                'robot' => 'pieseauto',
            ];
        }

        if (PieseAutoRobotConfig::ping()) {
            return [
                'success' => true,
                'message' => 'PieseAuto rulează deja.',
                'online' => true,
                'already_running' => true,
                'robot' => 'pieseauto',
            ];
        }

        $lock = PieseAutoRobotConfig::robotDir() . DIRECTORY_SEPARATOR . PieseAutoRobotConfig::lockFileName();
        if (is_file($lock) && (time() - (int) filemtime($lock)) < 12) {
            return [
                'success' => true,
                'message' => 'PieseAuto se pornește...',
                'online' => false,
                'starting' => true,
                'robot' => 'pieseauto',
            ];
        }

        @touch($lock, time());
        $spawned = $this->spawnProcess();
        if (!$spawned) {
            @unlink($lock);

            return [
                'success' => false,
                'message' => 'Nu am putut porni PieseAuto. Rulează robot\\start_pieseauto_visible.bat.',
                'online' => false,
                'robot' => 'pieseauto',
            ];
        }

        return [
            'success' => true,
            'message' => 'PieseAuto pornit.',
            'online' => PieseAutoRobotConfig::ping(),
            'already_running' => false,
            'robot' => 'pieseauto',
        ];
    }

    private function spawnProcess(): bool
    {
        $robotDir = PieseAutoRobotConfig::robotDir();
        $script = $robotDir . DIRECTORY_SEPARATOR . 'robot_pieseauto.py';
        if (!is_file($script)) {
            return false;
        }

        if (PHP_OS_FAMILY === 'Windows') {
            $ps1 = $robotDir . DIRECTORY_SEPARATOR . 'ensure_pieseauto.ps1';
            if (is_file($ps1)) {
                $cmd = 'powershell.exe -NoProfile -ExecutionPolicy Bypass -File ' . escapeshellarg($ps1);
                if (function_exists('proc_open')) {
                    $descriptors = [0 => ['file', 'NUL', 'r'], 1 => ['file', 'NUL', 'w'], 2 => ['file', 'NUL', 'w']];
                    $process = @proc_open($cmd, $descriptors, $pipes);
                    if (is_resource($process)) {
                        $exit = proc_close($process);

                        return $exit === 0 || PieseAutoRobotConfig::ping();
                    }
                }
                exec($cmd, $out, $exit);

                return $exit === 0 || PieseAutoRobotConfig::ping();
            }
        }

        $vbs = $robotDir . DIRECTORY_SEPARATOR . 'start_pieseauto_hidden.vbs';
        if (PHP_OS_FAMILY === 'Windows' && is_file($vbs)) {
            $cmd = 'wscript.exe //B "' . str_replace('"', '""', $vbs) . '"';
        } else {
            $log = $robotDir . DIRECTORY_SEPARATOR . 'robot_pieseauto_service.log';
            $python = $this->pythonBin();
            $cmd = 'cd ' . escapeshellarg($robotDir)
                . ' && nohup ' . escapeshellarg($python)
                . ' robot_pieseauto.py >> ' . escapeshellarg($log) . ' 2>&1 &';
        }

        if (function_exists('proc_open')) {
            $descriptors = PHP_OS_FAMILY === 'Windows'
                ? [0 => ['file', 'NUL', 'r'], 1 => ['file', 'NUL', 'w'], 2 => ['file', 'NUL', 'w']]
                : [0 => ['file', '/dev/null', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']];
            $process = @proc_open($cmd, $descriptors, $pipes);
            if (is_resource($process)) {
                @proc_close($process);
                sleep(4);

                return PieseAutoRobotConfig::ping();
            }
        }

        @exec($cmd);
        sleep(4);

        return PieseAutoRobotConfig::ping();
    }

    private function pythonBin(): string
    {
        $fromEnv = trim((string) (getenv('ROBOT_PYTHON') ?: ($_ENV['ROBOT_PYTHON'] ?? '')));
        $candidates = array_filter([
            $fromEnv,
            'C:\\laragon\\bin\\python\\python-3.13\\python.exe',
            'python',
        ]);

        foreach ($candidates as $bin) {
            if ($bin === 'python' || is_file($bin)) {
                return $bin;
            }
        }

        return 'python';
    }
}
