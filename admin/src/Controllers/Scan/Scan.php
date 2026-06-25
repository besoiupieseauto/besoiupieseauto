<?php

declare(strict_types=1);

namespace Evasystem\Controllers\Scan;

use Evasystem\Core\Hub\HubScaffoldController;

final class Scan extends HubScaffoldController
{
    protected const TABLE = 'scan';
    protected const LABEL = 'Scan';
    protected const SESSION_KEY = 'scan';
}
