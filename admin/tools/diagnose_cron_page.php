<?php

declare(strict_types=1);

/**
 * Diagnostic complet /admin/cron — rulare: php admin/tools/diagnose_cron_page.php
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

use Evasystem\Core\AdminPageResolver;
use Evasystem\Core\AdminUrl;
use Evasystem\Core\Auth\Permision;

Dotenv\Dotenv::createImmutable(dirname(__DIR__))->safeLoad();
$config = require dirname(__DIR__) . '/config/config.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

echo "=== diagnose_cron_page ===\n\n";

$adminRoot = dirname(__DIR__);
$cronDir = $adminRoot . '/cron';
if (is_dir($cronDir)) {
    echo "FAIL  Folder fizic admin/cron/ EXISTĂ — ștergeți cu: php admin/tools/fix_cron_folder_conflict.php\n";
    foreach (glob($cronDir . '/*') ?: [] as $f) {
        echo "      - " . basename((string) $f) . "\n";
    }
} else {
    echo "OK    Fără folder fizic admin/cron/\n";
}

\Config\Database::getInstance(
    (string) $config['db_host'],
    (string) $config['db_name'],
    (string) $config['db_user'],
    (string) $config['db_pass']
);
$pdo = \Config\Database::getDB();

$stmt = $pdo->query("SELECT method, path, controller, action, load_type, dir, is_active FROM routes WHERE path LIKE '%cron%' ORDER BY path");
$routes = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "\nRute DB (cron):\n";
foreach ($routes as $row) {
    echo '  ' . json_encode($row, JSON_UNESCAPED_UNICODE) . "\n";
}
$hasClean = false;
foreach ($routes as $row) {
    if (($row['path'] ?? '') === '/admin/cron' && (int) ($row['is_active'] ?? 0) === 1) {
        $hasClean = true;
    }
}
echo $hasClean ? "OK    Rută GET /admin/cron activă în DB\n" : "FAIL  Lipsește rută GET /admin/cron — rulați php admin/migrations/run_047_cron_clean_route.php\n";

$docRoot = dirname(__DIR__, 2);
$_SERVER['DOCUMENT_ROOT'] = $docRoot;
$tpl = AdminPageResolver::resolveTemplate('cron', '/admin/Templates/admin/pages/cron/');
$tplFile = $tpl !== null ? $docRoot . '/' . ltrim(str_replace('\\', '/', $tpl), '/') : '';
echo $tpl !== null && $tplFile !== '' && is_file($tplFile)
    ? "OK    Template: {$tpl}\n"
    : "FAIL  Template cron/cron.php nu se rezolvă\n";

$_SESSION['user_id'] = 999001;
$_SESSION['role'] = 'super_ambassador';
$perm = new Permision(['guest' => ['label' => 'Guest', 'scopes' => [], 'nav' => [], 'widgets' => []]]);
$publicPaths = require dirname(__DIR__) . '/config/public_paths.php';
$perm->setPublicPaths($publicPaths);
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/admin/cron';
try {
    $perm->guard('GET', AdminUrl::path('cron'));
    echo "OK    Permision guard permite /admin/cron (user autentificat)\n";
} catch (Throwable $e) {
    echo "FAIL  Permision: " . $e->getMessage() . "\n";
}

$navFile = dirname(__DIR__) . '/Templates/admin/static_elements/nav.php';
$navHtml = file_get_contents($navFile) ?: '';
if (str_contains($navHtml, "adminHref('cron')")) {
    echo "FAIL  nav.php încă folosește adminHref('cron') — trebuie navPath (relativ)\n";
} elseif (str_contains($navHtml, "navPath('cron')") || str_contains($navHtml, "path('cron')")) {
    echo "OK    nav.php — link relativ /admin/cron\n";
} else {
    echo "??    nav.php — verificați manual linkul Cron Sync\n";
}

$siteBase = rtrim(getenv('EVASYSTEM_WEB_BASE') ?: 'http://besoiupieseauto.ro.test', '/');
$ctx = stream_context_create(['http' => ['method' => 'GET', 'header' => "Accept: text/html\r\n", 'timeout' => 12, 'ignore_errors' => true]]);
$raw = @file_get_contents($siteBase . '/admin/cron', false, $ctx);
$status = 0;
if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
    $status = (int) $m[1];
}
if ($status === 301) {
    echo "FAIL  HTTP 301 pe {$siteBase}/admin/cron — conflict folder admin/cron/\n";
} elseif ($raw !== false && str_contains($raw, 'id="cron-sync-page"')) {
    echo "OK    HTTP {$status} — panou cron-sync-page în răspuns\n";
} elseif ($raw !== false && str_contains($raw, 'admin-login-form')) {
    echo "OK    HTTP {$status} — redirect login (fără cookie sesiune) — normal\n";
} else {
    echo "??    HTTP {$status} body: " . substr(strip_tags((string) $raw), 0, 120) . "\n";
}

echo "\nGata. Dacă FAIL: php admin/tools/fix_cron_folder_conflict.php\n";
