<?php
declare(strict_types=1);



/**

 * Motor Scraper — acum în app/Import/Scraper (besoiupieseauto.ro).

 * Date mari (Poze, CSV) rămân la BESOIU_IMPORT_DATA_ROOT până la mutare completă.

 */

$metroRoot = defined('BESOIU_ROOT')

    ? str_replace('\\', '/', (string) BESOIU_ROOT)

    : dirname(__DIR__, 3);



return [

    'candidates' => [

        getenv('BESOIU_SCRAPER_ROOT') ?: '',

        dirname(__DIR__, 2) . '/Import/Scraper',

        $metroRoot . '/app/Import/Scraper',

        'F:/laragon/www/besoiupieseauto.ro/app/Import/Scraper',

        'F:/laragon/www/besoiupieseimport/Scraper',

    ],

    'public_url' => '/admin/scraper-web',

    'assets_root' => $metroRoot . '/assets/scraper',

    'assets_public_url' => '/assets/scraper',

    'import_data_root' => getenv('BESOIU_IMPORT_DATA_ROOT') ?: 'F:/laragon/www/besoiupieseimport',

];

