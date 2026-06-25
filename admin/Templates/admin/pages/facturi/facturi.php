<div class="mt-8 grid grid-cols-10 gap-6">
    <div id="facturi-toast" class="hidden fixed right-5 top-5 z-50 rounded-md border bg-background px-4 py-3 text-sm shadow"></div>

    <div class="col-span-10 lg:col-span-2">
        <h2 class="mr-auto mt-2 text-lg font-medium">Facturi</h2>
        <a href="/admin/caiet-facturi" class="box mt-3 inline-flex h-9 items-center rounded-md border bg-amber-100 px-3 text-sm font-medium text-amber-900">
            Caiet comenzi - Facturi
        </a>
        <div class="box relative mt-6 p-5">
            <div class="flex flex-col gap-2">
                <button type="button" data-facturi-filter-status="" class="flex items-center rounded-md border border-foreground/10 bg-foreground/5 px-3 py-2 text-left">Toate <span id="facturi-count-all" class="ms-auto rounded-full border px-2 py-px text-xs">0</span></button>
                <button type="button" data-facturi-filter-status="achitata" class="flex items-center rounded-md px-3 py-2 text-left hover:bg-foreground/5">Achitate <span id="facturi-count-paid" class="ms-auto rounded-full border px-2 py-px text-xs">0</span></button>
                <button type="button" data-facturi-filter-status="neachitata" class="flex items-center rounded-md px-3 py-2 text-left hover:bg-foreground/5">In asteptare <span id="facturi-count-open" class="ms-auto rounded-full border px-2 py-px text-xs">0</span></button>
                <button type="button" data-facturi-filter-status="anulata" class="flex items-center rounded-md px-3 py-2 text-left hover:bg-foreground/5">Anulate <span id="facturi-count-cancelled" class="ms-auto rounded-full border px-2 py-px text-xs">0</span></button>
                <button type="button" data-facturi-filter-status="storno" class="flex items-center rounded-md px-3 py-2 text-left hover:bg-foreground/5">Storno <span id="facturi-count-storno" class="ms-auto rounded-full border px-2 py-px text-xs">0</span></button>
            </div>
        </div>
    </div>

    <div class="col-span-10 lg:col-span-8">
        <div class="flex flex-col-reverse items-center gap-3 sm:flex-row">
            <input id="facturi-search" class="box mr-auto h-10 w-full rounded-md border bg-background px-3 py-2 sm:w-80" type="text" placeholder="Cauta factura, client, comanda, telefon...">
            <div class="flex w-full gap-2 sm:w-auto">
                <button id="facturi-open-create" type="button" class="box inline-flex h-10 items-center justify-center gap-2 rounded-lg border bg-primary/20 px-4 py-2 text-sm font-medium text-primary"><i data-lucide="plus" class="size-4"></i>Genereaza factura</button>
                <button id="facturi-export-excel" type="button" class="box inline-flex h-10 items-center justify-center gap-2 rounded-lg border px-4 py-2 text-sm font-medium"><i data-lucide="download" class="size-4"></i>Export</button>
            </div>
        </div>

        <div class="mt-5 grid grid-cols-12 gap-4">
            <div class="col-span-12 sm:col-span-6 xl:col-span-3"><div class="box p-5"><div id="facturi-stat-total" class="text-2xl font-medium">0</div><div class="mt-1 text-xs uppercase opacity-70">Facturi totale</div></div></div>
            <div class="col-span-12 sm:col-span-6 xl:col-span-3"><div class="box p-5"><div id="facturi-stat-paid" class="text-2xl font-medium">0</div><div class="mt-1 text-xs uppercase opacity-70">Achitate</div></div></div>
            <div class="col-span-12 sm:col-span-6 xl:col-span-3"><div class="box p-5"><div id="facturi-stat-open" class="text-2xl font-medium">0</div><div class="mt-1 text-xs uppercase opacity-70">In asteptare</div></div></div>
            <div class="col-span-12 sm:col-span-6 xl:col-span-3"><div class="box p-5"><div id="facturi-stat-amount" class="text-2xl font-medium">0 RON</div><div class="mt-1 text-xs uppercase opacity-70">Total facturat</div></div></div>
        </div>

        <div
            id="facturi-grid"
            class="mt-5 grid gap-4"
            style="grid-template-columns: repeat(auto-fill, minmax(190px, 240px)); align-items: start;"
        ></div>
        <div id="facturi-pagination" class="mt-5"></div>
    </div>

    <div id="facturi-modal" class="hidden fixed inset-0 bg-black/40" style="z-index: 99999; overflow-y: auto; padding: 16px;">
        <div class="mx-auto w-full max-w-3xl rounded-lg bg-white shadow-xl" style="background: #ffffff; max-height: calc(100vh - 32px); overflow-y: auto;">
            <div class="mb-5 flex items-center border-b p-6 pb-4">
                <h3 id="facturi-modal-title" class="text-base font-medium">Factura noua</h3>
                <button type="button" id="facturi-close-modal" class="ml-auto rounded border px-3 py-2">Inchide</button>
            </div>
            <form id="facturi-form" data-action="add" style="padding: 24px;">
                <input type="hidden" name="randomn_id">
                <div class="grid grid-cols-12 gap-4">
                    <label class="col-span-12">
                        <span class="mb-1 block text-sm">Alege comanda</span>
                        <select id="facturi-order-select" class="box h-10 w-full rounded-md border px-3">
                            <option value="">Selecteaza comanda</option>
                        </select>
                    </label>
                    <label class="col-span-12 md:col-span-6"><span class="mb-1 block text-sm">Titlu factura</span><input class="box h-10 w-full rounded-md border px-3" type="text" name="invoice_title" required maxlength="255"></label>
                    <label class="col-span-12 md:col-span-6"><span class="mb-1 block text-sm">Comanda</span><input class="box h-10 w-full rounded-md border px-3" type="text" name="order_number" maxlength="40"></label>
                    <label class="col-span-12 md:col-span-6"><span class="mb-1 block text-sm">Client</span><input class="box h-10 w-full rounded-md border px-3" type="text" name="client_name" maxlength="160"></label>
                    <label class="col-span-12 md:col-span-6"><span class="mb-1 block text-sm">Telefon</span><input class="box h-10 w-full rounded-md border px-3" type="tel" name="phone" maxlength="50"></label>
                    <label class="col-span-12 md:col-span-6"><span class="mb-1 block text-sm">Email</span><input class="box h-10 w-full rounded-md border px-3" type="email" name="email" maxlength="255"></label>
                    <label class="col-span-12 md:col-span-6"><span class="mb-1 block text-sm">Scadenta</span><input class="box h-10 w-full rounded-md border px-3" type="date" name="due_date"></label>
                    <label class="col-span-12 md:col-span-4"><span class="mb-1 block text-sm">Plata</span><select class="box h-10 w-full rounded-md border px-3" name="payment_method"><option value="ramburs">Ramburs</option><option value="card_online">Card online</option><option value="transfer">Transfer</option><option value="cash">Cash</option></select></label>
                    <label class="col-span-12 md:col-span-4"><span class="mb-1 block text-sm">Status</span><select class="box h-10 w-full rounded-md border px-3" name="invoice_status"><option value="neachitata">Neachitata</option><option value="achitata">Achitata</option><option value="anulata">Anulata</option><option value="storno">Storno</option></select></label>
                    <label class="col-span-12 md:col-span-4"><span class="mb-1 block text-sm">Suma</span><input class="box h-10 w-full rounded-md border px-3" type="number" name="amount" step="0.01" value="0.00"></label>
                    <label class="col-span-12"><span class="mb-1 block text-sm">Note</span><textarea class="box min-h-20 w-full rounded-md border px-3 py-2" name="notes"></textarea></label>
                </div>
                <div class="mt-5 flex justify-end gap-2 border-t bg-white pt-4" style="position: sticky; bottom: 0; z-index: 2;">
                    <button type="button" id="facturi-cancel" class="box rounded-lg border px-4 py-2">Anuleaza</button>
                    <button type="submit" class="box rounded-lg border bg-primary px-4 py-2 text-white">Salveaza</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
  'use strict';
  const ENDPOINT = '/admin/api/facturi_endpoint.php';
  const COMENZI_ENDPOINT = '/admin/api/comenzi_endpoint.php';
  const grid = document.getElementById('facturi-grid');
  const form = document.getElementById('facturi-form');
  const modal = document.getElementById('facturi-modal');
  const toast = document.getElementById('facturi-toast');
  const search = document.getElementById('facturi-search');
  const orderSelect = document.getElementById('facturi-order-select');
  let facturi = [];
  let comenzi = [];
  let statusFilter = '';
  let listMeta = { page: 1, total: 0, per_page: 10, total_pages: 1 };
  let currentPage = 1;
  let invoiceStats = null;
  const paginationEl = document.getElementById('facturi-pagination');
  async function apiCall(actionType, payload) {
    const response = await fetch(ENDPOINT, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ type_product: actionType, ...payload }) });
    const rawText = await response.text();
    let result;
    try { result = JSON.parse(rawText); } catch (error) { throw new Error('Endpoint-ul nu a returnat JSON valid.'); }
    if (!response.ok || !result.success) throw new Error(result.message || 'Eroare necunoscuta');
    return result.data;
  }
  async function comenziApiCall(actionType, payload) {
    const response = await fetch(COMENZI_ENDPOINT, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ type_product: actionType, ...payload }) });
    const rawText = await response.text();
    let result;
    try { result = JSON.parse(rawText); } catch (error) { throw new Error('Endpoint-ul Comenzi nu a returnat JSON valid.'); }
    if (!response.ok || !result.success) throw new Error(result.message || 'Nu am putut incarca comenzile.');
    return result.data;
  }
  function escapeHtml(value) { return String(value ?? '').replace(/[&<>"']/g, (char) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char])); }
  function showToast(message, isError) { if (!toast) return; toast.textContent = message; toast.classList.remove('hidden'); toast.classList.toggle('text-danger', Boolean(isError)); setTimeout(() => toast.classList.add('hidden'), 3000); }
  function formToObject(formElement) { const payload = {}; new FormData(formElement).forEach((value, key) => { if (String(value).trim() !== '') payload[key] = value; }); return payload; }
  function filteredInvoices() { return facturi; }
  function badgeClass(status) { if (status === 'achitata') return 'bg-success/20 text-success'; if (status === 'anulata') return 'bg-danger/20 text-danger'; if (status === 'storno') return 'bg-pending/20 text-pending'; return 'bg-warning/20 text-warning'; }
  function updateStats() {
    if (!invoiceStats) return;
    document.getElementById('facturi-count-all').textContent = invoiceStats.all;
    document.getElementById('facturi-count-paid').textContent = invoiceStats.achitata;
    document.getElementById('facturi-count-open').textContent = invoiceStats.neachitata;
    document.getElementById('facturi-count-cancelled').textContent = invoiceStats.anulata;
    document.getElementById('facturi-count-storno').textContent = invoiceStats.storno;
    document.getElementById('facturi-stat-total').textContent = invoiceStats.all;
    document.getElementById('facturi-stat-paid').textContent = invoiceStats.achitata;
    document.getElementById('facturi-stat-open').textContent = invoiceStats.neachitata;
    document.getElementById('facturi-stat-amount').textContent = `${Number(invoiceStats.total_amount || 0).toFixed(2)} RON`;
  }
  function renderInvoices() {
    const visible = filteredInvoices(); updateStats(); if (!grid) return;
    if (visible.length === 0) { grid.innerHTML = '<div class="box col-span-full p-6 text-center opacity-70">Nu exista facturi.</div>'; if (window.BpaPagination) BpaPagination.render(paginationEl, listMeta, (p) => loadInvoices(p)); return; }
    grid.innerHTML = visible.map((invoice) => `<div class="box relative rounded-md p-4" style="width: 100%; max-width: 240px;" data-facturi-row data-id="${escapeHtml(invoice.randomn_id)}"><div class="mx-auto flex h-28 w-24 items-center justify-center rounded-md text-sm font-medium text-white shadow-sm" style="position: relative; overflow: hidden; background: #7b8ba1;"><div style="position:absolute;right:0;top:0;width:34px;height:34px;background:#c6d0df;clip-path:polygon(0 0,100% 0,100% 100%);"></div><span style="position:relative;z-index:1;">PDF</span></div><div class="mt-4 truncate text-center font-medium">${escapeHtml(invoice.invoice_number || ('INV-' + invoice.randomn_id))}.pdf</div><div class="mt-0.5 truncate text-center text-xs opacity-70">${escapeHtml(invoice.order_number || '-')}</div><div class="mt-3 flex justify-center"><span class="rounded-full px-3 py-1 text-xs ${badgeClass(invoice.invoice_status)}">${escapeHtml(invoice.invoice_status || 'neachitata')}</span></div><div class="mt-3 text-center text-xs font-medium">${escapeHtml(invoice.amount || '0.00')} RON</div><div class="mt-4 flex justify-center gap-3"><button type="button" data-action="edit" data-invoice='${escapeHtml(JSON.stringify(invoice))}' class="text-primary">Edit</button><button type="button" data-action="setstatus" data-id="${escapeHtml(invoice.randomn_id)}" data-status="achitata" class="text-success">Achita</button><button type="button" data-action="delete" data-id="${escapeHtml(invoice.randomn_id)}" class="text-danger">Delete</button></div></div>`).join('');
    if (window.lucide) window.lucide.createIcons();
    if (window.BpaPagination) BpaPagination.render(paginationEl, listMeta, (p) => loadInvoices(p));
  }
  function openModal(invoice) { form.reset(); if (orderSelect) orderSelect.value = ''; form.dataset.action = invoice ? 'edit' : 'add'; document.getElementById('facturi-modal-title').textContent = invoice ? 'Editeaza factura' : 'Factura noua'; if (invoice) Object.entries(invoice).forEach(([key, value]) => { const field = form.elements.namedItem(key); if (field) field.value = value ?? ''; }); modal.classList.remove('hidden'); }
  function closeModal() { modal.classList.add('hidden'); }
  async function loadStats() { invoiceStats = await apiCall('stats', {}); updateStats(); }
  async function loadInvoices(page) {
    if (page) currentPage = page;
    const payload = { page: currentPage, per_page: 10, q: (search?.value || '').trim(), invoice_status: statusFilter || undefined };
    const data = await apiCall('list', payload);
    const parsed = window.BpaPagination ? BpaPagination.unwrapList(data) : { items: data, total: data.length, page: 1, per_page: 10, total_pages: 1 };
    facturi = parsed.items;
    listMeta = parsed;
    currentPage = parsed.page;
    renderInvoices();
  }
  async function loadOrdersForInvoices() {
    try {
      comenzi = await comenziApiCall('list', {});
      if (!orderSelect) return;
      orderSelect.innerHTML = '<option value="">Selecteaza comanda</option>' + comenzi.map((order) => {
        const label = `${order.order_number || ('ORD-' + order.randomn_id)} - ${order.client_name || 'Client fara nume'} - ${order.product_name || ''} - ${order.total_amount || '0.00'} RON`;
        return `<option value="${escapeHtml(order.randomn_id)}">${escapeHtml(label)}</option>`;
      }).join('');
    } catch (error) {
      showToast(error.message, true);
    }
  }
  function fillInvoiceFromOrder(randomId) {
    const order = comenzi.find((item) => Number(item.randomn_id) === Number(randomId));
    if (!order || !form) return;
    const fields = form.elements;
    if (fields.invoice_title) fields.invoice_title.value = `Factura ${order.order_number || ('ORD-' + order.randomn_id)}`;
    if (fields.order_number) fields.order_number.value = order.order_number || `ORD-${order.randomn_id}`;
    if (fields.client_name) fields.client_name.value = order.client_name || '';
    if (fields.phone) fields.phone.value = order.phone || '';
    if (fields.email) fields.email.value = order.email || '';
    if (fields.amount) fields.amount.value = order.total_amount || '0.00';
    if (fields.payment_method) fields.payment_method.value = order.payment_status === 'card_online' ? 'card_online' : 'ramburs';
    if (fields.invoice_status) fields.invoice_status.value = order.payment_status === 'confirmata' || order.order_status === 'platita' ? 'achitata' : 'neachitata';
  }
  function exportInvoices() { const rows = filteredInvoices(); if (!rows.length) { showToast('Nu exista facturi pentru export.', true); return; } const headers = ['Factura','Comanda','Client','Telefon','Email','Plata','Status','Suma','Scadenta','Creat la']; const body = rows.map((invoice) => [invoice.invoice_number, invoice.order_number, invoice.client_name, invoice.phone, invoice.email, invoice.payment_method, invoice.invoice_status, invoice.amount, invoice.due_date, invoice.created_at]); const html = `<html><head><meta charset="utf-8"></head><body><table border="1"><thead><tr>${headers.map((h)=>`<th>${escapeHtml(h)}</th>`).join('')}</tr></thead><tbody>${body.map((r)=>`<tr>${r.map((c)=>`<td>${escapeHtml(c)}</td>`).join('')}</tr>`).join('')}</tbody></table></body></html>`; const url = URL.createObjectURL(new Blob([html], { type: 'application/vnd.ms-excel;charset=utf-8;' })); const link = document.createElement('a'); link.href = url; link.download = `facturi-export-${new Date().toISOString().slice(0, 10)}.xls`; document.body.appendChild(link); link.click(); link.remove(); URL.revokeObjectURL(url); }
  document.getElementById('facturi-open-create')?.addEventListener('click', () => openModal(null)); document.getElementById('facturi-export-excel')?.addEventListener('click', exportInvoices); document.getElementById('facturi-close-modal')?.addEventListener('click', closeModal); document.getElementById('facturi-cancel')?.addEventListener('click', closeModal);
  let searchTimer;
  search?.addEventListener('input', () => { clearTimeout(searchTimer); searchTimer = setTimeout(() => { currentPage = 1; loadInvoices().catch((e) => showToast(e.message, true)); }, 300); });
  orderSelect?.addEventListener('change', () => fillInvoiceFromOrder(orderSelect.value));
  document.querySelectorAll('[data-facturi-filter-status]').forEach((button) => button.addEventListener('click', () => { statusFilter = button.dataset.facturiFilterStatus || ''; currentPage = 1; loadInvoices().catch((e) => showToast(e.message, true)); }));
  form?.addEventListener('submit', async (event) => { event.preventDefault(); try { await apiCall(form.dataset.action || 'add', formToObject(form)); closeModal(); showToast('Factura salvata.', false); await loadStats(); await loadInvoices(); } catch (error) { showToast(error.message, true); } });
  grid?.addEventListener('click', async (event) => { const button = event.target.closest('[data-action]'); if (!button) return; try { if (button.dataset.action === 'edit') { openModal(JSON.parse(button.dataset.invoice || '{}')); return; } if (button.dataset.action === 'delete') { if (!confirm('Confirmi stergerea facturii?')) return; await apiCall('delete', { randomn_id: Number(button.dataset.id) }); showToast('Factura stearsa.', false); await loadStats(); await loadInvoices(); return; } if (button.dataset.action === 'setstatus') { await apiCall('setstatus', { randomn_id: Number(button.dataset.id), invoice_status: button.dataset.status }); showToast('Status actualizat.', false); await loadStats(); await loadInvoices(); } } catch (error) { showToast(error.message, true); } });
  loadOrdersForInvoices();
  loadStats().catch(() => {});
  loadInvoices().catch((error) => showToast(error.message, true));
})();
</script>
