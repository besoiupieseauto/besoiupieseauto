<?php
declare(strict_types=1);

namespace Evasystem\Controllers;

use Config\Database;
use Evasystem\Controllers\Redirector;
use Evasystem\Core\AdminPageResolver;
use Evasystem\Core\AdminUrl;
use Evasystem\Core\Auth\SessionAuth;

class Admin
{
    private string $dir = '';

    public function login(): void
    {
        $data = ['title' => 'Welcome', 'message' => 'Hello, welcome to our website!'];
        $this->renderadmin('login', $data, 'pages/user');
    }

    /** GET /admin/ sau /admin/public — redirecționare către login */
    public function redirectToLogin(string $dir = ''): void
    {
        SessionAuth::redirectToLogin();
    }

    /** GET /admin/logout — curăță sesiunea și redirecționează la login */
    public function logout(string $dir = ''): void
    {
        SessionAuth::logout();
        SessionAuth::redirectToLogin();
    }

    /** GET /admin/workspace — portal departamente (pagină izolată, ca login) */
    public function workspace(string $dir = ''): void
    {
        if (empty($_SESSION['user_id'])) {
            SessionAuth::redirectToLogin();
        }

        $file = dirname(__DIR__, 2) . '/Templates/admin/pages/workspace/workspace.php';
        if (!is_file($file)) {
            http_response_code(500);
            echo 'Pagina workspace lipsește.';
            return;
        }

        require $file;
    }

    /** POST /admin/workspace — setează departamentul activ */
    public function workspaceSet(string $dir = ''): void
    {
        if (empty($_SESSION['user_id'])) {
            SessionAuth::redirectToLogin();
        }

        $workspaceId = trim((string) ($_POST['workspace'] ?? ''));
        $allowed = \Evasystem\Core\Auth\AdminWorkspace::allowedWorkspacesForSession();

        if ($workspaceId === '' || !in_array($workspaceId, $allowed, true)) {
            http_response_code(400);
            echo 'Departament invalid.';
            return;
        }

        \Evasystem\Core\Auth\AdminWorkspace::setCurrent($workspaceId);
        $redirect = \Evasystem\Core\Auth\AdminWorkspaceCatalog::dashboardPath($workspaceId);

        if (!headers_sent()) {
            header('Location: ' . $redirect, true, 302);
            exit;
        }
        echo '<script>location.href="' . htmlspecialchars($redirect, ENT_QUOTES, 'UTF-8') . '";</script>';
    }

    /** GET /admin/workspace-switch?to=orders — schimbă departamentul fără portal */
    public function workspaceSwitch(string $dir = ''): void
    {
        if (empty($_SESSION['user_id'])) {
            SessionAuth::redirectToLogin();
        }

        $workspaceId = trim((string) ($_GET['to'] ?? $_GET['workspace'] ?? ''));
        $allowed = \Evasystem\Core\Auth\AdminWorkspace::allowedWorkspacesForSession();

        if ($workspaceId === '' || !in_array($workspaceId, $allowed, true)) {
            if (!headers_sent()) {
                header('Location: ' . AdminUrl::path('workspace') . '?force=1', true, 302);
                exit;
            }
            return;
        }

        \Evasystem\Core\Auth\AdminWorkspace::setCurrent($workspaceId);
        $redirect = \Evasystem\Core\Auth\AdminWorkspaceCatalog::dashboardPath($workspaceId);

        if (!headers_sent()) {
            header('Location: ' . $redirect, true, 302);
            exit;
        }
        echo '<script>location.href="' . htmlspecialchars($redirect, ENT_QUOTES, 'UTF-8') . '";</script>';
    }

    public function getDir(): string { return $this->dir; }
    public function setDir(string $dir): void { $this->dir = $dir ?: $this->dir; }

    /** ÎNLOCUIT: randează pagini pe baza rutei GET din DB */
    public function index(string $dir = ''): void
    {
        [$method, $path, $slug] = $this->getRequest();
        // Forțăm GET aici, index e pentru pagini
        $route = $this->findRoute('GET', $path, $slug);

        if ($this->redirectStubPage($path, $slug)) {
            return;
        }

        // dir din DB sau parametru
        $this->dir = $dir ?: ($route['dir'] ?? '/admin/Templates/admin/pages/');
        $this->renderFromRoute($route, $slug);
    }

