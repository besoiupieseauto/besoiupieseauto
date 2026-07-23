<?php

declare(strict_types=1);

/**
 * Mod marker DOM — antrenare RAG chat prin click pe elemente pagină.
 */

if (!function_exists('dom_marker_mode_active')) {
    function dom_marker_mode_active(): bool
    {
        if (PHP_SAPI === 'cli') {
            return false;
        }

        if (!function_exists('site_live_admin_authenticated')) {
            require_once __DIR__ . '/site-live-cms.php';
        }

        if (!site_live_admin_authenticated()) {
            return false;
        }

        if (!empty($_GET['bpa_dom_marker']) && (string) $_GET['bpa_dom_marker'] === '1') {
            return true;
        }

        return defined('BPA_DOM_MARKER_ACTIVE') && BPA_DOM_MARKER_ACTIVE;
    }
}

if (!function_exists('dom_marker_user_config')) {
    /** @return array{can_document: bool, mode: string} */
    function dom_marker_user_config(): array
    {
        $adminRoot = dirname(__DIR__) . '/admin';

        if (session_status() === PHP_SESSION_ACTIVE && session_name() === 'PHPSESSID') {
            if (empty($_SESSION['user_id'])) {
                return ['can_document' => false, 'mode' => 'inform'];
            }
        } else {
            if (!function_exists('besoiu_admin_session_authenticated')) {
                require_once __DIR__ . '/session-bridge.php';
            }
            if (!besoiu_admin_session_authenticated()) {
                return ['can_document' => false, 'mode' => 'inform'];
            }
        }

        if (!is_file($adminRoot . '/vendor/autoload.php')) {
            return ['can_document' => false, 'mode' => 'inform'];
        }

        require_once $adminRoot . '/vendor/autoload.php';

        if (!function_exists('besoiu_session_peek')) {
            require_once __DIR__ . '/session-bridge.php';
        }

        $resolveCanDoc = static function (): bool {
            if (!class_exists(\Besoiu\Core\Auth\AdminChatPermissionGuard::class)) {
                return false;
            }

            return \Besoiu\Core\Auth\AdminChatPermissionGuard::fromSession()->canDomMarkerDocument();
        };

        // Admin deja autentificat în PHPSESSID — evită session_peek după output HTML (warnings headers).
        if (session_status() === PHP_SESSION_ACTIVE && session_name() === 'PHPSESSID' && !empty($_SESSION['user_id'])) {
            $canDoc = $resolveCanDoc();
        } else {
            $canDoc = (bool) besoiu_session_peek('PHPSESSID', static function () use ($resolveCanDoc): bool {
                return $resolveCanDoc();
            });
        }

        return [
            'can_document' => $canDoc,
            'mode' => $canDoc ? 'document' : 'inform',
        ];
    }
}

if (!function_exists('dom_marker_boot_config')) {
    /** @return array<string, mixed> */
    function dom_marker_boot_config(): array
    {
        $adminRoot = dirname(__DIR__) . '/admin';
        $csrf = '';

        if (is_file($adminRoot . '/vendor/autoload.php')) {
            require_once $adminRoot . '/vendor/autoload.php';
            require_once __DIR__ . '/session-bridge.php';

            if (besoiu_admin_session_authenticated() && class_exists(\Besoiu\Core\Auth\AdminCsrf::class)) {
                $csrf = (string) besoiu_session_peek('PHPSESSID', static function (): string {
                    return \Besoiu\Core\Auth\AdminCsrf::token();
                });
            }
        }

        return array_merge(dom_marker_user_config(), [
            'api' => '/admin/api/comunicare_endpoint.php',
            'csrf' => $csrf,
            'scope' => str_starts_with((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH), '/admin') ? 'admin' : 'storefront',
            'page_url' => (string) ($_SERVER['REQUEST_URI'] ?? '/'),
            'page_title' => '',
            'show_fab' => !dom_marker_mode_active(),
        ]);
    }
}

if (!function_exists('dom_marker_render_fab')) {
    function dom_marker_render_fab(): void
    {
        if (!function_exists('besoiu_admin_storefront_context')) {
            return;
        }
        if (!besoiu_admin_storefront_context() || dom_marker_mode_active()) {
            return;
        }
        echo '<button type="button" id="bpaDomMarkerFab" class="bpa-dm-fab" title="Pornește Marker DOM">⛶ Marker DOM</button>' . "\n";
    }
}

if (!function_exists('dom_marker_render_assets')) {
    function dom_marker_render_assets(): void
    {
        if (!dom_marker_mode_active()) {
            return;
        }

        $cfg = dom_marker_boot_config();
        $css = '/admin/public/assets/css/admin-dom-marker.css?v=20260628-marker7';
        $js = '/admin/public/assets/js/admin-dom-marker.js?v=20260628-marker7';

        echo '<link rel="stylesheet" href="' . htmlspecialchars($css, ENT_QUOTES) . '">' . "\n";
        echo '<script type="application/json" id="bpa-dom-marker-cfg">' . json_encode($cfg, JSON_UNESCAPED_UNICODE) . '</script>' . "\n";
        echo '<script src="' . htmlspecialchars($js, ENT_QUOTES) . '" defer></script>' . "\n";
    }
}
