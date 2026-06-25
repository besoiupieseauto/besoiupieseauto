(function () {
    'use strict';

    var BESOIU_ADMIN = window.BESOIU_ADMIN_CTX === true;

    var TECHNICAL_NEEDLES = [
        'admin',
        'migrare',
        'migrat',
        'migration',
        'rapidapi',
        'tecdoc',
        'psubcategory',
        'pvitrina',
        '.env',
        'eroare intern',
        'sistem intern',
        'sistem extern',
        'intern/extern',
        'internă / externă',
        'operator_message',
        'ruleaza migrarea',
        'rulează migrarea',
        'trimisa in admin',
        'trimisă în admin',
        'http 4',
        'http 5',
        'fatal error',
        'sqlstate'
    ];

    function stripHtml(text) {
        return String(text || '').replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
    }

    function noticeIsTechnical(text) {
        var lower = stripHtml(text).toLowerCase();
        if (lower === '') {
            return false;
        }
        for (var i = 0; i < TECHNICAL_NEEDLES.length; i++) {
            if (lower.indexOf(TECHNICAL_NEEDLES[i]) !== -1) {
                return true;
            }
        }
        return false;
    }

    function publicNotice(text) {
        var raw = stripHtml(text);
        if (raw === '') {
            return '';
        }
        if (BESOIU_ADMIN) {
            return raw;
        }
        return noticeIsTechnical(raw) ? '' : raw;
    }

    function genericClientError() {
        return 'Operațiunea nu a putut fi finalizată. Încearcă din nou.';
    }

    function publicErrorMessage(text) {
        var clean = publicNotice(text);
        if (clean !== '') {
            return clean;
        }
        return BESOIU_ADMIN ? stripHtml(text) : genericClientError();
    }

    function quotaNotice() {
        return BESOIU_ADMIN
            ? 'Catalogul TecDoc (RapidAPI) este limitat. Căutarea continuă în stocul local.'
            : 'Căutarea continuă în stocul local al magazinului.';
    }

    window.besoiuStorefrontNoticeIsTechnical = noticeIsTechnical;
    window.besoiuStorefrontPublicNotice = publicNotice;
    window.besoiuStorefrontPublicError = publicErrorMessage;
    window.besoiuStorefrontQuotaNotice = quotaNotice;

    function markTechnicalNodes(root) {
        if (BESOIU_ADMIN || !root) {
            return;
        }
        root.querySelectorAll('._product-notice, .catalog-notice, .catalog-notices > div').forEach(function (node) {
            var text = stripHtml(node.textContent || node.innerText || '');
            if (noticeIsTechnical(text)) {
                node.classList.add('_product-notice--technical', 'catalog-notice--technical');
                node.setAttribute('aria-hidden', 'true');
                node.hidden = true;
            }
        });
    }

    function observeNoticeContainers() {
        if (BESOIU_ADMIN) {
            return;
        }
        ['catalog-notices', '_product-grid'].forEach(function (id) {
            var el = document.getElementById(id);
            if (!el) {
                return;
            }
            markTechnicalNodes(el);
            if (typeof MutationObserver === 'undefined') {
                return;
            }
            var observer = new MutationObserver(function () {
                markTechnicalNodes(el);
            });
            observer.observe(el, { childList: true, subtree: true, characterData: true });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', observeNoticeContainers);
    } else {
        observeNoticeContainers();
    }
})();
