<div>
    <div id="order-create-toast" class="hidden fixed right-5 top-5 z-50 rounded-md border bg-background px-4 py-3 text-sm shadow"></div>

    <div class="mt-10 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-lg font-medium">Comandă nouă (internă / externă)</h2>
            <p class="mt-1 text-sm opacity-70">Adaugă produse manual în tmp sau importă din coș furnizori, apoi salvează în caietul legacy.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="/admin/supplier-cart" class="box inline-flex h-10 items-center rounded-md border px-4 text-sm">Coș furnizori</a>
            <a href="/admin/orders" class="box inline-flex h-10 items-center rounded-md border px-4 text-sm">Lista comenzi</a>
        </div>
    </div>

    <div class="mt-5 box p-5">
        <div class="grid grid-cols-12 gap-4">
            <div class="col-span-12 md:col-span-6">
                <label class="mb-1 block text-sm">Client ERP</label>
                <input id="order-create-client-search" list="order-create-clients" class="box h-10 w-full rounded-md border px-3" placeholder="Caută client după nume / telefon">
                <datalist id="order-create-clients"></datalist>
                <input type="hidden" id="order-create-client-id">
                <div id="order-create-client-meta" class="mt-1 text-xs opacity-60"></div>
            </div>
            <div class="col-span-12 md:col-span-3">
                <label class="mb-1 block text-sm">Data</label>
                <input type="date" id="order-create-date" class="box h-10 w-full rounded-md border px-3">
            </div>
            <div class="col-span-12 md:col-span-3">
                <label class="mb-1 block text-sm">Stare</label>
                <select id="order-create-status" class="box h-10 w-full rounded-md border px-3"></select>
            </div>
            <div class="col-span-12 md:col-span-4">
                <label class="mb-1 block text-sm">Marcă mașină</label>
                <input id="order-create-marca" class="box h-10 w-full rounded-md border px-3" placeholder="Opțional">
            </div>
            <div class="col-span-12 md:col-span-4">
                <label class="mb-1 block text-sm">Destinație</label>
                <select id="order-create-location" class="box h-10 w-full rounded-md border px-3"></select>
            </div>
            <div class="col-span-12 md:col-span-4">
                <label class="mb-1 block text-sm">Cont AWB</label>
                <input id="order-create-awb" class="box h-10 w-full rounded-md border px-3" placeholder="Opțional">
            </div>
            <div class="col-span-12">
                <label class="mb-1 block text-sm">Observații</label>
                <textarea id="order-create-observations" class="box min-h-[80px] w-full rounded-md border px-3 py-2"></textarea>
            </div>
        </div>
    </div>

    <div class="mt-5 overflow-auto box p-5">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
            <h3 class="font-medium">Produse în tmp</h3>
            <div class="flex gap-2">
                <button type="button" id="order-create-add-product" class="box h-9 rounded-md border px-3 text-sm">+ Adaugă produs ERP</button>
                <div id="order-create-tmp-summary" class="flex h-9 items-center text-sm opacity-70">Se încarcă...</div>
            </div>
        </div>
        <table class="w-full text-sm">
            <thead class="border-b bg-foreground/5">
            <tr>
                <th class="px-3 py-2 text-left">Cod</th>
                <th class="px-3 py-2 text-left">Denumire</th>
                <th class="px-3 py-2 text-center">Qty</th>
                <th class="px-3 py-2 text-right">Preț</th>
                <th class="px-3 py-2 text-left">Furnizor</th>
                <th class="px-3 py-2 text-right">Acțiuni</th>
            </tr>
            </thead>
            <tbody id="order-create-tmp-rows">
            <tr><td colspan="6" class="px-3 py-6 text-center opacity-70">Se încarcă...</td></tr>
            </tbody>
        </table>
    </div>

    <div class="mt-5 flex justify-end gap-2">
        <button type="button" id="order-create-submit" class="box h-10 rounded-md border bg-primary px-5 text-white">Salvează comandă</button>
    </div>

    <div id="order-create-product-modal" class="hidden fixed inset-0 z-[9999] flex items-center justify-center bg-black/40 p-4">
        <div class="box w-full max-w-2xl rounded-lg bg-background p-5 shadow-xl">
            <div class="mb-4 flex items-center justify-between">
                <h3 class="font-medium">Picker produse ERP</h3>
                <button type="button" id="order-create-product-close" class="rounded border px-3 py-1 text-sm">Închide</button>
            </div>
            <input id="order-create-product-search" class="box mb-3 h-10 w-full rounded-md border px-3" placeholder="Cod sau denumire produs...">
            <div class="max-h-80 overflow-auto">
                <table class="w-full text-sm">
                    <thead class="border-b"><tr><th class="px-2 py-1 text-left">Cod</th><th class="px-2 py-1 text-left">Denumire</th><th class="px-2 py-1 text-right">Preț</th><th></th></tr></thead>
                    <tbody id="order-create-product-results"><tr><td colspan="4" class="py-4 text-center opacity-60">Caută un produs...</td></tr></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';

    const TMP_ENDPOINT = '/admin/api/order_tmp_endpoint.php';
    const ORDER_ENDPOINT = '/admin/api/legacy_orders_endpoint.php';
    const CLIENTS_ENDPOINT = '/admin/api/caiet_comenzi_endpoint.php';
    const toast = document.getElementById('order-create-toast');
    const clientsMap = new Map();
    let productSearchTimer = null;

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

    async function loadMeta() {
        const response = await fetch(ORDER_ENDPOINT + '?action=meta');
        const data = await response.json();
        if (!response.ok || !data.success) return;

        const statusSelect = document.getElementById('order-create-status');
        const locationSelect = document.getElementById('order-create-location');
        statusSelect.innerHTML = (data.statuses || []).map((item) =>
            `<option value="${escapeHtml(item.value)}">${escapeHtml(item.label)}</option>`
        ).join('');
        locationSelect.innerHTML = (data.locations || []).map((item) =>
            `<option value="${escapeHtml(item.value)}">${escapeHtml(item.label)}</option>`
        ).join('');

        const params = new URLSearchParams(window.location.search);
        if (params.get('location') === 'utvin') locationSelect.value = '2';
        if (params.get('type') === 'extern') locationSelect.value = '3';
    }

    async function loadClients() {
        const response = await fetch(CLIENTS_ENDPOINT, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ type_product: 'clienti_list', limit: 200 })
        });
        const data = await response.json();
        if (!response.ok || !data.success) return;

        const datalist = document.getElementById('order-create-clients');
        datalist.innerHTML = '';
        clientsMap.clear();

        (data.data || []).forEach((client) => {
            const label = [client.nume, client.telefon, client.adresa].filter(Boolean).join(' · ');
            clientsMap.set(label, client);
            const option = document.createElement('option');
            option.value = label;
            datalist.appendChild(option);
        });
    }

    async function loadTmp() {
        const tbody = document.getElementById('order-create-tmp-rows');
        const summary = document.getElementById('order-create-tmp-summary');
        try {
            const response = await fetch(TMP_ENDPOINT + '?action=list');
            const data = await response.json();
            if (!response.ok || !data.success) {
                throw new Error(data.message || 'Nu s-a putut încărca tmp.');
            }

            const rows = (data.products || []).map((item) => `
                <tr class="border-b" data-id-tmp="${escapeHtml(item.id_tmp)}">
                    <td class="px-3 py-2">${escapeHtml(item.cod_produs || item.id_produs || '')}</td>
                    <td class="px-3 py-2">${escapeHtml(item.ProductName || '-')}</td>
                    <td class="px-3 py-2 text-center">
                        <input type="number" min="1" class="tmp-qty box h-8 w-16 rounded border px-2 text-center" value="${escapeHtml(item.cantitate_tmp || 1)}">
                    </td>
                    <td class="px-3 py-2 text-right">
                        <input type="number" min="0" step="0.01" class="tmp-price box h-8 w-24 rounded border px-2 text-right" value="${escapeHtml(item.pret_tmp || 0)}">
                    </td>
                    <td class="px-3 py-2">${escapeHtml(item.furnizor || '-')}</td>
                    <td class="px-3 py-2 text-right">
                        <button type="button" class="tmp-save box rounded border px-2 py-1 text-xs">Salvează</button>
                        <button type="button" class="tmp-delete box rounded border px-2 py-1 text-xs text-danger">Șterge</button>
                    </td>
                </tr>
            `);

            tbody.innerHTML = rows.length
                ? rows.join('')
                : '<tr><td colspan="6" class="px-3 py-6 text-center opacity-70">Coșul tmp este gol. Adaugă produse ERP sau importă din <a class="underline" href="/admin/supplier-cart">coșul furnizori</a>.</td></tr>';

            if (summary) {
                summary.textContent = `${data.products?.length || 0} linii · total ${formatMoney(data.total || 0)}`;
            }
        } catch (error) {
            tbody.innerHTML = `<tr><td colspan="6" class="px-3 py-6 text-center text-danger">${escapeHtml(error.message)}</td></tr>`;
            if (summary) summary.textContent = '';
        }
    }

    document.getElementById('order-create-client-search')?.addEventListener('change', (event) => {
        const client = clientsMap.get(event.target.value);
        const hidden = document.getElementById('order-create-client-id');
        const meta = document.getElementById('order-create-client-meta');
        if (!client || !hidden) return;
        hidden.value = String(client.idclienti || '');
        if (meta) {
            meta.textContent = [client.telefon, client.adresa, client.companie].filter(Boolean).join(' · ');
        }
    });

    document.getElementById('order-create-tmp-rows')?.addEventListener('click', async (event) => {
        const row = event.target.closest('tr[data-id-tmp]');
        if (!row) return;
        const idTmp = parseInt(row.dataset.idTmp || '0', 10);

        try {
            if (event.target.classList.contains('tmp-delete')) {
                const response = await fetch(TMP_ENDPOINT + '?action=delete', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id_tmp: idTmp })
                });
                const data = await response.json();
                if (!response.ok || !data.success) throw new Error(data.message);
                showToast('Linie ștearsă din tmp.');
                await loadTmp();
                return;
            }
            if (event.target.classList.contains('tmp-save')) {
                const response = await fetch(TMP_ENDPOINT + '?action=update', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        id_tmp: idTmp,
                        cantitate: parseInt(row.querySelector('.tmp-qty')?.value || '1', 10),
                        pret: parseFloat(row.querySelector('.tmp-price')?.value || '0'),
                    })
                });
                const data = await response.json();
                if (!response.ok || !data.success) throw new Error(data.message);
                showToast('Linie tmp actualizată.');
                await loadTmp();
            }
        } catch (error) {
            showToast(error.message, true);
        }
    });

    document.getElementById('order-create-submit')?.addEventListener('click', async () => {
        const clientId = parseInt(document.getElementById('order-create-client-id')?.value || '0', 10);
        if (!clientId) {
            showToast('Selectează un client ERP.', true);
            return;
        }

        const location = parseInt(document.getElementById('order-create-location')?.value || '1', 10);
        const isExternal = location === 3;
        const payload = {
            id_client: clientId,
            data: document.getElementById('order-create-date')?.value || '',
            idstare: parseInt(document.getElementById('order-create-status')?.value || '1', 10),
            locatie_mgz: location,
            marca: document.getElementById('order-create-marca')?.value || '',
            cont_awb: document.getElementById('order-create-awb')?.value || '',
            observations: document.getElementById('order-create-observations')?.value || '',
        };

        const button = document.getElementById('order-create-submit');
        if (button) {
            button.disabled = true;
            button.textContent = 'Se salvează...';
        }

        try {
            const action = isExternal ? 'create_external' : 'create_internal';
            const response = await fetch(ORDER_ENDPOINT + '?action=' + action, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await response.json();
            if (!response.ok || !data.success) {
                throw new Error(data.message || 'Salvarea comenzii a eșuat.');
            }

            const order = data.order || {};
            showToast(`Comandă #${order.idcomanda} creată (${formatMoney(order.total || 0)}).`);
            setTimeout(() => {
                window.location.href = '/admin/orders?legacy_tab=' + encodeURIComponent(order.redirect_tab || 'tm');
            }, 900);
        } catch (error) {
            showToast(error.message, true);
        } finally {
            if (button) {
                button.disabled = false;
                button.textContent = 'Salvează comandă';
            }
        }
    });

    const productModal = document.getElementById('order-create-product-modal');
    const productSearch = document.getElementById('order-create-product-search');

    document.getElementById('order-create-add-product')?.addEventListener('click', () => {
        productModal?.classList.remove('hidden');
        productSearch?.focus();
    });
    document.getElementById('order-create-product-close')?.addEventListener('click', () => productModal?.classList.add('hidden'));

    async function searchProducts(query) {
        const tbody = document.getElementById('order-create-product-results');
        const response = await fetch(ORDER_ENDPOINT + '?action=products&q=' + encodeURIComponent(query) + '&limit=40');
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
                        data-price="${escapeHtml(product.pret || 0)}">Adaugă în tmp</button>
                </td>
            </tr>
        `);
        tbody.innerHTML = rows.length ? rows.join('') : '<tr><td colspan="4" class="py-4 text-center opacity-60">Niciun rezultat.</td></tr>';
    }

    productSearch?.addEventListener('input', () => {
        clearTimeout(productSearchTimer);
        productSearchTimer = setTimeout(() => searchProducts(productSearch.value.trim()), 300);
    });

    document.getElementById('order-create-product-results')?.addEventListener('click', async (event) => {
        const btn = event.target.closest('.pick-product');
        if (!btn) return;
        try {
            const response = await fetch(TMP_ENDPOINT + '?action=add', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    id_produs: parseInt(btn.dataset.id || '0', 10),
                    cantitate: 1,
                    pret: parseFloat(btn.dataset.price || '0'),
                    furnizor: '__',
                })
            });
            const data = await response.json();
            if (!response.ok || !data.success) throw new Error(data.message);
            showToast('Produs adăugat în tmp.');
            productModal?.classList.add('hidden');
            await loadTmp();
        } catch (error) {
            showToast(error.message, true);
        }
    });

    const dateInput = document.getElementById('order-create-date');
    if (dateInput) {
        dateInput.value = new Date().toISOString().slice(0, 10);
    }

    loadMeta().catch((e) => showToast(e.message, true));
    loadClients().catch(() => {});
    BpaAsync.defer(loadTmp);
})();
</script>
