<?php

declare(strict_types=1);

namespace Evasystem\Core;

/**
 * URL-uri admin curate — fără /public/ în browser.
 * Ex: /admin/dashboard, /admin/product, /admin/orders
 */
final class AdminUrl
{
    public const BASE = '/admin';
    public const LEGACY_PREFIX = '/admin/public';
    public const PUBLIC_SITE = 'https://besoiupieseauto.ro';

    /** Domeniu public (APP_URL) — niciodată besoiupieseauto.ro.test */
    public static function siteBaseUrl(): string
    {
        static $base = null;
        if ($base !== null) {
            return $base;
        }

        $fromEnv = trim((string) ($_ENV['APP_URL'] ?? getenv('APP_URL') ?: ''));
        if ($fromEnv !== '') {
            $fromEnv = (string) preg_replace(
                '#^https?://[^/]*besoiupieseauto\.ro\.test(?::\d+)?#i',
                self::PUBLIC_SITE,
                $fromEnv
            );
            $base = rtrim($fromEnv, '/');
            return $base;
        }

        $base = self::PUBLIC_SITE;
        return $base;
    }

    /** Site magazin + cale (ex. /catalog) — URL absolut producție. */
    public static function publicSiteUrl(string $path = '/'): string
    {
        $path = $path === '' ? '/' : $path;
        if ($path[0] !== '/') {
            $path = '/' . $path;
        }

        return self::siteBaseUrl() . $path;
    }

    /**
     * Link meniu admin — mereu cale relativă (/admin/…).
     * Păstrează hostul curent (besoiupieseauto.ro sau Laragon); nu forța cross-domain.
     */
    public static function navPath(string $slug = ''): string
    {
        return self::path($slug);
    }

    /** URL absolut admin pe domeniul producție — webhook, Task Scheduler, linkuri externe */
    public static function adminHref(string $slugOrPath = ''): string
    {
        if ($slugOrPath === '') {
            return self::siteBaseUrl() . self::BASE;
        }

        $path = str_starts_with($slugOrPath, '/')
            ? $slugOrPath
            : self::path($slugOrPath);

        return self::siteBaseUrl() . $path;
    }

    /**
     * Înlocuiește domeniul .test cu besoiupieseauto.ro; lasă path-uri relative nemodificate.
     */
    public static function normalizePublicHref(string $href): string
    {
        $href = trim($href);
        if ($href === '' || $href[0] === '#' || preg_match('#^(mailto:|tel:|javascript:)#i', $href)) {
            return $href;
        }

        if (preg_match('#^https?://[^/]*besoiupieseauto\.ro\.test(?::\d+)?(/.*)?$#i', $href, $m)) {
            $path = ($m[1] ?? '') !== '' ? (string) $m[1] : '/';
            return self::publicSiteUrl($path);
        }

        if ($href[0] === '/' && ($href === self::BASE || str_starts_with($href, self::BASE . '/'))) {
            return self::adminHref($href);
        }

        if ($href === '/' || $href === '') {
            return self::publicSiteUrl('/');
        }

        return $href;
    }

    /** Slug curat în URL → nume fișier pagină (basename template). */
    public const PAGE_ALIASES = [
        'dashboard' => 'homepages',
        'product' => 'produse',
        'products' => 'produse',
        'orders' => 'comenzi',
        'order' => 'comenzi',
        'suppliers' => 'furnizori',
        'supplier' => 'furnizori',
        'customers' => 'clienti',
        'customer' => 'clienti',
        'invoices' => 'facturi',
        'invoice' => 'facturi',
        'messages' => 'messages',
        'settings' => 'settings',
        'users' => 'users',
        'login' => 'login',
        'logout' => 'logout',
        'search-logs' => 'searchlogs',
        'searchlogs' => 'searchlogs',
        'import' => 'import',
        'importproduse' => 'import',
        'importreview' => 'importreview',
        'categorii' => 'categorii',
        'categories' => 'categorii',
        'facturi' => 'facturi',
        'clienti' => 'clienti',
        'livrare' => 'livrare',
        'furnizori' => 'furnizori',
        'adaoscomercial' => 'adaoscomercial',
        'addproduse' => 'addproduse',
        'editproduse' => 'editproduse',
        'addblog' => 'addblog',
        'reg' => 'reg',
        'scraper' => 'scraper',
        'backup' => 'backup',
        'bots' => 'bots',
        'website' => 'website',
        'categories' => 'categorii',
        'blog' => 'blog',
        'reports' => 'reports',
        'cron' => 'cron',
        'scan' => 'scan',
        'alerts' => 'alerts',
        'help' => 'help',
        'vitrina' => 'produse-vitrina',
        'scanned' => 'produse-scanate',
        'produse-vitrina' => 'produse-vitrina',
        'produse-scanate' => 'produse-scanate',
        'supplier-search' => 'supplier-search',
        'supplier-cart' => 'supplier-cart',
        'system-errors' => 'system-errors',
        'ai-tokens' => 'ai-tokens',
        'cross-reference' => 'cross-reference',
        'delivery' => 'livrare',
        'marketplace' => 'marketplace',
        'caiet' => 'caietcomenzi',
    ];

