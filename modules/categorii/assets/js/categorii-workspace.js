(function () {
  'use strict';

  const cfg = window.CP_WORKSPACE;
  if (!cfg || !Array.isArray(cfg.items)) return;

  const PER_PAGE = cfg.perPage || 25;
  const tabsEl = document.getElementById('cpTabs');
  const panelTree = document.getElementById('cpPanelBesoiu');
  const panelTable = document.getElementById('cpPanelTable');
  const tableBody = document.getElementById('cpTableBody');
  const tableEmpty = document.getElementById('cpTableEmpty');
  const paginationEl = document.getElementById('cpTablePagination');
  const searchBox = document.getElementById('searchBox');
  const filterSource = document.getElementById('cpFilterSource');
  const filterStatus = document.getElementById('cpFilterStatus');
  const filterType = document.getElementById('cpFilterType');
  const filtersBar = document.getElementById('cpWorkspaceFilters');

  const state = {
    view: cfg.initial.view || 'besoiu',
    type: cfg.initial.type || '',
    q: cfg.initial.q || '',
    source: 'all',
    status: 'all',
    catalogType: 'all',
    page: 1,
  };

  function esc(text) {
    const d = document.createElement('div');
    d.textContent = String(text ?? '');
    return d.innerHTML;
  }

  function sourceBadge(src) {
    if (src === 'besoiu_tree') {
      return '<span class="cp-src cp-src--besoiu">Excel Besoiu</span>';
    }
    if (src === 'pieseauto') {
      return '<span class="cp-src">PieseAuto</span>';
    }
    if (src === 'manual' || src === 'local' || src === 'besoiu') {
      return '<span class="cp-src cp-src--local">Manual</span>';
    }
    if (src === 'tecdoc') {
      return '<span class="cp-src">TecDoc</span>';
    }
    return '<span class="cp-src cp-src--local">' + esc(src || 'Manual') + '</span>';
  }

  function typeClass(type) {
    return cfg.typeMeta[type] ? type : 'categorie';
  }

  function matchesTab(item) {
    const view = state.view;
    const type = state.type;
    if (view === 'besoiu') return false;
    if (view === 'other') {
      return item.source !== 'besoiu_tree'
        && ['categorie', 'subcategorie', 'familie'].indexOf(item.type) !== -1;
    }
    if (view === 'all') {
      if (type) return item.type === type;
      return ['categorie', 'subcategorie', 'familie'].indexOf(item.type) !== -1;
    }
    return true;
  }

  function matchesFilters(item) {
    const q = state.q.trim().toLowerCase();
    if (q && item.search.indexOf(q) === -1) return false;
    if (state.source !== 'all') {
      if (state.source === 'local') {
        if (item.source === 'besoiu_tree' || item.source === 'pieseauto') return false;
      } else if (item.source !== state.source) return false;
    }
    if (state.status === 'active' && !item.is_active) return false;
    if (state.status === 'inactive' && item.is_active) return false;
    if (state.catalogType !== 'all' && item.type !== state.catalogType) return false;
    return true;
  }

  function filteredItems() {
    return cfg.items.filter(function (item) {
      return matchesTab(item) && matchesFilters(item);
    });
  }

  function syncUrl() {
    const p = new URLSearchParams();
    if (state.view && state.view !== 'besoiu') p.set('view', state.view);
    if (state.view === 'all' && state.type) p.set('type', state.type);
    if (state.q) p.set('q', state.q);
    const qs = p.toString();
    const newUrl = qs ? (window.location.pathname + '?' + qs) : window.location.pathname;
    history.replaceState({ cpWorkspace: true, state: Object.assign({}, state) }, '', newUrl);
  }

  function setFieldDisabled(el, disabled) {
    if (!el) return;
    el.disabled = disabled;
    const field = el.closest('.cp-workspace-filters__field');
    if (field) {
      field.classList.toggle('is-disabled', disabled);
    }
  }

  function updateFilterUi() {
    if (!filtersBar) return;
    const isTree = state.view === 'besoiu';
    filtersBar.classList.toggle('is-tree-mode', isTree);

    if (filterSource) {
      filterSource.hidden = false;
      setFieldDisabled(filterSource, isTree || state.view === 'other');
    }
    if (filterType) {
      filterType.hidden = false;
      setFieldDisabled(filterType, isTree || state.type !== '' || state.view === 'other');
    }
    if (filterStatus) {
      filterStatus.hidden = false;
      setFieldDisabled(filterStatus, isTree);
    }
    if (searchBox) {
      if (isTree) {
        searchBox.placeholder = 'Caută în arbore: denumire, ART_NAME, cale, ID…';
      } else if (state.type === 'marca') {
        searchBox.placeholder = 'Caută marcă: denumire, slug…';
      } else if (state.type === 'model') {
        searchBox.placeholder = 'Caută model vehicul: denumire, slug…';
      } else if (state.type === 'motorizare') {
        searchBox.placeholder = 'Caută motorizare: denumire, slug…';
      } else if (state.view === 'other') {
        searchBox.placeholder = 'Caută catalog alternativ: denumire, slug, sursă…';
      } else {
        searchBox.placeholder = 'Caută: denumire, slug, tip, sursă…';
      }
    }
  }

  function setActiveTab(view, type, pushPage) {
    state.view = view || 'besoiu';
    state.type = type || '';
    if (pushPage !== false) state.page = 1;

    if (tabsEl) {
      tabsEl.querySelectorAll('.cp-tab').forEach(function (btn) {
        const v = btn.getAttribute('data-view') || 'besoiu';
        const t = btn.getAttribute('data-type') || '';
        btn.classList.toggle('is-active', v === state.view && t === state.type);
      });
    }

    const showTree = state.view === 'besoiu';
    if (panelTree) panelTree.hidden = !showTree;
    if (panelTable) panelTable.hidden = showTree;

    updateFilterUi();
    syncUrl();

    if (showTree) {
      triggerTreeSearch();
    } else {
      renderTable();
    }
  }

  function triggerTreeSearch() {
    if (!searchBox) return;
    searchBox.dispatchEvent(new CustomEvent('cp-tree-search', {
      bubbles: true,
      detail: { query: searchBox.value.trim() },
    }));
  }

  function renderTable() {
    if (!tableBody) return;
    const items = filteredItems();
    const total = items.length;
    const totalPages = Math.max(1, Math.ceil(total / PER_PAGE));
    if (state.page > totalPages) state.page = totalPages;
    if (state.page < 1) state.page = 1;

    const start = (state.page - 1) * PER_PAGE;
    const pageItems = items.slice(start, start + PER_PAGE);

    let html = '';
    pageItems.forEach(function (item, idx) {
      const tc = typeClass(item.type);
      const iconHtml = item.icon
        ? '<img src="/' + esc(item.icon) + '" alt="" width="20" height="20" style="object-fit:contain;vertical-align:middle;">'
        : '<span style="color:var(--cp-muted)">—</span>';
      html += '<tr class="cat-row cp-row cp-row--' + esc(tc) + '" data-id="' + item.id + '" data-type="' + esc(item.type) + '">'
        + '<td class="cp-num">' + (start + idx + 1) + '</td>'
        + '<td class="cp-label">' + esc(item.label) + '</td>'
        + '<td class="cp-slug">' + esc(item.slug) + '</td>'
        + '<td><span class="cp-type cp-type--' + esc(tc) + '">' + esc(item.type_label) + '</span></td>'
        + '<td>' + sourceBadge(item.source) + '</td>'
        + '<td>' + iconHtml + '</td>'
        + '<td style="text-align:center">' + item.sort_order + '</td>'
        + '<td class="cp-parent" title="' + esc(item.parent_label) + '">' + esc(item.parent_label) + '</td>'
        + '<td style="text-align:center">'
        + '<button type="button" onclick="toggleCat(' + item.id + ', ' + (item.is_active ? 0 : 1) + ')" class="cp-toggle ' + (item.is_active ? 'is-on' : 'is-off') + '" aria-label="Comută vizibilitate"><span></span></button>'
        + '</td>'
        + '<td style="text-align:center;white-space:nowrap">'
        + '<button type="button" onclick="editCatFromId(' + item.id + ')" class="cp-icon-btn" title="Editează"><i data-lucide="pencil" class="h-4 w-4"></i></button>'
        + '<button type="button" onclick="deleteCat(' + item.id + ')" class="cp-icon-btn cp-icon-btn--danger" title="Șterge"><i data-lucide="trash-2" class="h-4 w-4"></i></button>'
        + '</td></tr>';
    });

    tableBody.innerHTML = html;

    if (tableEmpty) {
      tableEmpty.hidden = total > 0;
    }

    if (paginationEl) {
      if (totalPages <= 1) {
        paginationEl.hidden = true;
        paginationEl.innerHTML = '';
      } else {
        paginationEl.hidden = false;
        let links = '<span>' + (start + 1) + '–' + Math.min(start + PER_PAGE, total) + ' din ' + total + '</span><div class="cp-pagination__links">';
        if (state.page > 1) links += '<button type="button" class="cp-page-btn" data-page="' + (state.page - 1) + '">‹</button>';
        for (let p = Math.max(1, state.page - 2); p <= Math.min(totalPages, state.page + 2); p++) {
          links += '<button type="button" class="cp-page-btn' + (p === state.page ? ' is-current' : '') + '" data-page="' + p + '">' + p + '</button>';
        }
        if (state.page < totalPages) links += '<button type="button" class="cp-page-btn" data-page="' + (state.page + 1) + '">›</button>';
        links += '</div>';
        paginationEl.innerHTML = links;
      }
    }

    if (window.lucide && typeof window.lucide.createIcons === 'function') {
      window.lucide.createIcons({ nodes: tableBody.querySelectorAll('[data-lucide]') });
    }
  }

  if (tabsEl) {
    tabsEl.addEventListener('click', function (e) {
      const btn = e.target.closest('.cp-tab');
      if (!btn || !tabsEl.contains(btn)) return;
      e.preventDefault();
      setActiveTab(btn.getAttribute('data-view') || 'besoiu', btn.getAttribute('data-type') || '');
    });
  }

  if (paginationEl) {
    paginationEl.addEventListener('click', function (e) {
      const btn = e.target.closest('.cp-page-btn');
      if (!btn) return;
      state.page = parseInt(btn.getAttribute('data-page') || '1', 10) || 1;
      renderTable();
    });
  }

  let searchTimer;
  if (searchBox) {
    searchBox.addEventListener('input', function () {
      clearTimeout(searchTimer);
      searchTimer = setTimeout(function () {
        state.q = searchBox.value.trim();
        state.page = 1;
        syncUrl();
        if (state.view === 'besoiu') {
          triggerTreeSearch();
        } else {
          renderTable();
        }
      }, 180);
    });
  }

  [filterSource, filterStatus, filterType].forEach(function (el) {
    if (!el) return;
    el.addEventListener('change', function () {
      if (el === filterSource) state.source = el.value || 'all';
      if (el === filterStatus) state.status = el.value || 'all';
      if (el === filterType) state.catalogType = el.value || 'all';
      state.page = 1;
      renderTable();
    });
  });

  window.addEventListener('popstate', function (e) {
    if (e.state && e.state.cpWorkspace && e.state.state) {
      Object.assign(state, e.state.state);
      if (searchBox) searchBox.value = state.q;
      setActiveTab(state.view, state.type, false);
      return;
    }
    const p = new URLSearchParams(window.location.search);
    state.view = p.get('view') || 'besoiu';
    state.type = p.get('type') || '';
    state.q = p.get('q') || '';
    state.page = 1;
    if (searchBox) searchBox.value = state.q;
    setActiveTab(state.view, state.type, false);
  });

  window.CP_WORKSPACE_API = {
    refresh: function () {
      if (state.view === 'besoiu') triggerTreeSearch();
      else renderTable();
    },
    setTab: setActiveTab,
  };

  setActiveTab(state.view, state.type, false);
})();
