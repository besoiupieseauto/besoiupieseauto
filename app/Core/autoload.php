<?php
/*
 * ============================================================================
 * FIȘIER: app/Core/autoload.php (autoloader PSR-4 pentru storefront)
 * ============================================================================
 * Scop: Înregistrează un autoloader PHP care încarcă automat clasele din
 *       namespace-ul Storefront\ atunci când sunt referite în cod.
 *       Evită require manual pentru fiecare clasă Core sau Module.
 *
 * Include/require: Niciun fișier extern; este inclus din app/bootstrap.php.
 *
 * Bază de date: Nu are legătură directă cu baza de date.
 * ============================================================================
 */

declare(strict_types=1);

// Înregistrează funcția de autoload care se apelează la prima utilizare a unei clase necunoscute
spl_autoload_register(static function (string $class): void {
    // Procesăm doar clasele din namespace-ul Storefront\; restul sunt ignorate
    if (!str_starts_with($class, 'Storefront\\')) {
        return;
    }

    // Determină calea către folderul app/ (din constantă sau relativ la acest fișier)
    $app = defined('BESOIU_APP') ? BESOIU_APP : (defined('BESOIU_ROOT') ? BESOIU_ROOT . '/app' : dirname(__DIR__) . '');

    // --- Clase din Storefront\Core\{SubNamespace}\{ClassName} → app/Core/{SubNamespace}/{ClassName}.php ---
    if (str_starts_with($class, 'Storefront\\Core\\')) {
        // Elimină prefixul namespace și transformă \ în / pentru calea fișierului
        $relative = substr($class, strlen('Storefront\\Core\\'));
        $path = $app . '/Core/' . str_replace('\\', '/', $relative) . '.php';
        if (is_file($path)) {
            require_once $path;
        }
        return;
    }

    // --- Clase din Storefront\Modules\{Modul}\{SubPath} → app/Modules/{Modul}/src/{SubPath}.php ---
    if (preg_match('#^Storefront\\\\Modules\\\\([^\\\\]+)\\\\(.+)$#', $class, $m)) {
        $path = $app . '/Modules/' . $m[1] . '/src/' . str_replace('\\', '/', $m[2]) . '.php';
        if (is_file($path)) {
            require_once $path;
        }
    }
});
