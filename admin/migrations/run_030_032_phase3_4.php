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

function tableExists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
    );
    $stmt->execute([$table]);

    return (int) $stmt->fetchColumn() > 0;
}

function columnExists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$table, $column]);

    return (int) $stmt->fetchColumn() > 0;
}

function execSqlFile(PDO $pdo, string $file): void
{
    $path = __DIR__ . '/' . $file;
    $sql = file_get_contents($path);
    if ($sql === false) {
        throw new RuntimeException("Cannot read $file");
    }

    foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
        if ($statement === '') {
            continue;
        }
        $pdo->exec($statement);
    }

    echo "OK: $file\n";
}

$files = [
    '030_create_coupons.sql',
    '031_create_cart_items.sql',
    '032_comenzi_coupon_payment.sql',
];

foreach ($files as $file) {
    execSqlFile($pdo, $file);
}

$comenziColumns = [
    'coupon_code' => 'ALTER TABLE `comenzi` ADD COLUMN `coupon_code` VARCHAR(50) NULL DEFAULT NULL AFTER `total_amount`',
    'discount_amount' => 'ALTER TABLE `comenzi` ADD COLUMN `discount_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `coupon_code`',
    'payment_reference' => 'ALTER TABLE `comenzi` ADD COLUMN `payment_reference` VARCHAR(120) NULL DEFAULT NULL AFTER `payment_status`',
    'payment_status_detail' => 'ALTER TABLE `comenzi` ADD COLUMN `payment_status_detail` VARCHAR(40) NULL DEFAULT NULL AFTER `payment_reference`',
];

foreach ($comenziColumns as $column => $statement) {
    if (columnExists($pdo, 'comenzi', $column)) {
        echo "SKIP: comenzi.$column exists\n";
        continue;
    }

    try {
        $pdo->exec($statement);
        echo "OK: comenzi.$column\n";
    } catch (PDOException $exception) {
        if (str_contains($exception->getMessage(), 'Duplicate column')) {
            echo "SKIP: comenzi.$column duplicate\n";
            continue;
        }
        throw $exception;
    }
}

echo "Migrations 030-032 completed.\n";
