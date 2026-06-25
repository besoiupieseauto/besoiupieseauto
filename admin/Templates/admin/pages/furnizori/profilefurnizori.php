<style>
  .furnizor-profile-page .fp-field-narrow input,
  .furnizor-profile-page .fp-field-narrow textarea,
  .furnizor-profile-page .fp-field-narrow select,
  .furnizor-profile-page .fp-field-narrow .box {
    max-width: 400px;
    width: 100%;
  }
  .furnizor-profile-page .fp-conn-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(160px, 400px));
    gap: 0.625rem 1rem;
    align-items: start;
  }
  .furnizor-profile-page .fp-conn-stack {
    display: flex;
    flex-direction: column;
    gap: 0.625rem;
  }
  .furnizor-profile-page .furnizor-pane {
    padding: 1rem 1.25rem !important;
  }
  .furnizor-profile-page .fp-cred-textarea {
    min-height: 4.5rem;
    max-height: 5.5rem;
    resize: vertical;
  }
  .furnizor-profile-page .conn-panel--compact {
    padding: 0.75rem 1rem;
  }
  .furnizor-profile-page #furnizor-browse-preview {
    max-height: 8rem;
  }
  .furnizor-profile-page #furnizor-browse-status {
    word-break: break-word;
    line-height: 1.45;
  }
  .furnizor-profile-page #furnizor-panel-browse {
    display: block !important;
    position: relative;
    z-index: 2;
    pointer-events: auto;
    opacity: 1 !important;
  }
  .furnizor-profile-page .conn-panel h4 {
    margin: 0 0 0.5rem;
    font-size: 0.75rem;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    opacity: 0.65;
  }
  .furnizor-profile-page #furnizor-price-markup-value.is-editable {
    opacity: 1 !important;
    pointer-events: auto !important;
    background: #fff !important;
    cursor: text;
  }
  .furnizor-profile-page #furnizor-price-markup-value.is-locked {
    opacity: 0.6 !important;
    cursor: not-allowed;
  }
  .furnizor-profile-page .fp-feed-folder-path {
    word-break: break-all;
    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    font-size: 0.8125rem;
  }
  /* Panouri Program & import — vizibilitate + click (conflicte display/z-index/pointer-events admin) */
  .furnizor-profile-page {
    position: relative;
    z-index: 1;
    pointer-events: auto;
    isolation: isolate;
  }
  .furnizor-profile-page .furnizor-pane.hidden {
    display: none !important;
    visibility: hidden !important;
    pointer-events: none !important;
  }
  .furnizor-profile-page .furnizor-pane:not(.hidden) {
    display: block !important;
    visibility: visible !important;
    pointer-events: auto;
  }
  .furnizor-profile-page .conn-panel.hidden {
    display: none !important;
  }
  .furnizor-profile-page .conn-panel:not(.hidden),
  .furnizor-profile-page #furnizor-feed-folder-box {
    display: block !important;
    position: relative;
    z-index: 2;
    pointer-events: auto;
    opacity: 1 !important;
  }
  .furnizor-profile-page #furnizor-feed-folder-box {
    z-index: 3;
  }
  /* ── Tab Program & import — layout carduri ── */
  .furnizor-profile-page .fp-import-layout {
    display: flex;
    flex-direction: column;
    gap: 1rem;
  }
  .furnizor-profile-page .fp-import-card {
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    background: #fff;
    overflow: hidden;
  }
  .furnizor-profile-page .fp-import-card__head {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.75rem 1.25rem;
    padding: 1rem 1.25rem;
    border-bottom: 1px solid #f1f5f9;
    background: #fafbfc;
  }
  .furnizor-profile-page .fp-import-card__title {
    margin: 0;
    font-size: 0.92rem;
    font-weight: 700;
    color: #0f172a;
    display: flex;
    align-items: center;
    gap: 0.45rem;
  }
  .furnizor-profile-page .fp-import-card__title i,
  .furnizor-profile-page .fp-import-card__title svg {
    width: 1rem;
    height: 1rem;
    color: #1abc9c;
    flex-shrink: 0;
  }
  .furnizor-profile-page .fp-destinations {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-top: 0.5rem;
  }
  .furnizor-profile-page .fp-destination-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.25rem 0.625rem;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 600;
    border: 1px solid #86efac;
    background: #ecfdf5;
    color: #166534;
  }
  .furnizor-profile-page .fp-import-card__sub {
    margin: 0.2rem 0 0;
    font-size: 0.78rem;
    line-height: 1.4;
    color: #64748b;
    max-width: 36rem;
  }
  .furnizor-profile-page .fp-import-card__body {
    padding: 1.1rem 1.25rem 1.25rem;
  }
  .furnizor-profile-page .fp-import-card__footer {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 0.65rem 1rem;
    padding: 0.75rem 1.25rem;
    border-top: 1px solid #f1f5f9;
    background: #f8fafc;
  }
  .furnizor-profile-page .fp-import-form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.85rem 1.25rem;
    align-items: start;
  }
  @media (max-width: 768px) {
    .furnizor-profile-page .fp-import-form-grid {
      grid-template-columns: 1fr;
    }
  }
  .furnizor-profile-page .fp-import-field {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    min-width: 0;
  }
  .furnizor-profile-page .fp-import-field--full {
    grid-column: 1 / -1;
  }
  .furnizor-profile-page .fp-import-field__label {
    font-size: 0.78rem;
    font-weight: 600;
    color: #475569;
    letter-spacing: 0.01em;
  }
  .furnizor-profile-page .fp-import-field__hint {
    margin: 0;
    font-size: 0.72rem;
    line-height: 1.4;
    color: #94a3b8;
  }
  .furnizor-profile-page .fp-import-field input.box,
  .furnizor-profile-page .fp-import-field select.box,
  .furnizor-profile-page .fp-import-field .box {
    max-width: none;
    width: 100%;
  }
  .furnizor-profile-page .fp-import-field .fp-time-row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.5rem;
  }
  .furnizor-profile-page .fp-import-field .fp-time-row input {
    width: 7.25rem;
    flex: 0 0 auto;
  }
  .furnizor-profile-page .fp-schedule-mode-panel.hidden {
    display: none !important;
  }
  .furnizor-profile-page .fp-switch {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.4rem 0.75rem;
    border-radius: 999px;
    border: 1px solid #d1fae5;
    background: #ecfdf5;
    font-size: 0.78rem;
    font-weight: 600;
    color: #166534;
    cursor: pointer;
    white-space: nowrap;
  }
  .furnizor-profile-page .fp-switch input {
    width: 1rem;
    height: 1rem;
    accent-color: #1abc9c;
  }
  .furnizor-profile-page .fp-schedule-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.35rem 0.7rem;
    border-radius: 999px;
    background: #ecfdf5;
    border: 1px solid #bbf7d0;
    font-size: 0.76rem;
    font-weight: 600;
    color: #166534;
  }
  .furnizor-profile-page .fp-last-scan-line {
    font-size: 0.76rem;
    color: #64748b;
  }
  .furnizor-profile-page .fp-last-scan-line strong {
    color: #334155;
    font-weight: 600;
  }
  .furnizor-profile-page .fp-import-note {
    margin: 0 0 1rem;
    padding: 0.65rem 0.85rem;
    border-radius: 10px;
    border: 1px solid #e2e8f0;
    background: #f8fafc;
    font-size: 0.76rem;
    line-height: 1.45;
    color: #64748b;
  }
  .furnizor-profile-page .fp-import-note code {
    font-size: 0.72rem;
    background: #fff;
    padding: 0.1rem 0.35rem;
    border-radius: 4px;
    border: 1px solid #e2e8f0;
  }
  .furnizor-profile-page .fp-folder-path {
    margin-top: 0.5rem;
    padding: 0.55rem 0.75rem;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
    background: #f8fafc;
    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    font-size: 0.75rem;
    line-height: 1.45;
    color: #334155;
    word-break: break-all;
  }
  .furnizor-profile-page #furnizor-feed-folder-box {
    border-color: #e2e8f0 !important;
    background: transparent !important;
    padding: 0 !important;
    margin: 0 !important;
    border: none !important;
  }
  .furnizor-profile-page .fp-conn-grid--wide {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    max-width: none;
  }
  @media (min-width: 900px) {
    .furnizor-profile-page .fp-conn-grid--wide {
      grid-template-columns: repeat(3, minmax(0, 1fr));
    }
  }
  .furnizor-profile-page .fp-conn-grid--wide .fp-field-narrow input,
  .furnizor-profile-page .fp-conn-grid--wide .fp-field-narrow select {
    max-width: none;
  }
  .furnizor-profile-page #furnizor-panel-browse {
    border: none;
    background: transparent;
    padding: 0;
  }
  .furnizor-profile-page .fp-browse-toolbar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.75rem;
  }
  .furnizor-profile-page .fp-browse-toolbar__title {
    flex: 1 1 12rem;
    min-width: 0;
  }
  .furnizor-profile-page .fp-browse-toolbar__title strong {
    display: block;
    font-size: 0.82rem;
    color: #334155;
  }
  .furnizor-profile-page .fp-browse-toolbar__title span {
    font-size: 0.72rem;
    color: #94a3b8;
  }
  .furnizor-profile-page .fp-browse-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
  }
  .furnizor-profile-page .fp-browse-actions .box {
    height: 2.125rem;
    padding: 0 0.75rem;
    font-size: 0.72rem;
  }
  .furnizor-profile-page .conn-panel input,
  .furnizor-profile-page .conn-panel select,
  .furnizor-profile-page .conn-panel textarea,
  .furnizor-profile-page .conn-panel button,
  .furnizor-profile-page #furnizor-connection-type {
    pointer-events: auto !important;
    position: relative;
    z-index: 4;
  }
  .furnizor-profile-page .conn-panel h4 {
    opacity: 1 !important;
  }
  .furnizor-profile-page #furnizor-browse-root,
  .furnizor-profile-page #furnizor-browse-path {
    pointer-events: auto !important;
    cursor: pointer;
    position: relative;
    z-index: 5;
  }
  .furnizor-profile-page #furnizor-browse-list:not(.hidden) {
    display: block !important;
    pointer-events: auto;
    position: relative;
    z-index: 2;
  }
  .furnizor-profile-page .fp-browse-paths {
    word-break: break-all;
    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    font-size: 0.75rem;
    line-height: 1.5;
  }
  .furnizor-profile-page .fp-browse-paths dt {
    font-weight: 600;
    color: #475569;
    margin-top: 0.35rem;
  }
  .furnizor-profile-page .fp-browse-paths dt:first-child {
    margin-top: 0;
  }
  .furnizor-profile-page .fp-browse-paths dd {
    margin: 0;
    color: #0f172a;
  }
  .furnizor-profile-page #furnizor-tabs-nav,
  .furnizor-profile-page #furnizor-tabs-nav .furnizor-tab {
    pointer-events: auto !important;
    position: relative;
    z-index: 4;
  }
  .furnizor-profile-page .furnizor-pane:not(.hidden) .furnizor-tab-save-bar {
    display: flex !important;
  }
  .furnizor-profile-page .furnizor-tab-save-bar {
    margin-top: 1.25rem;
    padding-top: 1rem;
    border-top: 1px solid rgba(15, 23, 42, 0.08);
    display: flex;
    justify-content: flex-end;
    gap: 0.5rem;
    pointer-events: auto !important;
    position: relative;
    z-index: 6;
  }
  .furnizor-profile-page .furnizor-tab-save,
  .furnizor-profile-page #furnizor-tab-save-general,
  .furnizor-profile-page #furnizor-tab-save-pret,
  .furnizor-profile-page #furnizor-tab-save-scanare,
  .furnizor-profile-page #furnizor-tab-save-conexiune {
    display: inline-flex !important;
    align-items: center;
    pointer-events: auto !important;
    cursor: pointer;
    position: relative;
    z-index: 7;
    opacity: 1 !important;
  }
