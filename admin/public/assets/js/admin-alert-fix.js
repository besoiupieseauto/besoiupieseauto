/**
 * Reparare alerte operaționale — popup ghid + reparare automată (init unic).
 */
(function () {
  'use strict';

  if (window.__besoiuAlertFixInit) {
    return;
  }
  window.__besoiuAlertFixInit = true;

  var fixing = false;
  var modalEl = null;
  var currentPayload = null;

  /** Ghid fallback client — când payload-ul din cache e incomplet */
  var CLIENT_GUIDES = {
    import_failed: {
      situation: 'Un job de import s-a oprit cu eroare.',
      steps: ['Deschide Import (secțiunea job-uri) și citește mesajul job-ului eșuat.', 'Corectează CSV/furnizor.', 'Relansează importul sau curăță job-ul eșuat.'],
      links: [{ label: 'Import — job-uri', url: '/admin/import#job-progress-wrap' }],
      auto_label: 'Curăță job eșuat',
      auto_hint: 'Elimină job-ul din coadă — nu repară fișierul defect.',
    },
    job_blocked: {
      situation: 'Import blocat — rulează fără progres.',
      steps: ['Deschide Import și verifică bara de progres.', 'Oprește job-ul blocat.', 'Relansează cu batch mai mic.'],
      links: [
        { label: 'Import — job-uri', url: '/admin/import#job-progress-wrap' },
        { label: 'Cron Sync', url: '/admin/cron' },
      ],
      auto_label: 'Oprește job blocat',
      auto_hint: 'Anulează job-ul care nu avansează.',
    },
    link_broken: {
      situation: 'Integrare furnizor/bot — test conexiune eșuat.',
      steps: ['Verifică URL și credențiale furnizor.', 'Rulează test conexiune din formular.', 'Apasă reparare automată după fix manual.'],
      links: [{ label: 'Furnizori', url: '/admin/suppliers' }],
      auto_label: 'Retest conexiune',
      auto_hint: 'Testează din server — credențialele trebuie corectate manual dacă eșuează.',
    },
    tecdoc_unified: {
      situation: 'Catalog TecDoc / RapidAPI indisponibil.',
      steps: ['Verifică RAPIDAPI_AUTOPARTS_KEY în Setări.', 'Controlează cota și IP whitelist RapidAPI.', 'Testează din Dashboard sau searchlogs.'],
      links: [{ label: 'Setări', url: '/admin/settings' }, { label: 'Jurnal căutări', url: '/admin/searchlogs' }],
      auto_label: 'Re-probe TecDoc',
      auto_hint: 'Curăță cache local și retestează API-ul.',
    },
    tecdoc_dead: { situation: 'API TecDoc nu răspunde.', steps: ['Verifică cheia RapidAPI.', 'Verifică cotă plan.'], links: [{ label: 'Setări', url: '/admin/settings' }], auto_label: 'Re-probe TecDoc', auto_hint: '' },
    tecdoc_api: { situation: 'API TecDoc nu răspunde.', steps: ['Verifică cheia RapidAPI.', 'Verifică cotă plan.'], links: [{ label: 'Setări', url: '/admin/settings' }], auto_label: 'Re-probe TecDoc', auto_hint: '' },
  };

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];
    });
  }

  function readNavCfg() {
    var el = document.getElementById('besoiu-admin-nav-cfg');
    if (!el) return null;
    try {
      return JSON.parse(el.textContent || '{}');
    } catch (e) {
      return null;
    }
  }

  /** Normalizează URL admin — corectează rute vechi (cron-sync, homepages). */
  function resolveAdminHref(raw) {
    var href = String(raw || '').trim();
    if (!href || href === '#') return '#';
    if (/^(https?:|mailto:|tel:|javascript:)/i.test(href)) return href;

    var cfg = readNavCfg();
    var legacy = (cfg && cfg.legacy) ? cfg.legacy : {
      '/admin/cron-sync': '/admin/cron',
      '/admin/homepages': '/admin/dashboard',
      '/admin/furnizori': '/admin/suppliers',
    };

    var path = href.split('#')[0];
    var hash = href.indexOf('#') >= 0 ? href.slice(href.indexOf('#')) : '';
    if (legacy[path]) {
      path = legacy[path];
    }
    if (path[0] !== '/') {
      path = '/admin/' + path.replace(/^\/+/, '');
    }
    return path + hash;
  }

  function mergeGuide(item) {
    item = item || {};
    var code = String(item.code || '');
    var guide = item.fix_guide || {};
    var fallback = CLIENT_GUIDES[code] || {};
    var steps = Array.isArray(guide.steps) && guide.steps.length
      ? guide.steps
      : (Array.isArray(item.steps) && item.steps.length ? item.steps : (fallback.steps || []));

    return {
      situation: String(guide.situation || fallback.situation || item.problem || item.title || 'Alertă operațională'),
      detail: String(guide.detail || item.detail || ''),
      steps: steps,
      links: (Array.isArray(guide.links) && guide.links.length) ? guide.links : (fallback.links || []),
      auto_label: String(guide.auto_label || fallback.auto_label || 'Reparare automată'),
      auto_hint: String(guide.auto_hint || fallback.auto_hint || ''),
    };
  }

  var DEFAULT_FIX_ACTIONS = {
    import_failed: 'dismiss_import_error',
    job_blocked: 'cancel_blocked_job',
    link_broken: 'test_integration',
    tecdoc_unified: 'refresh_tecdoc',
    tecdoc_dead: 'refresh_tecdoc',
    tecdoc_api: 'refresh_tecdoc',
    tecdoc_ip_invalid: 'refresh_tecdoc',
    ai_api_error: 'clear_ai_error',
  };

  function applyFixDefaults(payload) {
    if (!payload.fix_action && DEFAULT_FIX_ACTIONS[payload.code]) {
      payload.fix_action = DEFAULT_FIX_ACTIONS[payload.code];
    }
    if (!payload.fixable && payload.fix_action) {
      payload.fixable = true;
    }
    return payload;
  }

  function payloadFromItem(item) {
    item = item || {};
    var guide = mergeGuide(item);
    var payload = {
      code: String(item.code || ''),
      fix_action: String(item.fix_action || ''),
      error_id: Number(item.error_id || 0),
      channel: String(item.channel || ''),
      job_id: String(item.job_id || ''),
      entity_type: String(item.entity_type || ''),
      entity_id: String(item.entity_id || ''),
      title: String(item.title || ''),
      problem: String(item.problem || ''),
      detail: String(item.detail || ''),
      url: String(item.url || '/admin/alerts'),
      level: String(item.level || 'warning'),
      fixable: !!item.fixable,
      fix_label: String(item.fix_label || 'Corectează'),
      steps: guide.steps,
      fix_guide: guide,
    };
    return applyFixDefaults(payload);
  }

  function encodePayload(item) {
    try {
      var json = JSON.stringify(payloadFromItem(item));
      return btoa(unescape(encodeURIComponent(json)));
    } catch (e) {
      return '';
    }
  }

  function decodePayload(raw) {
    raw = String(raw || '').trim();
    if (!raw) {
      throw new Error('empty');
    }
    if (/^[A-Za-z0-9+/=]+$/.test(raw) && raw.length > 16) {
      try {
        return JSON.parse(decodeURIComponent(escape(atob(raw))));
      } catch (e) { /* legacy */ }
    }
    return JSON.parse(raw.replace(/&quot;/g, '"'));
  }

  function resolveRequest(payload) {
    var cfgEl = document.getElementById('ai-agent-cfg');
    if (cfgEl) {
      try {
        var cfg = JSON.parse(cfgEl.textContent || '{}');
        if (cfg.api) {
          return { url: cfg.api, useActionInBody: true };
        }
      } catch (e) { /* fallback */ }
    }
    return { url: '/admin/api/admin_hub_endpoint.php?action=ops_alert_fix', useActionInBody: false };
  }

  function levelBadge(level) {
    if (level === 'critical') return '<span class="baf-level baf-level--critical">Critic</span>';
    if (level === 'info') return '<span class="baf-level baf-level--info">Info</span>';
    return '<span class="baf-level baf-level--warn">Atenție</span>';
  }

  function q(sel) {
    return modalEl ? modalEl.querySelector(sel) : null;
  }

  function ensureModal() {
    document.querySelectorAll('#besoiu-alert-fix-modal').forEach(function (el, i) {
      if (i > 0) el.remove();
    });

    var existing = document.getElementById('besoiu-alert-fix-modal');
    if (existing) {
      modalEl = existing;
      return modalEl;
    }

    modalEl = document.createElement('div');
    modalEl.id = 'besoiu-alert-fix-modal';
    modalEl.className = 'besoiu-modal-backdrop hidden';
    modalEl.setAttribute('role', 'dialog');
    modalEl.setAttribute('aria-modal', 'true');
    modalEl.innerHTML =
      '<div class="besoiu-modal baf-modal">'
      + '<div class="besoiu-modal__head baf-modal__head">'
      + '<div class="baf-modal__head-text"><span class="baf-modal-level"></span>'
      + '<h3 class="baf-modal-title">Corectare alertă</h3></div>'
      + '<button type="button" class="besoiu-modal__close baf-modal__close" aria-label="Închide">×</button>'
      + '</div>'
      + '<div class="besoiu-modal__body baf-modal__body">'
      + '<section class="baf-section"><h4>Situația</h4><p class="baf-situation"></p><p class="baf-detail hidden"></p></section>'
      + '<section class="baf-section baf-section--steps"><h4>Ce trebuie să faci</h4><ol class="baf-steps"></ol></section>'
      + '<section class="baf-section baf-section--links hidden"><h4>Linkuri utile</h4><div class="baf-links"></div></section>'
      + '<p class="baf-auto-hint hidden"></p>'
      + '<div class="baf-result hidden" role="status"></div>'
      + '</div>'
      + '<div class="besoiu-modal__foot baf-modal__foot">'
      + '<button type="button" class="ai-btn ai-btn--ghost ai-btn--sm baf-close-btn">Închide</button>'
      + '<a href="/admin/alerts" class="ai-btn ai-btn--outline ai-btn--sm baf-open-page">Deschide pagina →</a>'
      + '<button type="button" class="ai-btn ai-btn--fix ai-btn--sm baf-run-btn">Reparare automată</button>'
      + '</div></div>';

    document.body.appendChild(modalEl);

    modalEl.querySelector('.baf-modal__close').addEventListener('click', closeModal);
    modalEl.querySelector('.baf-close-btn').addEventListener('click', closeModal);
    modalEl.addEventListener('click', function (e) {
      if (e.target === modalEl) closeModal();
    });
    modalEl.querySelector('.baf-run-btn').addEventListener('click', function () {
      if (!currentPayload || !currentPayload.code) {
        showModalResult('Date alertă incomplete — reîncarcă pagina (Ctrl+F5).', true);
        return;
      }
      if (fixing) return;
      var btn = this;
      var oldLabel = btn.textContent;
      btn.disabled = true;
      btn.textContent = 'Composer repară…';
      hideModalResult();

      run(currentPayload, {
        onSuccess: function (msg, json) {
          var detail = buildFixDetailMessage(msg, json);
          showModalResult(detail, false);
          btn.textContent = oldLabel;
          btn.disabled = false;
          setTimeout(function () {
            closeModal();
            toastOk(msg || 'Alerta a dispărut.');
          }, 1400);
        },
        onPartial: function (msg, json) {
          var detail = buildFixDetailMessage(msg, json);
          showModalResult(detail, 'warn');
          btn.textContent = oldLabel;
          btn.disabled = false;
          toastWarn(msg || 'Executat, dar alerta încă apare.');
        },
        onError: function (msg) {
          showModalResult(msg, true);
          btn.textContent = oldLabel;
          btn.disabled = false;
          toastErr(msg);
        },
      }).catch(function (err) {
        showModalResult((err && err.message) || 'Eroare la reparare.', true);
        btn.textContent = oldLabel;
        btn.disabled = false;
      });
    });

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && modalEl && !modalEl.classList.contains('hidden')) {
        closeModal();
      }
    });

    return modalEl;
  }

  function hideModalResult() {
    var el = q('.baf-result');
    if (el) {
      el.textContent = '';
      el.classList.add('hidden');
      el.classList.remove('baf-result--ok', 'baf-result--err', 'baf-result--warn');
    }
  }

  function showModalResult(msg, mode) {
    var el = q('.baf-result');
    if (!el) return;
    el.textContent = msg || (mode === true ? 'Eroare.' : mode === 'warn' ? 'Parțial.' : 'OK.');
    el.classList.remove('hidden', 'baf-result--ok', 'baf-result--err', 'baf-result--warn');
    if (mode === true) {
      el.classList.add('baf-result--err');
    } else if (mode === 'warn') {
      el.classList.add('baf-result--warn');
    } else {
      el.classList.add('baf-result--ok');
    }
  }

  function buildFixDetailMessage(msg, json) {
    var parts = [];
    if (msg) parts.push(msg);
    if (json && json.composer && json.composer.analysis_ro) {
      parts.push(String(json.composer.analysis_ro));
    }
    if (json && json.still_active) {
      parts.push('Alerta încă e în monitor — urmează pașii manuali sau linkurile de mai sus.');
    }
    return parts.join('\n\n');
  }

  function closeModal() {
    if (!modalEl) return;
    modalEl.classList.add('hidden');
    modalEl.hidden = true;
    currentPayload = null;
    document.body.classList.remove('baf-modal-open');
  }

  function openModal(item) {
    ensureModal();
    hideModalResult();
    currentPayload = payloadFromItem(item);
    var guide = currentPayload.fix_guide || {};
    var steps = guide.steps || [];

    var levelEl = q('.baf-modal-level');
    if (levelEl) levelEl.innerHTML = levelBadge(currentPayload.level);

    var titleEl = q('.baf-modal-title');
    if (titleEl) titleEl.textContent = currentPayload.title || 'Corectare alertă';

    var sitEl = q('.baf-situation');
    if (sitEl) sitEl.textContent = guide.situation || currentPayload.problem || '—';

    var detailEl = q('.baf-detail');
    var detail = guide.detail || currentPayload.detail || '';
    if (detailEl) {
      if (detail && detail !== (guide.situation || currentPayload.problem)) {
        detailEl.textContent = detail;
        detailEl.classList.remove('hidden');
      } else {
        detailEl.textContent = '';
        detailEl.classList.add('hidden');
      }
    }

    var stepsEl = q('.baf-steps');
    var stepsSection = q('.baf-section--steps');
    if (stepsEl) {
      stepsEl.innerHTML = (steps.length ? steps : ['Deschide pagina indicată și urmează instrucțiunile.'])
        .map(function (s) { return '<li>' + esc(s) + '</li>'; }).join('');
      if (stepsSection) stepsSection.classList.remove('hidden');
    }

    var linksEl = q('.baf-links');
    var linkSection = q('.baf-section--links');
    var links = guide.links || [];
    if (linksEl && linkSection) {
      if (links.length) {
        linksEl.innerHTML = links.map(function (lnk) {
          var href = resolveAdminHref(lnk.url || '#');
          return '<a href="' + esc(href) + '" class="baf-link" target="_blank" rel="noopener noreferrer">'
            + esc(lnk.label || 'Deschide') + ' →</a>';
        }).join('');
        linkSection.classList.remove('hidden');
      } else {
        linksEl.innerHTML = '';
        linkSection.classList.add('hidden');
      }
    }

    var hintEl = q('.baf-auto-hint');
    if (hintEl) {
      if (guide.auto_hint) {
        hintEl.innerHTML = '<strong>Reparare automată:</strong> ' + esc(guide.auto_hint);
        hintEl.classList.remove('hidden');
      } else {
        hintEl.textContent = '';
        hintEl.classList.add('hidden');
      }
    }

    var openPage = q('.baf-open-page');
    if (openPage) {
      openPage.href = resolveAdminHref(currentPayload.url || '/admin/alerts');
      openPage.target = '_blank';
      openPage.rel = 'noopener noreferrer';
    }

    var runBtn = q('.baf-run-btn');
    if (runBtn) {
      if (currentPayload.fixable && currentPayload.fix_action) {
        runBtn.textContent = guide.auto_label || 'Composer repară';
        runBtn.classList.remove('hidden');
        runBtn.disabled = false;
      } else {
        runBtn.classList.add('hidden');
      }
    }

    modalEl.classList.remove('hidden');
    modalEl.hidden = false;
    document.body.classList.add('baf-modal-open');
    if (runBtn && !runBtn.classList.contains('hidden')) runBtn.focus();
    else if (q('.baf-close-btn')) q('.baf-close-btn').focus();
  }

  function notify(msg, isError) {
    var el = document.getElementById('alerts-toast');
    if (el) {
      el.textContent = msg;
      el.classList.remove('hidden', 'text-danger', 'border-emerald-300', 'bg-emerald-50');
      if (isError) el.classList.add('text-danger');
      else el.classList.add('border-emerald-300', 'bg-emerald-50');
      setTimeout(function () { el.classList.add('hidden'); }, 6000);
      return;
    }
    if (window.aiAgentToast) {
      window.aiAgentToast(msg, isError);
      return;
    }
    if (isError) alert(msg);
  }

  function toastOk(msg) { notify(msg, false); }
  function toastErr(msg) { notify(msg, true); }
  function toastWarn(msg) {
    var el = document.getElementById('alerts-toast');
    if (el) {
      el.textContent = msg;
      el.classList.remove('hidden', 'text-danger', 'border-emerald-300', 'bg-emerald-50', 'border-amber-300', 'bg-amber-50');
      el.classList.add('border-amber-300', 'bg-amber-50');
      setTimeout(function () { el.classList.add('hidden'); }, 8000);
      return;
    }
    if (window.aiAgentToast) {
      window.aiAgentToast(msg, false);
      return;
    }
    alert(msg);
  }

  function run(source, opts) {
    opts = opts || {};
    if (fixing) return Promise.reject(new Error('Reparare în curs…'));

    var payload = (source && source.getAttribute)
      ? (function () {
        try { return decodePayload(source.getAttribute('data-alert-fix')); }
        catch (e) { return null; }
      })()
      : payloadFromItem(source);

    if (!payload || !payload.code) {
      var msg = 'Date alertă invalide.';
      if (opts.onError) opts.onError(msg);
      return Promise.reject(new Error(msg));
    }

    payload = payloadFromItem(payload);
    fixing = true;

    var req = resolveRequest(payload);
    var fixFields = {
      code: payload.code,
      fix_action: payload.fix_action,
      error_id: payload.error_id,
      channel: payload.channel,
      job_id: payload.job_id,
      entity_type: payload.entity_type,
      entity_id: payload.entity_id,
    };
    var body = req.useActionInBody
      ? Object.assign({ action: 'ops_alert_fix' }, fixFields)
      : fixFields;

    return fetch(req.url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body),
    })
      .then(function (r) {
        return r.text().then(function (text) {
          var json = {};
          try { json = text ? JSON.parse(text) : {}; } catch (e) {
            throw new Error('Răspuns invalid de la server.');
          }
          if (!r.ok && !json.message) {
            json.message = 'Eroare server (HTTP ' + r.status + ').';
            json.success = false;
          }
          return json;
        });
      })
      .then(function (json) {
        var feed = json.data || json.ops_alerts || null;

        if (window.BesoiuOpsAlerts && feed && feed.items) {
          window.BesoiuOpsAlerts.apply(feed);
        } else if (window.BesoiuOpsAlerts && json.ops_alerts) {
          window.BesoiuOpsAlerts.apply(json.ops_alerts);
        }

        if (json.fixed) {
          if (opts.onSuccess) opts.onSuccess(json.message || 'Reparat — alerta a dispărut.', json);
        } else if (json.success && (json.partial || json.still_active)) {
          if (opts.onPartial) {
            opts.onPartial(json.message || 'Executat, dar alerta încă apare.', json);
          } else if (opts.onError) {
            opts.onError(json.message || 'Executat, dar alerta încă apare.', json);
          }
        } else if (json.success) {
          if (opts.onPartial) {
            opts.onPartial(json.message || 'Analiză făcută — verifică pașii manuali.', json);
          } else if (opts.onError) {
            opts.onError(json.message || 'Executat, dar problema persistă.', json);
          }
        } else {
          if (opts.onError) opts.onError(json.message || 'Repararea a eșuat.', json);
        }

        if (window.aiCommandCenterRefresh) window.aiCommandCenterRefresh();
        if (window.aiSupervisorLoad) window.aiSupervisorLoad();

        document.dispatchEvent(new CustomEvent('besoiu-alert-fixed', { detail: json }));
        return json;
      })
      .catch(function (err) {
        var msg = (err && err.message) ? err.message : 'Eroare de rețea la reparare.';
        if (opts.onError) opts.onError(msg);
        throw err;
      })
      .finally(function () {
        fixing = false;
      });
  }

  document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-alert-fix]');
    if (!btn) return;
    e.preventDefault();
    e.stopPropagation();
    e.stopImmediatePropagation();
    try {
      openModal(decodePayload(btn.getAttribute('data-alert-fix')));
    } catch (err) {
      toastErr('Date alertă invalide — reîncarcă pagina (Ctrl+F5).');
    }
  }, true);

  window.BesoiuAlertFix = {
    run: run,
    openModal: openModal,
    closeModal: closeModal,
    encodePayload: encodePayload,
    payloadFromItem: payloadFromItem,
    isFixing: function () { return fixing; },
  };
})();
