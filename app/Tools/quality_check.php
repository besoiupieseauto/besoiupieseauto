<?php

declare(strict_types=1);

/**
 * Verificare calitate storefront — rulează local sau pe server.
 * Usage: php app/Tools/quality_check.php [base_url]
 */
define('BESOIU_ROOT', dirname(__DIR__, 2));
require BESOIU_ROOT . '/app/bootstrap.php';

$base = $argv[1] ?? 'http://besoiupieseauto.ro.test';
$base = rtrim($base, '/');

/** @var list<array{label:string,ok:bool,detail:string}> */
$checks = [];

function qc_fetch(string $url): array
{
    $ctx = stream_context_create(['http' => ['timeout' => 20, 'ignore_errors' => true]]);
    $body = @file_get_contents($url, false, $ctx);
    $headers = $http_response_header ?? [];
    $status = 0;
    if ($headers !== [] && preg_match('#HTTP/\S+\s+(\d+)#', (string) $headers[0], $m)) {
        $status = (int) $m[1];
    }

    return ['status' => $status, 'body' => is_string($body) ? $body : '', 'headers' => $headers];
}

function qc_header(array $headers, string $name): string
{
    foreach ($headers as $line) {
        if (stripos($line, $name . ':') === 0) {
            return trim(substr($line, strlen($name) + 1));
        }
    }

    return '';
}

function qc_add(array &$checks, string $label, bool $ok, string $detail): void
{
    $checks[] = ['label' => $label, 'ok' => $ok, 'detail' => $detail];
}

// --- Fișiere locale ---
$localFiles = [
    'Logo WebP' => BESOIU_ROOT . '/assets/img/logo.webp',
    'Produs 200w' => BESOIU_ROOT . '/assets/images/products/1-200.webp',
    'Produs 400w' => BESOIU_ROOT . '/assets/images/products/1-400.webp',
    'CSS inline critical' => BESOIU_LEGACY . '/home-inline-critical.php',
    'Helper imagini' => BESOIU_LEGACY . '/besoiu-image.php',
    'Sitemap root' => BESOIU_ROOT . '/sitemap.xml',
];
foreach ($localFiles as $label => $path) {
    qc_add($checks, "Local: {$label}", is_file($path), $path);
}

// --- HTTP ---
$home = qc_fetch($base . '/');
$homeOk = $home['status'] === 200 && $home['body'] !== '';
qc_add($checks, 'HTTP homepage 200', $homeOk, 'status=' . $home['status']);

