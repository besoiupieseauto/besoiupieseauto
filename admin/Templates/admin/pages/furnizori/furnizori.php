<style>
  .furnizori-page { --fz-primary: #2563eb; --fz-primary-dark: #1d4ed8; --fz-border: #e5e7eb; --fz-muted: #64748b; --fz-success: #16a34a; --fz-danger: #dc2626; --fz-warning: #d97706; --fz-surface: #f8fafc; --fz-ink: #0f172a; }
  .furnizori-page .fz-header { display: flex; flex-wrap: wrap; align-items: flex-start; justify-content: space-between; gap: 16px; margin-top: 8px; }
  .furnizori-page .fz-title { font-size: 1.35rem; font-weight: 700; letter-spacing: -0.02em; margin: 0; color: #0f172a; }
  .furnizori-page .fz-subtitle { font-size: 0.85rem; color: var(--fz-muted); margin: 4px 0 0; }
  .furnizori-page .fz-btn-price {
    display: inline-flex; align-items: center; justify-content: center; gap: 6px; height: 38px; padding: 0 12px;
    border-radius: 10px; border: 1.5px solid #93c5fd; background: #eff6ff; color: #1d4ed8;
    font-size: 0.78rem; font-weight: 600; text-decoration: none !important; cursor: pointer; transition: all .15s;
  }
  .furnizori-page .fz-btn-price:hover { border-color: #2563eb; background: #dbeafe; color: #1e3a8a; }
  .furnizori-page .fz-page-tabs {
    display: flex; flex-wrap: wrap; gap: 0; margin-top: 18px;
    border-bottom: 1px solid var(--fz-border);
  }
  .furnizori-page .fz-page-tab {
    display: inline-flex; align-items: center; gap: 8px;
    height: 42px; padding: 0 18px; margin-bottom: -1px;
    border: 1px solid transparent; border-radius: 10px 10px 0 0;
    background: transparent; color: var(--fz-muted);
    font-size: 0.875rem; font-weight: 600; cursor: pointer; transition: color .15s, background .15s, border-color .15s;
  }
  .furnizori-page .fz-page-tab:hover { color: #334155; background: #f8fafc; }
  .furnizori-page .fz-page-tab.active {
    color: var(--fz-primary); background: #fff;
    border-color: var(--fz-border); border-bottom-color: #fff;
  }
  .furnizori-page .fz-page-tab svg { width: 16px; height: 16px; stroke: currentColor; fill: none; stroke-width: 2; }
  .furnizori-page .fz-page-pane { display: none; padding-top: 16px; }
  .furnizori-page .fz-page-pane.active { display: block; }
  .furnizori-page .fz-actions { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
  .furnizori-page .fz-btn-primary,
  .furnizori-page button.fz-btn-primary {
    display: inline-flex; align-items: center; gap: 8px; height: 40px; padding: 0 18px; border-radius: 10px;
    border: none !important; background: #2563eb !important; color: #ffffff !important;
    font-size: 0.85rem; font-weight: 600; cursor: pointer;
    box-shadow: 0 2px 8px rgba(37,99,235,.35); transition: transform .15s, box-shadow .15s;
  }
  .furnizori-page .fz-btn-primary:hover { transform: translateY(-1px); background: #1d4ed8 !important; color: #fff !important; box-shadow: 0 4px 14px rgba(37,99,235,.45); }
  .furnizori-page .fz-btn-outline,
  .furnizori-page button.fz-btn-outline {
    display: inline-flex; align-items: center; gap: 8px; height: 40px; padding: 0 16px; border-radius: 10px;
    border: 1.5px solid #cbd5e1 !important; background: #fff !important; color: #334155 !important;
    font-size: 0.85rem; font-weight: 600; cursor: pointer; transition: border-color .15s, color .15s;
  }
  .furnizori-page .fz-btn-outline:hover { border-color: #2563eb !important; color: #2563eb !important; background: #eff6ff !important; }
  .furnizori-page .fz-toolbar { display: flex; flex-wrap: wrap; align-items: center; gap: 12px; margin-top: 20px; padding: 14px 16px; background: #fff; border: 1px solid var(--fz-border); border-radius: 12px; }
  .furnizori-page .fz-search-wrap { position: relative; flex: 1; min-width: 200px; max-width: 360px; }
  .furnizori-page .fz-search-wrap svg { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); opacity: .45; pointer-events: none; }
  .furnizori-page .fz-search { width: 100%; height: 40px; padding: 0 12px 0 38px; border: 1px solid var(--fz-border); border-radius: 10px; font-size: 0.875rem; background: #f8fafc; outline: none; transition: border-color .15s, box-shadow .15s; }
  .furnizori-page .fz-search:focus { border-color: var(--fz-primary); box-shadow: 0 0 0 3px rgba(37,99,235,.12); background: #fff; }
  .furnizori-page .fz-filter { height: 40px; padding: 0 14px; border: 1px solid var(--fz-border); border-radius: 10px; font-size: 0.85rem; background: #fff; color: #334155; cursor: pointer; min-width: 140px; }
  .furnizori-page .fz-view-toggle { display: flex; border: 1px solid var(--fz-border); border-radius: 10px; overflow: hidden; margin-left: auto; }
  .furnizori-page .fz-view-btn { width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; border: none; background: #fff; color: var(--fz-muted); cursor: pointer; transition: background .15s, color .15s; }
  .furnizori-page .fz-view-btn.active { background: var(--fz-primary); color: #fff; }
  /* Rezumat numeric — același pattern ca .fz-metrics din card (informativ, nu clickabil) */
  .furnizori-page .fz-summary-caption {
    margin: 14px 0 0;
    padding: 10px 16px;
    font-size: 0.68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .05em;
    color: var(--fz-muted);
    background: var(--fz-surface);
    border: 1px solid var(--fz-border);
    border-bottom: none;
    border-radius: 12px 12px 0 0;
    pointer-events: none !important;
    cursor: default !important;
    user-select: none;
  }
  .furnizori-page .fz-summary-caption span {
    font-weight: 500;
    text-transform: none;
    letter-spacing: 0;
    color: #94a3b8;
  }
  .furnizori-page .fz-summary-metrics {
    display: grid !important;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 0;
    margin-top: 0;
    background: var(--fz-surface);
    border: 1px solid var(--fz-border);
    border-top: none;
    border-radius: 0 0 12px 12px;
    overflow: hidden;
    position: relative;
    z-index: 0;
    isolation: isolate;
    pointer-events: none !important;
    cursor: default !important;
    user-select: none;
  }
  .furnizori-page .fz-summary-metrics *,
  .furnizori-page .fz-summary-metrics *:hover,
  .furnizori-page .fz-summary-metrics *:focus,
  .furnizori-page .fz-summary-metrics *:active {
    pointer-events: none !important;
    cursor: default !important;
    box-shadow: none !important;
    transform: none !important;
    outline: none !important;
    -webkit-appearance: none;
    appearance: none;
  }
  .furnizori-page .fz-summary-metrics .fz-metric {
    display: flex !important;
    flex-direction: column !important;
    padding: 14px 16px;
    min-width: 0;
    background: transparent !important;
    border: none !important;
    border-right: 1px solid var(--fz-border) !important;
    border-radius: 0 !important;
    box-shadow: none !important;
    transition: none !important;
  }
  .furnizori-page .fz-summary-metrics .fz-metric:last-child { border-right: none; }
  .furnizori-page .fz-summary-metrics .fz-metric-label {
    display: block;
    font-size: 0.65rem;
    line-height: 1.35;
  }
  .furnizori-page .fz-summary-metrics .fz-metric-value {
    font-size: 1.05rem;
    margin-top: 2px;
  }
  .furnizori-page .fz-summary-metrics .fz-metric-value.text-success { color: var(--fz-success); }
  .furnizori-page .fz-summary-metrics .fz-metric-value.text-danger { color: var(--fz-danger); }
  .furnizori-page .fz-summary-metrics .fz-metric-value.text-primary { color: var(--fz-primary); }
  .furnizori-page .fz-summary-metrics .fz-metric-value.text-warning { color: var(--fz-warning); }
  @media (max-width: 900px) {
    .furnizori-page .fz-summary-metrics { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .furnizori-page .fz-summary-metrics .fz-metric:nth-child(2) { border-right: none; }
    .furnizori-page .fz-summary-metrics .fz-metric:nth-child(-n+2) { border-bottom: 1px solid var(--fz-border); }
  }
  @media (max-width: 520px) {
    .furnizori-page .fz-summary-metrics { grid-template-columns: 1fr; }
    .furnizori-page .fz-summary-metrics .fz-metric { border-right: none; border-bottom: 1px solid var(--fz-border); }
    .furnizori-page .fz-summary-metrics .fz-metric:last-child { border-bottom: none; }
  }
  .furnizori-page .fz-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 18px; margin-top: 20px; }
  .furnizori-page .fz-grid.list-view { grid-template-columns: 1fr; }
  .furnizori-page .fz-card {
    background: #fff; border: 1px solid var(--fz-border); border-radius: 16px; overflow: hidden;
    box-shadow: 0 1px 2px rgba(15,23,42,.04), 0 8px 24px rgba(15,23,42,.04);
    transition: box-shadow .22s, transform .22s, border-color .22s;
    display: flex; flex-direction: column; position: relative;
  }
  .furnizori-page .fz-card::before {
    content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 4px;
    background: linear-gradient(180deg, #22c55e, #16a34a); border-radius: 16px 0 0 16px;
  }
  .furnizori-page .fz-card.is-blocked::before { background: linear-gradient(180deg, #94a3b8, #64748b); }
  .furnizori-page .fz-card:hover { box-shadow: 0 4px 12px rgba(15,23,42,.06), 0 16px 40px rgba(15,23,42,.08); transform: translateY(-2px); border-color: #d1d5db; }
  .furnizori-page .fz-card.is-blocked { opacity: .88; }
  .furnizori-page .fz-card-head {
    display: flex; align-items: flex-start; gap: 14px;
    padding: 18px 18px 14px 22px; background: linear-gradient(180deg, #fff 0%, var(--fz-surface) 100%);
    color: var(--fz-ink); min-height: auto;
  }
  .furnizori-page .fz-card-head.is-blocked { background: linear-gradient(180deg, #fff 0%, #f1f5f9 100%); }
  .furnizori-page .fz-card-avatar {
    width: 52px; height: 52px; border-radius: 14px; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.95rem; font-weight: 800; letter-spacing: .02em; color: #fff;
    background: linear-gradient(145deg, #334155 0%, #0f172a 100%);
    box-shadow: 0 4px 12px rgba(15,23,42,.18);
  }
  .furnizori-page .fz-card-head.is-blocked .fz-card-avatar { background: linear-gradient(145deg, #94a3b8 0%, #64748b 100%); box-shadow: none; }
  .furnizori-page .fz-card-head-content { flex: 1; min-width: 0; }
  .furnizori-page .fz-card-head-top { display: flex; align-items: flex-start; justify-content: space-between; gap: 10px; }
  .furnizori-page .fz-card-head-menu { position: relative; flex-shrink: 0; margin-top: -2px; }
  .furnizori-page .fz-card-menu-btn {
    display: inline-flex; align-items: center; justify-content: center;
    width: 32px; height: 32px; padding: 0; border-radius: 8px;
    border: 1px solid transparent; background: transparent; color: var(--fz-muted);
    cursor: pointer; transition: background .15s, color .15s, border-color .15s;
  }
  .furnizori-page .fz-card-menu-btn:hover,
  .furnizori-page .fz-card-menu-btn[aria-expanded="true"] {
    background: #f1f5f9; color: #334155; border-color: #e2e8f0;
  }
  .furnizori-page .fz-card-menu-btn svg { width: 18px; height: 18px; stroke: currentColor; fill: none; stroke-width: 2; stroke-linecap: round; }
  .furnizori-page .fz-card-menu-popup {
    position: absolute; top: calc(100% + 4px); right: 0; z-index: 20;
    min-width: 168px; padding: 6px;
    background: #fff; border: 1px solid var(--fz-border); border-radius: 10px;
    box-shadow: 0 8px 24px rgba(15,23,42,.12);
    display: none;
  }
  .furnizori-page .fz-card-menu-popup.is-open { display: block; }
  .furnizori-page .fz-card-menu-item {
    display: flex; align-items: center; gap: 8px; width: 100%;
    padding: 8px 10px; border: none; border-radius: 8px;
    background: transparent; color: #334155;
    font-size: 0.78rem; font-weight: 600; text-align: left; cursor: pointer;
    transition: background .15s, color .15s;
  }
  .furnizori-page .fz-card-menu-item svg { width: 14px; height: 14px; flex-shrink: 0; stroke: currentColor; fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
  .furnizori-page .fz-card-menu-item:hover { background: #f8fafc; }
  .furnizori-page .fz-card-menu-item--warn { color: #b45309; }
  .furnizori-page .fz-card-menu-item--warn:hover { background: #fffbeb; }
  .furnizori-page .fz-card-menu-item--danger { color: #b91c1c; }
  .furnizori-page .fz-card-menu-item--danger:hover { background: #fef2f2; }
  .furnizori-page .fz-card-name { font-size: 1.05rem; font-weight: 700; margin: 0; letter-spacing: -0.02em; color: var(--fz-ink); line-height: 1.25; }
  .furnizori-page .fz-card-conn-row {
    display: flex; flex-wrap: wrap; gap: 8px;
    padding: 10px 16px 10px 22px;
    border-top: 1px solid var(--fz-border);
    background: #fff;
  }
  .furnizori-page .fz-card-conn-btn {
    display: inline-flex; align-items: center; gap: 6px;
    height: 34px; padding: 0 12px; border-radius: 8px;
    border: 1px solid #cbd5e1; background: #f8fafc; color: #334155;
    font-size: 0.74rem; font-weight: 600; cursor: pointer; transition: all .15s;
  }
  .furnizori-page .fz-card-conn-btn:hover { border-color: var(--fz-primary); color: var(--fz-primary); background: #eff6ff; }
  .furnizori-page .fz-card-conn-btn--api { border-color: #bfdbfe; background: #eff6ff; color: #1d4ed8; }
  .furnizori-page .fz-card-conn-btn--api:hover { border-color: #2563eb; background: #dbeafe; color: #1e3a8a; }
  .furnizori-page .fz-card-conn-btn svg { width: 14px; height: 14px; stroke: currentColor; fill: none; stroke-width: 2; flex-shrink: 0; stroke-linecap: round; stroke-linejoin: round; }
  .furnizori-page .fz-card-badges { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; }
  .furnizori-page .fz-badge { display: inline-flex; align-items: center; padding: 3px 9px; border-radius: 999px; font-size: 0.68rem; font-weight: 600; border: 1px solid transparent; }
  .furnizori-page .fz-badge--code { background: #f1f5f9; color: #475569; border-color: #e2e8f0; }
  .furnizori-page .fz-badge--active { background: #ecfdf5; color: #047857; border-color: #a7f3d0; }
  .furnizori-page .fz-badge--blocked { background: #fef2f2; color: #b91c1c; border-color: #fecaca; }
  .furnizori-page .fz-card-scan { font-size: 0.72rem; color: var(--fz-muted); margin-top: 6px; line-height: 1.4; }
  .furnizori-page .fz-metrics { display: grid; grid-template-columns: repeat(3, 1fr); gap: 0; border-top: 1px solid var(--fz-border); background: var(--fz-surface); }
  .furnizori-page .fz-metric { padding: 12px 14px; display: flex; flex-direction: column; gap: 3px; border-right: 1px solid var(--fz-border); }
  .furnizori-page .fz-metric:last-child { border-right: none; }
  .furnizori-page .fz-metric-label { font-size: 0.65rem; color: var(--fz-muted); text-transform: uppercase; letter-spacing: .05em; font-weight: 600; }
  .furnizori-page .fz-metric-value { font-size: 0.88rem; font-weight: 700; color: var(--fz-ink); }
  .furnizori-page .fz-metric-value.text-success { color: var(--fz-success); }
  .furnizori-page .fz-metric-value.text-muted { color: var(--fz-muted); font-weight: 500; }
  .furnizori-page .fz-sync-report {
    border-top: 1px solid var(--fz-border);
    background: #fff;
    padding: 12px 16px 12px 22px;
    display: flex;
    flex-direction: column;
    gap: 8px;
  }
  .furnizori-page .fz-sync-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
  }
  .furnizori-page .fz-sync-hint {
    margin: 6px 0 0;
    font-size: 11px;
    line-height: 1.45;
    color: #64748b;
  }
  .furnizori-page .fz-sync-title {
    font-size: 0.68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .05em;
    color: var(--fz-muted);
  }
  .furnizori-page .fz-badge--sync-yes { background: #ecfdf5; color: #047857; border-color: #a7f3d0; }
  .furnizori-page .fz-badge--sync-no { background: #fef2f2; color: #b91c1c; border-color: #fecaca; }
  .furnizori-page .fz-sync-meta {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px 12px;
    font-size: 0.72rem;
    color: #334155;
  }
  .furnizori-page .fz-sync-meta-label { display: block; font-size: 0.62rem; text-transform: uppercase; letter-spacing: .04em; color: var(--fz-muted); font-weight: 600; margin-bottom: 2px; }
  .furnizori-page .fz-sync-files { font-size: 0.72rem; color: #334155; }
  .furnizori-page .fz-sync-files ul { margin: 4px 0 0; padding-left: 16px; }
  .furnizori-page .fz-sync-files li { margin: 2px 0; line-height: 1.35; }
  .furnizori-page .fz-sync-files .fz-sync-file-src { color: var(--fz-muted); font-size: 0.66rem; }
  .furnizori-page .fz-conn-panels {
    border-top: 1px solid var(--fz-border);
    background: #fff;
    padding: 12px 16px 12px 22px;
    display: flex;
    flex-direction: column;
    gap: 10px;
  }
  .furnizori-page .fz-conn-panel {
    border: 1px solid var(--fz-border);
    border-radius: 12px;
    padding: 12px 14px;
    background: var(--fz-surface);
    display: flex;
    flex-direction: column;
    gap: 10px;
  }
  .furnizori-page .fz-conn-panel--api {
    border-color: #bfdbfe;
    background: linear-gradient(180deg, #f8fbff 0%, #eff6ff 100%);
  }
  .furnizori-page .fz-conn-panel--ftp {
    border-color: #cbd5e1;
    background: linear-gradient(180deg, #fff 0%, #f8fafc 100%);
  }
  .furnizori-page .fz-conn-panel-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
  }
  .furnizori-page .fz-conn-panel-title {
    font-size: 0.68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .05em;
    color: var(--fz-muted);
  }
  .furnizori-page .fz-conn-panel-badge {
    font-size: 0.62rem;
    font-weight: 600;
    padding: 2px 8px;
    border-radius: 999px;
    background: #e2e8f0;
    color: #475569;
  }
  .furnizori-page .fz-conn-panel--api .fz-conn-panel-badge { background: #dbeafe; color: #1d4ed8; }
  .furnizori-page .fz-conn-panel--ftp .fz-conn-panel-badge { background: #e2e8f0; color: #334155; }
  .furnizori-page .fz-conn-fields {
    display: grid;
    grid-template-columns: 1fr;
    gap: 8px;
  }
  .furnizori-page .fz-conn-field label {
    display: block;
    font-size: 0.62rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .04em;
    color: var(--fz-muted);
    margin-bottom: 3px;
  }
  .furnizori-page .fz-conn-field input {
    width: 100%;
    height: 34px;
    padding: 0 10px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    font-size: 0.78rem;
    background: #fff;
    color: var(--fz-ink);
    box-sizing: border-box;
  }
  .furnizori-page .fz-conn-field input:focus {
    outline: none;
    border-color: var(--fz-primary);
    box-shadow: 0 0 0 2px rgba(37,99,235,.12);
  }
  .furnizori-page .fz-conn-readonly {
    min-height: 34px;
    padding: 7px 10px;
    border: 1px dashed #cbd5e1;
    border-radius: 8px;
    font-size: 0.78rem;
    color: #334155;
    background: rgba(255,255,255,.7);
    word-break: break-all;
  }
  .furnizori-page .fz-conn-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
  }
  .furnizori-page .fz-conn-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    height: 34px;
    padding: 0 12px;
    border-radius: 8px;
    border: 1px solid #cbd5e1;
    background: #fff;
    color: #334155;
    font-size: 0.74rem;
    font-weight: 600;
    cursor: pointer;
    transition: all .15s;
  }
  .furnizori-page .fz-conn-btn:hover { border-color: var(--fz-primary); color: var(--fz-primary); background: #eff6ff; }
  .furnizori-page .fz-conn-btn--primary {
    border-color: var(--fz-primary);
    background: var(--fz-primary);
    color: #fff;
  }
  .furnizori-page .fz-conn-btn--primary:hover { background: #1d4ed8; color: #fff; }
  .furnizori-page .fz-conn-btn:disabled { opacity: .55; cursor: not-allowed; }
  .furnizori-page .fz-conn-status {
    font-size: 0.7rem;
    color: var(--fz-muted);
    min-height: 1em;
    line-height: 1.35;
  }
  .furnizori-page .fz-conn-status.is-ok { color: var(--fz-success); }
  .furnizori-page .fz-conn-status.is-error { color: var(--fz-danger); }
  .furnizori-page .fz-card-foot {
    display: flex; flex-direction: column; gap: 0;
    padding: 12px 16px 12px 22px; border-top: 1px solid var(--fz-border); background: #fff;
    margin-top: auto; flex-shrink: 0;
  }
  .furnizori-page .fz-card-foot svg {
    width: 16px; height: 16px; flex-shrink: 0;
    stroke: currentColor; fill: none; stroke-width: 2;
    stroke-linecap: round; stroke-linejoin: round;
  }
  .furnizori-page .fz-card-foot-main { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
  .furnizori-page .fz-btn-config {
    display: inline-flex; align-items: center; justify-content: center; gap: 7px;
    height: 40px; padding: 0 12px; border-radius: 10px; border: none;
    background: var(--fz-ink) !important; color: #fff !important;
    font-size: 0.8rem; font-weight: 600; text-decoration: none !important;
    cursor: pointer; transition: background .15s, transform .15s; width: 100%; min-width: 0;
  }
  .furnizori-page .fz-btn-config:hover { background: #1e293b !important; color: #fff !important; transform: translateY(-1px); }
  .furnizori-page .fz-btn-config svg { width: 15px; height: 15px; flex-shrink: 0; stroke: currentColor; fill: none; }
  .furnizori-page .fz-card-foot .fz-btn-price {
    width: 100%; min-width: 0; height: 40px; box-sizing: border-box;
    border-color: #d1d5db !important; background: #fff !important; color: #334155 !important;
  }
  .furnizori-page .fz-card-foot .fz-btn-price:hover { border-color: var(--fz-primary) !important; color: var(--fz-primary) !important; background: #f8fafc !important; }
  .furnizori-page .fz-btn-primary svg,
  .furnizori-page .fz-btn-outline svg { width: 16px; height: 16px; flex-shrink: 0; stroke: currentColor; fill: none; stroke-width: 2.2; stroke-linecap: round; stroke-linejoin: round; }
  .furnizori-page .fz-empty { grid-column: 1 / -1; text-align: center; padding: 48px 24px; background: #fff; border: 1px dashed var(--fz-border); border-radius: 14px; }
  .furnizori-page .fz-empty-icon { width: 56px; height: 56px; margin: 0 auto 16px; border-radius: 14px; background: #eff6ff; display: flex; align-items: center; justify-content: center; color: var(--fz-primary); }
  .furnizori-page .fz-list-card { flex-direction: row; }
  .furnizori-page .fz-grid.list-view .fz-card { flex-direction: row; align-items: stretch; }
  .furnizori-page .fz-grid.list-view .fz-card-head { min-width: 260px; flex-shrink: 0; border-right: 1px solid var(--fz-border); }
  .furnizori-page .fz-grid.list-view .fz-metrics { flex: 1; align-self: center; border-top: none; background: transparent; }
  .furnizori-page .fz-grid.list-view .fz-card-foot {
    min-width: 220px; justify-content: center;
    border-top: none; border-left: 1px solid var(--fz-border);
  }
  @media (max-width: 768px) {
    .furnizori-page .fz-grid.list-view .fz-card { flex-direction: column; }
    .furnizori-page .fz-grid.list-view .fz-card-head { min-width: auto; border-right: none; border-bottom: 1px solid var(--fz-border); }
    .furnizori-page .fz-grid.list-view .fz-metrics { grid-template-columns: repeat(3, 1fr); border-top: 1px solid var(--fz-border); }
    .furnizori-page .fz-grid.list-view .fz-card-foot { border-left: none; border-top: 1px solid var(--fz-border); min-width: auto; }
    .furnizori-page .fz-metrics { grid-template-columns: 1fr; }
    .furnizori-page .fz-metric { border-right: none; border-bottom: 1px solid var(--fz-border); }
    .furnizori-page .fz-metric:last-child { border-bottom: none; }
  }

  /* Modal adaugare — izolat de layout-ul paginii */
  .fz-modal-overlay {
    position: fixed;
    inset: 0;
    z-index: 999999;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 20px;
    background: rgba(15, 23, 42, 0.55);
    backdrop-filter: blur(4px);
    -webkit-backdrop-filter: blur(4px);
  }
  .fz-modal-overlay.is-open { display: flex; }
  body.fz-modal-open { overflow: hidden; }
  body.fz-modal-open .side-menu,
  body.fz-modal-open .side-menu * {
    pointer-events: none !important;
  }
  .fz-modal-dialog {
    position: relative;
    width: 100%;
    max-width: 520px;
    max-height: calc(100vh - 40px);
    overflow: hidden;
    display: flex;
    flex-direction: column;
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.35);
    animation: fzModalIn 0.2s ease-out;
  }
  @keyframes fzModalIn {
    from { opacity: 0; transform: scale(0.96) translateY(8px); }
    to { opacity: 1; transform: scale(1) translateY(0); }
  }
  .fz-modal-head {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 20px 22px;
    border-bottom: 1px solid var(--fz-border);
    background: linear-gradient(180deg, #f8fafc, #fff);
    flex-shrink: 0;
  }
  .fz-modal-head-icon {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    background: linear-gradient(135deg, #3b82f6, #1d4ed8);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    flex-shrink: 0;
  }
  .fz-modal-head-icon svg { width: 20px; height: 20px; stroke: currentColor; fill: none; stroke-width: 2.2; }
  .fz-modal-head-text { flex: 1; min-width: 0; }
  .fz-modal-head-text h3 { margin: 0; font-size: 1.05rem; font-weight: 700; color: #0f172a; }
  .fz-modal-head-text p { margin: 2px 0 0; font-size: 0.78rem; color: var(--fz-muted); }
  .fz-modal-close {
    width: 36px;
    height: 36px;
    border: none;
    border-radius: 10px;
    background: #f1f5f9;
    color: #64748b;
    font-size: 1.25rem;
    line-height: 1;
    cursor: pointer;
    flex-shrink: 0;
    transition: background 0.15s, color 0.15s;
  }
  .fz-modal-close:hover { background: #e2e8f0; color: #0f172a; }
  .fz-modal-body {
    padding: 22px;
    overflow-y: auto;
    flex: 1;
  }
  .fz-modal-form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
  }
  .fz-modal-field { display: flex; flex-direction: column; gap: 6px; }
  .fz-modal-field--full { grid-column: 1 / -1; }
  .fz-modal-field label {
    font-size: 0.8rem;
    font-weight: 600;
    color: #334155;
  }
  .fz-modal-field input,
  .fz-modal-field select {
    width: 100%;
    height: 42px;
    padding: 0 12px;
    border: 1px solid var(--fz-border);
    border-radius: 10px;
    font-size: 0.875rem;
    color: #0f172a;
    background: #fff;
    box-sizing: border-box;
    outline: none;
    transition: border-color 0.15s, box-shadow 0.15s;
  }
  .fz-modal-field input:focus,
  .fz-modal-field select:focus {
    border-color: var(--fz-primary);
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
  }
  #furnizori-modal .fz-modal-foot {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 12px;
    padding: 16px 22px 20px;
    border-top: 1px solid #e2e8f0;
    background: #f1f5f9;
    flex-shrink: 0;
  }
  #furnizori-modal .fz-modal-btn-cancel,
  #furnizori-modal button.fz-modal-btn-cancel {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    height: 42px;
    min-width: 110px;
    padding: 0 20px;
    border-radius: 10px;
    border: 1.5px solid #cbd5e1 !important;
    background: #ffffff !important;
    color: #334155 !important;
    font-size: 0.875rem !important;
    font-weight: 600 !important;
    cursor: pointer;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
  }
  #furnizori-modal .fz-modal-btn-cancel:hover {
    background: #f8fafc !important;
    border-color: #94a3b8 !important;
    color: #0f172a !important;
  }
  #furnizori-modal .fz-modal-btn-save,
  #furnizori-modal button.fz-modal-btn-save,
  #furnizori-modal button[type="submit"].fz-modal-btn-save {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    height: 42px;
    min-width: 160px;
    padding: 0 24px;
    border-radius: 10px;
    border: none !important;
    background: #2563eb !important;
    background-image: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%) !important;
    color: #ffffff !important;
    font-size: 0.875rem !important;
    font-weight: 700 !important;
    cursor: pointer;
    box-shadow: 0 4px 14px rgba(37, 99, 235, 0.45) !important;
    -webkit-appearance: none;
    appearance: none;
  }
  #furnizori-modal .fz-modal-btn-save:hover {
    background: #1d4ed8 !important;
    background-image: linear-gradient(135deg, #1d4ed8 0%, #1e40af 100%) !important;
    color: #ffffff !important;
    box-shadow: 0 6px 18px rgba(37, 99, 235, 0.5) !important;
  }
  @media (max-width: 520px) {
    .fz-modal-form-grid { grid-template-columns: 1fr; }
    .fz-modal-field--full { grid-column: auto; }
  }
  #furnizori-conn-modal .fz-modal-body { padding: 16px 20px 20px; }
  #furnizori-conn-modal .fz-conn-panel {
    border: 1px solid #e5e7eb; border-radius: 12px; padding: 12px 14px;
    background: #f8fafc; display: flex; flex-direction: column; gap: 10px;
  }
  #furnizori-conn-modal .fz-conn-panel--api {
    border-color: #bfdbfe; background: linear-gradient(180deg, #f8fbff 0%, #eff6ff 100%);
  }
  #furnizori-conn-modal .fz-conn-panel--ftp {
    border-color: #cbd5e1; background: linear-gradient(180deg, #fff 0%, #f8fafc 100%);
  }
  #furnizori-conn-modal .fz-conn-panel-head { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
  #furnizori-conn-modal .fz-conn-panel-title { font-size: 0.68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: #64748b; }
  #furnizori-conn-modal .fz-conn-panel-badge { font-size: 0.62rem; font-weight: 600; padding: 2px 8px; border-radius: 999px; background: #e2e8f0; color: #475569; }
  #furnizori-conn-modal .fz-conn-panel--api .fz-conn-panel-badge { background: #dbeafe; color: #1d4ed8; }
  #furnizori-conn-modal .fz-conn-fields { display: grid; grid-template-columns: 1fr; gap: 8px; }
  #furnizori-conn-modal .fz-conn-field span { display: block; font-size: 0.62rem; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; color: #64748b; margin-bottom: 3px; }
  #furnizori-conn-modal .fz-conn-field input {
    width: 100%; height: 34px; padding: 0 10px; border: 1px solid #cbd5e1; border-radius: 8px;
    font-size: 0.78rem; background: #fff; color: #0f172a; box-sizing: border-box;
  }
  #furnizori-conn-modal .fz-conn-field input:focus { outline: none; border-color: #2563eb; box-shadow: 0 0 0 2px rgba(37,99,235,.12); }
  #furnizori-conn-modal .fz-conn-readonly {
    min-height: 34px; padding: 7px 10px; border: 1px dashed #cbd5e1; border-radius: 8px;
    font-size: 0.78rem; color: #334155; background: rgba(255,255,255,.7); word-break: break-all;
  }
  #furnizori-conn-modal .fz-conn-actions { display: flex; flex-wrap: wrap; gap: 8px; }
  #furnizori-conn-modal .fz-conn-btn {
    display: inline-flex; align-items: center; justify-content: center; gap: 6px;
    height: 34px; padding: 0 12px; border-radius: 8px; border: 1px solid #cbd5e1;
    background: #fff; color: #334155; font-size: 0.74rem; font-weight: 600; cursor: pointer;
  }
  #furnizori-conn-modal .fz-conn-btn--primary { background: #2563eb; border-color: #2563eb; color: #fff; }
  #furnizori-conn-modal .fz-conn-btn:disabled { opacity: .55; cursor: not-allowed; }
  #furnizori-conn-modal .fz-conn-status { font-size: 0.72rem; line-height: 1.35; }
  #furnizori-conn-modal .fz-conn-status.is-ok { color: #16a34a; }
  #furnizori-conn-modal .fz-conn-status.is-error { color: #dc2626; }
</style>

<div class="furnizori-page">
  <div id="furnizori-toast" class="hidden fixed right-5 top-5 z-[100000] rounded-md border bg-white px-4 py-3 text-sm shadow-lg"></div>

  <div class="fz-header">
    <div>
      <h2 class="fz-title">Furnizori</h2>
      <p class="fz-subtitle">Comparare furnizori la import, formare preț și scanare stoc.</p>
    </div>
    <div class="fz-actions">
      <button id="furnizori-open-create" type="button" class="fz-btn-primary">
        <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>Adaugă furnizor
      </button>
    </div>
  </div>

  <div class="fz-page-tabs" role="tablist">
    <button type="button" class="fz-page-tab" data-page-tab="compare" role="tab" aria-selected="false">
      <svg viewBox="0 0 24 24"><path d="M16 3h5v5M4 20 21 3M21 16v5h-5M15 15l6 6M4 4l5 5"/></svg>
      Comparare furnizori
    </button>
    <button type="button" class="fz-page-tab active" data-page-tab="lista" role="tab" aria-selected="true">
      <svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
      Lista furnizori
    </button>
    <button type="button" class="fz-page-tab" data-page-tab="blocked" role="tab" aria-selected="false">
      <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
      Furnizori blocați
    </button>
  </div>

  <div class="fz-page-pane" data-page-pane="compare" role="tabpanel">
    <?php include __DIR__ . '/price-logic-panel.php'; ?>
  </div>

  <div class="fz-page-pane active" data-page-pane="lista" role="tabpanel">
  <div class="fz-toolbar">
    <div class="fz-search-wrap">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
      <input id="furnizori-search" class="fz-search" type="text" placeholder="Caută furnizor...">
    </div>
    <span class="text-sm opacity-60 hidden sm:inline"><span id="furnizori-count">0</span> <span id="furnizori-count-label">furnizori activi</span></span>
    <div class="fz-view-toggle">
      <button type="button" class="fz-view-btn active" data-view="grid" title="Grid">
        <i data-lucide="layout-grid" class="size-4"></i>
      </button>
      <button type="button" class="fz-view-btn" data-view="list" title="Listă">
        <i data-lucide="list" class="size-4"></i>
      </button>
    </div>
  </div>

  <p class="fz-summary-caption">Rezumat listă furnizori <span>· valori calculate automat, nu sunt butoane</span></p>
  <div class="fz-summary-metrics" role="status" aria-live="polite" aria-label="Rezumat listă furnizori — valori informative, fără acțiuni">
    <div class="fz-metric">
      <span class="fz-metric-label">Furnizori activi</span>
      <span class="fz-metric-value text-success" id="furnizori-stat-active">0</span>
    </div>
    <div class="fz-metric">
      <span class="fz-metric-label">Furnizori blocați</span>
      <span class="fz-metric-value text-danger" id="furnizori-stat-blocked">0</span>
    </div>
    <div class="fz-metric">
      <span class="fz-metric-label">Total produse (stoc)</span>
      <span class="fz-metric-value text-primary" id="furnizori-stat-products">0</span>
    </div>
    <div class="fz-metric">
      <span class="fz-metric-label">Interval mediu scanare</span>
      <span class="fz-metric-value text-warning"><span id="furnizori-stat-interval">60</span> min</span>
    </div>
  </div>

  <div id="furnizori-grid" class="fz-grid"></div>
  <div id="furnizori-pagination" class="mt-4"></div>
  </div><!-- /lista -->
</div>

<!-- Modal în afara .furnizori-page — nu se suprapune cu sidebar -->
<div id="furnizori-modal" class="fz-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="furnizori-modal-title">
  <div class="fz-modal-dialog" role="document">
    <div class="fz-modal-head">
      <div class="fz-modal-head-icon">
        <svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
      </div>
      <div class="fz-modal-head-text">
        <h3 id="furnizori-modal-title">Furnizor nou</h3>
        <p>Completează datele de bază. Restul setărilor le faci după salvare.</p>
      </div>
      <button type="button" id="furnizori-close-modal" class="fz-modal-close" aria-label="Închide">&times;</button>
    </div>
    <form id="furnizori-form" data-action="add">
      <div class="fz-modal-body">
        <input type="hidden" name="randomn_id">
        <div class="fz-modal-form-grid">
          <div class="fz-modal-field fz-modal-field--full">
            <label for="fz-field-name">Nume furnizor</label>
            <input id="fz-field-name" name="name" required maxlength="255" placeholder="ex: BOSCH, AutoPartner" autocomplete="organization">
          </div>
          <div class="fz-modal-field">
            <label for="fz-field-code">Cod scurt (pSupplier)</label>
            <input id="fz-field-code" name="code" maxlength="50" placeholder="AUTONET, ELIT, MATEROM...">
          </div>
          <input type="hidden" name="connection_type" value="api">
          <div class="fz-modal-field">
            <label for="fz-field-scan">Interval scanare (min)</label>
            <input id="fz-field-scan" type="number" min="5" step="5" name="scan_interval_minutes" value="60">
          </div>
          <div class="fz-modal-field">
            <label for="fz-field-stock">Când stocul este 0</label>
            <select id="fz-field-stock" name="stock_zero_mode">
              <option value="full">Afișează ca FULL</option>
              <option value="hide">Nu ne adresăm (ascunde)</option>
              <option value="out_of_stock">Epuizat</option>
            </select>
          </div>
        </div>
      </div>
      <div class="fz-modal-foot">
        <button type="button" id="furnizori-cancel" class="fz-modal-btn-cancel">Anulează</button>
        <button type="submit" class="fz-modal-btn-save" style="background:#2563eb;color:#fff;border:none;">Salvează furnizorul</button>
      </div>
    </form>
  </div>
</div>

<div id="furnizori-conn-modal" class="fz-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="furnizori-conn-modal-title">
  <div class="fz-modal-dialog" role="document">
    <div class="fz-modal-head">
      <div class="fz-modal-head-icon">
        <svg viewBox="0 0 24 24"><path d="M4 20h16a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7l-2-2H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2z"/></svg>
      </div>
      <div class="fz-modal-head-text">
        <h3 id="furnizori-conn-modal-title">Conexiune furnizor</h3>
        <p id="furnizori-conn-modal-sub">Setări conexiune și acțiuni rapide</p>
      </div>
      <button type="button" id="furnizori-close-conn-modal" class="fz-modal-close" aria-label="Închide">&times;</button>
    </div>
    <div class="fz-modal-body" id="furnizori-conn-modal-body"></div>
  </div>
</div>

<script>
(function(){'use strict';
const ENDPOINT='/admin/api/furnizori_endpoint.php';
const grid=document.getElementById('furnizori-grid');
const form=document.getElementById('furnizori-form');
const modal=document.getElementById('furnizori-modal');
const connModal=document.getElementById('furnizori-conn-modal');
const connModalBody=document.getElementById('furnizori-conn-modal-body');
const connModalTitle=document.getElementById('furnizori-conn-modal-title');
const connModalSub=document.getElementById('furnizori-conn-modal-sub');
const toast=document.getElementById('furnizori-toast');
const filters={search:document.getElementById('furnizori-search')};
let furnizori=[];
let supplierListTab='lista';
let viewMode='grid';
let listMeta={page:1,total:0,per_page:10,total_pages:1};
let currentPage=1;
const paginationEl=document.getElementById('furnizori-pagination');

const FZ_ICONS={
  settings:'<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>',
  plug:'<svg viewBox="0 0 24 24"><path d="M13 2 3 14h9l-1 8 10-12h-9l1-8z"/></svg>',
  lock:'<svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>',
  unlock:'<svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 9.9-1"/></svg>',
  trash:'<svg viewBox="0 0 24 24"><path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/></svg>',
  plus:'<svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>',
  folder:'<svg viewBox="0 0 24 24"><path d="M4 20h16a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7l-2-2H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2z"/></svg>',
  mail:'<svg viewBox="0 0 24 24"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>',
  'badge-percent':'<svg viewBox="0 0 24 24"><path d="M3.85 8.62a4 4 0 0 1 4.78-4.77 4 4 0 0 1 6.74 0 4 4 0 0 1 4.78 4.78 4 4 0 0 1 0 6.74 4 4 0 0 1-4.77 4.78 4 4 0 0 1-6.75 0 4 4 0 0 1-4.78-4.77 4 4 0 0 1 0-6.76Z"/><path d="m15 9-6 6M9 9h.01M15 15h.01"/></svg>',
  'more-vertical':'<svg viewBox="0 0 24 24"><circle cx="12" cy="5" r="1.5" fill="currentColor" stroke="none"/><circle cx="12" cy="12" r="1.5" fill="currentColor" stroke="none"/><circle cx="12" cy="19" r="1.5" fill="currentColor" stroke="none"/></svg>',
};
function fzIcon(name){return FZ_ICONS[name]||''}

function closeAllCardMenus(except){
  document.querySelectorAll('.fz-card-menu-popup.is-open').forEach(popup=>{
    if(except&&popup===except) return;
    popup.classList.remove('is-open');
    popup.closest('.fz-card-head-menu')?.querySelector('[data-menu-trigger]')?.setAttribute('aria-expanded','false');
  });
}
function renderCardHeadMenu(blocked,st,sid){
  const toggleLabel=blocked?'Deblochează':'Blochează';
  const toggleIcon=blocked?'unlock':'lock';
  return `<div class="fz-card-head-menu">
    <button type="button" class="fz-card-menu-btn" data-menu-trigger aria-label="Acțiuni furnizor" aria-expanded="false" aria-haspopup="true">${fzIcon('more-vertical')}</button>
    <div class="fz-card-menu-popup" role="menu">
      <button type="button" class="fz-card-menu-item fz-card-menu-item--warn" role="menuitem" data-action="toggle" data-id="${escapeHtml(sid)}" data-status="${escapeHtml(st)}" title="${toggleLabel}">${fzIcon(toggleIcon)}<span>${toggleLabel}</span></button>
      <button type="button" class="fz-card-menu-item fz-card-menu-item--danger" role="menuitem" data-action="delete" data-id="${escapeHtml(sid)}" title="Șterge">${fzIcon('trash')}<span>Șterge</span></button>
    </div>
  </div>`;
}

function normalizeStatus(s){return (s==='blocked')?'blocked':'active'}
function supplierId(item){
  if(item.randomn_id!=null&&item.randomn_id!=='')return Number(item.randomn_id);
  if(item.id!=null&&item.id!=='')return Number(item.id);
  return 0;
}
function unwrapFurnizoriList(data){
  if(window.BpaPagination)return BpaPagination.unwrapList(data);
  if(Array.isArray(data))return{items:data,total:data.length,page:1,per_page:10,total_pages:1};
  if(data&&Array.isArray(data.items))return{
    items:data.items,
    total:Number(data.total??data.items.length),
    page:Number(data.page??1),
    per_page:Number(data.per_page??10),
    total_pages:Number(data.total_pages??1)
  };
  return{items:[],total:0,page:1,per_page:10,total_pages:1};
}
async function apiCall(action,payload){
  const response=await fetch(ENDPOINT,{
    method:'POST',
    headers:{'Content-Type':'application/json'},
    credentials:'same-origin',
    body:JSON.stringify({type_product:action,...payload})
  });
  const raw=await response.text();
  let result;try{result=JSON.parse(raw)}catch(e){throw new Error('Endpoint-ul nu a returnat JSON valid.')}
  if(!response.ok||!result.success)throw new Error(result.message||'Eroare necunoscută');
  return result.data;
}
function escapeHtml(v){return String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]))}
function showToast(msg,err){if(!toast)return;toast.textContent=msg;toast.classList.remove('hidden');toast.classList.toggle('border-red-300',!!err);toast.classList.toggle('text-red-600',!!err);setTimeout(()=>toast.classList.add('hidden'),3200)}
function formToObject(f){
  const p={};
  new FormData(f).forEach((v,k)=>{
    const el=f.elements.namedItem(k);
    if(el&&el.type==='radio')return;
    if(String(v).trim()!=='')p[k]=v;
  });
  return p;
}
function stockLabel(m){return{full:'FULL',hide:'Ascuns',out_of_stock:'Epuizat'}[m]||m||'FULL'}
function formatProducts(n){const x=Number(n||0);return x>=1000?(x/1000).toFixed(1).replace('.0','')+'k':String(x)}
function formatMarkup(item){
  if(item.feed_markup_locked&&!item.feed_markup_override) return '0% (CSV = achiziție)';
  if(item.price_markup_label) return item.price_markup_label;
  const v=item.price_markup_value||0;
  return v+'%';
}
function formatProductsBreakdown(item){
  const pub=Number(item.products_published||0);
  const queue=Number(item.products_queue||0);
  if(pub>0||queue>0) return pub+' mag · '+queue+' coada';
  return '0';
}
function formatSyncFiles(item){
  const files=Array.isArray(item.sync_files)?item.sync_files:[];
  if(!files.length){
    return '<span class="text-muted" id="furnizori-sync-files">Niciun fisier pe server</span>';
  }
  const limit=4;
  const rows=files.slice(0,limit).map(file=>{
    const name=escapeHtml(file.name||'-');
    const src=escapeHtml(file.source_label||item.sync_source_label||'');
    const at=escapeHtml(file.received_at_label||'');
    return `<li><strong>${name}</strong> <span class="fz-sync-file-src">(${src}${at?(' · '+at):''})</span></li>`;
  }).join('');
  const more=files.length>limit?`<li class="opacity-60">+${files.length-limit} alte fisiere</li>`:'';
  return `<div id="furnizori-sync-files"><ul>${rows}${more}</ul></div>`;
}
function supplierCode(item){
  return String(item?.supplier_code||item?.code||'').trim().toUpperCase();
}
function connectionChannelLabel(item){
  const type=String(item?.connection_type||'').toLowerCase();
  if(type==='sftp') return 'SFTP';
  if(type==='email') return 'Email';
  if(type==='api') return 'API';
  if(type==='ftp') return 'FTP';
  return 'FTP / SFTP';
}
function hasFeedFiles(item){
  return Number(item?.sync_files_count||0)>0||((Array.isArray(item?.sync_files)?item.sync_files:[]).length>0);
}
function feedFilesStatusHtml(item){
  if(!hasFeedFiles(item)) return '';
  const filesCount=Number(item.sync_files_count||((Array.isArray(item.sync_files)?item.sync_files:[]).length)||0);
  const lastLabel=String(item.sync_last_at_label||'').trim();
  const when=lastLabel&&lastLabel!=='—'?(' · ultima actualizare '+escapeHtml(lastLabel)):'';
  return `<div class="fz-conn-status is-ok" id="furnizori-conn-status">Folder local activ — ${filesCount} fisier(e) pe server${when}</div>`;
}
function maskSecret(hasValue){
  return hasValue?'••••••••':'—';
}
function renderApiPanel(item,sid){
  const connType=String(item.connection_type||'api').toLowerCase();
  const apiUrl=String(item.api_base_url||'').trim();
  if(connType!=='api'&&!apiUrl) return '';
  const statusHtml=renderConnStatusHint(item,'api');
  return `<div class="fz-conn-panel fz-conn-panel--api" data-conn-panel="api">
    <div class="fz-conn-panel-head">
      <span class="fz-conn-panel-title">Setari API B2B</span>
      <span class="fz-conn-panel-badge">API</span>
    </div>
    <div class="fz-conn-fields">
      <div class="fz-conn-field">
        <span class="mb-1 block text-sm">URL API</span>
        <div class="fz-conn-readonly">${escapeHtml(apiUrl||'Neconfigurat')}</div>
      </div>
      <div class="fz-conn-field">
        <span class="mb-1 block text-sm">Token / credentiale</span>
        <div class="fz-conn-readonly">${escapeHtml(maskSecret(!!item.has_api_token))}</div>
      </div>
    </div>
    <div class="fz-conn-actions">
      <button type="button" class="fz-conn-btn" data-action="test-api" data-id="${escapeHtml(sid)}">Test conexiune</button>
    </div>
    ${statusHtml}
  </div>`;
}
function renderEmailChannelHint(item){
  const type=String(item.connection_type||'').toLowerCase();
  if(type!=='email') return '';
  const inbox=String(item.conn_email_inbox||'').trim();
  if(!inbox) return '';
  return `<div class="fz-conn-field">
    <span>Inbox email (canal principal)</span>
    <div class="fz-conn-readonly">${escapeHtml(inbox)}</div>
  </div>`;
}
function renderConnStatusHint(item,target){
  const feedStatus=feedFilesStatusHtml(item);
  if(feedStatus) return feedStatus;
  const targetType=String(target||'').toLowerCase();
  const connType=String(item.connection_type||'').toLowerCase();
  const testMsg=String(item.last_test_message||'').trim();
  const testOk=item.last_test_status==='success';
  const testAt=String(item.last_test_at||'').trim();
  if(testMsg){
    const when=testAt?(' · '+escapeHtml(testAt)):'';
    return `<div class="fz-conn-status ${testOk?'is-ok':'is-error'}" id="furnizori-conn-status">${escapeHtml(testMsg)}${when}</div>`;
  }
  if(targetType==='api') return '';
  const host=String(item.conn_host||'').trim();
  const login=String(item.conn_username||'').trim();
  const hasPwd=!!item.has_conn_password;
  if(host&&(login||hasPwd)){
    return `<div class="fz-conn-status is-ok" id="furnizori-conn-status">Conexiune configurata (${escapeHtml(host)}). Apasa «Test conexiune» pentru verificare.</div>`;
  }
  if(connType==='email'){
    const inbox=String(item.conn_email_inbox||'').trim();
    if(inbox){
      return `<div class="fz-conn-status is-ok" id="furnizori-conn-status">Canal Email activ — inbox ${escapeHtml(inbox)}. Fisierele din folderul local apar in raportul de sincronizare.</div>`;
    }
  }
  if(host){
    return `<div class="fz-conn-status" id="furnizori-conn-status">Host setat: ${escapeHtml(host)}. Completeaza login si parola.</div>`;
  }
  return '';
}
function renderFtpPanel(item,sid){
  const pwdPlaceholder=item.has_conn_password?'Pastreaza parola salvata':'Parola FTP';
  const statusHtml=renderConnStatusHint(item,'ftp');
  return `<div class="fz-conn-panel fz-conn-panel--ftp" data-conn-panel="ftp" data-supplier-id="${escapeHtml(sid)}">
    <div class="fz-conn-panel-head">
      <span class="fz-conn-panel-title">Conexiune FTP</span>
      <span class="fz-conn-panel-badge">FTP / SFTP</span>
    </div>
    ${renderEmailChannelHint(item)}
    <div class="fz-conn-fields">
      <label class="fz-conn-field">
        <span>URL FTP</span>
        <input type="text" name="conn_host" value="${escapeHtml(item.conn_host||'')}" placeholder="ftp.exemplu.ro" autocomplete="off">
      </label>
      <label class="fz-conn-field">
        <span>Login</span>
        <input type="text" name="conn_username" value="${escapeHtml(item.conn_username||'')}" placeholder="utilizator" autocomplete="off">
      </label>
      <label class="fz-conn-field">
        <span>Parola</span>
        <input type="password" name="conn_password" value="" placeholder="${escapeHtml(pwdPlaceholder)}" autocomplete="new-password">
      </label>
    </div>
    <div class="fz-conn-actions">
      <button type="button" class="fz-conn-btn" data-action="test-ftp" data-id="${escapeHtml(sid)}">Test conexiune</button>
      <button type="button" class="fz-conn-btn fz-conn-btn--primary" data-action="sync-now" data-id="${escapeHtml(sid)}">Sincronizează acum</button>
    </div>
    <div class="fz-conn-status" data-ftp-status="${escapeHtml(sid)}">${statusHtml}</div>
  </div>`;
}
function hasApiPanel(item){
  const connType=String(item.connection_type||'api').toLowerCase();
  const apiUrl=String(item.api_base_url||'').trim();
  return connType==='api'||!!apiUrl;
}
function renderConnButtons(item){
  const sid=supplierId(item);
  const showApi=hasApiPanel(item);
  const connLabel=connectionChannelLabel(item);
  const btns=[
    `<button type="button" class="fz-card-conn-btn" data-action="open-conn" data-conn-type="ftp" data-id="${escapeHtml(sid)}">${fzIcon('folder')}Conexiune ${escapeHtml(connLabel)}</button>`,
    showApi?`<button type="button" class="fz-card-conn-btn fz-card-conn-btn--api" data-action="open-conn" data-conn-type="api" data-id="${escapeHtml(sid)}">${fzIcon('plug')}Setări API B2B</button>`:''
  ].filter(Boolean).join('');
  return `<div class="fz-card-conn-row">${btns}</div>`;
}
function findSupplierById(id){
  const num=Number(id);
  return furnizori.find(item=>supplierId(item)===num)||null;
}
function openConnModal(type,id){
  const item=findSupplierById(id);
  if(!item){showToast('Furnizor negăsit. Reîncarcă lista.',true);return;}
  const sid=supplierId(item);
  const html=type==='api'?renderApiPanel(item,sid):renderFtpPanel(item,sid);
  if(!html){showToast('Secțiunea nu este disponibilă pentru acest furnizor.',true);return;}
  const channel=connectionChannelLabel(item);
  if(connModalTitle) connModalTitle.textContent=type==='api'?'Setări API B2B':('Conexiune '+channel);
  if(connModalSub) connModalSub.textContent=type==='api'?'URL API, credentiale și test conexiune.':('Host '+channel.toLowerCase()+', login, parolă și sincronizare.');
  if(connModalBody){
    connModalBody.innerHTML=html;
    connModalBody.dataset.supplierId=String(sid);
    connModalBody.dataset.connType=type;
  }
  connModal?.classList.add('is-open');
  document.body.classList.add('fz-modal-open');
  closeAllCardMenus();
}
function closeConnModal(){
  connModal?.classList.remove('is-open');
  if(connModalBody){
    connModalBody.innerHTML='';
    delete connModalBody.dataset.supplierId;
    delete connModalBody.dataset.connType;
  }
  if(!modal?.classList.contains('is-open')) document.body.classList.remove('fz-modal-open');
}
function refreshConnModalIfOpen(id){
  if(!connModal?.classList.contains('is-open')||!connModalBody) return;
  const sid=Number(connModalBody.dataset.supplierId||0);
  if(sid!==Number(id)) return;
  openConnModal(connModalBody.dataset.connType||'ftp',id);
}
function connContextRoot(card){
  if(card) return card;
  if(connModal?.classList.contains('is-open')&&connModalBody) return connModalBody;
  return null;
}
function connPayloadFromCard(card){
  const root=connContextRoot(card);
  const panel=root?.querySelector('[data-conn-panel="ftp"]');
  if(!panel) return {};
  const payload={};
  ['conn_host','conn_username','conn_password','conn_remote_path'].forEach(name=>{
    const field=panel.querySelector(`[name="${name}"]`);
    if(!field) return;
    const val=String(field.value||'').trim();
    if(val!=='') payload[name]=val;
  });
  return payload;
}
function setFtpStatus(card,message,isError){
  const root=connContextRoot(card);
  const el=root?.querySelector('[data-ftp-status]');
  if(!el) return;
  el.innerHTML=message?escapeHtml(message):'';
  el.classList.toggle('is-error',!!isError&&!!message);
  el.classList.toggle('is-ok',!isError&&!!message);
}
function renderSyncReport(item){
  const code=supplierCode(item);
  const hasFiles=hasFeedFiles(item);
  const syncedToday=!!item.sync_today;
  const ready=!!(item.files_ready||hasFiles);
  const badgeClass=(syncedToday||ready)?'fz-badge--sync-yes':'fz-badge--sync-no';
  const badgeText=syncedToday
    ?'Sincronizat azi DA'
    :(ready?'Sincronizat azi NU · Fisiere pe server':'Sincronizat azi NU');
  const lastAt=escapeHtml(String(item.sync_last_at_label||'').trim()||'—');
  const source=escapeHtml(item.sync_source_label||'—');
  const freshHint=ready&&!syncedToday
    ?'<p class="fz-sync-hint">Fisiere in folderul local — ultima actualizare nu e de azi.</p>'
    :'';
  return `<div class="fz-sync-report" id="furnizori-sync-report" data-supplier-code="${escapeHtml(code)}">
    <div class="fz-sync-head">
      <span class="fz-sync-title">Sincronizare zilnica</span>
      <span class="fz-badge ${badgeClass}" id="furnizori-sync-badge" data-supplier-code="${escapeHtml(code)}">${badgeText}</span>
    </div>
    ${freshHint}
    <div class="fz-sync-meta">
      <div><span class="fz-sync-meta-label">Ultima descarcare</span><span id="furnizori-sync-last-at" data-supplier-code="${escapeHtml(code)}">${lastAt}</span></div>
      <div><span class="fz-sync-meta-label">Canal</span><span id="furnizori-sync-channel" data-supplier-code="${escapeHtml(code)}">${source}</span></div>
    </div>
    <div class="fz-sync-files">
      <span class="fz-sync-meta-label">Fisiere primite (FTP/API)</span>
      ${formatSyncFiles(item)}
    </div>
  </div>`;
}
function supplierSortKey(item){
  if(item.is_import_supplier&&item.import_priority!=null) return Number(item.import_priority);
  return 1000;
}
function sortFurnizori(rows){
  return rows.slice().sort((a,b)=>{
    const pa=supplierSortKey(a), pb=supplierSortKey(b);
    if(pa!==pb) return pa-pb;
    return String(a.name||'').localeCompare(String(b.name||''),'ro');
  });
}
function initials(name,code){
  if(code)return String(code).slice(0,2).toUpperCase();
  const p=String(name||'').trim().split(/\s+/);
  return p.length>=2?(p[0][0]+p[1][0]):(p[0]||'FZ').slice(0,2).toUpperCase();
}

function filtered(){return furnizori;}

function renderStats(){
  const active=furnizori.filter(i=>normalizeStatus(i.status)==='active');
  document.getElementById('furnizori-count').textContent=listMeta.total||furnizori.length;
  document.getElementById('furnizori-stat-active').textContent=active.length;
  document.getElementById('furnizori-stat-blocked').textContent=furnizori.filter(i=>normalizeStatus(i.status)==='blocked').length;
  const products=furnizori.reduce((s,i)=>s+Number(i.products_count||0),0);
  document.getElementById('furnizori-stat-products').textContent=products>=1000?formatProducts(products):products;
  const intervals=active.map(i=>Number(i.scan_interval_minutes||60));
  const avg=intervals.length?Math.round(intervals.reduce((a,b)=>a+b,0)/intervals.length):60;
  document.getElementById('furnizori-stat-interval').textContent=avg;
}

function renderGrid(){
  const rows=sortFurnizori(filtered());
  grid.classList.toggle('list-view',viewMode==='list');
  if(!rows.length){
    const isBlockedTab=supplierListTab==='blocked';
    grid.innerHTML=`<div class="fz-empty">
      <div class="fz-empty-icon"><i data-lucide="${isBlockedTab?'lock':'truck'}" class="size-7"></i></div>
      <p class="font-medium text-slate-700">${isBlockedTab?'Niciun furnizor blocat':'Niciun furnizor activ'}</p>
      <p class="mt-1 text-sm opacity-60">${isBlockedTab?'Furnizorii blocați apar aici după acțiunea Blochează.':'Adaugă primul furnizor sau schimbă filtrele.'}</p>
      ${isBlockedTab?'':`<button type="button" class="fz-btn-primary mt-4" onclick="document.getElementById('furnizori-open-create').click()">
        ${fzIcon('plus')}Adaugă furnizor
      </button>`}
    </div>`;
    if(window.BpaPagination)BpaPagination.render(paginationEl,listMeta,(p)=>load(p));
    return;
  }
  grid.innerHTML=rows.map(item=>{
    const st=normalizeStatus(item.status);
    const blocked=st==='blocked';
    const sid=supplierId(item);
    const profileUrl=sid>0?'/admin/profilefurnizori?randomn_id='+encodeURIComponent(sid):'#';
    const priceUrl=sid>0?profileUrl+'&tab=pret':'#';
    const codeLabel=item.supplier_code||item.code||initials(item.name,item.code);
    const priorityBadge=item.is_import_supplier&&item.import_priority!=null
      ? `<span class="fz-badge fz-badge--code">P${escapeHtml(item.import_priority)}</span>` : '';
    return`<article class="fz-card ${blocked?'is-blocked':''}" data-id="${escapeHtml(sid)}">
      <div class="fz-card-head ${blocked?'is-blocked':''}">
        <div class="fz-card-avatar" aria-hidden="true">${escapeHtml(initials(item.name,item.code))}</div>
        <div class="fz-card-head-content">
          <div class="fz-card-head-top">
            <h3 class="fz-card-name">${escapeHtml(item.name)}</h3>
            ${renderCardHeadMenu(blocked,st,sid)}
          </div>
          <div class="fz-card-badges">
            <span class="fz-badge fz-badge--code">${escapeHtml(codeLabel)}</span>
            ${priorityBadge}
            <span class="fz-badge ${blocked?'fz-badge--blocked':'fz-badge--active'}">${blocked?'Blocat':'Activ'}</span>
          </div>
          <div class="fz-card-scan">${item.is_import_supplier?('Import · '+escapeHtml(item.import_vat_label||'lista pret')):'Furnizor manual'} · scanare la ${escapeHtml(item.scan_interval_minutes||60)} min</div>
        </div>
      </div>
      <div class="fz-metrics">
        <div class="fz-metric">
          <span class="fz-metric-label">Compensator pre-import</span>
          <span class="fz-metric-value">${escapeHtml(formatMarkup(item))}</span>
        </div>
        <div class="fz-metric">
          <span class="fz-metric-label">Stoc 0</span>
          <span class="fz-metric-value text-success">${escapeHtml(item.stock_zero_label||stockLabel(item.stock_zero_mode))}</span>
        </div>
        <div class="fz-metric">
          <span class="fz-metric-label">Produse</span>
          <span class="fz-metric-value">${escapeHtml(formatProductsBreakdown(item))}</span>
        </div>
      </div>
      ${renderConnButtons(item)}
      ${renderSyncReport(item)}
      <div class="fz-card-foot">
        <div class="fz-card-foot-main">
          <a href="${profileUrl}" class="fz-btn-config" data-profile-link="1" data-supplier-id="${escapeHtml(sid)}">${fzIcon('settings')}Configurează</a>
          <a href="${priceUrl}" class="fz-btn-price" data-profile-link="1" data-supplier-id="${escapeHtml(sid)}">${fzIcon('badge-percent')}Formare preț</a>
        </div>
      </div>
    </article>`;
  }).join('');
  if(window.BpaPagination)BpaPagination.render(paginationEl,listMeta,(p)=>load(p));
}

function render(){renderStats();renderGrid()}
function openModal(item){
  form.reset();
  delete form.dataset.wasOpened;
  form.dataset.action=item?'edit':'add';
  const titleEl=document.getElementById('furnizori-modal-title');
  const subEl=modal?.querySelector('.fz-modal-head-text p');
  if(titleEl)titleEl.textContent=item?'Editează furnizor':'Furnizor nou';
  if(subEl)subEl.textContent=item?'Modifică datele de bază ale furnizorului.':'Completează datele de bază. Restul setărilor le faci după salvare.';
  if(item)Object.entries(item).forEach(([k,v])=>{const f=form.elements.namedItem(k);if(f)f.value=v??''});
  form.dataset.wasOpened='1';
  modal?.classList.add('is-open');
  document.body.classList.add('fz-modal-open');
  setTimeout(()=>document.getElementById('fz-field-name')?.focus(),100);
}
function closeModal(){
  modal?.classList.remove('is-open');
  if(!connModal?.classList.contains('is-open')) document.body.classList.remove('fz-modal-open');
}
async function load(page){
  if(page)currentPage=page;
  const payload={page:currentPage,per_page:10,q:(filters.search?.value||'').trim(),status:supplierListTab==='blocked'?'blocked':'active'};
  const data=await apiCall('list',payload);
  const parsed=unwrapFurnizoriList(data);
  furnizori=Array.isArray(parsed.items)?parsed.items:[];
  listMeta=parsed;
  currentPage=parsed.page;
  render();
}

document.getElementById('furnizori-open-create')?.addEventListener('click',()=>openModal(null));
document.getElementById('furnizori-close-conn-modal')?.addEventListener('click',closeConnModal);
connModal?.addEventListener('click',e=>{if(e.target===connModal)closeConnModal()});
document.getElementById('furnizori-close-modal')?.addEventListener('click',closeModal);
document.getElementById('furnizori-cancel')?.addEventListener('click',closeModal);
modal?.addEventListener('click',e=>{if(e.target===modal)closeModal()});
document.addEventListener('keydown',e=>{
  if(e.key!=='Escape') return;
  if(connModal?.classList.contains('is-open')) closeConnModal();
  else if(modal?.classList.contains('is-open')) closeModal();
});
Object.values(filters).forEach(f=>{f?.addEventListener('input',()=>{currentPage=1;load().catch(e=>showToast(e.message,true))});f?.addEventListener('change',()=>{currentPage=1;load().catch(e=>showToast(e.message,true))})});
document.querySelectorAll('.fz-view-btn').forEach(btn=>{
  btn.addEventListener('click',()=>{
    viewMode=btn.dataset.view||'grid';
    document.querySelectorAll('.fz-view-btn').forEach(b=>b.classList.toggle('active',b.dataset.view===viewMode));
    renderGrid();
  });
});
form?.addEventListener('submit',async e=>{e.preventDefault();
  const nameField=form.elements.namedItem('name');
  const nameVal=String(nameField?.value||'').trim();
  if(!nameVal){
    showToast('Numele furnizorului este obligatoriu.',true);
    nameField?.focus();
    return;
  }
  try{
  const data=formToObject(form);
  if(form.dataset.action==='add'){const r=await apiCall('add',data);closeModal();window.location.href='/admin/profilefurnizori?randomn_id='+r.randomn_id;return}
  await apiCall('edit',data);closeModal();showToast('Furnizor salvat.',false);await load();
}catch(err){showToast(err.message,true)}});
document.addEventListener('click',e=>{
  const trigger=e.target.closest('[data-menu-trigger]');
  if(trigger){
    e.stopPropagation();
    const menu=trigger.closest('.fz-card-head-menu');
    const popup=menu?.querySelector('.fz-card-menu-popup');
    if(!popup) return;
    const willOpen=!popup.classList.contains('is-open');
    closeAllCardMenus();
    popup.classList.toggle('is-open',willOpen);
    trigger.setAttribute('aria-expanded',willOpen?'true':'false');
    return;
  }
  if(!e.target.closest('.fz-card-head-menu')) closeAllCardMenus();
});
async function handleSupplierAction(btn,card){
  const id=Number(btn.dataset.id);
  const ctx=connContextRoot(card);
  closeAllCardMenus();
  try{
    if(btn.dataset.action==='open-conn'){
      openConnModal(btn.dataset.connType||'ftp',id);
      return;
    }
    if(btn.dataset.action==='test-ftp'){
      btn.disabled=true;
      setFtpStatus(ctx,'Testez conexiunea FTP...',false);
      const result=await apiCall('testconnection',{randomn_id:id,test_target:'ftp',...connPayloadFromCard(ctx)});
      const ok=result.last_test_status==='success';
      setFtpStatus(ctx,result.last_test_message||'Test finalizat.',!ok);
      if(ok) showToast('Conexiune FTP OK.',false); else showToast(result.last_test_message||'Test FTP esuat.',true);
      await load();
      refreshConnModalIfOpen(id);
      return;
    }
    if(btn.dataset.action==='test-api'){
      btn.disabled=true;
      const result=await apiCall('testconnection',{randomn_id:id,test_target:'api'});
      const ok=result.last_test_status==='success';
      showToast(result.last_test_message||'Test finalizat.',!ok);
      await load();
      refreshConnModalIfOpen(id);
      return;
    }
    if(btn.dataset.action==='sync-now'){
      if(!confirm('Descarc fisierele CSV de pe FTP acum?')) return;
      btn.disabled=true;
      setFtpStatus(ctx,'Sincronizez fisierele...',false);
      const result=await apiCall('syncnow',{randomn_id:id,...connPayloadFromCard(ctx)});
      const names=Array.isArray(result.files)?result.files.map(f=>f.name).filter(Boolean):[];
      setFtpStatus(ctx,result.message||(names.length?('Fisiere: '+names.join(', ')):'Sincronizare reusita.'),false);
      showToast(result.message||'Sincronizare reusita.',false);
      await load();
      refreshConnModalIfOpen(id);
      return;
    }
    if(btn.dataset.action==='toggle'){
      const wasActive=btn.dataset.status==='active';
      await apiCall(wasActive?'block':'unblock',{randomn_id:id});
      showToast(wasActive?'Furnizor blocat. Îl găsești în tab Furnizori blocați.':'Furnizor activat. Apare din nou în lista activă.',false);
      closeConnModal();
      if(wasActive&&supplierListTab==='lista'){await load();return}
      if(!wasActive&&supplierListTab==='blocked'){await load();return}
      if(wasActive){setPageTab('blocked');return}
      setPageTab('lista');
      return;
    }
    if(btn.dataset.action==='delete'){
      if(!confirm('Confirmi ștergerea furnizorului?')) return;
      closeConnModal();
      await apiCall('delete',{randomn_id:id});
      showToast('Furnizor șters.',false);
      await load();
    }
  }catch(err){
    if(btn.dataset.action==='test-ftp'||btn.dataset.action==='sync-now') setFtpStatus(ctx,err.message,true);
    showToast(err.message,true);
  }finally{
    btn.disabled=false;
  }
}
grid?.addEventListener('click',async e=>{
  const profileLink=e.target.closest('a[data-profile-link]');
  if(profileLink){
    e.preventDefault();
    const sid=Number(profileLink.dataset.supplierId||0);
    if(sid<=0){
      showToast('ID furnizor invalid. Reîncarcă lista.',true);
      return;
    }
    const tab=profileLink.classList.contains('fz-btn-price')?'&tab=pret':'';
    window.location.href='/admin/profilefurnizori?randomn_id='+encodeURIComponent(String(sid))+tab;
    return;
  }
  const btn=e.target.closest('button[data-action]');if(!btn)return;
  await handleSupplierAction(btn,btn.closest('.fz-card'));
});
connModalBody?.addEventListener('click',async e=>{
  const btn=e.target.closest('button[data-action]');if(!btn)return;
  e.stopPropagation();
  await handleSupplierAction(btn,null);
});
function setPageTab(tab){
  const id=['compare','lista','blocked'].includes(tab)?tab:'lista';
  const listPaneActive=id==='lista'||id==='blocked';
  supplierListTab=id==='blocked'?'blocked':'lista';
  document.querySelectorAll('.furnizori-page .fz-page-tab').forEach(btn=>{
    const on=btn.dataset.pageTab===id;
    btn.classList.toggle('active',on);
    btn.setAttribute('aria-selected',on?'true':'false');
  });
  document.querySelectorAll('.furnizori-page .fz-page-pane').forEach(pane=>{
    const paneId=pane.dataset.pagePane;
    pane.classList.toggle('active',paneId==='compare'?id==='compare':listPaneActive);
  });
  const actions=document.querySelector('.furnizori-page .fz-actions');
  if(actions) actions.style.display=id==='lista'?'':'none';
  const countLabel=document.getElementById('furnizori-count-label');
  if(countLabel) countLabel.textContent=id==='blocked'?'furnizori blocați':'furnizori activi';
  if(listPaneActive){
    currentPage=1;
    load().catch(e=>showToast(e.message,true));
  }
  try{
    const u=new URL(window.location.href);
    if(id==='compare'){u.searchParams.set('tab','compare');}
    else if(id==='blocked'){u.searchParams.set('tab','blocked');}
    else{u.searchParams.delete('tab');}
    history.replaceState(null,'',u.pathname+(u.search||'')+(u.hash||''));
  }catch(_){}
}
window.furnizoriSetPageTab=setPageTab;
document.querySelectorAll('.furnizori-page .fz-page-tab').forEach(btn=>{
  btn.addEventListener('click',()=>setPageTab(btn.dataset.pageTab||'compare'));
});
function initFurnizoriPageTab(){
  const params=new URLSearchParams(window.location.search);
  const hash=String(window.location.hash||'');
  const tabParam=params.get('tab');
  if(tabParam==='compare'||hash==='#price-logic-panel'||hash==='#compare'){
    setPageTab('compare');
  }else if(tabParam==='blocked'){
    setPageTab('blocked');
  }else{
    setPageTab('lista');
  }
}
window.addEventListener('besoiu:open-price-logic',()=>setPageTab('compare'));

function bootFurnizori(){
  initFurnizoriPageTab();
}
if(document.readyState==='loading'){
  document.addEventListener('DOMContentLoaded',bootFurnizori);
}else{
  bootFurnizori();
}
})();
</script>