    public static function path(string $slug = ''): string
    {
        $slug = trim($slug, '/');
        if ($slug === '') {
            return self::BASE;
        }

        return self::BASE . '/' . $slug;
    }

    public static function page(string $logicalName): string
    {
        $slug = array_search($logicalName, self::PAGE_ALIASES, true);
        if ($slug !== false) {
            return self::path((string) $slug);
        }

        return self::path($logicalName);
    }

    public static function asset(string $relativePath): string
    {
        return self::publicAsset($relativePath);
    }

    public static function publicAsset(string $relativePath): string
    {
        return self::BASE . '/public/assets/' . ltrim($relativePath, '/');
    }

    public static function api(string $relativePath): string
    {
        return self::BASE . '/api/' . ltrim($relativePath, '/');
    }

    /** Pentru echo în template-uri fără htmlspecialchars pe path-uri statice. */
    public static function e(string $slug = ''): string
    {
        return htmlspecialchars(self::path($slug), ENT_QUOTES, 'UTF-8');
    }

    /** Slug din URL curat → cheie pagină PHP (homepages, produse, …). */
    public static function resolvePageKey(string $urlSlug): string
    {
        return self::PAGE_ALIASES[$urlSlug] ?? $urlSlug;
    }

    /** /admin/dashboard → /admin/dashboard */
    public static function legacyPathToClean(string $path): ?string
    {
        if (!str_starts_with($path, self::LEGACY_PREFIX)) {
            return null;
        }

        $suffix = substr($path, strlen(self::LEGACY_PREFIX));
        $suffix = trim($suffix, '/');
        if ($suffix === '') {
            return self::path('login');
        }

        $legacySlug = explode('/', $suffix)[0];
        $cleanSlug = array_search($legacySlug, self::PAGE_ALIASES, true);
        if ($cleanSlug !== false) {
            return self::path((string) $cleanSlug);
        }

        return self::path($legacySlug);
    }

    /**
     * Normalizează REQUEST_URI pentru rutare + permisiuni.
     * Rezolvă conflictul folder fizic admin/cron/ (DirectoryIndex → index.php).
     */
    public static function normalizeRequestPath(string $uriOrPath): string
    {
        $path = parse_url($uriOrPath, PHP_URL_PATH) ?? '/';
        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }

        if (preg_match('#^' . preg_quote(self::path('cron'), '#') . '/index\.php$#i', $path)) {
            return self::path('cron');
        }

        if (str_ends_with($path, '/index.php') && str_starts_with($path, self::BASE . '/')) {
            $stripped = substr($path, 0, -strlen('/index.php'));
            return $stripped === '' ? self::BASE : $stripped;
        }

        return $path;
    }

    public static function currentRequestPath(): string
    {
        return self::normalizeRequestPath((string) ($_SERVER['REQUEST_URI'] ?? '/'));
    }

    /**
     * Variante de path pentru potrivire rută DB (curat + legacy).
     *
     * @return list<string>
     */
    public static function alternatePaths(string $path): array
    {
        $path = self::normalizeRequestPath($path);
        $path = rtrim($path, '/') ?: '/';
        $paths = [$path];

        if (str_starts_with($path, self::BASE) && !str_starts_with($path, self::LEGACY_PREFIX)) {
            $slug = basename($path);
            if ($slug === 'admin' || $slug === '') {
                $paths[] = self::LEGACY_PREFIX;
                $paths[] = self::LEGACY_PREFIX . '/login';
            } else {
                $legacyPage = self::resolvePageKey($slug);
                $paths[] = self::LEGACY_PREFIX . '/' . $legacyPage;
            }
        }

        return array_values(array_unique($paths));
    }

    public static function redirectLegacyIfNeeded(string $requestPath): void
    {
        $clean = self::legacyPathToClean($requestPath);
        if ($clean === null || $clean === $requestPath) {
            return;
        }

        $query = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_QUERY);
        if (is_string($query) && $query !== '') {
            $clean .= '?' . $query;
        }

        if (!headers_sent()) {
            header('Location: ' . $clean, true, 301);
            exit;
        }
    }
}
