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

$sql = file_get_contents(__DIR__ . '/030_sql_conventions_registry.sql');
foreach (array_filter(array_map('trim', explode(';', $sql ?: ''))) as $statement) {
    $lines = array_filter(array_map('trim', preg_split('/\R/', $statement) ?: []), static function ($line) {
        return $line !== '' && strpos($line, '--') !== 0;
    });
    $statement = trim(implode("\n", $lines));
    if ($statement === '') {
        continue;
    }
    $pdo->exec($statement);
    echo 'OK: ' . substr(str_replace(["\r", "\n"], ' ', $statement), 0, 90) . "...\n";
}

$count = (int) $pdo->query('SELECT COUNT(*) FROM schema_legacy_registry')->fetchColumn();
echo "Migration 030 completed. Legacy registry rows: {$count}\n";
