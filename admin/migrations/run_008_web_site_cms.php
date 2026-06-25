<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

$host = $_ENV['DB_HOST'] ?? 'localhost';
$name = $_ENV['DB_NAME'] ?? '';
$user = $_ENV['DB_USER'] ?? '';
$pass = $_ENV['DB_PASS'] ?? '';

if ($name === '' || $user === '') {
    fwrite(STDERR, "Missing DB credentials\n");
    exit(1);
}

$pdo = new PDO("mysql:host={$host};dbname={$name};charset=utf8mb4", $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$sql = file_get_contents(__DIR__ . '/008_create_web_site_cms.sql');
if ($sql === false) {
    fwrite(STDERR, "Cannot read migration file\n");
    exit(1);
}

$statements = array_filter(array_map('trim', preg_split('/;\s*\n/', $sql) ?: []));
foreach ($statements as $statement) {
    $statement = preg_replace('/^(\s*--[^\n]*\n)+/', '', $statement) ?? $statement;
    $statement = trim($statement);
    if ($statement === '') {
        continue;
    }
    try {
        $pdo->exec($statement);
        echo "OK: " . substr(str_replace("\n", ' ', $statement), 0, 80) . "...\n";
    } catch (PDOException $e) {
        if (str_contains($e->getMessage(), 'Duplicate entry') || str_contains($e->getMessage(), 'already exists')) {
            echo "SKIP: " . $e->getMessage() . "\n";
            continue;
        }
        throw $e;
    }
}

echo "Migration completed.\n";
