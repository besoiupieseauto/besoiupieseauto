<?php

declare(strict_types=1);

namespace Evasystem\Controllers\Report;

use Evasystem\Core\Hub\HubScaffoldController;

final class Report extends HubScaffoldController
{
    protected const TABLE = 'report';
    protected const LABEL = 'Raport';
    protected const SESSION_KEY = 'report';
}
