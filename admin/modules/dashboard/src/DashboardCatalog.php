<?php
declare(strict_types=1);

namespace Besoiu\Modules\Dashboard;

use Besoiu\Core\Auth\AdminWorkspaceCatalog;

/**
 * Panouri dashboard per zonă de lucru — Company Settings = tablou complet + erori centralizate.
 */
final class DashboardCatalog
{
    /** Tablou complet Company Settings — hero, alerte, taburi, acțiuni rapide. */
    private const COMPANY_PANELS = [
        'hero',
        'red-flags',
        'company-tabs',
        'quick-actions',
    ];

    /** @return list<string> */
    public static function panelsFor(?string $workspaceId): array
    {
        $ws = AdminWorkspaceCatalog::resolveId((string) $workspaceId) ?? 'company';

        if ($ws === 'company') {
            return self::COMPANY_PANELS;
        }

        return match ($ws) {
            'orders' => [
                'hero',
                'red-flags',
                'workspace-deck',
                'furnizori-section',
                'quick-actions',
            ],
            'suppliers' => [
                'hero',
                'red-flags',
                'workspace-deck',
                'quick-actions',
            ],
            'ai' => [
                'hero',
                'red-flags',
                'workspace-deck',
                'quick-actions',
            ],
            'social' => [
                'hero',
                'red-flags',
                'workspace-deck',
                'quick-actions',
            ],
            'marketing' => [
                'hero',
                'red-flags',
                'quick-actions',
            ],
            'shop' => [
                'hero',
                'red-flags',
                'workspace-deck',
                'quick-actions',
            ],
            default => ['hero', 'red-flags'],
        };
    }

    public static function isFullDashboard(?string $workspaceId): bool
    {
        $ws = AdminWorkspaceCatalog::resolveId((string) $workspaceId) ?? 'company';

        return $ws === 'company';
    }

    public static function panelVisible(?string $workspaceId, string $panelId): bool
    {
        if (!in_array($panelId, self::panelsFor($workspaceId), true)) {
            return false;
        }

        if (
            $panelId === 'furnizori-section'
            && (
                !class_exists(\Besoiu\Core\Module\ModuleGate::class)
                || !\Besoiu\Core\Module\ModuleGate::enabled('furnizori')
                || !\Besoiu\Core\Module\OptionalModuleBridge::isInstalled('furnizori')
            )
        ) {
            return false;
        }

        return true;
    }

    /** @return list<string> */
    public static function quickActionsFor(?string $workspaceId): array
    {
        $ws = AdminWorkspaceCatalog::resolveId((string) $workspaceId) ?? 'company';

        if ($ws === 'company') {
            return ['import', 'searchlogs', 'orders', 'bots', 'settings', 'alerts'];
        }

        $actions = match ($ws) {
            'orders' => ['orders', 'order-create', 'clienti', 'facturi', 'suppliers'],
            'suppliers' => ['import', 'product', 'importreview', 'suppliers', 'cron', 'searchlogs', 'search-logs'],
            'ai' => ['bots', 'ai-agent', 'cron', 'scraper'],
            'social' => ['comunicare', 'messages', 'comunicare-leads', 'reply-templates'],
            'marketing' => ['marketplace', 'planner'],
            'shop' => ['website', 'blog', 'vitrina', 'product'],
            default => [],
        };

        return array_values(array_filter(
            $actions,
            static fn (string $id): bool => \Besoiu\Core\Module\ModuleGate::quickActionAllowed($id)
        ));
    }

    public static function quickActionVisible(?string $workspaceId, string $actionId): bool
    {
        return in_array($actionId, self::quickActionsFor($workspaceId), true);
    }

    public static function titleFor(?string $workspaceId): string
    {
        $ws = AdminWorkspaceCatalog::resolveId((string) $workspaceId);
        $meta = $ws !== null ? AdminWorkspaceCatalog::get($ws) : null;
        $label = (string) ($meta['label'] ?? 'Company Settings');

        if (self::isFullDashboard($workspaceId)) {
            return 'Company Settings — Centru de comandă';
        }

        return 'Dashboard — ' . $label;
    }

    public static function subtitleFor(?string $workspaceId): string
    {
        if (self::isFullDashboard($workspaceId)) {
            return 'Tablou executiv — date agregate de la toți utilizatorii admin, plus indicatori magazin (comenzi, căutări, catalog, mesaje).';
        }

        $ws = AdminWorkspaceCatalog::resolveId((string) $workspaceId);
        $meta = $ws !== null ? AdminWorkspaceCatalog::get($ws) : null;

        return (string) ($meta['desc'] ?? 'Indicatori relevanți pentru zona de lucru activă.');
    }

    /** @return array{accent: string, accent2: string, label: string} */
    public static function themeFor(?string $workspaceId): array
    {
        $ws = AdminWorkspaceCatalog::resolveId((string) $workspaceId) ?? 'company';
        $meta = AdminWorkspaceCatalog::get($ws) ?? [];

        return [
            'accent' => (string) ($meta['accent'] ?? '#1abc9c'),
            'accent2' => (string) ($meta['accent2'] ?? '#0d9488'),
            'label' => (string) ($meta['label'] ?? 'Dashboard'),
        ];
    }
}
