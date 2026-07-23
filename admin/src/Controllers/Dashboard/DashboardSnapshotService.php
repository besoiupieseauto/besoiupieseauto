<?php

declare(strict_types=1);

namespace Besoiu\Controllers\Dashboard;

/**
 * @deprecated Mutat în Besoiu\Services\DashboardSnapshotService — alias de compatibilitate (clasele țintă sunt final).
 */
if (!class_exists('Besoiu\Controllers\Dashboard\DashboardSnapshotService', false)) {
    class_alias(\Besoiu\Services\DashboardSnapshotService::class, 'Besoiu\Controllers\Dashboard\DashboardSnapshotService');
}
