<?php
declare(strict_types=1);

use Besoiu\Core\AdminUrl;
use Besoiu\Services\Products\ProduseService;

$projectRoot = dirname(__DIR__, 5);
require_once $projectRoot . '/modules/produse/pages/views/_produse-list-helpers.php';

$service = new ProduseService();
$produseSectionActive = 'caiet';
$produseNavVitrinaCount = $service->countVitrinaProducts();

$configPath = $projectRoot . '/app/Config/config.php';
$config = is_file($configPath) ? (require $configPath) : [];
$legacyDbConfigured = trim((string) ($config['legacy_db_name'] ?? '')) !== '';
?>
<link rel="stylesheet" href="<?= produse_list_h(AdminUrl::fontAwesomeCssUrl()) ?>" crossorigin="anonymous" referrerpolicy="no-referrer">
<link rel="stylesheet" href="<?= produse_list_h(AdminUrl::publicAsset('css/admin-produse-list.css')) ?>?v=20260718-layout-v4">

<div class="-mt-5 admin-content produse-list-page produse-section-page" data-page-title="Caiet comenzi — produse ERP">
<div class="admin-panel">
    <div class="admin-panel__head">
        <div class="pl-head-main">
            <span class="pl-head-icon" aria-hidden="true"><i class="fa-solid fa-clipboard-list"></i></span>
            <h2 class="mt-0 text-lg font-medium">Caiet comenzi</h2>
        </div>
        <span class="pl-head-meta"><span class="pl-head-meta__dot" aria-hidden="true"></span><i class="fa-solid fa-database"></i> Produse ERP</span>
    </div>
    <?php require $projectRoot . '/modules/produse/pages/views/_produse-section-nav.php'; ?>

