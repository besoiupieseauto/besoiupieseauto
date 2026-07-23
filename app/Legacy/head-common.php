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
$besoiuStorefrontBase = function_exists('besoiu_storefront_base') ? besoiu_storefront_base() : '';
echo '<script>(function(){document.documentElement.classList.add('
    . json_encode($besoiuStorefrontHtmlClass, JSON_THROW_ON_ERROR)
    . ');})();</script>' . "\n";
echo '<script>window.BESOIU_STOREFRONT_BASE=' . json_encode($besoiuStorefrontBase, JSON_THROW_ON_ERROR) . ';</script>' . "\n";
$deferBase = !empty($besoiuHeadCommon['deferBaseJs']);
$baseJs = besoiu_asset_href('assets/js/storefront-base.js');
if ($deferBase) {
    echo '<script src="' . $baseJs . '" defer></script>' . "\n";
} else {
    echo '<script src="' . $baseJs . '"></script>' . "\n";
}
besoiu_link_stylesheet('assets/css/storefront-public.css', !empty($besoiuHeadCommon['asyncPublicCss']));

if (!empty($besoiuHeadCommon['layout'])) {
    besoiu_link_stylesheet('assets/css/site-layout.css', !empty($besoiuHeadCommon['layoutAsync']));
}
echo '<script src="' . besoiu_asset_href('assets/js/site-icons.js') . '" defer></script>' . "\n";
besoiu_link_stylesheet('assets/css/site-mobile.css', !empty($besoiuHeadCommon['mobileAsync']));
if (function_exists('besoiu_preview_head_script')) {
    echo besoiu_preview_head_script();
}
