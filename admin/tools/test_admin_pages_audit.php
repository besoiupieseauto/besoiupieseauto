<?php

declare(strict_types=1);

/**
 * Audit pagini admin — verifică că fiecare slug din meniu are template rezolvabil.
 * Usage: php tools/test_admin_pages_audit.php
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

use Evasystem\Core\AdminPageResolver;

$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__, 2);

/** @var list<string> $slugs */
$slugs = require dirname(__DIR__) . '/config/admin_nav_routes.php';

$failures = [];
$ok = 0;

foreach ($slugs as $slug) {
    $path = AdminPageResolver::resolveTemplate($slug, AdminPageResolver::routeDirectory($slug));
    if ($path === null || !is_file($_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($path, '/'))) {
        $failures[] = $slug . ' → MISSING';
        continue;
    }
    $ok++;
}

echo "Admin pages audit: {$ok} OK, " . count($failures) . " FAIL\n";
foreach ($failures as $line) {
    echo "  FAIL {$line}\n";
}

exit($failures === [] ? 0 : 1);
