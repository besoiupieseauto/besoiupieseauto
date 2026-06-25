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

$pdo->exec(
    "INSERT INTO `api_token_budgets` (`provider_key`, `label`, `env_key`, `monthly_quota`, `tokens_per_request`, `cost_per_unit`, `warning_pct`, `is_active`, `notes`)
     SELECT 'cursor', 'Cursor AI (Composer)', 'CURSOR_API_KEY', 2000000, 2500, 0.0000, 80, 1, 'Audit imagini + agent scraper Composer 2.5'
     WHERE NOT EXISTS (SELECT 1 FROM `api_token_budgets` WHERE `provider_key` = 'cursor')"
);

echo "OK: migrare 055 cursor API token aplicată.\n";
