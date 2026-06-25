<?php
require_once __DIR__ . '/system/page-init.php';
require_once __DIR__ . '/system/site-content.php';
require_once __DIR__ . '/system/site-live-cms.php';
require_once __DIR__ . '/system/site-builder.php';
require_once __DIR__ . '/system/besoiu-assets.php';

$GLOBALS['bpaCmsPage'] = 'home';

$homeMeta = site_content_page('home', array_merge(site_defaults_page_meta('home'), [
    'title' => 'Besoiu Piese Auto',
    'description' => site_defaults_page_meta('home')['description'] ?? '',
]));
$home = site_content_blocks('home');
$whyPhone = trim((string) ($home['why']['phone'] ?? '0726 498 573'));
$whyPhoneHref = site_phone_resolve_href($whyPhone, (string) ($home['why']['phone_href'] ?? ''));
if ($whyPhoneHref === '') {
    $whyPhoneHref = site_phone_to_tel_href($whyPhone);
}
$whyWhatsAppPrefill = trim((string) ($home['why']['whatsapp_prefill'] ?? 'Bună! Am nevoie de consultanță pentru piese auto.'));
$whyWhatsAppHref = site_phone_to_wa_href($whyPhone, $whyWhatsAppPrefill);

require_once __DIR__ . '/system/home-vitrina-render.php';
require_once __DIR__ . '/lib/Scraper/EpiesaCatalog.php';
require_once __DIR__ . '/system/scraper-home.php';
require_once __DIR__ . '/system/hero-promo-carousel.php';
$homeVitrinaProducts = besoiu_home_vitrina_products();
$homeVitrinaCount = count($homeVitrinaProducts);
$homeSpecialProducts = besoiu_home_special_products();
$homeSpecialCount = count($homeSpecialProducts);
$homeScraperTotal = EpiesaCatalog::productCount();
$homeScraperTabs = besoiu_scraper_home_tabs();
$homeScraperProducts = besoiu_scraper_catalog_products(besoiu_scraper_home_display_limit(), true);
$homeScraperCount = count($homeScraperProducts);
$homeScraperShow = $homeScraperTotal > 0;
// Carousel hero: CMS manual → produse speciale/vitrină → slide fallback
$homeHeroPromoSlides = site_live_cms_promo_slides($home);
if ($homeHeroPromoSlides === []) {
    $homeHeroPromoSlides = besoiu_hero_promo_slides(8);
}
if ($homeHeroPromoSlides === []) {
    $homeHeroPromoSlides = [besoiu_hero_promo_fallback_slide($home)];
}
$homeHeroPromoInterval = (int) ($home['hero']['promo_interval_ms'] ?? 5000);
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= site_cms_h($homeMeta['title'] ?? 'Besoiu Piese Auto') ?></title>
    <?php if (!empty($homeMeta['description'])): ?>
    <meta name="description" content="<?= site_cms_h($homeMeta['description']) ?>">
    <?php endif; ?>

    <?php besoiu_render_fonts(true); ?>
    <?php besoiu_render_styles('home'); ?>
</head>
<body>
<div class="page">

<?php include_once 'system/header.php'; ?>

<main id="main-content">

