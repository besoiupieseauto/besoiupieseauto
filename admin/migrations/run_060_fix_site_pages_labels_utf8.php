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
$pdo->exec('SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci');

$sql = file_get_contents(__DIR__ . '/060_fix_site_pages_labels_utf8.sql');
if ($sql === false || trim($sql) === '') {
    fwrite(STDERR, "Migration file missing.\n");
    exit(1);
}

foreach (preg_split('/;\s*\n/', trim($sql)) as $statement) {
    $statement = trim($statement);
    if ($statement === '' || str_starts_with($statement, '--')) {
        continue;
    }
    $pdo->exec($statement);
}

echo "Migration 060 completed — site_pages labels UTF-8 fixed.\n";
