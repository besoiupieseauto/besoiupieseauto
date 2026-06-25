<?php
/**
 * Layout + site-icons + mobile CSS (apelat din besoiu_render_styles)
 * Înainte de include: $besoiuHeadCommon = ['layout' => bool, 'mobileAsync' => bool]
 */
$besoiuHeadCommon = $besoiuHeadCommon ?? ['layout' => true, 'mobileAsync' => false];

if (!function_exists('besoiu_storefront_html_class')) {
    require_once __DIR__ . '/storefront-context.php';
}
$besoiuStorefrontHtmlClass = besoiu_storefront_html_class();
echo '<script>(function(){document.documentElement.classList.add('
    . json_encode($besoiuStorefrontHtmlClass, JSON_THROW_ON_ERROR)
    . ');})();</script>' . "\n";
besoiu_link_stylesheet('assets/css/storefront-public.css', false);

if (!empty($besoiuHeadCommon['layout'])) {
    besoiu_link_stylesheet('assets/css/site-layout.css', false);
}
echo '<script src="' . besoiu_asset_href('assets/js/site-icons.js') . '" defer></script>' . "\n";
besoiu_link_stylesheet('assets/css/site-mobile.css', !empty($besoiuHeadCommon['mobileAsync']));
if (function_exists('besoiu_preview_head_script')) {
    echo besoiu_preview_head_script();
}