<!-- ══ FILTERBAR — categorii + vehicul (marcă / model / motorizare) ══ -->
<div class="filterbar">
    <div class="container home-filter-panel">
        <div class="cat-btn-wrap">
            <button class="cat-btn" type="button" id="cat-toggle">
                <img src="img/icons/16_meniu_categorii.svg" alt="" class="ui-icon-22 ui-icon--on-dark" role="presentation">
                <?php site_live_cms_tag('home', 'filterbar.categories_btn', 'span', (string) ($home['filterbar']['categories_btn'] ?? 'Categorii piese')); ?>
                <img src="img/icons/31_chevron_jos.svg" alt="" class="ui-icon-14 ui-icon--on-dark ui-icon--chevron-auto" role="presentation">
            </button>
            <div class="cat-badge" id="cat-badge">
                <img alt="" id="cat-badge-icon" width="24" height="24" hidden>
                <span id="cat-badge-text"></span>
                <button type="button" class="cat-badge-x" id="cat-badge-x">✕</button>
            </div>
            <div class="cat-popup" id="cat-popup">
                <!-- Categoriile se încarcă dinamic din API -->
            </div>
            <div class="cat-popup-overlay" id="cat-popup-overlay"></div>
        </div>
        <div class="vehicle-box" id="vehicle-box">
            <div class="vehicle-item">
                <div class="vico"><img src="img/icons/17_marca_auto.svg" alt="" class="ui-icon-20" role="presentation"></div>
                <div class="vehicle-item-field">
                    <strong>Marcă</strong>
                    <select class="_product-input" id="select_marca">
                        <option value="0">Alege marca</option>
                    </select>
                </div>
            </div>
            <div class="vehicle-item">
                <div class="vico"><img src="img/icons/18_model_auto.svg" alt="" class="ui-icon-20" role="presentation"></div>
                <div class="vehicle-item-field">
                    <strong>Model</strong>
                    <select class="_product-input" id="model_marca" disabled>
                        <option value="0">Alege modelul</option>
                    </select>
                </div>
            </div>
            <div class="vehicle-item">
                <div class="vico"><img src="img/icons/20_motorizare_ceas.svg" alt="" class="ui-icon-20" role="presentation"></div>
                <div class="vehicle-item-field">
                    <strong>Motorizare</strong>
                    <select class="_product-input" id="motorizari" disabled>
                        <option value="0">Alege motorizarea</option>
                    </select>
                </div>
            </div>
            <div class="vehicle-item" id="subcat-slot" style="display:none">
                <div class="vico"><img src="img/icons/16_meniu_categorii.svg" alt="" class="ui-icon-20" role="presentation"></div>
                <div class="vehicle-item-field">
                    <strong>Subcategorie</strong>
                    <select class="_product-input" id="select_categorie">
                        <option value="0">Alege subcategoria</option>
                    </select>
                </div>
            </div>
            <button class="vehicle-search" type="button" id="btnSearch" disabled><?php site_live_cms_tag('home', 'filterbar.search_btn', 'span', (string) ($home['filterbar']['search_btn'] ?? 'CAUTĂ PIESĂ')); ?></button>
        </div>
        <input type="hidden" id="_filter-category" value="">
        <input type="hidden" id="_filter-name" value="">
        <input type="hidden" id="_filter-oem" value="">
        <span id="_categorii-row" class="is-hidden"></span>
        <div id="_subcategory-panel" class="is-hidden">
            <div id="_subcategory-list" class="is-hidden"></div>
        </div>
        <div id="search-insights-bar" class="search-insights-bar is-hidden" aria-label="Căutări populare"></div>
    </div>
</div>

<!-- ══ HERO ══ -->
<section class="hero">
    <div class="container">
        <div>
            <?php site_live_cms_tag('home', 'hero.eyebrow', 'div', (string) ($home['hero']['eyebrow'] ?? ''), ['class' => 'eyebrow']); ?>
            <?php site_live_cms_tag('home', 'hero.title_html', 'h1', (string) ($home['hero']['title_html'] ?? ''), [], true); ?>
            <?php site_live_cms_tag('home', 'hero.subtitle', 'p', (string) ($home['hero']['subtitle'] ?? '')); ?>

            <form class="quick-check" onsubmit="return false;">
                <input type="text" id="_filter-vin" placeholder="<?= site_cms_h($home['hero']['search_placeholder'] ?? '') ?>" />
                <button type="button" id="_product-apply-filters"><?php site_live_cms_tag('home', 'hero.search_button', 'span', (string) ($home['hero']['search_button'] ?? '')); ?></button>
            </form>

            <div class="hero-benefits">
                <?php foreach (($home['hero']['benefits'] ?? []) as $bi => $benefit): ?>
                <div><span class="bico"><?php site_live_cms_image_tag('home', 'hero.benefits.' . $bi . '.icon', (string) ($benefit['icon'] ?? ''), ['class' => 'ui-icon-28', 'alt' => '', 'role' => 'presentation', 'data-cms-variant' => 'icon']); ?></span><?php site_live_cms_tag('home', 'hero.benefits.' . $bi . '.text', 'b', (string) ($benefit['text'] ?? '')); ?></div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="hero-art hero-art--promo-only">
            <?php besoiu_render_hero_promo_carousel($homeHeroPromoSlides, $homeHeroPromoInterval); ?>
        </div>
    </div>
</section>

<?php besoiu_render_home_vitrina_panel($home, $homeVitrinaProducts, $homeVitrinaCount); ?>

<?php site_builder_render_zone('home', 'after_hero'); ?>
<?php site_builder_render_zone('home', 'main'); ?>

<?php site_builder_render_zone('home', 'before_special'); ?>

