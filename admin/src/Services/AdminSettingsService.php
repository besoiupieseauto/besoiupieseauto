<?php

declare(strict_types=1);

namespace Evasystem\Services;

use Evasystem\Core\Auth\AdminPermissionCatalog;
use Evasystem\Core\Users\UsersModel;
use Evasystem\Controllers\Users\Users;
use Evasystem\Controllers\Users\UsersService;

/**
 * Hub setări admin — utilizatori + buget tokeni API.
 */
final class AdminSettingsService
{
  public function hubPayload(?array $sessionUser): array
  {
    require_once dirname(__DIR__, 2) . '/system/api_token_budget.php';
    require_once dirname(__DIR__, 2) . '/system/env_settings.php';

    $role = (string) ($sessionUser['role'] ?? $_SESSION['role'] ?? '');
    $perms = AdminPermissionCatalog::normalizePermissions(
      $sessionUser['permissions'] ?? $_SESSION['admin_permissions'] ?? null,
      $role
    );

    $pdo = api_token_budget_pdo();

    return [
      'can_manage_users' => AdminPermissionCatalog::canManageUsers($role, $perms),
      'permission_sections' => AdminPermissionCatalog::sections(),
      'permission_modules' => AdminPermissionCatalog::modules(),
      'role_presets' => AdminPermissionCatalog::rolePresets(),
      'env_keys' => besoiu_env_editable_keys(),
      'env_values_masked' => $this->maskedEnvValues(),
      'token_budgets' => api_token_budget_list($pdo),
      'token_stats' => api_token_budget_stats($pdo),
      'token_alerts' => api_token_budget_alerts($pdo),
      'users' => $this->listUsers(),
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
      $out[] = [
        'id' => (int) ($row['id'] ?? 0),
        'randomn_id' => (int) ($row['randomn_id'] ?? 0),
        'login' => (string) ($row['login'] ?? ''),
        'fullname' => (string) ($row['fullname'] ?? $row['nikname'] ?? ''),
        'role' => $role,
        'status' => (string) ($row['status'] ?? ''),
        'permissions' => $perms,
        'permissions_summary' => AdminPermissionCatalog::permissionsSummary($perms),
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
    require_once dirname(__DIR__, 3) . '/lib/Scraper/ScrapeDoConfig.php';
    $pdo = api_token_budget_pdo();
    api_token_budget_save($pdo, $input);

    $provider = api_token_budget_normalize_provider((string) ($input['provider_key'] ?? ''));
    if ($provider === 'scrape_do') {
      $usage = api_token_budget_provider_usage('scrape_do');
      if (is_array($usage) && (int) ($usage['queries_left'] ?? 0) > 0) {
        \ScrapeDoConfig::clearQuotaExceeded();
      }
    }

    return [
      'success' => true,
      'message' => 'Buget token salvat.',
      'token_budgets' => api_token_budget_list($pdo),
      'token_stats' => api_token_budget_stats($pdo),
      'token_alerts' => api_token_budget_alerts($pdo),
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

    return [
      'success' => true,
      'message' => $res['message'],
      'env_values_masked' => $this->maskedEnvValues(),
      'env_keys' => besoiu_env_editable_keys(),
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
