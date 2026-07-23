<?php

declare(strict_types=1);

namespace Besoiu\Services\AiSupervisor\Config;

/**
 * Configurare supervizor AI — persistată pe disc, controlabilă din UI.
 */
final class AiSupervisorConfig
{
    private string $path;

    public function __construct(?string $projectRoot = null)
    {
        $root = $projectRoot ?? dirname(__DIR__, 5);
        $dir = $root . '/robot/data/ai_supervisor';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $this->path = $dir . '/config.json';
    }

    /** @return array<string, mixed> */
    public function defaults(): array
    {
        return [
            'enabled' => true,
            'phase1_admin_hooks' => true,
            'phase2_catalog_scan' => true,
            'phase3_pipeline_health' => true,
            'phase3_token_budget' => true,
            'phase3_supplier_watch' => true,
            'phase4_diagnostics' => true,
            'phase4_conversations' => true,
            'phase4_daily_report' => true,
            'phase5_composer_repair' => true,
            'composer_repair_use_llm' => true,
            'composer_repair_auto_execute' => false,
            'composer_repair_min_confidence' => 0.72,
            'composer_repair_max_items' => 5,
            'intervals_sec' => [
                'catalog_scan' => 3600,
                'pipeline_health' => 21600,
                'supplier_watch' => 1800,
                'diagnostics' => 900,
                'conversations' => 600,
                'daily_report' => 86400,
                'composer_repair' => 1200,
            ],
            'token_daily_limit' => 500000,
            'token_warn_pct' => 80,
            'catalog_scan_limit' => 500,
            'diagnostics_max_items' => 8,
            'diagnostics_use_llm' => true,
            'conversation_tail_lines' => 120,
            'autonomy_enabled' => true,
            'autonomy_proposals_only' => true,
            'updated_at' => null,
        ];
    }

    /** @return array<string, mixed> */
    public function get(): array
    {
        if (!is_file($this->path)) {
            return $this->defaults();
        }
        $json = json_decode((string) file_get_contents($this->path), true);
        if (!is_array($json)) {
            return $this->defaults();
        }

        return array_replace_recursive($this->defaults(), $json);
    }

    /** @param array<string, mixed> $patch */
    public function set(array $patch): array
    {
        $allowed = array_keys($this->defaults());
        $allowed[] = 'intervals_sec';
        $filtered = [];
        foreach ($patch as $key => $value) {
            if ($key === 'intervals_sec' && is_array($value)) {
                $filtered['intervals_sec'] = $value;
                continue;
            }
            if (in_array($key, $allowed, true)) {
                $filtered[$key] = $value;
            }
        }

        $current = $this->get();
        $merged = array_replace_recursive($current, $filtered);
        $merged['updated_at'] = date('c');
        file_put_contents(
            $this->path,
            json_encode($merged, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );

        return $merged;
    }

    public function isEnabled(): bool
    {
        return !empty($this->get()['enabled']);
    }

    public function intervalSec(string $key): int
    {
        $cfg = $this->get();
        $intervals = is_array($cfg['intervals_sec'] ?? null) ? $cfg['intervals_sec'] : [];

        return max(60, (int) ($intervals[$key] ?? 3600));
    }

    public function isPhaseEnabled(string $phaseKey): bool
    {
        $cfg = $this->get();

        return !empty($cfg[$phaseKey]);
    }

    public function isAutonomyEnabled(): bool
    {
        return !empty($this->get()['autonomy_enabled']);
    }

    public function isAutonomyProposalsOnly(): bool
    {
        $cfg = $this->get();

        return !isset($cfg['autonomy_proposals_only']) || !empty($cfg['autonomy_proposals_only']);
    }
}
