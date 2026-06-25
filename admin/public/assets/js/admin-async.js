/**
 * Încărcare non-blocantă pentru pagini admin EvaSystem.
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

    global.BpaAsync = {
        escapeHtml,
        defer,
        fetchJson,
        slot,
        runParallel,
        bindLoader,
        skeletonRows,
        DEFAULT_TIMEOUT,
    };
})(window);
