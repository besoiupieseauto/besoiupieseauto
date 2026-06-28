<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/Scraper/ScraperModule.php';

if (!function_exists('besoiu_scraper_home_display_limit')) {

    function besoiu_scraper_home_display_limit(): int

    {

        return 60;

    }

}



if (!function_exists('besoiu_scraper_catalog_products')) {

    /** @return array<int, array<string, mixed>> */

    function besoiu_scraper_catalog_products(int $limit = 0, bool $withImages = true): array

    {

        try {

            return ScraperModule::instance()->epiesaListProductsLite(null, $limit, $withImages);

        } catch (Throwable $e) {

            error_log('[scraper-home] ' . $e->getMessage());



            return [];

        }

    }

}



if (!function_exists('besoiu_scraper_tab_icon')) {

    function besoiu_scraper_tab_icon(string $slug): string

    {

        $icons = [

            'toate'     => 'img/icons/16_meniu_categorii.svg',

            'uleiuri'   => 'img/icons/03_ulei_lichide.svg',

            'filtre'    => 'img/icons/02_filtre.svg',

            'frane'     => 'img/icons/01_frane.svg',

            'baterii'   => 'img/icons/06_electric.svg',

            'becuri'    => 'img/icons/06_electric.svg',

            'anvelope'  => 'img/icons/07_caroserie.svg',

        ];



        return $icons[$slug] ?? 'img/icons/22_cutie_produse.svg';

    }

}



if (!function_exists('besoiu_scraper_home_tabs')) {

    /** @return array<int, array{slug: string, label: string, count: int}> */

    function besoiu_scraper_home_tabs(): array

    {

        $mod = ScraperModule::instance();
        $counts = $mod->epiesaCategorySlugCounts();

        $total = (int) ($counts['toate'] ?? 0);



        $tabs = [

            ['slug' => 'toate', 'label' => 'Toate', 'count' => $total],

        ];



        foreach ($mod->epiesaCategoryPresets() as $preset) {

            $slug = $preset['slug'];

            $count = (int) ($counts[$slug] ?? 0);

            if ($count <= 0) {

                continue;

            }

            $tabs[] = [

                'slug'  => $slug,

                'label' => $preset['label'],

                'count' => $count,

            ];

        }



        foreach ($counts as $slug => $count) {

            if ($slug === 'toate' || isset($mod->epiesaCategoryPresets()[$slug])) {

                continue;

            }

            $count = (int) $count;

            if ($count <= 0) {

                continue;

            }

            $tabs[] = [

                'slug'  => $slug,

                'label' => $mod->epiesaCategoryLabel($slug),

                'count' => $count,

            ];

        }



        return $tabs;

    }

}



if (!function_exists('besoiu_scraper_product_id')) {

    /** @param array<string, mixed> $product */

    function besoiu_scraper_product_id(array $product): string

    {

        $path = trim((string) ($product['url_path'] ?? ''));

        if ($path !== '' && preg_match('/mstrnid-(\d+)/', $path, $matches)) {

            return 'epiesa_' . $matches[1];

        }



        $seed = $path !== '' ? $path : trim((string) ($product['url'] ?? ''));



        return 'epiesa_' . substr(md5($seed), 0, 12);

    }

}



if (!function_exists('besoiu_scraper_price_value')) {

    function besoiu_scraper_price_value(string $price): float

    {

        $normalized = preg_replace('/[^\d.,]/', '', $price) ?? '';

        $normalized = str_replace(',', '.', $normalized);

        $value = (float) $normalized;



        return $value > 0 ? $value : 0.0;

    }

}



if (!function_exists('besoiu_scraper_price_label')) {

    function besoiu_scraper_price_label(string $price): string

    {

        $value = besoiu_scraper_price_value($price);

        if ($value <= 0) {
            return 'La cerere';
        }

        if (function_exists('besoiu_store_price_label')) {
            return besoiu_store_price_label($value);
        }

        return number_format($value, 2, '.', '') . ' RON';

    }

}



if (!function_exists('besoiu_scraper_find_by_id')) {

    /** @return array<string, mixed>|null */

    function besoiu_scraper_find_by_id(string $id): ?array

    {

        $id = trim($id);

        if ($id === '') {

            return null;

        }



        foreach (besoiu_scraper_catalog_products(0, true) as $product) {

            if (besoiu_scraper_product_id($product) === $id) {

                return $product;

            }

        }



        return null;

    }

}



if (!function_exists('besoiu_scraper_as_page_product')) {

    /**

     * Mapare produs scanat → structură compatibilă product.php.

     *

     * @param array<string, mixed> $product

     * @return array<string, mixed>

     */

    function besoiu_scraper_as_page_product(array $product, string $id): array

    {

        $title = trim((string) ($product['title'] ?? 'Produs'));

        $rawPrice = trim((string) ($product['price'] ?? ''));

        $image = trim((string) ($product['image'] ?? ''));

        $category = trim((string) ($product['category_label'] ?? 'Piese auto'));

        $oem = '';

        if (preg_match('/mstrnid-(\d+)/', (string) ($product['url_path'] ?? ''), $matches)) {

            $oem = $matches[1];

        }



        $images = $image !== '' ? [$image] : [];

        $description = 'Produs disponibil în catalogul Besoiu Piese Auto. '

            . 'Pentru compatibilitate exactă, recomandăm verificarea după VIN sau cod OEM.';



        return [

            'randomn_id' => $id,

            'pName'      => $title,

            'pCode'      => $oem,

            'pBrand'     => '',

            'pCar'       => '',

            'pCategory'  => $category,

            'pPrice'     => besoiu_scraper_price_value($rawPrice),

            'pImages'    => json_encode($images, JSON_UNESCAPED_UNICODE),

            'pNote'      => $description,

            'status'     => '1',

            '_scraper'   => true,

        ];

    }

}



