<div>

    <div id="supplier-search-toast" class="hidden fixed right-5 top-5 z-50 rounded-md border bg-background px-4 py-3 text-sm shadow"></div>



    <div class="mt-10 flex flex-wrap items-center justify-between gap-3">

        <div>

            <h2 class="text-lg font-medium">Căutare furnizori B2B</h2>

            <p class="mt-1 text-sm opacity-70">Căutare paralelă: Materom, Elit, Auto Partner, Autonet, Autototal.</p>

        </div>

        <a href="/admin/supplier-cart" id="supplier-cart-link" class="box relative inline-flex h-10 items-center rounded-md border px-4 text-sm">

            Coș furnizori

            <span id="supplier-cart-badge" class="ml-2 hidden min-w-[1.25rem] rounded-full bg-primary px-1.5 py-0.5 text-center text-xs text-white">0</span>

        </a>

    </div>



    <div class="mt-5 box p-5">

        <div class="grid grid-cols-12 gap-4">

            <div class="col-span-12 md:col-span-6">

                <label class="mb-1 block text-sm">Cod OE / AM</label>

                <div class="flex gap-2">

                    <input id="supplier-search-code" class="box h-10 w-full rounded-md border bg-background px-3" type="text" placeholder="Ex: 34116761244">

                    <button type="button" id="supplier-search-btn" class="box h-10 rounded-md border bg-primary px-4 text-white">Căutare</button>

                </div>

            </div>

            <div class="col-span-12 md:col-span-6">

                <label class="mb-1 block text-sm">Furnizori conectați</label>

                <div id="supplier-search-checkboxes" class="flex flex-wrap gap-2 text-sm">

                    <span class="opacity-60">Se încarcă furnizorii...</span>

                </div>

            </div>

        </div>

        <div id="supplier-search-meta" class="mt-3 text-xs opacity-60"></div>

    </div>



    <div class="mt-5 overflow-auto">

        <table class="w-full text-sm">

            <thead class="border-b bg-foreground/5">

            <tr>

                <th class="px-3 py-2 text-left">Cod / Produs</th>

                <th class="px-3 py-2 text-left">Furnizor</th>

                <th class="px-3 py-2 text-center">Stoc</th>

                <th class="px-3 py-2 text-right">Preț final</th>

                <th class="px-3 py-2 text-left">Livrare</th>

                <th class="px-3 py-2 text-right">Coș</th>

            </tr>

            </thead>

            <tbody id="supplier-search-results">

            <tr>

                <td colspan="6" class="px-3 py-6 text-center opacity-70">Introdu un cod și apasă „Căutare”.</td>

            </tr>

            </tbody>

        </table>

    </div>



    <div id="supplier-add-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">

        <div class="box w-full max-w-md rounded-lg border bg-background p-5 shadow-lg">

            <h3 class="text-base font-medium">Adaugă în coș furnizori</h3>

            <p id="supplier-add-modal-title" class="mt-2 text-sm opacity-70"></p>

            <div class="mt-4 grid grid-cols-2 gap-3">

                <div>

                    <label class="mb-1 block text-xs">Cantitate</label>

                    <input id="supplier-add-qty" type="number" min="1" value="1" class="box h-10 w-full rounded border px-3">

                </div>

                <div>

                    <label class="mb-1 block text-xs">Preț final (RON)</label>

                    <input id="supplier-add-price" type="number" min="1" step="1" class="box h-10 w-full rounded border px-3">

                </div>

            </div>

            <div class="mt-5 flex justify-end gap-2">

                <button type="button" id="supplier-add-cancel" class="box h-10 rounded border px-4">Anulează</button>

                <button type="button" id="supplier-add-confirm" class="box h-10 rounded border bg-primary px-4 text-white">Adaugă</button>

            </div>

        </div>

    </div>

</div>



<script>

