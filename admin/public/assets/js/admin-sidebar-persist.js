/**
 * Meniu lateral — permanent deschis; blochează hover Rubick care ascunde contentul.
 */
(function () {
  'use strict';

  var STORAGE_KEY = 'compactMenu';
  var DESKTOP_MQ = window.matchMedia('(min-width: 1280px)');

  function isDesktop() {
    return DESKTOP_MQ.matches;
  }

  function forceSidebarExpanded() {
    if (!isDesktop()) {
      return;
    }
    try {
      localStorage.setItem(STORAGE_KEY, 'false');
    } catch (e) {
      /* ignore */
    }

    var menu = document.querySelector('.side-menu');
    var content = document.querySelector('.content');
    var scroll = document.querySelector('.content__scroll-area');

    if (menu) {
      menu.classList.remove('side-menu--collapsed', 'side-menu--on-hover');
    }
    if (content) {
      content.classList.remove('content--compact');
    }
    if (scroll) {
      scroll.classList.remove('-ml-[165px]');
      scroll.style.marginLeft = '0';
    }
  }

  function lockSidebarNoOverlay() {
    if (!isDesktop()) {
      return;
    }
    var menu = document.querySelector('.side-menu');
    if (!menu) {
      return;
    }
    menu.classList.remove('side-menu--on-hover', 'side-menu--collapsed');
  }

  function unbindRubickSidebarHover() {
    if (!window.$) {
      return;
    }
    var $menu = window.$('.side-menu');
    if ($menu.length) {
      $menu.off('mouseover mouseleave');
    }
  }

  function neutralizeRubickResize() {
    window.onresize = function () {
      if (!isDesktop()) {
        return;
      }
      try {
        localStorage.setItem(STORAGE_KEY, 'false');
      } catch (e) {
        /* ignore */
      }
      forceSidebarExpanded();
      lockSidebarNoOverlay();
    };
  }

  function bindSidebarGuards() {
    if (!isDesktop()) {
      return;
    }
    var menu = document.querySelector('.side-menu');
    var scroll = document.querySelector('.content__scroll-area');
    if (!menu || menu.dataset.besoiuSidebarGuard === '1') {
      return;
    }
    menu.dataset.besoiuSidebarGuard = '1';

    menu.addEventListener(
      'mouseenter',
      function () {
        if (!isDesktop()) {
          return;
        }
        lockSidebarNoOverlay();
        forceSidebarExpanded();
      },
      true
    );
    menu.addEventListener(
      'mouseleave',
      function () {
        if (!isDesktop()) {
          return;
        }
        lockSidebarNoOverlay();
        forceSidebarExpanded();
      },
      true
    );

    if (window.MutationObserver) {
      var observer = new MutationObserver(function () {
        if (!isDesktop()) {
          return;
        }
        if (
          menu.classList.contains('side-menu--on-hover') ||
          menu.classList.contains('side-menu--collapsed')
        ) {
          forceSidebarExpanded();
          lockSidebarNoOverlay();
        }
        if (scroll && scroll.classList.contains('-ml-[165px]')) {
          scroll.classList.remove('-ml-[165px]');
          scroll.style.marginLeft = '0';
        }
      });
      observer.observe(menu, { attributes: true, attributeFilter: ['class'] });
      if (scroll) {
        observer.observe(scroll, { attributes: true, attributeFilter: ['class', 'style'] });
      }
    }
  }

  function bindDesktopMediaQuery() {
    if (!DESKTOP_MQ.addEventListener) {
      return;
    }
    DESKTOP_MQ.addEventListener('change', function () {
      if (isDesktop()) {
        init();
      }
    });
  }

  function init() {
    if (!isDesktop()) {
      return;
    }
    try {
      localStorage.setItem(STORAGE_KEY, 'false');
    } catch (e) {
      /* ignore */
    }
    forceSidebarExpanded();
    lockSidebarNoOverlay();
    unbindRubickSidebarHover();
    neutralizeRubickResize();
    bindSidebarGuards();
  }

  try {
    if (isDesktop()) {
      localStorage.setItem(STORAGE_KEY, 'false');
    }
  } catch (e) {
    /* ignore */
  }

  init();
  bindDesktopMediaQuery();
  document.addEventListener('DOMContentLoaded', init);
  window.addEventListener('load', init);

  var attempts = 0;
  var timer = window.setInterval(function () {
    if (!isDesktop()) {
      window.clearInterval(timer);
      return;
    }
    forceSidebarExpanded();
    lockSidebarNoOverlay();
    attempts += 1;
    if (attempts >= 8) {
      window.clearInterval(timer);
    }
  }, 250);
})();
