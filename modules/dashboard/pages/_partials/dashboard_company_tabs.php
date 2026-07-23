<?php

declare(strict_types=1);

?>
<div class="col-span-12" data-besoiu-dash-panel="company-tabs">
    <div class="bcd-shell" id="bcd-shell">
        <div class="bcd-scope-banner" id="bcd-scope-banner">
            <div class="bcd-scope-banner__icon" aria-hidden="true">◎</div>
            <div>
                <strong id="bcd-scope-title">Date consolidate — întreaga organizație</strong>
                <p id="bcd-scope-desc">Comenzi, căutări, catalog și mesaje — agregat de la toți utilizatorii admin, nu doar contul tău.</p>
            </div>
        </div>
        <header class="bcd-toolbar">
            <nav class="bcd-tabs" role="tablist" aria-label="Secțiuni dashboard Company Settings">
                <button type="button" class="bcd-tab is-active" role="tab" aria-selected="true" data-bcd-tab="overview" id="bcd-tab-btn-overview">
                    <i data-lucide="layout-dashboard" class="bcd-tab__icon"></i>
                    <span>Prezentare</span>
                </button>
                <button type="button" class="bcd-tab" role="tab" aria-selected="false" data-bcd-tab="team" id="bcd-tab-btn-team">
                    <i data-lucide="users" class="bcd-tab__icon"></i>
                    <span>Echipă</span>
                </button>
                <button type="button" class="bcd-tab" role="tab" aria-selected="false" data-bcd-tab="orders" id="bcd-tab-btn-orders">
                    <i data-lucide="shopping-cart" class="bcd-tab__icon"></i>
                    <span>Comenzi</span>
                </button>
                <button type="button" class="bcd-tab" role="tab" aria-selected="false" data-bcd-tab="catalog" id="bcd-tab-btn-catalog">
                    <i data-lucide="package" class="bcd-tab__icon"></i>
                    <span>Catalog</span>
                </button>
                <button type="button" class="bcd-tab" role="tab" aria-selected="false" data-bcd-tab="search" id="bcd-tab-btn-search">
                    <i data-lucide="search" class="bcd-tab__icon"></i>
                    <span>Căutări</span>
                </button>
                <button type="button" class="bcd-tab" role="tab" aria-selected="false" data-bcd-tab="automation" id="bcd-tab-btn-automation">
                    <i data-lucide="bot" class="bcd-tab__icon"></i>
                    <span>Automatizare</span>
                </button>
                <button type="button" class="bcd-tab" role="tab" aria-selected="false" data-bcd-tab="system" id="bcd-tab-btn-system">
                    <i data-lucide="server" class="bcd-tab__icon"></i>
                    <span>Sistem</span>
                </button>
            </nav>
            <div class="bcd-toolbar__meta">
                <span class="bcd-live-dot" aria-hidden="true"></span>
                <span id="bcd-sync-hint">Se sincronizează...</span>
            </div>
        </header>

        <div class="bcd-panels">
            <!-- TAB 1: Prezentare -->
            <section class="bcd-panel is-active" id="bcd-panel-overview" role="tabpanel" aria-labelledby="bcd-tab-btn-overview" data-bcd-panel="overview">
                <p class="bcd-panel__lead">Vedere de ansamblu — indicatori cheie, alerte critice și pulsul operațiunilor în timp real.</p>
                <div class="bws-metrics bws-grid--thirds">
                    <div class="bcd-metric bcd-metric--hero">
                        <span class="bcd-metric__label">Comenzi azi</span>
                        <strong id="bcd-ov-orders-today" class="bcd-metric__value">—</strong>
                        <span id="bcd-ov-orders-today-hint" class="bcd-metric__hint">—</span>
                        <div id="bcd-ov-spark-orders" class="bcd-sparkline"></div>
                    </div>
                    <div class="bcd-metric bcd-metric--hero">
                        <span class="bcd-metric__label">Venit azi</span>
                        <strong id="bcd-ov-revenue-today" class="bcd-metric__value">—</strong>
                        <span id="bcd-ov-revenue-hint" class="bcd-metric__hint">RON</span>
                        <div id="bcd-ov-spark-revenue" class="bcd-sparkline"></div>
                    </div>
                    <div class="bcd-metric bcd-metric--hero">
                        <span class="bcd-metric__label">Căutări azi</span>
                        <strong id="bcd-ov-searches-today" class="bcd-metric__value">—</strong>
                        <span id="bcd-ov-search-rate" class="bcd-metric__hint">—</span>
                    </div>
                </div>
                <div class="bws-metrics bws-grid--thirds bcd-mt">
                    <div class="bcd-metric">
                        <span class="bcd-metric__label">Produse active</span>
                        <strong id="bcd-ov-prod-active" class="bcd-metric__value">—</strong>
                        <span class="bcd-metric__hint">din <span id="bcd-ov-prod-total">—</span> total</span>
                    </div>
                    <div class="bcd-metric bcd-metric--warn">
                        <span class="bcd-metric__label">Red flags</span>
                        <strong id="bcd-ov-flags-count" class="bcd-metric__value">—</strong>
                        <span class="bcd-metric__hint">alerte active</span>
                    </div>
                    <div class="bcd-metric">
                        <span class="bcd-metric__label">Mesaje hub</span>
                        <strong id="bcd-ov-messages-open" class="bcd-metric__value">—</strong>
                        <span class="bcd-metric__hint">necesită atenție</span>
                    </div>
                </div>
                <div class="bws-charts bcd-mt">
                    <div class="bws-charts-row bws-charts-row--1">
                        <div class="bws-card">
                            <div class="bws-card__head"><h4>Red Flag Center</h4><span id="bcd-flags-badge" class="bcd-badge bcd-badge--danger">0</span></div>
                            <div id="bcd-red-flags-list" class="bcd-flags-list"><div class="bws-empty">Se încarcă...</div></div>
                        </div>
                    </div>
                    <div class="bws-charts-row bws-charts-row--2">
                        <div class="bws-card">
                            <div class="bws-card__head"><h4>Activitate recentă</h4></div>
                            <div id="bcd-activity-table"></div>
                        </div>
                        <div class="bws-card">
                            <div class="bws-card__head"><h4>Top căutări fără rezultat</h4></div>
                            <div id="bcd-top-missing"></div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- TAB: Echipă — toți utilizatorii -->
            <section class="bcd-panel" id="bcd-panel-team" role="tabpanel" hidden data-bcd-panel="team">
                <p class="bcd-panel__lead">Activitate și contribuție per utilizator admin — produse gestionate, roluri și zone de lucru.</p>
                <div class="bws-metrics bws-grid--thirds">
                    <div class="bcd-metric bcd-metric--hero">
                        <span class="bcd-metric__label">Utilizatori total</span>
                        <strong id="bcd-team-total" class="bcd-metric__value">—</strong>
                        <span class="bcd-metric__hint">conturi admin</span>
                    </div>
                    <div class="bcd-metric bcd-metric--ok">
                        <span class="bcd-metric__label">Activi</span>
                        <strong id="bcd-team-active" class="bcd-metric__value">—</strong>
                        <span class="bcd-metric__hint">status activ</span>
                    </div>
                    <div class="bcd-metric">
                        <span class="bcd-metric__label">Produse neatribuite</span>
                        <strong id="bcd-team-unassigned" class="bcd-metric__value">—</strong>
                        <span class="bcd-metric__hint">fără id utilizator</span>
                    </div>
                </div>
                <div class="bws-charts bcd-mt">
                    <div class="bws-charts-row bws-charts-row--1">
                        <div class="bws-card"><div class="bws-card__head"><h4>Produse per utilizator</h4></div><div id="bcd-chart-team-bars"></div></div>
                    </div>
                    <div class="bws-charts-row bws-charts-row--1">
                        <div class="bws-card"><div class="bws-card__head"><h4>Membri echipă</h4><a href="/admin/users" class="bcd-link">Gestiune utilizatori</a></div><div id="bcd-table-team"></div></div>
                    </div>
                </div>
            </section>

            <!-- TAB 2: Comenzi -->
            <section class="bcd-panel" id="bcd-panel-orders" role="tabpanel" hidden data-bcd-panel="orders">
                <p class="bcd-panel__lead">Performanță comercială — trenduri, distribuție status și ultimele tranzacții.</p>
                <div class="bws-metrics bws-grid--halves">
                    <div class="bcd-metric"><span class="bcd-metric__label">Total comenzi</span><strong id="bcd-ord-total" class="bcd-metric__value">—</strong></div>
                    <div class="bcd-metric"><span class="bcd-metric__label">Comenzi noi</span><strong id="bcd-ord-new" class="bcd-metric__value">—</strong></div>
                    <div class="bcd-metric"><span class="bcd-metric__label">Comenzi azi</span><strong id="bcd-ord-today" class="bcd-metric__value">—</strong></div>
                    <div class="bcd-metric bcd-metric--accent"><span class="bcd-metric__label">Venit azi</span><strong id="bcd-ord-revenue" class="bcd-metric__value">—</strong></div>
                </div>
                <div class="bws-charts bcd-mt">
                    <div class="bws-charts-row bws-charts-row--1">
                        <div class="bws-card"><div class="bws-card__head"><h4>Comenzi — ultimele 14 zile</h4></div><div id="bcd-chart-orders-bars"></div></div>
                    </div>
                    <div class="bws-charts-row bws-charts-row--2">
                        <div class="bws-card"><div class="bws-card__head"><h4>Distribuție status</h4></div><div id="bcd-chart-status-donut"></div></div>
                        <div class="bws-card"><div class="bws-card__head"><h4>Venit — trend 14 zile</h4></div><div id="bcd-chart-revenue-area"></div></div>
                    </div>
                    <div class="bws-charts-row bws-charts-row--1">
                        <div class="bws-card"><div class="bws-card__head"><h4>Ultimele comenzi</h4><a href="/admin/orders" class="bcd-link">Vezi toate</a></div><div id="bcd-table-orders"></div></div>
                    </div>
                </div>
            </section>

            <!-- TAB 3: Catalog -->
            <section class="bcd-panel" id="bcd-panel-catalog" role="tabpanel" hidden data-bcd-panel="catalog">
                <p class="bcd-panel__lead">Stoc, calitate date și pipeline import — tot ce ține de catalogul de piese.</p>
                <div class="bws-metrics bws-grid--halves">
                    <div class="bcd-metric"><span class="bcd-metric__label">Produse totale</span><strong id="bcd-cat-total" class="bcd-metric__value">—</strong></div>
                    <div class="bcd-metric bcd-metric--ok"><span class="bcd-metric__label">Active</span><strong id="bcd-cat-active" class="bcd-metric__value">—</strong></div>
                    <div class="bcd-metric bcd-metric--warn"><span class="bcd-metric__label">Fără imagine</span><strong id="bcd-cat-no-image" class="bcd-metric__value">—</strong></div>
                    <div class="bcd-metric bcd-metric--danger"><span class="bcd-metric__label">Fără OEM</span><strong id="bcd-cat-no-oem" class="bcd-metric__value">—</strong></div>
                </div>
                <div class="bws-metrics bws-grid--thirds bcd-mt">
                    <div class="bcd-metric"><span class="bcd-metric__label">Coadă import</span><strong id="bcd-cat-queue" class="bcd-metric__value">—</strong><span class="bcd-metric__hint">pending</span></div>
                    <div class="bcd-metric"><span class="bcd-metric__label">Index OEM</span><strong id="bcd-cat-oem-products" class="bcd-metric__value">—</strong><span class="bcd-metric__hint">produse indexate</span></div>
                    <div class="bcd-metric"><span class="bcd-metric__label">Coduri OEM</span><strong id="bcd-cat-oem-codes" class="bcd-metric__value">—</strong><span class="bcd-metric__hint">în index</span></div>
                </div>
                <div class="bws-charts bcd-mt">
                    <div class="bws-charts-row bws-charts-row--2">
                        <div class="bws-card"><div class="bws-card__head"><h4>Calitate catalog</h4></div><div id="bcd-chart-quality-donut"></div></div>
                        <div class="bws-card"><div class="bws-card__head"><h4>Status import joburi</h4></div><div id="bcd-chart-import-donut"></div></div>
                    </div>
                    <div class="bws-charts-row bws-charts-row--1">
                        <div class="bws-card"><div class="bws-card__head"><h4>Ultimul job import</h4></div><div id="bcd-import-last-job"></div></div>
                    </div>
                </div>
            </section>

            <!-- TAB 4: Căutări -->
            <section class="bcd-panel" id="bcd-panel-search" role="tabpanel" hidden data-bcd-panel="search">
                <p class="bcd-panel__lead">Analytics căutări site — succes, gap-uri stoc și top interogări OEM.</p>
                <div class="bws-metrics bws-grid--halves">
                    <div class="bcd-metric"><span class="bcd-metric__label">Total căutări</span><strong id="bcd-srch-total" class="bcd-metric__value">—</strong></div>
                    <div class="bcd-metric bcd-metric--ok"><span class="bcd-metric__label">Cu rezultat</span><strong id="bcd-srch-found" class="bcd-metric__value">—</strong></div>
                    <div class="bcd-metric bcd-metric--warn bcd-metric--clickable" id="bcd-missing-widget" role="button" tabindex="0" aria-haspopup="dialog" aria-controls="missing-searches-modal" title="Click pentru coduri negăsite">
                        <span class="bcd-metric__label">Negăsite</span><strong id="bcd-srch-not-found" class="bcd-metric__value">—</strong>
                        <span id="bcd-srch-missing-codes" class="bcd-metric__hint">— coduri unice</span>
                    </div>
                    <div class="bcd-metric"><span class="bcd-metric__label">Rată succes</span><strong id="bcd-srch-rate" class="bcd-metric__value">—</strong></div>
                </div>
                <div class="bws-charts bcd-mt">
                    <div class="bws-charts-row bws-charts-row--1">
                        <div class="bws-card"><div class="bws-card__head"><h4>Volum căutări — 14 zile</h4><a href="/admin/searchlogs" class="bcd-link">Search Logs</a></div><div id="bcd-chart-search-bars"></div></div>
                    </div>
                    <div class="bws-charts-row bws-charts-row--2">
                        <div class="bws-card"><div class="bws-card__head"><h4>Found vs not found</h4></div><div id="bcd-chart-search-donut"></div></div>
                        <div class="bws-card"><div class="bws-card__head"><h4>Tipuri negăsite</h4></div><div id="bcd-chart-missing-types"></div></div>
                    </div>
                    <div class="bws-charts-row bws-charts-row--1">
                        <div class="bws-card"><div class="bws-card__head"><h4>Top OEM căutate</h4></div><div id="bcd-table-top-oem"></div></div>
                    </div>
                </div>
            </section>

            <!-- TAB 5: Automatizare -->
            <section class="bcd-panel" id="bcd-panel-automation" role="tabpanel" hidden data-bcd-panel="automation">
                <p class="bcd-panel__lead">Boți, hub mesaje și prezență online — automatizări și canale de comunicare.</p>
                <div class="bws-metrics bws-grid--thirds">
                    <div class="bcd-metric"><span class="bcd-metric__label">Boți configurați</span><strong id="bcd-auto-bots-count" class="bcd-metric__value">—</strong></div>
                    <div class="bcd-metric"><span class="bcd-metric__label">Mesaje deschise</span><strong id="bcd-auto-msg-open" class="bcd-metric__value">—</strong></div>
                    <div class="bcd-metric"><span class="bcd-metric__label">Pagini site</span><strong id="bcd-auto-pages" class="bcd-metric__value">—</strong></div>
                </div>
                <div class="bws-charts bcd-mt">
                    <div class="bws-charts-row bws-charts-row--1">
                        <div class="bws-card"><div class="bws-card__head"><h4>Boți &amp; integrări</h4><a href="/admin/bots" class="bcd-link">Administrare</a></div><div id="bcd-table-bots"></div></div>
                    </div>
                    <div class="bws-charts-row bws-charts-row--2">
                        <div class="bws-card"><div class="bws-card__head"><h4>Canale mesaje</h4></div><div id="bcd-chart-channels-donut"></div></div>
                        <div class="bws-card"><div class="bws-card__head"><h4>Website &amp; conținut</h4></div><div id="bcd-website-stats"></div></div>
                    </div>
                </div>
            </section>

            <!-- TAB 6: Sistem -->
            <section class="bcd-panel" id="bcd-panel-system" role="tabpanel" hidden data-bcd-panel="system">
                <p class="bcd-panel__lead">Sănătate infrastructură — TecDoc, backup, loguri și status integrări.</p>
                <div class="bws-metrics bws-grid--thirds">
                    <div class="bcd-metric" id="bcd-sys-tecdoc-metric">
                        <span class="bcd-metric__label">TecDoc API</span>
                        <strong id="bcd-sys-tecdoc-status" class="bcd-metric__value">—</strong>
                    </div>
                    <div class="bcd-metric">
                        <span class="bcd-metric__label">Ultim backup</span>
                        <strong id="bcd-sys-backup" class="bcd-metric__value bcd-metric__value--sm">—</strong>
                    </div>
                    <div class="bcd-metric">
                        <span class="bcd-metric__label">Log RapidAPI</span>
                        <strong id="bcd-sys-log" class="bcd-metric__value bcd-metric__value--sm">—</strong>
                    </div>
                </div>
                <div class="bws-charts bcd-mt">
                    <div class="bws-charts-row bws-charts-row--1">
                        <div class="bws-card bcd-tecdoc-card">
                            <div class="bws-card__head"><h4>TecDoc — Validitate IP / API</h4><span id="bcd-tecdoc-badge" class="bcd-badge">—</span></div>
                            <div id="bcd-tecdoc-widget" class="bcd-tecdoc">
                                <div class="bcd-tecdoc__row"><span class="bcd-tecdoc__dot" id="bcd-tecdoc-dot"></span><span id="bcd-tecdoc-label">Se verifică...</span></div>
                                <p class="bcd-tecdoc__ip">IP server: <strong id="bcd-tecdoc-ip">—</strong></p>
                                <p class="bcd-tecdoc__detail" id="bcd-tecdoc-detail">—</p>
                            </div>
                        </div>
                    </div>
                    <div class="bws-charts-row bws-charts-row--2">
                        <div class="bws-card"><div class="bws-card__head"><h4>Import joburi</h4></div><div id="bcd-sys-import-grid" class="bcd-health-grid"></div></div>
                        <div class="bws-card"><div class="bws-card__head"><h4>Stare sistem</h4></div><div id="bcd-sys-health-grid" class="bcd-health-grid"></div></div>
                    </div>
                    <div class="bws-charts-row bws-charts-row--1">
                        <div class="bws-card"><div class="bws-card__head"><h4>Toate alertele</h4><a href="/admin/alerts" class="bcd-link">Centru alerte</a></div><div id="bcd-sys-flags-full"></div></div>
                    </div>
                </div>
            </section>
        </div>
    </div>
</div>
