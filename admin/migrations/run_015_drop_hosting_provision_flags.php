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

$sql = file_get_contents(__DIR__ . '/015_drop_hosting_provision_flags.sql');
if ($sql === false) {
    fwrite(STDERR, "Cannot read migration file\n");
    exit(1);
}

foreach (['conn_create_ftp', 'conn_create_inbox'] as $column) {
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute(['furnizori', $column]);
    if ((int) $stmt->fetchColumn() === 0) {
        echo "SKIP: coloana {$column} nu exista.\n";
        continue;
    }

    $pdo->exec('ALTER TABLE `furnizori` DROP COLUMN `' . $column . '`');
    echo "OK: DROP COLUMN {$column}\n";
}

echo "Migration 015 completed.\n";
