<?php

declare(strict_types=1);

namespace Evasystem\Controllers\Scan;

use RuntimeException;

/** Scan întrerupt manual din panoul Cron Sync. */
final class ScanCancelledException extends RuntimeException
{
}
