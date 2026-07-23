<?php

declare(strict_types=1);



require_once __DIR__ . '/bootstrap.php';



@ini_set('memory_limit', '768M');

@set_time_limit(600);



if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {

    http_response_code(204);

    exit;

}



if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    import_json_response(['success' => false, 'error' => 'POST obligatoriu'], 405);

}



import_motor_api_run(static function (): void {

    $supplier = trim((string) ($_POST['supplier'] ?? ''));

    $filename = trim((string) ($_POST['filename'] ?? ''));

    $limit = max(1, min(200, (int) ($_POST['limit'] ?? 20)));

    $offset = max(0, min(500000, (int) ($_POST['offset'] ?? 0)));

    $scanMode = trim((string) ($_POST['scan_mode'] ?? 'continue'));

    $onlyWithImage = filter_var($_POST['only_with_image'] ?? false, FILTER_VALIDATE_BOOLEAN);



    import_motor_require_allowed_supplier(strtolower($supplier));



    $path = import_resolve_stored_file($supplier, $filename);

    if ($path === null) {

        import_json_response(['success' => false, 'error' => 'Fișier invalid sau inexistent'], 404);

    }



    require_once __DIR__ . '/lib/ImportFastCardPipeline.php';

    $response = ImportFastCardPipeline::buildPage(

        strtolower($supplier),

        $filename,

        $path,

        $limit,

        $offset,

        $onlyWithImage,

        $scanMode

    );

    $response['buildMode'] = 'mysql_tecdoc';

    import_json_response($response);

}, 'build-stored.php');

