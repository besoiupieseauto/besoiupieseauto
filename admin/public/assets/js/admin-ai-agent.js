/**
 * AI Agent admin — tab-uri, bibliotecă, editor, roboți.
 */
(function () {
  'use strict';

  function readConfig() {
    var el = document.getElementById('ai-agent-cfg');
    if (!el) {
      var root = document.getElementById('ai-agent-root');
      return { api: root ? root.getAttribute('data-api') || '' : '', catLabels: {} };
    }
    try {
      return JSON.parse(el.textContent || '{}');
    } catch (e) {
      return { api: '', catLabels: {} };
    }
  }

  var cfg = readConfig();
  var ENDPOINT = cfg.api || '';
  var catLabels = cfg.catLabels || {};
  var aiUrls = cfg.urls || {};
  var agentSections = cfg.agentSections || {};
  var healthLegend = cfg.healthLegend || {};

  var agents = [];
  var selectedSlug = 'context-master';
  var liveRoutedSlug = '';
  var activeCategory = 'all';
  var searchQuery = '';
  var activeTab = 'centru';
  var lastStatusJson = {};
  var livePollTimer = null;
  var livePollSec = 25;
  var livePollBusy = false;
  var lastLiveRefresh = 0;
  var cachedLiveEvents = [];
  var LIVE_EVENTS_SHOW = 10;
  var libraryEntriesCache = [];
  var learnedLinesCache = [];

  var TAB_HINTS = {
    centru: 'Centru de comandă — briefing AI, KPI-uri, ultimele acțiuni și propuneri. Totul se actualizează din cron + API.',
    biblioteca: 'Șabloane: alegi un specialist și apeși Adaugă (copie pe server). Nu instalează cod PHP.',
    agenti: 'Agenții mei: lista agenților deja copiați pe server. Click pe unul pentru detalii.',
    editor: 'Editor: modifici promptul (reguli) și contextul manual al agentului selectat.',
    roboti: 'Roboți: alegi ce agent AI folosește fiecare bot WhatsApp / chat.',
    ollama: 'Ollama: chat local cu cei 4 agenți specializați (imagini, produse, clienți, statistici).',
    supervizor: 'Supervizor: cron + jurnal Composer (mesaje făcut/nefăcut) — scroll la „Composer — mesagile tale”.',
    monitor: 'Live: evenimente recente + agent activ ales automat după activitatea site-ului.',
  };

  var HASH_TO_TAB = {
    live: 'monitor',
    monitor: 'monitor',
    supervizor: 'supervizor',
    biblioteca: 'biblioteca',
    agenti: 'agenti',
    editor: 'editor',
    roboti: 'roboti',
    ollama: 'ollama',
    centru: 'centru',
  };

  function normalizeAdminPath(pathname) {
    return String(pathname || '').replace(/\/+$/, '').replace(/^\/admin\/public(\/|$)/, '/admin$1');
  }

  function resolveHashTab() {
    var hashTab = (window.location.hash || '').replace('#', '').toLowerCase();
    return HASH_TO_TAB[hashTab] || '';
  }

  function applyHashTab() {
    var tab = resolveHashTab();
    if (tab) switchTab(tab, { scroll: true });
  }

  function workspaceForHref(href) {
    var ctx = window.BESOIU_WORKSPACE_CTX;
    if (!ctx || !ctx.pathToWorkspace || !href) {
      return null;
    }
    var path = String(href).split('?')[0].split('#')[0];
    if (path.indexOf('http') === 0) {
      try {
        path = new URL(path).pathname;
      } catch (ignore) {
        return null;
      }
    }
    path = normalizeAdminPath(path.replace(/\/+$/, '') || '/');

    var map = ctx.pathToWorkspace;
    if (map[path]) {
      return map[path];
    }

    var best = null;
    var bestLen = 0;
    Object.keys(map).forEach(function (prefix) {
      var normPrefix = normalizeAdminPath(prefix.replace(/\/+$/, '') || '/');
      if (path === normPrefix || path.indexOf(normPrefix + '/') === 0) {
        if (normPrefix.length > bestLen) {
          bestLen = normPrefix.length;
          best = map[prefix];
        }
      }
    });

    return best;
  }

  function navigateAway(href) {
    if (!href || href === '#') return;

    var fakeAnchor = document.createElement('a');
    fakeAnchor.setAttribute('href', href);
    if (handleSamePageHashNav(fakeAnchor, null)) {
      return;
    }

    var ctx = window.BESOIU_WORKSPACE_CTX;
    var targetWs = workspaceForHref(href);
    if (ctx && ctx.workspace && targetWs && targetWs !== ctx.workspace) {
      var switchBase = '/admin/workspace-switch';
      try {
        if (window.BESOIU_ADMIN && window.BESOIU_ADMIN.workspaceSwitch) {
          switchBase = window.BESOIU_ADMIN.workspaceSwitch;
        }
      } catch (ignore) { /* noop */ }
      var switchUrl = switchBase
        + '?to=' + encodeURIComponent(targetWs)
        + '&redirect=' + encodeURIComponent(href);
      window.location.assign(switchUrl);
      return;
    }

    window.location.assign(href);
  }

  function handleSamePageHashNav(anchor, e) {
    if (!anchor) return false;
    var href = String(anchor.getAttribute('href') || '').trim();
    if (!href) return false;

    var hash = '';
    if (href.charAt(0) === '#') {
      hash = href.slice(1).toLowerCase();
    } else {
      try {
        var url = new URL(href, window.location.href);
        if (normalizeAdminPath(url.pathname) !== normalizeAdminPath(window.location.pathname)) {
          return false;
        }
        hash = (url.hash || '').replace('#', '').toLowerCase();
      } catch (ignore) {
        return false;
      }
    }

    if (!hash || !HASH_TO_TAB[hash]) return false;
    if (e) {
      e.preventDefault();
      e.stopPropagation();
    }
    switchTab(HASH_TO_TAB[hash], { scroll: true });
    try {
      if (window.history && window.history.replaceState) {
        var base = window.location.pathname + window.location.search;
        window.history.replaceState(null, '', base + '#' + hash);
      }
    } catch (ignore2) { /* noop */ }
    return true;
  }

  var root = document.getElementById('ai-agent-root');
  if (!root || !ENDPOINT) {
    return;
  }

  var toast = document.getElementById('ai-agent-toast');
  var preview = document.getElementById('ai-agent-preview');
  var previewShort = document.getElementById('ai-agent-preview-short');
  var listEl = document.getElementById('ai-agent-list');
  var importSelect = document.getElementById('ai-agent-import-select');
  var tplTbody = document.getElementById('ai-agent-templates-tbody');
  var tplEmpty = document.getElementById('ai-agent-tpl-empty');
  var tplSearch = document.getElementById('ai-agent-tpl-search');
  var catFilters = document.getElementById('ai-agent-cat-filters');
  var botsEl = document.getElementById('ai-agent-bots');
  var tempRange = document.getElementById('ai-agent-field-temperature');
  var tempLabel = document.getElementById('ai-agent-temp-label');
  var healthBox = document.getElementById('ai-agent-health-box');
  var detailEl = document.getElementById('ai-agent-detail');

  function showToast(msg, isError) {
    if (!toast) return;
    toast.textContent = msg;
    toast.className = 'fixed right-5 top-5 z-[200] rounded-md border px-4 py-3 text-sm shadow '
      + (isError ? 'border-red-300 bg-red-50 text-red-800' : 'border-emerald-300 bg-emerald-50 text-emerald-800');
    toast.classList.remove('hidden');
    setTimeout(function () { toast.classList.add('hidden'); }, 4500);
  }
  window.aiAgentToast = showToast;

  function escapeHtml(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];
    });
  }

  function healthClass(label) {
    if (label === 'excelent') return 'ai-health--good';
    if (label === 'ok') return 'ai-health--ok';
    return 'ai-health--bad';
  }

  function apiGet(params) {
    return fetch(ENDPOINT + '?' + new URLSearchParams(params || {}), { credentials: 'same-origin' })
      .then(function (res) { return res.text(); })
      .then(function (text) {
        var json;
        try { json = JSON.parse(text); } catch (e) {
          throw new Error('Răspuns invalid de la server.');
        }
        if (!json.success) throw new Error(json.message || 'Eroare API.');
        return json;
      });
  }

  function apiPost(body) {
    return fetch(ENDPOINT, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body),
    }).then(function (res) { return res.json(); }).then(function (json) {
      if (!json.success) throw new Error(json.message || 'Eroare la salvare.');
      return json;
    });
  }

  function setText(id, text) {
    if (window.aiMissionSetText) {
      window.aiMissionSetText(id, text);
      return;
    }
    var el = document.getElementById(id);
    if (el) el.textContent = text;
  }

  function renderGaugeSvg(remainingPct, tone) {
    var p = Math.max(0, Math.min(100, Number(remainingPct) || 0));
    var r = 38;
    var c = 2 * Math.PI * r;
    var off = c - (p / 100) * c;
    var color = tone === 'danger' ? '#ef4444' : (tone === 'warning' ? '#f59e0b' : '#14b8a6');
    return '<svg class="ai-gauge" viewBox="0 0 96 96" aria-hidden="true">'
      + '<circle class="ai-gauge__bg" cx="48" cy="48" r="' + r + '" />'
      + '<circle class="ai-gauge__ring" cx="48" cy="48" r="' + r + '" stroke="' + color + '"'
      + ' transform="rotate(-90 48 48)"'
      + ' stroke-dasharray="' + c.toFixed(2) + '" stroke-dashoffset="' + off.toFixed(2) + '" />'
      + '<text class="ai-gauge__pct" x="48" y="52" text-anchor="middle">' + Math.round(p) + '%</text>'
      + '</svg>';
  }
  window.BesoiuRenderGauge = renderGaugeSvg;

  function apiProviderTone(u) {
    var left = Number(u.requests_left);
    var used = Number(u.used_pct || 0);
    if (left <= 0 || used >= 100) return 'danger';
    if (used >= 80 || left < 10) return 'warning';
    return 'ok';
  }

  function renderApiTokenHub(hub, gridId) {
    var grid = document.getElementById(gridId);
    if (!grid) return;
    var providers = hub && hub.providers ? hub.providers : null;
    var keys = hub && hub.primary_providers ? hub.primary_providers
      : ['scrape_do', 'rapidapi_tecdoc', 'cursor'];
    var icons = { scrape_do: '🌐', rapidapi_tecdoc: '⚙', cursor: '✦' };
    var accents = { scrape_do: 'scrape', rapidapi_tecdoc: 'rapid', cursor: 'cursor' };
    if (!providers) {
      grid.innerHTML = '<div class="ai-api-empty">Buget API indisponibil — deschide Setări → Tokeni API.</div>';
      return;
    }
    var cards = keys.map(function (key) {
      var p = providers[key];
      if (!p) return '';
      var u = p.usage || {};
      var tone = apiProviderTone(u);
      var remPct = u.remaining_pct != null ? u.remaining_pct : Math.max(0, 100 - (u.used_pct || 0));
      var usedPct = Math.max(0, Math.min(100, u.used_pct || (100 - remPct)));
      var left = Math.max(0, Number(u.requests_left) || 0);
      var billingFoot = '';
      if (key === 'cursor') {
        var cb = hub.cursor_billing || (p.billing_info || null);
        if (cb) {
          billingFoot = '<footer class="ai-api-card-v2__foot">'
            + '<span>Server local: <strong>' + (cb.local_month_requests || 0).toLocaleString('ro-RO') + '</strong> apeluri/lună</span>'
            + '<a href="' + escapeHtml(cb.dashboard_billing_url || 'https://cursor.com/dashboard?tab=billing') + '" target="_blank" rel="noopener noreferrer">Billing cursor.com →</a>'
            + '</footer>';
        }
      }
      return '<article class="ai-api-card-v2 ai-api-card-v2--' + accents[key] + ' ai-api-card-v2--' + tone + '">'
        + '<div class="ai-api-card-v2__top">'
        + '<div class="ai-api-card-v2__brand"><span class="ai-api-card-v2__ico">' + (icons[key] || '•') + '</span>'
        + '<div><strong class="ai-api-card-v2__name">' + escapeHtml(p.label || key) + '</strong>'
        + '<code class="ai-api-card-v2__key">' + escapeHtml(key) + '</code></div></div>'
        + renderGaugeSvg(remPct, tone)
        + '</div>'
        + '<div class="ai-api-card-v2__bar"><div class="ai-api-card-v2__bar-fill" style="width:' + usedPct + '%"></div></div>'
        + '<p class="ai-api-card-v2__bar-label">' + usedPct + '% consumat luna curentă</p>'
        + '<div class="ai-api-card-v2__stats">'
        + '<div class="ai-api-card-v2__stat"><span>Cereri lună</span><strong>' + (u.month_requests || 0).toLocaleString('ro-RO') + ' <em>/ ' + (u.max_requests || 0).toLocaleString('ro-RO') + '</em></strong></div>'
        + '<div class="ai-api-card-v2__stat"><span>Rămase</span><strong>' + left.toLocaleString('ro-RO') + '</strong></div>'
        + '<div class="ai-api-card-v2__stat"><span>Cost/cerere</span><strong>' + (u.tokens_per_request || 1).toLocaleString('ro-RO') + ' tok</strong></div>'
        + '<div class="ai-api-card-v2__stat"><span>Tokeni rămași</span><strong>' + (u.remaining_tokens || 0).toLocaleString('ro-RO') + '</strong></div>'
        + '</div>' + billingFoot + '</article>';
    }).filter(Boolean).join('');
    grid.innerHTML = cards || '<div class="ai-api-empty">Configurează cheile în Setări → Tokeni.</div>';
  }
  window.BesoiuRenderApiTokenHub = renderApiTokenHub;

  function renderMetroEcosystem(supervisorData) {
    supervisorData = supervisorData || {};
    var metro = supervisorData.metro_llm || {};
    var eco = metro.ecosystem || {};
    var usage = metro.usage || {};
    var today = usage.today || {};
    var el = document.getElementById('ai-cmd-ecosystem');
    if (el) {
      var lvl = eco.level || 'ok';
      el.className = 'ai-cmd-ecosystem ai-cmd-ecosystem--' + lvl;
      var reasons = (eco.reasons || []).join(' · ');
      var ollama = metro.ollama || {};
      el.innerHTML = '<span class="ai-cmd-ecosystem__dot"></span>'
        + '<div><strong>' + escapeHtml(eco.label || 'Ecosistem Metro') + '</strong>'
        + (reasons ? '<span class="ai-cmd-ecosystem__reason">' + escapeHtml(reasons) + '</span>' : '')
        + '</div>'
        + '<span class="ai-metro-chip' + (ollama.ready ? ' is-on' : '') + '">'
        + (ollama.ready ? 'Ollama ' + escapeHtml(ollama.model || '') : 'Ollama oprit')
        + '</span>';
    }
    setText('ai-cmd-kpi-metro', (today.ollama || 0) + ' / ' + (today.cursor || 0));
    var hintMetro = document.getElementById('ai-cmd-hint-metro');
    if (hintMetro) {
      var ollamaHint = metro.ollama || {};
      var cron = metro.cron || {};
      hintMetro.textContent = ollamaHint.ready
        ? ('Local gratuit · ciclu ' + (cron.last_cycle_age_minutes != null ? cron.last_cycle_age_minutes + ' min' : '—'))
        : 'Ollama oprit — vezi Setări → Tokeni';
    }
    var logBody = document.getElementById('ai-cmd-metro-log-body');
    if (logBody) {
      var routes = metro.route_log || [];
      if (!routes.length) {
        logBody.innerHTML = '<p class="ai-metro-table-empty">Niciun apel LLM logat încă.</p>';
      } else {
        var rows = routes.slice(0, 10).map(function (r) {
          var via = r.routed_via || r.provider || '—';
          var viaCls = via.indexOf('ollama') >= 0 ? 'ollama' : (via.indexOf('cursor') >= 0 ? 'cursor' : 'other');
          return '<tr class="' + (r.ok ? '' : 'is-fail') + '">'
            + '<td><time>' + escapeHtml((r.ts || '').replace('T', ' ').slice(0, 16)) + '</time></td>'
            + '<td><span class="ai-metro-tag">' + escapeHtml(r.task || '') + '</span></td>'
            + '<td><span class="ai-metro-via ai-metro-via--' + viaCls + '">' + escapeHtml(via) + '</span></td>'
            + '<td><span class="ai-metro-pill ai-metro-pill--' + (r.ok ? 'ok' : 'fail') + '">' + (r.ok ? 'OK' : 'Eșec') + '</span></td>'
            + '</tr>';
        }).join('');
        logBody.innerHTML = '<table class="ai-metro-table ai-metro-table--compact"><thead><tr>'
          + '<th>Când</th><th>Task</th><th>Via</th><th>Status</th>'
          + '</tr></thead><tbody>' + rows + '</tbody></table>';
      }
    }
    var meta = document.getElementById('ai-cmd-briefing-meta');
    if (meta && metro.cron) {
      if (metro.cron.local_cycle_allowed) {
        meta.textContent = 'Ciclu local Ollama · API cloud la cerere';
      } else if (metro.cron.api_on_demand) {
        meta.textContent = 'Mod la cerere · fundal oprit';
      }
    }
  }

  function renderCommandCenter(statusJson, supervisorData) {
    statusJson = statusJson || lastStatusJson || {};
    supervisorData = supervisorData || {};
    var core = statusJson.core || {};
    var status = statusJson.status || {};
    var autonomy = statusJson.autonomy || {};
    var eventCount = core.event_count || (status.event_count != null ? status.event_count : 0);

    setText('ai-cmd-kpi-events', String(eventCount));
    setText('ai-agent-kpi-events', String(eventCount));

    var cat = supervisorData.catalog_audit || {};
    setText('ai-cmd-kpi-catalog', cat.score != null ? cat.score + '/100' : '—');
    var hintCat = document.getElementById('ai-cmd-hint-catalog');
    if (hintCat) {
      hintCat.textContent = (cat.counts && cat.counts.no_image != null)
        ? cat.counts.no_image + ' produse fără imagine'
        : 'Audit supervizor (cron)';
    }

    var tok = supervisorData.token_budget || {};
    var apiHub = tok.api_hub || null;
    renderMetroEcosystem(supervisorData);
    renderApiTokenHub(apiHub, 'ai-cmd-api-tokens-grid');
    setText('ai-cmd-kpi-tokens', tok.tokens_today != null ? tok.tokens_today.toLocaleString('ro-RO') : '—');
    var hintTok = document.getElementById('ai-cmd-hint-tokens');
    if (hintTok) {
      var scrapeU = apiHub && apiHub.providers && apiHub.providers.scrape_do ? apiHub.providers.scrape_do.usage : null;
      var rapidU = apiHub && apiHub.providers && apiHub.providers.rapidapi_tecdoc ? apiHub.providers.rapidapi_tecdoc.usage : null;
      if (scrapeU && rapidU) {
        hintTok.textContent = 'Scrape.do ~' + (scrapeU.requests_left || 0).toLocaleString('ro-RO')
          + ' cereri · RapidAPI ~' + (rapidU.requests_left || 0).toLocaleString('ro-RO') + ' cereri';
      } else if (tok.used_pct != null) {
        hintTok.textContent = tok.used_pct + '% din limită zilnică LLM';
      } else {
        hintTok.textContent = 'Buget API sincronizat cu Setări';
      }
    }

    var routed = liveRoutedSlug || selectedSlug || 'context-master';
    var routedAgent = agents.find(function (a) { return a.slug === routed; });
    setText('ai-cmd-kpi-agent', routedAgent ? routedAgent.name : routed);
    var hintAgent = document.getElementById('ai-cmd-hint-agent');
    if (hintAgent) {
      hintAgent.textContent = liveRoutedSlug ? 'Rutare automată activă' : 'Implicit până la activitate Live';
    }

    var cycle = supervisorData.last_cycle || {};
    var diagItems = ((supervisorData.diagnostics || {}).diagnostics) || [];
    var open = autonomy.proposals_open || [];
    var briefing = [];
    if (cycle.ok) briefing.push('Supervizorul a rulat ultimul ciclu cu succes.');
    else if (cycle.skipped) briefing.push('Supervizor dezactivat — pornește-l din tab Supervizor.');
    else briefing.push('Se pregătește primul ciclu cron (la 2–3 minute).');
    if (cat.score != null && cat.score < 70) briefing.push('Catalog scor ' + cat.score + '/100 — merită verificat.');
    else if (cat.score != null) briefing.push('Catalog ' + cat.score + '/100 — în regulă.');
    if (diagItems.length) briefing.push(diagItems.length + ' alertă diagnostic.');
    if (open.length) briefing.push(open.length + ' propuneri autonome deschise.');
    setText('ai-cmd-briefing-text', briefing.join(' '));

    var meta = [];
    if (status.latest_at) meta.push('Activitate: ' + new Date(status.latest_at).toLocaleString('ro-RO'));
    if (cycle.started_at) meta.push('Ciclu: ' + cycle.started_at);
    setText('ai-cmd-briefing-meta', meta.join(' · ') || 'Cron · supervizor · agenți · roboți');

    var pulse = document.getElementById('ai-mission-pulse');
    if (pulse) {
      pulse.textContent = cycle.ok ? '● Sistem activ' : (cycle.skipped ? '○ Supervizor OFF' : '● Se sincronizează');
    }

    var feedEl = document.getElementById('ai-cmd-feed');
    if (feedEl) {
      var events = (statusJson.events || cachedLiveEvents || []).slice().reverse().slice(0, 6);
      feedEl.innerHTML = events.length
        ? events.map(function (e) {
          var f = formatEventHuman(e);
          return '<li><span class="ai-cmd-feed__time">' + escapeHtml(f.when) + '</span> '
            + escapeHtml(f.icon + ' ' + f.label) + '</li>';
        }).join('')
        : '<li class="ai-empty-inline">Nicio acțiune recentă — admin și clienții generează evenimente automat.</li>';
    }

    var propEl = document.getElementById('ai-cmd-proposals');
    if (propEl) {
      var fixEnc = window.BesoiuAlertFix && window.BesoiuAlertFix.encodePayload;
      var proposals = [];
      var seenCodes = {};

      diagItems.forEach(function (d) {
        var code = d.code || '';
        if (code) seenCodes[code] = true;
        proposals.push({
          cls: 'warn',
          text: d.title || d.problem || 'Diagnostic',
          fixable: !!d.fixable,
          item: d,
        });
      });

      var opsItems = ((statusJson.ops_alerts || {}).items) || [];
      opsItems.forEach(function (it) {
        if (!it || !it.fixable) return;
        var code = it.code || '';
        if (code && seenCodes[code]) return;
        if (code) seenCodes[code] = true;
        proposals.push({
          cls: it.level === 'critical' ? 'warn' : 'ok',
          text: it.title || it.problem || 'Alertă',
          fixable: true,
          item: it,
        });
      });

      open.forEach(function (p) {
        proposals.push({
          cls: (p.priority === 'high' || p.priority === 'critical') ? 'warn' : 'ok',
          text: p.title || 'Propunere autonomă',
          fixable: false,
          item: null,
        });
      });
      var pipe = supervisorData.pipeline_health || {};
      if (pipe.total && pipe.passed < pipe.total) {
        proposals.push({ cls: 'warn', text: 'Pipeline ' + pipe.passed + '/' + pipe.total + ' teste OK', fixable: false, item: null });
      }
      var sup = supervisorData.supplier_watch || {};
      if ((sup.needs_attention || []).length) {
        proposals.push({ cls: 'warn', text: (sup.needs_attention || []).length + ' furnizori necesită atenție', fixable: false, item: null });
      }

      propEl.innerHTML = proposals.length
        ? proposals.slice(0, 8).map(function (p) {
          var fixBtn = (p.fixable && fixEnc && p.item)
            ? '<button type="button" class="ai-btn ai-btn--fix ai-btn--xs" data-alert-fix="'
              + fixEnc(p.item) + '">' + escapeHtml(p.item.fix_label || 'Corectează') + '</button>'
            : '';
          return '<li class="ai-cmd-proposal ai-cmd-proposal--' + p.cls + '">'
            + '<span class="ai-cmd-proposal__text">' + escapeHtml(p.text) + '</span>'
            + fixBtn + '</li>';
        }).join('')
        : '<li class="ai-cmd-proposal ai-cmd-proposal--ok">✓ Nimic critic — monitorizare activă în fundal.</li>';
    }

    animateCommandCenter();
  }

  function renderComposerQueryLog(payload) {
    var listEl = document.getElementById('ai-cmd-composer-queries');
    var sumEl = document.getElementById('ai-cmd-composer-summary');
    if (!listEl) return;

    var summary = (payload && payload.summary) || {};
    var rows = (payload && payload.data) || [];

    if (sumEl) {
      var total = summary.total || 0;
      var ok = summary.ok || 0;
      var fail = summary.fail || 0;
      sumEl.textContent = total
        ? (ok + ' OK · ' + (summary.partial || 0) + ' partial · ' + fail + ' de invatat (7 zile)')
        : 'Nicio query Composer inca';
    }

    if (!rows.length) {
      listEl.innerHTML = '<li class="ai-empty-inline">Scrie in widget Composer — mesajele apar aici cu evaluare.</li>';
      return;
    }

    listEl.innerHTML = rows.slice(0, 12).map(function (row) {
      var fb = row.feedback || {};
      var st = fb.status || 'fail';
      var cls = st === 'ok' ? 'ok' : (st === 'partial' ? 'partial' : 'fail');
      var when = row.at ? new Date(row.at).toLocaleString('ro-RO') : '';
      var msg = String(row.message || '').slice(0, 120);
      var missing = (fb.missing_context || []).join(', ');
      var notDone = (fb.not_done || []).join('; ');
      var extra = missing || notDone || fb.outcome_label || '';
      return '<li class="ai-cmd-composer-item ai-cmd-composer-item--' + cls + '">'
        + '<span class="ai-cmd-composer-item__badge">' + (st === 'ok' ? 'OK' : (st === 'partial' ? '~' : '!')) + '</span>'
        + '<div class="ai-cmd-composer-item__body">'
        + '<strong>' + escapeHtml(msg) + '</strong>'
        + '<span class="ai-cmd-composer-item__meta">' + escapeHtml(when) + ' · ' + escapeHtml(row.section || '') + '</span>'
        + (extra ? '<span class="ai-cmd-composer-item__note">' + escapeHtml(extra) + '</span>' : '')
        + '</div></li>';
    }).join('');
  }

  function animateCommandCenter() {
    if (activeTab !== 'centru') return;
    var briefingEl = document.getElementById('ai-cmd-briefing');
    if (briefingEl) {
      briefingEl.classList.remove('is-updated');
      void briefingEl.offsetWidth;
      briefingEl.classList.add('is-updated');
    }
    var cmdRoot = document.getElementById('ai-command-center');
    if (cmdRoot) {
      cmdRoot.classList.remove('ai-cmd-animate-in');
      void cmdRoot.offsetWidth;
      cmdRoot.classList.add('ai-cmd-animate-in');
    }
  }

  function loadCommandCenter() {
    loadBrainRulesStatus();
    return apiGet({ action: 'supervisor_status' }).then(function (j) {
      renderCommandCenter(lastStatusJson, j.data || j);
    }).catch(function () {
      renderCommandCenter(lastStatusJson, {});
    }).then(function () {
      return apiGet({ action: 'section_assist_queries', limit: 20 }).then(function (q) {
        renderComposerQueryLog(q);
      }).catch(function () {
        renderComposerQueryLog(null);
      });
    });
  }

  function switchTab(tab, opts) {
    if (!tab) return;
    activeTab = tab;
    root.querySelectorAll('.ai-mission-nav__btn, .ai-agent-tab').forEach(function (btn) {
      btn.classList.toggle('is-active', btn.getAttribute('data-tab') === tab);
    });
    root.querySelectorAll('.ai-agent-tab-panel').forEach(function (panel) {
      panel.classList.toggle('hidden', panel.id !== 'ai-agent-panel-' + tab);
    });
    var hintEl = document.getElementById('ai-agent-tab-hint');
    if (hintEl && TAB_HINTS[tab]) {
      hintEl.innerHTML = TAB_HINTS[tab];
    }
    if (tab === 'centru') {
      loadCommandCenter();
    } else if (tab === 'monitor') {
      refreshLiveView(true);
      startLivePolling();
    } else if (tab === 'supervizor') {
      document.dispatchEvent(new CustomEvent('ai-agent-tab-supervizor'));
    } else if (tab === 'biblioteca') {
      markInstalledTemplates();
    } else if (tab === 'ollama') {
      document.dispatchEvent(new CustomEvent('ai-agent-tab-ollama'));
    }
    if (tab !== 'monitor') stopLivePolling();
    try {
      var hashMap = { centru: '', supervizor: 'supervizor', monitor: 'live', biblioteca: 'biblioteca', agenti: 'agenti', editor: 'editor', roboti: 'roboti', ollama: 'ollama' };
      var h = hashMap[tab];
      if (h !== undefined && window.history && window.history.replaceState) {
        var base = window.location.pathname + window.location.search;
        window.history.replaceState(null, '', h ? base + '#' + h : base);
      }
    } catch (ignore) { /* noop */ }
    var panel = document.getElementById('ai-agent-panel-' + tab);
    if (opts && opts.scroll && panel && panel.scrollIntoView) {
      panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  }

  function isMonitorTabActive() {
    return activeTab === 'monitor';
  }

  function updateLiveStatus(text, syncing) {
    var statusEl = document.getElementById('ai-agent-live-status');
    if (!statusEl) return;
    statusEl.textContent = text;
    statusEl.classList.toggle('is-syncing', !!syncing);
  }

  function startLivePolling() {
    stopLivePolling();
    livePollTimer = setInterval(function () {
      if (document.hidden || !isMonitorTabActive()) return;
      refreshLiveView(true);
    }, livePollSec * 1000);
  }

  function stopLivePolling() {
    if (livePollTimer) {
      clearInterval(livePollTimer);
      livePollTimer = null;
    }
  }

  function markInstalledTemplates() {
    if (!tplTbody) return;
    var slugs = {};
    agents.forEach(function (a) { slugs[a.slug] = true; });
    tplTbody.querySelectorAll('.ai-agent-tpl-row').forEach(function (row) {
      var tid = row.getAttribute('data-template-id') || '';
      var installed = !!slugs[tid];
      var stateEl = row.querySelector('[data-tpl-state="' + tid + '"]');
      var btn = row.querySelector('.ai-agent-tpl-create');
      if (stateEl) {
        stateEl.textContent = installed ? '✓ Adăugat' : 'Neinstalat';
        stateEl.className = 'ai-tpl-state ' + (installed ? 'ai-tpl-state--ok' : 'ai-tpl-state--new');
      }
      if (btn) {
        if (installed) {
          btn.textContent = 'Deschide';
          btn.classList.remove('ai-btn--primary');
          btn.classList.add('ai-btn--outline');
          btn.setAttribute('data-action', 'open-agent');
        } else {
          btn.textContent = 'Adaugă';
          btn.classList.add('ai-btn--primary');
          btn.classList.remove('ai-btn--outline');
          btn.removeAttribute('data-action');
        }
      }
    });
  }

  function filterTemplateRows() {
    if (!tplTbody) return;
    var q = searchQuery.trim().toLowerCase();
    var visible = 0;
    tplTbody.querySelectorAll('.ai-agent-tpl-row').forEach(function (row) {
      var cat = row.getAttribute('data-category') || '';
      var blob = row.getAttribute('data-search') || '';
      var show = (activeCategory === 'all' || cat === activeCategory) && (q === '' || blob.indexOf(q) !== -1);
      row.style.display = show ? '' : 'none';
      if (show) visible++;
    });
    if (tplEmpty) tplEmpty.classList.toggle('hidden', visible > 0);
    markInstalledTemplates();
  }

  function renderAgentList() {
    if (!listEl) return;
    listEl.innerHTML = agents.length ? agents.map(function (a) {
      var h = a.health || {};
      var isLive = liveRoutedSlug && a.slug === liveRoutedSlug;
      var isSelected = a.slug === selectedSlug;
      return '<li class="ai-agent-list-item' + (isSelected ? ' is-active' : '') + (isLive ? ' is-live-routed' : '') + '" data-slug="' + escapeHtml(a.slug) + '">'
        + '<div class="flex items-start justify-between gap-2"><strong>' + escapeHtml(a.name) + '</strong>'
        + '<span class="ai-health-badge ' + healthClass(h.label) + '">' + (h.score != null ? h.score : '—') + '%</span></div>'
        + '<div class="text-xs opacity-70 mt-1">' + escapeHtml(a.slug)
        + (isLive ? ' · <span class="text-red-600 font-semibold">ACTIV automat</span>' : '')
        + '</div></li>';
    }).join('') : '<li class="opacity-60">Niciun agent adăugat — mergi la Bibliotecă și apasă Adaugă.</li>';
  }

  function renderHealthBox(agent) {
    if (!healthBox || !agent) return;
    var h = agent.health || {};
    if (h.score == null) { healthBox.classList.add('hidden'); return; }
    healthBox.className = 'ai-agent-health-box mt-4 ' + healthClass(h.label);
    healthBox.classList.remove('hidden');
    var kindNote = h.kind === 'system' ? (healthLegend.system || 'Agent sistem') : (healthLegend.specialist || '');
    var issuesHtml = (h.issues && h.issues.length)
      ? '<ul class="ai-health-issues">' + h.issues.map(function (i) { return '<li>' + escapeHtml(i) + '</li>'; }).join('') + '</ul>'
      : '';
    var fixesHtml = (h.fixes && h.fixes.length)
      ? '<p class="ai-health-fixes"><strong>Cum ridici sănătatea:</strong> ' + h.fixes.map(escapeHtml).join(' · ') + '</p>'
      : '';
    healthBox.innerHTML = '<strong>Sănătate ' + h.score + '%</strong>'
      + (kindNote ? '<span class="ai-health-kind"> — ' + escapeHtml(kindNote) + '</span>' : '')
      + issuesHtml + fixesHtml;
    var kpi = document.getElementById('ai-agent-kpi-health');
    if (kpi) kpi.textContent = h.score + '%';
  }

  function agentOrgInfo(slug) {
    return agentSections[slug] || null;
  }

  function parseLearnedLines(raw) {
    var out = [];
    var seen = {};
    if (!raw || raw === '—') return out;
    String(raw).split('\n').forEach(function (line) {
      line = String(line || '').trim();
      if (line.length < 8) return;
      if (/^#\s*context\s+inv[aă]tat/i.test(line)) return;
      if (/^##\s/.test(line)) return;
      if (line.startsWith('- ')) line = line.slice(2).trim();
      if (line.length < 8 || seen[line]) return;
      seen[line] = true;
      out.push(line);
    });
    return out;
  }

  function updateLearnedSelectionCount() {
    var bulkBtn = document.getElementById('ai-agent-learned-add-selected');
    if (!bulkBtn) return;
    var n = document.querySelectorAll('.ai-learned-pick__check:checked').length;
    bulkBtn.disabled = n === 0;
    bulkBtn.textContent = n > 0 ? ('Adaugă selectate (' + n + ')') : 'Adaugă selectate';
  }

  function updateLibrarySelectionCount() {
    var bulkBtn = document.getElementById('ai-agent-library-promote-selected');
    if (!bulkBtn) return;
    var n = document.querySelectorAll('.ai-context-library__check:checked:not(:disabled)').length;
    bulkBtn.disabled = n === 0;
    bulkBtn.textContent = n > 0 ? ('Promovează selectate ca operator (' + n + ')') : 'Promovează selectate ca operator';
  }

  function renderLearnedJournalLines(raw) {
    var listEl = document.getElementById('ai-agent-learned-lines');
    if (!listEl) return;
    learnedLinesCache = parseLearnedLines(raw);
    if (!learnedLinesCache.length) {
      listEl.innerHTML = '<li class="ai-learned-pick__empty">Jurnalul nu are linii extractibile — scrie manual mai jos sau rulează agentul.</li>';
      updateLearnedSelectionCount();
      return;
    }
    listEl.innerHTML = learnedLinesCache.map(function (text, idx) {
      return '<li class="ai-learned-pick__item">'
        + '<label class="ai-learned-pick__label-wrap">'
        + '<input type="checkbox" class="ai-learned-pick__check" data-learned-idx="' + idx + '">'
        + '<span class="ai-learned-pick__text">' + escapeHtml(text) + '</span>'
        + '</label>'
        + '<button type="button" class="ai-btn ai-btn--ghost ai-btn--xs" data-learned-add="' + idx + '">Adaugă</button>'
        + '</li>';
    }).join('');
    updateLearnedSelectionCount();
  }

  function getLibraryEntryById(id) {
    return libraryEntriesCache.find(function (e) { return String(e.id) === String(id); }) || null;
  }

  function refreshLibraryFromResponse(data) {
    renderContextLibrary({
      summary: (data || {}).summary,
      entries: (data || {}).entries,
    });
  }

  function renderContextLibrary(lib) {
    var listEl = document.getElementById('ai-agent-library-list');
    var summaryEl = document.getElementById('ai-agent-library-summary');
    if (!listEl) return;

    lib = lib || {};
    var summary = lib.summary || {};
    var entries = lib.entries || [];
    libraryEntriesCache = entries;

    if (summaryEl) {
      summaryEl.textContent = (summary.total || 0) + ' fragmente · '
        + (summary.operator || 0) + ' operator · '
        + (summary.learned || 0) + ' auto';
    }

    if (!entries.length) {
      listEl.innerHTML = '<li class="ai-context-library__empty">Biblioteca e goală — apasă <strong>Import din jurnal vechi</strong> sau adaugă un fragment manual.</li>';
      updateLibrarySelectionCount();
      return;
    }

    listEl.innerHTML = entries.map(function (e) {
      var when = e.at ? new Date(e.at).toLocaleString('ro-RO', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' }) : '';
      var tags = (e.tags || []).map(function (t) { return '<span class="ai-context-library__tag">' + escapeHtml(t) + '</span>'; }).join('');
      var score = e.rag_score != null ? ' · RAG ' + e.rag_score : '';
      var src = String(e.source || 'learned');
      var isOperator = src === 'operator' || src === 'manual';
      var pinned = !!e.pinned;
      var id = escapeHtml(e.id || '');
      return '<li class="ai-context-library__item ai-context-library__item--' + escapeHtml(src)
        + (pinned ? ' ai-context-library__item--pinned' : '') + '" data-library-id="' + id + '">'
        + '<div class="ai-context-library__item-row">'
        + '<input type="checkbox" class="ai-context-library__check" data-library-id="' + id + '"' + (isOperator ? ' disabled title="Deja operator"' : '') + '>'
        + '<div class="ai-context-library__item-body">'
        + '<div class="ai-context-library__item-head"><span class="ai-context-library__src">' + escapeHtml(src)
        + (pinned ? ' · pin' : '') + score + '</span>'
        + '<time>' + escapeHtml(when) + '</time></div>'
        + '<p class="ai-context-library__text">' + escapeHtml(e.text || '') + '</p>'
        + (tags ? '<div class="ai-context-library__tags">' + tags + '</div>' : '')
        + '</div>'
        + '<div class="ai-context-library__actions">'
        + '<button type="button" class="ai-btn ai-btn--ghost ai-btn--xs" data-library-use="' + id + '">Folosește</button>'
        + (isOperator ? '' : '<button type="button" class="ai-btn ai-btn--primary ai-btn--xs" data-library-promote="' + id + '">+ Operator</button>')
        + '<button type="button" class="ai-btn ai-btn--ghost ai-btn--xs ai-btn--danger" data-library-delete="' + id + '">Șterge</button>'
        + '</div></div></li>';
    }).join('');
    updateLibrarySelectionCount();
  }

  function loadContextLibrary(slug, query) {
    if (!slug) return Promise.resolve();
    var params = { action: 'agent_context_library', slug: slug };
    if (query) params.q = query;
    return apiGet(params).then(function (json) {
      renderContextLibrary(json.data || {});
    }).catch(function () {
      renderContextLibrary({ summary: {}, entries: [] });
    });
  }

  function renderAgentDetail(agent) {
    if (!detailEl || !agent) return;
    var h = agent.health || {};
    var org = agentOrgInfo(agent.slug);
    var lastRun = agent.state && agent.state.last_run_at
      ? new Date(agent.state.last_run_at).toLocaleString('ro-RO') : 'Niciodată';
    var isLive = liveRoutedSlug && agent.slug === liveRoutedSlug;
    var isSystem = !!agent.system_agent || h.kind === 'system' || (org && org.system);

    var issuesBlock = (h.issues && h.issues.length)
      ? '<div class="ai-detail-health"><strong>De ce ' + (h.score != null ? h.score : '—') + '%?</strong><ul>'
        + h.issues.map(function (i) { return '<li>' + escapeHtml(i) + '</li>'; }).join('') + '</ul>'
        + (h.fixes && h.fixes.length ? '<p class="ai-detail-fixes">' + h.fixes.map(escapeHtml).join(' · ') + '</p>' : '')
        + '</div>'
      : '';

    var orgBlock = org
      ? '<div class="ai-detail-org"><strong>Secțiune dedicată:</strong> ' + escapeHtml(org.label) + '<br>'
        + '<strong>Zonă:</strong> ' + escapeHtml(org.zone) + '<br>'
        + '<strong>Când rulează:</strong> ' + escapeHtml(org.trigger)
        + (org.live ? ' · <span class="text-emerald-700">participă la Live</span>' : ' · <span class="text-slate-600">NU pe Live (doar Supervizor/manual)</span>')
        + '<br><strong>Fișiere:</strong> <code>robot/data/ai_agents/' + escapeHtml(agent.slug) + '/</code>'
        + ' + <code>context.library.jsonl</code> (RAG)</div>'
      : '<div class="ai-detail-org"><strong>Fișiere:</strong> <code>robot/data/ai_agents/' + escapeHtml(agent.slug) + '/</code>'
        + ' — agent.mdc, context.manual.md, context.learned.mdc, <strong>context.library.jsonl</strong> (RAG)</div>';

    var lib = agent.context_library || {};
    var libSum = lib.summary || {};
    var libBlock = (libSum.total != null && libSum.total > 0)
      ? '<div class="ai-detail-library"><strong>Bibliotecă context:</strong> ' + libSum.total + ' fragmente RAG'
        + ' (' + (libSum.operator || 0) + ' operator, ' + (libSum.learned || 0) + ' auto) · <code>' + escapeHtml(libSum.rag_file || '') + '</code></div>'
      : '<div class="ai-detail-library"><strong>Bibliotecă context:</strong> goală — Editor → Import din jurnal vechi sau adaugă fragment.</div>';

    detailEl.innerHTML = '<p><strong>' + escapeHtml(agent.name) + '</strong> · <code>' + escapeHtml(agent.slug) + '</code>'
      + (isSystem ? ' · <span class="ai-badge-system">Agent sistem</span>' : '')
      + (isLive ? ' · <span class="text-red-600 font-semibold">ACTIV pe Live acum</span>' : '') + '</p>'
      + '<p class="mt-2">Sănătate <span class="ai-health-badge ' + healthClass(h.label) + '">'
      + (h.score != null ? h.score + '%' : '—') + '</span> · Ultima rulare: ' + escapeHtml(lastRun) + '</p>'
      + '<p class="text-sm opacity-80 mt-2">' + escapeHtml(agent.description || 'Specialist AI — prompt + context pe server.') + '</p>'
      + orgBlock
      + libBlock
      + issuesBlock
      + '<div class="ai-detail-legend mt-3 text-sm opacity-90">'
      + '<p><strong>● Live</strong> = flux automat (~25s): site + admin trimit evenimente → router alege agentul potrivit (ex. catalog-stoc la căutări).</p>'
      + '<p><strong>Composer widget</strong> (buton violet) = asistent admin pe pagină — separat de agenții de mai sus.</p>'
      + (isSystem ? '<p><strong>Ops Composer Repair:</strong> repară alerte (import, TecDoc) — rulează din Supervizor, nu din Live client.</p>' : '')
      + '</div>'
      + '<div class="flex flex-wrap gap-2 mt-4">'
      + '<button type="button" class="ai-btn ai-btn--primary ai-btn--sm" data-action="goto-editor">Editează</button>'
      + (isSystem
        ? '<button type="button" class="ai-btn ai-btn--ghost ai-btn--sm" data-action="goto-supervisor">Supervizor</button>'
        : '<button type="button" class="ai-btn ai-btn--live ai-btn--sm" data-action="goto-live">● Runtime Live</button>'
        + '<button type="button" class="ai-btn ai-btn--ghost ai-btn--sm" data-action="run-runtime">▶ Generează runtime</button>')
      + '</div>';
  }

  function refreshLiveView(silent) {
    if (livePollBusy) return Promise.resolve();
    livePollBusy = true;
    if (!silent) updateLiveStatus('Se încarcă…', true);
    var nameEl = document.getElementById('ai-agent-live-agent-name');
    return apiGet({ action: 'agent_runtime', slug: 'auto', auto: '0', admin_section: 'ai-agent' }).then(function (json) {
      var d = json.data || {};
      var route = d.route || {};
      liveRoutedSlug = route.slug || d.slug || '';
      lastLiveRefresh = Date.now();
      if (nameEl) {
        nameEl.textContent = (route.name || d.name || '—')
          + (route.slug && route.slug !== 'context-master' ? ' + Context Master' : '');
      }
      var routeEl = document.getElementById('ai-agent-live-route');
      if (routeEl) {
        routeEl.textContent = route.reason || 'Rutare automată după secțiune și acțiuni client.';
        routeEl.classList.remove('hidden');
      }
      renderLiveDashboard(d);
      renderAutonomyPanel(d.autonomy);
      if (window.BesoiuOpsAlerts && d.ops_alerts) {
        window.BesoiuOpsAlerts.apply(d.ops_alerts);
      }
      renderAgentList();
      if (activeTab === 'centru') {
        var routedAgent = agents.find(function (a) { return a.slug === liveRoutedSlug; });
        setText('ai-cmd-kpi-agent', routedAgent ? routedAgent.name : (liveRoutedSlug || 'context-master'));
      }
      var md = d.runtime_markdown || '';
      if (previewShort) previewShort.textContent = md || '(Se generează automat…)';
      if (preview) preview.textContent = md || '—';
      var kpi = document.getElementById('ai-agent-kpi-size');
      if (kpi) kpi.textContent = md.length ? md.length.toLocaleString('ro-RO') + ' car.' : '—';
      var syncNote = (d.auto_sync && d.auto_sync.ran) ? ' · context recalculat' : '';
      updateLiveStatus('🟢 Live automat · ' + new Date().toLocaleTimeString('ro-RO') + syncNote
        + (d.last_run_at ? ' · rulare ' + new Date(d.last_run_at).toLocaleString('ro-RO') : ''), false);
      if (d.auto_sync && d.auto_sync.ran && !silent) {
        showToast('Context actualizat automat.');
      }
    }).catch(function (err) {
      updateLiveStatus('Eroare: ' + err.message, false);
      if (!silent) showToast(err.message, true);
    }).finally(function () {
      livePollBusy = false;
    });
  }

  function openLiveTab() {
    switchTab('monitor');
  }

  function fillEditor(agent) {
    if (!agent) return;
    var t = Number(agent.temperature != null ? agent.temperature : 1.0);
    var set = function (id, val) { var el = document.getElementById(id); if (el) el.textContent = val; };
    var setVal = function (id, val) { var el = document.getElementById(id); if (el) el.value = val; };
    set('ai-agent-editor-title', agent.name || agent.slug);
    setVal('ai-agent-field-name', agent.name || '');
    setVal('ai-agent-field-slug', agent.slug || '');
    setVal('ai-agent-field-desc', agent.description || '');
    setVal('ai-agent-field-category', agent.category || '');
    setVal('ai-agent-field-prompt', agent.prompt || '');
    setVal('ai-agent-field-manual', agent.manual_context || '');
    var learned = document.getElementById('ai-agent-field-learned');
    if (learned) learned.textContent = agent.learned_context || '—';
    renderLearnedJournalLines(agent.learned_context || '');
    renderContextLibrary(agent.context_library || {});
    var ac = document.getElementById('ai-agent-field-auto-collect');
    var ae = document.getElementById('ai-agent-field-auto-evolve');
    if (ac) ac.checked = !!agent.auto_collect;
    if (ae) ae.checked = !!agent.auto_evolve;
    if (tempRange) { tempRange.value = String(t); if (tempLabel) tempLabel.textContent = t.toFixed(2); }
    var del = document.getElementById('ai-agent-delete');
    if (del) del.style.display = agent.slug === 'context-master' ? 'none' : '';
    renderHealthBox(agent);
    renderAgentDetail(agent);
  }

  function selectAgent(slug) {
    selectedSlug = slug;
    renderAgentList();
    return apiGet({ action: 'agent', slug: slug }).then(function (json) {
      fillEditor(json.data);
      if (isMonitorTabActive()) refreshLiveView(true);
    });
  }

  function eventRowHtml(e) {
    var f = formatEventHuman(e);
    return '<li class="ai-agent-event-row ai-agent-event-row--' + escapeHtml(f.actor) + '">'
      + '<span class="ai-agent-event-row__icon">' + f.icon + '</span>'
      + '<span class="ai-agent-event-row__body">' + escapeHtml(f.label) + '</span>'
      + (f.when ? '<span class="ai-agent-event-row__when">' + escapeHtml(f.when) + '</span>' : '')
      + '</li>';
  }

  function renderEventsList(events, totalCount) {
    var evEl = document.getElementById('ai-agent-events');
    var moreEl = document.getElementById('ai-agent-events-more');
    if (!evEl) return;

    var list = (events || []).slice();
    cachedLiveEvents = list;
    var total = totalCount != null && totalCount > 0 ? totalCount : list.length;

    if (!list.length) {
      evEl.innerHTML = '<li class="opacity-60">Niciun eveniment — acțiunile din admin și căutările pe site apar aici.</li>';
      if (moreEl) { moreEl.classList.add('hidden'); moreEl.textContent = ''; }
      return;
    }

    var visible = list.slice(0, LIVE_EVENTS_SHOW);
    evEl.innerHTML = visible.map(eventRowHtml).join('');

    if (moreEl) {
      var hiddenOnScreen = Math.max(0, total - LIVE_EVENTS_SHOW);
      if (hiddenOnScreen > 0) {
        moreEl.textContent = 'Încă ' + hiddenOnScreen + ' acțiuni ascunse · Export tot istoricul descarcă fișierul CSV complet.';
        moreEl.classList.remove('hidden');
      } else {
        moreEl.classList.add('hidden');
        moreEl.textContent = '';
      }
    }
  }

  function exportEventsToFile() {
    var btn = document.getElementById('ai-agent-events-export');
    if (btn) { btn.disabled = true; btn.textContent = 'Se exportă…'; }
    return apiGet({ action: 'events', limit: 800 }).then(function (json) {
      var events = (json.data || []).slice().reverse();
      if (!events.length) {
        showToast('Niciun eveniment de exportat.', true);
        return;
      }

      var stamp = new Date().toISOString().slice(0, 10);
      var csvLines = ['Data;Actor;Actiune;Subiect'];
      events.forEach(function (e) {
        var f = formatEventHuman(e);
        var when = e.at ? new Date(e.at).toLocaleString('ro-RO') : '';
        var row = [when, f.actor, f.label, (e.subject || '')].map(function (cell) {
          return '"' + String(cell == null ? '' : cell).replace(/"/g, '""') + '"';
        });
        csvLines.push(row.join(';'));
      });

      var blob = new Blob(['\ufeff' + csvLines.join('\r\n')], { type: 'text/csv;charset=utf-8' });
      var a = document.createElement('a');
      a.href = URL.createObjectURL(blob);
      a.download = 'besoiu-actiuni-agent-' + stamp + '.csv';
      a.click();
      URL.revokeObjectURL(a.href);
      showToast('Export: ' + events.length + ' evenimente.');
    }).catch(function (err) {
      showToast(err.message, true);
    }).finally(function () {
      if (btn) { btn.disabled = false; btn.textContent = 'Export tot istoricul'; }
    });
  }

  function formatEventHuman(e) {
    var actor = e.actor_type || 'system';
    var action = e.action || '';
    var sub = e.subject || '';
    var icon = { admin: '🛠️', client: '👤', robot: '🤖', cron: '⏱️', system: '⚙️' }[actor] || '•';
    var label = action;
    if (action === 'agent_full_cycle') label = 'Context salvat pentru roboți';
    else if (action === 'page_view') label = 'Pagină: ' + sub;
    else if (action === 'scroll') label = 'Scroll ' + sub;
    else if (action === 'product_view') label = 'Vede produs: ' + sub;
    else if (action === 'product_click') label = 'Click produs: ' + sub;
    else if (action === 'add_to_cart') label = 'Adaugă în coș: ' + sub;
    else if (action === 'cart_view') label = 'Coș: ' + sub;
    else if (action === 'click') label = sub || 'Click';
    else if (action === 'time_on_page') label = sub || 'Timp pe pagină';
    else if (action === 'checkout_step') label = 'Checkout: ' + sub;
    else if (action === 'idle_return') label = 'Revine pe site: ' + sub;
    else if (action === 'search') label = sub ? 'Căutare: «' + sub + '»' : 'Căutare client';
    else if (action === 'search_resolved') label = 'OEM rezolvat: «' + sub + '»';
    else if (action === 'cart_abandon') label = 'Coș abandonat' + (sub ? ' — ' + sub : '');
    else if (action === 'ai_api_error') label = '⚠ API AI: ' + sub;
    else if (sub) label = action + ' — ' + sub;
    var when = e.at ? new Date(e.at).toLocaleString('ro-RO', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' }) : '';
    return { icon: icon, label: label, when: when, actor: actor };
  }

  function renderAutonomyPanel(autonomy) {
    var box = document.getElementById('ai-agent-autonomy-list');
    var archiveBox = document.getElementById('ai-agent-archive-list');
    var badge = document.getElementById('ai-agent-autonomy-status');
    if (!box) return;
    var data = autonomy || {};
    var archive = data.archive || {};
    var last = data.last_cycle || {};
    var open = data.proposals_open || [];
    if (badge) {
      var badgeParts = [];
      if (last.ran_at) {
        badgeParts.push('Ciclu: ' + new Date(last.ran_at).toLocaleString('ro-RO'));
      }
      if (archive.last_archive_at) {
        badgeParts.push('Arhivă: ' + new Date(archive.last_archive_at).toLocaleString('ro-RO'));
      }
      badge.textContent = badgeParts.length
        ? badgeParts.join(' · ')
        : 'Cron neconfigurat — rulează run_ai_agent_cycle.bat';
      badge.classList.toggle('is-ok', !!last.ran_at);
    }
    if (archiveBox) {
      var recent = archive.recent_archives || [];
      var nextLbl = archive.next_archive_hour ? 'Următoarea arhivă: ' + archive.next_archive_hour : '';
      var lastLbl = archive.last_archive_path
        ? 'Ultima: <code>' + escapeHtml(archive.last_archive_path) + '</code>'
        : 'Nicio arhivă încă — la schimbarea orei cron salvează tot.';
      var recentHtml = recent.length
        ? recent.slice(0, 5).map(function (a) {
          var when = a.archived_at ? new Date(a.archived_at).toLocaleString('ro-RO', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' }) : '—';
          return '<li><span>' + when + '</span> · ' + (a.event_count || 0) + ' ev. · <code>' + escapeHtml(a.path || '') + '</code></li>';
        }).join('')
        : '';
      archiveBox.innerHTML = '<li class="ai-agent-archive__meta">' + lastLbl + (nextLbl ? ' · ' + escapeHtml(nextLbl) : '') + '</li>'
        + recentHtml;
    }
    if (!open.length) {
      box.innerHTML = '<li class="opacity-60">Nicio propunere deschisă — sistemul monitorizează.</li>';
      return;
    }
    box.innerHTML = open.slice(0, 6).map(function (p) {
      var pri = p.priority || 'medium';
      return '<li class="ai-agent-autonomy__item ai-agent-autonomy__item--' + escapeHtml(pri) + '">'
        + '<strong>' + escapeHtml(p.title || 'Propunere') + '</strong>'
        + '<p>' + escapeHtml(p.detail || '') + '</p></li>';
    }).join('');
  }

  function renderLiveOrchestra(data) {
    var grid = document.getElementById('ai-agent-orchestra-grid');
    var meta = document.getElementById('ai-agent-orchestra-meta');
    if (!grid) return;

    var orchestra = (data && data.orchestra) || [];
    var route = (data && data.route) || {};
    var installed = orchestra.filter(function (a) { return a.installed; });
    var active = orchestra.filter(function (a) { return a.status === 'primary' || a.status === 'sync'; });

    if (meta) {
      meta.textContent = installed.length + ' instalați · '
        + active.length + ' activi acum · șef: ' + (route.name || data.name || '—');
    }

    var statusLabel = {
      primary: 'ȘEF acum',
      sync: 'Sincronizat',
      warm: 'Pregătit',
      idle: 'Standby',
      missing: 'Lipsește'
    };

    if (!orchestra.length) {
      grid.innerHTML = '<p class="ai-live-orchestra__empty">Niciun agent — adaugă din tab Șabloane.</p>';
      return;
    }

    grid.innerHTML = orchestra.map(function (a) {
      var temp = a.temperature != null ? Math.round(Number(a.temperature) * 100) + '%' : '—';
      var tempStrict = a.temperature != null && Number(a.temperature) <= 0.4;
      var st = a.status || 'idle';
      var lastRun = a.last_run_at ? new Date(a.last_run_at).toLocaleString('ro-RO', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' }) : '—';
      var files = a.files_path || ('robot/data/ai_agents/' + a.slug + '/');
      var actionBtn = a.installed
        ? '<button type="button" class="ai-live-orchestra__btn" data-orchestra-slug="' + escapeHtml(a.slug) + '">Editor</button>'
        : '<button type="button" class="ai-live-orchestra__btn ai-live-orchestra__btn--add" data-orchestra-add="' + escapeHtml(a.slug) + '">Adaugă</button>';

      return '<article class="ai-live-orchestra__card ai-live-orchestra__card--' + escapeHtml(st) + '" data-slug="' + escapeHtml(a.slug) + '">'
        + '<div class="ai-live-orchestra__card-head">'
        + '<strong>' + escapeHtml(a.name || a.slug) + '</strong>'
        + '<span class="ai-live-orchestra__pill ai-live-orchestra__pill--' + escapeHtml(st) + '">' + escapeHtml(statusLabel[st] || st) + '</span>'
        + '</div>'
        + '<p class="ai-live-orchestra__zone">' + escapeHtml(a.label || a.zone || '') + '</p>'
        + '<dl class="ai-live-orchestra__stats">'
        + '<div><dt>Creativitate</dt><dd class="' + (tempStrict ? 'is-strict' : '') + '">' + temp + '</dd></div>'
        + '<div><dt>Scor Live</dt><dd>' + escapeHtml(String(a.score != null ? a.score : 0)) + '</dd></div>'
        + '<div><dt>Runtime</dt><dd>' + (a.runtime_chars ? (Number(a.runtime_chars).toLocaleString('ro-RO') + ' car.') : '—') + '</dd></div>'
        + '<div><dt>Ultima rulare</dt><dd>' + escapeHtml(lastRun) + '</dd></div>'
        + '</dl>'
        + '<code class="ai-live-orchestra__path">' + escapeHtml(files) + '</code>'
        + '<p class="ai-live-orchestra__trigger">' + escapeHtml(a.trigger || '') + '</p>'
        + actionBtn
        + '</article>';
    }).join('');
  }

  function renderLiveDashboard(data) {
    var core = (data && data.core) || {};
    var events = (data && data.events) || [];
    var sources = (data && data.sources) || {};

    var tempEl = document.getElementById('ai-agent-live-temp');
    if (tempEl) {
      var orchestra = (data && data.orchestra) || [];
      var primary = orchestra.find(function (a) { return a.status === 'primary'; });
      var temp = primary && primary.temperature != null ? Number(primary.temperature) : (data && data.temperature != null ? Number(data.temperature) : null);
      tempEl.textContent = primary
        ? ('Șef: ' + (primary.name || primary.slug) + ' · ' + Math.round(temp * 100) + '%')
        : (temp != null ? 'Creativitate ' + Math.round(temp * 100) + '%' : 'Orchestră Live');
      tempEl.classList.toggle('is-max', temp != null && temp >= 0.95);
      tempEl.classList.toggle('is-strict', temp != null && temp <= 0.4);
    }

    renderLiveOrchestra(data);

    var kpisEl = document.getElementById('ai-agent-live-kpis');
    if (kpisEl) {
      var actors = core.actors || {};
      var patterns = core.patterns || {};
      var notFound = (patterns.searches_not_found || []).length;
      var carts = Number(patterns.open_cart_abandonments || 0);
      kpisEl.innerHTML = [
        { label: 'Evenimente', value: core.event_count || 0, tone: 'neutral' },
        { label: 'Admin', value: actors.admin || 0, tone: 'admin' },
        { label: 'Clienți', value: actors.client || 0, tone: 'client' },
        { label: 'Fără stoc', value: notFound, tone: notFound ? 'alert' : 'ok' },
        { label: 'Coșuri', value: carts, tone: carts ? 'warn' : 'ok' }
      ].map(function (k) {
        return '<div class="ai-agent-live-kpi ai-agent-live-kpi--' + k.tone + '">'
          + '<span class="ai-agent-live-kpi__val">' + k.value + '</span>'
          + '<span class="ai-agent-live-kpi__lbl">' + escapeHtml(k.label) + '</span></div>';
      }).join('');
    }

    var summaryEl = document.getElementById('ai-agent-live-summary');
    if (summaryEl) {
      var dirs = core.robot_directives || [];
      var highlights = core.highlights || [];
      var html = '';
      if (dirs.length) {
        html += '<div class="ai-agent-live-block"><p class="ai-agent-live-block__title">Ce face agentul pentru roboți</p><ul class="ai-agent-live-list">'
          + dirs.map(function (d) { return '<li>' + escapeHtml(d) + '</li>'; }).join('') + '</ul></div>';
      }
      if (highlights.length) {
        html += '<div class="ai-agent-live-block"><p class="ai-agent-live-block__title">Ultimele semnale importante</p><ul class="ai-agent-live-list ai-agent-live-list--highlights">'
          + highlights.slice(0, 5).map(function (h) {
            return '<li>' + escapeHtml(h.text || '') + '</li>';
          }).join('') + '</ul></div>';
      }
      if (!html) {
        html = '<p class="opacity-60 text-sm">Agentul colectează automat — datele apar aici în câteva secunde.</p>';
      }
      summaryEl.innerHTML = html;
    }

    var srcEl = document.getElementById('ai-agent-live-sources');
    if (srcEl) {
      var srcList = Object.keys(sources).map(function (k) { return sources[k]; }).filter(Boolean);
      srcEl.innerHTML = srcList.length
        ? srcList.map(function (s) {
          var ok = !!s.ok;
          return '<div class="ai-agent-live-source' + (ok ? ' is-ok' : ' is-warn') + '">'
            + '<span class="ai-agent-live-source__dot">' + (ok ? '✓' : '!') + '</span>'
            + '<div><strong>' + escapeHtml(s.label || 'Sursă') + '</strong>'
            + '<p>' + escapeHtml(s.summary || '—') + '</p></div></div>';
        }).join('')
        : '<p class="text-xs opacity-60">Sursele se populează automat la prima sincronizare.</p>';
    }

    var evEl = document.getElementById('ai-agent-events');
    if (evEl) {
      renderEventsList((events || []).slice().reverse(), core.event_count || 0);
    }
  }

  function renderCore(core, events) {
    renderLiveDashboard({ core: core, events: events, sources: {}, temperature: null });
  }

  function createFromTemplate(templateId, btn) {
    if (!templateId) { showToast('Template invalid.', true); return Promise.resolve(); }
    if (btn && btn.getAttribute('data-action') === 'open-agent') {
      selectedSlug = templateId;
      switchTab('editor');
      return selectAgent(templateId);
    }
    if (btn) { btn.disabled = true; btn.textContent = 'Se adaugă…'; }
    return apiPost({ action: 'agent_create_from_template', template_id: templateId })
      .then(function (json) {
        selectedSlug = json.data.slug;
        showToast('Agent adăugat: ' + json.data.name + '. Următorul pas: Editor (opțional) → Roboți.');
        switchTab('agenti');
        return loadPage(false).then(function () {
          markInstalledTemplates();
          return selectAgent(selectedSlug);
        });
      })
      .catch(function (e) { showToast(e.message, true); })
      .finally(function () {
        if (btn) {
          btn.disabled = false;
          if (btn.getAttribute('data-action') !== 'open-agent') {
            btn.textContent = 'Adaugă';
          }
        }
      });
  }

  function renderBots(bots, agentList) {
    if (!botsEl) return;
    if (!bots || !bots.length) { botsEl.innerHTML = '<p class="opacity-60">Niciun bot.</p>'; return; }
    var opts = (agentList || []).map(function (a) {
      return '<option value="' + escapeHtml(a.slug) + '">' + escapeHtml(a.name) + '</option>';
    }).join('');
    botsEl.innerHTML = '<table class="w-full ai-agent-bots-table"><thead><tr><th>Bot</th><th>Canal</th><th>Agent</th></tr></thead><tbody>'
      + bots.map(function (b) {
        return '<tr><td>' + escapeHtml(b.name) + '</td><td>' + escapeHtml(b.channel) + '</td>'
          + '<td><select class="ai-agent-bot-select border rounded px-2 py-1 text-xs" data-rid="' + b.randomn_id + '">'
          + '<option value="">Context Master</option>' + opts + '</select></td></tr>';
      }).join('') + '</tbody></table>';
    botsEl.querySelectorAll('.ai-agent-bot-select').forEach(function (sel) {
      var bot = bots.find(function (x) { return String(x.randomn_id) === sel.getAttribute('data-rid'); });
      if (bot && bot.ai_agent_slug) sel.value = bot.ai_agent_slug;
    });
  }

  function updatePreview(md) {
    var text = md || '';
    if (preview) preview.textContent = text || 'Apasă Actualizează context.';
    if (previewShort) previewShort.textContent = text || 'Apasă Reîmprospătează live.';
    var kpi = document.getElementById('ai-agent-kpi-size');
    if (kpi) kpi.textContent = text.length ? text.length.toLocaleString('ro-RO') + ' car.' : '—';
  }

  function loadPage() {
    filterTemplateRows();
    if (window.aiMissionSetLoading) window.aiMissionSetLoading(true);
    return apiGet().then(function (statusJson) {
      lastStatusJson = statusJson;
      agents = statusJson.agents || [];
      if (!agents.some(function (a) { return a.slug === selectedSlug; }) && agents[0]) {
        selectedSlug = agents[0].slug;
      }
      var navCount = document.getElementById('ai-nav-agents-count');
      if (navCount) navCount.textContent = String(agents.length);
      renderCore(statusJson.core || {}, statusJson.events || []);
      renderAutonomyPanel(statusJson.autonomy);
      var ev = document.getElementById('ai-agent-kpi-events');
      var sync = document.getElementById('ai-agent-kpi-sync');
      if (ev) ev.textContent = (statusJson.status && statusJson.status.event_count) || 0;
      if (sync) sync.textContent = statusJson.status && statusJson.status.latest_at
        ? new Date(statusJson.status.latest_at).toLocaleString('ro-RO') : 'Niciodată';
      updatePreview(statusJson.latest && statusJson.latest.markdown ? statusJson.latest.markdown : '');
      renderAgentList();
      markInstalledTemplates();
      if (activeTab === 'centru') loadCommandCenter();
      return selectAgent(selectedSlug).catch(function () {});
    }).then(function () {
      return apiGet({ action: 'importable_mdc' }).then(function (imp) {
        if (importSelect) {
          importSelect.innerHTML = '<option value="">— fișier —</option>'
            + (imp.data || []).map(function (f) {
              return '<option value="' + escapeHtml(f.file) + '">' + escapeHtml(f.file) + '</option>';
            }).join('');
        }
      }).catch(function () {
        if (importSelect) importSelect.innerHTML = '<option value="">—</option>';
      });
    }).then(function () {
      return apiGet({ action: 'bots' }).then(function (bots) {
        renderBots(bots.data || [], agents);
      }).catch(function () {
        if (botsEl) botsEl.innerHTML = '<p class="opacity-60">Roboți indisponibili.</p>';
      });
    }).catch(function (e) {
      showToast('Eroare încărcare: ' + e.message, true);
    }).finally(function () {
      if (window.aiMissionSetLoading) window.aiMissionSetLoading(false);
    });
  }

  /* Navigare externă — capture, înainte de handler-ul de tab-uri */
  root.addEventListener('click', function (e) {
    var leaveLink = e.target.closest('a[data-nav-leave]');
    if (leaveLink) {
      var leaveHref = leaveLink.getAttribute('href') || '';
      if (leaveHref && leaveHref !== '#') {
        e.preventDefault();
        e.stopPropagation();
        navigateAway(leaveHref);
      }
      return;
    }

    var hashLink = e.target.closest('a[href*="#"]');
    if (hashLink && handleSamePageHashNav(hashLink, e)) {
      return;
    }

    var extLink = e.target.closest('a[href^="/admin/"]');
    if (extLink && !extLink.hasAttribute('data-goto-tab') && !extLink.hasAttribute('data-nav-leave')) {
      var extHref = extLink.getAttribute('href') || '';
      if (extHref && extHref.indexOf('#') === -1 && extLink.target !== '_blank') {
        return;
      }
    }
  }, true);

  /* Event delegation — funcționează imediat, fără re-bind */
  root.addEventListener('click', function (e) {
    var plainLink = e.target.closest('a[href]');
    if (plainLink && !plainLink.hasAttribute('data-goto-tab')) {
      var plainHref = String(plainLink.getAttribute('href') || '').trim();
      if (plainHref && plainHref !== '#' && plainHref.indexOf('javascript:') !== 0) {
        if (plainHref.charAt(0) === '#') {
          if (handleSamePageHashNav(plainLink, e)) return;
        } else if (plainLink.target !== '_blank' && !plainLink.hasAttribute('download')) {
          try {
            var plainUrl = new URL(plainHref, window.location.href);
            if (plainUrl.origin === window.location.origin
              && normalizeAdminPath(plainUrl.pathname) !== normalizeAdminPath(window.location.pathname)) {
              return;
            }
            if (plainUrl.origin === window.location.origin
              && normalizeAdminPath(plainUrl.pathname) === normalizeAdminPath(window.location.pathname)
              && plainUrl.hash) {
              if (handleSamePageHashNav(plainLink, e)) return;
            }
          } catch (ignorePlain) {
            return;
          }
        }
      }
    }

    var navBtn = e.target.closest('.ai-mission-nav__btn');
    if (navBtn) {
      e.preventDefault();
      switchTab(navBtn.getAttribute('data-tab') || 'centru');
      return;
    }

    var gotoAny = e.target.closest('[data-goto-tab]');
    if (gotoAny) {
      e.preventDefault();
      switchTab(gotoAny.getAttribute('data-goto-tab') || 'centru');
      return;
    }

    var tab = e.target.closest('.ai-agent-tab');
    if (tab) {
      e.preventDefault();
      switchTab(tab.getAttribute('data-tab') || 'centru');
      return;
    }

    var catBtn = e.target.closest('.ai-agent-cat-btn');
    if (catBtn && catFilters) {
      activeCategory = catBtn.getAttribute('data-cat') || 'all';
      catFilters.querySelectorAll('.ai-agent-cat-btn').forEach(function (b) {
        b.classList.toggle('is-active', b === catBtn);
      });
      filterTemplateRows();
      return;
    }

    var installBtn = e.target.closest('.ai-agent-tpl-create');
    if (installBtn) {
      e.preventDefault();
      createFromTemplate(installBtn.getAttribute('data-id'), installBtn);
      return;
    }

    var agentItem = e.target.closest('.ai-agent-list-item[data-slug]');
    if (agentItem) {
      selectAgent(agentItem.getAttribute('data-slug')).catch(function (err) { showToast(err.message, true); });
      return;
    }

    if (e.target.closest('[data-action="goto-editor"]')) { switchTab('editor'); return; }
    if (e.target.closest('[data-action="goto-live"]')) { openLiveTab(); return; }
    if (e.target.closest('[data-action="goto-supervisor"]')) { switchTab('supervizor'); return; }
    if (e.target.closest('[data-action="run-runtime"]') || e.target.closest('[data-action="goto-run"]')) {
      document.getElementById('ai-agent-run') && document.getElementById('ai-agent-run').click();
      return;
    }

    var orchSlug = e.target.closest('[data-orchestra-slug]');
    if (orchSlug) {
      e.preventDefault();
      selectedSlug = orchSlug.getAttribute('data-orchestra-slug') || '';
      switchTab('editor');
      selectAgent(selectedSlug).catch(function (err) { showToast(err.message, true); });
      return;
    }

    var orchAdd = e.target.closest('[data-orchestra-add]');
    if (orchAdd) {
      e.preventDefault();
      createFromTemplate(orchAdd.getAttribute('data-orchestra-add'), orchAdd);
      return;
    }

    if (e.target.closest('#ai-agent-library-add-btn')) {
      var addText = document.getElementById('ai-agent-library-add-text');
      var txt = addText ? String(addText.value || '').trim() : '';
      if (!selectedSlug || txt === '') {
        showToast('Scrie text fragment.', true);
        return;
      }
      apiPost({ action: 'agent_context_add', slug: selectedSlug, text: txt })
        .then(function (json) {
          if (addText) addText.value = '';
          showToast('Fragment adăugat.');
          refreshLibraryFromResponse(json.data || {});
          return selectAgent(selectedSlug);
        })
        .catch(function (err) { showToast(err.message, true); });
      return;
    }

    var learnedAddBtn = e.target.closest('[data-learned-add]');
    if (learnedAddBtn) {
      var lidx = parseInt(learnedAddBtn.getAttribute('data-learned-add') || '-1', 10);
      var ltext = learnedLinesCache[lidx];
      if (!selectedSlug || !ltext) return;
      apiPost({ action: 'agent_context_add', slug: selectedSlug, text: ltext, pinned: true, tags: ['din-jurnal'] })
        .then(function (json) {
          showToast('Linie adăugată în bibliotecă.');
          refreshLibraryFromResponse(json.data || {});
          return selectAgent(selectedSlug);
        })
        .catch(function (err) { showToast(err.message, true); });
      return;
    }

    if (e.target.closest('#ai-agent-learned-add-selected')) {
      var picked = [];
      document.querySelectorAll('.ai-learned-pick__check:checked').forEach(function (cb) {
        var idx = parseInt(cb.getAttribute('data-learned-idx') || '-1', 10);
        if (learnedLinesCache[idx]) picked.push(learnedLinesCache[idx]);
      });
      if (!selectedSlug || !picked.length) {
        showToast('Bifează cel puțin o linie din jurnal.', true);
        return;
      }
      apiPost({ action: 'agent_context_add_bulk', slug: selectedSlug, texts: picked, pinned: true })
        .then(function (json) {
          showToast(json.message || 'Fragmente adăugate.');
          document.querySelectorAll('.ai-learned-pick__check:checked').forEach(function (cb) { cb.checked = false; });
          updateLearnedSelectionCount();
          refreshLibraryFromResponse(json.data || {});
          return selectAgent(selectedSlug);
        })
        .catch(function (err) { showToast(err.message, true); });
      return;
    }

    if (e.target.classList && e.target.classList.contains('ai-learned-pick__check')) {
      updateLearnedSelectionCount();
      return;
    }

    if (e.target.classList && e.target.classList.contains('ai-context-library__check')) {
      updateLibrarySelectionCount();
      return;
    }

    var libUse = e.target.closest('[data-library-use]');
    if (libUse) {
      var useEntry = getLibraryEntryById(libUse.getAttribute('data-library-use'));
      var useField = document.getElementById('ai-agent-library-add-text');
      if (useEntry && useField) {
        useField.value = useEntry.text || '';
        useField.focus();
        showToast('Text copiat în câmpul de adăugare.');
      }
      return;
    }

    var libPromote = e.target.closest('[data-library-promote]');
    if (libPromote) {
      var promoteId = libPromote.getAttribute('data-library-promote');
      if (!selectedSlug || !promoteId) return;
      apiPost({ action: 'agent_context_promote', slug: selectedSlug, id: promoteId, pinned: true })
        .then(function (json) {
          showToast(json.message || 'Promovat.');
          refreshLibraryFromResponse(json.data || {});
        })
        .catch(function (err) { showToast(err.message, true); });
      return;
    }

    var libDelete = e.target.closest('[data-library-delete]');
    if (libDelete) {
      var deleteId = libDelete.getAttribute('data-library-delete');
      if (!selectedSlug || !deleteId) return;
      if (!window.confirm('Ștergi fragmentul din bibliotecă?')) return;
      apiPost({ action: 'agent_context_delete', slug: selectedSlug, id: deleteId })
        .then(function (json) {
          showToast(json.message || 'Șters.');
          refreshLibraryFromResponse(json.data || {});
        })
        .catch(function (err) { showToast(err.message, true); });
      return;
    }

    if (e.target.closest('#ai-agent-library-promote-selected')) {
      var promoteIds = [];
      document.querySelectorAll('.ai-context-library__check:checked:not(:disabled)').forEach(function (cb) {
        var pid = cb.getAttribute('data-library-id');
        if (pid) promoteIds.push(pid);
      });
      if (!selectedSlug || !promoteIds.length) return;
      var promoteChain = Promise.resolve();
      promoteIds.forEach(function (pid) {
        promoteChain = promoteChain.then(function () {
          return apiPost({ action: 'agent_context_promote', slug: selectedSlug, id: pid, pinned: true });
        });
      });
      promoteChain
        .then(function () {
          showToast('Selectate promovate ca operator.');
          return loadContextLibrary(selectedSlug, librarySearchEl ? String(librarySearchEl.value || '').trim() : '');
        })
        .catch(function (err) { showToast(err.message, true); });
      return;
    }

    if (e.target.closest('#ai-agent-library-migrate')) {
      if (!selectedSlug) return;
      apiPost({ action: 'agent_context_migrate', slug: selectedSlug })
        .then(function (json) {
          showToast(json.message || 'Migrare OK.');
          renderContextLibrary({ summary: (json.data || {}).summary, entries: (json.data || {}).entries });
          return selectAgent(selectedSlug);
        })
        .catch(function (err) { showToast(err.message, true); });
      return;
    }

    var actionBtn = e.target.closest('#ai-agent-live, #ai-agent-live-refresh, #ai-agent-live-run, #ai-agent-events-export, #ai-agent-new, #ai-agent-save, #ai-agent-run, #ai-agent-delete, #ai-agent-clone, #ai-agent-export, #ai-agent-import-btn, #ai-agent-copy, #ai-agent-copy-live');
    if (!actionBtn) return;
    var id = actionBtn.id;

    if (id === 'ai-agent-live' || id === 'ai-agent-live-refresh') {
      openLiveTab();
      return;
    }
    if (id === 'ai-agent-live-run') {
      updateLiveStatus('Recalculare forțată…', true);
      apiPost({ action: 'agent_run', slug: selectedSlug, auto_collect: true })
        .then(function () { showToast('Context recalculat.'); return refreshLiveView(true); })
        .catch(function (err) { showToast(err.message, true); });
      return;
    }
    if (id === 'ai-agent-events-export') {
      exportEventsToFile();
      return;
    }
    if (id === 'ai-agent-copy-live' && previewShort) {
      navigator.clipboard.writeText(previewShort.textContent || '').then(function () {
        showToast('Context copiat.');
      }).catch(function () { showToast('Nu s-a copiat.', true); });
      return;
    }

    if (id === 'ai-agent-new') {
      var name = prompt('Nume agent:', 'Agent custom');
      if (!name) return;
      var slug = name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
      apiPost({ action: 'agent_create', name: name, slug: slug, temperature: 1.0, prompt: '# ' + name })
        .then(function () { selectedSlug = slug; switchTab('editor'); return loadPage(); })
        .catch(function (err) { showToast(err.message, true); });
      return;
    }
    if (id === 'ai-agent-save') {
      if (!selectedSlug) {
        showToast('Niciun agent selectat.', true);
        return;
      }
      var saveBtn = actionBtn;
      saveBtn.disabled = true;
      apiPost({
        action: 'agent_update', slug: selectedSlug,
        name: document.getElementById('ai-agent-field-name').value,
        description: document.getElementById('ai-agent-field-desc').value,
        category: document.getElementById('ai-agent-field-category').value,
        prompt: document.getElementById('ai-agent-field-prompt').value,
        manual_context: document.getElementById('ai-agent-field-manual').value,
        temperature: Number(tempRange ? tempRange.value : 1.0),
        auto_collect: document.getElementById('ai-agent-field-auto-collect').checked,
        auto_evolve: document.getElementById('ai-agent-field-auto-evolve').checked,
      }).then(function (json) {
        var saved = json.data || {};
        fillEditor(saved);
        var idx = agents.findIndex(function (a) { return a.slug === saved.slug; });
        if (idx >= 0) {
          agents[idx] = saved;
        } else if (saved.slug) {
          agents.push(saved);
        }
        renderAgentList();
        showToast('Salvat — temperature ' + Number(saved.temperature != null ? saved.temperature : 0).toFixed(2));
      }).catch(function (err) { showToast(err.message, true); })
        .finally(function () { saveBtn.disabled = false; });
      return;
    }
    if (id === 'ai-agent-run') {
      var runBtn = actionBtn;
      runBtn.disabled = true;
      apiPost({ action: 'agent_run', slug: selectedSlug, auto_collect: true })
        .then(function (json) {
          updatePreview(json.data && json.data.markdown ? json.data.markdown : '');
          if (json.agent) fillEditor(json.agent);
          showToast('Context actualizat.');
          return loadPage();
        })
        .catch(function (err) { showToast(err.message, true); })
        .finally(function () { runBtn.disabled = false; });
      return;
    }
    if (id === 'ai-agent-delete') {
      if (!confirm('Ștergi agentul?')) return;
      apiPost({ action: 'agent_delete', slug: selectedSlug })
        .then(function () { selectedSlug = 'context-master'; showToast('Șters.'); return loadPage(); })
        .catch(function (err) { showToast(err.message, true); });
      return;
    }
    if (id === 'ai-agent-clone') {
      apiPost({ action: 'agent_clone', slug: selectedSlug })
        .then(function (json) { selectedSlug = json.data.slug; switchTab('editor'); return loadPage(); })
        .catch(function (err) { showToast(err.message, true); });
      return;
    }
    if (id === 'ai-agent-export') {
      apiPost({ action: 'agent_export_mdc', slug: selectedSlug })
        .then(function (json) {
          var blob = new Blob([json.data.content], { type: 'text/markdown' });
          var a = document.createElement('a');
          a.href = URL.createObjectURL(blob);
          a.download = json.data.filename;
          a.click();
          showToast('Export OK.');
        })
        .catch(function (err) { showToast(err.message, true); });
      return;
    }
    if (id === 'ai-agent-import-btn') {
      if (!importSelect || !importSelect.value) { showToast('Alege fișier.', true); return; }
      apiPost({ action: 'agent_import_mdc', file: importSelect.value })
        .then(function (json) { selectedSlug = json.data.slug; switchTab('editor'); return loadPage(); })
        .catch(function (err) { showToast(err.message, true); });
      return;
    }
    if (id === 'ai-agent-copy' && preview) {
      navigator.clipboard.writeText(preview.textContent || '').then(function () {
        showToast('Copiat.');
      }).catch(function () { showToast('Nu s-a copiat.', true); });
    }
  });

  root.addEventListener('change', function (e) {
    if (e.target.classList && e.target.classList.contains('ai-agent-bot-select')) {
      apiPost({
        action: 'bot_set_agent',
        randomn_id: Number(e.target.getAttribute('data-rid')),
        ai_agent_slug: e.target.value,
      }).then(function () { showToast('Bot actualizat.'); })
        .catch(function (err) { showToast(err.message, true); });
    }
  });

  if (tplSearch) {
    tplSearch.addEventListener('input', function () {
      searchQuery = tplSearch.value || '';
      filterTemplateRows();
    });
  }
  if (tempRange && tempLabel) {
    tempRange.addEventListener('input', function () {
      tempLabel.textContent = Number(tempRange.value).toFixed(2);
    });
  }

  /* Asigură click-uri — ascunde overlay loader */
  document.querySelectorAll('.page-loader').forEach(function (el) {
    el.classList.add('hidden', 'opacity-0');
    el.style.pointerEvents = 'none';
  });

  var lastBrainRuleInstalled = null;

  function appendBrainLog(html, isOk) {
    var log = document.getElementById('ai-cmd-brain-log');
    if (!log) return;
    var row = document.createElement('div');
    row.className = 'ai-cmd-brain-log__row' + (isOk === true ? ' is-ok' : (isOk === false ? ' is-bad' : ''));
    row.innerHTML = html;
    log.prepend(row);
    while (log.children.length > 8) {
      log.removeChild(log.lastChild);
    }
  }

  function renderBrainRulesStatus(st) {
    if (!st || !st.rules) return;
    var installed = st.installed || 0;
    var total = st.total || 20;
    var pct = total > 0 ? Math.round((installed / total) * 100) : 0;
    var prog = document.getElementById('ai-cmd-brain-progress');
    var progLabel = document.getElementById('ai-cmd-brain-progress-label');
    var statsEl = document.getElementById('ai-cmd-brain-stats');
    if (prog) prog.style.width = pct + '%';
    if (progLabel) {
      progLabel.textContent = installed + ' / ' + total + ' reguli în RAG chat' + (st.complete ? ' — complet' : (st.next_id ? ' · următoarea #' + st.next_id : ''));
    }
    if (statsEl) {
      statsEl.innerHTML = '<span class="ai-cmd-brain__stat ai-cmd-brain__stat--p0">' + installed + '/' + total + '</span>';
    }
    st.rules.forEach(function (r) {
      var card = document.querySelector('.ai-cmd-brain-card[data-brain-id="' + r.id + '"]');
      if (!card) return;
      card.classList.toggle('is-installed', !!r.installed);
      card.classList.toggle('is-next', !st.complete && st.next_id === r.id);
      var state = card.querySelector('[data-brain-state="' + r.id + '"]');
      if (state) state.textContent = r.installed ? '✓' : (st.next_id === r.id ? '→' : '○');
      var btn = card.querySelector('[data-brain-install="' + r.id + '"]');
      if (btn) {
        btn.textContent = r.installed ? 'Reinstalează #' + r.id : 'Instalează #' + r.id;
        btn.disabled = false;
      }
    });
    var nextBtn = document.getElementById('ai-cmd-brain-next-btn');
    if (nextBtn) {
      nextBtn.disabled = !!st.complete;
      nextBtn.textContent = st.complete ? '✓ Toate instalate' : '▶ Instalează #' + (st.next_id || '?');
    }
  }

  function loadBrainRulesStatus() {
    return apiGet({ action: 'brain_rules_status' }).then(function (j) {
      if (j && j.success && j.data) renderBrainRulesStatus(j.data);
      return j;
    }).catch(function () { return null; });
  }

  function installBrainRule(ruleId, btn) {
    if (!ruleId) return Promise.resolve(null);
    if (btn) btn.disabled = true;
    appendBrainLog('Se instalează regula <strong>#' + ruleId + '</strong>…');
    return apiPost({ action: 'brain_rules_seed_one', rule_id: ruleId, update_existing: true }).then(function (j) {
      if (!j || !j.success) {
        appendBrainLog('Eroare regulă #' + ruleId + ': ' + escapeHtml((j && j.message) || 'necunoscută'), false);
        showToast((j && j.message) || 'Eroare instalare regulă', true);
        if (btn) btn.disabled = false;
        return j;
      }
      lastBrainRuleInstalled = ruleId;
      var v = (j.data && j.data.verify) || {};
      var ok = !!v.ok;
      appendBrainLog(
        'Regula <strong>#' + ruleId + '</strong> ' + (v.title || '') + '<br>'
        + 'BD: ' + (v.installed ? '✓' : '✗') + ' · RAG: ' + (v.rag_match ? '✓ scor ' + (v.match_score || '—') : '✗')
        + '<br><em>Test:</em> „' + escapeHtml(v.test_message || '') + '”',
        ok
      );
      showToast(ok ? 'Regula #' + ruleId + ' OK (BD + RAG)' : 'Regula #' + ruleId + ' instalată — verifică RAG', !ok);
      if (j.data && j.data.status) renderBrainRulesStatus(j.data.status);
      if (btn) btn.disabled = false;
      return j;
    }).catch(function (e) {
      appendBrainLog('Eroare rețea regulă #' + ruleId, false);
      if (btn) btn.disabled = false;
      throw e;
    });
  }

  function verifyLastBrainRule() {
    var id = lastBrainRuleInstalled;
    if (!id) {
      return apiGet({ action: 'brain_rules_status' }).then(function (j) {
        var st = j && j.data;
        if (!st || !st.rules) return;
        var last = null;
        st.rules.forEach(function (r) { if (r.installed) last = r.id; });
        id = last;
        if (!id) {
          showToast('Nicio regulă instalată încă', true);
          return;
        }
        return apiGet({ action: 'brain_rules_verify', rule_id: id }).then(function (vr) {
          var v = vr && vr.data;
          if (!v) return;
          appendBrainLog('Re-test #' + id + ': BD ' + (v.installed ? '✓' : '✗') + ' · RAG ' + (v.rag_match ? '✓' : '✗'), !!v.ok);
          showToast(v.ok ? 'Test PASS #' + id : 'Test FAIL #' + id, !v.ok);
        });
      });
    }
    return apiGet({ action: 'brain_rules_verify', rule_id: id }).then(function (vr) {
      var v = vr && vr.data;
      if (!v) return;
      appendBrainLog('Re-test #' + id + ': BD ' + (v.installed ? '✓' : '✗') + ' · RAG ' + (v.rag_match ? '✓' : '✗'), !!v.ok);
      showToast(v.ok ? 'Test PASS #' + id : 'Test FAIL #' + id, !v.ok);
    });
  }

  function bindBrainRulesWizard() {
    var nextBtn = document.getElementById('ai-cmd-brain-next-btn');
    var verifyBtn = document.getElementById('ai-cmd-brain-verify-btn');
    var allBtn = document.getElementById('ai-cmd-brain-all-btn');
    if (nextBtn) {
      nextBtn.addEventListener('click', function () {
        apiGet({ action: 'brain_rules_status' }).then(function (j) {
          var st = j && j.data;
          if (!st || st.complete) {
            showToast('Toate regulile sunt deja instalate');
            return;
          }
          installBrainRule(st.next_id, nextBtn);
        });
      });
    }
    if (verifyBtn) {
      verifyBtn.addEventListener('click', function () { verifyLastBrainRule(); });
    }
    if (allBtn) {
      allBtn.addEventListener('click', function () {
        if (!confirm('Instalezi toate cele 20 reguli în RAG chat?')) return;
        allBtn.disabled = true;
        apiPost({ action: 'brain_rules_seed_all', update_existing: true }).then(function (j) {
          allBtn.disabled = false;
          if (!j || !j.success) {
            showToast((j && j.message) || 'Eroare', true);
            return;
          }
          showToast('20 reguli instalate');
          if (j.data && j.data.status) renderBrainRulesStatus(j.data.status);
          appendBrainLog('Batch complet: ' + (j.data.seed.created || 0) + ' create, ' + (j.data.seed.updated || 0) + ' update', true);
        });
      });
    }
    document.querySelectorAll('.ai-cmd-brain-install').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var id = parseInt(btn.getAttribute('data-brain-install'), 10);
        installBrainRule(id, btn);
      });
    });
  }

  bindBrainRulesWizard();

  window.addEventListener('hashchange', applyHashTab);

  document.addEventListener('visibilitychange', function () {
    if (!document.hidden && isMonitorTabActive()) refreshLiveView(true);
  });

  loadPage().then(function () {
    applyHashTab();
    if (!resolveHashTab()) switchTab('centru');
  });

  window.aiCommandCenterSync = function (supervisorData) {
    renderCommandCenter(lastStatusJson, supervisorData || {});
  };
  window.aiCommandCenterRefresh = loadCommandCenter;

  document.addEventListener('besoiu-alert-fixed', function () {
    loadPage().catch(function () {});
  });

  var librarySearchTimer = null;
  var librarySearchEl = document.getElementById('ai-agent-library-search');
  if (librarySearchEl) {
    librarySearchEl.addEventListener('input', function () {
      if (!selectedSlug) return;
      clearTimeout(librarySearchTimer);
      var q = String(librarySearchEl.value || '').trim();
      librarySearchTimer = setTimeout(function () {
        loadContextLibrary(selectedSlug, q);
      }, 350);
    });
  }
})();
