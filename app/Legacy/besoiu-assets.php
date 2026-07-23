<?php
/**
 * Încărcare optimizată CSS/JS — profil per pagină (țintă ~80% performanță)
 */
declare(strict_types=1);

/** Fallback dacă fișierul lipsește (nu ar trebui folosit pentru CSS/JS existente). */
const BESOIU_ASSET_VER = '1';

/** @return list<string> */
function besoiu_asset_profiles(): array
{
    return ['minimal', 'shop', 'home', 'cart', 'product', 'account'];
}

function besoiu_project_root(): string
{
    return defined('BESOIU_ROOT') ? BESOIU_ROOT : dirname(__DIR__, 2);
}

/** Versiune cache = mtime fișier — URL nou automat la fiecare salvare (fără bump manual). */
function besoiu_asset_version(string $path): string
{
    $path = ltrim(str_replace('\\', '/', $path), '/');
    if ($path === '' || str_contains($path, '..')) {
        return BESOIU_ASSET_VER;
    }

    $query = '';
    if (str_contains($path, '?')) {
        [$path, $query] = explode('?', $path, 2);
    }

    $full = besoiu_project_root() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
    if (is_file($full)) {
        return (string) filemtime($full) . ($query !== '' ? '-' . substr(md5($query), 0, 6) : '');
    }

    return BESOIU_ASSET_VER;
}

/** Preferă .min.css/.min.js dacă există lângă fișierul sursă (generat de app/Tools/minify_assets.php). */
function besoiu_asset_minified_path(string $path): string
{
    if (!preg_match('/\.(css|js)$/', $path, $m) || str_ends_with($path, '.min.' . $m[1])) {
        return $path;
    }
    $full = besoiu_project_root() . '/' . preg_replace('/\.(css|js)$/', '.min.$1', $path);
    if (is_file($full)) {
        return preg_replace('/\.(css|js)$/', '.min.$1', $path);
    }

    return $path;
}

function besoiu_asset_href(string $path): string
{
    $sep = str_contains($path, '?') ? '&' : '?';
    $basePath = strtok($path, '?') ?: $path;
    $query = strtok('?') ?: '';
    $minPath = besoiu_asset_minified_path($basePath);
    $ver = besoiu_asset_version($minPath);
    $out = $minPath . ($query !== '' ? '?' . $query : '') . $sep . 'v=' . $ver;

    return htmlspecialchars($out, ENT_QUOTES, 'UTF-8');
}

/** Preconnect + font Inter (4 greutăți, display=swap). */
function besoiu_render_fonts(bool $nonBlocking = false): void
{
    echo '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
    echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
    $fontUrl = 'https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=optional';
    $fontEsc = htmlspecialchars($fontUrl, ENT_QUOTES, 'UTF-8');
    if ($nonBlocking) {
        echo '<link rel="preload" as="style" href="' . $fontEsc . '">' . "\n";
        echo '<link rel="stylesheet" href="' . $fontEsc . '" media="print" onload="this.media=\'all\'">' . "\n";
        echo '<noscript><link rel="stylesheet" href="' . $fontEsc . '"></noscript>' . "\n";
    } else {
        echo '<link rel="stylesheet" href="' . $fontEsc . '">' . "\n";
    }
}

/** @param list<string> $paths căi relative (ex. img/hero-car.png) */
function besoiu_render_lcp_preloads(array $paths): void
{
    foreach ($paths as $i => $path) {
        $path = trim($path);
        if ($path === '') {
            continue;
        }
        $href = htmlspecialchars($path, ENT_QUOTES, 'UTF-8');
        $prio = $i === 0 ? ' fetchpriority="high"' : '';
        echo '<link rel="preload" as="image" href="' . $href . '"' . $prio . '>' . "\n";
    }
}

function besoiu_link_stylesheet(string $path, bool $async = false): void
{
    $href = besoiu_asset_href($path);
    if ($async) {
        echo '<link rel="stylesheet" href="' . $href . '" media="print" onload="this.media=\'all\'">' . "\n";
        echo '<noscript><link rel="stylesheet" href="' . $href . '"></noscript>' . "\n";
    } else {
        echo '<link rel="stylesheet" href="' . $href . '">' . "\n";
    }
}

function besoiu_link_external_stylesheet(string $url, bool $async = false): void
{
    $href = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
    if ($async) {
        echo '<link rel="preload" href="' . $href . '" as="style" onload="this.onload=null;this.rel=\'stylesheet\'">' . "\n";
        echo '<noscript><link rel="stylesheet" href="' . $href . '"></noscript>' . "\n";
    } else {
        echo '<link rel="stylesheet" href="' . $href . '" crossorigin="anonymous" referrerpolicy="no-referrer">' . "\n";
    }
}

/**
 * @param string $profile minimal|shop|home|cart|product|account
 * @param list<string> $extraCss căi relative, ex. assets/css/contact-page.css
 */
