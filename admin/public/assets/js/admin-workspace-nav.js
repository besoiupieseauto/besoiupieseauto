/**
 * Filtrare meniu lateral după workspace activ (mapare pe URL).
 */
(function () {
  'use strict';

  var ctx = window.BESOIU_WORKSPACE_CTX;
  if (!ctx || !ctx.workspace) {
    return;
  }

  var activeWs = ctx.workspace;
  var pathMap = ctx.pathToWorkspace || {};

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
    var href = link.getAttribute('href') || '';
    var ws = workspaceForHref(href);
    if (!ws || ws === activeWs) {
      showLi(li);
    } else {
      hideLi(li);
    }
  }

  // Iteme simple (link direct)
  document.querySelectorAll('.side-menu__nav li').forEach(function (li) {
    if (li.classList.contains('side-menu__group-label')) {
      return;
    }
  });

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

  // Etichete grup
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
