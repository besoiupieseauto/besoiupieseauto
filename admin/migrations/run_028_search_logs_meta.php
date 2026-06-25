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
    'SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
);
$stmt->execute(['search_logs', 'meta_json']);
if ((int) $stmt->fetchColumn() > 0) {
    echo "SKIP: coloana search_logs.meta_json exista deja.\n";
    exit(0);
}

$stmt->execute(['search_logs', 'id']);
if ((int) $stmt->fetchColumn() === 0) {
    fwrite(STDERR, "ERROR: tabela search_logs lipseste. Ruleaza migrarea 016.\n");
    exit(1);
}

$pdo->exec('ALTER TABLE `search_logs` ADD COLUMN `meta_json` JSON NULL DEFAULT NULL AFTER `notice`');
echo "OK: coloana search_logs.meta_json adaugata.\n";
echo "Migration 028 completed.\n";
