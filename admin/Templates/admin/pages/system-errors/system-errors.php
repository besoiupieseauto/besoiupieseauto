<div class="grid grid-cols-12 gap-6 mt-6">
    <div id="system-errors-toast" class="hidden fixed right-5 top-5 z-50 rounded-md border bg-background px-4 py-3 text-sm shadow"></div>

    <div class="col-span-12 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-lg font-medium">Jurnal erori sistem</h2>
            <p class="text-sm opacity-70">Monitorizează erorile din procesare fundal: cron, coadă joburi, TecDoc, cache API.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <button type="button" id="system-errors-refresh" class="box rounded-md border bg-primary px-4 py-2 text-sm text-white">Reîncarcă</button>
        </div>
    </div>

    <div class="col-span-12 sm:col-span-6 xl:col-span-3">
        <div class="box p-4">
            <div class="text-xs uppercase opacity-70">Total înregistrări</div>
            <div id="stat-total" class="mt-2 text-2xl font-semibold">0</div>
        </div>
    </div>
    <div class="col-span-12 sm:col-span-6 xl:col-span-3">
        <div class="box p-4">
            <div class="text-xs uppercase opacity-70">Nerezolvate</div>
            <div id="stat-unresolved" class="mt-2 text-2xl font-semibold text-danger">0</div>
        </div>
    </div>
    <div class="col-span-12 sm:col-span-6 xl:col-span-3">
        <div class="box p-4">
            <div class="text-xs uppercase opacity-70">Azi (total)</div>
            <div id="stat-today" class="mt-2 text-2xl font-semibold">0</div>
        </div>
    </div>
    <div class="col-span-12 sm:col-span-6 xl:col-span-3">
        <div class="box p-4">
            <div class="text-xs uppercase opacity-70">Critice / eroare azi</div>
            <div id="stat-critical-today" class="mt-2 text-2xl font-semibold text-warning">0</div>
        </div>
    </div>

    <div class="col-span-12 xl:col-span-4">
        <div class="box p-5 h-full">
            <h3 class="text-base font-medium">Erori pe canal (7 zile, nerezolvate)</h3>
            <div id="system-errors-by-channel" class="mt-4 space-y-2 text-sm"></div>
        </div>
    </div>

    <div class="col-span-12 xl:col-span-8">
        <div class="box p-5">
            <form id="system-errors-filters" class="grid grid-cols-12 gap-3">
                <div class="col-span-12 sm:col-span-6 lg:col-span-3">
                    <label class="text-xs opacity-70">Canal</label>
                    <select name="channel" class="form-control mt-1 w-full rounded-md border px-3 py-2 text-sm">
                        <option value="">Toate</option>
                        <option value="cron">cron</option>
                        <option value="queue">queue</option>
                        <option value="tecdoc">tecdoc</option>
                        <option value="rapidapi">rapidapi</option>
                        <option value="cache">cache</option>
                        <option value="import">import</option>
                        <option value="general">general</option>
                    </select>
                </div>
                <div class="col-span-12 sm:col-span-6 lg:col-span-2">
                    <label class="text-xs opacity-70">Nivel</label>
                    <select name="level" class="form-control mt-1 w-full rounded-md border px-3 py-2 text-sm">
                        <option value="">Toate</option>
                        <option value="critical">critical</option>
                        <option value="error">error</option>
                        <option value="warning">warning</option>
                        <option value="info">info</option>
                    </select>
                </div>
                <div class="col-span-12 sm:col-span-6 lg:col-span-2">
                    <label class="text-xs opacity-70">De la</label>
                    <input type="date" name="date_from" class="form-control mt-1 w-full rounded-md border px-3 py-2 text-sm">
                </div>
                <div class="col-span-12 sm:col-span-6 lg:col-span-2">
                    <label class="text-xs opacity-70">Până la</label>
                    <input type="date" name="date_to" class="form-control mt-1 w-full rounded-md border px-3 py-2 text-sm">
                </div>
                <div class="col-span-12 lg:col-span-3">
                    <label class="text-xs opacity-70">Căutare</label>
                    <input type="search" name="q" placeholder="mesaj, fișier sursă…" class="form-control mt-1 w-full rounded-md border px-3 py-2 text-sm">
                </div>
                <div class="col-span-12 flex flex-wrap items-center gap-4">
                    <label class="inline-flex items-center gap-2 text-sm">
                        <input type="checkbox" name="unresolved_only" value="1" checked>
                        Doar nerezolvate
                    </label>
                    <button type="submit" class="box rounded-md border bg-primary px-4 py-2 text-sm text-white">Filtrează</button>
                </div>
            </form>
        </div>
    </div>

    <div class="col-span-12">
        <div class="box p-5 overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b text-left text-xs uppercase opacity-70">
                        <th class="py-2 pr-3">Data</th>
                        <th class="py-2 pr-3">Nivel</th>
                        <th class="py-2 pr-3">Canal</th>
                        <th class="py-2 pr-3">Mesaj</th>
                        <th class="py-2 pr-3">Sursă</th>
                        <th class="py-2 pr-3">Status</th>
                        <th class="py-2">Acțiuni</th>
                    </tr>
                </thead>
                <tbody id="system-errors-table">
                    <tr><td colspan="7" class="py-6 text-center opacity-60">Se încarcă…</td></tr>
                </tbody>
            </table>
            <div id="system-errors-meta" class="mt-3 text-xs opacity-70"></div>
        </div>
    </div>
</div>

