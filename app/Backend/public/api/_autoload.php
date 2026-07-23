<?php

declare(strict_types=1);

/**
 * Composer autoload — obligatoriu înainte de orice clasă Besoiu\ din public/api/.
 * ApiBootstrap::boot() încarcă autoload din nou (idempotent via require_once).
 */
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

// JSON curat — înainte de parse endpoint (evită warning PHP în body)
\Besoiu\Core\Bootstrap\ApiBootstrap::registerProductionSafeErrors();
\Besoiu\Core\Bootstrap\ApiBootstrap::enforceDefaultApiAuthIfNeeded();
