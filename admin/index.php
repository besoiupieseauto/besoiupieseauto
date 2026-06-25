<?php

declare(strict_types=1);

/**
 * Punct de intrare rădăcină admin — delegă către front controller EvaSystem.
 * Evită buclă /admin/login → index.php → /admin/login când .htaccess rescrie greșit.
 */
require __DIR__ . '/public/index.php';
