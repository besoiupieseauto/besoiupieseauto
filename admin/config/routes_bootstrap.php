<?php

declare(strict_types=1);

use Evasystem\Core\AdminPageResolver;
use Evasystem\Core\AdminUrl;

/**
 * Rute bootstrap — curate (fără /public/) + compatibilitate legacy.
 */

/** @var list<string> $navSlugs */
$navSlugs = require __DIR__ . '/admin_nav_routes.php';

$routes = [
    ['GET', AdminUrl::BASE, 'Admin', 'redirectToLogin', ''],
    ['GET', AdminUrl::BASE . '/', 'Admin', 'redirectToLogin', ''],
    ['GET', AdminUrl::path('login'), 'Admin', 'index', '/admin/Templates/admin/pages/login/'],
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

$legacyOnly = [
    ['GET', AdminUrl::LEGACY_PREFIX . '/caietcomenzi', '/admin/Templates/admin/pages/caietcomenzi/'],
    ['GET', AdminUrl::LEGACY_PREFIX . '/abandoned-carts', '/admin/Templates/admin/pages/comenzi/'],
    ['GET', AdminUrl::LEGACY_PREFIX . '/caiet-de-comenzi', '/admin/Templates/admin/pages/caietcomenzi/'],
    ['GET', AdminUrl::LEGACY_PREFIX . '/comenzi-tm', '/admin/Templates/admin/pages/caietcomenzi/'],
    ['GET', AdminUrl::LEGACY_PREFIX . '/comenzi-utvin', '/admin/Templates/admin/pages/caietcomenzi/'],
    ['GET', AdminUrl::LEGACY_PREFIX . '/comenzi-externe', '/admin/Templates/admin/pages/caietcomenzi/'],
    ['GET', AdminUrl::LEGACY_PREFIX . '/searching', '/admin/Templates/admin/pages/supplier-search/'],
    ['GET', AdminUrl::LEGACY_PREFIX . '/produse-vitrina', '/admin/Templates/admin/pages/produse/'],
    ['GET', AdminUrl::LEGACY_PREFIX . '/produse-scanate', '/admin/Templates/admin/pages/produse/'],
    ['GET', AdminUrl::LEGACY_PREFIX . '/caiet-produse', '/admin/Templates/admin/pages/caietcomenzi/'],
    ['GET', AdminUrl::LEGACY_PREFIX . '/caiet-clienti', '/admin/Templates/admin/pages/caietcomenzi/'],
    ['GET', AdminUrl::LEGACY_PREFIX . '/caiet-facturi', '/admin/Templates/admin/pages/caietcomenzi/'],
    ['GET', AdminUrl::LEGACY_PREFIX . '/caiet-incasari', '/admin/Templates/admin/pages/caietcomenzi/'],
    ['GET', AdminUrl::LEGACY_PREFIX . '/furnizori', '/admin/Templates/admin/pages/furnizori/'],
    ['GET', AdminUrl::LEGACY_PREFIX . '/profilefurnizori', '/admin/Templates/admin/pages/furnizori/'],
    ['GET', AdminUrl::LEGACY_PREFIX . '/addfurnizori', '/admin/Templates/admin/pages/furnizori/'],
    ['GET', AdminUrl::LEGACY_PREFIX . '/comenzi', '/admin/Templates/admin/pages/comenzi/'],
    ['GET', AdminUrl::LEGACY_PREFIX . '/produse', '/admin/Templates/admin/pages/produse/'],
    ['GET', AdminUrl::LEGACY_PREFIX . '/import', '/admin/Templates/admin/pages/import/'],
    ['GET', AdminUrl::LEGACY_PREFIX . '/clienti', '/admin/Templates/admin/pages/clienti/'],
    ['GET', AdminUrl::LEGACY_PREFIX . '/facturi', '/admin/Templates/admin/pages/facturi/'],
    ['GET', AdminUrl::LEGACY_PREFIX . '/livrare', '/admin/Templates/admin/pages/livrare/'],
    ['GET', AdminUrl::LEGACY_PREFIX . '/messages', '/admin/Templates/admin/pages/messages/'],
    ['GET', AdminUrl::LEGACY_PREFIX . '/bots', '/admin/Templates/admin/pages/bots/'],
    ['GET', AdminUrl::LEGACY_PREFIX . '/website', '/admin/Templates/admin/pages/website/'],
    ['GET', AdminUrl::LEGACY_PREFIX . '/blog', '/admin/Templates/admin/pages/blog/'],
    ['GET', AdminUrl::LEGACY_PREFIX . '/marketplace', '/admin/Templates/admin/pages/marketplace/'],
    ['GET', AdminUrl::path('marketplace-pieseauto'), '/admin/Templates/admin/pages/marketplace/'],
    ['GET', AdminUrl::LEGACY_PREFIX . '/marketplace-pieseauto', '/admin/Templates/admin/pages/marketplace/'],
    ['GET', AdminUrl::path('marketplace-baselinker'), '/admin/Templates/admin/pages/marketplace/'],
    ['GET', AdminUrl::LEGACY_PREFIX . '/marketplace-baselinker', '/admin/Templates/admin/pages/marketplace/'],
    ['GET', AdminUrl::LEGACY_PREFIX . '/cron', '/admin/Templates/admin/pages/cron/'],
    ['GET', AdminUrl::LEGACY_PREFIX . '/alerts', '/admin/Templates/admin/pages/alerts/'],
    ['GET', AdminUrl::LEGACY_PREFIX . '/reports', '/admin/Templates/admin/pages/report/'],
    ['GET', AdminUrl::LEGACY_PREFIX . '/report', '/admin/Templates/admin/pages/report/'],
    ['GET', AdminUrl::LEGACY_PREFIX . '/cross-reference', '/admin/Templates/admin/pages/cross-reference/'],
    ['GET', AdminUrl::LEGACY_PREFIX . '/crossreference', '/admin/Templates/admin/pages/cross-reference/'],
    ['GET', AdminUrl::LEGACY_PREFIX . '/settings', '/admin/Templates/admin/pages/settings/'],
    ['GET', AdminUrl::LEGACY_PREFIX . '/users', '/admin/Templates/admin/pages/users/'],
];

foreach ($legacyOnly as [$method, $path, $dir]) {
    $routes[] = [$method, $path, 'Admin', 'index', $dir];
}

return $routes;
