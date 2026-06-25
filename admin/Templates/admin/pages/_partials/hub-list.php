<div>
    <div id="hub-toast" class="hidden fixed right-5 top-5 z-50 rounded-md border bg-background px-4 py-3 text-sm shadow"></div>
    <div class="admin-panel mt-10" id="hub-list-panel">
        <div class="admin-panel__head">
            <div>
                <h2 class="m-0 text-lg font-medium" id="hub-title">Modul</h2>
                <p class="mt-1 mb-0 text-sm opacity-70" id="hub-subtitle">Date încărcate în fundal — pagina rămâne utilizabilă.</p>
            </div>
            <button type="button" id="hub-refresh" class="ms-auto box h-10 rounded-md border px-4 text-sm">Reîncarcă</button>
        </div>
        <div id="hub-extra" class="mt-5 grid grid-cols-12 gap-4"></div>
        <div id="hub-status" class="mb-3 text-xs opacity-60">Pregătire...</div>
        <div class="admin-table-wrap">
            <table class="w-full text-sm">
                <thead id="hub-thead"></thead>
                <tbody id="hub-tbody"></tbody>
            </table>
        </div>
        <div id="hub-pagination" class="mt-4"></div>
    </div>
</div>
<script>
(function (config) {
    'use strict';

    const HUB = '/admin/api/admin_hub_endpoint.php';
    let hubPage = 1;
    let hubMeta = { page: 1, total: 0, per_page: 10, total_pages: 1 };
    const Async = window.BpaAsync || {
        escapeHtml(v) {
            return String(v ?? '').replace(/[&<>"']/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
        },
        defer(fn) { setTimeout(() => fn().catch(() => {}), 0); },
        skeletonRows(cols, rows) {
            let h = '';
            for (let i = 0; i < (rows || 3); i++) {
                h += '<tr class="border-b animate-pulse"><td colspan="' + cols + '" class="px-3 py-3"><div class="h-4 rounded bg-foreground/10"></div></td></tr>';
            }
            return h;
        },
        async fetchJson(url) {
            const r = await fetch(url, { credentials: 'same-origin' });
            const d = await r.json();
            if (!r.ok || d.success === false) throw new Error(d.message || 'Eroare API');
            return d;
        }
    };

    const toast = document.getElementById('hub-toast');
    const tbody = document.getElementById('hub-tbody');
    const thead = document.getElementById('hub-thead');
    const statusEl = document.getElementById('hub-status');

    document.getElementById('hub-title').textContent = config.title || 'Modul';
    document.getElementById('hub-subtitle').textContent = config.subtitle || '';

    function showToast(msg, err) {
        if (!toast) return;
        toast.textContent = msg;
        toast.classList.remove('hidden');
        toast.classList.toggle('text-danger', !!err);
        setTimeout(() => toast.classList.add('hidden'), 4000);
    }

    function renderTable(rows, columns) {
        thead.innerHTML = '<tr>' + columns.map((c) =>
            '<th class="px-3 py-2 text-left">' + Async.escapeHtml(c.label) + '</th>'
        ).join('') + '</tr>';

        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="' + columns.length + '" class="px-3 py-6 text-center opacity-70">' +
                Async.escapeHtml(config.emptyText || 'Nu există înregistrări.') + '</td></tr>';
            return;
        }

        tbody.innerHTML = rows.map((row) => '<tr class="border-b">' + columns.map((c) => {
            const val = row[c.key];
            return '<td class="px-3 py-2">' + Async.escapeHtml(val ?? '') + '</td>';
        }).join('') + '</tr>').join('');
    }

    async function load(page) {
        if (page) hubPage = page;
        statusEl.textContent = 'Se sincronizează...';
        tbody.innerHTML = Async.skeletonRows(config.columns.length, 4);

        try {
            const result = await Async.fetchJson(HUB + '?action=' + encodeURIComponent(config.action) + '&page=' + hubPage + '&per_page=10');
            let rows = result.data || [];
            if (config.rowsFrom === 'tasks') rows = result.tasks || [];
            if (config.rowsFrom === 'oem') rows = result.oem || result.data || [];
            if (config.rowsFrom === 'furnizori') rows = result.furnizori || [];
            if (result.pagination) hubMeta = result.pagination;

            if (config.action === 'alerts' && Array.isArray(result.red_flags) && result.red_flags.length) {
                const extra = document.getElementById('hub-extra');
                if (extra) {
                    extra.innerHTML = result.red_flags.map((f) =>
                        '<div class="col-span-12 md:col-span-6 box rounded-xl border border-warning/30 p-4">' +
                        '<div class="text-sm font-medium text-warning">' + Async.escapeHtml(f.title || 'Alertă') + '</div>' +
                        '<div class="mt-1 text-xs opacity-70">' + Async.escapeHtml(f.detail || '') + '</div></div>'
                    ).join('');
                }
            }

            if (config.action === 'reports' && result.overview) {
                const o = result.overview;
                const extra = document.getElementById('hub-extra');
                if (extra) {
                    extra.innerHTML = `
                        <div class="col-span-12 md:col-span-3 box p-4"><div class="text-xs opacity-60">Comenzi azi</div><div class="text-xl font-semibold">${Async.escapeHtml(o.orders?.today_new ?? 0)}</div></div>
                        <div class="col-span-12 md:col-span-3 box p-4"><div class="text-xs opacity-60">Căutări azi</div><div class="text-xl font-semibold">${Async.escapeHtml(o.search_logs?.today ?? 0)}</div></div>
                        <div class="col-span-12 md:col-span-3 box p-4"><div class="text-xs opacity-60">Negăsite azi</div><div class="text-xl font-semibold">${Async.escapeHtml(o.search_logs?.today_not_found ?? 0)}</div></div>
                        <div class="col-span-12 md:col-span-3 box p-4"><div class="text-xs opacity-60">Produse active</div><div class="text-xl font-semibold">${Async.escapeHtml(o.products?.active ?? 0)}</div></div>`;
                }
            }

            renderTable(Array.isArray(rows) ? rows : [], config.columns);
            statusEl.textContent = (hubMeta.total || rows.length) + ' înregistrări · pag. ' + hubMeta.page + '/' + hubMeta.total_pages;
            if (window.BpaPagination) {
                BpaPagination.render(document.getElementById('hub-pagination'), hubMeta, (p) => load(p));
            }
        } catch (error) {
            tbody.innerHTML = '<tr><td colspan="' + config.columns.length +
                '" class="px-3 py-6 text-center text-danger">' + Async.escapeHtml(error.message) + '</td></tr>';
            statusEl.textContent = 'Eroare — verificați migrarea 025 și fișierul admin_hub_endpoint.php';
            showToast(error.message, true);
        }
    }

    document.getElementById('hub-refresh')?.addEventListener('click', () => load(hubPage));
    Async.defer(() => load(1));
})(<?= json_encode($hubConfig ?? [], JSON_UNESCAPED_UNICODE) ?>);
</script>
