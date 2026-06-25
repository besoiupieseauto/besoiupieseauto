<?php

declare(strict_types=1);

/**
 * Rezolvă binary PHP CLI Laragon — evită PHP_BINARY = httpd.exe (AutoTester AH02965).
 *
 * @return string Cale absolută către php.exe
 */
function admin_php_cli_binary(): string
{
    static $resolved = null;
    if ($resolved !== null) {
        return $resolved;
    }

    $candidates = [
        'F:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe',
        'F:/laragon/bin/php/php-8.1.31-Win32-vs16-x64/php.exe',
        'E:/laragon/bin/php/php-8.3.30-Win32-vs16-x64/php.exe',
        'E:/laragon/bin/php/php-8.1.31-Win32-vs16-x64/php.exe',
    ];

    $phpBin = PHP_BINARY;
    if (
        !is_string($phpBin)
        || $phpBin === ''
        || !is_file($phpBin)
        || stripos($phpBin, 'httpd') !== false
        || stripos($phpBin, 'apache') !== false
    ) {
        $phpBin = '';
    }

    foreach ($candidates as $path) {
        if ($phpBin === '' && is_file($path)) {
            $phpBin = $path;
            break;
        }
    }

    if ($phpBin === '' || !is_file($phpBin)) {
        throw new RuntimeException('PHP CLI binary not found');
    }

    $resolved = $phpBin;

    return $resolved;
}
