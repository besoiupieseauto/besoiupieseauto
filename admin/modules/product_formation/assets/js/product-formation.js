/**
 * Admin — Formare carte produs (reguli titlu per canal)
 */
(function () {
  'use strict';

  var cfgEl = document.getElementById('bpa-pcf-cfg');
  var ENDPOINT = '';
  var CSRF = '';
  try {
    var cfg = JSON.parse(cfgEl ? cfgEl.textContent : '{}');
    ENDPOINT = cfg.api || '';
    CSRF = cfg.csrf || (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
  } catch (e) {
    ENDPOINT = '';
  }

  if (!ENDPOINT) {
    var page = document.getElementById('bpa-pcf-page');
    if (page) {
      page.insertAdjacentHTML('beforeend', '<div class="bpa-pcf-load-err">Config API lipsă — reîncarcă pagina.</div>');
    }
    return;
  }

  function bindEl(id, event, handler) {
    var el = document.getElementById(id);
    if (el) el.addEventListener(event, handler);
  }

  function bindAll(selector, event, handler) {
    document.querySelectorAll(selector).forEach(function (el) {
      el.addEventListener(event, handler);
    });
  }

  var state = {
    config: null,
    fullConfig: null,
    segments: {},
    channels: {},
    samples: {},
    tabFields: {},
    tabColumns: {},
    tabFormats: {},
    tabChartSources: {},
    tabChartTypes: {},
    tabChartPeriods: {},
    tabChartMetrics: {},
    categories: [],
    savedScopes: [],
    activeChannel: 'website',
    pageMode: 'title',
    activeTabIdx: 0,
    scopeMode: 'default',
    scopeValue: '',
    scopeId: 'default',
    scopeLabel: 'Implicit (toate produsele)',
  };

  var toastEl = document.getElementById('bpa-pcf-toast');

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];
    });
  }

  function toast(msg, isErr) {
    if (!toastEl) {
      toastEl = document.getElementById('bpa-pcf-toast');
    }
    if (!toastEl) {
      // Fallback vizibil — fără toast în DOM.
      window.alert((isErr ? 'Eroare: ' : '') + String(msg || ''));
      return;
    }
    // Scoate toast-ul din layout cu overflow ascuns / z-index mic.
    if (toastEl.parentElement !== document.body) {
      document.body.appendChild(toastEl);
    }
    toastEl.textContent = msg;
    toastEl.className = 'bpa-pcf-toast' + (isErr ? ' bpa-pcf-toast--err' : ' bpa-pcf-toast--ok');
    toastEl.classList.remove('hidden');
    toastEl.style.display = 'block';
    toastEl.setAttribute('aria-live', 'polite');
    clearTimeout(toast._timer);
    toast._timer = setTimeout(function () {
      toastEl.classList.add('hidden');
      toastEl.style.display = '';
    }, 5200);
  }

  function fieldValue(id, fallback) {
    var el = document.getElementById(id);
    if (!el) return fallback;
    if (el.type === 'checkbox') return !!el.checked;
    return el.value;
  }

  function setFieldValue(id, value) {
    var el = document.getElementById(id);
    if (!el) return;
    if (el.type === 'checkbox') {
      el.checked = !!value;
      return;
    }
    el.value = value == null ? '' : String(value);
  }

  function currentScopeId() {
    if (state.scopeMode === 'category' && state.scopeValue) {
      return 'category:' + state.scopeValue;
    }
    if (state.scopeMode === 'project' && state.scopeValue) {
      return 'project:' + state.scopeValue;
    }
    return 'default';
  }

  function renderScopeControls() {
    var catWrap = document.getElementById('bpa-pcf-scope-category-wrap');
    var projWrap = document.getElementById('bpa-pcf-scope-project-wrap');
    var catSel = document.getElementById('bpa-pcf-scope-category');
    var projSel = document.getElementById('bpa-pcf-scope-project');
    if (catWrap) catWrap.classList.toggle('bpa-pcf-is-hidden', state.scopeMode !== 'category');
    if (projWrap) projWrap.classList.toggle('bpa-pcf-is-hidden', state.scopeMode !== 'project');

    if (catSel && !catSel.options.length) {
      catSel.innerHTML = state.categories.map(function (c) {
        return '<option value="' + esc(c.label) + '">' + esc(c.label) + ' (' + (c.count || 0) + ')</option>';
      }).join('');
      if (state.categories.length && !state.scopeValue && state.scopeMode === 'category') {
        state.scopeValue = state.categories[0].label;
        catSel.value = state.scopeValue;
      }
    }

    if (projSel && !projSel.options.length) {
      projSel.innerHTML = Object.keys(state.channels).map(function (key) {
        var meta = state.channels[key] || {};
        return '<option value="' + esc(key) + '">' + esc(meta.label || key) + '</option>';
      }).join('');
      if (!state.scopeValue && state.scopeMode === 'project') {
        state.scopeValue = 'website';
        projSel.value = state.scopeValue;
      }
    }

    document.querySelectorAll('input[name="pcf_scope_mode"]').forEach(function (r) {
      r.checked = r.value === state.scopeMode;
    });
    if (catSel && state.scopeMode === 'category' && state.scopeValue) catSel.value = state.scopeValue;
    if (projSel && state.scopeMode === 'project' && state.scopeValue) projSel.value = state.scopeValue;
    renderScopeBadge();
  }

  function renderScopeBadge() {
    var badge = document.getElementById('bpa-pcf-scope-badge');
    var hint = document.getElementById('bpa-pcf-scope-hint');
    if (badge) {
      badge.innerHTML = 'Profil activ: <strong>' + esc(state.scopeLabel || currentScopeId()) + '</strong>';
    }
    if (hint) {
      if (state.scopeMode === 'category') {
        hint.textContent = 'Modificările se aplică produselor din categoria „' + state.scopeValue + '” (tab-uri site + titlu la import).';
      } else if (state.scopeMode === 'project') {
        hint.textContent = 'Modificările se aplică titlului la import/export pe canalul „' + (state.channels[state.scopeValue] || {}).label + '”.';
      } else {
        hint.textContent = 'Regulile implicite se aplică tuturor produselor. Poți crea un profil separat per categorie sau per proiect.';
      }
    }
  }

  function applyEditorData(editor) {
    editor = editor || {};
    state.config = {
      channels: editor.channels || {},
      product_tabs: editor.product_tabs || { tabs: [] },
    };
    state.scopeId = editor.scope || currentScopeId();
    state.scopeLabel = editor.scope_label || state.scopeLabel;
    if (state.scopeMode === 'project' && state.scopeValue) {
      state.activeChannel = state.scopeValue;
    }
    renderScopeControls();
    renderPlaceholders();
    renderChannelTabs();
    syncFormFromChannel();
    renderTabsList();
    syncTabFormFromState();
    updateScopeUiLocks();
    if (state.pageMode === 'tabs') {
      scheduleTabsPreview();
    } else {
      schedulePreview();
    }
  }

  function updateScopeUiLocks() {
    var sidebar = document.querySelector('#bpa-pcf-mode-title .bpa-pcf-channels');
    if (sidebar) {
      sidebar.classList.toggle('bpa-pcf-channels--locked', state.scopeMode === 'project');
    }
    var tabsMode = document.getElementById('bpa-pcf-mode-tabs');
    if (tabsMode) {
      tabsMode.classList.toggle('bpa-pcf-scope-tabs-only', state.scopeMode === 'project');
    }
  }

  function loadEditorScope(scopeId) {
    return apiGet({ action: 'editor', scope: scopeId }).then(function (j) {
      applyEditorData(j.data);
      return j;
    });
  }

  function switchScopeMode(mode, value) {
    state.scopeMode = mode;
    state.scopeValue = value || '';
    if (mode === 'category' && !state.scopeValue && state.categories.length) {
      state.scopeValue = state.categories[0].label;
    }
    if (mode === 'project' && !state.scopeValue) {
      state.scopeValue = 'website';
    }
    state.scopeId = currentScopeId();
    return loadEditorScope(state.scopeId);
  }

  function tabsCfg() {
    return (state.config && state.config.product_tabs) || { tabs: [] };
  }

  function ensureTabsConfig() {
    if (!state.config) state.config = {};
    if (!state.config.product_tabs) state.config.product_tabs = { tabs: [] };
    if (!Array.isArray(state.config.product_tabs.tabs)) state.config.product_tabs.tabs = [];
  }

  function tabIds() {
    return (tabsCfg().tabs || []).map(function (t) { return t.id; });
  }

  function slugTabId(label, existing) {
    var base = String(label || 'tab').toLowerCase()
      .replace(/[ăâ]/g, 'a').replace(/[î]/g, 'i').replace(/[șş]/g, 's').replace(/[țţ]/g, 't')
      .replace(/[^a-z0-9]+/g, '_').replace(/^_|_$/g, '') || 'tab';
    var id = base;
    var n = 2;
    while (existing.indexOf(id) >= 0) {
      id = base + '_' + n;
      n += 1;
    }
    return id;
  }

  function defaultFieldsList() {
    return [
      { id: 'denumire', label: 'Descrierea', enabled: true },
      { id: 'brand_piesa', label: 'Producătorul', enabled: true },
      { id: 'cod', label: 'Număr articol', enabled: true },
    ];
  }

  function defaultColumnsList() {
    return ['car_brand', 'car_model', 'car_motor'].map(function (id) {
      var meta = state.tabColumns[id] || {};
      return { id: id, label: meta.label || id, enabled: true };
    });
  }

  function ensureTabStructure(tab) {
    if (!tab) return;
    var format = tab.format || 'fields';
    var source = tab.source || 'field_rows';
    if (format === 'reviews') return;
    if (format === 'html') return;
    if (format === 'prose') {
      if (!tab.prose_words) tab.prose_words = 400;
      return;
    }
    if (format === 'chart') {
      if (!tab.chart_source) tab.chart_source = 'price_diversity';
      if (!tab.chart_type) {
        var srcMeta = state.tabChartSources[tab.chart_source] || {};
        tab.chart_type = srcMeta.default_type || 'bar';
      }
      if (!tab.chart_period) tab.chart_period = '6m';
      ensureChartMetrics(tab);
      return;
    }
    if (format === 'table' && source === 'compat_entries') {
      if (!Array.isArray(tab.columns)) tab.columns = [];
      return;
    }
    if (!Array.isArray(tab.fields)) tab.fields = [];
  }

  function defaultChartTypeForSource(sourceId) {
    var meta = state.tabChartSources[sourceId] || {};
    return meta.default_type || 'bar';
  }

  function chartMetricsCatalogForSource(sourceId) {
    return allChartMetricsCatalog(sourceId || 'price_diversity');
  }

  function allChartMetricsCatalog(sourceId) {
    var source = sourceId || 'price_diversity';
    var core = state.tabChartMetrics[source] || {};
    var out = {};
    Object.keys(core).forEach(function (id) {
      out[id] = {
        label: (core[id] && core[id].label) || id,
        hint: (core[id] && core[id].hint) || '',
        group: 'chart',
      };
    });
    Object.keys(state.tabFields || {}).forEach(function (id) {
      var key = 'field_' + id;
      if (out[key]) return;
      var meta = state.tabFields[id] || {};
      out[key] = {
        label: meta.label || id,
        hint: meta.hint || '',
        group: 'fields',
      };
    });
    return out;
  }

  function isCustomChartMetric(metric) {
    return !!(metric && (metric.custom || String(metric.id || '').indexOf('custom_') === 0));
  }

  function defaultChartMetricsList(sourceId) {
    var catalog = chartMetricsCatalogForSource(sourceId || 'price_diversity');
    return Object.keys(catalog).map(function (id) {
      var meta = catalog[id] || {};
      return { id: id, label: meta.label || id, enabled: true };
    });
  }

  function ensureChartMetrics(tab) {
    if (!tab || tab.format !== 'chart') return;
    var source = tab.chart_source || 'price_diversity';
    var catalog = chartMetricsCatalogForSource(source);
    if (!Array.isArray(tab.chart_metrics) || !tab.chart_metrics.length) {
      tab.chart_metrics = defaultChartMetricsList(source);
      return;
    }
    tab.chart_metrics = tab.chart_metrics.filter(function (m) {
      return m && (isCustomChartMetric(m) || catalog[m.id]);
    });
    if (!tab.chart_metrics.length) {
      tab.chart_metrics = defaultChartMetricsList(source);
    }
  }

  function renderTabChartMetricsList(tab) {
    var ul = document.getElementById('bpa-pcf-tab-chart-metrics');
    if (!ul || !tab) return;
    ensureChartMetrics(tab);
    var source = tab.chart_source || 'price_diversity';
    var catalog = chartMetricsCatalogForSource(source);
    var metrics = tab.chart_metrics || [];
    ul.innerHTML = metrics.map(function (metric, idx) {
      var meta = catalog[metric.id] || {};
      var isCustom = isCustomChartMetric(metric);
      var title = isCustom ? 'Valoare manuală' : (meta.label || metric.id);
      var hint = isCustom ? 'Introdu un număr (ex. 150 sau 29.99)' : (meta.hint || '');
      var valueInput = isCustom
        ? '<input type="text" class="bpa-pcf-tab-field-label bpa-pcf-chart-metric-value" data-chart-metric-value="' + idx + '" value="' + esc(metric.static_value || '') + '" placeholder="Valoare">'
        : '';
      return '<li class="bpa-pcf-seg" data-chart-metric-idx="' + idx + '">'
        + '<label class="bpa-pcf-seg__check"><input type="checkbox" data-chart-metric-enabled="' + idx + '" '
        + (metric.enabled ? 'checked' : '') + '> ' + esc(title) + '</label>'
        + '<span class="bpa-pcf-seg__hint">' + esc(hint) + '</span>'
        + '<input type="text" class="bpa-pcf-tab-field-label" data-chart-metric-label="' + idx + '" value="' + esc(metric.label || meta.label || metric.id) + '" placeholder="Etichetă pe grafic">'
        + valueInput
        + '<span class="bpa-pcf-seg__order">'
        + '<button type="button" class="bpa-pcf-seg__btn" data-chart-metric-move="up" data-idx="' + idx + '">↑</button>'
        + '<button type="button" class="bpa-pcf-seg__btn" data-chart-metric-move="down" data-idx="' + idx + '">↓</button>'
        + '<button type="button" class="bpa-pcf-seg__btn bpa-pcf-seg__btn--del" data-chart-metric-remove="' + idx + '" title="Șterge dată">×</button>'
        + '</span></li>';
    }).join('');
    renderChartMetricAddToolbar(tab);
  }

  function renderChartMetricAddToolbar(tab) {
    var wrap = document.getElementById('bpa-pcf-chart-metrics-add');
    var sel = document.getElementById('bpa-pcf-add-chart-metric-id');
    if (!wrap || !sel || !tab) return;
    var catalog = chartMetricsCatalogForSource(tab.chart_source || 'price_diversity');
    var available = availableCatalogIds(catalog, (tab.chart_metrics || []).map(function (m) { return m.id; }));
    if (!available.length) {
      wrap.classList.add('bpa-pcf-is-hidden');
      return;
    }
    wrap.classList.remove('bpa-pcf-is-hidden');
    var chartIds = available.filter(function (id) { return (catalog[id] || {}).group === 'chart'; });
    var fieldIds = available.filter(function (id) { return (catalog[id] || {}).group === 'fields'; });
    var html = '';
    if (chartIds.length) {
      html += '<optgroup label="Metrici grafic">' + chartIds.map(function (id) {
        return '<option value="' + esc(id) + '">' + esc((catalog[id] || {}).label || id) + '</option>';
      }).join('') + '</optgroup>';
    }
    if (fieldIds.length) {
      html += '<optgroup label="Câmpuri produs">' + fieldIds.map(function (id) {
        return '<option value="' + esc(id) + '">' + esc((catalog[id] || {}).label || id) + '</option>';
      }).join('') + '</optgroup>';
    }
    sel.innerHTML = html;
  }

  function addCustomChartMetric() {
    var tab = activeTab();
    if (!tab) return;
    ensureTabStructure(tab);
    if (!Array.isArray(tab.chart_metrics)) tab.chart_metrics = [];
    var id = 'custom_' + Date.now().toString(36);
    tab.chart_metrics.push({
      id: id,
      label: 'Dată manuală',
      static_value: '100',
      enabled: true,
      custom: true,
    });
    renderTabChartMetricsList(tab);
    scheduleTabsPreview();
  }

  function addAllChartMetrics() {
    var tab = activeTab();
    if (!tab) return;
    ensureTabStructure(tab);
    var catalog = chartMetricsCatalogForSource(tab.chart_source || 'price_diversity');
    var used = (tab.chart_metrics || []).map(function (m) { return m.id; });
    Object.keys(catalog).forEach(function (id) {
      if (used.indexOf(id) >= 0) return;
      var meta = catalog[id] || {};
      tab.chart_metrics.push({ id: id, label: meta.label || id, enabled: true });
    });
    renderTabChartMetricsList(tab);
    scheduleTabsPreview();
    toast('Toate datele disponibile au fost adăugate.');
  }

  function addChartMetricToTab(metricId) {
    var tab = activeTab();
    if (!tab || !metricId) return;
    ensureTabStructure(tab);
    ensureChartMetrics(tab);
    var catalog = chartMetricsCatalogForSource(tab.chart_source || 'price_diversity');
    var used = (tab.chart_metrics || []).map(function (m) { return m.id; });
    if (used.indexOf(metricId) >= 0) return;
    var meta = catalog[metricId] || {};
    tab.chart_metrics.push({ id: metricId, label: meta.label || metricId, enabled: true });
    renderTabChartMetricsList(tab);
    scheduleTabsPreview();
  }

  function removeChartMetricAt(idx) {
    var tab = activeTab();
    if (!tab || !tab.chart_metrics || tab.chart_metrics.length <= 1) {
      toast('Graficul trebuie să aibă cel puțin o dată activă.', true);
      return;
    }
    tab.chart_metrics.splice(idx, 1);
    renderTabChartMetricsList(tab);
    scheduleTabsPreview();
  }

  function syncChartMetricsFromDom() {
    var tab = activeTab();
    if (!tab || tab.format !== 'chart' || !tab.chart_metrics) return;
    document.querySelectorAll('#bpa-pcf-tab-chart-metrics input[data-chart-metric-enabled]').forEach(function (cb) {
      var idx = parseInt(cb.getAttribute('data-chart-metric-enabled'), 10);
      if (tab.chart_metrics[idx]) tab.chart_metrics[idx].enabled = cb.checked;
    });
    document.querySelectorAll('#bpa-pcf-tab-chart-metrics input[data-chart-metric-label]').forEach(function (inp) {
      var idx = parseInt(inp.getAttribute('data-chart-metric-label'), 10);
      if (tab.chart_metrics[idx]) tab.chart_metrics[idx].label = inp.value.trim();
    });
    document.querySelectorAll('#bpa-pcf-tab-chart-metrics input[data-chart-metric-value]').forEach(function (inp) {
      var idx = parseInt(inp.getAttribute('data-chart-metric-value'), 10);
      if (tab.chart_metrics[idx]) tab.chart_metrics[idx].static_value = inp.value.trim();
    });
  }

  function fillChartSelects() {
    var srcSel = document.getElementById('bpa-pcf-tab-chart-source');
    var typeSel = document.getElementById('bpa-pcf-tab-chart-type');
    var periodSel = document.getElementById('bpa-pcf-tab-chart-period');
    if (srcSel && !srcSel.options.length) {
      Object.keys(state.tabChartSources).forEach(function (key) {
        var meta = state.tabChartSources[key] || {};
        var opt = document.createElement('option');
        opt.value = key;
        opt.textContent = meta.label || key;
        opt.title = meta.hint || '';
        srcSel.appendChild(opt);
      });
    }
    if (typeSel && !typeSel.options.length) {
      Object.keys(state.tabChartTypes).forEach(function (key) {
        var opt = document.createElement('option');
        opt.value = key;
        opt.textContent = state.tabChartTypes[key] || key;
        typeSel.appendChild(opt);
      });
    }
    if (periodSel && !periodSel.options.length) {
      Object.keys(state.tabChartPeriods).forEach(function (key) {
        var opt = document.createElement('option');
        opt.value = key;
        opt.textContent = state.tabChartPeriods[key] || key;
        periodSel.appendChild(opt);
      });
    }
  }

  function availableCatalogIds(catalog, usedIds) {
    return Object.keys(catalog || {}).filter(function (id) {
      return usedIds.indexOf(id) < 0;
    });
  }

  function addTab() {
    syncTabFormToState();
    ensureTabsConfig();
    var tabs = state.config.product_tabs.tabs;
    var label = 'Tab nou';
    var tab = {
      id: slugTabId(label, tabIds()),
      enabled: true,
      label: label,
      format: 'fields',
      source: 'field_rows',
      fields: defaultFieldsList(),
    };
    tabs.push(tab);
    state.activeTabIdx = tabs.length - 1;
    renderTabsList();
    syncTabFormFromState();
    scheduleTabsPreview();
    toast('Tab adăugat — redenumește-l și salvează.');
  }

  function deleteTabAt(idx) {
    ensureTabsConfig();
    var tabs = state.config.product_tabs.tabs;
    if (tabs.length <= 1) {
      toast('Trebuie să rămână cel puțin un tab pe pagină.', true);
      return;
    }
    var tab = tabs[idx];
    if (!tab) return;
    if (!confirm('Ștergi tab-ul „' + (tab.label || tab.id) + '”?')) return;
    syncTabFormToState();
    tabs.splice(idx, 1);
    if (state.activeTabIdx >= tabs.length) state.activeTabIdx = tabs.length - 1;
    renderTabsList();
    syncTabFormFromState();
    scheduleTabsPreview();
  }

  function addFieldToTab(fieldId) {
    var tab = activeTab();
    if (!tab || !fieldId) return;
    ensureTabStructure(tab);
    var used = (tab.fields || []).map(function (f) { return f.id; });
    if (used.indexOf(fieldId) >= 0) return;
    var meta = state.tabFields[fieldId] || {};
    tab.fields.push({ id: fieldId, label: meta.label || fieldId, enabled: true });
    renderTabFieldsList(tab);
    renderFieldAddToolbar(tab);
    scheduleTabsPreview();
  }

  function removeFieldAt(idx) {
    var tab = activeTab();
    if (!tab || !tab.fields || tab.fields.length <= 1) {
      toast('Tab-ul trebuie să aibă cel puțin un câmp.', true);
      return;
    }
    tab.fields.splice(idx, 1);
    renderTabFieldsList(tab);
    renderFieldAddToolbar(tab);
    scheduleTabsPreview();
  }

  function addColumnToTab(colId) {
    var tab = activeTab();
    if (!tab || !colId) return;
    ensureTabStructure(tab);
    if (!Array.isArray(tab.columns)) tab.columns = [];
    var used = tab.columns.map(function (c) { return c.id; });
    if (used.indexOf(colId) >= 0) return;
    var meta = state.tabColumns[colId] || {};
    tab.columns.push({ id: colId, label: meta.label || colId, enabled: true });
    renderTabColumnsList(tab);
    renderColumnAddToolbar(tab);
    scheduleTabsPreview();
  }

  function removeColumnAt(idx) {
    var tab = activeTab();
    if (!tab || !tab.columns || tab.columns.length <= 1) {
      toast('Tabelul trebuie să aibă cel puțin o coloană.', true);
      return;
    }
    tab.columns.splice(idx, 1);
    renderTabColumnsList(tab);
    renderColumnAddToolbar(tab);
    scheduleTabsPreview();
  }

  function renderFieldAddToolbar(tab) {
    var wrap = document.getElementById('bpa-pcf-fields-add');
    var sel = document.getElementById('bpa-pcf-add-field-id');
    if (!wrap || !sel || !tab) return;
    var available = availableCatalogIds(state.tabFields, (tab.fields || []).map(function (f) { return f.id; }));
    if (!available.length) {
      wrap.classList.add('bpa-pcf-is-hidden');
      return;
    }
    wrap.classList.remove('bpa-pcf-is-hidden');
    sel.innerHTML = available.map(function (id) {
      return '<option value="' + esc(id) + '">' + esc((state.tabFields[id] || {}).label || id) + '</option>';
    }).join('');
  }

  function renderColumnAddToolbar(tab) {
    var wrap = document.getElementById('bpa-pcf-columns-add');
    var sel = document.getElementById('bpa-pcf-add-column-id');
    if (!wrap || !sel || !tab) return;
    var available = availableCatalogIds(state.tabColumns, (tab.columns || []).map(function (c) { return c.id; }));
    if (!available.length) {
      wrap.classList.add('bpa-pcf-is-hidden');
      return;
    }
    wrap.classList.remove('bpa-pcf-is-hidden');
    sel.innerHTML = available.map(function (id) {
      return '<option value="' + esc(id) + '">' + esc((state.tabColumns[id] || {}).label || id) + '</option>';
    }).join('');
  }

  function activeTab() {
    var tabs = tabsCfg().tabs || [];
    return tabs[state.activeTabIdx] || null;
  }

  function setPageMode(mode) {
    state.pageMode = mode;
    document.querySelectorAll('[data-page-mode]').forEach(function (btn) {
      btn.classList.toggle('is-active', btn.getAttribute('data-page-mode') === mode);
    });
    document.getElementById('bpa-pcf-mode-title').classList.toggle('bpa-pcf-is-hidden', mode !== 'title');
    document.getElementById('bpa-pcf-mode-tabs').classList.toggle('bpa-pcf-is-hidden', mode !== 'tabs');
    document.querySelector('.bpa-pcf-head__actions').classList.toggle('bpa-pcf-is-hidden', mode !== 'title');
    updateScopeUiLocks();
    if (mode === 'tabs') {
      renderTabsList();
      syncTabFormFromState();
      scheduleTabsPreview();
    }
  }

  function renderTabsList() {
    var ul = document.getElementById('bpa-pcf-tabs-list');
    if (!ul) return;
    var tabs = tabsCfg().tabs || [];
    ul.innerHTML = tabs.map(function (tab, idx) {
      var active = idx === state.activeTabIdx ? ' is-active' : '';
      var off = tab.enabled ? '' : ' is-off';
      return '<li class="bpa-pcf-tabs-list__item' + active + off + '" data-tab-idx="' + idx + '">'
        + '<label class="bpa-pcf-tabs-list__check"><input type="checkbox" data-tab-enabled="' + idx + '" '
        + (tab.enabled ? 'checked' : '') + '> ' + esc(tab.label || tab.id) + '</label>'
        + '<span class="bpa-pcf-tabs-list__meta">' + esc((state.tabFormats[tab.format] || tab.format || '')) + '</span>'
        + '<span class="bpa-pcf-seg__order">'
        + '<button type="button" class="bpa-pcf-seg__btn" data-tab-move="up" data-idx="' + idx + '">↑</button>'
        + '<button type="button" class="bpa-pcf-seg__btn" data-tab-move="down" data-idx="' + idx + '">↓</button>'
        + '<button type="button" class="bpa-pcf-seg__btn bpa-pcf-seg__btn--del" data-tab-remove="' + idx + '" title="Șterge tab">×</button>'
        + '</span></li>';
    }).join('');
  }

  function fillFormatSelect() {
    var sel = document.getElementById('bpa-pcf-tab-format');
    if (!sel || sel.options.length) return;
    Object.keys(state.tabFormats).forEach(function (key) {
      var opt = document.createElement('option');
      opt.value = key;
      opt.textContent = state.tabFormats[key];
      sel.appendChild(opt);
    });
  }

  function syncTabFormFromState() {
    fillFormatSelect();
    var tab = activeTab();
    var empty = document.getElementById('bpa-pcf-tab-empty');
    var form = document.getElementById('bpa-pcf-tab-form');
    if (!tab) {
      empty.classList.remove('bpa-pcf-is-hidden');
      form.classList.add('bpa-pcf-is-hidden');
      return;
    }
    empty.classList.add('bpa-pcf-is-hidden');
    form.classList.remove('bpa-pcf-is-hidden');
    document.getElementById('bpa-pcf-tab-label').value = tab.label || '';
    document.getElementById('bpa-pcf-tab-enabled').checked = !!tab.enabled;
    document.getElementById('bpa-pcf-tab-format').value = tab.format || 'fields';
    document.getElementById('bpa-pcf-tab-source').value = tab.source || 'field_rows';
    document.getElementById('bpa-pcf-tab-footnote').value = tab.footnote || '';
    fillChartSelects();
    ensureTabStructure(tab);
    var chartSrcEl = document.getElementById('bpa-pcf-tab-chart-source');
    var chartTypeEl = document.getElementById('bpa-pcf-tab-chart-type');
    var chartPeriodEl = document.getElementById('bpa-pcf-tab-chart-period');
    var chartTitleEl = document.getElementById('bpa-pcf-tab-chart-title');
    if (chartSrcEl) chartSrcEl.value = tab.chart_source || 'price_diversity';
    if (chartTypeEl) chartTypeEl.value = tab.chart_type || defaultChartTypeForSource(tab.chart_source || 'price_diversity');
    if (chartPeriodEl) chartPeriodEl.value = tab.chart_period || '6m';
    if (chartTitleEl) chartTitleEl.value = tab.chart_title || '';
    var proseWordsEl = document.getElementById('bpa-pcf-tab-prose-words');
    if (proseWordsEl) proseWordsEl.value = tab.prose_words || 400;
    updateTabEditorPanes(tab);
    renderTabFieldsList(tab);
    renderTabColumnsList(tab);
    renderTabChartMetricsList(tab);
  }

  function updateTabEditorPanes(tab) {
    var format = tab.format || 'fields';
    var source = tab.source || 'field_rows';
    var isChart = format === 'chart';
    var usesCompatCols = format === 'table' && source === 'compat_entries';
    var hideFields = format === 'reviews' || format === 'html' || usesCompatCols || isChart;
    document.getElementById('bpa-pcf-tab-fields-pane').classList.toggle('bpa-pcf-is-hidden', hideFields);
    document.getElementById('bpa-pcf-tab-columns-pane').classList.toggle('bpa-pcf-is-hidden', !usesCompatCols);
    var chartPane = document.getElementById('bpa-pcf-tab-chart-pane');
    if (chartPane) chartPane.classList.toggle('bpa-pcf-is-hidden', !isChart);
    document.querySelector('.bpa-pcf-field--source').classList.toggle('bpa-pcf-is-hidden', format === 'reviews' || format === 'prose' || isChart);
    var proseField = document.querySelector('.bpa-pcf-field--prose-words');
    if (proseField) proseField.classList.toggle('bpa-pcf-is-hidden', format !== 'prose');
    var proseHint = document.getElementById('bpa-pcf-prose-fields-hint');
    if (proseHint) proseHint.classList.toggle('bpa-pcf-is-hidden', format !== 'prose');
    var fieldsHint = document.getElementById('bpa-pcf-fields-hint');
    if (fieldsHint) fieldsHint.classList.toggle('bpa-pcf-is-hidden', format === 'prose');
    document.querySelector('.bpa-pcf-field--footnote').classList.toggle('bpa-pcf-is-hidden', !usesCompatCols);
    var periodField = document.querySelector('.bpa-pcf-field--chart-period');
    if (periodField) {
      var src = tab.chart_source || 'price_diversity';
      periodField.classList.toggle('bpa-pcf-is-hidden', !isChart || src !== 'purchase_stats');
    }
  }

  function renderTabFieldsList(tab) {
    var ul = document.getElementById('bpa-pcf-tab-fields');
    if (!ul) return;
    var fields = tab.fields || [];
    ul.innerHTML = fields.map(function (field, idx) {
      var meta = state.tabFields[field.id] || {};
      return '<li class="bpa-pcf-seg" data-field-idx="' + idx + '">'
        + '<label class="bpa-pcf-seg__check"><input type="checkbox" data-field-enabled="' + idx + '" '
        + (field.enabled ? 'checked' : '') + '> ' + esc(meta.label || field.id) + '</label>'
        + '<input type="text" class="bpa-pcf-tab-field-label" data-field-label="' + idx + '" value="' + esc(field.label || meta.label || field.id) + '" placeholder="Etichetă afișată">'
        + '<span class="bpa-pcf-seg__order">'
        + '<button type="button" class="bpa-pcf-seg__btn" data-field-move="up" data-idx="' + idx + '">↑</button>'
        + '<button type="button" class="bpa-pcf-seg__btn" data-field-move="down" data-idx="' + idx + '">↓</button>'
        + '<button type="button" class="bpa-pcf-seg__btn bpa-pcf-seg__btn--del" data-field-remove="' + idx + '" title="Șterge câmp">×</button>'
        + '</span></li>';
    }).join('');
    renderFieldAddToolbar(tab);
  }

  function renderTabColumnsList(tab) {
    var ul = document.getElementById('bpa-pcf-tab-columns');
    if (!ul) return;
    var cols = tab.columns || [];
    ul.innerHTML = cols.map(function (col, idx) {
      var meta = state.tabColumns[col.id] || {};
      return '<li class="bpa-pcf-seg" data-col-idx="' + idx + '">'
        + '<label class="bpa-pcf-seg__check"><input type="checkbox" data-col-enabled="' + idx + '" '
        + (col.enabled ? 'checked' : '') + '> ' + esc(meta.label || col.id) + '</label>'
        + '<input type="text" class="bpa-pcf-tab-field-label" data-col-label="' + idx + '" value="' + esc(col.label || meta.label || col.id) + '" placeholder="Etichetă coloană">'
        + '<span class="bpa-pcf-seg__order">'
        + '<button type="button" class="bpa-pcf-seg__btn" data-col-move="up" data-idx="' + idx + '">↑</button>'
        + '<button type="button" class="bpa-pcf-seg__btn" data-col-move="down" data-idx="' + idx + '">↓</button>'
        + '<button type="button" class="bpa-pcf-seg__btn bpa-pcf-seg__btn--del" data-col-remove="' + idx + '" title="Șterge coloană">×</button>'
        + '</span></li>';
    }).join('');
    renderColumnAddToolbar(tab);
  }

  function syncTabRowsFromDom() {
    var tab = activeTab();
    if (!tab) return;
    document.querySelectorAll('#bpa-pcf-tab-fields input[data-field-enabled]').forEach(function (cb) {
      var idx = parseInt(cb.getAttribute('data-field-enabled'), 10);
      if (tab.fields && tab.fields[idx]) tab.fields[idx].enabled = cb.checked;
    });
    document.querySelectorAll('#bpa-pcf-tab-fields input[data-field-label]').forEach(function (inp) {
      var idx = parseInt(inp.getAttribute('data-field-label'), 10);
      if (tab.fields && tab.fields[idx]) tab.fields[idx].label = inp.value.trim();
    });
    document.querySelectorAll('#bpa-pcf-tab-columns input[data-col-enabled]').forEach(function (cb) {
      var idx = parseInt(cb.getAttribute('data-col-enabled'), 10);
      if (tab.columns && tab.columns[idx]) tab.columns[idx].enabled = cb.checked;
    });
    document.querySelectorAll('#bpa-pcf-tab-columns input[data-col-label]').forEach(function (inp) {
      var idx = parseInt(inp.getAttribute('data-col-label'), 10);
      if (tab.columns && tab.columns[idx]) tab.columns[idx].label = inp.value.trim();
    });
  }

  function syncTabFormToState() {
    syncTabRowsFromDom();
    syncChartMetricsFromDom();
    var tab = activeTab();
    if (!tab) return;
    tab.label = document.getElementById('bpa-pcf-tab-label').value.trim() || tab.id;
    tab.enabled = document.getElementById('bpa-pcf-tab-enabled').checked;
    tab.format = document.getElementById('bpa-pcf-tab-format').value;
    tab.source = document.getElementById('bpa-pcf-tab-source').value;
    tab.footnote = document.getElementById('bpa-pcf-tab-footnote').value.trim();
    if (tab.format === 'prose') {
      var proseWordsEl = document.getElementById('bpa-pcf-tab-prose-words');
      tab.prose_words = proseWordsEl ? parseInt(proseWordsEl.value, 10) || 400 : 400;
      if (tab.prose_words < 150) tab.prose_words = 150;
      if (tab.prose_words > 800) tab.prose_words = 800;
    }
    if (tab.format === 'chart') {
      var chartSrcEl = document.getElementById('bpa-pcf-tab-chart-source');
      var chartTypeEl = document.getElementById('bpa-pcf-tab-chart-type');
      var chartPeriodEl = document.getElementById('bpa-pcf-tab-chart-period');
      var chartTitleEl = document.getElementById('bpa-pcf-tab-chart-title');
      tab.chart_source = chartSrcEl ? chartSrcEl.value : 'price_diversity';
      tab.chart_type = chartTypeEl ? chartTypeEl.value : defaultChartTypeForSource(tab.chart_source);
      tab.chart_period = chartPeriodEl ? chartPeriodEl.value : '6m';
      tab.chart_title = chartTitleEl ? chartTitleEl.value.trim() : '';
    }
  }

  function renderTabsPreview(data) {
    var wrap = document.getElementById('bpa-pcf-tabs-preview');
    if (!wrap || !data || !data.tabs) return;
    if (!data.tabs.length) {
      wrap.innerHTML = '<p class="bpa-pcf-hint">Niciun tab activ.</p>';
      return;
    }
    var nav = data.tabs.map(function (tab, idx) {
      return '<button type="button" class="bpa-pcf-tabs-preview__nav' + (idx === 0 ? ' is-active' : '') + '" data-preview-tab="' + idx + '">'
        + esc(tab.label) + '</button>';
    }).join('');
    var panes = data.tabs.map(function (tab, idx) {
      return '<div class="bpa-pcf-tabs-preview__pane' + (idx === 0 ? ' is-active' : '') + '" data-preview-pane="' + idx + '">'
        + (tab.content_html || '') + '</div>';
    }).join('');
    wrap.innerHTML = '<div class="bpa-pcf-tabs-preview__navs">' + nav + '</div><div class="bpa-pcf-tabs-preview__body">' + panes + '</div>';
    wrap.querySelectorAll('.besoiu-product-tab-chart').forEach(function (el) {
      el.removeAttribute('data-chart-ready');
    });
    if (window.besoiuInitProductTabCharts) {
      window.besoiuInitProductTabCharts(wrap);
    }
    if (window.besoiuInitProductReviewStars) {
      window.besoiuInitProductReviewStars(wrap);
    }
  }

  var tabsPreviewTimer = null;
  function scheduleTabsPreview() {
    clearTimeout(tabsPreviewTimer);
    tabsPreviewTimer = setTimeout(runTabsPreview, 320);
  }

  function runTabsPreview() {
    syncTabFormToState();
    var sampleEl = document.getElementById('bpa-pcf-tabs-sample');
    apiPost({
      action: 'preview_tabs',
      config: state.config,
      sample: sampleEl ? sampleEl.value : 'default',
      scope: currentScopeId(),
    }).then(function (j) {
      renderTabsPreview(j.data);
    }).catch(function (e) {
      toast(e.message, true);
    });
  }

  function moveListItem(list, idx, dir) {
    var next = dir === 'up' ? idx - 1 : idx + 1;
    if (next < 0 || next >= list.length) return;
    var tmp = list[idx];
    list[idx] = list[next];
    list[next] = tmp;
  }

  function apiGet(params) {
    return fetch(ENDPOINT + '?' + new URLSearchParams(params), { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        if (!j.success) throw new Error(j.message || 'Eroare API');
        return j;
      });
  }

  function apiPost(body) {
    var headers = { 'Content-Type': 'application/json' };
    if (CSRF) headers['X-Admin-CSRF'] = CSRF;
    return fetch(ENDPOINT, {
      method: 'POST',
      credentials: 'same-origin',
      headers: headers,
      body: JSON.stringify(body),
    }).then(function (r) {
      return r.text().then(function (text) {
        var j;
        try { j = JSON.parse(text); } catch (e) {
          throw new Error(text.slice(0, 180) || ('HTTP ' + r.status));
        }
        if (!j.success) throw new Error(j.message || 'Eroare API');
        return j;
      });
    });
  }

  function channelCfg() {
    if (!state.config || !state.config.channels) return {};
    return state.config.channels[state.activeChannel] || {};
  }

  function ensureChannelConfig() {
    if (!state.config) state.config = {};
    if (!state.config.channels) state.config.channels = {};
    if (!state.config.channels[state.activeChannel]) {
      state.config.channels[state.activeChannel] = {
        mode: 'segments',
        segments: [],
        template: '',
        separator: ' ',
        vehicle_prefix: 'pentru',
        compat_prefix: 'pentru',
        max_length: 150,
        uppercase: false,
      };
    }
  }

  function syncFormFromChannel() {
    var ch = channelCfg();
    document.querySelectorAll('input[name="pcf_mode"]').forEach(function (r) {
      r.checked = r.value === (ch.mode || 'segments');
    });
    var templatePane = document.getElementById('bpa-pcf-template-pane');
    var segmentsPane = document.getElementById('bpa-pcf-segments-pane');
    if (templatePane) {
      templatePane.classList.toggle('bpa-pcf-is-hidden', (ch.mode || 'segments') !== 'template');
    }
    if (segmentsPane) {
      segmentsPane.classList.toggle('bpa-pcf-is-hidden', (ch.mode || 'segments') === 'template');
    }
    setFieldValue('bpa-pcf-template', ch.template || '');
    setFieldValue('bpa-pcf-separator', ch.separator != null ? ch.separator : ' ');
    setFieldValue('bpa-pcf-vehicle-prefix', ch.vehicle_prefix || 'pentru');
    setFieldValue('bpa-pcf-compat-prefix', ch.compat_prefix || 'pentru');
    setFieldValue('bpa-pcf-max-length', ch.max_length || 150);
    setFieldValue('bpa-pcf-uppercase', !!ch.uppercase);
    renderSegmentsList();
  }

  function syncChannelFromForm() {
    ensureChannelConfig();
    var ch = channelCfg();
    if (!ch || !state.config) return;
    var modeEl = document.querySelector('input[name="pcf_mode"]:checked');
    ch.mode = modeEl ? modeEl.value : (ch.mode || 'segments');
    ch.template = String(fieldValue('bpa-pcf-template', ch.template || '') || '');
    ch.separator = String(fieldValue('bpa-pcf-separator', ch.separator != null ? ch.separator : ' ') || ' ');
    ch.vehicle_prefix = String(fieldValue('bpa-pcf-vehicle-prefix', ch.vehicle_prefix || 'pentru') || 'pentru');
    ch.compat_prefix = String(fieldValue('bpa-pcf-compat-prefix', ch.compat_prefix || 'pentru') || 'pentru');
    ch.max_length = parseInt(String(fieldValue('bpa-pcf-max-length', ch.max_length || 150)), 10) || 150;
    ch.uppercase = !!fieldValue('bpa-pcf-uppercase', !!ch.uppercase);
    // Asigură referința pe canalul activ (nu obiect gol).
    state.config.channels[state.activeChannel] = ch;
  }

  function renderChannelTabs() {
    var wrap = document.getElementById('bpa-pcf-channels');
    if (!wrap) return;
    var keys = Object.keys(state.channels);
    if (state.scopeMode === 'project' && state.scopeValue) {
      keys = keys.filter(function (k) { return k === state.scopeValue; });
    }
    wrap.innerHTML = keys.map(function (key) {
      var meta = state.channels[key] || {};
      var active = key === state.activeChannel ? ' is-active' : '';
      return '<button type="button" class="bpa-pcf-channel' + active + '" data-channel="' + esc(key) + '" role="tab">'
        + esc(meta.label || key) + '</button>';
    }).join('');
  }

  function renderPlaceholders() {
    var ul = document.getElementById('bpa-pcf-placeholders');
    if (!ul) return;
    ul.innerHTML = Object.keys(state.segments).map(function (id) {
      var seg = state.segments[id];
      return '<li><code>' + esc(seg.placeholder || ('{' + id + '}')) + '</code> — ' + esc(seg.label || id) + '</li>';
    }).join('');
  }

  function renderSegmentsList() {
    var ul = document.getElementById('bpa-pcf-segments');
    var ch = channelCfg();
    if (!ul || !ch.segments) return;
    ul.innerHTML = ch.segments.map(function (seg, idx) {
      var meta = state.segments[seg.id] || {};
      return '<li class="bpa-pcf-seg" data-idx="' + idx + '">'
        + '<label class="bpa-pcf-seg__check"><input type="checkbox" data-seg-id="' + esc(seg.id) + '" ' + (seg.enabled ? 'checked' : '') + '> '
        + esc(meta.label || seg.id) + '</label>'
        + '<span class="bpa-pcf-seg__hint">' + esc(meta.hint || '') + '</span>'
        + '<span class="bpa-pcf-seg__order">'
        + '<button type="button" class="bpa-pcf-seg__btn" data-move="up" data-idx="' + idx + '" aria-label="Sus">↑</button>'
        + '<button type="button" class="bpa-pcf-seg__btn" data-move="down" data-idx="' + idx + '" aria-label="Jos">↓</button>'
        + '</span></li>';
    }).join('');
  }

  function renderPreview(data) {
    var titleEl = document.getElementById('bpa-pcf-preview-title');
    var segEl = document.getElementById('bpa-pcf-preview-segments');
    if (titleEl) titleEl.textContent = (data && data.title) || '—';
    if (!segEl || !data || !data.segments) return;
    segEl.innerHTML = Object.keys(data.segments).map(function (key) {
      var val = data.segments[key];
      if (!val) return '';
      var label = (state.segments[key] && state.segments[key].label) || key;
      return '<div><dt>' + esc(label) + '</dt><dd>' + esc(val) + '</dd></div>';
    }).join('');
  }

  var previewTimer = null;
  function schedulePreview() {
    clearTimeout(previewTimer);
    previewTimer = setTimeout(runPreview, 280);
  }

  function runPreview() {
    syncChannelFromForm();
    var sample = document.getElementById('bpa-pcf-sample').value || 'default';
    apiPost({
      action: 'preview',
      channel: state.activeChannel,
      config: state.config,
      sample: sample,
      scope: currentScopeId(),
    }).then(function (j) {
      renderPreview(j.data);
    }).catch(function (e) {
      toast(e.message, true);
    });
  }

  function load() {
    return apiGet({ scope: 'default' }).then(function (j) {
      state.fullConfig = j.data.config;
      state.segments = j.data.segments || {};
      state.channels = j.data.channels || {};
      state.samples = j.data.samples || {};
      state.tabFields = j.data.tab_fields || {};
      state.tabColumns = j.data.tab_columns || {};
      state.tabFormats = j.data.tab_formats || {};
      state.tabChartSources = j.data.tab_chart_sources || {};
      state.tabChartTypes = j.data.tab_chart_types || {};
      state.tabChartPeriods = j.data.tab_chart_periods || {};
      state.tabChartMetrics = j.data.tab_chart_metrics || {};
      state.categories = j.data.categories || [];
      state.savedScopes = j.data.saved_scopes || [];
      renderScopeControls();
      applyEditorData(j.data.editor || {});
    });
  }

  bindAll('[data-page-mode]', 'click', function (e) {
    var btn = e.target.closest('[data-page-mode]');
    if (btn) setPageMode(btn.getAttribute('data-page-mode'));
  });

  bindEl('bpa-pcf-add-tab', 'click', addTab);

  bindEl('bpa-pcf-delete-tab', 'click', function () {
    deleteTabAt(state.activeTabIdx);
  });

  bindEl('bpa-pcf-add-field-btn', 'click', function () {
    var sel = document.getElementById('bpa-pcf-add-field-id');
    if (sel && sel.value) addFieldToTab(sel.value);
  });

  bindEl('bpa-pcf-add-column-btn', 'click', function () {
    var sel = document.getElementById('bpa-pcf-add-column-id');
    if (sel && sel.value) addColumnToTab(sel.value);
  });

  bindEl('bpa-pcf-add-chart-metric-btn', 'click', function () {
    var sel = document.getElementById('bpa-pcf-add-chart-metric-id');
    if (sel && sel.value) addChartMetricToTab(sel.value);
  });

  bindEl('bpa-pcf-add-chart-custom-btn', 'click', addCustomChartMetric);

  bindEl('bpa-pcf-add-all-chart-metrics-btn', 'click', addAllChartMetrics);

  bindEl('bpa-pcf-tab-chart-metrics', 'click', function (e) {
    var tab = activeTab();
    if (!tab || !tab.chart_metrics) return;
    var removeBtn = e.target.closest('[data-chart-metric-remove]');
    if (removeBtn) {
      removeChartMetricAt(parseInt(removeBtn.getAttribute('data-chart-metric-remove'), 10));
      return;
    }
    var moveBtn = e.target.closest('[data-chart-metric-move]');
    if (moveBtn) {
      moveListItem(tab.chart_metrics, parseInt(moveBtn.getAttribute('data-idx'), 10), moveBtn.getAttribute('data-chart-metric-move'));
      renderTabChartMetricsList(tab);
      scheduleTabsPreview();
    }
  });

  bindEl('bpa-pcf-tab-chart-metrics', 'change', function (e) {
    var tab = activeTab();
    if (!tab || !tab.chart_metrics) return;
    var cb = e.target.closest('input[data-chart-metric-enabled]');
    if (cb) {
      var idx = parseInt(cb.getAttribute('data-chart-metric-enabled'), 10);
      var enabledCount = tab.chart_metrics.filter(function (m) { return m.enabled; }).length;
      if (!cb.checked && enabledCount <= 1) {
        cb.checked = true;
        toast('Trebuie să rămână cel puțin o dată activă.', true);
        return;
      }
      tab.chart_metrics[idx].enabled = cb.checked;
      scheduleTabsPreview();
    }
  });

  bindEl('bpa-pcf-tab-chart-metrics', 'input', function (e) {
    var tab = activeTab();
    if (!tab || !tab.chart_metrics) return;
    var inp = e.target.closest('input[data-chart-metric-label]');
    if (inp) {
      tab.chart_metrics[parseInt(inp.getAttribute('data-chart-metric-label'), 10)].label = inp.value;
      scheduleTabsPreview();
      return;
    }
    var valInp = e.target.closest('input[data-chart-metric-value]');
    if (valInp) {
      tab.chart_metrics[parseInt(valInp.getAttribute('data-chart-metric-value'), 10)].static_value = valInp.value;
      scheduleTabsPreview();
    }
  });

  bindEl('bpa-pcf-tabs-list', 'click', function (e) {
    var removeBtn = e.target.closest('[data-tab-remove]');
    if (removeBtn) {
      e.preventDefault();
      deleteTabAt(parseInt(removeBtn.getAttribute('data-tab-remove'), 10));
      return;
    }
    var item = e.target.closest('[data-tab-idx]');
    if (item && !e.target.closest('[data-tab-move]') && !e.target.closest('[data-tab-remove]') && !e.target.matches('input[type="checkbox"]')) {
      syncTabFormToState();
      state.activeTabIdx = parseInt(item.getAttribute('data-tab-idx'), 10);
      renderTabsList();
      syncTabFormFromState();
      return;
    }
    var moveBtn = e.target.closest('[data-tab-move]');
    if (moveBtn) {
      syncTabFormToState();
      var tabs = tabsCfg().tabs || [];
      moveListItem(tabs, parseInt(moveBtn.getAttribute('data-idx'), 10), moveBtn.getAttribute('data-tab-move'));
      renderTabsList();
      scheduleTabsPreview();
    }
  });

  bindEl('bpa-pcf-tabs-list', 'change', function (e) {
    var cb = e.target.closest('input[data-tab-enabled]');
    if (!cb) return;
    var tabs = tabsCfg().tabs || [];
    var idx = parseInt(cb.getAttribute('data-tab-enabled'), 10);
    if (tabs[idx]) tabs[idx].enabled = cb.checked;
    renderTabsList();
    scheduleTabsPreview();
  });

  ['bpa-pcf-tab-label', 'bpa-pcf-tab-enabled', 'bpa-pcf-tab-format', 'bpa-pcf-tab-source', 'bpa-pcf-tab-footnote', 'bpa-pcf-tabs-sample',
    'bpa-pcf-tab-chart-source', 'bpa-pcf-tab-chart-type', 'bpa-pcf-tab-chart-period', 'bpa-pcf-tab-chart-title', 'bpa-pcf-tab-prose-words']
    .forEach(function (id) {
      var el = document.getElementById(id);
      if (!el) return;
      el.addEventListener('input', function () {
        syncTabFormToState();
        if (id === 'bpa-pcf-tab-format' || id === 'bpa-pcf-tab-source' || id === 'bpa-pcf-tab-chart-source') {
          updateTabEditorPanes(activeTab() || {});
        }
        scheduleTabsPreview();
      });
      el.addEventListener('change', function () {
        syncTabFormToState();
        if (id === 'bpa-pcf-tab-format' || id === 'bpa-pcf-tab-source' || id === 'bpa-pcf-tab-chart-source') {
          var tab = activeTab();
          if (tab) {
            if (tab.format === 'table' && tab.source === 'compat_entries' && (!tab.columns || !tab.columns.length)) {
              tab.columns = defaultColumnsList();
            }
            if (tab.format === 'chart') {
              ensureTabStructure(tab);
              if (id === 'bpa-pcf-tab-chart-source') {
                tab.chart_metrics = defaultChartMetricsList(tab.chart_source || 'price_diversity');
                tab.chart_type = defaultChartTypeForSource(tab.chart_source || 'price_diversity');
              }
            } else if (tab.format === 'prose') {
              if (!tab.prose_words) tab.prose_words = 400;
            } else if (tab.format !== 'reviews' && tab.format !== 'html' && !(tab.format === 'table' && tab.source === 'compat_entries')) {
              if (!tab.fields || !tab.fields.length) tab.fields = defaultFieldsList();
            }
          }
          updateTabEditorPanes(activeTab() || {});
          syncTabFormFromState();
        }
        scheduleTabsPreview();
      });
    });

  bindEl('bpa-pcf-tab-fields', 'click', function (e) {
    var tab = activeTab();
    if (!tab || !tab.fields) return;
    var removeBtn = e.target.closest('[data-field-remove]');
    if (removeBtn) {
      removeFieldAt(parseInt(removeBtn.getAttribute('data-field-remove'), 10));
      return;
    }
    var moveBtn = e.target.closest('[data-field-move]');
    if (moveBtn) {
      moveListItem(tab.fields, parseInt(moveBtn.getAttribute('data-idx'), 10), moveBtn.getAttribute('data-field-move'));
      renderTabFieldsList(tab);
      scheduleTabsPreview();
    }
  });

  bindEl('bpa-pcf-tab-fields', 'change', function (e) {
    var tab = activeTab();
    if (!tab || !tab.fields) return;
    var cb = e.target.closest('input[data-field-enabled]');
    if (cb) {
      tab.fields[parseInt(cb.getAttribute('data-field-enabled'), 10)].enabled = cb.checked;
      scheduleTabsPreview();
    }
  });

  bindEl('bpa-pcf-tab-fields', 'input', function (e) {
    var tab = activeTab();
    if (!tab || !tab.fields) return;
    var inp = e.target.closest('input[data-field-label]');
    if (inp) {
      tab.fields[parseInt(inp.getAttribute('data-field-label'), 10)].label = inp.value;
      scheduleTabsPreview();
    }
  });

  bindEl('bpa-pcf-tab-columns', 'click', function (e) {
    var tab = activeTab();
    if (!tab || !tab.columns) return;
    var removeBtn = e.target.closest('[data-col-remove]');
    if (removeBtn) {
      removeColumnAt(parseInt(removeBtn.getAttribute('data-col-remove'), 10));
      return;
    }
    var moveBtn = e.target.closest('[data-col-move]');
    if (moveBtn) {
      moveListItem(tab.columns, parseInt(moveBtn.getAttribute('data-idx'), 10), moveBtn.getAttribute('data-col-move'));
      renderTabColumnsList(tab);
      scheduleTabsPreview();
    }
  });

  bindEl('bpa-pcf-tab-columns', 'change', function (e) {
    var tab = activeTab();
    if (!tab || !tab.columns) return;
    var cb = e.target.closest('input[data-col-enabled]');
    if (cb) {
      tab.columns[parseInt(cb.getAttribute('data-col-enabled'), 10)].enabled = cb.checked;
      scheduleTabsPreview();
    }
  });

  bindEl('bpa-pcf-tab-columns', 'input', function (e) {
    var tab = activeTab();
    if (!tab || !tab.columns) return;
    var inp = e.target.closest('input[data-col-label]');
    if (inp) {
      tab.columns[parseInt(inp.getAttribute('data-col-label'), 10)].label = inp.value;
      scheduleTabsPreview();
    }
  });

  bindEl('bpa-pcf-tabs-preview', 'click', function (e) {
    var btn = e.target.closest('[data-preview-tab]');
    if (!btn) return;
    var idx = btn.getAttribute('data-preview-tab');
    btn.parentElement.querySelectorAll('[data-preview-tab]').forEach(function (b) {
      b.classList.toggle('is-active', b.getAttribute('data-preview-tab') === idx);
    });
    btn.closest('.bpa-pcf-tabs-preview').querySelectorAll('[data-preview-pane]').forEach(function (pane) {
      pane.classList.toggle('is-active', pane.getAttribute('data-preview-pane') === idx);
    });
  });

  function applySavedConfig(data, message) {
    if (data && data.config) state.fullConfig = data.config;
    if (data && data.saved_scopes) state.savedScopes = data.saved_scopes;
    applyEditorData((data && data.editor) || {});
    toast(message || 'Salvat.');
  }

  function saveCurrentScope(message) {
    try {
      if (!ENDPOINT) {
        return Promise.reject(new Error('API Formare carte indisponibil — reîncarcă pagina.'));
      }
      if (state.pageMode === 'title') syncChannelFromForm();
      if (state.pageMode === 'tabs') syncTabFormToState();
      if (state.scopeMode === 'project' && state.scopeValue) {
        state.activeChannel = state.scopeValue;
        ensureChannelConfig();
      }
      return apiPost({
        action: 'save',
        scope: currentScopeId(),
        config: state.config,
      }).then(function (j) {
        applySavedConfig(j.data, j.message || message || 'Reguli salvate.');
        return j;
      });
    } catch (err) {
      return Promise.reject(err instanceof Error ? err : new Error(String(err)));
    }
  }

  function runSave(message) {
    var saveBtn = document.getElementById('bpa-pcf-save');
    var saveTabsBtn = document.getElementById('bpa-pcf-save-tabs');
    var btn = state.pageMode === 'tabs' ? saveTabsBtn : saveBtn;
    var prev = btn ? btn.innerHTML : '';
    if (btn) {
      btn.disabled = true;
      btn.setAttribute('aria-busy', 'true');
      btn.textContent = 'Se salvează…';
    }
    return saveCurrentScope(message)
      .catch(function (e) {
        var errMsg = (e && e.message) ? e.message : 'Salvarea a eșuat.';
        toast(errMsg, true);
        console.error('[product-formation] save failed', e);
      })
      .finally(function () {
        if (btn) {
          btn.disabled = false;
          btn.removeAttribute('aria-busy');
          btn.innerHTML = prev || (state.pageMode === 'tabs'
            ? 'Salvează tab-uri'
            : '<i class="fa-solid fa-floppy-disk"></i> Salvează regulile');
        }
      });
  }

  bindEl('bpa-pcf-save-tabs', 'click', function () {
    syncTabFormToState();
    runSave('Tab-uri salvate.');
  });

  bindEl('bpa-pcf-reset-tabs', 'click', function () {
    if (!confirm('Resetezi tab-urile pentru profilul activ la valorile implicite?')) return;
    apiPost({ action: 'reset', scope: currentScopeId(), scope_part: 'tabs' })
      .then(function (j) {
        state.activeTabIdx = 0;
        applySavedConfig(j.data, j.message || 'Reset.');
      })
      .catch(function (e) { toast(e.message, true); });
  });

  bindEl('bpa-pcf-channels', 'click', function (e) {
    var btn = e.target.closest('[data-channel]');
    if (!btn) return;
    syncChannelFromForm();
    state.activeChannel = btn.getAttribute('data-channel');
    renderChannelTabs();
    syncFormFromChannel();
    schedulePreview();
  });

  bindEl('bpa-pcf-segments', 'click', function (e) {
    var moveBtn = e.target.closest('[data-move]');
    if (!moveBtn) return;
    var ch = channelCfg();
    var idx = parseInt(moveBtn.getAttribute('data-idx'), 10);
    var dir = moveBtn.getAttribute('data-move');
    if (!ch.segments || idx < 0) return;
    var next = dir === 'up' ? idx - 1 : idx + 1;
    if (next < 0 || next >= ch.segments.length) return;
    var tmp = ch.segments[idx];
    ch.segments[idx] = ch.segments[next];
    ch.segments[next] = tmp;
    renderSegmentsList();
    schedulePreview();
  });

  bindEl('bpa-pcf-segments', 'change', function (e) {
    var cb = e.target.closest('input[data-seg-id]');
    if (!cb) return;
    var ch = channelCfg();
    var id = cb.getAttribute('data-seg-id');
    ch.segments.forEach(function (seg) {
      if (seg.id === id) seg.enabled = cb.checked;
    });
    renderSegmentsList();
    schedulePreview();
  });

  document.querySelectorAll('input[name="pcf_mode"]').forEach(function (r) {
    r.addEventListener('change', function () {
      syncChannelFromForm();
      syncFormFromChannel();
      schedulePreview();
    });
  });

  ['bpa-pcf-template', 'bpa-pcf-separator', 'bpa-pcf-vehicle-prefix', 'bpa-pcf-compat-prefix', 'bpa-pcf-max-length', 'bpa-pcf-uppercase', 'bpa-pcf-sample']
    .forEach(function (id) {
      var el = document.getElementById(id);
      if (!el) return;
      el.addEventListener('input', schedulePreview);
      el.addEventListener('change', schedulePreview);
    });

  bindEl('bpa-pcf-save', 'click', function () {
    runSave('Reguli salvate.');
  });

  bindEl('bpa-pcf-reset-channel', 'click', function () {
    if (!confirm('Resetezi regulile canalului activ pentru profilul curent?')) return;
    apiPost({ action: 'reset', scope: currentScopeId(), scope_part: 'channel', channel: state.activeChannel })
      .then(function (j) {
        applySavedConfig(j.data, j.message || 'Reset.');
      })
      .catch(function (e) { toast(e.message, true); });
  });

  document.querySelectorAll('input[name="pcf_scope_mode"]').forEach(function (r) {
    r.addEventListener('change', function () {
      switchScopeMode(r.value, null).catch(function (e) { toast(e.message, true); });
    });
  });

  bindEl('bpa-pcf-scope-category', 'change', function (e) {
    if (state.scopeMode !== 'category') return;
    switchScopeMode('category', e.target.value).catch(function (err) { toast(err.message, true); });
  });

  bindEl('bpa-pcf-scope-project', 'change', function (e) {
    if (state.scopeMode !== 'project') return;
    switchScopeMode('project', e.target.value).catch(function (err) { toast(err.message, true); });
  });

  load().catch(function (e) {
    toast('Eroare încărcare: ' + e.message, true);
    var page = document.getElementById('bpa-pcf-page');
    if (page && !page.querySelector('.bpa-pcf-load-err')) {
      page.insertAdjacentHTML('beforeend', '<div class="bpa-pcf-load-err rounded-md border border-danger/30 bg-danger/5 p-4 text-sm">' + esc(e.message) + '</div>');
    }
  });
})();
