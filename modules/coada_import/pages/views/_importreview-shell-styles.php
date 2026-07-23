<?php
declare(strict_types=1);
/** Stiluri shell Import Review — folosit de importreview + importreview-normalize */
?>
<style>
.import-review-page.irv-layout,
.irv-layout {
    --irv-teal: #0d9488;
    --irv-teal-dark: #0f766e;
    --irv-ink: #0f172a;
    --irv-muted: #64748b;
    --irv-line: #e2e8f0;
    --irv-bg: #f8fafc;
    --irv-radius: 14px;
    --irv-shadow: 0 1px 2px rgba(15,23,42,.04), 0 8px 24px rgba(15,23,42,.05);
    display: block;
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
    margin-top: -0.5rem;
}
.import-review-page .admin-panel.irv-shell {
    display: flex;
    flex-direction: column;
    gap: 16px;
    width: 100%;
    max-width: 100%;
    padding: 0 !important;
    border: none !important;
    background: transparent !important;
    box-shadow: none !important;
}
.irv-shell > * { width: 100%; max-width: 100%; box-sizing: border-box; }

.irv-main-tabs {
    display: flex;
    gap: 8px;
    padding: 6px;
    border-radius: 14px;
    border: 1px solid var(--irv-line);
    background: #fff;
    box-shadow: var(--irv-shadow);
}
.irv-main-tabs__item {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 11px 16px;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 700;
    color: var(--irv-muted);
    text-decoration: none;
    border: 1px solid transparent;
    transition: background .15s, color .15s, box-shadow .15s;
}
.irv-main-tabs__item:hover { color: var(--irv-ink); background: var(--irv-bg); }
.irv-main-tabs__item.is-active {
    color: #fff;
    background: linear-gradient(135deg, var(--irv-teal), #059669);
    box-shadow: 0 4px 14px rgba(13,148,136,.28);
}
.irv-main-tabs__icon { width: 17px; height: 17px; flex-shrink: 0; }

.irv-hero {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 20px;
    flex-wrap: wrap;
    padding: 22px 24px;
    border-radius: 18px;
    border: 1px solid var(--irv-line);
    background: linear-gradient(135deg, #ecfdf5 0%, #fff 55%, #f0f9ff 100%);
    box-shadow: var(--irv-shadow);
}
.irv-hero__eyebrow {
    font-size: 11px;
    font-weight: 700;
    letter-spacing: .08em;
    text-transform: uppercase;
    color: var(--irv-teal-dark);
    margin-bottom: 4px;
}
.irv-hero__title {
    margin: 0;
    font-size: 1.65rem;
    font-weight: 800;
    color: var(--irv-ink);
    line-height: 1.2;
}
.irv-hero__desc {
    margin: 8px 0 0;
    max-width: 58ch;
    font-size: 13px;
    line-height: 1.6;
    color: var(--irv-muted);
}
.irv-hero__desc code {
    font-size: 12px;
    padding: 2px 6px;
    border-radius: 6px;
    background: rgba(255,255,255,.8);
    border: 1px solid var(--irv-line);
}

.irv-card {
    border: 1px solid var(--irv-line);
    border-radius: var(--irv-radius);
    background: #fff;
    box-shadow: var(--irv-shadow);
    overflow: hidden;
}
.irv-card__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
    padding: 16px 20px;
    border-bottom: 1px solid var(--irv-line);
    background: linear-gradient(180deg, var(--irv-bg), #fff);
}
.irv-card__title {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: 15px;
    font-weight: 800;
    color: var(--irv-ink);
}
.irv-card__title-icon { width: 18px; height: 18px; color: var(--irv-teal-dark); }
.irv-card__hint { display: block; margin-top: 4px; font-size: 12px; color: var(--irv-muted); line-height: 1.5; }

.irv-field { display: flex; flex-direction: column; gap: 6px; min-width: 0; }
.irv-field__label {
    font-size: 11px;
    font-weight: 700;
    letter-spacing: .04em;
    text-transform: uppercase;
    color: var(--irv-muted);
}
.irv-input {
    width: 100%;
    height: 42px;
    padding: 0 14px;
    border-radius: 10px;
    border: 1px solid var(--irv-line);
    background: #fff;
    font-size: 14px;
    color: var(--irv-ink);
    box-sizing: border-box;
    transition: border-color .15s, box-shadow .15s;
}
.irv-input:focus {
    outline: none;
    border-color: #5eead4;
    box-shadow: 0 0 0 3px rgba(13,148,136,.15);
}
.irv-select { cursor: pointer; }

.irv-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    height: 42px;
    padding: 0 18px;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 700;
    line-height: 1;
    border: 1px solid transparent;
    cursor: pointer;
    text-decoration: none;
    white-space: nowrap;
    transition: transform .15s, box-shadow .15s, background .15s;
}
.irv-btn:hover { transform: translateY(-1px); }
.irv-btn--sm { height: 36px; padding: 0 14px; font-size: 12px; }
.irv-btn--primary { background: linear-gradient(135deg, var(--irv-teal), #059669); color: #fff; box-shadow: 0 4px 14px rgba(13,148,136,.25); }
.irv-btn--ghost { background: #fff; color: var(--irv-teal-dark); border-color: #99f6e4; }
.irv-btn--violet { background: linear-gradient(135deg, #0d9488, #0f766e); color: #fff; box-shadow: 0 4px 14px rgba(15,118,110,.22); }
.irv-btn--secondary { background: #fff; color: #334155; border-color: var(--irv-line); }
.irv-btn:disabled { opacity: .45; cursor: not-allowed; transform: none; box-shadow: none; }

.admin-table-wrap {
    width: 100%;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}
.import-review-page .irv-table {
    min-width: 720px;
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
}
.import-review-page .irv-table thead {
    background: linear-gradient(90deg, #047857, #0f172a) !important;
}
.import-review-page .irv-table thead th,
.import-review-page .irv-table .irv-th {
    padding: 12px 14px;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: .06em;
    text-transform: uppercase;
    color: #fff !important;
    background: transparent !important;
    border-bottom: 1px solid rgba(255,255,255,.15) !important;
    white-space: nowrap;
    text-align: left;
}
.import-review-page .irv-table tbody tr {
    border-bottom: 1px solid #f1f5f9;
    transition: background .12s;
}
.import-review-page .irv-table tbody tr:hover { background: #f8fafc; }
.import-review-page .irv-table .irv-td {
    padding: 12px 14px;
    vertical-align: middle;
    font-size: 14px;
    line-height: 1.45;
    color: #334155;
}
.irv-th--check { width: 44px; text-align: center; }
.irv-th--actions { width: 140px; min-width: 140px; }
.irv-td--check { text-align: center; width: 44px; }
.irv-td--actions { width: 140px; vertical-align: middle !important; }

.irv-row-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 34px;
    padding: 6px 12px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 700;
    border: 1px solid transparent;
    cursor: pointer;
    background: #f8fafc;
    color: #475569;
    margin-right: 4px;
    margin-bottom: 4px;
}
.irv-row-btn--violet { background: #f0fdfa; color: #0f766e; border-color: #ddd6fe; }
.irv-row-btn--teal { background: #f0fdfa; color: #0f766e; border-color: #99f6e4; }
.irv-row-btn:hover { filter: brightness(.97); }

.irv-pill {
    display: inline-flex;
    align-items: center;
    padding: 4px 10px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 700;
    white-space: nowrap;
}
.irv-pill--green { background: #dcfce7; color: #166534; }
.irv-pill--blue { background: #ccfbf1; color: #0f766e; }
.irv-pill--slate { background: #f1f5f9; color: #475569; }
.irv-pill--amber { background: #fef3c7; color: #92400e; }
.irv-pill--red { background: #fee2e2; color: #991b1b; }
.irv-pill--violet { background: #ede9fe; color: #0f766e; }
</style>
