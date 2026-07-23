(function () {
  'use strict';

  var STORAGE_KEY = 'bpa_dom_marker_on';
  var ROOT_ID = 'bpa-dom-marker-root';

  function readCfg() {
    var el = document.getElementById('bpa-dom-marker-cfg');
    if (el) {
      try { return JSON.parse(el.textContent || '{}'); } catch (e) { return {}; }
    }
    return {
      api: '/admin/api/comunicare_endpoint.php',
      csrf: (document.querySelector('meta[name="csrf-token"]') || {}).content || '',
      scope: location.pathname.indexOf('/admin') === 0 ? 'admin' : 'storefront',
      page_url: location.pathname + location.search,
      page_title: document.title || ''
    };
  }

  function esc(v) {
    return String(v == null ? '' : v).replace(/[&<>"']/g, function (c) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[c];
    });
  }

  function cssEsc(v) {
    if (window.CSS && typeof CSS.escape === 'function') return CSS.escape(v);
    return String(v).replace(/[^a-zA-Z0-9_-]/g, '\\$&');
  }

  function qs(sel, root) {
    return (root || document).querySelector(sel);
  }

  function isMarkerUi(el) {
    if (!el || !el.closest) return true;
    return !!el.closest('#' + ROOT_ID + ', .bpa-dm-bar, .bpa-dm-modal-backdrop, .bpa-cms-toolbar, #bsa-root, .bsa-root');
  }

  function directText(el) {
    if (!el || !el.childNodes) return '';
    var parts = [];
    for (var i = 0; i < el.childNodes.length; i++) {
      var n = el.childNodes[i];
      if (n.nodeType === 3) {
        var t = String(n.textContent || '').replace(/\s+/g, ' ').trim();
        if (t) parts.push(t);
      }
    }
    return parts.join(' ').trim();
  }

  function elementTypeRo(el) {
    if (!el || !el.tagName) return 'Zonă pagină';
    var tag = el.tagName.toLowerCase();
    var role = String(el.getAttribute('role') || '').toLowerCase();
    if (tag === 'button' || role === 'button') return 'Buton';
    if (tag === 'a') return 'Link';
    if (tag === 'input' || tag === 'select' || tag === 'textarea') return 'Câmp formular';
    if (tag === 'tr' || tag === 'td' || tag === 'th') return 'Rând tabel';
    if (el.closest && el.closest('table')) return 'Tabel date';
    if (el.closest && el.closest('nav')) return 'Meniu navigare';
    if (el.closest && el.closest('[class*="card"], .st-card, .bws-metric, .supplier-card')) return 'Card informații';
    return 'Zonă pagină';
  }

  function findMarkerContext(el) {
    var node = el;
    while (node && node.nodeType === 1 && node !== document.body) {
      var help = node.getAttribute('data-bpa-help') || node.getAttribute('data-marker-label');
      if (help) return String(help).replace(/\s+/g, ' ').trim();
      var aria = node.getAttribute('aria-label');
      if (aria) return String(aria).replace(/\s+/g, ' ').trim();
      var title = node.getAttribute('title');
      if (title && title.length < 120) return String(title).replace(/\s+/g, ' ').trim();
      if (node.matches && node.matches('h1,h2,h3,h4,h5,.st-card__title,[class*="__title"],[class*="__name"]')) {
        var ht = String(node.innerText || node.textContent || '').replace(/\s+/g, ' ').trim();
        if (ht && ht.length <= 80) return ht;
      }
      var heading = node.querySelector && node.querySelector(':scope > h1, :scope > h2, :scope > h3, :scope > h4, .st-card__title, [class*="__title"]');
      if (heading) {
        var htxt = String(heading.innerText || heading.textContent || '').replace(/\s+/g, ' ').trim();
        if (htxt && htxt.length <= 80) return htxt;
      }
      node = node.parentElement;
    }
    return '';
  }

  function elementLabel(el) {
    if (!el || el.nodeType !== 1) return 'Zonă pagină';
    var actionEl = el.closest && el.closest('button, a[href], [role="button"]');
    if (actionEl) el = actionEl;

    var ctx = findMarkerContext(el);
    if (ctx) return ctx;

    var direct = directText(el);
    if (direct && direct.length <= 80) return direct;

    var tag = el.tagName ? el.tagName.toLowerCase() : '';
    if (tag === 'div' || tag === 'span' || tag === 'section' || tag === 'article' || tag === 'li') {
      var parent = el.parentElement;
      var depth = 0;
      while (parent && parent !== document.body && depth < 8) {
        ctx = findMarkerContext(parent);
        if (ctx) return ctx;
        direct = directText(parent);
        if (direct && direct.length <= 60) return direct;
        parent = parent.parentElement;
        depth++;
      }
    }

    if (el.getAttribute && el.getAttribute('placeholder')) {
      return String(el.getAttribute('placeholder')).trim();
    }

    return elementTypeRo(el);
  }

  function hoverTooltip(el, cfg) {
    var label = elementLabel(el);
    if (canDocument(cfg)) {
      return label + ' — Click: documentează (IT Specialist)';
    }
    return label + ' — Click: vezi ce face această zonă';
  }

  function buildSelector(el) {
    if (!el || el.nodeType !== 1) return '';
    if (el.id && /^[a-zA-Z][\w-]*$/.test(el.id)) {
      return '#' + cssEsc(el.id);
    }
    var parts = [];
    var node = el;
    var depth = 0;
    while (node && node.nodeType === 1 && depth < 6) {
      var part = node.tagName.toLowerCase();
      if (node.id && /^[a-zA-Z][\w-]*$/.test(node.id)) {
        parts.unshift('#' + cssEsc(node.id));
        break;
      }
      if (node.classList && node.classList.length) {
        var cls = Array.prototype.find.call(node.classList, function (c) {
          return c && !/^bpa-dm-/.test(c) && !/^bsa-/.test(c);
        });
        if (cls) part += '.' + cssEsc(cls);
      }
      var parent = node.parentElement;
      if (parent) {
        var siblings = Array.prototype.filter.call(parent.children, function (c) {
          return c.tagName === node.tagName;
        });
        if (siblings.length > 1) {
          part += ':nth-of-type(' + (siblings.indexOf(node) + 1) + ')';
        }
      }
      parts.unshift(part);
      node = parent;
      depth++;
    }
    return parts.join(' > ');
  }

  function collectDomMeta(el) {
    if (el && el.nodeType === 1 && el.closest) {
      var actionEl = el.closest('button, a[href], [role="button"]');
      if (actionEl) el = actionEl;
    }
    var attrs = {};
    ['type', 'name', 'href', 'role', 'onclick', 'data-bpa-help', 'data-action', 'data-intent', 'data-product-id', 'data-cart-count'].forEach(function (a) {
      var v = el.getAttribute(a);
      if (v) attrs[a] = v;
    });
    var rect = el.getBoundingClientRect();
    return {
      tag: el.tagName.toLowerCase(),
      id: el.id || '',
      classes: el.className ? String(el.className).split(/\s+/).filter(Boolean).slice(0, 8) : [],
      text: (el.innerText || el.textContent || '').replace(/\s+/g, ' ').trim().slice(0, 200),
      label: elementLabel(el),
      selector: buildSelector(el),
      page_url: location.pathname + location.search,
      page_title: document.title || '',
      scope: readCfg().scope || 'storefront',
      onclick: el.getAttribute('onclick') || '',
      data_bpa_help: el.getAttribute('data-bpa-help') || '',
      attrs: attrs,
      xpath_hint: buildSelector(el)
    };
  }

  function toast(msg, isErr) {
    var old = document.querySelector('.bpa-dm-toast');
    if (old) old.remove();
    var t = document.createElement('div');
    t.className = 'bpa-dm-toast' + (isErr ? ' is-error' : '');
    t.textContent = msg;
    document.body.appendChild(t);
    setTimeout(function () { t.remove(); }, 3200);
  }

  function canDocument(cfg) {
    if (!cfg) cfg = readCfg();
    if (cfg.mode === 'document' && cfg.can_document === true) return true;
    if (cfg.mode === 'inform') return false;
    return cfg.can_document === true;
  }

  function applyToggleTitles(cfg) {
    cfg = cfg || readCfg();
    var doc = canDocument(cfg);
    var idleTitle = doc
      ? 'Marker DOM — documentează elemente (IT Specialist)'
      : 'Marker DOM — vezi ce face fiecare zonă (popup + chat)';
    document.querySelectorAll('[data-bpa-dom-marker-toggle]').forEach(function (btn) {
      if (btn.getAttribute('aria-pressed') !== 'true') {
        btn.title = idleTitle;
      }
    });
  }

  function markerModeLabel(cfg) {
    return canDocument(cfg) ? 'documentare RAG' : 'instrucțiuni de lucru';
  }

  function setStatus(msg) {
    var el = document.getElementById('bpaDomMarkerStatus');
    if (el) el.textContent = msg || '';
  }

  var Marker = {
    active: false,
    cfg: readCfg(),
    root: null,
    highlight: null,
    tooltip: null,
    hovered: null,

    init: function () {
      this.root = document.createElement('div');
      this.root.id = ROOT_ID;
      this.root.className = 'bpa-dm-root';
      this.highlight = document.createElement('div');
      this.highlight.className = 'bpa-dm-highlight';
      this.highlight.style.display = 'none';
      this.tooltip = document.createElement('div');
      this.tooltip.className = 'bpa-dm-tooltip';
      this.tooltip.style.display = 'none';
      this.root.appendChild(this.highlight);
      this.root.appendChild(this.tooltip);
      document.body.appendChild(this.root);

      var self = this;
      document.addEventListener('mousemove', function (e) { self.onMove(e); }, true);
      document.addEventListener('click', function (e) { self.onClick(e); }, true);
      document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && self.active) self.disable();
      });

      var toggleBar = document.getElementById('bpaDomMarkerToggle');
      if (toggleBar) {
        toggleBar.addEventListener('click', function () {
          if (self.active) self.disable(); else self.enable();
        });
      }

      document.querySelectorAll('[data-bpa-dom-marker-toggle]').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
          e.preventDefault();
          self.toggle();
        });
      });

      var fab = document.getElementById('bpaDomMarkerFab');
      if (fab) {
        fab.addEventListener('click', function () {
          self.toggle();
          fab.classList.toggle('is-active', self.active);
          fab.textContent = self.active ? '✕ Oprește' : '⛶ Marker DOM';
        });
      }

      // Nu reporni automat din localStorage — markerul lăsat ON blochează ORICE click
      // (popup pe fiecare buton) și pare că „funcționalitățile nu mai merg”.
      // Persistență doar pe sesiunea curentă (sessionStorage) + query ?bpa_dom_marker=1.
      var shouldStart = false;
      try {
        if (localStorage.getItem(STORAGE_KEY) === '1') {
          localStorage.removeItem(STORAGE_KEY);
        }
        shouldStart = sessionStorage.getItem(STORAGE_KEY) === '1';
      } catch (e) {}
      if (location.search.indexOf('bpa_dom_marker=1') >= 0) shouldStart = true;
      if (shouldStart) this.enable();
      else this.syncToggleButtons(false);
      applyToggleTitles(this.cfg);
    },

    enable: function () {
      this.active = true;
      this.cfg = readCfg();
      document.body.classList.add('bpa-dom-marker-on');
      try {
        sessionStorage.setItem(STORAGE_KEY, '1');
        localStorage.removeItem(STORAGE_KEY);
      } catch (e) {}
      this.syncToggleButtons(true);
      var hint = canDocument(this.cfg)
        ? 'Marker activ — click pe element (mod IT Specialist: documentare RAG)'
        : 'Marker activ — click pe element (vezi explicația în popup și chat)';
      setStatus(hint);
      toast(canDocument(this.cfg)
        ? 'Marker DOM — mod documentare IT Specialist.'
        : 'Marker DOM — click pe o zonă pentru a vedea ce face.');
    },

    disable: function () {
      this.active = false;
      document.body.classList.remove('bpa-dom-marker-on');
      try {
        sessionStorage.removeItem(STORAGE_KEY);
        localStorage.removeItem(STORAGE_KEY);
      } catch (e) {}
      if (this.highlight) this.highlight.style.display = 'none';
      if (this.tooltip) this.tooltip.style.display = 'none';
      this.syncToggleButtons(false);
      setStatus('');
    },

    toggle: function () {
      if (this.active) this.disable(); else this.enable();
    },

    syncToggleButtons: function (on) {
      var cfg = readCfg();
      var doc = canDocument(cfg);
      var idleTitle = doc
        ? 'Marker DOM — documentează elemente (IT Specialist)'
        : 'Marker DOM — vezi ce face fiecare zonă (popup + chat)';
      document.querySelectorAll('[data-bpa-dom-marker-toggle]').forEach(function (btn) {
        btn.classList.toggle('is-active', !!on);
        btn.setAttribute('aria-pressed', on ? 'true' : 'false');
        btn.title = on
          ? (doc ? 'Oprește marker (mod documentare)' : 'Oprește marker (mod instrucțiuni)')
          : idleTitle;
      });
      var barBtn = document.getElementById('bpaDomMarkerToggle');
      if (barBtn) {
        barBtn.textContent = on ? 'Oprește marker' : 'Pornește marker';
        barBtn.setAttribute('aria-pressed', on ? 'true' : 'false');
      }
      var fab = document.getElementById('bpaDomMarkerFab');
      if (fab) {
        fab.classList.toggle('is-active', !!on);
        fab.textContent = on ? '✕ Oprește' : '⛶ Marker DOM';
      }
    },

    onMove: function (e) {
      if (!this.active) return;
      var el = document.elementFromPoint(e.clientX, e.clientY);
      if (!el || isMarkerUi(el)) {
        this.highlight.style.display = 'none';
        this.tooltip.style.display = 'none';
        return;
      }
      this.hovered = el;
      var rect = el.getBoundingClientRect();
      this.highlight.style.display = 'block';
      this.highlight.style.top = rect.top + 'px';
      this.highlight.style.left = rect.left + 'px';
      this.highlight.style.width = rect.width + 'px';
      this.highlight.style.height = rect.height + 'px';
      this.tooltip.style.display = 'block';
      this.tooltip.textContent = hoverTooltip(el, readCfg());
      this.tooltip.style.top = Math.max(8, rect.top - 32) + 'px';
      this.tooltip.style.left = Math.min(window.innerWidth - 330, rect.left) + 'px';
    },

    onClick: function (e) {
      if (!this.active) return;
      var el = e.target;
      if (isMarkerUi(el)) return;
      e.preventDefault();
      e.stopPropagation();
      this.cfg = readCfg();
      if (canDocument(this.cfg)) {
        this.openModal(el);
      } else {
        this.openInformMode(el);
      }
    },

    lookupDomKnowledge: function (meta) {
      var cfg = readCfg();
      var qs = [
        'action=chat_knowledge_marker_lookup',
        'selector=' + encodeURIComponent(meta.selector || ''),
        'id=' + encodeURIComponent(meta.id || ''),
        'label=' + encodeURIComponent(meta.label || ''),
        'text=' + encodeURIComponent(meta.text || ''),
        'tag=' + encodeURIComponent(meta.tag || ''),
        'page_url=' + encodeURIComponent(meta.page_url || cfg.page_url || location.pathname),
        'scope=' + encodeURIComponent(meta.scope || cfg.scope || 'admin'),
        'onclick=' + encodeURIComponent(meta.onclick || ''),
        'data_bpa_help=' + encodeURIComponent(meta.data_bpa_help || '')
      ].join('&');

      return fetch(cfg.api + '?' + qs, { credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (res) {
          if (!res.success) throw new Error(res.message || 'Eroare căutare RAG');
          return res.data || {};
        });
    },

    openInformMode: function (el) {
      var meta = collectDomMeta(el);
      var self = this;
      setStatus('Se caută explicația…');

      this.lookupDomKnowledge(meta)
        .then(function (data) {
          self.disable();
          var detail = {
            meta: meta,
            instruction: data.instruction || {},
            found: !!data.found,
            entry: data.entry || null
          };

          self.openInfoModal(meta, data);

          if (window.BesoiuSectionAssist && typeof window.BesoiuSectionAssist.showDomMarkerGuide === 'function') {
            window.setTimeout(function () {
              window.BesoiuSectionAssist.showDomMarkerGuide(detail);
            }, 350);
          }

          toast(data.found
            ? 'Explicație pentru: ' + (meta.label || 'element selectat')
            : 'Nu există documentație — vezi chat sau contactează IT Specialist');
        })
        .catch(function (err) {
          toast(err.message || 'Eroare', true);
          setStatus('Eroare la căutare explicație');
        });
    },

    openInfoModal: function (meta, data) {
      var instr = (data && data.instruction) ? data.instruction : {};
      var htmlBody = instr.html || '';
      if (!htmlBody) {
        htmlBody = '<p>Nu există încă o explicație pentru <strong>' + esc(meta.label || 'acest element') + '</strong>.</p>'
          + '<p style="margin-top:8px;color:#64748b;font-size:13px;">Un IT Specialist poate documenta această zonă din Marker DOM.</p>';
      }

      var backdrop = document.createElement('div');
      backdrop.className = 'bpa-dm-modal-backdrop';
      backdrop.innerHTML =
        '<div class="bpa-dm-modal bpa-dm-modal--info" role="dialog" aria-modal="true">'
        + '<div class="bpa-dm-modal__head">'
        + '<div><h3>Ce face această zonă?</h3>'
        + '<p class="bpa-dm-modal__sub"><strong>' + esc(meta.label || 'Element selectat') + '</strong></p></div>'
        + '<button type="button" class="bpa-dm-modal__close" aria-label="Închide">×</button>'
        + '</div>'
        + '<div class="bpa-dm-modal__body">'
        + '<div class="bpa-dm-info">' + htmlBody + '</div>'
        + '<div class="bpa-dm-actions">'
        + '<button type="button" class="bpa-dm-btn bpa-dm-btn--primary" data-close>Am înțeles</button>'
        + '</div></div></div>';

      backdrop.querySelector('.bpa-dm-modal__close').addEventListener('click', function () { backdrop.remove(); });
      backdrop.querySelector('[data-close]').addEventListener('click', function () { backdrop.remove(); });
      backdrop.addEventListener('click', function (ev) {
        if (ev.target === backdrop) backdrop.remove();
      });
      document.body.appendChild(backdrop);
    },

    openModal: function (el) {
      var meta = collectDomMeta(el);
      var backdrop = document.createElement('div');
      backdrop.className = 'bpa-dm-modal-backdrop';
      backdrop.innerHTML =
        '<div class="bpa-dm-modal" role="dialog" aria-modal="true">'
        + '<div class="bpa-dm-modal__head">'
        + '<div><h3>Documentează elementul</h3>'
        + '<p class="bpa-dm-modal__sub">Salvare în baza RAG — chat-ul va învăța ce face acest element.</p></div>'
        + '<button type="button" class="bpa-dm-modal__close" aria-label="Închide">×</button>'
        + '</div>'
        + '<div class="bpa-dm-modal__body">'
        + '<div class="bpa-dm-meta">' + esc(meta.selector) + '<br>' + esc(meta.tag) + (meta.id ? ' #' + esc(meta.id) : '') + '</div>'
        + '<div class="bpa-dm-field"><label>Ce este?</label><input name="what_is" value="' + esc(meta.label) + '" placeholder="Ex: Buton Adaugă în coș"></div>'
        + '<div class="bpa-dm-field"><label>Ce face?</label><textarea name="what_does" placeholder="Ex: Adaugă produsul în coșul localStorage și afișează badge"></textarea></div>'
        + '<div class="bpa-dm-field"><label>Pentru ce? (scop)</label><textarea name="why" placeholder="Ex: Clientul vrea să cumpere fără a părăsi pagina"></textarea></div>'
        + '<div class="bpa-dm-field"><label>Ce rezultat dă?</label><textarea name="expected_result" placeholder="Ex: Badge coș +1, popup confirmare, imagine în coș"></textarea></div>'
        + '<div class="bpa-dm-field"><label>Canal</label><select name="channel">'
        + '<option value="internal">Admin intern</option>'
        + '<option value="external">Widget / site public</option>'
        + '<option value="both">Ambele</option>'
        + '</select></div>'
        + '<div class="bpa-dm-field"><label>Acțiune chat (opțional)</label><input name="expected_action" placeholder="search, order, refine, whatsapp…"></div>'
        + '<div class="bpa-dm-actions">'
        + '<button type="button" class="bpa-dm-btn bpa-dm-btn--primary" data-save>Salvează în RAG</button>'
        + '<button type="button" class="bpa-dm-btn bpa-dm-btn--ghost" data-cancel>Anulează</button>'
        + '</div></div></div>';

      var self = this;
      backdrop.querySelector('.bpa-dm-modal__close').addEventListener('click', function () { backdrop.remove(); });
      backdrop.querySelector('[data-cancel]').addEventListener('click', function () { backdrop.remove(); });
      backdrop.addEventListener('click', function (ev) {
        if (ev.target === backdrop) backdrop.remove();
      });
      backdrop.querySelector('[data-save]').addEventListener('click', function () {
        self.saveAnnotation(backdrop, meta);
      });
      document.body.appendChild(backdrop);
      var chSel = backdrop.querySelector('[name="channel"]');
      if (chSel) {
        chSel.value = (readCfg().scope === 'admin') ? 'internal' : 'external';
      }
      var first = backdrop.querySelector('[name="what_does"]');
      if (first) first.focus();
    },

    saveAnnotation: function (backdrop, meta) {
      var cfg = readCfg();
      var get = function (n) {
        var inp = backdrop.querySelector('[name="' + n + '"]');
        return inp ? String(inp.value || '').trim() : '';
      };
      var payload = {
        action: 'chat_knowledge_marker',
        what_is: get('what_is'),
        what_does: get('what_does'),
        why: get('why'),
        expected_result: get('expected_result'),
        expected_action: get('expected_action'),
        channel: get('channel') || 'external',
        dom: meta,
        question_examples: meta.text ? [meta.text] : []
      };

      var btn = backdrop.querySelector('[data-save]');
      if (btn) { btn.disabled = true; btn.textContent = 'Se salvează…'; }

      fetch(cfg.api, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          'X-Admin-CSRF': cfg.csrf || ''
        },
        body: JSON.stringify(payload)
      })
        .then(function (r) { return r.json(); })
        .then(function (res) {
          if (!res.success) throw new Error(res.message || 'Eroare salvare');
          backdrop.remove();
          toast('Salvat în RAG chat ✓');
          setStatus('Salvat: ' + (payload.what_is || meta.label));
        })
        .catch(function (err) {
          toast(err.message || 'Eroare', true);
          if (btn) { btn.disabled = false; btn.textContent = 'Salvează în RAG'; }
        });
    }
  };

  window.BpaDomMarker = Marker;

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { Marker.init(); });
  } else {
    Marker.init();
  }
})();
