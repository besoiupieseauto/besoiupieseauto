<?php
require_once __DIR__ . '/system/page-init.php';
require_once __DIR__ . '/system/site-content.php';
require_once __DIR__ . '/system/site-live-cms.php';
require_once __DIR__ . '/system/site-builder.php';
require_once __DIR__ . '/system/besoiu-assets.php';

$GLOBALS['bpaCmsPage'] = 'catalog';

$catalogMeta = site_content_page('catalog', array_merge(site_defaults_page_meta('catalog'), [
    'title' => 'Catalog Piese Auto',
    'description' => 'Catalog piese auto - Besoiu Piese Auto. Peste 15.000 de piese pentru toate mărcile auto.',
]));
$catalogBlocks = site_content_blocks('catalog');
$catalogHero = $catalogBlocks['hero'] ?? site_defaults_blocks('catalog')['hero'];
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= site_cms_h($catalogMeta['title'] ?? 'Catalog Piese Auto') ?> - Besoiu Piese Auto</title>
    <meta name="description" content="<?= site_cms_h($catalogMeta['description'] ?? '') ?>">
    <?php besoiu_render_fonts(false); ?>
    <?php besoiu_render_styles('shop', ['assets/css/catalog-page.css']); ?>
</head>
<body>
<div class="page">
    <?php include_once 'system/header.php'; ?>

    <!-- Hero Banner -->
    <section class="cat-hero">
        <div class="cat-hero-inner">
            <?php site_live_cms_tag('catalog', 'hero.title', 'h1', (string) ($catalogHero['title'] ?? 'CATALOG PIESE AUTO')); ?>
            <?php site_live_cms_tag('catalog', 'hero.subtitle', 'p', (string) ($catalogHero['subtitle'] ?? '')); ?>
        </div>
    </section>

    <?php site_builder_render_zone('catalog', 'after_hero'); ?>
    <?php site_builder_render_zone('catalog', 'main'); ?>

    <!-- Search Bar -->
    <div class="cat-search-bar">
        <input type="text" id="hero-search" placeholder="<?= site_cms_h($catalogHero['search_placeholder'] ?? '') ?>">
        <button type="button" id="hero-search-btn"><?= site_cms_h($catalogHero['search_button'] ?? 'CAUTĂ') ?></button>
    </div>

    <main id="main-content" class="page-content">
        <div class="container">
            <nav class="breadcrumb-nav">
                <a href="/">Acasă</a>
                <span class="sep">›</span>
                <span class="current">Catalog</span>
            </nav>

            <div class="catalog-grid">
                <!-- Sidebar desktop -->
                <aside class="sidebar">
                    <div class="sidebar-card">
                        <div class="filter-actions filter-actions--top">
                            <button type="button" id="applyFilters" class="btn-apply">APLICĂ FILTRELE</button>
                            <button type="button" id="resetFilters" class="btn-reset">RESETEAZĂ</button>
                        </div>
                        <div class="widget">
                            <div class="widget-title" data-target="widget-vin">Caută după VIN</div>
                            <div class="widget-body" id="widget-vin">
                                <input type="text" class="s-input" id="vin-search" placeholder="VIN, cod OEM sau denumire produs">
                            </div>
                        </div>

                        <div class="widget">
                            <div class="widget-title" data-target="widget-categories">Categorii</div>
                            <div class="widget-body" id="widget-categories">
                                <input type="text" class="s-input s-input--spaced" id="category-search" placeholder="Caută categorie">
                                <ul class="cat-list" id="category-list">
                                    <li><a href="#" class="category-filter" data-category=""><span class="site-icon site-icon--sm"><i class="fa-solid fa-box-open"></i></span>Toate<span class="products-count"></span></a></li>
                                </ul>
                            </div>
                        </div>

                        <div class="widget">
                            <div class="widget-title" data-target="widget-subcategories">Subcategorii</div>
                            <div class="widget-body" id="widget-subcategories">
                                <ul class="cat-list" id="subcategory-list">
                                    <li><a href="#" class="subcategory-filter active" data-subcategory=""><span class="site-icon site-icon--sm"><i class="fa-solid fa-folder-tree"></i></span>Toate</a></li>
                                </ul>
                            </div>
                        </div>

                        <div class="widget">
                            <div class="widget-title" data-target="widget-marci">Marcă auto</div>
                            <div class="widget-body" id="widget-marci">
                                <ul class="cat-list" id="marca-list">
                                    <li><a href="#" class="marca-filter active" data-marca=""><span class="site-icon site-icon--sm"><i class="fa-solid fa-car"></i></span>Toate</a></li>
                                </ul>
                            </div>
                        </div>

                        <div class="widget">
                            <div class="widget-title" data-target="widget-brands">Brand produs</div>
                            <div class="widget-body" id="widget-brands">
                                <ul class="cat-list" id="brand-list">
                                    <li><a href="#" class="brand-filter active" data-brand=""><span class="site-icon site-icon--sm"><i class="fa-solid fa-industry"></i></span>Toate</a></li>
                                </ul>
                            </div>
                        </div>

                        <div class="widget">
                            <div class="widget-title" data-target="widget-price">Preț</div>
                            <div class="widget-body" id="widget-price">
                                <div class="price-slider">
                                    <div class="price-slider-track"></div>
                                    <div class="price-slider-fill" id="priceSliderFill"></div>
                                    <input type="range" id="priceMin" class="price-slider-input price-slider-min" min="0" max="6000" step="10" value="0">
                                    <input type="range" id="priceMax" class="price-slider-input price-slider-max" min="0" max="6000" step="10" value="6000">
                                </div>
                                <div class="price-range-text">Interval: <span id="filter-price-range">0 RON - 6000 RON</span></div>
                            </div>
                        </div>
                    </div>
                </aside>

                <!-- Products area -->
                <div class="products-area">
                    <div id="catalog-notices" class="catalog-notices" aria-live="polite"></div>
                    <nav class="toolbox">
                        <button type="button" class="cat-mobile-filter-btn" id="openMobileFilter" aria-label="Deschide filtrele">
                            <i class="fa-solid fa-filter"></i>
                            <span>Filtrează produse</span>
                        </button>
                        <div class="toolbox-meta">
                            <span id="pagination-info" class="toolbox-count"></span>
                        </div>
                        <div class="toolbox-controls">
                            <div class="toolbox-field">
                                <label for="orderby">Sortează</label>
                                <select name="orderby" id="orderby">
                                    <option value="default" selected>Implicit</option>
                                    <option value="price-asc">Preț ↑</option>
                                    <option value="price-desc">Preț ↓</option>
                                    <option value="name-asc">A-Z</option>
                                    <option value="name-desc">Z-A</option>
                                    <option value="time-asc">Livrare rapidă</option>
                                </select>
                            </div>
                            <div class="toolbox-field">
                                <label for="count">Pe pagină</label>
                                <select name="count" id="count">
                                    <option value="6">6</option>
                                    <option value="9">9</option>
                                    <option value="12" selected>12</option>
                                    <option value="24">24</option>
                                </select>
                            </div>
                        </div>
                    </nav>

                    <?php $besoiuProductGridMode = 'magazin'; include __DIR__ . '/system/product.php'; unset($besoiuProductGridMode); ?>

                    <div id="empty-state" class="_empty-state">
                        <i class="fa-solid fa-box-open"></i>
                        Nu au fost găsite produse pentru filtrele selectate.
                    </div>

                    <nav class="toolbox toolbox-pagination">
                        <div class="toolbox-left">
                            <label>Afișează:</label>
                            <select name="count-bottom" id="count-bottom">
                                <option value="6">6</option>
                                <option value="9">9</option>
                                <option value="12" selected>12</option>
                                <option value="24">24</option>
                            </select>
                        </div>
                        <div class="toolbox-right">
                            <span id="pagination-info-bottom" class="pagination-info-muted"></span>
                        </div>
                    </nav>
                </div>
            </div>
        </div>
    </main>

    <!-- Mobile sidebar -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <div class="mobile-sidebar" id="mobileSidebar">
        <div class="mobile-filter-head">
            <div class="mobile-filter-title">Filtrare produse</div>
            <button type="button" class="mobile-filter-close" id="closeMobileFilter" aria-label="Închide filtrele"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="sidebar-wrapper">
            <div class="widget">
                <div class="widget-title" data-target="m-widget-vin">Caută după VIN</div>
                <div class="widget-body" id="m-widget-vin">
                    <input type="text" class="s-input" id="m-vin-search" placeholder="VIN, cod OEM sau denumire produs">
                </div>
            </div>

            <div class="widget">
                <div class="widget-title" data-target="m-widget-categories">Categorii</div>
                <div class="widget-body" id="m-widget-categories">
                    <input type="text" class="s-input s-input--spaced" id="m-category-search" placeholder="Caută categorie">
                    <ul class="cat-list" id="m-category-list">
                        <li><a href="#" class="category-filter" data-category=""><span class="site-icon site-icon--sm"><i class="fa-solid fa-box-open"></i></span>Toate<span class="products-count"></span></a></li>
                    </ul>
                </div>
            </div>

            <div class="widget">
                <div class="widget-title" data-target="m-widget-subcategories">Subcategorii</div>
                <div class="widget-body" id="m-widget-subcategories">
                    <ul class="cat-list" id="m-subcategory-list">
                        <li><a href="#" class="subcategory-filter active" data-subcategory=""><span class="site-icon site-icon--sm"><i class="fa-solid fa-folder-tree"></i></span>Toate</a></li>
                    </ul>
                </div>
            </div>

            <div class="widget">
                <div class="widget-title" data-target="m-widget-marci">Marcă auto</div>
                <div class="widget-body" id="m-widget-marci">
                    <ul class="cat-list" id="m-marca-list">
                        <li><a href="#" class="marca-filter active" data-marca=""><span class="site-icon site-icon--sm"><i class="fa-solid fa-car"></i></span>Toate</a></li>
                    </ul>
                </div>
            </div>

            <div class="widget">
                <div class="widget-title" data-target="m-widget-brands">Brand produs</div>
                <div class="widget-body" id="m-widget-brands">
                    <ul class="cat-list" id="m-brand-list">
                        <li><a href="#" class="brand-filter active" data-brand=""><span class="site-icon site-icon--sm"><i class="fa-solid fa-industry"></i></span>Toate</a></li>
                    </ul>
                </div>
            </div>

            <div class="widget">
                <div class="widget-title" data-target="m-widget-price">Preț</div>
                <div class="widget-body" id="m-widget-price">
                    <div class="price-slider">
                        <div class="price-slider-track"></div>
                        <div class="price-slider-fill" id="m-priceSliderFill"></div>
                        <input type="range" id="m-priceMin" class="price-slider-input price-slider-min" min="0" max="6000" step="10" value="0">
                        <input type="range" id="m-priceMax" class="price-slider-input price-slider-max" min="0" max="6000" step="10" value="6000">
                    </div>
                    <div class="price-range-text">Interval: <span id="m-filter-price-range">0 RON - 6000 RON</span></div>
                </div>
            </div>

            <div class="filter-actions">
                <button type="button" id="applyMobileFilters" class="btn-apply">APLICĂ FILTRELE</button>
                <button type="button" id="resetMobileFilters" class="btn-reset">RESETEAZĂ</button>
            </div>
        </div>
    </div>

    <?php site_builder_render_zone('catalog', 'before_footer'); ?>

    <?php include_once 'system/footer.php'; ?>
</div>

<?php besoiu_render_scripts('shop', ['assets/js/catalog-page.js']); ?>
</body>
</html>