</style>
<div class="furnizor-profile-page">
  <div id="furnizor-profile-toast" class="hidden fixed right-5 top-5 z-[100000] rounded-md border bg-white px-4 py-3 text-sm shadow"></div>

  <div class="mt-8 flex flex-wrap items-center gap-3">
    <a href="/admin/suppliers" class="box inline-flex h-10 items-center rounded-lg border px-4 text-sm">
      <i data-lucide="arrow-left" class="mr-2 size-4"></i>Inapoi la furnizori
    </a>
    <div class="ml-auto flex flex-wrap gap-2">
      <button id="furnizor-profile-toggle" type="button" class="box inline-flex h-10 items-center rounded-lg border px-4 text-sm">Blocheaza / Deblocheaza</button>
      <button id="furnizor-profile-delete" type="button" class="box inline-flex h-10 items-center rounded-lg border px-4 text-sm text-danger">Sterge</button>
    </div>
  </div>

  <div class="mt-6 flex items-center gap-4">
    <div id="furnizor-profile-avatar" class="flex size-14 items-center justify-center rounded-xl bg-primary/10 text-primary">
      <i data-lucide="truck" class="size-7"></i>
    </div>
    <div>
      <h2 id="furnizor-profile-title" class="text-xl font-semibold">Furnizor</h2>
      <p id="furnizor-profile-subtitle" class="text-sm opacity-70">-</p>
    </div>
  </div>

  <div class="mt-6 flex flex-wrap gap-1 border-b" id="furnizor-tabs-nav" role="tablist">
    <button type="button" data-tab="general" class="furnizor-tab box -mb-px inline-flex h-10 items-center gap-2 rounded-t-lg border border-b-0 px-4 text-sm bg-white">General</button>
    <button type="button" data-tab="pret" class="furnizor-tab box -mb-px inline-flex h-10 items-center gap-2 rounded-t-lg border border-b-0 px-4 text-sm">Formare pret</button>
    <button type="button" data-tab="scanare" class="furnizor-tab box -mb-px inline-flex h-10 items-center gap-2 rounded-t-lg border border-b-0 px-4 text-sm">Reguli scanare</button>
    <button type="button" data-tab="produse" class="furnizor-tab box -mb-px inline-flex h-10 items-center gap-2 rounded-t-lg border border-b-0 px-4 text-sm">Produse</button>
    <button type="button" data-tab="conexiune" class="furnizor-tab box -mb-px inline-flex h-10 items-center gap-2 rounded-t-lg border border-b-0 px-4 text-sm">Program &amp; import</button>
  </div>

  <form id="furnizor-profile-form" class="mt-0">
    <input type="hidden" name="randomn_id" id="furnizor-randomn-id">

    <div data-tab-pane="general" class="furnizor-pane box rounded-b-lg rounded-tr-lg border border-t-0 p-6">
      <div class="fp-conn-grid">
        <label class="fp-field-narrow">
          <span class="mb-1 block text-sm font-medium">Nume furnizor</span>
          <input class="box h-10 w-full rounded-md border px-3" name="name" required maxlength="255">
        </label>
        <label class="fp-field-narrow">
          <span class="mb-1 block text-sm font-medium">Cod scurt</span>
          <input class="box h-10 w-full rounded-md border px-3" name="code" maxlength="50" placeholder="AP, MA, EL...">
        </label>
        <label class="fp-field-narrow">
          <span class="mb-1 block text-sm font-medium">Email furnizor</span>
          <input class="box h-10 w-full rounded-md border px-3" type="email" name="conn_email" placeholder="contact@furnizor.ro">
          <p class="mt-1 text-xs opacity-60">Doar informativ — nu se conecteaza automat la server.</p>
        </label>
        <label class="fp-field-narrow">
          <span class="mb-1 block text-sm font-medium">Email inbox / fisiere</span>
          <input class="box h-10 w-full rounded-md border px-3" type="email" name="conn_email_inbox" placeholder="furnizor@domeniu.ro">
          <p class="mt-1 text-xs opacity-60">Adresa unde primesti liste — salvata ca referinta.</p>
        </label>
        <label class="fp-field-narrow col-span-full">
          <span class="mb-1 block text-sm font-medium">Note / descriere</span>
          <textarea class="box fp-cred-textarea w-full rounded-md border px-3 py-2" name="notes" rows="3"></textarea>
        </label>
        <div id="furnizor-import-info" class="col-span-12 hidden rounded-lg border bg-slate-50 p-4 text-sm">
          <div class="font-semibold">Conditii import lista pret</div>
          <dl class="mt-2 grid grid-cols-1 gap-2 md:grid-cols-2">
            <div><dt class="opacity-60">Cod import (pSupplier)</dt><dd id="furnizor-import-code" class="font-medium">-</dd></div>
            <div><dt class="opacity-60">Prioritate la match pret</dt><dd id="furnizor-import-priority" class="font-medium">-</dd></div>
            <div><dt class="opacity-60">Regula TVA</dt><dd id="furnizor-import-vat" class="font-medium">-</dd></div>
            <div><dt class="opacity-60">Coloana pret CSV</dt><dd id="furnizor-import-columns" class="font-medium">-</dd></div>
          </dl>
        </div>
        <div id="furnizor-destinations-box" class="col-span-12 hidden rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-950">
          <div class="font-semibold">Destinatii alimentate din cartela furnizor</div>
          <p class="mt-1 text-xs opacity-80">Un furnizor configurat este disponibil automat in generator Piese Auto, feed BaseLinker (produse importate) si Supplier Search (daca suportat).</p>
          <div id="furnizor-destinations-list" class="fp-destinations"></div>
        </div>
        <div class="col-span-12 rounded-lg border p-4 text-sm">
          <div class="font-semibold">Produse asociate</div>
          <div id="furnizor-products-summary" class="mt-1 opacity-80">-</div>
        </div>
      </div>
      <div class="furnizor-tab-save-bar" id="furnizor-tab-save-bar-general">
        <button type="button" id="furnizor-tab-save-general" class="furnizor-tab-save box inline-flex h-10 items-center rounded-lg border bg-primary px-6 py-2 text-sm text-white" data-save-tab="general">
          <i data-lucide="save" class="mr-2 size-4"></i>Salveaza General
        </button>
      </div>
    </div>

    <div data-tab-pane="pret" class="furnizor-pane hidden box rounded-b-lg rounded-tr-lg border border-t-0 p-6">
      <p class="mb-4 text-sm opacity-70">Formare preț în doi pași: <strong>(1)</strong> preț CSV + compensator pre-import % = preț achiziție (ex. 100 + 10% = 110); <strong>(2)</strong> preț achiziție + adaos comercial din <a href="/admin/adaoscomercial" class="text-primary underline">Adaos Comercial</a> + TVA = preț final în magazin. Compararea furnizorilor la același cod este în <strong>Comparare furnizori</strong>.</p>
      <div class="mb-4 rounded-lg border border-sky-200 bg-sky-50 p-4 text-sm text-sky-950">
        <strong>Doar compensator pre-import aici (0% / 5% / 10%).</strong>
        <p class="mt-1">Adaosul comercial (reguli pe categorie, brand, prag preț) se configurează exclusiv în pagina
          <a href="/admin/adaoscomercial" class="font-medium text-primary underline">Adaos Comercial</a> — nu pe profilul furnizorului.</p>
      </div>

      <div class="mb-6 rounded-lg border bg-slate-50 p-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div>
            <div class="text-sm font-semibold">Comparare furnizori (import)</div>
            <p class="mt-1 text-xs opacity-70">Ordine scanare, furnizori omisi, verificări brand/stoc și strategie preț.</p>
          </div>
          <a href="/admin/suppliers?tab=compare" class="box inline-flex h-9 items-center rounded-lg border bg-white px-3 text-xs font-medium hover:bg-foreground/5">
            Deschide compararea
          </a>
        </div>
        <div id="furnizor-price-logic-summary" class="mt-3 rounded-md border bg-white p-3 text-sm opacity-80">Se încarcă sumarul…</div>
      </div>

      <input type="hidden" name="price_markup_type" value="percentage">
      <div id="furnizor-feed-markup-lock-note" class="mb-4 hidden rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950">
        <strong>Acest furnizor nu permite adaos compensator (return 10% lunar).</strong>
        <p id="furnizor-feed-markup-lock-text" class="mt-1">Pretul din CSV este pretul de achizitie real — adaos feed = 0%.</p>
        <p class="mt-2 text-xs opacity-80">Pentru tm_054 (Elite +5%, Auto Partner +10%), deschide profilul furnizorului compensator:</p>
        <div id="furnizor-compensator-shortcuts" class="mt-2 flex flex-wrap gap-2"></div>
      </div>
      <div class="grid grid-cols-12 gap-4">
        <label class="col-span-12 md:col-span-4 fp-field-narrow">
          <span class="mb-1 block text-sm font-medium">Compensator pre-import (%)</span>
          <select class="box h-10 w-full rounded-md border px-3" id="furnizor-feed-markup-select" aria-label="Compensator pre-import">
            <option value="0">0% — CSV = preț achiziție</option>
            <option value="5">5% — compensator (ex. Elit)</option>
            <option value="10">10% — compensator (ex. Auto Partner)</option>
          </select>
          <input class="box mt-2 hidden h-10 w-full rounded-md border px-3" type="number" min="0" step="0.01" name="price_markup_value" id="furnizor-price-markup-value" value="0" placeholder="ex: 10" inputmode="decimal" autocomplete="off">
          <label id="furnizor-feed-markup-override-wrap" class="mt-2 hidden flex items-start gap-2 text-sm">
            <input type="checkbox" name="feed_markup_override" value="1" id="furnizor-feed-markup-override" class="mt-0.5 rounded border">
            <span>Modific manual compensatorul (override return 10% — implicit 0%, CSV = achiziție)</span>
          </label>
          <p id="furnizor-markup-editable-hint" class="mt-1 hidden text-xs text-emerald-700">Câmp editabil — la salvare se recalculează automat prețurile produselor acestui furnizor.</p>
          <p id="furnizor-markup-locked-hint" class="mt-1 hidden text-xs opacity-60">Blocat implicit (return 10%) — bifați caseta de mai sus dacă trebuie compensator personalizat.</p>
          <p class="mt-1 text-xs opacity-60">Procent aplicat pe prețul din CSV înainte de adaosul comercial (pasul 2 = Adaos Comercial).</p>
        </label>
      </div>
      <div class="mt-4 rounded-lg border bg-slate-50 p-4 text-sm" id="furnizor-price-preview-box">
        <strong>Formare pret (exemplu 100 lei din CSV):</strong>
        <ol class="mt-2 list-decimal space-y-1 pl-5">
          <li>Pret furnizor (CSV): <strong>100,00 lei</strong></li>
          <li>+ adaos feed <span id="furnizor-preview-feed-pct">0</span>% → pret baza (fara TVA): <strong><span id="furnizor-preview-purchase-net">100,00</span> lei</strong></li>
          <li>+ adaus comercial + TVA (pagina Adaos Comercial) → pret final in magazin</li>
        </ol>
      </div>
      <div class="furnizor-tab-save-bar" id="furnizor-tab-save-bar-pret">
        <button type="button" id="furnizor-tab-save-pret" class="furnizor-tab-save box inline-flex h-10 items-center rounded-lg border bg-primary px-6 py-2 text-sm text-white" data-save-tab="pret">
          <i data-lucide="save" class="mr-2 size-4"></i>Salveaza Formare pret
        </button>
      </div>
    </div>

    <div data-tab-pane="scanare" class="furnizor-pane hidden box rounded-b-lg rounded-tr-lg border border-t-0 p-6">
      <p class="mb-4 text-sm opacity-70">Reguli pentru produsele fara stoc sau indisponibile la furnizor.</p>
      <div class="grid grid-cols-12 gap-4">
        <label class="col-span-12 md:col-span-6">
          <span class="mb-1 block text-sm font-medium">Cand stocul este 0</span>
          <select class="box h-10 w-full rounded-md border px-3" name="stock_zero_mode">
            <option value="full">Afiseaza ca FULL (stoc plin)</option>
            <option value="hide">Nu ne adresam (ascunde produsul)</option>
            <option value="out_of_stock">Afiseaza ca epuizat</option>
          </select>
        </label>
        <label class="col-span-12 md:col-span-6 flex items-end">
          <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="scan_include_zero_stock" value="1" class="rounded border" checked>
            Include produsele cu stoc 0 in scanare
          </label>
        </label>
        <label class="col-span-12 md:col-span-6 flex items-end">
          <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="scan_skip_unavailable" value="1" class="rounded border">
            Sari peste produse indisponibile la furnizor
          </label>
        </label>
      </div>
      <div class="furnizor-tab-save-bar" id="furnizor-tab-save-bar-scanare">
        <button type="button" id="furnizor-tab-save-scanare" class="furnizor-tab-save box inline-flex h-10 items-center rounded-lg border bg-primary px-6 py-2 text-sm text-white" data-save-tab="scanare">
          <i data-lucide="save" class="mr-2 size-4"></i>Salveaza Reguli scanare
        </button>
      </div>
    </div>

    <div data-tab-pane="produse" class="furnizor-pane hidden box rounded-b-lg rounded-tr-lg border border-t-0 p-6">
      <p class="mb-4 text-sm opacity-70">Doar produsele deja importate si publicate in magazin de la acest furnizor (<code>pSupplier = cod furnizor</code>).</p>
      <div id="furnizor-products-empty" class="hidden rounded-lg border border-dashed p-6 text-center text-sm opacity-70">Niciun produs importat in magazin de la acest furnizor.</div>
      <div class="overflow-x-auto">
        <table class="w-full min-w-[640px] text-left text-sm">
          <thead class="border-b text-xs uppercase opacity-60">
            <tr>
              <th class="py-2 pr-3">Cod</th>
              <th class="py-2 pr-3">Brand</th>
              <th class="py-2 pr-3">Denumire</th>
              <th class="py-2 pr-3">Pret</th>
              <th class="py-2 pr-3">Stoc</th>
              <th class="py-2">Status</th>
            </tr>
          </thead>
          <tbody id="furnizor-products-body"></tbody>
        </table>
      </div>
    </div>

    <div data-tab-pane="conexiune" class="furnizor-pane hidden box rounded-b-lg rounded-tr-lg border border-t-0 p-6">
      <div class="fp-import-layout">

        <section class="fp-import-card" aria-label="Program sincronizare">
          <header class="fp-import-card__head">
            <div>
              <h3 class="fp-import-card__title"><i data-lucide="clock"></i> Program sincronizare</h3>
              <p class="fp-import-card__sub">Când rulează sync automat pentru acest furnizor (agentul respectă setările de mai jos).</p>
            </div>
            <label class="fp-switch">
              <input type="checkbox" name="scan_auto_enabled" value="1" checked>
              Automat activ
            </label>
          </header>
          <div class="fp-import-card__body">
            <div class="fp-import-form-grid">
              <label class="fp-import-field">
                <span class="fp-import-field__label">Mod programare</span>
                <select class="box h-10 rounded-md border px-3" name="scan_schedule_mode" id="furnizor-scan-schedule-mode">
                  <option value="interval">La fiecare X minute</option>
                  <option value="daily">O dată pe zi — oră fixă</option>
                  <option value="window">Interval orar + repetare</option>
                  <option value="manual">Doar manual</option>
                </select>
              </label>
              <div id="furnizor-schedule-panel-interval" class="fp-schedule-mode-panel fp-import-field" data-schedule-mode="interval">
                <span class="fp-import-field__label">Frecvență (minute)</span>
                <input class="box h-10 rounded-md border px-3" type="number" min="5" step="5" name="scan_interval_minutes" value="60">
                <p class="fp-import-field__hint">60 = oră · 360 = 6 ore · 1440 = zilnic</p>
              </div>
              <div id="furnizor-schedule-panel-daily" class="fp-schedule-mode-panel fp-import-field hidden" data-schedule-mode="daily">
                <span class="fp-import-field__label">Ora zilnică</span>
                <input class="box h-10 rounded-md border px-3" type="time" name="scan_schedule_time" value="06:00">
                <p class="fp-import-field__hint">O singură sincronizare pe zi, după această oră</p>
              </div>
              <div id="furnizor-schedule-panel-window" class="fp-schedule-mode-panel fp-import-field fp-import-field--full hidden" data-schedule-mode="window">
                <span class="fp-import-field__label">Interval orar</span>
                <div class="fp-time-row">
                  <input class="box h-10 rounded-md border px-3" type="time" name="scan_window_start" value="08:00">
                  <span class="text-xs text-slate-500">până la</span>
                  <input class="box h-10 rounded-md border px-3" type="time" name="scan_window_end" value="18:00">
                </div>
                <p class="fp-import-field__hint">În acest interval se repetă sync-ul la frecvența setată în minute</p>
              </div>
            </div>
          </div>
          <footer class="fp-import-card__footer">
            <span id="furnizor-schedule-summary" class="fp-schedule-pill">Program: —</span>
            <span class="fp-last-scan-line">Ultima scanare: <strong id="furnizor-last-scan">—</strong></span>
          </footer>
        </section>

        <section class="fp-import-card" aria-label="Conexiune și import">
          <header class="fp-import-card__head">
            <div>
              <h3 class="fp-import-card__title"><i data-lucide="plug"></i> Conexiune &amp; import</h3>
              <p class="fp-import-card__sub">Tip conexiune, credențiale și folderul local unde ajung CSV-urile.</p>
            </div>
          </header>
          <div class="fp-import-card__body">
            <div class="fp-import-form-grid" style="margin-bottom:1rem">
              <label class="fp-import-field">
                <span class="fp-import-field__label">Tip conexiune</span>
                <select class="box h-10 rounded-md border px-3" name="connection_type" id="furnizor-connection-type">
                  <option value="api">API B2B</option>
                  <option value="ftp">FTP</option>
                  <option value="sftp">SFTP</option>
                  <option value="email">Email</option>
                </select>
              </label>
            </div>

            <div id="furnizor-feed-folder-box" class="conn-panel" data-supplier-feed-inbox="1">
              <span class="fp-import-field__label">Folder local furnizor</span>
              <div id="furnizor-feed-folder-path" class="fp-folder-path" data-role="supplier-feed-folder-path">Se încarcă…</div>
              <p id="furnizor-feed-folder-hint" class="fp-import-field__hint" style="margin-top:0.35rem">CSV din Import produse → Copiază în folder local sau Deschide lista (mai jos).</p>
            </div>

            <div id="furnizor-panel-api" class="conn-panel mt-4" data-connection-panel="api">
              <span class="fp-import-field__label" style="display:block;margin-bottom:0.65rem">Setări API B2B</span>
              <div class="fp-import-form-grid">
                <label class="fp-import-field fp-import-field--full">
                  <span class="fp-import-field__label">URL API</span>
                  <input class="box h-10 rounded-md border px-3" type="url" name="api_base_url" placeholder="https://customerapi.autopartner.dev/CustomerAPI.svc/rest">
                </label>
                <label class="fp-import-field">
                  <span class="fp-import-field__label">Login API</span>
                  <input class="box h-10 rounded-md border px-3" type="text" name="api_credential_login" id="furnizor-api-login" autocomplete="off" placeholder="utilizator">
                  <p id="furnizor-api-login-saved" class="fp-import-field__hint hidden"></p>
                </label>
                <label class="fp-import-field">
                  <span class="fp-import-field__label">Parolă API</span>
                  <input class="box h-10 rounded-md border px-3" type="password" name="api_credential_password" id="furnizor-api-password" autocomplete="new-password" placeholder="Lasă gol = păstrează">
                </label>
                <label class="fp-import-field fp-import-field--full">
                  <span class="fp-import-field__label">Token API</span>
                  <input class="box h-10 rounded-md border px-3" type="password" name="api_credential_token" id="furnizor-api-token" autocomplete="new-password" placeholder="Bearer / access token">
                  <p id="furnizor-api-token-saved" class="fp-import-field__hint hidden"></p>
                </label>
                <input type="hidden" name="api_token" id="furnizor-api-token-json" value="">
              </div>
              <div id="furnizor-autopartner-files-hint" class="mt-3 hidden rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-950">
                <div class="font-semibold text-xs uppercase tracking-wide">Auto Partner — fișiere așteptate</div>
                <ul class="mt-2 list-disc space-y-1 pl-5 text-xs opacity-90">
                  <li><strong>3208129.csv</strong> — prețuri</li>
                  <li><strong>STANY.csv</strong> — stocuri</li>
                  <li><strong>INDEKS_PARAMETR.csv</strong> — parametri</li>
                </ul>
              </div>
            </div>

            <div id="furnizor-panel-ftp" class="conn-panel mt-4 hidden" data-connection-panel="ftp">
              <span class="fp-import-field__label" id="furnizor-ftp-panel-title" style="display:block;margin-bottom:0.65rem">Setări FTP</span>
              <p class="fp-import-field__hint" id="furnizor-ftp-panel-help" style="margin:-0.35rem 0 0.75rem">Date de logare pentru descărcarea automată a fișierelor.</p>
              <div class="fp-import-form-grid">
                <label class="fp-import-field">
                  <span class="fp-import-field__label">Server</span>
                  <input class="box h-10 rounded-md border px-3" type="text" name="conn_host" id="furnizor-sftp-host" placeholder="ftp.exemplu.ro" autocomplete="off">
                </label>
                <label class="fp-import-field">
                  <span class="fp-import-field__label">Port</span>
                  <input class="box h-10 rounded-md border px-3" type="number" min="1" max="65535" name="conn_port" id="furnizor-sftp-port" placeholder="21">
                </label>
                <label class="fp-import-field">
                  <span class="fp-import-field__label">Utilizator</span>
                  <input class="box h-10 rounded-md border px-3" type="text" name="conn_username" id="furnizor-sftp-login" autocomplete="off">
                </label>
                <label class="fp-import-field">
                  <span class="fp-import-field__label">Parolă</span>
                  <input class="box h-10 rounded-md border px-3" type="password" name="conn_password" id="furnizor-sftp-password" autocomplete="new-password" placeholder="Lasă gol = păstrează">
                </label>
                <label class="fp-import-field fp-import-field--full">
                  <span class="fp-import-field__label">Folder remote</span>
                  <input class="box h-10 rounded-md border px-3" type="text" name="conn_remote_path" id="furnizor-conn-remote-path" placeholder="/export">
                </label>
                <label class="fp-import-field flex items-end">
                  <span class="flex items-center gap-2 text-sm text-slate-600">
                    <input type="checkbox" name="conn_passive" value="1" class="rounded border">
                    Mod pasiv (FTP)
                  </span>
                </label>
              </div>
            </div>
          </div>
        </section>

        <section class="fp-import-card" aria-label="Fișiere sincronizate">
          <header class="fp-import-card__head">
            <div>
              <h3 class="fp-import-card__title"><i data-lucide="folder-open"></i> Fișiere sincronizate</h3>
              <p class="fp-import-card__sub" id="furnizor-browse-help">Folder local + staging import (storage/imports).</p>
            </div>
          </header>
          <div class="fp-import-card__body" style="padding-top:0.85rem">
            <div id="furnizor-panel-browse" class="conn-panel" data-supplier-browse-panel="1">
              <div class="fp-browse-toolbar">
                <div class="fp-browse-toolbar__title">
                  <strong id="furnizor-browse-title">Listă fișiere CSV</strong>
                  <span>Previzualizare și copiere în folderul furnizorului</span>
                </div>
                <div class="fp-browse-actions">
                  <button id="furnizor-mirror-feed" type="button" class="box rounded-lg border bg-white">Copiază local</button>
                  <button id="furnizor-browse-root" type="button" class="box rounded-lg border bg-white">Deschide lista</button>
                  <button id="furnizor-browse-path" type="button" class="box rounded-lg border bg-primary text-white">Reîncarcă</button>
                </div>
              </div>
              <dl id="furnizor-browse-paths" class="fp-browse-paths fp-folder-path" aria-live="polite">
                <dt>Folder local</dt>
                <dd id="furnizor-browse-local-path">—</dd>
                <dt id="furnizor-browse-remote-label" class="hidden">Cale remote</dt>
                <dd id="furnizor-browse-remote-path" class="hidden">—</dd>
              </dl>
              <div id="furnizor-browse-status" class="fp-import-field__hint" style="margin-top:0.65rem">Apasă Deschide lista pentru a vedea fișierele.</div>
              <div id="furnizor-browse-list" class="mt-3 hidden overflow-x-auto rounded-md border bg-white"></div>
              <pre id="furnizor-browse-preview" class="mt-3 hidden max-h-64 overflow-auto rounded-md border bg-slate-900 p-3 text-xs text-slate-100 whitespace-pre-wrap"></pre>
            </div>
          </div>
        </section>

      </div>
      <div class="furnizor-tab-save-bar" id="furnizor-tab-save-bar-conexiune">
        <button type="button" id="furnizor-tab-save-conexiune" class="furnizor-tab-save box inline-flex h-10 items-center rounded-lg border bg-primary px-6 py-2 text-sm text-white" data-save-tab="conexiune" data-furnizor-tab-save="conexiune">
          <i data-lucide="save" class="mr-2 size-4"></i>Salveaza Program &amp; import
        </button>
      </div>
    </div>
  </form>
