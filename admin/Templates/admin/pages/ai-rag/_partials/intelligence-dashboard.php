<?php declare(strict_types=1); ?>

<section class="ai-intel-pro ai-intel-page" id="ai-intel-root">

    <div class="ai-intel-toolbar ai-intel-card-in">

        <div class="ai-intel-toolbar__main">

            <span class="ai-intel-toolbar__icon" aria-hidden="true"><i class="fa-solid fa-chart-line"></i></span>

            <div>

                <h2 class="ai-intel-toolbar__title">Intelligence · Analytics</h2>

                <p class="ai-intel-toolbar__sub">Tracking · geo ip-api · search hybrid · pipeline AI</p>

            </div>

        </div>

        <div class="ai-intel-toolbar__actions">

            <button type="button" id="ai-intel-refresh" class="ai-intel-btn ai-intel-btn--ghost" title="Reîmprospătează">

                <i class="fa-solid fa-rotate" aria-hidden="true"></i><span class="ai-intel-btn__label">Refresh</span>

            </button>

            <button type="button" id="ai-intel-index-btn" class="ai-intel-btn ai-intel-btn--primary">

                <i class="fa-solid fa-layer-group" aria-hidden="true"></i><span class="ai-intel-btn__label">Index</span>

            </button>

            <button type="button" id="ai-intel-aggregate-btn" class="ai-intel-btn ai-intel-btn--ghost">

                <i class="fa-solid fa-calculator" aria-hidden="true"></i><span class="ai-intel-btn__label">Agregate</span>

            </button>

        </div>

        <span class="ai-intel-toolbar__meta">

            <i class="fa-solid fa-database" aria-hidden="true"></i>

            <span id="ai-intel-db-name">—</span>

        </span>

    </div>



    <div class="ai-intel-page-body">

        <!-- Rând 1: sănătate + KPI-uri -->

        <div class="ai-intel-top-row">

            <header class="ai-intel-hero ai-intel-card-in" id="ai-intel-hero">

                <div class="ai-intel-hero__main">

                    <div class="ai-intel-hero__ring-wrap" aria-hidden="true">

                        <svg class="ai-intel-hero__ring" viewBox="0 0 120 120">

                            <circle class="ai-intel-hero__ring-bg" cx="60" cy="60" r="52" />

                            <circle class="ai-intel-hero__ring-fg" id="ai-intel-health-ring" cx="60" cy="60" r="52"

                                    stroke-dasharray="327" stroke-dashoffset="327" transform="rotate(-90 60 60)" />

                        </svg>

                        <div class="ai-intel-hero__score">

                            <span id="ai-intel-health-score">—</span><small>%</small>

                        </div>

                    </div>

                    <div class="ai-intel-hero__text">

                        <p class="ai-intel-hero__eyebrow"><i class="fa-solid fa-gauge-high" aria-hidden="true"></i> Status pipeline</p>

                        <h3 class="ai-intel-hero__title" id="ai-intel-health-label">Se încarcă…</h3>

                        <p class="ai-intel-hero__desc">Tracking → coadă → agregate → search → orchestrator</p>

                    </div>

                </div>

                <pre id="ai-intel-ops-output" class="ai-intel-ops-log hidden"></pre>

            </header>

            <div class="ai-intel-kpis ai-intel-kpis--sidebar" id="ai-intel-kpis" aria-label="Metrici cheie"></div>

        </div>



        <!-- Pipeline -->

        <section class="ai-intel-flow ai-intel-card-in" aria-label="Flux date intelligence">

            <h3 class="ai-intel-section-title">

                <i class="fa-solid fa-diagram-project" aria-hidden="true"></i>

                Unde merg datele

            </h3>

            <div id="ai-intel-pipeline-flow" class="ai-intel-flow__track">Se încarcă…</div>

        </section>



        <!-- Vizitatori -->

        <section class="ai-intel-visitors ai-intel-card-in" id="ai-intel-visitors" aria-label="Vizitatori magazin">

            <div class="ai-intel-visitors__head">

                <div class="ai-intel-visitors__title-block">

                    <h3 class="ai-intel-section-title">

                        <i class="fa-solid fa-users" aria-hidden="true"></i>

                        Vizitatori live

                    </h3>

                    <p class="ai-intel-visitors__sub">

                        Azi: <strong id="ai-intel-visitors-today">—</strong> sesiuni · geo via ip-api.com

                    </p>

                </div>

                <div class="ai-intel-visitors__filters">

                    <div class="ai-intel-search-field ai-intel-search-field--compact">

                        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>

                        <input type="search" id="ai-intel-visitors-q" placeholder="IP, oraș, țară…" />

                    </div>

                    <select id="ai-intel-visitors-days" class="ai-intel-select">

                        <option value="1">Azi</option>

                        <option value="7" selected>7 zile</option>

                        <option value="30">30 zile</option>

                    </select>

                    <button type="button" id="ai-intel-visitors-refresh" class="ai-intel-btn ai-intel-btn--icon" title="Reîmprospătează">

                        <i class="fa-solid fa-rotate" aria-hidden="true"></i>

                    </button>

                    <button type="button" id="ai-intel-visitors-enrich" class="ai-intel-btn ai-intel-btn--ghost" title="Geo ip-api.com">

                        <i class="fa-solid fa-map-location-dot" aria-hidden="true"></i> Geo

                    </button>

                </div>

            </div>



            <div class="ai-intel-visitors__stats-row">

                <div class="ai-intel-visitors__stat-card">

                    <h4><i class="fa-solid fa-earth-europe" aria-hidden="true"></i> Țări</h4>

                    <div id="ai-intel-countries" class="ai-intel-countries">—</div>

                </div>

                <div class="ai-intel-visitors__stat-card">

                    <h4><i class="fa-solid fa-mobile-screen" aria-hidden="true"></i> Device</h4>

                    <div id="ai-intel-devices" class="ai-intel-devices">—</div>

                </div>

            </div>



            <div class="ai-intel-visitors-table-wrap">

                <table class="ai-intel-visitors-table">

                    <thead>

                        <tr>

                            <th class="col-when">Când</th>

                            <th class="col-loc">Țară / Oraș</th>

                            <th class="col-ip">IP</th>

                            <th class="col-dev">Device</th>

                            <th class="col-ev">Ev.</th>

                            <th class="col-action">Ultima acțiune</th>

                        </tr>

                    </thead>

                    <tbody id="ai-intel-visitors-tbody">

                        <tr><td colspan="6" class="ai-intel-empty-state">Se încarcă…</td></tr>

                    </tbody>

                </table>

            </div>

            <div class="ai-intel-visitors-pager">

                <button type="button" id="ai-intel-visitors-prev" class="ai-intel-btn ai-intel-btn--ghost" disabled>

                    <i class="fa-solid fa-chevron-left" aria-hidden="true"></i>

                </button>

                <span id="ai-intel-visitors-pager-label">—</span>

                <button type="button" id="ai-intel-visitors-next" class="ai-intel-btn ai-intel-btn--ghost" disabled>

                    <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>

                </button>

            </div>

        </section>



        <!-- Analytics grid -->

        <div class="ai-intel-dashboard-grid">

            <article class="ai-intel-panel ai-intel-panel--span-6 ai-intel-card-in">

                <h3 class="ai-intel-panel__title"><i class="fa-solid fa-chart-column" aria-hidden="true"></i> Evenimente — 7 zile</h3>

                <div id="ai-intel-chart-timeline" class="ai-intel-bar-chart"></div>

            </article>



            <article class="ai-intel-panel ai-intel-panel--span-6 ai-intel-card-in">

                <h3 class="ai-intel-panel__title"><i class="fa-solid fa-chart-pie" aria-hidden="true"></i> Tipuri evenimente</h3>

                <div id="ai-intel-chart-breakdown" class="ai-intel-donut-list"></div>

            </article>



            <article class="ai-intel-panel ai-intel-panel--span-4 ai-intel-card-in">

                <h3 class="ai-intel-panel__title"><i class="fa-solid fa-clipboard-check" aria-hidden="true"></i> Verificare sistem</h3>

                <ul id="ai-intel-health-checks" class="ai-intel-checklist ai-intel-checklist--scroll"></ul>

            </article>



            <article class="ai-intel-panel ai-intel-panel--span-4 ai-intel-card-in">

                <h3 class="ai-intel-panel__title"><i class="fa-solid fa-bolt" aria-hidden="true"></i> Ollama</h3>

                <div id="ai-intel-ollama-detail" class="ai-intel-service-card">Se încarcă…</div>

            </article>



            <article class="ai-intel-panel ai-intel-panel--span-4 ai-intel-card-in">

                <h3 class="ai-intel-panel__title"><i class="fa-solid fa-trophy" aria-hidden="true"></i> Top produse (CTR)</h3>

                <div id="ai-intel-top-products" class="ai-intel-rank-list ai-intel-rank-list--scroll"></div>

            </article>



            <article class="ai-intel-panel ai-intel-panel--span-12 ai-intel-card-in">

                <h3 class="ai-intel-panel__title"><i class="fa-solid fa-shuffle" aria-hidden="true"></i> Routing task-uri</h3>

                <div id="ai-intel-task-list" class="ai-intel-task-grid"></div>

            </article>



            <article class="ai-intel-panel ai-intel-panel--span-12 ai-intel-panel--search-lab ai-intel-card-in">

                <h3 class="ai-intel-panel__title"><i class="fa-solid fa-flask" aria-hidden="true"></i> Laborator search hybrid</h3>

                <p class="ai-intel-panel__hint">Pipeline identic cu <code>POST /api/search</code></p>

                <div class="ai-intel-search-bar">

                    <div class="ai-intel-search-field ai-intel-search-field--grow">

                        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>

                        <input type="text" id="ai-intel-search-q" placeholder="Ex: filtru ulei MANN, cod OEM…" />

                    </div>

                    <button type="button" id="ai-intel-search-btn" class="ai-intel-btn ai-intel-btn--primary">Caută</button>

                </div>

                <div class="ai-intel-pop-row">

                    <label for="ai-intel-pop-weight">Popularitate în scor</label>

                    <strong id="ai-intel-pop-val">15%</strong>

                    <input type="range" id="ai-intel-pop-weight" min="0" max="50" value="15" step="5" />

                </div>

                <div id="ai-intel-search-meta" class="ai-intel-search-meta">—</div>

                <div id="ai-intel-search-results" class="ai-intel-search-results">

                    <p class="ai-intel-empty-state">Introdu un query și apasă Caută.</p>

                </div>

            </article>



            <article class="ai-intel-panel ai-intel-panel--span-6 ai-intel-card-in">

                <h3 class="ai-intel-panel__title"><i class="fa-solid fa-route" aria-hidden="true"></i> Ultimele rute AI</h3>

                <div id="ai-intel-routes" class="ai-intel-timeline ai-intel-timeline--scroll"></div>

            </article>



            <details class="ai-intel-panel ai-intel-panel--span-6 ai-intel-details ai-intel-card-in">

                <summary><i class="fa-solid fa-book-open" aria-hidden="true"></i> Cron &amp; ghid</summary>

                <div class="ai-intel-details__grid ai-intel-details__grid--stack">

                    <div>

                        <h4>Automatizări</h4>

                        <div id="ai-intel-cron" class="ai-intel-cron-list"></div>

                    </div>

                    <div>

                        <h4>Cum funcționează</h4>

                        <div id="ai-intel-learn" class="ai-intel-learn-steps"></div>

                    </div>

                    <div>

                        <h4>Mapare tehnică</h4>

                        <div id="ai-intel-pipeline-map" class="ai-intel-tech-map"></div>

                    </div>

                </div>

            </details>

        </div>

    </div>



    <div class="ai-intel-drawer" id="ai-intel-session-drawer" hidden>

        <div class="ai-intel-drawer__backdrop" id="ai-intel-drawer-backdrop"></div>

        <aside class="ai-intel-drawer__panel" role="dialog" aria-labelledby="ai-intel-drawer-title">

            <header class="ai-intel-drawer__head">

                <h3 id="ai-intel-drawer-title"><i class="fa-solid fa-user-clock" aria-hidden="true"></i> Sesiune vizitator</h3>

                <button type="button" id="ai-intel-drawer-close" class="ai-intel-drawer__close" aria-label="Închide">

                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>

                </button>

            </header>

            <div id="ai-intel-drawer-meta" class="ai-intel-drawer__meta"></div>

            <div id="ai-intel-drawer-timeline" class="ai-intel-drawer__timeline"></div>

        </aside>

    </div>

</section>

