/* Import cards — vizualizare produse cu filtrare & ultra-filtrare */

(function () {
  'use strict';

  var cfg = window.IMPORT_CARDS_CONFIG || {};
  var API = cfg.apiUrl || '';
  var SCRAPER_API = cfg.scraperApi || '';
  var IMAGE_API = cfg.imageApi || '';

  var allCards = [];
  var batchRunning = false;

  var $ = function (id) { return document.getElementById(id); };

  function apiUrl(action, params) {
    var u = new URL(API, window.location.origin);
    u.searchParams.set('action', action);
    if (params) {
      Object.keys(params).forEach(function (k) {
        u.searchParams.set(k, params[k]);
      });
    }
    return u.toString();
  }

  function escapeHtml(v) {
    return String(v ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function setStatus(el, msg, type) {
    if (!el) return;
    el.hidden = false;
    el.className = 'ic-status ic-status--' + (type || 'info');
    el.textContent = msg;
  }

  function cardHasImage(card) {
    return !!(card.hasImage || card.scrapedImageUrl);
  }

  function getImageSource(card) {
    if (card.scrapedImageUrl) return 'scraped';
    if (card.imageSource === 'autopartner') return 'autopartner';
    if (card.hasImage) return 'poze';
    return 'none';
  }

  function getProductImageUrl(card) {
    if (card.scrapedImageUrl && SCRAPER_API) {
      var u = new URL(SCRAPER_API);
      u.searchParams.set('view', 'image_proxy');
      u.searchParams.set('url', card.scrapedImageUrl);
      return u.toString();
    }
    if (!IMAGE_API) return '';
    var url = new URL(IMAGE_API);
    if (card.imageSource === 'autopartner' && card.autopartnerCode) {
      url.searchParams.set('source', 'autopartner');
      url.searchParams.set('code', card.autopartnerCode);
    } else if (card.ttcArtId) {
      url.searchParams.set('source', 'poze');
      url.searchParams.set('brand', card.brand);
      url.searchParams.set('id', card.ttcArtId);
    } else {
      return '';
    }
    return url.toString();
  }

  function getFilteredCards() {
    var imageFilter = $('ic-filter-image')?.value || 'all';
    var brandFilter = ($('ic-filter-brand')?.value || '').toUpperCase();
    var supplierFilter = ($('ic-filter-supplier')?.value || '').toUpperCase();
    var search = ($('ic-filter-search')?.value || '').toLowerCase().trim();
    var priceMin = parseFloat($('ic-filter-price-min')?.value) || 0;
    var priceMax = parseFloat($('ic-filter-price-max')?.value);
    var compatMin = parseInt($('ic-filter-compat-min')?.value, 10) || 0;
    var sort = $('ic-sort')?.value || 'title-asc';

    var list = allCards.filter(function (card) {
      var src = getImageSource(card);
      if (imageFilter === 'with' && !cardHasImage(card)) return false;
      if (imageFilter === 'without' && cardHasImage(card)) return false;
      if (imageFilter === 'poze' && src !== 'poze') return false;
      if (imageFilter === 'autopartner' && src !== 'autopartner') return false;
      if (imageFilter === 'scraped' && src !== 'scraped') return false;

      if (brandFilter && String(card.brand || '').toUpperCase() !== brandFilter) return false;
      if (supplierFilter && String(card.supplier || '').toUpperCase() !== supplierFilter) return false;

      if (search) {
        var hay = [card.title, card.sku, card.brand, card.name, card.artCode1, card.artCode2]
          .join(' ').toLowerCase();
        if (hay.indexOf(search) === -1) return false;
      }

      var price = parseFloat(card.priceFinal) || 0;
      if (priceMin > 0 && price < priceMin) return false;
      if (!isNaN(priceMax) && priceMax > 0 && price > priceMax) return false;

      var compat = parseInt(card.compatCount, 10) || 0;
      if (compatMin > 0 && compat < compatMin) return false;

      return true;
    });

    list.sort(function (a, b) {
      switch (sort) {
        case 'title-desc':
          return String(b.title || '').localeCompare(String(a.title || ''));
        case 'price-asc':
          return (parseFloat(a.priceFinal) || 0) - (parseFloat(b.priceFinal) || 0);
        case 'price-desc':
          return (parseFloat(b.priceFinal) || 0) - (parseFloat(a.priceFinal) || 0);
        case 'compat-desc':
          return (parseInt(b.compatCount, 10) || 0) - (parseInt(a.compatCount, 10) || 0);
        case 'image-first':
          return (cardHasImage(b) ? 1 : 0) - (cardHasImage(a) ? 1 : 0);
        default:
          return String(a.title || '').localeCompare(String(b.title || ''));
      }
    });

    return list;
  }

  function populateFilterOptions() {
    var brands = {};
    var suppliers = {};
    allCards.forEach(function (c) {
      if (c.brand) brands[String(c.brand).toUpperCase()] = true;
      if (c.supplier) suppliers[String(c.supplier).toUpperCase()] = true;
    });

    var brandSel = $('ic-filter-brand');
    var supSel = $('ic-filter-supplier');
    if (!brandSel || !supSel) return;

    var curBrand = brandSel.value;
    var curSup = supSel.value;

    brandSel.innerHTML = '<option value="">Toate</option>';
    Object.keys(brands).sort().forEach(function (b) {
      brandSel.insertAdjacentHTML('beforeend', '<option value="' + escapeHtml(b) + '">' + escapeHtml(b) + '</option>');
    });

    supSel.innerHTML = '<option value="">Toți</option>';
    Object.keys(suppliers).sort().forEach(function (s) {
      supSel.insertAdjacentHTML('beforeend', '<option value="' + escapeHtml(s) + '">' + escapeHtml(s) + '</option>');
    });

    brandSel.value = curBrand;
    supSel.value = curSup;
  }

  function renderCards() {
    var grid = $('ic-grid');
    var empty = $('ic-empty');
    var summary = $('ic-summary');
    var scrapeAll = $('ic-scrape-all');
    if (!grid) return;

    var filtered = getFilteredCards();
    var withImg = 0;
    var withoutImg = 0;

    grid.innerHTML = '';

    filtered.forEach(function (card) {
      var origIndex = allCards.indexOf(card);
      var hasImg = cardHasImage(card);
      if (hasImg) withImg++; else withoutImg++;

      var imageSrc = getProductImageUrl(card);
      var src = getImageSource(card);
      var badge = '';
      if (src === 'scraped') {
        badge = '<span class="ic-badge ic-badge--scraped">Scraper ' + escapeHtml(card.scrapedImageScore ?? '') + '%</span>';
      } else if (src === 'poze') {
        badge = '<span class="ic-badge ic-badge--poze">Poze</span>';
      } else if (src === 'autopartner') {
        badge = '<span class="ic-badge ic-badge--ap">Autopartner</span>';
      } else {
        badge = '<span class="ic-badge ic-badge--missing">Fără imagine</span>';
      }

      var imageBlock = hasImg
        ? '<img class="ic-card-img" src="' + escapeHtml(imageSrc) + '" alt="" loading="lazy">'
        : '<div class="ic-card-placeholder">Lipsește imaginea</div>';

      var scrapeBtn = hasImg ? '' : '<button type="button" class="ic-btn ic-btn--green ic-btn--sm" data-scrape="' + origIndex + '">Scraping</button>';

      grid.insertAdjacentHTML('beforeend', [
        '<article class="ic-card' + (hasImg ? '' : ' ic-card--no-image') + '" data-index="' + origIndex + '">',
        '<div class="ic-card-img-wrap">', badge, imageBlock, '</div>',
        '<div class="ic-card-body">',
        '<h3 class="ic-card-title">', escapeHtml(card.title), '</h3>',
        '<div class="ic-card-meta">',
        '<span>SKU: ', escapeHtml(card.sku), '</span>',
        '<span>', escapeHtml(card.brand), '</span>',
        card.supplier ? '<span>' + escapeHtml(card.supplier) + '</span>' : '',
        '<span>', (card.compatCount || 0), ' compat.</span>',
        '</div>',
        '<div class="ic-card-price">Achiziție: ', escapeHtml(card.pricePurchaseNet ?? card.pricePurchaseVat ?? '—'), ' RON',
        '<small>Preț final → după Adaos comercial</small></div>',
        '<div class="ic-card-actions">', scrapeBtn, '</div>',
        '<div class="ic-card-log" id="ic-log-' + origIndex + '"></div>',
        '</div></article>'
      ].join(''));
    });

    if (summary) {
      summary.textContent = filtered.length + ' afișate din ' + allCards.length
        + ' · ' + withImg + ' cu imagine · ' + withoutImg + ' fără imagine';
    }
    if (empty) empty.hidden = filtered.length > 0;
    if (scrapeAll) scrapeAll.disabled = withoutImg === 0 || batchRunning;

    grid.querySelectorAll('[data-scrape]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        scrapeForCard(parseInt(btn.dataset.scrape, 10));
      });
    });
  }

  function setCardLog(index, msg) {
    var el = document.getElementById('ic-log-' + index);
    if (el) el.textContent = msg;
  }

  async function scrapeForCard(index) {
    var card = allCards[index];
    if (!card || cardHasImage(card)) return;

    setCardLog(index, 'Căutare imagine…');
    try {
      var res = await fetch(apiUrl('scrape_image'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify({
          query: card.scrapeQuery || card.title || card.sku,
          brand: card.brand,
          sku: card.sku,
          name: card.name || card.title,
          min_score: parseInt($('ic-min-score')?.value, 10) || 70,
        }),
      });
      var data = await res.json();
      if (data.image_url || data.imageUrl) {
        card.scrapedImageUrl = data.image_url || data.imageUrl;
        card.scrapedImageScore = data.score ?? data.relevance_score ?? '';
        setCardLog(index, 'Imagine găsită ✓');
        renderCards();
      } else {
        setCardLog(index, data.error || 'Nicio imagine găsită');
      }
    } catch (e) {
      setCardLog(index, e.message || 'Eroare scraping');
    }
  }

  async function scrapeAllWithoutImage() {
    var targets = getFilteredCards().filter(function (c) { return !cardHasImage(c); });
    if (!targets.length || batchRunning) return;

    batchRunning = true;
    var progress = $('ic-progress');
    var bar = $('ic-progress-bar');
    if (progress) progress.hidden = false;

    for (var i = 0; i < targets.length; i++) {
      var idx = allCards.indexOf(targets[i]);
      if (bar) bar.style.width = Math.round(((i + 1) / targets.length) * 100) + '%';
      await scrapeForCard(idx);
    }

    batchRunning = false;
    if (progress) progress.hidden = true;
    if (bar) bar.style.width = '0%';
    renderCards();
  }

  async function loadIndexStatus() {
    try {
      var res = await fetch(apiUrl('index_status'));
      var data = await res.json();
      var idx = data.index || {};
      var el = $('ic-index-status');
      if (!el) return;
      if (idx.available && (idx.entries || 0) > 0) {
        setStatus(el, 'Index Base TecDoc: ' + idx.entries + ' SKU, ' + idx.codes + ' coduri (' + (idx.sizeMb || '?') + ' MB)', 'ok');
      } else {
        setStatus(el, 'Index Base TecDoc lipsește — lookup lent. Rulează: py scripts/build_base_index_parallel.py', 'warn');
      }
    } catch (_) { /* ignore */ }
  }

  async function loadMatcFiles() {
    var sel = $('ic-matc-file');
    if (!sel) return;
    try {
      var res = await fetch(apiUrl('list_files'));
      var data = await res.json();
      sel.innerHTML = '<optgroup label="Base TecDoc">';
      (data.base || []).forEach(function (f) {
        var label = (f.brand ? f.brand + ' — ' : '') + f.name + ' (' + f.sizeMb + ' MB)';
        sel.insertAdjacentHTML('beforeend', '<option value="' + escapeHtml(f.name) + '">' + escapeHtml(label) + '</option>');
      });
      sel.insertAdjacentHTML('beforeend', '</optgroup><optgroup label="Liste preț furnizori">');
      (data.suppliers || []).forEach(function (f) {
        sel.insertAdjacentHTML('beforeend', '<option value="' + escapeHtml(f.name) + '">' + escapeHtml(f.name) + ' (' + f.sizeMb + ' MB)</option>');
      });
      sel.insertAdjacentHTML('beforeend', '</optgroup>');
    } catch (e) {
      sel.innerHTML = '<option value="">Eroare: ' + escapeHtml(e.message) + '</option>';
    }
  }

  async function generateCards() {
    var btn = $('ic-generate');
    var status = $('ic-load-status');
    var sourceType = $('ic-source-type')?.value || 'upload';

    if (btn) btn.disabled = true;
    setStatus(status, 'Se generează carduri…', 'info');

    try {
      var form = new FormData();
      form.append('action', 'build_cards');
      form.append('limit', $('ic-limit')?.value || '50');
      form.append('include_without_image', $('ic-include-no-image')?.checked ? '1' : '0');

      if (sourceType === 'api') {
        var brand = ($('ic-brand')?.value || '').trim();
        if (!brand) throw new Error('Introdu brandul TecDoc.');
        var res = await fetch(apiUrl('product_cards', { limit: $('ic-limit')?.value || '50', brand: brand }));
        var data = await res.json();
        allCards = Array.isArray(data.cards) ? data.cards : (Array.isArray(data) ? data : []);
      } else if (sourceType === 'matc') {
        var matc = $('ic-matc-file')?.value;
        if (!matc) throw new Error('Selectează un fișier Matc.');
        form.append('matc_file', matc);
        var res2 = await fetch(apiUrl('build_cards'), { method: 'POST', body: form });
        var data2 = await res2.json();
        if (!data2.success && !data2.cards?.length) throw new Error(data2.error || 'Generare eșuată');
        allCards = data2.cards || [];
      } else {
        var file = $('ic-file')?.files?.[0];
        if (!file) throw new Error('Selectează un fișier CSV.');
        form.append('file', file);
        var res3 = await fetch(apiUrl('build_cards'), { method: 'POST', body: form });
        var data3 = await res3.json();
        if (!data3.success && !data3.cards?.length) throw new Error(data3.error || 'Generare eșuată');
        allCards = data3.cards || [];
      }

      $('ic-filters-panel').hidden = false;
      populateFilterOptions();
      renderCards();

      var sum = allCards.filter(cardHasImage).length;
      setStatus(status,
        allCards.length + ' carduri generate · ' + sum + ' cu imagine · ' + (allCards.length - sum) + ' fără imagine',
        'ok');

    } catch (e) {
      setStatus(status, e.message || 'Eroare', 'error');
    } finally {
      if (btn) btn.disabled = false;
    }
  }

  function bindSourceType() {
    var sel = $('ic-source-type');
    if (!sel) return;
    sel.addEventListener('change', function () {
      var v = sel.value;
      $('ic-upload-wrap').hidden = v !== 'upload';
      $('ic-matc-wrap').hidden = v !== 'matc';
      $('ic-brand-wrap').hidden = v !== 'api';
    });
  }

  function bindFilters() {
    ['ic-filter-image', 'ic-filter-brand', 'ic-filter-supplier', 'ic-filter-search',
      'ic-filter-price-min', 'ic-filter-price-max', 'ic-filter-compat-min', 'ic-sort'
    ].forEach(function (id) {
      var el = $(id);
      if (!el) return;
      el.addEventListener('input', renderCards);
      el.addEventListener('change', renderCards);
    });

    $('ic-reset-filters')?.addEventListener('click', function () {
      ['ic-filter-image', 'ic-filter-brand', 'ic-filter-supplier', 'ic-filter-search',
        'ic-filter-price-min', 'ic-filter-price-max', 'ic-filter-compat-min'
      ].forEach(function (id) {
        var el = $(id);
        if (el) el.value = '';
      });
      if ($('ic-filter-image')) $('ic-filter-image').value = 'all';
      if ($('ic-sort')) $('ic-sort').value = 'title-asc';
      renderCards();
    });
  }

  function init() {
    if (!cfg.available) return;
    bindSourceType();
    bindFilters();
    loadIndexStatus();
    loadMatcFiles();
    $('ic-generate')?.addEventListener('click', generateCards);
    $('ic-scrape-all')?.addEventListener('click', scrapeAllWithoutImage);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
