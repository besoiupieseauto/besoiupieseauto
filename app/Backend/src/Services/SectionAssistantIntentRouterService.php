<?php

declare(strict_types=1);

namespace Besoiu\Services;

/**
 * Decodează intenția utilizatorului (LLM + reguli) înainte de keyword routing.
 * Flux: mesaj natural → handler + mesaj normalizat → interogare live SQL.
 */
final class SectionAssistantIntentRouterService
{
    private string $projectRoot;

    public function __construct(
        ?string $projectRoot = null,
        private ?LlmRouterService $llm = null,
    ) {
        $this->projectRoot = $projectRoot ?? dirname(__DIR__, 3);
        $this->llm = $llm ?? LlmRouterService::create($this->projectRoot);
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>|null plan Composer gata de afișat
     */
    public function resolve(
        string $message,
        string $section,
        array $context,
        SectionAssistantModuleService $modules,
    ): ?array {
        $message = SectionAssistantQueryHelper::normalizeComposerTypos(trim($message));
        if ($message === ''
            || SectionAssistantActionService::messageLooksLikeAction($message)
            || SectionAssistantConversationService::shouldSkipIntentRouter($message)) {
            return null;
        }

        $decoded = $this->decodeDeterministic($message);
        if ($decoded === null && $this->shouldUseLlmDecoder($message)) {
            $decoded = $this->decodeWithLlm($message, $section, $context);
        }

        if ($decoded === null || ($decoded['handler'] ?? '') === '' || ($decoded['handler'] ?? '') === 'none') {
            return null;
        }

        return $this->dispatchDecoded($decoded, $message, $section, $context, $modules);
    }

    /**
     * @return array{handler:string,normalized_message:string,client_name:?string,confidence:float,decoder:string}|null
     */
    public function decodeDeterministic(string $message): ?array
    {
        $lower = mb_strtolower($message, 'UTF-8');

        $code = SectionAssistantQueryHelper::extractProductCode($message);
        if ($code !== null && SectionAssistantQueryHelper::wantsProductCodeLookup($message)) {
            return [
                'handler' => 'product_by_code',
                'normalized_message' => 'produs cod ' . $code,
                'client_name' => null,
                'confidence' => 0.98,
                'decoder' => 'rules',
            ];
        }

        if ($this->looksLikeClientInventory($message)) {
            return [
                'handler' => 'client_list',
                'normalized_message' => 'cate clienti sunt in sistem',
                'client_name' => null,
                'confidence' => 0.95,
                'decoder' => 'rules',
            ];
        }

        $clientName = $this->extractRealClientName($message);
        if ($clientName !== null && $this->looksLikeClientOrdersAsk($lower)) {
            return [
                'handler' => 'client_orders',
                'normalized_message' => 'comenzi pentru ' . $clientName,
                'client_name' => $clientName,
                'confidence' => 0.9,
                'decoder' => 'rules',
            ];
        }

        if (preg_match('/\b(comenz\w*|orders?)\b/u', $lower)
            && SectionAssistantQueryHelper::hasLiveDataAsk($message)
            && !preg_match('/\b(factur\w*|awb|cos|cart)\b/u', $lower)
            && $clientName === null) {
            return [
                'handler' => 'orders_summary',
                'normalized_message' => 'cate comenzi sunt in sistem',
                'client_name' => null,
                'confidence' => 0.85,
                'decoder' => 'rules',
            ];
        }

        $productMode = SectionAssistantQueryHelper::resolveProductQueryMode($message);
        if ($productMode === 'filter') {
            return [
                'handler' => 'product_filter',
                'normalized_message' => trim($message),
                'client_name' => null,
                'confidence' => 0.96,
                'decoder' => 'rules',
            ];
        }
        if ($productMode === 'list') {
            return [
                'handler' => 'product_list',
                'normalized_message' => 'lista tuturor produselor active',
                'client_name' => null,
                'confidence' => 0.94,
                'decoder' => 'rules',
            ];
        }
        if ($productMode === 'summary') {
            return [
                'handler' => 'product_inventory',
                'normalized_message' => 'rezumat inventar produse online',
                'client_name' => null,
                'confidence' => 0.88,
                'decoder' => 'rules',
            ];
        }

        if (preg_match('/\b(vitrin[aă]|homepage)\b/u', $lower)
            && !preg_match('/\b(pune|adaug[aă]?|scoate|sterge|mut[aă]?)\b/u', $lower)) {
            return [
                'handler' => 'vitrina_homepage',
                'normalized_message' => trim($message),
                'client_name' => null,
                'confidence' => 0.95,
                'decoder' => 'rules',
            ];
        }

        if (preg_match('/\b(fara|fără)\s+imagine/u', $lower)) {
            return [
                'handler' => 'product_filter',
                'normalized_message' => 'produse fara imagine',
                'client_name' => null,
                'confidence' => 0.94,
                'decoder' => 'rules',
            ];
        }

        if (preg_match('/\b(furnizor\w*|supplier\w*)\b/u', $lower)
            && preg_match('/\b(cum\s+(?:sa|să)|how\s+to|adaug[aă]?|inregistr|înregistr)\b/u', $lower)) {
            return [
                'handler' => 'supplier_list',
                'normalized_message' => 'cum adaug furnizor in admin',
                'client_name' => null,
                'confidence' => 0.9,
                'decoder' => 'rules',
            ];
        }

        return null;
    }

    private function looksLikeClientInventory(string $message): bool
    {
        $lower = mb_strtolower($message, 'UTF-8');
        if (!preg_match('/\b(client\w*|clienti|customers?)\b/u', $lower)) {
            return false;
        }
        if (!SectionAssistantQueryHelper::hasLiveDataAsk($message)) {
            return false;
        }
        if ($this->extractRealClientName($message) !== null) {
            return false;
        }

        return (bool) preg_match(
            '/\b(am\s+eu|in\s+sistem|din\s+sistem|pe\s+proiect|inregistrat\w*|activ\w*|total|toti|toți|tot)\b/u',
            $lower
        ) || !preg_match('/\b(pentru|lui|named|numit)\b/u', $lower);
    }

    private function looksLikeClientOrdersAsk(string $lower): bool
    {
        return (bool) preg_match('/\b(comenz\w*|comanda|orders?)\b/u', $lower)
            || ((bool) preg_match('/\b(pentru|lui|de\s+la)\b/u', $lower)
                && (bool) preg_match('/\b(ce|care|date|detali\w*|info)\b/u', $lower));
    }

    public function extractRealClientName(string $message): ?string
    {
        $name = null;

        if (preg_match(
            '/\b(?:pentru|lui|de\s+la|al\s+lui)\s+((?:[A-ZÀ-Ÿ][a-zà-ÿ]+(?:\s+[A-ZÀ-Ÿ][a-zà-ÿ]+){0,3}|[A-Za-zÀ-ÿ]{2,}(?:\s+[A-Za-zÀ-ÿ]{2,}){0,3}))/u',
            $message,
            $m
        )) {
            $name = trim($m[1]);
        } elseif (preg_match(
            '/\b(?:client(?:ul)?)\s+((?:[A-ZÀ-Ÿ][a-zà-ÿ]+(?:\s+[A-ZÀ-Ÿ][a-zà-ÿ]+){0,3}))/u',
            $message,
            $m
        )) {
            $name = trim($m[1]);
        } elseif (preg_match(
            '/\bcomenz\w*\s+(?:lui|pentru|client(?:ul)?)\s+((?:[A-Za-zÀ-ÿ][A-Za-zÀ-ÿ\-\']*(?:\s+[A-Za-zÀ-ÿ][A-Za-zÀ-ÿ\-\']*){0,4}))/iu',
            $message,
            $m
        )) {
            $name = trim($m[1]);
        } elseif (preg_match('/\b([A-ZÀ-Ÿ][a-zà-ÿ]+(?:\s+[A-ZÀ-Ÿ][a-zà-ÿ]+)+)\b/u', $message, $m)) {
            $name = trim($m[1]);
        }

        if ($name === null || $name === '') {
            return null;
        }

        $name = SectionAssistantQueryHelper::sanitize($name);
        if (preg_match('/^(.+?)\s+(?:am|sunt|care|ce|c[aâ]te|cu|sa|s[aă]|vreau|despre|orders?|comenz\w*)\b/iu', $name, $cut)) {
            $name = trim($cut[1]);
        }
        $name = SectionAssistantQueryHelper::sanitize($name);

        if ($name === '' || SectionAssistantQueryHelper::isBoilerplateClientName($name)) {
            return null;
        }

        return $name;
    }

    private function shouldUseLlmDecoder(string $message): bool
    {
        $ready = $this->llm->readiness();

        if (empty($ready['ready']) || !$this->llm->isConfigured()) {
            return false;
        }

        if ($this->decodeDeterministic($message) !== null) {
            return false;
        }

        $lower = mb_strtolower($message, 'UTF-8');
        if (preg_match('/\b(vitrin[aă]|homepage|inventar|fara\s+imagine|fără\s+imagine|lista\s+(?:de\s+)?produs)\b/u', $lower)) {
            return false;
        }

        return SectionAssistantQueryHelper::hasLiveDataAsk($message)
            && (
                SectionAssistantQueryHelper::hasNaturalLanguageNoise($message)
                || mb_strlen($message, 'UTF-8') > 18
            );
    }

    /**
     * @param array<string, mixed> $context
     * @return array{handler:string,normalized_message:string,client_name:?string,confidence:float,decoder:string}|null
     */
    private function decodeWithLlm(string $message, string $section, array $context): ?array
    {
        $system = <<<'PROMPT'
Ești decoder de intenții pentru Composer admin Besoiu Piese Auto.
Utilizatorul scrie natural în română (cu typo-uri). Tu alegi UN handler SQL live și reformulezi cererea.

Răspunde DOAR JSON valid:
{
  "handler": "client_list|client_orders|orders_summary|category_list|product_inventory|product_list|product_filter|supplier_list|supplier_compare|import_queue|import_readiness|comunicare_summary|invoice_list|awb_list|cart_list|vitrina_homepage|adaos_comercial|none",
  "normalized_message": "cerere scurtă standardizată în română",
  "client_name": null,
  "confidence": 0.0
}

Reguli:
- "ce clienti am eu / in sistem / pe proiect" → client_list, client_name null (NU extrage "am eu" ca nume!)
- "comenzi pentru Radu Galac" → client_orders, client_name "Radu Galac"
- "cate comenzi sunt" fără nume client → orders_summary
- "ce produse online / cate produse" → product_inventory (doar cifre)
- "lista tuturor produselor / arata toate produsele" → product_list
- "produse fara imagine / care nu au poza" → product_filter
- "e totul ok pentru import / sunt pregatit sa import" → import_readiness
- Dacă nu e clar → handler "none"
PROMPT;

        $payload = json_encode([
            'section' => $section,
            'user_message' => $message,
            'conversation_thread' => array_slice(
                is_array($context['conversation_thread'] ?? null) ? $context['conversation_thread'] : [],
                -6
            ),
        ], JSON_UNESCAPED_UNICODE);

        $result = $this->llm->complete($system, (string) $payload, 0.1, 10, 'section_assistant_intent');
        if (empty($result['ok'])) {
            return null;
        }

        $parsed = $this->parseJsonObject((string) ($result['content'] ?? ''));
        if ($parsed === null) {
            return null;
        }

        $handler = trim((string) ($parsed['handler'] ?? 'none'));
        if ($handler === '' || $handler === 'none') {
            return null;
        }

        $clientName = trim((string) ($parsed['client_name'] ?? ''));
        if ($clientName !== '' && SectionAssistantQueryHelper::isBoilerplateClientName($clientName)) {
            $clientName = '';
        }

        return [
            'handler' => $handler,
            'normalized_message' => trim((string) ($parsed['normalized_message'] ?? $message)) ?: $message,
            'client_name' => $clientName !== '' ? $clientName : null,
            'confidence' => (float) ($parsed['confidence'] ?? 0.7),
            'decoder' => 'llm',
        ];
    }

    /**
     * @param array{handler:string,normalized_message:string,client_name:?string,confidence:float,decoder:string} $decoded
     * @param array<string, mixed> $context
     * @return array<string, mixed>|null
     */
    private function dispatchDecoded(
        array $decoded,
        string $originalMessage,
        string $section,
        array $context,
        SectionAssistantModuleService $modules,
    ): ?array {
        $handler = trim((string) ($decoded['handler'] ?? ''));
        $normalized = trim((string) ($decoded['normalized_message'] ?? $originalMessage));
        if ($handler === '') {
            return null;
        }

        $queryMessage = $normalized;
        if ($handler === 'client_orders' && !empty($decoded['client_name'])) {
            $queryMessage = 'comenzi pentru ' . (string) $decoded['client_name'];
        }

        $plan = $modules->dispatchByHandlerKey($handler, $queryMessage, $section, $context);
        if ($plan === null) {
            return null;
        }

        $decoder = (string) ($decoded['decoder'] ?? 'rules');
        $plan['source'] = $decoder === 'llm' ? 'llm_intent+' . $handler : 'intent_router+' . $handler;
        $plan['intent_decoded'] = [
            'original' => $originalMessage,
            'normalized' => $normalized,
            'handler' => $handler,
            'client_name' => $decoded['client_name'] ?? null,
            'confidence' => $decoded['confidence'] ?? null,
            'decoder' => $decoder,
        ];

        if (is_array($plan['cheat_sheet'] ?? null)) {
            $hints = is_array($plan['cheat_sheet']['hints'] ?? null) ? $plan['cheat_sheet']['hints'] : [];
            array_unshift(
                $hints,
                'Inteles: «' . $originalMessage . '» → «' . $normalized . '» (' . $handler . ', ' . $decoder . ').'
            );
            $plan['cheat_sheet']['hints'] = $hints;
        }

        return $plan;
    }

    /** @return array<string, mixed>|null */
    private function parseJsonObject(string $raw): ?array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return $decoded;
        }
        if (preg_match('/\{[\s\S]*\}/', $raw, $m)) {
            $decoded = json_decode($m[0], true);

            return is_array($decoded) ? $decoded : null;
        }

        return null;
    }
}
