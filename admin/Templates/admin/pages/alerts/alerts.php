<?php declare(strict_types=1); ?>
<div>
    <div id="alerts-toast" class="hidden fixed right-5 top-5 z-[60] rounded-md border bg-background px-4 py-3 text-sm shadow max-w-md"></div>
    <div class="besoiu-dashboard grid grid-cols-12 gap-4" id="admin-alerts-root">

        <div class="col-span-12">
            <div class="besoiu-dash-hero">
                <div>
                    <span id="alerts-status-badge" class="besoiu-dash-hero__meta">Se verifică…</span>
                    <h1>Centru alerte sistem</h1>
                    <p class="besoiu-red-flag__intro mb-0 mt-2">
                        Aceeași sursă ca <strong>clopoțelul</strong> și <strong>AI Agent</strong> — fără duplicate, cu acțiuni clare.
                    </p>
                </div>
                <div class="besoiu-dash-hero__actions admin-alerts-kpis" id="alerts-kpis">
                    <div class="admin-alerts-kpi admin-alerts-kpi--critical">
                        <span class="admin-alerts-kpi__num" id="kpi-critical">—</span>
                        <span class="admin-alerts-kpi__lbl">Critice</span>
                    </div>
                    <div class="admin-alerts-kpi admin-alerts-kpi--warning">
                        <span class="admin-alerts-kpi__num" id="kpi-warning">—</span>
                        <span class="admin-alerts-kpi__lbl">Atenție</span>
                    </div>
                    <div class="admin-alerts-kpi admin-alerts-kpi--total">
                        <span class="admin-alerts-kpi__num" id="kpi-total">—</span>
                        <span class="admin-alerts-kpi__lbl">Total</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-span-12 hidden" id="alerts-loading">
            <div class="box p-6 text-center text-sm opacity-70">
                <div class="admin-alerts-loading__spinner" aria-hidden="true"></div>
                Scanăm sistemul…
            </div>
        </div>

        <div class="col-span-12 hidden" id="alerts-ok-banner" role="status">
            <div class="besoiu-subpanel besoiu-subpanel--card">
                <div class="besoiu-subpanel__body admin-alerts-ok">
                    <div class="admin-alerts-ok__icon">✓</div>
                    <div>
                        <strong>Totul funcționează normal</strong>
                        <p class="mb-0 mt-1 text-sm opacity-70">Nicio problemă activă. Verificare automată la 30 secunde.</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-span-12 hidden" id="alerts-section-critical">
            <div class="besoiu-subpanel besoiu-subpanel--card besoiu-subpanel--red-flags">
                <div class="besoiu-subpanel__head">
                    <h3>Probleme critice — rezolvă acum</h3>
                    <span class="besoiu-subpanel__tag besoiu-subpanel__tag--alert" id="alerts-critical-tag">0</span>
                </div>
                <div class="besoiu-subpanel__body">
                    <p class="besoiu-red-flag__intro">Servicii oprite, API-uri moarte sau erori care blochează operațiuni.</p>
                    <div class="admin-alerts-grid" id="alerts-list-critical"></div>
                </div>
            </div>
        </div>

        <div class="col-span-12 hidden" id="alerts-section-warning">
            <div class="besoiu-subpanel besoiu-subpanel--card">
                <div class="besoiu-subpanel__head">
                    <h3>Atenție — de verificat</h3>
                    <span class="besoiu-subpanel__tag" id="alerts-warning-tag">0</span>
                </div>
                <div class="besoiu-subpanel__body">
                    <p class="besoiu-red-flag__intro">Nu opresc site-ul, dar pot afecta vânzări, import sau clienții.</p>
                    <div class="admin-alerts-grid" id="alerts-list-warning"></div>
                </div>
            </div>
        </div>

        <div class="col-span-12 hidden" id="alerts-section-info">
            <div class="besoiu-subpanel besoiu-subpanel--card">
                <div class="besoiu-subpanel__head">
                    <h3>Informări</h3>
                    <span class="besoiu-subpanel__tag" id="alerts-info-tag">0</span>
                </div>
                <div class="besoiu-subpanel__body">
                    <div class="admin-alerts-grid" id="alerts-list-info"></div>
                </div>
            </div>
        </div>

        <div class="col-span-12">
            <div class="besoiu-subpanel besoiu-subpanel--card">
                <div class="besoiu-subpanel__head">
                    <h3>Jurnal &amp; diagnostic</h3>
                </div>
                <div class="besoiu-subpanel__body admin-alerts-footer">
                    <p class="besoiu-red-flag__intro mb-0">Istoric complet erori API, import, TecDoc — pentru depanare detaliată.</p>
                    <div class="admin-alerts-footer__actions">
                        <a href="/admin/system-errors" class="besoiu-red-flag__btn">Jurnal erori sistem</a>
                        <a href="/admin/searchlogs" class="besoiu-red-flag__btn">Jurnal căutări TecDoc</a>
                        <span class="admin-alerts-footer__time" id="alerts-checked-at">—</span>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>
