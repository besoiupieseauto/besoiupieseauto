<?php

declare(strict_types=1);

namespace Besoiu\Core\Module;

use Besoiu\Core\Supplier\SupplierCatalogRepository;
use Besoiu\Core\Supplier\SupplierHooks;

/**
 * Instanțiere sigură a serviciilor furnizori prin hook-uri modul.
 *
 * @deprecated Folosiți SupplierHooks — păstrat pentru API bridge Furnizori controller.
 */
final class FurnizoriBridgeFactory
{
    public static function repository(): SupplierCatalogRepository
    {
        return SupplierHooks::catalog();
    }

    /** @return object */
    public static function statsService(): object
    {
        self::requireOperational();
        $stats = SupplierHooks::stats();
        if ($stats === null) {
            throw new \RuntimeException('FurnizoriStatsService indisponibil.');
        }

        return $stats;
    }

    /** @return object */
    public static function defaultFurnizoriService(): object
    {
        self::requireOperational();
        $serviceClass = OptionalModuleBridge::resolveClass('furnizori', 'Service\\FurnizoriService');
        $statsClass = OptionalModuleBridge::resolveClass('furnizori', 'Service\\FurnizoriStatsService');
        if ($serviceClass === null || $statsClass === null) {
            throw new \RuntimeException('FurnizoriService indisponibil.');
        }

        $repository = self::moduleRepository();

        return new $serviceClass($repository, new $statsClass($repository));
    }

    /** @return object */
    public static function statsServiceBridge(): object
    {
        return self::statsService();
    }

    /** @return object */
    public static function moduleRepository(): object
    {
        $class = OptionalModuleBridge::resolveClass('furnizori', 'Model\\FurnizoriRepository');
        if ($class === null) {
            throw new \RuntimeException('Repository furnizori indisponibil.');
        }

        return new $class();
    }

    public static function requireOperational(): void
    {
        if (!OptionalModuleBridge::furnizoriAvailable()) {
            throw new \RuntimeException('Modulul furnizori nu este instalat sau este dezactivat.');
        }
    }
}
