<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();
$config = require dirname(__DIR__) . '/config/config.php';

if (empty($config['legacy_db_name'])) {
    fwrite(STDERR, "LEGACY_DB_NAME not configured.\n");
    exit(1);
}

$pdo = new PDO(
    'mysql:host=' . $config['legacy_db_host'] . ';dbname=' . $config['legacy_db_name'] . ';charset=utf8mb4',
    $config['legacy_db_user'],
    $config['legacy_db_pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$sql = file_get_contents(__DIR__ . '/022_legacy_orders_core.sql');
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
    echo "OK (legacy): " . substr(str_replace(["\r", "\n"], ' ', $statement), 0, 80) . "...\n";
}

echo "Legacy orders core schema ready.\n";
