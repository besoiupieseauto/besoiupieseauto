<?php

declare(strict_types=1);

/**
 * Bootstrap comun API storefront — sesiune + mod preview.
 */
require_once __DIR__ . '/shop-auth.php';
shop_auth_load_env();
shop_auth_session_start();
require_once __DIR__ . '/preview-gate.php';
besoiu_preview_gate_enforce('json');
