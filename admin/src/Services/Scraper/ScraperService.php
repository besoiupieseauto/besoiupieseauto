<?php

declare(strict_types=1);

namespace Besoiu\Services\Scraper;

/**
 * Bridge admin → lib/Scraper/ScraperModule (motorul procedural rămâne în lib/).
 */
final class ScraperService
{
    private static bool $booted = false;

    private \ScraperModule $module;

    public function __construct(?\ScraperModule $module = null)
    {
        self::bootModule();
        $this->module = $module ?? \ScraperModule::instance();
    }

    public static function bootModule(): void
    {
        if (self::$booted) {
            return;
        }
        $path = dirname(__DIR__, 4) . '/lib/Scraper/ScraperModule.php';
        require_once $path;
        \ScraperModule::boot();
        self::$booted = true;
    }

    public function module(): \ScraperModule
    {
        return $this->module;
    }

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function handleApiRequest(string $method, array $query = [], array $payload = []): array
    {
        return $this->module->handleApiRequest($method, $query, $payload);
    }

    public function streamImageProxy(string $url): void
    {
        $this->module->streamImageProxy($url);
    }

    public function epiesaProductCount(): int
    {
        return (int) $this->module->epiesaProductCount();
    }

    /** @return array<string, mixed> */
    public function getSourceConfig(string $sourceId): array
    {
        return $this->module->getSourceConfig($sourceId);
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function testSource(string $sourceId, array $input = []): array
    {
        return $this->module->testSource($sourceId, $input);
    }

    /** @param array<int, mixed> $args */
    public function __call(string $name, array $args): mixed
    {
        if (!method_exists($this->module, $name)) {
            throw new \BadMethodCallException('ScraperModule::' . $name);
        }

        return $this->module->{$name}(...$args);
    }
}
