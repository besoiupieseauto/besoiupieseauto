<?php

declare(strict_types=1);

namespace Storefront\Modules\Catalog;

use Storefront\Core\Bootstrap\StorefrontRequest;
use Storefront\Core\Repository\SiteContentRepository;
use Storefront\Core\Template\PageView;

final class CatalogService
{
    public function __construct(
        private readonly SiteContentRepository $content = new SiteContentRepository(),
        private readonly ProductRepository $products = new ProductRepository(),
    ) {
    }

    public function buildPageView(StorefrontRequest $request): PageView
    {
        $root = defined('BESOIU_ROOT') ? BESOIU_ROOT : dirname(__DIR__, 3);
        require_once BESOIU_LEGACY . '/site-defaults.php';

        $row = $this->content->findActiveBySlug('catalog');
        $hero = $this->content->decodeSections($row['sections_json'] ?? null)['hero']
            ?? site_defaults_blocks('catalog')['hero'] ?? [];

        return new PageView(
            templatePath: dirname(__DIR__) . '/templates/catalog.php',
            data: [
                'meta' => [
                    'title' => 'Catalog Piese Auto — Besoiu Piese Auto',
                    'description' => (string) ($row['meta_description'] ?? 'Catalog piese auto'),
                    'canonical' => besoiu_absolute_url('/catalog'),
                ],
                'hero' => $hero,
                'productCount' => $this->products->countActive(),
            ],
            assetProfile: 'shop',
            scripts: ['assets/js/catalog-page.js', 'assets/js/cart-admin.js'],
            styles: ['assets/css/catalog-page.css'],
            cmsPage: 'catalog',
        );
    }
}
