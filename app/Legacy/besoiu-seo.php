<?php

declare(strict_types=1);

require_once __DIR__ . '/url.php';

/**
 * Meta SEO reutilizabil — canonical, Open Graph, Twitter Card.
 *
 * @param array{
 *   title?: string,
 *   description?: string,
 *   path?: string,
 *   image?: string,
 *   type?: string,
 *   noindex?: bool,
 *   locale?: string
 * } $meta
 */
function besoiu_seo_page_meta(string $path, array $meta, string $type = 'website'): array
{
    $title = trim((string) ($meta['title'] ?? 'Besoiu Piese Auto'));
    $description = trim((string) ($meta['description'] ?? ''));
    $image = trim((string) ($meta['image'] ?? '/assets/img/logo.png'));

    return [
        'title' => $title,
        'description' => $description,
        'path' => $path === '' ? '/' : $path,
        'image' => $image,
        'type' => $type,
        'noindex' => !empty($meta['noindex']),
        'locale' => (string) ($meta['locale'] ?? 'ro_RO'),
    ];
}

/** @param array<string, mixed> $meta */
function besoiu_render_seo_meta(array $meta): void
{
    $title = trim((string) ($meta['title'] ?? ''));
    $description = trim((string) ($meta['description'] ?? ''));
    $path = (string) ($meta['path'] ?? '/');
    $type = (string) ($meta['type'] ?? 'website');
    $noindex = !empty($meta['noindex']);
    $locale = (string) ($meta['locale'] ?? 'ro_RO');

    $canonical = besoiu_absolute_url($path);
    $imagePath = trim((string) ($meta['image'] ?? '/assets/img/logo.png'));
    if ($imagePath !== '' && $imagePath[0] === '/') {
        $imageUrl = besoiu_absolute_url($imagePath);
    } elseif (preg_match('#^https?://#i', $imagePath)) {
        $imageUrl = $imagePath;
    } else {
        $imageUrl = besoiu_absolute_url('/' . ltrim($imagePath, '/'));
    }

    $esc = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

    echo '<link rel="canonical" href="' . $esc($canonical) . '">' . "\n";
    if ($noindex) {
        echo '<meta name="robots" content="noindex,nofollow">' . "\n";
    } else {
        echo '<meta name="robots" content="index,follow,max-image-preview:large">' . "\n";
    }

    echo '<meta property="og:locale" content="' . $esc($locale) . '">' . "\n";
    echo '<meta property="og:site_name" content="Besoiu Piese Auto">' . "\n";
    echo '<meta property="og:type" content="' . $esc($type) . '">' . "\n";
    echo '<meta property="og:url" content="' . $esc($canonical) . '">' . "\n";
    if ($title !== '') {
        echo '<meta property="og:title" content="' . $esc($title) . '">' . "\n";
    }
    if ($description !== '') {
        echo '<meta property="og:description" content="' . $esc($description) . '">' . "\n";
        echo '<meta name="twitter:description" content="' . $esc($description) . '">' . "\n";
    }
    echo '<meta property="og:image" content="' . $esc($imageUrl) . '">' . "\n";
    echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
    if ($title !== '') {
        echo '<meta name="twitter:title" content="' . $esc($title) . '">' . "\n";
    }
    echo '<meta name="twitter:image" content="' . $esc($imageUrl) . '">' . "\n";
}

function besoiu_seo_indexing_blocked(): bool
{
    if (!function_exists('besoiu_preview_load_env')) {
        require_once __DIR__ . '/preview-gate.php';
    }

    besoiu_preview_load_env();

    if (besoiu_preview_is_local_host()) {
        return filter_var(getenv('SITE_SEO_NOINDEX') ?: '0', FILTER_VALIDATE_BOOL);
    }

    if (filter_var(getenv('SITE_SEO_NOINDEX') ?: '0', FILTER_VALIDATE_BOOL)) {
        return true;
    }

    return besoiu_preview_mode_enabled();
}
