<?php
declare(strict_types=1);

/** @var string $apiJson */
?>
<script>
function showEnvPanelFeedback(message, type) {
    const el = document.getElementById('env-panel-feedback');
    if (!el) return;
    el.textContent = message || '';
    el.classList.remove('hidden', 'is-ok', 'is-err', 'is-info');
    if (!message) { el.classList.add('hidden'); return; }
    el.classList.add(type === 'error' ? 'is-err' : (type === 'info' ? 'is-info' : 'is-ok'));
}

function updateEnvStatusStrip(data) {
    const rapid = data.RAPIDAPI_AUTOPARTS_KEY || {};
    const scrape = data.SCRAPE_DO_TOKEN || {};
    const rapidEl = document.getElementById('status-rapidapi-key');
    const scrapeEl = document.getElementById('status-scrape-do-token');
    if (rapidEl) {
        rapidEl.textContent = rapid.set ? 'Cheie setată' : 'Lipsă cheie';
        rapidEl.className = rapid.set ? 'ok' : 'warn';
    }
    if (scrapeEl) {
        scrapeEl.textContent = scrape.set ? 'Token setat' : 'Lipsă token';
        scrapeEl.className = scrape.set ? 'ok' : 'warn';
    }
}

function openEnvPanel() {
    document.getElementById('env-keys')?.classList.remove('hidden');
    loadEnvKeysPanel();
}

document.getElementById('toggle-env-panel')?.addEventListener('click', function () {
    const panel = document.getElementById('env-keys');
    if (!panel) return;
    const willOpen = panel.classList.contains('hidden');
    panel.classList.toggle('hidden');
    if (willOpen) loadEnvKeysPanel();
});

async function envApiPost(action, body) {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const res = await fetch(<?= $apiJson ?>, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-Admin-CSRF': csrf,
        },
        credentials: 'same-origin',
        body: JSON.stringify({ action, ...body }),
    });
    const raw = await res.text();
    let json;
    try { json = raw ? JSON.parse(raw) : {}; } catch (e) {
        throw new Error('Răspuns invalid de la API Scraper.');
    }
    if (!res.ok || json.success === false) {
        throw new Error(json.message || ('HTTP ' + res.status));
    }
    return json;
}

async function loadEnvKeysPanel() {
    try {
        const res = await envApiPost('env_keys', {});
        const data = res.data || {};
        const rapid = data.RAPIDAPI_AUTOPARTS_KEY || {};
        const scrape = data.SCRAPE_DO_TOKEN || {};
        const rapidPreview = document.getElementById('env-rapidapi-preview');
        const scrapePreview = document.getElementById('env-scrape-do-preview');
        if (rapidPreview) {
            rapidPreview.textContent = rapid.set
                ? ('Setată: ' + (rapid.preview || '••••'))
                : 'Lipsă — adaugă cheia RapidAPI';
        }
        if (scrapePreview) {
            scrapePreview.textContent = scrape.set
                ? ('Setat: ' + (scrape.preview || '••••'))
                : 'Opțional';
        }
        const cacheEl = document.getElementById('env-cache-stats');
        const cache = data.tecdoc_cache || {};
        if (cacheEl) {
            cacheEl.textContent = 'Cache TecDoc: '
                + (cache.lookup_entries || 0) + ' lookup · '
                + (cache.url_cache_files || 0) + ' răspunsuri API'
                + (cache.quota_blocked ? ' · cotă blocată temporar' : '');
        }
        updateEnvStatusStrip(data);
    } catch (e) {
        showEnvPanelFeedback('Nu s-a putut încărca statusul cheilor: ' + e.message, 'error');
    }
}

function readEnvInputValue(id) {
    const el = document.getElementById(id);
    return el ? String(el.value || '').trim() : '';
}

async function saveEnvKey(buttonId, inputId, envKey, successLabel) {
    const btn = document.getElementById(buttonId);
    const val = readEnvInputValue(inputId);
    if (!val) {
        showEnvPanelFeedback('Câmpul este gol.', 'error');
        document.getElementById(inputId)?.focus();
        return;
    }
    const oldLabel = btn?.textContent || '';
    if (btn) { btn.disabled = true; btn.textContent = 'Se salvează…'; }
    showEnvPanelFeedback('Se salvează în Scraper/.env…', 'info');
    try {
        const res = await envApiPost('env_save', { key: envKey, value: val });
        showEnvPanelFeedback((res.message || 'Cheie salvată.') + ' Poți testa TecDoc mai jos.', 'ok');
        document.getElementById(inputId).value = '';
        await loadEnvKeysPanel();
    } catch (e) {
        showEnvPanelFeedback('Eroare la salvare: ' + e.message, 'error');
    } finally {
        if (btn) { btn.disabled = false; btn.textContent = oldLabel; }
    }
}

document.getElementById('env-save-rapidapi')?.addEventListener('click', () => {
    saveEnvKey('env-save-rapidapi', 'env-rapidapi-key', 'RAPIDAPI_AUTOPARTS_KEY', 'Salvează cheia TecDoc');
});
document.getElementById('env-save-scrape-do')?.addEventListener('click', () => {
    saveEnvKey('env-save-scrape-do', 'env-scrape-do-key', 'SCRAPE_DO_TOKEN', 'Salvează scrape.do');
});
document.getElementById('env-tecdoc-test-btn')?.addEventListener('click', async () => {
    const query = document.getElementById('env-tecdoc-test-query')?.value?.trim() || '079025830';
    const pre = document.getElementById('env-tecdoc-test-result');
    const btn = document.getElementById('env-tecdoc-test-btn');
    const oldLabel = btn?.textContent || '';
    if (pre) { pre.classList.remove('hidden'); pre.textContent = 'Se interoghează TecDoc…'; }
    if (btn) { btn.disabled = true; btn.textContent = 'Se testează…'; }
    try {
        const res = await envApiPost('tecdoc_test', { query, limit: 3 });
        const data = res.data || {};
        const lines = [(res.message || 'OK')];
        (data.items || []).forEach((it, i) => {
            lines.push((i + 1) + '. ' + (it.title || '—') + ' | ' + (it.brand || '') + ' | OEM: ' + (it.oem || it.sku || '—'));
        });
        if (pre) pre.textContent = lines.join('\n');
        await loadEnvKeysPanel();
    } catch (e) {
        if (pre) pre.textContent = 'Eroare: ' + e.message;
        showEnvPanelFeedback('Eroare test TecDoc: ' + e.message, 'error');
    } finally {
        if (btn) { btn.disabled = false; btn.textContent = oldLabel; }
    }
});
</script>
