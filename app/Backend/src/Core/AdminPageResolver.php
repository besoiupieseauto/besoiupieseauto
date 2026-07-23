<?php

declare(strict_types=1);

namespace Besoiu\Core;

use Besoiu\Core\Module\ModuleGate;
use Besoiu\Core\Module\ModulesPaths;

/**
 * Rezolvă slug URL admin → cale template PHP existentă.
 * Prioritate: pagini modul (modules/{Id}/pages) → shim-uri Templates → fallback director.
 */
final class AdminPageResolver
{
    private const PAGES_BASE = '/admin/Templates/admin/pages';

    /** Shim-uri CORE + aliasuri pentru module active (Templates delegă la modules/). */
    private const TEMPLATE_OVERRIDES = [
        'homepages' => '/admin/Templates/admin/pages/homepages.php',
        'dashboard' => '/admin/Templates/admin/pages/homepages.php',
        'reg' => '/admin/Templates/admin/pages/reg/reg.php',
        'reset-password' => '/admin/Templates/admin/pages/users/reset-password.php',
        'profileusers' => '/admin/Templates/admin/pages/users/profileusers.php',
        'addusers' => '/admin/Templates/admin/pages/users/addusers.php',
        'help' => '/admin/Templates/admin/pages/users/help.php',
        'users' => '/admin/Templates/admin/pages/users/users.php',
        'login' => '/admin/Templates/admin/pages/login/login.php',
        'settings' => '/admin/Templates/admin/pages/settings/settings.php',
        'ai-agent' => '/admin/Templates/admin/pages/ai-rag/ai-rag.php',
        'ai-rag' => '/admin/Templates/admin/pages/ai-rag/ai-rag.php',
        'furnizori' => '/admin/Templates/admin/pages/furnizori/furnizori.php',
        'suppliers' => '/admin/Templates/admin/pages/furnizori/furnizori.php',
        'supplier' => '/admin/Templates/admin/pages/furnizori/furnizori.php',
        'addfurnizori' => '/admin/Templates/admin/pages/furnizori/addfurnizori.php',
        'profilefurnizori' => '/admin/Templates/admin/pages/furnizori/profilefurnizori.php',
        'clienti' => '/admin/Templates/admin/pages/clienti/clienti.php',
        'customers' => '/admin/Templates/admin/pages/clienti/clienti.php',
        'customer' => '/admin/Templates/admin/pages/clienti/clienti.php',
        'addclienti' => '/admin/Templates/admin/pages/clienti/addclienti.php',
        'profileclienti' => '/admin/Templates/admin/pages/clienti/profileclienti.php',
        'supplier-search' => '/admin/Templates/admin/pages/supplier-search/supplier-search.php',
        'supplier-cart' => '/admin/Templates/admin/pages/supplier-search/supplier-cart.php',
        'searching' => '/admin/Templates/admin/pages/supplier-search/searching.php',
        'scraper' => '/admin/Templates/admin/pages/scraper/scraper.php',
        'scraper-web' => '/admin/Templates/admin/pages/scraper-web/scraper-web.php',
    
        // BEGIN module_gen: import_pro
        'import-pro' => '/admin/Templates/admin/pages/import-pro/import-pro.php',
        // END module_gen: import_pro

        // BEGIN module_gen: import
        'import-system' => '/admin/Templates/admin/pages/import-system/import-system.php',
        'import-cards' => '/admin/Templates/admin/pages/import-cards/import-cards.php',
        'import-bovsoft' => '/admin/Templates/admin/pages/import-bovsoft/import-bovsoft.php',
        'import' => '/admin/Templates/admin/pages/import/import.php',
        'importproduse' => '/admin/Templates/admin/pages/import/import.php',
        // END module_gen: import

        'nav' => '/admin/Templates/admin/pages/nav/nav.php',

        // BEGIN module_gen: produse
        'product' => '/admin/Templates/admin/pages/product/product.php',
        'vitrina' => '/admin/Templates/admin/pages/vitrina/vitrina.php',
        'scanned' => '/admin/Templates/admin/pages/scanned/scanned.php',
        'addproduse' => '/admin/Templates/admin/pages/addproduse/addproduse.php',
        'editproduse' => '/admin/Templates/admin/pages/editproduse/editproduse.php',
        // END module_gen: produse

        // BEGIN module_gen: coada_import
        'importreview' => '/admin/Templates/admin/pages/importreview/importreview.php',
        'import-review' => '/admin/Templates/admin/pages/importreview/importreview.php',
        // END module_gen: coada_import

        // BEGIN module_gen: adaos_comercial
        'adaoscomercial' => '/admin/Templates/admin/pages/adaoscomercial/adaoscomercial.php',
        'adaos-comercial' => '/admin/Templates/admin/pages/adaoscomercial/adaoscomercial.php',
        // END module_gen: adaos_comercial

        // BEGIN module_gen: categorii
        'categorii' => '/admin/Templates/admin/pages/categorii/categorii.php',
        // END module_gen: categorii

        // BEGIN module_gen: product_formation
        'product-formation' => '/admin/Templates/admin/pages/product-formation/product-formation.php',
        'produse-formare' => '/admin/Templates/admin/pages/product-formation/product-formation.php',
        // END module_gen: product_formation

        'caiet-produse' => '/admin/Templates/admin/pages/caietcomenzi/caiet-produse.php',
        'caietcomenzi' => '/admin/Templates/admin/pages/caietcomenzi/caiet-produse.php',
    ];

