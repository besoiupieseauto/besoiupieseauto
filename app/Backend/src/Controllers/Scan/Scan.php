<?php

declare(strict_types=1);

namespace Besoiu\Controllers\Scan;

use Besoiu\Core\Hub\HubScaffoldController;

final class Scan extends HubScaffoldController
{
    protected const TABLE = 'scan';
    protected const LABEL = 'Scan';
    protected const SESSION_KEY = 'scan';
}
