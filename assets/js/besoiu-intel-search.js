/**
 * Besoiu — hybrid search widget (header) → POST /api/search
 */
(function () {
  'use strict';

  if (window.__besoiuIntelSearch) return;
  window.__besoiuIntelSearch = true;

  var ENDPOINT = '/api/search';
  var MIN_CHARS = 2;
  var DEBOUNCE_MS = 300;
  var LIMIT = 8;
  var DEFAULT_IMG = '/assets/img/logo.png';

  var input = null;
  var panel = null;
  var wrap = null;
  var timer = null;
  var abortCtrl = null;
  var activeIndex = -1;
  var lastHits = [];
  var lastQuery = '';

  function enabled() {
    var wrap = document.getElementById('besoiu-search-wrap');
    if (wrap && wrap.getAttribute('data-intel-search') === '0') return false;
    return true;
  }

  function esc(s) {
    var d = document.createElement('div');
    d.textContent = s == null ? '' : String(s);
    return d.innerHTML;
  }

  function formatPrice(val) {
    var n = parseFloat(val);
    if (!isFinite(n) || n <= 0) return 'La cerere';
    return n.toFixed(2).replace('.', ',') + ' RON';
  }

  function trackClick(hit, query) {
    var id = hit.randomn_id || hit.product_id || '';
    if (!id) return;
    if (window.BesoiuIntel && typeof window.BesoiuIntel.track === 'function') {
      window.BesoiuIntel.track('search_result_click', 'product', id, {
        subject: hit.name || '',
        sku: hit.oem || hit.code || '',
        query: query,
        source: 'intel_search_widget',
        rank: hit._rank || 0
      });
    }
  }

  function productUrl(hit) {
    if (hit.url) return hit.url;
    var id = hit.randomn_id || hit.product_id || '';
    return id ? '/produs?id=' + encodeURIComponent(id) : '/catalog';
  }

  function hidePanel() {
    if (!panel) return;
    panel.hidden = true;
    panel.innerHTML = '';
    activeIndex = -1;
    lastHits = [];
  }

  function showPanel(html) {
    if (!panel) return;
    panel.innerHTML = html;
    panel.hidden = false;
  }

  function renderHits(hits, query) {
    if (!hits.length) {
      showPanel('<div class="besoiu-intel-search__empty">Niciun rezultat pentru «' + esc(query) + '»</div>' +
        '<a class="besoiu-intel-search__all" href="/catalog?q=' + encodeURIComponent(query) + '">Caută în catalog</a>');
      return;
    }

    var html = '<ul class="besoiu-intel-search__list" role="listbox">';
    hits.forEach(function (hit, idx) {
      hit._rank = idx + 1;
      var img = hit.image || DEFAULT_IMG;
      var meta = [];
      if (hit.brand) meta.push(hit.brand);
      if (hit.oem || hit.code) meta.push(hit.oem || hit.code);
      html += '<li class="besoiu-intel-search__item' + (idx === activeIndex ? ' is-active' : '') + '" role="option" data-idx="' + idx + '" aria-selected="' + (idx === activeIndex ? 'true' : 'false') + '">' +
        '<img class="besoiu-intel-search__thumb" src="' + esc(img) + '" alt="" loading="lazy" width="48" height="48">' +
        '<div class="besoiu-intel-search__body">' +
        '<div class="besoiu-intel-search__name">' + esc((hit.name || '').slice(0, 72)) + '</div>' +
        (meta.length ? '<div class="besoiu-intel-search__meta">' + esc(meta.join(' · ')) + '</div>' : '') +
        '</div>' +
        '<div class="besoiu-intel-search__price">' + esc(formatPrice(hit.price_ron)) + '</div>' +
        '</li>';
    });
    html += '</ul>';
    html += '<a class="besoiu-intel-search__all" href="/catalog?q=' + encodeURIComponent(query) + '">Vezi toate în catalog →</a>';
    showPanel(html);
  }

  function fetchHits(query) {
    if (abortCtrl) abortCtrl.abort();
    abortCtrl = typeof AbortController !== 'undefined' ? new AbortController() : null;
    lastQuery = query;

    return fetch(ENDPOINT, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({ query: query, limit: LIMIT, popularity_weight: 0.15 }),
      signal: abortCtrl ? abortCtrl.signal : undefined
    }).then(function (r) {
      return r.json();
    }).then(function (json) {
      if (query !== lastQuery) return;
      lastHits = Array.isArray(json.hits) ? json.hits : [];
      activeIndex = lastHits.length ? 0 : -1;
      renderHits(lastHits, query);
    }).catch(function (err) {
      if (err && err.name === 'AbortError') return;
      hidePanel();
    });
  }

  function scheduleSearch() {
    if (!input) return;
    var q = (input.value || '').trim();
    if (timer) clearTimeout(timer);
    if (q.length < MIN_CHARS) {
      hidePanel();
      return;
    }
    timer = setTimeout(function () {
      timer = null;
      fetchHits(q);
    }, DEBOUNCE_MS);
  }

  function navigateHit(hit) {
    if (!hit) return;
    trackClick(hit, lastQuery);
    hidePanel();
    window.location.href = productUrl(hit);
  }

  function bindPanelClicks() {
    if (!panel) return;
    panel.addEventListener('mousedown', function (e) {
      e.preventDefault();
    });
    panel.addEventListener('click', function (e) {
      var item = e.target.closest('.besoiu-intel-search__item');
      if (!item) return;
      var idx = parseInt(item.getAttribute('data-idx') || '-1', 10);
      if (idx >= 0 && lastHits[idx]) navigateHit(lastHits[idx]);
    });
  }

  function bindInput() {
    if (!input) return;
    input.setAttribute('autocomplete', 'off');
    input.setAttribute('aria-autocomplete', 'list');
    input.setAttribute('aria-controls', 'besoiu-intel-search-panel');
    input.setAttribute('aria-expanded', 'false');

    input.addEventListener('input', scheduleSearch);
    input.addEventListener('focus', function () {
      if ((input.value || '').trim().length >= MIN_CHARS && lastHits.length) {
        panel.hidden = false;
      }
    });
    input.addEventListener('keydown', function (e) {
      if (panel.hidden || !lastHits.length) return;
      if (e.key === 'ArrowDown') {
        e.preventDefault();
        activeIndex = Math.min(lastHits.length - 1, activeIndex + 1);
        renderHits(lastHits, lastQuery);
      } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        activeIndex = Math.max(0, activeIndex - 1);
        renderHits(lastHits, lastQuery);
      } else if (e.key === 'Enter' && activeIndex >= 0 && lastHits[activeIndex]) {
        e.preventDefault();
        e.stopPropagation();
        navigateHit(lastHits[activeIndex]);
      } else if (e.key === 'Escape') {
        hidePanel();
      }
    });
  }

  function bindGlobal() {
    document.addEventListener('click', function (e) {
      if (!wrap || wrap.contains(e.target)) return;
      hidePanel();
    });
    document.getElementById('_home-search-btn')?.addEventListener('click', hidePanel);
  }

  function init() {
    if (!enabled()) return;
    input = document.getElementById('_home-product-name');
    wrap = document.getElementById('besoiu-search-wrap');
    if (!input || !wrap) return;

    panel = document.getElementById('besoiu-intel-search-panel');
    if (!panel) {
      panel = document.createElement('div');
      panel.id = 'besoiu-intel-search-panel';
      panel.className = 'besoiu-intel-search';
      panel.hidden = true;
      panel.setAttribute('role', 'listbox');
      wrap.appendChild(panel);
    }

    bindInput();
    bindPanelClicks();
    bindGlobal();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
