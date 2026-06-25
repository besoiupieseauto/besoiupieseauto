<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use Config\Database;

$adminRoot = dirname(__DIR__);
Dotenv::createImmutable($adminRoot)->safeLoad();
$config = require $adminRoot . '/config/config.php';

Database::getInstance($config['db_host'], $config['db_name'], $config['db_user'], $config['db_pass'], 'default');
$pdo = Database::getDB();
$sql = file_get_contents(__DIR__ . '/059_reply_templates.sql');
if (!is_string($sql) || trim($sql) === '') {
    fwrite(STDERR, "SQL lipsă.\n");
    exit(1);
}
$pdo->exec($sql);
echo "OK — reply_templates + 10 template-uri seed.\n";
