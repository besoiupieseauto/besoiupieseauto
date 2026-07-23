<!-- Adaugă pe butonul Plus existent: id="clienti-open-create" data-action="add" -->
<!-- Adaugă pe <tbody>: id="clienti-table-body" -->
<!-- Fiecare <tr> randat din JS primește: data-clienti-row data-id="{randomn_id}" -->

<div id="clienti-toast" class="hidden fixed right-5 top-5 z-50 rounded-md border bg-background px-4 py-3 text-sm shadow"></div>

<div id="clienti-modal" class="hidden fixed inset-0 z-40 bg-black/40" style="background: white;">
    <div class="mx-auto mt-16 w-full max-w-2xl rounded-lg bg-white p-6 shadow-xl">
        <div class="mb-5 flex items-center border-b pb-4">
            <h3 id="clienti-modal-title" class="text-base font-medium">Client nou</h3>
            <button type="button" id="clienti-close-modal" class="ml-auto rounded border px-3 py-2">Închide</button>
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
                    <span class="mb-1 block text-sm">Oraș</span>
                    <input class="box h-10 w-full rounded-md border px-3" type="text" name="city" maxlength="120">
                </label>

                <label class="col-span-12">
                    <span class="mb-1 block text-sm">Adresă</span>
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
                    <span class="mb-1 block text-sm">Total plătit</span>
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
                <button type="button" id="clienti-cancel" class="box rounded-lg border px-4 py-2">Anulează</button>
                <button type="submit" class="box rounded-lg border bg-primary px-4 py-2 text-white">Salvează</button>
            </div>
        </form>
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
  const createButton = document.getElementById('clienti-open-create');

  async function apiCall(actionType, payload) {
    const response = await fetch(ENDPOINT, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ type_product: actionType, ...payload }),
    });
    const result = await response.json();
    if (!response.ok || !result.success) {
      throw new Error(result.message || 'Eroare necunoscută');
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

  function showToast(message, isError = false) {
    if (!toast) return;
    toast.textContent = message;
    toast.classList.toggle('hidden', false);
    toast.classList.toggle('text-danger', isError);
    setTimeout(() => toast.classList.add('hidden'), 3000);
  }

  function openModal(client = null) {
    if (!form || !modal) return;
    form.reset();
    form.dataset.action = client ? 'edit' : 'add';
    document.getElementById('clienti-modal-title').textContent = client ? 'Editează client' : 'Client nou';

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

  function renderClients(clients) {
    if (!tableBody) return;
    tableBody.innerHTML = clients.map((client) => `
      <tr data-clienti-row data-id="${escapeHtml(client.randomn_id)}">
        <td class="box rounded-none border-y border-foreground/10 bg-background p-4">${escapeHtml(client.randomn_id)}</td>
        <td class="box rounded-none border-y border-foreground/10 bg-background p-4">
          <div class="font-medium">${escapeHtml(client.client_name)}</div>
          <div class="mt-0.5 text-xs opacity-70">${escapeHtml(client.city || client.address || '')}</div>
        </td>
        <td class="box rounded-none border-y border-foreground/10 bg-background p-4 text-center">${escapeHtml(client.status)}</td>
        <td class="box rounded-none border-y border-foreground/10 bg-background p-4">
          <div>${escapeHtml(client.phone || '-')}</div>
          <div class="mt-0.5 text-xs opacity-70">${escapeHtml(client.email || '-')}</div>
        </td>
        <td class="box rounded-none border-y border-foreground/10 bg-background p-4 text-right">
          ${escapeHtml(client.total_orders || 0)} comenzi
          <div class="mt-0.5 text-xs opacity-70">${escapeHtml(client.total_paid || '0.00')} RON</div>
        </td>
        <td class="box rounded-none border-y border-foreground/10 bg-background p-4">
          <div class="flex items-center justify-center gap-4">
            <button type="button" data-action="edit" data-client='${escapeHtml(JSON.stringify(client))}' class="text-primary">Edit</button>
            <button type="button" data-action="setstatus" data-id="${escapeHtml(client.randomn_id)}" class="text-primary">Status</button>
            <button type="button" data-action="delete" data-id="${escapeHtml(client.randomn_id)}" class="text-danger">Delete</button>
          </div>
        </td>
      </tr>
    `).join('');
  }

  async function loadClients() {
    renderClients(await apiCall('list', {}));
  }

  createButton?.addEventListener('click', () => openModal());
  document.getElementById('clienti-close-modal')?.addEventListener('click', closeModal);
  document.getElementById('clienti-cancel')?.addEventListener('click', closeModal);

  form?.addEventListener('submit', async (event) => {
    event.preventDefault();
    try {
      await apiCall(form.dataset.action || 'add', formToObject(form));
      closeModal();
      showToast('Client salvat.');
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
        if (!confirm('Confirmi ștergerea clientului?')) return;
        await apiCall('delete', { randomn_id: Number(button.dataset.id) });
        button.closest('[data-clienti-row]')?.remove();
        showToast('Client șters.');
        return;
      }

      if (button.dataset.action === 'setstatus') {
        await apiCall('setstatus', { randomn_id: Number(button.dataset.id), status: 'activ' });
        showToast('Status actualizat.');
        await loadClients();
      }
    } catch (error) {
      showToast(error.message, true);
    }
  });

  loadClients().catch((error) => showToast(error.message, true));
})();
</script>
