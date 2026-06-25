<?php
declare(strict_types=1);

use Evasystem\Controllers\Produse\ProduseService;

require_once __DIR__ . '/../produse/_produse-list-helpers.php';

$service = new ProduseService();
$produseSectionActive = 'caiet';
$produseNavVitrinaCount = $service->countVitrinaProducts();
$produseNavScraperCount = 0;
$scraperRoot = dirname(__DIR__, 5);
if (is_file($scraperRoot . '/lib/Scraper/EpiesaCatalog.php')) {
    require_once $scraperRoot . '/lib/Scraper/EpiesaCatalog.php';
    $produseNavScraperCount = EpiesaCatalog::productCount();
}

$configPath = dirname(__DIR__, 4) . '/config/config.php';
$config = is_file($configPath) ? (require $configPath) : [];
$legacyDbConfigured = trim((string) ($config['legacy_db_name'] ?? '')) !== '';
?>
<div class="besoiu-page besoiu-produse-page besoiu-caiet-produse-page" data-page-title="Caiet comenzi — produse ERP">
    <h2 class="sr-only">Caiet comenzi — produse ERP</h2>

    <div class="besoiu-dash-hero">
        <div>
            <h1>Caiet comenzi — produse ERP</h1>
            <p class="besoiu-dash-hero__meta mt-2">
                Catalog produse din baza legacy TM/Utvin — denumire, cod, preț, TVA. Separat de magazinul online Besoiu.
            </p>
        </div>
        <div class="besoiu-dash-hero__actions" style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;">
            <a href="/admin/orders?legacy_tab=tm" class="besoiu-btn-secondary inline-flex items-center gap-2">
                <i data-lucide="book-open" class="size-4"></i>
                Comenzi legacy
            </a>
            <button id="legacy-produse-new" class="besoiu-btn-primary inline-flex items-center gap-2" type="button"<?= $legacyDbConfigured ? '' : ' disabled' ?>>
                <i data-lucide="plus" class="size-4"></i>
                Produs nou ERP
            </button>
            <button id="legacy-produse-tva" class="besoiu-btn-secondary inline-flex items-center gap-2" type="button"<?= $legacyDbConfigured ? '' : ' disabled' ?>>
                <i data-lucide="percent" class="size-4"></i>
                Actualizează TVA
            </button>
        </div>
    </div>

    <?php require __DIR__ . '/../produse/_produse-section-nav.php'; ?>

    <?php if (!$legacyDbConfigured): ?>
        <div class="besoiu-produse-panel mt-4 rounded-xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-950">
            <strong>LEGACY_DB_NAME</strong> nu este configurat. Completează conexiunea la baza Caiet comenzi în <code>.env</code> / config admin ca să încarci produsele ERP.
        </div>
    <?php else: ?>
        <div class="besoiu-produse-panel mt-4">
            <div class="besoiu-produse-panel__toolbar">
                <div class="besoiu-toolbar besoiu-toolbar--inline">
                    <input id="legacy-produse-search" class="box h-10 w-full max-w-md rounded-md border bg-background px-3 text-sm" type="search" placeholder="Caută denumire, cod produs…">
                    <button id="legacy-produse-refresh" class="besoiu-btn-secondary inline-flex h-10 items-center gap-2 px-4" type="button">
                        <i data-lucide="refresh-cw" class="size-4"></i>
                        Reîncarcă
                    </button>
                </div>
            </div>

            <div class="overflow-auto rounded-xl border border-slate-200 bg-white">
                <table class="besoiu-data-table w-full text-sm">
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
                    <tr><td colspan="7" class="px-3 py-6 text-center text-slate-500">Se încarcă produsele ERP…</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <div id="legacy-produse-modal" class="hidden fixed inset-0 z-[99999] bg-black/40 p-4">
        <div class="mx-auto w-full max-w-2xl rounded-lg bg-white shadow-xl">
            <div class="flex items-center border-b p-5">
                <h3 id="legacy-produse-modal-title" class="text-base font-medium">Produs nou</h3>
                <button id="legacy-produse-close" class="ml-auto rounded border px-3 py-2 text-sm" type="button">Închide</button>
            </div>
            <form id="legacy-produse-form" class="p-5">
                <input type="hidden" name="idprodus" id="legacy-produse-id">
                <div class="grid grid-cols-12 gap-3">
                    <label class="col-span-12 md:col-span-8">
                        <span class="mb-1 block text-sm">Denumire</span>
                        <input class="box h-10 w-full rounded-md border bg-white px-3 text-slate-900" type="text" name="denumire" required>
                    </label>
                    <label class="col-span-12 md:col-span-4">
                        <span class="mb-1 block text-sm">Cod produs</span>
                        <input class="box h-10 w-full rounded-md border bg-white px-3 text-slate-900" type="text" name="cod_produs">
                    </label>
                    <label class="col-span-12 md:col-span-4">
                        <span class="mb-1 block text-sm">Preț</span>
                        <input class="box h-10 w-full rounded-md border bg-white px-3 text-slate-900" type="number" step="0.01" min="0" name="pret" value="0">
                    </label>
                    <label class="col-span-12 md:col-span-4">
                        <span class="mb-1 block text-sm">TVA</span>
                        <input class="box h-10 w-full rounded-md border bg-white px-3 text-slate-900" type="text" name="TVA">
                    </label>
                    <label class="col-span-12 md:col-span-4">
                        <span class="mb-1 block text-sm">UM</span>
                        <input class="box h-10 w-full rounded-md border bg-white px-3 text-slate-900" type="text" name="um">
                    </label>
                </div>
                <div class="mt-4 flex justify-end gap-2 border-t pt-4">
                    <button id="legacy-produse-cancel" class="box rounded-md border px-3 py-2" type="button">Anulează</button>
                    <button class="box rounded-md border bg-primary px-3 py-2 text-white" type="submit">Salvează</button>
                </div>
            </form>
        </div>
    </div>

    <div id="legacy-produse-tva-modal" class="hidden fixed inset-0 z-[99999] bg-black/40 p-4">
        <div class="mx-auto w-full max-w-md rounded-lg bg-white shadow-xl">
            <div class="flex items-center border-b p-5">
                <h3 class="text-base font-medium">Actualizare TVA</h3>
                <button id="legacy-produse-tva-close" class="ml-auto rounded border px-3 py-2 text-sm" type="button">Închide</button>
            </div>
            <form id="legacy-produse-tva-form" class="p-5">
                <label>
                    <span class="mb-1 block text-sm">TVA nou (%)</span>
                    <input id="legacy-produse-tva-input" class="box h-10 w-full rounded-md border bg-white px-3 text-slate-900" type="number" step="0.01" min="0" value="19">
                </label>
                <div class="mt-4 flex justify-end gap-2 border-t pt-4">
                    <button class="box rounded-md border px-3 py-2" id="legacy-produse-tva-cancel" type="button">Anulează</button>
                    <button class="box rounded-md border bg-primary px-3 py-2 text-white" type="submit">Aplică</button>
                </div>
            </form>
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
  let rowsCache = [];

  function escapeHtml(v) {
    return String(v ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
  }

  function showLoadError(message) {
    if (!body) return;
    body.innerHTML = '<tr><td colspan="7" class="px-3 py-6 text-center text-rose-700">' + escapeHtml(message) + '</td></tr>';
  }

  async function apiCall(type, payload = {}) {
    const response = await fetch(ENDPOINT, {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      credentials: 'same-origin',
      body: JSON.stringify({type_product: type, ...payload})
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

  async function load() {
    try {
      const rows = await apiCall('produse_list', {search: search.value || '', limit: 300});
      rowsCache = rows;
      if (!rows.length) {
        body.innerHTML = '<tr><td colspan="7" class="px-3 py-6 text-center text-slate-500">Nu există produse în catalogul ERP.</td></tr>';
        return;
      }
      body.innerHTML = rows.map(p => `
        <tr class="border-b">
          <td class="px-3 py-2">${escapeHtml(p.idprodus)}</td>
          <td class="px-3 py-2 font-medium">${escapeHtml(p.denumire)}</td>
          <td class="px-3 py-2">${escapeHtml(p.cod_produs || '-')}</td>
          <td class="px-3 py-2 text-right">${Number(p.pret || 0).toFixed(2)} RON</td>
          <td class="px-3 py-2 text-center">${escapeHtml(p.TVA || '-')}</td>
          <td class="px-3 py-2 text-center">${escapeHtml(p.um || '-')}</td>
          <td class="px-3 py-2 text-center">
            <div class="inline-flex gap-2">
              <button class="rounded border px-2 py-1 text-xs text-primary" type="button" data-action="edit" data-id="${escapeHtml(p.idprodus)}">Edit</button>
              <button class="rounded border px-2 py-1 text-xs text-danger" type="button" data-action="delete" data-id="${escapeHtml(p.idprodus)}">Șterge</button>
            </div>
          </td>
        </tr>
      `).join('');
    } catch (error) {
      showLoadError(error.message || 'Nu am putut încărca produsele ERP.');
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

  document.getElementById('legacy-produse-refresh')?.addEventListener('click', () => load().catch(console.error));
  search?.addEventListener('input', () => {
    clearTimeout(search._t);
    search._t = setTimeout(() => load().catch(console.error), 350);
  });
  load().catch(console.error);
})();
</script>
<?php endif; ?>
