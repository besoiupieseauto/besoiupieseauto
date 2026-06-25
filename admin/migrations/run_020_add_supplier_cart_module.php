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

$sql = file_get_contents(__DIR__ . '/020_add_supplier_cart_module.sql');
if ($sql === false) {
    fwrite(STDERR, "Cannot read migration file\n");
    exit(1);
}

foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
    $lines = array_filter(array_map('trim', preg_split('/\R/', $statement) ?: []), static function (string $line): bool {
        return $line !== '' && !str_starts_with($line, '--');
    });
    $statement = trim(implode("\n", $lines));
    if ($statement === '') {
        continue;
    }
    $pdo->exec($statement);
    echo "OK: " . substr(str_replace(["\r", "\n"], ' ', $statement), 0, 80) . "...\n";
}

echo "Migration 020 completed.\n";
