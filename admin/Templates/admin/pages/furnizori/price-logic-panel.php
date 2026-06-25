<?php
/** Panou: ierarhie / comparare furnizori la import (ordine scanare, omit, reguli preț). */
?>
<style>
  .furnizori-page .fz-price-panel .pl-sub-tabs {
    display: flex; flex-wrap: wrap; gap: 0; margin: 16px 0 0;
    border-bottom: 1px solid #e5e7eb;
  }
  .furnizori-page .fz-price-panel .pl-sub-tab {
    display: inline-flex; align-items: center; gap: 6px;
    height: 38px; padding: 0 16px; margin-bottom: -1px;
    border: 1px solid transparent; border-radius: 8px 8px 0 0;
    background: transparent; color: #64748b;
    font-size: 0.8125rem; font-weight: 600; cursor: pointer;
    transition: color .15s, background .15s, border-color .15s;
  }
  .furnizori-page .fz-price-panel .pl-sub-tab:hover { color: #334155; background: #f8fafc; }
  .furnizori-page .fz-price-panel .pl-sub-tab.active {
    color: #2563eb; background: #fff;
    border-color: #e5e7eb; border-bottom-color: #fff;
  }
  .furnizori-page .fz-price-panel .pl-sub-pane { display: none; padding-top: 18px; }
  .furnizori-page .fz-price-panel .pl-sub-pane.active { display: block; }
  .furnizori-page .fz-price-panel .pl-section {
    border: 1px solid #e5e7eb; border-radius: 10px; background: #f8fafc; padding: 14px;
  }
  .furnizori-page .fz-price-panel .pl-section + .pl-section { margin-top: 16px; }
  .furnizori-page .fz-price-panel .pl-section-title {
    font-size: 0.8125rem; font-weight: 700; color: #0f172a; margin: 0 0 4px;
  }
  .furnizori-page .fz-price-panel .pl-section-hint {
    font-size: 0.75rem; color: #64748b; margin: 0 0 12px;
  }
  .furnizori-page .fz-price-panel .pl-omit-select {
    width: 100%; min-height: 180px; padding: 8px;
    border: 1px solid #cbd5e1; border-radius: 8px; background: #fff;
    font-size: 0.8125rem; color: #334155;
  }
  .furnizori-page .fz-price-panel .pl-omit-select option {
    padding: 6px 8px; border-radius: 4px;
  }
  .furnizori-page .fz-price-panel .pl-omit-select option:checked {
    background: linear-gradient(0deg, #dbeafe 0%, #dbeafe 100%); color: #1e40af;
  }
  .furnizori-page .fz-price-panel .pl-brand-toolbar {
    display: grid; grid-template-columns: 1fr 1fr auto; gap: 10px; align-items: end;
  }
  @media (max-width: 640px) {
    .furnizori-page .fz-price-panel .pl-brand-toolbar { grid-template-columns: 1fr; }
  }
  .furnizori-page .fz-price-panel .pl-brand-toolbar label span {
    display: block; font-size: 0.75rem; font-weight: 600; color: #475569; margin-bottom: 4px;
  }
  .furnizori-page .fz-price-panel .pl-brand-rules {
    margin-top: 14px; display: flex; flex-direction: column; gap: 8px;
  }
  .furnizori-page .fz-price-panel .pl-brand-rule {
    display: flex; flex-wrap: wrap; align-items: center; gap: 8px;
    padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 8px; background: #fff;
  }
  .furnizori-page .fz-price-panel .pl-brand-rule-supplier {
    font-size: 0.8125rem; font-weight: 600; color: #0f172a; min-width: 120px;
  }
  .furnizori-page .fz-price-panel .pl-brand-rule-code {
    font-size: 0.6875rem; color: #94a3b8; font-weight: 600;
  }
  .furnizori-page .fz-price-panel .pl-brand-rule-chips {
    display: flex; flex-wrap: wrap; gap: 6px; flex: 1;
  }
  .furnizori-page .fz-price-panel .pl-brand-chip {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 3px 8px; border-radius: 999px; border: 1px solid #cbd5e1;
    background: #f1f5f9; font-size: 0.75rem; font-weight: 600; color: #334155;
  }
  .furnizori-page .fz-price-panel .pl-brand-chip button {
    border: none; background: transparent; color: #94a3b8; cursor: pointer;
    font-size: 0.875rem; line-height: 1; padding: 0 2px;
  }
  .furnizori-page .fz-price-panel .pl-brand-chip button:hover { color: #dc2626; }
  .furnizori-page .fz-price-panel .pl-rules-grid {
    display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px;
  }
  @media (max-width: 768px) {
    .furnizori-page .fz-price-panel .pl-rules-grid { grid-template-columns: 1fr; }
  }
  .furnizori-page .fz-price-panel .pl-rules-grid label span {
    display: block; font-size: 0.8125rem; font-weight: 600; color: #334155; margin-bottom: 6px;
  }
  .furnizori-page .fz-price-panel .pl-scan-legend {
    display: flex; flex-wrap: wrap; gap: 10px 16px; margin-bottom: 14px;
    padding: 10px 12px; border-radius: 8px; background: #eff6ff; border: 1px solid #bfdbfe;
    font-size: 0.75rem; color: #1e40af;
  }
  .furnizori-page .fz-price-panel .pl-scan-legend span {
    display: inline-flex; align-items: center; gap: 6px;
  }
  .furnizori-page .fz-price-panel .pl-scan-legend i {
    display: inline-block; width: 10px; height: 10px; border-radius: 999px; flex-shrink: 0;
  }
  .furnizori-page .fz-price-panel .pl-scan-order {
    list-style: none; margin: 0; padding: 0;
    display: flex; flex-direction: column; gap: 8px;
  }
  .furnizori-page .fz-price-panel .pl-scan-row {
    display: grid; grid-template-columns: auto 1fr auto auto;
    align-items: center; gap: 10px;
    padding: 12px 14px; border: 1px solid #e2e8f0; border-radius: 10px; background: #fff;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
    transition: transform .28s ease, box-shadow .28s ease, border-color .28s ease, background .28s ease;
  }
  .furnizori-page .fz-price-panel .pl-scan-row.is-moving {
    transform: scale(1.01);
    box-shadow: 0 8px 20px rgba(37, 99, 235, 0.14);
    border-color: #93c5fd;
    background: #f8fbff;
  }
  .furnizori-page .fz-price-panel .pl-scan-row--tier {
    border-color: #bfdbfe; background: linear-gradient(90deg, #eff6ff 0%, #fff 42%);
  }
  .furnizori-page .fz-price-panel .pl-scan-rank {
    display: inline-flex; align-items: center; justify-content: center;
    min-width: 34px; height: 34px; border-radius: 10px;
    font-size: 0.875rem; font-weight: 800; line-height: 1;
    transition: transform .28s ease, background .28s ease, color .28s ease;
  }
  .furnizori-page .fz-price-panel .pl-scan-rank--1 { background: #2563eb; color: #fff; }
  .furnizori-page .fz-price-panel .pl-scan-rank--2 { background: #3b82f6; color: #fff; }
  .furnizori-page .fz-price-panel .pl-scan-rank--3 { background: #60a5fa; color: #fff; }
  .furnizori-page .fz-price-panel .pl-scan-rank--n { background: #f1f5f9; color: #64748b; border: 1px solid #e2e8f0; }
  .furnizori-page .fz-price-panel .pl-scan-row.is-moving .pl-scan-rank { transform: scale(1.08); }
  .furnizori-page .fz-price-panel .pl-scan-main { min-width: 0; }
  .furnizori-page .fz-price-panel .pl-scan-name {
    display: block; font-size: 0.875rem; font-weight: 700; color: #0f172a;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
  }
  .furnizori-page .fz-price-panel .pl-scan-code {
    display: block; font-size: 0.6875rem; color: #94a3b8; font-weight: 600; margin-top: 2px;
  }
  .furnizori-page .fz-price-panel .pl-scan-grip {
    display: inline-flex; align-items: center; justify-content: center;
    width: 28px; height: 34px; color: #cbd5e1; font-size: 1rem; letter-spacing: -2px;
    user-select: none;
  }
  .furnizori-page .fz-price-panel .pl-scan-actions {
    display: inline-flex; gap: 4px;
  }
  .furnizori-page .fz-price-panel .pl-scan-btn {
    display: inline-flex; align-items: center; justify-content: center;
    width: 32px; height: 32px; border: 1px solid #cbd5e1; border-radius: 8px;
    background: #fff; color: #475569; font-size: 0.875rem; font-weight: 700;
    cursor: pointer; transition: background .15s, border-color .15s, color .15s, transform .15s;
  }
  .furnizori-page .fz-price-panel .pl-scan-btn:hover:not(:disabled) {
    background: #eff6ff; border-color: #93c5fd; color: #2563eb;
  }
  .furnizori-page .fz-price-panel .pl-scan-btn:active:not(:disabled) { transform: scale(0.94); }
  .furnizori-page .fz-price-panel .pl-scan-btn:disabled {
    opacity: 0.35; cursor: not-allowed;
  }
  .furnizori-page .fz-price-panel .pl-rules-supplier-bar {
    display: grid; grid-template-columns: minmax(0, 1fr); gap: 12px; margin-bottom: 16px;
    padding-bottom: 16px; border-bottom: 1px dashed #e2e8f0;
  }
  .furnizori-page .fz-price-panel .pl-rules-supplier-bar label span {
    display: block; font-size: 0.8125rem; font-weight: 700; color: #0f172a; margin-bottom: 6px;
  }
  .furnizori-page .fz-price-panel .pl-rules-context {
    display: flex; flex-wrap: wrap; align-items: center; gap: 8px 12px;
    padding: 10px 12px; border-radius: 8px; background: #f8fafc; border: 1px solid #e2e8f0;
    font-size: 0.75rem; color: #475569;
  }
  .furnizori-page .fz-price-panel .pl-rules-context strong { color: #0f172a; }
  .furnizori-page .fz-price-panel .pl-rules-context-badge {
    display: inline-flex; align-items: center; padding: 2px 8px; border-radius: 999px;
    background: #dbeafe; color: #1d4ed8; font-weight: 700;
  }
  @media (max-width: 640px) {
    .furnizori-page .fz-price-panel .pl-scan-row {
      grid-template-columns: auto 1fr;
      grid-template-areas: "rank main" "rank actions";
    }
    .furnizori-page .fz-price-panel .pl-scan-grip { display: none; }
    .furnizori-page .fz-price-panel .pl-scan-actions { grid-area: actions; justify-self: end; }
  }
</style>

<div id="price-logic-panel" class="fz-price-panel box rounded-xl border bg-white p-5 md:p-6">
  <p class="mb-2 text-sm text-slate-600">
    La același cod produs, sistemul alege furnizorul și prețul conform ordinii de scanare, regulilor de stoc/brand și strategiei de preț.
    Strategia ierarhică verifică întâi primii furnizori din listă (implicit top 3); dacă niciunul nu are stoc/preț valid, trece la următorul din ordine.
  </p>

  <div class="pl-sub-tabs" role="tablist" aria-label="Secțiuni comparare furnizori">
    <button type="button" class="pl-sub-tab active" data-pl-tab="scan-order" role="tab" aria-selected="true">
      1. Ordine scanare
    </button>
    <button type="button" class="pl-sub-tab" data-pl-tab="exclusions" role="tab" aria-selected="false">
      2. Excluderi
    </button>
    <button type="button" class="pl-sub-tab" data-pl-tab="rules" role="tab" aria-selected="false">
      3. Reguli comparare
    </button>
    <button type="button" class="pl-sub-tab" data-pl-tab="stock-zero" role="tab" aria-selected="false">
      4. Reguli stoc zero
    </button>
  </div>

  <div class="pl-sub-pane active" data-pl-pane="scan-order" role="tabpanel">
    <div class="pl-section">
      <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
        <h3 class="pl-section-title">Ordine scanare (prioritate)</h3>
        <span class="text-xs text-slate-500">Folosește ↑ ↓ pentru reordonare — primul = cel mai important</span>
      </div>
      <p class="pl-section-hint">Primii furnizori din listă sunt verificați primii la comparare. Pozițiile 1–3 formează grupul ierarhic implicit.</p>
      <div class="pl-scan-legend" aria-hidden="true">
        <span><i style="background:#2563eb"></i> Prioritate maximă (poziția 1)</span>
        <span><i style="background:#60a5fa"></i> Grup ierarhic top 3</span>
        <span><i style="background:#e2e8f0"></i> Următorii în ordinea de scanare</span>
      </div>
      <ul id="pl-scan-order" class="pl-scan-order min-h-[160px]"></ul>
    </div>
  </div>

  <div class="pl-sub-pane" data-pl-pane="exclusions" role="tabpanel">
    <div class="pl-section">
      <h3 class="pl-section-title">Furnizori omisi (nu participă la comparare)</h3>
      <p class="pl-section-hint">Selectează din listă furnizorii excluși din logica de comparare (Ctrl+click pentru mai mulți).</p>
      <select id="pl-omit-select" class="pl-omit-select" multiple aria-label="Furnizori omisi"></select>
    </div>

    <div class="pl-section">
      <h3 class="pl-section-title">Branduri ignorate per furnizor</h3>
      <p class="pl-section-hint">Produsele acelui brand de la furnizorul ales nu participă la logica de preț optim (ex: MAN de la Elite).</p>
      <div class="pl-brand-toolbar">
        <label>
          <span>Furnizor</span>
          <select id="pl-brand-supplier" class="box h-10 w-full rounded-md border px-3 text-sm"></select>
        </label>
        <label>
          <span>Brand de ignorat</span>
          <input type="text" id="pl-brand-input" class="box h-10 w-full rounded-md border px-3 text-sm" placeholder="ex: MAN, BOSCH" maxlength="80">
        </label>
        <button type="button" id="pl-brand-add" class="fz-btn-outline h-10 px-4 whitespace-nowrap">Adaugă</button>
      </div>
      <div id="pl-ignore-brands" class="pl-brand-rules"></div>
    </div>
  </div>

  <div class="pl-sub-pane" data-pl-pane="rules" role="tabpanel">
    <div class="pl-section bg-white">
      <h3 class="pl-section-title">Parametri de lucru pentru comparare</h3>
      <p class="pl-section-hint">Alege furnizorul de referință, apoi configurează regulile care se aplică la compararea prețurilor.</p>
      <div class="pl-rules-supplier-bar">
        <label>
          <span>Furnizor de referință</span>
          <select id="pl-rules-supplier" class="box h-10 w-full rounded-md border px-3 text-sm" aria-label="Alege furnizor pentru reguli comparare"></select>
        </label>
        <div id="pl-rules-context" class="pl-rules-context"></div>
      </div>
      <div class="pl-rules-grid">
        <label>
          <span>Verificare brand</span>
          <select id="pl-brand-verify" class="box h-10 w-full rounded-md border px-3 text-sm">
            <option value="exact">Exact (același brand)</option>
            <option value="contains">Conține / compatibil</option>
            <option value="ignore">Ignoră brandul</option>
          </select>
        </label>
        <label>
          <span>Verificare stoc</span>
          <select id="pl-stock-verify" class="box h-10 w-full rounded-md border px-3 text-sm">
            <option value="skip_zero">Sari peste stoc 0</option>
            <option value="include_zero">Include și stoc 0</option>
            <option value="require_positive">Doar stoc pozitiv</option>
            <option value="require_known">Stoc cunoscut pozitiv</option>
          </select>
        </label>
        <label>
          <span>Strategie preț</span>
          <select id="pl-price-strategy" class="box h-10 w-full rounded-md border px-3 text-sm">
            <option value="hierarchical_top3_lowest">Ierarhic top 3 — cel mai mic preț</option>
            <option value="hierarchical_top3_first_stock">Ierarhic top 3 — primul cu stoc</option>
            <option value="lowest_then_priority">Cel mai mic preț, apoi prioritate</option>
            <option value="priority_first">Prioritate furnizor, apoi preț</option>
          </select>
        </label>
        <label>
          <span>Grup ierarhic (top N furnizori)</span>
          <select id="pl-compare-tier-size" class="box h-10 w-full rounded-md border px-3 text-sm" title="Primii N din ordinea de scanare formează primul grup de comparare">
            <option value="1">Top 1 furnizor</option>
            <option value="2">Top 2 furnizori</option>
            <option value="3" selected>Top 3 furnizori</option>
            <option value="4">Top 4 furnizori</option>
            <option value="5">Top 5 furnizori</option>
            <option value="6">Top 6 furnizori</option>
            <option value="7">Top 7 furnizori</option>
            <option value="8">Top 8 furnizori</option>
            <option value="9">Top 9 furnizori</option>
            <option value="10">Top 10 furnizori</option>
          </select>
        </label>
      </div>
    </div>
  </div>

  <div class="pl-sub-pane" data-pl-pane="stock-zero" role="tabpanel">
    <div class="pl-section bg-white">
      <h3 class="pl-section-title">Reguli automate stoc zero (per furnizor)</h3>
      <p class="pl-section-hint">
        La scanare feed: dacă stoc=0 la sursă, sistemul aplică acțiunea configurată (ascunde, epuizat, full).
        Aceleași setări sunt disponibile și în profilul furnizorului, tab <strong>Reguli scanare</strong>.
      </p>
      <div class="overflow-x-auto">
        <table class="w-full min-w-[720px] text-left text-sm">
          <thead class="border-b text-xs uppercase text-slate-500">
            <tr>
              <th class="py-2 pr-3">Furnizor</th>
              <th class="py-2 pr-3">Când stoc = 0</th>
              <th class="py-2 pr-3">Include stoc 0</th>
              <th class="py-2 pr-3">Sari indisponibil</th>
              <th class="py-2">Acțiune</th>
            </tr>
          </thead>
          <tbody id="pl-stock-zero-rows"></tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="mt-5 flex flex-wrap gap-3">
    <button type="button" id="pl-save" class="fz-btn-primary">Salvează logica</button>
    <button type="button" id="pl-test" class="fz-btn-outline">Testează pe exemple</button>
    <span id="pl-status" class="self-center text-sm text-slate-500"></span>
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
  const orderEl = document.getElementById('pl-scan-order');
  const omitSelectEl = document.getElementById('pl-omit-select');
  const ignoreBrandsEl = document.getElementById('pl-ignore-brands');
  const brandSupplierEl = document.getElementById('pl-brand-supplier');
  const brandInputEl = document.getElementById('pl-brand-input');
  const rulesSupplierEl = document.getElementById('pl-rules-supplier');
  const rulesContextEl = document.getElementById('pl-rules-context');
  const stockZeroRowsEl = document.getElementById('pl-stock-zero-rows');
  const statusEl = document.getElementById('pl-status');
  const resultsEl = document.getElementById('pl-test-results');
  const winnersEl = document.getElementById('pl-test-winners');
  const traceEl = document.getElementById('pl-test-trace');
  let suppliers = [];
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

  function setPlTab(id) {
    document.querySelectorAll('#price-logic-panel .pl-sub-tab').forEach((btn) => {
      const active = btn.dataset.plTab === id;
      btn.classList.toggle('active', active);
      btn.setAttribute('aria-selected', active ? 'true' : 'false');
    });
    document.querySelectorAll('#price-logic-panel .pl-sub-pane').forEach((pane) => {
      pane.classList.toggle('active', pane.dataset.plPane === id);
    });
  }

  document.querySelectorAll('#price-logic-panel .pl-sub-tab').forEach((btn) => {
    btn.addEventListener('click', () => setPlTab(btn.dataset.plTab || 'scan-order'));
  });

  async function api(action, payload) {
    const res = await fetch(ENDPOINT, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
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
    if (!omitSelectEl) return [];
    return Array.from(omitSelectEl.selectedOptions).map((opt) => opt.value).filter(Boolean);
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

  function applyConfigToForm() {
    document.getElementById('pl-brand-verify').value = config.brand_verify || 'exact';
    document.getElementById('pl-stock-verify').value = config.stock_verify || 'skip_zero';
    document.getElementById('pl-price-strategy').value = config.price_strategy || 'hierarchical_top3_lowest';
    const tierEl = document.getElementById('pl-compare-tier-size');
    if (tierEl) tierEl.value = String(Math.max(1, Math.min(10, Number(config.compare_tier_size) || 3)));
    renderOrderList();
    renderOmitList();
    renderBrandSupplierSelect();
    renderIgnoreBrandsList();
    renderRulesSupplierSelect();
    renderRulesContext();
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
    renderRulesContext();
  }

  function renderOrderList() {
    if (!orderEl) return;
    const codes = config.scan_order.length
      ? config.scan_order.slice()
      : suppliers.map((s) => s.code);
    const tierSize = getTierSize();
    orderEl.innerHTML = codes.map((code, idx) => {
      const tier = idx < tierSize;
      return `
      <li class="pl-scan-row${tier ? ' pl-scan-row--tier' : ''}" data-code="${esc(code)}">
        <span class="pl-scan-rank ${rankClass(idx)}" aria-label="Poziția ${idx + 1}">${idx + 1}</span>
        <div class="pl-scan-main">
          <span class="pl-scan-name">${esc(supplierName(code))}</span>
          <span class="pl-scan-code">${esc(code)}${tier ? ' · în grupul ierarhic' : ''}</span>
        </div>
        <span class="pl-scan-grip" aria-hidden="true" title="Reordonare">⋮⋮</span>
        <div class="pl-scan-actions">
          <button type="button" class="pl-scan-btn pl-move-up" data-dir="-1" title="Mută sus" ${idx === 0 ? 'disabled' : ''}>↑</button>
          <button type="button" class="pl-scan-btn pl-move-down" data-dir="1" title="Mută jos" ${idx === codes.length - 1 ? 'disabled' : ''}>↓</button>
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

  function activeScanCodes() {
    const omitSet = new Set(config.omit_suppliers || []);
    const codes = (config.scan_order.length ? config.scan_order : suppliers.map((s) => s.code))
      .filter((code) => code && !omitSet.has(code));
    return codes.length ? codes : suppliers.map((s) => s.code);
  }

  function renderRulesSupplierSelect() {
    if (!rulesSupplierEl) return;
    const prev = rulesSupplierEl.value;
    const codes = activeScanCodes();
    rulesSupplierEl.innerHTML = codes.map((code) =>
      `<option value="${esc(code)}">${esc(supplierName(code))} (${esc(code)})</option>`
    ).join('');
    if (prev && codes.includes(prev)) rulesSupplierEl.value = prev;
    else if (codes.length) rulesSupplierEl.value = codes[0];
  }

  function renderRulesContext() {
    if (!rulesContextEl || !rulesSupplierEl) return;
    const code = rulesSupplierEl.value;
    if (!code) {
      rulesContextEl.innerHTML = '<span>Selectează un furnizor pentru a vedea poziția în ordinea de scanare.</span>';
      return;
    }
    const order = config.scan_order.length ? config.scan_order : suppliers.map((s) => s.code);
    const pos = order.indexOf(code);
    const rank = pos >= 0 ? pos + 1 : '—';
    const tierSize = getTierSize();
    const inTier = pos >= 0 && pos < tierSize;
    const omitted = (config.omit_suppliers || []).includes(code);
    rulesContextEl.innerHTML = `
      <strong>${esc(supplierName(code))}</strong>
      <span class="pl-rules-context-badge">Poziția ${esc(rank)}</span>
      <span>${inTier ? 'Face parte din grupul ierarhic (top ' + esc(tierSize) + ')' : 'Verificat după grupul ierarhic'}</span>
      ${omitted ? '<span style="color:#dc2626;font-weight:600">Omis din comparare</span>' : ''}`;
  }

  function renderOmitList() {
    if (!omitSelectEl) return;
    const omitSet = new Set(config.omit_suppliers || []);
    omitSelectEl.innerHTML = suppliers.map((s) =>
      `<option value="${esc(s.code)}" ${omitSet.has(s.code) ? 'selected' : ''}>${esc(s.name)} (${esc(s.code)})</option>`
    ).join('');
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
      ignoreBrandsEl.innerHTML = '<p class="text-xs text-slate-400">Niciun brand ignorat. Alege furnizorul și brandul de mai sus.</p>';
      return;
    }
    ignoreBrandsEl.innerHTML = codes.map((code) => {
      const brands = map[code];
      const chips = brands.map((brand) => `
        <span class="pl-brand-chip" data-brand="${esc(brand)}">
          ${esc(brand)}
          <button type="button" class="pl-brand-remove" title="Elimină">×</button>
        </span>`).join('');
      return `
        <div class="pl-brand-rule" data-supplier-code="${esc(code)}">
          <span class="pl-brand-rule-supplier">${esc(supplierName(code))}</span>
          <span class="pl-brand-rule-code">${esc(code)}</span>
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
      stockZeroRowsEl.innerHTML = '<tr><td colspan="5" class="py-4 text-sm text-slate-500">Niciun furnizor activ.</td></tr>';
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
          <select class="pl-stock-mode box h-9 w-full max-w-[220px] rounded-md border px-2 text-sm">
            <option value="full"${mode === 'full' ? ' selected' : ''}>Afișează FULL</option>
            <option value="hide"${mode === 'hide' ? ' selected' : ''}>Ascunde produsul</option>
            <option value="out_of_stock"${mode === 'out_of_stock' ? ' selected' : ''}>Epuizat</option>
          </select>
        </td>
        <td class="py-3 pr-3">
          <label class="inline-flex items-center gap-2 text-sm">
            <input type="checkbox" class="pl-stock-include rounded border"${includeZero ? ' checked' : ''}>
            Include în scanare
          </label>
        </td>
        <td class="py-3 pr-3">
          <label class="inline-flex items-center gap-2 text-sm">
            <input type="checkbox" class="pl-stock-skip rounded border"${skipUnavailable ? ' checked' : ''}>
            Sari peste
          </label>
        </td>
        <td class="py-3">
          <button type="button" class="pl-stock-save fz-btn-outline h-9 px-3 text-xs">Salvează</button>
        </td>
      </tr>`;
    }).join('');

    stockZeroRowsEl.querySelectorAll('.pl-stock-save').forEach((btn) => {
      btn.addEventListener('click', () => saveStockZeroRule(btn.closest('[data-stock-code]')));
    });
  }

  async function saveStockZeroRule(rowEl) {
    if (!rowEl) return;
    const code = rowEl.getAttribute('data-stock-code') || '';
    const supplier = suppliers.find((s) => String(s.code) === code);
    if (!supplier || !supplier.randomn_id) {
      statusEl.textContent = 'Lipsește randomn_id pentru ' + code;
      return;
    }
    const mode = rowEl.querySelector('.pl-stock-mode')?.value || 'full';
    const includeZero = rowEl.querySelector('.pl-stock-include')?.checked ? 1 : 0;
    const skipUnavailable = rowEl.querySelector('.pl-stock-skip')?.checked ? 1 : 0;
    try {
      statusEl.textContent = 'Salvez ' + code + '…';
      await api('edit', {
        randomn_id: supplier.randomn_id,
        stock_zero_mode: mode,
        scan_include_zero_stock: includeZero,
        scan_skip_unavailable: skipUnavailable,
      });
      supplier.stock_zero_mode = mode;
      supplier.scan_include_zero_stock = includeZero;
      supplier.scan_skip_unavailable = skipUnavailable;
      statusEl.textContent = code + ': ' + stockZeroLabel(mode) + ' — salvat.';
      setTimeout(() => { statusEl.textContent = ''; }, 2500);
    } catch (e) {
      statusEl.textContent = e.message;
    }
  }

  function renderTest(data) {
    resultsEl?.classList.remove('hidden');
    const winners = Array.isArray(data.winners) ? data.winners : [];
    winnersEl.innerHTML = winners.length
      ? '<strong>Câștigători simulați:</strong> ' + winners.map((w) =>
          `${esc(w.code)} → ${esc(w.supplier)} (${Number(w.price).toFixed(2)} lei)`
        ).join(' · ')
      : 'Niciun câștigător în simulare.';
    const trace = Array.isArray(data.trace) ? data.trace : [];
    traceEl.innerHTML = trace.map((t) => `
      <tr class="border-b border-slate-100">
        <td class="py-2 pr-3">${esc(t.code)}</td>
        <td class="py-2 pr-3">${esc(t.supplier)}</td>
        <td class="py-2 pr-3">${esc(t.action)}</td>
        <td class="py-2">${esc(t.reason)}</td>
      </tr>`).join('');
  }

  async function load() {
    statusEl.textContent = 'Se încarcă…';
    const [logicData, listData] = await Promise.all([
      api('get_price_logic', {}),
      api('list', { per_page: 100, page: 1 }),
    ]);
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
    statusEl.textContent = '';
  }

  rulesSupplierEl?.addEventListener('change', renderRulesContext);
  omitSelectEl?.addEventListener('change', () => {
    config.omit_suppliers = readOmitFromForm();
    renderRulesSupplierSelect();
    renderRulesContext();
  });
  document.getElementById('pl-compare-tier-size')?.addEventListener('change', () => {
    config.compare_tier_size = Math.max(1, Math.min(10, Number(document.getElementById('pl-compare-tier-size')?.value) || 3));
    renderOrderList();
    renderRulesContext();
  });

  document.getElementById('pl-brand-add')?.addEventListener('click', addIgnoreBrand);
  brandInputEl?.addEventListener('keydown', (e) => {
    if (e.key !== 'Enter') return;
    e.preventDefault();
    addIgnoreBrand();
  });

  document.getElementById('pl-save')?.addEventListener('click', async () => {
    try {
      statusEl.textContent = 'Se salvează…';
      const payload = readFormConfig();
      const data = await api('save_price_logic', payload);
      config = data.config || payload;
      applyConfigToForm();
      statusEl.textContent = 'Salvat.';
      setTimeout(() => { statusEl.textContent = ''; }, 2500);
    } catch (e) {
      statusEl.textContent = e.message;
    }
  });

  document.getElementById('pl-test')?.addEventListener('click', async () => {
    try {
      statusEl.textContent = 'Test…';
      const cfg = readFormConfig();
      const data = await api('test_price_logic', { config: cfg });
      renderTest(data);
      statusEl.textContent = 'Test finalizat.';
      setTimeout(() => { statusEl.textContent = ''; }, 2500);
    } catch (e) {
      statusEl.textContent = e.message;
    }
  });

  window.addEventListener('besoiu:open-price-logic', () => {
    if (typeof window.furnizoriSetPageTab === 'function') {
      window.furnizoriSetPageTab('compare');
    }
    document.getElementById('price-logic-panel')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  });

  if (document.getElementById('price-logic-panel')) {
    load().catch((e) => { statusEl.textContent = e.message; });
  }
})();
</script>
