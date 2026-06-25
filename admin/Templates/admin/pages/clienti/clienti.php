<div>
    <h2 class="mt-10 text-lg font-medium">Lista Clienti</h2>

    <div id="clienti-toast" class="hidden fixed right-5 top-5 z-50 rounded-md border bg-background px-4 py-3 text-sm shadow"></div>

    <div class="mt-5 grid grid-cols-12 gap-6">
        <div class="col-span-12 mt-2 flex flex-wrap items-center sm:flex-nowrap">
            <a href="/admin/caiet-clienti" class="box mr-2 inline-flex h-10 items-center justify-center rounded-lg border bg-amber-100 px-4 py-2 text-sm font-medium text-amber-900">
                Caiet comenzi - Clienti
            </a>
            <div class="flex w-full sm:w-auto">
                <div class="relative w-56">
                    <input
                        id="clienti-search"
                        class="box h-10 w-56 rounded-md border bg-background px-3 py-2 pr-10"
                        type="text"
                        placeholder="Search..."
                    >
                    <i data-lucide="search" class="absolute inset-y-0 right-0 my-auto mr-3 h-4 w-4 opacity-70"></i>
                </div>

                <select id="clienti-status-filter" class="box ml-2 h-10 rounded-md border bg-background px-3">
                    <option value="">Status</option>
                    <option value="activ">Activ</option>
                    <option value="vip">VIP</option>
                    <option value="nou">Nou</option>
                    <option value="inactiv">Inactiv</option>
                    <option value="blocat">Blocat</option>
                </select>
            </div>

            <div id="clienti-counter" class="mx-auto hidden opacity-70 md:block">
                Se incarca...
            </div>

            <div class="mt-3 flex w-full items-center xl:mt-0 xl:w-auto">
                <button type="button" class="box mr-2 inline-flex h-10 items-center justify-center gap-2 rounded-lg border px-4 py-2 text-sm font-medium">
                    <i data-lucide="file-text" class="size-4"></i>
                    Export Excel
                </button>

                <button type="button" class="box mr-2 inline-flex h-10 items-center justify-center gap-2 rounded-lg border px-4 py-2 text-sm font-medium">
                    <i data-lucide="file-text" class="size-4"></i>
                    Export PDF
                </button>

                <button
                    type="button"
                    id="clienti-open-create"
                    data-action="add"
                    class="box inline-flex h-10 items-center justify-center rounded-lg border px-3 py-2"
                    title="Adauga client"
                >
                    <i data-lucide="plus" class="size-4"></i>
                </button>
            </div>
        </div>

        <div class="col-span-12 overflow-auto lg:overflow-visible">
            <div class="relative w-full overflow-auto">
                <table class="w-full caption-bottom border-separate border-spacing-y-[10px] -mt-2">
                    <thead>
                    <tr>
                        <th class="h-12 px-4 text-left font-medium text-muted-foreground">CLIENT ID</th>
                        <th class="h-12 px-4 text-left font-medium text-muted-foreground">CLIENT NAME</th>
                        <th class="h-12 px-4 text-center font-medium text-muted-foreground">STATUS</th>
                        <th class="h-12 px-4 text-left font-medium text-muted-foreground">CONTACT</th>
                        <th class="h-12 px-4 text-right font-medium text-muted-foreground">TOTAL ORDERS</th>
                        <th class="h-12 px-4 text-center font-medium text-muted-foreground">ACTIONS</th>
                    </tr>
                    </thead>
                    <tbody id="clienti-table-body"></tbody>
                </table>
            </div>
        </div>
        <div id="clienti-pagination" class="col-span-12"></div>
    </div>

    <div id="clienti-modal" class="hidden fixed inset-0 z-40 bg-black/40">
        <div class="mx-auto mt-16 w-full max-w-2xl rounded-lg bg-white p-6 shadow-xl">
            <div class="mb-5 flex items-center border-b pb-4">
                <h3 id="clienti-modal-title" class="text-base font-medium">Client nou</h3>
                <button type="button" id="clienti-close-modal" class="ml-auto rounded border px-3 py-2">Inchide</button>
            </div>

            <form id="clienti-form" data-clienti-form data-action="add">
                <input type="hidden" name="randomn_id" id="clienti-randomn-id">

                <div class="grid grid-cols-12 gap-4">
                    <label class="col-span-12 md:col-span-6">
                        <span class="mb-1 block text-sm">Nume client</span>
                        <input class="box h-10 w-full rounded-md border px-3" type="text" name="client_name" required maxlength="160">
                    </label>

                    <label class="col-span-12 md:col-span-6">
                        <span class="mb-1 block text-sm">Email</span>
                        <input class="box h-10 w-full rounded-md border px-3" type="email" name="email" maxlength="190">
                    </label>

                    <label class="col-span-12 md:col-span-6">
                        <span class="mb-1 block text-sm">Telefon</span>
                        <input class="box h-10 w-full rounded-md border px-3" type="tel" name="phone" maxlength="40">
                    </label>

                    <label class="col-span-12 md:col-span-6">
                        <span class="mb-1 block text-sm">Oras</span>
                        <input class="box h-10 w-full rounded-md border px-3" type="text" name="city" maxlength="120">
                    </label>

                    <label class="col-span-12">
                        <span class="mb-1 block text-sm">Adresa</span>
                        <input class="box h-10 w-full rounded-md border px-3" type="text" name="address" maxlength="255">
                    </label>

                    <label class="col-span-12 md:col-span-4">
                        <span class="mb-1 block text-sm">Status</span>
                        <select class="box h-10 w-full rounded-md border px-3" name="status">
                            <option value="nou">Nou</option>
                            <option value="activ">Activ</option>
                            <option value="vip">VIP</option>
                            <option value="inactiv">Inactiv</option>
                            <option value="blocat">Blocat</option>
                        </select>
                    </label>

                    <label class="col-span-12 md:col-span-4">
                        <span class="mb-1 block text-sm">Comenzi</span>
                        <input class="box h-10 w-full rounded-md border px-3" type="number" name="total_orders" min="0" value="0">
                    </label>

                    <label class="col-span-12 md:col-span-4">
                        <span class="mb-1 block text-sm">Total platit</span>
                        <input class="box h-10 w-full rounded-md border px-3" type="number" name="total_paid" min="0" step="0.01" value="0.00">
                    </label>

                    <label class="col-span-12">
                        <span class="mb-1 block text-sm">Curier preferat</span>
                        <input class="box h-10 w-full rounded-md border px-3" type="text" name="preferred_courier" maxlength="120">
                    </label>

                    <label class="col-span-12">
                        <span class="mb-1 block text-sm">Note</span>
                        <textarea class="box min-h-24 w-full rounded-md border px-3 py-2" name="notes"></textarea>
                    </label>
                </div>

                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" id="clienti-cancel" class="box rounded-lg border px-4 py-2">Anuleaza</button>
                    <button type="submit" class="box rounded-lg border bg-primary px-4 py-2 text-white">Salveaza</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
  'use strict';

  const ENDPOINT = '/admin/api/clienti_endpoint.php';
  const form = document.getElementById('clienti-form');
  const modal = document.getElementById('clienti-modal');
  const tableBody = document.getElementById('clienti-table-body');
  const toast = document.getElementById('clienti-toast');
  const counter = document.getElementById('clienti-counter');
  const searchInput = document.getElementById('clienti-search');
  const statusFilter = document.getElementById('clienti-status-filter');
  const paginationEl = document.getElementById('clienti-pagination');
  let clienti = [];
  let listMeta = { page: 1, total: 0, per_page: 10, total_pages: 1 };
  let currentPage = 1;

  async function apiCall(actionType, payload) {
    const response = await fetch(ENDPOINT, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ type_product: actionType, ...payload }),
    });
    const result = await response.json();
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
    toast.classList.remove('hidden');
    toast.classList.toggle('text-danger', Boolean(isError));
    setTimeout(() => toast.classList.add('hidden'), 3000);
  }

  function openModal(client) {
    if (!form || !modal) return;
    form.reset();
    form.dataset.action = client ? 'edit' : 'add';
    document.getElementById('clienti-modal-title').textContent = client ? 'Editeaza client' : 'Client nou';

    if (client) {
      Object.entries(client).forEach(([key, value]) => {
        const field = form.elements.namedItem(key);
        if (field) field.value = value ?? '';
      });
    }

    modal.classList.remove('hidden');
  }

  function closeModal() {
    modal?.classList.add('hidden');
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

  function statusClass(status) {
    return ['activ', 'vip'].includes(status) ? 'text-success' : 'text-danger';
  }

  function renderClients() {
    if (!tableBody) return;
    counter.textContent = `${listMeta.total} clienti`;

    if (clienti.length === 0) {
      tableBody.innerHTML = '<tr><td colspan="6" class="box bg-background p-6 text-center opacity-70">Nu exista clienti.</td></tr>';
      if (window.BpaPagination) BpaPagination.render(paginationEl, listMeta, (p) => loadClients(p));
      return;
    }

    tableBody.innerHTML = clienti.map((client) => `
      <tr data-clienti-row data-id="${escapeHtml(client.randomn_id)}">
        <td class="box rounded-none border-y border-foreground/10 bg-background p-4 first:rounded-l-xl first:border-l last:rounded-r-xl last:border-r shadow-[3px_3px_5px_#0000000b]">
          <a class="whitespace-nowrap underline decoration-dotted" href="#">#CL-${escapeHtml(client.randomn_id)}</a>
        </td>
        <td class="box rounded-none border-y border-foreground/10 bg-background p-4 first:rounded-l-xl first:border-l last:rounded-r-xl last:border-r shadow-[3px_3px_5px_#0000000b]">
          <div class="whitespace-nowrap font-medium">${escapeHtml(client.client_name)}</div>
          <div class="mt-0.5 whitespace-nowrap text-xs opacity-70">${escapeHtml(client.city || client.address || '')}</div>
        </td>
        <td class="box rounded-none border-y border-foreground/10 bg-background p-4 text-center first:rounded-l-xl first:border-l last:rounded-r-xl last:border-r shadow-[3px_3px_5px_#0000000b]">
          <div class="flex items-center justify-center ${statusClass(client.status)}">
            <i data-lucide="check-square" class="mr-2 size-4"></i>
            ${escapeHtml(client.status)}
          </div>
        </td>
        <td class="box rounded-none border-y border-foreground/10 bg-background p-4 first:rounded-l-xl first:border-l last:rounded-r-xl last:border-r shadow-[3px_3px_5px_#0000000b]">
          <div class="whitespace-nowrap">${escapeHtml(client.phone || '-')}</div>
          <div class="mt-0.5 whitespace-nowrap text-xs opacity-70">${escapeHtml(client.email || '-')}</div>
        </td>
        <td class="box rounded-none border-y border-foreground/10 bg-background p-4 text-right first:rounded-l-xl first:border-l last:rounded-r-xl last:border-r shadow-[3px_3px_5px_#0000000b]">
          ${escapeHtml(client.total_orders || 0)} comenzi
          <div class="mt-0.5 text-xs opacity-70">${escapeHtml(client.total_paid || '0.00')} RON</div>
        </td>
        <td class="box rounded-none border-y border-foreground/10 bg-background p-4 first:rounded-l-xl first:border-l last:rounded-r-xl last:border-r shadow-[3px_3px_5px_#0000000b]">
          <div class="flex items-center justify-center gap-4">
            <button type="button" data-action="edit" data-client='${escapeHtml(JSON.stringify(client))}' class="flex items-center whitespace-nowrap text-primary">
              <i data-lucide="edit" class="mr-1 size-4"></i>Edit
            </button>
            <button type="button" data-action="setstatus" data-id="${escapeHtml(client.randomn_id)}" data-status="${client.status === 'activ' ? 'inactiv' : 'activ'}" class="flex items-center whitespace-nowrap text-primary">
              <i data-lucide="arrow-left-right" class="mr-1 size-4"></i>Status
            </button>
            <button type="button" data-action="delete" data-id="${escapeHtml(client.randomn_id)}" class="flex items-center whitespace-nowrap text-danger">
              <i data-lucide="trash-2" class="mr-1 size-4"></i>Delete
            </button>
          </div>
        </td>
      </tr>
    `).join('');

    if (window.lucide) {
      window.lucide.createIcons();
    }
    if (window.BpaPagination) {
      BpaPagination.render(paginationEl, listMeta, (p) => loadClients(p));
    }
  }

  async function loadClients(page) {
    if (page) currentPage = page;
    const payload = {
      page: currentPage,
      per_page: 10,
      q: (searchInput?.value || '').trim(),
    };
    const data = await apiCall('list', payload);
    const parsed = window.BpaPagination ? BpaPagination.unwrapList(data) : { items: data, total: data.length, page: 1, per_page: 10, total_pages: 1 };
    clienti = parsed.items;
    listMeta = parsed;
    currentPage = parsed.page;
    renderClients();
  }

  document.getElementById('clienti-open-create')?.addEventListener('click', () => openModal(null));
  document.getElementById('clienti-close-modal')?.addEventListener('click', closeModal);
  document.getElementById('clienti-cancel')?.addEventListener('click', closeModal);
  searchInput?.addEventListener('input', () => { currentPage = 1; loadClients().catch((e) => showToast(e.message, true)); });
  statusFilter?.addEventListener('change', renderClients);

  form?.addEventListener('submit', async (event) => {
    event.preventDefault();
    try {
      await apiCall(form.dataset.action || 'add', formToObject(form));
      closeModal();
      showToast('Client salvat.', false);
      await loadClients();
    } catch (error) {
      showToast(error.message, true);
    }
  });

  tableBody?.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-action]');
    if (!button) return;

    try {
      if (button.dataset.action === 'edit') {
        openModal(JSON.parse(button.dataset.client || '{}'));
        return;
      }

      if (button.dataset.action === 'delete') {
        if (!confirm('Confirmi stergerea clientului?')) return;
        await apiCall('delete', { randomn_id: Number(button.dataset.id) });
        showToast('Client sters.', false);
        await loadClients();
        return;
      }

      if (button.dataset.action === 'setstatus') {
        await apiCall('setstatus', { randomn_id: Number(button.dataset.id), status: button.dataset.status });
        showToast('Status actualizat.', false);
        await loadClients();
      }
    } catch (error) {
      showToast(error.message, true);
    }
  });

  BpaAsync.defer(() => loadClients().catch((error) => showToast(error.message, true)));
})();
</script>
