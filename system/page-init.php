<?php

declare(strict_types=1);

if (defined('BPA_PAGE_INIT')) {
    return;
}

define('BPA_PAGE_INIT', true);

if (PHP_SAPI !== 'cli') {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
}

require_once __DIR__ . '/shop-auth.php';
require_once __DIR__ . '/url.php';
require_once __DIR__ . '/storefront-context.php';

if (
    PHP_SAPI !== 'cli'
    && !empty($_GET['site_cms_edit'])
    && (string) $_GET['site_cms_edit'] === '1'
) {
    require_once __DIR__ . '/site-live-cms.php';
    if (!headers_sent()) {
        header_remove('X-Frame-Options');
        header('X-Frame-Options: SAMEORIGIN');
        header("Content-Security-Policy: frame-ancestors 'self'", true);
    }
    if (site_live_admin_authenticated()) {
        if (!defined('BPA_CMS_EDIT_ACTIVE')) {
            define('BPA_CMS_EDIT_ACTIVE', true);
        }
    }
}

shop_auth_session_start();
require_once __DIR__ . '/preview-gate.php';
besoiu_preview_gate_enforce();

/** Panou debug / markup Zeus — doar operator admin autentificat (sau CLI cu BESOIU_DEV_TOOLS). */
function besoiu_dev_tools_enabled(): bool
{
    if (PHP_SAPI === 'cli') {
        return filter_var($_ENV['BESOIU_DEV_TOOLS'] ?? getenv('BESOIU_DEV_TOOLS'), FILTER_VALIDATE_BOOL);
    }

    if (besoiu_admin_storefront_context()) {
        return true;
    }

    if (filter_var($_ENV['BESOIU_DEV_TOOLS'] ?? getenv('BESOIU_DEV_TOOLS'), FILTER_VALIDATE_BOOL)) {
        return true;
    }

    return isset($_GET['besoiu_debug'])
        && (string) $_GET['besoiu_debug'] === '1'
        && besoiu_admin_storefront_context();
}
