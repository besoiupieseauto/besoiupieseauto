<?php

declare(strict_types=1);

/**
 * Verifică existența scripturilor cron + sintaxă PHP CLI.
 * Usage: php admin/tools/verify_cron_setup.php
 */

require __DIR__ . '/php_cli.php';

$admin = dirname(__DIR__);
$php = admin_php_cli_binary();
$failures = 0;

require_once $admin . '/config/cron_tasks.php';

$batRequired = [
    'scripts/run_refresh_dashboard_snapshot.bat',
    'scripts/run_queue_worker.bat',
    'scripts/run_daily_backup.bat',
    'scripts/run_supplier_rclone_sync.bat',
    'scripts/_cron_env.bat',
];

$phpRequired = [
    'cron_cli/queue_worker.php',
    'cron_cli/baselinker_sync.php',
    'cron_cli/daily_backup.php',
    'scripts/refresh_dashboard_snapshot.php',
    'scripts/supplier_sync_agent.php',
];

echo "=== verify_cron_setup ===\n\n";

$cronDir = $admin . DIRECTORY_SEPARATOR . 'cron';
if (is_dir($cronDir)) {
    echo "FAIL folder fizic admin/cron/ există — rulează: php admin/tools/fix_cron_folder_conflict.php\n";
    $failures++;
} else {
    echo "OK  fără folder fizic admin/cron/\n";
}

foreach ($batRequired as $rel) {
    $path = $admin . '/' . str_replace('/', DIRECTORY_SEPARATOR, $rel);
    if (!is_file($path)) {
        echo "FAIL missing bat: {$rel}\n";
        $failures++;
        continue;
    }
    echo "OK  bat {$rel}\n";
}

foreach ($phpRequired as $rel) {
    $path = $admin . '/' . str_replace('/', DIRECTORY_SEPARATOR, $rel);
    if (!is_file($path)) {
        echo "FAIL missing php: {$rel}\n";
        $failures++;
        continue;
    }
    passthru('"' . $php . '" -l ' . escapeshellarg($path), $code);
    if ($code !== 0) {
        $failures++;
        echo "FAIL syntax {$rel}\n";
    } else {
        echo "OK  php -l {$rel}\n";
    }
}

$tasks = admin_cron_tasks_registry();
if (count($tasks) < 6) {
    echo "FAIL cron_tasks registry prea mic\n";
    $failures++;
} else {
    echo 'OK  cron_tasks registry: ' . count($tasks) . " joburi\n";
}

$modules = admin_cron_modules_registry();
if (count($modules) < 4) {
    echo "FAIL cron_modules registry prea mic\n";
    $failures++;
} else {
    echo 'OK  cron_modules registry: ' . count($modules) . " module\n";
}

$removedScanTemplate = $admin . '/Templates/admin/pages/scan/scan.php';
if (is_file($removedScanTemplate)) {
    echo "FAIL pagina /admin/scan inca exista: Templates/admin/pages/scan/scan.php\n";
    $failures++;
} else {
    echo "OK  pagina /admin/scan stearsa din template-uri\n";
}

$selectorChecks = [
    'Templates/admin/pages/cron/cron.php' => ['id="cron-sync-page"', 'cron-sync-dashboard.php'],
    'Templates/admin/pages/_partials/cron-sync-dashboard.php' => ['scan-cron-jobs-table', 'id="cron-cron-jobs"'],
    'Templates/admin/pages/backup/backup.php' => ['id="backup-run"'],
];

foreach ($selectorChecks as $rel => $needles) {
    $path = $admin . '/' . str_replace('/', DIRECTORY_SEPARATOR, $rel);
    if (!is_file($path)) {
        echo "FAIL missing template: {$rel}\n";
        $failures++;
        continue;
    }
    $html = (string) file_get_contents($path);
    foreach ($needles as $needle) {
        if (!str_contains($html, $needle)) {
            echo "FAIL selector {$needle} lipsește din {$rel}\n";
            $failures++;
        } else {
            echo "OK  selector {$needle} in {$rel}\n";
        }
    }
}

if ($failures > 0) {
    echo "\nFAILED: {$failures}\n";
    exit(1);
}

echo "\nCRON SETUP OK\n";
exit(0);
