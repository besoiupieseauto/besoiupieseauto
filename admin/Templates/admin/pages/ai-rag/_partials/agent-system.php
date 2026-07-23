<?php declare(strict_types=1); ?>
<div class="ai-sys-pro">
    <section class="aiwork-hero" aria-label="AI Work live" style="background:linear-gradient(135deg,#0f172a,#115e59,#0d9488);border-radius:20px;padding:22px 24px;color:#fff;width:100%;box-sizing:border-box;">
        <header class="aiwork-hero__head">
            <div class="aiwork-hero__brand">
                <span class="aiwork-live-dot" id="aiwork-live-dot" aria-hidden="true"></span>
                <div>
                    <h2 class="aiwork-hero__title"><i class="fa-solid fa-bolt" aria-hidden="true"></i> AI Work</h2>
                    <p class="aiwork-hero__sub">Unde lucrează · ce întreabă · ce răspunde · ultimele 15 min · live</p>
                </div>
            </div>
            <div class="aiwork-hero__sync">
                <span id="aiwork-last-sync" class="aiwork-sync">Se sincronizează…</span>
            </div>
        </header>

        <div class="aiwork-kpis" id="aiwork-stats" aria-live="polite" style="display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;width:100%;margin-bottom:18px;">
            <article class="aiwork-kpi aiwork-kpi--blue" style="display:flex;align-items:center;gap:12px;background:rgba(255,255,255,0.13);border-radius:14px;padding:14px 16px;">
                <div class="aiwork-kpi__icon"><i class="fa-solid fa-list-check" aria-hidden="true"></i></div>
                <div class="aiwork-kpi__body">
                    <strong id="aiwork-stat-total" class="aiwork-kpi__val" style="font-size:1.6rem;color:#fff;">—</strong>
                    <span class="aiwork-kpi__lbl">Evenimente</span>
                </div>
            </article>
            <article class="aiwork-kpi aiwork-kpi--violet" style="display:flex;align-items:center;gap:12px;background:rgba(255,255,255,0.13);border-radius:14px;padding:14px 16px;">
                <div class="aiwork-kpi__icon"><i class="fa-solid fa-robot" aria-hidden="true"></i></div>
                <div class="aiwork-kpi__body">
                    <strong id="aiwork-stat-ollama" class="aiwork-kpi__val" style="font-size:1.6rem;color:#fff;">—</strong>
                    <span class="aiwork-kpi__lbl">Cu Ollama</span>
                </div>
            </article>
            <article class="aiwork-kpi aiwork-kpi--green" style="display:flex;align-items:center;gap:12px;background:rgba(255,255,255,0.13);border-radius:14px;padding:14px 16px;">
                <div class="aiwork-kpi__icon"><i class="fa-solid fa-circle-check" aria-hidden="true"></i></div>
                <div class="aiwork-kpi__body">
                    <strong id="aiwork-stat-rate" class="aiwork-kpi__val" style="font-size:1.6rem;color:#fff;">—</strong>
                    <span class="aiwork-kpi__lbl">Rată succes</span>
                </div>
            </article>
            <article class="aiwork-kpi aiwork-kpi--amber" style="display:flex;align-items:center;gap:12px;background:rgba(255,255,255,0.13);border-radius:14px;padding:14px 16px;">
                <div class="aiwork-kpi__icon"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i></div>
                <div class="aiwork-kpi__body">
                    <strong id="aiwork-stat-fail" class="aiwork-kpi__val" style="font-size:1.6rem;color:#fff;">—</strong>
                    <span class="aiwork-kpi__lbl">Eșecuri</span>
                </div>
            </article>
        </div>

        <div id="aiwork-feed" class="aiwork-table-wrap" style="background:#fff;border-radius:16px;overflow:hidden;">
            <div class="aiwork-empty aiwork-empty--on-light">
                <i class="fa-solid fa-spinner fa-spin" aria-hidden="true"></i>
                <span>Se încarcă activitatea AI…</span>
            </div>
        </div>
    </section>

    <div class="ai-sys-pro__grid" style="display:grid;grid-template-columns:1fr 1fr;gap:16px;width:100%;">
        <article class="ai-sys-panel">
            <header class="ai-sys-panel__head">
                <h3><i class="fa-solid fa-shield-halved" aria-hidden="true"></i> Audit sistem</h3>
                <span class="ai-rag-meta" id="ollama-audit-summary">—</span>
            </header>
            <div class="ai-sys-panel__actions">
                <button type="button" id="ollama-run-audit" class="ai-hub-btn ai-hub-btn--primary">
                    <i class="fa-solid fa-play" aria-hidden="true"></i> Rulează
                </button>
                <button type="button" id="ollama-verify-structure" class="ai-hub-btn ai-hub-btn--ghost">Structură</button>
                <button type="button" id="ollama-verify-live" class="ai-hub-btn ai-hub-btn--ghost">Live Ollama</button>
            </div>
            <div id="ollama-audit-steps" class="ollama-audit-grid ai-sys-audit-grid">
                <div class="aiwork-empty aiwork-empty--sm">Apasă «Rulează» pentru verificare.</div>
            </div>
            <details class="ai-sys-details" id="ollama-checkpoints-details">
                <summary><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i> Checkpoint-uri vechi</summary>
                <div id="ollama-opportunities" class="ollama-opportunities"><div class="aiwork-empty aiwork-empty--sm">—</div></div>
            </details>
        </article>

        <article class="ai-sys-panel">
            <header class="ai-sys-panel__head">
                <h3><i class="fa-solid fa-file-lines" aria-hidden="true"></i> Rezumate log</h3>
            </header>
            <p class="ai-sys-panel__hint">Dacă Ollama e offline, primești sumar local instant — fără timeout.</p>
            <div class="ai-sys-panel__actions">
                <button type="button" id="ai-rag-preview-sources" class="ai-hub-btn ai-hub-btn--ghost">Preview</button>
                <button type="button" id="ai-rag-generate-summary" class="ai-hub-btn ai-hub-btn--primary">
                    <i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i> Generează
                </button>
            </div>
            <pre id="ai-rag-summary-output" class="ai-rag-pre hidden"></pre>
            <div id="ai-rag-summaries-list" class="ai-rag-summaries"></div>
        </article>
    </div>

    <article class="ai-sys-panel ai-sys-panel--wide">
        <header class="ai-sys-panel__head">
            <h3><i class="fa-solid fa-plug" aria-hidden="true"></i> Integrări &amp; erori</h3>
            <button type="button" id="ai-system-refresh" class="ai-hub-btn ai-hub-btn--ghost ai-sys-refresh">
                <i class="fa-solid fa-rotate" aria-hidden="true"></i> Reîmprospătează
            </button>
        </header>
        <div id="ollama-integrations" class="ollama-integrations ai-sys-integrations">
            <div class="aiwork-empty aiwork-empty--sm">Se încarcă…</div>
        </div>
        <pre id="ollama-test-result" class="ai-rag-pre hidden" aria-live="polite"></pre>
        <h4 class="ai-sys-errors-title"><i class="fa-solid fa-bug" aria-hidden="true"></i> Erori recente Ollama</h4>
        <div id="ollama-errors" class="ollama-errors ai-sys-errors">
            <div class="aiwork-empty aiwork-empty--sm">—</div>
        </div>
    </article>
</div>
