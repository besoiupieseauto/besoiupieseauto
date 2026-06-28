<?php
declare(strict_types=1);

namespace Besoiu\Core\Auth;

use Besoiu\Core\AdminUrl;
use Besoiu\Core\AdvancedCRUD;

/**
 * Permision – control de permisiuni / navigare / vizibilitate pe roluri.
 *
 * Exemplu rapid:
 *   $perm = new Permision($ROLES);
 *   // optional: încărcăm rutele active din DB (tabela routes)
 *   $perm->warmRoutesIndex();
 *
 *   // În index.php – protecție:
 *   $perm->guard($_SERVER['REQUEST_METHOD'] ?? 'GET', parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/');
 *
 *   // În template – meniu vizibil:
 *   $menu = $perm->allowedNav($_SESSION['role'] ?? 'guest');
 *   foreach ($menu as $label => $href) { ... }
 */
class Permision
{
    /** @var array<string,array> */
    private array $roles;

    /** mapă din DB: path => is_active (1/0) */
    private array $routesIndex = [];
    private array $navTreeCache = [];

    /** rute publice (nu necesită login) – acceptă wildcard `*` */
    private array $publicPaths = [
        '/', '/public/login', '/public/reg',
        '/public/auth/google', '/public/auth/google/callback',
        '/assets/*', '/public/assets/*'
    ];

    /** ce răspuns să dea guard() pentru JSON vs HTML */
    private string $loginPath = '/admin/login';
    private string $forbiddenPath = '/admin/403';

    public function __construct(array $roles)
    {
        $this->roles = $roles;
    }

    /** Setează/înlocuiește lista de rute publice */
    public function setPublicPaths(array $patterns): void
    {
        $this->publicPaths = $patterns;
    }

    /** Setează path-urile de redirect */
    public function setRedirects(string $login, string $forbidden = '/admin/403'): void
    {
        $this->loginPath = $login;
        $this->forbiddenPath = $forbidden;
    }

    /**
     * Citește tabela `routes` și încălzește index-ul local.
     * Folosește AdvancedCRUD::select('routes','*',"WHERE is_active = 1")
     * și marchează doar path-urile active.
     */
    public function warmRoutesIndex(): void
    {
        try {
            $rows = AdvancedCRUD::select('routes', '*', "WHERE is_active = 1");
            foreach ($rows as $r) {
                $path = (string)($r['path'] ?? '');
                if ($path !== '') $this->routesIndex[$path] = 1;
            }
        } catch (\Throwable $e) {
            // dacă DB nu e pregătită, ignorăm cu grație
            error_log('[Permision] warmRoutesIndex fail: ' . $e->getMessage());
        }
    }

    /** Poți injecta din afară index-ul (cache) al rutelor active: [path => 1] */
    public function setRoutesIndex(array $index): void
    {
        $this->routesIndex = $index;
    }

    /** Returnează meta-rol (sau guest dacă nu există) */
    public function roleMeta(string $role): array
    {
        return $this->roles[$role] ?? $this->roles['guest'] ?? ['label'=>'Guest','nav'=>[],'widgets'=>[],'scopes'=>[]];
    }

    /** Are rolul un anumit scope? */
    public function hasScope(string $role, string $scope): bool
    {
        $meta = $this->roleMeta($role);
        return in_array($scope, $meta['scopes'] ?? [], true);
    }

    /** Widget-urile vizibile pentru rol (+ override-uri) */
    public function allowedWidgets(string $role, array $overrides = []): array
    {
        $meta = $this->roleMeta($role);
        $widgets = $meta['widgets'] ?? [];

        // overrides: deny_widgets / allow_widgets
        if (!empty($overrides['deny_widgets'])) {
            $widgets = array_values(array_diff($widgets, (array)$overrides['deny_widgets']));
        }
        if (!empty($overrides['allow_widgets'])) {
            $widgets = array_values(array_unique(array_merge($widgets, (array)$overrides['allow_widgets'])));
        }

        return $widgets;
    }

    /**
     * Navigația vizibilă pentru rol:
     * - pornește de la $ROLES[$role]['nav']
     * - aplică overrides (deny_nav/allow_nav)
     * - filtrează rutele inactive (dacă avem warmRoutesIndex)
     */
    public function navTreeFor(string $role): array
    {
      
        // dacă în DB au rămas spații, le ignorăm cu REPLACE
        $rows = \Besoiu\Core\AdvancedCRUD::selectnew(
            'role_nav',
            '*',
            'WHERE is_active = 1 AND FIND_IN_SET(:role, REPLACE(role_slug, " ", "")) > 0',
            'COALESCE(parent_id,0), sort_order, id',
            '',
            [':role' => $role]
        );

        $index = [];
        foreach ($rows as $r) {
            $id = (int)$r['id'];
            $index[$id] = [
                'id'       => $id,
                'parent'   => $r['parent_id'] !== null ? (int)$r['parent_id'] : null,
                'label'    => (string)$r['label'],
                'url'      => $r['url'] !== null ? (string)$r['url'] : null,
                'icon'     => (string)($r['icon'] ?: 'bx bx-right-arrow-alt'),
                'children' => [],
            ];
        }

        $tree = [];
        foreach ($index as $id => &$node) {
            if ($node['parent'] && isset($index[$node['parent']])) {
                $index[$node['parent']]['children'][] =& $node;
            } else {
                $tree[] =& $node;
            }
        }
        unset($node);

        return $tree;
    }

