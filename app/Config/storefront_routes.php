<?php

declare(strict_types=1);

/**
 * Rute storefront — path → modul → Controller
 * Format: [METHOD, PATH, module_id, ControllerClass, cmsPageSlug?]
 */
return [
    ['GET', '/', 'home', Storefront\Modules\Home\HomeController::class, 'home'],
    ['GET', '/catalog', 'catalog', Storefront\Modules\Catalog\CatalogController::class, 'catalog'],
    ['GET', '/produs', 'product', Storefront\Modules\Product\ProductController::class, 'product'],
    ['GET', '/cart', 'cart', Storefront\Modules\Cart\CartController::class, 'cart'],
    ['GET', '/cont', 'account', Storefront\Modules\Account\AccountController::class, 'cont'],
    ['GET', '/contact', 'static', Storefront\Modules\StaticPages\StaticPageController::class, 'contact'],
    ['GET', '/despre', 'static', Storefront\Modules\StaticPages\StaticPageController::class, 'about'],
    ['GET', '/blog', 'blog', Storefront\Modules\Blog\BlogController::class, 'blog'],
    ['GET', '/articol', 'blog', Storefront\Modules\Blog\BlogController::class, 'blog-articol'],
    ['GET', '/autentificare', 'account', Storefront\Modules\Account\AccountController::class, 'autentificare'],
    ['GET', '/inregistrare', 'account', Storefront\Modules\Account\AccountController::class, 'inregistrare'],
    ['GET', '/cum-comand', 'static', Storefront\Modules\StaticPages\StaticPageController::class, 'cum-comand'],
    ['GET', '/livrare-plata', 'static', Storefront\Modules\StaticPages\StaticPageController::class, 'livrare-plata'],
    ['GET', '/retur-garantie', 'static', Storefront\Modules\StaticPages\StaticPageController::class, 'retur-garantie'],
    ['GET', '/intrebari-frecvente', 'static', Storefront\Modules\StaticPages\StaticPageController::class, 'intrebari-frecvente'],
    ['GET', '/termeni-conditii', 'static', Storefront\Modules\StaticPages\StaticPageController::class, 'termeni-conditii'],
    ['GET', '/politica-confidentialitate', 'static', Storefront\Modules\StaticPages\StaticPageController::class, 'politica-confidentialitate'],
    ['GET', '/politica-cookies', 'static', Storefront\Modules\StaticPages\StaticPageController::class, 'politica-cookies'],
    ['GET', '/cariere', 'static', Storefront\Modules\StaticPages\StaticPageController::class, 'cariere'],
    ['GET', '/p', 'cms', Storefront\Modules\Cms\CmsPageController::class, 'cms'],
];
