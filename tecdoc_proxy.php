<?php
declare(strict_types=1);

require_once __DIR__ . '/system/public-api-init.php';
require_once __DIR__ . '/system/storefront-context.php';
require_once __DIR__ . '/system/tecdoc_stock.php';
require_once __DIR__ . '/system/shop-order-guard.php';

header('Content-Type: application/json; charset=utf-8');

$action = (string)($_GET['action'] ?? '');
$rateLimitedActions = ['get_models', 'get_vehicles', 'get_parts', 'get_articles', 'decode_vin', 'search_stock', 'search_oem', 'vitrina', 'status'];
if (in_array($action, $rateLimitedActions, true) && !shop_order_rate_limit_check(120)) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Prea multe cereri. Incearca din nou.'], JSON_UNESCAPED_UNICODE);
    exit;
}

/** @param array<string, mixed> $payload */
function tecdoc_proxy_json(array $payload): void
{
    echo json_encode(
        besoiu_storefront_sanitize_api_payload($payload),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
}

$host = BESOiu_TECDOC_HOST;

try {
    switch ($action) {

        case 'get_models':
            $manuId = (int)($_GET['manuId'] ?? 0);
            $url = "https://$host/models/list/type-id/1/manufacturer-id/$manuId/lang-id/21/country-filter-id/63";
            echo tecdoc_cached_response($url);
            break;

        case 'get_vehicles':
            $modelId = (int)($_GET['modelId'] ?? 0);
            $url = "https://$host/types/type-id/1/vehicle-type-details/$modelId/lang-id/21/country-filter-id/63";
            echo tecdoc_cached_response($url);
            break;

        case 'get_parts':
            $carId = (int)($_GET['carId'] ?? 0);
            $url = "https://$host/category/type-id/1/products-groups-variant-3/$carId/lang-id/21";
            echo tecdoc_cached_response($url);
            break;

        /* get_articles — intersectează stoc BD cu articole TecDoc (lang-id din tecdoc_catalog_lang_id(), RO=21) */
        case 'get_articles':
            $carId  = (int)($_GET['carId']  ?? 0);
            $nodeId = (int)($_GET['nodeId'] ?? 0);
            $filters = [
                'category'    => (string)($_GET['category']    ?? ''),
                'subcategory' => (string)($_GET['subcategory'] ?? ''),
                'marca'       => (string)($_GET['marca']       ?? ''),
                'name'        => (string)($_GET['name']        ?? ''),
                'oem'         => (string)($_GET['oem']         ?? ''),
            ];

            if ($carId > 0 && $nodeId > 0) {
                tecdoc_proxy_json(tecdoc_vehicle_articles_in_stock($carId, $nodeId, $filters));
                break;
            }

            if (trim($filters['category']) !== '' || trim($filters['subcategory']) !== '') {
                $bdNodeId = trim($filters['subcategory']) !== '' ? 0 : $nodeId;
                tecdoc_proxy_json(tecdoc_bd_stock_search($filters, 80, $carId, $bdNodeId));
                break;
            }

            tecdoc_proxy_json(['success' => true, 'source' => 'bd', 'count' => 0, 'scanned' => 0, 'products' => []]);
            break;

        case 'decode_vin':
            $vin = (string) ($_GET['vin'] ?? '');
            tecdoc_proxy_json(tecdoc_decode_vin($vin));
            break;

        case 'search_stock':
            $filters = [
                'name'        => (string)($_GET['name']        ?? ''),
                'oem'         => (string)($_GET['oem']         ?? ''),
                'vin'         => (string)($_GET['vin']         ?? ''),
                'category'    => (string)($_GET['category']    ?? ''),
                'subcategory' => (string)($_GET['subcategory'] ?? ''),
                'marca'       => (string)($_GET['marca']       ?? ''),
                'car_id'      => (string)($_GET['car_id']      ?? ''),
                'node_id'     => (string)($_GET['node_id']     ?? ''),
                'price_min'   => (string)($_GET['price_min']   ?? ''),
                'price_max'   => (string)($_GET['price_max']   ?? ''),
            ];
            tecdoc_proxy_json(tecdoc_public_search($filters));
            break;

        case 'search_oem':
            $code = trim((string) ($_GET['code'] ?? $_GET['oem'] ?? ''));
            $filters = [
                'category'    => (string) ($_GET['category'] ?? ''),
                'subcategory' => (string) ($_GET['subcategory'] ?? ''),
                'marca'       => (string) ($_GET['marca'] ?? ''),
            ];
            $limit = max(1, min(120, (int) ($_GET['limit'] ?? 80)));
            tecdoc_proxy_json(tecdoc_search_oem_in_stock($code, $filters, $limit));
            break;

        case 'status':
            $automationPaused = false;
            $guardPath = __DIR__ . '/system/api_automation_guard.php';
            if (is_file($guardPath)) {
                require_once $guardPath;
                $automationPaused = besoiu_api_automation_paused();
            }
            echo json_encode(besoiu_storefront_sanitize_api_payload([
                'success' => true,
                'api_unavailable' => tecdoc_api_is_unavailable(),
                'automation_paused' => $automationPaused,
                'rate_limit_only' => !tecdoc_api_is_unavailable() && is_array(tecdoc_last_api_error())
                    && tecdoc_is_rate_limited(
                        (string)(tecdoc_last_api_error()['message'] ?? ''),
                        (int)((tecdoc_last_api_error()['context']['http_code'] ?? 0))
                    ),
                'cache' => tecdoc_db_cache_stats(),
                'last_error' => tecdoc_last_api_error(),
                'notice' => (tecdoc_api_is_unavailable() || (
                    !tecdoc_api_is_unavailable() && is_array(tecdoc_last_api_error())
                    && tecdoc_is_rate_limited(
                        (string)(tecdoc_last_api_error()['message'] ?? ''),
                        (int)((tecdoc_last_api_error()['context']['http_code'] ?? 0))
                    )
                )) ? besoiu_storefront_quota_notice() : '',
            ]), JSON_UNESCAPED_UNICODE);
            break;

        case 'test_cache':
            if (!besoiu_admin_storefront_context()) {
                http_response_code(403);
                tecdoc_proxy_json(['success' => false, 'message' => 'Acces interzis.']);
                break;
            }
            $sampleUrl = 'https://' . BESOiu_TECDOC_HOST . '/articles/article-id/123/lang-id/21';
            $testKey = 'proxy_test_' . date('YmdHis');
            $payload = json_encode([
                'test' => true,
                'key' => $testKey,
                'items' => [],
            ], JSON_UNESCAPED_UNICODE);
            $written = tecdoc_db_cache_set($sampleUrl, $payload, 3600);
            $readBack = tecdoc_db_cache_get($sampleUrl, 3600);
            $decoded = is_string($readBack) ? json_decode($readBack, true) : null;
            echo json_encode([
                'success' => $written && is_array($decoded) && ($decoded['key'] ?? '') === $testKey,
                'written' => $written,
                'read_ok' => is_array($decoded) && ($decoded['key'] ?? '') === $testKey,
                'cache' => tecdoc_db_cache_stats(),
            ], JSON_UNESCAPED_UNICODE);
            break;

        case 'vitrina':
            $vitrinaLimit = max(1, min(10, (int) ($_GET['limit'] ?? 10)));
            tecdoc_proxy_json(tecdoc_vitrina_products_payload($vitrinaLimit));
            break;

        case 'health':
            if (!besoiu_admin_storefront_context()) {
                http_response_code(403);
                tecdoc_proxy_json(['success' => false, 'message' => 'Acces interzis.']);
                break;
            }
            tecdoc_clear_api_error();
            $key = tecdoc_rapidapi_key();
            $testUrl = "https://$host/models/list/type-id/1/manufacturer-id/5/lang-id/21/country-filter-id/63";
            $response = tecdoc_http_get($testUrl);
            $body = (string)($response['body'] ?? '');
            $decoded = json_decode($body, true);
            $message = is_array($decoded) ? (string)($decoded['message'] ?? '') : '';
            echo json_encode([
                'success' => true,
                'key_suffix' => substr($key, -8),
                'key_from_env' => tecdoc_rapidapi_is_user_key(),
                'http_code' => (int)($response['http_code'] ?? 0),
                'api_message' => $message,
                'quota_flag' => is_file(tecdoc_quota_flag_path()),
                'cache' => tecdoc_db_cache_stats(),
            ], JSON_UNESCAPED_UNICODE);
            break;

        default:
            tecdoc_proxy_json(['success' => false, 'message' => 'Actiune invalida']);
            break;
    }
} catch (Throwable $e) {
    http_response_code(500);
    tecdoc_proxy_json([
        'success' => false,
        'message' => besoiu_admin_storefront_context()
            ? $e->getMessage()
            : 'Operațiunea nu a putut fi finalizată. Încearcă din nou.',
    ]);
}