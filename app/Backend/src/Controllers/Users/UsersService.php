<?php

declare(strict_types=1);

namespace Besoiu\Controllers\Users;

/**
 * @deprecated Mutat în Besoiu\Services\UsersService — alias de compatibilitate (clasele țintă sunt final).
 */
if (!class_exists('Besoiu\Controllers\Users\UsersService', false)) {
    class_alias(\Besoiu\Services\UsersService::class, 'Besoiu\Controllers\Users\UsersService');
}
