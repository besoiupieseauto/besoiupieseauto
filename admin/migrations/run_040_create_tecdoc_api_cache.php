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
$stmt->execute(['tecdoc_api_cache']);
if ((int) $stmt->fetchColumn() > 0) {
    echo "SKIP: tabela tecdoc_api_cache exista deja.\n";
    exit(0);
}

$sql = file_get_contents(__DIR__ . '/040_create_tecdoc_api_cache.sql');
if ($sql === false) {
    fwrite(STDERR, "Cannot read migration file\n");
    exit(1);
}

$pdo->exec($sql);
echo "OK: tabela tecdoc_api_cache creata.\n";
echo "Migration 040 completed.\n";
