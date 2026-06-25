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
    ['facturi', 'order_id', 'ALTER TABLE `facturi` ADD COLUMN `order_id` INT NULL DEFAULT NULL AFTER `order_number`'],
    ['livrare', 'order_id', 'ALTER TABLE `livrare` ADD COLUMN `order_id` INT NULL DEFAULT NULL AFTER `order_number`'],
    ['comenzi', 'invoice_randomn_id', 'ALTER TABLE `comenzi` ADD COLUMN `invoice_randomn_id` INT NULL DEFAULT NULL AFTER `payment_status_detail`'],
    ['comenzi', 'livrare_randomn_id', 'ALTER TABLE `comenzi` ADD COLUMN `livrare_randomn_id` INT NULL DEFAULT NULL AFTER `invoice_randomn_id`'],
];

foreach ($changes as [$table, $column, $sql]) {
    if (columnExists($pdo, $table, $column)) {
        echo "SKIP: {$table}.{$column}\n";
        continue;
    }
    $pdo->exec($sql);
    echo "OK: {$table}.{$column}\n";
}

$indexes = [
    ['facturi', 'idx_facturi_order_id', 'CREATE INDEX `idx_facturi_order_id` ON `facturi` (`order_id`)'],
    ['livrare', 'idx_livrare_order_id', 'CREATE INDEX `idx_livrare_order_id` ON `livrare` (`order_id`)'],
    ['comenzi', 'idx_comenzi_invoice_randomn_id', 'CREATE INDEX `idx_comenzi_invoice_randomn_id` ON `comenzi` (`invoice_randomn_id`)'],
    ['comenzi', 'idx_comenzi_livrare_randomn_id', 'CREATE INDEX `idx_comenzi_livrare_randomn_id` ON `comenzi` (`livrare_randomn_id`)'],
];

foreach ($indexes as [$table, $index, $sql]) {
    if (indexExists($pdo, $table, $index)) {
        echo "SKIP index: {$index}\n";
        continue;
    }
    $pdo->exec($sql);
    echo "OK index: {$index}\n";
}

// Backfill order_id din order_number unde e posibil
$pdo->exec(
    'UPDATE facturi f
     INNER JOIN comenzi c ON c.order_number = f.order_number
     SET f.order_id = c.id
     WHERE f.order_id IS NULL AND f.order_number IS NOT NULL AND f.order_number <> \'\''
);
echo 'OK: backfill facturi.order_id' . PHP_EOL;

$pdo->exec(
    'UPDATE livrare l
     INNER JOIN comenzi c ON c.order_number = l.order_number
     SET l.order_id = c.id
     WHERE l.order_id IS NULL AND l.order_number IS NOT NULL AND l.order_number <> \'\''
);
echo 'OK: backfill livrare.order_id' . PHP_EOL;

$pdo->exec(
    'UPDATE comenzi c
     INNER JOIN facturi f ON f.order_id = c.id
     SET c.invoice_randomn_id = f.randomn_id
     WHERE c.invoice_randomn_id IS NULL AND f.randomn_id IS NOT NULL'
);
echo 'OK: backfill comenzi.invoice_randomn_id' . PHP_EOL;

$pdo->exec(
    'UPDATE comenzi c
     INNER JOIN livrare l ON l.order_id = c.id
     SET c.livrare_randomn_id = l.randomn_id
     WHERE c.livrare_randomn_id IS NULL AND l.randomn_id IS NOT NULL'
);
echo 'OK: backfill comenzi.livrare_randomn_id' . PHP_EOL;

echo "Migrare 033 finalizata.\n";
