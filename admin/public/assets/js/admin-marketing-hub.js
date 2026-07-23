/**
 * Marketing Hub v2 — Rezultat → Probleme → Plan → Execuție → Dovezi
 */
(function () {
  'use strict';

  var API = '/admin/api/marketing_hub_endpoint.php';
  var STORAGE = 'bmh_hub_state_v2';
  var PLANNER_KEY = 'bws_marketing_planner_v3';
  var KANBAN = [
    { id: 'identified', label: 'Identificat', hint: 'Problema e clară' },
    { id: 'in_progress', label: 'În lucru', hint: 'Execuți acțiuni' },
    { id: 'published', label: 'Publicat', hint: 'Postări / listări live' },
    { id: 'measured', label: 'Măsurat', hint: 'Verifici impact' },
  ];

  var state = {
    campaigns: [],
    actions: [],
    content: [],
    indicators: [],
    problems: [],
    result: {},
    weekly: {},
    playbooks: [],
    selectedCampaignId: null,
    tab: 'result',
    channel: 'all',
    focusMode: false,
    focusLimit: 3,
    calendarMonth: null,
    calendarSelectedDay: null,
    studioFilter: 'all',
    execShowAll: true,
    execSub: 'pipeline',
    plannerGoal: null,
    plannerLocal: null,
    apiReady: false,
    dataSource: '',
  };

  var EXEC_SUBTABS = ['pipeline', 'calendar', 'paid', 'studio'];

  var modalState = { type: null, payload: {} };

  var CHANNELS = [
    { id: 'site', label: 'Site / catalog' },
    { id: 'google', label: 'Google / SEO' },
    { id: 'pieseauto', label: 'PieseAuto.ro' },
    { id: 'facebook', label: 'Facebook' },
    { id: 'whatsapp', label: 'WhatsApp' },
  ];

  var KPI_METRICS = {
    visitors: 'vizitatori',
    traffic: 'trafic',
    leads: 'lead-uri',
    conversions: 'conversii',
    listings: 'listări',
    searches: 'căutări',
  };

  function defaultKpiForChannel(ch) {
    if (ch === 'facebook' || ch === 'whatsapp') return 'leads';
    if (ch === 'google') return 'traffic';
    if (ch === 'pieseauto') return 'listings';
    return 'visitors';
  }

  function kpiLabel(metric) {
    return KPI_METRICS[metric] || metric || 'KPI';
  }

  function formatKpiRow(item) {
    var metric = item.kpiMetric || defaultKpiForChannel(item.channel || 'site');
    var cur = Number(item.currentValue || 0);
    var tgt = Number(item.targetValue || 0);
    return fmt(cur) + ' → ' + fmt(tgt) + ' ' + kpiLabel(metric);
  }

  function formatProblemImpact(p) {
    var metric = p.kpiMetric || defaultKpiForChannel(p.channel || 'site');
    var gap = Math.abs(Number(p.target || 0) - Number(p.current || 0));
    if (gap <= 0 && p.impactScore) gap = Number(p.impactScore);
    return '+' + fmt(gap || p.impactScore || 0) + ' ' + kpiLabel(metric);
  }

  var CONTENT_TPL = {
    fb_post: { platform: 'Facebook', format: 'post', body: '🔧 [Titlu campanie]\n\n✅ Soluție: [piesă + OEM]\n💰 Preț de la [X] RON\n📲 WhatsApp: [nr]' },
    pieseauto_ad: { platform: 'PieseAuto.ro', format: 'ad', body: 'Titlu: [BRAND] [OEM]\nPreț: [X] RON\nLivrare + garanție.' },
    blog_guide: { platform: 'Blog', format: 'article', body: '# [Titlu ghid]\n\n## Piese recomandate\n- OEM: ...' },
  };

  var CHANNEL_LABELS = {
    all: 'Toate',
    site: 'Site',
    google: 'Google',
    pieseauto: 'PieseAuto',
    facebook: 'Facebook',
    whatsapp: 'WhatsApp',
    instagram: 'Instagram',
    blog: 'Blog',
    tiktok: 'TikTok',
    youtube: 'YouTube',
  };

  var STUDIO_PLATFORMS = [
    { id: 'all', label: 'Toate' },
    { id: 'blog', label: 'Blog' },
    { id: 'facebook', label: 'Facebook' },
    { id: 'instagram', label: 'Instagram' },
    { id: 'tiktok', label: 'TikTok' },
    { id: 'youtube', label: 'YouTube' },
  ];

  var MONTH_NAMES = ['Ianuarie', 'Februarie', 'Martie', 'Aprilie', 'Mai', 'Iunie', 'Iulie', 'August', 'Septembrie', 'Octombrie', 'Noiembrie', 'Decembrie'];
  var DOW_SHORT = ['Lu', 'Ma', 'Mi', 'Jo', 'Vi', 'Sa', 'Du'];

  function normalizeContentItem(c) {
    if (!c || typeof c !== 'object') return c;
    var m = c.metrics && typeof c.metrics === 'object' ? c.metrics : {};
    return {
      id: c.id,
      title: c.title || '',
      platform: c.platform || '',
      format: c.format || 'post',
      status: c.status || 'draft',
      publishDate: c.publishDate || c.publish_date || '',
      campaignId: c.campaignId || c.campaign_id || null,
      body: c.body || '',
      templateKey: c.templateKey || c.template_key || null,
      channel: m.channel || c.channel || resolveItemChannel(c),
      hashtags: m.hashtags || c.hashtags || '',
      mediaUrls: Array.isArray(m.mediaUrls) ? m.mediaUrls : (Array.isArray(c.mediaUrls) ? c.mediaUrls : []),
      mediaType: m.mediaType || c.mediaType || 'none',
      spend: Number(m.spend != null ? m.spend : c.spend || 0),
      impressions: Number(m.impressions != null ? m.impressions : c.impressions || 0),
      clicks: Number(m.clicks != null ? m.clicks : c.clicks || 0),
      leads: Number(m.leads != null ? m.leads : c.leads || 0),
      revenue: Number(m.revenue != null ? m.revenue : c.revenue || 0),
      createdAt: c.createdAt || c.created_at || '',
      updatedAt: c.updatedAt || c.updated_at || '',
      metrics: m,
    };
  }

  function contentForSave(c) {
    var row = normalizeContentItem(c);
    var metrics = {
      channel: row.channel,
      hashtags: row.hashtags,
      mediaUrls: row.mediaUrls,
      mediaType: row.mediaType,
    };
    if (row.format === 'paid_ad') {
      metrics.spend = row.spend;
      metrics.impressions = row.impressions;
      metrics.clicks = row.clicks;
      metrics.leads = row.leads;
      metrics.revenue = row.revenue;
    }
    return {
      id: row.id,
      title: row.title,
      platform: row.platform,
      format: row.format,
      status: row.status,
      publishDate: row.publishDate,
      campaignId: row.campaignId,
      body: row.body,
      templateKey: row.templateKey,
      metrics: metrics,
      createdAt: row.createdAt,
      updatedAt: row.updatedAt,
    };
  }

  function organicContent() {
    return state.content.filter(function (c) { return c.format !== 'paid_ad'; });
  }

  function paidAdsContent() {
    return state.content.filter(function (c) { return c.format === 'paid_ad'; });
  }

  function studioContent() {
    return organicContent().filter(function (c) {
      return ['post', 'video', 'article', 'story', 'reel', 'carousel'].indexOf(c.format) !== -1 ||
        (c.hashtags || (c.mediaUrls && c.mediaUrls.length));
    });
  }

  function calendarContent() {
    return state.content.filter(function (c) {
      return c.format !== 'paid_ad' && (c.publishDate || c.status === 'scheduled');
    });
  }

  function ensureExecLinkedDemo() {
    if (!state.campaigns.length) return;
    var added = false;
    state.campaigns.forEach(function (camp) {
      if (!campaignActions(camp.id).length) {
        state.actions.push(spawnAction(camp, findPlaybook(camp.playbookKey)));
        added = true;
      }
      if (!campaignContent(camp.id).filter(function (c) { return c.format !== 'paid_ad'; }).length) {
        state.content.push(spawnContent(camp, camp.focusOem));
        added = true;
      }
    });
    if (added) {
      state.content = state.content.map(normalizeContentItem);
    }
  }

  function ensureDemoContentHub() {
    if (paidAdsContent().length) return;
    var now = new Date().toISOString();
    var d = function (offset) {
      var x = new Date();
      x.setDate(x.getDate() + offset);
      return x.toISOString().slice(0, 10);
    };
    state.content = state.content.concat([
      {
        id: newId('ads'), title: 'FB Ads — piese BMW', platform: 'Facebook Ads', format: 'paid_ad', status: 'active',
        publishDate: d(-14), channel: 'facebook', body: 'Campanie conversii WhatsApp',
        spend: 420, impressions: 18500, clicks: 340, leads: 12, revenue: 4800,
        createdAt: now, updatedAt: now, metrics: {},
      },
      {
        id: newId('ads'), title: 'Instagram — reach local', platform: 'Instagram Ads', format: 'paid_ad', status: 'active',
        publishDate: d(-7), channel: 'instagram', body: 'Promovare atelier + livrare',
        spend: 280, impressions: 12200, clicks: 210, leads: 8, revenue: 2100,
        createdAt: now, updatedAt: now, metrics: {},
      },
      {
        id: newId('ads'), title: 'Google Search — OEM', platform: 'Google Ads', format: 'paid_ad', status: 'paused',
        publishDate: d(-30), channel: 'google', body: 'Cuvinte cheie cod OEM',
        spend: 650, impressions: 8900, clicks: 520, leads: 15, revenue: 6200,
        createdAt: now, updatedAt: now, metrics: {},
      },
    ]);
  }

  function ensureDemoStudioSamples() {
    if (studioContent().length >= 2) return;
    var now = new Date().toISOString();
    var d = function (offset) {
      var x = new Date();
      x.setDate(x.getDate() + offset);
      return x.toISOString().slice(0, 10);
    };
    state.content = state.content.concat([
      {
        id: newId('cnt'), title: 'Post FB — filtre Mann', platform: 'Facebook', format: 'post', status: 'scheduled',
        publishDate: d(2), channel: 'facebook', body: '🔧 Filtre ulei Mann în stoc!\n\n✅ Livrare rapidă\n📲 Comandă pe WhatsApp',
        hashtags: '#pieseauto #mann #filtru #bmw', mediaType: 'image', mediaUrls: [],
        campaignId: state.campaigns[0] ? state.campaigns[0].id : null, createdAt: now, updatedAt: now,
      },
      {
        id: newId('cnt'), title: 'Reel IG — montaj plăcuțe', platform: 'Instagram', format: 'video', status: 'draft',
        publishDate: d(5), channel: 'instagram', body: 'Video scurt: cum verifici plăcuțele în 60 sec.',
        hashtags: '#frane #tutorial #atelier', mediaType: 'video', mediaUrls: [],
        campaignId: null, createdAt: now, updatedAt: now,
      },
      {
        id: newId('cnt'), title: 'Articol blog — OEM negăsit', platform: 'Blog', format: 'article', status: 'draft',
        publishDate: d(7), channel: 'blog', body: '# De ce codul OEM nu apare în catalog\n\n## Soluție\nImport + TecDoc…',
        hashtags: '#seo #oem #catalog', mediaType: 'none', mediaUrls: [],
        campaignId: null, createdAt: now, updatedAt: now,
      },
    ]);
  }

  function pad2(n) {
    return n < 10 ? '0' + n : String(n);
  }

  function getCalendarMonth() {
    if (!state.calendarMonth) {
      var t = new Date();
      state.calendarMonth = t.getFullYear() + '-' + pad2(t.getMonth() + 1);
    }
    return state.calendarMonth;
  }

  function shiftCalendarMonth(delta) {
    var parts = getCalendarMonth().split('-');
    var y = parseInt(parts[0], 10);
    var m = parseInt(parts[1], 10) - 1 + delta;
    var d = new Date(y, m, 1);
    state.calendarMonth = d.getFullYear() + '-' + pad2(d.getMonth() + 1);
    renderCalendar();
  }

  function itemsOnDate(dateStr) {
    return state.content.filter(function (c) {
      return (c.publishDate || '').slice(0, 10) === dateStr;
    });
  }

  function platformColor(ch) {
    var map = {
      facebook: '#1877f2', instagram: '#e1306c', google: '#4285f4', blog: '#059669',
      pieseauto: '#e85d04', tiktok: '#010101', youtube: '#ff0000', site: '#1abc9c',
    };
    return map[ch] || '#be185d';
  }

  function execCountForCampaign(cid) {
    return campaignActions(cid).length + campaignContent(cid).filter(function (c) { return c.format !== 'paid_ad'; }).length;
  }

  function statusLabel(st) {
    var map = {
      todo: 'De făcut',
      done: 'Finalizat',
      draft: 'Draft',
      scheduled: 'Programat',
      published: 'Publicat',
      active: 'Activ',
    };
    return map[st] || st || '—';
  }

  function campaignTitleById(cid) {
    if (!cid) return 'Fără campanie';
    var c = state.campaigns.find(function (x) { return x.id === cid; });
    return c ? c.title : 'Campanie';
  }

  function execPool() {
    var cid = state.execShowAll ? null : state.selectedCampaignId;
    var ch = state.channel;
    var items = state.content.filter(function (c) {
      if (c.format === 'paid_ad') return false;
      if (cid && c.campaignId !== cid) return false;
      if (ch !== 'all' && resolveItemChannel(c) !== ch) return false;
      return true;
    });
    var acts = state.actions.filter(function (a) {
      if (cid && a.campaignId !== cid) return false;
      if (ch !== 'all' && resolveItemChannel(a) !== ch) return false;
      return true;
    });
    return { items: items, acts: acts };
  }

  function channelExecCounts() {
    var counts = { all: 0 };
    state.content.forEach(function (c) {
      if (c.format === 'paid_ad') return;
      counts.all++;
      var ch = resolveItemChannel(c);
      counts[ch] = (counts[ch] || 0) + 1;
    });
    state.actions.forEach(function (a) {
      counts.all++;
      var ch = resolveItemChannel(a);
      counts[ch] = (counts[ch] || 0) + 1;
    });
    return counts;
  }

  function renderExecCardAction(a) {
    var pri = a.priority === 'high' ? ' bmh-exec-badge--high' : '';
    return '<article class="bmh-exec-card" data-exec-id="' + esc(a.id) + '">' +
      '<div class="bmh-exec-card__camp">' + esc(campaignTitleById(a.campaignId)) + '</div>' +
      '<strong>' + esc(a.title) + '</strong>' +
      '<div class="bmh-exec-card__meta">' +
      '<span class="bmh-exec-badge' + pri + '">' + esc(a.priority || 'medium') + '</span>' +
      '<span class="bmh-exec-badge">' + esc(CHANNEL_LABELS[resolveItemChannel(a)] || resolveItemChannel(a)) + '</span>' +
      '<span>scor ' + fmt(a.score) + '</span>' +
      (a.targetKpi ? '<span>KPI: ' + esc(a.targetKpi) + '</span>' : '') +
      '</div>' +
      (a.notes ? '<p class="bmh-exec-preview" style="max-height:60px">' + esc(a.notes.slice(0, 150)) + '</p>' : '') +
      '<div class="bmh-exec-card__actions">' +
      (a.status !== 'done'
        ? '<button type="button" class="bmh-btn bmh-btn--sm bmh-btn--primary" data-bmh-done-act="' + esc(a.id) + '">✓ Marchează făcut</button>'
        : '<span class="bmh-exec-badge" style="background:#d1fae5">Finalizat</span>') +
      '</div></article>';
  }

  function renderExecCardContent(c) {
    var stCls = c.status === 'published' ? ' style="background:#d1fae5"' : (c.status === 'scheduled' ? ' style="background:#dbeafe"' : '');
    return '<article class="bmh-exec-card" data-exec-id="' + esc(c.id) + '">' +
      '<div class="bmh-exec-card__camp">' + esc(campaignTitleById(c.campaignId)) + '</div>' +
      '<strong>' + esc(c.title) + '</strong>' +
      '<div class="bmh-exec-card__meta">' +
      '<span class="bmh-exec-badge"' + stCls + '>' + esc(statusLabel(c.status)) + '</span>' +
      '<span class="bmh-exec-badge">' + esc(c.platform || CHANNEL_LABELS[resolveItemChannel(c)] || '') + '</span>' +
      (c.publishDate ? '<span>📅 ' + esc(String(c.publishDate).slice(0, 10)) + '</span>' : '') +
      '</div>' +
      '<pre class="bmh-exec-preview">' + esc((c.body || '').slice(0, 280)) + '</pre>' +
      (c.hashtags ? '<div class="bmh-exec-card__meta" style="color:#2563eb">' + esc(c.hashtags) + '</div>' : '') +
      '<div class="bmh-exec-card__actions">' +
      (c.status !== 'published'
        ? '<button type="button" class="bmh-btn bmh-btn--sm bmh-btn--primary" data-bmh-pub-cnt="' + esc(c.id) + '">Publică acum</button>'
        : '') +
      '<button type="button" class="bmh-btn bmh-btn--sm" data-bmh-edit-studio="' + esc(c.id) + '">Editează</button>' +
      '</div></article>';
  }

  function $(id) {
    return document.getElementById(id);
  }

  function fmt(n) {
    var v = Number(n);
    return Number.isFinite(v) ? v.toLocaleString('ro-RO') : '—';
  }

  function esc(s) {
    var d = document.createElement('div');
    d.textContent = s == null ? '' : String(s);
    return d.innerHTML;
  }

  function toast(msg, err) {
    if (typeof window.bpaToast === 'function') window.bpaToast(msg, !!err);
  }

  function apiGet() {
    return fetch(API, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
      .then(function (r) {
        return r.text().then(function (text) {
          var j = null;
          try {
            j = text ? JSON.parse(text) : null;
          } catch (parseErr) {
            throw new Error('API răspuns invalid (HTTP ' + r.status + ')');
          }
          if (j && j.success && j.data) {
            return j;
          }
          if (!r.ok) {
            throw new Error((j && j.message) || ('API HTTP ' + r.status));
          }
          if (!j || !j.success) {
            throw new Error((j && j.message) || 'Răspuns API invalid');
          }
          return j;
        });
      });
  }

  function apiPost(body) {
    return fetch(API, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify(body),
    }).then(function (r) {
      return r.text().then(function (text) {
        var j = null;
        try {
          j = text ? JSON.parse(text) : null;
        } catch (parseErr) {
          throw new Error('API răspuns invalid (HTTP ' + r.status + ')');
        }
        if (!r.ok) {
          throw new Error((j && j.message) || ('API HTTP ' + r.status));
        }
        if (!j || !j.success) {
          throw new Error((j && j.message) || 'Răspuns API invalid');
        }
        return j;
      });
    });
  }

  function fetchWithTimeout(ms) {
    return Promise.race([
      apiGet(),
      new Promise(function (_, reject) {
        setTimeout(function () { reject(new Error('Timeout — serverul nu răspunde')); }, ms);
      }),
    ]);
  }

  function readPlannerLocal() {
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
        target_users: users,
        conversion_rate: conv,
        avg_price: price,
        orders_month: orders,
        revenue_month: Math.round(orders * price),
        visits_month: users * 3,
        source: 'localStorage',
      };
    } catch (e) {
      return null;
    }
  }

  function activePlannerGoal() {
    return state.plannerGoal || state.plannerLocal || null;
  }

  function renderPlannerNorth() {
    var section = $('bmh-planner-north');
    if (!section) return;
    var local = readPlannerLocal();
    state.plannerLocal = local;
    var goal = activePlannerGoal();
    var title = $('bmh-planner-north-title');
    var formula = $('bmh-planner-north-formula');
    var funnel = $('bmh-planner-north-funnel');
    var progress = $('bmh-planner-north-progress');
    var progressFill = $('bmh-planner-north-progress-fill');
    var progressLabel = $('bmh-planner-north-progress-label');
    var applyBtn = $('bmh-apply-planner');

    if (!local && !goal) {
      if (title) title.textContent = 'Setează ținta în Planner';
      if (formula) formula.textContent = 'Deschide /admin/planner → scenariu (ex. 10.000 utilizatori × 5% conversie) → revino aici.';
      if (funnel) funnel.innerHTML = '';
      if (progress) progress.hidden = true;
      section.classList.add('bmh-planner-north--empty');
      return;
    }

    section.classList.remove('bmh-planner-north--empty');
    var g = goal || local;
    var users = Number(g.target_users || 0);
    var conv = Number(g.conversion_rate || 0);
    var orders = Number(g.orders_month || 0);
    var revenue = Number(g.revenue_month || 0);

    if (title) {
      title.textContent = goal
        ? 'North Star activ: ' + fmt(users) + ' utilizatori/lună'
        : 'Planner detectat: ' + fmt(users) + ' utilizatori — apasă Aplică';
    }
    if (formula) {
      formula.textContent = fmt(users) + ' utilizatori × ' + conv + '% conversie = '
        + fmt(orders) + ' comenzi/lună · ~' + fmt(revenue) + ' RON';
    }
    if (funnel) {
      funnel.innerHTML =
        '<span class="bmh-planner-chip"><em>Utilizatori</em><strong>' + fmt(users) + '</strong></span>' +
        '<span class="bmh-planner-arrow">→</span>' +
        '<span class="bmh-planner-chip"><em>Vizite (~×3)</em><strong>' + fmt(g.visits_month || users * 3) + '</strong></span>' +
        '<span class="bmh-planner-arrow">→</span>' +
        '<span class="bmh-planner-chip"><em>Comenzi</em><strong>' + fmt(orders) + '</strong></span>' +
        '<span class="bmh-planner-arrow">→</span>' +
        '<span class="bmh-planner-chip bmh-planner-chip--accent"><em>Venit</em><strong>' + fmt(revenue) + ' RON</strong></span>';
    }

    var r = state.result || {};
    var pct = Number(r.planner_progress_pct || 0);
    var current = Number(r.planner_current_visitors || 0);
    if (goal && progress && progressFill && progressLabel) {
      progress.hidden = false;
      progressFill.style.width = Math.min(100, pct) + '%';
      progressLabel.textContent = fmt(current) + ' / ' + fmt(users) + ' utilizatori estimați (' + pct + '% din țintă)';
    } else if (progress) {
      progress.hidden = true;
    }

    if (applyBtn && local) {
      var same = goal && Number(goal.target_users) === users;
      applyBtn.disabled = !!same;
      var sm = applyBtn.querySelector('small');
      if (sm) {
        sm.textContent = same ? 'Hub aliniat la Planner' : 'Indicatori + campanii + probleme';
      }
    }
  }

  function applyPlannerToHub() {
    var local = readPlannerLocal();
    if (!local) {
      toast('Nu găsesc scenariul în Planner — deschide /admin/planner și salvează ținta.', true);
      return;
    }
    var btn = $('bmh-apply-planner');
    if (btn) btn.disabled = true;
    apiPost({
      action: 'apply_planner',
      planner: {
        target_users: local.target_users,
        conversion_rate: local.conversion_rate,
        avg_price: local.avg_price,
        source: 'planner_local',
      },
    }).then(function (j) {
      if (!j.success) throw new Error(j.message || 'Aplicare eșuată');
      applyData(j.data);
      state.plannerGoal = j.data.planner_goal || local;
      state.execShowAll = true;
      setTab('problems');
      render();
      toast('Ținta ' + fmt(local.target_users) + ' utilizatori — campanii și indicatori generate');
    }).catch(function (e) {
      toast('Planner: ' + (e.message || 'eroare'), true);
    }).finally(function () {
      if (btn) btn.disabled = false;
      renderPlannerNorth();
    });
  }

  function renderLoadingShell() {
    var headline = $('bmh-headline');
    if (headline) headline.textContent = 'Marketing — se încarcă datele…';
    var hero = $('bmh-result-hero');
    if (hero) {
      hero.innerHTML = '<p class="bmh-empty">Se încarcă indicatori și campanii…</p>';
    }
  }

  function applyClientDemoFallback() {
    var now = new Date().toISOString();
    state.campaigns = [
      {
        id: 'cmp_demo_1', title: 'Lansare magazin - site în lucru', channel: 'site', status: 'in_progress',
        problemType: 'traffic', kpiMetric: 'visitors', currentValue: 120, targetValue: 5000,
        baselineValue: 80, isFocus: true, priorityScore: 85, createdAt: now, updatedAt: now,
      },
      {
        id: 'cmp_demo_2', title: 'PieseAuto.ro - vitrină stoc', channel: 'pieseauto', status: 'in_progress',
        problemType: 'marketplace', kpiMetric: 'listings', currentValue: 8, targetValue: 30,
        baselineValue: 8, isFocus: true, priorityScore: 90, createdAt: now, updatedAt: now,
      },
    ];
    state.result = {
      headline: 'Marketing — conținut test (offline)',
      orders_today: 0, revenue_today: 0, searches_today: 0, missing_oem: 0, success_rate: 0,
      actions_done: 0, actions_open: 2, content_published: 0,
      campaigns_total: state.campaigns.length, campaigns_in_progress: 2, campaigns_measured: 0,
    };
    ensureDemoContentHub();
    ensureDemoStudioSamples();
    ensureExecLinkedDemo();
    state.focusMode = false;
    state.execShowAll = true;
    var focusEl = $('bmh-focus-mode');
    if (focusEl) focusEl.checked = false;
    normalizeFilters();
    updateDataStatusHint();
  }

  function applyData(data, opts) {
    opts = opts || {};
    if (!data) return;
    var incomingCampaigns = Array.isArray(data.campaigns) ? data.campaigns : null;
    var incomingActions = Array.isArray(data.actions) ? data.actions : null;
    var incomingContent = Array.isArray(data.content) ? data.content : null;
    if (incomingCampaigns && incomingCampaigns.length) {
      state.campaigns = incomingCampaigns;
    } else if (opts.allowEmpty || !state.campaigns.length) {
      state.campaigns = incomingCampaigns || [];
    }
    if (incomingActions && incomingActions.length) {
      state.actions = incomingActions;
    } else if (opts.allowEmpty || !state.actions.length) {
      state.actions = incomingActions || [];
    }
    if (incomingContent && incomingContent.length) {
      state.content = incomingContent.map(normalizeContentItem);
    } else if (opts.allowEmpty || !state.content.length) {
      state.content = (incomingContent || []).map(normalizeContentItem);
    }
    state.indicators = Array.isArray(data.indicators) ? data.indicators : state.indicators;
    state.problems = Array.isArray(data.problems) ? data.problems : state.problems;
    state.result = data.result || state.result || {};
    state.weekly = data.weekly || state.weekly || {};
    state.playbooks = Array.isArray(data.playbooks) ? data.playbooks : state.playbooks;
    state.focusLimit = data.focus_limit || state.focusLimit || 3;
    state.plannerGoal = data.planner_goal || state.plannerGoal || null;
    ensureDemoContentHub();
    ensureDemoStudioSamples();
    ensureExecLinkedDemo();
    normalizeFilters();
    if (state.selectedCampaignId && !state.campaigns.find(function (c) { return c.id === state.selectedCampaignId; })) {
      state.selectedCampaignId = null;
    }
    updateDataStatusHint();
  }

  function normalizeFilters() {
    if (state.focusMode && state.campaigns.length && !visibleCampaigns().length) {
      state.focusMode = false;
      var focusEl = $('bmh-focus-mode');
      if (focusEl) focusEl.checked = false;
    }
    if (!state.execShowAll && state.selectedCampaignId) {
      var camp = state.campaigns.find(function (c) { return c.id === state.selectedCampaignId; });
      if (!camp) {
        state.execShowAll = true;
        state.selectedCampaignId = null;
      }
    }
  }

  function updateDataStatusHint() {
    var hint = $('bmh-save-hint');
    if (!hint) return;
    if (!state.apiReady) {
      hint.textContent = state.campaigns.length
        ? 'Cache local · ' + state.campaigns.length + ' campanii — sincronizare…'
        : 'Se încarcă datele…';
      return;
    }
    var src = state.dataSource || 'server';
    hint.textContent = state.campaigns.length
      ? state.campaigns.length + ' campanii · ' + state.actions.length + ' acțiuni · ' + src
      : 'Fără campanii — apasă «Încarcă demo»';
  }

  function saveLocal() {
    try {
      var payload = {
        selectedCampaignId: state.selectedCampaignId,
        tab: state.tab,
        focusMode: state.focusMode,
        execSub: state.execSub,
      };
      if (state.campaigns.length) {
        payload.campaigns = state.campaigns;
        payload.actions = state.actions;
        payload.content = state.content;
        payload.savedAt = new Date().toISOString();
      }
      localStorage.setItem(STORAGE, JSON.stringify(payload));
    } catch (e) { /* ignore */ }
  }

  function hydrateFromLocal() {
    try {
      var raw = localStorage.getItem(STORAGE);
      if (!raw) return false;
      var p = JSON.parse(raw);
      if (p.selectedCampaignId) state.selectedCampaignId = p.selectedCampaignId;
      if (p.focusMode) state.focusMode = !!p.focusMode;
      if (p.execSub && EXEC_SUBTABS.indexOf(p.execSub) !== -1) state.execSub = p.execSub;
      if (Array.isArray(p.campaigns) && p.campaigns.length) {
        state.campaigns = p.campaigns;
        state.actions = Array.isArray(p.actions) ? p.actions : [];
        state.content = Array.isArray(p.content) ? p.content.map(normalizeContentItem) : [];
        ensureExecLinkedDemo();
        state.dataSource = 'cache local';
        return true;
      }
    } catch (e) { /* ignore */ }
    return false;
  }

  function loadLocalPrefs() {
    hydrateFromLocal();
  }

  function saveAll(opts) {
    opts = opts || {};
    saveLocal();
    if (!state.apiReady && !opts.force) {
      updateDataStatusHint();
      return Promise.resolve();
    }
    var hint = $('bmh-save-hint');
    if (hint) hint.textContent = 'Se salvează…';
    return apiPost({
      action: 'save_state',
      state: {
        campaigns: state.campaigns,
        actions: state.actions,
        content: state.content.map(contentForSave),
        indicators: state.indicators,
      },
    }).then(function (j) {
      if (j.success && j.data) applyData(j.data);
      state.dataSource = 'server';
      if (hint) hint.textContent = 'Salvat · ' + new Date().toLocaleTimeString('ro-RO', { hour: '2-digit', minute: '2-digit' });
      render();
    }).catch(function () {
      if (hint) hint.textContent = 'Salvat local (BD indisponibilă)';
      render();
    });
  }

  function setChannelFilter(ch) {
    state.channel = ch || 'all';
    document.querySelectorAll('.bmh-channel-tabs [data-channel]').forEach(function (b) {
      b.classList.toggle('is-active', b.getAttribute('data-channel') === state.channel);
    });
    if (state.tab === 'execution') renderExecution();
  }

  function selectedCampaign() {
    return state.campaigns.find(function (c) { return c.id === state.selectedCampaignId; }) || null;
  }

  function selectCampaign(id, opts) {
    opts = opts || {};
    state.selectedCampaignId = id || null;
    if (opts.execShowAll === true) {
      state.execShowAll = true;
    } else if (id) {
      state.execShowAll = false;
    }
    if (opts.syncChannel === true) {
      var camp = selectedCampaign();
      if (camp && camp.channel) setChannelFilter(camp.channel);
    }
    saveLocal();
    render();
  }

  function campaignActions(cid) {
    return state.actions.filter(function (a) { return a.campaignId === cid; });
  }

  function campaignContent(cid) {
    return state.content.filter(function (c) { return c.campaignId === cid; });
  }

  function visibleCampaigns() {
    if (!state.focusMode) return state.campaigns;
    return state.campaigns.filter(function (c) { return c.isFocus || c.status === 'in_progress'; }).slice(0, state.focusLimit);
  }

  function setExecSubTab(sub) {
    if (EXEC_SUBTABS.indexOf(sub) === -1) sub = 'pipeline';
    state.execSub = sub;
    document.querySelectorAll('#bmh-exec-subtabs [data-exec-sub]').forEach(function (btn) {
      btn.classList.toggle('is-active', btn.getAttribute('data-exec-sub') === sub);
    });
    document.querySelectorAll('#bmh-app .bmh-exec-view[data-exec-view]').forEach(function (view) {
      var active = view.getAttribute('data-exec-view') === sub;
      view.classList.toggle('is-active', active);
      if (active) view.removeAttribute('hidden');
      else view.setAttribute('hidden', '');
    });
    var layout = $('bmh-layout');
    if (layout) layout.classList.toggle('bmh-layout--wide', sub !== 'pipeline');
    if (sub === 'pipeline') renderExecution();
    if (sub === 'calendar') renderCalendar();
    if (sub === 'paid') renderPaidAds();
    if (sub === 'studio') renderStudio();
    try {
      var u = new URL(window.location.href);
      if (state.tab === 'execution' && sub !== 'pipeline') u.searchParams.set('sub', sub);
      else u.searchParams.delete('sub');
      window.history.replaceState({}, '', u.pathname + u.search);
    } catch (e) { /* ignore */ }
  }

  function setTab(tab) {
    var execSub = null;
    if (tab === 'calendar' || tab === 'paid' || tab === 'studio') {
      execSub = tab;
      tab = 'execution';
    }
    state.tab = tab;
    document.querySelectorAll('#bmh-app .bmh-tab').forEach(function (btn) {
      btn.classList.toggle('is-active', btn.getAttribute('data-bmh-tab') === tab);
    });
    document.querySelectorAll('#bmh-app .bmh-panel').forEach(function (p) {
      p.classList.toggle('is-active', p.getAttribute('data-bmh-panel') === tab);
    });
    if (tab === 'execution') {
      setChannelFilter('all');
      state.execShowAll = true;
      setExecSubTab(execSub || state.execSub || 'pipeline');
    } else {
      var layout = $('bmh-layout');
      if (layout) layout.classList.remove('bmh-layout--wide');
    }
    try {
      var u = new URL(window.location.href);
      u.searchParams.set('tab', tab);
      if (tab !== 'execution') u.searchParams.delete('sub');
      window.history.replaceState({}, '', u.pathname + u.search);
    } catch (e) { /* ignore */ }
    saveLocal();
  }

  function resolveItemChannel(item) {
    if (!item) return 'site';
    if (item.channel) return String(item.channel);
    var camp = state.campaigns.find(function (x) { return x.id === item.campaignId; });
    if (camp && camp.channel) return camp.channel;
    var p = String(item.platform || '').toLowerCase();
    if (p.indexOf('instagram') !== -1) return 'instagram';
    if (p.indexOf('facebook') !== -1) return 'facebook';
    if (p.indexOf('tiktok') !== -1) return 'tiktok';
    if (p.indexOf('youtube') !== -1) return 'youtube';
    if (p.indexOf('blog') !== -1) return 'blog';
    if (p.indexOf('pieseauto') !== -1) return 'pieseauto';
    if (p.indexOf('whatsapp') !== -1) return 'whatsapp';
    if (p.indexOf('google') !== -1) return 'google';
    return 'site';
  }

  function indicatorChannel(ind) {
    if (ind && ind.category && ind.category !== 'general') return String(ind.category);
    var p = String((ind && ind.platform) || '').toLowerCase();
    if (p.indexOf('facebook') !== -1) return 'facebook';
    if (p.indexOf('pieseauto') !== -1) return 'pieseauto';
    if (p.indexOf('whatsapp') !== -1) return 'whatsapp';
    if (p.indexOf('google') !== -1 || p.indexOf('seo') !== -1) return 'google';
    return 'site';
  }

  function indicatorPct(ind) {
    var tgt = Number(ind.target || 0);
    var val = Number(ind.value || 0);
    if (tgt <= 0) return 0;
    return Math.min(999, Math.round((val / tgt) * 100));
  }

  function indicatorsForChannel(ch) {
    if (!ch || ch === 'all') return state.indicators.slice();
    return state.indicators.filter(function (ind) { return indicatorChannel(ind) === ch; });
  }

  function renderIndicatorCard(ind, compact) {
    var pct = indicatorPct(ind);
    var pctClass = pct >= 100 ? 'is-ok' : pct >= 60 ? 'is-mid' : 'is-low';
    var src = ind.source === 'auto' ? 'auto' : 'manual';
    return '<article class="bmh-ind-card' + (compact ? ' bmh-ind-card--compact' : '') + '">' +
      '<header><span class="bmh-ind-card__platform">' + esc(ind.platform) + '</span>' +
      '<span class="bmh-ind-card__src">' + esc(src) + '</span></header>' +
      '<strong class="bmh-ind-card__metric">' + esc(ind.metric) + '</strong>' +
      '<div class="bmh-ind-card__vals">' +
      '<span class="bmh-ind-card__pct ' + pctClass + '">' + pct + '%</span>' +
      '<span>' + fmt(ind.value) + ' / ' + fmt(ind.target) + '</span></div>' +
      '<button type="button" class="bmh-btn bmh-btn--sm" data-bmh-edit-indicator="' + esc(ind.id) + '">Editează</button>' +
      '</article>';
  }

  function renderPlatformIndicators() {
    var wrap = $('bmh-platform-indicators');
    if (!wrap) return;
    if (!state.indicators.length) {
      wrap.innerHTML =
        '<div class="bmh-ind-empty">' +
        '<h3>Indicatori piață / platforme</h3>' +
        '<p>Nu există indicatori — apasă <strong>Actualizează date</strong> (site live) sau <strong>Încarcă demo</strong>.</p></div>';
      return;
    }
    var groups = {};
    state.indicators.forEach(function (ind) {
      var ch = indicatorChannel(ind);
      if (!groups[ch]) groups[ch] = [];
      groups[ch].push(ind);
    });
    var order = ['site', 'google', 'facebook', 'pieseauto', 'whatsapp'];
    wrap.innerHTML =
      '<div class="bmh-ind-head">' +
      '<h3>Indicatori piață / platforme</h3>' +
      '<p>Ținte împărțite din Planner (North Star) + valori actuale vs țintă pe canal.</p>' +
      '<button type="button" class="bmh-btn bmh-btn--sm" data-bmh-new-indicator>+ Indicator platformă</button></div>' +
      order.filter(function (ch) { return groups[ch] && groups[ch].length; }).map(function (ch) {
        return '<section class="bmh-ind-group" data-ind-group="' + ch + '">' +
          '<h4>' + esc(CHANNEL_LABELS[ch] || ch) + '</h4>' +
          '<div class="bmh-ind-grid">' +
          groups[ch].map(function (ind) { return renderIndicatorCard(ind, false); }).join('') +
          '</div></section>';
      }).join('');
  }

  function renderPlanIndicators() {
    var wrap = $('bmh-plan-indicators');
    if (!wrap) return;
    var camp = selectedCampaign();
    var items = camp ? indicatorsForChannel(camp.channel) : state.indicators.slice(0, 6);
    if (!items.length) {
      wrap.innerHTML = '';
      return;
    }
    wrap.innerHTML =
      '<section class="bmh-plan-ind">' +
      '<h3>Indicatori ' + (camp ? esc(CHANNEL_LABELS[camp.channel] || camp.channel) : 'platformă') + '</h3>' +
      '<div class="bmh-ind-grid bmh-ind-grid--plan">' +
      items.map(function (ind) { return renderIndicatorCard(ind, true); }).join('') +
      '</div></section>';
  }

  function renderSidebar() {
    var body = $('bmh-sidebar-body');
    var hint = $('bmh-sidebar-hint');
    var c = selectedCampaign();
    if (!body) return;
    if (!c) {
      if (hint) hint.textContent = 'Nicio selecție';
      body.innerHTML = '<p class="bmh-sidebar__empty">Apasă <strong>+ Campanie nouă</strong> — sau selectează un card din kanban.</p>';
      return;
    }
    if (hint) hint.textContent = c.channel + ' · ' + c.status;
    var checklist = (c.checklist || []).map(function (item) {
      return '<label class="bmh-check-item"><input type="checkbox" data-check-id="' + esc(item.id) + '" ' + (item.done ? 'checked' : '') + '> ' + esc(item.label) + '</label>';
    }).join('');
    var inds = indicatorsForChannel(c.channel).slice(0, 3);
    var indHtml = inds.length
      ? '<section class="bmh-sidebar-ind"><h4>Indicatori canal</h4>' +
        inds.map(function (ind) {
          return '<div class="bmh-sidebar-ind-row"><span>' + esc(ind.metric) + '</span><strong>' +
            fmt(ind.value) + ' / ' + fmt(ind.target) + '</strong></div>';
        }).join('') + '</section>'
      : '';
    body.innerHTML =
      '<article class="bmh-sidebar-card">' +
      '<strong>' + esc(c.title) + '</strong>' +
      '<p>' + esc(c.problemSummary) + '</p>' +
      '<dl class="bmh-sidebar-stats">' +
      '<div><dt>KPI țintă</dt><dd>' + formatKpiRow(c) + '</dd></div>' +
      '<div><dt>Canal</dt><dd>' + esc(c.channel) + '</dd></div>' +
      '<div><dt>Acțiuni</dt><dd>' + campaignActions(c.id).length + '</dd></div>' +
      '<div><dt>Conținut</dt><dd>' + campaignContent(c.id).length + '</dd></div>' +
      '</dl>' +
      indHtml +
      '<div class="bmh-checklist">' + checklist + '</div>' +
      '<div class="bmh-sidebar-actions">' +
      '<button type="button" class="bmh-btn bmh-btn--sm bmh-btn--primary" data-bmh-edit-campaign="' + esc(c.id) + '">Editează</button>' +
      '<button type="button" class="bmh-btn bmh-btn--sm" data-bmh-add-checklist>Pas checklist</button>' +
      '<button type="button" class="bmh-btn bmh-btn--sm" data-bmh-goto="execution">Execuție</button>' +
      '<button type="button" class="bmh-btn bmh-btn--sm bmh-btn--danger" data-bmh-delete-campaign="' + esc(c.id) + '">Șterge</button>' +
      '</div></article>';
  }

  function renderResult() {
    var r = state.result || {};
    var hero = $('bmh-result-hero');
    var grid = $('bmh-result-grid');
    var headline = $('bmh-headline');
    if (headline) headline.textContent = r.headline || 'Marketing — spre comenzi';
    if (hero) {
      hero.innerHTML =
        '<div class="bmh-hero-stat bmh-hero-stat--main"><span>Comenzi azi</span><strong>' + fmt(r.orders_today) + '</strong><em>vânzări confirmate</em></div>' +
        '<div class="bmh-hero-stat"><span>Venit azi</span><strong>' + fmt(r.revenue_today) + ' RON</strong></div>' +
        '<div class="bmh-hero-stat"><span>Căutări azi</span><strong>' + fmt(r.searches_today) + '</strong><em>evenimente, nu clienți</em></div>' +
        '<div class="bmh-hero-stat"><span>OEM lipsă</span><strong>' + fmt(r.missing_oem) + '</strong><em>coduri distincte</em></div>';
    }
    if (grid) {
      var plannerExtra = '';
      if (r.planner_linked && r.planner_target_users) {
        plannerExtra =
          '<article class="bmh-stat-card bmh-stat-card--north">' +
          '<span>North Star (Planner)</span>' +
          '<strong>' + fmt(r.planner_current_visitors || 0) + ' / ' + fmt(r.planner_target_users) + '</strong>' +
          '<em>' + fmt(r.planner_progress_pct || 0) + '% · țintă ' + fmt(r.planner_orders_month || 0) + ' comenzi/lună</em></article>';
      }
      grid.innerHTML = plannerExtra +
        '<article class="bmh-stat-card"><span>Campanii</span><strong>' + fmt(r.campaigns_total) + '</strong><em>' + fmt(r.campaigns_in_progress) + ' în lucru</em></article>' +
        '<article class="bmh-stat-card"><span>Acțiuni</span><strong>' + fmt(r.actions_done) + ' done</strong><em>' + fmt(r.actions_open) + ' deschise</em></article>' +
        '<article class="bmh-stat-card"><span>Conținut</span><strong>' + fmt(r.content_published) + ' publicat</strong><em>postări / anunțuri</em></article>' +
        '<article class="bmh-stat-card"><span>Rată succes</span><strong>' + fmt(r.success_rate) + '%</strong><em>căutări găsite</em></article>';
    }
    var w = $('bmh-weekly-review');
    if (w && state.weekly && state.weekly.summary) {
      var s = state.weekly.summary;
      w.innerHTML = '<h3>Review săptămână</h3><p>' +
        fmt(s.actions_done) + ' acțiuni închise · ' +
        fmt(s.content_published) + ' publicate · ' +
        fmt(s.indicators_below_target) + ' indicatori sub țintă</p>';
    }
  }

  function renderProblems() {
    var list = $('bmh-problems-list');
    var pb = $('bmh-playbooks-quick');
    if (!list) return;
    if (!state.problems.length) {
      list.innerHTML =
        '<p class="bmh-empty">Probleme auto din Search Logs — sau creează manual planul ofensiv.</p>' +
        '<div class="bmh-problems-cta">' +
        '<button type="button" class="bmh-btn bmh-btn--primary" id="bmh-new-campaign-problems">+ Campanie nouă (manual)</button>' +
        '<button type="button" class="bmh-btn" id="bmh-sync-problems">Actualizează date Search Logs</button>' +
        '</div>';
    } else {
      list.innerHTML = state.problems.map(function (p) {
        var sev = p.severity || 'info';
        var plannerBadge = p.plannerLinked ? '<span class="bmh-problem__planner">North Star</span>' : '';
        return '<article class="bmh-problem bmh-problem--' + sev + (p.plannerLinked ? ' bmh-problem--planner' : '') + '">' +
          '<div class="bmh-problem__main">' +
          '<span class="bmh-problem__badge">' + esc(p.type) + ' · ' + esc(p.channel) + plannerBadge + '</span>' +
          '<strong>' + esc(p.title) + '</strong>' +
          '<p>' + esc(p.summary) + '</p>' +
          '<div class="bmh-problem__nums">' +
          '<span>Acum: <strong>' + fmt(p.current) + ' ' + esc(p.unit) + '</strong></span>' +
          '<span>Potențial: <strong>' + formatProblemImpact(p) + '</strong></span>' +
          '</div></div>' +
          '<button type="button" class="bmh-btn bmh-btn--primary" data-bmh-create-campaign="' + esc(p.id) + '">' +
          'Creează campanie<small>Plan + execuție + draft</small></button></article>';
      }).join('');
    }
    if (pb) {
      var pbList = state.playbooks.length ? state.playbooks : [
        { key: 'seo_catalog', title: 'SEO catalog', notes: 'Top OEM negăsite' },
        { key: 'fb_weekly', title: 'Facebook săptămânal', notes: '3 postări / săpt.' },
        { key: 'pieseauto_refresh', title: 'Refresh PieseAuto.ro', notes: 'Republică anunțuri' },
      ];
      pb.innerHTML = '<h3>Rețete rapide (playbook) — 1 click = campanie</h3><div class="bmh-playbook-row">' +
        pbList.map(function (p) {
          return '<button type="button" class="bmh-playbook-btn" data-bmh-playbook="' + esc(p.key) + '">' +
            '<strong>' + esc(p.title) + '</strong><span>' + esc(p.notes || '') + '</span></button>';
        }).join('') + '</div>';
    }
  }

  function renderKanban() {
    var board = $('bmh-kanban');
    if (!board) return;
    var camps = visibleCampaigns();
    var emptyBanner = '';
    if (!camps.length) {
      emptyBanner =
        '<div class="bmh-kanban-empty">' +
        '<p><strong>Kanban gol</strong> — se încarcă demo automat sau apasă <em>Încarcă demo</em> în footer.</p>' +
        '<button type="button" class="bmh-btn bmh-btn--primary" id="bmh-seed-demo-inline">Încarcă conținut test</button>' +
        '</div>';
    }
    board.innerHTML = emptyBanner + KANBAN.map(function (col) {
      var cards = camps.filter(function (c) { return c.status === col.id; });
      return '<div class="bmh-kanban-col" data-status="' + col.id + '">' +
        '<header><strong>' + esc(col.label) + '</strong><span>' + esc(col.hint) + ' · ' + cards.length + '</span></header>' +
        '<div class="bmh-kanban-cards">' + cards.map(function (c) {
          var sel = c.id === state.selectedCampaignId ? ' is-selected' : '';
          return '<article class="bmh-kanban-card' + sel + '" data-campaign-id="' + esc(c.id) + '">' +
            '<strong>' + esc(c.title) + '</strong>' +
            '<span>' + esc(c.channel) + ' · ' + formatKpiRow(c) + '</span>' +
            '<div class="bmh-kanban-card__actions">' +
            '<button type="button" data-bmh-select-campaign="' + esc(c.id) + '">Selectează</button>' +
            '<button type="button" data-bmh-edit-campaign="' + esc(c.id) + '">Editează</button>' +
            (col.id !== 'measured' ? '<button type="button" data-bmh-advance="' + esc(c.id) + '">Mută →</button>' : '') +
            '</div></article>';
        }).join('') +
        '</div>' +
        '<button type="button" class="bmh-kanban-add" data-bmh-new-in-col="' + col.id + '">+ Adaugă campanie aici</button>' +
        '</div>';
    }).join('');
  }

  function renderExecution() {
    var grid = $('bmh-exec-grid');
    var header = $('bmh-exec-header');
    var campRow = $('bmh-exec-campaigns');
    if (!grid) return;

    var pool = execPool();
    var acts = pool.acts;
    var items = pool.items;
    var chCounts = channelExecCounts();

    document.querySelectorAll('#bmh-channel-tabs [data-channel]').forEach(function (btn) {
      var ch = btn.getAttribute('data-channel') || 'all';
      var n = chCounts[ch] || 0;
      var label = CHANNEL_LABELS[ch] || (ch === 'all' ? 'Toate' : ch);
      btn.innerHTML = esc(label) + (n > 0 ? ' <span class="bmh-ch-count">' + n + '</span>' : '');
      btn.classList.toggle('is-active', state.channel === ch);
    });

    var todoActs = acts.filter(function (a) { return a.status !== 'done'; });
    var doneActs = acts.filter(function (a) { return a.status === 'done'; });
    var draftItems = items.filter(function (c) { return c.status !== 'published'; });
    var pubItems = items.filter(function (c) { return c.status === 'published'; });

    if (header) {
      header.innerHTML =
        '<article class="bmh-exec-stat"><span>Acțiuni de făcut</span><strong>' + todoActs.length + '</strong></article>' +
        '<article class="bmh-exec-stat"><span>Conținut draft</span><strong>' + draftItems.length + '</strong></article>' +
        '<article class="bmh-exec-stat"><span>Publicat</span><strong>' + pubItems.length + '</strong></article>' +
        '<article class="bmh-exec-stat"><span>Total vizibil</span><strong>' + (acts.length + items.length) + '</strong></article>';
    }

    if (campRow) {
      var pills = '<button type="button" class="bmh-exec-camp-pill' + (state.execShowAll ? ' is-active' : '') + '" data-bmh-exec-camp="__all__">Toate campaniile<small>' + state.campaigns.length + '</small></button>';
      pills += state.campaigns.map(function (c) {
        var n = execCountForCampaign(c.id);
        var active = !state.execShowAll && state.selectedCampaignId === c.id;
        return '<button type="button" class="bmh-exec-camp-pill' + (active ? ' is-active' : '') + '" data-bmh-exec-camp="' + esc(c.id) + '">' +
          esc(c.title.length > 28 ? c.title.slice(0, 28) + '…' : c.title) +
          '<small>' + n + '</small></button>';
      }).join('');
      campRow.innerHTML = pills;
    }

    if (!acts.length && !items.length) {
      var hasAny = state.actions.length + organicContent().length;
      grid.innerHTML =
        '<div class="bmh-exec-pipeline">' +
        '<div class="bmh-exec-col bmh-exec-col--todo" style="grid-column:1/-1;padding:24px;text-align:center">' +
        (hasAny > 0
          ? '<p class="bmh-empty">Filtrul curent ascunde tot. Apasă <strong>Arată tot</strong> sau <strong>Toate campaniile</strong>.</p>' +
            '<div class="bmh-problems-cta" style="justify-content:center;margin-top:12px">' +
            '<button type="button" class="bmh-btn bmh-btn--primary" id="bmh-exec-show-all-inline">Arată tot</button>' +
            '<button type="button" class="bmh-btn" data-bmh-set-channel="all">Canal: Toate</button></div>'
          : '<p class="bmh-empty"><strong>Nicio acțiune sau conținut.</strong> Apasă «Încarcă demo» (footer) sau adaugă manual.</p>' +
            '<div class="bmh-problems-cta" style="justify-content:center;margin-top:12px">' +
            '<button type="button" class="bmh-btn bmh-btn--primary" data-bmh-trigger-add-action">+ Acțiune</button>' +
            '<button type="button" class="bmh-btn bmh-btn--primary" data-bmh-trigger-add-content">+ Conținut</button>' +
            '<button type="button" class="bmh-btn" id="bmh-seed-demo-inline">Încarcă demo</button></div>') +
        '</div></div>';
      return;
    }

    grid.innerHTML =
      '<div class="bmh-exec-pipeline">' +
      '<div class="bmh-exec-col bmh-exec-col--todo">' +
      '<header class="bmh-exec-col__head"><strong>1 · De făcut</strong><span>Acțiuni deschise · ' + todoActs.length + '</span></header>' +
      '<div class="bmh-exec-col__body">' +
      (todoActs.length ? todoActs.map(renderExecCardAction).join('') : '<p class="bmh-exec-empty-col">Nicio acțiune — adaugă una.</p>') +
      '</div></div>' +
      '<div class="bmh-exec-col bmh-exec-col--draft">' +
      '<header class="bmh-exec-col__head"><strong>2 · Draft / programat</strong><span>Conținut de publicat · ' + draftItems.length + '</span></header>' +
      '<div class="bmh-exec-col__body">' +
      (draftItems.length ? draftItems.map(renderExecCardContent).join('') : '<p class="bmh-exec-empty-col">Niciun draft — creează în Studio sau + Conținut.</p>') +
      '</div></div>' +
      '<div class="bmh-exec-col bmh-exec-col--done">' +
      '<header class="bmh-exec-col__head"><strong>3 · Finalizat</strong><span>Acțiuni + publicat · ' + (doneActs.length + pubItems.length) + '</span></header>' +
      '<div class="bmh-exec-col__body">' +
      (doneActs.length + pubItems.length
        ? doneActs.map(renderExecCardAction).join('') + pubItems.map(renderExecCardContent).join('')
        : '<p class="bmh-exec-empty-col">Marchează acțiuni făcute sau conținut publicat.</p>') +
      '</div></div></div>';
  }

  function renderCalendar() {
    var grid = $('bmh-cal-grid');
    var label = $('bmh-cal-label');
    var legend = $('bmh-cal-legend');
    var detail = $('bmh-cal-day-detail');
    if (!grid || !label) return;

    var ym = getCalendarMonth().split('-');
    var year = parseInt(ym[0], 10);
    var month = parseInt(ym[1], 10) - 1;
    label.textContent = MONTH_NAMES[month] + ' ' + year;

    if (legend) {
      legend.innerHTML =
        '<span><i style="background:#ec4899"></i> Post organic / studio</span>' +
        '<span><i style="background:#3b82f6"></i> Reclame plătite</span>' +
        '<span><i style="background:#10b981"></i> Publicat</span>';
    }

    var first = new Date(year, month, 1);
    var startDow = (first.getDay() + 6) % 7;
    var daysInMonth = new Date(year, month + 1, 0).getDate();
    var todayStr = new Date().toISOString().slice(0, 10);

    var html = DOW_SHORT.map(function (d) { return '<div class="bmh-cal-dow">' + d + '</div>'; }).join('');

    var prevDays = new Date(year, month, 0).getDate();
    for (var i = 0; i < startDow; i++) {
      var pd = prevDays - startDow + i + 1;
      html += '<div class="bmh-cal-cell is-other"><span class="bmh-cal-cell__num">' + pd + '</span></div>';
    }

    for (var day = 1; day <= daysInMonth; day++) {
      var dateStr = year + '-' + pad2(month + 1) + '-' + pad2(day);
      var dayItems = itemsOnDate(dateStr);
      var cls = 'bmh-cal-cell';
      if (dateStr === todayStr) cls += ' is-today';
      if (dateStr === state.calendarSelectedDay) cls += ' is-selected';
      var chips = dayItems.slice(0, 3).map(function (it) {
        var chipCls = it.format === 'paid_ad' ? 'bmh-cal-chip bmh-cal-chip--paid' : 'bmh-cal-chip';
        if (it.status === 'published') chipCls += ' bmh-cal-chip--pub';
        return '<span class="' + chipCls + '">' + esc(it.title) + '</span>';
      }).join('');
      if (dayItems.length > 3) chips += '<span class="bmh-cal-chip">+' + (dayItems.length - 3) + '</span>';
      html += '<div class="' + cls + '" data-bmh-cal-day="' + dateStr + '">' +
        '<span class="bmh-cal-cell__num">' + day + '</span>' + chips + '</div>';
    }

    grid.innerHTML = html;

    if (detail) {
      if (!state.calendarSelectedDay) {
        detail.innerHTML = '<p class="bmh-empty">Click pe o zi pentru detalii și programare postări.</p>';
        return;
      }
      var selItems = itemsOnDate(state.calendarSelectedDay);
      detail.innerHTML = '<h3 style="margin:0 0 10px;font-size:14px">' + esc(state.calendarSelectedDay) + '</h3>' +
        (selItems.length ? selItems.map(function (it) {
          return '<article class="bmh-exec-card" style="margin-bottom:8px">' +
            '<strong>' + esc(it.title) + '</strong>' +
            '<span>' + esc(it.platform) + ' · ' + esc(it.status) + (it.format === 'paid_ad' ? ' · ads' : '') + '</span>' +
            '<button type="button" class="bmh-btn bmh-btn--sm" data-bmh-edit-studio="' + esc(it.id) + '">Editează</button></article>';
        }).join('') : '<p class="bmh-empty">Nicio postare — adaugă una programată.</p>') +
        '<button type="button" class="bmh-btn bmh-btn--primary bmh-btn--sm" data-bmh-cal-add-day="' + esc(state.calendarSelectedDay) + '">+ Postare în această zi</button>';
    }
  }

  function renderPaidAds() {
    var kpis = $('bmh-paid-kpis');
    var chartSpend = $('bmh-paid-chart-spend');
    var chartImpact = $('bmh-paid-chart-impact');
    var table = $('bmh-paid-table');
    var ads = paidAdsContent();
    if (!kpis) return;

    var totalSpend = 0;
    var totalLeads = 0;
    var totalRevenue = 0;
    var totalClicks = 0;
    ads.forEach(function (a) {
      totalSpend += Number(a.spend || 0);
      totalLeads += Number(a.leads || 0);
      totalRevenue += Number(a.revenue || 0);
      totalClicks += Number(a.clicks || 0);
    });
    var roas = totalSpend > 0 ? (totalRevenue / totalSpend) : 0;
    var cpl = totalLeads > 0 ? (totalSpend / totalLeads) : 0;

    kpis.innerHTML =
      '<article class="bmh-paid-kpi"><span>Cheltuieli totale</span><strong>' + fmt(totalSpend) + ' RON</strong><em>spend ads</em></article>' +
      '<article class="bmh-paid-kpi"><span>Lead-uri</span><strong>' + fmt(totalLeads) + '</strong><em>din reclame</em></article>' +
      '<article class="bmh-paid-kpi"><span>Venit estimat</span><strong>' + fmt(totalRevenue) + ' RON</strong><em>atribuit campaniilor</em></article>' +
      '<article class="bmh-paid-kpi"><span>ROAS</span><strong>' + (roas > 0 ? roas.toFixed(2) + '×' : '—') + '</strong><em>venit / cheltuieli</em></article>' +
      '<article class="bmh-paid-kpi"><span>Cost / lead</span><strong>' + (cpl > 0 ? fmt(Math.round(cpl)) + ' RON' : '—') + '</strong><em>CPL mediu</em></article>' +
      '<article class="bmh-paid-kpi"><span>Click-uri</span><strong>' + fmt(totalClicks) + '</strong><em>trafic plătit</em></article>';

    var maxSpend = Math.max.apply(null, ads.map(function (a) { return Number(a.spend || 0); }).concat([1]));

    if (chartSpend) {
      chartSpend.innerHTML = '<h3>Cheltuieli pe platformă (RON)</h3>' +
        (ads.length ? ads.map(function (a) {
          var pct = Math.round((Number(a.spend || 0) / maxSpend) * 100);
          return '<div class="bmh-bar-row"><label>' + esc(a.platform) + '</label>' +
            '<div class="bmh-bar-track"><div class="bmh-bar-fill" style="width:' + pct + '%"></div></div>' +
            '<output>' + fmt(a.spend) + '</output></div>';
        }).join('') : '<p class="bmh-empty">Adaugă o campanie ads.</p>');
    }

    if (chartImpact) {
      var maxRev = Math.max.apply(null, ads.map(function (a) { return Number(a.revenue || 0); }).concat([1]));
      chartImpact.innerHTML = '<h3>Impact — venit vs lead-uri</h3>' +
        (ads.length ? ads.map(function (a) {
          var pct = Math.round((Number(a.revenue || 0) / maxRev) * 100);
          return '<div class="bmh-bar-row"><label>' + esc(a.title.slice(0, 12)) + '</label>' +
            '<div class="bmh-bar-track"><div class="bmh-bar-fill bmh-bar-fill--revenue" style="width:' + pct + '%"></div></div>' +
            '<output>' + fmt(a.leads) + ' L</output></div>';
        }).join('') : '<p class="bmh-empty">—</p>');
    }

    if (table) {
      table.innerHTML = ads.length
        ? '<table class="bmh-paid-table"><thead><tr><th>Campanie</th><th>Platformă</th><th>Spend</th><th>Impresii</th><th>Click</th><th>Lead</th><th>Venit</th><th>ROAS</th><th></th></tr></thead><tbody>' +
          ads.map(function (a) {
            var r = Number(a.spend) > 0 ? (Number(a.revenue) / Number(a.spend)) : 0;
            var roasCls = r >= 2 ? 'bmh-paid-roas' : 'bmh-paid-roas is-low';
            return '<tr><td><strong>' + esc(a.title) + '</strong></td><td>' + esc(a.platform) + '</td>' +
              '<td>' + fmt(a.spend) + '</td><td>' + fmt(a.impressions) + '</td><td>' + fmt(a.clicks) + '</td>' +
              '<td>' + fmt(a.leads) + '</td><td>' + fmt(a.revenue) + '</td>' +
              '<td class="' + roasCls + '">' + (r > 0 ? r.toFixed(2) + '×' : '—') + '</td>' +
              '<td><button type="button" class="bmh-btn bmh-btn--sm" data-bmh-edit-paid="' + esc(a.id) + '">Editează</button></td></tr>';
          }).join('') + '</tbody></table>'
        : '<p class="bmh-empty">Nicio campanie ads — apasă «+ Campanie ads».</p>';
    }
  }

  function renderStudio() {
    var filters = $('bmh-studio-filters');
    var grid = $('bmh-studio-grid');
    if (!filters || !grid) return;

    filters.innerHTML = STUDIO_PLATFORMS.map(function (p) {
      return '<button type="button" class="' + (state.studioFilter === p.id ? 'is-active' : '') + '" data-bmh-studio-filter="' + p.id + '">' + esc(p.label) + '</button>';
    }).join('');

    var items = studioContent().filter(function (c) {
      if (state.studioFilter === 'all') return true;
      return resolveItemChannel(c) === state.studioFilter || (c.channel || '') === state.studioFilter;
    });

    if (!items.length) {
      grid.innerHTML = '<p class="bmh-empty">Niciun material — creează postări blog, FB, Instagram, video cu hashtag-uri.</p>' +
        '<button type="button" class="bmh-btn bmh-btn--primary" data-bmh-trigger-studio">+ Material nou</button>';
      return;
    }

    grid.innerHTML = items.map(function (c) {
      var mediaHtml = '';
      if (c.mediaUrls && c.mediaUrls[0]) {
        mediaHtml = '<img src="' + esc(c.mediaUrls[0]) + '" alt="">';
      } else if (c.mediaType === 'video') {
        mediaHtml = '<span style="font-size:2rem;opacity:.4">▶</span>';
      } else {
        mediaHtml = '<span style="font-size:2rem;opacity:.25">🖼</span>';
      }
      var stCls = 'bmh-studio-status';
      if (c.status === 'scheduled') stCls += ' bmh-studio-status--scheduled';
      if (c.status === 'published') stCls += ' bmh-studio-status--published';
      return '<article class="bmh-studio-card">' +
        '<div class="bmh-studio-card__media' + (c.mediaType === 'video' ? ' bmh-studio-card__media--video' : '') + '">' + mediaHtml + '</div>' +
        '<div class="bmh-studio-card__body">' +
        '<span class="bmh-studio-card__platform">' + esc(c.platform) + ' · ' + esc(c.format) + '</span>' +
        '<strong>' + esc(c.title) + '</strong>' +
        (c.hashtags ? '<div class="bmh-studio-card__tags">' + esc(c.hashtags) + '</div>' : '') +
        '<div class="bmh-studio-card__preview">' + esc((c.body || '').replace(/\n/g, ' ').slice(0, 120)) + '</div>' +
        (c.publishDate ? '<small style="color:var(--bmh-muted)">📅 ' + esc(String(c.publishDate).slice(0, 10)) + '</small>' : '') +
        '</div>' +
        '<div class="bmh-studio-card__foot">' +
        '<span class="' + stCls + '">' + esc(c.status) + '</span>' +
        '<button type="button" class="bmh-btn bmh-btn--sm" data-bmh-edit-studio="' + esc(c.id) + '">Editează</button>' +
        (c.status !== 'published' ? '<button type="button" class="bmh-btn bmh-btn--sm" data-bmh-pub-cnt="' + esc(c.id) + '">Publică</button>' : '') +
        '</div></article>';
    }).join('');
  }

  function renderEvidence() {
    var list = $('bmh-evidence-list');
    if (!list) return;
    var camps = cidFilter(state.campaigns);
    if (!camps.length) {
      list.innerHTML = '<p class="bmh-empty">Campaniile apar aici după ce le creezi — snapshot before/after.</p>';
      return;
    }
    list.innerHTML = camps.map(function (c) {
      var before = c.evidenceBefore || {};
      var after = c.evidenceAfter || {};
      return '<article class="bmh-evidence-card">' +
        '<header><strong>' + esc(c.title) + '</strong><span>' + esc(c.status) + '</span></header>' +
        '<div class="bmh-evidence-compare">' +
        '<div><h4>Înainte</h4><pre>' + esc(JSON.stringify(before, null, 2)) + '</pre></div>' +
        '<div><h4>După</h4><pre>' + esc(Object.keys(after).length ? JSON.stringify(after, null, 2) : 'Încă nemăsurat') + '</pre></div>' +
        '</div>' +
        '<button type="button" class="bmh-btn bmh-btn--primary" data-bmh-capture-after="' + esc(c.id) + '">Capturează „după” acum</button>' +
        '</article>';
    }).join('');
  }

  function cidFilter(arr) {
    if (!state.selectedCampaignId) return arr;
    return arr.filter(function (x) { return x.id === state.selectedCampaignId; });
  }

  function render() {
    normalizeFilters();
    renderPlannerNorth();
    renderResult();
    renderPlatformIndicators();
    renderProblems();
    renderKanban();
    renderPlanIndicators();
    renderExecution();
    renderEvidence();
    if (state.tab === 'execution') {
      var sub = state.execSub || 'pipeline';
      if (sub === 'calendar') renderCalendar();
      else if (sub === 'paid') renderPaidAds();
      else if (sub === 'studio') renderStudio();
    }
    renderSidebar();
  }

  function nextStatus(current) {
    var i = KANBAN.findIndex(function (k) { return k.id === current; });
    return i >= 0 && i < KANBAN.length - 1 ? KANBAN[i + 1].id : current;
  }

  function newId(prefix) {
    return prefix + '_' + Date.now().toString(36) + Math.random().toString(36).slice(2, 6);
  }

  function defaultChecklist(playbookKey) {
    var map = {
      seo_catalog: [
        { id: 'c1', label: 'Identifică top 5 OEM negăsite', done: false },
        { id: 'c2', label: 'Import / publică produse în catalog', done: false },
        { id: 'c3', label: 'Verifică în Search Logs', done: false },
      ],
      fb_weekly: [
        { id: 'c1', label: 'Scrie post cu CTA WhatsApp', done: false },
        { id: 'c2', label: 'Publică pe Facebook', done: false },
        { id: 'c3', label: 'Notează reach / click-uri', done: false },
      ],
      pieseauto_refresh: [
        { id: 'c1', label: 'Selectează anunțuri de republicat', done: false },
        { id: 'c2', label: 'Actualizează preț + stoc', done: false },
        { id: 'c3', label: 'Marchează publicat', done: false },
      ],
    };
    return map[playbookKey] || [
      { id: 'c1', label: 'Definește obiectivul campaniei', done: false },
      { id: 'c2', label: 'Execută acțiunile din Plan', done: false },
      { id: 'c3', label: 'Măsoară după 7 zile', done: false },
    ];
  }

  function findPlaybook(key) {
    return state.playbooks.find(function (p) { return p.key === key; }) || null;
  }

  function canAddFocusCampaign() {
    if (!state.focusMode) return true;
    var active = state.campaigns.filter(function (c) { return c.isFocus && c.status !== 'done' && c.status !== 'measured'; }).length;
    return active < state.focusLimit;
  }

  function spawnAction(campaign, pb) {
    var now = new Date().toISOString();
    return {
      id: newId('act'),
      title: (pb && pb.title) ? pb.title : ('Acțiune: ' + campaign.title),
      targetKpi: (pb && pb.targetKpi) || '',
      campaignId: campaign.id,
      kanbanStep: campaign.status || 'identified',
      deadline: '',
      priority: (pb && pb.priority) || 'high',
      status: 'todo',
      effort: (pb && pb.effort) || 3,
      impact: (pb && pb.impact) || 4,
      notes: campaign.problemSummary || '',
      playbookKey: campaign.playbookKey || null,
      score: 0,
      createdAt: now,
      updatedAt: now,
    };
  }

  function spawnContent(campaign, oem) {
    var key = campaign.channel === 'pieseauto' ? 'pieseauto_ad' : 'fb_post';
    var tpl = CONTENT_TPL[key] || CONTENT_TPL.fb_post;
    var body = tpl.body.replace('[OEM]', oem || '[OEM]').replace('[Titlu campanie]', campaign.title);
    var now = new Date().toISOString();
    return {
      id: newId('cnt'),
      title: 'Conținut: ' + campaign.title,
      platform: tpl.platform,
      format: tpl.format,
      status: 'draft',
      publishDate: '',
      campaignId: campaign.id,
      channel: campaign.channel || 'site',
      body: body,
      templateKey: key,
      createdAt: now,
      updatedAt: now,
    };
  }

  function buildCampaign(opts) {
    var now = new Date().toISOString();
    var pbKey = opts.playbookKey || 'seo_catalog';
    var pb = findPlaybook(pbKey);
    var channel = opts.channel || 'site';
    var kpiMetric = opts.kpiMetric || defaultKpiForChannel(channel);
    var current = Number(opts.currentValue != null ? opts.currentValue : (opts.current || 0));
    var target = Number(opts.targetValue != null ? opts.targetValue : (opts.target || 100));
    return {
      id: newId('cmp'),
      title: opts.title || 'Campanie nouă',
      problemType: opts.problemType || 'traffic',
      channel: channel,
      status: opts.status || 'identified',
      problemSummary: opts.problemSummary || opts.summary || '',
      focusOem: opts.focusOem || null,
      baselineValue: Number(opts.baselineValue != null ? opts.baselineValue : current),
      targetValue: target,
      currentValue: current,
      kpiMetric: kpiMetric,
      playbookKey: pbKey,
      checklist: defaultChecklist(pbKey),
      evidenceBefore: { captured_at: now, note: 'Snapshot la crearea campaniei' },
      evidenceAfter: {},
      priorityScore: Math.round(Math.abs(target - current) / Math.max(1, (pb && pb.effort) || 3)),
      isFocus: opts.isFocus !== false,
      notes: opts.notes || (pb && pb.notes) || '',
      createdAt: now,
      updatedAt: now,
    };
  }

  function addCampaignBundle(campaign, withSpawn) {
    if (!canAddFocusCampaign()) {
      toast('Săptămâna de foc: max ' + state.focusLimit + ' campanii active.', true);
      return false;
    }
    state.campaigns.push(campaign);
    if (withSpawn !== false) {
      var pb = findPlaybook(campaign.playbookKey);
      var act = spawnAction(campaign, pb);
      act.score = act.impact * 10 - act.effort;
      state.actions.push(act);
      state.content.push(spawnContent(campaign, campaign.focusOem));
    }
    state.selectedCampaignId = campaign.id;
    return true;
  }

  function createManualCampaign(form, status) {
    var campaign = buildCampaign({
      title: form.title,
      channel: form.channel,
      problemSummary: form.problemSummary,
      kpiMetric: form.kpiMetric,
      currentValue: form.currentValue,
      targetValue: form.targetValue,
      playbookKey: form.playbookKey,
      focusOem: form.focusOem,
      status: status || 'identified',
      isFocus: form.isFocus === '1' || form.isFocus === 'on',
    });
    if (!addCampaignBundle(campaign, true)) return;
    saveAll().then(function () {
      toast('Campanie adăugată în Plan');
      setTab('plan');
    });
  }

  function createFromPlaybook(key) {
    var pb = findPlaybook(key);
    if (!pb) {
      toast('Playbook indisponibil', true);
      return;
    }
    var channel = key === 'pieseauto_refresh' ? 'pieseauto' : (key === 'fb_weekly' || key === 'whatsapp_followup' ? 'facebook' : 'site');
    createManualCampaign({
      title: pb.title,
      channel: channel,
      problemSummary: pb.notes || '',
      kpiMetric: defaultKpiForChannel(channel),
      currentValue: 0,
      targetValue: 100,
      playbookKey: key,
    });
  }

  function deleteCampaign(id) {
    if (!window.confirm('Ștergi campania și acțiunile/conținutul legate?')) return;
    state.campaigns = state.campaigns.filter(function (c) { return c.id !== id; });
    state.actions = state.actions.filter(function (a) { return a.campaignId !== id; });
    state.content = state.content.filter(function (c) { return c.campaignId !== id; });
    if (state.selectedCampaignId === id) state.selectedCampaignId = null;
    saveAll();
    toast('Campanie ștearsă');
  }

  function updateCampaign(campaignId, form) {
    var c = state.campaigns.find(function (x) { return x.id === campaignId; });
    if (!c) return;
    c.title = String(form.title || '').trim();
    c.channel = form.channel || c.channel;
    c.problemSummary = form.problemSummary || '';
    c.kpiMetric = form.kpiMetric || defaultKpiForChannel(c.channel);
    c.currentValue = Number(form.currentValue) || 0;
    c.targetValue = Number(form.targetValue) || 0;
    c.focusOem = form.focusOem || null;
    c.status = form.status || c.status;
    c.playbookKey = form.playbookKey || c.playbookKey;
    c.notes = form.notes || c.notes || '';
    c.isFocus = form.isFocus === '1' || form.isFocus === 'on' || form.isFocus === true;
    c.updatedAt = new Date().toISOString();
    if (c.status === 'in_progress' && !c.startedAt) c.startedAt = c.updatedAt;
    saveAll().then(function () {
      toast('Campanie actualizată');
      selectCampaign(c.id, { syncChannel: false });
    });
  }

  function openModal(type, payload) {
    modalState = { type: type, payload: payload || {} };
    var overlay = $('bmh-overlay');
    var title = $('bmh-modal-title');
    var body = $('bmh-modal-body');
    if (!overlay || !title || !body) return;

    if (type === 'campaign') {
      var editCamp = payload.campaign || null;
      title.textContent = editCamp ? 'Editează campania' : 'Campanie nouă — plan ofensiv';
      var pbList = state.playbooks.length ? state.playbooks : [
        { key: 'seo_catalog', title: 'SEO catalog — OEM negăsite' },
        { key: 'fb_weekly', title: 'Postări Facebook' },
        { key: 'pieseauto_refresh', title: 'Refresh PieseAuto.ro' },
        { key: 'blog_oem', title: 'Articol blog OEM' },
        { key: 'whatsapp_followup', title: 'Follow-up WhatsApp' },
      ];
      var pbOpts = pbList.map(function (p) {
        var sel = editCamp && editCamp.playbookKey === p.key ? ' selected' : '';
        return '<option value="' + esc(p.key) + '"' + sel + '>' + esc(p.title) + '</option>';
      }).join('');
      var chOpts = CHANNELS.map(function (c) {
        var sel = editCamp && editCamp.channel === c.id ? ' selected' : '';
        return '<option value="' + esc(c.id) + '"' + sel + '>' + esc(c.label) + '</option>';
      }).join('');
      var kpiOpts = Object.keys(KPI_METRICS).map(function (k) {
        var sel = editCamp && (editCamp.kpiMetric || defaultKpiForChannel(editCamp.channel)) === k ? ' selected' : '';
        return '<option value="' + k + '"' + sel + '>' + esc(KPI_METRICS[k]) + '</option>';
      }).join('');
      var statusOpts = KANBAN.map(function (k) {
        var sel = editCamp && editCamp.status === k.id ? ' selected' : '';
        return '<option value="' + k.id + '"' + sel + '>' + esc(k.label) + '</option>';
      }).join('');
      body.innerHTML =
        '<form class="bmh-form" id="bmh-form-campaign">' +
        '<label>Titlu campanie *<input name="title" required placeholder="ex: Ofensivă OEM frâne BMW" value="' + esc(editCamp ? editCamp.title : '') + '"></label>' +
        '<label>Canal<select name="channel" id="bmh-form-channel">' + chOpts + '</select></label>' +
        '<label>Playbook / rețetă<select name="playbookKey">' + pbOpts + '</select></label>' +
        '<label>Obiectiv / problemă<textarea name="problemSummary" placeholder="Ce vrei să rezolvi?">' + esc(editCamp ? editCamp.problemSummary : '') + '</textarea></label>' +
        '<label>KPI principal<select name="kpiMetric" id="bmh-form-kpi">' + kpiOpts + '</select>' +
        '<span class="bmh-form-hint">Vizitatori, trafic, lead-uri, conversii — nu RON</span></label>' +
        '<div class="bmh-form-row">' +
        '<label>Valoare acum<input name="currentValue" type="number" min="0" step="1" value="' + esc(editCamp ? editCamp.currentValue : 0) + '"></label>' +
        '<label>Țintă<input name="targetValue" type="number" min="0" step="1" value="' + esc(editCamp ? editCamp.targetValue : 100) + '"></label>' +
        '</div>' +
        '<label>OEM focus (opțional)<input name="focusOem" placeholder="ex: 34116761244" value="' + esc(editCamp && editCamp.focusOem ? editCamp.focusOem : '') + '"></label>' +
        '<label>Coloană kanban<select name="status">' + statusOpts + '</select></label>' +
        '<label>Note interne<textarea name="notes" rows="2">' + esc(editCamp ? editCamp.notes || '' : '') + '</textarea></label>' +
        '<label class="bmh-check-item"><input type="checkbox" name="isFocus" value="1"' +
        (editCamp ? (editCamp.isFocus ? ' checked' : '') : ' checked') + '> Săptămâna de foc (prioritar)</label>' +
        '</form>';
      var chSel = document.getElementById('bmh-form-channel');
      var kpiSel = document.getElementById('bmh-form-kpi');
      if (chSel && kpiSel && !editCamp) {
        chSel.addEventListener('change', function () {
          kpiSel.value = defaultKpiForChannel(chSel.value);
        });
      }
    } else if (type === 'indicator') {
      var editInd = payload.indicator || null;
      title.textContent = editInd ? 'Editează indicator' : 'Indicator platformă nou';
      var chOpts2 = CHANNELS.map(function (c) {
        var sel = editInd && indicatorChannel(editInd) === c.id ? ' selected' : '';
        return '<option value="' + esc(c.id) + '"' + sel + '>' + esc(c.label) + '</option>';
      }).join('');
      body.innerHTML =
        '<form class="bmh-form" id="bmh-form-indicator">' +
        '<label>Canal / platformă<select name="category" id="bmh-form-ind-channel">' + chOpts2 + '</select></label>' +
        '<label>Nume platformă<input name="platform" required placeholder="ex: PieseAuto.ro" value="' + esc(editInd ? editInd.platform : '') + '"></label>' +
        '<label>Metrică *<input name="metric" required placeholder="ex: Listări active" value="' + esc(editInd ? editInd.metric : '') + '"></label>' +
        '<div class="bmh-form-row">' +
        '<label>Valoare acum<input name="value" type="number" min="0" step="1" value="' + esc(editInd ? editInd.value : 0) + '"></label>' +
        '<label>Țintă<input name="target" type="number" min="0" step="1" value="' + esc(editInd ? editInd.target : 100) + '"></label>' +
        '</div>' +
        '<label>URL referință<input name="url" placeholder="https://…" value="' + esc(editInd ? editInd.url || '' : '') + '"></label>' +
        '<label>Note<textarea name="notes" rows="2">' + esc(editInd ? editInd.notes || '' : '') + '</textarea></label>' +
        '</form>';
    } else if (type === 'action') {
      var campOptsA = '<option value="">— Alege campanie —</option>' + state.campaigns.map(function (c) {
        var sel = state.selectedCampaignId === c.id ? ' selected' : '';
        return '<option value="' + esc(c.id) + '"' + sel + '>' + esc(c.title) + '</option>';
      }).join('');
      title.textContent = 'Acțiune nouă';
      body.innerHTML =
        '<form class="bmh-form" id="bmh-form-action">' +
        '<label>Campanie<select name="campaignId">' + campOptsA + '</select></label>' +
        '<label>Titlu acțiune *<input name="title" required placeholder="ex: Publică 10 anunțuri PieseAuto.ro"></label>' +
        '<label>Prioritate<select name="priority"><option value="high">Ridicată</option><option value="medium">Medie</option><option value="low">Scăzută</option></select></label>' +
        '<label>Efort (1-5)<input name="effort" type="number" min="1" max="5" value="3"></label>' +
        '<label>Impact acțiune (1-5)<input name="impact" type="number" min="1" max="5" value="4">' +
        '<span class="bmh-form-hint">Cât de mult ajută acțiunea la KPI (nu RON)</span></label>' +
        '<label>Note<textarea name="notes"></textarea></label>' +
        '</form>';
    } else if (type === 'content') {
      var campOpts = '<option value="">— Fără campanie —</option>' + state.campaigns.map(function (c) {
        var sel = state.selectedCampaignId === c.id ? ' selected' : '';
        return '<option value="' + esc(c.id) + '"' + sel + '>' + esc(c.title) + '</option>';
      }).join('');
      title.textContent = 'Conținut / postare nouă';
      body.innerHTML =
        '<form class="bmh-form" id="bmh-form-content">' +
        '<label>Campanie (opțional)<select name="campaignId">' + campOpts + '</select></label>' +
        '<label>Titlu *<input name="title" required placeholder="ex: Post Facebook — filtre ulei"></label>' +
        '<label>Platformă<input name="platform" placeholder="Facebook, PieseAuto.ro, Blog…"></label>' +
        '<label>Data publicare<input name="publishDate" type="date"></label>' +
        '<label>Text draft<textarea name="body" rows="6" placeholder="Textul postării sau anunțului"></textarea></label>' +
        '</form>';
    } else if (type === 'studio') {
      var editS = (payload && payload.item) ? normalizeContentItem(payload.item) : null;
      var platOpts = STUDIO_PLATFORMS.filter(function (p) { return p.id !== 'all'; }).map(function (p) {
        var sel = editS && (editS.channel === p.id || String(editS.platform).toLowerCase().indexOf(p.id) !== -1) ? ' selected' : '';
        return '<option value="' + p.id + '"' + sel + '>' + esc(p.label) + '</option>';
      }).join('');
      title.textContent = editS ? 'Editează material' : 'Material nou — Studio';
      body.innerHTML =
        '<form class="bmh-form" id="bmh-form-studio">' +
        '<label>Titlu *<input name="title" required value="' + esc(editS ? editS.title : '') + '"></label>' +
        '<div class="bmh-form-row">' +
        '<label>Platformă<select name="channel">' + platOpts + '</select></label>' +
        '<label>Format<select name="format">' +
        '<option value="post"' + (editS && editS.format === 'post' ? ' selected' : '') + '>Post imagine</option>' +
        '<option value="video"' + (editS && editS.format === 'video' ? ' selected' : '') + '>Video / Reel</option>' +
        '<option value="article"' + (editS && editS.format === 'article' ? ' selected' : '') + '>Articol blog</option>' +
        '<option value="story"' + (editS && editS.format === 'story' ? ' selected' : '') + '>Story</option>' +
        '<option value="carousel"' + (editS && editS.format === 'carousel' ? ' selected' : '') + '>Carousel</option>' +
        '</select></label></div>' +
        '<label>Text / caption<textarea name="body" rows="5">' + esc(editS ? editS.body : '') + '</textarea></label>' +
        '<label>Hashtag-uri<input name="hashtags" placeholder="#pieseauto #bmw #atelier" value="' + esc(editS ? editS.hashtags : '') + '"></label>' +
        '<label>URL imagine / video (una per linie)<textarea name="mediaUrls" rows="2" placeholder="https://…">' + esc(editS && editS.mediaUrls ? editS.mediaUrls.join('\n') : '') + '</textarea></label>' +
        '<div class="bmh-form-row">' +
        '<label>Data publicare<input name="publishDate" type="date" value="' + esc(editS && editS.publishDate ? String(editS.publishDate).slice(0, 10) : (payload && payload.publishDate ? payload.publishDate : '')) + '"></label>' +
        '<label>Status<select name="status">' +
        '<option value="draft"' + (editS && editS.status === 'draft' ? ' selected' : '') + '>Draft</option>' +
        '<option value="scheduled"' + (editS && editS.status === 'scheduled' ? ' selected' : '') + '>Programat</option>' +
        '<option value="published"' + (editS && editS.status === 'published' ? ' selected' : '') + '>Publicat</option>' +
        '</select></label></div>' +
        '</form>';
      modalState.payload = { item: editS };
    } else if (type === 'paid_ad') {
      var editP = (payload && payload.item) ? normalizeContentItem(payload.item) : null;
      title.textContent = editP ? 'Editează campanie ads' : 'Campanie reclame plătite';
      body.innerHTML =
        '<form class="bmh-form" id="bmh-form-paid">' +
        '<label>Nume campanie *<input name="title" required value="' + esc(editP ? editP.title : '') + '"></label>' +
        '<label>Platformă<select name="channel">' +
        ['facebook', 'instagram', 'google', 'tiktok', 'youtube'].map(function (ch) {
          return '<option value="' + ch + '"' + (editP && editP.channel === ch ? ' selected' : '') + '>' + esc(CHANNEL_LABELS[ch] || ch) + ' Ads</option>';
        }).join('') +
        '</select></label>' +
        '<div class="bmh-form-row">' +
        '<label>Cheltuieli (RON)<input name="spend" type="number" min="0" step="1" value="' + esc(editP ? editP.spend : 0) + '"></label>' +
        '<label>Venit estimat (RON)<input name="revenue" type="number" min="0" step="1" value="' + esc(editP ? editP.revenue : 0) + '"></label></div>' +
        '<div class="bmh-form-row">' +
        '<label>Impresii<input name="impressions" type="number" min="0" value="' + esc(editP ? editP.impressions : 0) + '"></label>' +
        '<label>Click-uri<input name="clicks" type="number" min="0" value="' + esc(editP ? editP.clicks : 0) + '"></label></div>' +
        '<label>Lead-uri<input name="leads" type="number" min="0" value="' + esc(editP ? editP.leads : 0) + '"></label>' +
        '<label>Perioadă (start)<input name="publishDate" type="date" value="' + esc(editP && editP.publishDate ? String(editP.publishDate).slice(0, 10) : '') + '"></label>' +
        '<label>Note<textarea name="body" rows="2">' + esc(editP ? editP.body : '') + '</textarea></label>' +
        '</form>';
      modalState.payload = { item: editP };
    } else if (type === 'checklist') {
      title.textContent = 'Pas nou în checklist';
      body.innerHTML =
        '<form class="bmh-form" id="bmh-form-checklist">' +
        '<label>Descriere pas *<input name="label" required placeholder="ex: Verifică stoc furnizor"></label>' +
        '</form>';
    }

    overlay.classList.remove('hidden');
  }

  function closeModal() {
    modalState = { type: null, payload: {} };
    var overlay = $('bmh-overlay');
    if (overlay) overlay.classList.add('hidden');
  }

  function readForm(formId) {
    var form = document.getElementById(formId);
    if (!form) return {};
    var data = {};
    Array.prototype.forEach.call(form.elements, function (el) {
      if (!el.name) return;
      data[el.name] = el.value;
    });
    return data;
  }

  function handleModalSave() {
    var type = modalState.type;
    if (type === 'campaign') {
      var f = readForm('bmh-form-campaign');
      if (!f.title || !String(f.title).trim()) {
        toast('Titlul campaniei e obligatoriu', true);
        return;
      }
      if (modalState.payload && modalState.payload.campaign) {
        updateCampaign(modalState.payload.campaign.id, f);
        closeModal();
        return;
      }
      createManualCampaign(f, f.status);
      closeModal();
      return;
    }
    if (type === 'indicator') {
      var fi = readForm('bmh-form-indicator');
      if (!fi.metric || !fi.platform) {
        toast('Platformă și metrică sunt obligatorii', true);
        return;
      }
      var nowInd = new Date().toISOString();
      if (modalState.payload && modalState.payload.indicator) {
        var ind = state.indicators.find(function (i) { return i.id === modalState.payload.indicator.id; });
        if (ind) {
          ind.platform = fi.platform;
          ind.metric = fi.metric;
          ind.value = Number(fi.value) || 0;
          ind.target = Number(fi.target) || 0;
          ind.url = fi.url || '';
          ind.notes = fi.notes || '';
          ind.category = fi.category || 'site';
          ind.updatedAt = nowInd;
        }
      } else {
        state.indicators.push({
          id: newId('ind'),
          platform: fi.platform,
          metric: fi.metric,
          value: Number(fi.value) || 0,
          target: Number(fi.target) || 0,
          url: fi.url || '',
          notes: fi.notes || '',
          category: fi.category || 'site',
          source: 'manual',
          createdAt: nowInd,
          updatedAt: nowInd,
        });
      }
      closeModal();
      saveAll();
      toast('Indicator salvat');
      return;
    }
    if (type === 'action') {
      var fa = readForm('bmh-form-action');
      if (!fa.title) return;
      var campA = state.campaigns.find(function (c) { return c.id === fa.campaignId; }) || selectedCampaign();
      var nowA = new Date().toISOString();
      var effortA = Math.max(1, Math.min(5, parseInt(fa.effort, 10) || 3));
      var impactA = Math.max(1, Math.min(5, parseInt(fa.impact, 10) || 4));
      state.actions.push({
        id: newId('act'),
        title: fa.title,
        targetKpi: campA && findPlaybook(campA.playbookKey) ? (findPlaybook(campA.playbookKey).targetKpi || '') : '',
        campaignId: campA ? campA.id : (fa.campaignId || null),
        kanbanStep: campA ? campA.status : 'identified',
        deadline: '',
        priority: fa.priority || 'medium',
        status: 'todo',
        effort: effortA,
        impact: impactA,
        notes: fa.notes || '',
        channel: campA ? campA.channel : 'site',
        score: impactA * 10,
        createdAt: nowA,
        updatedAt: nowA,
      });
      closeModal();
      saveAll();
      toast('Acțiune adăugată');
      return;
    }
    if (type === 'content') {
      var fc = readForm('bmh-form-content');
      if (!fc.title) return;
      var campC = state.campaigns.find(function (c) { return c.id === fc.campaignId; }) || selectedCampaign();
      var now2 = new Date().toISOString();
      state.content.push(normalizeContentItem({
        id: newId('cnt'),
        title: fc.title,
        platform: fc.platform || (campC ? (CHANNEL_LABELS[campC.channel] || campC.channel) : 'Manual'),
        format: 'post',
        status: fc.publishDate ? 'scheduled' : 'draft',
        publishDate: fc.publishDate || '',
        campaignId: campC ? campC.id : (fc.campaignId || null),
        channel: campC ? campC.channel : 'site',
        body: fc.body || '',
        createdAt: now2,
        updatedAt: now2,
      }));
      closeModal();
      saveAll();
      toast('Conținut adăugat');
      return;
    }
    if (type === 'studio') {
      var fs = readForm('bmh-form-studio');
      if (!fs.title) return;
      var chMap = { blog: 'Blog', facebook: 'Facebook', instagram: 'Instagram', tiktok: 'TikTok', youtube: 'YouTube', pieseauto: 'PieseAuto.ro' };
      var mediaLines = (fs.mediaUrls || '').split(/\n/).map(function (s) { return s.trim(); }).filter(Boolean);
      var nowS = new Date().toISOString();
      var fmtType = fs.format || 'post';
      if (modalState.payload && modalState.payload.item) {
        var ex = modalState.payload.item;
        Object.assign(ex, {
          title: fs.title,
          platform: chMap[fs.channel] || fs.channel,
          channel: fs.channel,
          format: fmtType,
          body: fs.body || '',
          hashtags: fs.hashtags || '',
          mediaUrls: mediaLines,
          mediaType: fmtType === 'video' ? 'video' : (mediaLines.length ? 'image' : 'none'),
          publishDate: fs.publishDate || '',
          status: fs.status || 'draft',
          updatedAt: nowS,
        });
      } else {
        state.content.push(normalizeContentItem({
          id: newId('cnt'),
          title: fs.title,
          platform: chMap[fs.channel] || fs.channel,
          channel: fs.channel,
          format: fmtType,
          status: fs.status || (fs.publishDate ? 'scheduled' : 'draft'),
          publishDate: fs.publishDate || '',
          campaignId: state.selectedCampaignId,
          body: fs.body || '',
          hashtags: fs.hashtags || '',
          mediaUrls: mediaLines,
          mediaType: fmtType === 'video' ? 'video' : (mediaLines.length ? 'image' : 'none'),
          createdAt: nowS,
          updatedAt: nowS,
        }));
      }
      closeModal();
      saveAll();
      toast('Material salvat');
      return;
    }
    if (type === 'paid_ad') {
      var fp = readForm('bmh-form-paid');
      if (!fp.title) return;
      var nowP = new Date().toISOString();
      var platLabel = (CHANNEL_LABELS[fp.channel] || fp.channel) + ' Ads';
      if (modalState.payload && modalState.payload.item) {
        var exp = modalState.payload.item;
        Object.assign(exp, {
          title: fp.title,
          platform: platLabel,
          channel: fp.channel,
          spend: Number(fp.spend || 0),
          revenue: Number(fp.revenue || 0),
          impressions: Number(fp.impressions || 0),
          clicks: Number(fp.clicks || 0),
          leads: Number(fp.leads || 0),
          publishDate: fp.publishDate || '',
          body: fp.body || '',
          updatedAt: nowP,
        });
      } else {
        state.content.push(normalizeContentItem({
          id: newId('ads'),
          title: fp.title,
          platform: platLabel,
          format: 'paid_ad',
          status: 'active',
          channel: fp.channel,
          spend: Number(fp.spend || 0),
          revenue: Number(fp.revenue || 0),
          impressions: Number(fp.impressions || 0),
          clicks: Number(fp.clicks || 0),
          leads: Number(fp.leads || 0),
          publishDate: fp.publishDate || '',
          body: fp.body || '',
          createdAt: nowP,
          updatedAt: nowP,
        }));
      }
      closeModal();
      saveAll();
      toast('Campanie ads salvată');
      return;
    }
    if (type === 'checklist') {
      var c = selectedCampaign();
      if (!c) return;
      var fl = readForm('bmh-form-checklist');
      if (!fl.label) return;
      if (!c.checklist) c.checklist = [];
      c.checklist.push({ id: newId('ck'), label: fl.label, done: false });
      closeModal();
      saveAll();
      toast('Pas adăugat în checklist');
    }
  }

  function createCampaignFromProblem(problemId) {
    var p = state.problems.find(function (x) { return x.id === problemId; });
    if (!p) return;
    if (state.focusMode) {
      var active = state.campaigns.filter(function (c) { return c.isFocus && c.status !== 'done'; }).length;
      if (active >= state.focusLimit) {
        toast('Săptămâna de foc: max ' + state.focusLimit + ' campanii. Închide una sau dezactivează modul.', true);
        return;
      }
    }
    apiPost({ action: 'create_campaign', problem: p }).then(function (j) {
      if (!j.success) throw new Error(j.message || 'Eroare');
      applyData(j.data);
      if (j.data && j.data.campaigns && j.data.campaigns.length) {
        state.selectedCampaignId = j.data.campaigns[j.data.campaigns.length - 1].id;
      }
      toast('Campanie creată');
      setTab('plan');
      render();
    }).catch(function (e) { toast(e.message || 'Eroare', true); });
  }

  function bindEvents() {
    var root = document.getElementById('bmh-app');
    if (!root || root.dataset.bmhBound === '1') return;
    root.dataset.bmhBound = '1';

    function openNewCampaign(status) {
      openModal('campaign', { status: status || 'identified' });
      if (status) {
        setTimeout(function () {
          var sel = document.querySelector('#bmh-form-campaign select[name="status"]');
          if (sel) sel.value = status;
        }, 0);
      }
    }

    root.addEventListener('click', function (e) {
      var tabBtn = e.target.closest('.bmh-tab');
      if (tabBtn && root.contains(tabBtn)) {
        e.preventDefault();
        setTab(tabBtn.getAttribute('data-bmh-tab') || 'result');
        return;
      }

      var chBtn = e.target.closest('.bmh-channel-tabs [data-channel]');
      if (chBtn) {
        e.preventDefault();
        setChannelFilter(chBtn.getAttribute('data-channel') || 'all');
        return;
      }

      var chFix = e.target.closest('[data-bmh-set-channel]');
      if (chFix) {
        e.preventDefault();
        setChannelFilter(chFix.getAttribute('data-bmh-set-channel') || 'all');
        return;
      }

      var execCamp = e.target.closest('[data-bmh-exec-camp]');
      if (execCamp) {
        var ecid = execCamp.getAttribute('data-bmh-exec-camp');
        if (ecid === '__all__') {
          state.execShowAll = true;
          renderExecution();
        } else {
          state.execShowAll = false;
          state.selectedCampaignId = ecid;
          saveLocal();
          renderExecution();
          renderSidebar();
        }
        return;
      }

      if (e.target.closest('#bmh-exec-show-all') || e.target.closest('#bmh-exec-show-all-inline')) {
        state.execShowAll = true;
        setChannelFilter('all');
        return;
      }

      var execSubBtn = e.target.closest('[data-exec-sub]');
      if (execSubBtn && execSubBtn.closest('#bmh-exec-subtabs')) {
        setExecSubTab(execSubBtn.getAttribute('data-exec-sub') || 'pipeline');
        return;
      }

      if (e.target.closest('#bmh-reset-filters')) {
        state.focusMode = false;
        state.execShowAll = true;
        state.selectedCampaignId = null;
        state.channel = 'all';
        var focusEl2 = $('bmh-focus-mode');
        if (focusEl2) focusEl2.checked = false;
        setChannelFilter('all');
        normalizeFilters();
        saveLocal();
        render();
        toast('Filtre resetate — toate campaniile vizibile');
        return;
      }

      if (e.target.closest('#bmh-seed-demo') || e.target.closest('#bmh-seed-demo-inline')) {
        e.preventDefault();
        var hasData = state.campaigns.length > 0 || state.actions.length > 0;
        if (hasData && !window.confirm('Înlocuiești campaniile existente cu setul demo (5 campanii — site în lucru + canale paralele)?')) {
          return;
        }
        apiPost({ action: 'seed_demo', force: hasData }).then(function (j) {
          if (!j.success) throw new Error(j.message);
          applyData(j.data);
          state.execShowAll = true;
          if (state.campaigns.length) {
            selectCampaign(state.selectedCampaignId || state.campaigns[0].id, { syncChannel: false, execShowAll: true });
          } else {
            applyClientDemoFallback();
            render();
          }
          toast(j.message || 'Demo încărcat');
        }).catch(function (err) {
          applyClientDemoFallback();
          state.execShowAll = true;
          render();
          toast(err.message || 'Demo local — API indisponibil', true);
        });
        return;
      }

      if (e.target.closest('#bmh-new-campaign-problems')) {
        openNewCampaign();
        return;
      }
      if (e.target.closest('#bmh-sync-problems')) {
        var syncBtn = $('bmh-sync-live');
        if (syncBtn) syncBtn.click();
        return;
      }
      if (e.target.closest('[data-bmh-trigger-add-action]')) {
        openModal('action');
        return;
      }
      if (e.target.closest('[data-bmh-trigger-add-content]')) {
        openModal('content');
        return;
      }
      if (e.target.closest('[data-bmh-trigger-studio]') || e.target.closest('#bmh-studio-add') || e.target.closest('#bmh-cal-add')) {
        openModal('studio', { publishDate: state.calendarSelectedDay || '' });
        return;
      }
      if (e.target.closest('#bmh-paid-add')) {
        openModal('paid_ad', {});
        return;
      }
      if (e.target.closest('#bmh-cal-prev')) {
        shiftCalendarMonth(-1);
        return;
      }
      if (e.target.closest('#bmh-cal-next')) {
        shiftCalendarMonth(1);
        return;
      }
      var calDay = e.target.closest('[data-bmh-cal-day]');
      if (calDay) {
        state.calendarSelectedDay = calDay.getAttribute('data-bmh-cal-day');
        renderCalendar();
        return;
      }
      var calAddDay = e.target.closest('[data-bmh-cal-add-day]');
      if (calAddDay) {
        openModal('studio', { publishDate: calAddDay.getAttribute('data-bmh-cal-add-day') || '' });
        return;
      }
      var studioFilter = e.target.closest('[data-bmh-studio-filter]');
      if (studioFilter) {
        state.studioFilter = studioFilter.getAttribute('data-bmh-studio-filter') || 'all';
        renderStudio();
        return;
      }
      var editStudio = e.target.closest('[data-bmh-edit-studio]');
      if (editStudio) {
        var sid = editStudio.getAttribute('data-bmh-edit-studio');
        var sit = state.content.find(function (c) { return c.id === sid; });
        if (sit) openModal('studio', { item: sit });
        return;
      }
      var editPaid = e.target.closest('[data-bmh-edit-paid]');
      if (editPaid) {
        var pid = editPaid.getAttribute('data-bmh-edit-paid');
        var pit = state.content.find(function (c) { return c.id === pid; });
        if (pit) openModal('paid_ad', { item: pit });
        return;
      }
      if (e.target.closest('[data-bmh-new-in-col]')) {
        var colBtn = e.target.closest('[data-bmh-new-in-col]');
        openNewCampaign(colBtn ? colBtn.getAttribute('data-bmh-new-in-col') : 'identified');
        return;
      }
      if (e.target.closest('[data-bmh-playbook]')) {
        createFromPlaybook(e.target.closest('[data-bmh-playbook]').getAttribute('data-bmh-playbook'));
        return;
      }
      if (e.target.closest('[data-bmh-add-checklist]')) {
        openModal('checklist');
        return;
      }
      if (e.target.closest('[data-bmh-delete-campaign]')) {
        deleteCampaign(e.target.closest('[data-bmh-delete-campaign]').getAttribute('data-bmh-delete-campaign'));
        return;
      }
      var editCampBtn = e.target.closest('[data-bmh-edit-campaign]');
      if (editCampBtn) {
        var ecid = editCampBtn.getAttribute('data-bmh-edit-campaign');
        var ec = state.campaigns.find(function (c) { return c.id === ecid; });
        if (ec) openModal('campaign', { campaign: ec });
        return;
      }
      var editIndBtn = e.target.closest('[data-bmh-edit-indicator]');
      if (editIndBtn) {
        var iid = editIndBtn.getAttribute('data-bmh-edit-indicator');
        var ei = state.indicators.find(function (i) { return i.id === iid; });
        if (ei) openModal('indicator', { indicator: ei });
        return;
      }
      if (e.target.closest('[data-bmh-new-indicator]')) {
        openModal('indicator', {});
        return;
      }
      var create = e.target.closest('[data-bmh-create-campaign]');
      if (create) {
        createCampaignFromProblem(create.getAttribute('data-bmh-create-campaign'));
        return;
      }
      var selBtn = e.target.closest('[data-bmh-select-campaign]');
      if (selBtn) {
        selectCampaign(selBtn.getAttribute('data-bmh-select-campaign'));
        return;
      }
      var card = e.target.closest('.bmh-kanban-card');
      if (card && !e.target.closest('button')) {
        selectCampaign(card.getAttribute('data-campaign-id'));
        return;
      }
      var adv = e.target.closest('[data-bmh-advance]');
      if (adv) {
        var id = adv.getAttribute('data-bmh-advance');
        var c = state.campaigns.find(function (x) { return x.id === id; });
        if (c) {
          c.status = nextStatus(c.status);
          c.updatedAt = new Date().toISOString();
          if (c.status === 'in_progress' && !c.startedAt) c.startedAt = c.updatedAt;
          if (c.status === 'measured') c.measuredAt = c.updatedAt;
          saveAll();
        }
        return;
      }
      var doneAct = e.target.closest('[data-bmh-done-act]');
      if (doneAct) {
        var aid = doneAct.getAttribute('data-bmh-done-act');
        var act = state.actions.find(function (a) { return a.id === aid; });
        if (act) { act.status = 'done'; act.updatedAt = new Date().toISOString(); saveAll(); }
        return;
      }
      var pubCnt = e.target.closest('[data-bmh-pub-cnt]');
      if (pubCnt) {
        var cid2 = pubCnt.getAttribute('data-bmh-pub-cnt');
        var cnt = state.content.find(function (c) { return c.id === cid2; });
        if (cnt) { cnt.status = 'published'; cnt.updatedAt = new Date().toISOString(); saveAll(); }
        return;
      }
      var cap = e.target.closest('[data-bmh-capture-after]');
      if (cap) {
        var cid3 = cap.getAttribute('data-bmh-capture-after');
        var camp = state.campaigns.find(function (c) { return c.id === cid3; });
        if (camp) {
          camp.evidenceAfter = {
            captured_at: new Date().toISOString(),
            missing_oem: state.result.missing_oem,
            orders_today: state.result.orders_today,
            revenue_today: state.result.revenue_today,
          };
          camp.status = 'measured';
          camp.measuredAt = new Date().toISOString();
          saveAll();
          toast('Dovadă „după” salvată');
        }
        return;
      }
      var goto = e.target.closest('[data-bmh-goto]');
      if (goto) setTab(goto.getAttribute('data-bmh-goto') || 'plan');
    });

    ['bmh-new-campaign-top', 'bmh-new-campaign-plan', 'bmh-new-campaign-sidebar'].forEach(function (id) {
      var el = $(id);
      if (el) el.addEventListener('click', function () { openNewCampaign(); });
    });

    var addAct = $('bmh-add-action');
    if (addAct) addAct.addEventListener('click', function () { openModal('action'); });
    var addCnt = $('bmh-add-content');
    if (addCnt) addCnt.addEventListener('click', function () { openModal('content'); });
    var modalClose = $('bmh-modal-close');
    if (modalClose) modalClose.addEventListener('click', closeModal);
    var modalCancel = $('bmh-modal-cancel');
    if (modalCancel) modalCancel.addEventListener('click', closeModal);
    var modalSave = $('bmh-modal-save');
    if (modalSave) modalSave.addEventListener('click', handleModalSave);
    var overlay = $('bmh-overlay');
    if (overlay) overlay.addEventListener('click', function (e) {
      if (e.target.id === 'bmh-overlay') closeModal();
    });

    var syncLive = $('bmh-sync-live');
    if (syncLive) syncLive.addEventListener('click', function () {
      apiPost({ action: 'sync_live' }).then(function (j) {
        if (!j.success) throw new Error(j.message);
        applyData(j.data);
        toast('Date actualizate');
        render();
      }).catch(function (e) { toast(e.message || 'Eroare sync', true); });
    });

    var applyPlanner = $('bmh-apply-planner');
    if (applyPlanner) applyPlanner.addEventListener('click', applyPlannerToHub);
    var refreshPlanner = $('bmh-refresh-planner');
    if (refreshPlanner) refreshPlanner.addEventListener('click', function () {
      state.plannerLocal = readPlannerLocal();
      renderPlannerNorth();
      toast(state.plannerLocal
        ? 'Planner: ' + fmt(state.plannerLocal.target_users) + ' utilizatori detectați'
        : 'Planner gol — setează scenariul în /admin/planner');
    });

    var focusEl = $('bmh-focus-mode');
    if (focusEl) focusEl.addEventListener('change', function (e) {
      state.focusMode = e.target.checked;
      saveLocal();
      render();
    });

    var sidebarBody = document.getElementById('bmh-sidebar-body');
    if (sidebarBody) sidebarBody.addEventListener('change', function (e) {
      if (e.target.type !== 'checkbox') return;
      var c = selectedCampaign();
      if (!c || !c.checklist) return;
      var id = e.target.getAttribute('data-check-id');
      c.checklist.forEach(function (item) {
        if (item.id === id) item.done = e.target.checked;
      });
      saveAll();
    });
  }

  function init() {
    if (!$('bmh-app')) return;
    loadLocalPrefs();
    var hadLocal = state.campaigns.length > 0;
    var bootstrap = null;
    var cfgEl = document.getElementById('bmh-bootstrap-cfg');
    if (cfgEl) {
      try { bootstrap = JSON.parse(cfgEl.textContent || '{}'); } catch (e) { bootstrap = null; }
    }
    var params = new URLSearchParams(window.location.search);
    var tab = (bootstrap && bootstrap.tab) ? bootstrap.tab : params.get('tab');
    var sub = (bootstrap && bootstrap.sub) ? bootstrap.sub : params.get('sub');
    if (tab === 'calendar' || tab === 'paid' || tab === 'studio') {
      state.execSub = tab;
      state.tab = 'execution';
    } else if (tab) {
      state.tab = tab;
    }
    if (sub && EXEC_SUBTABS.indexOf(sub) !== -1) state.execSub = sub;
    state.plannerLocal = readPlannerLocal();
    var focusEl = $('bmh-focus-mode');
    if (focusEl) focusEl.checked = state.focusMode;
    bindEvents();
    setTab(state.tab);
    if (hadLocal) {
      state.execShowAll = true;
      normalizeFilters();
      render();
    } else {
      renderLoadingShell();
      render();
    }

    fetchWithTimeout(20000).then(function (j) {
      if (!j.success) throw new Error(j.message || 'Răspuns API invalid');
      applyData(j.data);
      state.dataSource = (j.data && j.data.storage) ? String(j.data.storage) : 'server';
      if (j.data && j.data.hub_error) {
        toast('Hub parțial: ' + j.data.hub_error, true);
      }
      if (!state.campaigns.length) {
        var forceSeed = state.actions.length > 0 || state.content.length > 0;
        return apiPost({ action: 'seed_demo', force: forceSeed }).then(function (sj) {
          if (sj.success && sj.data) {
            applyData(sj.data);
            state.dataSource = 'demo';
            if ((sj.data.campaigns || []).length) {
              toast('Conținut test încărcat');
            }
          } else if (!state.campaigns.length) {
            applyClientDemoFallback();
            state.dataSource = 'demo local';
            toast('Conținut test local (API parțial)', true);
          }
        });
      }
    }).then(function () {
      state.apiReady = true;
      if (!state.campaigns.length) {
        applyClientDemoFallback();
        state.dataSource = 'demo local';
      }
      state.execShowAll = true;
      normalizeFilters();
      if (state.tab === 'execution') {
        setChannelFilter('all');
      }
      saveLocal();
      render();
    }).catch(function (e) {
      state.apiReady = true;
      toast('Eroare încărcare — ' + (e.message || 'verifică API'), true);
      if (!state.campaigns.length) {
        applyClientDemoFallback();
        state.dataSource = 'demo local';
      }
      state.execShowAll = true;
      normalizeFilters();
      render();
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
