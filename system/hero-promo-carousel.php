<?php

declare(strict_types=1);



require_once __DIR__ . '/scraper-home.php';

require_once __DIR__ . '/home-vitrina-render.php';

if (!function_exists('besoiu_hero_promo_clean_badge')) {
    /** Elimină bullet-uri / „??” din badge-uri carousel (probleme encoding). */
    function besoiu_hero_promo_clean_badge(string $badge, string $fallback = 'În stoc'): string
    {
        $badge = trim($badge);
        $badge = preg_replace('/^[\?\?●•◦▪·\s]+/u', '', $badge) ?? $badge;
        $badge = preg_replace('/\?\?+/u', '', $badge) ?? $badge;
        $badge = trim($badge);

        return $badge !== '' ? $badge : $fallback;
    }
}

if (!function_exists('besoiu_hero_promo_slides')) {

    /**
     * Fallback carousel când nu există slide-uri CMS configurate manual.
     *
     * @param int $limit
     * @return array<int, array{id: string, title: string, price: string, badge: string, image: string, href: string, external: bool}>
     */
    function besoiu_hero_promo_slides(int $limit = 8): array

    {

        $slides = [];

        $seen = [];



        foreach (besoiu_scraper_catalog_products() as $product) {

            $title = trim((string) ($product['title'] ?? ''));

            if ($title === '') {

                continue;

            }

            $id = besoiu_scraper_product_id($product);

            if (isset($seen[$id])) {

                continue;

            }

            $seen[$id] = true;



            $image = trim((string) ($product['image'] ?? ''));

            $slides[] = [

                'id'       => $id,

                'title'    => $title,

                'price'    => besoiu_scraper_price_label((string) ($product['price'] ?? '')),

                'badge'    => 'Ofertă ePiesa',

                'image'    => $image !== '' ? $image : 'assets/images/products/1.jpg',

                'href'     => '/produs?id=' . rawurlencode($id),

                'external' => false,

            ];



            if (count($slides) >= $limit) {

                return $slides;

            }

        }



        foreach (besoiu_home_vitrina_products() as $row) {

            $product = besoiu_vitrina_row_for_card($row);

            $title = trim((string) ($product['pName'] ?? ''));

            if ($title === '') {

                continue;

            }

            $id = trim((string) ($product['randomn_id'] ?? ''));

            if ($id === '' || isset($seen[$id])) {

                continue;

            }

            $seen[$id] = true;



            $price = besoiu_catalog_price($product['pPrice'] ?? 0);

            $priceLabel = besoiu_store_price_label($price);



            $slides[] = [

                'id'       => $id,

                'title'    => $title,

                'price'    => $priceLabel,

                'badge'    => 'În stoc',

                'image'    => besoiu_catalog_first_image($product),

                'href'     => $id !== '' ? '/produs?id=' . rawurlencode($id) : '/catalog',

                'external' => false,

            ];



            if (count($slides) >= $limit) {

                break;

            }

        }



        return $slides;

    }

}



if (!function_exists('besoiu_hero_promo_fallback_slide')) {

    /** @param array<string, mixed> $home */

    function besoiu_hero_promo_fallback_slide(array $home): array

    {

        $popup = is_array($home['hero']['popup_product'] ?? null) ? $home['hero']['popup_product'] : [];

        $image = trim(site_live_cms_image_url('home', 'hero.popup_product.image', (string) ($popup['image'] ?? '')));
        if ($image === '') {
            $image = 'assets/images/products/1.jpg';
        }

        return [
            'id'       => 'cms-fallback',
            'title'    => trim((string) ($popup['title'] ?? 'Disc frână ventilat Brembo Max')),
            'price'    => trim((string) ($popup['price'] ?? '450 RON')),
            'badge'    => besoiu_hero_promo_clean_badge(trim((string) ($popup['stock'] ?? 'În stoc'))),
            'image'    => $image,
            'href'     => trim((string) ($popup['url'] ?? '/catalog')),
            'external' => false,
        ];

    }

}



if (!function_exists('besoiu_render_hero_promo_carousel')) {

    /**

     * @param array<int, array<string, mixed>> $slides

     */

    function besoiu_render_hero_promo_carousel(array $slides, int $intervalMs = 5000): void

    {

        if ($slides === []) {

            return;

        }



        $intervalMs = max(2500, min(20000, $intervalMs));

        $total = count($slides);

        ?>

        <div class="hero-promo-banner"
             id="hero-promo-carousel"
             data-interval="<?= (int) $intervalMs ?>"
             role="region"
             aria-label="Promoții produse speciale"
             aria-live="polite">
            <div class="hero-promo-banner-top">
                <span class="hero-promo-banner-label">Produse speciale</span>
                <?php if ($total > 1): ?>
                <span class="hero-promo-counter">01 / <?= str_pad((string) $total, 2, '0', STR_PAD_LEFT) ?></span>
                <?php endif; ?>
            </div>
            <div class="hero-promo-track">
            <?php foreach ($slides as $index => $slide): ?>
                <?php
                    $isActive = $index === 0;
                    $href = trim((string) ($slide['href'] ?? '#'));
                    $external = !empty($slide['external']);
                    $image = trim((string) ($slide['image'] ?? ''));
                    $title = trim((string) ($slide['title'] ?? 'Produs'));
                    $price = trim((string) ($slide['price'] ?? ''));
                    $badge = besoiu_hero_promo_clean_badge(trim((string) ($slide['badge'] ?? 'În stoc')));
                ?>
            <a class="hero-promo-slide<?= $isActive ? ' is-active' : '' ?>"
               href="<?= besoiu_catalog_h($href !== '' ? $href : '#') ?>"
               data-slide-index="<?= (int) $index ?>"
               <?= $external ? 'target="_blank" rel="noopener noreferrer"' : '' ?>
               <?= $isActive ? 'tabindex="0"' : 'tabindex="-1" aria-hidden="true"' ?>>
                <div class="hero-promo-slide-media">
                    <?php if ($image !== ''): ?>
                    <img src="<?= besoiu_catalog_h(besoiu_catalog_css_url($image)) ?>" alt="<?= besoiu_catalog_h($title) ?>" loading="<?= $index === 0 ? 'eager' : 'lazy' ?>" decoding="async" fetchpriority="<?= $index === 0 ? 'high' : 'auto' ?>">
                    <?php else: ?>
                    <span class="hero-promo-visual-fallback" aria-hidden="true"></span>
                    <?php endif; ?>
                </div>
                <div class="hero-promo-slide-copy">
                    <span class="hero-promo-badge"><?= besoiu_catalog_h($badge) ?></span>
                    <h3 class="hero-promo-title"><?= besoiu_catalog_h($title) ?></h3>
                    <p class="hero-promo-price"><?= besoiu_catalog_h($price !== '' ? $price : 'La cerere') ?></p>
                    <span class="hero-promo-cta">Vezi oferta</span>
                </div>
            </a>
            <?php endforeach; ?>
            </div>
            <?php if ($total > 1): ?>
            <div class="hero-promo-dots" aria-hidden="true">
                <?php for ($i = 0; $i < $total; $i++): ?>
                <button type="button" class="hero-promo-dot<?= $i === 0 ? ' is-active' : '' ?>" data-slide-dot="<?= $i ?>" aria-label="Slide <?= $i + 1 ?>"></button>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
        </div>

        <?php

    }

}


