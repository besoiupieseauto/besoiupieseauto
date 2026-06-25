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

if (!columnExists($pdo, 'api_token_budgets', 'tokens_per_request')) {
    $pdo->exec(
        'ALTER TABLE `api_token_budgets`
         ADD COLUMN `tokens_per_request` INT UNSIGNED NOT NULL DEFAULT 1
         COMMENT \'Tokeni consumați per query/request\'
         AFTER `monthly_quota`'
    );
}

$updates = [
    ['rapidapi_tecdoc', 5000, 10],
    ['scrape_do', 1000, 1],
    ['openai', 500000, 1500],
    ['groq', 1000000, 800],
    ['gemini', 500000, 1200],
    ['grok', 300000, 1200],
];
$stmt = $pdo->prepare(
    'UPDATE api_token_budgets SET monthly_quota = ?, tokens_per_request = ? WHERE provider_key = ?'
);
foreach ($updates as [$key, $quota, $tpr]) {
    $stmt->execute([$quota, $tpr, $key]);
}

echo "OK: migrare 053 tokens per request aplicată.\n";

function columnExists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$table, $column]);

    return (int) $stmt->fetchColumn() > 0;
}
