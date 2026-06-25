<?php

declare(strict_types=1);

/**
 * Helper rclone pentru supplier sync agent.
 */

/** @return string */
function supplier_rclone_binary(): string
{
    $candidates = [
        getenv('RCLONE_BIN') ?: '',
        dirname(__DIR__) . '/tools/rclone/rclone.exe',
        'C:/Program Files/rclone/rclone.exe',
        'rclone',
    ];

    foreach ($candidates as $candidate) {
        $candidate = trim((string) $candidate);
        if ($candidate === '') {
            continue;
        }
        if ($candidate === 'rclone') {
            $output = [];
            $code = 1;
            @exec('where rclone 2>nul', $output, $code);
            if ($code === 0 && isset($output[0]) && is_file($output[0])) {
                return $output[0];
            }
            continue;
        }
        if (is_file($candidate)) {
            return $candidate;
        }
    }

    throw new RuntimeException(
        'rclone nu este instalat. Ruleaza admin/tools/install_rclone.ps1 sau seteaza RCLONE_BIN.'
    );
}

function supplier_rclone_config_path(): string
{
    return dirname(__DIR__) . '/storage/rclone/rclone.conf';
}

/** @return array{ok:bool,code:int,output:string} */
function supplier_rclone_run(array $args, ?string $configPath = null): array
{
    $binary = supplier_rclone_binary();
    $config = $configPath ?? supplier_rclone_config_path();

    $command = escapeshellarg($binary)
        . ' --config ' . escapeshellarg($config)
        . ' ' . implode(' ', array_map(static fn ($arg) => escapeshellarg((string) $arg), $args));

    $output = [];
    $code = 1;
    exec($command . ' 2>&1', $output, $code);

    return [
        'ok' => $code === 0,
        'code' => $code,
        'output' => implode("\n", $output),
    ];
}

function supplier_rclone_obscure_password(string $password): string
{
    $result = supplier_rclone_run(['obscure', $password], supplier_rclone_config_path());
    if (!$result['ok']) {
        throw new RuntimeException('rclone obscure esuat: ' . $result['output']);
    }

    $line = trim($result['output']);

    return $line !== '' ? $line : $password;
}

/** @param array<string, mixed> $supplier */
function supplier_rclone_sync_supplier(array $supplier, string $targetDir): void
{
    $remote = trim((string) ($supplier['rclone_remote'] ?? strtolower((string) ($supplier['code'] ?? ''))));
    if ($remote === '') {
        throw new RuntimeException('Lipseste rclone_remote sau code furnizor.');
    }

    $remoteDir = trim((string) ($supplier['remote_dir'] ?? '/'));
    if ($remoteDir === '' || $remoteDir === '/') {
        $remotePath = $remote . ':';
    } else {
        $remotePath = $remote . ':' . ltrim(str_replace('\\', '/', $remoteDir), '/');
    }

    $pattern = trim((string) ($supplier['file_pattern'] ?? '*.csv'));
    if ($pattern === '') {
        $pattern = '*.csv';
    }

    if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
        throw new RuntimeException('Nu pot crea folder cache: ' . $targetDir);
    }

    $args = [
        'copy',
        $remotePath,
        rtrim(str_replace('\\', '/', $targetDir), '/') . '/',
        '--include',
        $pattern,
        '--no-traverse',
        '-v',
    ];

    $transfers = (int) ($supplier['rclone_transfers'] ?? 4);
    if ($transfers > 0) {
        $args[] = '--transfers';
        $args[] = (string) $transfers;
    }

    $result = supplier_rclone_run($args);
    if (!$result['ok']) {
        throw new RuntimeException('rclone copy esuat: ' . $result['output']);
    }
}

function supplier_rclone_ping_remote(string $remote): void
{
    $result = supplier_rclone_run(['lsd', $remote . ':', '--max-depth', '1']);
    if (!$result['ok']) {
        throw new RuntimeException('rclone lsd esuat pentru ' . $remote . ': ' . $result['output']);
    }
}
