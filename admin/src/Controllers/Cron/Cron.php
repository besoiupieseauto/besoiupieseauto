<?php

declare(strict_types=1);

namespace Evasystem\Controllers\Cron;

use Evasystem\Core\Hub\HubScaffoldController;

/**
 * Hub scaffold CRUD — tabel MySQL `cron` (înregistrări generice).
 * NU este panoul Cron Sync: acela e /admin/cron → Templates/.../cron/cron.php + ScanService.
 */
final class Cron extends HubScaffoldController
{
    protected const TABLE = 'cron';
    protected const LABEL = 'Cron';
    protected const SESSION_KEY = 'cron';
}
