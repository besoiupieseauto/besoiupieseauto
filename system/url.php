<?php

declare(strict_types=1);

/**
 * URL-uri curate storefront — relative, fără .php și fără domeniu .test
 */

/** Domeniu public (SEO, absolute) — niciodată .test */
function besoiu_site_base_url(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }

    $fromEnv = trim((string) (getenv('APP_URL') ?: ($_ENV['APP_URL'] ?? '')));
    if ($fromEnv !== '') {
        $fromEnv = (string) preg_replace(
            '#^https?://[^/]*besoiupieseauto\.ro\.test(?::\d+)?#i',
            'https://besoiupieseauto.ro',
            $fromEnv
        );
        $base = rtrim($fromEnv, '/');
        return $base;
    }

    $base = 'https://besoiupieseauto.ro';
    return $base;
}

/** URL absolut pentru canonical / OG — producție, fără .test */
function besoiu_absolute_url(string $path = '/'): string
{
    $path = besoiu_normalize_href($path);
    if ($path === '' || $path[0] !== '/') {
        $path = '/' . ltrim($path, '/');
    }

    return besoiu_site_base_url() . $path;
}

function besoiu_url(string $page = 'home', array $query = []): string
{
    static $map = [
        'home' => '/',
        'index' => '/',
        'catalog' => '/catalog',
        'cart' => '/cart',
        'product' => '/produs',
        'produs' => '/produs',
        'account' => '/cont',
        'cont' => '/cont',
        'contact' => '/contact',
        'about' => '/despre',
        'despre' => '/despre',
        'blog' => '/blog',
        'blog-articol' => '/articol',
        'articol' => '/articol',
        'cum-comand' => '/cum-comand',
        'livrare-plata' => '/livrare-plata',
        'retur-garantie' => '/retur-garantie',
        'intrebari-frecvente' => '/intrebari-frecvente',
        'termeni-conditii' => '/termeni-conditii',
        'politica-confidentialitate' => '/politica-confidentialitate',
        'politica-cookies' => '/politica-cookies',
        'cariere' => '/cariere',
    ];

    $path = $map[$page] ?? '/' . trim($page, '/');
    if ($query === []) {
        return $path;
    }

    return $path . '?' . http_build_query($query);
}

/** Normalizează orice href intern: fără .test, fără .php, relative pe site. */
function besoiu_normalize_href(string $href): string
{
    $href = trim($href);
    if ($href === '' || $href[0] === '#' || preg_match('#^(mailto:|tel:|javascript:)#i', $href)) {
        return $href;
    }

    if (preg_match('#^https?://[^/]*besoiupieseauto\.ro\.test(?::\d+)?(/.*)?$#i', $href, $m)) {
        $path = ($m[1] ?? '') !== '' ? (string) $m[1] : '/';
        return besoiu_normalize_internal_path($path);
    }

    if (preg_match('#^https?://[^/]*besoiupieseauto\.ro(?::\d+)?(/.*)?$#i', $href, $m)) {
        $path = ($m[1] ?? '') !== '' ? (string) $m[1] : '/';
        return besoiu_normalize_internal_path($path);
    }

    if (preg_match('#^//#', $href)) {
        return $href;
    }

    if (!preg_match('#^https?://#i', $href)) {
        return besoiu_normalize_internal_path($href);
    }

    return $href;
}

function besoiu_normalize_internal_path(string $path): string
{
    $path = trim($path);
    if ($path === '' || $path === '/') {
        return '/';
    }

    $query = '';
    if (str_contains($path, '?')) {
        [$path, $query] = explode('?', $path, 2);
        $query = '?' . $query;
    }

    if ($path[0] !== '/') {
        $path = '/' . $path;
    }

    static $map = [
        '/index.php' => '/',
        '/catalog.php' => '/catalog',
        '/cart.php' => '/cart',
        '/product.php' => '/produs',
        '/cont.php' => '/cont',
        '/contact.php' => '/contact',
        '/about.php' => '/despre',
        '/blog.php' => '/blog',
        '/blog-articol.php' => '/articol',
        '/cariere.php' => '/cariere',
        '/cum-comand.php' => '/cum-comand',
        '/livrare-plata.php' => '/livrare-plata',
        '/retur-garantie.php' => '/retur-garantie',
        '/intrebari-frecvente.php' => '/intrebari-frecvente',
        '/termeni-conditii.php' => '/termeni-conditii',
        '/politica-confidentialitate.php' => '/politica-confidentialitate',
        '/politica-cookies.php' => '/politica-cookies',
    ];

    if (isset($map[$path])) {
        return $map[$path] . $query;
    }

    if (str_ends_with($path, '.php')) {
        return substr($path, 0, -4) . $query;
    }

    return $path . $query;
}

/**
 * @param array<string,mixed> $data
 * @return array<string,mixed>
 */
function besoiu_normalize_hrefs_in_array(array $data): array
{
    foreach ($data as $key => $value) {
        if (is_array($value)) {
            $data[$key] = besoiu_normalize_hrefs_in_array($value);
            continue;
        }

        if (!is_string($value)) {
            continue;
        }

        if (in_array($key, ['href', 'link_href', 'url', 'button_href', 'cta_href', 'secondary_href'], true)) {
            $data[$key] = besoiu_normalize_href($value);
        }
    }

    return $data;
}

function besoiu_href(string $page = 'home', array $query = []): string
{
    return htmlspecialchars(besoiu_url($page, $query), ENT_QUOTES, 'UTF-8');
}

function besoiu_product_url(array $params): string
{
    return besoiu_url('product', $params);
}
