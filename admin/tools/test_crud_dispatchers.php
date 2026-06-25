<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();
$config = require dirname(__DIR__) . '/config/config.php';

\Config\Database::getInstance(
    $config['db_host'],
    $config['db_name'],
    $config['db_user'],
    $config['db_pass']
);

use Evasystem\Core\Crud\CrudModuleFactory;
use Evasystem\Core\Crud\ModernCrudDispatcher;

$modernModules = [
    'Comenzi', 'Clienti', 'Bots', 'Livrare', 'Facturi', 'Messages', 'Marketplace',
    'Alerts', 'Scan', 'Cron', 'Report', 'Settings', 'CrossReference', 'SearchLogsCrud',
];

foreach ($modernModules as $module) {
    $controller = CrudModuleFactory::createModernController($module);
    echo 'OK modern ' . $module . ' => ' . get_class($controller) . PHP_EOL;
}

echo 'PHP ' . PHP_VERSION . PHP_EOL;
echo 'Dispatcher: ' . ModernCrudDispatcher::class . PHP_EOL;
