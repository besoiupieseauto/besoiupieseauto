<?php
declare(strict_types=1);

namespace Besoiu\Modules\Categorii\Controller;

use Besoiu\Modules\Categorii\Service\CategoriiAiService;
use Besoiu\Modules\Categorii\Service\CategoriiService;

final class CategoriiController
{
    public function __construct(
        private readonly CategoriiService $service = new CategoriiService(),
        private readonly CategoriiAiService $aiService = new CategoriiAiService(),
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
