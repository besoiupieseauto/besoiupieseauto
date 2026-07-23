<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once BESOIU_LEGACY . '/url.php';

$rootFile = dirname(__DIR__) . '/sitemap.xml';
$storageFile = BESOIU_SEO . '/sitemap.xml';
$maxAge = 86400;

foreach ([$rootFile, $storageFile] as $file) {
    if (is_file($file) && (time() - (int) filemtime($file)) < $maxAge) {
        header('Content-Type: application/xml; charset=utf-8');
        header('Cache-Control: public, max-age=3600');
        readfile($file);
        exit;
    }
}

require_once BESOIU_LEGACY . '/shop-db.php';
shop_db_load_env(BESOIU_CONFIG . '/.env');

$baseUrl = besoiu_site_base_url();
$generator = new Storefront\Core\Seo\SitemapGenerator(BESOIU_ROOT, rtrim($baseUrl, '/'));
$result = $generator->generateAll();

header('Content-Type: application/xml; charset=utf-8');
header('Cache-Control: public, max-age=3600');
readfile($result['index']);
