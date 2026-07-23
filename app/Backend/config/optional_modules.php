<?php



declare(strict_types=1);



/**

 * Hartă module opționale → rute/CRUD/API/quick-actions (bridge legacy + hub MVP).

 * Manifestele din modules/{Id}/module.json.provides au prioritate la merge.

 *

 * @return array<string, array{

 *   slugs: list<string>,

 *   paths: list<string>,

 *   crud_key: ?string,

 *   api_scripts?: list<string>,

 *   quick_actions?: list<string>,

 *   folder?: string

 * }>

 */

return [

    'furnizori' => [

        'slugs' => ['furnizori', 'suppliers', 'supplier', 'addfurnizori', 'profilefurnizori'],

        'paths' => [

            '/admin/furnizori',

            '/admin/suppliers',

            '/admin/supplier',

            '/admin/addfurnizori',

            '/admin/profilefurnizori',

            '/admin/public/furnizori',

            '/admin/public/suppliers',

            '/admin/public/addfurnizori',

            '/admin/public/profilefurnizori',

            '/admin/public/api/furnizori_endpoint.php',

        ],

        'crud_key' => 'furnizori',

        'api_scripts' => ['furnizori_endpoint.php'],

        'quick_actions' => ['suppliers', 'furnizori'],

        'folder' => 'furnizori',

        'workspace' => 'orders',

    ],

    'clienti' => [

        'slugs' => ['clienti', 'customers', 'customer', 'addclienti', 'profileclienti'],

        'paths' => [

            '/admin/clienti',

            '/admin/addclienti',

            '/admin/profileclienti',

            '/admin/public/clienti',

            '/admin/public/addclienti',

            '/admin/public/profileclienti',

            '/admin/public/api/clienti_endpoint.php',

        ],

        'crud_key' => 'clienti',

        'api_scripts' => ['clienti_endpoint.php'],

        'quick_actions' => ['clienti'],

        'folder' => 'clienti',

        'workspace' => 'orders',

    ],

    'supplier_search' => [

        'slugs' => ['supplier-search', 'supplier-cart', 'searching', 'supplier_search'],

        'paths' => [

            '/admin/supplier-search',

            '/admin/supplier-cart',

            '/admin/searching',

            '/admin/public/supplier-search',

            '/admin/public/supplier-cart',

            '/admin/public/searching',

            '/admin/public/api/supplier_search_endpoint.php',

            '/admin/public/api/supplier_cart_endpoint.php',

        ],

        'crud_key' => null,

        'api_scripts' => ['supplier_search_endpoint.php', 'supplier_cart_endpoint.php'],

        'quick_actions' => ['supplier-search'],

        'folder' => 'supplier_search',

        'workspace' => 'orders',

    ],


    'scraper' => [
        'slugs' => ['scraper'],
        'paths' => [
            '/admin/scraper',
            '/admin/public/scraper',
            '/admin/public/api/scraper_endpoint.php',
        ],
        'crud_key' => null,
        'api_scripts' => ['scraper_endpoint.php'],
        'quick_actions' => ['scraper'],
        'folder' => 'scraper',
        'workspace' => 'ai',
    ],

    // BEGIN module_pack: scraper_web
    'scraper_web' => [
        'slugs' => ['scraper-web'],
        'paths' => ['/admin/scraper-web', '/admin/public/scraper-web', '/admin/public/api/scraper_web_endpoint.php'],
        'crud_key' => 'scraper_web',
        'api_scripts' => ['scraper_web_endpoint.php'],
        'quick_actions' => ['scraper-web'],
        'folder' => 'scraper_web',
        'workspace' => 'ai',
    ],
    // END module_pack: scraper_web

    // BEGIN module_gen: import_pro
    'import_pro' => [
        'slugs' => ['import-pro'],
        'paths' => [
            '/admin/import-pro',
            '/admin/public/import-pro',
            '/admin/public/api/import_pro_endpoint.php',
            '/admin/public/api/import_pro_motor_endpoint.php',
        ],
        'crud_key' => 'import_pro',
        'api_scripts' => ['import_pro_endpoint.php', 'import_pro_motor_endpoint.php'],
        'quick_actions' => ['import-pro'],
        'folder' => 'import_pro',
        'workspace' => 'suppliers',
    ],
    // END module_gen: import_pro

    // BEGIN module_gen: import
    'import' => [
        'slugs' => ['import-system', 'import-cards', 'import-bovsoft', 'import', 'importproduse'],
        'paths' => [
            '/admin/import-system',
            '/admin/public/import-system',
            '/admin/import-cards',
            '/admin/public/import-cards',
            '/admin/import-bovsoft',
            '/admin/public/import-bovsoft',
            '/admin/import',
            '/admin/public/import',
            '/admin/importproduse',
            '/admin/public/importproduse',
            '/admin/public/bovsoft-import',
            '/admin/public/api/import_endpoint.php',
            '/admin/public/api/import_bridge_endpoint.php',
            '/admin/public/api/supplier_sync_endpoint.php',
        ],
        'crud_key' => 'import',
        'api_scripts' => ['import_endpoint.php', 'import_bridge_endpoint.php', 'supplier_sync_endpoint.php'],
        'quick_actions' => ['import-system', 'import-cards', 'import-bovsoft', 'import'],
        'folder' => 'import',
        'workspace' => 'suppliers',
    ],
    // END module_gen: import

    'nav' => [
        'slugs' => ['nav'],
        'paths' => [
            '/admin/nav',
            '/admin/public/nav',
            '/admin/public/api/nav_registry_endpoint.php',
        ],
        'crud_key' => null,
        'api_scripts' => ['nav_registry_endpoint.php'],
        'quick_actions' => ['nav'],
        'folder' => 'nav',
        'workspace' => 'company',
    ],

    // BEGIN module_gen: produse
    'produse' => [
        'slugs' => ['product', 'vitrina', 'scanned', 'addproduse', 'editproduse', 'produse'],
        'paths' => [
            '/admin/product',
            '/admin/vitrina',
            '/admin/scanned',
            '/admin/addproduse',
            '/admin/editproduse',
            '/admin/produse',
            '/admin/public/product',
            '/admin/public/vitrina',
            '/admin/public/scanned',
            '/admin/public/addproduse',
            '/admin/public/editproduse',
            '/admin/public/api/produse_endpoint.php',
            '/admin/crudproduse',
            '/admin/public/crudproduse',
        ],
        'crud_key' => 'produse',
        'api_scripts' => ['produse_endpoint.php'],
        'quick_actions' => ['product', 'produse'],
        'folder' => 'produse',
        'workspace' => 'suppliers',
    ],
    // END module_gen: produse

    // BEGIN module_gen: coada_import
    'coada_import' => [
        'slugs' => ['importreview', 'import-review'],
        'paths' => [
            '/admin/importreview',
            '/admin/import-review',
            '/admin/public/importreview',
            '/admin/public/api/coada_import_endpoint.php',
            '/admin/public/api/import_action_endpoint.php',
        ],
        'crud_key' => 'coada_import',
        'api_scripts' => ['coada_import_endpoint.php', 'import_action_endpoint.php'],
        'quick_actions' => ['importreview'],
        'folder' => 'coada_import',
        'workspace' => 'suppliers',
    ],
    // END module_gen: coada_import

    // BEGIN module_gen: adaos_comercial
    'adaos_comercial' => [
        'slugs' => ['adaoscomercial', 'adaos-comercial'],
        'paths' => [
            '/admin/adaoscomercial',
            '/admin/adaos-comercial',
            '/admin/public/adaoscomercial',
            '/admin/public/api/adaos_comercial_endpoint.php',
            '/admin/crudadaoscomercial',
            '/admin/public/crudadaoscomercial',
        ],
        'crud_key' => 'adaos_comercial',
        'api_scripts' => ['adaos_comercial_endpoint.php'],
        'quick_actions' => ['adaoscomercial'],
        'folder' => 'adaos_comercial',
        'workspace' => 'suppliers',
    ],
    // END module_gen: adaos_comercial

    // BEGIN module_gen: categorii
    'categorii' => [
        'slugs' => ['categorii'],
        'paths' => [
            '/admin/categorii',
            '/admin/public/categorii',
            '/admin/public/api/categorii_endpoint.php',
            '/admin/crudcategorii',
            '/admin/public/crudcategorii',
        ],
        'crud_key' => 'categorii',
        'api_scripts' => ['categorii_endpoint.php'],
        'quick_actions' => ['categorii'],
        'folder' => 'categorii',
        'workspace' => 'suppliers',
    ],
    // END module_gen: categorii

    // BEGIN module_gen: product_formation
    'product_formation' => [
        'slugs' => ['product-formation', 'produse-formare'],
        'paths' => [
            '/admin/product-formation',
            '/admin/produse-formare',
            '/admin/public/product-formation',
            '/admin/public/api/product_card_formation_endpoint.php',
        ],
        'crud_key' => 'product_formation',
        'api_scripts' => ['product_card_formation_endpoint.php'],
        'quick_actions' => ['product-formation'],
        'folder' => 'product_formation',
        'workspace' => 'suppliers',
    ],
    // END module_gen: product_formation
];