<style>
.sys-err-level-critical, .sys-err-level-error { color: #dc2626; font-weight: 600; }
.sys-err-level-warning { color: #d97706; }
.sys-err-level-info { color: #2563eb; }
.sys-err-badge {
    display: inline-block;
    padding: 0.15rem 0.5rem;
    border-radius: 9999px;
    font-size: 0.7rem;
    background: rgba(107, 114, 128, 0.12);
}
.sys-err-badge-resolved { background: rgba(16, 185, 129, 0.15); color: #059669; }
.sys-err-badge-open { background: rgba(239, 68, 68, 0.12); color: #b91c1c; }
.sys-err-context {
    margin-top: 0.35rem;
    font-size: 0.7rem;
    opacity: 0.75;
    word-break: break-all;
}
</style>

<script>
(function () {
    'use strict';

    const ENDPOINT = '/admin/api/system_errors_endpoint.php';
    const form = document.getElementById('system-errors-filters');
    const table = document.getElementById('system-errors-table');
    const metaEl = document.getElementById('system-errors-meta');
    const toast = document.getElementById('system-errors-toast');
    const byChannelEl = document.getElementById('system-errors-by-channel');

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, (c) => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
        }[c]));
    }

    function showToast(message, isError) {
        if (!toast) return;
        toast.textContent = message;
        toast.classList.remove('hidden');
        toast.classList.toggle('text-danger', Boolean(isError));
        setTimeout(() => toast.classList.add('hidden'), 3500);
    }

    function formPayload() {
        const payload = { type_product: 'list', limit: 100, offset: 0 };
        if (!form) return payload;
        const data = new FormData(form);
        data.forEach((value, key) => {
            if (key === 'unresolved_only') {
                if (form.querySelector('[name="unresolved_only"]')?.checked) {
                    payload.unresolved_only = true;
                }
                return;
            }
            if (String(value).trim() !== '') payload[key] = value;
        });
        return payload;
    }

    async function apiCall(payload) {
        const response = await fetch(ENDPOINT, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const result = await response.json();
        if (!response.ok || !result.success) {
            throw new Error(result.message || 'Eroare la încărcare.');
        }
        return result;
    }

    function renderStats(stats) {
        const s = stats || {};
        const set = (id, val) => {
            const el = document.getElementById(id);
            if (el) el.textContent = Number(val || 0).toLocaleString('ro-RO');
        };
        set('stat-total', s.total);
        set('stat-unresolved', s.unresolved);
        set('stat-today', s.today);
        set('stat-critical-today', s.critical_today);

        if (!byChannelEl) return;
        const channels = s.by_channel || {};
        const keys = Object.keys(channels);
        if (!keys.length) {
            byChannelEl.innerHTML = '<p class="opacity-60">Nicio eroare nerezolvată în ultimele 7 zile.</p>';
            return;
        }
        byChannelEl.innerHTML = keys.map((ch) =>
            `<div class="flex justify-between gap-2 border-b border-dashed py-1">
                <span class="sys-err-badge">${escapeHtml(ch)}</span>
                <strong>${Number(channels[ch]).toLocaleString('ro-RO')}</strong>
            </div>`
        ).join('');
    }

    function renderTable(items, total) {
        if (!table) return;
        if (!items || !items.length) {
            table.innerHTML = '<tr><td colspan="7" class="py-6 text-center opacity-60">Nicio înregistrare pentru filtrele selectate.</td></tr>';
        } else {
            table.innerHTML = items.map((row) => {
                const ctx = row.context && Object.keys(row.context).length
                    ? `<div class="sys-err-context">${escapeHtml(JSON.stringify(row.context))}</div>` : '';
                const resolved = row.is_resolved;
                return `<tr class="border-b border-dashed" data-id="${Number(row.id)}">
                    <td class="py-2 pr-3 whitespace-nowrap">${escapeHtml(row.created_at)}</td>
                    <td class="py-2 pr-3 sys-err-level-${escapeHtml(row.level)}">${escapeHtml(row.level)}</td>
                    <td class="py-2 pr-3"><span class="sys-err-badge">${escapeHtml(row.channel)}</span></td>
                    <td class="py-2 pr-3 max-w-md">${escapeHtml(row.message)}${ctx}</td>
                    <td class="py-2 pr-3 text-xs opacity-70">${escapeHtml(row.source_file || '—')}</td>
                    <td class="py-2 pr-3">
                        <span class="sys-err-badge ${resolved ? 'sys-err-badge-resolved' : 'sys-err-badge-open'}">
                            ${resolved ? 'rezolvat' : 'deschis'}
                        </span>
                    </td>
                    <td class="py-2">
                        ${resolved ? '' : `<button type="button" class="sys-err-resolve text-xs text-primary underline" data-id="${Number(row.id)}">Marchează rezolvat</button>`}
                    </td>
                </tr>`;
            }).join('');
        }
        if (metaEl) {
            metaEl.textContent = `${items.length} afișate din ${Number(total || 0).toLocaleString('ro-RO')} total`;
        }
    }

    async function loadList() {
        const result = await apiCall(formPayload());
        renderStats(result.stats);
        renderTable(result.data || [], result.total);
    }

    async function resolveError(id) {
        await apiCall({ type_product: 'resolve', id, resolved: true });
        showToast('Eroare marcată ca rezolvată.');
        await loadList();
    }

    document.getElementById('system-errors-refresh')?.addEventListener('click', () => {
        loadList().catch((e) => showToast(e.message, true));
    });

    form?.addEventListener('submit', (e) => {
        e.preventDefault();
        loadList().catch((err) => showToast(err.message, true));
    });

    table?.addEventListener('click', (e) => {
        const btn = e.target.closest('.sys-err-resolve');
        if (!btn) return;
        const id = Number(btn.dataset.id || 0);
        if (id <= 0) return;
        resolveError(id).catch((err) => showToast(err.message, true));
    });

    loadList().catch((e) => showToast(e.message, true));
})();
</script>
