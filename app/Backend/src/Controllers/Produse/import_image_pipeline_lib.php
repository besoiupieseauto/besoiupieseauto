<?php
declare(strict_types=1);

/**
 * Boot pipeline imagini coadă import — modul scraper_web + motor app/Import/Scraper.
 */
function import_image_search_service_path(): ?string
{
    $appRoot = dirname(__DIR__, 4);
    $candidates = [
        $appRoot . '/Import/Scraper/lib/ImageSearchService.php',
        $appRoot . '/Lib/Scraper/ImageSearchService.php',
    ];
    foreach ($candidates as $path) {
        if (is_file($path)) {
            return $path;
        }
    }

    return null;
}

function import_image_job_batch_size(): int
{
    $raw = trim((string) (getenv('IMPORT_IMAGE_BATCH_SIZE') ?: ($_ENV['IMPORT_IMAGE_BATCH_SIZE'] ?? '3')));

    return max(1, min(6, (int) ($raw !== '' ? $raw : 3)));
}

function import_image_job_proc_parallel_enabled(): bool
{
    $defaultProc = PHP_OS_FAMILY === 'Windows' ? '0' : '1';
    $raw = strtolower(trim((string) (getenv('IMPORT_IMAGE_PROC_PARALLEL') ?: ($_ENV['IMPORT_IMAGE_PROC_PARALLEL'] ?? $defaultProc))));

    return PHP_SAPI === 'cli'
        && function_exists('proc_open')
        && !in_array($raw, ['0', 'false', 'off', 'no'], true);
}

/** @return array{ok:bool,error?:string,message?:string} */
function import_image_pipeline_boot(): array
{
    static $booted = false;
    if ($booted) {
        return ['ok' => true];
    }

    if (!class_exists(\Besoiu\Core\Module\OptionalModuleBridge::class, false)) {
        $bridgeFile = dirname(__DIR__, 2) . '/Core/Module/OptionalModuleBridge.php';
        if (is_file($bridgeFile)) {
            require_once $bridgeFile;
        }
    }

    if (
        class_exists(\Besoiu\Core\Module\OptionalModuleBridge::class, false)
        && !\Besoiu\Core\Module\OptionalModuleBridge::isOperational('scraper_web')
    ) {
        return [
            'ok' => false,
            'error' => 'scraper_web_off',
            'message' => 'Modulul Scraper Web nu este activ. Activează scraper_web din Module admin.',
        ];
    }

    if (class_exists(\Besoiu\Core\Module\OptionalModuleBridge::class, false)) {
        $bridgeClass = \Besoiu\Core\Module\OptionalModuleBridge::resolveClass('scraper_web', 'Support/ScraperWebBridge');
        if ($bridgeClass !== null && method_exists($bridgeClass, 'boot')) {
            try {
                $bridgeClass::boot();
            } catch (\Throwable $e) {
                return [
                    'ok' => false,
                    'error' => 'scraper_web_boot',
                    'message' => 'Scraper Web — eroare boot: ' . $e->getMessage(),
                ];
            }
        }
    }

    $servicePath = import_image_search_service_path();
    if ($servicePath === null) {
        return [
            'ok' => false,
            'error' => 'missing_pipeline',
            'message' => 'Pipeline imagini indisponibil (ImageSearchService).',
        ];
    }

    require_once $servicePath;
    if (class_exists('ImageSearchService', false)) {
        \ImageSearchService::boot();
    }

    $booted = true;

    return ['ok' => true];
}

/** @return array<string, mixed> */
function import_image_job_lookup_opts(bool $force = false, ?string $jobId = null): array
{
    $opts = [
        'background_job' => true,
        'step_budget_sec' => 420,
        'import_review' => true,
        'ollama_quality' => true,
        'reject_partial_verdict' => true,
        'force' => $force,
        'query_limit' => 8,
        'parallel_queries' => 4,
        'epiesa_timeout_sec' => 22,
    ];

    $jobId = trim((string) ($jobId ?? ''));
    if ($jobId !== '' && function_exists('import_image_job_push_log')) {
        $opts['log'] = static function (string $msg, string $level = 'info') use ($jobId): void {
            import_image_job_push_log($jobId, $msg, $level);
        };
    }

    return $opts;
}

/** @return array<string, mixed> */
function import_image_job_worker_cli_path(): ?string
{
    $path = dirname(__DIR__, 3) . '/tools/import_image_worker_cli.php';

    return is_file($path) ? $path : null;
}
