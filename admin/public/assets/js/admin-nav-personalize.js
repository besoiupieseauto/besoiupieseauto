/**
 * Personalizare meniu lateral per-utilizator.
 * Ascunde / reordonează grupuri (secțiuni) și item-uri; persistă prin settings_endpoint.php.
 * Preferințele se aplică pe server (AdminNavJsonRegistry::buildTree) la următoarea randare.
 */
(function () {
  'use strict';

  var API_URLS = [
    '/admin/api/settings_endpoint.php',
    '/admin/public/api/settings_endpoint.php'
  ];

  var nav = document.querySelector('.side-menu__nav');
  if (!nav) {
    return;
  }
  var list = nav.querySelector('ul.scrollable') || nav.querySelector('ul');
  if (!list) {
    return;
  }

  function csrfToken() {
    if (window.BESOIU_ADMIN_CSRF) {
      return window.BESOIU_ADMIN_CSRF;
    }
    var meta = document.querySelector('meta[name="admin-csrf"]');
    if (meta && meta.content) {
      return meta.content;
    }
    var el = document.querySelector('[data-csrf]');
    return el ? el.getAttribute('data-csrf') : '';
  }

  function post(action, body) {
    var headers = { 'Content-Type': 'application/json' };
    var csrf = csrfToken();
    if (csrf) {
      headers['X-Admin-CSRF'] = csrf;
    }
    var payload = JSON.stringify(Object.assign({ action: action }, body || {}));
    var attempt = function (i) {
      if (i >= API_URLS.length) {
        return Promise.reject(new Error('Endpoint indisponibil.'));
      }
      return fetch(API_URLS[i], {
        method: 'POST',
        credentials: 'same-origin',
        headers: headers,
        body: payload
      }).then(function (r) {
        if (r.status === 404) {
          return attempt(i + 1);
        }
        return r.json();
      }).catch(function () {
        return attempt(i + 1);
      });
    };
    return attempt(0);
  }

  function injectStyles() {
    if (document.getElementById('bpa-nav-personalize-css')) {
      return;
    }
    var css =
      '.bpa-nav-personalize-bar{display:flex;gap:6px;align-items:center;justify-content:space-between;margin:4px 0 8px;padding:0 4px;}' +
      '.bpa-nav-pz-btn{cursor:pointer;border:0;border-radius:8px;font-size:11px;line-height:1;padding:6px 8px;background:rgba(255,255,255,.10);color:inherit;opacity:.85;}' +
      '.bpa-nav-pz-btn:hover{opacity:1;}' +
      '.bpa-nav-pz-btn.is-on{background:#0d9488;color:#fff;opacity:1;}' +
      '.side-menu__nav.bpa-nav-editing .side-menu__link{padding-right:78px;}' +
      '.bpa-nav-tools{position:absolute;top:50%;right:6px;transform:translateY(-50%);display:none;gap:2px;z-index:5;}' +
      '.side-menu__nav.bpa-nav-editing li[data-nav-item-id]{position:relative;}' +
      '.side-menu__nav.bpa-nav-editing li[data-nav-item-id] .bpa-nav-tools{display:flex;}' +
      '.bpa-nav-tool{cursor:pointer;border:0;border-radius:6px;width:22px;height:22px;font-size:12px;line-height:1;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,.25);color:#fff;opacity:.75;}' +
      '.bpa-nav-tool:hover{opacity:1;background:rgba(0,0,0,.45);}';
    var style = document.createElement('style');
    style.id = 'bpa-nav-personalize-css';
    style.textContent = css;
    document.head.appendChild(style);
  }

  function itemLis() {
    return Array.prototype.slice.call(list.querySelectorAll('li[data-nav-item-id]'));
  }

  function currentSectionOrder() {
    var order = [];
    itemLis().forEach(function (li) {
      var sk = li.getAttribute('data-nav-section-key') || '';
      if (sk && order.indexOf(sk) === -1) {
        order.push(sk);
      }
    });
    return order;
  }

  function refreshGroupLabels() {
    var labels = list.querySelectorAll('.side-menu__group-label');
    Array.prototype.forEach.call(labels, function (label) {
      var next = label.nextElementSibling;
      var anyVisible = false;
      while (next && !next.classList.contains('side-menu__group-label')) {
        if (next.style.display !== 'none') {
          anyVisible = true;
          break;
        }
        next = next.nextElementSibling;
      }
      label.style.display = anyVisible ? '' : 'none';
    });
  }

  function saveOrder() {
    post('nav_pref_reorder', { section_order: currentSectionOrder() });
  }

  function moveLi(li, dir) {
    if (dir < 0) {
      var prev = li.previousElementSibling;
      while (prev && prev.classList.contains('side-menu__group-label')) {
        prev = prev.previousElementSibling;
      }
      if (prev) {
        list.insertBefore(li, prev);
      }
    } else {
      var next = li.nextElementSibling;
      while (next && next.classList.contains('side-menu__group-label')) {
        next = next.nextElementSibling;
      }
      if (next) {
        list.insertBefore(next, li);
      }
    }
    refreshGroupLabels();
    saveOrder();
  }

  function hideItem(li) {
    var itemId = li.getAttribute('data-nav-item-id') || '';
    if (!itemId) {
      return;
    }
    li.style.display = 'none';
    refreshGroupLabels();
    post('nav_pref_hide_item', { item_id: itemId });
  }

  function buildTools(li) {
    if (li.querySelector(':scope > .bpa-nav-tools')) {
      return;
    }
    var tools = document.createElement('span');
    tools.className = 'bpa-nav-tools';

    var up = document.createElement('button');
    up.type = 'button';
    up.className = 'bpa-nav-tool';
    up.title = 'Mută mai sus';
    up.textContent = '\u2191';
    up.addEventListener('click', function (e) {
      e.preventDefault();
      e.stopPropagation();
      moveLi(li, -1);
    });

    var down = document.createElement('button');
    down.type = 'button';
    down.className = 'bpa-nav-tool';
    down.title = 'Mută mai jos';
    down.textContent = '\u2193';
    down.addEventListener('click', function (e) {
      e.preventDefault();
      e.stopPropagation();
      moveLi(li, 1);
    });

    var hide = document.createElement('button');
    hide.type = 'button';
    hide.className = 'bpa-nav-tool';
    hide.title = 'Ascunde din meniul meu';
    hide.textContent = '\u2715';
    hide.addEventListener('click', function (e) {
      e.preventDefault();
      e.stopPropagation();
      hideItem(li);
    });

    tools.appendChild(up);
    tools.appendChild(down);
    tools.appendChild(hide);
    li.appendChild(tools);
  }

  function ensureTools() {
    itemLis().forEach(buildTools);
  }

  function setEditing(on) {
    if (on) {
      ensureTools();
      nav.classList.add('bpa-nav-editing');
    } else {
      nav.classList.remove('bpa-nav-editing');
    }
  }

  function resetPrefs() {
    if (!window.confirm('Readuci meniul la varianta implicită (afișezi tot, ordine standard)?')) {
      return;
    }
    post('nav_pref_reset', {}).then(function () {
      window.location.reload();
    });
  }

  function buildBar() {
    var bar = document.createElement('li');
    bar.className = 'bpa-nav-personalize-bar';
    bar.setAttribute('data-besoiu-global-nav', '1');

    var toggle = document.createElement('button');
    toggle.type = 'button';
    toggle.className = 'bpa-nav-pz-btn';
    toggle.textContent = 'Personalizează';

    var reset = document.createElement('button');
    reset.type = 'button';
    reset.className = 'bpa-nav-pz-btn';
    reset.textContent = 'Reset';
    reset.title = 'Readu meniul la varianta implicită';
    reset.addEventListener('click', resetPrefs);

    var editing = false;
    toggle.addEventListener('click', function () {
      editing = !editing;
      toggle.classList.toggle('is-on', editing);
      toggle.textContent = editing ? 'Gata' : 'Personalizează';
      setEditing(editing);
    });

    bar.appendChild(toggle);
    bar.appendChild(reset);
    list.insertBefore(bar, list.firstChild);
  }

  injectStyles();
  buildBar();
})();
