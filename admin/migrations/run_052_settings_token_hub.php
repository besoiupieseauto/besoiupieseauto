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

$sqlFile = __DIR__ . '/052_settings_token_hub.sql';
$sql = file_get_contents($sqlFile);
if ($sql === false) {
    fwrite(STDERR, "Nu pot citi 052_settings_token_hub.sql\n");
    exit(1);
}

// MySQL < 8 nu are ADD COLUMN IF NOT EXISTS — fallback manual
if (!columnExists($pdo, 'users_connect', 'permissions_json')) {
    $pdo->exec('ALTER TABLE `users_connect` ADD COLUMN `permissions_json` TEXT NULL COMMENT \'Module admin delegabile\' AFTER `role`');
}

foreach (array_filter(array_map('trim', preg_split('/;\s*\n/', $sql))) as $statement) {
    if ($statement === '' || str_starts_with($statement, '--')) {
        continue;
    }
    if (stripos($statement, 'ALTER TABLE `users_connect`') === 0) {
        continue;
    }
    try {
        $pdo->exec($statement);
    } catch (PDOException $e) {
        if (str_contains($e->getMessage(), 'Duplicate')) {
            continue;
        }
        throw $e;
    }
}

echo "OK: migrare 052 settings token hub aplicată.\n";

function columnExists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$table, $column]);

    return (int) $stmt->fetchColumn() > 0;
}