    /**
     * Fallback: dacă nu ai rânduri în role_nav pentru rolul cerut,
     * construiește un arbore simplu din configurarea roles.php (nav flat).
     */
    private function fallbackNavTreeFromRoles(string $role): array
    {
        // $this->roles ar trebui să conțină map-ul încărcat din RolesRepository::loadAll()
        if (empty($this->roles[$role]['nav']) || !is_array($this->roles[$role]['nav'])) {
            return [];
        }
        $tree = [];
        foreach ($this->roles[$role]['nav'] as $label => $url) {
            $label = trim((string)$label);
            if ($label === '') continue;
            $tree[] = [
                'label' => $label,
                'url'   => (string)$url,
                'icon'  => 'bx bx-radio-circle',
                // fără children – e o listă simplă
            ];
        }
        return $tree;
    }
    public function allowedNav(string $role, array $overrides = []): array
    {
        $meta = $this->roleMeta($role);
        $nav  = $meta['nav'] ?? [];

        // deny/allow după LABEL sau PATH
        $deny = (array)($overrides['deny_nav'] ?? []);
        $allow = (array)($overrides['allow_nav'] ?? []);

        // 1) deny
        foreach ($nav as $label => $href) {
            if (in_array($label, $deny, true) || in_array($href, $deny, true)) {
                unset($nav[$label]);
            }
        }

        // 2) allow (adaugi intrări extra)
        foreach ($allow as $k => $v) {
            // acceptăm fie ['Label'=>'/path'] fie ['/path'] (fără label)
            if (is_string($k) && is_string($v)) {
                $nav[$k] = $v;
            } elseif (is_string($v)) {
                $nav[$v] = $v;
            }
        }

        // 3) filtrează pe rute active (dacă avem index populat)
        if (!empty($this->routesIndex)) {
            foreach ($nav as $label => $href) {
                $path = $this->normalizePath($href);
                if (!$this->isPathActive($path)) {
                    unset($nav[$label]);
                }
            }
        }

        return $nav;
    }

    /** Este path-ul marcat activ în DB (sau necunoscut => permis)? */
    private function isPathActive(string $path): bool
    {
        if (empty($this->routesIndex)) return true; // dacă nu avem cache, nu blocăm
        return (bool)($this->routesIndex[$path] ?? 0);
    }

    /** Verifică dacă PATH este public (nu cere autentificare) */
    public function isPublic(string $path): bool
    {
        $path = $this->normalizePath($path);
        foreach ($this->publicPaths as $pattern) {
            if ($this->match($pattern, $path)) return true;
        }
        return false;
    }

    /**
     * Permite PATH pentru ROL?
     * Reguli:
     *  - public → true
     *  - super_ambassador → true
     *  - dacă path e în nav-ul rolului (după filtre + rute active) → true
     *  - overrides: allow_paths / deny_paths
     */
    public function isRouteAllowedForRole(string $role, string $method, string $path, array $overrides = []): bool
    {
        $path = $this->normalizePath($path);
        $role = $this->normalizeRoleSlug($role);

        // public
        if ($this->isPublic($path)) return true;

        // super user — acces total explicit
        if ($role === 'super_ambassador') return true;
        if ($role === 'manager') return true;

        // Roluri restrânse — verificare nav + overrides (fără bypass total)

        // overrides deny_paths
        foreach ((array)($overrides['deny_paths'] ?? []) as $deny) {
            if ($this->match($deny, $path)) return false;
        }

        // overrides allow_paths
        foreach ((array)($overrides['allow_paths'] ?? []) as $allow) {
            if ($this->match($allow, $path)) return true;
        }

        // nav-based (curat + legacy /admin/public/*)
        $nav = $this->allowedNav($role, $overrides);
        foreach ($nav as $label => $href) {
            if ($this->pathsMatchForPermission($path, (string) $href)) {
                return true;
            }
        }

        // Heuristic: permite CRUD aferent modulelor vizibile
        // ex: dacă ai /public/clients în nav, atunci /public/crudclients POST e ok.
        $base = $this->crudBaseFromPath($path);
        if ($base !== null) {
            foreach ($nav as $label => $href) {
                $npath = $this->normalizePath($href);
                $navBase = $this->crudBaseFromPath($npath);
                if ($navBase !== null && $navBase === $base) {
                    return true;
                }
            }
        }

        return false;
    }

