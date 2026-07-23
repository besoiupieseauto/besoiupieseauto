<?php declare(strict_types=1);

use Besoiu\Core\AdminUrl;
use Besoiu\Services\AdaosComercial\AdaosComercialService;

$importApiUrl = AdminUrl::api('import_endpoint.php');
$asyncClientUrl = AdminUrl::asset('js/besoiu-async-client.js');
$bovsoftImportUrl = '/admin/public/bovsoft-import/Procesare%20fisier%20import%20Base.html';
$importMarkupRules = (new AdaosComercialService())->getAll();
?>
<style>
.import-page {
    --ip-teal: #0d9488;
    --ip-teal-dark: #0f766e;
    --ip-ink: #0f172a;
    --ip-muted: #64748b;
    --ip-line: #e2e8f0;
    --ip-bg: #f8fafc;
    --ip-radius: 16px;
    --ip-shadow: 0 1px 2px rgba(15, 23, 42, .05), 0 8px 24px rgba(15, 23, 42, .04);
}
.import-hero {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 20px;
    flex-wrap: wrap;
    margin-top: 2rem;
    margin-bottom: 1.5rem;
}
.import-hero__actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}
.import-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 10px 18px;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 700;
    line-height: 1.2;
    border: 1px solid transparent;
    cursor: pointer;
    text-decoration: none;
    transition: transform .15s ease, box-shadow .15s ease, background .15s ease;
}
.import-btn:hover { transform: translateY(-1px); }
.import-btn:disabled, .import-btn.is-disabled {
    opacity: .45;
    cursor: not-allowed;
    transform: none;
    box-shadow: none !important;
}
.import-btn--primary {
    background: linear-gradient(135deg, #0d9488, #059669);
    color: #fff;
    box-shadow: 0 4px 14px rgba(13, 148, 136, .25);
}
.import-btn--purple {
    background: linear-gradient(135deg, #0d9488, #0f766e);
    color: #fff;
    box-shadow: 0 4px 14px rgba(124, 58, 237, .22);
}
.import-btn--secondary {
    background: #fff;
    color: #334155;
    border-color: var(--ip-line);
}
.import-btn--ghost {
    background: transparent;
    color: var(--ip-teal-dark);
    border-color: #99f6e4;
}
.import-btn--danger {
    background: #fff;
    color: #dc2626;
    border-color: #fecaca;
    padding: 7px 12px;
    font-size: 12px;
}
.import-btn--sm { padding: 7px 12px; font-size: 12px; }
.import-btn--lg { padding: 12px 26px; font-size: 15px; }
.import-label-btn {
    display: inline-flex;
    cursor: pointer;
}
.import-label-btn input { display: none; }
.import-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 20px;
}
@media (min-width: 1024px) {
    .import-grid--2 { grid-template-columns: 1.1fr .9fr; }
    .import-split { grid-template-columns: 1fr 1fr; }
}
.import-card {
    background: #fff;
    border: 1px solid var(--ip-line);
    border-radius: var(--ip-radius);
    box-shadow: var(--ip-shadow);
    overflow: hidden;
}
.import-card__head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
    padding: 18px 20px 14px;
    border-bottom: 1px solid var(--ip-line);
    background: linear-gradient(180deg, var(--ip-bg) 0%, #fff 100%);
}
.import-card__title {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 15px;
    font-weight: 800;
    color: var(--ip-ink);
}
.import-card__step {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 26px;
    height: 26px;
    border-radius: 999px;
    background: var(--ip-teal);
    color: #fff;
    font-size: 12px;
    font-weight: 800;
}
.import-card__desc {
    margin: 6px 0 0;
    font-size: 12px;
    color: var(--ip-muted);
    line-height: 1.5;
    max-width: 640px;
}
.import-card__body { padding: 18px 20px 20px; }
.import-split {
    display: grid;
    grid-template-columns: 1fr;
    gap: 16px;
}
.import-panel {
    border: 1px solid var(--ip-line);
    border-radius: 12px;
    padding: 14px;
    background: var(--ip-bg);
}
.import-panel__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 12px;
}
.import-panel__title {
    font-size: 13px;
    font-weight: 800;
    color: #334155;
}
.import-panel__actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    align-items: center;
}
.import-file-list {
    display: grid;
    gap: 8px;
    min-height: 40px;
}
.import-file-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 10px 12px;
    background: #fff;
    border: 1px solid var(--ip-line);
    border-radius: 10px;
}
.import-file-row__name {
    font-size: 13px;
    font-weight: 600;
    color: var(--ip-ink);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.import-file-row__meta {
    font-size: 11px;
    color: var(--ip-muted);
    margin-top: 3px;
}
.import-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 999px;
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .03em;
}
.import-badge--ok { background: #dcfce7; color: #166534; }
.import-badge--warn { background: #fef3c7; color: #b45309; }
.import-badge--teal { background: #ccfbf1; color: #0f766e; }
.import-badge--blue { background: #ccfbf1; color: #0f766e; }
.import-field {
    display: grid;
    gap: 6px;
    font-size: 12px;
    color: #334155;
    font-weight: 600;
}
.import-field input,
.import-field select {
    padding: 10px 12px;
    border: 1px solid var(--ip-line);
    border-radius: 10px;
    font-size: 13px;
    font-weight: 500;
    background: #fff;
    color: var(--ip-ink);
}
.import-fields { display: grid; gap: 12px; }
.import-ready-pills {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 14px;
}
.import-ready-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 700;
    border: 1px solid var(--ip-line);
    background: #fff;
    color: var(--ip-muted);
}
.import-ready-pill.is-ok { border-color: #a7f3d0; background: #ecfdf5; color: #047857; }
.import-ready-pill.is-bad { border-color: #fecaca; background: #fef2f2; color: #b91c1c; }
.import-ready-pill.is-neutral { border-color: #e2e8f0; background: #f8fafc; color: #64748b; }
.import-progress {
    margin-top: 14px;
    padding: 12px 14px;
    border: 1px solid var(--ip-line);
    border-radius: 12px;
    background: #fff;
}
.import-progress__top {
    display: flex;
    justify-content: space-between;
    font-size: 12px;
    color: var(--ip-muted);
    margin-bottom: 6px;
    gap: 10px;
}
.import-progress__bar {
    width: 100%;
    height: 8px;
    background: #e2e8f0;
    border-radius: 999px;
    overflow: hidden;
}
.import-progress__fill {
    height: 100%;
    border-radius: 999px;
    background: linear-gradient(90deg, #0d9488, #059669);
    transition: width .2s ease;
}
.import-progress__fill--job {
    background: linear-gradient(90deg, #0d9488, #14b8a6);
}
.import-progress__detail {
    font-size: 11px;
    color: #94a3b8;
    margin-top: 6px;
}
.import-library-row {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 12px;
    background: #fff;
    border: 1px solid #ccfbf1;
    border-radius: 10px;
    cursor: pointer;
}
.import-library-row__name {
    font-size: 13px;
    font-weight: 600;
    color: var(--ip-ink);
}
.import-library-row__meta {
    font-size: 11px;
    color: var(--ip-muted);
    margin-top: 3px;
}
.import-empty {
    font-size: 12px;
    color: #94a3b8;
    padding: 8px 4px;
}
.import-details {
    margin-top: 14px;
    border: 1px dashed #cbd5e1;
    border-radius: 12px;
    padding: 10px 12px;
    background: #fff;
}
.import-details summary {
    cursor: pointer;
    font-size: 12px;
    font-weight: 700;
    color: var(--ip-teal-dark);
}
.import-debug {
    margin-top: 16px;
    background: #0f172a;
    color: #e2e8f0;
    border-radius: 12px;
    padding: 12px 14px;
    display: none;
}
.import-debug__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 8px;
}
.import-debug__log {
    font-family: Consolas, monospace;
    font-size: 11px;
    line-height: 1.5;
    max-height: 200px;
    overflow: auto;
}
.import-preview-toolbar {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    flex-wrap: wrap;
    padding: 18px 20px 14px;
    border-bottom: 1px solid var(--ip-line);
    background: linear-gradient(180deg, var(--ip-bg) 0%, #fff 100%);
}
.import-preview-title {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 15px;
    font-weight: 800;
    color: var(--ip-ink);
}
.import-preview-stats { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 8px; }
.import-preview-actions {
    display: flex;
    gap: 10px;
    align-items: center;
    flex-wrap: wrap;
}
.import-stat-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 11px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 600;
    border: 1px solid var(--ip-line);
    background: #fff;
    color: var(--ip-muted);
}
.import-stat-chip strong { color: var(--ip-ink); font-weight: 800; }
.import-stat-chip--priced { border-color: #a7f3d0; background: #ecfdf5; color: #047857; }
.import-stat-chip--priced strong { color: #065f46; }
.import-stat-chip--missing { border-color: #fecaca; background: #fef2f2; color: #b91c1c; }
.import-stat-chip--missing strong { color: #991b1b; }
.import-price-filters {
    display: inline-flex;
    padding: 4px;
    border-radius: 12px;
    background: #f1f5f9;
    border: 1px solid var(--ip-line);
    gap: 4px;
}
.import-price-filters button {
    padding: 7px 14px;
    border: none;
    border-radius: 9px;
    background: transparent;
    font-size: 12px;
    font-weight: 600;
    color: var(--ip-muted);
    cursor: pointer;
}
.import-price-filters button.is-active {
    background: #fff;
    color: var(--ip-teal);
    box-shadow: 0 1px 3px rgba(15, 23, 42, .08);
}
.import-preview-table-wrap {
    overflow: auto;
    max-height: 520px;
}
#previewTable thead th.import-col-price {
    background: #f8fafc;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .04em;
}
#previewTable tbody td.import-col-price {
    background: linear-gradient(90deg, rgba(240, 253, 250, .35) 0%, transparent 100%);
    vertical-align: middle;
}
.import-price-stack { display: flex; flex-direction: column; align-items: flex-end; gap: 4px; min-width: 108px; }
.import-price-chip { display: inline-flex; align-items: baseline; gap: 4px; border-radius: 10px; line-height: 1; white-space: nowrap; }
.import-price-chip--final {
    padding: 8px 12px;
    background: linear-gradient(135deg, #0d9488, #059669, #10b981);
    color: #fff;
    box-shadow: 0 4px 14px rgba(13, 148, 136, .28);
}
.import-price-chip__amount { font-size: 15px; font-weight: 800; font-variant-numeric: tabular-nums; }
.import-price-chip__cur { font-size: 10px; font-weight: 700; opacity: .88; letter-spacing: .06em; }
.import-price-chip--base {
    padding: 3px 8px;
    font-size: 11px;
    font-weight: 600;
    color: var(--ip-muted);
    background: #f8fafc;
    border: 1px dashed #cbd5e1;
    font-variant-numeric: tabular-nums;
}
.import-price-chip--delta {
    padding: 2px 7px;
    font-size: 10px;
    font-weight: 700;
    color: #b45309;
    background: #fffbeb;
    border: 1px solid #fde68a;
    border-radius: 999px;
}
.import-price-chip--missing {
    padding: 7px 11px;
    font-size: 11px;
    font-weight: 700;
    color: #b91c1c;
    background: #fef2f2;
    border: 1px solid #fecaca;
}
.import-supplier-cell { display: flex; flex-direction: column; gap: 3px; }
.import-supplier-cell__name { font-weight: 600; color: #334155; }
.import-supplier-cell__match { font-size: 10px; color: var(--ip-muted); font-family: ui-monospace, Consolas, monospace; }
#import-status {
    margin: 16px 20px;
    padding: 12px 16px;
    border-radius: 10px;
    display: none;
}
</style>
<div class="-mt-5 import-page">
    <div class="import-hero">
        <div>
            <h2 class="text-lg font-semibold text-slate-900">Import produse</h2>
            <p class="mt-1 text-sm text-slate-500 max-w-2xl">TecDoc + preț furnizor → preview → coadă → publicare pe site.</p>
        </div>
        <div class="import-hero__actions">
            <a href="<?= htmlspecialchars($bovsoftImportUrl, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" class="import-btn import-btn--ghost import-btn--sm">Procesor Base (offline)</a>
            <a href="/admin/importreview" class="import-btn import-btn--secondary import-btn--sm">Coadă import</a>
        </div>
    </div>

    <div class="import-grid import-grid--2">
        <!-- Pas 1: Sursă date (fără duplicate) -->
        <section class="import-card">
            <div class="import-card__head">
                <div>
                    <div class="import-card__title"><span class="import-card__step">1</span> Sursă date</div>
                    <p class="import-card__desc">Listele furnizor aduc prețul. Biblioteca TecDoc aduce produsul (CSV permanent pe server).</p>
                </div>
                <button type="button" class="import-btn import-btn--secondary import-btn--sm" onclick="refreshImportSources()">Reîncarcă</button>
            </div>
            <div class="import-card__body">
                <div class="import-split">
                    <div class="import-panel">
                        <div class="import-panel__head">
                            <span class="import-panel__title">Liste preț furnizori</span>
                            <label class="import-label-btn import-btn import-btn--primary import-btn--sm">
                                Încarcă CSV/XLSX
                                <input type="file" id="supplierFiles" accept=".csv,.xlsx,.txt" multiple onchange="handleRoleUpload('supplier', this)">
                            </label>
                        </div>
                        <div id="supplier-files-list" class="import-file-list">
                            <div class="import-empty">Se încarcă fișierele furnizor…</div>
                        </div>
                        <div id="progress-wrap" class="import-progress" style="display:none;">
                            <div class="import-progress__top">
                                <span id="progress-label">Se încarcă fișierul…</span>
                                <span id="progress-pct">0%</span>
                            </div>
                            <div class="import-progress__bar"><div id="progress-bar" class="import-progress__fill" style="width:0%;"></div></div>
                            <div id="progress-size" class="import-progress__detail"></div>
                        </div>
                    </div>

                    <div class="import-panel">
                        <div class="import-panel__head">
                            <span class="import-panel__title">Bibliotecă TecDoc</span>
                            <div class="import-panel__actions">
                                <label class="import-field" style="margin:0;font-weight:600;">
                                    <span style="display:flex;align-items:center;gap:6px;cursor:pointer;">
                                        <input type="checkbox" id="tecdocLibrarySelectAll" checked onchange="toggleTecdocLibraryAll(this.checked)"> Toate
                                    </span>
                                </label>
                                <label class="import-label-btn import-btn import-btn--secondary import-btn--sm">
                                    Brand nou
                                    <input type="file" id="tecdocFile" accept=".csv,.xlsx" onchange="handleRoleUpload('tecdoc', this)">
                                </label>
                            </div>
                        </div>
                        <div id="tecdoc-library-list" class="import-file-list">
                            <div class="import-empty">Se încarcă biblioteca…</div>
                        </div>
                        <div id="tecdoc-pending-list" class="import-file-list" style="margin-top:10px;display:none;"></div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Pas 2: Configurare & generare -->
        <section class="import-card">
            <div class="import-card__head">
                <div>
                    <div class="import-card__title"><span class="import-card__step">2</span> Configurare & generare</div>
                    <p class="import-card__desc">TecDoc = produs, furnizor = preț. Alege filtrele și generează lista pentru coadă.</p>
                </div>
            </div>
            <div class="import-card__body">
                <div class="import-fields">
                    <label class="import-field">
                        <span>Filtru brand (opțional)</span>
                        <input type="text" id="brandFilter" placeholder="ex: GATES, BOSCH">
                    </label>
                    <label class="import-field">
                        <span>Limită preview</span>
                        <select id="maxPreview">
                            <option value="100">100 produse</option>
                            <option value="250">250 produse</option>
                            <option value="500" selected>500 produse</option>
                        </select>
                    </label>
                </div>

                <div id="step-status" class="import-ready-pills"></div>

                <button type="button" id="generateBtn" class="import-btn import-btn--purple import-btn--lg is-disabled" onclick="generateProductList()" disabled>
                    Generează lista de produse
                </button>
                <div id="file-names" class="import-progress__detail" style="margin-top:10px;"></div>

                <div id="job-progress-wrap" class="import-progress" style="display:none;">
                    <div class="import-progress__top">
                        <span id="job-progress-label">Procesare în fundal…</span>
                        <div style="display:flex;align-items:center;gap:10px;">
                            <span id="job-progress-pct">0%</span>
                            <button type="button" id="jobCancelBtn" class="import-btn import-btn--danger import-btn--sm" onclick="cancelActiveJob()" style="display:none;">Oprește</button>
                        </div>
                    </div>
                    <div class="import-progress__bar"><div id="job-progress-bar" class="import-progress__fill import-progress__fill--job" style="width:0%;"></div></div>
                    <div id="job-progress-detail" class="import-progress__detail"></div>
                </div>
            </div>
        </section>
    </div>

    <!-- Debug (collapsible) -->
    <details class="import-details" style="margin-top:16px;">
        <summary>Jurnal debug (upload / job)</summary>
        <div id="import-debug" class="import-debug" style="display:block;margin-top:10px;">
            <div class="import-debug__head">
                <strong style="font-size:12px;">Evenimente</strong>
                <button type="button" class="import-btn import-btn--secondary import-btn--sm" onclick="clearDebugLog()">Curăță</button>
            </div>
            <div id="import-debug-log" class="import-debug__log"></div>
        </div>
    </details>

    <!-- Pas 3: Preview & trimitere coadă -->
    <section class="import-card" id="preview-section" style="display:none;margin-top:20px;">
        <div class="import-preview-toolbar">
            <div>
                <div class="import-preview-title"><span class="import-card__step">3</span> Preview & trimitere în coadă</div>
                <div id="preview-count" class="import-preview-stats"></div>
            </div>
            <div class="import-preview-actions">
                <label style="font-size:13px;color:#64748b;cursor:pointer;display:flex;align-items:center;gap:6px;" onclick="toggleSelectAll()">
                    <input type="checkbox" id="selectAllCb" checked style="width:16px;height:16px;"> Selectează tot
                </label>
                <div class="import-price-filters" role="group" aria-label="Filtru preț">
                    <button type="button" onclick="setPriceFilter('all')" id="filterAllBtn" class="is-active">Toate</button>
                    <button type="button" onclick="setPriceFilter('priced')" id="filterPricedBtn">Cu preț</button>
                    <button type="button" onclick="setPriceFilter('missing')" id="filterMissingBtn">Fără preț</button>
                </div>
                <label class="import-field" style="min-width:200px;margin:0;">
                    <span>Regulă adaos</span>
                    <select id="importMarkupRuleId">
                        <option value="">Fără regulă (doar TVA)</option>
                        <?php foreach ($importMarkupRules as $importRuleRow): ?>
                            <?php
                            $importRuleId = (int) ($importRuleRow['id'] ?? 0);
                            if ($importRuleId <= 0) continue;
                            $importRuleName = trim((string) ($importRuleRow['name'] ?? ('Regulă #' . $importRuleId)));
                            $importRuleActive = (int) ($importRuleRow['is_active'] ?? 0) === 1;
                            ?>
                            <option value="<?= htmlspecialchars((string) $importRuleId, ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars($importRuleName, ENT_QUOTES, 'UTF-8') ?><?= $importRuleActive ? '' : ' (inactivă)' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <button type="button" onclick="importSelected()" id="importBtn" class="import-btn import-btn--primary">
                    Trimite în coadă
                </button>
            </div>
        </div>

        <div class="import-preview-table-wrap">
            <table style="width:100%;text-align:left;font-size:13px;border-collapse:collapse;" id="previewTable">
                <thead style="position:sticky;top:0;z-index:2;">
                    <tr style="background:#f9fafb;border-bottom:1px solid #e5e7eb;">
                        <th style="padding:10px 12px;width:40px;text-align:center;">✓</th>
                        <th style="padding:10px 12px;">#</th>
                        <th style="padding:10px 12px;">Cod</th>
                        <th style="padding:10px 12px;">Denumire</th>
                        <th style="padding:10px 12px;">Brand</th>
                        <th style="padding:10px 12px;">Marcă auto</th>
                        <th style="padding:10px 12px;">Model</th>
                        <th style="padding:10px 12px;">Motorizare</th>
                        <th class="import-col-price" style="padding:10px 12px;text-align:right;">Preț final</th>
                        <th class="import-col-price" style="padding:10px 12px;text-align:right;">Preț bază</th>
                        <th style="padding:10px 12px;">Furnizor preț</th>
                        <th style="padding:10px 12px;">Regulă adaos</th>
                        <th style="padding:10px 12px;">TecDoc</th>
                        <th style="padding:10px 12px;">Caracteristici</th>
                        <th style="padding:10px 12px;">Categorie</th>
                        <th style="padding:10px 12px;">Subcategorie</th>
                        <th style="padding:10px 12px;">OEM</th>
                        <th style="padding:10px 12px;">KM</th>
                        <th style="padding:10px 12px;">Date tehnice</th>
                        <th style="padding:10px 12px;">Stoc</th>
                    </tr>
                </thead>
                <tbody id="previewBody"></tbody>
            </table>
        </div>
        <div id="import-status"></div>
    </section>
</div>

<script src="<?= htmlspecialchars($asyncClientUrl, ENT_QUOTES, 'UTF-8') ?>"></script>
<script>
const IMPORT_URL = <?= json_encode($importApiUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const IMPORT_MODE = 'tecdoc_master';
const CHUNK_SIZE = 5 * 1024 * 1024;
const JOB_FETCH_TIMEOUT_MS = 120000;
const IMPORT_QUEUE_STEP_TIMEOUT_MS = 300000;
const IMPORT_QUEUE_CHUNK_SIZE = 20;
let parsedProducts = [];
let uploadedFilesMeta = [];
let cachedUploadedFiles = [];
let cachedTecdocLibrary = [];
let selectedTecdocLibraryKeys = new Set();
let priceFilter = 'all';
let activeJobAbort = false;
let activeJobId = '';
let activeJobController = null;

function setJobProgress(visible, pct = 0, label = '', detail = '') {
    const wrap = document.getElementById('job-progress-wrap');
    const bar = document.getElementById('job-progress-bar');
    const pctEl = document.getElementById('job-progress-pct');
    const labelEl = document.getElementById('job-progress-label');
    const detailEl = document.getElementById('job-progress-detail');
    const cancelBtn = document.getElementById('jobCancelBtn');
    if (!wrap || !bar || !pctEl || !labelEl || !detailEl) return;
    wrap.style.display = visible ? 'block' : 'none';
    if (cancelBtn) cancelBtn.style.display = visible ? 'inline-block' : 'none';
    const safePct = Math.max(0, Math.min(100, Number(pct) || 0));
    bar.style.width = safePct + '%';
    pctEl.textContent = safePct.toFixed(1).replace(/\.0$/, '') + '%';
    labelEl.textContent = label || 'Procesare în fundal...';
    detailEl.textContent = detail || '';
}

async function cancelJobOnServer(jobId) {
    if (!jobId) return;
    try {
        await fetch(IMPORT_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ mode: 'import_job_cancel', job_id: jobId })
        });
    } catch (e) {
        debugLog('Nu am putut opri job-ul pe server: ' + e.message, 'warn');
    }
}

async function stopAllBackgroundJobs(showAlert = true) {
    activeJobAbort = true;
    if (activeJobController) {
        activeJobController.abort();
        activeJobController = null;
    }
    if (activeJobId) {
        await cancelJobOnServer(activeJobId);
        activeJobId = '';
    }
    try {
        const res = await fetch(IMPORT_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ mode: 'import_job_cancel_all' })
        });
        const json = await parseJsonResponse(res, 'Oprire joburi');
        if (showAlert && json.message) {
            debugLog(json.message, 'warn');
        }
    } catch (e) {
        debugLog('Oprire joburi: ' + e.message, 'warn');
    }
    setJobProgress(false);
    resetJobButtons();
}

function cancelActiveJob() {
    stopAllBackgroundJobs(false).then(() => {
        debugLog('Proces oprit.', 'warn');
        alert('Proces oprit. Poți relua importul când vrei.');
    });
}

function resetJobButtons() {
    const generateBtn = document.getElementById('generateBtn');
    if (generateBtn) {
        generateBtn.disabled = false;
        generateBtn.textContent = 'Generează lista de produse';
    }
    const importBtn = document.getElementById('importBtn');
    if (importBtn) {
        importBtn.disabled = false;
        importBtn.textContent = 'Trimite în coadă';
    }
}

async function parseJsonResponse(res, contextLabel) {
    const raw = await res.text();
    if (!raw.trim()) {
        throw new Error('Serverul a returnat răspuns gol la ' + contextLabel);
    }
    try {
        return JSON.parse(raw);
    } catch (e) {
        debugLog(contextLabel + ': JSON invalid', 'error');
        debugLog(raw.slice(0, 500), 'warn');
        if (raw.includes('524') || raw.toLowerCase().includes('timeout')) {
            throw new Error('Timeout server (524). Pasul a durat prea mult — reîncearcă cu mai puține produse sau contactează hosting (limită ~120s/request).');
        }
        throw new Error('Răspuns JSON invalid la ' + contextLabel);
    }
}

function jobFetchSignal(parentSignal, timeoutMs) {
    const ms = timeoutMs || JOB_FETCH_TIMEOUT_MS;
    if (typeof AbortSignal !== 'undefined' && typeof AbortSignal.timeout === 'function') {
        return AbortSignal.timeout(ms);
    }
    return parentSignal || undefined;
}

async function runBackgroundJob(startMode, stepMode, startPayload, contextLabel, options = {}) {
    const onProgress = typeof options.onProgress === 'function' ? options.onProgress : null;
    const stepTimeoutMs = options.stepTimeoutMs || JOB_FETCH_TIMEOUT_MS;
    const showGlobalProgress = options.showGlobalProgress !== false;
    const customStartFn = typeof options.startFn === 'function' ? options.startFn : null;

    activeJobAbort = false;
    activeJobId = '';
    activeJobController = new AbortController();
    if (showGlobalProgress) {
        setJobProgress(true, 1, 'Pornesc ' + contextLabel + '...', '');
    }
    if (onProgress) {
        onProgress({ progress: 1, message: 'Pornesc ' + contextLabel + '...', phase: 'start' });
    }

    let jobId = '';
    if (customStartFn) {
        jobId = await customStartFn({
            signal: activeJobController.signal,
            timeoutMs: stepTimeoutMs,
            onProgress: onProgress,
            showGlobalProgress: showGlobalProgress
        });
        if (!jobId) {
            throw new Error('Nu am putut porni job-ul.');
        }
    } else {
        const startRes = await fetch(IMPORT_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(Object.assign({ mode: startMode }, startPayload)),
            signal: jobFetchSignal(activeJobController.signal, stepTimeoutMs)
        });
        const startJson = await parseJsonResponse(startRes, contextLabel + ' (start)');
        if (!startJson.success || !startJson.job_id) {
            throw new Error(startJson.message || 'Nu am putut porni job-ul.');
        }
        jobId = startJson.job_id;
    }

    activeJobId = jobId;
    debugLog(contextLabel + ' pornit (job ' + jobId + ')', 'info');

    const stepKind = stepMode === 'preview_job_step' ? 'preview' : 'import_queue';
    const timeoutSec = Math.max(60, Math.ceil((stepTimeoutMs || JOB_FETCH_TIMEOUT_MS) / 1000));

    const asyncCreate = await BesoiuAsync.submitJob({
        type: 'import.process_step',
        queue: 'import',
        payload: {
            legacy_job_id: jobId,
            step_kind: stepKind,
        },
        timeout_sec: timeoutSec,
        signal: activeJobController.signal,
    });

    if (!asyncCreate.success || !asyncCreate.job_id) {
        throw new Error(asyncCreate.message || 'Nu am putut porni procesarea în fundal.');
    }

    return new Promise((resolve, reject) => {
        let settled = false;
        const finish = (fn, value) => {
            if (settled) return;
            settled = true;
            activeJobId = '';
            activeJobController = null;
            fn(value);
        };

        const watch = BesoiuAsync.watchJob(asyncCreate.job_id, {
            signal: activeJobController.signal,
            onStatus: (snap) => {
                if (activeJobAbort) {
                    watch.close();
                    finish(reject, new Error('Proces oprit.'));
                    return;
                }
                const progress = Number(snap.progress || 0);
                const message = String(snap.message || 'Procesare...');
                if (showGlobalProgress) {
                    setJobProgress(
                        true,
                        progress,
                        message,
                        snap.phase ? ('Fază: ' + snap.phase) : ''
                    );
                }
                if (onProgress) {
                    onProgress({ progress, message, phase: snap.phase || '' }, snap);
                }
            },
            onDone: (snap) => {
                if (showGlobalProgress) {
                    setJobProgress(true, 100, String(snap.message || 'Finalizat.'), '');
                }
                debugLog(contextLabel + ' finalizat.', 'ok');
                const result = (snap.result && typeof snap.result === 'object') ? snap.result : {};
                finish(resolve, Object.assign({ success: true, job_id: jobId, status: snap.status || null }, result));
            },
            onFailed: (snap) => {
                finish(reject, new Error(String(snap.error || snap.message || 'Job eșuat.')));
            },
            onError: (err) => {
                finish(reject, err instanceof Error ? err : new Error(String(err)));
            },
        });
    });
}

function parsePriceNumber(val) {
    const raw = String(val ?? '').trim().replace(/\s/g, '').replace(',', '.');
    if (raw === '') return null;
    const n = Number.parseFloat(raw.replace(/[^\d.-]/g, ''));
    return Number.isFinite(n) && n > 0 ? n : null;
}

function formatPriceRon(amount) {
    return new Intl.NumberFormat('ro-RO', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(amount);
}

function formatPrice(val) {
    const n = parsePriceNumber(val);
    return n !== null ? formatPriceRon(n) + ' RON' : '—';
}

function renderPriceFinalCell(product) {
    const final = parsePriceNumber(product?.pPrice);
    const base = parsePriceNumber(product?.pBasePrice);
    if (final === null) {
        return '<span class="import-price-chip import-price-chip--missing">Fără preț</span>';
    }
    let html = '<div class="import-price-stack">';
    html += '<span class="import-price-chip import-price-chip--final">';
    html += '<span class="import-price-chip__amount">' + esc(formatPriceRon(final)) + '</span>';
    html += '<span class="import-price-chip__cur">RON</span></span>';
    if (base !== null && Math.abs(base - final) > 0.009) {
        const pct = base > 0 ? Math.round(((final - base) / base) * 100) : 0;
        if (pct > 0) {
            html += '<span class="import-price-chip import-price-chip--delta" title="Adaos față de preț bază">+' + pct + '%</span>';
        }
    }
    html += '</div>';
    return html;
}

function renderPriceBaseCell(product) {
    const base = parsePriceNumber(product?.pBasePrice);
    if (base === null) {
        return '<span class="import-price-chip import-price-chip--missing" style="opacity:.75;">—</span>';
    }
    return '<span class="import-price-chip import-price-chip--base">' + esc(formatPriceRon(base)) + ' RON</span>';
}

function renderPreviewPriceStats(total, visible, priced, missing) {
    const avg = priced > 0
        ? parsedProducts.filter(hasProductPrice).reduce((sum, p) => sum + (parsePriceNumber(p.pPrice) || 0), 0) / priced
        : 0;
    const avgLabel = priced > 0 ? formatPriceRon(avg) + ' RON medie' : '— medie';
    return ''
        + '<span class="import-stat-chip">Afișate <strong>' + visible + '</strong> / ' + total + '</span>'
        + '<span class="import-stat-chip import-stat-chip--priced">Cu preț <strong>' + priced + '</strong></span>'
        + '<span class="import-stat-chip import-stat-chip--missing">Fără preț <strong>' + missing + '</strong></span>'
        + '<span class="import-stat-chip" title="Preț final mediu (produse cu preț)">' + esc(avgLabel) + '</span>';
}

function hasProductPrice(product) {
    return parsePriceNumber(product?.pPrice) !== null;
}

function setPriceFilter(mode) {
    priceFilter = mode;
    ['all', 'priced', 'missing'].forEach(key => {
        const btn = document.getElementById('filter' + key.charAt(0).toUpperCase() + key.slice(1) + 'Btn');
        if (!btn) return;
        const active = (key === 'all' && mode === 'all')
            || (key === 'priced' && mode === 'priced')
            || (key === 'missing' && mode === 'missing');
        btn.classList.toggle('is-active', active);
    });
    renderPreview();
}

function debugLog(message, type = 'info') {
    const wrap = document.getElementById('import-debug');
    const log = document.getElementById('import-debug-log');
    if (!wrap || !log) return;
    const color = type === 'error'
        ? '#fca5a5'
        : type === 'ok'
            ? '#86efac'
            : type === 'warn'
                ? '#fcd34d'
                : '#99f6e4';
    log.innerHTML += `<div style="color:${color}">[${new Date().toLocaleTimeString('ro-RO')}] ${esc(message)}</div>`;
    log.scrollTop = log.scrollHeight;
}

function clearDebugLog() {
    const log = document.getElementById('import-debug-log');
    if (log) log.innerHTML = '';
}

function isTecdocFile(file) {
    if (file.resolved_role === 'tecdoc' || file.upload_role === 'tecdoc') return true;
    if (file.file_kind === 'tecdoc') return true;
    const name = String(file.original_name || '').toLowerCase();
    return name.includes('tableusecarsforparts')
        || name.includes('universal-csv-data')
        || name.includes('tecdoc');
}

function isSupplierFile(file) {
    if (file.resolved_role === 'supplier' || file.upload_role === 'supplier') return true;
    return typeof file.file_kind === 'string' && file.file_kind.startsWith('supplier:');
}

function roleBadge(file) {
    if (isTecdocFile(file)) {
        return '<span class="import-badge import-badge--blue">TecDoc</span>';
    }
    if (isSupplierFile(file)) {
        return '<span class="import-badge import-badge--teal">Furnizor</span>';
    }
    return '<span class="import-badge import-badge--warn">Alt</span>';
}

function renderFileItem(file) {
    const status = file.completed
        ? '<span class="import-badge import-badge--ok">complet</span>'
        : '<span class="import-badge import-badge--warn">incomplet</span>';
    const kind = file.file_kind_label ? ' · ' + esc(file.file_kind_label) : '';
    return `
        <div class="import-file-row">
            <div style="min-width:0;flex:1;">
                <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:4px;">
                    ${roleBadge(file)}
                    <div class="import-file-row__name">${esc(file.original_name)}</div>
                </div>
                <div class="import-file-row__meta">${formatSize(file.size)} · ${esc(file.updated_at)} · ${status}${kind}</div>
            </div>
            <button type="button" class="import-btn import-btn--danger" onclick="deleteUploadedFile('${String(file.file_id).replace(/'/g, "\\'")}', '${String(file.original_name).replace(/'/g, "\\'")}')">Șterge</button>
        </div>
    `;
}

async function refreshImportSources() {
    await loadUploadedFiles();
    await loadTecdocLibrary();
}

function getSelectedTecdocLibraryKeys() {
    return Array.from(selectedTecdocLibraryKeys);
}

function toggleTecdocLibraryAll(checked) {
    selectedTecdocLibraryKeys = new Set();
    if (checked) {
        cachedTecdocLibrary.forEach(entry => selectedTecdocLibraryKeys.add(entry.key));
    }
    renderTecdocLibraryList();
    updateStepStatus();
}

function onTecdocLibraryToggle(key, checked) {
    if (checked) {
        selectedTecdocLibraryKeys.add(key);
    } else {
        selectedTecdocLibraryKeys.delete(key);
    }
    const selectAll = document.getElementById('tecdocLibrarySelectAll');
    if (selectAll) {
        selectAll.checked = cachedTecdocLibrary.length > 0
            && selectedTecdocLibraryKeys.size === cachedTecdocLibrary.length;
    }
    updateStepStatus();
}

function renderTecdocLibraryList() {
    const list = document.getElementById('tecdoc-library-list');
    if (!list) return;

    if (!cachedTecdocLibrary.length) {
        list.innerHTML = '<div class="import-empty">Biblioteca e goală. Încarcă CSV TecDoc (brand nou) sau rulează sync din procesorul Base.</div>';
        return;
    }

    list.innerHTML = cachedTecdocLibrary.map(entry => {
        const checked = selectedTecdocLibraryKeys.has(entry.key);
        const brand = entry.brand ? `<span class="import-badge import-badge--blue">${esc(entry.brand)}</span>` : '';
        return `
            <label class="import-library-row">
                <input type="checkbox" class="tecdoc-library-check" data-library-key="${esc(entry.key)}" ${checked ? 'checked' : ''} onchange="onTecdocLibraryToggle('${String(entry.key).replace(/'/g, "\\'")}', this.checked)">
                <div style="min-width:0;flex:1;">
                    <div style="display:flex;align-items:center;flex-wrap:wrap;gap:6px;margin-bottom:4px;">
                        ${brand}
                        <span class="import-library-row__name">${esc(entry.name)}</span>
                    </div>
                    <div class="import-library-row__meta">${formatSize(entry.size)} · ${esc(entry.updated_at)} · permanent</div>
                </div>
            </label>
        `;
    }).join('');
}

async function loadTecdocLibrary() {
    const list = document.getElementById('tecdoc-library-list');
    try {
        const res = await fetch(IMPORT_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ mode: 'list_tecdoc_library' })
        });
        const json = await res.json();
        if (!json.success || !Array.isArray(json.library)) {
            cachedTecdocLibrary = [];
            if (list) list.innerHTML = '<div style="font-size:12px;color:#dc2626;">Nu am putut încărca biblioteca TecDoc.</div>';
            updateStepStatus();
            return;
        }

        cachedTecdocLibrary = json.library;
        if (selectedTecdocLibraryKeys.size === 0 && cachedTecdocLibrary.length > 0) {
            cachedTecdocLibrary.forEach(entry => selectedTecdocLibraryKeys.add(entry.key));
        } else {
            const validKeys = new Set(cachedTecdocLibrary.map(e => e.key));
            selectedTecdocLibraryKeys = new Set(
                Array.from(selectedTecdocLibraryKeys).filter(key => validKeys.has(key))
            );
        }

        if (json.promoted_from_uploads > 0) {
            debugLog('Promovate în bibliotecă: ' + json.promoted_from_uploads + ' fișiere TecDoc din upload.', 'ok');
        }

        renderTecdocLibraryList();
        updateStepStatus();
    } catch (e) {
        debugLog('Bibliotecă TecDoc: ' + e.message, 'error');
        if (list) {
            list.innerHTML = '<div style="font-size:12px;color:#dc2626;">Eroare: ' + esc(e.message) + '</div>';
        }
    }
}

function updateStepStatus() {
    const tecdocLibraryCount = getSelectedTecdocLibraryKeys().length;
    const supplierFiles = cachedUploadedFiles.filter(f => f.completed && isSupplierFile(f));
    const statusEl = document.getElementById('step-status');
    const generateBtn = document.getElementById('generateBtn');
    if (!statusEl || !generateBtn) return;

    const supplierOk = supplierFiles.length > 0;
    const tecdocOk = tecdocLibraryCount > 0;
    const ready = supplierOk && tecdocOk;

    statusEl.innerHTML = `
        <span class="import-ready-pill ${supplierOk ? 'is-ok' : 'is-bad'}">${supplierOk ? '✓' : '✗'} Furnizori: ${supplierFiles.length}</span>
        <span class="import-ready-pill ${tecdocOk ? 'is-ok' : 'is-bad'}">${tecdocOk ? '✓' : '✗'} TecDoc: ${tecdocLibraryCount}${cachedTecdocLibrary.length ? ' / ' + cachedTecdocLibrary.length : ''}</span>
    `;

    generateBtn.disabled = !ready;
    generateBtn.classList.toggle('is-disabled', !ready);
}

async function loadUploadedFiles() {
    const supplierList = document.getElementById('supplier-files-list');
    const tecdocPending = document.getElementById('tecdoc-pending-list');
    if (!supplierList) return;

    try {
        const res = await fetch(IMPORT_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ mode: 'list_uploaded' })
        });
        const json = await res.json();
        if (!json.success || !Array.isArray(json.files)) {
            cachedUploadedFiles = [];
            supplierList.innerHTML = '<div class="import-empty">Niciun fișier furnizor încărcat.</div>';
            if (tecdocPending) {
                tecdocPending.style.display = 'none';
                tecdocPending.innerHTML = '';
            }
            updateStepStatus();
            return;
        }

        cachedUploadedFiles = json.files;
        const completedFiles = json.files.filter(f => f.completed);
        const tecdocFiles = completedFiles.filter(isTecdocFile);
        const supplierFiles = completedFiles.filter(isSupplierFile);

        supplierList.innerHTML = supplierFiles.length
            ? supplierFiles.map(f => renderFileItem(f)).join('')
            : '<div class="import-empty">Niciun fișier furnizor. Încarcă Autonet, Materom, Elit…</div>';

        if (tecdocPending) {
            if (tecdocFiles.length) {
                tecdocPending.style.display = 'grid';
                tecdocPending.innerHTML = '<div class="import-empty" style="font-weight:700;color:#64748b;padding-bottom:4px;">Upload recent (promovare automată în bibliotecă)</div>'
                    + tecdocFiles.map(f => renderFileItem(f)).join('');
            } else {
                tecdocPending.style.display = 'none';
                tecdocPending.innerHTML = '';
            }
        }

        updateStepStatus();
        loadTecdocLibrary();
    } catch (e) {
        debugLog('Nu am putut încărca lista fișierelor: ' + e.message, 'error');
        supplierList.innerHTML = '<div class="import-empty" style="color:#dc2626;">Eroare: ' + esc(e.message) + '</div>';
    }
}

