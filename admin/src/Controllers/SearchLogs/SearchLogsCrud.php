<?php

declare(strict_types=1);

namespace Evasystem\Controllers\SearchLogs;

use Evasystem\Core\Hub\HubScaffoldController;

/** CRUD scaffold hub — tabel `search_logs_scaffold`. Jurnalul VIN/OEM = SearchLogsService (`search_logs`). */
final class SearchLogsCrud extends HubScaffoldController
{
    protected const TABLE = 'search_logs_scaffold';
    protected const LABEL = 'Search logs';
    protected const SESSION_KEY = 'search_logs';
}
