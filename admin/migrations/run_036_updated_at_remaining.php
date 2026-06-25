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

$skipTables = ['schema_legacy_registry', 'migrations'];

$stmt = $pdo->query(
    "SELECT t.TABLE_NAME AS table_name
     FROM information_schema.TABLES t
     INNER JOIN information_schema.COLUMNS c_created
       ON c_created.TABLE_SCHEMA = t.TABLE_SCHEMA
      AND c_created.TABLE_NAME = t.TABLE_NAME
      AND c_created.COLUMN_NAME = 'created_at'
     LEFT JOIN information_schema.COLUMNS c_updated
       ON c_updated.TABLE_SCHEMA = t.TABLE_SCHEMA
      AND c_updated.TABLE_NAME = t.TABLE_NAME
      AND c_updated.COLUMN_NAME = 'updated_at'
     WHERE t.TABLE_SCHEMA = DATABASE()
       AND t.TABLE_TYPE = 'BASE TABLE'
       AND c_updated.COLUMN_NAME IS NULL
     ORDER BY t.TABLE_NAME"
);

$check = $pdo->prepare(
    'SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column'
);

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $table = (string) $row['table_name'];
    if (in_array($table, $skipTables, true)) {
        echo "SKIP {$table}\n";
        continue;
    }

    $check->execute([':table' => $table, ':column' => 'updated_at']);
    if ((int) $check->fetchColumn() > 0) {
        continue;
    }

    $quoted = '`' . str_replace('`', '``', $table) . '`';
    $after = 'created_at';
    $check->execute([':table' => $table, ':column' => 'created_at']);
    if ((int) $check->fetchColumn() === 0) {
        $after = 'id';
    }

    $pdo->exec(
        "ALTER TABLE {$quoted} ADD COLUMN `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER `{$after}`"
    );
    echo "OK {$table}.updated_at added\n";
}

echo "Migration 036 completed.\n";
