<?php

declare(strict_types=1);

/** Placeholder mic (~4 KB) — nu folosi 1.jpg/2.jpg/3.jpg demo (1.8 MB). */
function besoiu_default_product_image(): string
{
    return 'assets/images/products/product-1.jpg';
}

/** @return list<string> */
function besoiu_oversized_demo_images(): array
{
    return [
        'assets/images/products/1.jpg',
        'assets/images/products/2.jpg',
        'assets/images/products/3.jpg',
    ];
}

function besoiu_project_root_for_image(): string
{
    return defined('BESOIU_ROOT') ? BESOIU_ROOT : dirname(__DIR__, 2);
}

/**
 * Înlocuiește demo-urile uriașe cu WebP optimizat sau placeholder mic.
 */
function besoiu_resolve_product_image(string $url): string
{
    $url = trim(str_replace('\\', '/', $url));
    if ($url === '') {
        return besoiu_default_product_image();
    }

    $normalized = ltrim($url, '/');
    foreach (besoiu_oversized_demo_images() as $demo) {
        if ($normalized === $demo || str_ends_with($normalized, basename($demo))) {
            $webp = preg_replace('/\.jpe?g$/i', '.webp', $demo) ?? $demo;
            $root = besoiu_project_root_for_image();
            if (is_file($root . '/' . str_replace('/', DIRECTORY_SEPARATOR, $webp))) {
                return $webp;
            }

            return besoiu_default_product_image();
        }
    }

    if (preg_match('/\.webp$/i', $normalized)) {
        return $normalized;
    }

    return $url;
}

/**
 * @return array<int, string> width => relative path
 */
function besoiu_product_image_variants(string $url): array
{
    $src = besoiu_resolve_product_image($url);
    $root = besoiu_project_root_for_image();
    $variants = [];

    $base = preg_replace('/\.(webp|jpe?g|png)$/i', '', ltrim($src, '/')) ?? ltrim($src, '/');
    foreach ([200, 400] as $width) {
        $candidate = $base . '-' . $width . '.webp';
        $full = $root . '/' . str_replace('/', DIRECTORY_SEPARATOR, $candidate);
        if (is_file($full)) {
            $variants[$width] = $candidate;
        }
    }

    if ($variants === [] && preg_match('/\.webp$/i', $src)) {
        $full = $root . '/' . str_replace('/', DIRECTORY_SEPARATOR, ltrim($src, '/'));
        if (is_file($full)) {
            $variants[400] = ltrim($src, '/');
        }
    }

    return $variants;
}

function besoiu_image_public_path(string $path): string
{
    return str_replace(["\\", "'", ')'], ['/', '%27', '%29'], ltrim($path, '/'));
}

/**
 * @param array{eager?:bool,width?:int,height?:int,class?:string,fetchpriority?:string,sizes?:string} $opts
 */
function besoiu_render_product_image(string $url, string $alt, array $opts = []): void
{
    $esc = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

    $variants = besoiu_product_image_variants($url);
    if ($variants !== []) {
        $src = $variants[200] ?? $variants[400] ?? (string) reset($variants);
    } else {
        $src = besoiu_resolve_product_image($url);
    }

    $width = max(1, (int) ($opts['width'] ?? 130));
    $height = max(1, (int) ($opts['height'] ?? 130));
    $class = trim((string) ($opts['class'] ?? ''));
    $eager = !empty($opts['eager']);
    $loading = $eager ? 'eager' : 'lazy';
    $fetch = (string) ($opts['fetchpriority'] ?? ($eager ? 'high' : 'auto'));
    $sizes = (string) ($opts['sizes'] ?? '(max-width: 768px) 130px, (max-width: 1200px) 340px, 400px');
    $classAttr = $class !== '' ? ' class="' . $esc($class) . '"' : '';

    $href = besoiu_image_public_path($src);

    if ($variants !== []) {
        $parts = [];
        foreach ($variants as $w => $path) {
            $parts[] = besoiu_image_public_path($path) . ' ' . $w . 'w';
        }
        echo '<img src="' . $esc($href) . '" alt="' . $esc($alt) . '"'
            . ' width="' . $width . '" height="' . $height . '"'
            . ' srcset="' . $esc(implode(', ', $parts)) . '"'
            . ' sizes="' . $esc($sizes) . '"'
            . ' loading="' . $loading . '" decoding="async" fetchpriority="' . $esc($fetch) . '"'
            . $classAttr . '>';

        return;
    }

    echo '<img src="' . $esc($href) . '" alt="' . $esc($alt) . '"'
        . ' width="' . $width . '" height="' . $height . '"'
        . ' loading="' . $loading . '" decoding="async" fetchpriority="' . $esc($fetch) . '"'
        . $classAttr . '>';
}

/**
 * Preferă WebP local când există (ex. img/car1.png → assets/img/car1.webp).
 * Reduce „Improve image delivery” (PNG-uri multi-MB).
 */
function besoiu_prefer_local_webp(string $url): string
{
    $url = trim($url);
    if ($url === '' || str_starts_with($url, 'data:') || preg_match('#^https?://#i', $url)) {
        return $url;
    }

    $path = parse_url($url, PHP_URL_PATH);
    if (!is_string($path) || $path === '') {
        $path = $url;
    }
    $path = ltrim(str_replace('\\', '/', $path), '/');
    if ($path === '' || str_contains($path, '..')) {
        return $url;
    }

    if (!preg_match('/\.(png|jpe?g)$/i', $path)) {
        return $url;
    }

    $root = besoiu_project_root_for_image();
    $candidates = [
        preg_replace('/\.(png|jpe?g)$/i', '.webp', $path) ?: '',
        'assets/' . (preg_replace('/\.(png|jpe?g)$/i', '.webp', $path) ?: ''),
        'assets/img/' . basename(preg_replace('/\.(png|jpe?g)$/i', '.webp', $path) ?: ''),
        'img/' . basename(preg_replace('/\.(png|jpe?g)$/i', '.webp', $path) ?: ''),
    ];

    foreach ($candidates as $candidate) {
        $candidate = ltrim(str_replace('\\', '/', (string) $candidate), '/');
        if ($candidate === '' || str_contains($candidate, '..')) {
            continue;
        }
        if (is_file($root . '/' . $candidate)) {
            return $candidate;
        }
    }

    return $url;
}

/** Logo header — WebP direct + fetchpriority=high (LCP pe homepage). */
function besoiu_render_logo_img(string $alt = 'Besoiu Piese Auto', string $class = 'logo-img'): void
{
    $esc = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    $root = besoiu_project_root_for_image();
    $webp = 'assets/img/logo.webp';
    $png = 'img/logo.png';
    $hasWebp = is_file($root . '/assets/img/logo.webp');

    if ($hasWebp) {
        echo '<img src="' . $esc($webp) . '" alt="' . $esc($alt) . '" class="' . $esc($class) . '"'
            . ' width="125" height="42" decoding="async" loading="eager" fetchpriority="high">';

        return;
    }

    echo '<img src="' . $esc($png) . '" alt="' . $esc($alt) . '" class="' . $esc($class) . '"'
        . ' width="125" height="42" decoding="async" loading="eager" fetchpriority="high">';
}
