<?php

declare(strict_types=1);

namespace Besoiu\Services\AiIntelligence;

use Besoiu\Services\AiRag\OllamaEmbeddingsClient;
use Besoiu\Services\CategoryMatchService;
use Besoiu\Services\ModuleOllamaSupport;
use Besoiu\Services\OllamaLlmClient;
use RuntimeException;

/**
 * Fațadă Ollama pentru modulul AI Intelligence (Pas 4).
 *
 * - inferență locală (generate / classify)
 * - embeddings (indexare / hybrid search)
 * - fără fine-tuning live — doar inferență
 */
final class OllamaClient
{
    private string $root;
    private OllamaEmbeddingsClient $embeddings;
    private ?CategoryMatchService $categoryMatcher = null;
    private ?OllamaLlmClient $llm = null;

    public function __construct(?string $projectRoot = null)
    {
        $this->root = $projectRoot ?? (defined('BESOIU_ROOT') ? (string) BESOIU_ROOT : dirname(__DIR__, 4));
        $this->loadOllamaHelper();
        $this->embeddings = new OllamaEmbeddingsClient($this->root);
    }

    public static function create(?string $projectRoot = null): self
    {
        return new self($projectRoot);
    }

    /** @return array<string, mixed> */
    public function readiness(): array
    {
        $llm = $this->llmClient();
        $llmReady = $llm->readiness();
        $probe = $this->embeddings->embed('test');

        return [
            'enabled' => besoiu_ollama_enabled(),
            'ready' => !empty($llmReady['ready']) && !empty($probe['ok']),
            'base_url' => besoiu_ollama_base_url(),
            'chat_model' => $this->defaultChatModel(),
            'embed_model' => $this->embedModel(),
            'kv_cache_type' => besoiu_ollama_kv_cache_type(),
            'llm' => $llmReady,
            'embeddings' => [
                'ok' => !empty($probe['ok']),
                'dims' => count($probe['vector'] ?? []),
                'error' => (string) ($probe['error'] ?? ''),
            ],
            'runtime_hints' => besoiu_ollama_runtime_env_hints(),
        ];
    }

    public function defaultChatModel(): string
    {
        return besoiu_ollama_model();
    }

    public function embedModel(): string
    {
        return besoiu_ollama_embed_model();
    }

    /**
     * @return list<float>
     */
    public function getEmbedding(string $text, ?string $model = null): array
    {
        $result = $this->embeddings->embed($text, $model);
        if (empty($result['ok'])) {
            throw new RuntimeException((string) ($result['error'] ?? 'Embedding eșuat'));
        }

        return $result['vector'];
    }

    /**
     * Potrivește text (denumire produs / query) cu o categorie existentă din BD.
     *
     * @return array{
     *   ok:bool,
     *   category_id:int,
     *   subcategory_id:int,
     *   category:string,
     *   subcategory:string,
     *   confidence:float,
     *   method:string,
     *   error?:string
     * }
     */
    public function classifyCategory(string $text, ?string $brand = null): array
    {
        $text = trim($text);
        if ($text === '') {
            return [
                'ok' => false,
                'category_id' => 0,
                'subcategory_id' => 0,
                'category' => '',
                'subcategory' => '',
                'confidence' => 0.0,
                'method' => 'empty',
                'error' => 'Text gol',
            ];
        }

        if (!besoiu_ollama_enabled()) {
            return [
                'ok' => false,
                'category_id' => 0,
                'subcategory_id' => 0,
                'category' => '',
                'subcategory' => '',
                'confidence' => 0.0,
                'method' => 'disabled',
                'error' => 'Ollama dezactivat (OLLAMA_ENABLED=0)',
            ];
        }

        $match = $this->categoryMatcher()->match([
            'name' => $text,
            'brand' => $brand ?? '',
            'use_ollama' => true,
            'use_tecdoc' => false,
        ]);

        return [
            'ok' => !empty($match['ok']),
            'category_id' => (int) ($match['category_id'] ?? 0),
            'subcategory_id' => (int) ($match['subcategory_id'] ?? 0),
            'category' => (string) ($match['category'] ?? ''),
            'subcategory' => (string) ($match['subcategory'] ?? ''),
            'confidence' => (float) ($match['confidence'] ?? 0.0),
            'method' => (string) ($match['method'] ?? ''),
            'error' => !empty($match['ok']) ? null : (string) ($match['reasoning'] ?? $match['ollama']['error'] ?? 'Fără potrivire'),
        ];
    }

    /**
     * Inferență text simplă (clasificare, extragere scurtă).
     *
     * @param array<string, mixed> $options
     * @return array{content:string,model:string,provider:string}
     */
    public function complete(string $taskContext, string $prompt, string $system = '', array $options = []): array
    {
        $support = ModuleOllamaSupport::create($this->root);
        $moduleId = (string) ($options['module_id'] ?? 'ai_intelligence');
        unset($options['module_id']);
        $response = $support->complete(
            $moduleId,
            $prompt,
            $system,
            $taskContext,
            $options
        );

        return [
            'content' => trim((string) ($response['content'] ?? $response['text'] ?? '')),
            'model' => (string) ($response['model'] ?? $this->defaultChatModel()),
            'provider' => (string) ($response['provider'] ?? 'ollama'),
        ];
    }

    private function categoryMatcher(): CategoryMatchService
    {
        if ($this->categoryMatcher === null) {
            $this->categoryMatcher = new CategoryMatchService(null, ModuleOllamaSupport::create($this->root));
        }

        return $this->categoryMatcher;
    }

    private function llmClient(): OllamaLlmClient
    {
        if ($this->llm === null) {
            $this->llm = new OllamaLlmClient($this->root . '/app');
        }

        return $this->llm;
    }

    private function loadOllamaHelper(): void
    {
        $helper = $this->root . '/app/Backend/system/ollama_llm.php';
        if (is_file($helper)) {
            require_once $helper;
        }
    }
}
