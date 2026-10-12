<?php

declare(strict_types=1);

/**
 * Seed meniu CORE — secțiuni universale (fără module opționale; acestea vin din sync module.json).
 *
 * @return list<array{
 *   section_key: string,
 *   label: string,
 *   group_label?: string|null,
 *   workspace?: string|null,
 *   sort_order: int,
 *   items: list<array{
 *     item_key: string,
 *     label: string,
 *     url: string,
 *     icon?: string,
 *     badge_key?: string|null,
 *     alert_key?: string|null,
 *     is_global?: bool,
 *     open_new_tab?: bool,
 *     item_type?: string,
 *     sort_order?: int
 *   }>
 * }>
 */
return [
    [
        'section_key' => 'core_dashboard',
        'label' => 'Dashboard',
        'group_label' => 'ADMIN PANEL',
        'workspace' => 'company',
        'sort_order' => 10,
        'items' => [
            [
                'item_key' => 'dashboard',
                'label' => 'Dashboard',
                'url' => '/admin/dashboard',
                'icon' => 'layout-dashboard',
                'is_global' => true,
                'sort_order' => 10,
            ],
        ],
    ],
    [
        'section_key' => 'core_comenzi',
        'label' => 'Comenzi',
        'group_label' => 'COMENZI',
        'workspace' => 'orders',
        'sort_order' => 15,
        'items' => [
            ['item_key' => 'comenzi', 'label' => 'Comenzi', 'url' => '/admin/caiet-comenzi-tm', 'icon' => 'clipboard-list', 'sort_order' => 10],
            ['item_key' => 'clienti', 'label' => 'Clienți', 'url' => '/admin/caiet-clienti', 'icon' => 'users', 'sort_order' => 30],
            ['item_key' => 'caiet-facturi', 'label' => 'Facturi', 'url' => '/admin/caiet-facturi', 'icon' => 'file-text', 'sort_order' => 40],
            ['item_key' => 'caiet-incasari', 'label' => 'Încasări', 'url' => '/admin/caiet-incasari', 'icon' => 'wallet', 'sort_order' => 50],
            ['item_key' => 'caiet-utilizatori', 'label' => 'Utilizatori', 'url' => '/admin/caiet-utilizatori', 'icon' => 'user-cog', 'sort_order' => 60],
            ['item_key' => 'caiet-pieseauto', 'label' => 'Pieseauto', 'url' => '/admin/caiet-pieseauto', 'icon' => 'car', 'sort_order' => 70],
            ['item_key' => 'supplier-search', 'label' => 'Supplier Search', 'url' => '/admin/caiet-search', 'icon' => 'search-check', 'sort_order' => 80],
            ['item_key' => 'caiet-api', 'label' => 'API', 'url' => '/admin/caiet-api', 'icon' => 'key', 'sort_order' => 90],
            ['item_key' => 'supplier-cart', 'label' => 'Coș furnizori', 'url' => '/admin/supplier-cart', 'icon' => 'shopping-cart', 'sort_order' => 100],
        ],
    ],
    [
        'section_key' => 'core_furnizori',
        'label' => 'Furnizori',
        'group_label' => 'FURNIZORI',
        'workspace' => 'suppliers',
        'sort_order' => 20,
        'items' => [
            ['item_key' => 'lista-furnizori', 'label' => 'Lista furnizori', 'url' => '/admin/furnizori', 'icon' => 'truck', 'sort_order' => 20],
        ],
    ],
    [
        'section_key' => 'core_produse',
        'label' => 'Produse',
        'group_label' => 'ADMIN PANEL',
        'workspace' => 'suppliers',
        'sort_order' => 21,
        'items' => [
            ['item_key' => 'lista-produse', 'label' => 'Lista produse', 'url' => '/admin/product', 'icon' => 'package', 'sort_order' => 10],
            ['item_key' => 'vitrina', 'label' => 'Vitrină', 'url' => '/admin/vitrina', 'icon' => 'store', 'sort_order' => 20],
            ['item_key' => 'product-formation', 'label' => 'Formare carte', 'url' => '/admin/product-formation', 'icon' => 'layout-template', 'sort_order' => 30],
            ['item_key' => 'gpsr', 'label' => 'GPSR BaseLinker', 'url' => '/admin/gpsr', 'icon' => 'shield-check', 'sort_order' => 35],
            ['item_key' => 'add-produs', 'label' => 'Adaugă produs', 'url' => '/admin/addproduse', 'icon' => 'plus-circle', 'sort_order' => 40],
            ['item_key' => 'categorii', 'label' => 'Categorii', 'url' => '/admin/categorii', 'icon' => 'folder-tree', 'sort_order' => 50],
            ['item_key' => 'adaos', 'label' => 'Adaos comercial', 'url' => '/admin/adaoscomercial', 'icon' => 'percent', 'sort_order' => 60],
            ['item_key' => 'scraper', 'label' => 'Scraper imagini', 'url' => '/admin/scraper', 'icon' => 'image', 'sort_order' => 70],
        ],
    ],
    [
        'section_key' => 'core_import',
        'label' => 'Import',
        'group_label' => 'IMPORT',
        'workspace' => 'suppliers',
        'sort_order' => 36,
        'items' => [
            ['item_key' => 'import-pro', 'label' => 'Import & Matching Pro', 'url' => '/admin/import-pro', 'icon' => 'upload', 'sort_order' => 10],
            ['item_key' => 'tecdoc-import', 'label' => 'Progres TecDoc Matc', 'url' => '/admin/tecdoc-import', 'icon' => 'activity', 'sort_order' => 12],
            ['item_key' => 'import-queue', 'label' => 'Coadă import', 'url' => '/admin/importreview', 'icon' => 'list-checks', 'sort_order' => 20],
        ],
    ],
    [
        'section_key' => 'core_sistem',
        'label' => 'Sistem',
        'group_label' => 'SISTEM',
        'workspace' => 'company',
        'sort_order' => 110,
        'items' => [
            [
                'item_key' => 'users',
                'label' => 'Utilizatori admin',
                'url' => '/admin/users',
                'icon' => 'user-cog',
                'sort_order' => 10,
            ],
            [
                'item_key' => 'alerts',
                'label' => 'Alerte',
                'url' => '/admin/alerts',
                'icon' => 'bell-ring',
                'alert_key' => 'alerts',
                'sort_order' => 20,
            ],
            [
                'item_key' => 'settings',
                'label' => 'Setări',
                'url' => '/admin/settings',
                'icon' => 'settings',
                'sort_order' => 90,
            ],
            [
                'item_key' => 'ai-rag',
                'label' => 'Centru AI',
                'url' => '/admin/ai-rag',
                'icon' => 'brain-circuit',
                'sort_order' => 85,
            ],
        ],
    ],
];
