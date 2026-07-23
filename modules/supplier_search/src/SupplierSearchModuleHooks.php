<?php
declare(strict_types=1);

namespace Besoiu\Modules\SupplierSearch;

use Besoiu\Core\Module\ModuleHookRegistry;
use Besoiu\Core\Module\OptionalModuleBridge;

/**
 * Înregistrare hook-uri — CORE apelează doar ModuleHookRegistry / helper dedicat.
 *
 * Exemplu hook id: 'supplier_search.service'
 * În CORE: helper care face ModuleHookRegistry::resolve('supplier_search.service')
 */
final class SupplierSearchModuleHooks
{
    public static function register(): void
    {
        if (ModuleHookRegistry::has('supplier_search.service')) {
            return;
        }

        if (!OptionalModuleBridge::isOperational('supplier_search')) {
            return;
        }

        ModuleHookRegistry::registerFactory('supplier_search.service', static function (): ?object {
            $class = OptionalModuleBridge::resolveClass('supplier_search', 'Service\\SupplierSearchService');

            return $class !== null ? new $class() : null;
        });
    }
}
