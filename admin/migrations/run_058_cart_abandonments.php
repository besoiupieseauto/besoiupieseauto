<?php

declare(strict_types=1);

/**
 * Rulează migrarea 058 — tabel cart_abandonments.
 * Usage: php admin/migrations/run_058_cart_abandonments.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use Config\Database;

$adminRoot = dirname(__DIR__);
Dotenv::createImmutable($adminRoot)->safeLoad();
$config = require $adminRoot . '/config/config.php';

Database::getInstance(
    $config['db_host'],
    $config['db_name'],
    $config['db_user'],
    $config['db_pass'],
    'default'
);

$pdo = Database::getDB();
$sql = file_get_contents(__DIR__ . '/058_cart_abandonments.sql');
if (!is_string($sql) || trim($sql) === '') {
    fwrite(STDERR, "Fișier SQL lipsă.\n");
    exit(1);
}

$pdo->exec($sql);
echo "OK — cart_abandonments creat/verificat.\n";
