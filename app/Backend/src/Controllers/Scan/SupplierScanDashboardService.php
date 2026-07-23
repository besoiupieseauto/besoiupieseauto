<?php

declare(strict_types=1);

namespace Besoiu\Controllers\Scan;

/**
 * @deprecated Mutat în Besoiu\Services\Scan\SupplierScanDashboardService — alias de compatibilitate (clasele țintă sunt final).
 */
if (!class_exists('Besoiu\Controllers\Scan\SupplierScanDashboardService', false)) {
    class_alias(\Besoiu\Services\Scan\SupplierScanDashboardService::class, 'Besoiu\Controllers\Scan\SupplierScanDashboardService');
}
