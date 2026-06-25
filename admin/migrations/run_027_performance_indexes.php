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

$sql = file_get_contents(__DIR__ . '/027_performance_indexes.sql');
foreach (array_filter(array_map('trim', explode(';', $sql ?: ''))) as $statement) {
    $lines = array_filter(array_map('trim', preg_split('/\R/', $statement) ?: []), static fn ($l) => $l !== '' && !str_starts_with($l, '--'));
    $statement = trim(implode("\n", $lines));
    if ($statement === '') continue;
    try {
        $pdo->exec($statement);
        echo "OK: " . substr(str_replace(["\r", "\n"], ' ', $statement), 0, 70) . "...\n";
    } catch (PDOException $e) {
        if (str_contains($e->getMessage(), 'Duplicate key name')) {
            echo "SKIP (exists): " . substr($statement, 0, 50) . "...\n";
            continue;
        }
        throw $e;
    }
}
echo "Migration 027 completed.\n";
