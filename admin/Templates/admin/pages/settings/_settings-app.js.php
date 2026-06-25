<script>
(function SettingsHub() {
    'use strict';
    const API = '/admin/api/settings_endpoint.php';
    const toast = document.getElementById('settings-toast');
    let hub = null;

    const PROVIDER_META = {
        rapidapi_tecdoc: { abbr: 'TD', color: '#0ea5e9' },
        scrape_do:       { abbr: 'SD', color: '#f97316' },
        openai:          { abbr: 'AI', color: '#10b981' },
        cursor:          { abbr: 'CR', color: '#7c3aed' },
        groq:            { abbr: 'GQ', color: '#8b5cf6' },
        gemini:          { abbr: 'GM', color: '#3b82f6' },
        grok:            { abbr: 'GK', color: '#334155' },
    };

    function esc(s) {
        const d = document.createElement('div');
        d.textContent = s ?? '';
        return d.innerHTML;
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
        if (body) opts.body = JSON.stringify(body);
        const r = await fetch(API, opts);
        const j = await r.json();
        if (!j.success) throw new Error(j.message || 'Eroare API');
        return j;
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

    function openUserModal() {
        const modal = document.getElementById('settings-user-modal');
        if (!modal) return;
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        modal.querySelector('[name="fullname"]')?.focus();
    }

    function closeUserModal() {
        const modal = document.getElementById('settings-user-modal');
        if (!modal) return;
        modal.classList.add('hidden');
        document.body.style.overflow = '';
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
        applyRolePreset('operator');
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
        openUserModal();
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
            tbody.innerHTML = '<tr><td colspan="5" class="st-table-empty">Niciun utilizator înregistrat.</td></tr>';
            return;
        }
        tbody.innerHTML = users.map(u => `
            <tr>
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
        const maxQueries = Math.floor(quota / tpr);
        const queriesLeft = Math.max(0, Math.floor(remaining / tpr));
        return {
            quota, tpr, requests, usedTokens, remaining, remainingAuto, usedPct, remainingPct,
            maxQueries, queriesLeft, isManualRemaining: hasOverride,
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

    function renderTokenCards(budgets, stats) {
        const wrap = document.getElementById('settings-token-cards');
        const select = document.getElementById('settings-budget-provider');
        if (!wrap) return;
        const byStats = stats?.by_provider || {};
        wrap.innerHTML = (budgets || []).map(b => {
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
            return `
                <article class="st-token-card ${cardCls}" data-provider="${esc(key)}">
                    <div class="st-token-head">
                        <div class="st-token-icon" style="background:${meta.color}">${esc(meta.abbr)}</div>
                        <div>
                            <h3 class="st-token-name">${esc(b.label || key)}</h3>
                            <div class="st-token-key">${esc(key)}</div>
                        </div>
                    </div>
                    <div class="st-token-stats">
                        <span class="st-token-used">${u.remaining.toLocaleString('ro-RO')}</span>
                        <span class="st-token-quota">rămași din ${u.quota.toLocaleString('ro-RO')} tokeni</span>
                    </div>
                    <div class="st-token-bar" role="progressbar" aria-valuenow="${u.remainingPct}" aria-valuemin="0" aria-valuemax="100" title="Tokeni rămași">
                        <div class="st-token-bar-fill ${barCls}" style="width:${u.remainingPct}%"></div>
                    </div>
                    <dl class="st-token-meta">
                        <div><dt>Tokeni / query</dt><dd>${u.tpr.toLocaleString('ro-RO')}</dd></div>
                        <div><dt>Consumat lună</dt><dd>${u.usedTokens.toLocaleString('ro-RO')}</dd></div>
                        <div><dt>Query-uri rămase</dt><dd>~${u.queriesLeft.toLocaleString('ro-RO')}</dd></div>
                        <div><dt>Cost lună</dt><dd>${cost.toFixed(2)} RON</dd></div>
                    </dl>
                </article>
            `;
        }).join('');

        if (select) {
            select.innerHTML = (budgets || []).map(b =>
                `<option value="${esc(b.provider_key)}">${esc(b.label || b.provider_key)}</option>`
            ).join('');
            const first = budgets?.[0];
            if (first) fillBudgetForm(first);
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
            detailEl.textContent = `${u.usedTokens.toLocaleString('ro-RO')} consumați din ${u.quota.toLocaleString('ro-RO')} · ~${u.queriesLeft.toLocaleString('ro-RO')} query-uri disponibile · ${u.tpr.toLocaleString('ro-RO')} tokeni/query${manualNote}`;
        }
        if (barEl) barEl.style.width = u.remainingPct + '%';
        if (pctEl) pctEl.textContent = u.remainingPct + '% rămas';
        box.querySelector('.st-budget-status__bar')?.setAttribute('aria-valuenow', String(u.remainingPct));
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
        form.innerHTML = Object.entries(keys || {}).map(([key, meta]) => `
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
            </div>
        `).join('');
        bindModelFields(form);
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
        renderEnvForm(data.env_keys, data.env_values_masked);
    }

    async function loadHub() {
        const j = await api('GET');
        renderHub(j.data);
    }

    document.querySelectorAll('.settings-page .st-tab').forEach(tab => {
        tab.addEventListener('click', () => switchTab(tab.dataset.tab));
    });

    const urlTab = new URLSearchParams(location.search).get('tab');
    if (urlTab && document.querySelector(`.settings-page .st-tab[data-tab="${urlTab}"]`)) {
        switchTab(urlTab);
    }

    document.getElementById('settings-user-role')?.addEventListener('change', e => applyRolePreset(e.target.value));
    document.getElementById('settings-user-add')?.addEventListener('click', () => {
        resetUserForm();
        openUserModal();
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
        const payload = {
            action: 'save_user',
            id: parseInt(fd.get('id') || '0', 10) || undefined,
            fullname: fd.get('fullname'),
            login: fd.get('login'),
            password: fd.get('password'),
            role: fd.get('role'),
            status: fd.get('status'),
            permissions: perms,
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
            renderEnvForm(hub.env_keys, j.data?.env_values_masked || hub.env_values_masked);
        } catch (err) { showToast(err.message, false); }
    });

    document.getElementById('settings-refresh')?.addEventListener('click', () => loadHub().catch(e => showToast(e.message, false)));

    initPermDelegation();
    if (window.BpaAsync?.defer) BpaAsync.defer(() => loadHub().catch(e => showToast(e.message, false)));
    else loadHub().catch(e => showToast(e.message, false));
})();
</script>
