<?php
declare(strict_types=1);

use Besoiu\Core\AdminUrl;

$importActionApiUrl = AdminUrl::api('import_action_endpoint.php');
$asyncClientUrl = AdminUrl::asset('js/besoiu-async-client.js');

function irn_h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

require __DIR__ . '/_importreview-shell-styles.php';
?>
<div class="import-review-page irv-layout irn-page">
    <div class="admin-panel irv-shell">

        <nav class="irv-main-tabs" aria-label="Secțiuni import review">
            <a href="/admin/importreview" class="irv-main-tabs__item">
                <i data-lucide="list" class="irv-main-tabs__icon"></i>
                Coadă import
            </a>
            <a href="/admin/importreview?view=normalize" class="irv-main-tabs__item is-active" aria-current="page">
                <i data-lucide="text-cursor-input" class="irv-main-tabs__icon"></i>
                Normalizare denumiri
            </a>
        </nav>

        <header class="irv-hero irn-hero">
            <div class="irv-hero__main">
                <div class="irv-hero__eyebrow">Import · TecDoc → Magazin</div>
                <h1 class="irv-hero__title">Normalizare denumiri produse</h1>
                <p class="irv-hero__desc">
                    Transformă denumirile tehnice TecDoc în denumiri clare pentru titlu, categorie și descriere.
                    Regulile din <code>denumiri produse.txt</code> se combină cu reguli custom editabile.
                    Ollama poate propune mapări pe care le salvezi cu un click.
                </p>
            </div>
            <div class="irn-hero-stats">
                <div class="irn-stat" id="irnStatOllama">
                    <span class="irn-stat__icon"><i data-lucide="cpu"></i></span>
                    <div>
                        <span class="irn-stat__label">Ollama</span>
                        <span class="irn-stat__value" id="irnOllamaBadge">Se verifică…</span>
                    </div>
                </div>
                <div class="irn-stat">
                    <span class="irn-stat__icon"><i data-lucide="book-marked"></i></span>
                    <div>
                        <span class="irn-stat__label">Reguli active</span>
                        <span class="irn-stat__value" id="irnRulesBadge">—</span>
                    </div>
                </div>
                <div class="irn-stat">
                    <span class="irn-stat__icon"><i data-lucide="database"></i></span>
                    <div>
                        <span class="irn-stat__label">Sursă base</span>
                        <span class="irn-stat__value" id="irnBaseBadge">—</span>
                    </div>
                </div>
                <div class="irn-stat">
                    <span class="irn-stat__icon"><i data-lucide="pen-line"></i></span>
                    <div>
                        <span class="irn-stat__label">Custom</span>
                        <span class="irn-stat__value" id="irnCustomBadge">—</span>
                    </div>
                </div>
            </div>
        </header>

        <div class="irn-steps" aria-label="Flux de lucru">
            <div class="irn-step is-active"><span class="irn-step__num">1</span> Testează denumirea</div>
            <div class="irn-step"><span class="irn-step__num">2</span> Reguli custom</div>
            <div class="irn-step"><span class="irn-step__num">3</span> Normalizare masivă</div>
            <div class="irn-step"><span class="irn-step__num">4</span> Revizie coadă</div>
        </div>

        <section class="irv-card irn-bulk-card" id="irnBulkCard">
            <div class="irv-card__head irn-card-head">
                <div class="irn-card-head__text">
                    <div class="irv-card__title">
                        <i data-lucide="layers" class="irv-card__title-icon"></i>
                        <span>Normalizare masivă (10k+)</span>
                    </div>
                    <span class="irv-card__hint">
                        Rulează în <strong>fundal</strong> (worker CLI) — fără timeout Cloudflare.
                        <strong>Pas 1</strong> = reguli rapide pe toată coada · <strong>Pas 2</strong> = Ollama pe rest.
                    </span>
                </div>
                <div class="irn-toolbar irn-toolbar--inline">
                    <span class="irn-bulk-stat" id="irnBulkPending">Pending: —</span>
                </div>
            </div>
            <div class="irn-bulk-actions">
                <button type="button" id="irnBulkRulesBtn" class="irn-btn irn-btn--save">
                    <i data-lucide="zap" style="width:16px;height:16px"></i>
                    Pas 1 — Reguli pe toată coada
                </button>
                <button type="button" id="irnBulkOllamaBtn" class="irn-btn irn-btn--ai">
                    <i data-lucide="sparkles" style="width:16px;height:16px"></i>
                    Pas 2 — Ollama (fundal)
                </button>
                <label class="irn-check irn-check--inline">
                    <input type="checkbox" id="irnBulkSaveRules">
                    <span>Salvează reguli Ollama</span>
                </label>
            </div>
            <div id="irnBulkProgress" class="irn-bulk-progress" hidden>
                <div class="irn-bulk-progress__bar"><div id="irnBulkProgressFill" class="irn-bulk-progress__fill"></div></div>
                <p id="irnBulkProgressText" class="irn-bulk-progress__text">Se pregătește…</p>
            </div>
        </section>

        <section class="irv-card irn-workbench">
            <div class="irv-card__head">
                <div>
                    <div class="irv-card__title">
                        <i data-lucide="flask-conical" class="irv-card__title-icon"></i>
                        <span>Laborator normalizare</span>
                    </div>
                    <span class="irv-card__hint">Test manual + pipeline complet: <strong>reguli → Ollama</strong> pe cazurile incerte (Neschimbat / Pattern).</span>
                </div>
            </div>

            <div class="irn-workbench__grid">
                <div class="irn-panel irn-panel--form">
                    <div class="irn-field">
                        <label class="irv-field__label" for="irnRawName">Denumire TecDoc (ART_NAME)</label>
                        <input id="irnRawName" type="text" class="irv-input" placeholder="ex. Amortizor telescop capota">
                    </div>
                    <div class="irn-field">
                        <label class="irv-field__label" for="irnBrand">Brand piesă (opțional)</label>
                        <input id="irnBrand" type="text" class="irv-input" placeholder="ex. STABILUS">
                    </div>
                    <div class="irn-panel__actions">
                        <label class="irn-check irn-check--inline">
                            <input type="checkbox" id="irnUseOllamaFilter" checked>
                            <span>Filtre Ollama (pas 2)</span>
                        </label>
                        <button type="button" id="irnPreviewBtn" class="irn-btn irn-btn--save">
                            <i data-lucide="play" style="width:16px;height:16px"></i>
                            Previzualizează
                        </button>
                        <button type="button" id="irnOllamaBtn" class="irn-btn irn-btn--ai">
                            <i data-lucide="sparkles" style="width:16px;height:16px"></i>
                            Sugerează cu Ollama
                        </button>
                    </div>
                </div>

                <div class="irn-panel irn-panel--result">
                    <div id="irnPipelineEmpty" class="irn-empty-state">
                        <i data-lucide="arrow-left" class="irn-empty-state__icon"></i>
                        <p>Completează denumirea TecDoc și apasă <strong>Previzualizează</strong> pentru a vedea transformarea.</p>
                    </div>
                    <div id="irnPipeline" class="irn-flow" hidden>
                        <div class="irn-flow__card">
                            <span class="irn-flow__tag">Intrare TecDoc</span>
                            <p id="irnPipeRaw" class="irn-flow__text"></p>
                        </div>
                        <div class="irn-flow__connector" aria-hidden="true"><i data-lucide="chevron-right"></i></div>
                        <div class="irn-flow__card irn-flow__card--rule">
                            <span class="irn-flow__tag">Regulă / pattern</span>
                            <p id="irnPipeRule" class="irn-flow__text"></p>
                        </div>
                        <div class="irn-flow__connector" aria-hidden="true"><i data-lucide="chevron-right"></i></div>
                        <div class="irn-flow__card irn-flow__card--final">
                            <span class="irn-flow__tag">Rezultat magazin</span>
                            <p id="irnPipeFinal" class="irn-flow__text irn-flow__text--final"></p>
                        </div>
                        <div class="irn-flow__meta">
                            <span id="irnPipeSource"></span>
                        </div>
                    </div>
                    <div id="irnOllamaResult" class="irn-ollama-card" hidden></div>
                </div>
            </div>
        </section>

        <section class="irv-card irn-rules-card">
            <div class="irv-card__head irn-card-head">
                <div class="irn-card-head__text">
                    <div class="irv-card__title">
                        <i data-lucide="book-open" class="irv-card__title-icon"></i>
                        <span>Reguli de mapare</span>
                    </div>
                    <span class="irv-card__hint"><strong>Custom</strong> = editabile · <strong>Base</strong> = din fișierul TecDoc (read-only)</span>
                </div>
                <button type="button" id="irnAddRuleBtn" class="irn-btn irn-btn--save irn-btn--compact">
                    <i data-lucide="plus" style="width:14px;height:14px"></i>
                    Regulă nouă
                </button>
            </div>

            <div class="irn-toolbar">
                <div class="irn-toolbar__search">
                    <i data-lucide="search" class="irn-toolbar__search-icon"></i>
                    <input id="irnRuleSearch" type="search" class="irv-input irn-toolbar__input" placeholder="Caută alias sau denumire magazin…">
                </div>
                <select id="irnRuleSource" class="irv-input irv-select irn-toolbar__select">
                    <option value="all">Toate sursele</option>
                    <option value="custom">Doar custom</option>
                    <option value="base">Doar base</option>
                </select>
                <button type="button" id="irnRulesReload" class="irn-btn irn-btn--ghost irn-btn--compact">Reîncarcă</button>
            </div>

            <div class="admin-table-wrap irn-table-wrap">
                <table class="irv-table" id="irnRulesTable">
                    <thead>
                        <tr>
                            <th class="irv-th">Alias TecDoc</th>
                            <th class="irv-th">Denumire magazin</th>
                            <th class="irv-th" style="width:90px">Sursă</th>
                            <th class="irv-th irv-th--actions">Acțiuni</th>
                        </tr>
                    </thead>
                    <tbody id="irnRulesBody">
                        <tr><td colspan="4" class="irv-td irn-table-empty">Se încarcă regulile…</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="irn-pagination" id="irnRulesPager"></div>
        </section>

        <section class="irv-card irn-queue-card">
            <div class="irv-card__head irn-card-head">
                <div class="irn-card-head__text">
                    <div class="irv-card__title">
                        <i data-lucide="scan-search" class="irv-card__title-icon"></i>
                        <span>Coadă — produse de revizuit</span>
                    </div>
                    <span class="irv-card__hint">Produse pending fără mapare explicită. <strong>Scanează</strong> = rapid (reguli). <strong>Aplică selectate</strong> = Ollama pe max 3/cerere (evită timeout 524).</span>
                </div>
                <div class="irn-toolbar irn-toolbar--inline">
                    <label class="irn-check irn-check--inline" title="Doar la Aplică selectate — nu la scanare">
                        <input type="checkbox" id="irnQueueUseOllama" checked>
                        <span>Ollama la aplicare</span>
                    </label>
                    <button type="button" id="irnQueueScanBtn" class="irn-btn irn-btn--ghost irn-btn--compact">
                        <i data-lucide="refresh-cw" style="width:14px;height:14px"></i>
                        Scanează coada
                    </button>
                    <button type="button" id="irnQueueApplySelectedBtn" class="irn-btn irn-btn--save irn-btn--compact" disabled>
                        Aplică selectate
                    </button>
                </div>
            </div>

            <div class="admin-table-wrap irn-table-wrap">
                <table class="irv-table">
                    <thead>
                        <tr>
                            <th class="irv-th irv-th--check"><input type="checkbox" id="irnQueueCheckAll" aria-label="Selectează toate"></th>
                            <th class="irv-th" style="width:120px">Cod</th>
                            <th class="irv-th">TecDoc (sursă)</th>
                            <th class="irv-th">Normalizat</th>
                            <th class="irv-th">Subcategorie curentă</th>
                            <th class="irv-th" style="width:90px">Sursă</th>
                            <th class="irv-th irv-th--actions">Acțiuni</th>
                        </tr>
                    </thead>
                    <tbody id="irnQueueBody">
                        <tr class="irn-queue-placeholder">
                            <td colspan="7" class="irv-td">
                                <div class="irn-empty-state irn-empty-state--table">
                                    <i data-lucide="inbox" class="irn-empty-state__icon"></i>
                                    <p>Apasă <strong>Scanează coada</strong> pentru a găsi produse care necesită normalizare.</p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <div id="irnRuleModal" class="irn-modal" aria-hidden="true">
        <div class="irn-modal__backdrop" data-close="1"></div>
        <div class="irn-modal__panel" role="dialog" aria-labelledby="irnRuleModalTitle" aria-modal="true">
            <header class="irn-modal__head">
                <div>
                    <h2 id="irnRuleModalTitle" class="irn-modal__title">Regulă normalizare</h2>
                    <p class="irn-modal__subtitle">Format: <code>alias TecDoc#Denumire magazin</code></p>
                </div>
                <button type="button" class="irn-modal__close" data-close="1" aria-label="Închide">×</button>
            </header>
            <div class="irn-modal__body">
                <input type="hidden" id="irnRuleLine">
                <div class="irn-field">
                    <label class="irv-field__label" for="irnRuleAliases">Alias TecDoc (separă cu |)</label>
                    <textarea id="irnRuleAliases" rows="3" class="irv-input irn-textarea" placeholder="Amortizor telescop capota | Amortizor portbagaj"></textarea>
                </div>
                <div class="irn-field">
                    <label class="irv-field__label" for="irnRuleTarget">Denumire magazin</label>
                    <input id="irnRuleTarget" type="text" class="irv-input" placeholder="ex. Amortizor capota">
                </div>
            </div>
            <footer class="irn-modal__foot">
                <button type="button" class="irn-btn irn-btn--cancel" data-close="1">Anulează</button>
                <button type="button" id="irnRuleSaveBtn" class="irn-btn irn-btn--save">Salvează regula</button>
            </footer>
        </div>
    </div>
