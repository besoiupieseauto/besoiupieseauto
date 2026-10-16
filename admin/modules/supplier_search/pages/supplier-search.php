<div class="ss-wrap">
    <div id="supplier-search-toast" class="ss-toast" hidden></div>

    <section class="ss-panel">
        <div class="ss-panel__head">Supplier Search</div>
        <div class="ss-panel__body">
            <div class="ss-tools">
                <input id="supplier-search-code" type="text" placeholder="OE / AM Code or VIN" autocomplete="off">
                <button type="button" id="supplier-search-btn" class="ss-btn ss-btn-primary">Cautare</button>
                <a class="ss-btn ss-btn-success" href="/admin/caiet-comenzi-tm">Comenzi</a>
                <a href="/admin/supplier-cart" id="supplier-cart-link" class="ss-btn ss-btn-success">
                    Cos <span id="supplier-cart-badge" class="ss-badge-count" hidden>0</span>
                </a>
            </div>
            <div id="supplier-search-checkboxes" class="ss-filters">
                <span class="ss-muted">Se încarcă furnizorii...</span>
            </div>
            <div id="ss-plants" class="ss-filters">
                <label class="ss-chip is-on" style="background:#28a745"><input type="checkbox" value="Azi" checked> Azi</label>
                <label class="ss-chip is-on" style="background:#007bff"><input type="checkbox" value="Maine" checked> Maine</label>
                <label class="ss-chip is-on" style="background:#fd7e14"><input type="checkbox" value="2 zile" checked> 2 zile</label>
                <label class="ss-chip is-on" style="background:#dc3545"><input type="checkbox" value="3 zile" checked> 3 zile</label>
                <label class="ss-chip is-on" style="background:#dc3545"><input type="checkbox" value="Depozite externe" checked> Depozite externe</label>
            </div>
            <div id="supplier-search-meta" class="ss-meta"></div>
        </div>
    </section>

    <table class="ss-table" id="productsTable">
        <thead>
        <tr>
            <th>Produs</th>
            <th>Stoc</th>
            <th>Preț Minim</th>
            <th>Acțiuni</th>
        </tr>
        </thead>
        <tbody id="supplier-search-results">
        <tr><td colspan="4" class="ss-empty">Introdu un cod și apasă „Cautare”.</td></tr>
        </tbody>
    </table>

    <div id="supplier-add-modal" class="ss-modal" hidden>
        <div class="ss-dialog" role="dialog" aria-modal="true" aria-labelledby="ss-price-title">
            <div class="ss-dialog__head">
                <h3 id="ss-price-title">Confirmă prețul</h3>
                <button type="button" class="ss-x" data-ss-close>&times;</button>
            </div>
            <div class="ss-dialog__body">
                <p id="supplier-add-modal-title" class="ss-muted"></p>
                <label for="supplier-add-price">Preț final de vânzare</label>
                <input id="supplier-add-price" type="number" min="1" step="1">
                <input id="supplier-add-qty" type="hidden" value="1">
            </div>
            <div class="ss-dialog__foot">
                <button type="button" id="supplier-add-cancel" class="ss-btn" data-ss-close>Anulează</button>
                <button type="button" id="supplier-add-confirm" class="ss-btn ss-btn-success">Adaugă în coș</button>
            </div>
        </div>
    </div>

    <div id="ss-variants-modal" class="ss-modal" hidden>
        <div class="ss-dialog ss-dialog--lg" role="dialog" aria-modal="true">
            <div class="ss-dialog__head">
                <h3>Vezi alte variante</h3>
                <button type="button" class="ss-x" data-ss-close>&times;</button>
            </div>
            <div class="ss-dialog__body">
                <table class="ss-sub">
                    <thead>
                    <tr>
                        <th>Brand</th>
                        <th>Disponibilitate / Livrare / Depozit</th>
                        <th>Preț</th>
                        <th>Adaugă în coș</th>
                    </tr>
                    </thead>
                    <tbody id="ss-variants-body"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
