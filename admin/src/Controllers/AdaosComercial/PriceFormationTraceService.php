<?php

declare(strict_types=1);

namespace Besoiu\Controllers\AdaosComercial;

/**
 * @deprecated Mutat în Besoiu\Services\AdaosComercial\PriceFormationTraceService — alias de compatibilitate (clasele țintă sunt final).
 */
if (!class_exists('Besoiu\Controllers\AdaosComercial\PriceFormationTraceService', false)) {
    class_alias(\Besoiu\Services\AdaosComercial\PriceFormationTraceService::class, 'Besoiu\Controllers\AdaosComercial\PriceFormationTraceService');
}
