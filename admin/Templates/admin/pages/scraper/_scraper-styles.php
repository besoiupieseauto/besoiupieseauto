<style>
.scraper-page { --sc-primary: #1abc9c; --sc-border: #e2e8f0; --sc-muted: #64748b; }
.scraper-page .hidden { display: none !important; }
.scraper-page .sc-header { display: flex; flex-wrap: wrap; align-items: flex-start; justify-content: space-between; gap: 16px; margin-bottom: 20px; }
.scraper-page .sc-title { font-size: 1.35rem; font-weight: 700; margin: 0; color: #0f172a; }
.scraper-page .sc-subtitle { font-size: 0.85rem; color: var(--sc-muted); margin: 4px 0 0; max-width: 42rem; }
.scraper-page .sc-token-badge { border-radius: 999px; border: 1px solid var(--sc-border); padding: 6px 12px; font-size: 0.72rem; font-weight: 600; }
.scraper-page .sc-cards-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 16px; }
.scraper-page .sc-source-card {
    border: 1px solid var(--sc-border); border-radius: 14px; background: #fff; overflow: hidden;
    display: flex; flex-direction: column; transition: box-shadow .15s, transform .15s;
}
.scraper-page .sc-source-card:hover { box-shadow: 0 8px 24px rgba(15,23,42,.08); transform: translateY(-2px); }
.scraper-page .sc-source-card.is-stub { opacity: .92; }
.scraper-page .sc-card-head { padding: 16px 16px 12px; display: flex; gap: 12px; align-items: flex-start; border-bottom: 1px solid #f1f5f9; }
.scraper-page .sc-card-avatar {
    width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center;
    font-weight: 800; font-size: 0.85rem; color: #fff; flex-shrink: 0;
}
.scraper-page .sc-card-name { font-size: 1.05rem; font-weight: 700; margin: 0; }
.scraper-page .sc-card-domain { font-size: 0.75rem; color: var(--sc-muted); }
.scraper-page .sc-card-desc { font-size: 0.78rem; color: var(--sc-muted); line-height: 1.45; padding: 0 16px 12px; flex: 1; }
.scraper-page .sc-card-badges { display: flex; flex-wrap: wrap; gap: 6px; padding: 0 16px 12px; }
.scraper-page .sc-badge { font-size: 0.65rem; font-weight: 700; text-transform: uppercase; padding: 3px 8px; border-radius: 999px; border: 1px solid var(--sc-border); }
.scraper-page .sc-badge--ok { background: #ecfdf5; color: #059669; border-color: #a7f3d0; }
.scraper-page .sc-badge--warn { background: #fffbeb; color: #d97706; border-color: #fde68a; }
.scraper-page .sc-badge--off { background: #f1f5f9; color: #64748b; }
.scraper-page .sc-card-foot { padding: 12px 16px; border-top: 1px solid #f1f5f9; display: grid; grid-template-columns: 1fr 1fr; gap: 8px; background: #fafbfc; }
.scraper-page .sc-btn-primary, .scraper-page button.sc-btn-primary {
    display: inline-flex; align-items: center; justify-content: center; gap: 6px;
    padding: 10px 16px; border-radius: 10px; border: none; background: var(--sc-primary) !important;
    color: #fff !important; font-size: 0.85rem; font-weight: 600; cursor: pointer;
}
.scraper-page .sc-btn-outline, .scraper-page button.sc-btn-outline {
    display: inline-flex; align-items: center; justify-content: center;
    padding: 10px 16px; border-radius: 10px; border: 1.5px solid #cbd5e1 !important;
    background: #fff !important; color: #334155 !important; font-size: 0.85rem; font-weight: 600; cursor: pointer;
}
.scraper-page .sc-btn-sm { padding: 6px 12px; font-size: 0.78rem; }
.scraper-page .sc-tabs { display: flex; gap: 0; border-bottom: 1px solid var(--sc-border); margin-bottom: 16px; }
.scraper-page .sc-tab {
    padding: 10px 18px; border: none; background: transparent; font-size: 0.85rem; font-weight: 600;
    color: var(--sc-muted); border-bottom: 2px solid transparent; margin-bottom: -1px; cursor: pointer;
}
.scraper-page .sc-tab.is-active { color: var(--sc-primary); border-bottom-color: var(--sc-primary); }
.scraper-page .sc-tab-panel { display: none; }
.scraper-page .sc-tab-panel.is-active { display: block; }
.scraper-page .sc-box { border: 1px solid var(--sc-border); border-radius: 12px; padding: 16px; background: #fff; }
.scraper-page .sc-box-title { font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; opacity: .65; margin: 0 0 12px; }
.scraper-page .sc-field { display: flex; flex-direction: column; gap: 4px; margin-bottom: 12px; }
.scraper-page .sc-field > span { font-size: 0.78rem; font-weight: 600; color: #475569; }
.scraper-page .sc-step-card { border: 1px solid var(--sc-border); border-radius: 12px; overflow: hidden; }
.scraper-page .sc-step-head {
    display: flex; align-items: center; justify-content: space-between; gap: 12px;
    padding: 12px 16px; background: #f8fafc; border-bottom: 1px solid #f1f5f9;
}
.scraper-page .sc-step-head h5 { margin: 0; font-size: 0.9rem; font-weight: 700; }
.scraper-page .sc-step-body { padding: 16px; display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
@media (max-width: 768px) { .scraper-page .sc-step-body { grid-template-columns: 1fr; } }
.scraper-page .sc-step-body .full { grid-column: 1 / -1; }
.scraper-page .sc-trace .sc-trace-row {
    display: flex; gap: 12px; padding: 10px 0; border-bottom: 1px solid #f1f5f9; font-size: 0.82rem;
}
.scraper-page .sc-trace-status { font-weight: 700; min-width: 56px; text-transform: uppercase; font-size: 0.7rem; }
.scraper-page .sc-trace-status.ok { color: #059669; }
.scraper-page .sc-trace-status.error { color: #dc2626; }
.scraper-page .sc-trace-status.warn { color: #d97706; }
.scraper-page .sc-trace-status.skipped { color: #94a3b8; }
.scraper-page .sc-items-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 12px; }
.scraper-page .sc-item-card { border: 1px solid var(--sc-border); border-radius: 10px; overflow: hidden; background: #fff; display: flex; flex-direction: column; }
.scraper-page .sc-item-card img { width: 100%; height: 140px; object-fit: contain; background: #f8fafc; display: block; }
.scraper-page .sc-item-card img.sc-item-img-broken { object-fit: none; background: #f1f5f9; opacity: 0.35; }
.scraper-page .sc-item-noimg { height: 140px; background: #f1f5f9; display: flex; align-items: center; justify-content: center; font-size: 12px; color: #94a3b8; }
.scraper-page .sc-item-body { padding: 10px; flex: 1; display: flex; flex-direction: column; gap: 4px; }
.scraper-page .sc-item-title { font-size: 0.8rem; font-weight: 600; line-height: 1.35; min-height: 2.5em; }
.scraper-page .sc-item-desc { font-size: 0.72rem; line-height: 1.35; color: #64748b; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
.scraper-page .sc-item-price { font-size: 0.9rem; font-weight: 700; color: #059669; margin-top: auto; }
.scraper-page .sc-item-meta { margin-top: 6px; font-size: 0.72rem; line-height: 1.4; color: #64748b; }
.scraper-page .sc-item-meta strong { color: #334155; font-weight: 600; }
.scraper-page .sc-item-link { display: inline-block; margin-top: 4px; font-size: 0.72rem; color: var(--sc-primary); text-decoration: none; word-break: break-all; }
.scraper-page .sc-item-link:hover { text-decoration: underline; }
.scraper-page .sc-pipeline-progress-wrap { margin-top: 14px; padding: 12px 14px; border: 1px solid #e2e8f0; border-radius: 10px; background: #f8fafc; }
.scraper-page .sc-pipeline-status-row { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 8px; }
.scraper-page .sc-pipeline-status { font-size: 0.88rem; font-weight: 600; color: #0f172a; flex: 1; min-width: 0; }
.scraper-page .sc-pipeline-pct { font-size: 0.82rem; font-weight: 700; color: #0d9488; flex-shrink: 0; }
.scraper-page .sc-pipeline-bar-track {
    height: 12px; border-radius: 999px; background: #e2e8f0; overflow: hidden;
}
.scraper-page .sc-pipeline-bar-fill {
    height: 100%; width: 0%; border-radius: 999px; background: linear-gradient(90deg, #1abc9c, #16a085);
    transition: width 0.4s ease;
}
.scraper-page .sc-pipeline-bar-fill.is-indeterminate {
    width: 35% !important;
    animation: sc-pipeline-indet 1.2s ease-in-out infinite;
}
.scraper-page .sc-pipeline-segments {
    display: flex; gap: 4px; margin-top: 8px;
}
.scraper-page .sc-pipeline-segment {
    flex: 1; min-width: 0; text-align: center; font-size: 0.68rem; font-weight: 600; color: #94a3b8;
    padding: 4px 2px; border-radius: 6px; background: #f1f5f9; border: 1px solid #e2e8f0;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.scraper-page .sc-pipeline-segment.is-running { color: #0f766e; background: #ccfbf1; border-color: #5eead4; }
.scraper-page .sc-pipeline-segment.is-ok { color: #166534; background: #dcfce7; border-color: #86efac; }
.scraper-page .sc-pipeline-segment.is-miss { color: #92400e; background: #fef3c7; border-color: #fde68a; }
.scraper-page .sc-pipeline-segment.is-skipped { opacity: 0.55; }
.scraper-page .sc-pipeline-segment.is-error { color: #b91c1c; background: #fee2e2; border-color: #fecaca; }
.scraper-page .sc-pipeline-quota-warn {
    margin-top: 10px; margin-bottom: 4px; padding: 8px 10px; border-radius: 8px; font-size: 0.78rem; line-height: 1.4;
    border: 1px solid #fde68a; background: #fffbeb; color: #92400e;
}
.scraper-page .sc-pipeline-summary {
    margin-top: 12px; padding: 10px 12px; border-radius: 8px; font-size: 0.8rem; line-height: 1.45;
    border: 1px solid #e2e8f0; background: #fff;
}
.scraper-page .sc-pipeline-summary.is-ok { border-color: #86efac; background: #f0fdf4; color: #166534; }
.scraper-page .sc-pipeline-summary.is-fail { border-color: #fecaca; background: #fef2f2; color: #991b1b; }
@keyframes sc-pipeline-indet {
    0% { margin-left: 0; }
    50% { margin-left: 60%; }
    100% { margin-left: 0; }
}
.scraper-page .sc-pipeline-steps { margin-top: 12px; display: flex; flex-direction: column; gap: 6px; }
.scraper-page .sc-pipeline-step {
    display: flex; align-items: flex-start; gap: 10px; padding: 8px 10px; border-radius: 8px;
    border: 1px solid #e2e8f0; background: #f8fafc; font-size: 0.78rem;
}
.scraper-page .sc-pipeline-step.is-running { border-color: #99f6e4; background: #f0fdfa; }
.scraper-page .sc-pipeline-step.is-ok { border-color: #86efac; background: #f0fdf4; }
.scraper-page .sc-pipeline-step.is-miss { border-color: #fde68a; background: #fffbeb; }
.scraper-page .sc-pipeline-step.is-error { border-color: #fecaca; background: #fef2f2; }
.scraper-page .sc-pipeline-step.is-skipped { opacity: 0.65; }
.scraper-page .sc-pipeline-step-time { font-size: 0.7rem; color: #94a3b8; margin-top: 2px; }
.scraper-page .sc-pipeline-step-icon { flex-shrink: 0; width: 1.25rem; text-align: center; }
.scraper-page .sc-pipeline-step-body { flex: 1; min-width: 0; }
.scraper-page .sc-pipeline-step-title { font-weight: 600; color: #1e293b; }
.scraper-page .sc-pipeline-step-msg { color: #64748b; margin-top: 2px; word-break: break-word; }
.scraper-page .sc-pipeline-hit { margin-top: 14px; }
.scraper-page .sc-pipeline-hit-card {
    display: flex; gap: 14px; align-items: flex-start; padding: 12px; border-radius: 10px;
    border: 1px solid #86efac; background: #f0fdf4;
}
.scraper-page .sc-pipeline-hit-card img {
    width: 120px; height: 120px; object-fit: contain; background: #fff; border-radius: 8px; border: 1px solid #e2e8f0;
}
.scraper-page .sc-pipeline-hit-meta { font-size: 0.8rem; line-height: 1.45; }
.scraper-page .sc-pipeline-hit-meta strong { color: #166534; }
.scraper-page .sc-ai-json-toggle { margin-top: 8px; font-size: 0.75rem; color: #64748b; cursor: pointer; background: none; border: none; padding: 0; text-decoration: underline; }
.scraper-page .sc-pre {
    margin: 0; padding: 12px; border-radius: 8px; background: #0f172a; color: #e2e8f0;
    font-size: 0.7rem; line-height: 1.45; overflow: auto; max-height: 240px; white-space: pre-wrap; word-break: break-word;
}
.scraper-page .sc-log-pre { max-height: 480px; }
.scraper-page .scraper-nav-tab {
    border: none; background: transparent; padding: 8px 14px; font-size: 13px; font-weight: 600;
    border-bottom: 2px solid transparent; margin-bottom: -1px; opacity: .65; cursor: pointer;
}
.scraper-page .scraper-nav-tab.is-active { opacity: 1; border-bottom-color: var(--sc-primary); color: var(--sc-primary); }
.scraper-page .scraper-panel { display: none; }
.scraper-page .scraper-panel.is-active { display: block; }
.scraper-page .sc-field-check label { display: inline-flex; align-items: center; gap: 6px; font-size: 0.85rem; }
.scraper-page .sc-btn-danger {
    display: inline-flex; align-items: center; padding: 6px 12px; border-radius: 8px;
    border: 1.5px solid #fecaca !important; background: #fef2f2 !important; color: #b91c1c !important;
    font-size: 0.78rem; font-weight: 600; cursor: pointer;
}
.scraper-page .sc-btn-danger:hover { background: #fee2e2 !important; border-color: #f87171 !important; }
.scraper-page .sc-card-delete {
    position: absolute; top: 10px; right: 10px; width: 28px; height: 28px; border-radius: 8px;
    border: 1px solid #e2e8f0; background: #fff; color: #94a3b8; cursor: pointer; font-size: 14px; line-height: 1;
}
.scraper-page .sc-card-delete:hover { color: #dc2626; border-color: #fecaca; background: #fef2f2; }
.scraper-page .sc-source-card { position: relative; }
.scraper-page .sc-modal { position: fixed; inset: 0; z-index: 100; display: flex; align-items: center; justify-content: center; padding: 16px; }
.scraper-page .sc-modal.hidden { display: none !important; }
.scraper-page .sc-modal-backdrop { position: absolute; inset: 0; background: rgba(15,23,42,.45); }
.scraper-page .sc-step-head { flex-wrap: wrap; }
.scraper-page .sc-step-actions { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; }
.scraper-page .sc-step-move, .scraper-page .sc-step-del, .scraper-page .sc-el-del {
    border: 1px solid #e2e8f0; background: #fff; border-radius: 6px; padding: 4px 8px; font-size: 0.72rem; cursor: pointer;
}
.scraper-page .sc-step-del, .scraper-page .sc-el-del { color: #b91c1c; }
.scraper-page .sc-elements-list { display: flex; flex-direction: column; gap: 8px; grid-column: 1 / -1; }
.scraper-page .sc-element-row {
    display: grid; grid-template-columns: 1fr 2fr auto; gap: 8px; align-items: end;
    padding: 10px; border: 1px dashed #e2e8f0; border-radius: 8px; background: #fafbfc;
}
@media (max-width: 640px) { .scraper-page .sc-element-row { grid-template-columns: 1fr; } }
.scraper-page .sc-type-list { display: flex; flex-direction: column; gap: 6px; }
.scraper-page .sc-type-pick {
    text-align: left; padding: 10px 12px; border: 1px solid #e2e8f0; border-radius: 10px;
    background: #fff; cursor: pointer; font-size: 0.85rem;
}
.scraper-page .sc-type-pick:hover { border-color: var(--sc-primary); background: #f0fdfa; }
.scraper-page .sc-type-pick small { display: block; opacity: 0.65; font-size: 0.75rem; margin-top: 2px; }
.scraper-page .sc-modal-box {
    position: relative; z-index: 1; width: 100%; max-width: 480px; background: #fff;
    border-radius: 14px; padding: 20px 22px; box-shadow: 0 20px 50px rgba(0,0,0,.15);
}
.scraper-page .sc-modal-box--sm { max-width: 420px; }
.scraper-page .sc-btn-add-el {
    grid-column: 1 / -1; border: 1px dashed #94a3b8; background: #f8fafc; color: #475569;
    border-radius: 8px; padding: 8px; font-size: 0.8rem; font-weight: 600; cursor: pointer;
}
.scraper-page .sc-btn-add-el:hover { border-color: var(--sc-primary); color: var(--sc-primary); }

/* Modale în afara .scraper-page — popup adaugă pas / element */
.sc-modal { --sc-primary: #1abc9c; position: fixed; inset: 0; z-index: 9999; display: flex; align-items: center; justify-content: center; padding: 16px; }
.sc-modal.hidden { display: none !important; }
.sc-modal-backdrop { position: absolute; inset: 0; background: rgba(15,23,42,.5); }
.sc-modal-box {
    position: relative; z-index: 1; width: 100%; max-width: 480px; max-height: 90vh; overflow-y: auto;
    background: #fff; border-radius: 14px; padding: 20px 22px; box-shadow: 0 20px 50px rgba(0,0,0,.2);
}
.sc-modal-box--sm { max-width: 440px; }
.sc-modal .sc-title { font-size: 1.1rem; font-weight: 700; margin: 0; color: #0f172a; }
.sc-modal .sc-subtitle { font-size: 0.85rem; color: #64748b; margin: 4px 0 0; }
.sc-modal .sc-field { display: flex; flex-direction: column; gap: 4px; margin-bottom: 12px; }
.sc-modal .sc-field > span { font-size: 0.78rem; font-weight: 600; color: #475569; }
.sc-modal .sc-type-list { display: flex; flex-direction: column; gap: 6px; max-height: 220px; overflow-y: auto; }
.sc-modal .sc-type-pick {
    text-align: left; padding: 10px 12px; border: 2px solid #e2e8f0; border-radius: 10px;
    background: #fff; cursor: pointer; font-size: 0.85rem; transition: border-color .15s, background .15s;
}
.sc-modal .sc-type-pick:hover { border-color: #1abc9c; background: #f0fdfa; }
.sc-modal .sc-type-pick.is-selected { border-color: #1abc9c; background: #ecfdf5; box-shadow: 0 0 0 1px #1abc9c; }
.sc-modal .sc-type-pick small { display: block; opacity: 0.65; font-size: 0.75rem; margin-top: 2px; }
.sc-modal .sc-btn-primary {
    display: inline-flex; align-items: center; justify-content: center; padding: 10px 16px; border-radius: 10px;
    border: none; background: #1abc9c; color: #fff; font-size: 0.85rem; font-weight: 600; cursor: pointer;
}
.sc-modal .sc-btn-outline {
    display: inline-flex; align-items: center; padding: 10px 16px; border-radius: 10px;
    border: 1.5px solid #cbd5e1; background: #fff; color: #334155; font-size: 0.85rem; font-weight: 600; cursor: pointer;
}
.scraper-page .sc-plan-row, .sc-plan-row {
    display: flex; flex-wrap: wrap; gap: 8px; align-items: center;
    padding: 10px 12px; border: 1px solid #e2e8f0; border-radius: 10px; background: #fafbfc;
}
.scraper-page .sc-plan-tier, .sc-plan-tier {
    font-size: 0.72rem; font-weight: 700; text-transform: uppercase; color: #1abc9c;
    min-width: 52px;
}
.scraper-page .sc-plan-row .plan-label, .sc-plan-row .plan-label { min-width: 140px; flex: 1; }
.scraper-page .sc-plan-row .plan-source, .sc-plan-row .plan-source { min-width: 120px; }
</style>
