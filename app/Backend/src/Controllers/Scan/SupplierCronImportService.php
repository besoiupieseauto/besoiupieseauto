<?php

declare(strict_types=1);

namespace Besoiu\Controllers\Scan;

/**
 * @deprecated Mutat în Besoiu\Services\Scan\SupplierCronImportService — alias de compatibilitate (clasele țintă sunt final).
 */
if (!class_exists('Besoiu\Controllers\Scan\SupplierCronImportService', false)) {
    class_alias(\Besoiu\Services\Scan\SupplierCronImportService::class, 'Besoiu\Controllers\Scan\SupplierCronImportService');
}
