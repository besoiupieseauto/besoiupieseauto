<?php
declare(strict_types=1);

namespace Besoiu\Modules\CoadaImport\Controller;

use Besoiu\Modules\CoadaImport\Service\CoadaImportAiService;
use Besoiu\Modules\CoadaImport\Service\CoadaImportService;

final class CoadaImportController
{
    public function __construct(
        private readonly CoadaImportService $service = new CoadaImportService(),
        private readonly CoadaImportAiService $aiService = new CoadaImportAiService(),
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
