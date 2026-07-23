<?php

declare(strict_types=1);

namespace Besoiu\Controllers\Furnizori;

/**
 * @deprecated Mutat în Besoiu\Services\Furnizori\FurnizoriDailySyncReportService — alias de compatibilitate (clasele țintă sunt final).
 */
if (!class_exists('Besoiu\Controllers\Furnizori\FurnizoriDailySyncReportService', false)) {
    class_alias(\Besoiu\Services\Furnizori\FurnizoriDailySyncReportService::class, 'Besoiu\Controllers\Furnizori\FurnizoriDailySyncReportService');
}
