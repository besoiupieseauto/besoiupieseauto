<?php

declare(strict_types=1);

use Besoiu\Core\AdminPageResolver;
use Besoiu\Core\AdminUrl;
use Besoiu\Core\Module\ModuleGate;

/**
 * Rute bootstrap — doar infrastructură CORE + module active (fără legacy).
 */

/** @var list<string> $navSlugs */
$navSlugs = require __DIR__ . '/admin_nav_routes.php';

// Slug-uri din module externe instalate (installed_map)
foreach (\Besoiu\Core\Module\ModuleGate::optionalMap() as $moduleId => $meta) {
    if (!\Besoiu\Core\Module\ModuleGate::enabled((string) $moduleId)) {
        continue;
    }
    foreach ($meta['slugs'] ?? [] as $slug) {
        $slug = strtolower(trim((string) $slug));
        if ($slug !== '' && !in_array($slug, $navSlugs, true)) {
            $navSlugs[] = $slug;
        }
    }
}

$navSlugs = array_values(array_filter(
    $navSlugs,
    static fn (string $slug): bool => ModuleGate::slugAllowed($slug)
));

$routes = [
    ['GET', AdminUrl::BASE, 'Admin', 'redirectToLogin', ''],
    ['GET', AdminUrl::BASE . '/', 'Admin', 'redirectToLogin', ''],
    ['GET', AdminUrl::path('login'), 'Admin', 'index', AdminPageResolver::routeDirectory('login')],
    ['GET', AdminUrl::path('workspace'), 'Admin', 'workspace', '/admin/Templates/admin/pages/workspace/'],
    ['POST', AdminUrl::path('workspace'), 'Admin', 'workspaceSet', ''],
    ['GET', AdminUrl::path('workspace-switch'), 'Admin', 'workspaceSwitch', ''],
    ['GET', AdminUrl::path('logout'), 'Admin', 'logout', ''],
    ['GET', AdminUrl::LEGACY_PREFIX, 'Admin', 'redirectToLogin', ''],
    ['GET', AdminUrl::LEGACY_PREFIX . '/', 'Admin', 'redirectToLogin', ''],
    ['GET', AdminUrl::LEGACY_PREFIX . '/logout', 'Admin', 'logout', ''],
];

foreach ($navSlugs as $slug) {
    $routes[] = [
        'GET',
        AdminUrl::path($slug),
        'Admin',
        'index',
        AdminPageResolver::routeDirectory($slug),
    ];
}

return $routes;
