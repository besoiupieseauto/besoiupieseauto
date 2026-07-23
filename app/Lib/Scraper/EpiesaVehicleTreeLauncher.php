<?php

declare(strict_types=1);

require_once __DIR__ . '/EpiesaVehicleTreeCrawler.php';
require_once __DIR__ . '/EpiesaVehicleTreeStore.php';
require_once __DIR__ . '/ScraperPaths.php';
require_once __DIR__ . '/ScraperLogger.php';

/**
 * Pornește crawl arbore vehicule ePiesa în fundal (CLI, fără timeout HTTP).
 */
final class EpiesaVehicleTreeLauncher
{
    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public static function spawnBackground(array $options = []): array
    {
        if (EpiesaVehicleTreeStore::isCrawlRunning()) {
            return [
                'ok' => false,
                'running' => true,
                'message' => 'Botul rulează deja — vezi progresul mai jos.',
                'status' => EpiesaVehicleTreeCrawler::status(),
            ];
        }

        ScraperPaths::ensureDirs();
        $job = [
            'created_at' => date('c'),
            'options' => self::normalizeOptions($options),
        ];
        file_put_contents(
            ScraperPaths::epiesaVehicleCrawlJobPath(),
            json_encode($job, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            LOCK_EX
        );

        $php = self::resolvePhpCli();
        $root = ScraperPaths::projectRoot();
        $script = $root . '/admin/cron_cli/epiesa_vehicle_tree_crawl.php';
        if (!is_file($script)) {
            throw new RuntimeException('Script CLI lipsă: admin/cron_cli/epiesa_vehicle_tree_crawl.php');
        }

        $logFile = ScraperPaths::logsDir() . '/epiesa_vehicle_tree_crawl.log';
        $cmd = escapeshellarg($php) . ' ' . escapeshellarg($script) . ' --job';

        $spawned = self::spawnDetached($cmd, $root, $logFile);
        if (!$spawned) {
            throw new RuntimeException('Nu am putut porni botul în fundal. Rulează manual: php admin/cron_cli/epiesa_vehicle_tree_crawl.php --job');
        }

        $state = [
            'status' => 'running',
            'started_at' => date('c'),
            'finished_at' => null,
            'spawned_via' => 'background',
            'last_progress_at' => date('c'),
            'processed_brands' => 0,
            'skipped_brands' => 0,
            'errors' => [],
            'cancel_requested' => false,
        ];
        EpiesaVehicleTreeStore::saveCrawlState($state);

        ScraperLogger::log('info', 'ePiesa arbore vehicule — bot pornit în fundal');

        return [
            'ok' => true,
            'running' => true,
            'message' => 'Bot pornit în fundal. Salvează mărci progresiv — poți închide pagina.',
            'log_file' => $logFile,
            'status' => EpiesaVehicleTreeCrawler::status(),
        ];
    }

    /** @param array<string, mixed> $options @return array<string, mixed> */
    private static function normalizeOptions(array $options): array
    {
        return [
            'resume' => !array_key_exists('resume', $options) || !empty($options['resume']),
            'delay_ms' => max(0, min(2000, (int) ($options['delay_ms'] ?? 120))),
            'max_brands' => max(0, (int) ($options['max_brands'] ?? 0)),
            'max_models_per_brand' => max(0, (int) ($options['max_models_per_brand'] ?? 0)),
        ];
    }

    private static function resolvePhpCli(): string
    {
        $tools = ScraperPaths::projectRoot() . '/admin/tools/php_cli.php';
        if (is_file($tools)) {
            require_once $tools;
            try {
                return admin_php_cli_binary();
            } catch (Throwable) {
                // fallback
            }
        }

        $phpBin = PHP_BINARY;
        if (is_string($phpBin) && $phpBin !== '' && is_file($phpBin)
            && stripos($phpBin, 'httpd') === false && stripos($phpBin, 'apache') === false) {
            return $phpBin;
        }

        $roots = ['F:/laragon', 'E:/laragon', 'C:/laragon', 'f:/laragon', 'e:/laragon'];
        foreach ($roots as $laragon) {
            $glob = glob($laragon . '/bin/php/php-*/php.exe');
            if (is_array($glob)) {
                rsort($glob);
                foreach ($glob as $path) {
                    if (is_file($path)) {
                        return $path;
                    }
                }
            }
        }

        throw new RuntimeException('PHP CLI negăsit — instalează Laragon sau setează calea php.exe.');
    }

    private static function spawnDetached(string $cmd, string $cwd, string $logFile): bool
    {
        if (!is_dir(dirname($logFile))) {
            mkdir(dirname($logFile), 0775, true);
        }

        if (PHP_OS_FAMILY === 'Windows') {
            $batFile = dirname($logFile) . '/spawn_vehicle_tree_' . bin2hex(random_bytes(4)) . '.bat';
            $batBody = '@echo off' . "\r\n"
                . 'cd /d ' . escapeshellarg($cwd) . "\r\n"
                . $cmd . ' >> ' . escapeshellarg($logFile) . ' 2>&1' . "\r\n"
                . 'del "%~f0"' . "\r\n";
            if (@file_put_contents($batFile, $batBody) === false) {
                return false;
            }
            $bg = 'cmd /C start /B "" ' . escapeshellarg($batFile);
            $handle = @popen($bg, 'r');
            if ($handle === false) {
                return false;
            }
            pclose($handle);

            return true;
        }

        $full = 'cd ' . escapeshellarg($cwd) . ' && ' . $cmd . ' >> ' . escapeshellarg($logFile) . ' 2>&1 &';
        exec($full);

        return true;
    }
}
