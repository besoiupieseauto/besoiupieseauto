/**
 * Panou Control Ollama — instrument simplu admin.
 */
(function () {
  'use strict';

  var AGENT_META = {
    'agent-imagini': { icon: '🖼️', quick: ['Cum verific o imagine?', 'Audit imagine produs'] },
    'agent-produse': { icon: '📦', quick: ['Câte produse active avem?', 'Ce știi despre ZOLLEX T-522Z?', 'Ce categorii domină?'] },
    'agent-clienti': { icon: '👤', quick: ['Status comenzi recente', 'Politica retur pe scurt'] },
    'agent-statistici': { icon: '📊', quick: ['Raport KPI acum', 'Import staging — pending?'] },
  };

  var selectedSlug = 'agent-produse';
  var statusData = null;
  var healthData = null;
  var busy = false;

  function cfg() {
    var el = document.getElementById('ollama-control-cfg');
    if (!el) return {};
    try { return JSON.parse(el.textContent || '{}'); } catch (e) { return {}; }
  }

  function root() {
    return document.getElementById('ollama-control-root');
  }

  function agentApi() {
    var c = cfg();
    if (c.agentApi) return c.agentApi;
    var hub = document.getElementById('ai-hub-root');
    if (hub && hub.getAttribute('data-agent-api')) return hub.getAttribute('data-agent-api');
    return (root() && root().getAttribute('data-agent-api')) || '';
  }

  function settingsApi() {
    var c = cfg();
    if (c.settingsApi) return c.settingsApi;
    var hub = document.getElementById('ai-hub-root');
    if (hub && hub.getAttribute('data-settings-api')) return hub.getAttribute('data-settings-api');
    return (root() && root().getAttribute('data-settings-api')) || '';
  }

  function esc(s) {
    var d = document.createElement('div');
    d.textContent = s == null ? '' : String(s);
    return d.innerHTML;
  }

  function toast(msg) {
    var el = document.getElementById('ai-hub-toast') || document.getElementById('ollama-toast') || document.getElementById('ai-rag-toast');
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
        try { json = text ? JSON.parse(text) : null; } catch (e) { /* not json */ }
        if (!r.ok) {
          if (r.status === 401 && window.BesoiuAdminAuth && window.BesoiuAdminAuth.handleUnauthorized(r)) {
            return Promise.reject(new Error('Sesiune expirată — redirecționare login…'));
          }
          var msg = (json && json.message) ? json.message : ('HTTP ' + r.status);
          throw new Error(msg);
        }
        if (json === null) {
          throw new Error('Răspuns invalid de la server (nu e JSON).');
        }
        return json;
      });
    }).finally(function () {
      if (timer) clearTimeout(timer);
    });
  }

  function agentGet(params, timeoutMs) {
    var q = new URLSearchParams(params);
    return fetchJson(agentApi() + '?' + q.toString(), {}, timeoutMs || 120000);
  }

  function agentPost(body, timeoutMs) {
    return fetchJson(agentApi(), {
      method: 'POST',
      body: JSON.stringify(body),
    }, timeoutMs || 130000);
  }

  function settingsGet(view) {
    return fetchJson(settingsApi() + '?view=' + encodeURIComponent(view), {}, 30000);
  }

  function settingsPost(body, timeoutMs) {
    return fetchJson(settingsApi(), {
      method: 'POST',
      body: JSON.stringify(body),
    }, timeoutMs || 120000);
  }

  function formatMarkdown(text) {
    var safe = esc(text);
    safe = safe.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
    safe = safe.replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" target="_blank" rel="noopener">$1</a>');
    return safe.replace(/\n/g, '<br>');
  }

  function modeBadge(mode) {
    var labels = {
      db_direct: 'MySQL live',
      rag_direct: 'Catalog RAG',
      library_rag_direct: 'Bibliotecă RAG',
      ollama_direct: 'Ollama + context',
      router: 'Router LLM',
    };
    return labels[mode] || mode || 'agent';
  }

  function wiringBadge(w) {
    w = String(w || 'dead');
    if (w === 'active') return '<span class="ollama-badge ollama-badge--active">activ</span>';
    if (w === 'partial') return '<span class="ollama-badge ollama-badge--partial">parțial</span>';
    return '<span class="ollama-badge ollama-badge--dead">moarte</span>';
  }

  function renderHealth(data) {
    healthData = data || null;
    var pills = document.getElementById('ollama-status-pills');
    var detail = document.getElementById('ollama-connection-detail');
    var timeEl = document.getElementById('ollama-status-time');
    if (!pills || !data) return;

    var enabled = !!data.enabled;
    var erp = data.erp_readiness || {};
    var vision = data.vision_readiness || {};
    var modules = Array.isArray(data.modules) ? data.modules : [];
    var erpMod = modules.find(function (m) { return m.module === 'erp'; }) || modules[0] || {};
    var tel = data.telemetry && data.telemetry.erp ? data.telemetry.erp : {};

    var pillHtml = [];
    pillHtml.push('<span class="ollama-pill ' + (enabled ? 'ollama-pill--ok' : 'ollama-pill--warn') + '">'
      + (enabled ? '✓ Ollama activ' : '✗ Ollama dezactivat') + '</span>');
    pillHtml.push('<span class="ollama-pill ' + (erp.ready ? 'ollama-pill--ok' : 'ollama-pill--warn') + '">'
      + 'Text: ' + esc(erp.model || erpMod.text_model || '—') + '</span>');
    pillHtml.push('<span class="ollama-pill ' + (vision.ready ? 'ollama-pill--ok' : 'ollama-pill--warn') + '">'
      + 'Vision: ' + esc(vision.model || erpMod.vision_model || '—') + '</span>');
    if (erpMod.latency_ms != null) {
      pillHtml.push('<span class="ollama-pill ollama-pill--ok">' + erpMod.latency_ms + ' ms</span>');
    }
    pills.innerHTML = pillHtml.join('');

    var parts = [];
    parts.push('URL: ' + esc(erpMod.base_url || 'http://127.0.0.1:11434'));
    if (tel.total_calls != null) parts.push('Apeluri ERP: ' + tel.total_calls);
    if (tel.avg_latency_ms) parts.push('Medie: ' + tel.avg_latency_ms + ' ms');
    if (detail) detail.textContent = parts.join(' · ');

    if (timeEl && data.generated_at) {
      timeEl.textContent = 'Actualizat: ' + new Date(data.generated_at).toLocaleString('ro-RO');
    }

    renderIntegrations(data.integrations || []);
    renderErrors(data.error_log || []);
  }

  function renderIntegrations(list) {
    var wrap = document.getElementById('ollama-integrations');
    if (!wrap) return;
    if (!list.length) {
      wrap.innerHTML = '<p class="ollama-empty">Nicio integrare înregistrată.</p>';
      return;
    }
    wrap.innerHTML = list.map(function (i) {
      return '<div class="ollama-int-row" data-int-id="' + esc(i.id || '') + '">'
        + '<div class="ollama-int-row__name"><strong>' + esc(i.purpose || i.id || '') + '</strong>'
        + '<small>' + esc(i.file || '') + ' · ' + esc(i.fn || '') + '</small></div>'
        + wiringBadge(i.wiring)
        + '<button type="button" class="ollama-btn ollama-btn--ghost ollama-btn--xs ollama-int-test" data-id="' + esc(i.id || '') + '">Test</button>'
        + '</div>';
    }).join('');
  }

  function renderErrors(errors) {
    var wrap = document.getElementById('ollama-errors');
    if (!wrap) return;
    if (!errors || !errors.length) {
      wrap.innerHTML = '<div class="ollama-empty">Nicio eroare recentă.</div>';
      return;
    }
    wrap.innerHTML = errors.slice(0, 20).map(function (e) {
      var t = e.ts || e.at || e.timestamp || '';
      return '<div class="ollama-err-row"><time>' + esc(t) + '</time>'
        + (e.source ? '<code>[' + esc(e.source) + '] </code>' : '')
        + '<code>' + esc((e.error || e.message || '').slice(0, 200)) + '</code></div>';
    }).join('');
  }

  function renderAgentStatus(data) {
    statusData = data || null;
    var inline = document.getElementById('ollama-agent-status');
    if (inline && data) {
      var ollama = data.ollama || {};
      var vision = data.vision || {};
      var db = data.db || {};
      var txt = (ollama.ready ? '✓ Ollama text OK' : '✗ Ollama text indisponibil')
        + ' · ' + (vision.ready ? '✓ Vision OK' : '✗ Vision indisponibil');
      if (db.ok && db.products) {
        txt += ' · MySQL: ' + (db.products.active || 0) + ' produse active';
      } else if (db.error) {
        txt += ' · MySQL: ' + db.error;
      }
      inline.textContent = txt;
      inline.className = 'ollama-inline-status ' + ((ollama.ready || vision.ready || db.ok) ? 'is-ok' : 'is-warn');
    }
    renderAgentCards(data && data.agents ? data.agents : []);
    updateChatChrome();
  }

  function renderAgentCards(agents) {
    var wrap = document.getElementById('ollama-agent-cards');
    if (!wrap) return;
    if (!agents || !agents.length) {
      wrap.innerHTML = '<p class="ollama-empty">Niciun agent instalat.</p>';
      return;
    }
    wrap.innerHTML = agents.map(function (a) {
      var meta = AGENT_META[a.slug] || { icon: '🤖' };
      var active = a.slug === selectedSlug ? ' is-active' : '';
      var ready = a.ready ? ' is-ready' : '';
      return '<button type="button" class="ollama-agent-btn' + active + ready + '" data-slug="' + esc(a.slug) + '">'
        + '<span class="ollama-agent-btn__icon">' + meta.icon + '</span>'
        + '<span class="ollama-agent-btn__body"><strong>' + esc(a.name || a.slug) + '</strong>'
        + '<small>' + esc(a.model || '') + '</small></span>'
        + '<span class="ollama-agent-btn__state">' + (a.ready ? 'READY' : 'OFF') + '</span>'
        + '</button>';
    }).join('');
  }

  function canSendChat(current, options) {
    options = options || {};
    if (!current || (current.installed === false && !current.chat_capable)) {
      return false;
    }
    if (options.smoke) {
      return !!current.ready;
    }
    if (current.ready) {
      return true;
    }
    var useKnowledge = !!(document.getElementById('ollama-use-knowledge') && document.getElementById('ollama-use-knowledge').checked);
    var useLiveDb = !!(document.getElementById('ollama-use-live-db') && document.getElementById('ollama-use-live-db').checked);
    return useKnowledge || useLiveDb;
  }

  function updateChatChrome(options) {
    options = options || {};
    var agents = (statusData && statusData.agents) || [];
    var current = agents.find(function (a) { return a.slug === selectedSlug; }) || {};
    var meta = AGENT_META[selectedSlug] || { icon: '🤖', quick: [] };

    var title = document.getElementById('ollama-chat-title');
    var model = document.getElementById('ollama-chat-model');
    if (title) title.textContent = (meta.icon || '') + ' ' + (current.name || selectedSlug);
    if (model) model.textContent = current.model || '—';

    var sendBtn = document.getElementById('ollama-send');
    if (sendBtn) sendBtn.disabled = busy || !canSendChat(current, options);
    var smokeBtn = document.getElementById('ollama-send-smoke');
    if (smokeBtn) smokeBtn.disabled = busy || !canSendChat(current, { smoke: true });

    var quick = document.getElementById('ollama-quick');
    if (quick) {
      if (!meta.quick || !meta.quick.length) {
        quick.classList.add('hidden');
      } else {
        quick.classList.remove('hidden');
        quick.innerHTML = meta.quick.map(function (q) {
          return '<button type="button" class="ollama-quick__btn" data-quick="' + esc(q) + '">' + esc(q) + '</button>';
        }).join('');
      }
    }
  }

  function appendMessage(role, text, meta) {
    var box = document.getElementById('ollama-messages');
    if (!box) return;
    var empty = box.querySelector('.ollama-empty');
    if (empty) box.innerHTML = '';

    var article = document.createElement('article');
    article.className = 'ollama-msg ollama-msg--' + role;
    var head = role === 'user' ? 'Tu' : 'Agent';
    if (meta && meta.model) head += ' · ' + meta.model;
    article.innerHTML = '<div class="ollama-msg__head">' + esc(head) + '</div>'
      + '<div class="ollama-msg__body">' + formatMarkdown(text) + '</div>';
    if (meta && meta.mode) {
      var badge = document.createElement('span');
      badge.className = 'ollama-msg__mode';
      badge.textContent = modeBadge(meta.mode);
      article.querySelector('.ollama-msg__body').appendChild(badge);
    }
    box.appendChild(article);
    box.scrollTop = box.scrollHeight;

    if (role === 'assistant' && text && text.indexOf('⏳') === -1 && text.indexOf('Eroare:') !== 0) {
      var saveBtn = document.createElement('button');
      saveBtn.type = 'button';
      saveBtn.className = 'ollama-btn ollama-btn--ghost ollama-btn--xs ollama-save-reply';
      saveBtn.textContent = 'Salvează în bibliotecă';
      saveBtn.addEventListener('click', function () { saveLastReplyAsFragment(text); });
      article.querySelector('.ollama-msg__body')?.appendChild(saveBtn);
    }
  }

  function updateTrainAgentLabel() {
    var el = document.getElementById('ollama-train-agent-label');
    if (el) el.textContent = 'Agent: ' + selectedSlug;
  }

  function renderLibrarySummaryHtml(summary, visible, noise, ragFile) {
    summary = summary || {};
    var pills = [
      { icon: 'fa-layer-group', val: visible, label: 'vizibile' },
      { icon: 'fa-user-gear', val: summary.operator || 0, label: 'operator' },
      { icon: 'fa-graduation-cap', val: summary.learned || 0, label: 'learned' },
    ];
    if (noise > 0) {
      pills.push({ icon: 'fa-filter', val: noise, label: 'zgomot ascuns', tone: 'muted' });
    }
    var pillsHtml = pills.map(function (p) {
      return '<span class="ai-lib-pill' + (p.tone ? ' ai-lib-pill--' + p.tone : '') + '">'
        + '<i class="fa-solid ' + p.icon + '" aria-hidden="true"></i> '
        + '<strong>' + esc(String(p.val)) + '</strong> ' + esc(p.label)
        + '</span>';
    }).join('');
    var fileHtml = ragFile
      ? '<div class="ai-lib-file"><i class="fa-solid fa-file-lines" aria-hidden="true"></i> <code>' + esc(ragFile) + '</code></div>'
      : '';
    return '<div class="ai-lib-summary__pills">' + pillsHtml + '</div>' + fileHtml;
  }

  function renderLibrary(data) {
    var summaryEl = document.getElementById('ollama-library-summary');
    var sectionsEl = document.getElementById('ollama-library-sections');
    if (!summaryEl || !sectionsEl) return;

    var summary = (data && data.summary) || {};
    var visible = data && data.total_visible != null ? data.total_visible : (summary.total || 0);
    var noise = data && data.total_noise != null ? data.total_noise : 0;
    summaryEl.innerHTML = renderLibrarySummaryHtml(summary, visible, noise, summary.rag_file || '');

    var sections = (data && data.sections) || [];
    if (!sections.length) {
      sectionsEl.innerHTML = '<p class="ollama-empty">Biblioteca e goală sau tot conținutul e zgomot — adaugă text sau scrape URL produs.</p>';
      return;
    }

    sectionsEl.innerHTML = sections.map(function (sec, idx) {
      var entries = sec.entries || [];
      var shown = entries.length;
      var total = sec.count || shown;
      var moreHint = total > shown
        ? '<p class="ollama-lib-more-hint">Afișate ' + shown + ' din ' + total + ' — folosește <strong>Caută</strong> pentru restul.</p>'
        : '';
      var body = entries.length
        ? entries.map(function (entry) { return renderLibraryEntry(entry); }).join('') + moreHint
        : '<p class="ollama-empty">Niciun fragment în această secțiune.</p>';
      var openClass = idx === 0 ? ' is-open' : '';
      return '<section class="ollama-lib-section' + openClass + '" data-section="' + esc(sec.id || '') + '">'
        + '<button type="button" class="ollama-lib-section__head" aria-expanded="' + (idx === 0 ? 'true' : 'false') + '">'
        + '<i class="fa-solid fa-chevron-right ollama-lib-section__chevron" aria-hidden="true"></i>'
        + '<span class="ollama-lib-section__icon">' + esc(sec.icon || '📄') + '</span>'
        + '<strong class="ollama-lib-section__label">' + esc(sec.label || sec.id) + '</strong>'
        + '<span class="ollama-lib-section__count">' + total + '</span>'
        + '</button>'
        + '<div class="ollama-lib-section__body">' + body + '</div>'
        + '</section>';
    }).join('');
  }

  function setLibrarySectionOpen(sectionEl, open) {
    if (!sectionEl) return;
    sectionEl.classList.toggle('is-open', !!open);
    var head = sectionEl.querySelector('.ollama-lib-section__head');
    if (head) head.setAttribute('aria-expanded', open ? 'true' : 'false');
  }

  function toggleLibrarySection(sectionEl) {
    if (!sectionEl) return;
    setLibrarySectionOpen(sectionEl, !sectionEl.classList.contains('is-open'));
  }

  function setAllLibrarySections(open) {
    document.querySelectorAll('#ollama-library-sections .ollama-lib-section').forEach(function (sec) {
      setLibrarySectionOpen(sec, open);
    });
  }

  function renderLibraryEntry(entry) {
    var parsed = entry.parsed || {};
    var tags = (entry.tags || []).slice(0, 4).join(', ');
    var pin = entry.pinned ? ' 📌' : '';
    var cardClass = 'ollama-lib-row';
    if (parsed.type === 'product') cardClass += ' ollama-lib-row--product';

    var body = '';
    if (parsed.type === 'product') {
      body = '<div class="ollama-lib-product">'
        + '<strong class="ollama-lib-product__name">' + esc(parsed.name || entry.text) + '</strong>'
        + '<div class="ollama-lib-product__meta">'
        + (parsed.sku ? '<span>SKU: <code>' + esc(parsed.sku) + '</code></span>' : '')
        + (parsed.price ? '<span>Preț: <strong>' + esc(parsed.price) + '</strong></span>' : '')
        + '</div>'
        + (parsed.url ? '<a class="ollama-lib-product__url" href="' + esc(parsed.url) + '" target="_blank" rel="noopener">' + esc(parsed.url) + '</a>' : '')
        + '</div>';
    } else if (parsed.type === 'supplier') {
      body = '<div class="ollama-lib-supplier"><strong>' + esc(parsed.name) + '</strong>'
        + ' · Cod: <code>' + esc(parsed.code) + '</code> · ' + esc(parsed.status) + '</div>';
    } else {
      body = '<p class="ollama-lib-row__text">' + esc((parsed.preview || entry.text || '').slice(0, 320)) + '</p>';
    }

    return '<article class="' + cardClass + '" data-entry-id="' + esc(entry.id || '') + '">'
      + '<div class="ollama-lib-row__meta">'
      + '<span class="ollama-badge ollama-badge--partial ai-lib-source-badge">' + esc(entry.source || 'learned') + pin + '</span>'
      + (tags ? '<span class="ai-lib-tags">' + esc(tags) + '</span>' : '')
      + '<time class="ai-lib-time">' + esc((entry.at || '').slice(0, 19)) + '</time></div>'
      + body
      + '<div class="ollama-lib-row__actions">'
      + '<button type="button" class="ollama-btn ollama-btn--ghost ollama-btn--xs ollama-lib-promote ai-lib-action" data-id="' + esc(entry.id || '') + '">'
      + '<i class="fa-solid fa-thumbtack" aria-hidden="true"></i><span>Fixează</span></button>'
      + '<button type="button" class="ollama-btn ollama-btn--ghost ollama-btn--xs ollama-lib-delete ai-lib-action ai-lib-action--danger" data-id="' + esc(entry.id || '') + '">'
      + '<i class="fa-solid fa-trash-can" aria-hidden="true"></i><span>Șterge</span></button>'
      + '</div></article>';
  }

  function loadLibrary(query) {
    var params = { action: 'agent_context_library', slug: selectedSlug };
    if (query) params.q = query;
    return agentGet(params).then(function (json) {
      if (json.success) {
        renderLibrary(json.data);
        if (query) setAllLibrarySections(true);
      }
    }).catch(function () {
      var sectionsEl = document.getElementById('ollama-library-sections');
      if (sectionsEl) sectionsEl.innerHTML = '<p class="ollama-empty">Eroare la încărcarea bibliotecii.</p>';
    });
  }

  function addManualFragment() {
    var textEl = document.getElementById('ollama-train-text');
    var text = textEl ? String(textEl.value || '').trim() : '';
    if (!text) { toast('Scrie un fragment.'); return; }
    var pinned = !!(document.getElementById('ollama-train-pin') && document.getElementById('ollama-train-pin').checked);
    toast('Salvez fragment…');
    return agentPost({
      action: 'agent_context_add',
      slug: selectedSlug,
      text: text,
      pinned: pinned,
      tags: ['manual', 'operator-panel'],
    }).then(function (json) {
      toast(json.message || (json.success ? 'Salvat' : 'Eșuat'));
      if (json.success && textEl) textEl.value = '';
      if (json.data) loadLibrary();
    });
  }

  function scrapeAndLearn() {
    var urlEl = document.getElementById('ollama-scrape-url');
    var url = urlEl ? String(urlEl.value || '').trim() : '';
    if (!url) { toast('Introdu URL.'); return; }
    var box = document.getElementById('ollama-scrape-result');
    if (box) { box.classList.remove('hidden'); box.textContent = 'Scrape inteligent în curs (Scraper + SEO + linkuri)…'; }
    toast('Scrape URL…');
    var intelligent = !!(document.getElementById('ollama-scrape-intelligent') && document.getElementById('ollama-scrape-intelligent').checked);
    return agentPost({
      action: 'agent_context_scrape_url',
      slug: selectedSlug,
      url: url,
      use_scraper: intelligent,
      intelligent: intelligent,
      source_id: (document.getElementById('ollama-scrape-source') && document.getElementById('ollama-scrape-source').value) || '',
      topic: (document.getElementById('ollama-scrape-topic') && document.getElementById('ollama-scrape-topic').value) || '',
      follow_links: !!(document.getElementById('ollama-scrape-follow') && document.getElementById('ollama-scrape-follow').checked),
      use_source_pipeline: !!(document.getElementById('ollama-scrape-pipeline') && document.getElementById('ollama-scrape-pipeline').checked),
      summarize: !!(document.getElementById('ollama-scrape-summarize') && document.getElementById('ollama-scrape-summarize').checked),
      pinned: !!(document.getElementById('ollama-scrape-pin') && document.getElementById('ollama-scrape-pin').checked),
    }).then(function (json) {
      if (box) box.textContent = JSON.stringify(json.data || json, null, 2);
      toast(json.message || (json.success ? 'Scrape OK' : 'Eșuat'));
      if (json.data) loadLibrary();
    }).catch(function (err) {
      if (box) box.textContent = String(err.message || err);
      toast(String(err.message || err));
    });
  }

  function seedCorpus500() {
    var box = document.getElementById('ollama-scrape-result');
    if (box) { box.classList.remove('hidden'); box.textContent = 'Generez 500 elemente RAG din DB + ePiesa + furnizori + agenți…'; }
    toast('Seed corpus 500…');
    var ragApi = (function () {
      var hub = document.getElementById('ai-hub-cfg');
      if (hub) {
        try {
          var parsed = JSON.parse(hub.textContent || '{}');
          if (parsed.ragApi) return parsed.ragApi;
        } catch (ignore) { /* noop */ }
      }
      var root = document.getElementById('ai-hub-root');
      return (root && root.getAttribute('data-rag-api')) || '/admin/public/api/ai_rag_endpoint.php';
    })();
    return fetch(ragApi, {
      method: 'POST',
      credentials: 'same-origin',
      headers: csrfHeader(),
      body: JSON.stringify({
        action: 'bulk_seed_corpus',
        target: 500,
        clear: false,
        index_embeddings: true,
        agent_slug: selectedSlug,
      }),
    }).then(function (r) { return r.json(); }).then(function (json) {
      if (box) box.textContent = JSON.stringify(json.data || json, null, 2);
      toast(json.message || 'Corpus generat');
      loadLibrary();
    }).catch(function (err) {
      if (box) box.textContent = String(err.message || err);
      toast(String(err.message || err));
    });
  }

  function testRag() {
    var qEl = document.getElementById('ollama-rag-query');
    var q = qEl ? String(qEl.value || '').trim() : '';
    if (!q) { toast('Introdu o întrebare test.'); return; }
    var box = document.getElementById('ollama-rag-test-result');
    if (box) { box.classList.remove('hidden'); box.textContent = 'Testez…'; }
    return agentPost({ action: 'agent_knowledge_test', slug: selectedSlug, query: q }).then(function (json) {
      if (box) box.textContent = JSON.stringify(json.data || json, null, 2);
      toast(json.message || 'Test RAG');
    });
  }

  function migrateLearned() {
    toast('Migrez din context.learned…');
    return agentPost({ action: 'agent_context_migrate', slug: selectedSlug }).then(function (json) {
      toast(json.message || 'Migrare');
      if (json.data) loadLibrary();
    });
  }

  function refreshRuntime() {
    toast('Regenerez runtime agent…');
    return agentPost({ action: 'agent_run_refresh', slug: selectedSlug }).then(function (json) {
      toast(json.message || 'Runtime');
    });
  }

  function promoteEntry(id) {
    return agentPost({ action: 'agent_context_promote', slug: selectedSlug, id: id, pinned: true }).then(function (json) {
      toast(json.message || 'Promovat');
      if (json.data) loadLibrary();
    });
  }

  function deleteEntry(id) {
    if (!window.confirm('Ștergi fragmentul din bibliotecă?')) return;
    return agentPost({ action: 'agent_context_delete', slug: selectedSlug, id: id }).then(function (json) {
      toast(json.message || 'Șters');
      if (json.data) loadLibrary();
    });
  }

  function saveLastReplyAsFragment(text) {
    text = String(text || '').trim();
    if (!text) return;
    return agentPost({
      action: 'agent_context_add',
      slug: selectedSlug,
      text: text,
      pinned: false,
      tags: ['manual', 'din-chat'],
    }).then(function (json) {
      toast(json.message || 'Salvat din chat');
      if (json.data) loadLibrary();
    });
  }

  function switchTrainTab(tab) {
    document.querySelectorAll('.ollama-train-tab').forEach(function (btn) {
      btn.classList.toggle('is-active', btn.getAttribute('data-train-tab') === tab);
    });
    document.querySelectorAll('[data-train-panel]').forEach(function (panel) {
      panel.classList.toggle('hidden', panel.getAttribute('data-train-panel') !== tab);
    });
    if (tab === 'library') loadLibrary();
  }

  function loadHealth() {
    return settingsGet('ollama_control').then(function (json) {
      if (!json.success) throw new Error(json.message || 'Health eșuat');
      renderHealth(json.data);
    }).catch(function (err) {
      var pills = document.getElementById('ollama-status-pills');
      if (pills) {
        pills.innerHTML = '<span class="ollama-pill ollama-pill--err">Eroare: ' + esc(err.message || err) + '</span>';
      }
    });
  }

  function loadAgents() {
    return agentGet({ action: 'agent_ollama_status' }).then(function (json) {
      if (!json.success) throw new Error(json.message || 'Status agenți eșuat');
      renderAgentStatus(json.data);
      loadEscalations();
    }).catch(function (err) {
      var inline = document.getElementById('ollama-agent-status');
      if (inline) {
        inline.textContent = 'Eroare agenți: ' + (err.message || err);
        inline.className = 'ollama-inline-status is-warn';
      }
    });
  }

  function loadEscalations() {
    return agentGet({ action: 'shop_chat_escalations', limit: 10 }).then(function (res) {
      var section = document.getElementById('ollama-esc-section');
      var list = document.getElementById('ollama-esc-list');
      var countEl = document.getElementById('ollama-esc-count');
      if (!section || !list) return;
      var data = (res && res.data) || {};
      var items = data.items || [];
      var count = data.open_count != null ? data.open_count : items.length;
      if (countEl) countEl.textContent = String(count);
      if (count <= 0) {
        section.classList.add('hidden');
        return;
      }
      section.classList.remove('hidden');
      list.innerHTML = items.map(function (item) {
        return '<article class="ollama-esc-item">'
          + '<div class="ollama-esc-item__meta">#' + esc(item.id) + ' · ' + esc(item.channel || 'chat') + ' · ' + esc(item.created_at || '') + '</div>'
          + '<p>' + esc(item.message || '') + '</p>'
          + '<button type="button" class="ollama-btn ollama-btn--ghost ollama-btn--xs ollama-esc-resolve" data-id="' + esc(item.id) + '">Rezolvat</button>'
          + '</article>';
      }).join('');
    }).catch(function () { /* optional */ });
  }

  function loadSystemPanel() {
    return loadHealth().then(function () {
      return settingsGet('ollama_opportunities').then(function (json) {
        if (json.data) renderOpportunities(json.data);
      }).catch(function () { /* optional */ });
    });
  }

  function loadAll() {
    return loadHealth().then(function () {
      return loadAgents();
    });
  }

  function testPing() {
    var box = document.getElementById('ollama-ping-result');
    var btn = document.getElementById('ollama-ping-btn');
    if (box) {
      box.classList.remove('hidden');
      box.textContent = 'Se testează conexiunea… (poate dura până la 90 sec)';
    }
    if (btn) btn.disabled = true;
    return settingsPost({ action: 'ollama_integration_test', integration_id: 'erp.client' })
      .then(function (json) {
        var d = (json && json.data) || {};
        if (box) {
          box.textContent = JSON.stringify(d, null, 2);
        }
        toast(json.success ? 'Test OK' : (json.message || 'Test eșuat'));
        return loadHealth();
      })
      .catch(function (err) {
        var msg = err.name === 'AbortError' ? 'Timeout — Ollama nu a răspuns la timp.' : String(err.message || err);
        if (box) box.textContent = msg;
        toast(msg);
      })
      .finally(function () {
        if (btn) btn.disabled = false;
      });
  }

  function oppStatusBadge(status) {
    status = String(status || 'fail');
    if (status === 'ok') return '<span class="ollama-badge ollama-badge--active">OK</span>';
    if (status === 'warn') return '<span class="ollama-badge ollama-badge--partial">ATENȚIE</span>';
    return '<span class="ollama-badge ollama-badge--dead">EȘEC</span>';
  }

  function renderOpportunities(data) {
    var el = document.getElementById('ollama-opportunities');
    var summary = document.getElementById('ollama-opp-summary');
    if (!el || !data) return;
    var s = data.summary || {};
    if (summary) {
      summary.textContent = (s.ok || 0) + ' OK · ' + (s.warn || 0) + ' atenție · ' + (s.fail || 0) + ' eșec';
    }
    var items = data.items || [];
    if (!items.length) {
      el.innerHTML = '<div class="ollama-empty">Nicio verificare.</div>';
      return;
    }
    el.innerHTML = items.map(function (row) {
      return '<article class="ollama-opp-row" data-opp="' + esc(row.id) + '">' +
        '<div class="ollama-opp-row__head">' +
        '<strong>#' + esc(row.problem || '') + ' ' + esc(row.title || row.id) + '</strong>' +
        oppStatusBadge(row.status) +
        '</div>' +
        '<p class="ollama-opp-row__msg">' + esc(row.message || '') + '</p>' +
        '<div class="ollama-opp-row__meta">' +
        '<span>' + esc(row.category || '') + '</span>' +
        (row.latency_ms ? '<span>' + row.latency_ms + ' ms</span>' : '') +
        '<button type="button" class="ollama-btn ollama-btn--ghost ollama-btn--xs ollama-opp-retest" data-opp="' + esc(row.id) + '">↻</button>' +
        '</div></article>';
    }).join('');
  }

  function renderVerifyResults(data, live) {
    renderOpportunities(data);
    var auditEl = document.getElementById('ollama-audit-steps');
    var auditSummary = document.getElementById('ollama-audit-summary');
    if (!auditEl || !data) return;

    var s = data.summary || {};
    var label = live ? 'Live Ollama' : 'Structură';
    if (auditSummary) {
      auditSummary.textContent = label + ': ' + (s.ok || 0) + ' OK · ' + (s.warn || 0) + ' atenție · ' + (s.fail || 0) + ' eșec';
    }

    var items = data.items || [];
    if (!items.length) {
      auditEl.innerHTML = '<div class="ollama-empty">Niciun rezultat de verificare.</div>';
      return;
    }

    auditEl.innerHTML = items.map(function (row) {
      var st = String(row.status || 'fail');
      return '<article class="ollama-audit-step is-' + esc(st) + '">'
        + '<div class="ollama-audit-step__head"><span>#' + esc(row.problem || row.id || '') + ' ' + esc(row.title || row.id) + '</span>'
        + oppStatusBadge(st) + '</div>'
        + '<p class="ollama-audit-step__msg">' + esc(row.message || '') + '</p>'
        + '</article>';
    }).join('');
  }

  function setVerifyButtonsBusy(busyState) {
    ['ollama-verify-structure', 'ollama-verify-live', 'ollama-run-audit'].forEach(function (id) {
      var btn = document.getElementById(id);
      if (btn) btn.disabled = !!busyState;
    });
  }

  function openCheckpointsDetails() {
    var details = document.getElementById('ollama-checkpoints-details')
      || document.querySelector('.ai-sys-details');
    if (details && typeof details.open !== 'undefined') {
      details.open = true;
    }
  }

  function renderAudit(data) {
    var el = document.getElementById('ollama-audit-steps');
    var summary = document.getElementById('ollama-audit-summary');
    if (!el || !data) return;
    var s = data.summary || {};
    if (summary) {
      summary.textContent = (s.ok || 0) + ' OK · ' + (s.warn || 0) + ' atenție · ' + (s.fail || 0) + ' eșec';
    }
    var steps = data.steps || [];
    if (!steps.length) {
      el.innerHTML = '<div class="ollama-empty">Niciun pas.</div>';
      return;
    }
    el.innerHTML = steps.map(function (row) {
      var st = String(row.status || 'fail');
      return '<article class="ollama-audit-step is-' + esc(st) + '">'
        + '<div class="ollama-audit-step__head"><span>#' + esc(row.step || row.id) + ' ' + esc(row.title) + '</span>'
        + oppStatusBadge(st) + '</div>'
        + '<p class="ollama-audit-step__msg">' + esc(row.message || '') + ' · ' + (row.ms || 0) + ' ms</p>'
        + '</article>';
    }).join('');
  }

  function runAudit20() {
    var el = document.getElementById('ollama-audit-steps');
    if (!agentApi()) {
      toast('Lipsește URL agent API — reîncarcă pagina.');
      return Promise.resolve();
    }
    setVerifyButtonsBusy(true);
    if (el) el.innerHTML = '<div class="ollama-empty"><i class="fa-solid fa-spinner fa-spin"></i> Audit 20 pași în curs…</div>';
    toast('Rulez audit 20 pași…');
    return agentGet({ action: 'agent_ollama_audit' }, 120000).then(function (json) {
      if (json.data) renderAudit(json.data);
      var s = (json.data && json.data.summary) || {};
      toast('Audit: ' + (s.ok || 0) + ' OK, ' + (s.fail || 0) + ' eșec');
    }).catch(function (err) {
      if (el) el.innerHTML = '<div class="ollama-empty">Eroare audit: ' + esc(String(err.message || err)) + '</div>';
      toast(String(err.message || err));
    }).finally(function () {
      setVerifyButtonsBusy(false);
    });
  }

  function bindSectionNav() {
    var links = document.querySelectorAll('.ollama-section-nav__link');
    links.forEach(function (link) {
      link.addEventListener('click', function (e) {
        links.forEach(function (l) { l.classList.remove('is-active'); });
        link.classList.add('is-active');
      });
    });
  }

  function runVerifyAll(live) {
    var oppEl = document.getElementById('ollama-opportunities');
    var auditEl = document.getElementById('ollama-audit-steps');
    var loadingMsg = live
      ? 'Test Live Ollama în curs… (poate dura 1–2 min)'
      : 'Verific structura fișierelor și modulelor…';

    if (!settingsApi()) {
      toast('Lipsește URL settings API — reîncarcă pagina.');
      return Promise.resolve();
    }

    openCheckpointsDetails();
    setVerifyButtonsBusy(true);

    if (auditEl) auditEl.innerHTML = '<div class="ollama-empty"><i class="fa-solid fa-spinner fa-spin"></i> ' + loadingMsg + '</div>';
    if (oppEl) oppEl.innerHTML = '<div class="ollama-empty">' + loadingMsg + '</div>';
    toast(live ? 'Test Live Ollama pornit…' : 'Verificare structură pornită…');

    return settingsPost({ action: 'ollama_verify_all', live: !!live }, live ? 180000 : 90000)
      .then(function (json) {
        if (json.data) renderVerifyResults(json.data, !!live);
        toast(json.message || (json.success ? 'Verificări OK' : 'Verificări cu eșecuri'));
      })
      .catch(function (err) {
        var msg = err.name === 'AbortError' ? 'Timeout verificare — încearcă din nou.' : String(err.message || err);
        if (auditEl) auditEl.innerHTML = '<div class="ollama-empty">Eroare: ' + esc(msg) + '</div>';
        if (oppEl) oppEl.innerHTML = '<div class="ollama-empty">Eroare: ' + esc(msg) + '</div>';
        toast(msg);
      })
      .finally(function () {
        setVerifyButtonsBusy(false);
      });
  }

  function retestOpportunity(id, live) {
    return settingsPost({ action: 'ollama_verify_one', opportunity_id: id, live: !!live }, live ? 120000 : 30000)
      .then(function (json) {
        toast(json.message || 'Retest');
        return settingsGet('ollama_opportunities' + (live ? '&live=1' : '')).then(function (snap) {
          if (snap.data) renderOpportunities(snap.data);
        });
      });
  }

  function testIntegration(id) {
    var box = document.getElementById('ollama-test-result') || document.getElementById('ollama-ping-result');
    if (box) {
      box.classList.remove('hidden');
      box.textContent = 'Test ' + id + '… (poate dura până la 90 sec)';
    }
    return settingsPost({ action: 'ollama_integration_test', integration_id: id }, 120000)
      .then(function (json) {
        var d = (json && json.data) || {};
        if (box) box.textContent = JSON.stringify(d, null, 2);
        toast(json.success ? ('Test ' + id + ' OK') : (json.message || 'Eșuat'));
        return loadHealth();
      })
      .catch(function (err) {
        var msg = err.name === 'AbortError' ? 'Timeout test integrare.' : String(err.message || err);
        if (box) box.textContent = msg;
        toast(msg);
      });
  }

  function sendMessage(text, options) {
    options = options || {};
    text = String(text || '').trim();
    if (!text || busy) return;
    busy = true;
    updateChatChrome();
    appendMessage('user', text);

    var useKnowledge = options.smoke
      ? false
      : !!(document.getElementById('ollama-use-knowledge') && document.getElementById('ollama-use-knowledge').checked);
    var useLiveDb = options.smoke
      ? false
      : !!(document.getElementById('ollama-use-live-db') && document.getElementById('ollama-use-live-db').checked);
    var waitHint = options.smoke
      ? '⏳ Smoke test (~30–60 sec)…'
      : (useKnowledge ? '⏳ Caut în bibliotecă…' : (useLiveDb ? '⏳ Interoghez MySQL…' : '⏳ Agent procesează…'));
    appendMessage('assistant', waitHint, { model: '…' });

    var input = document.getElementById('ollama-input');
    if (input) input.value = '';

    agentPost({
      action: 'agent_ollama_chat',
      slug: selectedSlug,
      message: text,
      smoke_only: !!options.smoke,
      use_knowledge: useKnowledge,
      use_live_db: useLiveDb,
    }, useKnowledge ? 180000 : 90000).then(function (json) {
      busy = false;
      updateChatChrome();
      var box = document.getElementById('ollama-messages');
      if (box && box.lastElementChild && box.lastElementChild.textContent.indexOf('⏳') !== -1) {
        box.removeChild(box.lastElementChild);
      }
      if (json.success && json.data && json.data.content) {
        appendMessage('assistant', json.data.content, {
          model: json.data.model || '',
          mode: json.data.mode || '',
        });
      } else {
        appendMessage('assistant', json.message || (json.data && json.data.error) || 'Eroare la răspuns', {});
        toast(json.message || 'Chat eșuat');
      }
    }).catch(function (err) {
      busy = false;
      updateChatChrome();
      var box = document.getElementById('ollama-messages');
      if (box && box.lastElementChild && box.lastElementChild.textContent.indexOf('⏳') !== -1) {
        box.removeChild(box.lastElementChild);
      }
      var msg = err.name === 'AbortError'
        ? (useKnowledge ? 'Timeout — agentul nu a răspuns în 3 minute.' : 'Timeout — agentul nu a răspuns în 90 secunde.')
        : String(err.message || err);
      appendMessage('assistant', 'Eroare: ' + msg, {});
      toast(msg);
    });
  }

  function dailyStats() {
    toast('Generez raport…');
    var box = document.getElementById('ollama-ping-result');
    if (box) {
      box.classList.remove('hidden');
      box.textContent = 'Generez raport zilnic…';
    }
    return agentPost({ action: 'agent_ollama_daily_stats' }, 120000).then(function (json) {
      var content = (json.data && json.data.content) || json.message || '';
      if (box) {
        box.textContent = content || JSON.stringify(json.data || json, null, 2);
      } else {
        appendMessage('assistant', content, { model: 'agent-statistici' });
      }
      toast(json.success ? 'Raport generat' : 'Eșuat');
    }).catch(function (err) {
      var msg = String(err.message || err);
      if (box) box.textContent = msg;
      toast(msg);
    });
  }

  function bindSystemPanelEvents() {
    if (window.__besoiuOllamaSystemBound) return;
    window.__besoiuOllamaSystemBound = true;

    document.addEventListener('click', function (e) {
      if (e.target.closest('#ollama-run-audit')) {
        e.preventDefault();
        runAudit20();
        return;
      }
      if (e.target.closest('#ollama-verify-structure')) {
        e.preventDefault();
        runVerifyAll(false);
        return;
      }
      if (e.target.closest('#ollama-verify-live')) {
        e.preventDefault();
        runVerifyAll(true);
        return;
      }
    });
  }

  function bindEvents() {
    document.getElementById('ollama-use-knowledge')?.addEventListener('change', function () {
      updateChatChrome();
    });
    document.getElementById('ollama-use-live-db')?.addEventListener('change', function () {
      updateChatChrome();
    });

    document.getElementById('ai-system-refresh')?.addEventListener('click', function () {
      loadSystemPanel().then(function () { toast('Sistem actualizat'); });
      document.dispatchEvent(new CustomEvent('ai-hub:system-refresh'));
    });

    document.getElementById('ollama-ping-btn')?.addEventListener('click', function () {
      testPing();
    });

    document.getElementById('ollama-daily-stats-btn')?.addEventListener('click', function () {
      dailyStats();
    });

    document.getElementById('ollama-agent-cards')?.addEventListener('click', function (e) {
      var btn = e.target.closest('[data-slug]');
      if (!btn) return;
      selectedSlug = btn.getAttribute('data-slug') || 'agent-produse';
      renderAgentCards((statusData && statusData.agents) || []);
      updateChatChrome();
      updateTrainAgentLabel();
      loadLibrary();
    });

    document.getElementById('ollama-send')?.addEventListener('click', function () {
      var input = document.getElementById('ollama-input');
      sendMessage(input ? input.value : '', {});
    });

    document.getElementById('ollama-send-smoke')?.addEventListener('click', function () {
      var input = document.getElementById('ollama-input');
      sendMessage(input ? input.value : '', { smoke: true });
    });

    document.getElementById('ollama-input')?.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage(e.target.value);
      }
    });

    document.getElementById('ollama-quick')?.addEventListener('click', function (e) {
      var btn = e.target.closest('[data-quick]');
      if (!btn) return;
      sendMessage(btn.getAttribute('data-quick'));
    });

    document.getElementById('ollama-run-audit')?.addEventListener('click', function (e) {
      e.preventDefault();
      runAudit20();
    });

    document.getElementById('ollama-verify-structure')?.addEventListener('click', function (e) {
      e.preventDefault();
      runVerifyAll(false);
    });

    document.getElementById('ollama-verify-live')?.addEventListener('click', function (e) {
      e.preventDefault();
      runVerifyAll(true);
    });

    document.getElementById('ollama-opportunities')?.addEventListener('click', function (e) {
      var btn = e.target.closest('.ollama-opp-retest');
      if (!btn) return;
      retestOpportunity(btn.getAttribute('data-opp') || '', true);
    });

    document.getElementById('ollama-integrations')?.addEventListener('click', function (e) {
      var btn = e.target.closest('.ollama-int-test');
      if (!btn) return;
      testIntegration(btn.getAttribute('data-id') || '');
    });

    document.querySelectorAll('.ollama-train-tab').forEach(function (btn) {
      btn.addEventListener('click', function () {
        switchTrainTab(btn.getAttribute('data-train-tab') || 'manual');
      });
    });

    document.getElementById('ollama-train-add')?.addEventListener('click', addManualFragment);
    document.getElementById('ollama-scrape-btn')?.addEventListener('click', scrapeAndLearn);
    document.getElementById('ollama-train-migrate')?.addEventListener('click', migrateLearned);

    document.getElementById('ollama-library-search-btn')?.addEventListener('click', function () {
      var q = document.getElementById('ollama-library-search');
      loadLibrary(q ? String(q.value || '').trim() : '');
    });
    document.getElementById('ollama-library-refresh-btn')?.addEventListener('click', function () {
      var q = document.getElementById('ollama-library-search');
      if (q) q.value = '';
      loadLibrary();
    });
    document.getElementById('ollama-library-search')?.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        loadLibrary(String(e.target.value || '').trim());
      }
    });

    document.getElementById('ollama-library-sections')?.addEventListener('keydown', function (e) {
      if (e.key !== 'Enter' && e.key !== ' ') return;
      var head = e.target.closest('.ollama-lib-section__head');
      if (!head) return;
      e.preventDefault();
      toggleLibrarySection(head.closest('.ollama-lib-section'));
    });

    document.getElementById('ollama-library-sections')?.addEventListener('click', function (e) {
      var head = e.target.closest('.ollama-lib-section__head');
      if (head) {
        toggleLibrarySection(head.closest('.ollama-lib-section'));
        return;
      }
      var promote = e.target.closest('.ollama-lib-promote');
      if (promote) return promoteEntry(promote.getAttribute('data-id') || '');
      var del = e.target.closest('.ollama-lib-delete');
      if (del) return deleteEntry(del.getAttribute('data-id') || '');
    });

    document.getElementById('ollama-library-expand-all')?.addEventListener('click', function () {
      setAllLibrarySections(true);
    });
    document.getElementById('ollama-library-collapse-all')?.addEventListener('click', function () {
      setAllLibrarySections(false);
    });
  }

  function init() {
    bindSystemPanelEvents();

    if (!document.getElementById('ollama-control-root') && !document.getElementById('ai-hub-panel-system')) {
      return;
    }

    bindEvents();
    bindSectionNav();
    updateTrainAgentLabel();

    if (document.getElementById('ollama-control-root')) {
      loadAll().then(function () { loadLibrary(); });
    }

    document.addEventListener('ai-hub:refresh', function () {
      if (document.getElementById('ollama-control-root')) {
        loadAll().then(function () { loadLibrary(); });
      }
    });
    document.addEventListener('ai-hub:tab', function (e) {
      var tab = (e.detail && e.detail.tab) || '';
      if (tab === 'library' && document.getElementById('ollama-control-root')) loadLibrary();
      if (tab === 'system') {
        if (document.getElementById('ollama-control-root')) loadAll();
        loadSystemPanel();
      }
    });
    document.addEventListener('ai-hub:system-refresh', function () {
      loadSystemPanel();
    });
    document.addEventListener('ai-hub:lib-tab', function (e) {
      var t = (e.detail && e.detail.tab) || '';
      if (t === 'explore' && document.getElementById('ollama-control-root')) loadLibrary();
    });
  }

  window.OllamaControl = {
    loadLibrary: loadLibrary,
    loadAll: loadAll,
    loadSystemPanel: loadSystemPanel,
    refresh: loadAll,
    runAudit20: runAudit20,
    runVerifyAll: runVerifyAll,
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
