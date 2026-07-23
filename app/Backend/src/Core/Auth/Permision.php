<?php
declare(strict_types=1);

/**
 * Shim compatibilitate: numele istoric cu typo „Permision”.
 * Clasa reală este Permission (alias înregistrat la încărcare).
 */
require_once __DIR__ . '/Permission.php';
