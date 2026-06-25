<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Config\Database;
use Evasystem\Controllers\Dashboard\DashboardService;

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();
$config = require dirname(__DIR__) . '/config/config.php';
Database::getInstance($config['db_host'], $config['db_name'], $config['db_user'], $config['db_pass']);

$data = (new DashboardService())->overview();
echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
