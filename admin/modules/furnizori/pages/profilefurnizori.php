<?php

use Besoiu\Core\Module\ModuleAssets;

$fpProCss = ModuleAssets::url('furnizori', 'css/furnizori-profile-pro.css');
if ($fpProCss !== '') {
    echo '<link rel="stylesheet" href="' . htmlspecialchars($fpProCss . '?v=20260718-layout-v4', ENT_QUOTES, 'UTF-8') . '">';
}
?>
<div class="furnizor-profile-page fp-pro-ui">
  <div id="furnizor-profile-toast" class="hidden fixed right-5 top-5 z-[100000] rounded-md border bg-white px-4 py-3 text-sm shadow"></div>

  <div class="fp-toolbar">
    <a href="/admin/suppliers" class="fp-btn fp-btn--ghost">
      <i data-lucide="arrow-left" class="size-4"></i>Înapoi la furnizori
    </a>
    <div class="fp-toolbar__actions">
      <button id="furnizor-profile-toggle" type="button" class="fp-btn fp-btn--ghost">Blochează / Deblochează</button>
      <button id="furnizor-profile-delete" type="button" class="fp-btn fp-btn--danger">Șterge</button>
    </div>
  </div>

  <header class="fp-hero">
    <div class="fp-hero__main">
      <div class="fp-hero__badge"><i data-lucide="truck"></i> Furnizor Pro · Besoiu</div>
      <h1 class="fp-hero__title" id="furnizor-profile-title">Furnizor</h1>
      <p class="fp-hero__lead" id="furnizor-profile-subtitle">Se încarcă datele furnizorului…</p>
      <div class="fp-hero__links">
        <a href="/admin/import-pro"><i data-lucide="zap" class="size-4"></i> Import Pro</a>
        <a href="/admin/suppliers?tab=compare"><i data-lucide="git-compare" class="size-4"></i> Comparare furnizori</a>
      </div>
    </div>
    <div class="fp-kpi-grid">
      <div class="fp-kpi">
        <div class="fp-kpi__icon"><i data-lucide="package"></i></div>
        <div><strong id="fp-kpi-products">—</strong><span>Produse în magazin</span></div>
      </div>
      <div class="fp-kpi">
        <div class="fp-kpi__icon"><i data-lucide="layers"></i></div>
        <div><strong id="fp-kpi-queue">—</strong><span>În coadă import</span></div>
      </div>
      <div class="fp-kpi">
        <div class="fp-kpi__icon"><i data-lucide="plug"></i></div>
        <div><strong id="fp-kpi-connection">—</strong><span>Tip conexiune</span></div>
      </div>
      <div class="fp-kpi">
        <div class="fp-kpi__icon"><i data-lucide="activity"></i></div>
        <div><strong id="fp-kpi-status">—</strong><span>Stare furnizor</span></div>
      </div>
    </div>
  </header>

  <nav class="fp-workflow-nav" id="furnizor-tabs-nav" aria-label="Secțiuni profil furnizor">
    <div class="fp-workflow-tabs" role="tablist">
      <button type="button" data-tab="general" class="furnizor-tab fp-workflow-tab active" role="tab" aria-selected="true">
        <span class="step-num">1</span>
        <span class="fp-workflow-tab__text"><strong>General</strong><small>Identitate &amp; note</small></span>
      </button>
      <button type="button" data-tab="pret" class="furnizor-tab fp-workflow-tab" role="tab" aria-selected="false">
        <span class="step-num">2</span>
        <span class="fp-workflow-tab__text"><strong>Formare preț</strong><small>Compensator &amp; adaos</small></span>
      </button>
      <button type="button" data-tab="scanare" class="furnizor-tab fp-workflow-tab" role="tab" aria-selected="false">
        <span class="step-num">3</span>
        <span class="fp-workflow-tab__text"><strong>Reguli scanare</strong><small>Stoc &amp; disponibilitate</small></span>
      </button>
      <button type="button" data-tab="produse" class="furnizor-tab fp-workflow-tab" role="tab" aria-selected="false">
        <span class="step-num">4</span>
        <span class="fp-workflow-tab__text"><strong>Produse</strong><small>Catalog importat</small></span>
      </button>
      <button type="button" data-tab="conexiune" class="furnizor-tab fp-workflow-tab" role="tab" aria-selected="false">
        <span class="step-num">5</span>
        <span class="fp-workflow-tab__text"><strong>Program &amp; import</strong><small>Sync &amp; conexiune</small></span>
      </button>
    </div>
  </nav>

  <form id="furnizor-profile-form" class="mt-0">
    <input type="hidden" name="randomn_id" id="furnizor-randomn-id">

    <div data-tab-pane="general" class="furnizor-pane fp-panel">
      <header class="fp-panel__head">
        <div class="fp-panel__icon"><i data-lucide="user"></i></div>
        <div>
          <h2>Date generale</h2>
          <p class="fp-panel__desc">Identitate, contact și note furnizor</p>
        </div>
      </header>
      <div class="fp-panel__body">
        <div class="fp-form-grid">
          <div class="fp-field-card">
            <label class="fp-field-card__label" for="fp-input-name">
              <span class="fp-field-card__icon"><i data-lucide="building-2"></i></span>
              Nume furnizor
            </label>
            <input class="fp-input" id="fp-input-name" name="name" required maxlength="255" placeholder="ex. Auto Partner">
          </div>
          <div class="fp-field-card">
            <label class="fp-field-card__label" for="fp-input-code">
              <span class="fp-field-card__icon"><i data-lucide="hash"></i></span>
              Cod scurt
            </label>
            <input class="fp-input" id="fp-input-code" name="code" maxlength="50" placeholder="AP, MA, EL…">
            <p class="fp-field-card__hint">Folosit la import (pSupplier) și în feed-uri</p>
          </div>
          <div class="fp-field-card">
            <label class="fp-field-card__label" for="fp-input-email">
              <span class="fp-field-card__icon"><i data-lucide="mail"></i></span>
              Email furnizor
            </label>
            <input class="fp-input" id="fp-input-email" type="email" name="conn_email" placeholder="contact@furnizor.ro">
            <p class="fp-field-card__hint">Doar informativ — nu se conectează automat</p>
          </div>
          <div class="fp-field-card">
            <label class="fp-field-card__label" for="fp-input-inbox">
              <span class="fp-field-card__icon"><i data-lucide="inbox"></i></span>
              Email inbox / fișiere
            </label>
            <input class="fp-input" id="fp-input-inbox" type="email" name="conn_email_inbox" placeholder="furnizor@domeniu.ro">
            <p class="fp-field-card__hint">Adresa unde primești liste CSV</p>
          </div>
          <div class="fp-field-card fp-field-card--wide">
            <label class="fp-field-card__label" for="fp-input-notes">
              <span class="fp-field-card__icon"><i data-lucide="file-text"></i></span>
              Note / descriere
            </label>
            <textarea class="fp-input fp-input--area" id="fp-input-notes" name="notes" rows="3" placeholder="Observații interne despre furnizor…"></textarea>
          </div>
        </div>

        <div class="fp-accent-stack">
          <section id="furnizor-import-info" class="fp-accent-card fp-accent-card--violet hidden" aria-label="Condiții import">
            <header class="fp-accent-card__head">
              <div class="fp-accent-card__icon"><i data-lucide="list-checks"></i></div>
              <div>
                <h3>Condiții import listă preț</h3>
                <p>Reguli aplicate la matching și import CSV</p>
              </div>
            </header>
            <div class="fp-stat-grid">
              <div class="fp-stat-box">
                <span class="fp-stat-box__label">Cod import (pSupplier)</span>
                <strong class="fp-stat-box__value" id="furnizor-import-code">—</strong>
              </div>
              <div class="fp-stat-box">
                <span class="fp-stat-box__label">Prioritate match preț</span>
                <strong class="fp-stat-box__value" id="furnizor-import-priority">—</strong>
              </div>
              <div class="fp-stat-box">
                <span class="fp-stat-box__label">Regulă TVA</span>
                <strong class="fp-stat-box__value" id="furnizor-import-vat">—</strong>
              </div>
              <div class="fp-stat-box">
                <span class="fp-stat-box__label">Coloană preț CSV</span>
                <strong class="fp-stat-box__value" id="furnizor-import-columns">—</strong>
              </div>
            </div>
          </section>

          <section id="furnizor-destinations-box" class="fp-accent-card fp-accent-card--green hidden" aria-label="Destinații export">
            <header class="fp-accent-card__head">
              <div class="fp-accent-card__icon"><i data-lucide="share-2"></i></div>
              <div>
                <h3>Destinații alimentate din cartela furnizor</h3>
                <p>Disponibil automat în generator, feed și căutare B2B</p>
              </div>
            </header>
            <div id="furnizor-destinations-list" class="fp-destinations"></div>
          </section>

          <section class="fp-accent-card fp-accent-card--blue" aria-label="Produse asociate">
            <header class="fp-accent-card__head">
              <div class="fp-accent-card__icon"><i data-lucide="package-check"></i></div>
              <div>
                <h3>Produse asociate</h3>
                <p>Rezumat catalog importat de la acest furnizor</p>
              </div>
            </header>
            <div class="fp-highlight-line" id="furnizor-products-summary">—</div>
          </section>
        </div>
      </div>
      <div class="furnizor-tab-save-bar" id="furnizor-tab-save-bar-general">
        <button type="button" id="furnizor-tab-save-general" class="furnizor-tab-save fp-btn fp-btn--primary" data-save-tab="general">
          <i data-lucide="save" class="size-4"></i>Salvează General
        </button>
      </div>
    </div>

    <div data-tab-pane="pret" class="furnizor-pane hidden fp-panel">
      <header class="fp-panel__head">
        <div class="fp-panel__icon fp-panel__icon--violet"><i data-lucide="percent"></i></div>
        <div>
          <h2>Formare preț</h2>
          <p class="fp-panel__desc">Compensator pre-import și previzualizare preț achiziție</p>
        </div>
      </header>
      <div class="fp-panel__body">
      <div class="fp-callout fp-callout--info mb-4">Formare preț în doi pași: <strong>(1)</strong> preț CSV + compensator pre-import % = preț achiziție (ex. 100 + 10% = 110); <strong>(2)</strong> preț achiziție + adaos comercial din <a href="/admin/adaoscomercial" class="font-medium underline">Adaos Comercial</a> + TVA = preț final în magazin.</div>
      <div class="fp-callout fp-callout--tip mb-4">
        <strong>Doar compensator pre-import aici (0% / 5% / 10%).</strong>
        <p class="mt-1">Adaosul comercial (reguli pe categorie, brand, prag preț) se configurează exclusiv în pagina
          <a href="/admin/adaoscomercial" class="font-medium underline">Adaos Comercial</a> — nu pe profilul furnizorului.</p>
      </div>

      <section class="fp-accent-card fp-accent-card--blue mb-4">
        <header class="fp-accent-card__head">
          <div class="fp-accent-card__icon"><i data-lucide="git-compare"></i></div>
          <div style="flex:1">
            <h3>Comparare furnizori (import)</h3>
            <p>Ordine scanare, furnizori omisi, verificări brand/stoc și strategie preț</p>
          </div>
          <a href="/admin/suppliers?tab=compare" class="fp-btn fp-btn--ghost" style="height:36px;padding:0 12px;font-size:12px;flex-shrink:0">
            Deschide compararea
          </a>
        </header>
        <div id="furnizor-price-logic-summary" class="fp-highlight-line" style="border-top:1px solid rgba(15,23,42,.06)">Se încarcă sumarul…</div>
      </section>

      <input type="hidden" name="price_markup_type" value="percentage">
      <div id="furnizor-feed-markup-lock-note" class="fp-callout fp-callout--warn mb-4 hidden">
        <strong>Acest furnizor nu permite adaos compensator (return 10% lunar).</strong>
        <p id="furnizor-feed-markup-lock-text" class="mt-1">Prețul din CSV este prețul de achiziție real — adaos feed = 0%.</p>
        <p class="mt-2 text-xs opacity-80">Pentru tm_054 (Elite +5%, Auto Partner +10%), deschide profilul furnizorului compensator:</p>
        <div id="furnizor-compensator-shortcuts" class="mt-2 flex flex-wrap gap-2"></div>
      </div>
      <div class="fp-form-grid">
        <div class="fp-field-card fp-field-card--wide">
          <label class="fp-field-card__label" for="furnizor-feed-markup-select">
            <span class="fp-field-card__icon"><i data-lucide="percent"></i></span>
            Compensator pre-import (%)
          </label>
          <select class="fp-input" id="furnizor-feed-markup-select" aria-label="Compensator pre-import">
            <option value="0">0% — CSV = preț achiziție</option>
            <option value="5">5% — compensator (ex. Elit)</option>
            <option value="10">10% — compensator (ex. Auto Partner)</option>
          </select>
          <input class="fp-input hidden mt-2" type="number" min="0" step="0.01" name="price_markup_value" id="furnizor-price-markup-value" value="0" placeholder="ex: 10" inputmode="decimal" autocomplete="off">
          <label id="furnizor-feed-markup-override-wrap" class="mt-2 hidden flex items-start gap-2 text-sm">
            <input type="checkbox" name="feed_markup_override" value="1" id="furnizor-feed-markup-override">
            <span>Modific manual compensatorul (override return 10% — implicit 0%, CSV = achiziție)</span>
          </label>
          <p id="furnizor-markup-editable-hint" class="fp-field-card__hint hidden" style="color:#047857">Câmp editabil — la salvare se recalculează automat prețurile produselor acestui furnizor.</p>
          <p id="furnizor-markup-locked-hint" class="fp-field-card__hint hidden">Blocat implicit (return 10%) — bifați caseta de mai sus dacă trebuie compensator personalizat.</p>
          <p class="fp-field-card__hint">Procent aplicat pe prețul din CSV înainte de adaosul comercial</p>
        </div>
      </div>
      <section class="fp-accent-card fp-accent-card--violet mt-4" id="furnizor-price-preview-box">
        <header class="fp-accent-card__head">
          <div class="fp-accent-card__icon"><i data-lucide="calculator"></i></div>
          <div>
            <h3>Previzualizare formare preț</h3>
            <p>Exemplu calcul pornind de la 100 lei din CSV</p>
          </div>
        </header>
        <ol class="fp-price-steps">
          <li><span>Preț furnizor (CSV)</span><strong>100,00 lei</strong></li>
          <li><span>+ adaos feed <em id="furnizor-preview-feed-pct">0</em>%</span><strong><span id="furnizor-preview-purchase-net">100,00</span> lei</strong></li>
          <li><span>+ adaos comercial + TVA</span><strong>Adaos Comercial → magazin</strong></li>
        </ol>
      </section>
      </div>
      <div class="furnizor-tab-save-bar" id="furnizor-tab-save-bar-pret">
        <button type="button" id="furnizor-tab-save-pret" class="furnizor-tab-save fp-btn fp-btn--primary" data-save-tab="pret">
          <i data-lucide="save" class="size-4"></i>Salvează Formare preț
        </button>
      </div>
    </div>

    <div data-tab-pane="scanare" class="furnizor-pane hidden fp-panel">
      <header class="fp-panel__head">
        <div class="fp-panel__icon fp-panel__icon--amber"><i data-lucide="scan-search"></i></div>
        <div>
          <h2>Reguli scanare</h2>
          <p class="fp-panel__desc">Comportament pentru stoc zero și produse indisponibile</p>
        </div>
      </header>
      <div class="fp-panel__body">
      <div class="fp-form-grid">
        <div class="fp-field-card">
          <label class="fp-field-card__label" for="fp-stock-zero">
            <span class="fp-field-card__icon"><i data-lucide="package-x"></i></span>
            Când stocul este 0
          </label>
          <select class="fp-input" id="fp-stock-zero" name="stock_zero_mode">
            <option value="full">Afișează ca FULL (stoc plin)</option>
            <option value="hide">Nu ne adresăm (ascunde produsul)</option>
            <option value="out_of_stock">Afișează ca epuizat</option>
          </select>
        </div>
        <div class="fp-field-card fp-field-card--toggle">
          <span class="fp-field-card__label">
            <span class="fp-field-card__icon"><i data-lucide="list-plus"></i></span>
            Opțiuni scanare
          </span>
          <label class="fp-toggle-row">
            <input type="checkbox" name="scan_include_zero_stock" value="1" checked>
            <span>Include produsele cu stoc 0 în scanare</span>
          </label>
          <label class="fp-toggle-row">
            <input type="checkbox" name="scan_skip_unavailable" value="1">
            <span>Sari peste produse indisponibile la furnizor</span>
          </label>
        </div>
      </div>
      </div>
      <div class="furnizor-tab-save-bar" id="furnizor-tab-save-bar-scanare">
        <button type="button" id="furnizor-tab-save-scanare" class="furnizor-tab-save fp-btn fp-btn--primary" data-save-tab="scanare">
          <i data-lucide="save" class="size-4"></i>Salvează Reguli scanare
        </button>
      </div>
    </div>

    <div data-tab-pane="produse" class="furnizor-pane hidden fp-panel">
      <header class="fp-panel__head">
        <div class="fp-panel__icon fp-panel__icon--green"><i data-lucide="package"></i></div>
        <div>
          <h2>Produse importate</h2>
          <p class="fp-panel__desc">Catalog publicat în magazin de la acest furnizor</p>
        </div>
      </header>
      <div class="fp-panel__body">
      <p class="mb-4 text-sm opacity-70">Doar produsele deja importate și publicate în magazin de la acest furnizor (<code>pSupplier = cod furnizor</code>).</p>
      <div id="furnizor-products-empty" class="hidden rounded-lg border border-dashed p-6 text-center text-sm opacity-70">Niciun produs importat in magazin de la acest furnizor.</div>
      <div class="overflow-x-auto">
        <table class="fp-products-table">
          <thead>
            <tr>
              <th>Cod</th>
              <th>Brand</th>
              <th>Denumire</th>
              <th>Preț</th>
              <th>Stoc</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody id="furnizor-products-body"></tbody>
        </table>
      </div>
      </div>
    </div>

    <div data-tab-pane="conexiune" class="furnizor-pane hidden fp-panel">
      <header class="fp-panel__head">
        <div class="fp-panel__icon"><i data-lucide="plug"></i></div>
        <div>
          <h2>Program &amp; import</h2>
          <p class="fp-panel__desc">Sincronizare automată, conexiune B2B și fișiere CSV</p>
        </div>
      </header>
      <div class="fp-panel__body">
      <div class="fp-import-layout">

        <section class="fp-import-card" aria-label="Program sincronizare">
          <header class="fp-import-card__head">
            <div>
              <h3 class="fp-import-card__title"><i data-lucide="clock"></i> Program sincronizare</h3>
              <p class="fp-import-card__sub">Când se descarcă automat fișierele FTP/SFTP pe server, în <code>admin/storage/supplier_feeds/{cod}/</code>. Job: <code>run_supplier_ftp_pull.bat</code> (Task Scheduler).</p>
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
              <p class="fp-import-card__sub">Furnizorul îți dă IP/host, login și parolă — noi ne conectăm la serverul lui și descărcăm listele la noi.</p>
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
              <p class="fp-import-field__hint" id="furnizor-ftp-panel-help" style="margin:-0.35rem 0 0.75rem">Completezi datele pe care ți le dă furnizorul. Noi ne conectăm la serverul LUI și tragem fișierele la noi.</p>
              <div class="fp-import-form-grid">
                <label class="fp-import-field">
                  <span class="fp-import-field__label">IP / host (de la furnizor)</span>
                  <input class="box h-10 rounded-md border px-3" type="text" name="conn_host" id="furnizor-sftp-host" placeholder="ftp.furnizor.ro sau 185.x.x.x" autocomplete="off">
                </label>
                <label class="fp-import-field">
                  <span class="fp-import-field__label">Port</span>
                  <input class="box h-10 rounded-md border px-3" type="number" min="1" max="65535" name="conn_port" id="furnizor-sftp-port" placeholder="21">
                </label>
                <label class="fp-import-field">
                  <span class="fp-import-field__label">Login (de la furnizor)</span>
                  <input class="box h-10 rounded-md border px-3" type="text" name="conn_username" id="furnizor-sftp-login" autocomplete="off">
                </label>
                <label class="fp-import-field">
                  <span class="fp-import-field__label">Parolă (de la furnizor)</span>
                  <input class="box h-10 rounded-md border px-3" type="password" name="conn_password" id="furnizor-sftp-password" autocomplete="new-password" placeholder="Lasă gol = păstrează">
                </label>
                <label class="fp-import-field fp-import-field--full">
                  <span class="fp-import-field__label">Folder pe serverul furnizorului</span>
                  <input class="box h-10 rounded-md border px-3" type="text" name="conn_remote_path" id="furnizor-conn-remote-path" placeholder="/ sau /export">
                  <p class="fp-import-field__hint">Calea de unde luăm lista (pe serverul lor). Gol = rădăcina contului.</p>
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
              <p class="fp-import-card__sub" id="furnizor-browse-help">Fișiere din folderul local al furnizorului (admin/storage/supplier_feeds/). Staging apare doar dacă fișierul nu e încă copiat local.</p>
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
      </div>
      <div class="furnizor-tab-save-bar" id="furnizor-tab-save-bar-conexiune">
        <button type="button" id="furnizor-tab-save-conexiune" class="furnizor-tab-save fp-btn fp-btn--primary" data-save-tab="conexiune" data-furnizor-tab-save="conexiune">
          <i data-lucide="save" class="size-4"></i>Salvează Program &amp; import
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
  const csrf=document.getElementById('bpa-api-live-arm')?.getAttribute('data-csrf')
    ||document.querySelector('[data-csrf]')?.getAttribute('data-csrf')
    ||window.BESOIU_ADMIN_CSRF||'';
  const headers={'Content-Type':'application/json'};
  if(csrf) headers['X-Admin-CSRF']=csrf;
  const response=await fetch(ENDPOINT,{method:'POST',headers,credentials:'same-origin',body:JSON.stringify({type_product:action,...payload})});
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
    ?'SFTP: pui IP/host, login și parola de la furnizor. Noi ne conectăm la serverul lui și descărcăm listele în folderul local de mai sus.'
    :'FTP: pui IP/host, login și parola de la furnizor. Noi ne conectăm la serverul lui și descărcăm listele în folderul local de mai sus.';
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
    const active=t.dataset.tab===name;
    t.classList.toggle('active',active);
    t.setAttribute('aria-selected',active?'true':'false');
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

