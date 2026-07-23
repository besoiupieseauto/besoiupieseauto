<?php

declare(strict_types=1);

namespace Besoiu\Controllers\Furnizori;

/**
 * @deprecated Mutat în Besoiu\Services\Furnizori\PriceFormationLogicService — alias de compatibilitate (clasele țintă sunt final).
 */
if (!class_exists('Besoiu\Controllers\Furnizori\PriceFormationLogicService', false)) {
    class_alias(\Besoiu\Services\Furnizori\PriceFormationLogicService::class, 'Besoiu\Controllers\Furnizori\PriceFormationLogicService');
}
