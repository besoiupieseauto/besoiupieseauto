<?php

declare(strict_types=1);

namespace Besoiu\Services\AiIntelligence;

use Besoiu\Services\ProductImageAuditService;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Orchestrator AI Intelligence — rutare taskType → Ollama local / API extern (Pas 6).
 */
final class AiOrchestrator
{
    private string $root;
    private string $logPath;

    public function __construct(
        private readonly OllamaClient $ollama,
        private readonly ExternalLlmGateway $external,
        private readonly AiOrchestratorContextBuilder $contextBuilder,
        private readonly IntelligenceSearchService $search,
        ?string $projectRoot = null,
    ) {
        $this->root = $projectRoot ?? (defined('BESOIU_ROOT') ? (string) BESOIU_ROOT : dirname(__DIR__, 4));
        $dir = $this->root . '/admin/storage/ai_intelligence';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $this->logPath = $dir . '/orchestrator_routes.jsonl';
    }

    public static function create(?string $projectRoot = null): self
    {
        $root = $projectRoot ?? (defined('BESOIU_ROOT') ? (string) BESOIU_ROOT : dirname(__DIR__, 4));
        $store = new EventTrackingStore(null, $root);

        return new self(
            OllamaClient::create($root),
            new ExternalLlmGateway($root),
            new AiOrchestratorContextBuilder(new ProductSignalService($store, null, $root)),
            IntelligenceSearchService::create($root),
            $root,
        );
    }

