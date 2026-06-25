<div class="grid grid-cols-12 gap-6 mt-6">
    <div id="searchlogs-toast" class="hidden fixed right-5 top-5 z-50 rounded-md border bg-background px-4 py-3 text-sm shadow"></div>

    <div class="col-span-12 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-lg font-medium">Jurnal căutări VIN / OEM</h2>
            <p class="text-sm opacity-70">Monitorizează scanările de pe site — cereri găsite și negăsite, cu detalii complete.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <button type="button" id="searchlogs-export" class="box rounded-md border px-4 py-2 text-sm">Export CSV</button>
            <button type="button" id="searchlogs-refresh" class="box rounded-md border bg-primary px-4 py-2 text-sm text-white">Reîncarcă</button>
        </div>
    </div>

    <div class="col-span-12">
        <div class="inline-flex rounded-lg border p-1" role="tablist" aria-label="Filtru rezultat scanare">
            <button type="button" id="searchlogs-tab-missing" class="searchlogs-tab active rounded-md px-4 py-2 text-sm font-medium" data-tab="missing" aria-selected="true">
                Negăsite <span id="tab-badge-missing" class="ml-1 rounded-full bg-danger/15 px-2 py-0.5 text-xs text-danger">0</span>
            </button>
            <button type="button" id="searchlogs-tab-found" class="searchlogs-tab rounded-md px-4 py-2 text-sm font-medium" data-tab="found" aria-selected="false">
                Găsite <span id="tab-badge-found" class="ml-1 rounded-full bg-success/15 px-2 py-0.5 text-xs text-success">0</span>
            </button>
        </div>
    </div>

    <div class="col-span-12 sm:col-span-6 xl:col-span-2">
        <div class="box p-4">
            <div class="text-xs uppercase opacity-70">Total înregistrări</div>
            <div id="stat-total" class="mt-2 text-2xl font-semibold">0</div>
        </div>
    </div>
    <div class="col-span-12 sm:col-span-6 xl:col-span-2">
        <div class="box p-4">
            <div class="text-xs uppercase opacity-70">Găsite</div>
            <div id="stat-found" class="mt-2 text-2xl font-semibold text-success">0</div>
        </div>
    </div>
    <div class="col-span-12 sm:col-span-6 xl:col-span-2">
        <div class="box p-4">
            <div class="text-xs uppercase opacity-70">Negăsite</div>
            <div id="stat-not-found" class="mt-2 text-2xl font-semibold text-danger">0</div>
        </div>
    </div>
    <div class="col-span-12 sm:col-span-6 xl:col-span-2">
        <div class="box p-4">
            <div class="text-xs uppercase opacity-70">Azi (total)</div>
            <div id="stat-today" class="mt-2 text-2xl font-semibold">0</div>
            <div class="mt-1 text-xs opacity-60"><span id="stat-today-found" class="text-success">0</span> găsite · <span id="stat-today-not-found" class="text-danger">0</span> negăsite</div>
        </div>
    </div>
    <div class="col-span-12 sm:col-span-6 xl:col-span-2">
        <div class="box p-4">
            <div class="text-xs uppercase opacity-70">VIN</div>
            <div class="mt-2 text-sm"><span class="text-success font-semibold" id="stat-vin-found">0</span> găsite · <span class="text-danger font-semibold" id="stat-vin-not-found">0</span> negăsite</div>
        </div>
    </div>
    <div class="col-span-12 sm:col-span-6 xl:col-span-2">
        <div class="box p-4">
            <div class="text-xs uppercase opacity-70">OEM / Nume</div>
            <div class="mt-2 text-sm"><span class="text-success font-semibold" id="stat-oem-found">0</span> OEM · <span class="text-danger font-semibold" id="stat-oem-not-found">0</span> negăsite</div>
            <div class="text-xs opacity-60"><span id="stat-name-found">0</span> nume găsite · <span id="stat-name-not-found">0</span> negăsite</div>
        </div>
    </div>

    <div class="col-span-12 xl:col-span-6">
        <div class="box p-5 h-full">
            <div class="flex items-center gap-2">
                <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-danger/15 text-danger text-xs font-bold">!</span>
                <h3 class="text-base font-medium">Top cereri negăsite</h3>
            </div>
            <p class="mt-1 text-xs opacity-60">Prioritizează extinderea stocului pentru aceste coduri.</p>
            <div id="searchlogs-top-missing" class="mt-4 space-y-2 text-sm"></div>
        </div>
    </div>

    <div class="col-span-12 xl:col-span-6">
        <div class="box p-5 h-full">
            <div class="flex items-center gap-2">
                <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-success/15 text-success text-xs font-bold">✓</span>
                <h3 class="text-base font-medium">Top cereri găsite</h3>
            </div>
            <p class="mt-1 text-xs opacity-60">Scanări reușite — cele mai căutate coduri cu rezultate în stoc.</p>
            <div id="searchlogs-top-found" class="mt-4 space-y-2 text-sm"></div>
        </div>
    </div>

    <div class="col-span-12">
        <div class="box p-5">
            <form id="searchlogs-filters" class="grid grid-cols-12 gap-3">
                <input type="hidden" name="result_tab" id="searchlogs-result-tab" value="missing">
                <label class="col-span-12 md:col-span-3">
                    <span class="mb-1 block text-sm">Căutare</span>
                    <input type="text" name="q" class="box h-10 w-full rounded-md border px-3" placeholder="VIN, OEM, vehicul...">
                </label>
                <label class="col-span-12 md:col-span-2">
                    <span class="mb-1 block text-sm">Tip</span>
                    <select name="query_type" class="box h-10 w-full rounded-md border px-3">
                        <option value="">Toate</option>
                        <option value="vin">VIN</option>
                        <option value="oem">OEM</option>
                        <option value="name">Nume</option>
                    </select>
                </label>
                <label class="col-span-12 md:col-span-2">
                    <span class="mb-1 block text-sm">De la</span>
                    <input type="date" name="date_from" class="box h-10 w-full rounded-md border px-3">
                </label>
                <label class="col-span-12 md:col-span-2">
                    <span class="mb-1 block text-sm">Până la</span>
                    <input type="date" name="date_to" class="box h-10 w-full rounded-md border px-3">
                </label>
                <label class="col-span-12 md:col-span-3 flex items-end">
                    <button type="submit" class="ml-auto box h-10 rounded-md border bg-primary px-4 text-white">Filtrează</button>
                </label>
            </form>

            <div class="mt-4 overflow-auto">
                <table class="w-full text-sm">
                    <thead class="border-b bg-foreground/5">
                    <tr>
                        <th class="px-3 py-2 text-left">Data</th>
                        <th class="px-3 py-2 text-left">Tip</th>
                        <th class="px-3 py-2 text-left">Valoare scanată</th>
                        <th class="px-3 py-2 text-left">Vehicul</th>
                        <th class="px-3 py-2 text-center">Status</th>
                        <th class="px-3 py-2 text-left">Rezumat</th>
                        <th class="px-3 py-2 text-right">Detalii</th>
                    </tr>
                    </thead>
                    <tbody id="searchlogs-table">
                    <tr>
                        <td colspan="7" class="px-3 py-6 text-center opacity-70">Se încarcă...</td>
                    </tr>
                    </tbody>
                </table>
            </div>
            <div id="searchlogs-meta" class="mt-3 text-xs opacity-70"></div>
            <div id="searchlogs-pagination" class="mt-3"></div>
        </div>
    </div>