    /** Protejează o rută (redirect sau 403 JSON) */
    public function guard(string $method, string $path, array $overrides = []): void
    {
        $path = $this->normalizePath($path);
        $role = $this->normalizeRoleSlug((string)($_SESSION['role'] ?? 'guest'));
        $isLoggedIn = !empty($_SESSION['user_id']);

        // dacă path e public, nu facem nimic
        if ($this->isPublic($path)) return;

        // Delegare module (utilizatori cu permissions_json în BD)
        if ($isLoggedIn && !empty($_SESSION['admin_permissions_delegated']) && $role !== 'super_ambassador') {
            $perms = \Besoiu\Core\Auth\AdminPermissionCatalog::normalizePermissions(
                $_SESSION['admin_permissions'] ?? null,
                $role
            );
            if (!\Besoiu\Core\Auth\AdminPermissionCatalog::urlAllowed($path, $perms, $role)) {
                $this->denyAccess($isLoggedIn, $role, $path);
            }
            return;
        }

        $ok = $this->isRouteAllowedForRole($role, $method, $path, $overrides);

        if ($ok) return;

        $this->denyAccess($isLoggedIn, $role, $path);
    }

    private function denyAccess(bool $isLoggedIn, string $role, string $path): void
    {
        // Evită bucle: nu redirecționa spre login dacă EȘTI pe login.
        if ($path === $this->loginPath || $path === $this->forbiddenPath) {
            return;
        }

        $accept = strtolower($_SERVER['HTTP_ACCEPT'] ?? '');
        $isAjax = (strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest')
            || str_contains($accept, 'application/json');

        if ($isAjax) {
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8', true, $isLoggedIn ? 403 : 401);
            }
            echo json_encode([
                'success' => false,
                'message' => $isLoggedIn
                    ? 'Acces interzis pentru rolul curent.'
                    : 'Autentificare necesară.',
                'role'    => $role,
                'path'    => $path
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if (!$isLoggedIn) {
            $redir = $this->loginPath . '?next=' . rawurlencode($path);
            if (!headers_sent()) {
                header('Location: ' . $redir, true, 302);
                exit;
            }

            echo '<script>location.href=' . json_encode($redir) . ';</script>';
            echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($redir, ENT_QUOTES, 'UTF-8') . '"></noscript>';
            exit;
        }

        $forbidden = $this->forbiddenPath;
        if (!headers_sent()) {
            header('Location: ' . $forbidden, true, 302);
            exit;
        }

        echo '<script>location.href=' . json_encode($forbidden) . ';</script>';
        exit;
    }

    /* ===================== helpers ===================== */

    private function normalizePath(string $path): string
    {
        return AdminUrl::normalizeRequestPath($path);
    }

    private function normalizeRoleSlug(string $role): string
    {
        $role = strtolower(trim($role));
        return str_replace([' ', '-'], '_', $role);
    }

    /** Potrivire /admin/cron ↔ /admin/public/cron (și query pe path cerere). */
    private function pathsMatchForPermission(string $requestPath, string $navHref): bool
    {
        $requestPath = $this->normalizePath($requestPath);
        $navPath = $this->normalizePath($navHref);

        if (str_starts_with($requestPath, $navPath . '?')) {
            return true;
        }

        $requestVariants = AdminUrl::alternatePaths($requestPath);
        $navVariants = AdminUrl::alternatePaths($navPath);

        foreach ($requestVariants as $candidate) {
            if (in_array($candidate, $navVariants, true)) {
                return true;
            }
        }

        return false;
    }

    /** Pagini GET din meniul admin (config/admin_nav_routes.php). */
    private function isStandardAdminPagePath(string $path): bool
    {
        static $standardPaths = null;
        if ($standardPaths === null) {
            /** @var list<string> $slugs */
            $slugs = require dirname(__DIR__, 3) . '/config/admin_nav_routes.php';
            $standardPaths = [];
            foreach ($slugs as $slug) {
                foreach (AdminUrl::alternatePaths(AdminUrl::path((string) $slug)) as $variant) {
                    $standardPaths[$variant] = true;
                }
            }
        }

        foreach (AdminUrl::alternatePaths($path) as $variant) {
            if (isset($standardPaths[$variant])) {
                return true;
            }
        }

        return false;
    }

    /** match cu suport pentru wildcard * (doar pe path, nu pe query) */
    private function match(string $pattern, string $path): bool
    {
        $pattern = $this->normalizePath($pattern);
        // escape regex
        $regex = '#^' . str_replace('\*', '.*', preg_quote($pattern, '#')) . '$#i';
        return (bool)preg_match($regex, $path);
    }

    /** întoarce „crudclients” din „/public/crudclients” pentru heuristica CRUD */
    private function crudBaseFromPath(string $path): ?string
    {
        $seg = $this->lastSegment($path);
        if ($seg === '') return null;
        if (str_starts_with($seg, 'crud') || str_starts_with($seg, 'add')) {
            return $seg; // ex: crudusers, addusers
        }
        return null;
    }

    /** ultimul segment de path (fără query) */
    private function lastSegment(string $path): string
    {
        $path = $this->normalizePath($path);
        $parts = explode('/', $path);
        return (string)end($parts);
    }
}

/** Alias convenabil dacă vrei „Permission” în loc de „Permision” */
\class_alias(\Besoiu\Core\Auth\Permision::class, \Besoiu\Core\Auth\Permission::class);
