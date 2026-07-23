<?php

declare(strict_types=1);

namespace Besoiu\Controllers\Alerts;

use Besoiu\Core\Hub\HubScaffoldController;

final class Alerts extends HubScaffoldController
{
    protected const TABLE = 'alerts';
    protected const LABEL = 'Alert';
    protected const SESSION_KEY = 'alerts';
}
