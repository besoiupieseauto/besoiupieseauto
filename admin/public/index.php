<?php

declare(strict_types=1);

/**
 * Front Controller EvaSystem — singur punct de intrare HTTP.
 * Logica de bootstrap: Evasystem\Core\Bootstrap\HttpApplication
 *
 * @see 08_project_specific.mdc
 * @see 02_architecture_patterns.mdc
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Evasystem\Core\Bootstrap\HttpApplication;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->safeLoad();

/** @var array<string, mixed> $applicationConfig */
$applicationConfig = require __DIR__ . '/../config/config.php';

$httpApplication = new HttpApplication($applicationConfig);
$httpApplication->run();
