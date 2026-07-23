<?php

declare(strict_types=1);

namespace Besoiu\Services\AiSupervisor\Phase3;

use Besoiu\Services\AiSupervisor\Store\AiSupervisorStore;

/**
 * Faza 3a — raport health pipeline imagini (dezactivat odată cu modulul de test).
 */
final class PipelineHealthReporter
{
    public function __construct(
        private ?AiSupervisorStore $store = null,
    ) {
        $this->store = $store ?? new AiSupervisorStore();
    }

    /** @return array<string, mixed> */
    public function run(): array
    {
        $report = [
            'ok' => false,
            'http' => 410,
            'generated_at' => date('c'),
            'duration_ms' => 0,
            'summary' => ['ok' => 0, 'fail' => 0, 'total' => 0],
            'failed_tests' => [],
            'passed' => 0,
            'failed' => 0,
            'total' => 0,
            'recommendations' => ['Verificările pipeline imagini au fost dezactivate.'],
            'message' => 'Modulul de test pipeline imagini a fost eliminat.',
        ];

        $this->store->write('pipeline_health', $report);

        return $report;
    }

    /** @return array<string, mixed> */
    public function latest(): array
    {
        return $this->store->read('pipeline_health');
    }
}
