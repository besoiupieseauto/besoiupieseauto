/**
 * AI Agent — tab Supervizor v2 (carduri, progress, accordion)
 */
(function () {
  'use strict';

  function cfg() {
    var el = document.getElementById('ai-agent-cfg');
    if (!el) return { api: '' };
    try { return JSON.parse(el.textContent || '{}'); } catch (e) { return { api: '' }; }
  }

  var ENDPOINT = cfg().api || '';
  var AI_URLS = cfg().urls || {};
  var root = document.getElementById('ai-supervisor-root');
  if (!root || !ENDPOINT) return;

  var SECTION_META = {
    cycle: { icon: '⚡', phase: 'Ciclu', cls: 'cycle', open: true },
    catalog: { icon: '📦', phase: 'Faza 2', cls: 'catalog', open: false },
    pipeline: { icon: '🔧', phase: 'Faza 3', cls: 'pipeline', open: false },
    tokens: { icon: '🪙', phase: 'Faza 3', cls: 'tokens', open: false },
    suppliers: { icon: '🚚', phase: 'Faza 3', cls: 'suppliers', open: false },
    conversations: { icon: '💬', phase: 'Faza 4', cls: 'conversations', open: false },
  };

  function toast(msg, err) {
    if (window.aiAgentToast) window.aiAgentToast(msg, err);
    else alert(msg);
  }

  function get(params) {
    return fetch(ENDPOINT + '?' + new URLSearchParams(params), { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        if (!j.success) throw new Error(j.message || 'Eroare API');
        return j;
      });
  }

  function post(body) {
    return fetch(ENDPOINT, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body),
    }).then(function (r) { return r.json(); }).then(function (j) {
      if (!j.success) throw new Error(j.message || 'Eroare');
      return j;
    });
  }

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];
    });
  }

  function fmtTime(iso) {
    if (!iso) return '—';
    try {
      var d = new Date(iso);
      if (isNaN(d.getTime())) return iso;
      return d.toLocaleString('ro-RO', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit' });
    } catch (e) { return iso; }
  }

  function badgeClass(headline) {
    var h = String(headline || '').toLowerCase();
    if (h.indexOf('succes') >= 0 || h.indexOf('100') >= 0 || h.indexOf('ok') >= 0 || h.indexOf('chei active') >= 0) return 'ok';
    if (h.indexOf('nicio cheie') >= 0 || h.indexOf('eș') >= 0 || h.indexOf('problem') >= 0 || h.indexOf('oprit') >= 0) return 'warn';
    return 'neutral';
  }

  function renderMetrics(metrics) {
    if (!metrics || !metrics.length) return '';
    return '<div class="ai-sup-metrics ai-sup-metrics--wide">' + metrics.map(function (m) {
      var n = parseInt(String(m.val).replace(/\D/g, ''), 10);
      var cls = m.warn ? ' ai-sup-metric--warn' : (m.ok || n === 0 ? ' ai-sup-metric--ok' : '');
      return '<div class="ai-sup-metric' + cls + '"><span class="ai-sup-metric__val">' + esc(m.val) + '</span><span class="ai-sup-metric__lbl">' + esc(m.lbl) + '</span></div>';
    }).join('') + '</div>';
  }

  function renderSummaryList(lines) {
    if (!lines || !lines.length) return '';
    return '<ul class="ai-sup-summary">' + lines.map(function (l) {
      return '<li>' + esc(l) + '</li>';
    }).join('') + '</ul>';
  }

  function renderKeysStatus(keysStatus) {
    keysStatus = keysStatus || {};
    var items = keysStatus.items || [];
    if (!items.length) return '';
    var llm = items.filter(function (k) { return k.group === 'llm'; });
    var pipe = items.filter(function (k) { return k.group === 'pipeline'; });
    var html = '<div class="ai-sup-keys">';
    html += '<div class="ai-sup-keys__head"><span>Chei API (Setări)</span>';
    html += '<a href="' + esc(keysStatus.settings_url || '/admin/settings') + '" class="ai-sup-keys__link">Deschide Setări →</a></div>';
    if (llm.length) {
      html += '<p class="ai-sup-keys__group">LLM — consum logat în jurnal</p>';
      html += '<div class="ai-sup-keys__grid">' + llm.map(function (k) {
        return '<div class="ai-sup-key ai-sup-key--' + (k.configured ? 'ok' : 'miss') + '">'
          + '<span class="ai-sup-key__dot"></span><span class="ai-sup-key__name">' + esc(k.label) + '</span>'
          + '<span class="ai-sup-key__state">' + (k.configured ? 'Setat' : 'Lipsă') + '</span>'
          + (k.model ? '<span class="ai-sup-key__model">' + esc(k.model) + '</span>' : '')
          + '</div>';
      }).join('') + '</div>';
    }
    if (pipe.length) {
      html += '<p class="ai-sup-keys__group">Pipeline imagini / catalog</p>';
      html += '<div class="ai-sup-keys__grid">' + pipe.map(function (k) {
        return '<div class="ai-sup-key ai-sup-key--' + (k.configured ? 'ok' : 'miss') + '">'
          + '<span class="ai-sup-key__dot"></span><span class="ai-sup-key__name">' + esc(k.label) + '</span>'
          + '<span class="ai-sup-key__state">' + (k.configured ? 'Setat' : 'Lipsă') + '</span></div>';
      }).join('') + '</div>';
    }
    html += '</div>';
    return html;
  }

  function renderRecs(recs) {
    if (!recs || !recs.length) return '';
    return '<div class="ai-sup-recs">' + recs.map(function (r) {
      return '<span class="ai-sup-rec">→ ' + esc(r) + '</span>';
    }).join('') + '</div>';
  }

  function renderMetricsFromSummary(summary, id) {
    if (!summary || !summary.length) return '';
    var metrics = [];
    summary.forEach(function (line) {
      var m = String(line).match(/^([^:]+):\s*([\d.,]+)/);
      if (m) metrics.push({ val: m[2], lbl: m[1].trim() });
    });
    if (!metrics.length && id === 'catalog') return '';
    if (!metrics.length) {
      return '<ul class="ai-sup-deep__list" style="margin:0;padding-left:1.2em;font-size:0.8rem;color:#64748b">' +
        summary.map(function (l) { return '<li>' + esc(l) + '</li>'; }).join('') + '</ul>';
    }
    return '<div class="ai-sup-metrics">' + metrics.map(function (m) {
      var n = parseInt(String(m.val).replace(/\./g, ''), 10) || 0;
      var cls = n > 0 ? ' ai-sup-metric--warn' : ' ai-sup-metric--ok';
      return '<div class="ai-sup-metric' + cls + '"><span class="ai-sup-metric__val">' + esc(m.val) + '</span><span class="ai-sup-metric__lbl">' + esc(m.lbl) + '</span></div>';
    }).join('') + '</div>';
  }

  function renderJobs(jobs) {
    if (!jobs || !jobs.length) return '';
    return '<div class="ai-sup-jobs">' + jobs.map(function (j) {
      var st = j.status === 'ok' ? 'ok' : (j.status === 'skip' ? 'skip' : 'warn');
      return '<span class="ai-sup-job ai-sup-job--' + st + '" title="' + esc(j.detail || '') + '">'
        + '<span class="ai-sup-job__dot"></span>' + esc(j.label) + '</span>';
    }).join('') + '</div>';
  }

  function renderErrors(tests) {
    if (!tests || !tests.length) return '';
    return '<div class="ai-sup-errors">' + tests.map(function (t) {
      return '<div class="ai-sup-error"><strong>#' + esc(t.id || '?') + ' ' + esc(t.name || '') + '</strong><br>' + esc(t.message || '') + '</div>';
    }).join('') + '</div>';
  }

  function renderTokenTable(rows, cols) {
    if (!rows || !rows.length) return '';
    var head = cols.map(function (c) { return '<th>' + esc(c.label) + '</th>'; }).join('');
    var body = rows.map(function (r) {
      return '<tr>' + cols.map(function (c) {
        var v = r[c.key];
        if (c.fmt === 'num') v = (v || 0).toLocaleString('ro-RO');
        return '<td>' + esc(v == null ? '—' : v) + '</td>';
      }).join('') + '</tr>';
    }).join('');
    return '<div class="ai-sup-table-wrap"><table class="ai-sup-table"><thead><tr>' + head + '</tr></thead><tbody>' + body + '</tbody></table></div>';
  }

  function renderDeepSection(id, title, section) {
    section = section || {};
    var meta = SECTION_META[id] || { icon: '•', phase: '', cls: id, open: false };
    var bCls = badgeClass(section.headline);
    var openCls = meta.open ? ' is-open' : '';
    var html = '<article class="ai-sup-card ai-sup-card--' + meta.cls + openCls + '" id="ai-sup-deep-' + esc(id) + '" data-sup-card>';

    html += '<header class="ai-sup-card__head" role="button" tabindex="0" aria-expanded="' + (meta.open ? 'true' : 'false') + '">';
    html += '<div class="ai-sup-card__icon">' + meta.icon + '</div>';
    html += '<div class="ai-sup-card__titles">';
    html += '<span class="ai-sup-card__phase">' + esc(meta.phase) + '</span>';
    html += '<h4 class="ai-sup-card__headline">' + esc(title.replace(/^Faza \d · /, '')) + '</h4>';
    html += '</div>';
    html += '<span class="ai-sup-card__badge ai-sup-card__badge--' + bCls + '">' + esc(section.headline || '—') + '</span>';
    html += '<span class="ai-sup-card__chevron">▼</span>';
    html += '</header>';

    html += '<div class="ai-sup-card__body">';
    if (section.what_is) {
      html += '<p class="ai-sup-card__what">' + esc(section.what_is) + '</p>';
    }

    if (id === 'cycle') {
      if (section.summary && section.summary.length) {
        html += '<p style="font-size:0.82rem;color:#475569;margin:0 0 12px">' + esc(section.summary.join(' · ')) + '</p>';
      }
      html += renderJobs(section.jobs);
    }

    if (id === 'catalog') {
      html += renderMetricsFromSummary(section.summary, id);
      if (section.issues && section.issues.length) {
        html += '<div class="ai-sup-errors">' + section.issues.slice(0, 6).map(function (i) {
          return '<div class="ai-sup-error">' + esc(typeof i === 'string' ? i : (i.message || i.title || JSON.stringify(i))) + '</div>';
        }).join('') + '</div>';
      }
    }

    if (id === 'pipeline') {
      var passed = null;
      var total = null;
      var hm = String(section.headline || '').match(/(\d+)\s*\/\s*(\d+)/);
      if (hm) {
        passed = parseInt(hm[1], 10);
        total = parseInt(hm[2], 10);
      }
      if (passed != null && total != null) {
        html += '<div class="ai-sup-metrics"><div class="ai-sup-metric ai-sup-metric--ok"><span class="ai-sup-metric__val">' + passed + '</span><span class="ai-sup-metric__lbl">Teste OK</span></div>';
        html += '<div class="ai-sup-metric ai-sup-metric--warn"><span class="ai-sup-metric__val">' + (total - passed) + '</span><span class="ai-sup-metric__lbl">Eșuate</span></div></div>';
      } else if (section.summary) {
        html += renderMetricsFromSummary(section.summary, id);
      }
      html += renderErrors(section.failed_tests);
    }

    if (id === 'tokens') {
      html += renderMetrics([
        { val: String(section.tokens_today != null ? section.tokens_today.toLocaleString('ro-RO') : '0'), lbl: 'Consum azi (tokeni LLM)' },
        { val: String(section.calls_today || 0), lbl: 'Apeluri LLM azi' },
        { val: String((section.keys_status && section.keys_status.configured_count) || 0), lbl: 'Chei API setate', ok: true },
        { val: String(section.month_total != null ? section.month_total.toLocaleString('ro-RO') : '0'), lbl: 'Consum luna curentă' },
      ]);
      html += renderKeysStatus(section.keys_status);
      if (section.consumers && section.consumers.length) {
        html += '<p class="ai-sup-section-label">Unde apare consumul LLM</p>';
        html += '<div class="ai-sup-bots ai-sup-bots--wide">' + section.consumers.map(function (c) {
          var initial = String(c.label || '?').charAt(0).toUpperCase();
          return '<div class="ai-sup-bot"><div class="ai-sup-bot__avatar">' + initial + '</div><div><div class="ai-sup-bot__name">' + esc(c.label) + '</div><div class="ai-sup-bot__meta">' + esc(c.desc) + '</div></div></div>';
        }).join('') + '</div>';
      }
      if (section.by_source && section.by_source.length) {
        html += '<p class="ai-sup-section-label">Jurnal consum azi (pe sursă)</p>';
        html += renderTokenTable(section.by_source, [
          { key: 'label', label: 'Sursă' },
          { key: 'tokens', label: 'Tokeni', fmt: 'num' },
          { key: 'calls', label: 'Apeluri', fmt: 'num' },
        ]);
      } else if ((section.keys_status && section.keys_status.configured_count) > 0) {
        html += '<div class="ai-sup-empty ai-sup-empty--ok">Cheile sunt configurate. 0 consum azi = niciun apel LLM logat încă (agent, diagnostic, chat).</div>';
      } else {
        html += '<div class="ai-sup-empty">Completează cheile în Setări, apoi rulează agent sau test din /admin/ai-tokens.</div>';
      }
      if (section.by_model && section.by_model.length) {
        html += renderTokenTable(section.by_model, [
          { key: 'provider', label: 'Provider' },
          { key: 'model', label: 'Model' },
          { key: 'tokens', label: 'Tokeni', fmt: 'num' },
        ]);
      }
      if (section.recent_calls && section.recent_calls.length) {
        html += section.recent_calls.slice(0, 8).map(function (c) {
          return '<div class="ai-sup-call"><span class="ai-sup-call__time">' + esc(c.at) + '</span> '
            + esc(c.provider) + ' · <code>' + esc(c.model) + '</code> · ' + esc(c.source)
            + '<span class="ai-sup-call__tok">' + (c.total || 0).toLocaleString('ro-RO') + ' tok</span></div>';
        }).join('');
      }
      if (section.link) {
        html += '<p style="margin-top:10px"><a href="' + esc(section.link || AI_URLS.settings_tokens || '/admin/settings?tab=tokens') + '" class="ai-btn ai-btn--outline ai-btn--xs" data-nav-leave="1">Setări tokeni API →</a></p>';
      }
      if (section.api_providers && section.api_providers.length) {
        html += '<p class="ai-sup-section-label">Buget API (sincron Setări)</p>';
        html += renderTokenTable(section.api_providers, [
          { key: 'label', label: 'Provider' },
          { key: 'month_requests', label: 'Cereri luna', fmt: 'num' },
          { key: 'max_requests', label: 'Max cereri', fmt: 'num' },
          { key: 'requests_left', label: 'Rămase', fmt: 'num' },
          { key: 'tokens_per_request', label: 'Credite/cerere', fmt: 'num' },
        ]);
      }
    }

    if (id === 'suppliers') {
      if (section.metrics && section.metrics.length) {
        html += renderMetrics(section.metrics);
      }
      if (section.summary && section.summary.length) {
        html += renderSummaryList(section.summary);
      }
      if (section.files_new && section.files_new.length) {
        html += renderTokenTable(section.files_new, [
          { key: 'supplier', label: 'Furnizor' },
          { key: 'new_files', label: 'Fișiere noi', fmt: 'num' },
        ]);
      }
    }

    if (id === 'conversations') {
      if (section.webhook) {
        var w = section.webhook;
        html += '<div class="ai-sup-metrics">';
        html += '<div class="ai-sup-metric ' + (w.exists ? 'ai-sup-metric--ok' : 'ai-sup-metric--warn') + '"><span class="ai-sup-metric__val">' + (w.exists ? 'Activ' : 'Lipsă') + '</span><span class="ai-sup-metric__lbl">Webhook</span></div>';
        html += '<div class="ai-sup-metric"><span class="ai-sup-metric__val">' + (w.lines_analyzed || 0) + '</span><span class="ai-sup-metric__lbl">Linii analizate</span></div>';
        html += '<div class="ai-sup-metric"><span class="ai-sup-metric__val">' + (w.size_kb || 0) + ' KB</span><span class="ai-sup-metric__lbl">Log</span></div></div>';
      }
      if (section.bots && section.bots.length) {
        html += '<div class="ai-sup-bots">' + section.bots.map(function (b) {
          var ch = String(b.channel || '?').charAt(0).toUpperCase();
          return '<div class="ai-sup-bot"><div class="ai-sup-bot__avatar">' + ch + '</div><div><div class="ai-sup-bot__name">' + esc(b.name) + '</div><div class="ai-sup-bot__meta">' + esc(b.channel) + ' → ' + esc(b.agent) + '</div></div></div>';
        }).join('') + '</div>';
      }
      if (section.sessions && section.sessions.length) {
        html += renderTokenTable(section.sessions.slice(0, 8), [
          { key: 'phone', label: 'Contact' },
          { key: 'messages', label: 'Mesaje', fmt: 'num' },
          { key: 'file', label: 'Sesiune' },
        ]);
      }
      if (section.recent_issues && section.recent_issues.length) {
        html += '<div class="ai-sup-errors">' + section.recent_issues.slice(0, 5).map(function (m) {
          return '<div class="ai-sup-error">' + esc(m.text || '') + (m.from ? ' <small>(' + esc(m.from) + ')</small>' : '') + '</div>';
        }).join('') + '</div>';
      }
      if (section.recent_positive && section.recent_positive.length) {
        html += '<div class="ai-sup-recs">' + section.recent_positive.slice(0, 4).map(function (m) {
          return '<span class="ai-sup-rec" style="background:#ecfdf5;border-color:#bbf7d0">✓ ' + esc(m.text || '') + '</span>';
        }).join('') + '</div>';
      }
      if (section.links && section.links.length) {
        html += '<div class="ai-btn-row" style="margin-top:10px">';
        section.links.forEach(function (lnk) {
          html += '<a href="' + esc(lnk.url) + '" class="ai-btn ai-btn--ghost ai-btn--xs" data-nav-leave="1">' + esc(lnk.label) + '</a>';
        });
        html += '</div>';
      }
    }

    if (id !== 'cycle' && id !== 'catalog' && id !== 'pipeline' && id !== 'tokens' && id !== 'suppliers' && id !== 'conversations') {
      html += renderMetricsFromSummary(section.summary, id);
    }

    html += renderRecs(section.recommendations);

    if (section.generated_at) {
      html += '<p class="ai-sup-tile__meta" style="margin-top:10px">Generat: ' + esc(section.generated_at) + '</p>';
    }

    html += '</div></article>';
    return html;
  }

  function bindCardToggles() {
    root.querySelectorAll('[data-sup-card]').forEach(function (card) {
      var head = card.querySelector('.ai-sup-card__head');
      if (!head || head._bound) return;
      head._bound = true;
      function toggle() {
        var open = card.classList.toggle('is-open');
        head.setAttribute('aria-expanded', open ? 'true' : 'false');
      }
      head.addEventListener('click', toggle);
      head.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); toggle(); }
      });
    });
  }

  function renderDeepReport(deep) {
    var wrap = document.getElementById('ai-sup-deep-sections');
    var atEl = document.getElementById('ai-sup-deep-at');
    if (!wrap) return;
    if (!deep || typeof deep !== 'object') {
      wrap.innerHTML = '<div class="ai-sup-empty">Raport indisponibil — rulează un ciclu.</div>';
      return;
    }
    if (atEl) atEl.textContent = fmtTime(deep.generated_at);
    var sections = [
      ['cycle', 'Ciclu cron', deep.cycle],
      ['catalog', 'Catalog produse', deep.catalog],
      ['pipeline', 'Pipeline health', deep.pipeline],
      ['tokens', 'Tokeni LLM', deep.tokens],
      ['suppliers', 'Furnizori', deep.suppliers],
      ['conversations', 'Conversații', deep.conversations],
    ];
    wrap.innerHTML = sections.map(function (s) {
      return renderDeepSection(s[0], s[1], s[2]);
    }).join('');
    bindCardToggles();
  }

  function setBar(id, pct) {
    var el = document.getElementById(id);
    if (el) el.style.width = Math.max(0, Math.min(100, pct)) + '%';
  }

  function setPctText(id, pct) {
    var el = document.getElementById(id);
    if (el) el.textContent = Math.round(Math.max(0, Math.min(100, pct))) + '%';
  }

  function setTileBadge(id, text, level) {
    var el = document.getElementById(id);
    if (!el) return;
    el.textContent = text;
    el.className = 'ai-sup-tile__status ai-sup-tile__status--' + (level || 'neutral');
  }

  function setTilePhaseStatus(phase, level) {
    var tile = document.querySelector('.ai-sup-tile[data-phase="' + phase + '"]');
    if (!tile) return;
    tile.classList.remove('ai-sup-tile--status-ok', 'ai-sup-tile--status-warn', 'ai-sup-tile--status-danger', 'ai-sup-tile--status-neutral');
    tile.classList.add('ai-sup-tile--status-' + (level || 'neutral'));
  }

  function detailCell(label, value, warn) {
    return '<div class="ai-sup-tile__detail' + (warn ? ' is-warn' : '') + '">'
      + '<dt>' + esc(label) + '</dt><dd>' + esc(String(value)) + '</dd></div>';
  }

  function fillDetails(id, cells) {
    var el = document.getElementById(id);
    if (el) el.innerHTML = cells.join('');
  }

  function enrichAnalyticsTiles(data) {
    data = data || {};
    var pulse = data.live_pulse || {};
    var syncEl = document.getElementById('ai-sup-analytics-sync');
    if (syncEl) {
      var at = data.generated_at || pulse.generated_at || (data.deep_report || {}).generated_at;
      syncEl.textContent = at ? ('Actualizat: ' + fmtTime(at)) : 'Date live la încărcare';
    }

    var cycle = data.last_cycle || {};
    var cyclePct = cycle.ok ? 100 : (cycle.skipped ? 0 : 15);
    setPctText('ai-sup-cycle-progress-pct', cyclePct);
    if (cycle.ok) {
      setTileBadge('ai-sup-tile-cycle-badge', 'ACTIV', 'ok');
      setTilePhaseStatus('cycle', 'ok');
    } else if (cycle.skipped) {
      setTileBadge('ai-sup-tile-cycle-badge', 'OPRIT', 'neutral');
      setTilePhaseStatus('cycle', 'neutral');
    } else {
      setTileBadge('ai-sup-tile-cycle-badge', 'AȘTEAPTĂ', 'warn');
      setTilePhaseStatus('cycle', 'warn');
    }
    var deepCycle = (data.deep_report || {}).cycle || {};
    fillDetails('ai-sup-cycle-details', [
      detailCell('Durată', Math.round((cycle.duration_ms || deepCycle.duration_ms || 0) / 1000) + ' sec'),
      detailCell('Vârstă', pulse.last_cycle_age_minutes != null ? pulse.last_cycle_age_minutes + ' min' : '—'),
      detailCell('Mod', pulse.api_on_demand ? 'La cerere' : (pulse.ollama && pulse.ollama.ready ? 'Ollama local' : 'Cron'))
    ]);

    var cat = data.catalog_audit || {};
    var counts = cat.counts || {};
    var score = cat.score != null ? cat.score : null;
    setPctText('ai-sup-catalog-progress-pct', score != null ? score : 0);
    if (score == null) {
      setTileBadge('ai-sup-tile-catalog-badge', 'NERULAT', 'neutral');
      setTilePhaseStatus('catalog', 'neutral');
    } else if (score >= 80) {
      setTileBadge('ai-sup-tile-catalog-badge', 'BUN', 'ok');
      setTilePhaseStatus('catalog', 'ok');
    } else if (score >= 60) {
      setTileBadge('ai-sup-tile-catalog-badge', 'MEDIU', 'warn');
      setTilePhaseStatus('catalog', 'warn');
    } else {
      setTileBadge('ai-sup-tile-catalog-badge', 'SLAB', 'danger');
      setTilePhaseStatus('catalog', 'danger');
    }
    fillDetails('ai-sup-catalog-details', [
      detailCell('Produse active', (counts.active_total || 0).toLocaleString('ro-RO')),
      detailCell('Fără imagine', counts.no_image || 0, (counts.no_image || 0) > 0),
      detailCell('Fără categorie', counts.no_category || 0, (counts.no_category || 0) > 0)
    ]);
    var catSub = document.getElementById('ai-sup-catalog-btn-sub');
    if (catSub) catSub.textContent = (data.config && data.config.catalog_scan_limit) ? ('Sample ' + data.config.catalog_scan_limit + ' produse') : 'Audit calitate';

    var pipe = data.pipeline_health || {};
    var pipePct = pipe.total ? Math.round((pipe.passed / pipe.total) * 100) : 0;
    setPctText('ai-sup-pipeline-progress-pct', pipePct);
    if (!pipe.total) {
      setTileBadge('ai-sup-tile-pipeline-badge', '—', 'neutral');
      setTilePhaseStatus('pipeline', 'neutral');
    } else if (pipePct >= 90) {
      setTileBadge('ai-sup-tile-pipeline-badge', 'STABIL', 'ok');
      setTilePhaseStatus('pipeline', 'ok');
    } else if (pipePct >= 80) {
      setTileBadge('ai-sup-tile-pipeline-badge', 'ATENȚIE', 'warn');
      setTilePhaseStatus('pipeline', 'warn');
    } else {
      setTileBadge('ai-sup-tile-pipeline-badge', 'EȘECURI', 'danger');
      setTilePhaseStatus('pipeline', 'danger');
    }
    fillDetails('ai-sup-pipeline-details', [
      detailCell('Trecute', (pipe.passed || 0) + ' / ' + (pipe.total || 0)),
      detailCell('Eșuate', pipe.failed || 0, (pipe.failed || 0) > 0),
      detailCell('Recomandări', ((pipe.recommendations || []).length) || 0)
    ]);

    var tok = data.token_budget || {};
    var keysSt = tok.keys_status || {};
    var keysN = keysSt.configured_count || 0;
    var hub = tok.api_hub || {};
    var usedPct = tok.used_pct || 0;
    if (usedPct <= 0 && hub.providers) {
      ['scrape_do', 'rapidapi_tecdoc', 'cursor'].forEach(function (k) {
        var u = hub.providers[k] ? hub.providers[k].usage : null;
        if (u && u.used_pct > usedPct) usedPct = u.used_pct;
      });
    }
    setPctText('ai-sup-tokens-progress-pct', usedPct);
    if (keysN === 0 && !hub.providers) {
      setTileBadge('ai-sup-tile-tokens-badge', 'NECONFIG', 'warn');
      setTilePhaseStatus('tokens', 'warn');
    } else if (usedPct > 90) {
      setTileBadge('ai-sup-tile-tokens-badge', 'CRITIC', 'danger');
      setTilePhaseStatus('tokens', 'danger');
    } else if (usedPct > 75) {
      setTileBadge('ai-sup-tile-tokens-badge', 'RIDICAT', 'warn');
      setTilePhaseStatus('tokens', 'warn');
    } else {
      setTileBadge('ai-sup-tile-tokens-badge', 'OK', 'ok');
      setTilePhaseStatus('tokens', 'ok');
    }
    var scrapeU = hub.providers && hub.providers.scrape_do ? hub.providers.scrape_do.usage : null;
    var rapidU = hub.providers && hub.providers.rapidapi_tecdoc ? hub.providers.rapidapi_tecdoc.usage : null;
    var scrapeLeft = scrapeU ? Math.max(0, scrapeU.requests_left || 0) : '—';
    var rapidLeft = rapidU ? Math.max(0, rapidU.requests_left || 0) : '—';
    fillDetails('ai-sup-tokens-details', [
      detailCell('Tokeni azi', (tok.tokens_today || 0).toLocaleString('ro-RO')),
      detailCell('Scrape rămase', scrapeLeft),
      detailCell('RapidAPI rămase', rapidLeft)
    ]);
    var tokSub = document.getElementById('ai-sup-tokens-btn-sub');
    if (tokSub) tokSub.textContent = keysN ? (keysN + ' chei configurate') : 'Deschide Setări';

    var sup = data.supplier_watch || {};
    var fn = (sup.files_new || []).length;
    var na = (sup.needs_attention || []).length;
    var supTotal = (sup.suppliers_watched || sup.supplier_count || (fn + na)) || 0;
    setPctText('ai-sup-suppliers-progress-pct', supTotal ? Math.round(((supTotal - na) / Math.max(supTotal, 1)) * 100) : 100);
    if (na > 0) {
      setTileBadge('ai-sup-tile-suppliers-badge', na + ' ATENȚIE', 'warn');
      setTilePhaseStatus('suppliers', 'warn');
    } else if (fn > 0) {
      setTileBadge('ai-sup-tile-suppliers-badge', fn + ' NOI', 'ok');
      setTilePhaseStatus('suppliers', 'ok');
    } else {
      setTileBadge('ai-sup-tile-suppliers-badge', 'CURAT', 'ok');
      setTilePhaseStatus('suppliers', 'ok');
    }
    fillDetails('ai-sup-suppliers-details', [
      detailCell('Fișiere noi', fn),
      detailCell('Necesită acțiune', na, na > 0),
      detailCell('Ultimul scan', sup.generated_at ? fmtTime(sup.generated_at).slice(0, 16) : '—')
    ]);

    var conv = data.conversations || {};
    var issues = conv.issues_detected || 0;
    var pos = conv.positive_signals || 0;
    var convTotal = issues + pos;
    setPctText('ai-sup-chat-progress-pct', convTotal ? Math.round((pos / convTotal) * 100) : 0);
    if (issues > 0) {
      setTileBadge('ai-sup-tile-chat-badge', issues + ' PROBLEME', 'warn');
      setTilePhaseStatus('chat', 'warn');
    } else if (pos > 0) {
      setTileBadge('ai-sup-tile-chat-badge', 'POZITIV', 'ok');
      setTilePhaseStatus('chat', 'ok');
    } else {
      setTileBadge('ai-sup-tile-chat-badge', 'FĂRĂ DATE', 'neutral');
      setTilePhaseStatus('chat', 'neutral');
    }
    fillDetails('ai-sup-chat-details', [
      detailCell('Probleme', issues, issues > 0),
      detailCell('Semnale +', pos),
      detailCell('Analizate', conv.analyzed_count || convTotal || 0)
    ]);
  }

  function enrichComposerTiles(composer, querySummary) {
    composer = composer || {};
    querySummary = querySummary || {};
    var stats = composer.stats || {};
    var repaired = stats.repaired || 0;
    var analyzed = stats.analyzed || 0;
    var repairPct = analyzed > 0 ? Math.round((repaired / analyzed) * 100) : (composer.configured ? 20 : 0);
    setPctText('ai-sup-composer-progress-pct', repairPct);
    if (!composer.configured) {
      setTileBadge('ai-sup-tile-composer-badge', 'FĂRĂ CHEIE', 'warn');
      setTilePhaseStatus('composer', 'warn');
    } else if (repaired > 0) {
      setTileBadge('ai-sup-tile-composer-badge', 'REPARAT', 'ok');
      setTilePhaseStatus('composer', 'ok');
    } else if (analyzed > 0) {
      setTileBadge('ai-sup-tile-composer-badge', 'ANALIZAT', 'neutral');
      setTilePhaseStatus('composer', 'neutral');
    } else {
      setTileBadge('ai-sup-tile-composer-badge', 'PREGĂTIT', 'ok');
      setTilePhaseStatus('composer', 'ok');
    }
    fillDetails('ai-sup-composer-details', [
      detailCell('Analizate', analyzed),
      detailCell('Reparate', repaired),
      detailCell('Model', composer.model || 'composer-2.5')
    ]);
    var compSub = document.getElementById('ai-sup-composer-btn-sub');
    if (compSub) compSub.textContent = composer.auto_execute ? 'Auto-repair ON' : 'Doar analiză';

    var ok = querySummary.ok || 0;
    var partial = querySummary.partial || 0;
    var fail = querySummary.fail || 0;
    var total = querySummary.total || (ok + partial + fail);
    var okPct = total ? Math.round((ok / total) * 100) : 0;
    setPctText('ai-sup-composer-chat-progress-pct', okPct);
    if (fail > 0) {
      setTileBadge('ai-sup-tile-composer-chat-badge', fail + ' NEFĂCUT', 'warn');
      setTilePhaseStatus('composer_chat', 'warn');
    } else if (ok > 0) {
      setTileBadge('ai-sup-tile-composer-chat-badge', 'OK', 'ok');
      setTilePhaseStatus('composer_chat', 'ok');
    } else {
      setTileBadge('ai-sup-tile-composer-chat-badge', 'GOL', 'neutral');
      setTilePhaseStatus('composer_chat', 'neutral');
    }
    fillDetails('ai-sup-composer-chat-details', [
      detailCell('Făcut', ok),
      detailCell('Parțial', partial, partial > 0),
      detailCell('Nefăcut', fail, fail > 0)
    ]);
    var chatSub = document.getElementById('ai-sup-composer-chat-btn-sub');
    if (chatSub) chatSub.textContent = total ? (total + ' mesaje · 7 zile') : 'Scrie în widget';
  }

  function fillConfig(c) {
    if (!c) return;
    var form = document.getElementById('ai-sup-config-form');
    if (!form) return;
    ['enabled', 'phase1_admin_hooks', 'phase2_catalog_scan', 'phase3_pipeline_health',
      'phase3_token_budget', 'phase3_supplier_watch', 'phase4_diagnostics',
      'phase4_conversations', 'phase4_daily_report', 'diagnostics_use_llm',
      'phase5_composer_repair', 'composer_repair_use_llm', 'composer_repair_auto_execute'].forEach(function (k) {
      var inp = form.querySelector('[name="' + k + '"]');
      if (inp) inp.checked = !!c[k];
    });
    var tl = form.querySelector('[name="token_daily_limit"]');
    if (tl && c.token_daily_limit) tl.value = c.token_daily_limit;
    var cl = form.querySelector('[name="catalog_scan_limit"]');
    if (cl && c.catalog_scan_limit) cl.value = c.catalog_scan_limit;
  }

  function outcomeClass(outcome) {
    var o = String(outcome || '');
    if (o === 'repaired' || o === 'partial') return 'ok';
    if (o === 'failed') return 'warn';
    return 'neutral';
  }

  function renderComposerRepair(composer) {
    composer = composer || {};
    var stats = composer.stats || {};
    var elTile = document.getElementById('ai-sup-composer');
    if (elTile) {
      var repaired = stats.repaired || 0;
      var analyzed = stats.analyzed || 0;
      if (composer.last_run) {
        elTile.textContent = repaired > 0 ? (repaired + ' reparate') : (analyzed + ' analizate');
        elTile.className = 'ai-sup-tile__value' + (repaired > 0 ? ' is-ok' : (analyzed > 0 ? '' : ' is-warn'));
      } else {
        elTile.textContent = composer.configured ? 'Pregătit' : 'Fără cheie';
        elTile.className = 'ai-sup-tile__value' + (composer.configured ? ' is-ok' : ' is-warn');
      }
    }
    setBar('ai-sup-composer-bar', stats.repaired > 0 && stats.analyzed > 0
      ? Math.round((stats.repaired / stats.analyzed) * 100)
      : (composer.configured ? 20 : 0));

    var summaryEl = document.getElementById('ai-sup-composer-summary');
    if (summaryEl) {
      summaryEl.textContent = composer.last_summary
        || ('Model ' + (composer.model || 'composer-2.5')
          + (composer.auto_execute ? ' · auto-repair ON' : ' · doar analiză')
          + (composer.llm_allowed ? '' : ' · LLM indisponibil'));
    }

    var list = document.getElementById('ai-sup-composer-items');
    if (!list) return;
    var items = composer.items || [];
    if (!items.length) {
      list.innerHTML = '<li class="ai-sup-empty" style="list-style:none">Nicio rulare recentă — apasă «Analizează & repară».</li>';
      return;
    }
    list.innerHTML = items.map(function (it) {
      var steps = (it.manual_steps || []).map(function (s) { return '<li>' + esc(s) + '</li>'; }).join('');
      var execBtn = '';
      if (it.outcome !== 'repaired' && it.should_auto_fix && it.fix_action) {
        execBtn = '<button type="button" class="ai-btn ai-btn--fix ai-btn--xs ai-composer-exec" data-code="'
          + esc(it.code) + '">Execută fix</button>';
      }
      return '<li class="ai-sup-diag-item ai-sup-diag-item--' + esc(it.level || 'warning') + ' ai-composer-item--' + outcomeClass(it.outcome) + '">'
        + '<div class="ai-sup-diag-item__head"><strong>' + esc(it.title || it.code) + '</strong>'
        + '<span class="ai-sup-badge ai-sup-badge--' + outcomeClass(it.outcome) + '">' + esc(it.outcome || 'pending') + '</span>'
        + (execBtn ? '<span class="ai-sup-diag-item__actions">' + execBtn + '</span>' : '')
        + '</div><p class="ai-sup-meta">' + esc(it.analysis_ro || it.root_cause || it.detail || '') + '</p>'
        + (it.execute_message ? '<p class="ai-sup-meta ai-sup-meta--exec">' + esc(it.execute_message) + '</p>' : '')
        + (steps ? '<ol class="ai-sup-steps">' + steps + '</ol>' : '') + '</li>';
    }).join('');
  }

  function composerStatusFromData(data) {
    data = data || {};
    var cr = data.composer_repair || {};
    var tok = data.token_budget || {};
    var keys = tok.keys_status || {};
    var llmOk = false;
    (keys.items || []).forEach(function (k) {
      if ((k.id === 'groq' || k.key === 'GROQ_KEY' || k.id === 'openai' || k.key === 'OPENAI_KEY') && k.configured) llmOk = true;
    });
    var cfg = data.config || {};
    return {
      model: cr.model || 'composer-2.5',
      configured: llmOk,
      auto_execute: !!cfg.composer_repair_auto_execute,
      llm_allowed: !!cr.llm_used || llmOk,
      last_run: cr.generated_at || null,
      last_summary: cr.summary || '',
      items: cr.items || [],
      stats: cr.stats || {},
    };
  }

  function bindComposerExec() {
    root.querySelectorAll('.ai-composer-exec').forEach(function (btn) {
      if (btn._bound) return;
      btn._bound = true;
      btn.addEventListener('click', function () {
        var code = btn.getAttribute('data-code') || '';
        if (!code) return;
        btn.disabled = true;
        post({ action: 'composer_repair_execute', code: code })
          .then(function (j) {
            toast(j.message || 'Executat.');
            return loadStatus();
          })
          .catch(function (e) { toast(e.message, true); })
          .finally(function () { btn.disabled = false; });
      });
    });
  }

  function metroBadgeClass(level) {
    if (level === 'critical') return 'is-critical';
    if (level === 'warning') return 'is-warning';
    return 'is-ok';
  }

  function renderMetroSupervisor(data) {
    data = data || {};
    var metro = data.metro_llm || {};
    var dash = document.getElementById('ai-sup-metro-dashboard');
    if (!dash) return;

    var eco = metro.ecosystem || {};
    var usage = metro.usage || {};
    var today = usage.today || {};
    var month = usage.month || {};
    var cron = metro.cron || {};
    var ollama = metro.ollama || {};
    var cfg = metro.config || {};
    var routes = metro.route_log || [];
    var matrix = metro.task_matrix || [];
    var totalToday = (today.ollama || 0) + (today.cursor || 0);
    var ollamaShare = totalToday > 0 ? Math.round((today.ollama / totalToday) * 100) : 100;

    var routeRows = routes.length ? routes.map(function (r) {
      var via = r.routed_via || r.provider || '—';
      var viaCls = via.indexOf('ollama') >= 0 ? 'ollama' : (via.indexOf('cursor') >= 0 ? 'cursor' : 'other');
      return '<tr class="' + (r.ok ? '' : 'is-fail') + '">'
        + '<td><time>' + esc((r.ts || '').replace('T', ' ').slice(0, 19)) + '</time></td>'
        + '<td><span class="ai-metro-tag">' + esc(r.task || r.action || '—') + '</span></td>'
        + '<td><span class="ai-metro-via ai-metro-via--' + viaCls + '">' + esc(via) + '</span></td>'
        + '<td><span class="ai-metro-pill ai-metro-pill--' + (r.ok ? 'ok' : 'fail') + '">' + (r.ok ? 'OK' : 'Eșec') + '</span></td>'
        + '<td><code>' + esc((r.model || '—').slice(0, 28)) + '</code></td>'
        + '<td class="ai-metro-err">' + esc((r.error || '').slice(0, 40)) + '</td>'
        + '</tr>';
    }).join('') : '<tr><td colspan="6" class="ai-metro-table-empty">Niciun apel LLM logat — rulează Test Ollama sau un ciclu.</td></tr>';

    var matrixRows = matrix.slice(0, 5).map(function (m) {
      return '<tr><td>' + esc(m.label) + '</td><td><span class="ai-metro-profile ai-metro-profile--' + esc(m.profile_key || '') + '">' + esc(m.provider) + '</span></td><td>' + esc(m.trigger) + '</td></tr>';
    }).join('');

    dash.innerHTML = ''
      + '<div class="ai-metro-grid">'
      + '<article class="ai-metro-eco-card ai-metro-eco-card--' + metroBadgeClass(eco.level) + '">'
      + '<div class="ai-metro-eco-card__orb"><span></span><span></span><span></span></div>'
      + '<div class="ai-metro-eco-card__body">'
      + '<span class="ai-metro-eco-card__label">Stare ecosistem</span>'
      + '<strong class="ai-metro-eco-card__title">' + esc(eco.label || '—') + '</strong>'
      + '<p class="ai-metro-eco-card__reasons">' + esc((eco.reasons || []).join(' · ') || 'Totul în regulă') + '</p>'
      + '<div class="ai-metro-eco-card__chips">'
      + '<span class="ai-metro-chip' + (ollama.ready ? ' is-on' : '') + '">Ollama ' + (ollama.ready ? esc(ollama.model || 'OK') : 'oprit') + '</span>'
      + '<span class="ai-metro-chip' + (cron.local_cycle_allowed ? ' is-on' : '') + '">Ciclu local ' + (cron.local_cycle_allowed ? 'activ' : '—') + '</span>'
      + '<span class="ai-metro-chip">Mod ' + esc(cfg.llm_router_mode || 'ollama_first') + '</span>'
      + '</div></div></article>'

      + '<article class="ai-metro-stat-card ai-metro-stat-card--ollama">'
      + '<span class="ai-metro-stat-card__ico">◉</span>'
      + '<span class="ai-metro-stat-card__lbl">Ollama azi</span>'
      + '<strong class="ai-metro-stat-card__val">' + (today.ollama || 0).toLocaleString('ro-RO') + '</strong>'
      + '<span class="ai-metro-stat-card__sub">Lună: ' + (month.ollama || 0).toLocaleString('ro-RO') + ' · 0 RON</span>'
      + '<div class="ai-metro-stat-card__bar"><div style="width:' + ollamaShare + '%"></div></div>'
      + '</article>'

      + '<article class="ai-metro-stat-card ai-metro-stat-card--cursor">'
      + '<span class="ai-metro-stat-card__ico">✦</span>'
      + '<span class="ai-metro-stat-card__lbl">Cursor azi</span>'
      + '<strong class="ai-metro-stat-card__val">' + (today.cursor || 0).toLocaleString('ro-RO') + '</strong>'
      + '<span class="ai-metro-stat-card__sub">Lună: ' + (month.cursor || 0).toLocaleString('ro-RO') + ' · cloud</span>'
      + '<div class="ai-metro-stat-card__bar ai-metro-stat-card__bar--cursor"><div style="width:' + (100 - ollamaShare) + '%"></div></div>'
      + '</article>'

      + '<article class="ai-metro-stat-card ai-metro-stat-card--cycle">'
      + '<span class="ai-metro-stat-card__ico">⚡</span>'
      + '<span class="ai-metro-stat-card__lbl">Ultimul ciclu</span>'
      + '<strong class="ai-metro-stat-card__val">' + (cron.last_cycle_age_minutes != null ? cron.last_cycle_age_minutes + ' min' : '—') + '</strong>'
      + '<span class="ai-metro-stat-card__sub">' + (cron.jobs_last || 0) + ' joburi · ' + Math.round((cron.duration_ms || 0) / 1000) + ' sec</span>'
      + '<span class="ai-metro-pill ai-metro-pill--' + (cron.cron_blocked ? 'fail' : 'ok') + '">' + (cron.cron_blocked ? 'Blocat' : (cron.api_on_demand ? 'La cerere' : 'Cron activ')) + '</span>'
      + '</article>'
      + '</div>'

      + '<div class="ai-metro-panels">'
      + '<div class="ai-metro-panel">'
      + '<header class="ai-metro-panel__head"><h4>Jurnal rutare LLM</h4><span class="ai-metro-panel__count">' + routes.length + ' evenimente</span></header>'
      + '<div class="ai-metro-table-wrap"><table class="ai-metro-table"><thead><tr>'
      + '<th>Când</th><th>Task</th><th>Via</th><th>Status</th><th>Model</th><th>Detaliu</th>'
      + '</tr></thead><tbody>' + routeRows + '</tbody></table></div>'
      + '</div>'
      + '<div class="ai-metro-panel">'
      + '<header class="ai-metro-panel__head"><h4>Matrice LLM</h4><a href="' + esc(AI_URLS.settings_tokens || '/admin/settings?tab=tokens') + '" class="ai-metro-link" data-nav-leave="1">Vezi tot →</a></header>'
      + '<div class="ai-metro-table-wrap"><table class="ai-metro-table ai-metro-table--compact"><thead><tr>'
      + '<th>Funcție</th><th>Provider</th><th>Declanșare</th>'
      + '</tr></thead><tbody>' + (matrixRows || '<tr><td colspan="3" class="ai-metro-table-empty">—</td></tr>') + '</tbody></table></div>'
      + '</div></div>';

    if (metro.generated_at) {
      dash.setAttribute('data-synced', metro.generated_at);
    }
  }

  function renderLivePulse(pulse) {
    var banner = document.getElementById('ai-sup-live-banner');
    var eyebrow = document.getElementById('ai-sup-hero-eyebrow');
    if (!pulse || typeof pulse !== 'object') {
      if (banner) banner.hidden = true;
      return;
    }
    if (eyebrow) {
      if (pulse.ollama && pulse.ollama.ready) {
        eyebrow.textContent = 'Ollama local · ' + (pulse.ollama.model || 'LLM') + ' · metro Cursor';
      } else if (pulse.api_on_demand) {
        eyebrow.textContent = 'Mod la cerere · fundal oprit';
      } else if (pulse.cycle_stale) {
        eyebrow.textContent = 'Cron întârziat';
      } else {
        eyebrow.textContent = 'Motor fundal · cron 2–3 min';
      }
    }
    if (!banner) return;
    var ops = pulse.ops_alerts || {};
    var opsLine = '';
    if (ops.total > 0) {
      opsLine = ' <strong>' + ops.total + ' alerte</strong> (' + ops.critical + ' critice, ' + ops.warning + ' atenție).';
    }
    var ollama = pulse.ollama || {};
    if (ollama.ready) {
      opsLine += ' <strong>Ollama</strong>: ' + esc(ollama.model || 'local') + ' — 0 tokeni cloud.';
    } else if (ollama.enabled && !ollama.reachable) {
      opsLine += ' Ollama: pornire necesară (ollama serve).';
    }
    var ageLine = '';
    if (pulse.last_cycle_age_minutes != null && pulse.last_cycle_at) {
      ageLine = ' Ultimul ciclu: acum ' + pulse.last_cycle_age_minutes + ' min.';
    }
    banner.textContent = '';
    banner.innerHTML = '<div class="ai-sup-banner__inner">'
      + '<span class="ai-sup-banner__icon" aria-hidden="true">●</span>'
      + '<div class="ai-sup-banner__text"><strong>Status live</strong><p>' + esc(pulse.message || '') + ageLine + opsLine + '</p></div>'
      + '</div>';
    banner.className = 'ai-sup-v2__live-banner'
      + (pulse.api_on_demand ? ' is-paused' : '')
      + (!pulse.api_on_demand && pulse.cycle_stale ? ' is-stale' : '');
    banner.hidden = false;
  }

  function formatApiHubSummary(hub) {
    if (!hub || !hub.providers) return '';
    var parts = [];
    ['scrape_do', 'rapidapi_tecdoc', 'cursor'].forEach(function (key) {
      var p = hub.providers[key];
      if (!p) return;
      var u = p.usage || {};
      var left = u.requests_left != null ? u.requests_left : (u.remaining_tokens || 0);
      var short = key === 'rapidapi_tecdoc' ? 'RapidAPI' : (key === 'scrape_do' ? 'Scrape' : 'Cursor');
      parts.push('~' + Number(left).toLocaleString('ro-RO') + ' ' + short);
    });
    return parts.join(' · ');
  }

  var lastComposerQuerySummary = {};
  var lastSupervisorData = null;

  function renderStatus(data) {
    lastSupervisorData = data;
    data = data || {};
    renderLivePulse(data.live_pulse || null);
    renderMetroSupervisor(data);

    var cycle = data.last_cycle || {};
    var elCycle = document.getElementById('ai-sup-cycle-status');
    if (elCycle) {
      var cycleOk = !!cycle.ok;
      elCycle.textContent = cycleOk ? 'OK · ' + Math.round((cycle.duration_ms || 0) / 1000) + ' sec' : (cycle.skipped ? 'Dezactivat' : 'Așteaptă');
      elCycle.className = 'ai-sup-tile__value' + (cycleOk ? ' is-ok' : (cycle.skipped ? '' : ' is-warn'));
    }
    setBar('ai-sup-cycle-bar', cycle.ok ? 100 : (cycle.skipped ? 0 : 15));

    var elLast = document.getElementById('ai-sup-last-cycle');
    if (elLast) {
      var pulse = data.live_pulse || {};
      var deepCycle = (data.deep_report || {}).cycle || {};
      if (pulse.api_on_demand && pulse.last_cycle_at) {
        elLast.textContent = 'Cache: ' + fmtTime(pulse.last_cycle_at) + ' — apasă «Rulează ciclu» pentru date proaspete';
      } else if (deepCycle.started_at) {
        elLast.textContent = 'Ultimul: ' + deepCycle.started_at;
      } else if (cycle.started_at) {
        elLast.textContent = 'Ultimul: ' + fmtTime(cycle.started_at);
      } else {
        elLast.textContent = pulse.cron_expected ? 'Cron la 2–3 min' : 'Fundal oprit — rulare manuală';
      }
    }

    var cat = data.catalog_audit || {};
    var elCat = document.getElementById('ai-sup-catalog');
    if (elCat) {
      var score = cat.score != null ? cat.score : null;
      elCat.textContent = score != null ? score + '/100' : 'Nerulat';
      elCat.className = 'ai-sup-tile__value' + (score >= 80 ? ' is-ok' : (score != null && score < 60 ? ' is-warn' : ''));
    }
    setBar('ai-sup-catalog-bar', cat.score != null ? cat.score : 0);

    var pipe = data.pipeline_health || {};
    var elPipe = document.getElementById('ai-sup-pipeline');
    if (elPipe) {
      var pct = pipe.total ? Math.round((pipe.passed / pipe.total) * 100) : 0;
      elPipe.textContent = pipe.total ? pipe.passed + '/' + pipe.total + ' OK' : '—';
      elPipe.className = 'ai-sup-tile__value' + (pct >= 90 ? ' is-ok' : (pipe.total && pct < 80 ? ' is-warn' : ''));
    }
    setBar('ai-sup-pipeline-bar', pipe.total ? (pipe.passed / pipe.total) * 100 : 0);

    var tok = data.token_budget || {};
    var keysSt = tok.keys_status || {};
    var elTok = document.getElementById('ai-sup-tokens');
    if (elTok) {
      var keysN = keysSt.configured_count || 0;
      var hubSummary = formatApiHubSummary(tok.api_hub);
      if (tok.tokens_today > 0) {
        elTok.textContent = tok.tokens_today.toLocaleString('ro-RO') + ' tok azi';
      } else if (hubSummary) {
        elTok.textContent = hubSummary;
      } else if (keysN > 0) {
        elTok.textContent = keysN + ' chei · 0 consum azi';
      } else {
        elTok.textContent = 'Configurează chei';
      }
      var usedPct = tok.used_pct || 0;
      if (hubSummary && usedPct <= 0) {
        var hub = tok.api_hub || {};
        var maxPct = 0;
        ['scrape_do', 'rapidapi_tecdoc', 'cursor'].forEach(function (k) {
          var u = hub.providers && hub.providers[k] ? hub.providers[k].usage : null;
          if (u && u.used_pct > maxPct) maxPct = u.used_pct;
        });
        usedPct = maxPct;
      }
      elTok.className = 'ai-sup-tile__value' + (usedPct > 80 ? ' is-warn' : (keysN > 0 || hubSummary ? ' is-ok' : ' is-warn'));
      setBar('ai-sup-tokens-bar', usedPct);
    } else {
      setBar('ai-sup-tokens-bar', tok.used_pct || 0);
    }

    if (window.BesoiuRenderApiTokenHub) {
      window.BesoiuRenderApiTokenHub(tok.api_hub || null, 'ai-sup-api-tokens-grid');
    }

    var sup = data.supplier_watch || {};
    var elSup = document.getElementById('ai-sup-suppliers');
    if (elSup) {
      var fn = (sup.files_new || []).length;
      var na = (sup.needs_attention || []).length;
      elSup.textContent = fn + ' noi · ' + na + ' atenție';
    }

    var conv = data.conversations || {};
    var elConv = document.getElementById('ai-sup-conversations');
    var issues = conv.issues_detected || 0;
    var pos = conv.positive_signals || 0;
    if (elConv) {
      elConv.textContent = issues + ' prob · ' + pos + ' poz';
      elConv.className = 'ai-sup-tile__value' + (issues > 0 ? ' is-warn' : (pos > 0 ? ' is-ok' : ''));
    }
    var total = issues + pos || 1;
    setBar('ai-sup-chat-warn', (issues / total) * 100);
    setBar('ai-sup-chat-ok', (pos / total) * 100);

    var diag = data.diagnostics || {};
    var list = document.getElementById('ai-sup-diagnostics');
    if (list) {
      var items = diag.diagnostics || [];
      var pulseOps = (data.live_pulse || {}).ops_alerts || {};
      var fixEnc = window.BesoiuAlertFix && window.BesoiuAlertFix.encodePayload;
      if (!items.length) {
        var okMsg = pulseOps.total > 0
          ? 'Se încarcă diagnosticele… (' + pulseOps.total + ' alerte active în sistem)'
          : 'Nicio alertă — sistem OK.';
        list.innerHTML = '<li class="ai-sup-empty" style="list-style:none">' + esc(okMsg) + '</li>';
      } else {
        list.innerHTML = items.map(function (d) {
          var steps = (d.steps || []).map(function (s) { return '<li>' + esc(s) + '</li>'; }).join('');
          var fixBtn = '';
          if (d.fixable && fixEnc) {
            fixBtn = '<button type="button" class="ai-btn ai-btn--fix ai-btn--xs" data-alert-fix="'
              + fixEnc(d) + '">' + esc(d.fix_label || 'Corectează') + '</button>';
          } else if (d.url) {
            fixBtn = '<a href="' + esc(d.url) + '" class="ai-btn ai-btn--ghost ai-btn--xs" data-nav-leave="1">Detalii →</a>';
          }
          return '<li class="ai-sup-diag-item ai-sup-diag-item--' + esc(d.level || 'warning') + '">'
            + '<div class="ai-sup-diag-item__head"><strong>' + esc(d.title) + '</strong>'
            + (fixBtn ? '<span class="ai-sup-diag-item__actions">' + fixBtn + '</span>' : '')
            + '</div><p class="ai-sup-meta">' + esc(d.problem || d.detail || '') + '</p>'
            + (steps ? '<ol class="ai-sup-steps">' + steps + '</ol>' : '') + '</li>';
        }).join('');
      }
    }

    var daily = data.daily_report || {};
    var pre = document.getElementById('ai-sup-daily-report');
    if (pre) pre.textContent = daily.markdown || 'Raport zilnic — se generează automat (cron).';

    fillConfig(data.config);
    enrichAnalyticsTiles(data);
    renderDeepReport(data.deep_report);
    renderComposerRepair(composerStatusFromData(data));
    enrichComposerTiles(composerStatusFromData(data), lastComposerQuerySummary);
    bindComposerExec();
  }

  function statusLabel(st) {
    if (st === 'ok') return { text: 'FACUT', cls: 'ok' };
    if (st === 'partial') return { text: 'PARTIAL', cls: 'partial' };
    return { text: 'NEFACUT', cls: 'fail' };
  }

  function renderComposerQueryLog(payload) {
    var listEl = document.getElementById('ai-sup-composer-log-list');
    var sumEl = document.getElementById('ai-sup-composer-log-summary');
    var kpiEl = document.getElementById('ai-sup-composer-queries-kpi');
    if (!listEl) return;

    var summary = (payload && payload.summary) || {};
    lastComposerQuerySummary = summary;
    var rows = (payload && payload.data) || [];
    var ok = summary.ok || 0;
    var partial = summary.partial || 0;
    var fail = summary.fail || 0;
    var total = summary.total || (ok + partial + fail);

    if (sumEl) {
      sumEl.textContent = total
        ? ok + ' făcut · ' + partial + ' parțial · ' + fail + ' nefăcut (7 zile)'
        : '0 mesaje în jurnal';
    }

    if (kpiEl) {
      kpiEl.textContent = total ? (ok + ' OK · ' + fail + ' ✗') : '0 mesaje';
      kpiEl.className = 'ai-sup-tile__value' + (fail > 0 ? ' is-warn' : (ok > 0 ? ' is-ok' : ''));
    }

    var denom = total || 1;
    setBar('ai-sup-composer-ok-bar', (ok / denom) * 100);
    setBar('ai-sup-composer-fail-bar', ((fail + partial) / denom) * 100);
    enrichComposerTiles(composerStatusFromData(lastSupervisorData || {}), summary);

    if (!rows.length) {
      listEl.innerHTML = '<li class="ai-sup-empty-inline">Niciun mesaj încă — scrie în widget-ul <strong>Composer</strong> (buton violet) de pe Lista produse, Import, Adaos, etc.</li>';
      return;
    }

    listEl.innerHTML = rows.slice(0, 25).map(function (row) {
      var fb = row.feedback || {};
      var st = fb.status || 'fail';
      var badge = statusLabel(st);
      var when = fmtTime(row.at);
      var msg = String(row.message || '').slice(0, 160);
      var section = row.section || '';
      var missing = (fb.missing_context || []).join(', ');
      var notDone = (fb.not_done || []).join('; ');
      var note = missing ? ('Lipsește: ' + missing) : (notDone ? ('Nu s-a făcut: ' + notDone) : (fb.outcome_label || ''));
      var rated = row.operator_rating ? (' · Tu: ' + row.operator_rating) : '';
      return '<li class="ai-sup-composer-log-item ai-sup-composer-log-item--' + badge.cls + '">'
        + '<span class="ai-sup-composer-log-item__status">' + esc(badge.text) + '</span>'
        + '<div class="ai-sup-composer-log-item__body">'
        + '<strong class="ai-sup-composer-log-item__msg">' + esc(msg) + '</strong>'
        + '<span class="ai-sup-composer-log-item__meta">' + esc(when) + ' · ' + esc(section) + esc(rated) + '</span>'
        + (note ? '<span class="ai-sup-composer-log-item__note">' + esc(note) + '</span>' : '')
        + '</div></li>';
    }).join('');
  }

  function loadComposerQueryLog() {
    return get({ action: 'section_assist_queries', limit: 30 }).then(function (q) {
      renderComposerQueryLog(q);
    }).catch(function () {
      renderComposerQueryLog(null);
    });
  }

  function loadStatus() {
    return get({ action: 'supervisor_status' }).then(function (j) {
      var data = j.data || j;
      renderStatus(data);
      if (window.aiCommandCenterSync) window.aiCommandCenterSync(data);
    }).then(function () {
      return loadComposerQueryLog();
    });
  }

  var SETTINGS_API = '/admin/api/settings_endpoint.php';

  function progress() {
    return window.BesoiuActionProgress;
  }

  function withProgress(jobFn) {
    var P = progress();
    if (!P) {
      return jobFn().then(function (r) { toast('Finalizat.'); return r; }).catch(function (e) { toast(e.message, true); throw e; });
    }
    return jobFn(P);
  }

  document.getElementById('ai-sup-metro-test-ollama')?.addEventListener('click', function () {
    withProgress(function (P) {
      return P.runMetroTest('test_metro_ollama', SETTINGS_API, function () { loadStatus(); });
    });
  });

  document.getElementById('ai-sup-metro-test-cursor')?.addEventListener('click', function () {
    withProgress(function (P) {
      return P.runMetroTest('test_metro_cloud', SETTINGS_API, function () { loadStatus(); });
    });
  });

  document.getElementById('ai-sup-metro-test-cycle')?.addEventListener('click', function () {
    withProgress(function (P) {
      return P.runMetroTest('test_metro_cycle', SETTINGS_API, function () { loadStatus(); });
    });
  });

  document.getElementById('ai-sup-composer-log-refresh')?.addEventListener('click', function () {
    withProgress(function (P) {
      return P.run({
        title: 'Reîncărcare jurnal Composer',
        steps: ['Citire mesaje', 'Calcul statistici', 'Actualizare listă'],
        task: function (update) {
          update(0, 'Se citește jurnalul…');
          return loadComposerQueryLog().then(function () {
            update(2, 'Gata.');
          });
        },
        successMessage: 'Jurnal Composer actualizat.',
      });
    });
  });

  document.getElementById('ai-sup-composer-log-scroll')?.addEventListener('click', function () {
    var el = document.getElementById('ai-sup-composer-log');
    if (el && el.scrollIntoView) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
  });

  document.getElementById('ai-sup-refresh')?.addEventListener('click', function () {
    withProgress(function (P) {
      return P.run({
        title: 'Reîncărcare supervizor',
        steps: ['Status live', 'Panou analitic', 'Metro LLM'],
        task: function (update) {
          update(0, 'Se încarcă datele…');
          return loadStatus().then(function () { update(2, 'Panou actualizat.'); });
        },
        successMessage: 'Date supervizor actualizate.',
      });
    });
  });

  document.getElementById('ai-sup-run-cycle')?.addEventListener('click', function () {
    withProgress(function (P) {
      return P.runSupervisorJob(post, 'supervisor_run_cycle', { force_all: true }, function () { loadStatus(); });
    });
  });

  root.querySelectorAll('.ai-sup-run-one').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var job = btn.getAttribute('data-job');
      if (!job) return;
      withProgress(function (P) {
        return P.runSupervisorJob(post, job, {}, function () { loadStatus(); });
      });
    });
  });

  document.getElementById('ai-sup-composer-run')?.addEventListener('click', function () {
    withProgress(function (P) {
      return P.runSupervisorJob(post, 'composer_repair_run', { auto_execute: true }, function () { loadStatus(); });
    });
  });

  document.getElementById('ai-sup-config-form')?.addEventListener('submit', function (ev) {
    ev.preventDefault();
    var form = ev.target;
    var body = { action: 'supervisor_config_set' };
    ['enabled', 'phase1_admin_hooks', 'phase2_catalog_scan', 'phase3_pipeline_health',
      'phase3_token_budget', 'phase3_supplier_watch', 'phase4_diagnostics',
      'phase4_conversations', 'phase4_daily_report', 'diagnostics_use_llm',
      'phase5_composer_repair', 'composer_repair_use_llm', 'composer_repair_auto_execute'].forEach(function (k) {
      var inp = form.querySelector('[name="' + k + '"]');
      body[k] = !!(inp && inp.checked);
    });
    body.token_daily_limit = parseInt(form.querySelector('[name="token_daily_limit"]').value, 10) || 500000;
    body.catalog_scan_limit = parseInt(form.querySelector('[name="catalog_scan_limit"]').value, 10) || 500;
    withProgress(function (P) {
      return P.run({
        title: 'Salvare configurare supervizor',
        steps: ['Validare opțiuni', 'Scriere setări', 'Reîncărcare panou'],
        task: function (update) {
          update(0, 'Se salvează…');
          return post(body).then(function () {
            update(2, 'Actualizare UI…');
            return loadStatus();
          });
        },
        successMessage: 'Config supervizor salvat.',
      });
    });
  });

  document.addEventListener('ai-agent-tab-supervizor', function () {
    loadStatus().catch(function () { /* silent */ });
  });

  window.aiSupervisorLoad = loadStatus;
})();