async function fetchUploadedFilesMeta() {
    const res = await fetch(IMPORT_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ mode: 'list_uploaded' })
    });
    const json = await res.json();
    if (!json.success || !Array.isArray(json.files)) {
        return [];
    }
    return json.files
        .filter(file => file.completed && isSupplierFile(file))
        .map(file => ({
            file_id: file.file_id,
            original_name: file.original_name,
            file_kind: file.file_kind || '',
            upload_role: file.upload_role || ''
        }));
}

async function previewUploadedFiles(fileMetas, options = {}) {
    const previewRes = await fetch(IMPORT_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            mode: 'preview_uploaded',
            uploaded_files: fileMetas,
            brand_filter: options.brandFilter || '',
            max_preview: options.maxPreview || 500,
            force_supplier_api: !!options.forceSupplierApi,
            skip_tecdoc_csv_scan: options.skipTecdocCsvScan !== false
        })
    });
    const previewRaw = await previewRes.text();
    if (!previewRaw.trim()) {
        throw new Error('Serverul a returnat răspuns gol la preview');
    }
    let previewJson;
    try {
        previewJson = JSON.parse(previewRaw);
    } catch (e) {
        debugLog('Preview: JSON invalid', 'error');
        debugLog(previewRaw.slice(0, 500), 'warn');
        throw new Error('Răspuns JSON invalid la preview');
    }
    if (!previewJson.success) {
        throw new Error(previewJson.message || 'Nu am putut genera preview-ul');
    }
    return previewJson;
}

