(function () {
    'use strict';

    const root = document.getElementById('nav-mod-app');
    if (!root) return;

    const apiUrls = [
        root.getAttribute('data-api') || '/admin/api/nav_registry_endpoint.php',
        '/admin/public/api/nav_registry_endpoint.php',
    ];

    const WS = window.NAV_MOD_BOOT?.workspaces || {};
    const WS_ORDER = window.NAV_MOD_BOOT?.workspaceOrder || Object.keys(WS);
    let treeCache = window.NAV_MOD_BOOT?.tree || [];
    let rawCache = window.NAV_MOD_BOOT?.raw || {};
    let activeWorkspaceId = null;

    const ICONS = {
        orders: '<svg width="32" height="32" viewBox="0 0 48 48" fill="none" aria-hidden="true"><circle cx="17" cy="38" r="3" stroke="currentColor" stroke-width="2.2"/><circle cx="33" cy="38" r="3" stroke="currentColor" stroke-width="2.2"/><path d="M6 8h4l2.8 14H34l3-12H12" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        suppliers: '<svg width="32" height="32" viewBox="0 0 48 48" fill="none" aria-hidden="true"><rect x="10" y="12" width="28" height="22" rx="3" stroke="currentColor" stroke-width="2.2"/><path d="M10 18h28M18 12v22M30 12v22" stroke="currentColor" stroke-width="2.2"/></svg>',
        ai: '<svg width="32" height="32" viewBox="0 0 48 48" fill="none" aria-hidden="true"><rect x="11" y="16" width="26" height="20" rx="6" stroke="currentColor" stroke-width="2.2"/><circle cx="20" cy="26" r="2.5" fill="currentColor"/><circle cx="28" cy="26" r="2.5" fill="currentColor"/><path d="M24 8v6M17 10l2 3M31 10l-2 3" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/></svg>',
        social: '<svg width="32" height="32" viewBox="0 0 48 48" fill="none" aria-hidden="true"><path d="M14 18c2 2 8 2 10 0 2-2 2-6 0-8-2-2-8-2-10 0-2 2-2 6 0 8z" stroke="currentColor" stroke-width="2.2"/><path d="M12 32c3 2 9 2 12 0" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/></svg>',
        marketing: '<svg width="32" height="32" viewBox="0 0 48 48" fill="none" aria-hidden="true"><path d="M8 22v8l8-4 10 6V16l-10 6-8-4z" stroke="currentColor" stroke-width="2.2" stroke-linejoin="round"/><path d="M32 12l8-4v20l-8-4" stroke="currentColor" stroke-width="2.2" stroke-linejoin="round"/></svg>',
        shop: '<svg width="32" height="32" viewBox="0 0 48 48" fill="none" aria-hidden="true"><rect x="10" y="14" width="28" height="20" rx="3" stroke="currentColor" stroke-width="2.2"/><path d="M10 20h28" stroke="currentColor" stroke-width="2.2"/><path d="M14 26h12M14 30h8" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/></svg>',
        company: '<svg width="32" height="32" viewBox="0 0 48 48" fill="none" aria-hidden="true"><circle cx="24" cy="24" r="14" stroke="currentColor" stroke-width="2.2"/><circle cx="24" cy="24" r="5" stroke="currentColor" stroke-width="2.2"/><path d="M24 10v4M24 34v4M10 24h4M34 24h4" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/></svg>',
        navigatie: '<svg width="32" height="32" viewBox="0 0 48 48" fill="none" aria-hidden="true"><path d="M8 12h32M8 24h22M8 36h28" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/><circle cx="38" cy="24" r="4" stroke="currentColor" stroke-width="2.2"/></svg>',
        admin_panel: '<svg width="32" height="32" viewBox="0 0 48 48" fill="none" aria-hidden="true"><rect x="10" y="10" width="28" height="28" rx="4" stroke="currentColor" stroke-width="2.2"/><path d="M16 18h16M16 24h10M16 30h14" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/></svg>',
        default: '<svg width="32" height="32" viewBox="0 0 48 48" fill="none" aria-hidden="true"><rect x="12" y="12" width="24" height="24" rx="4" stroke="currentColor" stroke-width="2.2"/></svg>',
    };

    const ARROW = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>';

    function esc(s) {
        return String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
    }

    function isActive(v) {
        if (typeof v === 'boolean') return v;
        if (typeof v === 'number') return v === 1;
        if (typeof v === 'string') return ['1', 'true', 'yes', 'on'].includes(v.toLowerCase());
        return !!v;
    }

    function showToast(message, ok = true) {
        const el = document.getElementById('nav-mod-toast');
        if (!el) return;
        el.textContent = message;
        el.classList.remove('hidden', 'is-error', 'is-ok');
        el.classList.add(ok ? 'is-ok' : 'is-error');
        clearTimeout(showToast._t);
        showToast._t = setTimeout(() => el.classList.add('hidden'), 2800);
    }

    async function saveItemFromRow(row) {
        const id = row?.querySelector('[data-save-item]')?.getAttribute('data-save-item');
        if (!id || !row) return;
        const j = await api('save_item', {
            item_id: id,
            label: row.querySelector('.nav-item-label')?.value || '',
            url: row.querySelector('.nav-item-url')?.value || '',
            is_active: row.querySelector('.nav-item-active')?.checked ? 1 : 0,
        });
        await applySnapshot(j);
        showToast(j.message || 'Link salvat.', true);
    }

    function wsMeta(wsId) {
        const id = String(wsId || 'company');
        return WS[id] || {
            label: id,
            desc: 'Zonă meniu sidebar.',
            accent: '#64748b',
            accent2: '#475569',
        };
    }

    function sectionIcon(wsId) {
        return ICONS[String(wsId)] || ICONS.default;
    }

    function wsSectionKeys(wsId) {
        const keys = WS[String(wsId)]?.sectionKeys;
        return Array.isArray(keys) ? keys.map(String) : null;
    }

    function blocksForWorkspace(wsId) {
        const sectionKeys = wsSectionKeys(wsId);
        if (sectionKeys) {
            return (treeCache || []).filter(b => sectionKeys.includes(String(b.section?.section_key || b.section?.id || '')));
        }
        return (treeCache || []).filter(b => String(b.section?.workspace || 'company') === String(wsId));
    }

    function workspaceForItem(item, section) {
        const sectionKey = String(section?.section_key || section?.id || '');
        for (const wsId of WS_ORDER) {
            const keys = wsSectionKeys(wsId);
            if (keys && keys.includes(sectionKey)) {
                return wsId;
            }
        }
        return String(section?.workspace || 'company');
    }

    function allNavItemsFlat() {
        const out = [];
        (treeCache || []).forEach(block => {
            const section = block.section || {};
            (block.items || []).forEach(item => {
                out.push({
                    item,
                    section,
                    workspaceId: workspaceForItem(item, section),
                });
            });
        });
        return out;
    }

    function workspaceStats(wsId) {
        const blocks = blocksForWorkspace(wsId);
        const items = blocks.flatMap(b => b.items || []);
        const active = items.filter(it => isActive(it.is_active));
        return {
            sections: blocks.length,
            links: items.length,
            activeLinks: active.length,
        };
    }

    function sourceLabel(item) {
        const src = String(item.source || 'core');
        const mod = item.module_id ? ` · ${item.module_id}` : '';
        if (src === 'manual') return 'Manual' + mod;
        if (src === 'module') return 'Modul' + mod;
        return 'CORE' + mod;
    }

    async function api(action, extra) {
        let lastErr = new Error('API navigație indisponibil.');
        for (const apiUrl of apiUrls) {
            try {
                const res = await fetch(apiUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify(Object.assign({ action }, extra || {})),
                });
                const j = await res.json().catch(() => ({}));
                if (!res.ok || !j.success) {
                    throw new Error(j.message || 'Eroare API navigație.');
                }
                return j;
            } catch (err) {
                lastErr = err instanceof Error ? err : new Error(String(err));
            }
        }
        throw lastErr;
    }

    function updateCount() {
        const countEl = document.getElementById('nav-mod-count');
        if (!countEl) return;
        const zones = WS_ORDER.filter(id => workspaceStats(id).links > 0);
        const totalLinks = (treeCache || []).reduce((n, b) => n + (b.items?.length || 0), 0);
        countEl.textContent = `${zones.length} zone · ${totalLinks} linkuri totale`;
    }

    function setView(mode) {
        const overview = document.getElementById('nav-mod-overview');
        const detail = document.getElementById('nav-mod-detail');
        const addSectionBtn = document.getElementById('nav-mod-add-section');
        if (mode === 'detail') {
            overview?.classList.add('is-hidden');
            detail?.classList.remove('is-hidden');
            addSectionBtn?.classList.add('is-hidden');
        } else {
            activeWorkspaceId = null;
            overview?.classList.remove('is-hidden');
            detail?.classList.add('is-hidden');
            addSectionBtn?.classList.remove('is-hidden');
        }
    }

    function renderWorkspaceCard(wsId, interactive) {
        const ws = wsMeta(wsId);
        const stats = workspaceStats(wsId);
        if (stats.links === 0 && interactive) {
            return '';
        }
        const accent = ws.accent || '#64748b';
        const accent2 = ws.accent2 || accent;
        const desc = `${stats.activeLinks} linkuri active · ${stats.sections} subsecțiuni · ${ws.desc || ''}`;
        const tag = interactive ? 'button' : 'div';
        const attrs = interactive
            ? ` type="button" class="besoiu-ws-card besoiu-ws-card--${esc(wsId)}" data-open-workspace="${esc(wsId)}" style="--ws-a: ${esc(accent)}; --ws-a2: ${esc(accent2)}"`
            : ` class="besoiu-ws-card besoiu-ws-card--${esc(wsId)}" style="--ws-a: ${esc(accent)}; --ws-a2: ${esc(accent2)}"`;

        return `<${tag}${attrs}>
            <div class="besoiu-ws-card__head">
                <div class="besoiu-ws-card__icon">${sectionIcon(wsId)}</div>
                <h2 class="besoiu-ws-card__title">${esc(ws.label)}</h2>
            </div>
            <p class="besoiu-ws-card__desc">${esc(desc)}</p>
            <span class="besoiu-ws-card__btn"><span>Intră</span>${ARROW}</span>
        </${tag}>`;
    }

    function renderWorkspaceCards() {
        const el = document.getElementById('nav-mod-cards');
        if (!el) return;
        updateCount();
        const html = WS_ORDER.map(id => renderWorkspaceCard(id, true)).filter(Boolean).join('');
        el.innerHTML = html || '<p class="st-table-empty">Registru gol. Apasă Resincronizează module.</p>';
    }

    function renderSearchResults(query) {
        const box = document.getElementById('nav-mod-search-results');
        if (!box) return;
        const q = String(query || '').trim().toLowerCase();
        if (q.length < 2) {
            box.classList.add('is-hidden');
            box.innerHTML = '';
            return;
        }
        const matches = allNavItemsFlat().filter(row => {
            const label = String(row.item?.label || '').toLowerCase();
            const url = String(row.item?.url || '').toLowerCase();
            const sec = String(row.section?.label || '').toLowerCase();
            return label.includes(q) || url.includes(q) || sec.includes(q);
        }).slice(0, 12);

        if (!matches.length) {
            box.innerHTML = '<p class="nav-mod__search-empty">Niciun link găsit.</p>';
            box.classList.remove('is-hidden');
            return;
        }

        box.innerHTML = matches.map(row => {
            const ws = wsMeta(row.workspaceId);
            const active = isActive(row.item?.is_active);
            return `<button type="button" class="nav-mod__search-hit" data-open-workspace="${esc(row.workspaceId)}" data-item-id="${esc(row.item?.id || '')}">
                <span class="nav-mod__search-hit-label">${esc(row.item?.label || '')}${active ? '' : ' <em>(ascuns)</em>'}</span>
                <span class="nav-mod__search-hit-meta">${esc(ws.label)} · ${esc(row.item?.url || '')}</span>
            </button>`;
        }).join('');
        box.classList.remove('is-hidden');
    }

    function renderWorkspaceDetail(wsId) {
        const heroEl = document.getElementById('nav-mod-detail-hero');
        const el = document.getElementById('nav-mod-detail-content');
        if (!el) return;

        if (heroEl) {
            heroEl.innerHTML = renderWorkspaceCard(wsId, false);
        }

        const blocks = blocksForWorkspace(wsId);
        if (!blocks.length) {
            el.innerHTML = '<p class="st-table-empty">Nicio navigație în această zonă.</p>';
            return;
        }

        el.innerHTML = blocks.map(block => {
            const s = block.section || {};
            const sid = String(s.id || s.section_key || '');
            const items = block.items || [];
            const mod = s.module_id ? `modul <code>${esc(s.module_id)}</code>` : 'CORE';
            const group = s.group_label ? esc(String(s.group_label)) : '—';

            const rows = items.map(it => {
                const iid = it.id || `${sid}::${it.item_key || ''}`;
                const src = sourceLabel(it);
                const canDelete = String(it.source || '') === 'manual';
                const deleteLabel = canDelete ? 'Șterge' : 'Ascunde';
                return `<tr class="${isActive(it.is_active) ? '' : 'nav-mod__row-off'}">
                    <td><input type="text" class="st-input st-input--sm nav-item-label" value="${esc(it.label || '')}"></td>
                    <td><input type="text" class="st-input st-input--sm nav-item-url" value="${esc(it.url || '')}" title="${esc(it.url || '')}"></td>
                    <td><span class="nav-mod__pill">${esc(src)}</span></td>
                    <td class="st-tc"><label class="st-field st-field--check"><input type="checkbox" class="nav-item-active" ${isActive(it.is_active) ? 'checked' : ''}></label></td>
                    <td class="st-tr nav-mod__row-actions">
                        <button type="button" class="st-btn st-btn--primary st-btn--sm" data-save-item="${esc(iid)}">Salvează</button>
                        <button type="button" class="st-btn st-btn--ghost st-btn--sm nav-mod__btn-danger" data-delete-item="${esc(iid)}" data-item-source="${esc(it.source || 'core')}">${deleteLabel}</button>
                    </td>
                </tr>`;
            }).join('');

            return `<article class="nav-mod__zone-block" data-section-id="${esc(sid)}">
                <header class="nav-mod__zone-block-head">
                    <div>
                        <h3 class="nav-mod__zone-block-title">${esc(s.label || sid)}</h3>
                        <p class="nav-mod__zone-block-meta">Grup sidebar: <strong>${group}</strong> · ${mod} · ${items.length} linkuri</p>
                    </div>
                    <div class="nav-mod__zone-block-actions">
                        <label class="st-field st-field--inline"><span class="st-label">Titlu</span>
                        <input type="text" class="st-input st-input--sm nav-section-label" value="${esc(s.label || '')}"></label>
                        <label class="st-field st-field--check st-field--inline"><input type="checkbox" class="nav-section-active" ${isActive(s.is_active) ? 'checked' : ''}> Activă</label>
                        <button type="button" class="st-btn st-btn--ghost st-btn--sm" data-save-section="${esc(sid)}">Salvează</button>
                        <button type="button" class="st-btn st-btn--ghost st-btn--sm" data-add-item="${esc(sid)}">+ Link aici</button>
                        ${s.module_id ? `<button type="button" class="st-btn st-btn--ghost st-btn--sm" data-reset-module="${esc(s.module_id)}">Reset modul</button>` : ''}
                    </div>
                </header>
                <div class="st-table-wrap"><table class="st-table st-table--compact"><thead><tr>
                    <th>Label</th><th>URL / legătură</th><th>Sursă</th><th class="st-tc">Activ</th><th class="st-tr">Acțiuni</th>
                </tr></thead><tbody>${rows || '<tr><td colspan="5" class="st-table-empty">Fără linkuri</td></tr>'}</tbody></table></div>
            </article>`;
        }).join('');
    }

    function openWorkspace(wsId) {
        activeWorkspaceId = wsId;
        renderWorkspaceDetail(wsId);
        setView('detail');
        document.getElementById('nav-mod-detail')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function refreshCurrentView() {
        updateCount();
        if (activeWorkspaceId) {
            renderWorkspaceDetail(activeWorkspaceId);
            setView('detail');
        } else {
            renderWorkspaceCards();
            setView('overview');
        }
    }

    async function applySnapshot(j) {
        const snap = j.data || {};
        if (Array.isArray(snap.tree)) treeCache = snap.tree;
        if (snap.raw && typeof snap.raw === 'object') rawCache = snap.raw;
        refreshCurrentView();
        syncJsonEditor(rawCache);
    }

    function syncJsonEditor(raw) {
        const ta = document.getElementById('nav-mod-json');
        if (!ta) return;
        ta.value = JSON.stringify(raw || {}, null, 2);
    }

    async function reloadAll() {
        const j = await api('list');
        treeCache = j.data?.tree || [];
        rawCache = j.data?.raw || {};
        refreshCurrentView();
        syncJsonEditor(rawCache);
    }

    root.querySelectorAll('[data-nav-tab]').forEach(btn => {
        btn.addEventListener('click', () => {
            const tab = btn.getAttribute('data-nav-tab');
            root.querySelectorAll('[data-nav-tab]').forEach(b => b.classList.toggle('is-active', b === btn));
            root.querySelectorAll('[data-nav-panel]').forEach(p => {
                p.classList.toggle('is-active', p.getAttribute('data-nav-panel') === tab);
            });
        });
    });

    document.getElementById('nav-mod-back')?.addEventListener('click', () => {
        activeWorkspaceId = null;
        renderWorkspaceCards();
        setView('overview');
    });

    document.getElementById('nav-mod-reload')?.addEventListener('click', () => reloadAll().catch(err => showToast(err.message, false)));

    document.getElementById('nav-mod-sync')?.addEventListener('click', async () => {
        try {
            const j = await api('sync');
            await applySnapshot(j);
            showToast(j.message || 'Resincronizat.', true);
        } catch (err) {
            showToast(err.message, false);
        }
    });

    document.getElementById('nav-mod-save-json')?.addEventListener('click', async () => {
        const ta = document.getElementById('nav-mod-json');
        let parsed;
        try {
            parsed = JSON.parse(ta?.value || '{}');
        } catch (e) {
            showToast('JSON invalid: ' + e.message, false);
            return;
        }
        try {
            const j = await api('save_raw', { raw: parsed });
            await applySnapshot(j);
            showToast(j.message || 'JSON salvat.', true);
        } catch (err) {
            showToast(err.message, false);
        }
    });

    document.getElementById('nav-mod-add-section')?.addEventListener('click', async () => {
        const label = prompt('Titlu secțiune nouă:', 'Secțiune manuală');
        if (label === null) return;
        const ws = prompt('Workspace (orders, suppliers, ai, social, marketing, shop, company):', 'company') || 'company';
        const j = await api('add_section', { label, workspace: ws });
        await applySnapshot(j);
    });

    document.getElementById('nav-mod-add-link-zone')?.addEventListener('click', async () => {
        if (!activeWorkspaceId) return;
        const blocks = blocksForWorkspace(activeWorkspaceId);
        if (!blocks.length) {
            alert('Nu există subsecțiuni în această zonă. Creează mai întâi o secțiune.');
            return;
        }
        let sectionKey = blocks.length === 1
            ? String(blocks[0].section?.section_key || blocks[0].section?.id || '')
            : '';
        if (!sectionKey) {
            const names = blocks.map((b, i) => `${i + 1}. ${b.section?.label || b.section?.section_key}`).join('\n');
            const pick = prompt(`Alege subsecțiunea (număr):\n${names}`, '1');
            if (pick === null) return;
            const idx = parseInt(pick, 10) - 1;
            if (idx < 0 || idx >= blocks.length) return;
            sectionKey = String(blocks[idx].section?.section_key || blocks[idx].section?.id || '');
        }
        const label = prompt('Label link:', 'Link nou');
        if (label === null || !sectionKey) return;
        const url = prompt('URL:', '/admin/dashboard') || '/admin/dashboard';
        await api('add_item', { section_key: sectionKey, label, url });
        await reloadAll();
    });

    document.getElementById('nav-mod-cards')?.addEventListener('click', e => {
        const card = e.target?.closest?.('[data-open-workspace]');
        if (!card) return;
        openWorkspace(card.getAttribute('data-open-workspace') || '');
    });

    document.getElementById('nav-mod-search')?.addEventListener('input', e => {
        renderSearchResults(e.target?.value || '');
    });

    document.getElementById('nav-mod-search-results')?.addEventListener('click', e => {
        const hit = e.target?.closest?.('[data-open-workspace]');
        if (!hit) return;
        const wsId = hit.getAttribute('data-open-workspace') || '';
        openWorkspace(wsId);
        document.getElementById('nav-mod-search-results')?.classList.add('is-hidden');
        const itemId = hit.getAttribute('data-item-id') || '';
        if (itemId) {
            requestAnimationFrame(() => {
                const row = document.querySelector(`[data-save-item="${CSS.escape(itemId)}"]`)?.closest('tr');
                row?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                row?.classList.add('nav-mod__row-flash');
                setTimeout(() => row?.classList.remove('nav-mod__row-flash'), 1800);
            });
        }
    });

    document.getElementById('nav-mod-detail-content')?.addEventListener('change', async e => {
        const target = e.target;
        if (!target) return;
        if (target.classList.contains('nav-item-active')) {
            const row = target.closest('tr');
            try {
                await saveItemFromRow(row);
            } catch (err) {
                target.checked = !target.checked;
                showToast(err.message, false);
            }
            return;
        }
        if (target.classList.contains('nav-section-active')) {
            const block = target.closest('.nav-mod__zone-block');
            const id = block?.getAttribute('data-section-id');
            if (!id || !block) return;
            try {
                const j = await api('save_section', {
                    section_id: id,
                    label: block.querySelector('.nav-section-label')?.value || '',
                    is_active: target.checked ? 1 : 0,
                });
                await applySnapshot(j);
                showToast(j.message || 'Secțiune actualizată.', true);
            } catch (err) {
                target.checked = !target.checked;
                showToast(err.message, false);
            }
        }
    });

    document.getElementById('nav-mod-detail-content')?.addEventListener('click', async e => {
        const saveItem = e.target?.closest?.('[data-save-item]');
        if (saveItem) {
            const row = saveItem.closest('tr');
            try {
                await saveItemFromRow(row);
            } catch (err) {
                showToast(err.message, false);
            }
            return;
        }
        const deleteItem = e.target?.closest?.('[data-delete-item]');
        if (deleteItem) {
            const id = deleteItem.getAttribute('data-delete-item');
            const src = deleteItem.getAttribute('data-item-source') || 'core';
            if (!id) return;
            const msg = src === 'manual'
                ? 'Ștergi definitiv acest link manual?'
                : 'Ascunzi acest link din meniu?';
            if (!confirm(msg)) return;
            try {
                const j = await api('delete_item', { item_id: id });
                await applySnapshot(j);
                showToast(j.message || 'Link ascuns.', true);
            } catch (err) {
                showToast(err.message, false);
            }
            return;
        }
        const saveSection = e.target?.closest?.('[data-save-section]');
        if (saveSection) {
            const id = saveSection.getAttribute('data-save-section');
            const block = saveSection.closest('.nav-mod__zone-block');
            if (!id || !block) return;
            try {
                const j = await api('save_section', {
                    section_id: id,
                    label: block.querySelector('.nav-section-label')?.value || '',
                    is_active: block.querySelector('.nav-section-active')?.checked ? 1 : 0,
                });
                await applySnapshot(j);
                showToast(j.message || 'Secțiune salvată.', true);
            } catch (err) {
                showToast(err.message, false);
            }
            return;
        }
        const addItem = e.target?.closest?.('[data-add-item]');
        if (addItem) {
            const sectionKey = addItem.getAttribute('data-add-item');
            const label = prompt('Label link:', 'Link nou');
            if (label === null || !sectionKey) return;
            const url = prompt('URL:', '/admin/dashboard') || '/admin/dashboard';
            await api('add_item', { section_key: sectionKey, label, url });
            await reloadAll();
            return;
        }
        const resetMod = e.target?.closest?.('[data-reset-module]');
        if (resetMod) {
            const mod = resetMod.getAttribute('data-reset-module');
            if (!mod || !confirm(`Resetezi navigația modulului „${mod}”?`)) return;
            const j = await api('reset_module', { module_id: mod });
            await applySnapshot(j);
        }
    });

    renderWorkspaceCards();
    syncJsonEditor(rawCache);
    setView('overview');

    if (window.location.hash === '#json') {
        root.querySelector('[data-nav-tab="json"]')?.click();
    }
})();
