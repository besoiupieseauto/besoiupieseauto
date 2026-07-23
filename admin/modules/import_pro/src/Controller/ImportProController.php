<?php
declare(strict_types=1);

namespace Besoiu\Modules\ImportPro\Controller;

use Besoiu\Modules\ImportPro\Service\ImportProAiService;
use Besoiu\Modules\ImportPro\Service\ImportProService;

final class ImportProController
{
    public function __construct(
        private readonly ImportProService $service = new ImportProService(),
        private readonly ImportProAiService $aiService = new ImportProAiService(),
    ) {
    }

    /** @return array<string, mixed> */
    public function bridgeStatus(): array
    {
        \Besoiu\Modules\ImportPro\Support\ImportProBridge::boot();

        return [
            'motor_root' => \Besoiu\Modules\ImportPro\Support\ImportProBridge::root(),
            'embed_url' => \Besoiu\Modules\ImportPro\Support\ImportProBridge::standaloneUrl(),
            'api_base' => \Besoiu\Modules\ImportPro\Support\ImportProBridge::apiBaseUrl(),
            'erp_public_ready' => \Besoiu\Modules\ImportPro\Support\ImportProBridge::erpPublicReady(),
            'status_lines' => \Besoiu\Modules\ImportPro\Support\ImportProBridge::statusLines(),
        ];
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
