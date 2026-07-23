<?php

declare(strict_types=1);

/**
 * Smoke test admin Laragon compact — rulează:
 *   php admin/tools/smoke_admin.php
 */
$adminRoot = dirname(__DIR__);
$projectRoot = dirname($adminRoot);

require_once $adminRoot . '/bootstrap.php';

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/admin/login';
$_SERVER['HTTPS'] = 'on';
$_SERVER['HTTP_HOST'] = 'besoiupieseauto.test';

$checks = [
    'vendor' => is_file(BESOIU_ADMIN . '/vendor/autoload.php'),
    'env' => is_file(BESOIU_APP . '/Config/.env') || is_file(BESOIU_ADMIN . '/.env'),
    'session_shim' => is_file($projectRoot . '/system/session-bridge.php'),
    'session_legacy' => is_file(BESOIU_LEGACY . '/session-bridge.php'),
];

echo "=== Laragon admin pre-flight ===\n";
foreach ($checks as $k => $v) {
    echo sprintf("%-18s %s\n", $k, $v ? 'OK' : 'LIPSESTE');
}

require_once BESOIU_ADMIN . '/vendor/autoload.php';

$envFile = is_file(BESOIU_APP . '/Config/.env') ? BESOIU_APP . '/Config' : BESOIU_ADMIN;
Dotenv\Dotenv::createImmutable($envFile)->safeLoad();

ob_start();
try {
    $applicationConfig = require BESOIU_ADMIN . '/config/config.php';
    (new Besoiu\Core\Bootstrap\HttpApplication($applicationConfig))->run();
    $out = ob_get_clean();
    echo 'Output: ' . strlen($out) . " bytes\n";
    echo str_contains($out, 'login') || str_contains($out, 'Login') ? "PASS\n" : "WARN\n";
} catch (Throwable $e) {
    ob_end_clean();
    echo 'FAIL: ' . $e->getMessage() . "\n";
    exit(1);
}
