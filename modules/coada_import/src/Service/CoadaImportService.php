<?php
declare(strict_types=1);

namespace Besoiu\Modules\CoadaImport\Service;

use Besoiu\Core\Categorii\CategoriiHooks;
use Besoiu\Services\ImportReviewQueueService;

/**
 * Fațadă modul coadă import — delegă la CORE ImportReviewQueueService + hook-uri categorii.
 */
final class CoadaImportService
{
    public function __construct(
        private readonly ?ImportReviewQueueService $queueService = null,
    ) {
    }

    public function queueService(): ImportReviewQueueService
    {
        if ($this->queueService instanceof ImportReviewQueueService) {
            return $this->queueService;
        }

        return new ImportReviewQueueService(null, CategoriiHooks::service());
    }

    /** @param array<string, mixed> $query @return array<string, mixed> */
    public function loadPage(array $query): array
    {
        return $this->queueService()->loadPage($query);
    }

    /** @param array<string, mixed> $filters @return array{items: list<array<string, mixed>>, total: int} */
    public function list(array $filters): array
    {
        $page = $this->loadPage($filters);

        return [
            'items' => $page['rows'] ?? [],
            'total' => (int) ($page['total'] ?? 0),
        ];
    }
}
