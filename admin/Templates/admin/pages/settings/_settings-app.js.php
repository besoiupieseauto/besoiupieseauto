<script>
(function SettingsHub() {
    'use strict';
    const API_URLS = [
        '/admin/api/settings_endpoint.php',
        '/admin/public/api/settings_endpoint.php',
    ];
    const API = API_URLS[0];
    const toast = document.getElementById('settings-toast');
    let hub = null;

    const PRIMARY_PROVIDERS = ['stealth_browser', 'rapidapi_tecdoc', 'scrape_do'];

    const PROVIDER_META = {
        stealth_browser: { abbr: 'SB', color: '#1abc9c' },
        scrape_do:       { abbr: 'SD', color: '#f97316' },
        rapidapi_tecdoc: { abbr: 'TD', color: '#0ea5e9' },
        cursor:          { abbr: 'CR', color: '#0d9488' },
        openai:          { abbr: 'OA', color: '#10b981' },
        groq:            { abbr: 'GQ', color: '#2dd4bf' },
        gemini:          { abbr: 'GM', color: '#2dd4bf' },
        grok:            { abbr: 'GK', color: '#1e293b' },
        ollama:          { abbr: 'OL', color: '#64748b' },
    };

    const PROVIDER_HINTS = {
        stealth_browser: 'Scraper ePiesa/eMAG/Autodoc — Chrome local nodriver (0 credite)',
        scrape_do: 'Fallback opțional dacă SCRAPER_FALLBACK_SCRAPE_DO=1',
        rapidapi_tecdoc: 'RapidAPI auto-parts-catalog — OEM, VIN, imagini TecDoc',
        cursor: 'Cursor API — agent AI, audit imagini',
        openai: 'OpenAI — chat, vision',
        groq: 'Groq — robot WhatsApp',
        gemini: 'Google Gemini',
        grok: 'xAI Grok',
        ollama: 'Ollama local — fără cost cloud',
    };

    let usageLiveTimer = null;
    let usageLiveActive = false;

    function visibleBudgets(budgets) {
        const list = Array.isArray(budgets) ? budgets : [];
        const fromHub = hub?.api_token_hub?.priority_providers;
        if (list.length === 0 && Array.isArray(fromHub) && fromHub.length) {
            return fromHub;
        }
        const primary = hub?.primary_providers || PRIMARY_PROVIDERS;
        const filtered = list.filter(b => primary.includes(b.provider_key));
        if (filtered.length) {
            return filtered;
        }
        return list;
    }

    function esc(s) {
        const d = document.createElement('div');
        d.textContent = s ?? '';
        return d.innerHTML;
    }

    function cursorBillingInfo() {
        return hub?.api_token_hub?.cursor_billing || hub?.cursor_billing || null;
    }

    function renderCursorBillingPanel(info, containerId) {
        const el = document.getElementById(containerId);
        if (!el) return;
        if (!info) {
            el.classList.add('hidden');
            el.innerHTML = '';
            return;
        }
        const labels = info.labels || {};
        el.classList.remove('hidden');
        el.innerHTML = `
            <div class="st-cursor-billing__grid">
                <div class="st-cursor-billing__col st-cursor-billing__col--local">
                    <span class="st-cursor-billing__tag">Local</span>
                    <h4 class="st-cursor-billing__title">${esc(labels.local || 'Consum local server')}</h4>
                    <p><strong>${Number(info.local_month_requests || 0).toLocaleString('ro-RO')}</strong> apeluri/lună · <strong>${Number(info.local_today_requests || 0).toLocaleString('ro-RO')}</strong> azi</p>
                    <p class="st-cursor-billing__sub">~${Number(info.local_estimated_tokens || 0).toLocaleString('ro-RO')} tokeni estimați (jurnal Besoiu)</p>
                </div>
                <div class="st-cursor-billing__col st-cursor-billing__col--external">
                    <span class="st-cursor-billing__tag st-cursor-billing__tag--ext">Cursor.com</span>
                    <h4 class="st-cursor-billing__title">${esc(labels.external || 'Cotă reală API')}</h4>
                    <p>${esc(labels.ide_bucket || 'Auto + Composer (IDE) — alt buget')}</p>
                    <p class="st-cursor-billing__links">
                        <a href="${esc(info.dashboard_billing_url || 'https://cursor.com/dashboard?tab=billing')}" target="_blank" rel="noopener noreferrer">Billing → API</a>
                        · <a href="${esc(info.dashboard_usage_url || 'https://cursor.com/dashboard?tab=usage')}" target="_blank" rel="noopener noreferrer">Usage</a>
                    </p>
                </div>
            </div>
            <p class="st-cursor-billing__note">${esc(info.note_ro || '')}</p>
        `;
    }

    function cursorBillingCardHtml(info) {
        if (!info) return '';
        return `
            <div class="st-cursor-billing st-cursor-billing--card">
                <p><strong>Local server:</strong> ${Number(info.local_month_requests || 0).toLocaleString('ro-RO')} apeluri/lună</p>
                <p><a href="${esc(info.dashboard_billing_url || 'https://cursor.com/dashboard?tab=billing')}" target="_blank" rel="noopener noreferrer">Cotă API pe cursor.com →</a> (≠ Auto+Composer IDE)</p>
            </div>
        `;
    }

    function initials(name) {
        const parts = String(name || '?').trim().split(/\s+/).filter(Boolean);
        if (parts.length >= 2) return (parts[0][0] + parts[1][0]).toUpperCase();
        return (parts[0] || '?').slice(0, 2).toUpperCase();
    }

    function rolePillClass(role) {
        const r = String(role || '').toLowerCase();
        if (r === 'super_ambassador') return 'st-role-pill st-role-pill--super';
        if (r === 'manager') return 'st-role-pill st-role-pill--manager';
        return 'st-role-pill';
    }

    function showToast(msg, ok) {
        if (!toast) return;
        toast.textContent = msg;
        toast.className = 'st-toast ' + (ok ? 'is-ok' : 'is-err');
        toast.classList.remove('hidden');
        setTimeout(() => toast.classList.add('hidden'), 4500);
    }

    async function api(method, body) {
        const opts = { method, credentials: 'include', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' } };
        const csrf = window.BESOIU_ADMIN_CSRF
            || document.querySelector('meta[name="admin-csrf"]')?.content
            || document.getElementById('bpa-api-live-arm')?.getAttribute('data-csrf')
            || document.querySelector('[data-csrf]')?.getAttribute('data-csrf')
            || '';
        if (csrf && method !== 'GET') {
            opts.headers['X-Admin-CSRF'] = csrf;
        }
        if (body) opts.body = JSON.stringify(body);
        let lastErr = new Error('Settings API indisponibil.');
        for (const url of API_URLS) {
            try {
                const r = await fetch(url, opts);
                const raw = await r.text();
                if (!raw || !raw.trim()) {
                    throw new Error('Răspuns gol de la server (settings API).');
                }
                let j;
                try {
                    j = JSON.parse(raw);
                } catch (e) {
                    throw new Error('JSON invalid de la server: ' + raw.slice(0, 120));
                }
                if (!j.success) throw new Error(j.message || 'Eroare API');
                return j;
            } catch (err) {
                lastErr = err instanceof Error ? err : new Error(String(err));
            }
        }
        throw lastErr;
    }

    function switchTab(name) {
        document.querySelectorAll('.settings-page .st-tab').forEach(t => {
            const active = t.dataset.tab === name;
            t.classList.toggle('is-active', active);
            t.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        document.querySelectorAll('.settings-page .st-panel').forEach(p => {
            const active = p.dataset.panel === name;
            p.classList.toggle('is-active', active);
            if (active) p.removeAttribute('hidden');
            else p.setAttribute('hidden', '');
        });
        if (name) try { history.replaceState(null, '', '?tab=' + name); } catch (e) {}
        if (name === 'usage') {
            renderUsageLiveIfActive();
            startUsageLivePoll();
        } else {
            stopUsageLivePoll();
        }
        if (name === 'modules') {
            loadModulesCatalog().catch(err => showToast(err.message, false));
        }
        if (name === 'ollama') {
            loadOllamaControl().catch(err => showToast(err.message, false));
        }
    }

    function providerMeta(key) {
        return PROVIDER_META[key] || { abbr: String(key || '?').slice(0, 2).toUpperCase(), color: '#64748b' };
    }

    function formatUsageTime(iso) {
        if (!iso) return '—';
        try {
            const d = new Date(iso.replace(' ', 'T'));
            if (Number.isNaN(d.getTime())) return iso;
            return d.toLocaleString('ro-RO', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit', second: '2-digit' });
        } catch (_) {
            return iso;
        }
    }

    function renderUsageHourlyChart(hourly) {
        const slots = Array.from({ length: 24 }, (_, i) => ({ hour: i, units: 0 }));
        (hourly || []).forEach(row => {
            const h = Number(row.hour);
            if (h >= 0 && h < 24) slots[h].units += Number(row.units || 0);
        });
        const max = Math.max(1, ...slots.map(s => s.units));
        return `
            <div class="st-usage-hourly">
                ${slots.map(s => {
                    const pct = Math.round((s.units / max) * 100);
                    const title = `${String(s.hour).padStart(2, '0')}:00 — ${s.units} unități`;
                    return `<div class="st-usage-hourly__bar" title="${title}">
                        <div class="st-usage-hourly__fill" style="height:${pct}%"></div>
                        <span class="st-usage-hourly__label">${s.hour}</span>
                    </div>`;
                }).join('')}
            </div>`;
    }

    function isUsageTabActive() {
        const panel = document.querySelector('.settings-page .st-panel[data-panel="usage"]');
        return panel && !panel.hasAttribute('hidden');
    }

    function buildUsageLiveFromHub(data) {
        if (!data) return null;
        if (data.usage_live && typeof data.usage_live === 'object') {
            return data.usage_live;
        }
        const auto = data.api_automation;
        const budgets = data.token_budgets || [];
        const stats = data.token_stats?.by_provider || {};
        const modeStats = auto?.usage_mode_stats || {};
        if (!budgets.length && !auto) return null;

        const providers = budgets.map(b => {
            const key = b.provider_key;
            const st = stats[key] || {};
            const u = computeTokenUsage(b, st);
            const modes = modeStats[key] || {};
            return {
                provider_key: key,
                label: b.label || key,
                external_hint: PROVIDER_HINTS[key] || '',
                live_enabled: !!b.is_active,
                today_units: st.today_units || 0,
                month_units: st.month_units || 0,
                month_tokens_remaining: u.remaining,
                used_pct: u.usedPct,
                tokens_per_request: u.tpr,
                mode_auto: modes.auto || 0,
                mode_manual: modes.manual || 0,
            };
        });

        let todayApi = 0;
        Object.values(stats).forEach(st => { todayApi += Number(st.today_units || 0); });

        const timeline = (auto?.recent_usage || []).map(r => ({ ...r, kind: 'api' }));

        return {
            generated_at: auto?.generated_at || new Date().toISOString(),
            controls: auto?.controls || {},
            summary: {
                automation_paused: !!auto?.summary?.automation_paused,
                api_live_armed: !!auto?.summary?.api_live_armed,
                today_api_units: todayApi,
                today_ai_tokens: 0,
                auto_consumers_active: auto?.summary?.auto_consumers_active || 0,
            },
            providers,
            external_resources: (auto?.consumers || []).map(c => ({
                label: c.label,
                mode: c.mode,
                providers: c.providers || [],
                schedule: c.schedule,
                expect: c.expect,
                status: c.status,
                block_reason: c.block_reason || '',
            })),
            hourly_today: [],
            timeline,
            ai_stats: {},
        };
    }

    function renderUsageLiveError(message) {
        const el = document.getElementById('settings-usage-live');
        if (!el) return;
        el.innerHTML = `
            <div class="st-usage-live__error">
                <p><strong>Nu s-a putut încărca consumul live.</strong></p>
                <p class="st-usage-live__error-detail">${esc(message || 'Eroare necunoscută')}</p>
                <button type="button" id="settings-usage-retry" class="st-btn st-btn--primary st-btn--sm">Reîncearcă</button>
            </div>`;
    }

    function renderUsageLivePanel(data) {
        const el = document.getElementById('settings-usage-live');
        if (!el) return;
        if (!data) {
            renderUsageLiveError('Date indisponibile.');
            return;
        }

        const summary = data.summary || {};
        const controls = data.controls || {};
        const arm = controls.api_live_arm || {};
        const paused = !!summary.automation_paused;
        const armed = !!summary.api_live_armed;
        const loadError = data.error ? `<div class="st-alert st-alert--warning" role="alert">${esc(data.error)}</div>` : '';

        let statusCls = 'st-usage-status--ok';
        let statusText = 'API fundal activ';
        if (paused && !armed) {
            statusCls = 'st-usage-status--paused';
            statusText = 'Fundal oprit — doar manual după «Armez API»';
        } else if (armed) {
            statusCls = 'st-usage-status--armed';
            statusText = arm.permanent ? 'API live armat (permanent)' : `API live armat (~${arm.minutes_left || '?'} min rămase)`;
        }

        const providerCards = (data.providers || []).map(p => {
            const meta = providerMeta(p.provider_key);
            const pct = Math.min(100, Math.max(0, Number(p.used_pct || 0)));
            const barCls = pct >= 90 ? 'is-danger' : (pct >= 70 ? 'is-warning' : '');
            const liveBadge = p.live_enabled
                ? '<span class="st-usage-live-badge st-usage-live-badge--on">LIVE</span>'
                : '<span class="st-usage-live-badge">OFF</span>';
            return `
                <article class="st-usage-provider" data-provider="${esc(p.provider_key)}">
                    <div class="st-usage-provider__head">
                        <span class="st-usage-provider__icon" style="background:${meta.color}">${esc(meta.abbr)}</span>
                        <div>
                            <h3 class="st-usage-provider__name">${esc(p.label || p.provider_key)} ${liveBadge}</h3>
                            <p class="st-usage-provider__hint">${esc(p.external_hint || '')}</p>
                        </div>
                    </div>
                    <div class="st-usage-provider__stats">
                        <div><strong>${Number(p.today_units || 0).toLocaleString('ro-RO')}</strong><span>azi (cereri)</span></div>
                        <div><strong>${Number(p.month_units || 0).toLocaleString('ro-RO')}</strong><span>luna (cereri)</span></div>
                        <div><strong>${Number(p.month_tokens_remaining || 0).toLocaleString('ro-RO')}</strong><span>rămași</span></div>
                    </div>
                    <div class="st-usage-provider__bar" role="progressbar" aria-valuenow="${100 - pct}" aria-valuemin="0" aria-valuemax="100">
                        <div class="st-usage-provider__bar-fill ${barCls}" style="width:${100 - pct}%"></div>
                    </div>
                    <p class="st-usage-provider__modes">
                        Auto: <strong>${Number(p.mode_auto || 0).toLocaleString('ro-RO')}</strong>
                        · Manual: <strong>${Number(p.mode_manual || 0).toLocaleString('ro-RO')}</strong>
                        · ${Number(p.tokens_per_request || 1)}/cerere
                    </p>
                </article>`;
        }).join('') || '<p class="st-table-empty">Niciun provider configurat.</p>';

        const resourceRows = (data.external_resources || []).map(r => `<tr>
            <td><strong>${esc(r.label)}</strong></td>
            <td>${modeBadge(r.mode || 'unknown')}</td>
            <td>${(r.providers || []).map(p => `<code>${esc(p)}</code>`).join(' ') || '—'}</td>
            <td>${esc(r.schedule || '—')}</td>
            <td>${esc(r.expect || '—')}</td>
            <td>${statusBadge(r.status || 'active')}${r.block_reason ? `<br><small>${esc(r.block_reason)}</small>` : ''}</td>
        </tr>`).join('') || '<tr><td colspan="6" class="st-table-empty">Nicio resursă mapată.</td></tr>';

        const timelineRows = (data.timeline || []).map(r => {
            const kind = r.kind === 'ai' ? 'AI' : 'API';
            const meta = providerMeta(r.provider_key);
            const modeCell = r.kind === 'ai'
                ? '<span class="st-mode-badge st-mode-badge--manual">Tokeni</span>'
                : modeBadge(r.mode || 'unknown');
            return `<tr class="st-usage-timeline-row" data-kind="${esc(r.kind || 'api')}">
                <td class="st-usage-timeline__time">${esc(formatUsageTime(r.created_at))}</td>
                <td><span class="st-usage-kind st-usage-kind--${esc(r.kind || 'api')}">${kind}</span></td>
                <td><span class="st-usage-provider-pill" style="--pill:${meta.color}">${esc(r.provider_key || '')}</span></td>
                <td>${modeCell}</td>
                <td>${esc(r.source || '—')}</td>
                <td><code>${esc((r.note || '').slice(0, 100))}</code></td>
                <td class="st-tr">${Number(r.units || 1).toLocaleString('ro-RO')}</td>
            </tr>`;
        }).join('') || '<tr><td colspan="7" class="st-table-empty">Niciun apel înregistrat încă. Rulează scraper, import sau TecDoc.</td></tr>';

        el.innerHTML = `
            ${loadError}
            <div class="st-usage-live__toolbar">
                <div class="st-usage-status ${statusCls}">
                    <span class="st-usage-status__pulse" aria-hidden="true"></span>
                    <div>
                        <strong>${esc(statusText)}</strong>
                        <p>${Number(summary.today_api_units || 0).toLocaleString('ro-RO')} unități API azi
                            · ${Number(summary.today_ai_tokens || 0).toLocaleString('ro-RO')} tokeni AI azi
                            · ${Number(summary.auto_consumers_active || 0)} surse auto active</p>
                    </div>
                </div>
                <div class="st-usage-live__meta">
                    <span class="st-usage-live__clock" id="settings-usage-updated">Actualizat: ${esc(formatUsageTime(data.generated_at))}</span>
                    <label class="st-usage-live__autoref">
                        <input type="checkbox" id="settings-usage-autorefresh" checked> Auto 5s
                    </label>
                    <button type="button" id="settings-usage-refresh" class="st-btn st-btn--ghost st-btn--sm">↻ Acum</button>
                    <a href="?tab=tokens" class="st-btn st-btn--ghost st-btn--sm">Buget API →</a>
                </div>
            </div>

            <h2 class="st-section-title">Resurse externe — consum azi</h2>
            <div class="st-usage-provider-grid">${providerCards}</div>

            <div class="st-card st-card--usage-chart">
                <div class="st-card__head">
                    <div>
                        <h2 class="st-card__title">Activitate API azi (pe ore)</h2>
                        <p class="st-card__desc">Unități logate în jurnal — toți providerii</p>
                    </div>
                </div>
                ${renderUsageHourlyChart(data.hourly_today)}
            </div>

            <h2 class="st-section-title">Unde se consumă — matrice resurse</h2>
            <p class="st-lead st-lead--compact">Fiecare rând = flux din proiect care poate apela un API extern (cron, scraper, site, import).</p>
            <div class="st-auto-table-wrap">
                <table class="st-auto-table st-usage-resources-table">
                    <thead><tr>
                        <th>Resursă / flux</th><th>Mod</th><th>API extern</th><th>Când</th><th>Impact</th><th>Status</th>
                    </tr></thead>
                    <tbody>${resourceRows}</tbody>
                </table>
            </div>

            <h2 class="st-section-title">Jurnal live — ultimele apeluri</h2>
            <div class="st-usage-timeline-wrap">
                <table class="st-auto-table st-usage-timeline-table">
                    <thead><tr>
                        <th>Moment</th><th>Tip</th><th>Provider</th><th>Mod</th><th>Sursă</th><th>Detaliu</th><th class="st-tr">Unități</th>
                    </tr></thead>
                    <tbody id="settings-usage-timeline-body">${timelineRows}</tbody>
                </table>
            </div>`;
    }

    async function loadUsageLive(manual) {
        try {
            let j = null;
            try {
                const r = await fetch(API + '?view=usage_live', { credentials: 'include', headers: { Accept: 'application/json' } });
                const text = await r.text();
                try {
                    j = JSON.parse(text);
                } catch (parseErr) {
                    throw new Error('Răspuns invalid de la server (nu e JSON).');
                }
                if (!r.ok || !j.success) {
                    throw new Error(j?.message || ('HTTP ' + r.status));
                }
            } catch (fetchErr) {
                const fallback = buildUsageLiveFromHub(hub);
                if (fallback) {
                    renderUsageLivePanel(fallback);
                    if (manual) showToast('Consum din cache hub (endpoint live indisponibil).', false);
                    return fallback;
                }
                throw fetchErr;
            }
            renderUsageLivePanel(j.data);
            if (manual) showToast('Consum live actualizat.', true);
            if (hub) hub.usage_live = j.data;
            return j.data;
        } catch (err) {
            const fallback = buildUsageLiveFromHub(hub);
            if (fallback && (fallback.providers?.length || fallback.timeline?.length)) {
                renderUsageLivePanel(fallback);
                if (manual) showToast('Afișare parțială din hub.', false);
                return fallback;
            }
            renderUsageLiveError(err.message || String(err));
            if (manual) showToast(err.message, false);
            throw err;
        }
    }

    function renderUsageLiveIfActive() {
        if (!isUsageTabActive()) return;
        const payload = buildUsageLiveFromHub(hub);
        if (payload) renderUsageLivePanel(payload);
    }

    function startUsageLivePoll() {
        usageLiveActive = true;
        if (usageLiveTimer) clearInterval(usageLiveTimer);
        loadUsageLive(false).catch(() => {});
        usageLiveTimer = setInterval(() => {
            if (!usageLiveActive) return;
            const panel = document.querySelector('.settings-page .st-panel[data-panel="usage"]');
            if (!panel || panel.hasAttribute('hidden')) {
                stopUsageLivePoll();
                return;
            }
            const auto = document.getElementById('settings-usage-autorefresh');
            if (auto && !auto.checked) return;
            loadUsageLive(false).catch(() => {});
        }, 5000);
    }

    function stopUsageLivePoll() {
        usageLiveActive = false;
        if (usageLiveTimer) {
            clearInterval(usageLiveTimer);
            usageLiveTimer = null;
        }
    }

    function renderAlerts(alerts) {
        const el = document.getElementById('settings-alerts');
        if (!el) return;
        if (!alerts?.length) { el.innerHTML = ''; return; }
        el.innerHTML = alerts.map(a => {
            const cls = a.level === 'danger' ? 'st-alert--danger' : 'st-alert--warning';
            return `<div class="st-alert ${cls}" role="alert"><span>${esc(a.message)}</span></div>`;
        }).join('');
    }

    function syncPermItemState(item) {
        const cb = item.querySelector('.settings-perm-cb');
        if (cb) item.classList.toggle('is-checked', cb.checked);
    }

    function expandPresetKeys(keys) {
        const sections = hub?.permission_sections || {};
        const out = new Set();
        (keys || []).forEach(k => {
            if (sections[k]?.features) {
                Object.keys(sections[k].features).forEach(fk => out.add(fk));
            } else {
                out.add(k);
            }
        });
        return [...out];
    }

    let activePermSection = null;
    let selectedUserId = null;

    function switchEditorTab(tabName) {
        document.querySelectorAll('.st-user-modal__tab').forEach(btn => {
            const on = btn.dataset.editorTab === tabName;
            btn.classList.toggle('is-active', on);
            btn.setAttribute('aria-selected', on ? 'true' : 'false');
        });
        document.querySelectorAll('.st-user-editor__pane').forEach(pane => {
            const on = pane.dataset.editorPane === tabName;
            pane.classList.toggle('is-active', on);
            if (on) pane.removeAttribute('hidden');
            else pane.setAttribute('hidden', '');
        });
    }

    function highlightUserRow(id) {
        selectedUserId = id || null;
        document.querySelectorAll('#settings-users-tbody tr').forEach(tr => {
            tr.classList.toggle('is-selected', id && tr.dataset.userId === String(id));
        });
    }

    function openUserModal(focusTab) {
        const modal = document.getElementById('settings-user-modal');
        if (!modal) return;
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        switchEditorTab(focusTab || 'identity');
        modal.querySelector('[name="fullname"]')?.focus();
    }

    function closeUserModal() {
        const modal = document.getElementById('settings-user-modal');
        if (!modal) return;
        modal.classList.add('hidden');
        document.body.style.overflow = '';
        highlightUserRow(null);
    }

    function featureCheckboxHtml(fk, f, sk, sel) {
        return `
            <label class="st-perm-item ${sel.has(fk) ? 'is-checked' : ''}">
                <input type="checkbox" name="permissions[]" value="${esc(fk)}" class="settings-perm-cb" data-section="${esc(sk)}" ${sel.has(fk) ? 'checked' : ''}>
                <span class="st-perm-check" aria-hidden="true"></span>
                <span>
                    <span class="st-perm-label">${esc(f.label)}</span>
                    <span class="st-perm-desc">${esc(f.desc)}</span>
                </span>
            </label>
        `;
    }

    function initPermDelegation() {
        const form = document.getElementById('settings-user-form');
        if (!form || form.dataset.permDelegated === '1') return;
        form.dataset.permDelegated = '1';

        form.addEventListener('change', e => {
            if (!e.target.classList.contains('settings-perm-cb')) return;
            syncPermItemState(e.target.closest('.st-perm-item'));
            updateSectionCounts();
            const roleSel = document.getElementById('settings-user-role');
            if (roleSel) roleSel.value = 'custom';
        });

        form.addEventListener('click', e => {
            const btn = e.target.closest('.settings-perm-section-all, .settings-perm-section-none');
            if (!btn) return;
            e.preventDefault();
            const sk = btn.dataset.section || activePermSection;
            if (!sk) return;
            const checked = btn.classList.contains('settings-perm-section-all');
            document.querySelectorAll(`.settings-perm-cb[data-section="${sk}"]`).forEach(cb => {
                cb.checked = checked;
                syncPermItemState(cb.closest('.st-perm-item'));
            });
            updateSectionCounts();
            document.getElementById('settings-user-role').value = 'custom';
        });
    }

    function updateSectionCounts() {
        document.querySelectorAll('.st-perm-nav-btn').forEach(btn => {
            const sk = btn.dataset.section;
            if (!sk) return;
            const total = document.querySelectorAll(`.settings-perm-cb[data-section="${sk}"]`).length;
            const checked = document.querySelectorAll(`.settings-perm-cb[data-section="${sk}"]:checked`).length;
            const countEl = btn.querySelector('.st-perm-nav-count');
            if (countEl) countEl.textContent = checked + '/' + total;
            btn.classList.toggle('has-selection', checked > 0);
        });
    }

    function showPermPanel(sk) {
        activePermSection = sk;
        document.querySelectorAll('.st-perm-nav-btn').forEach(b => {
            b.classList.toggle('is-active', b.dataset.section === sk);
        });
        document.querySelectorAll('.st-perm-pane').forEach(p => {
            p.classList.toggle('is-active', p.dataset.section === sk);
        });
    }

    function renderPermSections(sections, selected) {
        const nav = document.getElementById('settings-perm-nav');
        const panel = document.getElementById('settings-perm-panel');
        if (!nav || !panel) return;

        const sel = new Set(expandPresetKeys(selected || []));
        const entries = Object.entries(sections || {});
        nav.innerHTML = '';
        panel.innerHTML = '';

        if (!entries.length) {
            panel.innerHTML = '<p class="st-perms-deleg__empty">Nicio secțiune configurată.</p>';
            return;
        }

        entries.forEach(([sk, sec], idx) => {
            const feats = Object.entries(sec.features || {});
            const checked = feats.filter(([k]) => sel.has(k)).length;

            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'st-perm-nav-btn' + (checked > 0 ? ' has-selection' : '') + (idx === 0 ? ' is-active' : '');
            btn.dataset.section = sk;
            btn.innerHTML = `<span>${esc(sec.label)}</span><span class="st-perm-nav-count">${checked}/${feats.length}</span>`;
            btn.addEventListener('click', () => showPermPanel(sk));
            nav.appendChild(btn);

            const pane = document.createElement('div');
            pane.className = 'st-perm-pane' + (idx === 0 ? ' is-active' : '');
            pane.dataset.section = sk;
            pane.innerHTML = `
                <div class="st-perm-pane__head">
                    <p class="st-perm-pane__desc">${esc(sec.desc || '')}</p>
                    <div class="st-perm-pane__toolbar">
                        <button type="button" class="st-link-btn settings-perm-section-all" data-section="${esc(sk)}">Toate</button>
                        <button type="button" class="st-link-btn settings-perm-section-none" data-section="${esc(sk)}">Nicio</button>
                    </div>
                </div>
                <div class="st-perm-features">
                    ${feats.map(([fk, f]) => featureCheckboxHtml(fk, f, sk, sel)).join('')}
                </div>
            `;
            panel.appendChild(pane);
        });

        activePermSection = entries[0][0];
        updateSectionCounts();
    }

    function applyRolePreset(role) {
        const presets = hub?.role_presets || {};
        const keys = expandPresetKeys(presets[role]?.permissions || []);
        document.querySelectorAll('.settings-perm-cb').forEach(cb => {
            if (role === 'super_ambassador') cb.checked = true;
            else if (role === 'custom') return;
            else cb.checked = keys.includes(cb.value);
            const item = cb.closest('.st-perm-item');
            if (item) syncPermItemState(item);
        });
        updateSectionCounts();
        applyChatRolePreset(role);
    }

    function chatFeatureCheckboxHtml(fk, f, sel) {
        return `
            <label class="st-perm-item ${sel.has(fk) ? 'is-checked' : ''}">
                <input type="checkbox" name="chat_permissions[]" value="${esc(fk)}" class="settings-chat-perm-cb" ${sel.has(fk) ? 'checked' : ''}>
                <span class="st-perm-check" aria-hidden="true"></span>
                <span>
                    <span class="st-perm-label">${esc(f.label)}</span>
                    <span class="st-perm-desc">${esc(f.desc)}</span>
                </span>
            </label>
        `;
    }

    function renderChatPermSections(groups, selected) {
        const wrap = document.getElementById('settings-chat-perms');
        if (!wrap) return;
        const sel = new Set(selected || []);
        const entries = Object.entries(groups || {});
        if (!entries.length) {
            wrap.innerHTML = '<p class="st-perms-deleg__empty">Nicio permisiune chat configurată.</p>';
            return;
        }
        wrap.innerHTML = entries.map(([gk, group]) => {
            const feats = Object.entries(group.features || {});
            return `
                <div class="st-chat-perm-group">
                    <div class="st-perm-pane__head">
                        <div>
                            <strong>${esc(group.label || gk)}</strong>
                            <p class="st-perm-pane__desc">${esc(group.desc || '')}</p>
                        </div>
                    </div>
                    <div class="st-perm-features">
                        ${feats.map(([fk, f]) => chatFeatureCheckboxHtml(fk, f, sel)).join('')}
                    </div>
                </div>
            `;
        }).join('');
        wrap.querySelectorAll('.settings-chat-perm-cb').forEach(cb => {
            cb.addEventListener('change', () => {
                syncPermItemState(cb.closest('.st-perm-item'));
                const roleSel = document.getElementById('settings-user-role');
                if (roleSel) roleSel.value = 'custom';
            });
        });
    }

    function applyChatRolePreset(role) {
        const presets = hub?.chat_role_presets || {};
        const keys = presets[role]?.permissions || presets.operator?.permissions || [];
        document.querySelectorAll('.settings-chat-perm-cb').forEach(cb => {
            if (role === 'custom') return;
            cb.checked = keys.includes(cb.value);
            const item = cb.closest('.st-perm-item');
            if (item) syncPermItemState(item);
        });
    }

    function resetUserForm() {
        const form = document.getElementById('settings-user-form');
        form?.reset();
        document.getElementById('settings-user-id').value = '0';
        document.getElementById('settings-user-form-title').textContent = 'Utilizator nou';
        document.getElementById('settings-form-mode').textContent = 'Creare';
        document.getElementById('settings-pw-hint').textContent = 'obligatorie';
        form?.querySelector('[name="password"]')?.setAttribute('required', 'required');
        renderPermSections(hub?.permission_sections || {}, expandPresetKeys(hub?.role_presets?.operator?.permissions || []));
        renderChatPermSections(hub?.chat_permission_groups || {}, hub?.chat_role_presets?.operator?.permissions || []);
        applyRolePreset('operator');
        highlightUserRow(null);
    }

    function fillUserForm(user) {
        const uid = user.randomn_id || user.id;
        document.getElementById('settings-user-id').value = String(uid);
        document.getElementById('settings-user-form-title').textContent = user.fullname || 'Utilizator';
        document.getElementById('settings-form-mode').textContent = 'Editare #' + uid;
        document.getElementById('settings-pw-hint').textContent = 'opțională la editare';
        const form = document.getElementById('settings-user-form');
        if (!form) return;
        form.fullname.value = user.fullname || '';
        form.login.value = user.login || '';
        form.password.value = '';
        form.password.removeAttribute('required');
        form.role.value = user.role || 'operator';
        form.status.value = user.status === '0' ? '0' : '1';
        renderPermSections(hub.permission_sections, user.permissions || []);
        renderChatPermSections(hub.chat_permission_groups, user.chat_permissions || []);
        highlightUserRow(uid);
        openUserModal('identity');
    }

    function renderPermTags(summary) {
        const labels = String(summary || '').split(' · ').map(s => s.trim()).filter(Boolean);
        if (!labels.length) return '<span class="st-tag st-tag--more">—</span>';
        const max = 5;
        const shown = labels.slice(0, max);
        const rest = labels.length - max;
        let html = shown.map(l => `<span class="st-tag">${esc(l)}</span>`).join('');
        if (rest > 0) html += `<span class="st-tag st-tag--more">+${rest}</span>`;
        return `<div class="st-tags">${html}</div>`;
    }

    function renderUsers(users) {
        const tbody = document.getElementById('settings-users-tbody');
        const countEl = document.getElementById('settings-users-count');
        if (!tbody) return;
        if (countEl) countEl.textContent = (users?.length || 0) + ' conturi active în sistem';
        if (!users?.length) {
            tbody.innerHTML = '<tr><td colspan="6" class="st-table-empty">Niciun utilizator înregistrat.</td></tr>';
            return;
        }
        tbody.innerHTML = users.map(u => `
            <tr data-user-id="${u.randomn_id || u.id}">
                <td>
                    <div class="st-user-cell">
                        <div class="st-avatar" aria-hidden="true">${esc(initials(u.fullname))}</div>
                        <div>
                            <div class="st-user-name">${esc(u.fullname)}</div>
                            <div class="st-user-login">${esc(u.login)}</div>
                        </div>
                    </div>
                </td>
                <td><span class="${rolePillClass(u.role)}">${esc(String(u.role || '').replace(/_/g, ' '))}</span></td>
                <td>${renderPermTags(u.permissions_summary)}</td>
                <td>${renderPermTags(u.chat_permissions_summary || 'Fără chat')}</td>
                <td class="st-tc">
                    <span class="st-status ${u.status === '0' ? '' : 'is-on'}">${u.status === '0' ? 'Inactiv' : 'Activ'}</span>
                </td>
                <td class="st-tr">
                    <div class="st-row-actions">
                        <button type="button" class="st-icon-btn settings-edit-user" data-id="${u.randomn_id || u.id}" title="Editează" aria-label="Editează">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                        </button>
                        <button type="button" class="st-icon-btn st-icon-btn--danger settings-del-user" data-id="${u.randomn_id || u.id}" title="Șterge" aria-label="Șterge">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                        </button>
                    </div>
                </td>
            </tr>
        `).join('');

        tbody.querySelectorAll('.settings-edit-user').forEach(btn => {
            btn.addEventListener('click', () => {
                const id = parseInt(btn.dataset.id, 10);
                const user = users.find(x => (x.randomn_id || x.id) === id);
                if (user) fillUserForm(user);
            });
        });
        if (selectedUserId) highlightUserRow(selectedUserId);
        tbody.querySelectorAll('.settings-del-user').forEach(btn => {
            btn.addEventListener('click', async () => {
                const id = parseInt(btn.dataset.id, 10);
                if (!id || !confirm('Ștergi acest utilizator? Acțiunea nu poate fi anulată.')) return;
                try {
                    const j = await api('POST', { action: 'delete_user', id });
                    showToast(j.message, true);
                    closeUserModal();
                    renderUsers(j.data?.users || []);
                } catch (err) { showToast(err.message, false); }
            });
        });
    }

    function computeTokenUsage(budget, providerStats) {
        const quota = Math.max(1, parseInt(budget.monthly_quota, 10) || 1);
        const tpr = Math.max(1, parseInt(budget.tokens_per_request, 10) || 1);
        const requests = parseInt(providerStats?.month_units, 10) || 0;
        const usedTokensAuto = requests * tpr;
        const remainingAuto = Math.max(0, quota - usedTokensAuto);
        const hasOverride = budget.remaining_override !== null
            && budget.remaining_override !== undefined
            && budget.remaining_override !== '';
        const remaining = hasOverride
            ? Math.max(0, parseInt(budget.remaining_override, 10) || 0)
            : remainingAuto;
        const usedTokens = hasOverride ? Math.max(0, quota - remaining) : usedTokensAuto;
        const usedPct = Math.min(100, Math.round((usedTokens / quota) * 100));
        const remainingPct = Math.max(0, 100 - usedPct);
        const maxRequests = Math.floor(quota / tpr);
        const requestsLeft = Math.max(0, maxRequests - requests);
        const queriesFromTokens = Math.max(0, Math.floor(remaining / tpr));
        const queriesLeft = hasOverride && requests > 0
            ? Math.min(queriesFromTokens, requestsLeft)
            : requestsLeft;
        const quotaInconsistent = hasOverride && requests > 0 && queriesFromTokens > requestsLeft + 1;
        return {
            quota, tpr, requests, usedTokens, remaining, remainingAuto, usedPct, remainingPct,
            maxRequests, requestsLeft, queriesLeft, queriesFromTokens,
            isManualRemaining: hasOverride, quotaInconsistent,
        };
    }

    function budgetFromForm() {
        const form = document.getElementById('settings-budget-form');
        if (!form) return null;
        const key = form.provider_key?.value;
        const base = hub?.token_budgets?.find(b => b.provider_key === key) || {};
        const input = document.getElementById('settings-budget-remaining-input');
        const isManual = input?.dataset.mode === 'manual';
        return {
            ...base,
            provider_key: key,
            monthly_quota: form.monthly_quota?.value,
            tokens_per_request: form.tokens_per_request?.value,
            warning_pct: form.warning_pct?.value,
            remaining_override: isManual && input?.value !== '' ? input.value : null,
        };
    }

    function syncRemainingInput(budget) {
        const input = document.getElementById('settings-budget-remaining-input');
        const autoBtn = document.getElementById('settings-budget-remaining-auto');
        if (!input || !budget) return;
        const stats = hub?.token_stats?.by_provider?.[budget.provider_key] || {};
        const u = computeTokenUsage(budget, stats);
        const isManual = u.isManualRemaining;
        input.dataset.mode = isManual ? 'manual' : 'auto';
        input.value = String(u.remaining);
        input.classList.toggle('is-manual', isManual);
        if (autoBtn) autoBtn.classList.toggle('is-hidden', !isManual);
    }

    function modeBadge(mode) {
        const cls = mode === 'auto' ? 'st-mode-badge--auto' : (mode === 'manual' ? 'st-mode-badge--manual' : 'st-mode-badge--unknown');
        const label = mode === 'auto' ? 'Automat' : (mode === 'manual' ? 'Manual' : 'Necunoscut');
        return `<span class="st-mode-badge ${cls}">${label}</span>`;
    }

    function statusBadge(status) {
        const map = {
            active: ['st-status-badge--active', 'Activ'],
            blocked: ['st-status-badge--blocked', 'Oprit'],
            external: ['st-status-badge--external', 'Extern'],
        };
        const m = map[status] || ['st-status-badge--external', status || '—'];
        return `<span class="st-status-badge ${m[0]}">${m[1]}</span>`;
    }

    function renderAutomationPanel(auto) {
        const el = document.getElementById('settings-automation-panel');
        if (!el || !auto) return;

        const c = auto.controls || {};
        const summary = auto.summary || {};
        const paused = !!c.api_automation_disabled;
        const strict = c.api_strict_manual !== false;
        const autoTecScrape = c.api_auto_tecdoc_scrape !== false;
        const arm = c.api_live_arm || {};
        const armed = !!arm.armed;
        const projects = auto.projects || [];
        const consumers = auto.consumers || [];
        const recent = auto.recent_usage || [];

        const projectHtml = projects.map(p => {
            const checklist = Array.isArray(p.checklist) && p.checklist.length
                ? `<ul class="st-auto-checklist">${p.checklist.map(i => `<li>${esc(i)}</li>`).join('')}</ul>`
                : '';
            const st = p.is_local
                ? (p.automation_paused ? statusBadge('blocked') : statusBadge('active'))
                : statusBadge('external');
            return `
                <article class="st-auto-project">
                    <div class="st-auto-project__head">
                        <span class="st-auto-project__name">${esc(p.label)}</span>
                        ${st}
                    </div>
                    <p class="st-auto-project__note">${esc(p.note || '')}</p>
                    ${checklist}
                </article>`;
        }).join('');

        const blockedAuto = consumers.filter(r => (r.mode || '') === 'auto' && (r.status || '') === 'blocked').length;

        const consumerRows = consumers.map(row => {
            const providers = (row.providers || []).map(p => esc(p)).join(', ');
            const reason = row.block_reason
                ? `<br><small class="st-auto-block-reason">${esc(row.block_reason)}</small>`
                : '';
            return `<tr>
                <td>${esc(row.label)}<br><code>${esc(row.script || '')}</code></td>
                <td>${modeBadge(row.mode || 'unknown')}</td>
                <td>${providers || '—'}</td>
                <td>${esc(row.schedule || '—')}</td>
                <td>${esc(row.expect || '—')}</td>
                <td>${statusBadge(row.status || 'active')}${reason}</td>
            </tr>`;
        }).join('');

        const recentRows = recent.length ? recent.map(r => `<tr>
            <td>${esc(r.created_at || '')}</td>
            <td>${esc(r.provider_key || '')}</td>
            <td>${modeBadge(r.mode || 'unknown')}</td>
            <td>${esc(r.source || '')}</td>
            <td><code>${esc((r.note || '').slice(0, 80))}</code></td>
            <td>${Number(r.units || 1).toLocaleString('ro-RO')}</td>
        </tr>`).join('') : '<tr><td colspan="6" class="st-table-empty">Niciun consum logat încă.</td></tr>';

        const providerLive = c.provider_live || {};
        const providerAllowed = c.provider_live_allowed || {};

        const providerToggle = (id, label, hint, key, color) => {
            const on = !!providerLive[key];
            const allowed = providerAllowed[key] !== false;
            return `
                <div class="st-auto-toggle st-provider-toggle ${on ? 'is-on' : 'is-off'}">
                    <label for="${id}">
                        <strong>${label}</strong>
                        <small>${hint}</small>
                        ${allowed ? '' : '<small class="text-danger">Blocat de mod strict / fundal</small>'}
                    </label>
                    <input type="checkbox" id="${id}" data-provider-key="${key}" ${on ? 'checked' : ''}>
                </div>`;
        };

        el.innerHTML = `
            <div class="st-auto-providers" id="settings-consumption-controls">
                <h3 class="st-section-title" style="margin:0">API activ per provider</h3>
                <p class="text-sm opacity-70 mb-2">Scraper HTML = stealth-browser-mcp (local). Scrape.do doar fallback opțional.</p>
                <div class="st-auto-toggles st-auto-toggles--providers">
                    ${providerToggle('settings-provider-stealth', 'Stealth browser', 'ePiesa, eMAG, Autodoc — nodriver local', 'stealth_browser')}
                    ${providerToggle('settings-provider-tecdoc', 'RapidAPI TecDoc', 'Cod OEM, imagini, VIN — ca testul din Scraper', 'rapidapi_tecdoc')}
                    ${providerToggle('settings-provider-scrape', 'Scrape.do (fallback)', 'Doar dacă SCRAPER_FALLBACK_SCRAPE_DO=1', 'scrape_do')}
                </div>
                <button type="button" id="settings-provider-save" class="st-btn st-btn--primary st-btn--sm">Salvează provideri API</button>
            </div>

            <div class="st-auto-master ${paused ? 'is-paused' : ''}">
                <div>
                    <h2 class="st-auto-master__title">${paused ? '⏸ Mod la cerere — fundal OPRIT' : '▶ Consum API automat — ACTIV'}</h2>
                    <p class="st-auto-master__sub">
                        ${paused
                            ? (strict
                                ? 'Fundal oprit. API plătit (Cursor, RapidAPI, scrape.do) doar după «Armez API live» din bara de sus — apoi rulezi tu import, scraper, audit.'
                                : 'Site, cron și agenți folosesc doar cache/local. API plătit doar când apeși tu: import, scraper, audit, robot admin.')
                            : 'Cron, site și import pot consuma tokeni fără limită de fundal. ' + (summary.auto_consumers_active || 0) + ' surse auto active.'}
                    </p>
                </div>
                <label class="st-switch" title="API_AUTOMATION_DISABLED în .env">
                    <input type="checkbox" id="settings-auto-master" ${paused ? 'checked' : ''}>
                    <span class="st-switch__track"></span>
                </label>
            </div>

            ${strict ? `
            <div class="bpa-api-live-arm-panel${armed ? ' is-armed' : ''}" id="settings-api-live-arm-panel">
                <h3 class="bpa-api-live-arm-panel__title">${armed ? '🔓 API live ARMAT' : '🔒 API live OPRIT (mod strict)'}</h3>
                <p class="bpa-api-live-arm-panel__desc">
                    ${armed
                        ? (arm.permanent
                            ? 'API mereu activ — consum plătit permis până apeși «Oprit complet» în bara de sus.'
                            : ((arm.minutes_left >= 60)
                                ? ('Poți consuma API ~' + Math.round(arm.minutes_left / 60) + ' h. Apasă «Oprit complet» când ai terminat.')
                                : ('Poți consuma API ~' + (arm.minutes_left || '?') + ' min. Apasă «Oprit complet» când ai terminat.')))
                        : 'Niciun token Cursor/RapidAPI/scrape.do nu pleacă până nu apeși «Armez» (30m, 4h sau Mereu) din bara de sus.'}
                </p>
                <p class="bpa-api-live-arm-panel__desc"><strong>Blocat automat:</strong> cron AI Agent, Cursor/LLM. TecDoc manual (Scraper) merge fără «Armez». Cron TecDoc: bifează opțiunea de mai jos.</p>
            </div>` : ''}

            <div class="st-auto-toggles">
                <div class="st-auto-toggle st-auto-toggle--highlight">
                    <label for="settings-auto-tecdoc-scrape">
                        <strong>Cron TecDoc + Scrape.do (fără Cursor AI)</strong>
                        <small>API_AUTO_TECDOC_SCRAPE — imagini pipeline, pagini produs, import enrich; nu pornește AI Agent</small>
                    </label>
                    <input type="checkbox" id="settings-auto-tecdoc-scrape" ${autoTecScrape ? 'checked' : ''} ${paused ? '' : 'disabled'}>
                </div>
                <div class="st-auto-toggle">
                    <label for="settings-cron-light">Enrichment TecDoc la import cron
                        <small>CRON_LIGHT_ENRICH — căutări OEM masive</small>
                    </label>
                    <input type="checkbox" id="settings-cron-light" ${c.cron_light_enrich ? 'checked' : ''}>
                </div>
                <div class="st-auto-toggle">
                    <label for="settings-cron-epiesa">ePiesa la cron (stealth browser)
                        <small>CRON_CHECK_EPIESA — nodriver local, 0 credite</small>
                    </label>
                    <input type="checkbox" id="settings-cron-epiesa" ${c.cron_check_epiesa ? 'checked' : ''}>
                </div>
                <div class="st-auto-toggle">
                    <label for="settings-audit-retry">Re-căutare imagini după audit
                        <small>IMAGE_AUDIT_AUTO_RETRY</small>
                    </label>
                    <input type="checkbox" id="settings-audit-retry" ${c.image_audit_auto_retry ? 'checked' : ''}>
                </div>
            </div>

            <div style="display:flex;gap:10px;flex-wrap:wrap">
                <button type="button" id="settings-automation-save" class="st-btn st-btn--primary">Salvează control consum</button>
                <a href="/admin/settings" class="st-btn st-btn--ghost">Setări →</a>
            </div>

            <h3 class="st-section-title" style="margin-top:8px">Proiecte</h3>
            <div class="st-auto-projects">${projectHtml}</div>

            ${blockedAuto > 0 ? `
            <div class="st-auto-matrix-hint">
                <strong>${blockedAuto} surse automate sunt OPRIT.</strong>
                Matricea e doar status — activezi din secțiunea de mai sus:
                <a href="#settings-consumption-controls">«Cron TecDoc + Scrape.do»</a> și checkbox-urile cron, apoi
                <strong>Salvează control consum</strong>.
            </div>` : ''}

            <h3 class="st-section-title">Matrice consum — ce pornește singur</h3>
            <p class="st-auto-matrix-lead">Status live — nu e buton aici. Comutatoarele sunt deasupra (scroll ↑ dacă nu le vezi).</p>
            <div class="st-auto-table-wrap">
                <table class="st-auto-table">
                    <thead><tr>
                        <th>Sursă</th><th>Mod</th><th>Provider</th><th>Program</th><th>Ce să te aștepți</th><th>Status</th>
                    </tr></thead>
                    <tbody>${consumerRows}</tbody>
                </table>
            </div>

            <h3 class="st-section-title">Jurnal recent (auto vs manual)</h3>
            <div class="st-auto-table-wrap">
                <table class="st-auto-table">
                    <thead><tr>
                        <th>Data</th><th>Provider</th><th>Mod</th><th>Sursă</th><th>Detaliu</th><th>Unități</th>
                    </tr></thead>
                    <tbody>${recentRows}</tbody>
                </table>
            </div>`;
    }

    function ecoClass(level) {
        const l = String(level || 'ok');
        if (l === 'critical') return 'is-critical';
        if (l === 'warning') return 'is-warning';
        return 'is-ok';
    }

    function renderMetroLlmPanel(metro) {
        const el = document.getElementById('settings-metro-llm-panel');
        if (!el) return;
        metro = metro || {};
        const cfg = metro.config || {};
        const ollama = metro.ollama || {};
        const router = metro.router || {};
        const groq = router.groq || {};
        const gemini = router.gemini || {};
        const openrouter = router.openrouter || {};
        const eco = metro.ecosystem || {};
        const usage = metro.usage || {};
        const today = usage.today || {};
        const month = usage.month || {};
        const cron = metro.cron || {};
        const matrix = Array.isArray(metro.task_matrix) ? metro.task_matrix : [];
        const routes = Array.isArray(metro.route_log) ? metro.route_log : [];
        const orchestra = metro.orchestra || {};
        const orchModules = Array.isArray(orchestra.module_results) ? orchestra.module_results : [];

        const orchRows = orchModules.length ? orchModules.map(r => `<tr>
            <td>${r.ok ? '✓' : (r.skipped ? '○' : '✗')}</td>
            <td>${esc(r.label || r.module || '')}</td>
            <td>${esc(r.zone || '')}</td>
            <td>${esc((r.message || '').slice(0, 72))}</td>
        </tr>`).join('') : '';

        const matrixRows = matrix.map(r => `<tr>
            <td>${esc(r.label)}</td>
            <td><code>${esc(r.profile || '')}</code></td>
            <td>${esc(r.provider || '')}</td>
            <td>${esc(r.trigger || '')}</td>
        </tr>`).join('');

        const routeRows = routes.length ? routes.map(r => `<tr>
            <td>${esc((r.ts || '').replace('T', ' ').slice(0, 19))}</td>
            <td>${esc(r.task || '')}</td>
            <td>${esc(r.routed_via || r.provider || '')}</td>
            <td>${r.ok ? '✓' : '✗'}</td>
            <td><code>${esc((r.model || '').slice(0, 24))}</code></td>
        </tr>`).join('') : '<tr><td colspan="5" class="st-table-empty">Niciun apel încă.</td></tr>';

        el.innerHTML = `
            <div class="st-metro st-metro--${ecoClass(eco.level)}">
                <header class="st-metro__head">
                    <div>
                        <h2 id="settings-metro-title" class="st-metro__title">🧠 Metro LLM — Ollama + cloud free + Cursor</h2>
                        <p class="st-metro__sub">Prioritate: Ollama (0 tokeni) → Groq → Gemini → OpenRouter (free) → Cursor (escaladare).</p>
                    </div>
                    <div class="st-metro__eco st-metro__eco--${ecoClass(eco.level)}">
                        <span class="st-metro__eco-dot"></span>
                        <strong>${esc(eco.label || '—')}</strong>
                    </div>
                </header>

                <div class="st-metro__stats">
                    <div class="st-metro__stat">
                        <span class="st-metro__stat-label">Azi local</span>
                        <strong>${Number(today.ollama || 0).toLocaleString('ro-RO')}</strong>
                    </div>
                    <div class="st-metro__stat">
                        <span class="st-metro__stat-label">Azi Cursor</span>
                        <strong>${Number(today.cursor || 0).toLocaleString('ro-RO')}</strong>
                    </div>
                    <div class="st-metro__stat">
                        <span class="st-metro__stat-label">Lună local</span>
                        <strong>${Number(month.ollama || 0).toLocaleString('ro-RO')}</strong>
                    </div>
                    <div class="st-metro__stat">
                        <span class="st-metro__stat-label">Lună Cursor</span>
                        <strong>${Number(month.cursor || 0).toLocaleString('ro-RO')}</strong>
                    </div>
                </div>

                <div class="st-metro__status-grid">
                    <div class="st-metro__chip ${ollama.ready ? 'is-ok' : 'is-warn'}">Ollama: ${ollama.ready ? 'OK · ' + esc(ollama.model || '') : (ollama.reachable ? 'model lipsă' : 'oprit')}</div>
                    <div class="st-metro__chip ${groq.ready ? 'is-ok' : ''}">Groq: ${groq.ready ? 'OK · ' + esc(groq.model || '') : 'neconfigurat'}</div>
                    <div class="st-metro__chip ${gemini.ready ? 'is-ok' : ''}">Gemini: ${gemini.ready ? 'OK · ' + esc(gemini.model || '') : 'neconfigurat'}</div>
                    <div class="st-metro__chip ${openrouter.ready ? 'is-ok' : ''}">OpenRouter: ${openrouter.ready ? ('OK · ' + esc((openrouter.model || '').slice(0, 28))) : 'neconfigurat'}</div>
                    <div class="st-metro__chip ${cron.local_cycle_allowed ? 'is-ok' : ''}">Ciclu local: ${cron.local_cycle_allowed ? 'permis' : (cron.cron_blocked ? 'blocat' : 'n/a')}</div>
                    <div class="st-metro__chip">Ultimul ciclu: ${cron.last_cycle_age_minutes != null ? cron.last_cycle_age_minutes + ' min' : '—'}</div>
                    <div class="st-metro__chip">Mod: ${esc(cfg.llm_router_mode || 'ollama_first')}</div>
                </div>

                <form id="settings-metro-form" class="st-metro__form">
                    <label class="st-check"><input type="checkbox" name="ollama_enabled" ${cfg.ollama_enabled ? 'checked' : ''}> Ollama activ</label>
                    <label class="st-check"><input type="checkbox" name="ollama_local_cycle" ${cfg.ollama_local_cycle ? 'checked' : ''}> Ciclu local când API cloud oprit</label>
                    <label>Model <input type="text" name="ollama_model" value="${esc(cfg.ollama_model || 'qwen2.5:3b')}" class="st-input st-input--sm"></label>
                    <label>URL <input type="text" name="ollama_base_url" value="${esc(cfg.ollama_base_url || 'http://127.0.0.1:11434')}" class="st-input st-input--sm"></label>
                    <label>Router
                        <select name="llm_router_mode" class="st-input st-input--sm">
                            <option value="ollama_first" ${cfg.llm_router_mode === 'ollama_first' ? 'selected' : ''}>Ollama first</option>
                            <option value="ollama_only" ${cfg.llm_router_mode === 'ollama_only' ? 'selected' : ''}>Doar Ollama</option>
                        </select>
                    </label>
                    <button type="submit" class="st-btn st-btn--primary st-btn--sm">Salvează Metro LLM</button>
                </form>

                <div class="st-metro__tests">
                    <button type="button" class="st-btn st-btn--ghost st-btn--sm" data-metro-test="test_metro_ollama">▶ Test Ollama</button>
                    <button type="button" class="st-btn st-btn--ghost st-btn--sm" data-metro-test="test_metro_cloud">▶ Test Metro LLM</button>
                    <button type="button" class="st-btn st-btn--ghost st-btn--sm" data-metro-test="test_metro_cycle">▶ Ciclu local</button>
                    <button type="button" class="st-btn st-btn--primary st-btn--sm" data-metro-test="test_metro_orchestra">▶ Orchestră AI (10 pași)</button>
                    <a href="/admin/ai-rag?tab=chat" class="st-btn st-btn--ghost st-btn--sm">Centru AI →</a>
                </div>

                <div id="settings-orchestra-results" class="st-metro__orchestra">${orchRows ? `<h3 class="st-section-title">Ultimul test orchestră</h3><div class="st-auto-table-wrap"><table class="st-auto-table"><thead><tr><th></th><th>Modul</th><th>Zonă</th><th>Rezultat</th></tr></thead><tbody>${orchRows}</tbody></table></div>` : ''}</div>

                <h3 class="st-section-title">Matrice LLM — cine folosește ce</h3>
                <div class="st-auto-table-wrap"><table class="st-auto-table"><thead><tr><th>Funcție</th><th>Profil</th><th>Provider</th><th>Declanșare</th></tr></thead><tbody>${matrixRows}</tbody></table></div>

                <h3 class="st-section-title">Jurnal rutare LLM (ultimele ${routes.length})</h3>
                <div class="st-auto-table-wrap"><table class="st-auto-table"><thead><tr><th>Când</th><th>Task</th><th>Via</th><th>OK</th><th>Model</th></tr></thead><tbody>${routeRows}</tbody></table></div>
            </div>`;
    }

    async function saveMetroLlm(ev) {
        if (ev) ev.preventDefault();
        const form = document.getElementById('settings-metro-form');
        if (!form) return;
        const fd = new FormData(form);
        const payload = {
            action: 'save_metro_llm',
            ollama_enabled: form.querySelector('[name="ollama_enabled"]')?.checked ? 1 : 0,
            ollama_local_cycle: form.querySelector('[name="ollama_local_cycle"]')?.checked ? 1 : 0,
            ollama_model: fd.get('ollama_model'),
            ollama_base_url: fd.get('ollama_base_url'),
            llm_router_mode: fd.get('llm_router_mode'),
        };
        const applyResult = (j) => {
            if (j.data?.metro_llm) hub.metro_llm = j.data.metro_llm;
            renderMetroLlmPanel(j.data?.metro_llm || hub?.metro_llm);
        };
        const P = window.BesoiuActionProgress;
        if (P) {
            return P.run({
                title: 'Salvare Metro LLM',
                subtitle: 'Ollama, mod router și ciclu local',
                steps: ['Validare formular', 'Scriere configurare', 'Actualizare panou'],
                task: async (update) => {
                    update(0, 'Se validează…');
                    const j = await api('POST', payload);
                    update(2, 'Se redesenează panoul…');
                    applyResult(j);
                    return j;
                },
                successMessage: (j) => j.message || 'Config Metro salvată.',
            });
        }
        const j = await api('POST', payload);
        applyResult(j);
        showToast(j.message || 'Salvat.', true);
    }

    async function runMetroTest(action) {
        const applyResult = (j) => {
            if (j.data?.metro_llm) {
                hub.metro_llm = j.data.metro_llm;
                renderMetroLlmPanel(j.data.metro_llm);
            }
            if (action === 'test_metro_orchestra' && Array.isArray(j.data?.modules)) {
                const box = document.getElementById('settings-orchestra-results');
                if (box) {
                    const rows = j.data.modules.map(r => `<tr>
                        <td>${r.ok ? '✓' : (r.skipped ? '○' : '✗')}</td>
                        <td>${esc(r.label || r.module || '')}</td>
                        <td>${esc(r.zone || '')}</td>
                        <td>${esc((r.message || '').slice(0, 100))}</td>
                    </tr>`).join('');
                    const s = j.data.summary || {};
                    box.innerHTML = `<h3 class="st-section-title">Test orchestră — ${s.ok || 0} OK / ${s.fail || 0} eșec</h3>
                        <div class="st-auto-table-wrap"><table class="st-auto-table"><thead><tr><th></th><th>Modul</th><th>Zonă</th><th>Rezultat</th></tr></thead><tbody>${rows}</tbody></table></div>`;
                }
            }
        };
        const P = window.BesoiuActionProgress;
        if (P) {
            return P.runMetroTest(action, API, applyResult);
        }
        const j = await api('POST', { action });
        applyResult(j);
        showToast(j.message || (j.success ? 'OK' : 'Eșec'), !!j.success);
    }

    async function saveProviderApiLive() {
        const payload = {
            action: 'save_provider_api',
            stealth_browser: document.getElementById('settings-provider-stealth')?.checked ? 1 : 0,
            rapidapi_tecdoc: document.getElementById('settings-provider-tecdoc')?.checked ? 1 : 0,
            scrape_do: document.getElementById('settings-provider-scrape')?.checked ? 1 : 0,
        };
        const applyResult = (j) => {
            hub = { ...hub, ...j.data, api_automation: j.data?.api_automation || hub?.api_automation };
            renderAutomationPanel(hub.api_automation);
            renderTokenCards(j.data?.token_budgets || hub.token_budgets, j.data?.token_stats || hub.token_stats);
            renderAlerts(j.data?.token_alerts || []);
        };
        const j = await api('POST', payload);
        applyResult(j);
        showToast(j.message || 'Provideri salvați.', true);
    }

    async function saveAutomationControls() {
        const payload = {
            action: 'save_automation',
            api_automation_disabled: document.getElementById('settings-auto-master')?.checked ? 1 : 0,
            api_auto_tecdoc_scrape: document.getElementById('settings-auto-tecdoc-scrape')?.checked ? 1 : 0,
            cron_light_enrich: document.getElementById('settings-cron-light')?.checked ? 1 : 0,
            cron_check_epiesa: document.getElementById('settings-cron-epiesa')?.checked ? 1 : 0,
            image_audit_auto_retry: document.getElementById('settings-audit-retry')?.checked ? 1 : 0,
        };
        const applyResult = (j) => {
            hub = { ...hub, ...j.data, api_automation: j.data?.api_automation || hub?.api_automation };
            renderAutomationPanel(hub.api_automation);
            renderTokenCards(j.data?.token_budgets || hub.token_budgets, j.data?.token_stats || hub.token_stats);
            renderAlerts(j.data?.token_alerts || []);
        };
        const P = window.BesoiuActionProgress;
        if (P) {
            return P.run({
                title: 'Salvare automatizări',
                steps: ['Validare', 'Scriere .env', 'Refresh tokeni'],
                task: async (update) => {
                    update(0);
                    const j = await api('POST', payload);
                    update(2);
                    applyResult(j);
                    return j;
                },
                successMessage: (j) => j.message || 'Automatizări salvate.',
            });
        }
        const j = await api('POST', payload);
        applyResult(j);
        showToast(j.message || 'Salvat.', true);
    }

    function renderTokenCards(budgets, stats) {
        const wrap = document.getElementById('settings-token-cards');
        const select = document.getElementById('settings-budget-provider');
        if (!wrap) return;
        const visible = visibleBudgets(budgets);
        const byStats = stats?.by_provider || {};
        if (!visible.length) {
            wrap.innerHTML = `
                <div class="st-token-empty">
                    <p><strong>Nu s-au încărcat providerii.</strong> Rulează pe server migrarea tokeni, apoi reîncarcă pagina.</p>
                    <code class="st-code">php admin/migrations/run_063_api_token_primary_providers.php</code>
                    <button type="button" class="st-btn st-btn--ghost st-btn--sm" id="settings-token-reload">↻ Reîncarcă</button>
                </div>`;
            document.getElementById('settings-token-reload')?.addEventListener('click', () => {
                loadHub().catch(err => showToast(err.message, false));
            });
            if (select) {
                select.innerHTML = PRIMARY_PROVIDERS.map(k =>
                    `<option value="${esc(k)}">${esc(k)}</option>`
                ).join('');
            }
            return;
        }
        wrap.innerHTML = visible.map(b => {
            const key = b.provider_key;
            const meta = PROVIDER_META[key] || { abbr: key.slice(0, 2).toUpperCase(), color: '#059669' };
            const st = byStats[key] || {};
            const u = computeTokenUsage(b, st);
            const warn = parseInt(b.warning_pct, 10) || 80;
            let cardCls = '';
            let barCls = '';
            if (u.remaining <= 0) { cardCls = 'is-danger'; barCls = 'is-danger'; }
            else if (u.usedPct >= warn) { cardCls = 'is-warning'; barCls = 'is-warning'; }
            const cost = parseFloat(st.month_cost) || 0;
            const costUnit = (parseFloat(b.cost_per_unit) || 0).toFixed(4);
            const modeStats = hub?.api_automation?.usage_mode_stats?.[key] || {};
            const modeLine = (modeStats.total > 0)
                ? `<p class="st-token-mode-line">Luna asta: <strong>${(modeStats.auto || 0).toLocaleString('ro-RO')} auto</strong> · <strong>${(modeStats.manual || 0).toLocaleString('ro-RO')} manual</strong>${modeStats.unknown ? ' · ' + modeStats.unknown + ' necunoscut' : ''}</p>`
                : '';
            const warnInconsistent = u.quotaInconsistent
                ? '<div class="st-token-warn">Override manual nu corespunde consumului logat — apasă Auto sau ajustează cota.</div>'
                : '';
            const liveOff = !(b.is_active === 1 || b.is_active === '1' || b.is_active === true);
            const liveBadge = liveOff
                ? '<span class="st-status-badge st-status-badge--blocked">API oprit</span>'
                : '<span class="st-status-badge st-status-badge--active">API activ</span>';
            const cursorBillingBlock = key === 'cursor' ? cursorBillingCardHtml(cursorBillingInfo()) : '';
            return `
                <article class="st-token-card ${cardCls}${liveOff ? ' is-api-off' : ''}" data-provider="${esc(key)}">
                    <div class="st-token-head">
                        <div class="st-token-icon" style="background:${meta.color}">${esc(meta.abbr)}</div>
                        <div>
                            <h3 class="st-token-name">${esc(b.label || key)}</h3>
                            <div class="st-token-key">${esc(key)} ${liveBadge}</div>
                        </div>
                    </div>
                    <div class="st-token-stats">
                        <span class="st-token-used">${u.remaining.toLocaleString('ro-RO')}</span>
                        <span class="st-token-quota">rămași din ${u.quota.toLocaleString('ro-RO')} tokeni</span>
                    </div>
                    <div class="st-token-bar" role="progressbar" aria-valuenow="${u.remainingPct}" aria-valuemin="0" aria-valuemax="100" title="Tokeni rămași">
                        <div class="st-token-bar-fill ${barCls}" style="width:${u.remainingPct}%"></div>
                    </div>
                    ${modeLine}
                    ${warnInconsistent}
                    ${cursorBillingBlock}
                    <dl class="st-token-meta">
                        <div><dt>Tokeni / query</dt><dd>${u.tpr.toLocaleString('ro-RO')}</dd></div>
                        <div><dt>Cereri luna</dt><dd>${u.requests.toLocaleString('ro-RO')} / ${u.maxRequests.toLocaleString('ro-RO')}</dd></div>
                        <div><dt>Cereri rămase</dt><dd>~${u.requestsLeft.toLocaleString('ro-RO')}</dd></div>
                        <div><dt>Consum tokeni</dt><dd>${u.usedTokens.toLocaleString('ro-RO')}</dd></div>
                        <div><dt>Cost lună</dt><dd>${cost.toFixed(2)} RON</dd></div>
                    </dl>
                </article>
            `;
        }).join('');

        if (select) {
            select.innerHTML = visible.map(b =>
                `<option value="${esc(b.provider_key)}">${esc(b.label || b.provider_key)}</option>`
            ).join('');
            const currentKey = select.value;
            const pick = visible.find(b => b.provider_key === currentKey) || visible[0];
            if (pick) fillBudgetForm(pick);
        }
    }

    function updateBudgetStatusDisplay(budget) {
        const box = document.getElementById('settings-budget-status');
        const metaEl = document.getElementById('settings-budget-remaining-meta');
        const detailEl = document.getElementById('settings-budget-remaining-detail');
        const barEl = document.getElementById('settings-budget-remaining-bar');
        const pctEl = document.getElementById('settings-budget-remaining-pct');
        if (!box || !budget) return;

        const stats = hub?.token_stats?.by_provider?.[budget.provider_key] || {};
        const u = computeTokenUsage(budget, stats);
        const warn = parseInt(budget.warning_pct, 10) || 80;

        box.classList.remove('is-warning', 'is-danger');
        if (u.remaining <= 0) box.classList.add('is-danger');
        else if (u.usedPct >= warn) box.classList.add('is-warning');

        syncRemainingInput(budget);
        if (metaEl) metaEl.textContent = 'tokeni rămași';
        if (detailEl) {
            const manualNote = u.isManualRemaining ? ' · setat manual' : '';
            const incNote = u.quotaInconsistent ? ' · atenție: override ≠ consum logat' : '';
            detailEl.textContent = `${u.requests.toLocaleString('ro-RO')} cereri din max ${u.maxRequests.toLocaleString('ro-RO')} · ~${u.requestsLeft.toLocaleString('ro-RO')} rămase · ${u.tpr.toLocaleString('ro-RO')} tokeni/cerere · ${u.usedTokens.toLocaleString('ro-RO')} tokeni consumați${manualNote}${incNote}`;
        }
        if (barEl) barEl.style.width = u.remainingPct + '%';
        if (pctEl) pctEl.textContent = u.remainingPct + '% rămas';
        box.querySelector('.st-budget-status__bar')?.setAttribute('aria-valuenow', String(u.remainingPct));

        if (budget.provider_key === 'cursor') {
            renderCursorBillingPanel(cursorBillingInfo(), 'settings-cursor-billing');
        } else {
            renderCursorBillingPanel(null, 'settings-cursor-billing');
        }
    }

    function fillBudgetForm(b) {
        const form = document.getElementById('settings-budget-form');
        if (!form || !b) return;
        form.provider_key.value = b.provider_key;
        form.monthly_quota.value = b.monthly_quota;
        if (form.tokens_per_request) form.tokens_per_request.value = b.tokens_per_request || 1;
        const cost = parseFloat(b.cost_per_unit);
        form.cost_per_unit.value = (cost > 0) ? b.cost_per_unit : '';
        form.warning_pct.value = b.warning_pct;
        form.is_active.checked = b.is_active !== 0 && b.is_active !== '0';
        updateBudgetStatusDisplay(b);
    }

    function renderModelField(model) {
        if (!model || !model.env_key) return '';
        const presets = model.presets || {};
        const current = (model.value || model.default || '').trim();
        const presetIds = Object.keys(presets);
        const isPreset = presetIds.includes(current);
        const customVal = isPreset ? '' : current;
        const options = presetIds.map(id =>
            `<option value="${esc(id)}"${id === current ? ' selected' : ''}>${esc(presets[id])}</option>`
        ).join('');
        const customSelected = !isPreset && current ? ' selected' : '';
        return `
            <div class="st-env-model">
                <label class="st-env-model__label">${esc(model.label || 'Model')}</label>
                <select class="st-env-model__select" data-env-model-key="${esc(model.env_key)}">
                    ${options}
                    <option value="__custom__"${customSelected}>Alt model (personalizat)…</option>
                </select>
                <input type="text" class="st-env-model__custom st-env-input${isPreset ? ' is-hidden' : ''}"
                       data-env-model-custom-for="${esc(model.env_key)}"
                       value="${esc(customVal)}" placeholder="ex. gpt-4.1, composer-2.5" spellcheck="false">
                ${model.hint ? `<p class="st-env-hint st-env-hint--model">${esc(model.hint)}</p>` : ''}
            </div>
        `;
    }

    function bindModelFields(root) {
        root?.querySelectorAll('.st-env-model__select').forEach(sel => {
            sel.addEventListener('change', () => {
                const custom = root.querySelector(`[data-env-model-custom-for="${sel.dataset.envModelKey}"]`);
                if (!custom) return;
                const isCustom = sel.value === '__custom__';
                custom.classList.toggle('is-hidden', !isCustom);
                if (isCustom) custom.focus();
            });
        });
    }

    function collectModelValues(root) {
        const out = {};
        root?.querySelectorAll('.st-env-model__select').forEach(sel => {
            const key = sel.dataset.envModelKey;
            if (!key) return;
            if (sel.value === '__custom__') {
                const custom = root.querySelector(`[data-env-model-custom-for="${key}"]`);
                const val = (custom?.value || '').trim();
                if (val) out[key] = val;
            } else if (sel.value) {
                out[key] = sel.value;
            }
        });
        return out;
    }

    function renderEnvForm(keys, masked) {
        const form = document.getElementById('settings-env-form');
        if (!form) return;
        form.innerHTML = Object.entries(keys || {}).map(([key, meta]) => {
            const cons = meta.consumption || {};
            const consHtml = cons.auto || cons.manual ? `
                <dl class="st-env-consumption">
                    ${cons.auto ? `<dt>Consum automat</dt><dd>${esc(cons.auto)}</dd>` : ''}
                    ${cons.manual ? `<dt>Consum manual</dt><dd>${esc(cons.manual)}</dd>` : ''}
                </dl>` : '';
            return `
            <div class="st-env-card">
                <div class="st-env-card__head">
                    <div class="st-env-icon" aria-hidden="true">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    </div>
                    <div>
                        <h4 class="st-env-title">${esc(meta.label)}</h4>
                        <div class="st-env-key">${esc(key)}</div>
                    </div>
                </div>
                <input type="text" name="env_${key}" data-env-key="${esc(key)}" class="st-env-input"
                       value="${esc(masked[key] || '')}" placeholder="Lipește token nou…" autocomplete="off" spellcheck="false">
                ${renderModelField(meta.model)}
                <p class="st-env-hint">${esc(meta.hint)}</p>
                ${consHtml}
                ${meta.test_module ? `<button type="button" class="st-btn st-btn--ghost st-btn--sm st-env-test-btn" data-env-test="${esc(meta.test_module)}">▶ Test modul</button>` : ''}
            </div>`;
        }).join('');
        bindModelFields(form);
    }

    function renderEnvTestResults(payload) {
        const el = document.getElementById('settings-env-tests');
        if (!el || !payload) return;
        const modules = Array.isArray(payload.modules) ? payload.modules : [];
        const summary = payload.summary || {};
        if (!modules.length) {
            el.classList.add('hidden');
            el.innerHTML = '';
            return;
        }
        el.classList.remove('hidden');
        const rows = modules.map(m => {
            const ok = !!m.ok;
            const skip = !!m.skipped;
            const cls = ok ? 'is-ok' : (skip ? 'is-skip' : 'is-fail');
            const icon = ok ? '✓' : (skip ? '○' : '✗');
            const detail = ok
                ? esc(m.message || 'OK')
                : esc(m.error || m.message || 'Eșec');
            return `<tr class="st-env-test-row st-env-test-row--${cls}">
                <td>${icon}</td>
                <td><strong>${esc(m.label || m.module || '')}</strong></td>
                <td>${detail}</td>
                <td><code>${Number(m.duration_ms || 0)} ms</code></td>
            </tr>`;
        }).join('');
        el.innerHTML = `
            <div class="st-env-tests__head">
                <h3 class="st-section-title">Rezultate test module</h3>
                <div class="st-env-tests__summary">
                    <span class="is-ok">${Number(summary.ok || 0)} OK</span>
                    <span class="is-fail">${Number(summary.fail || 0)} eșec</span>
                    <span class="is-skip">${Number(summary.skipped || 0)} sărite</span>
                </div>
            </div>
            <div class="st-auto-table-wrap">
                <table class="st-auto-table st-env-tests-table">
                    <thead><tr><th></th><th>Modul</th><th>Rezultat</th><th>Timp</th></tr></thead>
                    <tbody>${rows}</tbody>
                </table>
            </div>`;
    }

    async function runEnvModuleTests(module) {
        const btnAll = document.getElementById('settings-env-test-all');
        const cardBtns = document.querySelectorAll('.st-env-test-btn');
        if (btnAll) btnAll.disabled = true;
        cardBtns.forEach(b => { b.disabled = true; });
        try {
            const j = await api('POST', { action: 'test_env_modules', module: module || 'all' });
            renderEnvTestResults(j.data || {});
            const sum = j.data?.summary || {};
            const ok = Number(sum.fail || 0) === 0;
            showToast(j.message || 'Teste finalizate.', ok);
            return j;
        } finally {
            if (btnAll) btnAll.disabled = false;
            cardBtns.forEach(b => { b.disabled = false; });
        }
    }

    function renderHub(data) {
        hub = data;
        renderAlerts(data.token_alerts);
        const canUsers = !!data.can_manage_users;
        document.getElementById('settings-users-denied')?.classList.toggle('hidden', canUsers);
        document.getElementById('settings-users-wrap')?.classList.toggle('hidden', !canUsers);
        if (canUsers) {
            renderUsers(data.users);
        }
        renderTokenCards(data.token_budgets, data.token_stats);
        renderAutomationPanel(data.api_automation);
        renderMetroLlmPanel(data.metro_llm);
        renderEnvForm(data.env_keys, data.env_values_masked);
        renderUsageLiveIfActive();
    }

    document.getElementById('settings-metro-llm-panel')?.addEventListener('click', e => {
        const btn = e.target?.closest?.('[data-metro-test]');
        if (btn) {
            runMetroTest(btn.getAttribute('data-metro-test')).catch(err => showToast(err.message, false));
        }
    });

    document.addEventListener('bpa-api-live-changed', function (e) {
        const auto = e.detail && e.detail.api_automation;
        if (auto) {
            if (hub) hub.api_automation = auto;
            renderAutomationPanel(auto);
        }
    });
    document.getElementById('settings-metro-llm-panel')?.addEventListener('submit', e => {
        if (e.target?.id === 'settings-metro-form') {
            saveMetroLlm(e).catch(err => showToast(err.message, false));
        }
    });

    document.getElementById('settings-hub-app')?.addEventListener('click', e => {
        if (e.target?.id === 'settings-usage-refresh' || e.target?.id === 'settings-usage-retry') {
            loadUsageLive(true).catch(() => {});
        }
    });
    document.getElementById('settings-hub-app')?.addEventListener('change', e => {
        if (e.target?.id === 'settings-usage-autorefresh') {
            if (e.target.checked) startUsageLivePoll();
            else stopUsageLivePoll();
        }
    });

    document.getElementById('settings-automation-panel')?.addEventListener('click', e => {
        if (e.target?.id === 'settings-automation-save') {
            saveAutomationControls().catch(err => showToast(err.message, false));
        }
        if (e.target?.id === 'settings-provider-save') {
            saveProviderApiLive().catch(err => showToast(err.message, false));
        }
    });

    function wiringBadge(wiring) {
        const w = String(wiring || 'active');
        if (w === 'dead') return '<span class="st-ollama-badge st-ollama-badge--red">moarte</span>';
        if (w === 'partial') return '<span class="st-ollama-badge st-ollama-badge--yellow">parțial</span>';
        return '<span class="st-ollama-badge st-ollama-badge--green">activ</span>';
    }

    function statusDot(status) {
        const s = String(status || 'red');
        if (s === 'green') return 'st-ollama-dot st-ollama-dot--green';
        if (s === 'yellow') return 'st-ollama-dot st-ollama-dot--yellow';
        return 'st-ollama-dot st-ollama-dot--red';
    }

    function renderOllamaControl(data) {
        const el = document.getElementById('settings-ollama-control');
        const meta = document.getElementById('settings-ollama-generated');
        if (!el) return;
        data = data || {};
        if (meta) {
            meta.textContent = data.generated_at
                ? ('Actualizat: ' + formatUsageTime(String(data.generated_at).replace('T', ' ')))
                : '';
        }

        const modules = Array.isArray(data.modules) ? data.modules : [];
        const moduleCards = modules.map(m => `
            <article class="st-ollama-module-card">
                <header><span class="${statusDot(m.status)}"></span><strong>${esc(m.label || m.module || '')}</strong></header>
                <dl class="st-ollama-dl">
                    <div><dt>URL</dt><dd><code>${esc(m.base_url || '')}</code></dd></div>
                    <div><dt>Model text</dt><dd>${esc(m.text_model || '—')}</dd></div>
                    <div><dt>Vision</dt><dd>${esc(m.vision_model || '—')}</dd></div>
                    <div><dt>Ultimul apel</dt><dd>${m.last_at ? esc(formatUsageTime(String(m.last_at).replace('T', ' '))) : '—'} ${m.last_ok ? '✓' : (m.last_at ? '✗' : '')}</dd></div>
                    <div><dt>Latency</dt><dd>${m.avg_latency_ms ? (m.avg_latency_ms + ' ms medie') : (m.latency_ms ? (m.latency_ms + ' ms acum') : '—')}</dd></div>
                    ${m.last_error ? `<div class="st-ollama-err"><dt>Eroare</dt><dd>${esc(m.last_error)}</dd></div>` : ''}
                </dl>
            </article>`).join('');

        const diff = data.config_diff || {};
        const rows = Array.isArray(diff.rows) ? diff.rows : [];
        const sourceLabels = ['app/Config/.env', 'admin/.env', 'Import/Scraper/.env', 'Import/modulOrchestr/.env', 'MatchingPro/settings.json'];
        const diffHead = sourceLabels.map(s => `<th>${esc(s)}</th>`).join('');
        const diffRows = rows.map(r => {
            const vals = r.values || {};
            const cells = sourceLabels.map(label => {
                const v = vals[label];
                const canonical = r.canonical;
                const conflict = r.has_conflict && v && canonical && v !== canonical;
                return `<td class="${conflict ? 'is-conflict' : ''}">${v != null && v !== '' ? esc(v) : '<span class="st-muted">—</span>'}</td>`;
            }).join('');
            return `<tr><th><code>${esc(r.key || '')}</code>${r.has_conflict ? ' ⚠️' : ''}</th>${cells}</tr>`;
        }).join('') || '<tr><td colspan="6" class="st-table-empty">Nicio cheie OLLAMA_* găsită.</td></tr>';

        const integrations = Array.isArray(data.integrations) ? data.integrations : [];
        const intRows = integrations.map(i => `<tr>
            <td>${wiringBadge(i.wiring)}</td>
            <td>${esc(i.module || '')}</td>
            <td><code>${esc(i.file || '')}</code><br><small>${esc(i.fn || '')}</small></td>
            <td>${esc(i.purpose || '')}</td>
            <td><button type="button" class="st-btn st-btn--ghost st-btn--sm" data-ollama-test="${esc(i.id || '')}">Test apel</button></td>
        </tr>`).join('') || '<tr><td colspan="5" class="st-table-empty">—</td></tr>';

        const errors = Array.isArray(data.error_log) ? data.error_log : [];
        const errRows = errors.slice(0, 30).map(e => `<tr>
            <td>${esc(formatUsageTime(String(e.ts || '').replace('T', ' ')))}</td>
            <td><code>${esc(e.source || '')}</code></td>
            <td>${esc(e.model || '')}</td>
            <td class="st-ollama-err-cell">${esc((e.error || '').slice(0, 120))}</td>
        </tr>`).join('') || '<tr><td colspan="4" class="st-table-empty">Nicio eroare înregistrată.</td></tr>';

        const erp = data.erp_readiness || {};
        const vision = data.vision_readiness || {};
        const conflicts = Array.isArray(diff.conflicts) ? diff.conflicts.length : 0;

        el.innerHTML = `
            <div class="st-ollama-summary">
                <div class="st-ollama-summary__item ${data.enabled ? 'is-ok' : 'is-warn'}">Ollama ${data.enabled ? 'activ' : 'dezactivat'}</div>
                <div class="st-ollama-summary__item ${erp.ready ? 'is-ok' : 'is-warn'}">ERP text: ${erp.ready ? esc(erp.model || 'OK') : 'indisponibil'}</div>
                <div class="st-ollama-summary__item ${vision.ready ? 'is-ok' : 'is-warn'}">Vision: ${vision.ready ? esc(vision.model || 'OK') : 'indisponibil'}</div>
                <div class="st-ollama-summary__item ${conflicts ? 'is-warn' : 'is-ok'}">${conflicts ? (conflicts + ' conflicte config') : 'Config aliniat'}</div>
            </div>

            <h3 class="st-section-title">Status pe modul</h3>
            <div class="st-ollama-modules">${moduleCards || '<p class="st-table-empty">—</p>'}</div>

            <h3 class="st-section-title">Diff configurare OLLAMA_*</h3>
            <div class="st-auto-table-wrap"><table class="st-auto-table st-ollama-diff"><thead><tr><th>Cheie</th>${diffHead}</tr></thead><tbody>${diffRows}</tbody></table></div>

            <h3 class="st-section-title">Hartă integrări</h3>
            <div class="st-auto-table-wrap"><table class="st-auto-table"><thead><tr><th>Stare</th><th>Modul</th><th>Fișier</th><th>Scop</th><th>Test</th></tr></thead><tbody>${intRows}</tbody></table></div>
            <div id="settings-ollama-test-result" class="st-ollama-test-result hidden" aria-live="polite"></div>

            <h3 class="st-section-title">Jurnal erori recente</h3>
            <div class="st-auto-table-wrap"><table class="st-auto-table"><thead><tr><th>Data</th><th>Sursă</th><th>Model</th><th>Mesaj</th></tr></thead><tbody>${errRows}</tbody></table></div>
        `;
    }

    async function loadOllamaControl(force) {
        const el = document.getElementById('settings-ollama-control');
        if (!el) return;
        if (!force && el.dataset.loaded === '1') return;
        el.innerHTML = '<div class="st-ollama-loading">Se încarcă panoul Ollama…</div>';
        let lastErr = new Error('Settings API indisponibil.');
        let j = null;
        for (const base of API_URLS) {
            try {
                const r = await fetch(base + '?view=ollama_control', { method: 'GET', credentials: 'include', headers: { Accept: 'application/json' } });
                const raw = await r.text();
                j = JSON.parse(raw);
                if (!j.success) throw new Error(j.message || 'Eroare');
                break;
            } catch (err) {
                lastErr = err instanceof Error ? err : new Error(String(err));
            }
        }
        if (!j) throw lastErr;
        renderOllamaControl(j.data);
        el.dataset.loaded = '1';
    }

    async function testOllamaIntegration(integrationId) {
        const box = document.getElementById('settings-ollama-test-result');
        if (box) {
            box.classList.remove('hidden');
            box.textContent = 'Test în curs pentru ' + integrationId + '…';
        }
        const j = await api('POST', { action: 'ollama_integration_test', integration_id: integrationId });
        if (box) {
            const d = j.data || {};
            box.innerHTML = `<strong>${j.success ? '✓ Test OK' : '✗ Eșec'}</strong> · ${esc(integrationId)} · ${d.latency_ms || '—'} ms<br>`
                + `<pre class="st-ollama-pre">${esc(d.content || d.error || d.raw || j.message || '')}</pre>`;
        }
        showToast(j.message || (j.success ? 'Test OK' : 'Test eșuat'), !!j.success);
        await loadOllamaControl(true);
    }

    async function loadHub() {
        const j = await api('GET');
        renderHub(j.data);
    }

    document.getElementById('settings-ollama-refresh')?.addEventListener('click', () => {
        loadOllamaControl(true).catch(err => showToast(err.message, false));
    });
    document.getElementById('settings-ollama-control')?.addEventListener('click', e => {
        const btn = e.target?.closest?.('[data-ollama-test]');
        if (!btn) return;
        const id = btn.getAttribute('data-ollama-test');
        if (!id) return;
        testOllamaIntegration(id).catch(err => showToast(err.message, false));
    });

    document.querySelectorAll('.settings-page .st-tab').forEach(tab => {
        tab.addEventListener('click', () => switchTab(tab.dataset.tab));
    });

    const urlTab = new URLSearchParams(location.search).get('tab');
    if (urlTab && document.querySelector(`.settings-page .st-tab[data-tab="${urlTab}"]`)) {
        switchTab(urlTab);
    }

    document.getElementById('settings-chat-perm-all')?.addEventListener('click', () => {
        document.querySelectorAll('.settings-chat-perm-cb').forEach(cb => {
            cb.checked = true;
            syncPermItemState(cb.closest('.st-perm-item'));
        });
        const roleSel = document.getElementById('settings-user-role');
        if (roleSel) roleSel.value = 'custom';
    });

    document.getElementById('settings-user-role')?.addEventListener('change', e => applyRolePreset(e.target.value));
    document.getElementById('settings-user-add')?.addEventListener('click', () => {
        resetUserForm();
        openUserModal('identity');
    });
    document.querySelectorAll('.st-user-modal__tab').forEach(tab => {
        tab.addEventListener('click', () => switchEditorTab(tab.dataset.editorTab || 'identity'));
    });
    document.getElementById('settings-perm-all')?.addEventListener('click', () => {
        document.querySelectorAll('.settings-perm-cb').forEach(cb => {
            cb.checked = true;
            const item = cb.closest('.st-perm-item');
            if (item) syncPermItemState(item);
        });
        updateSectionCounts();
        const roleSel = document.getElementById('settings-user-role');
        if (roleSel) roleSel.value = 'custom';
    });
    document.getElementById('settings-user-cancel')?.addEventListener('click', () => closeUserModal());
    document.querySelectorAll('[data-settings-modal-close]').forEach(el => {
        el.addEventListener('click', () => closeUserModal());
    });
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') closeUserModal();
    });

    document.getElementById('settings-user-form')?.addEventListener('submit', async e => {
        e.preventDefault();
        const fd = new FormData(e.target);
        const perms = [];
        document.querySelectorAll('.settings-perm-cb:checked').forEach(cb => perms.push(cb.value));
        const chatPerms = [];
        document.querySelectorAll('.settings-chat-perm-cb:checked').forEach(cb => chatPerms.push(cb.value));
        const payload = {
            action: 'save_user',
            id: parseInt(fd.get('id') || '0', 10) || undefined,
            fullname: fd.get('fullname'),
            login: fd.get('login'),
            password: fd.get('password'),
            role: fd.get('role'),
            status: fd.get('status'),
            permissions: perms,
            chat_permissions: chatPerms,
        };
        try {
            const j = await api('POST', payload);
            showToast(j.message, true);
            closeUserModal();
            renderUsers(j.data?.users || []);
        } catch (err) { showToast(err.message, false); }
    });

    document.getElementById('settings-budget-form')?.addEventListener('submit', async e => {
        e.preventDefault();
        const fd = new FormData(e.target);
        const budget = hub?.token_budgets?.find(b => b.provider_key === fd.get('provider_key'));
        const costRaw = String(fd.get('cost_per_unit') || '').trim();
        const remainInput = document.getElementById('settings-budget-remaining-input');
        const remainManual = remainInput?.dataset.mode === 'manual' && String(remainInput?.value || '').trim() !== '';
        try {
            const j = await api('POST', {
                action: 'save_token_budget',
                provider_key: fd.get('provider_key'),
                label: budget?.label || fd.get('provider_key'),
                env_key: budget?.env_key,
                monthly_quota: fd.get('monthly_quota'),
                tokens_per_request: fd.get('tokens_per_request'),
                remaining_override: remainManual ? remainInput.value : null,
                cost_per_unit: costRaw === '' ? 0 : costRaw,
                warning_pct: fd.get('warning_pct'),
                is_active: fd.get('is_active') ? 1 : 0,
            });
            showToast(j.message, true);
            hub = { ...hub, token_budgets: j.data?.token_budgets, token_stats: j.data?.token_stats };
            renderTokenCards(j.data?.token_budgets, j.data?.token_stats);
            renderAlerts(j.data?.token_alerts);
            const saved = j.data?.token_budgets?.find(b => b.provider_key === fd.get('provider_key'));
            if (saved) fillBudgetForm(saved);
        } catch (err) { showToast(err.message, false); }
    });

    document.getElementById('settings-budget-provider')?.addEventListener('change', e => {
        const b = hub?.token_budgets?.find(x => x.provider_key === e.target.value);
        if (b) fillBudgetForm(b);
    });

    document.getElementById('settings-budget-remaining-input')?.addEventListener('input', e => {
        const input = e.target;
        input.dataset.mode = 'manual';
        input.classList.add('is-manual');
        document.getElementById('settings-budget-remaining-auto')?.classList.remove('is-hidden');
        updateBudgetStatusDisplay(budgetFromForm());
    });

    document.getElementById('settings-budget-remaining-auto')?.addEventListener('click', () => {
        const form = document.getElementById('settings-budget-form');
        const key = form?.provider_key?.value;
        const base = hub?.token_budgets?.find(b => b.provider_key === key);
        if (!base) return;
        fillBudgetForm({ ...base, remaining_override: null });
    });

    ['monthly_quota', 'tokens_per_request'].forEach(name => {
        document.querySelector(`#settings-budget-form [name="${name}"]`)?.addEventListener('input', () => {
            const b = budgetFromForm();
            if (b) updateBudgetStatusDisplay(b);
        });
    });

    document.getElementById('settings-env-save')?.addEventListener('click', async () => {
        const form = document.getElementById('settings-env-form');
        const env = {};
        document.querySelectorAll('[data-env-key]').forEach(inp => {
            const v = inp.value.trim();
            if (v && !v.includes('•')) env[inp.dataset.envKey] = v;
        });
        Object.assign(env, collectModelValues(form));
        try {
            const j = await api('POST', { action: 'save_env_keys', env });
            showToast(j.message, true);
            if (j.data?.env_keys) hub.env_keys = j.data.env_keys;
            if (j.data?.token_budgets) {
                hub.token_budgets = j.data.token_budgets;
                hub.token_stats = j.data.token_stats;
                hub.api_token_hub = j.data.api_token_hub || hub.api_token_hub;
                renderTokenCards(j.data.token_budgets, j.data.token_stats);
                renderAlerts(j.data.token_alerts || []);
                const activeKey = document.getElementById('settings-budget-form')?.provider_key?.value;
                const refreshed = j.data.token_budgets.find(b => b.provider_key === activeKey);
                if (refreshed) fillBudgetForm(refreshed);
            }
            renderEnvForm(hub.env_keys, j.data?.env_values_masked || hub.env_values_masked);
        } catch (err) { showToast(err.message, false); }
    });

    document.getElementById('settings-env-test-all')?.addEventListener('click', () => {
        runEnvModuleTests('all').catch(err => showToast(err.message, false));
    });

    document.getElementById('settings-env-form')?.addEventListener('click', e => {
        const btn = e.target?.closest?.('[data-env-test]');
        if (!btn) return;
        e.preventDefault();
        runEnvModuleTests(btn.getAttribute('data-env-test')).catch(err => showToast(err.message, false));
    });

    document.getElementById('settings-refresh')?.addEventListener('click', () => {
        const P = window.BesoiuActionProgress;
        if (P) {
            P.run({
                title: 'Reîncărcare setări',
                steps: ['Hub tokeni', 'Metro LLM', 'Utilizatori'],
                task: async (update) => {
                    update(0, 'Se încarcă hub-ul…');
                    await loadHub();
                    update(2, 'Gata.');
                },
                successMessage: 'Setări reîncărcate.',
            }).catch(err => showToast(err.message, false));
            return;
        }
        loadHub().catch(e => showToast(e.message, false));
    });

    /* ---- Module plug / unplug ---- */
    let modulesCatalogCache = null;

    async function loadModulesCatalog() {
        const j = await api('POST', { action: 'modules_list' });
        modulesCatalogCache = j.data;
        renderModulesCatalog(j.data);
        return j.data;
    }

    function renderModulesCatalog(data) {
        const optEl = document.getElementById('settings-modules-optional');
        const coreEl = document.getElementById('settings-modules-core');
        const countEl = document.getElementById('settings-modules-optional-count');
        if (!optEl || !coreEl) return;

        const optional = data?.optional || [];
        const core = data?.core || [];
        const on = optional.filter(m => m.enabled).length;
        if (countEl) {
            countEl.textContent = `${optional.length} module · ${on} active · ${optional.length - on} oprite / lipsă`;
        }

        optEl.innerHTML = optional.map(m => {
            const status = !m.installed
                ? '<span class="st-badge st-badge--warn">Lipsă</span>'
                : (m.enabled
                    ? '<span class="st-badge st-badge--soft">Activ</span>'
                    : '<span class="st-badge st-badge--muted">Oprit</span>');
            const extBadge = m.external ? '<span class="st-badge st-badge--ext">Extern</span>' : '';
            const desc = esc(m.description || '');
            const ver = esc(m.version || '—');
            let actions = '';
            if (!m.installed) {
                actions = m.external
                    ? `<span class="st-hint">Reinstalează din ZIP</span>`
                    : `<button type="button" class="st-btn st-btn--primary st-btn--sm" data-mod-act="restore" data-mod-id="${esc(m.id)}">Restaurează</button>`;
            } else {
                if (m.enabled) {
                    actions += `<button type="button" class="st-btn st-btn--ghost st-btn--sm" data-mod-act="disable" data-mod-id="${esc(m.id)}">Oprește</button>`;
                } else {
                    actions += `<button type="button" class="st-btn st-btn--primary st-btn--sm" data-mod-act="enable" data-mod-id="${esc(m.id)}">Activează</button>`;
                }
                actions += `<button type="button" class="st-btn st-btn--ghost st-btn--sm" data-mod-act="export" data-mod-id="${esc(m.id)}">Export ZIP</button>`;
                actions += `<button type="button" class="st-btn st-btn--danger st-btn--sm" data-mod-act="uninstall" data-mod-id="${esc(m.id)}">Șterge</button>`;
            }
            return `
                <article class="st-module-card" data-module="${esc(m.id)}">
                    <div class="st-module-card__top">
                        <div>
                            <h3 class="st-module-card__title">${esc(m.name || m.id)} ${extBadge}</h3>
                            <p class="st-module-card__meta"><code>${esc(m.id)}</code> · v${ver}</p>
                        </div>
                        ${status}
                    </div>
                    <p class="st-module-card__desc">${desc}</p>
                    <div class="st-module-card__actions">${actions}</div>
                </article>`;
        }).join('') || '<p class="st-table-empty">Niciun modul opțional mapat.</p>';

        coreEl.innerHTML = `
            <ul class="st-modules-core-ul">
                ${core.map(c => `
                    <li>
                        <strong>${esc(c.name || c.id)}</strong>
                        <span class="st-modules-core-id">${esc(c.id)}</span>
                        <span class="st-modules-core-note">${esc(c.why_core || c.note || '')}</span>
                    </li>`).join('')}
            </ul>`;
    }

    async function runModuleAction(act, id) {
        if (act === 'export') {
            await downloadModuleZip(id);
            return;
        }
        const map = {
            enable: 'module_enable',
            disable: 'module_disable',
            uninstall: 'module_uninstall',
            restore: 'module_restore',
        };
        const action = map[act];
        if (!action) return;
        if (act === 'uninstall') {
            const ok = confirm(`Ștergi modulul „${id}”?\n\nSe elimină folderul din admin/modules/. Poți restaura stub-ul ulterior. Codul legacy din src/ rămâne.`);
            if (!ok) return;
        }
        const j = await api('POST', { action, module_id: id });
        showToast(j.message || 'OK', true);
        if (j.data?.catalog) {
            modulesCatalogCache = j.data.catalog;
            renderModulesCatalog(j.data.catalog);
        } else {
            await loadModulesCatalog();
        }
    }

    async function downloadModuleZip(moduleId) {
        const url = API + '?view=module_export&module_id=' + encodeURIComponent(moduleId);
        const csrf = window.BESOIU_ADMIN_CSRF
            || document.querySelector('meta[name="admin-csrf"]')?.content
            || document.getElementById('bpa-api-live-arm')?.getAttribute('data-csrf')
            || document.querySelector('[data-csrf]')?.getAttribute('data-csrf')
            || '';
        const headers = { 'Accept': 'application/zip, application/json' };
        if (csrf) headers['X-Admin-CSRF'] = csrf;
        const r = await fetch(url, { method: 'GET', credentials: 'include', headers });
        const ctype = (r.headers.get('Content-Type') || '').toLowerCase();
        if (!r.ok || ctype.includes('application/json')) {
            const raw = await r.text();
            let msg = 'Export eșuat';
            try {
                const j = JSON.parse(raw);
                msg = j.message || msg;
            } catch (_) {
                msg = raw.slice(0, 160) || msg;
            }
            throw new Error(msg);
        }
        const blob = await r.blob();
        const cd = r.headers.get('Content-Disposition') || '';
        let filename = (moduleId === '_template' || moduleId === 'template' || moduleId === 'hello_demo' || moduleId === 'mvp_kit_template')
            ? 'mvp_kit-template.zip'
            : (moduleId + '.zip');
        const m = /filename="?([^";]+)"?/i.exec(cd);
        if (m && m[1]) filename = m[1];
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        a.remove();
        setTimeout(() => URL.revokeObjectURL(a.href), 2000);
        showToast('Descărcat: ' + filename, true);
    }

    document.getElementById('settings-modules-refresh')?.addEventListener('click', () => {
        loadModulesCatalog().catch(err => showToast(err.message, false));
    });

    document.getElementById('settings-modules-optional')?.addEventListener('click', e => {
        const btn = e.target?.closest?.('[data-mod-act]');
        if (!btn) return;
        e.preventDefault();
        runModuleAction(btn.getAttribute('data-mod-act'), btn.getAttribute('data-mod-id'))
            .catch(err => showToast(err.message, false));
    });

    document.getElementById('settings-module-export-template')?.addEventListener('click', () => {
        downloadModuleZip('_template').catch(err => showToast(err.message, false));
    });

    document.getElementById('settings-module-install-form')?.addEventListener('submit', async e => {
        e.preventDefault();
        const fileInput = document.getElementById('settings-module-zip');
        const file = fileInput?.files?.[0];
        if (!file) {
            showToast('Alege un fișier ZIP.', false);
            return;
        }
        const fd = new FormData();
        fd.append('action', 'module_install');
        fd.append('module_zip', file);
        if (document.getElementById('settings-module-overwrite')?.checked) {
            fd.append('overwrite', '1');
        }
        const csrf = window.BESOIU_ADMIN_CSRF
            || document.querySelector('meta[name="admin-csrf"]')?.content
            || document.getElementById('bpa-api-live-arm')?.getAttribute('data-csrf')
            || document.querySelector('[data-csrf]')?.getAttribute('data-csrf')
            || '';
        const headers = { 'Accept': 'application/json' };
        if (csrf) headers['X-Admin-CSRF'] = csrf;
        try {
            const r = await fetch(API, { method: 'POST', credentials: 'include', headers, body: fd });
            const raw = await r.text();
            let j;
            try { j = JSON.parse(raw); } catch (_) {
                throw new Error('Răspuns invalid: ' + raw.slice(0, 120));
            }
            if (!j.success) throw new Error(j.message || 'Instalare eșuată');
            showToast(j.message || 'Modul instalat.', true);
            if (j.data?.catalog) {
                modulesCatalogCache = j.data.catalog;
                renderModulesCatalog(j.data.catalog);
            } else {
                await loadModulesCatalog();
            }
            if (fileInput) fileInput.value = '';
        } catch (err) {
            showToast(err.message || String(err), false);
        }
    });

    initPermDelegation();
    if (window.BpaAsync?.defer) BpaAsync.defer(() => loadHub().catch(e => showToast(e.message, false)));
    else loadHub().catch(e => showToast(e.message, false));
})();
</script>
