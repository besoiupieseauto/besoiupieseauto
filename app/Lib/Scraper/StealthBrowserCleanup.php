<?php

declare(strict_types=1);

require_once __DIR__ . '/StealthBrowserMutex.php';
require_once __DIR__ . '/ScraperPaths.php';
require_once __DIR__ . '/ScraperLogger.php';

/**
 * Curățare după fetch stealth — mutex, lock stale, procese Chrome/Python zombie.
 */
final class StealthBrowserCleanup
{
    private static bool $shutdownRegistered = false;

    /** Înainte de pornire scraper (import/cron/test). */
    public static function prepareBeforeRun(): void
    {
        StealthBrowserMutex::tryBreakStaleLock(90);
        self::registerShutdownCleanup();
    }

    /** Oprește Chrome orphan dacă PHP moare (timeout web) înainte de finally. */
    public static function registerShutdownCleanup(): void
    {
        if (self::$shutdownRegistered) {
            return;
        }
        self::$shutdownRegistered = true;
        register_shutdown_function(static function (): void {
            if (!is_file(StealthBrowserMutex::lockPath())) {
                return;
            }
            StealthBrowserMutex::tryBreakStaleLock(0);
            self::killOrphanStealthProcesses();
            StealthBrowserMutex::forceClearLock();
        });
    }

    /**
     * După fiecare rulare sursă stealth — eliberează mutex + oprește procese orphan.
     *
     * @return array{killed:int, lock_cleared:bool}
     */
    public static function afterRun(bool $killOrphans = true): array
    {
        StealthBrowserMutex::release();
        StealthBrowserMutex::tryBreakStaleLock(60);
        $killed = $killOrphans ? self::killOrphanStealthProcesses() : 0;

        return ['killed' => $killed, 'lock_cleared' => !is_file(StealthBrowserMutex::lockPath())];
    }

    /** Reset complet — tool CLI / înainte de test manual. */
    public static function resetAll(): array
    {
        StealthBrowserMutex::forceClearLock();
        $killed = self::killOrphanStealthProcesses();
        usleep(500000);
        StealthBrowserMutex::forceClearLock();

        ScraperLogger::log('info', 'StealthBrowserCleanup reset: killed=' . $killed);

        return ['killed' => $killed, 'lock_cleared' => !is_file(StealthBrowserMutex::lockPath())];
    }

    /**
     * Șterge profiluri Chrome arse (turnstileBotCheck) — Autodoc folosește sesiune curată ca incognito.
     */
    public static function wipeAutodocProfiles(): void
    {
        $root = ScraperPaths::projectRoot() . '/storage/scraper';
        foreach (['stealth_profile', 'stealth_profile_autodoc', 'stealth_ephemeral'] as $dir) {
            $path = $root . '/' . $dir;
            if (!is_dir($path)) {
                continue;
            }
            self::deleteDirectory($path);
        }
    }

    private static function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = scandir($dir);
        if ($items === false) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                self::deleteDirectory($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }

    public static function killOrphanStealthProcesses(): int
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return self::killOrphanStealthProcessesWindows();
        }

        $killed = 0;
        exec("pkill -f 'stealth_browser_fetch.py' 2>/dev/null", $out, $code);
        if ($code === 0) {
            ++$killed;
        }

        return $killed;
    }

    private static function killOrphanStealthProcessesWindows(): int
    {
        $killed = 0;
        $ps = "Get-CimInstance Win32_Process -ErrorAction SilentlyContinue | Where-Object { "
            . "\$_.CommandLine -like '*stealth_browser_fetch*' -or "
            . "\$_.CommandLine -like '*stealth_profile*' -or "
            . "\$_.CommandLine -like '*stealth-browser-mcp*' } | ForEach-Object { "
            . "Stop-Process -Id \$_.ProcessId -Force -ErrorAction SilentlyContinue; "
            . "Write-Output ('killed:' + \$_.ProcessId) }";
        $cmd = 'powershell -NoProfile -ExecutionPolicy Bypass -Command ' . escapeshellarg($ps);
        $out = [];
        exec($cmd, $out);
        foreach ($out as $line) {
            if (str_starts_with((string) $line, 'killed:')) {
                ++$killed;
            }
        }

        return $killed;
    }
}
