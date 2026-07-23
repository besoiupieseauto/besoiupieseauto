<?php

declare(strict_types=1);

namespace Besoiu\Services;

use Besoiu\Core\Auth\AdminChatPermissionCatalog;
use Besoiu\Core\Auth\AdminChatPermissionGuard;
use Besoiu\Core\Auth\AdminPermissionCatalog;
use Besoiu\Core\Users\UsersModel;
use Besoiu\Controllers\Users\Users;
use Besoiu\Services\UsersService;

/**
 * Hub setări admin — utilizatori + buget tokeni API.
 */
final class AdminSettingsService
{
  public function usageLivePayload(): array
  {
    require_once dirname(__DIR__, 2) . '/system/api_token_budget.php';
    require_once dirname(__DIR__, 2) . '/system/api_automation_catalog.php';

    try {
        $pdo = api_token_budget_pdo();

        return api_automation_usage_live_snapshot($pdo);
    } catch (\Throwable $e) {
        return [
            'generated_at' => date('c'),
            'error' => $e->getMessage(),
            'controls' => [],
            'summary' => [
                'automation_paused' => false,
                'api_live_armed' => false,
                'today_api_units' => 0,
                'today_ai_tokens' => 0,
                'auto_consumers_active' => 0,
            ],
            'providers' => [],
            'external_resources' => [],
            'hourly_today' => [],
            'timeline' => [],
            'ai_stats' => [],
        ];
    }
  }

  public function hubPayload(?array $sessionUser): array
  {
    require_once dirname(__DIR__, 2) . '/system/api_token_budget.php';
    require_once dirname(__DIR__, 2) . '/system/env_settings.php';
    require_once dirname(__DIR__, 2) . '/system/api_automation_catalog.php';

    $role = (string) ($sessionUser['role'] ?? $_SESSION['role'] ?? '');
    $perms = AdminPermissionCatalog::normalizePermissions(
      $sessionUser['permissions'] ?? $_SESSION['admin_permissions'] ?? null,
      $role
    );

    $pdo = api_token_budget_pdo();
    $hub = api_token_budget_hub_snapshot($pdo);
    $automation = [];
    try {
        $automation = api_automation_hub_snapshot($pdo);
    } catch (\Throwable) {
        $automation = ['ok' => false, 'summary' => ['message' => 'Panou automatizare indisponibil']];
    }

    $usageLive = [];
    try {
        $usageLive = api_automation_usage_live_snapshot($pdo);
    } catch (\Throwable $e) {
        $usageLive = [
            'generated_at' => date('c'),
            'error' => $e->getMessage(),
            'controls' => $automation['controls'] ?? [],
            'summary' => [
                'automation_paused' => !empty($automation['summary']['automation_paused']),
                'api_live_armed' => !empty($automation['summary']['api_live_armed']),
                'today_api_units' => 0,
                'today_ai_tokens' => 0,
                'auto_consumers_active' => (int) ($automation['summary']['auto_consumers_active'] ?? 0),
            ],
            'providers' => [],
            'external_resources' => [],
            'hourly_today' => [],
            'timeline' => array_map(
                static fn (array $row): array => array_merge($row, ['kind' => 'api']),
                is_array($automation['recent_usage'] ?? null) ? $automation['recent_usage'] : []
            ),
            'ai_stats' => [],
        ];
    }

    return [
      'can_manage_users' => AdminPermissionCatalog::canManageUsers($role, $perms),
      'permission_sections' => AdminPermissionCatalog::sections(),
      'permission_modules' => AdminPermissionCatalog::modules(),
      'role_presets' => AdminPermissionCatalog::rolePresets(),
      'chat_permission_groups' => AdminChatPermissionCatalog::groups(),
      'chat_role_presets' => AdminChatPermissionCatalog::rolePresets(),
      'env_keys' => besoiu_env_editable_keys(),
      'env_values_masked' => $this->maskedEnvValues(),
      'primary_providers' => api_token_budget_primary_providers(),
      'token_budgets' => $hub['budgets'],
      'token_stats' => $hub['stats'],
      'token_alerts' => $hub['alerts'],
      'api_token_hub' => $hub,
      'api_automation' => $automation,
      'usage_live' => $usageLive,
      'metro_llm' => (new MetroLlmHubService())->snapshot(),
      'users' => $this->listUsers(),
    ];
  }

