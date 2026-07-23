/**
 * Import Pro — AI pipeline automat (Modul 1 semantic, Modul 2 normalizare, Modul 6 rezumat cron).
 * Butoanele manuale sunt eliminate: acțiunile rulează în fundal după randarea cardurilor.
 */
(function (global) {
    'use strict';

    const state = {
        apiUrl: '',
        modules: { mod_01: null, mod_02: null, mod_06: null },
        pending: new Map(),
        autoDone: new Set(),
        autoQueue: [],
        autoRunning: false,
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

    function cardAutoKey(card, index) {
        return String(card.sku || '') + '|' + String(card.sourceFile || '') + '|' + index;
    }

    /** Overlay discret — fără butoane manuale. */
    function overlayHtml(index, card) {
        const pending = state.pending.get(index);
        if (!pending) return '';
        let inner = '';
        if (pending.loading) {
            inner = '<p class="hint">AI rulează automat în fundal…</p>';
        } else if (pending.applied) {
            inner = '<p class="hint">' + esc(pending.message || 'AI aplicat automat.') + '</p>';
        } else if (pending.error) {
            inner = '<p class="hint">AI: ' + esc(pending.error) + '</p>';
        } else {
            return '';
        }
        return '<div class="ai-import-overlay ai-import-overlay--auto" data-ai-overlay="' + index + '">'
            + '<div class="ai-import-overlay-head"><span>AI — automat</span></div>'
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
    }

    async function recordAction(logId, humanAction) {
        if (!logId) return;
        try {
            await aiPost('record_human_action', { log_id: logId, human_action: humanAction }, 15000);
        } catch (_) { /* non-blocking */ }
    }

    function applyMatchResult(card, data) {
        const cands = Array.isArray(data?.candidates) ? data.candidates : [];
        const cand = cands.find(c => c && (c.verdict === 'exact' || c.verdict === 'probable')) || cands[0];
        if (!cand || cand.verdict === 'no_match') {
            return false;
        }
        card.aiConfirmedMatch = cand;
        card.aiAutoApplied = true;
        card.matchStatus = cand.verdict === 'exact' ? 'exact' : 'probable';
        card.aiMatchLabel = cand.label;
        card.matchedProductId = cand.source_id;
        return true;
    }

    function applyNormalizeResult(card, data) {
        const after = data?.after;
        if (!after || typeof after !== 'object') return false;
        const confidence = Number(after.confidence);
        const verdict = String(after.verdict || '');
        if (verdict === 'review' && !(confidence >= 0.85)) {
            return false;
        }
        if (after.nume_normalizat) card.title = after.nume_normalizat;
        if (after.cod_oe) card.oem = after.cod_oe;
        card.aiNormalized = after;
        card.aiAutoApplied = true;
        return true;
    }

    async function processCardAuto(index) {
        const cards = hooks.getCards ? hooks.getCards() : [];
        const card = cards[index];
        if (!card) return;

        const status = String(card.matchStatus || '');
        const wantMatch = !!(state.modules.mod_01?.enabled) && (status === 'probable' || status === 'no_match');
        const wantNorm = !!(state.modules.mod_02?.enabled) && !card.aiNormalized;
        if (!wantMatch && !wantNorm) return;

        state.pending.set(index, { loading: true });
        attachOverlays();

        const messages = [];
        try {
            if (wantMatch) {
                const json = await aiPost('semantic_match_suggest', { input: cardInput(card), auto_index: true }, 120000);
                if (applyMatchResult(card, json.data)) {
                    messages.push('match semantic');
                    void recordAction(json.data?.log_id, 'auto_applied');
                } else {
                    void recordAction(json.data?.log_id, 'auto_skipped');
                }
            }
            if (wantNorm) {
                const json = await aiPost('normalize_description', { input: cardInput(card) }, 120000);
                if (applyNormalizeResult(card, json.data)) {
                    messages.push('normalizare');
                    void recordAction(json.data?.log_id, 'auto_applied');
                } else {
                    void recordAction(json.data?.log_id, 'auto_skipped');
                }
            }
            state.pending.set(index, {
                loading: false,
                applied: messages.length > 0,
                message: messages.length ? ('Aplicat: ' + messages.join(' + ')) : 'Fără modificări AI',
            });
            if (messages.length && typeof hooks.renderCards === 'function') {
                hooks.renderCards();
            } else {
                attachOverlays();
            }
        } catch (e) {
            state.pending.set(index, { loading: false, error: String(e.message || e) });
            attachOverlays();
        }
    }

    function enqueueVisibleCards() {
        if (!hooks.getCards) return;
        const grid = document.getElementById('cardsGrid');
        if (!grid) return;
        const cards = hooks.getCards();
        grid.querySelectorAll('.product-card').forEach(article => {
            const index = parseInt(article.dataset.cardIndex, 10);
            if (Number.isNaN(index) || !cards[index]) return;
            const key = cardAutoKey(cards[index], index);
            if (state.autoDone.has(key)) return;
            state.autoDone.add(key);
            state.autoQueue.push(index);
        });
        void drainAutoQueue();
    }

    async function drainAutoQueue() {
        if (state.autoRunning) return;
        state.autoRunning = true;
        try {
            while (state.autoQueue.length) {
                const index = state.autoQueue.shift();
                await processCardAuto(index);
            }
        } finally {
            state.autoRunning = false;
        }
    }

    function patchRenderCards(original) {
        return function patchedRenderCards() {
            original.apply(this, arguments);
            attachOverlays();
            // Pipeline automat după randare (fără click).
            setTimeout(() => enqueueVisibleCards(), 50);
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
            enqueueVisibleCards();
        });
    }

    global.BesoiuImportAi = {
        init,
        patchRenderCards,
        onCronFinished,
        attachOverlays,
        refreshCronSummary,
        enqueueVisibleCards,
    };
})(typeof window !== 'undefined' ? window : globalThis);
