<?php
declare(strict_types=1);

/**
 * Pagină modul __MODULE_NAME__
 * Ruta: /admin/__URL_SLUG__
 */
$pageTitle = '__MODULE_NAME__';
$apiUrl = '/admin/public/api/__MODULE_ID___endpoint.php';
?>
<div class="container-fluid py-3" id="module-__MODULE_ID__-page">
    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
        <h1 class="h4 mb-0"><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></h1>
        <span id="module-__MODULE_ID__-ollama-badge" class="badge text-bg-secondary">Ollama…</span>
    </div>

    <p class="text-muted small">
        Modul generat din <code>modules/_template/</code>. AI via <code>ModuleOllamaSupport</code>
        — același Ollama ca admin, import, scraper.
    </p>

    <div class="card mb-3">
        <div class="card-body">
            <label class="form-label small" for="module-__MODULE_ID__-ai-prompt">Test AI (Ollama)</label>
            <textarea id="module-__MODULE_ID__-ai-prompt" class="form-control form-control-sm mb-2" rows="2"
                placeholder="Ex: Rezumă ce face acest modul în 2 propoziții."></textarea>
            <button type="button" class="btn btn-sm btn-outline-primary" id="module-__MODULE_ID__-ai-run">
                Întreabă Ollama
            </button>
            <pre id="module-__MODULE_ID__-ai-out" class="small mt-2 mb-0 text-muted"></pre>
        </div>
    </div>

    <div id="module-__MODULE_ID__-loader" class="text-muted small">Se încarcă…</div>
    <div id="module-__MODULE_ID__-list" class="d-none"></div>
</div>

<script>
(function () {
    const apiUrl = <?= json_encode($apiUrl, JSON_UNESCAPED_UNICODE) ?>;
    const loader = document.getElementById('module-__MODULE_ID__-loader');
    const list = document.getElementById('module-__MODULE_ID__-list');
    const badge = document.getElementById('module-__MODULE_ID__-ollama-badge');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

    function post(body) {
        return fetch(apiUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Admin-CSRF': csrf
            },
            credentials: 'same-origin',
            body: JSON.stringify(body)
        }).then(function (r) { return r.json(); });
    }

    post({ type_product: 'ollama_status' })
        .then(function (res) {
            const d = res.data || {};
            const ok = d.ready === true;
            badge.textContent = d.message_ro || (ok ? 'Ollama OK' : 'Ollama indisponibil');
            badge.className = 'badge ' + (ok ? 'text-bg-success' : 'text-bg-warning');
        })
        .catch(function () {
            badge.textContent = 'Ollama: status necunoscut';
            badge.className = 'badge text-bg-secondary';
        });

    document.getElementById('module-__MODULE_ID__-ai-run').addEventListener('click', function () {
        const prompt = document.getElementById('module-__MODULE_ID__-ai-prompt').value.trim();
        const out = document.getElementById('module-__MODULE_ID__-ai-out');
        if (!prompt) {
            out.textContent = 'Introdu un prompt.';
            return;
        }
        out.textContent = 'Se procesează…';
        post({ type_product: 'ai_complete', prompt: prompt, task: '__MODULE_ID__' })
            .then(function (res) {
                const d = res.data || {};
                out.textContent = d.content || d.error || res.message || JSON.stringify(d);
            })
            .catch(function () {
                out.textContent = 'Eroare la apel AI.';
            });
    });

    post({ type_product: 'list', page: 1, per_page: 20 })
        .then(function (res) {
            loader.classList.add('d-none');
            list.classList.remove('d-none');
            const items = (res.data && res.data.items) ? res.data.items : [];
            if (!items.length) {
                list.innerHTML = '<p class="text-muted">Nicio înregistrare. Modulul e conectat — adaugă date în Repository.</p>';
                return;
            }
            list.innerHTML = '<pre class="small">' + JSON.stringify(items, null, 2) + '</pre>';
        })
        .catch(function () {
            loader.textContent = 'Eroare la API. Verifică proxy-ul și permisiunile.';
        });
})();
</script>
