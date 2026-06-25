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



$tables = ['alerts', 'scan', 'cron', 'report', 'settings', 'cross_reference', 'search_logs_scaffold'];



$check = $pdo->prepare(

    'SELECT COUNT(*) FROM information_schema.COLUMNS

     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column'

);



foreach ($tables as $table) {

    $check->execute([':table' => $table, ':column' => 'updated_at']);

    if ((int) $check->fetchColumn() > 0) {

        echo "SKIP {$table}.updated_at (exists)\n";

        continue;

    }



    $quoted = '`' . str_replace('`', '``', $table) . '`';

    $pdo->exec(

        "ALTER TABLE {$quoted} ADD COLUMN `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`"

    );

    echo "OK {$table}.updated_at added\n";

}



echo "Migration 035 completed.\n";