async function generateProductList() {
    const generateBtn = document.getElementById('generateBtn');
    const fileNames = document.getElementById('file-names');
    const supplierFiles = cachedUploadedFiles.filter(f => f.completed && isSupplierFile(f));
    const tecdocLibraryKeys = getSelectedTecdocLibraryKeys();

    if (supplierFiles.length === 0) {
        alert('Încarcă listele furnizor (Autonet, Elit) — necesare pentru preț.');
        return;
    }

    if (tecdocLibraryKeys.length === 0) {
        alert('Selectează cel puțin un brand din biblioteca TecDoc (sau încarcă un CSV nou).');
        return;
    }

    const brandFilter = String(document.getElementById('brandFilter')?.value || '').trim();
    const maxPreview = parseInt(document.getElementById('maxPreview')?.value || '500', 10) || 500;

    try {
        if (generateBtn) {
            generateBtn.disabled = true;
            generateBtn.textContent = 'Procesare TecDoc + preț furnizor…';
        }
        debugLog('Generare: TecDoc (produs) + furnizor (preț)…', 'info');

        const allFiles = await fetchUploadedFilesMeta();
        const previewJson = await runBackgroundJob(
            'preview_job_start',
            'preview_job_step',
            {
                uploaded_files: allFiles,
                brand_filter: brandFilter,
                max_preview: maxPreview,
                tecdoc_max_rows_per_code: 30,
                import_mode: IMPORT_MODE,
                require_supplier_price: true,
                tecdoc_library_keys: tecdocLibraryKeys
            },
            'Generare listă produse',
            { stepTimeoutMs: IMPORT_QUEUE_STEP_TIMEOUT_MS }
        );
        uploadedFilesMeta = allFiles;

        if (fileNames) {
            fileNames.textContent = previewJson.message || ((previewJson.products || []).length + ' produse gata de import');
            fileNames.style.color = '#059669';
        }

        parsedProducts = previewJson.products || [];
        renderPreview();
        debugLog('Lista de produse generată cu succes.', 'ok');

        document.getElementById('preview-section')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    } catch (e) {
        if (e.name === 'AbortError' || String(e.message || '').includes('Proces oprit')) {
            debugLog('Proces oprit de utilizator.', 'warn');
        } else {
            debugLog('Generare listă eșuată: ' + e.message, 'error');
            alert('Eroare: ' + e.message);
        }
        if (fileNames && e.name !== 'AbortError' && !String(e.message || '').includes('Proces oprit')) {
            fileNames.textContent = 'Eroare: ' + e.message;
            fileNames.style.color = '#dc2626';
        }
    } finally {
        activeJobId = '';
        activeJobController = null;
        setTimeout(() => setJobProgress(false), 800);
        updateStepStatus();
        if (generateBtn) {
            generateBtn.disabled = false;
            generateBtn.textContent = 'Generează lista de produse';
        }
    }
}

