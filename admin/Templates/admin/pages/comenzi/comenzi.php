<?php

declare(strict_types=1);

$legacyCaietBootstrap = [
    'ready' => true,
    'lazy' => true,
    'error' => '',
    'endpoint' => '/admin/api/caiet_comenzi_endpoint.php',
];

$legacyCaietBootstrapJson = json_encode(
    $legacyCaietBootstrap,
    JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
);
if (!is_string($legacyCaietBootstrapJson)) {
    $legacyCaietBootstrapJson = '{"ready":true,"lazy":true,"error":"","endpoint":"/admin/api/caiet_comenzi_endpoint.php"}';
}

function besoiu_legacy_h($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/** @param array<string, mixed> $tabConfig */
function besoiu_legacy_render_panel(string $tabKey, array $tabConfig): string
{
    $title = besoiu_legacy_h($tabConfig['title'] ?? $tabKey);

    ob_start();
    ?>
    <div class="besoiu-legacy-panel" data-legacy-panel="<?= besoiu_legacy_h($tabKey) ?>" id="legacy-panel-<?= besoiu_legacy_h($tabKey) ?>">
        <div class="besoiu-toolbar">
            <span class="besoiu-counter legacy-counter" id="legacy-counter-<?= besoiu_legacy_h($tabKey) ?>"><?= $title ?></span>
            <div class="besoiu-toolbar__spacer"></div>
            <div class="besoiu-toolbar__search">
                <input id="legacy-search-<?= besoiu_legacy_h($tabKey) ?>" class="legacy-search" type="text" placeholder="Cauta client, telefon, cod..." data-legacy-field="search">
                <i data-lucide="search" class="size-4"></i>
            </div>
            <button id="legacy-refresh-<?= besoiu_legacy_h($tabKey) ?>" class="legacy-refresh" type="button" data-action="legacy-refresh">
                <i data-lucide="refresh-cw" class="size-4"></i>
                Reincarca
            </button>
        </div>

        <div class="besoiu-filters box">
            <div class="grid grid-cols-12 gap-4">
                <div class="col-span-12 md:col-span-4">
                    <label class="besoiu-filters__label" for="legacy-status-<?= besoiu_legacy_h($tabKey) ?>">Status</label>
                    <select id="legacy-status-<?= besoiu_legacy_h($tabKey) ?>" class="legacy-status" data-legacy-field="status">
                        <option value="">Toate statusurile</option>
                        <option value="1">Comandat</option><option value="2">Sosit</option><option value="3">Cash</option>
                        <option value="4">Avans</option><option value="5">Retur</option><option value="6">Card</option>
                        <option value="7">FD</option><option value="12">In verificare</option>
                    </select>
                </div>
                <div class="col-span-12 md:col-span-4">
                    <label class="besoiu-filters__label" for="legacy-date-from-<?= besoiu_legacy_h($tabKey) ?>">De la</label>
                    <input id="legacy-date-from-<?= besoiu_legacy_h($tabKey) ?>" class="legacy-date-from" type="date" data-legacy-field="date-from">
                </div>
                <div class="col-span-12 md:col-span-4">
                    <label class="besoiu-filters__label" for="legacy-date-to-<?= besoiu_legacy_h($tabKey) ?>">Pana la</label>
                    <input id="legacy-date-to-<?= besoiu_legacy_h($tabKey) ?>" class="legacy-date-to" type="date" data-legacy-field="date-to">
                </div>
            </div>
        </div>

        <div class="besoiu-kpi-strip">
            <div class="besoiu-kpi-mini besoiu-kpi-mini--green">
                <div class="besoiu-kpi-mini__label">Total luna</div>
                <div id="legacy-stat-month-<?= besoiu_legacy_h($tabKey) ?>" class="legacy-stat-month">—</div>
            </div>
            <div class="besoiu-kpi-mini besoiu-kpi-mini--amber">
                <div class="besoiu-kpi-mini__label">Total zi</div>
                <div id="legacy-stat-day-<?= besoiu_legacy_h($tabKey) ?>" class="legacy-stat-day">—</div>
            </div>
            <div class="besoiu-kpi-mini besoiu-kpi-mini--sky">
                <div class="besoiu-kpi-mini__label">Venituri luna</div>
                <div id="legacy-stat-revenue-<?= besoiu_legacy_h($tabKey) ?>" class="legacy-stat-revenue">—</div>
            </div>
        </div>

        <div class="besoiu-table-wrap mt-5">
            <table class="besoiu-data-table">
                <thead>
                    <tr>
                        <th class="w-8"></th>
                        <th>Data</th>
                        <th>Client</th>
                        <th>Marca</th>
                        <th>Linii</th>
                        <th class="text-right">Total</th>
                        <th class="text-center">Status</th>
                        <th>Notite</th>
                        <th class="text-center">Actiuni</th>
                    </tr>
                </thead>
                <tbody id="legacy-body-<?= besoiu_legacy_h($tabKey) ?>" class="legacy-body" data-legacy-field="body">
                    <tr><td colspan="9" class="besoiu-empty">Deschide tab-ul pentru a incarca comenzile.</td></tr>
                </tbody>
            </table>
        </div>
    </div>
    <?php
    return (string) ob_get_clean();
}
?>
<div class="besoiu-page comenzi-admin-page" data-page-title="Comenzi">
    <h2 class="sr-only">Comenzi</h2>

    <div id="comenzi-toast" class="besoiu-toast hidden" role="status" aria-live="polite"></div>
    <div id="legacy-comenzi-toast" class="besoiu-toast besoiu-toast--stack hidden" role="status" aria-live="polite"></div>

    <script type="application/json" id="legacy-comenzi-bootstrap"><?= $legacyCaietBootstrapJson ?></script>

    <div class="besoiu-dash-hero">
        <div>
            <h1>Comenzi — Besoiu Piese Auto</h1>
            <p class="besoiu-dash-hero__meta" id="comenzi-hero-meta">Comenzi site și fluxuri TM, UTVIN, externe — panouri aliniate admin Besoiu.</p>
        </div>
        <div class="besoiu-dash-hero__actions">
            <button id="comenzi-open-create" type="button" class="besoiu-btn-primary">
                <i data-lucide="plus" class="size-4"></i>
                Comandă nouă
            </button>
            <button id="comenzi-export-excel" type="button" class="besoiu-btn-secondary">
                <i data-lucide="file-spreadsheet" class="size-4"></i>
                Export Excel
            </button>
            <button id="comenzi-refresh" type="button" class="besoiu-btn-secondary">
                <i data-lucide="refresh-cw" class="size-4"></i>
                Sincronizează
            </button>
        </div>
    </div>

    <div class="besoiu-kpi-strip">
        <div class="besoiu-kpi-mini besoiu-kpi-mini--green">
            <div class="besoiu-kpi-mini__label">Total comenzi</div>
            <div class="besoiu-kpi-mini__value" id="comenzi-kpi-total">—</div>
        </div>
        <div class="besoiu-kpi-mini besoiu-kpi-mini--amber">
            <div class="besoiu-kpi-mini__label">Pe această pagină</div>
            <div class="besoiu-kpi-mini__value" id="comenzi-kpi-page">—</div>
        </div>
        <div class="besoiu-kpi-mini besoiu-kpi-mini--sky">
            <div class="besoiu-kpi-mini__label">Pagină</div>
            <div class="besoiu-kpi-mini__value" id="comenzi-kpi-pagenum">—</div>
        </div>
        <div class="besoiu-kpi-mini besoiu-kpi-mini--violet">
            <div class="besoiu-kpi-mini__label">Ultima sincronizare</div>
            <div class="besoiu-kpi-mini__value besoiu-kpi-mini__value--sm" id="comenzi-kpi-sync">—</div>
        </div>
    </div>

    <div class="besoiu-tabs" role="tablist">
        <button type="button" id="comenzi-tab-btn-standard" class="comenzi-tab-btn besoiu-tabs__btn besoiu-tabs__btn--active" data-tab="standard" data-action="comenzi-tab-switch" data-active="1">Toate comenzile</button>
        <button type="button" id="comenzi-tab-btn-tm" class="comenzi-tab-btn besoiu-tabs__btn" data-tab="tm" data-action="comenzi-tab-switch">Comenzi TM</button>
        <button type="button" id="comenzi-tab-btn-utvin" class="comenzi-tab-btn besoiu-tabs__btn" data-tab="utvin" data-action="comenzi-tab-switch">Comenzi UTVIN</button>
        <button type="button" id="comenzi-tab-btn-ext" class="comenzi-tab-btn besoiu-tabs__btn" data-tab="ext" data-action="comenzi-tab-switch">Comenzi externe</button>
    </div>

    <div id="comenzi-tab-standard" class="comenzi-tab-panel comenzi-tab-panel--active mt-5" data-comenzi-tab-panel="standard" aria-hidden="false">
        <div class="besoiu-toolbar">
            <span id="comenzi-counter" class="besoiu-counter">Se încarcă...</span>
            <div class="besoiu-toolbar__spacer"></div>
            <div class="besoiu-toolbar__search">
                <input id="comenzi-search" type="text" placeholder="Caută comandă, telefon, VIN...">
                <i data-lucide="search" class="size-4"></i>
            </div>
        </div>

        <div class="besoiu-filters box">
            <div class="grid grid-cols-12 gap-4">
                <div class="col-span-12 md:col-span-3">
                    <label class="besoiu-filters__label" for="comenzi-status-filter">Status comandă</label>
                    <select id="comenzi-status-filter" class="w-full">
                        <option value="">Toate comenzile</option>
                        <option value="noua">Nouă</option>
                        <option value="in_lucru">În lucru</option>
                        <option value="platita">Plătită</option>
                        <option value="expediata">Expediată</option>
                        <option value="finalizata">Finalizată</option>
                        <option value="retur">Retur</option>
                        <option value="anulata">Anulată</option>
                    </select>
                </div>
                <div class="col-span-12 md:col-span-3">
                    <label class="besoiu-filters__label" for="comenzi-channel-filter">Canal</label>
                    <select id="comenzi-channel-filter" class="w-full">
                        <option value="">Toate canalele</option>
                        <option value="website">Website</option>
                        <option value="whatsapp">WhatsApp</option>
                        <option value="olx">OLX</option>
                        <option value="pieseauto">PieseAuto.ro</option>
                        <option value="facebook">Facebook</option>
                        <option value="manual">Manual</option>
                    </select>
                </div>
                <div class="col-span-12 md:col-span-3">
                    <label class="besoiu-filters__label" for="comenzi-payment-filter">Plată</label>
                    <select id="comenzi-payment-filter" class="w-full">
                        <option value="">Toate</option>
                        <option value="ramburs">Ramburs</option>
                        <option value="card_online">Card online</option>
                        <option value="card_fizic">Card fizic</option>
                        <option value="numerar">Numerar</option>
                        <option value="confirmata">Plată confirmată</option>
                        <option value="esuata">Plată eșuată</option>
                    </select>
                </div>
                <div class="col-span-12 md:col-span-3">
                    <label class="besoiu-filters__label" for="comenzi-date-filter">Perioadă</label>
                    <input id="comenzi-date-filter" class="w-full" type="date">
                </div>
            </div>
        </div>

        <div class="besoiu-table-wrap mt-5">
            <table class="besoiu-data-table">
                <thead>
                <tr>
                    <th>Comandă</th>
                    <th>Client</th>
                    <th>Produs / Piesă</th>
                    <th>Canal</th>
                    <th class="text-center">Plată</th>
                    <th class="text-center">Livrare</th>
                    <th class="text-center">Factură / AWB</th>
                    <th class="text-center">Status</th>
                    <th class="text-center">Acțiuni</th>
                </tr>
                </thead>
                <tbody id="comenzi-table-body">
                <tr><td colspan="9" class="besoiu-empty">Se încarcă comenzile...</td></tr>
                </tbody>
            </table>
        </div>
        <div id="comenzi-pagination" class="mt-5"></div>
    </div>

    <div id="comenzi-tab-tm" class="comenzi-tab-panel mt-5 hidden" data-comenzi-tab-panel="tm" data-legacy-tab="tm">
        <?= besoiu_legacy_render_panel('tm', ['title' => 'Comenzi Timisoara']) ?>
    </div>
    <div id="comenzi-tab-utvin" class="comenzi-tab-panel mt-5 hidden" data-comenzi-tab-panel="utvin" data-legacy-tab="utvin">
        <?= besoiu_legacy_render_panel('utvin', ['title' => 'Comenzi Utvin']) ?>
    </div>
    <div id="comenzi-tab-ext" class="comenzi-tab-panel mt-5 hidden" data-comenzi-tab-panel="ext" data-legacy-tab="ext">
        <?= besoiu_legacy_render_panel('ext', ['title' => 'Comenzi externe']) ?>
    </div>

    <div
        id="comenzi-modal"
        class="besoiu-modal-backdrop hidden"
        aria-hidden="true"
        role="dialog"
        aria-labelledby="comenzi-modal-title"
    >
        <div class="besoiu-modal besoiu-modal--lg" role="document">
            <div class="besoiu-modal__head">
                <h3 id="comenzi-modal-title">Comandă nouă</h3>
                <button type="button" id="comenzi-close-modal" class="besoiu-modal__close" aria-label="Închide">×</button>
            </div>

            <div id="comenzi-modal-shortcuts" class="besoiu-modal-shortcuts hidden">
                <p class="besoiu-modal-shortcuts__label">Acțiuni rapide</p>
                <div class="besoiu-modal-shortcuts__actions">
                    <a id="comenzi-shortcut-client" href="/admin/clienti" class="besoiu-action-btn besoiu-action-btn--primary"><i data-lucide="user"></i> Client</a>
                    <a id="comenzi-shortcut-facturi" href="/admin/facturi" class="besoiu-action-btn"><i data-lucide="file-text"></i> Factură</a>
                    <a id="comenzi-shortcut-livrare" href="/admin/livrare" class="besoiu-action-btn"><i data-lucide="truck"></i> AWB / Livrare</a>
                    <a id="comenzi-shortcut-whatsapp" href="#" target="_blank" rel="noopener" class="besoiu-action-btn"><i data-lucide="message-circle"></i> WhatsApp</a>
                </div>
            </div>

            <form id="comenzi-form" class="besoiu-modal__body" data-comenzi-form data-action="add">
                <input type="hidden" name="randomn_id">

                <div class="grid grid-cols-12 gap-4">
                    <label class="col-span-12 md:col-span-6 besoiu-modal__field">
                        <span>Client</span>
                        <input type="text" name="client_name" maxlength="160">
                    </label>

                    <label class="col-span-12 md:col-span-6 besoiu-modal__field">
                        <span>Telefon</span>
                        <input type="tel" name="phone" maxlength="50">
                    </label>

                    <label class="col-span-12 md:col-span-6 besoiu-modal__field">
                        <span>Email</span>
                        <input type="email" name="email" maxlength="255">
                    </label>

                    <label class="col-span-12 md:col-span-6 besoiu-modal__field">
                        <span>VIN</span>
                        <input type="text" name="vin" maxlength="40">
                    </label>

                    <label class="col-span-12 besoiu-modal__field">
                        <span>Produs / piesă</span>
                        <input type="text" name="product_name" required maxlength="255">
                    </label>

                    <label class="col-span-12 md:col-span-4 besoiu-modal__field">
                        <span>Canal</span>
                        <select name="channel">
                            <option value="manual">Manual</option>
                            <option value="website">Website</option>
                            <option value="whatsapp">WhatsApp</option>
                            <option value="olx">OLX</option>
                            <option value="pieseauto">PieseAuto.ro</option>
                            <option value="facebook">Facebook</option>
                        </select>
                    </label>

                    <label class="col-span-12 md:col-span-4 besoiu-modal__field">
                        <span>Plată</span>
                        <select name="payment_status">
                            <option value="ramburs">Ramburs</option>
                            <option value="card_online">Card online</option>
                            <option value="confirmata">Confirmată</option>
                            <option value="esuata">Eșuată</option>
                        </select>
                    </label>

                    <label class="col-span-12 md:col-span-4 besoiu-modal__field">
                        <span>Status</span>
                        <select name="order_status">
                            <option value="noua">Nouă</option>
                            <option value="in_lucru">În lucru</option>
                            <option value="platita">Plătită</option>
                            <option value="expediata">Expediată</option>
                            <option value="finalizata">Finalizată</option>
                            <option value="retur">Retur</option>
                            <option value="anulata">Anulată</option>
                        </select>
                    </label>

                    <label class="col-span-12 md:col-span-3 besoiu-modal__field">
                        <span>Cantitate</span>
                        <input type="number" name="quantity" min="1" value="1">
                    </label>

                    <label class="col-span-12 md:col-span-3 besoiu-modal__field">
                        <span>Total</span>
                        <input type="number" name="total_amount" min="0" step="0.01" value="0.00">
                    </label>

                    <label class="col-span-12 md:col-span-3 besoiu-modal__field">
                        <span>Livrare</span>
                        <input type="text" name="delivery_method" maxlength="80" placeholder="Curier">
                    </label>

                    <label class="col-span-12 md:col-span-3 besoiu-modal__field">
                        <span>Status livrare</span>
                        <input type="text" name="delivery_status" maxlength="80" placeholder="AWB negenerat">
                    </label>

                    <label class="col-span-12 besoiu-modal__field">
                        <span>Note</span>
                        <textarea name="notes" rows="3"></textarea>
                    </label>
                </div>

                <div class="besoiu-modal__foot">
                    <button type="button" id="comenzi-cancel" class="besoiu-btn-secondary">Anulează</button>
                    <button type="submit" class="besoiu-btn-primary">Salvează</button>
                </div>
            </form>
        </div>
    </div>

    <div id="legacy-comenzi-modal" class="besoiu-modal-backdrop comenzi-legacy-modal hidden" aria-hidden="true" role="dialog" aria-labelledby="legacy-comenzi-modal-title">
        <div class="besoiu-modal besoiu-modal--wide" role="document">
            <div class="besoiu-modal__head">
                <h3 id="legacy-comenzi-modal-title">Detalii comandă caiet</h3>
                <button id="legacy-comenzi-close-modal" class="besoiu-modal__close" type="button" aria-label="Închide">×</button>
            </div>
            <div class="besoiu-modal__body">
                <div class="grid grid-cols-12 gap-3 mb-4">
                    <div class="col-span-12 md:col-span-3">
                        <div class="besoiu-modal__meta-label">ID comandă</div>
                        <div id="legacy-comenzi-modal-order-id" class="besoiu-modal__meta-value">-</div>
                    </div>
                    <div class="col-span-12 md:col-span-3">
                        <div class="besoiu-modal__meta-label">Tip</div>
                        <div id="legacy-comenzi-modal-source" class="besoiu-modal__meta-value">-</div>
                    </div>
                    <div class="col-span-12 md:col-span-3">
                        <div class="besoiu-modal__meta-label">Status curent</div>
                        <div id="legacy-comenzi-modal-status" class="besoiu-modal__meta-value">-</div>
                    </div>
                    <div class="col-span-12 md:col-span-3">
                        <div class="besoiu-modal__meta-label">Total</div>
                        <div id="legacy-comenzi-modal-total" class="besoiu-modal__meta-value">0.00 RON</div>
                    </div>
                </div>
                <div class="grid grid-cols-12 gap-3 mb-4">
                    <div class="col-span-12 md:col-span-6 besoiu-modal__field">
                        <span>Actualizează status</span>
                        <select id="legacy-comenzi-new-status">
                            <option value="1">1 - Comandat</option>
                            <option value="2">2 - Sosit</option>
                            <option value="3">3 - Cash</option>
                            <option value="4">4 - Avans</option>
                            <option value="5">5 - Retur</option>
                            <option value="6">6 - Card</option>
                            <option value="7">7 - FD</option>
                            <option value="8">8 - Anulat</option>
                            <option value="9">9 - Facturat</option>
                            <option value="12">12 - În verificare</option>
                        </select>
                    </div>
                    <div class="col-span-12 md:col-span-6 flex items-end">
                        <button id="legacy-comenzi-save-status" class="besoiu-btn-primary" type="button">Salvează status</button>
                    </div>
                </div>
                <div class="besoiu-table-wrap">
                    <table class="besoiu-data-table">
                        <thead>
                        <tr>
                            <th>ID detaliu</th>
                            <th>ID produs</th>
                            <th class="text-center">Cantitate</th>
                            <th class="text-right">Preț</th>
                            <th>Furnizor</th>
                            <th>Culoare</th>
                        </tr>
                        </thead>
                        <tbody id="legacy-comenzi-lines-body"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
  'use strict';

  const ENDPOINT = '/admin/api/comenzi_endpoint.php';
  const form = document.getElementById('comenzi-form');
  const modal = document.getElementById('comenzi-modal');
  const tableBody = document.getElementById('comenzi-table-body');
  const toast = document.getElementById('comenzi-toast');
  const counter = document.getElementById('comenzi-counter');
  const filters = {
    search: document.getElementById('comenzi-search'),
    status: document.getElementById('comenzi-status-filter'),
    channel: document.getElementById('comenzi-channel-filter'),
    payment: document.getElementById('comenzi-payment-filter'),
    date: document.getElementById('comenzi-date-filter'),
  };
  let comenzi = [];
  let listMeta = { page: 1, total: 0, per_page: 10, total_pages: 1 };
  let currentPage = 1;
  const paginationEl = document.getElementById('comenzi-pagination');

  async function apiCall(actionType, payload) {
    const response = await fetch(ENDPOINT, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ type_product: actionType, ...payload }),
    });
    const rawText = await response.text();
    let result;
    try {
      result = JSON.parse(rawText);
    } catch (error) {
      throw new Error('Endpoint-ul nu a returnat JSON valid.');
    }
    if (!response.ok || !result.success) {
      throw new Error(result.message || 'Eroare necunoscuta');
    }
    return result.data;
  }

  function formToObject(formElement) {
    const payload = {};
    new FormData(formElement).forEach((value, key) => {
      if (String(value).trim() !== '') {
        payload[key] = value;
      }
    });
    return payload;
  }

  function showToast(message, isError) {
    if (!toast) return;
    toast.textContent = message;
    toast.classList.remove('hidden', 'besoiu-toast--error');
    if (isError) toast.classList.add('besoiu-toast--error');
    setTimeout(() => toast.classList.add('hidden'), 3000);
  }

  function updateComenziKpi() {
    const totalEl = document.getElementById('comenzi-kpi-total');
    const pageEl = document.getElementById('comenzi-kpi-page');
    const pagenumEl = document.getElementById('comenzi-kpi-pagenum');
    const syncEl = document.getElementById('comenzi-kpi-sync');
    if (totalEl) totalEl.textContent = String(listMeta.total ?? 0);
    if (pageEl) pageEl.textContent = String(comenzi.length);
    if (pagenumEl) pagenumEl.textContent = `${listMeta.page || 1} / ${listMeta.total_pages || 1}`;
    if (syncEl) {
      const now = new Date();
      syncEl.textContent = now.toLocaleTimeString('ro-RO', { hour: '2-digit', minute: '2-digit' });
    }
    if (counter) counter.textContent = `${listMeta.total} comenzi`;
  }

  function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, (char) => ({
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#039;',
    }[char]));
  }

  function openModal(order) {
    if (!form || !modal) return;
    form.reset();
    form.dataset.action = order ? 'edit' : 'add';
    document.getElementById('comenzi-modal-title').textContent = order ? 'Editează comanda' : 'Comandă nouă';

    const shortcuts = document.getElementById('comenzi-modal-shortcuts');
    const shortcutClient = document.getElementById('comenzi-shortcut-client');
    const shortcutFacturi = document.getElementById('comenzi-shortcut-facturi');
    const shortcutLivrare = document.getElementById('comenzi-shortcut-livrare');
    const shortcutWhatsapp = document.getElementById('comenzi-shortcut-whatsapp');

    if (order) {
      Object.entries(order).forEach(([key, value]) => {
        const field = form.elements.namedItem(key);
        if (field) field.value = value ?? '';
      });

      if (shortcuts) shortcuts.classList.remove('hidden');
      const phone = String(order.phone || '').trim();
      const clientQ = encodeURIComponent(phone || order.client_name || '');
      if (shortcutClient) shortcutClient.href = '/admin/clienti?q=' + clientQ;
      if (shortcutFacturi) {
        const inv = order.invoice_randomn_id ? '?id=' + encodeURIComponent(order.invoice_randomn_id) : '';
        shortcutFacturi.href = '/admin/facturi' + inv;
      }
      if (shortcutLivrare) {
        const awb = order.livrare_randomn_id ? '?id=' + encodeURIComponent(order.livrare_randomn_id) : '';
        shortcutLivrare.href = '/admin/livrare' + awb;
      }
      if (shortcutWhatsapp && phone) {
        const digits = phone.replace(/\D+/g, '');
        const orderNo = order.order_number || ('ORD-' + (order.randomn_id || ''));
        const text = encodeURIComponent('Buna ziua, referitor la comanda ' + orderNo + '.');
        shortcutWhatsapp.href = digits ? ('https://wa.me/4' + digits.replace(/^0/, '') + '?text=' + text) : '#';
        shortcutWhatsapp.classList.toggle('hidden', !digits);
      }
    } else if (shortcuts) {
      shortcuts.classList.add('hidden');
    }

    modal.classList.remove('hidden');
    modal.setAttribute('aria-hidden', 'false');
    if (window.lucide) window.lucide.createIcons();
  }

  function closeModal() {
    modal?.classList.add('hidden');
    modal?.setAttribute('aria-hidden', 'true');
  }

  function normalizeFilterValue(value) {
    return String(value || '').toLowerCase().trim();
  }

  function filteredOrders() {
    const query = (filters.search?.value || '').toLowerCase().trim();
    const status = normalizeFilterValue(filters.status?.value);
    const channel = normalizeFilterValue(filters.channel?.value);
    const payment = normalizeFilterValue(filters.payment?.value);
    const date = filters.date?.value || '';

    return comenzi.filter((order) => {
      const text = `${order.order_number || ''} ${order.client_name || ''} ${order.phone || ''} ${order.vin || ''} ${order.product_name || ''}`.toLowerCase();
      const orderStatus = normalizeFilterValue(order.order_status);
      const orderChannel = normalizeFilterValue(order.channel);
      const paymentStatus = normalizeFilterValue(order.payment_status);
      const createdDate = String(order.created_at || '').slice(0, 10);
      return (!query || text.includes(query))
        && (!status || orderStatus === status)
        && (!channel || orderChannel === channel)
        && (!payment || paymentStatus === payment)
        && (!date || createdDate === date);
    });
  }

  function excelCell(value) {
    return escapeHtml(value).replace(/\n/g, '<br>');
  }

  function exportFilteredOrdersToExcel() {
    const rows = filteredOrders();
    if (rows.length === 0) {
      showToast('Nu exista comenzi pentru export.', true);
      return;
    }

    const headers = [
      'Comanda',
      'Client',
      'Telefon',
      'Email',
      'VIN',
      'Produs',
      'Canal',
      'Plata',
      'Livrare',
      'Status livrare',
      'Status comanda',
      'Cantitate',
      'Total',
      'Creat la',
    ];

    const bodyRows = rows.map((order) => [
      order.order_number || `ORD-${order.randomn_id}`,
      order.client_name || '',
      order.phone || '',
      order.email || '',
      order.vin || '',
      order.product_name || '',
      order.channel || '',
      order.payment_status || '',
      order.delivery_method || '',
      order.delivery_status || '',
      order.order_status || '',
      order.quantity || 1,
      order.total_amount || '0.00',
      order.created_at || '',
    ]);

    const tableHtml = `
      <html>
        <head>
          <meta charset="utf-8">
        </head>
        <body>
          <table border="1">
            <thead>
              <tr>${headers.map((header) => `<th>${excelCell(header)}</th>`).join('')}</tr>
            </thead>
            <tbody>
              ${bodyRows.map((row) => `<tr>${row.map((cell) => `<td>${excelCell(cell)}</td>`).join('')}</tr>`).join('')}
            </tbody>
          </table>
        </body>
      </html>
    `;

    const blob = new Blob([tableHtml], { type: 'application/vnd.ms-excel;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    const today = new Date().toISOString().slice(0, 10);
    link.href = url;
    link.download = `comenzi-export-${today}.xls`;
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
    showToast('Export Excel generat.', false);
  }

  function badgeClass(value, type) {
    const v = String(value || '').toLowerCase();
    if (type === 'payment') {
      if (['confirmata', 'card_online', 'numerar'].includes(v)) return 'besoiu-badge besoiu-badge--payment';
      if (['esuata'].includes(v)) return 'besoiu-badge besoiu-badge--danger';
      return 'besoiu-badge besoiu-badge--info';
    }
    if (['finalizata', 'platita', 'expediata'].includes(v)) return 'besoiu-badge besoiu-badge--success';
    if (['anulata', 'retur'].includes(v)) return 'besoiu-badge besoiu-badge--danger';
    if (['in_lucru', 'noua'].includes(v)) return 'besoiu-badge besoiu-badge--warning';
    return 'besoiu-badge besoiu-badge--info';
  }

  function listPayload() {
    return {
      page: currentPage,
      per_page: 10,
      q: (filters.search?.value || '').trim(),
      order_status: filters.status?.value || '',
      channel: filters.channel?.value || '',
      payment_status: filters.payment?.value || '',
    };
  }

  function renderOrderItemsCell(order) {
    const lines = Array.isArray(order.order_items) && order.order_items.length
      ? order.order_items
      : [];

    if (lines.length === 0) {
      return `
        <div class="flex items-center gap-3">
          ${order.product_image ? `<img src="${escapeHtml(order.product_image)}" alt="${escapeHtml(order.product_name)}" class="h-12 w-12 rounded-md object-cover">` : '<div class="flex h-12 w-12 items-center justify-center rounded-md bg-foreground/5 text-xs opacity-50">img</div>'}
          <div>
            <div class="font-medium">${escapeHtml(order.product_name)}</div>
            <div class="mt-0.5 text-xs opacity-70">${escapeHtml(order.quantity || 1)} buc.</div>
            <div class="mt-0.5 text-xs font-medium">${escapeHtml(order.total_amount || '0.00')} RON</div>
          </div>
        </div>
      `;
    }

    const preview = lines.slice(0, 3).map((line) => `
      <div class="flex items-center gap-2 py-1">
        ${line.product_image ? `<img src="${escapeHtml(line.product_image)}" alt="${escapeHtml(line.product_name)}" class="h-10 w-10 rounded-md object-cover">` : '<div class="flex h-10 w-10 items-center justify-center rounded-md bg-foreground/5 text-[10px] opacity-50">img</div>'}
        <div class="min-w-0">
          <div class="truncate font-medium">${escapeHtml(line.product_name)}</div>
          <div class="text-xs opacity-70">${escapeHtml(line.quantity || 1)} buc. x ${Number(line.unit_price || 0).toFixed(2)} RON</div>
          ${line.oem_code ? `<div class="text-xs opacity-60">OEM: ${escapeHtml(line.oem_code)}</div>` : ''}
        </div>
      </div>
    `).join('');

    const extra = lines.length > 3
      ? `<div class="text-xs opacity-60">+ inca ${lines.length - 3} produse</div>`
      : '';

    return `
      <div>
        ${preview}
        ${extra}
        <div class="mt-1 text-xs font-medium">${escapeHtml(order.total_amount || '0.00')} RON total</div>
      </div>
    `;
  }

  function renderFulfillmentCell(order) {
    const fulfillment = order.fulfillment || {};
    const invoice = fulfillment.invoice || null;
    const delivery = fulfillment.delivery || null;
    const parts = [];

    if (invoice) {
      parts.push(`<a class="besoiu-action-btn besoiu-action-btn--primary text-xs!" href="/admin/facturi?highlight=${encodeURIComponent(invoice.randomn_id || '')}">${escapeHtml(invoice.invoice_number || ('INV-' + invoice.randomn_id))}</a>`);
      parts.push(`<span class="besoiu-badge besoiu-badge--info">${escapeHtml(invoice.invoice_status || 'neachitată')}</span>`);
    } else {
      parts.push('<span class="text-xs font-semibold text-[var(--b26-muted)]">Fără factură</span>');
    }

    if (delivery) {
      parts.push(`<a class="besoiu-action-btn text-xs!" href="/admin/livrare?highlight=${encodeURIComponent(delivery.randomn_id || '')}">${escapeHtml(delivery.awb || ('AWB-' + delivery.randomn_id))}</a>`);
      parts.push(`<span class="besoiu-badge besoiu-badge--success">${escapeHtml(delivery.delivery_status || 'pregătire')}</span>`);
    } else {
      parts.push('<span class="text-xs font-semibold text-[var(--b26-muted)]">Fără AWB</span>');
    }

    return `<div class="space-y-1">${parts.join('<br>')}</div>`;
  }

  function renderOrders() {
    if (!tableBody) return;
    updateComenziKpi();

    if (comenzi.length === 0) {
      tableBody.innerHTML = '<tr><td colspan="9" class="besoiu-empty">Nu există comenzi pentru filtrele selectate.</td></tr>';
      if (window.BpaPagination) BpaPagination.render(paginationEl, listMeta, (p) => loadOrders(p));
      return;
    }

    tableBody.innerHTML = comenzi.map((order) => `
      <tr data-comenzi-row data-id="${escapeHtml(order.randomn_id)}">
        <td>
          <div class="font-bold">#${escapeHtml(order.order_number || ('ORD-' + order.randomn_id))}</div>
          <div class="mt-1 text-xs font-semibold text-[var(--b26-muted)]">${escapeHtml(order.created_at || '')}</div>
        </td>
        <td>
          <div class="font-bold">${escapeHtml(order.client_name || '—')}</div>
          <div class="mt-1 text-xs font-semibold text-[var(--b26-muted)]">${escapeHtml(order.phone || '—')}</div>
          <div class="mt-0.5 text-xs text-[var(--b26-muted)]">VIN: ${escapeHtml(order.vin || '—')}</div>
        </td>
        <td>${renderOrderItemsCell(order)}</td>
        <td>
          <div class="flex items-center gap-2 font-semibold">
            <i data-lucide="radio" class="size-4 text-[var(--b26-emerald-mid)]"></i>
            ${escapeHtml(order.channel || 'manual')}
          </div>
        </td>
        <td class="text-center">
          <span class="${badgeClass(order.payment_status, 'payment')}">${escapeHtml(order.payment_status || 'ramburs')}</span>
        </td>
        <td class="text-center">
          <div class="text-sm font-bold">${escapeHtml(order.delivery_method || '—')}</div>
          <div class="mt-1 text-xs font-semibold text-[var(--b26-muted)]">${escapeHtml(order.delivery_status || '—')}</div>
        </td>
        <td class="text-center">${renderFulfillmentCell(order)}</td>
        <td class="text-center">
          <span class="${badgeClass(order.order_status)}">${escapeHtml(order.order_status || 'noua')}</span>
        </td>
        <td>
          <div class="besoiu-actions">
            <button type="button" data-action="edit" data-order='${escapeHtml(JSON.stringify(order))}' class="besoiu-action-btn besoiu-action-btn--primary"><i data-lucide="pencil"></i>Edit</button>
            <button type="button" data-action="create-invoice" data-id="${escapeHtml(order.randomn_id)}" class="besoiu-action-btn"><i data-lucide="file-text"></i>Factură</button>
            <button type="button" data-action="create-delivery" data-id="${escapeHtml(order.randomn_id)}" class="besoiu-action-btn"><i data-lucide="truck"></i>AWB</button>
            <button type="button" data-action="setstatus" data-id="${escapeHtml(order.randomn_id)}" data-status="finalizata" class="besoiu-action-btn besoiu-action-btn--success"><i data-lucide="check"></i>Final</button>
            <button type="button" data-action="delete" data-id="${escapeHtml(order.randomn_id)}" class="besoiu-action-btn besoiu-action-btn--danger"><i data-lucide="trash-2"></i>Șterge</button>
          </div>
        </td>
      </tr>
    `).join('');

    if (window.lucide) window.lucide.createIcons();
    if (window.BpaPagination) BpaPagination.render(paginationEl, listMeta, (p) => loadOrders(p));
  }

  async function loadOrders(page) {
    if (page) currentPage = page;
    const data = await apiCall('list', listPayload());
    const parsed = window.BpaPagination ? BpaPagination.unwrapList(data) : { items: data, total: data.length, page: 1, per_page: 10, total_pages: 1 };
    comenzi = parsed.items;
    listMeta = parsed;
    currentPage = parsed.page;
    renderOrders();
  }

  document.getElementById('comenzi-open-create')?.addEventListener('click', () => openModal(null));
  document.getElementById('comenzi-export-excel')?.addEventListener('click', exportFilteredOrdersToExcel);
  document.getElementById('comenzi-refresh')?.addEventListener('click', () => loadOrders().catch((error) => showToast(error.message, true)));
  document.getElementById('comenzi-close-modal')?.addEventListener('click', closeModal);
  document.getElementById('comenzi-cancel')?.addEventListener('click', closeModal);
  Object.values(filters).forEach((filter) => filter?.addEventListener('input', () => { currentPage = 1; loadOrders().catch((e) => showToast(e.message, true)); }));
  Object.values(filters).forEach((filter) => filter?.addEventListener('change', () => { currentPage = 1; loadOrders().catch((e) => showToast(e.message, true)); }));

  form?.addEventListener('submit', async (event) => {
    event.preventDefault();
    try {
      await apiCall(form.dataset.action || 'add', formToObject(form));
      closeModal();
      showToast('Comanda salvata.', false);
      await loadOrders();
    } catch (error) {
      showToast(error.message, true);
    }
  });

  tableBody?.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-action]');
    if (!button) return;

    try {
      if (button.dataset.action === 'edit') {
        openModal(JSON.parse(button.dataset.order || '{}'));
        return;
      }

      if (button.dataset.action === 'delete') {
        if (!confirm('Confirmi stergerea comenzii?')) return;
        await apiCall('delete', { randomn_id: Number(button.dataset.id) });
        showToast('Comanda stearsa.', false);
        await loadOrders();
        return;
      }

      if (button.dataset.action === 'setstatus') {
        await apiCall('setstatus', { randomn_id: Number(button.dataset.id), order_status: button.dataset.status });
        showToast('Status actualizat.', false);
        await loadOrders();
        return;
      }

      if (button.dataset.action === 'create-invoice') {
        const result = await apiCall('create_invoice', { randomn_id: Number(button.dataset.id) });
        showToast(result?.message || 'Factura generata.', false);
        await loadOrders();
        return;
      }

      if (button.dataset.action === 'create-delivery') {
        const result = await apiCall('create_delivery', { randomn_id: Number(button.dataset.id) });
        showToast(result?.message || 'Livrare creata.', false);
        await loadOrders();
      }
    } catch (error) {
      showToast(error.message, true);
    }
  });

  function bootOrdersPage() {
    loadOrders().catch((error) => showToast(error.message, true));
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootOrdersPage);
  } else {
    bootOrdersPage();
  }
})();
</script>

