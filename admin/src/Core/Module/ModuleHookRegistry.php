<?php

declare(strict_types=1);

namespace Besoiu\Core\Module;

/**
 * Registry generic de hook-uri — module opționale înregistrează factory/callable la boot.
 * CORE consumă doar id-uri de hook, fără namespace-uri din module.
 */
final class ModuleHookRegistry
{
    /** @var array<string, callable> */
    private static array $factories = [];

    /** @var array<string, class-string> */
    private static array $classes = [];

    /** @var array<string, callable> */
    private static array $callables = [];

    public static function registerFactory(string $hook, callable $factory): void
    {
        self::$factories[$hook] = $factory;
    }

    /** @param class-string $className */
    public static function registerClass(string $hook, string $className): void
    {
        self::$classes[$hook] = $className;
    }

    public static function registerCallable(string $hook, callable $callable): void
    {
        self::$callables[$hook] = $callable;
    }

    public static function has(string $hook): bool
    {
        return isset(self::$factories[$hook])
            || isset(self::$classes[$hook])
            || isset(self::$callables[$hook]);
    }

    public static function isRegistered(): bool
    {
        return self::$factories !== [] || self::$classes !== [] || self::$callables !== [];
    }

    /** @return object|null */
    public static function resolve(string $hook): ?object
    {
        if (!isset(self::$factories[$hook])) {
            return null;
        }

        $result = (self::$factories[$hook])();

        return is_object($result) ? $result : null;
    }

    /** @return class-string|null */
    public static function resolveClass(string $hook): ?string
    {
        $class = self::$classes[$hook] ?? null;

        return is_string($class) && class_exists($class) ? $class : null;
    }

    /**
     * @param array<int, mixed> $args
     */
    public static function invoke(string $hook, array $args = [], mixed $default = null): mixed
    {
        if (!isset(self::$callables[$hook])) {
            return $default;
        }

        return (self::$callables[$hook])(...$args);
    }

    /**
     * @param array<int, mixed> $args
     */
    public static function invokeStatic(string $hook, string $method, array $args = [], mixed $default = null): mixed
    {
        $class = self::resolveClass($hook);
        if ($class === null || !method_exists($class, $method)) {
            return $default;
        }

        return $class::$method(...$args);
    }

    /** @internal teste */
    public static function reset(): void
    {
        self::$factories = [];
        self::$classes = [];
        self::$callables = [];
    }
}
