<?php

declare(strict_types=1);

namespace Evasystem\Core;

/**
 * Rezolvă slug URL admin → cale template PHP existentă.
 * Acoperă aliasuri EN/RO (suppliers→furnizori) și layout-uri nestandard.
 */
final class AdminPageResolver
{
    private const PAGES_BASE = '/admin/Templates/admin/pages';

    /** slug logic → cale relativă document root (fără leading slash opțional) */
    private const TEMPLATE_OVERRIDES = [
        'homepages' => '/admin/Templates/admin/pages/homepages.php',
        'dashboard' => '/admin/Templates/admin/pages/homepages.php',
        'import' => '/admin/Templates/admin/pages/import/import.php',
        'importproduse' => '/admin/Templates/admin/pages/import/import.php',
        'importreview' => '/admin/Templates/admin/pages/import/importreview.php',
        'reports' => '/admin/Templates/admin/pages/report/reports.php',
        'report' => '/admin/Templates/admin/pages/report/reports.php',
        'produse' => '/admin/Templates/admin/pages/produse/produse.php',
        'product' => '/admin/Templates/admin/pages/produse/produse.php',
        'products' => '/admin/Templates/admin/pages/produse/produse.php',
        'addproduse' => '/admin/Templates/admin/pages/produse/addproduse.php',
        'editproduse' => '/admin/Templates/admin/pages/produse/editproduse.php',
        'produse-selective' => '/admin/Templates/admin/pages/produse/produse-selective.php',
        'produse-vitrina' => '/admin/Templates/admin/pages/produse/produse-vitrina.php',
        'vitrina' => '/admin/Templates/admin/pages/produse/produse-vitrina.php',
        'produse-scanate' => '/admin/Templates/admin/pages/produse/produse-scanate.php',
        'scanned' => '/admin/Templates/admin/pages/produse/produse-scanate.php',
        'caiet-produse' => '/admin/Templates/admin/pages/caietcomenzi/caiet-produse.php',
        'comenzi' => '/admin/Templates/admin/pages/comenzi/comenzi.php',
        'orders' => '/admin/Templates/admin/pages/comenzi/comenzi.php',
        'order' => '/admin/Templates/admin/pages/comenzi/comenzi.php',
        'abandoned-carts' => '/admin/Templates/admin/pages/comenzi/abandoned-carts.php',
        'caietcomenzi' => '/admin/Templates/admin/pages/comenzi/comenzi.php',
        'order-edit' => '/admin/Templates/admin/pages/orders/order-edit.php',
        'order-create' => '/admin/Templates/admin/pages/orders/order-create.php',
        'furnizori' => '/admin/Templates/admin/pages/furnizori/furnizori.php',
        'suppliers' => '/admin/Templates/admin/pages/furnizori/furnizori.php',
        'supplier' => '/admin/Templates/admin/pages/furnizori/furnizori.php',
        'addfurnizori' => '/admin/Templates/admin/pages/furnizori/addfurnizori.php',
        'profilefurnizori' => '/admin/Templates/admin/pages/furnizori/profilefurnizori.php',
        'clienti' => '/admin/Templates/admin/pages/clienti/clienti.php',
        'customers' => '/admin/Templates/admin/pages/clienti/clienti.php',
        'customer' => '/admin/Templates/admin/pages/clienti/clienti.php',
        'facturi' => '/admin/Templates/admin/pages/facturi/facturi.php',
        'invoices' => '/admin/Templates/admin/pages/facturi/facturi.php',
        'invoice' => '/admin/Templates/admin/pages/facturi/facturi.php',
        'search-logs' => '/admin/Templates/admin/pages/searchlogs/searchlogs.php',
        'searchlogs' => '/admin/Templates/admin/pages/searchlogs/searchlogs.php',
        'supplier-search' => '/admin/Templates/admin/pages/supplier-search/supplier-search.php',
        'supplier-cart' => '/admin/Templates/admin/pages/supplier-search/supplier-cart.php',
        'addblog' => '/admin/Templates/admin/pages/blog/addblog.php',
        'reg' => '/admin/Templates/admin/pages/reg/reg.php',
        'reset-password' => '/admin/Templates/admin/pages/users/reset-password.php',
        'profileusers' => '/admin/Templates/admin/pages/users/profileusers.php',
        'addusers' => '/admin/Templates/admin/pages/users/addusers.php',
        'help' => '/admin/Templates/admin/pages/users/help.php',
        'login' => '/admin/Templates/admin/pages/login/login.php',
        'cron' => '/admin/Templates/admin/pages/cron/cron.php',
        'comunicare' => '/admin/Templates/admin/pages/comunicare/comunicare.php',
        'reply-templates' => '/admin/Templates/admin/pages/comunicare/reply-templates.php',
        'comunicare-canale' => '/admin/Templates/admin/pages/comunicare/comunicare-canale.php',
        'comunicare-leads' => '/admin/Templates/admin/pages/comunicare/comunicare-leads.php',
        'comunicare-broadcast' => '/admin/Templates/admin/pages/comunicare/comunicare-broadcast.php',
        'comunicare-archive' => '/admin/Templates/admin/pages/comunicare/comunicare-archive.php',
        'marketplace-pieseauto' => '/admin/Templates/admin/pages/marketplace/pieseauto.php',
        'marketplace-baselinker' => '/admin/Templates/admin/pages/marketplace/baselinker.php',
        'export' => '/admin/Templates/admin/pages/export/export.php',
    ];

