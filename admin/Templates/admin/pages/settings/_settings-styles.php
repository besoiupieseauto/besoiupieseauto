<style>
/* ── Settings Hub 2026 — full width în zona de conținut ── */
.admin-content:has(.settings-page--fullbleed) {
    max-width: none !important;
    width: 100% !important;
    padding-left: 12px !important;
    padding-right: 16px !important;
    box-sizing: border-box;
}

.settings-page {
    --st-primary: #059669;
    --st-primary-soft: #ecfdf5;
    --st-primary-ring: rgba(5, 150, 105, 0.25);
    --st-border: #e2e8f0;
    --st-border-soft: #f1f5f9;
    --st-text: #0f172a;
    --st-muted: #64748b;
    --st-surface: #ffffff;
    --st-bg: #f8fafc;
    --st-radius: 16px;
    --st-radius-sm: 10px;
    --st-shadow: 0 1px 2px rgba(15, 23, 42, 0.04), 0 8px 24px rgba(15, 23, 42, 0.06);
    --st-shadow-hover: 0 12px 32px rgba(15, 23, 42, 0.1);
    font-family: inherit;
    color: var(--st-text);
}
.settings-page--fullbleed {
    width: 100%;
    max-width: none;
    margin: 12px 0 24px;
    box-sizing: border-box;
}
.settings-page .hidden { display: none !important; }

