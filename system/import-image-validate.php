<?php
declare(strict_types=1);

/**
 * Validare URL imagine import — respinge placeholder, stock photos, rezultate SerpAPI greșite.
 */

function besoiu_import_image_is_placeholder(string $url): bool
{
    $url = trim($url);
    if ($url === '') {
        return true;
    }

    foreach ([
        'preview-12.jpg',
        'fakers/',
        '/dist/images/fakers/',
        'placeholder',
        'no-image',
        'noimage',
        'default.jpg',
    ] as $needle) {
        if (stripos($url, $needle) !== false) {
            return true;
        }
    }

    return false;
}

/** @return array<int, string> */
function besoiu_import_image_blocked_url_fragments(): array
{
    return [
        'googleusercontent.com',
        'gstatic.com',
        'facebook.com',
        'fbcdn.net',
        'instagram.com',
        'pinterest.',
        'gravatar.com',
        'avatar',
        '/profile',
        'wikimedia.org',
        'wikipedia.org',
        'shutterstock',
        'gettyimages',
        'istockphoto',
        'dreamstime',
        'alamy.com',
        'stock-photo',
        'stockphoto',
        'unsplash.com',
        'pexels.com',
        'lookaside.fbsbx',
        'twimg.com',
    ];
}

/** @return array<int, string> */
function besoiu_import_image_allowed_url_fragments(): array
{
    return [
        'tecalliance',
        'tecdoc',
        'caietcomenzi.ro',
        'besoiupieseauto.ro',
        '/uploads/products/',
        'digital-assets.',
        'images.auto',
        'intercars.eu',
        'iccdn.',
        'materom.',
        'autonet.',
        'autopartner.',
        'elit.',
        'lkq.',
        'rapidapi',
        'cdn.',
        'media.',
        'img.',
        'image.',
        'autodoc24.ro',
        'autodoc.de',
        'media.autodoc',
        'emagst.akamaized.net',
        'emag.ro',
    ];
}

function besoiu_import_image_url_host_blocked(string $url): bool
{
    $lower = strtolower($url);
    foreach (besoiu_import_image_blocked_url_fragments() as $fragment) {
        if ($fragment !== '' && str_contains($lower, $fragment)) {
            return true;
        }
    }

    return false;
}

function besoiu_import_image_url_host_allowed(string $url): bool
{
    if (besoiu_import_image_url_host_blocked($url)) {
        return false;
    }

    $lower = strtolower($url);
    foreach (besoiu_import_image_allowed_url_fragments() as $fragment) {
        if ($fragment !== '' && str_contains($lower, $fragment)) {
            return true;
        }
    }

    return false;
}

function besoiu_import_image_local_upload_exists(string $url): bool
{
    if (!str_starts_with($url, '/uploads/')) {
        return false;
    }

    $root = dirname(__DIR__);
    $localPath = $root . $url;
    $minBytes = str_contains($url, '/uploads/products/emag/') ? 200 : 512;

    return is_file($localPath) && (int) filesize($localPath) >= $minBytes;
}

function besoiu_import_image_url_is_trusted(string $url, string $source = ''): bool
{
    $url = trim($url);
    if ($url === '' || besoiu_import_image_is_placeholder($url)) {
        return false;
    }

    if (besoiu_import_image_local_upload_exists($url)) {
        return true;
    }

    if (str_starts_with($url, '/uploads/')) {
        return false;
    }

    $source = strtolower(trim($source));
    if (in_array($source, ['missing', 'import', 'csv', 'supplier', 'supplier_catalog'], true)) {
        return false;
    }

    if ($source === 'serpapi') {
        return false;
    }

    if (in_array($source, ['tecdoc_api', 'caietcomenzi', 'mesterino', 'epiesa_search', 'emag_search', 'autodoc_scraper', 'autodoc'], true)) {
        return besoiu_import_image_url_host_allowed($url);
    }

    if (stripos($url, 'caietcomenzi.ro/PozeEmag') !== false) {
        return false;
    }

    if (!str_starts_with($url, 'http://') && !str_starts_with($url, 'https://')) {
        return false;
    }

    return besoiu_import_image_url_host_allowed($url);
}

/** @param array<string, mixed> $row */
function besoiu_import_row_image_url(array $row): string
{
    $images = json_decode((string) ($row['pImages'] ?? '[]'), true);
    if (!is_array($images)) {
        return '';
    }

    foreach ($images as $candidate) {
        $url = trim((string) $candidate);
        if ($url === '') {
            continue;
        }
        if (besoiu_import_image_url_is_trusted($url, (string) ($row['pImageSource'] ?? ''))) {
            return $url;
        }
    }

    return '';
}

/** @param array<string, mixed> $row */
function besoiu_import_row_has_trusted_image(array $row): bool
{
    return besoiu_import_row_image_url($row) !== '';
}
