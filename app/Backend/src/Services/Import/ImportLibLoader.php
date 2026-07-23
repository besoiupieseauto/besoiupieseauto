<?php

declare(strict_types=1);

namespace Besoiu\Services\Import;

/**
 * Boot unic pentru librăriile procedurale din Controllers/Produse.
 * Deduplică require_once din importproduse.php și SupplierCronImportService.
 */
final class ImportLibLoader
{
    private static bool $coreLoaded = false;

    public static function produseDir(): string
    {
        return dirname(__DIR__, 2) . '/Controllers/Produse';
    }

    public static function projectRoot(): string
    {
        return dirname(__DIR__, 4);
    }

    /**
     * Librării import fără monolitul HTTP (importproduse.php).
     *
     * @param bool $includeCronDual include import_cron_dual_scan_lib.php
     * @param bool $includeJobs include job + image job + tecdoc library
     */
    public static function bootCoreLibs(bool $includeCronDual = false, bool $includeJobs = true): void
    {
        if (self::$coreLoaded) {
            if ($includeCronDual) {
                self::requireCronDual();
            }

            return;
        }

        $base = self::produseDir();
        $root = self::projectRoot();

        require_once $base . '/import_paths_lib.php';
        require_once $base . '/import_lib.php';
        require_once $base . '/import_supplier_lib.php';
        require_once $base . '/import_identity_lib.php';
        require_once $base . '/import_tecdoc_master_lib.php';
        require_once $base . '/import_base_lib.php';
        require_once $base . '/import_tecdoc_lib.php';
        if ($includeJobs) {
            require_once $base . '/import_job_lib.php';
            require_once $base . '/import_tecdoc_library_lib.php';
            require_once $base . '/import_image_pipeline_lib.php';
            require_once $base . '/import_image_job_lib.php';
        }
        import_require_system_file('tecdoc_stock.php');
        import_require_system_file('tecdoc_description.php');
        import_require_system_file('emag_image_search.php');
        require_once $base . '/import_consumable_scan_lib.php';

        // Stub alias → Besoiu\Services\AdaosComercial\AdaosComercialService
        $adaosStub = dirname($base) . '/AdaosComercial/AdaosComercialService.php';
        if (is_file($adaosStub)) {
            require_once $adaosStub;
        }

        if ($includeCronDual) {
            self::requireCronDual();
        }

        self::$coreLoaded = true;
    }

    /** Boot pentru cron scan (libs + dual + monolit dacă lipsește preview). */
    public static function bootForCron(): void
    {
        self::ensureSkipHttp();
        self::bootCoreLibs(includeCronDual: true, includeJobs: false);

        if (!function_exists('preview_products_from_file')) {
            require_once self::produseDir() . '/importproduse.php';
        }
        if (!function_exists('import_sync_products_oem')) {
            import_require_system_file('products_oem.php');
        }
    }

    /**
     * Boot complet: libs + monolit (funcții din importproduse.php).
     * Folosit de facade / acțiuni coadă / CLI.
     */
    public static function bootFull(bool $skipHttp = true): void
    {
        if ($skipHttp) {
            self::ensureSkipHttp();
        }
        self::bootCoreLibs(includeCronDual: false, includeJobs: true);
        require_once self::produseDir() . '/importproduse.php';
    }

    /** Boot pentru endpoint HTTP (rulează switch-ul mode din monolit). */
    public static function bootForHttp(): void
    {
        // Fără SKIP_HTTP — monolitul procesează request-ul
        self::bootCoreLibs(includeCronDual: false, includeJobs: true);
        require_once self::produseDir() . '/importproduse.php';
    }

    /** Boot pentru acțiuni coadă importreview (action file încarcă monolitul). */
    public static function bootForQueueActions(): void
    {
        self::ensureSkipHttp();
        require_once self::produseDir() . '/importproduse_action.php';
    }

    private static function ensureSkipHttp(): void
    {
        if (!defined('IMPORT_PRODUCE_SKIP_HTTP')) {
            define('IMPORT_PRODUCE_SKIP_HTTP', true);
        }
    }

    private static function requireCronDual(): void
    {
        $path = self::produseDir() . '/import_cron_dual_scan_lib.php';
        if (is_file($path)) {
            require_once $path;
        }
    }

}
