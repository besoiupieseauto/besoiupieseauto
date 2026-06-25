<?php

declare(strict_types=1);

/**
 * Simulează GET /admin/cron cu sesiune — verifică că template-ul se încarcă.
 * Usage: php admin/tools/test_cron_page_render.php
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

use Evasystem\Controllers\Admin;
use Evasystem\Core\AdminUrl;

Dotenv\Dotenv::createImmutable(dirname(__DIR__))->safeLoad();
$config = require dirname(__DIR__) . '/config/config.php';

$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 2);
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/admin/cron/index.php';
$_SERVER['HTTP_ACCEPT'] = 'text/html';

session_start();
$_SESSION['user_id'] = 999001;
$_SESSION['role'] = 'super_ambassador';

\Config\Database::getInstance(
    (string) $config['db_host'],
    (string) $config['db_name'],
    (string) $config['db_user'],
    (string) $config['db_pass']
);

$path = AdminUrl::normalizeRequestPath($_SERVER['REQUEST_URI']);
if ($path !== '/admin/cron') {
    fwrite(STDERR, "FAIL normalize: {$path}\n");
    exit(1);
}

$admin = new Admin();
ob_start();
try {
    $admin->index('/admin/Templates/admin/pages/cron/');
} catch (Throwable $e) {
    ob_end_clean();
    fwrite(STDERR, 'FAIL exception: ' . $e->getMessage() . "\n");
    exit(1);
}
$html = (string) ob_get_clean();

if (!str_contains($html, 'id="cron-sync-page"')) {
    fwrite(STDERR, "FAIL lipsește #cron-sync-page în output\n");
    fwrite(STDERR, substr(strip_tags($html), 0, 200) . "\n");
    exit(1);
}

echo "OK  /admin/cron/index.php → panou Cron Sync renderizat\n";
