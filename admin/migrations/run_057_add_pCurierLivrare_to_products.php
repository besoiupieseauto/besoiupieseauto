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

$columnExists = static function (PDO $pdo, string $table, string $column): bool {
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$table, $column]);
    return (int) $stmt->fetchColumn() > 0;
};

foreach (['produse', 'import_produse'] as $table) {
    if ($columnExists($pdo, $table, 'pCurierLivrare')) {
        echo "SKIP: {$table}.pCurierLivrare exista deja.\n";
        continue;
    }
    $pdo->exec(
        "ALTER TABLE `{$table}`
         ADD COLUMN `pCurierLivrare` VARCHAR(8) NOT NULL DEFAULT 'Da'
         COMMENT 'Livrare curier: Da sau Nu' AFTER `pShipping`"
    );
    echo "OK: {$table}.pCurierLivrare adaugat.\n";
}

echo "Migration 057 completed.\n";
