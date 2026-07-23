<?php

declare(strict_types=1);

namespace Besoiu\Controllers\Report;

/**
 * @deprecated Mutat în Besoiu\Services\ReportService — alias de compatibilitate (clasele țintă sunt final).
 */
if (!class_exists('Besoiu\Controllers\Report\ReportService', false)) {
    class_alias(\Besoiu\Services\ReportService::class, 'Besoiu\Controllers\Report\ReportService');
}
