<?php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/admin/public/api/_autoload.php';

use Besoiu\Modules\Categorii\Handler\CrudcategoriiHandler;

CrudcategoriiHandler::handle();
