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
    return dirname(__DIR__);
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

function besoiu_asset_href(string $path): string
{
    $sep = str_contains($path, '?') ? '&' : '?';
    $basePath = strtok($path, '?') ?: $path;
    $ver = besoiu_asset_version($basePath);

    return htmlspecialchars($path . $sep . 'v=' . $ver, ENT_QUOTES, 'UTF-8');
}

/** Preconnect + font Inter (4 greutăți, display=swap). */
function besoiu_render_fonts(bool $nonBlocking = false): void
{
    echo '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
    echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
    $fontUrl = 'https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap';
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
        echo '<link rel="preload" href="' . $href . '" as="style" onload="this.onload=null;this.rel=\'stylesheet\'">' . "\n";
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
    echo '<link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>' . "\n";
    $faBase = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/';
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
        besoiu_render_lcp_preloads(['img/logo.png']);
        besoiu_link_stylesheet('assets/css/home-critical.css', false);
        $besoiuHeadCommon = ['layout' => false, 'mobileAsync' => true];
        include __DIR__ . '/head-common.php';
        besoiu_link_stylesheet('assets/css/product-cards.css', false);
        besoiu_link_stylesheet('assets/css/home-scraper-products.css', false);
    } else {
        besoiu_render_fontawesome(true, $fontawesomeRegular);
        besoiu_link_stylesheet('assets/css/site-shell.css', false);
        $besoiuHeadCommon = ['layout' => true, 'mobileAsync' => false];
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
        echo '<script src="' . besoiu_asset_href('assets/js/storefront-notice-gate.js') . '"></script>' . "\n";
        echo '<script src="' . besoiu_asset_href('assets/js/cart-admin.js') . '" defer></script>' . "\n";
    } else {
        echo '<script src="' . besoiu_asset_href('assets/js/cart-lite.js') . '" defer></script>' . "\n";
    }

    foreach ($extraJs as $jsPath) {
        $jsPath = trim($jsPath);
        if ($jsPath === '' || $jsPath === '/robot/widget.js.php') {
            continue;
        }
        echo '<script src="' . besoiu_asset_href($jsPath) . '" defer></script>' . "\n";
    }
}

/** Chat robot — după load + idle, fără a concura cu LCP. */
function besoiu_render_widget_deferred(): void
{
    $src = htmlspecialchars('/robot/widget.js.php?v=' . besoiu_asset_version('robot/widget.js.php'), ENT_QUOTES, 'UTF-8');
    echo "<script>(function(){function w(){var s=document.createElement('script');s.src='{$src}';s.defer=true;document.body.appendChild(s);}";
    echo "'requestIdleCallback'in window?requestIdleCallback(w,{timeout:5000}):window.addEventListener('load',function(){setTimeout(w,2e3)});})();</script>\n";
}
