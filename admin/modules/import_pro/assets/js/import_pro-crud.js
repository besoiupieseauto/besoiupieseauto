(function () {
  'use strict';

  const cfg = window.__MOD_import_pro_UI__ || {};
  const ENDPOINT = cfg.apiUrl || '/admin/public/api/import_pro_endpoint.php';
  const columns = Array.isArray(cfg.columns) ? cfg.columns : [];
  const rowActions = Array.isArray(cfg.rowActions) ? cfg.rowActions : ['edit', 'delete'];
  const flags = cfg.flags || {};

  const loader = document.getElementById('mod-import_pro-loader');
  const empty = document.getElementById('mod-import_pro-empty');
  const tableBody = document.getElementById('mod-import_pro-table-body');
  const cardsEl = document.getElementById('mod-import_pro-cards');
  const toast = document.getElementById('mod-import_pro-toast');
  const modal = document.getElementById('mod-import_pro-modal');
  const form = document.getElementById('mod-import_pro-form');
  const searchInput = document.getElementById('mod-import_pro-search');
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

  let items = [];
  let meta = { page: 1, per_page: 20, total: 0, total_pages: 1 };

  function esc(s) {
    return String(s ?? '').replace(/[&<>"']/g, function (c) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];
    });
  }

  function showToast(msg, isError) {
    if (!toast) return;
    toast.textContent = msg;
    toast.classList.toggle('is-error', !!isError);
    toast.classList.remove('hidden');
    setTimeout(function () { toast.classList.add('hidden'); }, 3200);
  }

  async function apiCall(action, payload) {
    const res = await fetch(ENDPOINT, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Admin-CSRF': csrf },
      credentials: 'same-origin',
      body: JSON.stringify(Object.assign({ type_product: action }, payload || {})),
    });
    const json = await res.json();
    if (!res.ok || json.success === false) {
      throw new Error(json.message || 'Eroare API');
    }
    return json.data !== undefined ? json.data : json;
  }

  function cellValue(row, col) {
    const v = row[col.field];
    if (col.type === 'badge') {
      return '<span class="mod-import_pro-badge">' + esc(v ?? '—') + '</span>';
    }
    return esc(v ?? '');
  }

  function actionButtons(row) {
    let html = '<div class="text-center">';
    if (rowActions.includes('edit') && flags.edit) {
      html += '<button type="button" class="mod-import_pro-btn mod-import_pro-btn-ghost mod-import_pro-btn-sm" data-edit="' + esc(row.id) + '">Edit</button> ';
    }
    if (rowActions.includes('delete') && flags.delete) {
      html += '<button type="button" class="mod-import_pro-btn mod-import_pro-btn-danger mod-import_pro-btn-sm" data-delete="' + esc(row.id) + '">Șterge</button>';
    }
    return html + '</div>';
  }

  function renderTable(rows) {
    if (!tableBody) return;
    if (!rows.length) {
      tableBody.innerHTML = '';
      return;
    }
    tableBody.innerHTML = rows.map(function (row) {
      let tr = '<tr>';
      columns.forEach(function (col) {
        const align = col.align === 'right' ? ' text-right' : (col.align === 'center' ? ' text-center' : '');
        tr += '<td class="' + align.trim() + '">' + cellValue(row, col) + '</td>';
      });
      if (rowActions.length) tr += '<td>' + actionButtons(row) + '</td>';
      return tr + '</tr>';
    }).join('');
    bindRowActions();
  }

  function renderCards(rows) {
    if (!cardsEl) return;
    cardsEl.innerHTML = rows.map(function (row) {
      let body = columns.map(function (col) {
        return '<div><strong>' + esc(col.label) + ':</strong> ' + cellValue(row, col) + '</div>';
      }).join('');
      return '<article class="mod-import_pro-card">' + body + '<div class="mod-import_pro-card-actions">' + actionButtons(row) + '</div></article>';
    }).join('');
    bindRowActions();
  }

  function bindRowActions() {
    document.querySelectorAll('[data-edit]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        const id = btn.getAttribute('data-edit');
        const row = items.find(function (r) { return String(r.id) === String(id); });
        if (row) openModal('edit', row);
      });
    });
    document.querySelectorAll('[data-delete]').forEach(function (btn) {
      btn.addEventListener('click', async function () {
        const id = btn.getAttribute('data-delete');
        if (!confirm('Ștergi înregistrarea #' + id + '?')) return;
        try {
          await apiCall('delete', { id: id });
          showToast('Șters.');
          loadList();
        } catch (e) {
          showToast(e.message, true);
        }
      });
    });
  }

  function openModal(mode, row) {
    if (!modal || !form) return;
    form.dataset.action = mode === 'edit' ? 'edit' : 'add';
    document.getElementById('mod-import_pro-modal-title').textContent = mode === 'edit' ? 'Editează' : 'Adaugă';
    form.reset();
    if (row) {
      new FormData(form).forEach(function (_, key) {
        const el = form.elements.namedItem(key);
        if (el && row[key] !== undefined) el.value = row[key];
      });
      const idEl = document.getElementById('mod-import_pro-record-id');
      if (idEl) idEl.value = row.id || '';
    }
    modal.classList.remove('hidden');
  }

  function closeModal() {
    if (modal) modal.classList.add('hidden');
  }

  async function loadList() {
    if (loader) loader.classList.remove('hidden');
    if (empty) empty.classList.add('hidden');
    try {
      const q = searchInput ? searchInput.value.trim() : '';
      const data = await apiCall('list', { page: meta.page, per_page: meta.per_page, q: q });
      items = data.items || data.rows || (Array.isArray(data) ? data : []);
      meta.total = data.total || items.length;
      if (loader) loader.classList.add('hidden');
      if (!items.length) {
        if (empty) empty.classList.remove('hidden');
      }
      if (flags.layout === 'cards') renderCards(items);
      else renderTable(items);
      if (window.lucide) lucide.createIcons();
    } catch (e) {
      if (loader) loader.textContent = 'Eroare: ' + e.message;
      showToast(e.message, true);
    }
  }

  document.getElementById('mod-import_pro-btn-add')?.addEventListener('click', function () { openModal('add'); });
  document.getElementById('mod-import_pro-modal-close')?.addEventListener('click', closeModal);
  document.getElementById('mod-import_pro-btn-cancel')?.addEventListener('click', closeModal);
  searchInput?.addEventListener('input', function () {
    clearTimeout(searchInput._t);
    searchInput._t = setTimeout(loadList, 300);
  });

  form?.addEventListener('submit', async function (ev) {
    ev.preventDefault();
    const action = form.dataset.action || 'add';
    const payload = {};
    new FormData(form).forEach(function (v, k) { if (String(v).trim() !== '') payload[k] = v; });
    try {
      await apiCall(action, payload);
      closeModal();
      showToast('Salvat.');
      loadList();
    } catch (e) {
      showToast(e.message, true);
    }
  });

  document.querySelectorAll('.mod-import_pro-tabs [data-tab]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var tab = btn.getAttribute('data-tab');
      var tabsRoot = btn.closest('.mod-import_pro-tabs, .mod-import_pro-tabs-pills, .mod-import_pro-nav-vertical');
      if (tabsRoot) {
        tabsRoot.querySelectorAll('.nav-link').forEach(function (b) { b.classList.remove('active'); });
      }
      btn.classList.add('active');
      document.querySelectorAll('.mod-import_pro-tab-pane').forEach(function (p) {
        p.classList.toggle('hidden', p.getAttribute('data-tab-pane') !== tab);
      });
    });
  });

  document.querySelectorAll('.mod-import_pro-accordion [data-acc-toggle]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var panelId = btn.getAttribute('data-acc-toggle');
      var panel = document.getElementById(panelId);
      if (!panel) return;
      var open = panel.classList.contains('show');
      document.querySelectorAll('.mod-import_pro-accordion [data-acc-panel]').forEach(function (p) {
        p.classList.remove('show');
      });
      document.querySelectorAll('.mod-import_pro-accordion [data-acc-toggle]').forEach(function (b) {
        b.classList.add('collapsed');
      });
      if (!open) {
        panel.classList.add('show');
        btn.classList.remove('collapsed');
      }
    });
  });

  document.querySelectorAll('[data-collapse-toggle]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var targetId = btn.getAttribute('data-collapse-toggle');
      var panel = document.getElementById(targetId);
      if (panel) panel.classList.toggle('show');
    });
  });

  document.querySelectorAll('[data-offcanvas-open]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var el = document.getElementById(btn.getAttribute('data-offcanvas-open'));
      if (el) el.classList.add('show');
    });
  });
  document.querySelectorAll('[data-offcanvas-close]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var panel = btn.closest('.offcanvas');
      if (panel) panel.classList.remove('show');
    });
  });

  var sideNav = document.getElementById('mod-import_pro-side-nav');
  if (sideNav) {
    sideNav.querySelectorAll('[data-side]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        sideNav.querySelectorAll('[data-side]').forEach(function (b) { b.classList.remove('is-active'); });
        btn.classList.add('is-active');
      });
    });
  }
  document.querySelectorAll('.mod-import_pro-chip').forEach(function (chip) {
    chip.addEventListener('click', function () {
      document.querySelectorAll('.mod-import_pro-chip').forEach(function (c) { c.classList.remove('is-active'); });
      chip.classList.add('is-active');
      loadList();
    });
  });

  function elAnim(node, name) {
    if (!node || !name || name === 'none') return;
    node.classList.add('mod-import_pro-anim-' + name);
    setTimeout(function () { node.classList.remove('mod-import_pro-anim-' + name); }, 520);
  }

  document.querySelectorAll('.mod-import_pro-el-widget[data-action]').forEach(function (wrap) {
    var act = wrap.getAttribute('data-action') || 'none';
    var anim = wrap.getAttribute('data-animation') || 'none';
    if (act === 'none') return;
    wrap.querySelectorAll('button, .besoiu-btn-primary, [type="submit"]').forEach(function (btn) {
      if (btn._elActBound) return;
      btn._elActBound = true;
      btn.addEventListener('click', function (ev) {
        elAnim(btn, anim);
        if (act === 'open_modal') {
          ev.preventDefault();
          openModal('add');
        } else if (act === 'api_submit' && form) {
          ev.preventDefault();
          form.requestSubmit ? form.requestSubmit() : form.dispatchEvent(new Event('submit', { cancelable: true }));
        } else if (act === 'api_list') {
          ev.preventDefault();
          loadList();
        } else if (act === 'filter_table' && searchInput) {
          ev.preventDefault();
          searchInput.focus();
          loadList();
        } else if (act === 'show_toast') {
          ev.preventDefault();
          showToast('Widget: ' + act);
        } else if (act === 'api_delete') {
          ev.preventDefault();
          showToast('Ștergere simulată — conectează Handler la BD', true);
        } else if (act === 'toggle_panel') {
          ev.preventDefault();
          var panel = wrap.querySelector('[data-toggle-panel], .besoiu-toggle-body, .mod-import_pro-toggle-body');
          if (panel) panel.classList.toggle('is-open');
        } else if (act === 'switch_tab') {
          ev.preventDefault();
          var tabs = wrap.closest('[data-el-section]') || document;
          tabs.querySelectorAll('[data-tab]').forEach(function (t, idx) {
            t.classList.toggle('is-active', idx === 0);
          });
        } else if (act === 'open_offcanvas') {
          ev.preventDefault();
          var oc = document.querySelector('.mod-import_pro-offcanvas, .offcanvas');
          if (oc) oc.classList.add('show');
        } else if (act === 'open_page') {
          ev.preventDefault();
          var slug = wrap.getAttribute('data-target-page') || '';
          var url = wrap.getAttribute('data-target-url') || '';
          showToast('Navigare → ' + (slug || url || 'pagină detaliu'));
        }
      });
    });
  });

  loadList();
})();