    /** @return array<string, mixed> */
    public function status(): array
    {
        $ollama = $this->ollama->readiness();
        $external = $this->external->readiness();

        return [
            'tasks' => AiTaskRegistry::describeForUi(),
            'local' => [
                'ready' => !empty($ollama['ready']),
                'ollama' => $ollama,
            ],
            'external' => $external,
            'principle' => 'Prompt doar din agregate + search — fără evenimente brute din ai_intel_events.',
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function handleRequest(string $taskType, array $payload): array
    {
        $started = microtime(true);
        $taskType = trim($taskType);

        try {
            AiTaskRegistry::validate($taskType);
            $context = $this->contextBuilder->build($taskType, $payload);

            if (AiTaskRegistry::isLocal($taskType)) {
                $result = $this->dispatchLocal($taskType, $context, $payload);
                $response = $this->wrapSuccess($taskType, 'local', $result, $started);
            } else {
                $result = $this->dispatchComplex($taskType, $context);
                $response = $this->wrapSuccess($taskType, 'external', $result, $started);
            }

            $this->logRoute($response);

            return $response;
        } catch (InvalidArgumentException $e) {
            $response = $this->wrapError($taskType, $e->getMessage(), $started, 'validation');
            $this->logRoute($response);

            return $response;
        } catch (Throwable $e) {
            $response = $this->wrapError($taskType, $e->getMessage(), $started, 'exception');
            $this->logRoute($response);

            return $response;
        }
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function dispatchLocal(string $taskType, array $context, array $payload): array
    {
        return match ($taskType) {
            'classify_category' => $this->taskClassifyCategory($payload),
            'embedding' => $this->taskEmbedding($payload),
            'hybrid_search' => $this->taskHybridSearch($payload),
            'image_check' => $this->taskImageCheck($payload),
            default => throw new InvalidArgumentException('Task local neimplementat: ' . $taskType),
        };
    }

    /** @param array<string, mixed> $context @return array<string, mixed> */
    private function dispatchComplex(string $taskType, array $context): array
    {
        if (!$this->external->isConfigured()) {
            throw new RuntimeException(
                'EXTERNAL_LLM_API_KEY lipsește — task «' . $taskType . '» necesită API extern configurat.'
            );
        }

        [$system, $user] = match ($taskType) {
            'seo_description' => $this->promptSeoDescription($context),
            'price_competition_analysis' => $this->promptPriceAnalysis($context),
            'scraped_data_synthesis' => $this->promptScrapedSynthesis($context),
            default => throw new InvalidArgumentException('Task complex neimplementat: ' . $taskType),
        };

        $llm = $this->external->complete($system, $user, ['temperature' => 0.35]);
        if (empty($llm['ok'])) {
            throw new RuntimeException((string) ($llm['error'] ?? 'API extern eșuat'));
        }

        return [
            'content' => (string) ($llm['content'] ?? ''),
            'provider' => (string) ($llm['provider'] ?? ''),
            'model' => (string) ($llm['model'] ?? ''),
            'context_used' => [
                'product_signals_count' => count($context['product_signals'] ?? []),
                'search_summary_count' => count($context['search_summary'] ?? []),
                'aggregates_count' => count($context['aggregates'] ?? []),
            ],
        ];
    }

    /** @param array<string, mixed> $payload */
    private function taskClassifyCategory(array $payload): array
    {
        $text = trim((string) ($payload['text'] ?? $payload['product_name'] ?? $payload['query'] ?? ''));
        $brand = trim((string) ($payload['brand'] ?? ''));

        return $this->ollama->classifyCategory($text, $brand !== '' ? $brand : null);
    }

    /** @param array<string, mixed> $payload */
    private function taskEmbedding(array $payload): array
    {
        $text = trim((string) ($payload['text'] ?? ''));
        if ($text === '') {
            throw new InvalidArgumentException('text obligatoriu pentru embedding.');
        }
        $vector = $this->ollama->getEmbedding($text);

        return [
            'ok' => true,
            'dims' => count($vector),
            'model' => $this->ollama->embedModel(),
            'vector_preview' => array_slice($vector, 0, 8),
        ];
    }

    /** @param array<string, mixed> $payload */
    private function taskHybridSearch(array $payload): array
    {
        $query = trim((string) ($payload['query'] ?? ''));
        if ($query === '') {
            throw new InvalidArgumentException('query obligatoriu pentru hybrid_search.');
        }

        return $this->search->search($query, (int) ($payload['limit'] ?? 20), [
            'popularity_weight' => (float) ($payload['popularity_weight'] ?? 0.15),
            'semantic_weight' => (float) ($payload['semantic_weight'] ?? 0.45),
            'keyword_weight' => (float) ($payload['keyword_weight'] ?? 0.55),
            'signal_days' => (int) ($payload['signal_days'] ?? 7),
        ]);
    }

    /** @param array<string, mixed> $payload */
    private function taskImageCheck(array $payload): array
    {
        $productId = trim((string) ($payload['product_id'] ?? ''));
        if ($productId === '') {
            throw new InvalidArgumentException('product_id obligatoriu pentru image_check.');
        }

        $service = new ProductImageAuditService($this->root);
        $product = [
            'id' => $productId,
            'name' => (string) ($payload['product_name'] ?? $payload['name'] ?? ''),
            'pName' => (string) ($payload['product_name'] ?? $payload['name'] ?? ''),
            'pImages' => (string) ($payload['image_url'] ?? $payload['pImages'] ?? ''),
        ];

        $res = $service->analyzeProductWithOllamaVision($product);

        return [
            'ok' => !in_array(strtolower((string) ($res['verdict'] ?? '')), ['error', 'no_image'], true),
            'verdict' => (string) ($res['verdict'] ?? ''),
            'summary_ro' => (string) ($res['summary_ro'] ?? ''),
            'match_score' => (float) ($res['match_score'] ?? 0),
            'provider' => 'ollama_vision',
        ];
    }

    /** @param array<string, mixed> $context @return array{0:string,1:string} */
    private function promptSeoDescription(array $context): array
    {
        $input = is_array($context['input'] ?? null) ? $context['input'] : [];

        $system = 'Ești copywriter SEO pentru magazin piese auto Besoiu. '
            . 'Scrii descrieri clare în română, factual, fără invenții. '
            . 'Folosește DOAR datele din context (semnale agregate, fără evenimente individuale).';

        $user = "Generează descriere SEO scurtă + titlu meta (max 160 car.) pentru:\n"
            . json_encode([
                'product' => [
                    'name' => $input['product_name'] ?? '',
                    'brand' => $input['brand'] ?? '',
                    'category' => $input['category'] ?? '',
                    'oem' => $input['oem'] ?? '',
                ],
                'signals' => $context['product_signals'] ?? [],
                'search_context' => $context['search_summary'] ?? [],
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        return [$system, $user];
    }

    /** @param array<string, mixed> $context @return array{0:string,1:string} */
    private function promptPriceAnalysis(array $context): array
    {
        $input = is_array($context['input'] ?? null) ? $context['input'] : [];

        $system = 'Ești analist pricing auto B2C. Compari prețuri cu concurența folosind DOAR datele agregate furnizate. '
            . 'Răspunde structurat: poziție preț, recomandare, risc stoc.';

        $user = "Analizează poziția de preț:\n"
            . json_encode([
                'our_price_ron' => $input['our_price_ron'] ?? $input['price_ron'] ?? null,
                'competitor_prices' => $input['competitor_prices'] ?? [],
                'product_signals' => $context['product_signals'] ?? [],
                'aggregates' => $context['aggregates'] ?? [],
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        return [$system, $user];
    }

    /** @param array<string, mixed> $context @return array{0:string,1:string} */
    private function promptScrapedSynthesis(array $context): array
    {
        $input = is_array($context['input'] ?? null) ? $context['input'] : [];

        $system = 'Sintetizezi date scrapuite despre piese auto în bullet points acționabile pentru echipa Besoiu. '
            . 'Nu inventa prețuri sau stoc — marchează incertitudinile.';

        $user = "Sintetizează:\n"
            . json_encode([
                'topic' => $input['topic'] ?? '',
                'scraped_summary' => $input['scraped_summary'] ?? [],
                'aggregates' => $context['aggregates'] ?? [],
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        return [$system, $user];
    }

    /** @param array<string, mixed> $result */
    private function wrapSuccess(string $taskType, string $route, array $result, float $started): array
    {
        return [
            'ok' => true,
            'task_type' => $taskType,
            'route' => $route,
            'provider' => (string) ($result['provider'] ?? ($route === 'local' ? 'ollama' : 'external')),
            'model' => (string) ($result['model'] ?? ''),
            'result' => $result,
            'latency_ms' => (int) round((microtime(true) - $started) * 1000),
        ];
    }

    private function wrapError(string $taskType, string $message, float $started, string $kind): array
    {
        return [
            'ok' => false,
            'task_type' => $taskType,
            'route' => AiTaskRegistry::isComplex($taskType) ? 'external' : 'local',
            'error' => $message,
            'error_kind' => $kind,
            'latency_ms' => (int) round((microtime(true) - $started) * 1000),
        ];
    }

    /** @param array<string, mixed> $entry */
    private function logRoute(array $entry): void
    {
        $entry['ts'] = date('c');
        @file_put_contents(
            $this->logPath,
            json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n",
            FILE_APPEND | LOCK_EX
        );
    }
}
