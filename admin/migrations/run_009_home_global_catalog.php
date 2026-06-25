<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

$pdo = new PDO(
    'mysql:host=' . ($_ENV['DB_HOST'] ?? 'localhost') . ';dbname=' . ($_ENV['DB_NAME'] ?? '') . ';charset=utf8mb4',
    $_ENV['DB_USER'] ?? '',
    $_ENV['DB_PASS'] ?? '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$sql = file_get_contents(__DIR__ . '/009_add_home_global_catalog_pages.sql');
$statement = preg_replace('/^(\s*--[^\n]*\n)+/', '', trim($sql ?: '')) ?? '';
$pdo->exec($statement);
echo "Migration 009 completed.\n";
