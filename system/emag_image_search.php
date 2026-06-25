<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/Scraper/EmagSearch.php';
require_once dirname(__DIR__) . '/lib/Scraper/ScrapeDoConfig.php';
require_once __DIR__ . '/import-image-validate.php';

function emag_image_bootstrap_env(): void
{
    static $booted = false;
    if ($booted) {
        return;
    }

    if (ScrapeDoConfig::hasToken()) {
        $booted = true;
        return;
    }

    $adminRoot = dirname(__DIR__) . '/admin';
    $autoload = $adminRoot . '/vendor/autoload.php';
    if (is_file($autoload)) {
        require_once $autoload;
        if (class_exists(\Dotenv\Dotenv::class) && is_file($adminRoot . '/.env')) {
            \Dotenv\Dotenv::createImmutable($adminRoot)->safeLoad();
        }
    }

    $booted = true;
}

function emag_image_log(string $message): void
{
    $dir = dirname(__DIR__) . '/admin/storage/logs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    @file_put_contents($dir . '/emag_image.log', $line, FILE_APPEND);
}

function emag_local_image_exists(string $relative): bool
{
    if (!str_starts_with($relative, '/uploads/')) {
        return false;
    }

    $path = dirname(__DIR__) . $relative;

    return is_file($path) && (int) filesize($path) >= 200;
}

function emag_download_product_image(string $url, string $code): string
{
    $url = trim($url);
    if ($url === '') {
        return '';
    }

    $safeCode = preg_replace('/[^A-Za-z0-9_-]/', '_', $code) ?: md5($url);
    $relative = '/uploads/products/tecdoc/' . $safeCode . '.jpg';
    $target = dirname(__DIR__) . $relative;
    $dir = dirname($target);
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }

    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 45,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_HTTPHEADER => [
            'Accept: image/avif,image/webp,image/apng,image/*,*/*;q=0.8',
            'Referer: https://www.emag.ro/',
        ],
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
    ]);
    $data = curl_exec($curl);
    curl_close($curl);

    if (!is_string($data) || strlen($data) < 200) {
        return '';
    }

    if (@file_put_contents($target, $data) === false) {
        return '';
    }

    return $relative;
}

/**
 * @param array<string, mixed> $product
 * @return array{url:string,source:string,query:string,api_error:?string,emag_remote_url?:string,emag_title?:string,emag_product_url?:string,emag_search_url?:string}
 */
function emag_find_image_for_product(array $product): array
{
    $empty = [
        'url' => '',
        'source' => 'missing',
        'query' => '',
        'api_error' => null,
    ];

    $pipelinePath = dirname(__DIR__) . '/system/image_search_pipeline.php';
    if (is_file($pipelinePath)) {
        require_once $pipelinePath;
    }
    if (!function_exists('besoiu_image_search_source_enabled') || !besoiu_image_search_source_enabled('emag')) {
        return array_merge($empty, ['api_error' => 'eMAG dezactivat (lipsește din pipeline Scraper)']);
    }

    emag_image_bootstrap_env();

    $query = emag_image_search_query_from_product($product);
    if ($query === '') {
        return array_merge($empty, ['api_error' => 'eMAG: lipsă denumire produs']);
    }

    $result = EmagSearch::searchForProduct($product);
    $searchUrl = (string) ($result['search_url'] ?? EmagSearch::buildSearchUrl($query));
    if (!empty($result['query'])) {
        $query = (string) $result['query'];
    }
    if (empty($result['ok'])) {
        $error = trim((string) ($result['error'] ?? 'eMAG: căutare eșuată'));
        emag_image_log($query . ' | ' . $error);

        return array_merge($empty, [
            'query' => $query,
            'api_error' => $error,
            'emag_search_url' => (string) ($result['search_url'] ?? $searchUrl),
        ]);
    }

    $remoteUrl = trim((string) ($result['image_url'] ?? ''));
    if ($remoteUrl === '' || !besoiu_import_image_url_is_trusted($remoteUrl, 'emag_search')) {
        $error = 'eMAG: imagine respinsă de validator';
        emag_image_log($query . ' | ' . $error . ' | ' . $remoteUrl);

        return array_merge($empty, [
            'query' => $query,
            'api_error' => $error,
            'emag_search_url' => (string) ($result['search_url'] ?? $searchUrl),
        ]);
    }

    $code = trim((string) ($product['pCode'] ?? ''));
    if ($code === '') {
        $code = 'emag';
    }

    $storedUrl = emag_download_product_image($remoteUrl, $code);

    if ($storedUrl === '' || !emag_local_image_exists($storedUrl)) {
        $error = 'eMAG: download local eșuat (' . $remoteUrl . ')';
        emag_image_log($query . ' | ' . $error);

        return array_merge($empty, [
            'query' => $query,
            'api_error' => $error,
            'emag_remote_url' => $remoteUrl,
            'emag_search_url' => (string) ($result['search_url'] ?? $searchUrl),
        ]);
    }

    emag_image_log($query . ' | OK | ' . $storedUrl);

    return [
        'url' => $storedUrl,
        'source' => 'emag_search',
        'query' => $query,
        'api_error' => null,
        'emag_remote_url' => $remoteUrl,
        'emag_title' => (string) ($result['title'] ?? ''),
        'emag_product_url' => (string) ($result['product_url'] ?? ''),
        'emag_search_url' => (string) ($result['search_url'] ?? $searchUrl),
    ];
}

