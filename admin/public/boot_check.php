<?php

declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');

echo 'PHP: ' . PHP_VERSION . ' (' . PHP_SAPI . ")\n";

$adminRoot = dirname(__DIR__);
$projectRoot = dirname($adminRoot);

$paths = [
    'vendor' => $adminRoot . '/vendor/autoload.php',
    'env' => $adminRoot . '/.env',
    'config' => $adminRoot . '/config/config.php',
    'session_system' => $projectRoot . '/system/session-bridge.php',
    'session_legacy' => $projectRoot . '/app/Legacy/session-bridge.php',
];

foreach ($paths as $name => $path) {
    echo $name . ': ' . (is_file($path) ? 'OK' : 'LIPSESTE') . "\n";
}
