<?php
/*
 * ============================================================================
 * FIȘIER: app/Core/Bootstrap/StorefrontRequest.php
 * ============================================================================
 * Scop: Encapsulează datele unui request HTTP al vitrinei (metodă, cale, query)
 *       într-un obiect imutabil, ușor de transmis routerului.
 *
 * Include/require: Niciun fișier extern.
 *
 * Bază de date: Nu interacționează cu baza de date.
 * ============================================================================
 */

declare(strict_types=1);

namespace Storefront\Core\Bootstrap;

/**
 * Request HTTP normalizat pentru routerul storefront.
 * Proprietățile sunt readonly — obiectul nu se modifică după creare.
 */
final class StorefrontRequest
{
    /**
     * @param string $method Metoda HTTP (GET, POST, PUT, DELETE, etc.)
     * @param string $path   Calea URL normalizată (ex. /produse/filtru)
     * @param array<string, string> $query Parametrii din query string (?key=val)
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        /** @var array<string, string> */
        public readonly array $query = [],
    ) {
    }

    /**
     * Construiește un StorefrontRequest din superglobalii PHP ($_SERVER).
     *
     * @return self Obiect request cu metodă, path și query normalizate
     * Nu modifică baza de date sau fișiere externe.
     */
    public static function fromGlobals(): self
    {
        // Metoda HTTP din request; implicit GET dacă lipsește
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

        // URI complet din browser (ex. /produse?page=2)
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');

        // Extrage doar partea de path, fără query string
        $path = (string) parse_url($uri, PHP_URL_PATH);
        $path = $path === '' ? '/' : $path;

        // Elimină slash final (exceptând rădăcina /) pentru potrivire uniformă în router
        $path = rtrim($path, '/') ?: '/';

        /** @var array<string, string> $query */
        // Parsează parametrii din ?key=value&... în array asociativ
        $query = [];
        parse_str((string) parse_url($uri, PHP_URL_QUERY), $query);

        return new self($method, $path, $query);
    }
}
