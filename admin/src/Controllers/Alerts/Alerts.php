<?php

declare(strict_types=1);

namespace Evasystem\Controllers\Alerts;

use Evasystem\Core\Hub\HubScaffoldController;

final class Alerts extends HubScaffoldController
{
    protected const TABLE = 'alerts';
    protected const LABEL = 'Alert';
    protected const SESSION_KEY = 'alerts';
}
