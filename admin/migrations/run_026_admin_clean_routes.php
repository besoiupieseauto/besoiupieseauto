<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();
$config = require dirname(__DIR__) . '/config/config.php';

\Config\Database::getInstance(
    $config['db_host'],
    $config['db_name'],
    $config['db_user'],
    $config['db_pass']
);

$pdo = \Config\Database::getDB();
$sql = file_get_contents(__DIR__ . '/026_admin_clean_routes_and_dirs.sql');
if ($sql === false) {
    fwrite(STDERR, "Cannot read SQL file\n");
    exit(1);
}

foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
    if ($statement === '' || str_starts_with($statement, '--')) {
        continue;
    }
    $pdo->exec($statement);
}

echo "Migration 026 completed.\n";
