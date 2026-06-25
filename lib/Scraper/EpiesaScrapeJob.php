<?php
declare(strict_types=1);

require_once __DIR__ . '/ScraperPaths.php';
require_once __DIR__ . '/ScrapeDoClient.php';
require_once __DIR__ . '/EpiesaCategoryParser.php';
require_once __DIR__ . '/EpiesaCategories.php';
require_once __DIR__ . '/EpiesaCatalog.php';
require_once __DIR__ . '/ScraperLogger.php';

final class EpiesaScrapeJob
{
    public const DEFAULT_CATEGORY_URL = 'https://www.epiesa.ro/gmtn1:auto/gmtn2:uleiuri-si-lubrifianti-auto/';

    /** @return array<string, mixed> */
    public static function run(
        ?string $categoryUrl = null,
        int $productLimit = 10,
        ?string $scrapeToken = null
    ): array {
        ScraperPaths::ensureDirs();

        $url = trim($categoryUrl ?? self::DEFAULT_CATEGORY_URL);
        $category = EpiesaCategories::resolveFromUrl($url);
        EpiesaCatalog::saveStatus([
            'status'         => 'running',
            'source_url'     => $url,
            'category_slug'  => $category['slug'],
            'category_label' => $category['label'],
            'started_at'     => date('c'),
        ]);
        ScraperLogger::log('info', 'Start scan | categorie=' . $category['label'] . ' | url=' . $url);

        $stamp = date('Ymd_His');
        $rawFile = ScraperPaths::rawDir() . '/epiesa_' . $stamp . '.html';
        $jsonFile = ScraperPaths::jsonDir() . '/epiesa_' . $stamp . '.json';
        $latest = ScraperPaths::latestJsonPath();

        $client = new ScrapeDoClient($scrapeToken);
        $html = $client->fetch($url);
        file_put_contents($rawFile, $html, LOCK_EX);

        $products = EpiesaCategoryParser::parse($html, $productLimit);
        foreach ($products as &$product) {
            $product['category_slug'] = $category['slug'];
            $product['category_label'] = $category['label'];
        }
        unset($product);

        $added = EpiesaCatalog::mergeScan($products, $url, $category['slug'], $category['label']);
        $catalogTotal = count(EpiesaCatalog::listProducts());

        $payload = [
            'scraped_at'   => date('c'),
            'source_url'   => $url,
            'category_slug'  => $category['slug'],
            'category_label' => $category['label'],
            'raw_html'     => basename($rawFile),
            'raw_path'     => $rawFile,
            'product_count'=> count($products),
            'catalog_added' => $added,
            'catalog_total' => $catalogTotal,
            'limit'        => $productLimit,
            'products'     => $products,
        ];

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        file_put_contents($jsonFile, $json, LOCK_EX);
        file_put_contents($latest, $json, LOCK_EX);

        EpiesaCatalog::saveStatus([
            'status'         => 'finished',
            'source_url'     => $url,
            'category_slug'  => $category['slug'],
            'category_label' => $category['label'],
            'product_count'  => count($products),
            'catalog_added'  => $added,
            'catalog_total'  => $catalogTotal,
            'finished_at'    => date('c'),
        ]);
        ScraperLogger::log(
            'info',
            'Scan finalizat | extrase=' . count($products) . ' | noi în catalog=' . $added . ' | total catalog=' . $catalogTotal
        );

        return [
            'success'       => true,
            'message'       => count($products) . ' produse extrase (' . $added . ' noi în catalog, total ' . $catalogTotal . ').',
            'scraped_at'    => $payload['scraped_at'],
            'source_url'    => $url,
            'category_slug' => $category['slug'],
            'category_label'=> $category['label'],
            'raw_html'      => basename($rawFile),
            'json_file'     => basename($jsonFile),
            'product_count' => count($products),
            'catalog_added' => $added,
            'catalog_total' => $catalogTotal,
            'products'      => $products,
        ];
    }

    /** @return array<string, mixed> */
    public static function loadLatest(): array
    {
        $path = ScraperPaths::latestJsonPath();
        if (!is_file($path)) {
            return [
                'success'  => true,
                'message'  => 'Niciun scan salvat încă.',
                'products' => [],
            ];
        }

        $data = json_decode((string) file_get_contents($path), true);
        if (!is_array($data)) {
            return ['success' => false, 'message' => 'JSON invalid.', 'products' => []];
        }

        return [
            'success'       => true,
            'scraped_at'    => $data['scraped_at'] ?? null,
            'source_url'    => $data['source_url'] ?? '',
            'raw_html'      => $data['raw_html'] ?? '',
            'product_count' => (int) ($data['product_count'] ?? count($data['products'] ?? [])),
            'products'      => is_array($data['products'] ?? null) ? $data['products'] : [],
        ];
    }
}
