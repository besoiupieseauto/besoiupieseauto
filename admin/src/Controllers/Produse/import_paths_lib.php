<?php
declare(strict_types=1);

/**
 * Rezolvă fișiere vechi din app/system/ către app/Legacy/ (sau alte locații existente).
 */
function import_system_file_path(string $filename): ?string
{
    $filename = ltrim(str_replace('\\', '/', $filename), '/');
    if ($filename === '' || str_contains($filename, '..')) {
        return null;
    }

    $appRoot = dirname(__DIR__, 3);
    $projectRoot = defined('BESOIU_ROOT')
        ? rtrim(str_replace('\\', '/', (string) BESOIU_ROOT), '/')
        : dirname($appRoot);

    $candidates = [
        $appRoot . '/system/' . $filename,
        $appRoot . '/Legacy/' . $filename,
        $projectRoot . '/system/' . $filename,
        $projectRoot . '/app/Legacy/' . $filename,
    ];

    $importScraperSystem = $projectRoot . '/app/Import/Scraper/system/' . $filename;
    if (is_file($importScraperSystem)) {
        array_unshift($candidates, $importScraperSystem);
    }

    foreach ($candidates as $path) {
        if (is_file($path)) {
            return $path;
        }
    }

    return null;
}

function import_require_system_file(string $filename): void
{
    static $loaded = [];

    if (isset($loaded[$filename])) {
        return;
    }

    $path = import_system_file_path($filename);
    if ($path === null) {
        throw new RuntimeException('System/Legacy file not found: ' . $filename);
    }

    require_once $path;
    $loaded[$filename] = true;
}
