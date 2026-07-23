/**
 * Filtrare meniu lateral după workspace activ (secțiuni + mapare URL fallback).
 */
(function () {
  'use strict';

  var ctx = window.BESOIU_WORKSPACE_CTX;
  if (!ctx || !ctx.workspace) {
    return;
  }

  // Filtrarea pe workspace se face pe server (fără flash / dublu-hide).
  if (ctx.serverFiltered) {
    return;
  }

  var activeWs = ctx.workspace;
  var pathMap = ctx.pathToWorkspace || {};
  var allowedSections = Array.isArray(ctx.allowedSections) ? ctx.allowedSections : [];

  // Company Settings = vedere completă meniu (tablou de bord central)
  if (activeWs === 'company') {
    return;
  }

  function workspaceForHref(href) {
    if (!href || href.indexOf('javascript:') === 0) {
      return null;
    }
    var path = href.split('?')[0].split('#')[0];
    if (path.indexOf('http') === 0) {
      try {
        path = new URL(path).pathname;
      } catch (e) {
        return null;
      }
    }
    path = path.replace(/\/+$/, '') || '/';

    if (path === '/admin/dashboard' || pathMap[path] === undefined) {
      var best = null;
      var bestLen = 0;
      Object.keys(pathMap).forEach(function (prefix) {
        if (path === prefix || path.indexOf(prefix + '/') === 0) {
          if (prefix.length > bestLen) {
            bestLen = prefix.length;
            best = pathMap[prefix];
          }
        }
      });
      return best;
    }

    return pathMap[path] || null;
  }

  function sectionAllowed(section) {
    if (!allowedSections.length) {
      return true;
    }
    if (!section) {
      return false;
    }
    return allowedSections.indexOf(section) !== -1;
  }

  function hideLi(li) {
    if (!li) {
      return;
    }
    li.style.display = 'none';
    li.setAttribute('data-besoiu-ws-hidden', '1');
  }

  function showLi(li) {
    if (!li) {
      return;
    }
    li.style.display = '';
    li.removeAttribute('data-besoiu-ws-hidden');
  }

  function filterLinkLi(li) {
    var link = li.querySelector(':scope > a.side-menu__link[href]');
    if (!link) {
      return;
    }

    var section = li.getAttribute('data-besoiu-section') || '';

    if (activeWs === 'marketing') {
      if (section === 'dashboard' || section === 'marketing') {
        showLi(li);
      } else {
        hideLi(li);
      }
      return;
    }

    if (link.hasAttribute('data-besoiu-global-nav')) {
      showLi(li);
      return;
    }

    // Filtrare principală: data-besoiu-section vs secțiunile departamentului
    if (allowedSections.length) {
      if (sectionAllowed(section)) {
        showLi(li);
      } else {
        hideLi(li);
      }
      return;
    }

    // Fallback: mapare URL (legacy)
    var href = link.getAttribute('href') || '';
    var ws = workspaceForHref(href);
    if (!ws || ws === activeWs) {
      showLi(li);
    } else {
      hideLi(li);
    }
  }

  document.querySelectorAll('.side-menu__nav li').forEach(function (li) {
    if (li.classList.contains('side-menu__group-label')) {
      return;
    }

    var sub = li.querySelector(':scope > ul');
    if (sub) {
      sub.querySelectorAll(':scope > li').forEach(filterLinkLi);
      var anyChild = sub.querySelector('li:not([data-besoiu-ws-hidden])');
      if (anyChild) {
        showLi(li);
      } else {
        hideLi(li);
      }
      return;
    }

    filterLinkLi(li);
  });

  document.querySelectorAll('.side-menu__group-label').forEach(function (label) {
    var next = label.nextElementSibling;
    var anyVisible = false;
    while (next && !next.classList.contains('side-menu__group-label')) {
      if (next.getAttribute('data-besoiu-ws-hidden') !== '1' && next.style.display !== 'none') {
        anyVisible = true;
        break;
      }
      next = next.nextElementSibling;
    }
    label.style.display = anyVisible ? '' : 'none';
  });
})();
