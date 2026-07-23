<?php
$importEmbedInline = defined('BESOIU_IMPORT_EMBED_INLINE') && BESOIU_IMPORT_EMBED_INLINE;

if (!function_exists('ip_svg')) {
    function ip_svg(string $name): string
    {
        $icons = [
            'upload' => '<path d="M12 16V4m0 0L8 8m4-4 4 4"/><path d="M4 17v1a2 2 0 002 2h12a2 2 0 002-2v-1"/>',
            'folder' => '<path d="M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V7z"/>',
            'sparkles' => '<path d="M12 3l1.2 3.8L17 8l-3.8 1.2L12 13l-1.2-3.8L7 8l3.8-1.2L12 3z"/><path d="M5 15l.7 2.1L8 18l-2.3.9L5 21l-.7-2.1L2 18l2.3-.9L5 15z"/>',
            'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
            'file' => '<path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8l-6-6z"/><path d="M14 2v6h6"/>',
            'trash' => '<path d="M4 7h16"/><path d="M10 11v6M14 11v6"/><path d="M9 7V5a1 1 0 011-1h4a1 1 0 011 1v2"/><path d="M7 7l1 12a2 2 0 002 2h4a2 2 0 002-2l1-12"/>',
            'cards' => '<rect x="3" y="4" width="7" height="9" rx="1"/><rect x="14" y="4" width="7" height="9" rx="1"/><rect x="3" y="15" width="7" height="5" rx="1"/><rect x="14" y="15" width="7" height="5" rx="1"/>',
            'zap' => '<path d="M13 2L3 14h7l-1 8 10-12h-7l1-8z" fill="currentColor" stroke="none"/>',
            'info' => '<circle cx="12" cy="12" r="9"/><path d="M12 10v6M12 7h.01"/>',
            'check' => '<path d="M5 12l4 4L19 7"/>',
            'play' => '<polygon points="10,8 16,12 10,16" fill="currentColor" stroke="none"/>',
            'layers' => '<path d="M12 2l9 5-9 5-9-5 9-5z"/><path d="M3 12l9 5 9-5"/><path d="M3 17l9 5 9-5"/>',
        ];
        $body = $icons[$name] ?? '<circle cx="12" cy="12" r="8"/>';
        $fill = str_contains($body, 'fill="currentColor"') ? 'fill="none"' : 'fill="none"';
        return '<svg class="ip-svg" viewBox="0 0 24 24" width="20" height="20" ' . $fill . ' stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $body . '</svg>';
    }
}

