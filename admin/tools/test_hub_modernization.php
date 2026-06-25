<?php

declare(strict_types=1);

/**
 * Smoke test complet — modernizare module hub + CRUD factory.
 * Rulează: php tools/test_hub_modernization.php
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
use Evasystem\Controllers\Cron\CronService;
use Evasystem\Controllers\Report\ReportService;
use Evasystem\Controllers\Scan\ScanService;
use Evasystem\Controllers\Settings\SettingsService;
use Evasystem\Core\Crud\CrudModuleFactory;

$failures = [];
$passed = 0;

function pass(string $label): void
{
    global $passed;
    $passed++;
    echo "OK  {$label}\n";
}

function fail(string $label, string $reason): void
{
    global $failures;
    $failures[] = "{$label}: {$reason}";
    echo "FAIL {$label} — {$reason}\n";
}

function assertTrue(bool $cond, string $label, string $reason = 'condiție falsă'): void
{
    $cond ? pass($label) : fail($label, $reason);
}

$pdo = \Config\Database::getDB();

// 1. Migrare 035 — coloane updated_at
$hubTables = ['alerts', 'scan', 'cron', 'report', 'settings', 'cross_reference', 'search_logs_scaffold'];
$checkCol = $pdo->prepare(
    'SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c'
);
foreach ($hubTables as $table) {
    $checkCol->execute([':t' => $table, ':c' => 'updated_at']);
    assertTrue(
        (int) $checkCol->fetchColumn() > 0,
        "schema {$table}.updated_at",
        'coloana updated_at lipsește — rulează migrations/run_035_hub_scaffold_updated_at.php'
    );
}

// 2. Servicii listare hub (admin_hub_endpoint)
$services = [
    'AlertsService' => fn () => (new AlertsService())->getAllAlertss(),
    'ScanService' => fn () => (new ScanService())->getAllScans(),
    'CronService' => fn () => (new CronService())->getAllCrons(),
    'ReportService' => fn () => (new ReportService())->getAllReports(),
    'SettingsService' => fn () => (new SettingsService())->getAllSettingss(),
];
foreach ($services as $name => $callable) {
    try {
        $rows = $callable();
        assertTrue(is_array($rows), "{$name}::list", 'returnează non-array');
    } catch (Throwable $e) {
        fail("{$name}::list", $e->getMessage());
    }
}

// 3. CRUD cycle pe fiecare modul hub (add → update → status → delete)
$hubModules = [
    'Alerts' => 'alerts',
    'Scan' => 'scan',
    'Cron' => 'cron',
    'Report' => 'report',
    'Settings' => 'settings',
    'CrossReference' => 'cross_reference',
    'SearchLogsCrud' => 'search_logs_scaffold',
];

$testName = 'Test_' . date('YmdHis') . '_' . bin2hex(random_bytes(2));

foreach ($hubModules as $moduleKey => $table) {
    try {
        $controller = CrudModuleFactory::createModernController($moduleKey);
        $created = $controller->add([
            'name' => $testName . '_' . $moduleKey,
            'email' => 'test@evasystem.local',
            'phone' => '0700000000',
            'status' => 1,
        ]);
        $id = (int) ($created['id'] ?? 0);
        assertTrue($id > 0, "{$moduleKey} add", 'id invalid');

        $updated = $controller->update([
            'id' => $id,
            'name' => $testName . '_edit_' . $moduleKey,
        ]);
        assertTrue((int) ($updated['id'] ?? 0) === $id, "{$moduleKey} update", 'update id mismatch');

        $controller->changeStatus(['id' => $id, 'status' => 0]);
        $row = $pdo->query("SELECT status FROM `" . str_replace('`', '``', $table) . "` WHERE id = {$id}")->fetch(PDO::FETCH_ASSOC);
        assertTrue((int) ($row['status'] ?? -1) === 0, "{$moduleKey} changeStatus", 'status nu s-a schimbat');

        $controller->delete(['id' => $id]);
        $exists = $pdo->query("SELECT COUNT(*) FROM `" . str_replace('`', '``', $table) . "` WHERE id = {$id}")->fetchColumn();
        assertTrue((int) $exists === 0, "{$moduleKey} delete", 'înregistrarea încă există');
    } catch (Throwable $e) {
        fail("{$moduleKey} CRUD cycle", $e->getMessage());
        // cleanup dacă add a reușit parțial
        if (isset($id) && $id > 0) {
            try {
                $pdo->exec("DELETE FROM `" . str_replace('`', '``', $table) . "` WHERE id = {$id}");
            } catch (Throwable) {
            }
        }
    }
}

// 4. Factory meta pentru toate modulele moderne
$allModern = [
    'Comenzi', 'Clienti', 'Bots', 'Livrare', 'Facturi', 'Messages', 'Marketplace',
    'Alerts', 'Scan', 'Cron', 'Report', 'Settings', 'CrossReference', 'SearchLogsCrud',
];
foreach ($allModern as $moduleKey) {
    try {
        $meta = CrudModuleFactory::modernModuleMeta($moduleKey);
        $ctrl = CrudModuleFactory::createModernController($moduleKey);
        assertTrue(
            isset($meta['label'], $meta['session_key']) && is_object($ctrl),
            "factory {$moduleKey}",
            'meta sau controller invalid'
        );
    } catch (Throwable $e) {
        fail("factory {$moduleKey}", $e->getMessage());
    }
}

echo "\n--- Rezumat ---\n";
echo "Passed: {$passed}\n";
echo 'Failed: ' . count($failures) . "\n";

if ($failures !== []) {
    echo "\nErori:\n";
    foreach ($failures as $f) {
        echo "  - {$f}\n";
    }
    exit(1);
}

echo "Toate testele hub modernizare au trecut.\n";
