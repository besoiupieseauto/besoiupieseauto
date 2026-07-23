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
        '/admin/public/import-pro/',
        'import-pro/_proxy/',
        'product-image.php',
        'scraped-image.php',
        'prelucrare%20fisiere',
    ];
}

function besoiu_import_image_is_admin_proxy_url(string $url): bool
{
    $lower = strtolower(trim($url));
    if ($lower === '') {
        return false;
    }

    foreach ([
        '/admin/public/import-pro/',
        'import-pro/_proxy/',
        'product-image.php',
        'scraped-image.php',
    ] as $fragment) {
        if (str_contains($lower, $fragment)) {
            return true;
        }
    }

    return false;
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

function besoiu_import_image_scraper_asset_exists(string $url): bool
{
    $url = trim(str_replace('\\', '/', $url));
    if ($url === '' || !str_starts_with($url, '/assets/scraper/')) {
        return false;
    }

    if (class_exists('ScraperPaths', false)) {
        $path = ScraperPaths::localPathFromPublicAssetUrl($url);
        if ($path !== '' && is_file($path) && (int) filesize($path) >= 64) {
            return true;
        }
    }

    $roots = [];
    if (defined('BESOIU_ROOT')) {
        $roots[] = rtrim(str_replace('\\', '/', (string) BESOIU_ROOT), '/');
    }
    $roots[] = rtrim(str_replace('\\', '/', dirname(__DIR__, 2)), '/');

    foreach (array_unique(array_filter($roots)) as $root) {
        $localPath = $root . $url;
        if (is_file($localPath) && (int) filesize($localPath) >= 64) {
            return true;
        }
    }

    return false;
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

    if (besoiu_import_image_is_admin_proxy_url($url)) {
        return true;
    }

    if (str_starts_with($url, '/uploads/')) {
        return false;
    }

    $source = strtolower(trim($source));
    if (in_array($source, ['missing', 'import', 'supplier', 'supplier_catalog'], true)) {
        return false;
    }

    if ($source === 'serpapi') {
        return false;
    }

    $trustedSources = [
        'tecdoc_api', 'tecdoc', 'caietcomenzi', 'local_ttc_poze', 'autopartner_local', 'autopartner',
        'poze', 'import_pro', 'mesterino', 'epiesa_search', 'epiesa_scraper', 'epiesa', 'emag_search',
        'autodoc_scraper', 'autodoc', 'csv',
    ];
    if (in_array($source, $trustedSources, true)) {
        return besoiu_import_image_url_host_allowed($url) || besoiu_import_image_is_admin_proxy_url($url);
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

/**
 * Imagine afișabilă în coadă (trusted, stocată sau reconstruită TecDoc/Poze) — pentru lane-uri importreview.
 *
 * @param array<string, mixed> $row
 */
function besoiu_import_row_has_queue_image(array $row): bool
{
    if (besoiu_import_row_has_trusted_image($row)) {
        return true;
    }

    if (besoiu_import_row_stored_image_url($row) !== '') {
        return true;
    }

    $rebuilt = besoiu_import_row_rebuild_image_from_metadata($row);
    $url = trim((string) ($rebuilt['url'] ?? ''));

    return $url !== '' && !besoiu_import_image_is_placeholder($url);
}

/**
 * Prima imagine stocată — pentru preview în UI (chiar dacă nu e încă „trusted” pentru publicare).
 *
 * @param array<string, mixed> $row
 */
function besoiu_import_row_stored_image_url(array $row): string
{
    $images = json_decode((string) ($row['pImages'] ?? '[]'), true);
    if (!is_array($images)) {
        return '';
    }

    foreach ($images as $candidate) {
        $url = besoiu_import_normalize_image_candidate_url(trim((string) $candidate));
        if ($url !== '' && !besoiu_import_image_is_placeholder($url)) {
            return $url;
        }
    }

    return '';
}

function besoiu_import_admin_proxy_base(): string
{
    return '/admin/public/import-pro/_proxy/';
}

function besoiu_import_poze_proxy_url(string $brand, string $ttcArtId): string
{
    $brand = ltrim(trim($brand), '=');
    $ttcArtId = trim($ttcArtId);
    if ($brand === '' || $ttcArtId === '' || !preg_match('/^\d+$/', $ttcArtId)) {
        return '';
    }

    return besoiu_import_admin_proxy_base() . 'product-image.php?source=poze&brand='
        . rawurlencode($brand) . '&id=' . rawurlencode($ttcArtId);
}

function besoiu_import_autopartner_proxy_url(string $code): string
{
    $code = trim($code);
    if ($code === '') {
        return '';
    }

    return besoiu_import_admin_proxy_base() . 'product-image.php?source=autopartner&code='
        . rawurlencode($code);
}

function besoiu_import_scraped_proxy_url(string $path): string
{
    $path = trim(str_replace('\\', '/', $path));
    if ($path === '') {
        return '';
    }

    return besoiu_import_admin_proxy_base() . 'scraped-image.php?path=' . rawurlencode($path);
}

function besoiu_import_normalize_image_candidate_url(string $url): string
{
    $url = trim($url);
    if ($url === '' || besoiu_import_image_is_placeholder($url)) {
        return '';
    }

    if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://') || str_starts_with($url, '/')) {
        return $url;
    }

    if (str_contains($url, '/') || preg_match('/\.(jpe?g|png|webp|gif)$/i', $url)) {
        return besoiu_import_scraped_proxy_url($url);
    }

    return $url;
}

/**
 * Reconstruiește URL imagine din raw_json (Poze/Autopartner/TecDoc) când pImages a fost golit.
 *
 * @param array<string, mixed> $row
 * @return array{url: string, source: string}
 */
function besoiu_import_row_rebuild_image_from_metadata(array $row): array
{
    $raw = json_decode((string) ($row['raw_json'] ?? '{}'), true);
    if (!is_array($raw)) {
        return ['url' => '', 'source' => ''];
    }

    $defaultBrand = trim((string) ($row['pBrand'] ?? ''));

    foreach ([
        is_array($raw['tecdoc_file'] ?? null) ? $raw['tecdoc_file'] : [],
        is_array($raw['tecdoc_api'] ?? null) ? $raw['tecdoc_api'] : [],
        is_array($raw['tecdoc_import_enrichment'] ?? null) ? $raw['tecdoc_import_enrichment'] : [],
    ] as $section) {
        $ttcId = trim((string) ($section['ttc_art_id'] ?? ''));
        $brand = trim((string) ($section['art_brand'] ?? $section['query_brand'] ?? $defaultBrand));
        if ($ttcId !== '') {
            $proxy = besoiu_import_poze_proxy_url($brand, $ttcId);
            if ($proxy !== '') {
                return ['url' => $proxy, 'source' => 'local_ttc_poze'];
            }
        }
    }

    $audit = is_array($raw['tecdoc_audit'] ?? null) ? $raw['tecdoc_audit'] : [];
    $ttcId = trim((string) ($audit['ttcArtId'] ?? ''));
    if ($ttcId !== '') {
        $proxy = besoiu_import_poze_proxy_url($defaultBrand, $ttcId);
        if ($proxy !== '') {
            return ['url' => $proxy, 'source' => 'local_ttc_poze'];
        }
    }

    $apCode = trim((string) ($raw['autopartner_code'] ?? $raw['autopartnerCode'] ?? ''));
    if ($apCode === '' && is_array($raw['import_pro_card'] ?? null)) {
        $apCode = trim((string) ($raw['import_pro_card']['autopartnerCode'] ?? ''));
    }
    if ($apCode !== '') {
        $proxy = besoiu_import_autopartner_proxy_url($apCode);
        if ($proxy !== '') {
            return ['url' => $proxy, 'source' => 'autopartner_local'];
        }
    }

    foreach (['rows', 'source_rows'] as $key) {
        foreach ((array) ($raw[$key] ?? []) as $sourceRow) {
            if (!is_array($sourceRow)) {
                continue;
            }
            $ttcId = trim((string) ($sourceRow['ttc art id'] ?? $sourceRow['ttc_art_id'] ?? ''));
            $brand = trim((string) ($sourceRow['art brand'] ?? $sourceRow['art_brand'] ?? $defaultBrand));
            if ($ttcId !== '') {
                $proxy = besoiu_import_poze_proxy_url($brand, $ttcId);
                if ($proxy !== '') {
                    return ['url' => $proxy, 'source' => 'local_ttc_poze'];
                }
            }
        }
    }

    $remote = trim((string) ($raw['tecdoc_api']['image_url'] ?? $raw['tecdoc_api']['remote_url'] ?? ''));
    if ($remote !== '' && !besoiu_import_image_is_placeholder($remote)) {
        return ['url' => $remote, 'source' => 'tecdoc_api'];
    }

    if (class_exists(\Besoiu\Services\LocalTtcImageLibrary::class)) {
        $hit = \Besoiu\Services\LocalTtcImageLibrary::instance()->lookupForProduct($row);
        if (is_array($hit)) {
            $url = trim((string) ($hit['url'] ?? ''));
            if ($url !== '' && !besoiu_import_image_is_placeholder($url)) {
                return [
                    'url' => $url,
                    'source' => trim((string) ($hit['source'] ?? 'local_ttc_poze')),
                ];
            }
        }
    }

    return ['url' => '', 'source' => ''];
}

/**
 * URL pentru afișare în importreview — include reconstrucție din TecDoc/Poze.
 *
 * @param array<string, mixed> $row
 */
function besoiu_import_row_resolve_preview_image_url(array $row): string
{
    $trusted = besoiu_import_row_image_url($row);
    if ($trusted !== '') {
        return $trusted;
    }

    $stored = besoiu_import_row_stored_image_url($row);
    if ($stored !== '') {
        return $stored;
    }

    $rebuilt = besoiu_import_row_rebuild_image_from_metadata($row);

    return (string) ($rebuilt['url'] ?? '');
}

/**
 * Persistă în rând imaginea reconstruită (când pImages a fost golit anterior).
 *
 * @param array<string, mixed> $row
 * @return array<string, mixed>
 */
function besoiu_import_row_hydrate_missing_image(array $row): array
{
    if (besoiu_import_row_stored_image_url($row) !== '') {
        return $row;
    }

    $rebuilt = besoiu_import_row_rebuild_image_from_metadata($row);
    $url = trim((string) ($rebuilt['url'] ?? ''));
    if ($url === '') {
        return $row;
    }

    $source = trim((string) ($rebuilt['source'] ?? 'local_ttc_poze'));
    $row['pImages'] = json_encode([$url], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $row['pImageSource'] = $source;

    $raw = json_decode((string) ($row['raw_json'] ?? '{}'), true);
    if (!is_array($raw)) {
        $raw = [];
    }
    $raw['__image_source'] = $source;
    $raw['__image_rebuilt_at'] = date('c');
    $row['raw_json'] = json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    return $row;
}