async function deleteUploadedFile(fileId, originalName) {
    if (!confirm('Sigur vrei să ștergi fișierul încărcat "' + originalName + '"?')) {
        return;
    }
    try {
        debugLog('Șterg fișierul încărcat: ' + originalName, 'warn');
        const res = await fetch(IMPORT_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ mode: 'delete_uploaded', file_id: fileId })
        });
        const json = await res.json();
        if (!json.success) {
            throw new Error(json.message || 'Nu s-a putut șterge');
        }
        debugLog('Fișier șters: ' + originalName, 'ok');
        await loadUploadedFiles();
        updateStepStatus();
    } catch (e) {
        debugLog('Ștergerea a eșuat: ' + e.message, 'error');
        alert('Eroare la ștergere: ' + e.message);
    }
}

async function handleRoleUpload(role, input) {
    if (!input.files || input.files.length === 0) return;

    const files = role === 'tecdoc'
        ? [input.files[0]]
        : Array.from(input.files);

    await uploadFiles(files, role);
    input.value = '';
}

async function uploadFiles(files, uploadRole) {
    const fileNames = document.getElementById('file-names');
    const progressWrap = document.getElementById('progress-wrap');
    const progressBar = document.getElementById('progress-bar');
    const progressPct = document.getElementById('progress-pct');
    const progressLabel = document.getElementById('progress-label');
    const progressSize = document.getElementById('progress-size');

    const names = files.map(f => f.name).join(', ');
    const totalSize = files.reduce((s, f) => s + f.size, 0);
    if (fileNames) {
        fileNames.textContent = 'Se încarcă: ' + names;
        fileNames.style.color = '#64748b';
    }
    debugLog('Upload ' + uploadRole + ': ' + names, 'info');

    progressWrap.style.display = 'block';
    progressBar.style.width = '0%';
    progressPct.textContent = '0%';
    progressLabel.textContent = 'Se încarcă fișierul…';
    progressSize.textContent = formatSize(0) + ' / ' + formatSize(totalSize);

    try {
        let totalUploaded = 0;

        for (const file of files) {
            const fileId = 'f_' + Date.now() + '_' + Math.random().toString(36).slice(2, 10);
            const totalChunks = Math.ceil(file.size / CHUNK_SIZE);
            debugLog(`Fișier: ${file.name} | bucăți: ${totalChunks} | mărime: ${formatSize(file.size)}`, 'info');

            for (let chunkIndex = 0; chunkIndex < totalChunks; chunkIndex++) {
                const start = chunkIndex * CHUNK_SIZE;
                const end = Math.min(start + CHUNK_SIZE, file.size);
                const chunk = file.slice(start, end);

                const formData = new FormData();
                formData.append('mode', 'upload_chunk');
                formData.append('file_id', fileId);
                formData.append('original_name', file.name);
                formData.append('chunk_index', String(chunkIndex));
                formData.append('total_chunks', String(totalChunks));
                formData.append('upload_role', uploadRole);
                formData.append('chunk', chunk, file.name + '.part');

                const res = await fetch(IMPORT_URL, { method: 'POST', body: formData });
                const rawText = await res.text();
                if (!rawText.trim()) {
                    throw new Error('Serverul a returnat răspuns gol la chunk-ul ' + (chunkIndex + 1));
                }
                const json = JSON.parse(rawText);
                if (!json.success) {
                    throw new Error(json.message || 'Eroare upload chunk');
                }

                totalUploaded += chunk.size;
                const pct = Math.min(100, Math.round((totalUploaded / totalSize) * 100));
                progressBar.style.width = pct + '%';
                progressPct.textContent = pct + '%';
                progressSize.textContent = formatSize(totalUploaded) + ' / ' + formatSize(totalSize);
                progressLabel.textContent = 'Se încarcă: ' + file.name + ' (' + (chunkIndex + 1) + '/' + totalChunks + ')';
            }

            debugLog(`Fișier complet uploadat: ${file.name}`, 'ok');
        }

        progressWrap.style.display = 'none';
        if (fileNames) {
            fileNames.textContent = 'Upload finalizat. Apasă „Generează lista de produse”.';
            fileNames.style.color = '#059669';
        }
        await loadUploadedFiles();
    } catch (err) {
        progressLabel.textContent = 'Eroare la upload';
        progressBar.style.width = '100%';
        progressBar.style.background = '#dc2626';
        if (fileNames) {
            fileNames.textContent = 'Eroare: ' + err.message;
            fileNames.style.color = '#dc2626';
        }
        debugLog('Upload oprit: ' + err.message, 'error');
    }
}

