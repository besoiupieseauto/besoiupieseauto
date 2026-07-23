<?php
declare(strict_types=1);

use Besoiu\Core\AdminUrl;
use Besoiu\Core\Auth\AdminWorkspace;
use Besoiu\Core\Module\ModulesPaths;
use Besoiu\Modules\Dashboard\DashboardCatalog;

$dashboardWorkspace = AdminWorkspace::getCurrent() ?? 'company';
$dashShow = static fn (string $panel): bool => DashboardCatalog::panelVisible($dashboardWorkspace, $panel);
$dashAction = static fn (string $action): bool => DashboardCatalog::quickActionVisible($dashboardWorkspace, $action);
$dashFull = DashboardCatalog::isFullDashboard($dashboardWorkspace);
$dashTitle = DashboardCatalog::titleFor($dashboardWorkspace);
$dashSubtitle = DashboardCatalog::subtitleFor($dashboardWorkspace);

/** Snapshot JSON pre-colectat — randare instant fără așteptare API. */
$dashboardSnapshotEnvelope = null;
$dashboardSnapshotPath = ModulesPaths::adminRoot() . '/storage/cache/dashboard_snapshot.json';
if (is_file($dashboardSnapshotPath)) {
    $rawSnapshot = json_decode((string) file_get_contents($dashboardSnapshotPath), true);
    if (is_array($rawSnapshot) && isset($rawSnapshot['data']) && is_array($rawSnapshot['data'])) {
        $dashboardSnapshotEnvelope = $rawSnapshot;
    }
}

