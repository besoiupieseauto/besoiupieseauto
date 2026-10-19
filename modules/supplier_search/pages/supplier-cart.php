<?php

declare(strict_types=1);

$cartUserId = (int) ($_SESSION['user_id'] ?? 0);
$cartInitial = ['cart' => [], 'summary' => ['lines' => 0, 'items' => 0, 'total' => 0.0]];
$cartInitialError = '';
try {
    if ($cartUserId > 0) {
        $cartInitial = (new \Besoiu\Modules\SupplierSearch\Service\SupplierCartService(
            \Besoiu\Modules\SupplierSearch\Service\SupplierSearchConfig::legacyPdo()
        ))->show($cartUserId);
    }
} catch (\Throwable $cartBootError) {
    $cartInitialError = $cartBootError->getMessage();
}

$cartEsc = static function (mixed $value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};
$cartMoney = static function (mixed $value): string {
    return number_format((float) $value, 2, ',', '.') . ' RON';
};
$cartSupplierLabel = static function (string $key): string {
    return [
        'materom' => 'Materom',
        'elit' => 'Elit',
        'autopartner' => 'Auto Partner',
        'autonet' => 'Autonet',
        'autototal' => 'Autototal',
        'intercars' => 'InterCars',
        'szakal' => 'Szakal Metal',
        'bardi' => 'Bardi Auto',
        'site_produse' => 'Site / ERP',
    ][$key] ?? $key;
};
$cartSummary = is_array($cartInitial['summary'] ?? null) ? $cartInitial['summary'] : [];
$cartSummaryText = $cartInitialError !== ''
    ? $cartInitialError
    : ((int) ($cartSummary['lines'] ?? 0) . ' linii · ' . (int) ($cartSummary['items'] ?? 0) . ' bucăți · total ' . $cartMoney($cartSummary['total'] ?? 0));
