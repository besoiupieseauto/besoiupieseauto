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

function columnExists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$table, $column]);

    return (int) $stmt->fetchColumn() > 0;
}

function indexExists(PDO $pdo, string $table, string $index): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?'
    );
    $stmt->execute([$table, $index]);

    return (int) $stmt->fetchColumn() > 0;
}

$changes = [
    ['facturi', 'smartbill_series', 'ALTER TABLE `facturi` ADD COLUMN `smartbill_series` VARCHAR(32) NULL DEFAULT NULL AFTER `invoice_number`'],
    ['facturi', 'smartbill_number', 'ALTER TABLE `facturi` ADD COLUMN `smartbill_number` VARCHAR(32) NULL DEFAULT NULL AFTER `smartbill_series`'],
    ['facturi', 'smartbill_invoice_id', 'ALTER TABLE `facturi` ADD COLUMN `smartbill_invoice_id` VARCHAR(64) NULL DEFAULT NULL AFTER `smartbill_number`'],
    ['livrare', 'courier_provider', 'ALTER TABLE `livrare` ADD COLUMN `courier_provider` VARCHAR(32) NULL DEFAULT NULL AFTER `courier`'],
    ['livrare', 'courier_response', 'ALTER TABLE `livrare` ADD COLUMN `courier_response` TEXT NULL DEFAULT NULL AFTER `notes`'],
];

foreach ($changes as [$table, $column, $sql]) {
    if (columnExists($pdo, $table, $column)) {
        echo "SKIP: {$table}.{$column}\n";
        continue;
    }
    $pdo->exec($sql);
    echo "OK: {$table}.{$column}\n";
}

if (!indexExists($pdo, 'facturi', 'idx_facturi_smartbill_number')) {
    $pdo->exec('CREATE INDEX `idx_facturi_smartbill_number` ON `facturi` (`smartbill_series`, `smartbill_number`)');
    echo "OK index: idx_facturi_smartbill_number\n";
} else {
    echo "SKIP index: idx_facturi_smartbill_number\n";
}

echo "Migrare 034 finalizata.\n";