<script>
(function () {
  'use strict';

  const bootstrapEl = document.getElementById('legacy-comenzi-bootstrap');
  let pageBootstrap = { ready: false, error: '', endpoint: '/admin/api/caiet_comenzi_endpoint.php', tabs: {} };
  if (bootstrapEl) {
    try {
      pageBootstrap = JSON.parse(bootstrapEl.textContent || '{}');
    } catch (error) {
      pageBootstrap.error = 'Datele initiale legacy nu pot fi citite.';
    }
  }

  const ENDPOINT = pageBootstrap.endpoint || '/admin/api/caiet_comenzi_endpoint.php';
  const STATUS_LABELS = {1:'Comandat',2:'Sosit',3:'Cash',4:'Avans',5:'Retur',6:'Card',7:'FD',8:'Anulat',9:'Facturat',10:'Pregatire',11:'Livrare',12:'In verificare'};
  const LEGACY_TABS = {
    tm: { title: 'Comenzi Timisoara', location: 'Timisoara', source_type: 'interna' },
    utvin: { title: 'Comenzi Utvin', location: 'Utvin', source_type: 'interna' },
    ext: { title: 'Comenzi externe', location: 'externa', source_type: 'externa' },
  };
  const legacyControllers = {};

  function encodeOrderAttr(order) {
    return encodeURIComponent(JSON.stringify(order));
  }

  function decodeOrderAttr(raw) {
    return JSON.parse(decodeURIComponent(String(raw || '')));
  }

  const tabButtons = Array.from(document.querySelectorAll('.comenzi-tab-btn[data-action="comenzi-tab-switch"]'));
  const tabButtonByKey = {
    standard: document.getElementById('comenzi-tab-btn-standard'),
    tm: document.getElementById('comenzi-tab-btn-tm'),
    utvin: document.getElementById('comenzi-tab-btn-utvin'),
    ext: document.getElementById('comenzi-tab-btn-ext'),
  };
  const panels = {
    standard: document.getElementById('comenzi-tab-standard'),
    tm: document.getElementById('comenzi-tab-tm'),
    utvin: document.getElementById('comenzi-tab-utvin'),
    ext: document.getElementById('comenzi-tab-ext'),
  };

  function getLegacyPanelRoot(tabKey) {
    return document.getElementById('legacy-panel-' + tabKey)
      || panels[tabKey]?.querySelector('[data-legacy-panel="' + tabKey + '"]')
      || panels[tabKey];
  }

  const toast = document.getElementById('legacy-comenzi-toast');
  const modal = document.getElementById('legacy-comenzi-modal');
  const linesBody = document.getElementById('legacy-comenzi-lines-body');
  let selectedOrder = null;

  function escapeHtml(v) {
    return String(v ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
  }

  function showToast(msg, isErr) {
    if (!toast) return;
    toast.textContent = msg;
    toast.classList.remove('hidden', 'besoiu-toast--error');
    if (isErr) toast.classList.add('besoiu-toast--error');
    setTimeout(() => toast.classList.add('hidden'), 4000);
  }

  function activateTab(tab) {
    tabButtons.forEach(btn => {
      const active = btn.dataset.tab === tab;
      btn.classList.toggle('besoiu-tabs__btn--active', active);
      btn.setAttribute('aria-selected', active ? 'true' : 'false');
      if (active) {
        btn.setAttribute('data-active', '1');
      } else {
        btn.removeAttribute('data-active');
      }
    });
    Object.entries(panels).forEach(([key, panel]) => {
      if (!panel) return;
      const visible = key === tab;
      panel.classList.toggle('hidden', !visible);
      panel.classList.toggle('comenzi-tab-panel--active', visible);
      panel.setAttribute('aria-hidden', visible ? 'false' : 'true');
    });
  }

  async function apiCall(actionType, payload) {
    const response = await fetch(ENDPOINT, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({type_product: actionType, ...payload}),
    });
    const rawText = await response.text();
    let result;
    try {
      result = JSON.parse(rawText);
    } catch (error) {
      throw new Error('API caiet comenzi: raspuns invalid (verificati LEGACY_DB_NAME).');
    }
    if (!response.ok || !result.success) {
      throw new Error(result.message || 'Eroare la incarcare.');
    }
    return result.data;
  }

  function badgeClass(statusCode) {
    if ([3, 6, 9].includes(Number(statusCode))) return 'besoiu-badge besoiu-badge--success';
    if ([5, 8].includes(Number(statusCode))) return 'besoiu-badge besoiu-badge--danger';
    return 'besoiu-badge besoiu-badge--warning';
  }

  function renderLegacyRows(rows) {
    if (!rows.length) {
      return '<tr><td colspan="9" class="besoiu-empty">Nu exista comenzi.</td></tr>';
    }

    return rows.map(order => {
      const encoded = encodeOrderAttr(order);
      return `
        <tr class="legacy-order-row" data-order-id="${escapeHtml(order.order_id)}">
          <td>
            <button type="button" class="legacy-expand besoiu-action-btn" data-order="${encoded}">+</button>
          </td>
          <td><div class="font-bold">#${escapeHtml(order.order_id)}</div><div class="mt-1 text-xs font-semibold text-[var(--b26-muted)]">${escapeHtml(order.order_date || '')}</div></td>
          <td><div class="font-bold">${escapeHtml(order.client_name || '-')}</div><div class="mt-1 text-xs font-semibold text-[var(--b26-muted)]">${escapeHtml(order.client_phone || '')}</div></td>
          <td>${escapeHtml(order.marca || '-')}</td>
          <td class="text-center">${escapeHtml(order.lines_count || 0)}</td>
          <td class="text-right font-bold">${Number(order.total_amount || 0).toFixed(2)} RON</td>
          <td class="text-center"><span class="${badgeClass(order.status)}">${escapeHtml(order.status_label || STATUS_LABELS[order.status] || order.status)}</span></td>
          <td>${escapeHtml(order.observations || '-')}</td>
          <td>
            <div class="besoiu-actions">
              <a class="besoiu-action-btn besoiu-action-btn--primary" href="/admin/order-edit?id=${encodeURIComponent(order.order_id)}&source=${encodeURIComponent(order.source_type || 'interna')}">Edit</a>
              <button type="button" class="besoiu-action-btn" data-action="legacy-details" data-order="${encoded}">Detalii</button>
            </div>
          </td>
        </tr>
        <tr class="legacy-expand-row hidden" data-expand-for="${escapeHtml(order.order_id)}"></tr>`;
    }).join('');
  }

  function applyStats(tabKey, stats) {
    if (!stats) return;
    const monthEl = document.getElementById('legacy-stat-month-' + tabKey);
    const dayEl = document.getElementById('legacy-stat-day-' + tabKey);
    const revenueEl = document.getElementById('legacy-stat-revenue-' + tabKey);
    if (monthEl) monthEl.textContent = stats.total_month || 0;
    if (dayEl) dayEl.textContent = stats.total_day || 0;
    if (revenueEl) revenueEl.textContent = `${Number(stats.revenue_month || 0).toFixed(2)} RON`;
  }

  function wireLegacyPanel(tabKey, config) {
    const panel = getLegacyPanelRoot(tabKey);
    if (!panel || panel.dataset.legacyWired === '1') {
      return legacyControllers[tabKey] || null;
    }

    panel.dataset.legacyWired = '1';

    const search = document.getElementById('legacy-search-' + tabKey);
    const status = document.getElementById('legacy-status-' + tabKey);
    const dateFrom = document.getElementById('legacy-date-from-' + tabKey);
    const dateTo = document.getElementById('legacy-date-to-' + tabKey);
    const refresh = document.getElementById('legacy-refresh-' + tabKey);
    const body = document.getElementById('legacy-body-' + tabKey);
    const expandedCache = new Map();

    async function toggleExpand(order, expandRow, expandBtn) {
      const cacheKey = order.order_id + ':' + order.source_type;
      if (expandRow.classList.contains('hidden')) {
        if (!expandedCache.has(cacheKey)) {
          const details = await apiCall('details', { order_id: Number(order.order_id), source_type: order.source_type });
          expandedCache.set(cacheKey, details);
        }
        const details = expandedCache.get(cacheKey);
        const linesHtml = (details.lines || []).map(line => `
          <tr class="border-b border-dashed">
            <td class="px-2 py-1 opacity-50">${escapeHtml(line.iddetaliu)}</td>
            <td class="px-2 py-1">${escapeHtml(line.idprodus)}</td>
            <td class="px-2 py-1 text-center">${escapeHtml(line.cantitate)}</td>
            <td class="px-2 py-1 text-right">${Number(line.pret || 0).toFixed(2)} RON</td>
            <td class="px-2 py-1">${escapeHtml(line.furnizor || '-')}</td>
            <td class="px-2 py-1">${escapeHtml(line.culoare || '-')}</td>
          </tr>
        `).join('');
        expandRow.innerHTML = `
          <td colspan="9" class="bg-foreground/5 px-4 py-3">
            <div class="text-xs font-medium uppercase opacity-60 mb-2">Linii detaliu</div>
            <table class="w-full text-xs">
              <thead><tr><th class="px-2 py-1 text-left">ID</th><th class="px-2 py-1 text-left">Produs</th><th class="px-2 py-1 text-center">Qty</th><th class="px-2 py-1 text-right">Preț</th><th class="px-2 py-1 text-left">Furnizor</th><th class="px-2 py-1 text-left">Culoare</th></tr></thead>
              <tbody>${linesHtml || '<tr><td colspan="6" class="py-2 opacity-60">Fara linii.</td></tr>'}</tbody>
            </table>
            <div class="mt-2 text-right text-xs opacity-70">Total calculat: ${Number(details.calculated_total || 0).toFixed(2)} RON</div>
          </td>`;
        expandRow.classList.remove('hidden');
        if (expandBtn) expandBtn.textContent = '−';
      } else {
        expandRow.classList.add('hidden');
        if (expandBtn) expandBtn.textContent = '+';
      }
    }

    async function loadRows() {
      if (!body) return;
      body.innerHTML = '<tr><td colspan="9" class="besoiu-empty">Se incarca...</td></tr>';
      try {
        const payload = {
          location: config.location,
          source_type: config.source_type,
          limit: 300,
        };
        if (search?.value?.trim()) payload.search = search.value.trim();
        if (status?.value) payload.status = status.value;
        if (dateFrom?.value) payload.date_from = dateFrom.value;
        if (dateTo?.value) payload.date_to = dateTo.value;

        const rows = await apiCall('list', payload);
        body.innerHTML = renderLegacyRows(Array.isArray(rows) ? rows : []);
        if (window.lucide) window.lucide.createIcons();

        const stats = await apiCall('stats_location', { location: config.location });
        applyStats(tabKey, stats);
      } catch (error) {
        body.innerHTML = `<tr><td colspan="9" class="besoiu-empty besoiu-toast--error">${escapeHtml(error.message)}</td></tr>`;
        showToast(error.message, true);
      }
    }

    refresh?.addEventListener('click', () => loadRows());
    search?.addEventListener('input', () => {
      clearTimeout(search._timer);
      search._timer = setTimeout(() => loadRows(), 350);
    });
    status?.addEventListener('change', () => loadRows());
    dateFrom?.addEventListener('change', () => loadRows());
    dateTo?.addEventListener('change', () => loadRows());

    body?.addEventListener('click', async (event) => {
      const expandBtn = event.target.closest('.legacy-expand');
      if (expandBtn) {
        try {
          const order = decodeOrderAttr(expandBtn.dataset.order || '');
          const expandRow = body.querySelector(`tr[data-expand-for="${order.order_id}"]`);
          if (expandRow) {
            await toggleExpand(order, expandRow, expandBtn);
          }
        } catch (error) {
          showToast(error.message, true);
        }
        return;
      }

      const btn = event.target.closest('[data-action="legacy-details"]');
      if (!btn) return;
      try {
        const order = decodeOrderAttr(btn.dataset.order || '');
        const details = await apiCall('details', { order_id: Number(order.order_id), source_type: order.source_type });
        openLegacyModal(order, details);
      } catch (error) {
        showToast(error.message, true);
      }
    });

    const controller = { reload: loadRows, loaded: false };
    legacyControllers[tabKey] = controller;
    return controller;
  }

  function buildLegacyPanel(tabKey, config) {
    return wireLegacyPanel(tabKey, config);
  }

  function ensureLegacyTab(tabKey) {
    const config = LEGACY_TABS[tabKey];
    if (!config) return;
    if (!legacyControllers[tabKey]) {
      legacyControllers[tabKey] = buildLegacyPanel(tabKey, config);
    }
    const controller = legacyControllers[tabKey];
    if (controller && !controller.loaded) {
      controller.loaded = true;
      controller.reload();
    }
  }

  function reloadActiveLegacyTab() {
    const activeBtn = tabButtons.find(btn =>
      btn.dataset.active === '1'
      || btn.classList.contains('besoiu-tabs__btn--active')
    );
    const tab = activeBtn?.dataset?.tab;
    if (tab && legacyControllers[tab]?.reload) {
      legacyControllers[tab].reload();
    }
  }

  function bindLegacyTabs() {
    if (!tabButtons.length) {
      return;
    }

    Object.entries(tabButtonByKey).forEach(([tabKey, btn]) => {
      if (!btn) return;
      btn.addEventListener('click', () => {
        activateTab(tabKey);
        if (tabKey === 'tm' || tabKey === 'utvin' || tabKey === 'ext') {
          ensureLegacyTab(tabKey);
        }
      });
    });

    const initialTab = new URLSearchParams(window.location.search).get('legacy_tab');
    if (initialTab && panels[initialTab]) {
      activateTab(initialTab);
      if (initialTab === 'tm' || initialTab === 'utvin' || initialTab === 'ext') {
        ensureLegacyTab(initialTab);
      }
    }

    if (!pageBootstrap.ready && pageBootstrap.error) {
      showToast(pageBootstrap.error, true);
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bindLegacyTabs);
  } else {
    bindLegacyTabs();
  }

  function openLegacyModal(order, details) {
    selectedOrder = order;
    document.getElementById('legacy-comenzi-modal-order-id').textContent = '#' + order.order_id;
    document.getElementById('legacy-comenzi-modal-source').textContent = order.source_type;
    document.getElementById('legacy-comenzi-modal-status').textContent = order.status_label || STATUS_LABELS[order.status] || ('Status ' + order.status);
    document.getElementById('legacy-comenzi-modal-total').textContent = Number(details.calculated_total || 0).toFixed(2) + ' RON';
    document.getElementById('legacy-comenzi-new-status').value = String(order.status || 1);
    linesBody.innerHTML = (details.lines || []).map(line => `
      <tr>
        <td>${escapeHtml(line.iddetaliu)}</td>
        <td>${escapeHtml(line.idprodus)}</td>
        <td class="text-center">${escapeHtml(line.cantitate)}</td>
        <td class="text-right">${Number(line.pret || 0).toFixed(2)} RON</td>
        <td>${escapeHtml(line.furnizor || '-')}</td>
        <td>${escapeHtml(line.culoare || '-')}</td>
      </tr>
    `).join('');
    modal.classList.remove('hidden');
    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('comenzi-legacy-modal-open');
  }

  function closeLegacyModal() {
    if (!modal) return;
    modal.classList.add('hidden');
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('comenzi-legacy-modal-open');
    selectedOrder = null;
    if (linesBody) linesBody.innerHTML = '';
  }

  document.getElementById('legacy-comenzi-close-modal')?.addEventListener('click', closeLegacyModal);

  document.getElementById('legacy-comenzi-save-status')?.addEventListener('click', async () => {
    if (!selectedOrder) return;
    try {
      const newStatus = Number(document.getElementById('legacy-comenzi-new-status').value);
      await apiCall('setstatus', {
        order_id: Number(selectedOrder.order_id),
        source_type: selectedOrder.source_type,
        new_status: newStatus
      });
      showToast('Status actualizat cu succes.', false);
      closeLegacyModal();
      reloadActiveLegacyTab();
    } catch (error) {
      showToast(error.message, true);
    }
  });
})();
</script>
