<?php

declare(strict_types=1);

namespace Besoiu\Controllers\Furnizori;

/**
 * @deprecated Mutat în Besoiu\Services\Furnizori\SupplierScanScheduleService — alias de compatibilitate (clasele țintă sunt final).
 */
if (!class_exists('Besoiu\Controllers\Furnizori\SupplierScanScheduleService', false)) {
    class_alias(\Besoiu\Services\Furnizori\SupplierScanScheduleService::class, 'Besoiu\Controllers\Furnizori\SupplierScanScheduleService');
}