    /** ÎNLOCUIT: execută rootFunction (CRUD/API) pe baza rutei POST din DB */
    public function rootFunction(string $dir = ''): void
    {
        [$method, $path, $slug] = $this->getRequest();
        // rootFunction e, în mod normal, POST
        $route = $this->findRoute('POST', $path, $slug) ?? $this->findRoute($method, $path, $slug);

        if (!$route) {
            http_response_code(404);
            echo json_encode(['success'=>false, 'message'=>'Rută neconfigurată în DB']);
            return;
        }

        $action   = $route['action']    ?? '';
        $loadType = $route['load_type'] ?? '';

        if ($action === 'rootFunction' || $loadType === 'rootFunction') {
            $file = $this->docrootPath($route['dir'] ?? '');
            if (!$file || !is_file($file)) {
                http_response_code(500);
                echo json_encode(['success'=>false, 'message'=>'Fișier rootFunction inexistent: '.($route['dir'] ?? '')]);
                return;
            }
            $this->executeJsonRootFunction($file);
            return;
        }

        // Dacă nu e rootFunction, îl tratăm ca pagină
        $this->dir = $route['dir'] ?? $this->dir;
        $this->renderFromRoute($route, $slug);
    }

    /** Execută crudu/API rootFunction — JSON curat, fără warning PHP în output. */
    private function executeJsonRootFunction(string $absoluteFilePath): void
    {
        if (class_exists(\Evasystem\Core\Bootstrap\ApiBootstrap::class)) {
            \Evasystem\Core\Bootstrap\ApiBootstrap::registerProductionSafeErrors();
        } else {
            ini_set('display_errors', '0');
            ini_set('display_startup_errors', '0');
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        ob_start();

        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }

        require $absoluteFilePath;

        $output = ob_get_clean();
        if ($output !== false && $output !== '') {
            echo $output;
        }
    }

    /* ================== PRIVATE: rendering pagini ================== */

    private function renderFromRoute(?array $route, string $slug): void
    {
        $redirector  = new Redirector();
        $currentPage = $redirector->thispagesurl(); // ex: cron, scan, login

        $loadType = $route['load_type'] ?? 'loadPage';

        // Slug din rută are prioritate (evită index.php când există folder fizic admin/cron/)
        $pageKey = $slug !== '' ? AdminUrl::resolvePageKey($slug) : $currentPage;
        if ($pageKey === '' || $pageKey === 'index' || $pageKey === 'index.php') {
            $pageKey = $currentPage !== '' ? $currentPage : 'index';
        }
        $pageDirectory = $this->normalizeDirLike($this->getDir() ?: '/admin/Templates/admin/pages/');
        $resolvedTemplate = AdminPageResolver::resolveTemplate($pageKey, $pageDirectory);

        if ($resolvedTemplate !== null) {
            $pageLoader = new PageLoader([
                $pageKey => $resolvedTemplate,
                AdminUrl::resolvePageKey($pageKey) => $resolvedTemplate,
            ]);
        } else {
            $slugForFile = AdminUrl::resolvePageKey($pageKey);
            $base = rtrim($pageDirectory, '/');
            $fallback = $base . '/' . $slugForFile . '/' . $slugForFile . '.php';
            $pageLoader = new PageLoader([
                $pageKey => $fallback,
                $slugForFile => $fallback,
            ]);
        }

        // dacă vrei să forțezi simplepag pentru anume rute, pune 'simplepag' în DB
        if ($currentPage === 'login' || $loadType === 'simplepag' ) {
            $pageLoader->simplepag();
        } else {
            $pageLoader->loadPage();
        }
    }

    /* ================== PRIVATE: routing din DB ================== */

    /**
     * Redirect HTTP înainte de layout — pentru stub-uri add/profile.
     * Nu folosi header() din fișiere incluse în Templates (headers already sent).
     */
    private function redirectStubPage(string $path, string $slug): bool
    {
        $stubPairs = [
            ['addsearch-logs', 'searchlogs'],
            ['profilesearch-logs', 'searchlogs'],
            ['addscan', 'cron'],
            ['profilescan', 'cron'],
            ['addcron', 'cron'],
            ['profilecron', 'cron'],
            ['addalerts', 'alerts'],
            ['profilealerts', 'alerts'],
            ['addreport', 'reports'],
            ['profilereport', 'reports'],
            ['addcross-reference', 'cross-reference'],
            ['profilecross-reference', 'cross-reference'],
            ['addfurnizori', 'suppliers'],
            ['profileproduse', 'product'],
            ['addclienti', 'clienti'],
            ['profileclienti', 'clienti'],
            ['addfacturi', 'facturi'],
            ['profilefacturi', 'facturi'],
            ['addmessages', 'messages'],
            ['profilemessages', 'messages'],
            ['addbots', 'bots'],
            ['profilebots', 'bots'],
            ['addmarketplace', 'marketplace'],
            ['profilemarketplace', 'marketplace'],
            ['addsettings', 'settings'],
            ['profilesettings', 'settings'],
            ['addblog', 'blog'],
            ['profileblog', 'blog'],
        ];

        $caietOrderRedirects = [
            'caietcomenzi' => 'tm',
            'caiet-de-comenzi' => 'tm',
            'comenzi-tm' => 'tm',
            'comenzi-utvin' => 'utvin',
            'comenzi-externe' => 'ext',
        ];

        $targets = [];
        foreach ($stubPairs as [$stub, $target]) {
            $targets[AdminUrl::LEGACY_PREFIX . '/' . $stub] = AdminUrl::path($target);
            $targets[AdminUrl::path($stub)] = AdminUrl::path($target);
        }

        foreach ($caietOrderRedirects as $slug => $legacyTab) {
            $redirect = AdminUrl::path('orders') . '?legacy_tab=' . rawurlencode($legacyTab);
            $targets[AdminUrl::LEGACY_PREFIX . '/' . $slug] = $redirect;
            $targets[AdminUrl::path($slug)] = $redirect;
        }

        if (isset($targets[$path])) {
            header('Location: ' . $targets[$path], true, 302);
            exit;
        }

        return false;
    }