</div><!-- /.furnizor-profile-page -->

<script>
(function(){'use strict';
const ENDPOINT='/admin/api/furnizori_endpoint.php';
const urlParams=new URLSearchParams(window.location.search);
const randomId=Number(urlParams.get('id')||urlParams.get('randomn_id')||0);
const allowedTabs=['general','pret','scanare','produse','conexiune'];
const TAB_SAVE_FIELDS={
  general:['name','code','conn_email','conn_email_inbox','notes'],
  pret:['price_markup_type','price_markup_value','feed_markup_override'],
  scanare:['stock_zero_mode','scan_include_zero_stock','scan_skip_unavailable'],
  conexiune:['connection_type','scan_auto_enabled','scan_schedule_mode','scan_interval_minutes','scan_schedule_time','scan_window_start','scan_window_end','api_base_url','api_credential_login','api_credential_password','api_credential_token','conn_host','conn_port','conn_username','conn_password','conn_remote_path','conn_passive']
};
const TAB_SAVE_LABELS={
  general:'General',
  pret:'Formare pret',
  scanare:'Reguli scanare',
  conexiune:'Program & import'
};
const initialTab=allowedTabs.includes(String(urlParams.get('tab')||'').toLowerCase())
  ?String(urlParams.get('tab')).toLowerCase()
  :'general';
const FEED_MARKUP_PRESETS=[0,5,10];
const toast=document.getElementById('furnizor-profile-toast');
const form=document.getElementById('furnizor-profile-form');
let furnizor=null;
let productsLoaded=false;
let priceLogicLoaded=false;

function formatPriceLogicSummary(config){
  if(!config) return 'Logica globală nu este încărcată.';
  const order=Array.isArray(config.scan_order)?config.scan_order.join(' → '):'—';
  const omit=Array.isArray(config.omit_suppliers)&&config.omit_suppliers.length?config.omit_suppliers.join(', '):'niciunul';
  const ignoreMap=config.ignore_brands_by_supplier&&typeof config.ignore_brands_by_supplier==='object'?config.ignore_brands_by_supplier:{};
  const ignoreParts=Object.keys(ignoreMap).map((code)=>{
    const brands=Array.isArray(ignoreMap[code])?ignoreMap[code].join(', '):'';
    return brands?`${escapeHtml(code)}: ${escapeHtml(brands)}`:'';
  }).filter(Boolean);
  const ignoreSummary=ignoreParts.length?ignoreParts.join(' · '):'niciunul';
  return `<strong>Ordine:</strong> ${escapeHtml(order)}<br>
    <strong>Omisi:</strong> ${escapeHtml(omit)}<br>
    <strong>Branduri ignorate:</strong> ${ignoreSummary}<br>
    <strong>Brand:</strong> ${escapeHtml(config.brand_verify||'exact')} ·
    <strong>Stoc:</strong> ${escapeHtml(config.stock_verify||'skip_zero')} ·
    <strong>Preț:</strong> ${escapeHtml(config.price_strategy||'lowest_then_priority')}`;
}
async function loadPriceLogicSummary(force){
  const box=document.getElementById('furnizor-price-logic-summary');
  if(!box) return;
  if(priceLogicLoaded&&!force) return;
  try{
    const data=await apiCall('get_price_logic',{});
    box.innerHTML=formatPriceLogicSummary(data.config||{});
    priceLogicLoaded=true;
  }catch(err){
    box.textContent='Nu s-a putut încărca: '+err.message;
  }
}
async function apiCall(action,payload){
  const response=await fetch(ENDPOINT,{method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({type_product:action,...payload})});
  const raw=await response.text();
  let result;try{result=JSON.parse(raw)}catch(e){throw new Error('Endpoint-ul nu a returnat JSON valid.')}
  if(!response.ok||!result.success)throw new Error(result.message||'Eroare');
  return result.data;
}
function showToast(msg,err){if(!toast)return;toast.textContent=msg;toast.classList.remove('hidden');toast.classList.toggle('text-danger',!!err);setTimeout(()=>toast.classList.add('hidden'),3500)}
function buildApiTokenPayload(){
  const login=String(form.elements.namedItem('api_credential_login')?.value||'').trim();
  const password=String(form.elements.namedItem('api_credential_password')?.value||'').trim();
  const token=String(form.elements.namedItem('api_credential_token')?.value||'').trim();
  if(!login&&!password&&!token) return '';
  const payload={};
  if(login){
    payload.login=login;
    payload.clientCode=login;
    payload.username=login;
  }
  if(password){
    payload.password=password;
    payload.clientPassword=password;
    payload.wsPassword=password;
  }
  if(token){
    payload.token=token;
    payload.access_token=token;
  }
  return JSON.stringify(payload);
}
function formToObjectForTab(tab){
  const fields=TAB_SAVE_FIELDS[tab];
  if(!fields) return {};
  const skipKeys=new Set(['api_credential_login','api_credential_password','api_credential_token']);
  const p={randomn_id:randomId,save_tab:tab};
  fields.forEach(k=>{
    if(skipKeys.has(k)) return;
    const el=form.elements.namedItem(k);
    if(!el) return;
    if(el.type==='checkbox'){
      p[k]=el.checked?'1':'0';
      return;
    }
    const val=String(el.value??'').trim();
    if(val!=='') p[k]=val;
  });
  if(tab==='pret'){
    const overrideOn=!!form.elements.namedItem('feed_markup_override')?.checked;
    const customInput=document.getElementById('furnizor-price-markup-value');
    const selectEl=document.getElementById('furnizor-feed-markup-select');
    if(overrideOn&&customInput&&!customInput.classList.contains('hidden')){
      p.price_markup_value=String(customInput.value??'0').trim()||'0';
    }else if(selectEl){
      p.price_markup_value=String(selectEl.value??'0').trim()||'0';
    }else{
      p.price_markup_value=String(customInput?.value??'0').trim()||'0';
    }
    if(!p.price_markup_type) p.price_markup_type='percentage';
    const code=String(furnizor?.supplier_code||furnizor?.code||'').trim();
    if(code) p.code=code;
  }
  if(tab==='conexiune'){
    const apiToken=buildApiTokenPayload();
    if(apiToken) p.api_token=apiToken;
    const code=String(furnizor?.supplier_code||furnizor?.code||form.elements.namedItem('code')?.value||'').trim();
    if(code) p.code=code;
  }
  return p;
}
function validateTabBeforeSave(tab){
  if(tab==='general'){
    const name=String(form.elements.namedItem('name')?.value||'').trim();
    if(!name){
      showToast('Numele furnizorului este obligatoriu.',true);
      return false;
    }
  }
  if(tab==='pret'){
    const overrideOn=!!form.elements.namedItem('feed_markup_override')?.checked;
    const customInput=document.getElementById('furnizor-price-markup-value');
    const selectEl=document.getElementById('furnizor-feed-markup-select');
    if(overrideOn&&customInput&&!customInput.classList.contains('hidden')){
      if(customInput.readOnly){
        showToast('Bifați «Modific manual compensatorul» pentru a edita valoarea.',true);
        return false;
      }
      const markupVal=parseFloat(String(customInput.value??'').replace(',','.'));
      if(!Number.isFinite(markupVal)||markupVal<0){
        showToast('Compensatorul trebuie să fie un număr pozitiv sau zero.',true);
        return false;
      }
    }else if(selectEl&&selectEl.disabled){
      showToast('Bifați «Modific manual compensatorul» pentru a edita valoarea.',true);
      return false;
    }
  }
  return true;
}
async function saveTab(tab){
  if(!validateTabBeforeSave(tab)) return;
  const label=TAB_SAVE_LABELS[tab]||tab;
  try{
    const result=await apiCall('edit',formToObjectForTab(tab));
    const repriced=Number(result?.products_repriced||0);
    showToast(
      repriced>0
        ?`Tab «${label}» salvat. ${repriced} produse au primit pret nou.`
        :`Tab «${label}» salvat.`,
      false
    );
    productsLoaded=false;
    priceLogicLoaded=false;
    await load();
    if(tab==='conexiune') browseRemote('/',{includeRemote:false}).catch(()=>{});
  }catch(err){showToast(err.message,true)}
}

function syncSchedulePanels(){
  const mode=(form.elements.namedItem('scan_schedule_mode')?.value||'interval').toLowerCase();
  const autoOn=form.elements.namedItem('scan_auto_enabled')?.checked!==false;
  document.querySelectorAll('.fp-schedule-mode-panel').forEach(el=>{
    const panelMode=el.getAttribute('data-schedule-mode')||'';
    const show=mode===panelMode&&(mode==='interval'||mode==='window'||mode==='daily');
    el.classList.toggle('hidden',!show);
  });
  const intervalPanel=document.getElementById('furnizor-schedule-panel-interval');
  if(intervalPanel&&(mode==='window'||mode==='interval')){
    intervalPanel.classList.remove('hidden');
  }
  const minutes=Number(form.elements.namedItem('scan_interval_minutes')?.value||60);
  const time=String(form.elements.namedItem('scan_schedule_time')?.value||'06:00');
  const wStart=String(form.elements.namedItem('scan_window_start')?.value||'08:00');
  const wEnd=String(form.elements.namedItem('scan_window_end')?.value||'18:00');
  let summary='Program: ';
  if(!autoOn){
    summary+='automat oprit';
  }else if(mode==='manual'){
    summary+='doar manual';
  }else if(mode==='daily'){
    summary+='zilnic la '+time;
  }else if(mode==='window'){
    summary+=wStart+'–'+wEnd+', la '+minutes+' min';
  }else{
    summary+='la '+minutes+' min';
  }
  const sumEl=document.getElementById('furnizor-schedule-summary');
  if(sumEl) sumEl.textContent=summary;
}
['scan_schedule_mode','scan_interval_minutes','scan_schedule_time','scan_window_start','scan_window_end','scan_auto_enabled'].forEach(name=>{
  form.elements.namedItem(name)?.addEventListener('change',syncSchedulePanels);
  form.elements.namedItem(name)?.addEventListener('input',syncSchedulePanels);
});

function syncConnectionPanels(){
  const type=(form.elements.namedItem('connection_type')?.value||'api').toLowerCase();
  const isApi=type==='api';
  const isFtp=type==='ftp'||type==='sftp';
  const isSftp=type==='sftp';
  document.getElementById('furnizor-panel-api')?.classList.toggle('hidden',!isApi);
  document.getElementById('furnizor-panel-ftp')?.classList.toggle('hidden',!isFtp);
  const ftpPanel=document.getElementById('furnizor-panel-ftp');
  if(ftpPanel){
    ftpPanel.dataset.connectionPanel=isSftp?'sftp':'ftp';
    ftpPanel.classList.toggle('furnizor-panel-sftp',isSftp);
  }
  const ftpTitle=document.getElementById('furnizor-ftp-panel-title');
  const ftpHelp=document.getElementById('furnizor-ftp-panel-help');
  if(ftpTitle) ftpTitle.textContent=isSftp?'Setări SFTP':'Setări FTP';
  if(ftpHelp) ftpHelp.textContent=isSftp
    ?'Alegi SFTP: salvezi datele de logare (host, port, login, parola). Folderul special de mai sus primeste fisierele dupa sync.'
    :'Conexiune FTP — datele de logare pentru descarcarea automata a fisierelor.';
  const portEl=form.elements.namedItem('conn_port');
  if(portEl&&isSftp&&String(portEl.value||'').trim()===''){
    portEl.value='22';
  }else if(portEl&&type==='ftp'&&String(portEl.value||'').trim()===''){
    portEl.value='21';
  }
  updateBrowseHelpFromForm();
}
form.elements.namedItem('connection_type')?.addEventListener('change',syncConnectionPanels);
['conn_remote_path','conn_host'].forEach(name=>{
  form.elements.namedItem(name)?.addEventListener('input',updateBrowseHelpFromForm);
});

function switchTab(name){
  document.querySelectorAll('.furnizor-tab').forEach(t=>{
    t.classList.toggle('bg-white',t.dataset.tab===name);
    t.classList.toggle('opacity-60',t.dataset.tab!==name);
  });
  document.querySelectorAll('.furnizor-pane').forEach(p=>p.classList.toggle('hidden',p.dataset.tabPane!==name));
}
document.querySelectorAll('.furnizor-tab').forEach(btn=>btn.addEventListener('click',()=>{
  const tabName=btn.dataset.tab;
  switchTab(tabName);
  if(tabName==='produse') loadProducts().catch(err=>showToast(err.message,true));
  if(tabName==='pret') loadPriceLogicSummary().catch(err=>showToast(err.message,true));
  if(tabName==='conexiune'){
    if(window.lucide) window.lucide.createIcons();
    browseRemote('/',{includeRemote:false}).catch(err=>showToast(err.message,true));
  }
}));

function formatRoMoney(value){
  const num=Number(value);
  if(!Number.isFinite(num)) return '0,00';
  return num.toLocaleString('ro-RO',{minimumFractionDigits:2,maximumFractionDigits:2});
}
function renderCompensatorShortcuts(links){
  const box=document.getElementById('furnizor-compensator-shortcuts');
  if(!box) return;
  const items=Array.isArray(links)?links:[];
  if(!items.length){
    box.innerHTML='<span class="text-xs opacity-70">Elit si Auto Partner — din lista Furnizori, tab Formare pret.</span>';
    return;
  }
  box.innerHTML=items.map(item=>{
    const sid=Number(item.randomn_id||0);
    const code=escapeHtml(item.code||'');
    const name=escapeHtml(item.name||code);
    const pct=Number(item.default_markup??item.price_markup_value??0);
    const href=sid>0?('/admin/profilefurnizori?randomn_id='+encodeURIComponent(String(sid))+'&tab=pret'):'#';
    return `<a href="${href}" class="box inline-flex h-9 items-center rounded-lg border bg-white px-3 text-xs font-medium hover:bg-foreground/5">${name} (${code}) — ${pct}%</a>`;
  }).join('');
}
function snapFeedMarkupPreset(value){
  const num=Number(value);
  if(!Number.isFinite(num)) return 0;
  return FEED_MARKUP_PRESETS.reduce((best, preset)=>{
    return Math.abs(preset-num)<Math.abs(best-num)?preset:best;
  },FEED_MARKUP_PRESETS[0]);
}
function syncFeedMarkupControlsFromValue(value,useCustom){
  const selectEl=document.getElementById('furnizor-feed-markup-select');
  const input=document.getElementById('furnizor-price-markup-value');
  const num=Math.max(0,Number(value)||0);
  if(useCustom){
    selectEl?.classList.add('hidden');
    input?.classList.remove('hidden');
    if(input) input.value=String(num);
    return;
  }
  selectEl?.classList.remove('hidden');
  input?.classList.add('hidden');
  if(selectEl){
    const preset=FEED_MARKUP_PRESETS.includes(num)?num:snapFeedMarkupPreset(num);
    selectEl.value=String(preset);
    if(input) input.value=String(preset);
  }
}
function setMarkupInputEditable(editable,useCustomOverride){
  const selectEl=document.getElementById('furnizor-feed-markup-select');
  const input=document.getElementById('furnizor-price-markup-value');
  const editableHint=document.getElementById('furnizor-markup-editable-hint');
  const lockedHint=document.getElementById('furnizor-markup-locked-hint');
  const useCustom=!!useCustomOverride;
  syncFeedMarkupControlsFromValue(input?.value||selectEl?.value||'0',useCustom);
  if(selectEl){
    selectEl.disabled=!editable;
    selectEl.classList.toggle('opacity-60',!editable);
  }
  if(input){
    input.readOnly=!editable;
    input.disabled=false;
    input.tabIndex=editable?0:-1;
    input.classList.toggle('is-editable',editable);
    input.classList.toggle('is-locked',!editable);
    input.classList.toggle('opacity-60',!editable);
    if(editable){
      input.removeAttribute('readonly');
      input.removeAttribute('disabled');
      input.removeAttribute('aria-disabled');
    }else{
      input.setAttribute('readonly','readonly');
      input.setAttribute('aria-disabled','true');
    }
  }
  editableHint?.classList.toggle('hidden',!editable);
  lockedHint?.classList.toggle('hidden',editable);
}
let feedMarkupOverrideBound=false;
function bindFeedMarkupOverrideCheckbox(){
  const overrideCheckbox=document.getElementById('furnizor-feed-markup-override');
  if(!overrideCheckbox||feedMarkupOverrideBound) return;
  feedMarkupOverrideBound=true;
  overrideCheckbox.addEventListener('change',()=>{
    const editable=overrideCheckbox.checked;
    setMarkupInputEditable(editable||!furnizor?.feed_markup_locked,editable);
    if(!editable){
      const selectEl=document.getElementById('furnizor-feed-markup-select');
      if(selectEl) selectEl.value='0';
      const input=document.getElementById('furnizor-price-markup-value');
      if(input) input.value='0';
      updatePricePreview();
    }
  });
}
function syncFeedMarkupLock(data){
  const locked=!!data?.feed_markup_locked;
  const hasOverride=!!data?.feed_markup_override;
  const editable=!locked||hasOverride;
  const note=document.getElementById('furnizor-feed-markup-lock-note');
  const lockText=document.getElementById('furnizor-feed-markup-lock-text');
  const overrideWrap=document.getElementById('furnizor-feed-markup-override-wrap');
  const overrideCheckbox=document.getElementById('furnizor-feed-markup-override');
  const markupValue=Number(data?.price_markup_value??0);
  note?.classList.toggle('hidden',!locked);
  overrideWrap?.classList.toggle('hidden',!locked);
  if(overrideCheckbox) overrideCheckbox.checked=hasOverride;
  syncFeedMarkupControlsFromValue(markupValue,hasOverride);
  bindFeedMarkupOverrideCheckbox();
  if(lockText&&data?.feed_markup_lock_reason) lockText.textContent=data.feed_markup_lock_reason;
  if(locked) renderCompensatorShortcuts(data?.compensator_profile_links);
  setMarkupInputEditable(editable,hasOverride);
  if(locked&&!hasOverride){
    const selectEl=document.getElementById('furnizor-feed-markup-select');
    if(selectEl) selectEl.value='0';
    const input=document.getElementById('furnizor-price-markup-value');
    if(input) input.value='0';
  }
}
function updatePricePreview(){
  const overrideOn=!!form.elements.namedItem('feed_markup_override')?.checked;
  const customInput=document.getElementById('furnizor-price-markup-value');
  const selectEl=document.getElementById('furnizor-feed-markup-select');
  let raw='0';
  if(overrideOn&&customInput&&!customInput.classList.contains('hidden')){
    raw=String(customInput.value??'0');
  }else if(selectEl){
    raw=String(selectEl.value??'0');
  }else{
    raw=String(customInput?.value??'0');
  }
  const val=parseFloat(raw||'0');
  const pct=isNaN(val)?0:Math.max(0,val);
  const supplierRaw=100;
  const purchaseNet=supplierRaw*(1+pct/100);
  const pctEl=document.getElementById('furnizor-preview-feed-pct');
  if(pctEl)pctEl.textContent=String(pct).replace('.',',');
  const purchaseEl=document.getElementById('furnizor-preview-purchase-net');
  if(purchaseEl)purchaseEl.textContent=formatRoMoney(purchaseNet);
}
form.elements.namedItem('price_markup_value')?.addEventListener('input',updatePricePreview);
document.getElementById('furnizor-feed-markup-select')?.addEventListener('change',updatePricePreview);

function fillForm(data){
  Object.entries(data).forEach(([k,v])=>{
    const el=form.elements.namedItem(k);
    if(!el)return;
    if(el.type==='checkbox'){el.checked=Number(v)===1||v===true||v==='1';return}
    el.value=v??'';
  });
  const sid=data.randomn_id||data.id||randomId;
  document.getElementById('furnizor-randomn-id').value=sid;
  const code=data.supplier_code||data.code||'';
  document.getElementById('furnizor-profile-title').textContent=data.name||'Furnizor';
  document.getElementById('furnizor-autopartner-files-hint')?.classList.toggle('hidden',code!=='AUTOPARTNER');
  document.getElementById('furnizor-profile-subtitle').textContent=
    (code?code+' · ':'')+(Number(data.products_published||0))+' in magazin · '+(Number(data.products_queue||0))+' in coada';
  const lastScanEl=document.getElementById('furnizor-last-scan');
  if(lastScanEl){
    lastScanEl.textContent=(data.last_scan_at||'Niciodată')+(data.last_scan_status?' ('+data.last_scan_status+')':'');
  }
  if(data.scan_schedule_label){
    const sumEl=document.getElementById('furnizor-schedule-summary');
    if(sumEl) sumEl.textContent='Program: '+data.scan_schedule_label;
  }
  document.getElementById('furnizor-profile-toggle').textContent=data.status==='active'?'Blocheaza furnizorul':'Deblocheaza furnizorul';
  document.getElementById('furnizor-products-summary').textContent=
    (Number(data.products_published||0))+' produse importate in magazin'+(Number(data.products_queue_pending||0)>0?', '+(Number(data.products_queue_pending||0))+' in asteptare in coada import':'')+'.';
  const importInfo=document.getElementById('furnizor-import-info');
  if(data.is_import_supplier){
    importInfo?.classList.remove('hidden');
    document.getElementById('furnizor-import-code').textContent=code||'-';
    document.getElementById('furnizor-import-priority').textContent=data.import_priority!=null?('#'+data.import_priority+' (mai mic = prioritate mai mare)'):'-';
    document.getElementById('furnizor-import-vat').textContent=data.import_vat_label||'-';
    document.getElementById('furnizor-import-columns').textContent=data.import_price_columns||'-';
  }else{
    importInfo?.classList.add('hidden');
  }
  const destinationsBox=document.getElementById('furnizor-destinations-box');
  const destinationsList=document.getElementById('furnizor-destinations-list');
  const destinations=Array.isArray(data.export_destinations)?data.export_destinations:[];
  if(destinationsBox&&destinationsList){
    if(code&&destinations.length){
      destinationsBox.classList.remove('hidden');
      destinationsList.innerHTML=destinations.map((dest)=>(
        '<span class="fp-destination-badge">'+escapeHtml(dest.label||dest.key||'')+'</span>'
      )).join('');
    }else{
      destinationsBox.classList.add('hidden');
      destinationsList.innerHTML='';
    }
  }
  const markupTypeEl=form.elements.namedItem('price_markup_type');
  if(markupTypeEl)markupTypeEl.value='percentage';
  syncFeedMarkupLock(data);
  syncSchedulePanels();
  syncConnectionPanels();
  updateBrowseHelp(data);
  updateBrowsePaths(data);
  updatePricePreview();
  const feedPath=document.getElementById('furnizor-feed-folder-path');
  if(feedPath){
    const rel=data.feed_folder_relative||'';
    const abs=data.feed_folder_path||'';
    feedPath.textContent=rel?(rel+(abs?('  ('+abs+')'):'')):'Folderul se creeaza la salvare (dupa cod furnizor).';
  }
  const apiLogin=document.getElementById('furnizor-api-login');
  const apiLoginSaved=document.getElementById('furnizor-api-login-saved');
  if(apiLogin) apiLogin.value=data.api_login_hint||'';
  if(apiLoginSaved){
    const hasHint=!!(data.api_login_hint||'').trim();
    apiLoginSaved.textContent=hasHint?'Login salvat — modificati doar daca schimbati credentialele.':'';
    apiLoginSaved.classList.toggle('hidden',!hasHint);
  }
  const apiPwd=document.getElementById('furnizor-api-password');
  if(apiPwd) apiPwd.value='';
  const apiTokenSaved=document.getElementById('furnizor-api-token-saved');
  const apiToken=document.getElementById('furnizor-api-token');
  if(apiToken) apiToken.value='';
  if(apiTokenSaved){
    const saved=!!data.api_token_saved||!!data.has_api_token;
    apiTokenSaved.textContent=saved?'Token salvat — lasati gol pentru a pastra.':'';
    apiTokenSaved.classList.toggle('hidden',!saved);
  }
  if(window.lucide)window.lucide.createIcons();
}

function renderProductsTable(items){
  const body=document.getElementById('furnizor-products-body');
  const empty=document.getElementById('furnizor-products-empty');
  if(!body)return;
  if(!items.length){
    body.innerHTML='';
    empty?.classList.remove('hidden');
    return;
  }
  empty?.classList.add('hidden');
  body.innerHTML=items.map(row=>`<tr class="border-b">
    <td class="py-2 pr-3 font-medium">${escapeHtml(row.code||'')}</td>
    <td class="py-2 pr-3">${escapeHtml(row.brand||'')}</td>
    <td class="py-2 pr-3">${escapeHtml(row.name||'')}</td>
    <td class="py-2 pr-3">${escapeHtml(row.price||'—')}</td>
    <td class="py-2 pr-3">${escapeHtml(row.stock||'—')}</td>
    <td class="py-2">${escapeHtml(row.status||'Publicat')}</td>
  </tr>`).join('');
}

function escapeHtml(v){return String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]))}

