/**
 * Meniu lateral Besoiu — submeniuri (fără Rubick / SimpleBar pe sidebar).
 */
(function () {
  'use strict';

  var BOUND = false;

  function submenuUl(link) {
    if (!link || !link.parentElement) {
      return null;
    }
    var next = link.nextElementSibling;
    if (next && next.tagName === 'UL') {
      return next;
    }
    var kids = link.parentElement.children;
    var i;
    for (i = 0; i < kids.length; i += 1) {
      if (kids[i].tagName === 'UL') {
        return kids[i];
      }
    }
    return null;
  }

  function isToggleLink(link) {
    if (!link || !link.classList.contains('side-menu__link')) {
      return false;
    }
    if (link.getAttribute('data-submenu-toggle') === '1') {
      return true;
    }
    return submenuUl(link) !== null;
  }

  function isOpen(ul) {
    return ul.getAttribute('data-besoiu-open') === '1';
  }

  function clearInline(ul) {
    ul.style.removeProperty('height');
    ul.style.removeProperty('overflow');
    ul.style.removeProperty('padding-top');
    ul.style.removeProperty('padding-bottom');
    ul.style.removeProperty('margin-top');
    ul.style.removeProperty('margin-bottom');
    ul.style.removeProperty('transition-property');
    ul.style.removeProperty('transition-duration');
    ul.style.removeProperty('display');
    ul.style.removeProperty('visibility');
  }

  function setOpen(link, ul, open) {
    clearInline(ul);
    if (open) {
      ul.setAttribute('data-besoiu-open', '1');
      ul.classList.remove('hidden');
      link.classList.add('side-menu__link--open');
      link.setAttribute('aria-expanded', 'true');
    } else {
      ul.setAttribute('data-besoiu-open', '0');
      ul.classList.add('hidden');
      link.classList.remove('side-menu__link--open');
      link.setAttribute('aria-expanded', 'false');
    }
  }

  function closeOtherSubmenus(exceptUl) {
    document.querySelectorAll('.side-menu li > ul:not(.scrollable)[data-besoiu-open="1"]').forEach(function (ul) {
      if (ul === exceptUl) {
        return;
      }
      var li = ul.parentElement;
      var link = li && li.querySelector(':scope > a.side-menu__link[data-submenu-toggle="1"]');
      if (link) {
        setOpen(link, ul, false);
      }
    });
  }

  function toggle(link) {
    var ul = submenuUl(link);
    if (!ul) {
      return false;
    }
    var willOpen = !isOpen(ul);
    if (willOpen) {
      closeOtherSubmenus(ul);
    }
    setOpen(link, ul, willOpen);
    return true;
  }

  /** Același map ca AdminUrl::PAGE_ALIASES (slug URL → pagină canonică). */
  var PAGE_ALIASES = {
    suppliers: 'furnizori',
    supplier: 'furnizori',
    furnizori: 'furnizori',
    product: 'produse',
    products: 'produse',
    produse: 'produse',
    orders: 'comenzi',
    order: 'comenzi',
    comenzi: 'comenzi'
  };

  function normalizePath(path) {
    var p = String(path || '').split('?')[0].replace(/\/$/, '');
    return p === '' ? '/' : p;
  }

  function canonicalAdminPath(pathname) {
    var parts = normalizePath(pathname).split('/').filter(Boolean);
    if (parts[0] === 'admin' && parts[1] && PAGE_ALIASES[parts[1]]) {
      parts[1] = PAGE_ALIASES[parts[1]];
    }
    return '/' + parts.join('/');
  }

  function parseNavHref(href) {
    if (!href || href === '#' || href.indexOf('javascript:') === 0) {
      return null;
    }
    try {
      var url = new URL(href, window.location.origin);
      return {
        path: canonicalAdminPath(url.pathname),
        params: new URLSearchParams(url.search)
      };
    } catch (err) {
      return null;
    }
  }

  function pathMatches(linkPath, currentPath) {
    return linkPath === currentPath
      || (linkPath !== '/' && currentPath.indexOf(linkPath + '/') === 0);
  }

  function queryParamsMatch(linkParams, currentParams) {
    var keys = [];
    linkParams.forEach(function (_, key) {
      keys.push(key);
    });
    if (!keys.length) {
      return true;
    }
    var i;
    for (i = 0; i < keys.length; i += 1) {
      if (linkParams.get(keys[i]) !== currentParams.get(keys[i])) {
        return false;
      }
    }
    return true;
  }

  function openParentSubmenu(link) {
    var li = link.parentElement;
    var parentUl = li && li.parentElement;
    if (!parentUl || parentUl.classList.contains('scrollable')) {
      return;
    }
    var parentLink = parentUl.previousElementSibling;
    if (parentLink && isToggleLink(parentLink)) {
      setOpen(parentLink, parentUl, true);
    }
  }

  function markActiveRoutes() {
    var currentPath = canonicalAdminPath(window.location.pathname);
    var currentParams = new URLSearchParams(window.location.search);
    var currentHasQuery = currentParams.toString().length > 0;
    var candidates = [];

    document.querySelectorAll('.side-menu__link[href]').forEach(function (link) {
      link.classList.remove('side-menu__link--active');
    });

    document.querySelectorAll('.side-menu__link[href]').forEach(function (link) {
      var parsed = parseNavHref(link.getAttribute('href'));
      if (!parsed || !pathMatches(parsed.path, currentPath)) {
        return;
      }
      if (!queryParamsMatch(parsed.params, currentParams)) {
        return;
      }

      var specificity = parsed.params.toString().length > 0 ? 2 : 1;
      if (parsed.path !== currentPath) {
        specificity -= 0.25;
      }

      candidates.push({
        link: link,
        specificity: specificity,
        hasQuery: parsed.params.toString().length > 0
      });
    });

    if (!candidates.length) {
      return;
    }

    if (currentHasQuery) {
      var withQuery = candidates.filter(function (c) { return c.hasQuery; });
      if (withQuery.length) {
        candidates = withQuery;
      }
    } else {
      candidates = candidates.filter(function (c) { return !c.hasQuery; });
    }

    if (!candidates.length) {
      return;
    }

    var maxSpec = candidates[0].specificity;
    var i;
    for (i = 1; i < candidates.length; i += 1) {
      if (candidates[i].specificity > maxSpec) {
        maxSpec = candidates[i].specificity;
      }
    }

    candidates.forEach(function (c) {
      if (c.specificity !== maxSpec) {
        return;
      }
      c.link.classList.add('side-menu__link--active');
      openParentSubmenu(c.link);
    });
  }

  function onMenuActivate(e) {
    if (!e.target || !e.target.closest) {
      return;
    }

    var menuRoot = e.target.closest('.side-menu');
    if (!menuRoot) {
      return;
    }

    var link = e.target.closest('a.side-menu__link');
    if (!link || !isToggleLink(link)) {
      return;
    }

    if (!toggle(link)) {
      return;
    }

    e.preventDefault();
    e.stopPropagation();
    e.stopImmediatePropagation();
  }

  function initSubmenuState() {
    document.querySelectorAll('.side-menu li > ul:not(.scrollable)').forEach(function (ul) {
      if (!ul.hasAttribute('data-besoiu-open')) {
        ul.setAttribute('data-besoiu-open', '0');
      }
      if (ul.getAttribute('data-besoiu-open') !== '1') {
        ul.classList.add('hidden');
      }
    });
  }

  function neutralizeRubickMenuClicks() {
    if (!window.$) {
      return;
    }
    window.$('.scrollable').find('li').children('a').off();
  }

  function bindMenu() {
    if (BOUND) {
      return;
    }
    BOUND = true;
    document.addEventListener('click', onMenuActivate, true);
    document.addEventListener('keydown', function (e) {
      if (e.key !== 'Enter' && e.key !== ' ') {
        return;
      }
      onMenuActivate(e);
    }, true);
  }

  function boot() {
    neutralizeRubickMenuClicks();
    initSubmenuState();
    markActiveRoutes();
    bindMenu();
    if (window.lucide && typeof window.lucide.createIcons === 'function') {
      window.lucide.createIcons();
    }
  }

  boot();
  document.addEventListener('DOMContentLoaded', boot);
  window.addEventListener('load', boot);

  var tries = 0;
  var timer = window.setInterval(function () {
    neutralizeRubickMenuClicks();
    tries += 1;
    if (tries >= 6) {
      window.clearInterval(timer);
    }
  }, 250);
})();
