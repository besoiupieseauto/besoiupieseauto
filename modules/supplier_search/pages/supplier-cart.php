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

    <div id="supplier-cart-place-modal" class="hidden fixed inset-0 z-[9999] flex items-center justify-center bg-black/40 p-4">
        <div class="box w-full max-w-lg rounded-lg bg-background p-5 shadow-xl">
            <h3 class="mb-4 font-medium">Place order — comandă directă</h3>
            <div class="space-y-3">
                <div>
                    <label class="mb-1 block text-sm">Client ERP</label>
                    <input id="place-client-search" list="place-clients" class="box h-10 w-full rounded-md border px-3" placeholder="Caută client...">
                    <datalist id="place-clients"></datalist>
                    <input type="hidden" id="place-client-id">
                </div>
                <div>
                    <label class="mb-1 block text-sm">Import în</label>
                    <select id="place-import-from" class="box h-10 w-full rounded-md border px-3">
                        <option value="TIMISOARA">Timișoara (internă)</option>
                        <option value="UTVIN">Utvin (internă)</option>
                        <option value="EXTERNE">Comandă externă</option>
                        <option value="NUIMPORTA">Nu importa (doar elimină din coș)</option>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="mb-1 block text-sm">Data</label>
                        <input type="date" id="place-date" class="box h-10 w-full rounded-md border px-3">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm">Stare</label>
                        <select id="place-status" class="box h-10 w-full rounded-md border px-3">
                            <option value="1">Comandat</option>
                            <option value="2">Sosit</option>
                            <option value="3">Expediat</option>
                            <option value="4">Achitat</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="mb-1 block text-sm">Observații</label>
                    <textarea id="place-observations" class="box min-h-[60px] w-full rounded-md border px-3 py-2"></textarea>
                </div>
            </div>
            <div class="mt-5 flex justify-end gap-2">
                <button type="button" id="place-cancel" class="box h-10 rounded-md border px-4 text-sm">Anulează</button>
                <button type="button" id="place-submit" class="box h-10 rounded-md border bg-primary px-4 text-sm text-white">Confirmă place order</button>
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

    async function loadClients() {
        try {
            const response = await fetch(CLIENTS_ENDPOINT, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ type_product: 'clienti_list', limit: 200 })
            });
            const data = await response.json();
            if (!response.ok || !data.success) return;

            const datalist = document.getElementById('place-clients');
            if (!datalist) return;
            datalist.innerHTML = '';
            clientsMap.clear();
            const items = (data.data && data.data.items) ? data.data.items : (data.data || []);
            items.forEach((client) => {
                const label = [client.nume || client.client_name || client.name, client.telefon || client.phone].filter(Boolean).join(' · ');
                if (!label) return;
                clientsMap.set(label, client);
                const option = document.createElement('option');
                option.value = label;
                datalist.appendChild(option);
            });
        } catch (_) {}
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

    const placeModal = document.getElementById('supplier-cart-place-modal');
    document.getElementById('supplier-cart-place-order')?.addEventListener('click', () => {
        const keys = selectedItemKeys();
        if (!keys.length) {
            showToast('Selectează cel puțin un articol.', true);
            return;
        }
        placeModal?.classList.remove('hidden');
    });
    document.getElementById('place-cancel')?.addEventListener('click', () => placeModal?.classList.add('hidden'));

    document.getElementById('place-client-search')?.addEventListener('change', (event) => {
        const client = clientsMap.get(event.target.value);
        document.getElementById('place-client-id').value = client ? String(client.idclienti || client.id || client.randomn_id || '') : '';
    });

    document.getElementById('place-submit')?.addEventListener('click', async () => {
        const importFrom = document.getElementById('place-import-from')?.value || 'TIMISOARA';
        const clientId = parseInt(document.getElementById('place-client-id')?.value || '0', 10);
        const keys = selectedItemKeys();

        if (importFrom !== 'NUIMPORTA' && !clientId) {
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
                    cont_awb: 'Utvin',
                })
            });
            const data = await response.json();
            if (!response.ok || !data.success) {
                throw new Error(data.message || 'Place order eșuat.');
            }

            placeModal?.classList.add('hidden');
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
            if (btn) { btn.disabled = false; btn.textContent = 'Confirmă place order'; }
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

    const dateInput = document.getElementById('place-date');
    if (dateInput) dateInput.value = new Date().toISOString().slice(0, 10);

    function bootCart() {
        loadClients();
        loadCart().catch(function () {});
    }

    bootCart();
})();
</script>