function formatBytes(bytes){
  if(!Number.isFinite(bytes)||bytes<=0)return '0 B';
  if(bytes>=1048576)return (bytes/1048576).toFixed(2)+' MB';
  if(bytes>=1024)return (bytes/1024).toFixed(1)+' KB';
  return bytes.toLocaleString('ro-RO')+' B';
}

async function loadProducts(force){
  if(productsLoaded&&!force) return;
  try{
    const result=await apiCall('products',{randomn_id:randomId,limit:50,scope:'imported'});
    renderProductsTable(result.items||[]);
    productsLoaded=true;
  }catch(err){
    renderProductsTable([]);
    showToast('Nu s-au putut incarca produsele: '+err.message,true);
  }
}

async function load(){
  if(!randomId)throw new Error('Lipseste id-ul furnizorului.');
  furnizor=await apiCall('get',{randomn_id:randomId});
  fillForm(furnizor);
  if(initialTab==='produse') await loadProducts();
  if(initialTab==='pret') await loadPriceLogicSummary();
}

form?.addEventListener('submit',e=>e.preventDefault());
document.querySelectorAll('.furnizor-tab-save').forEach(btn=>{
  btn.addEventListener('click',()=>{
    const tab=String(btn.dataset.saveTab||'').trim();
    if(!tab||!TAB_SAVE_FIELDS[tab]) return;
    saveTab(tab);
  });
});

