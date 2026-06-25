<?php

declare(strict_types=1);

/**
 * CLI — regenerează snapshot dashboard (Task Scheduler / cron la 2–5 min).
 *
 * php admin/scripts/refresh_dashboard_snapshot.php
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

use Config\Database;
use Evasystem\Controllers\Dashboard\DashboardSnapshotService;

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();
$config = require dirname(__DIR__) . '/config/config.php';

Database::getInstance(
    $config['db_host'],
    $config['db_name'],
    $config['db_user'],
    $config['db_pass']
);

$started = microtime(true);
$data = DashboardSnapshotService::refresh(300, false);
$elapsed = round((microtime(true) - $started) * 1000);

echo 'Dashboard snapshot OK — generated_at=' . ($data['generated_at'] ?? '?')
    . ' — ' . $elapsed . "ms\n";
