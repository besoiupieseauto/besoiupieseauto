<?php
declare(strict_types=1);

if (!defined('BESOIU_SKIP_PRODUCT_GRID')) {
    define('BESOIU_SKIP_PRODUCT_GRID', true);
}
require_once __DIR__ . '/product.php';

if (!function_exists('besoiu_home_vitrina_limit')) {
    function besoiu_home_vitrina_limit(): int
    {
        return 10;
    }
}

if (!function_exists('besoiu_home_vitrina_products')) {
    /** @return array<int, array<string, mixed>> */
    function besoiu_home_vitrina_products(): array
    {
        require_once __DIR__ . '/tecdoc_stock.php';

        try {
            $pdo = tecdoc_db();
            tecdoc_ensure_vitrina_column($pdo);
            $products = tecdoc_list_vitrina_products($pdo, besoiu_home_vitrina_limit());
            if ($products !== []) {
                return $products;
            }
        } catch (Throwable $exception) {
            error_log('[home-vitrina] ' . $exception->getMessage());
        }

        require_once __DIR__ . '/scraper-home.php';

        return besoiu_scraper_products_for_vitrina(besoiu_home_vitrina_limit());
    }
}

if (!function_exists('besoiu_home_special_limit')) {
    function besoiu_home_special_limit(): int
    {
        return 8;
    }
}

if (!function_exists('besoiu_home_special_products')) {
    /** @return array<int, array<string, mixed>> */
    function besoiu_home_special_products(): array
    {
        require_once __DIR__ . '/tecdoc_stock.php';

        try {
            $pdo = tecdoc_db();
            tecdoc_ensure_special_column($pdo);
            return tecdoc_list_special_products($pdo, besoiu_home_special_limit());
        } catch (Throwable $exception) {
            error_log('[home-special] ' . $exception->getMessage());

            return [];
        }
    }
}

if (!function_exists('besoiu_vitrina_row_for_card')) {
    /** @param array<string, mixed> $row */
    function besoiu_vitrina_row_for_card(array $row): array
    {
        $image = trim((string) ($row['image'] ?? ''));
        $images = $image !== '' ? [$image] : [];

        return [
            'pName' => (string) ($row['name'] ?? $row['pName'] ?? 'Produs'),
            'pCode' => (string) ($row['code'] ?? $row['pCode'] ?? ''),
            'pBrand' => (string) ($row['brand'] ?? $row['pBrand'] ?? ''),
            'pPrice' => (string) ($row['price'] ?? $row['pPrice'] ?? ''),
            'price_numeric' => (string) ($row['price_numeric'] ?? ''),
            'price_old' => (string) ($row['price_old'] ?? $row['price_old_label'] ?? $row['pPriceOld'] ?? ''),
            'pCategory' => (string) ($row['category'] ?? $row['pCategory'] ?? ''),
            'pSubcategory' => (string) ($row['subcategory'] ?? $row['pSubcategory'] ?? ''),
            'pNote' => (string) ($row['note'] ?? $row['pNote'] ?? ''),
            'pImages' => $images !== [] ? json_encode($images, JSON_UNESCAPED_UNICODE) : (string) ($row['pImages'] ?? ''),
            'randomn_id' => (string) ($row['randomn_id'] ?? $row['id'] ?? ''),
            'pBadge' => (string) ($row['pBadge'] ?? ''),
            'pShipping' => (string) ($row['stock'] ?? $row['pShipping'] ?? ''),
            'pCar' => (string) ($row['pCar'] ?? ''),
            'pMarca' => (string) ($row['marca'] ?? $row['pMarca'] ?? ''),
        ];
    }
}

if (!function_exists('besoiu_vitrina_short_title')) {
    function besoiu_vitrina_short_title(string $name, int $maxLength = 52): string
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');
        if ($name === '') {
            return 'Produs';
        }

        if (mb_strlen($name) <= $maxLength) {
            return $name;
        }

        $short = mb_substr($name, 0, $maxLength);
        $lastSpace = mb_strrpos($short, ' ');
        if ($lastSpace !== false && $lastSpace > (int) ($maxLength * 0.5)) {
            $short = mb_substr($short, 0, $lastSpace);
        }

        return rtrim($short, " ,.;:-") . '…';
    }
}

