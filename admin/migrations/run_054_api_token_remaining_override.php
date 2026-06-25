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

if (!columnExists($pdo, 'api_token_budgets', 'remaining_override')) {
    $pdo->exec(
        'ALTER TABLE `api_token_budgets`
         ADD COLUMN `remaining_override` INT UNSIGNED NULL DEFAULT NULL
         COMMENT \'Tokeni rămași setați manual (NULL = calcul automat)\'
         AFTER `tokens_per_request`'
    );
}

echo "OK: migrare 054 remaining_override aplicată.\n";

function columnExists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$table, $column]);

    return (int) $stmt->fetchColumn() > 0;
}
