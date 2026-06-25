<?php

declare(strict_types=1);

namespace Evasystem\Core\Bootstrap;

use Config\Database;
use Evasystem\Core\AppCache;
use Evasystem\Core\AdvancedCRUD;
use Evasystem\Core\Auth\AdminWorkspace;
use Evasystem\Core\Auth\Permision;
use Evasystem\Core\Auth\RolesRepository;
use Evasystem\Core\Exception\DatabaseConnectionException;
use Evasystem\Core\AdminUrl;
use Evasystem\Core\Router;

/**
 * Orchestrare request HTTP — Front Controller delegă aici.
 * Flux: env → DB → permisiuni → rute → Router::handleRequest()
 */
final class HttpApplication
{
    /** @var array<string, mixed> */
    private array $applicationConfig;

    /** @param array<string, mixed> $applicationConfig */
    public function __construct(array $applicationConfig)
    {
        $this->applicationConfig = $applicationConfig;
    }

    public function run(): void
    {
        $this->registerDevelopmentErrorDisplay();
        $this->startSession();
        $this->initializeDatabaseConnections();
        $this->dispatchHttpRequest();
    }

    private function registerDevelopmentErrorDisplay(): void
    {
        $applicationEnvironment = $_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: 'development';

        if ($applicationEnvironment === 'production') {
            return;
        }

        ini_set('display_errors', '1');
        ini_set('display_startup_errors', '1');
        error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
    }

    private function startSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $previewGate = dirname(__DIR__, 4) . '/system/preview-gate.php';
        if (is_file($previewGate)) {
            require_once $previewGate;
            besoiu_preview_gate_enforce();
        }
    }

    private function initializeDatabaseConnections(): void
    {
        try {
            Database::getInstance(
                (string) $this->applicationConfig['db_host'],
                (string) $this->applicationConfig['db_name'],
                (string) $this->applicationConfig['db_user'],
                (string) $this->applicationConfig['db_pass']
            );

            if (Database::requestNeedsLegacy()) {
                Database::ensureLegacy($this->applicationConfig);
            }
        } catch (DatabaseConnectionException $exception) {
            http_response_code(503);
            echo 'Serviciul este temporar indisponibil.';
            exit;
        }
    }

    private function dispatchHttpRequest(): void
    {
        $rolesConfiguration = AppCache::remember(
            'roles_all_v1',
            300,
            static fn (): array => RolesRepository::loadAll()
        );

        $permissionGuard = new Permision($rolesConfiguration);

        $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $requestPath = AdminUrl::currentRequestPath();

        AdminUrl::redirectLegacyIfNeeded($requestPath);

        /** @var list<string> $publicPaths */
        $publicPaths = require dirname(__DIR__, 3) . '/config/public_paths.php';
        $permissionGuard->setPublicPaths($publicPaths);
        $permissionGuard->guard($requestMethod, $requestPath);

        if (!empty($_SESSION['user_id'])) {
            AdminWorkspace::enforce($requestPath, $requestMethod);
        }

        $userRole = $_SESSION['role'] ?? 'guest';
        $GLOBALS['APP_ROLE'] = $userRole;
        $GLOBALS['APP_PERM'] = $permissionGuard;
        $GLOBALS['APP_NAVTREE'] = [];

        $activeRoutes = AppCache::remember(
            'routes_active_v1',
            120,
            static fn (): array => AdvancedCRUD::select('routes', '*', 'WHERE is_active = 1')
        );

        $routesIndex = [];
        foreach ($activeRoutes as $routeRow) {
            $routePath = (string) ($routeRow['path'] ?? '');
            if ($routePath !== '') {
                $routesIndex[$routePath] = 1;
            }
        }
        $permissionGuard->setRoutesIndex($routesIndex);

        $router = new Router();

        // Bootstrap înainte de DB — rute critice (logout, login) nu sunt suprascrise de intrări incomplete din `routes`.
        $this->registerBootstrapRoutes($router);

        foreach ($activeRoutes as $routeRow) {
            $this->registerRouteFromDatabaseRow($router, $routeRow);
        }

        $router->handleRequest();
    }

    /** @param array<string, mixed> $routeRow */
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
}
