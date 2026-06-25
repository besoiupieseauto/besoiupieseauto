<div>
    <div id="order-edit-toast" class="hidden fixed right-5 top-5 z-50 rounded-md border bg-background px-4 py-3 text-sm shadow"></div>

    <div class="mt-10 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h2 class="text-lg font-medium">Editare comandă <span id="order-edit-title-id" class="opacity-70"></span></h2>
            <p class="mt-1 text-sm opacity-70">Modifică antetul și liniile detaliu (internă / externă legacy ERP).</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="/admin/orders" class="box inline-flex h-10 items-center rounded-md border px-4 text-sm">← Lista comenzi</a>
            <a href="/admin/order-create" class="box inline-flex h-10 items-center rounded-md border px-4 text-sm">Comandă nouă</a>
        </div>
    </div>

    <div class="mt-5 grid grid-cols-12 gap-4">
        <aside id="order-edit-shortcuts" class="col-span-12 lg:col-span-3 box p-4 hidden">
            <p class="mb-3 text-xs font-medium uppercase tracking-wide opacity-60">Acțiuni rapide</p>
            <div class="flex flex-col gap-2">
                <a id="order-edit-link-client" href="/admin/clienti" class="box inline-flex h-10 items-center rounded-md border px-3 text-sm">👤 Deschide client</a>
                <a id="order-edit-link-facturi" href="/admin/facturi" class="box inline-flex h-10 items-center rounded-md border px-3 text-sm">🧾 Factură</a>
                <a id="order-edit-link-livrare" href="/admin/livrare" class="box inline-flex h-10 items-center rounded-md border px-3 text-sm">🚚 AWB / Livrare</a>
                <a id="order-edit-link-caiet" href="/admin/orders?legacy_tab=tm" class="box inline-flex h-10 items-center rounded-md border px-3 text-sm">📒 Comenzi legacy</a>
                <a id="order-edit-link-whatsapp" href="#" target="_blank" rel="noopener" class="box inline-flex h-10 items-center rounded-md border px-3 text-sm text-primary">💬 WhatsApp client</a>
            </div>
        </aside>

        <div id="order-edit-main" class="col-span-12 lg:col-span-9">
    <div id="order-edit-panel">
        <div class="mt-5 box p-5">
            <div class="grid grid-cols-12 gap-4">
                <div class="col-span-12 md:col-span-4">
                    <label class="mb-1 block text-sm">Client</label>
                    <div id="order-edit-client" class="text-sm font-medium">—</div>
                    <div id="order-edit-client-meta" class="text-xs opacity-60"></div>
                </div>
                <div class="col-span-12 md:col-span-2">
                    <label class="mb-1 block text-sm">Data</label>
                    <input type="date" id="order-edit-date" class="box h-10 w-full rounded-md border px-3">
                </div>
                <div class="col-span-12 md:col-span-2">
                    <label class="mb-1 block text-sm">Stare</label>
                    <select id="order-edit-status" class="box h-10 w-full rounded-md border px-3"></select>
                </div>
                <div class="col-span-12 md:col-span-2">
                    <label class="mb-1 block text-sm">Total</label>
                    <div id="order-edit-total" class="box flex h-10 items-center rounded-md border px-3 font-medium">0.00 RON</div>
                </div>
                <div class="col-span-12 md:col-span-2">
                    <label class="mb-1 block text-sm">Tip</label>
                    <div id="order-edit-source" class="box flex h-10 items-center rounded-md border px-3 text-sm">—</div>
                </div>
                <div class="col-span-12 md:col-span-4">
                    <label class="mb-1 block text-sm">Cont AWB</label>
                    <input id="order-edit-awb" class="box h-10 w-full rounded-md border px-3">
                </div>
                <div class="col-span-12">
                    <label class="mb-1 block text-sm">Observații</label>
                    <textarea id="order-edit-observations" class="box min-h-[80px] w-full rounded-md border px-3 py-2"></textarea>
                </div>
            </div>
            <div class="mt-4 flex justify-end">
                <button type="button" id="order-edit-save-header" class="box h-10 rounded-md border bg-primary px-5 text-white">Salvează antet</button>
            </div>
        </div>

        <div class="mt-5 overflow-auto box p-5">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                <h3 class="font-medium">Linii detaliu</h3>
                <button type="button" id="order-edit-add-line" class="box h-9 rounded-md border px-3 text-sm">+ Adaugă produs ERP</button>
            </div>
            <table class="w-full text-sm">
                <thead class="border-b bg-foreground/5">
                <tr>
                    <th class="px-3 py-2 text-left">ID</th>
                    <th class="px-3 py-2 text-left">Cod / Denumire</th>
                    <th class="px-3 py-2 text-center">Qty</th>
                    <th class="px-3 py-2 text-right">Preț</th>
                    <th class="px-3 py-2 text-left">Furnizor</th>
                    <th class="px-3 py-2 text-right">Acțiuni</th>
                </tr>
                </thead>
                <tbody id="order-edit-lines"></tbody>
            </table>
        </div>
    </div>
        </div>
    </div>

    <div id="order-edit-product-modal" class="hidden fixed inset-0 z-[9999] flex items-center justify-center bg-black/40 p-4">
        <div class="box w-full max-w-2xl rounded-lg bg-background p-5 shadow-xl">
            <div class="mb-4 flex items-center justify-between">
                <h3 class="font-medium">Caută produs ERP</h3>
                <button type="button" id="order-edit-product-close" class="rounded border px-3 py-1 text-sm">Închide</button>
            </div>
            <input id="order-edit-product-search" class="box mb-3 h-10 w-full rounded-md border px-3" placeholder="Cod sau denumire...">
            <div class="max-h-80 overflow-auto">
                <table class="w-full text-sm">
                    <thead class="border-b"><tr><th class="px-2 py-1 text-left">Cod</th><th class="px-2 py-1 text-left">Denumire</th><th class="px-2 py-1 text-right">Preț</th><th></th></tr></thead>
                    <tbody id="order-edit-product-results"><tr><td colspan="4" class="py-4 text-center opacity-60">Caută un produs...</td></tr></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';

    const ENDPOINT = '/admin/api/legacy_orders_endpoint.php';
    const params = new URLSearchParams(window.location.search);
    const orderId = parseInt(params.get('id') || params.get('order_id') || '0', 10);
    const sourceType = params.get('source') || params.get('source_type') || 'interna';
    const toast = document.getElementById('order-edit-toast');
    let orderData = null;

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, (char) => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
        }[char]));
    }

    function showToast(message, isError) {
        if (!toast) return;
        toast.textContent = message;
        toast.classList.remove('hidden');
        toast.classList.toggle('text-danger', Boolean(isError));
        setTimeout(() => toast.classList.add('hidden'), 5000);
    }

    function formatMoney(value) {
        return Number(value || 0).toLocaleString('ro-RO', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' RON';
    }

    async function apiPost(action, payload) {
        const response = await fetch(ENDPOINT + '?action=' + encodeURIComponent(action), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ ...payload, source_type: sourceType, order_id: orderId })
        });
        const data = await response.json();
        if (!response.ok || !data.success) {
            throw new Error(data.message || 'Eroare API.');
        }
        return data;
    }

    function renderLines(lines) {
        const tbody = document.getElementById('order-edit-lines');
        if (!tbody) return;
        if (!lines.length) {
            tbody.innerHTML = '<tr><td colspan="6" class="px-3 py-6 text-center opacity-70">Fără linii.</td></tr>';
            return;
        }
        tbody.innerHTML = lines.map((line) => `
            <tr class="border-b" data-line-id="${escapeHtml(line.iddetaliu)}">
                <td class="px-3 py-2">${escapeHtml(line.iddetaliu)}</td>
                <td class="px-3 py-2">
                    <div class="font-medium">${escapeHtml(line.cod_produs || line.idprodus)}</div>
                    <div class="text-xs opacity-60">${escapeHtml(line.product_name || '')}</div>
                </td>
                <td class="px-3 py-2 text-center">
                    <input type="number" min="1" class="line-qty box h-8 w-16 rounded border px-2 text-center" value="${escapeHtml(line.cantitate || 1)}">
                </td>
                <td class="px-3 py-2 text-right">
                    <input type="number" min="0" step="0.01" class="line-price box h-8 w-24 rounded border px-2 text-right" value="${escapeHtml(line.pret || 0)}">
                </td>
                <td class="px-3 py-2">
                    <input type="text" class="line-furnizor box h-8 w-24 rounded border px-2" value="${escapeHtml(line.furnizor || '__')}">
                </td>
                <td class="px-3 py-2 text-right">
                    <button type="button" class="line-save box rounded border px-2 py-1 text-xs">Salvează</button>
                    <button type="button" class="line-delete box rounded border px-2 py-1 text-xs text-danger">Șterge</button>
                </td>
            </tr>
        `).join('');
    }

    function fillHeader(data) {
        const header = data.header || {};
        document.getElementById('order-edit-title-id').textContent = '#' + orderId;
        document.getElementById('order-edit-client').textContent = header.client_name || ('Client #' + (header.idclient || ''));
        document.getElementById('order-edit-client-meta').textContent = [header.client_phone, header.client_address].filter(Boolean).join(' · ');
        document.getElementById('order-edit-date').value = String(header.data || '').slice(0, 10);
        document.getElementById('order-edit-status').value = String(header.stare || 1);
        document.getElementById('order-edit-awb').value = header.cont_awb || '';
        document.getElementById('order-edit-observations').value = header.observations || '';
        document.getElementById('order-edit-total').textContent = formatMoney(data.calculated_total || header.total || 0);
        document.getElementById('order-edit-source').textContent = data.source_type === 'externa' ? 'Externă' : 'Internă';
        renderLines(data.lines || []);
        updateOrderEditShortcuts(header);
    }

    function updateOrderEditShortcuts(header) {
        const panel = document.getElementById('order-edit-shortcuts');
        const main = document.getElementById('order-edit-main');
        if (!panel) return;
        panel.classList.remove('hidden');
        if (main) main.classList.remove('lg:col-span-12');

        const clientId = header.idclient || header.id_client || '';
        const phone = String(header.client_phone || '').trim();
        const clientLink = document.getElementById('order-edit-link-client');
        if (clientLink) {
            clientLink.href = clientId ? ('/admin/clienti?id=' + encodeURIComponent(clientId)) : ('/admin/clienti?q=' + encodeURIComponent(phone || header.client_name || ''));
        }
        const wa = document.getElementById('order-edit-link-whatsapp');
        if (wa && phone) {
            const digits = phone.replace(/\D+/g, '');
            wa.href = digits ? ('https://wa.me/4' + digits.replace(/^0/, '') + '?text=' + encodeURIComponent('Buna ziua, referitor la comanda #' + orderId + '.')) : '#';
            wa.classList.toggle('hidden', !digits);
        }
    }

    async function loadOrder() {
        if (!orderId) {
            showToast('ID comandă lipsă din URL (?id=).', true);
            BpaAsync.slot('order-edit-lines', { error: 'ID comandă lipsă.' });
            return;
        }
        const tbody = document.getElementById('order-edit-lines');
        if (tbody) tbody.innerHTML = BpaAsync.skeletonRows(6, 3);
        try {
            const result = await BpaAsync.fetchJson(ENDPOINT + '?action=get&order_id=' + orderId + '&source_type=' + encodeURIComponent(sourceType));
            orderData = result.data;
            fillHeader(orderData);
        } catch (error) {
            showToast(error.message, true);
            if (tbody) {
                tbody.innerHTML = '<tr><td colspan="6" class="px-3 py-6 text-center text-danger">' + escapeHtml(error.message) + '</td></tr>';
            }
        }
    }

    async function loadMeta() {
        const response = await fetch(ENDPOINT + '?action=meta');
        const data = await response.json();
        if (!response.ok || !data.success) return;
        const statusSelect = document.getElementById('order-edit-status');
        statusSelect.innerHTML = (data.statuses || []).map((item) =>
            `<option value="${escapeHtml(item.value)}">${escapeHtml(item.label)}</option>`
        ).join('');
    }

    document.getElementById('order-edit-save-header')?.addEventListener('click', async () => {
        try {
            const result = await apiPost('update_header', {
                idstare: parseInt(document.getElementById('order-edit-status')?.value || '1', 10),
                data: document.getElementById('order-edit-date')?.value || '',
                cont_awb: document.getElementById('order-edit-awb')?.value || '',
                observations: document.getElementById('order-edit-observations')?.value || '',
            });
            orderData = result.data;
            fillHeader(orderData);
            showToast('Antet salvat.');
        } catch (error) {
            showToast(error.message, true);
        }
    });

    document.getElementById('order-edit-lines')?.addEventListener('click', async (event) => {
        const row = event.target.closest('tr[data-line-id]');
        if (!row) return;
        const lineId = parseInt(row.dataset.lineId || '0', 10);

        try {
            if (event.target.classList.contains('line-delete')) {
                if (!confirm('Ștergi linia?')) return;
                const result = await apiPost('line_delete', { line_id: lineId });
                orderData = result.data;
                fillHeader(orderData);
                showToast('Linie ștearsă.');
                return;
            }
            if (event.target.classList.contains('line-save')) {
                const result = await apiPost('line_update', {
                    line_id: lineId,
                    cantitate: parseInt(row.querySelector('.line-qty')?.value || '1', 10),
                    pret: parseFloat(row.querySelector('.line-price')?.value || '0'),
                    furnizor: row.querySelector('.line-furnizor')?.value || '__',
                });
                orderData = result.data;
                fillHeader(orderData);
                showToast('Linie actualizată.');
            }
        } catch (error) {
            showToast(error.message, true);
        }
    });

    const productModal = document.getElementById('order-edit-product-modal');
    const productSearch = document.getElementById('order-edit-product-search');
    let searchTimer = null;

    document.getElementById('order-edit-add-line')?.addEventListener('click', () => {
        productModal?.classList.remove('hidden');
        productSearch?.focus();
    });
    document.getElementById('order-edit-product-close')?.addEventListener('click', () => productModal?.classList.add('hidden'));

    async function searchProducts(query) {
        const tbody = document.getElementById('order-edit-product-results');
        const response = await fetch(ENDPOINT + '?action=products&q=' + encodeURIComponent(query) + '&limit=40');
        const data = await response.json();
        if (!response.ok || !data.success) {
            tbody.innerHTML = '<tr><td colspan="4" class="py-4 text-center text-danger">Eroare căutare.</td></tr>';
            return;
        }
        const rows = (data.data || []).map((product) => `
            <tr class="border-b">
                <td class="px-2 py-2">${escapeHtml(product.cod_produs || product.idprodus)}</td>
                <td class="px-2 py-2">${escapeHtml(product.denumire || '')}</td>
                <td class="px-2 py-2 text-right">${escapeHtml(formatMoney(product.pret || 0))}</td>
                <td class="px-2 py-2 text-right">
                    <button type="button" class="pick-product box rounded border px-2 py-1 text-xs"
                        data-id="${escapeHtml(product.idprodus)}"
                        data-price="${escapeHtml(product.pret || 0)}">Adaugă</button>
                </td>
            </tr>
        `);
        tbody.innerHTML = rows.length ? rows.join('') : '<tr><td colspan="4" class="py-4 text-center opacity-60">Niciun rezultat.</td></tr>';
    }

    productSearch?.addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => searchProducts(productSearch.value.trim()), 300);
    });

    document.getElementById('order-edit-product-results')?.addEventListener('click', async (event) => {
        const btn = event.target.closest('.pick-product');
        if (!btn) return;
        try {
            const result = await apiPost('line_add', {
                id_produs: parseInt(btn.dataset.id || '0', 10),
                cantitate: 1,
                pret: parseFloat(btn.dataset.price || '0'),
                furnizor: '__',
            });
            orderData = result.data;
            fillHeader(orderData);
            productModal?.classList.add('hidden');
            showToast('Produs adăugat pe comandă.');
        } catch (error) {
            showToast(error.message, true);
        }
    });

    BpaAsync.defer(() => loadMeta().then(() => loadOrder()).catch((error) => showToast(error.message, true)));
})();
</script>