    /** slug URL → director template (bootstrap rute — doar module + CORE). */
    public const ROUTE_DIRS = [
        'dashboard' => '/admin/Templates/admin/pages/',
        'settings' => '/admin/Templates/admin/pages/settings/',
        'users' => '/admin/Templates/admin/pages/users/',
        'profileusers' => '/admin/Templates/admin/pages/users/',
        'addusers' => '/admin/Templates/admin/pages/users/',
        'reset-password' => '/admin/Templates/admin/pages/users/',
        'help' => '/admin/Templates/admin/pages/users/',
        'reg' => '/admin/Templates/admin/pages/reg/',
        'login' => '/admin/Templates/admin/pages/login/',
        'alerts' => '/admin/Templates/admin/pages/alerts/',
        'system-errors' => '/admin/Templates/admin/pages/system-errors/',
        'workspace' => '/admin/Templates/admin/pages/workspace/',
        'supplier-search' => '/admin/Templates/admin/pages/supplier-search/',
        'supplier-cart' => '/admin/Templates/admin/pages/supplier-search/',
        'searching' => '/admin/Templates/admin/pages/supplier-search/',
        'suppliers' => '/admin/Templates/admin/pages/furnizori/',
        'supplier' => '/admin/Templates/admin/pages/furnizori/',
        'furnizori' => '/admin/Templates/admin/pages/furnizori/',
        'addfurnizori' => '/admin/Templates/admin/pages/furnizori/',
        'profilefurnizori' => '/admin/Templates/admin/pages/furnizori/',
        'clienti' => '/admin/Templates/admin/pages/clienti/',
        'customers' => '/admin/Templates/admin/pages/clienti/',
        'customer' => '/admin/Templates/admin/pages/clienti/',
        'addclienti' => '/admin/Templates/admin/pages/clienti/',
        'profileclienti' => '/admin/Templates/admin/pages/clienti/',
        'scraper' => '/admin/Templates/admin/pages/scraper/',
        'scraper-web' => '/admin/Templates/admin/pages/scraper-web/',
        'nav' => '/admin/Templates/admin/pages/nav/',
        'caiet-produse' => '/admin/Templates/admin/pages/caietcomenzi/',
        'caietcomenzi' => '/admin/Templates/admin/pages/caietcomenzi/',
    ];

    public static function resolveTemplate(string $urlSlug, ?string $pageDirectory = null): ?string
    {
        $keys = self::candidateKeys($urlSlug);

        foreach ($keys as $key) {
            $meta = self::modulePagesMeta($key);
            if ($meta === null) {
                continue;
            }
            $allowIndex = ($key === $meta['primary']);
            $path = self::resolveFromDirectory($key, $meta['dir'], $allowIndex);
            if ($path !== null) {
                return $path;
            }
        }

        foreach ($keys as $key) {
            if (isset(self::TEMPLATE_OVERRIDES[$key])) {
                $path = self::TEMPLATE_OVERRIDES[$key];
                if (self::fileExists($path)) {
                    return $path;
                }
            }
        }

        foreach ($keys as $key) {
            $path = self::resolveFromDirectory($key, $pageDirectory, true);
            if ($path !== null) {
                return $path;
            }
            $path = self::resolveFromDirectory($key, self::PAGES_BASE, true);
            if ($path !== null) {
                return $path;
            }
        }

        return null;
    }

