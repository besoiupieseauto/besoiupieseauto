<?php

declare(strict_types=1);

namespace Evasystem\Core\Auth;

/**
 * Portal departamente admin — 7 zone de lucru.
 */
final class AdminWorkspaceCatalog
{
    /** @return array<string, array<string, mixed>> */
    public static function all(): array
    {
        return [
            'orders' => [
                'id' => 'orders',
                'label' => 'Clienți și Comenzi',
                'desc' => 'Comenzi site, facturi, AWB, clienți.',
                'accent' => '#1abc9c',
                'accent2' => '#0d9488',
                'dashboard' => '/admin/orders',
                'tags' => ['Comenzi', 'Clienți', 'AWB'],
                'features' => [
                    'dashboard.home',
                    'comenzi.list', 'comenzi.create', 'comenzi.supplier_search',
                    'comenzi.caiet', 'comenzi.facturi', 'comenzi.livrare',
                    'comenzi.abandoned_carts',
                    'clienti.list',
                ],
            ],
            'suppliers' => [
                'id' => 'suppliers',
                'label' => 'Produse Furnizori',
                'desc' => 'Catalog piese auto, import CSV, vitrină, categorii, stoc.',
                'accent' => '#f59e0b',
                'accent2' => '#d97706',
                'dashboard' => '/admin/product',
                'tags' => ['Produse', 'Import', 'Furnizori', 'TecDoc'],
                'features' => [
                    'dashboard.home',
                    'furnizori.compare', 'furnizori.list',
                    'produse.list', 'produse.vitrina', 'produse.scanned', 'produse.caiet',
                    'produse.categorii', 'produse.adaos',
                    'produse.import', 'produse.import_queue',
                ],
            ],
            'ai' => [
                'id' => 'ai',
                'label' => 'AI, agenți și robotică',
                'desc' => 'Boți WhatsApp, cron, scraper — fără mesagerie.',
                'accent' => '#8b5cf6',
                'accent2' => '#7c3aed',
                'dashboard' => '/admin/bots',
                'tags' => ['Boți', 'Cron', 'Scraper'],
                'features' => [
                    'dashboard.home',
                    'automatizare.bots',
                    'automatizare.cron', 'automatizare.scraper',
                ],
            ],
            'social' => [
                'id' => 'social',
                'label' => 'Comunicare & Socializare',
                'desc' => 'Chat, template-uri răspuns, canale, lead-uri, broadcast.',
                'accent' => '#14b8a6',
                'accent2' => '#0d9488',
                'dashboard' => '/admin/comunicare',
                'tags' => ['Mesagerie', 'Template-uri', 'Social'],
                'features' => [
                    'dashboard.home',
                    'comunicare.hub', 'comunicare.messages', 'comunicare.templates',
                    'comunicare.channels', 'comunicare.leads',
                    'comunicare.broadcast', 'comunicare.archive',
                ],
            ],
            'marketing' => [
                'id' => 'marketing',
                'label' => 'Marketing și promovare',
                'desc' => 'Marketplace OLX, rapoarte, search logs.',
                'accent' => '#ec4899',
                'accent2' => '#db2777',
                'dashboard' => '/admin/marketplace',
                'tags' => ['OLX', 'Rapoarte', 'OEM'],
                'features' => [
                    'dashboard.home',
                    'automatizare.marketplace',
                    'analiza.searchlogs', 'analiza.oem', 'analiza.reports',
                ],
            ],
            'shop' => [
                'id' => 'shop',
                'label' => 'Produse Besoiupieseauto',
                'desc' => 'Site web, CMS, blog — produse și conținut digital.',
                'accent' => '#38bdf8',
                'accent2' => '#2563eb',
                'dashboard' => '/admin/website',
                'tags' => ['Website', 'CMS', 'Blog'],
                'features' => [
                    'dashboard.home',
                    'website.pages', 'website.blog',
                ],
            ],
            'company' => [
                'id' => 'company',
                'label' => 'Company Settings',
                'desc' => 'Utilizatori, setări, backup, alerte.',
                'accent' => '#64748b',
                'accent2' => '#475569',
                'dashboard' => '/admin/settings',
                'tags' => ['Setări', 'Echipă', 'Backup'],
                'features' => [
                    'dashboard.home',
                    'sistem.settings', 'sistem.backup', 'sistem.ai_tokens', 'sistem.alerts',
                    'utilizatori.manage',
                ],
            ],
        ];
    }

    /** Mapare ID-uri vechi (3 departamente) → noi. */
    private const LEGACY_IDS = [
        'sales' => 'orders',
        'catalog' => 'suppliers',
        'ops' => 'ai',
        'comunicare' => 'social',
    ];

    public static function resolveId(string $id): ?string
    {
        if (isset(self::all()[$id])) {
            return $id;
        }

        return self::LEGACY_IDS[$id] ?? null;
    }

    /** @return list<string> */
    public static function ids(): array
    {
        return array_keys(self::all());
    }

    /** @return array<string, mixed>|null */
    public static function get(string $id): ?array
    {
        $resolved = self::resolveId($id);

        return $resolved !== null ? self::all()[$resolved] : null;
    }

    /** @return list<string> */
    public static function featuresFor(string $workspaceId): array
    {
        $def = self::get($workspaceId);

        return $def['features'] ?? [];
    }

    public static function dashboardPath(string $workspaceId): string
    {
        return (string) (self::get($workspaceId)['dashboard'] ?? '/admin/dashboard');
    }

    /** @return list<string> */
    public static function urlsForWorkspace(string $workspaceId): array
    {
        $features = AdminPermissionCatalog::allFeatures();
        $urls = [];

        foreach (self::featuresFor($workspaceId) as $key) {
            if (!isset($features[$key])) {
                continue;
            }
            foreach ($features[$key]['urls'] as $url) {
                $urls[] = $url;
            }
        }

        return array_values(array_unique($urls));
    }

    /** @return array<string, string> path normalizat => workspace id */
    public static function navPathWorkspaceMap(): array
    {
        $map = [];
        foreach (self::ids() as $wsId) {
            foreach (self::urlsForWorkspace($wsId) as $url) {
                $path = rtrim(parse_url($url, PHP_URL_PATH) ?: $url, '/') ?: '/';
                $map[$path] = $wsId;
            }
        }

        return $map;
    }

    public static function workspaceForPath(string $href): ?string
    {
        $path = rtrim(parse_url($href, PHP_URL_PATH) ?: $href, '/') ?: '/';
        $map = self::navPathWorkspaceMap();

        foreach ($map as $prefix => $wsId) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                return $wsId;
            }
        }

        return null;
    }
}
