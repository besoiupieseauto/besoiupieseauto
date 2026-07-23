/**
 * Dashboard creativ per zonă de lucru — grafice, tabele, KPI.
 */
(function () {
  'use strict';

  const root = document.querySelector('.besoiu-dashboard[data-besoiu-dash-workspace]');
  if (!root || root.getAttribute('data-besoiu-dash-full') === '1') {
    return;
  }

  const workspace = root.getAttribute('data-besoiu-dash-workspace') || 'orders';

  function esc(s) {
    return String(s ?? '').replace(/[&<>"']/g, (c) => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
    }[c]));
  }

  function fmtNum(n) {
    return Number(n || 0).toLocaleString('ro-RO');
  }

  function fmtMoney(n) {
    return Number(n || 0).toLocaleString('ro-RO', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' RON';
  }

  function fmtShortDay(day) {
    if (!day) return '—';
    const d = new Date(String(day).replace(' ', 'T'));
    if (Number.isNaN(d.getTime())) return String(day).slice(5);
    return d.toLocaleDateString('ro-RO', { day: '2-digit', month: 'short' });
  }

  function fmtDateTime(v) {
    if (!v) return '—';
    const d = new Date(String(v).replace(' ', 'T'));
    if (Number.isNaN(d.getTime())) return String(v);
    return d.toLocaleString('ro-RO', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
  }

  function statusPill(status) {
    const s = String(status || '').toLowerCase();
    let cls = 'bws-pill';
    if (s === 'noua' || s === 'new' || s === 'pending') cls += ' bws-pill--warn';
    else if (s === 'livrat' || s === 'done' || s === 'handled' || s === 'sent') cls += ' bws-pill--ok';
    else if (s === 'failed' || s === 'needs_human') cls += ' bws-pill--danger';
    else cls += ' bws-pill--info';
    return `<span class="${cls}">${esc(status || '—')}</span>`;
  }

  function renderSparkline(el, values) {
    if (!el || !values.length) return;
    const max = Math.max(...values, 1);
    el.innerHTML = values.map((v) => {
      const h = Math.max(8, Math.round((v / max) * 100));
      return `<span style="height:${h}%"></span>`;
    }).join('');
  }

  function renderBars(container, rows, valueKey, labelKey) {
    if (!container) return;
    if (!rows.length) {
      container.innerHTML = '<div class="bws-empty">Nu există date pentru perioada selectată.</div>';
      return;
    }
    const max = Math.max(...rows.map((r) => Number(r[valueKey] || 0)), 1);
    container.innerHTML = `<div class="bws-bars" role="img">${rows.map((row) => {
      const val = Number(row[valueKey] || 0);
      const pct = Math.max(4, Math.round((val / max) * 100));
      return `<div class="bws-bars__col">
        <span class="bws-bars__value">${esc(fmtNum(val))}</span>
        <div class="bws-bars__bar-wrap"><div class="bws-bars__bar" style="height:${pct}%"></div></div>
        <span class="bws-bars__label">${esc(fmtShortDay(row[labelKey]))}</span>
      </div>`;
    }).join('')}</div>`;
  }

  function renderArea(container, rows, valueKey) {
    if (!container) return;
    if (!rows.length) {
      container.innerHTML = '<div class="bws-empty">Fără venituri înregistrate.</div>';
      return;
    }
    const vals = rows.map((r) => Number(r[valueKey] || 0));
    const max = Math.max(...vals, 1);
    const w = 400;
    const h = 120;
    const step = w / Math.max(vals.length - 1, 1);
    const points = vals.map((v, i) => {
      const x = i * step;
      const y = h - (v / max) * (h - 8) - 4;
      return `${x},${y}`;
    });
    const areaPoints = `0,${h} ${points.join(' ')} ${w},${h}`;
    container.innerHTML = `<div class="bws-area-chart"><svg viewBox="0 0 ${w} ${h}" preserveAspectRatio="none">
      <defs><linearGradient id="bwsAreaGrad" x1="0" y1="0" x2="0" y2="1">
        <stop offset="0%" stop-color="var(--bws-accent)"/>
        <stop offset="100%" stop-color="var(--bws-accent)" stop-opacity="0"/>
      </linearGradient></defs>
      <polygon class="bws-area-chart__fill" points="${areaPoints}"/>
      <polyline class="bws-area-chart__line" points="${points.join(' ')}"/>
    </svg></div>`;
  }

  function renderDonut(container, segments) {
    if (!container) return;
    const total = segments.reduce((s, x) => s + x.value, 0) || 1;
    const colors = ['var(--bws-accent)', 'var(--bws-accent2)', '#94a3b8', '#f59e0b', '#ec4899', '#38bdf8'];
    let offset = 0;
    const r = 42;
    const c = 2 * Math.PI * r;
    const arcs = segments.map((seg, i) => {
      const pct = seg.value / total;
      const dash = pct * c;
      const circle = `<circle cx="50" cy="50" r="${r}" fill="none" stroke="${colors[i % colors.length]}"
        stroke-width="14" stroke-dasharray="${dash} ${c - dash}" stroke-dashoffset="${-offset}" />`;
      offset += dash;
      return circle;
    }).join('');
    const legend = segments.map((seg, i) => `
      <div class="bws-legend__item">
        <span class="bws-legend__dot" style="background:${colors[i % colors.length]}"></span>
        <span class="bws-legend__label">${esc(seg.label)}</span>
        <span class="bws-legend__val">${fmtNum(seg.value)}</span>
      </div>`).join('');
    container.innerHTML = `<div class="bws-donut-row">
      <div class="bws-donut"><svg viewBox="0 0 100 100">${arcs}</svg>
        <div class="bws-donut__center"><strong>${fmtNum(total)}</strong><span>total</span></div>
      </div>
      <div class="bws-legend">${legend}</div>
    </div>`;
  }

  function renderTable(container, rows, columns) {
    if (!container) return;
    if (!rows.length) {
      container.innerHTML = '<div class="bws-empty">Nicio înregistrare recentă.</div>';
      return;
    }
    const head = columns.map((c) => `<th>${esc(c.label)}</th>`).join('');
    const body = rows.map((row, rowIdx) => `<tr>${columns.map((c) => {
      const raw = typeof c.render === 'function' ? c.render(row, rowIdx) : row[c.key];
      return `<td>${raw ?? '—'}</td>`;
    }).join('')}</tr>`).join('');
    container.innerHTML = `<div class="bws-table-wrap"><table class="bws-table"><thead><tr>${head}</tr></thead><tbody>${body}</tbody></table></div>`;
  }

  function setMetric(id, value, hint) {
    const v = document.getElementById(id);
    const h = document.getElementById(id + '-hint');
    if (v) v.textContent = value;
    if (h && hint !== undefined) h.textContent = hint;
  }

  function setText(id, value) {
    const node = document.getElementById(id);
    if (node) node.textContent = value;
  }

    function renderOrdersFurnizori(data) {
    const fz = data.furnizori || {};
    setMetric('bws-fz-active', fmtNum(fz.active), 'din ' + fmtNum(fz.total) + ' total');
    setMetric('bws-fz-failed', fmtNum(fz.failed_tests), 'scan eșuate: ' + fmtNum(fz.failed_scans || 0));
    setMetric('bws-fz-products', fmtNum(fz.products_total), 'produse în feed-uri');

    renderTable(document.getElementById('bws-table-furnizori'), fz.recent || [], [
      { label: 'Furnizor', key: 'name', render: (r) => `<strong>${esc(r.name)}</strong><br><span class="opacity-60 text-xs">${esc(r.code)}</span>` },
      { label: 'Conexiune', key: 'connection', render: (r) => esc(String(r.connection || '—').toUpperCase()) },
      { label: 'Produse', key: 'products', render: (r) => esc(fmtNum(r.products)) },
      { label: 'Status', key: 'status', render: (r) => statusPill(r.status) },
      { label: 'Ultim scan', key: 'last_scan', render: (r) => esc(fmtDateTime(r.last_scan)) },
      { label: '', key: 'url', render: (r) => `<a class="bws-table__link" href="${esc(r.url || '/admin/furnizori')}">Profil</a>` },
    ]);
  }

  function renderOrders(data) {
    const o = data.orders || {};
    setMetric('bws-m-orders-today', fmtNum(o.today_new), 'Comenzi create astăzi');
    setMetric('bws-m-revenue-today', fmtMoney(o.today_revenue), 'Valoare totală azi');
    setMetric('bws-m-orders-total', fmtNum(o.total), 'În baza de date');
    setMetric('bws-m-orders-new', fmtNum(o.new_orders), 'Status „nouă”');

    renderSparkline(document.getElementById('bws-spark-orders'), (o.daily_trend || []).map((x) => x.count));
    renderSparkline(document.getElementById('bws-spark-revenue'), (o.revenue_trend || []).map((x) => x.revenue));

    renderBars(document.getElementById('bws-chart-orders-bars'), o.daily_trend || [], 'count', 'day');
    renderArea(document.getElementById('bws-chart-revenue-area'), o.revenue_trend || [], 'revenue');

    const statusSeg = Object.entries(o.by_status || {}).map(([label, value]) => ({
      label: label.replace(/_/g, ' '),
      value: Number(value),
    })).filter((s) => s.value > 0);
    renderDonut(document.getElementById('bws-chart-status-donut'), statusSeg.length ? statusSeg : [{ label: 'Fără date', value: 1 }]);

    renderTable(document.getElementById('bws-table-orders'), o.recent || [], [
      { label: 'Data', key: 'time', render: (r) => esc(fmtDateTime(r.time)) },
      { label: 'Client', key: 'client', render: (r) => esc(r.client) },
      { label: 'Canal', key: 'channel', render: (r) => esc(r.channel) },
      { label: 'Status', key: 'status', render: (r) => statusPill(r.status) },
      { label: 'Sumă', key: 'amount', render: (r) => esc(fmtMoney(r.amount)) },
      { label: '', key: 'url', render: (r) => `<a class="bws-table__link" href="${esc(r.url || '/admin/orders')}">Vezi</a>` },
    ]);
    renderOrdersFurnizori(data);
  }

  function renderSuppliers(data) {
    const p = data.products || {};
    const imp = data.import || {};
    const search = data.search_logs || {};

    setMetric('bws-m-prod-total', fmtNum(p.total), 'În catalog');
    setMetric('bws-m-prod-active', fmtNum(p.active), 'Publicate / active');
    setMetric('bws-m-queue', fmtNum(p.queue_pending), 'În coada import');
    setMetric('bws-m-no-image', fmtNum(p.no_image), 'Necesită imagine');

    const importSeg = [
      { label: 'Joburi active', value: Number(imp.running_jobs || 0) },
      { label: 'Eșuate', value: Number(imp.failed_jobs || 0) },
      { label: 'Blocate', value: Number(imp.blocked_jobs || 0) },
    ].filter((s) => s.value > 0);
    renderDonut(document.getElementById('bws-chart-import-donut'), importSeg.length ? importSeg : [{ label: 'Fără joburi', value: 1 }]);

    const qualSeg = [
      { label: 'Fără imagine', value: Number(p.no_image || 0) },
      { label: 'Fără OEM', value: Number(p.no_oem || 0) },
      { label: 'OK', value: Math.max(0, Number(p.active || 0) - Number(p.no_image || 0) - Number(p.no_oem || 0)) },
    ].filter((s) => s.value > 0);
    renderDonut(document.getElementById('bws-chart-quality-donut'), qualSeg.length ? qualSeg : [{ label: 'Catalog', value: Number(p.active || 1) }]);

    const missing = (search.top_missing || []).map((row) => ({
      code: row.code || row.term || row.query || '—',
      count: row.count || row.hits || row.total || 0,
      type: row.type || 'oem',
    }));
    renderTable(document.getElementById('bws-table-missing'), missing, [
      { label: 'Cod', key: 'code', render: (r) => `<strong>${esc(r.code)}</strong>` },
      { label: 'Tip', key: 'type', render: (r) => esc(String(r.type).toUpperCase()) },
      { label: 'Căutări', key: 'count', render: (r) => esc(fmtNum(r.count)) },
    ]);

    const flags = document.getElementById('bws-supplier-flags');
    if (flags) {
      const rf = data.red_flags || [];
      flags.innerHTML = rf.length
        ? rf.slice(0, 5).map((f) => `<div class="bws-health-item"><strong>${esc(f.title || 'Alertă')}</strong><span>${esc(f.detail || '')}</span></div>`).join('')
        : '<div class="bws-empty">Nicio alertă critică — feed-uri și TecDoc OK.</div>';
    }
  }

  function renderMarketing(data) {
    const s = data.search_logs || {};
    setMetric('bws-m-search-total', fmtNum(s.total), 'În jurnal');
    setMetric('bws-m-search-today', fmtNum(s.today), 'Astăzi pe site');
    setMetric('bws-m-not-found', fmtNum(s.not_found), 'Fără rezultat în stoc');
    const rate = s.total > 0 ? Math.round(((s.total - s.not_found) / s.total) * 100) : 0;
    setMetric('bws-m-success-rate', rate + '%', 'Rată găsite');
    setText('bws-m-success-rate-inline', rate + '%');

    renderBars(document.getElementById('bws-chart-search-bars'), (s.daily_trend || []).map((row) => ({
      day: row.day,
      count: Number(row.found || 0) + Number(row.not_found || 0),
    })), 'count', 'day');

    const trendRows = (s.daily_trend || []).map((row) => ({
      day: row.day,
      found: Number(row.found || 0),
      missing: Number(row.not_found || 0),
    }));
    const trendEl = document.getElementById('bws-chart-search-split');
    if (trendEl && trendRows.length) {
      const max = Math.max(...trendRows.map((r) => r.found + r.missing), 1);
      trendEl.innerHTML = `<div class="bws-bars">${trendRows.map((row) => {
        const total = row.found + row.missing;
        const h = Math.max(4, Math.round((total / max) * 100));
        const fPct = total > 0 ? Math.round((row.found / total) * 100) : 0;
        return `<div class="bws-bars__col">
          <span class="bws-bars__value">${esc(fmtNum(total))}</span>
          <div class="bws-bars__bar-wrap"><div class="bws-bars__bar" style="height:${h}%;background:linear-gradient(180deg,#34d399 ${fPct}%,#fbbf24 ${fPct}%)"></div></div>
          <span class="bws-bars__label">${esc(fmtShortDay(row.day))}</span>
        </div>`;
      }).join('')}</div>`;
    }

    renderTable(document.getElementById('bws-table-oem'), (s.top_oem || []).slice(0, 10), [
      { label: '#', key: 'i', render: (_, i) => esc(i + 1) },
      { label: 'Cod OEM', key: 'code', render: (r) => `<strong>${esc(r.code || r.term || '—')}</strong>` },
      { label: 'Căutări', key: 'count', render: (r) => esc(fmtNum(r.count || r.hits || 0)) },
    ]);
  }

  function renderAi(data) {
    const bots = data.bots || [];
    const health = data.health || {};
    setMetric('bws-m-bots', fmtNum(bots.length), 'Boți configurați');
    setMetric('bws-m-flags', fmtNum((data.red_flags || []).length), 'Alerte active');
    setMetric('bws-m-tecdoc', health.tecdoc_online ? 'Online' : 'Offline', 'API TecDoc');
    setMetric('bws-m-backup', health.latest_backup_at ? fmtDateTime(health.latest_backup_at) : '—', 'Ultimul backup');

    renderTable(document.getElementById('bws-table-bots'), bots, [
      { label: 'Bot', key: 'name', render: (r) => esc(r.name) },
      { label: 'Canal', key: 'channel', render: (r) => esc(r.channel) },
      { label: 'Token', key: 'token_status', render: (r) => statusPill(r.token_status) },
      { label: 'Test', key: 'last_test_status', render: (r) => statusPill(r.last_test_status || '—') },
    ]);

    const flags = document.getElementById('bws-ai-flags');
    if (flags) {
      const rf = data.red_flags || [];
      flags.innerHTML = rf.length
        ? rf.map((f) => `<div class="bws-health-item" style="text-align:left"><strong>${esc(f.title)}</strong><span>${esc(f.detail || f.message || '')}</span></div>`).join('')
        : '<div class="bws-empty">Sistem stabil — fără alerte critice.</div>';
    }
  }

  function renderSocial(data) {
    const m = data.messages_hub || {};
    setMetric('bws-m-msg-total', fmtNum(m.total), 'Mesaje în sistem');
    const channels = m.by_channel || {};
    const topCh = Object.entries(channels).sort((a, b) => b[1] - a[1])[0];
    setMetric('bws-m-top-channel', topCh ? topCh[0] : '—', topCh ? fmtNum(topCh[1]) + ' mesaje' : '');
    setText('bws-m-msg-total-dup', fmtNum(m.total));
    setText('bws-m-top-channel-dup', topCh ? topCh[0] : '—');

    const chSeg = Object.entries(channels).map(([label, value]) => ({ label, value: Number(value) }));
    renderDonut(document.getElementById('bws-chart-channels'), chSeg.length ? chSeg : [{ label: 'Fără mesaje', value: 1 }]);

    renderTable(document.getElementById('bws-table-messages'), m.recent || [], [
      { label: 'Data', key: 'time', render: (r) => esc(fmtDateTime(r.time)) },
      { label: 'Contact', key: 'name', render: (r) => esc(r.name) },
      { label: 'Canal', key: 'channel', render: (r) => esc(r.channel) },
      { label: 'Status', key: 'status', render: (r) => statusPill(r.status) },
      { label: 'Preview', key: 'preview', render: (r) => esc(r.preview || '—') },
    ]);
  }

  function renderShop(data) {
    const w = data.website || {};
    const p = data.products || {};
    setMetric('bws-m-pages', fmtNum(w.pages), 'Pagini CMS');
    setMetric('bws-m-vitrina', fmtNum(w.vitrina), 'Produse vitrină');
    setMetric('bws-m-blog', fmtNum(w.blog_posts), 'Articole blog');
    setMetric('bws-m-products', fmtNum(w.products_active || p.active), 'Produse active site');

    const seg = [
      { label: 'Pagini CMS', value: Number(w.pages || 0) },
      { label: 'Vitrină', value: Number(w.vitrina || 0) },
      { label: 'Blog', value: Number(w.blog_posts || 0) },
      { label: 'Produse', value: Number(w.products_active || p.active || 0) },
    ].filter((s) => s.value > 0);
    renderDonut(document.getElementById('bws-chart-shop-mix'), seg.length ? seg : [{ label: 'Conținut', value: 1 }]);
  }

  const renderers = {
    orders: renderOrders,
    suppliers: renderSuppliers,
    marketing: renderMarketing,
    ai: renderAi,
    social: renderSocial,
    shop: renderShop,
  };

  function applyUserContext(user, metrics) {
    if (!user) return;
    const intro = document.querySelector('.bws-deck__intro');
    if (intro && user.name) {
      const base = intro.textContent || '';
      if (!base.includes(user.name)) {
        intro.innerHTML = '<span class="bws-user-line">Salut, <strong>' + esc(user.name) + '</strong> — date pentru contul tău</span>' + base;
      }
    }
    if (metrics && workspace === 'suppliers') {
      setMetric('bws-m-prod-total', fmtNum(metrics.products_owned?.total), 'din ' + fmtNum(metrics.products_org_total) + ' în organizație');
    }
  }

  window.BwsWorkspaceDash = {
    render(workspaceId, data, user, metrics) {
      const fn = renderers[workspaceId];
      if (fn) fn(data || {});
      applyUserContext(user || data?.current_user, metrics || data?.user_metrics);
    },
  };

  function hookDashboard() {
    const orig = window.__besoiuRenderDashboard;
    window.__besoiuRenderDashboard = function (data) {
      if (typeof orig === 'function') orig(data);
      window.BwsWorkspaceDash.render(workspace, data);
    };
  }

  hookDashboard();

  if (window.__besoiuDashboardData) {
    window.BwsWorkspaceDash.render(workspace, window.__besoiuDashboardData);
  }
})();
