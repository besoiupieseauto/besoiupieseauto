<?php

declare(strict_types=1);

namespace Besoiu\Controllers\Clienti;

/**
 * @deprecated Mutat în Besoiu\Services\ClientiService — alias de compatibilitate (clasele țintă sunt final).
 */
if (!class_exists('Besoiu\Controllers\Clienti\ClientiService', false)) {
    class_alias(\Besoiu\Services\ClientiService::class, 'Besoiu\Controllers\Clienti\ClientiService');
}
