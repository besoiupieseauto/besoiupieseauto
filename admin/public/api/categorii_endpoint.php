<?php
declare(strict_types=1);

require_once __DIR__ . '/_autoload.php';

use Besoiu\Controllers\Categorii\Categorii;
use Besoiu\Core\Bootstrap\ApiBootstrap;
use Besoiu\Core\Crud\CrudModuleFactory;
use Besoiu\Services\CategoriiService;

ApiBootstrap::bootJsonApi();
ApiBootstrap::sendCorsHeaders('GET, POST, OPTIONS', 'Content-Type, Authorization');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$service = new CategoriiService();
$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

try {
    if ($method === 'POST') {
        ApiBootstrap::requireAuthenticatedSession();

        $raw = file_get_contents('php://input') ?: '';
        $payload = json_decode($raw, true);
        if (!is_array($payload)) {
            $payload = $_POST;
        }

        $action = trim((string) ($payload['action'] ?? $payload['type_product'] ?? ''));
        if ($action === '') {
            ApiBootstrap::json(['success' => false, 'message' => 'Lipsește action sau type_product.'], 422);
        }

        $longActions = ['sync_tecdoc_modele', 'import_besoiu_tree'];
        ApiBootstrap::beginBoundedJsonWork(in_array($action, $longActions, true) ? 600 : 120);

        $controller = new Categorii();

        $response = match ($action) {
            'add' => $controller->add($payload),
            'edit' => $controller->edit($payload),
            'delete' => $controller->delete($payload),
            'toggle' => $controller->toggleActive($payload),
            'import_defaults' => $controller->importDefaults(),
            'backfill_icons' => $controller->backfillIcons(),
            'match_category' => $controller->matchCategory($payload),
            'ollama_category_status' => [
                'success' => true,
                'status' => $service->categoryMatchOllamaStatus(),
            ],
            'pieseauto_stats' => [
                'success' => true,
                'stats' => $service->getPieseAutoCatalogStats(),
            ],
            'import_pieseauto' => $controller->importPieseAuto($payload),
            'parse_pieseauto_html' => $controller->parsePieseAutoHtml($payload),
            'sync_taxonomy' => $controller->syncTaxonomy(),
            'preview_besoiu_tree' => $controller->previewBesoiuTree($payload),
            'import_besoiu_tree' => $controller->importBesoiuTree($payload),
            'purge_alternate_catalog' => $controller->purgeAlternateCatalog(),
            'sync_tecdoc_marci' => $controller->syncTecdocMarci($payload),
            'preview_tecdoc_marci' => $controller->previewTecdocMarci($payload),
            'sync_tecdoc_modele' => $controller->syncTecdocModele($payload),
            'preview_tecdoc_modele' => $controller->previewTecdocModele($payload),
            'upload_besoiu_excel' => $controller->uploadBesoiuExcel($_FILES),
            default => CrudModuleFactory::handleExtendedAction('Categorii', $action, $payload)
                ?? ['success' => false, 'message' => 'Acțiune necunoscută: ' . $action],
        };

        if (!is_array($response)) {
            ApiBootstrap::json(['success' => false, 'message' => 'Răspuns invalid.'], 500);
        }

        ApiBootstrap::json($response, !empty($response['success']) ? 200 : 422);
    }

    $action = trim((string) ($_GET['action'] ?? 'popup'));

    switch ($action) {
        case 'popup':
            $data = $service->getForPopup();
            ApiBootstrap::json(['success' => true, 'categories' => $data]);

        case 'tree':
            $data = $service->getTree();
            ApiBootstrap::json(['success' => true, 'tree' => $data]);

        case 'marci':
            $data = $service->getMarci();
            ApiBootstrap::json(['success' => true, 'marci' => $data]);

        case 'by_type':
            $type = $_GET['type'] ?? 'categorie';
            $data = $service->getByType($type);
            ApiBootstrap::json(['success' => true, 'items' => $data]);

        case 'children':
            $parentId = (int) ($_GET['parent_id'] ?? 0);
            $data = $service->getChildren($parentId);
            ApiBootstrap::json(['success' => true, 'children' => $data]);

        case 'all':
            $data = $service->getActive();
            ApiBootstrap::json(['success' => true, 'categories' => $data]);

        case 'upload_icon':
            ApiBootstrap::requireAuthenticatedSession();
            if (empty($_FILES['icon']) || $_FILES['icon']['error'] !== UPLOAD_ERR_OK) {
                ApiBootstrap::json(['success' => false, 'message' => 'Niciun fișier trimis sau eroare upload.']);
            }

            $file = $_FILES['icon'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowedExt = ['svg', 'png', 'jpg', 'jpeg', 'webp'];

            if (!in_array($ext, $allowedExt, true)) {
                ApiBootstrap::json(['success' => false, 'message' => 'Extensie nepermisă: ' . $ext]);
            }

            $uploadDir = __DIR__ . '/../uploads/categorii/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0775, true);
            }

            $safeName = preg_replace('/[^a-z0-9_\-.]/', '_', strtolower($file['name']));
            $uniqueName = time() . '_' . $safeName;
            $targetPath = $uploadDir . $uniqueName;

            if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                $relativePath = 'admin/public/uploads/categorii/' . $uniqueName;
                ApiBootstrap::json(['success' => true, 'path' => $relativePath]);
            }

            ApiBootstrap::json(['success' => false, 'message' => 'Nu s-a putut salva fișierul.']);

        default:
            ApiBootstrap::json(['success' => false, 'message' => 'Acțiune necunoscută.'], 404);
    }
} catch (\Throwable $e) {
    ApiBootstrap::respondInternalError('categorii_endpoint', $e);
}
