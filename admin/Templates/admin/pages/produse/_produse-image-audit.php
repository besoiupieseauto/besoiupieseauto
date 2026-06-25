<?php
declare(strict_types=1);

/** Modal audit imagini — mod Cursor Composer 2.5 (fără OpenAI/Gemini în admin). */
$imageAuditMaxBatch = max(1, min(500, (int) ($imageAuditMaxBatch ?? $_ENV['IMAGE_AUDIT_MAX_BATCH'] ?? getenv('IMAGE_AUDIT_MAX_BATCH') ?: 100)));
?>
<style>
    .image-audit-modal {
        display: none;
        position: fixed;
        inset: 0;
        z-index: 100000;
        background: rgba(15, 23, 42, .62);
        backdrop-filter: blur(3px);
        padding: 16px;
    }
    .image-audit-modal.is-open { display: flex; align-items: center; justify-content: center; }
    .image-audit-modal__panel {
        width: min(960px, 100%);
        max-height: min(92vh, 920px);
        background: #fff;
        border-radius: 16px;
        box-shadow: 0 25px 80px rgba(0,0,0,.28);
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }
    .image-audit-modal__head {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 12px;
        padding: 14px 18px;
        border-bottom: 1px solid #e5e7eb;
        background: linear-gradient(180deg, #f5f3ff, #fff);
    }
    .image-audit-modal__head h3 { margin: 0; font-size: 16px; font-weight: 700; color: #0f172a; }
    .image-audit-modal__head p { margin: 4px 0 0; font-size: 12px; color: #64748b; }
    .image-audit-modal__body { padding: 16px 18px; overflow-y: auto; flex: 1; }
    .image-audit-progress {
        padding: 12px 14px;
        border-radius: 10px;
        background: #f5f3ff;
        border: 1px solid #ddd6fe;
        color: #5b21b6;
        font-size: 13px;
        margin-bottom: 14px;
        line-height: 1.5;
    }
    .image-audit-progress__text { margin: 0 0 10px; font-weight: 600; }
    .image-audit-progress__bar {
        height: 10px;
        border-radius: 999px;
        background: #ede9fe;
        overflow: hidden;
        margin-bottom: 8px;
    }
    .image-audit-progress__fill {
        height: 100%;
        width: 0%;
        border-radius: 999px;
        background: linear-gradient(90deg, #7c3aed, #1abc9c);
        transition: width .45s ease;
    }
    .image-audit-progress__meta {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        font-size: 12px;
        color: #6d28d9;
        opacity: .9;
    }
    .image-audit-progress.is-done {
        background: #ecfdf5;
        border-color: #a7f3d0;
        color: #065f46;
    }
    .image-audit-progress.is-done .image-audit-progress__meta { color: #047857; }
    .image-audit-progress.is-error {
        background: #fef2f2;
        border-color: #fecaca;
        color: #991b1b;
    }
    .image-audit-progress.is-error .image-audit-progress__meta { color: #b91c1c; }
    .image-audit-cursor-box {
        border: 1px solid #c4b5fd;
        background: #faf5ff;
        border-radius: 12px;
        padding: 14px;
        margin-bottom: 14px;
    }
    .image-audit-cursor-box ol {
        margin: 8px 0 0;
        padding-left: 20px;
        font-size: 13px;
        color: #334155;
        line-height: 1.55;
    }
    .image-audit-cursor-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 12px;
    }
    .image-audit-cursor-actions button {
        border: 1px solid #c4b5fd;
        background: #fff;
        color: #6d28d9;
        border-radius: 8px;
        padding: 8px 12px;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
    }
    .image-audit-cursor-actions button:hover { background: #ede9fe; }
    .image-audit-prompt {
        margin-top: 10px;
        width: 100%;
        min-height: 120px;
        font-family: ui-monospace, monospace;
        font-size: 11px;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 10px;
        resize: vertical;
        background: #fff;
        color: #334155;
    }
    .image-audit-card {
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 14px;
        margin-bottom: 12px;
        background: #fff;
    }
    .image-audit-card--pending { border-left: 4px solid #a78bfa; }
    .image-audit-card--match { border-left: 4px solid #16a34a; }
    .image-audit-card--partial { border-left: 4px solid #d97706; }
    .image-audit-card--mismatch,
    .image-audit-card--error,
    .image-audit-card--no_image { border-left: 4px solid #dc2626; }
    .image-audit-card--uncertain { border-left: 4px solid #64748b; }
    .image-audit-card__top { display: flex; gap: 14px; align-items: flex-start; }
    .image-audit-card__thumb {
        width: 96px;
        height: 96px;
        object-fit: cover;
        border-radius: 10px;
        border: 1px solid #e2e8f0;
        flex-shrink: 0;
        background: #f8fafc;
    }
    .image-audit-card__no-thumb {
        width: 96px;
        height: 96px;
        border-radius: 10px;
        border: 2px dashed #c4b5fd;
        background: #f5f3ff;
        color: #7c3aed;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        font-weight: 700;
        flex-shrink: 0;
    }
    .image-audit-card__title { font-weight: 700; font-size: 14px; color: #0f172a; margin: 0 0 6px; }
    .image-audit-card__meta { font-size: 12px; color: #64748b; margin: 0 0 8px; }
    .image-audit-badge {
        display: inline-flex;
        padding: 2px 8px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
    }
    .image-audit-badge--pending { background: #ede9fe; color: #6d28d9; }
    .image-audit-badge--match { background: #dcfce7; color: #166534; }
    .image-audit-badge--partial { background: #ffedd5; color: #9a3412; }
    .image-audit-badge--mismatch,
    .image-audit-badge--error,
    .image-audit-badge--no_image { background: #fee2e2; color: #991b1b; }
    .image-audit-card__section { margin-top: 10px; font-size: 13px; line-height: 1.5; color: #334155; }
    .product-card .image-audit-pill {
        position: absolute;
        top: 12px;
        right: 12px;
        z-index: 21;
        font-size: 10px;
        font-weight: 700;
        padding: 3px 7px;
        border-radius: 999px;
        background: rgba(255,255,255,.94);
        border: 1px solid rgba(15,23,42,.12);
    }
    .product-card .image-audit-pill--match { color: #166534; border-color: #86efac; }
    .product-card .image-audit-pill--partial { color: #9a3412; border-color: #fdba74; }
    .product-card .image-audit-pill--bad { color: #991b1b; border-color: #fca5a5; }
    .btn-audit-images {
        border-color: rgba(124, 58, 237, .35);
        background: #f5f3ff;
        color: #6d28d9;
    }
    .btn-audit-images:disabled { opacity: .45; cursor: not-allowed; }
    .image-audit-steps {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 8px;
        margin-bottom: 14px;
    }
    @media (max-width: 720px) {
        .image-audit-steps { grid-template-columns: 1fr; }
    }
    .image-audit-step {
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 10px 12px;
        background: #f8fafc;
        font-size: 11px;
        line-height: 1.45;
        color: #64748b;
        opacity: .55;
    }
    .image-audit-step strong {
        display: block;
        font-size: 12px;
        color: #334155;
        margin-bottom: 4px;
    }
    .image-audit-step.is-active {
        opacity: 1;
        border-color: #c4b5fd;
        background: #faf5ff;
        box-shadow: 0 0 0 1px #ede9fe;
    }
    .image-audit-step.is-done {
        opacity: 1;
        border-color: #86efac;
        background: #f0fdf4;
    }
    .image-audit-activity {
        font-size: 12px;
        color: #475569;
        margin: 0 0 10px;
        padding: 8px 10px;
        background: #fff;
        border-radius: 8px;
        border: 1px dashed #cbd5e1;
    }
    .image-audit-pipeline-steps {
        display: flex;
        flex-direction: column;
        gap: 6px;
        margin: 0 0 14px;
    }
    .image-audit-pipeline-step {
        display: flex;
        gap: 10px;
        align-items: flex-start;
        padding: 10px 12px;
        border-radius: 10px;
        border: 1px solid #e2e8f0;
        background: #f8fafc;
        font-size: 12px;
    }
    .image-audit-pipeline-step.is-running { border-color: #99f6e4; background: #f0fdfa; }
    .image-audit-pipeline-step.is-ok { border-color: #86efac; background: #f0fdf4; }
    .image-audit-pipeline-step.is-miss { border-color: #fde68a; background: #fffbeb; }
    .image-audit-pipeline-step.is-skipped { opacity: 0.65; }
    .image-audit-pipeline-step-icon { flex-shrink: 0; width: 1.25rem; text-align: center; }
    .image-audit-pipeline-step-title { font-weight: 600; color: #1e293b; }
    .image-audit-pipeline-step-msg { color: #64748b; margin-top: 2px; }
    .image-audit-pipeline-hit {
        border: 1px solid #86efac;
        background: #f0fdf4;
        border-radius: 12px;
        padding: 12px;
        margin-bottom: 14px;
        display: flex;
        gap: 14px;
        align-items: flex-start;
    }
    .image-audit-pipeline-hit img {
        width: 96px;
        height: 96px;
        object-fit: cover;
        border-radius: 10px;
        border: 1px solid #bbf7d0;
        flex-shrink: 0;
    }
    .image-audit-pipeline-hit-meta { font-size: 13px; line-height: 1.5; color: #334155; }
    .image-audit-toolbar {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-bottom: 12px;
    }
    .image-audit-toolbar button {
        border: 1px solid #c4b5fd;
        background: #fff;
        color: #6d28d9;
        border-radius: 8px;
        padding: 8px 12px;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
    }
    .image-audit-toolbar button:hover { background: #ede9fe; }
    .image-audit-log {
        max-height: 140px;
        overflow-y: auto;
        margin: 0 0 12px;
        padding: 8px 10px;
        background: #0f172a;
        border-radius: 10px;
        font-family: ui-monospace, Consolas, monospace;
        font-size: 11px;
        line-height: 1.45;
        color: #cbd5e1;
    }
    .image-audit-log-line { margin: 0 0 4px; }
    .image-audit-log-line:last-child { margin-bottom: 0; color: #5eead4; }
    .image-audit-noimage-banner {
        margin: 0 0 12px;
        padding: 10px 12px;
        border-radius: 10px;
        border: 1px solid #fcd34d;
        background: #fffbeb;
        color: #92400e;
        font-size: 12px;
        line-height: 1.5;
    }
    .image-audit-noimage-banner code {
        font-size: 11px;
        background: #fef3c7;
        padding: 1px 4px;
        border-radius: 4px;
    }
    .image-audit-toolbar button.is-pulse {
        animation: image-audit-pulse 1.2s ease-in-out infinite;
        border-color: #7c3aed;
        background: #ede9fe;
    }
    @keyframes image-audit-pulse {
        0%, 100% { box-shadow: 0 0 0 0 rgba(124, 58, 237, 0.35); }
        50% { box-shadow: 0 0 0 6px rgba(124, 58, 237, 0); }
    }
</style>

<div id="imageAuditModal" class="image-audit-modal" role="dialog" aria-modal="true" aria-labelledby="imageAuditModalTitle">
    <div class="image-audit-modal__panel">
        <div class="image-audit-modal__head">
            <div>
                <h3 id="imageAuditModalTitle">Audit imagini + Pipeline Plan 1→3</h3>
                <p>Același motor ca în Scraper → Pipeline imagini: audit vizual, apoi căutare Autodoc / ePiesa / TecDoc.</p>
            </div>
            <button type="button" id="imageAuditModalClose" class="products-modal-hover" style="border:1px solid #d1d5db;background:#fff;border-radius:10px;padding:8px 12px;font-size:12px;cursor:pointer;">Închide</button>
        </div>
        <div class="image-audit-modal__body" id="imageAuditModalBody">
            <div class="image-audit-steps" id="imageAuditSteps">
                <div class="image-audit-step is-active" data-step="audit">
                    <strong>Pas 1 — Audit vizual</strong>
                    Composer 2.5 compară imaginea cu titlul, codul și categoria. Primești scor 0–100 și verdict (match / mismatch).
                </div>
                <div class="image-audit-step" data-step="pipeline">
                    <strong>Pas 2 — Căutare imagine nouă</strong>
                    Dacă scorul e sub limita din Pipeline sau e mismatch, rulează Plan 1→N (doar sursele active din Scraper).
                    Căutare: <strong>titlu</strong>, apoi <strong>cod OEM</strong> (pCode, pOem sau extras din titlu).
                </div>
                <div class="image-audit-step" data-step="save">
                    <strong>Pas 3 — Salvare</strong>
                    Imaginea găsită se salvează în produs. Vezi verdictul și noua poză mai jos.
                </div>
            </div>
            <p class="image-audit-activity" id="imageAuditActivity">Pregătesc lotul de produse…</p>
            <div id="imageAuditNoImageBanner" class="image-audit-noimage-banner" hidden>
                <strong>Produse fără imagine reală în magazin.</strong>
                Pe lista admin toate arată același placeholder generic — în baza de date <code>pImages</code> e gol.
                Pas 2 caută automat imagini pe Autodoc / ePiesa / TecDoc.
            </div>
            <div class="image-audit-log" id="imageAuditLog" hidden aria-live="polite"></div>
            <div class="image-audit-progress" id="imageAuditProgress">
                <p class="image-audit-progress__text" id="imageAuditProgressText">Pregătire lot…</p>
                <div class="image-audit-progress__bar" aria-hidden="true">
                    <div class="image-audit-progress__fill" id="imageAuditProgressFill"></div>
                </div>
                <div class="image-audit-progress__meta">
                    <span id="imageAuditProgressCount">0 / 0 produse</span>
                    <span id="imageAuditProgressTime">0:00</span>
                </div>
            </div>
            <div class="image-audit-toolbar">
                <button type="button" id="imageAuditFindOnlyBtn">Caută imagine Plan 1→3</button>
            </div>
            <div id="imageAuditPipelineSteps" class="image-audit-pipeline-steps" hidden></div>
            <div id="imageAuditPipelineHit" class="image-audit-pipeline-hit" hidden></div>
            <div id="imageAuditCursorBox" class="image-audit-cursor-box" hidden>
                <strong style="font-size:13px;color:#5b21b6;">Mod manual (fără CURSOR_API_KEY)</strong>
                <ol>
                    <li>Adaugă <code>CURSOR_API_KEY</code> în <code>admin/.env</code> pentru apel automat</li>
                    <li>Sau deschide <strong>Cursor IDE</strong> → <strong>Composer 2.5</strong></li>
                    <li>Copiază promptul de mai jos și lipește în Composer</li>
                    <li>Apasă <strong>Încarcă rezultate Cursor</strong> după ce agentul salvează fișierele</li>
                </ol>
                <div class="image-audit-cursor-actions">
                    <button type="button" id="imageAuditCopyPrompt">Copiază prompt Cursor</button>
                    <button type="button" id="imageAuditReloadResults">Încarcă rezultate Cursor</button>
                </div>
                <textarea id="imageAuditPrompt" class="image-audit-prompt" readonly></textarea>
            </div>
            <div id="imageAuditResults"></div>
        </div>
    </div>
</div>

<script>
window.besoiuImageAudit = (function () {
    const endpoint = '/admin/crudproduse';
    const scraperApi = '/admin/api/scraper_endpoint.php';
    const modal = document.getElementById('imageAuditModal');
    const progress = document.getElementById('imageAuditProgress');
    const progressText = document.getElementById('imageAuditProgressText');
    const progressFill = document.getElementById('imageAuditProgressFill');
    const progressCount = document.getElementById('imageAuditProgressCount');
    const progressTime = document.getElementById('imageAuditProgressTime');
    const cursorBox = document.getElementById('imageAuditCursorBox');
    const promptEl = document.getElementById('imageAuditPrompt');
    const resultsEl = document.getElementById('imageAuditResults');
    const closeBtn = document.getElementById('imageAuditModalClose');
    const copyBtn = document.getElementById('imageAuditCopyPrompt');
    const reloadBtn = document.getElementById('imageAuditReloadResults');
    const activityEl = document.getElementById('imageAuditActivity');
    const activityLogEl = document.getElementById('imageAuditLog');
    const noImageBannerEl = document.getElementById('imageAuditNoImageBanner');
    const stepsEl = document.getElementById('imageAuditSteps');
    const pipelineStepsEl = document.getElementById('imageAuditPipelineSteps');
    const pipelineHitEl = document.getElementById('imageAuditPipelineHit');
    const findOnlyBtn = document.getElementById('imageAuditFindOnlyBtn');
    const AUDIT_MAX_BATCH = <?= (int) ($imageAuditMaxBatch ?? 100) ?>;
    const POLL_INTERVAL_MS = 20000;
    const POLL_FIRST_MS = 3000;

    let lastIds = [];
    let lastProducts = [];
    let lastAuditById = {};
    let lastPipelineById = {};
    let cachedPipelinePlans = null;
    let pollTimer = null;
    let elapsedTimer = null;
    let pollStartMs = 0;
    let activeJobId = '';
    let analyzingProductId = '';
    let pipelineRunning = false;
    let lastActivityLine = '';

    function sleep(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }

    function formatElapsed(sec) {
        const s = Math.max(0, Number(sec) || 0);
        const m = Math.floor(s / 60);
        const r = s % 60;
        return m + ':' + String(r).padStart(2, '0');
    }

    function stopPolling() {
        if (pollTimer) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
        if (elapsedTimer) {
            clearInterval(elapsedTimer);
            elapsedTimer = null;
        }
    }

    function setProgressState(state) {
        if (!progress) return;
        progress.classList.remove('is-done', 'is-error');
        if (state === 'done') progress.classList.add('is-done');
        if (state === 'error') progress.classList.add('is-error');
    }

    function setWorkflowStep(step) {
        if (!stepsEl) return;
        const order = ['audit', 'pipeline', 'save'];
        const idx = order.indexOf(step);
        stepsEl.querySelectorAll('.image-audit-step').forEach(el => {
            const s = el.dataset.step || '';
            const si = order.indexOf(s);
            el.classList.remove('is-active', 'is-done');
            if (si < idx) el.classList.add('is-done');
            else if (si === idx) el.classList.add('is-active');
        });
    }

    function setActivity(text) {
        if (activityEl) activityEl.textContent = text || '';
    }

    function appendActivityLog(line) {
        const msg = String(line || '').trim();
        if (!msg || msg === lastActivityLine) return;
        lastActivityLine = msg;
        if (!activityLogEl) return;
        activityLogEl.hidden = false;
        const t = new Date().toLocaleTimeString('ro-RO', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        activityLogEl.insertAdjacentHTML('beforeend', '<div class="image-audit-log-line">[' + t + '] ' + escapeHtml(msg) + '</div>');
        activityLogEl.scrollTop = activityLogEl.scrollHeight;
    }

    function clearActivityLog() {
        lastActivityLine = '';
        if (activityLogEl) {
            activityLogEl.innerHTML = '';
            activityLogEl.hidden = true;
        }
    }

    function updateNoImageBanner(products, results) {
        if (!noImageBannerEl) return;
        const list = products || lastProducts || [];
        const withoutImg = list.filter(p => !(p.image_url || '').trim()).length;
        const noImageVerdicts = (results || Object.values(lastAuditById)).filter(r => {
            return r && String(r.verdict || '').toLowerCase() === 'no_image';
        }).length;
        const show = withoutImg > 0 && (noImageVerdicts > 0 || withoutImg === list.length);
        noImageBannerEl.hidden = !show;
        if (show && findOnlyBtn && !pipelineRunning) {
            findOnlyBtn.classList.add('is-pulse');
            findOnlyBtn.textContent = 'Caută imagine Plan 1→3 (' + Math.max(noImageVerdicts, withoutImg) + ')';
        } else if (findOnlyBtn) {
            findOnlyBtn.classList.remove('is-pulse');
            findOnlyBtn.textContent = 'Caută imagine Plan 1→3';
        }
    }

    function setProgressLine(text) {
        if (!progressText) return;
        const line = String(text || '').replace(/\*\*/g, '').trim();
        progressText.textContent = line !== '' ? line : 'Procesez…';
    }

    function clearAnalyzing() {
        analyzingProductId = '';
    }

    function updateProgressUi(data) {
        const total = Number(data.total || lastProducts.length || 0);
        const done = Number(data.done || 0);
        const currentIndex = Number(data.current_index || 0);
        const displayDone = Number(data.display_done != null ? data.display_done : Math.max(done, currentIndex));
        const remaining = Math.max(0, total - Math.max(done, currentIndex));
        const percent = Math.max(0, Math.min(100, Number(data.percent || 0)));
        const phase = String(data.phase || data.message || '').replace(/\*\*/g, '');

        const workflow = data.workflow_step || 'audit';
        setWorkflowStep(workflow);

        if (data.activity) {
            setActivity(String(data.activity));
        } else if (workflow === 'pipeline') {
            setActivity('Caut imagini alternative în sursele configurate în Scraper → Pipeline imagini & import.');
        } else if (data.status === 'running' && currentIndex > 0) {
            setActivity('Cursor citește poza produsului și o compară cu denumirea din magazin (~20–40 sec/produs).');
        } else if (data.status === 'done') {
            setActivity('Audit terminat. Produsele cu mismatch trec la căutare automată de imagine (dacă e activată în Pipeline).');
        } else {
            setActivity('Pornesc agentul Cursor Composer 2.5 pe server — primul produs poate dura până la 1 minut.');
        }

        if (progressText) {
            if (workflow === 'pipeline') {
                setProgressLine(phase || 'Caut imagine alternativă…');
            } else if (currentIndex > 0 && done < currentIndex && data.status === 'running') {
                setProgressLine('Analizez ' + currentIndex + '/' + total + (phase ? (' — ' + phase) : ''));
            } else {
                setProgressLine(phase || 'Analizez imaginea…');
            }
        }
        if (progressFill) progressFill.style.width = percent + '%';
        if (progressCount) {
            let extra = '';
            if (data.eta_sec != null && data.status === 'running' && done < total) {
                extra = ' · ~' + formatElapsed(data.eta_sec) + ' rămas';
            }
            const shownDone = workflow === 'pipeline' ? done : (data.status === 'done' ? done : displayDone);
            progressCount.textContent = shownDone + ' / ' + total + ' produse'
                + (remaining > 0 && data.status === 'running' ? (' · rămân ' + remaining) : '') + extra;
        }
        if (progressTime && data.elapsed_sec != null) {
            progressTime.textContent = formatElapsed(data.elapsed_sec);
        }

        if (data.current_product_id) {
            analyzingProductId = String(data.current_product_id);
        } else if (data.current_product && data.current_product.randomn_id) {
            analyzingProductId = String(data.current_product.randomn_id);
        }

        if (data.status === 'done') {
            setProgressState('done');
        } else if (data.status === 'error') {
            setProgressState('error');
        } else {
            setProgressState('');
        }
    }

    function pipelineStepIcon(status) {
        if (status === 'running') return '⏳';
        if (status === 'ok') return '✅';
        if (status === 'skipped') return '⏭️';
        if (status === 'miss') return '❌';
        return '○';
    }

    function proxyPipelineImage(url) {
        const u = String(url || '').trim();
        if (!u) return '';
        if (u.startsWith('/')) return u;
        try {
            const host = new URL(u).hostname.toLowerCase();
            const needsProxy = ['autodoc', 'akamaized', 'emag.', 'epiesa', 'pieseauto'].some(k => host.includes(k));
            if (needsProxy) {
                return scraperApi + '?view=image_proxy&url=' + encodeURIComponent(u);
            }
        } catch (e) { /* ignore */ }
        return u;
    }

    async function loadPipelinePlans() {
        if (cachedPipelinePlans) return cachedPipelinePlans;
        try {
            const r = await fetch(scraperApi + '?view=integration');
            const j = await r.json();
            const plans = (j.data?.config?.image_plans || []).filter(p => p && p.enabled !== false);
            cachedPipelinePlans = plans;
            return plans;
        } catch (e) {
            return [];
        }
    }

    function renderPipelineSteps(plans, activeIdx, triedMap) {
        if (!pipelineStepsEl) return;
        if (!plans.length) {
            pipelineStepsEl.hidden = true;
            return;
        }
        pipelineStepsEl.hidden = false;
        pipelineStepsEl.innerHTML = plans.map((p, i) => {
            const key = p.source_id || ('plan-' + i);
            const tried = triedMap?.[key] || triedMap?.[String(p.tier)] || null;
            let status = 'pending';
            if (tried) status = tried.status || 'miss';
            else if (activeIdx === i) status = 'running';
            const msg = tried?.message || (status === 'running' ? 'Se caută imagine…' : (status === 'pending' ? 'În așteptare' : (status === 'miss' ? 'Fără rezultat' : '')));
            return ''
                + '<div class="image-audit-pipeline-step is-' + status + '">'
                + '<span class="image-audit-pipeline-step-icon">' + pipelineStepIcon(status) + '</span>'
                + '<div><div class="image-audit-pipeline-step-title">Plan ' + (i + 1) + ' — ' + escapeHtml(p.label || p.source_id || '—')
                + ' <span style="opacity:.6">(' + escapeHtml(p.source_id || '') + ')</span></div>'
                + (msg ? '<div class="image-audit-pipeline-step-msg">' + escapeHtml(msg) + '</div>' : '')
                + '</div></div>';
        }).join('');
    }

    function displayImageSrc(url) {
        const u = String(url || '').trim();
        if (!u) return '';
        if (u.startsWith('/')) return u;
        return proxyPipelineImage(u);
    }

    function renderPipelineHit(hit, titleFallback) {
        if (!pipelineHitEl) return;
        const url = hit?.url || hit?.new_image || '';
        if (!url) {
            pipelineHitEl.hidden = true;
            pipelineHitEl.innerHTML = '';
            return;
        }
        const img = displayImageSrc(url);
        const title = hit.title || titleFallback || 'Produs';
        const source = hit.source || '—';
        const productUrl = hit.url_product || '';
        const saved = hit.url && String(hit.url).startsWith('/') ? '<div style="margin-top:6px;font-size:12px;color:#047857">✓ Salvată în produs: <code>' + escapeHtml(hit.url) + '</code></div>' : '';
        pipelineHitEl.hidden = false;
        pipelineHitEl.innerHTML = ''
            + (img ? '<img src="' + escapeHtml(img) + '" alt="">' : '')
            + '<div class="image-audit-pipeline-hit-meta">'
            + '<div><strong>Imagine salvată în produs</strong> — sursă: <code>' + escapeHtml(source) + '</code></div>'
            + '<div style="margin-top:4px">' + escapeHtml(title) + '</div>'
            + saved
            + (productUrl ? '<div style="margin-top:6px"><a href="' + escapeHtml(productUrl) + '" target="_blank" rel="noopener" style="color:#0d9488">Deschide sursa ↗</a></div>' : '')
            + '</div>';
    }

    function buildTriedMap(triedList) {
        const map = {};
        normalizePipelineTried(triedList).forEach(t => {
            if (t?.source_id) map[t.source_id] = t;
            if (t?.tier != null) map[String(t.tier)] = t;
        });
        return map;
    }

    function normalizePipelineTried(raw) {
        if (!Array.isArray(raw) || !raw.length) return [];
        if (raw[0] && (raw[0].source_id || raw[0].status)) return raw;
        return raw.map(t => (t && t.tried) ? t.tried : t).filter(Boolean);
    }

    async function loadPipelinePlansFromResult(result) {
        const fromPipe = result && result.pipeline && Array.isArray(result.pipeline.plans) ? result.pipeline.plans : null;
        if (fromPipe && fromPipe.length) {
            cachedPipelinePlans = fromPipe.filter(p => p && p.enabled !== false);
            return cachedPipelinePlans;
        }
        return loadPipelinePlans();
    }

    function storeAuditResults(results) {
        (results || []).forEach(r => {
            if (r && r.randomn_id) lastAuditById[r.randomn_id] = r;
        });
    }

    /** @returns {string[]} */
    function idsNeedingImageReplace(results) {
        const out = [];
        const list = Array.isArray(results) ? results : Object.values(lastAuditById);
        list.forEach(r => {
            if (!r || !r.randomn_id) return;
            if (lastPipelineById[r.randomn_id]?.status === 'replaced') return;
            const v = String(r.verdict || '').toLowerCase();
            const score = Number(r.match_score || 0);
            const reco = String(r.recommendation || '').toLowerCase();
            if (['mismatch', 'error', 'no_image'].includes(v)) out.push(r.randomn_id);
            else if (reco === 'replace') out.push(r.randomn_id);
            else if (v === 'partial' && score < 70) out.push(r.randomn_id);
        });
        return [...new Set(out.filter(Boolean))];
    }

    function applyReplacedRowToDom(row) {
        if (!row || row.status !== 'replaced' || !row.new_image || !row.randomn_id) return;
        const src = displayImageSrc(row.new_image);
        const card = document.querySelector('.product-card[data-id="' + row.randomn_id + '"] img');
        if (card) card.src = src;
    }

    async function runPipelineSingle(id) {
        const response = await fetch(endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                type_product: 'audit_images_find_image',
                ids: [id],
                force: true,
            }),
        });
        const raw = await response.text();
        try {
            return JSON.parse(raw);
        } catch (e) {
            return { success: false, message: 'Răspuns invalid de la server (timeout?).' };
        }
    }

    async function runPipelineBatchSequential(ids, options) {
        const list = (ids || []).filter(Boolean);
        const opts = options || {};
        if (!list.length) {
            setWorkflowStep('save');
            setActivity('Nimic de înlocuit — imaginile sunt OK.');
            return { replaced: 0, missed: 0 };
        }

        setWorkflowStep('pipeline');
        pipelineRunning = true;
        startElapsedTimer();
        setActivity('Înlocuiesc imaginile nepotrivite — câte un produs (Plan 1→3), ca în Scraper.');
        if (resultsEl) resultsEl.hidden = false;
        if (pipelineHitEl) { pipelineHitEl.hidden = true; pipelineHitEl.innerHTML = ''; }

        const plans = await loadPipelinePlans();
        const total = list.length;
        let replaced = 0;
        let missed = 0;
        const t0 = Date.now();

        for (let i = 0; i < list.length; i++) {
            const id = list[i];
            const product = (lastProducts || []).find(p => p.randomn_id === id) || {};
            const name = product.title || product.pName || id;
            analyzingProductId = id;

            setProgressLine('Produs ' + (i + 1) + '/' + total + ' — ' + name);
            setActivity('Caut pe Autodoc / TecDoc (același motor ca în Scraper) — poate dura 30–90 sec/produs…');
            if (progressCount) {
                progressCount.textContent = (i + 1) + ' / ' + total + ' · ' + replaced + ' salvate';
            }
            if (progressFill) progressFill.style.width = Math.round(((i + 0.15) / total) * 100) + '%';
            renderPipelineSteps(plans, 0, null);
            mergeResults(lastProducts, Object.values(lastAuditById));

            let result;
            try {
                result = await runPipelineSingle(id);
            } catch (e) {
                missed++;
                lastPipelineById[id] = { randomn_id: id, status: 'error', message: 'Eroare rețea' };
                renderPipelineSteps(plans, -1, {});
                mergeResults(lastProducts, Object.values(lastAuditById));
                continue;
            }

            const activePlans = await loadPipelinePlansFromResult(result);
            const pipeline = result.pipeline || {};
            const row = (pipeline.results || []).find(r => r && r.randomn_id === id)
                || (pipeline.results || [])[0]
                || null;

            const triedList = normalizePipelineTried(
                (row && Array.isArray(row.tried) && row.tried.length)
                    ? row.tried
                    : (Array.isArray(pipeline.tried) ? pipeline.tried : [])
            );
            renderPipelineSteps(activePlans.length ? activePlans : plans, -1, buildTriedMap(triedList));

            if (row && row.randomn_id) {
                lastPipelineById[row.randomn_id] = row;
            }

            if (row && row.message && row.status === 'miss') {
                appendActivityLog(row.message);
                setProgressLine(row.message);
            } else if (result.message) {
                appendActivityLog(result.message);
            }

            if (Array.isArray(result.products) && result.products.length) {
                const byId = {};
                result.products.forEach(p => { if (p.randomn_id) byId[p.randomn_id] = p; });
                lastProducts = lastProducts.map(p => byId[p.randomn_id] || p);
                result.products.forEach(p => {
                    if (!lastProducts.some(x => x.randomn_id === p.randomn_id)) {
                        lastProducts.push(p);
                    }
                });
            }
            if (Array.isArray(result.results) && result.results.length) {
                storeAuditResults(result.results);
            }

            if (row && row.status === 'replaced') {
                replaced++;
                applyReplacedRowToDom(row);
                if (pipelineHitEl && list.length === 1) {
                    renderPipelineHit(row.hit || row, row.title || name);
                }
            } else {
                missed++;
            }

            mergeResults(lastProducts, Object.values(lastAuditById));
        }

        analyzingProductId = '';
        pipelineRunning = false;
        const sec = ((Date.now() - t0) / 1000).toFixed(1);
        if (progressFill) progressFill.style.width = '100%';
        if (progressCount) {
            progressCount.textContent = total + ' / ' + total + ' · ' + replaced + ' salvate · ' + missed + ' fără sursă';
        }
        setWorkflowStep('save');
        setProgressLine('Pipeline finalizat (' + sec + 's) — ' + replaced + ' imagini salvate.');
        setProgressState(replaced > 0 ? 'done' : (missed < total ? 'done' : 'error'));
        setActivity(replaced > 0
            ? ('Gata — ' + replaced + ' imagini înlocuite. Reîncarcă lista de produse pentru preview.')
            : 'Niciun plan nu a găsit imagine — verifică mesajele pe fiecare plan sau sursele în Scraper → Pipeline imagini.');
        if (pipelineStepsEl && total > 1) {
            pipelineStepsEl.hidden = true;
            pipelineStepsEl.innerHTML = '';
        }

        return { replaced, missed, pipeline: { replaced, missed, results: Object.values(lastPipelineById) } };
    }

    async function runPipelineSearch(ids, options) {
        const list = (ids || lastIds || []).filter(Boolean);
        if (!list.length) {
            alert('Selectează cel puțin un produs.');
            return null;
        }

        const forceOnly = !!(options && options.findOnly);
        if (forceOnly) {
            lastIds = list.slice();
            openModal();
            const preview = await loadPreview(list);
            lastProducts = preview.products.length ? preview.products : lastProducts;
            if (preview.results.length) {
                storeAuditResults(preview.results);
                mergeResults(lastProducts, preview.results);
            }
        }

        lastPipelineById = {};
        return runPipelineBatchSequential(list);
    }

    async function runPipelineRetryAfterAudit() {
        const needIds = idsNeedingImageReplace(Object.values(lastAuditById));
        if (!needIds.length) {
            setWorkflowStep('save');
            setActivity('Audit finalizat — imaginile OK, nu e nevoie de înlocuire automată.');
            setProgressLine('Audit finalizat — fără mismatch.');
            setProgressState('done');
            updateNoImageBanner(lastProducts, Object.values(lastAuditById));
            return { replaced: 0, missed: 0 };
        }
        updateNoImageBanner(lastProducts, Object.values(lastAuditById));
        setActivity('Am găsit ' + needIds.length + ' produse fără poză sau cu poză nepotrivită — pornesc Pas 2 (căutare automată)…');
        appendActivityLog('Pas 2 — caut imagini pentru ' + needIds.length + ' produse (Plan 1→3)…');
        if (findOnlyBtn) findOnlyBtn.classList.remove('is-pulse');
        return runPipelineBatchSequential(needIds);
    }

    async function runFindImageOnly(ids) {
        return runPipelineSearch(ids || lastIds, { findOnly: true });
    }

    async function pollJobProgress(jobId) {
        activeJobId = jobId;
        pollStartMs = Date.now();
        let pollCount = 0;

        if (elapsedTimer) clearInterval(elapsedTimer);
        elapsedTimer = setInterval(() => {
            if (progressTime) {
                progressTime.textContent = formatElapsed(Math.floor((Date.now() - pollStartMs) / 1000));
            }
        }, 1000);

        appendActivityLog('Job ' + jobId + ' — urmăresc progresul la fiecare ~20 sec (fără reîncărcare pagină).');

        while (activeJobId === jobId) {
            let response;
            try {
                response = await fetch(endpoint, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ type_product: 'audit_images_status', job_id: jobId }),
                });
            } catch (e) {
                if (progressText) progressText.textContent = 'Eroare rețea la citirea progresului…';
                appendActivityLog('Eroare rețea — reîncerc în 5 sec.');
                await sleep(5000);
                continue;
            }

            const data = await parseJsonResponse(response);
            if (!data.success) {
                if (progressText) progressText.textContent = data.message || 'Job negăsit.';
                appendActivityLog(data.message || 'Job negăsit sau expirat.');
                setProgressState('error');
                break;
            }

            const logLine = data.phase || data.activity || ('Progres: ' + (data.display_done != null ? data.display_done : data.done) + '/' + (data.total || 0));
            appendActivityLog(logLine);
            updateProgressUi(data);
            mergeResultsIncremental(lastProducts, data.results || []);

            if (data.finished || data.status === 'done' || data.status === 'error') {
                const doneCount = Number(data.done || 0);
                if (data.status === 'done' && doneCount === 0) {
                    setProgressLine(data.phase || 'Nu am primit verdicturi.');
                    setActivity('Verifică CURSOR_API_KEY în admin/.env sau rulează auditul manual în Composer 2.5.');
                    appendActivityLog('Audit terminat fără verdicturi — verifică logul cursor_audit_spawn.log.');
                    setProgressState('error');
                } else if (data.status === 'done') {
                    clearAnalyzing();
                    mergeResultsIncremental(lastProducts, data.results || []);
                    appendActivityLog('Pas 1 finalizat — ' + doneCount + ' produse analizate. Pornesc Pas 2 (căutare imagini)…');
                    await runPipelineRetryAfterAudit();
                    if (data.results_incomplete) {
                        setProgressLine((data.phase || 'Audit parțial.') + ' Pipeline doar pe produsele cu verdict.');
                    }
                } else if (data.status === 'error') {
                    appendActivityLog('Eroare: ' + (data.error || data.phase || 'audit Cursor'));
                    setProgressState('error');
                }
                break;
            }

            pollCount += 1;
            const waitMs = pollCount === 1 ? POLL_FIRST_MS : POLL_INTERVAL_MS;
            const waitSec = Math.round(waitMs / 1000);
            setProgressLine((data.phase || 'Procesez…') + ' — următoarea verificare în ~' + waitSec + ' sec');
            await sleep(waitMs);
        }

        stopPolling();
        activeJobId = '';
        clearAnalyzing();
    }

    function verdictClass(verdict) {
        const v = String(verdict || 'pending').toLowerCase();
        if (v === 'match') return 'match';
        if (v === 'partial') return 'partial';
        if (v === 'no_image') return 'no_image';
        if (['mismatch', 'error'].includes(v)) return 'mismatch';
        if (v === 'pending') return 'pending';
        return 'uncertain';
    }

    function pillClass(verdict) {
        const v = String(verdict || '').toLowerCase();
        if (v === 'match') return 'image-audit-pill--match';
        if (v === 'partial') return 'image-audit-pill--partial';
        if (v === 'no_image') return 'image-audit-pill--bad';
        if (['mismatch', 'error'].includes(v)) return 'image-audit-pill--bad';
        return '';
    }

    function escapeHtml(text) {
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function renderProductPreview(product, result) {
        const pipe = lastPipelineById[product.randomn_id] || null;
        if (pipe && pipe.status === 'replaced' && pipe.new_image) {
            const img = displayImageSrc(pipe.new_image);
            const title = product.title || pipe.title || 'Produs';
            return ''
                + '<article class="image-audit-card image-audit-card--match" data-audit-id="' + escapeHtml(product.randomn_id || '') + '">'
                + '<div class="image-audit-card__top">'
                + (img ? '<img class="image-audit-card__thumb" src="' + escapeHtml(img) + '" alt="">' : '')
                + '<div>'
                + '<p class="image-audit-card__title">' + escapeHtml(title) + '</p>'
                + '<p class="image-audit-card__meta">Cod: ' + escapeHtml(product.code || '—') + '</p>'
                + '<span class="image-audit-badge image-audit-badge--match">SALVATĂ · ' + escapeHtml(pipe.source || 'pipeline') + '</span>'
                + '</div></div>'
                + '<div class="image-audit-card__section" style="color:#047857">Imagine nouă salvată în produs.</div>'
                + '</article>';
        }
        if (pipe && (pipe.status === 'miss' || pipe.status === 'error')) {
            const title = product.title || 'Produs';
            return ''
                + '<article class="image-audit-card image-audit-card--mismatch" data-audit-id="' + escapeHtml(product.randomn_id || '') + '">'
                + '<div class="image-audit-card__top">'
                + '<div><p class="image-audit-card__title">' + escapeHtml(title) + '</p>'
                + '<span class="image-audit-badge image-audit-badge--mismatch">FĂRĂ SURSĂ</span></div></div>'
                + '<div class="image-audit-card__section" style="color:#9a3412;">' + escapeHtml(pipe.message || 'Niciun plan nu a găsit imagine.') + '</div>'
                + '</article>';
        }

        const img = product.image_url || '';
        const title = product.title || 'Produs';
        const hasResult = result && result.verdict;
        const verdict = hasResult ? String(result.verdict) : 'pending';
        const cls = verdictClass(verdict);
        const score = hasResult ? Number(result.match_score || 0) : '—';

        let statusLabel = 'AȘTEPTARE';
        let statusHint = 'Se pregătește analiza imaginii.';
        if (product.randomn_id === analyzingProductId) {
            if (pipelineRunning) {
                statusLabel = 'SE CAUTĂ IMAGINE';
                statusHint = 'Rulez Plan 1→3 (Autodoc, ePiesa, TecDoc)…';
            } else {
                statusLabel = 'SE ANALIZEAZĂ';
                statusHint = 'Compar poza cu titlul și codul produsului.';
            }
        } else if (hasResult) {
            if (verdict === 'no_image') {
                statusLabel = 'FĂRĂ IMAGINE';
                statusHint = 'Nu există poză în magazin — doar placeholder pe listă.';
            } else {
                statusLabel = String(verdict).toUpperCase();
                statusHint = 'Scor ' + score + '/100';
            }
        }

        let body = '';
        if (hasResult && verdict === 'no_image') {
            body = '<div class="image-audit-card__section" style="color:#7c3aed;">'
                + escapeHtml(result.summary_ro || 'Produs fără imagine în baza de date. Urmează căutare automată pe Autodoc / ePiesa / TecDoc.')
                + '</div>';
        } else if (hasResult && !['mismatch', 'error', 'no_image'].includes(verdict)) {
            body = '<div class="image-audit-card__section">' + escapeHtml(result.summary_ro || statusHint) + '</div>';
        } else if (!hasResult) {
            body = '<div class="image-audit-card__section" style="color:#6d28d9;">' + escapeHtml(statusHint) + '</div>';
        } else {
            body = '<div class="image-audit-card__section" style="color:#9a3412;">Poză nepotrivită — înlocuire automată după audit.</div>';
        }

        const thumb = displayImageSrc(img);
        const noThumbNote = !thumb && verdict === 'no_image'
            ? '<div class="image-audit-card__no-thumb" title="Fără imagine în DB">?</div>'
            : '';

        return ''
            + '<article class="image-audit-card image-audit-card--' + cls + '" data-audit-id="' + escapeHtml(product.randomn_id || '') + '">'
            + '<div class="image-audit-card__top">'
            + (thumb ? '<img class="image-audit-card__thumb" src="' + escapeHtml(thumb) + '" alt="">' : noThumbNote)
            + '<div>'
            + '<p class="image-audit-card__title">' + escapeHtml(title) + '</p>'
            + '<p class="image-audit-card__meta">Cod: ' + escapeHtml(product.code || '—') + ' · ' + escapeHtml(product.category || '') + '</p>'
            + '<span class="image-audit-badge image-audit-badge--' + cls + '">' + escapeHtml(statusLabel) + (hasResult ? (' · ' + score + '/100') : '') + '</span>'
            + '</div></div>'
            + body
            + '</article>';
    }

    function mergeResultsIncremental(products, results) {
        storeAuditResults(results);
        const list = products || lastProducts || [];
        if (!resultsEl) return;

        if (!resultsEl.querySelector('.image-audit-card') && list.length) {
            mergeResults(list, results || []);
            return;
        }

        const byId = {};
        (results || []).forEach(r => { if (r && r.randomn_id) byId[r.randomn_id] = r; });

        list.forEach(p => {
            if (!p || !p.randomn_id) return;
            const rid = String(p.randomn_id);
            const row = byId[rid] || lastAuditById[rid] || null;
            const existing = resultsEl.querySelector('.image-audit-card[data-audit-id="' + rid + '"]');
            const html = renderProductPreview(p, row);
            if (existing) {
                const wrap = document.createElement('div');
                wrap.innerHTML = html;
                const newCard = wrap.firstElementChild;
                if (newCard) existing.replaceWith(newCard);
            } else {
                resultsEl.insertAdjacentHTML('beforeend', html);
            }
        });

        (results || []).forEach(r => updateCardPill(String(r.randomn_id || ''), r));
        updateNoImageBanner(products, results);
    }

    function mergeResults(products, results) {
        storeAuditResults(results);
        const byId = {};
        (results || []).forEach(r => { if (r && r.randomn_id) byId[r.randomn_id] = r; });
        if (!resultsEl) return;
        resultsEl.innerHTML = (products || []).map(p => renderProductPreview(p, byId[p.randomn_id] || lastAuditById[p.randomn_id] || null)).join('');
        (results || []).forEach(r => updateCardPill(String(r.randomn_id || ''), r));
        updateNoImageBanner(products, results);
    }

    function updateCardPill(publicId, row) {
        if (!publicId || !row || !row.verdict) return;
        const card = document.querySelector('.product-card[data-id="' + publicId + '"]');
        if (!card) return;
        let pill = card.querySelector('.image-audit-pill');
        if (!pill) {
            pill = document.createElement('span');
            pill.className = 'image-audit-pill';
            card.querySelector('.box')?.appendChild(pill);
        }
        pill.textContent = row.verdict + ' ' + (row.match_score || 0);
        pill.className = 'image-audit-pill ' + pillClass(row.verdict);
    }

    function openModal() {
        const el = document.getElementById('imageAuditModal') || modal;
        if (!el) {
            alert('Fereastra audit imagini lipsește. Reîncarcă pagina (Ctrl+F5).');
            return false;
        }
        if (el.parentElement && el.parentElement !== document.body) {
            document.body.appendChild(el);
        }
        el.classList.add('is-open');
        document.body.style.overflow = 'hidden';
        return true;
    }

    function closeModal() {
        stopPolling();
        activeJobId = '';
        const el = document.getElementById('imageAuditModal') || modal;
        el?.classList.remove('is-open');
        document.body.style.overflow = '';
    }

    function resetProgressStyle() {
        setProgressState('');
        if (progressFill) progressFill.style.width = '0%';
        if (progressCount) progressCount.textContent = '0 / 0 produse';
        if (progressTime) progressTime.textContent = '0:00';
    }

    function startElapsedTimer() {
        pollStartMs = Date.now();
        if (elapsedTimer) clearInterval(elapsedTimer);
        elapsedTimer = setInterval(() => {
            if (progressTime) {
                progressTime.textContent = formatElapsed(Math.floor((Date.now() - pollStartMs) / 1000));
            }
        }, 1000);
    }

    async function loadPreview(ids, options) {
        const opts = options || {};
        const selectAllCatalog = !!opts.all;
        const list = (ids || []).filter(Boolean);
        if (!selectAllCatalog && !list.length) {
            return { products: [], results: [] };
        }
        try {
            const body = selectAllCatalog
                ? { type_product: 'audit_images_preview', all: true }
                : { type_product: 'audit_images_preview', ids: list };
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(body),
            });
            const data = await response.json();
            if (!data.success) return { products: [], results: [] };
            return {
                products: Array.isArray(data.products) ? data.products : [],
                results: Array.isArray(data.results) ? data.results : [],
            };
        } catch (e) {
            return { products: [], results: [] };
        }
    }

    function applyAuditPreview(preview, options) {
        const opts = options || {};
        if (!preview) return;
        try {
            if (preview.products.length) {
                lastProducts = preview.products;
                lastIds = idsFromProducts(lastProducts);
            }
            mergeResults(lastProducts, preview.results || []);
        } catch (err) {
            console.error('applyAuditPreview', err);
            if (resultsEl) {
                resultsEl.innerHTML = '<div class="image-audit-card"><div class="image-audit-card__section" style="color:#991b1b">Eroare la afișarea produselor în listă.</div></div>';
            }
        }
        const total = lastProducts.length || Number(opts.expectedCount || 0);
        if (progressCount && total > 0) {
            progressCount.textContent = (preview.results.length || 0) + ' / ' + total + ' produse';
        }
        if (progressFill && total > 0) {
            const pct = Math.round(((preview.results.length || 0) / total) * 100);
            progressFill.style.width = Math.max(8, pct) + '%';
        }
    }

    function idsFromProducts(products) {
        return (products || [])
            .map(p => String(p.randomn_id || p.id || '').trim())
            .filter(Boolean);
    }

    async function parseJsonResponse(response) {
        const text = await response.text();
        try {
            return JSON.parse(text);
        } catch (e) {
            return {
                success: false,
                message: 'Răspuns invalid de la server (nu e JSON). Reîncarcă pagina sau verifică sesiunea admin.',
            };
        }
    }

    async function runAudit(ids, options) {
        const opts = options || {};
        const selectAllCatalog = !!opts.all;
        let list = (ids || []).filter(Boolean);

        if (!selectAllCatalog && !list.length) {
            alert('Selectează cel puțin un produs.');
            return null;
        }

        const expectedCount = selectAllCatalog ? Number(opts.count || 0) : list.length;
        if (!selectAllCatalog && list.length > AUDIT_MAX_BATCH) {
            alert('Maxim ' + AUDIT_MAX_BATCH + ' produse per audit (ai ' + list.length + ').');
            return null;
        }
        if (selectAllCatalog && expectedCount > AUDIT_MAX_BATCH) {
            alert('Maxim ' + AUDIT_MAX_BATCH + ' produse per audit (catalog: ' + expectedCount + ').');
            return null;
        }

        if (!openModal()) {
            return null;
        }

        try {
        lastIds = selectAllCatalog ? [] : list.slice();
        stopPolling();
        lastAuditById = {};
        lastPipelineById = {};
        clearActivityLog();
        resetProgressStyle();
        if (resultsEl) { resultsEl.hidden = false; resultsEl.innerHTML = ''; }
        if (pipelineStepsEl) { pipelineStepsEl.hidden = true; pipelineStepsEl.innerHTML = ''; }
        if (pipelineHitEl) { pipelineHitEl.hidden = true; pipelineHitEl.innerHTML = ''; }
        startElapsedTimer();
        setWorkflowStep('audit');
        pipelineRunning = false;
        setActivity('Compar imaginea cu titlul și codul produsului…');
        setProgressLine('Pregătesc analiza…');
        if (cursorBox) cursorBox.hidden = true;
        if (resultsEl) resultsEl.innerHTML = '';
        analyzingProductId = selectAllCatalog ? '' : list[0];
        let preview = { products: [], results: [] };

        if (!selectAllCatalog) {
            preview = await loadPreview(list);
            applyAuditPreview(preview);
            if (progressFill) progressFill.style.width = '15%';
        } else {
            setProgressLine('Încarc toate produsele selectate din magazin…');
            preview = await loadPreview([], { all: true });
            applyAuditPreview(preview, { expectedCount: expectedCount });
            if (!lastProducts.length) {
                setProgressLine('Nu am putut încărca lista de produse. Verifică sesiunea admin sau reîncarcă pagina.');
                setProgressState('error');
                stopPolling();
                return null;
            }
            if (progressFill) progressFill.style.width = '12%';
        }

        setProgressLine('Analizez imaginea…');
        setActivity('Pornesc agentul Cursor pe server — vezi jurnalul și lista de mai jos (actualizare ~20 sec).');
        appendActivityLog('Trimit cererea de audit către server…');

        const payload = selectAllCatalog
            ? { type_product: 'audit_images_bulk', all: true }
            : (list.length === 1
                ? { type_product: 'audit_images', id: list[0] }
                : { type_product: 'audit_images_bulk', ids: list });

        let response;
        try {
            response = await fetch(endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            });
        } catch (err) {
            setProgressLine('Eroare rețea.');
            setProgressState('error');
            return null;
        }
        const result = await parseJsonResponse(response);

        if (!result.success) {
            const cached = selectAllCatalog
                ? await loadPreview([], { all: true })
                : await loadPreview(list);
            if (cached.results.length) {
                lastProducts = cached.products.length ? cached.products : lastProducts;
                mergeResults(lastProducts, cached.results);
                setProgressLine((result.message || 'Scanare eșuată') + ' — verdict anterior afișat mai jos.');
                setActivity('Verifică CURSOR_API_KEY în admin/.env sau reîncearcă auditul.');
                setProgressState('error');
                await runPipelineRetryAfterAudit();
                stopPolling();
                return result;
            }
            setProgressLine(result.message || 'Eroare la audit.');
            setProgressState('error');
            stopPolling();
            return result;
        }

        if (result.mode === 'cursor' && result.cursor_prompt) {
            if (Array.isArray(result.products) && result.products.length) {
                lastProducts = result.products;
                lastIds = idsFromProducts(lastProducts);
            }
            mergeResults(lastProducts, result.results || []);
            updateProgressUi({
                phase: result.message || 'Lot pregătit pentru Composer 2.5.',
                total: result.total || lastProducts.length,
                done: Number(result.done || 0),
                percent: 100,
                status: 'done',
                elapsed_sec: Math.floor((Date.now() - pollStartMs) / 1000),
            });
            if (cursorBox) {
                cursorBox.hidden = false;
            }
            if (promptEl) {
                promptEl.value = String(result.cursor_prompt);
            }
            setProgressLine(result.message || 'Lot pregătit pentru Composer 2.5.');
            setActivity('Deschide Cursor → Composer 2.5, lipește promptul (@product-image-audit), apoi Încarcă rezultate.');
            if (progressFill) progressFill.style.width = '100%';
            stopPolling();
            return result;
        }

        lastProducts = Array.isArray(result.products) ? result.products : lastProducts;
        if (!lastProducts.length && !selectAllCatalog && preview && preview.products.length) {
            lastProducts = preview.products;
        }
        if (lastProducts.length) {
            lastIds = idsFromProducts(lastProducts);
        } else if (Array.isArray(result.ids) && result.ids.length) {
            lastIds = result.ids.slice();
        }

        if (result.job_id) {
            if (!lastProducts.length && Array.isArray(result.products) && result.products.length) {
                lastProducts = result.products;
                lastIds = idsFromProducts(lastProducts);
            }
            mergeResultsIncremental(lastProducts, result.results || []);
            if (result.async) {
                appendActivityLog(result.message || 'Audit pornit în fundal — urmăresc progresul.');
            }
            updateProgressUi({
                phase: result.message || 'Analizez…',
                total: result.total || lastProducts.length,
                done: Number(result.done || 0),
                percent: result.async ? 6 : 8,
                status: 'running',
                elapsed_sec: 0,
                activity: result.message || '',
            });
            await pollJobProgress(result.job_id);
            return result;
        }

        clearAnalyzing();
        let auditResults = result.results || [];
        if (!auditResults.length) {
            const again = selectAllCatalog
                ? await loadPreview([], { all: true })
                : await loadPreview(lastIds.length ? lastIds : list);
            auditResults = again.results || [];
            if (!lastProducts.length && again.products.length) {
                lastProducts = again.products;
            }
        }
        mergeResults(lastProducts, auditResults);
        updateProgressUi({
            phase: result.message || ('Audit: ' + auditResults.length + ' produse.'),
            total: result.total || lastProducts.length || list.length,
            done: auditResults.length,
            percent: auditResults.length > 0 ? 100 : 20,
            status: auditResults.length > 0 ? 'done' : 'error',
            elapsed_sec: Math.floor((Date.now() - pollStartMs) / 1000),
        });

        if (auditResults.length > 0) {
            if (result.cached) {
                setActivity((result.message || '') + ' Poți rula pipeline-ul pe acest verdict.');
            }
            await runPipelineRetryAfterAudit();
        } else {
            setProgressState('error');
            setActivity(result.message || 'Nu am putut analiza imaginea. Verifică CURSOR_API_KEY în admin/.env.');
        }
        stopPolling();
        return result;
        } catch (err) {
            console.error('runAudit', err);
            setProgressLine('Eroare la pornirea auditului.');
            setProgressState('error');
            setActivity(String(err && err.message ? err.message : err));
            stopPolling();
            return null;
        }
    }

    async function reloadResults() {
        if (!lastIds.length) return;
        if (progressText) progressText.textContent = 'Încarc rezultate salvate de Cursor…';
        const response = await fetch(endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ type_product: 'audit_images_reload', ids: lastIds }),
        });
        const result = await response.json();
        if (progressText) progressText.textContent = (result.message || '').replace(/\*\*/g, '');
        if (result.success) {
            mergeResults(lastProducts, result.results || []);
            await runPipelineRetryAfterAudit();
        }
        return result;
    }

    copyBtn && copyBtn.addEventListener('click', async () => {
        const text = promptEl?.value || '';
        if (!text) return;
        try {
            await navigator.clipboard.writeText(text);
            copyBtn.textContent = 'Copiat!';
            setTimeout(() => { copyBtn.textContent = 'Copiază prompt Cursor'; }, 2000);
        } catch (e) {
            promptEl?.select();
            document.execCommand('copy');
        }
    });

    findOnlyBtn && findOnlyBtn.addEventListener('click', () => {
        if (!lastIds.length) {
            alert('Selectează un produs sau deschide auditul de pe pagina produsului.');
            return;
        }
        runFindImageOnly(lastIds).catch(() => {});
    });
    reloadBtn && reloadBtn.addEventListener('click', reloadResults);
    closeBtn && closeBtn.addEventListener('click', closeModal);
    modal && modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && modal?.classList.contains('is-open')) closeModal();
    });

    return { runAudit, runFindImageOnly, reloadResults, openModal, closeModal };
})();
</script>
