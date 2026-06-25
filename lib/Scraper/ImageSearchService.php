<?php

declare(strict_types=1);

require_once __DIR__ . '/ImportImageBridge.php';
require_once __DIR__ . '/ScraperImageResolver.php';

/**
 * Modul unic căutare imagini — hub scraper, pipeline UI, import, cron, audit.
 */
final class ImageSearchService
{
    private static bool $booted = false;

    /** Bootstrap unic — import fără HTTP + pipeline helpers. */
    public static function boot(): void
    {
        if (self::$booted) {
            return;
        }
        ImportImageBridge::boot();
        $pipelinePath = dirname(__DIR__, 2) . '/system/image_search_pipeline.php';
        if (is_file($pipelinePath)) {
            require_once $pipelinePath;
        }
        self::$booted = true;
    }

    /** @return list<array<string, mixed>> */
    public static function activeImagePlans(array $categories = []): array
    {
        self::boot();

        return ScraperImageResolver::activeImagePlans($categories);
    }

    public static function hasActiveImagePlans(array $categories = []): bool
    {
        return self::activeImagePlans($categories) !== [];
    }

    /**
     * Pipeline complet Plan 1→N (Autodoc, TecDoc, …).
     *
     * @param array<string, mixed> $product
     * @param array<string, mixed> $opts categories, force, test_mode, pipeline_test_query, log, skip_vision
     * @return array{product: array<string, mixed>, hit: array<string, mixed>|null, tried: list<array<string, mixed>>}
     */
    public static function resolve(array $product, array $opts = []): array
    {
        self::boot();
        $product = self::enrichProduct($product);

        $cats = self::categoriesFromProduct($product, $opts);
        $result = ScraperImageResolver::resolve($product, array_merge($opts, ['categories' => $cats]));

        if (is_array($result['hit'] ?? null) && trim((string) ($result['hit']['url'] ?? '')) !== '') {
            $hit = ImportImageBridge::normalizeHit($result['hit']);
            $result['hit'] = $hit;
            $result['product'] = ImportImageBridge::applyHit($result['product'], $hit);
        }

        return $result;
    }

    /**
     * Un singur plan (progres UI pas-cu-pas).
     *
     * @param array<string, mixed> $plan
     * @param array<string, mixed> $product
     * @param array<string, mixed> $opts
     * @return array{tried: array<string, mixed>, hit: array<string, mixed>|null}
     */
    public static function resolvePlan(array $plan, array $product, bool $force = false, array $opts = []): array
    {
        self::boot();
        $product = self::enrichProduct($product);
        $step = ScraperImageResolver::tryImagePlan($plan, $product, $force, $opts);
        if (is_array($step['hit'] ?? null) && trim((string) ($step['hit']['url'] ?? '')) !== '') {
            $hit = ImportImageBridge::normalizeHit($step['hit']);
            $step['hit'] = $hit;
            $step['product'] = ImportImageBridge::applyHit($product, $hit);
        }

        return $step;
    }

    /**
     * API simplu pentru import / cron — returnează hit normalizat sau gol.
     *
     * @param array<string, mixed> $product
     * @param array<string, mixed> $opts force, fast_mode, test_mode, pipeline_test_query
     * @return array<string, mixed>
     */
    public static function findImage(array $product, array $opts = []): array
    {
        self::boot();

        $force = !empty($opts['force']);
        $fastMode = !empty($opts['fast_mode']);

        if (!$force && function_exists('import_row_image_url') && function_exists('import_image_url_is_trusted')) {
            $existingUrl = import_row_image_url($product);
            $existingSource = (string) ($product['pImageSource'] ?? '');
            if (import_image_url_is_trusted($existingUrl, $existingSource)) {
                return [
                    'url' => $existingUrl,
                    'source' => $existingSource !== '' ? $existingSource : 'tecdoc_api',
                    'query' => trim((string) ($product['pCode'] ?? '')),
                ];
            }
        }

        if ($fastMode) {
            return ImportImageBridge::normalizeHit(
                ImportImageBridge::findFromOemCrossList($product, true)
            );
        }

        $resolved = self::resolve($product, $opts);
        $hit = is_array($resolved['hit'] ?? null) ? $resolved['hit'] : null;
        if ($hit !== null && trim((string) ($hit['url'] ?? '')) !== '') {
            return $hit;
        }

        if (function_exists('import_find_image_legacy_fallbacks')) {
            return ImportImageBridge::normalizeHit(import_find_image_legacy_fallbacks(
                $resolved['product'],
                $opts['tecdoc_files'] ?? null,
                $opts['shared_lookup'] ?? null
            ));
        }

        return [
            'url' => '',
            'source' => 'missing',
            'query' => trim((string) ($product['pCode'] ?? '')),
            'api_error' => null,
        ];
    }

    /** @param array<string, mixed> $product @return array<string, mixed> */
    public static function enrichProduct(array $product): array
    {
        self::boot();
        if (!empty($product['__image_enriched'])) {
            return $product;
        }
        if (function_exists('besoiu_image_enrich_product_context')) {
            $product = besoiu_image_enrich_product_context($product);
        }
        $product['__image_enriched'] = true;

        return $product;
    }

    /**
     * @param array<string, mixed> $product
     * @param array<string, mixed> $opts
     * @return list<string>
     */
    private static function categoriesFromProduct(array $product, array $opts): array
    {
        if (is_array($opts['categories'] ?? null) && $opts['categories'] !== []) {
            return array_values(array_filter(array_map('strval', $opts['categories'])));
        }

        $cats = [];
        $cat = strtolower(trim((string) ($product['pCategory'] ?? '')));
        $sub = strtolower(trim((string) ($product['pSubcategory'] ?? '')));
        if ($cat !== '') {
            $cats[] = $cat;
        }
        if ($sub !== '') {
            $cats[] = $sub;
        }

        return $cats;
    }
}