document.getElementById('furnizor-profile-toggle')?.addEventListener('click',async()=>{
  try{
    await apiCall(furnizor.status==='active'?'block':'unblock',{randomn_id:randomId});
    showToast(furnizor.status==='active'?'Furnizor blocat.':'Furnizor activat.',false);
    await load();
  }catch(err){showToast(err.message,true)}
});

document.getElementById('furnizor-profile-delete')?.addEventListener('click',async()=>{
  if(!confirm('Confirmi stergerea furnizorului?'))return;
  try{
    await apiCall('delete',{randomn_id:randomId});
    window.location.href='/admin/suppliers';
  }catch(err){showToast(err.message,true)}
});

document.getElementById('furnizor-browse-root')?.addEventListener('click',()=>browseRemote('/',{includeRemote:false}));
document.getElementById('furnizor-browse-path')?.addEventListener('click',()=>browseRemote('/',{includeRemote:false}));
document.getElementById('furnizor-mirror-feed')?.addEventListener('click',async()=>{
  try{
    const result=await apiCall('mirror_feed_files',{randomn_id:randomId});
    const n=Array.isArray(result.copied)?result.copied.length:0;
    showToast(n>0?(n+' fisier(e) copiate in '+String(result.folder||'folder local')):'Fisierele sunt deja in folder sau lipsesc din import.',false);
    await browseRemote('/',{includeRemote:false});
  }catch(err){showToast(err.message,true)}
});

