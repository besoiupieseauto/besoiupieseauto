(function () {
  'use strict';

  function api() {
    var root = document.getElementById('ai-hub-root') || document.getElementById('ai-rag-root');
    if (!root) return '';
    return root.getAttribute('data-rag-api') || root.getAttribute('data-api') || '';
  }

  function esc(s) {
    var d = document.createElement('div');
    d.textContent = s == null ? '' : String(s);
    return d.innerHTML;
  }

  function toast(msg) {
    var el = document.getElementById('ai-hub-toast') || document.getElementById('ai-rag-toast');
    if (!el) return;
    el.textContent = msg;
    el.classList.remove('hidden');
    setTimeout(function () { el.classList.add('hidden'); }, 4000);
  }

  function csrfHeader() {
    var m = document.querySelector('meta[name="csrf-token"]');
    var h = { 'Content-Type': 'application/json', Accept: 'application/json' };
    if (m && m.content) h['X-Admin-CSRF'] = m.content;
    return h;
  }

  function fetchJson(url, options, timeoutMs) {
    options = options || {};
    timeoutMs = timeoutMs || 60000;
    var controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
    var timer = controller ? setTimeout(function () { controller.abort(); }, timeoutMs) : null;
    var opts = {
      credentials: 'same-origin',
      headers: csrfHeader(),
      signal: controller ? controller.signal : undefined,
    };
    if (options.method) opts.method = options.method;
    if (options.body) opts.body = options.body;
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
    }).finally(function () {
      if (timer) clearTimeout(timer);
    });
  }

  function get(action, params) {
    var q = new URLSearchParams(params || {});
    q.set('action', action);
    return fetchJson(api() + '?' + q.toString());
  }

  function post(body, timeoutMs) {
    return fetchJson(api(), { method: 'POST', body: JSON.stringify(body) }, timeoutMs || 120000);
  }

  function switchTab(tab) {
    document.querySelectorAll('.ai-rag-tab').forEach(function (btn) {
      btn.classList.toggle('is-active', btn.getAttribute('data-tab') === tab);
    });
    document.querySelectorAll('.ai-rag-panel').forEach(function (panel) {
      var id = panel.id.replace('ai-rag-panel-', '');
      if (id === tab) {
        panel.hidden = false;
        panel.classList.add('is-active');
      } else {
        panel.hidden = true;
        panel.classList.remove('is-active');
      }
    });
  }

  function renderDashboard(data) {
    data = data || {};
    var ollama = data.ollama || {};
    var ollEl = document.getElementById('ai-rag-ollama-status');
    if (ollEl) {
      var pills = [];
      pills.push('<span class="ai-rag-pill ' + (ollama.reachable ? 'ai-rag-pill--ok' : 'ai-rag-pill--fail') + '">' +
        (ollama.reachable ? 'Online' : 'Offline') + '</span>');
      if (ollama.model) pills.push('<span class="ai-rag-pill">' + esc(ollama.model) + '</span>');
      if (ollama.latency_ms) pills.push('<span class="ai-rag-pill">' + ollama.latency_ms + ' ms</span>');
      ollEl.innerHTML = pills.join('');
    }
    var vec = data.vector_store || {};
    var vecEl = document.getElementById('ai-rag-vector-status');
    if (vecEl) {
      vecEl.innerHTML = 'Backend: <strong>' + esc(vec.backend || '—') + '</strong><br>' +
        'Documente indexate: ' + esc(vec.documents_indexed || 0) + '<br>' +
        '<small>' + esc(vec.note || '') + '</small>';
    }
    var act = data.activity_today || {};
    var actEl = document.getElementById('ai-rag-activity');
    if (actEl) {
      actEl.innerHTML = 'Cereri AI: <strong>' + (act.total || 0) + '</strong><br>' +
        'Acceptate: ' + (act.accepted || 0) + ' · Respinse: ' + (act.rejected || 0) + '<br>' +
        'Latență medie: ' + (act.avg_latency_ms || 0) + ' ms';
    }
    var alertsEl = document.getElementById('ai-rag-alerts');
    var alerts = data.alerts || [];
    if (alertsEl) {
      alertsEl.innerHTML = alerts.length
        ? alerts.map(function (a) { return '<div class="ai-rag-alert">' + esc(a.message || a.type) + '</div>'; }).join('')
        : 'Nicio alertă activă.';
    }
  }

  function renderModules(data) {
    var el = document.getElementById('ai-rag-modules');
    if (!el) return;
    var modules = (data && data.modules) || [];
    el.innerHTML = modules.map(function (m) {
      var phaseOk = m.phase_available !== false;
      return '<article class="ai-rag-module' + (phaseOk ? '' : ' is-disabled') + '" data-id="' + esc(m.id) + '">' +
        '<div class="ai-rag-module__head">' +
        '<strong>#' + m.number + ' ' + esc(m.name) + '</strong>' +
        '<span class="ai-rag-badge ai-rag-badge--phase">Faza ' + m.phase + '</span>' +
        '</div>' +
        '<p class="ai-rag-module__desc">' + esc(m.description) + '</p>' +
        '<div class="ai-rag-module__row">' +
        '<label><input type="checkbox" class="ai-rag-mod-enabled" ' + (m.enabled ? 'checked' : '') +
          (phaseOk ? '' : ' disabled') + '> Activ</label>' +
        '<label>Model <input type="text" class="ai-rag-mod-model" value="' + esc(m.model) + '" size="14"' +
          (phaseOk ? '' : ' disabled') + '></label>' +
        (m.id === 'mod_06_log_summary' ? '<button type="button" class="ai-rag-btn ai-rag-btn--ghost ai-rag-mod-test">Test</button>' : '') +
        '<button type="button" class="ai-rag-btn ai-rag-btn--ghost ai-rag-mod-save"' + (phaseOk ? '' : ' disabled') + '>Salvează</button>' +
        '</div></article>';
    }).join('');
  }

  function renderLogs(rows) {
    var el = document.getElementById('ai-rag-interaction-logs');
    if (!el) return;
    if (!rows || !rows.length) {
      el.innerHTML = '<p class="ai-rag-meta">Nicio interacțiune încă.</p>';
      return;
    }
    var html = '<table><thead><tr><th>Timp</th><th>Modul</th><th>Input</th><th>Status</th><th>Model</th><th>ms</th></tr></thead><tbody>';
    rows.forEach(function (r) {
      var st = String(r.status || '');
      var stCls = st === 'failed' ? ' ai-rag-log-fail' : (st === 'proposed' ? ' ai-rag-log-ok' : '');
      html += '<tr class="' + stCls + '"><td>' + esc(r.created_at) + '</td><td>' + esc(r.module_id) + '</td><td>' +
        esc(r.input_summary) + '</td><td>' + esc(r.status) + '</td><td>' + esc(r.model) +
        '</td><td>' + esc(r.latency_ms) + '</td></tr>';
    });
    html += '</tbody></table>';
    el.innerHTML = html;
  }

  var aiworkPollTimer = null;
  var aiworkFeedbackTarget = null;

  function formatAiWorkTime(iso) {
    if (!iso) return '—';
    try {
      var d = new Date(iso);
      if (isNaN(d.getTime())) return '—';
      return d.toLocaleTimeString('ro-RO', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    } catch (e) {
      return String(iso).slice(11, 19) || '—';
    }
  }

  function aiworkClip(text, max) {
    var s = String(text || '').trim();
    if (!s) return '—';
    max = max || 120;
    return s.length > max ? s.slice(0, max) + '…' : s;
  }

  function aiworkRowTone(row) {
    var st = String(row.status || '');
    var rating = String(row.operator_rating || '');
    if (rating === 'bad') return 'fail';
    if (row.success === true || st === 'ok' || st === 'proposed' || st === 'accepted' || st === 'fallback_ok') return 'ok';
    if (row.success === false || st === 'fail' || st === 'failed') return 'fail';
    return 'warn';
  }

  function aiworkRowIcon(tone) {
    if (tone === 'ok') return 'fa-circle-check';
    if (tone === 'fail') return 'fa-circle-xmark';
    return 'fa-circle-half-stroke';
  }

  function aiworkRowLabel(row, tone) {
    var st = String(row.status || '');
    if (String(row.operator_rating || '') === 'bad') return 'Respins de tine';
    if (String(row.operator_rating || '') === 'ok') return 'Confirmat';
    if (st === 'fallback_ok') return 'OK local';
    if (tone === 'ok') return 'Reușit';
    if (tone === 'fail') return 'Eșuat';
    return 'Parțial';
  }

  function renderAiWorkFeed(payload) {
    var el = document.getElementById('aiwork-feed');
    if (!el) return;
    var rows = (payload && payload.rows) || [];
    var stats = (payload && payload.stats) || {};

    var totalEl = document.getElementById('aiwork-stat-total');
    var ollamaEl = document.getElementById('aiwork-stat-ollama');
    var rateEl = document.getElementById('aiwork-stat-rate');
    var failEl = document.getElementById('aiwork-stat-fail');
    var syncEl = document.getElementById('aiwork-last-sync');

    if (totalEl) totalEl.textContent = String(stats.total != null ? stats.total : '0');
    if (ollamaEl) ollamaEl.textContent = String(stats.ollama_events != null ? stats.ollama_events : '0');
    if (rateEl) rateEl.textContent = stats.success_rate_pct != null ? (stats.success_rate_pct + '%') : '—';
    if (failEl) failEl.textContent = String(stats.fail_count != null ? stats.fail_count : '0');
    if (syncEl) {
      try {
        syncEl.textContent = 'Live · ' + new Date().toLocaleTimeString('ro-RO', {
          hour: '2-digit', minute: '2-digit', second: '2-digit',
        });
      } catch (e) {
        syncEl.textContent = 'Live';
      }
    }

    if (!rows.length) {
      var emptyHint = stats.archive_fallback
        ? 'Nimic live în ultimele ' + esc(String(stats.window_minutes || 15)) + ' min — afișez ultimele din jurnal.'
        : 'Nimic în ultimele ' + esc(String(stats.window_minutes || 15)) + ' min.';
      el.innerHTML = '<div class="aiwork-empty aiwork-empty--on-light">'
        + '<i class="fa-regular fa-face-smile" aria-hidden="true"></i>'
        + '<span>' + emptyHint + '</span>'
        + '<small>Folosește chat, import sau RAG — apare aici.</small>'
        + '</div>';
      return;
    }

    var html = '';
    if (stats.archive_fallback) {
      html += '<div class="aiwork-archive-note">Ultimele evenimente din jurnal (în afara ferestrei live '
        + esc(String(stats.window_minutes || 15)) + ' min)</div>';
    }

    html += '<table class="aiwork-table"><thead><tr>'
      + '<th>Timp</th><th>Unde</th><th>Ce face</th><th>Întrebare / scanat</th><th>Rezultat</th>'
      + '<th>Status</th><th>ms</th><th>Tu</th>'
      + '</tr></thead><tbody>';

    rows.forEach(function (r) {
      var tone = aiworkRowTone(r);
      var label = aiworkRowLabel(r, tone);
      var trCls = 'aiwork-tr--' + tone;

      var where = r.where || r.section_label || r.section || '—';
      var actionLbl = r.action_label || r.action || '—';
      var inputTxt = r.input_text || '';
      var outputTxt = r.output_text || '';
      var detailFull = r.detail || r.summary || '';
      var modelHint = r.model ? (' · ' + r.model) : '';

      var statusCell = '<span class="aiwork-pill aiwork-pill--' + tone + '">' + esc(label) + '</span>';

      var feedbackCell = '—';
      if (r.can_feedback) {
        if (String(r.operator_rating || '') === 'bad') {
          feedbackCell = '<span class="aiwork-rated aiwork-rated--bad"><i class="fa-solid fa-thumbs-down"></i> Notat</span>';
        } else if (String(r.operator_rating || '') === 'ok') {
          feedbackCell = '<span class="aiwork-rated aiwork-rated--ok"><i class="fa-solid fa-thumbs-up"></i> OK</span>';
        } else {
          feedbackCell = '<button type="button" class="aiwork-btn-bad" data-aiwork-bad="1"'
            + ' data-source-type="' + esc(r.source_type) + '"'
            + ' data-source-id="' + esc(r.id) + '"'
            + ' data-section="' + esc(where) + '"'
            + ' data-summary="' + esc(detailFull || inputTxt) + '">'
            + '<i class="fa-solid fa-thumbs-down"></i> Nu corect</button>';
        }
      }

      html += '<tr class="' + trCls + '">'
        + '<td class="aiwork-td-time">' + esc(formatAiWorkTime(r.at)) + '</td>'
        + '<td class="aiwork-td-where"><span class="aiwork-where">' + esc(where) + '</span></td>'
        + '<td class="aiwork-td-action">' + esc(actionLbl) + esc(modelHint) + '</td>'
        + '<td class="aiwork-td-input" title="' + esc(inputTxt) + '">' + esc(aiworkClip(inputTxt, 140)) + '</td>'
        + '<td class="aiwork-td-output" title="' + esc(outputTxt || detailFull) + '">' + esc(aiworkClip(outputTxt || detailFull, 140)) + '</td>'
        + '<td>' + statusCell + '</td>'
        + '<td class="aiwork-td-ms">' + esc(r.latency_ms || '—') + '</td>'
        + '<td>' + feedbackCell + '</td>'
        + '</tr>';
    });

    html += '</tbody></table>';
    el.innerHTML = html;
  }

  function openAiWorkFeedbackModal(target) {
    aiworkFeedbackTarget = target;
    var modal = document.getElementById('aiwork-feedback-modal');
    var ctx = document.getElementById('aiwork-feedback-context');
    var note = document.getElementById('aiwork-feedback-note');
    if (ctx) {
      ctx.textContent = (target.section || '—') + ' · ' + (target.summary || target.action || '');
    }
    if (note) note.value = '';
    if (modal) modal.classList.remove('hidden');
  }

  function closeAiWorkFeedbackModal() {
    aiworkFeedbackTarget = null;
    var modal = document.getElementById('aiwork-feedback-modal');
    if (modal) modal.classList.add('hidden');
  }

  function submitAiWorkFeedback() {
    if (!aiworkFeedbackTarget) return;
    var noteEl = document.getElementById('aiwork-feedback-note');
    var note = noteEl ? String(noteEl.value || '').trim() : '';
    var body = {
      action: 'aiwork_feedback',
      source_type: aiworkFeedbackTarget.source_type,
      source_id: aiworkFeedbackTarget.source_id,
      rating: 'bad',
      note: note,
    };
    fetchJson(api(), {
      method: 'POST',
      body: JSON.stringify(body),
    }, 30000).then(function (json) {
      toast((json && json.message) ? json.message : 'Feedback salvat.');
      closeAiWorkFeedbackModal();
      loadAiWorkFeed();
    }).catch(function (err) {
      toast(err.message || 'Eroare la salvare feedback.');
    });
  }

  function loadAiWorkFeed() {
    var apiUrl = api();
    if (!apiUrl) {
      renderAiWorkFeed({ rows: [], stats: { window_minutes: 15, total: 0, ollama_events: 0, fail_count: 0 } });
      return Promise.resolve();
    }
    return get('aiwork_feed', { minutes: 15, limit: 150 }).then(function (json) {
      if (!json || json.success === false) {
        throw new Error((json && json.message) ? json.message : 'Răspuns invalid API');
      }
      renderAiWorkFeed(json.data || {});
    }).catch(function (err) {
      var el = document.getElementById('aiwork-feed');
      var msg = err && err.message ? String(err.message) : 'Eroare necunoscută';
      if (el) {
        el.innerHTML = '<div class="aiwork-empty aiwork-empty--on-light">'
          + '<i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>'
          + '<span>Nu s-a putut încărca AI Work.</span>'
          + '<small>' + esc(msg) + '</small>'
          + '</div>';
      }
      var syncEl = document.getElementById('aiwork-last-sync');
      if (syncEl) syncEl.textContent = 'Eroare sync';
    });
  }

  function startAiWorkPolling() {
    stopAiWorkPolling();
    aiworkPollTimer = setInterval(function () {
      var panel = document.getElementById('ai-hub-panel-system');
      if (panel && !panel.hidden) {
        loadAiWorkFeed();
      }
    }, 5000);
  }

  function stopAiWorkPolling() {
    if (aiworkPollTimer) {
      clearInterval(aiworkPollTimer);
      aiworkPollTimer = null;
    }
  }

  function renderSummaries(rows) {
    var el = document.getElementById('ai-rag-summaries-list');
    if (!el) return;
    if (!rows || !rows.length) {
      el.innerHTML = '<p class="ai-rag-meta">Niciun sumar salvat.</p>';
      return;
    }
    el.innerHTML = rows.map(function (r) {
      var text = r.summary_text || '';
      if (!text && r.stats_json && typeof r.stats_json === 'object') {
        text = r.stats_json.summary_ro || JSON.stringify(r.stats_json);
      }
      return '<div class="ai-rag-summary-item"><strong>' + esc(r.created_at) + '</strong><p>' +
        esc(text) + '</p></div>';
    }).join('');
  }

  function loadSystemTab() {
    loadAiWorkFeed();
    startAiWorkPolling();
    return loadSummaries();
  }

  function loadDashboard() {
    return get('dashboard').then(function (json) {
      renderDashboard(json.data);
    });
  }

  function loadModules() {
    return get('modules').then(function (json) {
      renderModules(json.data);
    });
  }

  function loadLogs() {
    return get('interaction_logs', { limit: 40 }).then(function (json) {
      renderLogs(json.data);
    });
  }

  function loadSummaries() {
    return get('log_summaries', { limit: 10 }).then(function (json) {
      renderSummaries(json.data);
    });
  }

  function loadVectorDetail() {
    return Promise.all([
      get('vector_status'),
      get('corpus_status'),
    ]).then(function (results) {
      var d = (results[0] && results[0].data) || {};
      var corpus = (results[1] && results[1].data) || {};
      var el = document.getElementById('ai-rag-vector-detail');
      if (el) {
        el.textContent = JSON.stringify({ vector: d, corpus: corpus }, null, 2);
      }
      var cEl = document.getElementById('ai-rag-corpus-status');
      if (cEl) {
        cEl.textContent = (corpus.total || 0) + ' / ' + (corpus.target || 500) + ' elemente · '
          + JSON.stringify(corpus.by_source_type || {});
      }
    });
  }

  var libBrowseOffset = 0;
  var siteTipOptions = {};
  var categoryOptions = [];
  var acRegistry = new WeakMap();
  var acActiveNodes = [];
  var sitesRunPollTimer = null;

  function formatLogTime(iso) {
    if (!iso) return '—';
    try {
      var d = new Date(iso);
      return d.toLocaleTimeString('ro-RO', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    } catch (e) {
      return String(iso).slice(11, 19) || '—';
    }
  }

  function stepLabel(step) {
    var map = {
      queued: 'Pornire job',
      start: 'Inițializare site',
      robots: 'Verific robots.txt',
      scrape: 'Scrape pagină',
      process: 'Procesare AI',
      done_slot: 'Site finalizat',
      finished: 'Job finalizat',
    };
    return map[step] || step || '—';
  }

  function renderSitesRunProgress(prog) {
    prog = prog || {};
    var panel = document.getElementById('ai-rag-sites-run-panel');
    var statusEl = document.getElementById('ai-rag-sites-run-status');
    var statsEl = document.getElementById('ai-rag-sites-run-stats');
    var barWrap = document.getElementById('ai-rag-sites-run-bar-wrap');
    var barEl = document.getElementById('ai-rag-sites-run-bar');
    var currentEl = document.getElementById('ai-rag-sites-run-current');
    var logEl = document.getElementById('ai-rag-sites-run-log');
    var summaryEl = document.getElementById('ai-rag-sites-progress');

    var status = prog.status || 'idle';
    var total = parseInt(prog.total || 0, 10);
    var done = parseInt(prog.done || 0, 10);
    var okCount = parseInt(prog.ok_count || 0, 10);
    var failCount = parseInt(prog.fail_count || 0, 10);
    var frags = parseInt(prog.fragments_total || 0, 10);
    var pct = total > 0 ? Math.min(100, Math.round((done / total) * 100)) : (status === 'running' ? 5 : 0);

    if (panel) {
      panel.classList.remove('hidden', 'is-running', 'is-done', 'is-error');
      if (status === 'running') {
        panel.classList.add('is-running');
      } else if (status === 'done') {
        panel.classList.add(failCount > 0 && okCount === 0 ? 'is-error' : 'is-done');
      } else if (status !== 'idle') {
        panel.classList.add('is-error');
      } else {
        panel.classList.add('hidden');
      }
    }

    if (statusEl) {
      if (status === 'running') statusEl.textContent = 'Rulează scrape batch…';
      else if (status === 'done') statusEl.textContent = failCount > 0 && okCount === 0 ? 'Job eșuat' : 'Job finalizat';
      else if (status === 'idle') statusEl.textContent = 'Inactiv';
      else statusEl.textContent = 'Status: ' + status;
    }

    if (statsEl) {
      statsEl.textContent = okCount + ' OK · ' + failCount + ' eșec · ' + frags + ' frag · ' + done + '/' + total;
    }

    if (barWrap) barWrap.setAttribute('aria-valuenow', String(pct));
    if (barEl) barEl.style.width = pct + '%';

    if (currentEl) {
      var cur = prog.current || '';
      var step = prog.current_step ? stepLabel(prog.current_step) : '';
      currentEl.textContent = (cur || step || '—') + (step && cur ? ' · ' + step : '');
    }

    if (logEl) {
      var log = Array.isArray(prog.log) ? prog.log : [];
      if (!log.length) {
        logEl.innerHTML = '<li class="ai-sites-run-log__empty">Nicio intrare în jurnal încă.</li>';
      } else {
        logEl.innerHTML = log.slice(-80).map(function (entry) {
          var level = entry.level || (entry.ok === true ? 'ok' : (entry.ok === false ? 'fail' : 'info'));
          var slot = entry.slot ? ('#' + entry.slot) : '—';
          return '<li class="ai-sites-run-log__item">' +
            '<span class="ai-sites-run-log__time">' + esc(formatLogTime(entry.at)) + '</span>' +
            '<span class="ai-sites-run-log__slot">' + esc(slot) + '</span>' +
            '<span class="ai-sites-run-log__msg ai-sites-run-log__msg--' + esc(level) + '">' + esc(entry.message || '') + '</span>' +
            '</li>';
        }).join('');
        logEl.scrollTop = logEl.scrollHeight;
      }
    }

    if (summaryEl) {
      if (status === 'running') {
        summaryEl.textContent = (prog.current || 'Rulează…') + ' · ' + done + '/' + total + ' site-uri';
      } else if (status === 'done') {
        summaryEl.textContent = 'Ultima rulare: ' + okCount + ' OK, ' + failCount + ' eșecuri, ' + frags + ' fragmente RAG';
      } else {
        summaryEl.textContent = total ? (done + ' procesate din ' + total) : '—';
      }
    }
  }

  function pollSitesProgress() {
    return get('library_sites_progress').then(function (json) {
      renderSitesRunProgress((json && json.data) || {});
      return json;
    }).catch(function () { /* ignore poll errors */ });
  }

  function startSitesRunPolling(resume) {
    stopSitesRunPolling();
    var panel = document.getElementById('ai-rag-sites-run-panel');
    if (panel) panel.classList.remove('hidden');
    if (!resume) {
      renderSitesRunProgress({
        status: 'running',
        total: 0,
        done: 0,
        ok_count: 0,
        fail_count: 0,
        fragments_total: 0,
        current: 'Trimit job la server…',
        current_step: 'queued',
        log: [{ at: new Date().toISOString(), slot: 0, level: 'info', message: 'Se pornește job-ul…' }],
      });
    }
    pollSitesProgress();
    sitesRunPollTimer = setInterval(pollSitesProgress, 2000);
  }

  function stopSitesRunPolling() {
    if (sitesRunPollTimer) {
      clearInterval(sitesRunPollTimer);
      sitesRunPollTimer = null;
    }
  }

  function setSitesRunBusy(busy) {
    var runBtn = document.getElementById('ai-rag-sites-run');
    var saveBtn = document.getElementById('ai-rag-sites-save');
    if (runBtn) runBtn.classList.toggle('is-busy', !!busy);
    if (saveBtn) saveBtn.classList.toggle('is-busy', !!busy);
  }

  function hubCfg() {
    var el = document.getElementById('ai-hub-cfg');
    if (!el) return {};
    try { return JSON.parse(el.textContent || '{}'); } catch (e) { return {}; }
  }

  function flattenCategoryOptions(categories) {
    var list = Array.isArray(categories) ? categories : [];
    var byId = {};
    list.forEach(function (c) {
      if (c && c.id != null) byId[c.id] = c;
    });
    var out = [];
    var seen = {};
    list.forEach(function (c) {
      if (!c) return;
      var label = String(c.label || c.slug || '').trim();
      if (!label || seen[label]) return;
      seen[label] = true;
      var parentId = parseInt(c.parent_id || 0, 10);
      var display = label;
      if (parentId && byId[parentId] && byId[parentId].label) {
        display = String(byId[parentId].label) + ' › ' + label;
      }
      out.push({ value: label, label: display });
    });
    out.sort(function (a, b) { return a.label.localeCompare(b.label, 'ro'); });
    return out;
  }

  function loadCategoryOptions() {
    if (categoryOptions.length) return Promise.resolve(categoryOptions);
    var apiUrl = hubCfg().categoriiApi || '';
    if (!apiUrl) return Promise.resolve([]);
    return fetchJson(apiUrl + '?action=all', {}, 30000).then(function (json) {
      categoryOptions = flattenCategoryOptions((json && json.categories) || []);
      return categoryOptions;
    }).catch(function () {
      categoryOptions = [
        { value: 'Ulei motor', label: 'Ulei motor' },
        { value: 'Filtre', label: 'Filtre' },
        { value: 'Frânare', label: 'Frânare' },
        { value: 'Suspensie', label: 'Suspensie' },
        { value: 'Electrice', label: 'Electrice' },
      ];
      return categoryOptions;
    });
  }

  function highlightMatch(text, query) {
    if (!query) return esc(text);
    var q = query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    return esc(text).replace(new RegExp('(' + q + ')', 'ig'), '<mark>$1</mark>');
  }

  function destroyAutocomplete(node) {
    var inst = acRegistry.get(node);
    if (!inst) return;
    if (inst.remoteTimer) clearTimeout(inst.remoteTimer);
    if (inst.onDocClick) document.removeEventListener('mousedown', inst.onDocClick);
    if (inst.wrap && inst.wrap.parentNode) {
      if (inst.inPlace && inst.source) {
        inst.source.classList.remove('ai-ac__input');
        var parent = inst.wrap.parentNode;
        parent.insertBefore(inst.source, inst.wrap);
      } else if (inst.source && inst.source.parentNode === inst.wrap) {
        inst.wrap.parentNode.insertBefore(inst.source, inst.wrap);
      }
      inst.wrap.remove();
    }
    acRegistry.delete(node);
    acActiveNodes = acActiveNodes.filter(function (n) { return n !== node; });
  }

  function setFieldValue(el, value) {
    if (!el) return;
    el.value = value == null ? '' : String(value);
    var inst = acRegistry.get(el);
    if (inst && inst.input && inst.input !== el) {
      inst.input.value = el.value;
    }
  }

  function clearAllAutocompletes() {
    acActiveNodes.slice().forEach(destroyAutocomplete);
  }

  function mountAutocomplete(sourceNode, config) {
    config = config || {};
    destroyAutocomplete(sourceNode);

    var options = config.options || [];
    var allowCustom = config.allowCustom !== false;
    var placeholder = config.placeholder || 'Caută…';
    var isSelect = sourceNode.tagName === 'SELECT';
    var inPlace = !isSelect;
    var minChars = parseInt(config.minChars || 0, 10);
    var debounceMs = parseInt(config.debounceMs || 280, 10);
    var remoteSearch = typeof config.remoteSearch === 'function' ? config.remoteSearch : null;

    var wrap = document.createElement('div');
    wrap.className = 'ai-ac' + (isSelect ? ' ai-ac--select' : ' ai-ac--input');
    sourceNode.parentNode.insertBefore(wrap, sourceNode);

    var input;
    if (inPlace) {
      input = sourceNode;
      input.classList.add('ai-ac__input');
      if (placeholder) input.placeholder = placeholder;
      input.autocomplete = 'off';
      input.setAttribute('role', 'combobox');
      input.setAttribute('aria-expanded', 'false');
      input.setAttribute('aria-autocomplete', 'list');
      wrap.appendChild(input);
    } else {
      input = document.createElement('input');
      input.type = 'text';
      input.className = 'ai-ac__input';
      input.autocomplete = 'off';
      input.placeholder = placeholder;
      input.setAttribute('role', 'combobox');
      input.setAttribute('aria-expanded', 'false');
      input.setAttribute('aria-autocomplete', 'list');
      wrap.appendChild(input);
      wrap.appendChild(sourceNode);
      sourceNode.classList.add('ai-ac__native');
      sourceNode.tabIndex = -1;
      var selOpt = sourceNode.options[sourceNode.selectedIndex];
      input.value = selOpt ? selOpt.textContent : '';
    }

    var menu = document.createElement('ul');
    menu.className = 'ai-ac__menu';
    menu.hidden = true;
    menu.setAttribute('role', 'listbox');
    wrap.appendChild(menu);

    var state = { open: false, active: -1, filtered: [], loading: false };
    var remoteTimer = null;

    function syncToSource(value, label) {
      if (isSelect) {
        var found = false;
        Array.prototype.forEach.call(sourceNode.options, function (opt) {
          if (opt.value === value) {
            sourceNode.value = value;
            found = true;
          }
        });
        if (!found && allowCustom) {
          var opt = document.createElement('option');
          opt.value = value;
          opt.textContent = label || value;
          sourceNode.appendChild(opt);
          sourceNode.value = value;
        }
      } else {
        sourceNode.value = value;
      }
    }

    function filterOptions(query) {
      var q = (query || '').trim().toLowerCase();
      if (!q) return options.slice(0, 80);
      return options.filter(function (opt) {
        return opt.label.toLowerCase().indexOf(q) !== -1
          || opt.value.toLowerCase().indexOf(q) !== -1
          || (opt.sublabel && opt.sublabel.toLowerCase().indexOf(q) !== -1);
      }).slice(0, 80);
    }

    function renderMenu(items, query) {
      menu.innerHTML = '';
      if (state.loading) {
        var loading = document.createElement('li');
        loading.className = 'ai-ac__empty';
        loading.textContent = 'Se caută în baza de date…';
        menu.appendChild(loading);
        return;
      }
      if (!items.length) {
        var empty = document.createElement('li');
        empty.className = 'ai-ac__empty';
        if (remoteSearch && (query || '').trim().length < minChars) {
          empty.textContent = 'Tastează min. ' + minChars + ' caractere pentru căutare în BD';
        } else {
          empty.textContent = allowCustom ? 'Nicio potrivire — Enter pentru valoare liberă' : 'Nicio potrivire';
        }
        menu.appendChild(empty);
        return;
      }
      items.forEach(function (opt, idx) {
        var li = document.createElement('li');
        li.className = 'ai-ac__option' + (idx === state.active ? ' is-active' : '');
        li.setAttribute('role', 'option');
        li.setAttribute('data-value', opt.value);
        var html = highlightMatch(opt.label, query);
        if (opt.sublabel) {
          html += '<span class="ai-ac__option-sub">' + esc(opt.sublabel) + '</span>';
        }
        li.innerHTML = html;
        li.addEventListener('mousedown', function (e) {
          e.preventDefault();
          pickOption(opt);
        });
        menu.appendChild(li);
      });
    }

    function closeMenu() {
      state.open = false;
      state.active = -1;
      state.loading = false;
      wrap.classList.remove('is-open');
      menu.hidden = true;
      input.setAttribute('aria-expanded', 'false');
    }

    function openMenuWithItems(items, query) {
      state.filtered = items;
      state.active = items.length ? 0 : -1;
      renderMenu(items, query);
      wrap.classList.add('is-open');
      menu.hidden = false;
      state.open = true;
      input.setAttribute('aria-expanded', 'true');
    }

    function fetchRemoteOptions(query) {
      if (!remoteSearch) {
        openMenuWithItems(filterOptions(query), query);
        return;
      }
      if ((query || '').trim().length < minChars) {
        openMenuWithItems([], query);
        return;
      }
      state.loading = true;
      openMenuWithItems([], query);
      clearTimeout(remoteTimer);
      remoteTimer = setTimeout(function () {
        remoteSearch(query).then(function (items) {
          state.loading = false;
          options = items || [];
          if (state.open) openMenuWithItems(options, query);
        }).catch(function () {
          state.loading = false;
          openMenuWithItems([], query);
        });
      }, debounceMs);
    }

    function openMenu() {
      var query = input.value;
      if (remoteSearch) {
        fetchRemoteOptions(query);
      } else {
        openMenuWithItems(filterOptions(query), query);
      }
    }

    function pickOption(opt) {
      input.value = opt.displayValue || opt.label || opt.value;
      syncToSource(opt.value, opt.label);
      if (typeof config.onPick === 'function') config.onPick(opt, input);
      closeMenu();
    }

    function commitFreeText() {
      var val = input.value.trim();
      if (!val) {
        syncToSource('', '');
        return;
      }
      var exact = options.find(function (o) {
        return o.value.toLowerCase() === val.toLowerCase() || o.label.toLowerCase() === val.toLowerCase();
      });
      if (exact) {
        pickOption(exact);
        return;
      }
      if (allowCustom) syncToSource(val, val);
    }

    input.addEventListener('focus', function () { openMenu(); });
    input.addEventListener('input', function () {
      if (allowCustom || inPlace) syncToSource(input.value.trim(), input.value.trim());
      openMenu();
    });
    input.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowDown') {
        e.preventDefault();
        if (!state.open) openMenu();
        else if (state.filtered.length) {
          state.active = Math.min(state.active + 1, state.filtered.length - 1);
          renderMenu(state.filtered, input.value);
        }
      } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        if (state.filtered.length) {
          state.active = Math.max(state.active - 1, 0);
          renderMenu(state.filtered, input.value);
        }
      } else if (e.key === 'Enter') {
        e.preventDefault();
        if (state.open && state.active >= 0 && state.filtered[state.active]) {
          pickOption(state.filtered[state.active]);
        } else {
          commitFreeText();
          closeMenu();
        }
      } else if (e.key === 'Escape') {
        closeMenu();
      }
    });
    input.addEventListener('blur', function () {
      setTimeout(function () {
        if (!wrap.contains(document.activeElement)) commitFreeText();
        closeMenu();
      }, 120);
    });

    var onDocClick = function (e) {
      if (!wrap.contains(e.target)) closeMenu();
    };
    document.addEventListener('mousedown', onDocClick);

    acRegistry.set(sourceNode, {
      wrap: wrap,
      source: sourceNode,
      input: input,
      inPlace: inPlace,
      onDocClick: onDocClick,
      remoteTimer: remoteTimer,
    });
    acActiveNodes.push(sourceNode);
    return { getValue: function () { return inPlace ? input.value.trim() : (sourceNode.value || input.value.trim()); } };
  }

  function initSitesTableAutocompletes() {
    document.querySelectorAll('#ai-rag-sites-tbody .site-category').forEach(function (el) {
      mountAutocomplete(el, {
        options: categoryOptions,
        allowCustom: true,
        placeholder: 'Caută categorie…',
      });
    });
    document.querySelectorAll('#ai-rag-sites-tbody .site-tip').forEach(function (el) {
      var opts = Object.keys(siteTipOptions).map(function (k) {
        return { value: k, label: siteTipOptions[k] };
      });
      mountAutocomplete(el, {
        options: opts,
        allowCustom: false,
        placeholder: 'Tip scrape…',
      });
    });
  }

  function initImportAutocompletes() {
    /* Import Pro: grid vizual cu imagini — vezi bindImportPickerEvents(). */
  }

  var importPickerState = {
    page: 1,
    totalPages: 1,
    total: 0,
    selected: null,
    loaded: false,
    items: [],
  };

  function importImageFilterValue() {
    var el = document.getElementById('ai-rag-import-image-filter');
    return el ? el.value : 'all';
  }

  function importFilterQuery() {
    var el = document.getElementById('ai-rag-import-filter');
    return el ? el.value.trim() : '';
  }

  function renderImportGrid(items) {
    var grid = document.getElementById('ai-rag-import-grid');
    if (!grid) return;
    items = items || [];
    importPickerState.items = items;
    grid._items = items;
    if (!items.length) {
      grid.innerHTML = '<div class="ai-import-grid__empty">Niciun produs găsit pentru filtrele alese.</div>';
      grid.hidden = false;
      return;
    }
    grid.innerHTML = items.map(function (p) {
      var selected = importPickerState.selected && importPickerState.selected.id === p.id;
      var img = p.image || '';
      var imgHtml = img
        ? '<img src="' + esc(img) + '" alt="" loading="lazy" class="ai-import-card__img">'
        : '<span class="ai-import-card__noimg"><i class="fa-solid fa-image" aria-hidden="true"></i> Fără imagine</span>';
      var meta = [p.brand, p.code, p.category].filter(Boolean).join(' · ');
      return '<button type="button" class="ai-import-card' + (selected ? ' is-selected' : '') + '" data-product-id="' + esc(p.id) + '">' +
        '<div class="ai-import-card__media">' + imgHtml + '</div>' +
        '<div class="ai-import-card__body">' +
        '<strong class="ai-import-card__name">' + esc(p.name) + '</strong>' +
        (meta ? '<span class="ai-import-card__meta">' + esc(meta) + '</span>' : '') +
        (p.price_label ? '<span class="ai-import-card__price">' + esc(p.price_label) + '</span>' : '') +
        '</div></button>';
    }).join('');
    grid.hidden = false;
  }

  function renderImportPagination(data) {
    data = data || {};
    var wrap = document.getElementById('ai-rag-import-pagination');
    var label = document.getElementById('ai-rag-import-page-label');
    var prev = document.getElementById('ai-rag-import-prev');
    var next = document.getElementById('ai-rag-import-next');
    importPickerState.page = data.page || 1;
    importPickerState.totalPages = data.total_pages || 1;
    importPickerState.total = data.total || 0;
    if (label) {
      label.textContent = 'Pagina ' + importPickerState.page + ' / ' + importPickerState.totalPages + ' · ' + importPickerState.total + ' produse';
    }
    if (prev) prev.disabled = importPickerState.page <= 1;
    if (next) next.disabled = importPickerState.page >= importPickerState.totalPages;
    if (wrap) wrap.classList.toggle('hidden', (importPickerState.totalPages || 1) <= 1);
  }

  function selectImportProduct(product) {
    importPickerState.selected = product || null;
    var panel = document.getElementById('ai-rag-import-selected');
    var imgEl = document.getElementById('ai-rag-import-selected-img');
    var noImg = document.getElementById('ai-rag-import-selected-noimg');
    var nameEl = document.getElementById('ai-rag-import-selected-name');
    var metaEl = document.getElementById('ai-rag-import-selected-meta');
    if (!product) {
      if (panel) panel.classList.add('hidden');
      document.querySelectorAll('.ai-import-card.is-selected').forEach(function (c) { c.classList.remove('is-selected'); });
      return;
    }
    if (panel) panel.classList.remove('hidden');
    if (nameEl) nameEl.textContent = product.name || '—';
    if (metaEl) {
      var parts = [];
      if (product.brand) parts.push('Brand: ' + product.brand);
      if (product.code) parts.push('Cod: ' + product.code);
      if (product.oem) parts.push('OEM: ' + product.oem);
      if (product.category) parts.push('Categorie: ' + product.category);
      if (product.price_label) parts.push('Preț: ' + product.price_label);
      if (product.image_source) parts.push('Sursă imagine: ' + product.image_source);
      metaEl.textContent = parts.join(' · ') || '—';
    }
    if (imgEl && noImg) {
      if (product.image) {
        imgEl.src = product.image;
        imgEl.classList.remove('hidden');
        noImg.classList.add('hidden');
      } else {
        imgEl.removeAttribute('src');
        imgEl.classList.add('hidden');
        noImg.classList.remove('hidden');
      }
    }
    document.querySelectorAll('.ai-import-card').forEach(function (card) {
      card.classList.toggle('is-selected', card.getAttribute('data-product-id') === product.id);
    });
  }

  function loadImportProducts(page) {
    page = page || 1;
    var metaEl = document.getElementById('ai-rag-import-meta');
    var loadBtn = document.getElementById('ai-rag-import-load');
    if (metaEl) metaEl.textContent = 'Se încarcă produsele din magazin…';
    if (loadBtn) loadBtn.classList.add('is-busy');
    return get('library_product_browse', {
      q: importFilterQuery(),
      page: page,
      limit: 24,
      image: importImageFilterValue(),
    }).then(function (json) {
      var data = (json && json.data) || {};
      renderImportGrid(data.items || []);
      renderImportPagination(data);
      importPickerState.loaded = true;
      if (metaEl) {
        metaEl.textContent = (data.total || 0) + ' produse · click pe un card pentru a lucra cu el';
      }
      if (importPickerState.selected) {
        var still = (data.items || []).find(function (p) { return p.id === importPickerState.selected.id; });
        if (still) selectImportProduct(still);
      }
    }).catch(function (e) {
      if (metaEl) metaEl.textContent = 'Eroare: ' + String(e.message || e);
      toast(String(e.message || e));
    }).finally(function () {
      if (loadBtn) loadBtn.classList.remove('is-busy');
    });
  }

  var importSuggestModalTimer = null;
  var importSuggestLastPayload = null;

  var IMPORT_SUGGEST_STEPS = [
    'Pregătire date produs',
    'Căutare în biblioteca RAG',
    'Potriviri interne / externe',
    'Generare SEO AI (Ollama)',
    'Keywords long-tail + categorii L1/L2',
    'Schema Product + fișier HTML indexabil',
  ];

  function closeImportSuggestModal() {
    var modal = document.getElementById('ai-import-suggest-modal');
    if (importSuggestModalTimer) {
      clearInterval(importSuggestModalTimer);
      importSuggestModalTimer = null;
    }
    if (modal) {
      modal.classList.add('hidden');
      modal.hidden = true;
    }
    document.body.classList.remove('ai-import-modal-open');
  }

  function setImportSuggestProgress(pct, stepIndex, stepLabel) {
    var bar = document.getElementById('ai-import-modal-bar');
    var barWrap = document.getElementById('ai-import-modal-bar-wrap');
    var stepEl = document.getElementById('ai-import-modal-step');
    var stepsEl = document.getElementById('ai-import-modal-steps');
    if (bar) bar.style.width = Math.min(100, Math.max(0, pct)) + '%';
    if (barWrap) barWrap.setAttribute('aria-valuenow', String(Math.round(pct)));
    if (stepEl) stepEl.textContent = stepLabel || IMPORT_SUGGEST_STEPS[stepIndex] || 'Procesare…';
    if (stepsEl) {
      stepsEl.innerHTML = IMPORT_SUGGEST_STEPS.map(function (label, idx) {
        var cls = idx < stepIndex ? 'is-done' : (idx === stepIndex ? 'is-active' : '');
        return '<li class="' + cls + '">' + esc(label) + '</li>';
      }).join('');
    }
  }

  function renderImportSuggestProduct(product) {
    var el = document.getElementById('ai-import-modal-product');
    if (!el || !product) return;
    var img = product.image
      ? '<img src="' + esc(product.image) + '" alt="">'
      : '<span class="ai-import-card__noimg" style="width:56px;height:56px;padding:8px"><i class="fa-solid fa-image"></i></span>';
    var meta = [product.brand, product.code, product.category].filter(Boolean).join(' · ');
    el.innerHTML = img + '<div class="ai-import-modal__product-info"><strong>' + esc(product.name) + '</strong><span>' + esc(meta) + '</span></div>';
  }

  function renderImportSuggestResult(data, message) {
    data = data || {};
    var resultEl = document.getElementById('ai-import-modal-result');
    var copyBtn = document.getElementById('ai-import-modal-copy');
    var progressWrap = document.getElementById('ai-import-modal-progress-wrap');
    if (!resultEl) return;

    importSuggestLastPayload = data;
    var ai = data.ai_suggestion || {};
    var structured = ai.structured || {};
    var structure = data.structure || {};
    var seo = data.seo_package || {};
    var title = structured.titlu_normalizat || structure.nume_sugerat || data.query || '—';
    var descScurta = (seo.descrieri && seo.descrieri.scurta) || structured.descriere_scurta || '';
    var descLunga = (seo.descrieri && seo.descrieri.lunga) || structured.descriere_lunga || '';
    var metaDesc = structured.meta_description || descScurta;
    var seoLongTail = (seo.keywords_seo && seo.keywords_seo.fraze_long_tail) || structured.cuvinte_seo_long_tail || data.keywords_suggested || [];
    var seoWords = (seo.keywords_seo && seo.keywords_seo.termeni) || structured.cuvinte_seo || data.keywords_terms || [];
    var categorii = seo.categorii || {};
    var nivel1 = categorii.nivel_1 || (data.product && data.product.category) || '';
    var nivel2 = categorii.nivel_2 || (data.product && data.product.subcategory) || structured.categorie_nivel_2 || '';
    var categoryPath = [nivel1, nivel2].filter(Boolean).join(' › ');
    var seoHtml = data.seo_html || {};
    var confidence = structured.incredere;
    var completari = structured.completari_indirecte || [];
    var campuri = structure.campuri_recomandate || [];
    var coduri = seo.coduri || {};
    var metaTags = seo.meta_tags || {};
    var schemaLd = seo.schema_json_ld || null;
    var recomandari = seo.recomandari_imbunatatire || structure.recomandari_imbunatatire || [];

    var html = '';

    html += '<section class="ai-import-modal__section"><h3>Implementare propusă (previzualizare vitrină)</h3><div class="ai-import-modal__preview">';
    html += '<p class="ai-import-modal__preview-title">' + esc(title) + '</p>';
    if (descScurta) html += '<p class="ai-import-modal__preview-desc">' + esc(descScurta) + '</p>';
    if (categoryPath) html += '<p class="ai-import-modal__preview-desc"><strong>Categorii:</strong> ' + esc(categoryPath) + ' › ' + esc(title) + '</p>';
    if (seoLongTail.length) {
      html += '<div class="ai-import-modal__chips ai-import-modal__chips--longtail">' + seoLongTail.slice(0, 4).map(function (w) {
        return '<span class="ai-import-modal__chip ai-import-modal__chip--longtail">' + esc(w) + '</span>';
      }).join('') + '</div>';
    } else if (seoWords.length) {
      html += '<div class="ai-import-modal__chips">' + seoWords.map(function (w) {
        return '<span class="ai-import-modal__chip">' + esc(w) + '</span>';
      }).join('') + '</div>';
    }
    if (confidence != null && confidence !== '') {
      html += '<span class="ai-import-modal__confidence">Încredere AI: ' + esc(Math.round(parseFloat(confidence) * 100)) + '%</span>';
    }
    html += '</div></section>';

    if (nivel1 || nivel2) {
      html += '<section class="ai-import-modal__section"><h3>Structură categorii (nivel 1 › nivel 2 › produs)</h3><div class="ai-import-modal__field-grid">';
      if (nivel1) html += '<div class="ai-import-modal__field"><span class="ai-import-modal__field-label">Nivel 1 — categorie principală</span><div class="ai-import-modal__field-value">' + esc(nivel1) + '</div></div>';
      if (nivel2) html += '<div class="ai-import-modal__field"><span class="ai-import-modal__field-label">Nivel 2 — subcategorie</span><div class="ai-import-modal__field-value">' + esc(nivel2) + '</div></div>';
      html += '<div class="ai-import-modal__field ai-import-modal__field--wide"><span class="ai-import-modal__field-label">Produs final</span><div class="ai-import-modal__field-value">' + esc(title) + '</div></div>';
      html += '</div></section>';
    }

    if (seoHtml.ok) {
      html += '<section class="ai-import-modal__section ai-import-modal__section--tips"><h3>Fișier HTML indexabil</h3>';
      html += '<p class="ai-import-modal__hits">Pagină SEO statică generată și indexată în biblioteca RAG.</p>';
      html += '<div class="ai-import-modal__field-grid">';
      html += '<div class="ai-import-modal__field ai-import-modal__field--wide"><span class="ai-import-modal__field-label">Fișier proiect</span><div class="ai-import-modal__field-value"><code>' + esc(seoHtml.file || '') + '</code></div></div>';
      if (seoHtml.url_static) {
        html += '<div class="ai-import-modal__field ai-import-modal__field--wide"><span class="ai-import-modal__field-label">URL public</span><div class="ai-import-modal__field-value"><a href="' + esc(seoHtml.url_static) + '" target="_blank" rel="noopener">' + esc(seoHtml.url_static) + '</a></div></div>';
      }
      if (seoHtml.url_live) {
        html += '<div class="ai-import-modal__field ai-import-modal__field--wide"><span class="ai-import-modal__field-label">Pagină live (canonical)</span><div class="ai-import-modal__field-value"><a href="' + esc(seoHtml.url_live) + '" target="_blank" rel="noopener">' + esc(seoHtml.url_live) + '</a></div></div>';
      }
      html += '</div>';
      if (seoHtml.indexed_rag) html += '<p class="ai-import-modal__hits">Indexat în corpus RAG (source_type: seo_html).</p>';
      html += '</section>';
    }

    html += '<section class="ai-import-modal__section"><h3>Descrieri SEO</h3><div class="ai-import-modal__field-grid">';
    if (descScurta) {
      html += '<div class="ai-import-modal__field ai-import-modal__field--wide"><span class="ai-import-modal__field-label">Descriere scurtă (card produs)</span><div class="ai-import-modal__field-value">' + esc(descScurta) + '</div></div>';
    }
    if (descLunga) {
      html += '<div class="ai-import-modal__field ai-import-modal__field--wide"><span class="ai-import-modal__field-label">Descriere lungă (specs API/ACEA, intervale, compatibilități)</span><div class="ai-import-modal__field-value ai-import-modal__field-value--long">' + esc(descLunga) + '</div></div>';
    }
    if (metaDesc) {
      html += '<div class="ai-import-modal__field ai-import-modal__field--wide"><span class="ai-import-modal__field-label">Meta description (Google, max ~160 car.)</span><div class="ai-import-modal__field-value">' + esc(metaDesc) + '</div></div>';
    }
    if (!descScurta && !descLunga) {
      html += '<p class="ai-import-modal__hits">Descrierile vor apărea după generarea AI.</p>';
    }
    html += '</div></section>';

    if (seoLongTail.length || seoWords.length) {
      html += '<section class="ai-import-modal__section"><h3>Keywords SEO — strategie long-tail</h3>';
      html += '<p class="ai-import-modal__hits">Fraze de căutare reale (nu cuvinte izolate din meniuri site).</p>';
      if (seoLongTail.length) {
        html += '<p class="ai-import-modal__field-label">Fraze long-tail (prioritare)</p><div class="ai-import-modal__chips ai-import-modal__chips--longtail">';
        seoLongTail.forEach(function (w) { html += '<span class="ai-import-modal__chip ai-import-modal__chip--longtail">' + esc(w) + '</span>'; });
        html += '</div>';
      }
      if (seoWords.length) {
        html += '<p class="ai-import-modal__field-label" style="margin-top:10px">Termeni scurți (brand, viscozitate, cod)</p><div class="ai-import-modal__chips">';
        seoWords.forEach(function (w) { html += '<span class="ai-import-modal__chip">' + esc(w) + '</span>'; });
        html += '</div>';
      }
      html += '</section>';
    }

    if (coduri.oem || coduri.cod_articol || Object.keys(metaTags).length) {
      html += '<section class="ai-import-modal__section"><h3>Coduri OEM / Articol</h3><div class="ai-import-modal__field-grid">';
      if (coduri.oem) {
        html += '<div class="ai-import-modal__field"><span class="ai-import-modal__field-label">Cod OEM (mpn)</span><div class="ai-import-modal__field-value"><code>' + esc(coduri.oem) + '</code></div></div>';
      }
      if (coduri.cod_articol) {
        html += '<div class="ai-import-modal__field"><span class="ai-import-modal__field-label">Cod articol (sku)</span><div class="ai-import-modal__field-value"><code>' + esc(coduri.cod_articol) + '</code></div></div>';
      }
      if (coduri.expunere) {
        html += '<div class="ai-import-modal__field ai-import-modal__field--wide"><span class="ai-import-modal__field-label">Expunere în pagină</span><div class="ai-import-modal__field-value">' + esc(coduri.expunere) + '</div></div>';
      }
      html += '</div>';
      if (Object.keys(metaTags).length) {
        html += '<p class="ai-import-modal__field-label" style="margin-top:12px">Meta tag-uri generate</p><pre class="ai-import-modal__code">' + esc(JSON.stringify(metaTags, null, 2)) + '</pre>';
      }
      html += '</section>';
    }

    if (schemaLd) {
      html += '<section class="ai-import-modal__section"><h3>Date structurate — Schema.org Product</h3>';
      html += '<p class="ai-import-modal__hits">JSON-LD pentru rich results Google (preț, stoc, brand, coduri).</p>';
      html += '<pre class="ai-import-modal__code">' + esc(JSON.stringify(schemaLd, null, 2)) + '</pre></section>';
    }

    if (recomandari.length) {
      html += '<section class="ai-import-modal__section ai-import-modal__section--tips"><h3>Recomandări pentru îmbunătățire</h3><ul class="ai-import-modal__list ai-import-modal__list--tips">';
      recomandari.forEach(function (r) { html += '<li>' + esc(r) + '</li>'; });
      html += '</ul></section>';
    }

    html += '<section class="ai-import-modal__section"><h3>Sugestie AI — câmpuri</h3><div class="ai-import-modal__field-grid">';
    [
      ['Titlu normalizat', title],
      ['Brand', structured.brand || ''],
      ['Categorie nivel 1', nivel1],
      ['Categorie nivel 2', nivel2],
      ['Format titlu (similar)', structure.format_titlu || ''],
      ['Notă structură', structure.nota || ''],
    ].forEach(function (row) {
      if (!row[1]) return;
      html += '<div class="ai-import-modal__field"><span class="ai-import-modal__field-label">' + esc(row[0]) + '</span><div class="ai-import-modal__field-value">' + esc(row[1]) + '</div></div>';
    });
    html += '</div></section>';

    if (campuri.length) {
      html += '<section class="ai-import-modal__section"><h3>Câmpuri recomandate import</h3><div class="ai-import-modal__chips">';
      campuri.forEach(function (c) { html += '<span class="ai-import-modal__chip">' + esc(c) + '</span>'; });
      html += '</div></section>';
    }

    if (completari.length) {
      html += '<section class="ai-import-modal__section"><h3>Completări din bibliotecă</h3><ul class="ai-import-modal__list">';
      completari.forEach(function (c) { html += '<li>' + esc(c) + '</li>'; });
      html += '</ul></section>';
    }

    html += '<section class="ai-import-modal__section"><h3>Surse RAG</h3>';
    html += '<p class="ai-import-modal__hits">' + esc((data.hits || 0) + ' potriviri · ' + (data.internal || []).length + ' intern · ' + (data.external || []).length + ' extern') + '</p>';
    if ((data.internal || []).length) {
      html += '<ul class="ai-import-modal__list">';
      (data.internal || []).slice(0, 4).forEach(function (h) {
        html += '<li><strong>Intern:</strong> ' + esc(h.title || h.text_preview || '—') + '</li>';
      });
      html += '</ul>';
    }
    if ((data.external || []).length) {
      html += '<ul class="ai-import-modal__list">';
      (data.external || []).slice(0, 4).forEach(function (h) {
        html += '<li><strong>Extern:</strong> ' + esc(h.title || h.text_preview || '—') + '</li>';
      });
      html += '</ul>';
    }
    html += '</section>';

    if (!ai.ok && (ai.text || ai.raw)) {
      html += '<section class="ai-import-modal__section"><div class="ai-import-modal__warn">' + esc(ai.text || ai.raw || 'Sugestia AI nu a putut fi generată.') + (ai.fallback ? ' Conținutul de mai sus a fost completat local.' : '') + '</div></section>';
    } else if (ai.fallback) {
      html += '<section class="ai-import-modal__section"><p class="ai-import-modal__hits">Sugestie completată local (keywords long-tail + descrieri) — Ollama opțional pentru rafinare.</p></section>';
    }

    if (message) {
      html += '<p class="ai-import-modal__hits">' + esc(message) + '</p>';
    }

    resultEl.innerHTML = html;
    resultEl.classList.remove('hidden');
    if (progressWrap) progressWrap.classList.add('hidden');
    if (copyBtn) copyBtn.classList.remove('hidden');
  }

  function openImportSuggestModal(product) {
    var modal = document.getElementById('ai-import-suggest-modal');
    var resultEl = document.getElementById('ai-import-modal-result');
    var progressWrap = document.getElementById('ai-import-modal-progress-wrap');
    var copyBtn = document.getElementById('ai-import-modal-copy');
    var subtitle = document.getElementById('ai-import-modal-subtitle');
    if (!modal) return;

    if (subtitle) subtitle.textContent = 'Analiză dicționar + corpus pentru «' + (product.name || '') + '»';
    renderImportSuggestProduct(product);
    if (resultEl) { resultEl.classList.add('hidden'); resultEl.innerHTML = ''; }
    if (progressWrap) progressWrap.classList.remove('hidden');
    if (copyBtn) copyBtn.classList.add('hidden');
    importSuggestLastPayload = null;

    modal.classList.remove('hidden');
    modal.hidden = false;
    document.body.classList.add('ai-import-modal-open');
    setImportSuggestProgress(8, 0, IMPORT_SUGGEST_STEPS[0]);

    var stepIdx = 0;
    var pct = 8;
    if (importSuggestModalTimer) clearInterval(importSuggestModalTimer);
    importSuggestModalTimer = setInterval(function () {
      pct = Math.min(92, pct + (stepIdx < 4 ? 3 : 2));
      if (pct > 14 && stepIdx < 1) stepIdx = 1;
      if (pct > 28 && stepIdx < 2) stepIdx = 2;
      if (pct > 42 && stepIdx < 3) stepIdx = 3;
      if (pct > 58 && stepIdx < 4) stepIdx = 4;
      if (pct > 74 && stepIdx < 5) stepIdx = 5;
      setImportSuggestProgress(pct, stepIdx, IMPORT_SUGGEST_STEPS[stepIdx]);
    }, 900);
  }

  function finishImportSuggestModal(json, err) {
    if (importSuggestModalTimer) {
      clearInterval(importSuggestModalTimer);
      importSuggestModalTimer = null;
    }
    if (err) {
      setImportSuggestProgress(100, IMPORT_SUGGEST_STEPS.length - 1, 'Eroare');
      var resultEl = document.getElementById('ai-import-modal-result');
      if (resultEl) {
        resultEl.innerHTML = '<div class="ai-import-modal__warn">' + esc(String(err.message || err)) + '</div>';
        resultEl.classList.remove('hidden');
      }
      document.getElementById('ai-import-modal-progress-wrap')?.classList.add('hidden');
      return;
    }
    setImportSuggestProgress(100, IMPORT_SUGGEST_STEPS.length - 1, 'Finalizat');
    setTimeout(function () {
      renderImportSuggestResult((json && json.data) || {}, json && json.message);
      toast((json && json.message) || 'Sugestie gata');
    }, 350);
  }

  function runImportSuggestLookup(product) {
    openImportSuggestModal(product);
    return post({
      action: 'library_import_lookup',
      name: product.name || '',
      category: product.category || '',
      oem: product.oem || product.code || '',
      product_id: product.id || '',
    }, 130000).then(function (json) {
      finishImportSuggestModal(json);
      var pre = document.getElementById('ai-rag-import-output');
      if (pre) { pre.classList.remove('hidden'); pre.textContent = JSON.stringify(json.data, null, 2); }
    }).catch(function (e) {
      finishImportSuggestModal(null, e);
      toast(String(e.message || e));
    });
  }

  var libraryAskModalTimer = null;
  var libraryAskLastAnswer = '';

  var LIBRARY_ASK_STEPS = [
    'Validare întrebare',
    'Căutare hybrid în corpus',
    'Potriviri interne / externe / market',
    'Sinteză răspuns (RAG + Ollama)',
    'Pregătire surse citate',
  ];

  function closeLibraryAskModal() {
    var modal = document.getElementById('ai-library-ask-modal');
    if (libraryAskModalTimer) {
      clearInterval(libraryAskModalTimer);
      libraryAskModalTimer = null;
    }
    if (modal) {
      modal.classList.add('hidden');
      modal.hidden = true;
    }
    document.body.classList.remove('ai-import-modal-open');
  }

  function setLibraryAskProgress(pct, stepIndex, stepLabel) {
    var bar = document.getElementById('ai-library-ask-bar');
    var barWrap = document.getElementById('ai-library-ask-bar-wrap');
    var stepEl = document.getElementById('ai-library-ask-step');
    var stepsEl = document.getElementById('ai-library-ask-steps');
    if (bar) bar.style.width = Math.min(100, Math.max(0, pct)) + '%';
    if (barWrap) barWrap.setAttribute('aria-valuenow', String(Math.round(pct)));
    if (stepEl) stepEl.textContent = stepLabel || LIBRARY_ASK_STEPS[stepIndex] || 'Procesare…';
    if (stepsEl) {
      stepsEl.innerHTML = LIBRARY_ASK_STEPS.map(function (label, idx) {
        var cls = idx < stepIndex ? 'is-done' : (idx === stepIndex ? 'is-active' : '');
        return '<li class="' + cls + '">' + esc(label) + '</li>';
      }).join('');
    }
  }

  function updateInlineMarketAnswer(json) {
    var data = (json && json.data) || {};
    var ansEl = document.getElementById('ai-rag-market-answer');
    var srcEl = document.getElementById('ai-rag-market-sources-used');
    if (ansEl) {
      ansEl.classList.remove('hidden');
      ansEl.textContent = data.answer || json.message || '—';
    }
    if (srcEl && data.sources) {
      srcEl.innerHTML = data.sources.map(function (s, i) {
        var url = s.url || '';
        return '<div class="src-item"><strong>#' + (i + 1) + ' ' + esc(s.origin || s.type || '') + '</strong> '
          + esc(s.title || s.source_type || s.processed || '')
          + (url ? ' — <a href="' + esc(url) + '" target="_blank" rel="noopener">' + esc(url) + '</a>' : '')
          + '<br><small>' + esc(s.scraped_at || '') + '</small></div>';
      }).join('');
    }
  }

  function renderLibraryAskResult(data, message) {
    var resultEl = document.getElementById('ai-library-ask-result');
    var copyBtn = document.getElementById('ai-library-ask-copy');
    var progressWrap = document.getElementById('ai-library-ask-progress-wrap');
    if (!resultEl) return;

    data = data || {};
    libraryAskLastAnswer = String(data.answer || message || '');

    var html = '';
    html += '<section class="ai-import-modal__section ai-library-ask-answer-wrap">';
    html += '<h3><i class="fa-solid fa-robot" aria-hidden="true"></i> Răspuns</h3>';
    html += '<div class="ai-library-ask-answer">' + esc(data.answer || message || '—') + '</div>';
    html += '<p class="ai-import-modal__hits">' + esc((data.hits_count || 0) + ' potriviri · corpus ' + (data.library_total || '—') + ' intrări') + '</p>';
    html += '</section>';

    var sources = data.sources || [];
    if (sources.length) {
      html += '<section class="ai-import-modal__section"><h3><i class="fa-solid fa-link" aria-hidden="true"></i> Surse citate</h3><ul class="ai-import-modal__list">';
      sources.forEach(function (s, i) {
        var url = s.url || '';
        var label = s.title || s.source_type || s.processed || s.type || 'sursă';
        html += '<li><strong>#' + (i + 1) + ' ' + esc(s.origin || s.type || '') + '</strong> — ' + esc(label);
        if (url) html += ' <a href="' + esc(url) + '" target="_blank" rel="noopener">' + esc(url) + '</a>';
        if (s.scraped_at) html += '<br><small>' + esc(s.scraped_at) + '</small>';
        html += '</li>';
      });
      html += '</ul></section>';
    }

    if (!data.answer && data.error) {
      html = '<section class="ai-import-modal__section"><div class="ai-import-modal__warn">' + esc(data.error) + '</div></section>';
    }

    resultEl.innerHTML = html;
    resultEl.classList.remove('hidden');
    if (progressWrap) progressWrap.classList.add('hidden');
    if (copyBtn) copyBtn.classList.toggle('hidden', !libraryAskLastAnswer);
  }

  function openLibraryAskModal(question) {
    var modal = document.getElementById('ai-library-ask-modal');
    var resultEl = document.getElementById('ai-library-ask-result');
    var progressWrap = document.getElementById('ai-library-ask-progress-wrap');
    var copyBtn = document.getElementById('ai-library-ask-copy');
    var subtitle = document.getElementById('ai-library-ask-subtitle');
    var queryEl = document.getElementById('ai-library-ask-query');
    if (!modal) return;

    if (subtitle) subtitle.textContent = 'Interogare RAG — corpus + bibliotecă agent';
    if (queryEl) {
      queryEl.innerHTML = '<span class="ai-library-ask-query__label"><i class="fa-solid fa-circle-question" aria-hidden="true"></i> Întrebare</span>'
        + '<p class="ai-library-ask-query__text">' + esc(question) + '</p>';
    }
    if (resultEl) { resultEl.classList.add('hidden'); resultEl.innerHTML = ''; }
    if (progressWrap) progressWrap.classList.remove('hidden');
    if (copyBtn) copyBtn.classList.add('hidden');
    libraryAskLastAnswer = '';

    modal.classList.remove('hidden');
    modal.hidden = false;
    document.body.classList.add('ai-import-modal-open');
    setLibraryAskProgress(6, 0, LIBRARY_ASK_STEPS[0]);

    var stepIdx = 0;
    var pct = 6;
    if (libraryAskModalTimer) clearInterval(libraryAskModalTimer);
    libraryAskModalTimer = setInterval(function () {
      pct = Math.min(92, pct + (stepIdx < 2 ? 4 : 3));
      if (pct > 18 && stepIdx < 1) stepIdx = 1;
      if (pct > 36 && stepIdx < 2) stepIdx = 2;
      if (pct > 54 && stepIdx < 3) stepIdx = 3;
      if (pct > 72 && stepIdx < 4) stepIdx = 4;
      setLibraryAskProgress(pct, stepIdx, LIBRARY_ASK_STEPS[stepIdx]);
    }, 700);
  }

  function finishLibraryAskModal(json, err) {
    if (libraryAskModalTimer) {
      clearInterval(libraryAskModalTimer);
      libraryAskModalTimer = null;
    }
    if (err) {
      setLibraryAskProgress(100, LIBRARY_ASK_STEPS.length - 1, 'Eroare');
      renderLibraryAskResult({ error: String(err.message || err) }, '');
      toast(String(err.message || err));
      return;
    }
    setLibraryAskProgress(100, LIBRARY_ASK_STEPS.length - 1, 'Finalizat');
    var data = (json && json.data) || {};
    setTimeout(function () {
      renderLibraryAskResult(data, json && json.message);
      updateInlineMarketAnswer(json);
      toast((json && json.message) || 'Răspuns generat');
    }, 300);
  }

  function runLibraryAsk(question) {
    question = String(question || '').trim();
    if (!question) {
      toast('Scrie o întrebare în câmp');
      return Promise.resolve();
    }
    var qEl = document.getElementById('ai-rag-market-question');
    if (qEl) qEl.value = question;

    openLibraryAskModal(question);
    return post({ action: 'market_ask', question: question }, 130000)
      .then(function (json) {
        if (!json.success) {
          return Promise.reject(new Error(json.message || 'Interogare eșuată'));
        }
        finishLibraryAskModal(json);
      })
      .catch(function (e) {
        finishLibraryAskModal(null, e);
      });
  }

  function bindLibraryAskModalEvents() {
    document.querySelectorAll('[data-library-ask-close]').forEach(function (el) {
      el.addEventListener('click', closeLibraryAskModal);
    });
    document.getElementById('ai-library-ask-copy')?.addEventListener('click', function () {
      if (!libraryAskLastAnswer) return;
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(libraryAskLastAnswer).then(function () { toast('Răspuns copiat'); });
      } else {
        toast('Copiere indisponibilă');
      }
    });
  }

  function bindImportSuggestModalEvents() {
    document.querySelectorAll('[data-import-modal-close]').forEach(function (el) {
      el.addEventListener('click', closeImportSuggestModal);
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') {
        closeImportSuggestModal();
        closeLibraryAskModal();
      }
    });
    document.getElementById('ai-import-modal-copy')?.addEventListener('click', function () {
      if (!importSuggestLastPayload) return;
      var text = JSON.stringify(importSuggestLastPayload, null, 2);
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(function () { toast('Sugestie copiată'); });
      } else {
        toast('Copiere indisponibilă în browser');
      }
    });
  }

  function bindImportPickerEvents() {
    document.getElementById('ai-rag-import-load')?.addEventListener('click', function () {
      loadImportProducts(1);
    });
    document.getElementById('ai-rag-import-filter')?.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        loadImportProducts(1);
      }
    });
    document.getElementById('ai-rag-import-image-filter')?.addEventListener('change', function () {
      if (importPickerState.loaded) loadImportProducts(1);
    });
    document.getElementById('ai-rag-import-prev')?.addEventListener('click', function () {
      if (importPickerState.page > 1) loadImportProducts(importPickerState.page - 1);
    });
    document.getElementById('ai-rag-import-next')?.addEventListener('click', function () {
      if (importPickerState.page < importPickerState.totalPages) loadImportProducts(importPickerState.page + 1);
    });
    document.getElementById('ai-rag-import-grid')?.addEventListener('click', function (e) {
      var card = e.target.closest('.ai-import-card');
      if (!card) return;
      var id = card.getAttribute('data-product-id');
      var product = importPickerState.items.find(function (p) { return p.id === id; });
      if (product) selectImportProduct(product);
    });
    document.getElementById('ai-rag-import-clear')?.addEventListener('click', function () {
      selectImportProduct(null);
    });
    document.getElementById('ai-rag-import-lookup')?.addEventListener('click', function () {
      var p = importPickerState.selected;
      if (!p) { toast('Selectează un produs din listă'); return; }
      runImportSuggestLookup(p);
    });
  }

  function collectSitesFromTable() {
    var sites = [];
    document.querySelectorAll('#ai-rag-sites-tbody tr').forEach(function (row) {
      var slot = parseInt(row.getAttribute('data-slot') || '0', 10);
      sites.push({
        slot: slot,
        name: (row.querySelector('.site-name') || {}).value || '',
        url: (row.querySelector('.site-url') || {}).value || '',
        category: (row.querySelector('.site-category') || {}).value || '',
        mission: (row.querySelector('.site-mission') || {}).value || '',
        tip_sursa: (row.querySelector('.site-tip') || {}).value || 'catalog_produse',
        status: (row.querySelector('.site-active') || {}).checked ? 'active' : 'paused',
        follow_links: true,
        max_follow: 3,
      });
    });
    return sites;
  }

  function renderSitesTable(data) {
    data = data || {};
    var cfg = data.config || data;
    var sites = cfg.sites || [];
    siteTipOptions = data.tip_options || {};
    var tbody = document.getElementById('ai-rag-sites-tbody');
    if (!tbody) return;

    clearAllAutocompletes();

    var tipOpts = Object.keys(siteTipOptions).length ? siteTipOptions : {
      catalog_produse: 'Catalog produse',
      pret_concurenta: 'Preț concurență',
      seo_keyword: 'SEO',
      specificatie_tehnica: 'Specificații',
      tendinta_piata: 'Tendințe',
    };

    tbody.innerHTML = sites.map(function (s) {
      var tipSelect = '<select class="site-tip">';
      Object.keys(tipOpts).forEach(function (k) {
        tipSelect += '<option value="' + esc(k) + '"' + (s.tip_sursa === k ? ' selected' : '') + '>' + esc(tipOpts[k]) + '</option>';
      });
      tipSelect += '</select>';
      var lastRun = s.last_run_at ? '<br><small class="ai-rag-meta">' + esc(s.last_run_at) + ' · ' + (s.last_fragments || 0) + ' frag</small>' : '';
      return '<tr data-slot="' + esc(s.slot) + '">' +
        '<td class="col-slot">' + esc(s.slot) + lastRun + '</td>' +
        '<td><input type="text" class="site-name" value="' + esc(s.name) + '" placeholder="ex: ePiesa ulei"></td>' +
        '<td><input type="url" class="site-url" value="' + esc(s.url) + '" placeholder="https://…"></td>' +
        '<td><input type="text" class="site-category" value="' + esc(s.category) + '" placeholder="Ulei motor"></td>' +
        '<td><textarea class="site-mission" placeholder="Ce să extragă: prețuri, SEO, cod OEM, titluri produse…">' + esc(s.mission) + '</textarea></td>' +
        '<td>' + tipSelect + '</td>' +
        '<td class="col-active"><input type="checkbox" class="site-active"' + (s.status === 'active' ? ' checked' : '') + '></td>' +
        '</tr>';
    }).join('');

    var activeCount = sites.filter(function (s) { return s.status === 'active' && s.url; }).length;
    var countEl = document.getElementById('ai-rag-sites-active-count');
    if (countEl) countEl.textContent = activeCount + ' active / 10';

    loadCategoryOptions().then(function () {
      initSitesTableAutocompletes();
    });

    var prog = data.progress || {};
    renderSitesRunProgress(prog);
    if (prog.status === 'running' && !sitesRunPollTimer) {
      startSitesRunPolling(true);
      setSitesRunBusy(true);
    }
  }

  function loadSitesConfig() {
    return get('library_sites_config').then(function (json) {
      renderSitesTable(json.data || {});
    });
  }

  function renderLibStats(data) {
    data = data || {};
    var t = data.totals || {};
    var set = function (id, v) { var el = document.getElementById(id); if (el) el.textContent = v == null ? '—' : String(v); };
    set('ai-rag-stat-total', t.all);
    set('ai-rag-stat-intern', t.intern);
    set('ai-rag-stat-extern', t.extern);
    set('ai-rag-stat-seo', t.seo);
    set('ai-rag-stat-scrape', t.scraped);
  }

  function renderLibList(data) {
    var listEl = document.getElementById('ai-rag-lib-list');
    var countEl = document.getElementById('ai-rag-lib-count');
    if (!listEl) return;
    data = data || {};
    var items = data.items || [];
    if (countEl) {
      countEl.textContent = 'Afișate ' + items.length + ' din ' + (data.total || 0) + ' potriviri';
    }
    if (!items.length) {
      listEl.innerHTML = '<p class="ai-rag-meta">Nicio intrare. Rulează „Generează 500” sau scrape URL, apoi reîmprospătează.</p>';
      return;
    }
    listEl.innerHTML = items.map(function (it) {
      var badge = it.origin === 'intern' ? 'intern' : 'extern';
      var seo = it.has_seo ? '<span class="ai-rag-badge ai-rag-badge--ok">SEO</span>' : '';
      var kws = (it.keywords || []).slice(0, 6).map(function (k) { return '<span class="ai-rag-kw">' + esc(k) + '</span>'; }).join(' ');
      return '<article class="ai-rag-lib-item" data-id="' + esc(it.id) + '">' +
        '<div class="ai-rag-lib-item__head">' +
        '<span class="ai-rag-badge ai-rag-badge--origin-' + badge + '">' + esc(badge) + '</span> ' +
        '<span class="ai-rag-badge">' + esc(it.source_type) + '</span> ' + seo +
        '<strong>' + esc(it.title || '(fără titlu)') + '</strong></div>' +
        '<p class="ai-rag-lib-item__text">' + esc(it.text_preview) + '</p>' +
        (it.url ? '<a href="' + esc(it.url) + '" target="_blank" rel="noopener" class="ai-rag-lib-link">' + esc(it.url) + '</a>' : '') +
        (kws ? '<div class="ai-rag-lib-kws">' + kws + '</div>' : '') +
        '<small class="ai-rag-meta">' + esc(it.at) + '</small></article>';
    }).join('');
  }

  function browseLibrary(reset) {
    if (reset) libBrowseOffset = 0;
    var params = {
      q: (document.getElementById('ai-rag-lib-search') || {}).value || '',
      origin: (document.getElementById('ai-rag-lib-origin') || {}).value || '',
      source_type: (document.getElementById('ai-rag-lib-type') || {}).value || '',
      offset: libBrowseOffset,
      limit: 20,
    };
    if (document.getElementById('ai-rag-lib-seo-only') && document.getElementById('ai-rag-lib-seo-only').checked) {
      params.has_seo = '1';
    }
    return get('library_browse', params).then(function (json) {
      var data = json.data || {};
      var listEl = document.getElementById('ai-rag-lib-list');
      if (!listEl) return;
      if (reset) {
        renderLibList(data);
      } else if (data.items && data.items.length) {
        var extra = document.createElement('div');
        extra.innerHTML = data.items.map(function (it) {
          var badge = it.origin === 'intern' ? 'intern' : 'extern';
          var kws = (it.keywords || []).slice(0, 4).map(function (k) { return esc(k); }).join(', ');
          return '<article class="ai-rag-lib-item" data-id="' + esc(it.id) + '"><div class="ai-rag-lib-item__head"><span class="ai-rag-badge">' + esc(badge) + '</span> <strong>' + esc(it.title) + '</strong></div><p>' + esc(it.text_preview) + '</p><small>' + esc(kws) + '</small></article>';
        }).join('');
        while (extra.firstChild) listEl.appendChild(extra.firstChild);
        var countEl = document.getElementById('ai-rag-lib-count');
        if (countEl) countEl.textContent = 'Afișate ' + listEl.querySelectorAll('.ai-rag-lib-item').length + ' din ' + (data.total || 0);
      }
      var moreBtn = document.getElementById('ai-rag-lib-more');
      if (moreBtn) {
        var shown = listEl.querySelectorAll('.ai-rag-lib-item').length;
        moreBtn.classList.toggle('hidden', shown >= (data.total || 0));
      }
    });
  }

  function renderMarket(data) {
    data = data || {};
    renderLibStats(data);
    var research = data.research || {};
    var stealthEl = document.getElementById('ai-rag-market-stealth');
    if (stealthEl) {
      stealthEl.innerHTML = research.stealth_available
        ? '<span class="ai-rag-pill ai-rag-pill--ok">stealth-browser disponibil</span>'
        : '<span class="ai-rag-pill ai-rag-pill--fail">Stealth indisponibil</span>';
    }
    var srcEl = document.getElementById('ai-rag-market-sources');
    var sources = research.sources || [];
    if (srcEl) {
      if (!sources.length) {
        srcEl.innerHTML = '<p class="ai-rag-meta">Surse pilot — configurează în JSON sau adaugă URL mai sus.</p>';
      } else {
        var html = '<table><thead><tr><th>Sursă</th><th>Status</th><th>Ultim OK</th></tr></thead><tbody>';
        sources.forEach(function (s) {
          html += '<tr><td>' + esc(s.name) + '</td><td>' + esc(s.status) + '</td><td>' + esc((s.stats && s.stats.last_ok) || '—') + '</td></tr>';
        });
        html += '</tbody></table>';
        srcEl.innerHTML = html;
      }
    }
    var prog = research.progress || data.progress || {};
    var progEl = document.getElementById('ai-rag-market-progress');
    if (progEl) {
      progEl.textContent = prog.status === 'done'
        ? ('Ultim job: +' + (prog.added || 0) + ' / ' + (prog.failed || 0) + ' eșecuri')
        : (prog.status === 'running' ? (prog.current || 'În curs…') : 'Corpus: ' + ((data.totals && data.totals.all) || 0) + ' intrări');
    }
  }

  function loadMarket() {
    return Promise.all([
      get('market_dashboard').then(function (json) {
        renderMarket(json.data);
      }),
      loadSitesConfig(),
      browseLibrary(true),
    ]);
  }

  function loadAll() {
    var tasks = [loadLogs(), loadSummaries()];
    if (document.getElementById('ai-rag-vector-detail') || document.getElementById('ai-rag-corpus-status')) {
      tasks.push(loadVectorDetail());
    }
    if (document.getElementById('ai-rag-sites-tbody')) {
      tasks.push(loadSitesConfig());
    }
    if (document.getElementById('ai-rag-lib-list')) {
      tasks.push(get('market_dashboard').then(function (json) { renderLibStats(json.data); }));
    }
    return Promise.all(tasks);
  }

  function bindEvents() {
    bindImportSuggestModalEvents();
    bindLibraryAskModalEvents();
    bindImportPickerEvents();

    document.getElementById('aiwork-feedback-submit')?.addEventListener('click', submitAiWorkFeedback);
    document.querySelectorAll('[data-aiwork-close]').forEach(function (el) {
      el.addEventListener('click', closeAiWorkFeedbackModal);
    });
    document.getElementById('aiwork-feed')?.addEventListener('click', function (ev) {
      var btn = ev.target && ev.target.closest ? ev.target.closest('[data-aiwork-bad]') : null;
      if (!btn) return;
      openAiWorkFeedbackModal({
        source_type: btn.getAttribute('data-source-type') || '',
        source_id: btn.getAttribute('data-source-id') || '',
        section: btn.getAttribute('data-section') || '',
        summary: btn.getAttribute('data-summary') || '',
        action: btn.getAttribute('data-action') || '',
      });
    });

    document.querySelectorAll('.ai-rag-tab').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var tab = btn.getAttribute('data-tab') || 'dashboard';
        switchTab(tab);
        if (tab === 'market') loadMarket();
      });
    });

    // Refresh-ul e deținut de admin-ai-hub.js (#ai-hub-refresh), care emite
    // evenimentul `ai-hub:refresh` — ascultat mai jos în init(). Nu mai legăm
    // direct nimic aici, ca să nu dublăm apelul loadAll() la un singur click.

    document.getElementById('ai-rag-preview-sources')?.addEventListener('click', function () {
      get('log_sources_preview', { lines: 40 }).then(function (json) {
        var pre = document.getElementById('ai-rag-summary-output');
        if (pre) {
          pre.classList.remove('hidden');
          pre.textContent = JSON.stringify(json.data, null, 2);
        }
        toast('Preview surse log');
      }).catch(function (e) { toast(String(e.message)); });
    });

    document.getElementById('ai-rag-generate-summary')?.addEventListener('click', function () {
      var btn = document.getElementById('ai-rag-generate-summary');
      if (btn) btn.disabled = true;
      toast('Generez sumar… (LLM sau fallback local 30–120 sec)');
      post({ action: 'generate_log_summary', log_lines: 80 }, 150000).then(function (json) {
        var pre = document.getElementById('ai-rag-summary-output');
        if (pre) {
          pre.classList.remove('hidden');
          var structured = json.data && json.data.structured;
          pre.textContent = structured
            ? JSON.stringify(structured, null, 2)
            : JSON.stringify(json.data, null, 2);
        }
        var note = json.data && json.data.fallback ? ' (sumar local — Ollama offline)' : '';
        toast((json.message || 'Gata') + note);
        loadSummaries();
        loadAiWorkFeed();
        if (document.getElementById('ai-rag-stat-total')) loadDashboard();
      }).catch(function (e) { toast(String(e.message)); })
        .finally(function () { if (btn) btn.disabled = false; });
    });

    document.getElementById('ai-rag-seed-corpus')?.addEventListener('click', function () {
      toast('Generez 500 elemente RAG…');
      post({ action: 'bulk_seed_corpus', target: 500, index_embeddings: true, agent_slug: 'agent-produse' }, 300000)
        .then(function (json) {
          var pre = document.getElementById('ai-rag-seed-output');
          if (pre) { pre.classList.remove('hidden'); pre.textContent = JSON.stringify(json.data, null, 2); }
          toast(json.message || 'Seed OK');
          loadVectorDetail();
          loadDashboard();
        }).catch(function (e) { toast(String(e.message)); });
    });

    document.getElementById('ai-rag-index-embeddings')?.addEventListener('click', function () {
      post({ action: 'index_products', limit: 500 }, 300000).then(function (json) {
        toast(json.message || 'Embeddings OK');
        loadVectorDetail();
      });
    });

    document.getElementById('ai-rag-lib-browse')?.addEventListener('click', function () {
      browseLibrary(true).then(function () { toast('Bibliotecă actualizată'); });
    });

    document.getElementById('ai-rag-lib-more')?.addEventListener('click', function () {
      libBrowseOffset += 20;
      browseLibrary(false);
    });

    document.getElementById('ai-rag-lib-list')?.addEventListener('click', function (e) {
      var item = e.target.closest('.ai-rag-lib-item');
      if (!item) return;
      var id = item.getAttribute('data-id');
      get('library_entry', { id: id }).then(function (json) {
        var pre = document.getElementById('ai-rag-lib-detail');
        if (pre) {
          pre.classList.remove('hidden');
          pre.textContent = JSON.stringify(json.data, null, 2);
        }
      });
    });

    document.getElementById('ai-rag-sites-save')?.addEventListener('click', function () {
      post({ action: 'library_save_sites', sites: collectSitesFromTable() }).then(function (json) {
        toast(json.message || 'Config salvat');
        loadSitesConfig();
      }).catch(function (e) { toast(String(e.message)); });
    });

    document.getElementById('ai-rag-sites-run')?.addEventListener('click', function () {
      var sites = collectSitesFromTable();
      var active = sites.filter(function (s) { return s.status === 'active' && s.url; });
      if (!active.length) { toast('Bifează Activ și pune URL la cel puțin un site'); return; }
      setSitesRunBusy(true);
      startSitesRunPolling();
      toast('Rulez ' + active.length + ' site-uri (poate dura 10–30 min)…');
      post({ action: 'library_run_sites', sites: sites }, 900000).then(function (json) {
        stopSitesRunPolling();
        setSitesRunBusy(false);
        var prog = (json.data && json.data.progress) || {};
        renderSitesRunProgress(prog);
        toast(json.message || 'Gata');
        loadSitesConfig();
        loadMarket();
      }).catch(function (e) {
        stopSitesRunPolling();
        setSitesRunBusy(false);
        pollSitesProgress();
        toast(String(e.message));
      });
    });

    document.getElementById('ai-rag-market-ask')?.addEventListener('click', function () {
      var q = document.getElementById('ai-rag-market-question');
      runLibraryAsk(q ? q.value : '');
    });

    document.getElementById('ai-rag-market-question')?.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        runLibraryAsk(e.target.value);
      }
    });

    // Când există hub-ul (admin-ai-hub.js), acesta gestionează deja `.ai-hub-chip`
    // (apelează AiRag.askLibrary). Legăm doar `.ai-rag-chip` ca să nu declanșăm
    // market_ask de două ori. În pagina legacy (fără hub) legăm ambele.
    var chipSelector = document.getElementById('ai-hub-root')
      ? '.ai-rag-chip'
      : '.ai-hub-chip, .ai-rag-chip';
    document.querySelectorAll(chipSelector).forEach(function (chip) {
      chip.addEventListener('click', function () {
        runLibraryAsk(chip.getAttribute('data-q') || '');
      });
    });

  }

  function init() {
    if (!document.getElementById('ai-hub-root') && !document.getElementById('ai-rag-root')) return;
    bindEvents();
    loadAll().catch(function () { /* 401 → redirect login */ });
    document.addEventListener('ai-hub:refresh', function () { loadAll(); });
    document.addEventListener('ai-hub:lib-tools', function () {
      loadVectorDetail();
      loadSitesConfig();
      if (!importPickerState.loaded) loadImportProducts(1);
    });
    document.addEventListener('ai-hub:tab', function (e) {
      var tab = (e.detail && e.detail.tab) || '';
      if (tab === 'library') loadAll();
      if (tab === 'system') loadSystemTab();
      if (tab !== 'system') stopAiWorkPolling();
    });
    document.addEventListener('ai-hub:system-refresh', function () {
      loadSystemTab();
    });

    // Hub emite ai-hub:tab înainte ca listener-ul de mai sus să existe (?tab=system la refresh)
    var hubRoot = document.getElementById('ai-hub-root');
    var activeTab = (hubRoot && hubRoot.getAttribute('data-active-tab'))
      || new URLSearchParams(window.location.search).get('tab')
      || '';
    if (activeTab === 'system' || activeTab === 'sistem' || activeTab === 'audit' || activeTab === 'monitor') {
      loadSystemTab();
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  window.AiRag = window.AiRag || {};
  window.AiRag.askLibrary = runLibraryAsk;
})();
