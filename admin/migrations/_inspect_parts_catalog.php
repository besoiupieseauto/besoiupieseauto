<?php
require __DIR__ . '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();
$c = require dirname(__DIR__) . '/config/config.php';
$pdo = new PDO(
    'mysql:host=' . $c['legacy_db_host'] . ';dbname=' . $c['legacy_db_name'],
    $c['legacy_db_user'],
    $c['legacy_db_pass']
);
foreach ($pdo->query('SHOW COLUMNS FROM parts_catalog') as $row) {
    echo $row['Field'] . PHP_EOL;
}
