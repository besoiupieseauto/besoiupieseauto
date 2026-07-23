<?php

declare(strict_types=1);

namespace Besoiu\Async\Handlers;

use Besoiu\Async\JobHandlerInterface;

/**
 * Handler demo — simulează procesare în pași (folosit la testare worker/SSE).
 */
final class DemoProgressJobHandler implements JobHandlerInterface
{
    public function handle(array $payload, callable $reportProgress): array
    {
        $steps = max(1, (int) ($payload['steps'] ?? 5));
        $sleepMs = max(0, (int) ($payload['step_sleep_ms'] ?? 400));

        for ($i = 1; $i <= $steps; $i++) {
            $pct = (int) round(($i / $steps) * 100);
            $reportProgress($pct, 'Pas ' . $i . '/' . $steps);
            if ($sleepMs > 0) {
                usleep($sleepMs * 1000);
            }
        }

        return [
            'message' => (string) ($payload['message'] ?? 'Demo finalizat'),
            'steps' => $steps,
        ];
    }
}