function joinRemotePath(base,name){
  if(!base||base==='/')return '/'+String(name).replace(/^\/+/,'');
  return String(base).replace(/\/+$/,'')+'/'+String(name).replace(/^\/+/,'');
}

function browseSourceLabel(entry){
  const source=String(entry?.source||'').toLowerCase();
  if(source==='local_feed') return 'Folder local';
  if(source==='sftp_remote'||source==='ftp_remote') return 'Remote SFTP/FTP';
  if(source==='import') return 'Import staging';
  return source||'—';
}

function browseFormContext(){
  return {
    connection_type:String(form.elements.namedItem('connection_type')?.value||'').trim(),
    conn_host:String(form.elements.namedItem('conn_host')?.value||'').trim(),
    conn_port:String(form.elements.namedItem('conn_port')?.value||'').trim(),
    conn_username:String(form.elements.namedItem('conn_username')?.value||'').trim(),
    conn_remote_path:String(form.elements.namedItem('conn_remote_path')?.value||'').trim(),
    conn_password:String(form.elements.namedItem('conn_password')?.value||'').trim(),
  };
}

function updateBrowseHelpFromForm(){
  const code=String(furnizor?.supplier_code||furnizor?.code||'').trim();
  const local=String(furnizor?.feed_folder_relative||'').trim()
    ||(code?'storage/supplier_feeds/'+code.toLowerCase():'storage/supplier_feeds/{cod}/');
  updateBrowseHelp({
    connection_type:form.elements.namedItem('connection_type')?.value||'',
    conn_remote_path:form.elements.namedItem('conn_remote_path')?.value||'',
    feed_folder_relative:local,
  });
  updateBrowsePaths({
    feed_folder_relative:local,
    feed_folder_path:furnizor?.feed_folder_path||'',
    connection_type:form.elements.namedItem('connection_type')?.value||'',
    conn_remote_path:form.elements.namedItem('conn_remote_path')?.value||'',
    conn_host:form.elements.namedItem('conn_host')?.value||'',
  });
}

