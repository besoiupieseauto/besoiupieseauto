/**
 * Pagina /admin/alerts — feed + buton Corectează / reparare automată.
 */
(function () {
  'use strict';

  var API = '/admin/api/admin_hub_endpoint.php?action=alerts';
  var POLL_MS = 30000;
  var root = document.getElementById('admin-alerts-root');
  if (!root) return;

  function refresh() {
    return fetch(API, { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (json) {
        if (json && json.success && json.data) apply(json.data);
      })
      .catch(function () {
        showToast('Nu am putut încărca alertele.', true);
      });
  }

  function escapeHtml(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];
    });
  }

  function formatTime(iso) {
    if (!iso) return '';
    try {
      var d = new Date(iso);
      if (isNaN(d.getTime())) return '';
      return d.toLocaleString('ro-RO', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });
    } catch (e) {
      return '';
    }
  }

  function showToast(msg, isError) {
    var toast = document.getElementById('alerts-toast');
    if (!toast) return;
    toast.textContent = msg;
    toast.classList.remove('hidden', 'text-danger', 'border-emerald-300', 'bg-emerald-50');
    if (isError) {
      toast.classList.add('text-danger');
    } else {
      toast.classList.add('border-emerald-300', 'bg-emerald-50');
    }
    setTimeout(function () { toast.classList.add('hidden'); }, 5000);
  }

  function levelClass(level) {
    if (level === 'critical') return 'admin-alert-card--critical';
    if (level === 'info') return 'admin-alert-card--info';
    return 'admin-alert-card--warning';
  }

  function cardPayload(item) {
    if (window.BesoiuAlertFix && window.BesoiuAlertFix.encodePayload) {
      return window.BesoiuAlertFix.encodePayload(item);
    }
    return JSON.stringify({
      code: item.code || '',
      fix_action: item.fix_action || '',
      error_id: item.error_id || 0,
      channel: item.channel || '',
      job_id: item.job_id || '',
      entity_type: item.entity_type || '',
      entity_id: item.entity_id || '',
      title: item.title || '',
      problem: item.problem || '',
      detail: item.detail || '',
      url: item.url || '/admin/alerts',
      level: item.level || 'warning',
      fixable: !!item.fixable,
      fix_label: item.fix_label || 'Corectează',
      fix_guide: item.fix_guide || {},
      steps: item.steps || [],
    }).replace(/"/g, '&quot;');
  }

  function renderCard(item) {
    var url = item.url || '/admin/alerts';
    var lbl = item.action_label || 'Deschide';
    var lvl = levelClass(item.level);
    var problem = item.problem || item.title || '';
    var detail = item.detail || '';
    var code = item.code || '';
    var time = formatTime(item.at);
    var fixBtn = '';

    if (item.fixable) {
      fixBtn = '<button type="button" class="besoiu-red-flag__btn admin-alert-card__fix" '
        + 'data-alert-fix="' + cardPayload(item) + '">'
        + escapeHtml(item.fix_label || 'Corectează') + '</button>';
    }

    return '<article class="admin-alert-card ' + lvl + '" data-alert-code="' + escapeHtml(code) + '">'
      + '<div class="admin-alert-card__head">'
      + '<h3 class="admin-alert-card__title">' + escapeHtml(item.title || 'Alertă') + '</h3>'
      + (code ? '<span class="admin-alert-card__code">' + escapeHtml(code) + '</span>' : '')
      + '</div>'
      + '<p class="admin-alert-card__problem">' + escapeHtml(problem) + '</p>'
      + (detail && detail !== problem ? '<p class="admin-alert-card__detail">' + escapeHtml(detail) + '</p>' : '')
      + '<div class="admin-alert-card__foot">'
      + (time ? '<span class="admin-alert-card__time">' + escapeHtml(time) + '</span>' : '<span></span>')
      + '<div class="admin-alert-card__actions">'
      + fixBtn
      + '<a href="' + escapeHtml(url) + '" class="besoiu-red-flag__btn besoiu-red-flag__btn--ghost">' + escapeHtml(lbl) + ' →</a>'
      + '</div></div>'
      + '</article>';
  }

  function renderList(el, items) {
    if (!el) return;
    if (!items || !items.length) {
      el.innerHTML = '';
      return;
    }
    el.innerHTML = items.map(renderCard).join('');
  }

  function setHidden(el, hide) {
    if (!el) return;
    el.classList.toggle('hidden', !!hide);
  }

  function apply(data) {
    var loading = document.getElementById('alerts-loading');
    setHidden(loading, true);

    var status = data.status || 'ok';
    var badge = document.getElementById('alerts-status-badge');
    if (badge) {
      badge.classList.remove('is-critical', 'is-warning', 'is-ok');
      badge.classList.add('is-' + (status === 'ok' ? 'ok' : status));
      if (status === 'critical') {
        badge.textContent = 'Sistem cu probleme critice';
      } else if (status === 'warning') {
        badge.textContent = 'Necesită atenție';
      } else {
        badge.textContent = 'Sistem OK';
      }
    }

    var critCount = data.critical_count || 0;
    var warnCount = data.warning_count || 0;

    document.getElementById('kpi-critical').textContent = String(critCount);
    document.getElementById('kpi-warning').textContent = String(warnCount);
    document.getElementById('kpi-total').textContent = String(data.total_count || 0);

    var critTag = document.getElementById('alerts-critical-tag');
    var warnTag = document.getElementById('alerts-warning-tag');
    var infoTag = document.getElementById('alerts-info-tag');
    if (critTag) critTag.textContent = String(critCount);
    if (warnTag) warnTag.textContent = String(warnCount);
    if (infoTag) infoTag.textContent = String(data.info_count || 0);

    var groups = data.groups || {};
    var critical = groups.critical || [];
    var warning = groups.warning || [];
    var info = groups.info || [];

    var okBanner = document.getElementById('alerts-ok-banner');
    var hasAny = critical.length + warning.length + info.length > 0;
    setHidden(okBanner, hasAny);

    setHidden(document.getElementById('alerts-section-critical'), !critical.length);
    setHidden(document.getElementById('alerts-section-warning'), !warning.length);
    setHidden(document.getElementById('alerts-section-info'), !info.length);

    renderList(document.getElementById('alerts-list-critical'), critical);
    renderList(document.getElementById('alerts-list-warning'), warning);
    renderList(document.getElementById('alerts-list-info'), info);

    var checked = document.getElementById('alerts-checked-at');
    if (checked) {
      var t = formatTime(data.checked_at);
      checked.textContent = t ? 'Ultima verificare: ' + t : '';
    }

    if (window.BesoiuOpsAlerts && typeof window.BesoiuOpsAlerts.apply === 'function') {
      window.BesoiuOpsAlerts.apply({
        bell_count: data.bell_count,
        critical_count: data.critical_count,
        items: data.items,
        ai_key_ok: data.ai_key_ok,
        ai_health: data.ai_health
      });
    }
  }

  document.addEventListener('besoiu-alert-fixed', function (ev) {
    var json = ev.detail || {};
    if (json.data) apply(json.data);
    else refresh();
    if (json.fixed) showToast(json.message || 'Reparat — alerta a dispărut.', false);
    else if (json.message && json.success) showToast(json.message, true);
  });

  refresh();
  setInterval(function () {
    if (!fixing) refresh();
  }, POLL_MS);
})();
