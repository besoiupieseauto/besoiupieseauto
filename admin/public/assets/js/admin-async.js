/**
 * Încărcare non-blocantă pentru pagini Besoiu Admin.
 * Nu blochează layout-ul — actualizează sloturi independent, cu timeout și fallback.
 */
(function (global) {
    'use strict';

    const DEFAULT_TIMEOUT = 45000;

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, (char) => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
        }[char]));
    }

    function defer(fn) {
        if (typeof global.requestIdleCallback === 'function') {
            global.requestIdleCallback(() => { fn().catch(() => {}); }, { timeout: 1200 });
        } else {
            global.setTimeout(() => { fn().catch(() => {}); }, 0);
        }
    }

    function withTimeout(promise, ms) {
        return new Promise((resolve, reject) => {
            const timer = global.setTimeout(() => reject(new Error('Timeout rețea — reîncercați.')), ms);
            Promise.resolve(promise)
                .then((value) => { global.clearTimeout(timer); resolve(value); })
                .catch((error) => { global.clearTimeout(timer); reject(error); });
        });
    }

    async function fetchJson(url, options, timeoutMs) {
        const response = await withTimeout(
            fetch(url, Object.assign({ credentials: 'same-origin' }, options || {})),
            timeoutMs || DEFAULT_TIMEOUT
        );
        let data = null;
        try {
            data = await response.json();
        } catch (e) {
            throw new Error('Răspuns invalid de la server.');
        }
        if (!response.ok || (data && data.success === false)) {
            throw new Error((data && data.message) || ('HTTP ' + response.status));
        }
        return data;
    }

    function slot(elementOrId, state) {
        const el = typeof elementOrId === 'string'
            ? document.getElementById(elementOrId)
            : elementOrId;
        if (!el) return;

        if (state.loading) {
            el.dataset.bpaLoading = '1';
            if (state.loadingHtml) {
                el.innerHTML = state.loadingHtml;
            } else if (state.loadingLabel && global.BpaAsync && typeof global.BpaAsync.loadingHtml === 'function') {
                el.innerHTML = global.BpaAsync.loadingHtml(state.loadingLabel);
            } else {
                el.innerHTML = '<div class="bpa-load bpa-load--inline"><div class="bpa-load__head"><span class="bpa-load__label">Se încarcă…</span></div><div class="bpa-load__track"><div class="bpa-load__bar bpa-load__bar--pulse" style="width:40%"></div></div></div>';
            }
            el.classList.remove('hidden');
            return;
        }

        delete el.dataset.bpaLoading;

        if (state.error) {
            el.innerHTML = '<div class="rounded-md border border-danger/30 bg-danger/5 px-3 py-2 text-sm text-danger">' +
                escapeHtml(state.error) + '</div>';
            el.classList.remove('hidden');
            return;
        }

        if (state.html !== undefined) {
            el.innerHTML = state.html;
        }
        if (state.show === true) el.classList.remove('hidden');
        if (state.hide === true) el.classList.add('hidden');
    }

    /**
     * Rulează task-uri în paralel; fiecare task: { id, run: async () => void }
     */
    async function runParallel(tasks) {
        await Promise.allSettled(tasks.map((task) => {
            if (!task || typeof task.run !== 'function') return Promise.resolve();
            return task.run().catch((error) => {
                if (task.id) {
                    slot(task.id, { error: error.message || 'Eroare la încărcare.' });
                }
            });
        }));
    }

    function bindLoader(buttonId, loaderFn) {
        const btn = document.getElementById(buttonId);
        if (!btn) {
            defer(loaderFn);
            return;
        }
        btn.addEventListener('click', () => {
            loaderFn().catch(() => {});
        });
        defer(loaderFn);
    }

    function skeletonRows(cols, rows) {
        const count = rows || 3;
        let html = '';
        for (let i = 0; i < count; i++) {
            html += '<tr class="border-b animate-pulse"><td colspan="' + cols + '" class="px-3 py-3"><div class="h-4 rounded bg-foreground/10"></div></td></tr>';
        }
        return html;
    }

    /**
     * Progres vizibil — bară + pași (înlocuiește „Se încarcă…”).
     * @param {{ container?: string|HTMLElement, steps?: Array<{id:string,label:string,run?:Function}>, compact?: boolean, inline?: boolean }} options
     */
    function createLoadProgress(options) {
        const opts = Object.assign({ steps: [], compact: false, inline: false }, options || {});
        let containerEl = null;
        if (opts.container) {
            containerEl = typeof opts.container === 'string'
                ? document.getElementById(opts.container)
                : opts.container;
        }
        const stepStates = new Map();
        (opts.steps || []).forEach((step) => stepStates.set(step.id, 'pending'));

        function pctDone() {
            const total = opts.steps.length || 1;
            const done = [...stepStates.values()].filter((v) => v === 'done').length;
            const active = [...stepStates.values()].filter((v) => v === 'active').length;
            const base = Math.round((done / total) * 100);
            return active > 0 && base < 100 ? Math.min(99, base + Math.round(50 / total)) : base;
        }

        function activeStep() {
            return (opts.steps || []).find((s) => stepStates.get(s.id) === 'active');
        }

        function render() {
            if (!containerEl) return;
            const pct = pctDone();
            const active = activeStep();
            const allDone = opts.steps.length > 0 && [...stepStates.values()].every((v) => v === 'done');
            const hasError = [...stepStates.values()].some((v) => v === 'error');
            const label = hasError
                ? 'Eroare la încărcare'
                : (allDone ? 'Încărcare completă' : (active ? active.label + '…' : 'Se pregătește…'));

            const classes = ['bpa-load'];
            if (opts.compact) classes.push('bpa-load--compact');
            if (opts.inline) classes.push('bpa-load--inline');
            if (allDone) classes.push('is-complete');
            if (hasError) classes.push('is-error');

            const stepsHtml = (opts.steps || []).map((step) => {
                const st = stepStates.get(step.id) || 'pending';
                const cls = st === 'done' ? 'is-done' : (st === 'active' ? 'is-active' : (st === 'error' ? 'is-error' : ''));
                const icon = st === 'done' ? '✓' : (st === 'active' ? '→' : (st === 'error' ? '✕' : '○'));
                return '<li class="' + cls + '"><span class="bpa-load__step-ico">' + icon + '</span> ' + escapeHtml(step.label) + '</li>';
            }).join('');

            containerEl.innerHTML =
                '<div class="' + classes.join(' ') + '">' +
                '<div class="bpa-load__head">' +
                '<span class="bpa-load__label">' + escapeHtml(label) + '</span>' +
                '<span class="bpa-load__pct">' + pct + '%</span>' +
                '</div>' +
                '<div class="bpa-load__track">' +
                '<div class="bpa-load__bar' + (active && !allDone ? ' bpa-load__bar--pulse' : '') + '" style="width:' + pct + '%"></div>' +
                '</div>' +
                (stepsHtml ? '<ul class="bpa-load__steps">' + stepsHtml + '</ul>' : '') +
                '</div>';
        }

        function inlineHtml(label) {
            return '<div class="bpa-load bpa-load--inline">' +
                '<div class="bpa-load__head"><span class="bpa-load__label">' + escapeHtml(label) + '</span><span class="bpa-load__pct">…</span></div>' +
                '<div class="bpa-load__track"><div class="bpa-load__bar bpa-load__bar--pulse" style="width:40%"></div></div>' +
                '</div>';
        }

        function tableRow(cols, label) {
            return '<tr><td colspan="' + cols + '" class="px-3 py-6 text-center">' + inlineHtml(label) + '</td></tr>';
        }

        return {
            start(id) {
                if (stepStates.has(id)) stepStates.set(id, 'active');
                render();
            },
            done(id) {
                if (stepStates.has(id)) stepStates.set(id, 'done');
                render();
            },
            fail(id) {
                if (stepStates.has(id)) stepStates.set(id, 'error');
                render();
            },
            show() {
                if (containerEl) containerEl.classList.remove('hidden');
                render();
            },
            hide(delayMs) {
                if (!containerEl) return;
                const hideFn = () => containerEl.classList.add('hidden');
                if (delayMs) global.setTimeout(hideFn, delayMs);
                else hideFn();
            },
            render,
            inlineHtml,
            tableRow,
            async runAll() {
                this.show();
                for (const step of opts.steps) {
                    if (typeof step.run !== 'function') continue;
                    this.start(step.id);
                    try {
                        await step.run();
                        this.done(step.id);
                    } catch (error) {
                        this.fail(step.id);
                        throw error;
                    }
                }
                this.hide(1200);
            },
        };
    }

    global.BpaAsync = {
        escapeHtml,
        defer,
        fetchJson,
        slot,
        runParallel,
        bindLoader,
        skeletonRows,
        createLoadProgress,
        loadingHtml: function (label) {
            return createLoadProgress({ steps: [] }).inlineHtml(label || 'Se încarcă…');
        },
        loadingTableRow: function (cols, label) {
            return createLoadProgress({ steps: [] }).tableRow(cols, label || 'Se încarcă…');
        },
        DEFAULT_TIMEOUT,
    };

    global.BpaLoadProgress = createLoadProgress;
})(window);
