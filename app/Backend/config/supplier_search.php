<?php

declare(strict_types=1);

return [
    'supported_suppliers' => ['autopartner', 'materom', 'autonet', 'autototal', 'elit', 'intercars', 'szakal'],
    'phase1_enabled' => ['materom', 'elit', 'autopartner', 'autonet', 'autototal', 'intercars', 'szakal'],
    'timeout' => 15,
    'connect_timeout' => 5,
    'materom_base_url' => 'https://api.materom.ro/api',
    'szakal_base_url' => 'https://webservice.szakalmetal.hu',
];
