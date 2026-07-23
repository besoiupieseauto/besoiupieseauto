<?php
declare(strict_types=1);

namespace Besoiu\Modules\ImportPro;

use Besoiu\Core\Import\ImportHooks;
use Besoiu\Core\Module\ModuleHookRegistry;
use Besoiu\Core\Module\OptionalModuleBridge;
use Besoiu\Modules\ImportPro\Support\ImportProBridge;

final class ImportProModuleHooks
{
    public const HOOK_BRIDGE = 'import_pro.bridge';

    public static function register(): void
    {
        if (ModuleHookRegistry::has(self::HOOK_BRIDGE)) {
            return;
        }

        if (!OptionalModuleBridge::isOperational('import_pro')) {
            return;
        }

        ModuleHookRegistry::registerFactory(self::HOOK_BRIDGE, static fn (): ImportProBridge => ImportProBridge::boot());

        ModuleHookRegistry::registerCallable('import_pro.stage_to_queue', static function (array $rows, array $options = []): array {
            if (!ImportHooks::queueModuleAvailable()) {
                return ['staged' => 0, 'skipped' => count($rows), 'errors' => ['Modulul coada_import este oprit.']];
            }

            return ImportHooks::stageProductsForReview($rows, $options);
        });
    }
}
