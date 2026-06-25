<?php

declare(strict_types=1);

use Evasystem\Core\AdminUrl;

$siteHost = parse_url(AdminUrl::siteBaseUrl(), PHP_URL_HOST) ?: 'besoiupieseauto.ro';

?>
<link rel="stylesheet" href="/admin/public/assets/css/scan-furnizori.css?v=16">

<div class="scan-page scan-page--fullbleed scan-page--cron" id="cron-sync-page">
    <div class="scan-hero">
        <div class="scan-hero__brand">
            <img src="/admin/Templates/admin/dist/images/logo.svg" alt="Besoiu Piese Auto" class="scan-hero__logo" width="48" height="48">
            <div class="scan-hero__text">
                <p class="scan-hero__eyebrow">Automatizare · <?= htmlspecialchars($siteHost, ENT_QUOTES, 'UTF-8') ?></p>
                <h1 class="scan-hero__title">Cron Sync — motor automatizări</h1>
                <p class="scan-hero__desc">
                    Pune CSV-urile în <code>admin/storage/supplier_feeds/{FURNIZOR}/</code>, apoi apasă
                    <strong>Scanează furnizori</strong>. <strong>Mod dual (implicit):</strong> scanează
                    <em>toate</em> fișierele — <strong>ulei + lichide</strong> → vitrină homepage (max
                    <strong>8</strong>);                     <strong>piese auto</strong> (plăcuțe, discuri, filtre…) → magazin cu categorie.
                    Mod vechi consumabile: <code>CRON_IMPORT_MODE=consumables</code>. Mod rapid: fără TecDoc greu pe produs.
                    Limită implicită catalog: <strong>20</strong>/rulare (<code>CRON_CATALOG_LIMIT</code>).
                    Dacă scanul rămâne blocat → <strong>Deblochează</strong>.
                </p>
            </div>
        </div>
        <div class="scan-hero__actions">
            <span class="scan-live-pill" id="cron-live-pill"><span class="scan-live-pill__dot"></span> Live</span>
            <button type="button" class="scan-btn scan-btn--ghost" id="cron-refresh">
                <i class="bi bi-arrow-clockwise"></i> Reîncarcă
            </button>
            <button type="button" class="scan-btn scan-btn--danger" id="cron-reset-all" title="Golește jurnalul, deblochează scan blocat și resetează statisticile.">
                <i class="bi bi-trash3"></i> Reset tot
            </button>
            <button type="button" class="scan-btn scan-btn--ghost" id="cron-unlock-scan" title="Eliberează scan blocat fără a goli jurnalul.">
                <i class="bi bi-unlock"></i> Deblochează
            </button>
            <button type="button" class="scan-btn scan-btn--stop is-idle" id="cron-stop-scan" disabled title="Oprește scanarea în curs (import, validare, FTP). Activ când rulează un scan.">
                <i class="bi bi-stop-fill"></i> Oprește scan
            </button>
            <button type="button" class="scan-btn scan-btn--primary" id="cron-run-local">
                <i class="bi bi-play-fill"></i> Scanează furnizori
            </button>
            <button type="button" class="scan-btn scan-btn--accent" id="cron-run-remote">
                <i class="bi bi-cloud-download"></i> Sync FTP + scan
            </button>
        </div>
    </div>

    <div class="scan-stats scan-animate-in" id="cron-stats" style="--scan-delay: 80ms">
        <div class="scan-stat-card scan-stat-card--accent">
            <span class="scan-stat-card__icon" aria-hidden="true"><i class="bi bi-building"></i></span>
            <span class="scan-stat-card__label">Furnizori activi</span>
            <strong class="scan-stat-card__value" data-cron-stat="suppliers">—</strong>
        </div>
        <div class="scan-stat-card scan-stat-card--ok">
            <span class="scan-stat-card__icon" aria-hidden="true"><i class="bi bi-file-earmark-check"></i></span>
            <span class="scan-stat-card__label">Cu fișiere gata</span>
            <strong class="scan-stat-card__value" data-cron-stat="files_ready">—</strong>
        </div>
        <div class="scan-stat-card scan-stat-card--ok">
            <span class="scan-stat-card__icon" aria-hidden="true"><i class="bi bi-patch-check"></i></span>
            <span class="scan-stat-card__label">CSV validate</span>
            <strong class="scan-stat-card__value" data-cron-stat="validated_ok">—</strong>
        </div>
        <div class="scan-stat-card scan-stat-card--warn">
            <span class="scan-stat-card__icon" aria-hidden="true"><i class="bi bi-exclamation-triangle"></i></span>
            <span class="scan-stat-card__label">Necesită atenție</span>
            <strong class="scan-stat-card__value" data-cron-stat="needs_attention">—</strong>
        </div>
        <div class="scan-stat-card scan-stat-card--info">
            <span class="scan-stat-card__icon" aria-hidden="true"><i class="bi bi-diagram-3"></i></span>
            <span class="scan-stat-card__label">Modele noi</span>
            <strong class="scan-stat-card__value" data-cron-stat="new_models_total">—</strong>
        </div>
    </div>

    <div id="cron-sync-dashboard">
    <?php
    $cronDashVariant = 'cron';
    $cronDashSecondaryHref = '/admin/furnizori';
    $cronDashSecondaryLabel = 'Furnizori →';
    $cronDashShowActions = false;
    require dirname(__DIR__) . '/_partials/cron-sync-dashboard.php';
    ?>
    </div>

    <div id="cron-toast" class="scan-toast hidden" role="status"></div>
</div>

<script src="/admin/public/assets/js/cron-sync-dashboard.js?v=23"></script>
<script>
(function () {
    'use strict';
    if (!window.BpaCronSyncDashboard) return;
    BpaCronSyncDashboard.init({
        apiAction: 'cron',
        mirror: false,
        analyze: false,
        refreshMs: 120000
    });
})();
</script>