if (!$importEmbedInline):
?><!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import & Matching Produse</title>
<?php endif; ?>
    <style>
        :root {
            --indigo: #14b8a6;
            --green: #16a34a;
            --amber: #d97706;
            --red: #dc2626;
            --border: #e5e7eb;
            --muted: #6b7280;
        }
        * { box-sizing: border-box; }
        body {
            font-family: "Segoe UI", Arial, sans-serif;
            margin: 0;
            padding: 24px;
            background: #f3f4f6;
            color: #111827;
        }
        .wrap { max-width: 1400px; margin: 0 auto; }
        h1 { margin: 0 0 8px; font-size: 28px; }
        .subtitle { color: var(--muted); margin-bottom: 24px; line-height: 1.5; }
        .panel {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 24px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.04);
        }
        .panel h2 { margin: 0 0 14px; font-size: 17px; }
        .row { display: flex; flex-wrap: wrap; gap: 16px; align-items: flex-end; }
        label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; }
        input[type="file"], input[type="number"], select {
            padding: 8px 10px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 14px;
        }
        .btn {
            border: none;
            border-radius: 8px;
            padding: 10px 18px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            color: #fff;
            background: var(--indigo);
        }
        .btn:hover { filter: brightness(1.05); }
        .btn:disabled { opacity: 0.55; cursor: not-allowed; }
        .btn-green { background: var(--green); }
        .btn-amber { background: var(--amber); }
        .btn-outline { background: #fff; color: var(--indigo); border: 1px solid #99f6e4; }
        .btn-sm { padding: 8px 12px; font-size: 13px; }
        .status {
            margin-top: 14px;
            padding: 10px 12px;
            border-radius: 8px;
            font-size: 14px;
        }
        .status.info { background: #f0fdfa; color: #0f766e; }
        .ip-build-progress {
            position: fixed; inset: 0; z-index: 12000;
            display: flex; align-items: center; justify-content: center;
            padding: 16px;
        }
        .ip-build-progress.hidden { display: none; }
        .ip-build-progress__backdrop {
            position: absolute; inset: 0; background: rgba(15, 23, 42, 0.45);
        }
        .ip-build-progress__panel {
            position: relative; z-index: 1; width: min(520px, 100%);
            background: #fff; border-radius: 14px; padding: 20px 22px;
            box-shadow: 0 20px 50px rgba(15, 23, 42, 0.25);
        }
        .ip-build-progress__title { margin: 0 0 4px; font-size: 18px; color: #0f172a; }
        .ip-build-progress__subtitle { margin: 0 0 14px; font-size: 13px; color: #64748b; }
        .ip-build-progress__stats {
            display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 12px;
        }
        .ip-build-progress__stats strong { display: block; font-size: 18px; color: #0f172a; }
        .ip-build-progress__bar-wrap {
            height: 8px; border-radius: 999px; background: #e2e8f0; overflow: hidden;
        }
        .ip-build-progress__bar {
            height: 100%; width: 0; border-radius: inherit;
            background: linear-gradient(90deg, #0d9488, #14b8a6);
            transition: width 0.25s ease;
        }
        .ip-build-progress__bar.is-indeterminate {
            width: 42%; animation: ipBuildProgressPulse 1.4s ease-in-out infinite;
        }
        @keyframes ipBuildProgressPulse {
            0% { transform: translateX(-30%); }
            50% { transform: translateX(120%); }
            100% { transform: translateX(-30%); }
        }
        .ip-build-progress__detail { margin: 12px 0 0; font-size: 13px; color: #334155; line-height: 1.45; }
        .ip-build-progress__log {
            margin-top: 10px; max-height: 120px; overflow-y: auto;
            padding: 8px 10px; border-radius: 8px; background: #0f172a; color: #cbd5e1;
            font-family: ui-monospace, monospace; font-size: 11px;
        }
        .ip-build-progress__log[hidden] { display: none; }
        .ip-build-progress__actions { margin-top: 14px; display: flex; justify-content: flex-end; }
        .ip-build-progress.is-complete .ip-build-progress__bar { background: linear-gradient(90deg, #059669, #10b981); width: 100% !important; }
        .ip-build-progress.is-error .ip-build-progress__bar { background: linear-gradient(90deg, #dc2626, #ef4444); width: 100% !important; }
        .status.ok { background: #ecfdf5; color: #047857; }
        .status.warn { background: #fffbeb; color: #b45309; }
        .status.error { background: #fef2f2; color: #b91c1c; }
        #stageQueueStatus { margin: 10px 0 0; border-radius: 10px; font-size: 13px; }
        .btn.is-disabled,
        .btn[aria-disabled="true"]:not(:disabled) {
            opacity: 0.55;
            cursor: not-allowed;
            filter: grayscale(0.15);
        }
        .tabs { display: flex; gap: 8px; margin-bottom: 16px; flex-wrap: wrap; }
        .tab {
            padding: 8px 14px;
            border-radius: 8px;
            border: 1px solid var(--border);
            background: #f9fafb;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
        }
        .tab.active { background: var(--indigo); color: #fff; border-color: var(--indigo); }
        .tab-panel { display: none; min-height: 120px; }
        .tab-panel.active { display: block; }
        .workflow-nav {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 12px 16px 0;
            margin-bottom: 20px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.04);
        }
        .workflow-tabs {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            border-bottom: 1px solid var(--border);
            padding-bottom: 0;
        }
        .workflow-tab {
            position: relative;
            padding: 12px 18px;
            border: none;
            border-bottom: 3px solid transparent;
            background: transparent;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            color: var(--muted);
            border-radius: 8px 8px 0 0;
            margin-bottom: -1px;
        }
        .workflow-tab:hover { color: #111827; background: #f9fafb; }
        .workflow-tab.active {
            color: var(--indigo);
            border-bottom-color: var(--indigo);
            background: #f0fdfa;
        }
        .workflow-tab.has-results::after {
            content: "";
            position: absolute;
            top: 8px;
            right: 8px;
            width: 8px;
            height: 8px;
            border-radius: 999px;
            background: var(--green);
        }
        .workflow-tab-panel { display: none; }
        .workflow-tab-panel.active { display: block; }
        .workflow-tab-extra {
            margin-bottom: 16px;
        }
        .workflow-tab-extra:empty { display: none; }
        .workflow-tab-extra-slot {
            background: #f8fafc;
            border: 1px dashed #cbd5e1;
            border-radius: 10px;
            padding: 14px 16px;
            font-size: 13px;
            color: var(--muted);
        }
        .empty-results {
            padding: 24px; text-align: center; color: var(--muted);
            border: 1px dashed var(--border); border-radius: 8px; margin-top: 8px;
        }
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
            gap: 20px;
        }
        .product-card {
            border: 1px solid var(--border);
            border-radius: 12px;
            overflow: hidden;
            background: #fff;
            display: flex;
            flex-direction: column;
            box-shadow: 0 4px 16px rgba(0,0,0,0.06);
        }
        .product-card.no-image { border-color: #fcd34d; }
        .product-card-image-wrap {
            position: relative;
            height: 220px;
            background: #f8fafc;
            border-bottom: 1px solid #eef2f7;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .product-card-image { width: 100%; height: 220px; object-fit: contain; }
        .placeholder { color: var(--muted); text-align: center; padding: 16px; font-size: 14px; }
        .badge {
            position: absolute;
            top: 10px;
            left: 10px;
            padding: 4px 8px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
            color: #fff;
        }
        .badge-scraped { background: #0d9488; }
        .badge-poze { background: #059669; }
        .badge-missing { background: #d97706; }
        .badge-exact { background: #16a34a; }
        .badge-probable { background: #ca8a04; }
        .badge-no_match { background: #dc2626; }
        .badge-conflict { background: #ea580c; }
        .product-card-body { padding: 16px; display: flex; flex-direction: column; gap: 10px; flex: 1; }
        .product-card-title { margin: 0; font-size: 15px; line-height: 1.35; font-weight: 700; color: #111827; }
        .meta { display: flex; flex-wrap: wrap; gap: 8px; font-size: 12px; color: #4b5563; }
        .meta span { background: #f3f4f6; padding: 4px 8px; border-radius: 999px; }
        .product-card-params {
            font-size: 13px;
            color: #374151;
        }
        .product-card-params ul {
            margin: 6px 0 0 18px;
            padding: 0;
        }
        .product-card-description {
            font-size: 13px;
            color: #374151;
            max-height: 280px;
            overflow-y: auto;
            border-top: 1px solid #f3f4f6;
            padding-top: 10px;
        }
        .product-card-description p { margin: 0.55em 0 0.25em; }
        .product-card-description p:first-child { margin-top: 0; }
        .product-card-description ul {
            margin: 6px 0 0 18px;
            padding: 0;
            list-style-type: disc;
        }
        .product-card-description ul ul { list-style-type: circle; }
        .product-card-description ul ul ul { list-style-type: square; }
        .product-card-description li { margin: 0 0 0.35em; }
        .price { font-size: 15px; font-weight: 600; color: #374151; }
        .price .price-main { font-size: 18px; font-weight: 700; color: #0f766e; }
        .price small { display: block; font-size: 12px; color: var(--muted); font-weight: 400; margin-top: 2px; }
        .price .price-hint { font-size: 11px; opacity: 0.85; color: #92400e; }
        .card-actions { margin-top: auto; display: flex; gap: 8px; flex-wrap: wrap; }
        .scrape-log { font-size: 12px; color: var(--muted); min-height: 18px; }
        .tecdoc-audit {
            margin-top: 4px;
            border: 1px solid #ccfbf1;
            border-radius: 8px;
            background: #f3fbf8;
            font-size: 11px;
            line-height: 1.45;
        }
        .tecdoc-audit summary {
            cursor: pointer;
            padding: 8px 10px;
            font-weight: 600;
            color: #0a3d31;
            list-style: none;
        }
        .tecdoc-audit summary::-webkit-details-marker { display: none; }
        .tecdoc-audit summary::before { content: '▸ '; }
        .tecdoc-audit[open] summary::before { content: '▾ '; }
        .tecdoc-audit-body { padding: 0 10px 10px; color: #374151; }
        .tecdoc-audit dl { margin: 0; display: grid; gap: 4px; }
        .tecdoc-audit dt { font-weight: 600; color: #1f2937; margin-top: 6px; }
        .tecdoc-audit dt:first-child { margin-top: 0; }
        .tecdoc-audit dd { margin: 0 0 0 8px; word-break: break-word; }
        .tecdoc-audit .audit-ok { color: #166534; }
        .tecdoc-audit .audit-warn { color: #854d0e; }
        .tecdoc-audit .audit-miss { color: #991b1b; }
        .tecdoc-pill {
            display: inline-block;
            background: #ccfbf1;
            color: #0a3d31;
            padding: 2px 7px;
            border-radius: 999px;
            font-size: 10px;
            font-weight: 700;
            margin-right: 4px;
        }
        .toolbar {
            display: flex; flex-wrap: wrap; gap: 10px; align-items: center;
            justify-content: space-between; margin-bottom: 16px;
        }
        .link { color: var(--indigo); text-decoration: none; font-size: 14px; }
        .link:hover { text-decoration: underline; }
        .progress-wrap {
            height: 8px; background: #e5e7eb; border-radius: 999px;
            overflow: hidden; margin-top: 10px;
        }
        .progress-bar {
            height: 100%; width: 0%;
            background: linear-gradient(90deg, #14b8a6, #0d9488);
            transition: width 0.25s ease;
        }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th, td { border-bottom: 1px solid var(--border); padding: 8px 10px; text-align: left; vertical-align: top; }
        th { color: var(--muted); font-weight: 600; background: #f9fafb; }
        tr.hidden { display: none; }
        .match-badge {
            display: inline-block; padding: 2px 8px; border-radius: 6px;
            font-size: 11px; font-weight: 700; text-transform: uppercase;
        }
        .match-badge.exact { background: #dcfce7; color: #166534; }
        .match-badge.probable { background: #fef9c3; color: #854d0e; }
        .match-badge.no_match { background: #fee2e2; color: #991b1b; }
        .match-badge.conflict { background: #ffedd5; color: #9a3412; }
        .filters { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 12px; }
        .filter-btn {
            padding: 6px 12px; border-radius: 8px; border: 1px solid var(--border);
            background: #f9fafb; cursor: pointer; font-size: 13px;
        }
        .filter-btn.active { border-color: var(--indigo); color: var(--indigo); background: #f0fdfa; }
        .stats-row { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 12px; }
        .stat-pill {
            padding: 6px 12px; border-radius: 999px; font-size: 12px; font-weight: 600;
            background: #f3f4f6;
        }
        .stat-pill.exact { background: #dcfce7; color: #166534; }
        .stat-pill.probable { background: #fef9c3; color: #854d0e; }
        .stat-pill.no_match { background: #fee2e2; color: #991b1b; }
        .stat-pill.conflict { background: #ffedd5; color: #9a3412; }
        details summary { cursor: pointer; font-weight: 600; color: var(--indigo); }
        .hint { font-size: 12px; color: var(--muted); margin-top: 6px; }
        .showcase-scan-progress {
            margin-top: 12px;
            padding: 14px 16px;
            border-radius: 12px;
            border: 1px solid #99f6e4;
            background: linear-gradient(180deg, #f0fdfa 0%, #f8fafc 100%);
        }
        .showcase-scan-progress[hidden] { display: none !important; }
        .showcase-scan-progress__head {
            display: flex;
            flex-wrap: wrap;
            gap: 10px 16px;
            align-items: center;
            justify-content: space-between;
            font-size: 13px;
            font-weight: 600;
            color: #0a3d31;
            margin-bottom: 8px;
        }
        .showcase-scan-progress__pct {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 999px;
            background: #fff;
            border: 1px solid #99f6e4;
            font-variant-numeric: tabular-nums;
        }
        .showcase-scan-progress__spinner {
            width: 14px;
            height: 14px;
            border: 2px solid #14b8a6;
            border-right-color: transparent;
            border-radius: 50%;
            animation: ipSpin 0.75s linear infinite;
        }
        .showcase-scan-progress__file {
            flex: 1 1 220px;
            min-width: 0;
            color: #1e1b4b;
        }
        .showcase-scan-progress__elapsed {
            font-size: 12px;
            color: #64748b;
            font-weight: 500;
        }
        .showcase-scan-progress__log {
            margin: 10px 0 0;
            padding: 0;
            list-style: none;
            max-height: 200px;
            overflow-y: auto;
            font-size: 12px;
            line-height: 1.45;
        }
        .showcase-scan-progress__log li {
            padding: 6px 8px;
            border-bottom: 1px solid rgba(148, 163, 184, 0.25);
            color: #334155;
        }
        .showcase-scan-progress__log li:last-child { border-bottom: none; }
        .showcase-scan-progress__log li.is-error { color: #b91c1c; background: #fef2f2; }
        .showcase-scan-progress__log li.is-warn { color: #92400e; background: #fffbeb; }
        .showcase-scan-progress__log li.is-ok { color: #166534; }
        .showcase-scan-progress__log li.is-active {
            background: #ccfbf1;
            color: #0a3d31;
            font-weight: 600;
        }
        @keyframes ipSpin { to { transform: rotate(360deg); } }
        .showcase-files-panel.highlight-pulse {
            box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.45);
            transition: box-shadow 0.3s ease;
        }
        .showcase-types-manager { margin-bottom: 16px; }
        .showcase-types-label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.02em;
            color: #0f766e;
        }
        .showcase-types-tabs {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: 1px solid var(--border);
        }
        .showcase-types-tabs .tab {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 14px;
            border-radius: 8px 8px 0 0;
            margin-bottom: -1px;
            border: 1px solid var(--border);
            border-bottom: none;
            background: #f9fafb;
            font-size: 13px;
        }
        .showcase-types-tabs .tab.active {
            background: #fff;
            color: #0f766e;
            border-color: #c4b5fd;
            box-shadow: inset 0 2px 0 #0d9488;
        }
        .showcase-types-tabs .tab.tab-add {
            background: #f0fdfa;
            color: #0f766e;
            border-style: dashed;
            border-bottom: 1px dashed #c4b5fd;
            margin-bottom: 0;
            border-radius: 8px;
        }
        .showcase-types-tabs .tab.tab-add:hover { background: #ede9fe; }
        .showcase-types-panels {
            border: 1px solid #c4b5fd;
            border-top: none;
            border-radius: 0 0 10px 10px;
            padding: 16px;
            background: #fff;
            min-height: 96px;
        }
        .showcase-type-panel { display: none; }
        .showcase-type-panel.active { display: block; }
        .showcase-type-panel .ip-field { margin-bottom: 0; }
        .showcase-type-panel input[type="text"] {
            width: 100%;
            max-width: 420px;
            padding: 8px 10px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 14px;
        }
        .showcase-type-panel-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
            margin-top: 14px;
        }
        .showcase-type-scan-row {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 10px;
            font-size: 13px;
            color: #374151;
        }
        .showcase-type-scan-row input { margin: 0; }
        .showcase-types-scan-tools {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 10px;
        }
        .showcase-types-scan-tools .btn-sm { font-size: 12px; padding: 4px 10px; }
        .showcase-types-empty {
            padding: 24px 16px;
            text-align: center;
            color: var(--muted);
            border: 1px dashed #c4b5fd;
            border-radius: 10px;
            background: #faf5ff;
        }
        .showcase-types-empty .btn { margin-top: 10px; }
        .step-num {
            display: inline-flex; align-items: center; justify-content: center;
            width: 24px; height: 24px; border-radius: 999px; background: var(--indigo);
            color: #fff; font-size: 12px; font-weight: 700; margin-right: 8px;
        }
        .panel h2 { display: flex; align-items: center; }
        .file-table { width: 100%; font-size: 13px; }
        .file-table tr { cursor: pointer; }
        .file-table tr.selected { background: #f0fdfa; }
        .file-table tr.checked { background: #f0fdf4; border-left: 3px solid var(--green); }
        .file-table tr.checked.selected { background: #ecfdf5; }
        .file-table input[type="checkbox"] { width: 16px; height: 16px; cursor: pointer; }
        .lib-toolbar { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 10px; align-items: center; }
        .btn-danger-outline { background: #fff; color: #b91c1c; border: 1px solid #fecaca; }
        .btn-danger-outline:hover { background: #fef2f2; }
        .folder-row td { background: #f8fafc; font-weight: 600; border-top: 2px solid #e2e8f0; }
        .folder-row code { font-size: 12px; color: #334155; background: #f0fdfa; padding: 2px 6px; border-radius: 4px; }
        .lib-base-path { font-size: 13px; color: var(--muted); margin-bottom: 10px; }
        .lib-base-path code { background: #f3f4f6; padding: 2px 6px; border-radius: 4px; }
        .file-table tr:hover { background: #f9fafb; }
        .status-pill {
            display: inline-block; padding: 2px 8px; border-radius: 999px;
            font-size: 11px; font-weight: 700; text-transform: uppercase;
        }
        .status-pill.new { background: #ccfbf1; color: #0f766e; }
        .status-pill.processed { background: #dcfce7; color: #166534; }
        .status-pill.modified { background: #fef9c3; color: #854d0e; }
        .cron-box {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px; margin-bottom: 14px;
        }
        .cron-stat {
            background: #f9fafb; border: 1px solid var(--border); border-radius: 8px;
            padding: 12px; font-size: 13px;
        }
        .cron-log-panel {
            margin-top: 16px; border: 1px solid #1e293b; border-radius: 10px; overflow: hidden;
        }
        .cron-log-header {
            background: #0f172a; color: #e2e8f0; padding: 10px 14px; font-size: 13px; font-weight: 600;
            display: flex; justify-content: space-between; align-items: center;
        }
        .cron-log-header .live-dot {
            width: 8px; height: 8px; border-radius: 999px; background: #22c55e;
            display: inline-block; margin-right: 6px;
            box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.65);
            animation: cron-live-pulse 1.4s ease-out infinite;
        }
        .cron-log-header .live-dot.off {
            background: #64748b; box-shadow: none; animation: none;
        }
        @keyframes cron-live-pulse {
            0% { box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.65); }
            70% { box-shadow: 0 0 0 7px rgba(34, 197, 94, 0); }
            100% { box-shadow: 0 0 0 0 rgba(34, 197, 94, 0); }
        }
        .cron-toast-container {
            position: fixed; top: 16px; right: 16px; z-index: 9999;
            display: flex; flex-direction: column; gap: 8px; max-width: 360px;
        }
        .cron-toast {
            background: #0f172a; color: #e2e8f0; border-radius: 10px; padding: 12px 16px;
            font-size: 13px; line-height: 1.4; box-shadow: 0 8px 24px rgba(0,0,0,.28);
            border-left: 4px solid #64748b; opacity: 0; transform: translateX(16px);
            transition: opacity .25s ease, transform .25s ease;
        }
        .cron-toast.is-visible { opacity: 1; transform: translateX(0); }
        .cron-toast-ok { border-left-color: #22c55e; }
        .cron-toast-warn { border-left-color: #f59e0b; }
        .cron-toast-error { border-left-color: #ef4444; }
        .cron-toast-info { border-left-color: #5eead4; }
        .cron-log-console {
            margin: 0; padding: 14px; background: #0b1220; color: #86efac;
            font-family: Consolas, "Courier New", monospace; font-size: 12px; line-height: 1.45;
            max-height: 300px; overflow: auto; white-space: pre-wrap; word-break: break-word;
        }
        .cron-progress-head {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 8px; font-size: 14px; font-weight: 600;
        }
        .cron-progress-head span:last-child { color: var(--indigo); }
        .cron-steps {
            margin-top: 12px; max-height: 200px; overflow: auto;
            border: 1px solid var(--border); border-radius: 8px; background: #fafafa;
        }
        .cron-step {
            display: flex; gap: 10px; padding: 8px 12px; border-bottom: 1px solid #eef2f7;
            font-size: 12px; align-items: flex-start;
        }
        .cron-step:last-child { border-bottom: none; }
        .cron-step-time { color: var(--muted); min-width: 68px; font-family: Consolas, monospace; }
        .cron-step-msg { flex: 1; }
        .cron-step.ok .cron-step-msg { color: #047857; }
        .cron-step.warn .cron-step-msg { color: #b45309; }
        .cron-step.error .cron-step-msg { color: #b91c1c; }
        .cron-step.active { background: #f0fdfa; }
        .cron-staging-panel {
            margin-top: 16px;
            border: 1px solid #ccfbf1;
            border-radius: 10px;
            background: #f3fbf8;
            padding: 14px 16px;
        }
        .cron-staging-stats {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 12px;
        }
        .cron-staging-stat {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 8px 12px;
            min-width: 100px;
        }
        .cron-staging-stat strong { display: block; font-size: 18px; color: #0a3d31; }
        .cron-staging-stat span { font-size: 11px; color: #64748b; }
        .cron-staging-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
            background: #fff;
        }
        .cron-staging-table th, .cron-staging-table td {
            border-bottom: 1px solid #e2e8f0;
            padding: 8px 10px;
            text-align: left;
            vertical-align: top;
        }
        .cron-staging-table th {
            background: #f1f5f9;
            font-weight: 700;
            color: #334155;
        }
        .cron-staging-table tr.row-queued { background: #ecfdf5; }
        .cron-staging-table tr.row-skipped { background: #fff7ed; }
        .cron-staging-table tr.row-candidate { background: #f8fafc; }
        .cron-staging-table-wrap { max-height: 360px; overflow: auto; border: 1px solid #e2e8f0; border-radius: 8px; }
        .cron-schedule-panel {
            margin: 18px 0 16px;
            border: 1px solid #ccfbf1;
            border-radius: 12px;
            overflow: hidden;
            background: linear-gradient(180deg, #f3fbf8 0%, #fff 100%);
            box-shadow: 0 4px 16px rgba(20, 184, 166, 0.06);
        }
        .cron-schedule-head {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
            justify-content: space-between;
            padding: 14px 16px;
            border-bottom: 1px solid #ccfbf1;
            background: #f0fdfa;
        }
        .cron-schedule-head h3 { margin: 0; font-size: 15px; color: #0a3d31; }
        .cron-schedule-filters { display: flex; flex-wrap: wrap; gap: 6px; }
        .cron-schedule-filters .filter-btn { font-size: 12px; padding: 5px 10px; }
        .cron-schedule-table-wrap { overflow: auto; }
        .cron-schedule-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 13px;
        }
        .cron-schedule-table th {
            position: sticky;
            top: 0;
            z-index: 1;
            background: #f8fafc;
            color: #475569;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            padding: 10px 12px;
            border-bottom: 1px solid #e2e8f0;
        }
        .cron-schedule-table td {
            padding: 12px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }
        .cron-schedule-table tbody tr:hover { background: #f8fafc; }
        .cron-schedule-table tbody tr.row-due { background: #ecfdf5; }
        .cron-supplier-name { font-weight: 700; color: #0f172a; }
        .cron-supplier-slug {
            display: block;
            font-size: 11px;
            color: var(--muted);
            font-family: Consolas, monospace;
            margin-top: 2px;
        }
        .cron-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 9px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
            white-space: nowrap;
        }
        .cron-badge.mode-interval { background: #ccfbf1; color: #0f766e; }
        .cron-badge.mode-daily { background: #ede9fe; color: #0f766e; }
        .cron-badge.mode-window { background: #fef3c7; color: #b45309; }
        .cron-badge.mode-manual { background: #f1f5f9; color: #64748b; }
        .cron-badge.status-due { background: #dcfce7; color: #166534; animation: cronPulse 1.6s ease-in-out infinite; }
        .cron-badge.status-waiting { background: #fef9c3; color: #854d0e; }
        .cron-badge.status-idle { background: #f3f4f6; color: #4b5563; }
        .cron-badge.status-manual { background: #e2e8f0; color: #475569; }
        .cron-badge.status-paused { background: #ffedd5; color: #9a3412; }
        .cron-badge.status-stopped { background: #fee2e2; color: #991b1b; }
        .cron-countdown {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-family: Consolas, "Courier New", monospace;
            font-size: 14px;
            font-weight: 700;
            color: #4338ca;
            background: #f0fdfa;
            border: 1px solid #99f6e4;
            border-radius: 8px;
            padding: 4px 10px;
            min-width: 88px;
            justify-content: center;
        }
        .cron-countdown.due-now { color: #047857; background: #ecfdf5; border-color: #86efac; }
        .cron-countdown.paused { color: #9a3412; background: #fff7ed; border-color: #fdba74; }
        .cron-countdown.manual { color: #64748b; background: #f8fafc; border-color: #e2e8f0; }
        .cron-files-pill {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 600;
            background: #f3f4f6;
            color: #374151;
        }
        .cron-files-pill.has-pending { background: #ccfbf1; color: #0f766e; }
        .cron-schedule-link {
            color: var(--indigo);
            text-decoration: none;
            font-size: 12px;
            font-weight: 600;
        }
        .cron-schedule-link:hover { text-decoration: underline; }
        @keyframes cronPulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.72; }
        }
        .scan-resume-panel {
            margin: 14px 0 4px;
            padding: 14px 16px;
            border: 1px solid #ccfbf1;
            border-radius: 10px;
            background: #f3fbf8;
        }
        .scan-resume-label {
            display: block;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            color: #0f766e;
            margin-bottom: 8px;
        }
        .scan-resume-modes {
            display: flex;
            flex-wrap: wrap;
            gap: 12px 18px;
            align-items: center;
            font-size: 13px;
        }
        .scan-resume-modes label { display: inline-flex; align-items: center; gap: 6px; margin: 0; cursor: pointer; }
        .scan-log-table-wrap { overflow: auto; margin-top: 12px; }
        .scan-log-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .scan-log-table th, .scan-log-table td {
            border-bottom: 1px solid var(--border);
            padding: 10px 12px;
            text-align: left;
            vertical-align: top;
        }
        .scan-log-table th {
            background: #f8fafc;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #64748b;
        }
        .scan-log-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
            background: #f0fdfa;
            color: #4338ca;
        }
        .scan-log-history {
            margin: 0;
            padding-left: 16px;
            font-size: 12px;
            color: var(--muted);
        }
        .selected-file-banner {
            margin-top: 10px; padding: 10px 12px; border-radius: 8px;
            background: #f0fdfa; border: 1px solid #99f6e4; font-size: 14px;
        }
        .inspect-overlay {
            position: fixed; inset: 0; background: rgba(15, 23, 42, 0.55);
            display: none; align-items: center; justify-content: center;
            z-index: 9999; padding: 20px;
        }
        .inspect-overlay.open { display: flex; }
        .inspect-modal {
            background: #fff; border-radius: 14px; width: min(1100px, 100%);
            max-height: 92vh; overflow: hidden; display: flex; flex-direction: column;
            box-shadow: 0 24px 48px rgba(0,0,0,0.2);
        }
        .inspect-modal-head {
            display: flex; justify-content: space-between; align-items: center;
            padding: 14px 18px; border-bottom: 1px solid var(--border);
            background: #f8fafc;
        }
        .inspect-modal-head h3 { margin: 0; font-size: 16px; }
        .inspect-modal-body { overflow: auto; padding: 14px 18px; flex: 1; }
        .inspect-meta { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 12px; }
        .inspect-meta span {
            font-size: 11px; background: #f3f4f6; padding: 3px 8px; border-radius: 999px;
        }
        .inspect-grid {
            display: grid; grid-template-columns: 1fr 300px; gap: 14px;
            align-items: start; margin-bottom: 14px;
        }
        @media (max-width: 860px) { .inspect-grid { grid-template-columns: 1fr; } }
        .inspect-box {
            border: 1px solid var(--border); border-radius: 10px; overflow: hidden;
        }
        .inspect-box h4 {
            margin: 0; padding: 8px 12px; font-size: 13px; background: #f8fafc;
            border-bottom: 1px solid var(--border);
        }
        .inspect-csv-wrap { overflow: auto; max-height: 280px; }
        .inspect-csv-wrap table { font-size: 12px; margin: 0; }
        .inspect-csv-wrap th {
            position: sticky; top: 0; z-index: 1; white-space: nowrap;
            background: #f9fafb;
        }
        .inspect-csv-wrap th.mapped { background: #f0fdfa; border-top: 3px solid var(--indigo); }
        .inspect-links { padding: 8px 10px; display: flex; flex-direction: column; gap: 8px; }
        .inspect-link-row {
            display: grid; grid-template-columns: 22px 82px 1fr; gap: 6px; align-items: center;
            font-size: 12px;
        }
        .inspect-link-row.off { opacity: 0.6; }
        .inspect-link-row.off select { background: #f3f4f6; color: var(--muted); }
        .inspect-link-row .link-active-cb { width: 16px; height: 16px; cursor: pointer; margin: 0; }
        .field-tag {
            font-size: 9px; font-weight: 700; padding: 1px 5px; border-radius: 4px;
            margin-left: 4px; vertical-align: middle;
        }
        .field-tag.match { background: #ccfbf1; color: #0f766e; }
        .field-tag.data { background: #f3f4f6; color: #6b7280; }
        .inspect-link-row.data-only label { color: var(--muted); font-weight: 500; }
        .inspect-link-section {
            font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em;
            color: var(--muted); margin: 8px 0 4px; padding-top: 6px;
        }
        .inspect-link-section:first-child { margin-top: 0; padding-top: 0; }
        .inspect-code-only {
            display: flex; align-items: center; gap: 8px; font-size: 12px; font-weight: 600;
            padding: 8px 10px; margin-bottom: 8px; background: #f0fdfa; border-radius: 8px;
            border: 1px solid #99f6e4; cursor: pointer;
        }
        .inspect-code-only input { width: 16px; height: 16px; }
        .inspect-link-row label { font-weight: 600; color: #374151; }
        .inspect-link-row.match-key label { color: #0f766e; }
        .inspect-link-row select {
            width: 100%; padding: 5px 6px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 12px;
        }
        .inspect-link-row select.unmapped { border-color: #fca5a5; background: #fef2f2; }
        .inspect-link-row .sample { grid-column: 2 / -1; font-size: 11px; color: var(--muted); }
        .inspect-preview-wrap { overflow: auto; max-height: 160px; }
        .inspect-preview-wrap table { font-size: 12px; margin: 0; }
        .inspect-norm-issues { padding: 8px 12px; font-size: 12px; display: flex; flex-direction: column; gap: 6px; max-height: 200px; overflow: auto; }
        .norm-issue { padding: 6px 8px; border-radius: 6px; border-left: 3px solid #d1d5db; background: #f9fafb; }
        .norm-issue.error { border-left-color: var(--red); background: #fef2f2; }
        .norm-issue.warn { border-left-color: var(--amber); background: #fffbeb; }
        .norm-issue.info { border-left-color: #2dd4bf; background: #f0fdfa; }
        .norm-issue .msg { font-weight: 600; }
        .norm-issue .sug { font-size: 11px; color: var(--muted); margin-top: 2px; }
        .norm-issue .ollama-tag { font-size: 10px; background: #ede9fe; color: #5b21b6; padding: 1px 5px; border-radius: 4px; margin-left: 6px; }
        .inspect-footer {
            display: flex; flex-wrap: wrap; gap: 8px; align-items: center;
            padding-top: 12px; border-top: 1px solid var(--border); margin-top: 4px;
        }
        .inspect-footer input[type="text"] {
            flex: 1; min-width: 180px; padding: 7px 10px; border: 1px solid #d1d5db;
            border-radius: 8px; font-size: 12px;
        }
        .inspect-footer .hint { font-size: 11px; flex: 1 1 100%; margin: 0; }
        .btn-purple { background: #0d9488; }
        .match-dot {
            display: inline-block; width: 8px; height: 8px; border-radius: 50%; margin-right: 4px;
        }
        .match-dot.exact { background: #16a34a; }
        .match-dot.probable { background: #ca8a04; }
        .match-dot.no_match { background: #dc2626; }
        .match-dot.conflict { background: #ea580c; }
        .inspect-db-list { padding: 8px 12px; font-size: 12px; display: flex; flex-direction: column; gap: 8px; }
        .inspect-db-item {
            padding: 8px 10px; border-radius: 8px; background: #f8fafc; border: 1px solid var(--border);
        }
        .inspect-db-item.inactive { opacity: 0.55; }
        .inspect-db-item strong { color: #0a3d31; }
        .inspect-db-item code { font-size: 11px; background: #f0fdfa; padding: 1px 5px; border-radius: 4px; }
        .inspect-extra-block {
            margin-top: 10px; padding-top: 10px; border-top: 1px dashed var(--border);
        }
        .inspect-extra-block label { display: block; font-size: 11px; font-weight: 600; color: #374151; margin-bottom: 6px; }
        .extra-code-tags { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 6px; min-height: 24px; }
        .extra-code-tag {
            display: inline-flex; align-items: center; gap: 4px; padding: 3px 8px;
            background: #fef9c3; border: 1px solid #fde047; border-radius: 999px; font-size: 11px;
        }
        .extra-code-tag button {
            border: none; background: none; cursor: pointer; color: #991b1b; font-weight: 700; padding: 0 2px;
        }
        .extra-code-add { display: flex; gap: 6px; }
        .extra-code-add select { flex: 1; padding: 5px 6px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 12px; }
        .db-col { font-size: 11px; color: #0f766e; font-weight: 600; }
<?php if ($importEmbedInline): ?>
        .import-pro-inline-app { width: 100%; background: transparent; color: #111827; font-family: "Segoe UI", Arial, sans-serif; }
        .import-pro-inline-app .wrap { max-width: none; margin: 0; width: 100%; }
<?php endif; ?>
    </style>
<?php if (defined('BESOIU_IMPORT_PUBLIC_WRAPPER') && BESOIU_IMPORT_PUBLIC_WRAPPER): ?>
<link rel="stylesheet" href="/admin/assets/css/import-pro-ai.css?v=20260718-ipai1">
<?php endif; ?>
<?php if (!$importEmbedInline): ?>
</head>
<body>
<?php else: ?>
<div class="import-pro-inline-app import-pro-ui" id="import-pro-inline-app">
<?php endif; ?>
<div class="wrap import-pro-ui">
    <header class="ip-hero">
        <div class="ip-hero__main">
            <div class="ip-hero__badge"><?= ip_svg('zap') ?> Import Pro · Besoiu</div>
            <h1 class="ip-hero__title">De la lista furnizor la produse gata de catalog</h1>
            <p class="ip-hero__lead">
                Sistem simplu în 3 pași: <strong>încarci CSV</strong>, <strong>bifezi fișierele</strong>, <strong>generezi carduri din MySQL TecDoc</strong>.
                Cronul poate continua automat în fundal.
            </p>
            <div class="ip-hero__links">
                <a href="../fetch%20product/"><?= ip_svg('layers') ?> Fetch Product</a>
                <a href="../Scraper/" target="_blank" rel="noopener"><?= ip_svg('sparkles') ?> Scraper Hub</a>
            </div>
        </div>
        <div class="ip-kpi-grid">
            <div class="ip-kpi">
                <div class="ip-kpi__icon"><?= ip_svg('file') ?></div>
                <div><strong id="ipKpiFiles">—</strong><span>Fișiere pe server</span></div>
            </div>
            <div class="ip-kpi">
                <div class="ip-kpi__icon"><?= ip_svg('clock') ?></div>
                <div><strong id="ipKpiPending">—</strong><span>De procesat (cron)</span></div>
            </div>
            <div class="ip-kpi">
                <div class="ip-kpi__icon"><?= ip_svg('check') ?></div>
                <div><strong id="ipKpiSelected">0</strong><span>Bifate acum</span></div>
            </div>
            <div class="ip-kpi">
                <div class="ip-kpi__icon"><?= ip_svg('cards') ?></div>
                <div><strong id="ipKpiCron">—</strong><span>Stare cron</span></div>
            </div>
        </div>
    </header>
    <div id="indexStatus" class="status info" hidden></div>

    <nav class="workflow-nav" aria-label="Pași import">
        <div class="workflow-tabs" role="tablist">
            <button type="button" class="workflow-tab active" role="tab" aria-selected="true" data-workflow-tab="upload" id="workflowTabUpload">
                <span class="step-num">1</span>
                <span class="workflow-tab__text">
                    <strong>Încarcă fișier</strong>
                    <small>CSV furnizor pe server</small>
                </span>
            </button>
            <button type="button" class="workflow-tab" role="tab" aria-selected="false" data-workflow-tab="library" id="workflowTabLibrary">
                <span class="step-num">2+3</span>
                <span class="workflow-tab__text">
                    <strong>Bibliotecă &amp; matching</strong>
                    <small>Bifezi, generezi carduri, vezi rezultate</small>
                </span>
            </button>
            <button type="button" class="workflow-tab" role="tab" aria-selected="false" data-workflow-tab="showcase" id="workflowTabShowcase">
                <span class="step-num">V</span>
                <span class="workflow-tab__text">
                    <strong>Produse-vitrină</strong>
                    <small>Scanare manuală după tip produs (nume)</small>
                </span>
            </button>
            <button type="button" class="workflow-tab" role="tab" aria-selected="false" data-workflow-tab="log" id="workflowTabLog">
                <span class="step-num">L</span>
                <span class="workflow-tab__text">
                    <strong>Log scanare</strong>
                    <small>Poziție oprită per fișier</small>
                </span>
            </button>
            <button type="button" class="workflow-tab" role="tab" aria-selected="false" data-workflow-tab="cron" id="workflowTabCron">
                <span class="step-num">4</span>
                <span class="workflow-tab__text">
                    <strong>Cron automat</strong>
                    <small>Procesare în fundal + log live</small>
                </span>
            </button>
        </div>
    </nav>

    <div class="workflow-tab-panel active" id="workflow-tab-upload" role="tabpanel" aria-labelledby="workflowTabUpload">
        <div class="workflow-tab-extra" id="workflowExtra-upload"></div>

    <section class="panel ip-panel">
        <header class="ip-panel__head">
            <div class="ip-panel__icon"><?= ip_svg('upload') ?></div>
            <div>
                <h2>Încarcă fișier furnizor</h2>
                <p class="ip-panel__desc">Pas 1 · Alege furnizorul și urcă lista de prețuri (.csv / .txt)</p>
            </div>
        </header>
        <div class="ip-panel__body">
        <div class="ip-callout ip-callout--tip" id="uploadFurnizoriHint">
            <span class="ip-callout__icon"><?= ip_svg('info') ?></span>
            <div>Fișierele se salvează în folderul feed al furnizorului din <strong>Admin → Furnizori</strong> (<code id="uploadFeedPathHint">admin/storage/supplier_feeds/</code>). Doar furnizorii înregistrați și activi pot fi importați.</div>
        </div>
        <div class="ip-callout ip-callout--info" id="uploadNoSuppliersHint" hidden>
            <span class="ip-callout__icon"><?= ip_svg('info') ?></span>
            <div>Nu există furnizori activi în modulul Furnizori. <a class="link" href="/admin/furnizori">Adaugă furnizori</a> înainte de upload.</div>
        </div>
        <div class="ip-upload-zone">
            <div class="ip-upload-zone__visual"><?= ip_svg('upload') ?> CSV</div>
            <div class="ip-upload-fields">
                <div class="ip-field">
                    <label for="uploadSupplier">Furnizor (din modul Furnizori)</label>
                    <select id="uploadSupplier" required>
                        <option value="">— selectează furnizor —</option>
                    </select>
                    <p class="hint" id="uploadSupplierMeta"></p>
                </div>
                <div class="ip-field">
                    <label for="uploadInput">Fișier listă preț</label>
                    <input type="file" id="uploadInput" accept=".csv,.txt">
                </div>
            </div>
            <div class="ip-upload-actions">
                <button type="button" class="btn btn-green" id="uploadBtn"><?= ip_svg('upload') ?> Încarcă pe server</button>
            </div>
        </div>
        <div id="uploadStatus" class="status info" hidden></div>
        </div>
    </section>

    <section class="panel ip-panel" id="uploadFilesPanel">
        <header class="ip-panel__head">
            <div class="ip-panel__icon ip-panel__icon--green"><?= ip_svg('folder') ?></div>
            <div>
                <h2>Fișiere încărcate</h2>
                <p class="ip-panel__desc">Sincronizat live cu tabul Bibliotecă — bifezi aici, vezi aceleași fișiere la Pasul 2</p>
            </div>
        </header>
        <div class="ip-panel__body">
        <div class="lib-toolbar">
            <button type="button" class="btn btn-sm btn-outline" id="uploadSelectAllBtn">Bifează toate</button>
            <button type="button" class="btn btn-sm btn-outline" id="uploadSelectNoneBtn">Debifează</button>
            <button type="button" class="btn btn-sm btn-danger-outline" id="uploadDeleteSelectedBtn">Șterge bifate</button>
            <span class="hint" id="uploadSelectedCountLabel">0 fișiere selectate</span>
        </div>
        <div style="overflow:auto; max-height: 420px;">
            <table class="file-table" id="uploadFilesTable">
                <thead>
                <tr>
                    <th style="width:36px"><input type="checkbox" id="uploadSelectAllCheckbox" title="Bifează toate"></th>
                    <th>Furnizor</th><th>Fișier</th><th>Mărime</th><th>Modificat</th><th>Status</th><th>Carduri</th><th>Acțiuni</th>
                </tr>
                </thead>
                <tbody id="uploadFilesBody">
                <tr><td colspan="8">Se încarcă…</td></tr>
                </tbody>
            </table>
        </div>
        </div>
    </section>
    </div>

    <div class="workflow-tab-panel" id="workflow-tab-library" role="tabpanel" aria-labelledby="workflowTabLibrary" hidden>
        <div class="workflow-tab-extra" id="workflowExtra-library"></div>

    <section class="panel ip-panel">
        <header class="ip-panel__head">
            <div class="ip-panel__icon ip-panel__icon--green"><?= ip_svg('folder') ?></div>
            <div>
                <h2>Bibliotecă fișiere</h2>
                <p class="ip-panel__desc">Pas 2 · Bifează unul sau mai multe fișiere pentru procesare manuală</p>
            </div>
        </header>
        <div class="ip-panel__body">
        <div class="lib-base-path">Loc unic fișiere: <code id="libBasePath">admin/storage/supplier_feeds/</code></div>
        <div class="lib-toolbar">
            <button type="button" class="btn btn-sm btn-outline" id="selectAllBtn">Bifează toate</button>
            <button type="button" class="btn btn-sm btn-outline" id="selectNoneBtn">Debifează</button>
            <button type="button" class="btn btn-sm btn-danger-outline" id="deleteSelectedBtn">Șterge bifate</button>
            <label style="font-size:13px;display:flex;align-items:center;gap:6px;cursor:pointer;margin:0">
                <input type="checkbox" id="showEmptyFoldersInput">
                Arată foldere goale
            </label>
            <span class="hint" id="selectedCountLabel">0 fișiere selectate</span>
        </div>
        <div style="overflow:auto; max-height: 420px;">
            <table class="file-table">
                <thead>
                <tr>
                    <th style="width:36px"><input type="checkbox" id="selectAllCheckbox" title="Bifează toate"></th>
                    <th>Folder / Fișier</th><th>Mărime</th><th>Modificat</th><th>Status cron</th><th>Acțiuni</th>
                </tr>
                </thead>
                <tbody id="filesBody">
                <tr><td colspan="6">Se încarcă…</td></tr>
                </tbody>
            </table>
        </div>
        <div id="selectedFileBanner" class="selected-file-banner" hidden>
            <strong id="selectedFileLabel">—</strong>
            <ul id="selectedFilesList" class="selected-files-list"></ul>
        </div>
        </div>
    </section>

    <section class="panel ip-panel">
        <header class="ip-panel__head">
            <div class="ip-panel__icon ip-panel__icon--violet"><?= ip_svg('sparkles') ?></div>
            <div>
                <h2>Generează carduri &amp; matching</h2>
                <p class="ip-panel__desc">Pas 3 · Lucrezi doar cu fișierele bifate — fără upload nou</p>
            </div>
        </header>
        <div class="ip-panel__body">
        <div class="ip-options-grid">
            <div class="ip-field">
                <label for="limitInput">Nr. produse valide / fișier</label>
                <input type="number" id="limitInput" value="20" min="1" max="200">
                <p class="hint" style="margin-top:4px;font-size:12px;color:#64748b">Caută în CSV până găsește atâtea produse cu match TecDoc — nu se oprește după N rânduri.</p>
            </div>
            <div class="ip-field">
                <label for="minScoreInput">Scor minim Ollama (%)</label>
                <input type="number" id="minScoreInput" value="70" min="0" max="100">
            </div>
            <div class="ip-field">
                <label for="scrapeLimitInput">Produse / sursă</label>
                <input type="number" id="scrapeLimitInput" value="2" min="1" max="10">
            </div>
            <div class="ip-field">
                <label for="speedModeInput">Viteză scraping</label>
                <select id="speedModeInput">
                    <option value="fast" selected>Rapid — 3 surse paralel</option>
                    <option value="normal">Normal — 3 surse + Ollama</option>
                    <option value="full">Complet — toate sursele (+ Autodoc)</option>
                </select>
            </div>
            <div class="ip-field">
                <label for="imageFilterInput">Filtru imagini</label>
                <select id="imageFilterInput">
                    <option value="all" selected>Toate produsele</option>
                    <option value="only_with_image">Doar cu imagine (Poze / Autopartner)</option>
                </select>
            </div>
            <div class="ip-field">
                <span class="ip-field__label">Mod generare</span>
                <p class="hint" style="margin:4px 0 0"><strong>MySQL TecDoc</strong> — scan match în baza TecDoc + card complet (titlu, specs, compat). Imagine doar dacă există în Poze/Autopartner. Fără date în TecDoc → omis.</p>
                <input type="hidden" id="buildModeInput" value="fast">
            </div>
            <div class="ip-field">
                <label for="buildParallelInput">Fișiere în paralel</label>
                <select id="buildParallelInput">
                    <option value="1" selected>1 (secvențial — cel mai sigur)</option>
                    <option value="2">2 (recomandat server mediu)</option>
                    <option value="3">3</option>
                    <option value="4">4 (server puternic)</option>
                </select>
                <p class="hint" style="margin-top:4px">Cu 5 CSV-uri bifate, folosește 1 fișier paralel — altfel request-ul poate bloca 2–5 minute.</p>
            </div>
        </div>
        <div class="scan-resume-panel">
            <span class="scan-resume-label">Poziție scanare (per fișier bifat)</span>
            <div class="scan-resume-modes">
                <label><input type="radio" name="scanResumeMode" value="continue" checked> Continuă de unde m-am oprit</label>
                <label><input type="radio" name="scanResumeMode" value="manual"> Offset manual de la #</label>
                <input type="number" id="scanManualOffset" min="0" step="1" value="0" disabled style="width:96px;padding:6px 8px;border:1px solid #d1d5db;border-radius:8px">
            </div>
            <p class="hint" id="scanResumeHint" style="margin-top:8px">La următoarea scanare continuă de la ultima poziție salvată pentru fiecare fișier.</p>
        </div>
        <div class="ip-action-row">
            <button type="button" class="btn" id="loadBtn"><?= ip_svg('cards') ?> Generează carduri</button>
            <button type="button" class="btn btn-outline" id="loadFromStartBtn">De la început</button>
            <button type="button" class="btn btn-outline" id="scanBtn"><?= ip_svg('layers') ?> Doar matching (tabel)</button>
        </div>
        <div class="ip-callout ip-callout--info">
            <span class="ip-callout__icon"><?= ip_svg('info') ?></span>
            <div>
                <strong>Carduri vizuale</strong> = MySQL TecDoc + preț (+ imagine dacă există în Poze).
                <strong>Doar matching</strong> = verificare rapidă în tabel.
                <strong>Doar cu imagine</strong> = filtrează cardurile fără poză locală.
            </div>
        </div>
        <div id="loadStatus" class="status info" hidden></div>
        <div id="cardsBuildProgressModal" class="ip-build-progress hidden" aria-hidden="true" role="dialog" aria-labelledby="cardsBuildProgressTitle" aria-modal="true">
            <div class="ip-build-progress__backdrop"></div>
            <div class="ip-build-progress__panel">
                <h3 id="cardsBuildProgressTitle" class="ip-build-progress__title">Generez carduri produse</h3>
                <p id="cardsBuildProgressSubtitle" class="ip-build-progress__subtitle">Scan TecDoc + enrichment MySQL</p>
                <div class="ip-build-progress__stats">
                    <div><strong id="cardsBuildProgressFiles">0 / 0</strong> fișiere</div>
                    <div><strong id="cardsBuildProgressCards">0</strong> carduri</div>
                    <div><strong id="cardsBuildProgressElapsed">0s</strong></div>
                </div>
                <div class="ip-build-progress__bar-wrap">
                    <div id="cardsBuildProgressBar" class="ip-build-progress__bar is-indeterminate"></div>
                </div>
                <p id="cardsBuildProgressDetail" class="ip-build-progress__detail">Pornesc…</p>
                <div id="cardsBuildProgressLog" class="ip-build-progress__log" hidden></div>
                <div class="ip-build-progress__actions">
                    <button type="button" class="btn btn-outline btn-sm" id="cardsBuildProgressCancel">Anulează</button>
                    <button type="button" class="btn btn-sm hidden" id="cardsBuildProgressClose">Închide</button>
                </div>
            </div>
        </div>
        </div>
    </section>

    <section class="panel ip-panel" id="resultsPanel">
        <header class="ip-panel__head">
            <div class="ip-panel__icon"><?= ip_svg('cards') ?></div>
            <div>
                <h2>Rezultate</h2>
                <p class="ip-panel__desc">Carduri vizuale și tabel matching — apar după generare</p>
            </div>
        </header>
        <div class="tabs">
            <button type="button" class="tab active" data-tab="cards">Carduri produse</button>
            <button type="button" class="tab" data-tab="matching">Matching / verificare</button>
        </div>

        <div class="tab-panel active" id="tab-cards">
            <div class="toolbar">
                <strong id="cardsSummary">0 carduri</strong>
                <label style="font-size:13px;display:flex;align-items:center;gap:6px;margin:0">
                    <input type="checkbox" id="cardsSelectAllCheckbox"> Selectează tot (încarcă până la limită)
                </label>
                <button type="button" class="btn btn-green btn-sm" id="stageSelectedBtn" aria-disabled="true">Trimite în coadă import</button>
                <span class="hint" id="stageQueueHint">Bifează carduri pentru trimitere</span>
                <button type="button" class="btn btn-amber btn-sm" id="scrapeAllBtn" disabled>
                    Scraping automat — toate fără imagine
                </button>
            </div>
            <div id="stageQueueStatus" class="status info" hidden role="status" aria-live="polite"></div>
            <div class="progress-wrap" id="batchProgressWrap" hidden>
                <div class="progress-bar" id="batchProgressBar"></div>
            </div>
            <div id="cardsEmpty" class="empty-results">Bifează fișiere în bibliotecă (Pasul 2) și apasă „Generează carduri” sau „Doar matching”.</div>
            <div id="cardsGrid" class="grid"></div>
            <div id="cardsScrollSentinel" class="hint" style="padding:16px;text-align:center" hidden>Se încarcă mai multe produse…</div>
        </div>

        <div class="tab-panel" id="tab-matching">
            <div class="stats-row" id="matchStats"></div>
            <div class="filters" id="matchFilters">
                <button type="button" class="filter-btn active" data-filter="all">Toate</button>
                <button type="button" class="filter-btn" data-filter="exact">Exact</button>
                <button type="button" class="filter-btn" data-filter="probable">Probabil</button>
                <button type="button" class="filter-btn" data-filter="no_match">Fără match</button>
                <button type="button" class="filter-btn" data-filter="conflict">Conflict</button>
            </div>
            <div style="overflow:auto">
                <div id="matchEmpty" class="empty-results">Tabelul de matching apare aici după scanare.</div>
                <table id="matchTable" hidden>
                    <thead>
                    <tr>
                        <th>#</th><th>Fișier</th><th>SKU furnizor</th><th>Nume CSV</th><th>Preț</th>
                        <th>Status</th><th>BD TecDoc</th><th>Metodă</th><th>Date luate din TecDoc</th><th>Note</th>
                    </tr>
                    </thead>
                    <tbody id="matchBody"></tbody>
                </table>
            </div>
        </div>
    </section>
    </div>

    <div class="workflow-tab-panel" id="workflow-tab-showcase" role="tabpanel" aria-labelledby="workflowTabShowcase" hidden>
        <div class="workflow-tab-extra" id="workflowExtra-showcase"></div>

        <section class="panel ip-panel showcase-files-panel" id="showcaseFilesPanel">
            <header class="ip-panel__head">
                <div class="ip-panel__icon ip-panel__icon--green"><?= ip_svg('folder') ?></div>
                <div>
                    <h2>Fișiere pentru vitrină</h2>
                    <p class="ip-panel__desc">Bifează unul sau mai multe CSV — aceeași selecție ca la Bibliotecă (Pas 2)</p>
                </div>
            </header>
            <div class="ip-panel__body">
                <div class="lib-base-path">Loc fișiere: <code id="showcaseLibBasePath">admin/storage/supplier_feeds/</code></div>
                <div class="lib-toolbar">
                    <button type="button" class="btn btn-sm btn-outline" id="showcaseSelectAllBtn">Bifează toate</button>
                    <button type="button" class="btn btn-sm btn-outline" id="showcaseSelectNoneBtn">Debifează</button>
                    <span class="hint" id="showcaseSelectedCountLabel">0 fișiere selectate</span>
                </div>
                <div style="overflow:auto; max-height: 320px;">
                    <table class="file-table" id="showcaseFilesTable">
                        <thead>
                        <tr>
                            <th style="width:36px"><input type="checkbox" id="showcaseSelectAllCheckboxHead" title="Bifează toate"></th>
                            <th>Furnizor</th><th>Fișier</th><th>Mărime</th><th>Modificat</th><th>Status</th>
                        </tr>
                        </thead>
                        <tbody id="showcaseFilesBody">
                        <tr><td colspan="6">Se încarcă…</td></tr>
                        </tbody>
                    </table>
                </div>
                <div id="showcaseSelectedFileBanner" class="selected-file-banner" hidden>
                    <strong id="showcaseSelectedFileLabel">—</strong>
                    <ul id="showcaseSelectedFilesList" class="selected-files-list"></ul>
                </div>
            </div>
        </section>

        <section class="panel ip-panel">
            <header class="ip-panel__head">
                <div class="ip-panel__icon ip-panel__icon--amber"><?= ip_svg('layers') ?></div>
                <div>
                    <h2>Produse-vitrină</h2>
                    <p class="ip-panel__desc">Scanare după tip produs (cuvânt cheie în denumire) — carduri, imagini și scraping</p>
                </div>
            </header>
            <div class="ip-panel__body">
                <div class="showcase-types-manager">
                    <span class="showcase-types-label">Tipuri produse vitrină</span>
                    <div class="showcase-types-tabs tabs" id="showcaseTypesTabs" role="tablist" aria-label="Tipuri produse vitrină"></div>
                    <div class="showcase-types-panels" id="showcaseTypesPanels"></div>
                    <div class="showcase-types-scan-tools">
                        <button type="button" class="btn btn-outline btn-sm" id="showcaseScanOnlyActiveBtn" title="Bifează doar tipul din tab-ul activ">Doar tab activ</button>
                        <button type="button" class="btn btn-outline btn-sm" id="showcaseScanAllTypesBtn">Toate tipurile</button>
                    </div>
                    <p class="hint">Bifează <strong>Include la scan</strong> pe fiecare tip dorit (ex. doar «ulei»). Scanarea folosește tipurile bifate — nu doar tab-ul vizibil. Reguli stricte + Ollama resping piese mecanice irelevante.</p>
                </div>
                <div class="ip-options-grid">
                    <div class="ip-field">
                        <label for="showcaseLimitInput">Nr. produse vitrină / fișier</label>
                        <input type="number" id="showcaseLimitInput" value="20" min="1" max="200">
                    </div>
                    <div class="ip-field">
                        <label for="showcaseMinScoreInput">Scor minim matching tip (%)</label>
                        <input type="number" id="showcaseMinScoreInput" value="72" min="50" max="100">
                    </div>
                    <div class="ip-field">
                        <label for="showcaseParallelInput">Fișiere în paralel</label>
                        <select id="showcaseParallelInput">
                            <option value="1">1</option>
                            <option value="2">2</option>
                            <option value="3" selected>3</option>
                            <option value="4">4</option>
                        </select>
                    </div>
                    <div class="ip-field">
                        <label for="showcaseScrapeLimitInput">Produse / sursă scraping</label>
                        <input type="number" id="showcaseScrapeLimitInput" value="2" min="1" max="10">
                    </div>
                </div>
                <div class="ip-check-row">
                    <label><input type="checkbox" id="showcaseFilterActiveTabInput" checked> Afișează rezultate doar pentru tab-ul activ (ex. doar ulei)</label>
                </div>
                <div class="ip-check-row">
                    <label><input type="checkbox" id="showcaseOllamaSemanticInput" checked> Filtrare Ollama (titlu + descriere + specs + imagine) — doar produse vitrină reale</label>
                    <p class="hint" id="showcaseOllamaHint" style="margin-top:4px;font-size:12px;color:#64748b">Reguli locale găsesc candidați, apoi Ollama primește <strong>referință RAG epiesa.ro</strong> (~5000 exemple: uleiuri, lichide, baterii, becuri) ca să clasifice ca pe catalogul «Produse auto universale». Necesită <code>ollama serve</code>. Recomandat: 1 fișier în paralel. <span id="showcaseRagStatus"></span></p>
                </div>
                <div class="ip-action-row">
                    <button type="button" class="btn" id="showcaseSaveTypesBtn">Salvează tipuri</button>
                    <button type="button" class="btn btn-green" id="showcaseScanBtn"><?= ip_svg('layers') ?> Scanează produse-vitrină</button>
                    <button type="button" class="btn btn-outline" id="showcaseStageBtn" disabled>Trimite selectate în coadă (vitrină)</button>
                </div>
                <div id="showcaseStatus" class="status info" hidden></div>
                <div id="showcaseScanProgress" class="showcase-scan-progress" hidden>
                    <div class="showcase-scan-progress__head">
                        <span class="showcase-scan-progress__pct">
                            <span class="showcase-scan-progress__spinner" id="showcaseScanSpinner" aria-hidden="true"></span>
                            <span id="showcaseScanProgressPct">0%</span>
                        </span>
                        <span class="showcase-scan-progress__file" id="showcaseScanProgressFile">Pregătesc scanarea…</span>
                        <span class="showcase-scan-progress__elapsed" id="showcaseScanProgressElapsed">0 sec</span>
                    </div>
                    <div class="progress-wrap">
                        <div class="progress-bar" id="showcaseScanProgressBar"></div>
                    </div>
                    <ul id="showcaseScanProgressLog" class="showcase-scan-progress__log" aria-live="polite"></ul>
                </div>
                <div class="toolbar" style="margin-top:12px">
                    <strong id="showcaseSummary">0 rezultate</strong>
                    <label style="font-size:13px;display:flex;align-items:center;gap:6px;margin:0">
                        <input type="checkbox" id="showcaseSelectAllCheckbox"> Selectează tot
                    </label>
                    <button type="button" class="btn btn-amber btn-sm" id="showcaseScrapeAllBtn" disabled>
                        Scraping automat — toate fără imagine
                    </button>
                </div>
                <div id="showcaseEmpty" class="empty-results">Bifează fișiere CSV mai sus, configurează tipurile și apasă „Scanează produse-vitrină”.</div>
                <div id="showcaseGrid" class="grid"></div>
            </div>
        </section>
    </div>

    <div class="workflow-tab-panel" id="workflow-tab-log" role="tabpanel" aria-labelledby="workflowTabLog" hidden>
        <div class="workflow-tab-extra" id="workflowExtra-log"></div>
        <section class="panel ip-panel">
            <header class="ip-panel__head">
                <div class="ip-panel__icon ip-panel__icon--amber"><?= ip_svg('info') ?></div>
                <div>
                    <h2>Log scanare fișiere</h2>
                    <p class="ip-panel__desc">Unde s-a oprit fiecare CSV — pentru continuare manuală sau corecții</p>
                </div>
            </header>
            <div class="ip-panel__body">
                <div class="ip-action-row" style="margin-bottom:12px">
                    <button type="button" class="btn btn-outline btn-sm" id="scanLogRefreshBtn">Reîncarcă log</button>
                </div>
                <div class="scan-log-table-wrap">
                    <table class="scan-log-table">
                        <thead>
                            <tr>
                                <th>Fișier</th>
                                <th>Oprit la #</th>
                                <th>Următor start</th>
                                <th>Ultima sesiune</th>
                                <th>Istoric recent</th>
                                <th>Acțiuni</th>
                            </tr>
                        </thead>
                        <tbody id="scanLogBody">
                            <tr><td colspan="6" class="hint" style="padding:18px">Se încarcă logul…</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>

    <div class="workflow-tab-panel" id="workflow-tab-cron" role="tabpanel" aria-labelledby="workflowTabCron" hidden>
        <div class="workflow-tab-extra" id="workflowExtra-cron"></div>

    <section class="panel ip-panel">
        <header class="ip-panel__head">
            <div class="ip-panel__icon ip-panel__icon--amber"><?= ip_svg('clock') ?></div>
            <div>
                <h2>Cron automat</h2>
                <p class="ip-panel__desc">Pas 4 · Task Scheduler procesează fișiere noi fără intervenție manuală</p>
            </div>
        </header>
        <div class="ip-panel__body">
        <div class="ip-callout ip-callout--info">
            <span class="ip-callout__icon"><?= ip_svg('info') ?></span>
            <div id="cronWatcherHint">Programează <code>import/cron_watcher.bat</code> la fiecare <strong>5–15 min</strong> în Task Scheduler. Verificarea respectă <strong>Program sincronizare</strong> din profilul fiecărui furnizor — pagina se actualizează singură.</div>
        </div>
        <div class="cron-box" id="cronStats">
            <div class="cron-stat"><strong id="cronPending">—</strong>Fișiere de procesat</div>
            <div class="cron-stat"><strong id="cronTotal">—</strong>Total fișiere</div>
            <div class="cron-stat"><strong id="cronDueNow">—</strong>Furnizori due acum</div>
            <div class="cron-stat"><strong id="cronActiveSuppliers">—</strong>Automat active</div>
        </div>

        <div class="cron-schedule-panel" id="cronSchedulePanel">
            <div class="cron-schedule-head">
                <h3>Program sincronizare per furnizor</h3>
                <div class="cron-schedule-filters filters" id="cronScheduleFilters">
                    <button type="button" class="filter-btn active" data-cron-filter="all">Toate</button>
                    <button type="button" class="filter-btn" data-cron-filter="active">Automat</button>
                    <button type="button" class="filter-btn" data-cron-filter="due">Due acum</button>
                    <button type="button" class="filter-btn" data-cron-filter="manual">Manual</button>
                </div>
            </div>
            <div class="cron-schedule-table-wrap">
                <table class="cron-schedule-table" id="cronScheduleTable">
                    <thead>
                        <tr>
                            <th>Furnizor</th>
                            <th>Program</th>
                            <th>Mod</th>
                            <th>Status</th>
                            <th>Următoarea scanare</th>
                            <th>Timer</th>
                            <th>Fișiere</th>
                        </tr>
                    </thead>
                    <tbody id="cronScheduleBody">
                        <tr><td colspan="7" class="hint" style="padding:20px;text-align:center">Se încarcă programul furnizorilor…</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div id="cronLastLog" class="hint" style="margin-bottom:12px"></div>

        <div id="cronProgressWrap">
            <div class="cron-progress-head">
                <span id="cronProgressLabel">Inactiv — aștept rulare cron</span>
                <span id="cronProgressPct">0%</span>
            </div>
            <div class="progress-wrap" style="height:14px">
                <div class="progress-bar" id="cronProgressBar" style="width:0%"></div>
            </div>
            <div id="cronPhaseDetail" class="hint" style="margin-top:8px"></div>
            <div id="cronSteps" class="cron-steps" hidden></div>
        </div>

        <div class="panel" style="margin-top:16px;padding:14px 16px;background:#f8f9ff;border:1px solid #e0e4f5;border-radius:10px">
            <strong>Rapoarte scanare (tabel + cartele)</strong>
            <p class="hint" style="margin:6px 0 10px">
                După cron, cartele apar în tabul <strong>„Bibliotecă &amp; matching”</strong> — câte <strong id="cronDisplayLimitHint">—</strong> produse per fișier
                (setarea de mai jos). Cron scanează tot CSV-ul; în pagină vezi doar limita setată.
            </p>
            <div id="reportsList" class="hint">Rapoarte recente…</div>
        </div>

        <div id="aiCronSummaryPanel" class="ai-cron-summary-panel" hidden>
            <div class="ai-cron-summary-header">
                <span>AI — Rezumat cron (Modul 6)</span>
                <button type="button" class="btn btn-sm btn-outline" id="aiCronSummaryRefresh">Regenerează</button>
            </div>
            <div id="aiCronSummaryBody" class="ai-cron-summary-body">
                <p class="hint">Rezumatul apare automat la finalul cronului (AI propune — tu confirmi acțiunile pe carduri).</p>
            </div>
        </div>

        <div class="cron-log-panel">
            <div class="cron-log-header">
                <span><span class="live-dot off" id="cronLiveDot"></span>Log activitate cron (live)</span>
                <span id="cronLogStatus">—</span>
            </div>
            <pre id="cronLogConsole" class="cron-log-console">Se încarcă logul…</pre>
        </div>

        <div class="cron-staging-panel" id="cronStagingPanel">
            <strong>Rezultat migrare coadă import</strong>
            <p class="hint" style="margin:6px 0 10px">
                După scan + match: enrichment complet din <strong>TecDoc MySQL</strong> → vitrină / standard / fără imagine în <code>import_produse</code>.
                Fără match scan sau fără date în MySQL TecDoc → omis.
            </p>
            <div class="cron-staging-stats" id="cronStagingStats">
                <div class="cron-staging-stat"><strong id="cronStScanned">—</strong><span>Scanate</span></div>
                <div class="cron-staging-stat"><strong id="cronStMatched">—</strong><span>Match TecDoc</span></div>
                <div class="cron-staging-stat"><strong id="cronStShowcase">—</strong><span>Vitrină</span></div>
                <div class="cron-staging-stat"><strong id="cronStStandard">—</strong><span>Standard</span></div>
                <div class="cron-staging-stat"><strong id="cronStQueued">—</strong><span>În coadă import</span></div>
                <div class="cron-staging-stat"><strong id="cronStNoImg">—</strong><span>Fără imagine (coadă separată)</span></div>
                <div class="cron-staging-stat"><strong id="cronStNoMatch">—</strong><span>Fără match</span></div>
            </div>
            <p class="hint" id="cronStQueuedHint" style="margin:2px 0 10px"></p>
            <div class="cron-staging-filters filters" id="cronStagingFilters" style="margin-bottom:8px">
                <button type="button" class="filter-btn active" data-staging-filter="all">Toate</button>
                <button type="button" class="filter-btn" data-staging-filter="queued">În coadă</button>
                <button type="button" class="filter-btn" data-staging-filter="candidate">Candidate</button>
                <button type="button" class="filter-btn" data-staging-filter="skipped">Respinse</button>
                <button type="button" class="filter-btn" data-staging-filter="no_image">Fără imagine</button>
            </div>
            <div class="cron-staging-table-wrap">
                <table class="cron-staging-table">
                    <thead>
                        <tr>
                            <th>SKU</th>
                            <th>Produs</th>
                            <th>Furnizor</th>
                            <th>Match</th>
                            <th>Lane</th>
                            <th>Acțiune</th>
                            <th>Detaliu</th>
                        </tr>
                    </thead>
                    <tbody id="cronStagingBody">
                        <tr><td colspan="7" class="hint" style="padding:16px">Rulează cron/test — produsele migrate apar aici.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="ip-cron-actions">
            <div class="ip-field" style="min-width:200px">
                <label for="cronBatchSize">Produse procesate / fișier / rulare cron</label>
                <input type="number" id="cronBatchSize" value="500" min="1" max="5000">
                <p class="hint" style="margin-top:4px">Batch TecDoc + staging; fișiere mari continuă automat la următoarea rulare</p>
            </div>
            <div class="ip-field" style="min-width:200px">
                <label for="cronSample">Produse afișate total (după cron)</label>
                <input type="number" id="cronSample" value="20" min="1" max="200">
                <p class="hint" style="margin-top:4px">Doar afișare în UI — ex: 2 fișiere × 10 = max 20 carduri</p>
            </div>
            <button type="button" class="btn btn-outline" id="cronBtn"><?= ip_svg('play') ?> Rulează cron acum</button>
            <button type="button" class="btn btn-outline" id="cronPauseBtn">Pauză</button>
            <button type="button" class="btn btn-outline" id="cronResumeBtn">Reluare</button>
            <button type="button" class="btn btn-outline" id="demoBtn" title="Aceeași cale ca «Rulează cron acum», dar se oprește după ce găsește 10 produse CU MATCH (agregat pe toate fișierele furnizorilor) — nu după 10 rânduri citite. Poate fi lansat și cât cronul principal rulează.">Testează cron (10 produse)</button>
            <button type="button" class="btn btn-outline" id="cronStopBtn" style="border-color:#c0392b;color:#c0392b"><?= ip_svg('trash') ?> Stop total</button>
        </div>
        <div id="cronStatus" class="status info" hidden style="margin-top:12px"></div>
        </div>
    </section>
    </div>
</div>

<div id="inspectOverlay" class="inspect-overlay" hidden>
    <div class="inspect-modal" role="dialog" aria-labelledby="inspectTitle">
        <div class="inspect-modal-head">
            <h3 id="inspectTitle">Inspectează mapping CSV</h3>
            <button type="button" class="btn btn-sm btn-outline" id="inspectCloseBtn">Închide</button>
        </div>
        <div class="inspect-modal-body">
            <div id="inspectLoading" class="hint">Se încarcă datele din fișier…</div>
            <div id="inspectContent" hidden>
                <div class="inspect-meta" id="inspectMeta"></div>
                <div class="inspect-grid">
                    <div class="inspect-box">
                        <h4>Fișier CSV — celule</h4>
                        <div class="inspect-csv-wrap" id="inspectCsvTable"></div>
                    </div>
                    <div class="inspect-box">
                        <h4>Legături → bază de date</h4>
                        <div class="inspect-links" id="inspectLinks"></div>
                    </div>
                </div>
                <div class="inspect-box" style="margin-bottom:14px">
                    <h4>Bază de date — unde se face matching</h4>
                    <div class="inspect-db-list" id="inspectDbInfo"></div>
                </div>
                <div class="inspect-box" style="margin-bottom:14px">
                    <h4>Normalizare OEM — probleme detectate</h4>
                    <div class="inspect-norm-issues" id="inspectNormIssues"></div>
                </div>
                <div class="inspect-box">
                    <h4>Rezultat matching (preview)</h4>
                    <div class="inspect-preview-wrap" id="inspectPreview"></div>
                </div>
                <div class="inspect-footer">
                    <input type="text" id="inspectOllamaNote" placeholder="Problemă mapping/normalizare? (ex: codul are prefix brand) — Ollama analizează">
                    <button type="button" class="btn btn-sm btn-purple" id="ollamaSuggestBtn">Ollama analiză</button>
                    <button type="button" class="btn btn-sm btn-green" id="saveMappingBtn">Salvează</button>
                    <button type="button" class="btn btn-sm btn-outline" id="clearMappingBtn">Reset</button>
                    <button type="button" class="btn btn-sm btn-outline" id="reloadInspectBtn">Reîncarcă</button>
                    <span id="ollamaSuggestStatus" class="hint"></span>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$besoiuEmbed = (defined('BESOIU_IMPORT_PUBLIC_WRAPPER') && BESOIU_IMPORT_PUBLIC_WRAPPER)
    || (defined('BESOIU_IMPORT_EMBED_INLINE') && BESOIU_IMPORT_EMBED_INLINE);
$besoiuWebBase = defined('BESOIU_IMPORT_WEB_BASE') ? (string) BESOIU_IMPORT_WEB_BASE : '';
$besoiuScraperApi = defined('BESOIU_IMPORT_SCRAPER_API') ? (string) BESOIU_IMPORT_SCRAPER_API : '';
$besoiuPurchaseVatPercent = 21.0;
if (is_file(__DIR__ . '/api/furnizori-bridge.php')) {
    require_once __DIR__ . '/api/_early.php';
    require_once __DIR__ . '/api/furnizori-bridge.php';
    if (function_exists('import_motor_purchase_vat_percent')) {
        $besoiuPurchaseVatPercent = import_motor_purchase_vat_percent();
    }
}
$besoiuImportPaths = $besoiuEmbed ? [
    'webBase' => rtrim($besoiuWebBase, '/') . '/',
    'motorApiProxy' => '/admin/public/api/import_pro_motor_endpoint.php',
    'fetchProductApi' => rtrim($besoiuWebBase, '/') . '/_proxy/fetch-index.php',
    'scraperApi' => $besoiuScraperApi !== '' ? $besoiuScraperApi : rtrim($besoiuWebBase, '/') . '/_proxy/scraper-api.php',
    'saveScrapedImageApi' => rtrim($besoiuWebBase, '/') . '/api/save-scraped-image.php',
    'imageApi' => rtrim($besoiuWebBase, '/') . '/_proxy/product-image.php',
    'scraperPassesJs' => rtrim($besoiuWebBase, '/') . '/_proxy/scraper-passes.js.php',
    'aiRagApi' => '/admin/public/api/ai_rag_endpoint.php',
    'aiImportJs' => '/admin/assets/js/import-pro-ai.js?v=20260721-ipai2',
] : [];
$besoiuImportPaths['purchaseVatPercent'] = $besoiuPurchaseVatPercent;
$besoiuOllamaQualityRaw = strtolower(trim((string) (
    $_ENV['IMPORT_OLLAMA_QUALITY_CHECK']
    ?? $_SERVER['IMPORT_OLLAMA_QUALITY_CHECK']
    ?? getenv('IMPORT_OLLAMA_QUALITY_CHECK')
    ?: ''
)));
if ($besoiuOllamaQualityRaw === '' && is_file(dirname(__DIR__, 2) . '/Config/.env')) {
    $envLine = @file_get_contents(dirname(__DIR__, 2) . '/Config/.env') ?: '';
    if (preg_match('/^\s*IMPORT_OLLAMA_QUALITY_CHECK\s*=\s*([^\r\n#]+)/m', $envLine, $m)) {
        $besoiuOllamaQualityRaw = strtolower(trim($m[1], " \t\"'"));
    }
}
$besoiuImportPaths['ollamaQualityCheck'] = $besoiuOllamaQualityRaw !== ''
    && !in_array($besoiuOllamaQualityRaw, ['0', 'false', 'no', 'off'], true);
?>
<?php if ($besoiuEmbed && is_string($besoiuImportPaths['scraperPassesJs'] ?? null)): ?>
<script src="<?= htmlspecialchars((string) $besoiuImportPaths['scraperPassesJs'], ENT_QUOTES, 'UTF-8') ?>"></script>
<?php else: ?>
<script src="../Scraper/assets/product-search-passes.js"></script>
<?php endif; ?>
<?php if ($besoiuEmbed && is_string($besoiuImportPaths['aiImportJs'] ?? null)): ?>
<script src="<?= htmlspecialchars((string) $besoiuImportPaths['aiImportJs'], ENT_QUOTES, 'UTF-8') ?>"></script>
<?php endif; ?>
<script>
window.__BESOIU_IMPORT_PATHS__ = <?= json_encode($besoiuImportPaths, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
(function () {
    const P = window.__BESOIU_IMPORT_PATHS__ || {};
    function importAbsoluteUrl(url) {
        const s = String(url || '').trim();
        if (!s) return '';
        if (/^https?:\/\//i.test(s)) return s;
        if (s.startsWith('/')) return new URL(s, location.origin).href;
        return new URL(s, location.href).href;
    }
    function importApi(path) {
        const raw = String(path).replace(/^\/?api\//, '');
        const qIndex = raw.indexOf('?');
        const script = qIndex >= 0 ? raw.slice(0, qIndex) : raw;
        const embeddedQuery = qIndex >= 0 ? raw.slice(qIndex + 1) : '';

        const proxy = String(P.motorApiProxy || '').trim();
        if (proxy) {
            const u = new URL(importAbsoluteUrl(proxy));
            u.searchParams.set('script', script);
            if (embeddedQuery) {
                new URLSearchParams(embeddedQuery).forEach((v, k) => u.searchParams.append(k, v));
            }
            return u.toString();
        }
        const base = String(P.webBase || '');
        if (base) {
            const combined = base.replace(/\/?$/, '/') + raw;
            return importAbsoluteUrl(combined);
        }
        return new URL('api/' + raw, location.href).toString();
    }

    function appendImportQuery(url, queryPart) {
        if (!queryPart) return url;
        const extra = String(queryPart).startsWith('?') ? String(queryPart).slice(1) : String(queryPart);
        if (!extra) return url;
        const u = new URL(url, location.origin);
        new URLSearchParams(extra).forEach((v, k) => u.searchParams.append(k, v));
        return u.toString();
    }

    function adminCsrfToken() {
        const el = document.querySelector('[data-csrf]') || document.getElementById('bpa-api-live-arm');
        return el?.getAttribute('data-csrf') || '';
    }
    const BUILD_STORED_API = importApi('api/build-stored.php');
    const ENRICH_TECDOC_API = importApi('api/enrich-tecdoc-cards.php');
    const SCAN_STORED_API = importApi('api/scan-stored.php');
    const UPLOAD_API = importApi('api/upload.php');
    const FILES_API = importApi('api/files.php');
    const DELETE_STORAGE_API = importApi('api/delete-storage.php');
    const CRON_API = importApi('api/scan.php');
    const CRON_START_API = importApi('api/cron-start.php');
    const CRON_STOP_API = importApi('api/cron-stop.php');
    const CRON_CONTROL_API = importApi('api/cron-control.php');
    const CRON_PROGRESS_API = importApi('api/cron-progress.php');
    const CRON_STAGING_API = importApi('api/cron-staging-last.php');
    const STAGE_CARDS_API = importApi('api/stage-cards.php');
    const SCAN_CHECKPOINTS_API = importApi('api/scan-checkpoints.php');
    const SHOWCASE_CONFIG_API = importApi('api/showcase-config.php');
    const SHOWCASE_SCAN_API = importApi('api/showcase-scan.php');
    const REPORTS_API = importApi('api/reports.php');
    const INSPECT_API = importApi('api/inspect-file.php');
    const MAPPING_OLLAMA_API = importApi('api/mapping-ollama.php');
    const SAVE_MAPPING_API = importApi('api/save-mapping.php');
    const INDEX_API = P.fetchProductApi ? importAbsoluteUrl(P.fetchProductApi) : new URL('../fetch%20product/api/index-status.php', location.href).toString();
    const SCRAPER_API = P.scraperApi ? importAbsoluteUrl(P.scraperApi) : new URL('../Scraper/api/index.php', location.href).toString();
    const SAVE_SCRAPED_IMAGE_API = P.saveScrapedImageApi ? importAbsoluteUrl(P.saveScrapedImageApi) : importApi('api/save-scraped-image.php');
    const IMAGE_API = P.imageApi ? importAbsoluteUrl(P.imageApi) : new URL('../Prelucrare%20fisiere%20bovsoft-base/api/product-image.php', location.href).toString();

    const ALL_SCRAPE_SOURCES = [
        { id: 'emag', label: 'eMAG', timeout: 100000 },
        { id: 'automag', label: 'Automag', timeout: 100000 },
        { id: 'niponauto', label: 'Nipon Auto', timeout: 100000 },
        { id: 'autodoc', label: 'Autodoc', timeout: 180000 },
    ];
    const SPEED_PROFILES = {
        fast: { label: 'rapid', sources: ALL_SCRAPE_SOURCES.filter(s => s.id !== 'autodoc'), limitPerSource: 2, maxOllama: 2, maxItems: 6 },
        normal: { label: 'normal', sources: ALL_SCRAPE_SOURCES.filter(s => s.id !== 'autodoc'), limitPerSource: 2, maxOllama: 3, maxItems: 8 },
        full: { label: 'complet', sources: ALL_SCRAPE_SOURCES, limitPerSource: 3, maxOllama: 4, maxItems: 12 },
    };

    let cards = [];
    let showcaseCards = [];
    let showcaseTypes = [];
    let showcaseTypeDefs = [];
    let showcaseTypeScanEnabled = [];
    let showcaseActiveTypeIndex = 0;
    let selectedCardIndexes = new Set();
    let selectedShowcaseIndexes = new Set();
    let cardsPagination = { files: [], fileOffsets: {}, exhaustedFiles: new Set(), hasMore: false, loading: false, pageSize: 9, emptyCompletePages: 0 };
    let cardsScrollObserver = null;
    let matchProducts = [];
    let matchFilter = 'all';
    let batchRunning = false;
    let showcaseBatchRunning = false;
    let showcaseScrapeCancelRequested = false;
    let storedFiles = [];
    let selectedFiles = [];
    let pollTimer = null;
    let cronPollTimer = null;
    let cronPollIntervalMs = 0;
    let cronWasRunning = false;
    let cronPostFinishBusy = false;
    let cronSuppliersSchedule = [];
    let cronScheduleServerTime = null;
    let cronScheduleFilter = 'all';
    let cronCountdownTimer = null;
    let inspectPayload = null;
    let inspectContext = null;
    let cardsJobBusy = false;
    let cardsBuildAbortController = null;
    let cardsBuildElapsedTimer = null;
    let cardsBuildStartedAt = 0;
    let cardsBuildModalVisible = false;
    let cardsBuildCloseTimer = null;

    function openCardsBuildProgressModal(totalFiles) {
        const modal = $('cardsBuildProgressModal');
        if (!modal) return;
        modal.classList.remove('hidden', 'is-complete', 'is-error');
        modal.setAttribute('aria-hidden', 'false');
        $('cardsBuildProgressFiles').textContent = '0 / ' + (totalFiles || 0);
        $('cardsBuildProgressCards').textContent = '0';
        $('cardsBuildProgressElapsed').textContent = '0s';
        $('cardsBuildProgressDetail').textContent = 'Pornesc scanarea TecDoc…';
        const bar = $('cardsBuildProgressBar');
        if (bar) {
            bar.classList.add('is-indeterminate');
            bar.style.width = '';
        }
        const log = $('cardsBuildProgressLog');
        if (log) { log.innerHTML = ''; log.hidden = true; }
        cardsBuildStartedAt = Date.now();
        cardsBuildModalVisible = true;
        if (cardsBuildCloseTimer) {
            clearTimeout(cardsBuildCloseTimer);
            cardsBuildCloseTimer = null;
        }
        const closeBtn = $('cardsBuildProgressClose');
        if (closeBtn) closeBtn.classList.add('hidden');
        if (cardsBuildElapsedTimer) clearInterval(cardsBuildElapsedTimer);
        cardsBuildElapsedTimer = setInterval(() => {
            const el = $('cardsBuildProgressElapsed');
            if (el && cardsBuildStartedAt) {
                el.textContent = Math.max(0, Math.floor((Date.now() - cardsBuildStartedAt) / 1000)) + 's';
            }
        }, 1000);
    }

    function closeCardsBuildProgressModal(success, options = {}) {
        const modal = $('cardsBuildProgressModal');
        if (!modal) return;
        if (cardsBuildElapsedTimer) {
            clearInterval(cardsBuildElapsedTimer);
            cardsBuildElapsedTimer = null;
        }
        if (cardsBuildCloseTimer) {
            clearTimeout(cardsBuildCloseTimer);
            cardsBuildCloseTimer = null;
        }
        const immediate = !!options.immediate;
        const autoCloseMs = options.autoCloseMs ?? (success ? 1200 : 0);
        modal.classList.toggle('is-complete', !!success);
        modal.classList.toggle('is-error', success === false);
        const bar = $('cardsBuildProgressBar');
        if (bar) {
            bar.classList.remove('is-indeterminate');
            bar.style.width = '100%';
        }
        const closeBtn = $('cardsBuildProgressClose');
        if (closeBtn && !immediate) closeBtn.classList.remove('hidden');

        const hideModal = () => {
            modal.classList.add('hidden');
            modal.setAttribute('aria-hidden', 'true');
            modal.classList.remove('is-complete', 'is-error');
            cardsBuildModalVisible = false;
            if (closeBtn) closeBtn.classList.add('hidden');
        };

        if (immediate) {
            hideModal();
            return;
        }
        if (success !== undefined && autoCloseMs > 0) {
            cardsBuildCloseTimer = setTimeout(() => {
                hideModal();
                cardsBuildCloseTimer = null;
            }, autoCloseMs);
        } else if (success === false) {
            // rămâne deschis cu buton Închide
        } else {
            hideModal();
        }
    }

    /** Închide popup-ul imediat după scanarea inițială (înainte de auto-fill / paginare). */
    function finishCardsBuildProgressScan(success, detail, totalFiles, cardCount, fileErrors) {
        const detailEl = $('cardsBuildProgressDetail');
        const filesEl = $('cardsBuildProgressFiles');
        const cardsEl = $('cardsBuildProgressCards');
        const total = Math.max(0, parseInt(totalFiles, 10) || 0);
        if (filesEl && total > 0) filesEl.textContent = total + ' / ' + total + ' fișiere scanate';
        if (cardsEl) cardsEl.textContent = String(cardCount || 0);
        if (detailEl) {
            let msg = detail || (success ? 'Scanare finalizată.' : 'Scanare finalizată cu probleme.');
            if (Array.isArray(fileErrors) && fileErrors.length) {
                msg += ' · ' + fileErrors.length + ' fișier(e) fără carduri'
                    + (fileErrors.length <= 2 ? ': ' + fileErrors.join(', ') : '');
            }
            detailEl.textContent = msg;
        }
        if (Array.isArray(fileErrors) && fileErrors.length) {
            fileErrors.forEach(err => cardsBuildProgressLog(err));
        }
        closeCardsBuildProgressModal(success, { autoCloseMs: success ? 1400 : 0 });
    }

    function cardsBuildProgressLog(message) {
        const log = $('cardsBuildProgressLog');
        if (!log) return;
        log.hidden = false;
        const line = document.createElement('div');
        line.textContent = message;
        log.appendChild(line);
        log.scrollTop = log.scrollHeight;
    }

    function updateCardsBuildProgressUi(ev, totalFiles, cardCount) {
        const filesEl = $('cardsBuildProgressFiles');
        const cardsEl = $('cardsBuildProgressCards');
        const detailEl = $('cardsBuildProgressDetail');
        const bar = $('cardsBuildProgressBar');
        if (ev?.phase === 'start') {
            if (filesEl) filesEl.textContent = (ev.index + 1) + ' / ' + totalFiles + ' (start #' + ev.offset + ')';
            if (detailEl) detailEl.textContent = 'Scan fișier: ' + ev.key + '…';
            if (bar) bar.classList.add('is-indeterminate');
        } else if (ev?.phase === 'done') {
            if (filesEl) filesEl.textContent = (ev.index + 1) + ' / ' + totalFiles + ' gata';
            if (cardsEl) cardsEl.textContent = String(cardCount || 0);
            const pct = totalFiles > 0 ? Math.round(((ev.index + 1) / totalFiles) * 100) : 0;
            if (bar) {
                bar.classList.remove('is-indeterminate');
                bar.style.width = pct + '%';
            }
            if (detailEl) {
                detailEl.textContent = ev.key + ': ' + (ev.cards?.length || 0) + ' carduri'
                    + (ev.res?.hint ? ' — ' + ev.res.hint : '');
            }
            cardsBuildProgressLog((ev.index + 1) + '/' + totalFiles + ' ' + ev.key + ' → ' + (ev.cards?.length || 0) + ' carduri');
        } else if (ev?.phase === 'error') {
            const msg = ev.error?.message || String(ev.error || 'Eroare');
            if (detailEl) detailEl.textContent = ev.key + ' — ' + msg + ' (continui…)';
            cardsBuildProgressLog('Eroare ' + ev.key + ': ' + msg);
        }
    }

    const CRON_PHASE_LABELS = {
        init: 'Inițializare',
        scan: 'Scanare admin/storage/supplier_feeds/',
        start: 'Start fișier',
        parse: 'Parsare CSV',
        match: 'Matching produse',
        staging: 'Formare carduri + coadă import',
        report: 'Salvare raport',
        archive: 'Arhivare',
        scanned: 'Scanare finalizată — migrez în coadă',
        staged: 'Migrare coadă finalizată',
        done: 'Finalizat',
        error: 'Eroare',
    };

    const $ = id => document.getElementById(id);

    function getCardsPageSize() {
        return Math.max(1, Math.min(50, parseInt($('limitInput')?.value, 10) || 9));
    }

    function getShowcaseLimit() {
        return Math.max(1, Math.min(200, parseInt($('showcaseLimitInput')?.value, 10) || 20));
    }

    function getShowcaseMinScore() {
        return Math.max(50, Math.min(100, parseInt($('showcaseMinScoreInput')?.value, 10) || 72));
    }

    function getShowcaseParallelism() {
        return Math.max(1, Math.min(4, parseInt($('showcaseParallelInput')?.value, 10) || 3));
    }

    function getCronBatchSize() {
        return Math.max(1, Math.min(5000, parseInt($('cronBatchSize')?.value, 10) || 500));
    }

    function cardUniqueKey(card) {
        return String(card?.sourceFile || '') + '|' + String(card?.sku || '') + '|' + String(card?.supplier || card?.sourceSupplier || '');
    }

    let scanCheckpointCache = {};

    function getScanResumeMode() {
        const picked = document.querySelector('input[name="scanResumeMode"]:checked');
        return picked?.value || 'continue';
    }

    async function fetchScanCheckpoints(files) {
        let url = SCAN_CHECKPOINTS_API;
        if (files?.length) {
            const keys = files.map(f => encodeURIComponent(f.supplier + '/' + f.filename)).join(',');
            url = appendImportQuery(SCAN_CHECKPOINTS_API, 'files=' + keys);
        }
        const data = await fetchJson(url);
        if (!data.success) throw new Error(data.error || 'Checkpoint indisponibil');
        scanCheckpointCache = data.files || {};
        return scanCheckpointCache;
    }

    function formatScanCheckpointHint(files, checkpoints) {
        if (!files?.length) return 'Bifează fișiere în bibliotecă pentru a vedea poziția salvată.';
        const parts = files.map(f => {
            const key = f.supplier + '/' + f.filename;
            const ck = checkpoints?.[key];
            const next = ck?.offset ?? 0;
            return key + ' → următorul start #' + next;
        });
        return parts.join(' · ');
    }

    async function updateScanResumeHint(files) {
        const el = $('scanResumeHint');
        if (!el) return;
        try {
            const list = files?.length ? files : getSelectedFiles();
            const ck = list.length ? await fetchScanCheckpoints(list) : scanCheckpointCache;
            el.textContent = formatScanCheckpointHint(list, ck);
        } catch {
            el.textContent = 'Checkpoint-uri indisponibile momentan.';
        }
    }

    async function initCardsPagination(files, scanMode) {
        const manualOffset = Math.max(0, parseInt($('scanManualOffset')?.value, 10) || 0);
        let checkpoints = {};
        try {
            checkpoints = await fetchScanCheckpoints(files);
        } catch { /* optional */ }

        cardsPagination = {
            files: (files || []).slice(),
            fileOffsets: {},
            exhaustedFiles: new Set(),
            hasMore: (files || []).length > 0,
            loading: false,
            pageSize: getCardsPageSize(),
            scanMode: scanMode || 'continue',
            emptyCompletePages: 0,
        };

        (files || []).forEach(f => {
            const key = f.supplier + '/' + f.filename;
            if (scanMode === 'restart') {
                cardsPagination.fileOffsets[key] = 0;
            } else if (scanMode === 'manual') {
                cardsPagination.fileOffsets[key] = manualOffset;
            } else {
                cardsPagination.fileOffsets[key] = checkpoints[key]?.offset ?? 0;
            }
        });

        if (scanMode === 'restart' && files?.length) {
            await fetchJson(SCAN_CHECKPOINTS_API, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'reset_all', files }),
            }).catch(() => {});
        } else if (scanMode === 'manual' && files?.length) {
            await Promise.all(files.map(f => fetchJson(SCAN_CHECKPOINTS_API, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'set_offset',
                    supplier: f.supplier,
                    filename: f.filename,
                    offset: manualOffset,
                }),
            }).catch(() => {})));
        }

        await updateScanResumeHint(files);
        return cardsPagination;
    }

    function renderScanLogTable(entries) {
        const body = $('scanLogBody');
        if (!body) return;
        const list = Array.isArray(entries) ? entries : [];
        if (!list.length) {
            body.innerHTML = '<tr><td colspan="6" class="hint" style="padding:18px">Niciun checkpoint — scanează cel puțin o dată un fișier.</td></tr>';
            return;
        }
        body.innerHTML = list.map(entry => {
            const key = entry.key || ((entry.supplier || '') + '/' + (entry.filename || ''));
            const lastEnd = entry.last_end ?? entry.offset ?? 0;
            const next = entry.offset ?? lastEnd;
            const hist = Array.isArray(entry.history) ? entry.history.slice(-3).reverse() : [];
            const histHtml = hist.length
                ? '<ul class="scan-log-history">' + hist.map(h =>
                    '<li>#' + (h.start ?? 0) + '→#' + (h.end ?? 0)
                    + ' (' + (h.count ?? 0) + ' prod., ' + escapeHtml(h.mode || '') + ')</li>'
                ).join('') + '</ul>'
                : '<span class="hint">—</span>';
            const updated = entry.updated_at ? String(entry.updated_at).replace('T', ' ').slice(0, 19) : '—';
            const parts = key.split('/');
            const supplier = parts[0] || '';
            const filename = parts.slice(1).join('/') || '';
            return '<tr>'
                + '<td><strong>' + escapeHtml(key) + '</strong></td>'
                + '<td><span class="scan-log-badge">#' + lastEnd + '</span></td>'
                + '<td><span class="scan-log-badge">#' + next + '</span></td>'
                + '<td>' + escapeHtml(updated) + '<br><span class="hint">' + escapeHtml(entry.last_mode || '') + '</span></td>'
                + '<td>' + histHtml + '</td>'
                + '<td>'
                + '<button type="button" class="btn btn-sm btn-outline scan-log-reset" data-sup="' + escapeHtml(supplier) + '" data-file="' + escapeHtml(filename) + '">Reset</button> '
                + '<button type="button" class="btn btn-sm btn-outline scan-log-set" data-sup="' + escapeHtml(supplier) + '" data-file="' + escapeHtml(filename) + '">Setează #</button>'
                + '</td>'
                + '</tr>';
        }).join('');

        body.querySelectorAll('.scan-log-reset').forEach(btn => {
            btn.addEventListener('click', async () => {
                try {
                    await fetchJson(SCAN_CHECKPOINTS_API, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            action: 'reset',
                            supplier: btn.dataset.sup,
                            filename: btn.dataset.file,
                        }),
                    });
                    await loadScanLog();
                    await updateScanResumeHint(getSelectedFiles());
                    setStatus('Checkpoint resetat: ' + btn.dataset.sup + '/' + btn.dataset.file, 'ok');
                } catch (e) {
                    setStatus(e.message, 'error');
                }
            });
        });
        body.querySelectorAll('.scan-log-set').forEach(btn => {
            btn.addEventListener('click', async () => {
                const val = prompt('Offset manual (nr. produs de la care pornești):', '0');
                if (val == null) return;
                const offset = Math.max(0, parseInt(val, 10) || 0);
                try {
                    await fetchJson(SCAN_CHECKPOINTS_API, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            action: 'set_offset',
                            supplier: btn.dataset.sup,
                            filename: btn.dataset.file,
                            offset,
                        }),
                    });
                    await loadScanLog();
                    await updateScanResumeHint(getSelectedFiles());
                    setStatus('Offset setat la #' + offset + ' pentru ' + btn.dataset.sup + '/' + btn.dataset.file, 'ok');
                } catch (e) {
                    setStatus(e.message, 'error');
                }
            });
        });
    }

    async function loadScanLog() {
        try {
            const data = await fetchJson(SCAN_CHECKPOINTS_API);
            scanCheckpointCache = data.files || {};
            renderScanLogTable(data.entries || []);
        } catch (e) {
            const body = $('scanLogBody');
            if (body) body.innerHTML = '<tr><td colspan="6" class="hint" style="padding:18px">Eroare: ' + escapeHtml(e.message) + '</td></tr>';
        }
    }

    function resetCardsPagination(files) {
        cardsPagination = {
            files: (files || []).slice(),
            fileOffsets: {},
            exhaustedFiles: new Set(),
            hasMore: (files || []).length > 0,
            loading: false,
            pageSize: getCardsPageSize(),
            emptyCompletePages: 0,
        };
        (files || []).forEach(f => {
            cardsPagination.fileOffsets[f.supplier + '/' + f.filename] = 0;
        });
    }

    function updateStageSelectedBtn() {
        const btn = $('stageSelectedBtn');
        const hint = $('stageQueueHint');
        const count = selectedCardIndexes.size;
        if (btn) {
            const inactive = count === 0;
            btn.classList.toggle('is-disabled', inactive);
            btn.setAttribute('aria-disabled', inactive ? 'true' : 'false');
            btn.disabled = false;
        }
        if (hint) {
            hint.textContent = count > 0
                ? (count + ' carduri selectate — gata de trimitere')
                : 'Bifează carduri pentru trimitere';
        }
    }

    function updateShowcaseStageBtn() {
        const btn = $('showcaseStageBtn');
        if (btn) btn.disabled = selectedShowcaseIndexes.size === 0;
    }

    /** Loturi mici — serverul acceptă max ~25/request; evităm TimeOut pe volum mare. */
    const STAGE_CARDS_CHUNK_SIZE = 20;
    const STAGE_CARDS_CHUNK_TIMEOUT_MS = 120000;

    function slimCardForStaging(card) {
        if (!card || typeof card !== 'object') return card;
        const slim = { ...card };
        // Păstrăm câmpurile minime obligatorii pentru conversie în coadă.
        if (!slim.supplierSku && slim.sku) slim.supplierSku = slim.sku;
        if (!slim.title && slim.name) slim.title = slim.name;
        if (!slim.matchedName && slim.name) slim.matchedName = slim.name;
        // Descrierea Base.html trebuie completă (nested ul) — NU tăia la 4000 (rupe HTML-ul).
        // Limita reală e pe server (ImportCardStaging ~12000).
        if (typeof slim.description === 'string' && slim.description.length > 12000) {
            slim.description = slim.description.slice(0, 12000) + '…';
        }
        if (typeof slim.compatText === 'string' && slim.compatText.length > 8000) {
            slim.compatText = slim.compatText.slice(0, 8000);
        }
        delete slim.tecdocAudit;
        // păstrăm sourceRows pentru regenerare Base; rawRows rămân prea voluminoase
        delete slim.rawRows;
        return slim;
    }

    async function stageCardsToQueue(cardsToStage, importLane = 'standard', options = {}) {
        if (!cardsToStage?.length) return;
        const total = cardsToStage.length;
        const onChunk = typeof options.onChunk === 'function' ? options.onChunk : null;
        const aggregated = {
            success: true,
            received: 0,
            converted: 0,
            skipped_staging: 0,
            queued: 0,
            updated_existing: 0,
            stage_errors: 0,
            rejected: [],
            with_image: 0,
            without_price: 0,
            queued_showcase: 0,
            queued_no_image: 0,
            import_lane: importLane,
            chunks: 0,
            message: '',
        };

        for (let offset = 0; offset < total; offset += STAGE_CARDS_CHUNK_SIZE) {
            const chunk = cardsToStage.slice(offset, offset + STAGE_CARDS_CHUNK_SIZE).map(slimCardForStaging);
            const done = Math.min(offset + chunk.length, total);
            setStageQueueStatus(
                'Se trimit în coadă ' + done + ' / ' + total + ' produse…',
                'info'
            );
            if (onChunk) onChunk(done, total);
            await yieldToUi();

            const data = await fetchJson(STAGE_CARDS_API, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ cards: chunk, import_lane: importLane }),
                timeoutMs: STAGE_CARDS_CHUNK_TIMEOUT_MS,
            });
            if (!data.success) {
                throw new Error(data.error || data.message || 'Staging eșuat (lot ' + done + '/' + total + ')');
            }

            aggregated.received += Number(data.received ?? chunk.length);
            aggregated.converted += Number(data.converted ?? 0);
            aggregated.skipped_staging += Number(data.skipped_staging ?? 0);
            aggregated.queued += Number(data.queued ?? 0);
            aggregated.updated_existing = (aggregated.updated_existing || 0) + Number(data.updated_existing ?? 0);
            aggregated.stage_errors = (aggregated.stage_errors || 0) + Number(data.stage_errors ?? 0);
            aggregated.rejected = (aggregated.rejected || []).concat(data.rejected || []);
            aggregated.with_image += Number(data.with_image ?? 0);
            aggregated.without_price += Number(data.without_price ?? 0);
            aggregated.queued_showcase += Number(data.queued_showcase ?? 0);
            aggregated.queued_no_image += Number(data.queued_no_image ?? 0);
            aggregated.chunks += 1;
        }

        aggregated.message = aggregated.queued + ' produse trimise în coada de import'
            + (aggregated.chunks > 1 ? ' (' + aggregated.chunks + ' loturi)' : '')
            + (aggregated.queued_showcase > 0 ? ' · ' + aggregated.queued_showcase + ' → Produse vitrină' : '')
            + (aggregated.queued_no_image > 0 ? ' · ' + aggregated.queued_no_image + ' → Produse fără imagine' : '')
            + '.';
        return aggregated;
    }

    /** Debifează selecția fără rebuild greu al grilei (evită freeze UI). */
    function clearProductCardSelectionUi() {
        selectedCardIndexes = new Set();
        const allCb = $('cardsSelectAllCheckbox');
        if (allCb) {
            allCb.checked = false;
            allCb.disabled = false;
        }
        document.querySelectorAll('#cardsGrid .card-select-cb:checked').forEach(cb => {
            cb.checked = false;
        });
        updateStageSelectedBtn();
        const hintSel = selectedCardIndexes.size;
        if ($('cardsSummary') && cards.length) {
            // actualizează doar contorul „selectate” din summary dacă e prezent
            const base = ($('cardsSummary').textContent || '').replace(/\s*·\s*\d+\s+selectate/i, '');
            $('cardsSummary').textContent = hintSel > 0 ? (base + ' · ' + hintSel + ' selectate') : base;
        }
    }

    function yieldToUi() {
        return new Promise(resolve => setTimeout(resolve, 0));
    }

    function stopCardsInfiniteScroll() {
        if (cardsScrollObserver) {
            cardsScrollObserver.disconnect();
            cardsScrollObserver = null;
        }
    }

    function noteCardsPaginationProgress(batch, added) {
        const rawBatchSize = (batch?.cards || []).length;
        if (rawBatchSize === 0) {
            cardsPagination.hasMore = false;
            return;
        }
        if (added > 0) {
            cardsPagination.emptyCompletePages = 0;
            return;
        }
        cardsPagination.emptyCompletePages = (cardsPagination.emptyCompletePages || 0) + 1;
        if (cardsPagination.emptyCompletePages >= 6) {
            cardsPagination.hasMore = false;
        }
    }

    function setupCardsInfiniteScroll() {
        const sentinel = $('cardsScrollSentinel');
        if (!sentinel) return;
        if (cardsScrollObserver) cardsScrollObserver.disconnect();
        cardsScrollObserver = new IntersectionObserver(entries => {
            if (entries.some(e => e.isIntersecting)) {
                void loadMoreCards();
            }
        }, { root: null, rootMargin: '120px', threshold: 0.01 });
        cardsScrollObserver.observe(sentinel);
    }

    function mergeUniqueCompleteCards(baseCards, extraCards) {
        const out = Array.isArray(baseCards) ? baseCards.slice() : [];
        const seen = new Set(out.map(cardUniqueKey));
        const accept = onlyWithImageSelected() ? isCompleteTecdocCard : isDisplayableBuildCard;
        (extraCards || []).filter(accept).forEach(c => {
            const k = cardUniqueKey(c);
            if (!seen.has(k)) {
                seen.add(k);
                out.push(c);
            }
        });
        return out;
    }

    function mergeCompleteCardsFromBuildPage(batch) {
        const existing = new Set(cards.map(cardUniqueKey));
        const fresh = (batch.cards || []).filter(c => !existing.has(cardUniqueKey(c)));
        const displayable = fresh.filter(isDisplayableBuildCard);
        if (displayable.length) {
            cards = cards.concat(displayable);
        }
        cardsPagination.fileOffsets = batch.fileOffsets || cardsPagination.fileOffsets;
        cardsPagination.hasMore = !!batch.hasMore;
        return displayable.length;
    }

    async function fillCardsToTargetLimit(fromCron, absoluteTarget = null) {
        const perFile = getDisplayLimit(fromCron);
        const fileCount = Math.max(1, cardsPagination.files?.length || 1);
        const target = absoluteTarget != null
            ? Math.max(1, Number(absoluteTarget) || 1)
            : (perFile * fileCount);
        if (!cardsPagination.files?.length) return cards.length;
        let guard = 0;
        while (cards.length < target && cardsPagination.hasMore && guard++ < 50) {
            setStatus('Completez lista… ' + cards.length + '/' + target + ' carduri', 'info');
            cardsPagination.loading = true;
            try {
                const batch = await fetchBuildCardsPage(cardsPagination);
                const batchSize = (batch.cards || []).length;
                const added = mergeCompleteCardsFromBuildPage(batch);
                noteCardsPaginationProgress(batch, added);
                if (batchSize === 0 || !cardsPagination.hasMore || (!added && cardsPagination.emptyCompletePages >= 3)) break;
            } catch (e) {
                console.warn('fillCardsToTargetLimit', e);
                break;
            } finally {
                cardsPagination.loading = false;
            }
        }
        return cards.length;
    }

    function selectAllLoadedProductCards() {
        selectedCardIndexes = new Set(cards.map((_, i) => i));
    }

    /**
     * Selectează toate cardurile vizibile imediat, apoi încearcă să încarce până la limită
     * (cu timeout — nu blochează UI dacă API e lent/oprit).
     */
    async function applyCardsSelectAll(wantAll) {
        const allCb = $('cardsSelectAllCheckbox');
        if (!wantAll) {
            clearProductCardSelectionUi();
            setStageQueueStatus('Selecție ștearsă.', 'info');
            return;
        }

        if (!cards.length) {
            if (allCb) allCb.checked = false;
            setStageQueueStatus('Nu există carduri de selectat. Generează carduri mai întâi.', 'warn');
            showImportToast('Nu există carduri de selectat.', 'warn');
            return;
        }

        // Feedback imediat — doar bifăm în DOM, fără rebuild greu al grilei
        selectAllLoadedProductCards();
        if (allCb) allCb.checked = true;
        document.querySelectorAll('#cardsGrid .card-select-cb').forEach(cb => {
            cb.checked = true;
        });
        updateStageSelectedBtn();
        setStageQueueStatus(selectedCardIndexes.size + ' carduri selectate…', 'info');

        const needsMore = cardsPagination.hasMore && cards.length < getManualDisplayLimit();
        if (!needsMore) {
            setStageQueueStatus(selectedCardIndexes.size + ' carduri selectate — gata de trimitere', 'ok');
            return;
        }

        try {
            if (allCb) allCb.disabled = true;
            await yieldToUi();
            const raced = await Promise.race([
                fillCardsToTargetLimit(false, getManualDisplayLimit()).then(() => 'ok'),
                new Promise(resolve => setTimeout(() => resolve('timeout'), 8000)),
            ]);
            selectAllLoadedProductCards();
            // Rebuild doar dacă s-au adăugat carduri noi
            await yieldToUi();
            renderCards();
            if (allCb) {
                allCb.checked = selectedCardIndexes.size === cards.length && cards.length > 0;
            }
            if (raced === 'timeout') {
                setStageQueueStatus(
                    selectedCardIndexes.size + ' carduri selectate — gata de trimitere'
                        + ' (mai sunt produse în CSV; se încarcă la scroll sau la următoarea generare)',
                    'ok'
                );
                showImportToast(
                    selectedCardIndexes.size + ' selectate. Apasă «Trimite în coadă import» — nu e nevoie să aștepți restul.',
                    'ok'
                );
            } else {
                setStageQueueStatus(selectedCardIndexes.size + ' carduri selectate — gata de trimitere', 'ok');
            }
        } catch (e) {
            selectAllLoadedProductCards();
            document.querySelectorAll('#cardsGrid .card-select-cb').forEach(cb => {
                cb.checked = true;
            });
            if (allCb) allCb.checked = selectedCardIndexes.size > 0;
            setStageQueueStatus(
                'Selectate ' + selectedCardIndexes.size + ' carduri încărcate. (' + (e.message || 'încărcare eșuată') + ')',
                'warn'
            );
        } finally {
            if (allCb) allCb.disabled = false;
            updateStageSelectedBtn();
        }
    }

    async function loadMoreCards() {
        if (!cardsPagination.hasMore || cardsPagination.loading || !cardsPagination.files?.length) return;
        cardsPagination.loading = true;
        const sentinel = $('cardsScrollSentinel');
        if (sentinel) { sentinel.hidden = false; sentinel.textContent = 'Se încarcă mai multe produse…'; }
        try {
            const batch = await fetchBuildCardsPage(cardsPagination);
            const added = mergeCompleteCardsFromBuildPage(batch);
            noteCardsPaginationProgress(batch, added);
            if (added) renderCards();
        } catch (e) {
            setStatus('Eroare scroll: ' + e.message, 'warn');
        } finally {
            cardsPagination.loading = false;
            if (sentinel) {
                sentinel.hidden = !cardsPagination.hasMore;
                sentinel.textContent = cardsPagination.hasMore ? 'Scroll pentru mai multe…' : 'Sfârșit listă';
            }
            if (!cardsPagination.hasMore) {
                stopCardsInfiniteScroll();
            }
        }
    }

    async function fetchBuildCardsPage(pagination, onFileProgress) {
        const perFileLimit = pagination.pageSize || getCardsPageSize();
        const merged = [];
        let hasMore = false;
        const fileOffsets = { ...(pagination.fileOffsets || {}) };
        const exhaustedFiles = pagination.exhaustedFiles instanceof Set
            ? pagination.exhaustedFiles
            : new Set();
        const scanMode = pagination.scanMode || getScanResumeMode();
        const fileList = pagination.files || [];
        const parallel = getBuildParallelism();

        await runTasksWithConcurrency(fileList, parallel, async (f, i) => {
            const key = f.supplier + '/' + f.filename;
            if (exhaustedFiles.has(key)) {
                if (typeof onFileProgress === 'function') {
                    onFileProgress({
                        phase: 'done',
                        index: i,
                        total: fileList.length,
                        file: f,
                        key,
                        cards: [],
                        skipped: true,
                        parallel,
                    });
                }
                return { list: [], fileHasMore: false, key, skipped: true };
            }
            const offset = fileOffsets[key] || 0;
            if (typeof onFileProgress === 'function') {
                onFileProgress({ phase: 'start', index: i, total: fileList.length, file: f, key, offset, parallel });
            }
            try {
                const res = await fetchBuildCardsForFile(f.supplier, f.filename, perFileLimit, {
                    offset,
                    limit: perFileLimit,
                    scanMode,
                    signal: pagination.buildSignal,
                });
                const list = (res?.cards || []).slice(0, perFileLimit);
                list.forEach(c => {
                    c.sourceFile = key;
                    c.sourceSupplier = f.supplier;
                });
                const nextOffset = res?.next_offset ?? (offset + list.length);
                fileOffsets[key] = nextOffset;
                const fileHasMore = !!res?.has_more;
                const noCardsThisPage = list.length === 0;
                const shouldExhaust = !fileHasMore && noCardsThisPage;
                if (shouldExhaust) {
                    exhaustedFiles.add(key);
                }
                if (typeof onFileProgress === 'function') {
                    onFileProgress({
                        phase: 'done',
                        index: i,
                        total: fileList.length,
                        file: f,
                        key,
                        cards: list,
                        fileOffsets: { ...fileOffsets },
                        hasMore: fileHasMore && !shouldExhaust,
                        res,
                        parallel,
                        exhausted: shouldExhaust,
                    });
                }
                return { list, fileHasMore: fileHasMore && !shouldExhaust, key };
            } catch (e) {
                exhaustedFiles.add(key);
                if (typeof onFileProgress === 'function') {
                    onFileProgress({ phase: 'error', index: i, total: fileList.length, file: f, key, error: e, parallel });
                }
                return { list: [], fileHasMore: false, key, error: e };
            }
        }).then(fileResults => {
            fileResults.forEach(entry => {
                if (entry?.skipped) return;
                if (!entry?.list?.length) return;
                entry.list.forEach(c => merged.push(c));
                if (entry.fileHasMore) hasMore = true;
            });
            fileResults.forEach(entry => {
                if (entry?.fileHasMore) hasMore = true;
            });
        });

        pagination.fileOffsets = fileOffsets;
        pagination.exhaustedFiles = exhaustedFiles;
        const activeFiles = fileList.filter(f => !exhaustedFiles.has(f.supplier + '/' + f.filename));
        if (!activeFiles.length) hasMore = false;

        return { cards: merged, hasMore, fileOffsets, exhaustedFiles };
    }

    function getCronDisplayLimit() {
        return Math.max(1, Math.min(200, parseInt($('cronSample')?.value, 10) || 20));
    }

    function getPurchaseVatPercent() {
        return Math.max(0, Math.min(100, Number(P.purchaseVatPercent) || 21));
    }

    function formatRoMoney(value) {
        const n = Number(value);
        if (!Number.isFinite(n)) return '—';
        return n.toLocaleString('ro-RO', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
    }

    /** Prețurile vin calculate strict din PHP (profil furnizor) — JS doar afișează. */
    function cardPriceFromProduct(card, product) {
        const out = { ...(card || {}) };
        const p = product || {};
        if (p.price_csv != null) out.priceCsv = Number(p.price_csv);
        if (p.price_purchase_net != null) {
            out.pricePurchaseNet = Number(p.price_purchase_net);
            out.priceNet = Number(p.price_purchase_net);
        } else if (p.price != null && Number.isFinite(Number(p.price))) {
            out.pricePurchaseNet = Number(p.price);
            out.priceNet = Number(p.price);
        }
        if (p.price_purchase_vat != null) out.pricePurchaseVat = p.price_purchase_vat;
        if (p.feed_markup_percent != null) out.feedMarkupPercent = Number(p.feed_markup_percent);
        if (p.vat_percent != null) out.purchaseVatPercent = Number(p.vat_percent);
        if (p.vat_rule) out.supplierVatRule = String(p.vat_rule);
        if (p.vat_extra_applied != null) out.purchaseVatExtraApplied = !!p.vat_extra_applied;
        return out;
    }

    function renderCardPriceBlock(card) {
        const purchaseVat = card.pricePurchaseVat;
        const purchaseNet = card.pricePurchaseNet ?? card.priceNet;
        const priceCsv = card.priceCsv;
        const vatPct = card.purchaseVatPercent ?? getPurchaseVatPercent();
        const feedPct = Number(card.feedMarkupPercent ?? 0);
        const vatRule = String(card.supplierVatRule || 'net_plus_tva');
        const vatExtra = card.purchaseVatExtraApplied !== false && vatRule !== 'price_final';

        if (purchaseNet == null && (purchaseVat == null || purchaseVat === '—')) {
            return '<div class="price">—</div>';
        }

        // Aici afișăm doar achiziția — prețul final magazin se pune după Adaos comercial.
        const mainNet = purchaseNet != null ? formatRoMoney(purchaseNet) : '—';
        let sub = '';
        if (priceCsv != null) {
            sub = 'CSV: ' + formatRoMoney(priceCsv) + ' RON';
            if (feedPct > 0 && purchaseNet != null && Math.abs(Number(priceCsv) - Number(purchaseNet)) > 0.009) {
                sub += ' (+' + feedPct + '% compensator → net)';
            }
        }
        if (purchaseVat != null && purchaseVat !== '—') {
            sub += (sub ? ' · ' : '') + 'cu TVA: ' + String(purchaseVat) + ' RON';
            if (vatRule === 'price_final') {
                sub += ' (preț furnizor)';
            } else if (vatExtra) {
                sub += ' (+TVA ' + vatPct + '%)';
            }
        }

        return '<div class="price"><span class="price-main">Achiziție: ' + escapeHtml(String(mainNet)) + ' RON</span>'
            + (sub ? '<small>' + escapeHtml(sub) + '</small>' : '')
            + '<small class="price-hint">Preț final → după ce alegi produsele și aplici Adaos comercial</small></div>';
    }

    function getManualDisplayLimit() {
        return Math.max(1, Math.min(200, parseInt($('limitInput')?.value, 10) || 20));
    }

    function getImageFilterMode() {
        return $('imageFilterInput')?.value === 'only_with_image' ? 'only_with_image' : 'all';
    }

    function onlyWithImageSelected() {
        return getImageFilterMode() === 'only_with_image';
    }

    function getBuildMode() {
        return 'fast';
    }

    function getBuildParallelism() {
        return Math.max(1, Math.min(4, parseInt($('buildParallelInput')?.value, 10) || 1));
    }

    async function runTasksWithConcurrency(items, concurrency, worker) {
        const list = Array.isArray(items) ? items : [];
        if (!list.length) return [];
        const limit = Math.max(1, Math.min(concurrency, list.length));
        const results = new Array(list.length);
        let nextIndex = 0;

        async function runner() {
            while (true) {
                const i = nextIndex++;
                if (i >= list.length) break;
                results[i] = await worker(list[i], i);
            }
        }

        await Promise.all(Array.from({ length: limit }, () => runner()));
        return results;
    }

    function getBuildLimit() {
        const base = getManualDisplayLimit();
        if (!onlyWithImageSelected()) return base;
        return Math.min(200, Math.max(base * 8, 80));
    }

    function cardHasDisplayableImage(card) {
        if (!card) return false;
        if (card.scrapedImagePath) return true;
        if (card.scrapedImageUrl) return true;
        const url = getProductImageUrl(card);
        return card.hasImage === true && !!url;
    }

    function probeImageUrl(url) {
        return new Promise(resolve => {
            if (!url) {
                resolve(false);
                return;
            }
            const img = new Image();
            const timer = setTimeout(() => {
                img.onload = null;
                img.onerror = null;
                resolve(false);
            }, 8000);
            img.onload = () => {
                clearTimeout(timer);
                resolve(true);
            };
            img.onerror = () => {
                clearTimeout(timer);
                resolve(false);
            };
            img.src = url;
        });
    }

    async function filterCardsWithLoadableImages(list) {
        const items = list || [];
        if (!items.length) return [];
        const results = await Promise.all(items.map(async (card) => {
            if (!cardHasDisplayableImage(card)) return false;
            if (card.scrapedImageUrl) return true;
            return probeImageUrl(getProductImageUrl(card));
        }));
        return items.filter((card, i) => results[i]);
    }

    function applyVerifiedImageCards(list) {
        return (list || []).filter(cardHasDisplayableImage);
    }

    async function prepareCardsForDisplay(list, fromCron) {
        let out = (list || []).slice();
        if (onlyWithImageSelected()) {
            out = applyVerifiedImageCards(out);
            if (out.length) {
                setStatus('Verific încărcarea imaginilor…', 'info');
                out = await filterCardsWithLoadableImages(out);
            }
        }
        return capCardsToDisplayLimit(out, fromCron);
    }

    function updateCardsSummary() {
        let withImg = 0, withoutImg = 0;
        cards.forEach(c => {
            if (cardHasDisplayableImage(c)) withImg++;
            else withoutImg++;
        });
        $('cardsSummary').textContent = cards.length + ' carduri · ' + withImg + ' cu imagine · ' + withoutImg + ' fără';
        $('scrapeAllBtn').disabled = withoutImg === 0 || batchRunning;
    }

    function getDisplayLimit(fromCron) {
        return fromCron ? getCronDisplayLimit() : getManualDisplayLimit();
    }

    function capCardsToDisplayLimit(list, fromCron) {
        return (list || []).slice(0, getDisplayLimit(fromCron));
    }

    function resetScanUiState() {
        cards = [];
        selectedCardIndexes = new Set();
        updateStageSelectedBtn();
        cardsPagination = { files: [], fileOffsets: {}, hasMore: false, loading: false, pageSize: getCardsPageSize() };
        matchProducts = [];
        renderCards();
        const matchEmpty = $('matchEmpty');
        const matchTable = $('matchTable');
        if (matchEmpty) matchEmpty.hidden = false;
        if (matchTable) matchTable.hidden = true;
        if ($('matchBody')) $('matchBody').innerHTML = '';
        if ($('matchStats')) $('matchStats').innerHTML = '';
    }

    function clearSelectionAfterScan() {
        selectAllFiles(false);
    }

    function filterCardsByImage(list, limit) {
        const target = limit ?? getManualDisplayLimit();
        const pool = onlyWithImageSelected()
            ? (list || []).filter(cardHasDisplayableImage)
            : applyVerifiedImageCards(list);
        return pool.slice(0, target);
    }

    function updateCronLimitHint() {
        const el = $('cronDisplayLimitHint');
        if (el) el.textContent = String(getCronDisplayLimit());
    }

    function statusLabel(s) {
        const map = { new: 'Nou', processed: 'Procesat', modified: 'Modificat' };
        return map[s] || s;
    }

    function formatFileSize(f) {
        if (f.size_mb >= 0.01) return f.size_mb + ' MB';
        return Math.max(1, Math.round((f.size || 0) / 1024)) + ' KB';
    }

    function formatFileCardsCell(f) {
        const total = f.cards_total ?? f.report_summary?.total;
        if (total == null || total === 0) {
            if (f.report_id) {
                return '<span class="hint" title="' + escapeHtml(f.report_id) + '">0</span>';
            }
            return '<span class="hint">—</span>';
        }
        const exact = f.report_summary?.exact ?? 0;
        const probable = f.report_summary?.probable ?? 0;
        let detail = exact + ' exact';
        if (probable) detail += ', ' + probable + ' prob.';
        return '<strong>' + total + '</strong><br><small>' + escapeHtml(detail) + '</small>';
    }

    function fileKey(supplier, filename) {
        return String(supplier) + '::' + String(filename);
    }

    function isFileSelected(supplier, filename) {
        const k = fileKey(supplier, filename);
        return selectedFiles.some(f => fileKey(f.supplier, f.filename) === k);
    }

    function getSelectedFiles() {
        return selectedFiles.slice();
    }

    function toggleFileSelection(supplier, filename, forceOn) {
        const k = fileKey(supplier, filename);
        const exists = selectedFiles.findIndex(f => fileKey(f.supplier, f.filename) === k);
        if (forceOn === true && exists < 0) {
            selectedFiles.push({ supplier, filename });
        } else if (forceOn === false && exists >= 0) {
            selectedFiles.splice(exists, 1);
        } else if (forceOn === undefined) {
            if (exists >= 0) selectedFiles.splice(exists, 1);
            else selectedFiles.push({ supplier, filename });
        }
        syncFileTableSelection();
        updateSelectedBanner();
    }

    function setSelectedFiles(list) {
        selectedFiles = (list || []).map(f => ({ supplier: f.supplier, filename: f.filename }));
        syncFileTableSelection();
        updateSelectedBanner();
        void updateScanResumeHint(selectedFiles);
    }

    function selectAllFiles(on) {
        if (on) {
            selectedFiles = storedFiles.map(f => ({ supplier: f.supplier, filename: f.filename }));
        } else {
            selectedFiles = [];
        }
        syncFileTableSelection();
        updateSelectedBanner();
        void updateScanResumeHint(selectedFiles);
    }

    let storedFolders = [];

    function syncFileTableSelection() {
        document.querySelectorAll('.file-table tr[data-filename]').forEach(tr => {
            const on = isFileSelected(tr.dataset.supplier, tr.dataset.filename);
            tr.classList.toggle('checked', on);
            const cb = tr.querySelector('.file-check');
            if (cb) cb.checked = on;
        });
        const allOn = storedFiles.length > 0 && selectedFiles.length === storedFiles.length;
        const countText = selectedFiles.length + ' fișier(e) selectate';
        ['selectAllCheckbox', 'uploadSelectAllCheckbox', 'showcaseSelectAllCheckboxHead'].forEach(id => {
            const headCb = $(id);
            if (headCb) {
                headCb.checked = allOn;
                headCb.indeterminate = selectedFiles.length > 0 && !allOn;
            }
        });
        ['selectedCountLabel', 'uploadSelectedCountLabel', 'showcaseSelectedCountLabel'].forEach(id => {
            const el = $(id);
            if (el) el.textContent = countText;
        });
        updateHeroKpis();
        updateShowcaseSelectedBanner();
    }

    function updateShowcaseSelectedBanner() {
        const banner = $('showcaseSelectedFileBanner');
        if (!banner) return;
        if (!selectedFiles.length) {
            banner.hidden = true;
            return;
        }
        banner.hidden = false;
        const label = $('showcaseSelectedFileLabel');
        if (label) {
            label.textContent = selectedFiles.length === 1
                ? '1 fișier selectat pentru scanare vitrină:'
                : selectedFiles.length + ' fișiere selectate pentru scanare vitrină:';
        }
        const list = $('showcaseSelectedFilesList');
        if (list) {
            list.innerHTML = selectedFiles.map(f =>
                '<li>' + escapeHtml(f.supplier) + ' / ' + escapeHtml(f.filename) + '</li>'
            ).join('');
        }
    }

    function updateSelectedBanner() {
        const banner = $('selectedFileBanner');
        if (!selectedFiles.length) {
            banner.hidden = true;
            return;
        }
        banner.hidden = false;
        $('selectedFileLabel').textContent = selectedFiles.length === 1
            ? '1 fișier pentru procesare manuală:'
            : selectedFiles.length + ' fișiere pentru procesare manuală:';
        $('selectedFilesList').innerHTML = selectedFiles.map(f =>
            '<li>' + escapeHtml(f.supplier) + ' / ' + escapeHtml(f.filename) + '</li>'
        ).join('');
    }

    function bindFileTableActions(body, options) {
        if (!body) return;
        const opts = Object.assign({
            inspect: true,
            cards: true,
            match: true,
            deleteFile: true,
            deleteFolder: true,
            rowSelect: true,
        }, options || {});

        body.querySelectorAll('tr[data-filename]').forEach(tr => {
            const sup = tr.dataset.supplier;
            const file = tr.dataset.filename;
            if (opts.rowSelect) {
                tr.querySelector('.file-check')?.addEventListener('click', ev => {
                    ev.stopPropagation();
                    toggleFileSelection(sup, file, ev.target.checked);
                });
                tr.addEventListener('click', ev => {
                    if (ev.target.closest('button') || ev.target.closest('input')) return;
                    toggleFileSelection(sup, file);
                });
            }
        });
        if (opts.inspect) {
            body.querySelectorAll('.act-inspect').forEach(btn => {
                btn.addEventListener('click', ev => {
                    ev.stopPropagation();
                    openInspectModal(btn.dataset.sup, btn.dataset.file);
                });
            });
        }
        if (opts.cards) {
            body.querySelectorAll('.act-cards').forEach(btn => {
                btn.addEventListener('click', ev => {
                    ev.stopPropagation();
                    setSelectedFiles([{ supplier: btn.dataset.sup, filename: btn.dataset.file }]);
                    switchWorkflowTab('library');
                    loadCards();
                });
            });
        }
        if (opts.match) {
            body.querySelectorAll('.act-match').forEach(btn => {
                btn.addEventListener('click', ev => {
                    ev.stopPropagation();
                    setSelectedFiles([{ supplier: btn.dataset.sup, filename: btn.dataset.file }]);
                    switchWorkflowTab('library');
                    scanOnly();
                });
            });
        }
        if (opts.deleteFile) {
            body.querySelectorAll('.act-del-file').forEach(btn => {
                btn.addEventListener('click', ev => {
                    ev.stopPropagation();
                    deleteFiles([{ supplier: btn.dataset.sup, filename: btn.dataset.file }]);
                });
            });
        }
        if (opts.deleteFolder) {
            body.querySelectorAll('.act-del-folder').forEach(btn => {
                btn.addEventListener('click', ev => {
                    ev.stopPropagation();
                    deleteSupplierFolder(btn.dataset.sup);
                });
            });
        }
        body.querySelectorAll('.act-open-library').forEach(btn => {
            btn.addEventListener('click', ev => {
                ev.stopPropagation();
                setSelectedFiles([{ supplier: btn.dataset.sup, filename: btn.dataset.file }]);
                switchWorkflowTab('library');
            });
        });
    }

    let registeredSuppliers = [];

    function renderRegisteredSuppliers(list) {
        registeredSuppliers = Array.isArray(list) ? list : [];
        const sel = $('uploadSupplier');
        const noHint = $('uploadNoSuppliersHint');
        const uploadBtn = $('uploadBtn');
        if (!sel) return;

        const prev = sel.value;
        sel.innerHTML = '<option value="">— selectează furnizor —</option>'
            + registeredSuppliers.map(s =>
                '<option value="' + escapeHtml(s.slug) + '">'
                + escapeHtml(s.name) + ' (' + escapeHtml(s.code) + ')'
                + (s.has_column_config ? '' : ' · fără mapare CSV')
                + '</option>'
            ).join('');

        if (prev && registeredSuppliers.some(s => s.slug === prev)) {
            sel.value = prev;
        }

        const has = registeredSuppliers.length > 0;
        if (noHint) noHint.hidden = has;
        if (uploadBtn) uploadBtn.disabled = !has;
        updateUploadSupplierMeta();
    }

    function updateUploadSupplierMeta() {
        const meta = $('uploadSupplierMeta');
        const sel = $('uploadSupplier');
        if (!meta || !sel) return;
        const s = registeredSuppliers.find(x => x.slug === sel.value);
        if (!s) {
            meta.textContent = registeredSuppliers.length
                ? 'Selectează furnizorul — adaos feed și reguli scan vin din profilul Furnizori.'
                : '';
            return;
        }
        meta.textContent = 'Folder: ' + (s.feed_folder || '—')
            + ' · Adaos feed: ' + (s.markup_percent ?? 0) + '%'
            + (s.has_column_config ? '' : ' · Atenție: lipsește mapare coloane în suppliers.json');
    }

    function renderUploadFilesTable(files) {
        const body = $('uploadFilesBody');
        if (!body) return;
        const list = (files || []).slice().sort((a, b) => String(b.modified_at).localeCompare(String(a.modified_at)));
        if (!list.length) {
            body.innerHTML = '<tr><td colspan="8">Niciun fișier încărcat. Alege furnizor + CSV și apasă „Încarcă pe server”.</td></tr>';
            return;
        }
        body.innerHTML = list.map(f => {
            const on = isFileSelected(f.supplier, f.filename);
            return '<tr class="file-row ' + (on ? 'checked' : '') + '" data-supplier="' + escapeHtml(f.supplier) +
                '" data-filename="' + escapeHtml(f.filename) + '">' +
                '<td><input type="checkbox" class="file-check"' + (on ? ' checked' : '') + '></td>' +
                '<td><code>' + escapeHtml(f.supplier) + '</code></td>' +
                '<td>' + escapeHtml(f.filename) + '</td>' +
                '<td>' + formatFileSize(f) + '</td>' +
                '<td>' + escapeHtml(f.modified_at) + '</td>' +
                '<td><span class="status-pill ' + escapeHtml(f.status) + '">' + statusLabel(f.status) + '</span></td>' +
                '<td>' + formatFileCardsCell(f) + '</td>' +
                '<td><button type="button" class="btn btn-sm btn-outline act-open-library" data-sup="' + escapeHtml(f.supplier) +
                '" data-file="' + escapeHtml(f.filename) + '">Pas 2</button> ' +
                '<button type="button" class="btn btn-sm btn-danger-outline act-del-file" data-sup="' + escapeHtml(f.supplier) +
                '" data-file="' + escapeHtml(f.filename) + '">Șterge</button></td></tr>';
        }).join('');
        bindFileTableActions(body, { inspect: false, cards: false, match: false, deleteFolder: false });
        syncFileTableSelection();
        updateHeroKpis();
    }

    function renderShowcaseFilesTable(files) {
        const body = $('showcaseFilesBody');
        if (!body) return;
        const list = (files || []).slice().sort((a, b) => String(b.modified_at).localeCompare(String(a.modified_at)));
        if (!list.length) {
            body.innerHTML = '<tr><td colspan="6">Niciun fișier încărcat. Tab «Upload» → alege furnizor + CSV.</td></tr>';
            updateShowcaseSelectedBanner();
            return;
        }
        body.innerHTML = list.map(f => {
            const on = isFileSelected(f.supplier, f.filename);
            return '<tr class="file-row ' + (on ? 'checked' : '') + '" data-supplier="' + escapeHtml(f.supplier) +
                '" data-filename="' + escapeHtml(f.filename) + '">' +
                '<td><input type="checkbox" class="file-check"' + (on ? ' checked' : '') + '></td>' +
                '<td><code>' + escapeHtml(f.supplier) + '</code></td>' +
                '<td>' + escapeHtml(f.filename) + '</td>' +
                '<td>' + formatFileSize(f) + '</td>' +
                '<td>' + escapeHtml(f.modified_at) + '</td>' +
                '<td><span class="status-pill ' + escapeHtml(f.status) + '">' + statusLabel(f.status) + '</span></td></tr>';
        }).join('');
        bindFileTableActions(body, { inspect: false, cards: false, match: false, deleteFile: false, deleteFolder: false });
        syncFileTableSelection();
    }

    function renderFilesTable(files, folders) {
        storedFiles = files || [];
        storedFolders = folders || [];
        const body = $('filesBody');
        if (!storedFiles.length) {
            body.innerHTML = '<tr><td colspan="6">Niciun fișier încărcat. Pasul 1 → alege furnizor + CSV.</td></tr>';
            selectedFiles = [];
            updateSelectedBanner();
            $('selectedCountLabel').textContent = '0 fișiere selectate';
            return;
        }
        selectedFiles = selectedFiles.filter(sf =>
            storedFiles.some(f => f.supplier === sf.supplier && f.filename === sf.filename)
        );

        const groups = storedFolders.filter(f => f.file_count > 0);
        if (!groups.length) {
            body.innerHTML = '<tr><td colspan="6">Niciun fișier încărcat. Pasul 1 → alege furnizor + CSV.</td></tr>';
            syncFileTableSelection();
            return;
        }
        let html = '';
        groups.forEach(folder => {
            html += '<tr class="folder-row" data-folder-supplier="' + escapeHtml(folder.supplier) + '">' +
                '<td></td>' +
                '<td colspan="4"><code>' + escapeHtml(folder.folder) + '</code> — ' + folder.file_count + ' fișier(e)</td>' +
                '<td><button type="button" class="btn btn-sm btn-danger-outline act-del-folder" data-sup="' + escapeHtml(folder.supplier) + '">Șterge folder</button></td></tr>';
            (folder.files || []).forEach(f => {
                const on = isFileSelected(f.supplier, f.filename);
                html += '<tr class="file-row ' + (on ? 'checked' : '') + '" data-supplier="' + escapeHtml(f.supplier) +
                    '" data-filename="' + escapeHtml(f.filename) + '">' +
                    '<td><input type="checkbox" class="file-check"' + (on ? ' checked' : '') + '></td>' +
                    '<td style="padding-left:28px">' + escapeHtml(f.filename) + '</td>' +
                    '<td>' + formatFileSize(f) + '</td>' +
                    '<td>' + escapeHtml(f.modified_at) + '</td>' +
                    '<td><span class="status-pill ' + escapeHtml(f.status) + '">' + statusLabel(f.status) + '</span></td>' +
                    '<td><button type="button" class="btn btn-sm btn-purple act-inspect" data-sup="' + escapeHtml(f.supplier) +
                    '" data-file="' + escapeHtml(f.filename) + '">Legături</button> ' +
                    '<button type="button" class="btn btn-sm btn-outline act-cards" data-sup="' + escapeHtml(f.supplier) +
                    '" data-file="' + escapeHtml(f.filename) + '">Carduri</button> ' +
                    '<button type="button" class="btn btn-sm btn-outline act-match" data-sup="' + escapeHtml(f.supplier) +
                    '" data-file="' + escapeHtml(f.filename) + '">Match</button> ' +
                    '<button type="button" class="btn btn-sm btn-danger-outline act-del-file" data-sup="' + escapeHtml(f.supplier) +
                    '" data-file="' + escapeHtml(f.filename) + '">Șterge</button></td></tr>';
            });
        });
        body.innerHTML = html;

        bindFileTableActions(body, { inspect: true, cards: true, match: true, deleteFile: true, deleteFolder: true });
        syncFileTableSelection();
        updateHeroKpis();
    }

    async function deleteFiles(list) {
        if (!list?.length) return;
        const label = list.length === 1
            ? list[0].supplier + '/' + list[0].filename
            : list.length + ' fișiere';
        if (!confirm('Ștergi definitiv ' + label + '?\nNu se poate anula.')) return;
        const fd = new FormData();
        if (list.length === 1) {
            fd.append('action', 'file');
            fd.append('supplier', list[0].supplier);
            fd.append('filename', list[0].filename);
        } else {
            fd.append('action', 'selected');
            fd.append('files', JSON.stringify(list));
        }
        try {
            const data = await fetchJson(DELETE_STORAGE_API, { method: 'POST', body: fd });
            if (!data.success) throw new Error(data.error || 'Ștergere eșuată');
            selectedFiles = selectedFiles.filter(sf =>
                !list.some(d => d.supplier === sf.supplier && d.filename === sf.filename)
            );
            await loadFilesLibrary();
            let msg = 'Șters definitiv: ' + label + ' (folder furnizor';
            if (Array.isArray(data.staging_purged) && data.staging_purged.length) {
                msg += ' + staging import';
            }
            msg += ')';
            setStatus(msg, 'ok');
        } catch (e) {
            setStatus('Eroare ștergere: ' + e.message, 'error');
        }
    }

    async function deleteSupplierFolder(supplier) {
        const folder = storedFolders.find(f => f.supplier === supplier);
        const path = folder?.folder || ('admin/storage/supplier_feeds/' + supplier + '/');
        const count = folder?.file_count ?? '?';
        if (!confirm('Ștergi TOATE fișierele din ' + path + '?\n(' + count + ' fișiere)\nNu se poate anula.')) return;
        const fd = new FormData();
        fd.append('action', 'supplier');
        fd.append('supplier', supplier);
        try {
            const data = await fetchJson(DELETE_STORAGE_API, { method: 'POST', body: fd });
            if (!data.success) throw new Error(data.error || 'Ștergere folder eșuată');
            selectedFiles = selectedFiles.filter(sf => sf.supplier !== supplier);
            await loadFilesLibrary();
            setStatus('Folder golit: ' + path + ' (' + (data.deleted?.length ?? 0) + ' fișiere)', 'ok');
        } catch (e) {
            setStatus('Eroare: ' + e.message, 'error');
        }
    }

    function deleteSelectedFiles() {
        const list = getSelectedFiles();
        if (!list.length) {
            setStatus('Bifează fișierele de șters.', 'warn');
            return;
        }
        deleteFiles(list);
    }

    function updateHeroKpis() {
        const filesEl = $('ipKpiFiles');
        const pendingEl = $('ipKpiPending');
        const selEl = $('ipKpiSelected');
        const cronEl = $('ipKpiCron');
        if (filesEl) filesEl.textContent = String(storedFiles?.length ?? 0);
        if (pendingEl) pendingEl.textContent = $('cronPending')?.textContent || '—';
        if (selEl) selEl.textContent = String(selectedFiles?.length ?? 0);
        if (cronEl) {
            const label = $('cronProgressLabel')?.textContent || '';
            if (label.includes('Inactiv') || label.includes('aștept')) cronEl.textContent = 'Inactiv';
            else if (label.includes('%') || $('cronProgressBar')?.style.width !== '0%') cronEl.textContent = 'Activ';
            else cronEl.textContent = 'Gata';
        }
    }

    function formatCountdown(seconds) {
        if (seconds == null || Number.isNaN(seconds)) return '—';
        if (seconds <= 0) return '00:00';
        const s = Math.max(0, Math.floor(seconds));
        const h = Math.floor(s / 3600);
        const m = Math.floor((s % 3600) / 60);
        const sec = s % 60;
        if (h > 0) {
            return String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0') + ':' + String(sec).padStart(2, '0');
        }
        return String(m).padStart(2, '0') + ':' + String(sec).padStart(2, '0');
    }

    function cronModeBadge(mode) {
        const labels = { interval: 'Interval', daily: 'Zilnic', window: 'Fereastră', manual: 'Manual' };
        const cls = 'cron-badge mode-' + (mode || 'interval');
        return '<span class="' + cls + '">' + escapeHtml(labels[mode] || mode || '—') + '</span>';
    }

    function cronStatusBadge(status) {
        const labels = {
            due: 'Scanează acum',
            waiting: 'Fișiere noi',
            idle: 'Așteaptă',
            manual: 'Doar manual',
            paused: 'Cron pe pauză',
            stopped: 'Cron oprit',
        };
        const cls = 'cron-badge status-' + (status || 'idle');
        return '<span class="' + cls + '">' + escapeHtml(labels[status] || status || '—') + '</span>';
    }

    function getCronScheduleRows() {
        return (cronSuppliersSchedule || []).filter(row => {
            if (cronScheduleFilter === 'active') {
                return row.auto_enabled && row.schedule_mode !== 'manual';
            }
            if (cronScheduleFilter === 'due') return !!row.due_now;
            if (cronScheduleFilter === 'manual') {
                return !row.auto_enabled || row.schedule_mode === 'manual';
            }
            return true;
        });
    }

    function renderCronSuppliersSchedule(schedule) {
        if (!schedule) return;
        cronSuppliersSchedule = schedule.suppliers || [];
        cronScheduleServerTime = schedule.server_time ? Date.parse(schedule.server_time) : Date.now();

        const hint = $('cronWatcherHint');
        if (hint && schedule.watcher_hint) {
            hint.innerHTML = String(schedule.watcher_hint).replace(
                'cron_watcher.bat',
                '<code>import/cron_watcher.bat</code>'
            );
        }

        const dueEl = $('cronDueNow');
        const activeEl = $('cronActiveSuppliers');
        if (dueEl) dueEl.textContent = String(schedule.due_count ?? '—');
        if (activeEl) activeEl.textContent = String(schedule.active_count ?? '—');

        const tbody = $('cronScheduleBody');
        if (!tbody) return;

        const rows = getCronScheduleRows();
        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="7" class="hint" style="padding:20px;text-align:center">Niciun furnizor pentru filtrul selectat.</td></tr>';
            return;
        }

        tbody.innerHTML = rows.map(row => {
            const rowClass = row.status === 'due' ? ' class="row-due"' : '';
            const profileLink = row.profile_url
                ? '<a class="cron-schedule-link" href="' + escapeHtml(row.profile_url) + '" target="_blank" rel="noopener">Program</a>'
                : '';
            const filesClass = (row.pending_files || 0) > 0 ? 'cron-files-pill has-pending' : 'cron-files-pill';
            const filesLabel = (row.pending_files || 0) + ' / ' + (row.total_files || 0);
            let countdownClass = 'cron-countdown';
            let countdownText = '—';
            let countdownAttr = '';
            if (row.status === 'paused' || row.status === 'stopped') {
                countdownClass += ' paused';
                countdownText = row.status === 'stopped' ? 'STOP' : 'PAUZĂ';
            } else if (!row.auto_enabled || row.schedule_mode === 'manual') {
                countdownClass += ' manual';
                countdownText = 'MANUAL';
            } else if (row.due_now) {
                countdownClass += ' due-now';
                countdownText = 'ACUM';
            } else if (row.next_run_at) {
                countdownAttr = ' data-next-run="' + escapeHtml(row.next_run_at) + '"';
                countdownText = formatCountdown(row.next_run_seconds);
            }
            const lastRun = row.last_run_at
                ? ('Ultima: ' + escapeHtml(String(row.last_run_at).slice(0, 16).replace('T', ' ')))
                : 'Niciodată';

            return '<tr' + rowClass + '>' +
                '<td><span class="cron-supplier-name">' + escapeHtml(row.name || row.slug) + '</span>' +
                '<span class="cron-supplier-slug">' + escapeHtml(row.slug) + '</span></td>' +
                '<td><strong>' + escapeHtml(row.schedule_label || '—') + '</strong><br>' +
                profileLink + '</td>' +
                '<td>' + cronModeBadge(row.schedule_mode) + '</td>' +
                '<td>' + cronStatusBadge(row.status) + '</td>' +
                '<td><span style="font-size:12px">' + escapeHtml(row.next_run_label || '—') + '</span><br>' +
                '<span class="hint">' + lastRun + '</span></td>' +
                '<td><span class="' + countdownClass + '"' + countdownAttr + '>' + countdownText + '</span></td>' +
                '<td><span class="' + filesClass + '">' + filesLabel + '</span></td>' +
                '</tr>';
        }).join('');
    }

    function tickCronCountdowns() {
        if (!cronScheduleServerTime || !cronSuppliersSchedule.length) return;
        const elapsed = Math.floor((Date.now() - cronScheduleServerTime) / 1000);
        document.querySelectorAll('.cron-countdown[data-next-run]').forEach(el => {
            const nextRun = Date.parse(el.dataset.nextRun || '');
            if (Number.isNaN(nextRun)) return;
            const serverNow = cronScheduleServerTime + elapsed * 1000;
            const left = Math.max(0, Math.floor((nextRun - serverNow) / 1000));
            el.textContent = left <= 0 ? 'ACUM' : formatCountdown(left);
            if (left <= 0) el.classList.add('due-now');
        });
    }

    function startCronCountdownTicker() {
        if (cronCountdownTimer) clearInterval(cronCountdownTimer);
        cronCountdownTimer = setInterval(tickCronCountdowns, 1000);
    }

    function updateCronPanel(cron) {
        if (!cron) return;
        $('cronPending').textContent = String(cron.pending_files ?? '—');
        $('cronTotal').textContent = String(cron.total_files ?? '—');
        if (cron.suppliers_schedule) renderCronSuppliersSchedule(cron.suppliers_schedule);
        $('cronLastLog').textContent = cron.last_log_line
            ? 'Ultimul log: ' + cron.last_log_line
            : 'Niciun log astăzi — programează cron_watcher.bat în Task Scheduler (la 5–15 min).';
        updateHeroKpis();
    }

    let cronStagingItems = [];
    let cronStagingFilter = 'all';

    function cronStagingRowMatchesFilter(row, filter) {
        const action = String(row.action || '');
        const reason = String(row.reason || '');
        switch (filter) {
            case 'queued': return action === 'queued';
            case 'candidate': return action === 'candidate';
            case 'skipped': return action === 'skipped';
            case 'no_image': return reason.toLowerCase().includes('imagine');
            default: return true;
        }
    }

    function renderCronStagingTable() {
        const tbody = $('cronStagingBody');
        if (!tbody) return;
        const items = cronStagingItems.filter(row => cronStagingRowMatchesFilter(row, cronStagingFilter));
        if (!items.length) {
            tbody.innerHTML = '<tr><td colspan="7" class="hint" style="padding:16px">'
                + (cronStagingItems.length ? 'Niciun produs pentru filtrul selectat.' : 'Niciun produs procesat încă.')
                + '</td></tr>';
            return;
        }
        tbody.innerHTML = items.slice(0, 200).map(row => {
            const action = String(row.action || '');
            const cls = action === 'queued' ? 'row-queued'
                : (action === 'skipped' ? 'row-skipped' : (action === 'candidate' ? 'row-candidate' : ''));
            const actionLabel = action === 'queued' ? 'În coadă'
                : (action === 'skipped' ? 'Respins'
                    : (action === 'candidate' ? 'Candidat' : action));
            const noImage = String(row.reason || '').toLowerCase().includes('imagine');
            return '<tr class="' + cls + '">'
                + '<td>' + escapeHtml(String(row.sku || '—')) + '</td>'
                + '<td>' + (noImage ? '<span title="Fără imagine" style="margin-right:4px">🖼️</span>' : '')
                    + escapeHtml(String(row.name || '—')) + '</td>'
                + '<td>' + escapeHtml(String(row.supplier || '—')) + '</td>'
                + '<td>' + escapeHtml(String(row.match || '—')) + '</td>'
                + '<td>' + escapeHtml(String(row.lane || '—')) + '</td>'
                + '<td><strong>' + escapeHtml(actionLabel) + '</strong></td>'
                + '<td>' + escapeHtml(String(row.reason || row.image || '—')) + '</td>'
                + '</tr>';
        }).join('');
    }

    function setCronStagingFilter(filter) {
        cronStagingFilter = filter;
        document.querySelectorAll('#cronStagingFilters .filter-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.stagingFilter === filter);
        });
        renderCronStagingTable();
    }

    function renderCronStagingJournal(journal) {
        const stats = journal?.stats || {};
        const set = (id, v) => { const el = $(id); if (el) el.textContent = String(v ?? '—'); };
        set('cronStScanned', stats.scanned ?? 0);
        set('cronStMatched', stats.matched ?? 0);
        set('cronStShowcase', stats.showcase_candidates ?? 0);
        set('cronStStandard', stats.standard_candidates ?? 0);
        set('cronStQueued', stats.queued ?? 0);
        set('cronStNoImg', stats.skipped_no_image ?? 0);
        set('cronStNoMatch', stats.skipped_no_match ?? 0);

        const noImage = Number(stats.skipped_no_image ?? 0);
        const incomplete = Number(stats.skipped_incomplete ?? 0);
        const matched = Number(stats.matched ?? 0);
        const queued = Number(stats.queued ?? 0);
        const hint = $('cronStQueuedHint');
        if (hint) {
            const parts = [];
            // Produsele fără imagine NU mai sunt aruncate — sunt migrate în coada dedicată
            // „Produse fără imagine” (admin/importreview?lane=no_image), vizibil pentru completare manuală.
            if (noImage > 0) {
                parts.push('ℹ️ ' + noImage + ' produs(e) fără imagine → coada „Produse fără imagine” (admin/importreview).');
            }
            if (incomplete > 0) {
                parts.push('⚠️ ' + incomplete + ' produs(e) cu card incomplet — nu au ajuns în coadă.');
            }
            hint.textContent = parts.join(' ');
        }

        cronStagingItems = (Array.isArray(journal?.items) ? journal.items : []).slice().reverse();
        renderCronStagingTable();
    }

    async function loadCronStagingResults(scrollIntoView) {
        try {
            const data = await fetchJson(CRON_STAGING_API);
            if (data.success && data.journal) {
                renderCronStagingJournal(data.journal);
                if (scrollIntoView) {
                    $('cronStagingPanel')?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }
                return data.journal;
            }
        } catch { /* ignore */ }
        return null;
    }

    /**
     * Sumarul final trebuie construit din agregatul REAL pe produse
     * (CronStagingJournal — scanned/matched/queued/skipped_*), nu din
     * `progress.summary` care contorizează doar FIȘIERE (câte CSV-uri
     * au fost procesate cu succes). Cele două obiecte măsoară lucruri
     * diferite; folosirea greșită a lui `summary` pentru mesajul final
     * producea „0 procesate, 0 sărite, 0 erori” chiar și când matching-ul
     * găsea produse reale (vezi log-ul serverului).
     */
    function buildCronFinishedMessage(filesSummary, stats) {
        const sum = filesSummary || {};
        const st = stats || {};
        const filesTotal = Number(sum.pending ?? 0);
        const filesDone = Array.isArray(sum.results) ? sum.results.length : filesTotal;
        const errorsCount = Array.isArray(sum.errors) ? sum.errors.length : 0;

        const scanned = Number(st.scanned ?? 0);
        const matched = Number(st.matched ?? 0);
        const queued = Number(st.queued ?? 0);
        const noMatch = Number(st.skipped_no_match ?? 0);
        // Produsele fără imagine NU mai sunt blocate/pierdute — merg în coada dedicată
        // „Produse fără imagine” (import_lane=no_image), vizibilă în admin/importreview.
        const noImage = Number(st.skipped_no_image ?? 0);
        const incomplete = Number(st.skipped_incomplete ?? 0);

        const partial = Number(sum.partial ?? 0);
        const parts = [
            filesDone + '/' + filesTotal + ' fișiere',
            scanned + ' produse scanate',
            matched + ' matched',
            queued + ' trimise în coadă' + (noImage > 0 ? ' (din care ' + noImage + ' fără imagine)' : ''),
            noMatch + ' fără match',
        ];
        if (incomplete > 0) {
            parts.push(incomplete + ' respinse — card incomplet');
        }
        parts.push(errorsCount + ' erori');
        if (partial > 0) {
            parts.push(partial + ' fișier(e) parțial(e) — continuă la următoarea rulare');
        }
        return 'Cron terminat: ' + parts.join(' · ');
    }

    /**
     * Golește instant tot ce e vizual legat de cron (log, bară progres, pași, panou
     * staging) în momentul în care userul apasă un buton — înainte ca primul poll
     * să ajungă cu date reale, ca să nu mai apară pentru o clipă rezultate vechi
     * (ex. "5 produse scanate, 0 rezultate" rămase de la rularea anterioară).
     */
    function resetCronLiveUiState() {
        const bar = $('cronProgressBar');
        if (bar) bar.style.width = '0%';
        const pct = $('cronProgressPct');
        if (pct) pct.textContent = '0%';
        const label = $('cronProgressLabel');
        if (label) label.textContent = 'Pornire…';
        const detail = $('cronPhaseDetail');
        if (detail) detail.textContent = '';

        const stepsEl = $('cronSteps');
        if (stepsEl) { stepsEl.innerHTML = ''; stepsEl.hidden = true; }

        const log = $('cronLogConsole');
        if (log) log.textContent = 'Se pornește rularea nouă…';

        const dot = $('cronLiveDot');
        if (dot) dot.classList.remove('off');
        const logStatus = $('cronLogStatus');
        if (logStatus) logStatus.textContent = 'LIVE — pornește…';

        renderCronStagingJournal({ stats: {}, items: [] });
    }

    let cronNotifyPermissionAsked = false;

    /** Cerem permisiunea de notificări browser în gestul de click pe un buton cron (nu la load). */
    function requestCronNotifyPermission() {
        if (cronNotifyPermissionAsked) return;
        cronNotifyPermissionAsked = true;
        try {
            if (window.Notification && Notification.permission === 'default') {
                Notification.requestPermission();
            }
        } catch { /* ignore — browser fără suport */ }
    }

    function ensureCronToastContainer() {
        let box = document.getElementById('cronToastContainer');
        if (!box) {
            box = document.createElement('div');
            box.id = 'cronToastContainer';
            box.className = 'cron-toast-container';
            document.body.appendChild(box);
        }
        return box;
    }

    function showCronToast(message, type) {
        const box = ensureCronToastContainer();
        const item = document.createElement('div');
        item.className = 'cron-toast cron-toast-' + (type || 'info');
        item.textContent = message;
        box.appendChild(item);
        requestAnimationFrame(() => item.classList.add('is-visible'));
        setTimeout(() => {
            item.classList.remove('is-visible');
            setTimeout(() => item.remove(), 300);
        }, 7000);
    }

    function showImportToast(message, type) {
        showCronToast(message, type);
    }

    function setStageQueueStatus(msg, type) {
        const el = $('stageQueueStatus');
        if (!el) {
            setStatus(msg, type);
            return;
        }
        el.hidden = false;
        el.className = 'status ' + (type || 'info');
        el.textContent = msg;
        try {
            el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        } catch { /* ignore */ }
        setStatus(msg, type);
    }

    /** Notificare la finalul unei rulări de cron — browser Notification (dacă e permisă) + toast intern. */
    function notifyCronFinished(message, type) {
        showCronToast(message, type);
        try {
            if (window.Notification && Notification.permission === 'granted') {
                new Notification('Cron automat — Import PRO', {
                    body: message,
                    tag: 'import-pro-cron',
                });
            }
        } catch { /* ignore — browser fără suport */ }
        if (type === 'ok' && window.BesoiuImportAi) {
            window.BesoiuImportAi.onCronFinished(message);
        }
    }

    function renderCronProgress(progress, logLines) {
        if (!progress) return;
        const status = progress.status || 'idle';
        const pct = Math.max(0, Math.min(100, Number(progress.percent) || 0));
        const phase = CRON_PHASE_LABELS[progress.phase] || progress.phase || '—';

        $('cronProgressBar').style.width = pct + '%';
        $('cronProgressPct').textContent = pct + '%';

        let label = progress.message || 'Inactiv';
        if (status === 'running' && progress.current_file) {
            label = '[' + (progress.file_index || '?') + '/' + (progress.total_files || '?') + '] '
                + (progress.current_supplier || '') + '/' + (progress.current_file || '') + ' — ' + phase;
        } else if (status === 'done') {
            label = progress.message || 'Cron finalizat';
        }
        $('cronProgressLabel').textContent = label;

        const detail = [];
        if (progress.started_at) detail.push('Start: ' + progress.started_at);
        if (progress.finished_at) detail.push('Sfârșit: ' + progress.finished_at);
        if (progress.summary?.processed != null) detail.push('Fișiere procesate: ' + progress.summary.processed + '/' + (progress.summary.pending ?? '?'));
        $('cronPhaseDetail').textContent = detail.join(' · ');

        const stepsEl = $('cronSteps');
        const steps = progress.steps || [];
        if (steps.length) {
            stepsEl.hidden = false;
            stepsEl.innerHTML = steps.slice(-30).map((s, i, arr) => {
                const t = (s.at || '').slice(11, 19) || '—';
                const lvl = s.level || 'info';
                const active = i === arr.length - 1 && status === 'running' ? ' active' : '';
                return '<div class="cron-step ' + lvl + active + '"><span class="cron-step-time">' + escapeHtml(t) +
                    '</span><span class="cron-step-msg">' + escapeHtml(s.message || '') + '</span></div>';
            }).join('');
            stepsEl.scrollTop = stepsEl.scrollHeight;
        } else {
            stepsEl.hidden = true;
        }

        const dot = $('cronLiveDot');
        const logStatus = $('cronLogStatus');
        if (status === 'running') {
            dot.classList.remove('off');
            logStatus.textContent = 'LIVE — rulează…';
        } else if (status === 'done') {
            dot.classList.add('off');
            logStatus.textContent = 'Finalizat';
        } else if (status === 'error') {
            dot.classList.add('off');
            logStatus.textContent = 'Eroare';
        } else {
            dot.classList.add('off');
            logStatus.textContent = 'Inactiv';
        }

        if (Array.isArray(logLines) && logLines.length) {
            $('cronLogConsole').textContent = logLines.join('\n');
            const con = $('cronLogConsole');
            con.scrollTop = con.scrollHeight;
        }
        if (status === 'running' || status === 'done') {
            void loadCronStagingResults(false);
        }
        updateHeroKpis();
    }

    async function pollCronProgress() {
        try {
            const data = await fetchJson(appendImportQuery(CRON_PROGRESS_API, 'lines=100'));
            if (!data.success) return;
            renderCronProgress(data.progress, data.log_lines);
            if (data.cron) updateCronPanel(data.cron);

            const running = !!data.running;
            syncCronPollInterval(running);
            if (running && !cronWasRunning) {
                cards = [];
                renderCards();
                const empty = $('cardsEmpty');
                if (empty) {
                    empty.hidden = false;
                    empty.textContent = 'Cron rulează — cartele apar la final (max '
                        + getCronDisplayLimit() + ' produse/fișier)…';
                }
            }
            if (cronWasRunning && !running && !cronPostFinishBusy) {
                cronPostFinishBusy = true;
                cronWasRunning = running;
                loadFilesLibrary();
                loadReports();
                const sum = data.progress?.summary || {};
                const el = $('cronStatus');
                el.hidden = false;
                const intervalSkipped = (data.progress?.steps || []).filter(
                    s => String(s.message || '').includes('interval furnizor neexpirat')
                ).length;
                if ((sum.pending ?? 0) === 0 || ((sum.processed ?? 0) === 0 && (sum.results || []).length === 0 && intervalSkipped > 0)) {
                    el.className = 'status warn';
                    el.textContent = intervalSkipped > 0
                        ? ('Cron terminat fără scanare — ' + intervalSkipped + ' fișier(e) sărite (interval furnizor). '
                            + 'La test folosește butonul «Testează cron» (forțează rularea).')
                        : 'Cron terminat — niciun fișier de procesat.';
                    notifyCronFinished(el.textContent, 'warn');
                    void loadCronStagingResults(true);
                    cronPostFinishBusy = false;
                } else {
                    el.className = 'status ok';
                    // Sumarul e construit din journal.stats (agregat pe PRODUSE, actualizat
                    // live la fiecare batch de staging), nu din `sum` (care e agregat pe
                    // FIȘIERE) — cele două nu trebuie confundate în mesajul afișat userului.
                    void loadCronStagingResults(true).then(journal => {
                        el.textContent = buildCronFinishedMessage(sum, journal?.stats);
                        notifyCronFinished(el.textContent, 'ok');
                    });
                    void presentCronResultsAsCards(sum)
                        .catch(() => {})
                        .finally(() => { cronPostFinishBusy = false; });
                }
            } else {
                cronWasRunning = running;
            }
        } catch { /* ignore transient */ }
    }

    function startCronPoll(intervalMs) {
        const ms = Math.max(1000, intervalMs || 2000);
        if (cronPollTimer && cronPollIntervalMs === ms) {
            return;
        }
        if (cronPollTimer) clearInterval(cronPollTimer);
        cronPollIntervalMs = ms;
        cronPollTimer = setInterval(pollCronProgress, ms);
    }

    function stopCronPoll() {
        if (cronPollTimer) {
            clearInterval(cronPollTimer);
            cronPollTimer = null;
        }
        cronPollIntervalMs = 0;
    }

    function syncCronPollInterval(running) {
        if (running) {
            startCronPoll(2000);
            return;
        }
        stopCronPoll();
    }

    async function loadFilesLibrary() {
        try {
            const empty = $('showEmptyFoldersInput')?.checked ? '1' : '0';
            const data = await fetchJson(appendImportQuery(FILES_API, 'empty=' + empty));
            if (data.success) {
                if (data.base_path) {
                    $('libBasePath').textContent = data.base_path;
                    const showcasePath = $('showcaseLibBasePath');
                    if (showcasePath) showcasePath.textContent = data.base_path;
                }
                renderRegisteredSuppliers(data.registered_suppliers || []);
                renderFilesTable(data.files || [], data.folders || []);
                renderUploadFilesTable(data.files || []);
                renderShowcaseFilesTable(data.files || []);
                updateCronPanel(data.cron);
                if (data.cron?.reports_recent?.length) {
                    renderReportsList(data.cron.reports_recent);
                }
            }
        } catch (e) {
            const err = '<tr><td colspan="6">Eroare: ' + escapeHtml(e.message) + '</td></tr>';
            const err8 = '<tr><td colspan="8">Eroare: ' + escapeHtml(e.message) + '</td></tr>';
            if ($('filesBody')) $('filesBody').innerHTML = err;
            if ($('uploadFilesBody')) $('uploadFilesBody').innerHTML = err8;
            if ($('showcaseFilesBody')) $('showcaseFilesBody').innerHTML = err;
        }
    }

    async function uploadToServer() {
        const input = $('uploadInput');
        if (!input.files?.[0]) {
            const el = $('uploadStatus');
            el.hidden = false;
            el.className = 'status error';
            el.textContent = 'Selectează un fișier pentru upload.';
            return;
        }
        const sup = $('uploadSupplier').value;
        if (!sup) {
            const el = $('uploadStatus');
            el.hidden = false;
            el.className = 'status error';
            el.textContent = 'Selectează un furnizor înregistrat în Admin → Furnizori.';
            return;
        }
        const fd = new FormData();
        fd.append('files[]', input.files[0]);
        fd.append('supplier', sup);
        $('uploadBtn').disabled = true;
        const el = $('uploadStatus');
        el.hidden = false;
        el.className = 'status info';
        el.textContent = 'Se încarcă…';
        try {
            const data = await fetchJson(UPLOAD_API, { method: 'POST', body: fd });
            if (!data.success) throw new Error(data.error || data.errors?.[0]?.error || 'Upload eșuat');
            const u = data.uploaded[0];
            el.className = 'status ok';
            el.textContent = 'Salvat: ' + (u.destination || '') + '/' + u.saved_as + ' — actualizat în tabelul de mai jos.';
            if (u.supplier) toggleFileSelection(u.supplier, u.saved_as, true);
            await loadFilesLibrary();
            input.value = '';
            $('uploadFilesPanel')?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        } catch (e) {
            el.className = 'status error';
            el.textContent = e.message;
        } finally {
            $('uploadBtn').disabled = false;
        }
    }

    function startAutoPoll() {
        if (pollTimer) clearInterval(pollTimer);
        pollTimer = setInterval(() => {
            loadFilesLibrary();
            loadReports();
        }, 60000);
        pollCronProgress();
        startCronCountdownTicker();
    }

    function escapeHtml(v) {
        return String(v ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function setStatus(msg, type) {
        const el = $('loadStatus');
        el.hidden = false;
        el.className = 'status ' + (type || 'info');
        el.textContent = msg;
    }

    function setShowcaseStatus(msg, type) {
        const el = $('showcaseStatus');
        if (!el) return;
        el.hidden = false;
        el.className = 'status ' + (type || 'info');
        el.textContent = msg;
    }

    let showcaseScanTimer = null;
    let showcaseScanStartedAt = 0;

    function showcaseScanFormatElapsed(seconds) {
        const mins = Math.floor(seconds / 60);
        const secs = seconds % 60;
        return mins > 0 ? (mins + ' min ' + secs + ' sec') : (secs + ' sec');
    }

    function showcaseScanClearTimer() {
        if (showcaseScanTimer) {
            clearInterval(showcaseScanTimer);
            showcaseScanTimer = null;
        }
    }

    function showcaseScanBeginProgress(totalFiles) {
        showcaseScanStartedAt = Date.now();
        const wrap = $('showcaseScanProgress');
        const bar = $('showcaseScanProgressBar');
        const log = $('showcaseScanProgressLog');
        if (wrap) wrap.hidden = false;
        if (bar) bar.style.width = '0%';
        if ($('showcaseScanProgressPct')) $('showcaseScanProgressPct').textContent = '0%';
        if ($('showcaseScanProgressFile')) {
            $('showcaseScanProgressFile').textContent = 'Pornesc scanarea a ' + totalFiles + ' fișier(e)…';
        }
        if (log) log.innerHTML = '';
        if ($('showcaseScanSpinner')) $('showcaseScanSpinner').hidden = false;
        showcaseScanClearTimer();
        showcaseScanTimer = setInterval(() => {
            const elapsed = Math.max(0, Math.floor((Date.now() - showcaseScanStartedAt) / 1000));
            if ($('showcaseScanProgressElapsed')) {
                $('showcaseScanProgressElapsed').textContent = showcaseScanFormatElapsed(elapsed);
            }
        }, 1000);
    }

    function showcaseScanEndProgress() {
        showcaseScanClearTimer();
        if ($('showcaseScanSpinner')) $('showcaseScanSpinner').hidden = true;
    }

    function showcaseScanSetProgress(fileIndex, fileTotal, file, phaseText) {
        const pct = fileTotal > 0 ? Math.round((fileIndex / fileTotal) * 100) : 0;
        if ($('showcaseScanProgressPct')) $('showcaseScanProgressPct').textContent = pct + '%';
        if ($('showcaseScanProgressBar')) $('showcaseScanProgressBar').style.width = pct + '%';
        const label = file
            ? ('[' + (fileIndex + 1) + '/' + fileTotal + '] ' + file.supplier + '/' + file.filename)
            : ('Pas ' + fileIndex + '/' + fileTotal);
        if ($('showcaseScanProgressFile')) {
            $('showcaseScanProgressFile').textContent = label + (phaseText ? ' — ' + phaseText : '');
        }
    }

    function showcaseScanAppendLog(message, className, active) {
        const log = $('showcaseScanProgressLog');
        if (!log) return;
        if (active) {
            log.querySelectorAll('.is-active').forEach(el => el.classList.remove('is-active'));
        }
        const li = document.createElement('li');
        li.className = String(className || '').trim() + (active ? ' is-active' : '');
        li.textContent = message;
        log.appendChild(li);
        log.scrollTop = log.scrollHeight;
    }

    function showcaseScanFinalizeLog(report) {
        if (!report) return;
        const log = $('showcaseScanProgressLog');
        if (log) log.querySelectorAll('.is-active').forEach(el => el.classList.remove('is-active'));
        const dur = report.duration_ms ? (' (' + (report.duration_ms / 1000).toFixed(1) + 's)') : '';
        const status = report.status === 'error' ? 'error' : (report.status === 'skipped' ? 'warn' : 'ok');
        const prefix = report.status === 'error' ? '✗ ' : (report.status === 'skipped' ? '○ ' : '✓ ');
        const mode = report.scan_mode ? (' [' + report.scan_mode + ']') : '';
        const rowsInfo = (report.rows_scanned != null)
            ? (' · ' + report.rows_scanned + ' rânduri scanate')
            : '';
        showcaseScanAppendLog(
            prefix + (report.supplier || '?') + '/' + (report.filename || '?') + mode
                + ' — ' + (report.message || report.status || 'gata') + rowsInfo + dur,
            status === 'error' ? 'is-error' : (status === 'warn' ? 'is-warn' : 'is-ok')
        );
        if (Array.isArray(report.ollama_batch_errors) && report.ollama_batch_errors.length) {
            report.ollama_batch_errors.slice(0, 3).forEach(err => {
                showcaseScanAppendLog(
                    '⚠ Ollama lot ' + (err.batch ?? '?') + ': ' + (err.message || 'eroare'),
                    'is-warn'
                );
            });
        }
    }

    function showcaseScanMergeCards(existing, incoming) {
        const seen = new Set((existing || []).map(c => String(c.sourceFile || '') + '::' + String(c.sku || c.SKU || '')));
        const merged = (existing || []).slice();
        (incoming || []).forEach(card => {
            const key = String(card.sourceFile || '') + '::' + String(card.sku || card.SKU || '');
            if (seen.has(key)) return;
            seen.add(key);
            merged.push(card);
        });
        return merged;
    }

    async function fetchJson(url, options = {}) {
        const opts = Object.assign({}, options);
        const headers = new Headers(opts.headers || {});
        const method = String(opts.method || 'GET').toUpperCase();
        if (method !== 'GET' && method !== 'HEAD') {
            const csrf = adminCsrfToken();
            if (csrf && !headers.has('X-ADMIN-CSRF')) {
                headers.set('X-ADMIN-CSRF', csrf);
            }
        }
        if (!headers.has('Accept')) {
            headers.set('Accept', 'application/json');
        }
        opts.headers = headers;
        if (!opts.credentials) opts.credentials = 'same-origin';
        const timeoutMs = Number(opts.timeoutMs || 0);
        if (timeoutMs > 0 && !opts.signal) {
            if (typeof AbortSignal !== 'undefined' && AbortSignal.timeout) {
                opts.signal = AbortSignal.timeout(timeoutMs);
            } else if (typeof AbortController !== 'undefined') {
                const controller = new AbortController();
                opts.signal = controller.signal;
                setTimeout(() => controller.abort(), timeoutMs);
            }
        }
        delete opts.timeoutMs;

        let res;
        try {
            res = await fetch(url, opts);
        } catch (e) {
            if (e && (e.name === 'TimeoutError' || e.name === 'AbortError')) {
                throw new Error('Timeout — serverul nu a răspuns la timp. Încearcă din nou sau reduce eșantionul.');
            }
            throw e;
        }
        const text = await res.text();
        if (!text?.trim()) throw new Error('Răspuns gol de la server (' + res.status + ')');
        let data;
        try {
            data = JSON.parse(text);
        } catch {
            if (/^\s*<!DOCTYPE html/i.test(text) || /cloudflare/i.test(text)) {
                throw new Error(
                    'Serverul a returnat HTML în loc de JSON (posibil timeout Cloudflare sau rută API blocată). '
                    + 'Reîncarcă pagina și încearcă din nou cu mai puține fișiere / limită mai mică.'
                );
            }
            throw new Error('JSON invalid: ' + text.slice(0, 150));
        }
        if (!res.ok && data && data.success === false && data.error) {
            throw new Error(String(data.error));
        }
        if (!res.ok && data && data.success === false && data.message) {
            throw new Error(String(data.message));
        }
        if (data && data.success === false) {
            throw new Error(String(data.error || data.message || ('Eroare API (' + res.status + ')')));
        }
        return data;
    }

    function normMatchSku(value) {
        return String(value || '').toUpperCase().replace(/[\s\-\/_.]/g, '');
    }

    const TECDOC_CARD_STATUSES = new Set(['exact', 'probable', 'conflict']);

    function filterCardsByTecdoc(list) {
        return (list || []).filter(c => TECDOC_CARD_STATUSES.has(c.matchStatus));
    }

    function supplementCardsFromTecdocMatch(payload) {
        if (!payload?.products?.length) return;
        const seen = new Set(cards.map(c => String(c.sourceFile || '') + '::' + normMatchSku(c.sku)));
        payload.products.forEach(p => {
            if (!TECDOC_CARD_STATUSES.has(p.status)) return;
            const k = String(p.source_file || '') + '::' + normMatchSku(p.sku_supplier);
            if (!p.sku_supplier || seen.has(k)) return;
            seen.add(k);
            cards.push(matchToPreviewCard(p));
        });
    }

    function cardLookupKey(sourceFile, sku) {
        return String(sourceFile || '') + '::' + normMatchSku(sku);
    }

    function addSkuKeyVariants(map, sourceFile, sku, card) {
        const sf = String(sourceFile || '');
        const n = normMatchSku(sku);
        if (!n) return;
        map.set(sf + '::' + n, card);
        const trimmed = n.replace(/^0+/, '');
        if (trimmed && trimmed !== n) map.set(sf + '::' + trimmed, card);
    }

    function indexPhpCardsByKey(phpCards) {
        const map = new Map();
        (phpCards || []).forEach(c => {
            addSkuKeyVariants(map, c.sourceFile, c.sku, c);
        });
        return map;
    }

    function lookupCardByKey(map, sourceFile, sku) {
        const sf = String(sourceFile || '');
        const n = normMatchSku(sku);
        if (!n) return null;
        return map.get(sf + '::' + n) || map.get(sf + '::' + n.replace(/^0+/, '')) || null;
    }

    function phpCardFromMatch(php, p) {
        const rich = isRichEnrichedCard(php);
        const card = {
            ...php,
            matchStatus: p.status,
            matchedName: p.matched_name || php.matchedName || '',
            matchMethod: p.match_method || php.matchMethod || '',
            matchedTecdocSku: p.matched_internal_sku || '',
            isPreview: false,
        };
        return attachTecdocAudit(card, p, {
            cardBuild: rich ? 'mysql_besoiu_tecdoc_base' : 'supplier_enriched',
            titleFrom: rich ? 'ART_NAME + product_compatibilities' : 'CSV furnizor',
            ttcArtId: php.ttcArtId || '',
            imageFrom: php.hasImage ? (php.imageSource || 'Poze/Autopartner') : '',
            compatCount: Number(php.compatCount) || 0,
            supplierSku: php.sku,
            supplierBrand: p.brand || php.brand,
            supplierName: p.name || php.name,
        });
    }

    const TECDOC_METHOD_LABELS = {
        tecdoc_brand_code: 'Brand + cod OEM',
        tecdoc_ean: 'EAN',
        tecdoc_code: 'Doar cod (fără brand confirmat)',
    };

    function tecdocTablesForMethod(method) {
        if (method === 'tecdoc_ean') return 'products · brands';
        if (method) return 'product_codes · products · brands';
        return '';
    }

    function extractTecdocProductId(internalSku, explicitId) {
        if (explicitId != null && explicitId !== '') return String(explicitId);
        const m = String(internalSku || '').match(/^TEC-(\d+)$/i);
        return m ? m[1] : '';
    }

    function buildTecdocAudit(product, extras = {}) {
        const method = product.match_method || extras.matchMethod || '';
        const status = product.status || extras.matchStatus || '';
        const found = TECDOC_CARD_STATUSES.has(status);
        const internalSku = product.matched_internal_sku || extras.matchedTecdocSku || '';
        const fieldsUsed = [];
        if (extras.titleFrom) fieldsUsed.push('titlu: ' + extras.titleFrom);
        if (Number(extras.compatCount) > 0) fieldsUsed.push('compatibilități vehicule (product_compatibilities)');
        if (extras.imageFrom) fieldsUsed.push('imagine: ' + extras.imageFrom);
        if (found && (product.matched_name || extras.matchedName)) fieldsUsed.push('ART_NAME (nume articol)');
        if (found && (product.matched_ttc_art_id || extras.ttcArtId)) fieldsUsed.push('TTC_ART_ID (poze)');

        return {
            found,
            db: product.tecdoc_db || 'besoiu_tecdoc_base',
            tables: product.tecdoc_tables || tecdocTablesForMethod(method),
            matchStatus: status,
            matchMethod: method,
            matchMethodLabel: TECDOC_METHOD_LABELS[method] || method || '—',
            confidence: product.confidence ?? extras.confidence ?? null,
            productId: extractTecdocProductId(internalSku, product.matched_product_id),
            internalSku,
            brand: product.matched_brand || '',
            name: product.matched_name || extras.matchedName || '',
            codes: Array.isArray(product.matched_codes) ? product.matched_codes.filter(Boolean) : [],
            ttcArtId: product.matched_ttc_art_id || extras.ttcArtId || '',
            ean: product.matched_ean || '',
            cardBuild: extras.cardBuild || '',
            titleFrom: extras.titleFrom || '',
            fieldsUsed,
            supplierSku: product.sku_supplier || extras.supplierSku || '',
            supplierBrand: product.brand || extras.supplierBrand || '',
            supplierName: product.name || extras.supplierName || '',
            notes: Array.isArray(product.notes) ? product.notes : [],
        };
    }

    function attachTecdocAudit(card, product, extras = {}) {
        if (card.tecdocAudit && extras.cardBuild === undefined) return card;
        card.tecdocAudit = buildTecdocAudit(product || {}, {
            matchStatus: card.matchStatus,
            matchMethod: card.matchMethod,
            matchedName: card.matchedName,
            matchedTecdocSku: card.matchedTecdocSku,
            ttcArtId: card.ttcArtId,
            ...extras,
        });
        return card;
    }

    function tecdocAuditSummary(audit) {
        if (!audit) return '';
        if (!audit.found) return 'TecDoc — fără match în besoiu_tecdoc_base';
        return 'TecDoc · ' + audit.db + ' · ' + (audit.matchMethodLabel || audit.matchMethod || 'match');
    }

    function renderTecdocAuditBlock(audit) {
        if (!audit) return '';
        const statusClass = audit.found
            ? (audit.matchStatus === 'probable' ? 'audit-warn' : 'audit-ok')
            : 'audit-miss';
        const taken = [];
        if (audit.productId) taken.push('products.id = ' + audit.productId);
        if (audit.brand) taken.push('brand: ' + audit.brand);
        if (audit.name) taken.push('ART_NAME: ' + audit.name);
        if (audit.codes?.length) taken.push('coduri: ' + audit.codes.join(', '));
        if (audit.ttcArtId) taken.push('TTC_ART_ID: ' + audit.ttcArtId);
        if (audit.ean) taken.push('EAN: ' + audit.ean);
        const fields = (audit.fieldsUsed || []).length
            ? audit.fieldsUsed.join(' · ')
            : (taken.length ? 'Match validare card' : '—');
        const cardBuild = audit.cardBuild
            ? (audit.cardBuild === 'mysql_besoiu_tecdoc_base'
                ? 'Card construit din MySQL (CoreDbLookup + compat)'
                : audit.cardBuild === 'supplier_csv'
                    ? 'Card din CSV furnizor (fără enrich Base)'
                    : audit.cardBuild)
            : '—';

        return `<details class="tecdoc-audit">
            <summary class="${statusClass}">${escapeHtml(tecdocAuditSummary(audit))}</summary>
            <div class="tecdoc-audit-body">
                <dl>
                    <dt>Baza de date</dt>
                    <dd><span class="tecdoc-pill">MySQL</span> ${escapeHtml(audit.db)}</dd>
                    <dt>Tabele interogate (matching)</dt>
                    <dd>${escapeHtml(audit.tables || '—')}</dd>
                    <dt>Metodă match</dt>
                    <dd>${escapeHtml(audit.matchMethodLabel)} <code>${escapeHtml(audit.matchMethod || '')}</code>
                        ${audit.confidence != null ? ' · ' + audit.confidence + '%' : ''}</dd>
                    <dt>Status</dt>
                    <dd>${escapeHtml(audit.matchStatus || '—')}</dd>
                    <dt>Ce s-a luat din TecDoc</dt>
                    <dd>${taken.length ? taken.map(t => escapeHtml(t)).join('<br>') : '—'}</dd>
                    <dt>Folosit la card</dt>
                    <dd>${escapeHtml(cardBuild)}<br>${escapeHtml(fields)}</dd>
                    <dt>Furnizor CSV (referință)</dt>
                    <dd>${escapeHtml(audit.supplierBrand || '—')} · SKU ${escapeHtml(audit.supplierSku || '—')}
                        ${audit.supplierName ? '<br>' + escapeHtml(audit.supplierName) : ''}</dd>
                    ${audit.notes?.length ? '<dt>Note</dt><dd>' + audit.notes.map(n => escapeHtml(n)).join('<br>') + '</dd>' : ''}
                </dl>
            </div>
        </details>`;
    }

    function formatTecdocTakenCell(p) {
        if (!TECDOC_CARD_STATUSES.has(p.status)) return '—';
        const parts = [];
        const pid = extractTecdocProductId(p.matched_internal_sku, p.matched_product_id);
        if (pid) parts.push('id=' + pid);
        if (p.matched_brand) parts.push('brand=' + p.matched_brand);
        if (p.matched_name) parts.push('name=' + p.matched_name);
        if (Array.isArray(p.matched_codes) && p.matched_codes.length) {
            parts.push('codes=' + p.matched_codes.join('/'));
        }
        if (p.matched_ttc_art_id) parts.push('ttc=' + p.matched_ttc_art_id);
        if (p.matched_ean) parts.push('ean=' + p.matched_ean);
        return parts.join(' · ') || '—';
    }

    async function fetchEnrichTecdocCards(products) {
        if (!products?.length) return { cards: [], skipped: 0, skippedDetails: [] };
        const data = await fetchJson(ENRICH_TECDOC_API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ products }),
            timeoutMs: 600000,
        });
        if (data.error) throw new Error(data.error);
        return {
            cards: data.cards || [],
            skipped: Number(data.skipped) || 0,
            skippedDetails: data.skippedDetails || [],
        };
    }

    /** Card complet TecDoc: date MySQL + imagine (filtru strict «Doar cu imagine»). */
    function isCompleteTecdocCard(card) {
        if (!card || card.isPreview) return false;
        if (card.cardBuild && card.cardBuild !== 'mysql_besoiu_tecdoc_base') return false;
        if (!cardHasDisplayableImage(card)) return false;
        const title = String(card.title || '').trim();
        if (title.length < 8) return false;
        const hasTecdocBody = (Array.isArray(card.parameters) && card.parameters.length > 0)
            || (card.description && String(card.description).length > 40)
            || Number(card.compatCount) > 0;
        return hasTecdocBody;
    }

    /** Acceptă card TecDoc pentru afișare / coadă — cu sau fără imagine, după filtru. */
    function acceptBuiltTecdocCard(card) {
        if (onlyWithImageSelected()) return isCompleteTecdocCard(card);
        return isDisplayableBuildCard(card);
    }

    /** Card afișabil în tab Carduri — cu sau fără imagine, după filtrul selectat. */
    function isDisplayableBuildCard(card) {
        if (!card || card.isPreview) return false;
        const title = String(card.title || '').trim();
        const sku = String(card.sku || '').trim();
        if (title.length < 4 || sku === '') return false;
        const hasTecdocBody = (Array.isArray(card.parameters) && card.parameters.length > 0)
            || (card.description && String(card.description).length > 40)
            || Number(card.compatCount) > 0
            || card.tecdocDataComplete === true
            || card.cardBuild === 'mysql_besoiu_tecdoc_base';
        if (card.cardBuild === 'mysql_besoiu_tecdoc_base' && hasTecdocBody) {
            return onlyWithImageSelected() ? cardHasDisplayableImage(card) : true;
        }
        if (card.cardBuild === 'fast_tecdoc_match' && TECDOC_CARD_STATUSES.has(card.matchStatus)) {
            return onlyWithImageSelected() ? cardHasDisplayableImage(card) : true;
        }
        if (onlyWithImageSelected()) return isCompleteTecdocCard(card);
        return hasTecdocBody || cardHasDisplayableImage(card);
    }

    function isRichEnrichedCard(card) {
        return isCompleteTecdocCard(card);
    }

    function renderCardParametersBlock(card) {
        const params = Array.isArray(card.parameters) ? card.parameters : [];
        if (!params.length) return '';
        // Evită dublarea: specificațiile sunt deja în descrierea HTML (Base.html).
        const desc = String(card.description || '');
        if (/Specificatii tehnice:|Specificații tehnice:/i.test(desc)) {
            return '';
        }
        return '<div class="product-card-params"><b>Parametri:</b><ul>'
            + params.map(p => '<li>' + escapeHtml(String(p.key || '') + (p.value ? ': ' + p.value : '')) + '</li>').join('')
            + '</ul></div>';
    }

    function renderCardTermsBlock(card) {
        if (!card.termsOfUse) return '';
        return '<div class="product-card-params"><b>Dotare:</b> ' + escapeHtml(String(card.termsOfUse)) + '</div>';
    }

    function renderCardDescriptionBlock(card) {
        if (!card.description) return '';
        return '<div class="product-card-description">' + card.description + '</div>';
    }

    /** Carduri din match MySQL TecDoc — date complete din DB; imagine opțională (Poze). */
    async function buildCardsFromTecdocMatch(payload, phpCards, fromCron) {
        const phpByKey = indexPhpCardsByKey(phpCards);
        const ordered = [];
        const needEnrich = [];
        const seen = new Set();
        let skippedIncomplete = 0;

        for (const p of payload.products || []) {
            if (!TECDOC_CARD_STATUSES.has(p.status)) continue;
            const k = cardLookupKey(p.source_file, p.sku_supplier);
            if (!p.sku_supplier || seen.has(k)) continue;
            seen.add(k);
            const php = lookupCardByKey(phpByKey, p.source_file, p.sku_supplier);
            if (php && acceptBuiltTecdocCard(php)) {
                ordered.push({ card: phpCardFromMatch(php, p) });
            } else {
                ordered.push({ product: p });
                needEnrich.push(p);
            }
        }

        let enrichedByKey = new Map();
        let enrichSkipped = 0;
        if (needEnrich.length) {
            const enrichRes = await fetchEnrichTecdocCards(needEnrich);
            enrichSkipped = enrichRes.skipped || 0;
            (enrichRes.cards || []).forEach(c => addSkuKeyVariants(enrichedByKey, c.sourceFile, c.sku, c));
        }

        const out = [];
        for (const item of ordered) {
            if (item.card) {
                if (acceptBuiltTecdocCard(item.card)) out.push(item.card);
                else skippedIncomplete++;
                continue;
            }
            const p = item.product;
            const ec = lookupCardByKey(enrichedByKey, p.source_file, p.sku_supplier);
            if (ec && acceptBuiltTecdocCard(ec)) {
                const merged = {
                    ...ec,
                    matchStatus: p.status,
                    matchedName: p.matched_name || ec.matchedName || '',
                    matchMethod: p.match_method || ec.matchMethod || '',
                    matchedTecdocSku: p.matched_internal_sku || ec.matchedTecdocSku || '',
                    isPreview: false,
                };
                if (!merged.tecdocAudit) attachTecdocAudit(merged, p);
                out.push(merged);
            } else {
                skippedIncomplete++;
            }
        }

        buildCardsFromTecdocMatch.lastSkipped = skippedIncomplete + enrichSkipped;

        if (onlyWithImageSelected()) {
            return filterCardsByImage(out, getDisplayLimit(fromCron));
        }
        return capCardsToDisplayLimit(out, fromCron);
    }
    buildCardsFromTecdocMatch.lastSkipped = 0;

    async function mergeMatchIntoCards(payload) {
        if (!payload?.products?.length) return 0;
        const phpBackup = cards.slice();
        cards = await buildCardsFromTecdocMatch(payload, phpBackup.length ? phpBackup : cards, false);
        return Math.max(0, phpBackup.length - cards.length);
    }

    function mergeMatchingPayloads(items) {
        const products = [];
        const summary = { total: 0, exact: 0, probable: 0, no_match: 0, conflict: 0 };
        const files = [];
        items.forEach(({ file, payload }) => {
            if (!payload?.products?.length) return;
            const label = file.supplier + '/' + file.filename;
            files.push(label);
            payload.products.forEach(p => {
                products.push({ ...p, source_file: label });
            });
            const s = payload.summary || {};
            summary.total += s.total || payload.products.length;
            summary.exact += s.exact || 0;
            summary.probable += s.probable || 0;
            summary.no_match += s.no_match || 0;
            summary.conflict += s.conflict || 0;
        });
        return { products, summary, files };
    }

    async function fetchBuildCardsForFile(supplier, filename, limit, options = {}) {
        const fd = new FormData();
        fd.append('supplier', supplier);
        fd.append('filename', filename);
        const pageLimit = options.limit ?? limit ?? getCardsPageSize();
        const offset = Math.max(0, parseInt(options.offset, 10) || 0);
        fd.append('limit', String(pageLimit));
        fd.append('offset', String(offset));
        fd.append('scan_mode', options.scanMode || getScanResumeMode());
        fd.append('build_mode', options.buildMode || getBuildMode());
        if (onlyWithImageSelected() && options.useServerImageFilter !== false) {
            fd.append('only_with_image', '1');
        }

        const timeoutMs = options.timeoutMs ?? 180000;
        let signal = options.signal || null;
        let localController = null;
        let timer = null;
        if (timeoutMs > 0 && typeof AbortController !== 'undefined') {
            localController = new AbortController();
            timer = setTimeout(() => localController.abort(), timeoutMs);
            if (signal) {
                if (signal.aborted) {
                    localController.abort();
                } else {
                    signal.addEventListener('abort', () => localController.abort(), { once: true });
                }
            }
            signal = localController.signal;
        }

        try {
            const data = await fetchJson(BUILD_STORED_API, {
                method: 'POST',
                body: fd,
                signal,
                timeoutMs: 0,
            });
            if (!data.success && data.error) throw new Error(data.error);
            if (!data.success && data.message) throw new Error(data.message);
            if (onlyWithImageSelected() && Array.isArray(data.cards) && !data.onlyWithImage) {
                data.cards = filterCardsByImage(data.cards);
                data.count = data.cards.length;
            }
            return data;
        } catch (e) {
            if (options.signal?.aborted) {
                throw new Error('Generare anulată.');
            }
            if (localController?.signal?.aborted) {
                throw new Error(
                    'Timeout fișier (' + Math.round(timeoutMs / 1000) + 's) — folosește 1 fișier paralel sau debifează CSV fără match TecDoc.'
                );
            }
            throw e;
        } finally {
            if (timer) clearTimeout(timer);
        }
    }

    async function runMatchingScanForFile(supplier, filename) {
        const fd = new FormData();
        fd.append('supplier', supplier);
        fd.append('filename', filename);
        fd.append('sample', String(getManualDisplayLimit()));
        fd.append('force', '1');
        if (onlyWithImageSelected()) {
            fd.append('only_with_image', '1');
        }
        const data = await fetchJson(SCAN_STORED_API, { method: 'POST', body: fd, timeoutMs: 600000 });
        if (!data.success && !data.payload) throw new Error(data.error || 'Scan eșuat');
        return { success: true, payload: data.payload };
    }

    async function fetchBuildCardsMulti(files, limit, fromCron = false) {
        const perFileLimit = limit ?? getDisplayLimit(fromCron);
        const allCards = [];
        let totalWith = 0, totalWithout = 0, okFiles = 0;
        const buildStats = [];
        for (let i = 0; i < files.length; i++) {
            const f = files[i];
            setStatus('Carduri ' + (i + 1) + '/' + files.length + ': ' + f.supplier + '/' + f.filename + '…', 'info');
            try {
                const res = await fetchBuildCardsForFile(f.supplier, f.filename, perFileLimit);
                if (res?.stats) {
                    buildStats.push({ file: f.supplier + '/' + f.filename, stats: res.stats, count: res.cards?.length || 0 });
                }
                if (res?.cards?.length) {
                    okFiles++;
                    const label = f.supplier + '/' + f.filename;
                    res.cards.slice(0, perFileLimit).forEach(c => {
                        c.sourceFile = label;
                        c.sourceSupplier = f.supplier;
                        allCards.push(c);
                    });
                    totalWith += res.summary?.withImage || 0;
                    totalWithout += res.summary?.withoutImage || 0;
                }
            } catch (e) {
                console.warn('build cards', f.filename, e);
            }
        }
        const filtered = onlyWithImageSelected()
            ? capCardsToDisplayLimit(filterCardsByImage(allCards, perFileLimit * files.length), fromCron)
            : allCards.filter(isDisplayableBuildCard);
        return {
            cards: filtered,
            count: filtered.length,
            summary: { withImage: totalWith, withoutImage: totalWithout },
            okFiles,
            buildStats,
        };
    }

    async function runMatchingScanMulti(files, onFileProgress) {
        const items = [];
        for (let i = 0; i < files.length; i++) {
            const f = files[i];
            const key = f.supplier + '/' + f.filename;
            if (typeof onFileProgress === 'function') {
                onFileProgress({ phase: 'start', index: i, total: files.length, file: f, key });
            }
            try {
                setStatus('Matching ' + (i + 1) + '/' + files.length + ': ' + key + '…', 'info');
                const res = await runMatchingScanForFile(f.supplier, f.filename);
                items.push({ file: f, payload: res.payload });
                if (typeof onFileProgress === 'function') {
                    onFileProgress({ phase: 'done', index: i, total: files.length, file: f, key, payload: res.payload });
                }
            } catch (e) {
                console.warn('matching scan', key, e);
                if (typeof onFileProgress === 'function') {
                    onFileProgress({ phase: 'error', index: i, total: files.length, file: f, key, error: e });
                }
            }
        }
        return { success: true, payload: mergeMatchingPayloads(items), items };
    }

    function requireSelectedFiles() {
        const files = getSelectedFiles();
        if (!files.length) {
            setStatus('Bifează cel puțin un fișier în bibliotecă (Pasul 2).', 'error');
            return null;
        }
        return files;
    }

    function requireShowcaseSelectedFiles() {
        const files = getSelectedFiles();
        if (!files.length) {
            setShowcaseStatus('Bifează cel puțin un fișier CSV din lista de mai sus (secțiunea «Fișiere pentru vitrină»).', 'error');
            const panel = $('showcaseFilesPanel');
            if (panel) {
                panel.classList.add('highlight-pulse');
                panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                setTimeout(() => panel.classList.remove('highlight-pulse'), 2500);
            }
            return null;
        }
        return files;
    }

    function matchToPreviewCard(p) {
        const purchaseNet = Number(p.price_purchase_net ?? p.price);
        const hasPrice = Number.isFinite(purchaseNet) && purchaseNet > 0;
        const brand = p.matched_brand || p.brand || '';
        const imageUrl = p.image_url || '';
        const title = (p.matched_name || p.name || p.sku_supplier || 'Produs').trim();
        return cardPriceFromProduct({
            sku: p.sku_supplier || '',
            brand: brand,
            title: title,
            name: p.name || '',
            supplier: p.source_file ? p.source_file.split('/')[0] : 'matching',
            sourceFile: p.source_file || '',
            sourceSupplier: p.source_file ? p.source_file.split('/')[0] : '',
            hasImage: !!p.has_image,
            imageUrl: imageUrl,
            ttcArtId: p.ttc_art_id || p.matched_ttc_art_id || '',
            autopartnerCode: p.autopartner_code || '',
            pozeFolder: p.poze_folder || '',
            imageSource: p.image_source || '',
            matchStatus: p.status,
            scrapeQuery: p.name || p.sku_supplier || '',
            isPreview: true,
            matchedName: p.matched_name || '',
            matchMethod: p.match_method || '',
            priceCsv: hasPrice && p.price_csv != null ? Number(p.price_csv) : (hasPrice ? purchaseNet : null),
            pricePurchaseNet: hasPrice ? purchaseNet : null,
            priceNet: hasPrice ? purchaseNet : null,
            pricePurchaseVat: p.price_purchase_vat ?? null,
            feedMarkupPercent: p.feed_markup_percent ?? p.supplier_profile?.markup_percent ?? null,
            purchaseVatPercent: p.vat_percent ?? null,
            supplierVatRule: p.vat_rule ?? p.supplier_profile?.import_vat_rule ?? null,
            purchaseVatExtraApplied: p.vat_extra_applied,
            tecdocAudit: buildTecdocAudit(p, {
                matchStatus: p.status,
                matchMethod: p.match_method,
                matchedName: p.matched_name,
                matchedTecdocSku: p.matched_internal_sku,
                ttcArtId: p.ttc_art_id || p.matched_ttc_art_id,
                cardBuild: 'preview',
                titleFrom: 'matched_name sau CSV furnizor',
                supplierSku: p.sku_supplier,
                supplierBrand: p.brand,
                supplierName: p.name,
            }),
        }, p);
    }

    function cardsFromMatchingPayload(payload) {
        return [];
    }

    function presentMatchResults(matchData, preferTab, fromCron = false) {
        const payload = matchData?.payload;
        if (!payload?.products?.length) {
            throw new Error('Matching fără produse în răspuns.');
        }
        cards = [];
        showResults(preferTab || 'matching', { keepWorkflowTab: fromCron });
        renderCards();
        renderMatching(payload, false);
        return payload;
    }

    function filesAndReportsFromCronSummary(summary) {
        const out = [];
        const seen = new Set();
        for (const r of summary?.results || []) {
            if (!r?.success || r?.skipped) continue;
            const supplier = r.parse?.supplier;
            const filename = r.parse?.filename;
            if (!supplier || !filename) continue;
            const key = supplier + '/' + filename;
            if (seen.has(key)) continue;
            seen.add(key);
            out.push({ supplier, filename, reportId: r.report?.id || '' });
        }
        return out;
    }

    async function fetchReportPayload(reportId, sample) {
        if (!reportId) return null;
        const d = await fetchJson(
            appendImportQuery(REPORTS_API, 'id=' + encodeURIComponent(reportId) + '&sample=' + (sample || 20))
        );
        return d.report || null;
    }

    async function presentCronResultsAsCards(summary) {
        const items = filesAndReportsFromCronSummary(summary);
        if (!items.length) return false;

        const limit = getCronDisplayLimit();
        const reportItems = [];
        for (const item of items) {
            if (!item.reportId) continue;
            try {
                const payload = await fetchReportPayload(item.reportId, limit);
                if (payload?.products?.length) {
                    reportItems.push({ file: item, payload });
                }
            } catch { /* skip */ }
        }

        const mergedReport = reportItems.length ? mergeMatchingPayloads(reportItems) : null;
        const files = items.map(i => ({ supplier: i.supplier, filename: i.filename }));

        return loadCardsForFiles(files, {
            fromCron: true,
            alsoMatch: false,
            reportPayload: mergedReport,
        });
    }

    async function enrichCardsFromBuild(files, reportPayload) {
        const limit = getCronDisplayLimit();
        const cardRes = await fetchBuildCardsMulti(files, limit, true);
        cards = reportPayload?.products?.length
            ? await buildCardsFromTecdocMatch(reportPayload, cardRes?.cards || [], true)
            : capCardsToDisplayLimit(cardRes?.cards || [], true);
        cards = await prepareCardsForDisplay(cards, true);
        if (reportPayload?.products?.length) {
            renderMatching(reportPayload, false);
        }
        showResults('cards', { keepWorkflowTab: true });
        renderCards();
        let withImg = 0;
        cards.forEach(c => { if (cardHasDisplayableImage(c)) withImg++; });
        setStatus(
            'Cron — ' + cards.length + ' carduri TecDoc (max ' + limit + ' total) · '
            + withImg + ' cu imagine verificată.',
            'ok'
        );
    }

    async function loadCardsForFiles(files, options = {}) {
        if (!files?.length) return false;
        if (cardsJobBusy) {
            setStatus('Generare carduri deja în curs — așteaptă sau apasă Anulează în panoul de progres.', 'warn');
            return false;
        }

        const fromCron = !!options.fromCron;
        const reportPayload = options.reportPayload;
        resetScanUiState();

        if (fromCron && reportPayload?.products?.length) {
            setStatus(
                'Cron — încarc carduri TecDoc (max ' + getCronDisplayLimit() + ' total)…',
                'ok'
            );
            cardsJobBusy = true;
            void enrichCardsFromBuild(files, reportPayload)
                .catch(() => {})
                .finally(() => {
                    cardsJobBusy = false;
                    clearSelectionAfterScan();
                });
            return true;
        }

        cardsJobBusy = true;
        cardsBuildAbortController = typeof AbortController !== 'undefined' ? new AbortController() : null;
        if (!fromCron) {
            $('loadBtn').disabled = true;
            $('scanBtn').disabled = true;
            $('loadFromStartBtn') && ($('loadFromStartBtn').disabled = true);
            openCardsBuildProgressModal(files.length);
        }

        const multiFileHint = files.length > 1
            ? (' · ' + getBuildParallelism() + ' fișiere în paralel')
            : '';
        const modeHint = ' [MySQL TecDoc]';
        setStatus(
            (fromCron ? 'Cron — generez cartele… ' : 'Procesez ') + files.length + ' fișier(e)…' + modeHint + multiFileHint,
            'info'
        );

        try {
            let excludedNoMatch = 0;
            const scanMode = options.scanMode || getScanResumeMode();
            await initCardsPagination(files, scanMode);
            if (cardsPagination) {
                cardsPagination.buildSignal = cardsBuildAbortController?.signal;
            }
            cards = [];
            let incrementalUiReady = false;
            const scanFileErrors = [];

            const buildStartedAt = Date.now();
            const firstPage = await fetchBuildCardsPage(cardsPagination, ev => {
                const elapsedSec = Math.max(0, Math.floor((Date.now() - buildStartedAt) / 1000));
                updateCardsBuildProgressUi(ev, files.length, cards.length);
                if (ev.phase === 'start') {
                    setStatus(
                        'Generez carduri — fișier ' + (ev.index + 1) + '/' + ev.total + ': '
                        + ev.key + ' (start #' + ev.offset + ')…'
                        + multiFileHint + ' · ' + elapsedSec + 's',
                        'info'
                    );
                    return;
                }
                if (ev.phase === 'error') {
                    const msg = ev.error?.message || String(ev.error || 'Eroare necunoscută');
                    scanFileErrors.push(ev.key + ': ' + msg);
                    setStatus(
                        'Fișier ' + (ev.index + 1) + '/' + ev.total + ' (' + ev.key + ') — ' + msg
                        + ' — continui cu restul…',
                        'warn'
                    );
                    return;
                }
                if (ev.phase !== 'done') return;

                cardsPagination.fileOffsets = ev.fileOffsets || cardsPagination.fileOffsets;
                cardsPagination.hasMore = !!ev.hasMore;
                if (ev.exhausted && !(ev.cards || []).length && ev.res?.hint) {
                    scanFileErrors.push(ev.key + ' — ' + ev.res.hint);
                } else if (ev.exhausted && !(ev.cards || []).length) {
                    scanFileErrors.push(ev.key + ' — 0 carduri (sfârșit segment sau fără match TecDoc)');
                }
                const batchCards = (ev.cards || []).filter(isDisplayableBuildCard);
                if (batchCards.length) {
                    cards = mergeUniqueCompleteCards(cards, batchCards);
                    showResults('cards', { keepWorkflowTab: fromCron });
                    renderCards();
                    if (!incrementalUiReady) {
                        setupCardsInfiniteScroll();
                        incrementalUiReady = true;
                    }
                }
                const cardsEl = $('cardsBuildProgressCards');
                if (cardsEl) cardsEl.textContent = String(cards.length);
                setStatus(
                    'Fișier ' + (ev.index + 1) + '/' + ev.total + ' gata: '
                    + batchCards.length + ' carduri · total ' + cards.length
                    + (ev.res?.hint ? ' — ' + ev.res.hint : '')
                    + (ev.index + 1 < ev.total ? ' — urmează următorul…' : ''),
                    batchCards.length ? 'info' : 'warn'
                );
            });

            cardsPagination.fileOffsets = firstPage.fileOffsets || cardsPagination.fileOffsets;
            cardsPagination.exhaustedFiles = firstPage.exhaustedFiles || cardsPagination.exhaustedFiles;
            cardsPagination.hasMore = !!firstPage.hasMore;
            const initialCardCount = Math.max(
                (firstPage.cards || []).filter(isDisplayableBuildCard).length,
                cards.length
            );
            if (!fromCron) {
                finishCardsBuildProgressScan(
                    initialCardCount > 0 || scanFileErrors.length < files.length,
                    initialCardCount > 0
                        ? 'Scanare OK — ' + initialCardCount + ' carduri gata de afișat'
                        : 'Scanare terminată — 0 carduri în acest eșantion',
                    files.length,
                    initialCardCount,
                    scanFileErrors
                );
            }

            const cardRes = {
                cards: firstPage.cards || [],
                okFiles: (firstPage.cards || []).length ? files.length : 0,
            };

            if (cardRes.cards.length || reportPayload?.products?.length) {
                showResults('cards', { keepWorkflowTab: fromCron });
                if (reportPayload?.products?.length) {
                    cards = await buildCardsFromTecdocMatch(reportPayload, cardRes.cards || [], fromCron);
                    renderMatching(reportPayload, false);
                } else {
                    cards = fromCron
                        ? capCardsToDisplayLimit((cardRes.cards || []).filter(isDisplayableBuildCard), fromCron)
                        : (cardRes.cards || []).filter(isDisplayableBuildCard);
                    renderMatching({ products: [] }, false);
                }
                if (!cards.length) {
                    throw new Error(
                        'Niciun produs cu date TecDoc în MySQL pentru eșantionul selectat. '
                        + 'Verifică matching-ul, filtrul de imagini sau mărește eșantionul.'
                    );
                }
                const targetLimit = getDisplayLimit(fromCron) * Math.max(1, files.length);
                const beforeFill = cards.length;
                await Promise.race([
                    fillCardsToTargetLimit(fromCron),
                    new Promise(resolve => setTimeout(resolve, 6000)),
                ]);
                cards = await prepareCardsForDisplay(cards, fromCron);
                renderCards();
                setupCardsInfiniteScroll();
                let withImg = 0;
                cards.forEach(c => { if (cardHasDisplayableImage(c)) withImg++; });
                const skippedNote = buildCardsFromTecdocMatch.lastSkipped
                    ? ' · ' + buildCardsFromTecdocMatch.lastSkipped + ' ignorate (fără card complet)'
                    : '';
                const fillNote = cards.length < targetLimit
                    ? ' · ' + cards.length + '/' + targetLimit + ' cerute'
                        + (cardsPagination.hasMore ? ' (mai există în CSV — scroll sau re-generează)' : ' (sfârșit fișier sau match-uri insuficiente)')
                    : '';
                const autoFillNote = cards.length > beforeFill
                    ? ' · auto-încărcate ' + (cards.length - beforeFill) + ' suplimentare'
                    : '';
                setStatus(
                    (fromCron ? 'Cron finalizat — ' : 'Gata: ') + files.length + ' fișier(e) · '
                    + cards.length + ' carduri TecDoc · ' + withImg + ' cu imagine'
                    + skippedNote
                    + fillNote
                    + autoFillNote
                    + (excludedNoMatch ? ' · ' + excludedNoMatch + ' fără match' : '')
                    + (onlyWithImageSelected() ? ' (filtru imagine)' : '')
                    + (reportPayload ? ' · cron MySQL TecDoc' : '')
                + ' · start #' + Object.values(cardsPagination.fileOffsets || {})[0] + '+'
                + (scanFileErrors.length ? ' · ' + scanFileErrors.length + ' fișier(e) fără carduri' : '')
                + '.',
                    scanFileErrors.length && !cards.length ? 'warn' : 'ok'
                );
                if (!fromCron) clearSelectionAfterScan();
                return true;
            }

            if (reportPayload?.products?.length) {
                cards = await buildCardsFromTecdocMatch(reportPayload, [], fromCron);
                cards = await prepareCardsForDisplay(cards, fromCron);
                showResults(cards.length ? 'cards' : 'matching', { keepWorkflowTab: fromCron });
                renderCards();
                renderMatching(reportPayload, false);
                setStatus(
                    'Cron — ' + cards.length + ' carduri complete din raport · '
                    + (buildCardsFromTecdocMatch.lastSkipped || 0) + ' ignorate.',
                    cards.length ? 'ok' : 'warn'
                );
                if (!fromCron && cardsBuildModalVisible) closeCardsBuildProgressModal(!!cards.length, { immediate: true });
                return true;
            }

            const fallbackMatch = await runMatchingScanMulti(files).catch(() => null);
            if (fallbackMatch?.payload?.products?.length) {
                presentMatchResults(fallbackMatch, 'matching');
                const s = fallbackMatch.payload.summary || {};
                const matched = (s.exact || 0) + (s.probable || 0) + (s.conflict || 0);
                const noImg = onlyWithImageSelected();
                setStatus(
                    noImg
                        ? (matched + ' produse match TecDoc, dar 0 cu imagine locală (Poze/Autopartner). '
                            + 'Schimbă filtrul pe «Toate produsele» sau folosește Scraping.')
                        : ('Matching: ' + matched + ' găsite, dar 0 carduri complete din ' + files.length + ' fișier(e). '
                            + 'Verifică enrichment TecDoc sau mărește eșantionul.'),
                    'warn'
                );
                if (!fromCron && cardsBuildModalVisible) closeCardsBuildProgressModal(false, { immediate: true });
                return true;
            }

            const errDetail = scanFileErrors.length
                ? scanFileErrors.slice(0, 3).join(' · ')
                : '';
            throw new Error(
                (onlyWithImageSelected()
                    ? 'Niciun produs cu imagine în fișierele selectate. '
                        + 'Încearcă „Toate produsele” sau selectează mai puține fișiere (1–2 furnizori). '
                        + 'Filtrul caută poze în Poze/ și Autopartner.'
                    : 'Nu s-au putut genera carduri pentru fișierele procesate. '
                        + 'Verifică maparea CSV (Inspect) sau debifează fișiere fără match (ex. Autonet).')
                + (errDetail ? ' Detalii: ' + errDetail : '')
            );
        } catch (e) {
            if (e && (e.name === 'AbortError' || /anulat/i.test(String(e.message || '')))) {
                setStatus('Generare carduri anulată.', 'warn');
                if (!fromCron) closeCardsBuildProgressModal(false, { immediate: true });
                return false;
            }
            if (reportPayload?.products?.length) {
                presentMatchResults({ success: true, payload: reportPayload }, 'cards');
                setStatus('Cron — cartele preview (fără Base TecDoc): ' + files.length + ' fișier(e).', 'warn');
                if (!fromCron && cardsBuildModalVisible) closeCardsBuildProgressModal(false, { immediate: true });
                return true;
            }
            setStatus((fromCron ? 'Cron terminat — fără cartele: ' : 'Eroare: ') + e.message, fromCron ? 'warn' : 'error');
            if (!fromCron && cardsBuildModalVisible) closeCardsBuildProgressModal(false);
            return false;
        } finally {
            cardsJobBusy = false;
            cardsBuildAbortController = null;
            if (!fromCron) {
                $('loadBtn').disabled = false;
                $('scanBtn').disabled = false;
                $('loadFromStartBtn') && ($('loadFromStartBtn').disabled = false);
            }
        }
    }

    async function loadCards() {
        const files = requireSelectedFiles();
        if (!files) return;
        return loadCardsForFiles(files, { scanMode: getScanResumeMode() });
    }

    async function loadCardsFromStart() {
        const files = requireSelectedFiles();
        if (!files) return;
        return loadCardsForFiles(files, { scanMode: 'restart' });
    }

    async function scanOnly() {
        const files = requireSelectedFiles();
        if (!files) return;
        resetScanUiState();
        $('scanBtn').disabled = true;
        $('loadBtn').disabled = true;
        setStatus('Matching pe ' + files.length + ' fișier(e)…', 'info');
        try {
            const matchData = await runMatchingScanMulti(files);
            if (!matchData?.payload?.products?.length) {
                throw new Error('Niciun produs găsit în fișierele selectate.');
            }

            let cardRes = { cards: [] };
            try { cardRes = await fetchBuildCardsMulti(files); } catch { cardRes = { cards: [] }; }

            cards = await buildCardsFromTecdocMatch(matchData.payload, cardRes.cards || [], false);
            await fillCardsToTargetLimit(false);
            cards = await prepareCardsForDisplay(cards, false);
            showResults(cards.length ? 'cards' : 'matching');
            renderCards();
            renderMatching(matchData.payload, false);
            let withImg = 0;
            cards.forEach(c => { if (cardHasDisplayableImage(c)) withImg++; });
            const skippedNote = buildCardsFromTecdocMatch.lastSkipped
                ? ' · ' + buildCardsFromTecdocMatch.lastSkipped + ' ignorate (fără card complet)'
                : '';
            setStatus(
                'Carduri TecDoc complete: ' + cards.length + ' (max ' + getManualDisplayLimit() + ') · '
                + withImg + ' cu imagine · Matching: ' + (matchData.payload.summary?.exact ?? 0) + ' exact'
                + skippedNote + '.',
                cards.length ? 'ok' : 'warn'
            );
            clearSelectionAfterScan();
        } catch (e) {
            setStatus('Eroare: ' + e.message, 'error');
        } finally {
            $('scanBtn').disabled = false;
            $('loadBtn').disabled = false;
        }
    }

    function getSpeedProfile() {
        return SPEED_PROFILES[$('speedModeInput')?.value || 'fast'] || SPEED_PROFILES.fast;
    }

    async function loadIndexStatus() {
        const el = $('indexStatus');
        try {
            const data = await fetchJson(INDEX_API);
            const idx = data.index || {};
            el.hidden = false;
            if (idx.available && idx.entries > 0) {
                el.className = 'status ok';
                el.textContent = 'Index Base TecDoc: ' + idx.entries + ' SKU, ' + idx.codes + ' coduri (' + idx.sizeMb + ' MB). Lookup rapid activ.';
            } else {
                el.className = 'status warn';
                el.textContent = 'Index Base lipsește — maparea va fi lentă. Rulează: py scripts/build_base_index_parallel.py';
            }
        } catch { el.hidden = true; }
    }

    const WORKFLOW_TAB_KEY = 'importProWorkflowTab';

    function switchWorkflowTab(name) {
        if (!name) return;
        document.querySelectorAll('.workflow-tab').forEach(tab => {
            const active = tab.dataset.workflowTab === name;
            tab.classList.toggle('active', active);
            tab.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        document.querySelectorAll('.workflow-tab-panel').forEach(panel => {
            const active = panel.id === 'workflow-tab-' + name;
            panel.classList.toggle('active', active);
            panel.hidden = !active;
        });
        try { localStorage.setItem(WORKFLOW_TAB_KEY, name); } catch (_) {}
        if (name === 'log') void loadScanLog();
    }

    function markWorkflowResultsReady() {
        document.getElementById('workflowTabLibrary')?.classList.add('has-results');
    }

    function restoreWorkflowTab() {
        let name = 'upload';
        try {
            const hash = (location.hash || '').replace(/^#/, '');
            if (hash === 'upload' || hash === 'library' || hash === 'showcase' || hash === 'cron' || hash === 'log') name = hash;
            else {
                const saved = localStorage.getItem(WORKFLOW_TAB_KEY);
                if (saved === 'upload' || saved === 'library' || saved === 'cron' || saved === 'log') name = saved;
            }
        } catch (_) {}
        switchWorkflowTab(name);
    }

    document.querySelectorAll('.workflow-tab').forEach(btn => {
        btn.addEventListener('click', () => switchWorkflowTab(btn.dataset.workflowTab));
    });

    function showResults(preferTab, options) {
        options = options || {};
        const panel = $('resultsPanel');
        if (!panel) return;
        panel.hidden = false;
        markWorkflowResultsReady();
        if (!options.keepWorkflowTab) {
            switchWorkflowTab('library');
            requestAnimationFrame(() => panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' }));
        }
        switchResultTab(preferTab || 'cards');
    }

    function switchResultTab(name) {
        const resultsPanel = $('resultsPanel');
        if (!resultsPanel) return;
        resultsPanel.querySelectorAll('.tab').forEach(t => t.classList.toggle('active', t.dataset.tab === name));
        resultsPanel.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
        const panel = document.getElementById('tab-' + name);
        if (panel) panel.classList.add('active');
    }

    document.querySelectorAll('#resultsPanel .tab').forEach(btn => {
        btn.addEventListener('click', () => switchResultTab(btn.dataset.tab));
    });

    function getProductImageUrl(card) {
        if (card.imageDisplayUrl) return importAbsoluteUrl(card.imageDisplayUrl);
        if (card.scrapedImageUrl) return proxyImage(card.scrapedImageUrl);
        const source = String(card.imageSource || '').toLowerCase();
        const url = new URL(IMAGE_API);
        if (source === 'autopartner' && card.autopartnerCode) {
            url.searchParams.set('source', 'autopartner');
            url.searchParams.set('code', card.autopartnerCode);
            return url.toString();
        }
        if (source === 'poze' && card.ttcArtId) {
            url.searchParams.set('source', 'poze');
            const folder = String(card.pozeFolder || card.pozeBrand || card.brand || '').replace(/^=+/, '');
            url.searchParams.set('brand', folder);
            url.searchParams.set('id', card.ttcArtId);
            return url.toString();
        }
        return '';
    }

    function onCardImageError(img) {
        const idx = Number(img.closest('.product-card')?.dataset?.cardIndex);
        if (!Number.isNaN(idx) && cards[idx]) {
            cards[idx].hasImage = false;
            cards[idx].imageDisplayUrl = '';
            cards = applyVerifiedImageCards(cards);
            renderCards();
            return;
        }
        const wrap = img.closest('.product-card-image-wrap');
        if (wrap) {
            img.remove();
            if (!wrap.querySelector('.placeholder')) {
                wrap.insertAdjacentHTML('beforeend', '<div class="placeholder">Imagine indisponibilă</div>');
            }
        }
        const card = img.closest('.product-card');
        if (card) card.classList.add('no-image');
        updateCardsSummary();
    }

    function proxyImage(remoteUrl) {
        if (String(remoteUrl || '').includes('/_proxy/scraped-image.php')) {
            return remoteUrl;
        }
        const u = new URL(SCRAPER_API);
        u.searchParams.set('view', 'image_proxy');
        u.searchParams.set('url', remoteUrl);
        return u.toString();
    }

    function cardImagePlaceholderHtml(card) {
        const hasTecdoc = card.tecdocDataComplete
            || (Array.isArray(card.parameters) && card.parameters.length > 0)
            || Number(card.compatCount) > 0
            || (card.description && String(card.description).length > 40);
        if (hasTecdoc) {
            return '<div class="placeholder">Fără poză în Poze<br><small>Date TecDoc complete · Scraping opțional</small></div>';
        }
        return '<div class="placeholder">Lipsește imaginea<br><small>Apasă Scraping</small></div>';
    }

    function cardHasImage(card) { return cardHasDisplayableImage(card); }

    function renderCards() {
        const grid = $('cardsGrid');
        const empty = $('cardsEmpty');
        grid.innerHTML = '';
        selectedCardIndexes = new Set([...selectedCardIndexes].filter(i => i < cards.length));
        if (!cards.length) {
            empty.hidden = false;
            $('cardsSummary').textContent = '0 carduri';
            $('scrapeAllBtn').disabled = true;
            updateStageSelectedBtn();
            return;
        }
        empty.hidden = true;
        let withImg = 0, withoutImg = 0;
        cards.forEach((card, index) => {
            const hasImg = cardHasImage(card);
            if (hasImg) withImg++;
            else withoutImg++;
            const imageSrc = hasImg ? getProductImageUrl(card) : '';
            let badge = '';
            if (card.matchStatus === 'exact') badge = '<span class="badge badge-exact">Match exact</span>';
            else if (card.matchStatus === 'probable') badge = '<span class="badge badge-probable">Match probabil</span>';
            else if (card.matchStatus === 'no_match') badge = '<span class="badge badge-no_match">Fără match</span>';
            else if (card.matchStatus === 'conflict') badge = '<span class="badge badge-conflict">Conflict</span>';
            else if (card.scrapedImageUrl || card.scrapedImagePath) {
                const oem = card.scrapedImageOem || card.sku || '';
                badge = '<span class="badge badge-scraped">Scraper ' + (card.scrapedImageScore ?? '') + '%'
                    + (oem ? ' · OEM ' + escapeHtml(oem) : '') + '</span>';
            }
            else if (card.hasImage) badge = '<span class="badge badge-poze">' + escapeHtml(card.imageSource || 'poze') + '</span>';
            else badge = '<span class="badge badge-missing">Fără imagine</span>';

            const imageBlock = hasImg
                ? '<img class="product-card-image" src="' + escapeHtml(imageSrc) + '" alt="" loading="lazy" onerror="onCardImageError(this)">'
                : cardImagePlaceholderHtml(card);
            const scrapeBtn = hasImg ? '' : '<button type="button" class="btn btn-green btn-sm" data-scrape="' + index + '">Scraping</button>';
            const checked = selectedCardIndexes.has(index) ? ' checked' : '';
            const paramsHtml = renderCardParametersBlock(card);
            const termsHtml = renderCardTermsBlock(card);
            const descHtml = renderCardDescriptionBlock(card);
            const imgMeta = hasImg ? ('Img: ' + escapeHtml(card.imageSource || card.scrapedImageSource || 'poze')
                + (card.scrapedImageFolder ? ' · ' + escapeHtml(card.scrapedImageFolder) : '')
                + (card.scrapedImageOem ? ' · OEM ' + escapeHtml(card.scrapedImageOem) : '')) : '';
            const compatMeta = card.compatCount ? (card.compatCount + ' compat.') : '';

            grid.insertAdjacentHTML('beforeend', `
                <article class="product-card ${hasImg ? '' : 'no-image'}" data-card-index="${index}">
                    <label class="product-card-select" style="display:flex;align-items:center;gap:8px;margin-bottom:8px">
                        <input type="checkbox" class="card-select-cb" data-card-index="${index}"${checked}>
                        <span>Selectează</span>
                    </label>
                    <div class="product-card-image-wrap">${badge}${imageBlock}</div>
                    <div class="product-card-body">
                        <h4 class="product-card-title">${escapeHtml(card.title)}</h4>
                        <div class="meta">
                            <span>SKU: ${escapeHtml(card.sku)}</span>
                            <span>${escapeHtml(card.brand)}</span>
                            <span>${escapeHtml(card.supplier || '—')}</span>
                            ${imgMeta ? '<span>' + imgMeta + '</span>' : ''}
                            ${compatMeta ? '<span>' + compatMeta + '</span>' : ''}
                            ${card.sourceFile ? '<span>' + escapeHtml(card.sourceFile) + '</span>' : ''}
                            ${card.isPreview ? '<span class="audit-warn">preview</span>' : ''}
                        </div>
                        ${renderCardPriceBlock(card)}
                        ${paramsHtml}
                        ${termsHtml}
                        ${descHtml}
                        ${renderTecdocAuditBlock(card.tecdocAudit)}
                        <div class="card-actions">${scrapeBtn}</div>
                        <div class="scrape-log" id="log-${index}"></div>
                    </div>
                </article>`);
        });
        const targetLimit = getManualDisplayLimit();
        const scrollHint = cardsPagination.hasMore ? ' · scroll pentru mai multe' : '';
        const limitHint = cards.length < targetLimit
            ? ' · ' + cards.length + '/' + targetLimit + ' din limită'
            : '';
        const selectedHint = selectedCardIndexes.size > 0
            ? ' · ' + selectedCardIndexes.size + ' selectate'
            : '';
        $('cardsSummary').textContent = cards.length + ' carduri · ' + withImg + ' cu imagine · ' + withoutImg + ' fără'
            + limitHint + selectedHint + scrollHint;
        $('scrapeAllBtn').disabled = withoutImg === 0 || batchRunning;
        grid.querySelectorAll('[data-scrape]').forEach(btn => {
            btn.addEventListener('click', () => scrapeForCard(parseInt(btn.dataset.scrape, 10)));
        });
        grid.querySelectorAll('.card-select-cb').forEach(cb => {
            cb.addEventListener('change', ev => {
                const idx = parseInt(ev.target.dataset.cardIndex, 10);
                if (ev.target.checked) selectedCardIndexes.add(idx);
                else selectedCardIndexes.delete(idx);
                updateStageSelectedBtn();
                const allCb = $('cardsSelectAllCheckbox');
                if (allCb && !allCb.disabled) {
                    allCb.checked = selectedCardIndexes.size === cards.length && cards.length > 0;
                }
            });
        });
        const allCbSync = $('cardsSelectAllCheckbox');
        if (allCbSync && !allCbSync.disabled) {
            allCbSync.checked = selectedCardIndexes.size === cards.length && cards.length > 0;
        }
        updateStageSelectedBtn();
    }

    if (window.BesoiuImportAi && P.aiRagApi) {
        renderCards = window.BesoiuImportAi.patchRenderCards(renderCards);
        window.BesoiuImportAi.init({
            apiUrl: P.aiRagApi,
            escapeHtml,
            showToast: showImportToast,
            getCards: () => cards,
            renderCards,
        });
    }

    function renderMatching(payload, autoSwitch = true) {
        const empty = $('matchEmpty');
        const table = $('matchTable');
        if (!payload?.products?.length) {
            empty.hidden = false;
            table.hidden = true;
            $('matchStats').innerHTML = '';
            $('matchBody').innerHTML = '';
            return;
        }
        empty.hidden = true;
        table.hidden = false;
        matchProducts = payload.products;
        const s = payload.summary || {};
        $('matchStats').innerHTML = [
            ['total','Total',''],['exact','Exact','exact'],['probable','Probabil','probable'],
            ['no_match','Fără match','no_match'],['conflict','Conflict','conflict'],
        ].map(([k,l,c]) => '<span class="stat-pill ' + c + '">' + l + ': ' + (s[k]??0) + '</span>').join('');
        $('matchBody').innerHTML = matchProducts.map(p => {
            const notes = [
                ...(p.notes || []),
                (p.conflicts || []).length ? 'conflicte: ' + JSON.stringify(p.conflicts) : '',
            ].filter(Boolean).join(' · ');
            const methodLabel = TECDOC_METHOD_LABELS[p.match_method] || p.match_method || '—';
            const dbLabel = TECDOC_CARD_STATUSES.has(p.status)
                ? (p.tecdoc_db || 'besoiu_tecdoc_base')
                : '—';
            const tables = p.tecdoc_tables || tecdocTablesForMethod(p.match_method);
            return '<tr data-status="' + (p.status || 'no_match') + '"><td>' + (p.row??'') + '</td><td>' + escapeHtml(p.source_file||'—') +
                '</td><td>' + escapeHtml(p.sku_supplier || '—') +
                '</td><td>' + escapeHtml(p.name || '—') + '</td><td>' + (p.price ?? '—') +
                '</td><td><span class="match-badge ' + (p.status || 'no_match') + '">' + (p.status || 'no_match') + '</span></td><td>' +
                escapeHtml(dbLabel) + (tables ? '<br><small>' + escapeHtml(tables) + '</small>' : '') +
                '</td><td>' + escapeHtml(methodLabel) +
                (p.confidence != null ? '<br><small>' + p.confidence + '%</small>' : '') +
                '</td><td><small>' + escapeHtml(formatTecdocTakenCell(p)) + '</small></td><td><small>' +
                escapeHtml(notes || '—') + '</small></td></tr>';
        }).join('');
        applyMatchFilter();
        if (autoSwitch) switchResultTab('matching');
    }

    function applyMatchFilter() {
        $('matchBody').querySelectorAll('tr').forEach(tr => {
            tr.classList.toggle('hidden', matchFilter !== 'all' && tr.dataset.status !== matchFilter);
        });
    }

    $('matchFilters').addEventListener('click', e => {
        const btn = e.target.closest('.filter-btn');
        if (!btn) return;
        document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        matchFilter = btn.dataset.filter;
        applyMatchFilter();
    });

    async function apiPost(action, body, timeoutMs) {
        const ctrl = new AbortController();
        const deadline = timeoutMs || 120000;
        const t = setTimeout(() => ctrl.abort(), deadline);
        try {
            const res = await fetch(SCRAPER_API, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({ action, ...body }),
                signal: ctrl.signal,
            });
            const raw = await res.text();
            let json;
            try {
                json = JSON.parse(raw);
            } catch (_) {
                throw new Error(res.ok ? 'Răspuns invalid de la scraper.' : ('HTTP ' + res.status + ' — scraper indisponibil'));
            }
            if (!res.ok || json.success === false) throw new Error(json.message || 'HTTP ' + res.status);
            return json;
        } catch (e) {
            if (e.name === 'AbortError') {
                throw new Error('Timeout scraper (' + Math.round(deadline / 1000) + 's) — sursa poate fi lentă; reîncearcă.');
            }
            throw e;
        } finally { clearTimeout(t); }
    }

    function pickImageUrl(item) { return item.image || item.image_url || item.pImages || ''; }
    function getItemScore(item) { const s = Number(item.relevance_score ?? 0); return Number.isFinite(s) ? s : 0; }

    function setCardLog(i, msg) { const el = document.getElementById('log-' + i); if (el) el.textContent = msg; }

    async function scrapeForCard(index) {
        const card = cards[index];
        if (!card || cardHasImage(card)) return;
        const btn = document.querySelector('[data-scrape="' + index + '"]');
        if (btn) btn.disabled = true;
        const profile = getSpeedProfile();
        const q = String(card.scrapeQuery || card.title || card.sku).trim();
        setCardLog(index, 'Căutare imagini…');
        try {
            const items = [];
            for (const src of profile.sources) {
                const res = await apiPost('orchestrate_search_step', {
                    query: q,
                    source_id: src.id,
                    limit: 2,
                    ollama_quality: !!P.ollamaQualityCheck,
                }, src.timeout);
                (res.data?.items || []).forEach(it => { if (pickImageUrl(it)) items.push(it); });
                if (items.length) break;
            }
            if (!items.length) { setCardLog(index, '0 imagini găsite.'); if (btn) btn.disabled = false; return; }
            const picked = items[0];
            card.scrapedImageUrl = pickImageUrl(picked);
            card.scrapedImageScore = getItemScore(picked);
            card.hasImage = true;
            setCardLog(index, P.ollamaQualityCheck ? 'Imagine găsită (verificată Ollama).' : 'Imagine găsită.');
            renderCards();
        } catch (e) {
            setCardLog(index, e.name === 'AbortError' ? 'Timeout' : e.message);
            if (btn) btn.disabled = false;
        }
    }

    async function scrapeAllMissing() {
        const indexes = cards.map((c,i) => cardHasImage(c) ? -1 : i).filter(i => i >= 0);
        if (!indexes.length) return;
        batchRunning = true;
        $('scrapeAllBtn').disabled = true;
        $('batchProgressWrap').hidden = false;
        for (let n = 0; n < indexes.length; n++) {
            $('batchProgressBar').style.width = Math.round(((n+1)/indexes.length)*100) + '%';
            await scrapeForCard(indexes[n]);
        }
        batchRunning = false;
        $('batchProgressWrap').hidden = true;
        renderCards();
    }

    async function stopCronAndClearCache() {
        if (!confirm('Stop total — oprește definitiv cronul și termină procesele active.\n\nContinuă?')) {
            return;
        }
        const el = $('cronStatus');
        el.hidden = false;
        el.className = 'status info';
        el.textContent = 'Stop total — opresc procese…';
        $('cronStopBtn').disabled = true;
        $('cronBtn').disabled = true;
        try {
            const fd = new FormData();
            fd.append('action', 'stop');
            fd.append('clear_cache', '0');
            const data = await fetchJson(CRON_CONTROL_API, { method: 'POST', body: fd });
            if (!data.success) throw new Error(data.error || 'Nu pot opri cron');
            cronWasRunning = false;
            cronPostFinishBusy = false;
            cardsJobBusy = false;
            el.className = 'status ok';
            el.textContent = data.message || 'Cron oprit definitiv.';
            if (data.progress) renderCronProgress(data.progress, []);
        } catch (e) {
            el.className = 'status error';
            el.textContent = e.message;
        } finally {
            $('cronStopBtn').disabled = false;
            $('cronBtn').disabled = false;
        }
    }

    async function setCronControl(action) {
        const fd = new FormData();
        fd.append('action', action);
        const data = await fetchJson(CRON_CONTROL_API, { method: 'POST', body: fd });
        if (!data.success) throw new Error(data.error || data.message || 'Control cron eșuat');
        const el = $('cronStatus');
        if (el) {
            el.hidden = false;
            el.className = 'status ok';
            el.textContent = data.message || ('Cron: ' + action);
        }
        return data;
    }

    async function loadShowcaseConfig() {
        try {
            const data = await fetchJson(SHOWCASE_CONFIG_API);
            const cfg = data?.config || {};
            showcaseTypes = [...(cfg.types || [])];
            if (!showcaseTypes.length) showcaseTypes = ['ulei', 'lichid', 'adeziv', 'baterie', 'bec', 'lubrifiant'];
            showcaseTypeDefs = Array.isArray(cfg.type_defs) ? cfg.type_defs : [];
            const enabled = Array.isArray(cfg.scan_enabled) ? cfg.scan_enabled : showcaseTypes;
            showcaseTypeScanEnabled = showcaseTypes.map(t => enabled.includes(t));
            showcaseActiveTypeIndex = 0;
            renderShowcaseTypesUI();
            const ragEl = $('showcaseRagStatus');
            const rag = data?.rag;
            if (ragEl && rag) {
                ragEl.textContent = rag.available
                    ? (' · RAG epiesa: ' + (rag.count || 0) + ' exemple')
                    : ' · RAG epiesa: lipsă (rulează build_showcase_epiesa_rag.php --write)';
            }
        } catch { /* optional */ }
    }

    function showcaseTypeLabel(type, index) {
        const key = String(type || '').trim().toLowerCase();
        const def = showcaseTypeDefs.find(d => String(d.key || '').toLowerCase() === key);
        if (def?.label) return def.label;
        return (type || '').trim() || ('Tip ' + (index + 1));
    }

    function showcaseTypeEpiesaHint(type) {
        const key = String(type || '').trim().toLowerCase();
        const def = showcaseTypeDefs.find(d => String(d.key || '').toLowerCase() === key);
        if (def?.epiesa_subcategory) return 'epiesa: ' + def.epiesa_subcategory;
        if (def?.epiesa_url) return def.epiesa_url;
        return '';
    }

    function collectAllShowcaseTypesFromUI() {
        syncShowcaseTypesFromInputs();
        return showcaseTypes.map(t => String(t || '').trim()).filter(Boolean);
    }

    function collectShowcaseScanTypesFromUI() {
        const types = [];
        document.querySelectorAll('#showcaseTypesPanels .showcase-type-panel').forEach((panel, i) => {
            const inp = panel.querySelector('.showcase-type-input');
            const cb = panel.querySelector('.showcase-type-scan-cb');
            const t = (inp?.value || '').trim();
            const enabled = cb ? cb.checked : (showcaseTypeScanEnabled[i] !== false);
            if (t && enabled) types.push(t);
        });
        return types;
    }

    function getVisibleShowcaseCardIndexes() {
        const filterActive = $('showcaseFilterActiveTabInput')?.checked === true;
        const activeType = (showcaseTypes[showcaseActiveTypeIndex] || '').trim().toLowerCase();
        const indexes = [];
        showcaseCards.forEach((card, index) => {
            if (filterActive && activeType) {
                const cardType = String(card.showcaseType || '').trim().toLowerCase();
                if (cardType !== activeType) return;
            }
            indexes.push(index);
        });
        return indexes;
    }

    function setShowcaseScanEnabledOnly(index) {
        syncShowcaseTypesFromInputs();
        showcaseTypeScanEnabled = showcaseTypes.map((_, i) => i === index);
        renderShowcaseTypesUI();
    }

    function setShowcaseScanEnabledAll(enabled = true) {
        syncShowcaseTypesFromInputs();
        showcaseTypeScanEnabled = showcaseTypes.map(() => enabled);
        renderShowcaseTypesUI();
    }

    function syncShowcaseTypesFromInputs() {
        const inputs = document.querySelectorAll('#showcaseTypesPanels .showcase-type-input');
        inputs.forEach((inp, i) => {
            if (showcaseTypes[i] !== undefined) showcaseTypes[i] = inp.value;
        });
    }

    function switchShowcaseTypeTab(index) {
        syncShowcaseTypesFromInputs();
        showcaseActiveTypeIndex = index;
        renderShowcaseTypesUI();
        const panel = document.querySelector('#showcaseTypesPanels .showcase-type-panel.active .showcase-type-input');
        if (panel) panel.focus();
        if ($('showcaseFilterActiveTabInput')?.checked) renderShowcaseCards();
    }

    function addShowcaseType() {
        syncShowcaseTypesFromInputs();
        showcaseTypes.push('');
        showcaseTypeScanEnabled.push(true);
        showcaseActiveTypeIndex = showcaseTypes.length - 1;
        renderShowcaseTypesUI();
        const panel = document.querySelector('#showcaseTypesPanels .showcase-type-panel.active .showcase-type-input');
        if (panel) panel.focus();
    }

    function removeShowcaseType(index) {
        syncShowcaseTypesFromInputs();
        if (showcaseTypes.length <= 1) {
            if (!confirm('Ștergi ultima secțiune? Lista de tipuri va fi goală până adaugi una nouă.')) return;
        } else {
            const label = (showcaseTypes[index] || '').trim() || ('secțiunea ' + (index + 1));
            if (!confirm('Ștergi tipul «' + label + '»?')) return;
        }
        showcaseTypes.splice(index, 1);
        showcaseTypeScanEnabled.splice(index, 1);
        if (showcaseActiveTypeIndex >= showcaseTypes.length) {
            showcaseActiveTypeIndex = Math.max(0, showcaseTypes.length - 1);
        }
        renderShowcaseTypesUI();
    }

    function renderShowcaseTypesUI() {
        const tabsEl = $('showcaseTypesTabs');
        const panelsEl = $('showcaseTypesPanels');
        if (!tabsEl || !panelsEl) return;

        tabsEl.innerHTML = '';
        panelsEl.innerHTML = '';

        if (!showcaseTypes.length) {
            panelsEl.innerHTML = `
                <div class="showcase-types-empty">
                    <p>Nu există tipuri configurate. Adaugă o secțiune pentru fiecare categorie de produs vitrină.</p>
                    <button type="button" class="btn btn-sm" id="showcaseAddFirstTypeBtn">+ Adaugă primul tip</button>
                </div>`;
            $('showcaseAddFirstTypeBtn')?.addEventListener('click', addShowcaseType);
            return;
        }

        showcaseTypes.forEach((type, index) => {
            const label = showcaseTypeLabel(type, index);
            const epiesaHint = showcaseTypeEpiesaHint(type);
            const active = index === showcaseActiveTypeIndex;
            const scanOn = showcaseTypeScanEnabled[index] !== false;
            tabsEl.insertAdjacentHTML('beforeend', `
                <button type="button" class="tab${active ? ' active' : ''}" role="tab"
                    aria-selected="${active ? 'true' : 'false'}" data-showcase-type-index="${index}"
                    title="${escapeHtml(epiesaHint)}">
                    ${escapeHtml(label)}${scanOn ? '' : ' <span style="opacity:.5">· off</span>'}
                </button>`);
            panelsEl.insertAdjacentHTML('beforeend', `
                <div class="showcase-type-panel${active ? ' active' : ''}" role="tabpanel" data-showcase-type-index="${index}">
                    <div class="ip-field">
                        <label for="showcaseTypeInput${index}">Tip vitrină (cheie matching)</label>
                        <input type="text" id="showcaseTypeInput${index}" class="showcase-type-input"
                            value="${escapeHtml(type)}" placeholder="ex: ulei, baterie, bec" autocomplete="off">
                        ${epiesaHint ? '<p class="hint" style="margin:6px 0 0;font-size:12px;color:#64748b">' + escapeHtml(epiesaHint) + '</p>' : ''}
                    </div>
                    <label class="showcase-type-scan-row">
                        <input type="checkbox" class="showcase-type-scan-cb" data-index="${index}"${scanOn ? ' checked' : ''}>
                        Include la scan (bifează doar tipurile pe care le cauți acum)
                    </label>
                    <div class="showcase-type-panel-actions">
                        <button type="button" class="btn btn-danger-outline btn-sm showcase-type-delete" data-index="${index}">
                            Șterge secțiunea
                        </button>
                    </div>
                </div>`);
        });

        tabsEl.insertAdjacentHTML('beforeend', `
            <button type="button" class="tab tab-add" id="showcaseAddTypeTab" title="Adaugă tip nou">+ Adaugă tip</button>`);

        tabsEl.querySelectorAll('[data-showcase-type-index]').forEach(btn => {
            btn.addEventListener('click', () => switchShowcaseTypeTab(parseInt(btn.dataset.showcaseTypeIndex, 10)));
        });
        panelsEl.querySelectorAll('.showcase-type-input').forEach(inp => {
            inp.addEventListener('input', () => {
                const idx = parseInt(inp.closest('.showcase-type-panel')?.dataset.showcaseTypeIndex || '0', 10);
                showcaseTypes[idx] = inp.value;
                const tabBtn = tabsEl.querySelector('[data-showcase-type-index="' + idx + '"]');
                if (tabBtn) {
                    const t = inp.value.trim();
                    tabBtn.textContent = t || ('Tip ' + (idx + 1));
                }
            });
        });
        panelsEl.querySelectorAll('.showcase-type-scan-cb').forEach(cb => {
            cb.addEventListener('change', () => {
                const idx = parseInt(cb.dataset.index || '0', 10);
                showcaseTypeScanEnabled[idx] = cb.checked;
                const tabBtn = tabsEl.querySelector('[data-showcase-type-index="' + idx + '"]');
                if (tabBtn) {
                    const t = (showcaseTypes[idx] || '').trim();
                    tabBtn.innerHTML = escapeHtml(t || ('Tip ' + (idx + 1)))
                        + (cb.checked ? '' : ' <span style="opacity:.5">· off</span>');
                }
            });
        });
        panelsEl.querySelectorAll('.showcase-type-delete').forEach(btn => {
            btn.addEventListener('click', () => removeShowcaseType(parseInt(btn.dataset.index, 10)));
        });
        $('showcaseAddTypeTab')?.addEventListener('click', addShowcaseType);
    }

    async function saveShowcaseTypes() {
        syncShowcaseTypesFromInputs();
        const types = collectAllShowcaseTypesFromUI();
        const scanEnabled = collectShowcaseScanTypesFromUI();
        if (!types.length) throw new Error('Adaugă cel puțin un tip produs.');
        const data = await fetchJson(SHOWCASE_CONFIG_API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                types,
                scan_enabled: scanEnabled.length ? scanEnabled : types,
                match_min_score: getShowcaseMinScore(),
            }),
        });
        if (!data.success) throw new Error(data.error || 'Salvare eșuată');
        showcaseTypes = [...(data.config?.types || types)];
        showcaseTypeDefs = Array.isArray(data.config?.type_defs) ? data.config.type_defs : showcaseTypeDefs;
        const enabled = Array.isArray(data.config?.scan_enabled) ? data.config.scan_enabled : scanEnabled;
        showcaseTypeScanEnabled = showcaseTypes.map(t => enabled.includes(t));
        renderShowcaseTypesUI();
        setShowcaseStatus('Tipuri vitrină salvate (' + showcaseTypes.length + ' tipuri, scan: ' + enabled.join(', ') + ')', 'ok');
    }

    async function prepareShowcaseCardsForDisplay(list) {
        // Vitrină: afișăm toate potrivirile (cu sau fără imagine); scraping separat
        return (list || []).slice();
    }

    function setShowcaseCardLog(i, msg) {
        const el = document.getElementById('showcase-log-' + i);
        if (el) el.textContent = msg;
    }

    function updateShowcaseScrapeAllBtn() {
        const btn = $('showcaseScrapeAllBtn');
        if (!btn) return;
        const withoutImg = showcaseCards.filter(c => !cardHasImage(c)).length;
        if (showcaseBatchRunning) {
            btn.textContent = 'Oprește scraping';
            btn.disabled = false;
            btn.classList.remove('btn-amber');
            btn.classList.add('btn-danger-outline');
            return;
        }
        btn.textContent = 'Scraping — toate fără imagine';
        btn.classList.remove('btn-danger-outline');
        btn.classList.add('btn-amber');
        btn.disabled = withoutImg === 0;
    }

    function renderShowcaseCards() {
        const grid = $('showcaseGrid');
        const empty = $('showcaseEmpty');
        if (!grid) return;
        grid.innerHTML = '';

        const filterActive = $('showcaseFilterActiveTabInput')?.checked === true;
        const activeType = (showcaseTypes[showcaseActiveTypeIndex] || '').trim().toLowerCase();
        const indexMap = [];
        const cardsToShow = [];
        showcaseCards.forEach((card, index) => {
            if (filterActive && activeType) {
                const cardType = String(card.showcaseType || '').trim().toLowerCase();
                if (cardType !== activeType) return;
            }
            indexMap.push(index);
            cardsToShow.push(card);
        });

        selectedShowcaseIndexes = new Set([...selectedShowcaseIndexes].filter(i => indexMap.includes(i)));
        if (!cardsToShow.length) {
            if (empty) {
                empty.hidden = false;
                empty.textContent = filterActive && activeType
                    ? 'Niciun rezultat pentru tipul «' + activeType + '». Debifează filtrul sau scanează cu acest tip bifat.'
                    : 'Bifează fișiere CSV mai sus, configurează tipurile și apasă „Scanează produse-vitrină”.';
            }
            const filterNote = filterActive && activeType ? ' · filtru: ' + activeType : '';
            $('showcaseSummary').textContent = '0 rezultate vitrină' + filterNote;
            updateShowcaseScrapeAllBtn();
            updateShowcaseStageBtn();
            return;
        }
        if (empty) empty.hidden = true;
        let withImg = 0, withoutImg = 0;
        cardsToShow.forEach((card, viewIndex) => {
            const index = indexMap[viewIndex];
            const hasImg = cardHasImage(card);
            if (hasImg) withImg++;
            else withoutImg++;
            const imageSrc = hasImg ? getProductImageUrl(card) : '';
            let statusBadge = '';
            if (card.matchStatus === 'exact') statusBadge = '<span class="badge badge-exact">Match exact</span>';
            else if (card.matchStatus === 'probable') statusBadge = '<span class="badge badge-probable">Match probabil</span>';
            else if (card.scrapedImageUrl || card.scrapedImagePath) {
                statusBadge = '<span class="badge badge-scraped">Scraper ' + (card.scrapedImageScore ?? '') + '%</span>';
            } else if (card.hasImage) {
                statusBadge = '<span class="badge badge-poze">' + escapeHtml(card.imageSource || 'poze') + '</span>';
            } else if (!hasImg) {
                statusBadge = '<span class="badge badge-missing">Fără imagine</span>';
            }
            const methodTag = card.showcaseMatchMethod === 'ollama' ? ' · Ollama' : '';
            const badge = '<span class="badge badge-scraped">' + escapeHtml(card.showcaseType || 'vitrină')
                + ' ' + (card.showcaseScore || '—') + '%' + methodTag + '</span>' + statusBadge;
            const imageBlock = hasImg
                ? '<img class="product-card-image" src="' + escapeHtml(imageSrc) + '" alt="" loading="lazy" onerror="onCardImageError(this)">'
                : cardImagePlaceholderHtml(card);
            const scrapeBtn = hasImg ? '' : '<button type="button" class="btn btn-green btn-sm" data-showcase-scrape="' + index + '">Scraping</button>';
            const checked = selectedShowcaseIndexes.has(index) ? ' checked' : '';
            const paramsHtml = renderCardParametersBlock(card);
            const termsHtml = renderCardTermsBlock(card);
            const descHtml = renderCardDescriptionBlock(card);
            const imgMeta = hasImg ? ('Img: ' + escapeHtml(card.imageSource || card.scrapedImageSource || 'poze')) : '';
            grid.insertAdjacentHTML('beforeend', `
                <article class="product-card ${hasImg ? '' : 'no-image'}" data-showcase-index="${index}">
                    <label class="product-card-select" style="display:flex;align-items:center;gap:8px;margin-bottom:8px">
                        <input type="checkbox" class="showcase-select-cb" data-index="${index}"${checked}>
                        <span>${escapeHtml(card.showcaseType || 'vitrină')} (${card.showcaseScore || '—'}%)</span>
                    </label>
                    <div class="product-card-image-wrap">${badge}${imageBlock}</div>
                    <div class="product-card-body">
                        <h4 class="product-card-title">${escapeHtml(card.title || '')}</h4>
                        <div class="meta">
                            <span>SKU: ${escapeHtml(card.sku || '')}</span>
                            <span>${escapeHtml(card.brand || '')}</span>
                            <span>${escapeHtml(card.supplier || card.sourceSupplier || '—')}</span>
                            ${imgMeta ? '<span>' + imgMeta + '</span>' : ''}
                            ${card.sourceFile ? '<span>' + escapeHtml(card.sourceFile) + '</span>' : ''}
                        </div>
                        ${renderCardPriceBlock(card)}
                        ${paramsHtml}
                        ${termsHtml}
                        ${descHtml}
                        ${renderTecdocAuditBlock(card.tecdocAudit)}
                        <div class="card-actions">${scrapeBtn}</div>
                        <div class="scrape-log" id="showcase-log-${index}"></div>
                    </div>
                </article>`);
        });
        const filterNote = ($('showcaseFilterActiveTabInput')?.checked && activeType)
            ? (' · filtru tab: ' + activeType)
            : '';
        const selectedHint = selectedShowcaseIndexes.size > 0
            ? ' · ' + selectedShowcaseIndexes.size + ' selectate'
            : '';
        $('showcaseSummary').textContent = cardsToShow.length + ' afișate'
            + (filterActive && showcaseCards.length !== cardsToShow.length
                ? (' din ' + showcaseCards.length + ' total')
                : '')
            + ' · ' + withImg + ' cu imagine · ' + withoutImg + ' fără'
            + filterNote + selectedHint;
        grid.querySelectorAll('[data-showcase-scrape]').forEach(btn => {
            btn.addEventListener('click', () => scrapeForShowcaseCard(parseInt(btn.dataset.showcaseScrape, 10)));
        });
        grid.querySelectorAll('.showcase-select-cb').forEach(cb => {
            cb.addEventListener('change', ev => {
                const idx = parseInt(ev.target.dataset.index, 10);
                if (ev.target.checked) selectedShowcaseIndexes.add(idx);
                else selectedShowcaseIndexes.delete(idx);
                updateShowcaseStageBtn();
                const allCb = $('showcaseSelectAllCheckbox');
                const visible = getVisibleShowcaseCardIndexes();
                if (allCb) {
                    allCb.checked = visible.length > 0 && visible.every(i => selectedShowcaseIndexes.has(i));
                }
            });
        });
        updateShowcaseStageBtn();
        updateShowcaseScrapeAllBtn();
    }

    async function persistScrapedShowcaseImage(card, imageUrl) {
        if (!imageUrl || !SAVE_SCRAPED_IMAGE_API) return null;
        const data = await fetchJson(SAVE_SCRAPED_IMAGE_API, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ imageUrl, card: slimCardForStaging(card) }),
            timeoutMs: 60000,
        });
        if (!data?.success || !data?.data) {
            throw new Error(data?.error || 'Nu pot salva imaginea în Poze');
        }
        return data.data;
    }

    async function scrapeForShowcaseCard(index) {
        const card = showcaseCards[index];
        if (!card || cardHasImage(card)) return;
        const btn = document.querySelector('[data-showcase-scrape="' + index + '"]');
        if (btn) btn.disabled = true;
        const profile = getSpeedProfile();
        const perSource = Math.max(1, Math.min(10, parseInt($('showcaseScrapeLimitInput')?.value, 10) || 2));
        const q = String(card.scrapeQuery || card.title || card.sku).trim();
        setShowcaseCardLog(index, 'Căutare imagini…');
        try {
            const items = [];
            for (const src of profile.sources) {
                const res = await apiPost('orchestrate_search_step', {
                    query: q,
                    source_id: src.id,
                    limit: perSource,
                    ollama_quality: !!P.ollamaQualityCheck,
                }, src.timeout);
                (res.data?.items || []).forEach(it => { if (pickImageUrl(it)) items.push(it); });
                if (items.length) break;
            }
            if (!items.length) {
                setShowcaseCardLog(index, '0 imagini găsite.');
                if (btn) btn.disabled = false;
                return;
            }
            const picked = items[0];
            const imageUrl = pickImageUrl(picked);
            setShowcaseCardLog(index, 'Salvez imagine în Poze…');
            const saved = await persistScrapedShowcaseImage(card, imageUrl);
            card.scrapedImageUrl = saved?.displayUrl || imageUrl;
            card.scrapedImagePath = saved?.relativePath || card.scrapedImagePath || '';
            card.imageDisplayUrl = saved?.displayUrl || card.scrapedImageUrl;
            card.imageUrl = card.imageDisplayUrl;
            card.scrapedImageScore = getItemScore(picked);
            card.scrapedImageSource = picked?.source || 'scraper';
            card.imageSource = 'import_pro';
            card.hasImage = true;
            setShowcaseCardLog(index, 'Imagine salvată local.');
            renderShowcaseCards();
        } catch (e) {
            setShowcaseCardLog(index, e.name === 'AbortError' ? 'Timeout' : e.message);
            if (btn) btn.disabled = false;
        }
    }

    async function scrapeAllShowcaseMissing() {
        const indexes = showcaseCards.map((c, i) => cardHasImage(c) ? -1 : i).filter(i => i >= 0);
        if (!indexes.length) return;
        showcaseScrapeCancelRequested = false;
        showcaseBatchRunning = true;
        updateShowcaseScrapeAllBtn();
        setShowcaseStatus('Scraping imagini pentru ' + indexes.length + ' produse vitrină…', 'info');
        let processed = 0;
        for (let n = 0; n < indexes.length; n++) {
            if (showcaseScrapeCancelRequested) {
                setShowcaseStatus('Scraping oprit — ' + processed + '/' + indexes.length + ' procesate.', 'warn');
                break;
            }
            setShowcaseStatus('Scraping ' + (n + 1) + '/' + indexes.length + '…', 'info');
            await scrapeForShowcaseCard(indexes[n]);
            processed++;
        }
        showcaseBatchRunning = false;
        showcaseScrapeCancelRequested = false;
        if (processed === indexes.length) {
            const withImg = showcaseCards.filter(c => cardHasImage(c)).length;
            setShowcaseStatus('Scraping finalizat — ' + withImg + '/' + showcaseCards.length + ' cu imagine.', 'ok');
        }
        updateShowcaseScrapeAllBtn();
        renderShowcaseCards();
    }

    async function scanShowcaseProducts() {
        const files = requireShowcaseSelectedFiles();
        if (!files) return;
        syncShowcaseTypesFromInputs();
        const types = collectShowcaseScanTypesFromUI();
        if (!types.length) {
            setShowcaseStatus('Bifează «Include la scan» pe cel puțin un tip (ex. doar ulei) sau apasă «Doar tab activ».', 'error');
            return;
        }
        const scanBtn = $('showcaseScanBtn');
        const stageBtn = $('showcaseStageBtn');
        if (scanBtn) scanBtn.disabled = true;
        if (stageBtn) stageBtn.disabled = true;

        const limit = getShowcaseLimit();
        const minScore = getShowcaseMinScore();
        const parallel = getShowcaseParallelism();
        const typesLabel = types.join(', ');
        const useOllamaSemantic = $('showcaseOllamaSemanticInput')?.checked === true;
        const effectiveParallel = useOllamaSemantic ? 1 : parallel;
        if (useOllamaSemantic && parallel > 1) {
            setShowcaseStatus('Ollama activ — rulez 1 fișier în paralel (analiză titlu/descriere/imagine).', 'warn');
        }

        showcaseScanBeginProgress(files.length);
        setShowcaseStatus(
            'Scanare vitrină: ' + files.length + ' fișier(e), ' + effectiveParallel + ' în paralel, tipuri [' + typesLabel + '], '
            + limit + ' produse/fișier'
            + (useOllamaSemantic ? ', Ollama: titlu+descriere+specs+imagine' : ', doar reguli locale'),
            'info'
        );

        let mergedCards = [];
        let errors = 0;
        let completed = 0;

        async function scanOneFile(file) {
            try {
                showcaseScanAppendLog(
                    '▶ ' + file.supplier + '/' + file.filename + ' — scan pornit…',
                    '',
                    true
                );

                const data = await fetchJson(SHOWCASE_SCAN_API, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        files: [file],
                        types,
                        limit,
                        offset: 0,
                        match_min_score: minScore,
                        per_file: true,
                        use_ollama: useOllamaSemantic,
                    }),
                    timeoutMs: 600000,
                });

                if (!data || data.success !== true) {
                    const fr = (data?.file_reports && data.file_reports[0]) ? data.file_reports[0] : null;
                    throw new Error(String(
                        data?.error
                        || data?.message
                        || fr?.message
                        || 'Scan vitrină eșuat — posibil timeout server (scanul poate dura 2–4 min/fișier cu Ollama). '
                            + 'Reduce tipurile vitrină, limita/fișier sau dezactivează Ollama temporar.'
                    ));
                }

                if (Array.isArray(data.scan_log)) {
                    data.scan_log.forEach(entry => {
                        if (!entry || !entry.message) return;
                        const cls = entry.event === 'scan_exception' || entry.event === 'file_missing' ? 'is-error'
                            : (entry.event === 'limit_reached' ? 'is-warn' : 'is-ok');
                        showcaseScanAppendLog(
                            (cls === 'is-error' ? '✗ ' : (cls === 'is-warn' ? '○ ' : '· '))
                                + (entry.supplier ? entry.supplier + '/' + (entry.filename || '') + ' — ' : '')
                                + entry.message
                                + (entry.duration_ms ? ' (' + (entry.duration_ms / 1000).toFixed(1) + 's)' : ''),
                            cls
                        );
                    });
                }

                const report = (data.file_reports && data.file_reports[0]) ? data.file_reports[0] : null;
                if (report) {
                    showcaseScanFinalizeLog(report);
                } else {
                    showcaseScanAppendLog('✓ ' + file.supplier + '/' + file.filename + ' — răspuns primit', 'is-ok');
                }

                const batch = (data.cards || []).slice(0, limit);
                completed++;
                showcaseScanSetProgress(
                    completed,
                    files.length,
                    file,
                    (report?.message || (batch.length + ' produse vitrină'))
                        + ' · ' + completed + '/' + files.length + ' fișiere'
                );

                return { file, batch, report, error: null };
            } catch (e) {
                completed++;
                showcaseScanAppendLog('✗ ' + file.supplier + '/' + file.filename + ': ' + e.message, 'is-error');
                showcaseScanSetProgress(completed, files.length, file, 'Eroare — ' + e.message);
                return { file, batch: [], report: { status: 'error' }, error: e };
            }
        }

        try {
            const results = await runTasksWithConcurrency(files, effectiveParallel, scanOneFile);
            let ollamaUsed = 0;
            let ollamaMissing = 0;
            results.forEach(entry => {
                if (entry?.error || entry?.report?.status === 'error') errors++;
                if (entry?.report?.ollama_used) ollamaUsed++;
                if (entry?.report?.ollama_requested && entry?.report?.ollama_available === false) ollamaMissing++;
                if (!entry?.batch?.length) return;
                mergedCards = showcaseScanMergeCards(mergedCards, entry.batch);
            });

            showcaseScanSetProgress(files.length, files.length, null, 'Finalizez afișarea cardurilor…');
            if ($('showcaseScanProgressBar')) $('showcaseScanProgressBar').style.width = '100%';
            if ($('showcaseScanProgressPct')) $('showcaseScanProgressPct').textContent = '100%';

            setShowcaseStatus('Procesez ' + mergedCards.length + ' produse găsite pentru afișare…', 'info');
            showcaseCards = await prepareShowcaseCardsForDisplay(mergedCards);
            selectedShowcaseIndexes = new Set();
            renderShowcaseCards();

            const maxPossible = limit * files.length;
            const withImg = showcaseCards.filter(c => cardHasImage(c)).length;
            const withoutImg = showcaseCards.length - withImg;
            let msg = showcaseCards.length + ' produse vitrină · ' + withImg + ' cu imagine · ' + withoutImg + ' fără';
            if (ollamaUsed > 0) msg += ' · Ollama activ pe ' + ollamaUsed + ' fișier(e)';
            if (ollamaMissing > 0) msg += ' · Ollama indisponibil pe ' + ollamaMissing + ' fișier(e)';
            if (errors > 0) msg += ' · ' + errors + ' fișier(e) cu erori (vezi log)';
            if (showcaseCards.length < maxPossible && files.length > 1) {
                msg += ' (max ' + maxPossible + ' = ' + limit + '/fișier × ' + files.length + ' fișiere)';
            }
            setShowcaseStatus(msg, showcaseCards.length ? (errors || ollamaMissing ? 'warn' : 'ok') : 'warn');

            if ($('showcaseScanProgressFile')) {
                $('showcaseScanProgressFile').textContent = 'Finalizat — ' + showcaseCards.length + ' carduri din ' + files.length + ' fișier(e)';
            }

        } catch (e) {
            setShowcaseStatus(e.message, 'error');
            showcaseScanAppendLog('✗ EROARE: ' + e.message, 'is-error');
            if ($('showcaseScanProgressFile')) $('showcaseScanProgressFile').textContent = 'Scanare oprită — eroare';
            showcaseCards = [];
            renderShowcaseCards();
        } finally {
            showcaseScanEndProgress();
            if (scanBtn) scanBtn.disabled = false;
            if (stageBtn) stageBtn.disabled = showcaseCards.length === 0;
        }
    }

    async function runCron(mode) {
        const el = $('cronStatus');
        el.hidden = false;

        if (mode === 'cron' || mode === 'cron_test') {
            const isTest = mode === 'cron_test';
            requestCronNotifyPermission();
            resetScanUiState();
            resetCronLiveUiState();
            clearSelectionAfterScan();
            el.className = 'status info';
            el.textContent = isTest
                ? 'Test cron — caut 10 produse CU MATCH din CSV furnizori (scan + matching + staging)…'
                : 'Pornesc cron în background…';
            // Testul nu blochează butonul principal — poate fi lansat și cât cronul deja rulează.
            if (!isTest) $('cronBtn').disabled = true;
            if (isTest) $('demoBtn').disabled = true;
            try {
                const fd = new FormData();
                fd.append('force', '1');
                fd.append('batch_size', String(isTest ? 10 : getCronBatchSize()));
                fd.append('sample', String(isTest ? 10 : ($('cronSample')?.value || '20')));
                if (isTest) {
                    fd.append('total_limit', '10');
                    fd.append('test', '1');
                }
                const data = await fetchJson(CRON_START_API, { method: 'POST', body: fd });
                if (!data.success) throw new Error(data.error || 'Nu pot porni cron');
                el.className = 'status ok';
                el.textContent = isTest
                    ? (data.message || 'Test cron pornit — urmărește logul (10 produse total).')
                    : (data.message || 'Cron pornit — urmărește logul live.');
                cronWasRunning = true;
                startCronPoll(1500);
                await pollCronProgress();
            } catch (e) {
                el.className = 'status error';
                el.textContent = e.message;
            } finally {
                if (!isTest) $('cronBtn').disabled = false;
                if (isTest) $('demoBtn').disabled = false;
            }
            return;
        }

        const fd = new FormData();
        fd.append('mode', mode);
        fd.append('force', '1');
        el.className = 'status info';
        el.textContent = 'Rulez scan…';
        $('demoBtn').disabled = true;
        try {
            const data = await fetchJson(CRON_API, { method: 'POST', body: fd });
            if (!data.success) throw new Error(data.error || 'Eșuat');
            if (data.payload) {
                presentMatchResults({ success: true, payload: data.payload }, 'matching');
                el.className = 'status ok';
                el.textContent = 'Scan: ' + (data.payload.summary?.total ?? 0) + ' produse.';
            }
            loadReports();
            loadFilesLibrary();
            pollCronProgress();
        } catch (e) {
            el.className = 'status error';
            el.textContent = e.message;
        } finally {
            $('demoBtn').disabled = false;
        }
    }

    async function openReportById(id) {
        if (cardsJobBusy) return;
        const limit = getCronDisplayLimit();
        const d = await fetchJson(appendImportQuery(REPORTS_API,
            'id=' + encodeURIComponent(id) + '&sample=' + limit));
        if (!d.report) return;

        const supplier = d.report.supplier_key || d.report.supplier;
        const filename = d.report.file;
        if (supplier && filename && d.report.products?.length) {
            await loadCardsForFiles(
                [{ supplier, filename }],
                { fromCron: true, alsoMatch: false, reportPayload: d.report }
            );
            return;
        }

        if (d.report.products?.length) {
            presentMatchResults({ success: true, payload: d.report }, 'cards');
            if (d.report.note) setStatus(d.report.note, 'warn');
        }
    }

    async function openLatestReport(fromCronFinish) {
        try {
            const data = await fetchJson(appendImportQuery(REPORTS_API, 'limit=6'));
            renderReportsList(data.reports);
            const latest = data.reports?.[0];
            if (latest?.id) {
                await openReportById(latest.id);
            } else if (!fromCronFinish) {
                setStatus('Niciun raport disponibil.', 'warn');
            }
        } catch {
            $('reportsList').textContent = 'Nu pot încărca rapoartele.';
        }
    }

    function renderReportsList(reports) {
        const el = $('reportsList');
        if (!reports?.length) { el.textContent = 'Niciun raport — rulează cron sau matching manual.'; return; }
        el.innerHTML = '<ul style="margin:0;padding-left:18px">' + reports.map(r =>
            '<li style="margin:6px 0"><a href="#" class="link report-link" data-id="' + escapeHtml(r.id) + '">' +
            escapeHtml(r.file) + '</a> — ' + escapeHtml(r.supplier) + ' · ' + (r.summary?.total ?? 0) + ' produse' +
            (r.large ? ' · <em>fișier mare</em>' : '') + '</li>'
        ).join('') + '</ul>';
        el.querySelectorAll('.report-link').forEach(a => {
            a.addEventListener('click', async ev => {
                ev.preventDefault();
                await openReportById(a.dataset.id);
            });
        });
    }

    async function loadReports() {
        try {
            const data = await fetchJson(appendImportQuery(REPORTS_API, 'limit=6'));
            renderReportsList(data.reports);
        } catch { $('reportsList').textContent = 'Nu pot încărca rapoartele.'; }
    }

    function closeInspectModal() {
        $('inspectOverlay').classList.remove('open');
        $('inspectOverlay').hidden = true;
        inspectPayload = null;
        inspectContext = null;
    }

    const ALL_FIELDS = ['sku', 'ean', 'name', 'price', 'brand', 'stock'];
    const MATCH_FIELDS = ['sku', 'brand', 'ean', 'name'];
    const FIELD_LABELS = {
        sku: 'Cod OEM',
        ean: 'EAN',
        name: 'Denumire',
        price: 'Preț',
        brand: 'Producător',
        stock: 'Stoc',
    };
    const FIELD_MATCH_HINT = {
        sku: '→ product_codes.code_norm',
        brand: '→ brands.name (obligatoriu TecDoc)',
        ean: '→ products.art_ean',
        name: '→ doar afișare',
        price: '→ doar preț furnizor',
        stock: '→ doar stoc',
    };

    function isFieldActive(field, mapping, override) {
        const ovCols = override?.columns;
        if (ovCols && Object.prototype.hasOwnProperty.call(ovCols, field)) {
            return Array.isArray(ovCols[field]) && ovCols[field].length > 0;
        }
        const aliases = mapping[field]?.configured_aliases || [];
        return aliases.length > 0;
    }

    function renderLinkRow(field, mapping, header, override) {
        const m = mapping[field] || {};
        const selected = resolveColumn(m.configured_aliases, header);
        const active = isFieldActive(field, mapping, override);
        const isMatch = MATCH_FIELDS.includes(field);
        const tag = '<span class="field-tag ' + (isMatch ? 'match' : 'data') + '">' + (isMatch ? 'match' : 'date') + '</span>';
        return '<div class="inspect-link-row ' + (active ? '' : 'off') + (isMatch ? ' match-key' : ' data-only') + '" data-field-row="' + field + '">' +
            '<input type="checkbox" class="link-active-cb" data-field="' + field + '"' + (active ? ' checked' : '') + ' title="Activează/dezactivează legătura">' +
            '<label>' + FIELD_LABELS[field] + tag + '</label>' +
            '<select class="mapping-col-select" data-field="' + field + '"' + (active ? '' : ' disabled') + '>' +
            '<option value="">— fără legătură —</option>' +
            header.map(h => '<option value="' + escapeHtml(h) + '"' + (h === selected ? ' selected' : '') + '>' + escapeHtml(h) + '</option>').join('') +
            '</select>' +
            '<span class="sample link-sample" data-field="' + field + '">' + escapeHtml(FIELD_MATCH_HINT[field] || '') + '</span>' +
            '</div>';
    }

    function bindLinkRowEvents() {
        document.querySelectorAll('.link-active-cb').forEach(cb => {
            cb.addEventListener('change', () => {
                const field = cb.dataset.field;
                const row = document.querySelector('[data-field-row="' + field + '"]');
                const sel = document.querySelector('.mapping-col-select[data-field="' + field + '"]');
                if (!sel || !row) return;
                if (cb.checked) {
                    row.classList.remove('off');
                    sel.disabled = false;
                } else {
                    row.classList.add('off');
                    sel.disabled = true;
                    sel.value = '';
                }
                refreshLinkSamples();
            });
        });
        document.querySelectorAll('.mapping-col-select').forEach(sel => {
            sel.addEventListener('change', () => {
                const field = sel.dataset.field;
                const cb = document.querySelector('.link-active-cb[data-field="' + field + '"]');
                if (cb && sel.value && !cb.checked) {
                    cb.checked = true;
                    document.querySelector('[data-field-row="' + field + '"]')?.classList.remove('off');
                    sel.disabled = false;
                }
                refreshLinkSamples();
            });
        });
    }

    function normHeader(h) {
        return (h || '').toUpperCase().replace(/[^A-Z0-9]/g, '');
    }

    function resolveColumn(aliases, headers) {
        for (const alias of aliases || []) {
            const hit = headers.find(h => normHeader(h) === normHeader(alias));
            if (hit) return hit;
        }
        return '';
    }

    let inspectExtraCodes = [];

    function renderDbSources(sources, note) {
        if (!sources?.length) {
            $('inspectDbInfo').innerHTML = '<span class="hint">Surse DB indisponibile</span>';
            return;
        }
        $('inspectDbInfo').innerHTML = sources.map(s => {
            const rows = s.rows != null ? s.rows.toLocaleString('ro-RO') + ' rânduri' : '';
            const cols = (s.columns || []).map(c => '<code>' + escapeHtml(c) + '</code>').join(' · ');
            const usedIn = s.used_in ? '<div class="hint" style="margin-top:4px;color:#0d9488"><strong>Folosit:</strong> ' + escapeHtml(s.used_in) + '</div>' : '';
            const stats = s.stats && Object.keys(s.stats).length
                ? '<div class="hint">Detalii: ' + Object.entries(s.stats).map(([k, v]) => k + '=' + v).join(' · ') + '</div>'
                : '';
            return '<div class="inspect-db-item' + (s.active ? '' : ' inactive') + '">' +
                '<div><strong>' + escapeHtml(s.label) + '</strong> → tabel/index: <code>' + escapeHtml(s.table) + '</code>' +
                (rows ? ' · ' + rows : '') + '</div>' +
                '<div class="hint" style="margin-top:4px">Locație: <code>' + escapeHtml(s.path || '') + '</code></div>' +
                '<div class="hint">Coloane: ' + cols + ' · match: ' + escapeHtml(s.match_how || '') + '</div>' +
                usedIn + stats + '</div>';
        }).join('') + (note
            ? '<p class="hint" style="margin:8px 0 0;padding:8px;background:#f0fdfa;border-radius:8px">' + escapeHtml(note) + '</p>'
            : '');
    }

    function renderExtraCodesBlock(header, extraCodes) {
        inspectExtraCodes = [...(extraCodes || [])];
        const usedByFields = new Set();
        document.querySelectorAll('.link-active-cb').forEach(cb => {
            if (!cb.checked) return;
            const sel = document.querySelector('.mapping-col-select[data-field="' + cb.dataset.field + '"]');
            if (sel?.value) usedByFields.add(sel.value);
        });

        const tagsHtml = inspectExtraCodes.map(col =>
            '<span class="extra-code-tag" data-col="' + escapeHtml(col) + '">' + escapeHtml(col) +
            ' <button type="button" class="extra-rm" data-col="' + escapeHtml(col) + '">×</button></span>'
        ).join('');

        const available = header.filter(h => !usedByFields.has(h) && !inspectExtraCodes.includes(h));

        return '<div class="inspect-extra-block">' +
            '<label>Coduri extra → <code>prices.code</code> / <code>catalog.by_code</code></label>' +
            '<div class="extra-code-tags" id="extraCodeTags">' + (tagsHtml || '<span class="hint">Niciun cod extra</span>') + '</div>' +
            '<div class="extra-code-add">' +
            '<select id="addExtraCodeSelect"><option value="">— coloană CSV —</option>' +
            available.map(h => '<option value="' + escapeHtml(h) + '">' + escapeHtml(h) + '</option>').join('') +
            '</select>' +
            '<button type="button" class="btn btn-sm btn-outline" id="addExtraCodeBtn">+</button>' +
            '</div></div>';
    }

    function bindExtraCodesEvents(header) {
        $('addExtraCodeBtn')?.addEventListener('click', () => {
            const sel = $('addExtraCodeSelect');
            const col = sel?.value;
            if (!col || inspectExtraCodes.includes(col)) return;
            inspectExtraCodes.push(col);
            const extraBlock = $('inspectLinks').querySelector('.inspect-extra-block');
            if (extraBlock) extraBlock.remove();
            $('inspectLinks').insertAdjacentHTML('beforeend', renderExtraCodesBlock(header, inspectExtraCodes));
            bindExtraCodesEvents(header);
            refreshCsvHighlights();
        });
        document.querySelectorAll('.extra-rm').forEach(btn => {
            btn.addEventListener('click', () => {
                inspectExtraCodes = inspectExtraCodes.filter(c => c !== btn.dataset.col);
                const extraBlock = $('inspectLinks').querySelector('.inspect-extra-block');
                if (extraBlock) extraBlock.remove();
                $('inspectLinks').insertAdjacentHTML('beforeend', renderExtraCodesBlock(header, inspectExtraCodes));
                bindExtraCodesEvents(header);
                refreshCsvHighlights();
            });
        });
    }

    function getMappedColumns() {
        const mapped = new Set(inspectExtraCodes);
        document.querySelectorAll('.link-active-cb').forEach(cb => {
            if (!cb.checked) return;
            const sel = document.querySelector('.mapping-col-select[data-field="' + cb.dataset.field + '"]');
            if (sel?.value) mapped.add(sel.value);
        });
        return mapped;
    }

    function refreshCsvHighlights() {
        const mapped = getMappedColumns();
        document.querySelectorAll('.inspect-csv-wrap th[data-col]').forEach(th => {
            th.classList.toggle('mapped', mapped.has(th.dataset.col));
        });
    }

    function refreshLinkSamples() {
        const raw = inspectPayload?.raw_sample || {};
        const header = raw.header || [];
        const firstRow = (raw.rows || [])[0] || [];
        ALL_FIELDS.forEach(field => {
            const cb = document.querySelector('.link-active-cb[data-field="' + field + '"]');
            const sel = document.querySelector('.mapping-col-select[data-field="' + field + '"]');
            const sampleEl = document.querySelector('.link-sample[data-field="' + field + '"]');
            if (!sampleEl || !sel) return;
            if (!cb?.checked) {
                sampleEl.textContent = '— dezactivat';
                sel.classList.remove('unmapped');
                return;
            }
            const col = sel.value;
            if (!col) {
                sampleEl.textContent = (FIELD_MATCH_HINT[field] || '') + ' — alege coloana';
                if (field === 'sku' || field === 'brand') sel.classList.add('unmapped');
                else sel.classList.remove('unmapped');
                return;
            }
            sel.classList.remove('unmapped');
            const idx = header.indexOf(col);
            sampleEl.textContent = (FIELD_MATCH_HINT[field] || '') + (col && idx >= 0 ? ' · ' + (firstRow[idx] ?? '').toString().slice(0, 28) : '');
        });
        refreshCsvHighlights();
    }

    function renderNormalizationIssues(issues, ollamaNote) {
        const el = $('inspectNormIssues');
        if (!el) return;
        const list = Array.isArray(issues) ? issues : [];
        if (!list.length) {
            el.innerHTML = '<p class="hint" style="margin:0">Nicio problemă detectată în eșantion.</p>';
            return;
        }
        let html = '';
        list.slice(0, 15).forEach(issue => {
            const sev = escapeHtml(issue.severity || 'info');
            const row = issue.row_num ? ' #' + issue.row_num : '';
            const ollamaTag = issue._ollama ? '<span class="ollama-tag">Ollama</span>' : '';
            html += '<div class="norm-issue ' + sev + '">' +
                '<div class="msg">' + escapeHtml(issue.message_ro || issue.message || '—') + ollamaTag + '</div>' +
                (issue.suggestion_ro ? '<div class="sug">' + escapeHtml(issue.suggestion_ro) + '</div>' : '') +
                (issue.try_codes && issue.try_codes.length
                    ? '<div class="sug">Încearcă: ' + escapeHtml(issue.try_codes.join(', ')) + '</div>' : '') +
                '</div>';
        });
        if (list.length > 15) {
            html += '<p class="hint">… și încă ' + (list.length - 15) + ' probleme</p>';
        }
        if (ollamaNote) {
            html += '<p class="hint" style="margin:6px 0 0"><strong>Ollama:</strong> ' + escapeHtml(ollamaNote) + '</p>';
        }
        el.innerHTML = html;
    }

    function renderInspectModal(payload, supplier, filename) {
        inspectPayload = payload;
        inspectContext = { supplier, filename };
        $('inspectTitle').textContent = supplier + ' / ' + filename;
        $('inspectLoading').hidden = true;
        $('inspectContent').hidden = false;

        const raw = payload.raw_sample || {};
        const header = raw.header || [];
        const rows = raw.rows || [];
        const mapping = payload.column_mapping || {};
        const matchCodeOnly = payload.match_code_only !== false;

        $('inspectMeta').innerHTML = [
            '<span><strong>' + escapeHtml(payload.supplier_label || payload.supplier) + '</strong></span>',
            '<span>delim: <code>' + escapeHtml(payload.delimiter || ';') + '</code></span>',
            matchCodeOnly ? '<span style="background:#ccfbf1">match: cod OEM</span>' : '<span style="background:#fef9c3">match: cod + denumire</span>',
            payload.mapping_override ? '<span style="background:#fef9c3">override</span>' : '',
            inspectOllamaAvailable === true
                ? '<span style="background:#ede9fe">Ollama: ' + escapeHtml(inspectOllamaModel || 'activ') + '</span>'
                : '<span style="background:#fee2e2">Ollama: indisponibil</span>',
        ].join('');

        renderNormalizationIssues(payload.normalization_issues || []);

        const mappedSet = new Set();
        Object.values(mapping).forEach(m => {
            if (m && m.source_column && m.source_column !== '(lipsă)') mappedSet.add(m.source_column);
        });

        let csvHtml = '<table><thead><tr>';
        header.forEach(h => {
            const cls = mappedSet.has(h) ? ' class="mapped"' : '';
            csvHtml += '<th data-col="' + escapeHtml(h) + '"' + cls + '>' + escapeHtml(h) + '</th>';
        });
        csvHtml += '</tr></thead><tbody>';
        rows.slice(0, 5).forEach(row => {
            csvHtml += '<tr>';
            header.forEach((_, i) => {
                csvHtml += '<td>' + escapeHtml((row[i] ?? '').toString().slice(0, 50)) + '</td>';
            });
            csvHtml += '</tr>';
        });
        csvHtml += '</tbody></table>';
        $('inspectCsvTable').innerHTML = csvHtml;

        const override = payload.mapping_override || null;

        let linksHtml =
            '<p class="hint" style="margin:0 0 8px;font-size:11px">Bifează ce legături sunt active. Debifat = câmp ignorat la parsare/match.</p>' +
            '<label class="inspect-code-only">' +
            '<input type="checkbox" id="matchCodeOnlyInput"' + (matchCodeOnly ? ' checked' : '') + '>' +
            'Fără match pe denumire (chiar dacă e legată)</label>' +
            '<div class="inspect-link-section">Legături câmpuri</div>';
        ALL_FIELDS.forEach(field => {
            linksHtml += renderLinkRow(field, mapping, header, override);
        });
        $('inspectLinks').innerHTML = linksHtml;
        $('inspectLinks').insertAdjacentHTML('beforeend', renderExtraCodesBlock(header, payload.extra_codes || mapping._meta?.extra_codes || []));

        renderDbSources(payload.data_sources || [], payload.data_sources_note);

        const inspected = (payload.rows_inspected || []).slice(0, 5);
        let prevHtml = '<table><thead><tr><th>#</th><th>Cod OEM</th><th>Denumire</th><th>Preț</th><th>Tabel DB</th><th>→ Match</th></tr></thead><tbody>';
        if (!inspected.length) {
            prevHtml += '<tr><td colspan="6">Niciun rând valid</td></tr>';
        } else {
            inspected.forEach(row => {
                const n = row.normalized || {};
                const match = row.match || {};
                const mp = match.matched_product;
                const dot = '<span class="match-dot ' + escapeHtml(match.status || 'no_match') + '"></span>';
                const matchTxt = mp
                    ? escapeHtml((mp.internal_sku || mp.name || '').toString().slice(0, 30))
                    : '—';
                const dbTbl = '<span class="db-col">' + escapeHtml(match.db_table || '—') + '</span>';
                const variants = (n.code_variants || n.codes || []).join(', ');
                const skuTitle = variants ? ' title="Variante: ' + escapeHtml(variants) + '"' : '';
                prevHtml += '<tr><td>' + row.row_num + '</td>' +
                    '<td' + skuTitle + '>' + escapeHtml((n.sku_supplier || '—').toString().slice(0, 20)) + '</td>' +
                    '<td>' + escapeHtml((n.name || '—').toString().slice(0, 28)) + '</td>' +
                    '<td>' + escapeHtml(String(n.price ?? '—')) + '</td>' +
                    '<td>' + dbTbl + '</td>' +
                    '<td>' + dot + matchTxt + '</td></tr>';
            });
        }
        prevHtml += '</tbody></table>';
        $('inspectPreview').innerHTML = prevHtml;

        bindLinkRowEvents();
        bindExtraCodesEvents(header);
        refreshLinkSamples();

        $('saveMappingBtn').onclick = saveMappingFromEditor;
        $('clearMappingBtn').onclick = clearMappingOverride;
        $('reloadInspectBtn').onclick = () => openInspectModal(supplier, filename);
        $('ollamaSuggestBtn').onclick = requestOllamaMapping;
    }

    function collectMappingFromEditor() {
        const columns = {};
        ALL_FIELDS.forEach(field => {
            const cb = document.querySelector('.link-active-cb[data-field="' + field + '"]');
            const sel = document.querySelector('.mapping-col-select[data-field="' + field + '"]');
            if (cb?.checked && sel?.value) {
                columns[field] = [sel.value];
            } else {
                columns[field] = [];
            }
        });
        return {
            columns,
            extra_codes: [...inspectExtraCodes],
            match_code_only: !!$('matchCodeOnlyInput')?.checked,
        };
    }

    async function saveMappingFromEditor() {
        if (!inspectContext) return;
        const { columns, extra_codes, match_code_only } = collectMappingFromEditor();
        const skuOn = (columns.sku || []).length > 0;
        if (!skuOn && !extra_codes.length) {
            setStatus('Activează Cod OEM (+ Producător pentru TecDoc) sau cod extra.', 'warn');
            return;
        }
        try {
            const data = await fetchJson(SAVE_MAPPING_API, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ supplier: inspectContext.supplier, columns, extra_codes, match_code_only }),
            });
            if (!data.success) throw new Error(data.error || 'Salvare eșuată');
            setStatus(data.message || 'Mapping salvat', 'ok');
            await openInspectModal(inspectContext.supplier, inspectContext.filename);
        } catch (e) {
            setStatus('Eroare salvare mapping: ' + e.message, 'error');
        }
    }

    async function clearMappingOverride() {
        if (!inspectContext) return;
        if (!confirm('Resetezi override-ul pentru ' + inspectContext.supplier + '?\nRevine la suppliers.json.')) return;
        try {
            const data = await fetchJson(SAVE_MAPPING_API, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ supplier: inspectContext.supplier, clear: true }),
            });
            if (!data.success) throw new Error(data.error || 'Reset eșuat');
            setStatus(data.message || 'Override șters', 'ok');
            await openInspectModal(inspectContext.supplier, inspectContext.filename);
        } catch (e) {
            setStatus('Eroare reset mapping: ' + e.message, 'error');
        }
    }

    let lastOllamaSuggestion = null;
    let inspectOllamaAvailable = null;
    let inspectOllamaModel = '';

    async function requestOllamaMapping() {
        if (!inspectPayload || !inspectContext) return;
        const statusEl = $('ollamaSuggestStatus');
        statusEl.textContent = 'Ollama analizează mapping + normalizare…';
        lastOllamaSuggestion = null;
        try {
            const raw = inspectPayload.raw_sample || {};
            const cm = inspectPayload.column_mapping || {};
            const currentColumns = {};
            ['sku', 'ean', 'name', 'price', 'stock', 'brand'].forEach(f => {
                if (cm[f]?.configured_aliases) currentColumns[f] = cm[f].configured_aliases;
            });
            const data = await fetchJson(MAPPING_OLLAMA_API, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    supplier: inspectContext.supplier,
                    filename: inspectContext.filename,
                    headers: raw.header || [],
                    current_columns: currentColumns,
                    sample_rows: raw.rows || [],
                    rows_inspected: inspectPayload.rows_inspected || [],
                    normalization_issues: inspectPayload.normalization_issues || [],
                    normalization_rules: inspectPayload.normalization_rules || '',
                    extra_codes: inspectExtraCodes || inspectPayload.extra_codes || [],
                    user_note: ($('inspectOllamaNote')?.value || '').trim(),
                }),
            });
            if (!data.success) throw new Error(data.error || 'Ollama eșuat');
            lastOllamaSuggestion = data.suggestion;
            inspectOllamaAvailable = data.ollama_available !== false;
            if (data.ollama_model) inspectOllamaModel = data.ollama_model;
            const headers = raw.header || [];
            if (data.suggestion?.columns) {
                Object.entries(data.suggestion.columns).forEach(([field, aliases]) => {
                    const cb = document.querySelector('.link-active-cb[data-field="' + field + '"]');
                    const sel = document.querySelector('.mapping-col-select[data-field="' + field + '"]');
                    if (!sel || !Array.isArray(aliases) || !aliases.length) return;
                    const col = resolveColumn(aliases, headers);
                    if (col) {
                        if (cb) cb.checked = true;
                        sel.value = col;
                    }
                });
                refreshLinkSamples();
            }
            if (Array.isArray(data.suggestion?.extra_codes) && data.suggestion.extra_codes.length) {
                data.suggestion.extra_codes.forEach(col => {
                    if (col && headers.includes(col) && !inspectExtraCodes.includes(col)) {
                        inspectExtraCodes.push(col);
                    }
                });
                const extraBlock = $('inspectLinks')?.querySelector('.inspect-extra-block');
                if (extraBlock) {
                    extraBlock.outerHTML = renderExtraCodesBlock(headers, inspectExtraCodes);
                    bindExtraCodesEvents(headers);
                }
            }
            const heuristic = (inspectPayload.normalization_issues || []).slice();
            const ollamaIssues = (data.suggestion?.normalization?.issues || []).map(i => ({
                ...i,
                _ollama: true,
            }));
            const merged = [...heuristic];
            ollamaIssues.forEach(oi => {
                const dup = merged.some(h =>
                    h.type === oi.type && h.row_num === oi.row_num && h.message_ro === oi.message_ro
                );
                if (!dup) merged.push(oi);
            });
            const normSummary = data.suggestion?.normalization?.summary_ro || '';
            const explain = [data.suggestion?.explanation_ro, normSummary].filter(Boolean).join(' · ');
            renderNormalizationIssues(merged, normSummary || null);
            if (data.suggestion?.warnings?.length) {
                statusEl.textContent = explain + ' Avertismente: ' + data.suggestion.warnings.join('; ');
            } else {
                statusEl.textContent = explain || 'Sugestie aplicată — verifică legăturile și Salvează.';
            }
        } catch (e) {
            statusEl.textContent = e.message;
        }
    }

    async function openInspectModal(supplier, filename) {
        const overlay = $('inspectOverlay');
        overlay.hidden = false;
        overlay.classList.add('open');
        $('inspectLoading').hidden = false;
        $('inspectLoading').textContent = 'Se încarcă datele din fișier… (mapping + preview matching)';
        $('inspectContent').hidden = true;
        $('inspectTitle').textContent = supplier + ' / ' + filename;
        $('ollamaSuggestStatus').textContent = '';
        try {
            const url = appendImportQuery(INSPECT_API,
                'supplier=' + encodeURIComponent(supplier)
                + '&filename=' + encodeURIComponent(filename) + '&sample=5');
            const data = await fetchJson(url, { timeoutMs: 300000 });
            if (!data.success || !data.payload) throw new Error(data.error || 'Inspect eșuat');
            inspectOllamaAvailable = data.ollama_available === true;
            inspectOllamaModel = data.ollama_model || '';
            renderInspectModal(data.payload, supplier, filename);
        } catch (e) {
            $('inspectLoading').textContent = 'Eroare: ' + e.message;
            $('inspectLoading').hidden = false;
            $('inspectContent').hidden = true;
        }
    }

    $('loadBtn').addEventListener('click', loadCards);
    $('loadFromStartBtn')?.addEventListener('click', loadCardsFromStart);
    $('cardsBuildProgressCancel')?.addEventListener('click', () => {
        if (cardsBuildAbortController) {
            cardsBuildAbortController.abort();
            cardsBuildProgressLog('Anulat de utilizator.');
            setStatus('Anulez generarea carduri…', 'warn');
            closeCardsBuildProgressModal(false, { immediate: true });
        }
    });
    $('cardsBuildProgressClose')?.addEventListener('click', () => {
        closeCardsBuildProgressModal(undefined, { immediate: true });
    });
    $('scanBtn').addEventListener('click', scanOnly);
    $('scanLogRefreshBtn')?.addEventListener('click', () => loadScanLog().catch(e => setStatus(e.message, 'error')));
    document.querySelectorAll('input[name="scanResumeMode"]').forEach(radio => {
        radio.addEventListener('change', () => {
            const manual = $('scanManualOffset');
            if (manual) manual.disabled = getScanResumeMode() !== 'manual';
            void updateScanResumeHint(getSelectedFiles());
        });
    });
    $('scanManualOffset')?.addEventListener('input', () => {
        if (getScanResumeMode() === 'manual') void updateScanResumeHint(getSelectedFiles());
    });
    $('uploadBtn').addEventListener('click', uploadToServer);
    $('uploadSupplier')?.addEventListener('change', updateUploadSupplierMeta);
    $('uploadSelectAllBtn')?.addEventListener('click', () => selectAllFiles(true));
    $('uploadSelectNoneBtn')?.addEventListener('click', () => selectAllFiles(false));
    $('uploadDeleteSelectedBtn')?.addEventListener('click', deleteSelectedFiles);
    $('uploadSelectAllCheckbox')?.addEventListener('change', ev => selectAllFiles(ev.target.checked));
    $('selectAllBtn').addEventListener('click', () => selectAllFiles(true));
    $('selectNoneBtn').addEventListener('click', () => selectAllFiles(false));
    $('selectAllCheckbox').addEventListener('change', ev => selectAllFiles(ev.target.checked));
    $('deleteSelectedBtn').addEventListener('click', deleteSelectedFiles);
    $('showEmptyFoldersInput')?.addEventListener('change', loadFilesLibrary);
    $('scrapeAllBtn').addEventListener('click', scrapeAllMissing);
    $('stageSelectedBtn')?.addEventListener('click', () => {
        void (async () => {
        const btn = $('stageSelectedBtn');
        const selectAll = $('cardsSelectAllCheckbox')?.checked;
        if (selectAll && selectedCardIndexes.size === 0 && cards.length) {
            selectAllLoadedProductCards();
        }
        if (!selectAll && selectedCardIndexes.size === 0) {
            const msg = 'Selectează cel puțin un card (bifează checkbox-ul de pe card).';
            setStageQueueStatus(msg, 'warn');
            showImportToast(msg, 'warn');
            return;
        }

        const prevLabel = btn ? btn.textContent : '';
        if (btn) {
            btn.disabled = true;
            btn.textContent = 'Se trimite…';
            btn.setAttribute('aria-busy', 'true');
        }
        try {
            // Nu mai așteptăm fillCardsToTargetLimit aici — blochează UI. Trimitem doar ce e deja selectat/încărcat.
            await yieldToUi();
            const picked = [...selectedCardIndexes].map(i => cards[i]).filter(Boolean);
            if (!picked.length) {
                const msg = 'Niciun card selectat pentru coadă.';
                setStageQueueStatus(msg, 'warn');
                showImportToast(msg, 'warn');
                return;
            }
            setStageQueueStatus('Se trimit ' + picked.length + ' produse în coada de import (loturi)…', 'info');
            showImportToast('Se trimit ' + picked.length + ' produse…', 'info');
            await yieldToUi();
            if (btn) btn.textContent = 'Se trimite 0/' + picked.length + '…';
            const data = await stageCardsToQueue(picked, 'standard', {
                onChunk: (done, total) => {
                    if (btn) btn.textContent = 'Se trimite ' + done + '/' + total + '…';
                },
            });
            const queued = Number(data.queued ?? picked.length);
            const received = Number(data.received ?? picked.length);
            const converted = Number(data.converted ?? queued);
            const updatedExisting = Number(data.updated_existing ?? 0);
            const stageErrors = Number(data.stage_errors ?? 0);
            const rejected = Array.isArray(data.rejected) ? data.rejected : [];
            const skipped = Number(data.skipped_staging ?? Math.max(0, received - converted));
            let msg;
            let type;
            if (queued < picked.length || skipped > 0 || stageErrors > 0) {
                const parts = ['Trimise ' + queued + '/' + picked.length + ' produse în coadă'];
                if (updatedExisting > 0) parts.push(updatedExisting + ' actualizate (erau deja pending)');
                if (skipped > 0) parts.push(skipped + ' respinse la validare');
                if (stageErrors > 0) parts.push(stageErrors + ' erori insert');
                if (rejected.length) {
                    const hint = rejected.slice(0, 2).map(r => (r.key || '?') + ': ' + (r.reason || '')).join(' · ');
                    if (hint) parts.push(hint);
                }
                parts.push('vezi /admin/importreview');
                msg = parts.join(' — ');
                type = queued > 0 ? 'warn' : 'error';
            } else {
                msg = (data.message || queued + ' produse trimise în coada de import.')
                    + (updatedExisting > 0 ? ' (' + updatedExisting + ' actualizate)' : '')
                    + ' — vezi /admin/importreview';
                type = 'ok';
            }
            setStageQueueStatus(msg, type);
            showImportToast(msg, type);
            clearProductCardSelectionUi();
        } catch (e) {
            const msg = 'Eroare coadă: ' + e.message;
            setStageQueueStatus(msg, 'error');
            showImportToast(msg, 'error');
        } finally {
            if (btn) {
                btn.disabled = false;
                btn.textContent = prevLabel || 'Trimite în coadă import';
                btn.removeAttribute('aria-busy');
            }
            updateStageSelectedBtn();
        }
        })();
    });
    $('cardsSelectAllCheckbox')?.addEventListener('change', ev => {
        void applyCardsSelectAll(!!ev.target.checked);
    });
    // Delegare — dacă listenerul direct e ratat, click pe label/checkbox tot funcționează
    document.getElementById('tab-cards')?.addEventListener('change', ev => {
        const t = ev.target;
        if (!(t instanceof HTMLInputElement) || t.id !== 'cardsSelectAllCheckbox') return;
        // Evită dublu-apel dacă listenerul direct a rulat deja în același tick
        if (t.dataset.selectAllBound === '1') return;
        void applyCardsSelectAll(!!t.checked);
    });
    if ($('cardsSelectAllCheckbox')) {
        $('cardsSelectAllCheckbox').dataset.selectAllBound = '1';
    }
    $('showcaseSaveTypesBtn')?.addEventListener('click', () => saveShowcaseTypes().catch(e => setShowcaseStatus(e.message, 'error')));
    $('showcaseScanOnlyActiveBtn')?.addEventListener('click', () => {
        setShowcaseScanEnabledOnly(showcaseActiveTypeIndex);
        setShowcaseStatus('Scanare setată doar pentru «' + (showcaseTypes[showcaseActiveTypeIndex] || 'tab activ') + '». Apasă Scanează.', 'info');
    });
    $('showcaseScanAllTypesBtn')?.addEventListener('click', () => {
        setShowcaseScanEnabledAll(true);
        setShowcaseStatus('Toate tipurile sunt bifate pentru scanare.', 'info');
    });
    $('showcaseFilterActiveTabInput')?.addEventListener('change', () => renderShowcaseCards());
    $('showcaseScanBtn')?.addEventListener('click', () => scanShowcaseProducts());
    $('showcaseScrapeAllBtn')?.addEventListener('click', () => {
        if (showcaseBatchRunning) {
            showcaseScrapeCancelRequested = true;
            setShowcaseStatus('Opresc scraping…', 'info');
            return;
        }
        scrapeAllShowcaseMissing().catch(e => setShowcaseStatus(e.message, 'error'));
    });
    $('showcaseSelectAllBtn')?.addEventListener('click', () => selectAllFiles(true));
    $('showcaseSelectNoneBtn')?.addEventListener('click', () => selectAllFiles(false));
    $('showcaseSelectAllCheckboxHead')?.addEventListener('change', ev => selectAllFiles(ev.target.checked));
    $('showcaseSelectAllCheckbox')?.addEventListener('change', ev => {
        const visible = getVisibleShowcaseCardIndexes();
        if (ev.target.checked) visible.forEach(i => selectedShowcaseIndexes.add(i));
        else visible.forEach(i => selectedShowcaseIndexes.delete(i));
        renderShowcaseCards();
    });
    $('showcaseStageBtn')?.addEventListener('click', () => {
        void (async () => {
        const btn = $('showcaseStageBtn');
        const picked = [...selectedShowcaseIndexes].map(i => showcaseCards[i]).filter(Boolean);
        if (!picked.length) {
            setShowcaseStatus('Selectează cel puțin un produs vitrină (bifează checkbox-ul).', 'warn');
            return;
        }
        const prev = btn ? btn.textContent : '';
        if (btn) {
            btn.disabled = true;
            btn.textContent = 'Se trimite 0/' + picked.length + '…';
        }
        try {
            setShowcaseStatus('Se trimit ' + picked.length + ' produse în coadă (loturi)…', 'info');
            await yieldToUi();
            const data = await stageCardsToQueue(picked, 'showcase', {
                onChunk: (done, total) => {
                    if (btn) btn.textContent = 'Se trimite ' + done + '/' + total + '…';
                    setShowcaseStatus('Se trimit ' + done + '/' + total + ' produse în coadă…', 'info');
                },
            });
            const queued = Number(data.queued ?? 0);
            const queuedShowcase = Number(data.queued_showcase ?? 0);
            const queuedNoImage = Number(data.queued_no_image ?? 0);
            let okMsg = data.message || (queued + ' produse trimise în coadă');
            okMsg += ' — verifică în admin/importreview: tab «Produse vitrină» (cu imagine) și «Produse fără imagine» (fără poză).';
            if (queuedNoImage > 0 && queuedShowcase === 0) {
                okMsg += ' Toate fără imagine → tab «Produse fără imagine»; rulează Scraping sau adaugă poză manual.';
            }
            setShowcaseStatus(okMsg, queued > 0 ? 'ok' : 'warn');
            selectedShowcaseIndexes = new Set();
            document.querySelectorAll('#showcaseGrid .showcase-select-cb:checked').forEach(cb => {
                cb.checked = false;
            });
            const scb = $('showcaseSelectAllCheckbox');
            if (scb) scb.checked = false;
            updateShowcaseStageBtn();
        } catch (e) {
            setShowcaseStatus('Eroare vitrină: ' + e.message, 'error');
        } finally {
            if (btn) {
                btn.disabled = selectedShowcaseIndexes.size === 0;
                btn.textContent = prev || 'Trimite selectate în coadă (vitrină)';
            }
            updateShowcaseStageBtn();
        }
        })();
    });
    $('cronBtn').addEventListener('click', () => runCron('cron'));
    $('cronPauseBtn')?.addEventListener('click', () => setCronControl('pause').catch(e => setStatus(e.message, 'error')));
    document.querySelectorAll('[data-cron-filter]').forEach(btn => {
        btn.addEventListener('click', () => {
            cronScheduleFilter = btn.dataset.cronFilter || 'all';
            document.querySelectorAll('[data-cron-filter]').forEach(b => b.classList.toggle('active', b === btn));
            renderCronSuppliersSchedule({
                suppliers: cronSuppliersSchedule,
                due_count: $('cronDueNow')?.textContent,
                active_count: $('cronActiveSuppliers')?.textContent,
                server_time: cronScheduleServerTime ? new Date(cronScheduleServerTime).toISOString() : null,
            });
        });
    });
    $('cronResumeBtn')?.addEventListener('click', () => setCronControl('resume').catch(e => setStatus(e.message, 'error')));
    $('demoBtn').addEventListener('click', () => runCron('cron_test'));
    $('cronStopBtn').addEventListener('click', stopCronAndClearCache);
    document.querySelectorAll('#cronStagingFilters .filter-btn').forEach(btn => {
        btn.addEventListener('click', () => setCronStagingFilter(btn.dataset.stagingFilter || 'all'));
    });
    $('cronSample')?.addEventListener('input', updateCronLimitHint);
    $('inspectCloseBtn')?.addEventListener('click', closeInspectModal);
    $('inspectOverlay')?.addEventListener('click', ev => {
        if (ev.target === $('inspectOverlay')) closeInspectModal();
    });

    if (location.protocol === 'file:') {
        setStatus('Deschide prin http://besoiupieseimport.test/import/', 'warn');
    } else {
        updateCronLimitHint();
        restoreWorkflowTab();
        loadIndexStatus();
        loadFilesLibrary();
        loadReports();
        loadShowcaseConfig();
        loadScanLog();
        startAutoPoll();
    }
})();
</script>
<?php if ($importEmbedInline): ?>
</div>
<?php else: ?>
</body>
</html>
<?php endif; ?>