  /** @param array<string, mixed> $input @return array<string, mixed> */
  public function saveProviderApiLive(array $input): array
  {
    require_once dirname(__DIR__, 2) . '/system/api_automation_catalog.php';
    require_once dirname(__DIR__, 2) . '/system/api_token_budget.php';

    $res = api_automation_save_provider_live($input);
    if (!$res['ok']) {
      throw new \RuntimeException($res['message']);
    }

    $pdo = api_token_budget_pdo();
    $hub = api_token_budget_hub_snapshot($pdo);
    $automation = api_automation_hub_snapshot($pdo);

    return [
      'success' => true,
      'message' => $res['message'],
      'token_budgets' => $hub['budgets'],
      'token_stats' => $hub['stats'],
      'token_alerts' => $hub['alerts'],
      'api_token_hub' => $hub,
      'api_automation' => $automation,
    ];
  }

  /** @param array<string, mixed> $input @return array<string, mixed> */
  public function saveAutomationControls(array $input): array
  {
    require_once dirname(__DIR__, 2) . '/system/api_automation_catalog.php';
    require_once dirname(__DIR__, 2) . '/system/api_token_budget.php';

    $res = api_automation_save_controls($input);
    if (!$res['ok']) {
      throw new \RuntimeException($res['message']);
    }

    $pdo = api_token_budget_pdo();
    $hub = api_token_budget_hub_snapshot($pdo);
    $automation = api_automation_hub_snapshot($pdo);

    return [
      'success' => true,
      'message' => $res['message'],
      'token_budgets' => $hub['budgets'],
      'token_stats' => $hub['stats'],
      'token_alerts' => $hub['alerts'],
      'api_token_hub' => $hub,
      'api_automation' => $automation,
    ];
  }

  /** @return array<string, mixed> */
  public function armApiLive(int $minutes = 30): array
  {
    require_once dirname(__DIR__, 2) . '/system/api_automation_catalog.php';
    require_once dirname(__DIR__, 2) . '/system/api_token_budget.php';

    $res = api_automation_arm_live($minutes);
    if (!$res['ok']) {
      throw new \RuntimeException($res['message']);
    }

    $pdo = api_token_budget_pdo();

    return [
      'success' => true,
      'message' => $res['message'],
      'api_live_arm' => $res['arm'],
      'api_automation' => api_automation_hub_snapshot($pdo),
    ];
  }

  /** @return array<string, mixed> */
  public function stopAllApiConsumption(): array
  {
    $guardPath = dirname(__DIR__, 2) . '/system/api_automation_guard.php';
    if (!is_file($guardPath)) {
      throw new \RuntimeException('Lipsește api_automation_guard.php');
    }
    require_once $guardPath;
    require_once dirname(__DIR__, 2) . '/system/api_automation_catalog.php';
    require_once dirname(__DIR__, 2) . '/system/api_token_budget.php';

    $result = besoiu_api_stop_all_consumption();
    if (!$result['ok']) {
      throw new \RuntimeException((string) ($result['message'] ?? 'Eșec oprire API'));
    }

    $pdo = api_token_budget_pdo();

    return [
      'success' => true,
      'message' => (string) $result['message'],
      'details' => $result['details'] ?? [],
      'api_live_arm' => besoiu_api_arm_state(),
      'api_automation' => api_automation_hub_snapshot($pdo),
    ];
  }

  /** @return array<string, mixed> */
  public function disarmApiLive(): array
  {
    require_once dirname(__DIR__, 2) . '/system/api_automation_catalog.php';
    require_once dirname(__DIR__, 2) . '/system/api_token_budget.php';

    $res = api_automation_disarm_live();
    $pdo = api_token_budget_pdo();

    return [
      'success' => true,
      'message' => $res['message'],
      'api_live_arm' => $res['arm'],
      'api_automation' => api_automation_hub_snapshot($pdo),
    ];
  }

