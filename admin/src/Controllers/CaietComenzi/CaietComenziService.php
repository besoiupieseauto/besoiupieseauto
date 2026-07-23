<?php

declare(strict_types=1);

namespace Besoiu\Controllers\CaietComenzi;

/**
 * @deprecated Mutat în Besoiu\Services\Orders\CaietComenziService — alias de compatibilitate (clasele țintă sunt final).
 */
if (!class_exists('Besoiu\Controllers\CaietComenzi\CaietComenziService', false)) {
    class_alias(\Besoiu\Services\Orders\CaietComenziService::class, 'Besoiu\Controllers\CaietComenzi\CaietComenziService');
}
