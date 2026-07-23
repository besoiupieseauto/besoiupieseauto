/**
 * Tab Intelligence PRO — dashboard vizual Centru AI
 */
(function () {
  'use strict';

  var RING_C = 327;

  function intelApi() {
    var root = document.getElementById('ai-hub-root');
    return (root && root.getAttribute('data-intel-api')) || '';
  }

  function esc(s) {
    var d = document.createElement('div');
    d.textContent = s == null ? '' : String(s);
    return d.innerHTML;
  }

  function toast(msg) {
    if (window.AiHub && window.AiHub.toast) window.AiHub.toast(msg);
    else {
      var el = document.getElementById('ai-hub-toast');
      if (el) { el.textContent = msg; el.classList.remove('hidden'); setTimeout(function () { el.classList.add('hidden'); }, 4000); }
    }
  }

  function csrfHeader() {
    var m = document.querySelector('meta[name="csrf-token"]');
    var h = { 'Content-Type': 'application/json', Accept: 'application/json' };
    if (m && m.content) h['X-Admin-CSRF'] = m.content;
    return h;
  }

  function fetchJson(url, options, timeoutMs) {
    options = options || {};
    timeoutMs = timeoutMs || 90000;
    var controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
    var timer = controller ? setTimeout(function () { controller.abort(); }, timeoutMs) : null;
    var opts = Object.assign({ credentials: 'same-origin', headers: csrfHeader(), signal: controller ? controller.signal : undefined }, options);
    return fetch(url, opts).then(function (r) {
      return r.text().then(function (text) {
        var json = null;
        try { json = text ? JSON.parse(text) : null; } catch (e) { /* */ }
        if (!r.ok) {
          if (r.status === 401 && window.BesoiuAdminAuth && window.BesoiuAdminAuth.handleUnauthorized(r)) {
            return Promise.reject(new Error('Sesiune expirată — redirecționare login…'));
          }
          throw new Error((json && json.message) ? json.message : ('HTTP ' + r.status));
        }
        return json;
      });
    }).finally(function () { if (timer) clearTimeout(timer); });
  }

  function get(action, params) {
    var q = new URLSearchParams(params || {});
    q.set('action', action);
    return fetchJson(intelApi() + '?' + q.toString());
  }

  function post(body, timeoutMs) {
    return fetchJson(intelApi(), { method: 'POST', body: JSON.stringify(body) }, timeoutMs || 180000);
  }

  function setText(id, val) {
    var el = document.getElementById(id);
    if (el) el.textContent = val;
  }

  function statusClass(s) {
    return 'is-' + (s || 'idle');
  }

  function fa(cls) {
    cls = cls || 'fa-solid fa-circle';
    if (cls.indexOf('fa-solid') === -1 && cls.indexOf('fa-regular') === -1 && cls.indexOf('fa-brands') === -1) {
      cls = 'fa-solid ' + cls;
    }
    return '<i class="' + cls + '" aria-hidden="true"></i>';
  }

  function renderHealth(health) {
    health = health || {};
    var score = parseInt(health.score, 10) || 0;
    setText('ai-intel-health-score', String(score));
    setText('ai-intel-health-label', health.label || '—');

    var ring = document.getElementById('ai-intel-health-ring');
    if (ring) {
      ring.setAttribute('stroke-dashoffset', String(RING_C - (RING_C * score / 100)));
      ring.classList.remove('is-ok', 'is-warn', 'is-fail');
      ring.classList.add(score >= 85 ? 'is-ok' : (score >= 55 ? 'is-warn' : 'is-fail'));
    }

    var hero = document.querySelector('.ai-intel-hero');
    if (hero) {
      hero.classList.remove('ai-intel-hero--ok', 'ai-intel-hero--warn', 'ai-intel-hero--fail');
      hero.classList.add(score >= 85 ? 'ai-intel-hero--ok' : (score >= 55 ? 'ai-intel-hero--warn' : 'ai-intel-hero--fail'));
    }

    var list = document.getElementById('ai-intel-health-checks');
    if (!list) return;
    var checks = health.checks || [];
    list.innerHTML = checks.map(function (c) {
      var icon = c.status === 'ok' ? 'fa-circle-check' : (c.status === 'warn' ? 'fa-triangle-exclamation' : 'fa-circle-xmark');
      return '<li class="ai-intel-check ' + statusClass(c.status) + '">' +
        '<span class="ai-intel-check__dot">' + fa(icon) + '</span>' +
        '<div class="ai-intel-check__body">' +
        '<strong>' + esc(c.label) + '</strong>' +
        '<small>' + esc(c.hint || '') + '</small></div></li>';
    }).join('');
  }

  function renderPipelineFlow(steps) {
    var el = document.getElementById('ai-intel-pipeline-flow');
    if (!el) return;
    steps = steps || [];
    el.innerHTML = steps.map(function (s, i) {
      var arrow = i > 0 ? '<div class="ai-intel-flow__arrow" aria-hidden="true">' + fa('fa-chevron-right') + '</div>' : '';
      return arrow + '<div class="ai-intel-flow__step ' + statusClass(s.status) + '">' +
        '<div class="ai-intel-flow__icon">' + fa(s.icon || 'fa-circle') + '</div>' +
        '<div class="ai-intel-flow__num">Pas ' + esc(s.step) + '</div>' +
        '<div class="ai-intel-flow__title">' + esc(s.title) + '</div>' +
        '<div class="ai-intel-flow__sub">' + esc(s.subtitle) + '</div>' +
        '<div class="ai-intel-flow__metric">' + esc(s.metric) + '</div>' +
        '</div>';
    }).join('');
  }

  function renderKpis(data) {
    var el = document.getElementById('ai-intel-kpis');
    if (!el) return;
    var storage = data.storage || {};
    var queue = data.queue || {};
    var ext = data.external_llm || {};
    var oll = data.ollama || {};

    var cards = [
      { icon: 'fa-solid fa-satellite-dish', label: 'Evenimente', val: storage.events || 0, sub: 'în MySQL', tone: (storage.events || 0) > 0 ? 'ok' : 'warn' },
      { icon: 'fa-solid fa-chart-column', label: 'Agregate', val: storage.aggregates || 0, sub: 'semnale CTR', tone: (storage.aggregates || 0) > 0 ? 'ok' : 'warn' },
      { icon: 'fa-solid fa-dna', label: 'Embeddings', val: storage.embeddings || 0, sub: 'produse indexate', tone: (storage.embeddings || 0) > 0 ? 'ok' : 'warn' },
      { icon: 'fa-solid fa-inbox', label: 'Coadă', val: queue.redis_active ? 'Redis' : 'Fișier', sub: queue.name || 'ai-events', tone: queue.redis_active ? 'ok' : 'warn' },
      { icon: 'fa-solid fa-cloud', label: 'API extern', val: ext.configured ? 'OK' : 'Lipsă', sub: ext.provider || 'opțional', tone: ext.configured ? 'ok' : 'idle' },
      { icon: 'fa-solid fa-bolt', label: 'Ollama', val: oll.ready ? 'Online' : 'Offline', sub: oll.embed_model || '—', tone: oll.ready ? 'ok' : 'fail' },
    ];

    el.innerHTML = cards.map(function (c, idx) {
      return '<article class="ai-intel-kpi ai-intel-kpi--' + c.tone + '" style="animation-delay:' + (idx * 0.06) + 's">' +
        '<span class="ai-intel-kpi__icon">' + fa(c.icon) + '</span>' +
        '<div class="ai-intel-kpi__label">' + esc(c.label) + '</div>' +
        '<div class="ai-intel-kpi__val">' + esc(String(c.val)) + '</div>' +
        '<div class="ai-intel-kpi__sub">' + esc(c.sub) + '</div></article>';
    }).join('');
  }

  function renderTimeline(rows) {
    var el = document.getElementById('ai-intel-chart-timeline');
    if (!el) return;
    rows = rows || [];
    if (!rows.length) {
      el.innerHTML = '<p class="ai-intel-empty-state">Niciun eveniment încă.</p>';
      return;
    }
    var max = Math.max.apply(null, rows.map(function (r) { return r.total || 0; }).concat([1]));
    el.innerHTML = '<div class="ai-intel-bars">' + rows.map(function (r) {
      var h = Math.round(((r.total || 0) / max) * 100);
      return '<div class="ai-intel-bars__col" title="' + esc(r.label + ': ' + r.total) + '">' +
        '<div class="ai-intel-bars__fill" style="height:' + h + '%"></div>' +
        '<span class="ai-intel-bars__val">' + esc(r.total) + '</span>' +
        '<span class="ai-intel-bars__lbl">' + esc(r.label) + '</span></div>';
    }).join('') + '</div>';
  }

  function renderBreakdown(rows) {
    var el = document.getElementById('ai-intel-chart-breakdown');
    if (!el) return;
    rows = rows || [];
    if (!rows.length) {
      el.innerHTML = '<p class="ai-intel-empty-state">Fără date.</p>';
      return;
    }
    var total = rows.reduce(function (s, r) { return s + (r.count || 0); }, 0) || 1;
    var colors = ['#4f46e5', '#059669', '#d97706', '#dc2626', '#0891b2', '#7c3aed', '#64748b', '#db2777'];
    el.innerHTML = rows.map(function (r, i) {
      var pct = Math.round(((r.count || 0) / total) * 100);
      var color = colors[i % colors.length];
      return '<div class="ai-intel-breakdown-row">' +
        '<div class="ai-intel-breakdown-row__head">' +
        '<span class="ai-intel-breakdown-row__dot" style="background:' + color + '"></span>' +
        '<span>' + esc(r.label) + '</span>' +
        '<strong>' + esc(r.count) + ' (' + pct + '%)</strong></div>' +
        '<div class="ai-intel-breakdown-row__bar"><span style="width:' + pct + '%;background:' + color + '"></span></div></div>';
    }).join('');
  }

  function renderOllama(oll) {
    oll = oll || {};
    var el = document.getElementById('ai-intel-ollama-detail');
    if (!el) return;
    var ready = !!oll.ready;
    var html = '<div class="ai-intel-service-card__status ' + (ready ? 'is-ok' : 'is-fail') + '">' +
      '<span class="ai-intel-service-card__pulse"></span>' +
      (ready ? 'Online' : 'Offline') + '</div>' +
      '<dl class="ai-intel-dl">' +
      '<div><dt>Chat</dt><dd>' + esc(oll.chat_model || '—') + '</dd></div>' +
      '<div><dt>Embed</dt><dd>' + esc(oll.embed_model || '—') + '</dd></div>' +
      '<div><dt>KV cache</dt><dd>' + esc(oll.kv_cache_type || '—') + '</dd></div>' +
      '<div><dt>Dimensiuni embed</dt><dd>' + esc(String((oll.embeddings && oll.embeddings.dims) || 0)) + '</dd></div>' +
      '</dl>';
    if (oll.embeddings && oll.embeddings.error) {
      html += '<div class="ai-intel-alert">' + esc(oll.embeddings.error) +
        '<br><code>ollama pull nomic-embed-text</code></div>';
    }
    el.innerHTML = html;
  }

  function renderTasks(tasks) {
    var el = document.getElementById('ai-intel-task-list');
    if (!el) return;
    tasks = tasks || [];
    el.innerHTML = tasks.map(function (t) {
      var local = t.route === 'local';
      return '<div class="ai-intel-task-chip ai-intel-task-chip--' + (local ? 'local' : 'ext') + '">' +
        '<span class="ai-intel-task-chip__route">' + (local ? fa('fa-bolt') + ' Local' : fa('fa-cloud') + ' Extern') + '</span>' +
        '<code>' + esc(t.task_type) + '</code>' +
        '<p>' + esc(t.description) + '</p></div>';
    }).join('');
  }

  function renderTopProducts(rows) {
    var el = document.getElementById('ai-intel-top-products');
    if (!el) return;
    if (!rows.length) {
      el.innerHTML = '<p class="ai-intel-empty-state">Niciun agregat — activează tracking + cron.</p>';
      return;
    }
    var maxViews = Math.max.apply(null, rows.map(function (r) { return parseInt(r.views, 10) || 0; }).concat([1]));
    el.innerHTML = rows.map(function (r) {
      var views = parseInt(r.views, 10) || 0;
      var pct = Math.round((views / maxViews) * 100);
      var ctr = parseFloat(r.ctr) || 0;
      return '<div class="ai-intel-rank-item">' +
        '<div class="ai-intel-rank-item__head"><code>' + esc(r.entity_id) + '</code>' +
        '<span>' + views + ' views · ' + (parseInt(r.clicks, 10) || 0) + ' clicks</span></div>' +
        '<div class="ai-intel-rank-item__bar"><span style="width:' + pct + '%"></span></div>' +
        '<small>CTR ' + (ctr * 100).toFixed(1) + '% · ' + esc(r.period_date) + '</small></div>';
    }).join('');
  }

  function renderRoutes(rows) {
    var el = document.getElementById('ai-intel-routes');
    if (!el) return;
    if (!rows.length) {
      el.innerHTML = '<p class="ai-intel-empty-state">Nicio rută încă.</p>';
      return;
    }
    el.innerHTML = rows.slice(0, 8).map(function (r) {
      return '<div class="ai-intel-route-item ' + (r.ok ? 'is-ok' : 'is-fail') + '">' +
        '<div class="ai-intel-route-item__dot"></div>' +
        '<div><strong>' + esc(r.task_type) + '</strong> → ' + esc(r.route || '') +
        ' <span class="ai-intel-muted">' + esc(r.latency_ms || '') + 'ms</span>' +
        (r.error ? '<br><small class="ai-intel-alert-inline">' + esc(r.error) + '</small>' : '') +
        '</div></div>';
    }).join('');
  }

  function renderCron(jobs) {
    var el = document.getElementById('ai-intel-cron');
    if (!el) return;
    el.innerHTML = (jobs || []).map(function (j) {
      return '<div class="ai-intel-cron-item"><span class="ai-intel-cron-item__sched">' + esc(j.schedule) + '</span>' +
        '<code>' + esc(j.command) + '</code><p>' + esc(j.role) + '</p></div>';
    }).join('');
  }

  function renderLearn(cards) {
    var el = document.getElementById('ai-intel-learn');
    if (!el) return;
    el.innerHTML = (cards || []).map(function (c, i) {
      return '<div class="ai-intel-learn-step"><span class="ai-intel-learn-step__n">' + (i + 1) + '</span>' +
        '<div><strong>' + esc(c.title) + '</strong><p>' + esc(c.text) + '</p></div></div>';
    }).join('');
  }

  function renderPipelineMap(rows) {
    var el = document.getElementById('ai-intel-pipeline-map');
    if (!el) return;
    el.innerHTML = (rows || []).map(function (r) {
      return '<div class="ai-intel-tech-row"><strong>' + esc(r.layer) + '</strong> · ' + esc(r.component) +
        '<br><small>' + esc(r.uses) + ' → ' + esc(r.stores) + '</small></div>';
    }).join('');
  }

  function scoreBar(label, val, maxVal, color) {
    var pct = maxVal > 0 ? Math.min(100, Math.round((val / maxVal) * 100)) : 0;
    return '<div class="ai-intel-score-row"><span>' + esc(label) + '</span>' +
      '<div class="ai-intel-score-row__track"><span style="width:' + pct + '%;background:' + color + '"></span></div>' +
      '<em>' + esc(typeof val === 'number' ? val.toFixed(2) : val) + '</em></div>';
  }

  function renderSearchResult(data) {
    var hits = (data && data.hits) || [];
    var meta = (data && data.meta) || {};
    var metaEl = document.getElementById('ai-intel-search-meta');
    if (metaEl) {
      metaEl.innerHTML = '<span class="ai-intel-tag">' + esc(meta.pipeline || meta.backend || 'search') + '</span> ' +
        hits.length + ' rezultate · pop weight ' + esc(String(meta.popularity_weight != null ? meta.popularity_weight : '—'));
    }
    var el = document.getElementById('ai-intel-search-results');
    if (!el) return;
    if (!hits.length) {
      el.innerHTML = '<p class="ai-intel-empty-state">Niciun rezultat — verifică embeddings sau query.</p>';
      return;
    }
    el.innerHTML = hits.map(function (h, idx) {
      var s = h.scores || {};
      var sig = h.signals || {};
      var final = parseFloat(s.final) || 0;
      return '<article class="ai-intel-hit">' +
        '<div class="ai-intel-hit__rank">#' + (idx + 1) + '</div>' +
        '<div class="ai-intel-hit__body">' +
        '<h4>' + esc((h.name || '').slice(0, 64)) + '</h4>' +
        '<div class="ai-intel-hit__meta">' + esc(h.brand || '') + (h.oem ? ' · ' + esc(h.oem) : '') +
        ' · <strong>' + esc(final.toFixed(3)) + '</strong> final</div>' +
        scoreBar('Keyword', parseFloat(s.keyword) || 0, 1, '#6366f1') +
        scoreBar('Semantic', parseFloat(s.semantic) || 0, 1, '#0891b2') +
        scoreBar('Hybrid', parseFloat(s.hybrid) || 0, 1, '#7c3aed') +
        scoreBar('Popularitate', parseFloat(s.popularity) || 0, 1, '#d97706') +
        '<small class="ai-intel-muted">' + (sig.views || 0) + ' views agregate</small></div></article>';
    }).join('');
  }

  var visitorsPage = 1;

  function formatDt(val) {
    if (!val) return '—';
    var d = new Date(val);
    if (isNaN(d.getTime())) return String(val).slice(0, 16);
    return d.toLocaleString('ro-RO', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
  }

  function deviceIcon(type) {
    if (type === 'mobile') return fa('fa-mobile-screen-button');
    if (type === 'tablet') return fa('fa-tablet-screen-button');
    return fa('fa-laptop');
  }

  function renderVisitorStats(stats) {
    stats = stats || {};
    setText('ai-intel-visitors-today', String(stats.live_today || 0));

    var countriesEl = document.getElementById('ai-intel-countries');
    if (countriesEl) {
      var countries = stats.countries || [];
      countriesEl.innerHTML = countries.length ? countries.map(function (c) {
        var cc = (c.country_code || '').toLowerCase();
        var flagHtml = cc.length === 2
          ? String.fromCodePoint(0x1F1E6 + cc.charCodeAt(0) - 65, 0x1F1E6 + cc.charCodeAt(1) - 65)
          : fa('fa-earth-americas');
        return '<div class="ai-intel-country-row"><span>' + flagHtml + ' ' + esc(c.country_name || c.country_code) + '</span><strong>' + esc(c.sessions) + '</strong></div>';
      }).join('') : '<p class="ai-intel-empty-state">Fără date geo încă.</p>';
    }

    var devicesEl = document.getElementById('ai-intel-devices');
    if (devicesEl) {
      var devices = stats.devices || [];
      devicesEl.innerHTML = devices.length ? devices.map(function (d) {
        return '<div class="ai-intel-country-row"><span>' + deviceIcon(d.device_type) + ' ' + esc(d.device_type || '?') + '</span><strong>' + esc(d.sessions) + '</strong></div>';
      }).join('') : '—';
    }
  }

  function renderVisitorsTable(data) {
    data = data || {};
    var tbody = document.getElementById('ai-intel-visitors-tbody');
    if (!tbody) return;
    var rows = data.sessions || [];
    if (!rows.length) {
      tbody.innerHTML = '<tr><td colspan="6" class="ai-intel-empty-state">Niciun vizitator în perioada selectată.</td></tr>';
    } else {
      tbody.innerHTML = rows.map(function (s) {
        var flag = s.country_flag || (s.country_code === 'LAN' ? '🏠' : '🌍');
        var loc = flag + ' ' + esc(s.country_name || (s.country_code === 'LAN' ? 'Rețea locală' : '—'));
        if (s.city) loc += ' · ' + esc(s.city);
        var ip = s.ip_address || (s.country_code === 'LAN' ? '127.0.0.1' : '—');
        return '<tr class="ai-intel-visitors-row" data-session-id="' + esc(s.session_id) + '" tabindex="0">' +
          '<td>' + formatDt(s.last_seen_at || s.started_at) + '</td>' +
          '<td>' + loc + '</td>' +
          '<td><code>' + esc(ip) + '</code></td>' +
          '<td>' + deviceIcon(s.device_type) + ' ' + esc(s.browser || s.os || '') + '</td>' +
          '<td><strong>' + esc(s.event_count) + '</strong></td>' +
          '<td>' + esc((s.last_event_label || s.last_event_type || '—').slice(0, 48)) + '</td></tr>';
      }).join('');
    }

    var label = document.getElementById('ai-intel-visitors-pager-label');
    if (label) label.textContent = 'Pagina ' + (data.page || 1) + ' / ' + (data.pages || 1) + ' · ' + (data.total || 0) + ' sesiuni';
    var prev = document.getElementById('ai-intel-visitors-prev');
    var next = document.getElementById('ai-intel-visitors-next');
    if (prev) prev.disabled = (data.page || 1) <= 1;
    if (next) next.disabled = (data.page || 1) >= (data.pages || 1);
  }

  function openSessionDrawer(sessionId) {
    if (!sessionId) return;
    var drawer = document.getElementById('ai-intel-session-drawer');
    if (drawer) drawer.hidden = false;
    var meta = document.getElementById('ai-intel-drawer-meta');
    var timeline = document.getElementById('ai-intel-drawer-timeline');
    if (timeline) timeline.innerHTML = '<p class="ai-intel-empty-state">Se încarcă…</p>';

    get('session_detail', { session_id: sessionId }).then(function (json) {
      var s = json.data || {};
      if (meta) {
        meta.innerHTML = '<dl class="ai-intel-drawer-dl">' +
          '<div><dt>IP</dt><dd><code>' + esc(s.ip_address || '—') + '</code></dd></div>' +
          '<div><dt>Locație</dt><dd>' + esc((s.country_flag || '') + ' ' + (s.country_name || '') + (s.city ? ', ' + s.city : '')) + '</dd></div>' +
          '<div><dt>Device</dt><dd>' + deviceIcon(s.device_type) + ' ' + esc(s.os || '') + ' · ' + esc(s.browser || '') + '</dd></div>' +
          '<div><dt>User-Agent</dt><dd><small>' + esc(s.user_agent || '—') + '</small></dd></div>' +
          '<div><dt>Referrer</dt><dd>' + esc(s.referrer || 'direct') + '</dd></div>' +
          '<div><dt>Landing</dt><dd><code>' + esc(s.landing_page || '—') + '</code></dd></div>' +
          '<div><dt>Sesiune</dt><dd><code>' + esc(s.session_id) + '</code></dd></div>' +
          '</dl>';
      }
      if (timeline) {
        var events = s.events || [];
        timeline.innerHTML = events.length ? events.map(function (ev) {
          var details = '';
          if (ev.metadata) {
            if (ev.metadata.query) details += '<br><small>Căutare: «' + esc(ev.metadata.query) + '»</small>';
            if (ev.metadata.element_text) details += '<br><small>Element: ' + esc(ev.metadata.element_text) + '</small>';
            if (ev.metadata.href) details += '<br><small>Link: ' + esc(ev.metadata.href) + '</small>';
            if (ev.metadata.subject) details += '<br><small>' + esc(ev.metadata.subject) + '</small>';
            if (ev.metadata.path) details += '<br><small>' + esc(ev.metadata.path) + '</small>';
          }
          return '<div class="ai-intel-timeline-item">' +
            '<div class="ai-intel-timeline-item__time">' + formatDt(ev.ts) + '</div>' +
            '<div class="ai-intel-timeline-item__icon">' + fa(ev.icon || 'fa-circle-dot') + '</div>' +
            '<div class="ai-intel-timeline-item__body"><strong>' + esc(ev.label) + '</strong>' +
            details + '</div></div>';
        }).join('') : '<p class="ai-intel-empty-state">Fără evenimente.</p>';
      }
    }).catch(function (e) { toast(e.message); });
  }

  function closeSessionDrawer() {
    var drawer = document.getElementById('ai-intel-session-drawer');
    if (drawer) drawer.hidden = true;
  }

  function enrichVisitors(silent) {
    return get('enrich_sessions', { limit: 200 }).then(function (json) {
      var d = json.data || {};
      if (!silent && (d.updated || 0) > 0) {
        toast('Geo: ' + d.updated + ' sesiuni actualizate');
      }
      return d;
    }).catch(function () { return null; });
  }

  function loadVisitors() {
    if (!document.getElementById('ai-intel-visitors')) return;
    var days = parseInt(document.getElementById('ai-intel-visitors-days')?.value || '7', 10);
    var q = (document.getElementById('ai-intel-visitors-q')?.value || '').trim();
    return get('sessions_list', { days: days, page: visitorsPage, limit: 25, q: q }).then(function (json) {
      renderVisitorsTable(json.data || {});
    }).catch(function (e) { toast('Vizitatori: ' + e.message); });
  }

  function loadVisitorStatsOnly() {
    var days = parseInt(document.getElementById('ai-intel-visitors-days')?.value || '7', 10);
    return get('visitor_stats', { days: days }).then(function (json) {
      renderVisitorStats(json.data || {});
    }).catch(function () { /* optional */ });
  }

  function renderDashboard(data) {
    data = data || {};
    setText('ai-intel-db-name', data.database || '—');
    renderHealth(data.health);
    renderPipelineFlow(data.pipeline_flow);
    renderKpis(data);
    renderTimeline(data.event_timeline);
    renderBreakdown(data.event_breakdown);
    renderOllama(data.ollama);
    renderTasks(data.tasks);
    renderTopProducts(data.top_products || []);
    renderRoutes(data.recent_routes || []);
    renderCron(data.cron_jobs);
    renderLearn(data.learn);
    renderPipelineMap(data.pipelines);
    renderVisitorStats(data.visitor_stats);
    enrichVisitors(true).then(function () {
      loadVisitorStatsOnly();
      loadVisitors();
    });
    if (window.AiHub && window.AiHub.refreshOverview) window.AiHub.refreshOverview();
  }

  function loadDashboard() {
    if (!intelApi()) return;
    return get('dashboard').then(function (json) {
      renderDashboard(json.data || {});
    }).catch(function (e) { toast('Intelligence: ' + e.message); });
  }

  function bindEvents() {
    document.getElementById('ai-intel-refresh')?.addEventListener('click', function () {
      loadDashboard().then(function () { toast('Dashboard reîmprospătat'); });
    });
    document.getElementById('ai-intel-pop-weight')?.addEventListener('input', function (e) {
      setText('ai-intel-pop-val', (e.target.value || 0) + '%');
    });
    document.getElementById('ai-intel-search-btn')?.addEventListener('click', function () {
      var q = (document.getElementById('ai-intel-search-q')?.value || '').trim();
      if (!q) { toast('Introdu un query.'); return; }
      var pop = parseInt(document.getElementById('ai-intel-pop-weight')?.value || '15', 10) / 100;
      var results = document.getElementById('ai-intel-search-results');
      if (results) results.innerHTML = '<p class="ai-intel-empty-state">Se caută…</p>';
      post({ action: 'test_search', query: q, limit: 10, popularity_weight: pop })
        .then(function (json) { renderSearchResult(json.data || {}); })
        .catch(function (e) { toast(e.message); });
    });
    document.getElementById('ai-intel-index-btn')?.addEventListener('click', function () {
      var out = document.getElementById('ai-intel-ops-output');
      if (out) { out.classList.remove('hidden'); out.textContent = 'Indexare produse…'; }
      post({ action: 'index_products', limit: 100 }, 300000).then(function (json) {
        if (out) out.textContent = JSON.stringify(json.data || json, null, 2);
        loadDashboard(); toast('Indexare finalizată');
      }).catch(function (e) { if (out) out.textContent = e.message; toast(e.message); });
    });
    document.getElementById('ai-intel-aggregate-btn')?.addEventListener('click', function () {
      var out = document.getElementById('ai-intel-ops-output');
      if (out) { out.classList.remove('hidden'); out.textContent = 'Agregare…'; }
      post({ action: 'run_aggregate', days: 1 }).then(function (json) {
        if (out) out.textContent = JSON.stringify(json.data || json, null, 2);
        loadDashboard(); toast('Agregate recalculate');
      }).catch(function (e) { if (out) out.textContent = e.message; toast(e.message); });
    });
    document.addEventListener('ai-hub:tab', function (e) {
      if (e.detail && e.detail.tab === 'intelligence') loadDashboard();
    });
    document.addEventListener('ai-hub:refresh', function () {
      var panel = document.getElementById('ai-hub-panel-intelligence');
      if (panel && !panel.hidden) loadDashboard();
    });
    bindVisitorEvents();
  }

  function bindVisitorEvents() {
    document.getElementById('ai-intel-visitors-refresh')?.addEventListener('click', function () {
      visitorsPage = 1;
      enrichVisitors(true).then(function () {
        loadVisitorStatsOnly();
        loadVisitors();
      });
    });
    document.getElementById('ai-intel-visitors-enrich')?.addEventListener('click', function () {
      enrichVisitors(false).then(function () {
        loadVisitorStatsOnly();
        loadVisitors();
      });
    });
    document.getElementById('ai-intel-visitors-days')?.addEventListener('change', function () {
      visitorsPage = 1;
      loadVisitorStatsOnly();
      loadVisitors();
    });
    document.getElementById('ai-intel-visitors-q')?.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') { visitorsPage = 1; loadVisitors(); }
    });
    document.getElementById('ai-intel-visitors-prev')?.addEventListener('click', function () {
      if (visitorsPage > 1) { visitorsPage--; loadVisitors(); }
    });
    document.getElementById('ai-intel-visitors-next')?.addEventListener('click', function () {
      visitorsPage++; loadVisitors();
    });
    document.getElementById('ai-intel-visitors-tbody')?.addEventListener('click', function (e) {
      var row = e.target.closest('.ai-intel-visitors-row');
      if (row) openSessionDrawer(row.getAttribute('data-session-id'));
    });
    document.getElementById('ai-intel-drawer-close')?.addEventListener('click', closeSessionDrawer);
    document.getElementById('ai-intel-drawer-backdrop')?.addEventListener('click', closeSessionDrawer);
  }

  function init() {
    if (!document.getElementById('ai-intel-root')) return;
    bindEvents();
    var panel = document.getElementById('ai-hub-panel-intelligence');
    if (panel && !panel.hidden) loadDashboard();
  }

  window.AiIntelligence = { refresh: loadDashboard };

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