  /** @return list<array<string, mixed>> */
  public function listUsers(): array
  {
    $rows = UsersModel::getUserssAll();
    $out = [];
    foreach ($rows as $row) {
      if (!is_array($row)) {
        continue;
      }
      $role = (string) ($row['role'] ?? '');
      $perms = AdminPermissionCatalog::normalizePermissions($row['permissions_json'] ?? null, $role);
      $chatPerms = AdminChatPermissionCatalog::normalizePermissions($row['chat_permissions_json'] ?? null, $role);
      $out[] = [
        'id' => (int) ($row['id'] ?? 0),
        'randomn_id' => (int) ($row['randomn_id'] ?? 0),
        'login' => (string) ($row['login'] ?? ''),
        'fullname' => (string) ($row['fullname'] ?? $row['nikname'] ?? ''),
        'role' => $role,
        'status' => (string) ($row['status'] ?? ''),
        'permissions' => $perms,
        'permissions_summary' => AdminPermissionCatalog::permissionsSummary($perms),
        'chat_permissions' => $chatPerms,
        'chat_permissions_summary' => AdminChatPermissionCatalog::permissionsSummary($chatPerms),
      ];
    }

    return $out;
  }

  /** @param array<string, mixed> $input @return array<string, mixed> */
  public function saveUser(array $input, ?array $sessionUser): array
  {
    $role = (string) ($sessionUser['role'] ?? $_SESSION['role'] ?? '');
    $perms = AdminPermissionCatalog::normalizePermissions($_SESSION['admin_permissions'] ?? null, $role);
    if (!AdminPermissionCatalog::canManageUsers($role, $perms)) {
      throw new \RuntimeException('Nu ai permisiunea de a gestiona utilizatori.');
    }

    $userId = (int) ($input['id'] ?? $input['randomn_id'] ?? $input['ridusers'] ?? 0);
    $isUpdate = $userId > 0;

    $permissions = $input['permissions'] ?? [];
    if (!is_array($permissions)) {
      $permissions = [];
    }
    $chatPermissions = $input['chat_permissions'] ?? [];
    if (!is_array($chatPermissions)) {
      $chatPermissions = [];
    }
    $requestedRole = strtolower(trim((string) ($input['role'] ?? 'operator')));
    if ($requestedRole === 'super_ambassador' && $role !== 'super_ambassador') {
      throw new \RuntimeException('Doar super_ambassador poate atribui rol super_ambassador.');
    }

    $payload = [
      'fullname' => trim((string) ($input['fullname'] ?? $input['name'] ?? '')),
      'nikname' => trim((string) ($input['fullname'] ?? $input['name'] ?? '')),
      'login' => trim((string) ($input['login'] ?? $input['email'] ?? '')),
      'contact' => trim((string) ($input['login'] ?? $input['email'] ?? '')),
      'role' => $requestedRole,
      'status' => !empty($input['status']) && (string) $input['status'] !== '0' ? '1' : '0',
      'permissions_json' => json_encode(
        AdminPermissionCatalog::normalizePermissions($permissions, $requestedRole),
        JSON_UNESCAPED_UNICODE
      ),
      'chat_permissions_json' => json_encode(
        AdminChatPermissionCatalog::normalizePermissions($chatPermissions, $requestedRole),
        JSON_UNESCAPED_UNICODE
      ),
    ];

    if (!empty($input['password'])) {
      $payload['password'] = (string) $input['password'];
    }

    if ($isUpdate) {
      $payload['ridusers'] = $userId;
    }

    $controller = new Users(new UsersService());
    $result = $controller->addProfileInfo($payload);
    if (empty($result['success'])) {
      throw new \RuntimeException((string) ($result['message'] ?? 'Eroare la salvare utilizator.'));
    }

    return ['success' => true, 'message' => $isUpdate ? 'Utilizator actualizat.' : 'Utilizator creat.', 'users' => $this->listUsers()];
  }

