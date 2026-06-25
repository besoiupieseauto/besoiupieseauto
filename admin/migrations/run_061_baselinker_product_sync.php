<?php

declare(strict_types=1);

require __DIR__ . '/../tools/php_cli.php';

$admin = dirname(__DIR__);
require_once $admin . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable($admin);
$dotenv->safeLoad();
$config = require $admin . '/config/config.php';

\Config\Database::getInstance(
    $config['db_host'],
    $config['db_name'],
    $config['db_user'],
    $config['db_pass']
);

$pdo = \Config\Database::getDB();
$sql = file_get_contents(__DIR__ . '/061_baselinker_product_sync.sql');
if ($sql === false) {
    fwrite(STDERR, "Nu pot citi 061_baselinker_product_sync.sql\n");
    exit(1);
}

foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
    if ($statement === '') {
        continue;
    }
    try {
        $pdo->exec($statement);
    } catch (PDOException $exception) {
        $msg = $exception->getMessage();
        if (str_contains($msg, 'Duplicate column')
            || str_contains($msg, 'check that column/key exists')
            || str_contains($msg, 'Duplicate entry')) {
            continue;
        }
        throw $exception;
    }
}

echo "OK: migrare 061 BaseLinker product sync\n";
