<?php
/*
 * ============================================================================
 * FIȘIER: app/Core/Bootstrap/StorefrontApplication.php
 * ============================================================================
 * Scop: Clasa principală (front controller) a vitrinei publice. Orchestrează
 *       încărcarea temei, modulelor, rutelor și dispatch-ul request-ului HTTP.
 *       Este instanțiată și apelată din index.php la fiecare vizită pe site.
 *
 * Include/require (indirect):
 *   - app/Config/storefront_routes.php — lista rutelor statice ale vitrinei
 *   - Clase Router, ModuleRegistry, ThemeRenderer (autoload din app/Core/)
 *
 * Bază de date: Nu se conectează direct; modulele individuale folosesc
 *               Storefront\Core\Database\Connection când au nevoie de date.
 * ============================================================================
 */

declare(strict_types=1);

namespace Storefront\Core\Bootstrap;

use Storefront\Core\Module\ModuleRegistry;
use Storefront\Core\Router\RouteDefinition;
use Storefront\Core\Router\StorefrontRouter;
use Storefront\Core\Template\ThemeRenderer;

/**
 * Aplicația vitrină — punct central de orchestrare pentru request-urile publice.
 */
final class StorefrontApplication
{
    /**
     * Pornește procesarea request-ului HTTP curent.
     *
     * Flux: temă → module → rute → request → dispatch router.
     * Nu primește parametri; citește datele din superglobalii PHP ($_SERVER).
     * Nu returnează valoare — trimite răspunsul HTTP direct (echo/headers).
     * Nu modifică baza de date; doar delegă către controllerul rutei potrivite.
     */
    public function run(): void
    {
        // Renderer pentru template-uri HTML ale temei active
        $theme = new ThemeRenderer();

        // Registry care descoperă și încarcă modulele din app/Modules/
        $modules = new ModuleRegistry(BESOIU_STOREFRONT_MODULES, $theme);

        /** @var list<array{0:string,1:string,2:string,3:string,4?:string}> $routeRows */
        // Încarcă definițiile de rute statice din fișierul de configurare
        $routeRows = require BESOIU_CONFIG . '/storefront_routes.php';

        // Transformă fiecare rând din config în obiect RouteDefinition
        $routes = [];
        foreach ($routeRows as $row) {
            $routes[] = new RouteDefinition(
                $row[0],           // Metoda HTTP (GET, POST, etc.)
                $row[1],           // Calea URL (ex. /blog)
                $row[2],           // Controller sau handler
                $row[3],           // Acțiune/metodă
                $row[4] ?? '',     // Director template (opțional)
            );
        }

        // Router care potrivește URL-ul curent cu o rută și execută handlerul
        $router = new StorefrontRouter($routes, $modules);

        // Construiește obiect request normalizat din $_SERVER (metodă, path, query)
        $request = StorefrontRequest::fromGlobals();

        // Găsește ruta potrivită și execută controllerul asociat
        $router->dispatch($request);
    }
}
