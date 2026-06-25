/**
 * Paginator reutilizabil admin — 10 rânduri / pagină.
 */
(function (global) {
    'use strict';

    const DEFAULT_PER_PAGE = 10;

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, (c) => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
        }[c]));
    }

    /**
     * @param {HTMLElement|null} container
     * @param {{total?:number,page?:number,per_page?:number,total_pages?:number}|null} meta
     * @param {(page:number)=>void} onPageChange
     */
    function render(container, meta, onPageChange) {
        if (!container) return;

        const total = Number(meta?.total ?? 0);
        const page = Number(meta?.page ?? 1);
        const perPage = Number(meta?.per_page ?? DEFAULT_PER_PAGE);
        const totalPages = Number(meta?.total_pages ?? Math.max(1, Math.ceil(total / perPage)));

        if (total <= perPage) {
            container.innerHTML = total > 0
                ? `<div class="text-xs opacity-60">${total} înregistrări</div>`
                : '';
            return;
        }

        const from = (page - 1) * perPage + 1;
        const to = Math.min(page * perPage, total);

        let buttons = '';
        const addBtn = (p, label, active, disabled) => {
            buttons += `<button type="button" class="bpa-page-btn box h-9 min-w-9 rounded-md border px-3 text-sm ${active ? 'bg-primary text-white' : ''}" data-page="${p}" ${disabled ? 'disabled' : ''}>${escapeHtml(label)}</button>`;
        };

        addBtn(page - 1, '‹', false, page <= 1);

        const windowSize = 5;
        let start = Math.max(1, page - Math.floor(windowSize / 2));
        let end = Math.min(totalPages, start + windowSize - 1);
        start = Math.max(1, end - windowSize + 1);

        for (let p = start; p <= end; p++) {
            addBtn(p, String(p), p === page, false);
        }

        addBtn(page + 1, '›', false, page >= totalPages);

        container.innerHTML = `
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="text-xs opacity-60">${from}–${to} din ${total}</div>
                <div class="flex flex-wrap items-center gap-1">${buttons}</div>
            </div>`;

        container.querySelectorAll('.bpa-page-btn').forEach((btn) => {
            btn.addEventListener('click', () => {
                const next = Number(btn.getAttribute('data-page'));
                if (!Number.isFinite(next) || next < 1 || next > totalPages || next === page) return;
                onPageChange(next);
            });
        });
    }

    /** Normalizează răspuns API listă (array simplu sau envelope paginat). */
    function unwrapList(data) {
        if (Array.isArray(data)) {
            return {
                items: data,
                total: data.length,
                page: 1,
                per_page: data.length || DEFAULT_PER_PAGE,
                total_pages: 1,
            };
        }
        if (data && Array.isArray(data.items)) {
            return {
                items: data.items,
                total: Number(data.total ?? data.items.length),
                page: Number(data.page ?? 1),
                per_page: Number(data.per_page ?? DEFAULT_PER_PAGE),
                total_pages: Number(data.total_pages ?? 1),
            };
        }
        return { items: [], total: 0, page: 1, per_page: DEFAULT_PER_PAGE, total_pages: 1 };
    }

    global.BpaPagination = { render, unwrapList, DEFAULT_PER_PAGE };
})(typeof window !== 'undefined' ? window : globalThis);
