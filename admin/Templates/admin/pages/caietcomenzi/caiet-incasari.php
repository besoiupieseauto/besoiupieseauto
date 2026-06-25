<div>
    <div class="mt-6 mb-4 flex flex-wrap items-center gap-3">
        <h2 class="text-lg font-medium">Caiet comenzi - Incasari</h2>
        <button id="legacy-incasari-new" class="box inline-flex h-9 items-center rounded-md border bg-primary px-3 text-sm text-white" type="button">+ Incasare noua</button>
        <button id="legacy-incasari-daily" class="box inline-flex h-9 items-center rounded-md border bg-primary/20 px-3 text-sm text-primary" type="button">Start zi cash</button>
    </div>

    <div class="mb-4 flex gap-2">
        <input id="legacy-incasari-search" class="box h-10 w-80 rounded-md border bg-background px-3" type="text" placeholder="Cauta client, text, comanda...">
        <input id="legacy-incasari-from" class="box h-10 rounded-md border bg-background px-3" type="date">
        <input id="legacy-incasari-to" class="box h-10 rounded-md border bg-background px-3" type="date">
        <button id="legacy-incasari-refresh" class="box h-10 rounded-md border bg-primary/20 px-4 text-primary" type="button">Reincarca</button>
    </div>

    <div class="overflow-auto">
        <table class="w-full text-sm">
            <thead class="border-b bg-foreground/5">
            <tr>
                <th class="px-3 py-2 text-left">ID</th>
                <th class="px-3 py-2 text-left">Client</th>
                <th class="px-3 py-2 text-left">Detalii</th>
                <th class="px-3 py-2 text-right">Suma</th>
                <th class="px-3 py-2 text-left">Data</th>
                <th class="px-3 py-2 text-center">Locatie</th>
                <th class="px-3 py-2 text-center">Actiune</th>
            </tr>
            </thead>
            <tbody id="legacy-incasari-body"></tbody>
        </table>
    </div>

    <div id="legacy-incasari-modal" class="hidden fixed inset-0 z-[99999] bg-black/40 p-4">
        <div class="mx-auto w-full max-w-2xl rounded-lg bg-white shadow-xl">
            <div class="flex items-center border-b p-5">
                <h3 id="legacy-incasari-modal-title" class="text-base font-medium">Incasare noua</h3>
                <button id="legacy-incasari-close" class="ml-auto rounded border px-3 py-2 text-sm" type="button">Inchide</button>
            </div>
            <form id="legacy-incasari-form" class="p-5">
                <input type="hidden" name="id" id="legacy-incasari-id">
                <div class="grid grid-cols-12 gap-3">
                    <label class="col-span-12 md:col-span-6">
                        <span class="mb-1 block text-sm">Client ID</span>
                        <input class="box h-10 w-full rounded-md border bg-white px-3 text-slate-900" type="number" name="idclient" min="0">
                    </label>
                    <label class="col-span-12 md:col-span-6">
                        <span class="mb-1 block text-sm">Comanda ID</span>
                        <input class="box h-10 w-full rounded-md border bg-white px-3 text-slate-900" type="number" name="idcmd" min="0">
                    </label>
                    <label class="col-span-12 md:col-span-6">
                        <span class="mb-1 block text-sm">Suma</span>
                        <input class="box h-10 w-full rounded-md border bg-white px-3 text-slate-900" type="number" step="0.01" min="0.01" name="suma" required>
                    </label>
                    <label class="col-span-12 md:col-span-6">
                        <span class="mb-1 block text-sm">Data</span>
                        <input class="box h-10 w-full rounded-md border bg-white px-3 text-slate-900" type="date" name="data">
                    </label>
                    <label class="col-span-12 md:col-span-6">
                        <span class="mb-1 block text-sm">Locatie (1 TM, 2 Utvin)</span>
                        <input class="box h-10 w-full rounded-md border bg-white px-3 text-slate-900" type="number" name="locatie_mgz" value="1">
                    </label>
                    <label class="col-span-12 md:col-span-6">
                        <span class="mb-1 block text-sm">Status</span>
                        <input class="box h-10 w-full rounded-md border bg-white px-3 text-slate-900" type="number" name="idstare" value="1">
                    </label>
                    <label class="col-span-12">
                        <span class="mb-1 block text-sm">Detalii</span>
                        <textarea class="box min-h-20 w-full rounded-md border bg-white px-3 py-2 text-slate-900" name="cstmtext"></textarea>
                    </label>
                </div>
                <div class="mt-4 flex justify-end gap-2 border-t pt-4">
                    <button id="legacy-incasari-cancel" class="box rounded-md border px-3 py-2" type="button">Anuleaza</button>
                    <button class="box rounded-md border bg-primary px-3 py-2 text-white" type="submit">Salveaza</button>
                </div>
            </form>
        </div>
    </div>

    <div id="legacy-incasari-daily-modal" class="hidden fixed inset-0 z-[99999] bg-black/40 p-4">
        <div class="mx-auto w-full max-w-md rounded-lg bg-white shadow-xl">
            <div class="flex items-center border-b p-5">
                <h3 class="text-base font-medium">Start of day cash</h3>
                <button id="legacy-incasari-daily-close" class="ml-auto rounded border px-3 py-2 text-sm" type="button">Inchide</button>
            </div>
            <form id="legacy-incasari-daily-form" class="p-5">
                <label>
                    <span class="mb-1 block text-sm">Suma</span>
                    <input id="legacy-incasari-daily-amount" class="box h-10 w-full rounded-md border bg-white px-3 text-slate-900" type="number" step="0.01" min="0" required>
                </label>
                <div class="mt-4 flex justify-end gap-2 border-t pt-4">
                    <button id="legacy-incasari-daily-cancel" class="box rounded-md border px-3 py-2" type="button">Anuleaza</button>
                    <button class="box rounded-md border bg-primary px-3 py-2 text-white" type="submit">Salveaza</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
  'use strict';
  const ENDPOINT = '/admin/api/caiet_comenzi_endpoint.php';
  const body = document.getElementById('legacy-incasari-body');
  const search = document.getElementById('legacy-incasari-search');
  const from = document.getElementById('legacy-incasari-from');
  const to = document.getElementById('legacy-incasari-to');
  const form = document.getElementById('legacy-incasari-form');
  const modal = document.getElementById('legacy-incasari-modal');
  const modalTitle = document.getElementById('legacy-incasari-modal-title');
  const dailyModal = document.getElementById('legacy-incasari-daily-modal');
  const dailyForm = document.getElementById('legacy-incasari-daily-form');
  let rowsCache = [];

  function escapeHtml(v) {
    return String(v ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
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
      modalTitle.textContent = 'Edit incasare';
      Object.keys(row).forEach(key => {
        const field = form.elements.namedItem(key);
        if (field) field.value = row[key] ?? '';
      });
    } else {
      modalTitle.textContent = 'Incasare noua';
      const dateField = form.elements.namedItem('data');
      if (dateField) dateField.value = new Date().toISOString().slice(0, 10);
    }
    modal.classList.remove('hidden');
  }

  function closeModal() {
    modal.classList.add('hidden');
  }

  async function load() {
    const rows = await apiCall('incasari_list', {
      search: search.value || '',
      date_from: from.value || '',
      date_to: to.value || '',
      limit: 300
    });
    rowsCache = rows;
    if (!rows.length) {
      body.innerHTML = '<tr><td colspan="7" class="px-3 py-4 text-center opacity-70">Nu exista incasari.</td></tr>';
      return;
    }
    body.innerHTML = rows.map(i => `
      <tr class="border-b">
        <td class="px-3 py-2">${escapeHtml(i.id)}</td>
        <td class="px-3 py-2 font-medium">${escapeHtml(i.client_name || '-')}</td>
        <td class="px-3 py-2">${escapeHtml(i.cstmtext || '-')}</td>
        <td class="px-3 py-2 text-right">${Number(i.suma || 0).toFixed(2)} RON</td>
        <td class="px-3 py-2">${escapeHtml(i.data || '-')}</td>
        <td class="px-3 py-2 text-center">${escapeHtml(i.locatie_mgz || '-')}</td>
        <td class="px-3 py-2 text-center">
          <div class="inline-flex gap-2">
            <button class="rounded border px-2 py-1 text-xs text-primary" type="button" data-action="edit" data-id="${escapeHtml(i.id)}">Edit</button>
            <button class="rounded border px-2 py-1 text-xs text-danger" type="button" data-action="delete" data-id="${escapeHtml(i.id)}">Sterge</button>
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
      await apiCall('incasari_save', payload);
      closeModal();
      await load();
    } catch (error) {
      alert(error.message || 'Eroare la salvare incasare.');
    }
  });

  body?.addEventListener('click', async (event) => {
    const btn = event.target.closest('[data-action]');
    if (!btn) return;
    const id = Number(btn.dataset.id || 0);
    if (!id) return;

    if (btn.dataset.action === 'edit') {
      const row = rowsCache.find(item => Number(item.id) === id);
      openModal(row || null);
      return;
    }

    if (btn.dataset.action === 'delete') {
      if (!confirm('Sigur doriti sa stergeti aceasta incasare?')) return;
      try {
        await apiCall('incasari_delete', {id});
        await load();
      } catch (error) {
        alert(error.message || 'Eroare la stergere incasare.');
      }
    }
  });

  dailyForm?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const amount = Number(document.getElementById('legacy-incasari-daily-amount')?.value || 0);
    try {
      await apiCall('incasari_daily_price_update', {amount});
      dailyModal.classList.add('hidden');
    } catch (error) {
      alert(error.message || 'Eroare la salvare suma start zi.');
    }
  });

  document.getElementById('legacy-incasari-new')?.addEventListener('click', () => openModal(null));
  document.getElementById('legacy-incasari-close')?.addEventListener('click', closeModal);
  document.getElementById('legacy-incasari-cancel')?.addEventListener('click', closeModal);
  document.getElementById('legacy-incasari-daily')?.addEventListener('click', async () => {
    try {
      const info = await apiCall('incasari_daily_price_get');
      document.getElementById('legacy-incasari-daily-amount').value = Number(info.amount || 0).toFixed(2);
      dailyModal.classList.remove('hidden');
    } catch (error) {
      alert(error.message || 'Eroare la incarcare suma start zi.');
    }
  });
  document.getElementById('legacy-incasari-daily-close')?.addEventListener('click', () => dailyModal.classList.add('hidden'));
  document.getElementById('legacy-incasari-daily-cancel')?.addEventListener('click', () => dailyModal.classList.add('hidden'));

  document.getElementById('legacy-incasari-refresh')?.addEventListener('click', () => load().catch(console.error));
  [search, from, to].forEach(el => el?.addEventListener('change', () => load().catch(console.error)));
  search?.addEventListener('input', () => {
    clearTimeout(search._t);
    search._t = setTimeout(() => load().catch(console.error), 350);
  });
  load().catch(console.error);
})();
</script>