    private function getRequest(): array
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $path   = AdminUrl::currentRequestPath();
        $slug   = $path === '/' ? '' : trim(basename($path), '/');
        return [$method, $path, $slug];
    }

    private function findRoute(string $method, string $path, ?string $slug = null): ?array
    {
        $pdo = Database::getDB();
        $method = strtoupper($method);

        foreach (AdminUrl::alternatePaths($path) as $tryPath) {
            $stmt = $pdo->prepare("SELECT * FROM routes WHERE is_active=1 AND method=:m AND path=:p LIMIT 1");
            $stmt->execute([':m' => $method, ':p' => $tryPath]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($row) {
                return $row;
            }
        }

        // 2) fallback pe basename(path) = slug
        if ($slug) {
            $stmt = $pdo->prepare("SELECT * FROM routes WHERE is_active=1 AND method=:m");
            $stmt->execute([':m' => $method]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            foreach ($rows as $r) {
                $base = trim(basename($r['path'] ?? ''), '/');
                if ($base !== '' && $base === $slug) return $r;
            }
        }

        return null;
    }

    /* ================== PRIVATE: căi & securitate ================== */

    private function normalizeDirLike(string $d): string
    {
        // Acceptă valori ca './webproject/index', '/Templates/admin/pages/firms/' etc.
        $d = trim($d);
        if ($d === '') return '/admin/Templates/admin/pages/';
        $d = str_replace('\\','/',$d);
        // scoate prefixul './'
        if (str_starts_with($d, './')) $d = substr($d, 1);
        return $d;
    }

    private function docrootPath(string $rel, bool $mustExist = true): ?string
    {
        $rel  = $this->normalizeDirLike($rel);
        $root = $this->documentRoot();
        if ($root === '') {
            return null;
        }

        $path = $root . '/' . ltrim($rel, '/');

        $real = realpath($path);
        if ($real === false) return $mustExist ? null : $path;
        if (strpos($this->normalizePathForCompare($real), $this->normalizePathForCompare($root)) !== 0) return null; // securitate
        return $real;
    }

    private function resolveFileFromDir(string $dirLike, string $slug): ?string
    {
        // dacă $dirLike e fișier -> întoarce-l; dacă e director -> caută <slug>.php sau index.php
        $maybeFile = $this->docrootPath($dirLike);
        if ($maybeFile && is_file($maybeFile)) return $maybeFile;

        $maybeDir  = $this->docrootPath(rtrim($dirLike,'/').'/', false);
        if ($maybeDir && is_dir($maybeDir)) {

            $cand1 = rtrim($maybeDir,'/').'/'.$slug.'.php';
            $cand2 = rtrim($maybeDir,'/').'/index.php';
            if (is_file($cand1)) return $cand1;
            if (is_file($cand2)) return $cand2;
        }
        return null;
    }

    private function templateFileExists(string $file): bool
    {
        $path = $this->docrootPath($file);
        return is_string($path) && is_file($path);
    }

    private function documentRoot(): string
    {
        $root = $_SERVER['DOCUMENT_ROOT'] ?? '';
        if (!is_string($root) || trim($root) === '') {
            return '';
        }

        $real = realpath($root);
        return $real !== false ? $real : rtrim(str_replace('\\', '/', $root), '/');
    }

    private function normalizePathForCompare(string $path): string
    {
        return strtolower(rtrim(str_replace('\\', '/', $path), '/'));
    }
}
