<?php
declare(strict_types=1);

$metroRoot = defined('BESOIU_ROOT')
    ? str_replace('\\', '/', (string) BESOIU_ROOT)
    : dirname(__DIR__, 3);

return [
    'candidates' => [
        getenv('BESOIU_IMPORT_MATCHING_ROOT') ?: '',
        $metroRoot . '/app/Import/MatchingPro',
        defined('BESOIU_APP') ? BESOIU_APP . '/Import/MatchingPro' : '',
    ],
    'public_url' => '/admin/import-pro',
    'standalone_url' => '/admin/public/import-pro/',
    'api_base' => '/admin/public/import-pro/api/',
    'import_data_root' => getenv('BESOIU_IMPORT_DATA_ROOT') ?: '',
    'erp_public_root' => $metroRoot . '/admin/public/import-pro',
];
