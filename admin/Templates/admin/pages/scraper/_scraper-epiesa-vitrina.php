<?php
declare(strict_types=1);
$scraperCatalogProducts = EpiesaCatalog::listProducts();
$scraperCatalogCount = count($scraperCatalogProducts);
$scraperStats = EpiesaCatalog::stats();
?>
<nav class="mb-4 flex flex-wrap gap-1 border-b pb-0">
    <button type="button" class="scraper-nav-tab is-active" data-panel="scanare">Scanare categorie</button>
    <button type="button" class="scraper-nav-tab" data-panel="produse">Produse (<?= (int) $scraperCatalogCount ?>)</button>
</nav>

<section class="scraper-panel is-active" data-panel="scanare">
    <div class="sc-box">
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
                ${p.image ? `<img src="${esc(p.image)}" alt="" loading="lazy" onerror="this.style.display='none'">` : ''}
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

    loadStats().then(() => loadCatalog('toate')).catch(() => {});
})();
</script>
