<?php

declare(strict_types=1);

namespace Besoiu\Controllers\Scan;

/**
 * @deprecated Mutat în Besoiu\Services\Scan\ScanService — alias de compatibilitate (clasele țintă sunt final).
 */
if (!class_exists('Besoiu\Controllers\Scan\ScanService', false)) {
    class_alias(\Besoiu\Services\Scan\ScanService::class, 'Besoiu\Controllers\Scan\ScanService');
}
