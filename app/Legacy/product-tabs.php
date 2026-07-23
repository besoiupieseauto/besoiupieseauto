<?php
declare(strict_types=1);

use Besoiu\Services\ProductDescriptionTabsService;

if (!function_exists('besoiu_product_tabs_build')) {
    /**
     * @param array<string, mixed> $product
     * @return list<array{id:string, label:string, nav_id:string, pane_id:string, content_html:string}>
     */
    function besoiu_product_tabs_build(array $product, string $descriptionHtml = ''): array
    {
        static $service = null;
        if ($service === null) {
            require_once BESOIU_BACKEND . '/vendor/autoload.php';
            $service = new ProductDescriptionTabsService();
        }

        return $service->buildForProduct($product, $descriptionHtml);
    }
}

if (!function_exists('besoiu_product_tabs_render')) {
    /**
     * @param array<string, mixed> $product
     */
    function besoiu_product_tabs_render(array $product, string $descriptionHtml = ''): void
    {
        $tabs = besoiu_product_tabs_build($product, $descriptionHtml);
        if ($tabs === []) {
            return;
        }

        echo '<ul class="nav nav-tabs" id="productTabs" role="tablist">';
        foreach ($tabs as $index => $tab) {
            $active = $index === 0 ? ' active' : '';
            $selected = $index === 0 ? 'true' : 'false';
            echo '<li class="nav-item" role="presentation">';
            echo '<button class="nav-link' . $active . '" id="' . htmlspecialchars($tab['nav_id'], ENT_QUOTES, 'UTF-8') . '"'
                . ' data-bs-toggle="tab" data-bs-target="#' . htmlspecialchars($tab['pane_id'], ENT_QUOTES, 'UTF-8') . '"'
                . ' type="button" role="tab" aria-controls="' . htmlspecialchars($tab['pane_id'], ENT_QUOTES, 'UTF-8') . '"'
                . ' aria-selected="' . $selected . '">';
            echo htmlspecialchars($tab['label'], ENT_QUOTES, 'UTF-8');
            echo '</button></li>';
        }
        echo '</ul>';

        echo '<div class="tab-content" id="productTabsContent">';
        foreach ($tabs as $index => $tab) {
            $active = $index === 0 ? ' show active' : '';
            echo '<div class="tab-pane fade' . $active . '" id="' . htmlspecialchars($tab['pane_id'], ENT_QUOTES, 'UTF-8') . '"'
                . ' role="tabpanel" aria-labelledby="' . htmlspecialchars($tab['nav_id'], ENT_QUOTES, 'UTF-8') . '">';
            if ($tab['id'] === 'description') {
                echo '<div class="product-desc-content" data-besoiu-desc-target="website">';
            }
            if ($tab['id'] === 'reviews') {
                include __DIR__ . '/_product-tab-reviews.php';
            } else {
                echo $tab['content_html'];
            }
            if ($tab['id'] === 'description') {
                echo '</div>';
            }
            echo '</div>';
        }
        echo '</div>';
    }
}
