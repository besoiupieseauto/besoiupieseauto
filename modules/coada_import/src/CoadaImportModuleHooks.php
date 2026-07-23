<?php
declare(strict_types=1);

namespace Besoiu\Modules\CoadaImport;

use Besoiu\Core\Import\ImportHooks;
use Besoiu\Core\Module\ModuleHookRegistry;
use Besoiu\Core\Module\OptionalModuleBridge;
use Besoiu\Modules\CoadaImport\Service\CoadaImportService;
use Besoiu\Services\Import\ImportFacade;
use Besoiu\Services\ImportReviewQueueService;

final class CoadaImportModuleHooks
{
    public static function register(): void
    {
        if (ModuleHookRegistry::has(ImportHooks::HOOK_QUEUE_SERVICE)) {
            return;
        }

        if (!OptionalModuleBridge::isOperational('coada_import')) {
            return;
        }

        ModuleHookRegistry::registerFactory(
            ImportHooks::HOOK_QUEUE_SERVICE,
            static fn (): ImportReviewQueueService => (new CoadaImportService())->queueService()
        );

        if (!ModuleHookRegistry::has(ImportHooks::HOOK_FACADE)) {
            ModuleHookRegistry::registerFactory(
                ImportHooks::HOOK_FACADE,
                static fn (): ImportFacade => new ImportFacade()
            );
        }

        ModuleHookRegistry::registerCallable(ImportHooks::HOOK_STAGE, static function (array $rows, array $options = []): array {
            $pdo = \Config\Database::getDB();
            $stats = ImportHooks::facade()->stageProductsForReview($pdo, $rows, null, $options);

            return [
                'staged' => (int) ($stats['queued'] ?? 0),
                'queued' => (int) ($stats['queued'] ?? 0),
                'skipped' => 0,
                'errors' => [],
                'stats' => $stats,
            ];
        });
    }
}
