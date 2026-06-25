<script>
(function ScraperApp() {
    const API = '/admin/api/scraper_endpoint.php';
    const toast = document.getElementById('scraper-toast');
    let cards = [];
    let currentSourceId = null;
    let currentConfig = null;
    let currentMeta = null;

    const views = {
        cards: document.getElementById('sc-view-cards'),
        detail: document.getElementById('sc-view-detail'),
        vitrina: document.getElementById('sc-view-vitrina'),
        logs: document.getElementById('sc-view-logs'),
        pipeline: document.getElementById('sc-view-pipeline'),
    };

    let integrationConfig = null;
    let availableSources = [];
    let extractionGoalCatalog = [];
    let pickedGoalType = null;

    function esc(s) {
        const d = document.createElement('div');
        d.textContent = s ?? '';
        return d.innerHTML;
    }

    function showToast(msg, ok) {
        if (!toast) return;
        toast.textContent = msg;
        toast.className = 'fixed right-5 top-5 z-50 rounded-md border px-4 py-3 text-sm shadow ' +
            (ok ? 'border-success/30 bg-success/10' : 'border-danger/30 bg-danger/10');
        toast.classList.remove('hidden');
        setTimeout(() => toast.classList.add('hidden'), 5000);
    }

    function showView(name) {
        Object.entries(views).forEach(([k, el]) => {
            if (el) el.classList.toggle('hidden', k !== name);
        });
    }

    async function apiGet(view, params) {
        const q = new URLSearchParams({ view, ...(params || {}) });
        const r = await fetch(API + '?' + q, { credentials: 'include' });
        const j = await r.json();
        if (!j.success) throw new Error(j.message || 'Eroare API');
        return j.data;
    }

    async function apiPost(action, body, timeoutMs = 180000) {
        const ctrl = new AbortController();
        const timer = setTimeout(() => ctrl.abort(), timeoutMs);
        const timeoutSec = Math.round(timeoutMs / 1000);
        try {
            const r = await fetch(API, {
                method: 'POST',
                credentials: 'include',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action, ...(body || {}) }),
                signal: ctrl.signal,
            });
            const raw = await r.text();
            let j;
            try {
                j = JSON.parse(raw);
            } catch {
                const snippet = (raw || '').replace(/\s+/g, ' ').trim().slice(0, 180);
                throw new Error(
                    r.ok
                        ? ('Răspuns invalid de la server.' + (snippet ? ' ' + snippet : ''))
                        : ('Eroare server HTTP ' + r.status + (snippet ? ': ' + snippet : ''))
                );
            }
            if (!j.success) throw new Error(j.message || 'Eroare');
            return j;
            } catch (err) {
            if (err && err.name === 'AbortError') {
                throw new Error('Timeout browser (>' + timeoutSec + 's) — serverul încă procesează; reîncearcă sau treci la planul următor.');
            }
            throw err;
        } finally {
            clearTimeout(timer);
        }
    }

  function statusBadge(card) {
    if (!card.env_ok) return '<span class="sc-badge sc-badge--warn">Token lipsă</span>';
    if (card.stub) return '<span class="sc-badge sc-badge--warn">De configurat</span>';
    const lt = card.last_test;
    if (lt?.status === 'ok') return '<span class="sc-badge sc-badge--ok">Test OK</span>';
    if (lt?.status === 'partial') return '<span class="sc-badge sc-badge--warn">Test parțial</span>';
    if (lt?.status === 'fail') return '<span class="sc-badge sc-badge--warn">Test eșuat</span>';
    if (!card.enabled) return '<span class="sc-badge sc-badge--off">Inactivă</span>';
    return '<span class="sc-badge sc-badge--ok">Activă</span>';
  }

    function renderCards(list) {
        cards = list || [];
        const grid = document.getElementById('sc-cards-grid');
        if (!grid) return;
        if (!cards.length) {
            grid.innerHTML = '<p class="opacity-60">Nicio sursă.</p>';
            return;
        }
        grid.innerHTML = cards.map(c => `
            <article class="sc-source-card ${c.stub ? 'is-stub' : ''}" data-source-id="${esc(c.id)}">
                <button type="button" class="sc-card-delete sc-delete-card" data-id="${esc(c.id)}" title="Șterge sursa">×</button>
                <div class="sc-card-head">
                    <div class="sc-card-avatar" style="background:${esc(c.color)}">${esc(c.icon)}</div>
                    <div>
                        <h3 class="sc-card-name">${esc(c.label)}</h3>
                        <div class="sc-card-domain">${esc(c.domain)}</div>
                    </div>
                </div>
                <p class="sc-card-desc">${esc(c.description)}</p>
                <div class="sc-card-badges">
                    ${statusBadge(c)}
                    <span class="sc-badge">${c.steps_enabled}/${c.steps_count} pași</span>
                    ${(c.roles || []).slice(0, 2).map(r => `<span class="sc-badge">${esc(r)}</span>`).join('')}
                </div>
                ${c.last_test ? `<p class="px-4 pb-2 text-xs opacity-60">Ultimul test: ${esc(c.last_test.query || '')} · ${c.last_test.items || 0} rez. · scor ${c.last_test.score || 0}</p>` : ''}
                <div class="sc-card-foot">
                    <button type="button" class="sc-btn-primary sc-open-config" data-id="${esc(c.id)}">Configurează</button>
                    <button type="button" class="sc-btn-outline sc-quick-test" data-id="${esc(c.id)}">Test rapid</button>
                </div>
            </article>
        `).join('');

        grid.querySelectorAll('.sc-open-config').forEach(btn => {
            btn.addEventListener('click', () => openSource(btn.dataset.id));
        });
        grid.querySelectorAll('.sc-quick-test').forEach(btn => {
            btn.addEventListener('click', () => quickTest(btn.dataset.id));
        });
        grid.querySelectorAll('.sc-delete-card').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                deleteSource(btn.dataset.id);
            });
        });
    }

    async function deleteSource(id) {
        const card = cards.find(c => c.id === id);
        const name = card?.label || id;
        const extra = card?.builtin ? '\n\n(Sursă preset — o poți readăuga din «Readaugă preseturi șterse».)' : '';
        if (!confirm('Ștergi sursa «' + name + '» și toată configurarea ei?' + extra)) return;
        try {
            await apiPost('source_delete', { source_id: id });
            if (currentSourceId === id) {
                currentSourceId = null;
                showView('cards');
            }
            showToast('Sursă ștearsă', true);
            await loadCards();
        } catch (e) {
            showToast(e.message, false);
        }
    }

    function openCreateModal() {
        document.getElementById('sc-modal-create')?.classList.remove('hidden');
    }

    function closeCreateModal() {
        document.getElementById('sc-modal-create')?.classList.add('hidden');
        document.getElementById('sc-create-form')?.reset();
    }

    async function loadCards() {
        const data = await apiGet('sources');
        renderCards(data.cards || []);
        const stats = await apiGet('stats').catch(() => null);
        const badge = document.getElementById('scraper-token-badge');
        if (badge && stats) {
            badge.textContent = stats.has_token ? 'scrape.do: activ' : 'SCRAPE_DO_TOKEN lipsă';
            badge.className = 'sc-token-badge ' + (stats.has_token ? 'text-success' : 'text-danger');
        }
    }

    async function openSource(id) {
        currentSourceId = id;
        const data = await apiGet('source', { source_id: id });
        currentConfig = data.config;
        currentMeta = data.meta || {};

        document.getElementById('sc-detail-title').textContent = currentConfig.label || id;
        document.getElementById('sc-detail-domain').textContent = currentMeta.domain || id;
        const av = document.getElementById('sc-detail-avatar');
        if (av) {
            av.textContent = currentMeta.icon || id.slice(0, 2).toUpperCase();
            av.style.background = currentMeta.color || '#64748b';
        }
        document.getElementById('sc-detail-enabled').checked = !!currentConfig.enabled;
        document.getElementById('sc-detail-notes').value = currentConfig.notes || '';
        document.getElementById('sc-test-query').value = currentConfig.test?.query || '';
        document.getElementById('sc-test-limit').value = currentConfig.test?.limit || 5;
        document.getElementById('sc-fetch-timeout').value = currentConfig.fetch?.timeout_sec || 90;
        document.getElementById('sc-fetch-super').checked = !!currentConfig.fetch?.super;
        document.getElementById('sc-fetch-render').checked = !!currentConfig.fetch?.render;
        // Autodoc / site-uri JS-heavy: implicit render + super la test dacă nu e setat
        if (id === 'autodoc' || (currentMeta.domain || '').includes('autodoc')) {
            if (!currentConfig.fetch?.super) document.getElementById('sc-fetch-super').checked = true;
            if (!currentConfig.fetch?.render) document.getElementById('sc-fetch-render').checked = true;
        }

        ScraperStepBuilder.setSteps(currentConfig.steps || []);
        ScraperStepBuilder.render();
        maybeShowAutodocPresetHint();
        renderOutputFields(currentConfig.output?.fields_needed || []);
        renderSourceIntegration(currentConfig.integration || {});
        const ai = currentConfig.ai_agent || {};
        const goalsEl = document.getElementById('sc-ai-goals');
        if (goalsEl) goalsEl.value = ai.goals || 'Extrage fiecare produs: titlu, preț RON, imagine, URL, cod articol.';
        const aiEn = document.getElementById('sc-ai-enabled');
        if (aiEn) aiEn.checked = ai.enabled !== false;
        const aiAuto = document.getElementById('sc-ai-auto-fail');
        if (aiAuto) aiAuto.checked = ai.auto_on_fail !== false;
        showView('detail');
        setScTab('logica');
    }

    function renderSourceIntegration(integration) {
        const goals = integration.extraction_goals || [];
        const el = document.getElementById('sc-extraction-goals');
        if (el) {
            el.innerHTML = goals.length ? goals.map((g, i) => `
                <div class="sc-plan-row" data-goal-idx="${i}">
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" class="goal-enabled" ${g.enabled !== false ? 'checked' : ''}>
                        <strong>${esc(g.label || g.type)}</strong>
                        <span class="opacity-50 text-xs">(${esc(g.type)})</span>
                    </label>
                    <input type="text" class="goal-selector box h-8 flex-1 rounded-md border px-2 text-xs" value="${esc(g.selector || '')}" placeholder="Selector bloc text">
                    <button type="button" class="sc-el-del goal-del" data-idx="${i}">Șterge</button>
                </div>
            `).join('') : '<p class="text-xs opacity-50">Niciun obiectiv — adaugă OEM, descriere, validare TecDoc…</p>';
            el.querySelectorAll('.goal-del').forEach(btn => {
                btn.addEventListener('click', () => {
                    const idx = parseInt(btn.dataset.idx, 10);
                    const intg = currentConfig.integration || {};
                    intg.extraction_goals = (intg.extraction_goals || []).filter((_, j) => j !== idx);
                    currentConfig.integration = intg;
                    renderSourceIntegration(intg);
                });
            });
        }
        document.getElementById('sc-src-ai-enabled').checked = !!integration.image_ai?.enabled;
        document.getElementById('sc-src-ai-prompt').value = integration.image_ai?.prompt_extra || '';
        document.getElementById('sc-rapidapi-validate').checked = !!integration.rapidapi?.validate_on_import;
        document.getElementById('sc-use-in-pipeline').checked = integration.use_in_image_pipeline !== false;
    }

    function collectSourceIntegration() {
        const goals = [];
        document.querySelectorAll('#sc-extraction-goals [data-goal-idx]').forEach(row => {
            const idx = parseInt(row.dataset.goalIdx, 10);
            const orig = (currentConfig.integration?.extraction_goals || [])[idx] || {};
            goals.push({
                ...orig,
                enabled: !!row.querySelector('.goal-enabled')?.checked,
                selector: row.querySelector('.goal-selector')?.value || '',
            });
        });
        return {
            use_in_image_pipeline: !!document.getElementById('sc-use-in-pipeline')?.checked,
            extraction_goals: goals,
            image_ai: {
                enabled: !!document.getElementById('sc-src-ai-enabled')?.checked,
                prompt_extra: document.getElementById('sc-src-ai-prompt')?.value || '',
            },
            rapidapi: {
                validate_on_import: !!document.getElementById('sc-rapidapi-validate')?.checked,
                fields: ['oem', 'image', 'description'],
            },
        };
    }

    async function loadPipelineView() {
        const data = await apiGet('integration');
        integrationConfig = data.config || {};
        availableSources = data.available_sources || [];
        extractionGoalCatalog = data.extraction_goal_catalog || [];
        renderImagePlans(integrationConfig.image_plans || []);
        const ai = integrationConfig.image_ai || {};
        document.getElementById('sc-global-ai-enabled').checked = ai.enabled !== false;
        document.getElementById('sc-ai-white-bg').checked = ai.accept_white_background !== false;
        document.getElementById('sc-ai-product-match').checked = ai.accept_product_match !== false;
        document.getElementById('sc-ai-on-import').checked = !!ai.on_import_review;
        document.getElementById('sc-ai-on-cron').checked = !!ai.on_import_cron;
        document.getElementById('sc-global-ai-prompt').value = ai.prompt_extra || '';
        document.getElementById('sc-ai-min-score').value = ai.min_score_keep ?? 70;
        const autoRetry = document.getElementById('sc-ai-auto-pipeline-retry');
        if (autoRetry) autoRetry.checked = ai.auto_retry_on_mismatch !== false;
        document.getElementById('sc-sync-env').checked = !!integrationConfig.sync_to_env;
        showPipelineQuotaWarn(data.pipeline_context || {});
        showView('pipeline');
    }

    function renderImagePlans(plans) {
        const el = document.getElementById('sc-image-plans');
        if (!el) return;
        const opts = availableSources.map(s => `<option value="${esc(s)}">${esc(s)}</option>`).join('');
        el.innerHTML = (plans.length ? plans : [{ tier: 1, label: 'Plan principal', source_id: availableSources[0] || '', enabled: true }]).map((p, i) => `
            <div class="sc-plan-row" data-plan-idx="${i}">
                <span class="sc-plan-tier">Plan ${i + 1}</span>
                <input type="text" class="plan-label box h-9 rounded-md border px-2 text-sm" value="${esc(p.label || 'Plan ' + (i + 1))}" placeholder="Denumire plan">
                <select class="plan-source box h-9 rounded-md border px-2 text-sm">${opts}</select>
                <label class="flex items-center gap-1 text-xs"><input type="checkbox" class="plan-enabled" ${p.enabled !== false ? 'checked' : ''}> Activ</label>
                <button type="button" class="sc-step-move plan-up" data-idx="${i}">↑</button>
                <button type="button" class="sc-step-move plan-down" data-idx="${i}">↓</button>
                <button type="button" class="sc-el-del plan-del" data-idx="${i}">×</button>
            </div>
        `).join('');
        el.querySelectorAll('.plan-source').forEach((sel, i) => {
            if (plans[i]?.source_id) sel.value = plans[i].source_id;
        });
        el.querySelectorAll('.plan-up').forEach(btn => btn.addEventListener('click', () => movePlan(parseInt(btn.dataset.idx, 10), -1)));
        el.querySelectorAll('.plan-down').forEach(btn => btn.addEventListener('click', () => movePlan(parseInt(btn.dataset.idx, 10), 1)));
        el.querySelectorAll('.plan-del').forEach(btn => btn.addEventListener('click', () => {
            integrationConfig.image_plans = collectImagePlans().filter((_, j) => j !== parseInt(btn.dataset.idx, 10));
            renderImagePlans(integrationConfig.image_plans);
        }));
    }

    function movePlan(idx, dir) {
        const plans = collectImagePlans();
        const j = idx + dir;
        if (j < 0 || j >= plans.length) return;
        [plans[idx], plans[j]] = [plans[j], plans[idx]];
        integrationConfig.image_plans = plans;
        renderImagePlans(plans);
    }

    function dedupeImagePlans(plans) {
        const seen = new Set();
        const out = [];
        (plans || []).forEach((p, i) => {
            const id = (p.source_id || '').trim();
            if (!id || seen.has(id)) return;
            seen.add(id);
            out.push({ ...p, tier: out.length + 1 });
        });
        return out;
    }

    function collectImagePlans() {
        const plans = [];
        document.querySelectorAll('#sc-image-plans [data-plan-idx]').forEach((row, i) => {
            plans.push({
                tier: i + 1,
                label: row.querySelector('.plan-label')?.value || 'Plan ' + (i + 1),
                source_id: row.querySelector('.plan-source')?.value || '',
                enabled: !!row.querySelector('.plan-enabled')?.checked,
                roles: ['image'],
            });
        });
        return plans;
    }

    function collectIntegrationConfig() {
        return {
            image_plans: collectImagePlans(),
            image_ai: {
                enabled: !!document.getElementById('sc-global-ai-enabled')?.checked,
                accept_white_background: !!document.getElementById('sc-ai-white-bg')?.checked,
                accept_product_match: !!document.getElementById('sc-ai-product-match')?.checked,
                on_import_review: !!document.getElementById('sc-ai-on-import')?.checked,
                on_import_cron: !!document.getElementById('sc-ai-on-cron')?.checked,
                prompt_extra: document.getElementById('sc-global-ai-prompt')?.value || '',
                min_score_keep: parseInt(document.getElementById('sc-ai-min-score')?.value || '70', 10),
                auto_retry_on_mismatch: document.getElementById('sc-ai-auto-pipeline-retry')?.checked !== false,
                verdicts_retry: ['mismatch', 'error', 'no_image'],
            },
            sync_to_env: !!document.getElementById('sc-sync-env')?.checked,
        };
    }

    function openAddGoalModal() {
        pickedGoalType = null;
        const list = document.getElementById('sc-goal-type-list');
        const modal = document.getElementById('sc-modal-add-goal');
        if (!list || !modal) return;
        list.innerHTML = extractionGoalCatalog.map(g => `
            <button type="button" class="sc-type-pick" data-goal-type="${esc(g.type)}">
                ${esc(g.label)}
                <small>${esc(g.hint || '')}</small>
            </button>
        `).join('');
        list.querySelectorAll('.sc-type-pick').forEach(btn => {
            btn.addEventListener('click', () => {
                pickedGoalType = btn.dataset.goalType;
                list.querySelectorAll('.sc-type-pick').forEach(b => b.classList.toggle('is-selected', b === btn));
                const cat = extractionGoalCatalog.find(c => c.type === pickedGoalType);
                document.getElementById('sc-goal-label').value = cat?.label || pickedGoalType;
                document.getElementById('sc-goal-rapidapi').checked = pickedGoalType === 'rapidapi_validate';
            });
        });
        document.getElementById('sc-goal-selector').value = '';
        modal.classList.remove('hidden');
    }

    function confirmAddGoal() {
        if (!pickedGoalType) {
            alert('Alege tipul obiectivului.');
            return;
        }
        const intg = currentConfig.integration || { extraction_goals: [] };
        intg.extraction_goals = intg.extraction_goals || [];
        intg.extraction_goals.push({
            id: 'goal_' + Math.random().toString(36).slice(2, 9),
            type: pickedGoalType,
            label: document.getElementById('sc-goal-label')?.value || pickedGoalType,
            selector: document.getElementById('sc-goal-selector')?.value || '',
            enabled: true,
            rapidapi_validate: !!document.getElementById('sc-goal-rapidapi')?.checked,
        });
        currentConfig.integration = intg;
        renderSourceIntegration(intg);
        document.getElementById('sc-modal-add-goal')?.classList.add('hidden');
    }

    function renderOutputFields(needed) {
        const all = ['title', 'image', 'url', 'price', 'description', 'sku', 'oem'];
        const el = document.getElementById('sc-output-fields');
        if (!el) return;
        el.innerHTML = all.map(f => `
            <label class="sc-field-check">
                <input type="checkbox" class="output-field" value="${f}" ${needed.includes(f) ? 'checked' : ''}>
                ${f}
            </label>
        `).join('');
    }

    function collectConfigFromForm() {
        ScraperStepBuilder.syncFromDom();
        const cfg = JSON.parse(JSON.stringify(currentConfig || {}));
        cfg.enabled = !!document.getElementById('sc-detail-enabled')?.checked;
        cfg.notes = document.getElementById('sc-detail-notes')?.value || '';
        cfg.test = cfg.test || {};
        cfg.test.query = document.getElementById('sc-test-query')?.value || '';
        cfg.test.limit = parseInt(document.getElementById('sc-test-limit')?.value || '5', 10);
        cfg.fetch = cfg.fetch || {};
        cfg.fetch.timeout_sec = parseInt(document.getElementById('sc-fetch-timeout')?.value || '90', 10);
        cfg.fetch.super = !!document.getElementById('sc-fetch-super')?.checked;
        cfg.fetch.render = !!document.getElementById('sc-fetch-render')?.checked;
        cfg.steps = ScraperStepBuilder.getSteps();
        cfg.output = cfg.output || {};
        cfg.output.fields_needed = [...document.querySelectorAll('.output-field:checked')].map(cb => cb.value);
        if (document.getElementById('sc-extraction-goals')) {
            cfg.integration = collectSourceIntegration();
        }
        cfg.ai_agent = {
            enabled: !!document.getElementById('sc-ai-enabled')?.checked,
            auto_on_fail: !!document.getElementById('sc-ai-auto-fail')?.checked,
            goals: document.getElementById('sc-ai-goals')?.value?.trim() || '',
        };
        return cfg;
    }

    async function saveConfig() {
        if (!currentSourceId) return;
        const cfg = collectConfigFromForm();
        const j = await apiPost('source_save', { source_id: currentSourceId, config: cfg });
        currentConfig = j.data;
        showToast(j.message || 'Salvat', true);
    }

    function setScTab(name) {
        document.querySelectorAll('.sc-tab').forEach(t => t.classList.toggle('is-active', t.dataset.scTab === name));
        document.querySelectorAll('.sc-tab-panel').forEach(p => p.classList.toggle('is-active', p.dataset.scTabPanel === name));
    }

    let lastRawSaved = '';

    const LIST_PRESETS = {
        autodoc: {
            ignore: 'placeholder, star-fill, 360-icon, brands/thumbs',
            elements: [
                { key: 'block', label: 'Bloc produs', selector: 'div.listing-item__wrap' },
                { key: 'title', label: 'Titlu', selector: 'a.listing-item__name' },
                { key: 'image', label: 'Imagine produs', selector: '.listing-item__image-product img@src' },
                { key: 'url', label: 'URL produs', selector: 'a.listing-item__name@href' },
                { key: 'price', label: 'Preț', selector: '.listing-item__price-new' },
                { key: 'sku', label: 'Cod articol', selector: '.listing-item__article-item' },
            ],
        },
    };

    function uidEl() {
        return 'el_' + Math.random().toString(36).slice(2, 9);
    }

    function applyListPreset(presetKey) {
        const preset = LIST_PRESETS[presetKey];
        if (!preset) return false;
        ScraperStepBuilder.syncFromDom();
        const steps = ScraperStepBuilder.getSteps();
        let step = steps.find(s => s.type === 'extract_list');
        if (!step) {
            showToast('Lipsește Pas 2 — Extrage din listă. Apasă Resetează la implicit.', false);
            return false;
        }
        step.params = step.params || {};
        step.params.ignore = preset.ignore;
        step.params.limit = step.params.limit || 5;
        step.params.elements = preset.elements.map(e => ({
            id: uidEl(),
            key: e.key,
            label: e.label,
            selector: e.selector,
        }));
        step.enabled = true;
        ScraperStepBuilder.setSteps(steps);
        ScraperStepBuilder.render();
        return true;
    }

    function toggleAutodocPresetButtons(show) {
        document.getElementById('sc-apply-autodoc-preset')?.classList.toggle('hidden', !show);
        document.getElementById('sc-apply-preset-from-diag')?.classList.toggle('hidden', !show);
    }

    async function applyAutodocPresetAndSave() {
        if (!applyListPreset('autodoc')) return;
        setScTab('logica');
        showToast('Selectori Autodoc completați în Pas 2.', true);
        await saveConfig();
        toggleAutodocPresetButtons(false);
    }

    function maybeShowAutodocPresetHint() {
        const isAutodoc = currentSourceId === 'autodoc' || (currentMeta?.domain || '').includes('autodoc');
        if (!isAutodoc) {
            toggleAutodocPresetButtons(false);
            return;
        }
        ScraperStepBuilder.syncFromDom();
        const steps = ScraperStepBuilder.getSteps();
        const step = steps.find(s => s.type === 'extract_list');
        const block = (step?.params?.elements || []).find(e => e.key === 'block');
        const empty = !block?.selector?.trim();
        toggleAutodocPresetButtons(empty);
    }


    const FIELD_LABELS = {
        title: 'Titlu',
        image: 'Imagine',
        url: 'URL',
        price: 'Preț',
        sku: 'Cod articol',
        description: 'Descriere',
        oem: 'OEM',
    };

    function buildMatchedFields(items, fieldsNeeded) {
        const first = items[0] || {};
        const fields = fieldsNeeded?.length ? fieldsNeeded : ['title', 'image', 'url', 'price', 'sku'];
        return Object.fromEntries(fields.map(f => {
            const val = first[f] ?? first[f + '_url'] ?? '';
            const s = val ? String(val).trim() : '';
            return [f, { found: s !== '', value: s ? s.slice(0, 140) : '' }];
        }));
    }

    function renderFieldsChecklist(containerId, matched) {
        const el = document.getElementById(containerId);
        if (!el || !matched) return;
        el.innerHTML = Object.entries(matched).map(([k, v]) =>
            `<div class="mb-1">${v.found ? '✅' : '❌'} <strong>${esc(FIELD_LABELS[k] || k)}</strong>: ${esc(v.value || '(lipsă)')}</div>`
        ).join('');
    }

    function normalizeMediaUrl(url, pageUrl) {
        const u = String(url || '').trim();
        if (!u) return '';
        if (/^https?:\/\//i.test(u)) return u;
        if (u.startsWith('//')) return 'https:' + u;
        try {
            const base = pageUrl || (currentMeta?.domain ? 'https://' + currentMeta.domain : '');
            if (base) return new URL(u, base).href;
        } catch (_) { /* ignore */ }
        return u;
    }

    function scraperProxyImageUrl(url) {
        const n = normalizeMediaUrl(url, currentMeta?.domain ? 'https://' + currentMeta.domain : '');
        if (!n) return '';
        try {
            const host = new URL(n).hostname.toLowerCase();
            const needsProxy = ['autodoc', 'akamaized', 'emag.', 'epiesa', 'pieseauto'].some((k) => host.includes(k));
            if (needsProxy) {
                return API + '?view=image_proxy&url=' + encodeURIComponent(n);
            }
        } catch (_) { /* ignore */ }
        return n;
    }

    function renderProductCards(containerId, items, emptyMsg) {
        const el = document.getElementById(containerId);
        if (!el) return;
        const list = items || [];
        const pageUrl = currentMeta?.domain ? 'https://' + currentMeta.domain : '';
        if (!list.length) {
            el.innerHTML = `<p class="text-sm opacity-60 col-span-full">${esc(emptyMsg || 'Niciun produs.')}</p>`;
            return;
        }
        el.innerHTML = list.map(p => {
            const imgRaw = p.image || p.image_url || '';
            const img = scraperProxyImageUrl(imgRaw);
            const title = p.title || p.name || '—';
            const price = p.price || '—';
            const url = normalizeMediaUrl(p.url || p.product_url || '', pageUrl);
            const sku = p.sku || p.code || p.pCode || '';
            const desc = p.description || p.desc || p.oem || '';
            const imgBlock = img
                ? (url
                    ? `<a href="${esc(url)}" target="_blank" rel="noopener"><img src="${esc(img)}" alt="${esc(title)}" loading="lazy" onerror="this.classList.add('sc-item-img-broken')"></a>`
                    : `<img src="${esc(img)}" alt="${esc(title)}" loading="lazy" onerror="this.classList.add('sc-item-img-broken')">`)
                : '<div class="sc-item-noimg">Fără imagine</div>';
            return `
                <article class="sc-item-card">
                    ${imgBlock}
                    <div class="sc-item-body">
                        <div class="sc-item-title">${esc(title)}</div>
                        ${desc ? `<div class="sc-item-desc">${esc(desc)}</div>` : ''}
                        <div class="sc-item-price">${esc(price)}</div>
                        <div class="sc-item-meta">
                            ${sku ? `<div><strong>SKU:</strong> ${esc(sku)}</div>` : ''}
                            ${url ? `<a class="sc-item-link" href="${esc(url)}" target="_blank" rel="noopener">Deschide produs ↗</a>` : ''}
                        </div>
                    </div>
                </article>
            `;
        }).join('');
    }

    function renderDiagnostics(diag, selectorsUsed, rawSaved) {
        const el = document.getElementById('sc-test-diagnostics');
        if (!el || !diag) {
            if (el) el.textContent = 'Fără diagnostic.';
            return;
        }
        const lines = [];
        if (rawSaved) lines.push(`<div class="mb-1"><strong>HTML:</strong> <code>${esc(rawSaved)}</code> (${diag.html_hints?.bytes || '—'} bytes în fișier)</div>`);
        if (selectorsUsed?.block) {
            lines.push(`<div class="mb-1"><strong>Bloc:</strong> <code>${esc(selectorsUsed.block)}</code> → ${diag.blocks_found ?? 0} găsit(e), ${diag.items_valid ?? 0} valid(e)</div>`);
        } else {
            lines.push(`<div class="mb-1 text-danger"><strong>Bloc:</strong> selector gol</div>`);
        }
        if (diag.xpath_block) {
            lines.push(`<div class="mb-1 text-xs opacity-60">XPath bloc: <code>${esc(diag.xpath_block)}</code></div>`);
        }
        if (diag.problem) {
            lines.push(`<div class="mb-2 text-amber-800 bg-amber-50 border border-amber-200 rounded px-2 py-1">${esc(diag.problem)}</div>`);
        }
        const hints = diag.html_hints || {};
        const hintParts = Object.entries(hints)
            .filter(([k, v]) => k !== 'bytes' && v)
            .map(([k, v]) => `${k}: ${v}`);
        if (hintParts.length) {
            lines.push(`<div class="mb-1"><strong>Markeri în HTML:</strong> ${hintParts.map(h => esc(h)).join(' · ')}</div>`);
        }
        if (diag.field_stats) {
            lines.push('<div class="mt-2 font-semibold">Câmpuri (câte blocuri au valoare):</div>');
            lines.push('<ul class="list-disc pl-5 mb-2">');
            Object.entries(diag.field_stats).forEach(([k, st]) => {
                lines.push(`<li><code>${esc(k)}</code> ← <code>${esc(st.selector || '')}</code> — ${st.non_empty ?? 0}/${diag.blocks_found ?? 0}</li>`);
            });
            lines.push('</ul>');
        }
        if (diag.skip_reasons && Object.keys(diag.skip_reasons).length) {
            lines.push(`<div class="mb-1"><strong>Sărite:</strong> ${Object.entries(diag.skip_reasons).map(([r, n]) => esc(r) + ' (' + n + '×)').join(', ')}</div>`);
        }
        if (diag.samples?.length) {
            lines.push('<div class="mt-2 font-semibold">Exemplu produs #1:</div><pre class="sc-pre text-xs">' + esc(JSON.stringify(diag.samples[0].fields, null, 2)) + '</pre>');
        }
        if (diag.suggestions?.length) {
            lines.push('<div class="mt-2"><strong>Sugestii:</strong><ul class="list-disc pl-5">' +
                diag.suggestions.map(s => `<li>${esc(s)}</li>`).join('') + '</ul></div>');
        }
        const needsAutodoc = (currentSourceId === 'autodoc' || (currentMeta?.domain || '').includes('autodoc'))
            && (!selectorsUsed?.block || selectorsUsed.block === '')
            && ((hints.autodoc_listing_wrap || 0) > 0);
        if (needsAutodoc) {
            toggleAutodocPresetButtons(true);
        }
        el.innerHTML = lines.join('') || '—';
    }

    function renderTestResult(data) {
        const traceEl = document.getElementById('sc-test-trace');
        if (traceEl) {
            traceEl.innerHTML = (data.trace || []).map(t => `
                <div class="sc-trace-row">
                    <span class="sc-trace-status ${esc(t.status)}">${esc(t.status)}</span>
                    <div><strong>Pas ${t.order}</strong> — ${esc(t.label)}<br><span class="opacity-70">${esc(t.message)}</span></div>
                </div>
            `).join('') || 'Fără trace.';
        }

        const parseStep = (data.trace || []).find(t => t.type === 'parse_list' || (t.label || '').includes('Scanează'));
        const diag = parseStep?.data?.diagnostics;
        const fetchStep = (data.trace || []).find(t => t.type === 'fetch');
        if (fetchStep?.data?.raw_saved) {
            lastRawSaved = fetchStep.data.raw_saved;
        }
        if (diag) {
            renderDiagnostics(diag, {
                block: diag.block_selector,
                fields: diag.field_stats,
            }, lastRawSaved);
        }

        const itemsEl = document.getElementById('sc-test-items');
        if (itemsEl) {
            renderProductCards('sc-test-items', data.items || [], 'Niciun produs extras — ajustează selectori sau folosește Agent AI.');
        }

        const fieldsEl = document.getElementById('sc-test-fields');
        if (fieldsEl && data.matched_fields) {
            renderFieldsChecklist('sc-test-fields', data.matched_fields);
        } else if (fieldsEl && (data.items || []).length) {
            renderFieldsChecklist('sc-test-fields', buildMatchedFields(data.items, currentConfig?.output?.fields_needed));
        }

        const pre = document.getElementById('sc-test-json');
        if (pre) pre.textContent = JSON.stringify(data, null, 2);

        if (data.ai_agent) {
            const agentPayload = { ...data.ai_agent, items: data.items?.length ? data.items : (data.ai_agent.items || []) };
            renderAgentResult(agentPayload);
            if (data.ai_agent.auto_applied && (data.items_count || 0) > 0) {
                showToast('Agent AI a configurat automat selectori — ' + data.items_count + ' produse.', true);
            }
        }
    }

    function renderAgentResult(agent) {
        if (!agent) return;

        const el = document.getElementById('sc-ai-result');
        const lines = [];
        const modText = agent.mode === 'cursor-composer-2.5'
            ? 'Cursor Composer 2.5'
            : `${esc(agent.mode || '—')} ${agent.llm_used ? '(LLM ' + esc(agent.provider || '') + ')' : '(fără LLM)'}`;
        lines.push(`<div class="mb-2"><strong>Mod:</strong> ${modText}</div>`);
        if (agent.explanation_ro) {
            lines.push(`<div class="mb-2 p-2 rounded bg-slate-50 border">${esc(agent.explanation_ro)}</div>`);
        }
        if (agent.error) {
            lines.push(`<div class="mb-2 text-danger">${esc(agent.error)}</div>`);
        }
        if (agent.selectors) {
            lines.push('<div class="mb-1 font-semibold">Selectori propusi:</div><ul class="list-disc pl-5 mb-2">');
            Object.entries(agent.selectors).forEach(([k, v]) => {
                lines.push(`<li><code>${esc(k)}</code> → <code>${esc(v)}</code></li>`);
            });
            lines.push('</ul>');
        }
        if (agent.items?.length) {
            lines.push(`<div class="mb-1"><strong>${agent.items_count || agent.items.length} produse</strong> extrase din HTML.</div>`);
        }
        if (agent.saved) {
            lines.push('<div class="text-success mb-2">✅ Selectori salvați în Logică pași.</div>');
        }
        if (el) el.innerHTML = lines.join('') || '—';

        const items = agent.items || [];
        const fieldsNeeded = currentConfig?.output?.fields_needed || ['title', 'image', 'url', 'price', 'sku'];
        renderProductCards('sc-ai-items', items, 'Niciun produs extras — rulează agentul după Pas 1 (fetch).');
        renderFieldsChecklist('sc-ai-fields', buildMatchedFields(items, fieldsNeeded));

        const cardsEl = document.getElementById('sc-ai-items');
        if (items.length && cardsEl) {
            cardsEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }

        const pre = document.getElementById('sc-ai-json');
        const toggle = document.getElementById('sc-ai-json-toggle');
        if (pre) {
            pre.textContent = JSON.stringify(agent, null, 2);
            pre.classList.add('hidden');
        }
        if (toggle) {
            toggle.classList.remove('hidden');
            toggle.textContent = 'Arată JSON tehnic';
            toggle.onclick = () => {
                const show = pre.classList.toggle('hidden');
                toggle.textContent = show ? 'Arată JSON tehnic' : 'Ascunde JSON';
            };
        }
    }

    async function runAiAgent(applyAndSave) {
        if (!currentSourceId) return;
        const btn = applyAndSave ? document.getElementById('sc-ai-apply') : document.getElementById('sc-ai-run');
        if (btn) { btn.disabled = true; }
        try {
            await saveConfig();
            const j = await apiPost('agent_analyze_html', {
                source_id: currentSourceId,
                goals: document.getElementById('sc-ai-goals')?.value?.trim(),
                raw_saved: lastRawSaved || undefined,
                limit: parseInt(document.getElementById('sc-test-limit')?.value || '5', 10),
                apply_and_save: !!applyAndSave,
            });
            renderAgentResult(j.data);
            if (j.data?.items?.length) {
                setScTab('agent');
            }
            if (applyAndSave && j.data?.saved) {
                const refreshed = await apiGet('source', { source_id: currentSourceId });
                currentConfig = refreshed.config;
                ScraperStepBuilder.setSteps(currentConfig.steps || []);
                ScraperStepBuilder.render();
            }
            showToast(j.message, !!(j.data?.items_count));
            setScTab('agent');
        } catch (e) {
            showToast(e.message, false);
        } finally {
            if (btn) btn.disabled = false;
        }
    }

    async function analyzeSavedHtml() {
        if (!currentSourceId) return;
        const btn = document.getElementById('sc-analyze-html');
        if (btn) { btn.disabled = true; btn.textContent = 'Analizez…'; }
        try {
            const j = await apiPost('analyze_saved_html', {
                source_id: currentSourceId,
                raw_saved: lastRawSaved || undefined,
                limit: parseInt(document.getElementById('sc-test-limit')?.value || '5', 10),
            });
            const d = j.data || {};
            renderDiagnostics(d.diagnostics, d.selectors_used, d.raw_saved);
            if (d.items?.length) {
                renderProductCards('sc-ai-items', d.items, 'Niciun produs extras.');
                renderFieldsChecklist('sc-ai-fields', buildMatchedFields(d.items, currentConfig?.output?.fields_needed));
                const itemsEl = document.getElementById('sc-test-items');
                if (itemsEl) renderProductCards('sc-test-items', d.items);
                setScTab('agent');
                document.getElementById('sc-ai-items')?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
            const pre = document.getElementById('sc-test-json');
            if (pre) pre.textContent = JSON.stringify(d, null, 2);
            showToast(j.message, (d.items_count || 0) > 0);
            if (!(d.items?.length)) {
                setScTab('testare');
            }
            if ((d.items_count || 0) === 0) {
                showToast('0 produse — deschide tab Agent AI sau apasă «Aplică selectori Autodoc + Salvează».', false);
            }
        } catch (e) {
            showToast(e.message, false);
        } finally {
            if (btn) { btn.disabled = false; btn.textContent = 'Analizează ultimul HTML (fără fetch)'; }
        }
    }

    async function runTest() {
        if (!currentSourceId) return;
        const btn = document.getElementById('sc-run-test');
        if (btn) { btn.disabled = true; btn.textContent = 'Se rulează… (~90s)'; }
        try {
            const activeSteps = (ScraperStepBuilder.getSteps() || []).filter(s => s.enabled !== false);
            if (activeSteps.length === 0) {
                showToast('Niciun pas activ — bifează «Activ pas» la Pas 1 și Pas 2.', false);
                setScTab('logica');
                return;
            }
            const j = await apiPost('source_test', {
                source_id: currentSourceId,
                query: document.getElementById('sc-test-query')?.value,
                limit: parseInt(document.getElementById('sc-test-limit')?.value || '5', 10),
                super: !!document.getElementById('sc-fetch-super')?.checked,
                render: !!document.getElementById('sc-fetch-render')?.checked,
            });
            renderTestResult(j.data);
            showToast(j.message, true);
            setScTab('testare');
        } catch (e) {
            showToast(e.message, false);
        } finally {
            if (btn) { btn.disabled = false; btn.textContent = 'Testează cu logica salvată'; }
        }
    }

    async function quickTest(id) {
        await openSource(id);
        setScTab('testare');
        await runTest();
    }

    // Events
    document.getElementById('sc-back-cards')?.addEventListener('click', () => { showView('cards'); loadCards(); });
    document.getElementById('sc-back-from-vitrina')?.addEventListener('click', () => showView('cards'));
    document.getElementById('sc-back-from-logs')?.addEventListener('click', () => showView('cards'));
    document.getElementById('sc-open-epiesa-vitrina')?.addEventListener('click', () => showView('vitrina'));
    document.getElementById('sc-open-pipeline')?.addEventListener('click', () => loadPipelineView().catch(e => showToast(e.message, false)));
    document.getElementById('sc-back-from-pipeline')?.addEventListener('click', () => showView('cards'));
    document.getElementById('sc-save-pipeline')?.addEventListener('click', async () => {
        try {
            const j = await apiPost('integration_save', { config: collectIntegrationConfig() });
            integrationConfig = j.data;
            showToast(j.message, true);
        } catch (e) { showToast(e.message, false); }
    });
    document.getElementById('sc-add-plan')?.addEventListener('click', () => {
        const plans = collectImagePlans();
        plans.push({
            tier: plans.length + 1,
            label: 'Plan ' + (plans.length + 1),
            source_id: availableSources[0] || '',
            enabled: true,
            roles: ['image'],
        });
        integrationConfig.image_plans = plans;
        renderImagePlans(plans);
    });
    function pipelineStepIcon(status) {
        if (status === 'running') return '⏳';
        if (status === 'ok') return '✅';
        if (status === 'skipped') return '⏭️';
        if (status === 'error') return '⛔';
        if (status === 'miss') return '❌';
        return '○';
    }

    function formatPipelineDuration(ms) {
        const sec = Math.max(0, Math.round((ms || 0) / 1000));
        if (sec < 60) return sec + 's';
        return Math.floor(sec / 60) + 'm ' + (sec % 60) + 's';
    }

    function renderPipelineSegments(plans, triedMap, activeIdx) {
        const el = document.getElementById('sc-pipeline-segments');
        if (!el) return;
        el.innerHTML = plans.map((p, i) => {
            const key = p.source_id || ('plan-' + i);
            const tried = triedMap?.[key] || triedMap?.[String(p.tier)] || null;
            let status = 'pending';
            if (tried) status = tried.status || 'miss';
            else if (activeIdx === i) status = 'running';
            const label = p.label || p.source_id || ('Plan ' + (i + 1));
            return `<div class="sc-pipeline-segment is-${status}" title="${esc(label)}">P${i + 1}</div>`;
        }).join('');
    }

    function showPipelineQuotaWarn(ctx) {
        const el = document.getElementById('sc-pipeline-quota-warn');
        if (!el) return;
        const quota = !!ctx?.scrape_do_quota_exceeded;
        const token = ctx?.scrape_do_token !== false;
        const left = ctx?.scrape_do_queries_left;
        const rapidBlocked = !!ctx?.rapidapi_quota_blocked;
        const rapidKey = ctx?.rapidapi_key_set !== false;
        const msgs = [];
        if (!token) {
            msgs.push('Lipsește SCRAPE_DO_TOKEN — Autodoc/eMAG nu pot descărca pagini.');
        } else if (quota) {
            const leftTxt = typeof left === 'number'
                ? ` (~${left.toLocaleString('ro-RO')} query-uri rămase în Setări)`
                : '';
            msgs.push('Cotă scrape.do epuizată' + leftTxt + ' — Plan 1 (Autodoc) poate eșua.');
        }
        if (!rapidKey) {
            msgs.push('Lipsește RAPIDAPI_AUTOPARTS_KEY — Plan 2 (TecDoc) nu poate căuta imagini.');
        } else if (rapidBlocked) {
            msgs.push((ctx?.rapidapi_message || 'RapidAPI TecDoc blocat local (cotă).') + ' Plan 2 va eșua instant.');
        }
        if (!msgs.length) {
            el.classList.add('hidden');
            el.innerHTML = '';
            return;
        }
        el.classList.remove('hidden');
        el.textContent = 'Atenție: ' + msgs.join(' ');
    }

    function renderPipelineSteps(plans, activeIdx, triedMap) {
        const el = document.getElementById('sc-pipeline-steps');
        if (!el) return;
        el.classList.remove('hidden');
        el.innerHTML = plans.map((p, i) => {
            const key = p.source_id || ('plan-' + i);
            const tried = triedMap?.[key] || triedMap?.[String(p.tier)] || null;
            let status = 'pending';
            if (tried) status = tried.status || 'miss';
            else if (activeIdx === i) status = 'running';

            let msg = tried?.message || '';
            if (!msg && status === 'running') msg = 'Se caută imagine… (fetch scrape.do / API — poate dura 30–90s)';
            if (!msg && status === 'pending') msg = 'În așteptare';

            const queryUsed = tried?.query_used ? `Query: «${tried.query_used}»` : '';
            const duration = tried?.duration_ms ? `Durată: ${formatPipelineDuration(tried.duration_ms)}` : '';
            const meta = [queryUsed, duration].filter(Boolean).join(' · ');

            return `
                <div class="sc-pipeline-step is-${status}">
                    <span class="sc-pipeline-step-icon">${pipelineStepIcon(status)}</span>
                    <div class="sc-pipeline-step-body">
                        <div class="sc-pipeline-step-title">Plan ${i + 1} — ${esc(p.label || p.source_id || '—')} <span class="opacity-60">(${esc(p.source_id || '')})</span></div>
                        ${msg ? `<div class="sc-pipeline-step-msg">${esc(msg)}</div>` : ''}
                        ${meta ? `<div class="sc-pipeline-step-time">${esc(meta)}</div>` : ''}
                    </div>
                </div>
            `;
        }).join('');
        renderPipelineSegments(plans, triedMap, activeIdx);
    }

    function renderPipelineSummary(hit, triedList, totalMs) {
        const el = document.getElementById('sc-pipeline-summary');
        if (!el) return;
        el.classList.remove('hidden', 'is-ok', 'is-fail');
        const ok = !!hit?.url;
        el.classList.add(ok ? 'is-ok' : 'is-fail');
        const tried = Array.isArray(triedList) ? triedList : [];
        const last = tried[tried.length - 1];
        if (ok) {
            el.innerHTML = `<strong>Succes</strong> — imagine găsită la Plan ${esc(String(last?.tier || '?'))} (${esc(last?.source_id || '')}) în ${formatPipelineDuration(totalMs)}.`;
            return;
        }
        const reasons = tried
            .filter(t => t && t.status !== 'skipped')
            .map(t => `Plan ${t.tier}: ${t.message || 'fără rezultat'}`)
            .join(' · ');
        el.innerHTML = `<strong>Fără imagine</strong> — ${tried.length} plan(uri) încercate în ${formatPipelineDuration(totalMs)}.${reasons ? `<br><span class="opacity-80">${esc(reasons)}</span>` : ''}`;
    }

    function renderPipelineHit(data, query) {
        const el = document.getElementById('sc-pipeline-hit');
        if (!el) return;
        const hit = data?.hit;
        const url = hit?.url || '';
        if (!url) {
            el.classList.add('hidden');
            el.innerHTML = '';
            return;
        }
        const img = scraperProxyImageUrl(url);
        const title = hit.title || query || 'Produs';
        const source = hit.source || '—';
        const productUrl = hit.url_product || '';
        el.classList.remove('hidden');
        el.innerHTML = `
            <div class="sc-pipeline-hit-card">
                ${img ? `<img src="${esc(img)}" alt="${esc(title)}">` : ''}
                <div class="sc-pipeline-hit-meta">
                    <div><strong>Imagine găsită</strong> — sursă: <code>${esc(source)}</code></div>
                    <div class="mt-1">${esc(title)}</div>
                    ${productUrl ? `<a class="sc-item-link" href="${esc(productUrl)}" target="_blank" rel="noopener">Deschide sursa ↗</a>` : ''}
                </div>
            </div>
        `;
    }

    function setPipelineProgress(running, pct, statusText) {
        const wrap = document.getElementById('sc-pipeline-progress-wrap');
        const bar = document.getElementById('sc-pipeline-bar-fill');
        const status = document.getElementById('sc-pipeline-status');
        const pctEl = document.getElementById('sc-pipeline-pct');
        if (!wrap || !bar) return;
        const safePct = Math.min(100, Math.max(0, pct));
        wrap.classList.toggle('hidden', !running && safePct <= 0);
        if (running || safePct > 0) wrap.classList.remove('hidden');
        bar.classList.toggle('is-indeterminate', running && safePct < 95);
        if (!running || safePct >= 95) {
            bar.style.marginLeft = '0';
            bar.style.width = safePct + '%';
        }
        if (status && statusText) status.textContent = statusText;
        if (pctEl) pctEl.textContent = Math.round(safePct) + '%';
    }

    async function runPipelineTest() {
        const q = (document.getElementById('sc-pipeline-test-query')?.value || '').trim();
        if (!q) { showToast('Introdu numele produsului.', false); return; }

        const btn = document.getElementById('sc-test-pipeline');
        const pre = document.getElementById('sc-pipeline-test-result');
        const jsonToggle = document.getElementById('sc-pipeline-json-toggle');
        const elapsedEl = document.getElementById('sc-pipeline-elapsed');
        const summaryEl = document.getElementById('sc-pipeline-summary');
        const plans = dedupeImagePlans(collectImagePlans().filter(p => p.enabled !== false && (p.source_id || '').trim() !== ''));

        if (!plans.length) {
            showToast('Adaugă cel puțin un plan activ cu sursă selectată.', false);
            return;
        }

        if (btn) { btn.disabled = true; btn.textContent = 'Se rulează…'; }
        if (pre) { pre.classList.add('hidden'); pre.textContent = '—'; }
        if (jsonToggle) jsonToggle.classList.add('hidden');
        if (summaryEl) { summaryEl.classList.add('hidden'); summaryEl.innerHTML = ''; }
        document.getElementById('sc-pipeline-hit')?.classList.add('hidden');

        const triedMap = {};
        const triedList = [];
        let finalHit = null;
        let finalData = { query: q, tried: [], hit: null, log: [] };

        setPipelineProgress(true, 2, `Pornesc test pipeline: «${q}»`);
        renderPipelineSteps(plans, 0, triedMap);
        renderPipelineSegments(plans, triedMap, 0);

        const t0 = Date.now();
        const elapsedTimer = setInterval(() => {
            const sec = Math.floor((Date.now() - t0) / 1000);
            if (elapsedEl) elapsedEl.textContent = `Timp total: ${sec}s`;
        }, 500);

        try {
            for (let i = 0; i < plans.length; i++) {
                const plan = plans[i];
                const planPctStart = Math.round((i / plans.length) * 100);
                const planPctEnd = Math.round(((i + 1) / plans.length) * 100);
                const planLabel = plan.label || plan.source_id || ('Plan ' + (i + 1));

                setPipelineProgress(true, planPctStart + 2, `Plan ${i + 1}/${plans.length} — ${planLabel}…`);
                renderPipelineSteps(plans, i, triedMap);

                try {
                    const stepRes = await apiPost('test_image_pipeline_step', {
                        query: q,
                        tier: i + 1,
                        source_id: plan.source_id,
                        label: plan.label || planLabel,
                    }, 200000);

                    const data = stepRes.data || {};
                    if (data.context) showPipelineQuotaWarn(data.context);

                    const tried = data.tried || {};
                    const key = plan.source_id || ('plan-' + i);
                    triedMap[key] = tried;
                    triedMap[String(tried.tier || (i + 1))] = tried;
                    triedList.push(tried);
                    renderPipelineSteps(plans, -1, triedMap);
                    setPipelineProgress(true, planPctEnd, `Plan ${i + 1}/${plans.length} — ${tried.message || 'finalizat'}`);

                    if (data.hit?.url) {
                        finalHit = data.hit;
                        finalData = { query: q, tried: triedList, hit: finalHit, context: data.context };
                        break;
                    }
                } catch (planErr) {
                    const errTried = {
                        tier: i + 1,
                        source_id: plan.source_id,
                        label: planLabel,
                        status: 'error',
                        message: planErr.message || 'Eroare plan',
                    };
                    triedList.push(errTried);
                    triedMap[plan.source_id || ('plan-' + i)] = errTried;
                    renderPipelineSteps(plans, -1, triedMap);
                    setPipelineProgress(true, planPctEnd, `Plan ${i + 1} — ${errTried.message}`);
                }
            }

            if (!finalHit) {
                finalData = { query: q, tried: triedList, hit: null };
            }

            renderPipelineHit(finalData, q);
            renderPipelineSummary(finalHit, triedList, Date.now() - t0);

            const hit = !!finalHit?.url;
            setPipelineProgress(false, 100,
                hit ? `Gata — imagine găsită (${formatPipelineDuration(Date.now() - t0)})` : `Gata — niciun plan nu a găsit imagine (${formatPipelineDuration(Date.now() - t0)})`);

            if (pre) {
                pre.textContent = JSON.stringify(finalData, null, 2);
                pre.classList.add('hidden');
            }
            if (jsonToggle) {
                jsonToggle.classList.remove('hidden');
                jsonToggle.textContent = 'Arată JSON tehnic';
                jsonToggle.onclick = () => {
                    const hidden = pre?.classList.toggle('hidden');
                    jsonToggle.textContent = hidden ? 'Arată JSON tehnic' : 'Ascunde JSON';
                };
            }

            showToast(hit ? 'Imagine găsită în pipeline.' : 'Fără imagine — vezi rezumatul planurilor.', hit);
            (document.getElementById('sc-pipeline-summary') || document.getElementById('sc-pipeline-hit'))?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        } catch (e) {
            setPipelineProgress(false, 0, 'Eroare la test.');
            renderPipelineSteps(plans, -1, triedMap);
            const errTried = {
                tier: (triedList.length || 0) + 1,
                source_id: plans[triedList.length]?.source_id || '',
                label: plans[triedList.length]?.label || 'Plan curent',
                status: 'error',
                message: e.message || 'Eroare necunoscută la apel API',
            };
            if (triedList.length < plans.length) {
                triedList.push(errTried);
                const key = errTried.source_id || ('plan-' + triedList.length);
                triedMap[key] = errTried;
            }
            renderPipelineSummary(null, triedList, Date.now() - t0);
            showToast(e.message, false);
        } finally {
            clearInterval(elapsedTimer);
            if (btn) { btn.disabled = false; btn.textContent = 'Testează Plan 1→2→3'; }
        }
    }

    document.getElementById('sc-test-pipeline')?.addEventListener('click', () => runPipelineTest().catch(e => showToast(e.message, false)));
    document.getElementById('sc-save-integration')?.addEventListener('click', () => saveConfig().catch(e => showToast(e.message, false)));
    document.getElementById('sc-add-goal')?.addEventListener('click', openAddGoalModal);
    document.getElementById('sc-confirm-add-goal')?.addEventListener('click', confirmAddGoal);
    document.querySelectorAll('[data-close-goal-modal]').forEach(el => el.addEventListener('click', () => {
        document.getElementById('sc-modal-add-goal')?.classList.add('hidden');
    }));
    document.getElementById('sc-open-logs')?.addEventListener('click', async () => {
        showView('logs');
        try {
            const data = await apiGet('logs', { lines: 200 });
            const pre = document.getElementById('scraper-log');
            if (pre) pre.textContent = data.log || '—';
        } catch (e) { showToast(e.message, false); }
    });
    document.getElementById('sc-save-config')?.addEventListener('click', () => saveConfig().catch(e => showToast(e.message, false)));
    document.getElementById('sc-run-test')?.addEventListener('click', () => runTest());
    document.getElementById('sc-analyze-html')?.addEventListener('click', () => analyzeSavedHtml());
    document.getElementById('sc-ai-run')?.addEventListener('click', () => runAiAgent(false));
    document.getElementById('sc-ai-apply')?.addEventListener('click', () => runAiAgent(true));
    document.getElementById('sc-apply-autodoc-preset')?.addEventListener('click', () => applyAutodocPresetAndSave().catch(e => showToast(e.message, false)));
    document.getElementById('sc-apply-preset-from-diag')?.addEventListener('click', () => applyAutodocPresetAndSave().catch(e => showToast(e.message, false)));
    document.getElementById('sc-delete-source')?.addEventListener('click', () => {
        if (currentSourceId) deleteSource(currentSourceId);
    });
    document.getElementById('sc-add-source')?.addEventListener('click', openCreateModal);
    document.querySelectorAll('[data-close-modal]').forEach(el => el.addEventListener('click', closeCreateModal));
    document.getElementById('sc-create-form')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const fd = new FormData(e.target);
        const body = Object.fromEntries(fd.entries());
        try {
            const j = await apiPost('source_create', body);
            closeCreateModal();
            showToast(j.message, true);
            await loadCards();
            if (j.data?.id) await openSource(j.data.id);
        } catch (err) {
            showToast(err.message, false);
        }
    });
    document.getElementById('sc-restore-presets')?.addEventListener('click', async () => {
        try {
            const j = await apiPost('source_restore_presets', {});
            showToast(j.message, true);
            await loadCards();
        } catch (e) { showToast(e.message, false); }
    });
    document.getElementById('sc-sync-all')?.addEventListener('click', async () => {
        try {
            const j = await apiPost('sync_all_sources', {});
            showToast(j.message + ' Active: ' + (j.data?.active || []).join(', '), true);
        } catch (e) { showToast(e.message, false); }
    });
    document.getElementById('sc-reset-config')?.addEventListener('click', async () => {
        if (!currentSourceId || !confirm('Resetezi la valorile implicite pentru această sursă?')) return;
        try {
            await apiPost('source_save', { source_id: currentSourceId, config: { __reset: true } });
            await openSource(currentSourceId);
            showToast('Resetat la implicit', true);
        } catch (e) { showToast(e.message, false); }
    });

    document.querySelectorAll('.sc-tab').forEach(tab => {
        tab.addEventListener('click', () => setScTab(tab.dataset.scTab));
    });

    ScraperStepBuilder.init({ esc });
    loadCards().catch(e => showToast(e.message, false));

    apiGet('step_catalog').then(cat => {
        ScraperStepBuilder.init({
            esc,
            stepTypes: cat.step_types || [],
            elementTypes: cat.element_types || [],
        });
        extractionGoalCatalog = cat.extraction_goals || [];
    }).catch(() => {});
    apiGet('integration').then(data => {
        availableSources = data.available_sources || [];
        extractionGoalCatalog = data.extraction_goal_catalog || extractionGoalCatalog;
    }).catch(() => {});
})();
</script>
