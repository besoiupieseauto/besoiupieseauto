/**
 * Centru creștere marketing — Indicatori / Plan / Conținut (API + localStorage).
 */
(function () {
  'use strict';

  const API = '/admin/api/marketing_hub_endpoint.php';
  const STORAGE_KEY = 'bws_marketing_growth_hub_v1';
  const PLANNER_KEY = 'bws_marketing_planner_v3';

  const IND_PRESETS = {
    site: { platform: 'besoiupieseauto.ro', url: 'https://besoiupieseauto.ro', metric: 'Vizite / lună', value: 0, target: 5000, source: 'manual' },
    pieseauto: { platform: 'PieseAuto.ro', url: 'https://www.pieseauto.ro', metric: 'Vizualizări anunțuri', value: 0, target: 1000, source: 'scrape' },
    facebook: { platform: 'Facebook', url: 'https://facebook.com', metric: 'Reach postări / lună', value: 0, target: 3000, source: 'manual' },
    google: { platform: 'Google Search', url: 'https://search.google.com/search-console', metric: 'Impresii căutare', value: 0, target: 10000, source: 'manual' },
    whatsapp: { platform: 'WhatsApp Business', url: '', metric: 'Conversații / lună', value: 0, target: 150, source: 'manual' },
  };

  const CONTENT_COLS = ['draft', 'review', 'ready', 'scheduled', 'published'];

  let state = null;
  let insights = null;
  let alerts = [];
  let catalogPlaybooks = [];
  let catalogTemplates = [];
  let storageMode = 'none';
  let modalContext = null;
  let saveTimer = null;
  let contentView = 'board';
  let pageMode = 'indicators';
  let seoIntel = { snapshots: [], keywords: [], competitors: [] };
  let indTab = 'general';

  function uid(prefix) {
    return prefix + '_' + Date.now().toString(36) + Math.random().toString(36).slice(2, 6);
  }

  function defaultState() {
    return { version: 1, indicators: [], actions: [], content: [], okrs: [] };
  }

  function loadStateLocal() {
    try {
      var raw = localStorage.getItem(STORAGE_KEY);
      if (raw) {
        var parsed = JSON.parse(raw);
        if (parsed && typeof parsed === 'object') {
          return {
            version: 1,
            indicators: Array.isArray(parsed.indicators) ? parsed.indicators : [],
            actions: Array.isArray(parsed.actions) ? parsed.actions : [],
            content: Array.isArray(parsed.content) ? parsed.content : [],
            okrs: Array.isArray(parsed.okrs) ? parsed.okrs : [],
          };
        }
      }
    } catch (e) { /* ignore */ }
    return defaultState();
  }

  function saveStateLocal() {
    try {
      localStorage.setItem(STORAGE_KEY, JSON.stringify({
        version: 1,
        indicators: state.indicators,
        actions: state.actions,
        content: state.content,
        okrs: state.okrs || [],
      }));
    } catch (e) { /* ignore */ }
  }

  function localHasData(data) {
    return (data.indicators && data.indicators.length > 0) ||
      (data.actions && data.actions.length > 0) ||
      (data.content && data.content.length > 0);
  }

  function dbHasData(data) {
    return localHasData(data);
  }

  function fmtNum(n) {
    var v = Number(n);
    if (!Number.isFinite(v)) return '—';
    return v.toLocaleString('ro-RO');
  }

  function fmtDate(iso) {
    if (!iso) return '—';
    try {
      return new Date(iso).toLocaleDateString('ro-RO', { day: '2-digit', month: 'short', year: 'numeric' });
    } catch (e) {
      return String(iso);
    }
  }

  function pct(current, target) {
    var c = Number(current) || 0;
    var t = Number(target) || 0;
    if (t <= 0) return c > 0 ? 100 : 0;
    return Math.min(999, Math.round((c / t) * 100));
  }

  function isLowerBetter(ind) {
    var m = String((ind && ind.metric) || '').toLowerCase();
    return /oem|lips[aă]|neg[aă]sit|missing|f[aă]r[aă]/.test(m);
  }

  function indProgress(ind) {
    var c = Number(ind.value) || 0;
    var t = Number(ind.target) || 0;
    if (isLowerBetter(ind)) {
      if (c <= 0) return 100;
      if (t <= 0) return 100;
      if (c <= t) return 100;
      return Math.min(100, Math.round((t / c) * 100));
    }
    return pct(c, t);
  }

  function indNeedsAction(ind) {
    if (isLowerBetter(ind)) {
      var c = Number(ind.value) || 0;
      var t = Number(ind.target) || 0;
      return t > 0 && c > t;
    }
    return pct(ind.value, ind.target) < 60;
  }

  function showOverlay(id) {
    var el = document.getElementById(id);
    if (!el) return;
    el.classList.remove('hidden');
    el.setAttribute('aria-hidden', 'false');
    document.body.classList.add('bmgh-modal-open');
  }

  function hideOverlay(id) {
    var el = document.getElementById(id);
    if (!el) return;
    el.classList.add('hidden');
    el.setAttribute('aria-hidden', 'true');
    if (!document.querySelector('.bmgh-overlay:not(.hidden)')) {
      document.body.classList.remove('bmgh-modal-open');
    }
  }

  function escapeHtml(s) {
    return String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  function actionScore(impact, effort, priority, status) {
    if (status === 'done') return 0;
    var pBonus = priority === 'high' ? 30 : priority === 'low' ? -10 : 0;
    return Math.max(0, (Number(impact) || 3) * 20 + pBonus - (Number(effort) || 3) * 5);
  }

  function recalcActionScore(act) {
    act.score = actionScore(act.impact, act.effort, act.priority, act.status);
    return act.score;
  }

  function showToast(msg, isWarn) {
    var el = document.getElementById('bmgh-toast');
    if (!el) return;
    el.textContent = msg;
    el.classList.remove('hidden', 'bmgh-toast--warn');
    if (isWarn) el.classList.add('bmgh-toast--warn');
    el.classList.add('is-visible');
    clearTimeout(showToast._t);
    showToast._t = setTimeout(function () {
      el.classList.remove('is-visible');
      setTimeout(function () { el.classList.add('hidden'); }, 300);
    }, 3200);
  }

  function refreshIcons() {
    if (typeof lucide !== 'undefined' && lucide.createIcons) {
      lucide.createIcons();
    }
  }

  function updateStorageMeta(mode) {
    var el = document.getElementById('bmgh-storage-meta');
    if (!el) return;
    var label = mode === 'database' ? 'Stocare: bază de date' : 'Stocare: local (migrare disponibilă)';
    el.textContent = label + ' · actualizat ' + new Date().toLocaleTimeString('ro-RO', { hour: '2-digit', minute: '2-digit' });
  }

  function applyApiData(data, opts) {
    opts = opts || {};
    var rawActions = Array.isArray(data.actions) ? data.actions : [];
    var actions = dedupeActions(rawActions);
    state = {
      version: 1,
      indicators: Array.isArray(data.indicators) ? data.indicators : [],
      actions: actions,
      content: Array.isArray(data.content) ? data.content : [],
      okrs: Array.isArray(data.okrs) ? data.okrs : [],
    };
    insights = data.insights || null;
    alerts = Array.isArray(data.alerts) ? data.alerts : [];
    catalogPlaybooks = Array.isArray(data.playbooks) ? data.playbooks : [];
    catalogTemplates = Array.isArray(data.content_templates) ? data.content_templates : [];
    storageMode = data.storage || 'database';
    seoIntel = data.seo_intel && typeof data.seo_intel === 'object'
      ? data.seo_intel
      : { snapshots: [], keywords: [], competitors: [] };
    saveStateLocal();
    if (!opts.skipDedupeSave && actions.length < rawActions.length) {
      var removed = rawActions.length - actions.length;
      setTimeout(function () {
        saveState();
        showToast('Eliminate ' + removed + ' acțiuni duplicate');
      }, 300);
    }
  }

  function actionFingerprint(act) {
    if (act.playbookKey) return 'pb:' + act.playbookKey;
    var title = String(act.title || '').trim().toLowerCase();
    var kpi = String(act.targetKpi || '').trim().toLowerCase();
    return 'manual:' + title + '|' + kpi;
  }

  function backfillPlaybookKey(act) {
    if (act.playbookKey) return act;
    var pb = catalogPlaybooks.find(function (p) { return p.title === act.title; });
    if (pb) act.playbookKey = pb.key;
    return act;
  }

  function dedupeActions(actions) {
    var list = (actions || []).map(function (a) {
      return backfillPlaybookKey(Object.assign({}, a));
    });
    list.sort(function (a, b) {
      return String(b.updatedAt || b.createdAt || '').localeCompare(String(a.updatedAt || a.createdAt || ''));
    });
    var openFp = {};
    var out = [];
    list.forEach(function (act) {
      if (act.status === 'done') {
        out.push(act);
        return;
      }
      var fp = actionFingerprint(act);
      if (openFp[fp]) return;
      openFp[fp] = true;
      out.push(act);
    });
    return out;
  }

  function apiGet(url) {
    return fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
      .then(function (r) { return r.json(); });
  }

  function apiPost(body) {
    return fetch(API, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify(body),
    }).then(function (r) { return r.json(); });
  }

  function loadFromApi() {
    return apiGet(API).then(function (json) {
      if (!json.success) throw new Error(json.message || 'Eroare API');
      var data = json.data || {};
      if (dbHasData(data)) {
        applyApiData(data);
        updateStorageMeta(data.storage || 'database');
        return;
      }
      var local = loadStateLocal();
      if (localHasData(local)) {
        return apiPost({ action: 'import_local', state: local }).then(function (imp) {
          if (imp.success && imp.data) {
            applyApiData(imp.data);
            showToast('Date locale importate în BD');
          } else {
            applyApiData(data);
            state = local;
            saveStateLocal();
          }
          updateStorageMeta(imp.data && imp.data.storage ? imp.data.storage : storageMode);
        });
      }
      applyApiData(data);
      updateStorageMeta(data.storage || 'none');
    }).catch(function (err) {
      state = loadStateLocal();
      insights = null;
      alerts = [];
      updateStorageMeta('local');
      showToast('Mod offline — ' + (err.message || 'API indisponibil'), true);
    });
  }

  function saveState() {
    state.actions = dedupeActions(state.actions);
    saveStateLocal();
    var hint = document.getElementById('bmgh-save-hint');
    if (hint) hint.textContent = 'Se salvează…';
    clearTimeout(saveTimer);
    saveTimer = setTimeout(function () {
      apiPost({ action: 'save_state', state: state }).then(function (json) {
        if (json.success) {
          if (json.data) {
            if (json.data.alerts) alerts = json.data.alerts;
            if (json.data.insights) insights = json.data.insights;
            storageMode = json.data.storage || storageMode;
          }
          var now = new Date().toLocaleTimeString('ro-RO', { hour: '2-digit', minute: '2-digit' });
          if (hint) hint.textContent = 'Salvat · ' + now;
          updateStorageMeta(storageMode);
        } else if (hint) {
          hint.textContent = 'Salvare locală · BD indisponibilă';
        }
      }).catch(function () {
        if (hint) hint.textContent = 'Salvat local · server indisponibil';
      });
    }, 800);
  }

  function syncLive() {
    var btn = document.getElementById('bmgh-sync-live');
    if (btn) btn.disabled = true;
    return apiPost({ action: 'sync_live' }).then(function (json) {
      if (btn) btn.disabled = false;
      if (!json.success) throw new Error(json.message || 'Sync eșuat');
      applyApiData(json.data || {});
      renderAll();
      showToast(json.message || 'KPI sincronizate din magazin');
    }).catch(function (err) {
      if (btn) btn.disabled = false;
      showToast(err.message || 'Sync live eșuat', true);
    });
  }

  function openWeeklyReview() {
    var body = document.getElementById('bmgh-review-body');
    if (!body) return;
    body.innerHTML = '<p>Se încarcă review-ul…</p>';
    showOverlay('bmgh-review-overlay');
    apiGet(API + '?action=review').then(function (json) {
      if (!json.success) throw new Error(json.message);
      body.innerHTML = renderReviewHtml(json.data || {});
      refreshIcons();
    }).catch(function (err) {
      body.innerHTML = '<p class="bmgh-review-error">' + escapeHtml(err.message || 'Eroare') + '</p>';
    });
  }

  function renderReviewHtml(data) {
    var s = data.summary || {};
    var live = data.live || {};
    var week = data.week_label || '';
    var alertRows = (data.alerts || []).slice(0, 6).map(function (a) {
      return '<li class="bmgh-review-alert bmgh-review-alert--' + escapeHtml(a.severity || 'warning') + '">' +
        '<strong>' + escapeHtml(a.title) + '</strong><span>' + escapeHtml(a.detail || '') + '</span></li>';
    }).join('');
    var missing = (data.top_missing_oem || []).slice(0, 5).map(function (m) {
      return '<tr><td>' + escapeHtml(m.code) + '</td><td>' + fmtNum(m.count) + '</td></tr>';
    }).join('');
    return '<div class="bmgh-review">' +
      '<p class="bmgh-review__week">' + escapeHtml(week) + '</p>' +
      '<div class="bmgh-review__grid">' +
      '<div class="bmgh-review__stat"><span>Indicatori sub țintă</span><strong>' + fmtNum(s.indicators_below_target) + ' / ' + fmtNum(s.indicators_total) + '</strong></div>' +
      '<div class="bmgh-review__stat"><span>Acțiuni finalizate</span><strong>' + fmtNum(s.actions_done) + '</strong></div>' +
      '<div class="bmgh-review__stat"><span>Acțiuni deschise</span><strong>' + fmtNum(s.actions_open) + '</strong></div>' +
      '<div class="bmgh-review__stat"><span>Conținut publicat</span><strong>' + fmtNum(s.content_published) + '</strong></div>' +
      '</div>' +
      '<h5>Pulse magazin</h5>' +
      '<div class="bmgh-review__live">' +
      'Căutări azi: <strong>' + fmtNum(live.searches_today) + '</strong> · ' +
      'Comenzi: <strong>' + fmtNum(live.orders_today) + '</strong> · ' +
      'Venit: <strong>' + fmtNum(live.revenue_today) + ' RON</strong> · ' +
      'Rată succes: <strong>' + fmtNum(live.success_rate) + '%</strong>' +
      '</div>' +
      (missing ? '<h5>Top OEM negăsite</h5><table class="bmgh-table bmgh-table--compact"><tbody>' + missing + '</tbody></table>' : '') +
      (alertRows ? '<h5>Alerte active</h5><ul class="bmgh-review__alerts">' + alertRows + '</ul>' : '<p class="bmgh-review__ok">Fără alerte critice.</p>') +
      '</div>';
  }

  function renderLiveStrip() {
    var live = insights && insights.live ? insights.live : {};
    var set = function (id, val) {
      var el = document.getElementById(id);
      if (el) el.textContent = val;
    };
    set('bmgh-live-searches', fmtNum(live.searches_today));
    set('bmgh-live-orders', fmtNum(live.orders_today));
    set('bmgh-live-revenue', fmtNum(live.revenue_today) + (live.revenue_today != null ? ' RON' : ''));
    set('bmgh-live-missing', fmtNum(live.missing_codes));
    set('bmgh-live-rate', live.success_rate != null ? fmtNum(live.success_rate) + '%' : '—');
  }

  function renderAlertsBar() {
    var bar = document.getElementById('bmgh-alerts-bar');
    if (!bar) return;
    if (!alerts.length) {
      bar.classList.add('hidden');
      bar.innerHTML = '';
      return;
    }
    bar.classList.remove('hidden');
    var seen = {};
    var unique = [];
    alerts.forEach(function (a) {
      var key = (a.title || '') + '|' + (a.detail || '');
      if (seen[key]) return;
      seen[key] = true;
      unique.push(a);
    });
    bar.innerHTML = '<strong class="bmgh-alerts-bar__title">⚠ Atenție — acțiuni recomandate</strong>' +
      unique.slice(0, 4).map(function (a) {
      return '<div class="bmgh-alert bmgh-alert--' + escapeHtml(a.severity || 'warning') + '">' +
        '<span><strong>' + escapeHtml(a.title) + '</strong> — ' + escapeHtml(a.detail || '') + '</span>' +
        (a.url ? '<a href="' + escapeHtml(a.url) + '" class="bmgh-alert__link">Deschide</a>' : '') +
        '</div>';
    }).join('');
  }

  function renderSuggestions() {
    var wrap = document.getElementById('bmgh-suggestions');
    var list = document.getElementById('bmgh-suggestions-list');
    if (!wrap || !list) return;
    var items = insights && Array.isArray(insights.suggestions) ? insights.suggestions : [];
    if (!items.length || wrap.classList.contains('is-dismissed')) {
      wrap.classList.add('hidden');
      return;
    }
    wrap.classList.remove('hidden');
    list.innerHTML = items.map(function (s, i) {
      return '<button type="button" class="bmgh-suggestion" data-bmgh-suggestion="' + escapeHtml(s.action || '') + '" data-sug-idx="' + i + '">' +
        '<span class="bmgh-suggestion__type">' + escapeHtml(s.type || 'tip') + '</span>' +
        '<strong>' + escapeHtml(s.title) + '</strong>' +
        '<span>' + escapeHtml(s.detail || '') + '</span></button>';
    }).join('');
  }

  function handleSuggestion(action) {
    if (action === 'create_missing_oem_indicator') {
      state.indicators.push({
        id: uid('ind'),
        platform: 'besoiupieseauto.ro',
        url: '/admin/searchlogs',
        metric: 'Coduri OEM lipsă din stoc',
        value: insights && insights.live ? insights.live.missing_codes || 0 : 0,
        target: 50,
        source: 'auto',
        notes: 'Adăugat din sugestie inteligentă',
        updatedAt: new Date().toISOString(),
      });
      saveState();
      renderAll();
      showToast('Indicator OEM adăugat');
      return;
    }
    if (action === 'playbook_seo_catalog') {
      addPlaybookAction('seo_catalog');
      return;
    }
    if (action === 'content_oem_post') {
      var top = insights && insights.top_missing_oem && insights.top_missing_oem[0];
      var code = top ? top.code : 'OEM';
      state.content.push({
        id: uid('cnt'),
        title: 'Post OEM trending: ' + code,
        platform: 'Facebook',
        format: 'post',
        status: 'draft',
        publishDate: '',
        linkedAction: '',
        body: '🔧 Căutare frecventă: ' + code + '\n\n✅ Verifică stoc pe besoiupieseauto.ro',
        createdAt: new Date().toISOString(),
        updatedAt: new Date().toISOString(),
      });
      saveState();
      renderAll();
      showToast('Draft conținut creat');
    }
  }

  function ringSvg(p) {
    var pctVal = Math.min(100, Math.max(0, p));
    var r = 42;
    var c = 2 * Math.PI * r;
    var off = c - (pctVal / 100) * c;
    var colorClass = pctVal >= 100 ? 'bmgh-ring--ok' : pctVal >= 60 ? 'bmgh-ring--mid' : 'bmgh-ring--low';
    return '<div class="bmgh-ring ' + colorClass + '">' +
      '<svg viewBox="0 0 100 100" aria-hidden="true">' +
      '<circle class="bmgh-ring__track" cx="50" cy="50" r="' + r + '"/>' +
      '<circle class="bmgh-ring__progress" cx="50" cy="50" r="' + r + '" stroke-dasharray="' + c + '" stroke-dashoffset="' + off + '"/>' +
      '</svg><span class="bmgh-ring__label">' + pctVal + '%</span></div>';
  }

  function renderIndScoreGrid() {
    var grid = document.getElementById('bmgh-ind-score-grid');
    if (!grid) return;
    var top = state.indicators.slice().sort(function (a, b) {
      return pct(b.value, b.target) - pct(a.value, a.target);
    }).slice(0, 6);
    if (!top.length) {
      grid.innerHTML = '<div class="bmgh-score-grid__empty">Adaugă indicatori pentru scoruri vizuale.</div>';
      return;
    }
    grid.innerHTML = top.map(function (ind) {
      var p = pct(ind.value, ind.target);
      return '<article class="bmgh-score-card">' + ringSvg(p) +
        '<div class="bmgh-score-card__body">' +
        '<strong>' + escapeHtml(ind.platform) + '</strong>' +
        '<span>' + escapeHtml(ind.metric) + '</span>' +
        '<span class="bmgh-score-card__vals">' + fmtNum(ind.value) + ' / ' + fmtNum(ind.target) + '</span>' +
        '</div></article>';
    }).join('');
  }

  function renderCounts() {
    ['indicators', 'actions', 'content'].forEach(function (key) {
      var el = document.getElementById('bmgh-count-' + key);
      if (el) el.textContent = String(state[key].length);
    });
  }

  function renderIndicators() {
    if (pageMode !== 'indicators') return;
    var grid = document.getElementById('bmgh-ind-cards');
    if (!grid) return;

    var general = state.indicators.filter(function (ind) {
      var c = ind.category || 'general';
      return c === 'general' || c === 'social' || c === 'marketplace' || !ind.category;
    });

    if (!general.length) {
      var calcHint = readPlannerCalc()
        ? 'Apasă <em>Împarte în indicatori</em> (banner verde sus) ca să legi ținta din Planner.'
        : 'Setează scenariul în <a href="/admin/planner">Planner</a>, apoi <em>Împarte în indicatori</em>.';
      grid.innerHTML = '<div class="bmgh-empty-state bmgh-empty-state--inline">' +
        '<strong>Niciun indicator pe canale</strong>' +
        '<p>' + calcHint + '</p></div>';
    } else {
      var calc = readPlannerCalc();
      grid.innerHTML = general.map(function (ind) {
      var p = indProgress(ind);
      var spec = indicatorSpec(ind);
      var lower = spec.lowerBetter || isLowerBetter(ind);
      var srcLabel = ind.source === 'scrape' ? 'Scraping' : ind.source === 'auto' ? 'Auto live' : 'Manual';
      var atTarget = lower ? (Number(ind.value) <= Number(ind.target)) : (p >= 100);
      var statusClass = atTarget ? 'is-ok' : 'is-warn';
      var statusText = lower
        ? (Number(ind.value) <= Number(ind.target) ? '✓ Sub țintă (bun)' : '⚠ Peste țintă — acționează')
        : (p >= 100 ? '✓ La țintă' : '⚠ Sub țintă — acționează');
      var whyText = indicatorWhyText(ind);
      var outcomeText = indicatorOutcome(ind, calc);
      var sliceText = plannerSliceLabel(ind, calc);
      var targetHint = autoTargetHint(ind, spec);
      var progressLabel = lower
        ? (Number(ind.value) <= Number(ind.target) ? 'Progres: ținta e „mai puțin”' : 'Depășit cu ' + fmtNum(Number(ind.value) - Number(ind.target)) + ' ' + spec.noun)
        : 'Progres spre țintă: ' + Math.min(100, p) + '%';
      var createBtn = indNeedsAction(ind)
        ? '<button type="button" class="bmgh-card-btn bmgh-card-btn--primary" data-bmgh-create-act="' + ind.id + '">' +
          '<span>Creează acțiune</span><small>Pași concreți spre ținta mare</small></button>'
        : '';
      return '<article class="bmgh-clear-card bmgh-metric-card ' + statusClass + '" data-ind-id="' + ind.id + '">' +
        '<div class="bmgh-metric-card__chips">' +
        '<span class="bmgh-metric-chip bmgh-metric-chip--' + escapeHtml(spec.chipTone) + '">' + escapeHtml(spec.chip) + '</span>' +
        '<span class="bmgh-metric-chip bmgh-metric-chip--period">' + escapeHtml(spec.period) + '</span>' +
        '</div>' +
        '<header class="bmgh-clear-card__head">' +
        '<div><strong class="bmgh-clear-card__title">' + escapeHtml(ind.metric) + '</strong>' +
        '<span class="bmgh-clear-card__metric">' + escapeHtml(ind.platform) + ' · ' + escapeHtml(srcLabel) + '</span></div>' +
        '</header>' +
        (sliceText ? '<p class="bmgh-clear-card__slice"><em>Legat de Planner:</em> ' + escapeHtml(sliceText) + '</p>' : '') +
        '<div class="bmgh-metric-card__explain">' +
        '<p><strong>Ce numără acest card:</strong> ' + escapeHtml(spec.measures) + '</p>' +
        '<p class="bmgh-metric-card__not"><strong>Nu sunt:</strong> ' + escapeHtml(spec.notThis) + '</p>' +
        '<p class="bmgh-metric-card__source"><strong>De unde vine cifra:</strong> ' + escapeHtml(spec.sourceFrom) + '</p>' +
        '</div>' +
        '<div class="bmgh-metric-card__compare">' +
        '<div class="bmgh-metric-box bmgh-metric-box--now">' +
        '<span class="bmgh-metric-box__label">ACUM — cât ai acum</span>' +
        '<strong class="bmgh-metric-box__val">' + fmtNum(ind.value) + '</strong>' +
        '<span class="bmgh-metric-box__unit">' + escapeHtml(spec.noun) + ' · ' + escapeHtml(spec.period) + '</span>' +
        '<span class="bmgh-metric-box__plain">' + escapeHtml(formatMetricAmount(ind.value, spec)) + '</span>' +
        '</div>' +
        '<div class="bmgh-metric-box__arrow" aria-hidden="true">→</div>' +
        '<div class="bmgh-metric-box bmgh-metric-box--target">' +
        '<span class="bmgh-metric-box__label">ȚINTĂ — unde vrei să ajungi</span>' +
        '<strong class="bmgh-metric-box__val">' + fmtNum(ind.target) + '</strong>' +
        '<span class="bmgh-metric-box__unit">' + escapeHtml(spec.noun) + ' · ' + escapeHtml(spec.period) + '</span>' +
        '<span class="bmgh-metric-box__plain">' + escapeHtml(formatMetricAmount(ind.target, spec)) + '</span>' +
        (targetHint ? '<small class="bmgh-metric-box__hint">' + escapeHtml(targetHint) + '</small>' : '') +
        '<small class="bmgh-metric-box__means">' + escapeHtml(spec.targetMeans) + '</small>' +
        '</div>' +
        '</div>' +
        '<div class="bmgh-clear-card__status-row">' +
        '<span class="bmgh-clear-card__status ' + statusClass + '">' + escapeHtml(statusText) + '</span>' +
        '<span class="bmgh-clear-card__progress-label">' + escapeHtml(progressLabel) + '</span>' +
        '</div>' +
        '<div class="bmgh-clear-card__bar"><div style="width:' + Math.min(100, p) + '%"></div></div>' +
        '<p class="bmgh-clear-card__outcome"><strong>Ce obții când atingi ținta:</strong> ' + escapeHtml(outcomeText) + '</p>' +
        '<p class="bmgh-clear-card__why"><em>De ce contează:</em> ' + whyText + '</p>' +
        '<footer class="bmgh-clear-card__foot">' + createBtn +
        '<button type="button" class="bmgh-card-btn" data-bmgh-edit-ind="' + ind.id + '">' +
        '<span>Editează</span><small>Schimbi valoarea sau ținta</small></button>' +
        '<button type="button" class="bmgh-card-btn bmgh-card-btn--danger" data-bmgh-del-ind="' + ind.id + '">' +
        '<span>Șterge</span><small>Elimini din listă</small></button></footer></article>';
      }).join('');
    }

    renderKeywords();
    renderSeoGrid();
  }

  function renderKeywords() {
    var tbody = document.getElementById('bmgh-kw-tbody');
    if (!tbody) return;
    var list = (seoIntel && Array.isArray(seoIntel.keywords)) ? seoIntel.keywords : [];
    if (!list.length) {
      tbody.innerHTML = '<tr><td colspan="7" class="bmgh-kw-empty">Niciun keyword — apasă <strong>Sincronizează keywords</strong> din Search Logs.</td></tr>';
      return;
    }
    tbody.innerHTML = list.map(function (kw) {
      return '<tr><td><strong>' + escapeHtml(kw.keyword) + '</strong></td>' +
        '<td>' + escapeHtml(kw.keywordType || '—') + '</td>' +
        '<td>' + fmtNum(kw.searches30d) + '</td>' +
        '<td>' + fmtNum(kw.foundRate) + '%</td>' +
        '<td>' + fmtNum(kw.impressions30d) + '</td>' +
        '<td>' + fmtNum(kw.clicks30d) + '</td>' +
        '<td><span class="bmgh-kw-src">' + escapeHtml(kw.source || '') + '</span></td></tr>';
    }).join('');
  }

  function renderSeoGrid() {
    var grid = document.getElementById('bmgh-seo-grid');
    if (!grid) return;
    var snaps = (seoIntel && Array.isArray(seoIntel.snapshots)) ? seoIntel.snapshots : [];
    var catalog = (seoIntel && Array.isArray(seoIntel.competitors)) ? seoIntel.competitors : [];
    if (!snaps.length && catalog.length) {
      grid.innerHTML = catalog.map(function (c) {
        return '<article class="bmgh-seo-card bmgh-seo-card--empty">' +
          '<strong>' + escapeHtml(c.label || c.domain) + '</strong>' +
          '<span>' + escapeHtml(c.domain) + '</span>' +
          '<button type="button" class="bmgh-card-btn bmgh-card-btn--primary" data-bmgh-scrape-domain="' + escapeHtml(c.domain) + '">' +
          '<span>Scanează acum</span><small>Title, meta, H1, scor SEO</small></button></article>';
      }).join('');
      return;
    }
    if (!snaps.length) {
      grid.innerHTML = '<div class="bmgh-empty-state bmgh-empty-state--inline">Apasă <strong>Scanează toți concurenții</strong>.</div>';
      return;
    }
    grid.innerHTML = snaps.map(function (s) {
      var own = s.isOwnSite ? ' bmgh-seo-card--own' : '';
      return '<article class="bmgh-seo-card' + own + '">' +
        '<header><strong>' + escapeHtml(s.label || s.domain) + '</strong>' +
        '<span class="bmgh-seo-score">' + fmtNum(s.seoScore) + '/100</span></header>' +
        '<p class="bmgh-seo-card__title">' + escapeHtml(s.pageTitle || '—') + '</p>' +
        '<dl class="bmgh-seo-card__stats">' +
        '<div><dt>Cuvinte</dt><dd>' + fmtNum(s.wordCount) + '</dd></div>' +
        '<div><dt>Linkuri int.</dt><dd>' + fmtNum(s.internalLinks) + '</dd></div>' +
        '<div><dt>H1</dt><dd>' + escapeHtml((s.h1 || '—').slice(0, 40)) + '</dd></div>' +
        '</dl>' +
        '<p class="bmgh-seo-card__meta">' + escapeHtml((s.metaDescription || '').slice(0, 120)) + '</p>' +
        '<footer><button type="button" class="bmgh-card-btn" data-bmgh-scrape-domain="' + escapeHtml(s.domain) + '">' +
        '<span>Re-scanează</span><small>' + escapeHtml(s.fetchSource || '') + ' · ' + fmtDate(s.fetchedAt) + '</small></button></footer></article>';
    }).join('');
  }

  function renderPostMetrics() {
    var wrap = document.getElementById('bmgh-post-metrics-grid');
    if (!wrap) return;
    var items = state.content.filter(function (c) {
      return c.status === 'published' || c.status === 'scheduled' || (c.metrics && Object.keys(c.metrics).length);
    }).slice(0, 12);
    if (!items.length) {
      wrap.innerHTML = '<p class="bmgh-empty-inline">Adaugă metrici la postări (reach, impresii) când editezi conținutul.</p>';
      return;
    }
    wrap.innerHTML = items.map(function (c) {
      var m = c.metrics || {};
      return '<article class="bmgh-post-metric-card">' +
        '<strong>' + escapeHtml(c.title) + '</strong>' +
        '<span>' + escapeHtml(c.platform || '—') + ' · ' + escapeHtml(c.status) + '</span>' +
        '<div class="bmgh-post-metric-card__nums">' +
        '<div><span>Reach</span><strong>' + fmtNum(m.reach) + '</strong></div>' +
        '<div><span>Impresii</span><strong>' + fmtNum(m.impressions) + '</strong></div>' +
        '<div><span>Like</span><strong>' + fmtNum(m.likes) + '</strong></div>' +
        '<div><span>Click</span><strong>' + fmtNum(m.clicks) + '</strong></div></div></article>';
    }).join('');
  }

  function playbookExists(key) {
    var pb = catalogPlaybooks.find(function (p) { return p.key === key; });
    return state.actions.some(function (a) {
      if (a.status === 'done') return false;
      if (a.playbookKey === key) return true;
      if (pb && a.title === pb.title) return true;
      return false;
    });
  }

  function renderPlaybooks() {
    var grid = document.getElementById('bmgh-playbooks-grid');
    if (!grid) return;
    var list = catalogPlaybooks.length ? catalogPlaybooks : [];
    if (!list.length) {
      grid.innerHTML = '<p class="bmgh-empty-state bmgh-empty-state--inline">Playbook-urile se încarcă…</p>';
      return;
    }
    grid.innerHTML = list.map(function (pb) {
      var exists = playbookExists(pb.key);
      var score = actionScore(pb.impact, pb.effort, pb.priority, 'todo');
      return '<article class="bmgh-playbook-tile' + (exists ? ' is-added' : '') + '">' +
        '<strong>' + escapeHtml(pb.title) + '</strong>' +
        '<p class="bmgh-playbook-tile__what"><em>Ce face:</em> ' + escapeHtml(pb.notes || pb.targetKpi || 'Task predefinit') + '</p>' +
        '<p class="bmgh-playbook-tile__why"><em>De ce:</em> Impact ' + (pb.impact || 3) + '/5 · prioritate ' +
        (pb.priority === 'high' ? 'urgentă' : pb.priority === 'low' ? 'scăzută' : 'medie') + '</p>' +
        (exists
          ? '<span class="bmgh-playbook-tile__done">✓ Deja în plan</span>'
          : '<button type="button" class="bmgh-card-btn bmgh-card-btn--primary" data-bmgh-playbook="' + escapeHtml(pb.key) + '">' +
            '<span>Adaugă în plan</span><small>Scor prioritate: ' + score + '</small></button>') +
        '</article>';
    }).join('');
  }

  function addPlaybookAction(key) {
    var pb = catalogPlaybooks.find(function (p) { return p.key === key; });
    if (!pb) return;
    if (playbookExists(key)) {
      showToast('Acest playbook e deja în plan', true);
      return;
    }
    var act = {
      id: uid('act'),
      title: pb.title,
      targetKpi: pb.targetKpi || '',
      indicatorId: null,
      deadline: '',
      priority: pb.priority || 'medium',
      status: 'todo',
      effort: pb.effort || 3,
      impact: pb.impact || 3,
      notes: pb.notes || '',
      playbookKey: pb.key,
      createdAt: new Date().toISOString(),
      updatedAt: new Date().toISOString(),
    };
    recalcActionScore(act);
    state.actions.push(act);
    saveState();
    renderAll();
    showToast('Acțiune adăugată din playbook');
  }

  function renderActions() {
    if (pageMode !== 'actions') return;
    var list = document.getElementById('bmgh-act-list');
    var empty = document.getElementById('bmgh-act-empty');
    if (!list || !empty) return;

    renderPlaybooks();

    var progress = 0, done = 0, urgent = 0;
    state.actions.forEach(function (a) {
      recalcActionScore(a);
      if (a.status === 'done') done++;
      else if (a.status === 'progress') progress++;
      if (a.priority === 'high' && a.status !== 'done') urgent++;
    });

    var setStat = function (id, v) { var el = document.getElementById(id); if (el) el.textContent = fmtNum(v); };
    setStat('bmgh-act-stat-total', state.actions.length);
    setStat('bmgh-act-stat-progress', progress);
    setStat('bmgh-act-stat-done', done);
    setStat('bmgh-act-stat-urgent', urgent);

    var sorted = state.actions.slice().sort(function (a, b) {
      return (b.score || 0) - (a.score || 0);
    });

    if (!sorted.length) {
      list.innerHTML = '';
      empty.classList.remove('hidden');
      return;
    }
    empty.classList.add('hidden');

    list.innerHTML = sorted.map(function (a) {
      var score = a.score || recalcActionScore(a);
      var statusLabel = a.status === 'done' ? 'Finalizat' : a.status === 'progress' ? 'În curs' : 'De făcut';
      return '<article class="bmgh-clear-card bmgh-clear-card--action" data-act-id="' + a.id + '">' +
        '<header class="bmgh-clear-card__head">' +
        '<div><strong class="bmgh-clear-card__title">' + escapeHtml(a.title) + '</strong>' +
        (a.targetKpi ? '<span class="bmgh-clear-card__metric">Țintă: ' + escapeHtml(a.targetKpi) + '</span>' : '') +
        '</div><span class="bmgh-clear-card__badge">Prioritate ' + score + '</span></header>' +
        '<div class="bmgh-clear-card__nums bmgh-clear-card__nums--3">' +
        '<div><span>Status</span><strong>' + escapeHtml(statusLabel) + '</strong></div>' +
        '<div><span>Impact</span><strong>' + (a.impact || 3) + '/5</strong></div>' +
        '<div><span>Efort</span><strong>' + (a.effort || 3) + '/5</strong></div></div>' +
        (a.notes ? '<p class="bmgh-clear-card__why">' + escapeHtml(a.notes) + '</p>' : '') +
        '<footer class="bmgh-clear-card__foot">' +
        '<label class="bmgh-card-btn bmgh-card-btn--select">' +
        '<span>Schimbă status</span><small>Ce: marchezi progresul</small>' +
        '<select data-bmgh-act-status="' + a.id + '">' +
        ['todo', 'progress', 'done'].map(function (s) {
          return '<option value="' + s + '"' + (a.status === s ? ' selected' : '') + '>' +
            (s === 'todo' ? 'De făcut' : s === 'progress' ? 'În curs' : 'Finalizat') + '</option>';
        }).join('') + '</select></label>' +
        (a.status !== 'done'
          ? '<button type="button" class="bmgh-card-btn bmgh-card-btn--primary" data-bmgh-cnt-from-act="' + a.id + '">' +
            '<span>Creează conținut</span><small>De ce: pregătești postarea</small></button>'
          : '') +
        '<button type="button" class="bmgh-card-btn" data-bmgh-edit-act="' + a.id + '">' +
        '<span>Editează</span><small>Modifici detaliile</small></button>' +
        '<button type="button" class="bmgh-card-btn bmgh-card-btn--danger" data-bmgh-del-act="' + a.id + '">' +
        '<span>Șterge</span><small>Elimini task-ul</small></button></footer></article>';
    }).join('');
  }

  function renderContentTemplates() {
    var wrap = document.getElementById('bmgh-content-templates');
    if (!wrap) return;
    var list = catalogTemplates.length ? catalogTemplates : [];
    if (!list.length) {
      wrap.innerHTML = '<span class="bmgh-templates-bar__empty">—</span>';
      return;
    }
    wrap.innerHTML = list.map(function (t) {
      return '<button type="button" class="bmgh-quick-btn bmgh-quick-btn--sm" data-bmgh-template="' + escapeHtml(t.key) + '">' +
        '<strong>' + escapeHtml(t.label || t.key) + '</strong><span>Deschide șablon text</span></button>';
    }).join('');
  }

  function renderContentBoard() {
    var draft = 0, scheduled = 0, published = 0, review = 0;

    state.content.forEach(function (c) {
      if (c.status === 'draft') draft++;
      if (c.status === 'review') review++;
      if (c.status === 'scheduled') scheduled++;
      if (c.status === 'published') published++;
    });

    var setStat = function (id, v) { var el = document.getElementById(id); if (el) el.textContent = fmtNum(v); };
    setStat('bmgh-cnt-stat-total', state.content.length);
    setStat('bmgh-cnt-stat-draft', draft);
    setStat('bmgh-cnt-stat-scheduled', scheduled);
    setStat('bmgh-cnt-stat-published', published);

    CONTENT_COLS.forEach(function (col) {
      var listEl = document.getElementById('bmgh-board-' + col);
      var countEl = document.getElementById('bmgh-board-count-' + col);
      if (!listEl) return;
      var allInCol = state.content.filter(function (c) { return c.status === col; });
      if (countEl) countEl.textContent = String(allInCol.length);
      var items = allInCol;
      listEl.innerHTML = items.length ? items.map(function (c) {
        return '<button type="button" class="bmgh-content-card" data-cnt-id="' + c.id + '">' +
          '<strong>' + escapeHtml(c.title) + '</strong>' +
          '<span>' + escapeHtml(c.platform || '—') + ' · ' + escapeHtml(c.format || 'post') + '</span>' +
          (c.publishDate ? '<em>' + fmtDate(c.publishDate) + '</em>' : '') +
          '<small>Click = editezi</small></button>';
      }).join('') : '<div class="bmgh-board__empty">Nimic aici</div>';
    });
  }

  function renderContentCalendar() {
    var cal = document.getElementById('bmgh-cnt-calendar');
    if (!cal) return;
    var now = new Date();
    var year = now.getFullYear();
    var month = now.getMonth();
    var firstDay = new Date(year, month, 1);
    var startPad = (firstDay.getDay() + 6) % 7;
    var daysInMonth = new Date(year, month + 1, 0).getDate();
    var monthLabel = firstDay.toLocaleDateString('ro-RO', { month: 'long', year: 'numeric' });

    var byDate = {};
    state.content.forEach(function (c) {
      if (!c.publishDate) return;
      var d = String(c.publishDate).slice(0, 10);
      if (!byDate[d]) byDate[d] = [];
      byDate[d].push(c);
    });

    var html = '<div class="bmgh-calendar__head"><strong>' + escapeHtml(monthLabel) + '</strong></div>';
    html += '<div class="bmgh-calendar__weekdays">';
    ['Lu', 'Ma', 'Mi', 'Jo', 'Vi', 'Sâ', 'Du'].forEach(function (d) {
      html += '<span>' + d + '</span>';
    });
    html += '</div><div class="bmgh-calendar__grid">';

    for (var i = 0; i < startPad; i++) {
      html += '<div class="bmgh-calendar__cell bmgh-calendar__cell--pad"></div>';
    }
    for (var day = 1; day <= daysInMonth; day++) {
      var iso = year + '-' + String(month + 1).padStart(2, '0') + '-' + String(day).padStart(2, '0');
      var items = byDate[iso] || [];
      var isToday = day === now.getDate() && month === now.getMonth();
      html += '<div class="bmgh-calendar__cell' + (isToday ? ' is-today' : '') + '">' +
        '<span class="bmgh-calendar__day">' + day + '</span>';
      items.slice(0, 3).forEach(function (c) {
        html += '<button type="button" class="bmgh-calendar__event" data-cnt-id="' + c.id + '">' + escapeHtml(c.title) + '</button>';
      });
      if (items.length > 3) html += '<span class="bmgh-calendar__more">+' + (items.length - 3) + '</span>';
      html += '</div>';
    }
    html += '</div>';
    cal.innerHTML = html;
  }

  function renderContent() {
    if (pageMode !== 'content') return;
    renderContentTemplates();
    renderContentBoard();
    renderContentCalendar();

    var board = document.getElementById('bmgh-cnt-board');
    var cal = document.getElementById('bmgh-cnt-calendar');
    if (board && cal) {
      if (contentView === 'calendar') {
        board.classList.add('hidden');
        cal.classList.remove('hidden');
      } else {
        board.classList.remove('hidden');
        cal.classList.add('hidden');
      }
    }
  }

  function indicatorSpec(ind) {
    var ak = String(ind.autoKey || '');
    var m = String(ind.metric || '').toLowerCase();
    var platform = String(ind.platform || '');
    var src = ind.source || 'manual';
    var base = {
      chip: 'KPI canal',
      chipTone: 'default',
      period: 'perioada ta',
      noun: 'unități',
      nounSingular: 'unitate',
      measures: 'Un număr legat de canalul „' + platform + '”.',
      notThis: 'Nu confunda cu utilizatori Planner, clienți înregistrați sau lead-uri — verifică unitatea de mai jos.',
      sourceFrom: src === 'auto' ? 'Actualizat automat din magazin (Actualizează)' : src === 'scrape' ? 'Scraping / import manual' : 'Introduci tu cifra (manual)',
      targetMeans: 'Obiectivul pe care vrei să-l atingi pe acest canal.',
      targetHint: '',
      lowerBetter: isLowerBetter(ind),
    };

    var byKey = {
      site_searches_today: {
        chip: 'Căutări site',
        chipTone: 'search',
        period: 'astăzi',
        noun: 'căutări',
        nounSingular: 'căutare',
        measures: 'De câte ori s-a folosit căutarea pe site (OEM, VIN, nume piesă) — fiecare încercare = 1 căutare în Search Logs.',
        notThis: 'Nu sunt vizitatori unici, utilizatori Planner sau comenzi.',
        sourceFrom: 'Search Logs → căutări înregistrate azi (live)',
        targetMeans: 'Câte căutări vrei într-o zi — semn că magazinul e folosit.',
      },
      site_searches_total: {
        chip: 'Căutări site',
        chipTone: 'search',
        period: 'total (all-time)',
        noun: 'căutări',
        nounSingular: 'căutare',
        measures: 'Toate căutările din istoricul Search Logs, de la începutul jurnalului.',
        notThis: 'Nu sunt 124 „clienți” sau „utilizatori” — sunt 124 evenimente de căutare (pot fi aceeași persoană de mai multe ori).',
        sourceFrom: 'Search Logs → total rânduri în jurnal (live)',
        targetMeans: 'Câte căutări totale vrei în jurnal — crește când site-ul e activ.',
        targetHint: 'Dacă ținta pare mică (ex. 186), e calculată auto ca ~150% din acum — editeaz-o la ce vrei tu.',
      },
      site_orders_today: {
        chip: 'Comenzi',
        chipTone: 'order',
        period: 'astăzi',
        noun: 'comenzi',
        nounSingular: 'comandă',
        measures: 'Comenzi finalizate pe site (checkout confirmat) — vânzări reale, nu intenții.',
        notThis: 'Nu sunt lead-uri WhatsApp, coșuri abandonate sau mesaje.',
        sourceFrom: 'Modul Comenzi site → comenzi noi azi (live)',
        targetMeans: 'Câte comenzi vrei într-o zi — direct legat de bani.',
      },
      site_revenue_today: {
        chip: 'Venit',
        chipTone: 'money',
        period: 'astăzi',
        noun: 'RON',
        nounSingular: 'RON',
        measures: 'Suma în lei din comenzile noi de azi (valoare comandă).',
        notThis: 'Nu sunt vizite, lead-uri sau profit net (fără cheltuieli).',
        sourceFrom: 'Comenzi site → total RON comenzi noi azi (live)',
        targetMeans: 'Câți lei vrei din vânzări azi.',
      },
      site_missing_oem: {
        chip: 'OEM lipsă',
        chipTone: 'warn',
        period: 'acum (coduri distincte)',
        noun: 'coduri OEM',
        nounSingular: 'cod OEM',
        measures: 'Câte coduri OEM diferite au fost căutate dar nu există în stocul tău.',
        notThis: 'Nu sunt comenzi pierdute numărate — sunt tipuri de piese pe care clienții le caută și nu le ai.',
        sourceFrom: 'Search Logs → coduri negăsite agregate (live)',
        targetMeans: 'Maximum acceptabil de coduri lipsă — aici mai puțin = mai bine.',
        lowerBetter: true,
      },
      site_success_rate: {
        chip: 'Rată succes',
        chipTone: 'rate',
        period: 'all-time (%)',
        noun: '%',
        nounSingular: '%',
        measures: 'Procent: câte căutări au găsit piesa în stoc (găsite ÷ total căutări × 100).',
        notThis: 'Nu este rata de conversie în comenzi — doar „am găsit piesa în catalog”.',
        sourceFrom: 'Search Logs → calcul găsite / total (live)',
        targetMeans: 'Procent minim de căutări reușite — ex. 75% = 3 din 4 căutări găsesc ceva.',
      },
      'planner:visits': {
        chip: 'Vizite site',
        chipTone: 'traffic',
        period: 'pe lună',
        noun: 'vizite pagină',
        nounSingular: 'vizită',
        measures: 'De câte ori se deschid pagini pe site într-o lună (trafic web).',
        notThis: 'Nu sunt aceiași cu „utilizatori țintă” din Planner — acolo e oameni unici estimați; aici e vizite (o persoană = mai multe vizite).',
        sourceFrom: 'Țintă din Planner (utilizatori × ~3 pagini) — valoarea acum o introduci din Analytics',
        targetMeans: 'Trafic lunar necesar pentru scenariul din Planner.',
      },
      'planner:orders': {
        chip: 'Comenzi',
        chipTone: 'order',
        period: 'pe lună',
        noun: 'comenzi',
        nounSingular: 'comandă',
        measures: 'Comenzi finalizate pe lună — rezultatul direct al conversiei din Planner.',
        notThis: 'Nu sunt lead-uri — doar vânzări confirmate.',
        sourceFrom: 'Planner: utilizatori × % conversie',
        targetMeans: 'Comenzi/lună = obiectivul financiar principal.',
      },
      'planner:revenue': {
        chip: 'Venit',
        chipTone: 'money',
        period: 'pe lună',
        noun: 'RON',
        nounSingular: 'RON',
        measures: 'Bani din comenzi pe lună (comenzi × preț mediu din Planner).',
        notThis: 'Nu include cheltuieli — e venit din vânzări, nu profit.',
        sourceFrom: 'Planner: comenzi × preț mediu',
        targetMeans: 'Câți lei/lună vrei din magazin când atingi scenariul.',
      },
      'planner:google': {
        chip: 'Impresii Google',
        chipTone: 'google',
        period: 'pe lună',
        noun: 'impresii',
        nounSingular: 'impresie',
        measures: 'De câte ori site-ul tău apare în rezultatele Google (Search Console).',
        notThis: 'Nu sunt vizitatori — mulți văd fără să dea click.',
        sourceFrom: 'Google Search Console (manual) sau estimare din Planner',
        targetMeans: 'Vizibilitate în Google spre traficul țintă.',
      },
      'planner:facebook': {
        chip: 'Reach Facebook',
        chipTone: 'social',
        period: 'pe lună',
        noun: 'persoane atinse',
        nounSingular: 'persoană',
        measures: 'Câți oameni unici văd postările tale (reach) — nu like-uri.',
        notThis: 'Nu sunt comenzi — sunt oameni expuși la mesaj.',
        sourceFrom: 'Facebook Insights (manual)',
        targetMeans: 'Câți oameni vrei să vadă promoțiile lunar.',
      },
      'planner:pieseauto': {
        chip: 'PieseAuto.ro',
        chipTone: 'market',
        period: 'active acum',
        noun: 'anunțuri',
        nounSingular: 'anunț',
        measures: 'Câte anunțuri ai listate simultan pe PieseAuto.ro.',
        notThis: 'Nu sunt vizualizări sau mesaje — doar anunțuri live.',
        sourceFrom: 'Cont PieseAuto.ro (manual)',
        targetMeans: 'Câte anunțuri active vrei pe marketplace.',
      },
    };

    if (byKey[ak]) {
      return Object.assign({}, base, byKey[ak]);
    }

    if (/vizite/.test(m)) {
      return Object.assign({}, base, {
        chip: 'Vizite site', chipTone: 'traffic', period: 'pe lună', noun: 'vizite', nounSingular: 'vizită',
        measures: 'Deschideri de pagini pe site — trafic web.',
        notThis: 'Nu sunt utilizatori unici din Planner decât dacă ai legat cardul de acolo.',
      });
    }
    if (/comenzi/.test(m)) {
      return Object.assign({}, base, {
        chip: 'Comenzi', chipTone: 'order', period: /azi/.test(m) ? 'astăzi' : 'pe lună', noun: 'comenzi', nounSingular: 'comandă',
        measures: 'Vânzări confirmate — nu lead-uri.',
        notThis: 'Nu sunt mesaje WhatsApp sau coșuri nefinalizate.',
      });
    }
    if (/venit|ron/.test(m)) {
      return Object.assign({}, base, {
        chip: 'Venit', chipTone: 'money', period: /azi/.test(m) ? 'astăzi' : 'pe lună', noun: 'RON', nounSingular: 'RON',
        measures: 'Lei din comenzi.', notThis: 'Nu sunt vizite sau lead-uri.',
      });
    }
    if (/google|impresii/.test(m)) {
      return Object.assign({}, base, {
        chip: 'Impresii Google', chipTone: 'google', period: 'pe lună', noun: 'impresii', nounSingular: 'impresie',
        measures: 'Apariții în Google.', notThis: 'Nu sunt clienți — doar afișări în căutare.',
      });
    }
    if (/facebook|reach/.test(m)) {
      return Object.assign({}, base, {
        chip: 'Reach Facebook', chipTone: 'social', period: 'pe lună', noun: 'persoane', nounSingular: 'persoană',
        measures: 'Oameni unici care văd postările.', notThis: 'Nu sunt comenzi direct.',
      });
    }
    if (/pieseauto|anunț/.test(m)) {
      return Object.assign({}, base, {
        chip: 'PieseAuto.ro', chipTone: 'market', period: 'active', noun: 'anunțuri', nounSingular: 'anunț',
        measures: 'Anunțuri listate pe PieseAuto.ro.', notThis: 'Nu sunt vizualizări anunț.',
      });
    }
    if (/pieseauto|vizualiz/.test(m)) {
      return Object.assign({}, base, {
        chip: 'PieseAuto.ro', chipTone: 'market', period: 'pe lună', noun: 'vizualizări', nounSingular: 'vizualizare',
        measures: 'De câte ori s-au văzut anunțurile tale pe PieseAuto.ro.', notThis: 'Nu sunt comenzi site.',
      });
    }
    if (/whatsapp|conversa/.test(m)) {
      return Object.assign({}, base, {
        chip: 'Lead-uri WhatsApp', chipTone: 'lead', period: 'pe lună', noun: 'conversații', nounSingular: 'conversație',
        measures: 'Dialoguri noi cu potențiali clienți — lead-uri, nu comenzi încă.',
        notThis: 'Nu sunt comenzi finalizate — sunt contacte de convertit.',
      });
    }
    if (/căutări|cautari|search/.test(m)) {
      return Object.assign({}, base, {
        chip: 'Căutări site', chipTone: 'search', period: /azi/.test(m) ? 'astăzi' : 'total',
        noun: 'căutări', nounSingular: 'căutare',
        measures: 'Evenimente de căutare OEM/VIN pe site.', notThis: 'Nu sunt utilizatori unici.',
      });
    }
    return base;
  }

  function formatMetricAmount(n, spec) {
    var v = Number(n);
    if (!Number.isFinite(v)) return '—';
    if (spec.noun === '%' || spec.noun === 'RON') {
      return fmtNum(v) + ' ' + spec.noun;
    }
    return fmtNum(v) + ' ' + (Math.abs(v) === 1 ? spec.nounSingular : spec.noun);
  }

  function autoTargetHint(ind, spec) {
    if (spec.targetHint) return spec.targetHint;
    if (ind.autoKey && ind.source === 'auto') {
      var v = Number(ind.value) || 0;
      var t = Number(ind.target) || 0;
      if (v > 0 && t > 0 && Math.abs(t - Math.max(v * 1.5, 10)) < 2) {
        return 'Ținta a fost setată automat (~150% din valoarea de acum). Editeaz-o la obiectivul tău real.';
      }
    }
    return '';
  }

  function indicatorWhyText(ind) {
    if (isLowerBetter(ind)) {
      return 'Mai puține coduri OEM lipsă = clienții găsesc piesa și comanda.';
    }
    var ak = ind.autoKey || '';
    var m = String(ind.metric || '').toLowerCase();
    if (ak === 'planner:visits' || /vizite/.test(m)) {
      return 'Trafic pe site — primul pas spre comenzile din scenariul Planner.';
    }
    if (ak === 'planner:orders' || /comenzi/.test(m)) {
      return 'Comenzi directe — obiectivul financiar din volumul de lucru.';
    }
    if (ak === 'planner:revenue' || /venit/.test(m)) {
      return 'Bani în casă — rezultatul final când atingi ținta mare.';
    }
    if (/google|impresii/.test(m)) {
      return 'Google aduce oameni care caută piese — parte din traficul spre ținta mare.';
    }
    if (/facebook|reach/.test(m)) {
      return 'Reach = câți oameni văd promoțiile; susține traficul spre comenzi.';
    }
    if (/pieseauto|anunț/.test(m)) {
      return 'Anunțuri active pe PieseAuto.ro = vânzări parallel pe marketplace.';
    }
    if (/pieseauto|vizualiz/.test(m)) {
      return 'Vizualizări pe PieseAuto = interes pe piesele listate acolo.';
    }
    if (/whatsapp|conversa/.test(m)) {
      return 'Conversații WhatsApp = lead-uri calificate spre comandă.';
    }
    return 'Canal care contribuie la ținta mare din Planner.';
  }

  function indicatorOutcome(ind, calc) {
    var ak = ind.autoKey || '';
    var m = String(ind.metric || '').toLowerCase();
    if (ak === 'site_searches_today' || ak === 'site_searches_total') {
      return 'La țintă → mai mult trafic de căutări pe site → mai multe șanse la comenzi (nu = atâția clienți unici).';
    }
    if (ak === 'site_orders_today') {
      return 'La țintă → atâtea vânzări confirmate azi (comenzi reale, nu lead-uri).';
    }
    if (ak === 'site_revenue_today') {
      return 'La țintă → atâția lei încasați azi din comenzi site.';
    }
    if (ak === 'site_missing_oem') {
      return 'La țintă (mai puțin) → clienții găsesc piesa → mai puține căutări pierdute.';
    }
    if (ak === 'site_success_rate') {
      return 'La țintă → majoritatea căutărilor găsesc piesa în catalog.';
    }
    if (!calc) {
      return 'Completează Planner-ul (/admin/planner) ca să vezi legătura cu comenzi și RON/lună.';
    }
    if (ak === 'planner:orders' || /comenzi/.test(m)) {
      return 'La țintă → ~' + fmtNum(calc.ordersMonth) + ' comenzi/lună · ~' + fmtNum(calc.revenueMonth) + ' RON';
    }
    if (ak === 'planner:revenue' || /venit/.test(m)) {
      return 'La țintă → ~' + fmtNum(calc.revenueMonth) + ' RON/lună din magazin';
    }
    if (ak === 'planner:visits' || /vizite/.test(m)) {
      return 'La țintă → trafic pentru ~' + fmtNum(calc.targetUsers) + ' clienți → ~' + fmtNum(calc.ordersMonth) + ' comenzi';
    }
    if (isLowerBetter(ind)) {
      return 'La țintă → mai mulți clienți găsesc piesa → spre ' + fmtNum(calc.ordersMonth) + ' comenzi/lună';
    }
    if (/google|impresii/.test(m)) {
      return 'La țintă → mai mult trafic organic spre ~' + fmtNum(calc.visitsMonth) + ' vizite totale';
    }
    if (/facebook|reach/.test(m)) {
      return 'La țintă → ~' + fmtNum(Math.round(calc.targetUsers * 0.2)) + ' oameni văd oferta → trafic spre site';
    }
    if (/pieseauto|anunț/.test(m)) {
      return 'La țintă → mai multe vânzări PieseAuto.ro → contribuie la ~' + fmtNum(calc.ordersMonth) + ' comenzi totale';
    }
    return 'La țintă → contribui la ' + fmtNum(calc.targetUsers) + ' util. · ' + fmtNum(calc.ordersMonth) + ' comenzi · ' + fmtNum(calc.revenueMonth) + ' RON';
  }

  function plannerSliceLabel(ind, calc) {
    if (!calc) return '';
    var ak = ind.autoKey || '';
    if (ak === 'planner:visits') {
      return 'Felie din ținta ' + fmtNum(calc.targetUsers) + ' util. (≈ ' + fmtNum(calc.visitsMonth) + ' vizite/lună)';
    }
    if (ak === 'planner:orders') {
      return 'Felie directă: comenzile din scenariu (' + calc.conversionRate + '% conversie)';
    }
    if (ak === 'planner:revenue') {
      return 'Felie directă: venitul din scenariu Planner';
    }
    if (ak.indexOf('planner:') === 0) {
      return 'Generat din Planner — ținta mare ' + fmtNum(calc.targetUsers) + ' utilizatori';
    }
    return '';
  }

  function readPlannerCalc() {
    try {
      var raw = localStorage.getItem(PLANNER_KEY);
      if (!raw) return null;
      var planner = JSON.parse(raw);
      var scenario = planner.scenario || [];
      var users = 1000;
      var conv = 5;
      var price = 141.23;
      scenario.forEach(function (r) {
        if (r.calcKey === 'target_users') users = Math.max(1, Math.round(Number(r.value) || 1));
        if (r.calcKey === 'conversion_rate') conv = Math.min(100, Math.max(0.1, Number(r.value) || 0.1));
        if (r.calcKey === 'avg_price') price = Math.max(0, Number(r.value) || 0);
      });
      var orders = Math.round(users * (conv / 100));
      return {
        targetUsers: users,
        conversionRate: conv,
        avgPrice: price,
        ordersMonth: orders,
        revenueMonth: Math.round(orders * price),
        visitsMonth: users * 3,
      };
    } catch (e) {
      return null;
    }
  }

  function upsertPlannerIndicator(spec) {
    var existing = state.indicators.find(function (i) { return i.autoKey === spec.autoKey; });
    if (existing) {
      existing.platform = spec.platform;
      existing.url = spec.url || existing.url || '';
      existing.metric = spec.metric;
      existing.target = spec.target;
      existing.source = spec.source || 'auto';
      existing.category = spec.category || 'general';
      existing.notes = spec.notes || '';
      existing.updatedAt = new Date().toISOString();
    } else {
      state.indicators.push({
        id: uid('ind'),
        platform: spec.platform,
        url: spec.url || '',
        metric: spec.metric,
        value: 0,
        target: spec.target,
        source: spec.source || 'auto',
        category: spec.category || 'general',
        autoKey: spec.autoKey,
        notes: spec.notes || '',
        createdAt: new Date().toISOString(),
        updatedAt: new Date().toISOString(),
      });
    }
  }

  function applyPlannerBreakdown() {
    var calc = readPlannerCalc();
    if (!calc) {
      showToast('Deschide /admin/planner și salvează scenariul (ex: 10.000 utilizatori)', true);
      return;
    }
    upsertPlannerIndicator({
      autoKey: 'planner:visits',
      platform: 'besoiupieseauto.ro',
      url: 'https://besoiupieseauto.ro',
      metric: 'Vizite / lună',
      target: calc.visitsMonth,
      notes: 'Din Planner: ' + fmtNum(calc.targetUsers) + ' util × ~3 pagini/sesiune',
    });
    upsertPlannerIndicator({
      autoKey: 'planner:orders',
      platform: 'besoiupieseauto.ro',
      url: 'https://besoiupieseauto.ro',
      metric: 'Comenzi / lună',
      target: calc.ordersMonth,
      notes: calc.conversionRate + '% conversie din Planner',
    });
    upsertPlannerIndicator({
      autoKey: 'planner:revenue',
      platform: 'besoiupieseauto.ro',
      url: 'https://besoiupieseauto.ro',
      metric: 'Venit / lună (RON)',
      target: calc.revenueMonth,
      notes: 'Preț mediu ' + fmtNum(calc.avgPrice) + ' RON din Planner',
    });
    upsertPlannerIndicator({
      autoKey: 'planner:google',
      platform: 'Google Search',
      url: 'https://search.google.com/search-console',
      metric: 'Impresii căutare / lună',
      target: Math.round(calc.visitsMonth * 1.5),
      notes: '≈1,5× vizite — trafic organic spre ținta mare',
    });
    upsertPlannerIndicator({
      autoKey: 'planner:facebook',
      platform: 'Facebook',
      url: 'https://facebook.com',
      metric: 'Reach postări / lună',
      target: Math.round(calc.targetUsers * 0.4),
      notes: 'Reach estimat pentru ' + fmtNum(calc.targetUsers) + ' util. țintă',
    });
    upsertPlannerIndicator({
      autoKey: 'planner:pieseauto',
      platform: 'PieseAuto.ro',
      url: 'https://www.pieseauto.ro',
      metric: 'Listări active',
      target: Math.max(50, Math.round(calc.ordersMonth * 0.5)),
      notes: 'Marketplace parallel — susține volumul de comenzi',
    });
    saveState();
    renderAll();
    showToast('Indicatori împărțiți din Planner — fără apeluri API externe');
  }

  function renderPlannerGoal() {
    var box = document.getElementById('bmgh-planner-goal');
    if (!box) return;
    var calc = readPlannerCalc();
    var title = document.getElementById('bmgh-planner-goal-title');
    var formula = document.getElementById('bmgh-planner-goal-formula');
    var set = function (id, val) {
      var el = document.getElementById(id);
      if (el) el.textContent = val;
    };
    if (!calc) {
      if (title) title.textContent = 'Setează ținta mare în Planner';
      if (formula) {
        formula.textContent = 'Ex: 10.000 utilizatori · 8% conversie · 141 RON preț mediu → vezi comenzile și venitul aici.';
      }
      set('bmgh-funnel-users', '—');
      set('bmgh-funnel-conv', '—');
      set('bmgh-funnel-outcome', '—');
      var subEmpty = document.getElementById('bmgh-funnel-outcome-sub');
      if (subEmpty) subEmpty.textContent = 'deschide /admin/planner';
      box.classList.add('is-empty');
      return;
    }
    box.classList.remove('is-empty');
    if (title) {
      title.textContent = fmtNum(calc.targetUsers) + ' utilizatori / lună — ținta ta mare';
    }
    if (formula) {
      formula.textContent = fmtNum(calc.targetUsers) + ' × ' + calc.conversionRate + '% × ' +
        fmtNum(calc.avgPrice) + ' RON = ' + fmtNum(calc.revenueMonth) + ' RON/lună';
    }
    set('bmgh-funnel-users', fmtNum(calc.targetUsers));
    set('bmgh-funnel-conv', calc.conversionRate + '%');
    set('bmgh-funnel-outcome', fmtNum(calc.ordersMonth) + ' comenzi · ' + fmtNum(calc.revenueMonth) + ' RON');
    var sub = document.getElementById('bmgh-funnel-outcome-sub');
    if (sub) sub.textContent = 'asta obții când atingi volumul din Planner';
    var live = insights && insights.live ? insights.live : {};
    var nowEl = document.getElementById('bmgh-planner-goal-now');
    if (nowEl) {
      nowEl.textContent = fmtNum(live.orders_today || 0) + ' comenzi azi · ' +
        fmtNum(live.revenue_today || 0) + ' RON azi · ' +
        fmtNum(live.searches_today || 0) + ' căutări azi';
    }
  }

  function syncPlannerBridge() {
    var textEl = document.getElementById('bmgh-planner-bridge-text');
    if (!textEl) return;
    try {
      var raw = localStorage.getItem(PLANNER_KEY);
      if (!raw) {
        textEl.textContent = 'Nu există scenariu salvat — deschide /admin/planner.';
        return;
      }
      var planner = JSON.parse(raw);
      var scenario = planner.scenario || [];
      var users = scenario.find(function (r) { return r.calcKey === 'target_users'; });
      var conv = scenario.find(function (r) { return r.calcKey === 'conversion_rate'; });
      var price = scenario.find(function (r) { return r.calcKey === 'avg_price'; });
      textEl.textContent = 'Scenariu activ: ' + (users ? fmtNum(users.value) : '—') + ' utilizatori · conversie ' +
        (conv ? conv.value + '%' : '—') + ' · preț mediu ' + (price ? fmtNum(price.value) + ' RON' : '—');
    } catch (e) {
      textEl.textContent = 'Nu s-a putut citi Planner-ul.';
    }
  }

  function createActionFromIndicator(ind) {
    if (state.actions.some(function (a) {
      return a.status !== 'done' && a.indicatorId === ind.id;
    })) {
      showToast('Există deja o acțiune pentru acest indicator', true);
      return;
    }
    var p = indProgress(ind);
    var act = {
      id: uid('act'),
      title: 'Crește ' + ind.metric + ' — ' + ind.platform,
      targetKpi: ind.metric,
      indicatorId: ind.id,
      deadline: '',
      priority: p < 30 ? 'high' : 'medium',
      status: 'todo',
      effort: 3,
      impact: p < 30 ? 5 : 4,
      notes: 'Generat automat la ' + p + '% din țintă (' + ind.value + ' / ' + ind.target + ')',
      createdAt: new Date().toISOString(),
      updatedAt: new Date().toISOString(),
    };
    recalcActionScore(act);
    state.actions.push(act);
    saveState();
    showToast('Acțiune creată — deschide Plan acțiune');
    setTimeout(function () { window.location.href = '/admin/marketing-actions'; }, 600);
  }

  function createContentFromAction(act) {
    state.content.push({
      id: uid('cnt'),
      title: 'Conținut: ' + act.title,
      platform: '',
      format: 'post',
      status: 'draft',
      publishDate: '',
      linkedActionId: act.id,
      linkedAction: act.id,
      body: act.notes || '',
      createdAt: new Date().toISOString(),
      updatedAt: new Date().toISOString(),
    });
    saveState();
    showToast('Draft conținut creat');
    setTimeout(function () { window.location.href = '/admin/marketing-content'; }, 600);
  }

  function renderAll() {
    renderCounts();
    renderLiveStrip();
    renderPlannerGoal();
    renderAlertsBar();
    renderSuggestions();
    renderIndicators();
    renderActions();
    renderContent();
    syncPlannerBridge();
    renderPostMetrics();
    refreshIcons();
  }

  function indFields(data) {
    return [
      { id: 'platform', label: 'Ce măsori? (platformă)', hint: 'Ex: Site, PieseAuto.ro, Facebook', value: data.platform || '' },
      { id: 'url', label: 'Link (opțional)', hint: 'Unde verifici cifra', value: data.url || '' },
      { id: 'metric', label: 'Numele indicatorului', hint: 'Ex: Vizite/lună, OEM lipsă', value: data.metric || '' },
      { type: 'row', fields: [
        { id: 'value', label: 'Valoare acum', hint: 'Cifra de azi', value: data.value || 0, type: 'number' },
        { id: 'target', label: 'Ținta ta', hint: 'Unde vrei să ajungi', value: data.target || 1000, type: 'number' },
      ]},
      { id: 'source', label: 'De unde vine cifra?', type: 'select', value: data.source || 'manual', options: [
        { value: 'manual', label: 'Introduc eu manual' },
        { value: 'scrape', label: 'Scraping / automat' },
        { value: 'auto', label: 'Auto din magazin (Sync live)' },
      ]},
      { id: 'notes', label: 'Note (opțional)', value: data.notes || '', type: 'textarea' },
    ];
  }

  function actFields(data) {
    return [
      { id: 'title', label: 'Titlu acțiune', value: data.title || '' },
      { id: 'targetKpi', label: 'Indicator / țintă legată', value: data.targetKpi || '' },
      { id: 'deadline', label: 'Deadline', value: data.deadline ? String(data.deadline).slice(0, 10) : '', type: 'date' },
      { type: 'row', fields: [
        { id: 'impact', label: 'Impact (1–5)', value: data.impact || 3, type: 'number' },
        { id: 'effort', label: 'Efort (1–5)', value: data.effort || 3, type: 'number' },
      ]},
      { id: 'priority', label: 'Prioritate', type: 'select', value: data.priority || 'medium', options: [
        { value: 'high', label: 'Urgent' }, { value: 'medium', label: 'Mediu' }, { value: 'low', label: 'Scăzut' },
      ]},
      { id: 'status', label: 'Status', type: 'select', value: data.status || 'todo', options: [
        { value: 'todo', label: 'De făcut' }, { value: 'progress', label: 'În curs' }, { value: 'done', label: 'Finalizat' },
      ]},
      { id: 'notes', label: 'Detalii', value: data.notes || '', type: 'textarea' },
    ];
  }

  function cntFields(data) {
    var m = data.metrics && typeof data.metrics === 'object' ? data.metrics : {};
    return [
      { id: 'title', label: 'Titlu / subiect', value: data.title || '' },
      { id: 'platform', label: 'Platformă', value: data.platform || '' },
      { type: 'row', fields: [
        { id: 'reach', label: 'Reach (câți au văzut)', hint: 'Indicator post', value: m.reach || 0, type: 'number' },
        { id: 'impressions', label: 'Impresii', hint: 'De câte ori afișat', value: m.impressions || 0, type: 'number' },
      ]},
      { type: 'row', fields: [
        { id: 'likes', label: 'Like / reacții', value: m.likes || 0, type: 'number' },
        { id: 'clicks', label: 'Click-uri', value: m.clicks || 0, type: 'number' },
      ]},
      { id: 'format', label: 'Format', type: 'select', value: data.format || 'post', options: [
        { value: 'post', label: 'Postare social' }, { value: 'article', label: 'Articol blog' },
        { value: 'ad', label: 'Anunț PieseAuto.ro' }, { value: 'video', label: 'Video / reel' },
        { value: 'newsletter', label: 'Newsletter' },
      ]},
      { id: 'status', label: 'Status', type: 'select', value: data.status || 'draft', options: [
        { value: 'draft', label: 'Draft' }, { value: 'review', label: 'Review' }, { value: 'ready', label: 'Gata de postat' },
        { value: 'scheduled', label: 'Programat' }, { value: 'published', label: 'Publicat' },
      ]},
      { id: 'publishDate', label: 'Dată publicare', value: data.publishDate ? String(data.publishDate).slice(0, 10) : '', type: 'date' },
      { id: 'linkedAction', label: 'ID acțiune din plan', value: data.linkedAction || data.linkedActionId || '' },
      { id: 'body', label: 'Conținut / draft', value: data.body || '', type: 'textarea' },
    ];
  }

  function renderFieldInput(f) {
    var id = 'bmgh-f-' + f.id;
    var val = escapeHtml(String(f.value ?? ''));
    if (f.type === 'select') {
      return '<select id="' + id + '" name="' + f.id + '">' + (f.options || []).map(function (o) {
        return '<option value="' + escapeHtml(o.value) + '"' + (String(o.value) === String(f.value) ? ' selected' : '') + '>' + escapeHtml(o.label) + '</option>';
      }).join('') + '</select>';
    }
    if (f.type === 'textarea') return '<textarea id="' + id + '" name="' + f.id + '">' + val + '</textarea>';
    return '<input type="' + (f.type || 'text') + '" id="' + id + '" name="' + f.id + '" value="' + val + '">';
  }

  function renderFieldHtml(f) {
    var hint = f.hint ? '<span class="bmgh-field__hint">' + escapeHtml(f.hint) + '</span>' : '';
    if (f.type === 'row') {
      return '<div class="bmgh-field-row">' + f.fields.map(function (ff) {
        return '<div class="bmgh-field"><label for="bmgh-f-' + ff.id + '">' + escapeHtml(ff.label) + '</label>' +
          (ff.hint ? '<span class="bmgh-field__hint">' + escapeHtml(ff.hint) + '</span>' : '') +
          renderFieldInput(ff) + '</div>';
      }).join('') + '</div>';
    }
    return '<div class="bmgh-field"><label for="bmgh-f-' + f.id + '">' + escapeHtml(f.label) + '</label>' +
      hint + renderFieldInput(f) + '</div>';
  }

  function openModal(title, fields, onSave, opts) {
    var body = document.getElementById('bmgh-modal-body');
    var titleEl = document.getElementById('bmgh-modal-title');
    var subtitleEl = document.getElementById('bmgh-modal-subtitle');
    if (!body || !titleEl) return;
    opts = opts || {};
    titleEl.textContent = title;
    if (subtitleEl) subtitleEl.textContent = opts.subtitle || 'Completează câmpurile — salvarea e automată.';
    body.innerHTML = fields.map(renderFieldHtml).join('');
    modalContext = { fields: fields, onSave: onSave };
    showOverlay('bmgh-overlay');
  }

  function readModalFields(fields) {
    var data = {};
    fields.forEach(function (f) {
      if (f.type === 'row') {
        f.fields.forEach(function (ff) {
          var el = document.getElementById('bmgh-f-' + ff.id);
          data[ff.id] = el ? el.value : '';
        });
      } else {
        var el2 = document.getElementById('bmgh-f-' + f.id);
        data[f.id] = el2 ? el2.value : '';
      }
    });
    return data;
  }

  function contentMetricsFromData(data) {
    return {
      reach: Number(data.reach) || 0,
      impressions: Number(data.impressions) || 0,
      likes: Number(data.likes) || 0,
      clicks: Number(data.clicks) || 0,
    };
  }

  function closeModal() {
    hideOverlay('bmgh-overlay');
    modalContext = null;
  }

  function applyTemplate(key) {
    var tpl = catalogTemplates.find(function (t) { return t.key === key; });
    if (!tpl) return;
    openModal('Conținut din șablon', cntFields({
      title: tpl.label || '',
      platform: tpl.platform || '',
      format: tpl.format || 'post',
      status: 'draft',
      body: tpl.body || '',
    }), function (data) {
      state.content.push({
        id: uid('cnt'),
        title: data.title.trim(),
        platform: data.platform.trim(),
        format: data.format,
        status: data.status,
        publishDate: data.publishDate || '',
        linkedAction: data.linkedAction.trim(),
        body: data.body.trim(),
        metrics: contentMetricsFromData(data),
        templateKey: key,
        createdAt: new Date().toISOString(),
        updatedAt: new Date().toISOString(),
      });
      saveState();
      renderAll();
      showToast('Conținut creat din șablon');
    });
  }

  function exportJson() {
    var blob = new Blob([JSON.stringify({
      version: 1,
      indicators: state.indicators,
      actions: state.actions,
      content: state.content,
      okrs: state.okrs || [],
      exported_at: new Date().toISOString(),
    }, null, 2)], { type: 'application/json' });
    var a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'besoiu-marketing-growth-' + new Date().toISOString().slice(0, 10) + '.json';
    a.click();
    URL.revokeObjectURL(a.href);
    showToast('Export JSON descărcat');
  }

  function importJsonFile(file) {
    var reader = new FileReader();
    reader.onload = function () {
      try {
        var parsed = JSON.parse(String(reader.result));
        state = {
          version: 1,
          indicators: parsed.indicators || [],
          actions: parsed.actions || [],
          content: parsed.content || [],
          okrs: parsed.okrs || [],
        };
        saveState();
        renderAll();
        showToast('Import JSON reușit');
      } catch (err) {
        showToast('Fișier JSON invalid', true);
      }
    };
    reader.readAsText(file);
  }

  function runSeoScrape(domain) {
    var btn = document.getElementById('bmgh-scrape-seo-all');
    if (btn) btn.disabled = true;
    showToast(domain ? 'Scan SEO: ' + domain + '…' : 'Scan concurenți…');
    return apiPost({ action: 'scrape_seo', domain: domain || '' }).then(function (json) {
      if (btn) btn.disabled = false;
      if (!json.success) throw new Error(json.message || 'Scan eșuat');
      if (json.data) applyApiData(json.data, { skipDedupeSave: true });
      renderAll();
      showToast(json.message || 'Scan SEO finalizat');
    }).catch(function (err) {
      if (btn) btn.disabled = false;
      showToast(err.message || 'Scan SEO eșuat', true);
    });
  }

  function runKeywordSync() {
    var btn = document.getElementById('bmgh-sync-keywords');
    if (btn) btn.disabled = true;
    return apiPost({ action: 'sync_keywords' }).then(function (json) {
      if (btn) btn.disabled = false;
      if (!json.success) throw new Error(json.message || 'Sync eșuat');
      if (json.data) applyApiData(json.data, { skipDedupeSave: true });
      renderAll();
      showToast(json.message || 'Keywords sincronizate');
    }).catch(function (err) {
      if (btn) btn.disabled = false;
      showToast(err.message || 'Sync keywords eșuat', true);
    });
  }

  function bindIndTabs() {
    document.querySelectorAll('[data-bmgh-ind-tab]').forEach(function (tab) {
      tab.addEventListener('click', function () {
        indTab = tab.getAttribute('data-bmgh-ind-tab') || 'general';
        document.querySelectorAll('[data-bmgh-ind-tab]').forEach(function (t) {
          t.classList.toggle('is-active', t === tab);
        });
        document.querySelectorAll('[data-bmgh-ind-panel]').forEach(function (p) {
          p.classList.toggle('is-active', p.getAttribute('data-bmgh-ind-panel') === indTab);
        });
      });
    });
  }

  function bindEvents() {
    var shell = document.getElementById('bmgh-shell');
    if (!shell || shell.dataset.bmghBound === '1') return;
    shell.dataset.bmghBound = '1';

    document.getElementById('bmgh-sync-live')?.addEventListener('click', syncLive);
    document.getElementById('bmgh-weekly-review')?.addEventListener('click', openWeeklyReview);
    document.getElementById('bmgh-review-close')?.addEventListener('click', function () {
      hideOverlay('bmgh-review-overlay');
    });
    document.getElementById('bmgh-help-open')?.addEventListener('click', function () {
      showOverlay('bmgh-help-overlay');
    });
    document.getElementById('bmgh-help-close')?.addEventListener('click', function () {
      hideOverlay('bmgh-help-overlay');
    });
    document.getElementById('bmgh-help-ok')?.addEventListener('click', function () {
      hideOverlay('bmgh-help-overlay');
    });
    ['bmgh-overlay', 'bmgh-help-overlay', 'bmgh-review-overlay'].forEach(function (oid) {
      document.getElementById(oid)?.addEventListener('click', function (e) {
        if (e.target.id === oid) hideOverlay(oid);
      });
    });
    document.getElementById('bmgh-suggestions-dismiss')?.addEventListener('click', function () {
      var wrap = document.getElementById('bmgh-suggestions');
      if (wrap) { wrap.classList.add('hidden', 'is-dismissed'); }
    });

    document.getElementById('bmgh-ind-add')?.addEventListener('click', function () {
      openModal('Indicator nou', indFields({}), function (data) {
        state.indicators.push({
          id: uid('ind'),
          platform: data.platform.trim(),
          url: data.url.trim(),
          metric: data.metric.trim(),
          value: Number(data.value) || 0,
          target: Number(data.target) || 0,
          source: data.source,
          notes: data.notes.trim(),
          updatedAt: new Date().toISOString(),
        });
        saveState();
        renderAll();
      });
    });

    document.getElementById('bmgh-act-add')?.addEventListener('click', function () {
      openModal('Acțiune nouă', actFields({}), function (data) {
        var act = {
          id: uid('act'),
          title: data.title.trim(),
          targetKpi: data.targetKpi.trim(),
          deadline: data.deadline || '',
          priority: data.priority,
          status: data.status,
          effort: Math.min(5, Math.max(1, Number(data.effort) || 3)),
          impact: Math.min(5, Math.max(1, Number(data.impact) || 3)),
          notes: data.notes.trim(),
          createdAt: new Date().toISOString(),
          updatedAt: new Date().toISOString(),
        };
        recalcActionScore(act);
        state.actions.push(act);
        saveState();
        renderAll();
      });
    });

    document.getElementById('bmgh-cnt-add')?.addEventListener('click', function () {
      openModal('Conținut nou', cntFields({}), function (data) {
        state.content.push({
          id: uid('cnt'),
          title: data.title.trim(),
          platform: data.platform.trim(),
          format: data.format,
          status: data.status,
          publishDate: data.publishDate || '',
          linkedAction: data.linkedAction.trim(),
          body: data.body.trim(),
          metrics: contentMetricsFromData(data),
          createdAt: new Date().toISOString(),
          updatedAt: new Date().toISOString(),
        });
        saveState();
        renderAll();
      });
    });

    document.getElementById('bmgh-export-json')?.addEventListener('click', exportJson);
    document.getElementById('bmgh-apply-planner')?.addEventListener('click', applyPlannerBreakdown);
    document.getElementById('bmgh-sync-planner')?.addEventListener('click', syncPlannerBridge);
    document.getElementById('bmgh-scrape-seo-all')?.addEventListener('click', function () { runSeoScrape(''); });
    document.getElementById('bmgh-sync-keywords')?.addEventListener('click', runKeywordSync);
    document.getElementById('bmgh-kw-add')?.addEventListener('click', function () {
      openModal('Keyword nou', [
        { id: 'keyword', label: 'Keyword (OEM, nume piesă…)', value: '' },
        { id: 'searches_30d', label: 'Căutări estimate / lună', value: 0, type: 'number' },
        { id: 'impressions_30d', label: 'Impresii Google (manual)', value: 0, type: 'number' },
        { id: 'clicks_30d', label: 'Click-uri', value: 0, type: 'number' },
        { id: 'notes', label: 'Note', value: '', type: 'textarea' },
      ], function (data) {
        apiPost({
          action: 'save_keyword',
          keyword: {
            keyword: data.keyword.trim(),
            keyword_type: 'manual',
            searches_30d: Number(data.searches_30d) || 0,
            impressions_30d: Number(data.impressions_30d) || 0,
            clicks_30d: Number(data.clicks_30d) || 0,
            source: 'manual',
            notes: data.notes.trim(),
          },
        }).then(function (json) {
          if (!json.success) throw new Error(json.message);
          if (json.data) applyApiData(json.data, { skipDedupeSave: true });
          renderAll();
          showToast('Keyword salvat');
        }).catch(function (e) { showToast(e.message || 'Eroare', true); });
      });
    });
    bindIndTabs();
    document.getElementById('bmgh-import-json')?.addEventListener('click', function () {
      document.getElementById('bmgh-import-file')?.click();
    });
    document.getElementById('bmgh-import-file')?.addEventListener('change', function (e) {
      var f = e.target.files && e.target.files[0];
      if (f) importJsonFile(f);
      e.target.value = '';
    });

    document.querySelectorAll('.bmgh-view-toggle__btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        contentView = btn.getAttribute('data-bmgh-view') || 'board';
        document.querySelectorAll('.bmgh-view-toggle__btn').forEach(function (b) {
          b.classList.toggle('is-active', b === btn);
        });
        renderContent();
      });
    });

    document.querySelectorAll('[data-bmgh-preset-ind]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var key = btn.getAttribute('data-bmgh-preset-ind');
        var preset = IND_PRESETS[key];
        if (!preset) return;
        state.indicators.push(Object.assign({
          id: uid('ind'),
          notes: '',
          updatedAt: new Date().toISOString(),
        }, preset));
        saveState();
        renderAll();
        showToast('Indicator adăugat din șablon');
      });
    });

    shell.addEventListener('click', function (e) {
      var scrapeDom = e.target.closest('[data-bmgh-scrape-domain]');
      if (scrapeDom) {
        runSeoScrape(scrapeDom.getAttribute('data-bmgh-scrape-domain'));
        return;
      }

      var sug = e.target.closest('[data-bmgh-suggestion]');
      if (sug) {
        handleSuggestion(sug.getAttribute('data-bmgh-suggestion'));
        return;
      }

      var pb = e.target.closest('[data-bmgh-playbook]');
      if (pb) {
        addPlaybookAction(pb.getAttribute('data-bmgh-playbook'));
        return;
      }

      var tpl = e.target.closest('[data-bmgh-template]');
      if (tpl) {
        applyTemplate(tpl.getAttribute('data-bmgh-template'));
        return;
      }

      var createAct = e.target.closest('[data-bmgh-create-act]');
      if (createAct) {
        var ind = state.indicators.find(function (i) { return i.id === createAct.getAttribute('data-bmgh-create-act'); });
        if (ind) createActionFromIndicator(ind);
        return;
      }

      var cntFromAct = e.target.closest('[data-bmgh-cnt-from-act]');
      if (cntFromAct) {
        var act0 = state.actions.find(function (a) { return a.id === cntFromAct.getAttribute('data-bmgh-cnt-from-act'); });
        if (act0) createContentFromAction(act0);
        return;
      }

      var editInd = e.target.closest('[data-bmgh-edit-ind]');
      if (editInd) {
        var ind2 = state.indicators.find(function (i) { return i.id === editInd.getAttribute('data-bmgh-edit-ind'); });
        if (ind2) {
          openModal('Editează indicator', indFields(ind2), function (data) {
            Object.assign(ind2, {
              platform: data.platform.trim(),
              url: data.url.trim(),
              metric: data.metric.trim(),
              value: Number(data.value) || 0,
              target: Number(data.target) || 0,
              source: data.source,
              notes: data.notes.trim(),
              updatedAt: new Date().toISOString(),
            });
            saveState();
            renderAll();
          });
        }
        return;
      }

      var delInd = e.target.closest('[data-bmgh-del-ind]');
      if (delInd && confirm('Ștergi acest indicator?')) {
        state.indicators = state.indicators.filter(function (i) { return i.id !== delInd.getAttribute('data-bmgh-del-ind'); });
        saveState();
        renderAll();
        return;
      }

      var editAct = e.target.closest('[data-bmgh-edit-act]');
      if (editAct) {
        var act = state.actions.find(function (a) { return a.id === editAct.getAttribute('data-bmgh-edit-act'); });
        if (act) {
          openModal('Editează acțiune', actFields(act), function (data) {
            Object.assign(act, {
              title: data.title.trim(),
              targetKpi: data.targetKpi.trim(),
              deadline: data.deadline || '',
              priority: data.priority,
              status: data.status,
              effort: Math.min(5, Math.max(1, Number(data.effort) || 3)),
              impact: Math.min(5, Math.max(1, Number(data.impact) || 3)),
              notes: data.notes.trim(),
              updatedAt: new Date().toISOString(),
            });
            recalcActionScore(act);
            saveState();
            renderAll();
          });
        }
        return;
      }

      var delAct = e.target.closest('[data-bmgh-del-act]');
      if (delAct && confirm('Ștergi această acțiune?')) {
        state.actions = state.actions.filter(function (a) { return a.id !== delAct.getAttribute('data-bmgh-del-act'); });
        saveState();
        renderAll();
        return;
      }

      var cntCard = e.target.closest('[data-cnt-id]');
      if (cntCard) {
        var item = state.content.find(function (c) { return c.id === cntCard.getAttribute('data-cnt-id'); });
        if (item) {
          openModal('Editează conținut', cntFields(item), function (data) {
            Object.assign(item, {
              title: data.title.trim(),
              platform: data.platform.trim(),
              format: data.format,
              status: data.status,
              publishDate: data.publishDate || '',
              linkedAction: data.linkedAction.trim(),
              linkedActionId: data.linkedAction.trim(),
              body: data.body.trim(),
              metrics: contentMetricsFromData(data),
              updatedAt: new Date().toISOString(),
            });
            saveState();
            renderAll();
          });
        }
      }
    });

    shell.addEventListener('change', function (e) {
      var sel = e.target.closest('[data-bmgh-act-status]');
      if (sel) {
        var act2 = state.actions.find(function (a) { return a.id === sel.getAttribute('data-bmgh-act-status'); });
        if (act2) {
          act2.status = sel.value;
          recalcActionScore(act2);
          saveState();
          renderActions();
          renderAlertsBar();
        }
      }
    });

    document.getElementById('bmgh-modal-form')?.addEventListener('submit', function (e) {
      e.preventDefault();
      if (modalContext && modalContext.onSave) {
        modalContext.onSave(readModalFields(modalContext.fields));
      }
      closeModal();
    });
    document.getElementById('bmgh-modal-cancel')?.addEventListener('click', closeModal);
    document.getElementById('bmgh-modal-close')?.addEventListener('click', closeModal);
  }

  function init() {
    var shell = document.getElementById('bmgh-shell');
    if (!shell) return;
    pageMode = shell.getAttribute('data-bmgh-mode') || 'indicators';
    state = defaultState();
    bindEvents();
    loadFromApi().then(function () {
      renderAll();
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
