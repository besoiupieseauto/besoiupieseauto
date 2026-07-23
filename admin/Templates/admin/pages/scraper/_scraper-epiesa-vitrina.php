<?php
declare(strict_types=1);

use Besoiu\Services\Scraper\ScraperService;

$scraperMod = new ScraperService();
$scraperCatalogProducts = $scraperMod->epiesaListProducts();
$scraperCatalogCount = count($scraperCatalogProducts);
$scraperStats = $scraperMod->epiesaStats();
?>
<nav class="mb-4 flex flex-wrap gap-1 border-b pb-0">
    <button type="button" class="scraper-nav-tab is-active" data-panel="scanare">Scanare categorie</button>
    <button type="button" class="scraper-nav-tab" data-panel="vehicule">Arbore vehicule</button>
    <button type="button" class="scraper-nav-tab" data-panel="produse">Produse (<?= (int) $scraperCatalogCount ?>)</button>
</nav>

<section class="scraper-panel is-active" data-panel="scanare">
    <div class="sc-box">
        <p class="text-xs opacity-75 mb-3 rounded border border-emerald-200 bg-emerald-50 px-3 py-2" style="color:#065f46;">
            Motor: <strong>stealth-browser-mcp</strong> (Chrome local) — fără scrape.do. La scan se deschide browserul ~30–120s.
            <?php if (empty($scraperStats['stealth_browser_ok'])): ?>
                <br><strong class="text-red-700">Stealth browser neinstalat</strong> — rulează <code>php admin/tools/setup_stealth_browser.php</code>
            <?php endif; ?>
        </p>
        <form id="scraper-form" class="grid grid-cols-12 gap-4">
            <label class="col-span-12 md:col-span-6 sc-field">
                <span>Categorie preset</span>
                <select id="scraper-category" class="box h-10 w-full rounded-md border px-3 text-sm"></select>
            </label>
            <label class="col-span-12 md:col-span-3 sc-field">
                <span>Limită</span>
                <input type="number" id="scraper-limit" min="1" max="50" value="10" class="box h-10 w-full rounded-md border px-3 text-sm">
            </label>
            <label class="col-span-12 sc-field">
                <span>URL categorie ePiesa</span>
                <input type="url" id="scraper-url" class="box h-10 w-full rounded-md border px-3 text-sm"
                       value="https://www.epiesa.ro/gmtn1:auto/gmtn2:uleiuri-si-lubrifianti-auto/">
            </label>
        </form>
        <div class="mt-4 flex gap-2">
            <button type="button" id="scraper-run" class="sc-btn-primary">Scanează acum</button>
            <button type="button" id="scraper-refresh" class="sc-btn-outline">Reîncarcă ultimul scan</button>
        </div>
        <p id="scraper-meta" class="mt-3 text-xs opacity-70"></p>
    </div>
</section>

<section class="scraper-panel" data-panel="vehicule">
    <div class="sc-box">
        <p class="text-sm opacity-80 mb-3">
            Parcurge API-ul <code>selector.php</code> ePiesa: <strong>marca → model → generație → motorizare</strong> — toate combinațiile 1:1, salvate în <code>storage/scraper/epiesa/vehicle_tree.json</code>.
        </p>
        <div class="grid grid-cols-12 gap-3 mb-3">
            <label class="col-span-6 md:col-span-3 sc-field">
                <span>Max mărci (0=toate)</span>
                <input type="number" id="veh-max-brands" min="0" value="0" class="box h-9 w-full rounded-md border px-2 text-sm">
            </label>
            <label class="col-span-6 md:col-span-3 sc-field">
                <span>Max modele/marcă (0=toate)</span>
                <input type="number" id="veh-max-models" min="0" value="0" class="box h-9 w-full rounded-md border px-2 text-sm">
            </label>
            <label class="col-span-6 md:col-span-3 sc-field">
                <span>Pauză API (ms)</span>
                <input type="number" id="veh-delay-ms" min="0" max="2000" value="120" class="box h-9 w-full rounded-md border px-2 text-sm">
            </label>
            <label class="col-span-6 md:col-span-3 sc-field flex items-end gap-2 pb-1">
                <input type="checkbox" id="veh-resume" checked> <span>Resume (sari mărci deja salvate)</span>
            </label>
        </div>
        <div class="flex flex-wrap gap-2 mb-3">
            <button type="button" id="veh-preview" class="sc-btn-outline">Test lanț (1 marcă)</button>
            <button type="button" id="veh-crawl" class="sc-btn-primary">▶ Lansează bot complet (fundal)</button>
            <button type="button" id="veh-stop" class="sc-btn-outline">Oprește bot</button>
            <button type="button" id="veh-status" class="sc-btn-outline">Status</button>
        </div>
        <pre id="veh-output" class="text-xs bg-slate-50 border rounded-md p-3 overflow-auto max-h-80">Apasă Status sau Test…</pre>
    </div>
