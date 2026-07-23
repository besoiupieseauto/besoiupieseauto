<?php

declare(strict_types=1);

namespace Besoiu\Controllers\Furnizori;

/**
 * @deprecated Mutat în Besoiu\Services\Furnizori\FurnizoriService — alias de compatibilitate (clasele țintă sunt final).
 */
if (!class_exists('Besoiu\Controllers\Furnizori\FurnizoriService', false)) {
    class_alias(\Besoiu\Services\Furnizori\FurnizoriService::class, 'Besoiu\Controllers\Furnizori\FurnizoriService');
}