    public static function routeDirectory(string $urlSlug): string
    {
        $meta = self::modulePagesMeta($urlSlug);
        if ($meta !== null) {
            $allowIndex = ($urlSlug === $meta['primary']);
            $probe = self::resolveFromDirectory($urlSlug, $meta['dir'], $allowIndex);
            if ($probe !== null) {
                return $meta['dir'];
            }
        }

        if (isset(self::ROUTE_DIRS[$urlSlug])) {
            return self::ROUTE_DIRS[$urlSlug];
        }

        $resolved = AdminUrl::resolvePageKey($urlSlug);
        if (isset(self::ROUTE_DIRS[$resolved])) {
            return self::ROUTE_DIRS[$resolved];
        }

        if ($meta !== null) {
            return $meta['dir'];
        }

        return self::PAGES_BASE . '/' . $resolved . '/';
    }

    public static function modulePagesDirectory(string $urlSlug): ?string
    {
        $meta = self::modulePagesMeta($urlSlug);

        return $meta['dir'] ?? null;
    }

    /** @return list<string> */
    private static function candidateKeys(string $urlSlug): array
    {
        $urlSlug = trim($urlSlug, '/');
        if ($urlSlug === '') {
            return ['homepages', 'dashboard'];
        }

        $resolved = AdminUrl::resolvePageKey($urlSlug);

        return array_values(array_unique([$urlSlug, $resolved]));
    }

    private static function resolveFromDirectory(string $key, ?string $directory, bool $allowIndexFallback = true): ?string
    {
        if ($directory === null || trim($directory) === '') {
            return null;
        }

        $base = rtrim(str_replace('\\', '/', $directory), '/');
        $candidates = [
            $base . '/' . $key . '/' . $key . '.php',
            $base . '/' . $key . '.php',
        ];
        if ($allowIndexFallback) {
            $candidates[] = $base . '/index.php';
        }

        foreach ($candidates as $candidate) {
            if (self::fileExists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private static function modulePrimarySlug(string $moduleId, array $meta): string
    {
        $slugs = $meta['slugs'] ?? [];
        if (is_array($slugs) && isset($slugs[0]) && is_string($slugs[0]) && $slugs[0] !== '') {
            return strtolower($slugs[0]);
        }

        return strtolower($moduleId);
    }

    /**
     * @return array{dir: string, primary: string}|null
     */
    private static function modulePagesMeta(string $urlSlug): ?array
    {
        $slug = strtolower(trim($urlSlug));
        if ($slug === '' || !class_exists(ModuleGate::class)) {
            return null;
        }

        foreach (ModuleGate::optionalMap() as $moduleId => $meta) {
            $slugs = $meta['slugs'] ?? [];
            if (!in_array($slug, $slugs, true)) {
                continue;
            }
            if (!ModuleGate::enabled((string) $moduleId)) {
                return null;
            }

            $folder = (string) ($meta['folder'] ?? '');
            if ($folder === '') {
                $parts = preg_split('/[^a-zA-Z0-9]+/', (string) $moduleId) ?: [];
                $folder = '';
                foreach ($parts as $part) {
                    if ($part !== '') {
                        $folder .= ucfirst(strtolower($part));
                    }
                }
            }
            if ($folder === '') {
                return null;
            }

            $pages = ModulesPaths::modulesRoot()
                . DIRECTORY_SEPARATOR . $folder
                . DIRECTORY_SEPARATOR . 'pages';
            if (!is_dir($pages)) {
                return null;
            }

            return [
                'dir' => '/admin/modules/' . $folder . '/pages/',
                'primary' => self::modulePrimarySlug((string) $moduleId, is_array($meta) ? $meta : []),
            ];
        }

        return null;
    }

    private static function fileExists(string $relativePath): bool
    {
        $root = self::documentRoot();
        if ($root === '') {
            return false;
        }

        $path = $root . '/' . ltrim(str_replace('\\', '/', $relativePath), '/');
        return is_file($path);
    }

    private static function documentRoot(): string
    {
        $root = $_SERVER['DOCUMENT_ROOT'] ?? '';
        if (!is_string($root) || trim($root) === '') {
            $root = dirname(__DIR__, 3);
        }

        $real = realpath($root);

        return $real !== false ? $real : rtrim(str_replace('\\', '/', $root), '/');
    }
}