</div>

<style>
/* ── Normalizare — layout PRO ── */
.irn-page { font-family: inherit; }

.irn-hero { align-items: stretch !important; }
.irn-hero-stats {
    display: grid;
    grid-template-columns: repeat(2, minmax(140px, 1fr));
    gap: 10px;
    flex: 0 0 min(420px, 100%);
}
@media (min-width: 1100px) {
    .irn-hero-stats { grid-template-columns: repeat(2, 1fr); }
}
.irn-stat {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 12px 14px;
    border-radius: 12px;
    background: rgba(255,255,255,.92);
    border: 1px solid var(--irv-line);
}
.irn-stat__icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 34px;
    height: 34px;
    border-radius: 10px;
    background: #ecfdf5;
    color: var(--irv-teal-dark);
    flex-shrink: 0;
}
.irn-stat__icon svg { width: 17px; height: 17px; }
.irn-stat__label {
    display: block;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: .06em;
    text-transform: uppercase;
    color: var(--irv-muted);
}
.irn-stat__value {
    display: block;
    margin-top: 2px;
    font-size: 13px;
    font-weight: 700;
    color: var(--irv-ink);
    line-height: 1.35;
    word-break: break-word;
}
.irn-stat.is-warn .irn-stat__icon { background: #fffbeb; color: #b45309; }
.irn-stat.is-ok .irn-stat__icon { background: #ecfdf5; color: #047857; }

.irn-steps {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    padding: 4px 0;
}
.irn-step {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 14px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 700;
    color: var(--irv-muted);
    background: #fff;
    border: 1px solid var(--irv-line);
}
.irn-step.is-active {
    color: var(--irv-teal-dark);
    background: #f0fdfa;
    border-color: #99f6e4;
}
.irn-step__num {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 22px;
    height: 22px;
    border-radius: 999px;
    font-size: 11px;
    background: var(--irv-bg);
    color: var(--irv-ink);
}
.irn-step.is-active .irn-step__num {
    background: var(--irv-teal);
    color: #fff;
}

.irn-workbench__grid {
    display: grid;
    grid-template-columns: minmax(280px, 360px) 1fr;
    gap: 0;
    border-top: 1px solid var(--irv-line);
}
@media (max-width: 960px) {
    .irn-workbench__grid { grid-template-columns: 1fr; }
}
.irn-panel {
    padding: 20px;
}
.irn-panel--form {
    border-right: 1px solid var(--irv-line);
    background: linear-gradient(180deg, #fafafa, #fff);
}
@media (max-width: 960px) {
    .irn-panel--form { border-right: none; border-bottom: 1px solid var(--irv-line); }
}
.irn-panel--result {
    background: #fff;
    min-height: 220px;
    display: flex;
    flex-direction: column;
}
.irn-panel__actions {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
    margin-top: 16px;
}
.irn-check {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    color: var(--irv-muted);
    cursor: pointer;
    user-select: none;
}
.irn-check input { width: 16px; height: 16px; accent-color: var(--irv-teal); }
.irn-check--inline { margin-right: 4px; }

.irn-bulk-actions {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 10px;
    padding: 16px 20px 20px;
}
.irn-bulk-stat {
    font-size: 13px;
    font-weight: 700;
    color: var(--irv-teal-dark);
    padding: 6px 12px;
    background: #ecfdf5;
    border-radius: 999px;
    border: 1px solid #99f6e4;
}
.irn-bulk-progress {
    padding: 0 20px 20px;
}
.irn-bulk-progress__bar {
    height: 10px;
    border-radius: 999px;
    background: #e2e8f0;
    overflow: hidden;
}
.irn-bulk-progress__fill {
    height: 100%;
    width: 0%;
    background: linear-gradient(90deg, #0d9488, #14b8a6);
    transition: width .35s ease;
}
.irn-bulk-progress__text {
    margin: 10px 0 0;
    font-size: 13px;
    color: var(--irv-muted);
}

.irn-empty-state {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    padding: 32px 24px;
    color: var(--irv-muted);
    font-size: 14px;
    line-height: 1.55;
}
.irn-empty-state--table { padding: 40px 20px; }
.irn-empty-state__icon { width: 36px; height: 36px; color: #cbd5e1; margin-bottom: 12px; }
.irn-empty-state strong { color: var(--irv-ink); }

.irn-flow {
    display: flex;
    flex-wrap: wrap;
    align-items: stretch;
    gap: 8px;
    padding: 4px 0;
}
.irn-flow__card {
    flex: 1 1 140px;
    min-width: 0;
    padding: 14px;
    border-radius: 12px;
    border: 1px solid var(--irv-line);
    background: var(--irv-bg);
}
.irn-flow__card--rule { background: #fffbeb; border-color: #fde68a; }
.irn-flow__card--final {
    background: linear-gradient(135deg, #ecfdf5, #f0fdfa);
    border-color: #99f6e4;
}
.irn-flow__tag {
    display: block;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: .06em;
    text-transform: uppercase;
    color: var(--irv-muted);
    margin-bottom: 6px;
}
.irn-flow__text {
    margin: 0;
    font-size: 14px;
    line-height: 1.45;
    color: var(--irv-ink);
    word-break: break-word;
}
.irn-flow__text--final {
    font-size: 16px;
    font-weight: 800;
    color: var(--irv-teal-dark);
}
.irn-flow__connector {
    display: flex;
    align-items: center;
    color: #cbd5e1;
    flex: 0 0 auto;
}
.irn-flow__connector svg { width: 20px; height: 20px; }
.irn-flow__meta {
    flex: 1 1 100%;
    margin-top: 4px;
    font-size: 12px;
    color: var(--irv-muted);
}
@media (max-width: 700px) {
    .irn-flow__connector { display: none; }
    .irn-flow__card { flex: 1 1 100%; }
}

.irn-ollama-card {
    margin-top: 16px;
    padding: 16px;
    border-radius: 12px;
    background: linear-gradient(135deg, #faf5ff, #f0fdfa);
    border: 1px solid #ddd6fe;
    font-size: 14px;
    line-height: 1.55;
}
.irn-ollama-card__title { font-weight: 800; color: #5b21b6; margin: 0 0 8px; }
.irn-ollama-card__rule {
    display: block;
    margin: 10px 0;
    padding: 10px 12px;
    border-radius: 8px;
    background: rgba(255,255,255,.7);
    border: 1px solid #e9d5ff;
    font-family: ui-monospace, monospace;
    font-size: 12px;
    word-break: break-word;
}
.irn-ollama-card__actions { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 12px; }

.irn-toolbar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 10px;
    padding: 14px 20px;
    border-bottom: 1px solid var(--irv-line);
    background: var(--irv-bg);
}
.irn-toolbar--inline { padding: 0; border: none; background: transparent; }
.irn-toolbar__search {
    position: relative;
    flex: 1 1 240px;
    max-width: 360px;
}
.irn-toolbar__search-icon {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    width: 16px;
    height: 16px;
    color: var(--irv-muted);
    pointer-events: none;
}
.irn-toolbar__input { padding-left: 38px !important; }
.irn-toolbar__select { width: auto; min-width: 160px; flex: 0 0 auto; }

.irn-table-wrap { max-height: 420px; overflow: auto; }
.irn-table-empty { text-align: center; color: var(--irv-muted); padding: 28px !important; }
.irn-alias {
    display: block;
    font-family: ui-monospace, monospace;
    font-size: 12px;
    line-height: 1.45;
    color: #475569;
    word-break: break-word;
}
.irn-target { font-weight: 800; color: var(--irv-ink); }
.irn-readonly {
    font-size: 11px;
    font-weight: 600;
    color: var(--irv-muted);
    font-style: italic;
}

.irn-pagination {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    padding: 14px 20px 18px;
    border-top: 1px solid var(--irv-line);
    background: #fff;
}

.irn-textarea {
    height: auto !important;
    min-height: 88px;
    padding: 12px 14px !important;
    resize: vertical;
    line-height: 1.45;
}

.irn-modal {
    position: fixed;
    inset: 0;
    z-index: 10050;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 20px;
}
.irn-modal__backdrop {
    position: absolute;
    inset: 0;
    background: rgba(15,23,42,.5);
    backdrop-filter: blur(2px);
}
.irn-modal__panel {
    position: relative;
    width: min(520px, 100%);
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 24px 60px rgba(15,23,42,.25);
    overflow: hidden;
}
.irn-modal__head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    padding: 18px 20px;
    border-bottom: 1px solid var(--irv-line);
    background: linear-gradient(180deg, var(--irv-bg), #fff);
}
.irn-modal__title { margin: 0; font-size: 1.1rem; font-weight: 800; color: var(--irv-ink); }
.irn-modal__subtitle { margin: 4px 0 0; font-size: 12px; color: var(--irv-muted); }
.irn-modal__subtitle code { font-size: 11px; }
.irn-modal__body { padding: 20px; display: grid; gap: 14px; }
.irn-modal__foot {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    padding: 14px 20px;
    border-top: 1px solid var(--irv-line);
    background: var(--irv-bg);
}
.irn-modal__close {
    border: 0;
    background: #fff;
    width: 36px;
    height: 36px;
    border-radius: 10px;
    font-size: 22px;
    line-height: 1;
    cursor: pointer;
    color: var(--irv-muted);
    border: 1px solid var(--irv-line);
}
.irn-modal__close:hover { background: var(--irv-bg); color: var(--irv-ink); }

/* Butoane dedicate — rezistente la tema admin */
.irn-page .irn-btn,
#irnRuleModal .irn-btn {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 8px !important;
    min-height: 42px !important;
    padding: 0 18px !important;
    border-radius: 10px !important;
    font-size: 13px !important;
    font-weight: 700 !important;
    line-height: 1.2 !important;
    border: 2px solid transparent !important;
    cursor: pointer !important;
    text-decoration: none !important;
    white-space: nowrap !important;
    position: relative !important;
    z-index: 2 !important;
    overflow: visible !important;
    transform: none !important;
    box-sizing: border-box !important;
    -webkit-appearance: none !important;
    appearance: none !important;
}
.irn-page .irn-btn--compact,
#irnRuleModal .irn-btn--compact {
    min-height: 38px !important;
    padding: 0 14px !important;
    font-size: 12px !important;
}
.irn-page .irn-btn--save,
#irnRuleModal .irn-btn--save {
    background: linear-gradient(180deg, #10b981 0%, #059669 100%) !important;
    color: #ffffff !important;
    border-color: #047857 !important;
    box-shadow: 0 4px 14px rgba(5, 150, 105, 0.35) !important;
}
.irn-page .irn-btn--save:hover,
#irnRuleModal .irn-btn--save:hover {
    background: linear-gradient(180deg, #34d399 0%, #059669 100%) !important;
    color: #ffffff !important;
}
.irn-page .irn-btn--ai {
    background: linear-gradient(180deg, #2dd4bf 0%, #0f766e 100%) !important;
    color: #ffffff !important;
    border-color: #5b21b6 !important;
    box-shadow: 0 4px 14px rgba(109, 40, 217, 0.3) !important;
}
.irn-page .irn-btn--ai:hover {
    background: linear-gradient(180deg, #5eead4 0%, #0d9488 100%) !important;
    color: #ffffff !important;
}
.irn-page .irn-btn--ghost,
#irnRuleModal .irn-btn--cancel {
    background: #ffffff !important;
    color: #0f766e !important;
    border-color: #99f6e4 !important;
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.06) !important;
}
.irn-page .irn-btn--ghost:hover,
#irnRuleModal .irn-btn--cancel:hover {
    background: #f0fdfa !important;
    color: #0f766e !important;
    border-color: #5eead4 !important;
}
.irn-page .irn-btn:disabled {
    opacity: 0.5 !important;
    cursor: not-allowed !important;
    filter: grayscale(0.2) !important;
}

.irn-card-head {
    align-items: center !important;
}
.irn-card-head__text {
    flex: 1 1 280px;
    min-width: 0;
}

/* Inputuri — contrast clar */
.irn-page .irv-input,
#irnRuleModal .irv-input {
    border: 2px solid #cbd5e1 !important;
    background: #ffffff !important;
    color: #0f172a !important;
}
.irn-page .irv-input:focus,
#irnRuleModal .irv-input:focus {
    border-color: #0d9488 !important;
    box-shadow: 0 0 0 3px rgba(13, 148, 136, 0.2) !important;
    outline: none !important;
}

/* Modal — deschidere corectă + overlay */
body.besoiu-admin-2026 #irnRuleModal {
    display: none !important;
    pointer-events: none !important;
}
body.besoiu-admin-2026 #irnRuleModal.is-open {
    display: flex !important;
    visibility: visible !important;
    opacity: 1 !important;
    pointer-events: auto !important;
    position: fixed !important;
    inset: 0 !important;
    z-index: 10050 !important;
    align-items: center !important;
    justify-content: center !important;
    padding: 20px !important;
}
body.besoiu-admin-2026.irn-modal-open {
    overflow: hidden !important;
}
body.besoiu-admin-2026 #irnRuleModal.is-open .irn-modal__backdrop {
    position: fixed !important;
    inset: 0 !important;
    background: rgba(15, 23, 42, 0.62) !important;
    backdrop-filter: blur(3px) !important;
    z-index: 0 !important;
}
body.besoiu-admin-2026 #irnRuleModal.is-open .irn-modal__panel {
    position: relative !important;
    z-index: 1 !important;
    pointer-events: auto !important;
}
body.besoiu-admin-2026 #irnRuleModal .besoiu-btn-ripple {
    display: none !important;
}
body.besoiu-admin-2026 #irnRuleModal .irn-modal__foot .irn-btn {
    min-width: 130px !important;
}

