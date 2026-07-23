<?php

declare(strict_types=1);

namespace Besoiu\Controllers\Website;

/**
 * @deprecated Mutat în Besoiu\Services\WebsiteService — alias de compatibilitate (clasele țintă sunt final).
 */
if (!class_exists('Besoiu\Controllers\Website\WebsiteService', false)) {
    class_alias(\Besoiu\Services\WebsiteService::class, 'Besoiu\Controllers\Website\WebsiteService');
}
