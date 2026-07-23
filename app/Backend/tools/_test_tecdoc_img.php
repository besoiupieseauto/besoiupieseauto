<?php
require 'F:/laragon/www/besoiupieseauto.ro/admin/bootstrap.php';
require_once BESOIU_LEGACY . '/shop-db.php';
require_once BESOIU_LEGACY . '/tecdoc_stock.php';
$r = tecdoc_find_image_payload_fast('255-102', 'ELSTOCK');
echo json_encode($r, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
echo 'api_unavailable=' . (function_exists('tecdoc_api_is_unavailable') && tecdoc_api_is_unavailable() ? 'yes' : 'no') . "\n";
