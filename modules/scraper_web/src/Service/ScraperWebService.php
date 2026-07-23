<?php
declare(strict_types=1);

namespace Besoiu\Modules\ScraperWeb\Service;

use Besoiu\Modules\ScraperWeb\Support\ScraperWebBridge;
use BesoiuImport\Scraper\Services\ScraperGatewayService;

/**
 * Fațadă service — delegă la motorul Scraper.
 */
final class ScraperWebService
{
    /** @return array{items: list<array<string, mixed>>, total: int} */
    public function listSources(): array
    {
        ScraperWebBridge::boot();
        $cards = (new ScraperGatewayService())->listSourceCards();

        return [
            'items' => $cards,
            'total' => count($cards),
        ];
    }

    /** @return array<string, mixed> */
    public function status(): array
    {
        ScraperWebBridge::boot();

        return [
            'scraper_root' => ScraperWebBridge::root(),
            'api' => ScraperWebBridge::apiEndpointUrl(),
            'sources' => count((new ScraperGatewayService())->listSourceCards()),
        ];
    }
}
