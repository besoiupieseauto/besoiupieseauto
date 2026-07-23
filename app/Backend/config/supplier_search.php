<?php

declare(strict_types=1);

return [
    'supported_suppliers' => ['autopartner', 'materom', 'autonet', 'autototal', 'elit'],
    'phase1_enabled' => ['materom', 'elit', 'autopartner', 'autonet', 'autototal'],
    'timeout' => 15,
    'connect_timeout' => 5,
    'materom_base_url' => 'https://api.materom.ro/api',
];
