<?php
/**
 * Înregistrare publică dezactivată — redirect la login.
 */
declare(strict_types=1);

header('Location: /admin/login', true, 302);
exit;