</div>

<div id="searchlogs-detail-modal" class="hidden fixed inset-0 z-[100] flex items-center justify-center bg-black/50 p-4">
    <div class="box max-h-[90vh] w-full max-w-3xl overflow-auto rounded-xl p-6 shadow-xl">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h3 class="text-lg font-semibold">Detalii scanare</h3>
                <p id="searchlogs-detail-subtitle" class="mt-1 text-sm opacity-70"></p>
            </div>
            <button type="button" id="searchlogs-detail-close" class="rounded-md border px-3 py-1 text-sm">Închide</button>
        </div>
        <div id="searchlogs-detail-body" class="mt-5 space-y-4 text-sm"></div>
    </div>
</div>

<style>
.searchlogs-tab.active {
    background: rgb(var(--color-primary) / 0.12);
    color: rgb(var(--color-primary));
}
.searchlogs-type-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    border-radius: 9999px;
    padding: 0.15rem 0.55rem;
    font-size: 0.7rem;
    font-weight: 600;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}
.searchlogs-type-vin { background: rgba(59, 130, 246, 0.15); color: #2563eb; }
.searchlogs-type-oem { background: rgba(26, 188, 156, 0.15); color: #0d9488; }
.searchlogs-type-name { background: rgba(168, 85, 247, 0.15); color: #7c3aed; }
.searchlogs-detail-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.75rem 1rem;
}
@media (max-width: 640px) {
    .searchlogs-detail-grid { grid-template-columns: 1fr; }
}
.searchlogs-kv dt { font-size: 0.7rem; text-transform: uppercase; opacity: 0.6; }
.searchlogs-kv dd { font-weight: 500; word-break: break-word; }
.searchlogs-product-chip {
    border: 1px solid rgba(0,0,0,0.08);
    border-radius: 0.5rem;
    padding: 0.5rem 0.75rem;
}
</style>

<script>
(function () {
    'use strict';

    const ENDPOINT = '/admin/api/search_logs_endpoint.php';
    const form = document.getElementById('searchlogs-filters');
    const table = document.getElementById('searchlogs-table');
    const topMissingEl = document.getElementById('searchlogs-top-missing');
    const topFoundEl = document.getElementById('searchlogs-top-found');
    const metaEl = document.getElementById('searchlogs-meta');
    const toast = document.getElementById('searchlogs-toast');
    const resultTabInput = document.getElementById('searchlogs-result-tab');
    const detailModal = document.getElementById('searchlogs-detail-modal');
    const detailBody = document.getElementById('searchlogs-detail-body');
    const detailSubtitle = document.getElementById('searchlogs-detail-subtitle');
    let currentPage = 1;
    let activeTab = 'missing';
    let cachedRows = [];
    let listMeta = { page: 1, total: 0, per_page: 10, total_pages: 1 };

    const TYPE_LABELS = { vin: 'VIN', oem: 'OEM', name: 'Nume' };
    const TYPE_ICONS = { vin: '🔑', oem: '⚙', name: '📝' };

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
        setTimeout(() => toast.classList.add('hidden'), 3500);
    }

    function typeBadge(type) {
        const key = String(type || 'name').toLowerCase();
        const label = TYPE_LABELS[key] || key.toUpperCase();
        const icon = TYPE_ICONS[key] || '•';
        return `<span class="searchlogs-type-badge searchlogs-type-${escapeHtml(key)}">${icon} ${escapeHtml(label)}</span>`;
    }

    function setActiveTab(tab) {
        activeTab = tab === 'found' ? 'found' : 'missing';
        if (resultTabInput) resultTabInput.value = activeTab;
        document.querySelectorAll('.searchlogs-tab').forEach((btn) => {
            const isActive = btn.dataset.tab === activeTab;
            btn.classList.toggle('active', isActive);
            btn.setAttribute('aria-selected', isActive ? 'true' : 'false');
        });
    }

    function formPayload(page) {
        const p = page || currentPage;
        const payload = { type_product: 'list', limit: 10, page: p, offset: (p - 1) * 10 };
        if (activeTab === 'found') {
            payload.found = '1';
            payload.not_found_only = false;
        } else {
            payload.not_found_only = true;
        }
        if (!form) return payload;
        const data = new FormData(form);
        data.forEach((value, key) => {
            if (key === 'result_tab') return;
            if (String(value).trim() !== '') payload[key] = value;
        });
        return payload;
    }

    async function apiList(payload) {
        const response = await fetch(ENDPOINT, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const result = await response.json();
        if (!response.ok || !result.success) {
            throw new Error(result.message || 'Eroare la încărcarea jurnalului.');
        }
        return result;
    }

    function renderStats(stats) {
        const map = {
            'stat-total': stats.total,
            'stat-found': stats.found,
            'stat-not-found': stats.not_found,
            'stat-today': stats.today,
            'stat-today-found': stats.today_found,
            'stat-today-not-found': stats.today_not_found,
            'stat-vin-found': stats.vin_found,
            'stat-vin-not-found': stats.vin_not_found,
            'stat-oem-found': stats.oem_found,
            'stat-oem-not-found': stats.oem_not_found,
            'stat-name-found': stats.name_found,
            'stat-name-not-found': stats.name_not_found,
            'tab-badge-missing': stats.not_found,
            'tab-badge-found': stats.found
        };
        Object.entries(map).forEach(([id, value]) => {
            const el = document.getElementById(id);
            if (el) el.textContent = String(value ?? 0);
        });
    }

    function renderTopList(container, items, mode) {
        if (!container) return;
        const emptyMsg = mode === 'found'
            ? 'Nu există încă scanări reușite înregistrate.'
            : 'Nu există cereri negăsite încă.';
        if (!items || !items.length) {
            container.innerHTML = `<div class="opacity-70">${emptyMsg}</div>`;
            return;
        }
        container.innerHTML = items.map((item) => {
            const extra = mode === 'found'
                ? `${escapeHtml(item.total_results || 0)} produse total · max ${escapeHtml(item.max_results || 0)}`
                : `${escapeHtml(item.attempts || 0)} încercări`;
            return `
                <div class="rounded-lg border px-3 py-2">
                    <div class="flex flex-wrap items-center gap-2">
                        ${typeBadge(item.query_type)}
                        <span class="font-medium">${escapeHtml(item.query_value || '')}</span>
                    </div>
                    <div class="mt-1 text-xs opacity-70">${extra} · ultima: ${escapeHtml(item.last_seen || '')}</div>
                    ${item.vehicle_label ? `<div class="mt-1 text-xs opacity-60">${escapeHtml(item.vehicle_label)}</div>` : ''}
                </div>
            `;
        }).join('');
    }

    function rowSummary(row) {
        const count = Number(row.result_count ?? 0);
        const notice = String(row.notice || '').trim();
        if (Number(row.found) === 1) {
            return count > 0 ? `${count} produse găsite` : 'Scanare reușită';
        }
        return notice || 'Fără rezultate în stoc';
    }

    function renderTable(items) {
        if (!table) return;
        cachedRows = items || [];
        if (!items || !items.length) {
            const label = activeTab === 'found' ? 'găsite' : 'negăsite';
            table.innerHTML = `<tr><td colspan="7" class="px-3 py-6 text-center opacity-70">Nu există înregistrări ${label} pentru filtrele selectate.</td></tr>`;
            return;
        }
        table.innerHTML = items.map((row, index) => {
            const isFound = Number(row.found) === 1;
            const statusBadge = isFound
                ? '<span class="rounded-full bg-success/15 px-2 py-0.5 text-success">Găsit</span>'
                : '<span class="rounded-full bg-danger/15 px-2 py-0.5 text-danger">Negăsit</span>';
            return `
                <tr class="border-b">
                    <td class="px-3 py-2 whitespace-nowrap">${escapeHtml(row.created_at || '')}</td>
                    <td class="px-3 py-2">${typeBadge(row.query_type)}</td>
                    <td class="px-3 py-2 font-medium font-mono text-xs sm:text-sm">${escapeHtml(row.query_value || '')}</td>
                    <td class="px-3 py-2">${escapeHtml(row.vehicle_label || '—')}</td>
                    <td class="px-3 py-2 text-center">${statusBadge}<div class="mt-1 text-xs opacity-60">${escapeHtml(row.result_count ?? 0)} rez.</div></td>
                    <td class="px-3 py-2 max-w-xs truncate" title="${escapeHtml(rowSummary(row))}">${escapeHtml(rowSummary(row))}</td>
                    <td class="px-3 py-2 text-right">
                        <button type="button" class="searchlogs-detail-btn rounded-md border px-2 py-1 text-xs" data-index="${index}">Vezi log</button>
                    </td>
                </tr>
            `;
        }).join('');

        table.querySelectorAll('.searchlogs-detail-btn').forEach((btn) => {
            btn.addEventListener('click', () => {
                const idx = Number(btn.getAttribute('data-index'));
                if (!Number.isNaN(idx) && cachedRows[idx]) openDetail(cachedRows[idx]);
            });
        });
    }

    function renderMetaBlock(title, entries) {
        const rows = entries.filter(([, value]) => value !== null && value !== undefined && String(value).trim() !== '');
        if (!rows.length) return '';
        return `
            <div>
                <h4 class="mb-2 font-medium">${escapeHtml(title)}</h4>
                <dl class="searchlogs-detail-grid">
                    ${rows.map(([label, value]) => `
                        <div class="searchlogs-kv">
                            <dt>${escapeHtml(label)}</dt>
                            <dd>${escapeHtml(value)}</dd>
                        </div>
                    `).join('')}
                </dl>
            </div>
        `;
    }

    function renderProductsPreview(products) {
        if (!products || !products.length) return '';
        return `
            <div>
                <h4 class="mb-2 font-medium">Produse găsite (preview)</h4>
                <div class="space-y-2">
                    ${products.map((product) => `
                        <div class="searchlogs-product-chip">
                            <div class="font-medium">${escapeHtml(product.name || '—')}</div>
                            <div class="text-xs opacity-70">
                                Cod: ${escapeHtml(product.code || '—')}
                                · Brand: ${escapeHtml(product.brand || '—')}
                                ${product.id ? ` · <a class="text-primary underline" href="/product.php?id=${encodeURIComponent(product.id)}" target="_blank" rel="noopener">Vezi produs</a>` : ''}
                            </div>
                        </div>
                    `).join('')}
                </div>
            </div>
        `;
    }

    function openDetail(row) {
        if (!detailModal || !detailBody) return;
        const meta = row.meta && typeof row.meta === 'object' ? row.meta : {};
        const filters = meta.filters && typeof meta.filters === 'object' ? meta.filters : {};
        const vehicle = meta.vehicle && typeof meta.vehicle === 'object' ? meta.vehicle : null;
        const isFound = Number(row.found) === 1;

        if (detailSubtitle) {
            detailSubtitle.textContent = `${String(row.query_type || '').toUpperCase()} · ${row.query_value || ''} · ${row.created_at || ''}`;
        }

        const filterEntries = Object.entries(filters).map(([key, value]) => [key, String(value)]);
        const vehicleEntries = vehicle
            ? Object.entries(vehicle).filter(([key]) => !['raw'].includes(key)).slice(0, 8).map(([key, value]) => [key, String(value)])
            : [];

        detailBody.innerHTML = [
            renderMetaBlock('Rezultat scanare', [
                ['Status', isFound ? 'Găsit în stoc' : 'Negăsit / eroare'],
                ['Număr rezultate', row.result_count ?? 0],
                ['Sursă', meta.source || '—'],
                ['Fallback', meta.fallback || '—'],
                ['Notă', row.notice || '—'],
            ]),
            renderMetaBlock('Context vehicul', [
                ['Vehicul (label)', row.vehicle_label || vehicle?.label || '—'],
                ['car_id TecDoc', row.car_id || filters.car_id || '—'],
                ['node_id', filters.node_id || '—'],
                ['VIN în meta', meta.vin || filters.vin || '—'],
            ]),
            vehicleEntries.length ? renderMetaBlock('Detalii vehicul decodat', vehicleEntries) : '',
            filterEntries.length ? renderMetaBlock('Filtre active la scanare', filterEntries) : '',
            renderProductsPreview(meta.products_preview || []),
            meta.decode_error ? renderMetaBlock('Eroare decodare', [['Mesaj', meta.decode_error]]) : '',
        ].join('');

        detailModal.classList.remove('hidden');
    }

    function closeDetail() {
        detailModal?.classList.add('hidden');
    }

    async function loadLogs(page) {
        if (page) currentPage = page;
        const payload = formPayload(currentPage);
        const result = await apiList(payload);
        renderStats(result.stats || {});
        renderTopList(topMissingEl, result.top_missing || [], 'missing');
        renderTopList(topFoundEl, result.top_found || [], 'found');
        renderTable(result.data || []);
        listMeta = {
            page: currentPage,
            per_page: 10,
            total: Number(result.total || 0),
            total_pages: Math.max(1, Math.ceil(Number(result.total || 0) / 10)),
        };
        if (metaEl) {
            const tabLabel = activeTab === 'found' ? 'găsite' : 'negăsite';
            metaEl.textContent = `${result.count || 0} din ${result.total || 0} (${tabLabel}) · pag. ${listMeta.page}/${listMeta.total_pages}`;
        }
        if (window.BpaPagination) {
            BpaPagination.render(document.getElementById('searchlogs-pagination'), listMeta, (p) => loadLogs(p).catch((e) => showToast(e.message, true)));
        }
    }

    async function exportCsv() {
        const payload = formPayload();
        payload.type_product = 'export';
        const response = await fetch(ENDPOINT, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        if (!response.ok) {
            let message = 'Export eșuat.';
            try {
                const json = await response.json();
                message = json.message || message;
            } catch (e) {}
            throw new Error(message);
        }
        const blob = await response.blob();
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = 'search_logs_' + activeTab + '_' + new Date().toISOString().slice(0, 19).replace(/[:T]/g, '-') + '.csv';
        document.body.appendChild(link);
        link.click();
        link.remove();
        URL.revokeObjectURL(url);
        showToast('Export CSV descărcat.', false);
    }

    document.querySelectorAll('.searchlogs-tab').forEach((btn) => {
        btn.addEventListener('click', () => {
            setActiveTab(btn.dataset.tab || 'missing');
            currentPage = 1;
            loadLogs(1).catch((error) => showToast(error.message, true));
        });
    });

    form?.addEventListener('submit', async (event) => {
        event.preventDefault();
        currentPage = 1;
        try {
            await loadLogs(1);
        } catch (error) {
            showToast(error.message, true);
        }
    });

    document.getElementById('searchlogs-refresh')?.addEventListener('click', () => {
        loadLogs().catch((error) => showToast(error.message, true));
    });

    document.getElementById('searchlogs-export')?.addEventListener('click', () => {
        exportCsv().catch((error) => showToast(error.message, true));
    });

    document.getElementById('searchlogs-detail-close')?.addEventListener('click', closeDetail);
    detailModal?.addEventListener('click', (event) => {
        if (event.target === detailModal) closeDetail();
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') closeDetail();
    });

    setActiveTab('missing');

    if (window.BpaAsync && typeof window.BpaAsync.defer === 'function') {
        window.BpaAsync.defer(() => loadLogs());
    } else {
        setTimeout(() => loadLogs().catch((error) => showToast(error.message, true)), 0);
    }
})();
</script>
