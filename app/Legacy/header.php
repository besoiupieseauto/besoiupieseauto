<?php
require_once __DIR__ . '/page-init.php';
require_once __DIR__ . '/site-content.php';
require_once __DIR__ . '/store-contact.php';

$shopAccountUser = shop_auth_session_user();
$global = site_content_blocks('global');

$current_page = basename($_SERVER['PHP_SELF']);

$nav_links = $global['nav'] ?? site_defaults_blocks('global')['nav'];
$nav_support = $global['nav_support'] ?? site_defaults_blocks('global')['nav_support'];
$hdr = $global['header'] ?? [];
$topbar = $global['topbar'] ?? [];
$hdrPhone = trim((string) ($hdr['phone'] ?? besoiu_store_phone_display()));
$hdrPhoneHref = site_phone_resolve_href($hdrPhone, trim((string) ($hdr['phone_href'] ?? '')));
if ($hdrPhoneHref === '') {
    $hdrPhoneHref = besoiu_store_phone_tel();
}
$hdrPhoneLabel = trim((string) ($hdr['phone_label'] ?? 'Sună acum'));
if ($hdrPhoneLabel === '' || strcasecmp($hdrPhoneLabel, $hdrPhone) === 0) {
    $hdrPhoneLabel = 'Sună acum';
}
$intelSearchEnabled = filter_var(getenv('AI_INTEL_STOREFRONT_SEARCH') ?: '1', FILTER_VALIDATE_BOOLEAN);
?>

<div class="topbar">
    <div class="container">
        <div class="top-left">
            <?php foreach ($topbar as $ti => $topItem): ?>
            <div class="top-item"><?php if (function_exists('site_live_cms_image_tag')): ?><?php site_live_cms_image_tag('global', 'topbar.' . $ti . '.icon', (string) ($topItem['icon'] ?? ''), ['class' => 'hdr-icon', 'alt' => (string) ($topItem['text'] ?? ''), 'width' => '18', 'height' => '18', 'data-cms-variant' => 'icon']); ?><?php else: ?><img src="<?= site_cms_h($topItem['icon'] ?? '') ?>" alt="<?= site_cms_h($topItem['text'] ?? '') ?>" class="hdr-icon" width="18" height="18"><?php endif; ?> <?= site_cms_h($topItem['text'] ?? '') ?></div>
            <?php endforeach; ?>
        </div>
        <nav class="top-nav" aria-label="Navigare secundară">
            <?php foreach (array_slice($nav_links, 1, 3) as $link): ?>
            <a href="<?= site_cms_h(besoiu_normalize_href((string) ($link['href'] ?? ''))) ?>" class="<?= $current_page === ($link['page'] ?? '') ? 'active' : '' ?>"><?= site_cms_h($link['label'] ?? '') ?></a>
            <?php endforeach; ?>
        </nav>
    </div>
</div>

<header class="header">
    <div class="container header-inner">
        <button type="button" class="mobile-nav-toggle" id="mobile-nav-toggle" aria-label="Deschide meniul" aria-expanded="false" aria-controls="mobile-nav">
            <span class="burger-line"></span>
            <span class="burger-line"></span>
            <span class="burger-line"></span>
        </button>

        <a class="logo" href="<?= besoiu_href('home') ?>">
            <?php
            require_once __DIR__ . '/besoiu-image.php';
            besoiu_render_logo_img('Besoiu Piese Auto', 'logo-img');
            ?>
        </a>

        <div class="search-main-wrap" id="besoiu-search-wrap" data-intel-search="<?= $intelSearchEnabled ? '1' : '0' ?>">
        <form class="search-main" id="_home-search-form" role="search" onsubmit="return false;">
            <input type="search" id="_home-product-name" placeholder="<?= site_cms_h($hdr['search_placeholder'] ?? '') ?>" aria-label="Caută piese" />
            <button type="button" id="_home-search-btn" data-search-submit><?= site_cms_h($hdr['search_button'] ?? 'CAUTĂ') ?></button>
        </form>
        </div>

        <a href="<?= site_cms_h($hdrPhoneHref) ?>" class="phone" aria-label="Sună la <?= site_cms_h($hdrPhone) ?>">
            <div class="ico"><img src="img/icons/12_telefon.svg" alt="" class="hdr-icon-lg" role="presentation" width="26" height="26"></div>
            <div><strong><?= site_cms_h($hdrPhone) ?></strong><span><?= site_cms_h($hdrPhoneLabel) ?></span></div>
        </a>

        <a href="<?= $shopAccountUser ? besoiu_href('cont') : besoiu_href('cont', ['view' => 'login']) ?>" class="account" aria-label="<?= $shopAccountUser ? 'Contul meu' : 'Autentificare / Cont' ?>">
            <div class="big"><img src="img/icons/13_cont_utilizator.svg" alt="" role="presentation" class="hdr-icon-lg" width="26" height="26"></div>
            <div>
                <strong><?= site_cms_h($hdr['account_title'] ?? 'Contul meu') ?></strong>
                <span><?= $shopAccountUser ? shop_auth_h(explode(' ', trim($shopAccountUser['name']))[0]) : site_cms_h($hdr['account_guest'] ?? 'Autentificare') ?></span>
            </div>
        </a>

        <a href="<?= besoiu_href('cart') ?>" class="cart" aria-label="Deschide coșul de cumpărături">
            <span class="cart-icon-wrap">
                <img src="img/icons/14_cos_cumparaturi.svg" alt="" class="cart-icon" width="24" height="24" role="presentation">
                <em class="badge-count cart-count" data-cart-count style="display:none">0</em>
            </span>
            <span class="cart-text">
                <strong><?= site_cms_h($hdr['cart_label'] ?? 'Coș') ?></strong>
            </span>
        </a>
    </div>
</header>

<div class="mobile-nav-overlay" id="mobile-nav-overlay" hidden></div>
<nav class="mobile-nav" id="mobile-nav" aria-label="Navigare principală" aria-hidden="true">
    <div class="mobile-nav-head">
        <strong>Meniu</strong>
        <button type="button" class="mobile-nav-close" id="mobile-nav-close" aria-label="Închide meniul">✕</button>
    </div>
    <ul class="mobile-nav-links">
        <?php foreach ($nav_links as $link): ?>
        <li>
            <a href="<?= site_cms_h(besoiu_normalize_href((string) ($link['href'] ?? ''))) ?>" class="<?= $current_page === ($link['page'] ?? '') ? 'active' : '' ?>"><?= site_cms_h($link['label'] ?? '') ?></a>
        </li>
        <?php endforeach; ?>
    </ul>
    <div class="mobile-nav-divider"></div>
    <ul class="mobile-nav-links mobile-nav-links--muted">
        <?php foreach ($nav_support as $link): ?>
        <li><a href="<?= site_cms_h(besoiu_normalize_href((string) ($link['href'] ?? ''))) ?>"><?= site_cms_h($link['label'] ?? '') ?></a></li>
        <?php endforeach; ?>
    </ul>
    <a href="<?= site_cms_h($hdrPhoneHref) ?>" class="mobile-nav-phone">
        <img src="img/icons/12_telefon.svg" alt="" width="20" height="20" role="presentation">
        <span><strong><?= site_cms_h($hdrPhone) ?></strong><small><?= site_cms_h($hdrPhoneLabel) ?></small></span>
    </a>
</nav>

<script src="<?= besoiu_asset_href('assets/js/mobile-nav.js') ?>" defer></script>
