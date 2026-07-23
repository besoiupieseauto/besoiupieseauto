<?php
/*
 * ============================================================================
 * FIȘIER: app/Core/Module/PageControllerInterface.php
 * ============================================================================
 * Scop: Contract (interfață) pe care trebuie să îl implementeze fiecare
 *       controller de pagină din modulele storefront.
 *
 * Include/require: Niciun fișier extern.
 * Bază de date: Implementarea concretă poate accesa DB.
 * ============================================================================
 */

declare(strict_types=1);

namespace Storefront\Core\Module;

use Storefront\Core\Bootstrap\StorefrontRequest;

/**
 * Interfață controller pagină — punct de intrare uniform pentru router.
 */
interface PageControllerInterface
{
    /**
     * Procesează request-ul și randează răspunsul (HTML, redirect, etc.).
     *
     * @param StorefrontRequest $request Request normalizat cu metodă, path, query
     */
    public function handle(StorefrontRequest $request): void;
}
