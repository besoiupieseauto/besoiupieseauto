<?php
require_once __DIR__ . '/page-init.php';
require_once __DIR__ . '/site-content.php';

$shopAccountUser = shop_auth_session_user();
$global = site_content_blocks('global');

$current_page = basename($_SERVER['PHP_SELF']);

$nav_links = $global['nav'] ?? site_defaults_blocks('global')['nav'];
$nav_support = $global['nav_support'] ?? site_defaults_blocks('global')['nav_support'];
$hdr = $global['header'] ?? [];
$topbar = $global['topbar'] ?? [];
$hdrPhone = trim((string) ($hdr['phone'] ?? '0726 498 573'));
$hdrPhoneHref = site_phone_resolve_href($hdrPhone, trim((string) ($hdr['phone_href'] ?? '')));
if ($hdrPhoneHref === '') {
    $hdrPhoneHref = site_phone_to_tel_href('0726498573');
}
$hdrPhoneLabel = trim((string) ($hdr['phone_label'] ?? 'Sună acum'));
if ($hdrPhoneLabel === '' || strcasecmp($hdrPhoneLabel, $hdrPhone) === 0) {
    $hdrPhoneLabel = 'Sună acum';
}
?>

<div class="topbar">
    <div class="container">
        <div class="top-left">
            <?php foreach ($topbar as $ti => $topItem): ?>
            <div class="top-item"><?php if (function_exists('site_live_cms_image_tag')): ?><?php site_live_cms_image_tag('global', 'topbar.' . $ti . '.icon', (string) ($topItem['icon'] ?? ''), ['class' => 'hdr-icon', 'alt' => '', 'role' => 'presentation', 'data-cms-variant' => 'icon']); ?><?php else: ?><img src="<?= site_cms_h($topItem['icon'] ?? '') ?>" alt="" class="hdr-icon" role="presentation"><?php endif; ?> <?= site_cms_h($topItem['text'] ?? '') ?></div>
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

        <a class="logo" href="/">
            <?php if (function_exists('site_live_cms_image_tag')): ?>
                <?php site_live_cms_image_tag('global', 'header.logo', 'img/logo.png', ['class' => 'logo-img', 'alt' => 'Besoiu Piese Auto', 'data-cms-variant' => 'logo']); ?>
            <?php else: ?>
                <img src="img/logo.png" alt="Besoiu Piese Auto" class="logo-img">
            <?php endif; ?>
        </a>

        <form class="search-main" id="_home-search-form" role="search" onsubmit="return false;">
            <input type="search" id="_home-product-name" placeholder="<?= site_cms_h($hdr['search_placeholder'] ?? '') ?>" aria-label="Caută piese" />
            <button type="button" id="_home-search-btn"><?= site_cms_h($hdr['search_button'] ?? 'CAUTĂ') ?></button>
        </form>

        <a href="<?= site_cms_h($hdrPhoneHref) ?>" class="phone" aria-label="Sună la <?= site_cms_h($hdrPhone) ?>">
            <div class="ico"><img src="img/icons/12_telefon.svg" alt="" class="hdr-icon-lg" role="presentation"></div>
            <div><strong><?= site_cms_h($hdrPhone) ?></strong><span><?= site_cms_h($hdrPhoneLabel) ?></span></div>
        </a>

        <a href="<?= $shopAccountUser ? '/cont' : '/cont?view=login' ?>" class="account">
            <div class="big"><img src="img/icons/13_cont_utilizator.svg" alt="Cont" class="hdr-icon-lg"></div>
            <div>
                <strong><?= site_cms_h($hdr['account_title'] ?? 'Contul meu') ?></strong>
                <span><?= $shopAccountUser ? shop_auth_h(explode(' ', trim($shopAccountUser['name']))[0]) : site_cms_h($hdr['account_guest'] ?? 'Autentificare') ?></span>
            </div>
        </a>

        <a href="/cart" class="cart" aria-label="Coș de cumpărături">
            <span class="cart-icon-wrap">
                <img src="img/icons/14_cos_cumparaturi.svg" alt="" class="cart-icon" width="26" height="26" role="presentation">
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
