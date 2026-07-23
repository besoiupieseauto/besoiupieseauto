<?php
declare(strict_types=1);

use Besoiu\Core\AdminUrl;
use Besoiu\Services\Products\ProduseService;
use Besoiu\Services\Scraper\ScraperService;

require_once __DIR__ . '/_produse-list-helpers.php';

$scraperMod = new ScraperService();

$service = new ProduseService();
$vitrinaCount = $service->countVitrinaProducts();

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 12;
$search = trim((string) ($_GET['q'] ?? ''));
$category = trim((string) ($_GET['category'] ?? 'toate'));
$paged = $scraperMod->epiesaListProductsPaginated($page, $perPage, $category !== 'toate' ? $category : null, $search);
$produse = $paged['items'];
$total = (int) ($paged['total'] ?? 0);
$totalPages = (int) ($paged['total_pages'] ?? 1);
$currentPage = (int) ($paged['page'] ?? 1);
$stats = $scraperMod->epiesaStats();

$produseSectionActive = 'scanate';
$produseNavVitrinaCount = $vitrinaCount;
$produseNavScraperCount = (int) ($stats['total_products'] ?? $total);
$produseNavScraperCountLazy = false;

$buildScanateQuery = static function (array $overrides = []) use ($search, $category): string {
    $params = [];
    if ($search !== '') {
        $params['q'] = $search;
    }
    if ($category !== '' && $category !== 'toate') {
        $params['category'] = $category;
    }
    $params = array_merge($params, $overrides);

    return http_build_query($params);
};
?>
<link rel="stylesheet" href="<?= produse_list_h(AdminUrl::fontAwesomeCssUrl()) ?>" crossorigin="anonymous" referrerpolicy="no-referrer">
<link rel="stylesheet" href="<?= produse_list_h(AdminUrl::publicAsset('css/admin-produse-list.css')) ?>?v=20260718-layout-v4">

<div class="-mt-5 admin-content produse-list-page produse-section-page" data-page-title="Produse scanate">
<div class="admin-panel">
    <div class="admin-panel__head">
        <div class="pl-head-main">
            <span class="pl-head-icon" aria-hidden="true"><i class="fa-solid fa-satellite-dish"></i></span>
            <h2 class="mt-0 text-lg font-medium">Produse scanate</h2>
        </div>
        <span class="pl-head-meta"><span class="pl-head-meta__dot" aria-hidden="true"></span><i class="fa-solid fa-bolt"></i> ePiesa · <?= (int) ($stats['total_products'] ?? $total) ?></span>
    </div>
    <?php require __DIR__ . '/_produse-section-nav.php'; ?>

<div class="besoiu-page besoiu-produse-page">
    <div class="besoiu-dash-hero">
        <div>
            <p class="besoiu-dash-hero__meta mt-0">Catalog parsat din scanări — afișat pe homepage la «Produse speciale».</p>
        </div>
        <div class="besoiu-dash-hero__actions">
            <a href="/admin/scraper" class="besoiu-btn-primary inline-flex items-center gap-2">
                <i class="fa-solid fa-satellite-dish"></i>
                Panou scanare
            </a>
        </div>
    </div>

    <div class="besoiu-produse-panel">
        <div class="besoiu-produse-panel__toolbar">
            <div class="besoiu-tabs besoiu-tabs--compact" role="tablist" aria-label="Categorii scanate">
                <a href="?category=toate<?= $search !== '' ? '&q=' . urlencode($search) : '' ?>"
                   class="admin-tab besoiu-tabs__btn<?= $category === 'toate' || $category === '' ? ' besoiu-tabs__btn--active admin-tab--active' : '' ?>">
                    Toate<span class="admin-tab__count"><?= (int) ($stats['total_products'] ?? 0) ?></span>
                </a>
                <?php foreach (($stats['categories'] ?? []) as $cat): ?>
                    <?php if ((int) ($cat['count'] ?? 0) > 0): ?>
                <a href="?category=<?= produse_list_h((string) ($cat['slug'] ?? '')) ?><?= $search !== '' ? '&q=' . urlencode($search) : '' ?>"
                   class="admin-tab besoiu-tabs__btn<?= $category === ($cat['slug'] ?? '') ? ' besoiu-tabs__btn--active admin-tab--active' : '' ?>">
                    <?= produse_list_h((string) ($cat['label'] ?? '')) ?><span class="admin-tab__count"><?= (int) ($cat['count'] ?? 0) ?></span>
                </a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>

            <form method="get" class="besoiu-toolbar besoiu-toolbar--inline">
                <?php if ($category !== '' && $category !== 'toate'): ?>
                    <input type="hidden" name="category" value="<?= produse_list_h($category) ?>">
                <?php endif; ?>
                <div class="besoiu-toolbar__search">
                    <input type="text" name="q" value="<?= produse_list_h($search) ?>" placeholder="Caută titlu, descriere...">
                    <i data-lucide="search" class="size-4"></i>
                </div>
                <button type="submit" class="besoiu-btn-secondary">Caută</button>
                <span class="besoiu-counter"><?= (int) $total ?> produse</span>
            </form>
        </div>

        <?php if ($produse === []): ?>
            <div class="besoiu-produse-empty">
                Niciun produs scanat. <a href="/admin/scraper">Pornește o scanare</a>.
            </div>
        <?php else: ?>
            <div class="besoiu-produse-grid">
                <?php foreach ($produse as $product): ?>
                    <?php include __DIR__ . '/_produse-card-scraper.php'; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($totalPages > 1): ?>
            <nav class="besoiu-pagination" aria-label="Paginare produse scanate">
                <?php if ($currentPage > 1): ?>
                    <a class="besoiu-pagination__btn" href="?<?= produse_list_h($buildScanateQuery(['page' => $currentPage - 1])) ?>">‹</a>
                <?php endif; ?>
                <?php for ($p = max(1, $currentPage - 2); $p <= min($totalPages, $currentPage + 2); $p++): ?>
                    <a class="besoiu-pagination__btn<?= $p === $currentPage ? ' besoiu-pagination__btn--active' : '' ?>"
                       href="?<?= produse_list_h($buildScanateQuery(['page' => $p])) ?>"><?= $p ?></a>
                <?php endfor; ?>
                <?php if ($currentPage < $totalPages): ?>
                    <a class="besoiu-pagination__btn" href="?<?= produse_list_h($buildScanateQuery(['page' => $currentPage + 1])) ?>">›</a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    </div>
</div>
</div>
</div>