    /** slug URL → director template (pentru bootstrap rute) */
    public const ROUTE_DIRS = [
        'dashboard' => '/admin/Templates/admin/pages/',
        'product' => '/admin/Templates/admin/pages/produse/',
        'products' => '/admin/Templates/admin/pages/produse/',
        'vitrina' => '/admin/Templates/admin/pages/produse/',
        'scanned' => '/admin/Templates/admin/pages/produse/',
        'caiet-produse' => '/admin/Templates/admin/pages/caietcomenzi/',
        'addproduse' => '/admin/Templates/admin/pages/produse/',
        'editproduse' => '/admin/Templates/admin/pages/produse/',
        'produse-selective' => '/admin/Templates/admin/pages/produse/',
        'categorii' => '/admin/Templates/admin/pages/categorii/',
        'adaoscomercial' => '/admin/Templates/admin/pages/adaoscomercial/',
        'import' => '/admin/Templates/admin/pages/import/',
        'importreview' => '/admin/Templates/admin/pages/import/',
        'searchlogs' => '/admin/Templates/admin/pages/searchlogs/',
        'search-logs' => '/admin/Templates/admin/pages/searchlogs/',
        'orders' => '/admin/Templates/admin/pages/comenzi/',
        'order' => '/admin/Templates/admin/pages/comenzi/',
        'abandoned-carts' => '/admin/Templates/admin/pages/comenzi/',
        'caietcomenzi' => '/admin/Templates/admin/pages/caietcomenzi/',
        'order-edit' => '/admin/Templates/admin/pages/orders/',
        'order-create' => '/admin/Templates/admin/pages/orders/',
        'supplier-search' => '/admin/Templates/admin/pages/supplier-search/',
        'supplier-cart' => '/admin/Templates/admin/pages/supplier-search/',
        'suppliers' => '/admin/Templates/admin/pages/furnizori/',
        'supplier' => '/admin/Templates/admin/pages/furnizori/',
        'furnizori' => '/admin/Templates/admin/pages/furnizori/',
        'facturi' => '/admin/Templates/admin/pages/facturi/',
        'invoices' => '/admin/Templates/admin/pages/facturi/',
        'invoice' => '/admin/Templates/admin/pages/facturi/',
        'livrare' => '/admin/Templates/admin/pages/livrare/',
        'delivery' => '/admin/Templates/admin/pages/livrare/',
        'clienti' => '/admin/Templates/admin/pages/clienti/',
        'customers' => '/admin/Templates/admin/pages/clienti/',
        'customer' => '/admin/Templates/admin/pages/clienti/',
        'bots' => '/admin/Templates/admin/pages/bots/',
        'messages' => '/admin/Templates/admin/pages/messages/',
        'marketplace' => '/admin/Templates/admin/pages/marketplace/',
        'marketplace-pieseauto' => '/admin/Templates/admin/pages/marketplace/',
        'marketplace-baselinker' => '/admin/Templates/admin/pages/marketplace/',
        'export' => '/admin/Templates/admin/pages/export/',
        'cron' => '/admin/Templates/admin/pages/cron/',
        'comunicare' => '/admin/Templates/admin/pages/comunicare/',
        'reply-templates' => '/admin/Templates/admin/pages/comunicare/',
        'comunicare-canale' => '/admin/Templates/admin/pages/comunicare/',
        'comunicare-leads' => '/admin/Templates/admin/pages/comunicare/',
        'comunicare-broadcast' => '/admin/Templates/admin/pages/comunicare/',
        'comunicare-archive' => '/admin/Templates/admin/pages/comunicare/',
        'cross-reference' => '/admin/Templates/admin/pages/cross-reference/',
        'reports' => '/admin/Templates/admin/pages/report/',
        'website' => '/admin/Templates/admin/pages/website/',
        'blog' => '/admin/Templates/admin/pages/blog/',
        'addblog' => '/admin/Templates/admin/pages/blog/',
        'users' => '/admin/Templates/admin/pages/users/',
        'profileusers' => '/admin/Templates/admin/pages/users/',
        'addusers' => '/admin/Templates/admin/pages/users/',
        'reset-password' => '/admin/Templates/admin/pages/users/',
        'help' => '/admin/Templates/admin/pages/users/',
        'reg' => '/admin/Templates/admin/pages/reg/',
        'alerts' => '/admin/Templates/admin/pages/alerts/',
        'scraper' => '/admin/Templates/admin/pages/scraper/',
        'backup' => '/admin/Templates/admin/pages/backup/',
        'settings' => '/admin/Templates/admin/pages/settings/',
        'ai-tokens' => '/admin/Templates/admin/pages/ai-tokens/',
        'system-errors' => '/admin/Templates/admin/pages/system-errors/',
        'caiet' => '/admin/Templates/admin/pages/caietcomenzi/',
        'caiet-produse' => '/admin/Templates/admin/pages/caietcomenzi/',
        'caiet-clienti' => '/admin/Templates/admin/pages/caietcomenzi/',
        'caiet-facturi' => '/admin/Templates/admin/pages/caietcomenzi/',
        'caiet-incasari' => '/admin/Templates/admin/pages/caietcomenzi/',
    ];

