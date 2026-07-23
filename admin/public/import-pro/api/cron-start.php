<?php

declare(strict_types=1);



require_once __DIR__ . '/bootstrap.php';



if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {

    http_response_code(204);

    exit;

}



if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    import_json_response(['success' => false, 'error' => 'POST obligatoriu'], 405);

}



import_motor_api_run(static function (): void {

    $isTest = filter_var($_POST['test'] ?? false, FILTER_VALIDATE_BOOLEAN);

    $progress = import_read_cron_progress();

    if (($progress['status'] ?? '') === 'running' && !$isTest) {

        import_json_response([

            'success' => false,

            'error' => 'Cron rulează deja — urmărește logul mai jos',

            'progress' => $progress,

        ], 409);

    }



    $force = filter_var($_POST['force'] ?? true, FILTER_VALIDATE_BOOLEAN);



    $batchSize = null;

    if (isset($_POST['batch_size']) && $_POST['batch_size'] !== '') {

        $batchSize = max(1, min(5000, (int) $_POST['batch_size']));

    }



    $displaySample = null;

    if (isset($_POST['sample']) && $_POST['sample'] !== '') {

        $displaySample = max(1, min(200, (int) $_POST['sample']));

    }



    $totalLimit = null;

    if (isset($_POST['total_limit']) && $_POST['total_limit'] !== '') {

        $totalLimit = max(1, min(5000, (int) $_POST['total_limit']));

    }



    $patch = ['mode' => 'running'];

    if ($batchSize !== null) {

        $patch['batch_size'] = $batchSize;

    }

    if ($displaySample !== null) {

        $patch['display_sample'] = $displaySample;

    }

    import_write_cron_control($patch);

    import_motor_sync_supplier_intervals_for_cron();

    // Test-ul poate fi lansat și cât cronul principal rulează — nu resetăm
    // jurnalul de staging/progresul în acest caz, ca să nu întrerupem urmărirea run-ului activ.
    $runId = date('Ymd_His');
    if (!$isTest || ($progress['status'] ?? '') !== 'running') {
        require_once __DIR__ . '/lib/CronStagingJournal.php';
        CronStagingJournal::resetRun($runId);

        // Scriem sincron un progres "proaspăt" ÎNAINTE de a porni Python, ca primul
        // poll de pe UI să nu mai afișeze datele vechi ale rulării anterioare.
        $freshMessage = $totalLimit !== null
            ? 'Pornire test cron — caut ' . $totalLimit . ' produse CU MATCH…'
            : 'Pornire cron în background…';
        import_start_fresh_cron_progress($freshMessage, $runId);
    }



    $args = ['cron'];
    if ($force) {
        $args[] = '--force';
    }
    if ($batchSize !== null) {
        $args[] = '--sample';
        $args[] = (string) $batchSize;
    }
    if ($totalLimit !== null) {
        $args[] = '--total-sample';
        $args[] = (string) $totalLimit;
    }



    $started = import_start_background_python($args);

    if (!$started) {

        import_json_response(['success' => false, 'error' => 'Nu pot porni cron în background'], 500);

    }



    import_json_response([

        'success' => true,

        'started' => true,

        'batch_size' => $batchSize ?? import_cron_batch_size(),

        'display_sample' => $displaySample,

        'total_limit' => $totalLimit,

        'message' => $totalLimit !== null
            ? 'Test cron pornit — caut ' . $totalLimit . ' produse CU MATCH (agregat pe toate fișierele)'
                . (($progress['status'] ?? '') === 'running' ? ' — rulează în paralel cu cronul activ' : '')
            : 'Cron pornit în background — urmărește progresul live',

    ]);

}, 'cron-start.php');

