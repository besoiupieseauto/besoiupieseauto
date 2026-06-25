<?php

declare(strict_types=1);

/**
 * Smoke BOON/Zeus — php -l + selectori login (binary Laragon explicit).
 * Usage: php tools/zeus_smoke.php
 */

require __DIR__ . '/php_cli.php';

$phpBin = admin_php_cli_binary();
$admin = dirname(__DIR__);
$lintFiles = [
    $admin . '/index.php',
    $admin . '/config/routes_bootstrap.php',
    $admin . '/config/admin_nav_routes.php',
    $admin . '/config/public_paths.php',
    $admin . '/config/Database.php',
    $admin . '/src/Core/AdminPageResolver.php',
    $admin . '/src/Core/AdminUrl.php',
    $admin . '/src/Controllers/Admin.php',
    $admin . '/src/Controllers/PageLoader.php',
    $admin . '/src/Controllers/Templates.php',
    $admin . '/Templates/admin/pages/login/login.php',
];

$failed = 0;
foreach ($lintFiles as $file) {
    if (!is_file($file)) {
        fwrite(STDERR, "MISSING {$file}\n");
        $failed++;
        continue;
    }
    $out = [];
    $code = 0;
    exec('"' . $phpBin . '" -l ' . escapeshellarg($file) . ' 2>&1', $out, $code);
    $line = trim(implode(' ', $out));
    if ($code !== 0 || !str_contains($line, 'No syntax errors')) {
        fwrite(STDERR, "LINT FAIL {$file}: {$line}\n");
        $failed++;
    }
}

$loginHtmlPath = $admin . '/Templates/admin/pages/login/login.php';
$loginHtml = is_file($loginHtmlPath) ? (string) file_get_contents($loginHtmlPath) : '';
$requiredSelectors = [
    'id="admin-login-form"',
    'id="admin-login-user"',
    'id="admin-login-password"',
    'id="admin-login-toggle-pass"',
    'data-action="toggle-password"',
    'id="admin-login-status"',
    'id="admin-login-submit"',
    'id="admin-page-loader"',
    'data-endpoint="/admin/addusersadd"',
    'admin-login.js',
];
foreach ($requiredSelectors as $needle) {
    if (!str_contains($loginHtml, $needle)) {
        fwrite(STDERR, "SELECTOR MISSING in login.php: {$needle}\n");
        $failed++;
    }
}

$jsPath = $admin . '/public/assets/js/admin-login.js';
$js = is_file($jsPath) ? (string) file_get_contents($jsPath) : '';
$jsSelectors = [
    '#admin-login-form',
    '#admin-login-password',
    '#admin-login-toggle-pass',
    'toggle-password',
    '#admin-login-submit',
    '#admin-page-loader',
];
foreach ($jsSelectors as $sel) {
    if (!str_contains($js, $sel)) {
        fwrite(STDERR, "SELECTOR MISSING in admin-login.js: {$sel}\n");
        $failed++;
    }
}

if ($failed > 0) {
    exit(1);
}

echo 'zeus_smoke OK (' . basename($phpBin) . ")\n";
exit(0);
