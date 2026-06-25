<?php

declare(strict_types=1);

/**
 * Reguli parsare scraping — documentație + selectori per sursă.
 * Folosit în /admin/scraper (tab Reguli + Test).
 */
return [
    'scrape_do' => [
        'label' => 'scrape.do (proxy HTTP)',
        'api_base' => 'https://api.scrape.do',
        'env_token' => 'SCRAPE_DO_TOKEN',
        'defaults' => [
            'timeout_sec' => 90,
            'super' => false,
            'render' => false,
        ],
        'flow' => [
            'Trimite GET către api.scrape.do/?url={TARGET}&token={TOKEN}',
            'Primești HTML brut al paginii țintă',
            'Parserul sursei extrage câmpurile din HTML',
        ],
    ],

    'sources' => [
        'epiesa_category' => [
            'label' => 'ePiesa — listă categorie',
            'parser_class' => 'EpiesaCategoryParser',
            'parser_file' => 'lib/Scraper/EpiesaCategoryParser.php',
            'url_template' => 'https://www.epiesa.ro/gmtn1:auto/gmtn2:{slug}/',
            'example_url' => 'https://www.epiesa.ro/gmtn1:auto/gmtn2:uleiuri-si-lubrifianti-auto/',
            'fetch_via' => 'scrape_do',
            'selectors' => [
                'product_card' => "//div[contains(@class,'sub-product-inner')]",
                'title' => ".//*[contains(@class,'product-auto-title')]//a[@href]",
                'image' => ".//*[contains(@class,'sub-product-img')]//img",
                'price' => ".//*[contains(@class,'bricolaje-bottom-text')]//h4",
                'details' => ".//*[contains(@class,'sub-product-detail')]//p",
            ],
            'ignore' => [
                'img src conține star-fill sau placeholder',
                'link-uri goale (#)',
            ],
            'output_fields' => ['title', 'url', 'image', 'price', 'description', 'details'],
        ],
        'epiesa' => [
            'label' => 'ePiesa — căutare produs',
            'parser_class' => 'EpiesaCategoryParser',
            'parser_file' => 'lib/Scraper/EpiesaCategoryParser.php',
            'url_template' => 'https://www.epiesa.ro/cautare-piesa/?find={query}',
            'example_query' => 'ulei motor 5W30',
            'fetch_via' => 'scrape_do',
            'reuse_parser' => 'epiesa_category',
            'limit_default' => 1,
            'output_fields' => ['title', 'url', 'image', 'price', 'description'],
        ],
        'emag' => [
            'label' => 'eMAG — căutare produs',
            'parser_class' => 'EmagSearchParser',
            'parser_file' => 'lib/Scraper/EmagSearchParser.php',
            'url_template' => 'https://www.emag.ro/search/{query}?ref=effective_search',
            'example_query' => 'ulei motor 5W30',
            'fetch_via' => 'scrape_do',
            'selectors' => [
                'card_block' => 'class="card-v2" sau card-v2-wrapper',
                'title' => 'class="card-v2-title"',
                'image' => 'img src emagst.akamaized.net/products/',
                'product_url' => 'class="card-v2-thumb" href',
            ],
            'ignore' => [
                'imagini fără emagst.akamaized.net/products',
            ],
            'output_fields' => ['image_url', 'title', 'product_url'],
        ],
        'tecdoc_api' => [
            'label' => 'TecDoc RapidAPI',
            'parser_class' => null,
            'fetch_via' => 'rapidapi',
            'env_required' => ['RAPIDAPI_AUTOPARTS_KEY'],
            'note' => 'Nu folosește HTML — JSON API articol + imagine',
        ],
        'caietcomenzi' => [
            'label' => 'Caiet comenzi (legacy DB)',
            'parser_class' => null,
            'fetch_via' => 'mysql_legacy',
            'note' => 'Caută TTC_ART_ID / imagini din ERP Laravel',
        ],
    ],

    'integrations' => [
        'import_cron' => [
            'label' => 'Cron import furnizori',
            'config' => 'admin/config/cron_import.php',
            'env' => ['CRON_IMPORT_MODE', 'IMAGE_SEARCH_SOURCES', 'CRON_CHECK_EPIESA'],
        ],
        'image_pipeline' => [
            'label' => 'Pipeline imagini import',
            'config' => 'config/image-search-sources.php',
            'code' => 'system/image_search_pipeline.php',
        ],
        'image_audit' => [
            'label' => 'Audit imagini Cursor',
            'env' => ['CURSOR_API_KEY', 'IMAGE_AUDIT_ENGINE'],
            'admin' => '/admin/public/produse',
        ],
        'vitrina' => [
            'label' => 'Vitrină homepage ePiesa',
            'storage' => 'storage/scraper/json/products_catalog.json',
            'admin' => '/admin/scraper',
        ],
    ],
];
