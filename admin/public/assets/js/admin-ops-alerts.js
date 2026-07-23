/**
 * Notificări admin — badge + popup clopoțel + banner AI Agent.
 */
(function () {
  'use strict';

  var HUB = '/admin/api/admin_hub_endpoint.php?action=ops_alerts';
  // Poll mai rar — summary e și cache-uit server-side 30s; evităm saturarea workerilor PHP.
  var POLL_MS = 120000;
  var lastData = null;
  var panelOpen = false;

  function escapeHtml(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];
    });
  }

  function setBadge(el, count) {
    if (!el) return;
    if (count > 0) {
      el.textContent = count > 99 ? '99+' : String(count);
      el.classList.remove('hidden');
      el.setAttribute('title', count + ' alerte');
    } else {
      el.classList.add('hidden');
      el.textContent = '0';
    }
  }

  function setDot(el, show) {
    if (!el) return;
    el.classList.toggle('hidden', !show);
  }

  function dedupeItems(items) {
    var seen = {};
    var out = [];
    (items || []).forEach(function (it) {
      if (!it) return;
      var key = String(it.code || it.title || '');
      if (key === '' || seen[key]) return;
      seen[key] = true;
      out.push(it);
    });
    return out;
  }

  function levelClass(level) {
    if (level === 'critical' || level === 'danger') return 'is-critical';
    if (level === 'warning' || level === 'warn') return 'is-warn';
    return 'is-info';
  }

  function renderNotifPanel(data) {
    var list = document.getElementById('besoiu-notif-list');
    var countLbl = document.getElementById('besoiu-notif-count');
    if (!list) return;

    var items = dedupeItems((data && data.items) || []);
    if (countLbl) countLbl.textContent = String(items.length);

    if (!items.length) {
      list.innerHTML = '<li class="besoiu-notif-panel__empty">Nicio alertă activă — totul OK.</li>';
      return;
    }

    list.innerHTML = items.map(function (it) {
      var url = it.url || '/admin/alerts';
      var lvl = levelClass(it.level);
      var line = it.problem || it.detail || '';
      return '<li class="besoiu-notif-item ' + lvl + '">'
        + '<a href="' + escapeHtml(url) + '" class="besoiu-notif-item__link">'
        + '<span class="besoiu-notif-item__title">' + escapeHtml(it.title || 'Alertă') + '</span>'
        + '<span class="besoiu-notif-item__detail">' + escapeHtml(line) + '</span>'
        + '<span class="besoiu-notif-item__action">' + escapeHtml(it.action_label || 'Deschide') + ' →</span>'
        + '</a></li>';
    }).join('');
  }

  function renderAiAgentBanner(data) {
    var box = document.getElementById('ai-agent-ops-alerts');
    if (!box) return;
    var items = dedupeItems((data && data.items) || []).filter(function (it) {
      var code = String(it && it.code || '');
      return code.indexOf('ai_') === 0 || code.indexOf('tecdoc') === 0;
    });
    if (!items.length && data && data.ai_key_ok === false) {
      items = [{ title: 'Cheie AI lipsă', detail: 'Setează GROQ_KEY, OPENAI_KEY sau activează Ollama în admin/.env', level: 'critical', url: '/admin/settings' }];
    }
    if (!items.length) {
      box.classList.add('hidden');
      box.innerHTML = '';
      return;
    }
    var fixEnc = window.BesoiuAlertFix && window.BesoiuAlertFix.encodePayload;
    box.classList.remove('hidden');
    box.innerHTML = '<div class="ai-agent-ops-alerts__head"><strong>⚠ Erori API / tokeni</strong><span class="ai-agent-ops-alerts__hint">One-click fix disponibil</span></div>'
      + items.slice(0, 4).map(function (it) {
        var url = it.url ? ' <a href="' + escapeHtml(it.url) + '" class="ai-agent-ops-alerts__link">Detalii →</a>' : '';
        var fixBtn = (it.fixable && fixEnc)
          ? '<button type="button" class="ai-btn ai-btn--fix ai-btn--xs" data-alert-fix="'
            + fixEnc(it) + '">' + escapeHtml(it.fix_label || 'Corectează') + '</button>'
          : '';
        return '<div class="ai-agent-ops-alerts__item ai-agent-ops-alerts__item--'
          + escapeHtml(it.level || 'critical') + '">'
          + '<div class="ai-agent-ops-alerts__item-head"><strong>' + escapeHtml(it.title || 'Eroare') + '</strong>'
          + fixBtn + '</div>'
          + '<p>' + escapeHtml(it.detail || '') + url + '</p></div>';
      }).join('');
  }

  function closePanel() {
    var panel = document.getElementById('besoiu-notif-panel');
    var btn = document.getElementById('besoiu-topbar-alerts-btn');
    if (!panel) return;
    panel.classList.add('hidden');
    panel.hidden = true;
    panelOpen = false;
    if (btn) btn.setAttribute('aria-expanded', 'false');
  }

  function openPanel() {
    var panel = document.getElementById('besoiu-notif-panel');
    var btn = document.getElementById('besoiu-topbar-alerts-btn');
    if (!panel) return;
    if (lastData) renderNotifPanel(lastData);
    panel.classList.remove('hidden');
    panel.hidden = false;
    panelOpen = true;
    if (btn) btn.setAttribute('aria-expanded', 'true');
  }

  function togglePanel() {
    if (panelOpen) closePanel();
    else openPanel();
  }

  function bindPanel() {
    var btn = document.getElementById('besoiu-topbar-alerts-btn');
    var wrap = document.getElementById('besoiu-notif-wrap');
    if (btn) {
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        togglePanel();
      });
    }
    document.addEventListener('click', function (e) {
      if (!panelOpen || !wrap) return;
      if (!wrap.contains(e.target)) closePanel();
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') closePanel();
    });
  }

  function apply(data) {
    lastData = data;
    var count = Number((data && data.bell_count) || 0);
    setBadge(document.getElementById('besoiu-alert-badge'), count);
    setDot(document.getElementById('besoiu-logo-alert-dot'), count > 0);
    document.querySelectorAll('[data-nav-alert]').forEach(function (dot) {
      setDot(dot, count > 0);
    });
    renderNotifPanel(data);
    renderAiAgentBanner(data);
    if (panelOpen) openPanel();
  }

  function refresh() {
    return fetch(HUB, { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (json) {
        if (json && json.success && json.data) apply(json.data);
      })
      .catch(function () { /* silent */ });
  }

  function init() {
    bindPanel();
    refresh();
    setInterval(refresh, POLL_MS);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  window.BesoiuOpsAlerts = { refresh: refresh, apply: apply, openPanel: openPanel, closePanel: closePanel };
})();
