<?php
declare(strict_types=1);

/** @var callable $dashShow */

if (!$dashShow('furnizori-section')) {
    return;
}

?>
<div class="col-span-12" data-besoiu-dash-panel="furnizori-section">
    <div class="bws-deck bws-deck--furnizori">
        <div class="bws-deck__head-row">
            <div>
                <h2 class="bws-deck__title">Furnizori B2B</h2>
                <p class="bws-deck__intro bws-deck__intro--tight">
                    Feed-uri, sincronizare și logică de formare preț — integrat în fluxul comercial Clienți și Comenzi.
                </p>
            </div>
            <div class="bws-deck__actions">
                <a href="/admin/furnizori" class="besoiu-btn-secondary inline-flex items-center gap-2">
                    <i data-lucide="truck" class="size-4"></i> Gestiune furnizori
                </a>
                <a href="/admin/furnizori#price-logic" class="besoiu-btn-secondary inline-flex items-center gap-2">
                    <i data-lucide="calculator" class="size-4"></i> Logică preț
                </a>
            </div>
        </div>

        <div class="bws-metrics bws-grid--thirds">
            <div class="bws-metric">
                <div class="bws-metric__label">Furnizori activi</div>
                <div class="bws-metric__value" id="bws-fz-active">—</div>
                <div class="bws-metric__hint" id="bws-fz-active-hint">din total configurat</div>
            </div>
            <div class="bws-metric bws-metric--warn">
                <div class="bws-metric__label">Conexiuni eșuate</div>
                <div class="bws-metric__value" id="bws-fz-failed">—</div>
                <div class="bws-metric__hint">test FTP/API</div>
            </div>
            <div class="bws-metric">
                <div class="bws-metric__label">Produse importate</div>
                <div class="bws-metric__value" id="bws-fz-products">—</div>
                <div class="bws-metric__hint">sumă pe furnizori</div>
            </div>
        </div>

        <div class="bws-charts bcd-mt">
            <div class="bws-charts-row bws-charts-row--1">
                <div class="bws-chart-card">
                    <div class="bws-chart-card__head">
                        <div>
                            <h3>Furnizori configurați</h3>
                            <p>Status, tip conexiune și ultimele scanări.</p>
                        </div>
                        <a href="/admin/furnizori" class="bcd-link">Vezi toți</a>
                    </div>
                    <div class="bws-chart-card__body" id="bws-table-furnizori">
                        <div class="bws-empty">Se încarcă…</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