if ($homeOk) {
    $h = $home['body'];
    qc_add($checks, 'SEO canonical', str_contains($h, 'rel="canonical"'), 'canonical tag');
    qc_add($checks, 'SEO og:title', str_contains($h, 'og:title'), 'Open Graph');
    qc_add($checks, 'Perf inline CSS', str_contains($h, 'besoiu-home-inline-critical'), 'inline critical');
    $hPerf2 = preg_replace('#<noscript[\s\S]*?</noscript>#i', '', $h) ?? $h;
    $blockingHomeCss = false;
    if (preg_match_all('#<link[^>]+home-critical(?:\.min)?\.css[^>]*>#i', $hPerf2, $homeCssLinks)) {
        foreach ($homeCssLinks[0] as $linkTag) {
            if (stripos($linkTag, 'rel="stylesheet"') !== false && stripos($linkTag, 'media="print"') === false) {
                $blockingHomeCss = true;
                break;
            }
        }
    }
    qc_add($checks, 'Perf home-critical blocking (anti-CLS)', $blockingHomeCss, 'home-critical render-blocking');
    qc_add($checks, 'Perf font optional', str_contains($h, 'display=optional'), 'font-display optional');
    qc_add($checks, 'Perf logo webp', str_contains($h, 'logo.webp'), 'logo.webp in HTML');
    qc_add($checks, 'Perf logo fetchpriority', str_contains($h, 'logo.webp') && str_contains($h, 'fetchpriority="high"'), 'LCP logo priority');
    qc_add($checks, 'Perf logo preload', str_contains($h, 'preload') && str_contains($h, 'logo.webp'), 'preload logo.webp');
    qc_add($checks, 'Perf logo dimensions', str_contains($h, 'width="125" height="42"'), 'logo w/h');
    qc_add($checks, 'Perf srcset produs', str_contains($h, 'srcset=') && str_contains($h, '-200.webp'), 'responsive images');
    qc_add($checks, 'Perf fără 1.jpg uriaș', !str_contains($h, 'images/products/1.jpg'), 'no huge 1.jpg');
    qc_add($checks, 'Perf fără car1.png uriaș', !str_contains($h, 'car1.png'), 'car1 WebP only');
    qc_add($checks, 'Perf defer notice-gate', str_contains($h, 'storefront-notice-gate') && str_contains($h, '.js') && str_contains($h, 'defer'), 'defer JS');
    qc_add($checks, 'Perf preload LCP', str_contains($h, 'rel="preload" as="image"'), 'LCP preload');
    qc_add($checks, 'Perf hero min-height', str_contains($h, 'besoiu-home-inline-critical') && str_contains($h, 'min-height:640px'), 'CLS hero reserve');
    qc_add($checks, 'Perf mobile vehicle stack', str_contains($h, 'besoiu-home-inline-critical') && str_contains($h, 'grid-template-columns:1fr'), 'CLS filterbar');
    qc_add($checks, 'Perf CMS pad neutru pe hero', str_contains($h, 'besoiu-home-inline-critical') && str_contains($h, 'bpa-pad-md'), 'anti CLS CMS pad');
    qc_add($checks, 'Perf CSS minificat', str_contains($h, '.min.css'), 'assets minificate servite');
}

$robots = qc_fetch($base . '/robots.txt');
qc_add($checks, 'robots.txt OK', $robots['status'] === 200 && str_contains($robots['body'], 'Sitemap:'), trim($robots['body']));

$sitemap = qc_fetch($base . '/sitemap.xml');
qc_add($checks, 'sitemap.xml OK', $sitemap['status'] === 200 && str_contains($sitemap['body'], 'sitemapindex'), 'status=' . $sitemap['status']);

$api = qc_fetch($base . '/api/api_categorii.php?action=facets');
qc_add($checks, 'API facets JSON', $api['status'] === 200 && str_contains($api['body'], '"success"'), 'status=' . $api['status']);

$cssAsset = qc_fetch($base . '/assets/css/home-critical.min.css');
$cacheControl = qc_header($cssAsset['headers'] ?? [], 'Cache-Control');
qc_add($checks, 'Cache-Control long TTL CSS', (bool) preg_match('/max-age=(\d+)/', $cacheControl, $mm) && (int) $mm[1] >= 2592000, $cacheControl);

$jsAsset = qc_fetch($base . '/assets/js/home-tecdoc.min.js');
$cacheControlJs = qc_header($jsAsset['headers'] ?? [], 'Cache-Control');
qc_add($checks, 'Cache-Control long TTL JS', (bool) preg_match('/max-age=(\d+)/', $cacheControlJs, $mm2) && (int) $mm2[1] >= 2592000, $cacheControlJs);

if ($homeOk) {
    $imgTags = [];
    preg_match_all('#<img[^>]*>#i', $h, $imgTags);
    $missingAlt = 0;
    foreach ($imgTags[0] as $tag) {
        if (!preg_match('/\balt\s*=/i', $tag)) {
            $missingAlt++;
        }
    }
    qc_add($checks, 'A11y: toate img au alt', $missingAlt === 0, $missingAlt . ' imagini fără alt din ' . count($imgTags[0]));
}

