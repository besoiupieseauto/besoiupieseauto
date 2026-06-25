<?php

declare(strict_types=1);

require __DIR__ . '/php_cli.php';

$phpBin = admin_php_cli_binary();
$roots = [
    __DIR__ . '/../src',
    __DIR__ . '/../public',
    __DIR__ . '/../config',
    __DIR__ . '/../cron',
];

$errors = [];

$rootFiles = [
    __DIR__ . '/../index.php',
];
foreach ($rootFiles as $rootFile) {
    if (!is_file($rootFile)) {
        continue;
    }
    exec($phpBin . ' -l ' . escapeshellarg($rootFile) . ' 2>&1', $out, $code);
    if ($code !== 0) {
        $errors[] = $rootFile . ' => ' . end($out);
    }
}

foreach ($roots as $root) {
    if (!is_dir($root)) {
        continue;
    }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
    foreach ($it as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }
        $path = $file->getPathname();
        exec($phpBin . ' -l ' . escapeshellarg($path) . ' 2>&1', $out, $code);
        if ($code !== 0) {
            $errors[] = $path . ' => ' . end($out);
        }
    }
}

echo empty($errors) ? "OK\n" : implode("\n", $errors) . "\n";
echo 'Total errors: ' . count($errors) . "\n";
