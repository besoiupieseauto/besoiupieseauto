/**
 * Popup cont utilizator — sidebar jos (click, nu hover).
 */
(function () {
  'use strict';

  function refreshIcons(root) {
    if (window.lucide && typeof window.lucide.createIcons === 'function') {
      window.lucide.createIcons({
        icons: window.lucide.icons,
        attrs: { 'stroke-width': 1.75 },
        nameAttr: 'data-lucide',
        root: root || document
      });
    }
  }

  function init() {
    var trigger = document.getElementById('besoiu-account-trigger');
    var popup = document.getElementById('besoiu-account-popup');
    var backdrop = document.getElementById('besoiu-account-backdrop');
    var closeBtn = document.getElementById('besoiu-account-close');

    if (!trigger || !popup || !backdrop) {
      return;
    }

    if (trigger.dataset.besoiuAccountBound === '1') {
      return;
    }
    trigger.dataset.besoiuAccountBound = '1';

    function isOpen() {
      return trigger.getAttribute('aria-expanded') === 'true';
    }

    function openMenu() {
      trigger.setAttribute('aria-expanded', 'true');
      popup.hidden = false;
      backdrop.hidden = false;
      refreshIcons(popup);
      var firstLink = popup.querySelector('.besoiu-account-popup__link, .besoiu-account-popup__logout');
      if (firstLink) {
        window.setTimeout(function () {
          firstLink.focus();
        }, 0);
      }
    }

    function closeMenu() {
      trigger.setAttribute('aria-expanded', 'false');
      popup.hidden = true;
      backdrop.hidden = true;
      trigger.focus();
    }

    function toggleMenu() {
      if (isOpen()) {
        closeMenu();
      } else {
        openMenu();
      }
    }

    trigger.addEventListener('click', function (e) {
      e.preventDefault();
      e.stopPropagation();
      toggleMenu();
    });

    closeBtn && closeBtn.addEventListener('click', function (e) {
      e.preventDefault();
      closeMenu();
    });

    backdrop.addEventListener('click', closeMenu);

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && isOpen()) {
        closeMenu();
      }
    });

    document.addEventListener('click', function (e) {
      if (!isOpen()) {
        return;
      }
      if (popup.contains(e.target) || trigger.contains(e.target)) {
        return;
      }
      closeMenu();
    });
  }

  init();
  document.addEventListener('DOMContentLoaded', init);
  window.addEventListener('load', init);
})();
