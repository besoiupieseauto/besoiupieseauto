<?php

declare(strict_types=1);

/**
 * Registru joburi fundal — sursă unică pentru admin hub + verify_cron_setup.
 *
 * @return list<array{name: string, url: string, batch: string, schedule: string, note: string}>
 */
function admin_cron_tasks_registry(): array
{
    return [
        [
            'name' => 'Async stale job sweeper',
            'category' => 'Admin',
            'url' => 'admin/cron_cli/async_stale_job_sweeper.php',
            'batch' => 'php admin/cron_cli/async_stale_job_sweeper.php',
            'schedule' => 'La 60 sec',
            'note' => 'Marchează joburi BesoiuAsync processing/pending expirate (timeout, worker pierdut).',
        ],
        [
            'name' => 'Retry imagini pipeline (batch)',
            'category' => 'Import',
            'url' => 'admin/cron_cli/image_pipeline_retry.php',
            'batch' => 'php admin/cron_cli/image_pipeline_retry.php --limit=20',
            'schedule' => 'Nightly 02:00',
            'note' => 'Produse live fără imagine — pipeline Scraper Plan 1→N.',
        ],
        [
            'name' => 'Snapshot dashboard (CLI)',
            'category' => 'Admin',
            'url' => 'admin/scripts/refresh_dashboard_snapshot.php',
            'batch' => 'admin/scripts/run_refresh_dashboard_snapshot.bat',
            'schedule' => 'La 3 min',
            'note' => 'Actualizează JSON-ul KPI de pe homepage fără browser.',
        ],
        [
            'name' => 'Snapshot dashboard (HTTP)',
            'category' => 'Admin',
            'url' => '/admin/api/dashboard_snapshot_cron.php',
            'batch' => 'HTTP + X-Dashboard-Cron-Key',
            'schedule' => 'La 3–5 min',
            'note' => 'Alternativă curl / Task Scheduler — cheie în .env.',
        ],
        [
            'name' => 'Queue worker BaseLinker',
            'category' => 'Comenzi',
            'url' => 'admin/cron_cli/queue_worker.php',
            'batch' => 'admin/scripts/run_queue_worker.bat',
            'schedule' => 'La 1–2 min',
            'note' => 'Procesează coada queue_jobs (sync comenzi).',
        ],
        [
            'name' => 'Sync besoiupieseimport → admin',
            'category' => 'Import',
            'url' => 'admin/tools/sync_besoiupieseimport_feeds.php',
            'batch' => 'admin/scripts/run_besoiupieseimport_sync.bat',
            'schedule' => 'Automat la Cron Sync',
            'note' => 'Copiază liste preț + Base TecDoc din F:/laragon/www/besoiupieseimport în supplier_feeds/ și storage/tecdoc/. Dezactivare: BESOIUPIESEIMPORT_SYNC=0.',
        ],
        [
            'name' => 'Pull FTP/SFTP furnizori',
            'category' => 'Furnizori',
            'url' => 'admin/cron_cli/supplier_ftp_pull.php',
            'batch' => 'admin/scripts/run_supplier_ftp_pull.bat',
            'schedule' => 'La 15–60 min (Task Scheduler)',
            'note' => 'Descarcă listele de pe FTP/SFTP în admin/storage/supplier_feeds/{cod}/. Respectă programul din profilul furnizorului. --force ignoră programul.',
        ],
        [
            'name' => 'Sync furnizori (rclone)',
            'category' => 'Furnizori',
            'url' => 'admin/scripts/supplier_sync_agent.php',
            'batch' => 'admin/scripts/run_supplier_rclone_sync.bat',
            'schedule' => 'La 6 ore',
            'note' => 'Descarcă CSV furnizori + pregătește scanarea.',
        ],
        [
            'name' => 'Sync furnizori (FTP legacy)',
            'category' => 'Furnizori',
            'url' => 'admin/scripts/supplier_sync_agent.php',
            'batch' => 'admin/scripts/run_supplier_sync.bat',
            'schedule' => 'La cerere',
            'note' => 'Variantă fără rclone — doar agent upload.',
        ],
        [
            'name' => 'Backup zilnic',
            'category' => 'Mentenanță',
            'url' => 'admin/cron_cli/daily_backup.php',
            'batch' => 'admin/scripts/run_daily_backup.bat',
            'schedule' => 'Zilnic 03:00',
            'note' => 'Arhivă BD + fișiere critice.',
        ],
        [
            'name' => 'AI Intelligence — agregare evenimente',
            'category' => 'AI',
            'url' => 'admin/cron_cli/ai_intel_aggregate_events.php',
            'batch' => 'php admin/cron_cli/ai_intel_aggregate_events.php',
            'schedule' => 'Orar (min 0)',
            'note' => 'Recalculează CTR/popularitate din ai_intel_events → ai_intel_aggregates.',
        ],
        [
            'name' => 'AI Intelligence — event writer worker',
            'category' => 'AI',
            'url' => 'admin/workers/event_writer_worker.php',
            'batch' => 'php admin/workers/event_writer_worker.php --max=100',
            'schedule' => 'La 1 min',
            'note' => 'Consumă coada Redis ai-events și scrie batch-uri în MySQL.',
        ],
        [
            'name' => 'AI Intelligence — index embeddings produse',
            'category' => 'AI',
            'url' => 'admin/cron_cli/ai_intel_index_products.php',
            'batch' => 'php admin/cron_cli/ai_intel_index_products.php --limit=400',
            'schedule' => 'Nightly 01:30',
            'note' => 'Indexare batch Ollama → ai_product_embeddings.',
        ],
        [
            'name' => 'FanCourier tracking',
            'category' => 'Legacy ERP',
            'url' => '/bes/well-known/cron/fancourier-tracking',
            'batch' => 'Laravel scheduler',
            'schedule' => 'La 30 min',
            'note' => 'Actualizare AWB — modul Laravel.',
        ],
        [
            'name' => 'Import LKQ',
            'category' => 'Legacy ERP',
            'url' => '/bes/well-known/lkq-import',
            'batch' => 'HTTP + cheie .env',
            'schedule' => 'La cerere',
            'note' => 'Import prețuri LKQ pe cheie secretă.',
        ],
    ];
}

