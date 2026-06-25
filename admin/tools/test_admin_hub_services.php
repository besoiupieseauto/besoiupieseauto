<?php

declare(strict_types=1);

/**
 * Smoke test servicii hub (simulare admin_hub_endpoint fără HTTP).
 * Rulează: php tools/test_admin_hub_services.php
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();
$config = require dirname(__DIR__) . '/config/config.php';

\Config\Database::getInstance(
    $config['db_host'],
    $config['db_name'],
    $config['db_user'],
    $config['db_pass']
);

use Evasystem\Controllers\Alerts\AlertsService;
use Evasystem\Controllers\Dashboard\DashboardService;
use Evasystem\Controllers\Report\ReportService;
use Evasystem\Controllers\Scan\ScanService;
use Evasystem\Controllers\Settings\SettingsService;
use Evasystem\Controllers\Users\UsersService;

$failures = [];

function jsonPayload(string $action, callable $builder): void
{
    global $failures;
    try {
        $payload = $builder();
        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            throw new RuntimeException('json_encode failed');
        }
        $decoded = json_decode($encoded, true);
        if (!is_array($decoded) || ($decoded['success'] ?? null) !== true) {
            throw new RuntimeException('payload invalid sau success != true');
        }
        echo "OK  action={$action}\n";
    } catch (Throwable $e) {
        $failures[] = "{$action}: " . $e->getMessage();
        echo "FAIL action={$action} — {$e->getMessage()}\n";
    }
}

jsonPayload('overview', function () {
    return ['success' => true, 'data' => (new DashboardService())->overview()];
});

jsonPayload('settings', function () {
    $all = (new SettingsService())->getAllSettingss();
    return ['success' => true, 'data' => is_array($all) ? $all : []];
});

jsonPayload('alerts', function () {
    $all = (new AlertsService())->getAllAlertss();
    $dash = (new DashboardService())->overview();
    return [
        'success' => true,
        'data' => is_array($all) ? $all : [],
        'red_flags' => $dash['red_flags'] ?? [],
    ];
});

jsonPayload('reports', function () {
    $all = (new ReportService())->getAllReports();
    return ['success' => true, 'data' => is_array($all) ? $all : []];
});

jsonPayload('cron', function () {
    $panel = (new ScanService())->loadSavedDashboard();

    return [
        'success' => true,
        'data' => $panel['cron_dashboard'] ?? [],
        'overview' => $panel['overview'] ?? [],
    ];
});

jsonPayload('scan', function () {
    $panel = (new ScanService())->loadSavedDashboard();

    return [
        'success' => true,
        'data' => is_array($panel['suppliers'] ?? null) ? $panel['suppliers'] : [],
    ];
});

jsonPayload('users', function () {
    $all = (new UsersService())->getAllUserss();
    return ['success' => true, 'data' => is_array($all) ? $all : []];
});

if ($failures !== []) {
    echo "\nErori: " . count($failures) . "\n";
    exit(1);
}

echo "\nToate acțiunile hub service au trecut (JSON valid).\n";
