<?php

declare(strict_types=1);

error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
ini_set('display_errors', '0');

require_once __DIR__ . '/system/public-api-init.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/admin/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/admin');
$dotenv->load();

$config = require __DIR__ . '/admin/config/config.php';
Config\Database::getInstance(
    $config['db_host'],
    $config['db_name'],
    $config['db_user'],
    $config['db_pass']
);

use Evasystem\Controllers\Produse\ProductFacetsService;

$facetsService = new ProductFacetsService();
$action = $_GET['action'] ?? 'popup';

try {
    switch ($action) {
        case 'popup':
            $data = $facetsService->getForPopup();
            echo json_encode(['success' => true, 'categories' => $data], JSON_UNESCAPED_UNICODE);
            break;

        case 'facets':
            echo json_encode(['success' => true] + $facetsService->getAll(), JSON_UNESCAPED_UNICODE);
            break;

        case 'tree':
            $data = $facetsService->getAll();
            $tree = [];
            foreach ($data['categories'] as $category) {
                $children = array_values(array_filter(
                    $data['subcategories'],
                    static fn(array $sub): bool => strcasecmp((string) ($sub['category'] ?? ''), (string) ($category['label'] ?? '')) === 0
                ));
                $node = [
                    'id' => 0,
                    'slug' => $category['slug'],
                    'label' => $category['label'],
                    'icon' => $category['icon'] ?? '',
                    'type' => 'categorie',
                    'count' => $category['count'] ?? 0,
                ];
                if ($children !== []) {
                    $node['children'] = array_map(static function (array $child): array {
                        return [
                            'id' => 0,
                            'slug' => $child['slug'],
                            'label' => $child['label'],
                            'type' => 'subcategorie',
                            'count' => $child['count'] ?? 0,
                        ];
                    }, $children);
                }
                $tree[] = $node;
            }
            echo json_encode(['success' => true, 'tree' => $tree], JSON_UNESCAPED_UNICODE);
            break;

        case 'all':
            $data = $facetsService->getCategories();
            echo json_encode(['success' => true, 'categories' => $data], JSON_UNESCAPED_UNICODE);
            break;

        case 'marci':
            $data = $facetsService->getMarci();
            echo json_encode(['success' => true, 'marci' => $data], JSON_UNESCAPED_UNICODE);
            break;

        case 'modele':
            $data = $facetsService->getModele();
            echo json_encode(['success' => true, 'modele' => $data], JSON_UNESCAPED_UNICODE);
            break;

        case 'subcategorii':
            $category = trim((string) ($_GET['category'] ?? ''));
            $data = $facetsService->getSubcategories($category !== '' ? $category : null);
            echo json_encode(['success' => true, 'subcategorii' => $data], JSON_UNESCAPED_UNICODE);
            break;

        case 'brands':
            $data = $facetsService->getBrands();
            echo json_encode(['success' => true, 'brands' => $data], JSON_UNESCAPED_UNICODE);
            break;

        case 'insights':
            require_once __DIR__ . '/system/search_logs.php';
            require_once __DIR__ . '/system/tecdoc_stock.php';
            $limit = max(1, min(12, (int) ($_GET['limit'] ?? 8)));
            $days = max(7, min(365, (int) ($_GET['days'] ?? 90)));
            $insights = search_logs_storefront_insights(tecdoc_db(), $limit, $days);
            echo json_encode(['success' => true, 'insights' => $insights], JSON_UNESCAPED_UNICODE);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Acțiune necunoscută.']);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