function formatSize(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / 1048576).toFixed(1) + ' MB';
}

function renderPreview() {
    const section = document.getElementById('preview-section');
    const tbody = document.getElementById('previewBody');
    const countEl = document.getElementById('preview-count');

    const visibleProducts = parsedProducts.filter(p => {
        if (priceFilter === 'priced') return hasProductPrice(p);
        if (priceFilter === 'missing') return !hasProductPrice(p);
        return true;
    });

    if (visibleProducts.length === 0) {
        section.style.display = parsedProducts.length > 0 ? 'block' : 'none';
        if (tbody) tbody.innerHTML = '';
        if (countEl) {
            const pricedCount = parsedProducts.filter(hasProductPrice).length;
            const missingCount = parsedProducts.length - pricedCount;
            countEl.innerHTML = parsedProducts.length > 0
                ? renderPreviewPriceStats(parsedProducts.length, 0, pricedCount, missingCount)
                : '';
        }
        return;
    }

    section.style.display = 'block';
    const pricedCount = parsedProducts.filter(hasProductPrice).length;
    const missingCount = parsedProducts.length - pricedCount;
    if (countEl) {
        countEl.innerHTML = renderPreviewPriceStats(parsedProducts.length, visibleProducts.length, pricedCount, missingCount);
    }

    tbody.innerHTML = visibleProducts.map((p) => {
        const i = parsedProducts.indexOf(p);
        const meta = extractProductMeta(p);
        const km = meta.km ? `${meta.km} km` : '';
        const technicalLabel = meta.technicalCount > 0 ? `${meta.technicalCount} câmpuri` : '';
        const specs = p.pSpecs || meta.specs || '';
        const priceMatch = meta.priceMatch || '';
        const tecdocApiLabel = meta.tecdocFileFound
            ? '<span style="color:#059669;font-weight:600;">CSV</span>'
            : (meta.tecdocSkipped
                ? '<span style="color:#64748b;">—</span>'
                : '<span style="color:#dc2626;">Lipsă</span>');
        const oemDisplay = p.pOem || meta.oemText || '';
        const supplierCell = '<div class="import-supplier-cell">'
            + '<span class="import-supplier-cell__name">' + esc(p.pSupplier) + '</span>'
            + (priceMatch ? '<span class="import-supplier-cell__match">' + esc(priceMatch) + '</span>' : '')
            + '</div>';
        return `
        <tr style="border-bottom:1px solid #f3f4f6;">
            <td style="padding:8px 12px;text-align:center;">
                <input type="checkbox" class="prod-cb" data-idx="${i}" checked style="width:16px;height:16px;">
            </td>
            <td style="padding:8px 12px;color:#9ca3af;">${i + 1}</td>
            <td style="padding:8px 12px;font-family:monospace;font-size:12px;">${esc(p.pCode)}</td>
            <td style="padding:8px 12px;font-weight:500;">${esc(p.pName)}</td>
            <td style="padding:8px 12px;">${esc(p.pBrand)}</td>
            <td style="padding:8px 12px;">${esc(p.pMarca)}</td>
            <td style="padding:8px 12px;">${esc(p.pModel)}</td>
            <td style="padding:8px 12px;">${esc(p.pMotorizare)}</td>
            <td class="import-col-price" style="padding:8px 12px;text-align:right;">${renderPriceFinalCell(p)}</td>
            <td class="import-col-price" style="padding:8px 12px;text-align:right;">${renderPriceBaseCell(p)}</td>
            <td style="padding:8px 12px;" title="${esc(priceMatch)}">${supplierCell}</td>
            <td style="padding:8px 12px;font-size:12px;color:#64748b;">${esc(p.pMarkupRuleName) || '—'}</td>
            <td style="padding:8px 12px;font-size:12px;">${tecdocApiLabel}</td>
            <td style="padding:8px 12px;font-size:12px;color:#475569;max-width:260px;" title="${esc(specs)}">${esc(specs)}</td>
            <td style="padding:8px 12px;">${esc(p.pCategory)}</td>
            <td style="padding:8px 12px;">${esc(p.pSubcategory)}</td>
            <td style="padding:8px 12px;">${esc(oemDisplay)}</td>
            <td style="padding:8px 12px;">${esc(km)}</td>
            <td style="padding:8px 12px;">${esc(technicalLabel)}</td>
            <td style="padding:8px 12px;">${esc(p.pStock)}</td>
        </tr>
    `;
    }).join('');
}

