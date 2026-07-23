<?php

declare(strict_types=1);

/** @return array<string, string> */
function besoiu_storefront_js_endpoints(): array
{
    return [
        'orders' => '/api/orders.php',
        'cart' => '/api/cart_endpoint.php',
        'coupon' => '/api/coupon_endpoint.php',
        'payment' => '/api/payment_endpoint.php',
        'cmsSave' => '/api/admin-website.php',
        'cmsMedia' => '/api/admin-cms-media.php',
        'messages' => '/api/messages.php',
        'tecdoc' => '/api/tecdoc_proxy.php',
        'categories' => '/api/api_categorii.php',
    ];
}

function besoiu_render_storefront_config_script(): void
{
    $endpoints = besoiu_storefront_js_endpoints();
    echo '<script>window.BESOIU_API=' . json_encode($endpoints, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';</script>' . "\n";
}
