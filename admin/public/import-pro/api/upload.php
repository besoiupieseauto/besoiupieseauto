<?php

declare(strict_types=1);



require_once __DIR__ . '/bootstrap.php';



if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {

    http_response_code(204);

    exit;

}



if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    import_json_response(['success' => false, 'error' => 'Metoda POST obligatorie'], 405);

}



import_motor_api_run(static function (): void {

    $supplier = strtolower(trim((string) ($_POST['supplier'] ?? '')));

    if ($supplier === '') {

        import_json_response([

            'success' => false,

            'error' => 'Selectează un furnizor înregistrat în modulul Furnizori (Admin → Furnizori).',

            'code' => 'supplier_required',

        ], 400);

    }



    $profile = import_motor_require_allowed_supplier($supplier);



    $settings = json_decode((string) @file_get_contents(IMPORT_CONFIG . DIRECTORY_SEPARATOR . 'settings.json'), true) ?: [];

    $maxMb = max(50, (int) ($settings['max_upload_mb'] ?? 50));



    $destDir = import_motor_supplier_feed_dir((string) ($profile['code'] ?? ''), (int) ($profile['randomn_id'] ?? 0));
    if ($destDir === '') {
        $destDir = (string) ($profile['feed_dir'] ?? '');
    }

    if ($destDir === '') {

        import_json_response(['success' => false, 'error' => 'Folder feed indisponibil pentru furnizor'], 500);

    }



    if (!is_dir($destDir) && !@mkdir($destDir, 0775, true) && !is_dir($destDir)) {

        import_json_response([

            'success' => false,

            'error' => 'Nu pot crea directorul destinație (permisiuni?): ' . ($profile['feed_folder_rel'] ?? $destDir),

        ], 500);

    }



    if (!is_writable($destDir)) {

        import_json_response([

            'success' => false,

            'error' => 'Folderul destinație nu are permisiuni de scriere: ' . ($profile['feed_folder_rel'] ?? $destDir),

        ], 500);

    }



    $uploaded = [];

    $errors = [];

    $files = import_normalize_upload_files();



    if ($files === []) {

        $postMax = ini_get('post_max_size') ?: '?';

        $uploadMax = ini_get('upload_max_filesize') ?: '?';

        import_json_response([

            'success' => false,

            'error' => 'Niciun fișier primit de server. Verifică limitele PHP (post_max_size=' . $postMax . ', upload_max_filesize=' . $uploadMax . ').',

            'code' => 'no_files',

            'php_limits' => [

                'post_max_size' => $postMax,

                'upload_max_filesize' => $uploadMax,

                'max_upload_mb_app' => $maxMb,

            ],

        ], 400);

    }



    $count = is_array($files['name'] ?? null) ? count($files['name']) : 0;



    for ($i = 0; $i < $count; $i++) {

        $error = (int) ($files['error'][$i] ?? UPLOAD_ERR_NO_FILE);

        $original = (string) ($files['name'][$i] ?? 'upload.csv');



        if ($error !== UPLOAD_ERR_OK) {

            $errors[] = [

                'name' => $original,

                'error' => import_motor_upload_error_message($error, $maxMb),

                'code' => $error,

            ];

            continue;

        }



        $size = (int) ($files['size'][$i] ?? 0);

        if ($size > $maxMb * 1024 * 1024) {

            $errors[] = [

                'name' => $original,

                'error' => 'Fișier prea mare (' . round($size / 1048576, 1) . ' MB). Limita Import Pro: ' . $maxMb . ' MB.',

            ];

            continue;

        }



        $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));

        if (!in_array($ext, ['csv', 'txt'], true)) {

            $errors[] = ['name' => $original, 'error' => 'Extensie invalidă (accept: csv, txt)'];

            continue;

        }



        $safe = preg_replace('/[^A-Za-z0-9._\-\s]+/u', '_', $original) ?: 'upload.csv';

        $safe = trim(preg_replace('/\s+/', ' ', $safe) ?? $safe) ?: 'upload.csv';

        $dest = $destDir . DIRECTORY_SEPARATOR . $safe;

        if (is_file($dest)) {

            $dest = $destDir . DIRECTORY_SEPARATOR . date('Ymd_His') . '_' . $safe;

        }



        $tmp = (string) ($files['tmp_name'][$i] ?? '');

        if ($tmp === '' || !is_uploaded_file($tmp)) {

            $errors[] = [

                'name' => $original,

                'error' => 'Fișier temporar invalid — posibil depășire post_max_size/upload_max_filesize pe server.',

            ];

            continue;

        }



        if (!@move_uploaded_file($tmp, $dest)) {

            $errors[] = [

                'name' => $original,

                'error' => 'Nu pot salva fișierul în ' . ($profile['feed_folder_rel'] ?? $destDir) . ' — verifică permisiunile folderului.',

            ];

            continue;

        }



        @chmod($dest, 0664);



        $uploaded[] = [

            'original' => $original,

            'saved_as' => basename($dest),

            'path' => $dest,

            'supplier' => $supplier,

            'supplier_code' => $profile['code'],

            'supplier_name' => $profile['name'],

            'destination' => $profile['feed_folder_rel'],

            'markup_percent' => $profile['price_markup_percent'],

        ];

    }



    if ($uploaded === [] && $errors !== []) {

        import_json_response([

            'success' => false,

            'error' => (string) ($errors[0]['error'] ?? 'Upload eșuat'),

            'errors' => $errors,

            'supplier_profile' => [

                'slug' => $profile['slug'],

                'code' => $profile['code'],

                'name' => $profile['name'],

                'feed_folder' => $profile['feed_folder_rel'],

            ],

        ], 400);

    }



    import_json_response([

        'success' => true,

        'uploaded' => $uploaded,

        'errors' => $errors,

        'supplier_profile' => [

            'slug' => $profile['slug'],

            'code' => $profile['code'],

            'name' => $profile['name'],

            'feed_folder' => $profile['feed_folder_rel'],

        ],

    ]);

}, 'upload.php');



function import_motor_upload_error_message(int $code, int $maxMb): string

{

    return match ($code) {

        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Fișier prea mare pentru limitele PHP/server (max aplicație ' . $maxMb . ' MB). Contactează hosting sau mărește upload_max_filesize și post_max_size.',

        UPLOAD_ERR_PARTIAL => 'Upload întrerupt — fișier parțial.',

        UPLOAD_ERR_NO_FILE => 'Niciun fișier trimis.',

        UPLOAD_ERR_NO_TMP_DIR => 'Lipsește folderul temporar PHP (upload_tmp_dir).',

        UPLOAD_ERR_CANT_WRITE => 'Nu pot scrie pe disc (permisiuni server).',

        UPLOAD_ERR_EXTENSION => 'Upload blocat de extensia PHP.',

        default => 'Eroare upload cod ' . $code,

    };

}