<!-- ══ PRODUSE SPECIALE (scanate ePiesa) — înainte de Categorii populare ══ -->
<section class="section home-special-products-panel" id="home-scraper-products" aria-labelledby="home-scraper-title">
    <div class="container">
        <div class="home-special-head">
            <?php site_live_cms_tag('home', 'special_products.title_html', 'h2', (string) ($home['special_products']['title_html'] ?? ''), ['class' => 'section-title', 'id' => 'home-scraper-title'], true); ?>
            <?php site_live_cms_tag('home', 'special_products.subtitle', 'p', (string) ($home['special_products']['subtitle'] ?? ''), ['class' => 'home-special-subtitle']); ?>
        </div>
        <?php if ($homeScraperShow): ?>
        <div class="home-scraper-toolbar">
            <span><strong id="home-scraper-count"><?= (int) $homeScraperCount ?></strong><?= $homeScraperTotal > $homeScraperCount ? ' din ' . (int) $homeScraperTotal : '' ?> produse afișate</span>
        </div>
        <div class="home-special-tabs" role="tablist" aria-label="Categorii produse speciale">
            <?php foreach ($homeScraperTabs as $tab): ?>
            <button type="button"
                    class="outline-btn home-special-tab<?= $tab['slug'] === 'toate' ? ' is-active' : '' ?>"
                    role="tab"
                    data-scraper-tab="<?= site_cms_h($tab['slug']) ?>"
                    aria-selected="<?= $tab['slug'] === 'toate' ? 'true' : 'false' ?>">
                <img src="<?= site_cms_h(besoiu_scraper_tab_icon((string) $tab['slug'])) ?>" alt="" class="home-special-tab-icon" width="18" height="18" loading="lazy" decoding="async">
                <span><?= site_cms_h($tab['label']) ?></span>
                <span class="home-special-tab-count"><?= (int) $tab['count'] ?></span>
            </button>
            <?php endforeach; ?>
        </div>
        <div class="_product-grid home-special-grid" id="home-scraper-grid">
            <?php foreach ($homeScraperProducts as $homeScraperProduct): ?>
                <?php besoiu_render_scraper_product_card($homeScraperProduct); ?>
            <?php endforeach; ?>
        </div>
        <?php elseif ($homeSpecialCount > 0): ?>
        <div class="_product-grid home-special-grid" id="home-special-grid" data-home-special="1">
            <?php foreach ($homeSpecialProducts as $homeSpecialProduct): ?>
                <?php besoiu_render_home_vitrina_card($homeSpecialProduct, 'special'); ?>
            <?php endforeach; ?>
        </div>
        <div class="center-btn">
            <a class="outline-btn" href="/catalog"><?php site_live_cms_tag('home', 'special_products.button', 'span', (string) ($home['special_products']['button'] ?? '')); ?></a>
        </div>
        <?php else: ?>
        <p class="home-special-empty"><?php site_live_cms_tag('home', 'special_products.empty', 'span', (string) ($home['special_products']['empty'] ?? '')); ?></p>
        <?php endif; ?>
    </div>
</section>

<?php site_builder_render_zone('home', 'before_categories'); ?>

<!-- ══ CATEGORII POPULARE ══ -->
<section class="section">
    <div class="container">
        <?php site_live_cms_tag('home', 'categories.title_html', 'h2', (string) ($home['categories']['title_html'] ?? ''), ['class' => 'section-title'], true); ?>
        <div class="category-grid" id="category-grid-dynamic">
            <!-- Se încarcă dinamic din API -->
        </div>
        <div class="center-btn"><a class="outline-btn" href="/catalog"><?php site_live_cms_tag('home', 'categories.button', 'span', (string) ($home['categories']['button'] ?? '')); ?></a></div>
    </div>
</section>

<!-- ══ MĂRCI DISPONIBILE ══ -->
<?php site_builder_render_zone('home', 'before_brands'); ?>
<section class="section">
    <div class="container">
        <?php site_live_cms_tag('home', 'brands.title_html', 'h2', (string) ($home['brands']['title_html'] ?? ''), ['class' => 'section-title'], true); ?>
        <div class="brand-grid">
            <?php foreach (($home['brands']['items'] ?? []) as $brand): ?>
            <div class="brand" style="color:<?= site_cms_h($brand['color'] ?? '#333') ?>"><?= site_cms_h($brand['name'] ?? '') ?></div>
            <?php endforeach; ?>
        </div>
        <div class="center-btn"><a class="outline-btn" href="/catalog"><?php site_live_cms_tag('home', 'brands.button', 'span', (string) ($home['brands']['button'] ?? '')); ?></a></div>
    </div>