if (!function_exists('besoiu_scraper_is_consumable_product')) {

    /** @param array<string, mixed> $product */

    function besoiu_scraper_is_consumable_product(array $product): bool

    {

        $slug = strtolower(trim((string) ($product['category_slug'] ?? '')));

        $title = strtolower(trim((string) ($product['title'] ?? '')));

        $label = strtolower(trim((string) ($product['category_label'] ?? '')));



        if ($slug === 'uleiuri' || str_contains($label, 'ulei') || str_contains($label, 'adesiv')) {

            return true;

        }



        foreach (['ulei', 'adesiv', 'lichid', 'lubrif', 'antigel', 'vaselin'] as $needle) {

            if ($needle !== '' && str_contains($title, $needle)) {

                return true;

            }

        }



        return false;

    }

}



if (!function_exists('besoiu_scraper_products_for_vitrina')) {

    /**

     * Uleiuri / adesive din catalog scraper — fallback vitrină homepage.

     *

     * @return array<int, array<string, mixed>>

     */

    function besoiu_scraper_products_for_vitrina(int $limit = 8): array

    {

        $limit = max(1, min(8, $limit));

        $rows = [];



        foreach (besoiu_scraper_catalog_products(0, false) as $product) {

            if (!besoiu_scraper_is_consumable_product($product)) {

                continue;

            }



            $id = besoiu_scraper_product_id($product);

            $mapped = besoiu_scraper_as_page_product($product, $id);

            $image = trim((string) ($product['image'] ?? ''));

            if ($image === '') {

                $images = json_decode((string) ($mapped['pImages'] ?? '[]'), true);

                $image = is_array($images) && isset($images[0]) ? (string) $images[0] : '';

            }



            $rows[] = [

                'name' => (string) ($mapped['pName'] ?? ''),

                'code' => (string) ($mapped['pCode'] ?? ''),

                'brand' => (string) ($mapped['pBrand'] ?? ''),

                'price' => (string) ($mapped['pPrice'] ?? ''),

                'price_numeric' => besoiu_scraper_price_value((string) ($product['price'] ?? '')),

                'image' => $image,

                'randomn_id' => $id,

                'category' => (string) ($mapped['pCategory'] ?? ''),

                'subcategory' => '',

                'note' => (string) ($mapped['pNote'] ?? ''),

                'stock' => 'Disponibil',

            ];



            if (count($rows) >= $limit) {

                break;

            }

        }



        return $rows;

    }

}



if (!function_exists('besoiu_render_scraper_product_card')) {

    /** @param array<string, mixed> $product */

    function besoiu_render_scraper_product_card(array $product): void

    {

        $title = trim((string) ($product['title'] ?? 'Produs'));

        $rawPrice = trim((string) ($product['price'] ?? ''));

        $image = trim((string) ($product['image'] ?? ''));

        $url = trim((string) ($product['url'] ?? '#'));

        $slug = trim((string) ($product['category_slug'] ?? 'altele'));

        $category = trim((string) ($product['category_label'] ?? ''));

        $productId = besoiu_scraper_product_id($product);

        $priceValue = besoiu_scraper_price_value($rawPrice);

        $priceLabel = besoiu_scraper_price_label($rawPrice);

        $oem = '';

        if (preg_match('/mstrnid-(\d+)/', (string) ($product['url_path'] ?? ''), $oemMatch)) {

            $oem = $oemMatch[1];

        }

        ?>

        <article class="_product-card home-grid-card home-scraper-card"

                 data-scraper-product="1"

                 data-category="<?= besoiu_catalog_h($slug) ?>"

                 data-product-id="<?= besoiu_catalog_h($productId) ?>"

                 data-name="<?= besoiu_catalog_h($title) ?>"

                 data-oem="<?= besoiu_catalog_h($oem) ?>"

                 data-vin=""

                 data-category-label="<?= besoiu_catalog_h($category) ?>"

                 data-brand=""

                 data-price="<?= besoiu_catalog_h((string) $priceValue) ?>"

                 data-image="<?= besoiu_catalog_h($image) ?>"

                 data-external-url="<?= besoiu_catalog_h($url) ?>">

            <div class="_product-card-image _product-card-image--clickable">

                <?php if ($image !== ''): ?>

                <img src="<?= besoiu_catalog_h($image) ?>" alt="<?= besoiu_catalog_h($title) ?>" loading="lazy" decoding="async">

                <?php endif; ?>

            </div>

            <div class="_product-card-head">

                <h3 class="_product-card-name"><?= besoiu_catalog_h($title) ?></h3>

            </div>

            <div class="_product-price"><?= besoiu_catalog_h($priceLabel) ?></div>

            <?= besoiu_product_card_actions_html($productId, false) ?>

        </article>

        <?php

    }

}


