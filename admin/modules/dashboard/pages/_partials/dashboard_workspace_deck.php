<?php
declare(strict_types=1);

/** @var string $dashboardWorkspace */
/** @var callable $dashShow */

use Besoiu\Core\AdminUrl;

if (!$dashShow('workspace-deck')) {
    return;
}

$bovsoftImportUrl = '/admin/public/bovsoft-import/Procesare%20fisier%20import%20Base.html';
$cronSyncUrl = AdminUrl::navPath('cron');

$deckIntro = match ($dashboardWorkspace) {
    'orders' => 'Flux comercial în timp real: comenzi noi, venituri zilnice, distribuție status și ultimele tranzacții din magazin.',
    'suppliers' => 'Sănătatea catalogului: stoc, coadă import, calitate date și coduri căutate dar absente din ofertă.',
    'marketing' => 'Statistici căutări site — volum, rată succes, top OEM. Planificarea (indicatori, acțiuni, conținut) e în meniul Analiză.',
    'ai' => 'Stare agenți, boți și integrări: monitorizare TecDoc, backup și alerte critice de automatizare.',
    'social' => 'Mesagerie unificată: volum pe canale, conversații recente și status răspunsuri.',
    'shop' => 'Conținut digital Besoiu: pagini CMS, vitrină homepage, blog și produse active pe site.',
    default => 'Indicatori pentru zona de lucru activă.',
};

$metricIds = match ($dashboardWorkspace) {
    'orders' => ['bws-m-orders-today', 'bws-m-revenue-today', 'bws-m-orders-total', 'bws-m-orders-new'],
    'suppliers' => ['bws-m-prod-total', 'bws-m-prod-active', 'bws-m-queue', 'bws-m-no-image'],
    'marketing' => ['bws-m-search-total', 'bws-m-search-today', 'bws-m-not-found', 'bws-m-success-rate'],
    'ai' => ['bws-m-bots', 'bws-m-flags', 'bws-m-tecdoc', 'bws-m-backup'],
    'social' => ['bws-m-msg-total', 'bws-m-top-channel'],
    'shop' => ['bws-m-pages', 'bws-m-vitrina', 'bws-m-blog', 'bws-m-products'],
    default => ['bws-m-1', 'bws-m-2', 'bws-m-3', 'bws-m-4'],
};
$metricLabels = match ($dashboardWorkspace) {
    'orders' => ['Comenzi azi', 'Vânzări azi', 'Total comenzi', 'Comenzi noi'],
    'suppliers' => ['Produse totale', 'Produse active', 'Coadă import', 'Fără imagine'],
    'marketing' => ['Căutări totale', 'Căutări azi', 'Negăsite', 'Rată succes'],
    'ai' => ['Boți configurați', 'Alerte active', 'TecDoc API', 'Ultimul backup'],
    'social' => ['Mesaje total', 'Canal principal'],
    'shop' => ['Pagini CMS', 'Vitrină homepage', 'Articole blog', 'Produse active'],
    default => ['KPI 1', 'KPI 2', 'KPI 3', 'KPI 4'],
};

$activeMetrics = [];
foreach ($metricLabels as $i => $label) {
    if ($label === '—') {
        continue;
    }
    $activeMetrics[] = ['id' => $metricIds[$i], 'label' => $label, 'index' => $i];
}

