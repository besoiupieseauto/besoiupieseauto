<?php

declare(strict_types=1);

namespace Besoiu\Core\Auth;

use Besoiu\Core\AdminUrl;

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
            '/admin/system-errors',
        ];

        foreach ($exempt as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                return true;
            }
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
        $role = strtolower(trim($role));
        if (in_array($role, ['super_ambassador', 'manager'], true)) {
            return true;
        }

        if (self::isExemptPath($path)) {
            return true;
        }

        if (!AdminPermissionCatalog::urlAllowed($path, $permissions, $role)) {
            return false;
        }

        // Hub-uri accesibile din orice departament dacă utilizatorul are permisiunea URL.
        $crossWorkspacePaths = [
            '/admin/export',
            '/admin/settings',
            // AI Agent → instalare reguli creier în RAG chat (Comunicare)
            '/admin/comunicare-chat',
        ];
        foreach ($crossWorkspacePaths as $hubPath) {
            if ($path === $hubPath || str_starts_with($path, $hubPath . '/')) {
                return true;
            }
        }

        $path = self::normalizePath($path);
        $allowedUrls = AdminWorkspaceCatalog::urlsForWorkspace($workspaceId);

        foreach ($allowedUrls as $prefix) {
            $prefix = rtrim($prefix, '/');
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                return true;
            }
        }

        // Fallback: slug din modul activ mapat pe workspace-ul curent
        if (class_exists(\Besoiu\Core\Module\ModuleGate::class)
            && preg_match('#^/admin(?:/public)?/([a-z0-9\-]+)#i', $path, $m)
        ) {
            $slug = strtolower($m[1]);
            foreach (\Besoiu\Core\Module\ModuleGate::optionalMap() as $moduleId => $meta) {
                if (!\Besoiu\Core\Module\ModuleGate::enabled((string) $moduleId)) {
                    continue;
                }
                $slugs = $meta['slugs'] ?? [];
                if (!in_array($slug, $slugs, true)) {
                    continue;
                }
                $modWs = AdminWorkspaceCatalog::workspaceForModuleId((string) $moduleId, is_array($meta) ? $meta : []);
                if ($modWs === $workspaceId) {
                    return true;
                }
            }
        }

        return false;
    }

    /** @return list<string> */
    public static function crossWorkspaceFeatures(): array
    {
        return [
            'dashboard.home',
            'automatizare.export',
            'sistem.alerts',
            'sistem.settings',
        ];
    }

    public static function isCrossWorkspaceFeature(string $featureKey): bool
    {
        return in_array($featureKey, self::crossWorkspaceFeatures(), true);
    }

    public static function featureAllowedInWorkspace(string $featureKey, string $workspaceId): bool
    {
        if (self::isCrossWorkspaceFeature($featureKey)) {
            return true;
        }

        return in_array($featureKey, AdminWorkspaceCatalog::featuresFor($workspaceId), true);
    }

    public static function featureAllowedInCurrentWorkspace(string $featureKey): bool
    {
        $current = self::getCurrent();
        if ($current === null) {
            return false;
        }

        return self::featureAllowedInWorkspace($featureKey, $current);
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
