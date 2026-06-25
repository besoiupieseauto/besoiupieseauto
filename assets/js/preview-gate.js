/**
 * Preview gate — persistă cheia de acces în localStorage și reîncarcă cu ?key= dacă lipsește cookie-ul.
 * Anti-loop: max 2 redirecturi / 60s; cheie invalidă → șterge storage și oprește.
 */
(function () {
    'use strict';

    var STORAGE_KEY = 'bpa_preview_key';
    var ATTEMPT_KEY = 'bpa_preview_redirect_at';
    var MAX_REDIRECTS = 2;
    var WINDOW_MS = 60000;
    var params = new URLSearchParams(window.location.search);
    var urlKey = params.get('key');
    var invalidKeyPage = document.body && document.body.getAttribute('data-preview-invalid-key') === '1';

    function clearStoredKey() {
        try {
            localStorage.removeItem(STORAGE_KEY);
            sessionStorage.removeItem(ATTEMPT_KEY);
        } catch (e) {
            /* ignore */
        }
    }

    function redirectAttempts() {
        try {
            var raw = sessionStorage.getItem(ATTEMPT_KEY) || '[]';
            var list = JSON.parse(raw);
            return Array.isArray(list) ? list : [];
        } catch (e2) {
            return [];
        }
    }

    function registerRedirectAttempt() {
        var now = Date.now();
        var attempts = redirectAttempts().filter(function (ts) {
            return now - ts < WINDOW_MS;
        });
        attempts.push(now);
        try {
            sessionStorage.setItem(ATTEMPT_KEY, JSON.stringify(attempts));
        } catch (e3) {
            /* ignore */
        }
        return attempts.length;
    }

    if (invalidKeyPage) {
        clearStoredKey();
        return;
    }

    if (urlKey) {
        try {
            localStorage.setItem(STORAGE_KEY, urlKey);
        } catch (e4) {
            /* ignore */
        }
        return;
    }

    var stored = '';
    try {
        stored = localStorage.getItem(STORAGE_KEY) || '';
    } catch (e5) {
        return;
    }

    if (!stored) {
        return;
    }

    var isComingSoon = document.body && document.body.getAttribute('data-preview-page') === '1'
        || document.title.indexOf('În curând') !== -1
        || document.body.textContent.indexOf('Site în lucru') !== -1;

    if (!isComingSoon) {
        return;
    }

    if (registerRedirectAttempt() > MAX_REDIRECTS) {
        clearStoredKey();
        return;
    }

    var next = new URL(window.location.href);
    next.searchParams.set('key', stored);
    window.location.replace(next.toString());
})();
