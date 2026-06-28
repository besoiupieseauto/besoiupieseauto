<?php

declare(strict_types=1);

/**
 * Mapare script API → feature key (RBAC + departament activ).
 * Folosit de ApiBootstrap::enforceApiWorkspaceIfNeeded() — fix audit #45.
 *
 * @return array<string, string>
 */
return [
    'import_endpoint.php' => 'produse.import',
    'import_action_endpoint.php' => 'produse.import',
    'supplier_sync_endpoint.php' => 'produse.import',
    'furnizori_endpoint.php' => 'furnizori.list',
    'categorii_endpoint.php' => 'produse.categorii',
    'tecdoc_endpoint.php' => 'produse.list',
    'pieseauto_scanned_endpoint.php' => 'produse.scanned',

    'order_tmp_endpoint.php' => 'comenzi.create',
    'internal_order_endpoint.php' => 'comenzi.caiet',
    'caiet_comenzi_endpoint.php' => 'comenzi.caiet',
    'legacy_orders_endpoint.php' => 'comenzi.caiet',
    'clienti_endpoint.php' => 'clienti.list',
    'facturi_endpoint.php' => 'comenzi.facturi',
    'livrare_endpoint.php' => 'comenzi.livrare',
    'cart_abandonments_endpoint.php' => 'comenzi.abandoned_carts',
    'supplier_search_endpoint.php' => 'comenzi.supplier_search',
    'supplier_cart_endpoint.php' => 'comenzi.supplier_search',

    'bots_endpoint.php' => 'automatizare.bots',
    'bots_pipeline_endpoint.php' => 'automatizare.bots',
    'bots_whatsapp_endpoint.php' => 'automatizare.bots',
    'pieseauto_status_endpoint.php' => 'automatizare.bots',
    'pieseauto_reset_session_endpoint.php' => 'automatizare.bots',
    'pieseauto_accounts_endpoint.php' => 'automatizare.bots',
    'pieseauto_robot_launcher_endpoint.php' => 'automatizare.bots',
    'scraper_endpoint.php' => 'automatizare.scraper',
    'ai_agent_endpoint.php' => 'automatizare.ai_agent',
    'ai_tokens_endpoint.php' => 'sistem.ai_tokens',

    'marketplace_endpoint.php' => 'automatizare.marketplace',

    'comunicare_endpoint.php' => 'comunicare.hub',
    'messages_endpoint.php' => 'comunicare.messages',

    'search_logs_endpoint.php' => 'analiza.searchlogs',

    'settings_endpoint.php' => 'sistem.settings',
    'backup_endpoint.php' => 'sistem.backup',
    'system_errors_endpoint.php' => 'sistem.system_errors',

    'dashboard_endpoint.php' => 'dashboard.home',
    'admin_hub_endpoint.php' => 'dashboard.home',
    'robot_pieseauto_proxy.php' => 'automatizare.bots',
    'export_action_endpoint.php' => 'automatizare.export',
];
