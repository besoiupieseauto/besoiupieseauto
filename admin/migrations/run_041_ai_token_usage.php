<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();
$config = require dirname(__DIR__) . '/config/config.php';

$pdo = new PDO(
    'mysql:host=' . $config['db_host'] . ';dbname=' . $config['db_name'] . ';charset=utf8mb4',
    $config['db_user'],
    $config['db_pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$sql = file_get_contents(__DIR__ . '/041_create_ai_token_usage.sql');
if ($sql === false) {
    fwrite(STDERR, "Nu pot citi 041_create_ai_token_usage.sql\n");
    exit(1);
}

foreach (array_filter(array_map('trim', preg_split('/;\s*\n/', $sql))) as $statement) {
    if ($statement === '' || str_starts_with($statement, '--')) {
        continue;
    }
    $pdo->exec($statement);
}

echo "OK: migrare 041 ai_token_usage aplicată.\n";
