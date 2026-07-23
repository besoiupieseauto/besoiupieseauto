<?php
declare(strict_types=1);

namespace Besoiu\Core\Import;

use Besoiu\Core\Module\ModuleHookRegistry;
use Besoiu\Core\Module\ModuleRegistry;
use Besoiu\Core\Module\OptionalModuleBridge;
use Besoiu\Services\Import\ImportFacade;
use Besoiu\Services\ImportReviewQueueService;

/**
 * Punct unic CORE → import CSV, coadă staging, facade publish.
 */
final class ImportHooks
{
    public const HOOK_QUEUE_SERVICE = 'import.queue.service';
    public const HOOK_FACADE = 'import.facade';
    public const HOOK_STAGE = 'import.stage';
    public const HOOK_PUBLISH = 'import.publish';

    private static bool $ensuring = false;

    public static function queueModuleAvailable(): bool
    {
        return OptionalModuleBridge::isOperational('coada_import');
    }

    public static function csvModuleAvailable(): bool
    {
        return OptionalModuleBridge::isOperational('import');
    }

    public static function ensureHooks(): void
    {
        if (ModuleHookRegistry::has(self::HOOK_QUEUE_SERVICE)) {
            return;
        }

        if (self::$ensuring) {
            return;
        }

        self::$ensuring = true;
        try {
            // Doar discover (autoload) — NU boot(), altfel buclă infinită din module->boot().
            ModuleRegistry::instance()->discover();

            if (self::queueModuleAvailable()) {
                $hooksClass = OptionalModuleBridge::resolveClass('coada_import', 'CoadaImportModuleHooks');
                if ($hooksClass !== null && method_exists($hooksClass, 'register')) {
                    $hooksClass::register();
                }
            }

            if (self::csvModuleAvailable()) {
                $hooksClass = OptionalModuleBridge::resolveClass('import', 'ImportModuleHooks');
                if ($hooksClass !== null && method_exists($hooksClass, 'register')) {
                    $hooksClass::register();
                }
            }
        } finally {
            self::$ensuring = false;
        }
    }

    public static function queueService(): ImportReviewQueueService
    {
        self::ensureHooks();
        $service = ModuleHookRegistry::resolve(self::HOOK_QUEUE_SERVICE);
        if ($service instanceof ImportReviewQueueService) {
            return $service;
        }

        return new ImportReviewQueueService();
    }

    public static function facade(): ImportFacade
    {
        self::ensureHooks();
        $facade = ModuleHookRegistry::resolve(self::HOOK_FACADE);
        if ($facade instanceof ImportFacade) {
            return $facade;
        }

        return new ImportFacade();
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public static function stageProductsForReview(array $rows, array $options = []): array
    {
        self::ensureHooks();
        $callable = ModuleHookRegistry::resolve(self::HOOK_STAGE);
        if (is_callable($callable)) {
            $result = $callable($rows, $options);

            return is_array($result) ? $result : ['staged' => 0, 'skipped' => 0, 'errors' => []];
        }

        $pdo = \Config\Database::getDB();
        $stats = self::facade()->stageProductsForReview($pdo, $rows, null, $options);

        return [
            'staged' => (int) ($stats['queued'] ?? 0),
            'queued' => (int) ($stats['queued'] ?? 0),
            'skipped' => 0,
            'errors' => [],
            'stats' => $stats,
        ];
    }
}
