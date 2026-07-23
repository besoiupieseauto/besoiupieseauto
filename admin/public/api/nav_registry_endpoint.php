<?php
declare(strict_types=1);

require_once __DIR__ . '/_autoload.php';

use Besoiu\Core\Auth\AdminPermissionCatalog;
use Besoiu\Core\Bootstrap\ApiBootstrap;
use Besoiu\Modules\Nav\NavHubService;
use Besoiu\Services\AdminNavRegistryService;

ApiBootstrap::bootJsonApi();

try {
    ApiBootstrap::requireAuthenticatedSession();
    $sessionUser = [
        'role' => (string) ($_SESSION['role'] ?? ''),
        'permissions' => $_SESSION['admin_permissions'] ?? null,
    ];

    $role = (string) ($sessionUser['role'] ?? '');
    $perms = AdminPermissionCatalog::normalizePermissions($sessionUser['permissions'] ?? null, $role);
    $canNav = $role === 'super_ambassador' || $role === 'admin'
        || AdminPermissionCatalog::canManageUsers($role, $perms);
    if (!$canNav) {
        ApiBootstrap::json(['success' => false, 'message' => 'Nu ai dreptul să gestionezi navigația.'], 403);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $hub = new NavHubService();
        ApiBootstrap::json(['success' => true, 'data' => $hub->snapshot()]);
    }

    $payload = json_decode(file_get_contents('php://input') ?: '', true);
    if (!is_array($payload)) {
        throw new InvalidArgumentException('JSON invalid.');
    }

    $action = (string) ($payload['action'] ?? '');
    $nav = new AdminNavRegistryService();

    switch ($action) {
        case 'list':
            ApiBootstrap::json(['success' => true, 'data' => (new NavHubService($nav))->snapshot()]);
            break;

        case 'sync':
            $sync = $nav->syncAllModules();
            ApiBootstrap::json([
                'success' => true,
                'message' => 'Navigația a fost resincronizată din module.',
                'data' => array_merge((new NavHubService($nav))->snapshot(), ['sync' => $sync]),
            ]);
            break;

        case 'save_raw':
            $raw = $payload['raw'] ?? null;
            if (!is_array($raw)) {
                throw new InvalidArgumentException('Lipsește raw (object JSON).');
            }
            $nav->importDecoded($raw);
            ApiBootstrap::json([
                'success' => true,
                'message' => 'registry.json salvat.',
                'data' => (new NavHubService($nav))->snapshot(),
            ]);
            break;

        case 'save_item':
            $itemRef = (string) ($payload['item_id'] ?? '');
            if ($itemRef === '') {
                throw new InvalidArgumentException('Lipsește item_id.');
            }
            $item = $nav->updateItem($itemRef, $payload);
            ApiBootstrap::json([
                'success' => true,
                'message' => 'Link actualizat.',
                'data' => array_merge((new NavHubService($nav))->snapshot(), ['item' => $item]),
            ]);
            break;

        case 'save_section':
            $sectionKey = (string) ($payload['section_id'] ?? $payload['section_key'] ?? '');
            if ($sectionKey === '') {
                throw new InvalidArgumentException('Lipsește section_id.');
            }
            $section = $nav->updateSection($sectionKey, $payload);
            ApiBootstrap::json([
                'success' => true,
                'message' => 'Secțiune actualizată.',
                'data' => array_merge((new NavHubService($nav))->snapshot(), ['section' => $section]),
            ]);
            break;

        case 'add_section':
            $section = $nav->addManualSection($payload);
            ApiBootstrap::json([
                'success' => true,
                'message' => 'Secțiune adăugată.',
                'data' => (new NavHubService($nav))->snapshot(),
            ]);
            break;

        case 'add_item':
            $sectionKey = (string) ($payload['section_key'] ?? '');
            if ($sectionKey === '') {
                throw new InvalidArgumentException('Lipsește section_key.');
            }
            $item = $nav->addManualItem($sectionKey, $payload);
            ApiBootstrap::json([
                'success' => true,
                'message' => 'Link adăugat.',
                'data' => ['item' => $item, 'snapshot' => (new NavHubService($nav))->snapshot()],
            ]);
            break;

        case 'delete_item':
            $itemRef = (string) ($payload['item_id'] ?? '');
            if ($itemRef === '') {
                throw new InvalidArgumentException('Lipsește item_id.');
            }
            $nav->deleteItem($itemRef, $payload);
            ApiBootstrap::json([
                'success' => true,
                'message' => 'Link eliminat din meniu.',
                'data' => (new NavHubService($nav))->snapshot(),
            ]);
            break;

        case 'reset_module':
            $moduleId = strtolower(trim((string) ($payload['module_id'] ?? '')));
            if ($moduleId === '') {
                throw new InvalidArgumentException('Lipsește module_id.');
            }
            $nav->resetModuleNav($moduleId);
            ApiBootstrap::json([
                'success' => true,
                'message' => 'Navigația modulului a fost resetată din module.json.',
                'data' => (new NavHubService($nav))->snapshot(),
            ]);
            break;

        default:
            ApiBootstrap::json(['success' => false, 'message' => 'Acțiune necunoscută.'], 400);
    }
} catch (Throwable $e) {
    ApiBootstrap::json(['success' => false, 'message' => $e->getMessage()], 400);
}