?>
<div>
    <div id="dashboard-toast" class="hidden fixed right-5 top-5 z-50 rounded-md border bg-background px-4 py-3 text-sm shadow"></div>

    <div class="besoiu-dashboard grid grid-cols-12 gap-4" data-besoiu-dash-workspace="<?= htmlspecialchars($dashboardWorkspace, ENT_QUOTES, 'UTF-8') ?>" data-besoiu-dash-full="<?= $dashFull ? '1' : '0' ?>">

        <?php if ($dashShow('hero')): ?>
        <div class="col-span-12" data-besoiu-dash-panel="hero">
            <?php if ($dashFull): ?>
            <div class="bcd-page-hero besoiu-dash-hero">
                <div class="bcd-page-hero__main">
                    <span class="bcd-scope-badge"><i data-lucide="building-2" class="size-3.5"></i> Agregat organizație · toți utilizatorii</span>
                    <h1><?= htmlspecialchars($dashTitle, ENT_QUOTES, 'UTF-8') ?></h1>
                    <p class="bcd-page-hero__subtitle"><?= htmlspecialchars($dashSubtitle, ENT_QUOTES, 'UTF-8') ?></p>
                    <div class="bcd-status-row">
                        <span id="dashboard-sync-label" class="bcd-status-pill bcd-status-pill--live"><?=
                            $dashboardSnapshotEnvelope
                                ? 'Live · ' . htmlspecialchars((string) ($dashboardSnapshotEnvelope['generated_at'] ?? ''), ENT_QUOTES, 'UTF-8')
                                : 'Se sincronizează...'
                        ?></span>
                        <span id="bcd-hero-team-pill" class="bcd-status-pill bcd-status-pill--team">— utilizatori</span>
                        <span id="bcd-hero-scope-pill" class="bcd-status-pill bcd-status-pill--muted">Magazin + admin</span>
                    </div>
                </div>
                <div class="besoiu-dash-hero__actions">
                    <a href="/admin/users" class="besoiu-btn-secondary inline-flex items-center gap-2"><i data-lucide="users" class="size-4"></i> Echipă</a>
                    <a href="/admin/dashboard?section=core_produse" class="besoiu-btn-secondary inline-flex items-center gap-2">Search Logs</a>
                    <a href="/admin/alerts" class="besoiu-btn-secondary inline-flex items-center gap-2">Alerte</a>
                    <button type="button" id="dashboard-refresh" class="besoiu-btn-primary inline-flex items-center gap-2">
                        <i data-lucide="refresh-cw" class="size-4 stroke-[1.5]"></i>
                        Reîncarcă
                    </button>
                </div>
            </div>
            <?php else: ?>
            <div class="besoiu-dash-hero">
                <div>
                    <span id="bws-user-badge" class="bws-user-badge hidden"></span>
                    <h1><?= htmlspecialchars($dashTitle, ENT_QUOTES, 'UTF-8') ?></h1>
                    <p class="besoiu-dash-hero__meta mt-2 opacity-80"><?= htmlspecialchars($dashSubtitle, ENT_QUOTES, 'UTF-8') ?></p>
                    <div id="dashboard-sync-label" class="besoiu-dash-hero__meta mt-3"><?=
                        $dashboardSnapshotEnvelope
                            ? 'Online · ' . htmlspecialchars((string) ($dashboardSnapshotEnvelope['generated_at'] ?? ''), ENT_QUOTES, 'UTF-8')
                            : 'Se sincronizează...'
                    ?></div>
                </div>
                <div class="besoiu-dash-hero__actions">
                    <?php if ($dashShow('search-analytics')): ?>
                    <a href="/admin/dashboard?section=core_produse" class="besoiu-btn-secondary inline-flex items-center gap-2">Search Logs</a>
                    <?php endif; ?>
                    <button type="button" id="dashboard-refresh" class="besoiu-btn-primary inline-flex items-center gap-2">
                        <i data-lucide="refresh-cw" class="size-4 stroke-[1.5] [--color:currentColor] stroke-(--color) fill-(--color)/25"></i>
                        Reîncarcă
                    </button>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($dashShow('marketing-pulse')): ?>
        <?php require __DIR__ . '/_partials/dashboard_marketing_pulse.php'; ?>
        <?php endif; ?>

        <?php if ($dashShow('workspace-deck')): ?>
        <?php require __DIR__ . '/_partials/dashboard_workspace_deck.php'; ?>
        <?php endif; ?>

        <?php if ($dashShow('company-tabs')): ?>
        <?php require __DIR__ . '/_partials/dashboard_company_tabs.php'; ?>
        <?php endif; ?>

        <?php if ($dashShow('red-flags')): ?>
        <div class="col-span-12" data-besoiu-dash-panel="red-flags">
            <div class="besoiu-subpanel besoiu-subpanel--card besoiu-subpanel--red-flags" id="red-flag-center-panel">
                <div class="besoiu-subpanel__head">
                    <h3>Red Flag Center</h3>
                    <span id="red-flags-count" class="besoiu-subpanel__tag besoiu-subpanel__tag--alert">0</span>
                </div>
                <div class="besoiu-subpanel__body">
                    <p class="besoiu-red-flag__intro">Alerte critice la conectare — import, TecDoc, joburi și integrări.</p>
                    <div id="red-flags-list" class="space-y-3">
                        <div class="rounded-xl border-2 border-dashed p-4 text-sm">Se încarcă...</div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($dashShow('tecdoc-ip')): ?>
        <div class="col-span-12" data-besoiu-dash-panel="tecdoc-ip">
            <div class="besoiu-subpanel besoiu-subpanel--card" id="tecdoc-ip-widget-panel">
                <div class="besoiu-subpanel__head">
                    <h3>TecDoc — Validitate IP / API</h3>
                    <span id="tecdoc-ip-badge" class="besoiu-subpanel__tag">Se verifică...</span>
                </div>
                <div class="besoiu-subpanel__body">
                    <div id="tecdoc-ip-status" class="besoiu-tecdoc-ip-widget">
                        <div class="besoiu-tecdoc-ip-widget__indicator" id="tecdoc-ip-indicator">
                            <span class="besoiu-tecdoc-ip-widget__dot" id="tecdoc-ip-dot" aria-hidden="true"></span>
                            <span id="tecdoc-ip-label">Verificare automată la încărcare...</span>
                        </div>
                        <p class="besoiu-tecdoc-ip-widget__ip">IP server: <strong id="tecdoc-ip-value">—</strong></p>
                        <p class="besoiu-tecdoc-ip-widget__detail" id="tecdoc-ip-detail">Se contactează API TecDoc...</p>
                        <p class="besoiu-tecdoc-ip-widget__alert hidden" id="tecdoc-ip-alert"></p>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php
        $dashKpiVisible = $dashShow('kpi-searches') || $dashShow('kpi-not-found') || $dashShow('kpi-orders') || $dashShow('kpi-revenue');
        if ($dashKpiVisible):
        ?>
        <div class="col-span-12" data-besoiu-dash-panel="kpi-main">
            <div class="admin-panel admin-panel--flush" id="dashboard-kpi-panel">
                <div class="admin-panel__head">
                    <h2 class="m-0 text-base font-medium">Indicatori principali</h2>
                </div>
                <div class="besoiu-kpi-grid-full">
                    <?php if ($dashShow('kpi-searches')): ?>
                    <div class="besoiu-kpi besoiu-kpi--search" data-besoiu-dash-panel="kpi-searches">
                        <div class="flex items-center">
                            <div class="besoiu-kpi__icon-wrap">
                                <i data-lucide="search" class="stroke-[1.5] text-primary [--color:currentColor] stroke-(--color) fill-(--color)/25"></i>
                            </div>
                            <div id="kpi-searches-badge" class="besoiu-kpi__badge ms-auto">azi</div>
                        </div>
                        <div id="kpi-searches-today" class="besoiu-kpi__value">—</div>
                        <div class="besoiu-kpi__label">Căutări site azi</div>
                    </div>
                    <?php endif; ?>
                    <?php if ($dashShow('kpi-not-found')): ?>
                    <div class="besoiu-kpi besoiu-kpi--warn besoiu-kpi--clickable" id="kpi-not-found-widget" data-besoiu-dash-panel="kpi-not-found" role="button" tabindex="0" aria-haspopup="dialog" aria-controls="missing-searches-modal" title="Click pentru lista codurilor negăsite">
                        <div class="flex items-center">
                            <div class="besoiu-kpi__icon-wrap">
                                <i data-lucide="search-x" class="stroke-[1.5] text-warning [--color:currentColor] stroke-(--color) fill-(--color)/25"></i>
                            </div>
                            <div id="kpi-not-found-badge" class="besoiu-kpi__badge ms-auto">negăsite</div>
                        </div>
                        <div id="kpi-not-found-today" class="besoiu-kpi__value">—</div>
                        <div class="besoiu-kpi__label">Căutări negăsite · click listă coduri</div>
                        <div id="kpi-not-found-codes-hint" class="besoiu-kpi__hint">— coduri unice lipsă din stoc</div>
                    </div>
                    <?php endif; ?>
                    <?php if ($dashShow('kpi-orders')): ?>
                    <div class="besoiu-kpi besoiu-kpi--success" data-besoiu-dash-panel="kpi-orders">
                        <div class="flex items-center">
                            <div class="besoiu-kpi__icon-wrap">
                                <i data-lucide="shopping-cart" class="stroke-[1.5] text-success [--color:currentColor] stroke-(--color) fill-(--color)/25"></i>
                            </div>
                            <div id="kpi-orders-badge" class="besoiu-kpi__badge ms-auto">noi</div>
                        </div>
                        <div id="kpi-orders-today" class="besoiu-kpi__value">—</div>
                        <div class="besoiu-kpi__label">Comenzi noi azi</div>
                    </div>
                    <?php endif; ?>
                    <?php if ($dashShow('kpi-revenue')): ?>
                    <div class="besoiu-kpi besoiu-kpi--revenue" data-besoiu-dash-panel="kpi-revenue">
                        <div class="flex items-center">
                            <div class="besoiu-kpi__icon-wrap">
                                <i data-lucide="banknote" class="stroke-[1.5] [--color:currentColor] stroke-(--color) fill-(--color)/25"></i>
                            </div>
                            <div class="besoiu-kpi__badge ms-auto">RON</div>
                        </div>
                        <div id="kpi-revenue-today" class="besoiu-kpi__value">—</div>
                        <div class="besoiu-kpi__label">Vânzări azi</div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($dashShow('search-analytics')): ?>
        <div class="col-span-12" data-besoiu-dash-panel="search-analytics">
                <div class="besoiu-subpanel__head">
                    <h3>Search Log — ce caută vizitatorii</h3>
                    <span id="search-analytics-badge" class="besoiu-subpanel__tag">Analitic · 14 zile</span>
                </div>
                <div class="besoiu-subpanel__body">
                    <p class="besoiu-search-analytics__intro">Dashboard analitic pentru decizii stoc și import — total căutări, găsite vs negăsite, top coduri OEM și trend zilnic.</p>

                    <div class="besoiu-search-analytics-kpi">
                        <div class="besoiu-search-analytics-kpi__item">
                            <div class="besoiu-search-analytics-kpi__label">Total căutări</div>
                            <div id="search-analytics-total" class="besoiu-search-analytics-kpi__value">—</div>
                        </div>
                        <div class="besoiu-search-analytics-kpi__item besoiu-search-analytics-kpi__item--found">
                            <div class="besoiu-search-analytics-kpi__label">Găsite</div>
                            <div id="search-analytics-found" class="besoiu-search-analytics-kpi__value">—</div>
                        </div>
                        <div role="button" tabindex="0" class="besoiu-search-analytics-kpi__item besoiu-search-analytics-kpi__item--missing besoiu-search-analytics-kpi__item--clickable" id="search-analytics-not-found-widget" aria-haspopup="dialog" aria-controls="missing-searches-modal" title="Click pentru lista codurilor căutate fără rezultat">
                            <div class="besoiu-search-analytics-kpi__label">Negăsite · click listă</div>
                            <div id="search-analytics-not-found" class="besoiu-search-analytics-kpi__value">—</div>
                        </div>
                        <div class="besoiu-search-analytics-kpi__item besoiu-search-analytics-kpi__item--rate">
                            <div class="besoiu-search-analytics-kpi__label">Rată succes</div>
                            <div id="search-analytics-rate" class="besoiu-search-analytics-kpi__value">—</div>
                        </div>
                    </div>

                    <div class="besoiu-search-analytics-split">
                        <div class="besoiu-search-analytics-block">
                            <div class="besoiu-search-analytics-block__head">
                                <h4>Trend pe zile</h4>
                                <span class="besoiu-search-analytics-legend">
                                    <span class="besoiu-search-analytics-legend__item besoiu-search-analytics-legend__item--found">Găsite</span>
                                    <span class="besoiu-search-analytics-legend__item besoiu-search-analytics-legend__item--missing">Negăsite</span>
                                </span>
                            </div>
                            <div id="search-analytics-trend" class="besoiu-search-trend">
                                <div class="besoiu-search-analytics__empty">Se încarcă trendul...</div>
                            </div>
                        </div>

                        <div class="besoiu-search-analytics-block">
                            <div class="besoiu-search-analytics-block__head">
                                <h4>Top coduri OEM căutate</h4>
                                <a href="/admin/dashboard?section=core_produse" class="besoiu-search-analytics-link">Jurnal complet</a>
                            </div>
                            <div id="search-analytics-top-oem" class="besoiu-search-top-oem">
                                <div class="besoiu-search-analytics__empty">Se încarcă...</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($dashShow('system-status') || $dashShow('orders-summary')): ?>
        <div class="col-span-12" data-besoiu-dash-panel="status-split">
            <div class="admin-panel admin-panel--flush" id="dashboard-status-panel">
                <div class="besoiu-dashboard-split<?= ($dashShow('system-status') && $dashShow('orders-summary')) ? '' : ' besoiu-dashboard-split--single' ?>">
                    <?php if ($dashShow('system-status')): ?>
                    <div class="besoiu-subpanel besoiu-subpanel--flush" data-besoiu-dash-panel="system-status">
                        <div class="besoiu-subpanel__head">
                            <h3>Status Sistem</h3>
                            <span id="system-status-label" class="besoiu-subpanel__tag">—</span>
                        </div>
                        <div class="besoiu-subpanel__body besoiu-subpanel__body--flush">
                            <div id="system-status-grid" class="besoiu-status-grid"></div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if ($dashShow('orders-summary')): ?>
                    <div class="besoiu-subpanel besoiu-subpanel--flush" data-besoiu-dash-panel="orders-summary">
                        <div class="besoiu-subpanel__head">
                            <h3>Rezumat comenzi</h3>
                        </div>
                        <div class="besoiu-subpanel__body space-y-3 text-sm">
                            <div class="flex justify-between">
                                <span class="opacity-70">Total comenzi</span>
                                <span id="summary-orders-total" class="font-medium">—</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="opacity-70">Comenzi noi (nepreluate)</span>
                                <span id="summary-orders-new" class="font-medium text-warning">—</span>
                            </div>
                            <?php if ($dashFull): ?>
                            <div class="flex justify-between">
                                <span class="opacity-70">Căutări totale (jurnal)</span>
                                <span id="summary-searches-total" class="font-medium">—</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="opacity-70">Rată găsite</span>
                                <span id="summary-search-rate" class="font-medium text-success">—</span>
                            </div>
                            <?php else: ?>
                            <span id="summary-searches-total" class="hidden" aria-hidden="true">—</span>
                            <span id="summary-search-rate" class="hidden" aria-hidden="true">—</span>
                            <?php endif; ?>
                            <a href="/admin/orders" class="besoiu-btn-secondary mt-4 inline-block w-full text-center">Vezi toate comenzile</a>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($dashShow('quick-actions')): ?>
        <div class="col-span-12 <?= $dashFull ? 'xl:col-span-6' : '' ?>" data-besoiu-dash-panel="quick-actions">
            <div class="besoiu-subpanel besoiu-subpanel--card">
                <div class="besoiu-subpanel__head">
                    <h3>Acțiuni Rapide</h3>
                </div>
                <div class="besoiu-subpanel__body bws-quick-actions">
                    <?php if ($dashAction('searchlogs')): ?>
                    <a href="/admin/dashboard?section=core_produse" class="besoiu-tile besoiu-tile--search" data-besoiu-dash-action="searchlogs">
                        <i data-lucide="search" class="stroke-[1.5]"></i>
                        <div class="besoiu-tile__title">Search Logs</div>
                        <div class="besoiu-tile__desc">VIN/OEM negăsite pe site</div>
                    </a>
                    <?php endif; ?>
                    <?php if ($dashAction('orders')): ?>
                    <a href="/admin/orders" class="besoiu-tile besoiu-tile--orders" data-besoiu-dash-action="orders">
                        <i data-lucide="shopping-cart" class="stroke-[1.5]"></i>
                        <div class="besoiu-tile__title">Comenzi site</div>
                        <div class="besoiu-tile__desc">Checkout și livrări</div>
                    </a>
                    <?php endif; ?>
                    <?php if ($dashAction('order-create')): ?>
                    <a href="/admin/order-create" class="besoiu-tile besoiu-tile--orders" data-besoiu-dash-action="order-create">
                        <i data-lucide="plus-circle" class="stroke-[1.5]"></i>
                        <div class="besoiu-tile__title">Comandă nouă</div>
                        <div class="besoiu-tile__desc">Creare manuală</div>
                    </a>
                    <?php endif; ?>
                    <?php if ($dashAction('clienti')): ?>
                    <a href="/admin/clienti" class="besoiu-tile besoiu-tile--orders" data-besoiu-dash-action="clienti">
                        <i data-lucide="users" class="stroke-[1.5]"></i>
                        <div class="besoiu-tile__title">Clienți</div>
                        <div class="besoiu-tile__desc">Listă clienți magazin</div>
                    </a>
                    <?php endif; ?>
                    <?php if ($dashAction('facturi')): ?>
                    <a href="/admin/facturi" class="besoiu-tile besoiu-tile--orders" data-besoiu-dash-action="facturi">
                        <i data-lucide="file-text" class="stroke-[1.5]"></i>
                        <div class="besoiu-tile__title">Facturi</div>
                        <div class="besoiu-tile__desc">Documente comenzi</div>
                    </a>
                    <?php endif; ?>
                    <?php if ($dashAction('product')): ?>
                    <a href="/admin/product" class="besoiu-tile besoiu-tile--import" data-besoiu-dash-action="product">
                        <i data-lucide="package" class="stroke-[1.5]"></i>
                        <div class="besoiu-tile__title">Lista produse</div>
                        <div class="besoiu-tile__desc">Catalog magazin</div>
                    </a>
                    <?php endif; ?>
                    <?php if ($dashAction('suppliers')): ?>
                    <a href="/admin/suppliers" class="besoiu-tile besoiu-tile--import" data-besoiu-dash-action="suppliers">
                        <i data-lucide="truck" class="stroke-[1.5]"></i>
                        <div class="besoiu-tile__title">Furnizori</div>
                        <div class="besoiu-tile__desc">Comparare și feed-uri</div>
                    </a>
                    <?php endif; ?>
                    <?php if ($dashAction('bots')): ?>
                    <a href="/admin/bots" class="besoiu-tile besoiu-tile--bots" data-besoiu-dash-action="bots">
                        <i data-lucide="bot" class="stroke-[1.5]"></i>
                        <div class="besoiu-tile__title">Boți</div>
                        <div class="besoiu-tile__desc">WhatsApp și integrări</div>
                    </a>
                    <?php endif; ?>
                    <?php if ($dashAction('ai-agent')): ?>
                    <a href="/admin/ai-agent" class="besoiu-tile besoiu-tile--bots" data-besoiu-dash-action="ai-agent">
                        <i data-lucide="brain" class="stroke-[1.5]"></i>
                        <div class="besoiu-tile__title">AI Agent</div>
                        <div class="besoiu-tile__desc">Context și automatizări</div>
                    </a>
                    <?php endif; ?>
                    <?php if ($dashAction('scraper')): ?>
                    <a href="/admin/scraper" class="besoiu-tile besoiu-tile--bots" data-besoiu-dash-action="scraper">
                        <i data-lucide="globe" class="stroke-[1.5]"></i>
                        <div class="besoiu-tile__title">Scraper</div>
                        <div class="besoiu-tile__desc">Colectare date externe</div>
                    </a>
                    <?php endif; ?>
                    <?php if ($dashAction('comunicare')): ?>
                    <a href="/admin/comunicare" class="besoiu-tile besoiu-tile--search" data-besoiu-dash-action="comunicare">
                        <i data-lucide="messages-square" class="stroke-[1.5]"></i>
                        <div class="besoiu-tile__title">Comunicare</div>
                        <div class="besoiu-tile__desc">Hub mesagerie</div>
                    </a>
                    <?php endif; ?>
                    <?php if ($dashAction('messages')): ?>
                    <a href="/admin/messages" class="besoiu-tile besoiu-tile--search" data-besoiu-dash-action="messages">
                        <i data-lucide="message-circle" class="stroke-[1.5]"></i>
                        <div class="besoiu-tile__title">Mesaje</div>
                        <div class="besoiu-tile__desc">Inbox unificat</div>
                    </a>
                    <?php endif; ?>
                    <?php if ($dashAction('comunicare-leads')): ?>
                    <a href="/admin/comunicare-leads" class="besoiu-tile besoiu-tile--search" data-besoiu-dash-action="comunicare-leads">
                        <i data-lucide="user-plus" class="stroke-[1.5]"></i>
                        <div class="besoiu-tile__title">Lead-uri</div>
                        <div class="besoiu-tile__desc">Contacte noi</div>
                    </a>
                    <?php endif; ?>
                    <?php if ($dashAction('reply-templates')): ?>
                    <a href="/admin/reply-templates" class="besoiu-tile besoiu-tile--search" data-besoiu-dash-action="reply-templates">
                        <i data-lucide="file-type" class="stroke-[1.5]"></i>
                        <div class="besoiu-tile__title">Template-uri</div>
                        <div class="besoiu-tile__desc">Răspunsuri rapide</div>
                    </a>
                    <?php endif; ?>
                    <?php if ($dashAction('marketplace')): ?>
                    <a href="/admin/marketplace" class="besoiu-tile besoiu-tile--search" data-besoiu-dash-action="marketplace">
                        <i data-lucide="store" class="stroke-[1.5]"></i>
                        <div class="besoiu-tile__title">Marketplace</div>
                        <div class="besoiu-tile__desc">PieseAuto.ro și canale</div>
                    </a>
                    <?php endif; ?>
                    <?php if ($dashAction('planner')): ?>
                    <a href="/admin/planner" class="besoiu-tile besoiu-tile--search" data-besoiu-dash-action="planner">
                        <i data-lucide="target" class="stroke-[1.5]"></i>
                        <div class="besoiu-tile__title">Planner</div>
                        <div class="besoiu-tile__desc">Plan eficiență financiară</div>
                    </a>
                    <?php endif; ?>
                    <?php if ($dashAction('marketing') || $dashAction('marketing-indicators')): ?>
                    <a href="/admin/marketing" class="besoiu-tile besoiu-tile--search" data-besoiu-dash-action="marketing">
                        <i data-lucide="megaphone" class="stroke-[1.5]"></i>
                        <div class="besoiu-tile__title">Marketing Hub</div>
                        <div class="besoiu-tile__desc">Rezultat → Probleme → Plan</div>
                    </a>
                    <?php endif; ?>
                    <?php if ($dashAction('marketing-actions')): ?>
                    <a href="/admin/marketing-actions" class="besoiu-tile besoiu-tile--search" data-besoiu-dash-action="marketing-actions">
                        <i data-lucide="list-checks" class="stroke-[1.5]"></i>
                        <div class="besoiu-tile__title">Plan de acțiune</div>
                        <div class="besoiu-tile__desc">Pași marketing</div>
                    </a>
                    <?php endif; ?>
                    <?php if ($dashAction('marketing-content')): ?>
                    <a href="/admin/marketing-content" class="besoiu-tile besoiu-tile--search" data-besoiu-dash-action="marketing-content">
                        <i data-lucide="pen-line" class="stroke-[1.5]"></i>
                        <div class="besoiu-tile__title">Conținut</div>
                        <div class="besoiu-tile__desc">Postări &amp; drafturi</div>
                    </a>
                    <?php endif; ?>
                    <?php if ($dashAction('reports')): ?>
                    <a href="/admin/reports" class="besoiu-tile besoiu-tile--search" data-besoiu-dash-action="reports">
                        <i data-lucide="bar-chart-3" class="stroke-[1.5]"></i>
                        <div class="besoiu-tile__title">Rapoarte</div>
                        <div class="besoiu-tile__desc">Statistici promovare</div>
                    </a>
                    <?php endif; ?>
                    <?php if ($dashAction('website')): ?>
                    <a href="/admin/website" class="besoiu-tile besoiu-tile--import" data-besoiu-dash-action="website">
                        <i data-lucide="layout-template" class="stroke-[1.5]"></i>
                        <div class="besoiu-tile__title">Website CMS</div>
                        <div class="besoiu-tile__desc">Pagini site public</div>
                    </a>
                    <?php endif; ?>
                    <?php if ($dashAction('blog')): ?>
                    <a href="/admin/blog" class="besoiu-tile besoiu-tile--import" data-besoiu-dash-action="blog">
                        <i data-lucide="newspaper" class="stroke-[1.5]"></i>
                        <div class="besoiu-tile__title">Blog</div>
                        <div class="besoiu-tile__desc">Articole și conținut</div>
                    </a>
                    <?php endif; ?>
                    <?php if ($dashAction('vitrina')): ?>
                    <a href="/admin/vitrina" class="besoiu-tile besoiu-tile--import" data-besoiu-dash-action="vitrina">
                        <i data-lucide="layout-grid" class="stroke-[1.5]"></i>
                        <div class="besoiu-tile__title">Vitrina</div>
                        <div class="besoiu-tile__desc">Produse homepage</div>
                    </a>
                    <?php endif; ?>
                    <?php if ($dashAction('settings')): ?>
                    <a href="/admin/settings" class="besoiu-tile besoiu-tile--bots" data-besoiu-dash-action="settings">
                        <i data-lucide="settings" class="stroke-[1.5]"></i>
                        <div class="besoiu-tile__title">Setări</div>
                        <div class="besoiu-tile__desc">Companie și sistem</div>
                    </a>
                    <?php endif; ?>
                    <?php if ($dashAction('alerts')): ?>
                    <a href="/admin/alerts" class="besoiu-tile besoiu-tile--bots" data-besoiu-dash-action="alerts">
                        <i data-lucide="bell-ring" class="stroke-[1.5]"></i>
                        <div class="besoiu-tile__title">Alerte</div>
                        <div class="besoiu-tile__desc">Monitorizare critică</div>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($dashShow('activity')): ?>
        <div class="col-span-12 <?= $dashShow('top-missing') ? 'xl:col-span-8' : 'xl:col-span-12' ?>" data-besoiu-dash-panel="activity">
            <div class="besoiu-subpanel besoiu-subpanel--card overflow-hidden">
                <div class="besoiu-subpanel__head">
                    <h3>Activitate Recentă</h3>
                    <span class="besoiu-subpanel__tag">Comenzi + mesaje</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                        <tr class="border-b text-xs uppercase opacity-70">
                            <th class="px-5 py-3 text-left">Data</th>
                            <th class="px-5 py-3 text-left">Titlu</th>
                            <th class="px-5 py-3 text-left">Canal</th>
                            <th class="px-5 py-3 text-center">Status</th>
                            <th class="px-5 py-3 text-right">Link</th>
                        </tr>
                        </thead>
                        <tbody id="activity-table">
                        <tr><td colspan="5" class="px-5 py-6 text-center opacity-70">Se încarcă...</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="px-5 pb-5">
                    <a href="/admin/orders" class="besoiu-btn-secondary mt-4 inline-block">Vezi jurnalul complet</a>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($dashShow('top-missing')): ?>
        <div class="col-span-12 xl:col-span-4" data-besoiu-dash-panel="top-missing">
            <div class="besoiu-subpanel besoiu-subpanel--card">
                <div class="besoiu-subpanel__head">
                    <h3>Căutări fără rezultat</h3>
                </div>
                <div class="besoiu-subpanel__body">
                    <div id="top-missing-list" class="space-y-4">
                        <div class="rounded-xl border-2 border-dashed p-4 text-sm">Se încarcă...</div>
                    </div>
                    <a href="/admin/dashboard?section=core_produse" class="besoiu-btn-secondary mt-4 inline-block">Vezi jurnal complet</a>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($dashShow('prod-stock')): ?>
        <div class="col-span-12" data-besoiu-dash-panel="prod-stock">
            <div class="admin-panel admin-panel--flush">
                <div class="admin-panel__head">
                    <h2>Stoc produse</h2>
                </div>
                <div class="besoiu-kpi-grid-full">
                    <div class="besoiu-kpi besoiu-kpi--search">
                        <div class="besoiu-kpi__icon-wrap">
                            <i data-lucide="package" class="stroke-[1.5]"></i>
                        </div>
                        <div id="prod-total" class="besoiu-kpi__value">—</div>
                        <div class="besoiu-kpi__label">Produse totale</div>
                    </div>
                    <div class="besoiu-kpi besoiu-kpi--success">
                        <div class="besoiu-kpi__icon-wrap">
                            <i data-lucide="check-circle" class="stroke-[1.5]"></i>
                        </div>
                        <div id="prod-active" class="besoiu-kpi__value">—</div>
                        <div class="besoiu-kpi__label">Produse active</div>
                    </div>
                    <div class="besoiu-kpi besoiu-kpi--warn">
                        <div class="besoiu-kpi__icon-wrap">
                            <i data-lucide="image-off" class="stroke-[1.5]"></i>
                        </div>
                        <div id="prod-no-image" class="besoiu-kpi__value">—</div>
                        <div class="besoiu-kpi__label">Fără imagine</div>
                    </div>
                    <div class="besoiu-kpi besoiu-kpi--danger">
                        <div class="besoiu-kpi__icon-wrap">
                            <i data-lucide="barcode" class="stroke-[1.5]"></i>
                        </div>
                        <div id="prod-no-oem" class="besoiu-kpi__value">—</div>
                        <div class="besoiu-kpi__label">Fără cod OEM</div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>