.ss-wrap{font-size:14px;color:#333}
.ss-panel{border:1px solid #bce8f1;border-radius:4px;background:#fff;margin-top:8px}
.ss-panel__head{background:#d9edf7;color:#31708f;padding:10px 14px;border-bottom:1px solid #bce8f1;font-weight:700}
.ss-panel__body{padding:14px}
.ss-tools{display:flex;flex-wrap:wrap;gap:8px;align-items:center}
.ss-tools input[type="text"]{width:min(400px,100%);height:34px;border:1px solid #ccc;border-radius:4px;padding:6px 10px;background:#fff}
.ss-btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;height:34px;padding:0 12px;border-radius:4px;border:1px solid #ccc;background:#fff;color:#333;text-decoration:none;font-size:14px;cursor:pointer}
.ss-btn-primary{background:#337ab7;border-color:#2e6da4;color:#fff}
.ss-btn-success{background:#5cb85c;border-color:#4cae4c;color:#fff}
.ss-btn-info{background:#5bc0de;border-color:#46b8da;color:#fff}
.ss-btn:disabled{opacity:.55;cursor:not-allowed}
.ss-filters{display:flex;flex-wrap:wrap;gap:6px;margin-top:10px}
.ss-chip{display:inline-flex;align-items:center;gap:6px;border:1px solid #ccc;background:#fff;color:#333;border-radius:4px;padding:3px 10px;cursor:pointer;font-size:13px}
.ss-chip input{margin:0}
#ss-plants .ss-chip.is-on{color:#fff;border-color:transparent}
#supplier-search-checkboxes .ss-chip.is-on{background:#f7f7f7;color:#333;font-weight:700;box-shadow:inset 0 0 0 1px #337ab7}
.ss-meta,.ss-muted{margin-top:8px;color:#666;font-size:12px}
.ss-table{width:100%;border-collapse:collapse;margin-top:14px;background:#fff}
.ss-table th{background:#d9edf7;color:#31708f;border:1px solid #ddd;padding:8px 16px;text-align:left}
.ss-table td{border:1px solid #ddd;padding:10px 16px;vertical-align:middle}
.ss-table tr.ss-parent:nth-of-type(4n+1) td{background:#f4f9ef}
.ss-sub{width:100%;border-collapse:collapse;background:#fff}
.ss-sub th,.ss-sub td{border:1px solid #ddd;padding:8px;text-align:left}
.ss-sub thead th{background:#fcf8e3;color:#333}
.ss-child{display:none;background:#f9f9f9}
.ss-child.is-open{display:table-row}
.ss-badge{display:inline-block;color:#fff;border-radius:7px;padding:5px 16px;margin-bottom:4px}
.ss-open{color:#007bff;font-weight:700;cursor:pointer;background:none;border:0;padding:0}
.ss-open.is-open::before{content:'▼ '}
.ss-open:not(.is-open)::before{content:'▶ '}
.ss-qty{width:70px;height:30px;border:1px solid #ccc;border-radius:4px;padding:4px 6px}
.ss-min{background:#d4edda}
.ss-empty{text-align:center;padding:20px;color:#666}
.ss-badge-count{min-width:1.2rem;border-radius:10px;background:#fff;color:#3c763d;font-size:12px;padding:0 6px;font-weight:700}
.ss-toast{position:fixed;right:20px;top:20px;z-index:200001;background:#fff;border:1px solid #ddd;border-radius:4px;padding:10px 14px;box-shadow:0 4px 16px rgba(0,0,0,.15)}
.ss-toast.is-error{color:#a94442}
.ss-modal{position:fixed;inset:0;z-index:200000;background:rgba(15,23,42,.45);display:flex;align-items:flex-start;justify-content:center;padding:72px 16px;overflow:auto}
.ss-modal[hidden]{display:none}
.ss-dialog{width:min(420px,100%);background:#fff;border:1px solid #ddd;border-radius:6px;box-shadow:0 8px 24px rgba(0,0,0,.2)}
.ss-dialog--lg{width:min(860px,100%)}
.ss-dialog__head{display:flex;align-items:center;justify-content:space-between;padding:12px 16px;border-bottom:1px solid #e5e5e5;background:#f5f5f5}
.ss-dialog__head h3{margin:0;font-size:18px;font-weight:500;color:#333}
.ss-dialog__body{padding:16px}
.ss-dialog__body label{display:block;margin:8px 0 6px;font-weight:600}
.ss-dialog__body input[type="number"]{width:100%;height:34px;border:1px solid #ccc;border-radius:4px;padding:6px 10px;box-sizing:border-box}
.ss-dialog__foot{padding:12px 16px;border-top:1px solid #e5e5e5;display:flex;justify-content:flex-end;gap:8px}
.ss-x{border:0;background:transparent;font-size:24px;line-height:1;cursor:pointer;color:#333}
</style>

<script>
(function () {
    'use strict';

    const SEARCH_ENDPOINT = '/admin/api/supplier_search_endpoint.php';
    const CART_ENDPOINT = '/admin/api/supplier_cart_endpoint.php';
    const SEARCH_TIMEOUT_MS = 70000;

    function fetchErrorMessage(error, response) {
        if (error && (error.name === 'AbortError' || error.name === 'TimeoutError')) {
            return 'Căutarea a expirat (' + Math.round(SEARCH_TIMEOUT_MS / 1000) + 's). Reduce furnizorii sau reîncearcă.';
        }
        if (error instanceof TypeError && String(error.message || '').toLowerCase().includes('fetch')) {
            return 'Serverul nu a răspuns (conexiune întreruptă sau request PHP blocat). Reîncarcă pagina.';
        }
        if (response && response.status === 503) {
            return 'API oprit — activează din header sau verifică setările API.';
        }
        return (error && error.message) ? error.message : 'Eroare de rețea.';
    }

    async function readJsonResponse(response) {
        const text = await response.text();
        try {
            return JSON.parse(text);
        } catch (parseError) {
            throw new Error(response.ok
                ? 'Răspuns invalid de la server (nu e JSON).'
                : 'Eroare server (HTTP ' + response.status + ').');
        }
    }

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
    const addConfirm = document.getElementById('supplier-add-confirm');
    const checkboxWrap = document.getElementById('supplier-search-checkboxes');
    const variantsModal = document.getElementById('ss-variants-modal');
    const variantsBody = document.getElementById('ss-variants-body');

    let lastSearchQuery = '';
    let pendingAddPayload = null;
    let supplierCatalog = [];
    let lastData = null;

    function mountModals() {
        [addModal, variantsModal].forEach((el) => {
            if (el && el.parentElement !== document.body) document.body.appendChild(el);
        });
    }

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, (char) => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
        }[char]));
    }

    function showToast(message, isError) {
        if (!toast) return;
        toast.hidden = false;
        toast.textContent = message;
        toast.classList.toggle('is-error', Boolean(isError));
        setTimeout(() => { toast.hidden = true; }, 4000);
    }

    function selectedSuppliers() {
        return Array.from(document.querySelectorAll('#supplier-search-checkboxes input[type="checkbox"]:checked'))
            .map((el) => el.value)
            .filter(Boolean);
    }

    function selectedPlants() {
        return Array.from(document.querySelectorAll('#ss-plants input[type="checkbox"]:checked'))
            .map((el) => el.value);
    }

    function formatMoney(value) {
        return String(Math.round(Number(value || 0)));
    }

    function supplierLabel(key) {
        const fromCatalog = supplierCatalog.find((row) => row.key === key);
        if (fromCatalog && fromCatalog.label) return fromCatalog.label;
        return ({
            materom: 'Materom',
            elit: 'Elit',
            autopartner: 'Auto Partner',
            autonet: 'Autonet',
            autototal: 'Autototal',
            intercars: 'InterCars',
            szakal: 'Szakal Metal'
        })[key] || key;
    }

    function plantOf(variant) {
        const text = String((variant && (variant.livrare || variant.depozit)) || '').toLowerCase();
        if (/azi|astazi|astăzi|timisoara|timișoara/.test(text)) return 'Azi';
        if (/maine|mâine/.test(text)) return 'Maine';
        const range = text.match(/(\d+)\s*[-–]\s*(\d+)\s*zile/);
        const single = text.match(/(\d+)\s*zile/);
        const days = range ? Number(range[1]) : (single ? Number(single[1]) : null);
        if (days === null) return 'Depozite externe';
        if (days <= 0) return 'Azi';
        if (days === 1) return 'Maine';
        if (days === 2) return '2 zile';
        if (days === 3) return '3 zile';
        return 'Depozite externe';
    }

    function plantColor(name) {
        return ({
            'Azi': '#28a745',
            'Maine': '#007bff',
            '2 zile': '#fd7e14',
            '3 zile': '#dc3545',
            'Depozite externe': '#dc3545'
        })[name] || '#777';
    }

    function priceOf(variant) {
        return Number(variant.calculated_price ?? variant.price ?? 0);
    }

    function stockOf(variant) {
        const stock = Number(variant.supplier_stock ?? variant.stock ?? 0);
        return Number.isFinite(stock) ? stock : 0;
    }

    function renderSupplierCheckboxes(suppliers) {
        if (!checkboxWrap) return;
        supplierCatalog = Array.isArray(suppliers) ? suppliers : [];
        if (!supplierCatalog.length) {
            checkboxWrap.innerHTML = '<span class="ss-muted">Nu s-au putut încărca furnizorii.</span>';
            return;
        }
        checkboxWrap.innerHTML = supplierCatalog.map((row) => {
            const title = row.connected
                ? 'Conectat'
                : escapeHtml(row.reason || 'Neconectat');
            return '<label class="ss-chip is-on" title="' + title + '">'
                + '<input type="checkbox" value="' + escapeHtml(row.key) + '" checked> '
                + escapeHtml(row.short || row.label || row.key)
                + '</label>';
        }).join('');
        const connectedCount = supplierCatalog.filter((row) => row.connected).length;
        if (meta && !lastData) {
            meta.textContent = connectedCount + ' conectat(i) din ' + supplierCatalog.length;
        }
    }

    async function loadSupplierStatus() {
        try {
            const response = await fetch(SEARCH_ENDPOINT + '?action=status');
            const data = await response.json();
            if (!response.ok || !data.success) throw new Error(data.message || 'Status furnizori indisponibil.');
            renderSupplierCheckboxes(data.suppliers || []);
        } catch (error) {
            renderSupplierCheckboxes([
                { key: 'materom', label: 'Materom', short: 'MA', connected: false },
                { key: 'elit', label: 'Elit', short: 'EL', connected: false },
                { key: 'autopartner', label: 'Auto Partner', short: 'AP', connected: false },
                { key: 'autonet', label: 'Autonet', short: 'AN', connected: false },
                { key: 'autototal', label: 'Autototal', short: 'AT', connected: false },
                { key: 'intercars', label: 'InterCars', short: 'IC', connected: false },
                { key: 'szakal', label: 'Szakal Metal', short: 'SZ', connected: false }
            ]);
        }
    }

    async function refreshCartBadge() {
        try {
            const response = await fetch(CART_ENDPOINT + '?action=count');
            const data = await readJsonResponse(response);
            if (!response.ok || !data.success || !cartBadge) return;
            const count = Number(data.summary?.items || 0);
            cartBadge.textContent = String(count);
            cartBadge.hidden = count <= 0;
        } catch (_) {}
    }

    function buildAddPayload(product, supplierKey, variant) {
        const variantCode = String(variant.order_code || variant.variant_code || variant.code || product.mfrpn || product.code || lastSearchQuery || '').trim();
        const productCode = String(product.mfrpn || product.code || variant.variant_code || variantCode).trim();
        const calculatedPrice = priceOf(variant);
        return {
            supplier: supplierKey,
            product_code: productCode,
            mfrpn: product.mfrpn || productCode,
            product_name: product.name || product.mfrpn || variantCode,
            manufacturer: typeof product.manufacturer === 'object' ? (product.manufacturer.name || '') : (product.manufacturer || ''),
            variant_code: variantCode || productCode,
            api_lookup_code: variant.api_lookup_code || variantCode || productCode,
            searched_code: lastSearchQuery,
            qty: 1,
            price: calculatedPrice,
            raw_price: Number(variant.raw_price ?? variant.price ?? calculatedPrice),
            currency: variant.currency || 'RON',
            plantname: variant.delivery?.plant_name || '',
            delivery: variant.delivery?.info_text || '',
            livrare: variant.livrare || '',
            depozit: variant.depozit || '',
            departamentcode: variant.departamentCode || variant.departamentcode || '',
            autonet_partno: variant.autonet_partno || '',
            szakal_token: variant.szakal_token || '',
            szakal_glid: variant.szakal_glid || ''
        };
    }

    function cartCell(payload) {
        const json = escapeHtml(JSON.stringify(payload));
        return '<input type="number" class="ss-qty" min="1" value="1">'
            + '<button type="button" class="ss-btn ss-btn-success supplier-add-btn" data-payload="' + json + '" title="Adaugă în coș">&#128722;</button>';
    }

    function variantRows(product, supplierKey, variants) {
        return variants.map((variant) => {
            const payload = buildAddPayload(product, supplierKey, variant);
            const brand = payload.manufacturer || supplierLabel(supplierKey);
            const plant = plantOf(variant);
            return '<tr><td><strong>' + escapeHtml(payload.product_code) + '</strong><br><span class="ss-muted">' + escapeHtml(brand) + '</span></td>'
                + '<td><span class="ss-badge" style="background:' + plantColor(plant) + '">' + escapeHtml(String(stockOf(variant))) + '</span> '
                + escapeHtml(variant.livrare || '—') + ' / ' + escapeHtml(variant.depozit || '—') + '</td>'
                + '<td>' + escapeHtml(formatMoney(priceOf(variant))) + '</td>'
                + '<td>' + cartCell(payload) + '</td></tr>';
        }).join('');
    }

    function renderRows(data) {
        lastData = data;
        const plants = selectedPlants();
        const allowed = new Set(selectedSuppliers());
        const products = data.products || [];
        const html = [];
        let shown = 0;

        products.forEach((product) => {
            const blocks = [];
            let minPrice = Infinity;
            let fastest = null;
            Object.entries(product.suppliers || {}).forEach(([supplierKey, supplierData]) => {
                if (allowed.size && !allowed.has(supplierKey)) return;
                const variants = (supplierData.variants || []).filter((variant) => plants.includes(plantOf(variant)));
                if (!variants.length) return;
                variants.sort((a, b) => priceOf(a) - priceOf(b));
                const main = variants[0];
                const price = priceOf(main);
                if (price < minPrice) minPrice = price;
                const plant = plantOf(main);
                if (!fastest || plantColor(plant) === '#28a745') fastest = { supplierKey, plant, variant: main };
                const payload = buildAddPayload(product, supplierKey, main);
                const minMark = '';
                blocks.push({ supplierKey, variants, main, price, plant, payload, minMark });
            });
            if (!blocks.length) return;
            shown += 1;
            blocks.forEach((block) => { block.isMin = block.price === minPrice; });
            if (!fastest) fastest = { supplierKey: blocks[0].supplierKey, plant: blocks[0].plant, variant: blocks[0].main };
            const title = product.name || product.mfrpn || '';
            const brand = typeof product.manufacturer === 'object' ? (product.manufacturer.name || '') : (product.manufacturer || '');
            const sub = blocks.map((block) => {
                return '<tr><td>' + escapeHtml(supplierLabel(block.supplierKey)) + '</td>'
                    + '<td><span class="ss-badge" style="background:' + plantColor(block.plant) + '">' + escapeHtml(String(stockOf(block.main))) + '</span> '
                    + escapeHtml(block.main.livrare || '—') + (block.main.depozit ? ' / ' + escapeHtml(block.main.depozit) : '') + '</td>'
                    + '<td class="' + (block.isMin ? 'ss-min' : '') + '">' + escapeHtml(formatMoney(block.price))
                    + (block.isMin ? ' <strong>Cel mai mic pret !</strong>' : '') + '</td>'
                    + '<td>' + cartCell(block.payload)
                    + (block.variants.length > 1
                        ? ' <button type="button" class="ss-btn ss-btn-info ss-variants" data-supplier="' + escapeHtml(block.supplierKey) + '" data-variants="' + escapeHtml(JSON.stringify(block.variants)) + '" data-product="' + escapeHtml(JSON.stringify({ mfrpn: product.mfrpn, name: product.name, manufacturer: brand })) + '">Variante</button>'
                        : '')
                    + '</td></tr>';
            }).join('');
            html.push(
                '<tr class="ss-parent"><td><strong>' + escapeHtml(title) + '</strong><br>' + escapeHtml(brand) + '<br><span class="ss-muted">' + escapeHtml(product.mfrpn || '') + '</span></td>'
                + '<td><span class="ss-badge" style="background:' + plantColor(fastest.plant) + '">' + escapeHtml(supplierLabel(fastest.supplierKey)) + ' ' + escapeHtml(fastest.variant.livrare || fastest.plant) + '</span><br><small>' + blocks.length + ' furnizor(i)</small></td>'
                + '<td><strong>' + escapeHtml(formatMoney(minPrice)) + '</strong></td>'
                + '<td><button type="button" class="ss-open">Deschide</button></td></tr>'
                + '<tr class="ss-child"><td colspan="4"><table class="ss-sub"><thead><tr><th>Furnizor</th><th>Stoc / Livrare / Depozit</th><th>Preț</th><th>Achiziție</th></tr></thead><tbody>' + sub + '</tbody></table></td></tr>'
            );
        });

        if (!html.length) {
            results.innerHTML = '<tr><td colspan="4" class="ss-empty">Niciun rezultat pentru furnizorii și termenele bifate.</td></tr>';
            return;
        }
        results.innerHTML = html.join('');
        if (meta) meta.textContent = shown + ' produse afișate';
    }

    function openModal(el) {
        mountModals();
        el.hidden = false;
    }

    function closeModal(el) {
        if (el) el.hidden = true;
    }

    function openAddModal(payload, qty) {
        pendingAddPayload = payload;
        if (addModalTitle) {
            addModalTitle.textContent = [supplierLabel(payload.supplier), payload.product_code, payload.manufacturer, payload.product_name].filter(Boolean).join(' · ');
        }
        if (addQty) addQty.value = String(Math.max(1, parseInt(qty || '1', 10)));
        if (addPrice) {
            const rawPriceNum = Number(payload.price || 0);
            addPrice.value = String(rawPriceNum > 0 ? Math.max(1, Math.round(rawPriceNum)) : 1);
        }
        openModal(addModal);
    }

    function closeAddModal() {
        pendingAddPayload = null;
        closeModal(addModal);
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
        results.innerHTML = '<tr><td colspan="4" class="ss-empty">Căutare în curs...</td></tr>';
        try {
            const response = await fetch(SEARCH_ENDPOINT, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ query, suppliers, debug_timings: true }),
                signal: AbortSignal.timeout(SEARCH_TIMEOUT_MS)
            });
            const data = await readJsonResponse(response);
            if (!response.ok || !data.success) throw new Error(data.message || 'Căutarea a eșuat.');
            renderRows(data);
            const errorText = Object.entries(data.errors || {}).map(([key, msg]) => supplierLabel(key) + ': ' + msg).join(' · ');
            if (meta) {
                const count = (data.products || []).length;
                const timing = data.timings?.total ? ' · ' + data.timings.total + 's' : '';
                meta.textContent = count + ' produse agregate' + timing + (errorText ? ' · ' + errorText : '');
            }
        } catch (error) {
            const message = fetchErrorMessage(error);
            results.innerHTML = '<tr><td colspan="4" class="ss-empty">' + escapeHtml(message) + '</td></tr>';
            showToast(message, true);
        } finally {
            if (button) {
                button.disabled = false;
                button.textContent = 'Cautare';
            }
        }
    }

    async function confirmAddToCart() {
        if (!pendingAddPayload) return;
        const payload = Object.assign({}, pendingAddPayload);
        payload.qty = Math.max(1, parseInt(addQty?.value || '1', 10));
        payload.price = Math.max(1, parseFloat(addPrice?.value || '0'));
        if (addConfirm) addConfirm.disabled = true;
        try {
            const response = await fetch(CART_ENDPOINT + '?action=add', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await readJsonResponse(response);
            if (!response.ok || !data.success) throw new Error(data.message || 'Nu s-a putut adăuga în coș.');
            closeAddModal();
            closeModal(variantsModal);
            showToast('Produs adăugat în coș.');
            refreshCartBadge();
        } catch (error) {
            showToast(fetchErrorMessage(error), true);
        } finally {
            if (addConfirm) addConfirm.disabled = false;
        }
    }

    results?.addEventListener('click', (event) => {
        const opener = event.target.closest('.ss-open');
        if (opener) {
            const child = opener.closest('tr')?.nextElementSibling;
            if (child) child.classList.toggle('is-open');
            opener.classList.toggle('is-open');
            opener.textContent = opener.classList.contains('is-open') ? 'Închide' : 'Deschide';
            return;
        }
        const variantsBtn = event.target.closest('.ss-variants');
        if (variantsBtn) {
            const product = JSON.parse(variantsBtn.getAttribute('data-product') || '{}');
            const variants = JSON.parse(variantsBtn.getAttribute('data-variants') || '[]');
            variantsBody.innerHTML = variantRows(product, variantsBtn.getAttribute('data-supplier'), variants);
            openModal(variantsModal);
            return;
        }
        const btn = event.target.closest('.supplier-add-btn');
        if (!btn) return;
        const qtyInput = btn.parentElement?.querySelector('.ss-qty');
        openAddModal(JSON.parse(btn.getAttribute('data-payload') || '{}'), qtyInput ? qtyInput.value : '1');
    });

    variantsBody?.addEventListener('click', (event) => {
        const btn = event.target.closest('.supplier-add-btn');
        if (!btn) return;
        const qtyInput = btn.parentElement?.querySelector('.ss-qty');
        openAddModal(JSON.parse(btn.getAttribute('data-payload') || '{}'), qtyInput ? qtyInput.value : '1');
    });

    document.querySelector('.ss-wrap')?.addEventListener('change', (event) => {
        const chip = event.target.closest('.ss-chip');
        if (chip && event.target.matches('input[type="checkbox"]')) {
            chip.classList.toggle('is-on', event.target.checked);
            if (chip.parentElement?.id === 'ss-plants') {
                chip.style.background = event.target.checked ? plantColor(event.target.value) : '#fff';
                chip.style.color = event.target.checked ? '#fff' : '#333';
            }
        }
        if (lastData && (event.target.closest('#ss-plants') || event.target.closest('#supplier-search-checkboxes'))) {
            renderRows(lastData);
        }
    });

    button?.addEventListener('click', runSearch);
    input?.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            runSearch();
        }
    });
    document.addEventListener('click', (event) => {
        if (event.target.closest('[data-ss-close]')) {
            closeAddModal();
            closeModal(variantsModal);
        }
        if (event.target === addModal) closeAddModal();
        if (event.target === variantsModal) closeModal(variantsModal);
    });
    addConfirm?.addEventListener('click', confirmAddToCart);

    mountModals();
    refreshCartBadge();
    loadSupplierStatus();
})();
</script>
