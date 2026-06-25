<div>
    <h2 class="mt-10 text-lg font-medium">Livrari</h2>
    <div id="livrare-toast" class="hidden fixed right-5 top-5 z-50 rounded-md border bg-background px-4 py-3 text-sm shadow"></div>

    <div class="mt-5 grid grid-cols-12 gap-6">
        <div class="col-span-12 mt-2 flex flex-wrap items-center sm:flex-nowrap">
            <div class="flex w-full flex-wrap gap-2 sm:w-auto">
                <input id="livrare-search" class="box h-10 w-64 rounded-md border bg-background px-3 py-2" type="text" placeholder="Cauta AWB, comanda, client...">
                <select id="livrare-status-filter" class="box h-10 rounded-md border bg-background px-3">
                    <option value="">Status livrare</option>
                    <option value="pregatire">Pregatire colet</option>
                    <option value="awb_generat">AWB generat</option>
                    <option value="in_tranzit">In tranzit</option>
                    <option value="livrat">Livrat</option>
                    <option value="retur">Retur</option>
                    <option value="anulat">Anulat</option>
                </select>
                <select id="livrare-courier-filter" class="box h-10 rounded-md border bg-background px-3">
                    <option value="">Curier</option>
                    <option value="Fan Courier">Fan Courier</option>
                    <option value="Cargus">Cargus</option>
                    <option value="Sameday">Sameday</option>
                    <option value="DHL">DHL</option>
                    <option value="Ridicare personala">Ridicare personala</option>
                </select>
            </div>
            <div id="livrare-counter" class="mx-auto hidden opacity-70 md:block">Se incarca...</div>
            <div class="mt-3 flex w-full items-center xl:mt-0 xl:w-auto">
                <button id="livrare-export-excel" type="button" class="box mr-2 inline-flex h-10 items-center justify-center gap-2 rounded-lg border px-4 py-2 text-sm font-medium"><i data-lucide="file-text" class="size-4"></i>Export Excel</button>
                <button id="livrare-print-awb" type="button" class="box mr-2 inline-flex h-10 items-center justify-center gap-2 rounded-lg border px-4 py-2 text-sm font-medium"><i data-lucide="printer" class="size-4"></i>Print AWB</button>
                <button id="livrare-open-create" type="button" class="box inline-flex h-10 items-center justify-center gap-2 rounded-lg border bg-primary/20 px-4 py-2 text-sm font-medium text-primary"><i data-lucide="plus" class="size-4"></i>Adauga livrare</button>
            </div>
        </div>

        <div class="col-span-12 overflow-auto lg:overflow-visible">
            <div class="relative w-full overflow-auto">
                <table class="w-full caption-bottom border-separate border-spacing-y-[10px] -mt-2">
                    <thead>
                    <tr>
                        <th class="h-12 px-4 text-left font-medium text-muted-foreground">AWB</th>
                        <th class="h-12 px-4 text-left font-medium text-muted-foreground">CLIENT</th>
                        <th class="h-12 px-4 text-left font-medium text-muted-foreground">COMANDA</th>
                        <th class="h-12 px-4 text-left font-medium text-muted-foreground">CURIER</th>
                        <th class="h-12 px-4 text-center font-medium text-muted-foreground">STATUS</th>
                        <th class="h-12 px-4 text-left font-medium text-muted-foreground">DATA LIVRARII</th>
                        <th class="h-12 px-4 text-right font-medium text-muted-foreground">TOTAL</th>
                        <th class="h-12 px-4 text-center font-medium text-muted-foreground">ACTIUNI</th>
                    </tr>
                    </thead>
                    <tbody id="livrare-table-body"></tbody>
                </table>
            </div>
        </div>
        <div id="livrare-pagination" class="col-span-12"></div>
    </div>

    <div id="livrare-modal" class="hidden fixed inset-0 bg-black/40" style="z-index: 99999; overflow-y: auto; padding: 16px;">
        <div class="mx-auto w-full max-w-3xl rounded-lg bg-white shadow-xl" style="background: #ffffff; max-height: calc(100vh - 32px); overflow-y: auto;">
            <div class="mb-5 flex items-center border-b p-6 pb-4">
                <h3 id="livrare-modal-title" class="text-base font-medium">Livrare noua</h3>
                <button type="button" id="livrare-close-modal" class="ml-auto rounded border px-3 py-2">Inchide</button>
            </div>
            <form id="livrare-form" data-action="add" style="padding: 24px;">
                <input type="hidden" name="randomn_id">
                <div class="grid grid-cols-12 gap-4">
                    <label class="col-span-12"><span class="mb-1 block text-sm">Alege comanda</span><select id="livrare-order-select" class="box h-10 w-full rounded-md border px-3"><option value="">Selecteaza comanda</option></select></label>
                    <label class="col-span-12 md:col-span-6"><span class="mb-1 block text-sm">Titlu livrare</span><input class="box h-10 w-full rounded-md border px-3" type="text" name="delivery_title" required maxlength="255"></label>
                    <label class="col-span-12 md:col-span-6"><span class="mb-1 block text-sm">AWB</span><input class="box h-10 w-full rounded-md border px-3" type="text" name="awb" maxlength="80"></label>
                    <label class="col-span-12 md:col-span-6"><span class="mb-1 block text-sm">Comanda</span><input class="box h-10 w-full rounded-md border px-3" type="text" name="order_number" maxlength="40"></label>
                    <label class="col-span-12 md:col-span-6"><span class="mb-1 block text-sm">Client</span><input class="box h-10 w-full rounded-md border px-3" type="text" name="client_name" maxlength="160"></label>
                    <label class="col-span-12 md:col-span-6"><span class="mb-1 block text-sm">Telefon</span><input class="box h-10 w-full rounded-md border px-3" type="tel" name="phone" maxlength="50"></label>
                    <label class="col-span-12 md:col-span-6"><span class="mb-1 block text-sm">Email</span><input class="box h-10 w-full rounded-md border px-3" type="email" name="email" maxlength="255"></label>
                    <label class="col-span-12"><span class="mb-1 block text-sm">Adresa</span><input class="box h-10 w-full rounded-md border px-3" type="text" name="address" maxlength="255"></label>
                    <label class="col-span-12 md:col-span-4"><span class="mb-1 block text-sm">Curier</span><select class="box h-10 w-full rounded-md border px-3" name="courier"><option value="Fan Courier">Fan Courier</option><option value="Cargus">Cargus</option><option value="Sameday">Sameday</option><option value="DHL">DHL</option><option value="Ridicare personala">Ridicare personala</option></select></label>
                    <label class="col-span-12 md:col-span-4"><span class="mb-1 block text-sm">Status</span><select class="box h-10 w-full rounded-md border px-3" name="delivery_status"><option value="pregatire">Pregatire</option><option value="awb_generat">AWB generat</option><option value="in_tranzit">In tranzit</option><option value="livrat">Livrat</option><option value="retur">Retur</option><option value="anulat">Anulat</option></select></label>
                    <label class="col-span-12 md:col-span-4"><span class="mb-1 block text-sm">Total</span><input class="box h-10 w-full rounded-md border px-3" type="number" name="total_amount" min="0" step="0.01" value="0.00"></label>
                    <label class="col-span-12 md:col-span-6"><span class="mb-1 block text-sm">Data livrarii</span><input class="box h-10 w-full rounded-md border px-3" type="date" name="delivery_date"></label>
                    <label class="col-span-12 md:col-span-6"><span class="mb-1 block text-sm">Ora / observatie timp</span><input class="box h-10 w-full rounded-md border px-3" type="text" name="delivery_time" maxlength="20"></label>
                    <label class="col-span-12"><span class="mb-1 block text-sm">Note</span><textarea class="box min-h-20 w-full rounded-md border px-3 py-2" name="notes"></textarea></label>
                </div>
                <div class="mt-5 flex justify-end gap-2 border-t bg-white pt-4" style="position: sticky; bottom: 0; z-index: 2;">
                    <button type="button" id="livrare-cancel" class="box rounded-lg border px-4 py-2">Anuleaza</button>
                    <button type="submit" class="box rounded-lg border bg-primary px-4 py-2 text-white">Salveaza</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
  'use strict';
  const ENDPOINT = '/admin/api/livrare_endpoint.php';
  const COMENZI_ENDPOINT = '/admin/api/comenzi_endpoint.php';
  const tableBody = document.getElementById('livrare-table-body');
  const form = document.getElementById('livrare-form');
  const modal = document.getElementById('livrare-modal');
  const toast = document.getElementById('livrare-toast');
  const counter = document.getElementById('livrare-counter');
  const orderSelect = document.getElementById('livrare-order-select');
  const filters = { search: document.getElementById('livrare-search'), status: document.getElementById('livrare-status-filter'), courier: document.getElementById('livrare-courier-filter') };
  let livrari = [];
  let comenzi = [];
  let listMeta = { page: 1, total: 0, per_page: 10, total_pages: 1 };
  let currentPage = 1;
  const paginationEl = document.getElementById('livrare-pagination');
  async function apiCall(endpoint, actionType, payload) {
    const response = await fetch(endpoint, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ type_product: actionType, ...payload }) });
    const rawText = await response.text();
    let result;
    try { result = JSON.parse(rawText); } catch (error) { throw new Error('Endpoint-ul nu a returnat JSON valid.'); }
    if (!response.ok || !result.success) throw new Error(result.message || 'Eroare necunoscuta');
    return result.data;
  }
  function escapeHtml(value) { return String(value ?? '').replace(/[&<>"']/g, (char) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char])); }
  function showToast(message, isError) { if (!toast) return; toast.textContent = message; toast.classList.remove('hidden'); toast.classList.toggle('text-danger', Boolean(isError)); setTimeout(() => toast.classList.add('hidden'), 3000); }
  function formToObject(formElement) { const payload = {}; new FormData(formElement).forEach((value, key) => { if (String(value).trim() !== '') payload[key] = value; }); return payload; }
  function filteredDeliveries() { return livrari; }
  function statusClass(status) { if (status === 'livrat') return 'text-success'; if (['retur','anulat'].includes(status)) return 'text-danger'; return 'text-warning'; }
  function renderDeliveries() {
    const visible = filteredDeliveries();
    if (counter) counter.textContent = `${listMeta.total} livrari`;
    if (!tableBody) return;
    if (!visible.length) { tableBody.innerHTML = '<tr><td colspan="8" class="box bg-background p-6 text-center opacity-70">Nu exista livrari.</td></tr>'; if (window.BpaPagination) BpaPagination.render(paginationEl, listMeta, (p) => loadDeliveries(p)); return; }
    tableBody.innerHTML = visible.map((delivery) => `<tr data-livrare-row data-id="${escapeHtml(delivery.randomn_id)}"><td class="box rounded-none border-y border-foreground/10 bg-background p-4 first:rounded-l-xl first:border-l last:rounded-r-xl last:border-r"><a class="whitespace-nowrap underline decoration-dotted" href="#">${escapeHtml(delivery.awb || '-')}</a><div class="mt-0.5 text-xs opacity-70">${escapeHtml(delivery.delivery_title || '')}</div></td><td class="box rounded-none border-y border-foreground/10 bg-background p-4"><div class="font-medium">${escapeHtml(delivery.client_name || '-')}</div><div class="mt-0.5 text-xs opacity-70">${escapeHtml(delivery.phone || '-')}</div><div class="mt-0.5 text-xs opacity-70">${escapeHtml(delivery.address || '-')}</div></td><td class="box rounded-none border-y border-foreground/10 bg-background p-4"><div class="font-medium text-primary">${escapeHtml(delivery.order_number || '-')}</div></td><td class="box rounded-none border-y border-foreground/10 bg-background p-4"><div class="font-medium">${escapeHtml(delivery.courier || '-')}</div><div class="mt-0.5 text-xs opacity-70">${escapeHtml(delivery.service_type || '')}</div></td><td class="box rounded-none border-y border-foreground/10 bg-background p-4 text-center"><div class="${statusClass(delivery.delivery_status)}">${escapeHtml(delivery.delivery_status || 'pregatire')}</div></td><td class="box rounded-none border-y border-foreground/10 bg-background p-4"><div>${escapeHtml(delivery.delivery_date || '-')}</div><div class="mt-0.5 text-xs opacity-70">${escapeHtml(delivery.delivery_time || '')}</div></td><td class="box rounded-none border-y border-foreground/10 bg-background p-4 text-right">${escapeHtml(delivery.total_amount || '0.00')} RON</td><td class="box rounded-none border-y border-foreground/10 bg-background p-4"><div class="flex items-center justify-center gap-3"><button type="button" data-action="edit" data-delivery='${escapeHtml(JSON.stringify(delivery))}' class="text-primary">Edit</button><button type="button" data-action="setstatus" data-id="${escapeHtml(delivery.randomn_id)}" data-status="livrat" class="text-success">Livrat</button><button type="button" data-action="delete" data-id="${escapeHtml(delivery.randomn_id)}" class="text-danger">Delete</button></div></td></tr>`).join('');
    if (window.lucide) window.lucide.createIcons();
    if (window.BpaPagination) BpaPagination.render(paginationEl, listMeta, (p) => loadDeliveries(p));
  }
  function openModal(delivery) { form.reset(); if (orderSelect) orderSelect.value = ''; form.dataset.action = delivery ? 'edit' : 'add'; document.getElementById('livrare-modal-title').textContent = delivery ? 'Editeaza livrare' : 'Livrare noua'; if (delivery) Object.entries(delivery).forEach(([key, value]) => { const field = form.elements.namedItem(key); if (field) field.value = value ?? ''; }); modal.classList.remove('hidden'); }
  function closeModal() { modal.classList.add('hidden'); }
  async function loadDeliveries(page) {
    if (page) currentPage = page;
    const payload = {
      page: currentPage,
      per_page: 10,
      q: (filters.search?.value || '').trim(),
      delivery_status: filters.status?.value || undefined,
      courier: filters.courier?.value || undefined,
    };
    const data = await apiCall(ENDPOINT, 'list', payload);
    const parsed = window.BpaPagination ? BpaPagination.unwrapList(data) : { items: data, total: data.length, page: 1, per_page: 10, total_pages: 1 };
    livrari = parsed.items;
    listMeta = parsed;
    currentPage = parsed.page;
    renderDeliveries();
  }
  async function loadOrders() { comenzi = await apiCall(COMENZI_ENDPOINT, 'list', {}); if (!orderSelect) return; orderSelect.innerHTML = '<option value="">Selecteaza comanda</option>' + comenzi.map((order) => `<option value="${escapeHtml(order.randomn_id)}">${escapeHtml((order.order_number || ('ORD-' + order.randomn_id)) + ' - ' + (order.client_name || 'Client') + ' - ' + (order.product_name || '') + ' - ' + (order.total_amount || '0.00') + ' RON')}</option>`).join(''); }
  function fillFromOrder(randomId) { const order = comenzi.find((item) => Number(item.randomn_id) === Number(randomId)); if (!order || !form) return; const fields = form.elements; if (fields.delivery_title) fields.delivery_title.value = `Livrare ${order.order_number || ('ORD-' + order.randomn_id)}`; if (fields.order_number) fields.order_number.value = order.order_number || `ORD-${order.randomn_id}`; if (fields.client_name) fields.client_name.value = order.client_name || ''; if (fields.phone) fields.phone.value = order.phone || ''; if (fields.email) fields.email.value = order.email || ''; if (fields.total_amount) fields.total_amount.value = order.total_amount || '0.00'; if (fields.delivery_status) fields.delivery_status.value = 'pregatire'; }
  function exportExcel() { const rows = filteredDeliveries(); if (!rows.length) { showToast('Nu exista livrari pentru export.', true); return; } const headers = ['AWB','Comanda','Client','Telefon','Adresa','Curier','Status','Data','Total']; const body = rows.map((d) => [d.awb,d.order_number,d.client_name,d.phone,d.address,d.courier,d.delivery_status,d.delivery_date,d.total_amount]); const html = `<html><head><meta charset="utf-8"></head><body><table border="1"><thead><tr>${headers.map((h)=>`<th>${escapeHtml(h)}</th>`).join('')}</tr></thead><tbody>${body.map((r)=>`<tr>${r.map((c)=>`<td>${escapeHtml(c)}</td>`).join('')}</tr>`).join('')}</tbody></table></body></html>`; const url = URL.createObjectURL(new Blob([html], { type: 'application/vnd.ms-excel;charset=utf-8;' })); const link = document.createElement('a'); link.href = url; link.download = `livrari-export-${new Date().toISOString().slice(0, 10)}.xls`; document.body.appendChild(link); link.click(); link.remove(); URL.revokeObjectURL(url); }
  function printAwb() {
    const rows = filteredDeliveries();
    if (!rows.length) { showToast('Nu exista AWB-uri pentru print.', true); return; }
    const printWindow = window.open('', '_blank', 'width=900,height=700');
    if (!printWindow) { showToast('Browserul a blocat fereastra de print.', true); return; }
    const cards = rows.map((delivery) => `<section class="awb-card"><div class="awb-head"><strong>${escapeHtml(delivery.awb || 'AWB negenerat')}</strong><span>${escapeHtml(delivery.courier || '')}</span></div><div class="awb-row"><b>Comanda:</b> ${escapeHtml(delivery.order_number || '-')}</div><div class="awb-row"><b>Client:</b> ${escapeHtml(delivery.client_name || '-')}</div><div class="awb-row"><b>Telefon:</b> ${escapeHtml(delivery.phone || '-')}</div><div class="awb-row"><b>Adresa:</b> ${escapeHtml(delivery.address || '-')}</div><div class="awb-row"><b>Status:</b> ${escapeHtml(delivery.delivery_status || '-')}</div><div class="awb-row"><b>Total:</b> ${escapeHtml(delivery.total_amount || '0.00')} RON</div></section>`).join('');
    printWindow.document.write(`<html><head><meta charset="utf-8"><title>Print AWB</title><style>body{font-family:Arial,sans-serif;margin:24px;color:#111}.awb-card{border:1px solid #222;border-radius:8px;padding:18px;margin:0 0 18px;page-break-inside:avoid}.awb-head{display:flex;justify-content:space-between;border-bottom:1px solid #ddd;padding-bottom:10px;margin-bottom:12px;font-size:18px}.awb-row{margin:7px 0}@media print{button{display:none}.awb-card{break-inside:avoid}}</style></head><body>${cards}<script>window.onload=function(){window.print();};<\/script></body></html>`);
    printWindow.document.close();
  }
  document.getElementById('livrare-open-create')?.addEventListener('click', () => openModal(null)); document.getElementById('livrare-export-excel')?.addEventListener('click', exportExcel); document.getElementById('livrare-print-awb')?.addEventListener('click', printAwb); document.getElementById('livrare-close-modal')?.addEventListener('click', closeModal); document.getElementById('livrare-cancel')?.addEventListener('click', closeModal); orderSelect?.addEventListener('change', () => fillFromOrder(orderSelect.value));
  let filterTimer;
  const reloadDeliveries = () => { currentPage = 1; loadDeliveries().catch((e) => showToast(e.message, true)); };
  Object.values(filters).forEach((filter) => filter?.addEventListener('input', () => { clearTimeout(filterTimer); filterTimer = setTimeout(reloadDeliveries, 300); }));
  Object.values(filters).forEach((filter) => filter?.addEventListener('change', reloadDeliveries));
  form?.addEventListener('submit', async (event) => { event.preventDefault(); try { await apiCall(ENDPOINT, form.dataset.action || 'add', formToObject(form)); closeModal(); showToast('Livrare salvata.', false); await loadDeliveries(); } catch (error) { showToast(error.message, true); } });
  tableBody?.addEventListener('click', async (event) => { const button = event.target.closest('[data-action]'); if (!button) return; try { if (button.dataset.action === 'edit') { openModal(JSON.parse(button.dataset.delivery || '{}')); return; } if (button.dataset.action === 'delete') { if (!confirm('Confirmi stergerea livrarii?')) return; await apiCall(ENDPOINT, 'delete', { randomn_id: Number(button.dataset.id) }); showToast('Livrare stearsa.', false); await loadDeliveries(); return; } if (button.dataset.action === 'setstatus') { await apiCall(ENDPOINT, 'setstatus', { randomn_id: Number(button.dataset.id), delivery_status: button.dataset.status }); showToast('Status actualizat.', false); await loadDeliveries(); } } catch (error) { showToast(error.message, true); } });
  loadOrders().catch((error) => showToast(error.message, true)); loadDeliveries().catch((error) => showToast(error.message, true));
})();
</script>
