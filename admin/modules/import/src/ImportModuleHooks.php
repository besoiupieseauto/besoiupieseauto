<?php
declare(strict_types=1);

namespace Besoiu\Modules\Import;

use Besoiu\Core\Import\ImportHooks;
use Besoiu\Core\Module\ModuleHookRegistry;
use Besoiu\Core\Module\OptionalModuleBridge;
use Besoiu\Services\Import\ImportFacade;

final class ImportModuleHooks
{
    public static function register(): void
    {
        if (ModuleHookRegistry::has(ImportHooks::HOOK_FACADE)) {
            return;
        }

        if (!OptionalModuleBridge::isOperational('import')) {
            return;
        }

        ModuleHookRegistry::registerFactory(ImportHooks::HOOK_FACADE, static fn (): ImportFacade => new ImportFacade());

        ModuleHookRegistry::registerCallable(ImportHooks::HOOK_STAGE, static function (array $rows, array $options = []): array {
            $facade = new ImportFacade();

            return $facade->stageProductsForReview($rows, $options);
        });
    }
}
