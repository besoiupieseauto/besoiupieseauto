<?php

declare(strict_types=1);

namespace Besoiu\Controllers\Scraper;

use Besoiu\Services\Scraper\ScraperService;

/**
 * Controller subțire Scraper Hub — dispatch API către ScraperService.
 */
final class Scraper
{
    public function __construct(
        private readonly ?ScraperService $service = null,
    ) {
    }

    private function svc(): ScraperService
    {
        return $this->service ?? new ScraperService();
    }

    /**
     * Entry point HTTP (folosit de scraper_endpoint.php).
     *
     * @param array<string, mixed> $query
     * @param array<string, mixed> $payload
     * @return array{mode?: string, url?: string, body?: array<string, mixed>, status?: int}
     */
    public function handleApi(string $method, array $query = [], array $payload = []): array
    {
        return $this->svc()->handleApiRequest($method, $query, $payload);
    }

    public function streamImage(string $url): void
    {
        $this->svc()->streamImageProxy($url);
    }

    public function service(): ScraperService
    {
        return $this->svc();
    }
}