$metricCount = count($activeMetrics);
$metricsGridClass = match (true) {
    $metricCount >= 4, $metricCount === 2 => 'bws-grid--halves',
    $metricCount === 3 => 'bws-grid--thirds',
    default => 'bws-grid--full',
};
?>
<div class="col-span-12" data-besoiu-dash-panel="workspace-deck">
    <div class="bws-deck">
        <p class="bws-deck__intro"><?= htmlspecialchars($deckIntro, ENT_QUOTES, 'UTF-8') ?></p>

        <div class="bws-metrics <?= htmlspecialchars($metricsGridClass, ENT_QUOTES, 'UTF-8') ?>">
            <?php foreach ($activeMetrics as $metric): ?>
            <div class="bws-metric">
                <div class="bws-metric__label"><?= htmlspecialchars($metric['label'], ENT_QUOTES, 'UTF-8') ?></div>
                <div class="bws-metric__value" id="<?= htmlspecialchars($metric['id'], ENT_QUOTES, 'UTF-8') ?>">—</div>
                <div class="bws-metric__hint" id="<?= htmlspecialchars($metric['id'], ENT_QUOTES, 'UTF-8') ?>-hint">Se încarcă…</div>
                <?php if ($dashboardWorkspace === 'orders' && $metric['index'] === 0): ?>
                <div class="bws-metric__spark" id="bws-spark-orders"></div>
                <?php elseif ($dashboardWorkspace === 'orders' && $metric['index'] === 1): ?>
                <div class="bws-metric__spark" id="bws-spark-revenue"></div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>

        <?php if ($dashboardWorkspace === 'suppliers'): ?>
        <div class="bws-import-pipeline">
            <div class="bws-import-pipeline__head">
                <h3>Import &amp; automatizare</h3>
                <p>Pipeline Bovsoft/Base → import site → coadă review → publish. Cron scan furnizori din <code>admin/storage/supplier_feeds/</code>.</p>
            </div>
            <div class="bws-import-pipeline__grid">
                <a href="/admin/import" class="bws-import-pipeline__card">
                    <span class="bws-import-pipeline__icon" aria-hidden="true"><i data-lucide="upload"></i></span>
                    <strong>Import CSV / Excel</strong>
                    <span>Upload TecDoc + furnizor, joburi chunk, validare pre-publish.</span>
                </a>
                <a href="<?= htmlspecialchars($bovsoftImportUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" class="bws-import-pipeline__card bws-import-pipeline__card--bovsoft">
                    <span class="bws-import-pipeline__icon" aria-hidden="true"><i data-lucide="layers"></i></span>
                    <strong>Procesare Base Bovsoft</strong>
                    <span>Matching preț Autototal/Autonet/Materom/Elit, export XLSX + missing_prices.</span>
                </a>
                <a href="/admin/importreview" class="bws-import-pipeline__card">
                    <span class="bws-import-pipeline__icon" aria-hidden="true"><i data-lucide="list-checks"></i></span>
                    <strong>Coadă import</strong>
                    <span>Review conflicte, exclude/restore, publicare în catalog magazin.</span>
                </a>
                <a href="<?= htmlspecialchars($cronSyncUrl, ENT_QUOTES, 'UTF-8') ?>" class="bws-import-pipeline__card">
                    <span class="bws-import-pipeline__icon" aria-hidden="true"><i data-lucide="refresh-cw"></i></span>
                    <strong>Cron Sync</strong>
                    <span>Scan automat furnizori, sync FTP, import dual vitrină + catalog.</span>
                </a>
            </div>
        </div>
        <?php endif; ?>

        <div class="bws-charts">
            <?php if ($dashboardWorkspace === 'orders'): ?>
            <div class="bws-charts-row bws-charts-row--1">
                <div class="bws-chart-card">
                    <div class="bws-chart-card__head">
                        <div><h3>Comenzi — ultimele 14 zile</h3><p>Volume zilnice de comenzi noi din magazin.</p></div>
                        <span class="bws-chart-card__tag">Bar chart</span>
                    </div>
                    <div class="bws-chart-card__body" id="bws-chart-orders-bars"><div class="bws-empty">Se încarcă graficul…</div></div>
                </div>
            </div>
            <div class="bws-charts-row bws-charts-row--2">
                <div class="bws-chart-card">
                    <div class="bws-chart-card__head">
                        <div><h3>Status comenzi</h3><p>Distribuție pe stări operaționale.</p></div>
                        <span class="bws-chart-card__tag">Donut</span>
                    </div>
                    <div class="bws-chart-card__body" id="bws-chart-status-donut"><div class="bws-empty">Se încarcă…</div></div>
                </div>
                <div class="bws-chart-card">
                    <div class="bws-chart-card__head">
                        <div><h3>Venituri zilnice</h3><p>RON încasați per zi (comenzi site).</p></div>
                        <span class="bws-chart-card__tag">Area</span>
                    </div>
                    <div class="bws-chart-card__body" id="bws-chart-revenue-area"><div class="bws-empty">Se încarcă…</div></div>
                </div>
            </div>
            <div class="bws-charts-row bws-charts-row--1">
                <div class="bws-chart-card">
                    <div class="bws-chart-card__head">
                        <div><h3>Ultimele comenzi</h3><p>Tabel detaliat — client, canal, status, sumă.</p></div>
                        <span class="bws-chart-card__tag">Tabel</span>
                    </div>
                    <div class="bws-chart-card__body" id="bws-table-orders"><div class="bws-empty">Se încarcă…</div></div>
                </div>
            </div>

            <?php if (class_exists(\Besoiu\Core\Module\ModuleGate::class) && \Besoiu\Core\Module\ModuleGate::enabled('furnizori')): ?>
            <div class="bws-import-pipeline bws-import-pipeline--furnizori">
                <div class="bws-import-pipeline__head">
                    <h3>Furnizori B2B</h3>
                    <p>Feed-uri, sincronizare și logică de formare preț la import.</p>
                    <div class="bws-deck__actions" style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap">
                        <a href="/admin/furnizori" class="besoiu-btn-secondary inline-flex items-center gap-2 text-sm">Gestiune furnizori</a>
                        <a href="/admin/furnizori#price-logic" class="besoiu-btn-secondary inline-flex items-center gap-2 text-sm">Logică preț</a>
                    </div>
                </div>
                <div class="bws-metrics bws-grid--thirds" style="margin-top:12px">
                    <div class="bws-metric">
                        <div class="bws-metric__label">Furnizori activi</div>
                        <div class="bws-metric__value" id="bws-fz-active">—</div>
                        <div class="bws-metric__hint" id="bws-fz-active-hint">Se încarcă…</div>
                    </div>
                    <div class="bws-metric">
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
                <div class="bws-charts-row bws-charts-row--1" style="margin-top:12px">
                    <div class="bws-chart-card">
                        <div class="bws-chart-card__head">
                            <div><h3>Furnizori configurați</h3><p>Status, conexiune, ultimele scanări.</p></div>
                            <a href="/admin/furnizori" class="bcd-link">Vezi toți</a>
                        </div>
                        <div class="bws-chart-card__body" id="bws-table-furnizori"><div class="bws-empty">Se încarcă…</div></div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php elseif ($dashboardWorkspace === 'suppliers'): ?>
            <div class="bws-charts-row bws-charts-row--2">
                <div class="bws-chart-card">
                    <div class="bws-chart-card__head">
                        <div><h3>Joburi import</h3><p>Stare procesare feed-uri furnizori.</p></div>
                        <span class="bws-chart-card__tag">Donut</span>
                    </div>
                    <div class="bws-chart-card__body" id="bws-chart-import-donut"></div>
                </div>
                <div class="bws-chart-card">
                    <div class="bws-chart-card__head">
                        <div><h3>Calitate catalog</h3><p>Produse cu imagine / OEM vs incomplete.</p></div>
                        <span class="bws-chart-card__tag">Donut</span>
                    </div>
                    <div class="bws-chart-card__body" id="bws-chart-quality-donut"></div>
                </div>
            </div>
            <div class="bws-charts-row bws-charts-row--1">
                <div class="bws-chart-card">
                    <div class="bws-chart-card__head">
                        <div><h3>Coduri căutate — lipsă din stoc</h3><p>Oportunități import din Search Log.</p></div>
                        <span class="bws-chart-card__tag">Tabel</span>
                    </div>
                    <div class="bws-chart-card__body" id="bws-table-missing"></div>
                </div>
            </div>
            <div class="bws-charts-row bws-charts-row--1">
                <div class="bws-chart-card">
                    <div class="bws-chart-card__head">
                        <div><h3>Alerte furnizori &amp; import</h3><p>Maximum 2 alerte pe rând.</p></div>
                        <span class="bws-chart-card__tag">Alerte</span>
                    </div>
                    <div class="bws-chart-card__body bws-health-grid" id="bws-supplier-flags"></div>
                </div>
            </div>

            <?php elseif ($dashboardWorkspace === 'marketing'): ?>
            <div class="bws-charts-row bws-charts-row--1">
                <div class="bws-chart-card">
                    <div class="bws-chart-card__head">
                        <div><h3>Volum căutări / zi</h3><p>Total evenimente Search Log pe 14 zile.</p></div>
                        <span class="bws-chart-card__tag">Bar</span>
                    </div>
                    <div class="bws-chart-card__body" id="bws-chart-search-bars"></div>
                </div>
            </div>
            <div class="bws-charts-row bws-charts-row--2">
                <div class="bws-chart-card">
                    <div class="bws-chart-card__head">
                        <div><h3>Găsite vs negăsite</h3><p>Verde = găsit, galben = lipsă stoc.</p></div>
                        <span class="bws-chart-card__tag">Stacked</span>
                    </div>
                    <div class="bws-chart-card__body" id="bws-chart-search-split"></div>
                </div>
                <div class="bws-chart-card">
                    <div class="bws-chart-card__head">
                        <div><h3>Rată succes</h3><p>Procent căutări cu rezultat în stoc.</p></div>
                        <span class="bws-chart-card__tag">KPI</span>
                    </div>
                    <div class="bws-chart-card__body">
                        <div class="bws-metric" style="box-shadow:none;border:none;padding:8px 0">
                            <div class="bws-metric__label">Rată găsite</div>
                            <div class="bws-metric__value" id="bws-m-success-rate-inline">—</div>
                            <div class="bws-metric__hint">Actualizat la sincronizare dashboard</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="bws-charts-row bws-charts-row--1">
                <div class="bws-chart-card">
                    <div class="bws-chart-card__head">
                        <div><h3>Top coduri OEM căutate</h3></div>
                        <span class="bws-chart-card__tag">Tabel</span>
                    </div>
                    <div class="bws-chart-card__body" id="bws-table-oem"></div>
                </div>
            </div>

            <?php elseif ($dashboardWorkspace === 'ai'): ?>
            <div class="bws-charts-row bws-charts-row--1">
                <div class="bws-chart-card">
                    <div class="bws-chart-card__head">
                        <div><h3>Boți &amp; canale</h3><p>WhatsApp, Facebook, BaseLinker — stare token și test.</p></div>
                        <span class="bws-chart-card__tag">Tabel</span>
                    </div>
                    <div class="bws-chart-card__body" id="bws-table-bots"></div>
                </div>
            </div>
            <div class="bws-charts-row bws-charts-row--1">
                <div class="bws-chart-card">
                    <div class="bws-chart-card__head">
                        <div><h3>Red flags &amp; integrări</h3><p>Alerte critice — 2 pe rând.</p></div>
                        <span class="bws-chart-card__tag">Alerte</span>
                    </div>
                    <div class="bws-chart-card__body bws-health-grid" id="bws-ai-flags"></div>
                </div>
            </div>

            <?php elseif ($dashboardWorkspace === 'social'): ?>
            <div class="bws-charts-row bws-charts-row--2">
                <div class="bws-chart-card">
                    <div class="bws-chart-card__head">
                        <div><h3>Mesaje pe canal</h3><p>Distribuție conversații.</p></div>
                        <span class="bws-chart-card__tag">Donut</span>
                    </div>
                    <div class="bws-chart-card__body" id="bws-chart-channels"></div>
                </div>
                <div class="bws-chart-card">
                    <div class="bws-chart-card__head">
                        <div><h3>Rezumat mesagerie</h3><p>Total și canal dominant.</p></div>
                        <span class="bws-chart-card__tag">KPI</span>
                    </div>
                    <div class="bws-chart-card__body">
                        <div class="bws-metrics bws-grid--halves" style="gap:12px">
                            <div class="bws-metric" style="box-shadow:none">
                                <div class="bws-metric__label">Total mesaje</div>
                                <div class="bws-metric__value" id="bws-m-msg-total-dup">—</div>
                            </div>
                            <div class="bws-metric" style="box-shadow:none">
                                <div class="bws-metric__label">Canal principal</div>
                                <div class="bws-metric__value" id="bws-m-top-channel-dup" style="font-size:1.25rem">—</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="bws-charts-row bws-charts-row--1">
                <div class="bws-chart-card">
                    <div class="bws-chart-card__head">
                        <div><h3>Conversații recente</h3><p>Preview mesaje și status bot.</p></div>
                        <span class="bws-chart-card__tag">Tabel</span>
                    </div>
                    <div class="bws-chart-card__body" id="bws-table-messages"></div>
                </div>
            </div>

            <?php elseif ($dashboardWorkspace === 'shop'): ?>
            <div class="bws-charts-row bws-charts-row--2">
                <div class="bws-chart-card">
                    <div class="bws-chart-card__head">
                        <div><h3>Mix conținut site</h3><p>Pagini, vitrină, blog, produse.</p></div>
                        <span class="bws-chart-card__tag">Donut</span>
                    </div>
                    <div class="bws-chart-card__body" id="bws-chart-shop-mix"></div>
                </div>
                <div class="bws-chart-card">
                    <div class="bws-chart-card__head">
                        <div><h3>Rezumat digital</h3><p>CMS și conținut public.</p></div>
                        <span class="bws-chart-card__tag">Info</span>
                    </div>
                    <div class="bws-chart-card__body">
                        <p class="bws-deck__intro">Folosește acțiunile rapide pentru editare pagini, vitrină sau listă produse.</p>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
