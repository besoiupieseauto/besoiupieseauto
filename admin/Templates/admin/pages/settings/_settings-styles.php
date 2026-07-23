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
.settings-page .st-tab--link {
    text-decoration: none;
    color: var(--st-muted);
    border: 1px dashed var(--st-border);
}
.settings-page .st-tab--link:hover {
    color: var(--st-primary);
    border-color: var(--st-primary);
    background: rgba(26, 188, 156, 0.08);
}

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

/* Layout users — tabel 100% lățime */
.settings-page .st-users-layout {
    display: flex;
    flex-direction: column;
    gap: 20px;
    width: 100%;
}
.settings-page .st-card--users-full { width: 100%; }
.settings-page .st-table--users { width: 100%; }
.settings-page .st-table tbody tr.is-selected { background: #ecfdf5 !important; }
.settings-page .st-table tbody tr.is-selected td { border-bottom-color: #a7f3d0; }
.settings-page .st-card__head .st-btn--primary { flex-shrink: 0; }

/* Popup editare utilizator */
.settings-page .st-user-modal {
    position: fixed; inset: 0; z-index: 10050;
    display: flex; align-items: center; justify-content: center;
    padding: 16px;
}
.settings-page .st-user-modal.hidden { display: none !important; }
.settings-page .st-user-modal__backdrop {
    position: absolute; inset: 0;
    background: rgba(15, 23, 42, 0.55);
    backdrop-filter: blur(4px);
}
.settings-page .st-user-modal__dialog {
    position: relative; z-index: 1;
    width: min(1024px, 100%);
    max-height: min(92vh, 880px);
    display: flex; flex-direction: column;
    background: var(--st-surface);
    border: 1px solid var(--st-border);
    border-radius: var(--st-radius);
    box-shadow: 0 24px 64px rgba(15, 23, 42, 0.25);
    overflow: hidden;
}
.settings-page .st-user-modal__header {
    display: flex; align-items: flex-start; justify-content: space-between; gap: 12px;
    padding: 18px 24px 12px;
    border-bottom: 1px solid var(--st-border-soft);
    background: linear-gradient(180deg, #f8fafc, #fff);
    flex-shrink: 0;
}
.settings-page .st-user-modal__title { margin: 0; font-size: 1.25rem; font-weight: 800; letter-spacing: -0.02em; }
.settings-page .st-user-modal__meta { margin: 8px 0 0; }
.settings-page .st-user-modal__close {
    width: 40px; height: 40px; border-radius: 10px;
    border: 1px solid var(--st-border); background: #fff;
    cursor: pointer; display: flex; align-items: center; justify-content: center;
    color: var(--st-muted); flex-shrink: 0;
}
.settings-page .st-user-modal__close:hover { border-color: #cbd5e1; color: var(--st-text); }

/* Tab-uri popup — bară completă, stil normal */
.settings-page .st-user-modal__tabs {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0;
    padding: 0 24px;
    border-bottom: 1px solid var(--st-border);
    background: #fff;
    flex-shrink: 0;
}
.settings-page .st-user-modal__tab {
    padding: 14px 12px;
    border: none; border-bottom: 3px solid transparent;
    border-radius: 0;
    background: transparent;
    color: var(--st-muted);
    font-size: 0.875rem; font-weight: 700;
    cursor: pointer;
    transition: color .15s, border-color .15s, background .15s;
    margin-bottom: -1px;
}
.settings-page .st-user-modal__tab:hover {
    color: var(--st-text);
    background: #f8fafc;
}
.settings-page .st-user-modal__tab.is-active {
    color: var(--st-primary);
    border-bottom-color: var(--st-primary);
    background: #fff;
}
.settings-page .st-user-modal__form {
    display: flex; flex-direction: column;
    flex: 1; min-height: 0;
}
.settings-page .st-user-modal__scroll {
    flex: 1; min-height: 0;
    overflow-y: auto;
    overflow-x: hidden;
    padding: 0;
}
.settings-page .st-user-modal__footer {
    display: flex; justify-content: flex-end; gap: 10px;
    padding: 14px 24px 18px;
    border-top: 1px solid var(--st-border-soft);
    background: #fff;
    flex-shrink: 0;
}
.settings-page .st-user-editor__pane {
    display: none;
    padding: 20px 24px 24px;
}
.settings-page .st-user-editor__pane.is-active { display: block; }
.settings-page .st-user-editor__pane[hidden] { display: none !important; }
.settings-page .st-user-editor__pane-lead {
    margin: 0 0 16px; font-size: 0.85rem; color: var(--st-muted); line-height: 1.5;
}
.settings-page .st-form--identity {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px 16px;
}
@media (max-width: 640px) {
    .settings-page .st-form--identity { grid-template-columns: 1fr; }
    .settings-page .st-user-modal__tabs { grid-template-columns: 1fr; }
}
.settings-page .st-form--identity .st-field { margin-bottom: 0; }
.settings-page .st-user-editor__section-head {
    display: flex; align-items: flex-start; justify-content: space-between; gap: 12px;
    margin-bottom: 14px; padding-bottom: 12px;
    border-bottom: 1px solid var(--st-border-soft);
}
.settings-page .st-user-editor__section-title { margin: 0; font-size: 1rem; font-weight: 700; }
.settings-page .st-user-editor__section-desc { margin: 4px 0 0; font-size: 0.8rem; color: var(--st-muted); line-height: 1.45; }

/* Permisiuni în modal */
.settings-page .st-perms-deleg--modal {
    min-height: 320px;
    max-height: 420px;
    border: 1px solid var(--st-border-soft);
    border-radius: 12px;
    overflow: hidden;
    background: #fff;
}
.settings-page .st-perms-deleg--modal.st-perms-deleg--chat {
    display: flex; flex-direction: column; gap: 16px;
    padding: 16px; max-height: none; min-height: 200px;
    overflow-y: auto;
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
.settings-page .st-btn--sm { height: 36px; padding: 0 14px; font-size: 0.8rem; }
.settings-page .st-perms-deleg {
    display: grid;
    grid-template-columns: minmax(180px, 220px) minmax(0, 1fr);
    gap: 0;
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
.settings-page .st-role-pill--manager { background: #ede9fe; color: #0f766e; border-color: #ddd6fe; }
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
.settings-page .st-env-test-btn { margin-top: 10px; width: 100%; justify-content: center; }
.settings-page .st-form__actions--env { gap: 10px; flex-wrap: wrap; padding: 0 24px 20px; }
.settings-page .st-env-tests { margin: 0 24px 20px; padding: 16px 18px; border-radius: 14px; border: 1px solid var(--st-border-soft); background: #fafbfc; }
.settings-page .st-env-tests.hidden { display: none; }
.settings-page .st-env-tests__head { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 10px; }
.settings-page .st-env-tests__head .st-section-title { margin: 0; }
.settings-page .st-env-tests__summary { display: flex; gap: 12px; font-size: 0.82rem; font-weight: 700; }
.settings-page .st-env-tests__summary .is-ok { color: #059669; }
.settings-page .st-env-tests__summary .is-fail { color: #dc2626; }
.settings-page .st-env-tests__summary .is-skip { color: #64748b; }
.settings-page .st-env-test-row--is-ok td:first-child { color: #059669; font-weight: 700; }
.settings-page .st-env-test-row--is-fail td:first-child { color: #dc2626; font-weight: 700; }
.settings-page .st-env-test-row--is-skip td:first-child { color: #94a3b8; font-weight: 700; }
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
.settings-page .st-token-card[data-provider="stealth_browser"] { --st-token-accent: #1abc9c; }
.settings-page .st-token-card[data-provider="rapidapi_tecdoc"] { --st-token-accent: #0ea5e9; }
.settings-page .st-token-card[data-provider="scrape_do"] { --st-token-accent: #f97316; }
.settings-page .st-token-card[data-provider="openai"] { --st-token-accent: #10b981; }
.settings-page .st-token-card[data-provider="cursor"] { --st-token-accent: #0d9488; }
.settings-page .st-token-card[data-provider="groq"] { --st-token-accent: #2dd4bf; }
.settings-page .st-token-card[data-provider="gemini"] { --st-token-accent: #2dd4bf; }
.settings-page .st-token-card[data-provider="grok"] { --st-token-accent: #1e293b; }

.settings-page .st-section-title { font-size: 1rem; font-weight: 700; margin: 28px 0 12px; color: var(--st-text); }
.settings-page .st-lead--keys a { color: var(--st-primary); font-weight: 600; }
.settings-page .st-lead--keys .st-lead__sub { display: block; margin-top: 8px; font-size: 0.88rem; color: var(--st-muted); font-weight: 400; }

.settings-page .st-automation-panel { display: flex; flex-direction: column; gap: 16px; margin-bottom: 8px; }
.settings-page .st-auto-matrix-hint {
    padding: 12px 14px; border-radius: 10px; border: 1px solid #fcd34d; background: #fffbeb;
    font-size: 0.82rem; line-height: 1.45; color: #92400e;
}
.settings-page .st-auto-matrix-hint a { color: #0d9488; font-weight: 700; }
.settings-page .st-auto-matrix-lead { margin: -6px 0 8px; font-size: 0.78rem; color: var(--st-muted); }
.settings-page .st-auto-block-reason { display: block; margin-top: 4px; font-size: 0.68rem; color: #b45309; font-weight: 600; line-height: 1.3; max-width: 14rem; }
.settings-page .st-auto-providers {
    padding: 16px 18px; border-radius: 14px; border: 1px solid #99f6e4; background: linear-gradient(135deg, #f0fdfa 0%, #fff 70%);
}
.settings-page .st-auto-toggle--highlight { border-color: #5eead4; background: #f0fdfa; }
.settings-page .st-provider-toggle.is-on { border-color: #5eead4; }
.settings-page .st-provider-toggle.is-off { opacity: 0.82; border-color: #fecaca; background: #fffbfb; }
.settings-page .st-token-card.is-api-off { opacity: 0.72; border-style: dashed; }
.settings-page .st-auto-master {
    display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 16px;
    padding: 18px 20px; border-radius: 14px; border: 1px solid var(--st-border);
    background: linear-gradient(135deg, #ecfdf5 0%, #fff 60%);
}
.settings-page .st-auto-master.is-paused { background: linear-gradient(135deg, #fef2f2 0%, #fff 60%); border-color: #fecaca; }
.settings-page .st-auto-master__title { font-size: 1.05rem; font-weight: 800; margin: 0 0 4px; }
.settings-page .st-auto-master__sub { font-size: 0.82rem; color: var(--st-muted); margin: 0; max-width: 52ch; }
.settings-page .st-auto-toggles { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 10px; }
.settings-page .st-auto-toggle {
    display: flex; align-items: center; justify-content: space-between; gap: 12px;
    padding: 12px 14px; border-radius: 10px; border: 1px solid var(--st-border); background: #fff;
}
.settings-page .st-auto-toggle label { font-size: 0.82rem; font-weight: 600; line-height: 1.35; cursor: pointer; }
.settings-page .st-auto-toggle small { display: block; font-weight: 500; color: var(--st-muted); font-size: 0.72rem; margin-top: 2px; }

.settings-page .st-auto-projects { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 12px; }
.settings-page .st-auto-project {
    padding: 14px 16px; border-radius: 12px; border: 1px solid var(--st-border); background: #fff;
}
.settings-page .st-auto-project__head { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 8px; }
.settings-page .st-auto-project__name { font-weight: 700; font-size: 0.9rem; }
.settings-page .st-auto-project__note { font-size: 0.78rem; color: var(--st-muted); margin: 0 0 8px; line-height: 1.45; }
.settings-page .st-auto-checklist { margin: 0; padding-left: 18px; font-size: 0.75rem; color: var(--st-muted); line-height: 1.5; }

.settings-page .st-mode-badge {
    display: inline-flex; align-items: center; padding: 2px 8px; border-radius: 999px;
    font-size: 0.65rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em;
}
.settings-page .st-mode-badge--auto { background: #fef3c7; color: #92400e; }
.settings-page .st-mode-badge--manual { background: #ccfbf1; color: #0a3d31; }
.settings-page .st-mode-badge--unknown { background: #f1f5f9; color: #475569; }
.settings-page .st-status-badge { font-size: 0.65rem; font-weight: 700; padding: 2px 8px; border-radius: 999px; text-transform: uppercase; }
.settings-page .st-status-badge--active { background: #dcfce7; color: #166534; }
.settings-page .st-status-badge--blocked { background: #fee2e2; color: #991b1b; }
.settings-page .st-status-badge--external { background: #ccfbf1; color: #0f766e; }

.settings-page .st-auto-table-wrap { overflow-x: auto; border: 1px solid var(--st-border); border-radius: 12px; background: #fff; }
.settings-page .st-auto-table { width: 100%; border-collapse: collapse; font-size: 0.8rem; }
.settings-page .st-auto-table th, .settings-page .st-auto-table td { padding: 10px 12px; text-align: left; border-bottom: 1px solid var(--st-border); vertical-align: top; }
.settings-page .st-auto-table th { font-size: 0.68rem; text-transform: uppercase; letter-spacing: .04em; color: var(--st-muted); background: #f8fafc; }
.settings-page .st-auto-table tr:last-child td { border-bottom: none; }
.settings-page .st-auto-table code { font-size: 0.72rem; word-break: break-all; }
.settings-page .st-token-mode-line { font-size: 0.72rem; color: var(--st-muted); margin: 0 0 10px; }
.settings-page .st-token-mode-line strong { color: var(--st-text); }
.settings-page .st-token-empty {
    grid-column: 1 / -1;
    padding: 20px;
    border: 1px dashed #cbd5e1;
    border-radius: 12px;
    background: #f8fafc;
    font-size: 0.85rem;
    color: var(--st-muted);
}
.settings-page .st-token-empty code { display: block; margin: 10px 0; font-size: 0.75rem; }
.settings-page .st-env-consumption { margin-top: 10px; padding: 10px 12px; border-radius: 8px; background: #f8fafc; border: 1px solid var(--st-border); font-size: 0.78rem; line-height: 1.5; }
.settings-page .st-env-consumption dt { font-weight: 700; font-size: 0.68rem; text-transform: uppercase; color: var(--st-muted); margin-top: 6px; }
.settings-page .st-env-consumption dt:first-child { margin-top: 0; }
.settings-page .st-env-consumption dd { margin: 2px 0 0; }
.settings-page .st-cursor-billing {
    margin: 0 0 16px;
    padding: 14px 16px;
    border-radius: 12px;
    border: 1px solid #c4b5fd;
    background: linear-gradient(135deg, #faf5ff 0%, #f0fdfa 100%);
    font-size: 0.8rem;
    line-height: 1.5;
}
.settings-page .st-cursor-billing.hidden { display: none; }
.settings-page .st-cursor-billing--card {
    margin: 0 0 12px;
    padding: 10px 12px;
    font-size: 0.72rem;
}
.settings-page .st-cursor-billing--card p { margin: 0 0 6px; }
.settings-page .st-cursor-billing--card p:last-child { margin-bottom: 0; }
.settings-page .st-cursor-billing__grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}
@media (max-width: 640px) {
    .settings-page .st-cursor-billing__grid { grid-template-columns: 1fr; }
}
.settings-page .st-cursor-billing__col {
    padding: 10px 12px;
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.7);
}
.settings-page .st-cursor-billing__tag {
    display: inline-block;
    font-size: 0.62rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .04em;
    padding: 2px 6px;
    border-radius: 4px;
    background: #0d9488;
    color: #fff;
    margin-bottom: 6px;
}
.settings-page .st-cursor-billing__tag--ext { background: #475569; }
.settings-page .st-cursor-billing__title { margin: 0 0 6px; font-size: 0.85rem; }
.settings-page .st-cursor-billing__sub { color: var(--st-muted); margin: 4px 0 0; font-size: 0.75rem; }
.settings-page .st-cursor-billing__links a { color: #0f766e; font-weight: 600; }
.settings-page .st-cursor-billing__note {
    margin: 10px 0 0;
    padding-top: 10px;
    border-top: 1px solid #ddd6fe;
    color: var(--st-muted);
    font-size: 0.75rem;
}

/* Metro LLM */
.settings-page .st-metro {
    margin: 16px 0 24px;
    padding: 18px;
    border-radius: 14px;
    border: 1px solid #c4b5fd;
    background: linear-gradient(135deg, #f0fdfa 0%, #fff 55%);
}
.settings-page .st-metro--is-warning { border-color: #fcd34d; background: linear-gradient(135deg, #fffbeb 0%, #fff 55%); }
.settings-page .st-metro--is-critical { border-color: #fca5a5; background: linear-gradient(135deg, #fef2f2 0%, #fff 55%); }
.settings-page .st-metro__head { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 12px; margin-bottom: 14px; }
.settings-page .st-metro__title { margin: 0; font-size: 1.05rem; }
.settings-page .st-metro__sub { margin: 4px 0 0; color: var(--st-muted); font-size: 0.8rem; }
.settings-page .st-metro__eco { display: flex; align-items: center; gap: 8px; padding: 6px 12px; border-radius: 999px; font-size: 0.8rem; background: #ecfdf5; color: #065f46; }
.settings-page .st-metro__eco--is-warning { background: #fff7ed; color: #9a3412; }
.settings-page .st-metro__eco--is-critical { background: #fef2f2; color: #991b1b; }

/* Consum live */
.settings-page .st-usage-live { margin-top: 4px; }
.settings-page .st-usage-live__loading { padding: 24px; text-align: center; color: var(--st-muted); font-size: 0.9rem; }
.settings-page .st-usage-live__error {
    padding: 28px 20px; text-align: center; border-radius: 12px; border: 1px dashed #fecaca; background: #fef2f2;
}
.settings-page .st-usage-live__error-detail { color: #b91c1c; font-size: 0.85rem; margin: 8px 0 14px; }
.settings-page .st-usage-live__toolbar {
    display: flex; flex-wrap: wrap; gap: 14px; justify-content: space-between; align-items: flex-start;
    margin-bottom: 20px;
}
.settings-page .st-usage-status {
    display: flex; align-items: flex-start; gap: 12px; padding: 14px 16px; border-radius: 12px;
    border: 1px solid var(--st-border); background: #f0fdf4; flex: 1; min-width: 260px;
}
.settings-page .st-usage-status p { margin: 4px 0 0; font-size: 0.78rem; color: var(--st-muted); }
.settings-page .st-usage-status--paused { background: #fffbeb; border-color: #fde68a; }
.settings-page .st-usage-status--armed { background: #f0fdfa; border-color: #99f6e4; }
.settings-page .st-usage-status__pulse {
    width: 10px; height: 10px; border-radius: 50%; background: #22c55e; margin-top: 5px; flex-shrink: 0;
    box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.5);
    animation: st-usage-pulse 2s infinite;
}
.settings-page .st-usage-status--paused .st-usage-status__pulse { background: #f59e0b; box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.4); }
.settings-page .st-usage-status--armed .st-usage-status__pulse { background: #2dd4bf; }
@keyframes st-usage-pulse {
    0% { box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.45); }
    70% { box-shadow: 0 0 0 8px rgba(34, 197, 94, 0); }
    100% { box-shadow: 0 0 0 0 rgba(34, 197, 94, 0); }
}
.settings-page .st-usage-live__meta {
    display: flex; flex-wrap: wrap; align-items: center; gap: 10px; font-size: 0.78rem;
}
.settings-page .st-usage-live__clock { color: var(--st-muted); }
.settings-page .st-usage-live__autoref { display: flex; align-items: center; gap: 6px; cursor: pointer; }
.settings-page .st-usage-provider-grid {
    display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 14px; margin-bottom: 22px;
}
.settings-page .st-usage-provider {
    padding: 14px; border-radius: 12px; border: 1px solid var(--st-border); background: #fff;
    box-shadow: var(--st-shadow);
}
.settings-page .st-usage-provider__head { display: flex; gap: 10px; margin-bottom: 12px; }
.settings-page .st-usage-provider__icon {
    width: 36px; height: 36px; border-radius: 10px; color: #fff; font-size: 0.7rem; font-weight: 800;
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.settings-page .st-usage-provider__name { margin: 0; font-size: 0.9rem; font-weight: 700; }
.settings-page .st-usage-provider__hint { margin: 4px 0 0; font-size: 0.72rem; color: var(--st-muted); line-height: 1.35; }
.settings-page .st-usage-live-badge {
    font-size: 0.58rem; font-weight: 800; letter-spacing: .04em; padding: 2px 5px; border-radius: 4px;
    background: #e2e8f0; color: #64748b; vertical-align: middle;
}
.settings-page .st-usage-live-badge--on { background: #dcfce7; color: #166534; }
.settings-page .st-usage-provider__stats {
    display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; margin-bottom: 10px;
}
.settings-page .st-usage-provider__stats div { text-align: center; }
.settings-page .st-usage-provider__stats strong { display: block; font-size: 1.1rem; }
.settings-page .st-usage-provider__stats span { font-size: 0.65rem; color: var(--st-muted); text-transform: uppercase; }
.settings-page .st-usage-provider__bar {
    height: 6px; border-radius: 999px; background: #e2e8f0; overflow: hidden; margin-bottom: 8px;
}
.settings-page .st-usage-provider__bar-fill {
    height: 100%; border-radius: 999px; background: linear-gradient(90deg, #34d399, #059669);
    transition: width 0.4s ease;
}
.settings-page .st-usage-provider__bar-fill.is-warning { background: linear-gradient(90deg, #fbbf24, #f59e0b); }
.settings-page .st-usage-provider__bar-fill.is-danger { background: linear-gradient(90deg, #f87171, #dc2626); }
.settings-page .st-usage-provider__modes { margin: 0; font-size: 0.72rem; color: var(--st-muted); }
.settings-page .st-card--usage-chart { margin-bottom: 22px; }
.settings-page .st-usage-hourly {
    display: flex; align-items: flex-end; gap: 4px; height: 100px; padding: 8px 4px 0;
}
.settings-page .st-usage-hourly__bar {
    flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: flex-end;
    height: 100%; min-width: 0;
}
.settings-page .st-usage-hourly__fill {
    width: 100%; max-width: 18px; border-radius: 4px 4px 0 0;
    background: linear-gradient(180deg, #0ea5e9, #0369a1); min-height: 2px;
    transition: height 0.3s ease;
}
.settings-page .st-usage-hourly__label { font-size: 0.55rem; color: var(--st-muted); margin-top: 4px; }
.settings-page .st-lead--compact { margin: -8px 0 12px; font-size: 0.82rem; }
.settings-page .st-usage-timeline-wrap { max-height: 420px; overflow: auto; border-radius: 12px; border: 1px solid var(--st-border); }
.settings-page .st-usage-timeline-table thead { position: sticky; top: 0; background: #f8fafc; z-index: 1; }
.settings-page .st-usage-timeline__time { white-space: nowrap; font-size: 0.75rem; font-variant-numeric: tabular-nums; }
.settings-page .st-usage-kind {
    font-size: 0.62rem; font-weight: 800; padding: 2px 6px; border-radius: 4px; letter-spacing: .03em;
}
.settings-page .st-usage-kind--api { background: #e0f2fe; color: #0369a1; }
.settings-page .st-usage-kind--ai { background: #f3e8ff; color: #6b21a8; }
.settings-page .st-usage-provider-pill {
    font-size: 0.68rem; font-weight: 700; padding: 2px 8px; border-radius: 999px;
    background: color-mix(in srgb, var(--pill) 15%, white); color: var(--pill);
}
.settings-page .st-usage-timeline-row { transition: background 0.2s; }
.settings-page .st-usage-timeline-row:hover { background: #f8fafc; }
.settings-page .st-metro__eco-dot { width: 8px; height: 8px; border-radius: 50%; background: currentColor; }
.settings-page .st-metro__stats { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px; margin-bottom: 12px; }
.settings-page .st-metro__stat { padding: 10px; border-radius: 10px; background: rgba(255,255,255,.85); border: 1px solid #e9e5ff; }
.settings-page .st-metro__stat-label { display: block; font-size: 0.68rem; color: var(--st-muted); text-transform: uppercase; }
.settings-page .st-metro__status-grid { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 12px; }
.settings-page .st-metro__chip { font-size: 0.75rem; padding: 4px 10px; border-radius: 999px; background: #f1f5f9; color: #475569; }
.settings-page .st-metro__chip.is-ok { background: #ecfdf5; color: #047857; }
.settings-page .st-metro__chip.is-warn { background: #fff7ed; color: #c2410c; }
.settings-page .st-metro__form { display: flex; flex-wrap: wrap; gap: 10px 16px; align-items: flex-end; margin-bottom: 12px; }
.settings-page .st-metro__form label { font-size: 0.78rem; display: flex; flex-direction: column; gap: 4px; }
.settings-page .st-input--sm { padding: 6px 8px; font-size: 0.8rem; min-width: 140px; }
.settings-page .st-metro__tests { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 16px; }
.settings-page .st-metro__tests button {
  padding: 10px 16px;
  border-radius: 10px;
  font-size: 0.8rem;
  font-weight: 700;
  border: none;
  cursor: pointer;
  color: #fff;
  transition: transform 0.2s, box-shadow 0.2s;
}
.settings-page .st-metro__tests button:hover { transform: translateY(-1px); }
.settings-page .st-metro__tests button[data-metro-test="test_metro_ollama"] {
  background: linear-gradient(135deg, #0d9488, #0f766e);
  box-shadow: 0 4px 14px rgba(124, 58, 237, 0.3);
}
.settings-page .st-metro__tests button[data-metro-test="test_metro_cursor"] {
  background: linear-gradient(135deg, #14b8a6, #14b8a6);
  box-shadow: 0 4px 14px rgba(99, 102, 241, 0.3);
}
.settings-page .st-metro__tests button[data-metro-test="test_metro_cycle"] {
  background: linear-gradient(135deg, #f59e0b, #ea580c);
  box-shadow: 0 4px 14px rgba(234, 88, 12, 0.3);
}
@media (max-width: 720px) { .settings-page .st-metro__stats { grid-template-columns: repeat(2, 1fr); } }
.settings-page .st-perms-deleg--chat { display: flex; flex-direction: column; gap: 16px; padding-right: 4px; }
.settings-page .st-chat-perm-group { border: 1px solid rgba(15, 23, 42, 0.08); border-radius: 12px; padding: 12px 14px; background: rgba(248, 250, 252, 0.7); }
.settings-page .st-chat-perm-group .st-perm-features { margin-top: 10px; }

/* Module plug / unplug */
.settings-page .st-modules-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 16px;
    padding: 8px 24px 24px;
}
.settings-page .st-module-card {
    border: 1px solid var(--st-border);
    border-radius: 14px;
    padding: 16px;
    background: #fff;
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.settings-page .st-module-card__top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 12px;
}
.settings-page .st-module-card__title {
    margin: 0;
    font-size: 1rem;
    font-weight: 700;
}
.settings-page .st-module-card__meta {
    margin: 4px 0 0;
    font-size: 0.75rem;
    color: var(--st-muted);
}
.settings-page .st-module-card__desc {
    margin: 0;
    font-size: 0.82rem;
    color: #475569;
    line-height: 1.45;
    flex: 1;
}
.settings-page .st-module-card__actions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 4px;
}
.settings-page .st-badge--muted {
    background: #f1f5f9;
    color: #64748b;
    border: 1px solid #e2e8f0;
}
.settings-page .st-badge--warn {
    background: #fffbeb;
    color: #b45309;
    border: 1px solid #fde68a;
}
.settings-page .st-btn--danger {
    background: #fef2f2;
    color: #b91c1c;
    border: 1px solid #fecaca;
}
.settings-page .st-btn--danger:hover {
    background: #fee2e2;
}
.settings-page .st-modules-core-list { padding: 0 24px 24px; }
.settings-page .st-modules-core-ul {
    list-style: none;
    margin: 0;
    padding: 0;
    display: grid;
    gap: 10px;
}
.settings-page .st-modules-core-ul li {
    display: grid;
    grid-template-columns: minmax(140px, 200px) 100px 1fr;
    gap: 12px;
    align-items: baseline;
    padding: 10px 12px;
    border-radius: 10px;
    background: #f8fafc;
    border: 1px solid var(--st-border-soft);
    font-size: 0.85rem;
}
.settings-page .st-modules-core-id {
    font-family: ui-monospace, monospace;
    font-size: 0.75rem;
    color: var(--st-muted);
}
.settings-page .st-modules-core-note { color: #64748b; }
.settings-page .mt-4 { margin-top: 1rem; }
.settings-page .mb-4 { margin-bottom: 1rem; }
.settings-page .st-badge--ext {
    background: #f0fdfa;
    color: #0f766e;
    border: 1px solid #99f6e4;
    margin-left: 6px;
    vertical-align: middle;
}
.settings-page .st-module-install {
    display: grid;
    gap: 14px;
    padding: 8px 24px 24px;
    max-width: 560px;
}
.settings-page .st-module-install__actions {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: center;
}
@media (max-width: 720px) {
    .settings-page .st-modules-core-ul li {
        grid-template-columns: 1fr;
        gap: 4px;
    }
}

/* Navigație — registru BD */
.settings-page .st-nav-tree {
    display: flex;
    flex-direction: column;
    gap: 20px;
    padding: 8px 24px 24px;
}
.settings-page .st-nav-block {
    border: 1px solid var(--st-border);
    border-radius: 14px;
    background: #fff;
    overflow: hidden;
}
.settings-page .st-nav-block__head {
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    gap: 12px;
    padding: 16px 18px;
    border-bottom: 1px solid var(--st-border);
    background: rgba(248, 250, 252, 0.85);
}
.settings-page .st-nav-block__actions {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: end;
}

/* Ollama Control panel */
.settings-page .st-ollama-toolbar { display: flex; align-items: center; gap: 12px; margin-bottom: 16px; }
.settings-page .st-ollama-meta { color: var(--st-muted); font-size: 13px; }
.settings-page .st-ollama-summary { display: flex; flex-wrap: wrap; gap: 10px; margin-bottom: 20px; }
.settings-page .st-ollama-summary__item {
    padding: 8px 14px; border-radius: 999px; font-size: 13px; font-weight: 600;
    background: #f1f5f9; color: #475569; border: 1px solid var(--st-border);
}
.settings-page .st-ollama-summary__item.is-ok { background: #ecfdf5; color: #047857; border-color: #a7f3d0; }
.settings-page .st-ollama-summary__item.is-warn { background: #fffbeb; color: #b45309; border-color: #fde68a; }
.settings-page .st-ollama-modules { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 14px; margin-bottom: 24px; }
.settings-page .st-ollama-module-card {
    border: 1px solid var(--st-border); border-radius: 14px; padding: 14px 16px; background: #fff;
    box-shadow: var(--st-shadow);
}
.settings-page .st-ollama-module-card header { display: flex; align-items: center; gap: 8px; margin-bottom: 10px; font-size: 14px; }
.settings-page .st-ollama-dl { display: grid; gap: 6px; font-size: 12px; margin: 0; }
.settings-page .st-ollama-dl dt { color: var(--st-muted); display: inline; }
.settings-page .st-ollama-dl dd { display: inline; margin: 0 0 0 4px; }
.settings-page .st-ollama-dl > div { display: block; }
.settings-page .st-ollama-dot { width: 10px; height: 10px; border-radius: 50%; display: inline-block; }
.settings-page .st-ollama-dot--green { background: #10b981; }
.settings-page .st-ollama-dot--yellow { background: #f59e0b; }
.settings-page .st-ollama-dot--red { background: #ef4444; }
.settings-page .st-ollama-badge { font-size: 11px; font-weight: 700; text-transform: uppercase; padding: 2px 8px; border-radius: 999px; }
.settings-page .st-ollama-badge--green { background: #ecfdf5; color: #047857; }
.settings-page .st-ollama-badge--yellow { background: #fffbeb; color: #b45309; }
.settings-page .st-ollama-badge--red { background: #fef2f2; color: #b91c1c; }
.settings-page .st-ollama-diff td.is-conflict, .settings-page .st-ollama-diff th code + .st-muted { background: #fff7ed; }
.settings-page .st-ollama-test-result {
    margin: 12px 0 24px; padding: 12px 14px; border-radius: 12px; border: 1px solid var(--st-border);
    background: #f8fafc; font-size: 13px;
}
.settings-page .st-ollama-pre {
    margin: 8px 0 0; padding: 10px; background: #0f172a; color: #e2e8f0; border-radius: 8px;
    font-size: 12px; white-space: pre-wrap; word-break: break-word; max-height: 220px; overflow: auto;
}
.settings-page .st-ollama-err-cell { color: #b91c1c; font-size: 12px; }
.settings-page .st-ollama-loading { padding: 24px; color: var(--st-muted); }
</style>
