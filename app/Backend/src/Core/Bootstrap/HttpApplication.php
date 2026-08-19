<?php
/*
 * ============================================================================
 * FIȘIER: app/Backend/src/Core/Bootstrap/HttpApplication.php
 * ============================================================================
 * Scop: Orchestrator principal al panoului admin. Gestionează întregul ciclu
 *       de viață al unui request HTTP: erori dev, sesiune, DB, module opționale,
 *       permisiuni, rute din DB + bootstrap, dispatch către controller.
 *
 * Include/require (indirect):
 *   - system/session-bridge.php sau app/Legacy/session-bridge.php
 *   - system/api_automation_guard.php — dezarmare API automation
 *   - system/preview-gate.php — protecție preview
 *   - admin/config/public_paths.php — rute publice (login)
 *   - admin/config/routes_bootstrap.php — rute critice hardcodate
 *
 * Bază de date: PDO MySQL via Config\Database (default + legacy lazy).
 *   Interogări: SELECT pe tabelul `routes` (is_active=1), roles via RolesRepository.
 * ============================================================================
 */

declare(strict_types=1);

namespace Besoiu\Core\Bootstrap;

use Config\Database;
use Besoiu\Core\AppCache;
use Besoiu\Core\AdvancedCRUD;
use Besoiu\Core\Auth\AdminWorkspace;
use Besoiu\Core\Auth\Permission;
use Besoiu\Core\Auth\RolesRepository;
use Besoiu\Core\Exception\DatabaseConnectionException;
use Besoiu\Core\AdminUrl;
use Besoiu\Core\Module\ModuleGate;
use Besoiu\Core\Module\ModuleRegistry;
use Besoiu\Core\Router;

/**
 * Orchestrare request HTTP admin — Front Controller delegă aici.
 * Flux: env → sesiune → DB → module → permisiuni → rute → Router::handleRequest()
 */
final class HttpApplication
{
    /** @var array<string, mixed> Configurare aplicație (db_host, db_name, db_user, db_pass, legacy_*) */
    private array $applicationConfig;

    /**
     * @param array<string, mixed> $applicationConfig Parametri DB și legacy din config.php
     */
    public function __construct(array $applicationConfig)
    {
        $this->applicationConfig = $applicationConfig;
    }

    /**
     * Pornește procesarea request-ului admin în ordinea corectă.
     * Nu returnează valoare; trimite răspuns HTTP via Router.
     */
    public function run(): void
    {
        $this->registerDevelopmentErrorDisplay();
        $this->startSession();
        $this->initializeDatabaseConnections();
        $this->bootOptionalModules();
        $this->dispatchHttpRequest();
    }

    /**
     * Încarcă module opționale din admin/modules/{Id}/module.json.
     * Eșecul unui modul nu oprește adminul (catch Throwable).
     */
    private function bootOptionalModules(): void
    {
        try {
            ModuleRegistry::instance()->boot();
        } catch (\Throwable) {
            // Registry nu trebuie să blocheze Adminul.
        }
    }

    /**
     * Activează afișarea erorilor PHP în mediul de dezvoltare.
     * În producție (APP_ENV=production) nu modifică setările implicite.
     */
    private function registerDevelopmentErrorDisplay(): void
    {
        // Citește mediul din .env; implicit development
        $applicationEnvironment = $_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: 'development';

        // În producție nu afișăm erori pe ecran
        if ($applicationEnvironment === 'production') {
            return;
        }

        ini_set('display_errors', '1');
        ini_set('display_startup_errors', '1');
        error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
    }

    /**
     * Pornește sesiunea admin și încarcă guard-urile de securitate.
     * Modifică $_SESSION via besoiu_admin_session_start().
     */
    private function startSession(): void
    {
        // Găsește fișierul bridge pentru sesiune (system/ sau Legacy/)
        $sessionBridge = $this->resolveSystemFile('session-bridge.php');
        if ($sessionBridge === null) {
            throw new \RuntimeException('Lipsește system/session-bridge.php (sau app/Legacy/session-bridge.php).');
        }
        require_once $sessionBridge;
        besoiu_admin_session_start();

        // Guard: forțează dezarmarea API automation la fiecare request
        $guardPath = $this->resolveSystemFile('api_automation_guard.php');
        if ($guardPath !== null) {
            require_once $guardPath;
            besoiu_api_automation_force_disarmed();
        }

        // Gate: restricționează accesul preview dacă e activ
        $previewGate = $this->resolveSystemFile('preview-gate.php');
        if ($previewGate !== null) {
            require_once $previewGate;
            besoiu_preview_gate_enforce();
        }
    }