function extractProductMeta(product) {
    try {
        const raw = JSON.parse(product.raw_json || '{}');
        const summary = raw.product_summary || {};
        const vehicle = summary.vehicle || {};
        const technical = Array.isArray(summary.technical_data) ? summary.technical_data : [];
        const supplierPrice = raw.supplier_price || {};
        const tecdocApi = raw.tecdoc_api || {};
        const tecdocFile = raw.tecdoc_file || {};
        let priceMatch = '';
        if (supplierPrice.matched_code) {
            priceMatch = 'Match: ' + supplierPrice.matched_code;
            if (supplierPrice.matched_via) {
                priceMatch += ' (' + supplierPrice.matched_via + ')';
            }
        }
        const codes = summary.codes || {};
        const oemList = Array.isArray(codes.coduri_oem) ? codes.coduri_oem.filter(Boolean) : [];
        let tecdocLabel = 'missing';
        if (tecdocFile.found) {
            tecdocLabel = 'file';
        } else if (tecdocApi.found) {
            tecdocLabel = 'api';
        } else if (tecdocApi.skipped) {
            tecdocLabel = 'skipped';
        }
        return {
            km: vehicle.kilometraj_km ? String(vehicle.kilometraj_km) : '',
            technicalCount: technical.length,
            specs: summary.specs || product.pSpecs || '',
            priceMatch,
            tecdocFound: tecdocLabel === 'api',
            tecdocFileFound: tecdocLabel === 'file',
            tecdocSkipped: tecdocLabel === 'skipped',
            oemText: oemList.length ? oemList.join(', ') : ''
        };
    } catch (e) {
        return { km: '', technicalCount: 0, specs: product.pSpecs || '', priceMatch: '', tecdocFound: false, tecdocFileFound: false, tecdocSkipped: false, oemText: '' };
    }
}