<div class="besoiu-page besoiu-produse-page besoiu-caiet-produse-page">
    <div class="besoiu-dash-hero">
        <div>
            <p class="besoiu-dash-hero__meta mt-0">
                Catalog produse din baza legacy TM/Utvin — denumire, cod, preț, TVA. Separat de magazinul online Besoiu.
            </p>
        </div>
        <div class="besoiu-dash-hero__actions" style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;">
            <a href="/admin/dashboard?section=core_comenzi" class="besoiu-btn-secondary inline-flex items-center gap-2">
                <i class="fa-solid fa-book-open"></i>
                Dashboard comenzi
            </a>
            <button id="legacy-produse-new" class="besoiu-btn-primary inline-flex items-center gap-2" type="button"<?= $legacyDbConfigured ? '' : ' disabled' ?>>
                <i class="fa-solid fa-plus"></i>
                Produs nou ERP
            </button>
            <button id="legacy-produse-tva" class="besoiu-btn-secondary inline-flex items-center gap-2" type="button"<?= $legacyDbConfigured ? '' : ' disabled' ?>>
                <i class="fa-solid fa-percent"></i>
                Actualizează TVA
            </button>
        </div>
    </div>

    <?php if (!$legacyDbConfigured): ?>
        <div class="besoiu-produse-panel mt-4">
            <strong>LEGACY_DB_NAME</strong> nu este configurat. Completează conexiunea la baza Caiet comenzi în <code>.env</code> ca să încarci produsele ERP.
        </div>
    <?php else: ?>
        <div class="besoiu-produse-panel mt-4">
            <div class="besoiu-produse-panel__toolbar">
                <div class="besoiu-toolbar besoiu-toolbar--inline" style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;">
                    <input id="legacy-produse-search" class="box h-10 w-full max-w-md rounded-md border bg-background px-3 text-sm" type="search" minlength="2" autocomplete="off" placeholder="Caută după cod sau începutul denumirii…">
                    <button id="legacy-produse-refresh" class="besoiu-btn-secondary inline-flex h-10 items-center gap-2 px-4" type="button">
                        <i class="fa-solid fa-rotate"></i>
                        Reîncarcă
                    </button>
                </div>
                <p class="mt-2 text-xs" style="color:var(--pl-muted,#5b7c78);">Căutarea începe de la 2 caractere și folosește potrivire exactă sau prefix.</p>
            </div>

            <div class="overflow-auto" style="border-radius:14px;border:1px solid var(--pl-line,rgba(15,118,110,.14));background:#fff;">
                <table class="besoiu-vitrina-table besoiu-data-table w-full text-sm">
                    <thead>
                    <tr>
                        <th class="px-3 py-2 text-left">ID</th>
                        <th class="px-3 py-2 text-left">Denumire</th>
                        <th class="px-3 py-2 text-left">Cod produs</th>
                        <th class="px-3 py-2 text-right">Preț</th>
                        <th class="px-3 py-2 text-center">TVA</th>
                        <th class="px-3 py-2 text-center">UM</th>
                        <th class="px-3 py-2 text-center">Acțiune</th>
                    </tr>
                    </thead>
                    <tbody id="legacy-produse-body">
                    <tr><td colspan="7" class="px-3 py-6 text-center" style="color:var(--pl-muted,#5b7c78);">Se încarcă produsele ERP…</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="mt-3 flex items-center justify-between gap-3">
                <span id="legacy-produse-page-meta" class="text-xs" style="color:var(--pl-muted,#5b7c78);" aria-live="polite"></span>
                <button id="legacy-produse-load-more" class="besoiu-btn-secondary hidden px-4 py-2 text-sm" type="button">
                    Încarcă următoarele
                </button>
            </div>
        </div>
    <?php endif; ?>

    <div id="legacy-produse-modal" class="hidden fixed inset-0 z-[99999] p-4" style="background:rgba(15,118,110,.35);">
        <div class="mx-auto w-full max-w-2xl rounded-2xl bg-white shadow-xl" style="border:1px solid rgba(20,184,166,.25);">
            <div class="flex items-center border-b p-5" style="border-color:rgba(15,118,110,.12);">
                <h3 id="legacy-produse-modal-title" class="text-base font-medium">Produs nou</h3>
                <button id="legacy-produse-close" class="ml-auto rounded-xl border px-3 py-2 text-sm" type="button">Închide</button>
            </div>
            <form id="legacy-produse-form" class="p-5">
                <input type="hidden" name="idprodus" id="legacy-produse-id">
                <div class="grid grid-cols-12 gap-3">
                    <label class="col-span-12 md:col-span-8">
                        <span class="mb-1 block text-sm">Denumire</span>
                        <input class="box h-10 w-full rounded-md border bg-white px-3" type="text" name="denumire" required>
                    </label>
                    <label class="col-span-12 md:col-span-4">
                        <span class="mb-1 block text-sm">Cod produs</span>
                        <input class="box h-10 w-full rounded-md border bg-white px-3" type="text" name="cod_produs">
                    </label>
                    <label class="col-span-12 md:col-span-4">
                        <span class="mb-1 block text-sm">Preț</span>
                        <input class="box h-10 w-full rounded-md border bg-white px-3" type="number" step="0.01" min="0" name="pret" value="0">
                    </label>
                    <label class="col-span-12 md:col-span-4">
                        <span class="mb-1 block text-sm">TVA</span>
                        <input class="box h-10 w-full rounded-md border bg-white px-3" type="text" name="TVA">
                    </label>
                    <label class="col-span-12 md:col-span-4">
                        <span class="mb-1 block text-sm">UM</span>
                        <input class="box h-10 w-full rounded-md border bg-white px-3" type="text" name="um">
                    </label>
                </div>
                <div class="mt-4 flex justify-end gap-2 border-t pt-4" style="border-color:rgba(15,118,110,.12);">
                    <button id="legacy-produse-cancel" class="besoiu-btn-secondary rounded-xl px-3 py-2" type="button">Anulează</button>
                    <button class="besoiu-btn-primary rounded-xl px-3 py-2" type="submit">Salvează</button>
                </div>
            </form>
        </div>
    </div>

    <div id="legacy-produse-tva-modal" class="hidden fixed inset-0 z-[99999] p-4" style="background:rgba(15,118,110,.35);">
        <div class="mx-auto w-full max-w-md rounded-2xl bg-white shadow-xl" style="border:1px solid rgba(20,184,166,.25);">
            <div class="flex items-center border-b p-5" style="border-color:rgba(15,118,110,.12);">
                <h3 class="text-base font-medium">Actualizare TVA</h3>
                <button id="legacy-produse-tva-close" class="ml-auto rounded-xl border px-3 py-2 text-sm" type="button">Închide</button>
            </div>
            <form id="legacy-produse-tva-form" class="p-5">
                <label>
                    <span class="mb-1 block text-sm">TVA nou (%)</span>
                    <input id="legacy-produse-tva-input" class="box h-10 w-full rounded-md border bg-white px-3" type="number" step="0.01" min="0" value="19">
                </label>
                <div class="mt-4 flex justify-end gap-2 border-t pt-4" style="border-color:rgba(15,118,110,.12);">
                    <button class="besoiu-btn-secondary rounded-xl px-3 py-2" id="legacy-produse-tva-cancel" type="button">Anulează</button>
                    <button class="besoiu-btn-primary rounded-xl px-3 py-2" type="submit">Aplică</button>
                </div>
            </form>
        </div>
    </div>
