<?php

declare(strict_types=1);

namespace Besoiu\Controllers\Scan;

/**
 * Management procese de fundal pentru scan/import (extras din ScanService).
 *
 * Responsabil de identificarea și terminarea proceselor PHP CLI legate de
 * import/cron (workeri, supervizor, teste) — cross-platform Windows/Linux.
 */
final class ScanProcessManager
{
    /**
     * Termină procesele PHP de fundal legate de import/cron (worker CLI, supervisor, teste).
     */
    public function terminateAllBackgroundImportProcesses(): int
    {
        $patterns = self::backgroundImportProcessPatterns();
        $currentPid = getmypid();
        $killed = 0;

        if (PHP_OS_FAMILY === 'Windows') {
            $out = [];
            @exec('wmic process where "name=\'php.exe\'" get ProcessId,CommandLine /FORMAT:CSV 2>nul', $out);
            foreach ($out as $line) {
                if ($line === '' || str_contains($line, 'CommandLine')) {
                    continue;
                }
                if (!preg_match('/,(\d+)\s*$/', $line, $m)) {
                    continue;
                }
                $procPid = (int) $m[1];
                if ($procPid <= 0 || $procPid === $currentPid) {
                    continue;
                }
                if (!$this->commandLineMatchesImportPatterns($line, $patterns)) {
                    continue;
                }
                if ($this->tryTerminateProcess($procPid)) {
                    ++$killed;
                }
            }

            return $killed;
        }

        foreach ($patterns as $pattern) {
            $escaped = escapeshellarg($pattern);
            @exec('pkill -f ' . $escaped . ' 2>/dev/null', $_, $code);
            if ($code === 0) {
                ++$killed;
            }
        }

        return $killed;
    }

    /**
     * Termină un proces după PID (taskkill pe Windows, SIGTERM→SIGKILL pe Linux).
     */
    public function tryTerminateProcess(int $pid): bool
    {
        if ($pid <= 0) {
            return false;
        }

        if (PHP_OS_FAMILY === 'Windows') {
            $out = [];
            @exec('taskkill /PID ' . $pid . ' /F /T 2>&1', $out, $code);

            return $code === 0;
        }

        if (function_exists('posix_kill')) {
            if (@posix_kill($pid, 15)) {
                usleep(200000);
                if (@posix_kill($pid, 0)) {
                    @posix_kill($pid, 9);
                }

                return true;
            }
            @posix_kill($pid, 9);

            return true;
        }

        return false;
    }

    /**
     * @return list<string>
     */
    public static function backgroundImportProcessPatterns(): array
    {
        return [
            'import_supervisor_cron.php',
            'run_consumable_publish_job.php',
            'test_cron_import_cli.php',
            'test_import_modes_live.php',
            'run_full_pipeline_20.php',
            'run_full_pipeline',
            'SupplierCronImportService',
            'import_cron_dual_scan',
            'scan_suppliers',
            'spawn_consumable_publish',
            'cleanup_import_full_reset.php',
        ];
    }

    /**
     * @param list<string> $patterns
     */
    private function commandLineMatchesImportPatterns(string $commandLine, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if ($pattern !== '' && stripos($commandLine, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }
}