function besoiu_render_fontawesome(bool $async = true, bool $includeRegular = false): void
{
    echo '<link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>' . "\n";
    $faBase = 'https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.2/css/';
    besoiu_link_external_stylesheet($faBase . 'fontawesome.min.css', $async);
    besoiu_link_external_stylesheet($faBase . 'solid.min.css', $async);
    if ($includeRegular) {
        besoiu_link_external_stylesheet($faBase . 'regular.min.css', $async);
    }
}

function besoiu_render_styles(string $profile, array $extraCss = [], bool $fontawesomeRegular = false): void
{
    $profile = in_array($profile, besoiu_asset_profiles(), true) ? $profile : 'minimal';
    $perfHome = $profile === 'home';

    if ($perfHome) {
        require_once __DIR__ . '/home-inline-critical.php';
        besoiu_render_home_inline_critical();
        // Blocking: 41KB — elimină CLS-ul major din flip-ul layout hero/filterbar (async media=print).
        besoiu_link_stylesheet('assets/css/home-critical.css', false);
        besoiu_link_stylesheet('assets/css/besoiu-intel-search.css', true);
        $besoiuHeadCommon = ['layout' => false, 'mobileAsync' => true, 'asyncPublicCss' => true, 'deferBaseJs' => true];
        include __DIR__ . '/head-common.php';
        besoiu_link_stylesheet('assets/css/product-cards.css', true);
        besoiu_link_stylesheet('assets/css/home-scraper-products.css', true);
    } else {
        require_once __DIR__ . '/home-inline-critical.php';
        besoiu_render_shell_inline_critical();
        besoiu_render_fontawesome(true, $fontawesomeRegular);
        besoiu_link_stylesheet('assets/css/site-shell.css', false);
        besoiu_link_stylesheet('assets/css/besoiu-intel-search.css', true);
        $besoiuHeadCommon = ['layout' => true, 'mobileAsync' => true, 'asyncPublicCss' => true, 'layoutAsync' => false];
        include __DIR__ . '/head-common.php';

        if ($profile === 'shop') {
            besoiu_link_stylesheet('assets/css/product-cards.css', true);
        }
    }

    foreach ($extraCss as $cssPath) {
        $cssPath = trim($cssPath);
        if ($cssPath === '') {
            continue;
        }
        $asyncExtra = $perfHome && str_contains($cssPath, 'product-cards');
        besoiu_link_stylesheet($cssPath, $asyncExtra);
    }
}

/**
 * @param string $profile
 * @param list<string> $extraJs
 */
function besoiu_render_scripts(string $profile, array $extraJs = []): void
{
    $profile = in_array($profile, besoiu_asset_profiles(), true) ? $profile : 'minimal';
    $needsFullCart = in_array($profile, ['home', 'shop', 'cart', 'product', 'account'], true);

    if ($needsFullCart) {
        echo '<script src="' . besoiu_asset_href('assets/js/storefront-notice-gate.js') . '" defer></script>' . "\n";
        echo '<script src="' . besoiu_asset_href('assets/js/cart-admin.js') . '" defer></script>' . "\n";
    } else {
        echo '<script src="' . besoiu_asset_href('assets/js/cart-lite.js') . '" defer></script>' . "\n";
    }

    echo '<script src="' . besoiu_asset_href('assets/js/besoiu-client-tracker.js') . '" defer></script>' . "\n";
    echo '<script src="' . besoiu_asset_href('assets/js/besoiu-intel-search.js') . '" defer></script>' . "\n";

    foreach ($extraJs as $jsPath) {
        $jsPath = trim($jsPath);
        if ($jsPath === '' || $jsPath === '/robot/widget.js.php') {
            continue;
        }
        echo '<script src="' . besoiu_asset_href($jsPath) . '" defer></script>' . "\n";
    }
}

/**
 * Chat robot — după load + idle, fără a concura cu LCP.
 * Rezervă poziția fixă + dimensiuni ÎNAINTE de injectare (evită CLS la apariția widget-ului).
 * Injectează scriptul doar dacă endpoint-ul chiar există (evită request 404 inutil / erori consolă).
 */
function besoiu_render_widget_deferred(): void
{
    $widgetFile = besoiu_project_root() . '/robot/widget.js.php';
    if (!is_file($widgetFile)) {
        return;
    }

    $src = htmlspecialchars('/robot/widget.js.php?v=' . besoiu_asset_version('robot/widget.js.php'), ENT_QUOTES, 'UTF-8');
    echo '<style>#besoiu-chat-widget-slot,.besoiu-chat-widget,.robot-widget-launcher{position:fixed!important;right:20px;bottom:20px;width:64px;height:64px;z-index:9990}</style>' . "\n";
    echo '<div id="besoiu-chat-widget-slot" aria-hidden="true"></div>' . "\n";
    echo "<script>(function(){function w(){var s=document.createElement('script');s.src='{$src}';s.defer=true;document.body.appendChild(s);}";
    echo "'requestIdleCallback'in window?requestIdleCallback(w,{timeout:5000}):window.addEventListener('load',function(){setTimeout(w,2e3)});})();</script>\n";
}
