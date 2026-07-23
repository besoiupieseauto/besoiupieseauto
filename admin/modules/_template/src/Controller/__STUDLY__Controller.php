<?php
declare(strict_types=1);

namespace Besoiu\Modules\__STUDLY__\Controller;

use Besoiu\Modules\__STUDLY__\Service\__STUDLY__AiService;
use Besoiu\Modules\__STUDLY__\Service\__STUDLY__Service;

final class __STUDLY__Controller
{
    public function __construct(
        private readonly __STUDLY__Service $service = new __STUDLY__Service(),
        private readonly __STUDLY__AiService $aiService = new __STUDLY__AiService(),
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
