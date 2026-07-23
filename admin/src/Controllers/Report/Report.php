<?php

declare(strict_types=1);

namespace Besoiu\Controllers\Report;

use Besoiu\Core\Hub\HubScaffoldController;

final class Report extends HubScaffoldController
{
    protected const TABLE = 'report';
    protected const LABEL = 'Raport';
    protected const SESSION_KEY = 'report';
}
