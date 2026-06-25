<?php
declare(strict_types=1);

use Evasystem\Controllers\Produse\ProduseService;
use Evasystem\Controllers\Produse\ProductFacetsService;
use Evasystem\Controllers\AdaosComercial\AdaosComercialService;

require_once __DIR__ . '/_produse-list-helpers.php';

$service = new ProduseService();
$markupRulesForModal = (new AdaosComercialService())->getAll();
$vitrinaCountAdmin = $service->countVitrinaProducts();
$scraperCountAdmin = 0;
$scraperRoot = dirname(__DIR__, 5);
if (is_file($scraperRoot . '/lib/Scraper/EpiesaCatalog.php')) {
    require_once $scraperRoot . '/lib/Scraper/EpiesaCatalog.php';
    $scraperCountAdmin = EpiesaCatalog::productCount();
}
$produseSectionActive = 'lista';
$produseNavVitrinaCount = $vitrinaCountAdmin;
$produseNavScraperCount = $scraperCountAdmin;
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 10;
$allowedListFilters = ['no_image'];
$listFilter = trim((string) ($_GET['filter'] ?? ''));
if ($listFilter !== '' && !in_array($listFilter, $allowedListFilters, true)) {
    $listFilter = '';
}
$noImageOnlineCount = $service->countOnlineWithoutImage();
$paged = $service->getProdusesPaginated($page, $perPage, $listFilter !== '' ? $listFilter : null);
$produse = $paged['items'];
$paginationMeta = $paged;
$productFacets = (new ProductFacetsService())->getListFilters();

