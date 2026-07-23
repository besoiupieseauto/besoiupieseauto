/**

 * Asistent Composer 2.5 — contextual pe secțiune admin (produse, furnizori, import).

 */

(function () {

  'use strict';



  if (window.__besoiuSectionAssistInit) return;

  window.__besoiuSectionAssistInit = true;



  function cfg() {

    var el = document.getElementById('besoiu-section-assist-cfg');

    if (!el) return null;

    try { return JSON.parse(el.textContent || '{}'); } catch (e) { return null; }

  }



  var C = cfg();

  if (!C || !C.api) return;

  function chatCan(key) {
    var perms = C.chat_permissions;
    if (!Array.isArray(perms) || !perms.length) return true;
    return perms.indexOf(key) >= 0;
  }



  function esc(s) {

    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {

      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];

    });

  }



  var BOT_NAME = 'besoiupieseauto';

  var root = document.createElement('div');

  root.id = 'besoiu-section-assist';

  root.className = 'bsa-root';

  root.innerHTML =

    '<button type="button" class="bsa-fab" id="bsa-fab" aria-label="' + esc(BOT_NAME) + '">'

    + '<span class="bsa-fab__icon">●</span><span class="bsa-fab__label">' + esc(BOT_NAME) + '</span>'
    + '<span class="bsa-fab__conn" id="bsa-conn" title="Conexiune API"></span></button>'

    + '<div class="bsa-panel hidden" id="bsa-panel" role="dialog" aria-label="Chat ' + esc(BOT_NAME) + '">'

    + '<div class="bsa-panel__head">'

    + '<div class="bsa-bot-head">'

    + '<span class="bsa-bot-avatar" aria-hidden="true">B</span>'

    + '<div><strong>' + esc(BOT_NAME) + '</strong>'

    + '<span class="bsa-panel__sub">organism live · ' + esc(C.label || C.section) + '</span></div>'

    + '</div>'

    + '<button type="button" class="bsa-close" id="bsa-close" aria-label="Inchide">×</button>'

    + '</div>'

    + '<div class="bsa-scorecard" id="bsa-scorecard" aria-live="polite">'

    + '<div class="bsa-scorecard__loading">Supervisor…</div>'

    + '</div>'

    + '<div class="bsa-panel__body">'

    + '<div class="bsa-panel__scroll" id="bsa-scroll">'

    + '<div class="bsa-chat" id="bsa-chat"></div>'

    + '<details class="bsa-organism-wrap" id="bsa-organism-wrap">'

    + '<summary>Capabilitati organism</summary>'

    + '<div class="bsa-organism" id="bsa-organism"><div class="bsa-organism__loading">Se incarca…</div></div>'

    + '</details>'

    + '<details class="bsa-test-log-wrap" id="bsa-test-log-wrap">'

    + '<summary>Jurnal teste sesiune (<span id="bsa-test-log-count">0</span>)</summary>'

    + '<div class="bsa-test-log" id="bsa-test-log"></div>'

    + '</details>'

    + '</div>'

    + '<div class="bsa-panel__composer bsa-chat-bar">'

    + '<input type="text" id="bsa-input" autocomplete="off" placeholder="Scrie lui ' + esc(BOT_NAME) + '…" />'

    + '<button type="button" class="bsa-send" id="bsa-send" aria-label="Trimite">➤</button>'

    + '</div>'

    + '</div></div>';



  document.body.appendChild(root);



  var fab = document.getElementById('bsa-fab');

  var panel = document.getElementById('bsa-panel');

  var closeBtn = document.getElementById('bsa-close');

  var input = document.getElementById('bsa-input');

  var sendBtn = document.getElementById('bsa-send');

  var chatEl = document.getElementById('bsa-chat');

  var scrollEl = document.getElementById('bsa-scroll');

  var thinkingEl = null;

  var lastMessage = '';

  var lastPendingAction = null;

  var lastQueryId = null;

  var connEl = document.getElementById('bsa-conn');

  var organismEl = document.getElementById('bsa-organism');

  var scorecardEl = document.getElementById('bsa-scorecard');

  var testLogEl = document.getElementById('bsa-test-log');

  var testLogCountEl = document.getElementById('bsa-test-log-count');

  var testLogWrapEl = document.getElementById('bsa-test-log-wrap');

  var organismLoaded = false;

  var scorecardLoaded = false;

  var pingTimer = null;

  var sessionId = '';

  var conversationHistory = [];

  var sessionTestLog = [];



  try {

    sessionId = sessionStorage.getItem('bsa-session-id') || '';

    if (!sessionId) {

      sessionId = 'bsa-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 8);

      sessionStorage.setItem('bsa-session-id', sessionId);

    }

    var storedConv = sessionStorage.getItem('bsa-conversation-' + sessionId);

    if (storedConv) conversationHistory = JSON.parse(storedConv) || [];

    var storedTests = sessionStorage.getItem('bsa-test-log-' + sessionId);

    if (storedTests) sessionTestLog = JSON.parse(storedTests) || [];

  } catch (eInit) { /* ignore */ }



  function persistConversation() {

    try {

      sessionStorage.setItem('bsa-conversation-' + sessionId, JSON.stringify(conversationHistory));

    } catch (eSave) { /* ignore */ }

  }



  function persistTestLog() {

    try {

      sessionStorage.setItem('bsa-test-log-' + sessionId, JSON.stringify(sessionTestLog.slice(-40)));

    } catch (eLog) { /* ignore */ }

  }



  function checkStatusIcon(status) {

    if (status === 'pass') return '✓';

    if (status === 'warn') return '~';

    return '✗';

  }



  function renderChecksHtml(checks) {

    if (!checks || !checks.length) return '';

    var html = '<ul class="bsa-training__checks">';

    checks.forEach(function (c) {

      var st = c.status || 'fail';

      html += '<li class="bsa-training__check bsa-training__check--' + esc(st) + '">'

        + '<span class="bsa-training__check-icon" aria-hidden="true">' + checkStatusIcon(st) + '</span>'

        + '<span class="bsa-training__check-body"><strong>' + esc(c.label || 'Verificare') + '</strong>'

        + '<span>' + esc(c.detail || '') + '</span></span></li>';

    });

    html += '</ul>';

    return html;

  }



  function renderFeedbackLogPanel(fb, meta) {

    meta = meta || {};

    if (!fb) return '';

    var log = fb.evaluation_log || {};

    var summary = log.summary || {};

    var html = '<div class="bsa-training__log">';

    html += '<div class="bsa-training__log-head"><strong>Log evaluare</strong>';

    if (summary.total) {

      html += '<span class="bsa-training__log-stats">'

        + esc(String(summary.passed || 0)) + ' ok · '

        + esc(String(summary.failed || 0)) + ' esec'

        + (summary.warnings ? (' · ' + esc(String(summary.warnings)) + ' atentie') : '')

        + '</span>';

    }

    html += '</div>';

    if (meta.message) {

      html += '<div class="bsa-training__log-row"><span class="bsa-training__label">Mesaj testat</span><p>' + esc(meta.message) + '</p></div>';

    }

    if (fb.source) {

      html += '<div class="bsa-training__log-row"><span class="bsa-training__label">Sursa</span><p>' + esc(fb.source) + '</p></div>';

    }

    if (fb.correct_items && fb.correct_items.length) {

      html += '<div class="bsa-training__block bsa-training__block--ok"><span class="bsa-training__label">Corect</span><ul>';

      fb.correct_items.forEach(function (x) { html += '<li>' + esc(x) + '</li>'; });

      html += '</ul></div>';

    }

    if (fb.checks && fb.checks.length) {

      html += '<div class="bsa-training__block"><span class="bsa-training__label">Verificari</span>';

      html += renderChecksHtml(fb.checks);

      html += '</div>';

    }

    if (meta.operator_rating) {

      html += '<div class="bsa-training__log-row bsa-training__log-row--operator"><span class="bsa-training__label">Verdict operator</span><p>'

        + esc(meta.operator_rating === 'ok' ? 'Corect' : (meta.operator_rating === 'partial' ? 'Partial' : 'Nu e bine'))

        + (meta.operator_rated_at ? (' · ' + esc(meta.operator_rated_at)) : '')

        + '</p></div>';

    }

    if (meta.query_id) {

      html += '<div class="bsa-training__log-id">ID: ' + esc(meta.query_id) + '</div>';

    }

    html += '</div>';

    return html;

  }



  function renderTestLogEntry(entry) {

    if (!entry) return '';

    var fb = entry.feedback || {};

    var rating = entry.operator_rating || fb.operator_rating || fb.status || 'auto';

    var cls = rating === 'ok' || fb.badge_ok ? 'bsa-test-log__item--ok' : (rating === 'partial' || fb.status === 'partial' ? 'bsa-test-log__item--partial' : 'bsa-test-log__item--fail');

    var html = '<details class="bsa-test-log__item ' + cls + '">';

    html += '<summary><span class="bsa-test-log__badge">' + esc(rating === 'ok' ? 'OK' : (rating === 'partial' ? '~' : '!')) + '</span>';

    html += '<span class="bsa-test-log__msg">' + esc(entry.message || fb.evaluation_log && fb.evaluation_log.message_excerpt || 'Test') + '</span>';

    html += '<span class="bsa-test-log__time">' + esc(entry.at || entry.operator_rated_at || '') + '</span></summary>';

    html += renderFeedbackLogPanel(fb, {

      message: entry.message || '',

      operator_rating: entry.operator_rating || '',

      operator_rated_at: entry.operator_rated_at || '',

      query_id: entry.query_id || entry.id || '',

    });

    html += '</details>';

    return html;

  }



  function syncTestLogPanel() {

    if (!testLogEl) return;

    if (!sessionTestLog.length) {

      testLogEl.innerHTML = '<p class="bsa-test-log__empty">Niciun test evaluat inca. Apasa Corect sau Nu e bine dupa fiecare raspuns.</p>';

    } else {

      testLogEl.innerHTML = sessionTestLog.slice().reverse().map(renderTestLogEntry).join('');

    }

    if (testLogCountEl) testLogCountEl.textContent = String(sessionTestLog.length);

  }



  function appendTestLogEntry(payload) {

    if (!payload) return;

    var fb = payload.feedback || {};

    var entry = {

      id: payload.query_id || (payload.query && payload.query.id) || '',

      query_id: payload.query_id || (payload.query && payload.query.id) || '',

      at: (payload.query && payload.query.at) || new Date().toISOString(),

      message: (payload.query && payload.query.message) || '',

      operator_rating: payload.operator_rating || '',

      operator_rated_at: (payload.query && payload.query.operator_rated_at) || new Date().toISOString(),

      feedback: fb,

    };

    sessionTestLog.push(entry);

    if (sessionTestLog.length > 40) sessionTestLog = sessionTestLog.slice(-40);

    persistTestLog();

    syncTestLogPanel();

    if (testLogWrapEl && !testLogWrapEl.open) testLogWrapEl.open = true;

  }



  syncTestLogPanel();



  function setConnStatus(state) {

    if (!connEl) return;

    connEl.classList.remove('is-online', 'is-offline', 'is-busy');

    if (state === 'online') {

      connEl.classList.add('is-online');

      connEl.title = 'Conectat la API admin';

    } else if (state === 'busy') {

      connEl.classList.add('is-busy');

      connEl.title = 'Composer proceseaza…';

    } else if (state === 'offline') {

      connEl.classList.add('is-offline');

      connEl.title = 'Conexiune intrerupta — reincerc automat';

    } else {

      connEl.title = 'Verific conexiunea…';

    }

  }



  function pingConnection() {
    // ping ieftin — NU ops_alerts (acela încărca DashboardService + TecDoc HTTP pe fiecare pagină)
    fetch(C.api + '?action=ping', { credentials: 'same-origin', cache: 'no-store' })
      .then(function (r) {
        if (!r.ok) throw new Error('HTTP ' + r.status);
        return r.json();
      })
      .then(function (j) {
        setConnStatus(j && j.success ? 'online' : 'offline');
      })
      .catch(function () {
        setConnStatus('offline');
      });
  }



  function schedulePing() {

    if (pingTimer) clearInterval(pingTimer);

    pingTimer = setInterval(pingConnection, 45000);

  }



  function toggle(open) {

    var show = open == null ? panel.classList.contains('hidden') : open;

    panel.classList.toggle('hidden', !show);

    fab.classList.toggle('is-open', show);

    try {

      sessionStorage.setItem('bsa-panel-open', show ? '1' : '0');

    } catch (e) { /* ignore */ }

    if (show && input) input.focus();

    if (show) loadOrganismCapabilities();

    if (show) loadSupervisorScorecard();

    if (show) ensureWelcome();

  }



  function scrollChatToBottom() {

    if (!scrollEl) return;

    requestAnimationFrame(function () {

      scrollEl.scrollTop = scrollEl.scrollHeight;

    });

  }



  function appendChatBubble(role, innerHtml, extraClass) {

    if (!chatEl) return null;

    var wrap = document.createElement('div');

    wrap.className = 'bsa-msg bsa-msg--' + role + (extraClass ? ' ' + extraClass : '');

    if (role === 'bot') {

      wrap.innerHTML = '<div class="bsa-msg__avatar">B</div>'

        + '<div class="bsa-msg__content">'

        + '<div class="bsa-msg__who">' + esc(BOT_NAME) + '</div>'

        + '<div class="bsa-msg__body">' + innerHtml + '</div></div>';

    } else {

      wrap.innerHTML = '<div class="bsa-msg__content bsa-msg__content--user">'

        + '<div class="bsa-msg__body">' + esc(innerHtml) + '</div></div>';

    }

    chatEl.appendChild(wrap);

    scrollChatToBottom();

    return wrap;

  }



  function appendUserMessage(text) {

    return appendChatBubble('user', text);

  }



  function showThinking() {

    hideThinking();

    thinkingEl = appendChatBubble(

      'bot',

      '<div class="bsa-thinking"><span class="bsa-thinking__pulse"></span> '

      + esc(BOT_NAME) + ' se gandeste…</div>',

      'bsa-msg--thinking'

    );

    setConnStatus('busy');

  }



  function hideThinking() {

    if (thinkingEl && thinkingEl.parentNode) {

      thinkingEl.parentNode.removeChild(thinkingEl);

    }

    thinkingEl = null;

    if (connEl && connEl.classList.contains('is-busy')) {

      setConnStatus('online');

    }

  }



  function appendBotHtml(html) {

    hideThinking();

    return appendChatBubble('bot', html, 'bsa-msg--rich');

  }



  function ensureWelcome() {

    if (!chatEl || chatEl.getAttribute('data-welcome') === '1') return;

    chatEl.setAttribute('data-welcome', '1');

    appendBotHtml(

      '<p class="bsa-chat-intro">Buna! Sunt <strong>' + esc(BOT_NAME) + '</strong> — organismul tau live pe '

      + esc(C.label || C.section) + '.</p>'

      + '<p class="bsa-chat-intro">Scrie natural sau alege rapid:</p>'

      + '<div class="bsa-chat-chips">'

      + '<button type="button" class="bsa-chat-chip" data-chip="ce e pe vitrina">vitrina</button>'

      + '<button type="button" class="bsa-chat-chip" data-chip="produse fara imagine">fara imagine</button>'

      + '<button type="button" class="bsa-chat-chip" data-chip="cate produse sunt online">inventar</button>'

      + '<button type="button" class="bsa-chat-chip" data-chip="lista tuturor produselor">lista produse</button>'

      + '</div>'

    );

  }



  function loadOrganismCapabilities() {

    if (!organismEl || organismLoaded) return;

    var section = C.section || '';

    fetch(C.api + '?action=chat_organism_capabilities&section=' + encodeURIComponent(section))

      .then(function (r) { return r.json(); })

      .then(function (j) {

        if (!j || !j.success || !j.data) {

          organismEl.innerHTML = '<p class="bsa-organism__empty">Organism indisponibil.</p>';

          return;

        }

        organismLoaded = true;

        renderOrganismPanel(j.data);

      })

      .catch(function () {

        organismEl.innerHTML = '<p class="bsa-organism__empty">Eroare la incarcarea capabilitatilor.</p>';

      });

  }



  function loadSupervisorScorecard(force) {

    if (!scorecardEl) return;

    if (scorecardLoaded && !force) return;

    var section = C.section || '';

    fetch(C.api + '?action=section_assist_scorecard&section=' + encodeURIComponent(section))

      .then(function (r) { return r.json(); })

      .then(function (j) {

        if (!j || !j.success || !j.data) {

          scorecardEl.innerHTML = '<div class="bsa-scorecard__empty">Supervisor indisponibil</div>';

          return;

        }

        scorecardLoaded = true;

        renderSupervisorScorecard(j.data);

      })

      .catch(function () {

        scorecardEl.innerHTML = '<div class="bsa-scorecard__empty">Eroare scorecard</div>';

      });

  }



  function renderSupervisorScorecard(data) {

    if (!scorecardEl || !data) return;

    var grade = esc(String(data.grade || '?'));

    var score = esc(String(data.score != null ? data.score : ''));

    var tiles = data.tiles || [];

    var supervisorUrl = C.supervisorUrl || '';

    var html = '<div class="bsa-scorecard__grade bsa-scorecard__grade--' + grade.toLowerCase() + '" title="Scor ' + score + '/100">'

      + grade + '</div>';

    html += '<div class="bsa-scorecard__tiles">';

    tiles.forEach(function (tile) {

      var st = esc(String(tile.status || 'ok'));

      html += '<div class="bsa-scorecard__tile bsa-scorecard__tile--' + st + '" title="' + esc(String(tile.hint || '')) + '">'

        + '<span class="bsa-scorecard__tile-label">' + esc(String(tile.label || '')) + '</span>'

        + '<span class="bsa-scorecard__tile-value">' + esc(String(tile.value || '')) + '</span>'

        + '</div>';

    });

    html += '</div>';

    if (supervisorUrl) {

      html += '<a class="bsa-scorecard__link" href="' + esc(supervisorUrl) + '" target="_blank" rel="noopener">Supervisor</a>';

    }

    scorecardEl.innerHTML = html;

  }



  function renderOrganismPanel(data) {

    if (!organismEl) return;

    var stats = data.stats || {};

    var modules = data.modules || [];

    var org = data.organism || {};

    var html = '<div class="bsa-organism__head">'

      + '<strong>' + esc(org.name || 'Besoiu Organism') + '</strong>'

      + '<span class="bsa-organism__stats">'

      + esc(String(stats.live || 0)) + ' live · '

      + esc(String(stats.navigate_only || 0)) + ' navigare · '

      + esc(String(stats.modules || 0)) + ' module'

      + '</span></div>';

    html += '<p class="bsa-organism__hint">Click pe exemplu — trimite comanda 1:1 modul admin.</p>';

    modules.forEach(function (mod) {

      html += '<details class="bsa-organism__mod">'

        + '<summary><span>' + esc(mod.label || mod.key) + '</span>'

        + '<em>' + esc(String((mod.counts && mod.counts.live) || 0)) + '/' + esc(String((mod.counts && mod.counts.total) || 0)) + '</em>'

        + '</summary><div class="bsa-organism__caps">';

      (mod.capabilities || []).forEach(function (cap) {

        var kind = cap.kind || 'query';

        var st = cap.status || 'live';

        var badge = st === 'live' ? kind : 'nav';

        html += '<div class="bsa-organism__cap">'

          + '<span class="bsa-organism__cap-label">' + esc(cap.label || cap.id) + '</span>'

          + '<span class="bsa-organism__cap-badge bsa-organism__cap-badge--' + esc(badge) + '">' + esc(badge) + '</span>';

        (cap.examples || []).slice(0, 2).forEach(function (ex) {

          html += '<button type="button" class="bsa-organism__ex" data-example="' + esc(ex) + '">' + esc(ex) + '</button>';

        });

        html += '</div>';

      });

      html += '</div></details>';

    });

    organismEl.innerHTML = html;

    organismEl.querySelectorAll('.bsa-organism__ex').forEach(function (btn) {

      btn.addEventListener('click', function () {

        var ex = btn.getAttribute('data-example') || '';

        if (input && ex) {

          input.value = ex;

          input.focus();

          send(false);

        }

      });

    });

    var orgHead = organismEl.querySelector('.bsa-organism__head');

    if (orgHead) {

      orgHead.addEventListener('click', function () {

        organismEl.classList.toggle('bsa-organism--compact');

      });

    }

  }



  fab.addEventListener('click', function () { toggle(); });

  closeBtn.addEventListener('click', function () { toggle(false); });



  function getSelectedProductRows(container) {
    var root = container || chatEl || scrollEl;
    if (!root) return [];
    var rows = [];
    root.querySelectorAll('.bsa-cheat__sel-row:checked').forEach(function (cb) {
      rows.push({
        randomn_id: cb.getAttribute('data-randomn-id') || '',
        edit_url: cb.getAttribute('data-edit-url') || '',
        code: cb.getAttribute('data-code') || '',
        name: cb.getAttribute('data-name') || '',
      });
    });
    return rows;
  }

  function attachSelectedProducts(body) {
    var selected = getSelectedProductRows();
    if (selected.length) {
      body.selected_products = selected;
    }
    return body;
  }

  function handleBulkAction(btn) {
    var action = btn.getAttribute('data-bulk-action') || '';
    var needsSel = btn.getAttribute('data-requires-selection') === '1';
    var selected = getSelectedProductRows();
    if (needsSel && !selected.length) {
      alert('Selecteaza cel putin un rand din lista.');
      return;
    }
    if (action === 'copy_codes') {
      var codes = selected.map(function (r) { return r.code; }).filter(Boolean).join('\n');
      if (!codes) {
        alert('Niciun cod de copiat.');
        return;
      }
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(codes).then(function () {
          btn.textContent = 'Copiat!';
          setTimeout(function () { btn.textContent = 'Copiaza coduri'; }, 1500);
        }).catch(function () { prompt('Coduri:', codes); });
      } else {
        prompt('Coduri:', codes);
      }
      return;
    }
    if (action === 'edit_selected') {
      selected.slice(0, 5).forEach(function (r) {
        if (r.edit_url) window.open(r.edit_url, '_blank', 'noopener');
      });
      if (selected.length > 5) {
        alert('Deschise primele 5 produse. Foloseste „Deschide in admin” pentru lista completa.');
      }
    }
  }



  (chatEl || scrollEl).addEventListener('click', function (event) {

    var bulkBtn = event.target && event.target.closest ? event.target.closest('.bsa-cheat__btn--bulk[data-bulk-action]') : null;
    if (bulkBtn) {
      event.preventDefault();
      handleBulkAction(bulkBtn);
      return;
    }

    var selAll = event.target && event.target.classList && event.target.classList.contains('bsa-cheat__sel-all') ? event.target : null;
    if (selAll) {
      var table = selAll.closest('table');
      if (table) {
        table.querySelectorAll('.bsa-cheat__sel-row').forEach(function (cb) {
          cb.checked = selAll.checked;
        });
      }
      return;
    }

    var btn = event.target && event.target.closest ? event.target.closest('.bsa-cheat__confirm') : null;

    if (btn && !btn.disabled) {

      event.preventDefault();

      event.stopPropagation();

      sendConfirm(btn);

      return;

    }

    var chipBtn = event.target && event.target.closest ? event.target.closest('.bsa-chat-chip') : null;

    if (chipBtn) {

      event.preventDefault();

      var chipText = chipBtn.getAttribute('data-chip') || chipBtn.textContent || '';

      if (input && chipText) {

        input.value = chipText.trim();

        send(false);

      }

      return;

    }

    var cancelBtn = event.target && event.target.closest ? event.target.closest('.bsa-cheat__cancel') : null;

    if (cancelBtn) {

      event.preventDefault();

      lastPendingAction = null;

      appendBotHtml('<p class="bsa-chat-muted">Actiune anulata. Poti continua conversatia.</p>');

      return;

    }



    var rateBtn = event.target && event.target.closest ? event.target.closest('.bsa-training__btn') : null;

    if (rateBtn) {

      var wrap = rateBtn.closest('.bsa-training');

      var qid = wrap ? wrap.getAttribute('data-query-id') : lastQueryId;

      if (rateBtn.getAttribute('data-learn') === '1') {

        if (rateBtn.disabled) return;

        rateBtn.disabled = true;

        rateBtn.textContent = 'Invat...';

        postLearn(qid, (wrap && wrap.getAttribute('data-query-message')) || lastMessage, wrap);

        return;

      }

      sendOperatorFeedback(qid, rateBtn.getAttribute('data-rating') || 'bad', wrap);

      return;

    }

  });



  function renderCheatSheet(sheet) {

    if (!sheet || !sheet.kind) return '';



    var kindClass = 'bsa-cheat--' + String(sheet.kind || 'default').replace(/[^a-z0-9_-]/gi, '');

    var html = '<div class="bsa-cheat ' + kindClass + '">';

    html += '<div class="bsa-cheat__head">';

    html += '<strong class="bsa-cheat__title">' + esc(sheet.title || 'Rezultat') + '</strong>';

    if (sheet.updated_at) {

      html += '<span class="bsa-cheat__time">' + esc(sheet.updated_at) + '</span>';

    }

    if (sheet.subtitle) {

      html += '<span class="bsa-cheat__sub">' + esc(sheet.subtitle) + '</span>';

    }

    html += '</div>';



    var stats = sheet.stats || [];
    if (sheet.selective && stats.length) {
      stats = stats.filter(function (st) { return st.primary; });
    }
    if (stats.length) {

      html += '<div class="bsa-cheat__stats">';

      stats.forEach(function (st) {

        var cls = st.warn ? ' bsa-cheat__stat--warn' : '';

        html += '<div class="bsa-cheat__stat' + cls + '">'

          + '<span class="bsa-cheat__stat-val">' + esc(st.value) + '</span>'

          + '<span class="bsa-cheat__stat-lbl">' + esc(st.label) + '</span>'

          + '</div>';

      });

      html += '</div>';

    }



    var cats = sheet.categories || [];

    if (cats.length) {

      html += '<div class="bsa-cheat__block"><div class="bsa-cheat__label">Categorii</div><div class="bsa-cheat__pills">';

      cats.forEach(function (c) {

        html += '<span class="bsa-cheat__pill">' + esc(c.name) + ' <em>' + esc(String(c.count)) + '</em></span>';

      });

      html += '</div></div>';

    }



    var tree = sheet.category_tree || [];

    if (tree.length) {

      html += '<div class="bsa-cheat__block"><div class="bsa-cheat__label">Categorii si subcategorii</div><div class="bsa-cheat__tree">';

      tree.forEach(function (cat) {

        html += '<div class="bsa-cheat__tree-cat"><strong>' + esc(cat.category) + '</strong> <em>' + esc(String(cat.count)) + ' prod.</em>';

        (cat.subcategories || []).forEach(function (sub) {

          html += '<div class="bsa-cheat__tree-sub">' + esc(sub.name) + ' <em>' + esc(String(sub.count)) + '</em></div>';

        });

        if (!cat.subcategories || !cat.subcategories.length) {

          html += '<div class="bsa-cheat__tree-sub bsa-cheat__tree-sub--muted">fara subcategorie setata</div>';

        }

        html += '</div>';

      });

      html += '</div></div>';

    }



    var caps = sheet.capabilities || [];

    if (caps.length) {

      html += '<div class="bsa-cheat__block"><div class="bsa-cheat__label">Functii admin in sectiune</div><ul class="bsa-cheat__caps">';

      caps.forEach(function (c) {

        html += '<li><a href="' + esc(c.url || '#') + '">' + esc(c.label || '') + '</a></li>';

      });

      html += '</ul></div>';

    }



    var cmds = sheet.composer_commands || [];

    if (cmds.length) {

      html += '<div class="bsa-cheat__block"><div class="bsa-cheat__label">Comenzi Composer (exemple)</div><ul class="bsa-cheat__cmds">';

      cmds.forEach(function (c) {

        html += '<li><strong>' + esc(c.label) + '</strong><span>' + esc(c.example) + '</span></li>';

      });

      html += '</ul></div>';

    }



    var configRows = sheet.config_rows || [];

    if (configRows.length) {

      html += '<div class="bsa-cheat__block"><div class="bsa-cheat__label">Configuratie</div><dl class="bsa-cheat__config">';

      configRows.forEach(function (r) {

        html += '<div class="bsa-cheat__config-row"><dt>' + esc(r.key || '') + '</dt><dd>' + esc(r.value || '') + '</dd></div>';

      });

      html += '</dl></div>';

    }



    var positions = sheet.supplier_positions || [];

    if (positions.length) {

      html += '<div class="bsa-cheat__block"><div class="bsa-cheat__label">Pozitionare furnizori (scan)</div><div class="bsa-cheat__tree">';

      positions.forEach(function (p) {

        var cls = p.omitted ? ' bsa-cheat__tree-sub--muted' : '';

        html += '<div class="bsa-cheat__tree-sub' + cls + '"><strong>#' + esc(String(p.rank)) + '</strong> ' + esc(p.supplier)

          + (p.omitted ? ' <em>(omit)</em>' : '') + '</div>';

      });

      html += '</div></div>';

    }



    var rules = sheet.rules || [];

    if (rules.length) {

      html += '<div class="bsa-cheat__block"><div class="bsa-cheat__label">Reguli adaos active</div><ul class="bsa-cheat__caps">';

      rules.forEach(function (r) {

        html += '<li><strong>' + esc(r.name) + '</strong> ' + esc(r.adjustment) + ' <span>' + esc(r.filters) + '</span></li>';

      });

      html += '</ul></div>';

    }



    var moduleGroups = sheet.module_groups || [];

    if (moduleGroups.length) {

      html += '<div class="bsa-cheat__block"><div class="bsa-cheat__label">Module admin (toate fazele)</div>';

      moduleGroups.forEach(function (mg) {

        html += '<div class="bsa-cheat__module"><div class="bsa-cheat__module-head"><strong>' + esc(mg.label) + '</strong> <em>' + esc(mg.phase) + '</em></div><ul class="bsa-cheat__caps">';

        (mg.features || []).forEach(function (f) {

          html += '<li><a href="' + esc(f.url || '#') + '">' + esc(f.label) + '</a></li>';

        });

        html += '</ul></div>';

      });

      html += '</div>';

    }



    var agents = sheet.agents || [];

    if (agents.length) {

      html += '<div class="bsa-cheat__block"><div class="bsa-cheat__label">Agenti AI instalati</div><div class="bsa-cheat__pills">';

      agents.forEach(function (a) {

        html += '<span class="bsa-cheat__pill bsa-cheat__pill--agent">' + esc(a.name) + '</span>';

      });

      html += '</div></div>';

    }



    var products = sheet.products || [];

    var isSupplierList = sheet.kind === 'supplier_list';

    var isInvoiceList = sheet.kind === 'invoice_list';

    var isAwbList = sheet.kind === 'awb_list';

    var isOrdersList = sheet.kind === 'orders_list'
      || sheet.kind === 'orders_summary'
      || (sheet.title && /comenzi/i.test(String(sheet.title)))
      || products.some(function (p) {
        return p && (p.order_number || p.order_status || (p.client_name && p.product_name && !p.brand));
      });

    var isComunicareList = sheet.kind === 'comunicare_summary'
      || products.some(function (p) { return p && (p.channel || p.read_label || p.message_preview); });

    var isClientList = sheet.kind === 'client_list'
      || products.some(function (p) { return p && p.total_orders != null && p.city && !p.order_number; });

    var isDocList = isSupplierList || isInvoiceList || isAwbList || isOrdersList || isComunicareList || isClientList;

    var isSelectable = !!sheet.selectable && products.length > 0 && !isDocList;
    var isSelectiveProduct = !!sheet.selective && isSelectable;

    var hasSubCol = !isDocList && !isSelectiveProduct && products.some(function (p) { return p && p.subcategory && p.subcategory !== '-'; });

    var hasBadgeCol = !isDocList && !isSelectiveProduct && products.some(function (p) { return p && (p.badge_after || (p.badge && p.badge !== '-')); });

    var hasBrandCol = !isSelectiveProduct;

    var hasPriceCol = !isDocList && products.some(function (p) {
      var price = p && p.price != null ? String(p.price).trim() : '';
      return price !== '' && price !== '-';
    });

    if (products.length || sheet.products_note) {
      var hideProductsTable = sheet.show_products_table === false
        || (sheet.selective && !products.length && sheet.kind === 'product_inventory');
      var blockLabel = isSupplierList ? 'Furnizori' : (isInvoiceList ? 'Facturi' : (isAwbList ? 'AWB' : (isOrdersList ? 'Comenzi' : (isComunicareList ? 'Mesaje' : (isClientList ? 'Clienti' : 'Produse')))));

      html += '<div class="bsa-cheat__block"><div class="bsa-cheat__label">' + blockLabel + '</div>';

      if (products.length && !hideProductsTable) {

        html += '<div class="bsa-cheat__table-wrap"><table class="bsa-cheat__table"><thead><tr>';

        if (isSupplierList) {
          html += '<th>Furnizor</th><th>Cod</th><th>Status</th><th></th>';
        } else if (isInvoiceList) {
          html += '<th>Factura</th><th>Comanda</th><th>Client</th><th>Status</th><th>Suma</th><th></th>';
        } else if (isAwbList) {
          html += '<th>AWB</th><th>Comanda</th><th>Client</th><th>Curier</th><th>Status</th><th>Total</th><th></th>';
        } else if (isComunicareList) {
          html += '<th>Expeditor</th><th>Canal</th><th>Subiect</th><th>Status</th><th>Data</th><th></th>';
        } else if (isClientList) {
          html += '<th>Client</th><th>Email</th><th>Telefon</th><th>Oras</th><th>Comenzi</th><th>Total</th><th></th>';
        } else if (isOrdersList) {
          html += '<th>Comanda</th><th>Client</th><th>Produs</th><th>Status</th><th>Plata</th><th>Total</th><th>Data</th><th></th>';
        } else if (isSelectiveProduct) {
          html += (isSelectable ? '<th class="bsa-cheat__sel-col"><input type="checkbox" class="bsa-cheat__sel-all" aria-label="Selecteaza toate"></th>' : '')
            + '<th>Produs</th><th>Cod</th><th>Categorie</th>'
            + (hasPriceCol ? '<th>Pret</th>' : '')
            + '<th></th>';
        } else {
          html += (isSelectable ? '<th class="bsa-cheat__sel-col"><input type="checkbox" class="bsa-cheat__sel-all" aria-label="Selecteaza toate"></th>' : '')
            + '<th>Produs</th>'
            + (hasBrandCol ? '<th>Brand</th>' : '')
            + '<th>Cod</th><th>Categorie</th>'
            + (hasSubCol ? '<th>Subcategorie</th>' : '')
            + (hasPriceCol ? '<th>Pret</th>' : '')
            + (hasBadgeCol ? '<th>Badge</th>' : '')
            + '<th></th>';
        }

        html += '</tr></thead><tbody>';

        products.forEach(function (p) {

          html += '<tr>';

          if (isSupplierList) {
            html += '<td>' + esc(p.name) + '</td>'
              + '<td>' + esc(p.code) + '</td>'
              + '<td>' + esc(p.status) + '</td>'
              + '<td><a class="bsa-cheat__edit" href="' + esc(p.edit_url || '/admin/suppliers') + '">Edit</a></td>';
          } else if (isInvoiceList) {
            html += '<td>' + esc(p.invoice_number) + '</td>'
              + '<td>' + esc(p.order_number) + '</td>'
              + '<td>' + esc(p.client_name) + '</td>'
              + '<td>' + esc(p.invoice_status) + '</td>'
              + '<td>' + esc(p.amount) + '</td>'
              + '<td><a class="bsa-cheat__edit" href="' + esc(p.edit_url || '/admin/facturi') + '">Deschide</a></td>';
          } else if (isAwbList) {
            html += '<td>' + esc(p.awb) + '</td>'
              + '<td>' + esc(p.order_number) + '</td>'
              + '<td>' + esc(p.client_name) + '</td>'
              + '<td>' + esc(p.courier) + '</td>'
              + '<td>' + esc(p.delivery_status) + '</td>'
              + '<td>' + esc(p.total_amount) + '</td>'
              + '<td><a class="bsa-cheat__edit" href="' + esc(p.edit_url || '/admin/livrare') + '">Deschide</a></td>';
        } else if (isOrdersList) {
          html += '<td>' + esc(p.order_number) + '</td>'
            + '<td>' + esc(p.client_name) + '</td>'
            + '<td>' + esc(p.product_name) + '</td>'
            + '<td>' + esc(p.order_status) + '</td>'
            + '<td>' + esc(p.payment_status) + '</td>'
            + '<td>' + esc(p.total_amount) + '</td>'
            + '<td>' + esc(p.created_at) + '</td>'
            + '<td><a class="bsa-cheat__edit" href="' + esc(p.edit_url || '/admin/orders') + '">Deschide</a></td>';
        } else if (isComunicareList) {
          html += '<td>' + esc(p.name) + '</td>'
            + '<td>' + esc(p.channel) + '</td>'
            + '<td>' + esc(p.subject) + '</td>'
            + '<td>' + esc(p.read_label) + '</td>'
            + '<td>' + esc(p.created_at) + '</td>'
            + '<td><a class="bsa-cheat__edit" href="' + esc(p.edit_url || '/admin/messages') + '">Deschide</a></td>';
        } else if (isClientList) {
          html += '<td>' + esc(p.name) + '</td>'
            + '<td>' + esc(p.email) + '</td>'
            + '<td>' + esc(p.phone) + '</td>'
            + '<td>' + esc(p.city) + '</td>'
            + '<td>' + esc(p.total_orders) + '</td>'
            + '<td>' + esc(p.total_paid) + '</td>'
            + '<td><a class="bsa-cheat__edit" href="' + esc(p.edit_url || '/admin/clienti') + '">Deschide</a></td>';
        } else if (isSelectiveProduct) {
          html += (isSelectable
            ? '<td class="bsa-cheat__sel-col"><input type="checkbox" class="bsa-cheat__sel-row" data-randomn-id="' + esc(p.randomn_id || '') + '" data-edit-url="' + esc(p.edit_url || '') + '" data-code="' + esc(p.code || '') + '" data-name="' + esc(p.name || '') + '"></td>'
            : '')
            + '<td>' + esc(p.name) + '</td>'
            + '<td>' + esc(p.code) + '</td>'
            + '<td>' + esc(p.category) + '</td>'
            + (hasPriceCol ? '<td>' + esc(p.price) + '</td>' : '')
            + '<td><a class="bsa-cheat__edit" href="' + esc(p.edit_url || '/admin/product') + '">Edit</a></td>';
        } else {
            html += (isSelectable
              ? '<td class="bsa-cheat__sel-col"><input type="checkbox" class="bsa-cheat__sel-row" data-randomn-id="' + esc(p.randomn_id || '') + '" data-edit-url="' + esc(p.edit_url || '') + '" data-code="' + esc(p.code || '') + '" data-name="' + esc(p.name || '') + '"></td>'
              : '')
              + '<td>' + esc(p.name) + '</td>'
              + (hasBrandCol ? '<td>' + esc(p.brand) + '</td>' : '')
              + '<td>' + esc(p.code) + '</td>'
              + '<td>' + esc(p.category) + '</td>'
              + (hasSubCol ? '<td>' + esc(p.subcategory || '-') + '</td>' : '')
              + (hasPriceCol ? '<td>' + esc(p.price) + '</td>' : '')
              + (hasBadgeCol ? '<td><span class="bsa-cheat__badge-tag">' + esc(p.badge_after || p.badge || '-') + '</span></td>' : '')
              + '<td><a class="bsa-cheat__edit" href="' + esc(p.edit_url || '/admin/product') + '">Edit</a></td>';
          }

          html += '</tr>';

        });

        html += '</tbody></table></div>';
        if (!isDocList) {
          html += '<p class="bsa-cheat__table-scroll-hint">Gliseaza stanga-dreapta daca nu vezi toate coloanele</p>';
        }

      } else if (!hideProductsTable) {

        html += '<p class="bsa-cheat__empty">' + (isOrdersList ? 'Nicio comanda.' : 'Niciun produs.') + '</p>';

      }

      if (sheet.products_note) {

        html += '<p class="bsa-cheat__note">' + esc(sheet.products_note) + '</p>';

      }

      var bulkActions = sheet.bulk_actions || [];
      if (bulkActions.length) {
        html += '<div class="bsa-cheat__bulk">';
        bulkActions.forEach(function (ba) {
          if (ba.url) {
            html += '<a class="bsa-cheat__btn bsa-cheat__btn--bulk" href="' + esc(ba.url) + '">' + esc(ba.label) + '</a>';
          } else {
            html += '<button type="button" class="bsa-cheat__btn bsa-cheat__btn--bulk" data-bulk-action="' + esc(ba.action || ba.id || '') + '"'
              + (ba.requires_selection ? ' data-requires-selection="1"' : '')
              + '>' + esc(ba.label) + '</button>';
          }
        });
        html += '</div>';
      }

      html += '</div>';

    }



    var hints = sheet.hints || [];

    if (hints.length) {

      html += '<div class="bsa-cheat__hints">';

      hints.forEach(function (h) {

        html += '<p>' + esc(h) + '</p>';

      });

      html += '</div>';

    }



    if (sheet.confirm && sheet.confirm.label && chatCan('chat.confirm_execute')) {

      var confirmQ = sheet.confirm_question || 'Doresti sa executi aceasta actiune?';

      html += '<div class="bsa-cheat__confirm-wrap">'

        + '<p class="bsa-cheat__confirm-q">' + esc(confirmQ) + '</p>'

        + '<div class="bsa-cheat__confirm-actions">'

        + '<button type="button" class="bsa-cheat__confirm"'

        + ' data-action="' + esc(sheet.confirm.action || 'set_badge') + '"'

        + ' data-badge="' + esc(sheet.confirm.badge || '') + '"'

        + ' data-pending-type="' + esc(sheet.confirm.action || '') + '"'

        + ' data-pending-code="' + esc(sheet.confirm.code || '') + '"'

        + ' data-pending-mode="' + esc(sheet.confirm.mode || 'auto') + '"'

        + ' data-pending-scope="' + esc(sheet.confirm.scope || 'all') + '"'

        + (sheet.confirm.pending_json ? ' data-pending-json="' + esc(sheet.confirm.pending_json) + '"' : '')

        + '>' + esc(sheet.confirm.label) + '</button>'

        + '<button type="button" class="bsa-cheat__cancel">Nu, anuleaza</button>'

        + '</div></div>';

    }



    var shortcuts = sheet.shortcuts || [];

    if (shortcuts.length) {

      html += '<div class="bsa-cheat__shortcuts">';

      shortcuts.forEach(function (s) {

        html += '<a class="bsa-cheat__btn" href="' + esc(s.url || '#') + '">' + esc(s.label) + '</a>';

      });

      html += '</div>';

    }



    html += '</div>';

    return html;

  }



  function renderQueryFeedback(fb, queryId, userMessage) {

    if (!fb) return '';

    var cls = fb.badge_ok ? 'bsa-training--ok' : (fb.status === 'partial' ? 'bsa-training--partial' : 'bsa-training--fail');

    var html = '<div class="bsa-training ' + cls + '" data-query-id="' + esc(queryId || fb.query_id || '') + '" data-query-message="' + esc(userMessage || '') + '">';

    html += '<div class="bsa-training__head"><span class="bsa-training__badge">' + (fb.badge_ok ? 'OK' : (fb.status === 'partial' ? '~' : '!')) + '</span>';

    html += '<strong>' + esc(fb.outcome_label || 'Evaluare') + '</strong></div>';

    if (fb.evaluation_log && fb.evaluation_log.summary) {

      var sm = fb.evaluation_log.summary;

      html += '<p class="bsa-training__summary">' + esc(String(sm.passed || 0)) + ' verificari ok · '

        + esc(String(sm.failed || 0)) + ' esec'

        + (sm.warnings ? (' · ' + esc(String(sm.warnings)) + ' atentie') : '')

        + '</p>';

    }

    if (fb.correct_items && fb.correct_items.length) {

      html += '<div class="bsa-training__block bsa-training__block--ok"><span class="bsa-training__label">Corect</span><ul>';

      fb.correct_items.forEach(function (x) { html += '<li>' + esc(x) + '</li>'; });

      html += '</ul></div>';

    }

    if (fb.missing_context && fb.missing_context.length) {

      html += '<div class="bsa-training__block bsa-training__block--fail"><span class="bsa-training__label">Lipseste context</span><ul>';

      fb.missing_context.forEach(function (x) { html += '<li>' + esc(x) + '</li>'; });

      html += '</ul></div>';

    }

    if (fb.not_done && fb.not_done.length) {

      html += '<div class="bsa-training__block bsa-training__block--fail"><span class="bsa-training__label">Nu s-a facut</span><ul>';

      fb.not_done.forEach(function (x) { html += '<li>' + esc(x) + '</li>'; });

      html += '</ul></div>';

    }

    if (fb.checks && fb.checks.length) {

      html += '<details class="bsa-training__checks-wrap"><summary>Detalii verificari (' + fb.checks.length + ')</summary>';

      html += renderChecksHtml(fb.checks);

      html += '</details>';

    }

    if (fb.training_hints && fb.training_hints.length) {

      fb.training_hints.forEach(function (h) {

        html += '<p class="bsa-training__hint">' + esc(h) + '</p>';

      });

    }

    html += '<div class="bsa-training__log-slot"></div>';

    if (queryId || fb.query_id) {

      html += '<div class="bsa-training__rate">';

      if (fb.can_learn) {

        html += '<button type="button" class="bsa-training__btn bsa-training__btn--learn" data-learn="1">Invata</button>';

      }

      html += '<button type="button" class="bsa-training__btn bsa-training__btn--ok" data-rating="ok">Corect</button>'

        + '<button type="button" class="bsa-training__btn bsa-training__btn--bad" data-rating="bad">Nu e bine</button>'

        + '</div>';

    }

    html += '<a class="bsa-training__link" href="/admin/ai-agent" target="_blank" rel="noopener">Jurnal AI Section</a>';

    html += '</div>';

    return html;

  }



  function postLearn(queryId, message, wrapEl) {

    if (!C.api) return;

    setConnStatus('busy');

    fetch(C.api + '?action=section_assist_learn', {

      method: 'POST',

      credentials: 'same-origin',

      headers: { 'Content-Type': 'application/json' },

      body: JSON.stringify({

        query_id: queryId || lastQueryId,

        message: message || lastMessage,

        admin_section: C.section || '',

        path: window.location.pathname || '',

        session_id: sessionId,

        conversation_history: conversationHistory,

      }),

    })

      .then(function (r) {

        return r.json().catch(function () { return {}; }).then(function (j) {

          if (!r.ok || !j.success) {

            throw new Error((j && j.message) ? j.message : ('HTTP ' + r.status));

          }

          return j;

        });

      })

      .then(function (j) {

        setConnStatus('online');

        if (j.data) {
          if (j.data.reply) {
            lastMessage = String(j.data.conversation_history && j.data.conversation_history.length
              ? (j.data.conversation_history[j.data.conversation_history.length - 2] || {}).message || lastMessage
              : lastMessage);
          }
          renderResult(j.data);
        }

        if (wrapEl && wrapEl.parentNode) {

          var src = j.data && j.data.llm_status ? j.data.llm_status.source : '';

          wrapEl.classList.remove('bsa-training--partial', 'bsa-training--fail', 'bsa-training--ok');

          if (src === 'learned_adapter' || src === 'learned_pattern') {
            wrapEl.classList.add('bsa-training--ok');
          } else if (src === 'learn_failed') {
            wrapEl.classList.add('bsa-training--fail');
          } else {
            wrapEl.classList.add('bsa-training--partial');
          }

        }

      })

      .catch(function (err) {

        setConnStatus('offline');

        if (wrapEl) {

          var learnBtn = wrapEl.querySelector('.bsa-training__btn--learn');

          if (learnBtn) {

            learnBtn.disabled = false;

            learnBtn.textContent = 'Invata';

          }

        }

        appendBotHtml('<div class="bsa-err">' + esc(err.message || 'Invatare esuata') + '</div>');

      });

  }



  function applyFeedbackVisual(wrap, fb, rating) {

    if (!wrap || !fb) return;

    wrap.classList.remove('bsa-training--ok', 'bsa-training--partial', 'bsa-training--fail');

    var badgeEl = wrap.querySelector('.bsa-training__badge');

    var labelEl = wrap.querySelector('.bsa-training__head strong');

    var status = fb.status || rating;

    if (status === 'ok' || rating === 'ok') {

      wrap.classList.add('bsa-training--ok');

      if (badgeEl) badgeEl.textContent = 'OK';

      if (labelEl) labelEl.textContent = fb.outcome_label || 'OK — confirmat de operator';

    } else if (status === 'partial' || rating === 'partial') {

      wrap.classList.add('bsa-training--partial');

      if (badgeEl) badgeEl.textContent = '~';

      if (labelEl) labelEl.textContent = fb.outcome_label || 'Partial — confirmat de operator';

    } else {

      wrap.classList.add('bsa-training--fail');

      if (badgeEl) badgeEl.textContent = '!';

      if (labelEl) labelEl.textContent = fb.outcome_label || 'Respins — raspuns incorect';

    }

  }



  function sendOperatorFeedback(queryId, rating, wrapEl) {

    if (!queryId || !C.api) return;

    var wrap = wrapEl || document.querySelector('.bsa-training[data-query-id="' + queryId + '"]');

    var rateBtns = wrap ? wrap.querySelectorAll('.bsa-training__btn[data-rating]') : [];

    rateBtns.forEach(function (btn) {

      btn.disabled = true;

      if (btn.getAttribute('data-rating') === rating) btn.textContent = 'Se salveaza…';

    });

    fetch(C.api + '?action=section_assist_feedback', {

      method: 'POST',

      credentials: 'same-origin',

      headers: { 'Content-Type': 'application/json' },

      body: JSON.stringify({ query_id: queryId, rating: rating }),

    })

      .then(function (res) { return res.json().catch(function () { return {}; }); })

      .then(function (j) {

        if (!j || !j.success) {

          throw new Error((j && j.message) ? j.message : 'Feedback esuat');

        }

        var data = j.data || {};

        var fb = data.feedback || {};

        if (wrap) {

          applyFeedbackVisual(wrap, fb, rating);

          var logSlot = wrap.querySelector('.bsa-training__log-slot');

          if (logSlot) {

            logSlot.innerHTML = renderFeedbackLogPanel(fb, {

              message: (wrap.getAttribute('data-query-message') || ''),

              operator_rating: data.operator_rating || rating,

              operator_rated_at: (data.query && data.query.operator_rated_at) || '',

              query_id: data.query_id || queryId,

            });

          }

          rateBtns.forEach(function (btn) {

            btn.textContent = btn.getAttribute('data-rating') === rating ? 'Salvat' : btn.textContent;

          });

        }

        appendTestLogEntry(data);

        loadSupervisorScorecard(true);

      })

      .catch(function (err) {

        rateBtns.forEach(function (btn) {

          btn.disabled = false;

          if (btn.getAttribute('data-rating') === rating) btn.textContent = btn.getAttribute('data-rating') === 'ok' ? 'Corect' : 'Nu e bine';

        });

        appendBotHtml('<div class="bsa-err">' + esc(err.message || 'Feedback esuat') + '</div>');

      });

  }



  function renderResult(data) {

    if (!chatEl) return;

    data = data || {};

    if (data.pending_action) {

      lastPendingAction = data.pending_action;

    } else if (data.llm_status && data.llm_status.source === 'action_executed') {

      lastPendingAction = null;

    }

    lastQueryId = (data.query_feedback && data.query_feedback.query_id) ? data.query_feedback.query_id : lastQueryId;



    if (Array.isArray(data.conversation_history)) {

      conversationHistory = data.conversation_history;

      persistConversation();

    }



    var html = '';



    if (data.reply && (!data.cheat_sheet || !data.cheat_sheet.selective)) {

      html += '<p class="bsa-chat-lead">' + esc(data.reply) + '</p>';

    }



    if (data.llm_status) {

      var st = data.llm_status;

      var badge = st.source === 'catalog_db' ? 'Inventar live'
        : st.source === 'catalog_structure' ? 'Catalog complet'
        : st.source === 'section_capabilities' ? 'Functii sectiune'
        : st.source === 'admin_full_catalog' ? 'Catalog admin complet'
        : st.source === 'adaos_comercial' ? 'Adaos comercial'
        : st.source === 'supplier_compare' ? 'Comparare furnizori'
        : st.source === 'supplier_list' ? 'Furnizori live'
        : st.source === 'invoice_list' ? 'Facturi live'
        : st.source === 'awb_list' ? 'AWB live'
        : st.source === 'cart_list' ? 'Cos live'
        : st.source === 'learned_adapter' || st.source === 'learned_pattern' ? 'Invatat'
        : (st.source && String(st.source).indexOf('llm_intent+') === 0) ? 'LLM → SQL live'
        : (st.source && String(st.source).indexOf('intent_router+') === 0) ? 'Inteligat → live'
        : st.source === 'unknown_learnable' ? 'Necunoscut'
        : st.source === 'vitrina_homepage' ? 'Vitrina'
        : st.source === 'import_queue' ? 'Coada import'
        : st.source === 'product_inventory' ? 'Rezumat inventar'
        : st.source === 'product_list' ? 'Lista produse'
        : st.source === 'product_filter' ? 'Filtrare produse'
        : st.source === 'comunicare_summary' ? 'Comunicare live'
        : st.source === 'client_orders' ? 'Comenzi client'
        : st.source === 'client_list' ? 'Clienti live'
        : st.source === 'category_list' ? 'Categorii live'
        : st.source === 'supplier_search' ? 'Cautare furnizor'
        : st.source === 'orders_summary' ? 'Comenzi live'
        : st.source === 'action_executed' ? 'Actiune executata'

        : st.source === 'action_preview' ? 'Confirmare'

        : st.source === 'action_error' ? 'Eroare actiune'

        : st.source === 'composer-2.5' ? 'Composer 2.5'

        : st.source === 'openai' ? 'OpenAI'

        : st.source === 'groq' ? 'Groq'

        : (st.source && String(st.source).indexOf('organism+') === 0) ? 'Organism 1:1'

        : st.source === 'conversation' ? 'besoiupieseauto'

        : (st.source && String(st.source).indexOf('conversation+') === 0) ? 'besoiupieseauto'

        : 'Local';

      html += '<div class="bsa-badge">' + esc(badge) + '</div>';

    }



    if (data.organism && data.organism.label) {

      html += '<div class="bsa-organism-hit">'

        + esc(data.organism.module_label || data.organism.module || '')

        + ' → ' + esc(data.organism.label)

        + (data.organism.via ? ' <em>(' + esc(data.organism.via) + ')</em>' : '')

        + '</div>';

    }



    if (data.reply) {
      var replyClass = 'bsa-reply';
      if (data.cheat_sheet && data.cheat_sheet.selective) {
        replyClass += ' bsa-reply--focus';
      }
      html += '<div class="' + replyClass + '">' + esc(data.reply) + '</div>';
    }

    if (data.cheat_sheet) {

      html += renderCheatSheet(data.cheat_sheet);

    } else if (!data.reply) {

      html += '<div class="bsa-reply">' + esc(data.message || '').replace(/\n/g, '<br>') + '</div>';

    }



    var steps = data.next_steps || [];

    if (steps.length) {

      html += '<div class="bsa-chat-chips">';

      steps.forEach(function (s) {

        html += '<button type="button" class="bsa-chat-chip" data-chip="' + esc(s) + '">' + esc(s) + '</button>';

      });

      html += '</div>';

    }



    if (!data.cheat_sheet) {

      var sug = data.suggestions || [];

      if (sug.length) {

        html += '<div class="bsa-suggestions">' + sug.map(function (s) {

          if (s.type === 'navigate' || s.url) {

            return '<a class="bsa-link" href="' + esc(s.url || '#') + '">' + esc(s.label || s.url) + '</a>';

          }

          if (s.type === 'product_template') {

            return '<div class="bsa-template"><strong>Template produs</strong>'

              + '<div>' + esc(s.name_pattern || '') + '</div>'

              + '<code>' + esc((s.fields || []).join(', ')) + '</code>'

              + (s.notes ? '<p>' + esc(s.notes) + '</p>' : '') + '</div>';

          }

          return '<div class="bsa-feature">' + esc(s.label || JSON.stringify(s)) + '</div>';

        }).join('') + '</div>';

      }

    } else {

      var sug2 = data.suggestions || [];

      if (sug2.length) {

        html += '<div class="bsa-suggestions">' + sug2.map(function (s) {

          return '<div class="bsa-feature">' + esc(s.label || JSON.stringify(s)) + '</div>';

        }).join('') + '</div>';

      }

    }



    if (data.composer_repair && data.composer_repair.message) {

      html += '<div class="bsa-repair">Reparatie: ' + esc(data.composer_repair.message) + '</div>';

    }



    var qfb = data.query_feedback || (data.cheat_sheet && data.cheat_sheet.query_feedback);

    if (qfb) {

      html += renderQueryFeedback(qfb, qfb.query_id || lastQueryId, lastMessage);

    }



    appendBotHtml(html);

    if (organismEl) {

      organismEl.classList.add('bsa-organism--compact');

    }

    scrollChatToBottom();



    if (data.llm_status && data.llm_status.source === 'action_executed') {

      document.dispatchEvent(new CustomEvent('besoiu-assistant-action-done', { detail: data }));

      loadSupervisorScorecard(true);

    }

  }



  function postAssist(body, attempt) {

    attempt = attempt || 0;

    var maxAttempts = 3;

    var ASSIST_TIMEOUT_MS = 35000;

    if (!window.__bsaAssistAbort) window.__bsaAssistAbort = null;

    if (!window.__bsaAssistTimeout) window.__bsaAssistTimeout = null;



    function clearAssistFlight() {

      if (window.__bsaAssistTimeout) {

        clearTimeout(window.__bsaAssistTimeout);

        window.__bsaAssistTimeout = null;

      }

      if (window.__bsaAssistAbort) {

        try { window.__bsaAssistAbort.abort(); } catch (eAbort) { /* ignore */ }

        window.__bsaAssistAbort = null;

      }

    }



    if (attempt === 0) {

      clearAssistFlight();

      window.__bsaAssistAbort = new AbortController();

      window.__bsaAssistTimeout = setTimeout(function () {

        if (window.__bsaAssistAbort) window.__bsaAssistAbort.abort();

      }, ASSIST_TIMEOUT_MS);

    }



    setConnStatus('busy');



    var fetchOpts = {

      method: 'POST',

      credentials: 'same-origin',

      headers: { 'Content-Type': 'application/json' },

      body: JSON.stringify(body),

    };

    if (window.__bsaAssistAbort) fetchOpts.signal = window.__bsaAssistAbort.signal;



    return fetch(C.api + '?action=section_assist', fetchOpts)

      .then(function (r) {

        if (!r.ok) {

          return r.json().catch(function () { return {}; }).then(function (j) {

            throw new Error((j && j.message) ? j.message : ('HTTP ' + r.status));

          });

        }

        return r.json();

      })

      .then(function (json) {

        clearAssistFlight();

        setConnStatus('online');

        return json;

      })

      .catch(function (err) {

        var aborted = err && (err.name === 'AbortError'

          || String(err.message || '').toLowerCase().indexOf('abort') >= 0);

        if (aborted) {

          clearAssistFlight();

          setConnStatus('online');

          throw new Error('Cererea a durat prea mult (>35s). Chip-urile rapide (vitrina, inventar) ar trebui sa raspunda in cateva secunde — reincarca pagina (Ctrl+F5) daca persista.');

        }

        if (attempt + 1 < maxAttempts) {

          return new Promise(function (resolve) {

            setTimeout(function () {

              resolve(postAssist(body, attempt + 1));

            }, 700 * (attempt + 1));

          });

        }

        clearAssistFlight();

        setConnStatus('offline');

        throw err;

      });

  }



  function pendingActionFromBtn(btn) {

    if (lastPendingAction) return lastPendingAction;

    if (!btn) return null;

    var type = btn.getAttribute('data-pending-type') || btn.getAttribute('data-action') || '';

    if (type === 'edit_product_title') {

      var code = btn.getAttribute('data-pending-code') || '';

      if (code === '') return null;

      return {

        type: 'edit_product_title',

        code: code,

        mode: btn.getAttribute('data-pending-mode') || 'auto',

      };

    }

    if (type === 'set_badge') {

      var badge = btn.getAttribute('data-badge') || '';

      if (badge === '') return null;

      return {

        type: 'set_badge',

        badge: badge,

        scope: btn.getAttribute('data-pending-scope') || 'all',

      };

    }

    return null;

  }



  function afterActionExecuted(data) {

    toggle(true);

    scrollChatToBottom();

    document.dispatchEvent(new CustomEvent('besoiu-assistant-action-done', { detail: data || {} }));

  }



  function sendConfirm(btn) {

    var pending = pendingActionFromBtn(btn);

    var msg = lastMessage || String(input.value || '').trim();

    if (!msg && pending && pending.code) {

      msg = 'confirm ' + pending.type + ' ' + pending.code;

    }

    if (!msg && !pending) return;



    btn.disabled = true;

    var oldText = btn.textContent;

    btn.textContent = 'Se aplica…';

    sendBtn.disabled = true;

    showThinking();

    appendUserMessage('Da, confirm actiunea');



    var body = {

      message: msg,

      admin_section: C.section,

      path: window.location.pathname || '',

      session_id: sessionId,

      conversation_history: conversationHistory,

      confirm_execute: true,

      execute_action: true,

    };

    if (pending) {

      body.pending_action = pending;

    } else if (lastPendingAction) {

      body.pending_action = lastPendingAction;

    } else if (btn.getAttribute('data-badge')) {

      body.pending_action = {

        type: 'set_badge',

        badge: btn.getAttribute('data-badge'),

        scope: 'all',

      };

    }



    attachSelectedProducts(body);



    postAssist(body)

      .then(function (json) {

        if (!json.success && !json.data) throw new Error(json.message || 'Eroare la confirmare');

        var payload = json.data || json;

        renderResult(payload);

        if (payload.llm_status && payload.llm_status.source === 'action_executed') {

          afterActionExecuted(payload);

          if (btn) {

            btn.textContent = 'Salvat ✓';

            btn.disabled = true;

          }

          if (payload.composer_repair && payload.composer_repair.fixed) {

            document.dispatchEvent(new CustomEvent('besoiu-alert-fixed', { detail: json }));

          }

          return;

        }

        if (payload.llm_status && payload.llm_status.source === 'action_error' && btn) {

          btn.textContent = 'Incearca din nou';

        }

      })

      .catch(function (e) {

        hideThinking();

        appendBotHtml('<div class="bsa-err">' + esc(e.message || 'Eroare') + '</div>');

      })

      .finally(function () {

        sendBtn.disabled = false;

        if (btn && btn.textContent === 'Se aplica…') {

          btn.disabled = false;

          btn.textContent = oldText;

        }

      });

  }



  function send(forceExecute) {

    var msg = String(input.value || '').trim();

    if (!msg && !forceExecute) return;

    if (forceExecute && lastMessage) {

      msg = lastMessage;

    } else if (!forceExecute) {

      lastMessage = msg;

      appendUserMessage(msg);

      input.value = '';

    } else {

      lastMessage = msg;

    }

    sendBtn.disabled = true;

    input.disabled = true;

    showThinking();



    var body = {

      message: msg,

      admin_section: C.section,

      path: window.location.pathname || '',

      session_id: sessionId,

      conversation_history: conversationHistory,

    };

    if (forceExecute) {

      body.confirm_execute = true;

      body.execute_action = true;

      if (lastPendingAction) body.pending_action = lastPendingAction;

    }



    attachSelectedProducts(body);



    postAssist(body)

      .then(function (json) {

        if (!json.success && !json.data) throw new Error(json.message || 'Eroare Composer');

        renderResult(json.data || json);

        if (json.data && json.data.composer_repair && json.data.composer_repair.fixed) {

          document.dispatchEvent(new CustomEvent('besoiu-alert-fixed', { detail: json }));

        }

      })

      .catch(function (e) {

        hideThinking();

        appendBotHtml('<div class="bsa-err">' + esc(e.message || 'Eroare') + '</div>');

      })

      .finally(function () {

        sendBtn.disabled = false;

        input.disabled = false;

        input.focus();

      });

  }



  sendBtn.addEventListener('click', function () { send(false); });



  function showDomMarkerGuide(detail) {
    detail = detail || {};
    var meta = detail.meta || {};
    var instr = detail.instruction || {};
    var label = meta.label || instr.title || 'zonă selectată';
    if (/^(div|span|section|#)/i.test(label)) label = 'zonă selectată';

    toggle(true);
    ensureWelcome();
    appendUserMessage('Ce face: ' + label);
    appendBotHtml(instr.html || (
      '<div class="bsa-dom-guide bsa-dom-guide--empty"><p>Nu există instrucțiuni RAG pentru acest element.</p></div>'
    ));
  }

  document.addEventListener('besoiu-dom-marker-guide', function (e) {
    showDomMarkerGuide((e && e.detail) ? e.detail : {});
  });

  window.BesoiuSectionAssist = window.BesoiuSectionAssist || {};
  window.BesoiuSectionAssist.showDomMarkerGuide = showDomMarkerGuide;



  try {

    if (sessionStorage.getItem('bsa-panel-open') === '1') toggle(true);

  } catch (e) { /* ignore */ }



  pingConnection();

  schedulePing();

  input.addEventListener('keydown', function (e) {

    if (e.key === 'Enter' && !e.shiftKey) {

      e.preventDefault();

      send(false);

    }

  });

})();


