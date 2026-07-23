/**
 * Tab Ollama — chat cu cei 4 agenți specializați.
 */
(function () {
  'use strict';

  var AGENT_META = {
    'agent-imagini': {
      icon: '🖼️',
      quick: ['Cum verific o imagine?', 'Ce înseamnă verdict mismatch?', 'Audit imagine produs'],
    },
    'agent-produse': {
      icon: '📦',
      quick: ['Câte produse active avem?', 'Ce categorii domină catalogul?', 'Explică randomn_id vs pCode'],
    },
    'agent-clienti': {
      icon: '👤',
      quick: ['Status comenzi recente', 'Ce date cer pentru identificare client?', 'Politica retur pe scurt'],
    },
    'agent-statistici': {
      icon: '📊',
      quick: ['Raport KPI acum', 'Produse fără imagine?', 'Import staging — ce e pending?'],
    },
  };

  var selectedSlug = 'agent-produse';
  var statusData = null;
  var busy = false;

  function cfg() {
    var el = document.getElementById('ai-agent-cfg');
    if (!el) return { api: '' };
    try {
      return JSON.parse(el.textContent || '{}');
    } catch (e) {
      return { api: '' };
    }
  }

  function api() {
    return cfg().api || (document.getElementById('ai-agent-root') || {}).getAttribute('data-api') || '';
  }

  function esc(s) {
    var d = document.createElement('div');
    d.textContent = s == null ? '' : String(s);
    return d.innerHTML;
  }

  function toast(msg) {
    var el = document.getElementById('ai-agent-toast');
    if (!el) return;
    el.textContent = msg;
    el.classList.remove('hidden');
    setTimeout(function () { el.classList.add('hidden'); }, 4200);
  }

  function apiGet(params) {
    var q = new URLSearchParams(params);
    return fetch(api() + '?' + q.toString(), { credentials: 'same-origin' }).then(function (r) {
      return r.json();
    });
  }

  function apiPost(body) {
    return fetch(api(), {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body),
    }).then(function (r) {
      return r.json();
    });
  }

  function renderStatus(data) {
    statusData = data || null;
    var el = document.getElementById('ai-ollama-status');
    if (!el || !data) return;

    var ollama = data.ollama || {};
    var vision = data.vision || {};
    var parts = [];
    parts.push(ollama.ready ? '✓ Ollama text: ' + esc(ollama.model || '') : '✗ Ollama text indisponibil');
    parts.push(vision.ready ? '✓ Vision: ' + esc(vision.model || '') : '✗ Vision indisponibil (ollama pull llava:7b)');
    el.innerHTML = parts.join(' · ');
    el.className = 'ai-ollama-status' + (ollama.ready || vision.ready ? ' is-ok' : ' is-warn');
  }

  function renderAgentCards(agents) {
    var wrap = document.getElementById('ai-ollama-agent-cards');
    if (!wrap) return;

    if (!agents || !agents.length) {
      wrap.innerHTML = '<p class="ai-empty-inline">Niciun agent Ollama instalat.</p>';
      return;
    }

    wrap.innerHTML = agents.map(function (a) {
      var meta = AGENT_META[a.slug] || { icon: '🤖', quick: [] };
      var active = a.slug === selectedSlug ? ' is-active' : '';
      var readyCls = a.ready ? ' is-ready' : ' is-off';
      return '<button type="button" class="ai-ollama-agent-card' + active + readyCls + '" data-ollama-slug="' + esc(a.slug) + '">'
        + '<span class="ai-ollama-agent-card__icon">' + meta.icon + '</span>'
        + '<span class="ai-ollama-agent-card__body">'
        + '<strong>' + esc(a.name || a.slug) + '</strong>'
        + '<small>' + esc(a.model || '') + '</small>'
        + '<span class="ai-ollama-agent-card__pill">' + (a.ready ? 'READY' : 'OFF') + '</span>'
        + '</span></button>';
    }).join('');
  }

  function updateChatChrome() {
    var agents = (statusData && statusData.agents) || [];
    var current = agents.find(function (a) { return a.slug === selectedSlug; }) || {};
    var meta = AGENT_META[selectedSlug] || { icon: '🤖', quick: [] };

    var title = document.getElementById('ai-ollama-chat-title');
    var model = document.getElementById('ai-ollama-chat-model');
    if (title) title.textContent = (meta.icon || '') + ' ' + (current.name || selectedSlug);
    if (model) model.textContent = current.model || '—';

    var quick = document.getElementById('ai-ollama-quick');
    var sendBtn = document.getElementById('ai-ollama-send');
    if (sendBtn) sendBtn.disabled = busy || !current.installed;

    if (quick) {
      quick.classList.remove('hidden');
      quick.innerHTML = (meta.quick || []).map(function (q) {
        return '<button type="button" class="ai-ollama-quick__btn" data-ollama-quick="' + esc(q) + '">' + esc(q) + '</button>';
      }).join('');
    }

    var extra = document.getElementById('ai-ollama-extra-fields');
    var auditBtn = document.getElementById('ai-ollama-audit-btn');
    if (extra) extra.classList.toggle('hidden', selectedSlug !== 'agent-imagini');
    if (auditBtn) auditBtn.classList.toggle('hidden', selectedSlug !== 'agent-imagini');
  }

  function appendMessage(role, text, meta) {
    var box = document.getElementById('ai-ollama-messages');
    if (!box) return;
    if (box.querySelector('.ai-empty-inline')) box.innerHTML = '';

    var article = document.createElement('article');
    article.className = 'ai-ollama-msg ai-ollama-msg--' + role;
    var head = role === 'user' ? 'Tu' : 'Agent';
    if (meta && meta.model) head += ' · ' + meta.model;
    if (meta && meta.mode) head += ' (' + meta.mode + ')';
    article.innerHTML = '<header class="ai-ollama-msg__head">' + esc(head) + '</header>'
      + '<div class="ai-ollama-msg__body">' + esc(text).replace(/\n/g, '<br>') + '</div>';
    box.appendChild(article);
    box.scrollTop = box.scrollHeight;
  }

  function loadStatus() {
    return apiGet({ action: 'agent_ollama_status' }).then(function (json) {
      if (!json.success) throw new Error(json.message || 'Status eșuat');
      renderStatus(json.data);
      renderAgentCards(json.data.agents || []);
      updateChatChrome();
      loadEscalations();
      loadKpi();
    }).catch(function (err) {
      var el = document.getElementById('ai-ollama-status');
      if (el) {
        el.textContent = 'Eroare status Ollama: ' + (err.message || err);
        el.className = 'ai-ollama-status is-warn';
      }
    });
  }

  function loadEscalations() {
    return apiGet({ action: 'shop_chat_escalations', limit: 15 }).then(function (res) {
      var wrap = document.getElementById('ai-ollama-escalations');
      var list = document.getElementById('ai-ollama-esc-list');
      var countEl = document.getElementById('ai-ollama-esc-count');
      if (!wrap || !list) return;
      var data = (res && res.data) || {};
      var items = data.items || [];
      var count = data.open_count != null ? data.open_count : items.length;
      if (countEl) countEl.textContent = String(count);
      if (count <= 0) {
        wrap.hidden = true;
        return;
      }
      wrap.hidden = false;
      list.innerHTML = items.map(function (item) {
        var id = item.id || '';
        var ch = item.channel || 'chat';
        var msg = item.message || '';
        var when = item.created_at || '';
        return '<article class="ai-ollama-esc-item" data-esc-id="' + esc(id) + '">'
          + '<div class="ai-ollama-esc-item__meta"><strong>#' + esc(id) + '</strong> · ' + esc(ch) + ' · ' + esc(when) + '</div>'
          + '<p class="ai-ollama-esc-item__msg">' + esc(msg) + '</p>'
          + '<div class="ai-ollama-esc-item__actions">'
          + '<button type="button" class="ai-btn ai-btn--sm ai-btn--outline ai-ollama-esc-resolve" data-id="' + esc(id) + '">Rezolvat</button>'
          + '</div></article>';
      }).join('');
      list.querySelectorAll('.ai-ollama-esc-resolve').forEach(function (btn) {
        btn.addEventListener('click', function () {
          var eid = parseInt(btn.getAttribute('data-id') || '0', 10);
          if (!eid) return;
          apiPost({ action: 'shop_chat_escalation_resolve', id: eid, status: 'resolved' }).then(function (r) {
            toast((r && r.message) || 'Gata');
            loadEscalations();
          });
        });
      });
    }).catch(function () { /* optional */ });
  }

  function loadKpi() {
    return apiGet({ action: 'agent_intelligence_kpi', days: 7 }).then(function (res) {
      var wrap = document.getElementById('ai-ollama-kpi');
      var grid = document.getElementById('ai-ollama-kpi-grid');
      var miss = document.getElementById('ai-ollama-kpi-miss');
      if (!wrap || !grid) return;
      var d = (res && res.data) || {};
      if (!res || !res.success) {
        wrap.hidden = true;
        return;
      }
      wrap.hidden = false;
      var p = d.products || {};
      var im = d.import || {};
      var ch = d.chat || {};
      var fu = d.followup || {};
      var cards = [
        { label: 'Produse active', value: p.active },
        { label: 'Fără imagine', value: p.without_image },
        { label: 'Import pending', value: im.staging_pending },
        { label: 'Escaladări', value: ch.escalations_open },
        { label: 'Coș abandonat', value: fu.cart_abandon_open },
        { label: 'Follow-up pending', value: fu.chat_followups_pending },
        { label: 'Comenzi 7 zile', value: ch.orders_week },
        { label: 'WA trimise (log)', value: fu.whatsapp_sent_log_hits },
        { label: 'SMS trimise (log)', value: fu.sms_sent_log_hits },
      ];
      grid.innerHTML = cards.map(function (c) {
        return '<div class="ai-ollama-kpi-card"><span class="ai-ollama-kpi-card__val">' + esc(c.value != null ? c.value : '—') + '</span><span class="ai-ollama-kpi-card__lbl">' + esc(c.label) + '</span></div>';
      }).join('');

      if (miss) {
        var top = ch.search_miss_top || [];
        if (!top.length) {
          miss.innerHTML = '';
        } else {
          miss.innerHTML = '<h4>Căutări fără rezultat (top)</h4><ul>'
            + top.map(function (row) {
              return '<li>' + esc(row.query_value || '') + ' — ' + esc(row.miss_count || 0) + '×</li>';
            }).join('') + '</ul>';
        }
      }
    }).catch(function () { /* optional */ });
  }

  function exportFaq() {
    if (!confirm('Export FAQ din shop_chat_knowledge în finetuning/date_antrenare.jsonl?')) return;
    apiPost({ action: 'finetuning_export_faq', limit: 500 }).then(function (json) {
      toast((json && json.message) || 'Export finalizat');
    }).catch(function () {
      toast('Eroare export FAQ');
    });
  }

  function loadBesoiuModel() {
    return apiGet({ action: 'agent_besoiu_model_status' }).then(function (res) {
      var wrap = document.getElementById('ai-ollama-besoiu-panel');
      var body = document.getElementById('ai-ollama-besoiu-body');
      if (!wrap || !body) return;
      var d = (res && res.data) || {};
      if (!res || !res.success) {
        wrap.hidden = true;
        return;
      }
      wrap.hidden = false;
      var lines = [
        '<p><strong>Model:</strong> ' + esc(d.model_name || 'besoiu-llama') + '</p>',
        '<p><strong>Instalat în Ollama:</strong> ' + (d.installed ? '✅ da' : '❌ nu') + '</p>',
        '<p><strong>GGUF local:</strong> ' + (d.gguf_found ? '✅' : '❌') + (d.gguf_path ? ' <code>' + esc(d.gguf_path) + '</code>' : '') + '</p>',
        '<p><strong>Activ pentru agenți produse/clienți:</strong> ' + (d.active_for_agents ? '✅' : '—') + '</p>',
      ];
      if (d.recommended_env) {
        lines.push('<p><strong>Env recomandat:</strong></p><pre class="ai-code">' + esc(Object.keys(d.recommended_env).map(function (k) {
          return k + '=' + d.recommended_env[k];
        }).join('\n')) + '</pre>');
      }
      body.innerHTML = lines.join('');
    }).catch(function () { /* optional */ });
  }

  function deployBesoiuModel() {
    if (!confirm('Rulează ollama create besoiu-llama din GGUF local? (necesită Ollama pe server)')) return;
    apiPost({ action: 'besoiu_model_deploy' }).then(function (json) {
      toast((json && json.message) || 'Deploy finalizat');
      loadBesoiuModel();
      loadStatus();
    }).catch(function () {
      toast('Eroare deploy model');
    });
  }

  function sendChat(message, extra) {
    if (busy) return;
    var msg = String(message || '').trim();
    if (!msg) return;

    busy = true;
    updateChatChrome();
    appendMessage('user', msg);

    var payload = Object.assign({
      action: 'agent_ollama_chat',
      slug: selectedSlug,
      message: msg,
    }, extra || {});

    apiPost(payload).then(function (json) {
      var data = json.data || {};
      if (!json.success) {
        appendMessage('assistant', json.message || data.error || 'Eroare', data);
        toast(json.message || 'Eroare agent');
        return;
      }
      appendMessage('assistant', json.message || data.content || '', data);
    }).catch(function (err) {
      appendMessage('assistant', 'Eroare rețea: ' + (err.message || err));
    }).finally(function () {
      busy = false;
      updateChatChrome();
    });
  }

  function runDailyStats() {
    if (busy) return;
    busy = true;
    updateChatChrome();
    appendMessage('user', '[Raport KPI zilnic automat]');

    apiPost({ action: 'agent_ollama_daily_stats' }).then(function (json) {
      var data = json.data || {};
      if (!json.success) {
        appendMessage('assistant', json.message || data.error || 'Eroare', data);
        toast('Raport KPI eșuat');
        return;
      }
      selectedSlug = 'agent-statistici';
      renderAgentCards((statusData && statusData.agents) || []);
      updateChatChrome();
      appendMessage('assistant', json.message || data.content || '', data);
      toast('Raport KPI salvat în agent-statistici/daily_report.md');
    }).finally(function () {
      busy = false;
      updateChatChrome();
    });
  }

  function bindEvents() {
    document.addEventListener('ai-agent-tab-ollama', function () {
      loadStatus();
    });

    document.addEventListener('click', function (e) {
      var card = e.target.closest('[data-ollama-slug]');
      if (card && card.closest('#ai-ollama-agent-cards')) {
        selectedSlug = card.getAttribute('data-ollama-slug') || selectedSlug;
        document.querySelectorAll('.ai-ollama-agent-card').forEach(function (c) {
          c.classList.toggle('is-active', c.getAttribute('data-ollama-slug') === selectedSlug);
        });
        updateChatChrome();
        return;
      }

      var quick = e.target.closest('[data-ollama-quick]');
      if (quick) {
        var input = document.getElementById('ai-ollama-input');
        if (input) input.value = quick.getAttribute('data-ollama-quick') || '';
        sendChat(input ? input.value : '');
        return;
      }
    });

    var refresh = document.getElementById('ai-ollama-refresh');
    if (refresh) refresh.addEventListener('click', loadStatus);

    var escRefresh = document.getElementById('ai-ollama-esc-refresh');
    if (escRefresh) escRefresh.addEventListener('click', loadEscalations);

    var kpiRefresh = document.getElementById('ai-ollama-kpi-refresh');
    if (kpiRefresh) kpiRefresh.addEventListener('click', loadKpi);

    var exportFaqBtn = document.getElementById('ai-ollama-export-faq');
    if (exportFaqBtn) exportFaqBtn.addEventListener('click', exportFaq);

    var besoiuToolbar = document.getElementById('ai-ollama-besoiu-model');
    if (besoiuToolbar) besoiuToolbar.addEventListener('click', loadBesoiuModel);

    var besoiuRefresh = document.getElementById('ai-ollama-besoiu-refresh');
    if (besoiuRefresh) besoiuRefresh.addEventListener('click', loadBesoiuModel);

    var besoiuDeploy = document.getElementById('ai-ollama-besoiu-deploy');
    if (besoiuDeploy) besoiuDeploy.addEventListener('click', deployBesoiuModel);

    var daily = document.getElementById('ai-ollama-daily-stats');
    if (daily) daily.addEventListener('click', runDailyStats);

    var send = document.getElementById('ai-ollama-send');
    if (send) {
      send.addEventListener('click', function () {
        var input = document.getElementById('ai-ollama-input');
        var val = input ? input.value : '';
        if (input) input.value = '';
        var extra = {};
        var pid = document.getElementById('ai-ollama-product-id');
        if (pid && pid.value.trim()) extra.randomn_id = pid.value.trim();
        sendChat(val, extra);
      });
    }

    var auditBtn = document.getElementById('ai-ollama-audit-btn');
    if (auditBtn) {
      auditBtn.addEventListener('click', function () {
        var pid = document.getElementById('ai-ollama-product-id');
        var id = pid ? pid.value.trim() : '';
        if (!id) {
          toast('Introdu randomn_id produs');
          return;
        }
        sendChat('Audit imagine produs', { randomn_id: id, mode: 'audit' });
      });
    }

    var input = document.getElementById('ai-ollama-input');
    if (input) {
      input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
          e.preventDefault();
          document.getElementById('ai-ollama-send')?.click();
        }
      });
    }
  }

  function init() {
    if (!document.getElementById('ai-ollama-panel')) return;
    bindEvents();
    if ((window.location.hash || '').replace('#', '') === 'ollama') {
      loadStatus();
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
