<?php

declare(strict_types=1);

namespace Besoiu\Services;

/**
 * Punct unic Ollama pentru module ERP — readiness + dispatch.
 * Folosește același .env (OLLAMA_*) ca admin, import, scraper.
 */
final class ModuleOllamaSupport
{
    public function __construct(
        private readonly string $projectRoot,
        private readonly ?MetroAiOrchestrator $orchestrator = null,
        private readonly ?OllamaLlmClient $ollama = null,
    ) {
    }

    public static function create(?string $projectRoot = null): self
    {
        $root = $projectRoot ?? (defined('BESOIU_ROOT') ? (string) BESOIU_ROOT : dirname(__DIR__, 3));

        return new self($root);
    }

    /** @return array<string, mixed> */
    public function readiness(): array
    {
        $ollama = $this->ollamaClient();
        $text = $ollama->readiness();
        $vision = $ollama->visionReadiness();
        $orch = $this->orchestrator()->readiness();

        return [
            'enabled' => $text['enabled'] ?? false,
            'reachable' => $text['reachable'] ?? false,
            'ready' => ($text['ready'] ?? false) && ($vision['ready'] ?? false),
            'text_model' => $text['model'] ?? '',
            'text_installed' => $text['model_installed'] ?? false,
            'vision_model' => $vision['model'] ?? '',
            'vision_installed' => $vision['model_installed'] ?? false,
            'base_url' => $text['base_url'] ?? '',
            'router_mode' => $this->routerMode(),
            'metro_ready' => !empty($orch['ready']),
            'message_ro' => $this->buildMessage($text, $vision),
        ];
    }

    /**
     * @param array<string, mixed> $options module_id, temperature, timeout_sec
     * @return array<string, mixed>
     */
    public function complete(
        string $moduleId,
        string $userPrompt,
        string $systemPrompt = '',
        string $taskContext = 'default',
        array $options = [],
    ): array {
        if ($systemPrompt === '') {
            $systemPrompt = 'Ești un asistent pentru modulul ERP «' . $moduleId . '». Răspunde concis în română.';
        }

        $options['module_id'] = $moduleId;
        $workCtx = $this->buildWorkContext($moduleId, $taskContext, $userPrompt);
        $this->ollamaClient()->pushCallContext($workCtx);

        try {
            $res = $this->orchestrator()->dispatchComplete(
                $taskContext,
                $systemPrompt,
                $userPrompt,
                $options,
            );
        } finally {
            $this->ollamaClient()->clearCallContext();
        }

        return $res;
    }

    private function ollamaClient(): OllamaLlmClient
    {
        return $this->ollama ?? new OllamaLlmClient($this->projectRoot);
    }

    private function orchestrator(): MetroAiOrchestrator
    {
        if ($this->orchestrator !== null) {
            return $this->orchestrator;
        }

        $router = new LlmRouterService($this->projectRoot, $this->ollamaClient());

        return new MetroAiOrchestrator($this->projectRoot, $router);
    }

    private function routerMode(): string
    {
        $helper = dirname(__DIR__, 2) . '/system/ollama_llm.php';
        if (is_file($helper)) {
            require_once $helper;

            return besoiu_llm_router_mode();
        }

        return 'ollama_first';
    }

    /** @param array<string, mixed> $text @param array<string, mixed> $vision */
    private function buildMessage(array $text, array $vision): string
    {
        if (empty($text['enabled'])) {
            return 'Ollama dezactivat — setează OLLAMA_ENABLED=1 în .env.';
        }
        if (empty($text['reachable'])) {
            return 'Ollama indisponibil — pornește ollama serve (127.0.0.1:11434).';
        }
        if (empty($text['model_installed'])) {
            return 'Model text lipsă — rulează: ollama pull ' . ($text['model'] ?? 'qwen2.5:7b');
        }
        if (empty($vision['model_installed'])) {
            return 'Model vision lipsă — rulează: ollama pull ' . ($vision['model'] ?? 'llava:7b');
        }

        return sprintf(
            'Ollama OK · %s · vision %s',
            (string) ($text['model'] ?? ''),
            (string) ($vision['model'] ?? '')
        );
    }

    /** @return array<string, string> */
    private function buildWorkContext(string $moduleId, string $taskContext, string $userPrompt): array
    {
        if (!$this->isImportModule($moduleId, $taskContext)) {
            return ['path' => $this->adminRequestPath()];
        }

        return [
            'section' => 'import',
            'source' => 'import.' . $taskContext,
            'task_label' => $this->importTaskLabel($taskContext),
            'path' => $this->importAdminPath($moduleId),
            'input_excerpt' => $this->extractPromptExcerpt($userPrompt, $taskContext),
        ];
    }

    private function isImportModule(string $moduleId, string $taskContext): bool
    {
        if (in_array($moduleId, ['categorii', 'importreview', 'import', 'import_pro', 'coada_import'], true)) {
            return true;
        }

        return str_contains($taskContext, 'import')
            || str_contains($taskContext, 'normalize')
            || str_contains($taskContext, 'category');
    }

    private function importTaskLabel(string $taskContext): string
    {
        return match ($taskContext) {
            'category_match' => 'Potrivire categorie',
            'product_name_normalize' => 'Normalizare denumire',
            'import_image_gate' => 'Verificare imagine',
            default => ucfirst(str_replace('_', ' ', $taskContext)),
        };
    }

    private function importAdminPath(string $moduleId): string
    {
        return match ($moduleId) {
            'categorii' => '/admin/categorii',
            'import_pro' => '/admin/import-pro',
            default => '/admin/importreview',
        };
    }

    private function adminRequestPath(): string
    {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '');
        if ($uri === '') {
            return '';
        }
        $path = parse_url($uri, PHP_URL_PATH);

        return is_string($path) ? $path : $uri;
    }

    private function extractPromptExcerpt(string $prompt, string $taskContext): string
    {
        if ($taskContext === 'category_match' && preg_match('/- Denumire:\s*(.+)/u', $prompt, $nameMatch)) {
            $name = trim($nameMatch[1]);
            if ($name === '(lipsă)') {
                $name = '';
            }
            $brand = '';
            if (preg_match('/- Brand:\s*(.+)/u', $prompt, $brandMatch)) {
                $brand = trim($brandMatch[1]);
                if ($brand === '(lipsă)') {
                    $brand = '';
                }
            }
            $excerpt = $brand !== '' ? $brand . ' · ' . $name : $name;

            return mb_substr(trim($excerpt), 0, 180);
        }

        if ($taskContext === 'product_name_normalize' && preg_match('/Denumire TecDoc \(sursă\):\s*(.+)/u', $prompt, $m)) {
            return mb_substr(trim($m[1]), 0, 180);
        }

        $line = trim(explode("\n", $prompt)[0] ?? '');

        return $line === '' ? '' : mb_substr($line, 0, 180);
    }
}
