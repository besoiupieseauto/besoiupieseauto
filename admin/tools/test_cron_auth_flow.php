<?php

declare(strict_types=1);

/**
 * Verifică că /admin/cron nu blochează utilizatorul autentificat.
 * Usage: php admin/tools/test_cron_auth_flow.php
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

use Evasystem\Core\AdminUrl;
use Evasystem\Core\Auth\Permision;
use Evasystem\Core\Auth\RolesRepository;

session_start();
$_SESSION['user_id'] = 999001;
$_SESSION['role'] = 'super_ambassador';

$perm = new Permision(['guest' => ['label' => 'Guest', 'scopes' => [], 'nav' => [], 'widgets' => []]]);
/** @var list<string> $publicPaths */
$publicPaths = require dirname(__DIR__) . '/config/public_paths.php';
$perm->setPublicPaths($publicPaths);

$cronPath = AdminUrl::path('cron');
$allowed = false;
try {
    $perm->guard('GET', $cronPath);
    $allowed = true;
} catch (Throwable $e) {
    echo 'FAIL guard threw: ' . $e->getMessage() . PHP_EOL;
    exit(1);
}

if (!$allowed) {
    echo "FAIL guard did not allow {$cronPath} for logged-in user\n";
    exit(1);
}

echo "OK  guard permite {$cronPath} pentru user autentificat\n";

// login.php nu trebuie să conțină logout la încărcare
$loginPhp = file_get_contents(dirname(__DIR__) . '/Templates/admin/pages/login/login.php') ?: '';
if (str_contains($loginPhp, 'SessionAuth::logout()')) {
    echo "FAIL login.php încă apelează SessionAuth::logout()\n";
    exit(1);
}
echo "OK  login.php nu mai distruge sesiunea la fiecare vizită\n";

echo "test_cron_auth_flow: PASS\n";
