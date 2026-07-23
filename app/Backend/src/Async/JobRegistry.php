<?php

declare(strict_types=1);

namespace Besoiu\Async;

final class JobRegistry
{
    /** @return array<string, class-string<JobHandlerInterface>> */
    public static function handlers(): array
    {
        return [
            'demo.progress' => Handlers\DemoProgressJobHandler::class,
            'import.publish_bulk' => Handlers\ImportPublishBulkJobHandler::class,
            'import.process_step' => Handlers\ImportProcessStepJobHandler::class,
            'import.refresh_images' => Handlers\ImportRefreshImagesJobHandler::class,
            'import.queue_row_action' => Handlers\ImportQueueRowActionJobHandler::class,
            'images.audit_batch' => Handlers\ImagesAuditBatchJobHandler::class,
            'tecdoc.merge' => Handlers\TecdocMergeJobHandler::class,
            'products.reapply_markup' => Handlers\ProductsReapplyMarkupJobHandler::class,
            'import.name_normalize_bulk' => Handlers\NameNormalizeBulkJobHandler::class,
        ];
    }

    public static function resolve(string $type): JobHandlerInterface
    {
        $map = self::handlers();
        if (!isset($map[$type])) {
            throw new \RuntimeException('Tip job necunoscut: ' . $type);
        }

        $class = $map[$type];
        $handler = new $class();
        if (!$handler instanceof JobHandlerInterface) {
            throw new \RuntimeException('Handler invalid: ' . $class);
        }

        return $handler;
    }
}
