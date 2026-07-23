<?php
declare(strict_types=1);

namespace Besoiu\Modules\Produse\Controller;

use Besoiu\Modules\Produse\Service\ProduseAiService;
use Besoiu\Modules\Produse\Service\ProduseService;

final class ProduseController
{
    public function __construct(
        private readonly ProduseService $service = new ProduseService(),
        private readonly ProduseAiService $aiService = new ProduseAiService(),
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