/** @param array<string, mixed> $product */
function emag_image_search_query_from_product(array $product): string
{
    $pipeline = __DIR__ . '/image_search_pipeline.php';
    if (is_file($pipeline)) {
        require_once $pipeline;
        $queries = besoiu_image_search_queries_for_product($product);
        if ($queries !== []) {
            return $queries[0];
        }
    }

    $name = trim((string) ($product['pName'] ?? ''));
    if ($name !== '') {
        return $name;
    }

    return trim(implode(' ', array_values(array_filter([
        trim((string) ($product['pBrand'] ?? '')),
        trim((string) ($product['pCode'] ?? '')),
        trim((string) ($product['pMarca'] ?? '')),
        trim((string) ($product['pModel'] ?? '')),
    ], static fn (string $part): bool => $part !== ''))));
}

/**
 * Înainte de publicare: verifică fișierul pe disc; pipeline Scraper sau eMAG (doar dacă e în plan).
 *
 * @param array<string, mixed> $row
 * @return array<string, mixed>
 */
function import_ensure_publish_image(array $row): array
{
    if (!function_exists('besoiu_import_row_image_url')) {
        require_once __DIR__ . '/import-image-validate.php';
    }

    $pipelinePath = dirname(__DIR__) . '/system/image_search_pipeline.php';
    if (is_file($pipelinePath)) {
        require_once $pipelinePath;
    }

    if (besoiu_import_row_image_url($row) !== '') {
        return $row;
    }

    $raw = json_decode((string) ($row['raw_json'] ?? '{}'), true);
    if (!is_array($raw)) {
        $raw = [];
    }

    // Cron / Import a rulat deja pipeline eMAG + TecDoc — nu relua (evită blocaje duble).
    if (!empty($raw['cron_image_pipeline_done']) || !empty($raw['cron_emag_attempted_at'])) {
        $images = json_decode((string) ($row['pImages'] ?? '[]'), true);
        if (!is_array($images) || trim((string) ($images[0] ?? '')) === '') {
            $row['pImages'] = '[]';
            $row['pImageSource'] = 'missing';
        }

        return $row;
    }

    $remoteUrl = trim((string) ($raw['__emag_remote_url'] ?? ''));
    $emagAllowed = function_exists('besoiu_image_search_source_enabled') && besoiu_image_search_source_enabled('emag');
    if ($emagAllowed && $remoteUrl !== '' && besoiu_import_image_url_is_trusted($remoteUrl, 'emag_search')) {
        $code = trim((string) ($row['pCode'] ?? '')) ?: 'img';
        $storedUrl = emag_download_product_image($remoteUrl, $code);
        if ($storedUrl !== '' && emag_local_image_exists($storedUrl) && function_exists('import_apply_image_lookup_result')) {
            return import_apply_image_lookup_result($row, [
                'url' => $storedUrl,
                'source' => 'emag_search',
                'query' => emag_image_search_query_from_product($row),
                'api_error' => null,
                'emag_remote_url' => $remoteUrl,
                'emag_title' => (string) ($raw['__emag_product_title'] ?? ''),
                'emag_product_url' => (string) ($raw['__emag_product_url'] ?? ''),
                'emag_search_url' => (string) ($raw['__emag_search_url'] ?? ''),
            ]);
        }
    }

    if ($emagAllowed && function_exists('emag_find_image_for_product')) {
        $found = emag_find_image_for_product($row);
        if (trim((string) ($found['url'] ?? '')) !== '' && function_exists('import_apply_image_lookup_result')) {
            return import_apply_image_lookup_result($row, $found);
        }
    }

    if (function_exists('besoiu_image_search_resolve_product')) {
        $resolved = besoiu_image_search_resolve_product($row);
        $hit = is_array($resolved['hit'] ?? null) ? $resolved['hit'] : null;
        if ($hit !== null && trim((string) ($hit['url'] ?? '')) !== '' && function_exists('import_apply_image_lookup_result')) {
            return import_apply_image_lookup_result($row, $hit);
        }
    }

    $images = json_decode((string) ($row['pImages'] ?? '[]'), true);
    if (is_array($images) && trim((string) ($images[0] ?? '')) !== '') {
        $row['pImages'] = '[]';
        $row['pImageSource'] = 'missing';
    }

    return $row;
}