function connectionTypeLabel(type){
  const map={api:'API B2B',ftp:'FTP',sftp:'SFTP',email:'Email'};
  return map[String(type||'').toLowerCase()]||String(type||'—').toUpperCase()||'—';
}
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
  const published=Number(data.products_published||0);
  const queue=Number(data.products_queue||0);
  const connType=String(data.connection_type||form.elements.namedItem('connection_type')?.value||'').toLowerCase();
  document.getElementById('furnizor-profile-subtitle').textContent=
    (code?code+' · ':'')+published+' în magazin · '+queue+' în coadă';
  const kpiProducts=document.getElementById('fp-kpi-products');
  const kpiQueue=document.getElementById('fp-kpi-queue');
  const kpiConnection=document.getElementById('fp-kpi-connection');
  const kpiStatus=document.getElementById('fp-kpi-status');
  if(kpiProducts) kpiProducts.textContent=String(published);
  if(kpiQueue) kpiQueue.textContent=String(queue);
  if(kpiConnection) kpiConnection.textContent=connectionTypeLabel(connType);
  if(kpiStatus){
    const active=data.status==='active';
    kpiStatus.textContent=active?'Activ':'Blocat';
    kpiStatus.classList.toggle('is-active',active);
    kpiStatus.classList.toggle('is-blocked',!active);
  }
  const lastScanEl=document.getElementById('furnizor-last-scan');
  if(lastScanEl){
    lastScanEl.textContent=(data.last_scan_at||'Niciodată')+(data.last_scan_status?' ('+data.last_scan_status+')':'');
  }
  if(data.scan_schedule_label){
    const sumEl=document.getElementById('furnizor-schedule-summary');
    if(sumEl) sumEl.textContent='Program: '+data.scan_schedule_label;
  }
  document.getElementById('furnizor-profile-toggle').textContent=data.status==='active'?'Blochează furnizorul':'Deblochează furnizorul';
  document.getElementById('furnizor-products-summary').innerHTML=
    '<span class="fp-highlight-num">'+published+'</span> produse importate în magazin'+
    (Number(data.products_queue_pending||0)>0
      ?' · <span class="fp-highlight-num fp-highlight-num--amber">'+Number(data.products_queue_pending||0)+'</span> în așteptare în coadă'
      :'')+'.';
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
        '<span class="fp-destination-badge fp-destination-badge--pro"><i data-lucide="check-circle"></i>'+escapeHtml(dest.label||dest.key||'')+'</span>'
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
  body.innerHTML=items.map(row=>`<tr>
    <td class="font-medium">${escapeHtml(row.code||'')}</td>
    <td>${escapeHtml(row.brand||'')}</td>
    <td>${escapeHtml(row.name||'')}</td>
    <td>${escapeHtml(row.price||'—')}</td>
    <td>${escapeHtml(row.stock||'—')}</td>
    <td>${escapeHtml(row.status||'Publicat')}</td>
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
      ?remote+(host?('  @ '+host):'')+' — folder pe serverul furnizorului (de unde descărcăm)'
      :'Configurează «Folder pe serverul furnizorului» de mai sus, apoi Salvează sau Reîncarcă lista.';
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
      :`Fisiere din folderul local ${local||'storage/supplier_feeds/{cod}/'}. Configurează «Folder pe serverul furnizorului» (calea de la ei).`;
  }else{
    help.textContent='Fișiere din folderul local '+ (local || 'storage/supplier_feeds/{cod}/') + '. Staging import apare doar pentru fișiere necopiate încă în folder.';
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
