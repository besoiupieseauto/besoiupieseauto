<?php
declare(strict_types=1);

use Besoiu\Core\AdminUrl;
use Besoiu\Core\Auth\AdminWorkspaceCatalog;
use Besoiu\Core\Module\ModuleAssets;
use Besoiu\Modules\Nav\NavHubService;

$hub = new NavHubService();
$snap = $hub->snapshot();
$registryPath = (string) ($snap['path'] ?? '');
$css = ModuleAssets::url('nav', 'nav-module.css');
$js = ModuleAssets::url('nav', 'nav-module.js');
$wsCss = AdminUrl::publicAsset('css/admin-workspace.css');

$wsBoot = [];
foreach (AdminWorkspaceCatalog::all() as $id => $ws) {
    $wsBoot[$id] = [
        'label' => (string) ($ws['label'] ?? $id),
        'desc' => (string) ($ws['desc'] ?? ''),
        'accent' => (string) ($ws['accent'] ?? '#64748b'),
        'accent2' => (string) ($ws['accent2'] ?? $ws['accent'] ?? '#475569'),
    ];
}
$wsBoot['navigatie'] = [
    'label' => 'Navigație admin',
    'desc' => 'Editor meniu sidebar și registru JSON.',
    'accent' => '#0ea5e9',
    'accent2' => '#0284c7',
];
$wsBoot['admin_panel'] = [
    'label' => 'Admin Panel (sidebar)',
    'desc' => 'Dashboard, lista produse, vitrină, categorii, calculator — ce vezi sub ADMIN PANEL.',
    'accent' => '#1abc9c',
    'accent2' => '#0d9488',
    'sectionKeys' => ['core_dashboard', 'core_produse', 'mod_calculator'],
];
$wsOrder = ['admin_panel', ...array_merge(array_keys(AdminWorkspaceCatalog::all()), ['navigatie'])];
?>
<link rel="stylesheet" href="<?= htmlspecialchars($wsCss, ENT_QUOTES, 'UTF-8') ?>?v=20260624f">
<link rel="stylesheet" href="<?= htmlspecialchars($css, ENT_QUOTES, 'UTF-8') ?>?v=20260714-zones">
<div class="box p-6 nav-mod" id="nav-mod-app" data-api="/admin/public/api/nav_registry_endpoint.php">
    <div id="nav-mod-toast" class="nav-mod__toast hidden" role="status" aria-live="polite"></div>
    <header class="nav-mod__head">
        <div>
            <h1 class="text-xl font-semibold">Navigație admin — Hub central</h1>
            <p class="text-sm opacity-80 mt-1">
                Gestionezi meniul sidebar pe <strong>zone globale</strong> (ca portalul departamentelor). Deschizi o zonă, vezi tot ce e legat acolo și ce poți ascunde sau șterge.
            </p>
            <p class="text-xs opacity-70 mt-1">
                Fișier: <code><?= htmlspecialchars($registryPath, ENT_QUOTES, 'UTF-8') ?></code>
            </p>
        </div>
        <div class="nav-mod__head-actions">
            <button type="button" class="st-btn st-btn--ghost st-btn--sm" id="nav-mod-sync">↻ Resincronizează module</button>
            <button type="button" class="st-btn st-btn--ghost st-btn--sm" id="nav-mod-reload">Reîncarcă</button>
        </div>
    </header>

    <section class="nav-mod__panel is-active" data-nav-panel="visual">
        <div class="nav-mod__toolbar">
            <button type="button" class="st-btn st-btn--ghost st-btn--sm" id="nav-mod-add-section">+ Secțiune manuală</button>
            <span class="nav-mod__count" id="nav-mod-count">Se încarcă…</span>
        </div>

        <div id="nav-mod-overview" class="nav-mod__overview">
            <div class="besoiu-ws-panel__head nav-mod__panel-head">
                <h1>Alege zona globală</h1>
                <p>Începe cu <strong>Admin Panel (sidebar)</strong> pentru Dashboard, Lista produse, Categorii etc. Celelalte zone grupează restul meniului.</p>
            </div>
            <div class="nav-mod__search-wrap">
                <input type="search" id="nav-mod-search" class="st-input nav-mod__search" placeholder="Caută link în tot meniul (ex: Lista produse, Comenzi…)" autocomplete="off">
                <div id="nav-mod-search-results" class="nav-mod__search-results is-hidden"></div>
            </div>
            <div id="nav-mod-cards" class="besoiu-ws-grid nav-mod__ws-grid"></div>
        </div>

        <div id="nav-mod-detail" class="nav-mod__detail is-hidden">
            <div class="nav-mod__detail-head">
                <button type="button" class="nav-mod__back" id="nav-mod-back">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><polyline points="15 18 9 12 15 6"/></svg>
                    Înapoi la zone
                </button>
                <button type="button" class="st-btn st-btn--primary st-btn--sm" id="nav-mod-add-link-zone">+ Link în această zonă</button>
            </div>
            <div id="nav-mod-detail-hero" class="nav-mod__detail-hero"></div>
            <div id="nav-mod-detail-content"></div>
        </div>
    </section>
</div>
<script>
window.NAV_MOD_BOOT = <?= json_encode([
    'path' => $registryPath,
    'raw' => $snap['raw'] ?? [],
    'tree' => $snap['tree'] ?? [],
    'workspaces' => $wsBoot,
    'workspaceOrder' => $wsOrder,
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
</script>
<script src="<?= htmlspecialchars($js, ENT_QUOTES, 'UTF-8') ?>?v=20260714-admin-panel"></script>
