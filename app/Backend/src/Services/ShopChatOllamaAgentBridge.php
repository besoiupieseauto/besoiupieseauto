<?php

declare(strict_types=1);

namespace Besoiu\Services;

/**
 * Leagă chat public (widget) de agenții Ollama specializați.
 */
final class ShopChatOllamaAgentBridge
{
    /** @var array<string, string> */
    private const SLUG_MAP = [
        'agent-produse' => 'agent-produse',
        'agent-clienti' => 'agent-clienti',
        'catalog-stoc' => 'agent-produse',
        'leaduri-cos' => 'agent-clienti',
        'comenzi-livrare' => 'agent-clienti',
        'vin-compatibilitate' => 'agent-produse',
        'multicanal' => 'agent-clienti',
    ];

    public function __construct(
        private readonly string $projectRoot,
        private readonly ?AiOllamaAgentRunnerService $runner = null,
    ) {
    }

    public static function isEnabled(): bool
    {
        $raw = strtolower(trim((string) ($_ENV['SHOP_CHAT_OLLAMA_AGENTS'] ?? getenv('SHOP_CHAT_OLLAMA_AGENTS') ?: '1')));

        return !in_array($raw, ['0', 'false', 'no', 'off'], true);
    }

    public function resolveCoreAgent(string $routerSlug, string $message): string
    {
        $slug = trim($routerSlug);
        if (isset(self::SLUG_MAP[$slug])) {
            return self::SLUG_MAP[$slug];
        }

        $m = mb_strtolower($message, 'UTF-8');
        if (preg_match('/\b(comand|awb|livrare|retur|garan|status)\b/u', $m)) {
            return 'agent-clienti';
        }
        if (preg_match('/\b(pret|preț|stoc|oem|vin|pies|catalog|cod)\b/u', $m)) {
            return 'agent-produse';
        }

        return '';
    }

    /**
     * @param list<array{role:string,content:string}> $messages
     * @param array<string, mixed> $contextOptions phone, email, visitor_key, channel
     * @return array<string, mixed>|null
     */
    public function tryReply(
        string $routerSlug,
        string $message,
        array $messages = [],
        string $pagePath = '',
        array $contextOptions = []
    ): ?array {
        if (!self::isEnabled()) {
            return null;
        }

        $core = $this->resolveCoreAgent($routerSlug, $message);
        if ($core === '' || !in_array($core, AiOllamaAgentRunnerService::CORE_SLUGS, true)) {
            return null;
        }

        $runner = $this->runner ?? new AiOllamaAgentRunnerService($this->projectRoot);
        $status = $runner->status();
        $agentReady = false;
        foreach ($status['agents'] ?? [] as $agent) {
            if (!is_array($agent)) {
                continue;
            }
            if (($agent['slug'] ?? '') === $core && !empty($agent['ready'])) {
                $agentReady = true;
                break;
            }
        }
        if (!$agentReady) {
            return null;
        }

        $enriched = $message;
        if ($pagePath !== '') {
            $enriched .= "\n\n[context pagină: {$pagePath}]";
        }

        $chatOptions = array_merge($contextOptions, [
            'channel' => (string) ($contextOptions['channel'] ?? 'shop_chat'),
        ]);

        $result = $runner->chat($core, $enriched, $chatOptions);
        if (empty($result['ok'])) {
            if ($core === 'agent-produse') {
                $rag = new CatalogRagService($this->projectRoot);
                $search = $rag->searchByMessage($message, 5);
                (new ShopSearchMissLogService())->logMiss(
                    $message,
                    (string) ($chatOptions['channel'] ?? 'shop_chat'),
                    $search['total'],
                    isset($chatOptions['visitor_key']) ? (string) $chatOptions['visitor_key'] : null
                );
            }
            $escalation = new ShopChatEscalationService();
            if ($escalation->shouldEscalate($result, $message)) {
                $escalation->create([
                    'message' => $message,
                    'channel' => (string) ($chatOptions['channel'] ?? 'shop_chat'),
                    'visitor_key' => (string) ($chatOptions['visitor_key'] ?? ''),
                    'phone' => (string) ($chatOptions['phone'] ?? ''),
                    'email' => (string) ($chatOptions['email'] ?? ''),
                    'reason' => 'no_answer',
                    'meta' => ['agent' => $core, 'error' => (string) ($result['error'] ?? '')],
                ]);
            }
            return null;
        }

        if ($core === 'agent-produse') {
            $rag = new CatalogRagService($this->projectRoot);
            $search = $rag->searchByMessage($message, 5);
            if ($search['total'] === 0) {
                (new ShopSearchMissLogService())->logMiss(
                    $message,
                    (string) ($chatOptions['channel'] ?? 'shop_chat'),
                    0,
                    isset($chatOptions['visitor_key']) ? (string) $chatOptions['visitor_key'] : null
                );
            }
        }

        $result['handled_by'] = 'ollama_agent';
        $result['agent_slug'] = $core;
        $result['router_slug'] = $routerSlug;

        return $result;
    }
}
