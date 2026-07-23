/**
 * Dropdown schimbare departament — topbar admin.
 */
(function () {
  'use strict';

  function closeAll() {
    document.querySelectorAll('[data-besoiu-ws-switch]').forEach(function (root) {
      var btn = root.querySelector('.besoiu-ws-switch__trigger');
      var menu = root.querySelector('.besoiu-ws-switch__menu');
      if (btn) {
        btn.setAttribute('aria-expanded', 'false');
      }
      if (menu) {
        menu.hidden = true;
      }
      root.classList.remove('is-open');
    });
  }

  document.querySelectorAll('[data-besoiu-ws-switch]').forEach(function (root) {
    var btn = root.querySelector('.besoiu-ws-switch__trigger');
    var menu = root.querySelector('.besoiu-ws-switch__menu');
    if (!btn || !menu) {
      return;
    }

    btn.addEventListener('click', function (e) {
      e.preventDefault();
      e.stopPropagation();
      var open = root.classList.contains('is-open');
      closeAll();
      if (!open) {
        root.classList.add('is-open');
        menu.hidden = false;
        btn.setAttribute('aria-expanded', 'true');
      }
    });

    menu.addEventListener('click', function (e) {
      e.stopPropagation();
    });
  });

  document.addEventListener('click', function () {
    closeAll();
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      closeAll();
    }
  });
})();
