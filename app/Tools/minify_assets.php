<?php

declare(strict_types=1);

/**
 * Minifică CSS (agresiv, sigur) și JS (conservator: doar comentarii /* *\/ pe linie proprie + whitespace la margini)
 * Rulare: php app/Tools/minify_assets.php
 * Generează fișiere .min.css / .min.js lângă original; besoiu-assets.php le preferă automat dacă există.
 */
define('BESOIU_ROOT', dirname(__DIR__, 2));
require BESOIU_ROOT . '/app/bootstrap.php';

function besoiu_minify_css(string $css): string
{
    // Scoate comentarii /* ... */ (păstrează /*! ... */ dacă vreodată e nevoie de licențe)
    $css = preg_replace('#/\*(?!!)[\s\S]*?\*/#', '', $css) ?? $css;
    // Colapsează whitespace
    $css = preg_replace('/\s+/', ' ', $css) ?? $css;
    // Elimină spații în jurul simbolurilor structurale
    $css = preg_replace('/\s*([{}:;,>])\s*/', '$1', $css) ?? $css;
    // Elimină ; redundant înainte de }
    $css = preg_replace('/;}/', '}', $css) ?? $css;
    // Repune spațiu necesar pt selectori combinatori descendenți (ex: .a .b) — collapse-ul de mai sus a scos spațiile utile
    return trim($css);
}

/** Minificare JS conservatoare: scoate doar comentarii pe linie proprie + linii goale + trim margini. NU comprimă identificatori (risc ASI/regex). */
function besoiu_minify_js_safe(string $js): string
{
    $lines = preg_split('/\r\n|\r|\n/', $js) ?: [];
    $out = [];
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '') {
            continue;
        }
        // Scoate linii comentariu simplu care nu conțin ://, URL-uri sau string-uri cu //
        if (preg_match('#^//#', $trimmed) && !str_contains($trimmed, 'http')) {
            continue;
        }
        $out[] = $line;
    }

    return implode("\n", $out);
}

$cssTargets = [
    'assets/css/home-critical.css',
    'assets/css/storefront-public.css',
    'assets/css/site-mobile.css',
    'assets/css/product-cards.css',
    'assets/css/home-scraper-products.css',
    'assets/css/site-layout.css',
    'assets/css/site-shell.css',
    'assets/css/about-page.css',
    'assets/css/account-page.css',
    'assets/css/blog-article-page.css',
    'assets/css/cart-page.css',
    'assets/css/catalog-page.css',
    'assets/css/contact-page.css',
    'assets/css/formular-contact.css',
    'assets/css/home-index.css',
    'assets/css/product-page.css',
    'assets/css/besoiu-intel-search.css',
    'assets/css/static-pages.css',
];

$jsTargets = [
    'assets/js/storefront-base.js',
    'assets/js/site-icons.js',
    'assets/js/mobile-nav.js',
    'assets/js/storefront-notice-gate.js',
    'assets/js/cart-admin.js',
    'assets/js/besoiu-client-tracker.js',
    'assets/js/besoiu-intel-search.js',
    'assets/js/home-tecdoc.js',
    'assets/js/preview-gate.js',
    'assets/js/hero-promo-carousel.js',
    'assets/js/cart-lite.js',
];

$root = BESOIU_ROOT;
$saved = 0;

foreach ($cssTargets as $rel) {
    $src = $root . '/' . $rel;
    if (!is_file($src)) {
        echo "SKIP (nu există) {$rel}\n";
        continue;
    }
    $content = (string) file_get_contents($src);
    $min = besoiu_minify_css($content);
    $dest = preg_replace('/\.css$/', '.min.css', $src);
    file_put_contents($dest, $min);
    $before = strlen($content);
    $after = strlen($min);
    $saved += ($before - $after);
    printf("CSS %-45s %6.1f KB -> %6.1f KB (-%d%%)\n", $rel, $before / 1024, $after / 1024, $before > 0 ? (int) round((1 - $after / $before) * 100) : 0);
}

foreach ($jsTargets as $rel) {
    $src = $root . '/' . $rel;
    if (!is_file($src)) {
        echo "SKIP (nu există) {$rel}\n";
        continue;
    }
    $content = (string) file_get_contents($src);
    $min = besoiu_minify_js_safe($content);
    $dest = preg_replace('/\.js$/', '.min.js', $src);
    file_put_contents($dest, $min);
    $before = strlen($content);
    $after = strlen($min);
    $saved += ($before - $after);
    printf("JS  %-45s %6.1f KB -> %6.1f KB (-%d%%)\n", $rel, $before / 1024, $after / 1024, $before > 0 ? (int) round((1 - $after / $before) * 100) : 0);
}

printf("\nTotal economisit: %.1f KB\n", $saved / 1024);