$catalog = qc_fetch($base . '/catalog');
qc_add($checks, 'Pagină catalog 200', $catalog['status'] === 200, 'status=' . $catalog['status']);
$cart = qc_fetch($base . '/cart');
qc_add($checks, 'Pagină cart 200', $cart['status'] === 200, 'status=' . $cart['status']);
if ($catalog['status'] === 200) {
    qc_add($checks, 'Catalog: CSS shell async', str_contains($catalog['body'], 'site-shell') && str_contains($catalog['body'], 'media="print"'), 'async shell CSS');
    qc_add($checks, 'Catalog: CSS minificat', str_contains($catalog['body'], '.min.css'), 'assets minificate');
    qc_add($checks, 'Catalog: căutare cu aria-label', str_contains($catalog['body'], 'aria-label="Caută produs în catalog"'), 'input căutare catalog etichetat');
}

$llms = qc_fetch($base . '/llms.txt');
qc_add($checks, 'llms.txt OK', $llms['status'] === 200 && str_contains($llms['body'], 'Besoiu Piese Auto'), 'status=' . $llms['status']);

if ($homeOk) {
    $iconsNoAriaHidden = preg_match_all('#<i class="fa-[^"]*"[^>]*></i>#i', $h, $iconMatches);
    $iconsMissing = 0;
    foreach ($iconMatches[0] ?? [] as $iconTag) {
        if (!str_contains($iconTag, 'aria-hidden')) {
            $iconsMissing++;
        }
    }
    qc_add($checks, 'A11y: iconițe FA cu aria-hidden', $iconsMissing === 0, $iconsMissing . ' icoane fără aria-hidden');

    $buttonsNoLabel = 0;
    preg_match_all('#<button[^>]*>((?:(?!</button>).)*)</button>#is', $h, $btnMatches);
    foreach ($btnMatches[0] ?? [] as $idx => $btnTag) {
        $inner = $btnMatches[1][$idx] ?? '';
        $hasVisibleText = (bool) preg_match('/[a-zA-ZăâîșțĂÂÎȘȚ0-9]{2,}/u', preg_replace('/<[^>]+>/', '', $inner));
        $hasAriaLabel = str_contains($btnTag, 'aria-label') || str_contains($btnTag, 'title=');
        if (!$hasVisibleText && !$hasAriaLabel) {
            $buttonsNoLabel++;
        }
    }
    qc_add($checks, 'A11y: butoane icon-only cu aria-label', $buttonsNoLabel === 0, $buttonsNoLabel . ' butoane fără text/aria-label');

    qc_add($checks, 'A11y: select-uri filterbar cu label', str_contains($h, 'for="select_marca"') && str_contains($h, 'for="model_marca"'), 'label for pe selecturi vehicul');
    qc_add($checks, 'Perf widget fixed-position rezervat', str_contains($h, 'besoiu-chat-widget-slot') || !str_contains($h, '/robot/widget.js.php'), 'fără injectare widget în flow');
}
$homeCssHref = '';
if ($homeOk && preg_match('#href="([^"]*home-critical\.min\.css[^"]*)"#', $h, $mHref)) {
    $homeCssHref = $mHref[1];
}
$catCss = qc_fetch($homeCssHref !== '' ? $base . '/' . $homeCssHref : $base . '/assets/css/home-critical.min.css');
qc_add($checks, 'Perf category-grid min-height (CLS)', str_contains($catCss['body'], 'min-height:318px'), 'reservat spațiu categorii în CSS');

// --- Raport ---
$pass = 0;
$fail = 0;
echo "\n=== Quality Check: {$base} ===\n\n";
foreach ($checks as $row) {
    $icon = $row['ok'] ? '[PASS]' : '[FAIL]';
    echo "{$icon} {$row['label']}\n";
    if (!$row['ok'] || strlen($row['detail']) < 120) {
        echo "       {$row['detail']}\n";
    }
    $row['ok'] ? $pass++ : $fail++;
}
echo "\nTotal: {$pass} pass, {$fail} fail\n";
exit($fail > 0 ? 1 : 0);