    public static function resolveTemplate(string $urlSlug, ?string $pageDirectory = null): ?string
    {
        $keys = self::candidateKeys($urlSlug);
        foreach ($keys as $key) {
            if (isset(self::TEMPLATE_OVERRIDES[$key])) {
                $path = self::TEMPLATE_OVERRIDES[$key];
                if (self::fileExists($path)) {
                    return $path;
                }
            }
        }

        foreach ($keys as $key) {
            $path = self::resolveFromDirectory($key, $pageDirectory);
            if ($path !== null) {
                return $path;
            }
            $path = self::resolveFromDirectory($key, self::PAGES_BASE);
            if ($path !== null) {
                return $path;
            }
        }

        return null;
    }

    public static function routeDirectory(string $urlSlug): string
    {
        if (isset(self::ROUTE_DIRS[$urlSlug])) {
            return self::ROUTE_DIRS[$urlSlug];
        }

        $resolved = AdminUrl::resolvePageKey($urlSlug);
        if (isset(self::ROUTE_DIRS[$resolved])) {
            return self::ROUTE_DIRS[$resolved];
        }

        return self::PAGES_BASE . '/' . $resolved . '/';
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

    private static function resolveFromDirectory(string $key, ?string $directory): ?string
    {
        if ($directory === null || trim($directory) === '') {
            return null;
        }

        $base = rtrim(str_replace('\\', '/', $directory), '/');
        $candidates = [
            $base . '/' . $key . '/' . $key . '.php',
            $base . '/' . $key . '.php',
            $base . '/index.php',
        ];

        foreach ($candidates as $candidate) {
            if (self::fileExists($candidate)) {
                return $candidate;
            }
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
