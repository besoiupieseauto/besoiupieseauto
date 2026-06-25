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

if (!$pdo->query("SHOW TABLES LIKE 'products_oem'")->fetchColumn()) {
    echo "SKIP: tabela products_oem lipsește.\n";
    exit(0);
}

$sql = file_get_contents(__DIR__ . '/050_widen_products_oem_code.sql');
if (!is_string($sql) || trim($sql) === '') {
    fwrite(STDERR, "FAIL: migrare goală.\n");
    exit(1);
}

$pdo->exec($sql);
echo "OK: products_oem.oem_code / oem_norm -> VARCHAR(120).\n";
