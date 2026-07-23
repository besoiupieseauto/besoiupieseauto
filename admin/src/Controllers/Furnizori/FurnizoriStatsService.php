<?php

declare(strict_types=1);

namespace Besoiu\Controllers\Furnizori;

/**
 * @deprecated Mutat în Besoiu\Services\Furnizori\FurnizoriStatsService — alias de compatibilitate (clasele țintă sunt final).
 */
if (!class_exists('Besoiu\Controllers\Furnizori\FurnizoriStatsService', false)) {
    class_alias(\Besoiu\Services\Furnizori\FurnizoriStatsService::class, 'Besoiu\Controllers\Furnizori\FurnizoriStatsService');
}
