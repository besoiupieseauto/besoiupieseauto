<?php

declare(strict_types=1);

use Evasystem\Core\AdminUrl;

/**
 * URI accesibile fără autentificare — folosit de Permision::guard().
 */
return [
    '/',
    AdminUrl::BASE,
    AdminUrl::BASE . '/',
    AdminUrl::path('login'),
    AdminUrl::path('logout'),
    AdminUrl::path('reg'),
    AdminUrl::path('addusersadd'),
    AdminUrl::LEGACY_PREFIX,
    AdminUrl::LEGACY_PREFIX . '/',
    AdminUrl::LEGACY_PREFIX . '/login',
    AdminUrl::LEGACY_PREFIX . '/logout',
    AdminUrl::LEGACY_PREFIX . '/logout.php',
    AdminUrl::LEGACY_PREFIX . '/reg',
    AdminUrl::LEGACY_PREFIX . '/addusersadd',
    AdminUrl::LEGACY_PREFIX . '/pages',
    AdminUrl::LEGACY_PREFIX . '/crududoc.',
    AdminUrl::LEGACY_PREFIX . '/auth/google',
    AdminUrl::LEGACY_PREFIX . '/auth/google/callback',
    AdminUrl::BASE . '/assets/*',
    AdminUrl::BASE . '/public/assets/*',
    AdminUrl::BASE . '/api/*',
    '/assets/*',
    '/public/assets/*',
];
