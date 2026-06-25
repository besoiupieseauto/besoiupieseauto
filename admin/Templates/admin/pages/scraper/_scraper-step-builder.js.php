<?php
declare(strict_types=1);

$scraperSchemaRoot = dirname(__DIR__, 5);
require_once $scraperSchemaRoot . '/lib/Scraper/ScraperStepSchema.php';

$scraperBuiltinCatalog = [
    'step_types' => ScraperStepSchema::stepTypeCatalog(),
    'element_types' => ScraperStepSchema::elementTypeCatalog(),
];
?>
<script>
const SC_BUILTIN_CATALOG = <?= json_encode($scraperBuiltinCatalog, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

window.ScraperStepBuilder = (function () {
    let steps = [];
    let esc = (s) => String(s ?? '');
    let stepTypes = SC_BUILTIN_CATALOG.step_types || [];
    let elementTypes = SC_BUILTIN_CATALOG.element_types || [];
    let addElementForStepId = null;
    let pickedElementKey = null;

    function uid(prefix) {
        return prefix + '_' + Math.random().toString(36).slice(2, 9);
    }

    function init(opts) {
        esc = opts.esc || esc;
        if (opts.stepTypes?.length) stepTypes = opts.stepTypes;
        if (opts.elementTypes?.length) elementTypes = opts.elementTypes;
    }

    function setSteps(list) {
        steps = JSON.parse(JSON.stringify(list || []));
        reorder();
    }

    function getSteps() {
        return JSON.parse(JSON.stringify(steps));
    }

    function reorder() {
        steps.sort((a, b) => (a.order || 0) - (b.order || 0));
        steps.forEach((s, i) => { s.order = i + 1; });
    }

    function syncFromDom() {
        const container = document.getElementById('sc-steps-container');
        if (!container || !container.querySelector('.sc-step-card')) {
            return;
        }
        container.querySelectorAll('.sc-step-card[data-step-id]').forEach(card => {
            const sid = card.dataset.stepId;
            const step = steps.find(s => s.id === sid);
            if (!step) return;
            const enabledCb = card.querySelector('.step-enabled');
            if (enabledCb) {
                step.enabled = enabledCb.checked;
            }
            step.label = card.querySelector('.step-label-input')?.value || step.label;
            const p = step.params || (step.params = {});

            card.querySelectorAll('[data-param]').forEach(inp => {
                const key = inp.dataset.param;
                if (inp.type === 'checkbox') p[key] = inp.checked;
                else if (inp.type === 'number') p[key] = parseInt(inp.value, 10) || 0;
                else p[key] = inp.value;
            });

            card.querySelectorAll('[data-el-id]').forEach(row => {
                const eid = row.dataset.elId;
                const el = (p.elements || []).find(e => e.id === eid);
                if (!el) return;
                el.label = row.querySelector('.el-label')?.value || el.label;
                el.selector = row.querySelector('.el-selector')?.value || '';
            });
        });
    }

    function defaultParams(type) {
        if (type === 'fetch') return { url_template: 'https://example.com/search?q={query}' };
        if (type === 'login') return {
            url: '', username_selector: '', password_selector: '', submit_selector: '',
            username: '', password: '',
        };
        if (type === 'extract_list') return {
            limit: 5, ignore: 'placeholder, star-fill, #', elements: [
                { id: uid('el'), key: 'block', label: 'Bloc produs', selector: '' },
            ],
        };
        if (type === 'follow_links') return { save_links: true, max_follow: 1, elements: [] };
        if (type === 'extract_detail') return {
            elements: [{ id: uid('el'), key: 'block', label: 'Bloc detaliu', selector: '' }],
        };
        return {};
    }

    function addStep(type) {
        const st = stepTypes.find(t => t.type === type);
        const step = {
            id: uid('step'),
            order: steps.length + 1,
            label: 'Pas ' + (steps.length + 1) + (st ? ' — ' + st.label.split('(')[0].trim() : ''),
            type,
            enabled: true,
            params: defaultParams(type),
        };
        steps.push(step);
        render();
    }

    function removeStep(id) {
        if (!confirm('Ștergi acest pas?')) return;
        steps = steps.filter(s => s.id !== id);
        reorder();
        render();
    }

    function moveStep(id, dir) {
        const i = steps.findIndex(s => s.id === id);
        if (i < 0) return;
        const j = i + dir;
        if (j < 0 || j >= steps.length) return;
        [steps[i], steps[j]] = [steps[j], steps[i]];
        reorder();
        render();
    }

    function catalogLabel(key) {
        const cat = elementTypes.find(e => e.key === key);
        return cat?.label || key;
    }

    function addElement(stepId, key, displayLabel, selector) {
        const step = steps.find(s => s.id === stepId);
        if (!step) return false;
        const p = step.params || (step.params = {});
        p.elements = p.elements || [];
        const label = (displayLabel || '').trim() || catalogLabel(key);
        const el = {
            id: uid('el'),
            key: key === 'custom' ? slug(label) : key,
            label,
            selector: (selector || '').trim(),
        };
        if (key === 'block' && p.elements.some(e => e.key === 'block')) {
            alert('Bloc produs există deja în acest pas.');
            return false;
        }
        p.elements.push(el);
        render();
        return true;
    }

    function removeElement(stepId, elId) {
        const step = steps.find(s => s.id === stepId);
        if (!step?.params?.elements) return;
        step.params.elements = step.params.elements.filter(e => e.id !== elId);
        render();
    }

    function slug(s) {
        return String(s).toLowerCase().replace(/[^a-z0-9_]+/g, '_').replace(/^_|_$/g, '') || 'custom';
    }

    function renderStepParams(step) {
        const p = step.params || {};
        const t = step.type;
        if (t === 'fetch') {
            return `<label class="sc-field full"><span>URL template — folosește <code>{query}</code></span>
                <input type="text" class="box h-9 w-full rounded-md border px-2 text-sm" data-param="url_template" value="${esc(p.url_template || '')}"></label>`;
        }
        if (t === 'login') {
            return `
                <label class="sc-field full"><span>URL pagină login</span><input type="url" data-param="url" class="box h-9 w-full rounded-md border px-2 text-sm" value="${esc(p.url || '')}"></label>
                <label class="sc-field"><span>Selector câmp user</span><input type="text" data-param="username_selector" class="box h-9 w-full rounded-md border px-2 text-sm" value="${esc(p.username_selector || '')}"></label>
                <label class="sc-field"><span>Selector câmp parolă</span><input type="text" data-param="password_selector" class="box h-9 w-full rounded-md border px-2 text-sm" value="${esc(p.password_selector || '')}"></label>
                <label class="sc-field"><span>Selector buton submit</span><input type="text" data-param="submit_selector" class="box h-9 w-full rounded-md border px-2 text-sm" value="${esc(p.submit_selector || '')}"></label>
                <label class="sc-field"><span>Utilizator (test)</span><input type="text" data-param="username" class="box h-9 w-full rounded-md border px-2 text-sm" value="${esc(p.username || '')}"></label>
                <label class="sc-field"><span>Parolă (test)</span><input type="password" data-param="password" class="box h-9 w-full rounded-md border px-2 text-sm" value="${esc(p.password || '')}"></label>`;
        }
        if (t === 'extract_list' || t === 'extract_detail') {
            const extra = t === 'extract_list' ? `
                <label class="sc-field"><span>Limită rezultate</span><input type="number" min="1" max="20" data-param="limit" class="box h-9 w-full rounded-md border px-2 text-sm" value="${esc(String(p.limit || 5))}"></label>
                <label class="sc-field full"><span>Ignoră (virgulă)</span><input type="text" data-param="ignore" class="box h-9 w-full rounded-md border px-2 text-sm" value="${esc(p.ignore || '')}"></label>` : '';
            return extra + renderElementsBlock(step);
        }
        if (t === 'follow_links') {
            return `
                <label class="sc-field full flex-row items-center gap-2"><input type="checkbox" data-param="save_links" ${p.save_links ? 'checked' : ''}> Salvează linkurile extrase</label>
                <label class="sc-field"><span>Max pagini de deschis</span><input type="number" min="1" max="5" data-param="max_follow" class="box h-9 w-full rounded-md border px-2 text-sm" value="${esc(String(p.max_follow || 1))}"></label>
                ${renderElementsBlock(step)}`;
        }
        return `<p class="text-sm opacity-60">Tip: ${esc(t)}</p>`;
    }

    function renderElementsBlock(step) {
        const elements = step.params?.elements || [];
        const rows = elements.map(el => `
            <div class="sc-element-row" data-el-id="${esc(el.id)}">
                <label class="sc-field m-0"><span>Denumire</span>
                    <input type="text" class="el-label box h-8 w-full rounded-md border px-2 text-xs" value="${esc(el.label)}">
                </label>
                <label class="sc-field m-0"><span>Selector CSS / XPath</span>
                    <input type="text" class="el-selector box h-8 w-full rounded-md border px-2 text-sm" value="${esc(el.selector || '')}" placeholder=".class sau //div[...]">
                </label>
                <button type="button" class="sc-el-del" data-action="del-el" data-step-id="${esc(step.id)}" data-el-id="${esc(el.id)}">Șterge</button>
            </div>
        `).join('');
        const canAdd = ['extract_list', 'extract_detail', 'follow_links'].includes(step.type);
        return `<div class="sc-elements-list">
            <div class="text-xs font-semibold uppercase opacity-50">Elemente de extras</div>
            ${rows || '<p class="text-xs opacity-50">Niciun element — apasă «+ Adaugă element».</p>'}
            ${canAdd ? `<button type="button" class="sc-btn-add-el" data-action="add-el" data-step-id="${esc(step.id)}">+ Adaugă element</button>` : ''}
        </div>`;
    }

    function render() {
        const container = document.getElementById('sc-steps-container');
        if (!container) return;
        if (!steps.length) {
            container.innerHTML = '<p class="text-sm opacity-60 py-4">Niciun pas — apasă <strong>+ Adaugă pas</strong>.</p>';
            return;
        }
        container.innerHTML = steps.map((step, idx) => `
            <div class="sc-step-card" data-step-id="${esc(step.id)}" data-step-index="${idx}">
                <div class="sc-step-head">
                    <div class="flex-1 min-w-0">
                        <input type="text" class="step-label-input box h-9 w-full max-w-md rounded-md border px-2 text-sm font-semibold" value="${esc(step.label)}">
                        <div class="text-xs opacity-50 mt-1">${esc(step.type)} · Pas ${step.order}</div>
                    </div>
                    <div class="sc-step-actions">
                        <button type="button" class="sc-step-move" data-action="up" data-step-id="${esc(step.id)}">↑</button>
                        <button type="button" class="sc-step-move" data-action="down" data-step-id="${esc(step.id)}">↓</button>
                        <label class="flex items-center gap-1 text-xs" title="Bifează ca pasul să ruleze la test">
                            <input type="checkbox" class="step-enabled" ${step.enabled !== false ? 'checked' : ''}> Activ pas
                        </label>
                        <button type="button" class="sc-step-del" data-action="del-step" data-step-id="${esc(step.id)}">Șterge pas</button>
                    </div>
                </div>
                <div class="sc-step-body">${renderStepParams(step)}</div>
            </div>
        `).join('');

        container.querySelectorAll('[data-action="del-step"]').forEach(btn => {
            btn.addEventListener('click', () => removeStep(btn.dataset.stepId));
        });
        container.querySelectorAll('[data-action="up"]').forEach(btn => {
            btn.addEventListener('click', () => moveStep(btn.dataset.stepId, -1));
        });
        container.querySelectorAll('[data-action="down"]').forEach(btn => {
            btn.addEventListener('click', () => moveStep(btn.dataset.stepId, 1));
        });
        container.querySelectorAll('[data-action="add-el"]').forEach(btn => {
            btn.addEventListener('click', () => openAddElementModal(btn.dataset.stepId));
        });
        container.querySelectorAll('[data-action="del-el"]').forEach(btn => {
            btn.addEventListener('click', () => removeElement(btn.dataset.stepId, btn.dataset.elId));
        });
    }

    function selectElementType(key) {
        pickedElementKey = key;
        const list = document.getElementById('sc-element-type-list');
        list?.querySelectorAll('.sc-type-pick').forEach(btn => {
            btn.classList.toggle('is-selected', btn.dataset.elKey === key);
        });
        const nameInput = document.getElementById('sc-el-display-name');
        const hint = document.getElementById('sc-el-type-hint');
        const defaultName = catalogLabel(key);
        if (nameInput && (!nameInput.value.trim() || nameInput.dataset.auto === '1')) {
            nameInput.value = defaultName;
            nameInput.dataset.auto = '1';
        }
        if (hint) {
            hint.textContent = key === 'block'
                ? 'Containerul fiecărui produs din listă (ex: div.sub-product-inner).'
                : 'Completează selectorul acum sau după ce adaugi elementul.';
        }
    }

    function openAddElementModal(stepId) {
        syncFromDom();
        addElementForStepId = stepId;
        pickedElementKey = null;
        const step = steps.find(s => s.id === stepId);
        const list = document.getElementById('sc-element-type-list');
        const modal = document.getElementById('sc-modal-add-element');
        const nameInput = document.getElementById('sc-el-display-name');
        const selInput = document.getElementById('sc-el-selector-input');
        const hint = document.getElementById('sc-el-type-hint');
        if (!list || !modal || !step) return;

        if (nameInput) { nameInput.value = ''; nameInput.dataset.auto = '1'; }
        if (selInput) selInput.value = '';
        if (hint) hint.textContent = 'Selectează un tip din listă.';

        const allowed = elementTypes.filter(et => {
            if (!et.only) return true;
            return et.only.includes(step.type);
        });

        if (!allowed.length) {
            allowed.push(...(SC_BUILTIN_CATALOG.element_types || []));
        }

        list.innerHTML = allowed.map(et => `
            <button type="button" class="sc-type-pick" data-el-key="${esc(et.key)}">${esc(et.label)}</button>
        `).join('');

        list.querySelectorAll('.sc-type-pick').forEach(btn => {
            btn.addEventListener('click', () => selectElementType(btn.dataset.elKey));
        });

        modal.classList.remove('hidden');
        nameInput?.focus();
    }

    function closeElementModal() {
        document.getElementById('sc-modal-add-element')?.classList.add('hidden');
        addElementForStepId = null;
        pickedElementKey = null;
    }

    function confirmAddElement() {
        if (!addElementForStepId) return;
        if (!pickedElementKey) {
            alert('Alege tipul elementului din listă (pasul 1).');
            return;
        }
        const displayName = document.getElementById('sc-el-display-name')?.value?.trim() || '';
        if (!displayName) {
            alert('Introdu denumirea elementului (pasul 2).');
            document.getElementById('sc-el-display-name')?.focus();
            return;
        }
        const selector = document.getElementById('sc-el-selector-input')?.value?.trim() || '';
        syncFromDom();
        if (addElement(addElementForStepId, pickedElementKey, displayName, selector)) {
            closeElementModal();
        }
    }

    function openAddStepModal() {
        const list = document.getElementById('sc-step-type-list');
        const modal = document.getElementById('sc-modal-add-step');
        if (!list || !modal) return;
        list.innerHTML = stepTypes.map(st => `
            <button type="button" class="sc-type-pick" data-step-type="${esc(st.type)}">
                ${esc(st.label)}
                <small>${esc(st.hint || '')}</small>
            </button>
        `).join('');
        list.querySelectorAll('.sc-type-pick').forEach(btn => {
            btn.addEventListener('click', () => {
                syncFromDom();
                addStep(btn.dataset.stepType);
                modal.classList.add('hidden');
            });
        });
        modal.classList.remove('hidden');
    }

    function bindGlobal() {
        document.getElementById('sc-add-step')?.addEventListener('click', openAddStepModal);
        document.querySelectorAll('[data-close-step-modal]').forEach(el => {
            el.addEventListener('click', () => document.getElementById('sc-modal-add-step')?.classList.add('hidden'));
        });
        document.querySelectorAll('[data-close-el-modal]').forEach(el => {
            el.addEventListener('click', closeElementModal);
        });
        document.getElementById('sc-confirm-add-element')?.addEventListener('click', confirmAddElement);
        document.getElementById('sc-el-display-name')?.addEventListener('input', function () {
            this.dataset.auto = '0';
        });
        document.getElementById('sc-modal-add-element')?.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                confirmAddElement();
            }
        });
    }

    bindGlobal();

    return { init, setSteps, getSteps, render, syncFromDom };
})();
</script>
