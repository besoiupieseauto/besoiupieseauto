/**
 * Import Pro — AI overlay (Modul 1 semantic, Modul 2 normalizare, Modul 6 rezumat cron).
 * Principiu: AI propune, omul confirmă — fără auto-apply.
 */
(function (global) {
    'use strict';

    const state = {
        apiUrl: '',
        modules: { mod_01: null, mod_02: null, mod_06: null },
        pending: new Map(),
        cronSummaryBusy: false,
    };

    let hooks = {};

    function esc(s) {
        if (hooks.escapeHtml) return hooks.escapeHtml(s);
        const d = document.createElement('div');
        d.textContent = String(s ?? '');
        return d.innerHTML;
    }

    function toast(msg, type) {
        if (typeof hooks.showToast === 'function') hooks.showToast(msg, type || 'info');
    }

    async function aiPost(action, body, timeoutMs) {
        const url = String(state.apiUrl || '').trim();
        if (!url) throw new Error('AI RAG API indisponibil');
        const ctrl = new AbortController();
        const t = setTimeout(() => ctrl.abort(), timeoutMs || 120000);
        try {
            const res = await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify(Object.assign({ action }, body || {})),
                signal: ctrl.signal,
            });
            const raw = await res.text();
            let json;
            try { json = JSON.parse(raw); } catch (_) { throw new Error('Răspuns AI invalid'); }
            if (!json.success && !json.data) throw new Error(json.message || 'Eroare AI');
            return json;
        } finally {
            clearTimeout(t);
        }
    }

    async function loadModuleStatus() {
        const url = String(state.apiUrl || '').trim();
        if (!url) return;
        try {
            const u = new URL(url, location.origin);
            u.searchParams.set('action', 'import_pro_status');
            const res = await fetch(u.toString(), { credentials: 'same-origin', headers: { Accept: 'application/json' } });
            const json = await res.json();
            if (json.success && json.data) {
                state.modules.mod_01 = json.data.mod_01;
                state.modules.mod_02 = json.data.mod_02;
                state.modules.mod_06 = json.data.mod_06;
            }
        } catch (_) { /* silent */ }
    }

    function renderCronSummary(structured, loading) {
        const panel = document.getElementById('aiCronSummaryPanel');
        const body = document.getElementById('aiCronSummaryBody');
        if (!panel || !body) return;
        if (loading) {
            panel.hidden = false;
            body.innerHTML = '<p class="ai-cron-summary-loading">Se generează rezumat AI (Modul 6)…</p>';
            return;
        }
        if (!structured || !structured.summary_ro) {
            body.innerHTML = '<p class="hint">Rezumat indisponibil — verifică Modul 6 în AI &amp; RAG.</p>';
            panel.hidden = false;
            return;
        }
        const highlights = Array.isArray(structured.highlights) ? structured.highlights : [];
        const recs = Array.isArray(structured.recommendations) ? structured.recommendations : [];
        const stats = structured.stats && typeof structured.stats === 'object' ? structured.stats : {};
        const statsHtml = Object.keys(stats).length
            ? '<p><strong>Statistici:</strong> ' + esc(Object.entries(stats).map(([k, v]) => k + ': ' + v).join(' · ')) + '</p>'
            : '';
        const hlHtml = highlights.length
            ? '<ul>' + highlights.map(h => '<li>' + esc(typeof h === 'string' ? h : (h.text || h.message || JSON.stringify(h))) + '</li>').join('') + '</ul>'
            : '';
        const recHtml = recs.length
            ? '<p><strong>Recomandări:</strong></p><ul>' + recs.map(r => '<li>' + esc(typeof r === 'string' ? r : (r.text || r.message || JSON.stringify(r))) + '</li>').join('') + '</ul>'
            : '';
        body.innerHTML = '<p>' + esc(structured.summary_ro) + '</p>' + statsHtml + hlHtml + recHtml;
        panel.hidden = false;
    }

    async function refreshCronSummary() {
        if (!state.modules.mod_06?.enabled) return;
        if (state.cronSummaryBusy) return;
        state.cronSummaryBusy = true;
        renderCronSummary(null, true);
        try {
            const json = await aiPost('generate_log_summary', { log_lines: 100 }, 120000);
            const structured = json.data?.structured || json.data?.governance?.structured || null;
            renderCronSummary(structured, false);
        } catch (e) {
            renderCronSummary(null, false);
            toast('Rezumat AI eșuat: ' + (e.message || e), 'warn');
        } finally {
            state.cronSummaryBusy = false;
        }
    }

    function cardInput(card) {
        return {
            name: card.title || card.name || '',
            sku: card.sku || '',
            brand: card.brand || '',
            supplier: card.supplier || '',
            oem: card.oem || card.scrapedImageOem || '',
            raw_name: card.title || card.name || '',
            raw_description: card.description || '',
        };
    }

    function overlayHtml(index, card) {
        const status = card.matchStatus || '';
        const showMatch = state.modules.mod_01?.enabled && (status === 'probable' || status === 'no_match');
        const showNorm = state.modules.mod_02?.enabled;
        if (!showMatch && !showNorm) return '';
        const pending = state.pending.get(index);
        let inner = '';
        if (pending?.loading) {
            inner = '<p class="hint">Se solicită sugestia AI…</p>';
        } else if (pending?.type === 'match' && pending.data) {
            const cands = pending.data.candidates || [];
            inner = '<div class="ai-import-candidates">' + cands.slice(0, 5).map((c, i) =>
                '<label class="ai-import-candidate"><span><input type="radio" name="aiCand' + index + '" value="' + i + '" ' + (i === 0 ? 'checked' : '') + '> '
                + '<strong>' + esc(c.label || '—') + '</strong>'
                + (c.sku ? ' · ' + esc(c.sku) : '')
                + '</span><span class="score">' + Math.round((c.score || 0) * 100) + '% · ' + esc(c.verdict || '') + '</span></label>'
            ).join('') + '</div>';
            inner += '<div class="ai-import-actions">'
                + '<button type="button" class="btn-ai btn-ai-green" data-ai-confirm="' + index + '">Confirmă</button>'
                + '<button type="button" class="btn-ai btn-ai-gray" data-ai-reject="' + index + '">Respinge</button>'
                + '</div>';
        } else if (pending?.type === 'normalize' && pending.data?.after) {
            const after = pending.data.after;
            inner = '<div class="ai-normalize-preview"><strong>Preview normalizare</strong>'
                + '<p>' + esc(after.nume_normalizat || '—') + '</p>'
                + (after.cod_oe ? '<p>OEM: ' + esc(after.cod_oe) + '</p>' : '')
                + '<pre>' + esc(JSON.stringify(after, null, 2)) + '</pre>'
                + '<div class="ai-import-actions">'
                + '<button type="button" class="btn-ai btn-ai-green" data-ai-confirm-norm="' + index + '">Confirmă</button>'
                + '<button type="button" class="btn-ai btn-ai-gray" data-ai-reject-norm="' + index + '">Respinge</button>'
                + '</div></div>';
        }
        const btns = [];
        if (showMatch && !pending) btns.push('<button type="button" class="btn-ai btn-ai-purple" data-ai-match="' + index + '">Matching semantic</button>');
        if (showNorm && !pending) btns.push('<button type="button" class="btn-ai btn-ai-purple" data-ai-norm="' + index + '">Normalizare descriere</button>');
        return '<div class="ai-import-overlay" data-ai-overlay="' + index + '">'
            + '<div class="ai-import-overlay-head"><span>AI — propunere (fără auto-apply)</span></div>'
            + (btns.length ? '<div class="ai-import-actions">' + btns.join('') + '</div>' : '')
            + inner
            + '</div>';
    }

    function attachOverlays() {
        const grid = document.getElementById('cardsGrid');
        if (!grid || !hooks.getCards) return;
        const cards = hooks.getCards();
        grid.querySelectorAll('.product-card').forEach(article => {
            const index = parseInt(article.dataset.cardIndex, 10);
            if (Number.isNaN(index) || !cards[index]) return;
            const existing = article.querySelector('[data-ai-overlay]');
            if (existing) existing.remove();
            const html = overlayHtml(index, cards[index]);
            if (!html) return;
            const actions = article.querySelector('.card-actions');
            if (actions) actions.insertAdjacentHTML('afterend', html);
            else article.querySelector('.product-card-body')?.insertAdjacentHTML('beforeend', html);
        });
        bindOverlayEvents();
    }

    async function requestMatch(index) {
        const cards = hooks.getCards();
        const card = cards[index];
        if (!card) return;
        state.pending.set(index, { type: 'match', loading: true });
        attachOverlays();
        try {
            const json = await aiPost('semantic_match_suggest', { input: cardInput(card), auto_index: true }, 120000);
            state.pending.set(index, { type: 'match', loading: false, data: json.data, logId: json.data?.log_id });
            attachOverlays();
        } catch (e) {
            state.pending.delete(index);
            attachOverlays();
            toast('Matching semantic: ' + (e.message || e), 'warn');
        }
    }

    async function requestNormalize(index) {
        const cards = hooks.getCards();
        const card = cards[index];
        if (!card) return;
        state.pending.set(index, { type: 'normalize', loading: true });
        attachOverlays();
        try {
            const json = await aiPost('normalize_description', { input: cardInput(card) }, 120000);
            state.pending.set(index, { type: 'normalize', loading: false, data: json.data, logId: json.data?.log_id });
            attachOverlays();
        } catch (e) {
            state.pending.delete(index);
            attachOverlays();
            toast('Normalizare: ' + (e.message || e), 'warn');
        }
    }

    async function recordAction(logId, humanAction) {
        if (!logId) return;
        try {
            await aiPost('record_human_action', { log_id: logId, human_action: humanAction }, 15000);
        } catch (_) { /* non-blocking */ }
    }

    function confirmMatch(index) {
        const pending = state.pending.get(index);
        if (!pending?.data) return;
        const cards = hooks.getCards();
        const card = cards[index];
        if (!card) return;
        const overlay = document.querySelector('[data-ai-overlay="' + index + '"]');
        const selected = overlay?.querySelector('input[name="aiCand' + index + '"]:checked');
        const idx = selected ? parseInt(selected.value, 10) : 0;
        const cand = (pending.data.candidates || [])[idx];
        if (cand) {
            card.aiConfirmedMatch = cand;
            card.matchStatus = cand.verdict === 'exact' ? 'exact' : 'probable';
            card.aiMatchLabel = cand.label;
            card.matchedProductId = cand.source_id;
            toast('Match AI confirmat — se aplică la staging manual.', 'ok');
        }
        void recordAction(pending.logId, 'confirmed');
        state.pending.delete(index);
        if (typeof hooks.renderCards === 'function') hooks.renderCards();
    }

    function rejectPending(index, norm) {
        const pending = state.pending.get(index);
        void recordAction(pending?.logId, 'rejected');
        state.pending.delete(index);
        toast(norm ? 'Normalizare respinsă.' : 'Sugestie respinsă.', 'info');
        attachOverlays();
    }

    function confirmNormalize(index) {
        const pending = state.pending.get(index);
        const after = pending?.data?.after;
        const cards = hooks.getCards();
        const card = cards[index];
        if (after && card) {
            if (after.nume_normalizat) card.title = after.nume_normalizat;
            if (after.cod_oe) card.oem = after.cod_oe;
            card.aiNormalized = after;
            toast('Descriere normalizată confirmată (local, până la staging).', 'ok');
        }
        void recordAction(pending?.logId, 'confirmed');
        state.pending.delete(index);
        if (typeof hooks.renderCards === 'function') hooks.renderCards();
    }

    function bindOverlayEvents() {
        document.querySelectorAll('[data-ai-match]').forEach(btn => {
            btn.onclick = () => requestMatch(parseInt(btn.dataset.aiMatch, 10));
        });
        document.querySelectorAll('[data-ai-norm]').forEach(btn => {
            btn.onclick = () => requestNormalize(parseInt(btn.dataset.aiNorm, 10));
        });
        document.querySelectorAll('[data-ai-confirm]').forEach(btn => {
            btn.onclick = () => confirmMatch(parseInt(btn.dataset.aiConfirm, 10));
        });
        document.querySelectorAll('[data-ai-reject]').forEach(btn => {
            btn.onclick = () => rejectPending(parseInt(btn.dataset.aiReject, 10), false);
        });
        document.querySelectorAll('[data-ai-confirm-norm]').forEach(btn => {
            btn.onclick = () => confirmNormalize(parseInt(btn.dataset.aiConfirmNorm, 10));
        });
        document.querySelectorAll('[data-ai-reject-norm]').forEach(btn => {
            btn.onclick = () => rejectPending(parseInt(btn.dataset.aiRejectNorm, 10), true);
        });
    }

    function patchRenderCards(original) {
        return function patchedRenderCards() {
            original.apply(this, arguments);
            attachOverlays();
        };
    }

    function onCronFinished() {
        if (state.modules.mod_06?.enabled) void refreshCronSummary();
    }

    function init(options) {
        hooks = options || {};
        state.apiUrl = String(options.apiUrl || '').trim();
        void loadModuleStatus().then(() => {
            const refreshBtn = document.getElementById('aiCronSummaryRefresh');
            if (refreshBtn) refreshBtn.addEventListener('click', () => void refreshCronSummary());
        });
    }

    global.BesoiuImportAi = {
        init,
        patchRenderCards,
        onCronFinished,
        attachOverlays,
        refreshCronSummary,
    };
})(typeof window !== 'undefined' ? window : globalThis);
