<?php

declare(strict_types=1);

namespace Evasystem\Controllers\Scraper;

final class EpiesaScraperService
{
    private static function bootLib(): void
    {
        static $loaded = false;
        if ($loaded) {
            return;
        }
        $root = dirname(__DIR__, 4);
        require_once $root . '/lib/Scraper/EpiesaScrapeJob.php';
        require_once $root . '/lib/Scraper/EpiesaCatalog.php';
        require_once $root . '/lib/Scraper/EpiesaCategories.php';
        require_once $root . '/lib/Scraper/ScraperLogger.php';
        require_once $root . '/lib/Scraper/EpiesaImageCache.php';
        $loaded = true;
    }

    public function cacheImages(): array
    {
        self::bootLib();

        $updated = \EpiesaCatalog::refreshAllImages();

        return [
            'success' => true,
            'message' => $updated . ' imagini actualizate local.',
            'updated' => $updated,
            'total'   => count(\EpiesaCatalog::listProducts()),
        ];
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    public function runScan(array $input): array
    {
        self::bootLib();

        $url = trim((string) ($input['url'] ?? \EpiesaScrapeJob::DEFAULT_CATEGORY_URL));
        $limit = max(1, min(50, (int) ($input['limit'] ?? 10)));
        $token = trim((string) (getenv('SCRAPE_DO_TOKEN') ?: ''));

        if ($token === '') {
            throw new \InvalidArgumentException('Configurează SCRAPE_DO_TOKEN în admin/.env');
        }

        return \EpiesaScrapeJob::run($url, $limit, $token);
    }

    /** @return array<string, mixed> */
    public function latest(): array
    {
        self::bootLib();

        return \EpiesaScrapeJob::loadLatest();
    }

    /** @return array<string, mixed> */
    public function stats(): array
    {
        self::bootLib();

        $stats = \EpiesaCatalog::stats();
        $stats['has_token'] = trim((string) (getenv('SCRAPE_DO_TOKEN') ?: '')) !== '';
        $stats['categories_presets'] = array_values(\EpiesaCategories::presets());

        return $stats;
    }

    /** @return array<string, mixed> */
    public function catalog(?string $categorySlug = null): array
    {
        self::bootLib();

        $products = \EpiesaCatalog::listProducts($categorySlug);

        return [
            'success'       => true,
            'product_count' => count($products),
            'category'      => $categorySlug ?? 'toate',
            'products'      => $products,
        ];
    }

  public function logs(int $lines = 120): string
  {
    self::bootLib();

    return \ScraperLogger::tail($lines);
  }
}
