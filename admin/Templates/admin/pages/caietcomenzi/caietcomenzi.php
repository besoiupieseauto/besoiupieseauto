<?php

declare(strict_types=1);

if (!headers_sent()) {
    header('Location: /admin/orders?legacy_tab=tm', true, 302);
    exit;
}

echo '<script>location.href="/admin/orders?legacy_tab=tm";</script>';
