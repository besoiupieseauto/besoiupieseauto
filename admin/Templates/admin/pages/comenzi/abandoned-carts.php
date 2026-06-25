<?php

declare(strict_types=1);

?>
<div>
    <div id="abandoned-carts-toast" class="hidden fixed right-5 top-5 z-50 rounded-md border bg-background px-4 py-3 text-sm shadow"></div>

    <div class="mt-8 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-lg font-medium">Coș abandonat</h2>
            <p class="mt-1 text-sm opacity-70">Clienți care au început checkout-ul sau au produse în coș fără să finalizeze comanda.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <select id="abandoned-carts-filter" class="box h-10 rounded-md border px-3 text-sm">
                <option value="open">Deschise</option>
                <option value="contacted">Contactate</option>
                <option value="converted">Convertite</option>
                <option value="dismissed">Respinse</option>
                <option value="all">Toate</option>
            </select>
            <button type="button" id="abandoned-carts-refresh" class="box h-10 rounded-md border px-4 text-sm">Reîncarcă</button>
        </div>
    </div>

    <div class="mt-5 overflow-auto box p-0">
        <table class="w-full text-sm">
            <thead class="border-b bg-foreground/5 text-left">
            <tr>
                <th class="px-4 py-3">Client</th>
                <th class="px-4 py-3">Contact</th>
                <th class="px-4 py-3 text-center">Produse</th>
                <th class="px-4 py-3 text-right">Total</th>
                <th class="px-4 py-3">Pas checkout</th>
                <th class="px-4 py-3">Ultima activitate</th>
                <th class="px-4 py-3">Sursă</th>
                <th class="px-4 py-3 text-center">Acțiuni</th>
            </tr>
            </thead>
            <tbody id="abandoned-carts-body">
            <tr><td colspan="8" class="px-4 py-6 text-center opacity-70">Se încarcă...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<script>
(function () {
    'use strict';

    const ENDPOINT = '/admin/api/cart_abandonments_endpoint.php';
    const tbody = document.getElementById('abandoned-carts-body');
    const filter = document.getElementById('abandoned-carts-filter');
    const toast = document.getElementById('abandoned-carts-toast');

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

    function formatMoney(value) {
        return Number(value || 0).toLocaleString('ro-RO', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' RON';
    }

    function whatsappLink(phone, name, total) {
        const digits = String(phone || '').replace(/\D+/g, '');
        if (!digits) return '';
        const text = encodeURIComponent(`Buna ziua${name ? ' ' + name : ''}, am vazut ca ati lasat produse in cos (${total} RON). Va putem ajuta sa finalizati comanda?`);
        return `https://wa.me/4${digits.replace(/^0/, '')}?text=${text}`;
    }

    function renderRows(items) {
        if (!tbody) return;
        if (!items.length) {
            tbody.innerHTML = '<tr><td colspan="8" class="px-4 py-6 text-center opacity-70">Niciun coș abandonat pentru filtrul selectat.</td></tr>';
            return;
        }

        tbody.innerHTML = items.map((row) => {
            const wa = whatsappLink(row.phone, row.client_name, formatMoney(row.total_amount));
            const canStatus = row.id > 0;
            return `<tr class="border-b" data-id="${escapeHtml(row.id)}">
                <td class="px-4 py-3 font-medium">${escapeHtml(row.client_name || '—')}</td>
                <td class="px-4 py-3">
                    <div>${escapeHtml(row.phone || '—')}</div>
                    <div class="text-xs opacity-60">${escapeHtml(row.email || '')}</div>
                </td>
                <td class="px-4 py-3 text-center">${escapeHtml(row.items_count)}</td>
                <td class="px-4 py-3 text-right">${escapeHtml(formatMoney(row.total_amount))}</td>
                <td class="px-4 py-3">Pas ${escapeHtml(row.checkout_step)}</td>
                <td class="px-4 py-3">${escapeHtml(row.last_seen_at || '')}</td>
                <td class="px-4 py-3 text-xs">${row.source === 'server_cart' ? 'Coș server' : 'Checkout lead'}</td>
                <td class="px-4 py-3 text-center">
                    ${wa ? `<a href="${wa}" target="_blank" rel="noopener" class="text-primary underline text-xs mr-2">WhatsApp</a>` : ''}
                    ${canStatus ? `
                        <button type="button" class="ab-mark-contacted text-xs underline text-primary mr-1">Contactat</button>
                        <button type="button" class="ab-mark-dismissed text-xs underline opacity-70">Respinge</button>
                    ` : '<span class="text-xs opacity-50">Doar coș server</span>'}
                </td>
            </tr>`;
        }).join('');
    }

    async function loadList() {
        if (!tbody) return;
        tbody.innerHTML = '<tr><td colspan="8" class="px-4 py-6 text-center opacity-70">Se încarcă...</td></tr>';
        try {
            const status = filter?.value || 'open';
            const response = await fetch(`${ENDPOINT}?status=${encodeURIComponent(status)}`);
            const data = await response.json();
            if (!response.ok || !data.success) {
                throw new Error(data.message || 'Eroare la încărcare.');
            }
            renderRows(data.data?.items || []);
        } catch (error) {
            tbody.innerHTML = `<tr><td colspan="8" class="px-4 py-6 text-center text-danger">${escapeHtml(error.message)}</td></tr>`;
        }
    }

    async function setStatus(id, status) {
        const response = await fetch(ENDPOINT, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'set_status', id, status }),
        });
        const data = await response.json();
        if (!response.ok || !data.success) {
            throw new Error(data.message || 'Eroare.');
        }
    }

    document.getElementById('abandoned-carts-refresh')?.addEventListener('click', () => loadList());
    filter?.addEventListener('change', () => loadList());

    tbody?.addEventListener('click', async (event) => {
        const row = event.target.closest('tr[data-id]');
        if (!row) return;
        const id = parseInt(row.dataset.id || '0', 10);
        if (id <= 0) return;

        try {
            if (event.target.classList.contains('ab-mark-contacted')) {
                await setStatus(id, 'contacted');
                showToast('Marcat contactat.');
                loadList();
            }
            if (event.target.classList.contains('ab-mark-dismissed')) {
                await setStatus(id, 'dismissed');
                showToast('Marcat respins.');
                loadList();
            }
        } catch (error) {
            showToast(error.message, true);
        }
    });

    loadList();
})();
</script>
