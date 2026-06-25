<?php



declare(strict_types=1);



require dirname(__DIR__) . '/vendor/autoload.php';



$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));

$dotenv->safeLoad();



use Evasystem\Core\Crud\CrudModuleFactory;



$hubModules = [

    'Alerts',

    'Scan',

    'Cron',

    'Report',

    'Settings',

    'CrossReference',

    'SearchLogsCrud',

];



foreach ($hubModules as $moduleKey) {

    $meta = CrudModuleFactory::modernModuleMeta($moduleKey);

    $controller = CrudModuleFactory::createModernController($moduleKey);

    echo "OK {$moduleKey} => {$meta['label']} (" . get_class($controller) . ")\n";

}



echo "All hub CRUD modules wired successfully.\n";

