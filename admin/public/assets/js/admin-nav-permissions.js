(function () {
    'use strict';
    const ctx = window.BESOIU_NAV_CTX;
    if (!ctx || ctx.role === 'super_ambassador' || !Array.isArray(ctx.permissions)) {
        return;
    }

    const perms = new Set(ctx.permissions);
    const features = ctx.features || {};

    function pathAllowed(href) {
        if (!href || href.indexOf('javascript') === 0 || href === '#') {
            return true;
        }
        let path = href.split('?')[0].split('#')[0];
        if (!path.startsWith('/')) {
            return true;
        }
        path = path.replace(/\/+$/, '') || '/';

        const always = ['/admin/login', '/admin/logout', '/admin/settings', '/admin/dashboard'];
        if (always.some(p => path === p || path.startsWith(p + '/'))) {
            return true;
        }

        for (const [key, meta] of Object.entries(features)) {
            if (!perms.has(key)) {
                continue;
            }
            for (const prefix of meta.urls || []) {
                const p = prefix.replace(/\/+$/, '');
                if (path === p || path.startsWith(p + '/')) {
                    return true;
                }
            }
        }

        // fallback legacy module keys
        const modules = ctx.modules || {};
        for (const [key, meta] of Object.entries(modules)) {
            if (!perms.has(key)) {
                continue;
            }
            for (const prefix of meta.urls || []) {
                const p = prefix.replace(/\/+$/, '');
                if (path === p || path.startsWith(p + '/')) {
                    return true;
                }
            }
        }

        return false;
    }

    function hideEmptySubmenus() {
        document.querySelectorAll('.side-menu__nav li').forEach(li => {
            const sub = li.querySelector(':scope > ul');
            if (!sub) {
                return;
            }
            const visible = sub.querySelectorAll('li:not([hidden])');
            if (visible.length === 0) {
                li.hidden = true;
            }
        });
    }

    document.querySelectorAll('.side-menu__nav a.side-menu__link[href^="/admin"]').forEach(link => {
        const href = link.getAttribute('href') || '';
        if (!pathAllowed(href)) {
            const li = link.closest('li');
            if (li) {
                li.hidden = true;
            }
        }
    });

    document.querySelectorAll('.side-menu__group-label').forEach(label => {
        let sib = label.nextElementSibling;
        let anyVisible = false;
        while (sib && !sib.classList.contains('side-menu__group-label')) {
            if (sib.tagName === 'LI' && !sib.hidden) {
                anyVisible = true;
                break;
            }
            sib = sib.nextElementSibling;
        }
        if (!anyVisible) {
            label.hidden = true;
        }
    });

    hideEmptySubmenus();
})();