function esc(val) {
    if (!val) return '';
    return String(val).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function toggleSelectAll() {
    const checked = document.getElementById('selectAllCb').checked;
    document.querySelectorAll('.prod-cb').forEach(cb => cb.checked = checked);
}

async function startImportQueueJob(products, markupRuleId, options = {}) {
    const chunkSize = IMPORT_QUEUE_CHUNK_SIZE;
    const timeoutMs = options.timeoutMs || IMPORT_QUEUE_STEP_TIMEOUT_MS;
    const onProgress = typeof options.onProgress === 'function' ? options.onProgress : null;
    const showGlobalProgress = options.showGlobalProgress !== false;
    let jobId = '';

    for (let offset = 0; offset < products.length; offset += chunkSize) {
        if (activeJobAbort) {
            throw new Error('Proces oprit.');
        }

        const chunk = products.slice(offset, offset + chunkSize);
        const loaded = Math.min(offset + chunk.length, products.length);
        const uploadPct = Math.round((loaded / products.length) * 8);

        if (!jobId) {
            const initRes = await fetch(IMPORT_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    mode: 'import_job_init',
                    markup_rule_id: markupRuleId || 0,
                    total_products: products.length
                }),
                signal: jobFetchSignal(options.signal, timeoutMs)
            });
            const initJson = await parseJsonResponse(initRes, 'Inițializare import');
            if (!initJson.success || !initJson.job_id) {
                throw new Error(initJson.message || 'Nu am putut inițializa job-ul de import.');
            }
            jobId = initJson.job_id;
            activeJobId = jobId;
        }

        const appendRes = await fetch(IMPORT_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                mode: 'import_job_append',
                job_id: jobId,
                products: chunk
            }),
            signal: jobFetchSignal(options.signal, timeoutMs)
        });
        const appendJson = await parseJsonResponse(appendRes, 'Încărcare produse (' + loaded + '/' + products.length + ')');
        if (!appendJson.success) {
            throw new Error(appendJson.message || 'Nu am putut încărca produsele în job.');
        }

        const detail = loaded + ' / ' + products.length + ' produse încărcate';
        if (showGlobalProgress) {
            setJobProgress(true, uploadPct, 'Încarc produse în job (fundal)...', detail);
        }
        if (onProgress) {
            onProgress({ progress: uploadPct, message: 'Încarc produse în job...', phase: 'loading_products', detail: detail });
        }
    }

    const beginRes = await fetch(IMPORT_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ mode: 'import_job_begin', job_id: jobId }),
        signal: jobFetchSignal(options.signal, timeoutMs)
    });
    const beginJson = await parseJsonResponse(beginRes, 'Pornire procesare import');
    if (!beginJson.success) {
        throw new Error(beginJson.message || 'Nu am putut porni procesarea job-ului.');
    }

    if (showGlobalProgress) {
        setJobProgress(true, 10, 'Procesare import în fundal...', products.length + ' produse');
    }
    if (onProgress) {
        onProgress({ progress: 10, message: 'Procesare import în fundal...', phase: 'processing' });
    }

    return jobId;
}

