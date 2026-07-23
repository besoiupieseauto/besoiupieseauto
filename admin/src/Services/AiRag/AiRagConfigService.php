<?php

declare(strict_types=1);

namespace Besoiu\Services\AiRag;

use Config\Database;
use PDO;
use Throwable;

/**
 * Config module AI — toggle, model, praguri (din panou, nu hardcodat).
 */
final class AiRagConfigService
{
    private string $root;
    private string $configPath;

    public function __construct(?string $projectRoot = null)
    {
        $this->root = $projectRoot ?? (defined('BESOIU_ROOT') ? (string) BESOIU_ROOT : dirname(__DIR__, 4));
        $dir = $this->root . '/admin/storage/ai_rag';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $this->configPath = $dir . '/modules.json';
    }

    /** @return array<string, mixed> */
    public function allModulesWithConfig(): array
    {
        $saved = $this->readSaved();
        $out = [];
        foreach (AiRagModuleRegistry::all() as $id => $meta) {
            $cfg = (array) ($saved[$id] ?? []);
            $out[] = array_merge($meta, [
                'enabled' => (bool) ($cfg['enabled'] ?? $meta['default_enabled'] ?? false),
                'model' => (string) ($cfg['model'] ?? $meta['default_model'] ?? 'qwen2.5:7b'),
                'thresholds' => array_merge(
                    (array) ($meta['default_thresholds'] ?? []),
                    (array) ($cfg['thresholds'] ?? [])
                ),
                'phase_available' => (int) ($meta['phase'] ?? 99) <= AiRagModuleRegistry::maxAvailablePhase()
                    || in_array($id, AiRagModuleRegistry::phase2EnabledIds(), true),
            ]);
        }

        return ['modules' => $out, 'updated_at' => (string) ($saved['_meta']['updated_at'] ?? '')];
    }

    /** @param array<string, mixed> $patch */
    public function saveModule(string $moduleId, array $patch): array
    {
        $meta = AiRagModuleRegistry::get($moduleId);
        if ($meta === null) {
            return ['ok' => false, 'error' => 'Modul necunoscut'];
        }

        $saved = $this->readSaved();
        $current = (array) ($saved[$moduleId] ?? []);
        if (array_key_exists('enabled', $patch)) {
            $current['enabled'] = !empty($patch['enabled']);
        }
        if (isset($patch['model']) && trim((string) $patch['model']) !== '') {
            $current['model'] = trim((string) $patch['model']);
        }
        if (isset($patch['thresholds']) && is_array($patch['thresholds'])) {
            $current['thresholds'] = $patch['thresholds'];
        }
        $saved[$moduleId] = $current;
        $saved['_meta'] = ['updated_at' => date('c')];
        $this->writeSaved($saved);

        return ['ok' => true, 'module_id' => $moduleId, 'config' => $current];
    }

    /** @return array{enabled:bool,model:string,thresholds:array<string,mixed>}|null */
    public function moduleRuntime(string $moduleId): ?array
    {
        $meta = AiRagModuleRegistry::get($moduleId);
        if ($meta === null) {
            return null;
        }
        $saved = $this->readSaved();
        $cfg = (array) ($saved[$moduleId] ?? []);

        return [
            'enabled' => (bool) ($cfg['enabled'] ?? $meta['default_enabled'] ?? false),
            'model' => (string) ($cfg['model'] ?? $meta['default_model'] ?? 'qwen2.5:7b'),
            'thresholds' => array_merge(
                (array) ($meta['default_thresholds'] ?? []),
                (array) ($cfg['thresholds'] ?? [])
            ),
        ];
    }

    /** @return array<string, mixed> */
    private function readSaved(): array
    {
        if (!is_file($this->configPath)) {
            return [];
        }
        $json = json_decode((string) file_get_contents($this->configPath), true);

        return is_array($json) ? $json : [];
    }

    /** @param array<string, mixed> $data */
    private function writeSaved(array $data): void
    {
        file_put_contents(
            $this->configPath,
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }
}
