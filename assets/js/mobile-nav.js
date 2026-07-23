(function () {
    'use strict';

    var nav = document.getElementById('mobile-nav');
    var overlay = document.getElementById('mobile-nav-overlay');
    if (nav && nav.parentNode !== document.body) {
        document.body.appendChild(nav);
    }
    if (overlay && overlay.parentNode !== document.body) {
        document.body.appendChild(overlay);
    }

    const toggle = document.getElementById('mobile-nav-toggle');
    const closeBtn = document.getElementById('mobile-nav-close');

    if (!toggle || !nav || !overlay) {
        return;
    }

    function openNav() {
        document.body.classList.add('mobile-nav-open');
        toggle.setAttribute('aria-expanded', 'true');
        nav.setAttribute('aria-hidden', 'false');
        overlay.hidden = false;
    }

    function closeNav() {
        document.body.classList.remove('mobile-nav-open');
        toggle.setAttribute('aria-expanded', 'false');
        nav.setAttribute('aria-hidden', 'true');
        overlay.hidden = true;
    }

    function isOpen() {
        return document.body.classList.contains('mobile-nav-open');
    }

    toggle.addEventListener('click', function () {
        if (isOpen()) {
            closeNav();
        } else {
            openNav();
        }
    });

    closeBtn?.addEventListener('click', closeNav);
    overlay.addEventListener('click', closeNav);

    nav.querySelectorAll('a').forEach(function (link) {
        link.addEventListener('click', closeNav);
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && isOpen()) {
            closeNav();
        }
    });

    window.addEventListener('resize', function () {
        if (window.innerWidth > 1100 && isOpen()) {
            closeNav();
        }
    });
})();
