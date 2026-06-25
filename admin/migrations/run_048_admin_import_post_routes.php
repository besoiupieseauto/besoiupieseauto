<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';
Dotenv\Dotenv::createImmutable(dirname(__DIR__))->safeLoad();
$config = require dirname(__DIR__) . '/config/config.php';
$pdo = new PDO(
    'mysql:host=' . $config['db_host'] . ';dbname=' . $config['db_name'] . ';charset=utf8mb4',
    $config['db_user'],
    $config['db_pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$sql = file_get_contents(__DIR__ . '/048_admin_import_post_routes.sql');
foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
    if ($statement === '' || str_starts_with($statement, '--')) {
        continue;
    }
    $pdo->exec($statement);
    echo "OK: " . substr(str_replace(["\r", "\n"], ' ', $statement), 0, 80) . "...\n";
}

echo "Migrare 048 finalizată.\n";
