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

$sqlFile = __DIR__ . '/007_add_pBadge_to_products.sql';
if (!is_file($sqlFile)) {
    fwrite(STDERR, "Missing migration SQL: {$sqlFile}\n");
    exit(1);
}

$pdo->exec((string) file_get_contents($sqlFile));
echo "Migration 007 completed.\n";
