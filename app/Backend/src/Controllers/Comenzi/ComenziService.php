<?php

declare(strict_types=1);

namespace Besoiu\Controllers\Comenzi;

/**
 * @deprecated Mutat în Besoiu\Services\Orders\ComenziService — alias de compatibilitate (clasele țintă sunt final).
 */
if (!class_exists('Besoiu\Controllers\Comenzi\ComenziService', false)) {
    class_alias(\Besoiu\Services\Orders\ComenziService::class, 'Besoiu\Controllers\Comenzi\ComenziService');
}