</div>
</div>
</div>

<?php if ($legacyDbConfigured): ?>
<script>
(function () {
  'use strict';
  const ENDPOINT = '/admin/api/caiet_comenzi_endpoint.php';
  const body = document.getElementById('legacy-produse-body');
  const search = document.getElementById('legacy-produse-search');
  const form = document.getElementById('legacy-produse-form');
  const modal = document.getElementById('legacy-produse-modal');
  const modalTitle = document.getElementById('legacy-produse-modal-title');
  const tvaModal = document.getElementById('legacy-produse-tva-modal');
  const tvaForm = document.getElementById('legacy-produse-tva-form');
  const idInput = document.getElementById('legacy-produse-id');
  const loadMoreButton = document.getElementById('legacy-produse-load-more');
  const pageMeta = document.getElementById('legacy-produse-page-meta');
  const PAGE_SIZE = 25;
  const MIN_SEARCH_LENGTH = 2;
  let rowsCache = [];
  let nextCursor = null;
  let activeRequest = null;
  let debounceTimer = null;

  function escapeHtml(v) {
    return String(v ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
  }

  function showLoadError(message) {
    if (!body) return;
    body.innerHTML = '<tr><td colspan="7" class="px-3 py-6 text-center text-rose-700">' + escapeHtml(message) + '</td></tr>';
  }

  async function apiCall(type, payload = {}, signal = undefined) {
    const response = await fetch(ENDPOINT, {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      credentials: 'same-origin',
      body: JSON.stringify({type_product: type, ...payload}),
      signal
    });
    let result;
    try {
      result = await response.json();
    } catch (error) {
      throw new Error('Răspuns invalid de la server.');
    }
    if (!response.ok || !result.success) {
      throw new Error(result.message || 'Eroare la încărcare.');
    }
    return result.data || [];
  }

  function openModal(row) {
    form.reset();
    if (row) {
      modalTitle.textContent = 'Edit produs ERP';
      idInput.value = row.idprodus || '';
      Object.keys(row).forEach(key => {
        const field = form.elements.namedItem(key);
        if (field) field.value = row[key] ?? '';
      });
    } else {
      modalTitle.textContent = 'Produs nou ERP';
      idInput.value = '';
    }
    modal.classList.remove('hidden');
  }

  function closeModal() {
    modal.classList.add('hidden');
  }

  function renderRows(rows) {
    return rows.map(p => `
      <tr class="border-b">
        <td class="px-3 py-2">${escapeHtml(p.idprodus)}</td>
        <td class="px-3 py-2 font-medium">${escapeHtml(p.denumire)}</td>
        <td class="px-3 py-2">${escapeHtml(p.cod_produs || '-')}</td>
        <td class="px-3 py-2 text-right">${Number(p.pret || 0).toFixed(2)} RON</td>
        <td class="px-3 py-2 text-center">${escapeHtml(p.TVA || '-')}</td>
        <td class="px-3 py-2 text-center">${escapeHtml(p.um || '-')}</td>
        <td class="px-3 py-2 text-center">
          <div class="inline-flex gap-2">
            <button class="rounded border px-2 py-1 text-xs" type="button" data-action="edit" data-id="${escapeHtml(p.idprodus)}">Edit</button>
            <button class="rounded border px-2 py-1 text-xs text-danger" type="button" data-action="delete" data-id="${escapeHtml(p.idprodus)}">Șterge</button>
          </div>
        </td>
      </tr>
    `).join('');
  }

  async function load(append = false) {
    const query = String(search?.value || '').trim();
    if (query !== '' && query.length < MIN_SEARCH_LENGTH) {
      activeRequest?.abort();
      rowsCache = [];
      nextCursor = null;
      body.innerHTML = '<tr><td colspan="7" class="px-3 py-6 text-center">Introdu minimum 2 caractere pentru căutare.</td></tr>';
      loadMoreButton?.classList.add('hidden');
      if (pageMeta) pageMeta.textContent = '';
      return;
    }

    activeRequest?.abort();
    activeRequest = new AbortController();
    const requestCursor = append ? nextCursor : null;
    if (append && !requestCursor) return;

    if (loadMoreButton) loadMoreButton.disabled = true;
    try {
      const page = await apiCall('produse_list', {
        search: query,
        cursor: requestCursor,
        limit: PAGE_SIZE
      }, activeRequest.signal);
      if (page.maintenance_required) {
        rowsCache = [];
        nextCursor = null;
        showLoadError(page.maintenance_message || 'Catalogul ERP necesită optimizarea indexurilor.');
        loadMoreButton?.classList.add('hidden');
        if (pageMeta) pageMeta.textContent = 'Optimizare BD necesară';
        return;
      }
      const rows = Array.isArray(page.items) ? page.items : [];
      rowsCache = append ? rowsCache.concat(rows) : rows;
      nextCursor = page.next_cursor || null;

      if (!rowsCache.length) {
        body.innerHTML = '<tr><td colspan="7" class="px-3 py-6 text-center">Nu există produse în catalogul ERP.</td></tr>';
      } else if (append) {
        body.insertAdjacentHTML('beforeend', renderRows(rows));
      } else {
        body.innerHTML = renderRows(rowsCache);
      }

      loadMoreButton?.classList.toggle('hidden', !page.has_more);
      if (pageMeta) pageMeta.textContent = rowsCache.length + ' produse încărcate';
    } catch (error) {
      if (error?.name === 'AbortError') return;
      showLoadError(error.message || 'Nu am putut încărca produsele ERP.');
    } finally {
      if (loadMoreButton) loadMoreButton.disabled = false;
    }
  }

  form?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const payload = {};
    new FormData(form).forEach((value, key) => {
      if (String(value).trim() !== '') payload[key] = value;
    });
    try {
      await apiCall('produse_save', payload);
      closeModal();
      await load();
    } catch (error) {
      alert(error.message || 'Eroare la salvare produs.');
    }
  });

  body?.addEventListener('click', async (event) => {
    const btn = event.target.closest('[data-action]');
    if (!btn) return;
    const id = Number(btn.dataset.id || 0);
    if (!id) return;

    if (btn.dataset.action === 'edit') {
      const row = rowsCache.find(item => Number(item.idprodus) === id);
      openModal(row || null);
      return;
    }

    if (btn.dataset.action === 'delete') {
      if (!confirm('Sigur doriți să ștergeți acest produs ERP?')) return;
      try {
        await apiCall('produse_delete', {idprodus: id});
        await load();
      } catch (error) {
        alert(error.message || 'Eroare la ștergere produs.');
      }
    }
  });

  tvaForm?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const tvaValue = Number(document.getElementById('legacy-produse-tva-input')?.value || 0);
    try {
      await apiCall('produse_update_tva', {tva: tvaValue});
      tvaModal.classList.add('hidden');
      await load();
    } catch (error) {
      alert(error.message || 'Eroare la actualizare TVA.');
    }
  });

  document.getElementById('legacy-produse-new')?.addEventListener('click', () => openModal(null));
  document.getElementById('legacy-produse-close')?.addEventListener('click', closeModal);
  document.getElementById('legacy-produse-cancel')?.addEventListener('click', closeModal);
  document.getElementById('legacy-produse-tva')?.addEventListener('click', () => tvaModal.classList.remove('hidden'));
  document.getElementById('legacy-produse-tva-close')?.addEventListener('click', () => tvaModal.classList.add('hidden'));
  document.getElementById('legacy-produse-tva-cancel')?.addEventListener('click', () => tvaModal.classList.add('hidden'));

  document.getElementById('legacy-produse-refresh')?.addEventListener('click', () => load(false).catch(console.error));
  loadMoreButton?.addEventListener('click', () => load(true).catch(console.error));
  search?.addEventListener('input', () => {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => load(false).catch(console.error), 400);
  });
  load(false).catch(console.error);
})();
</script>
<?php endif; ?>
