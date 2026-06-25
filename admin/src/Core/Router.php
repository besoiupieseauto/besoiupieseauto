<?php

declare(strict_types=1);

namespace Evasystem\Core;

use Evasystem\Core\PageLoader;
use RuntimeException;
use Evasystem\Core\AdminUrl;

/**
 * Router HTTP — potrivește method + path și delegă Controller sau PageLoader.
 */
final class Router
{
    /** @var list<array{method:string,route:string,controller:?string,action:?string,dir:string}> */
    private array $registeredRoutes = [];

    /**
     * @param string|null $controllerName Null = fallback PageLoader via $templateDirectory
     */
    public function addRoute(
        string $httpMethod,
        string $routePath,
        ?string $controllerName = null,
        ?string $actionName = null,
        string $templateDirectory = ''
    ): void {
        // Semnătură scurtă legacy: addRoute(method, path, templateDirectory)
        if (func_num_args() === 3) {
            $templateDirectory = (string) $controllerName;
            $controllerName = 'Admin';
            $actionName = 'rootFunction';
        }

        $this->registeredRoutes[] = [
            'method' => $httpMethod,
            'route' => $routePath,
            'controller' => $controllerName,
            'action' => $actionName,
            'dir' => $templateDirectory,
        ];
    }

    public function loadRoutesFromDatabase(): void
    {
        $routeRows = AdvancedCRUD::select('routes', '*', 'WHERE is_active = 1');

        foreach ($routeRows as $routeRow) {
            $this->addRoute(
                (string) $routeRow['method'],
                (string) $routeRow['path'],
                (string) ($routeRow['controller'] ?? '') ?: null,
                (string) ($routeRow['action'] ?? '') ?: null,
                (string) ($routeRow['dir'] ?? '')
            );
        }
    }

    public function handleRequest(): void
    {
        $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $requestPath = AdminUrl::currentRequestPath();

        $pathsToTry = AdminUrl::alternatePaths($requestPath);

        foreach ($pathsToTry as $tryPath) {
            foreach ($this->registeredRoutes as $routeDefinition) {
                if ($routeDefinition['method'] !== $requestMethod) {
                    continue;
                }

                if ($routeDefinition['route'] !== $tryPath) {
                    continue;
                }

                $this->dispatchMatchedRoute($routeDefinition);
                return;
            }
        }

        $this->handleStaticPageFallback($requestPath);
    }

    /** @param array{method:string,route:string,controller:?string,action:?string,dir:string} $routeDefinition */
    private function dispatchMatchedRoute(array $routeDefinition): void
    {
        $controllerName = $routeDefinition['controller'];
        $actionName = $routeDefinition['action'];
        $templateDirectory = $routeDefinition['dir'];

        if ($controllerName !== null && $controllerName !== '' && $actionName !== null && $actionName !== '') {
            $controllerClass = 'Evasystem\\Controllers\\' . $controllerName;

            if (!class_exists($controllerClass)) {
                throw new RuntimeException("Controller inexistent: {$controllerClass}");
            }

            $controllerInstance = new $controllerClass();

            if (!method_exists($controllerInstance, $actionName)) {
                throw new RuntimeException("Metodă inexistentă {$actionName} în {$controllerClass}");
            }

            $controllerInstance->{$actionName}($templateDirectory);
            return;
        }

        $this->handleStaticPageFallback($routeDefinition['route']);
    }

    private function handleStaticPageFallback(string $requestUri): void
    {
        $normalizedPath = trim($requestUri, '/');
        $lookupPath = $normalizedPath === '' ? '/' : '/' . $normalizedPath;

        $routeRows = AdvancedCRUD::selectnew(
            'routes',
            '*',
            'WHERE path = :routePath AND is_active = 1',
            '',
            null,
            ['routePath' => $lookupPath]
        );

        if ($routeRows === []) {
            $this->renderNotFound();
            return;
        }

        $routeRow = $routeRows[0];
        $filePath = (string) ($routeRow['file_path'] ?? '');
        $loadType = (string) ($routeRow['load_type'] ?? '');

        if ($filePath === '' || $loadType === '') {
            $this->renderNotFound();
            return;
        }

        $pageLoader = new PageLoader([trim($lookupPath, '/') => $filePath]);

        if (!method_exists($pageLoader, $loadType)) {
            error_log("[EvaSystem][Router] Metodă PageLoader inexistentă: {$loadType}");
            $this->renderNotFound();
            return;
        }

        $pageLoader->{$loadType}();
    }

    private function renderNotFound(): void
    {
        http_response_code(404);
        echo '<h1>404 - Pagină inexistentă</h1>';
    }
}