<div id="missing-searches-modal" class="besoiu-modal-backdrop hidden" aria-hidden="true" role="dialog" aria-labelledby="missing-searches-modal-title">
    <div class="besoiu-modal besoiu-missing-modal" role="document">
        <div class="besoiu-modal__head">
            <h3 id="missing-searches-modal-title">Căutări negăsite — coduri absente din stoc</h3>
            <button type="button" id="missing-searches-modal-close" class="besoiu-missing-modal__close" aria-label="Închide">×</button>
        </div>
        <div class="besoiu-modal__body">
            <p id="missing-searches-modal-intro" class="besoiu-missing-modal__intro">Se încarcă lista codurilor căutate dar negăsite în stoc…</p>
            <div id="missing-searches-modal-list" class="besoiu-missing-modal__list">
                <div class="rounded-xl border-2 border-dashed p-4 text-sm opacity-70">Se încarcă…</div>
            </div>
            <div class="besoiu-missing-modal__actions">
                <a href="/admin/dashboard?section=core_produse" class="besoiu-btn-secondary inline-flex items-center">Jurnal complet Search Logs</a>
                <button type="button" id="missing-searches-modal-close-bottom" class="besoiu-btn-primary">Închide</button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';

    const ENDPOINT = '/admin/api/dashboard_endpoint.php';
    const SEARCH_LOGS_ENDPOINT = '/admin/api/search_logs_endpoint.php';
    const INITIAL_SNAPSHOT = <?= json_encode($dashboardSnapshotEnvelope, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    let cachedMissingSearch = null;

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, (char) => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
        }[char]));
    }

    function formatMoney(value) {
        const num = Number(value || 0);
        return num.toLocaleString('ro-RO', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' RON';
    }

    function formatNumber(value) {
        return Number(value || 0).toLocaleString('ro-RO');
    }

    function formatDateTime(value) {
        if (!value) return '—';
        const date = new Date(String(value).replace(' ', 'T'));
        if (Number.isNaN(date.getTime())) return String(value);
        return date.toLocaleString('ro-RO', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
    }

    function setText(id, value) {
        const node = document.getElementById(id);
        if (node) {
            node.textContent = value;
        }
    }

    function statusBadge(status, type) {
        const normalized = String(status || '').toLowerCase();
        let cls = 'bg-foreground/10';
        if (normalized === 'noua' || normalized === 'new' || normalized === 'pending') cls = 'bg-warning/20 text-warning';
        if (normalized === 'livrat' || normalized === 'done' || normalized === 'handled' || normalized === 'sent') cls = 'bg-success/20 text-success';
        if (normalized === 'failed' || normalized === 'needs_human') cls = 'bg-danger/20 text-danger';
        const label = status || (type === 'order' ? 'comandă' : 'mesaj');
        return `<span class="rounded-full px-3 py-1 text-xs ${cls}">${escapeHtml(label)}</span>`;
    }

    function showToast(message, isError) {
        const toast = document.getElementById('dashboard-toast');
        if (!toast) return;
        toast.textContent = message;
        toast.classList.remove('hidden');
        toast.classList.toggle('text-danger', Boolean(isError));
        setTimeout(() => toast.classList.add('hidden'), 3500);
    }

    async function loadDashboard(forceRefresh, silent) {
        const response = await fetch(ENDPOINT, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ type_product: 'overview', refresh: forceRefresh ? 1 : 0 })
        });
        const result = await response.json();
        if (!response.ok || !result.success) {
            throw new Error(result.message || 'Nu s-a putut încărca dashboard-ul.');
        }
        renderDashboard(result.data || {});
        if (silent && result.source === 'live') {
            const syncLabel = document.getElementById('dashboard-sync-label');
            if (syncLabel) {
                syncLabel.textContent = 'Live · actualizat ' + formatDateTime(result.data?.generated_at);
                syncLabel.className = 'besoiu-dash-hero__meta mt-3';
            }
        }
    }

    function snapshotIsStale(envelope) {
        if (!envelope) return true;
        const expiresAt = Number(envelope.expires_at || 0);
        if (expiresAt > Date.now() / 1000) return false;
        return true;
    }

    function bootstrapDashboard() {
        const hasSnapshot = INITIAL_SNAPSHOT && INITIAL_SNAPSHOT.data;
        if (hasSnapshot) {
            renderDashboard(INITIAL_SNAPSHOT.data);
        }

        const forceLive = snapshotNeedsTeamRefresh(hasSnapshot ? INITIAL_SNAPSHOT.data : null);
        const needsLoad = forceLive || !hasSnapshot || snapshotIsStale(INITIAL_SNAPSHOT);

        if (needsLoad) {
            const run = () => loadDashboard(forceLive, !hasSnapshot).catch((error) => {
                showToast(error.message || 'Nu s-a putut încărca dashboard-ul.', true);
            });
            if (isCompanyDashboard()) {
                run();
            } else if (window.BpaAsync && typeof window.BpaAsync.defer === 'function') {
                window.BpaAsync.defer(run);
            } else {
                setTimeout(run, 50);
            }
        }
    }

    function renderDashboard(data) {
        const orders = data.orders || {};
        const products = data.products || {};
        const search = data.search_logs || {};
        const health = data.health || {};
        const importInfo = data.import || {};

        setText('kpi-searches-today', formatNumber(search.today));
        const missingCodesCount = Number(search.missing_codes_count ?? 0);
        setText('kpi-not-found-today', formatNumber(missingCodesCount));
        const codesHint = document.getElementById('kpi-not-found-codes-hint');
        if (codesHint) {
            codesHint.textContent = formatNumber(search.not_found) + ' căutări în jurnal · '
                + formatNumber(search.today_not_found) + ' azi';
        }
        setText('kpi-orders-today', formatNumber(orders.today_new));
        setText('kpi-revenue-today', formatMoney(orders.today_revenue));

        setText('summary-orders-total', formatNumber(orders.total));
        setText('summary-orders-new', formatNumber(orders.new_orders));
        setText('summary-searches-total', formatNumber(search.total));

        const foundRate = search.total > 0
            ? Math.round(((search.total - search.not_found) / search.total) * 100)
            : 0;
        setText('summary-search-rate', foundRate + '%');

        setText('prod-total', formatNumber(products.total));
        setText('prod-active', formatNumber(products.active));
        setText('prod-no-image', formatNumber(products.no_image));
        setText('prod-no-oem', formatNumber(products.no_oem));

        const syncLabel = document.getElementById('dashboard-sync-label');
        if (syncLabel) {
            const isCompanyHero = Boolean(document.querySelector('.bcd-page-hero'));
            const online = health.tecdoc_online;
            const syncText = (online ? 'Live' : 'TecDoc offline') + ' · actualizat ' + formatDateTime(data.generated_at);
            syncLabel.textContent = syncText;
            if (isCompanyHero) {
                syncLabel.className = 'bcd-status-pill ' + (online ? 'bcd-status-pill--live' : 'bcd-status-pill--warn');
            } else {
                syncLabel.className = 'besoiu-dash-hero__meta mt-3';
                syncLabel.style.background = '';
            }
        }

        const teamPill = document.getElementById('bcd-hero-team-pill');
        if (teamPill && data.team) {
            teamPill.textContent = (data.team.active_users ?? data.team.total_users ?? 0) + ' utilizatori activi';
        }

        const userBadge = document.getElementById('bws-user-badge');
        if (userBadge && data.current_user && !document.querySelector('.bcd-page-hero')) {
            const u = data.current_user;
            userBadge.textContent = (u.name || 'Operator') + ' · ' + (data.scope_label || '');
            userBadge.classList.remove('hidden');
        }

        renderSystemStatus(data);
        renderTecdocIpWidget(health.tecdoc_ip || {});
        renderRedFlags(data.red_flags || []);
        renderActivity((data.activity?.items || []).slice(0, 3));
        renderTopMissing(search.top_missing || []);
        renderSearchLogAnalytics(search);
        syncMissingWidgetState(search);
        window.__besoiuDashboardData = data;
        const wsRoot = document.querySelector('.besoiu-dashboard[data-besoiu-dash-workspace]');
        const ws = wsRoot ? wsRoot.getAttribute('data-besoiu-dash-workspace') : '';
        const isFull = wsRoot && wsRoot.getAttribute('data-besoiu-dash-full') === '1';
        applyCompanyDashboard(data);
        if (!isFull && window.BwsWorkspaceDash && ws) {
            window.BwsWorkspaceDash.render(ws, data, data.current_user, data.user_metrics);
        }
    }

    function isCompanyDashboard() {
        return Boolean(document.querySelector('.besoiu-dashboard[data-besoiu-dash-full="1"]'));
    }

    function applyCompanyDashboard(data) {
        if (!isCompanyDashboard()) {
            return;
        }
        if (window.BwsCompanyDash && typeof window.BwsCompanyDash.render === 'function') {
            window.BwsCompanyDash.render(data);
        } else {
            window.__besoiuPendingCompanyDash = data;
        }
    }

    function snapshotNeedsTeamRefresh(data) {
        return isCompanyDashboard() && (
            !data
            || data.scope !== 'company_all'
            || !data.team
            || !Array.isArray(data.team.members)
        );
    }

    function syncMissingWidgetState(search) {
        const kpiWidget = document.getElementById('kpi-not-found-widget');
        const analyticsWidget = document.getElementById('search-analytics-not-found-widget');
        const bcdWidget = document.getElementById('bcd-missing-widget');
        const disabled = !search.available || Number(search.missing_codes_count ?? 0) <= 0;
        [kpiWidget, analyticsWidget, bcdWidget].forEach((node) => {
            if (!node) return;
            node.classList.toggle('is-disabled', disabled);
            node.setAttribute('aria-disabled', disabled ? 'true' : 'false');
        });
    }

    function missingSearchTypeLabel(type) {
        const normalized = String(type || '').toLowerCase();
        if (normalized === 'vin') return 'VIN';
        if (normalized === 'oem') return 'OEM';
        if (normalized === 'name') return 'Nume';
        return normalized.toUpperCase() || '—';
    }

    function renderMissingSearchesModal(items, meta) {
        const intro = document.getElementById('missing-searches-modal-intro');
        const list = document.getElementById('missing-searches-modal-list');
        if (!list) return;

        const codesCount = Number(meta?.codes_count ?? items.length);
        const stats = meta?.stats || {};
        if (intro) {
            intro.textContent = codesCount > 0
                ? codesCount + ' coduri unice căutate fără rezultat · '
                    + formatNumber(stats.not_found) + ' evenimente în jurnal · '
                    + formatNumber(stats.today_not_found) + ' azi'
                : 'Nu există căutări negăsite în jurnal.';
        }

        if (!items.length) {
            list.innerHTML = '<div class="rounded-xl border p-4 text-sm opacity-70">Nu există coduri negăsite.</div>';
            return;
        }

        list.innerHTML = `
            <table class="besoiu-missing-modal__table w-full text-sm">
                <thead>
                    <tr class="border-b text-xs uppercase opacity-70">
                        <th class="px-3 py-2 text-left">Tip</th>
                        <th class="px-3 py-2 text-left">Cod / valoare</th>
                        <th class="px-3 py-2 text-right">Încercări</th>
                        <th class="px-3 py-2 text-left">Ultima căutare</th>
                    </tr>
                </thead>
                <tbody>
                    ${items.map((item) => `
                        <tr class="border-b border-foreground/5">
                            <td class="px-3 py-3 whitespace-nowrap">
                                <span class="besoiu-missing-modal__type">${escapeHtml(missingSearchTypeLabel(item.query_type))}</span>
                            </td>
                            <td class="px-3 py-3 font-medium">${escapeHtml(item.query_value || '—')}</td>
                            <td class="px-3 py-3 text-right">${formatNumber(item.attempts || 0)}</td>
                            <td class="px-3 py-3 whitespace-nowrap opacity-70">${escapeHtml(formatDateTime(item.last_seen))}</td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        `;
    }

    async function fetchMissingSearchCodes(forceRefresh) {
        if (!forceRefresh && cachedMissingSearch) {
            return cachedMissingSearch;
        }

        const response = await fetch(SEARCH_LOGS_ENDPOINT, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ type_product: 'top_missing', limit: 200 })
        });
        const result = await response.json();
        if (!response.ok || !result.success) {
            throw new Error(result.message || 'Nu s-a putut încărca lista codurilor negăsite.');
        }

        cachedMissingSearch = {
            items: Array.isArray(result.data) ? result.data : [],
            codes_count: Number(result.codes_count ?? 0),
            stats: result.stats || {},
        };

        return cachedMissingSearch;
    }

    function setMissingSearchesModalOpen(open) {
        const modal = document.getElementById('missing-searches-modal');
        if (!modal) return;
        modal.classList.toggle('hidden', !open);
        modal.setAttribute('aria-hidden', open ? 'false' : 'true');
        document.body.classList.toggle('overflow-y-hidden', open);
    }

    async function openMissingSearchesModal() {
        const list = document.getElementById('missing-searches-modal-list');
        if (list) {
            list.innerHTML = '<div class="rounded-xl border-2 border-dashed p-4 text-sm opacity-70">Se încarcă codurile…</div>';
        }
        setMissingSearchesModalOpen(true);

        try {
            const payload = await fetchMissingSearchCodes(false);
            renderMissingSearchesModal(payload.items, payload);
        } catch (error) {
            if (list) {
                list.innerHTML = '<div class="rounded-xl border border-danger/20 bg-danger/5 p-4 text-sm text-danger">'
                    + escapeHtml(error.message || 'Eroare la încărcare.') + '</div>';
            }
        }
    }

    function bindMissingSearchesWidget(node) {
        if (!node || node.dataset.boundMissing === '1') return;
        node.dataset.boundMissing = '1';
        node.addEventListener('click', () => {
            if (node.classList.contains('is-disabled')) return;
            openMissingSearchesModal().catch((error) => showToast(error.message, true));
        });
        node.addEventListener('keydown', (event) => {
            if (node.classList.contains('is-disabled')) return;
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                openMissingSearchesModal().catch((error) => showToast(error.message, true));
            }
        });
    }

    function renderTecdocIpWidget(tecdocIp) {
        const badge = document.getElementById('tecdoc-ip-badge');
        const label = document.getElementById('tecdoc-ip-label');
        const dot = document.getElementById('tecdoc-ip-dot');
        const ipValue = document.getElementById('tecdoc-ip-value');
        const detail = document.getElementById('tecdoc-ip-detail');
        const alertBox = document.getElementById('tecdoc-ip-alert');
        const indicator = document.getElementById('tecdoc-ip-indicator');
        if (!badge || !label || !dot) {
            return;
        }

        const isValid = Boolean(tecdocIp.ip_valid);
        const serverIp = tecdocIp.server_ip || 'necunoscut';
        const checkedAt = tecdocIp.checked_at ? formatDateTime(tecdocIp.checked_at) : '—';

        badge.textContent = isValid ? 'IP valid' : 'IP invalid';
        badge.className = 'besoiu-subpanel__tag ' + (isValid ? 'besoiu-subpanel__tag--ok' : 'besoiu-subpanel__tag--alert');
        label.textContent = isValid ? 'IP TecDoc valid' : 'IP TecDoc invalid';
        dot.className = 'besoiu-tecdoc-ip-widget__dot ' + (isValid ? 'is-valid' : 'is-invalid');
        if (indicator) {
            indicator.className = 'besoiu-tecdoc-ip-widget__indicator ' + (isValid ? 'is-valid' : 'is-invalid');
        }
        if (ipValue) {
            ipValue.textContent = serverIp;
        }
        if (detail) {
            detail.textContent = (tecdocIp.message || 'Verificare completă.') + ' · ' + checkedAt;
        }
        if (alertBox) {
            const operatorMessage = String(tecdocIp.operator_message || '').trim();
            if (!isValid && operatorMessage !== '') {
                alertBox.textContent = operatorMessage;
                alertBox.classList.remove('hidden');
            } else {
                alertBox.textContent = '';
                alertBox.classList.add('hidden');
            }
        }
    }

    function renderSystemStatus(data) {
        const grid = document.getElementById('system-status-grid');
        const label = document.getElementById('system-status-label');
        if (!grid) return;

        const health = data.health || {};
        const products = data.products || {};
        const importInfo = data.import || {};
        const bots = data.bots || [];

        const cards = [
            {
                title: 'TecDoc API',
                value: health.tecdoc_online ? 'Online' : 'Cotă depășită',
                tone: health.tecdoc_online ? 'text-success' : 'text-danger'
            },
            {
                title: 'Import job',
                value: importInfo.running_jobs > 0
                    ? importInfo.running_jobs + ' rulează'
                    : (importInfo.last_job?.status || 'Inactiv'),
                tone: importInfo.failed_jobs > 0 ? 'text-danger' : 'text-primary'
            },
            {
                title: 'Coada import',
                value: formatNumber(products.queue_pending) + ' pending',
                tone: products.queue_pending > 0 ? 'text-warning' : 'text-success'
            },
            {
                title: 'Produse active',
                value: formatNumber(products.active),
                tone: 'text-primary'
            },
            {
                title: 'Backup',
                value: health.latest_backup_at ? formatDateTime(health.latest_backup_at) : 'Neconfigurat',
                tone: health.latest_backup_at ? 'text-success' : 'text-warning'
            },
            {
                title: 'Boți configurați',
                value: bots.length > 0 ? bots.length + ' bot(i)' : 'Niciun bot',
                tone: bots.length > 0 ? 'text-success' : 'text-warning'
            }
        ];

        bots.slice(0, 2).forEach((bot) => {
            cards.push({
                title: bot.name || bot.channel || 'Bot',
                value: bot.token_status || 'unknown',
                tone: String(bot.token_status).toLowerCase() === 'active' ? 'text-success' : 'text-warning'
            });
        });

        grid.innerHTML = cards.map((card) => `
            <div class="besoiu-status-cell">
                <div class="uppercase">${escapeHtml(card.title)}</div>
                <div class="mt-2 font-medium ${card.tone}">${escapeHtml(card.value)}</div>
            </div>
        `).join('');

        if (label) {
            label.textContent = health.tecdoc_online ? 'Sistem operațional' : 'Atenție: TecDoc blocat';
            label.className = 'ms-auto text-xs ' + (health.tecdoc_online ? 'text-success' : 'text-danger');
        }
    }

    function renderRedFlags(flags) {
        const list = document.getElementById('red-flags-list');
        const count = document.getElementById('red-flags-count');
        if (!list) return;

        const critical = flags.filter((item) => item.critical || item.level === 'danger').length;
        if (count) {
            count.textContent = critical > 0 ? critical + ' critice' : (flags.length > 0 ? flags.length + ' alerte' : '0');
            count.className = 'besoiu-subpanel__tag besoiu-subpanel__tag--alert' + (critical > 0 ? '' : ' besoiu-subpanel__tag--ok');
        }

        if (!flags.length) {
            list.innerHTML = '<div class="rounded-xl border border-success/20 bg-success/5 p-4 text-sm text-success">Nicio alertă activă — sistem operațional.</div>';
            return;
        }

        list.innerHTML = flags.map((flag) => {
            const border = flag.level === 'danger' ? 'border-danger/20 bg-danger/5 text-danger' : 'border-warning/20 bg-warning/5 text-warning';
            const criticalBadge = flag.critical
                ? '<span class="besoiu-red-flag__badge">CRITIC</span>'
                : '';
            const detailUrl = flag.url || '#';
            const hasRetry = Boolean(flag.retry_url || flag.retry_action);
            const retryBtn = hasRetry
                ? `<button type="button" class="besoiu-red-flag__btn besoiu-red-flag__btn--retry"
                        data-code="${escapeHtml(flag.code || '')}"
                        data-job-id="${escapeHtml(flag.job_id || '')}"
                        data-entity-type="${escapeHtml(flag.entity_type || '')}"
                        data-entity-id="${escapeHtml(flag.entity_id || '')}"
                        data-retry-url="${escapeHtml(flag.retry_url || '')}"
                        data-retry-action="${escapeHtml(flag.retry_action || '')}">Reîncearcă</button>`
                : '';

            return `
                <div class="besoiu-red-flag rounded-xl border p-4 ${border}">
                    <div class="besoiu-red-flag__head">
                        ${criticalBadge}
                        <div class="text-sm font-medium">${escapeHtml(flag.title || '')}</div>
                    </div>
                    <div class="mt-1 text-xs opacity-70">${escapeHtml(flag.detail || '')}</div>
                    <div class="besoiu-red-flag__actions mt-3 flex flex-wrap gap-2">
                        <a href="${escapeHtml(detailUrl)}" class="besoiu-red-flag__btn">Vezi detalii</a>
                        ${retryBtn}
                    </div>
                </div>
            `;
        }).join('');
    }

    async function retryRedFlag(button) {
        const code = button.dataset.code || '';
        const jobId = button.dataset.jobId || '';
        const entityType = button.dataset.entityType || '';
        const entityId = button.dataset.entityId || '';
        const retryUrl = button.dataset.retryUrl || '';
        const retryAction = button.dataset.retryAction || '';

        button.disabled = true;

        try {
            if (retryAction === 'refresh_tecdoc' || code === 'tecdoc_dead' || code === 'tecdoc_ip_invalid') {
                await loadDashboard(true, false);
                showToast('TecDoc re-verificat.');
                return;
            }

            if (retryAction === 'cancel_blocked_job' && jobId) {
                showToast('Modulul de import a fost dezactivat.');
                return;
            }

            if (retryAction === 'test_integration' && entityType === 'furnizor' && entityId) {
                const res = await fetch('/admin/api/furnizori_endpoint.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify({ type_product: 'testconnection', randomn_id: entityId })
                });
                const result = await res.json();
                if (!res.ok || !result.success) {
                    throw new Error(result.message || 'Test conexiune eșuat.');
                }
                await loadDashboard(true, false);
                showToast('Test conexiune relansat.');
                return;
            }

            if (retryAction === 'test_integration' && entityType === 'bot' && entityId) {
                showToast('Modulul Bots nu este portat încă — test indisponibil.', true);
                return;
            }

            if (retryUrl) {
                window.location.href = retryUrl;
                return;
            }

            showToast('Acțiune indisponibilă pentru această alertă.', true);
        } catch (error) {
            showToast(error.message || 'Reîncercarea a eșuat.', true);
        } finally {
            button.disabled = false;
        }
    }

    function renderActivity(items) {
        const table = document.getElementById('activity-table');
        if (!table) return;

        if (!items.length) {
            table.innerHTML = '<tr><td colspan="5" class="px-5 py-6 text-center opacity-70">Nicio activitate recentă.</td></tr>';
            return;
        }

        table.innerHTML = items.map((item) => `
            <tr class="border-b">
                <td class="px-5 py-4 whitespace-nowrap opacity-70">${escapeHtml(formatDateTime(item.time))}</td>
                <td class="px-5 py-4">
                    <div class="font-medium">${escapeHtml(item.title || '')}</div>
                    <div class="text-xs opacity-60">${escapeHtml(item.subtitle || '')}</div>
                </td>
                <td class="px-5 py-4">${escapeHtml(item.channel || '')}</td>
                <td class="px-5 py-4 text-center">${statusBadge(item.status, item.type)}</td>
                <td class="px-5 py-4 text-right">
                    <a class="rounded-lg border px-3 py-1.5 text-xs" href="${escapeHtml(item.url || '#')}">Vezi</a>
                </td>
            </tr>
        `).join('');
    }

    function renderTopMissing(items) {
        const list = document.getElementById('top-missing-list');
        if (!list) return;

        if (!items.length) {
            list.innerHTML = '<div class="rounded-xl border p-4 text-sm opacity-70">Nu există căutări negăsite.</div>';
            return;
        }

        list.innerHTML = items.map((item) => `
            <div class="rounded-xl border border-foreground/10 p-4">
                <div class="text-sm font-medium">${escapeHtml(String(item.query_type || '').toUpperCase())}: ${escapeHtml(item.query_value || '')}</div>
                <div class="mt-1 text-xs opacity-70">${escapeHtml(item.attempts || 0)} încercări · ultima: ${escapeHtml(formatDateTime(item.last_seen))}</div>
            </div>
        `).join('');
    }

    function formatShortDay(dayValue) {
        if (!dayValue) return '—';
        const date = new Date(String(dayValue) + 'T12:00:00');
        if (Number.isNaN(date.getTime())) return String(dayValue);
        return date.toLocaleDateString('ro-RO', { day: '2-digit', month: '2-digit' });
    }

    function renderSearchLogAnalytics(search) {
        const panel = document.getElementById('search-log-analytics-panel');
        if (!panel) return;

        const totalEl = document.getElementById('search-analytics-total');
        const foundEl = document.getElementById('search-analytics-found');
        const notFoundEl = document.getElementById('search-analytics-not-found');
        const rateEl = document.getElementById('search-analytics-rate');
        const trendEl = document.getElementById('search-analytics-trend');
        const topOemEl = document.getElementById('search-analytics-top-oem');
        const badgeEl = document.getElementById('search-analytics-badge');

        if (!search.available) {
            if (totalEl) totalEl.textContent = '—';
            if (foundEl) foundEl.textContent = '—';
            if (notFoundEl) notFoundEl.textContent = '—';
            if (rateEl) rateEl.textContent = '—';
            if (trendEl) {
                trendEl.innerHTML = '<div class="besoiu-search-analytics__empty">Jurnalul search_logs nu este disponibil.</div>';
            }
            if (topOemEl) {
                topOemEl.innerHTML = '<div class="besoiu-search-analytics__empty">Nicio dată OEM.</div>';
            }
            if (badgeEl) badgeEl.textContent = 'Indisponibil';
            return;
        }

        const total = Number(search.total || 0);
        const found = Number(search.found || 0);
        const notFound = Number(search.not_found || 0);
        const rate = total > 0 ? Math.round((found / total) * 100) : 0;

        if (totalEl) totalEl.textContent = formatNumber(total);
        if (foundEl) foundEl.textContent = formatNumber(found);
        if (notFoundEl) notFoundEl.textContent = formatNumber(Number(search.missing_codes_count ?? notFound));
        if (rateEl) rateEl.textContent = rate + '%';
        if (badgeEl) badgeEl.textContent = 'Analitic · ' + formatNumber(search.today || 0) + ' azi';

        const trend = Array.isArray(search.daily_trend) ? search.daily_trend : [];
        if (trendEl) {
            if (!trend.length) {
                trendEl.innerHTML = '<div class="besoiu-search-analytics__empty">Nicio căutare în ultimele 14 zile.</div>';
            } else {
                const maxTotal = Math.max(...trend.map((row) => Number(row.total || 0)), 1);
                trendEl.innerHTML = `
                    <div class="besoiu-search-trend__bars" role="img" aria-label="Trend căutări pe zile">
                        ${trend.map((row) => {
                            const rowTotal = Number(row.total || 0);
                            const rowFound = Number(row.found || 0);
                            const rowMissing = Number(row.not_found || 0);
                            const heightPct = Math.max(4, Math.round((rowTotal / maxTotal) * 100));
                            const foundPct = rowTotal > 0 ? Math.round((rowFound / rowTotal) * heightPct) : 0;
                            const missingPct = Math.max(0, heightPct - foundPct);
                            return `
                                <div class="besoiu-search-trend__col" title="${escapeHtml(row.day || '')}: ${rowTotal} total">
                                    <div class="besoiu-search-trend__bar" style="height:${heightPct}%">
                                        <span class="besoiu-search-trend__seg besoiu-search-trend__seg--found" style="height:${foundPct}%"></span>
                                        <span class="besoiu-search-trend__seg besoiu-search-trend__seg--missing" style="height:${missingPct}%"></span>
                                    </div>
                                    <span class="besoiu-search-trend__label">${escapeHtml(formatShortDay(row.day))}</span>
                                </div>
                            `;
                        }).join('')}
                    </div>
                `;
            }
        }

        const topOem = Array.isArray(search.top_oem) ? search.top_oem : [];
        if (topOemEl) {
            if (!topOem.length) {
                topOemEl.innerHTML = '<div class="besoiu-search-analytics__empty">Niciun cod OEM căutat încă.</div>';
            } else {
                topOemEl.innerHTML = topOem.map((item, index) => {
                    const attempts = Number(item.attempts || 0);
                    const foundCount = Number(item.found_count || 0);
                    const missingCount = Number(item.not_found_count || 0);
                    const foundShare = attempts > 0 ? Math.round((foundCount / attempts) * 100) : 0;
                    return `
                        <div class="besoiu-search-top-oem__row">
                            <div class="besoiu-search-top-oem__rank">${index + 1}</div>
                            <div class="besoiu-search-top-oem__body">
                                <div class="besoiu-search-top-oem__code">${escapeHtml(item.query_value || '—')}</div>
                                <div class="besoiu-search-top-oem__meta">
                                    ${formatNumber(attempts)} căutări ·
                                    <span class="text-success">${formatNumber(foundCount)} găsite</span> ·
                                    <span class="text-danger">${formatNumber(missingCount)} negăsite</span>
                                </div>
                                <div class="besoiu-search-top-oem__bar" aria-hidden="true">
                                    <span class="besoiu-search-top-oem__bar-found" style="width:${foundShare}%"></span>
                                </div>
                            </div>
                        </div>
                    `;
                }).join('');
            }
        }
    }

    document.getElementById('dashboard-refresh')?.addEventListener('click', () => {
        loadDashboard(true, false).catch((error) => showToast(error.message, true));
    });

    document.getElementById('red-flags-list')?.addEventListener('click', (event) => {
        const button = event.target.closest('.besoiu-red-flag__btn--retry');
        if (!button) {
            return;
        }
        event.preventDefault();
        retryRedFlag(button).catch((error) => showToast(error.message, true));
    });

    bindMissingSearchesWidget(document.getElementById('kpi-not-found-widget'));
    bindMissingSearchesWidget(document.getElementById('search-analytics-not-found-widget'));
    bindMissingSearchesWidget(document.getElementById('bcd-missing-widget'));

    document.getElementById('missing-searches-modal-close')?.addEventListener('click', () => setMissingSearchesModalOpen(false));
    document.getElementById('missing-searches-modal-close-bottom')?.addEventListener('click', () => setMissingSearchesModalOpen(false));
    document.getElementById('missing-searches-modal')?.addEventListener('click', (event) => {
        if (event.target.id === 'missing-searches-modal') {
            setMissingSearchesModalOpen(false);
        }
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            setMissingSearchesModalOpen(false);
        }
    });

    bootstrapDashboard();
})();
</script>
