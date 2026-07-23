<?php

declare(strict_types=1);

namespace Besoiu\Services;

use Besoiu\Services\Scraper\ScraperService;

/**
 * @deprecated Folosește Besoiu\Services\Scraper\ScraperService.
 */
final class ScraperHubService
{
    private ScraperService $service;

    public function __construct(?string $projectRoot = null)
    {
        unset($projectRoot);
        $this->service = new ScraperService();
    }

    /** @param array<int, mixed> $args */
    public function __call(string $name, array $args): mixed
    {
        return $this->service->{$name}(...$args);
    }
}
