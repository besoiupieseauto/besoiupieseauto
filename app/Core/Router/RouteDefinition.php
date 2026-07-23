<?php
/*
 * ============================================================================
 * FIȘIER: app/Core/Router/RouteDefinition.php
 * ============================================================================
 * Scop: Value object (DTO) care descrie o rută storefront: metodă HTTP,
 *       cale URL, modul sursă și clasa controllerului responsabil.
 *
 * Include/require: Niciun fișier extern.
 * Bază de date: Nu are legătură directă.
 * ============================================================================
 */

declare(strict_types=1);

namespace Storefront\Core\Router;

/**
 * Definiție rută: path → modul + controller.
 * Toate proprietățile sunt readonly — obiect imutabil după creare.
 */
final class RouteDefinition
{
    /**
     * @param string $method          Metoda HTTP (GET, POST, etc.)
     * @param string $path            Calea URL (ex. /, /blog, /contact)
     * @param string $moduleId        ID modul din module.json (ex. cms, shop)
     * @param string $controllerClass FQCN controller (ex. Storefront\Modules\Cms\...\HomeController)
     * @param string $cmsPage         Slug pagină CMS opțional (pentru rute dinamice /p/)
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly string $moduleId,
        public readonly string $controllerClass,
        public readonly string $cmsPage = '',
    ) {
    }
}
