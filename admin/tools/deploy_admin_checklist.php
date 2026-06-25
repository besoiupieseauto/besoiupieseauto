<?php

declare(strict_types=1);

/**
 * Listă fișiere obligatorii pentru deploy admin funcțional.
 * Usage: php tools/deploy_admin_checklist.php
 */

$admin = dirname(__DIR__);
$root = dirname($admin);

$required = [
    'src/Core/AdminPageResolver.php',
    'src/Core/AdminUrl.php',
    'src/Core/Auth/Permision.php',
    'src/Core/Bootstrap/HttpApplication.php',
    'src/Core/Router.php',
    'src/Controllers/Admin.php',
    'src/Controllers/PageLoader.php',
    'src/Controllers/Templates.php',
    'src/Controllers/Verify.php',
    'config/admin_nav_routes.php',
    'config/routes_bootstrap.php',
    'config/public_paths.php',
    '.htaccess',
    'Templates/admin/pages/login/login.php',
    'Templates/admin/static_elements/admin-topbar.php',
    'public/assets/css/admin-login.css',
    'public/assets/js/admin-login.js',
    'public/assets/css/admin-layout.css',
    'migrations/026_admin_clean_routes_and_dirs.sql',
    'migrations/run_026_admin_clean_routes.php',
    'migrations/047_cron_clean_route.sql',
    'migrations/run_047_cron_clean_route.php',
    'Templates/admin/pages/cron/cron.php',
    'cron_cli/queue_worker.php',
    'config/cron_tasks.php',
];

$missing = [];
foreach ($required as $rel) {
    $path = $admin . '/' . str_replace('/', DIRECTORY_SEPARATOR, $rel);
    if (!is_file($path)) {
        $missing[] = $rel;
    }
}

$rootHtaccess = $root . '/.htaccess';
if (!is_file($rootHtaccess)) {
    $missing[] = '../.htaccess (root site)';
}

echo "Deploy checklist — " . count($required) . " fișiere critice\n";
if ($missing === []) {
    echo "OK — toate fișierele există local.\n";
    echo "\nPași producție:\n";
    echo "  1. Urcă fișierele de mai sus pe server\n";
    echo "  2. ȘTERGE pe server folderul vechi admin/cron/ (conflict cu URL /admin/cron)\n";
    echo "  3. php admin/migrations/run_047_cron_clean_route.php\n";
    echo "  4. șterge admin/storage/cache/*.json (cache rute/roluri)\n";
    echo "  5. php admin/tools/test_admin_pages_audit.php\n";
    echo "  6. php admin/tools/test_cron_route.php\n";
    echo "  7. php admin/tools/test_admin_pages_http.php\n";
    echo "  8. php admin/tools/test_api_http_smoke.php\n";
    exit(0);
}

echo "LIPSĂ " . count($missing) . " fișiere:\n";
foreach ($missing as $m) {
    echo "  - {$m}\n";
}
exit(1);
