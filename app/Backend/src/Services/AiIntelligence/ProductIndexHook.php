<?php

declare(strict_types=1);

namespace Besoiu\Services\AiIntelligence;

use Throwable;

/**
 * Hook non-blocant — reindexare produs după create/update.
 */
final class ProductIndexHook
{
    public static function afterSave(?int $productId = null, ?string $randomnId = null): void
    {
        if (!ProductIndexService::autoIndexEnabled()) {
            return;
        }
        if (($productId === null || $productId <= 0) && ($randomnId === null || $randomnId === '')) {
            return;
        }

        try {
            $service = ProductIndexService::create();
            if ($productId !== null && $productId > 0) {
                $service->indexById($productId);
            } elseif ($randomnId !== null && $randomnId !== '') {
                $service->indexByRandomnId($randomnId);
            }
        } catch (Throwable) {
            // Nu blochează CRUD admin dacă Ollama/embed e offline.
        }
    }
}
