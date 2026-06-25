<?php

declare(strict_types=1);

/**
 * Copiaza ca supplier_sync_agent.local.php (gitignored) pe PC-ul unde rulezi agentul.
 *
 * Flux recomandat (fara FileZilla manual):
 * 1. Instaleaza rclone: powershell admin/tools/install_rclone.ps1
 * 2. Genereaza config: php admin/tools/generate_rclone_config.php
 * 3. Task Scheduler -> run_supplier_rclone_sync.bat la fiecare 6 ore
 * 4. Fisierul apare in Admin -> Import -> lista incarcata
 *
 * source:
 *   rclone       — descarca automat cu rclone (recomandat)
 *   local_folder — doar incarca din folder (legacy FileZilla)
 *   ftp          — descarca cu PHP/cURL (legacy)
 *
 * @return array<string, mixed>
 */
return [
    // URL endpoint pe server (live sau local)
    'server_url' => 'https://besoiupieseauto.ro/admin/api/supplier_sync_endpoint.php',

    // Acelasi token ca SUPPLIER_SYNC_TOKEN din admin/.env pe server
    'sync_token' => 'schimba-cu-un-token-lung-sigur',

    // Unde salveaza temporar fisierele descarcate
    'local_download_dir' => 'C:/SupplierSync/cache',

    // Fisier stare (evita re-upload acelasi fisier)
    'state_file' => __DIR__ . '/../storage/supplier_sync_agent_state.json',

    'suppliers' => [
        'AUTOPARTNER' => [
            'enabled' => true,

            // rclone = descarca automat de pe FTP/SFTP (recomandat)
            // local_folder = FileZilla descarca aici; agentul doar incarca pe server
            // ftp = descarca direct cu PHP/cURL
            'source' => 'rclone',

            'rclone_remote' => 'autopartner',
            'file_pattern' => '*.csv',
            'remote_dir' => '/',

            'local_folder' => 'C:/SupplierSync/AutoPartner',

            // Setari conexiune (folosite de generate_rclone_config.php)
            'connection_type' => 'ftp',
            'conn_host' => 'ftp.autopartner.dev',
            'conn_port' => 21,
            'conn_username' => '3208129',
            'conn_password' => '',
            'conn_passive' => 1,
            'remote_file' => '',
        ],

        'AUTONET' => [
            'enabled' => false,
            'source' => 'rclone',
            'rclone_remote' => 'autonet',
            'file_pattern' => '*.csv',
            'remote_dir' => '/',
            'connection_type' => 'ftp',
            'conn_host' => 'ftp.besoiupieseauto.ro',
            'conn_port' => 21,
            'conn_username' => 'autonet@besoiupieseauto.ro',
            'conn_password' => '',
            'conn_passive' => 1,
        ],
    ],
];
