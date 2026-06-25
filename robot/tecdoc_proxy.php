<?php
/**
 * robot/tecdoc_proxy.php
 *
 * Portat 1:1 din C:\laragon\www\aibotpiese.online\tecdoc_proxy.php
 * Modificari: rapidApiKey din .env, cache_tecdoc/ ramane la fel.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth_guard.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';
$rapidApiKey  = (string) env('RAPIDAPI_AUTOPARTS_KEY', '');
$rapidApiHost = "auto-parts-catalog.p.rapidapi.com";

$cacheDir = __DIR__ . '/cache_tecdoc/';
if (!is_dir($cacheDir)) {
    mkdir($cacheDir, 0777, true);
}

function get_cached_response($url, $apiKey, $apiHost, $ttl = 86400) {
    global $cacheDir;

    $cacheFile = $cacheDir . md5($url) . '.json';

    if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $ttl)) {
        return file_get_contents($cacheFile);
    }

    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => [
            "x-rapidapi-host: $apiHost",
            "x-rapidapi-key: $apiKey"
        ],
    ]);

    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);

    if ($err) {
        return json_encode(["error" => "cURL Error: " . $err]);
    }

    $decoded = json_decode($response, true);
    if (isset($decoded['error']) && $decoded['error'] === 'rate_limit_exceeded') {
        return $response;
    }

    file_put_contents($cacheFile, $response);

    return $response;
}

switch ($action) {
    case 'get_models':
        $manuId = (int)$_GET['manuId'];
        $url = "https://$rapidApiHost/models/list/type-id/1/manufacturer-id/$manuId/lang-id/21/country-filter-id/63";
        echo get_cached_response($url, $rapidApiKey, $rapidApiHost);
        break;

    case 'get_vehicles':
        $vehicleId = (int)$_GET['modelId'];
        $url = "https://$rapidApiHost/types/type-id/1/vehicle-type-details/$vehicleId/lang-id/21/country-filter-id/63";
        echo get_cached_response($url, $rapidApiKey, $rapidApiHost);
        break;

    case 'get_parts':
        $carId = (int)$_GET['carId'];
        $url = "https://$rapidApiHost/category/type-id/1/products-groups-variant-3/$carId/lang-id/21";
        echo get_cached_response($url, $rapidApiKey, $rapidApiHost);
        break;

    case 'get_articles':
        $carId = (int)$_GET['carId'];
        $nodeId = (int)$_GET['nodeId'];
        $langId = function_exists('tecdoc_catalog_lang_id') ? tecdoc_catalog_lang_id() : 21;
        $url = "https://$rapidApiHost/articles/list/type-id/1/vehicle-id/$carId/category-id/$nodeId/lang-id/$langId";

        $response = get_cached_response($url, $rapidApiKey, $rapidApiHost);
        $data = json_decode($response, true);

        if (isset($data['error']) && $data['error'] === 'rate_limit_exceeded') {
            echo $response;
            exit;
        }

        $formatted = [];
        $items = $data['articles'] ?? $data ?? [];

        foreach ($items as $art) {
            $formatted[] = [
                "brandName" => $art['supplierName'] ?? $art['brand'] ?? 'N/A',
                "articleName" => $art['articleProductName'] ?? $art['articleName'] ?? 'Piesă auto',
                "articleNumber" => $art['articleNo'] ?? $art['articleNumber'] ?? 'N/A',
                "img" => $art['s3image'] ?? $art['images'][0]['url'] ?? null
            ];
        }
        echo json_encode($formatted);
        break;
}
