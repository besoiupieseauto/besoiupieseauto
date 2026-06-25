<style>
  .furnizori-page { --fz-primary: #2563eb; --fz-primary-dark: #1d4ed8; --fz-border: #e5e7eb; --fz-muted: #64748b; --fz-success: #16a34a; --fz-danger: #dc2626; --fz-warning: #d97706; --fz-surface: #f8fafc; --fz-ink: #0f172a; }
  .furnizori-page .fz-header { display: flex; flex-wrap: wrap; align-items: flex-start; justify-content: space-between; gap: 16px; margin-top: 8px; }
  .furnizori-page .fz-title { font-size: 1.35rem; font-weight: 700; letter-spacing: -0.02em; margin: 0; color: #0f172a; }
  .furnizori-page .fz-subtitle { font-size: 0.85rem; color: var(--fz-muted); margin: 4px 0 0; }
  .furnizori-page .fz-actions { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
  .furnizori-page .fz-btn-primary,
  .furnizori-page button.fz-btn-primary,
  .furnizori-page a.fz-btn-primary {
    display: inline-flex; align-items: center; gap: 8px; height: 40px; padding: 0 18px; border-radius: 10px;
    border: none !important; background: #2563eb !important; color: #ffffff !important;
    font-size: 0.85rem; font-weight: 600; cursor: pointer; text-decoration: none !important;
    box-shadow: 0 2px 8px rgba(37,99,235,.35); transition: transform .15s, box-shadow .15s;
  }
  .furnizori-page .fz-btn-primary:hover { transform: translateY(-1px); background: #1d4ed8 !important; color: #fff !important; }
  .furnizori-page .fz-btn-outline,
  .furnizori-page a.fz-btn-outline {
    display: inline-flex; align-items: center; gap: 8px; height: 40px; padding: 0 16px; border-radius: 10px;
    border: 1.5px solid #cbd5e1 !important; background: #fff !important; color: #334155 !important;
    font-size: 0.85rem; font-weight: 600; cursor: pointer; text-decoration: none !important;
  }
  .furnizori-page .fz-btn-outline:hover { border-color: #2563eb !important; color: #2563eb !important; background: #eff6ff !important; }
  .furnizori-page .fz-summary-caption {
    margin: 14px 0 0; padding: 10px 16px; font-size: 0.68rem; font-weight: 700;
    text-transform: uppercase; letter-spacing: .05em; color: var(--fz-muted);
    background: var(--fz-surface); border: 1px solid var(--fz-border); border-bottom: none; border-radius: 12px 12px 0 0;
  }
  .furnizori-page .fz-summary-metrics {
    display: grid !important; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 0; margin-top: 0;
    background: var(--fz-surface); border: 1px solid var(--fz-border); border-top: none; border-radius: 0 0 12px 12px; overflow: hidden;
  }
  .furnizori-page .fz-summary-metrics .fz-metric {
    display: flex !important; flex-direction: column !important; padding: 14px 16px; min-width: 0;
    border-right: 1px solid var(--fz-border) !important;
  }
  .furnizori-page .fz-summary-metrics .fz-metric:last-child { border-right: none; }
  .furnizori-page .fz-metric-label { font-size: 0.65rem; color: var(--fz-muted); text-transform: uppercase; letter-spacing: .05em; font-weight: 600; }
  .furnizori-page .fz-metric-value { font-size: 1.05rem; font-weight: 700; color: var(--fz-ink); margin-top: 2px; }
  .furnizori-page .fz-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 18px; margin-top: 20px; }
  .furnizori-page .fz-card {
    background: #fff; border: 1px solid var(--fz-border); border-radius: 16px; overflow: hidden;
    box-shadow: 0 1px 2px rgba(15,23,42,.04), 0 8px 24px rgba(15,23,42,.04);
    transition: box-shadow .22s, transform .22s, border-color .22s;
    display: flex; flex-direction: column; position: relative; text-decoration: none !important; color: inherit !important;
  }
  .furnizori-page .fz-card::before {
    content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 4px;
    background: linear-gradient(180deg, #22c55e, #16a34a); border-radius: 16px 0 0 16px;
  }
  .furnizori-page .fz-card.is-muted::before { background: linear-gradient(180deg, #94a3b8, #64748b); }
  .furnizori-page .fz-card.is-link:hover { box-shadow: 0 4px 12px rgba(15,23,42,.06), 0 16px 40px rgba(15,23,42,.08); transform: translateY(-2px); border-color: #d1d5db; }
  .furnizori-page .fz-card.is-muted { opacity: .72; pointer-events: none; }
  .furnizori-page .fz-card-head { display: flex; align-items: flex-start; gap: 14px; padding: 18px 18px 14px 22px; background: linear-gradient(180deg, #fff 0%, var(--fz-surface) 100%); min-height: auto; }
  .furnizori-page .fz-card-avatar {
    width: 52px; height: 52px; border-radius: 14px; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.95rem; font-weight: 800; color: #fff;
    background: linear-gradient(145deg, #334155 0%, #0f172a 100%);
    box-shadow: 0 4px 12px rgba(15,23,42,.18);
  }
  .furnizori-page .fz-card.is-muted .fz-card-avatar { background: linear-gradient(145deg, #94a3b8 0%, #64748b 100%); box-shadow: none; }
  .furnizori-page .fz-card-head-content { flex: 1; min-width: 0; }
  .furnizori-page .fz-card-name { font-size: 1.05rem; font-weight: 700; margin: 0; letter-spacing: -0.02em; color: var(--fz-ink); line-height: 1.25; }
  .furnizori-page .fz-card-desc { font-size: 0.78rem; color: var(--fz-muted); margin: 6px 0 0; line-height: 1.45; }
  .furnizori-page .fz-card-badges { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 10px; }
  .furnizori-page .fz-badge { display: inline-flex; align-items: center; padding: 3px 9px; border-radius: 999px; font-size: 0.68rem; font-weight: 600; border: 1px solid transparent; }
  .furnizori-page .fz-badge--active { background: #ecfdf5; color: #047857; border-color: #a7f3d0; }
  .furnizori-page .fz-badge--muted { background: #f1f5f9; color: #64748b; border-color: #e2e8f0; }
  .furnizori-page .fz-badge--soon { background: #fff7ed; color: #c2410c; border-color: #fed7aa; }
  .furnizori-page .fz-metrics { display: grid; grid-template-columns: repeat(2, 1fr); gap: 0; border-top: 1px solid var(--fz-border); background: var(--fz-surface); }
  .furnizori-page .fz-metric { padding: 12px 14px; display: flex; flex-direction: column; gap: 3px; border-right: 1px solid var(--fz-border); }
  .furnizori-page .fz-metrics .fz-metric:last-child { border-right: none; }
  .furnizori-page .fz-card-foot {
    display: flex; align-items: center; justify-content: space-between; gap: 8px;
    padding: 12px 16px 12px 22px; border-top: 1px solid var(--fz-border); background: #fff; margin-top: auto;
    font-size: 0.8rem; font-weight: 600; color: var(--fz-primary);
  }
  .furnizori-page .fz-card.is-muted .fz-card-foot { color: var(--fz-muted); }
  @media (max-width: 900px) {
    .furnizori-page .fz-summary-metrics { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .furnizori-page .fz-summary-metrics .fz-metric:nth-child(2) { border-right: none; }
    .furnizori-page .fz-summary-metrics .fz-metric:nth-child(-n+2) { border-bottom: 1px solid var(--fz-border); }
  }
</style>
