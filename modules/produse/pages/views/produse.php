<?php
declare(strict_types=1);

use Besoiu\Core\AdminUrl;
use Besoiu\Services\Products\ProduseService;
use Besoiu\Services\Products\ProductFacetsService;
use Besoiu\Services\AdaosComercial\AdaosComercialService;

$asyncClientUrl = AdminUrl::asset('js/besoiu-async-client.js');

require_once __DIR__ . '/_produse-list-helpers.php';

$dualTitleHelper = dirname(__DIR__, 4) . '/app/Legacy/product_dual_title.php';
if (is_file($dualTitleHelper)) {
    require_once $dualTitleHelper;
}

$service = new ProduseService();
$markupRulesForModal = (new AdaosComercialService())->getAll();
$vitrinaCountAdmin = $service->countVitrinaProducts();
$produseSectionActive = 'lista';
$produseNavVitrinaCount = $vitrinaCountAdmin;
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 10;
$allowedListFilters = ['no_image'];
$listFilter = trim((string) ($_GET['filter'] ?? ''));
if ($listFilter !== '' && !in_array($listFilter, $allowedListFilters, true)) {
    $listFilter = '';
}
$allowedFilterKeys = ['q', 'category', 'subcategory', 'marca', 'brand', 'supplier', 'image', 'markup', 'status', 'origin', 'sort'];
$allowedSorts = ['brand-asc', 'brand-desc'];
$serverFilters = [];
foreach ($allowedFilterKeys as $filterKey) {
    $value = trim((string) ($_GET[$filterKey] ?? ''));
    if ($value === '') {
        continue;
    }
    if ($filterKey === 'sort' && !in_array($value, $allowedSorts, true)) {
        continue;
    }
    $serverFilters[$filterKey] = mb_substr($value, 0, 255, 'UTF-8');
}
if ($listFilter === 'no_image') {
    $serverFilters['image'] = 'missing';
}
$noImageOnlineCount = $service->countOnlineWithoutImage();
$paged = $service->getProdusesPaginated($page, $perPage, $serverFilters);
$produse = $paged['items'];
$paginationMeta = $paged;
$originCounts = $service->countAdminListByOrigin($serverFilters);
$activeOriginTab = trim((string) ($serverFilters['origin'] ?? ''));
if (!in_array($activeOriginTab, ['site', 'export'], true)) {
    $activeOriginTab = 'all';
}
$productFacets = (new ProductFacetsService())->getListFilters();

function h($value): string { return produse_list_h($value); }
function product_images($value): array { return produse_list_images($value); }
function product_first_image(array $product): string { return produse_list_first_image($product); }
function price_number($value): string { return produse_list_price_number($value); }
function product_base_price(array $product): string { return produse_list_base_price($product); }
function produse_list_page_url(int $pageNum, string $activeListFilter = ''): string
{
    $params = ['page' => max(1, $pageNum)];
    foreach (['q', 'category', 'subcategory', 'marca', 'brand', 'supplier', 'image', 'markup', 'status', 'origin', 'sort'] as $key) {
        $value = trim((string) ($_GET[$key] ?? ''));
        if ($value !== '') {
            $params[$key] = $value;
        }
    }
    if ($activeListFilter !== '') {
        $params['filter'] = $activeListFilter;
    }

    return '?' . http_build_query($params);
}
$total = (int) ($paginationMeta['total'] ?? count($produse));
$catalogTotal = (int) ($paginationMeta['catalog_total'] ?? $total);
$totalPages = (int) ($paginationMeta['total_pages'] ?? 1);
$currentPage = (int) ($paginationMeta['page'] ?? 1);
$markupRulesModalJson = json_encode(array_values(array_map(static function (array $rule): array {
    return [
        'id' => (int) ($rule['id'] ?? 0),
        'name' => (string) ($rule['name'] ?? ''),
        'is_active' => (int) ($rule['is_active'] ?? 0),
        'adjustment_type' => (string) ($rule['adjustment_type'] ?? 'percentage'),
        'adjustment_value' => (string) ($rule['adjustment_value'] ?? '0'),
    ];
}, $markupRulesForModal)), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '[]';
$badgeConfigPath = dirname(__DIR__, 4) . '/app/Config/product-badges.php';
$badgeConfig = is_file($badgeConfigPath) ? (require $badgeConfigPath) : [];
$badgeModalJson = json_encode(array_values(array_map(static function (string $key, array $badge): array {
    return [
        'key' => $key,
        'label' => (string) ($badge['admin'] ?? $badge['label'] ?? strtoupper($key)),
    ];
}, array_keys(is_array($badgeConfig) ? $badgeConfig : []), is_array($badgeConfig) ? $badgeConfig : [])), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '[]';
$advancedFilterKeys = ['category', 'subcategory', 'marca', 'brand', 'supplier', 'image', 'markup', 'status', 'sort'];
$activeAdvancedFilters = 0;
foreach ($advancedFilterKeys as $filterKey) {
    if (trim((string) ($serverFilters[$filterKey] ?? '')) !== '') {
        $activeAdvancedFilters++;
    }
}
$filtersPanelOpen = $activeAdvancedFilters > 0 || $listFilter === 'no_image';
$produseListCssUrl = AdminUrl::publicAsset('css/admin-produse-list.css');
?>
<link rel="stylesheet" href="<?= h($produseListCssUrl) ?>?v=20260718-pl-v13">
<link rel="stylesheet" href="<?= h(AdminUrl::fontAwesomeCssUrl()) ?>" crossorigin="anonymous" referrerpolicy="no-referrer">
<script defer src="<?= h(AdminUrl::publicAsset('js/vendor/three.min.js')) ?>?v=0.160.0"></script>
<script defer src="<?= h(AdminUrl::publicAsset('js/admin-produse-list.js')) ?>?v=20260718-pl-v7"></script>
<style>
    /* Critical aurora fallback — fără negru */
    .produse-list-page .admin-panel__head {
        position: relative !important;
        display: flex !important;
        align-items: center !important;
        justify-content: space-between !important;
        gap: 16px !important;
        min-height: 92px;
        padding: 22px 26px !important;
        overflow: hidden !important;
        background: linear-gradient(120deg, #34d399 0%, #14b8a6 38%, #22d3ee 68%, #fb7185 100%) !important;
        background-size: 220% 220% !important;
        border-bottom: none !important;
    }
    .produse-list-page .admin-panel__head h2 { color: #fff !important; font-weight: 800 !important; letter-spacing: .06em !important; text-transform: uppercase !important; }
    .produse-list-page .pl-head-meta {
        display: inline-flex !important; align-items: center !important; gap: 8px !important;
        padding: 8px 14px !important; border-radius: 999px !important;
        background: rgba(255,255,255,.28) !important; border: 1px solid rgba(255,255,255,.5) !important;
        color: #fff !important; font-size: 11px !important; font-weight: 800 !important;
        letter-spacing: .06em !important; text-transform: uppercase !important; backdrop-filter: blur(12px);
    }
    .produse-list-page .besoiu-tabs__btn--active,
    .produse-list-page .admin-tab--active,
    .produse-list-page .admin-tab[aria-selected="true"] {
        background: linear-gradient(135deg, #34d399 0%, #14b8a6 55%, #22d3ee 100%) !important;
        color: #fff !important; border-color: transparent !important;
        box-shadow: 0 12px 28px rgba(20, 184, 166, .35) !important;
    }
    .produse-list-page .products-menu__toggle--hub {
        background: linear-gradient(135deg, #22d3ee 0%, #14b8a6 55%, #34d399 100%) !important;
        border: none !important; color: #fff !important; font-weight: 800 !important;
    }
    .produse-list-page .products-filters__panel[hidden] { display: none !important; }
    .btn-delete-selected {
        border-color: rgba(220, 38, 38, .35);
        background: #fef2f2;
        color: #b91c1c;
    }
    .btn-delete-selected:disabled {
        opacity: .45;
        cursor: not-allowed;
    }
    .produse-list-page ~ #addProductModal.products-overlay-modal:not(.is-open),
    #addProductModal.products-overlay-modal:not(.is-open),
    #addProductModal.products-overlay-modal[aria-hidden="true"]:not(.is-open) {
        display: none !important;
        visibility: hidden !important;
        pointer-events: none !important;
        opacity: 0 !important;
    }
    #addProductModal.products-overlay-modal.is-open {
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        padding: 24px 16px !important;
        position: fixed !important;
        inset: 0 !important;
        z-index: 100000 !important;
        pointer-events: auto !important;
        visibility: visible !important;
        opacity: 1 !important;
        background: rgba(15, 23, 42, 0.58) !important;
        backdrop-filter: blur(2px) !important;
    }
    #addProductModal.products-overlay-modal.is-open > .products-overlay-modal__panel {
        display: block !important;
        visibility: visible !important;
        pointer-events: auto !important;
        opacity: 1 !important;
    }
    #addProductModal.products-overlay-modal.is-open #addProductFrame {
        display: block !important;
        visibility: visible !important;
        pointer-events: auto !important;
        width: 100% !important;
        min-height: 280px !important;
    }
    .products-overlay-modal__panel {
        background: #fff;
        border-radius: 18px;
        box-shadow: 0 25px 80px rgba(0, 0, 0, .25);
    }
    .products-overlay-modal__header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 14px 18px;
        border-bottom: 1px solid #e5e7eb;
        background: #fff;
    }
    .products-overlay-modal__title {
        font-size: 15px;
        color: #111827;
    }
    .products-overlay-modal__close {
        border: 1px solid #d1d5db;
        background: #fff;
        border-radius: 10px;
        padding: 8px 12px;
        font-size: 12px;
        cursor: pointer;
    }
    .markup-select-modal__panel {
        position: relative;
        width: min(760px, 100%);
        margin: 0 auto;
        background: #fff;
        border-radius: 18px;
        box-shadow: 0 25px 80px rgba(0, 0, 0, .25);
        overflow: hidden;
        display: flex;
        flex-direction: column;
        max-height: calc(100vh - 48px);
    }
    .markup-select-modal__header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 12px;
        padding: 16px 18px;
        border-bottom: 1px solid #e5e7eb;
        background: linear-gradient(180deg, #fff 0%, #f8fafc 100%);
    }
    .markup-select-modal__header-title {
        font-size: 15px;
        font-weight: 600;
        color: #0f172a;
        line-height: 1.35;
    }
    .markup-select-modal__header-sub {
        margin-top: 4px;
        font-size: 12px;
        color: #64748b;
        line-height: 1.45;
    }
    .markup-select-modal__callout {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        margin-bottom: 14px;
        padding: 12px 14px;
        border-radius: 12px;
        border: 1px solid rgba(20, 184, 166, .22);
        background: linear-gradient(135deg, #f0fdfa 0%, #f0fdf4 100%);
        box-shadow: 0 1px 0 rgba(255, 255, 255, .7) inset;
    }
    .markup-select-modal__callout-icon {
        flex-shrink: 0;
        width: 18px;
        height: 18px;
        margin-top: 1px;
        color: #14b8a6;
    }
    .markup-select-modal__callout-text {
        margin: 0;
        font-size: 13px;
        font-weight: 500;
        line-height: 1.5;
        color: #1e293b;
    }
    .markup-select-modal__body {
        padding: 16px 18px;
        overflow: auto;
        flex: 1 1 auto;
    }
    .markup-select-modal__products {
        margin-top: 12px;
        max-height: 320px;
        overflow: auto;
        border: 1px solid rgba(15, 23, 42, .1);
        border-radius: 12px;
        background: #f8fafc;
    }
    .markup-select-modal__product-row {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        padding: 10px 12px;
        border-bottom: 1px solid rgba(15, 23, 42, .06);
        font-size: 13px;
    }
    .markup-select-modal__product-row:last-child {
        border-bottom: 0;
    }
    .markup-select-modal__product-meta {
        color: #64748b;
        font-size: 12px;
        margin-top: 2px;
    }
    .markup-select-modal__footer {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: flex-end;
        gap: 10px;
        padding: 14px 18px;
        border-top: 1px solid #e5e7eb;
        background: #fff;
    }
    .markup-select-modal__footer-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-left: auto;
    }
    .markup-select-modal__callout--badge {
        border-color: rgba(225, 29, 72, .24);
        background: linear-gradient(135deg, #fff1f2 0%, #f0fdfa 100%);
    }
    .markup-select-modal__callout--badge .markup-select-modal__callout-icon {
        color: #e11d48;
    }
    .products-menu {
        position: relative;
        flex-shrink: 0;
    }
    .products-menu__toggle {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        white-space: nowrap;
    }
    .products-menu__toggle:disabled {
        opacity: .5;
        cursor: not-allowed;
    }
    .products-menu__chevron {
        opacity: .65;
        transition: transform .16s ease;
    }
    .products-menu.is-open .products-menu__chevron {
        transform: rotate(180deg);
    }
    .products-menu__panel {
        display: flex;
        flex-direction: column;
    }
    .products-menu__panel[hidden] {
        display: none !important;
    }
    .products-menu__section.is-disabled {
        opacity: .55;
    }
    .products-bulk-bar__actions {
        margin-left: auto;
    }
    @media (max-width: 767px) {
        .products-menu__panel {
            left: auto;
            right: 0;
            min-width: 240px;
            max-width: 100%;
        }
        .markup-select-modal__footer-actions {
            width: 100%;
            justify-content: stretch;
        }
        .markup-select-modal__footer-actions button {
            flex: 1 1 auto;
        }
    }
    /* === Fix z-index (audit /admin/product): popup-uri / modale / dropdown + controalele lor mereu deasupra oricărui element de pagină === */
    body.besoiu-admin-2026 #addProductModal,
    body.besoiu-admin-2026 #markupSelectModal,
    body.besoiu-admin-2026 #badgeSelectModal,
    body.besoiu-admin-2026 #addProductModal.is-open,
    body.besoiu-admin-2026 #markupSelectModal.is-open,
    body.besoiu-admin-2026 #badgeSelectModal.is-open {
        z-index: 999999 !important;
    }
    body.besoiu-admin-2026 #addProductModal.is-open > .products-overlay-modal__panel,
    body.besoiu-admin-2026 #markupSelectModal.is-open .markup-select-modal__panel,
    body.besoiu-admin-2026 #badgeSelectModal.is-open .markup-select-modal__panel {
        position: relative !important;
        z-index: 999999 !important;
    }
    body.besoiu-admin-2026 #addProductModal.is-open .products-overlay-modal__close,
    body.besoiu-admin-2026 #markupSelectModal.is-open .markup-select-modal__panel button,
    body.besoiu-admin-2026 #markupSelectModal.is-open .markup-select-modal__panel select,
    body.besoiu-admin-2026 #markupSelectModal.is-open .markup-select-modal__panel input,
    body.besoiu-admin-2026 #badgeSelectModal.is-open .markup-select-modal__panel button,
    body.besoiu-admin-2026 #badgeSelectModal.is-open .markup-select-modal__panel select,
    body.besoiu-admin-2026 #badgeSelectModal.is-open .markup-select-modal__panel input {
        position: relative;
        z-index: 999999 !important;
        pointer-events: auto !important;
    }
    body.besoiu-admin-2026 .produse-list-page .products-menu.is-open .products-menu__panel {
        z-index: 999999 !important;
    }
