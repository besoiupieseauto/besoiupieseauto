/**
 * Company Settings — Command Center tabbed dashboard
 */
(function () {
  'use strict';

  const shell = document.getElementById('bcd-shell');
  if (!shell) return;

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

  function setText(id, value) {
    const el = document.getElementById(id);
    if (el) el.textContent = value;
  }

  function statusPill(status) {
    const s = String(status || '').toLowerCase();
    let cls = 'bws-pill';
    if (s === 'noua' || s === 'new' || s === 'pending') cls += ' bws-pill--warn';
    else if (s === 'livrat' || s === 'done' || s === 'handled' || s === 'sent' || s === 'ok') cls += ' bws-pill--ok';
    else if (s === 'failed' || s === 'needs_human' || s === 'error') cls += ' bws-pill--danger';
    else cls += ' bws-pill--info';
    return `<span class="${cls}">${esc(status || '—')}</span>`;
  }

  function renderSparkline(el, values) {
    if (!el || !values.length) return;
    const max = Math.max(...values, 1);
    el.innerHTML = values.map((v) => {
      const h = Math.max(6, Math.round((v / max) * 100));
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
    const gradId = 'bcdAreaGrad' + Math.random().toString(36).slice(2, 8);
    container.innerHTML = `<div class="bws-area-chart"><svg viewBox="0 0 ${w} ${h}" preserveAspectRatio="none">
      <defs><linearGradient id="${gradId}" x1="0" y1="0" x2="0" y2="1">
        <stop offset="0%" stop-color="var(--bws-accent)"/>
        <stop offset="100%" stop-color="var(--bws-accent)" stop-opacity="0"/>
      </linearGradient></defs>
      <polygon class="bws-area-chart__fill" points="${areaPoints}" fill="url(#${gradId})"/>
      <polyline class="bws-area-chart__line" points="${points.join(' ')}"/>
    </svg></div>`;
  }

  function renderDonut(container, segments) {
    if (!container) return;
    const filtered = segments.filter((s) => s.value > 0);
    const segs = filtered.length ? filtered : [{ label: 'Fără date', value: 1 }];
    const total = segs.reduce((s, x) => s + x.value, 0) || 1;
    const colors = ['var(--bws-accent)', 'var(--bws-accent2)', '#94a3b8', '#f59e0b', '#ec4899', '#38bdf8', '#ef4444'];
    let offset = 0;
    const r = 42;
    const c = 2 * Math.PI * r;
    const arcs = segs.map((seg, i) => {
      const pct = seg.value / total;
      const dash = pct * c;
      const circle = `<circle cx="50" cy="50" r="${r}" fill="none" stroke="${colors[i % colors.length]}"
        stroke-width="14" stroke-dasharray="${dash} ${c - dash}" stroke-dashoffset="${-offset}" />`;
      offset += dash;
      return circle;
    }).join('');
    const legend = segs.map((seg, i) => `
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
      container.innerHTML = '<div class="bws-empty">Nicio înregistrare.</div>';
      return;
    }
    const head = columns.map((c) => `<th>${esc(c.label)}</th>`).join('');
    const body = rows.map((row) => `<tr>${columns.map((c) => {
      const raw = typeof c.render === 'function' ? c.render(row) : row[c.key];
      return `<td>${raw ?? '—'}</td>`;
    }).join('')}</tr>`).join('');
    container.innerHTML = `<div class="bws-table-wrap"><table class="bws-table"><thead><tr>${head}</tr></thead><tbody>${body}</tbody></table></div>`;
  }

  function flagSeverityClass(flag) {
    const s = String(flag.level || flag.severity || '').toLowerCase();
    if (s === 'danger' || s === 'critical') return '';
    if (s === 'warning' || s === 'warn') return 'bcd-flag--warn';
    return 'bcd-flag--info';
  }

  function flagIcon(flag) {
    const s = String(flag.level || flag.severity || '').toLowerCase();
    if (s === 'danger' || s === 'critical') return '⚠';
    if (s === 'warning' || s === 'warn') return '◆';
    return 'ℹ';
  }

  function renderRedFlags(container, flags) {
    if (!container) return;
    const list = Array.isArray(flags) ? flags : [];
    const badge = document.getElementById('bcd-flags-badge');
    const critical = list.filter((f) => f.critical || f.level === 'danger').length;
    if (badge) {
      badge.textContent = critical > 0 ? critical + ' critice' : String(list.length);
      badge.className = 'bcd-badge' + (list.length ? ' bcd-badge--danger' : ' bcd-badge--ok');
    }
    setText('bcd-ov-flags-count', fmtNum(critical || list.length));

    if (!list.length) {
      container.innerHTML = '<div class="bws-empty bws-empty--ok">✓ Nicio alertă critică — totul arată bine.</div>';
      return;
    }

    container.innerHTML = list.map((f) => `
      <article class="bcd-flag ${flagSeverityClass(f)}">
        <div class="bcd-flag__icon" aria-hidden="true">${flagIcon(f)}</div>
        <div class="bcd-flag__body">
          <strong>${esc(f.title || 'Alertă')}${f.critical ? ' <span class="bcd-badge bcd-badge--danger" style="font-size:0.65rem;margin-left:6px">CRITIC</span>' : ''}</strong>
          <p>${esc(f.detail || f.message || '')}</p>
        </div>
        ${f.url ? `<a href="${esc(f.url)}" class="bcd-flag__action">Detalii →</a>` : ''}
      </article>
    `).join('');
  }

  function renderTopMissing(container, items) {
    if (!container) return;
    if (!items.length) {
      container.innerHTML = '<div class="bws-empty">Nicio căutare negăsită recent.</div>';
      return;
    }
    container.innerHTML = items.map((item) => `
      <div class="bcd-missing-item">
        <div><code>${esc(item.query_value || '—')}</code> <span class="opacity-60">${esc(item.query_type || '')}</span></div>
        <strong>${fmtNum(item.attempts || 0)}×</strong>
      </div>
    `).join('');
  }

  function renderHealthGrid(container, items) {
    if (!container) return;
    container.innerHTML = items.map((item) => `
      <div class="bcd-health-item ${item.cls || ''}">
        <strong>${esc(item.value)}</strong>
        <span>${esc(item.label)}</span>
      </div>
    `).join('');
  }

  function renderTecdoc(health) {
    const ip = health.tecdoc_ip || {};
    const isValid = Boolean(ip.ip_valid);
    const online = Boolean(health.tecdoc_online);

    setText('bcd-sys-tecdoc-status', online ? (isValid ? 'Online' : 'IP invalid') : 'Indisponibil');
    const metric = document.getElementById('bcd-sys-tecdoc-metric');
    if (metric) metric.classList.toggle('bcd-metric--danger', !online || !isValid);

    const badge = document.getElementById('bcd-tecdoc-badge');
    if (badge) {
      badge.textContent = isValid ? 'IP valid' : 'IP invalid';
      badge.className = 'bcd-badge ' + (isValid ? 'bcd-badge--ok' : 'bcd-badge--danger');
    }
    const dot = document.getElementById('bcd-tecdoc-dot');
    if (dot) dot.className = 'bcd-tecdoc__dot ' + (isValid ? 'is-valid' : 'is-invalid');
    setText('bcd-tecdoc-label', isValid ? 'Conexiune TecDoc validă' : 'Verificare IP eșuată');
    setText('bcd-tecdoc-ip', ip.server_ip || 'necunoscut');
    setText('bcd-tecdoc-detail', ip.checked_at
      ? 'Verificat ' + fmtDateTime(ip.checked_at) + (ip.operator_message ? ' · ' + ip.operator_message : '')
      : 'Se contactează API TecDoc...');
  }

  function renderImportJob(container, job) {
    if (!container) return;
    if (!job || !job.job_id) {
      container.innerHTML = '<div class="bws-empty">Niciun job import recent.</div>';
      return;
    }
    container.innerHTML = `<div class="bcd-job-card"><dl>
      <dt>Job ID</dt><dd>${esc(job.job_id)}</dd>
      <dt>Tip</dt><dd>${esc(job.type || '—')}</dd>
      <dt>Status</dt><dd>${statusPill(job.status)}</dd>
      <dt>Actualizat</dt><dd>${esc(fmtDateTime(job.updated_at))}</dd>
      <dt>Mesaj</dt><dd>${esc(job.message || job.error || '—')}</dd>
    </dl></div>`;
  }

  function renderWebsiteStats(container, web) {
    if (!container) return;
    const rows = [
      { label: 'Pagini site', value: fmtNum(web.pages) },
      { label: 'Articole blog', value: fmtNum(web.blog_posts) },
      { label: 'Produse vitrină', value: fmtNum(web.vitrina) },
      { label: 'Produse active', value: fmtNum(web.products_active) },
    ];
    container.innerHTML = `<div class="bcd-stat-list">${rows.map((r) => `
      <div class="bcd-stat-row"><span>${esc(r.label)}</span><strong>${esc(r.value)}</strong></div>
    `).join('')}</div>`;
  }

  function renderMissingTypes(container, search) {
    if (!container) return;
    const segments = [
      { label: 'VIN negăsite', value: Number(search.vin_not_found || 0) },
      { label: 'OEM negăsite', value: Number(search.oem_not_found || 0) },
      { label: 'Altele', value: Math.max(0, Number(search.not_found || 0) - Number(search.vin_not_found || 0) - Number(search.oem_not_found || 0)) },
    ];
    renderDonut(container, segments);
  }

  function syncMissingWidget(search) {
    const widget = document.getElementById('bcd-missing-widget');
    if (!widget) return;
    const disabled = !search.available || Number(search.missing_codes_count ?? 0) <= 0;
    widget.classList.toggle('is-disabled', disabled);
    widget.setAttribute('aria-disabled', disabled ? 'true' : 'false');
  }

  function renderOverview(data) {
    const o = data.orders || {};
    const s = data.search_logs || {};
    const p = data.products || {};
    const m = data.messages_hub || {};
    const flags = data.red_flags || [];

    setText('bcd-ov-orders-today', fmtNum(o.today_new));
    setText('bcd-ov-orders-today-hint', '+' + fmtNum(o.new_orders) + ' noi în total');
    setText('bcd-ov-revenue-today', fmtMoney(o.today_revenue));
    setText('bcd-ov-searches-today', fmtNum(s.today));
    const rate = s.total > 0 ? Math.round(((s.total - s.not_found) / s.total) * 100) : 0;
    setText('bcd-ov-search-rate', rate + '% rată succes');
    setText('bcd-ov-prod-active', fmtNum(p.active));
    setText('bcd-ov-prod-total', fmtNum(p.total));
    setText('bcd-ov-messages-open', fmtNum(m.total || 0));

    renderSparkline(document.getElementById('bcd-ov-spark-orders'), (o.daily_trend || []).map((x) => x.count));
    renderSparkline(document.getElementById('bcd-ov-spark-revenue'), (o.revenue_trend || []).map((x) => x.revenue));

    renderRedFlags(document.getElementById('bcd-red-flags-list'), flags);
    renderTable(document.getElementById('bcd-activity-table'), (data.activity?.items || []).slice(0, 8), [
      { label: 'Data', render: (r) => esc(fmtDateTime(r.time || r.created_at)) },
      { label: 'Titlu', render: (r) => esc(r.title || r.subject || '—') },
      { label: 'Canal', render: (r) => esc(r.channel || r.type || '—') },
      { label: 'Status', render: (r) => statusPill(r.status) },
    ]);
    renderTopMissing(document.getElementById('bcd-top-missing'), s.top_missing || []);
  }

  function renderOrdersTab(data) {
    const o = data.orders || {};
    setText('bcd-ord-total', fmtNum(o.total));
    setText('bcd-ord-new', fmtNum(o.new_orders));
    setText('bcd-ord-today', fmtNum(o.today_new));
    setText('bcd-ord-revenue', fmtMoney(o.today_revenue));

    renderBars(document.getElementById('bcd-chart-orders-bars'), o.daily_trend || [], 'count', 'day');
    renderArea(document.getElementById('bcd-chart-revenue-area'), o.revenue_trend || [], 'revenue');

    const statusSeg = Object.entries(o.by_status || {}).map(([label, value]) => ({
      label: label.replace(/_/g, ' '),
      value: Number(value),
    }));
    renderDonut(document.getElementById('bcd-chart-status-donut'), statusSeg);

    renderTable(document.getElementById('bcd-table-orders'), o.recent || [], [
      { label: 'Data', render: (r) => esc(fmtDateTime(r.time)) },
      { label: 'Client', render: (r) => esc(r.client) },
      { label: 'Canal', render: (r) => esc(r.channel) },
      { label: 'Status', render: (r) => statusPill(r.status) },
      { label: 'Sumă', render: (r) => esc(fmtMoney(r.amount)) },
    ]);
  }

  function renderCatalogTab(data) {
    const p = data.products || {};
    const imp = data.import || {};
    const oem = p.oem_index || {};

    setText('bcd-cat-total', fmtNum(p.total));
    setText('bcd-cat-active', fmtNum(p.active));
    setText('bcd-cat-no-image', fmtNum(p.no_image));
    setText('bcd-cat-no-oem', fmtNum(p.no_oem));
    setText('bcd-cat-queue', fmtNum(p.queue_pending));
    setText('bcd-cat-oem-products', fmtNum(oem.products));
    setText('bcd-cat-oem-codes', fmtNum(oem.codes));

    renderDonut(document.getElementById('bcd-chart-quality-donut'), [
      { label: 'Cu imagine', value: Math.max(0, Number(p.active) - Number(p.no_image)) },
      { label: 'Fără imagine', value: Number(p.no_image) },
      { label: 'Fără OEM', value: Number(p.no_oem) },
    ]);

    renderDonut(document.getElementById('bcd-chart-import-donut'), [
      { label: 'Running', value: Number(imp.running_jobs) },
      { label: 'Eșuate', value: Number(imp.failed_jobs) },
      { label: 'Blocaje', value: Number(imp.blocked_jobs) },
    ]);

    renderImportJob(document.getElementById('bcd-import-last-job'), imp.last_job);
  }

  function renderSearchTab(data) {
    const s = data.search_logs || {};
    const rate = s.total > 0 ? Math.round(((s.total - s.not_found) / s.total) * 100) : 0;

    setText('bcd-srch-total', fmtNum(s.total));
    setText('bcd-srch-found', fmtNum(s.found));
    setText('bcd-srch-not-found', fmtNum(s.not_found));
    setText('bcd-srch-missing-codes', fmtNum(s.missing_codes_count) + ' coduri unice');
    setText('bcd-srch-rate', rate + '%');
    syncMissingWidget(s);

    const trend = (s.daily_trend || []).map((row) => ({
      day: row.day,
      count: Number(row.total ?? row.count ?? 0),
    }));
    renderBars(document.getElementById('bcd-chart-search-bars'), trend, 'count', 'day');

    renderDonut(document.getElementById('bcd-chart-search-donut'), [
      { label: 'Cu rezultat', value: Number(s.found) },
      { label: 'Negăsite', value: Number(s.not_found) },
    ]);
    renderMissingTypes(document.getElementById('bcd-chart-missing-types'), s);

    renderTable(document.getElementById('bcd-table-top-oem'), s.top_oem || [], [
      { label: 'OEM', render: (r) => `<code>${esc(r.oem || r.query_value || '—')}</code>` },
      { label: 'Căutări', render: (r) => esc(fmtNum(r.count || r.attempts)) },
      { label: 'Ultima', render: (r) => esc(fmtDateTime(r.last_seen)) },
    ]);
  }

  function renderAutomationTab(data) {
    const bots = data.bots || [];
    const m = data.messages_hub || {};
    const w = data.website || {};

    setText('bcd-auto-bots-count', fmtNum(bots.length));
    setText('bcd-auto-msg-open', fmtNum(m.total || 0));
    setText('bcd-auto-pages', fmtNum(w.pages));

    renderTable(document.getElementById('bcd-table-bots'), bots, [
      { label: 'Bot', render: (r) => esc(r.name) },
      { label: 'Canal', render: (r) => esc(r.channel) },
      { label: 'Token', render: (r) => statusPill(r.token_status) },
      { label: 'Ultim test', render: (r) => esc(fmtDateTime(r.last_test_at)) + ' ' + statusPill(r.last_test_status) },
    ]);

    const channels = m.by_channel || {};
    const chSeg = Object.entries(channels).map(([label, value]) => ({
      label,
      value: Number(value),
    }));
    renderDonut(document.getElementById('bcd-chart-channels-donut'), chSeg.length ? chSeg : [{ label: 'Fără mesaje', value: 1 }]);
    renderWebsiteStats(document.getElementById('bcd-website-stats'), w);
  }

  function renderSystemTab(data) {
    const health = data.health || {};
    const imp = data.import || {};
    const flags = data.red_flags || [];

    setText('bcd-sys-backup', health.latest_backup_at ? fmtDateTime(health.latest_backup_at) : '—');
    setText('bcd-sys-log', health.rapidapi_log_at ? fmtDateTime(health.rapidapi_log_at) : '—');
    renderTecdoc(health);

    renderHealthGrid(document.getElementById('bcd-sys-import-grid'), [
      { label: 'Joburi active', value: fmtNum(imp.running_jobs), cls: imp.running_jobs > 0 ? 'is-ok' : '' },
      { label: 'Joburi eșuate', value: fmtNum(imp.failed_jobs), cls: imp.failed_jobs > 0 ? 'is-danger' : 'is-ok' },
      { label: 'Blocaje (>30m)', value: fmtNum(imp.blocked_jobs), cls: imp.blocked_jobs > 0 ? 'is-warn' : 'is-ok' },
      { label: 'Ultim import', value: imp.last_import_at ? fmtDateTime(imp.last_import_at) : '—', cls: '' },
    ]);

    renderHealthGrid(document.getElementById('bcd-sys-health-grid'), [
      { label: 'Bază de date', value: health.database_ok ? 'OK' : 'Eroare', cls: health.database_ok ? 'is-ok' : 'is-danger' },
      { label: 'TecDoc quota', value: health.tecdoc_quota_exceeded ? 'Depășită' : 'OK', cls: health.tecdoc_quota_exceeded ? 'is-danger' : 'is-ok' },
      { label: 'API online', value: health.tecdoc_online ? 'Da' : 'Nu', cls: health.tecdoc_online ? 'is-ok' : 'is-warn' },
      { label: 'Backup recent', value: health.latest_backup_at ? 'Da' : 'Lipsă', cls: health.latest_backup_at ? 'is-ok' : 'is-warn' },
    ]);

    renderRedFlags(document.getElementById('bcd-sys-flags-full'), flags);
  }

  function activateTab(tabId) {
    shell.querySelectorAll('.bcd-tab').forEach((btn) => {
      const active = btn.getAttribute('data-bcd-tab') === tabId;
      btn.classList.toggle('is-active', active);
      btn.setAttribute('aria-selected', active ? 'true' : 'false');
    });
    shell.querySelectorAll('.bcd-panel').forEach((panel) => {
      const active = panel.getAttribute('data-bcd-panel') === tabId;
      panel.classList.toggle('is-active', active);
      panel.hidden = !active;
    });
    if (window.lucide && typeof window.lucide.createIcons === 'function') {
      window.lucide.createIcons();
    }
  }

  function bindTabs() {
    shell.querySelectorAll('.bcd-tab').forEach((btn) => {
      btn.addEventListener('click', () => {
        activateTab(btn.getAttribute('data-bcd-tab') || 'overview');
      });
    });
  }

  function renderTeamTab(data) {
    const team = data.team || {};
    const members = team.members || [];
    setText('bcd-team-total', fmtNum(team.total_users));
    setText('bcd-team-active', fmtNum(team.active_users));
    setText('bcd-team-unassigned', fmtNum(team.unassigned_products));

    const barRows = members
      .filter((m) => Number(m.products_total) > 0)
      .slice(0, 12)
      .map((m) => ({ day: m.name, count: Number(m.products_total) }));
    renderBars(document.getElementById('bcd-chart-team-bars'), barRows, 'count', 'day');

    const roleLabels = {
      super_ambassador: 'Super ambassador',
      manager: 'Manager',
      regional_ambassador: 'Regional',
      executive: 'Executive',
      operator: 'Operator',
    };

    renderTable(document.getElementById('bcd-table-team'), members, [
      { label: 'Utilizator', render: (r) => `<strong>${esc(r.name)}</strong><br><span class="bcd-metric__hint">${esc(r.login)}</span>` },
      { label: 'Rol', render: (r) => esc(roleLabels[r.role] || r.role) },
      { label: 'Produse', render: (r) => esc(fmtNum(r.products_total)) + ' <span class="bcd-metric__hint">(' + fmtNum(r.products_active) + ' active)</span>' },
      { label: 'Zone lucru', render: (r) => esc((r.workspaces || []).join(', ') || '—') },
      { label: 'Status', render: (r) => statusPill(r.status === '1' ? 'activ' : 'inactiv') },
    ]);
  }

  function renderScope(data) {
    const title = document.getElementById('bcd-scope-title');
    const desc = document.getElementById('bcd-scope-desc');
    if (title && data.scope_label) title.textContent = data.scope_label;
    if (desc && data.team) {
      desc.textContent = 'Agregat de la ' + fmtNum(data.team.total_users) + ' utilizatori admin — comenzi magazin, căutări site, catalog și mesaje (nu doar sesiunea ta).';
    }
    const teamPill = document.getElementById('bcd-hero-team-pill');
    if (teamPill && data.team) {
      const n = data.team.active_users ?? data.team.total_users ?? 0;
      teamPill.textContent = fmtNum(n) + ' utilizatori activi';
    }
  }

  function render(data) {
    if (!data) return;

    renderScope(data);

    const hint = document.getElementById('bcd-sync-hint');
    if (hint) {
      const online = data.health?.tecdoc_online ? 'Live' : 'TecDoc offline';
      hint.textContent = online + ' · ' + fmtDateTime(data.generated_at);
    }

    renderOverview(data);
    renderTeamTab(data);
    renderOrdersTab(data);
    renderCatalogTab(data);
    renderSearchTab(data);
    renderAutomationTab(data);
    renderSystemTab(data);
  }

  bindTabs();
  activateTab('overview');

  window.BwsCompanyDash = { render, activateTab };

  const bootData = window.__besoiuPendingCompanyDash || window.__besoiuDashboardData;
  if (bootData) {
    render(bootData);
    delete window.__besoiuPendingCompanyDash;
  }
})();
