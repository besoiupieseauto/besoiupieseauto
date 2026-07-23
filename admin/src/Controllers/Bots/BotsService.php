<?php

declare(strict_types=1);

namespace Besoiu\Controllers\Bots;

/**
 * @deprecated Mutat în Besoiu\Services\BotsService — alias de compatibilitate (clasele țintă sunt final).
 */
if (!class_exists('Besoiu\Controllers\Bots\BotsService', false)) {
    class_alias(\Besoiu\Services\BotsService::class, 'Besoiu\Controllers\Bots\BotsService');
}
