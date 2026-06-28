<?php
declare(strict_types=1);

/**
 * Runner migrare 043_marketplace_pieseauto.sql
 */
require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

$config = require dirname(__DIR__) . '/config/config.php';
Config\Database::getInstance(
    (string) $config['db_host'],
    (string) $config['db_name'],
    (string) $config['db_user'],
    (string) $config['db_pass']
);

$pdo = Config\Database::getDB();
$sqlFile = __DIR__ . '/043_marketplace_pieseauto.sql';

if (!is_file($sqlFile)) {
    fwrite(STDERR, "Fisier migrare lipsa: {$sqlFile}\n");
    exit(1);
}

$sql = (string) file_get_contents($sqlFile);
$pdo->exec($sql);

echo "Migrare 043_marketplace_pieseauto aplicata.\n";
