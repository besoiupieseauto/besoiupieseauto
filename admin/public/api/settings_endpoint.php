<?php
declare(strict_types=1);

require_once __DIR__ . '/_autoload.php';

use Besoiu\Core\Bootstrap\ApiBootstrap;
use Besoiu\Services\AdminSettingsService;
use Besoiu\Services\AdminNavRegistryService;
use Besoiu\Services\AdminNavUserPreferences;
use Besoiu\Services\MetroLlmHubService;
use Besoiu\Services\OllamaControlService;
use Besoiu\Services\ModulesAdminService;
use Besoiu\Core\Auth\AdminPermissionCatalog;

ApiBootstrap::bootJsonApi();

try {
    ApiBootstrap::requireAuthenticatedSession();

    $service = new AdminSettingsService();
    $sessionUser = [
        'role' => (string) ($_SESSION['role'] ?? ''),
        'permissions' => $_SESSION['admin_permissions'] ?? null,
    ];

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $view = trim((string) ($_GET['view'] ?? ''));
        if ($view === 'usage_live') {
            ApiBootstrap::json(['success' => true, 'data' => $service->usageLivePayload()]);
        }
        if ($view === 'modules') {
            ApiBootstrap::json(['success' => true, 'data' => (new ModulesAdminService())->catalog()]);
        }
        if ($view === 'navigation') {
            ApiBootstrap::json(['success' => true, 'data' => (new AdminNavRegistryService())->listTreeForAdmin()]);
        }
        if ($view === 'module_export') {
            $role = (string) ($sessionUser['role'] ?? '');
            $perms = AdminPermissionCatalog::normalizePermissions($sessionUser['permissions'] ?? null, $role);
            $canModules = $role === 'super_ambassador'
                || $role === 'admin'
                || AdminPermissionCatalog::canManageUsers($role, $perms);
            if (!$canModules) {
                ApiBootstrap::json(['success' => false, 'message' => 'Nu ai dreptul să exporți module.'], 403);
            }
            $moduleId = strtolower(trim((string) ($_GET['module_id'] ?? $_GET['id'] ?? '')));
            if ($moduleId === '') {
                throw new InvalidArgumentException('Lipseste module_id.');
            }
            $export = (new ModulesAdminService())->exportZip($moduleId);
            $path = $export['path'];
            if (!is_file($path)) {
                ApiBootstrap::json(['success' => false, 'message' => 'ZIP export lipsă.'], 404);
            }
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . basename($export['filename']) . '"');
            header('Content-Length: ' . (string) filesize($path));
            header('Cache-Control: no-store');
            readfile($path);
            exit;
        }

        if ($view === 'ollama_control') {
            ApiBootstrap::releaseSession();
            ApiBootstrap::json(['success' => true, 'data' => (new OllamaControlService())->snapshot()]);
        }

        if ($view === 'ollama_opportunities') {
            ApiBootstrap::releaseSession();
            $live = !empty($_GET['live']);
            ApiBootstrap::json(['success' => true, 'data' => (new OllamaControlService())->verifyAll(['live' => $live])]);
        }

        ApiBootstrap::json(['success' => true, 'data' => $service->hubPayload($sessionUser)]);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        ApiBootstrap::json(['success' => false, 'message' => 'Metodă nepermisă.'], 405);
    }

    $rawInput = file_get_contents('php://input') ?: '';
    $payload = json_decode($rawInput, true);
    if (!is_array($payload)) {
        // multipart (ex. instalare ZIP) sau form POST
        $payload = $_POST;
        if (!is_array($payload)) {
            $payload = [];
        }
    }

    $action = (string) ($payload['action'] ?? $payload['type_product'] ?? ($_POST['action'] ?? 'hub'));

    if ($action === 'usage_live') {
        ApiBootstrap::json(['success' => true, 'data' => $service->usageLivePayload()]);
    }

    if ($action === 'hub') {
        ApiBootstrap::json(['success' => true, 'data' => $service->hubPayload($sessionUser)]);
    }

    if ($action === 'save_user') {
        $data = $service->saveUser($payload, $sessionUser);
        ApiBootstrap::json(['success' => true, 'message' => $data['message'], 'data' => $data]);
    }

    if ($action === 'delete_user') {
        $data = $service->deleteUser($payload, $sessionUser);
        ApiBootstrap::json(['success' => true, 'message' => $data['message'], 'data' => $data]);
    }

    if ($action === 'save_token_budget') {
        $data = $service->saveTokenBudget($payload);
        ApiBootstrap::json(['success' => true, 'message' => $data['message'], 'data' => $data]);
    }

    if ($action === 'save_env_keys') {
        $data = $service->saveEnvKeys($payload);
        ApiBootstrap::json(['success' => true, 'message' => $data['message'], 'data' => $data]);
    }

    if ($action === 'test_env_modules') {
        $module = isset($payload['module']) ? (string) $payload['module'] : 'all';
        $data = $service->testEnvModules($module);
        ApiBootstrap::json([
            'success' => true,
            'message' => sprintf(
                'Teste module: %d OK, %d eșec, %d sărite.',
                (int) ($data['summary']['ok'] ?? 0),
                (int) ($data['summary']['fail'] ?? 0),
                (int) ($data['summary']['skipped'] ?? 0)
            ),
            'data' => $data,
        ]);
    }

    if ($action === 'save_automation') {
        $data = $service->saveAutomationControls($payload);
        ApiBootstrap::json(['success' => true, 'message' => $data['message'], 'data' => $data]);
    }

    if ($action === 'save_provider_api') {
        $data = $service->saveProviderApiLive($payload);
        ApiBootstrap::json(['success' => true, 'message' => $data['message'], 'data' => $data]);
    }

    if ($action === 'arm_api_live') {
        $minutes = (int) ($payload['minutes'] ?? 30);
        $data = $service->armApiLive($minutes);
        ApiBootstrap::json(['success' => true, 'message' => $data['message'], 'data' => $data]);
    }

    if ($action === 'disarm_api_live') {
        $data = $service->disarmApiLive();
        ApiBootstrap::json(['success' => true, 'message' => $data['message'], 'data' => $data]);
    }

    if ($action === 'stop_all_api_consumption') {
        $data = $service->stopAllApiConsumption();
        ApiBootstrap::json(['success' => true, 'message' => $data['message'], 'data' => $data]);
    }

    // --- Module opționale (plug / unplug) ---
    if (in_array($action, ['modules_list', 'module_enable', 'module_disable', 'module_uninstall', 'module_restore', 'module_install', 'module_export'], true)) {
        $role = (string) ($sessionUser['role'] ?? '');
        $perms = AdminPermissionCatalog::normalizePermissions($sessionUser['permissions'] ?? null, $role);
        $canModules = $role === 'super_ambassador'
            || $role === 'admin'
            || AdminPermissionCatalog::canManageUsers($role, $perms);
        if (!$canModules) {
            ApiBootstrap::json(['success' => false, 'message' => 'Nu ai dreptul să gestionezi modulele.'], 403);
        }

        $modulesSvc = new ModulesAdminService();

        if ($action === 'modules_list') {
            ApiBootstrap::json(['success' => true, 'data' => $modulesSvc->catalog()]);
        }

        if ($action === 'module_install') {
            if (empty($_FILES['module_zip']) || !is_array($_FILES['module_zip'])) {
                throw new InvalidArgumentException('Încarcă un fișier ZIP (câmp module_zip).');
            }
            $overwrite = !empty($_POST['overwrite']) || !empty($payload['overwrite']);
            $data = $modulesSvc->installUpload($_FILES['module_zip'], (bool) $overwrite);
            ApiBootstrap::json(['success' => true, 'message' => $data['message'], 'data' => $data]);
        }

        $moduleId = strtolower(trim((string) ($payload['module_id'] ?? $payload['id'] ?? '')));
        if ($moduleId === '') {
            throw new InvalidArgumentException('Lipseste module_id.');
        }

        if ($action === 'module_enable') {
            $data = $modulesSvc->setEnabled($moduleId, true);
            ApiBootstrap::json(['success' => true, 'message' => $data['message'], 'data' => $data]);
        }
        if ($action === 'module_disable') {
            $data = $modulesSvc->setEnabled($moduleId, false);
            ApiBootstrap::json(['success' => true, 'message' => $data['message'], 'data' => $data]);
        }
        if ($action === 'module_uninstall') {
            $data = $modulesSvc->uninstall($moduleId);
            ApiBootstrap::json(['success' => true, 'message' => $data['message'], 'data' => $data]);
        }
        if ($action === 'module_restore') {
            $data = $modulesSvc->restoreStub($moduleId);
            ApiBootstrap::json(['success' => true, 'message' => $data['message'], 'data' => $data]);
        }
        if ($action === 'module_export') {
            $export = $modulesSvc->exportZip($moduleId);
            ApiBootstrap::json([
                'success' => true,
                'message' => 'ZIP gata: ' . $export['filename'],
                'data' => [
                    'filename' => $export['filename'],
                    'module_id' => $export['module_id'],
                    'bytes' => $export['bytes'],
                    'download_url' => '/admin/public/api/settings_endpoint.php?view=module_export&module_id=' . rawurlencode($export['module_id']),
                ],
            ]);
        }
    }

    // --- Navigație admin (registru JSON) ---
    if (in_array($action, ['nav_list', 'nav_sync', 'nav_save_item', 'nav_save_section', 'nav_reset_module', 'nav_raw', 'nav_save_raw', 'nav_add_section', 'nav_add_item'], true)) {
        $role = (string) ($sessionUser['role'] ?? '');
        $perms = AdminPermissionCatalog::normalizePermissions($sessionUser['permissions'] ?? null, $role);
        $canNav = $role === 'super_ambassador' || $role === 'admin' || AdminPermissionCatalog::canManageUsers($role, $perms);
        if (!$canNav) {
            ApiBootstrap::json(['success' => false, 'message' => 'Nu ai dreptul să gestionezi navigația.'], 403);
        }

        $navSvc = new AdminNavRegistryService();

        if ($action === 'nav_list') {
            ApiBootstrap::json([
                'success' => true,
                'data' => $navSvc->listTreeForAdmin(),
                'meta' => ['path' => $navSvc->registryPath(), 'source' => 'json'],
            ]);
        }

        if ($action === 'nav_raw') {
            ApiBootstrap::json(['success' => true, 'data' => $navSvc->readRaw(), 'meta' => ['path' => $navSvc->registryPath()]]);
        }

        if ($action === 'nav_save_raw') {
            $raw = $payload['raw'] ?? null;
            if (!is_array($raw)) {
                throw new InvalidArgumentException('Lipsește raw (object JSON).');
            }
            $navSvc->importDecoded($raw);
            ApiBootstrap::json([
                'success' => true,
                'message' => 'registry.json salvat.',
                'data' => ['tree' => $navSvc->listTreeForAdmin(), 'raw' => $navSvc->readRaw()],
            ]);
        }

        if ($action === 'nav_add_section') {
            $section = $navSvc->addManualSection($payload);
            ApiBootstrap::json([
                'success' => true,
                'message' => 'Secțiune adăugată.',
                'data' => ['section' => $section, 'tree' => $navSvc->listTreeForAdmin()],
            ]);
        }

        if ($action === 'nav_add_item') {
            $sectionKey = trim((string) ($payload['section_key'] ?? ''));
            if ($sectionKey === '') {
                throw new InvalidArgumentException('Lipsește section_key.');
            }
            $item = $navSvc->addManualItem($sectionKey, $payload);
            ApiBootstrap::json([
                'success' => true,
                'message' => 'Link adăugat.',
                'data' => ['item' => $item, 'tree' => $navSvc->listTreeForAdmin()],
            ]);
        }

        if ($action === 'nav_sync') {
            $result = $navSvc->syncAllModules();
            ApiBootstrap::json([
                'success' => true,
                'message' => 'Navigația a fost resincronizată din module.',
                'data' => ['tree' => $navSvc->listTreeForAdmin(), 'sync' => $result],
            ]);
        }

        $itemRef = trim((string) ($payload['item_id'] ?? $payload['id'] ?? ''));
        $sectionRef = trim((string) ($payload['section_id'] ?? $payload['section_key'] ?? ''));

        if ($action === 'nav_save_item') {
            if ($itemRef === '') {
                throw new InvalidArgumentException('Lipsește item_id (section_key::item_key).');
            }
            $item = $navSvc->updateItem($itemRef, $payload);
            ApiBootstrap::json(['success' => true, 'message' => 'Link actualizat.', 'data' => ['item' => $item]]);
        }

        if ($action === 'nav_save_section') {
            if ($sectionRef === '') {
                throw new InvalidArgumentException('Lipsește section_id.');
            }
            $section = $navSvc->updateSection($sectionRef, $payload);
            ApiBootstrap::json(['success' => true, 'message' => 'Secțiune actualizată.', 'data' => ['section' => $section]]);
        }

        if ($action === 'nav_reset_module') {
            $moduleId = strtolower(trim((string) ($payload['module_id'] ?? '')));
            if ($moduleId === '') {
                throw new InvalidArgumentException('Lipsește module_id.');
            }
            $navSvc->resetModuleNav($moduleId);
            ApiBootstrap::json([
                'success' => true,
                'message' => 'Navigația modulului a fost resetată din module.json.',
                'data' => $navSvc->listTreeForAdmin(),
            ]);
        }
    }

    // --- Preferințe navigație per-utilizator (personalizare meniu propriu) ---
    if (in_array($action, [
        'nav_pref_get',
        'nav_pref_hide_section',
        'nav_pref_show_section',
        'nav_pref_hide_item',
        'nav_pref_show_item',
        'nav_pref_reorder',
        'nav_pref_reset',
    ], true)) {
        $navUserId = (int) ($_SESSION['user_id'] ?? 0);
        if ($navUserId <= 0) {
            ApiBootstrap::json(['success' => false, 'message' => 'Sesiune invalidă.'], 403);
        }

        $prefs = AdminNavUserPreferences::forUser($navUserId);

        if ($action === 'nav_pref_get') {
            ApiBootstrap::json(['success' => true, 'data' => $prefs->toArray()]);
        }

        if ($action === 'nav_pref_hide_section' || $action === 'nav_pref_show_section') {
            $sectionKey = trim((string) ($payload['section_key'] ?? ''));
            if ($sectionKey === '') {
                throw new InvalidArgumentException('Lipsește section_key.');
            }
            $prefs->setSectionHidden($sectionKey, $action === 'nav_pref_hide_section');
            ApiBootstrap::json([
                'success' => true,
                'message' => $action === 'nav_pref_hide_section' ? 'Grup ascuns din meniul tău.' : 'Grup reafișat.',
                'data' => $prefs->toArray(),
            ]);
        }

        if ($action === 'nav_pref_hide_item' || $action === 'nav_pref_show_item') {
            $itemId = trim((string) ($payload['item_id'] ?? $payload['id'] ?? ''));
            if ($itemId === '') {
                throw new InvalidArgumentException('Lipsește item_id (section_key::item_key).');
            }
            $prefs->setItemHidden($itemId, $action === 'nav_pref_hide_item');
            ApiBootstrap::json([
                'success' => true,
                'message' => $action === 'nav_pref_hide_item' ? 'Link ascuns din meniul tău.' : 'Link reafișat.',
                'data' => $prefs->toArray(),
            ]);
        }

        if ($action === 'nav_pref_reorder') {
            $orderRaw = $payload['section_order'] ?? $payload['order'] ?? [];
            if (!is_array($orderRaw)) {
                throw new InvalidArgumentException('section_order trebuie să fie o listă de section_key.');
            }
            $order = array_values(array_map(static fn ($v): string => (string) $v, $orderRaw));
            $prefs->setSectionOrder($order);
            ApiBootstrap::json([
                'success' => true,
                'message' => 'Ordinea meniului a fost salvată.',
                'data' => $prefs->toArray(),
            ]);
        }

        if ($action === 'nav_pref_reset') {
            $prefs->reset();
            ApiBootstrap::json([
                'success' => true,
                'message' => 'Meniul a fost readus la varianta implicită.',
                'data' => $prefs->toArray(),
            ]);
        }
    }

    $metro = new MetroLlmHubService();

    if ($action === 'save_metro_llm') {
        $data = $metro->saveConfig($payload);
        ApiBootstrap::json(['success' => true, 'message' => $data['message'], 'data' => $data]);
    }

    if ($action === 'test_metro_ollama') {
        $res = $metro->testOllama();
        ApiBootstrap::json([
            'success' => !empty($res['ok']),
            'message' => !empty($res['ok']) ? ('Ollama OK: ' . mb_substr((string) ($res['content'] ?? ''), 0, 80)) : (string) ($res['error'] ?? 'Eșec'),
            'data' => array_merge($res, ['metro_llm' => $metro->snapshot()]),
        ]);
    }

    if ($action === 'test_metro_cloud' || $action === 'test_metro_cursor') {
        $res = $metro->testCloud();
        ApiBootstrap::json([
            'success' => !empty($res['ok']),
            'message' => !empty($res['ok']) ? 'Metro LLM OK' : (string) ($res['error'] ?? 'Eșec'),
            'data' => array_merge($res, ['metro_llm' => $metro->snapshot()]),
        ]);
    }

    if ($action === 'test_metro_cycle') {
        $res = $metro->testLocalCycle();
        ApiBootstrap::json([
            'success' => !empty($res['ok']),
            'message' => (string) ($res['message'] ?? $res['error'] ?? 'Ciclu finalizat'),
            'data' => array_merge($res, ['metro_llm' => $metro->snapshot()]),
        ]);
    }

    if ($action === 'test_metro_orchestra') {
        ApiBootstrap::json([
            'success' => false,
            'message' => 'Testele orchestră AI au fost dezactivate.',
        ], 410);
    }

    if ($action === 'metro_ai_orchestra_stats') {
        $root = dirname(__DIR__, 3);
        $orch = \Besoiu\Services\MetroAiOrchestrator::create($root);
        ApiBootstrap::json([
            'success' => true,
            'data' => [
                'readiness' => $orch->readiness(),
                'statistics' => $orch->statistics(),
            ],
        ]);
    }

    if ($action === 'ollama_control_snapshot') {
        ApiBootstrap::json(['success' => true, 'data' => (new OllamaControlService())->snapshot()]);
    }

    if ($action === 'ollama_integration_test') {
        ApiBootstrap::beginBoundedJsonWork(120);
        $integrationId = trim((string) ($payload['integration_id'] ?? ''));
        $res = (new OllamaControlService())->testIntegration($integrationId);
        ApiBootstrap::json([
            'success' => !empty($res['ok']),
            'message' => !empty($res['ok'])
                ? ('Test OK · ' . mb_substr((string) ($res['content'] ?? ''), 0, 80))
                : (string) ($res['error'] ?? 'Eșec test'),
            'data' => $res,
        ]);
    }

    if ($action === 'ollama_error_log') {
        $limit = max(1, min(200, (int) ($payload['limit'] ?? 50)));
        ApiBootstrap::json([
            'success' => true,
            'data' => (new OllamaControlService())->recentErrors($limit),
        ]);
    }

    if ($action === 'ollama_verify_all') {
        ApiBootstrap::beginBoundedJsonWork(180);
        $live = !empty($payload['live']);
        $data = (new OllamaControlService())->verifyAll(['live' => $live]);
        ApiBootstrap::json([
            'success' => ($data['summary']['fail'] ?? 1) === 0,
            'message' => sprintf(
                'Verificări: %d OK · %d atenție · %d eșec',
                (int) ($data['summary']['ok'] ?? 0),
                (int) ($data['summary']['warn'] ?? 0),
                (int) ($data['summary']['fail'] ?? 0)
            ),
            'data' => $data,
        ]);
    }

    if ($action === 'ollama_verify_one') {
        ApiBootstrap::beginBoundedJsonWork(120);
        $oppId = trim((string) ($payload['opportunity_id'] ?? ''));
        $live = !empty($payload['live']);
        $res = (new OllamaControlService())->verifyOne($oppId, $live);
        ApiBootstrap::json([
            'success' => !empty($res['ok']) || (($res['status'] ?? '') === 'warn'),
            'message' => (string) ($res['message'] ?? ''),
            'data' => $res,
        ]);
    }

    ApiBootstrap::json(['success' => false, 'message' => 'Acțiune necunoscută.'], 422);
} catch (Throwable $exception) {
    ApiBootstrap::respondInternalError('settings_endpoint', $exception);
}