if (!function_exists('besoiu_vitrina_reference_price_from_offer')) {
    function besoiu_vitrina_reference_price_from_offer(float $offerPrice, int $discountPercent): float
    {
        if ($offerPrice <= 0 || $discountPercent <= 0 || $discountPercent >= 100) {
            return $offerPrice;
        }

        $reference = $offerPrice / (1 - ($discountPercent / 100));
        if (besoiu_store_price_uses_integer_display()) {
            return (float) max((int) ceil($reference), (int) ceil($offerPrice) + 1);
        }

        return round($reference, 2);
    }
}

if (!function_exists('besoiu_vitrina_card_pricing')) {
    /** @param array<string, mixed> $product
     * @return array{new_price:float,new_label:string,old_price:?float,old_label:?string,discount_percent:?int}
     */
    function besoiu_vitrina_card_pricing(array $product): array
    {
        $newPrice = besoiu_catalog_price($product['pPrice'] ?? $product['price'] ?? $product['price_numeric'] ?? 0);
        $newLabel = besoiu_store_price_label($newPrice);

        if ($newPrice <= 0) {
            return [
                'new_price' => 0.0,
                'new_label' => 'La cerere',
                'old_price' => null,
                'old_label' => null,
                'discount_percent' => null,
            ];
        }

        foreach (['price_old', 'old_price', 'pPriceOld', 'pRetailPrice', 'compare_at_price'] as $key) {
            if (!isset($product[$key]) || trim((string) $product[$key]) === '') {
                continue;
            }

            $oldPrice = besoiu_catalog_price($product[$key]);
            if ($oldPrice > $newPrice) {
                $percent = (int) round((1 - ($newPrice / $oldPrice)) * 100);

                return [
                    'new_price' => $newPrice,
                    'new_label' => $newLabel,
                    'old_price' => $oldPrice,
                    'old_label' => besoiu_store_price_label($oldPrice),
                    'discount_percent' => max(1, min(99, $percent)),
                ];
            }
        }

        $discountPercent = 40;
        $oldPrice = besoiu_vitrina_reference_price_from_offer($newPrice, $discountPercent);

        return [
            'new_price' => $newPrice,
            'new_label' => $newLabel,
            'old_price' => $oldPrice,
            'old_label' => besoiu_store_price_label($oldPrice),
            'discount_percent' => $discountPercent,
        ];
    }
}

if (!function_exists('besoiu_vitrina_discount_badge_html')) {
    function besoiu_vitrina_discount_badge_html(?int $percent): string
    {
        if ($percent === null || $percent <= 0) {
            return '';
        }

        return '<div class="_product-card-badge"><span class="_product-badge _product-badge--discount">-'
            . besoiu_catalog_h((string) $percent) . '%</span></div>';
    }
}

if (!function_exists('besoiu_vitrina_card_price_html')) {
    /** @param array{new_price:float,new_label:string,old_price:?float,old_label:?string,discount_percent:?int} $pricing */
    function besoiu_vitrina_card_price_html(array $pricing): string
    {
        $oldLabel = trim((string) ($pricing['old_label'] ?? ''));
        $newLabel = trim((string) ($pricing['new_label'] ?? 'La cerere'));
        $oldPrice = $pricing['old_price'] ?? null;
        $newPrice = $pricing['new_price'] ?? 0.0;

        if ($oldLabel !== '' && $oldPrice !== null && $oldPrice > $newPrice) {
            return '<div class="_product-price _product-price--vitrina">'
                . '<span class="_product-price-old">' . besoiu_catalog_h($oldLabel) . '</span>'
                . '<span class="_product-price-new">' . besoiu_catalog_h($newLabel) . '</span>'
                . '</div>';
        }

        return '<div class="_product-price _product-price--vitrina"><span class="_product-price-new">'
            . besoiu_catalog_h($newLabel) . '</span></div>';
    }
}

