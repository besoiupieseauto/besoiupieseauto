<div>
    <div class="mt-6 mb-4 flex flex-wrap items-center gap-3">
        <h2 class="text-lg font-medium">Caiet comenzi - Facturi</h2>
        <a href="/admin/facturi" class="box inline-flex h-9 items-center rounded-md border px-3 text-sm">Inapoi la Facturi</a>
        <button id="legacy-facturi-new" class="box inline-flex h-9 items-center rounded-md border bg-primary px-3 text-sm text-white" type="button">+ Factura noua</button>
    </div>

    <div class="mb-4 flex gap-2">
        <input id="legacy-facturi-search" class="box h-10 w-80 rounded-md border bg-background px-3" type="text" placeholder="Cauta seria, client, id...">
        <select id="legacy-facturi-tip" class="box h-10 rounded-md border bg-background px-3">
            <option value="">Toate tipurile</option>
            <option value="interna">Comanda interna</option>
            <option value="externa">Comanda externa</option>
        </select>
        <button id="legacy-facturi-refresh" class="box h-10 rounded-md border bg-primary/20 px-4 text-primary" type="button">Reincarca</button>
    </div>

    <div class="overflow-auto">
        <table class="w-full text-sm">
            <thead class="border-b bg-foreground/5">
            <tr>
                <th class="px-3 py-2 text-left">OrderID</th>
                <th class="px-3 py-2 text-left">Seria</th>
                <th class="px-3 py-2 text-left">Client</th>
                <th class="px-3 py-2 text-left">Data</th>
                <th class="px-3 py-2 text-center">Tip incasare</th>
                <th class="px-3 py-2 text-center">Tip comanda</th>
                <th class="px-3 py-2 text-center">Valid</th>
                <th class="px-3 py-2 text-center">Actiune</th>
            </tr>
            </thead>
            <tbody id="legacy-facturi-body"></tbody>
        </table>
    </div>

    <div id="legacy-facturi-modal" class="hidden fixed inset-0 z-[99999] bg-black/40 p-4">
        <div class="mx-auto w-full max-w-3xl rounded-lg bg-white shadow-xl">
            <div class="flex items-center border-b p-5">
                <h3 id="legacy-facturi-modal-title" class="text-base font-medium">Factura noua</h3>
                <button id="legacy-facturi-close" class="ml-auto rounded border px-3 py-2 text-sm" type="button">Inchide</button>
            </div>
            <form id="legacy-facturi-form" class="p-5">
                <input type="hidden" name="OrderID" id="legacy-facturi-id">
                <div class="grid grid-cols-12 gap-3">
                    <label class="col-span-12 md:col-span-4">
                        <span class="mb-1 block text-sm">CustomerID</span>
                        <input class="box h-10 w-full rounded-md border bg-white px-3 text-slate-900" type="number" min="0" name="CustomerID">
                    </label>
                    <label class="col-span-12 md:col-span-4">
                        <span class="mb-1 block text-sm">Data factura</span>
                        <input class="box h-10 w-full rounded-md border bg-white px-3 text-slate-900" type="date" name="OrderDate">
                    </label>
                    <label class="col-span-12 md:col-span-4">
                        <span class="mb-1 block text-sm">Scadenta</span>
                        <input class="box h-10 w-full rounded-md border bg-white px-3 text-slate-900" type="date" name="RequiredDate">
                    </label>
                    <label class="col-span-12 md:col-span-4">
                        <span class="mb-1 block text-sm">Seria</span>
                        <input class="box h-10 w-full rounded-md border bg-white px-3 text-slate-900" type="text" name="seria">
                    </label>
                    <label class="col-span-12 md:col-span-4">
                        <span class="mb-1 block text-sm">Tip incasare</span>
                        <input class="box h-10 w-full rounded-md border bg-white px-3 text-slate-900" type="number" min="0" name="tip_incas">
                    </label>
                    <label class="col-span-12 md:col-span-4">
                        <span class="mb-1 block text-sm">Tip comanda</span>
                        <select class="box h-10 w-full rounded-md border bg-white px-3 text-slate-900" name="tip_comanda">
                            <option value="">Nespecificat</option>
                            <option value="interna">Interna</option>
                            <option value="externa">Externa</option>
                        </select>
                    </label>
                    <label class="col-span-12 md:col-span-4">
                        <span class="mb-1 block text-sm">Valid</span>
                        <select class="box h-10 w-full rounded-md border bg-white px-3 text-slate-900" name="valid">
                            <option value="Da">Da</option>
                            <option value="Nu">Nu</option>
                        </select>
                    </label>
                </div>
                <div class="mt-4 flex justify-end gap-2 border-t pt-4">
                    <button id="legacy-facturi-cancel" class="box rounded-md border px-3 py-2" type="button">Anuleaza</button>
                    <button class="box rounded-md border bg-primary px-3 py-2 text-white" type="submit">Salveaza</button>
                </div>
            </form>
        </div>
    </div>

    <div id="legacy-facturi-details-modal" class="hidden fixed inset-0 z-[99999] bg-black/40 p-4">
        <div class="mx-auto w-full max-w-4xl rounded-lg bg-white shadow-xl" style="max-height: calc(100vh - 32px); overflow-y: auto;">
            <div class="flex items-center border-b p-5">
                <h3 class="text-base font-medium">Detalii factura</h3>
                <button id="legacy-facturi-details-close" class="ml-auto rounded border px-3 py-2 text-sm" type="button">Inchide</button>
            </div>
            <div class="p-5">
                <div class="mb-3 grid grid-cols-12 gap-3 text-sm">
                    <div class="col-span-12 md:col-span-4"><span class="opacity-70">Factura:</span> <strong id="legacy-facturi-details-seria">-</strong></div>
                    <div class="col-span-12 md:col-span-4"><span class="opacity-70">Client:</span> <strong id="legacy-facturi-details-client">-</strong></div>
                    <div class="col-span-12 md:col-span-4"><span class="opacity-70">Total:</span> <strong id="legacy-facturi-details-total">0.00 RON</strong></div>
                </div>
                <div class="max-h-[360px] overflow-auto rounded-lg border">
                    <table class="w-full text-sm">
                        <thead class="border-b bg-foreground/5 text-left">
                        <tr>
                            <th class="px-3 py-2">Produs ID</th>
                            <th class="px-3 py-2 text-right">Cant.</th>
                            <th class="px-3 py-2 text-right">Pret</th>
                            <th class="px-3 py-2 text-right">Discount</th>
                            <th class="px-3 py-2 text-right">TVA</th>
                            <th class="px-3 py-2 text-right">Total</th>
                        </tr>
                        </thead>
                        <tbody id="legacy-facturi-details-body"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
  'use strict';
  const ENDPOINT = '/admin/api/caiet_comenzi_endpoint.php';
  const body = document.getElementById('legacy-facturi-body');
  const search = document.getElementById('legacy-facturi-search');
  const tip = document.getElementById('legacy-facturi-tip');
  const form = document.getElementById('legacy-facturi-form');
  const modal = document.getElementById('legacy-facturi-modal');
  const detailsModal = document.getElementById('legacy-facturi-details-modal');
  const idInput = document.getElementById('legacy-facturi-id');
  const modalTitle = document.getElementById('legacy-facturi-modal-title');
  const detailsBody = document.getElementById('legacy-facturi-details-body');
  let rowsCache = [];

  function escapeHtml(v) {
    return String(v ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
  }

  function validLabel(value) {
    const v = String(value ?? '').toLowerCase();
    if (v === 'da' || v === '1' || v === 'true') return 'Da';
    if (v === 'nu' || v === '0' || v === 'false') return 'Nu';
    return v !== '' ? String(value) : 'Nu';
  }

  function tipComandaLabel(value) {
    const n = Number(value);
    if (n === 2) return 'externa';
    if (n === 0 || n === 1) return 'interna';
    return String(value ?? '-');
  }

  async function apiCall(type, payload = {}) {
    const response = await fetch(ENDPOINT, {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({type_product: type, ...payload})
    });
    const result = await response.json();
    if (!response.ok || !result.success) throw new Error(result.message || 'Eroare la incarcare.');
    return result.data || [];
  }

  function openModal(row) {
    form.reset();
    if (row) {
      modalTitle.textContent = 'Edit factura';
      idInput.value = row.OrderID || '';
      Object.keys(row).forEach(key => {
        const field = form.elements.namedItem(key);
        if (field) field.value = row[key] ?? '';
      });
    } else {
      modalTitle.textContent = 'Factura noua';
      idInput.value = '';
      const dateField = form.elements.namedItem('OrderDate');
      if (dateField) dateField.value = new Date().toISOString().slice(0, 10);
    }
    modal.classList.remove('hidden');
  }

  function closeModal() {
    modal.classList.add('hidden');
  }

  async function load() {
    const rows = await apiCall('facturi_list', {
      search: search.value || '',
      tip_comanda: tip.value || '',
      limit: 300
    });
    rowsCache = rows;
    if (!rows.length) {
      body.innerHTML = '<tr><td colspan="8" class="px-3 py-4 text-center opacity-70">Nu exista facturi.</td></tr>';
      return;
    }
    body.innerHTML = rows.map(f => `
      <tr class="border-b">
        <td class="px-3 py-2">${escapeHtml(f.OrderID)}</td>
        <td class="px-3 py-2 font-medium">${escapeHtml(f.seria || '-')}</td>
        <td class="px-3 py-2">${escapeHtml(f.client_name || '-')}</td>
        <td class="px-3 py-2">${escapeHtml(f.OrderDate || '-')}</td>
        <td class="px-3 py-2 text-center">${escapeHtml(f.tip_incas || '-')}</td>
        <td class="px-3 py-2 text-center">${escapeHtml(tipComandaLabel(f.tip_comanda))}</td>
        <td class="px-3 py-2 text-center">${escapeHtml(validLabel(f.valid))}</td>
        <td class="px-3 py-2 text-center">
          <div class="inline-flex gap-2">
            <button class="rounded border px-2 py-1 text-xs text-primary" type="button" data-action="details" data-id="${escapeHtml(f.OrderID)}">Detalii</button>
            <button class="rounded border px-2 py-1 text-xs text-primary" type="button" data-action="edit" data-id="${escapeHtml(f.OrderID)}">Edit</button>
            <button class="rounded border px-2 py-1 text-xs text-danger" type="button" data-action="delete" data-id="${escapeHtml(f.OrderID)}">Sterge</button>
          </div>
        </td>
      </tr>
    `).join('');
  }

  form?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const payload = {};
    new FormData(form).forEach((value, key) => {
      if (String(value).trim() !== '') payload[key] = value;
    });
    try {
      await apiCall('facturi_save', payload);
      closeModal();
      await load();
    } catch (error) {
      alert(error.message || 'Eroare la salvare factura.');
    }
  });

  body?.addEventListener('click', async (event) => {
    const btn = event.target.closest('[data-action]');
    if (!btn) return;
    const id = Number(btn.dataset.id || 0);
    if (!id) return;

    if (btn.dataset.action === 'edit') {
      const row = rowsCache.find(item => Number(item.OrderID) === id);
      openModal(row || null);
      return;
    }

    if (btn.dataset.action === 'details') {
      try {
        const details = await apiCall('factura_details', {OrderID: id});
        document.getElementById('legacy-facturi-details-seria').textContent = details.seria || ('#' + id);
        document.getElementById('legacy-facturi-details-client').textContent = details.client_name || '-';
        document.getElementById('legacy-facturi-details-total').textContent = `${Number(details.grand_total || 0).toFixed(2)} RON`;
        const lines = details.lines || [];
        detailsBody.innerHTML = lines.length ? lines.map(line => `
          <tr class="border-b">
            <td class="px-3 py-2">${escapeHtml(line.ProductId)}</td>
            <td class="px-3 py-2 text-right">${Number(line.Quantity || 0).toFixed(2)}</td>
            <td class="px-3 py-2 text-right">${Number(line.UnitPrice || 0).toFixed(2)}</td>
            <td class="px-3 py-2 text-right">${Number(line.Discount || 0).toFixed(2)}</td>
            <td class="px-3 py-2 text-right">${Number(line.tva || 0).toFixed(2)}</td>
            <td class="px-3 py-2 text-right">${Number(line.total || 0).toFixed(2)}</td>
          </tr>
        `).join('') : '<tr><td colspan="6" class="px-3 py-3 text-center opacity-70">Nu exista linii pe factura.</td></tr>';
        detailsModal.classList.remove('hidden');
      } catch (error) {
        alert(error.message || 'Eroare la detalii factura.');
      }
      return;
    }

    if (btn.dataset.action === 'delete') {
      if (!confirm('Sigur doriti sa stergeti aceasta factura?')) return;
      try {
        await apiCall('facturi_delete', {OrderID: id});
        await load();
      } catch (error) {
        alert(error.message || 'Eroare la stergere factura.');
      }
    }
  });

  document.getElementById('legacy-facturi-new')?.addEventListener('click', () => openModal(null));
  document.getElementById('legacy-facturi-close')?.addEventListener('click', closeModal);
  document.getElementById('legacy-facturi-cancel')?.addEventListener('click', closeModal);
  document.getElementById('legacy-facturi-details-close')?.addEventListener('click', () => detailsModal.classList.add('hidden'));

  document.getElementById('legacy-facturi-refresh')?.addEventListener('click', () => load().catch(console.error));
  search?.addEventListener('input', () => {
    clearTimeout(search._t);
    search._t = setTimeout(() => load().catch(console.error), 350);
  });
  tip?.addEventListener('change', () => load().catch(console.error));
  load().catch(console.error);
})();
</script>
