<?php
/*
 * ============================================================================
 * FIȘIER: app/Core/Router/StorefrontRouter.php
 * ============================================================================
 * Scop: Potrivește URL-ul request-ului cu o rută definită și execută
 *       controllerul paginii corespunzător. Returnează 404 dacă nu găsește rută.
 *
 * Include/require: Niciun fișier direct; folosește ModuleRegistry pentru controller.
 *
 * Bază de date: Nu interoghează DB; controllerul apelat poate face asta.
 * ============================================================================
 */

declare(strict_types=1);

namespace Storefront\Core\Router;

use Storefront\Core\Bootstrap\StorefrontRequest;
use Storefront\Core\Module\ModuleRegistry;
use Storefront\Core\Module\PageControllerInterface;

/**
 * Router storefront — match path → Controller::handle()
 */
final class StorefrontRouter
{
    /**
     * @param list<RouteDefinition> $routes   Lista rutelor statice din storefront_routes.php
     * @param ModuleRegistry $modules         Registry pentru instanțierea controllerelor
     */
    public function __construct(
        private readonly array $routes,
        private readonly ModuleRegistry $modules,
    ) {
    }

    /**
     * Găsește prima rută potrivită și execută controllerul ei.
     *
     * @param StorefrontRequest $request Request normalizat (metodă, path, query)
     * Returnează 404 HTML dacă niciuna din rute nu se potrivește.
     */
    public function dispatch(StorefrontRequest $request): void
    {
        // Parcurge rutele în ordinea definită în config
        foreach ($this->routes as $route) {
            if (!$this->matches($route, $request)) {
                continue;
            }

            // Instanțiază controllerul din modulul asociat rutei
            $controller = $this->modules->makeController($route->controllerClass);
            if (!$controller instanceof PageControllerInterface) {
                continue;
            }

            // Execută logica paginii și termină dispatch-ul
            $controller->handle($request);
            return;
        }

        // Nicio rută potrivită — răspuns 404 generic în română
        http_response_code(404);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html lang="ro"><head><meta charset="utf-8"><title>404</title></head>';
        echo '<body><main style="font-family:sans-serif;padding:2rem"><h1>Pagina nu există</h1>';
        echo '<p><a href="/">Înapoi acasă</a></p></main></body></html>';
    }

    /**
     * Verifică dacă o rută se potrivește cu request-ul (metodă + path).
     *
     * @return bool true dacă metoda și calea coincid (sau e pagină CMS /p/...)
     */
    private function matches(RouteDefinition $route, StorefrontRequest $request): bool
    {
        // Metoda HTTP trebuie să coincidă exact (GET, POST, etc.)
        if (strtoupper($route->method) !== $request->method) {
            return false;
        }

        // Potrivire exactă a path-ului
        if ($route->path === $request->path) {
            return true;
        }

        // Regulă specială CMS: orice URL /p/{slug} e gestionat de modulul cms
        if ($route->moduleId === 'cms' && str_starts_with($request->path, '/p/') && strlen($request->path) > 3) {
            return true;
        }

        return false;
    }
}
