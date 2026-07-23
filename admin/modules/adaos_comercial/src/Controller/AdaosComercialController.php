<?php
declare(strict_types=1);

namespace Besoiu\Modules\AdaosComercial\Controller;

use Besoiu\Modules\AdaosComercial\Service\AdaosComercialAiService;
use Besoiu\Modules\AdaosComercial\Service\AdaosComercialService;

final class AdaosComercialController
{
    public function __construct(
        private readonly AdaosComercialService $service = new AdaosComercialService(),
        private readonly AdaosComercialAiService $aiService = new AdaosComercialAiService(),
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

    /** @return array<string, mixed> */
    public function ollamaStatus(): array
    {
        return $this->aiService->ollamaStatus();
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public function aiComplete(array $payload): array
    {
        return $this->aiService->complete($payload);
    }
}
