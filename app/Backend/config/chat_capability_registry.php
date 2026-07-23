<?php

declare(strict_types=1);

/**
 * Organism Chat Besoiu — registry 1:1 module admin → interogări + acțiuni.
 *
 * kind: query (SQL live), action (modifică date, confirmare), navigate (deschide UI)
 * status: live | navigate_only | planned
 */
return [
    'version' => '1.0.0',
    'organism' => 'besoiu-chat-organism',
    'modules' => [
        'produse' => [
            'label' => 'Produse & catalog',
            'url' => '/admin/product',
            'capabilities' => [
                ['id' => 'produse.list', 'kind' => 'query', 'handler' => 'product_list', 'status' => 'live', 'label' => 'Lista toate produsele', 'examples' => ['lista tuturor produselor', 'arata toate produsele', 'toate produsele active'], 'patterns' => ['lista', 'toate produse', 'list[aă] complet']],
                ['id' => 'produse.inventory', 'kind' => 'query', 'handler' => 'product_inventory', 'status' => 'live', 'label' => 'Rezumat inventar', 'examples' => ['cate produse sunt online', 'inventar produse', 'statistici catalog'], 'patterns' => ['cate produse', 'inventar', 'rezumat', 'statistici']],
                ['id' => 'produse.no_image', 'kind' => 'query', 'handler' => 'product_filter', 'status' => 'live', 'label' => 'Produse fara imagine', 'examples' => ['produse fara imagine', 'care nu au poza'], 'patterns' => ['fara imagine', 'fără imagine', 'lipsa imag']],
                ['id' => 'produse.by_code', 'kind' => 'query', 'handler' => 'product_by_code', 'status' => 'live', 'label' => 'Cautare dupa cod OEM', 'examples' => ['produs cod 1457434437', 'cauta OEM'], 'patterns' => ['cod ', 'oem ']],
                ['id' => 'produse.categories', 'kind' => 'query', 'handler' => 'category_list', 'status' => 'live', 'label' => 'Lista categorii', 'examples' => ['ce categorii sunt', 'lista categorii produse'], 'patterns' => ['categorii', 'subcategorii']],
                ['id' => 'produse.vitrina', 'kind' => 'query', 'handler' => 'vitrina_homepage', 'status' => 'live', 'label' => 'Produse vitrina homepage', 'examples' => ['ce e pe vitrina', 'produse homepage'], 'patterns' => ['vitrin', 'homepage']],
                ['id' => 'produse.badge', 'kind' => 'action', 'action' => 'set_badge', 'status' => 'live', 'label' => 'Set badge HOT/PROMO/NOU', 'examples' => ['badge HOT toate produsele', 'pune PROMO pe filtru'], 'patterns' => ['badge ', 'hot ', 'promo ']],
                ['id' => 'produse.edit_title', 'kind' => 'action', 'action' => 'edit_product_title', 'status' => 'live', 'label' => 'Edit titlu produs', 'examples' => ['edit titlu 30204A adauga categorie'], 'patterns' => ['edit titlu', 'schimba titlu', 'modifica nume produs']],
                ['id' => 'produse.vitrina_set', 'kind' => 'action', 'action' => 'vitrina', 'status' => 'live', 'label' => 'Adauga/scoate vitrina', 'examples' => ['pune pe vitrina cod X', 'scoate de pe vitrina'], 'patterns' => ['vitrin', 'homepage']],
                ['id' => 'produse.crud', 'kind' => 'action', 'action' => 'product_crud', 'status' => 'live', 'label' => 'CRUD camp produs', 'examples' => ['modifica pret 150 cod 30204A', 'categorie Frane cod X', 'sterge imagini cod X', 'ascunde produs cod X'], 'patterns' => ['modific', 'schimb', 'setez', 'pret ', 'stoc ', 'categor', 'brand ', 'sterge imag', 'ascunde produs']],
                ['id' => 'produse.image_url', 'kind' => 'action', 'action' => 'product_crud', 'status' => 'live', 'label' => 'Imagine din URL', 'examples' => ['pune imagine https://example.com/poza.jpg cod X'], 'patterns' => ['imagine http', 'poza http', 'incarca imag']],
                ['id' => 'produse.description', 'kind' => 'action', 'action' => 'product_crud', 'status' => 'live', 'label' => 'Descriere produs (pNote)', 'examples' => ['descriere "Filtru ulei MANN" cod X'], 'patterns' => ['descriere', 'pnote', 'nota ']],
                ['id' => 'produse.crud_bulk', 'kind' => 'action', 'action' => 'product_crud', 'status' => 'live', 'label' => 'CRUD bulk (selectie)', 'examples' => ['bifeaza + pret 150', 'bifeaza + categorie Frane', 'bifeaza + sterge imagini'], 'patterns' => ['selectat', 'bifat', 'bulk']],
                ['id' => 'produse.add', 'kind' => 'navigate', 'status' => 'navigate_only', 'label' => 'Adauga produs', 'url' => '/admin/addproduse', 'examples' => ['adauga produs nou']],
                ['id' => 'produse.audit_images', 'kind' => 'navigate', 'status' => 'navigate_only', 'label' => 'Audit imagini', 'url' => '/admin/product#image-audit', 'examples' => ['audit imagini produse']],
            ],
        ],
        'import' => [
            'label' => 'Import CSV / pipeline',
            'url' => '/admin/import',
            'capabilities' => [
                ['id' => 'import.queue', 'kind' => 'query', 'handler' => 'import_queue', 'status' => 'live', 'label' => 'Coada import staging', 'examples' => ['coada import', 'produse pending import'], 'patterns' => ['coada import', 'staging', 'pending import']],
                ['id' => 'import.readiness', 'kind' => 'query', 'handler' => 'import_readiness', 'status' => 'live', 'label' => 'Pregatit pentru import?', 'examples' => ['sunt pregatit sa import', 'e totul ok import'], 'patterns' => ['pregatit import', 'gata import', 'readiness']],
                ['id' => 'import.publish', 'kind' => 'action', 'action' => 'import_publish', 'status' => 'live', 'label' => 'Publica din coada', 'examples' => ['publica 5 ulei din import', 'publish selected import'], 'patterns' => ['publica', 'publish']],
                ['id' => 'import.scan_images', 'kind' => 'action', 'action' => 'extended_import_images', 'status' => 'live', 'label' => 'Scan imagini coada', 'examples' => ['scan imagini coada import'], 'patterns' => ['scan imagini', 'refresh imagini']],
                ['id' => 'import.consumable', 'kind' => 'action', 'action' => 'extended_consumable_scan', 'status' => 'live', 'label' => 'Scan consumabile CSV', 'examples' => ['porneste scan consumabil ulei'], 'patterns' => ['scan consumabil', 'consumabile']],
                ['id' => 'import.upload', 'kind' => 'navigate', 'status' => 'navigate_only', 'label' => 'Upload CSV', 'url' => '/admin/import', 'examples' => ['upload csv furnizor']],
            ],
        ],
        'furnizori' => [
            'label' => 'Furnizori B2B',
            'url' => '/admin/suppliers',
            'capabilities' => [
                ['id' => 'furnizori.list', 'kind' => 'query', 'handler' => 'supplier_list', 'status' => 'live', 'label' => 'Lista furnizori', 'examples' => ['ce furnizori sunt', 'lista furnizori'], 'patterns' => ['furnizor', 'supplier']],
                ['id' => 'furnizori.compare', 'kind' => 'query', 'handler' => 'supplier_compare', 'status' => 'live', 'label' => 'Comparare furnizori', 'examples' => ['comparare furnizori import'], 'patterns' => ['compar', 'pozitionare furnizor']],
                ['id' => 'furnizori.search', 'kind' => 'query', 'handler' => 'supplier_search', 'status' => 'live', 'label' => 'Cautare furnizor', 'examples' => ['cauta furnizor autonet'], 'patterns' => ['furnizor autonet', 'furnizor materom']],
                ['id' => 'furnizori.reorder', 'kind' => 'action', 'action' => 'extended_supplier_reorder', 'status' => 'live', 'label' => 'Reordoneaza furnizor scan', 'examples' => ['pune autonet primul'], 'patterns' => ['primul', 'poziti', 'reordoneaz']],
            ],
        ],
        'adaos' => [
            'label' => 'Adaos comercial',
            'url' => '/admin/adaoscomercial',
            'capabilities' => [
                ['id' => 'adaos.summary', 'kind' => 'query', 'handler' => 'adaos_comercial', 'status' => 'live', 'label' => 'Reguli adaos active', 'examples' => ['ce adaos avem', 'reguli adaos'], 'patterns' => ['adaos', 'markup', 'tva']],
                ['id' => 'adaos.set_global', 'kind' => 'action', 'action' => 'extended_adaos_global', 'status' => 'live', 'label' => 'Set adaos global %', 'examples' => ['seteaza adaos global 35%'], 'patterns' => ['adaos global', 'seteaza adaos']],
                ['id' => 'adaos.apply_rule', 'kind' => 'action', 'action' => 'extended_adaos_apply', 'status' => 'live', 'label' => 'Aplica regula adaos', 'examples' => ['aplica regula adaos 2'], 'patterns' => ['aplica regula adaos']],
                ['id' => 'adaos.create_rule', 'kind' => 'action', 'action' => 'extended_adaos_create', 'status' => 'live', 'label' => 'Creaza regula adaos', 'examples' => ['adauga regula adaos 25% categorie ulei'], 'patterns' => ['adauga regula adaos']],
            ],
        ],
        'comenzi' => [
            'label' => 'Comenzi & vanzari',
            'url' => '/admin/orders',
            'capabilities' => [
                ['id' => 'comenzi.summary', 'kind' => 'query', 'handler' => 'orders_summary', 'status' => 'live', 'label' => 'Rezumat comenzi', 'examples' => ['cate comenzi sunt', 'comenzi noi azi'], 'patterns' => ['comenz', 'orders']],
                ['id' => 'comenzi.client', 'kind' => 'query', 'handler' => 'client_orders', 'status' => 'live', 'label' => 'Comenzi client', 'examples' => ['comenzi pentru Radu Galac'], 'patterns' => ['comenzi pentru', 'comanda client']],
                ['id' => 'comenzi.cart', 'kind' => 'query', 'handler' => 'cart_list', 'status' => 'live', 'label' => 'Cos / abandon', 'examples' => ['cos abandonat', 'cosuri site'], 'patterns' => ['cos abandon', 'cos ', 'cart ']],
                ['id' => 'comenzi.invoices', 'kind' => 'query', 'handler' => 'invoice_list', 'status' => 'live', 'label' => 'Lista facturi', 'examples' => ['lista facturi', 'facturi recente'], 'patterns' => ['factur']],
                ['id' => 'comenzi.awb', 'kind' => 'query', 'handler' => 'awb_list', 'status' => 'live', 'label' => 'AWB / livrare', 'examples' => ['lista awb', 'curier expedieri'], 'patterns' => ['awb', 'curier', 'livrare awb']],
                ['id' => 'comenzi.create', 'kind' => 'navigate', 'status' => 'navigate_only', 'label' => 'Comanda noua', 'url' => '/admin/order-create', 'examples' => ['creeaza comanda noua']],
            ],
        ],
        'clienti' => [
            'label' => 'Clienti CRM',
            'url' => '/admin/clienti',
            'capabilities' => [
                ['id' => 'clienti.list', 'kind' => 'query', 'handler' => 'client_list', 'status' => 'live', 'label' => 'Lista clienti', 'examples' => ['cate clienti am', 'lista clienti'], 'patterns' => ['clienti', 'client ']],
            ],
        ],
        'comunicare' => [
            'label' => 'Comunicare & mesaje',
            'url' => '/admin/comunicare',
            'capabilities' => [
                ['id' => 'comunicare.summary', 'kind' => 'query', 'handler' => 'comunicare_summary', 'status' => 'live', 'label' => 'Mesaje & leads', 'examples' => ['mesaje necitite', 'lead uri contact'], 'patterns' => ['mesaj', 'comunicare', 'lead', 'whatsapp', 'broadcast']],
                ['id' => 'comunicare.templates', 'kind' => 'navigate', 'status' => 'navigate_only', 'label' => 'Template raspuns', 'url' => '/admin/reply-templates', 'examples' => ['template raspuns whatsapp']],
            ],
        ],
        'scraper' => [
            'label' => 'Scraper & imagini',
            'url' => '/admin/scraper',
            'capabilities' => [
                ['id' => 'scraper.pipeline', 'kind' => 'navigate', 'status' => 'navigate_only', 'label' => 'Pipeline scraper', 'url' => '/admin/scraper', 'examples' => ['deschide scraper plan 1']],
                ['id' => 'scraper.repair', 'kind' => 'action', 'action' => 'composer_repair', 'status' => 'live', 'label' => 'Repara job blocat', 'examples' => ['repara job import blocat', 'fix alert scraper'], 'patterns' => ['repara', 'fix ', 'job blocat']],
            ],
        ],
        'automatizare' => [
            'label' => 'AI & roboți',
            'url' => '/admin/ai-agent',
            'capabilities' => [
                ['id' => 'ai.supervisor', 'kind' => 'navigate', 'status' => 'navigate_only', 'label' => 'Supervizor AI', 'url' => '/admin/ai-agent', 'examples' => ['deschide supervizor ai']],
                ['id' => 'ai.bots', 'kind' => 'navigate', 'status' => 'navigate_only', 'label' => 'Roboți WhatsApp/FB', 'url' => '/admin/bots', 'examples' => ['deschide robot whatsapp']],
                ['id' => 'ai.catalog', 'kind' => 'query', 'handler' => 'admin_full_catalog', 'status' => 'live', 'label' => 'Catalog complet functii admin', 'examples' => ['ce pot face in admin', 'lista completa module'], 'patterns' => ['catalog admin', 'toate functi', 'ce pot face']],
            ],
        ],
        'website' => [
            'label' => 'Site & CMS',
            'url' => '/admin/website',
            'capabilities' => [
                ['id' => 'website.cms', 'kind' => 'navigate', 'status' => 'navigate_only', 'label' => 'Editor pagini site', 'url' => '/admin/website', 'examples' => ['editeaza pagina acasa']],
                ['id' => 'website.blog', 'kind' => 'navigate', 'status' => 'navigate_only', 'label' => 'Blog', 'url' => '/admin/blog', 'examples' => ['lista articole blog']],
            ],
        ],
        'sistem' => [
            'label' => 'Sistem & setari',
            'url' => '/admin/settings',
            'capabilities' => [
                ['id' => 'sistem.alerts', 'kind' => 'navigate', 'status' => 'navigate_only', 'label' => 'Alerte ops', 'url' => '/admin/alerts', 'examples' => ['alerte sistem', 'erori red flag']],
                ['id' => 'sistem.settings', 'kind' => 'navigate', 'status' => 'navigate_only', 'label' => 'Setari API', 'url' => '/admin/settings', 'examples' => ['setari api keys']],
                ['id' => 'sistem.export', 'kind' => 'navigate', 'status' => 'navigate_only', 'label' => 'Export hub', 'url' => '/admin/export', 'examples' => ['export produse csv']],
            ],
        ],
        'site_public' => [
            'label' => 'Site public (magazin)',
            'url' => '/',
            'capabilities' => [
                ['id' => 'public.chat', 'kind' => 'query', 'handler' => 'public_chat', 'status' => 'live', 'label' => 'Chat widget vizitatori', 'examples' => ['pret filtru combustibil', 'ai stoc disc frana'], 'patterns' => ['pret', 'stoc', 'livrare', 'comanda']],
                ['id' => 'public.catalog', 'kind' => 'navigate', 'status' => 'navigate_only', 'label' => 'Catalog public', 'url' => '/catalog.php', 'examples' => ['deschide magazin']],
            ],
        ],
    ],
];
