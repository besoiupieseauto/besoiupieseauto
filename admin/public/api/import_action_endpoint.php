<?php
declare(strict_types=1);

/**
 * API acțiuni coadă import (importreview).
 * URL: /admin/api/import_action_endpoint.php
 */

require_once __DIR__ . '/_autoload.php';

use Besoiu\Modules\CoadaImport\Handler\CoadaImportQueueApiHandler;

CoadaImportQueueApiHandler::handle();