</style>
<div class="-mt-5 admin-content produse-list-page">
    <div class="admin-panel">
        <div class="admin-panel__head">
            <canvas id="plThreeCanvas" aria-hidden="true"></canvas>
            <div class="pl-head-main">
                <span class="pl-head-icon" aria-hidden="true"><i class="fa-solid fa-gem"></i></span>
                <h2 class="mt-0 text-lg font-medium">Lista produse</h2>
            </div>
            <span class="pl-head-meta"><span class="pl-head-meta__dot" aria-hidden="true"></span><i class="fa-solid fa-bolt"></i> Catalog live · <?= (int) $catalogTotal ?> SKU</span>
        </div>
        <?php require __DIR__ . '/_produse-section-nav.php'; ?>
        <div class="admin-tabs" role="tablist" aria-label="Filtru sursa produse">
            <button type="button" class="admin-tab<?= $activeOriginTab === 'all' ? ' admin-tab--active' : '' ?>" data-product-tab="all" role="tab" aria-selected="<?= $activeOriginTab === 'all' ? 'true' : 'false' ?>">
                Toate<span class="admin-tab__count" id="tabCountAll"><?= (int) ($originCounts['all'] ?? $total) ?></span>
            </button>
            <button type="button" class="admin-tab<?= $activeOriginTab === 'site' ? ' admin-tab--active' : '' ?>" data-product-tab="site" role="tab" aria-selected="<?= $activeOriginTab === 'site' ? 'true' : 'false' ?>">
                Site propriu<span class="admin-tab__count" id="tabCountSite"><?= (int) ($originCounts['site'] ?? 0) ?></span>
            </button>
            <button type="button" class="admin-tab<?= $activeOriginTab === 'export' ? ' admin-tab--active' : '' ?>" data-product-tab="export" role="tab" aria-selected="<?= $activeOriginTab === 'export' ? 'true' : 'false' ?>">
                Export piese auto<span class="admin-tab__count" id="tabCountExport"><?= (int) ($originCounts['export'] ?? 0) ?></span>
            </button>
        </div>
        <div class="mt-2 grid grid-cols-12 gap-x-6 gap-y-8">
            <script src="<?= htmlspecialchars($asyncClientUrl, ENT_QUOTES, 'UTF-8') ?>"></script>
            <div id="productsShopArea" class="products-shop-area col-span-12 grid grid-cols-12 gap-x-6 gap-y-8">
            <div class="col-span-12 mt-2 products-toolbar">
                <div class="products-toolbar__primary">
                    <div class="products-toolbar__actions">
                        <button type="button" id="openAddProduct" class="products-btn-hover box inline-flex h-10 shrink-0 cursor-pointer items-center justify-center gap-2 whitespace-nowrap rounded-lg border border-(--color)/60 bg-(--color)/20 px-4 py-2 text-sm font-medium text-(--color) ring-offset-background transition-colors hover:bg-(--color)/5 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 [--color:var(--color-primary)] [&_svg]:pointer-events-none [&_svg]:size-4 [&_svg]:shrink-0">
                            <i class="fa-solid fa-plus"></i>
                            Produs nou
                        </button>
                        <div class="products-menu products-menu--hub" data-products-menu id="productsActionsHub">
                            <button type="button" id="productsActionsHubToggle" class="products-menu__toggle products-menu__toggle--hub products-btn-hover box inline-flex h-10 shrink-0 cursor-pointer items-center rounded-lg border px-4 py-2 text-sm" aria-expanded="false" aria-haspopup="true">
                                <i class="fa-solid fa-layer-group"></i>
                                Acțiuni produse
                                <i class="fa-solid fa-chevron-down products-menu__chevron"></i>
                            </button>
                            <div class="products-menu__panel products-menu__panel--hub" hidden>
                                <div class="products-menu__section">
                                    <p class="products-menu__section-title"><i class="fa-solid fa-filter"></i> Pe produse filtrate (<?= (int) $total ?> total)</p>
                                    <button type="button" id="reapplyFilteredMarkup" class="products-menu__item products-btn-hover">
                                        <i class="fa-solid fa-percent"></i><span data-btn-label>Reaplică adaos</span>
                                    </button>
                                    <button type="button" id="setFilteredBadgeHot" class="products-menu__item products-btn-hover">
                                        <i class="fa-solid fa-fire"></i><span data-btn-label>Badge HOT</span>
                                    </button>
                                    <button type="button" id="setFilteredBadgePromo" class="products-menu__item products-btn-hover">
                                        <i class="fa-solid fa-tags"></i><span data-btn-label>Badge PROMO</span>
                                    </button>
                                    <button type="button" id="setFilteredCurierNu" class="products-menu__item products-btn-hover">
                                        <i class="fa-solid fa-truck"></i><span data-btn-label>Livrare curier: Nu</span>
                                    </button>
                                    <button type="button" id="auditFilteredImagesBtn" class="products-menu__item products-btn-hover btn-audit-images">
                                        <i class="fa-solid fa-wand-magic-sparkles"></i><span data-btn-label>Audit Cursor</span>
                                    </button>
                                </div>
                                <div class="products-menu__section" id="categoryCurierSection">
                                    <p class="products-menu__section-title"><i class="fa-solid fa-truck-fast"></i> Curier — categorie din filtru</p>
                                    <button type="button" id="setCategoryCurierNu" class="products-menu__item products-btn-hover" disabled>
                                        <i class="fa-solid fa-ban"></i><span data-btn-label>Livrare curier: Nu</span>
                                    </button>
                                    <button type="button" id="setCategoryCurierDa" class="products-menu__item products-btn-hover" disabled>
                                        <i class="fa-solid fa-circle-check"></i><span data-btn-label>Livrare curier: Da</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="shrink-0 text-sm font-medium text-slate-600" id="entriesText">
                        <?= $total ? (($currentPage - 1) * $perPage + 1) . '–' . min($currentPage * $perPage, $total) . ' din ' . $total . ' produse' : '0 produse' ?>
                    </div>
                </div>
                <div class="products-filters">
                    <div class="products-filters__bar">
                        <div class="products-filters__search">
                            <input id="filterText" value="<?= h($serverFilters['q'] ?? '') ?>" type="text" placeholder="Caută produs, cod, brand...">
                            <i class="fa-solid fa-magnifying-glass"></i>
                        </div>
                        <button type="button"
                                id="productsFiltersToggle"
                                class="products-filters__toggle<?= $filtersPanelOpen ? ' is-open' : '' ?>"
                                aria-expanded="<?= $filtersPanelOpen ? 'true' : 'false' ?>"
                                aria-controls="productsFiltersPanel">
                            <i class="fa-solid fa-sliders"></i>
                            Filtre
                            <?php if ($activeAdvancedFilters > 0): ?>
                                <span class="products-filters__badge"><?= (int) $activeAdvancedFilters ?></span>
                            <?php endif; ?>
                            <i class="fa-solid fa-chevron-down pl-chevron"></i>
                        </button>
                        <button type="button"
                                id="quickFilterNoImage"
                                class="products-btn-hover products-quick-filter box inline-flex h-10 shrink-0 cursor-pointer items-center justify-center gap-2 whitespace-nowrap rounded-lg border px-4 py-2 text-sm font-medium<?= $listFilter === 'no_image' ? ' is-active' : '' ?>"
                                aria-pressed="<?= $listFilter === 'no_image' ? 'true' : 'false' ?>"
                                title="Afișează toate produsele online fără poză reală în magazin">
                            <i class="fa-solid fa-image"></i>
                            Fără imagine
                            <span class="products-quick-filter__count" id="quickFilterNoImageCount"><?= (int) $noImageOnlineCount ?></span>
                        </button>
                    </div>
                    <div id="productsFiltersPanel" class="products-filters__panel<?= $filtersPanelOpen ? ' is-open' : '' ?>"<?= $filtersPanelOpen ? '' : ' hidden' ?>>
                        <select id="imageSourceFilter" class="box">
                            <option value="">Toate imaginile</option>
                            <option value="missing"<?= ($serverFilters['image'] ?? '') === 'missing' ? ' selected' : '' ?>>Fără imagine</option>
                            <option value="tecdoc_api"<?= ($serverFilters['image'] ?? '') === 'tecdoc_api' ? ' selected' : '' ?>>TecDoc API</option>
                            <option value="csv"<?= ($serverFilters['image'] ?? '') === 'csv' ? ' selected' : '' ?>>CSV</option>
                            <option value="caietcomenzi"<?= ($serverFilters['image'] ?? '') === 'caietcomenzi' ? ' selected' : '' ?>>Caiet comenzi</option>
                        </select>
                        <select id="categoryFilter" class="box">
                            <option value="">Toate categoriile</option>
                            <?php foreach ($productFacets['categories'] as $facet): ?>
                                <option value="<?= h(mb_strtolower($facet['label'], 'UTF-8')) ?>"<?= mb_strtolower((string) ($serverFilters['category'] ?? ''), 'UTF-8') === mb_strtolower($facet['label'], 'UTF-8') ? ' selected' : '' ?> data-label="<?= h($facet['label']) ?>" data-count="<?= (int) ($facet['count'] ?? 0) ?>"><?= h($facet['label']) ?> (<?= h((string) ($facet['count'] ?? 0)) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                        <select id="subcategoryFilter" class="box">
                            <option value="">Toate subcategoriile</option>
                            <?php foreach ($productFacets['subcategories'] as $facet): ?>
                                <option value="<?= h(mb_strtolower($facet['label'], 'UTF-8')) ?>"<?= mb_strtolower((string) ($serverFilters['subcategory'] ?? ''), 'UTF-8') === mb_strtolower($facet['label'], 'UTF-8') ? ' selected' : '' ?> data-label="<?= h($facet['label']) ?>" data-count="<?= (int) ($facet['count'] ?? 0) ?>"><?= h($facet['label']) ?> (<?= h((string) ($facet['count'] ?? 0)) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                        <select id="marcaFilter" class="box">
                            <option value="">Toate mărcile</option>
                            <?php foreach ($productFacets['marci'] as $facet): ?>
                                <option value="<?= h(mb_strtolower($facet['label'], 'UTF-8')) ?>"<?= mb_strtolower((string) ($serverFilters['marca'] ?? ''), 'UTF-8') === mb_strtolower($facet['label'], 'UTF-8') ? ' selected' : '' ?>><?= h($facet['label']) ?> (<?= h((string) ($facet['count'] ?? 0)) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                        <select id="supplierFilter" class="box">
                            <option value="">Toți furnizorii</option>
                            <?php foreach ($productFacets['suppliers'] ?? [] as $facet): ?>
                                <option value="<?= h($facet['label']) ?>"<?= mb_strtoupper((string) ($serverFilters['supplier'] ?? ''), 'UTF-8') === mb_strtoupper($facet['label'], 'UTF-8') ? ' selected' : '' ?>><?= h($facet['label']) ?> (<?= (int) ($facet['count'] ?? 0) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                        <select id="productStatusFilter" class="box">
                            <option value="">Toate statusurile</option>
                            <option value="active"<?= ($serverFilters['status'] ?? '') === 'active' ? ' selected' : '' ?>>Active</option>
                            <option value="inactive"<?= ($serverFilters['status'] ?? '') === 'inactive' ? ' selected' : '' ?>>Inactive</option>
                        </select>
                        <select id="brandFilter" class="box">
                            <option value="">Toate brandurile</option>
                            <?php foreach ($productFacets['brands'] ?? [] as $facet): ?>
                                <option value="<?= h(mb_strtolower($facet['label'], 'UTF-8')) ?>"<?= mb_strtolower((string) ($serverFilters['brand'] ?? ''), 'UTF-8') === mb_strtolower($facet['label'], 'UTF-8') ? ' selected' : '' ?>><?= h($facet['label']) ?> (<?= h((string) ($facet['count'] ?? 0)) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                        <select id="sortFilter" class="box">
                            <option value="">Sortare implicită</option>
                            <option value="brand-asc"<?= ($serverFilters['sort'] ?? '') === 'brand-asc' ? ' selected' : '' ?>>Brand piesă A–Z</option>
                            <option value="brand-desc"<?= ($serverFilters['sort'] ?? '') === 'brand-desc' ? ' selected' : '' ?>>Brand piesă Z–A</option>
                        </select>
                        <select id="markupStatusFilter" class="box">
                            <option value="">Toate regulile</option>
                            <option value="with-rule"<?= ($serverFilters['markup'] ?? '') === 'with-rule' ? ' selected' : '' ?>>Cu adaos aplicat</option>
                            <option value="without-rule"<?= ($serverFilters['markup'] ?? '') === 'without-rule' ? ' selected' : '' ?>>Fără regulă</option>
                        </select>
                        <input id="markupRuleFilter" list="markupRuleList" value="<?= h(!in_array(($serverFilters['markup'] ?? ''), ['with-rule', 'without-rule'], true) ? ($serverFilters['markup'] ?? '') : '') ?>" class="box" type="text" placeholder="Filtru regulă...">
                        <datalist id="markupRuleList">
                            <?php foreach ($productFacets['markup_rules'] ?? [] as $facet): ?>
                                <option value="<?= h($facet['label']) ?>"></option>
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                </div>
            </div>

            <?php if ($listFilter === 'no_image'): ?>
                <div class="col-span-12 products-filter-banner" id="noImageFilterBanner">
                    <span>
                        <strong>Filtru activ:</strong> produse online fără poză reală (<?= (int) $total ?> din catalog).
                        Ideal după importuri pentru a prinde erori de imagine.
                    </span>
                    <a href="/admin/product?page=1" class="products-btn-hover box inline-flex h-9 items-center rounded-lg border px-3 text-sm font-medium">
                        Elimină filtrul
                    </a>
                </div>
            <?php endif; ?>

            <div class="col-span-12 products-bulk-bar" id="bulkBar">
                <label class="inline-flex items-center gap-2 text-sm cursor-pointer">
                    <input type="checkbox" id="selectAllPage" class="h-4 w-4">
                    <span>Pagina</span>
                </label>
                <button type="button" id="selectFilteredBtn" class="products-btn-hover box inline-flex h-9 items-center rounded-lg border px-3 text-sm">
                    <i class="fa-solid fa-eye"></i> Vizibile
                </button>
                <button type="button" id="deselectAllBtn" class="products-btn-hover box inline-flex h-9 items-center rounded-lg border px-3 text-sm">
                    <i class="fa-solid fa-xmark"></i> Deselectează
                </button>
                <button type="button" id="selectAllCatalogBtn" class="products-btn-hover box inline-flex h-9 items-center rounded-lg border px-3 text-sm" data-total="<?= (int) $catalogTotal ?>">
                    <i class="fa-solid fa-list-check"></i> Tot catalogul (<?= (int) $catalogTotal ?>)
                </button>
                <span class="products-bulk-bar__count" id="selectedCount">0 selectate</span>
                <div class="products-bulk-bar__actions products-menu products-menu--bulk" data-products-menu>
                    <button type="button" id="bulkActionsToggle" class="products-menu__toggle products-menu__toggle--accent products-btn-hover box inline-flex h-9 items-center rounded-lg border px-4 text-sm" aria-expanded="false" aria-haspopup="true" disabled>
                        <i class="fa-solid fa-layer-group"></i>
                        Acțiuni selecție
                        <i class="fa-solid fa-chevron-down products-menu__chevron"></i>
                    </button>
                    <div class="products-menu__panel" hidden>
                        <button type="button" id="applySelectedMarkupBtn" class="products-menu__item products-btn-hover" disabled>
                            <i class="fa-solid fa-percent"></i><span data-btn-label>Aplică adaos selectiv</span>
                        </button>
                        <button type="button" id="applySelectedBadgeBtn" class="products-menu__item products-btn-hover" disabled>
                            <i class="fa-solid fa-certificate"></i><span data-btn-label>Aplică badge selectiv</span>
                        </button>
                        <button type="button" id="setSelectedCurierNuBtn" class="products-menu__item products-btn-hover" disabled>
                            <i class="fa-solid fa-truck"></i><span data-btn-label>Livrare curier: Nu</span>
                        </button>
                        <button type="button" id="auditSelectedImagesBtn" class="products-menu__item products-btn-hover btn-audit-images" disabled title="Pregătește lot pentru Cursor Composer 2.5 (fără OpenAI)">
                            <i class="fa-solid fa-wand-magic-sparkles"></i><span data-btn-label>Audit imagini (selectate)</span>
                        </button>
                        <button type="button" id="deleteSelectedBtn" class="products-menu__item products-btn-hover btn-delete-selected" disabled>
                            <i class="fa-solid fa-trash-can"></i><span data-btn-label>Șterge selectate</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- BEGIN: Data List -->
            <?php if ($produse === []): ?>
                <div class="pl-empty col-span-12">Nu am găsit produse după căutarea aleasă.</div>
            <?php endif; ?>
            <?php foreach ($produse as $index => $product): ?>
                <?php
                    $id = (string)($product['randomn_id'] ?? $product['id'] ?? '');
                    $name = $product['pName'] ?: ($product['name'] ?? 'Produs fara nume');
                    $nameMarketplace = trim((string) ($product['pNameMarketplace'] ?? ''));
                    if ($nameMarketplace === '' && function_exists('besoiu_resolve_marketplace_product_title')) {
                        $nameMarketplace = besoiu_resolve_marketplace_product_title($product);
                    }
                    $price = (string)($product['pPrice'] ?? '');
                    $basePrice = product_base_price($product);
                    $markupRule = trim((string)($product['pMarkupRuleName'] ?? ''));
                    $brand = trim((string)($product['pBrand'] ?? ''));
                    $marca = trim((string)($product['pMarca'] ?? ''));
                    $model = trim((string)($product['pModel'] ?? ''));
                    $motorizare = trim((string)($product['pMotorizare'] ?? ''));
                    $productCategory = trim((string)($product['pCategory'] ?? ''));
                    $productSubcategory = trim((string)($product['pSubcategory'] ?? ''));
                    $state = (string)($product['pState'] ?? '');
                    $city = (string)($product['pCity'] ?? '');
                    $car = (string)($product['pCar'] ?? '');
                    $code = (string)($product['pCode'] ?? '');
                    $haystack = strtolower(trim($name . ' ' . $code . ' ' . $car . ' ' . $state . ' ' . $city . ' ' . $brand . ' ' . $marca . ' ' . $model . ' ' . $motorizare . ' ' . $productCategory . ' ' . $productSubcategory));
                    $active = ((string)($product['status'] ?? '1') !== '0');
                    $imageSource = trim((string)($product['pImageSource'] ?? 'missing'));
                    $hasRealImage = produse_list_has_real_image($product);
                    $supplierCode = trim((string)($product['pSupplier'] ?? ''));
                    $productOrigin = $supplierCode !== '' ? 'export' : 'site';
                    $productBadge = trim((string)($product['pBadge'] ?? ''));
                ?>
                <div class="product-card col-span-12 md:col-span-6 lg:col-span-4 xl:col-span-3"
                     data-id="<?= h($id) ?>"
                     data-order="<?= (int) $index ?>"
                     data-search="<?= h($haystack) ?>"
                     data-category="<?= h(strtolower($productCategory)) ?>"
                     data-subcategory="<?= h(strtolower($productSubcategory)) ?>"
                     data-marca="<?= h(strtolower($marca)) ?>"
                     data-brand="<?= h(strtolower($brand)) ?>"
                     data-badge="<?= h(strtolower($productBadge)) ?>"
                     data-markup-rule="<?= h(strtolower($markupRule)) ?>"
                     data-has-rule="<?= $markupRule !== '' ? '1' : '0' ?>"
                     data-image-source="<?= h(strtolower($imageSource)) ?>"
                     data-has-real-image="<?= $hasRealImage ? '1' : '0' ?>"
                     data-active="<?= $active ? '1' : '0' ?>"
                     data-product-origin="<?= h($productOrigin) ?>"
                     data-supplier="<?= h(strtolower($supplierCode)) ?>"
                     data-price="<?= h(price_number($price)) ?>">
                    <article class="pl-card">
                        <label class="product-select-wrap" title="Selectează produs">
                            <input type="checkbox" class="product-select" value="<?= h($id) ?>" aria-label="Selectează <?= h($name) ?>">
                        </label>
                        <a class="pl-card__media<?= $hasRealImage ? '' : ' pl-card__media--placeholder' ?>" href="/admin/editproduse?id=<?= urlencode($id) ?>">
                            <img src="<?= h(product_first_image($product)) ?>" alt="<?= h($name) ?>" loading="lazy" decoding="async">
                            <?php if (!$hasRealImage): ?>
                                <span class="pl-card__badge"><i class="fa-solid fa-image"></i> Placeholder</span>
                            <?php endif; ?>
                        </a>
                        <div class="pl-card__body">
                            <h3 class="pl-card__title">
                                <a href="/admin/editproduse?id=<?= urlencode($id) ?>"><?= h($name) ?></a>
                            </h3>
                            <div class="pl-card__title-versions" style="display:flex;flex-direction:column;gap:4px;margin:6px 0 8px;font-size:11px;line-height:1.35;">
                                <div title="<?= h($name) ?>" style="opacity:.9;">
                                    <span style="display:inline-block;min-width:2.4rem;font-weight:700;color:#0f766e;">WEB</span>
                                    <?= h(mb_strlen($name, 'UTF-8') > 72 ? mb_substr($name, 0, 72, 'UTF-8') . '…' : $name) ?>
                                </div>
                                <?php if ($nameMarketplace !== ''): ?>
                                <div title="<?= h($nameMarketplace) ?>" style="opacity:.85;">
                                    <span style="display:inline-block;min-width:2.4rem;font-weight:700;color:#b45309;">MP</span>
                                    <?= h(mb_strlen($nameMarketplace, 'UTF-8') > 72 ? mb_substr($nameMarketplace, 0, 72, 'UTF-8') . '…' : $nameMarketplace) ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="pl-card__code">
                                <i class="fa-solid fa-barcode"></i>
                                <?= $code !== '' ? h($code) : 'Cod nesetat' ?>
                            </div>
                            <div class="pl-card__prices">
                                <div class="pl-card__price">
                                    <span><i class="fa-solid fa-cart-shopping"></i> Achiziție</span>
                                    <strong><?= $basePrice !== '' ? h($basePrice) . ' lei' : '—' ?></strong>
                                </div>
                                <div class="pl-card__price pl-card__price--final">
                                    <span><i class="fa-solid fa-tag"></i> Preț final</span>
                                    <strong><?= $price !== '' ? h($price) . ' lei' : '—' ?></strong>
                                </div>
                            </div>
                            <ul class="pl-card__facts">
                                <li><i class="fa-solid fa-percent"></i><?= $markupRule !== '' ? h($markupRule) : 'Fără adaos activ' ?></li>
                            </ul>
                            <span class="pl-card__status <?= $active ? 'pl-card__status--on' : 'pl-card__status--off' ?>">
                                <i class="fa-solid <?= $active ? 'fa-circle-check' : 'fa-circle-xmark' ?>"></i>
                                <?= $active ? 'Activ' : 'Inactiv' ?>
                            </span>
                        </div>
                        <div class="pl-card__foot">
                            <button class="audit-product-image products-action-hover" type="button" title="Pregătește audit în Cursor Composer">
                                <i class="fa-solid fa-wand-magic-sparkles"></i>
                                Audit
                            </button>
                            <button class="open-markup-modal products-action-hover" type="button">
                                <i class="fa-solid fa-percent"></i>
                                Adaos
                            </button>
                            <a class="products-action-hover" href="/admin/editproduse?id=<?= urlencode($id) ?>">
                                <i class="fa-solid fa-pen-to-square"></i>
                                Edit
                            </a>
                            <button class="delete-product products-action-hover" type="button">
                                <i class="fa-solid fa-trash-can"></i>
                                Delete
                            </button>
                        </div>
                    </article>
                </div>
            <?php endforeach; ?>
            <!-- END: Data List -->

            <!-- BEGIN: Pagination -->
            <div id="paginationBar" class="col-span-12 flex flex-wrap items-center gap-2 sm:flex-nowrap">
                <?php if ($totalPages > 1): ?>
                    <span class="text-xs opacity-70 mr-2"><?= (($currentPage - 1) * $perPage) + 1 ?>–<?= min($currentPage * $perPage, $total) ?> din <?= $total ?></span>
                    <?php if ($currentPage > 1): ?>
                        <a class="box h-10 rounded-md border px-3 py-2 text-sm" href="<?= h(produse_list_page_url($currentPage - 1, $listFilter)) ?>">‹</a>
                    <?php endif; ?>
                    <?php for ($p = max(1, $currentPage - 2); $p <= min($totalPages, $currentPage + 2); $p++): ?>
                        <a class="box h-10 min-w-10 rounded-md border px-3 py-2 text-sm text-center <?= $p === $currentPage ? 'bg-primary text-white' : '' ?>" href="<?= h(produse_list_page_url($p, $listFilter)) ?>"><?= $p ?></a>
                    <?php endfor; ?>
                    <?php if ($currentPage < $totalPages): ?>
                        <a class="box h-10 rounded-md border px-3 py-2 text-sm" href="<?= h(produse_list_page_url($currentPage + 1, $listFilter)) ?>">›</a>
                    <?php endif; ?>
                <?php else: ?>
                    <span class="text-xs opacity-70"><?= $total ?> produse</span>
                <?php endif; ?>
            </div>
            <!-- END: Pagination -->
            </div><!-- /#productsShopArea -->
        </div>
    </div>
</div>

<?php
$imageAuditMaxBatch = max(1, min(500, (int) ($_ENV['IMAGE_AUDIT_MAX_BATCH'] ?? getenv('IMAGE_AUDIT_MAX_BATCH') ?: 100)));
require __DIR__ . '/_produse-image-audit.php'; ?>

<div id="addProductModal" class="products-overlay-modal" aria-hidden="true" style="display:none;">
    <div class="products-overlay-modal__panel" style="width:min(1200px,calc(100vw - 32px));height:min(calc(100vh - 48px),920px);max-height:calc(100vh - 48px);margin:0 auto;position:relative;overflow:hidden;">
        <div class="products-overlay-modal__header">
            <strong class="products-overlay-modal__title">Produs nou</strong>
            <button type="button" id="closeAddProductModal" class="products-modal-hover products-overlay-modal__close">Închide</button>
        </div>
        <iframe id="addProductFrame" src="" title="Formular adaugare produs"></iframe>
    </div>
</div>

<div id="markupSelectModal" class="products-overlay-modal" aria-hidden="true" style="display:none;">
    <div class="markup-select-modal__panel">
        <div class="markup-select-modal__header">
            <div>
                <strong class="markup-select-modal__header-title">Aplică adaos comercial selectiv</strong>
                <div class="markup-select-modal__header-sub">Selectează produsele țintă și alege o regulă de adaos.</div>
            </div>
            <button type="button" id="closeMarkupSelectModal" class="products-modal-hover box inline-flex h-9 shrink-0 items-center rounded-lg border px-3 text-xs">Închide</button>
        </div>
        <div class="markup-select-modal__body">
            <div class="markup-select-modal__callout" role="note">
                <i data-lucide="info" class="markup-select-modal__callout-icon"></i>
                <p id="markupModalHint" class="markup-select-modal__callout-text">Regula aleasă se aplică direct pe produsele bifate.</p>
            </div>
            <label class="block text-sm font-medium text-slate-800" for="markupRuleSelect">Regulă de adaos</label>
            <select id="markupRuleSelect" class="box mt-2 h-10 w-full rounded-md border bg-background px-3 py-2 text-sm">
                <option value="">Alege o regulă...</option>
                <?php foreach ($markupRulesForModal as $ruleRow): ?>
                    <?php
                    $ruleId = (int) ($ruleRow['id'] ?? 0);
                    if ($ruleId <= 0) {
                        continue;
                    }
                    $ruleName = trim((string) ($ruleRow['name'] ?? ''));
                    $ruleActive = (int) ($ruleRow['is_active'] ?? 0) === 1;
                    $ruleType = (string) ($ruleRow['adjustment_type'] ?? 'percentage') === 'fixed' ? 'fix' : '%';
                    $ruleValue = (string) ($ruleRow['adjustment_value'] ?? '0');
                    ?>
                    <option value="<?= h((string) $ruleId) ?>">
                        <?= h($ruleName !== '' ? $ruleName : ('Regulă #' . $ruleId)) ?><?= $ruleActive ? '' : ' (inactivă)' ?> — <?= h($ruleValue) ?><?= h($ruleType) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <div style="margin-top:14px;display:flex;flex-wrap:wrap;align-items:center;gap:10px;">
                <span class="text-sm font-medium text-slate-800">Produse țintă</span>
                <button type="button" id="markupModalSelectVisible" class="products-btn-hover box inline-flex h-8 items-center rounded-lg border px-3 text-xs">Selectează vizibile</button>
                <button type="button" id="markupModalDeselectAll" class="products-btn-hover box inline-flex h-8 items-center rounded-lg border px-3 text-xs">Deselectează</button>
                <span id="markupModalSelectedCount" class="text-xs font-medium text-slate-600">0 selectate</span>
            </div>
            <div id="markupModalProductList" class="markup-select-modal__products"></div>
            <p class="mt-3 text-xs text-slate-500">Produsele de pe pagina curentă apar mai jos. Pentru alte pagini, folosește selecția din listă înainte de a deschide modalul.</p>
        </div>
        <div class="markup-select-modal__footer">
            <div class="markup-select-modal__footer-actions">
                <button type="button" id="cancelMarkupSelectModal" class="products-modal-hover box inline-flex h-10 items-center rounded-lg border px-4 text-sm">Anulează</button>
                <button type="button" id="confirmMarkupSelectModal" class="products-btn-hover box inline-flex h-10 items-center rounded-lg border border-emerald-300 bg-emerald-100 px-4 text-sm font-medium text-emerald-800">Aplică regula</button>
            </div>
        </div>
    </div>
</div>

<div id="badgeSelectModal" class="products-overlay-modal" aria-hidden="true" style="display:none;">
    <div class="markup-select-modal__panel">
        <div class="markup-select-modal__header">
            <div>
                <strong class="markup-select-modal__header-title">Aplică badge pe selecție</strong>
                <div class="markup-select-modal__header-sub">Badge-ul se aplică doar pe produsele selectate. Alege tipul (ex. HOT, PROMO).</div>
            </div>
            <button type="button" id="closeBadgeSelectModal" class="products-modal-hover box inline-flex h-9 shrink-0 items-center rounded-lg border px-3 text-xs">Închide</button>
        </div>
        <div class="markup-select-modal__body">
            <div class="markup-select-modal__callout markup-select-modal__callout--badge" role="note">
                <i data-lucide="badge-check" class="markup-select-modal__callout-icon"></i>
                <p id="badgeModalHint" class="markup-select-modal__callout-text">Badge-ul ales se aplică direct pe produsele bifate.</p>
            </div>
            <label class="block text-sm font-medium text-slate-800" for="badgeTypeSelect">Badge produs</label>
            <select id="badgeTypeSelect" class="box mt-2 h-10 w-full rounded-md border bg-background px-3 py-2 text-sm">
                <option value="">Fără badge (elimină)</option>
                <?php if (is_array($badgeConfig)): ?>
                    <?php foreach ($badgeConfig as $badgeKey => $badgeRow): ?>
                        <option value="<?= h((string) $badgeKey) ?>"><?= h((string) ($badgeRow['admin'] ?? $badgeRow['label'] ?? strtoupper((string) $badgeKey))) ?></option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>
            <div style="margin-top:14px;display:flex;flex-wrap:wrap;align-items:center;gap:10px;">
                <span class="text-sm font-medium text-slate-800">Produse țintă</span>
                <button type="button" id="badgeModalSelectVisible" class="products-btn-hover box inline-flex h-8 items-center rounded-lg border px-3 text-xs">Selectează vizibile</button>
                <button type="button" id="badgeModalDeselectAll" class="products-btn-hover box inline-flex h-8 items-center rounded-lg border px-3 text-xs">Deselectează</button>
                <span id="badgeModalSelectedCount" class="text-xs font-medium text-slate-600">0 selectate</span>
            </div>
            <div id="badgeModalProductList" class="markup-select-modal__products"></div>
            <p class="mt-3 text-xs text-slate-500">Produsele de pe pagina curentă apar mai jos. Pentru alte pagini, folosește selecția din listă înainte de a deschide modalul.</p>
        </div>
        <div class="markup-select-modal__footer">
            <div class="markup-select-modal__footer-actions">
                <button type="button" id="cancelBadgeSelectModal" class="products-modal-hover box inline-flex h-10 items-center rounded-lg border px-4 text-sm">Anulează</button>
                <button type="button" id="confirmBadgeSelectModal" class="products-btn-hover box inline-flex h-10 items-center rounded-lg border border-rose-300 bg-rose-100 px-4 text-sm font-medium text-rose-800">Aplică badge</button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const endpoint = '/admin/crudproduse';
    const input = document.getElementById('filterText');
    const entries = document.getElementById('entriesText');
    const paginationBar = document.getElementById('paginationBar');
    const markupStatusFilter = document.getElementById('markupStatusFilter');
    const markupRuleFilter = document.getElementById('markupRuleFilter');
    const categoryFilter = document.getElementById('categoryFilter');
    const subcategoryFilter = document.getElementById('subcategoryFilter');
    const marcaFilter = document.getElementById('marcaFilter');
    const brandFilter = document.getElementById('brandFilter');
    const supplierFilter = document.getElementById('supplierFilter');
    const productStatusFilter = document.getElementById('productStatusFilter');
    const sortFilter = document.getElementById('sortFilter');
    const imageSourceFilter = document.getElementById('imageSourceFilter');
    const quickFilterNoImage = document.getElementById('quickFilterNoImage');
    const productsFiltersToggle = document.getElementById('productsFiltersToggle');
    const productsFiltersPanel = document.getElementById('productsFiltersPanel');
    if (productsFiltersToggle && productsFiltersPanel) {
        productsFiltersToggle.addEventListener('click', function () {
            const open = !productsFiltersPanel.classList.contains('is-open');
            productsFiltersPanel.classList.toggle('is-open', open);
            productsFiltersPanel.hidden = !open;
            productsFiltersToggle.classList.toggle('is-open', open);
            productsFiltersToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    }
    const listFilterActive = <?= json_encode($listFilter, JSON_UNESCAPED_UNICODE) ?>;
    const tabButtons = Array.from(document.querySelectorAll('[data-product-tab]'));
    const tabCountAll = document.getElementById('tabCountAll');
    const tabCountSite = document.getElementById('tabCountSite');
    const tabCountExport = document.getElementById('tabCountExport');
    let activeProductTab = <?= json_encode($activeOriginTab, JSON_UNESCAPED_UNICODE) ?>;
    const serverFilteredTotal = <?= (int) $total ?>;
    const reapplyFilteredMarkup = document.getElementById('reapplyFilteredMarkup');
    const setFilteredCurierNu = document.getElementById('setFilteredCurierNu');
    const setCategoryCurierNu = document.getElementById('setCategoryCurierNu');
    const setCategoryCurierDa = document.getElementById('setCategoryCurierDa');
    const openAddProduct = document.getElementById('openAddProduct');
    const addProductModal = document.getElementById('addProductModal');
    const closeAddProductModal = document.getElementById('closeAddProductModal');
    const addProductFrame = document.getElementById('addProductFrame');
    if (addProductModal && addProductModal.parentElement !== document.body) {
        document.body.appendChild(addProductModal);
    }
    if (addProductModal) {
        addProductModal.classList.remove('is-open');
        addProductModal.removeAttribute('hidden');
        addProductModal.setAttribute('aria-hidden', 'true');
        addProductModal.style.setProperty('display', 'none', 'important');
    }
    // Badge/markup overlays must live on <body> — otherwise stacking/overflow from
    // the admin layout can hide them or block clicks (same pattern as addProductModal).
    ['markupSelectModal', 'badgeSelectModal'].forEach((modalId) => {
        const modalEl = document.getElementById(modalId);
        if (modalEl && modalEl.parentElement !== document.body) {
            document.body.appendChild(modalEl);
        }
        if (modalEl) {
            modalEl.classList.remove('is-open');
            modalEl.removeAttribute('hidden');
            modalEl.setAttribute('aria-hidden', 'true');
            modalEl.style.setProperty('display', 'none', 'important');
        }
    });
    let currentPage = 1;
    const catalogTotal = Number(document.getElementById('selectAllCatalogBtn')?.dataset.total || '0');
    let catalogSelectAll = false;
    const selectAllPage = document.getElementById('selectAllPage');
    const selectFilteredBtn = document.getElementById('selectFilteredBtn');
    const deselectAllBtn = document.getElementById('deselectAllBtn');
    const selectAllCatalogBtn = document.getElementById('selectAllCatalogBtn');
    const selectedCountEl = document.getElementById('selectedCount');
    const deleteSelectedBtn = document.getElementById('deleteSelectedBtn');
    const auditSelectedImagesBtn = document.getElementById('auditSelectedImagesBtn');
    const auditFilteredImagesBtn = document.getElementById('auditFilteredImagesBtn');
    const applySelectedMarkupBtn = document.getElementById('applySelectedMarkupBtn');
    const setSelectedCurierNuBtn = document.getElementById('setSelectedCurierNuBtn');
    const markupSelectModal = document.getElementById('markupSelectModal');
    const closeMarkupSelectModal = document.getElementById('closeMarkupSelectModal');
    const cancelMarkupSelectModal = document.getElementById('cancelMarkupSelectModal');
    const confirmMarkupSelectModal = document.getElementById('confirmMarkupSelectModal');
    const markupRuleSelect = document.getElementById('markupRuleSelect');
    const markupModalProductList = document.getElementById('markupModalProductList');
    const markupModalSelectedCount = document.getElementById('markupModalSelectedCount');
    const markupModalSelectVisible = document.getElementById('markupModalSelectVisible');
    const markupModalDeselectAll = document.getElementById('markupModalDeselectAll');
    const markupRulesData = <?= $markupRulesModalJson ?>;
    let markupModalExternalIds = [];
    const setFilteredBadgeHot = document.getElementById('setFilteredBadgeHot');
    const setFilteredBadgePromo = document.getElementById('setFilteredBadgePromo');
    const applySelectedBadgeBtn = document.getElementById('applySelectedBadgeBtn');
    const badgeSelectModal = document.getElementById('badgeSelectModal');
    const closeBadgeSelectModal = document.getElementById('closeBadgeSelectModal');
    const cancelBadgeSelectModal = document.getElementById('cancelBadgeSelectModal');
    const confirmBadgeSelectModal = document.getElementById('confirmBadgeSelectModal');
    const badgeTypeSelect = document.getElementById('badgeTypeSelect');
    const badgeModalProductList = document.getElementById('badgeModalProductList');
    const badgeModalSelectedCount = document.getElementById('badgeModalSelectedCount');
    const badgeModalSelectVisible = document.getElementById('badgeModalSelectVisible');
    const badgeModalDeselectAll = document.getElementById('badgeModalDeselectAll');
    const badgeOptionsData = <?= $badgeModalJson ?>;
    let badgeModalExternalIds = [];
    const categoryCurierSection = document.getElementById('categoryCurierSection');
    const bulkActionsToggle = document.getElementById('bulkActionsToggle');

    function productCheckboxes() {
        return Array.from(document.querySelectorAll('.product-select'));
    }

    function visibleCheckboxes() {
        return cards()
            .map(card => card.querySelector('.product-select'))
            .filter(Boolean);
    }

    function selectedCount() {
        if (catalogSelectAll) {
            return catalogTotal;
        }
        return productCheckboxes().filter(cb => cb.checked).length;
    }

    function selectedIds() {
        if (catalogSelectAll) {
            return [];
        }
        return productCheckboxes()
            .filter(cb => cb.checked)
            .map(cb => cb.value)
            .filter(Boolean);
    }

    function updateSelectionUi() {
        const selectedOnPage = productCheckboxes().filter(cb => cb.checked).length;
        const count = selectedCount();
        if (selectedCountEl) {
            selectedCountEl.textContent = catalogSelectAll
                ? ('Toate cele ' + catalogTotal + ' produse selectate')
                : (count + ' selectate');
        }
        function setMenuBtnLabel(btn, text) {
            if (!btn) return;
            const label = btn.querySelector('[data-btn-label]');
            if (label) {
                label.textContent = text;
            } else {
                btn.textContent = text;
            }
        }
        if (deleteSelectedBtn) {
            deleteSelectedBtn.disabled = count <= 0;
            setMenuBtnLabel(deleteSelectedBtn, count > 0 ? ('Șterge selectate (' + count + ')') : 'Șterge selectate');
        }
        if (auditSelectedImagesBtn) {
            auditSelectedImagesBtn.disabled = count <= 0;
            setMenuBtnLabel(auditSelectedImagesBtn, count > 0
                ? ('Audit Cursor (' + count + ')')
                : 'Audit imagini (Cursor)');
        }
        if (applySelectedMarkupBtn) {
            applySelectedMarkupBtn.disabled = count <= 0 || catalogSelectAll;
            setMenuBtnLabel(applySelectedMarkupBtn, count > 0
                ? ('Aplică adaos selectiv (' + count + ')')
                : 'Aplică adaos selectiv');
        }
        if (setSelectedCurierNuBtn) {
            setSelectedCurierNuBtn.disabled = count <= 0 || catalogSelectAll;
            setMenuBtnLabel(setSelectedCurierNuBtn, count > 0
                ? ('Livrare curier: Nu (' + count + ')')
                : 'Livrare curier: Nu');
        }
        if (applySelectedBadgeBtn) {
            applySelectedBadgeBtn.disabled = count <= 0 || catalogSelectAll;
            setMenuBtnLabel(applySelectedBadgeBtn, count > 0
                ? ('Aplică badge selectiv (' + count + ')')
                : 'Aplică badge selectiv');
        }
        if (bulkActionsToggle) {
            bulkActionsToggle.disabled = count <= 0 && !catalogSelectAll;
        }
        productCheckboxes().forEach(cb => {
            const card = cb.closest('.product-card');
            if (card) {
                card.classList.toggle('is-selected', cb.checked || catalogSelectAll);
            }
        });
        if (selectAllPage) {
            const visible = visibleCheckboxes();
            selectAllPage.indeterminate = !catalogSelectAll
                && visible.some(cb => cb.checked)
                && !visible.every(cb => cb.checked);
            selectAllPage.checked = catalogSelectAll || (visible.length > 0 && visible.every(cb => cb.checked));
        }
    }

    function setCatalogSelectAll(enabled) {
        catalogSelectAll = !!enabled;
        productCheckboxes().forEach(cb => {
            cb.checked = catalogSelectAll;
            cb.disabled = catalogSelectAll;
        });
        updateSelectionUi();
    }

    function setCheckboxSelection(checkboxes, checked) {
        catalogSelectAll = false;
        productCheckboxes().forEach(cb => { cb.disabled = false; });
        checkboxes.forEach(cb => { cb.checked = !!checked; });
        updateSelectionUi();
    }

    selectAllPage && selectAllPage.addEventListener('change', () => {
        setCheckboxSelection(visibleCheckboxes(), selectAllPage.checked);
    });
    selectFilteredBtn && selectFilteredBtn.addEventListener('click', () => {
        setCheckboxSelection(visibleCheckboxes(), true);
    });
    deselectAllBtn && deselectAllBtn.addEventListener('click', () => {
        setCheckboxSelection(productCheckboxes(), false);
    });
    selectAllCatalogBtn && selectAllCatalogBtn.addEventListener('click', () => {
        if (catalogTotal <= 0) {
            alert('Nu există produse în magazin.');
            return;
        }
        if (!confirm('Selectezi toate cele ' + catalogTotal + ' produse din magazin (toate paginile)?')) {
            return;
        }
        setCatalogSelectAll(true);
    });
    productCheckboxes().forEach(cb => {
        cb.addEventListener('change', () => {
            if (catalogSelectAll) {
                setCatalogSelectAll(false);
            }
            updateSelectionUi();
        });
    });
    deleteSelectedBtn && deleteSelectedBtn.addEventListener('click', async (event) => {
        event.preventDefault();
        event.stopPropagation();
        const ids = selectedIds();
        const count = catalogSelectAll ? catalogTotal : ids.length;
        if (count <= 0) {
            alert('Nu ai selectat produse.');
            return;
        }

        let confirmText = '';
        if (catalogSelectAll) {
            confirmText = prompt(
                'ATENȚIE: vei șterge TOATE cele ' + catalogTotal + ' produse din magazin.\n\nTastează exact: STERGE TOT',
                ''
            );
            if (confirmText !== 'STERGE TOT') {
                alert('Ștergerea a fost anulată.');
                return;
            }
        } else if (!confirm('Ștergi ' + count + ' produse selectate? Acțiunea nu poate fi anulată.')) {
            return;
        }

        deleteSelectedBtn.disabled = true;
        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'Accept': 'application/json'},
                credentials: 'same-origin',
                body: JSON.stringify(
                    catalogSelectAll
                        ? {type_product: 'delete_bulk', all: true, confirm: 'STERGE TOT'}
                        : {type_product: 'delete_bulk', ids: ids}
                )
            });
            const raw = await response.text();
            let result = null;
            try {
                result = raw ? JSON.parse(raw) : null;
            } catch (parseErr) {
                throw new Error(
                    response.ok
                        ? 'Răspuns invalid de la server (nu e JSON).'
                        : ('Eroare server HTTP ' + response.status)
                );
            }
            if (!result || typeof result !== 'object') {
                throw new Error('Răspuns gol de la server.');
            }
            alert(result.message || (result.success ? 'Produse șterse.' : 'Nu am putut șterge produsele.'));
            if (result.success) {
                window.location.href = '/admin/product?page=1';
                return;
            }
        } catch (err) {
            console.error('delete_bulk', err);
            alert('Nu am putut șterge produsele: ' + (err && err.message ? err.message : String(err)));
        }
        deleteSelectedBtn.disabled = false;
        updateSelectionUi();
    });

    function cards() {
        return Array.from(document.querySelectorAll('.product-card'));
    }

    function currentServerFilters() {
        const markupRule = (markupRuleFilter && markupRuleFilter.value || '').trim();
        const markupStatus = (markupStatusFilter && markupStatusFilter.value) || '';
        const filters = {
            q: (input?.value || '').trim(),
            category: categoryFilter?.value || '',
            subcategory: subcategoryFilter?.value || '',
            marca: marcaFilter?.value || '',
            brand: brandFilter?.value || '',
            supplier: supplierFilter?.value || '',
            image: imageSourceFilter?.value || '',
            markup: markupRule || markupStatus || '',
            status: productStatusFilter?.value || '',
            sort: sortFilter?.value || '',
            origin: activeProductTab === 'site' || activeProductTab === 'export' ? activeProductTab : ''
        };
        Object.keys(filters).forEach(key => {
            if (!filters[key]) {
                delete filters[key];
            }
        });
        return filters;
    }

    function updateTabCounts() {
        if (tabCountAll) tabCountAll.textContent = String(<?= (int) ($originCounts['all'] ?? $total) ?>);
        if (tabCountSite) tabCountSite.textContent = String(<?= (int) ($originCounts['site'] ?? 0) ?>);
        if (tabCountExport) tabCountExport.textContent = String(<?= (int) ($originCounts['export'] ?? 0) ?>);
    }

    async function reapplyMarkupByFilters() {
        const filters = currentServerFilters();
        if (typeof BesoiuAsync !== 'undefined') {
            const created = await BesoiuAsync.submitJob({
                type: 'products.reapply_markup',
                queue: 'products',
                payload: { filters, chunk_size: 200 },
                timeout_sec: 600,
                idempotencyKey: 'reapply-markup-' + JSON.stringify(filters),
            });
            if (!created.success || !created.job_id) {
                return { success: false, message: created.message || 'Nu am putut porni job-ul de adaos.' };
            }
            return new Promise((resolve) => {
                BesoiuAsync.watchJob(created.job_id, {
                    onDone: (snap) => {
                        const result = (snap.result && typeof snap.result === 'object') ? snap.result : {};
                        resolve({
                            success: true,
                            message: result.message || snap.message || 'Adaos reaplicat.',
                            count: Number(result.updated || 0),
                            total_filtered: Number(result.total_filtered || 0),
                        });
                    },
                    onFailed: (snap) => resolve({
                        success: false,
                        message: String(snap.error || snap.message || 'Reaplicare adaos eșuată.'),
                    }),
                    onError: (err) => resolve({
                        success: false,
                        message: err instanceof Error ? err.message : String(err),
                    }),
                });
            });
        }
        const response = await fetch(endpoint, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                type_product: 'reapply_markup_by_filters',
                filters
            })
        });
        return response.json();
    }

    async function setCurierLivrareByFilters(value) {
        const response = await fetch(endpoint, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                type_product: 'set_curier_livrare_by_filters',
                filters: currentServerFilters(),
                value: value || 'Nu'
            })
        });
        return response.json();
    }

    async function applyBadgeByFilters(badge) {
        const response = await fetch(endpoint, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                type_product: 'set_badge_by_filters',
                filters: currentServerFilters(),
                badge: badge
            })
        });
        return response.json();
    }

    async function reapplyMarkup(ids) {
        const response = await fetch(endpoint, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                type_product: ids.length === 1 ? 'reapply_markup' : 'reapply_markup_bulk',
                id: ids.length === 1 ? ids[0] : undefined,
                ids: ids.length > 1 ? ids : undefined
            })
        });
        return response.json();
    }

    async function applyMarkupRule(ruleId, ids) {
        const response = await fetch(endpoint, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                type_product: 'apply_markup_rule',
                rule_id: ruleId,
                ids: ids
            })
        });
        return response.json();
    }

    async function setCurierLivrareBulk(ids, value) {
        const response = await fetch(endpoint, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                type_product: 'set_curier_livrare_bulk',
                ids: ids,
                value: value || 'Nu'
            })
        });
        return response.json();
    }

    function selectedFilterLabel(selectEl) {
        if (!selectEl || !selectEl.value) {
            return '';
        }
        const opt = selectEl.options[selectEl.selectedIndex];
        return (opt && opt.dataset.label) ? opt.dataset.label : selectEl.value;
    }

    function selectedFilterCount(selectEl) {
        if (!selectEl || !selectEl.value) {
            return 0;
        }
        const opt = selectEl.options[selectEl.selectedIndex];
        return opt && opt.dataset.count ? Number(opt.dataset.count) : 0;
    }

    function updateCategoryCurierButtons() {
        const hasCategory = !!(categoryFilter && categoryFilter.value);
        [setCategoryCurierNu, setCategoryCurierDa].forEach(btn => {
            if (!btn) return;
            btn.disabled = !hasCategory;
        });
        if (categoryCurierSection) {
            categoryCurierSection.classList.toggle('is-disabled', !hasCategory);
            categoryCurierSection.title = hasCategory
                ? ''
                : 'Selectează o categorie din filtru pentru acțiuni curier';
        }
    }

    async function setCurierLivrareByCategory(value) {
        const category = selectedFilterLabel(categoryFilter);
        if (!category) {
            alert('Selectează o categorie din filtru.');
            return null;
        }
        const subcategory = selectedFilterLabel(subcategoryFilter);
        const countHint = selectedFilterCount(categoryFilter);
        const scope = subcategory
            ? ('categoria „' + category + '” / subcategoria „' + subcategory + '”')
            : ('categoria „' + category + '”');
        const countText = countHint > 0 ? (' (~' + countHint + ' produse)') : '';
        if (!confirm('Setezi „Livrare curier: ' + value + '” pentru toate produsele din ' + scope + countText + '?')) {
            return null;
        }
        const response = await fetch(endpoint, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                type_product: 'set_curier_livrare_by_category',
                category: category,
                subcategory: subcategory || undefined,
                value: value
            })
        });
        return response.json();
    }

    async function setBadgeBulk(ids, badge) {
        const response = await fetch(endpoint, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                type_product: 'set_badge_bulk',
                ids: ids,
                badge: badge || ''
            })
        });
        return response.json();
    }

    function badgeLabelForKey(key) {
        const normalized = String(key || '').trim();
        if (!normalized) {
            return 'Fără badge';
        }
        const match = badgeOptionsData.find(item => String(item.key) === normalized);
        return match ? match.label : normalized.toUpperCase();
    }

    async function applyBadgeToIds(ids, badge, confirmLabel) {
        if (!ids.length) {
            alert('Nu există produse țintă.');
            return null;
        }
        const label = confirmLabel || badgeLabelForKey(badge);
        if (!confirm('Aplici badge „' + label + '” pe ' + ids.length + ' produse?')) {
            return null;
        }
        return setBadgeBulk(ids, badge);
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function cardProductLabel(card) {
        const link = card.querySelector('a[href*="/admin/editproduse"]');
        return link ? link.textContent.trim() : ('Produs ' + (card.dataset.id || ''));
    }

    function updateMarkupModalSelectionCount() {
        if (!markupModalProductList || !markupModalSelectedCount) return;
        const checked = markupModalProductList.querySelectorAll('.markup-modal-product:checked').length;
        const externalCount = markupModalExternalIds.length;
        markupModalSelectedCount.textContent = (checked + externalCount) + ' selectate';
    }

    function buildMarkupModalProductList(preselectedIds) {
        if (!markupModalProductList) return;
        const selectedSet = new Set((preselectedIds || []).map(String));
        const cardsOnPage = cards();
        let html = '';

        cardsOnPage.forEach(card => {
            const id = String(card.dataset.id || '');
            if (!id) return;
            const name = cardProductLabel(card);
            const rule = card.dataset.markupRule || '';
            const price = card.dataset.price || '';
            const checked = selectedSet.has(id) ? ' checked' : '';
            html += '<label class="markup-select-modal__product-row">' +
                '<input type="checkbox" class="markup-modal-product h-4 w-4 mt-0.5" value="' + escapeHtml(id) + '"' + checked + '>' +
                '<span><strong>' + escapeHtml(name) + '</strong>' +
                '<div class="markup-select-modal__product-meta">Regula curentă: ' + escapeHtml(rule || 'Fara adaos activ') +
                (price ? ' · Pret final: ' + escapeHtml(price) + ' lei' : '') +
                '</div></span></label>';
            selectedSet.delete(id);
        });

        markupModalExternalIds = Array.from(selectedSet);
        if (markupModalExternalIds.length > 0) {
            html += '<div class="markup-select-modal__product-row" style="background:#f0fdfa;">' +
                '<span><strong>' + markupModalExternalIds.length + ' produse selectate din alte pagini</strong>' +
                '<div class="markup-select-modal__product-meta">Vor fi incluse la aplicare (IDs din selecția listei).</div></span></div>';
        }

        if (!html) {
            html = '<div class="markup-select-modal__product-row"><span>Nu există produse pe pagina curentă.</span></div>';
        }

        markupModalProductList.innerHTML = html;
        markupModalProductList.querySelectorAll('.markup-modal-product').forEach(cb => {
            cb.addEventListener('change', updateMarkupModalSelectionCount);
        });
        updateMarkupModalSelectionCount();
    }

    function collectMarkupModalProductIds() {
        const ids = [];
        if (markupModalProductList) {
            markupModalProductList.querySelectorAll('.markup-modal-product:checked').forEach(cb => {
                if (cb.value) ids.push(cb.value);
            });
        }
        markupModalExternalIds.forEach(id => {
            if (id && !ids.includes(id)) ids.push(id);
        });
        return ids;
    }

    function openMarkupSelectModal(preselectedIds) {
        if (!markupSelectModal) return;
        if (catalogSelectAll) {
            alert('Selectarea întregului catalog nu este suportată pentru adaos selectiv. Bifează produsele dorite sau folosește filtrele.');
            return;
        }
        const merged = new Set();
        (preselectedIds || []).forEach(id => { if (id) merged.add(String(id)); });
        selectedIds().forEach(id => merged.add(String(id)));
        buildMarkupModalProductList(Array.from(merged));
        if (markupRuleSelect) markupRuleSelect.value = '';
        setOverlayModalOpen(markupSelectModal, true);
    }

    function closeMarkupSelectModalFn() {
        if (!markupSelectModal) return;
        setOverlayModalOpen(markupSelectModal, false);
        markupModalExternalIds = [];
    }

    function updateBadgeModalSelectionCount() {
        if (!badgeModalProductList || !badgeModalSelectedCount) return;
        const checked = badgeModalProductList.querySelectorAll('.badge-modal-product:checked').length;
        const externalCount = badgeModalExternalIds.length;
        badgeModalSelectedCount.textContent = (checked + externalCount) + ' selectate';
    }

    function buildBadgeModalProductList(preselectedIds) {
        if (!badgeModalProductList) return;
        const selectedSet = new Set((preselectedIds || []).map(String));
        const cardsOnPage = cards();
        let html = '';

        // Afișează DOAR produsele selectate (toate pre-bifate) — badge-ul se aplică strict pe selecție.
        cardsOnPage.forEach(card => {
            const id = String(card.dataset.id || '');
            if (!id || !selectedSet.has(id)) return;
            const name = cardProductLabel(card);
            const brand = card.dataset.brand || '';
            const badgeCurrent = card.dataset.badge || '';
            html += '<label class="markup-select-modal__product-row">' +
                '<input type="checkbox" class="badge-modal-product h-4 w-4 mt-0.5" value="' + escapeHtml(id) + '" checked>' +
                '<span><strong>' + escapeHtml(name) + '</strong>' +
                '<div class="markup-select-modal__product-meta">Brand: ' + escapeHtml(brand || 'Nesetat') +
                ' · Badge curent: ' + escapeHtml(badgeCurrent ? badgeLabelForKey(badgeCurrent) : 'Fără badge') +
                '</div></span></label>';
            selectedSet.delete(id);
        });

        badgeModalExternalIds = Array.from(selectedSet);
        if (badgeModalExternalIds.length > 0) {
            html += '<div class="markup-select-modal__product-row" style="background:#f0fdfa;">' +
                '<span><strong>' + badgeModalExternalIds.length + ' produse selectate din alte pagini</strong>' +
                '<div class="markup-select-modal__product-meta">Vor fi incluse la aplicare (IDs din selecția listei).</div></span></div>';
        }

        if (!html) {
            html = '<div class="markup-select-modal__product-row"><span>Niciun produs selectat.</span></div>';
        }

        badgeModalProductList.innerHTML = html;
        badgeModalProductList.querySelectorAll('.badge-modal-product').forEach(cb => {
            cb.addEventListener('change', updateBadgeModalSelectionCount);
        });
        updateBadgeModalSelectionCount();
    }

    function collectBadgeModalProductIds() {
        const ids = [];
        if (badgeModalProductList) {
            badgeModalProductList.querySelectorAll('.badge-modal-product:checked').forEach(cb => {
                if (cb.value) ids.push(cb.value);
            });
        }
        badgeModalExternalIds.forEach(id => {
            if (id && !ids.includes(id)) ids.push(id);
        });
        return ids;
    }

    function openBadgeSelectModal(preselectedIds, presetBadge) {
        if (!badgeSelectModal) return;
        if (catalogSelectAll) {
            alert('Selectarea întregului catalog nu este suportată pentru badge-uri. Bifează produsele dorite sau folosește filtrele + butoanele „filtrate”.');
            return;
        }
        const merged = new Set();
        (preselectedIds || []).forEach(id => { if (id) merged.add(String(id)); });
        selectedIds().forEach(id => merged.add(String(id)));
        buildBadgeModalProductList(Array.from(merged));
        if (badgeTypeSelect) {
            badgeTypeSelect.value = typeof presetBadge === 'string' ? presetBadge : '';
        }
        setOverlayModalOpen(badgeSelectModal, true);
    }

    function closeBadgeSelectModalFn() {
        if (!badgeSelectModal) return;
        setOverlayModalOpen(badgeSelectModal, false);
        badgeModalExternalIds = [];
    }

    function setOverlayModalOpen(modal, open) {
        if (!modal) return;
        modal.classList.toggle('is-open', !!open);
        modal.removeAttribute('hidden');
        modal.style.setProperty('display', open ? 'flex' : 'none', 'important');
        modal.setAttribute('aria-hidden', open ? 'false' : 'true');
        const anyOpen = (markupSelectModal && markupSelectModal.classList.contains('is-open'))
            || (addProductModal && addProductModal.classList.contains('is-open'))
            || (badgeSelectModal && badgeSelectModal.classList.contains('is-open'));
        document.body.classList.toggle('products-modal-open', anyOpen);
    }

    function closeAllProductMenus(exceptMenu) {
        document.querySelectorAll('[data-products-menu]').forEach(menu => {
            if (exceptMenu && menu === exceptMenu) {
                return;
            }
            menu.classList.remove('is-open');
            const panel = menu.querySelector('.products-menu__panel');
            const toggle = menu.querySelector('.products-menu__toggle');
            if (panel) {
                panel.hidden = true;
                panel.style.cssText = '';
            }
            if (toggle) {
                toggle.setAttribute('aria-expanded', 'false');
            }
        });
        document.body.classList.remove('products-menu-open');
        const page = document.querySelector('.produse-list-page');
        if (page) {
            page.classList.remove('is-menu-open');
        }
        const backdrop = document.getElementById('productsMenuBackdrop');
        if (backdrop) {
            backdrop.remove();
        }
    }

    document.querySelectorAll('[data-products-menu]').forEach(menu => {
        const toggle = menu.querySelector('.products-menu__toggle');
        const panel = menu.querySelector('.products-menu__panel');
        if (!toggle || !panel) {
            return;
        }
        // forțează închis la load
        menu.classList.remove('is-open');
        panel.hidden = true;
        panel.style.cssText = '';
        toggle.setAttribute('aria-expanded', 'false');

        toggle.addEventListener('click', (event) => {
            event.stopPropagation();
            if (toggle.disabled) {
                return;
            }
            const willOpen = !menu.classList.contains('is-open');
            closeAllProductMenus(willOpen ? menu : null);
            menu.classList.toggle('is-open', willOpen);
            panel.hidden = !willOpen;
            toggle.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
            document.body.classList.toggle('products-menu-open', willOpen);
        });
        panel.addEventListener('click', (event) => event.stopPropagation());
        panel.querySelectorAll('button').forEach(btn => {
            btn.addEventListener('click', () => closeAllProductMenus());
        });
    });
    document.body.classList.remove('products-menu-open');
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            closeAllProductMenus();
        }
    });
    document.addEventListener('click', () => closeAllProductMenus());

    function openProductModal(event) {
        if (event) {
            event.preventDefault();
            event.stopPropagation();
        }
        closeAllProductMenus();
        if (!addProductModal || !addProductFrame) return;
        addProductFrame.src = '/admin/addproduse';
        setOverlayModalOpen(addProductModal, true);
    }

    function closeProductModal() {
        if (!addProductModal || !addProductFrame) return;
        setOverlayModalOpen(addProductModal, false);
        addProductFrame.src = '';
    }

    function navigateWithServerFilters(options = {}) {
        const params = new URLSearchParams(window.location.search);
        params.set('page', '1');
        const tabKey = options.tab || activeProductTab;
        const values = {
            q: (input?.value || '').trim(),
            category: categoryFilter?.value || '',
            subcategory: subcategoryFilter?.value || '',
            marca: marcaFilter?.value || '',
            brand: brandFilter?.value || '',
            supplier: supplierFilter?.value || '',
            image: imageSourceFilter?.value || '',
            markup: (markupRuleFilter?.value || '').trim() || markupStatusFilter?.value || '',
            status: productStatusFilter?.value || '',
            sort: sortFilter?.value || '',
            origin: tabKey === 'site' || tabKey === 'export' ? tabKey : ''
        };
        Object.entries(values).forEach(([key, value]) => value ? params.set(key, value) : params.delete(key));
        if (values.image !== 'missing') {
            params.delete('filter');
        }
        params.delete('tab');
        window.location.href = window.location.pathname + '?' + params.toString();
    }

    let serverFilterTimer = 0;
    function queueServerFilterNavigation() {
        window.clearTimeout(serverFilterTimer);
        serverFilterTimer = window.setTimeout(navigateWithServerFilters, 350);
    }

    input && input.addEventListener('input', queueServerFilterNavigation);
    markupStatusFilter && markupStatusFilter.addEventListener('change', () => {
        if (markupStatusFilter.value && markupRuleFilter) {
            markupRuleFilter.value = '';
        }
        navigateWithServerFilters();
    });
    markupRuleFilter && markupRuleFilter.addEventListener('input', () => {
        if (markupRuleFilter.value.trim() && markupStatusFilter) {
            markupStatusFilter.value = '';
        }
        queueServerFilterNavigation();
    });
    categoryFilter && categoryFilter.addEventListener('change', () => {
        updateCategoryCurierButtons();
        navigateWithServerFilters();
    });
    subcategoryFilter && subcategoryFilter.addEventListener('change', navigateWithServerFilters);
    marcaFilter && marcaFilter.addEventListener('change', navigateWithServerFilters);
    brandFilter && brandFilter.addEventListener('change', navigateWithServerFilters);
    supplierFilter && supplierFilter.addEventListener('change', navigateWithServerFilters);
    productStatusFilter && productStatusFilter.addEventListener('change', navigateWithServerFilters);
    sortFilter && sortFilter.addEventListener('change', navigateWithServerFilters);
    imageSourceFilter && imageSourceFilter.addEventListener('change', navigateWithServerFilters);
    quickFilterNoImage && quickFilterNoImage.addEventListener('click', () => {
        if (listFilterActive === 'no_image') {
            window.location.href = '/admin/product?page=1';
            return;
        }
        window.location.href = '/admin/product?filter=no_image&page=1';
    });
    if (listFilterActive === 'no_image' && imageSourceFilter) {
        imageSourceFilter.value = 'missing';
    }
    tabButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            const tabKey = btn.dataset.productTab || 'all';
            activeProductTab = tabKey;
            navigateWithServerFilters({ tab: tabKey });
        });
    });
    updateTabCounts();
    updateCategoryCurierButtons();
    openAddProduct && openAddProduct.addEventListener('click', openProductModal);
    reapplyFilteredMarkup && reapplyFilteredMarkup.addEventListener('click', async () => {
        if (serverFilteredTotal <= 0) {
            alert('Nu exista produse filtrate pentru reaplicarea adaosului.');
            return;
        }
        if (!confirm(`Reaplici adaosul comercial pentru ${serverFilteredTotal} produse filtrate (toate paginile)?`)) {
            return;
        }

        const result = await reapplyMarkupByFilters();
        alert(result.message || 'Adaos reaplicat.');
        if (result.success) {
            window.location.reload();
        }
    });
    setFilteredCurierNu && setFilteredCurierNu.addEventListener('click', async () => {
        if (serverFilteredTotal <= 0) {
            alert('Nu exista produse filtrate pentru setarea livrarii curier.');
            return;
        }
        if (!confirm(`Setezi „Livrare curier: Nu” pentru ${serverFilteredTotal} produse filtrate (toate paginile)?`)) {
            return;
        }
        setFilteredCurierNu.disabled = true;
        const result = await setCurierLivrareByFilters('Nu');
        alert(result.message || (result.success ? 'Livrare curier actualizata.' : 'Nu am putut actualiza livrarea curier.'));
        setFilteredCurierNu.disabled = false;
        if (result.success) {
            window.location.reload();
        }
    });
    async function runCategoryCurierAction(btn, value) {
        if (!btn) return;
        btn.disabled = true;
        const result = await setCurierLivrareByCategory(value);
        btn.disabled = false;
        if (!result) {
            updateCategoryCurierButtons();
            return;
        }
        alert(result.message || (result.success ? 'Livrare curier actualizata.' : 'Nu am putut actualiza livrarea curier.'));
        if (result.success) {
            window.location.reload();
        }
    }
    setCategoryCurierNu && setCategoryCurierNu.addEventListener('click', () => runCategoryCurierAction(setCategoryCurierNu, 'Nu'));
    setCategoryCurierDa && setCategoryCurierDa.addEventListener('click', () => runCategoryCurierAction(setCategoryCurierDa, 'Da'));
    async function runQuickBadge(button, badgeKey, badgeLabel) {
        // Dacă există produse selectate → aplică DOAR pe selecție. Altfel → pe toate produsele filtrate.
        const selIds = catalogSelectAll ? [] : selectedIds();
        if (selIds.length > 0) {
            const selLabel = selIds.length === 1 ? 'produs selectat' : 'produse selectate';
            if (!confirm(`Ai ${selIds.length} ${selLabel}.\n\nAplic badge ${badgeLabel} DOAR pe ${selIds.length === 1 ? 'acest produs' : 'aceste produse'}?\n\n(Dacă voiai pe TOATE produsele filtrate, apasă Anulează și deselectează întâi produsele.)`)) {
                return;
            }
            button.disabled = true;
            const result = await setBadgeBulk(selIds, badgeKey);
            button.disabled = false;
            alert(result.message || (result.success ? `Badge ${badgeLabel} aplicat.` : 'Nu am putut aplica badge-ul.'));
            if (result.success) {
                window.location.reload();
            }
            return;
        }
        if (serverFilteredTotal <= 0) {
            alert(`Nu există produse selectate sau filtrate pentru aplicarea badge-ului ${badgeLabel}.`);
            return;
        }
        if (!confirm(`Nu ai selectat produse. Aplici badge ${badgeLabel} pentru TOATE cele ${serverFilteredTotal} produse filtrate?`)) {
            return;
        }
        button.disabled = true;
        const result = await applyBadgeByFilters(badgeKey);
        button.disabled = false;
        if (!result) return;
        alert(result.message || (result.success ? `Badge ${badgeLabel} aplicat.` : 'Nu am putut aplica badge-ul.'));
        if (result.success) {
            window.location.reload();
        }
    }
    setFilteredBadgeHot && setFilteredBadgeHot.addEventListener('click', () => runQuickBadge(setFilteredBadgeHot, 'hot', 'HOT'));
    setFilteredBadgePromo && setFilteredBadgePromo.addEventListener('click', () => runQuickBadge(setFilteredBadgePromo, 'promo', 'PROMO'));
    setSelectedCurierNuBtn && setSelectedCurierNuBtn.addEventListener('click', async () => {
        const ids = selectedIds();
        const count = ids.length;
        if (count <= 0) {
            alert('Nu ai selectat produse.');
            return;
        }
        if (catalogSelectAll) {
            alert('Selectarea întregului catalog nu este suportată. Bifează produsele dorite sau folosește filtrele + butonul „Livrare curier: Nu (filtrate)”.');
            return;
        }
        if (!confirm(`Setezi „Livrare curier: Nu” pentru ${count} produse selectate?`)) {
            return;
        }
        setSelectedCurierNuBtn.disabled = true;
        const result = await setCurierLivrareBulk(ids, 'Nu');
        alert(result.message || (result.success ? 'Livrare curier actualizata.' : 'Nu am putut actualiza livrarea curier.'));
        setSelectedCurierNuBtn.disabled = false;
        if (result.success) {
            window.location.reload();
        }
    });
    closeAddProductModal && closeAddProductModal.addEventListener('click', closeProductModal);
    applySelectedMarkupBtn && applySelectedMarkupBtn.addEventListener('click', () => {
        openMarkupSelectModal(selectedIds());
    });
    applySelectedBadgeBtn && applySelectedBadgeBtn.addEventListener('click', () => {
        openBadgeSelectModal(selectedIds());
    });
    closeBadgeSelectModal && closeBadgeSelectModal.addEventListener('click', closeBadgeSelectModalFn);
    cancelBadgeSelectModal && cancelBadgeSelectModal.addEventListener('click', closeBadgeSelectModalFn);
    badgeSelectModal && badgeSelectModal.addEventListener('click', (event) => {
        if (event.target === badgeSelectModal) closeBadgeSelectModalFn();
    });
    badgeSelectModal && badgeSelectModal.querySelector('.markup-select-modal__panel')?.addEventListener('click', (event) => {
        event.stopPropagation();
    });
    badgeModalSelectVisible && badgeModalSelectVisible.addEventListener('click', () => {
        if (!badgeModalProductList) return;
        badgeModalProductList.querySelectorAll('.badge-modal-product').forEach(cb => { cb.checked = true; });
        updateBadgeModalSelectionCount();
    });
    badgeModalDeselectAll && badgeModalDeselectAll.addEventListener('click', () => {
        if (!badgeModalProductList) return;
        badgeModalProductList.querySelectorAll('.badge-modal-product').forEach(cb => { cb.checked = false; });
        badgeModalExternalIds = [];
        updateBadgeModalSelectionCount();
    });
    confirmBadgeSelectModal && confirmBadgeSelectModal.addEventListener('click', async () => {
        const badge = badgeTypeSelect ? String(badgeTypeSelect.value || '') : '';
        const ids = collectBadgeModalProductIds();
        if (!ids.length) {
            alert('Selectează cel puțin un produs țintă.');
            return;
        }
        const label = badgeLabelForKey(badge);
        if (!confirm('Aplici badge „' + label + '” pe ' + ids.length + ' produse selectate?')) {
            return;
        }
        confirmBadgeSelectModal.disabled = true;
        const result = await setBadgeBulk(ids, badge);
        alert(result.message || (result.success ? 'Badge aplicat.' : 'Nu am putut aplica badge-ul.'));
        confirmBadgeSelectModal.disabled = false;
        if (result.success) {
            closeBadgeSelectModalFn();
            window.location.reload();
        }
    });
    closeMarkupSelectModal && closeMarkupSelectModal.addEventListener('click', closeMarkupSelectModalFn);
    cancelMarkupSelectModal && cancelMarkupSelectModal.addEventListener('click', closeMarkupSelectModalFn);
    markupSelectModal && markupSelectModal.addEventListener('click', (event) => {
        if (event.target === markupSelectModal) closeMarkupSelectModalFn();
    });
    markupSelectModal && markupSelectModal.querySelector('.markup-select-modal__panel')?.addEventListener('click', (event) => {
        event.stopPropagation();
    });
    markupModalSelectVisible && markupModalSelectVisible.addEventListener('click', () => {
        if (!markupModalProductList) return;
        markupModalProductList.querySelectorAll('.markup-modal-product').forEach(cb => { cb.checked = true; });
        updateMarkupModalSelectionCount();
    });
    markupModalDeselectAll && markupModalDeselectAll.addEventListener('click', () => {
        if (!markupModalProductList) return;
        markupModalProductList.querySelectorAll('.markup-modal-product').forEach(cb => { cb.checked = false; });
        markupModalExternalIds = [];
        updateMarkupModalSelectionCount();
    });
    confirmMarkupSelectModal && confirmMarkupSelectModal.addEventListener('click', async () => {
        const ruleId = parseInt(markupRuleSelect && markupRuleSelect.value ? markupRuleSelect.value : '0', 10);
        const ids = collectMarkupModalProductIds();
        if (!ruleId) {
            alert('Selectează o regulă de adaos.');
            return;
        }
        if (!ids.length) {
            alert('Selectează cel puțin un produs țintă.');
            return;
        }
        const ruleName = markupRulesData.find(rule => Number(rule.id) === ruleId)?.name || 'regula aleasă';
        if (!confirm('Aplici regula „' + ruleName + '” pe ' + ids.length + ' produse selectate?')) {
            return;
        }
        confirmMarkupSelectModal.disabled = true;
        const result = await applyMarkupRule(ruleId, ids);
        alert(result.message || (result.success ? 'Adaos aplicat.' : 'Nu am putut aplica regula.'));
        confirmMarkupSelectModal.disabled = false;
        if (result.success) {
            closeMarkupSelectModalFn();
            window.location.reload();
        }
    });
    addProductModal && addProductModal.addEventListener('click', (event) => {
        if (event.target === addProductModal) closeProductModal();
    });
    addProductModal && addProductModal.querySelector('.products-overlay-modal__panel')?.addEventListener('click', (event) => {
        event.stopPropagation();
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && addProductModal && addProductModal.classList.contains('is-open')) {
            closeProductModal();
        }
        if (event.key === 'Escape' && markupSelectModal && markupSelectModal.classList.contains('is-open')) {
            closeMarkupSelectModalFn();
        }
        if (event.key === 'Escape' && badgeSelectModal && badgeSelectModal.classList.contains('is-open')) {
            closeBadgeSelectModalFn();
        }
    });
    updateSelectionUi();

    async function launchImageAudit(ids, options) {
        if (!window.besoiuImageAudit || typeof window.besoiuImageAudit.runAudit !== 'function') {
            alert('Modulul audit imagini nu s-a încărcat. Apasă Ctrl+F5 și încearcă din nou.');
            return null;
        }
        try {
            return await window.besoiuImageAudit.runAudit(ids || [], options || {});
        } catch (err) {
            console.error('launchImageAudit', err);
            alert('Eroare la pornirea auditului: ' + (err && err.message ? err.message : String(err)));
            return null;
        }
    }

    auditSelectedImagesBtn && auditSelectedImagesBtn.addEventListener('click', async () => {
        const count = selectedCount();
        if (count <= 0) return;
        const confirmMsg = catalogSelectAll
            ? ('Pregătesc audit AI pentru toate cele ' + catalogTotal + ' produse din magazin?')
            : ('Pregătesc lot pentru Cursor Composer (' + count + ' produse)?');
        if (!confirm(confirmMsg)) return;
        auditSelectedImagesBtn.disabled = true;
        try {
            if (catalogSelectAll) {
                await launchImageAudit([], { all: true, count: catalogTotal });
            } else {
                await launchImageAudit(selectedIds());
            }
        } finally {
            updateSelectionUi();
        }
    });

    auditFilteredImagesBtn && auditFilteredImagesBtn.addEventListener('click', async () => {
        if (catalogSelectAll) {
            if (!confirm('Rulezi audit AI pe toate cele ' + catalogTotal + ' produse selectate?')) return;
            await launchImageAudit([], { all: true, count: catalogTotal });
            return;
        }
        const filters = currentServerFilters();
        const filteredTotal = serverFilteredTotal;
        if (filteredTotal <= 0) {
            alert('Nu există produse filtrate pentru audit.');
            return;
        }
        if (!confirm('Rulezi audit AI pe ' + filteredTotal + ' produse filtrate (toate paginile)?')) return;
        await launchImageAudit([], { filters, count: filteredTotal });
    });

    document.addEventListener('click', async (event) => {
        const auditButton = event.target.closest('.audit-product-image');
        if (auditButton) {
            const card = auditButton.closest('.product-card');
            if (!card || !card.dataset.id) return;
            auditButton.disabled = true;
            try {
                await launchImageAudit([card.dataset.id]);
            } finally {
                auditButton.disabled = false;
            }
            return;
        }

        const openMarkupButton = event.target.closest('.open-markup-modal');
        if (openMarkupButton) {
            const card = openMarkupButton.closest('.product-card');
            if (!card || !card.dataset.id) return;
            openMarkupSelectModal([card.dataset.id]);
            return;
        }

        const button = event.target.closest('.delete-product');
        if (!button) return;
        const card = button.closest('.product-card');
        if (!card || !card.dataset.id) return;
        if (!confirm('Ștergi acest produs?')) return;

        button.disabled = true;
        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'Accept': 'application/json'},
                credentials: 'same-origin',
                body: JSON.stringify({type_product: 'delete', id: card.dataset.id})
            });
            const raw = await response.text();
            let result = null;
            try {
                result = raw ? JSON.parse(raw) : null;
            } catch (parseErr) {
                throw new Error(
                    response.ok
                        ? 'Răspuns invalid de la server (nu e JSON).'
                        : ('Eroare server HTTP ' + response.status)
                );
            }
            alert((result && result.message) || (result && result.success ? 'Produs șters.' : 'Nu am putut șterge produsul.'));
            if (result && result.success) {
                window.location.reload();
                return;
            }
        } catch (err) {
            console.error('delete_product', err);
            alert('Nu am putut șterge produsul: ' + (err && err.message ? err.message : String(err)));
        }
        button.disabled = false;
    });

})();
</script>