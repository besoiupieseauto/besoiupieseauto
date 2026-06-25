<?php

declare(strict_types=1);

if (!isset($redirectTarget) || trim((string) $redirectTarget) === '') {
    $redirectTarget = '/admin/dashboard';
}

header('Location: ' . $redirectTarget, true, 302);
exit;
