<?php

declare(strict_types=1);

/**
 * Copiaza ca furnizori_connections.local.php si completeaza parolele.
 * fisierul .local.php nu se versioneaza in git.
 *
 * @return array<string, array<string, mixed>>
 */
return [
    'AUTONET' => [
        'conn_password' => '',
    ],
    'AUTOPARTNER' => [
        'connection_type' => 'api',
        'api_base_url' => 'https://customerapi.autopartner.dev/CustomerAPI.svc/rest',
        'api_token' => '',
        'conn_username' => '3208129',
        'conn_host' => 'ftp.autopartner.dev',
        'conn_port' => 21,
        'conn_passive' => 1,
        'conn_remote_path' => '',
        'notes' => 'Auto Partner: API Customer (recomandat). FTP poate necesita IP autorizat; foloseste sync agent pentru CSV.',
    ],
    'ELIT' => [
        'conn_password' => '',
    ],
    'AUTOTOTAL' => [
        'conn_email_inbox' => 'autototal@besoiupieseauto.ro',
    ],
    'MATEROM' => [
        'conn_host' => '',
        'conn_username' => '',
        'conn_password' => '',
    ],
];
