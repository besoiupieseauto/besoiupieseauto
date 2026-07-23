<?php

declare(strict_types=1);

namespace Besoiu\Core\Auth;

/**
 * Portal departamente admin — zone de lucru (nav minimal: dashboard + setări + utilizatori).
 */
final class AdminWorkspaceCatalog
{
    /** Funcții permise în fiecare zonă (nav lateral minimal). */
    private const MINIMAL_FEATURES = [
        'dashboard.home',
        'sistem.settings',
        'utilizatori.manage',
    ];

    /** @return array<string, array<string, mixed>> */
    public static function all(): array
    {
        $features = self::MINIMAL_FEATURES;

        return [
            'orders' => [
                'id' => 'orders',
                'label' => 'Clienți și Comenzi',
                'desc' => 'Comenzi, clienți, facturi și furnizori B2B — flux comercial complet.',
                'accent' => '#1abc9c',
                'accent2' => '#0d9488',
                'dashboard' => '/admin/dashboard',
                'tags' => ['Dashboard', 'Setări'],
                'features' => $features,
            ],
            'suppliers' => [
                'id' => 'suppliers',
                'label' => 'Produse Furnizori',
                'desc' => 'Catalog, furnizori, import și formare preț.',
                'accent' => '#f59e0b',
                'accent2' => '#d97706',
                'dashboard' => '/admin/dashboard',
                'tags' => ['Dashboard', 'Setări'],
                'features' => $features,
            ],
            'ai' => [
                'id' => 'ai',
                'label' => 'AI, agenți și robotică',
                'desc' => 'Zona de lucru — dashboard și administrare conturi.',
                'accent' => '#8b5cf6',
                'accent2' => '#7c3aed',
                'dashboard' => '/admin/dashboard',
                'tags' => ['Dashboard', 'Setări'],
                'features' => $features,
            ],
            'social' => [
                'id' => 'social',
                'label' => 'Comunicare & Socializare',
                'desc' => 'Zona de lucru — dashboard și administrare conturi.',
                'accent' => '#14b8a6',
                'accent2' => '#0d9488',
                'dashboard' => '/admin/dashboard',
                'tags' => ['Dashboard', 'Setări'],
                'features' => $features,
            ],
            'marketing' => [
                'id' => 'marketing',
                'label' => 'Marketing și promovare',
                'desc' => 'Zona de lucru — dashboard și administrare conturi.',
                'accent' => '#ec4899',
                'accent2' => '#db2777',
                'dashboard' => '/admin/dashboard',
                'tags' => ['Dashboard', 'Setări'],
                'features' => $features,
            ],
            'shop' => [
                'id' => 'shop',
                'label' => 'Produse Besoiupieseauto',
                'desc' => 'Zona de lucru — dashboard și administrare conturi.',
                'accent' => '#38bdf8',
                'accent2' => '#2563eb',
                'dashboard' => '/admin/dashboard',
                'tags' => ['Dashboard', 'Setări'],
                'features' => $features,
            ],
            'company' => [
                'id' => 'company',
                'label' => 'Company Settings',
                'desc' => 'Setări centrale, utilizatori și acces admin.',
                'accent' => '#64748b',
                'accent2' => '#475569',
                'dashboard' => '/admin/dashboard',
                'tags' => ['Setări', 'Echipă'],
                'features' => $features,
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

        return $def['features'] ?? self::MINIMAL_FEATURES;
    }

    public static function dashboardPath(string $workspaceId): string
    {
        return AdminSectionDashboardCatalog::url(
            AdminSectionDashboardCatalog::defaultForWorkspace($workspaceId)
        );
    }

    /** Secțiuni meniu permise per departament (URL-uri din AdminPermissionCatalog). */
    private const WORKSPACE_SECTIONS = [
        'orders' => ['furnizori', 'comenzi', 'clienti'],
        'shop' => ['produse'],
        'suppliers' => ['produse', 'furnizori'],
        'ai' => ['automatizare', 'analiza'],
        'social' => ['comunicare'],
        'marketing' => ['marketing', 'website'],
        'company' => [
            'dashboard', 'furnizori', 'produse', 'comenzi', 'clienti',
            'automatizare', 'comunicare', 'marketing', 'analiza', 'website', 'sistem', 'utilizatori',
        ],
    ];

    /** @return list<string> Secțiuni vizibile în meniul lateral pentru workspace (gol = toate). */
    public static function menuSectionsForWorkspace(string $workspaceId): array
    {
        $ws = self::resolveId($workspaceId) ?? 'company';
        if ($ws === 'company') {
            return [];
        }

        return self::WORKSPACE_SECTIONS[$ws] ?? [];
    }

    /** @param list<string> $sectionKeys @return list<string> */
    private static function urlsFromPermissionSections(array $sectionKeys): array
    {
        $urls = [];
        $sections = AdminPermissionCatalog::sections();
        foreach ($sectionKeys as $key) {
            if (!isset($sections[$key])) {
                continue;
            }
            foreach ($sections[$key]['features'] as $feat) {
                foreach ($feat['urls'] as $url) {
                    $urls[] = (string) $url;
                }
            }
        }

        return $urls;
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

        $ws = self::resolveId($workspaceId) ?? 'company';
        $urls = array_merge($urls, self::urlsFromPermissionSections(self::WORKSPACE_SECTIONS[$ws] ?? []));

        // Module active — toate modulele opționale din workspace-ul lor
        if (class_exists(\Besoiu\Core\Module\ModuleGate::class)) {
            foreach (\Besoiu\Core\Module\ModuleGate::optionalMap() as $moduleId => $meta) {
                if (!\Besoiu\Core\Module\ModuleGate::enabled((string) $moduleId)) {
                    continue;
                }
                $moduleWorkspace = self::workspaceForModuleId((string) $moduleId, is_array($meta) ? $meta : []);
                if ($moduleWorkspace !== $workspaceId) {
                    continue;
                }
                foreach ($meta['paths'] ?? [] as $p) {
                    $urls[] = (string) $p;
                }
                foreach ($meta['slugs'] ?? [] as $slug) {
                    $slug = trim((string) $slug);
                    if ($slug === '') {
                        continue;
                    }
                    $urls[] = '/admin/' . $slug;
                }
            }
        }

        return array_values(array_unique($urls));
    }

    /**
     * Mapare modul → workspace (departament).
     */
    public static function workspaceForModuleId(string $moduleId, array $meta = []): string
    {
        if (!empty($meta['workspace']) && is_string($meta['workspace'])) {
            $resolved = self::resolveId($meta['workspace']);
            if ($resolved !== null) {
                return $resolved;
            }
        }

        if ($moduleId === 'users') {
            return 'company';
        }

        if ($moduleId === 'furnizori' || $moduleId === 'clienti' || $moduleId === 'supplier_search') {
            return 'orders';
        }

        return 'company';
    }

    /** @return array<string, string> path normalizat => workspace id */
    public static function navPathWorkspaceMap(): array
    {
        $map = [];
        foreach (self::ids() as $wsId) {
            foreach (self::urlsForWorkspace($wsId) as $url) {
                $path = rtrim(parse_url($url, PHP_URL_PATH) ?: $url, '/') ?: '/';
                if (!isset($map[$path])) {
                    $map[$path] = $wsId;
                }
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
