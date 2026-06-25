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

$stmt = $pdo->prepare(
    'SELECT COUNT(*) FROM information_schema.TABLES
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
);
$stmt->execute(['system_errors']);
if ((int) $stmt->fetchColumn() > 0) {
    echo "SKIP: tabela system_errors exista deja.\n";
    exit(0);
}

$sql = file_get_contents(__DIR__ . '/043_create_system_errors.sql');
if ($sql === false) {
    fwrite(STDERR, "Cannot read migration file\n");
    exit(1);
}

$pdo->exec($sql);

echo "OK: tabela system_errors + rute admin create.\n";
echo "Migration 043 completed.\n";
