<?php

declare(strict_types=1);

namespace Besoiu\Core\Module;

/**
 * Proxy runtime către clase din modules/furnizori/ — fără extends (modul poate lipsi).
 */
trait DelegatesToOptionalFurnizoriModule
{
    private object $furnizoriModuleDelegate;

    /** @return non-empty-string */
    abstract protected static function furnizoriModuleRelativeClass(): string;

    public function __construct(mixed ...$args)
    {
        $class = OptionalModuleBridge::resolveClass('furnizori', static::furnizoriModuleRelativeClass());
        if ($class === null) {
            throw new \RuntimeException('Modulul furnizori nu este instalat sau este dezactivat.');
        }

        $this->furnizoriModuleDelegate = new $class(...$args);
    }

    /** @param array<int, mixed> $args */
    public function __call(string $name, array $args): mixed
    {
        return $this->furnizoriModuleDelegate->{$name}(...$args);
    }
}
