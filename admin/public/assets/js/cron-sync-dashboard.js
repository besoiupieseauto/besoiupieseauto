/**
 * Panou Cron Sync — /admin/cron
 */
(function (global) {
    'use strict';

    const HUB = '/admin/api/admin_hub_endpoint.php';

    function escapeHtml(v) {
        return String(v ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
    }

    function formatActivityTime(label) {
        const raw = String(label || '').trim();
        if (!raw) return '—';
        const space = raw.indexOf(' ');
        if (space > 0) {
            return '<span class="scan-activity-item__date">' + escapeHtml(raw.slice(0, space)) + '</span>' +
                '<span class="scan-activity-item__hour">' + escapeHtml(raw.slice(space + 1)) + '</span>';
        }
        return escapeHtml(raw);
    }

    async function fetchJson(url, options) {
        const opts = options || {};
        const timeoutMs = Number(opts.timeoutMs || 25000);
        const controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
        let timer = null;
        if (controller && timeoutMs > 0) {
            timer = setTimeout(() => controller.abort(), timeoutMs);
        }

        try {
            const r = await fetch(url, Object.assign({
                credentials: 'same-origin',
                signal: controller ? controller.signal : undefined
            }, opts));
            const raw = await r.text();
            let d = {};

            if (raw.trim() !== '') {
                try {
                    d = JSON.parse(raw);
                } catch (parseError) {
                    const isHtml = /<!DOCTYPE|<html/i.test(raw);
                    if (r.status === 401 || (isHtml && raw.toLowerCase().includes('autentificare'))) {
                        throw new Error('Sesiune expirată — reîncarcă pagina și autentifică-te din nou.');
                    }
                    if (r.status === 504 || r.status === 502 || r.status === 503) {
                        throw new Error('Serverul a întrerupt cererea (timeout). Încearcă «Reset tot» sau reîncarcă pagina.');
                    }
                    if (isHtml) {
                        throw new Error('Serverul a returnat HTML în loc de JSON. Verifică ruta /admin/cron pe server.');
                    }
                    const snippet = raw.trim().slice(0, 160).replace(/\s+/g, ' ');
                    throw new Error(snippet ? ('Răspuns invalid: ' + snippet) : 'Răspuns gol de la server.');
                }
            }

            if (!r.ok || d.success === false) {
                throw new Error(d.message || ('Eroare API (HTTP ' + r.status + ')'));
            }
            return d;
        } catch (e) {
            if (e && e.name === 'AbortError') {
                throw new Error('Timeout la încărcare (' + Math.round(timeoutMs / 1000) + 's). Serverul răspunde prea greu — apasă Reset tot.');
            }
            throw e;
        } finally {
            if (timer) clearTimeout(timer);
        }
    }

    const STEP_LABELS = {
        run: 'Pornire',
        ftp: 'FTP/SFTP',
        validate: 'Validare',
        idle: 'Sistem',
        sync: 'Sync',
        done: 'Finalizat',
        error: 'Eroare'
    };

    const PROGRESS_PHASES = ['run', 'validate', 'sync', 'done'];
    const POLL_ACTIVE_MS = 1200;
    const POLL_IDLE_MS = 2500;

    function parseActivityTimestamp(entry) {
        const iso = String(entry?.at || '').trim();
        if (iso) {
            const ts = Date.parse(iso);
            if (!Number.isNaN(ts)) return ts;
        }
        const label = String(entry?.at_label || '').trim();
        const m = label.match(/^(\d{2})\.(\d{2})\.(\d{4})\s+(\d{2}):(\d{2}):(\d{2})$/);
        if (m) {
            return new Date(
                parseInt(m[3], 10),
                parseInt(m[2], 10) - 1,
                parseInt(m[1], 10),
                parseInt(m[4], 10),
                parseInt(m[5], 10),
                parseInt(m[6], 10)
            ).getTime();
        }
        return 0;
    }

    function isRecentActivity(items, maxAgeMs) {
        const list = Array.isArray(items) ? items : [];
        if (!list.length) return false;
        const ts = parseActivityTimestamp(list[0]);
        if (!ts) return false;
        return (Date.now() - ts) < (maxAgeMs || 180000);
    }

    function shouldAutoPoll(running, progress, activity) {
        if (running) return true;
        const prog = progress && typeof progress === 'object' ? progress : {};
        const phase = String(prog.phase || '').toLowerCase();
        if (['done', 'error', 'stopped'].includes(phase)) {
            return false;
        }
        if (prog.running) return true;
        const pct = Number(prog.pct || 0);
        if (phase && pct > 0 && pct < 100) {
            return true;
        }
        if (!isRecentActivity(activity, 120000)) return false;
        const step = String((activity[0] || {}).step || '').toLowerCase();
        return ['run', 'validate', 'sync', 'ftp'].includes(step);
    }

    function collectIssues(items, limit) {
        const max = limit || 12;
        const out = [];
        const list = Array.isArray(items) ? items : [];
        for (let i = 0; i < list.length && out.length < max; i++) {
            const ev = list[i];
            const lvl = String(ev.level || 'info').toLowerCase();
            if (lvl !== 'error' && lvl !== 'warn') continue;
            const msg = String(ev.message || '').replace(/^\[PROGRESS:\d{1,3}%\]\s*/, '').trim();
            if (!msg) continue;
            const sup = String(ev.supplier || '').trim();
            const detail = String(ev.detail || '').trim();
            out.push({
                level: lvl,
                supplier: sup,
                message: msg,
                detail,
                at_label: ev.at_label || ''
            });
        }
        return out;
    }

    function renderIssuesPanel(wrapEl, listEl, countEl, items) {
        if (!wrapEl) return;
        const issues = collectIssues(items);
        if (!issues.length) {
            wrapEl.hidden = true;
            if (listEl) listEl.innerHTML = '';
            if (countEl) countEl.textContent = '';
            return;
        }
        wrapEl.hidden = false;
        const errCount = issues.filter(i => i.level === 'error').length;
        const warnCount = issues.length - errCount;
        if (countEl) {
            countEl.textContent = errCount > 0
                ? errCount + ' erori' + (warnCount > 0 ? ', ' + warnCount + ' avertismente' : '')
                : warnCount + ' avertismente';
        }
        if (listEl) {
            listEl.innerHTML = issues.map(issue => {
                const sup = issue.supplier ? '<span class="scan-run-issues__sup">[' + escapeHtml(issue.supplier) + ']</span> ' : '';
                const detail = issue.detail
                    ? '<span class="scan-run-issues__detail">' + escapeHtml(issue.detail) + '</span>'
                    : '';
                return '<li class="scan-run-issues__item scan-run-issues__item--' + escapeHtml(issue.level) + '">' +
                    sup + escapeHtml(issue.message) + detail +
                '</li>';
            }).join('');
        }
    }

    function renderSupplierProgressTrack(trackEl, pipeline, progress, active) {
        if (!trackEl) return;
        const list = Array.isArray(pipeline) ? pipeline : [];
        const prog = progress && typeof progress === 'object' ? progress : {};
        const total = Number(prog.supplier_total || list.length || 0);
        const currentIdx = Number(prog.supplier_index || 0);
        const currentCode = String(prog.supplier || '').toUpperCase();

        if (!active || total <= 0 || list.length === 0) {
            trackEl.hidden = true;
            trackEl.innerHTML = '';
            return;
        }

        trackEl.hidden = false;
        trackEl.innerHTML = list.map((row, idx) => {
            const code = String(row.code || '').toUpperCase();
            const name = escapeHtml(row.name || code);
            const pos = idx + 1;
            let state = 'pending';
            let pct = 0;
            if (currentIdx > 0) {
                if (pos < currentIdx) {
                    state = 'done';
                    pct = 100;
                } else if (code === currentCode || pos === currentIdx) {
                    state = 'active';
                    pct = Math.max(8, Math.min(95, Number(prog.pct || 0)));
                }
            }
            const st = String(row.status || '');
            if (state === 'pending' && st === 'ok') {
                state = 'done';
                pct = 100;
            }
            return '<div class="scan-supplier-progress-item scan-supplier-progress-item--' + state + '" title="' + escapeHtml(code) + '">' +
                '<div class="scan-supplier-progress-item__head">' +
                    '<span class="scan-supplier-progress-item__name">' + name + '</span>' +
                    '<span class="scan-supplier-progress-item__code">' + escapeHtml(code) + '</span>' +
                '</div>' +
                '<div class="scan-supplier-progress-item__bar"><span style="width:' + pct + '%"></span></div>' +
            '</div>';
        }).join('');
    }

    function highlightSupplierRow(supplierCode) {
        const code = String(supplierCode || '').toUpperCase();
        document.querySelectorAll('.scan-suppliers-live-row').forEach((row) => {
            const rowCode = row.querySelector('.scan-suppliers-live__code');
            const match = rowCode && String(rowCode.textContent || '').trim().toUpperCase() === code;
            row.classList.toggle('scan-suppliers-live-row--scanning', !!match && code !== '');
        });
    }

    function phaseIndex(phase) {
        const key = String(phase || '').toLowerCase();
        if (key === 'ftp') return 0;
        if (key === 'validate') return 1;
        if (key === 'sync') return 2;
        if (key === 'done' || key === 'error' || key === 'stopped') return 3;
        if (key === 'run') return 0;
        return -1;
    }

    function renderProgressSteps(stepsEl, phase) {
        if (!stepsEl) return;
        const idx = phaseIndex(phase);
        stepsEl.querySelectorAll('.scan-run-progress-step').forEach((el) => {
            const stepPhase = el.getAttribute('data-phase') || '';
            const stepIdx = phaseIndex(stepPhase);
            el.classList.remove('is-active', 'is-done');
            if (idx < 0) return;
            if (stepIdx < idx) {
                el.classList.add('is-done');
            } else if (stepIdx === idx) {
                el.classList.add('is-active');
            }
        });
    }

    function resolveProgressState(activity, progressPayload) {
        const progress = progressPayload && typeof progressPayload === 'object' ? progressPayload : null;
        if (progress && (progress.running || progress.pct > 0 || progress.message)) {
            const supplierIndex = Number(progress.supplier_index || 0);
            const supplierTotal = Number(progress.supplier_total || 0);
            let meta = '';
            if (progress.phase_label) {
                meta = String(progress.phase_label);
            }
            if (supplierTotal > 0 && supplierIndex > 0) {
                meta += (meta ? ' · ' : '') + 'Furnizor ' + supplierIndex + '/' + supplierTotal;
                if (progress.supplier) {
                    meta += ' (' + progress.supplier + ')';
                }
            }
            return {
                pct: typeof progress.pct === 'number' ? progress.pct : parseInt(progress.pct, 10),
                label: String(progress.message || progress.phase_label || 'Se rulează scanarea…'),
                phase: String(progress.phase || ''),
                meta
            };
        }
        return parseProgressFromActivity(activity);
    }

    function stepBadge(step) {
        const key = String(step || '').trim().toLowerCase();
        if (!key) return '';
        const label = STEP_LABELS[key] || key;
        return '<span class="scan-activity-item__step">' + escapeHtml(label) + '</span>';
    }

    function renderActivity(feedEl, items, opts) {
        if (!feedEl) return;
        const options = opts || {};
        const list = Array.isArray(items) ? items : [];
        if (!list.length) {
            feedEl.innerHTML = '<div class="scan-activity-empty">Jurnal gol. Evenimentele apar aici după «Scanează furnizori» sau sync FTP.</div>';
            return;
        }
        feedEl.innerHTML = list.map((ev, idx) => {
            const lvl = escapeHtml(ev.level || 'info');
            const sup = ev.supplier ? '<strong class="scan-activity-item__supplier">' + escapeHtml(ev.supplier) + '</strong>' : '';
            const step = stepBadge(ev.step);
            const detail = ev.detail
                ? '<span class="scan-activity-item__detail">' + escapeHtml(ev.detail) + '</span>'
                : '';
            const fresh = options.animate && idx < 3 ? ' scan-activity-item--new' : '';
            return '<div class="scan-activity-item scan-activity-item--' + lvl + fresh + '">' +
                '<span class="scan-activity-item__time">' + formatActivityTime(ev.at_label) + '</span>' +
                '<span class="scan-activity-item__body">' +
                    (step || sup ? '<span class="scan-activity-item__head">' + step + sup + '</span>' : '') +
                    '<span class="scan-activity-item__msg">' + escapeHtml(String(ev.message || '').replace(/^\[PROGRESS:\d{1,3}%\]\s*/, '')) + '</span>' +
                    detail +
                '</span>' +
            '</div>';
        }).join('');
    }

    const CONSOLE_COLORS = { ok: '#86efac', warn: '#fcd34d', error: '#fca5a5', info: '#93c5fd' };

    function parseProgressFromActivity(items) {
        const list = Array.isArray(items) ? items : [];
        for (let i = 0; i < list.length; i++) {
            const msg = String(list[i].message || '');
            const tagged = msg.match(/^\[PROGRESS:(\d{1,3})%\]\s*/);
            if (tagged) {
                return {
                    pct: Math.max(0, Math.min(100, parseInt(tagged[1], 10))),
                    label: msg.replace(/^\[PROGRESS:\d{1,3}%\]\s*/, ''),
                    phase: String(list[i].step || ''),
                    meta: ''
                };
            }
        }
        for (let j = 0; j < list.length; j++) {
            const text = String(list[j].message || '');
            const m = text.match(/Procesez\s+(\d+)\s*\/\s*(\d+)/i);
            if (m) {
                const cur = parseInt(m[1], 10);
                const total = parseInt(m[2], 10);
                if (total > 0) {
                    return {
                        pct: Math.max(5, Math.min(95, Math.round((cur / total) * 100))),
                        label: text,
                        phase: String(list[j].step || 'sync'),
                        meta: 'Furnizor ' + cur + '/' + total
                    };
                }
            }
        }
        const stepWeights = { run: 8, validate: 32, sync: 55, ftp: 20 };
        for (let k = 0; k < list.length; k++) {
            const step = String(list[k].step || '');
            if (stepWeights[step] !== undefined) {
                const sup = list[k].supplier ? list[k].supplier + ': ' : '';
                return {
                    pct: stepWeights[step],
                    label: sup + String(list[k].message || 'Se rulează…'),
                    phase: step,
                    meta: STEP_LABELS[step] || step
                };
            }
        }
        if (list.length) {
            const last = list[0];
            const sup = last.supplier ? last.supplier + ': ' : '';
            return {
                pct: null,
                label: sup + String(last.message || 'Se rulează…'),
                phase: String(last.step || ''),
                meta: ''
            };
        }
        return { pct: null, label: 'Se rulează scanarea…', phase: 'run', meta: '' };
    }

    function updateScanProgressBar(fillEl, pctEl, labelEl, metaEl, stepsEl, progressWrap, active, label, pct, phase, meta, issuesEl, issuesListEl, issuesCountEl, issuesItems, supplierTrackEl, pipeline, progressPayload, dismissBtn) {
        if (!progressWrap) return;
        progressWrap.hidden = !active;
        const phaseKey = String(phase || '').toLowerCase();
        progressWrap.classList.toggle('is-finished', active && (phaseKey === 'done' || phaseKey === 'error' || phaseKey === 'stopped'));
        progressWrap.classList.toggle('is-error-state', active && (phaseKey === 'error' || phaseKey === 'stopped'));
        if (dismissBtn) {
            dismissBtn.hidden = !active || (phaseKey !== 'done' && phaseKey !== 'error' && phaseKey !== 'stopped');
        }
        if (!active) {
            renderIssuesPanel(issuesEl, issuesListEl, issuesCountEl, []);
            if (supplierTrackEl) {
                supplierTrackEl.hidden = true;
                supplierTrackEl.innerHTML = '';
            }
            highlightSupplierRow('');
            return;
        }

        const text = label || 'Se rulează scanarea…';
        if (labelEl) labelEl.textContent = text;
        if (metaEl) metaEl.textContent = meta || '';

        const hasPct = typeof pct === 'number' && !Number.isNaN(pct);
        if (fillEl) {
            fillEl.classList.toggle('is-indeterminate', !hasPct);
            fillEl.classList.toggle('is-error', String(phase || '').toLowerCase() === 'error');
            if (hasPct) {
                fillEl.style.width = Math.max(2, Math.min(100, pct)) + '%';
            } else {
                fillEl.style.width = '';
            }
        }
        if (pctEl) {
            pctEl.textContent = hasPct ? Math.round(pct) + '%' : '…';
            pctEl.classList.toggle('is-error', String(phase || '').toLowerCase() === 'error');
        }
        renderProgressSteps(stepsEl, phase || '');
        renderIssuesPanel(issuesEl, issuesListEl, issuesCountEl, issuesItems || []);
        renderSupplierProgressTrack(supplierTrackEl, pipeline, progressPayload, active);
        highlightSupplierRow(progressPayload && progressPayload.supplier ? progressPayload.supplier : '');
    }

    function renderConsoleLog(logEl, items, opts) {
        if (!logEl) return;
        const list = Array.isArray(items) ? items : [];
        const options = opts || {};
        if (!list.length) {
            if (!options.keepContent) {
                logEl.innerHTML = '<div class="scan-cron-console__empty">Apasă «Scanează furnizori» — jurnalul se actualizează live în timpul rulării.</div>';
            }
            return;
        }

        const ordered = list.slice().reverse();
        logEl.innerHTML = ordered.map(ev => {
            const lvl = ev.level || 'info';
            const color = CONSOLE_COLORS[lvl] || CONSOLE_COLORS.info;
            const time = ev.at_label ? String(ev.at_label).split(' ').pop() : '';
            const sup = ev.supplier ? '[' + escapeHtml(ev.supplier) + '] ' : '';
            const step = ev.step ? '(' + escapeHtml(ev.step) + ') ' : '';
            const detail = ev.detail
                ? '<div class="scan-cron-console__detail">' + escapeHtml(ev.detail) + '</div>'
                : '';
            return '<div class="scan-cron-console__line" style="color:' + color + '">' +
                '<span class="scan-cron-console__time">[' + escapeHtml(time || '—') + ']</span> ' +
                sup + step + escapeHtml(String(ev.message || '').replace(/^\[PROGRESS:\d{1,3}%\]\s*/, '')) +
                detail +
            '</div>';
        }).join('');

        if (options.scrollBottom !== false) {
            logEl.scrollTop = logEl.scrollHeight;
        }
    }

    function flashEl(el) {
        if (!el) return;
        const card = el.closest('.scan-live-status__item');
        const target = card || el;
        target.classList.remove('is-flash');
        void target.offsetWidth;
        target.classList.add('is-flash');
        setTimeout(() => target.classList.remove('is-flash'), 900);
    }

    function setTextIfChanged(el, next) {
        if (!el) return false;
        const val = String(next || '—');
        if (el.textContent === val) return false;
        el.textContent = val;
        return true;
    }

    function renderLiveStatus(live, overview) {
        const lastEl = document.getElementById('cron-live-last-scan');
        const nextEl = document.getElementById('cron-live-next-scan');
        const dueEl = document.getElementById('cron-live-due');
        const sumEl = document.getElementById('cron-live-summary');
        const data = live && typeof live === 'object' ? live : {};

        if (setTextIfChanged(lastEl, data.last_scan_label)) flashEl(lastEl);
        if (nextEl) {
            const changed = setTextIfChanged(nextEl, data.next_scan_label);
            nextEl.classList.toggle('is-due', Number(data.suppliers_due || 0) > 0);
            if (changed) flashEl(nextEl);
        }
        if (dueEl) {
            const due = Number(data.suppliers_due || 0);
            const total = Number(data.suppliers_total || overview?.suppliers || 0);
            const dueText = due > 0 ? (due + ' / ' + total + ' de rulat') : ('0 · următoarea la ora setată');
            const changed = setTextIfChanged(dueEl, dueText);
            dueEl.classList.toggle('is-due', due > 0);
            if (changed) flashEl(dueEl);
        }
        if (setTextIfChanged(sumEl, data.summary)) flashEl(sumEl);
    }

    function renderSuppliersLive(tbody, items) {
        if (!tbody) return;
        const list = Array.isArray(items) ? items : [];
        if (!list.length) {
            tbody.innerHTML = '<tr><td colspan="6" class="scan-empty">Niciun furnizor activ în catalog.</td></tr>';
            return;
        }
        tbody.innerHTML = list.map(row => {
            const st = escapeHtml(row.status || 'pending');
            const dueClass = row.is_due ? ' scan-suppliers-live-row--due' : '';
            const profile = row.profile_url ? escapeHtml(row.profile_url) : '#';
            const name = escapeHtml(row.name || row.code);
            const code = escapeHtml(row.code || '');
            const file = row.file ? '<span class="scan-suppliers-live__file" title="' + escapeHtml(row.file) + '">' + escapeHtml(row.file) + '</span>' : '';
            const found = escapeHtml(row.found_label || row.validation_detail || '—');
            const next = escapeHtml(row.next_scan_label || '—');
            const nextClass = row.is_due ? ' scan-suppliers-live__next--due' : '';
            return '<tr class="scan-suppliers-live-row scan-suppliers-live-row--' + st + dueClass + '">' +
                '<td class="scan-suppliers-live__name">' +
                    '<a href="' + profile + '" class="scan-suppliers-live__link">' + name + '</a>' +
                    '<span class="scan-suppliers-live__code">' + code + '</span>' +
                '</td>' +
                '<td class="scan-suppliers-live__schedule">' + escapeHtml(row.schedule_label || '—') + '</td>' +
                '<td>' + escapeHtml(row.last_scan_label || row.synced_at_label || '—') + '</td>' +
                '<td class="scan-suppliers-live__next' + nextClass + '">' + next + '</td>' +
                '<td class="scan-suppliers-live__found">' + found + file + '</td>' +
                '<td><span class="scan-badge scan-badge--' + st + '">' + escapeHtml(row.status_label || st) + '</span></td>' +
            '</tr>';
        }).join('');
    }

    function renderPipeline(pipelineEl, items) {
        if (!pipelineEl) return;
        const list = Array.isArray(items) ? items : [];
        if (!list.length) {
            pipelineEl.innerHTML = '<div class="scan-pipeline-empty">Niciun furnizor.</div>';
            return;
        }
        pipelineEl.innerHTML = list.map(row => {
            const pct = Math.max(0, Math.min(100, Number(row.progress || 0)));
            const st = escapeHtml(row.status || 'pending');
            return '<div class="scan-pipeline-card scan-pipeline-card--' + st + '">' +
                '<div class="scan-pipeline-card__head">' +
                    '<span class="scan-pipeline-card__name">' + escapeHtml(row.name || row.code) + '</span>' +
                    '<span class="scan-badge scan-badge--' + st + '">' + escapeHtml(row.status_label || st) + '</span>' +
                '</div>' +
                '<div class="scan-pipeline-card__bar"><span style="width:' + pct + '%"></span></div>' +
                '<div class="scan-pipeline-card__meta">' +
                    '<span>' + escapeHtml(row.connection || '—') + '</span>' +
                    '<span>' + escapeHtml(row.step || '') + '</span>' +
                '</div>' +
                (row.file ? '<div class="scan-pipeline-card__file" title="' + escapeHtml(row.file) + '">' + escapeHtml(row.file) + '</div>' : '') +
            '</div>';
        }).join('');
    }

    function shortenScriptLabel(batch, url) {
        const raw = String(batch || url || '').trim();
        if (!raw) return '—';
        if (/\.bat$/i.test(raw)) {
            const parts = raw.split(/[/\\]/);
            return parts[parts.length - 1] || raw;
        }
        if (raw.length > 42) {
            return raw.slice(0, 39) + '…';
        }
        return raw;
    }

    function renderCronJobs(jobsEl, engineEl, tasks, health) {
        if (!jobsEl) return;
        const list = Array.isArray(tasks) ? tasks : [];
        const isTable = jobsEl.tagName === 'TBODY';

        if (!list.length) {
            if (isTable) {
                jobsEl.innerHTML = '<tr><td colspan="5" class="scan-empty">Niciun job Task Scheduler — panou gol până la configurare.</td></tr>';
            } else {
                jobsEl.innerHTML = '<div class="scan-activity-empty">Niciun job Task Scheduler configurat.</div>';
            }
            if (engineEl) {
                engineEl.innerHTML = '<span class="scan-engine-idle">○ Panou cron gol</span> — jurnal și joburi apar după prima rulare sau configurare Task Scheduler';
            }
            return;
        }
        if (isTable) {
            jobsEl.innerHTML = list.map(task => {
                const ok = task.script_ok !== false;
                const note = String(task.note || '').trim();
                const scriptFull = String(task.batch || task.url || '—');
                const rowClass = task.is_supplier ? ' scan-cron-jobs-row--highlight' : '';
                return '<tr class="scan-cron-jobs-row' + rowClass + '">' +
                    '<td class="scan-cron-jobs-row__name">' +
                        '<strong>' + escapeHtml(task.name) + '</strong>' +
                        (note ? '<span class="scan-cron-jobs-row__note">' + escapeHtml(note) + '</span>' : '') +
                    '</td>' +
                    '<td><span class="scan-cron-jobs-cat">' + escapeHtml(task.category || '—') + '</span></td>' +
                    '<td class="scan-cron-jobs-row__schedule">' + escapeHtml(task.schedule || '—') + '</td>' +
                    '<td class="scan-cron-jobs-row__script">' +
                        '<code title="' + escapeHtml(scriptFull) + '">' + escapeHtml(shortenScriptLabel(task.batch, task.url)) + '</code>' +
                    '</td>' +
                    '<td class="scan-cron-jobs-row__status">' +
                        '<span class="scan-badge scan-badge--' + (ok ? 'ok' : 'error') + '">' + (ok ? 'OK' : 'Lipsă .bat') + '</span>' +
                    '</td>' +
                '</tr>';
            }).join('');
        } else {
            jobsEl.innerHTML = list.map(task => {
                const ok = task.script_ok !== false;
                return '<div class="scan-cron-job">' +
                    '<div class="scan-cron-job__row">' +
                        '<span class="scan-cron-job__name">' + escapeHtml(task.name) + '</span>' +
                        '<span class="scan-badge scan-badge--' + (ok ? 'ok' : 'error') + '">' + (ok ? 'OK' : 'Lipsă') + '</span>' +
                    '</div>' +
                    '<div class="scan-cron-job__schedule">' + escapeHtml(task.schedule) + '</div>' +
                '</div>';
            }).join('');
        }

        if (engineEl) {
            const supplierOk = health && health.supplier_rclone;
            const okCount = list.filter(t => t.script_ok !== false).length;
            engineEl.innerHTML = supplierOk
                ? '<span class="scan-engine-ok">● Motor cron OK</span> — ' + okCount + '/' + list.length + ' scripturi .bat pe server · reîmprospătare automată'
                : '<span class="scan-engine-warn">● Verifică Task Scheduler</span> — lipsesc scripturi .bat pe acest mediu';
        }
    }

    function renderMotorBadge(live, health) {
        const badgeEl = document.getElementById('cron-motor-badge');
        if (!badgeEl) return;
        const data = live && typeof live === 'object' ? live : {};
        const label = data.motor_label || 'În așteptare';
        const ok = data.motor_ok === true;
        const idle = !ok && label.toLowerCase().includes('așteptare');
        badgeEl.className = 'scan-motor-badge ' + (ok ? 'scan-motor-badge--ok' : (idle ? 'scan-motor-badge--idle' : 'scan-motor-badge--warn'));
        badgeEl.innerHTML = '<span class="scan-motor-badge__dot"></span> ' + escapeHtml(label);
    }

    function renderStats(overview) {
        document.querySelectorAll('[data-cron-stat]').forEach(el => {
            const key = el.getAttribute('data-cron-stat');
            const next = overview && overview[key] != null ? String(overview[key]) : '0';
            if (el.textContent !== next) {
                el.textContent = next;
                el.classList.remove('is-updated');
                void el.offsetWidth;
                el.classList.add('is-updated');
            }
        });
    }

    function pushActivity(feedEl, msg, level) {
        if (!feedEl) return;
        const empty = feedEl.querySelector('.scan-activity-empty, .scan-activity-item--skeleton');
        if (empty) empty.remove();
        const item = document.createElement('div');
        item.className = 'scan-activity-item scan-activity-item--' + (level || 'info') + ' scan-activity-item--new';
        const now = new Date();
        const stamp = now.toLocaleDateString('ro-RO') + ' ' + now.toLocaleTimeString('ro-RO', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        item.innerHTML = '<span class="scan-activity-item__time">' + formatActivityTime(stamp) + '</span>' +
            '<span class="scan-activity-item__body"><span class="scan-activity-item__msg">' + escapeHtml(msg) + '</span></span>';
        feedEl.prepend(item);
        while (feedEl.children.length > 25) feedEl.lastElementChild?.remove();
    }

    function init(options) {
        const opts = options || {};
        const activityFeed = document.getElementById('cron-activity-feed');
        const consoleLog = document.getElementById('cron-console-log');
        const scanProgress = document.getElementById('cron-run-progress-panel');
        const scanProgressFill = document.getElementById('cron-scan-progress-fill');
        const scanProgressPct = document.getElementById('cron-scan-progress-pct');
        const scanProgressSteps = document.getElementById('cron-scan-progress-steps');
        const scanProgressLabel = document.getElementById('cron-scan-progress-label');
        const scanProgressMeta = document.getElementById('cron-scan-progress-meta');
        const scanIssuesWrap = document.getElementById('cron-scan-issues');
        const scanIssuesList = document.getElementById('cron-scan-issues-list');
        const scanIssuesCount = document.getElementById('cron-scan-issues-count');
        const supplierProgressTrack = document.getElementById('cron-supplier-progress-track');
        const progressDismissBtn = document.getElementById('cron-scan-progress-dismiss');
        const suppliersLive = document.getElementById('cron-suppliers-live');
        const cronJobs = document.getElementById('cron-cron-jobs');
        const engineStatus = document.getElementById('cron-engine-status');
        const lastRun = document.getElementById('cron-last-run');
        const toast = document.getElementById('cron-toast');
        const livePill = document.getElementById('cron-live-pill');
        const stopScanBtn = document.getElementById('cron-stop-scan');
        const stopScanBtnDash = document.getElementById('cron-dash-stop-scan');
        const apiAction = opts.apiAction || 'scan';
        const mirror = opts.mirror !== false ? '1' : '0';
        const analyze = opts.analyze !== false ? '1' : '0';
        let activityPollId = null;
        let loadInFlight = false;
        let scanRunInFlight = false;
        let lastPipeline = [];
        let lastProgressPayload = null;
        let progressDismissed = false;
        let progressHideTimer = null;

        function clearProgressHideTimer() {
            if (progressHideTimer) {
                clearTimeout(progressHideTimer);
                progressHideTimer = null;
            }
        }

        function dismissProgressPanel() {
            progressDismissed = true;
            clearProgressHideTimer();
            setScanProgress(false);
        }

        function shouldShowCompletedPanel(progress) {
            if (progressDismissed) return false;
            const prog = progress && typeof progress === 'object' ? progress : {};
            const phase = String(prog.phase || '').toLowerCase();
            if (!['done', 'error', 'stopped'].includes(phase)) return false;
            const msg = String(prog.message || '').trim();
            const pct = Number(prog.pct || 0);
            return msg !== '' || pct > 0;
        }

        function progressUiArgs(active, label, pct, phase, meta, activity, pipeline, progressPayload) {
            return [
                scanProgressFill,
                scanProgressPct,
                scanProgressLabel,
                scanProgressMeta,
                scanProgressSteps,
                scanProgress,
                active,
                label,
                pct,
                phase,
                meta,
                scanIssuesWrap,
                scanIssuesList,
                scanIssuesCount,
                activity || [],
                supplierProgressTrack,
                pipeline || lastPipeline,
                progressPayload || lastProgressPayload,
                progressDismissBtn
            ];
        }

        function setScanProgress(active, label, pct, phase, meta, activity, pipeline, progressPayload) {
            if (pipeline) lastPipeline = pipeline;
            if (progressPayload) lastProgressPayload = progressPayload;
            updateScanProgressBar.apply(null, progressUiArgs(active, label, pct, phase, meta, activity, pipeline, progressPayload));
        }

        function showToast(msg, isError) {
            if (!toast) return;
            toast.textContent = msg;
            toast.classList.remove('hidden', 'is-error', 'is-ok');
            toast.classList.add(isError ? 'is-error' : 'is-ok');
            setTimeout(() => toast.classList.add('hidden'), 4500);
        }

        function setScanning(active) {
            if (!livePill) return;
            livePill.classList.toggle('is-scanning', !!active);
            livePill.innerHTML = active
                ? '<span class="scan-live-pill__dot"></span> Scanează…'
                : '<span class="scan-live-pill__dot"></span> Live';
            updateStopButton(!!active);
        }

        function updateStopButton(active) {
            const show = !!active || !!activityPollId || scanRunInFlight;
            [stopScanBtn, stopScanBtnDash].forEach((btn) => {
                if (!btn) return;
                btn.disabled = !show;
                btn.classList.toggle('is-idle', !show);
                btn.hidden = false;
            });
        }

        let firstLoad = true;

        function applyPayload(result) {
            const cron = result.cron_dashboard || {};
            const overview = result.overview || cron.overview || {};
            const live = cron.live_status || {};
            const importSummary = result.import_summary || cron.import_summary || null;
            const activity = cron.activity || result.activity || [];

            renderActivity(activityFeed, activity, { animate: !firstLoad });
            renderConsoleLog(consoleLog, activity, { scrollBottom: false });
            renderSuppliersLive(suppliersLive, cron.supplier_pipeline);
            lastPipeline = Array.isArray(cron.supplier_pipeline) ? cron.supplier_pipeline : lastPipeline;
            renderLiveStatus(live, overview);
            renderMotorBadge(live, cron.scripts_health);
            renderCronJobs(cronJobs, engineStatus, cron.cron_tasks, cron.scripts_health);
            renderStats(overview);

            if (importSummary && typeof importSummary.published === 'number') {
                const published = importSummary.published;
                const el = document.querySelector('[data-cron-stat="validated_ok"]');
                if (el && published > 0) {
                    el.textContent = String(published);
                }
            }

            const label = result.scanned_at_label || cron.last_scan_label || '';
            if (lastRun) {
                lastRun.textContent = label ? label : '—';
            }

            if (engineStatus) {
                const parts = [];
                if (live.summary) parts.push(escapeHtml(live.summary));
                if (result.import_message) {
                    parts.push(escapeHtml(result.import_message));
                }
                const tasks = Array.isArray(cron.cron_tasks) ? cron.cron_tasks : [];
                const okCount = tasks.filter(t => t.script_ok !== false).length;
                if (tasks.length) {
                    parts.push(okCount + '/' + tasks.length + ' scripturi .bat pe server');
                }
                engineStatus.textContent = parts.length ? parts.join(' · ') : 'Motor cron și furnizori monitorizați.';
            }

            document.getElementById('cron-sync-page')?.classList.add('scan-page--loaded');
            firstLoad = false;

            renderIssuesPanel(scanIssuesWrap, scanIssuesList, scanIssuesCount, activity);

            if (typeof opts.onLoaded === 'function') {
                opts.onLoaded(result);
            }
        }

        function handleScanState(result, activity, progressPayload) {
            const running = !!(result && result.scan_running);
            const progress = progressPayload || result?.scan_progress || lastProgressPayload || {};
            const items = activity || result?.cron_dashboard?.activity || [];
            const pipeline = result?.cron_dashboard?.supplier_pipeline || lastPipeline;

            if (shouldAutoPoll(running, progress, items)) {
                setScanning(true);
                const prog = resolveProgressState(items, progress);
                setScanProgress(true, prog.label, prog.pct, prog.phase, prog.meta, items, pipeline, progress);
                if (!activityPollId) {
                    startActivityPoll();
                }
            } else if (running) {
                setScanning(true);
                const prog = resolveProgressState(items, progress);
                setScanProgress(true, prog.label, prog.pct, prog.phase, prog.meta, items, pipeline, progress);
                startActivityPoll();
            } else {
                const phase = String(progress.phase || '').toLowerCase();
                if (shouldShowCompletedPanel(progress)) {
                    const prog = resolveProgressState(items, progress);
                    setScanProgress(
                        true,
                        String(progress.message || prog.label || 'Scan finalizat'),
                        phase === 'done' ? 100 : (prog.pct ?? 0),
                        phase,
                        prog.meta,
                        items,
                        pipeline,
                        progress
                    );
                } else if (phase === 'error' || phase === 'stopped') {
                    setScanProgress(true, String(progress.message || 'Scan întrerupt'), 0, phase, '', items, pipeline, progress);
                } else {
                    setScanProgress(false);
                }
                if (!scanRunInFlight) {
                    stopActivityPoll();
                    setScanning(false);
                }
            }
            updateStopButton(running || scanRunInFlight || !!activityPollId);
        }

        async function load() {
            if (loadInFlight) {
                return;
            }
            loadInFlight = true;
            if (engineStatus) {
                engineStatus.textContent = 'Se încarcă panoul…';
            }
            try {
                const url = HUB + '?action=' + encodeURIComponent(apiAction) +
                    '&mirror=' + mirror + '&analyze=' + analyze;
                const result = await fetchJson(url, { timeoutMs: 25000 });
                applyPayload(result);
                handleScanState(result, result.cron_dashboard?.activity || [], result.scan_progress);
            } catch (e) {
                if (engineStatus) {
                    engineStatus.innerHTML = '<span class="scan-engine-warn">● Eroare încărcare</span> — ' + escapeHtml(e.message);
                }
                showToast(e.message, true);
            } finally {
                loadInFlight = false;
            }
        }

        async function unlockScan() {
            try {
                await fetchJson(HUB + '?action=scan_unlock', { method: 'POST', timeoutMs: 10000 });
                setScanning(false);
                setScanProgress(false);
                showToast('Scan deblocat.', false);
                await load();
            } catch (e) {
                showToast(e.message, true);
            }
        }

        async function stopScan() {
            if (!window.confirm('Oprești scanarea în curs? Importul și validarea vor fi întrerupte.')) {
                return;
            }

            const btns = ['cron-stop-scan', 'cron-dash-stop-scan', 'cron-run-local', 'cron-run-remote', 'cron-dash-scan-local', 'cron-dash-scan-remote'];
            btns.forEach(id => {
                const b = document.getElementById(id);
                if (b) b.disabled = true;
            });

            try {
                const result = await fetchJson(HUB + '?action=scan_stop', {
                    method: 'POST',
                    timeoutMs: 15000
                });
                stopActivityPoll();
                scanRunInFlight = false;
                setScanning(false);
                progressDismissed = false;
                setScanProgress(true, 'Scan oprit de utilizator', 0, 'stopped', '', [], lastPipeline, { phase: 'stopped', message: 'Scan oprit de utilizator' });
                pushActivity(activityFeed, result.message || 'Scan oprit.', 'warn');
                showToast(result.message || 'Scan oprit.', false);
                await load();
            } catch (e) {
                showToast(e.message, true);
            } finally {
                btns.forEach(id => {
                    const b = document.getElementById(id);
                    if (b) b.disabled = false;
                });
                updateStopButton(false);
            }
        }

        async function runReset() {
            if (!window.confirm('Oprești toate scanările automate, golești jurnalul și resetezi statisticile la zero?')) {
                return;
            }

            const btns = ['cron-reset-all', 'cron-refresh', 'cron-run-local', 'cron-run-remote']
                .map(id => document.getElementById(id));
            btns.forEach(b => { if (b) b.disabled = true; });
            setScanning(true);

            try {
                const result = await fetchJson(HUB + '?action=cron_reset', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'cron_reset' })
                });
                applyPayload(result);
                showToast(result.message || 'Reset finalizat — date la zero.', false);
            } catch (e) {
                showToast(e.message, true);
            } finally {
                btns.forEach(b => { if (b) b.disabled = false; });
                setScanning(false);
            }
        }

        function stopActivityPoll() {
            if (activityPollId) {
                clearInterval(activityPollId);
                activityPollId = null;
            }
        }

        function startActivityPoll() {
            stopActivityPoll();
            activityPollId = setInterval(pollActivityWhileScanning, POLL_ACTIVE_MS);
            updateStopButton(true);
            pollActivityWhileScanning();
        }

        async function waitForScanComplete() {
            const maxMs = 15 * 60 * 1000;
            const start = Date.now();
            while (Date.now() - start < maxMs) {
                const st = await fetchJson(HUB + '?action=scan_activity');
                if (!st.running) {
                    return;
                }
                await new Promise(resolve => setTimeout(resolve, 1500));
            }
            throw new Error('Scanul durează foarte mult — verifică jurnalul; poate continua în fundal.');
        }

        async function pollActivityWhileScanning() {
            try {
                const data = await fetchJson(HUB + '?action=scan_activity', { timeoutMs: 8000 });
                const items = data.activity || [];
                renderActivity(activityFeed, items, { animate: true });
                renderConsoleLog(consoleLog, items, { scrollBottom: true, keepContent: true });
                const prog = resolveProgressState(items, data.progress);
                lastProgressPayload = data.progress || lastProgressPayload;
                setScanProgress(true, prog.label, prog.pct, prog.phase, prog.meta, items, lastPipeline, data.progress);
                if (!data.running && !shouldAutoPoll(false, data.progress, items)) {
                    const phase = String((data.progress || {}).phase || prog.phase || '').toLowerCase();
                    if (shouldShowCompletedPanel(data.progress) || phase === 'error' || phase === 'stopped') {
                        setScanProgress(
                            true,
                            prog.label || String(data.progress?.message || 'Scan finalizat'),
                            phase === 'done' ? 100 : (prog.pct ?? 0),
                            phase || prog.phase,
                            prog.meta,
                            items,
                            lastPipeline,
                            data.progress
                        );
                    } else {
                        setScanProgress(false);
                    }
                    stopActivityPoll();
                    setScanning(false);
                    await load();
                }
            } catch (_) {
                /* ignore poll errors */
            }
        }

        async function runScan(remoteFtp) {
            if (scanRunInFlight) {
                showToast('Scan deja în curs — urmărește jurnalul.', true);
                return;
            }
            scanRunInFlight = true;
            progressDismissed = false;
            clearProgressHideTimer();
            const btns = ['cron-dash-refresh', 'cron-dash-scan-local', 'cron-dash-scan-remote', 'cron-refresh', 'cron-run-local', 'cron-run-remote']
                .map(id => document.getElementById(id));
            btns.forEach(b => { if (b) b.disabled = true; });
            setScanning(true);
            setScanProgress(true, remoteFtp
                ? 'Sync FTP + scanare — poate dura 30–90 secunde per furnizor…'
                : 'Scanare locală — verific foldere și import…', 3, 'run', 'Pas 1 · Pornire');

            if (consoleLog) {
                consoleLog.innerHTML = '';
            }
            pushActivity(activityFeed, remoteFtp ? 'Pornire sync FTP + scanare…' : 'Pornire scanare locală…', 'info');
            renderConsoleLog(consoleLog, [{
                at_label: new Date().toLocaleString('ro-RO'),
                message: remoteFtp ? 'Aștept răspuns server (FTP poate bloca 30–90s)…' : 'Scanare folder local…',
                level: 'info',
                step: 'run'
            }], { scrollBottom: true, keepContent: true });

            startActivityPoll();

            try {
                const result = await fetchJson(HUB + '?action=scan_run', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ remote_ftp: !!remoteFtp }),
                    timeoutMs: 60000
                });

                if (result.async) {
                    showToast(result.message || 'Scan pornit…', false);
                    await waitForScanComplete();
                } else {
                    stopActivityPoll();
                    applyPayload(result);
                    showToast(result.message || 'Scan finalizat', !result.success);
                }
            } catch (e) {
                const msg = e && e.message ? e.message : 'Eroare necunoscută la scanare.';
                const isLocked = /rulează deja|409/i.test(msg);
                showToast(
                    isLocked
                        ? msg + ' Apasă «Deblochează» sau așteaptă finalizarea.'
                        : msg,
                    true
                );
                pushActivity(activityFeed, msg, 'error');
                renderConsoleLog(consoleLog, [{
                    at_label: new Date().toLocaleString('ro-RO'),
                    message: msg,
                    level: 'error',
                    step: 'run'
                }], { scrollBottom: true, keepContent: true });
            } finally {
                btns.forEach(b => { if (b) b.disabled = false; });
                scanRunInFlight = false;
                await load();
                if (!activityPollId) {
                    setScanning(false);
                }
            }
        }

        document.getElementById('cron-console-clear')?.addEventListener('click', () => {
            if (consoleLog) {
                renderConsoleLog(consoleLog, [], {});
            }
        });

        progressDismissBtn?.addEventListener('click', () => dismissProgressPanel());

        document.getElementById('cron-dash-refresh')?.addEventListener('click', () => load());
        document.getElementById('cron-dash-scan-local')?.addEventListener('click', () => runScan(false));
        document.getElementById('cron-dash-scan-remote')?.addEventListener('click', () => runScan(true));
        document.getElementById('cron-refresh')?.addEventListener('click', () => load());
        document.getElementById('cron-reset-all')?.addEventListener('click', () => runReset());
        document.getElementById('cron-unlock-scan')?.addEventListener('click', () => unlockScan());
        document.getElementById('cron-stop-scan')?.addEventListener('click', () => stopScan());
        document.getElementById('cron-dash-stop-scan')?.addEventListener('click', () => stopScan());
        document.getElementById('cron-run-local')?.addEventListener('click', () => runScan(false));
        document.getElementById('cron-run-remote')?.addEventListener('click', () => runScan(true));

        setTimeout(() => load(), 0);
        updateStopButton(false);

        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState !== 'visible' || activityPollId || scanRunInFlight) {
                return;
            }
            fetchJson(HUB + '?action=scan_activity', { timeoutMs: 8000 })
                .then((data) => {
                    const items = data.activity || [];
                    if (shouldAutoPoll(data.running, data.progress, items)) {
                        renderActivity(activityFeed, items, { animate: true });
                        renderConsoleLog(consoleLog, items, { scrollBottom: true, keepContent: true });
                        handleScanState({ scan_running: data.running, cron_dashboard: { activity: items, supplier_pipeline: lastPipeline } }, items, data.progress);
                    }
                })
                .catch(() => {});
        });

        const intervalMs = Number(opts.refreshMs || 180000);
        if (intervalMs > 0) {
            setInterval(() => {
                if (document.visibilityState === 'visible' && !scanRunInFlight && !activityPollId) {
                    load();
                }
            }, intervalMs);
        }

        return { load, runScan, applyPayload };
    }

    global.BpaCronSyncDashboard = {
        init,
        renderActivity,
        renderConsoleLog,
        renderPipeline,
        renderSuppliersLive,
        renderLiveStatus,
        renderCronJobs,
        renderStats
    };
})(window);
