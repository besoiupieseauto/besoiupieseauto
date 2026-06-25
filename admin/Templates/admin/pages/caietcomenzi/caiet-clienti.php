<div>
    <div class="mt-6 mb-4 flex flex-wrap items-center gap-3">
        <h2 class="text-lg font-medium">Caiet comenzi - Clienti</h2>
        <a href="/admin/clienti" class="box inline-flex h-9 items-center rounded-md border px-3 text-sm">Inapoi la Clienti</a>
        <button id="legacy-clienti-new" class="box inline-flex h-9 items-center rounded-md border bg-primary px-3 text-sm text-white" type="button">+ Client nou</button>
    </div>

    <div class="mb-4 flex gap-2">
        <input id="legacy-clienti-search" class="box h-10 w-80 rounded-md border bg-background px-3" type="text" placeholder="Cauta nume, telefon, adresa, marca...">
        <button id="legacy-clienti-refresh" class="box h-10 rounded-md border bg-primary/20 px-4 text-primary" type="button">Reincarca</button>
    </div>

    <div class="overflow-auto">
        <table class="w-full text-sm">
            <thead class="border-b bg-foreground/5">
            <tr>
                <th class="px-3 py-2 text-left">ID</th>
                <th class="px-3 py-2 text-left">Client</th>
                <th class="px-3 py-2 text-left">Adresa</th>
                <th class="px-3 py-2 text-left">Telefon</th>
                <th class="px-3 py-2 text-left">Marca</th>
                <th class="px-3 py-2 text-left">Serie sasiu</th>
                <th class="px-3 py-2 text-left">Nr. inmat.</th>
                <th class="px-3 py-2 text-center">Actiune</th>
            </tr>
            </thead>
            <tbody id="legacy-clienti-body"></tbody>
        </table>
    </div>

    <div id="legacy-clienti-modal" class="hidden fixed inset-0 z-[99999] bg-black/40 p-4">
        <div class="mx-auto w-full max-w-3xl rounded-lg bg-white shadow-xl" style="max-height: calc(100vh - 32px); overflow-y: auto;">
            <div class="flex items-center border-b p-5">
                <h3 id="legacy-clienti-modal-title" class="text-base font-medium">Client nou</h3>
                <button id="legacy-clienti-close" class="ml-auto rounded border px-3 py-2 text-sm" type="button">Inchide</button>
            </div>
            <form id="legacy-clienti-form" class="p-5">
                <input type="hidden" name="idclienti" id="legacy-clienti-id">
                <div class="grid grid-cols-12 gap-3">
                    <label class="col-span-12 md:col-span-6">
                        <span class="mb-1 block text-sm">Client</span>
                        <input class="box h-10 w-full rounded-md border bg-white px-3 text-slate-900" type="text" name="nume" required>
                    </label>
                    <label class="col-span-12 md:col-span-6">
                        <span class="mb-1 block text-sm">Telefon</span>
                        <input class="box h-10 w-full rounded-md border bg-white px-3 text-slate-900" type="text" name="telefon">
                    </label>
                    <label class="col-span-12">
                        <span class="mb-1 block text-sm">Adresa</span>
                        <input class="box h-10 w-full rounded-md border bg-white px-3 text-slate-900" type="text" name="adresa">
                    </label>
                    <label class="col-span-12 md:col-span-6">
                        <span class="mb-1 block text-sm">Companie</span>
                        <input class="box h-10 w-full rounded-md border bg-white px-3 text-slate-900" type="text" name="companie">
                    </label>
                    <label class="col-span-12 md:col-span-6">
                        <span class="mb-1 block text-sm">CIF</span>
                        <input class="box h-10 w-full rounded-md border bg-white px-3 text-slate-900" type="text" name="cif">
                    </label>
                    <label class="col-span-12 md:col-span-4">
                        <span class="mb-1 block text-sm">Marca masina</span>
                        <input class="box h-10 w-full rounded-md border bg-white px-3 text-slate-900" type="text" name="marca">
                    </label>
                    <label class="col-span-12 md:col-span-4">
                        <span class="mb-1 block text-sm">Serie sasiu</span>
                        <input class="box h-10 w-full rounded-md border bg-white px-3 text-slate-900" type="text" name="sasiu">
                    </label>
                    <label class="col-span-12 md:col-span-4">
                        <span class="mb-1 block text-sm">Nr. inmatriculare</span>
                        <input class="box h-10 w-full rounded-md border bg-white px-3 text-slate-900" type="text" name="nr_inmat">
                    </label>
                </div>
                <div class="mt-4 flex justify-end gap-2 border-t pt-4">
                    <button id="legacy-clienti-cancel" class="box rounded-md border px-3 py-2" type="button">Anuleaza</button>
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
  const body = document.getElementById('legacy-clienti-body');
  const search = document.getElementById('legacy-clienti-search');
  const form = document.getElementById('legacy-clienti-form');
  const modal = document.getElementById('legacy-clienti-modal');
  const modalTitle = document.getElementById('legacy-clienti-modal-title');
  const idInput = document.getElementById('legacy-clienti-id');
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
      modalTitle.textContent = 'Edit Client';
      idInput.value = row.idclienti || '';
      Object.keys(row).forEach(key => {
        const field = form.elements.namedItem(key);
        if (field) field.value = row[key] ?? '';
      });
    } else {
      modalTitle.textContent = 'Client nou';
      idInput.value = '';
    }
    modal.classList.remove('hidden');
  }

  function closeModal() {
    modal.classList.add('hidden');
  }

  function renderRows(rows) {
    rowsCache = rows;
    if (!rows.length) {
      body.innerHTML = '<tr><td colspan="8" class="px-3 py-4 text-center opacity-70">Nu exista clienti.</td></tr>';
      return;
    }
    body.innerHTML = rows.map(c => `
      <tr class="border-b">
        <td class="px-3 py-2">${escapeHtml(c.idclienti)}</td>
        <td class="px-3 py-2 font-medium">${escapeHtml(c.nume || '-')}</td>
        <td class="px-3 py-2">${escapeHtml(c.adresa || '-')}</td>
        <td class="px-3 py-2">${escapeHtml(c.telefon || '-')}</td>
        <td class="px-3 py-2">${escapeHtml(c.marca || '-')}</td>
        <td class="px-3 py-2">${escapeHtml(c.sasiu || '-')}</td>
        <td class="px-3 py-2">${escapeHtml(c.nr_inmat || '-')}</td>
        <td class="px-3 py-2 text-center">
          <div class="inline-flex gap-2">
            <button class="rounded border px-2 py-1 text-xs text-primary" type="button" data-action="edit" data-id="${escapeHtml(c.idclienti)}">Edit</button>
            <button class="rounded border px-2 py-1 text-xs text-danger" type="button" data-action="delete" data-id="${escapeHtml(c.idclienti)}">Sterge</button>
          </div>
        </td>
      </tr>
    `).join('');
  }

  async function load() {
    const rows = await apiCall('clienti_list', {search: search.value || '', limit: 300});
    renderRows(rows);
  }

  document.getElementById('legacy-clienti-new')?.addEventListener('click', () => openModal(null));
  document.getElementById('legacy-clienti-close')?.addEventListener('click', closeModal);
  document.getElementById('legacy-clienti-cancel')?.addEventListener('click', closeModal);

  form?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const payload = {};
    new FormData(form).forEach((value, key) => {
      if (String(value).trim() !== '') payload[key] = value;
    });
    try {
      await apiCall('clienti_save', payload);
      closeModal();
      await load();
    } catch (error) {
      alert(error.message || 'Eroare la salvare client.');
    }
  });

  body?.addEventListener('click', async (event) => {
    const btn = event.target.closest('[data-action]');
    if (!btn) return;
    const id = Number(btn.dataset.id || 0);
    if (!id) return;

    if (btn.dataset.action === 'edit') {
      const row = rowsCache.find(item => Number(item.idclienti) === id);
      openModal(row || null);
      return;
    }

    if (btn.dataset.action === 'delete') {
      if (!confirm('Sigur doriti sa stergeti acest client?')) return;
      try {
        await apiCall('clienti_delete', {idclienti: id});
        await load();
      } catch (error) {
        alert(error.message || 'Eroare la stergere client.');
      }
    }
  });

  document.getElementById('legacy-clienti-refresh')?.addEventListener('click', () => load().catch(console.error));
  search?.addEventListener('input', () => {
    clearTimeout(search._t);
    search._t = setTimeout(() => load().catch(console.error), 350);
  });
  load().catch(console.error);
})();
</script>