function updateBrowsePaths(data){
  const localEl=document.getElementById('furnizor-browse-local-path');
  const remoteLabel=document.getElementById('furnizor-browse-remote-label');
  const remoteEl=document.getElementById('furnizor-browse-remote-path');
  const local=String(data?.local_path||data?.feed_folder_relative||'').trim();
  const abs=String(data?.feed_folder_path||'').trim();
  if(localEl){
    localEl.textContent=local?(local+(abs?('  ('+abs+')'):'')):'Se creeaza la salvare (admin/storage/supplier_feeds/{cod}/).';
  }
  const type=String(data?.connection_type||'').toLowerCase();
  const remote=String(data?.remote_path||data?.conn_remote_path||'').trim();
  const host=String(data?.remote_host||data?.conn_host||'').trim();
  const showRemote=(type==='sftp'||type==='ftp');
  remoteLabel?.classList.toggle('hidden',!showRemote);
  remoteEl?.classList.toggle('hidden',!showRemote);
  if(showRemote&&remoteEl){
    remoteEl.textContent=remote
      ?remote+(host?('  @ '+host):'')+' — fisierele de aici apar in lista (Remote SFTP/FTP)'
      :'Configureaza «Folder remote (SFTP/FTP)» de mai sus, apoi Salveaza sau Reincarca lista.';
  }
}