/* Hero */
.settings-page .st-hero {
    display: flex; flex-wrap: wrap; align-items: flex-start; justify-content: space-between;
    gap: 16px; margin-bottom: 24px; padding: 24px 28px;
    background: linear-gradient(135deg, #ecfdf5 0%, #f0fdfa 40%, #fff 100%);
    border: 1px solid #a7f3d0; border-radius: var(--st-radius);
    box-shadow: var(--st-shadow);
}
.settings-page .st-hero__main { display: flex; gap: 16px; align-items: flex-start; }
.settings-page .st-hero__icon {
    width: 52px; height: 52px; border-radius: 14px; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    background: linear-gradient(135deg, #10b981, #047857); color: #fff;
    box-shadow: 0 4px 14px rgba(5, 150, 105, 0.35);
}
.settings-page .st-hero__title { margin: 0; font-size: 1.5rem; font-weight: 800; letter-spacing: -0.02em; line-height: 1.2; }
.settings-page .st-hero__sub { margin: 6px 0 0; font-size: 0.9rem; color: var(--st-muted); max-width: 36rem; line-height: 1.5; }
.settings-page .st-hero__actions { display: flex; gap: 8px; align-items: center; }

/* Tabs — pill segmented */
.settings-page .st-tabs {
    display: inline-flex; flex-wrap: wrap; gap: 4px; padding: 4px;
    background: var(--st-bg); border: 1px solid var(--st-border);
    border-radius: 12px; margin-bottom: 20px;
}
.settings-page .st-tab {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 10px 18px; border: none; border-radius: 9px;
    background: transparent; color: var(--st-muted);
    font-size: 0.875rem; font-weight: 600; cursor: pointer;
    transition: background .15s, color .15s, box-shadow .15s;
}
.settings-page .st-tab:hover { color: var(--st-text); background: rgba(255,255,255,.7); }
.settings-page .st-tab.is-active {
    background: var(--st-surface); color: var(--st-primary);
    box-shadow: 0 1px 3px rgba(15,23,42,.08);
}
.settings-page .st-tab svg { opacity: .75; flex-shrink: 0; }
.settings-page .st-tab.is-active svg { opacity: 1; stroke: var(--st-primary); }

/* Panels */
.settings-page .st-panel[hidden] { display: none !important; }
.settings-page .st-lead { font-size: 0.9rem; color: var(--st-muted); margin: 0 0 20px; line-height: 1.55; }
.settings-page .st-inline-link { color: var(--st-primary); font-weight: 600; text-decoration: none; }
.settings-page .st-inline-link:hover { text-decoration: underline; }

/* Cards */
.settings-page .st-card {
    background: var(--st-surface); border: 1px solid var(--st-border);
    border-radius: var(--st-radius); box-shadow: var(--st-shadow);
    overflow: hidden;
}
.settings-page .st-card__head {
    display: flex; align-items: flex-start; justify-content: space-between; gap: 12px;
    padding: 20px 24px 0;
}
.settings-page .st-card__title { margin: 0; font-size: 1.05rem; font-weight: 700; letter-spacing: -0.01em; }
.settings-page .st-card__desc { margin: 4px 0 0; font-size: 0.8rem; color: var(--st-muted); }
.settings-page .st-card--form { padding-bottom: 20px; }
.settings-page .st-card--table .st-table-wrap { padding: 16px 24px 24px; }
.settings-page .st-card--budget { margin-top: 20px; padding-bottom: 24px; }

/* Layout users — doar tabel pe pagină */
.settings-page .st-users-layout {
    display: flex;
    flex-direction: column;
    gap: 20px;
}
.settings-page .st-card__head .st-btn--primary { flex-shrink: 0; }

/* Modal utilizator */
.settings-page .st-modal {
    position: fixed; inset: 0; z-index: 10050;
    display: flex; align-items: center; justify-content: center;
    padding: 20px;
}
.settings-page .st-modal.hidden { display: none !important; }
.settings-page .st-modal__backdrop {
    position: absolute; inset: 0;
    background: rgba(15, 23, 42, 0.55);
    backdrop-filter: blur(4px);
}
.settings-page .st-modal__dialog {
    position: relative; z-index: 1;
    width: min(960px, 100%);
    max-height: min(92vh, 900px);
    display: flex; flex-direction: column;
    background: var(--st-surface);
    border: 1px solid var(--st-border);
    border-radius: var(--st-radius);
    box-shadow: 0 24px 64px rgba(15, 23, 42, 0.22);
    overflow: hidden;
}
.settings-page .st-modal__header {
    display: flex; align-items: flex-start; justify-content: space-between; gap: 12px;
    padding: 20px 24px 16px;
    border-bottom: 1px solid var(--st-border-soft);
    background: linear-gradient(180deg, #f8fafc, #fff);
}
.settings-page .st-modal__title { margin: 0; font-size: 1.2rem; font-weight: 800; }
.settings-page .st-modal__sub { margin: 8px 0 0; }
.settings-page .st-modal__close {
    width: 40px; height: 40px; border-radius: 10px;
    border: 1px solid var(--st-border); background: #fff;
    cursor: pointer; display: flex; align-items: center; justify-content: center;
    color: var(--st-muted); flex-shrink: 0;
}
.settings-page .st-modal__close:hover { border-color: #cbd5e1; color: var(--st-text); }
.settings-page .st-modal__body {
    display: flex; flex-direction: column;
    overflow: hidden; flex: 1; min-height: 0;
}
.settings-page .st-form--identity-modal {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px 16px;
    padding: 20px 24px 0;
}
@media (max-width: 600px) {
    .settings-page .st-form--identity-modal { grid-template-columns: 1fr; }
}
.settings-page .st-form--identity-modal .st-field { margin-bottom: 0; }
.settings-page .st-modal__section {
    display: flex; flex-direction: column;
    flex: 1; min-height: 0;
    margin: 16px 24px 0;
    border: 1px solid var(--st-border-soft);
    border-radius: 12px;
    overflow: hidden;
    background: #fff;
}
.settings-page .st-modal__section-head {
    display: flex; align-items: center; justify-content: space-between; gap: 12px;
    padding: 14px 16px;
    border-bottom: 1px solid var(--st-border-soft);
    background: var(--st-bg);
}
.settings-page .st-modal__section-title { margin: 0; font-size: 0.95rem; font-weight: 700; }
.settings-page .st-modal__section-desc { margin: 4px 0 0; font-size: 0.78rem; color: var(--st-muted); }
.settings-page .st-modal__footer {
    display: flex; justify-content: flex-end; gap: 10px;
    padding: 16px 24px 20px;
    border-top: 1px solid var(--st-border-soft);
    margin-top: 16px;
    background: #fff;
}
.settings-page .st-btn--sm { height: 36px; padding: 0 14px; font-size: 0.8rem; }

/* Permisiuni — master / detail în modal */
.settings-page .st-perms-deleg {
    display: grid;
    grid-template-columns: minmax(180px, 220px) minmax(0, 1fr);
    gap: 0;
    min-height: 280px;
    max-height: 340px;
}
@media (max-width: 640px) {
    .settings-page .st-perms-deleg { grid-template-columns: 1fr; max-height: none; }
}
.settings-page .st-perms-deleg__nav {
    display: flex;
    flex-direction: column;
    gap: 4px;
    padding: 10px;
    background: var(--st-bg);
    border-right: 1px solid var(--st-border-soft);
    overflow-y: auto;
}
@media (max-width: 640px) {
    .settings-page .st-perms-deleg__nav {
        flex-direction: row; flex-wrap: wrap;
        border-right: none; border-bottom: 1px solid var(--st-border-soft);
        max-height: 120px;
    }
}
.settings-page .st-perm-nav-btn {
    display: flex; align-items: center; justify-content: space-between; gap: 8px;
    width: 100%; padding: 10px 12px; border: 1.5px solid transparent; border-radius: 10px;
    background: transparent; cursor: pointer; text-align: left; font: inherit;
    font-size: 0.85rem; font-weight: 600; color: #334155;
    transition: background .12s, border-color .12s;
}
.settings-page .st-perm-nav-btn:hover { background: #fff; border-color: var(--st-border); }
.settings-page .st-perm-nav-btn.is-active {
    background: #fff; border-color: #6ee7b7; color: #047857;
    box-shadow: 0 2px 8px rgba(5, 150, 105, 0.12);
}
.settings-page .st-perm-nav-btn.has-selection .st-perm-nav-count {
    background: var(--st-primary-soft); color: #047857; border-color: #a7f3d0;
}
.settings-page .st-perm-nav-count {
    font-size: 0.68rem; font-weight: 700; padding: 2px 7px; border-radius: 999px;
    background: #f1f5f9; color: var(--st-muted); border: 1px solid var(--st-border);
    flex-shrink: 0;
}
.settings-page .st-perms-deleg__panel {
    padding: 14px 16px 16px;
    overflow-y: auto;
    background: #fff;
}
.settings-page .st-perms-deleg__empty {
    margin: 0; padding: 32px 16px; text-align: center;
    font-size: 0.9rem; color: var(--st-muted);
}
.settings-page .st-perm-pane { display: none; }
.settings-page .st-perm-pane.is-active { display: block; }
.settings-page .st-perm-pane__head {
    display: flex; align-items: flex-start; justify-content: space-between; gap: 12px;
    margin-bottom: 12px; padding-bottom: 10px; border-bottom: 1px solid var(--st-border-soft);
}
.settings-page .st-perm-pane__desc { margin: 0; font-size: 0.8rem; color: var(--st-muted); line-height: 1.4; }
.settings-page .st-perm-pane__toolbar {
    display: flex; gap: 12px; flex-shrink: 0;
}
.settings-page .st-perm-features {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 8px;
}
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 8px;
}

/* Form */
.settings-page .st-form { padding: 20px 24px 0; }
.settings-page .st-form__row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
@media (max-width: 520px) { .settings-page .st-form__row { grid-template-columns: 1fr; } }
.settings-page .st-field { display: flex; flex-direction: column; gap: 6px; margin-bottom: 14px; }
.settings-page .st-field--check { flex-direction: row; align-items: center; gap: 12px; }
.settings-page .st-field--submit { margin-bottom: 0; align-self: end; }
.settings-page .st-label { font-size: 0.78rem; font-weight: 600; color: #475569; text-transform: uppercase; letter-spacing: .03em; }
.settings-page .st-label-hint { font-weight: 500; text-transform: none; letter-spacing: 0; color: var(--st-muted); font-size: 0.75rem; }
.settings-page .st-hint { font-size: 0.72rem; color: var(--st-muted); margin-top: 2px; }
.settings-page .st-input {
    height: 42px; padding: 0 14px; border: 1.5px solid var(--st-border); border-radius: var(--st-radius-sm);
    font-size: 0.9rem; color: var(--st-text); background: #fff;
    transition: border-color .15s, box-shadow .15s;
}
.settings-page .st-input:focus {
    outline: none; border-color: var(--st-primary);
    box-shadow: 0 0 0 3px var(--st-primary-ring);
}
.settings-page .st-select { cursor: pointer; appearance: auto; }
.settings-page .st-form__actions { padding: 8px 24px 20px; display: flex; flex-direction: column; gap: 8px; }
.settings-page .st-form__actions--inline { padding: 0 24px 24px; flex-direction: row; }
.settings-page .st-form--budget {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 12px 16px;
    padding: 20px 24px 0;
}
.settings-page .st-budget-form {
    padding: 0 0 20px;
}
.settings-page .st-budget-status {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 16px 24px;
    margin: 0 24px 20px;
    padding: 18px 20px;
    border-radius: 14px;
    border: 1px solid #a7f3d0;
    background: linear-gradient(135deg, #ecfdf5 0%, #f0fdfa 55%, #fff 100%);
}
.settings-page .st-budget-status.is-warning {
    border-color: #fde68a;
    background: linear-gradient(135deg, #fffbeb 0%, #fff 100%);
}
.settings-page .st-budget-status.is-danger {
    border-color: #fecaca;
    background: linear-gradient(135deg, #fef2f2 0%, #fff 100%);
}
.settings-page .st-budget-status__eyebrow {
    display: block;
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--st-muted);
    margin-bottom: 6px;
}
.settings-page .st-budget-status__row {
    display: flex;
    align-items: baseline;
    gap: 10px;
    flex-wrap: wrap;
}
.settings-page .st-budget-status__value {
    font-size: 2rem;
    font-weight: 800;
    letter-spacing: -0.03em;
    color: #047857;
    line-height: 1;
}
.settings-page .st-budget-status__value--input {
    width: min(9rem, 42vw);
    padding: 2px 6px;
    margin: 0;
    border: 2px solid transparent;
    border-radius: 10px;
    background: rgba(255, 255, 255, 0.55);
    font: inherit;
    color: inherit;
    -moz-appearance: textfield;
}
.settings-page .st-budget-status__value--input::-webkit-outer-spin-button,
.settings-page .st-budget-status__value--input::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
}
.settings-page .st-budget-status__value--input:hover {
    border-color: rgba(5, 150, 105, 0.25);
    background: rgba(255, 255, 255, 0.85);
}
.settings-page .st-budget-status__value--input:focus {
    outline: none;
    border-color: #10b981;
    background: #fff;
    box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.2);
}
.settings-page .st-budget-status__value--input.is-manual {
    border-color: #6ee7b7;
    background: #fff;
}
.settings-page .st-budget-status__auto-btn {
    border: 1px solid #a7f3d0;
    background: #fff;
    color: #047857;
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    padding: 4px 10px;
    border-radius: 999px;
    cursor: pointer;
    flex-shrink: 0;
}
.settings-page .st-budget-status__auto-btn:hover {
    border-color: #10b981;
    background: #ecfdf5;
}
.settings-page .st-budget-status__auto-btn.is-hidden {
    display: none;
}
.settings-page .st-budget-status.is-warning .st-budget-status__value { color: #b45309; }
.settings-page .st-budget-status.is-danger .st-budget-status__value { color: #b91c1c; }
.settings-page .st-budget-status__meta {
    font-size: 0.9rem;
    font-weight: 600;
    color: #334155;
}
.settings-page .st-budget-status__detail {
    margin: 8px 0 0;
    font-size: 0.82rem;
    color: var(--st-muted);
    line-height: 1.45;
}
.settings-page .st-budget-status__meter {
    display: flex;
    align-items: center;
    gap: 12px;
    min-width: min(280px, 100%);
    flex: 1;
}
.settings-page .st-budget-status__bar {
    flex: 1;
    height: 10px;
    border-radius: 999px;
    background: #e2e8f0;
    overflow: hidden;
}
.settings-page .st-budget-status__bar-fill {
    height: 100%;
    border-radius: 999px;
    background: linear-gradient(90deg, #34d399, #059669);
    transition: width 0.35s ease;
}
.settings-page .st-budget-status.is-warning .st-budget-status__bar-fill {
    background: linear-gradient(90deg, #fbbf24, #f59e0b);
}
.settings-page .st-budget-status.is-danger .st-budget-status__bar-fill {
    background: linear-gradient(90deg, #f87171, #dc2626);
}
.settings-page .st-budget-status__pct {
    font-size: 0.85rem;
    font-weight: 800;
    color: #047857;
    min-width: 3rem;
    text-align: right;
}
.settings-page .st-budget-status.is-warning .st-budget-status__pct { color: #b45309; }
.settings-page .st-budget-status.is-danger .st-budget-status__pct { color: #b91c1c; }
.settings-page .st-budget-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 14px 16px;
    padding: 0 24px;
}
@media (max-width: 900px) {
    .settings-page .st-budget-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 560px) {
    .settings-page .st-budget-grid { grid-template-columns: 1fr; }
}
.settings-page .st-budget-grid .st-field { margin-bottom: 0; }
.settings-page .st-field--span2 { grid-column: span 2; }
@media (max-width: 560px) {
    .settings-page .st-field--span2 { grid-column: span 1; }
}
.settings-page .st-field--switch-row {
    flex-direction: row;
    align-items: center;
    gap: 12px;
    align-self: end;
    padding-bottom: 4px;
}
.settings-page .st-label--inline {
    text-transform: none;
    letter-spacing: 0;
    font-size: 0.88rem;
    font-weight: 600;
    color: #334155;
}
.settings-page .st-field--optional .st-input:not(:placeholder-shown),
.settings-page .st-field--optional .st-input:focus {
    border-color: var(--st-primary);
}
.settings-page .st-budget-form__footer {
    display: flex;
    justify-content: flex-end;
    padding: 20px 24px 0;
    border-top: 1px solid var(--st-border-soft);
    margin-top: 20px;
}
@media (min-width: 1200px) {
    .settings-page .st-form--budget {
        grid-template-columns: 2fr 1fr 1fr 1fr 1.2fr 1fr;
        align-items: end;
    }
}

/* Buttons */
.settings-page .st-btn {
    display: inline-flex; align-items: center; justify-content: center; gap: 8px;
    height: 42px; padding: 0 18px; border-radius: var(--st-radius-sm);
    font-size: 0.875rem; font-weight: 600; cursor: pointer; border: none;
    transition: transform .12s, box-shadow .15s, background .15s;
}
.settings-page .st-btn:active { transform: scale(0.98); }
.settings-page .st-btn--primary {
    background: linear-gradient(180deg, #10b981 0%, #059669 55%, #047857 100%);
    color: #fff; box-shadow: 0 4px 14px rgba(5, 150, 105, 0.35);
}
.settings-page .st-btn--primary:hover { box-shadow: 0 6px 20px rgba(5, 150, 105, 0.45); }
.settings-page .st-btn--ghost {
    background: #fff; border: 1.5px solid var(--st-border); color: #334155;
}
.settings-page .st-btn--ghost:hover { border-color: #cbd5e1; background: var(--st-bg); }
.settings-page .st-btn--block { width: 100%; }
.settings-page .st-link-btn {
    border: none; background: none; color: var(--st-primary); font-size: 0.78rem;
    font-weight: 600; cursor: pointer; padding: 0;
}
.settings-page .st-link-btn:hover { text-decoration: underline; }

/* Permissions — item checkbox */
.settings-page .st-perm-item {
    display: flex; align-items: flex-start; gap: 12px; padding: 10px 12px;
    border: 1.5px solid var(--st-border-soft); border-radius: 10px;
    cursor: pointer; transition: border-color .15s, background .15s;
    background: #fff; position: relative;
}
.settings-page .st-perm-item:hover { border-color: #cbd5e1; }
.settings-page .st-perm-item.is-checked {
    border-color: #6ee7b7; background: var(--st-primary-soft);
}
.settings-page .st-perm-item input.settings-perm-cb {
    position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px;
    overflow: hidden; clip: rect(0, 0, 0, 0); white-space: nowrap; border: 0;
}
.settings-page .st-perm-check {
    width: 18px; height: 18px; border-radius: 5px; border: 2px solid #cbd5e1;
    flex-shrink: 0; margin-top: 1px; display: flex; align-items: center; justify-content: center;
    transition: all .15s;
}
.settings-page .st-perm-item.is-checked .st-perm-check {
    background: var(--st-primary); border-color: var(--st-primary);
}
.settings-page .st-perm-item.is-checked .st-perm-check::after {
    content: ''; width: 4px; height: 8px; border: solid #fff;
    border-width: 0 2px 2px 0; transform: rotate(45deg); margin-top: -2px;
}
.settings-page .st-perm-label { font-size: 0.82rem; font-weight: 600; display: block; }
.settings-page .st-perm-desc { font-size: 0.7rem; color: var(--st-muted); margin-top: 2px; line-height: 1.35; }

/* Table */
.settings-page .st-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 0.85rem; }
.settings-page .st-table thead th {
    text-align: left; padding: 12px 14px; font-size: 10px; font-weight: 800;
    text-transform: uppercase; letter-spacing: .08em; color: #fff;
    border-bottom: 1px solid rgba(255, 255, 255, 0.12);
    background: transparent;
}
.settings-page .st-table thead {
    background: linear-gradient(90deg, var(--b26-emerald-deep, #047857), var(--b26-navy, #0f172a));
}
.settings-page .st-table thead th:first-child { border-radius: 10px 0 0 0; }
.settings-page .st-table thead th:last-child { border-radius: 0 10px 0 0; }
.settings-page .st-table tbody tr { transition: background .12s; }
.settings-page .st-table tbody tr:hover { background: #f8fafc; }
.settings-page .st-table td { padding: 14px 12px; border-bottom: 1px solid var(--st-border-soft); vertical-align: middle; }
.settings-page .st-table-empty { text-align: center; padding: 48px 16px !important; color: var(--st-muted); }
.settings-page .st-tc { text-align: center; }
.settings-page .st-tr { text-align: right; }
.settings-page .st-user-cell { display: flex; align-items: center; gap: 12px; }
.settings-page .st-avatar {
    width: 40px; height: 40px; border-radius: 12px; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.8rem; font-weight: 800; color: #fff;
    background: linear-gradient(135deg, #34d399, #059669);
}
.settings-page .st-user-name { font-weight: 600; font-size: 0.9rem; }
.settings-page .st-user-login { font-size: 0.75rem; color: var(--st-muted); margin-top: 1px; }
.settings-page .st-role-pill {
    display: inline-block; padding: 4px 10px; border-radius: 999px;
    font-size: 0.72rem; font-weight: 700; text-transform: capitalize;
    background: #f1f5f9; color: #475569; border: 1px solid var(--st-border);
}
.settings-page .st-role-pill--super { background: #fef3c7; color: #b45309; border-color: #fde68a; }
.settings-page .st-role-pill--manager { background: #ede9fe; color: #6d28d9; border-color: #ddd6fe; }
.settings-page .st-tags { display: flex; flex-wrap: wrap; gap: 4px; max-width: none; }
.settings-page .st-tag {
    font-size: 0.65rem; font-weight: 600; padding: 3px 8px; border-radius: 6px;
    background: var(--st-primary-soft); color: #047857; border: 1px solid #a7f3d0;
}
.settings-page .st-tag--more { background: #f1f5f9; color: var(--st-muted); border-color: var(--st-border); }
.settings-page .st-status {
    display: inline-flex; align-items: center; gap: 6px; font-size: 0.75rem; font-weight: 600;
}
.settings-page .st-status::before {
    content: ''; width: 7px; height: 7px; border-radius: 50%; background: #94a3b8;
}
.settings-page .st-status.is-on::before { background: #10b981; box-shadow: 0 0 0 3px rgba(16,185,129,.25); }
.settings-page .st-status.is-on { color: #047857; }
.settings-page .st-row-actions { display: flex; gap: 6px; justify-content: flex-end; }
.settings-page .st-icon-btn {
    width: 34px; height: 34px; border-radius: 9px; border: 1px solid var(--st-border);
    background: #fff; cursor: pointer; display: inline-flex; align-items: center; justify-content: center;
    color: #64748b; transition: all .12s;
}
.settings-page .st-icon-btn:hover { border-color: var(--st-primary); color: var(--st-primary); background: var(--st-primary-soft); }
.settings-page .st-icon-btn--danger:hover { border-color: #fca5a5; color: #dc2626; background: #fef2f2; }

/* Token cards */
.settings-page .st-token-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
    gap: 16px;
}
@media (min-width: 1600px) {
    .settings-page .st-token-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}
.settings-page .st-token-card {
    border: 1px solid var(--st-border); border-radius: var(--st-radius);
    background: var(--st-surface); padding: 18px 20px;
    transition: box-shadow .2s, transform .2s;
    position: relative; overflow: hidden;
}
.settings-page .st-token-card::before {
    content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px;
    background: linear-gradient(90deg, var(--st-token-accent, #059669), transparent);
}
.settings-page .st-token-card:hover { box-shadow: var(--st-shadow-hover); transform: translateY(-2px); }
.settings-page .st-token-card.is-warning { border-color: #fde68a; }
.settings-page .st-token-card.is-danger { border-color: #fecaca; }
.settings-page .st-token-head { display: flex; align-items: center; gap: 12px; margin-bottom: 14px; }
.settings-page .st-token-icon {
    width: 44px; height: 44px; border-radius: 12px; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.7rem; font-weight: 800; color: #fff; letter-spacing: -.02em;
}
.settings-page .st-token-name { font-size: 0.95rem; font-weight: 700; margin: 0; }
.settings-page .st-token-key { font-size: 0.68rem; color: var(--st-muted); text-transform: uppercase; letter-spacing: .04em; }
.settings-page .st-token-stats { display: flex; flex-direction: column; align-items: flex-start; gap: 4px; margin-bottom: 10px; }
.settings-page .st-token-used { font-size: 1.75rem; font-weight: 800; letter-spacing: -.03em; line-height: 1; color: #047857; }
.settings-page .st-token-card.is-warning .st-token-used { color: #b45309; }
.settings-page .st-token-card.is-danger .st-token-used { color: #b91c1c; }
.settings-page .st-token-quota { font-size: 0.78rem; color: var(--st-muted); font-weight: 500; line-height: 1.3; }
.settings-page .st-token-bar {
    height: 10px; border-radius: 999px; background: #e2e8f0; overflow: hidden; margin-bottom: 12px;
}
.settings-page .st-token-bar-fill {
    height: 100%; border-radius: 999px;
    background: linear-gradient(90deg, #34d399, #059669);
    transition: width .4s cubic-bezier(.4,0,.2,1);
}
.settings-page .st-token-bar-fill.is-warning { background: linear-gradient(90deg, #fbbf24, #f59e0b); }
.settings-page .st-token-bar-fill.is-danger { background: linear-gradient(90deg, #f87171, #dc2626); }
.settings-page .st-token-meta { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
.settings-page .st-token-meta dt { font-size: 0.65rem; text-transform: uppercase; letter-spacing: .04em; color: var(--st-muted); font-weight: 600; }
.settings-page .st-token-meta dd { margin: 2px 0 0; font-size: 0.8rem; font-weight: 600; }

/* Env keys */
.settings-page .st-env-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 14px;
    padding: 20px 24px 8px;
}
@media (min-width: 1400px) {
    .settings-page .st-env-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}
.settings-page .st-env-card {
    border: 1.5px solid var(--st-border-soft); border-radius: 14px; padding: 16px;
    background: linear-gradient(180deg, #fafbfc, #fff);
    transition: border-color .15s;
}
.settings-page .st-env-card:focus-within { border-color: var(--st-primary); box-shadow: 0 0 0 3px var(--st-primary-ring); }
.settings-page .st-env-card__head { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; }
.settings-page .st-env-icon {
    width: 36px; height: 36px; border-radius: 10px; background: #f1f5f9;
    display: flex; align-items: center; justify-content: center; color: var(--st-muted);
}
.settings-page .st-env-title { font-size: 0.9rem; font-weight: 700; margin: 0; }
.settings-page .st-env-key { font-size: 0.68rem; font-family: ui-monospace, monospace; color: var(--st-muted); }
.settings-page .st-env-input {
    width: 100%; height: 40px; padding: 0 12px; border: 1.5px solid var(--st-border);
    border-radius: 9px; font-family: ui-monospace, monospace; font-size: 0.78rem;
    background: #fff;
}
.settings-page .st-env-input:focus { outline: none; border-color: var(--st-primary); }
.settings-page .st-env-hint { font-size: 0.72rem; color: var(--st-muted); margin-top: 8px; line-height: 1.4; }
.settings-page .st-env-hint--model { margin-top: 6px; }
.settings-page .st-env-model { margin-top: 12px; padding-top: 12px; border-top: 1px dashed var(--st-border); }
.settings-page .st-env-model__label {
    display: block; font-size: 0.72rem; font-weight: 700; color: var(--st-text);
    margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.04em;
}
.settings-page .st-env-model__select {
    width: 100%; margin-bottom: 8px; padding: 10px 12px; border-radius: 10px;
    border: 1px solid var(--st-border); background: #fff; font-size: 0.85rem; color: var(--st-text);
}
.settings-page .st-env-model__select:focus { outline: none; border-color: var(--st-primary); }
.settings-page .st-env-model__custom.is-hidden { display: none; }
.settings-page .st-code {
    font-family: ui-monospace, monospace; font-size: 0.8em;
    background: #f1f5f9; padding: 2px 6px; border-radius: 4px;
}

/* Alerts */
.settings-page .st-alerts { display: flex; flex-direction: column; gap: 8px; margin-bottom: 16px; }
.settings-page .st-alert {
    display: flex; align-items: flex-start; gap: 12px; padding: 14px 16px;
    border-radius: 12px; font-size: 0.85rem; line-height: 1.45;
}
.settings-page .st-alert::before { font-size: 1.1rem; flex-shrink: 0; }
.settings-page .st-alert--warning {
    background: #fffbeb; border: 1px solid #fde68a; color: #92400e;
}
.settings-page .st-alert--warning::before { content: '⚠️'; }
.settings-page .st-alert--danger {
    background: #fef2f2; border: 1px solid #fecaca; color: #991b1b;
}
.settings-page .st-alert--danger::before { content: '🛑'; }

/* Badge / empty / toast */
.settings-page .st-badge {
    font-size: 0.68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em;
    padding: 5px 10px; border-radius: 999px;
}
.settings-page .st-badge--soft { background: var(--st-primary-soft); color: #047857; border: 1px solid #a7f3d0; }
.settings-page .st-empty {
    text-align: center; padding: 48px 24px; color: var(--st-muted);
    border: 1px dashed var(--st-border); border-radius: var(--st-radius); background: var(--st-bg);
}
.settings-page .st-empty__icon { font-size: 2rem; margin-bottom: 12px; }
.settings-page .st-toast {
    position: fixed; right: 24px; top: 88px; z-index: 9999; max-width: 360px;
    padding: 14px 18px; border-radius: 12px; font-size: 0.875rem; font-weight: 500;
    box-shadow: 0 12px 40px rgba(15,23,42,.15); animation: st-toast-in .25s ease;
}
.settings-page .st-toast.is-ok { background: #ecfdf5; border: 1px solid #6ee7b7; color: #047857; }
.settings-page .st-toast.is-err { background: #fef2f2; border: 1px solid #fca5a5; color: #b91c1c; }
@keyframes st-toast-in {
    from { opacity: 0; transform: translateY(-8px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Toggle switch */
.settings-page .st-switch { position: relative; display: inline-block; width: 44px; height: 24px; flex-shrink: 0; }
.settings-page .st-switch input { opacity: 0; width: 0; height: 0; }
.settings-page .st-switch__track {
    position: absolute; inset: 0; border-radius: 999px; background: #cbd5e1; cursor: pointer;
    transition: background .2s;
}
.settings-page .st-switch__track::after {
    content: ''; position: absolute; width: 18px; height: 18px; left: 3px; top: 3px;
    background: #fff; border-radius: 50%; transition: transform .2s; box-shadow: 0 1px 3px rgba(0,0,0,.15);
}
.settings-page .st-switch input:checked + .st-switch__track { background: var(--st-primary); }
.settings-page .st-switch input:checked + .st-switch__track::after { transform: translateX(20px); }

/* Provider accents */
.settings-page .st-token-card[data-provider="rapidapi_tecdoc"] { --st-token-accent: #0ea5e9; }
.settings-page .st-token-card[data-provider="scrape_do"] { --st-token-accent: #f97316; }
.settings-page .st-token-card[data-provider="openai"] { --st-token-accent: #10b981; }
.settings-page .st-token-card[data-provider="cursor"] { --st-token-accent: #7c3aed; }
.settings-page .st-token-card[data-provider="groq"] { --st-token-accent: #8b5cf6; }
.settings-page .st-token-card[data-provider="gemini"] { --st-token-accent: #3b82f6; }
.settings-page .st-token-card[data-provider="grok"] { --st-token-accent: #1e293b; }
</style>
