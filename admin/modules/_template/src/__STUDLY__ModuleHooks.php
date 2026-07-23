<?php
declare(strict_types=1);

namespace Besoiu\Modules\__STUDLY__;

use Besoiu\Core\Module\ModuleHookRegistry;

/**
 * Hook-uri opționale — înregistrează capabilități consumate de CORE prin helper dedicat.
 * Exemplu: ModuleHookRegistry::registerFactory('__MODULE_ID__.stats', fn () => new __STUDLY__Service());
 */
final class __STUDLY__ModuleHooks
{
    public static function register(): void
    {
        // ModuleHookRegistry::registerFactory('__MODULE_ID__.example', static fn () => new __STUDLY__Service());
    }
}