async function importSelected() {
    const selected = [];
    document.querySelectorAll('.prod-cb:checked').forEach(cb => {
        const idx = parseInt(cb.dataset.idx);
        if (parsedProducts[idx]) selected.push(parsedProducts[idx]);
    });

    if (selected.length === 0) {
        alert('Selectează cel puțin un produs.');
        return;
    }

    const btn = document.getElementById('importBtn');
    const statusEl = document.getElementById('import-status');
    btn.disabled = true;
    btn.textContent = 'Procesare în fundal... (' + selected.length + ' produse)';
    statusEl.style.display = 'block';
    statusEl.style.background = '#f0fdf4';
    statusEl.style.color = '#059669';
    statusEl.textContent = 'Pregatesc ' + selected.length + ' produse in coada (proces in fundal)...';

    try {
        debugLog('Pornesc import în fundal pentru ' + selected.length + ' produse (chunk-uri)...', 'info');
        const markupRuleId = parseInt(document.getElementById('importMarkupRuleId')?.value || '0', 10) || 0;
        const json = await runBackgroundJob(
            null,
            'import_job_step',
            {},
            'Import în coadă',
            {
                stepTimeoutMs: IMPORT_QUEUE_STEP_TIMEOUT_MS,
                startFn: (opts) => startImportQueueJob(selected, markupRuleId, opts)
            }
        );

        statusEl.style.background = '#f0fdf4';
        statusEl.style.color = '#059669';
        statusEl.textContent = json.message || (json.count + ' produse importate cu succes!');
        btn.textContent = 'Trimitere finalizată!';
        debugLog('Import finalizat: ' + (json.message || ''), 'ok');
        if (json.redirect) {
            setTimeout(function() {
                window.location.href = json.redirect;
            }, 700);
        }
    } catch (err) {
        if (err.name === 'AbortError' || String(err.message || '').includes('Proces oprit')) {
            statusEl.style.background = '#fff7ed';
            statusEl.style.color = '#c2410c';
            statusEl.textContent = 'Proces oprit.';
            debugLog('Import întrerupt.', 'warn');
        } else {
            statusEl.style.background = '#fef2f2';
            statusEl.style.color = '#dc2626';
            statusEl.textContent = 'Eroare: ' + err.message;
            btn.disabled = false;
            btn.textContent = 'Trimite selectate in coada';
            debugLog('Import întrerupt: ' + err.message, 'error');
        }
    } finally {
        activeJobId = '';
        activeJobController = null;
        setTimeout(() => setJobProgress(false), 800);
    }
}

stopAllBackgroundJobs(false);

loadUploadedFiles();
loadTecdocLibrary();
setPriceFilter('all');
</script>
