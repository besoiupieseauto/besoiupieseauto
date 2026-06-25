<?php

/** @var string $cronDashSecondaryHref */
/** @var string $cronDashSecondaryLabel */
/** @var bool $cronDashShowActions */
/** @var string $cronDashVariant cron|scan */

$cronDashSecondaryHref = $cronDashSecondaryHref ?? '';
$cronDashSecondaryLabel = $cronDashSecondaryLabel ?? '';
$cronDashShowActions = (bool) ($cronDashShowActions ?? true);
$cronDashVariant = $cronDashVariant ?? 'cron';
$cronDashTitle = $cronDashVariant === 'cron'
    ? 'Monitorizare live'
    : 'Cron Sync — motor automatizări';

?>
<section class="scan-cron-dashboard scan-animate-in" aria-label="Cron Sync și progres scanare" style="--scan-delay: 160ms">
    <header class="scan-cron-dashboard__head">
        <div class="scan-cron-dashboard__head-main">
            <div class="scan-section-badge">Pas 1</div>
            <h2 class="scan-cron-dashboard__title">
                <i class="bi bi-broadcast scan-cron-dashboard__title-icon" aria-hidden="true"></i>
                <?= htmlspecialchars($cronDashTitle, ENT_QUOTES, 'UTF-8') ?>
            </h2>
            <div class="scan-motor-badges" id="cron-motor-badges">
                <span class="scan-motor-badge scan-motor-badge--pending" id="cron-motor-badge">
                    <span class="scan-motor-badge__dot"></span> Se încarcă…
                </span>
            </div>
            <p class="scan-cron-dashboard__sub" id="cron-engine-status">Verificare motor cron și furnizori…</p>
        </div>
        <div class="scan-cron-dashboard__meta">
            <?php if ($cronDashShowActions): ?>
            <div class="scan-cron-dashboard__quick-actions">
                <button type="button" class="scan-btn scan-btn--xs scan-btn--ghost" id="cron-dash-refresh">Reîncarcă</button>
                <button type="button" class="scan-btn scan-btn--xs scan-btn--stop is-idle" id="cron-dash-stop-scan" disabled title="Oprește scanarea">Oprește</button>
                <button type="button" class="scan-btn scan-btn--xs scan-btn--primary" id="cron-dash-scan-local">Scanează</button>
                <button type="button" class="scan-btn scan-btn--xs scan-btn--accent" id="cron-dash-scan-remote">Sync FTP</button>
            </div>
            <?php else: ?>
            <div class="scan-cron-dashboard__quick-actions">
                <button type="button" class="scan-btn scan-btn--xs scan-btn--stop is-idle" id="cron-dash-stop-scan" disabled title="Oprește scanarea în curs">
                    <i class="bi bi-stop-fill"></i> Oprește scan
                </button>
            </div>
            <?php endif; ?>
            <span class="scan-meta-pill" id="cron-last-run">Actualizat: —</span>
            <?php if (trim($cronDashSecondaryHref) !== '' && trim($cronDashSecondaryLabel) !== ''): ?>
            <a href="<?= htmlspecialchars($cronDashSecondaryHref, ENT_QUOTES, 'UTF-8') ?>" class="scan-link"><?= htmlspecialchars($cronDashSecondaryLabel, ENT_QUOTES, 'UTF-8') ?></a>
            <?php endif; ?>
        </div>
    </header>

    <div class="scan-run-progress-panel" id="cron-run-progress-panel" hidden aria-live="polite">
        <div class="scan-run-progress-panel__head">
            <div>
                <span class="scan-section-badge scan-section-badge--live">Live</span>
                <h3 class="scan-run-progress-panel__title"><i class="bi bi-hourglass-split"></i> Progres scanare</h3>
            </div>
            <div class="scan-run-progress-panel__actions">
                <strong class="scan-run-progress-panel__pct" id="cron-scan-progress-pct">0%</strong>
                <button type="button" class="scan-btn scan-btn--xs scan-btn--ghost scan-run-progress-dismiss" id="cron-scan-progress-dismiss" hidden title="Ascunde panoul (rămâne în jurnal)">
                    <i class="bi bi-x-lg"></i> Ascunde
                </button>
            </div>
        </div>
        <div class="scan-run-progress-steps" id="cron-scan-progress-steps" role="list">
            <span class="scan-run-progress-step" data-phase="run">① Pornire</span>
            <span class="scan-run-progress-step" data-phase="validate">② Validare CSV</span>
            <span class="scan-run-progress-step" data-phase="sync">③ Import</span>
            <span class="scan-run-progress-step" data-phase="done">④ Gata</span>
        </div>
        <div class="scan-cron-progress scan-cron-progress--hero">
            <div class="scan-cron-progress__bar"><span id="cron-scan-progress-fill"></span></div>
            <p class="scan-cron-progress__label" id="cron-scan-progress-label">Se rulează scanarea…</p>
            <p class="scan-cron-progress__meta" id="cron-scan-progress-meta"></p>
        </div>
        <div class="scan-supplier-progress-track" id="cron-supplier-progress-track" hidden></div>
        <div class="scan-run-issues" id="cron-scan-issues" hidden>
            <div class="scan-run-issues__head">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <strong>Probleme detectate</strong>
                <span class="scan-run-issues__count" id="cron-scan-issues-count"></span>
            </div>
            <ul class="scan-run-issues__list" id="cron-scan-issues-list"></ul>
        </div>
    </div>

    <div class="scan-live-status scan-live-status--cards" id="cron-live-status" aria-live="polite">
        <div class="scan-live-status__item scan-live-status__item--time">
            <span class="scan-live-status__icon"><i class="bi bi-clock-history"></i></span>
            <div class="scan-live-status__body">
                <span class="scan-live-status__label">Ultima scanare</span>
                <strong class="scan-live-status__value" id="cron-live-last-scan">—</strong>
            </div>
        </div>
        <div class="scan-live-status__item scan-live-status__item--next">
            <span class="scan-live-status__icon"><i class="bi bi-alarm"></i></span>
            <div class="scan-live-status__body">
                <span class="scan-live-status__label">Următoarea</span>
                <strong class="scan-live-status__value" id="cron-live-next-scan">—</strong>
            </div>
        </div>
        <div class="scan-live-status__item scan-live-status__item--due">
            <span class="scan-live-status__icon"><i class="bi bi-lightning-charge"></i></span>
            <div class="scan-live-status__body">
                <span class="scan-live-status__label">De rulat acum</span>
                <strong class="scan-live-status__value" id="cron-live-due">—</strong>
            </div>
        </div>
        <div class="scan-live-status__item scan-live-status__item--result">
            <span class="scan-live-status__icon"><i class="bi bi-pie-chart"></i></span>
            <div class="scan-live-status__body">
                <span class="scan-live-status__label">Rezultat</span>
                <strong class="scan-live-status__value" id="cron-live-summary">—</strong>
            </div>
        </div>
    </div>

    <div class="scan-cron-console scan-animate-in" id="cron-console-wrap" style="--scan-delay: 120ms">
        <div class="scan-cron-console__head">
            <div>
                <span class="scan-section-badge">Jurnal</span>
                <h3 class="scan-cron-console__title"><i class="bi bi-terminal"></i> Jurnal acțiuni (live)</h3>
                <p class="scan-cron-console__hint">La fel ca pe pagina Import — vezi pas cu pas ce face motorul (folder, FTP, validare, import).</p>
            </div>
            <button type="button" class="scan-btn scan-btn--xs scan-btn--ghost" id="cron-console-clear">Curăță afișaj</button>
        </div>
        <div class="scan-cron-console__log" id="cron-console-log" role="log" aria-live="polite">
            <div class="scan-cron-console__empty">Apasă «Scanează furnizori» — jurnalul se actualizează live în timpul rulării.</div>
        </div>
    </div>

    <div class="scan-cron-grid scan-cron-grid--split">
        <div class="scan-cron-col scan-cron-col--suppliers scan-panel-card">
            <div class="scan-panel-card__head">
                <span class="scan-section-badge">Pas 2</span>
                <h3 class="scan-cron-col__title"><i class="bi bi-truck"></i> Furnizori — scanat &amp; următorul pas</h3>
            </div>
            <div class="scan-table-wrap scan-table-wrap--animated">
                <table class="scan-table scan-suppliers-live-table">
                    <thead>
                    <tr>
                        <th>Furnizor</th>
                        <th>Program</th>
                        <th>Ultima</th>
                        <th>Următoarea</th>
                        <th>Ce s-a găsit</th>
                        <th>Stare</th>
                    </tr>
                    </thead>
                    <tbody id="cron-suppliers-live">
                    <tr><td colspan="6" class="scan-empty"><span class="scan-skeleton-line"></span> Se încarcă furnizorii…</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="scan-cron-col scan-cron-col--feed scan-panel-card">
            <div class="scan-panel-card__head">
                <span class="scan-section-badge">Live</span>
                <h3 class="scan-cron-col__title"><i class="bi bi-activity"></i> Jurnal evenimente</h3>
            </div>
            <div class="scan-activity-feed scan-activity-feed--live" id="cron-activity-feed">
                <div class="scan-activity-empty">Jurnal gol.</div>
            </div>
        </div>
    </div>

    <div class="scan-cron-jobs-panel scan-panel-card scan-animate-in" style="--scan-delay: 240ms">
        <div class="scan-cron-jobs-panel__head">
            <span class="scan-section-badge">Pas 3</span>
            <div>
                <h3 class="scan-cron-jobs-panel__title"><i class="bi bi-clock-history"></i> Joburi Windows (Task Scheduler)</h3>
                <p class="scan-cron-jobs-panel__hint">Scripturi <code>admin/scripts/run_*.bat</code></p>
            </div>
        </div>
        <div class="scan-table-wrap">
            <table class="scan-table scan-cron-jobs-table">
                <thead>
                <tr>
                    <th>Job</th>
                    <th>Categorie</th>
                    <th>Program</th>
                    <th>Script</th>
                    <th>Stare</th>
                </tr>
                </thead>
                <tbody id="cron-cron-jobs">
                <tr><td colspan="5" class="scan-empty">Niciun job Task Scheduler.</td></tr>
                </tbody>
            </table>
        </div>
        <p class="scan-cron-footnote">
            <code>admin/docs/CRON_WINDOWS_SETUP.md</code> ·
            <code>php admin/tools/verify_cron_setup.php</code>
        </p>
    </div>
</section>
