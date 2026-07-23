<?php

declare(strict_types=1);

namespace Besoiu\Controllers\Furnizori;

/**
 * @deprecated Mutat în Besoiu\Services\Furnizori\SupplierFeedFolderService — alias de compatibilitate (clasele țintă sunt final).
 */
if (!class_exists('Besoiu\Controllers\Furnizori\SupplierFeedFolderService', false)) {
    class_alias(\Besoiu\Services\Furnizori\SupplierFeedFolderService::class, 'Besoiu\Controllers\Furnizori\SupplierFeedFolderService');
}
