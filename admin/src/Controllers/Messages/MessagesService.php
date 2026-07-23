<?php

declare(strict_types=1);

namespace Besoiu\Controllers\Messages;

/**
 * @deprecated Mutat în Besoiu\Services\MessagesService — alias de compatibilitate (clasele țintă sunt final).
 */
if (!class_exists('Besoiu\Controllers\Messages\MessagesService', false)) {
    class_alias(\Besoiu\Services\MessagesService::class, 'Besoiu\Controllers\Messages\MessagesService');
}