function h($value): string { return produse_list_h($value); }
function product_images($value): array { return produse_list_images($value); }
function product_first_image(array $product): string { return produse_list_first_image($product); }
function price_number($value): string { return produse_list_price_number($value); }
function product_base_price(array $product): string { return produse_list_base_price($product); }
function produse_list_page_url(int $pageNum, string $activeListFilter = ''): string
{
    $params = ['page' => max(1, $pageNum)];
    if ($activeListFilter !== '') {
        $params['filter'] = $activeListFilter;
    }

    return '?' . http_build_query($params);
}
$total = (int) ($paginationMeta['total'] ?? count($produse));
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
$badgeConfigPath = dirname(__DIR__, 5) . '/config/product-badges.php';
$badgeConfig = is_file($badgeConfigPath) ? (require $badgeConfigPath) : [];
$badgeModalJson = json_encode(array_values(array_map(static function (string $key, array $badge): array {
    return [
        'key' => $key,
        'label' => (string) ($badge['admin'] ?? $badge['label'] ?? strtoupper($key)),
    ];
}, array_keys(is_array($badgeConfig) ? $badgeConfig : []), is_array($badgeConfig) ? $badgeConfig : [])), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '[]';
?>
<style>
    .products-btn-hover,
    .products-action-hover,
    .products-page-hover,
    .products-modal-hover {
        transition: transform .16s ease, box-shadow .16s ease, background-color .16s ease, color .16s ease, border-color .16s ease, opacity .16s ease;
    }
    .products-btn-hover:hover,
    .products-action-hover:hover,
    .products-page-hover:hover,
    .products-modal-hover:hover {
        transform: translateY(-1px);
        box-shadow: 0 10px 22px rgba(15, 23, 42, .10);
    }
    .products-btn-hover:hover {
        background: rgba(37, 99, 235, .12) !important;
        border-color: rgba(37, 99, 235, .32) !important;
    }
    .products-action-hover:hover {
        color: #2563eb !important;
    }
    .delete-product.products-action-hover:hover {
        color: #dc2626 !important;
    }
    .products-page-hover:hover {
        background: #eef2ff !important;
        color: #1d4ed8 !important;
    }
    .products-modal-hover:hover {
        background: #f8fafc !important;
        border-color: #94a3b8 !important;
    }
    .products-toolbar {
        display: flex;
        flex-direction: column;
        gap: 12px;
        margin-bottom: 10px;
    }
    .products-toolbar__primary {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 10px;
        padding: 12px 14px;
        border: 1px solid rgba(15, 23, 42, .08);
        border-radius: 14px;
        background: linear-gradient(180deg, #fff 0%, #f8fafc 100%);
        box-shadow: 0 1px 0 rgba(255, 255, 255, .8) inset, 0 8px 24px rgba(15, 23, 42, .04);
    }
    .products-toolbar__actions {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 10px;
        flex: 1 1 auto;
        min-width: 0;
    }
    .products-toolbar__filters {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 10px;
    }
    .products-quick-filter {
        border-color: rgba(245, 158, 11, .45);
        background: #fffbeb;
        color: #92400e;
    }
    .products-quick-filter.is-active {
        border-color: rgba(217, 119, 6, .65);
        background: #fef3c7;
        box-shadow: 0 0 0 1px rgba(217, 119, 6, .18);
    }
    .products-quick-filter__count {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 1.5rem;
        padding: 0 6px;
        margin-left: 4px;
        border-radius: 999px;
        background: rgba(217, 119, 6, .14);
        font-size: 11px;
        font-weight: 700;
    }
    .products-filter-banner {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        padding: 10px 12px;
        border: 1px solid rgba(245, 158, 11, .35);
        border-radius: 12px;
        background: #fffbeb;
        color: #92400e;
        font-size: 13px;
    }
    .products-bulk-bar {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 10px;
        padding: 10px 12px;
        border: 1px solid rgba(15, 23, 42, .08);
        border-radius: 12px;
        background: #f8fafc;
    }
    .products-bulk-bar__count {
        font-size: 13px;
        font-weight: 600;
        color: #1e293b;
        margin-right: 4px;
    }
    .product-select-wrap {
        position: absolute;
        top: 12px;
        left: 12px;
        z-index: 20;
        display: flex;
        align-items: center;
        justify-content: center;
        width: 28px;
        height: 28px;
        border-radius: 8px;
        background: rgba(255, 255, 255, .92);
        border: 1px solid rgba(15, 23, 42, .12);
        box-shadow: 0 4px 12px rgba(15, 23, 42, .08);
        cursor: pointer;
    }
    .product-select-wrap input {
        width: 16px;
        height: 16px;
        cursor: pointer;
    }
    .product-card.is-selected .box::after {
        border-color: rgba(37, 99, 235, .45);
        box-shadow: 0 0 0 1px rgba(37, 99, 235, .18);
    }
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
        border: 1px solid rgba(37, 99, 235, .22);
        background: linear-gradient(135deg, #eff6ff 0%, #f0fdf4 100%);
        box-shadow: 0 1px 0 rgba(255, 255, 255, .7) inset;
    }
    .markup-select-modal__callout-icon {
        flex-shrink: 0;
        width: 18px;
        height: 18px;
        margin-top: 1px;
        color: #2563eb;
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
        background: linear-gradient(135deg, #fff1f2 0%, #eff6ff 100%);
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
        position: absolute;
        top: calc(100% + 6px);
        left: 0;
        z-index: 80;
        display: flex;
        flex-direction: column;
        gap: 4px;
        min-width: 240px;
        padding: 8px;
        border: 1px solid rgba(15, 23, 42, .1);
        border-radius: 12px;
        background: #fff;
        box-shadow: 0 14px 36px rgba(15, 23, 42, .14);
    }
    .products-menu__panel--hub {
        min-width: min(320px, calc(100vw - 32px));
        padding: 10px;
        gap: 8px;
    }
    .products-menu__section {
        display: flex;
        flex-direction: column;
        gap: 4px;
        padding: 6px;
        border-radius: 10px;
        background: #f8fafc;
    }
    .products-menu__section.is-disabled {
        opacity: .55;
    }
    .products-menu__section-title {
        margin: 0 0 4px;
        padding: 0 4px;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: .04em;
        text-transform: uppercase;
        color: #64748b;
    }
    .products-menu__toggle--hub {
        border-color: rgba(37, 99, 235, .28);
        background: #eff6ff;
        color: #1d4ed8;
        font-weight: 600;
    }
    .products-menu__panel[hidden] {
        display: none !important;
    }
    .products-menu__item {
        display: flex;
        width: 100%;
        align-items: center;
        justify-content: flex-start;
        gap: 8px;
        text-align: left;
        border-radius: 8px !important;
    }
    .products-menu__item:disabled {
        opacity: .45;
        cursor: not-allowed;
    }
    .products-menu--bulk .products-menu__toggle--accent {
        border-color: rgba(37, 99, 235, .35);
        background: #eff6ff;
        color: #1d4ed8;
        font-weight: 600;
    }
    .products-bulk-bar__actions {
        margin-left: auto;
    }
    @media (max-width: 767px) {
        .products-menu__panel {
            left: auto;
            right: 0;
            min-width: min(280px, calc(100vw - 24px));
        }
        .markup-select-modal__footer-actions {
            width: 100%;
            justify-content: stretch;
        }
        .markup-select-modal__footer-actions button {
            flex: 1 1 auto;
        }
    }
</style>
<div class="-mt-5 admin-content produse-list-page">
    <div class="admin-panel">
        <div class="admin-panel__head">
            <h2 class="mt-0 text-lg font-medium">Lista produse</h2>
        </div>
        <?php require __DIR__ . '/_produse-section-nav.php'; ?>
        <div class="admin-tabs" role="tablist" aria-label="Filtru sursa produse">
            <button type="button" class="admin-tab admin-tab--active" data-product-tab="all" role="tab" aria-selected="true">
                Toate<span class="admin-tab__count" id="tabCountAll">0</span>
            </button>
            <button type="button" class="admin-tab" data-product-tab="site" role="tab" aria-selected="false">
                Site propriu<span class="admin-tab__count" id="tabCountSite">0</span>
            </button>
            <button type="button" class="admin-tab" data-product-tab="export" role="tab" aria-selected="false">
                Export piese auto<span class="admin-tab__count" id="tabCountExport">0</span>
            </button>
        </div>
        <div class="mt-2 grid grid-cols-12 gap-x-6 gap-y-8">
            <div class="col-span-12 mt-2 products-toolbar">
                <div class="products-toolbar__primary">
                    <div class="products-toolbar__actions">
                        <button type="button" id="openAddProduct" class="products-btn-hover box inline-flex h-10 shrink-0 cursor-pointer items-center justify-center gap-2 whitespace-nowrap rounded-lg border border-(--color)/60 bg-(--color)/20 px-4 py-2 text-sm font-medium text-(--color) ring-offset-background transition-colors hover:bg-(--color)/5 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 [--color:var(--color-primary)] [&_svg]:pointer-events-none [&_svg]:size-4 [&_svg]:shrink-0">
                            <i data-lucide="plus" class="h-4 w-4"></i>
                            Produs nou
                        </button>
                        <div class="products-menu products-menu--hub" data-products-menu id="productsActionsHub">
                            <button type="button" id="productsActionsHubToggle" class="products-menu__toggle products-menu__toggle--hub products-btn-hover box inline-flex h-10 shrink-0 cursor-pointer items-center rounded-lg border px-4 py-2 text-sm" aria-expanded="false" aria-haspopup="true">
                                <i data-lucide="layout-grid" class="h-4 w-4"></i>
                                Acțiuni produse
                                <i data-lucide="chevron-down" class="h-3.5 w-3.5 products-menu__chevron"></i>
                            </button>
                            <div class="products-menu__panel products-menu__panel--hub" hidden>
                                <div class="products-menu__section">
                                    <p class="products-menu__section-title">Pe produse filtrate (vizibile)</p>
                                    <button type="button" id="reapplyFilteredMarkup" class="products-menu__item products-btn-hover box inline-flex h-9 items-center rounded-lg border border-emerald-300 bg-emerald-50 px-3 text-sm font-medium text-emerald-800">
                                        Reaplică adaos
                                    </button>
                                    <button type="button" id="setFilteredBadgeHot" class="products-menu__item products-btn-hover box inline-flex h-9 items-center rounded-lg border border-rose-300 bg-rose-50 px-3 text-sm font-medium text-rose-800">
                                        Badge HOT
                                    </button>
                                    <button type="button" id="setFilteredBadgePromo" class="products-menu__item products-btn-hover box inline-flex h-9 items-center rounded-lg border border-orange-300 bg-orange-50 px-3 text-sm font-medium text-orange-900">
                                        Badge PROMO
                                    </button>
                                    <button type="button" id="setFilteredCurierNu" class="products-menu__item products-btn-hover box inline-flex h-9 items-center rounded-lg border border-amber-300 bg-amber-50 px-3 text-sm font-medium text-amber-900">
                                        Livrare curier: Nu
                                    </button>
                                    <button type="button" id="auditFilteredImagesBtn" class="products-menu__item products-btn-hover btn-audit-images box inline-flex h-9 items-center rounded-lg border px-3 text-sm font-medium">
                                        <i data-lucide="scan-eye" class="h-4 w-4"></i>
                                        Audit Cursor
                                    </button>
                                </div>
                                <div class="products-menu__section" id="categoryCurierSection">
                                    <p class="products-menu__section-title">Curier — categorie din filtru</p>
                                    <button type="button" id="setCategoryCurierNu" class="products-menu__item products-btn-hover box inline-flex h-9 items-center rounded-lg border border-amber-300 bg-amber-50 px-3 text-sm font-medium text-amber-950" disabled>
                                        Livrare curier: Nu
                                    </button>
                                    <button type="button" id="setCategoryCurierDa" class="products-menu__item products-btn-hover box inline-flex h-9 items-center rounded-lg border border-sky-300 bg-sky-50 px-3 text-sm font-medium text-sky-900" disabled>
                                        Livrare curier: Da
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="shrink-0">
                            <button type="button" id="openAddProductMini" class="products-btn-hover box relative z-[51] inline-flex h-10 cursor-pointer items-center justify-center gap-2 whitespace-nowrap rounded-lg border border-(--color)/20 bg-background px-2 py-2 text-sm font-medium text-(--color) ring-offset-background transition-colors hover:bg-(--color)/5 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 [--color:var(--color-foreground)] [&_svg]:pointer-events-none [&_svg]:size-4 [&_svg]:shrink-0" aria-label="Produs nou">
                                <span class="flex h-5 w-5 items-center justify-center">
                                    <i data-lucide="plus" class="h-4 w-4"></i>
                                </span>
                            </button>
                        </div>
                    </div>
                    <div class="shrink-0 text-sm font-medium text-slate-600" id="entriesText">
                        <?= $total ? (($currentPage - 1) * $perPage + 1) . '–' . min($currentPage * $perPage, $total) . ' din ' . $total . ' produse' : '0 produse' ?>
                    </div>
                </div>
                <div class="products-toolbar__filters">
                <button type="button"
                        id="quickFilterNoImage"
                        class="products-btn-hover products-quick-filter box inline-flex h-10 shrink-0 cursor-pointer items-center justify-center gap-2 whitespace-nowrap rounded-lg border px-4 py-2 text-sm font-medium ring-offset-background transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50<?= $listFilter === 'no_image' ? ' is-active' : '' ?>"
                        aria-pressed="<?= $listFilter === 'no_image' ? 'true' : 'false' ?>"
                        title="Afișează toate produsele online fără poză reală în magazin">
                    <i data-lucide="image-off" class="h-4 w-4"></i>
                    Produse fără imagine
                    <span class="products-quick-filter__count" id="quickFilterNoImageCount"><?= (int) $noImageOnlineCount ?></span>
                </button>
                <select id="imageSourceFilter" class="box h-10 shrink-0 rounded-md border bg-background px-3 py-2">
                    <option value="">Toate imaginile</option>
                    <option value="missing">Fara imagine</option>
                    <option value="tecdoc_api">TecDoc API</option>
                    <option value="csv">CSV</option>
                    <option value="caietcomenzi">Caiet comenzi</option>
                </select>
                <select id="categoryFilter" class="box h-10 shrink-0 rounded-md border bg-background px-3 py-2">
                    <option value="">Toate categoriile</option>
                    <?php foreach ($productFacets['categories'] as $facet): ?>
                        <option value="<?= h(mb_strtolower($facet['label'], 'UTF-8')) ?>" data-label="<?= h($facet['label']) ?>" data-count="<?= (int) ($facet['count'] ?? 0) ?>"><?= h($facet['label']) ?> (<?= h((string) ($facet['count'] ?? 0)) ?>)</option>
                    <?php endforeach; ?>
                </select>
                <select id="subcategoryFilter" class="box h-10 shrink-0 rounded-md border bg-background px-3 py-2">
                    <option value="">Toate subcategoriile</option>
                    <?php foreach ($productFacets['subcategories'] as $facet): ?>
                        <option value="<?= h(mb_strtolower($facet['label'], 'UTF-8')) ?>" data-label="<?= h($facet['label']) ?>" data-count="<?= (int) ($facet['count'] ?? 0) ?>"><?= h($facet['label']) ?> (<?= h((string) ($facet['count'] ?? 0)) ?>)</option>
                    <?php endforeach; ?>
                </select>
                <select id="marcaFilter" class="box h-10 shrink-0 rounded-md border bg-background px-3 py-2">
                    <option value="">Toate mărcile</option>
                    <?php foreach ($productFacets['marci'] as $facet): ?>
                        <option value="<?= h(mb_strtolower($facet['label'], 'UTF-8')) ?>"><?= h($facet['label']) ?> (<?= h((string) ($facet['count'] ?? 0)) ?>)</option>
                    <?php endforeach; ?>
                </select>
                <select id="brandFilter" class="box h-10 shrink-0 rounded-md border bg-background px-3 py-2">
                    <option value="">Toate brandurile</option>
                    <?php foreach ($productFacets['brands'] ?? [] as $facet): ?>
                        <option value="<?= h(mb_strtolower($facet['label'], 'UTF-8')) ?>"><?= h($facet['label']) ?> (<?= h((string) ($facet['count'] ?? 0)) ?>)</option>
                    <?php endforeach; ?>
                </select>
                <select id="sortFilter" class="box h-10 shrink-0 rounded-md border bg-background px-3 py-2">
                    <option value="">Sortare implicită</option>
                    <option value="brand-asc">Brand piesă A–Z</option>
                    <option value="brand-desc">Brand piesă Z–A</option>
                </select>
                <select id="markupStatusFilter" class="box h-10 shrink-0 rounded-md border bg-background px-3 py-2 ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-foreground/5 focus-visible:ring-offset-2">
                    <option value="">Toate regulile</option>
                    <option value="with-rule">Cu adaos aplicat</option>
                    <option value="without-rule">Fara regula</option>
                </select>
                <input id="markupRuleFilter" list="markupRuleList" class="box h-10 w-48 shrink-0 rounded-md border bg-background px-3 py-2 ring-offset-background placeholder:text-foreground/70 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-foreground/5 focus-visible:ring-offset-2" type="text" placeholder="Filtru regula...">
                <datalist id="markupRuleList">
                    <?php
                    $ruleNames = [];
                    foreach ($produse as $ruleProduct) {
                        $ruleName = trim((string)($ruleProduct['pMarkupRuleName'] ?? ''));
                        if ($ruleName !== '') {
                            $ruleNames[$ruleName] = true;
                        }
                    }
                    ksort($ruleNames, SORT_NATURAL | SORT_FLAG_CASE);
                    foreach (array_keys($ruleNames) as $ruleName):
                    ?>
                        <option value="<?= h($ruleName) ?>"></option>
                    <?php endforeach; ?>
                </datalist>
                <div class="relative h-10 w-56 shrink-0">
                    <input id="filterText" class="box h-10 w-56 rounded-md border bg-background py-2 pl-3 pr-10 ring-offset-background file:border-0 file:bg-transparent file:font-medium file:text-foreground placeholder:text-foreground/70 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-foreground/5 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50" type="text" placeholder="Search...">
                    <i data-lucide="search" class="absolute inset-y-0 right-0 my-auto mr-3 h-4 w-4"></i>
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
                    Vizibile
                </button>
                <button type="button" id="deselectAllBtn" class="products-btn-hover box inline-flex h-9 items-center rounded-lg border px-3 text-sm">
                    Deselectează
                </button>
                <button type="button" id="selectAllCatalogBtn" class="products-btn-hover box inline-flex h-9 items-center rounded-lg border px-3 text-sm" data-total="<?= (int) $total ?>">
                    Tot catalogul (<?= (int) $total ?>)
                </button>
                <span class="products-bulk-bar__count" id="selectedCount">0 selectate</span>
                <div class="products-bulk-bar__actions products-menu products-menu--bulk" data-products-menu>
                    <button type="button" id="bulkActionsToggle" class="products-menu__toggle products-menu__toggle--accent products-btn-hover box inline-flex h-9 items-center rounded-lg border px-4 text-sm" aria-expanded="false" aria-haspopup="true" disabled>
                        <i data-lucide="layers" class="h-4 w-4"></i>
                        Acțiuni selecție
                        <i data-lucide="chevron-down" class="h-3.5 w-3.5 products-menu__chevron"></i>
                    </button>
                    <div class="products-menu__panel" hidden>
                        <button type="button" id="applySelectedMarkupBtn" class="products-menu__item products-btn-hover box inline-flex h-9 items-center rounded-lg border border-emerald-300 bg-emerald-50 px-3 text-sm font-medium text-emerald-800" disabled>
                            Aplică adaos selectiv
                        </button>
                        <button type="button" id="applySelectedBadgeBtn" class="products-menu__item products-btn-hover box inline-flex h-9 items-center rounded-lg border border-rose-300 bg-rose-50 px-3 text-sm font-medium text-rose-800" disabled>
                            Aplică badge selectiv
                        </button>
                        <button type="button" id="setSelectedCurierNuBtn" class="products-menu__item products-btn-hover box inline-flex h-9 items-center rounded-lg border border-amber-300 bg-amber-50 px-3 text-sm font-medium text-amber-900" disabled>
                            Livrare curier: Nu
                        </button>
                        <button type="button" id="auditSelectedImagesBtn" class="products-menu__item products-btn-hover btn-audit-images box inline-flex h-9 items-center rounded-lg border px-3 text-sm font-medium" disabled title="Pregătește lot pentru Cursor Composer 2.5 (fără OpenAI)">
                            <i data-lucide="scan-eye" class="h-4 w-4"></i>
                            Audit imagini (selectate)
                        </button>
                        <button type="button" id="deleteSelectedBtn" class="products-menu__item products-btn-hover btn-delete-selected box inline-flex h-9 items-center rounded-lg border px-3 text-sm font-medium" disabled>
                            Șterge selectate
                        </button>
                    </div>
                </div>
            </div>

            <!-- BEGIN: Data List -->
            <?php foreach ($produse as $index => $product): ?>
                <?php
                    $id = (string)($product['randomn_id'] ?? $product['id'] ?? '');
                    $name = $product['pName'] ?: ($product['name'] ?? 'Produs fara nume');
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
                    <div class="box relative before:absolute before:inset-0 before:mx-3 before:-mb-3 before:border before:border-foreground/10 before:bg-background/30 before:shadow-[0px_3px_5px_#0000000b] before:z-[-1] before:rounded-xl after:absolute after:inset-0 after:border after:border-foreground/10 after:bg-background after:shadow-[0px_3px_5px_#0000000b] after:rounded-xl after:z-[-1] after:backdrop-blur-md p-0">
                        <label class="product-select-wrap" title="Selectează produs">
                            <input type="checkbox" class="product-select" value="<?= h($id) ?>" aria-label="Selectează <?= h($name) ?>">
                        </label>
                        <div class="p-5">
                            <div class="image-fit h-40 overflow-hidden rounded-lg before:absolute before:left-0 before:top-0 before:z-10 before:block before:h-full before:w-full before:bg-gradient-to-t before:from-black before:to-black/10 2xl:h-56<?= $hasRealImage ? '' : ' opacity-80' ?>">
                                <img class="rounded-lg<?= $hasRealImage ? '' : ' grayscale-[0.35]' ?>" src="<?= h(product_first_image($product)) ?>" alt="<?= h($name) ?>" loading="lazy" decoding="async">
                                <?php if (!$hasRealImage): ?>
                                    <div class="absolute right-0 top-0 z-20 m-3 rounded-full border border-amber-300 bg-amber-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-amber-900">
                                        Placeholder
                                    </div>
                                <?php endif; ?>
                                <div class="absolute bottom-0 z-10 px-5 pb-6 text-white">
                                    <a class="block text-base font-medium" href="/admin/editproduse?id=<?= urlencode($id) ?>">
                                        <?= h($name) ?>
                                    </a>
                                </div>
                            </div>
                            <div class="mt-5 opacity-70">
                                <div class="flex items-center">
                                    <i data-lucide="barcode" class="mr-2 h-4 w-4"></i>
                                    Cod brand: <?= $code !== '' ? h($code) : 'Nesetat' ?>
                                </div>
                                <div class="mt-2 flex items-center">
                                    <i data-lucide="wallet" class="mr-2 h-4 w-4"></i>
                                    Pret achizitie: <?= $basePrice !== '' ? h($basePrice) . ' lei' : 'Nesetat' ?>
                                </div>
                                <div class="mt-2 flex items-center">
                                    <i data-lucide="link" class="mr-2 h-4 w-4"></i>
                                    Pret final: <?= $price !== '' ? h($price) . ' lei' : 'Nesetat' ?>
                                </div>
                                <div class="mt-2 flex items-center">
                                    <i data-lucide="badge-percent" class="mr-2 h-4 w-4"></i>
                                    Regula adaos: <?= $markupRule !== '' ? h($markupRule) : 'Fara adaos activ' ?>
                                </div>
                                <div class="mt-2 flex items-center">
                                    <i data-lucide="check-square" class="mr-2 h-4 w-4"></i>
                                    Status: <?= h($active ? 'Activ' : 'Inactiv') ?>
                                </div>
                            </div>
                        </div>
                        <div class="relative z-10 flex flex-wrap items-center justify-end gap-2 border-t p-5">
                            <button class="audit-product-image products-action-hover flex items-center text-violet-700" type="button" title="Pregătește audit în Cursor Composer">
                                <i data-lucide="scan-eye" class="mr-1 h-4 w-4"></i>
                                Audit Cursor
                            </button>
                            <button class="open-markup-modal products-action-hover flex items-center text-emerald-700" type="button">
                                <i data-lucide="badge-percent" class="mr-1 h-4 w-4"></i>
                                Aplică adaos
                            </button>
                            <a class="products-action-hover mr-3 flex items-center" href="/admin/editproduse?id=<?= urlencode($id) ?>">
                                <i data-lucide="check-square" class="mr-1 h-4 w-4"></i>
                                Edit
                            </a>
                            <button class="delete-product products-action-hover text-danger flex items-center" type="button">
                                <i data-lucide="trash" class="mr-1 h-4 w-4"></i>
                                Delete
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            <!-- END: Data List -->

            <div id="emptyState" class="box col-span-12 hidden p-8 text-center opacity-70">Nu am gasit produse dupa cautarea aleasa.</div>

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
                <div class="markup-select-modal__header-sub">Filtrează după brand, selectează produsele și alege badge-ul (ex. HOT, PROMO).</div>
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
    const empty = document.getElementById('emptyState');
    const entries = document.getElementById('entriesText');
    const paginationBar = document.getElementById('paginationBar');
    const markupStatusFilter = document.getElementById('markupStatusFilter');
    const markupRuleFilter = document.getElementById('markupRuleFilter');
    const categoryFilter = document.getElementById('categoryFilter');
    const subcategoryFilter = document.getElementById('subcategoryFilter');
    const marcaFilter = document.getElementById('marcaFilter');
    const brandFilter = document.getElementById('brandFilter');
    const sortFilter = document.getElementById('sortFilter');
    const imageSourceFilter = document.getElementById('imageSourceFilter');
    const quickFilterNoImage = document.getElementById('quickFilterNoImage');
    const listFilterActive = <?= json_encode($listFilter, JSON_UNESCAPED_UNICODE) ?>;
    const tabButtons = Array.from(document.querySelectorAll('[data-product-tab]'));
    const tabCountAll = document.getElementById('tabCountAll');
    const tabCountSite = document.getElementById('tabCountSite');
    const tabCountExport = document.getElementById('tabCountExport');
    let activeProductTab = 'all';
    const reapplyFilteredMarkup = document.getElementById('reapplyFilteredMarkup');
    const setFilteredCurierNu = document.getElementById('setFilteredCurierNu');
    const setCategoryCurierNu = document.getElementById('setCategoryCurierNu');
    const setCategoryCurierDa = document.getElementById('setCategoryCurierDa');
    const openAddProduct = document.getElementById('openAddProduct');
    const openAddProductMini = document.getElementById('openAddProductMini');
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
        return filteredCards()
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
        if (deleteSelectedBtn) {
            deleteSelectedBtn.disabled = count <= 0;
            deleteSelectedBtn.textContent = count > 0 ? ('Șterge selectate (' + count + ')') : 'Șterge selectate';
        }
        if (auditSelectedImagesBtn) {
            auditSelectedImagesBtn.disabled = count <= 0;
            auditSelectedImagesBtn.textContent = count > 0
                ? ('Audit Cursor (' + count + ')')
                : 'Audit imagini (Cursor)';
        }
        if (applySelectedMarkupBtn) {
            applySelectedMarkupBtn.disabled = count <= 0 || catalogSelectAll;
            applySelectedMarkupBtn.textContent = count > 0
                ? ('Aplică adaos selectiv (' + count + ')')
                : 'Aplică adaos selectiv';
        }
        if (setSelectedCurierNuBtn) {
            setSelectedCurierNuBtn.disabled = count <= 0 || catalogSelectAll;
            setSelectedCurierNuBtn.textContent = count > 0
                ? ('Livrare curier: Nu (' + count + ')')
                : 'Livrare curier: Nu';
        }
        if (applySelectedBadgeBtn) {
            applySelectedBadgeBtn.disabled = count <= 0 || catalogSelectAll;
            applySelectedBadgeBtn.textContent = count > 0
                ? ('Aplică badge selectiv (' + count + ')')
                : 'Aplică badge selectiv';
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
    deleteSelectedBtn && deleteSelectedBtn.addEventListener('click', async () => {
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
        const response = await fetch(endpoint, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(
                catalogSelectAll
                    ? {type_product: 'delete_bulk', all: true, confirm: 'STERGE TOT'}
                    : {type_product: 'delete_bulk', ids: ids}
            )
        });
        const result = await response.json();
        alert(result.message || (result.success ? 'Produse șterse.' : 'Nu am putut șterge produsele.'));
        if (result.success) {
            window.location.href = '/admin/product?page=1';
        } else {
            deleteSelectedBtn.disabled = false;
            updateSelectionUi();
        }
    });

    function cards() {
        return Array.from(document.querySelectorAll('.product-card'));
    }

    function filteredCards() {
        return cards().filter(card => !card.classList.contains('hidden-by-filter') && !card.classList.contains('hidden-by-tab'));
    }

    function updateTabCounts() {
        const all = cards();
        const site = all.filter(card => (card.dataset.productOrigin || 'site') === 'site');
        const exp = all.filter(card => (card.dataset.productOrigin || '') === 'export');
        if (tabCountAll) tabCountAll.textContent = String(all.length);
        if (tabCountSite) tabCountSite.textContent = String(site.length);
        if (tabCountExport) tabCountExport.textContent = String(exp.length);
    }

    function setActiveTab(tabKey) {
        activeProductTab = tabKey;
        tabButtons.forEach(btn => {
            const isActive = (btn.dataset.productTab || 'all') === tabKey;
            btn.classList.toggle('admin-tab--active', isActive);
            btn.setAttribute('aria-selected', isActive ? 'true' : 'false');
        });
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
            html += '<div class="markup-select-modal__product-row" style="background:#eef2ff;">' +
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

        cardsOnPage.forEach(card => {
            const id = String(card.dataset.id || '');
            if (!id) return;
            const name = cardProductLabel(card);
            const brand = card.dataset.brand || '';
            const badgeCurrent = card.dataset.badge || '';
            const checked = selectedSet.has(id) ? ' checked' : '';
            html += '<label class="markup-select-modal__product-row">' +
                '<input type="checkbox" class="badge-modal-product h-4 w-4 mt-0.5" value="' + escapeHtml(id) + '"' + checked + '>' +
                '<span><strong>' + escapeHtml(name) + '</strong>' +
                '<div class="markup-select-modal__product-meta">Brand: ' + escapeHtml(brand || 'Nesetat') +
                ' · Badge curent: ' + escapeHtml(badgeCurrent ? badgeLabelForKey(badgeCurrent) : 'Fără badge') +
                '</div></span></label>';
            selectedSet.delete(id);
        });

        badgeModalExternalIds = Array.from(selectedSet);
        if (badgeModalExternalIds.length > 0) {
            html += '<div class="markup-select-modal__product-row" style="background:#eef2ff;">' +
                '<span><strong>' + badgeModalExternalIds.length + ' produse selectate din alte pagini</strong>' +
                '<div class="markup-select-modal__product-meta">Vor fi incluse la aplicare (IDs din selecția listei).</div></span></div>';
        }

        if (!html) {
            html = '<div class="markup-select-modal__product-row"><span>Nu există produse pe pagina curentă.</span></div>';
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

    function applySort() {
        const sort = (sortFilter && sortFilter.value) || '';
        const emptyEl = document.getElementById('emptyState');
        const container = emptyEl ? emptyEl.parentElement : null;
        if (!container) {
            return;
        }

        const anchor = emptyEl || document.getElementById('paginationBar');
        const sorted = cards().slice().sort((a, b) => {
            if (sort === 'brand-asc') {
                return (a.dataset.brand || '').localeCompare(b.dataset.brand || '', 'ro', {sensitivity: 'base'});
            }
            if (sort === 'brand-desc') {
                return (b.dataset.brand || '').localeCompare(a.dataset.brand || '', 'ro', {sensitivity: 'base'});
            }
            return Number(a.dataset.order || 0) - Number(b.dataset.order || 0);
        });

        sorted.forEach(card => {
            if (anchor) {
                container.insertBefore(card, anchor);
            } else {
                container.appendChild(card);
            }
        });
    }

    function applyFilters() {
        const text = (input.value || '').toLowerCase().trim();
        const markupStatus = (markupStatusFilter && markupStatusFilter.value) || '';
        const markupRule = (markupRuleFilter && markupRuleFilter.value || '').toLowerCase().trim();
        const category = (categoryFilter && categoryFilter.value || '').toLowerCase().trim();
        const subcategory = (subcategoryFilter && subcategoryFilter.value || '').toLowerCase().trim();
        const marca = (marcaFilter && marcaFilter.value || '').toLowerCase().trim();
        const brand = (brandFilter && brandFilter.value || '').toLowerCase().trim();
        const imageSource = (imageSourceFilter && imageSourceFilter.value || '').toLowerCase().trim();
        cards().forEach(card => {
            const matchesText = !text || card.dataset.search.includes(text);
            const hasRule = card.dataset.hasRule === '1';
            const matchesStatus = !markupStatus
                || (markupStatus === 'with-rule' && hasRule)
                || (markupStatus === 'without-rule' && !hasRule);
            const matchesRule = !markupRule || (card.dataset.markupRule || '').includes(markupRule);
            const matchesCategory = !category || (card.dataset.category || '') === category;
            const matchesSubcategory = !subcategory || (card.dataset.subcategory || '') === subcategory;
            const matchesMarca = !marca || (card.dataset.marca || '') === marca;
            const matchesBrand = !brand || (card.dataset.brand || '') === brand;
            const matchesImageSource = !imageSource || (
                imageSource === 'missing'
                    ? (card.dataset.hasRealImage === '0' && card.dataset.active === '1')
                    : (card.dataset.imageSource || '') === imageSource
            );
            const origin = card.dataset.productOrigin || 'site';
            const matchesTab = activeProductTab === 'all' || origin === activeProductTab;
            const ok = matchesText && matchesStatus && matchesRule && matchesCategory && matchesSubcategory && matchesMarca && matchesBrand && matchesImageSource && matchesTab;
            card.classList.toggle('hidden-by-filter', !ok);
            card.classList.toggle('hidden-by-tab', activeProductTab !== 'all' && !matchesTab);
        });
        applySort();
        currentPage = 1;
        paginate();
    }

    function paginate() {
        const allFiltered = filteredCards();
        cards().forEach(card => {
            if (card.classList.contains('hidden-by-filter') || card.classList.contains('hidden-by-tab')) {
                card.classList.add('hidden');
            } else {
                card.classList.remove('hidden');
            }
        });
        if (empty) empty.classList.toggle('hidden', allFiltered.length !== 0);
        updateSelectionUi();
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
            }
            if (toggle) {
                toggle.setAttribute('aria-expanded', 'false');
            }
        });
    }

    document.querySelectorAll('[data-products-menu]').forEach(menu => {
        const toggle = menu.querySelector('.products-menu__toggle');
        const panel = menu.querySelector('.products-menu__panel');
        if (!toggle || !panel) {
            return;
        }
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
        });
        panel.querySelectorAll('button').forEach(btn => {
            btn.addEventListener('click', () => closeAllProductMenus());
        });
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

    input && input.addEventListener('input', applyFilters);
    markupStatusFilter && markupStatusFilter.addEventListener('change', applyFilters);
    markupRuleFilter && markupRuleFilter.addEventListener('input', applyFilters);
    categoryFilter && categoryFilter.addEventListener('change', () => {
        updateCategoryCurierButtons();
        applyFilters();
    });
    subcategoryFilter && subcategoryFilter.addEventListener('change', applyFilters);
    marcaFilter && marcaFilter.addEventListener('change', applyFilters);
    brandFilter && brandFilter.addEventListener('change', applyFilters);
    sortFilter && sortFilter.addEventListener('change', applyFilters);
    imageSourceFilter && imageSourceFilter.addEventListener('change', applyFilters);
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
            setActiveTab(btn.dataset.productTab || 'all');
            applyFilters();
        });
    });
    updateTabCounts();
    updateCategoryCurierButtons();
    openAddProduct && openAddProduct.addEventListener('click', openProductModal);
    openAddProductMini && openAddProductMini.addEventListener('click', openProductModal);
    reapplyFilteredMarkup && reapplyFilteredMarkup.addEventListener('click', async () => {
        const ids = filteredCards().map(card => card.dataset.id).filter(Boolean);
        if (!ids.length) {
            alert('Nu exista produse filtrate pentru reaplicarea adaosului.');
            return;
        }
        if (!confirm(`Reaplici adaosul comercial pentru ${ids.length} produse filtrate?`)) {
            return;
        }

        const result = await reapplyMarkup(ids);
        alert(result.message || 'Adaos reaplicat.');
        if (result.success) {
            window.location.reload();
        }
    });
    setFilteredCurierNu && setFilteredCurierNu.addEventListener('click', async () => {
        const ids = filteredCards().map(card => card.dataset.id).filter(Boolean);
        if (!ids.length) {
            alert('Nu exista produse filtrate pentru setarea livrarii curier.');
            return;
        }
        if (!confirm(`Setezi „Livrare curier: Nu” pentru ${ids.length} produse filtrate (vizibile pe pagina curenta)?`)) {
            return;
        }
        setFilteredCurierNu.disabled = true;
        const result = await setCurierLivrareBulk(ids, 'Nu');
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
    setFilteredBadgeHot && setFilteredBadgeHot.addEventListener('click', async () => {
        const ids = filteredCards().map(card => card.dataset.id).filter(Boolean);
        if (!ids.length) {
            alert('Nu există produse filtrate pentru aplicarea badge-ului HOT.');
            return;
        }
        setFilteredBadgeHot.disabled = true;
        const result = await applyBadgeToIds(ids, 'hot', 'HOT (rosu)');
        setFilteredBadgeHot.disabled = false;
        if (!result) return;
        alert(result.message || (result.success ? 'Badge HOT aplicat.' : 'Nu am putut aplica badge-ul.'));
        if (result.success) {
            window.location.reload();
        }
    });
    setFilteredBadgePromo && setFilteredBadgePromo.addEventListener('click', async () => {
        const ids = filteredCards().map(card => card.dataset.id).filter(Boolean);
        if (!ids.length) {
            alert('Nu există produse filtrate pentru aplicarea badge-ului PROMO.');
            return;
        }
        setFilteredBadgePromo.disabled = true;
        const result = await applyBadgeToIds(ids, 'promo', 'PROMO (portocaliu)');
        setFilteredBadgePromo.disabled = false;
        if (!result) return;
        alert(result.message || (result.success ? 'Badge PROMO aplicat.' : 'Nu am putut aplica badge-ul.'));
        if (result.success) {
            window.location.reload();
        }
    });
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
    paginate();
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
        const ids = filteredCards().map(card => card.dataset.id).filter(Boolean);
        if (!ids.length) {
            alert('Nu există produse vizibile pentru audit.');
            return;
        }
        if (!confirm('Rulezi audit AI pe ' + ids.length + ' produse vizibile (pagina curentă)?')) return;
        await launchImageAudit(ids);
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
        if (!card || !confirm('Stergi acest produs?')) return;

        const response = await fetch(endpoint, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({type_product: 'delete', id: card.dataset.id})
        });
        const result = await response.json();
        alert(result.message || (result.success ? 'Produs sters.' : 'Nu am putut sterge produsul.'));
        if (result.success) {
            window.location.reload();
        }
    });
})();
</script>