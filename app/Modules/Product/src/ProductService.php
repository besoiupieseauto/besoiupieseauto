<?php

declare(strict_types=1);

namespace Storefront\Modules\Product;

use Storefront\Core\Bootstrap\StorefrontRequest;
use Storefront\Core\Template\PageView;

final class ProductService
{
    public function __construct(
        private readonly ProductRepository $products = new ProductRepository(),
    ) {
    }

    public function buildPageView(StorefrontRequest $request): PageView
    {
        $id = (string) ($request->query['id'] ?? '');
        $product = $id !== '' ? $this->products->findByPublicId($id) : null;

        return new PageView(
            templatePath: dirname(__DIR__) . '/templates/product.php',
            data: [
                'meta' => [
                    'title' => $product ? (string) ($product['pName'] ?? 'Produs') : 'Produs',
                    'description' => 'Detaliu produs piese auto',
                    'canonical' => besoiu_absolute_url('/produs?id=' . rawurlencode($id)),
                ],
                'product' => $product,
            ],
            assetProfile: 'product',
            scripts: ['assets/js/product-carousel.js', 'assets/js/cart-admin.js'],
            styles: ['assets/css/product-page.css', 'assets/css/product-cards.css'],
            cmsPage: 'product',
        );
    }
}