(function () {

    'use strict';



    const SEARCH_ENDPOINT = '/admin/api/supplier_search_endpoint.php';

    const CART_ENDPOINT = '/admin/api/supplier_cart_endpoint.php';

    const button = document.getElementById('supplier-search-btn');

    const input = document.getElementById('supplier-search-code');

    const results = document.getElementById('supplier-search-results');

    const meta = document.getElementById('supplier-search-meta');

    const toast = document.getElementById('supplier-search-toast');

    const cartBadge = document.getElementById('supplier-cart-badge');

    const addModal = document.getElementById('supplier-add-modal');

    const addModalTitle = document.getElementById('supplier-add-modal-title');

    const addQty = document.getElementById('supplier-add-qty');

    const addPrice = document.getElementById('supplier-add-price');

    const addCancel = document.getElementById('supplier-add-cancel');

    const addConfirm = document.getElementById('supplier-add-confirm');

    const checkboxWrap = document.getElementById('supplier-search-checkboxes');



    let lastSearchQuery = '';

    let pendingAddPayload = null;

    let supplierCatalog = [];



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

        setTimeout(() => toast.classList.add('hidden'), 4000);

    }



    function selectedSuppliers() {

        return Array.from(document.querySelectorAll('#supplier-search-checkboxes input[type="checkbox"]:checked'))

            .map((el) => el.value)

            .filter(Boolean);

    }



    function formatMoney(value) {

        const num = Number(value || 0);

        return num.toLocaleString('ro-RO', { minimumFractionDigits: 0, maximumFractionDigits: 0 }) + ' RON';

    }



    function supplierLabel(key) {

        const fromCatalog = supplierCatalog.find((row) => row.key === key);

        if (fromCatalog?.label) {

            return fromCatalog.label;

        }

        return ({

            materom: 'Materom',

            elit: 'Elit',

            autopartner: 'Auto Partner',

            autonet: 'Autonet',

            autototal: 'Autototal'

        })[key] || key;

    }



    function renderSupplierCheckboxes(suppliers) {

        if (!checkboxWrap) return;

        supplierCatalog = Array.isArray(suppliers) ? suppliers : [];

        if (!supplierCatalog.length) {

            checkboxWrap.innerHTML = '<span class="text-danger">Nu s-au putut încărca furnizorii.</span>';

            return;

        }

        const connectedCount = supplierCatalog.filter((row) => row.connected).length;

        checkboxWrap.innerHTML = supplierCatalog.map((row) => {

            const checked = row.connected ? ' checked' : '';

            const disabled = row.connected ? '' : ' disabled';

            const title = row.connected ? '' : escapeHtml(row.reason || 'Neconectat');

            const stateClass = row.connected ? 'border-primary/40 bg-primary/5' : 'opacity-50';

            return `<label class="inline-flex items-center gap-1 rounded border px-2 py-1 ${stateClass}" title="${title}">

                <input type="checkbox" value="${escapeHtml(row.key)}"${checked}${disabled}>

                ${escapeHtml(row.short || '')} ${escapeHtml(row.label || row.key)}

            </label>`;

        }).join('');

        if (meta && connectedCount === 0) {

            meta.textContent = 'Niciun furnizor conectat — configurează credențialele în Furnizori sau .env.';

        }

    }



    async function loadSupplierStatus() {

        try {

            const response = await fetch(SEARCH_ENDPOINT + '?action=status');

            const data = await response.json();

            if (!response.ok || !data.success) {

                throw new Error(data.message || 'Status furnizori indisponibil.');

            }

            renderSupplierCheckboxes(data.suppliers || []);

        } catch (error) {

            renderSupplierCheckboxes([

                { key: 'materom', label: 'Materom', short: 'MA', connected: false, reason: error.message },

                { key: 'elit', label: 'Elit', short: 'EL', connected: false, reason: error.message },

                { key: 'autopartner', label: 'Auto Partner', short: 'AP', connected: false, reason: error.message },

                { key: 'autonet', label: 'Autonet', short: 'AN', connected: false, reason: error.message },

                { key: 'autototal', label: 'Autototal', short: 'AT', connected: false, reason: error.message }

            ]);

        }

    }



    async function refreshCartBadge() {

        try {

            const response = await fetch(CART_ENDPOINT + '?action=count');

            const data = await response.json();

            if (!response.ok || !data.success || !cartBadge) return;

            const count = Number(data.summary?.items || 0);

            cartBadge.textContent = String(count);

            cartBadge.classList.toggle('hidden', count <= 0);

        } catch (_) {

            // badge optional

        }

    }



    function buildAddPayload(product, supplierKey, variant) {

        const variantCode = variant.order_code || variant.variant_code || product.mfrpn || '';

        const calculatedPrice = Number(variant.calculated_price ?? variant.price ?? 0);

        const rawPrice = Number(variant.raw_price ?? variant.price ?? calculatedPrice);

        const plantName = variant.delivery?.plant_name || '';



        return {

            supplier: supplierKey,

            product_code: product.mfrpn || variantCode,

            mfrpn: product.mfrpn || '',

            product_name: product.name || product.mfrpn || variantCode,

            manufacturer: product.manufacturer || '',

            variant_code: variantCode,

            api_lookup_code: variant.api_lookup_code || variantCode,

            searched_code: lastSearchQuery,

            qty: 1,

            price: calculatedPrice,

            raw_price: rawPrice,

            currency: variant.currency || 'RON',

            plantname: plantName,

            delivery: variant.delivery?.info_text || '',

            livrare: variant.livrare || '',

            depozit: variant.depozit || '',

            departamentcode: variant.departamentCode || variant.departamentcode || '',

            autonet_partno: variant.autonet_partno || ''

        };

    }



    function openAddModal(payload) {

        pendingAddPayload = payload;

        if (addModalTitle) {

            addModalTitle.textContent = [

                supplierLabel(payload.supplier),

                payload.product_code,

                payload.manufacturer,

                payload.product_name

            ].filter(Boolean).join(' · ');

        }

        if (addQty) addQty.value = '1';

        if (addPrice) addPrice.value = String(Math.round(Number(payload.price || 0)));

        addModal?.classList.remove('hidden');

    }



    function closeAddModal() {

        pendingAddPayload = null;

        addModal?.classList.add('hidden');

    }



    function renderRows(data) {

        const products = data.products || [];

        const rows = [];



        products.forEach((product) => {

            const title = [product.manufacturer, product.name, product.mfrpn].filter(Boolean).join(' · ');

            Object.entries(product.suppliers || {}).forEach(([supplierKey, supplierData]) => {

                (supplierData.variants || []).forEach((variant, variantIndex) => {

                    const payload = buildAddPayload(product, supplierKey, variant);

                    const payloadJson = escapeHtml(JSON.stringify(payload));

                    rows.push(`

                        <tr class="border-b">

                            <td class="px-3 py-2">

                                <div class="font-medium">${escapeHtml(product.mfrpn || '')}</div>

                                <div class="text-xs opacity-60">${escapeHtml(title)}</div>

                            </td>

                            <td class="px-3 py-2">${escapeHtml(supplierLabel(supplierKey))}</td>

                            <td class="px-3 py-2 text-center">${escapeHtml(variant.supplier_stock ?? '—')}</td>

                            <td class="px-3 py-2 text-right font-medium">${escapeHtml(formatMoney(variant.calculated_price ?? variant.price))}</td>

                            <td class="px-3 py-2">${escapeHtml(variant.livrare || variant.depozit || '—')}</td>

                            <td class="px-3 py-2 text-right">

                                <button type="button" class="supplier-add-btn box rounded border px-2 py-1 text-xs" data-payload="${payloadJson}">+ Coș</button>

                            </td>

                        </tr>

                    `);

                });

            });

        });



        if (!rows.length) {

            results.innerHTML = '<tr><td colspan="6" class="px-3 py-6 text-center opacity-70">Niciun rezultat de la furnizorii selectați.</td></tr>';

            return;

        }



        results.innerHTML = rows.join('');

    }



    async function runSearch() {

        const query = (input?.value || '').trim();

        const suppliers = selectedSuppliers();



        if (!query) {

            showToast('Introdu un cod OE/AM.', true);

            return;

        }

        if (!suppliers.length) {

            showToast('Selectează cel puțin un furnizor.', true);

            return;

        }



        lastSearchQuery = query;



        if (button) {

            button.disabled = true;

            button.textContent = 'Se caută...';

        }

        results.innerHTML = '<tr><td colspan="6" class="px-3 py-6 text-center opacity-70">Căutare în curs...</td></tr>';



        try {

            const response = await fetch(SEARCH_ENDPOINT, {

                method: 'POST',

                headers: { 'Content-Type': 'application/json' },

                body: JSON.stringify({ query, suppliers, debug_timings: true })

            });

            const data = await response.json();



            if (!response.ok || !data.success) {

                throw new Error(data.message || 'Căutarea a eșuat.');

            }



            renderRows(data);



            const errorText = Object.entries(data.errors || {})

                .map(([key, msg]) => supplierLabel(key) + ': ' + msg)

                .join(' · ');



            if (meta) {

                const count = (data.products || []).length;

                const timing = data.timings?.total ? ` · ${data.timings.total}s` : '';

                meta.textContent = `${count} produse agregate${timing}${errorText ? ' · ' + errorText : ''}`;

            }

        } catch (error) {

            results.innerHTML = `<tr><td colspan="6" class="px-3 py-6 text-center text-danger">${escapeHtml(error.message)}</td></tr>`;

            showToast(error.message, true);

        } finally {

            if (button) {

                button.disabled = false;

                button.textContent = 'Căutare';

            }

        }

    }



    async function confirmAddToCart() {

        if (!pendingAddPayload) return;



        const payload = { ...pendingAddPayload };

        payload.qty = Math.max(1, parseInt(addQty?.value || '1', 10));

        payload.price = Math.max(1, parseFloat(addPrice?.value || '0'));



        try {

            const response = await fetch(CART_ENDPOINT + '?action=add', {

                method: 'POST',

                headers: { 'Content-Type': 'application/json' },

                body: JSON.stringify(payload)

            });

            const data = await response.json();

            if (!response.ok || !data.success) {

                throw new Error(data.message || 'Nu s-a putut adăuga în coș.');

            }

            closeAddModal();

            showToast('Produs adăugat în coș.');

            refreshCartBadge();

        } catch (error) {

            showToast(error.message, true);

        }

    }



    results?.addEventListener('click', (event) => {

        const btn = event.target.closest('.supplier-add-btn');

        if (!btn) return;

        try {

            const payload = JSON.parse(btn.getAttribute('data-payload') || '{}');

            openAddModal(payload);

        } catch (error) {

            showToast('Date produs invalide.', true);

        }

    });



    button?.addEventListener('click', runSearch);

    input?.addEventListener('keydown', (event) => {

        if (event.key === 'Enter') {

            event.preventDefault();

            runSearch();

        }

    });

    addCancel?.addEventListener('click', closeAddModal);

    addConfirm?.addEventListener('click', confirmAddToCart);

    addModal?.addEventListener('click', (event) => {

        if (event.target === addModal) closeAddModal();

    });



    refreshCartBadge();

    loadSupplierStatus();

})();

</script>

