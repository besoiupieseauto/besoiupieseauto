<?php
declare(strict_types=1);

use Besoiu\Core\AdminUrl;
use Besoiu\Modules\Import\BesoiupieseimportBridge;

$bridge = BesoiupieseimportBridge::status();
$apiUrl = AdminUrl::moduleApi('import', 'import_bridge_endpoint.php');
$scraperApi = htmlspecialchars($bridge['urls']['scraper_api'] ?? '', ENT_QUOTES, 'UTF-8');
$imageApi = htmlspecialchars($bridge['urls']['product_image'] ?? '', ENT_QUOTES, 'UTF-8');
$css = '/admin/modules/Import/assets/import-cards.css';
$js = '/admin/modules/Import/assets/import-cards.js';
?>
<link rel="stylesheet" href="<?= htmlspecialchars($css, ENT_QUOTES, 'UTF-8') ?>">

<div class="ic-page" id="import-cards-app">
    <header class="ic-hero">
        <div>
            <a href="/admin/import-system" class="ic-back">← Hub Import</a>
            <h1 class="ic-title">Produse generate — filtrare & ultra-filtrare</h1>
            <p class="ic-subtitle">
                Generează carduri complete din Base TecDoc sau liste preț furnizor.
                Filtrează după imagine, brand, furnizor, preț, compatibilități și sursă imagine.
            </p>
        </div>
        <?php if (!$bridge['available']): ?>
            <div class="ic-alert ic-alert--error">
                Proiectul besoiupieseimport nu este disponibil. Verifică calea din
                <code>admin/config/besoiupieseimport_sync.php</code>.
            </div>
        <?php endif; ?>
    </header>

    <div id="ic-index-status" class="ic-status" hidden></div>

    <section class="ic-panel">
        <h2 class="ic-panel-title">Sursă date</h2>
        <div class="ic-form-row">
            <div class="ic-field">
                <label for="ic-source-type">Tip sursă</label>
                <select id="ic-source-type">
                    <option value="upload">Upload CSV furnizor</option>
                    <option value="matc">Fișier din Fisierile Matc</option>
                    <option value="api">API direct (brand TecDoc)</option>
                </select>
            </div>
            <div class="ic-field" id="ic-upload-wrap">
                <label for="ic-file">Listă preț / Base CSV</label>
                <input type="file" id="ic-file" accept=".csv,.txt">
            </div>
            <div class="ic-field" id="ic-matc-wrap" hidden>
                <label for="ic-matc-file">Fișier Matc</label>
                <select id="ic-matc-file"><option value="">Se încarcă…</option></select>
            </div>
            <div class="ic-field" id="ic-brand-wrap" hidden>
                <label for="ic-brand">Brand TecDoc</label>
                <input type="text" id="ic-brand" placeholder="DAYCO, FAG, BEHR…">
            </div>
            <div class="ic-field ic-field--sm">
                <label for="ic-limit">Nr. carduri</label>
                <input type="number" id="ic-limit" value="50" min="1" max="200">
            </div>
            <div class="ic-field ic-field--check">
                <label>
                    <input type="checkbox" id="ic-include-no-image" checked>
                    Include fără imagine
                </label>
            </div>
            <button type="button" id="ic-generate" class="ic-btn ic-btn--primary" <?= $bridge['available'] ? '' : 'disabled' ?>>
                Generează carduri
            </button>
        </div>
        <div id="ic-load-status" class="ic-status" hidden></div>
    </section>

    <section class="ic-panel" id="ic-filters-panel" hidden>
        <h2 class="ic-panel-title">Filtrare & ultra-filtrare</h2>
        <div class="ic-filters">
            <div class="ic-field">
                <label for="ic-filter-image">Imagine</label>
                <select id="ic-filter-image">
                    <option value="all">Toate</option>
                    <option value="with">Cu imagine</option>
                    <option value="without">Fără imagine</option>
                    <option value="poze">Sursă: Poze local</option>
                    <option value="autopartner">Sursă: Autopartner</option>
                    <option value="scraped">Sursă: Scraper</option>
                </select>
            </div>
            <div class="ic-field">
                <label for="ic-filter-brand">Brand</label>
                <select id="ic-filter-brand"><option value="">Toate</option></select>
            </div>
            <div class="ic-field">
                <label for="ic-filter-supplier">Furnizor preț</label>
                <select id="ic-filter-supplier"><option value="">Toți</option></select>
            </div>
            <div class="ic-field">
                <label for="ic-filter-search">Căutare text</label>
                <input type="text" id="ic-filter-search" placeholder="SKU, titlu, cod…">
            </div>
            <div class="ic-field ic-field--sm">
                <label for="ic-filter-price-min">Preț min</label>
                <input type="number" id="ic-filter-price-min" min="0" step="1" placeholder="0">
            </div>
            <div class="ic-field ic-field--sm">
                <label for="ic-filter-price-max">Preț max</label>
                <input type="number" id="ic-filter-price-max" min="0" step="1" placeholder="∞">
            </div>
            <div class="ic-field ic-field--sm">
                <label for="ic-filter-compat-min">Compat. min</label>
                <input type="number" id="ic-filter-compat-min" min="0" step="1" placeholder="0">
            </div>
            <div class="ic-field">
                <label for="ic-sort">Sortare</label>
                <select id="ic-sort">
                    <option value="title-asc">Titlu A→Z</option>
                    <option value="title-desc">Titlu Z→A</option>
                    <option value="price-asc">Preț crescător</option>
                    <option value="price-desc">Preț descrescător</option>
                    <option value="compat-desc">Compatibilități ↓</option>
                    <option value="image-first">Cu imagine primele</option>
                </select>
            </div>
            <button type="button" id="ic-reset-filters" class="ic-btn ic-btn--ghost">Reset filtre</button>
        </div>

        <div class="ic-toolbar">
            <div class="ic-summary" id="ic-summary">0 carduri</div>
            <div class="ic-toolbar-actions">
                <div class="ic-field ic-field--sm ic-field--inline">
                    <label for="ic-min-score">Scor Ollama %</label>
                    <input type="number" id="ic-min-score" value="70" min="0" max="100">
                </div>
                <button type="button" id="ic-scrape-all" class="ic-btn ic-btn--amber" disabled>
                    Scraping — toate fără imagine
                </button>
            </div>
        </div>

        <div class="ic-progress" id="ic-progress" hidden>
            <div class="ic-progress-bar" id="ic-progress-bar"></div>
        </div>

        <div class="ic-grid" id="ic-grid"></div>
        <p id="ic-empty" class="ic-empty" hidden>Niciun produs nu corespunde filtrelor curente.</p>
    </section>
</div>

<script>
window.IMPORT_CARDS_CONFIG = {
    apiUrl: <?= json_encode($apiUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
    scraperApi: <?= json_encode($bridge['urls']['scraper_api'] ?? '', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
    imageApi: <?= json_encode($bridge['urls']['product_image'] ?? '', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
    available: <?= $bridge['available'] ? 'true' : 'false' ?>
};
</script>
<?php if (!empty($bridge['urls']['scraper'])): ?>
<script src="<?= htmlspecialchars(rtrim($bridge['urls']['scraper'], '/') . '/assets/product-search-passes.js', ENT_QUOTES, 'UTF-8') ?>"></script>
<?php endif; ?>
<script src="<?= htmlspecialchars($js, ENT_QUOTES, 'UTF-8') ?>"></script>
