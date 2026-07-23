<?php
/** Panou: ierarhie / comparare furnizori la import (ordine scanare, omit, reguli preț). */
?>
<style>
  .furnizori-page .fz-price-panel {
    --pl-accent: #14b8a6;
    --pl-accent-soft: #f0fdfa;
    --pl-ink: #0f172a;
    --pl-muted: #64748b;
    --pl-border: #e2e8f0;
    --pl-success: #16a34a;
  }
  .furnizori-page .fz-price-panel.pl-loading { opacity: .72; pointer-events: none; }

  /* Intro — ce face pagina */
  .furnizori-page .fz-price-panel .pl-intro {
    display: grid; gap: 14px;
    padding: 16px 18px; margin-bottom: 20px;
    border: 1px solid #99f6e4; border-radius: 14px;
    background: linear-gradient(135deg, #f0fdfa 0%, #f8fafc 100%);
  }
  .furnizori-page .fz-price-panel .pl-intro-head {
    display: flex; flex-wrap: wrap; align-items: flex-start; justify-content: space-between; gap: 10px;
  }
  .furnizori-page .fz-price-panel .pl-intro-title {
    margin: 0; font-size: 1rem; font-weight: 800; color: var(--pl-ink);
  }
  .furnizori-page .fz-price-panel .pl-intro-lead {
    margin: 4px 0 0; font-size: 0.875rem; line-height: 1.55; color: #334155; max-width: 72ch;
  }
  .furnizori-page .fz-price-panel .pl-intro-toggle {
    border: 1px solid #99f6e4; background: #fff; color: #0f766e;
    border-radius: 999px; padding: 6px 12px; font-size: 0.75rem; font-weight: 700; cursor: pointer;
  }
  .furnizori-page .fz-price-panel .pl-flow {
    display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 8px;
  }
  @media (max-width: 900px) {
    .furnizori-page .fz-price-panel .pl-flow { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  }
  @media (max-width: 520px) {
    .furnizori-page .fz-price-panel .pl-flow { grid-template-columns: 1fr; }
  }
  .furnizori-page .fz-price-panel .pl-flow-step {
    position: relative; padding: 12px 12px 12px 14px;
    border: 1px solid var(--pl-border); border-radius: 12px; background: #fff;
  }
  .furnizori-page .fz-price-panel .pl-flow-step strong {
    display: block; font-size: 0.75rem; color: var(--pl-accent); margin-bottom: 4px;
  }
  .furnizori-page .fz-price-panel .pl-flow-step span {
    display: block; font-size: 0.8125rem; line-height: 1.45; color: #475569;
  }
  .furnizori-page .fz-price-panel .pl-intro.is-collapsed .pl-flow { display: none; }

  /* Rezumat live */
  .furnizori-page .fz-price-panel .pl-live-summary {
    display: flex; flex-wrap: wrap; gap: 8px 12px; align-items: center;
    padding: 12px 14px; margin-bottom: 18px;
    border: 1px dashed #cbd5e1; border-radius: 12px; background: #f8fafc;
    font-size: 0.8125rem; color: #475569;
  }
  .furnizori-page .fz-price-panel .pl-live-summary strong { color: var(--pl-ink); }
  .furnizori-page .fz-price-panel .pl-live-pill {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 4px 10px; border-radius: 999px;
    background: #fff; border: 1px solid var(--pl-border);
    font-size: 0.75rem; font-weight: 600; color: #334155;
  }
  .furnizori-page .fz-price-panel .pl-live-summary.is-warn {
    border-color: #fca5a5; background: #fef2f2; color: #991b1b;
  }
  .furnizori-page .fz-price-panel .pl-live-summary.is-warn strong { color: #7f1d1d; }
  .furnizori-page .fz-price-panel .pl-blocked-freeze {
    display: none;
    margin-bottom: 16px;
    padding: 12px 14px;
    border-radius: 12px;
    border: 1px solid #fca5a5;
    background: #fef2f2;
    color: #991b1b;
    font-size: 0.8125rem;
    line-height: 1.5;
  }
  .furnizori-page .fz-price-panel .pl-blocked-freeze.is-visible { display: block; }
  .furnizori-page .fz-price-panel .pl-blocked-freeze strong { color: #7f1d1d; }
  .furnizori-page .fz-price-panel .pl-blocked-freeze ul {
    margin: 8px 0 0; padding-left: 18px;
  }

  /* Wizard steps */
  .furnizori-page .fz-price-panel .pl-wizard-nav {
    display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 8px; margin-bottom: 18px;
  }
  @media (max-width: 768px) {
    .furnizori-page .fz-price-panel .pl-wizard-nav { grid-template-columns: 1fr 1fr; }
  }
  .furnizori-page .fz-price-panel .pl-wizard-step {
    display: flex; align-items: flex-start; gap: 10px; width: 100%;
    padding: 12px 12px; border: 2px solid var(--pl-border); border-radius: 12px;
    background: #fff; text-align: left; cursor: pointer;
    transition: border-color .15s, background .15s, box-shadow .15s;
  }
  .furnizori-page .fz-price-panel .pl-wizard-step:hover { border-color: #99f6e4; background: #f8fbff; }
  .furnizori-page .fz-price-panel .pl-wizard-step.active {
    border-color: var(--pl-accent); background: var(--pl-accent-soft);
    box-shadow: 0 0 0 3px rgba(20, 184, 166, .12);
  }
  .furnizori-page .fz-price-panel .pl-wizard-step.done { border-color: #86efac; }
  .furnizori-page .fz-price-panel .pl-wizard-num {
    flex-shrink: 0; width: 28px; height: 28px; border-radius: 999px;
    display: inline-flex; align-items: center; justify-content: center;
    background: #e2e8f0; color: #475569; font-size: 0.8125rem; font-weight: 800;
  }
  .furnizori-page .fz-price-panel .pl-wizard-step.active .pl-wizard-num { background: var(--pl-accent); color: #fff; }
  .furnizori-page .fz-price-panel .pl-wizard-step.done .pl-wizard-num { background: var(--pl-success); color: #fff; }
  .furnizori-page .fz-price-panel .pl-wizard-text strong {
    display: block; font-size: 0.8125rem; color: var(--pl-ink); line-height: 1.3;
  }
  .furnizori-page .fz-price-panel .pl-wizard-text span {
    display: block; margin-top: 2px; font-size: 0.6875rem; color: var(--pl-muted); line-height: 1.35;
  }

  .furnizori-page .fz-price-panel .pl-sub-pane { display: none; }
  .furnizori-page .fz-price-panel .pl-sub-pane.active { display: block; }

  .furnizori-page .fz-price-panel .pl-step-head {
    margin-bottom: 16px; padding-bottom: 14px; border-bottom: 1px solid var(--pl-border);
  }
  .furnizori-page .fz-price-panel .pl-step-head h3 {
    margin: 0 0 6px; font-size: 1.05rem; font-weight: 800; color: var(--pl-ink);
  }
  .furnizori-page .fz-price-panel .pl-step-head p {
    margin: 0; font-size: 0.875rem; line-height: 1.55; color: #475569; max-width: 70ch;
  }
  .furnizori-page .fz-price-panel .pl-step-action {
    display: inline-flex; align-items: center; gap: 6px; margin-top: 10px;
    padding: 6px 10px; border-radius: 8px; background: #ecfdf5; border: 1px solid #a7f3d0;
    font-size: 0.75rem; font-weight: 700; color: #047857;
  }

  .furnizori-page .fz-price-panel .pl-section {
    border: 1px solid var(--pl-border); border-radius: 12px; background: #f8fafc; padding: 16px;
  }
  .furnizori-page .fz-price-panel .pl-section + .pl-section { margin-top: 14px; }
  .furnizori-page .fz-price-panel .pl-section-title {
    font-size: 0.875rem; font-weight: 700; color: var(--pl-ink); margin: 0 0 4px;
  }
  .furnizori-page .fz-price-panel .pl-section-hint {
    font-size: 0.8125rem; color: var(--pl-muted); margin: 0 0 14px; line-height: 1.5;
  }

  /* Ordine scanare */
  .furnizori-page .fz-price-panel .pl-scan-legend {
    display: flex; flex-wrap: wrap; gap: 8px 14px; margin-bottom: 14px;
    padding: 10px 12px; border-radius: 10px; background: #fff; border: 1px solid #99f6e4;
    font-size: 0.75rem; color: #0a3d31;
  }
  .furnizori-page .fz-price-panel .pl-scan-legend span { display: inline-flex; align-items: center; gap: 6px; }
  .furnizori-page .fz-price-panel .pl-scan-legend i {
    display: inline-block; width: 10px; height: 10px; border-radius: 999px;
  }
  .furnizori-page .fz-price-panel .pl-scan-order {
    list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 8px;
  }
  .furnizori-page .fz-price-panel .pl-scan-row {
    display: grid; grid-template-columns: auto 1fr auto auto; align-items: center; gap: 10px;
    padding: 12px 14px; border: 1px solid var(--pl-border); border-radius: 12px; background: #fff;
    transition: transform .28s ease, box-shadow .28s ease, border-color .28s ease;
  }
  .furnizori-page .fz-price-panel .pl-scan-row.is-moving {
    transform: scale(1.01); box-shadow: 0 8px 20px rgba(20, 184, 166, 0.14); border-color: #99f6e4;
  }
  .furnizori-page .fz-price-panel .pl-scan-row--tier {
    border-color: #99f6e4; background: linear-gradient(90deg, #f0fdfa 0%, #fff 42%);
  }
  .furnizori-page .fz-price-panel .pl-scan-rank {
    display: inline-flex; align-items: center; justify-content: center;
    min-width: 34px; height: 34px; border-radius: 10px;
    font-size: 0.875rem; font-weight: 800;
  }
  .furnizori-page .fz-price-panel .pl-scan-rank--1 { background: #14b8a6; color: #fff; }
  .furnizori-page .fz-price-panel .pl-scan-rank--2 { background: #2dd4bf; color: #fff; }
  .furnizori-page .fz-price-panel .pl-scan-rank--3 { background: #5eead4; color: #fff; }
  .furnizori-page .fz-price-panel .pl-scan-rank--n { background: #f1f5f9; color: #64748b; border: 1px solid var(--pl-border); }
  .furnizori-page .fz-price-panel .pl-scan-name { display: block; font-size: 0.9375rem; font-weight: 700; color: var(--pl-ink); }
  .furnizori-page .fz-price-panel .pl-scan-code { display: block; font-size: 0.75rem; color: #94a3b8; margin-top: 2px; }
  .furnizori-page .fz-price-panel .pl-scan-grip { color: #cbd5e1; font-size: 1rem; letter-spacing: -2px; user-select: none; }
  .furnizori-page .fz-price-panel .pl-scan-actions { display: inline-flex; gap: 4px; }
  .furnizori-page .fz-price-panel .pl-scan-btn {
    width: 34px; height: 34px; border: 1px solid #cbd5e1; border-radius: 8px;
    background: #fff; color: #475569; font-size: 0.875rem; font-weight: 700; cursor: pointer;
  }
  .furnizori-page .fz-price-panel .pl-scan-btn:hover:not(:disabled) { background: #f0fdfa; border-color: #99f6e4; color: #14b8a6; }
  .furnizori-page .fz-price-panel .pl-scan-btn:disabled { opacity: .35; cursor: not-allowed; }

  /* Excluderi — toggle cards */
  .furnizori-page .fz-price-panel .pl-omit-grid {
    display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 10px;
  }
  .furnizori-page .fz-price-panel .pl-omit-card {
    display: flex; flex-direction: column; gap: 4px; align-items: flex-start;
    padding: 12px 14px; border: 2px solid var(--pl-border); border-radius: 12px;
    background: #fff; cursor: pointer; text-align: left; transition: all .15s;
  }
  .furnizori-page .fz-price-panel .pl-omit-card:hover { border-color: #99f6e4; }
  .furnizori-page .fz-price-panel .pl-omit-card.is-active { border-color: #86efac; background: #f0fdf4; }
  .furnizori-page .fz-price-panel .pl-omit-card.is-omitted { border-color: #fca5a5; background: #fef2f2; }
  .furnizori-page .fz-price-panel .pl-omit-card-name { font-size: 0.875rem; font-weight: 700; color: var(--pl-ink); }
  .furnizori-page .fz-price-panel .pl-omit-card-code { font-size: 0.6875rem; color: #94a3b8; font-weight: 600; }
  .furnizori-page .fz-price-panel .pl-omit-card-state {
    margin-top: 4px; font-size: 0.6875rem; font-weight: 700; text-transform: uppercase; letter-spacing: .03em;
  }
  .furnizori-page .fz-price-panel .pl-omit-card.is-active .pl-omit-card-state { color: #047857; }
  .furnizori-page .fz-price-panel .pl-omit-card.is-omitted .pl-omit-card-state { color: #b91c1c; }

  .furnizori-page .fz-price-panel .pl-brand-toolbar {
    display: grid; grid-template-columns: 1fr 1fr auto; gap: 10px; align-items: end;
  }
  @media (max-width: 640px) {
    .furnizori-page .fz-price-panel .pl-brand-toolbar { grid-template-columns: 1fr; }
  }
  .furnizori-page .fz-price-panel .pl-brand-toolbar label span {
    display: block; font-size: 0.8125rem; font-weight: 600; color: #334155; margin-bottom: 4px;
  }
  .furnizori-page .fz-price-panel .pl-brand-rules { margin-top: 14px; display: flex; flex-direction: column; gap: 8px; }
  .furnizori-page .fz-price-panel .pl-brand-rule {
    display: flex; flex-wrap: wrap; align-items: center; gap: 8px;
    padding: 10px 12px; border: 1px solid var(--pl-border); border-radius: 10px; background: #fff;
  }
  .furnizori-page .fz-price-panel .pl-brand-rule-supplier { font-size: 0.8125rem; font-weight: 700; min-width: 120px; }
  .furnizori-page .fz-price-panel .pl-brand-rule-chips { display: flex; flex-wrap: wrap; gap: 6px; flex: 1; }
  .furnizori-page .fz-price-panel .pl-brand-chip {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 4px 10px; border-radius: 999px; border: 1px solid #cbd5e1;
    background: #f1f5f9; font-size: 0.75rem; font-weight: 600;
  }
  .furnizori-page .fz-price-panel .pl-brand-chip button {
    border: none; background: transparent; color: #94a3b8; cursor: pointer; font-size: 1rem; line-height: 1;
  }
  .furnizori-page .fz-price-panel .pl-brand-chip button:hover { color: #dc2626; }

  /* Reguli comparare */
  .furnizori-page .fz-price-panel .pl-rules-grid {
    display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px;
  }
  @media (max-width: 768px) {
    .furnizori-page .fz-price-panel .pl-rules-grid { grid-template-columns: 1fr; }
  }
  .furnizori-page .fz-price-panel .pl-field label > span {
    display: block; font-size: 0.875rem; font-weight: 700; color: var(--pl-ink); margin-bottom: 6px;
  }
  .furnizori-page .fz-price-panel .pl-field-help {
    margin: 6px 0 0; font-size: 0.75rem; line-height: 1.45; color: var(--pl-muted);
  }
  .furnizori-page .fz-price-panel .pl-example-box {
    margin-top: 16px; padding: 14px 16px; border-radius: 12px;
    border: 1px solid #fde68a; background: #fffbeb;
    font-size: 0.8125rem; line-height: 1.55; color: #78350f;
  }
  .furnizori-page .fz-price-panel .pl-example-box strong { color: #92400e; }

  /* Stoc zero */
  .furnizori-page .fz-price-panel .pl-stock-note {
    display: flex; gap: 10px; align-items: flex-start;
    padding: 12px 14px; margin-bottom: 14px; border-radius: 10px;
    background: #fff; border: 1px solid #fde68a; font-size: 0.8125rem; color: #78350f;
  }
  .furnizori-page .fz-price-panel .pl-stock-table th { font-size: 0.6875rem; }
  .furnizori-page .fz-price-panel .pl-stock-table td { vertical-align: middle; }
  .furnizori-page .fz-price-panel .pl-stock-help {
    display: block; margin-top: 4px; font-size: 0.6875rem; color: #94a3b8; line-height: 1.35;
  }

  /* Footer acțiuni */
  .furnizori-page .fz-price-panel .pl-footer {
    margin-top: 22px; padding-top: 18px; border-top: 1px solid var(--pl-border);
    display: flex; flex-wrap: wrap; align-items: center; gap: 10px;
  }
  .furnizori-page .fz-price-panel .pl-footer-nav { display: flex; gap: 8px; margin-right: auto; }
  .furnizori-page .fz-price-panel .pl-footer-save { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
  .furnizori-page .fz-price-panel .pl-footer-hint {
    width: 100%; margin: 0; font-size: 0.75rem; color: var(--pl-muted); line-height: 1.45;
  }
  .furnizori-page .fz-price-panel #pl-status { font-size: 0.8125rem; font-weight: 600; }
  .furnizori-page .fz-price-panel #pl-status.is-ok { color: var(--pl-success); }
  .furnizori-page .fz-price-panel #pl-status.is-err { color: #dc2626; }

  @media (max-width: 640px) {
    .furnizori-page .fz-price-panel .pl-scan-row {
      grid-template-columns: auto 1fr auto;
      grid-template-areas: "rank main actions" "rank main actions";
    }
    .furnizori-page .fz-price-panel .pl-scan-grip { display: none; }
  }
</style>

<div id="price-logic-panel" class="fz-price-panel box rounded-xl border bg-white p-5 md:p-6">

  <div class="pl-intro" id="pl-intro">
    <div class="pl-intro-head">
      <div>
        <h2 class="pl-intro-title">Cum alege sistemul furnizorul și prețul</h2>
        <p class="pl-intro-lead">
          Când același cod produs apare la mai mulți furnizori, sistemul parcurge pașii de mai jos — în ordinea setată de tine — și decide cine câștigă.
        </p>
      </div>
      <button type="button" class="pl-intro-toggle" id="pl-intro-toggle" aria-expanded="true">Ascunde explicația</button>
    </div>
    <div class="pl-flow" id="pl-flow">
      <div class="pl-flow-step"><strong>Pas 1</strong><span>Stabilești ordinea — cine e verificat primul.</span></div>
      <div class="pl-flow-step"><strong>Pas 2</strong><span>Scoți furnizori sau branduri care nu contează.</span></div>
      <div class="pl-flow-step"><strong>Pas 3</strong><span>Alegi cum comparăm stocul, brandul și prețul.</span></div>
      <div class="pl-flow-step"><strong>Pas 4</strong><span>Definești ce se întâmplă când stocul e zero la sursă.</span></div>
    </div>
  </div>

  <div class="pl-live-summary" id="pl-live-summary" aria-live="polite">
    <strong>Rezumat:</strong> <span id="pl-live-text">Se încarcă configurația…</span>
  </div>

  <div class="pl-blocked-freeze" id="pl-blocked-freeze" role="status">
    <strong>Furnizori blocați — freeze total</strong>
    <p style="margin:6px 0 0">Acești furnizori sunt opriți peste tot: nu apar în comparare, nu se importă fișiere în Import Pro, nu participă la scanare/cron. Reactivează-i din tab „Furnizori blocați”.</p>
    <ul id="pl-blocked-list"></ul>
  </div>

  <div class="pl-wizard-nav" role="tablist" aria-label="Pași configurare comparare furnizori">
    <button type="button" class="pl-wizard-step active" data-pl-tab="scan-order" role="tab" aria-selected="true">
      <span class="pl-wizard-num">1</span>
      <span class="pl-wizard-text"><strong>Prioritate furnizori</strong><span>Cine e verificat primul</span></span>
    </button>
    <button type="button" class="pl-wizard-step" data-pl-tab="exclusions" role="tab" aria-selected="false">
      <span class="pl-wizard-num">2</span>
      <span class="pl-wizard-text"><strong>Excluderi</strong><span>Cine nu participă</span></span>
    </button>
    <button type="button" class="pl-wizard-step" data-pl-tab="rules" role="tab" aria-selected="false">
      <span class="pl-wizard-num">3</span>
      <span class="pl-wizard-text"><strong>Reguli preț</strong><span>Cum câștigă oferta</span></span>
    </button>
    <button type="button" class="pl-wizard-step" data-pl-tab="stock-zero" role="tab" aria-selected="false">
      <span class="pl-wizard-num">4</span>
      <span class="pl-wizard-text"><strong>Stoc zero</strong><span>Comportament la import</span></span>
    </button>
  </div>

  <!-- PAS 1 -->
  <div class="pl-sub-pane active" data-pl-pane="scan-order" role="tabpanel">
    <div class="pl-step-head">
      <h3>Pas 1 — Prioritatea furnizorilor</h3>
      <p>Furnizorul de pe poziția 1 are cea mai mare prioritate. Folosește săgețile ↑ ↓ pentru a schimba ordinea. Primii N furnizori (implicit 3) formează grupul care se compară întâi la preț.</p>
      <span class="pl-step-action">✓ Acțiune: mută furnizorii importanti sus în listă</span>
    </div>
    <div class="pl-section">
      <div class="pl-scan-legend" aria-hidden="true">
        <span><i style="background:#14b8a6"></i> Poziția 1 — prioritate maximă</span>
        <span><i style="background:#5eead4"></i> Top 3 — grup comparat primul</span>
        <span><i style="background:#e2e8f0"></i> Restul — verificați după grup</span>
      </div>
      <ul id="pl-scan-order" class="pl-scan-order min-h-[120px]"></ul>
    </div>
  </div>

  <!-- PAS 2 -->
  <div class="pl-sub-pane" data-pl-pane="exclusions" role="tabpanel">
    <div class="pl-step-head">
      <h3>Pas 2 — Excluderi</h3>
      <p>Click pe un furnizor pentru a-l scoate din comparare (devine roșu). Click din nou pentru a-l reactiva. Poți ignora și anumite branduri de la un furnizor anume.</p>
      <span class="pl-step-action">✓ Acțiune: dezactivează furnizorii pe care nu vrei să-i folosești</span>
    </div>
    <div class="pl-section">
      <h4 class="pl-section-title">Furnizori în comparare</h4>
      <p class="pl-section-hint">Verde = participă la alegerea prețului. Roșu = ignorat complet.</p>
      <div id="pl-omit-grid" class="pl-omit-grid" role="group" aria-label="Furnizori incluși sau excluși"></div>
      <select id="pl-omit-select" class="hidden" multiple aria-hidden="true" tabindex="-1"></select>
    </div>
    <div class="pl-section">
      <h4 class="pl-section-title">Branduri ignorate (opțional)</h4>
      <p class="pl-section-hint">Exemplu: ignori brandul MAN de la Elite — produsele MAN de acolo nu concurează la preț.</p>
      <div class="pl-brand-toolbar">
        <label>
          <span>La furnizorul</span>
          <select id="pl-brand-supplier" class="box h-10 w-full rounded-md border px-3 text-sm"></select>
        </label>
        <label>
          <span>Ignoră brandul</span>
          <input type="text" id="pl-brand-input" class="box h-10 w-full rounded-md border px-3 text-sm" placeholder="ex: MAN, BOSCH" maxlength="80">
        </label>
        <button type="button" id="pl-brand-add" class="fz-btn-outline h-10 px-4 whitespace-nowrap">Adaugă</button>
      </div>
      <div id="pl-ignore-brands" class="pl-brand-rules"></div>
    </div>
  </div>

  <!-- PAS 3 -->
  <div class="pl-sub-pane" data-pl-pane="rules" role="tabpanel">
    <div class="pl-step-head">
      <h3>Pas 3 — Reguli de comparare preț</h3>
      <p>Aceste setări se aplică tuturor produselor la import. Spun sistemului cum verifică brandul, stocul și ce preț alege când mai mulți furnizori au același cod.</p>
      <span class="pl-step-action">✓ Acțiune: alege strategia care ți se potrivește, apoi salvează</span>
    </div>
    <div class="pl-section bg-white">
      <div class="pl-rules-grid">
        <div class="pl-field">
          <label for="pl-brand-verify">
            <span>Brandul trebuie să fie identic?</span>
            <select id="pl-brand-verify" class="box h-10 w-full rounded-md border px-3 text-sm">
              <option value="exact">Da — același brand exact</option>
              <option value="contains">Parțial — brand compatibil / conține</option>
              <option value="ignore">Nu — ignor brandul la comparare</option>
            </select>
          </label>
          <p class="pl-field-help">Folosește „identic” dacă vrei potrivire strictă pe cod + brand.</p>
        </div>
        <div class="pl-field">
          <label for="pl-stock-verify">
            <span>Ce facem cu stoc 0?</span>
            <select id="pl-stock-verify" class="box h-10 w-full rounded-md border px-3 text-sm">
              <option value="skip_zero">Sari peste — nu considerăm stoc 0</option>
              <option value="include_zero">Include — acceptăm și stoc 0</option>
              <option value="require_positive">Doar stoc pozitiv</option>
              <option value="require_known">Stoc cunoscut și pozitiv</option>
            </select>
          </label>
          <p class="pl-field-help">De obicei alegi „sari peste” ca să nu câștige un furnizor fără stoc real.</p>
        </div>
        <div class="pl-field">
          <label for="pl-price-strategy">
            <span>Cum alegem prețul câștigător?</span>
            <select id="pl-price-strategy" class="box h-10 w-full rounded-md border px-3 text-sm">
              <option value="hierarchical_top3_lowest">Întâi top prioritari — cel mai mic preț</option>
              <option value="hierarchical_top3_first_stock">Întâi top prioritari — primul cu stoc</option>
              <option value="lowest_then_priority">Cel mai mic preț, apoi prioritate</option>
              <option value="priority_first">Prioritate furnizor, apoi preț</option>
            </select>
          </label>
          <p class="pl-field-help">Recomandat: verifică mai întâi furnizorii prioritari, apoi alege cel mai mic preț dintre ei.</p>
        </div>
        <div class="pl-field">
          <label for="pl-compare-tier-size">
            <span>Câți furnizori prioritari (top N)?</span>
            <select id="pl-compare-tier-size" class="box h-10 w-full rounded-md border px-3 text-sm">
              <option value="1">Top 1</option>
              <option value="2">Top 2</option>
              <option value="3" selected>Top 3</option>
              <option value="4">Top 4</option>
              <option value="5">Top 5</option>
              <option value="6">Top 6</option>
              <option value="7">Top 7</option>
              <option value="8">Top 8</option>
              <option value="9">Top 9</option>
              <option value="10">Top 10</option>
            </select>
          </label>
          <p class="pl-field-help">Primii N din lista de la Pas 1 sunt comparați întâi. Dacă niciunul nu are ofertă validă, trece la următorii.</p>
        </div>
      </div>
      <div class="pl-example-box" id="pl-example-box">
        <strong>Exemplu:</strong> <span id="pl-example-text">Completează pașii 1–3 pentru a vedea un scenariu concret.</span>
      </div>
    </div>
  </div>

  <!-- PAS 4 -->
  <div class="pl-sub-pane" data-pl-pane="stock-zero" role="tabpanel">
    <div class="pl-step-head">
      <h3>Pas 4 — Ce facem când stocul e zero la furnizor</h3>
      <p>Aceste reguli se aplică la scanarea feed-ului, per furnizor. Spun dacă produsul rămâne vizibil, devine epuizat sau dispare de pe site.</p>
      <span class="pl-step-action">✓ Acțiune: setează fiecare furnizor, apoi salvează regulile stoc</span>
    </div>
    <div class="pl-stock-note">
      <span aria-hidden="true">ℹ️</span>
      <span><strong>Atenție:</strong> regulile de stoc zero se salvează separat (butonul „Salvează regulile stoc”). Logica de comparare preț (pașii 1–3) se salvează cu „Salvează logica comparare”.</span>
    </div>
    <div class="pl-section bg-white">
      <div class="overflow-x-auto">
        <table class="pl-stock-table w-full min-w-[680px] text-left text-sm">
          <thead class="border-b text-slate-500">
            <tr>
              <th class="py-2 pr-3">Furnizor</th>
              <th class="py-2 pr-3">Când stoc = 0 la sursă</th>
              <th class="py-2 pr-3">Include în scanare</th>
              <th class="py-2">Sari peste indisponibil</th>
            </tr>
          </thead>
          <tbody id="pl-stock-zero-rows"></tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="pl-footer">
    <div class="pl-footer-nav">
      <button type="button" id="pl-prev" class="fz-btn-outline" disabled>← Pas anterior</button>
      <button type="button" id="pl-next" class="fz-btn-outline">Pas următor →</button>
    </div>
    <div class="pl-footer-save">
      <button type="button" id="pl-save" class="fz-btn-primary">Salvează logica comparare</button>
      <button type="button" id="pl-save-stock" class="fz-btn-outline">Salvează regulile stoc</button>
      <button type="button" id="pl-test" class="fz-btn-outline">Testează pe exemple</button>
      <span id="pl-status" class="self-center" role="status"></span>
    </div>
    <p class="pl-footer-hint">Pașii 1–3 + test → „Salvează logica comparare”. Pas 4 → „Salvează regulile stoc”. Poți testa oricând înainte de salvare.</p>
  </div>

  <div id="pl-test-results" class="mt-5 hidden rounded-lg border border-dashed border-slate-200 bg-slate-50 p-4">
    <h4 class="mb-2 text-sm font-semibold">Rezultat test (simulare)</h4>
    <div id="pl-test-winners" class="mb-3 text-sm"></div>
    <div class="overflow-x-auto">
      <table class="w-full text-left text-xs">
        <thead>
          <tr class="border-b text-slate-500">
            <th class="py-2 pr-3">Cod</th>
            <th class="py-2 pr-3">Furnizor</th>
            <th class="py-2 pr-3">Acțiune</th>
            <th class="py-2">Motiv</th>
          </tr>
        </thead>
        <tbody id="pl-test-trace"></tbody>
      </table>
    </div>
  </div>
</div>

<script>
(function () {
  'use strict';
  const ENDPOINT = '/admin/api/furnizori_endpoint.php';
  const STEPS = ['scan-order', 'exclusions', 'rules', 'stock-zero'];
  const panelEl = document.getElementById('price-logic-panel');
  const orderEl = document.getElementById('pl-scan-order');
  const omitGridEl = document.getElementById('pl-omit-grid');
  const omitSelectEl = document.getElementById('pl-omit-select');
  const ignoreBrandsEl = document.getElementById('pl-ignore-brands');
  const brandSupplierEl = document.getElementById('pl-brand-supplier');
  const brandInputEl = document.getElementById('pl-brand-input');
  const stockZeroRowsEl = document.getElementById('pl-stock-zero-rows');
  const statusEl = document.getElementById('pl-status');
  const resultsEl = document.getElementById('pl-test-results');
  const winnersEl = document.getElementById('pl-test-winners');
  const traceEl = document.getElementById('pl-test-trace');
  const liveTextEl = document.getElementById('pl-live-text');
  const exampleTextEl = document.getElementById('pl-example-text');
  const blockedFreezeEl = document.getElementById('pl-blocked-freeze');
  const blockedListEl = document.getElementById('pl-blocked-list');
  let blockedSuppliers = [];
  let suppliers = [];
  let currentStep = 0;
  let config = {
    scan_order: [],
    omit_suppliers: [],
    ignore_brands_by_supplier: {},
    brand_verify: 'exact',
    stock_verify: 'skip_zero',
    price_strategy: 'hierarchical_top3_lowest',
    compare_tier_size: 3,
  };

  function esc(v) {
    return String(v ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[c]));
  }

  function setStatus(msg, type) {
    if (!statusEl) return;
    statusEl.textContent = msg || '';
    statusEl.classList.toggle('is-ok', type === 'ok');
    statusEl.classList.toggle('is-err', type === 'err');
  }

  function setPlTab(id) {
    const idx = STEPS.indexOf(id);
    if (idx >= 0) currentStep = idx;
    document.querySelectorAll('#price-logic-panel .pl-wizard-step').forEach((btn) => {
      const stepId = btn.dataset.plTab;
      const stepIdx = STEPS.indexOf(stepId);
      const active = stepId === id;
      btn.classList.toggle('active', active);
      btn.classList.toggle('done', stepIdx >= 0 && stepIdx < currentStep);
      btn.setAttribute('aria-selected', active ? 'true' : 'false');
    });
    document.querySelectorAll('#price-logic-panel .pl-sub-pane').forEach((pane) => {
      pane.classList.toggle('active', pane.dataset.plPane === id);
    });
    const prevBtn = document.getElementById('pl-prev');
    const nextBtn = document.getElementById('pl-next');
    if (prevBtn) prevBtn.disabled = currentStep <= 0;
    if (nextBtn) nextBtn.disabled = currentStep >= STEPS.length - 1;
    try { sessionStorage.setItem('pl-wizard-step', id); } catch (_) {}
  }

  document.querySelectorAll('#price-logic-panel .pl-wizard-step').forEach((btn) => {
    btn.addEventListener('click', () => setPlTab(btn.dataset.plTab || 'scan-order'));
  });
  document.getElementById('pl-prev')?.addEventListener('click', () => {
    if (currentStep > 0) setPlTab(STEPS[currentStep - 1]);
  });
  document.getElementById('pl-next')?.addEventListener('click', () => {
    if (currentStep < STEPS.length - 1) setPlTab(STEPS[currentStep + 1]);
  });

  document.getElementById('pl-intro-toggle')?.addEventListener('click', () => {
    const intro = document.getElementById('pl-intro');
    const btn = document.getElementById('pl-intro-toggle');
    if (!intro || !btn) return;
    const collapsed = intro.classList.toggle('is-collapsed');
    btn.textContent = collapsed ? 'Arată explicația' : 'Ascunde explicația';
    btn.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
  });

  async function api(action, payload) {
    const csrf = document.getElementById('bpa-api-live-arm')?.getAttribute('data-csrf')
      || document.querySelector('[data-csrf]')?.getAttribute('data-csrf')
      || window.BESOIU_ADMIN_CSRF || '';
    const headers = { 'Content-Type': 'application/json' };
    if (csrf) headers['X-Admin-CSRF'] = csrf;
    const res = await fetch(ENDPOINT, {
      method: 'POST',
      headers,
      credentials: 'same-origin',
      body: JSON.stringify({ type_product: action, ...payload }),
    });
    const raw = await res.text();
    let json;
    try { json = JSON.parse(raw); } catch (e) { throw new Error('Răspuns invalid de la server.'); }
    if (!res.ok || !json.success) throw new Error(json.message || 'Eroare');
    return json.data;
  }

  function supplierName(code) {
    const hit = suppliers.find((s) => s.code === code);
    return hit ? hit.name : code;
  }

  function readOmitFromForm() {
    if (!omitSelectEl) return config.omit_suppliers || [];
    return Array.from(omitSelectEl.selectedOptions).map((opt) => opt.value).filter(Boolean);
  }

  function syncOmitSelectFromConfig() {
    if (!omitSelectEl) return;
    const omitSet = new Set(config.omit_suppliers || []);
    Array.from(omitSelectEl.options).forEach((opt) => {
      opt.selected = omitSet.has(opt.value);
    });
  }

  function readFormConfig() {
    const order = [];
    orderEl?.querySelectorAll('[data-code]').forEach((row) => {
      const code = row.getAttribute('data-code');
      if (code) order.push(code);
    });
    return {
      scan_order: order,
      omit_suppliers: readOmitFromForm(),
      ignore_brands_by_supplier: readIgnoreBrandsFromForm(),
      brand_verify: document.getElementById('pl-brand-verify')?.value || 'exact',
      stock_verify: document.getElementById('pl-stock-verify')?.value || 'skip_zero',
      price_strategy: document.getElementById('pl-price-strategy')?.value || 'hierarchical_top3_lowest',
      compare_tier_size: Math.max(1, Math.min(10, Number(document.getElementById('pl-compare-tier-size')?.value) || 3)),
    };
  }

  function normalizeBrand(value) {
    return String(value ?? '').trim().replace(/\s+/g, ' ').toUpperCase();
  }

  function readIgnoreBrandsFromForm() {
    return config.ignore_brands_by_supplier && typeof config.ignore_brands_by_supplier === 'object'
      ? { ...config.ignore_brands_by_supplier }
      : {};
  }

  function strategyLabel(key) {
    return ({
      hierarchical_top3_lowest: 'top prioritari → cel mai mic preț',
      hierarchical_top3_first_stock: 'top prioritari → primul cu stoc',
      lowest_then_priority: 'cel mai mic preț',
      priority_first: 'prioritate furnizor',
    })[key] || key;
  }

  function renderBlockedFreeze() {
    if (!blockedFreezeEl || !blockedListEl) return;
    const list = Array.isArray(blockedSuppliers) ? blockedSuppliers : [];
    blockedFreezeEl.classList.toggle('is-visible', list.length > 0);
    blockedListEl.innerHTML = list.length
      ? list.map((s) => `<li><strong>${esc(s.name || s.code)}</strong> (${esc(s.code)})</li>`).join('')
      : '';
  }

  function updateLiveSummary() {
    if (!liveTextEl) return;
    const order = config.scan_order.length ? config.scan_order : suppliers.map((s) => s.code);
    const omit = config.omit_suppliers || [];
    const active = order.filter((c) => !omit.includes(c));
    const tier = getTierSize();
    const topNames = active.slice(0, tier).map(supplierName);
    const strategy = document.getElementById('pl-price-strategy')?.value || config.price_strategy;
    let text = '';
    if (!active.length) {
      text = 'Niciun furnizor activ în comparare — reactivează cel puțin unul la Pas 2.';
    } else {
      text = 'Verificăm ' + active.length + ' furnizori. Top ' + tier + ': ' + (topNames.join(', ') || '—')
        + '. Strategie: ' + strategyLabel(strategy) + '.';
    }
    liveTextEl.textContent = text;
    const summary = document.getElementById('pl-live-summary');
    if (summary) summary.classList.toggle('is-warn', !active.length);
  }

  function updateExampleBox() {
    if (!exampleTextEl) return;
    const order = config.scan_order.length ? config.scan_order : suppliers.map((s) => s.code);
    const omitSet = new Set(config.omit_suppliers || []);
    const active = order.filter((c) => !omitSet.has(c));
    const tier = getTierSize();
    const first = active[0] ? supplierName(active[0]) : 'Furnizor 1';
    const second = active[1] ? supplierName(active[1]) : 'Furnizor 2';
    const strategy = document.getElementById('pl-price-strategy')?.value || config.price_strategy;
    exampleTextEl.textContent = 'Cod ABC123 la ' + first + ' (120 lei) și ' + second + ' (115 lei). '
      + 'Sistemul verifică întâi top ' + tier + ' din listă, apoi aplică: ' + strategyLabel(strategy) + '. '
      + 'Câștigătorul apare în vitrină după salvare + import.';
  }

  function applyConfigToForm() {
    document.getElementById('pl-brand-verify').value = config.brand_verify || 'exact';
    document.getElementById('pl-stock-verify').value = config.stock_verify || 'skip_zero';
    document.getElementById('pl-price-strategy').value = config.price_strategy || 'hierarchical_top3_lowest';
    const tierEl = document.getElementById('pl-compare-tier-size');
    if (tierEl) tierEl.value = String(Math.max(1, Math.min(10, Number(config.compare_tier_size) || 3)));
    renderOrderList();
    renderOmitGrid();
    renderBrandSupplierSelect();
    renderIgnoreBrandsList();
    updateLiveSummary();
    updateExampleBox();
  }

  function getTierSize() {
    return Math.max(1, Math.min(10, Number(config.compare_tier_size) || 3));
  }

  function rankClass(idx) {
    if (idx === 0) return 'pl-scan-rank--1';
    if (idx === 1) return 'pl-scan-rank--2';
    if (idx === 2) return 'pl-scan-rank--3';
    return 'pl-scan-rank--n';
  }

  function animateScanRow(code) {
    const row = orderEl?.querySelector(`[data-code="${CSS.escape(code)}"]`);
    if (!row) return;
    row.classList.add('is-moving');
    window.setTimeout(() => row.classList.remove('is-moving'), 320);
  }

  function moveItem(code, dir) {
    const list = config.scan_order.slice();
    const i = list.indexOf(code);
    if (i < 0) return;
    const j = i + dir;
    if (j < 0 || j >= list.length) return;
    [list[i], list[j]] = [list[j], list[i]];
    config.scan_order = list;
    renderOrderList();
    animateScanRow(code);
    updateLiveSummary();
    updateExampleBox();
  }

  function renderOrderList() {
    if (!orderEl) return;
    const codes = config.scan_order.length
      ? config.scan_order.slice()
      : suppliers.map((s) => s.code);
    const tierSize = getTierSize();
    orderEl.innerHTML = codes.map((code, idx) => {
      const tier = idx < tierSize;
      const omitted = (config.omit_suppliers || []).includes(code);
      return `
      <li class="pl-scan-row${tier ? ' pl-scan-row--tier' : ''}${omitted ? ' opacity-50' : ''}" data-code="${esc(code)}">
        <span class="pl-scan-rank ${rankClass(idx)}" aria-label="Poziția ${idx + 1}">${idx + 1}</span>
        <div class="pl-scan-main">
          <span class="pl-scan-name">${esc(supplierName(code))}${omitted ? ' <span style="color:#dc2626;font-size:0.75rem">( exclus )</span>' : ''}</span>
          <span class="pl-scan-code">${esc(code)}${tier ? ' · în grupul top ' + tierSize : ''}</span>
        </div>
        <span class="pl-scan-grip" aria-hidden="true" title="Reordonare">⋮⋮</span>
        <div class="pl-scan-actions">
          <button type="button" class="pl-scan-btn pl-move-up" data-dir="-1" title="Mută sus (prioritate mai mare)" ${idx === 0 ? 'disabled' : ''} aria-label="Mută sus">↑</button>
          <button type="button" class="pl-scan-btn pl-move-down" data-dir="1" title="Mută jos" ${idx === codes.length - 1 ? 'disabled' : ''} aria-label="Mută jos">↓</button>
        </div>
      </li>`;
    }).join('');
    config.scan_order = codes;
    orderEl.querySelectorAll('.pl-move-up, .pl-move-down').forEach((btn) => {
      btn.addEventListener('click', () => {
        const row = btn.closest('[data-code]');
        const code = row?.getAttribute('data-code');
        if (!code || btn.disabled) return;
        moveItem(code, Number(btn.dataset.dir));
      });
    });
  }

  function toggleOmitSupplier(code) {
    const list = Array.isArray(config.omit_suppliers) ? config.omit_suppliers.slice() : [];
    const idx = list.indexOf(code);
    if (idx >= 0) list.splice(idx, 1);
    else list.push(code);
    config.omit_suppliers = list;
    syncOmitSelectFromConfig();
    renderOmitGrid();
    renderOrderList();
    updateLiveSummary();
    updateExampleBox();
  }

  function renderOmitGrid() {
    if (!omitGridEl) return;
    const omitSet = new Set(config.omit_suppliers || []);
    if (omitSelectEl) {
      omitSelectEl.innerHTML = suppliers.map((s) =>
        `<option value="${esc(s.code)}">${esc(s.name)}</option>`
      ).join('');
      syncOmitSelectFromConfig();
    }
    if (!suppliers.length) {
      omitGridEl.innerHTML = '<p class="text-sm text-slate-500">Niciun furnizor configurat.</p>';
      return;
    }
    omitGridEl.innerHTML = suppliers.map((s) => {
      const omitted = omitSet.has(s.code);
      return `<button type="button" class="pl-omit-card ${omitted ? 'is-omitted' : 'is-active'}" data-omit-code="${esc(s.code)}" aria-pressed="${omitted ? 'true' : 'false'}">
        <span class="pl-omit-card-name">${esc(s.name)}</span>
        <span class="pl-omit-card-code">${esc(s.code)}</span>
        <span class="pl-omit-card-state">${omitted ? 'Exclus din comparare' : 'Activ în comparare'}</span>
      </button>`;
    }).join('');
    omitGridEl.querySelectorAll('[data-omit-code]').forEach((btn) => {
      btn.addEventListener('click', () => toggleOmitSupplier(btn.getAttribute('data-omit-code')));
    });
  }

  function renderBrandSupplierSelect() {
    if (!brandSupplierEl) return;
    const prev = brandSupplierEl.value;
    brandSupplierEl.innerHTML = suppliers.map((s) =>
      `<option value="${esc(s.code)}">${esc(s.name)} (${esc(s.code)})</option>`
    ).join('');
    if (prev && suppliers.some((s) => s.code === prev)) brandSupplierEl.value = prev;
  }

  function addIgnoreBrand() {
    const code = brandSupplierEl?.value;
    const brand = normalizeBrand(brandInputEl?.value);
    if (!code || !brand) return;
    const current = readIgnoreBrandsFromForm();
    const list = Array.isArray(current[code]) ? current[code].slice() : [];
    if (!list.includes(brand)) list.push(brand);
    config.ignore_brands_by_supplier = { ...current, [code]: list };
    if (brandInputEl) brandInputEl.value = '';
    renderIgnoreBrandsList();
  }

  function removeIgnoreBrand(code, brand) {
    const current = readIgnoreBrandsFromForm();
    const list = (Array.isArray(current[code]) ? current[code] : []).filter((b) => b !== brand);
    const next = { ...current };
    if (list.length) next[code] = list;
    else delete next[code];
    config.ignore_brands_by_supplier = next;
    renderIgnoreBrandsList();
  }

  function renderIgnoreBrandsList() {
    if (!ignoreBrandsEl) return;
    const map = readIgnoreBrandsFromForm();
    const codes = Object.keys(map).filter((code) => Array.isArray(map[code]) && map[code].length);
    if (!codes.length) {
      ignoreBrandsEl.innerHTML = '<p class="text-xs text-slate-400">Niciun brand ignorat — opțional.</p>';
      return;
    }
    ignoreBrandsEl.innerHTML = codes.map((code) => {
      const brands = map[code];
      const chips = brands.map((brand) => `
        <span class="pl-brand-chip" data-brand="${esc(brand)}">
          ${esc(brand)}
          <button type="button" class="pl-brand-remove" title="Elimină brand">×</button>
        </span>`).join('');
      return `
        <div class="pl-brand-rule" data-supplier-code="${esc(code)}">
          <span class="pl-brand-rule-supplier">${esc(supplierName(code))}</span>
          <div class="pl-brand-rule-chips">${chips}</div>
        </div>`;
    }).join('');
    ignoreBrandsEl.querySelectorAll('.pl-brand-remove').forEach((btn) => {
      btn.addEventListener('click', () => {
        const row = btn.closest('[data-supplier-code]');
        const chip = btn.closest('[data-brand]');
        const code = row?.getAttribute('data-supplier-code');
        const brand = normalizeBrand(chip?.getAttribute('data-brand'));
        if (!code || !brand) return;
        removeIgnoreBrand(code, brand);
      });
    });
  }

  function stockZeroLabel(mode) {
    return ({ hide: 'Ascunde produsul', out_of_stock: 'Epuizat', full: 'Afișează FULL' })[mode] || mode;
  }

  function renderStockZeroRules() {
    if (!stockZeroRowsEl) return;
    if (!suppliers.length) {
      stockZeroRowsEl.innerHTML = '<tr><td colspan="4" class="py-4 text-sm text-slate-500">Niciun furnizor activ.</td></tr>';
      return;
    }
    stockZeroRowsEl.innerHTML = suppliers.map((s) => {
      const code = String(s.code || '');
      const mode = String(s.stock_zero_mode || 'full');
      const includeZero = Number(s.scan_include_zero_stock ?? 1) === 1;
      const skipUnavailable = Number(s.scan_skip_unavailable ?? 0) === 1;
      return `<tr class="border-b border-slate-100" data-stock-code="${esc(code)}">
        <td class="py-3 pr-3">
          <span class="font-semibold text-slate-900">${esc(s.name || code)}</span>
          <span class="block text-xs text-slate-400">${esc(code)}</span>
        </td>
        <td class="py-3 pr-3">
          <select class="pl-stock-mode box h-9 w-full max-w-[220px] rounded-md border px-2 text-sm" aria-label="Acțiune stoc zero ${esc(s.name)}">
            <option value="full"${mode === 'full' ? ' selected' : ''}>Afișează FULL</option>
            <option value="hide"${mode === 'hide' ? ' selected' : ''}>Ascunde produsul</option>
            <option value="out_of_stock"${mode === 'out_of_stock' ? ' selected' : ''}>Marchează epuizat</option>
          </select>
          <span class="pl-stock-help">Ce vede clientul când sursa raportează stoc 0</span>
        </td>
        <td class="py-3 pr-3">
          <label class="inline-flex items-center gap-2 text-sm">
            <input type="checkbox" class="pl-stock-include rounded border"${includeZero ? ' checked' : ''}>
            Da
          </label>
          <span class="pl-stock-help">Include produsele cu stoc 0 în scanare</span>
        </td>
        <td class="py-3">
          <label class="inline-flex items-center gap-2 text-sm">
            <input type="checkbox" class="pl-stock-skip rounded border"${skipUnavailable ? ' checked' : ''}>
            Da
          </label>
          <span class="pl-stock-help">Sari peste la comparare dacă indisponibil</span>
        </td>
      </tr>`;
    }).join('');
  }

  async function saveStockZeroRule(rowEl) {
    if (!rowEl) return;
    const code = rowEl.getAttribute('data-stock-code') || '';
    const supplier = suppliers.find((s) => String(s.code) === code);
    if (!supplier || !supplier.randomn_id) throw new Error('Lipsește ID pentru ' + code);
    const mode = rowEl.querySelector('.pl-stock-mode')?.value || 'full';
    const includeZero = rowEl.querySelector('.pl-stock-include')?.checked ? 1 : 0;
    const skipUnavailable = rowEl.querySelector('.pl-stock-skip')?.checked ? 1 : 0;
    await api('edit', {
      randomn_id: supplier.randomn_id,
      stock_zero_mode: mode,
      scan_include_zero_stock: includeZero,
      scan_skip_unavailable: skipUnavailable,
    });
    supplier.stock_zero_mode = mode;
    supplier.scan_include_zero_stock = includeZero;
    supplier.scan_skip_unavailable = skipUnavailable;
    return code;
  }

  async function saveAllStockZeroRules() {
    const rows = stockZeroRowsEl?.querySelectorAll('[data-stock-code]') || [];
    const saved = [];
    for (const row of rows) {
      const code = await saveStockZeroRule(row);
      if (code) saved.push(code);
    }
    return saved;
  }

  function renderTest(data) {
    resultsEl?.classList.remove('hidden');
    const winners = Array.isArray(data.winners) ? data.winners : [];
    winnersEl.innerHTML = winners.length
      ? '<strong>Câștigători simulați:</strong> ' + winners.map((w) =>
          `${esc(w.code)} → ${esc(w.supplier)} (${Number(w.price).toFixed(2)} lei)`
        ).join(' · ')
      : 'Niciun câștigător în simulare — verifică excluderile și regulile de stoc.';
    const trace = Array.isArray(data.trace) ? data.trace : [];
    traceEl.innerHTML = trace.map((t) => `
      <tr class="border-b border-slate-100">
        <td class="py-2 pr-3">${esc(t.code)}</td>
        <td class="py-2 pr-3">${esc(t.supplier)}</td>
        <td class="py-2 pr-3">${esc(t.action)}</td>
        <td class="py-2">${esc(t.reason)}</td>
      </tr>`).join('');
    resultsEl?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }

  async function load() {
    panelEl?.classList.add('pl-loading');
    setStatus('Se încarcă…');
    try {
      const [logicData, listData] = await Promise.all([
        api('get_price_logic', {}),
        api('list', { per_page: 100, page: 1, status: 'active' }),
      ]);
      blockedSuppliers = Array.isArray(logicData.blocked_suppliers) ? logicData.blocked_suppliers : [];
      renderBlockedFreeze();
      const logicSuppliers = Array.isArray(logicData.suppliers) ? logicData.suppliers : [];
      const listItems = Array.isArray(listData?.items) ? listData.items : (Array.isArray(listData) ? listData : []);
      const byCode = {};
      listItems.forEach((item) => {
        const code = String(item.code || '').toUpperCase();
        if (code) byCode[code] = item;
      });
      suppliers = logicSuppliers.map((s) => {
        const code = String(s.code || '').toUpperCase();
        const fromList = byCode[code] || {};
        return {
          ...s,
          randomn_id: fromList.randomn_id || s.randomn_id || null,
          stock_zero_mode: fromList.stock_zero_mode || s.stock_zero_mode || 'full',
          scan_include_zero_stock: fromList.scan_include_zero_stock ?? s.scan_include_zero_stock ?? 1,
          scan_skip_unavailable: fromList.scan_skip_unavailable ?? s.scan_skip_unavailable ?? 0,
        };
      });
      config = { ...config, ...(logicData.config || {}) };
      if (!Array.isArray(config.scan_order) || !config.scan_order.length) {
        config.scan_order = suppliers.map((s) => s.code);
      }
      applyConfigToForm();
      renderStockZeroRules();
      setStatus('');
      let savedStep = 'scan-order';
      try { savedStep = sessionStorage.getItem('pl-wizard-step') || 'scan-order'; } catch (_) {}
      if (STEPS.includes(savedStep)) setPlTab(savedStep);
    } finally {
      panelEl?.classList.remove('pl-loading');
    }
  }

  ['pl-brand-verify', 'pl-stock-verify', 'pl-price-strategy', 'pl-compare-tier-size'].forEach((id) => {
    document.getElementById(id)?.addEventListener('change', () => {
      if (id === 'pl-compare-tier-size') {
        config.compare_tier_size = Math.max(1, Math.min(10, Number(document.getElementById('pl-compare-tier-size')?.value) || 3));
        renderOrderList();
      }
      updateLiveSummary();
      updateExampleBox();
    });
  });

  document.getElementById('pl-brand-add')?.addEventListener('click', addIgnoreBrand);
  brandInputEl?.addEventListener('keydown', (e) => {
    if (e.key !== 'Enter') return;
    e.preventDefault();
    addIgnoreBrand();
  });

  document.getElementById('pl-save')?.addEventListener('click', async () => {
    try {
      setStatus('Se salvează logica…');
      const payload = readFormConfig();
      const data = await api('save_price_logic', payload);
      config = data.config || payload;
      applyConfigToForm();
      setStatus('Logica comparare salvată.', 'ok');
      setTimeout(() => setStatus(''), 3000);
    } catch (e) {
      setStatus(e.message, 'err');
    }
  });

  document.getElementById('pl-save-stock')?.addEventListener('click', async () => {
    try {
      setStatus('Se salvează regulile stoc…');
      const saved = await saveAllStockZeroRules();
      setStatus('Reguli stoc salvate pentru ' + saved.length + ' furnizori.', 'ok');
      setTimeout(() => setStatus(''), 3000);
    } catch (e) {
      setStatus(e.message, 'err');
    }
  });

  document.getElementById('pl-test')?.addEventListener('click', async () => {
    try {
      setStatus('Rulez simularea…');
      const cfg = readFormConfig();
      const data = await api('test_price_logic', { config: cfg });
      renderTest(data);
      setStatus('Test finalizat — vezi rezultatul mai jos.', 'ok');
      setTimeout(() => setStatus(''), 4000);
    } catch (e) {
      setStatus(e.message, 'err');
    }
  });

  window.addEventListener('besoiu:open-price-logic', () => {
    if (typeof window.furnizoriSetPageTab === 'function') {
      window.furnizoriSetPageTab('compare');
    }
    document.getElementById('price-logic-panel')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  });

  if (document.getElementById('price-logic-panel')) {
    load().catch((e) => setStatus(e.message, 'err'));
  }
})();
</script>