if (!function_exists('besoiu_vitrina_enrich_public_product')) {
    /** @param array<string, mixed> $product @return array<string, mixed> */
    function besoiu_vitrina_enrich_public_product(array $product): array
    {
        $name = trim((string) ($product['name'] ?? $product['pName'] ?? 'Produs'));
        $pricing = besoiu_vitrina_card_pricing($product);

        $product['short_name'] = besoiu_vitrina_short_title($name);
        $product['price_old_numeric'] = $pricing['old_price'];
        $product['price_old_label'] = $pricing['old_label'];
        $product['discount_percent'] = $pricing['discount_percent'];

        return $product;
    }
}

if (!function_exists('besoiu_render_home_vitrina_panel')) {
    /**
     * Grilă vitrină homepage — imediat sub caruselul hero (admin → Produse selective).
     *
     * @param array<string, mixed> $home
     * @param array<int, array<string, mixed>> $products
     */
    function besoiu_render_home_vitrina_panel(array $home, array $products, int $count): void
    {
        $limit = besoiu_home_vitrina_limit();
        $panelClass = $count > 0 ? 'home-products-panel--has-vitrina' : 'home-products-panel--empty-vitrina';
        $devTools = function_exists('besoiu_dev_tools_enabled') && besoiu_dev_tools_enabled();
        ?>
<section class="_product-section section home-products-panel home-vitrina-under-hero <?= $panelClass ?>" id="home-vitrina-panel" aria-labelledby="home-vitrina-title">
    <div class="_product-container container">
        <?php if ($devTools): ?>
        <nav class="home-list-toolbar" aria-label="Acțiuni listă produse">
            <div class="home-list-toolbar__meta">
                <span class="home-list-toolbar__label">Pe vitrină:</span>
                <span id="_product-results-count" class="product-results-count"><?= (int) $count ?></span>
                <?php if ($count === 0): ?>
                <span class="home-vitrina-warn">— ulei, antigel, lichid frână etc. Bifează în admin → <strong>Produse selective</strong> (max. <?= (int) $limit ?>).</span>
                <?php endif; ?>
            </div>
            <div class="home-list-toolbar__actions">
                <a href="/catalog" class="home-list-catalog-link"><?= site_cms_h($home['products']['view_all'] ?? 'Vezi catalog') ?></a>
            </div>
        </nav>
        <?php else: ?>
        <span id="_product-results-count" class="product-results-count is-hidden" aria-hidden="true"><?= (int) $count ?></span>
        <?php endif; ?>

        <div class="products-head products-head--vitrina">
            <?php site_live_cms_tag('home', 'products.title_html', 'h2', (string) ($home['products']['title_html'] ?? ''), ['class' => 'section-title', 'id' => 'home-vitrina-title'], true); ?>
            <?php if (!$devTools): ?>
            <a href="/catalog" class="home-list-catalog-link products-head__catalog-link"><?php site_live_cms_tag('home', 'products.view_all', 'span', (string) ($home['products']['view_all'] ?? '')); ?></a>
            <?php endif; ?>
        </div>

        <div id="_loader-piese" class="loader-piese is-hidden">
            <div class="loader-piese__spinner"></div>
            <div class="loader-piese__text"><?php site_live_cms_tag('home', 'products.loading', 'span', (string) ($home['products']['loading'] ?? '')); ?></div>
        </div>

        <div class="_product-grid" id="_product-grid"
             data-home-vitrina="1"
             data-vitrina-limit="<?= (int) $limit ?>"<?= $devTools ? ' data-loc-page="index.php" data-card-image-selector="._product-card-image img" data-card-desc-selector="._product-card-desc" data-loc-modules="assets/js/home-tecdoc.js|assets/js/cart-admin.js|assets/css/product-cards.css|assets/css/home-critical.css|system/note-html.php|system/tecdoc_stock.php|system/home-vitrina-render.php"' : '' ?>>
            <?php foreach ($products as $homeVitrinaProduct): ?>
                <?php besoiu_render_home_vitrina_card($homeVitrinaProduct); ?>
            <?php endforeach; ?>
        </div>

        <?php if ($devTools): ?>
        <div id="_product-debug-status" class="product-debug-status is-hidden" hidden aria-hidden="true"></div>
        <?php endif; ?>

        <div id="_product-empty-state" class="product-empty-state<?= $count > 0 ? ' is-hidden' : '' ?>" data-browse-prompt="Momentan nu avem produse recomandate în această secțiune. Folosește filtrele de sus sau vezi tot catalogul.">
            <?php if ($count === 0): ?>
            Momentan nu avem produse recomandate aici. Folosește filtrele de sus sau <a href="/catalog">vezi catalogul complet</a>.
            <?php endif; ?>
        </div>
        <?php if ($devTools): ?>
        <details id="stock-loc-modules" class="stock-loc-modules" data-loc="pagini-module-descriere" data-task="product-desc-img" hidden>
            <summary id="stock-loc-modules-summary">Locul exact: module tehnice (doar dev)</summary>
            <dl id="stock-loc-modules-list">
                <dt>Pagină homepage</dt>
                <dd>index.php — #_product-grid · home-tecdoc.js (sub carusel hero)</dd>
            </dl>
        </details>
        <?php endif; ?>

        <div class="trust">
            <?php
            $whyPhone = trim((string) ($home['why']['phone'] ?? '0726 498 573'));
            $whyPhoneHref = function_exists('site_phone_resolve_href')
                ? site_phone_resolve_href($whyPhone, (string) ($home['why']['phone_href'] ?? ''))
                : '';
            if ($whyPhoneHref === '' && function_exists('site_phone_to_tel_href')) {
                $whyPhoneHref = site_phone_to_tel_href($whyPhone);
            }
            foreach (($home['products']['trust'] ?? []) as $ti => $trustItem):
                $trustSubtitle = trim((string) ($trustItem['subtitle'] ?? ''));
                $trustSubtitleHref = '';
                $explicitTrustHref = trim((string) ($trustItem['subtitle_href'] ?? ''));
                if ($explicitTrustHref !== '') {
                    $trustSubtitleHref = site_phone_resolve_href($trustSubtitle, $explicitTrustHref);
                } elseif ($trustSubtitle !== '' && $trustSubtitle === $whyPhone) {
                    $trustSubtitleHref = $whyPhoneHref;
                }
            ?>
            <div class="trust-item"><div class="ticon"><?php site_live_cms_image_tag('home', 'products.trust.' . $ti . '.icon', (string) ($trustItem['icon'] ?? ''), ['class' => 'ui-icon-28', 'alt' => '', 'role' => 'presentation', 'data-cms-variant' => 'icon']); ?></div><div><?php site_live_cms_tag('home', 'products.trust.' . $ti . '.title', 'b', (string) ($trustItem['title'] ?? '')); ?><span><?php if ($trustSubtitleHref !== ''): ?><a href="<?= site_cms_h($trustSubtitleHref) ?>" class="trust-phone-link"><?php site_live_cms_tag('home', 'products.trust.' . $ti . '.subtitle', 'span', $trustSubtitle); ?></a><?php else: ?><?php site_live_cms_tag('home', 'products.trust.' . $ti . '.subtitle', 'span', $trustSubtitle); ?><?php endif; ?></span></div></div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
        <?php
    }
}

