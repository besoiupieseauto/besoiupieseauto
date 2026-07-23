<?php

declare(strict_types=1);

define('BESOIU_ROOT', dirname(__DIR__, 2));

require BESOIU_ROOT . '/app/bootstrap.php';
require BESOIU_LEGACY . '/shop-db.php';
shop_db_load_env(BESOIU_CONFIG . '/.env');
require BESOIU_LEGACY . '/url.php';

$baseUrl = besoiu_site_base_url();
if (str_contains($baseUrl, 'localhost') || $baseUrl === 'https://besoiupieseauto.ro') {
    $baseUrl = getenv('APP_URL') ?: 'http://besoiupieseauto.ro.test';
}

$generator = new Storefront\Core\Seo\StaticHtmlGenerator(
    BESOIU_ROOT,
    BESOIU_SEO,
    rtrim($baseUrl, '/'),
);

$count = $generator->generateAll();

echo "Generat {$count} pagini SEO statice în " . BESOIU_SEO . "\n";
if (is_file(BESOIU_ROOT . '/sitemap.xml')) {
    echo "Sitemap index: " . BESOIU_ROOT . "/sitemap.xml\n";
}
if (is_file(BESOIU_SEO . '/sitemap-pages.xml')) {
    echo "Sitemap pagini: " . BESOIU_SEO . "/sitemap-pages.xml\n";
}
if (is_file(BESOIU_SEO . '/sitemap-products.xml')) {
    echo "Sitemap produse: " . BESOIU_SEO . "/sitemap-products.xml\n";
}
