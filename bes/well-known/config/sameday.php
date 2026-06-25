<?php


return [
    'host_url' => env('SAMEDAY_HOST_URL', 'https://api.sameday.ro'),
    'auth_user' => env('SAMEDAY_AUTH_USER', ''),
    'auth_password' => env('SAMEDAY_AUTH_PASSWORD', ''),
    'timeout' => env('SAMEDAY_TIMEOUT', 60),
    'connect_timeout' => env('SAMEDAY_CONNECT_TIMEOUT', 30),
    'verify_ssl' => env('SAMEDAY_VERIFY_SSL', false),
    'use_mock' => env('SAMEDAY_USE_MOCK', false),
    'proxy' => env('SAMEDAY_PROXY', null),

];