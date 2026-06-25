<?php

declare(strict_types=1);

require_once __DIR__ . '/_autoload.php';

use Evasystem\Controllers\Categorii\CategoriiService;
use Evasystem\Core\Bootstrap\ApiBootstrap;

ApiBootstrap::bootJsonApi();
ApiBootstrap::sendCorsHeaders('GET, POST, OPTIONS', 'Content-Type, Authorization');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$service = new CategoriiService();

$action = $_GET['action'] ?? 'popup';

try {
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
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                ApiBootstrap::json(['success' => false, 'message' => 'POST required.']);
            }
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
            ApiBootstrap::json(['success' => false, 'message' => 'Acțiune necunoscută.']);
    }
} catch (\Throwable $e) {
    ApiBootstrap::respondInternalError('categorii_endpoint', $e);
}
