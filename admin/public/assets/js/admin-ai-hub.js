/**
 * Centru AI Besoiu — 4 tab-uri: Chat · Bibliotecă · Intelligence · Sistem
 */
(function () {
  'use strict';

  var VALID_TABS = ['chat', 'library', 'intelligence', 'system'];
  var VALID_LIB = ['explore', 'add', 'tools'];

  function cfg() {
    var el = document.getElementById('ai-hub-cfg');
    if (!el) return {};
    try { return JSON.parse(el.textContent || '{}'); } catch (e) { return {}; }
  }

  function toast(msg) {
    var el = document.getElementById('ai-hub-toast') || document.getElementById('ollama-toast');
    if (!el) return;
    el.textContent = msg;
    el.classList.remove('hidden');
    setTimeout(function () { el.classList.add('hidden'); }, 4000);
  }

  function switchHubTab(tab) {
    tab = tab || 'chat';
    if (VALID_TABS.indexOf(tab) === -1) tab = 'chat';

    var root = document.getElementById('ai-hub-root');
    if (root) root.setAttribute('data-active-tab', tab);

    document.querySelectorAll('.ai-hub-nav__btn').forEach(function (btn) {
      btn.classList.toggle('is-active', btn.getAttribute('data-hub-tab') === tab);
    });
    document.querySelectorAll('.ai-hub-panel').forEach(function (panel) {
      var on = panel.getAttribute('data-hub-panel') === tab;
      panel.hidden = !on;
      panel.classList.toggle('is-active', on);
    });

    if (history.replaceState) {
      var url = new URL(window.location.href);
      url.searchParams.set('tab', tab);
      history.replaceState(null, '', url.pathname + '?' + url.searchParams.toString());
    }
    requestAnimationFrame(function () {
      document.dispatchEvent(new CustomEvent('ai-hub:tab', { detail: { tab: tab } }));
    });
    if (tab === 'system') {
      loadOverviewStats();
    }
  }

  function switchLibTab(tab) {
    tab = tab || 'explore';
    if (VALID_LIB.indexOf(tab) === -1) tab = 'explore';

    document.querySelectorAll('.ai-hub-subnav__btn').forEach(function (btn) {
      btn.classList.toggle('is-active', btn.getAttribute('data-lib-tab') === tab);
    });
    document.querySelectorAll('.ai-hub-subpanel').forEach(function (panel) {
      var on = panel.getAttribute('data-lib-panel') === tab;
      panel.hidden = !on;
      panel.classList.toggle('is-active', on);
    });

    if (tab === 'explore') {
      var src = document.querySelector('.ai-hub-source-btn.is-active');
      onLibSource((src && src.getAttribute('data-lib-source')) || 'agent');
    }
    if (tab === 'tools') {
      document.dispatchEvent(new CustomEvent('ai-hub:lib-tools'));
    }
    document.dispatchEvent(new CustomEvent('ai-hub:lib-tab', { detail: { tab: tab } }));
  }

  function onLibSource(source) {
    source = source === 'global' ? 'global' : 'agent';
    document.querySelectorAll('.ai-hub-source-btn').forEach(function (btn) {
      btn.classList.toggle('is-active', btn.getAttribute('data-lib-source') === source);
    });
    var agentView = document.getElementById('ai-hub-lib-agent-view');
    var globalView = document.getElementById('ai-hub-lib-global-view');
    if (agentView) agentView.classList.toggle('hidden', source !== 'agent');
    if (globalView) globalView.classList.toggle('hidden', source !== 'global');

    if (source === 'agent' && window.OllamaControl && window.OllamaControl.loadLibrary) {
      window.OllamaControl.loadLibrary();
    }
    if (source === 'global') {
      document.getElementById('ai-rag-lib-browse')?.click();
    }
  }

  function loadOverviewStats() {
    var c = cfg();
    var agentApi = c.agentApi || '';
    var ragApi = c.ragApi || '';
    if (!agentApi || !ragApi) return;

    var csrf = document.querySelector('meta[name="csrf-token"]');
    var headers = { Accept: 'application/json' };
    if (csrf && csrf.content) headers['X-Admin-CSRF'] = csrf.content;

    fetch(agentApi + '?action=agent_ollama_status', { credentials: 'same-origin', headers: headers })
      .then(function (r) { return r.json(); })
      .then(function (json) {
        if (!json.data) return;
        var ready = 0;
        (json.data.agents || []).forEach(function (a) { if (a.ready) ready++; });
        setText('ai-hub-stat-agents', ready + '/4');
        var oll = json.data.ollama || {};
        setText('ai-hub-stat-ollama', oll.ready ? 'Online' : (oll.enabled === false ? 'Dezactivat' : 'Offline'));
      }).catch(function () { setText('ai-hub-stat-ollama', 'Eroare API'); });

    fetch(agentApi + '?action=agent_context_library&slug=agent-produse', { credentials: 'same-origin', headers: headers })
      .then(function (r) { return r.json(); })
      .then(function (json) {
        var s = (json.data && json.data.summary) || {};
        setText('ai-hub-stat-agent-lib', String(s.total || json.data.total_visible || '—'));
      }).catch(function () { /* optional */ });

    fetch(ragApi + '?action=corpus_status', { credentials: 'same-origin', headers: headers })
      .then(function (r) { return r.json(); })
      .then(function (json) {
        setText('ai-hub-stat-corpus', String((json.data && json.data.total) || 0));
      }).catch(function () { /* optional */ });

    fetch(ragApi + '?action=dashboard', { credentials: 'same-origin', headers: headers })
      .then(function (r) { return r.json(); })
      .then(function (json) {
        var alerts = (json.data && json.data.alerts) || [];
        setText('ai-hub-stat-alerts', String(Array.isArray(alerts) ? alerts.length : 0));
      }).catch(function () { /* optional */ });
  }

  function setText(id, val) {
    var el = document.getElementById(id);
    if (el) el.textContent = val;
  }

  function resolveTabFromUrl() {
    var params = new URLSearchParams(window.location.search);
    var hash = (window.location.hash || '').replace('#', '').toLowerCase();
    var tab = params.get('tab') || hash || 'chat';

    if (!params.get('tab') && /\/ai-agent\/?$/.test(window.location.pathname)) {
      tab = 'chat';
    }

    var hashMap = {
      chat: 'chat', supervizor: 'chat', agent: 'chat',
      library: 'library', biblioteca: 'library', rag: 'library',
      learn: 'library', invatare: 'library', train: 'library',
      corpus: 'library', vector: 'library', research: 'library', market: 'library',
      intelligence: 'intelligence', intel: 'intelligence', tracking: 'intelligence', search: 'intelligence',
      system: 'system', audit: 'system', monitor: 'system', sistem: 'system', tehnic: 'system',
      overview: 'chat', conexiune: 'system',
    };
    if (hashMap[tab]) tab = hashMap[tab];

    var libSub = params.get('lib') || '';
    if (['learn', 'invatare', 'train', 'add'].indexOf(hash) !== -1 || libSub === 'add') {
      switchHubTab('library');
      switchLibTab('add');
      return 'library';
    }
    if (['corpus', 'research', 'tools', 'vector'].indexOf(hash) !== -1 || libSub === 'tools') {
      switchHubTab('library');
      switchLibTab('tools');
      return 'library';
    }
    if (['biblioteca', 'library', 'explore'].indexOf(hash) !== -1 || libSub === 'explore') {
      switchHubTab('library');
      switchLibTab('explore');
      return 'library';
    }

    if (VALID_TABS.indexOf(tab) === -1) tab = 'chat';
    return tab;
  }

  function bindEvents() {
    document.querySelectorAll('.ai-hub-nav__btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        switchHubTab(btn.getAttribute('data-hub-tab') || 'chat');
      });
    });

    document.querySelectorAll('.ai-hub-subnav__btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        switchLibTab(btn.getAttribute('data-lib-tab') || 'explore');
      });
    });

    document.querySelectorAll('.ai-hub-source-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        onLibSource(btn.getAttribute('data-lib-source') || 'agent');
      });
    });

    document.getElementById('ai-hub-refresh')?.addEventListener('click', function () {
      loadOverviewStats();
      toast('Reîmprospătat');
      document.dispatchEvent(new CustomEvent('ai-hub:refresh'));
    });

    document.querySelectorAll('.ai-hub-chip').forEach(function (chip) {
      chip.addEventListener('click', function () {
        var q = chip.getAttribute('data-q') || '';
        var qEl = document.getElementById('ai-rag-market-question');
        if (qEl) qEl.value = q;
        if (window.AiRag && typeof window.AiRag.askLibrary === 'function') {
          window.AiRag.askLibrary(q);
          return;
        }
        document.getElementById('ai-rag-market-ask')?.click();
      });
    });
  }

  function init() {
    if (!document.getElementById('ai-hub-root')) return;
    bindEvents();
    var tab = resolveTabFromUrl();
    if (!document.querySelector('.ai-hub-subpanel.is-active[data-lib-panel]')) {
      switchLibTab('explore');
    }
    switchHubTab(tab);
    loadOverviewStats();
  }

  window.AiHub = {
    switchTab: switchHubTab,
    switchLibTab: switchLibTab,
    toast: toast,
    refreshOverview: loadOverviewStats,
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
