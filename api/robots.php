<?php

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once BESOIU_LEGACY . '/shop-auth.php';
shop_auth_load_env();
require_once BESOIU_LEGACY . '/preview-gate.php';
require_once BESOIU_LEGACY . '/besoiu-seo.php';
require_once BESOIU_LEGACY . '/url.php';

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: public, max-age=3600');
header('X-Content-Type-Options: nosniff');

$baseUrl = besoiu_site_base_url();

if (besoiu_seo_indexing_blocked()) {
    echo "User-agent: *\n";
    echo "Disallow: /\n";
    exit;
}

echo "User-agent: *\n";
echo "Allow: /\n";
echo "Disallow: /admin/\n";
echo "Disallow: /app/\n";
echo "Disallow: /api/\n";
echo "Disallow: /cart\n";
echo "Disallow: /cont\n";
echo "Disallow: /seo/\n";
echo "\n";
echo 'Sitemap: ' . rtrim($baseUrl, '/') . "/sitemap.xml\n";
