<?php

declare(strict_types=1);

namespace Besoiu\Core\Auth;

/**
 * Dashboard dedicat per secțiune meniu (bloc sidebar).
 * Fiecare section_key are URL unic: /admin/dashboard?section={key}
 */
final class AdminSectionDashboardCatalog
{
    /** @var array<string, array{label: string, workspace: string, desc: string, icon: string}> */
    private const SECTIONS = [
        'core_dashboard' => [
            'label' => 'Dashboard',
            'workspace' => 'company',
            'desc' => 'Panou central admin.',
            'icon' => 'layout-dashboard',
        ],
        'core_produse' => [
            'label' => 'Produse',
            'workspace' => 'suppliers',
            'desc' => 'Catalog, vitrină și stoc.',
            'icon' => 'package',
        ],
        'core_comenzi' => [
            'label' => 'Comenzi',
            'workspace' => 'orders',
            'desc' => 'Flux comercial și livrări.',
            'icon' => 'shopping-cart',
        ],
        'core_comunicare' => [
            'label' => 'Comunicare',
            'workspace' => 'social',
            'desc' => 'Mesaje și canale.',
            'icon' => 'radio',
        ],
        'core_automatizare' => [
            'label' => 'Automatizare',
            'workspace' => 'ai',
            'desc' => 'Export, cron și integrări.',
            'icon' => 'bot',
        ],
        'core_marketing' => [
            'label' => 'Marketing',
            'workspace' => 'marketing',
            'desc' => 'Promovare și planner.',
            'icon' => 'target',
        ],
        'core_analiza' => [
            'label' => 'Analiză',
            'workspace' => 'suppliers',
            'desc' => 'Rapoarte și echivalențe.',
            'icon' => 'bar-chart-3',
        ],
        'core_website' => [
            'label' => 'Web site',
            'workspace' => 'shop',
            'desc' => 'CMS și pagini publice.',
            'icon' => 'globe',
        ],
        'core_sistem' => [
            'label' => 'Sistem',
            'workspace' => 'company',
            'desc' => 'Utilizatori, alerte și setări.',
            'icon' => 'settings',
        ],
        'mod_furnizori' => [
            'label' => 'Furnizori B2B',
            'workspace' => 'orders',
            'desc' => 'Furnizori, credențiale și comparare.',
            'icon' => 'truck',
        ],
        'mod_supplier_search' => [
            'label' => 'Supplier Search B2B',
            'workspace' => 'orders',
            'desc' => 'Căutare paralelă furnizori și coș.',
            'icon' => 'search-check',
        ],
        'mod_clienti' => [
            'label' => 'Clienți CRM',
            'workspace' => 'orders',
            'desc' => 'Clienți magazin și profiluri.',
            'icon' => 'users',
        ],
    ];

    /** @var array<string, string> workspace → section_key implicit la intrare */
    private const WORKSPACE_DEFAULT_SECTION = [
        'company' => 'core_dashboard',
        'orders' => 'core_comenzi',
        'suppliers' => 'core_produse',
        'ai' => 'core_automatizare',
        'social' => 'core_comunicare',
        'marketing' => 'core_marketing',
        'shop' => 'core_website',
    ];

    public static function url(string $sectionKey): string
    {
        $key = self::normalizeKey($sectionKey);
        if ($key === null) {
            return '/admin/dashboard';
        }

        return '/admin/dashboard?section=' . rawurlencode($key);
    }

    public static function resolveCurrent(): ?string
    {
        $key = self::normalizeKey((string) ($_GET['section'] ?? ''));

        return $key;
    }

    public static function isValid(string $sectionKey): bool
    {
        return self::normalizeKey($sectionKey) !== null;
    }

    public static function defaultForWorkspace(?string $workspaceId): string
    {
        $ws = AdminWorkspaceCatalog::resolveId((string) $workspaceId) ?? 'company';

        return self::WORKSPACE_DEFAULT_SECTION[$ws] ?? 'core_dashboard';
    }

    /** @return array{section_key: string, label: string, workspace: string, desc: string, icon: string, url: string} */
    public static function meta(string $sectionKey): array
    {
        $key = self::normalizeKey($sectionKey) ?? self::defaultForWorkspace('company');
        $row = self::SECTIONS[$key];

        return [
            'section_key' => $key,
            'label' => $row['label'],
            'workspace' => $row['workspace'],
            'desc' => $row['desc'],
            'icon' => $row['icon'],
            'url' => self::url($key),
        ];
    }

    /** @return array{item_key: string, label: string, url: string, icon: string, sort_order: int} */
    public static function navItem(string $sectionKey): array
    {
        $meta = self::meta($sectionKey);

        return [
            'item_key' => 'dashboard',
            'label' => 'Dashboard',
            'url' => $meta['url'],
            'icon' => 'layout-dashboard',
            'sort_order' => 10,
        ];
    }

    /** @return list<string> */
    public static function sectionKeys(): array
    {
        return array_keys(self::SECTIONS);
    }

    private static function normalizeKey(string $sectionKey): ?string
    {
        $key = trim($sectionKey);
        if ($key === '') {
            return null;
        }

        return isset(self::SECTIONS[$key]) ? $key : null;
    }
}