if (!function_exists('besoiu_render_home_vitrina_card')) {
    /** @param array<string, mixed> $product */
    function besoiu_render_home_vitrina_card(array $product, string $cardContext = 'vitrina'): void
    {
        $product = besoiu_vitrina_row_for_card($product);
        $name = trim((string) ($product['pName'] ?? 'Produs fără nume'));
        $shortTitle = besoiu_vitrina_short_title($name);
        $pricing = besoiu_vitrina_card_pricing($product);
        $price = $pricing['new_price'];
        $category = besoiu_catalog_category($product);
        $image = besoiu_catalog_first_image($product);
        $productId = trim((string) ($product['randomn_id'] ?? ''));
        $code = trim((string) ($product['pCode'] ?? ''));
        $brand = trim((string) ($product['pBrand'] ?? ''));
        $marca = trim((string) ($product['pMarca'] ?? ''));
        $subcategory = trim((string) ($product['pSubcategory'] ?? ''));
        $badge = trim((string) ($product['pBadge'] ?? ''));
        $cardClass = 'home-grid-card' . ($cardContext === 'special' ? ' home-special-card' : ' home-vitrina-card');
        $isMinimalVitrina = $cardContext === 'vitrina';

        $specsSearch = $shortTitle;
        $specsJson = '[]';
        if (!$isMinimalVitrina) {
            $description = trim((string) ($product['pNote'] ?? ''));
            if ($description === '') {
                $description = trim(($brand ? $brand . ' - ' : '') . 'Piesă auto disponibilă în stoc.');
            }
            $isHtmlNote = besoiu_note_is_html($description);
            $specs = besoiu_product_specs_from_product($product);
            if ($specs === [] && $description !== '' && !$isHtmlNote) {
                $specs = [['label' => 'Descriere', 'value' => $description]];
            }
            $plainDescription = $isHtmlNote ? besoiu_note_plain_text($description) : $description;
            $specsSearch = implode(' ', array_map(static fn(array $spec): string => $spec['label'] . ' ' . $spec['value'], $specs));
            if ($plainDescription !== '') {
                $specsSearch = trim($specsSearch . ' ' . $plainDescription);
            }
            $specsJson = json_encode($specs, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '[]';
        }
        ?>
        <article class="_product-card <?= besoiu_catalog_h($cardClass) ?>"
                 <?= $cardContext === 'special' ? 'data-home-special="1"' : 'data-home-vitrina="1" data-recommended="1"' ?>
                 data-product-id="<?= besoiu_catalog_h($productId) ?>"
                 data-name="<?= besoiu_catalog_h($name) ?>"
                 data-oem="<?= besoiu_catalog_h($code) ?>"
                 data-vin=""
                 data-category="<?= besoiu_catalog_h($category) ?>"
                 data-subcategory="<?= besoiu_catalog_h($subcategory) ?>"
                 data-marca="<?= besoiu_catalog_h($marca) ?>"
                 data-brand="<?= besoiu_catalog_h($brand) ?>"
                 data-price="<?= besoiu_catalog_h((string) $price) ?>"
                 data-image="<?= besoiu_catalog_h($image) ?>"
                 data-badge="<?= besoiu_catalog_h($badge) ?>"
                 data-desc="<?= besoiu_catalog_h($specsSearch) ?>"
                 data-specs="<?= besoiu_catalog_h($specsJson) ?>">
            <?php if ($isMinimalVitrina): ?>
                <?= besoiu_vitrina_discount_badge_html($pricing['discount_percent']) ?>
                <div class="_product-card-image _product-card-image--clickable">
                    <img src="<?= besoiu_catalog_h(besoiu_catalog_css_url($image)) ?>" alt="<?= besoiu_catalog_h($shortTitle) ?>" loading="lazy" decoding="async">
                </div>
                <div class="_product-card-head">
                    <h3 class="_product-card-name"><?= besoiu_catalog_h($shortTitle) ?></h3>
                </div>
                <?= besoiu_vitrina_card_price_html($pricing) ?>
            <?php else: ?>
                <?php
                    $badgeToShow = $badge !== '' ? $badge : 'recomandat';
                    echo besoiu_product_badge_html($badgeToShow);
                ?>
                <div class="_product-card-image _product-card-image--clickable">
                    <img src="<?= besoiu_catalog_h(besoiu_catalog_css_url($image)) ?>" alt="<?= besoiu_catalog_h($name) ?>" loading="lazy" decoding="async">
                </div>
                <div class="_product-card-head">
                    <h3 class="_product-card-name"><?= besoiu_catalog_h($name) ?></h3>
                </div>
                <div class="_product-price"><?= besoiu_catalog_h($pricing['new_label']) ?></div>
                <?= besoiu_product_card_actions_html($productId) ?>
            <?php endif; ?>
        </article>
        <?php
    }
}
