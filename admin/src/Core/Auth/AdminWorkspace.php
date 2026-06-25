<?php

declare(strict_types=1);

namespace Evasystem\Core\Auth;

use Evasystem\Core\AdminUrl;

/**
 * Workspace activ în sesiune — portal departamente admin.
 */
final class AdminWorkspace
{
    private const SESSION_KEY = 'admin_workspace';

    public static function getCurrent(): ?string
    {
        $id = (string) ($_SESSION[self::SESSION_KEY] ?? '');
        if ($id === '') {
            return null;
        }

        $resolved = AdminWorkspaceCatalog::resolveId($id);
        if ($resolved === null) {
            unset($_SESSION[self::SESSION_KEY]);

            return null;
        }

        if ($resolved !== $id) {
            $_SESSION[self::SESSION_KEY] = $resolved;
        }

        return $resolved;
    }

    public static function setCurrent(string $workspaceId): void
    {
        $resolved = AdminWorkspaceCatalog::resolveId($workspaceId);
        if ($resolved === null) {
            throw new \InvalidArgumentException('Workspace invalid: ' . $workspaceId);
        }
        $_SESSION[self::SESSION_KEY] = $resolved;
    }

    public static function clear(): void
    {
        unset($_SESSION[self::SESSION_KEY]);
    }

    /** @return list<string> */
    public static function allowedWorkspacesForSession(): array
    {
        if (empty($_SESSION['user_id'])) {
            return [];
        }

        $role = (string) ($_SESSION['role'] ?? 'guest');
        $perms = AdminPermissionCatalog::normalizePermissions(
            $_SESSION['admin_permissions'] ?? null,
            $role
        );

        $out = [];
        foreach (AdminWorkspaceCatalog::ids() as $id) {
            if (self::userCanAccessWorkspace($id, $role, $perms)) {
                $out[] = $id;
            }
        }

        return $out;
    }

    /** @param list<string> $permissions */
    public static function userCanAccessWorkspace(string $workspaceId, string $role, array $permissions): bool
    {
        if ($role === 'super_ambassador') {
            return AdminWorkspaceCatalog::get($workspaceId) !== null;
        }

        $features = AdminWorkspaceCatalog::featuresFor($workspaceId);
        if ($features === []) {
            return false;
        }

        $expanded = AdminPermissionCatalog::expandToFeatureKeys($permissions);

        return array_intersect($features, $expanded) !== [];
    }

    public static function isExemptPath(string $path): bool
    {
        $path = self::normalizePath($path);
        $workspacePath = rtrim(AdminUrl::path('workspace'), '/');
        $loginPath = rtrim(AdminUrl::path('login'), '/');
        $logoutPath = rtrim(AdminUrl::path('logout'), '/');

        $exempt = [
            $workspacePath,
            rtrim(AdminUrl::path('workspace-switch'), '/'),
            $loginPath,
            $logoutPath,
            '/admin/403',
            '/admin/settings',
            '/admin/alerts',
        ];

        foreach ($exempt as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                return true;
            }
        }

        if (str_starts_with($path, '/admin/api/') || str_starts_with($path, '/admin/public/api/')) {
            return true;
        }

        if (preg_match('#^/admin(?:/public)?/crud[a-z0-9_-]+$#i', $path)) {
            return true;
        }

        if (str_starts_with($path, '/admin/assets/') || str_starts_with($path, '/admin/public/assets/')) {
            return true;
        }

        return false;
    }

    /** @param list<string> $permissions */
    public static function pathAllowedInWorkspace(string $path, string $workspaceId, string $role, array $permissions): bool
    {
        if (!AdminPermissionCatalog::urlAllowed($path, $permissions, $role)) {
            return false;
        }

        if (self::isExemptPath($path)) {
            return true;
        }

        $path = self::normalizePath($path);
        $allowedUrls = AdminWorkspaceCatalog::urlsForWorkspace($workspaceId);

        foreach ($allowedUrls as $prefix) {
            $prefix = rtrim($prefix, '/');
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                return true;
            }
        }

        return false;
    }

    public static function enforce(string $path, string $method = 'GET'): void
    {
        if (empty($_SESSION['user_id'])) {
            return;
        }

        $path = self::normalizePath($path);
        if (self::isExemptPath($path)) {
            return;
        }

        $allowed = self::allowedWorkspacesForSession();
        if ($allowed === []) {
            return;
        }

        $workspacePath = rtrim(AdminUrl::path('workspace'), '/');
        if ($path === $workspacePath || str_starts_with($path, $workspacePath . '/')) {
            return;
        }

        $current = self::getCurrent();
        if ($current === null || !in_array($current, $allowed, true)) {
            if (count($allowed) === 1) {
                self::setCurrent($allowed[0]);
                $current = $allowed[0];
            } else {
                self::redirectToWorkspaceSelect();
            }
        }

        $role = (string) ($_SESSION['role'] ?? 'guest');
        $perms = AdminPermissionCatalog::normalizePermissions(
            $_SESSION['admin_permissions'] ?? null,
            $role
        );

        if (!self::pathAllowedInWorkspace($path, (string) $current, $role, $perms)) {
            self::denyWorkspaceAccess((string) $current);
        }
    }

    public static function redirectToWorkspaceSelect(): never
    {
        $url = AdminUrl::path('workspace');
        if (!headers_sent()) {
            header('Location: ' . $url, true, 302);
            exit;
        }
        echo '<script>location.href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '";</script>';
        exit;
    }

    public static function redirectAfterLogin(): string
    {
        $allowed = self::allowedWorkspacesForSession();
        if (count($allowed) === 1) {
            self::setCurrent($allowed[0]);

            return AdminWorkspaceCatalog::dashboardPath($allowed[0]);
        }

        return AdminUrl::path('workspace');
    }

    private static function denyWorkspaceAccess(string $workspaceId): void
    {
        $accept = strtolower($_SERVER['HTTP_ACCEPT'] ?? '');
        $isAjax = (strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest')
            || str_contains($accept, 'application/json');

        if ($isAjax) {
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8', true, 403);
            }
            echo json_encode([
                'success' => false,
                'message' => 'Această secțiune nu face parte din departamentul activ.',
                'workspace' => $workspaceId,
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $dashboard = AdminWorkspaceCatalog::dashboardPath($workspaceId);
        if (!headers_sent()) {
            header('Location: ' . $dashboard . '?workspace_denied=1', true, 302);
            exit;
        }
        echo '<script>location.href="' . htmlspecialchars($dashboard, ENT_QUOTES, 'UTF-8') . '";</script>';
        exit;
    }

    private static function normalizePath(string $path): string
    {
        $path = parse_url($path, PHP_URL_PATH) ?: $path;
        $path = rtrim($path, '/') ?: '/';

        return AdminUrl::normalizeRequestPath($path);
    }
}
