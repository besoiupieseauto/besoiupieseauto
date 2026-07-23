<?php

declare(strict_types=1);

namespace Besoiu\Services;

use Besoiu\Services\Scraper\ScraperService;

/**
 * @deprecated Folosește Besoiu\Services\Scraper\ScraperService.
 */
final class EpiesaScraperService
{
    private ScraperService $service;

    public function __construct()
    {
        $this->service = new ScraperService();
    }

    public function cacheImages(): array
    {
        return $this->service->epiesaCacheImages();
    }

    /** @param array<string, mixed> $input */
    public function runScan(array $input): array
    {
        return $this->service->epiesaRunScan($input);
    }

    /** @return array<string, mixed> */
    public function latest(): array
    {
        return $this->service->epiesaLatest();
    }

    /** @return array<string, mixed> */
    public function stats(): array
    {
        return $this->service->epiesaStats();
    }

    /** @return array<string, mixed> */
    public function catalog(?string $categorySlug = null): array
    {
        return $this->service->epiesaCatalog($categorySlug);
    }

    public function logs(int $lines = 120): string
    {
        return $this->service->epiesaLogs($lines);
    }
}
