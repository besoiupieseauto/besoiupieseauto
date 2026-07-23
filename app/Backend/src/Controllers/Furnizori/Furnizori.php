<?php

declare(strict_types=1);

namespace Besoiu\Controllers\Furnizori;

use Besoiu\Core\Module\FurnizoriBridgeFactory;
use Besoiu\Core\Module\OptionalModuleBridge;
use Besoiu\Exceptions\ValidationException;

/**
 * Bridge CORE — proxy runtime către modules/furnizori/ (fără extends).
 */
final class Furnizori
{
    private object $delegate;

    public function __construct(?object $furnizoriService = null)
    {
        $controllerClass = OptionalModuleBridge::resolveClass('furnizori', 'Controller\\FurnizoriController');
        if ($controllerClass === null) {
            throw new ValidationException('Modulul Furnizori B2B nu este instalat sau este dezactivat.');
        }

        if ($furnizoriService === null) {
            $furnizoriService = FurnizoriBridgeFactory::defaultFurnizoriService();
        }

        $this->delegate = new $controllerClass($furnizoriService);
    }

    /** @param array<int, mixed> $args */
    public function __call(string $name, array $args): mixed
    {
        return $this->delegate->{$name}(...$args);
    }
}