</section>

<?php site_builder_render_zone('home', 'before_why'); ?>

<!-- ══ DE CE NOI ══ -->
<section class="why">
    <div class="container">
        <div class="why-box">
            <div class="why-list">
                <?php site_live_cms_tag('home', 'why.title_html', 'h3', (string) ($home['why']['title_html'] ?? ''), [], true); ?>
                <ul>
                    <?php foreach (($home['why']['list'] ?? []) as $wi => $whyItem): ?>
                    <li><?php site_live_cms_tag('home', 'why.list.' . $wi, 'span', (string) $whyItem); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div class="road">
                <?php site_live_cms_image_tag('home', 'why.car_image', (string) ($home['why']['car_image'] ?? 'img/car1.png'), ['width' => '560', 'height' => '320', 'loading' => 'lazy', 'decoding' => 'async', 'class' => 'why-car-image', 'data-cms-variant' => 'full']); ?>
            </div>
            <div class="help" id="help-consult-block">
                <?php site_live_cms_tag('home', 'why.help_title', 'h3', (string) ($home['why']['help_title'] ?? '')); ?>
                <?php site_live_cms_tag('home', 'why.help_text', 'p', (string) ($home['why']['help_text'] ?? '')); ?>
                <div class="help-actions">
                    <?php if ($whyWhatsAppHref !== ''): ?>
                    <a class="whatsapp-btn" id="wa-consult-btn" href="<?= site_cms_h($whyWhatsAppHref) ?>" target="_blank" rel="noopener noreferrer" aria-label="<?= site_cms_h($home['why']['whatsapp_btn'] ?? 'Consultă pe WhatsApp') ?>" data-module="robot/webhook.php">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.435 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                        <?= site_cms_h($home['why']['whatsapp_btn'] ?? 'Consultă pe WhatsApp') ?>
                    </a>
                    <?php endif; ?>
                    <a class="call-btn" href="<?= site_cms_h($whyPhoneHref) ?>" aria-label="Sună la <?= site_cms_h($whyPhone) ?>"><?php site_live_cms_tag('home', 'why.phone', 'span', $whyPhone); ?></a>
                </div>
                <?php if (!empty($home['why']['whatsapp_hint'])): ?>
                <button type="button" class="help-ai-hint" id="help-ai-chat-trigger" data-target="#bpa-fab" data-module="robot/chat_widget_api.php"><?php site_live_cms_tag('home', 'why.whatsapp_hint', 'span', (string) ($home['why']['whatsapp_hint'] ?? '')); ?></button>
                <?php endif; ?>
                <div class="faces">
                    <div class="face"></div>
                    <div class="face"></div>
                    <div class="face"></div>
                    <span class="plus"><?php site_live_cms_tag('home', 'why.clients_plus', 'span', (string) ($home['why']['clients_plus'] ?? '')); ?></span>
                    <span class="clients"><?php site_live_cms_tag('home', 'why.clients_text_html', 'span', (string) ($home['why']['clients_text_html'] ?? ''), [], true); ?></span>
                </div>
            </div>
        </div>
    </div>
</section>

<?php site_builder_render_zone('home', 'before_footer'); ?>

</main>

<?php include_once 'system/footer.php'; ?>

</div><!-- .page -->

<?php if (besoiu_dev_tools_enabled()): ?>
<!-- DEBUG — doar local / ?besoiu_debug=1 -->
<button id="_debug-toggle" type="button" title="Deschide panoul de debug">🐞</button>
<div id="_debug-panel">
    <header>
        <span>🐞 Debug API</span>
        <span>
            <button type="button" id="_debug-clear">Curăță</button>
            <button type="button" id="_debug-close" aria-label="Închide"><span aria-hidden="true">×</span></button>
        </span>
    </header>
    <div id="_debug-log"></div>
</div>
<?php endif; ?>


<?php
$homeScripts = ['assets/js/home-tecdoc.js', 'home.js'];
if (count($homeHeroPromoSlides) > 1) {
    $homeScripts[] = 'assets/js/hero-promo-carousel.js';
}
if ($homeScraperShow) {
    $homeScripts[] = 'assets/js/home-scraper-tabs.js';
}
besoiu_render_scripts('home', $homeScripts);
?>
<?php besoiu_render_widget_deferred(); ?>
</body>
</html>
