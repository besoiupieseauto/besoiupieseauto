<?php
declare(strict_types=1);

namespace Besoiu\Modules\ScraperWeb\Controller;

use Besoiu\Modules\ScraperWeb\Service\ScraperWebService;

final class ScraperWebController
{
    public function __construct(
        private readonly ScraperWebService $service = new ScraperWebService(),
    ) {
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public function list(array $payload): array
    {
        return $this->service->list($payload);
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public function add(array $payload): array
    {
        return $this->service->add($payload);
    }
}
