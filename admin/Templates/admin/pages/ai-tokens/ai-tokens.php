<div class="grid grid-cols-12 gap-6 mt-6">
    <div id="ai-tokens-toast" class="hidden fixed right-5 top-5 z-50 rounded-md border bg-background px-4 py-3 text-sm shadow"></div>

    <div class="col-span-12 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-lg font-medium">Consum tokeni AI</h2>
            <p class="text-sm opacity-70">Monitorizare Grok, Gemini, Groq — statistici zilnice, limite și alerte.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <button type="button" id="ai-tokens-refresh" class="box rounded-md border bg-primary px-4 py-2 text-sm text-white">Reîncarcă</button>
        </div>
    </div>

    <div id="ai-tokens-alerts" class="col-span-12 space-y-2"></div>

    <div class="col-span-12 sm:col-span-6 xl:col-span-3">
        <div class="box p-4">
            <div class="text-xs uppercase opacity-70">Azi (total)</div>
            <div id="stat-today-all" class="mt-2 text-2xl font-semibold">0</div>
            <div class="mt-1 text-xs opacity-60">Luna: <span id="stat-month-all">0</span> · Total: <span id="stat-total-all">0</span></div>
        </div>
    </div>
    <div class="col-span-12 sm:col-span-6 xl:col-span-3">
        <div class="box p-4 ai-tokens-provider-card" data-provider="grok">
            <div class="flex items-center justify-between gap-2">
                <div class="text-xs uppercase opacity-70">Grok</div>
                <span class="ai-tokens-badge ai-tokens-badge-grok">Grok</span>
            </div>
            <div class="mt-2 text-2xl font-semibold" id="stat-grok-today">0</div>
            <div class="mt-2 h-2 rounded-full bg-foreground/10 overflow-hidden" aria-hidden="true">
                <div id="bar-grok" class="h-full rounded-full bg-primary transition-all" style="width:0%"></div>
            </div>
            <div class="mt-1 text-xs opacity-60"><span id="stat-grok-pct">0</span>% din limită · <span id="stat-grok-requests">0</span> cereri azi</div>
        </div>
    </div>
    <div class="col-span-12 sm:col-span-6 xl:col-span-3">
        <div class="box p-4 ai-tokens-provider-card" data-provider="gemini">
            <div class="flex items-center justify-between gap-2">
                <div class="text-xs uppercase opacity-70">Gemini</div>
                <span class="ai-tokens-badge ai-tokens-badge-gemini">Gemini</span>
            </div>
            <div class="mt-2 text-2xl font-semibold" id="stat-gemini-today">0</div>
            <div class="mt-2 h-2 rounded-full bg-foreground/10 overflow-hidden" aria-hidden="true">
                <div id="bar-gemini" class="h-full rounded-full bg-primary transition-all" style="width:0%"></div>
            </div>
            <div class="mt-1 text-xs opacity-60"><span id="stat-gemini-pct">0</span>% din limită · <span id="stat-gemini-requests">0</span> cereri azi</div>
        </div>
    </div>
    <div class="col-span-12 sm:col-span-6 xl:col-span-3">
        <div class="box p-4 ai-tokens-provider-card" data-provider="groq">
            <div class="flex items-center justify-between gap-2">
                <div class="text-xs uppercase opacity-70">Groq</div>
                <span class="ai-tokens-badge ai-tokens-badge-groq">Groq</span>
            </div>
            <div class="mt-2 text-2xl font-semibold" id="stat-groq-today">0</div>
            <div class="mt-2 h-2 rounded-full bg-foreground/10 overflow-hidden" aria-hidden="true">
                <div id="bar-groq" class="h-full rounded-full bg-primary transition-all" style="width:0%"></div>
            </div>
            <div class="mt-1 text-xs opacity-60"><span id="stat-groq-pct">0</span>% din limită · <span id="stat-groq-requests">0</span> cereri azi</div>
        </div>
    </div>

    <div class="col-span-12 xl:col-span-5">
        <div class="box p-5 h-full">
            <h3 class="text-base font-medium">Praguri alertă zilnică</h3>
            <p class="mt-1 text-xs opacity-60">Setează limita zilnică și procentul de avertizare per provider.</p>
            <form id="ai-tokens-threshold-form" class="mt-4 grid grid-cols-12 gap-3">
                <label class="col-span-12">
                    <span class="mb-1 block text-sm">Provider</span>
                    <select name="provider" class="box h-10 w-full rounded-md border px-3" required>
                        <option value="grok">Grok</option>
                        <option value="gemini">Gemini</option>
                        <option value="groq">Groq</option>
                        <option value="openai">OpenAI</option>
                    </select>
                </label>
                <label class="col-span-12 sm:col-span-6">
                    <span class="mb-1 block text-sm">Limită zilnică (tokeni)</span>
                    <input type="number" name="daily_limit" min="1000" step="1000" value="500000" class="box h-10 w-full rounded-md border px-3" required>
                </label>
                <label class="col-span-12 sm:col-span-6">
                    <span class="mb-1 block text-sm">Avertizare la (%)</span>
                    <input type="number" name="warning_pct" min="50" max="99" value="80" class="box h-10 w-full rounded-md border px-3" required>
                </label>
                <div class="col-span-12">
                    <button type="submit" class="box h-10 rounded-md border bg-primary px-4 text-white text-sm">Salvează prag</button>
                </div>
            </form>
            <div id="ai-tokens-thresholds-list" class="mt-4 space-y-2 text-sm"></div>
        </div>
    </div>

    <div class="col-span-12 xl:col-span-7">
        <div class="box p-5 h-full">
            <h3 class="text-base font-medium">Rezumat luna curentă</h3>
            <p class="mt-1 text-xs opacity-60">Consum cumulat per provider de la începutul lunii.</p>
            <div id="ai-tokens-month-summary" class="mt-4 space-y-3 text-sm"></div>
        </div>
    </div>

    <div class="col-span-12">
        <div class="box p-5">
            <form id="ai-tokens-filters" class="grid grid-cols-12 gap-3">
                <label class="col-span-12 md:col-span-3">
                    <span class="mb-1 block text-sm">Provider</span>
                    <select name="provider" class="box h-10 w-full rounded-md border px-3">
                        <option value="">Toate</option>
                        <option value="grok">Grok</option>
                        <option value="gemini">Gemini</option>
                        <option value="groq">Groq</option>
                        <option value="openai">OpenAI</option>
                    </select>
                </label>
                <label class="col-span-12 md:col-span-3">
                    <span class="mb-1 block text-sm">De la</span>
                    <input type="date" name="date_from" class="box h-10 w-full rounded-md border px-3">
                </label>
                <label class="col-span-12 md:col-span-3">
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
                        <th class="px-3 py-2 text-left">Provider</th>
                        <th class="px-3 py-2 text-left">Model</th>
                        <th class="px-3 py-2 text-right">Prompt</th>
                        <th class="px-3 py-2 text-right">Completion</th>
                        <th class="px-3 py-2 text-right">Total</th>
                        <th class="px-3 py-2 text-left">Sursă</th>
                    </tr>
                    </thead>
                    <tbody id="ai-tokens-table">
                    <tr>
                        <td colspan="7" class="px-3 py-6 text-center opacity-70">Se încarcă...</td>
                    </tr>
                    </tbody>
                </table>
            </div>
            <div id="ai-tokens-meta" class="mt-3 text-xs opacity-70"></div>
            <div id="ai-tokens-pagination" class="mt-3"></div>
        </div>
    </div>
