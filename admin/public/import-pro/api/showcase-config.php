<?php

declare(strict_types=1);



require_once __DIR__ . '/bootstrap.php';

require_once __DIR__ . '/lib/ImportShowcaseEpiesaRag.php';

require_once __DIR__ . '/lib/ImportShowcaseConfig.php';



if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {

    http_response_code(204);

    exit;

}



if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    $config = ImportShowcaseConfig::load();

    import_json_response([

        'success' => true,

        'config' => $config,

        'rag' => ImportShowcaseEpiesaRag::stats(),

    ]);

}



if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    import_json_response(['success' => false, 'error' => 'GET sau POST'], 405);

}



import_motor_api_run(static function (): void {

    $raw = file_get_contents('php://input');

    $data = json_decode($raw ?: '', true);

    if (!is_array($data)) {

        $data = $_POST;

    }



    /** @var list<string> $types */

    $types = is_array($data['types'] ?? null) ? $data['types'] : [];

    /** @var list<string>|null $scanEnabled */

    $scanEnabled = is_array($data['scan_enabled'] ?? null) ? $data['scan_enabled'] : null;

    $minScore = max(50, min(100, (int) ($data['match_min_score'] ?? 72)));



    $config = ImportShowcaseConfig::save($types, $scanEnabled, $minScore);



    import_json_response([

        'success' => true,

        'config' => $config,

        'rag' => ImportShowcaseEpiesaRag::stats(),

    ]);

}, 'showcase-config.php');