</section>

<section class="scraper-panel" data-panel="produse">
    <select id="scraper-filter-cat" class="box h-9 rounded-md border px-3 text-sm mb-3">
        <option value="toate">Toate categoriile</option>
        <?php foreach (($scraperStats['categories'] ?? []) as $scraperCat): ?>
            <?php if ((int) ($scraperCat['count'] ?? 0) > 0): ?>
        <option value="<?= scraper_admin_h((string) ($scraperCat['slug'] ?? '')) ?>">
            <?= scraper_admin_h((string) ($scraperCat['label'] ?? '')) ?> (<?= (int) ($scraperCat['count'] ?? 0) ?>)
        </option>
            <?php endif; ?>
        <?php endforeach; ?>
    </select>
    <div id="scraper-grid" class="sc-items-grid"></div>
</section>

<script>
(function vitrinaEpiesa() {
    const API = '/admin/api/scraper_endpoint.php';
    const grid = document.getElementById('scraper-grid');
    const meta = document.getElementById('scraper-meta');
    const urlInput = document.getElementById('scraper-url');
    const catSelect = document.getElementById('scraper-category');
    const filterCat = document.getElementById('scraper-filter-cat');
    const limitInput = document.getElementById('scraper-limit');

    function esc(s) { const d = document.createElement('div'); d.textContent = s ?? ''; return d.innerHTML; }

    function setPanel(name) {
        document.querySelectorAll('#sc-view-vitrina .scraper-nav-tab').forEach(t => t.classList.toggle('is-active', t.dataset.panel === name));
        document.querySelectorAll('#sc-view-vitrina .scraper-panel').forEach(p => p.classList.toggle('is-active', p.dataset.panel === name));
    }

    async function apiGet(view, params) {
        const q = new URLSearchParams({ view, ...(params || {}) });
        const r = await fetch(API + '?' + q, { credentials: 'include' });
        const j = await r.json();
        if (!j.success) throw new Error(j.message || 'Eroare');
        return j.data;
    }

    function renderProducts(products) {
        if (!grid) return;
        if (!products?.length) {
            grid.innerHTML = '<p class="text-sm opacity-60">Catalog gol.</p>';
            return;
        }
        grid.innerHTML = products.map(p => `
            <article class="sc-item-card">
                ${(p.image_local || p.image) ? `<img src="${esc(p.image_local || p.image)}" alt="" loading="lazy" onerror="this.style.display='none'">` : ''}
                <div class="sc-item-body">
                    <div class="sc-item-title">${esc(p.title)}</div>
                    <div class="sc-item-price">${esc(p.price || '—')}</div>
                    <a href="${esc(p.url)}" target="_blank" rel="noopener" class="text-xs text-primary">Vezi sursă</a>
                </div>
            </article>
        `).join('');
    }

    async function loadStats() {
        const stats = await apiGet('stats');
        if (catSelect) {
            catSelect.innerHTML = (stats.categories_presets || []).map(p =>
                `<option value="${esc(p.slug)}" data-url="${esc(p.url)}">${esc(p.label)}</option>`
            ).join('');
            const first = stats.categories_presets?.[0];
            if (first && urlInput) urlInput.value = first.url;
        }
        return stats;
    }

    async function loadCatalog(cat) {
        const data = await apiGet('catalog', cat && cat !== 'toate' ? { category: cat } : {});
        renderProducts(data.products || []);
    }

    async function runScan() {
        const btn = document.getElementById('scraper-run');
        if (btn) btn.disabled = true;
        try {
            const r = await fetch(API, {
                method: 'POST', credentials: 'include',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'scan', url: urlInput?.value || '', limit: parseInt(limitInput?.value || '10', 10) }),
            });
            const j = await r.json();
            if (!j.success) throw new Error(j.message);
            if (meta && j.data) {
                meta.textContent = [j.data.category_label, j.data.scraped_at].filter(Boolean).join(' · ');
            }
            await loadCatalog(filterCat?.value || 'toate');
            setPanel('produse');
        } catch (e) {
            alert(e.message || 'Eroare scan');
        } finally {
            if (btn) btn.disabled = false;
        }
    }

    document.querySelectorAll('#sc-view-vitrina .scraper-nav-tab').forEach(tab => {
        tab.addEventListener('click', () => setPanel(tab.dataset.panel || 'scanare'));
    });
    catSelect?.addEventListener('change', () => {
        const opt = catSelect.options[catSelect.selectedIndex];
        const url = opt?.getAttribute('data-url');
        if (url && urlInput) urlInput.value = url;
    });
    filterCat?.addEventListener('change', () => loadCatalog(filterCat.value).catch(() => {}));
    document.getElementById('scraper-run')?.addEventListener('click', runScan);
    document.getElementById('scraper-refresh')?.addEventListener('click', () => loadCatalog(filterCat?.value || 'toate'));

    const vehOut = document.getElementById('veh-output');
    function vehShow(obj) {
        if (vehOut) vehOut.textContent = typeof obj === 'string' ? obj : JSON.stringify(obj, null, 2);
    }
    async function vehPost(action, extra) {
        const r = await fetch(API, {
            method: 'POST', credentials: 'include',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action, ...(extra || {}) }),
        });
        const j = await r.json();
        if (!j.success) throw new Error(j.message || 'Eroare');
        return j.data;
    }
    document.getElementById('veh-preview')?.addEventListener('click', () => {
        vehShow('Test lanț…');
        vehPost('epiesa_vehicle_tree_preview', {}).then(vehShow).catch(e => vehShow(e.message));
    });
    document.getElementById('veh-status')?.addEventListener('click', () => {
        apiGet('vehicle_tree').then(vehShow).catch(e => vehShow(e.message));
    });
    document.getElementById('veh-crawl')?.addEventListener('click', async () => {
        const btn = document.getElementById('veh-crawl');
        if (btn) btn.disabled = true;
        const maxB = parseInt(document.getElementById('veh-max-brands')?.value || '0', 10);
        if (maxB === 0 && !confirm('Bot pentru TOATE mărcile — poate dura ore. Continui?')) {
            if (btn) btn.disabled = false;
            return;
        }
        vehShow('Pornesc bot în fundal…');
        try {
            const data = await vehPost('epiesa_vehicle_tree_start', {
                max_brands: maxB,
                max_models_per_brand: parseInt(document.getElementById('veh-max-models')?.value || '0', 10),
                delay_ms: parseInt(document.getElementById('veh-delay-ms')?.value || '120', 10),
                resume: !!document.getElementById('veh-resume')?.checked,
            });
            vehShow(data.status || data);
        } catch (e) {
            vehShow(e.message || String(e));
        } finally {
            if (btn) btn.disabled = false;
        }
    });
    document.getElementById('veh-stop')?.addEventListener('click', () => {
        vehPost('epiesa_vehicle_tree_stop', {}).then(d => vehShow(d.status || d)).catch(e => vehShow(e.message));
    });

    loadStats().then(() => loadCatalog('toate')).catch(() => {});
})();
</script>
