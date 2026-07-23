<?php declare(strict_types=1); ?>
<div class="sys-err-page grid grid-cols-12 gap-4 mt-6" id="system-errors-root">
    <div id="system-errors-toast" class="hidden fixed right-5 top-5 z-[60] rounded-md border bg-background px-4 py-3 text-sm shadow max-w-md"></div>

    <div class="col-span-12 sys-err-hero">
        <div>
            <h2 class="text-lg font-bold m-0">Jurnal erori — activ</h2>
            <p class="text-sm opacity-70 mb-0 mt-1">Vezi doar erorile ne-arhivate. Curăță jurnalul arhivând pentru AI — <strong>nu se șterge nimic</strong>, rămâne în arhivă pentru învățare.</p>
        </div>
        <button type="button" id="system-errors-refresh" class="sys-err-btn sys-err-btn--ghost">↻ Reîncarcă</button>
    </div>

    <div class="col-span-12 sys-err-kpis">
        <div class="sys-err-kpi">
            <div class="sys-err-kpi__lbl">Jurnal activ</div>
            <div class="sys-err-kpi__val" id="stat-total">0</div>
        </div>
        <div class="sys-err-kpi sys-err-kpi--danger">
            <div class="sys-err-kpi__lbl">Nerezolvate</div>
            <div class="sys-err-kpi__val" id="stat-unresolved">0</div>
        </div>
        <div class="sys-err-kpi">
            <div class="sys-err-kpi__lbl">Azi (activ)</div>
            <div class="sys-err-kpi__val" id="stat-today">0</div>
        </div>
        <div class="sys-err-kpi sys-err-kpi--muted">
            <div class="sys-err-kpi__lbl">Arhivate AI</div>
            <div class="sys-err-kpi__val" id="stat-archived">0</div>
        </div>
    </div>

    <div class="col-span-12 sys-err-archive-panel box p-4">
        <div class="sys-err-archive-panel__head">
            <div>
                <h3 class="text-sm font-bold m-0">Curăță jurnalul — arhivează pentru AI</h3>
                <p class="text-xs opacity-70 mt-1 mb-0">Alege perioada, apoi arhivează. Jurnalul activ se golește vizual; datele merg în <code>robot/data/ai_context/system_errors_archive/</code>.</p>
            </div>
            <div class="sys-err-archive-period" id="sys-err-archive-period" role="group" aria-label="Perioadă arhivare">
                <button type="button" class="sys-err-pill-btn" data-archive-period="today">Azi</button>
                <button type="button" class="sys-err-pill-btn" data-archive-period="yesterday">Ieri</button>
                <button type="button" class="sys-err-pill-btn" data-archive-period="week">7 zile</button>
                <button type="button" class="sys-err-pill-btn is-active" data-archive-period="all">Tot jurnalul activ</button>
            </div>
        </div>
        <div class="sys-err-archive-panel__actions">
            <span class="sys-err-archive-preview" id="sys-err-archive-preview">Se calculează…</span>
            <button type="button" id="sys-err-archive-btn" class="sys-err-btn sys-err-btn--archive">
                Arhivează pentru AI &amp; curăță jurnalul
            </button>
        </div>
        <div id="sys-err-recent-archives" class="sys-err-recent-archives text-xs opacity-80 mt-3"></div>
    </div>

    <div class="col-span-12 lg:col-span-4">
        <div class="box p-4 h-full">
            <h3 class="text-sm font-bold mb-3">Pe canal (7 zile, deschise)</h3>
            <div id="system-errors-by-channel" class="sys-err-channels text-sm"></div>
        </div>
    </div>

    <div class="col-span-12 lg:col-span-8">
        <div class="box p-4">
            <form id="system-errors-filters" class="grid grid-cols-12 gap-3">
                <div class="col-span-12">
                    <label class="text-xs opacity-70 block mb-1">Perioadă afișare</label>
                    <div class="sys-err-date-pills" id="sys-err-date-pills" role="group" aria-label="Filtru zile">
                        <button type="button" class="sys-err-pill-btn is-active" data-date-preset="">Tot timpul</button>
                        <button type="button" class="sys-err-pill-btn" data-date-preset="today">Azi</button>
                        <button type="button" class="sys-err-pill-btn" data-date-preset="yesterday">Ieri</button>
                        <button type="button" class="sys-err-pill-btn" data-date-preset="week">7 zile</button>
                    </div>
                    <input type="hidden" name="date_preset" id="sys-err-date-preset" value="">
                </div>
                <div class="col-span-6 sm:col-span-3">
                    <label class="text-xs opacity-70">Canal</label>
                    <select name="channel" class="form-control mt-1 w-full rounded-md border px-2 py-2 text-sm">
                        <option value="">Toate</option>
                        <option value="cron">cron</option>
                        <option value="queue">queue</option>
                        <option value="tecdoc">tecdoc</option>
                        <option value="rapidapi">rapidapi</option>
                        <option value="ai">ai</option>
                        <option value="import">import</option>
                        <option value="general">general</option>
                    </select>
                </div>
                <div class="col-span-6 sm:col-span-3">
                    <label class="text-xs opacity-70">Nivel</label>
                    <select name="level" class="form-control mt-1 w-full rounded-md border px-2 py-2 text-sm">
                        <option value="">Toate</option>
                        <option value="critical">critical</option>
                        <option value="error">error</option>
                        <option value="warning">warning</option>
                        <option value="info">info</option>
                    </select>
                </div>
                <div class="col-span-12 sm:col-span-6">
                    <label class="text-xs opacity-70">Căutare</label>
                    <input type="search" name="q" placeholder="mesaj, fișier…" class="form-control mt-1 w-full rounded-md border px-2 py-2 text-sm">
                </div>
                <div class="col-span-12">
                    <button type="submit" class="sys-err-btn sys-err-btn--primary">Filtrează</button>
                </div>
            </form>
        </div>
    </div>

    <div class="col-span-12">
        <div class="box p-4">
            <nav class="sys-err-tabs" role="tablist" id="sys-err-tabs">
                <button type="button" class="sys-err-tab is-active" data-tab="recent">Ultimele 10</button>
                <button type="button" class="sys-err-tab" data-tab="open">Nerezolvate</button>
                <button type="button" class="sys-err-tab" data-tab="resolved">Rezolvate</button>
                <button type="button" class="sys-err-tab" data-tab="all">Toate</button>
            </nav>

            <div class="sys-err-toolbar" id="sys-err-toolbar">
                <label class="sys-err-toolbar__select">
                    <input type="checkbox" id="sys-err-select-all" aria-label="Selectează toate pe pagină">
                    Selectează pagina
                </label>
                <button type="button" id="sys-err-resolve-selected" class="sys-err-btn sys-err-btn--primary" disabled>
                    Marchează selectate (<span id="sys-err-selected-count">0</span>)
                </button>
                <button type="button" id="sys-err-resolve-all" class="sys-err-btn sys-err-btn--danger">
                    Marchează toate nerezolvate
                </button>
            </div>

            <div id="system-errors-list" class="sys-err-list">
                <div class="sys-err-empty">Se încarcă…</div>
            </div>
            <div id="system-errors-pagination" class="sys-err-pagination hidden"></div>
            <div id="system-errors-meta" class="sys-err-meta"></div>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';

    var ENDPOINT = '/admin/api/system_errors_endpoint.php';
    var form = document.getElementById('system-errors-filters');
    var listEl = document.getElementById('system-errors-list');
    var metaEl = document.getElementById('system-errors-meta');
    var toast = document.getElementById('system-errors-toast');
    var byChannelEl = document.getElementById('system-errors-by-channel');
    var paginationEl = document.getElementById('system-errors-pagination');
    var selectAllEl = document.getElementById('sys-err-select-all');
    var resolveSelectedBtn = document.getElementById('sys-err-resolve-selected');
    var resolveAllBtn = document.getElementById('sys-err-resolve-all');
    var selectedCountEl = document.getElementById('sys-err-selected-count');
    var datePresetInput = document.getElementById('sys-err-date-preset');
    var archivePreviewEl = document.getElementById('sys-err-archive-preview');
    var archiveBtn = document.getElementById('sys-err-archive-btn');
    var recentArchivesEl = document.getElementById('sys-err-recent-archives');

    var state = {
        tab: 'recent',
        page: 1,
        perPage: 10,
        total: 0,
        items: [],
        selected: {},
        archivePeriod: 'all'
    };

    function escapeHtml(v) {
        return String(v == null ? '' : v).replace(/[&<>"']/g, function (c) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];
        });
    }

    function showToast(msg, err) {
        if (!toast) return;
        toast.textContent = msg;
        toast.classList.remove('hidden', 'text-danger', 'border-emerald-300', 'bg-emerald-50');
        if (err) toast.classList.add('text-danger');
        else toast.classList.add('border-emerald-300', 'bg-emerald-50');
        setTimeout(function () { toast.classList.add('hidden'); }, 5000);
    }

    function filterFields() {
        var out = {};
        if (!form) return out;
        new FormData(form).forEach(function (val, key) {
            if (String(val).trim() !== '') out[key] = val;
        });
        return out;
    }

    function tabPayload() {
        var f = filterFields();
        var payload = { type_product: 'list', offset: 0, limit: state.perPage };
        Object.assign(payload, f);

        if (state.tab === 'recent') {
            payload.unresolved_only = true;
            payload.limit = 10;
            payload.offset = 0;
        } else if (state.tab === 'open') {
            payload.unresolved_only = true;
            payload.limit = state.perPage;
            payload.offset = (state.page - 1) * state.perPage;
        } else if (state.tab === 'resolved') {
            payload.resolved_only = true;
            payload.limit = state.perPage;
            payload.offset = (state.page - 1) * state.perPage;
        } else {
            payload.limit = state.perPage;
            payload.offset = (state.page - 1) * state.perPage;
        }
        return payload;
    }

    function apiCall(payload) {
        return fetch(ENDPOINT, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify(payload)
        }).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, json: j }; }); })
          .then(function (res) {
            if (!res.ok || !res.json.success) throw new Error((res.json && res.json.message) || 'Eroare API');
            return res.json;
          });
    }

    function levelPillClass(level) {
        if (level === 'critical' || level === 'error') return 'sys-err-pill--level-error';
        if (level === 'warning') return 'sys-err-pill--level-warning';
        return 'sys-err-pill--level-info';
    }

    function renderStats(stats) {
        var s = stats || {};
        var set = function (id, v) {
            var el = document.getElementById(id);
            if (el) el.textContent = Number(v || 0).toLocaleString('ro-RO');
        };
        set('stat-total', s.active != null ? s.active : s.total);
        set('stat-unresolved', s.unresolved);
        set('stat-today', s.today);
        set('stat-archived', s.archived_total);

        if (!byChannelEl) return;
        var channels = s.by_channel || {};
        var keys = Object.keys(channels);
        if (!keys.length) {
            byChannelEl.innerHTML = '<p class="opacity-60">Nicio eroare deschisă (7 zile).</p>';
            return;
        }
        var max = Math.max.apply(null, keys.map(function (k) { return channels[k]; })) || 1;
        byChannelEl.innerHTML = keys.map(function (ch) {
            var n = channels[ch];
            var pct = Math.round((n / max) * 100);
            return '<div class="sys-err-channel-bar">'
                + '<span class="sys-err-pill sys-err-pill--channel">' + escapeHtml(ch) + '</span>'
                + '<div class="sys-err-channel-bar__track"><div class="sys-err-channel-bar__fill" style="width:' + pct + '%"></div></div>'
                + '<strong>' + n + '</strong></div>';
        }).join('');
    }

    function renderRecentArchives(archive) {
        if (!recentArchivesEl) return;
        var list = (archive && archive.recent) || [];
        if (!list.length) {
            recentArchivesEl.innerHTML = 'Nicio arhivă încă.';
            return;
        }
        recentArchivesEl.innerHTML = '<strong>Arhive recente:</strong> '
            + list.slice(0, 5).map(function (a) {
                return escapeHtml(a.period_label || a.period || '') + ' · ' + Number(a.count || 0) + ' erori · ' + escapeHtml(a.archived_at || '');
            }).join(' · ');
    }

    function updateArchivePreview() {
        if (!archivePreviewEl) return;
        archivePreviewEl.textContent = 'Se calculează…';
        var payload = Object.assign({ type_product: 'archive_preview', period: state.archivePeriod }, filterFields());
        apiCall(payload).then(function (res) {
            archivePreviewEl.textContent = res.message || (res.count + ' erori');
            if (archiveBtn) archiveBtn.disabled = !(res.count > 0);
        }).catch(function (e) {
            archivePreviewEl.textContent = e.message;
            if (archiveBtn) archiveBtn.disabled = true;
        });
    }

    function loadArchiveStatus() {
        return apiCall({ type_product: 'archive_status' }).then(function (res) {
            renderRecentArchives(res.archive);
            updateArchivePreview();
        });
    }

    function updateSelectionUi() {
        var ids = Object.keys(state.selected).filter(function (k) { return state.selected[k]; });
        if (selectedCountEl) selectedCountEl.textContent = String(ids.length);
        if (resolveSelectedBtn) resolveSelectedBtn.disabled = ids.length === 0;
        if (selectAllEl) {
            var openOnPage = state.items.filter(function (it) { return !it.is_resolved; });
            selectAllEl.checked = openOnPage.length > 0 && openOnPage.every(function (it) { return state.selected[it.id]; });
        }
    }

    function renderList(items, total) {
        state.items = items || [];
        state.total = total || 0;
        state.selected = {};

        if (!listEl) return;

        var showBulk = state.tab !== 'resolved';
        var toolbar = document.getElementById('sys-err-toolbar');
        if (toolbar) toolbar.style.display = showBulk ? '' : 'none';

        if (!items.length) {
            listEl.innerHTML = '<div class="sys-err-empty">Jurnal activ gol pentru filtrele curente — erorile noi vor apărea aici.</div>';
        } else {
            listEl.innerHTML = items.map(function (row) {
                var resolved = !!row.is_resolved;
                var lvl = escapeHtml(row.level || 'info');
                var cardClass = 'sys-err-card sys-err-card--' + lvl + (resolved ? ' sys-err-card--resolved' : '');
                var msg = String(row.message || '');
                var long = msg.length > 160;
                var check = (!resolved && showBulk)
                    ? '<input type="checkbox" class="sys-err-card__check sys-err-row-check" data-id="' + Number(row.id) + '" aria-label="Selectează">'
                    : '<span class="sys-err-card__check"></span>';
                var action = resolved
                    ? '<span class="sys-err-pill sys-err-pill--done">rezolvat</span>'
                    : '<button type="button" class="sys-err-btn sys-err-btn--primary sys-err-resolve-one" data-id="' + Number(row.id) + '">Corectează</button>';

                return '<article class="' + cardClass + '" data-id="' + Number(row.id) + '">'
                    + check
                    + '<div>'
                    + '<div class="sys-err-card__head">'
                    + '<span class="sys-err-pill ' + levelPillClass(row.level) + '">' + lvl + '</span>'
                    + '<span class="sys-err-pill sys-err-pill--channel">' + escapeHtml(row.channel) + '</span>'
                    + '<span class="sys-err-card__time">' + escapeHtml(row.created_at) + '</span>'
                    + (resolved ? '' : '<span class="sys-err-pill sys-err-pill--open">deschis</span>')
                    + '</div>'
                    + '<p class="sys-err-card__msg' + (long ? ' is-collapsed' : '') + '" data-full="' + escapeHtml(msg) + '">' + escapeHtml(msg) + '</p>'
                    + (long ? '<button type="button" class="sys-err-btn sys-err-btn--ghost sys-err-toggle-msg text-xs mt-1">Vezi tot mesajul</button>' : '')
                    + (row.source_file ? '<div class="sys-err-card__source">' + escapeHtml(row.source_file) + '</div>' : '')
                    + '</div>'
                    + '<div class="sys-err-card__actions">' + action + '</div>'
                    + '</article>';
            }).join('');
        }

        if (metaEl) {
            var tabLbl = { recent: 'Ultimele 10', open: 'Nerezolvate', resolved: 'Rezolvate', all: 'Toate' }[state.tab] || '';
            metaEl.textContent = tabLbl + ' · ' + items.length + ' afișate din ' + Number(total).toLocaleString('ro-RO') + ' (jurnal activ)';
        }

        renderPagination();
        updateSelectionUi();
    }

    function renderPagination() {
        if (!paginationEl) return;
        if (state.tab === 'recent') {
            paginationEl.classList.add('hidden');
            paginationEl.innerHTML = '';
            return;
        }
        var pages = Math.max(1, Math.ceil(state.total / state.perPage));
        if (pages <= 1) {
            paginationEl.classList.add('hidden');
            return;
        }
        paginationEl.classList.remove('hidden');
        var html = '';
        for (var p = 1; p <= pages && p <= 20; p++) {
            html += '<button type="button" class="sys-err-btn sys-err-btn--ghost sys-err-page' + (p === state.page ? ' is-active' : '') + '" data-page="' + p + '">' + p + '</button>';
        }
        paginationEl.innerHTML = html;
    }

    function loadList() {
        return apiCall(tabPayload()).then(function (result) {
            renderStats(result.stats);
            renderList(result.data || [], result.total || 0);
        });
    }

    function resolveIds(ids) {
        if (!ids.length) return Promise.resolve();
        return apiCall({ type_product: 'resolve_bulk', ids: ids }).then(function (res) {
            showToast(res.message || 'Marcate ca rezolvate.');
            return loadList();
        });
    }

    function resolveAllOpen() {
        if (!confirm('Marchezi TOATE erorile nerezolvate (cu filtrele curente) ca rezolvate?')) return;
        var payload = Object.assign({ type_product: 'resolve_all' }, filterFields());
        return apiCall(payload).then(function (res) {
            showToast(res.message || 'Gata.');
            state.page = 1;
            return loadList();
        });
    }

    function runArchive() {
        var labels = { today: 'AZI', yesterday: 'IERI', week: 'ULTIMELE 7 ZILE', all: 'TOT JURNALUL ACTIV' };
        var lbl = labels[state.archivePeriod] || state.archivePeriod;
        if (!confirm('Arhivezi erorile din perioada: ' + lbl + '?\n\nNu se șterg — merg în arhiva AI și dispar din jurnalul activ.')) return;

        archiveBtn.disabled = true;
        var payload = Object.assign({ type_product: 'archive', period: state.archivePeriod }, filterFields());
        return apiCall(payload).then(function (res) {
            showToast(res.message || 'Arhivat.');
            state.page = 1;
            renderRecentArchives(res.archive);
            updateArchivePreview();
            return loadList();
        }).catch(function (e) {
            showToast(e.message, true);
        }).finally(function () {
            if (archiveBtn) archiveBtn.disabled = false;
        });
    }

    document.getElementById('system-errors-refresh').addEventListener('click', function () {
        loadList().catch(function (e) { showToast(e.message, true); });
        loadArchiveStatus().catch(function () {});
    });

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        state.page = 1;
        loadList().catch(function (err) { showToast(err.message, true); });
        updateArchivePreview();
    });

    document.getElementById('sys-err-date-pills').addEventListener('click', function (e) {
        var btn = e.target.closest('[data-date-preset]');
        if (!btn) return;
        var preset = btn.getAttribute('data-date-preset') || '';
        if (datePresetInput) datePresetInput.value = preset;
        document.querySelectorAll('#sys-err-date-pills [data-date-preset]').forEach(function (b) {
            b.classList.toggle('is-active', b === btn);
        });
        state.page = 1;
        loadList().catch(function (err) { showToast(err.message, true); });
    });

    document.getElementById('sys-err-archive-period').addEventListener('click', function (e) {
        var btn = e.target.closest('[data-archive-period]');
        if (!btn) return;
        state.archivePeriod = btn.getAttribute('data-archive-period') || 'all';
        document.querySelectorAll('#sys-err-archive-period [data-archive-period]').forEach(function (b) {
            b.classList.toggle('is-active', b === btn);
        });
        updateArchivePreview();
    });

    if (archiveBtn) {
        archiveBtn.addEventListener('click', function () {
            runArchive();
        });
    }

    document.getElementById('sys-err-tabs').addEventListener('click', function (e) {
        var btn = e.target.closest('[data-tab]');
        if (!btn) return;
        state.tab = btn.getAttribute('data-tab') || 'recent';
        state.page = 1;
        state.perPage = state.tab === 'recent' ? 10 : 25;
        document.querySelectorAll('.sys-err-tab').forEach(function (t) {
            t.classList.toggle('is-active', t === btn);
        });
        loadList().catch(function (err) { showToast(err.message, true); });
    });

    selectAllEl.addEventListener('change', function () {
        var checked = selectAllEl.checked;
        state.items.forEach(function (it) {
            if (!it.is_resolved) state.selected[it.id] = checked;
        });
        listEl.querySelectorAll('.sys-err-row-check').forEach(function (cb) {
            cb.checked = checked;
        });
        updateSelectionUi();
    });

    listEl.addEventListener('change', function (e) {
        var cb = e.target.closest('.sys-err-row-check');
        if (!cb) return;
        var id = Number(cb.getAttribute('data-id') || 0);
        state.selected[id] = cb.checked;
        updateSelectionUi();
    });

    listEl.addEventListener('click', function (e) {
        var toggle = e.target.closest('.sys-err-toggle-msg');
        if (toggle) {
            var msg = toggle.previousElementSibling;
            if (msg) {
                msg.classList.toggle('is-collapsed');
                toggle.textContent = msg.classList.contains('is-collapsed') ? 'Vezi tot mesajul' : 'Ascunde';
            }
            return;
        }
        var one = e.target.closest('.sys-err-resolve-one');
        if (one) {
            var id = Number(one.getAttribute('data-id') || 0);
            if (id > 0) resolveIds([id]).catch(function (err) { showToast(err.message, true); });
        }
    });

    paginationEl.addEventListener('click', function (e) {
        var pbtn = e.target.closest('.sys-err-page');
        if (!pbtn) return;
        state.page = Number(pbtn.getAttribute('data-page') || 1);
        loadList().catch(function (err) { showToast(err.message, true); });
    });

    resolveSelectedBtn.addEventListener('click', function () {
        var ids = Object.keys(state.selected).filter(function (k) { return state.selected[k]; }).map(Number);
        resolveIds(ids).catch(function (err) { showToast(err.message, true); });
    });

    resolveAllBtn.addEventListener('click', function () {
        resolveAllOpen().catch(function (err) { showToast(err.message, true); });
    });

    loadList().catch(function (e) { showToast(e.message, true); });
    loadArchiveStatus().catch(function () {});
})();
</script>
