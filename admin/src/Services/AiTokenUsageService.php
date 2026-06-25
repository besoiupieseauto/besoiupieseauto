<?php

declare(strict_types=1);

namespace Evasystem\Services;

/**
 * Serviciu admin — dashboard consum tokeni AI.
 */
final class AiTokenUsageService
{
    private function boot(): void
    {
        require_once dirname(__DIR__, 2) . '/system/ai_token_usage.php';
    }

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    public function list(array $filters = []): array
    {
        $this->boot();
        $pdo = ai_token_pdo();
        $query = ai_token_query($pdo, $filters);

        return [
            'items' => $query['items'],
            'total' => $query['total'],
            'stats' => ai_token_stats($pdo),
            'alerts' => ai_token_alerts($pdo),
            'thresholds' => ai_token_thresholds($pdo),
        ];
    }

    /** @return array<string, mixed> */
    public function stats(): array
    {
        $this->boot();
        $pdo = ai_token_pdo();

        return [
            'stats' => ai_token_stats($pdo),
            'alerts' => ai_token_alerts($pdo),
            'thresholds' => ai_token_thresholds($pdo),
        ];
    }

    /** @param array<string, mixed> $payload */
    public function saveThreshold(array $payload): void
    {
        $this->boot();
        ai_token_save_threshold(ai_token_pdo(), $payload);
    }
}