/* Butoane irv rămase (paginare, rând tabel) */
body.besoiu-admin-2026 .irn-page .irv-btn--primary {
    background: linear-gradient(180deg, #10b981 0%, #059669 100%) !important;
    color: #fff !important;
    border: 2px solid #047857 !important;
}
body.besoiu-admin-2026 .irn-page .irv-btn--ghost {
    background: #fff !important;
    color: #0f766e !important;
    border: 2px solid #99f6e4 !important;
}
body.besoiu-admin-2026 .irn-page .irv-row-btn {
    background: #f8fafc !important;
    color: #334155 !important;
    border: 1px solid #e2e8f0 !important;
}
body.besoiu-admin-2026 .irn-page .irv-row-btn--violet {
    background: #f0fdfa !important;
    color: #0f766e !important;
    border-color: #ddd6fe !important;
}
body.besoiu-admin-2026 .irn-page .irv-row-btn--teal {
    background: #f0fdfa !important;
    color: #0f766e !important;
    border-color: #99f6e4 !important;
}
</style>

<script src="<?= htmlspecialchars($asyncClientUrl, ENT_QUOTES, 'UTF-8') ?>"></script>
<script>
(function () {
    const API = <?= json_encode($importActionApiUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

    const els = {
        statOllama: document.getElementById('irnStatOllama'),
        ollamaBadge: document.getElementById('irnOllamaBadge'),
        rulesBadge: document.getElementById('irnRulesBadge'),
        baseBadge: document.getElementById('irnBaseBadge'),
        customBadge: document.getElementById('irnCustomBadge'),
        rawName: document.getElementById('irnRawName'),
        brand: document.getElementById('irnBrand'),
        previewBtn: document.getElementById('irnPreviewBtn'),
        ollamaBtn: document.getElementById('irnOllamaBtn'),
        useOllamaFilter: document.getElementById('irnUseOllamaFilter'),
        queueUseOllama: document.getElementById('irnQueueUseOllama'),
        pipelineEmpty: document.getElementById('irnPipelineEmpty'),
        pipeline: document.getElementById('irnPipeline'),
        pipeRaw: document.getElementById('irnPipeRaw'),
        pipeRule: document.getElementById('irnPipeRule'),
        pipeFinal: document.getElementById('irnPipeFinal'),
        pipeSource: document.getElementById('irnPipeSource'),
        ollamaResult: document.getElementById('irnOllamaResult'),
        ruleSearch: document.getElementById('irnRuleSearch'),
        ruleSource: document.getElementById('irnRuleSource'),
        rulesReload: document.getElementById('irnRulesReload'),
        rulesBody: document.getElementById('irnRulesBody'),
        rulesPager: document.getElementById('irnRulesPager'),
        addRuleBtn: document.getElementById('irnAddRuleBtn'),
        queueScanBtn: document.getElementById('irnQueueScanBtn'),
        queueApplySelectedBtn: document.getElementById('irnQueueApplySelectedBtn'),
        queueCheckAll: document.getElementById('irnQueueCheckAll'),
        queueBody: document.getElementById('irnQueueBody'),
        ruleModal: document.getElementById('irnRuleModal'),
        ruleLine: document.getElementById('irnRuleLine'),
        ruleAliases: document.getElementById('irnRuleAliases'),
        ruleTarget: document.getElementById('irnRuleTarget'),
        ruleSaveBtn: document.getElementById('irnRuleSaveBtn'),
        bulkPending: document.getElementById('irnBulkPending'),
        bulkRulesBtn: document.getElementById('irnBulkRulesBtn'),
        bulkOllamaBtn: document.getElementById('irnBulkOllamaBtn'),
        bulkSaveRules: document.getElementById('irnBulkSaveRules'),
        bulkProgress: document.getElementById('irnBulkProgress'),
        bulkProgressFill: document.getElementById('irnBulkProgressFill'),
        bulkProgressText: document.getElementById('irnBulkProgressText'),
    };

    let rulesPage = 1;
    let bulkJobRunning = false;

    function esc(s) {
        return String(s ?? '').replace(/[&<>"']/g, function (c) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];
        });
    }

    async function api(payload, timeoutMs) {
        const opts = {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify(payload),
        };
        if (typeof AbortSignal !== 'undefined' && AbortSignal.timeout) {
            opts.signal = AbortSignal.timeout(timeoutMs || 120000);
        }
        const res = await fetch(API, opts);
        const json = await res.json().catch(function () { return {}; });
        if (!res.ok || json.success === false) {
            const err = json.message || json.error || ('HTTP ' + res.status);
            if (res.status === 524 || res.status === 504) {
                throw new Error('Timeout server (HTTP ' + res.status + '). Ollama procesează prea lent — încearcă mai puține produse odată.');
            }
            throw new Error(err);
        }
        return json;
    }

    function sourcePill(source) {
        const map = {
            override: ['Regulă', 'green'],
            patterns: ['Pattern', 'amber'],
            unchanged: ['Neschimbat', 'red'],
            custom: ['Custom', 'blue'],
            base: ['Base', 'slate'],
            ollama: ['Ollama', 'violet'],
        };
        const item = map[source] || [source || '—', 'slate'];
        return '<span class="irv-pill irv-pill--' + esc(item[1]) + '">' + esc(item[0]) + '</span>';
    }

    async function loadBulkStats() {
        try {
            const json = await api({ action: 'name_normalize_bulk_stats', status: 'pending' }, 30000);
            if (els.bulkPending) {
                els.bulkPending.textContent = 'Pending: ' + String(json.total ?? '—');
            }
        } catch (e) {
            if (els.bulkPending) els.bulkPending.textContent = 'Pending: —';
        }
    }

    function setBulkProgress(pct, text) {
        if (els.bulkProgress) els.bulkProgress.hidden = false;
        if (els.bulkProgressFill) els.bulkProgressFill.style.width = Math.max(0, Math.min(100, pct)) + '%';
        if (els.bulkProgressText) els.bulkProgressText.textContent = text || '';
    }

    function setBulkButtonsDisabled(disabled) {
        bulkJobRunning = disabled;
        if (els.bulkRulesBtn) els.bulkRulesBtn.disabled = disabled;
        if (els.bulkOllamaBtn) els.bulkOllamaBtn.disabled = disabled;
    }

    async function runBulkJobChainFixed(phase) {
        if (typeof BesoiuAsync === 'undefined') {
            alert('Worker async indisponibil. Pornește worker-ul BesoiuAsync (CLI).');
            return;
        }
        setBulkButtonsDisabled(true);
        let payload = {
            phase: phase,
            status: 'pending',
            cursor_id: 0,
            processed_total: 0,
            updated_total: 0,
            ollama_total: 0,
            only_gaps: true,
            save_rules: !!(els.bulkSaveRules && els.bulkSaveRules.checked),
            chunk_size: 400,
            ollama_max: 12,
            time_budget_sec: 280,
        };

        try {
            for (let guard = 0; guard < 500; guard++) {
                const created = await BesoiuAsync.submitJob({
                    type: 'import.name_normalize_bulk',
                    queue: 'import',
                    payload: payload,
                    timeout_sec: 3600,
                });
                if (!created.success || !created.job_id) {
                    throw new Error(created.message || 'Nu am putut porni job-ul.');
                }

                const snap = await new Promise(function (resolve, reject) {
                    BesoiuAsync.watchJob(created.job_id, {
                        onProgress: function (s) {
                            setBulkProgress(Number(s.progress || 0), s.message || 'Se procesează…');
                        },
                        onDone: resolve,
                        onFailed: function (s) { reject(new Error(String(s.error || s.message || 'Job eșuat'))); },
                        onError: function (err) { reject(err instanceof Error ? err : new Error(String(err))); },
                    });
                });

                const result = (snap.result && typeof snap.result === 'object') ? snap.result : {};
                if (!result.needs_continue) {
                    setBulkProgress(100, result.message || 'Finalizat.');
                    alert(result.message || 'Normalizare masivă finalizată.');
                    break;
                }
                payload.cursor_id = Number(result.cursor_id || payload.cursor_id || 0);
                payload.processed_total = Number(result.processed_total || 0);
                payload.updated_total = Number(result.updated_total || 0);
                payload.ollama_total = Number(result.ollama_total || 0);
                setBulkProgress(95, result.message || 'Continuă…');
            }
        } catch (e) {
            alert(e.message || String(e));
        } finally {
            setBulkButtonsDisabled(false);
            loadBulkStats();
            loadStatus();
        }
    }

    els.bulkRulesBtn?.addEventListener('click', function () {
        if (bulkJobRunning) return;
        if (!confirm('Aplic regulile din fișier pe TOATE produsele pending din coadă?')) return;
        runBulkJobChainFixed('rules');
    });
    els.bulkOllamaBtn?.addEventListener('click', function () {
        if (bulkJobRunning) return;
        if (!confirm('Pornesc Ollama în fundal pe produsele fără regulă explicită? (poate dura ore pentru mii de produse)')) return;
        runBulkJobChainFixed('ollama');
    });

    async function loadStatus() {
        try {
            const json = await api({ action: 'name_normalize_status' });
            const st = json.status || {};
            const ollama = st.ollama || {};
            const ready = !!ollama.ready;
            if (els.ollamaBadge) {
                els.ollamaBadge.textContent = ready
                    ? ('OK · ' + (ollama.text_model || 'model'))
                    : (ollama.message_ro || 'Indisponibil');
            }
            if (els.statOllama) {
                els.statOllama.className = 'irn-stat ' + (ready ? 'is-ok' : 'is-warn');
            }
            if (els.rulesBadge) els.rulesBadge.textContent = String(st.rules_total || 0);
            if (els.baseBadge) els.baseBadge.textContent = String(st.rules_base_lines || 0) + ' linii';
            if (els.customBadge) els.customBadge.textContent = String(st.rules_custom_lines || 0) + ' linii';
        } catch (e) {
            if (els.ollamaBadge) els.ollamaBadge.textContent = 'Offline';
            if (els.statOllama) els.statOllama.className = 'irn-stat is-warn';
        }
    }

    function renderPreview(preview) {
        if (!preview || !els.pipeline) return;
        if (els.pipelineEmpty) els.pipelineEmpty.hidden = true;
        els.pipeline.hidden = false;
        if (els.ollamaResult) els.ollamaResult.hidden = true;
        els.pipeRaw.textContent = preview.raw || '—';
        const ruleText = preview.override || preview.cleaned || preview.final || '(fără regulă directă)';
        els.pipeRule.textContent = preview.rule_source && preview.rule_source !== preview.source
            ? (ruleText + ' → ' + (preview.final || ''))
            : ruleText;
        els.pipeFinal.textContent = preview.final || '—';
        let meta = sourcePill(preview.source);
        if (preview.ollama_used && preview.ollama) {
            meta += ' <span class="irv-pill irv-pill--violet">Ollama · ' + esc(String(preview.ollama.confidence ?? '—')) + '</span>';
        } else if (preview.rule_source && preview.rule_source !== 'override') {
            meta += ' <span style="margin-left:6px">Reguli: ' + esc(preview.rule_source) + '</span>';
        }
        if (preview.patterns_applied && preview.patterns_applied.length) {
            meta += ' <span style="margin-left:6px">Pattern: ' + esc(preview.patterns_applied.join(', ')) + '</span>';
        }
        els.pipeSource.innerHTML = meta;
        if (preview.ollama_used && preview.ollama && els.ollamaResult) {
            els.ollamaResult.hidden = false;
            els.ollamaResult.innerHTML =
                '<p class="irn-ollama-card__title">Filtru Ollama aplicat · confidence ' + esc(String(preview.ollama.confidence ?? '—')) + '</p>'
                + '<p>' + esc(preview.ollama.reasoning || '') + '</p>'
                + (preview.ollama.rule_line
                    ? '<code class="irn-ollama-card__rule">' + esc(preview.ollama.rule_line) + '</code>'
                    : '');
        }
    }

    async function runPreview() {
        const raw = (els.rawName && els.rawName.value || '').trim();
        if (!raw) { alert('Introdu denumirea TecDoc.'); return; }
        const useOllama = !els.useOllamaFilter || els.useOllamaFilter.checked;
        const json = await api({
            action: 'name_normalize_preview',
            raw_name: raw,
            brand: (els.brand && els.brand.value || '').trim(),
            use_ollama: useOllama,
        });
        renderPreview(json.preview || {});
    }

    async function runOllama() {
        const raw = (els.rawName && els.rawName.value || '').trim();
        if (!raw) { alert('Introdu denumirea TecDoc.'); return; }
        if (els.ollamaBtn) { els.ollamaBtn.disabled = true; els.ollamaBtn.innerHTML = '<span>Se procesează…</span>'; }
        try {
            const json = await api({ action: 'name_normalize_ollama', raw_name: raw, brand: (els.brand && els.brand.value || '').trim() });
            const r = json.result || {};
            if (els.ollamaResult) {
                els.ollamaResult.hidden = false;
                els.ollamaResult.innerHTML =
                    '<p class="irn-ollama-card__title">Sugestie Ollama · confidence ' + esc(String(r.confidence ?? '—')) + '</p>'
                    + '<code class="irn-ollama-card__rule">' + esc(r.rule_line || '') + '</code>'
                    + '<p>' + esc(r.reasoning || '') + '</p>'
                    + '<div class="irn-ollama-card__actions">'
                    + '<button type="button" class="irn-btn irn-btn--save irn-btn--compact" id="irnUseOllamaRule">Salvează ca regulă</button>'
                    + '<button type="button" class="irn-btn irn-btn--ghost irn-btn--compact" id="irnUseOllamaPreview">Previzualizează</button>'
                    + '</div>';
                document.getElementById('irnUseOllamaRule')?.addEventListener('click', function () {
                    openRuleModal({ aliases: (r.aliases || [raw]).join(' | '), target: r.normalized || '' });
                });
                document.getElementById('irnUseOllamaPreview')?.addEventListener('click', function () {
                    renderPreview({ raw: raw, override: r.normalized, final: r.normalized, source: 'ollama', patterns_applied: [], cleaned: r.normalized });
                });
            }
        } finally {
            if (els.ollamaBtn) {
                els.ollamaBtn.disabled = false;
                els.ollamaBtn.innerHTML = '<i data-lucide="sparkles" style="width:16px;height:16px"></i> Sugerează cu Ollama';
                if (window.lucide) window.lucide.createIcons();
            }
        }
    }

    async function loadRules(page) {
        rulesPage = page || 1;
        const json = await api({
            action: 'name_normalize_rules_list',
            page: rulesPage,
            per_page: 25,
            search: (els.ruleSearch && els.ruleSearch.value || '').trim(),
            source: els.ruleSource ? els.ruleSource.value : 'all',
        });
        const rules = json.rules || [];
        if (!els.rulesBody) return;
        if (!rules.length) {
            els.rulesBody.innerHTML = '<tr><td colspan="4" class="irv-td irn-table-empty">Nicio regulă găsită.</td></tr>';
        } else {
            els.rulesBody.innerHTML = rules.map(function (rule) {
                const editable = !!rule.editable;
                return '<tr>'
                    + '<td class="irv-td"><span class="irn-alias">' + esc(rule.aliases_text) + '</span></td>'
                    + '<td class="irv-td"><span class="irn-target">' + esc(rule.target) + '</span></td>'
                    + '<td class="irv-td">' + sourcePill(rule.source) + '</td>'
                    + '<td class="irv-td irv-td--actions">'
                    + (editable
                        ? '<button type="button" class="irv-row-btn irv-row-btn--violet irn-edit-rule" data-line="' + esc(rule.line) + '" data-aliases="' + esc(rule.aliases_text) + '" data-target="' + esc(rule.target) + '">Editează</button>'
                        + '<button type="button" class="irv-row-btn irn-del-rule" data-line="' + esc(rule.line) + '">Șterge</button>'
                        : '<span class="irn-readonly">read-only</span>')
                    + '</td></tr>';
            }).join('');
        }
        if (els.rulesPager) {
            const totalPages = json.total_pages || 1;
            let pager = '';
            for (let p = 1; p <= Math.min(totalPages, 10); p++) {
                pager += '<button type="button" class="irv-btn irv-btn--sm ' + (p === rulesPage ? 'irv-btn--primary' : 'irv-btn--ghost') + '" data-page="' + p + '">' + p + '</button>';
            }
            if (totalPages > 10) pager += '<span class="irn-readonly" style="padding:8px">… ' + totalPages + ' pagini</span>';
            els.rulesPager.innerHTML = pager;
        }
    }

    function openRuleModal(data) {
        data = data || {};
        if (els.ruleLine) els.ruleLine.value = data.line || '';
        if (els.ruleAliases) els.ruleAliases.value = data.aliases || '';
        if (els.ruleTarget) els.ruleTarget.value = data.target || '';
        if (els.ruleModal) {
            els.ruleModal.classList.add('is-open');
            els.ruleModal.setAttribute('aria-hidden', 'false');
        }
        document.body.classList.add('irn-modal-open');
    }
    function closeRuleModal() {
        if (els.ruleModal) {
            els.ruleModal.classList.remove('is-open');
            els.ruleModal.setAttribute('aria-hidden', 'true');
        }
        document.body.classList.remove('irn-modal-open');
    }

    async function saveRule() {
        const payload = {
            action: 'name_normalize_rule_save',
            aliases: (els.ruleAliases && els.ruleAliases.value || '').trim(),
            target: (els.ruleTarget && els.ruleTarget.value || '').trim(),
        };
        const line = (els.ruleLine && els.ruleLine.value || '').trim();
        if (line) payload.line = parseInt(line, 10);
        const json = await api(payload);
        alert(json.message || 'Salvat.');
        closeRuleModal();
        await loadRules(rulesPage);
        await loadStatus();
        if (els.rawName && els.rawName.value.trim()) await runPreview();
    }

    function selectedQueueIds() {
        return Array.from(document.querySelectorAll('.irn-queue-check:checked')).map(function (el) {
            return parseInt(el.value, 10);
        }).filter(function (id) { return id > 0; });
    }
    function updateQueueApplyBtn() {
        if (els.queueApplySelectedBtn) els.queueApplySelectedBtn.disabled = selectedQueueIds().length === 0;
    }

    async function scanQueue() {
        if (els.queueBody) els.queueBody.innerHTML = '<tr><td colspan="7" class="irv-td irn-table-empty">Se scanează coada…</td></tr>';
        const json = await api({
            action: 'name_normalize_queue_scan',
            limit: 60,
            only_gaps: true,
            status: 'pending',
        }, 60000);
        const items = json.items || [];
        if (!els.queueBody) return;
        if (!items.length) {
            els.queueBody.innerHTML = '<tr><td colspan="7" class="irv-td"><div class="irn-empty-state irn-empty-state--table"><i data-lucide="check-circle" class="irn-empty-state__icon"></i><p>Toate produsele din eșantion au mapare OK.</p></div></td></tr>';
            if (window.lucide) window.lucide.createIcons();
            return;
        }
        els.queueBody.innerHTML = items.map(function (item) {
            return '<tr>'
                + '<td class="irv-td irv-td--check"><input type="checkbox" class="irn-queue-check" value="' + esc(item.id) + '"></td>'
                + '<td class="irv-td"><code class="irn-alias">' + esc(item.pCode) + '</code></td>'
                + '<td class="irv-td"><span class="irn-alias">' + esc(item.raw_art_name) + '</span></td>'
                + '<td class="irv-td"><span class="irn-target">' + esc(item.normalized) + '</span></td>'
                + '<td class="irv-td">' + esc(item.pSubcategory || '—') + '</td>'
                + '<td class="irv-td">' + sourcePill(item.source)
                + (item.needs_ollama ? ' <span class="irv-pill irv-pill--violet" title="Poate beneficia de Ollama la aplicare">AI?</span>' : '')
                + '</td>'
                + '<td class="irv-td irv-td--actions">'
                + '<button type="button" class="irv-row-btn irv-row-btn--teal irn-apply-one" data-id="' + esc(item.id) + '" data-raw="' + esc(item.raw_art_name) + '" data-normalized="' + esc(item.normalized) + '">Aplică</button>'
                + '<button type="button" class="irv-row-btn irv-row-btn--violet irn-ollama-one" data-raw="' + esc(item.raw_art_name) + '" data-brand="' + esc(item.pBrand) + '">Ollama</button>'
                + '</td></tr>';
        }).join('');
        updateQueueApplyBtn();
    }

    async function applyOne(id, normalized, rawArt, saveRule) {
        await api({ action: 'name_normalize_apply_one', id: id, normalized: normalized, raw_art_name: rawArt, save_rule: !!saveRule });
        await scanQueue();
        await loadStatus();
    }

    els.previewBtn?.addEventListener('click', function () { runPreview().catch(function (e) { alert(e.message); }); });
    els.ollamaBtn?.addEventListener('click', function () { runOllama().catch(function (e) { alert(e.message); }); });
    els.rawName?.addEventListener('keydown', function (e) { if (e.key === 'Enter') runPreview().catch(function (err) { alert(err.message); }); });
    els.rulesReload?.addEventListener('click', function () { loadRules(1).catch(function (e) { alert(e.message); }); });
    els.ruleSearch?.addEventListener('keydown', function (e) { if (e.key === 'Enter') loadRules(1).catch(function (err) { alert(err.message); }); });
    els.ruleSource?.addEventListener('change', function () { loadRules(1).catch(function (e) { alert(e.message); }); });
    els.rulesPager?.addEventListener('click', function (e) {
        const btn = e.target.closest('[data-page]');
        if (btn) loadRules(parseInt(btn.dataset.page, 10)).catch(function (err) { alert(err.message); });
    });
    els.addRuleBtn?.addEventListener('click', function () { openRuleModal({}); });
    els.ruleSaveBtn?.addEventListener('click', function () { saveRule().catch(function (e) { alert(e.message); }); });
    els.ruleModal?.addEventListener('click', function (e) { if (e.target.closest('[data-close]')) closeRuleModal(); });
    els.rulesBody?.addEventListener('click', function (e) {
        const edit = e.target.closest('.irn-edit-rule');
        if (edit) { openRuleModal({ line: edit.dataset.line, aliases: edit.dataset.aliases, target: edit.dataset.target }); return; }
        const del = e.target.closest('.irn-del-rule');
        if (del && confirm('Ștergi regula custom?')) {
            api({ action: 'name_normalize_rule_delete', line: parseInt(del.dataset.line, 10) })
                .then(function () { return loadRules(rulesPage); }).then(loadStatus)
                .catch(function (err) { alert(err.message); });
        }
    });
    els.queueScanBtn?.addEventListener('click', function () { scanQueue().catch(function (e) { alert(e.message); }); });
    els.queueCheckAll?.addEventListener('change', function () {
        document.querySelectorAll('.irn-queue-check').forEach(function (el) { el.checked = els.queueCheckAll.checked; });
        updateQueueApplyBtn();
    });
    els.queueBody?.addEventListener('change', function (e) {
        if (e.target.classList.contains('irn-queue-check')) updateQueueApplyBtn();
    });
    async function applySelectedBatch(ids, saveRules, useOllama) {
        let remaining = ids.slice();
        let totalApplied = 0;
        let totalOllama = 0;
        let batch = 0;
        while (remaining.length) {
            batch += 1;
            if (els.queueApplySelectedBtn) {
                els.queueApplySelectedBtn.textContent = useOllama
                    ? ('Batch ' + batch + '… (' + remaining.length + ' rămase)')
                    : ('Se aplică… (' + remaining.length + ')');
            }
            const json = await api({
                action: 'name_normalize_apply_selected',
                ids: remaining,
                save_rules: saveRules && batch === 1,
                use_ollama: useOllama,
                ollama_batch_max: 3,
            }, useOllama ? 95000 : 120000);
            totalApplied += json.applied || 0;
            totalOllama += json.ollama_applied || 0;
            remaining = (json.remaining_ids || []).map(function (id) { return parseInt(id, 10); }).filter(function (id) { return id > 0; });
            if (!json.has_more || !remaining.length) {
                break;
            }
        }
        return { applied: totalApplied, ollama_applied: totalOllama };
    }

    els.queueApplySelectedBtn?.addEventListener('click', function () {
        const ids = selectedQueueIds();
        if (!ids.length) return;
        const saveRules = confirm('Salvez și regulile custom? (OK = da)');
        const useOllama = !els.queueUseOllama || els.queueUseOllama.checked;
        if (els.queueApplySelectedBtn) els.queueApplySelectedBtn.disabled = true;
        applySelectedBatch(ids, saveRules, useOllama)
            .then(function (stats) {
                alert('Gata: ' + stats.applied + ' produse'
                    + (stats.ollama_applied ? (' (' + stats.ollama_applied + ' cu Ollama)') : '') + '.');
                return scanQueue();
            })
            .catch(function (e) { alert(e.message); })
            .finally(function () {
                if (els.queueApplySelectedBtn) {
                    els.queueApplySelectedBtn.textContent = 'Aplică selectate';
                    updateQueueApplyBtn();
                }
            });
    });
    els.queueBody?.addEventListener('click', function (e) {
        const apply = e.target.closest('.irn-apply-one');
        if (apply) {
            applyOne(parseInt(apply.dataset.id, 10), apply.dataset.normalized, apply.dataset.raw, confirm('Salvez regula în custom?'))
                .catch(function (err) { alert(err.message); });
            return;
        }
        const ollamaOne = e.target.closest('.irn-ollama-one');
        if (ollamaOne && els.rawName) {
            els.rawName.value = ollamaOne.dataset.raw || '';
            if (els.brand) els.brand.value = ollamaOne.dataset.brand || '';
            document.querySelector('.irn-workbench')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            runOllama().catch(function (err) { alert(err.message); });
        }
    });

    loadStatus();
    loadBulkStats();
    loadRules(1).catch(function () {});
    if (window.lucide && typeof window.lucide.createIcons === 'function') window.lucide.createIcons();
})();
</script>