function updateBrowseHelp(data){
  const help=document.getElementById('furnizor-browse-help');
  if(!help) return;
  const type=String(data?.connection_type||'').toLowerCase();
  const remote=String(data?.conn_remote_path||'').trim();
  const local=String(data?.feed_folder_relative||'').trim();
  if(type==='sftp'||type==='ftp'){
    help.textContent=remote
      ?`Fisiere din folderul local ${local||'storage/supplier_feeds/{cod}/'} si din ${type.toUpperCase()} ${remote} (dupa sync). Apasa Deschide lista.`
      :`Fisiere din folderul local ${local||'storage/supplier_feeds/{cod}/'}. Configureaza «Folder remote (SFTP/FTP)» pentru calea de pe server.`;
  }else{
    help.textContent='CSV-uri incarcate pe server (sync agent sau Import manual) si din folderul local furnizor.';
  }
}

function formatBrowseStatus(data){
  const parts=['Lista fisiere'];
  const entries=Array.isArray(data.entries)?data.entries:[];
  if(entries.length) parts.push(entries.length+' fisiere');
  const mirror=data.mirror;
  if(mirror&&Array.isArray(mirror.copied)&&mirror.copied.length){
    parts.push(mirror.copied.length+' copiate in folder local');
  }
  if(data.local_path) parts.push('folder local: '+data.local_path);
  const conn=String(data.connection_type||'').toLowerCase();
  if((conn==='sftp'||conn==='ftp')&&data.remote_path){
    const host=data.remote_host?(' @ '+data.remote_host):'';
    parts.push((conn==='sftp'?'SFTP':'FTP')+': '+data.remote_path+host);
  }
  if(data.remote_list_error) parts.push(String(data.remote_list_error));
  else if(data.mode) parts.push(data.mode);
  return parts.join(' · ');
}

function renderBrowseResults(data){
  const status=document.getElementById('furnizor-browse-status');
  const tableWrap=document.getElementById('furnizor-browse-list');
  const preview=document.getElementById('furnizor-browse-preview');
  if(status)status.textContent=data.message||'';
  if(!data.success){
    tableWrap?.classList.add('hidden');
    preview?.classList.add('hidden');
    return;
  }
  if(status)status.textContent=formatBrowseStatus(data);
  updateBrowsePaths(data);
  const entries=Array.isArray(data.entries)?data.entries:[];
  if(tableWrap){
    if(!entries.length){
      tableWrap.innerHTML='<div class="p-3 text-sm opacity-70">Director gol sau listare indisponibila. Verifica folderul local si calea SFTP/FTP configurata.</div>';
    }else{
      tableWrap.innerHTML='<table class="w-full text-left text-sm"><thead class="border-b text-xs uppercase opacity-60"><tr><th class="p-2">Nume</th><th class="p-2">Sursa</th><th class="p-2">Tip</th><th class="p-2">Marime</th><th class="p-2"></th></tr></thead><tbody>'+
        entries.map(entry=>{
          const size=entry.size!=null?formatBytes(Number(entry.size)):'—';
          const fullPath=joinRemotePath(data.path||'/',entry.name);
          const action=entry.type==='dir'
            ? `<button type="button" class="text-primary" data-browse-path="${escapeHtml(fullPath)}">Deschide</button>`
            : `<button type="button" class="text-primary" data-preview-path="${escapeHtml(fullPath)}">Preview</button>`;
          return `<tr class="border-b"><td class="p-2 font-medium">${escapeHtml(entry.name)}</td><td class="p-2 text-xs opacity-80">${escapeHtml(browseSourceLabel(entry))}</td><td class="p-2">${entry.type==='dir'?'Folder':'Fisier'}</td><td class="p-2">${size}</td><td class="p-2">${action}</td></tr>`;
        }).join('')+'</tbody></table>';
    }
    tableWrap.classList.remove('hidden');
    tableWrap.querySelectorAll('[data-browse-path]').forEach(btn=>btn.addEventListener('click',()=>browseRemote(btn.getAttribute('data-browse-path')||'/')));
    tableWrap.querySelectorAll('[data-preview-path]').forEach(btn=>btn.addEventListener('click',()=>browseRemote(btn.getAttribute('data-preview-path')||'')));
  }
  if(data.preview&&preview){
    preview.textContent=(data.preview.path?('Fisier: '+data.preview.path+' ('+data.preview.bytes+' bytes)\n\n'):'')+(data.preview.content||'');
    preview.classList.remove('hidden');
  }else{
    preview?.classList.add('hidden');
  }
}

async function browseRemote(path,options){
  const opts=options&&typeof options==='object'?options:{};
  const includeRemote=opts.includeRemote===true;
  const status=document.getElementById('furnizor-browse-status');
  if(status)status.textContent=includeRemote?'Se incarca lista (local + remote)...':'Se incarca fisierele locale...';
  try{
    const data=await apiCall('browseconnection',{
      randomn_id:randomId,
      path:path||'',
      include_remote:includeRemote?1:0,
      auto_mirror:1,
      ...browseFormContext()
    });
    renderBrowseResults(data);
  }catch(err){
    if(status)status.textContent=err.message;
    showToast(err.message,true);
  }
}

switchTab(initialTab);
if(initialTab==='conexiune')browseRemote('/',{includeRemote:false}).catch(err=>showToast(err.message,true));
load().catch(e=>showToast(e.message,true));
})();
</script>
