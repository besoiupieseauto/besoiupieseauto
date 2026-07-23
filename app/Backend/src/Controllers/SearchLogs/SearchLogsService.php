<?php

declare(strict_types=1);

namespace Besoiu\Controllers\SearchLogs;

/**
 * @deprecated Mutat în Besoiu\Services\SearchLogsService — alias de compatibilitate (clasele țintă sunt final).
 */
if (!class_exists('Besoiu\Controllers\SearchLogs\SearchLogsService', false)) {
    class_alias(\Besoiu\Services\SearchLogsService::class, 'Besoiu\Controllers\SearchLogs\SearchLogsService');
}
