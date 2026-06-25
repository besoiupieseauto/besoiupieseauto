<?php

declare(strict_types=1);

/**
 * Registru surse web scraping — carduri în /admin/scraper.
 * Configurația salvată per sursă: storage/scraper/sources/{id}.json
 */
return [
    'epiesa' => [
        'label' => 'ePiesa.ro',
        'domain' => 'epiesa.ro',
        'color' => '#e11d48',
        'icon' => 'EP',
        'fetch_via' => 'scrape_do',
        'env_required' => ['SCRAPE_DO_TOKEN'],
        'parser_builtin' => 'EpiesaCategoryParser',
        'roles' => ['image', 'title', 'price_hint', 'category'],
        'description' => 'Căutare piese și liste categorii (uleiuri, consumabile).',
    ],
    'emag' => [
        'label' => 'eMAG.ro',
        'domain' => 'emag.ro',
        'color' => '#f59e0b',
        'icon' => 'eM',
        'fetch_via' => 'scrape_do',
        'env_required' => ['SCRAPE_DO_TOKEN'],
        'parser_builtin' => 'EmagSearchParser',
        'roles' => ['image', 'title'],
        'description' => 'Căutare produse auto / uleiuri pe marketplace.',
    ],
    'autodoc' => [
        'label' => 'Autodoc.ro',
        'domain' => 'autodoc.ro',
        'color' => '#2563eb',
        'icon' => 'AD',
        'fetch_via' => 'scrape_do',
        'env_required' => ['SCRAPE_DO_TOKEN'],
        'parser_builtin' => null,
        'roles' => ['image', 'title', 'oem'],
        'description' => 'Catalog piese — configurează selectori și testează.',
        'stub' => true,
    ],
    'pieseauto' => [
        'label' => 'PieseAuto.ro',
        'domain' => 'pieseauto.ro',
        'color' => '#059669',
        'icon' => 'PA',
        'fetch_via' => 'scrape_do',
        'env_required' => ['SCRAPE_DO_TOKEN'],
        'parser_builtin' => null,
        'roles' => ['image', 'title'],
        'description' => 'Magazin piese — pași custom de configurat.',
        'stub' => true,
    ],
    'autovit' => [
        'label' => 'Autovit',
        'domain' => 'autovit.ro',
        'color' => '#7c3aed',
        'icon' => 'AV',
        'fetch_via' => 'scrape_do',
        'env_required' => ['SCRAPE_DO_TOKEN'],
        'parser_builtin' => null,
        'roles' => ['image'],
        'description' => 'Anunțuri piese — opțional viitor.',
        'stub' => true,
    ],
    'tecdoc_api' => [
        'label' => 'TecDoc API',
        'domain' => 'rapidapi.com',
        'color' => '#0d9488',
        'icon' => 'TD',
        'fetch_via' => 'rapidapi',
        'env_required' => ['RAPIDAPI_AUTOPARTS_KEY'],
        'parser_builtin' => null,
        'roles' => ['image', 'description', 'oem'],
        'description' => 'JSON API — fără HTML, articol + imagine oficială.',
        'no_html' => true,
    ],
];