  /** @param array<string, mixed> $input */
  public function deleteUser(array $input, ?array $sessionUser): array
  {
    $role = (string) ($sessionUser['role'] ?? $_SESSION['role'] ?? '');
    $perms = AdminPermissionCatalog::normalizePermissions($_SESSION['admin_permissions'] ?? null, $role);
    if (!AdminPermissionCatalog::canManageUsers($role, $perms)) {
      throw new \RuntimeException('Nu ai permisiunea de a șterge utilizatori.');
    }

    $userId = (int) ($input['id'] ?? $input['randomn_id'] ?? 0);
    $currentId = (int) ($_SESSION['user_id'] ?? 0);
    if ($userId <= 0) {
      throw new \InvalidArgumentException('ID utilizator invalid.');
    }
    if ($userId === $currentId) {
      throw new \RuntimeException('Nu poți șterge propriul cont.');
    }

    $service = new UsersService();
    $result = $service->deleteUsers($userId);
    if (empty($result['success'])) {
      throw new \RuntimeException((string) ($result['message'] ?? 'Ștergerea a eșuat.'));
    }

    return ['success' => true, 'message' => 'Utilizator șters.', 'users' => $this->listUsers()];
  }

  /** @param array<string, mixed> $input */
  public function saveTokenBudget(array $input): array
  {
    require_once dirname(__DIR__, 2) . '/system/api_token_budget.php';
    require_once dirname(__DIR__, 3) . '/lib/Scraper/bootstrap.php';
    $pdo = api_token_budget_pdo();
    api_token_budget_save($pdo, $input);

    $provider = api_token_budget_normalize_provider((string) ($input['provider_key'] ?? ''));
    if ($provider === 'scrape_do') {
      $usage = api_token_budget_provider_usage('scrape_do');
      if (is_array($usage) && (int) ($usage['requests_left'] ?? $usage['queries_left'] ?? 0) > 0) {
        \ScrapeDoConfig::clearQuotaExceeded();
      }
    }

    api_token_budget_sync_supervisor_store();
    $hub = api_token_budget_hub_snapshot($pdo);

    return [
      'success' => true,
      'message' => 'Buget token salvat.',
      'token_budgets' => $hub['budgets'],
      'token_stats' => $hub['stats'],
      'token_alerts' => $hub['alerts'],
      'api_token_hub' => $hub,
    ];
  }

  /** @param array<string, mixed> $input */
  public function saveEnvKeys(array $input): array
  {
    require_once dirname(__DIR__, 2) . '/system/env_settings.php';
    $env = is_array($input['env'] ?? null) ? $input['env'] : $input;
    $res = besoiu_env_save_keys($env);
    if (!$res['ok']) {
      throw new \RuntimeException($res['message']);
    }

    require_once dirname(__DIR__, 2) . '/system/api_token_budget.php';
    $pdo = api_token_budget_pdo();
    api_token_budget_sync_supervisor_store();
    $hub = api_token_budget_hub_snapshot($pdo);

    return [
      'success' => true,
      'message' => $res['message'],
      'env_values_masked' => $this->maskedEnvValues(),
      'env_keys' => besoiu_env_editable_keys(),
      'budget_resets' => $res['budget_resets'] ?? [],
      'token_budgets' => $hub['budgets'],
      'token_stats' => $hub['stats'],
      'token_alerts' => $hub['alerts'],
      'api_token_hub' => $hub,
    ];
  }

  /** @return array<string, mixed> */
  public function testEnvModules(?string $module = null): array
  {
    return [
      'success' => false,
      'message' => 'Testele modulului de import/API au fost dezactivate.',
      'module' => $module,
    ];
  }

  /** @return array<string, string> */
  private function maskedEnvValues(): array
  {
    require_once dirname(__DIR__, 2) . '/system/env_settings.php';
    require_once dirname(__DIR__, 2) . '/system/api_token_budget.php';
    $vals = besoiu_env_current_values();
    $masked = [];
    foreach ($vals as $k => $v) {
      $masked[$k] = $v !== '' ? api_token_budget_mask_secret($v) : '';
    }

    return $masked;
  }
}
