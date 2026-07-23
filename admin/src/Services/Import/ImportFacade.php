<?php

declare(strict_types=1);

namespace Besoiu\Services\Import;

use Besoiu\Services\AdaosComercial\AdaosComercialService;
use PDO;

/**
 * Facade subțire peste funcțiile procedurale din Controllers/Produse.
 * Nu rescrie logica — doar API OOP pentru entry points / cron / UI.
 */
final class ImportFacade
{
    private bool $booted = false;

    public function __construct(
        private readonly bool $bootOnConstruct = true,
    ) {
        if ($bootOnConstruct) {
            $this->boot();
        }
    }

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }
        ImportLibLoader::bootFull(skipHttp: true);
        $this->booted = true;
    }

    /** @return list<array<string, mixed>> */
    public function listUploadedFiles(): array
    {
        $this->boot();

        return function_exists('list_uploaded_import_files')
            ? list_uploaded_import_files()
            : [];
    }

    public function deleteUploadedFile(string $fileId): bool
    {
        $this->boot();

        return function_exists('delete_uploaded_import_file')
            && delete_uploaded_import_file($fileId);
    }

    /**
     * @param array<string, mixed> $filesMeta
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function previewUploadedFiles(array $filesMeta, int $maxPreview = 50, array $options = []): array
    {
        $this->boot();
        if (!function_exists('import_preview_uploaded_files')) {
            return ['products' => [], 'error' => 'import_preview_uploaded_files lipsește'];
        }

        return import_preview_uploaded_files($filesMeta, $maxPreview, $options);
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function previewProductsFromFile(string $path, int $maxPreview = 50, array $options = []): array
    {
        $this->boot();
        if (!function_exists('preview_products_from_file')) {
            return [];
        }

        return preview_products_from_file($path, $maxPreview, $options);
    }

    /**
     * @param list<array<string, mixed>> $products
     * @param array<string, mixed> $opts
     * @return array<string, mixed>
     */
    public function stageProductsForReview(
        PDO $pdo,
        array $products,
        ?AdaosComercialService $markup = null,
        array $opts = []
    ): array {
        $this->boot();
        if (!function_exists('import_stage_products_for_review')) {
            return ['staged' => 0, 'error' => 'import_stage_products_for_review lipsește'];
        }
        $markup ??= new AdaosComercialService();

        return import_stage_products_for_review($pdo, $products, $markup, $opts);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public function publishPreparedRow(PDO $pdo, array $row, string $publishMode = 'add'): array
    {
        $this->boot();
        if (!function_exists('import_publish_prepared_row')) {
            return ['success' => false, 'message' => 'import_publish_prepared_row lipsește'];
        }

        return import_publish_prepared_row($pdo, $row, $publishMode);
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return array<string, mixed>
     */
    public function processPublishRows(PDO $pdo, array $rows, string $publishMode = 'add'): array
    {
        $this->boot();
        if (!function_exists('import_process_publish_rows')) {
            return ['success' => false, 'message' => 'import_process_publish_rows lipsește'];
        }

        return import_process_publish_rows($pdo, $rows, $publishMode);
    }

    /**
     * @param array<string, mixed> $row
     */
    public function syncPreparedRow(PDO $pdo, int $importId, array $row): void
    {
        $this->boot();
        if (function_exists('import_sync_prepared_row')) {
            import_sync_prepared_row($pdo, $importId, $row);
        }
    }

    /**
     * @param array<string, mixed> $product
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function findImageForProduct(array $product, array $options = []): array
    {
        $this->boot();
        if (!function_exists('import_find_image_for_product')) {
            return [];
        }

        return import_find_image_for_product($product, $options);
    }

    /**
     * @param array<string, mixed> $product
     * @return array<string, mixed>
     */
    public function enrichProductFromTecdoc(array $product, bool $force = false): array
    {
        $this->boot();
        if (!function_exists('import_enrich_product_from_tecdoc')) {
            return $product;
        }

        return import_enrich_product_from_tecdoc($product, $force);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public function enrichRowBeforeLivePublish(array $row, bool $forceImage = false): array
    {
        $this->boot();
        if (!function_exists('import_enrich_row_before_live_publish')) {
            return $row;
        }

        return import_enrich_row_before_live_publish($row, $forceImage);
    }

    /**
     * @param array<string, mixed> $filesMeta
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function startPreviewJob(array $filesMeta, int $maxPreview = 50, array $options = []): array
    {
        $this->boot();
        if (!function_exists('import_preview_job_start')) {
            return ['success' => false, 'message' => 'import_preview_job_start lipsește'];
        }

        return import_preview_job_start($filesMeta, $maxPreview, $options);
    }

    /** @return array<string, mixed> */
    public function stepPreviewJob(string $jobId): array
    {
        $this->boot();
        if (!function_exists('import_preview_job_step')) {
            return ['success' => false, 'message' => 'import_preview_job_step lipsește'];
        }

        return import_preview_job_step($jobId);
    }

    /** @return array<string, mixed>|null */
    public function loadJobMeta(string $jobId): ?array
    {
        $this->boot();
        if (!function_exists('import_job_load_meta')) {
            return null;
        }

        $meta = import_job_load_meta($jobId);

        return is_array($meta) ? $meta : null;
    }
}
