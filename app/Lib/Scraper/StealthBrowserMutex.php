<?php

declare(strict_types=1);

require_once __DIR__ . '/ScraperPaths.php';

/**
 * Mutex global — un singur fetch Chrome stealth activ (profil partajat).
 */
final class StealthBrowserMutex
{
    /** @var resource|null */
    private static $holdFd = null;

    public static function lockPath(): string
    {
        ScraperPaths::ensureDirs();
        $dir = ScraperPaths::projectRoot() . '/storage/scraper';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        return $dir . '/stealth_chrome.lock';
    }

    public static function acquire(int $maxWaitSec = 120): bool
    {
        if (self::$holdFd !== null) {
            return true;
        }

        self::tryBreakStaleLock();

        $deadline = microtime(true) + max(1, $maxWaitSec);
        while (microtime(true) < $deadline) {
            $fp = @fopen(self::lockPath(), 'c+b');
            if ($fp === false) {
                usleep(250000);
                continue;
            }
            if (@flock($fp, LOCK_EX | LOCK_NB)) {
                self::$holdFd = $fp;
                ftruncate($fp, 0);
                fwrite($fp, json_encode([
                    'pid' => getmypid(),
                    'at' => date('c'),
                ], JSON_UNESCAPED_UNICODE));
                fflush($fp);

                return true;
            }
            fclose($fp);
            self::tryBreakStaleLock();
            usleep(400000);
        }

        return false;
    }

    public static function forceClearLock(): void
    {
        self::release();
        $path = self::lockPath();
        if (is_file($path)) {
            @unlink($path);
        }
    }

    /** Eliberează lock dacă procesul holder nu mai rulează (Chrome/zombie import). */
    public static function tryBreakStaleLock(int $maxAgeSec = 300): void
    {
        $path = self::lockPath();
        if (!is_file($path)) {
            return;
        }

        $meta = json_decode((string) @file_get_contents($path), true);
        $pid = is_array($meta) ? (int) ($meta['pid'] ?? 0) : 0;
        $at = is_array($meta) ? strtotime((string) ($meta['at'] ?? '')) : false;
        $ageOk = $at !== false && (time() - $at) >= max(60, $maxAgeSec);
        $pidDead = $pid > 0 && !self::pidIsAlive($pid);

        if (!$pidDead && !$ageOk) {
            return;
        }

        $fp = @fopen($path, 'c+b');
        if ($fp === false) {
            return;
        }
        if (@flock($fp, LOCK_EX | LOCK_NB)) {
            @flock($fp, LOCK_UN);
            @fclose($fp);
            @unlink($path);

            return;
        }
        @fclose($fp);
    }

    private static function pidIsAlive(int $pid): bool
    {
        if ($pid <= 0) {
            return false;
        }
        if (PHP_OS_FAMILY === 'Windows') {
            $out = shell_exec('tasklist /FI "PID eq ' . $pid . '" /NH 2>NUL');

            return is_string($out) && preg_match('/\b' . preg_quote((string) $pid, '/') . '\b/', $out) === 1;
        }
        if (function_exists('posix_kill')) {
            return @posix_kill($pid, 0);
        }

        return true;
    }

    public static function release(): void
    {
        if (self::$holdFd === null) {
            return;
        }
        @flock(self::$holdFd, LOCK_UN);
        @fclose(self::$holdFd);
        self::$holdFd = null;
    }

    /**
     * @template T
     * @param callable(): T $fn
     * @return T
     */
    public static function run(callable $fn, int $maxWaitSec = 120)
    {
        if (!self::acquire($maxWaitSec)) {
            throw new RuntimeException(
                'Stealth browser ocupat — altă operație Chrome rulează pe același profil. Așteaptă sau oprește importul/scraperul.'
            );
        }

        try {
            return $fn();
        } finally {
            self::release();
        }
    }
}
