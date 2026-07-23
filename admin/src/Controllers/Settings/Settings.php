<?php

declare(strict_types=1);

namespace Besoiu\Controllers\Settings;

use Besoiu\Core\Hub\HubScaffoldController;

final class Settings extends HubScaffoldController
{
    protected const TABLE = 'settings';
    protected const LABEL = 'Setare';
    protected const SESSION_KEY = 'settings';
}