/**
 * Joburi afișate în panoul /admin/cron — implicit gol până la configurare Task Scheduler.
 * Setează CRON_DASHBOARD_JOBS=all în .env pentru lista completă din registru.
 *
 * @return list<array<string, mixed>>
 */
function admin_cron_dashboard_tasks(): array
{
    $mode = strtolower(trim((string) ($_ENV['CRON_DASHBOARD_JOBS'] ?? getenv('CRON_DASHBOARD_JOBS') ?: 'none')));
    if (in_array($mode, ['none', '0', 'false', 'off', ''], true)) {
        return [];
    }

    $base = dirname(__DIR__);
    $tasks = admin_cron_tasks_registry();
    $rows = [];

    foreach ($tasks as $task) {
        if (!is_array($task)) {
            continue;
        }
        $batch = (string) ($task['batch'] ?? '');
        $relBatch = preg_replace('#^admin/#', '', trim($batch)) ?? '';
        $needsBat = str_contains(strtolower($batch), '.bat');
        $exists = $relBatch !== '' && is_file($base . '/' . str_replace('/', DIRECTORY_SEPARATOR, $relBatch));
        $scriptOk = !$needsBat || $exists;

        if ($mode === 'installed' && !$scriptOk) {
            continue;
        }

        $rows[] = [
            'name' => (string) ($task['name'] ?? 'Job'),
            'category' => (string) ($task['category'] ?? '—'),
            'schedule' => (string) ($task['schedule'] ?? '—'),
            'batch' => $batch,
            'url' => (string) ($task['url'] ?? ''),
            'note' => (string) ($task['note'] ?? ''),
            'script_ok' => $scriptOk,
            'health' => $scriptOk ? 'ok' : 'missing',
            'is_supplier' => str_contains(strtolower($batch), 'supplier'),
        ];
    }

    return $rows;
}

/**
 * Pagini/module admin legate de scanări și sincronizări (sursă pentru UI + verify_cron_setup).
 *
 * @return list<array{module: string, admin_url: string, template: string, selector: string, cron_batch: string, note: string}>
 */
function admin_cron_modules_registry(): array
{
    return [
        [
            'module' => 'Furnizori',
            'admin_url' => '/admin/furnizori',
            'template' => 'admin/Templates/admin/pages/furnizori/furnizori.php',
            'selector' => '#furnizori-grid',
            'cron_batch' => 'admin/scripts/run_supplier_rclone_sync.bat',
            'note' => 'Profil furnizor — program sync per furnizor (tab Program & import)',
        ],
        [
            'module' => 'Cron Sync',
            'admin_url' => '/admin/cron',
            'template' => 'admin/Templates/admin/pages/cron/cron.php',
            'selector' => '#cron-sync-page',
            'cron_batch' => 'admin/scripts/run_supplier_rclone_sync.bat',
            'note' => 'Panou Cron Sync — scan furnizori din UI (nu hub CRUD tabel cron)',
        ],
        [
            'module' => 'Backup DB',
            'admin_url' => '/admin/backup',
            'template' => 'admin/Templates/admin/pages/backup/backup.php',
            'selector' => '#backup-run',
            'cron_batch' => 'admin/scripts/run_daily_backup.bat',
            'note' => 'Backup manual + programare zilnică 03:00',
        ],
        [
            'module' => 'Dashboard snapshot',
            'admin_url' => '/admin/api/dashboard_snapshot_cron.php',
            'template' => 'admin/scripts/refresh_dashboard_snapshot.php',
            'selector' => '—',
            'cron_batch' => 'admin/scripts/run_refresh_dashboard_snapshot.bat',
            'note' => 'CLI snapshot home — independent de browser',
        ],
    ];
}