$cartRowsHtml = '';
foreach (is_array($cartInitial['cart'] ?? null) ? $cartInitial['cart'] : [] as $cartSupplier => $cartItems) {
    if (!is_string($cartSupplier) || !is_array($cartItems)) {
        continue;
    }
    foreach ($cartItems as $cartKey => $cartItem) {
        if (!is_array($cartItem)) {
            continue;
        }
        $qty = (int) ($cartItem['qty'] ?? 1);
        $price = (float) ($cartItem['price'] ?? 0);
        $title = implode(' · ', array_filter([
            (string) ($cartItem['manufacturer'] ?? ''),
            (string) ($cartItem['product_name'] ?? ''),
            (string) ($cartItem['product_code'] ?? ''),
        ], static fn (string $part): bool => $part !== ''));
        $exclude = strtolower($cartSupplier) === 'autototal'
            ? '<label class="text-xs"><input type="checkbox" class="cart-exclude"> exclus</label>'
            : '';
        $cartRowsHtml .= '<tr class="border-b" data-supplier="' . $cartEsc($cartSupplier) . '" data-key="' . $cartEsc((string) $cartKey) . '">'
            . '<td class="px-3 py-2"><input type="checkbox" class="cart-select" checked>' . $exclude . '</td>'
            . '<td class="px-3 py-2"><div class="font-medium">' . $cartEsc($cartItem['product_code'] ?? '') . '</div>'
            . '<div class="text-xs opacity-60">' . $cartEsc($title) . '</div>'
            . '<div class="text-xs opacity-50">' . $cartEsc($cartItem['order_code'] ?? $cartItem['variant_code'] ?? '') . '</div>'
            . ($cartEsc($cartItem['webshop_line_id'] ?? '') !== ''
                ? '<div class="text-xs">ID webshop: ' . $cartEsc($cartItem['webshop_line_id'] ?? '') . '</div>'
                : '')
            . '</td>'
            . '<td class="px-3 py-2">' . $cartEsc($cartSupplierLabel($cartSupplier)) . '</td>'
            . '<td class="px-3 py-2 text-center"><input type="number" min="1" class="cart-qty box h-8 w-16 rounded border px-2 text-center" value="' . $cartEsc($qty) . '"></td>'
            . '<td class="px-3 py-2 text-right">' . $cartEsc($cartMoney($price)) . '</td>'
            . '<td class="px-3 py-2 text-right font-medium">' . $cartEsc($cartMoney($qty * $price)) . '</td>'
            . '<td class="px-3 py-2">' . $cartEsc(($cartItem['livrare'] ?? '') !== '' && ($cartItem['livrare'] ?? '') !== '-' ? $cartItem['livrare'] : ($cartItem['depozit'] ?? '—')) . '</td>'
            . '<td class="px-3 py-2 text-right"><button type="button" class="cart-save box rounded border px-2 py-1 text-xs">Salvează</button> '
            . '<button type="button" class="cart-remove box rounded border px-2 py-1 text-xs text-danger">Șterge</button></td></tr>';
    }
}
if ($cartRowsHtml === '') {
    $cartRowsHtml = '<tr><td colspan="8" class="px-3 py-6 text-center opacity-70">'
        . ($cartInitialError !== '' ? $cartEsc($cartInitialError) : 'Coșul este gol. Adaugă produse din <a class="underline" href="/admin/supplier-search">căutarea furnizorilor</a>.')
        . '</td></tr>';
}
?>
<style>
.caiet-modal{position:fixed;inset:0;background:rgba(15,23,42,.45);z-index:200000;display:flex;align-items:flex-start;justify-content:center;padding:28px 16px;overflow:auto}
.caiet-modal.hidden{display:none !important}
.caiet-sheet{background:#fff;border:1px solid #bce8f1;width:min(980px,100%);max-height:calc(100vh - 56px);overflow:auto;border-radius:6px;box-shadow:0 18px 50px rgba(15,23,42,.28);box-sizing:border-box}
.caiet-sheet__head{background:#d9edf7;color:#31708f;border-bottom:1px solid #bce8f1;padding:10px 15px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:2}
.caiet-sheet__head h3{margin:0;font-size:18px;font-weight:500}
.caiet-sheet__body{padding:16px;background:#fff}
.caiet-sheet__close{border:0;background:transparent;font-size:22px;line-height:1;cursor:pointer;color:#31708f}
.order-form{display:flex;flex-direction:column;gap:12px}
.order-line{display:grid;grid-template-columns:104px minmax(260px,1.7fr) 108px minmax(140px,1fr) 64px minmax(140px,.9fr);gap:8px 10px;align-items:center}
.order-line > label,.order-k{text-align:right;font-size:13px;color:#333;line-height:1.25}
.order-ctrl{min-width:0}
.order-ctrl input,.order-ctrl select,.order-ctrl textarea{width:100%;height:34px;border:1px solid #ccc;border-radius:4px;padding:4px 8px;box-sizing:border-box;background:#fff;color:#111}
.order-ctrl textarea{height:78px;resize:vertical;padding-top:6px}
.order-when{display:flex;gap:8px}
.order-when input[type="date"]{flex:1 1 160px;width:auto;min-width:148px}
.order-when input[type="text"]{width:92px;flex:0 0 92px}
.order-split{display:grid;grid-template-columns:minmax(220px,280px) minmax(0,1fr);gap:20px;align-items:start}
.order-block{display:grid;grid-template-columns:112px minmax(0,1fr);gap:8px 10px;align-items:start}
.order-k,.order-block > label{padding-top:6px;text-align:right;font-size:13px;color:#333;line-height:1.25}
.order-radios{display:flex;flex-direction:column;align-items:flex-start;gap:6px;padding-top:4px}
.order-radios label{display:inline-flex;flex-direction:row;align-items:center;gap:8px;font-size:13px;color:#333;white-space:nowrap;width:auto}
.order-radios input{width:16px;height:16px;margin:0;flex:0 0 16px;accent-color:#31708f}
.order-actions{display:flex;justify-content:flex-end;gap:8px}
.caiet-input-plus{display:flex}
.caiet-input-plus input{flex:1}
.caiet-input-plus button,.lg-btn{height:34px;border:1px solid #ccc;background:#fff;cursor:pointer;padding:0 10px}
#supplier-cart-place-modal .lg-btn{height:34px;border:1px solid #ccc;background:#fff;cursor:pointer;padding:0 10px;border-radius:4px;color:#333}
#supplier-cart-place-modal .lg-btn-success{background:#5cb85c;border-color:#4cae4c;color:#fff}
#supplier-cart-place-modal .lg-btn-default{background:#fff;color:#333}
.caiet-suggest{position:relative}
.caiet-suggest ul{position:absolute;z-index:5;left:0;right:0;top:100%;margin:0;padding:0;list-style:none;background:#fff;border:1px solid #ccc;max-height:220px;overflow:auto}
.caiet-suggest li{padding:6px 8px;cursor:pointer}
.caiet-suggest li:hover{background:#d9edf7}
.caiet-lines{width:100%;border-collapse:collapse;margin-top:12px}
.caiet-lines th,.caiet-lines td{border:1px solid #ddd;padding:6px 8px;text-align:center}
.caiet-lines thead tr{background:#fcf8e3}
@media (max-width:900px){
  .order-line,.order-split,.order-block{grid-template-columns:100px minmax(0,1fr)}
}
</style>
<div>
    <div id="supplier-cart-toast" class="hidden fixed right-5 top-5 z-50 rounded-md border bg-background px-4 py-3 text-sm shadow"></div>

    <div class="mt-10 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-lg font-medium">Coș furnizori B2B</h2>
            <p class="mt-1 text-sm opacity-70">Coș intern propriu. La Intercars, Elit și Bardi lucrăm doar linia adăugată acum, fără click-ul final de comandă.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <button type="button" id="supplier-cart-rehearse" class="box h-10 rounded-md border px-4 text-sm">Pregătește webshop (fără trimitere)</button>
            <button type="button" id="supplier-cart-place-order" class="box h-10 rounded-md border bg-primary px-4 text-sm text-white">Place order →</button>
            <button type="button" id="supplier-cart-import-tmp" class="box h-10 rounded-md border px-4 text-sm">Import în tmp (manual)</button>
            <a href="/admin/order-create" class="box inline-flex h-10 items-center rounded-md border px-4 text-sm">Comandă internă</a>
            <a href="/admin/supplier-search" class="box inline-flex h-10 items-center rounded-md border px-4 text-sm">← Căutare</a>
        </div>
    </div>

    <div class="mt-5 box p-5">
        <div id="supplier-cart-summary" class="text-sm opacity-80"><?= $cartSummaryText ?></div>
        <div id="supplier-cart-rehearse-log" class="mt-3 hidden text-sm"></div>
    </div>

    <div class="mt-5 overflow-auto">
        <table class="w-full text-sm">
            <thead class="border-b bg-foreground/5">
            <tr>
                <th class="px-3 py-2 w-8"><input type="checkbox" id="supplier-cart-select-all" checked></th>
                <th class="px-3 py-2 text-left">Produs</th>
                <th class="px-3 py-2 text-left">Furnizor</th>
                <th class="px-3 py-2 text-center">Qty</th>
                <th class="px-3 py-2 text-right">Preț unitar</th>
                <th class="px-3 py-2 text-right">Subtotal</th>
                <th class="px-3 py-2 text-left">Livrare</th>
                <th class="px-3 py-2 text-right">Acțiuni</th>
            </tr>
            </thead>
            <tbody id="supplier-cart-results">
            <?= $cartRowsHtml ?>
            </tbody>
        </table>
    </div>

    <div id="supplier-cart-place-modal" class="caiet-modal hidden">
        <div class="caiet-sheet">
            <div class="caiet-sheet__head">
                <h3>Comanda noua</h3>
                <button type="button" class="caiet-sheet__close" id="place-close" aria-label="Închide">&times;</button>
            </div>
            <div class="caiet-sheet__body">
                <div class="order-form">
                    <div class="order-line">
                        <label for="place-client-search">Client</label>
                        <div class="order-ctrl caiet-suggest">
                            <div class="caiet-input-plus">
                                <input id="place-client-search" placeholder="Cauta si click pe client din lista" autocomplete="off">
                                <button type="button" id="place-add-client" title="Client nou">+</button>
                            </div>
                            <ul id="place-client-list" hidden></ul>
                            <input type="hidden" id="place-client-id">
                        </div>
                        <label for="place-phone">Telefon</label>
                        <div class="order-ctrl"><input id="place-phone" readonly></div>
                        <label for="place-address">Adresa</label>
                        <div class="order-ctrl"><input id="place-address" readonly></div>
                    </div>
                    <div class="order-line">
                        <label for="place-date">Data</label>
                        <div class="order-ctrl order-when">
                            <input id="place-date" type="date">
                            <input id="place-time" type="text" readonly>
                        </div>
                        <label for="place-marca">Marca masina</label>
                        <div class="order-ctrl"><input id="place-marca"></div>
                        <label for="place-status">Stare</label>
                        <div class="order-ctrl">
                            <select id="place-status">
                                <option value="1">Comandat</option>
                                <option value="2">Sosit</option>
                                <option value="3">Expediat</option>
                                <option value="4">Achitat</option>
                            </select>
                        </div>
                    </div>
                    <div class="order-split">
                        <div class="order-block">
                            <span class="order-k">Magazin</span>
                            <div class="order-radios">
                                <label><input type="radio" name="place-import-from" value="TIMISOARA" checked> Timisoara</label>
                                <label><input type="radio" name="place-import-from" value="UTVIN"> Utvin</label>
                                <label><input type="radio" name="place-import-from" value="EXTERNE"> Externe</label>
                            </div>
                        </div>
                        <div class="order-block">
                            <label for="place-observations">Observații comandă</label>
                            <div class="order-ctrl">
                                <textarea id="place-observations" placeholder="Adăugați observații sau comentarii despre comandă..."></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="order-actions">
                        <button type="button" id="place-cancel" class="lg-btn lg-btn-default">Anulează</button>
                        <button type="button" id="place-submit" class="lg-btn lg-btn-success">Salvare</button>
                    </div>
                </div>
                <table class="caiet-lines">
                    <thead>
                    <tr>
                        <th>Nr. Crt.</th>
                        <th>PRODUS</th>
                        <th>COD PRODUS</th>
                        <th>CANT.</th>
                        <th>PRET</th>
                        <th>VALOARE.</th>
                    </tr>
                    </thead>
                    <tbody id="place-draft-lines"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';

    const ENDPOINT = '/admin/api/supplier_cart_endpoint.php';
    const TMP_ENDPOINT = '/admin/api/order_tmp_endpoint.php';
    const ORDER_ENDPOINT = '/admin/api/legacy_orders_endpoint.php';
    const CLIENTS_ENDPOINT = '/admin/api/caiet_comenzi_endpoint.php';
    const tbody = document.getElementById('supplier-cart-results');
    const summary = document.getElementById('supplier-cart-summary');
    const toast = document.getElementById('supplier-cart-toast');
    const clientsMap = new Map();
    let lastCartData = { cart: {} };

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, (char) => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
        }[char]));
    }

    function cleanWebshopDetail(value) {
        const text = String(value ?? '');
        if (/WinError|10106|chrome|chromium|playwright|не удается/i.test(text)) {
            return 'Linia a fost izolată după codul e-Cat, fără să redeschidem Chrome.';
        }
        return text;
    }

    function showToast(message, isError) {
        if (!toast) return;
        toast.textContent = message;
        toast.classList.remove('hidden');
        toast.classList.toggle('text-danger', Boolean(isError));
        setTimeout(() => toast.classList.add('hidden'), 4000);
    }

    function formatMoney(value) {
        const num = Number(value || 0);
        return num.toLocaleString('ro-RO', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' RON';
    }

    function supplierLabel(key) {
        return ({
            materom: 'Materom',
            elit: 'Elit',
            autopartner: 'Auto Partner',
            autonet: 'Autonet',
            autototal: 'Autototal',
            intercars: 'InterCars',
            bardi: 'Bardi Auto',
            szakal: 'Szakal Metal',
            site_produse: 'Site / ERP'
        })[key] || key;
    }

    function selectedItemKeys() {
        const keys = [];
        tbody.querySelectorAll('tr[data-supplier] .cart-select:checked').forEach((checkbox) => {
            const row = checkbox.closest('tr[data-supplier]');
            if (row) {
                keys.push(row.dataset.supplier + '|' + row.dataset.key);
            }
        });
        return keys;
    }

    function renderCart(data) {
        lastCartData = data || { cart: {} };
        const cart = data.cart || {};
        const rows = [];

        Object.entries(cart).forEach(([supplier, items]) => {
            Object.entries(items || {}).forEach(([key, item]) => {
                const qty = Number(item.qty || 1);
                const price = Number(item.price || 0);
                const subtotal = qty * price;
                const title = [item.manufacturer, item.product_name, item.product_code].filter(Boolean).join(' · ');

                rows.push(`
                    <tr class="border-b" data-supplier="${escapeHtml(supplier)}" data-key="${escapeHtml(key)}">
                        <td class="px-3 py-2"><input type="checkbox" class="cart-select" checked>${String(supplier).toLowerCase() === 'autototal' ? '<label class="text-xs"><input type="checkbox" class="cart-exclude"> exclus</label>' : ''}</td>
                        <td class="px-3 py-2">
                            <div class="font-medium">${escapeHtml(item.product_code || '')}</div>
                            <div class="text-xs opacity-60">${escapeHtml(title)}</div>
                            <div class="text-xs opacity-50">${escapeHtml(item.order_code || item.variant_code || '')}</div>
                            ${item.webshop_line_id ? `<div class="text-xs">ID webshop: ${escapeHtml(item.webshop_line_id)}</div>` : ''}
                        </td>
                        <td class="px-3 py-2">${escapeHtml(supplierLabel(supplier))}</td>
                        <td class="px-3 py-2 text-center">
                            <input type="number" min="1" class="cart-qty box h-8 w-16 rounded border px-2 text-center" value="${escapeHtml(qty)}">
                        </td>
                        <td class="px-3 py-2 text-right">${escapeHtml(formatMoney(price))}</td>
                        <td class="px-3 py-2 text-right font-medium">${escapeHtml(formatMoney(subtotal))}</td>
                        <td class="px-3 py-2">${escapeHtml(item.livrare || item.depozit || '—')}</td>
                        <td class="px-3 py-2 text-right">
                            <button type="button" class="cart-save box rounded border px-2 py-1 text-xs">Salvează</button>
                            <button type="button" class="cart-remove box rounded border px-2 py-1 text-xs text-danger">Șterge</button>
                        </td>
                    </tr>
                `);
            });
        });

        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="8" class="px-3 py-6 text-center opacity-70">Coșul este gol. Adaugă produse din <a class="underline" href="/admin/supplier-search">căutarea furnizorilor</a>.</td></tr>';
        } else {
            tbody.innerHTML = rows.join('');
        }

        const s = data.summary || {};
        if (summary) {
            summary.textContent = `${s.lines || 0} linii · ${s.items || 0} bucăți · total ${formatMoney(s.total || 0)}`;
        }
    }

    async function loadCart() {
        try {
            const response = await fetch(ENDPOINT + '?action=show');
            const data = await response.json();
            if (!response.ok || !data.success) {
                throw new Error(data.message || 'Nu s-a putut încărca coșul.');
            }
            renderCart(data);
        } catch (error) {
            tbody.innerHTML = `<tr><td colspan="8" class="px-3 py-6 text-center text-danger">${escapeHtml(error.message)}</td></tr>`;
            if (summary) summary.textContent = '';
            showToast(error.message, true);
        }
    }

    async function postAction(action, payload) {
        const response = await fetch(ENDPOINT + '?action=' + encodeURIComponent(action), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await response.json();
        if (!response.ok || !data.success) {
            throw new Error(data.message || 'Operația a eșuat.');
        }
        return data;
    }

    async function searchPlaceClients(query) {
        const list = document.getElementById('place-client-list');
        if (!list) return;
        if (String(query || '').trim().length < 1) {
            list.hidden = true;
            list.innerHTML = '';
            return;
        }
        try {
            const response = await fetch(CLIENTS_ENDPOINT, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ type_product: 'clienti_list', search: query, limit: 8 })
            });
            const data = await response.json();
            const items = (data.data && data.data.items) ? data.data.items : (data.data || []);
            const found = Array.isArray(items) ? items : [];
            list.innerHTML = found.map((client, index) => (
                '<li data-i="' + index + '">' + escapeHtml(client.nume || client.client_name || '') + ' · ' + escapeHtml(client.telefon || client.phone || '') + '</li>'
            )).join('');
            list.hidden = !found.length;
            list.querySelectorAll('li').forEach((item) => {
                item.onclick = () => {
                    const client = found[Number(item.dataset.i)];
                    if (!client) return;
                    document.getElementById('place-client-id').value = String(client.idclienti || client.id || '');
                    document.getElementById('place-client-search').value = client.nume || client.client_name || '';
                    document.getElementById('place-phone').value = client.telefon || client.phone || '';
                    document.getElementById('place-address').value = client.adresa || client.address || '';
                    document.getElementById('place-marca').value = client.marca || document.getElementById('place-marca').value;
                    list.hidden = true;
                };
            });
        } catch (_) {
            list.hidden = true;
        }
    }

    function paintPlaceLines() {
        const target = document.getElementById('place-draft-lines');
        if (!target) return;
        const selected = new Set(selectedItemKeys());
        const rows = [];
        let total = 0;
        let index = 0;
        Object.entries(lastCartData.cart || {}).forEach(([supplier, items]) => {
            Object.entries(items || {}).forEach(([key, item]) => {
                if (!selected.has(supplier + '|' + key)) return;
                const qty = Number(item.qty || 1);
                const price = Number(item.price || 0);
                const value = qty * price;
                total += value;
                index += 1;
                rows.push(
                    '<tr><td>' + index + '</td><td>' + escapeHtml([item.manufacturer, item.product_name].filter(Boolean).join(' ') || item.product_code || '') + '</td><td>' + escapeHtml(item.product_code || item.order_code || '') + '</td><td>' + qty + '</td><td>' + Number(price).toFixed(2) + '</td><td>' + value.toFixed(2) + '</td></tr>'
                );
            });
        });
        target.innerHTML = (rows.join('') || '<tr><td colspan="6">Nicio linie selectată.</td></tr>')
            + '<tr><td colspan="5" style="text-align:right"><strong>TOTAL</strong></td><td>' + total.toFixed(2) + '</td></tr>';
    }

    function openPlaceModal() {
        const modal = document.getElementById('supplier-cart-place-modal');
        if (!modal) return;
        if (modal.parentElement !== document.body) {
            document.body.appendChild(modal);
        }
        const now = new Date();
        const date = document.getElementById('place-date');
        const time = document.getElementById('place-time');
        if (date && !date.value) {
            date.value = now.toISOString().slice(0, 10);
        }
        if (time) {
            time.value = now.toLocaleTimeString('ro-RO', { hour: '2-digit', minute: '2-digit' });
        }
        paintPlaceLines();
        modal.classList.remove('hidden');
    }

    function closePlaceModal() {
        document.getElementById('supplier-cart-place-modal')?.classList.add('hidden');
    }

    function selectedImportFrom() {
        return document.querySelector('input[name="place-import-from"]:checked')?.value || 'TIMISOARA';
    }

    tbody?.addEventListener('click', async (event) => {
        const row = event.target.closest('tr[data-supplier]');
        if (!row) return;

        const supplier = row.dataset.supplier;
        const key = row.dataset.key;

        try {
            if (event.target.classList.contains('cart-remove')) {
                await postAction('remove', { supplier, key });
                showToast('Articol eliminat.');
                await loadCart();
                return;
            }

            if (event.target.classList.contains('cart-save')) {
                const qtyInput = row.querySelector('.cart-qty');
                const qty = Math.max(1, parseInt(qtyInput?.value || '1', 10));
                await postAction('update', { supplier, key, qty });
                showToast('Cantitate actualizată.');
                await loadCart();
            }
        } catch (error) {
            showToast(error.message, true);
        }
    });

    document.getElementById('supplier-cart-select-all')?.addEventListener('change', (event) => {
        const checked = event.target.checked;
        tbody.querySelectorAll('.cart-select').forEach((cb) => { cb.checked = checked; });
    });

    document.getElementById('supplier-cart-place-order')?.addEventListener('click', () => {
        const keys = selectedItemKeys();
        if (!keys.length) {
            showToast('Selectează cel puțin un articol.', true);
            return;
        }
        openPlaceModal();
    });
    document.getElementById('place-cancel')?.addEventListener('click', closePlaceModal);
    document.getElementById('place-close')?.addEventListener('click', closePlaceModal);
    document.getElementById('place-add-client')?.addEventListener('click', () => {
        window.location.href = '/admin/caiet-clienti';
    });

    const placeClientSearch = document.getElementById('place-client-search');
    let placeClientTimer = 0;
    placeClientSearch?.addEventListener('input', () => {
        document.getElementById('place-client-id').value = '';
        clearTimeout(placeClientTimer);
        placeClientTimer = setTimeout(() => searchPlaceClients(placeClientSearch.value), 200);
    });

    document.getElementById('place-submit')?.addEventListener('click', async () => {
        const importFrom = selectedImportFrom();
        const clientId = parseInt(document.getElementById('place-client-id')?.value || '0', 10);
        const keys = selectedItemKeys();

        if (!clientId) {
            showToast('Selectează clientul.', true);
            return;
        }

        const btn = document.getElementById('place-submit');
        if (btn) { btn.disabled = true; btn.textContent = 'Se procesează...'; }

        try {
            const response = await fetch(ORDER_ENDPOINT + '?action=place_from_supplier_cart', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    import_from: importFrom,
                    id_client: clientId,
                    order_item_keys: keys,
                    autototal_excluded_keys: Array.from(document.querySelectorAll('.cart-exclude:checked')).map(box => box.closest('tr')?.dataset.key).filter(Boolean),
                    data: document.getElementById('place-date')?.value || '',
                    idstare: parseInt(document.getElementById('place-status')?.value || '1', 10),
                    observations: document.getElementById('place-observations')?.value || '',
                    marca: document.getElementById('place-marca')?.value || '',
                    cont_awb: 'Utvin',
                })
            });
            const data = await response.json();
            if (!response.ok || !data.success) {
                throw new Error(data.message || 'Place order eșuat.');
            }

            closePlaceModal();
            if (data.mode === 'order_created' && data.order) {
                showToast(`Comandă #${data.order.idcomanda} creată (${formatMoney(data.order.total || 0)}).`);
                setTimeout(() => {
                    window.location.href = '/admin/orders?legacy_tab=' + encodeURIComponent(data.order.redirect_tab || 'tm');
                }, 900);
            } else {
                showToast(data.message || 'Operație finalizată.');
                await loadCart();
            }
        } catch (error) {
            showToast(error.message, true);
        } finally {
            if (btn) { btn.disabled = false; btn.textContent = 'Salvare'; }
        }
    });

    document.getElementById('supplier-cart-rehearse')?.addEventListener('click', async () => {
        const keys = selectedItemKeys();
        if (!keys.length) {
            showToast('Selectează cel puțin un articol.', true);
            return;
        }
        const log = document.getElementById('supplier-cart-rehearse-log');
        const btn = document.getElementById('supplier-cart-rehearse');
        if (btn) { btn.disabled = true; btn.textContent = 'Simulez webshop...'; }
        if (log) {
            log.classList.remove('hidden');
            log.innerHTML = 'Se deschide e-Cat. Comanda NU se trimite.';
        }
        try {
            const response = await fetch(ENDPOINT + '?action=rehearse_webshop', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ order_item_keys: keys })
            });
            const data = await response.json();
            const rows = data.results || [];
            if (log) {
                log.innerHTML = rows.map((row) => {
                    const steps = (row.steps || []).map((step) => {
                        const mark = step.ok ? 'OK' : 'Atenție';
                        return `<div>${mark} · ${escapeHtml(step.name || '')}: ${escapeHtml(cleanWebshopDetail(step.detail || ''))}</div>`;
                    }).join('');
                    const id = row.line_id ? ('ID ' + row.line_id) : 'fără ID';
                    const isolated = row.isolated ? 'doar linia noastră' : 'linia nu e izolată';
                    return `<div class="mb-2"><strong>${escapeHtml(row.supplier || '?')}</strong> · ${escapeHtml(id)} · ${isolated} · fără trimitere${steps}</div>`;
                }).join('') || escapeHtml(data.message || 'Fără rezultat.');
            }
            if (!response.ok || !data.success) {
                throw new Error(data.message || 'Simularea webshop a eșuat. Comanda NU a fost trimisă.');
            }
            showToast(data.message || 'Simulare gata, fără trimitere.');
            await loadCart();
        } catch (error) {
            showToast(cleanWebshopDetail(error.message), true);
        } finally {
            if (btn) { btn.disabled = false; btn.textContent = 'Pregătește webshop (fără trimitere)'; }
        }
    });

    document.getElementById('supplier-cart-import-tmp')?.addEventListener('click', async () => {
        try {
            const response = await fetch(TMP_ENDPOINT + '?action=import_supplier_cart', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ clear_first: true })
            });
            const data = await response.json();
            if (!response.ok || !data.success) {
                throw new Error(data.message || 'Import eșuat.');
            }
            showToast(`Import tmp: ${data.imported || 0} produse.`);
            if ((data.products?.length || 0) > 0 && confirm('Deschizi formularul de comandă?')) {
                window.location.href = '/admin/order-create';
            }
        } catch (error) {
            showToast(error.message, true);
        }
    });

    function bootCart() {
        loadCart().catch(function () {});
    }

    bootCart();
})();
</script>