</div>

<style>
.ai-tokens-badge {
    display: inline-flex;
    align-items: center;
    border-radius: 9999px;
    padding: 0.15rem 0.55rem;
    font-size: 0.65rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}
.ai-tokens-badge-grok { background: rgba(239, 68, 68, 0.15); color: #dc2626; }
.ai-tokens-badge-gemini { background: rgba(59, 130, 246, 0.15); color: #2563eb; }
.ai-tokens-badge-groq { background: rgba(16, 185, 129, 0.15); color: #059669; }
.ai-tokens-badge-openai { background: rgba(107, 114, 128, 0.15); color: #4b5563; }
.ai-tokens-alert {
    border-radius: 0.5rem;
    padding: 0.75rem 1rem;
    font-size: 0.875rem;
}
.ai-tokens-alert-warning {
    background: rgba(245, 158, 11, 0.12);
    border: 1px solid rgba(245, 158, 11, 0.35);
    color: #b45309;
}
.ai-tokens-alert-danger {
    background: rgba(239, 68, 68, 0.1);
    border: 1px solid rgba(239, 68, 68, 0.35);
    color: #b91c1c;
}
.ai-tokens-provider-card.is-warning { box-shadow: inset 0 0 0 1px rgba(245, 158, 11, 0.45); }
.ai-tokens-provider-card.is-danger { box-shadow: inset 0 0 0 1px rgba(239, 68, 68, 0.5); }
#bar-grok.is-warning, #bar-gemini.is-warning, #bar-groq.is-warning { background: #f59e0b; }
#bar-grok.is-danger, #bar-gemini.is-danger, #bar-groq.is-danger { background: #ef4444; }
@media (max-width: 640px) {
    .ai-tokens-table-num { font-size: 0.75rem; }
}
</style>

<script>
(function () {
    'use strict';

    const ENDPOINT = '/admin/api/ai_tokens_endpoint.php';
    const PROVIDERS = ['grok', 'gemini', 'groq'];
    const PER_PAGE = 25;

    const form = document.getElementById('ai-tokens-filters');
    const thresholdForm = document.getElementById('ai-tokens-threshold-form');
    const table = document.getElementById('ai-tokens-table');
    const alertsEl = document.getElementById('ai-tokens-alerts');
    const metaEl = document.getElementById('ai-tokens-meta');
    const toast = document.getElementById('ai-tokens-toast');
    const monthSummaryEl = document.getElementById('ai-tokens-month-summary');
    const thresholdsListEl = document.getElementById('ai-tokens-thresholds-list');

    let currentPage = 1;
    let listMeta = { page: 1, total: 0, per_page: PER_PAGE, total_pages: 1 };
    let lastThresholds = [];

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, (char) => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
        }[char]));
    }

    function formatNum(n) {
        return Number(n || 0).toLocaleString('ro-RO');
    }

    function showToast(message, isError) {
        if (!toast) return;
        toast.textContent = message;
        toast.classList.remove('hidden');
        toast.classList.toggle('text-danger', Boolean(isError));
        setTimeout(() => toast.classList.add('hidden'), 3500);
    }

    function providerBadge(provider) {
        const key = String(provider || '').toLowerCase();
        const label = key.charAt(0).toUpperCase() + key.slice(1);
        return `<span class="ai-tokens-badge ai-tokens-badge-${escapeHtml(key)}">${escapeHtml(label)}</span>`;
    }

    function formPayload(page) {
        const p = page || currentPage;
        const payload = {
            type_product: 'list',
            limit: PER_PAGE,
            offset: (p - 1) * PER_PAGE
        };
        if (!form) return payload;
        const data = new FormData(form);
        data.forEach((value, key) => {
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
            throw new Error(result.message || 'Eroare la încărcarea datelor.');
        }
        return result;
    }

    function thresholdMap(thresholds) {
        const map = {};
        (thresholds || []).forEach((th) => {
            map[String(th.provider || '')] = th;
        });
        return map;
    }

    function renderAlerts(alerts) {
        if (!alertsEl) return;
        if (!alerts || !alerts.length) {
            alertsEl.innerHTML = '';
            return;
        }
        alertsEl.innerHTML = alerts.map((alert) => {
            const cls = alert.level === 'danger' ? 'ai-tokens-alert-danger' : 'ai-tokens-alert-warning';
            return `<div class="ai-tokens-alert ${cls}" role="alert">${escapeHtml(alert.message || '')}</div>`;
        }).join('');
    }

    function renderStats(stats, thresholds) {
        if (!stats) return;
        const byProvider = stats.by_provider || {};
        const thMap = thresholdMap(thresholds);

        const todayAll = document.getElementById('stat-today-all');
        const monthAll = document.getElementById('stat-month-all');
        const totalAll = document.getElementById('stat-total-all');
        if (todayAll) todayAll.textContent = formatNum(stats.today);
        if (monthAll) monthAll.textContent = formatNum(stats.month);
        if (totalAll) totalAll.textContent = formatNum(stats.total);

        PROVIDERS.forEach((provider) => {
            const data = byProvider[provider] || {};
            const th = thMap[provider] || {};
            const limit = Math.max(1, Number(th.daily_limit || 500000));
            const used = Number(data.today || 0);
            const pct = Math.min(100, Math.round((used / limit) * 100));

            const todayEl = document.getElementById('stat-' + provider + '-today');
            const pctEl = document.getElementById('stat-' + provider + '-pct');
            const reqEl = document.getElementById('stat-' + provider + '-requests');
            const barEl = document.getElementById('bar-' + provider);
            const cardEl = document.querySelector('.ai-tokens-provider-card[data-provider="' + provider + '"]');

            if (todayEl) todayEl.textContent = formatNum(used);
            if (pctEl) pctEl.textContent = String(pct);
            if (reqEl) reqEl.textContent = formatNum(data.requests_today || 0);
            if (barEl) {
                barEl.style.width = pct + '%';
                barEl.classList.remove('is-warning', 'is-danger');
                const warningPct = Number(th.warning_pct || 80);
                if (pct >= 100) barEl.classList.add('is-danger');
                else if (pct >= warningPct) barEl.classList.add('is-warning');
            }
            if (cardEl) {
                cardEl.classList.remove('is-warning', 'is-danger');
                const warningPct = Number(th.warning_pct || 80);
                if (pct >= 100) cardEl.classList.add('is-danger');
                else if (pct >= warningPct) cardEl.classList.add('is-warning');
            }
        });
    }

    function renderMonthSummary(stats, thresholds) {
        if (!monthSummaryEl || !stats) return;
        const byProvider = stats.by_provider || {};
        const thMap = thresholdMap(thresholds);
        monthSummaryEl.innerHTML = PROVIDERS.map((provider) => {
            const data = byProvider[provider] || {};
            const th = thMap[provider] || {};
            const limit = Number(th.daily_limit || 0);
            return `
                <div class="flex flex-wrap items-center justify-between gap-2 rounded-lg border px-3 py-2">
                    <div class="flex items-center gap-2">${providerBadge(provider)}</div>
                    <div class="text-right">
                        <div class="font-semibold">${formatNum(data.month || 0)} tokeni</div>
                        <div class="text-xs opacity-60">Limită zilnică: ${formatNum(limit)} · Total all-time: ${formatNum(data.total || 0)}</div>
                    </div>
                </div>
            `;
        }).join('');
    }

    function renderThresholdsList(thresholds) {
        if (!thresholdsListEl) return;
        lastThresholds = thresholds || [];
        if (!lastThresholds.length) {
            thresholdsListEl.innerHTML = '<div class="opacity-70">Nu există praguri configurate.</div>';
            return;
        }
        thresholdsListEl.innerHTML = lastThresholds.map((th) => `
            <div class="rounded-lg border px-3 py-2 flex flex-wrap justify-between gap-2">
                ${providerBadge(th.provider)}
                <span class="text-xs opacity-70">Limită: ${formatNum(th.daily_limit)} · Avertizare: ${escapeHtml(th.warning_pct)}%</span>
            </div>
        `).join('');
    }

    function renderTable(items) {
        if (!table) return;
        if (!items || !items.length) {
            table.innerHTML = '<tr><td colspan="7" class="px-3 py-6 text-center opacity-70">Nu există înregistrări pentru filtrele selectate.</td></tr>';
            return;
        }
        table.innerHTML = items.map((row) => `
            <tr class="border-b">
                <td class="px-3 py-2 whitespace-nowrap">${escapeHtml(row.created_at || '')}</td>
                <td class="px-3 py-2">${providerBadge(row.provider)}</td>
                <td class="px-3 py-2 font-mono text-xs">${escapeHtml(row.model || '—')}</td>
                <td class="px-3 py-2 text-right ai-tokens-table-num">${formatNum(row.prompt_tokens)}</td>
                <td class="px-3 py-2 text-right ai-tokens-table-num">${formatNum(row.completion_tokens)}</td>
                <td class="px-3 py-2 text-right font-medium ai-tokens-table-num">${formatNum(row.total_tokens)}</td>
                <td class="px-3 py-2 text-xs opacity-70">${escapeHtml(row.source || '—')}</td>
            </tr>
        `).join('');
    }

    async function loadData(page) {
        if (page) currentPage = page;
        const result = await apiCall(formPayload(currentPage));
        renderAlerts(result.alerts || []);
        renderStats(result.stats || {}, result.thresholds || []);
        renderMonthSummary(result.stats || {}, result.thresholds || []);
        renderThresholdsList(result.thresholds || []);
        renderTable(result.data || []);

        listMeta = {
            page: currentPage,
            per_page: PER_PAGE,
            total: Number(result.total || 0),
            total_pages: Math.max(1, Math.ceil(Number(result.total || 0) / PER_PAGE)),
        };
        if (metaEl) {
            metaEl.textContent = `${result.count || 0} din ${result.total || 0} înregistrări · pag. ${listMeta.page}/${listMeta.total_pages}`;
        }
        if (window.BpaPagination) {
            BpaPagination.render(document.getElementById('ai-tokens-pagination'), listMeta, (p) => loadData(p).catch((e) => showToast(e.message, true)));
        }
    }

    thresholdForm?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const data = new FormData(thresholdForm);
        const payload = {
            type_product: 'save_threshold',
            provider: data.get('provider'),
            daily_limit: Number(data.get('daily_limit')),
            warning_pct: Number(data.get('warning_pct')),
        };
        try {
            const result = await apiCall(payload);
            showToast(result.message || 'Prag salvat.', false);
            renderAlerts(result.alerts || []);
            renderStats(result.stats || {}, result.thresholds || []);
            renderMonthSummary(result.stats || {}, result.thresholds || []);
            renderThresholdsList(result.thresholds || []);
        } catch (error) {
            showToast(error.message, true);
        }
    });

    form?.addEventListener('submit', async (event) => {
        event.preventDefault();
        currentPage = 1;
        try {
            await loadData(1);
        } catch (error) {
            showToast(error.message, true);
        }
    });

    document.getElementById('ai-tokens-refresh')?.addEventListener('click', () => {
        loadData().catch((error) => showToast(error.message, true));
    });

    if (window.BpaAsync && typeof window.BpaAsync.defer === 'function') {
        window.BpaAsync.defer(() => loadData());
    } else {
        setTimeout(() => loadData().catch((error) => showToast(error.message, true)), 0);
    }
})();
</script>
