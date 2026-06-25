<?php



declare(strict_types=1);



require dirname(__DIR__) . '/vendor/autoload.php';



$classes = [

    Evasystem\Controllers\Alerts\Alerts::class,

    Evasystem\Controllers\Alerts\AlertsService::class,

    Evasystem\Controllers\Scan\Scan::class,

    Evasystem\Controllers\Scan\ScanService::class,

    Evasystem\Controllers\Cron\Cron::class,

    Evasystem\Controllers\Cron\CronService::class,

    Evasystem\Controllers\Report\Report::class,

    Evasystem\Controllers\Report\ReportService::class,

    Evasystem\Controllers\Settings\Settings::class,

    Evasystem\Controllers\Settings\SettingsService::class,

    Evasystem\Controllers\CrossReference\CrossReference::class,

    Evasystem\Controllers\SearchLogs\SearchLogsCrud::class,

    Evasystem\Controllers\SearchLogs\SearchLogsService::class,

    Evasystem\Core\Hub\HubScaffoldController::class,

    Evasystem\Core\Hub\HubScaffoldModel::class,

    Evasystem\Core\Hub\HubScaffoldService::class,

];



foreach ($classes as $className) {

    if (!class_exists($className)) {

        fwrite(STDERR, "MISSING: {$className}\n");

        exit(1);

    }

    echo "OK {$className}\n";

}



echo "All module classes load successfully.\n";