    /** @return string Calea absolută către rădăcina proiectului */
    private function projectRoot(): string
    {
        if (defined('BESOIU_ROOT')) {
            return (string) BESOIU_ROOT;
        }

        return dirname(__DIR__, 4);
    }

    /**
     * Caută un fișier system în system/ sau app/Legacy/.
     *
     * @return string|null Calea absolută sau null dacă nu există
     */
    private function resolveSystemFile(string $filename): ?string
    {
        $root = $this->projectRoot();
        $candidates = [
            $root . '/system/' . $filename,
            $root . '/app/Legacy/' . $filename,
        ];

        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Eliberează lock-ul sesiunii pe GET pentru pagini lungi, dar păstrează sesiunea
     * deschisă pe rute care scriu $_SESSION (ex. schimbare departament).
     */
    private function shouldReleaseSessionLock(string $requestMethod, string $requestPath): bool
    {
        if ($requestMethod !== 'GET') {
            return false;
        }

        if (str_contains($requestPath, '/api/') || str_contains($requestPath, '/public/import-pro/api/')) {
            return false;
        }

        foreach ([AdminUrl::path('workspace-switch'), AdminUrl::path('workspace')] as $sessionWritePath) {
            $normalized = rtrim($sessionWritePath, '/') ?: '/';
            if ($requestPath === $normalized || str_starts_with($requestPath, $normalized . '/')) {
                return false;
            }
        }

        return true;
    }

    /**
     * Deschide conexiunea PDO principală și legacy (dacă URL-ul o cere).
     * La eșec returnează HTTP 503 și oprește execuția.
     */
    private function initializeDatabaseConnections(): void
    {
        try {
            // Conexiune default: parametrii din config (host, nume bază, user, parolă)
            Database::getInstance(
                (string) $this->applicationConfig['db_host'],
                (string) $this->applicationConfig['db_name'],
                (string) $this->applicationConfig['db_user'],
                (string) $this->applicationConfig['db_pass']
            );

            // Conexiune legacy lazy — doar pentru rute caiet comenzi / orders
            if (Database::requestNeedsLegacy()) {
                Database::ensureLegacy($this->applicationConfig);
            }
        } catch (DatabaseConnectionException $exception) {
            http_response_code(503);
            echo 'Serviciul este temporar indisponibil.';
            exit;
        }
    }

    /**
     * Verifică permisiunile, încarcă rutele din DB și delegă către Router.
     * Folosește $_SESSION['user_id'], $_SESSION['role'], $_SERVER['REQUEST_METHOD'].
     */
    private function dispatchHttpRequest(): void
    {
        if ($this->dispatchImagineVerificareIfRequested()) {
            return;
        }

        // Încarcă rolurile din DB cu cache 300 secunde
        $rolesConfiguration = AppCache::remember(
            'roles_all_v1',
            300,
            static fn (): array => RolesRepository::loadAll()
        );

        $permissionGuard = new Permission($rolesConfiguration);

        // Metoda și calea request-ului curent
        $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $requestPath = AdminUrl::currentRequestPath();

        // Redirect-uri compatibilitate URL-uri vechi admin
        AdminUrl::redirectLegacyIfNeeded($requestPath);

        /** @var list<string> $publicPaths */
        // Rute accesibile fără autentificare (login, logout, etc.)
        $publicPaths = require dirname(__DIR__, 3) . '/config/public_paths.php';
        $permissionGuard->setPublicPaths($publicPaths);
        $permissionGuard->guard($requestMethod, $requestPath);

        if (
            $this->shouldReleaseSessionLock($requestMethod, $requestPath)
            && session_status() === PHP_SESSION_ACTIVE
        ) {
            session_write_close();
        }

        if (!ModuleGate::pathAllowed($requestPath)) {
            $this->renderModuleGateDenied($requestPath);

            return;
        }

        // Dacă utilizatorul e autentificat, aplică restricțiile workspace-ului
        if (!empty($_SESSION['user_id'])) {
            AdminWorkspace::enforce($requestPath, $requestMethod);
        }

        // Expune rolul și guard-ul permisiuni în GLOBALS pentru template-uri
        $userRole = $_SESSION['role'] ?? 'guest';
        $GLOBALS['APP_ROLE'] = $userRole;
        $GLOBALS['APP_PERM'] = $permissionGuard;
        $GLOBALS['APP_NAVTREE'] = [];

        // SELECT * FROM routes WHERE is_active = 1 — cache 120 secunde
        $activeRoutes = AppCache::remember(
            'routes_active_v1',
            120,
            static fn (): array => AdvancedCRUD::select('routes', '*', 'WHERE is_active = 1')
        );

        // Index rapid al căilor active pentru verificări permisiuni
        $routesIndex = [];
        foreach ($activeRoutes as $routeRow) {
            $routePath = (string) ($routeRow['path'] ?? '');
            if ($routePath !== '') {
                $routesIndex[$routePath] = 1;
            }
        }
        $permissionGuard->setRoutesIndex($routesIndex);

        $router = new Router();

        // Rute bootstrap (login/logout) — înregistrate înainte de rutele din DB
        $this->registerBootstrapRoutes($router);

        // Înregistrează fiecare rută activă din tabelul `routes`
        foreach ($activeRoutes as $routeRow) {
            $routePath = (string) ($routeRow['path'] ?? '');
            // Sari peste rutele modulelor dezactivate
            if ($routePath !== '' && !ModuleGate::pathAllowed($routePath)) {
                continue;
            }
            $this->registerRouteFromDatabaseRow($router, $routeRow);
        }

        // Potrivește request-ul curent și execută controllerul
        $router->handleRequest();
    }

    /**
     * Adaugă o rută din rândul tabelului `routes` în router.
     *
     * @param array<string, mixed> $routeRow Coloane: method, path, controller, action, dir
     */
    private function registerRouteFromDatabaseRow(Router $router, array $routeRow): void
    {
        $httpMethod = (string) ($routeRow['method'] ?? 'GET');
        $routePath = (string) ($routeRow['path'] ?? '');
        $controllerName = (string) ($routeRow['controller'] ?? '');
        $actionName = (string) ($routeRow['action'] ?? '');
        $templateDirectory = (string) ($routeRow['dir'] ?? '');

        if ($routePath === '') {
            return;
        }

        // Rută fără controller = doar template static
        if ($controllerName === '' || $actionName === '') {
            $router->addRoute($httpMethod, $routePath, $templateDirectory);
            return;
        }

        $router->addRoute(
            $httpMethod,
            $routePath,
            $controllerName,
            $actionName,
            $templateDirectory
        );
    }

    /**
     * Încarcă rutele critice din routes_bootstrap.php (prioritate față de DB).
     */
    private function registerBootstrapRoutes(Router $router): void
    {
        /** @var list<array{0:string,1:string,2:string,3:string,4:string}> $bootstrapRoutes */
        $bootstrapRoutes = require dirname(__DIR__, 3) . '/config/routes_bootstrap.php';

        foreach ($bootstrapRoutes as $routeDefinition) {
            [$httpMethod, $routePath, $controllerName, $actionName, $templateDirectory] = $routeDefinition;
            $router->addRoute(
                $httpMethod,
                $routePath,
                $controllerName,
                $actionName,
                $templateDirectory
            );
        }
    }

    private function dispatchImagineVerificareIfRequested(): bool
    {
        $path = strtolower((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH));
        $path = rtrim($path, '/');
        $publicDir = dirname(__DIR__, 3) . '/public';
        if (preg_match('#/(imagine[-_]verificare-img)(\.php)?$#', $path)) {
            require $publicDir . '/imagine-verificare-img.php';

            return true;
        }
        if (preg_match('#/(imagine[-_]verificare)(\.php)?$#', $path)) {
            require $publicDir . '/imagine-verificare.php';

            return true;
        }

        return false;
    }

    private function renderModuleGateDenied(string $requestPath): void
    {
        error_log('[admin-module-gate] blocked ' . $requestPath);

        // Module neportate încă — redirect la dashboard în loc de 404 gol.
        $slug = strtolower(trim((string) basename(parse_url($requestPath, PHP_URL_PATH) ?: ''), '/'));
        $retired = [
            'orders', 'order-create', 'abandoned-carts', 'facturi', 'livrare',
            'bots', 'blog', 'marketplace', 'messages', 'comunicare', 'cron',
            'searchlogs', 'export', 'planner', 'ai-tokens',
            'caietcomenzi', 'comenzi-tm', 'comenzi-utvin', 'comenzi-externe',
        ];
        if ($slug !== '' && in_array($slug, $retired, true)) {
            header('Location: /admin/dashboard', true, 302);
            exit;
        }

        http_response_code(404);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html lang="ro"><head><meta charset="utf-8"><title>404</title></head><body>';
        echo '<h1>404 — Pagină indisponibilă</h1>';
        echo '<p>Funcționalitatea nu este activă. Instalează sau activează modulul corespunzător din ';
        echo '<a href="/admin/settings">Setări → Module</a>.</p>';
        echo '</body></html>';
    }
}
