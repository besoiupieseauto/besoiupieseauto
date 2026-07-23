<?php

declare(strict_types=1);

namespace Besoiu\Services;

/**
 * Decoder mesaj liber (haos) → plan structurat catalog + răspuns verificat.
 *
 * Flux: input omul → AI interpretează → query BD → output aliniat la intenție.
 */
final class ShopChatMessageDecoderService
{
    public static function create(): self
    {
        return new self();
    }

    /**
     * Mesaj „haotic”: propoziții lungi, greșeli, amestec categorie+vehicul+preț fără structură clară.
     */
    public function isMessyInput(string $message): bool
    {
        $message = trim($message);
        if ($message === '') {
            return false;
        }

        if (mb_strlen($message) > 45) {
            return true;
        }

        if (preg_match('/[,;].+[,;]/u', $message)) {
            return true;
        }

        $signals = 0;
        foreach ([
            '/\b(frana|frână|filtru|ulei|disc|placute|plăcuțe|rulment|bosch|fag|pret|preț|sub|peste)\b/ui',
            '/\b(golf|bmw|audi|ford|dacia|mercedes|renault|peugeot|motor|model)\b/ui',
            '/\b(vreau|as\s+vrea|caut|am\s+nevoie|nu\s+scump|ieftin)\b/ui',
        ] as $rx) {
            if (preg_match($rx, $message)) {
                ++$signals;
            }
        }

        return $signals >= 2;
    }

    /**
     * @param list<array{role:string,content:string}> $history
     * @param array<string, mixed> $sessionState
     * @return array<string, mixed>|null Plan catalog sau null dacă LLM indisponibil
     */
    public function decodeWithAi(string $message, array $history = [], array $sessionState = []): ?array
    {
        $autoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
        if (!is_file($autoload)) {
            return null;
        }
        require_once $autoload;

        $lastProducts = is_array($sessionState['last_products'] ?? null) ? $sessionState['last_products'] : [];
        $vehicle = ShopChatSessionService::getVehicle($sessionState);
        $contextLines = [];
        if ($lastProducts !== []) {
            $contextLines[] = 'Produse recente: ' . implode('; ', array_slice(array_map(
                static fn ($p) => (string) ($p['name'] ?? ''),
                $lastProducts
            ), 0, 3));
        }
        if ($vehicle !== null) {
            $contextLines[] = 'Autoturism setat: ' . (string) ($vehicle['label'] ?? '');
        }
        $context = implode("\n", $contextLines);

        $system = <<<'PROMPT'
Ești decoder pentru chat magazin piese auto (România). Clientul scrie liber, greșit, incomplet.
Tu INTERPRETEZI ce vrea și produci JSON pentru căutare în baza de date — NU răspuns conversațional.

Răspunde DOAR JSON valid (fără markdown):
{
  "action": "search|order|whatsapp|order_status|greeting|consent|clarify|refine",
  "intent": "engine|filter|brake|suspension|code|category|oil|general|refine",
  "search_terms": ["max 6 termeni scurți pentru SQL/catalog: categorie, subcategorie, nume piesă"],
  "exclude": ["combustibil, suspensie, roata..."],
  "oem_codes": ["coduri alfanumerice dacă există"],
  "brand_piece": "BOSCH|FAG|TRW|... sau gol",
  "category": "Frane|Filtre|Ulei|Suspensie|... sau gol",
  "subcategory": "disc frana|filtru ulei|... sau gol",
  "vehicle_hint": "marca model motorizare sau gol",
  "price_max": null,
  "price_min": null,
  "label": "etichetă scurtă RO pentru UI",
  "user_meaning": "O singură propoziție clară: ce a vrut clientul (verificare output)",
  "confidence": 0.0-1.0
}

Reguli decode:
- Corectează greșeli: "frana"→frână, "ulei motor"→ulei, "filtru ulei"→filtru ulei.
- Mesaj haos "ceva de frana golf bosch nu scump" → intent brake, brand BOSCH, vehicle_hint Golf, price_max estimat sau refine.
- NU pune propoziția întreagă în search_terms — doar cuvinte cheie catalog.
- "motor"/"piese motor" = ulei, filtru ulei, piston — exclude combustibil, rulment roată.
- "dar aveti ulei" / "aveti X" = căutare NOUĂ (search), NU clarify.
- "mai ieftin"/"sub 100" cu context produse = action refine.
- "vreau sa comand" = order.
PROMPT;

        try {
            $knowledge = ShopChatKnowledgeService::create();
            $rag = $knowledge->buildRagSnippetForMessage($message, 'external');
            if ($rag !== '') {
                $system .= $rag;
            }
        } catch (\Throwable) {
            // RAG opțional — decoder funcționează și fără BD knowledge
        }

        $user = $context !== '' ? $context . "\n\nMesaj client (liber): " . $message : 'Mesaj client (liber): ' . $message;

        $messages = [];
        foreach (array_slice($history, -4) as $h) {
            if (!is_array($h)) {
                continue;
            }
            $messages[] = ['role' => (string) ($h['role'] ?? 'user'), 'content' => (string) ($h['content'] ?? '')];
        }
        $messages[] = ['role' => 'user', 'content' => $user];

        try {
            $llm = ChatLlmService::create(dirname(__DIR__, 2));
            $result = $llm->chat($messages, $system, 0.05, 14, 'chat_decode');
            if (empty($result['ok'])) {
                return null;
            }
            $parsed = $this->decodeJson((string) ($result['content'] ?? ''));
            if ($parsed === null) {
                return null;
            }

            return $this->normalizeDecodedPlan($parsed, $message);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Fallback local când LLM lipsește — tot produce user_meaning + termeni.
     *
     * @return array<string, mixed>
     */
    public function decodeWithRules(string $message): array
    {
        require_once dirname(__DIR__, 3) . '/robot/widget_catalog_search.php';
        require_once dirname(__DIR__, 3) . '/robot/public_shop_chat.php';

        $intent = widget_catalog_resolve_intent($message);
        $label = widget_catalog_query_label($message);
        $terms = widget_catalog_extract_terms($message);
        $exclude = [];
        $brand = '';
        $priceMax = null;
        $priceMin = null;

        if (preg_match('/\b(sub|pana\s+la|până\s+la|max)\s*(\d+)/ui', $message, $m)) {
            $priceMax = (float) $m[2];
        }
        if (preg_match('/\b(peste|min)\s*(\d+)/ui', $message, $m)) {
            $priceMin = (float) $m[2];
        }
        foreach (['bosch', 'fag', 'trw', 'febi', 'ridex'] as $b) {
            if (stripos($message, $b) !== false) {
                $brand = strtoupper($b);
                break;
            }
        }

        if (preg_match('/\b(frana|frână|frane|disc|placute|plăcuțe|tambur)\b/ui', $message)) {
            $intent = 'brake';
            $terms = ['disc frana', 'placute frana', 'tambur frana'];
            $label = 'produse frână';
        } elseif ($intent === 'engine' && preg_match('/\b(frana|frână|disc|placute|plăcuțe|tambur)\b/ui', $message)) {
            $intent = 'brake';
            $terms = ['disc frana', 'placute frana', 'tambur frana'];
            $label = 'produse frână';
        }

        if ($intent === 'oil') {
            $terms = ['ulei motor', 'ulei', 'lubrifiant'];
            $exclude = ['combustibil', 'filtru combustibil'];
            $label = 'ulei motor';
        }

        $vehicleSvc = new ShopChatVehicleService();
        $vehicle = $vehicleSvc->parseFromMessage($message);
        $meaning = $this->buildMeaningFromParts($label, $brand, $vehicle['label'] ?? '', $priceMax, $priceMin, $message);

        return [
            'action' => 'search',
            'intent' => $intent,
            'search_terms' => $terms,
            'exclude' => $exclude,
            'oem_codes' => function_exists('fb_extract_oem_codes') ? fb_extract_oem_codes($message) : [],
            'brand_piece' => $brand,
            'category' => '',
            'subcategory' => '',
            'vehicle_hint' => (string) ($vehicle['label'] ?? ''),
            'price_max' => $priceMax,
            'price_min' => $priceMin,
            'label' => $label,
            'user_meaning' => $meaning,
            'confidence' => $terms !== [] ? 0.72 : 0.45,
            'source' => 'rules_decode',
        ];
    }

    /**
     * Preamble verificat — arată clientului ce a înțeles sistemul.
     *
     * @param array<string, mixed> $plan
     */
    public function buildVerifiedPreamble(array $plan, string $originalMessage): string
    {
        $meaning = trim((string) ($plan['user_meaning'] ?? ''));
        if ($meaning === '') {
            $meaning = trim((string) ($plan['label'] ?? ''));
        }
        if ($meaning === '') {
            return '';
        }

        $parts = ['Am înțeles: «' . $meaning . '».'];
        $terms = is_array($plan['search_terms'] ?? null) ? array_filter($plan['search_terms']) : [];
        if ($terms !== [] && ($plan['source'] ?? '') === 'ai') {
            $parts[] = 'Caut în stoc: ' . implode(', ', array_slice($terms, 0, 4)) . '.';
        }

        return implode("\n", $parts);
    }

    /** @param array<string, mixed> $parsed */
    private function normalizeDecodedPlan(array $parsed, string $originalMessage): array
    {
        $terms = is_array($parsed['search_terms'] ?? null)
            ? array_values(array_filter(array_map('strval', $parsed['search_terms'])))
            : [];

        $brand = strtoupper(trim((string) ($parsed['brand_piece'] ?? '')));
        if ($brand !== '' && !in_array(mb_strtolower($brand, 'UTF-8'), array_map('mb_strtolower', $terms), true)) {
            $terms[] = mb_strtolower($brand, 'UTF-8');
        }

        $category = trim((string) ($parsed['category'] ?? ''));
        $sub = trim((string) ($parsed['subcategory'] ?? ''));
        if ($sub !== '') {
            $terms[] = $sub;
        } elseif ($category !== '') {
            $terms[] = $category;
        }

        return [
            'action' => $this->normalizeAction((string) ($parsed['action'] ?? 'search'), $parsed),
            'intent' => (string) ($parsed['intent'] ?? 'general'),
            'search_terms' => array_values(array_unique(array_slice($terms, 0, 8))),
            'exclude' => is_array($parsed['exclude'] ?? null) ? array_values(array_filter(array_map('strval', $parsed['exclude']))) : [],
            'oem_codes' => is_array($parsed['oem_codes'] ?? null) ? array_values(array_filter(array_map('strval', $parsed['oem_codes']))) : [],
            'brand_piece' => $brand,
            'category' => $category,
            'subcategory' => $sub,
            'vehicle_hint' => trim((string) ($parsed['vehicle_hint'] ?? '')),
            'price_max' => isset($parsed['price_max']) && $parsed['price_max'] !== null ? (float) $parsed['price_max'] : null,
            'price_min' => isset($parsed['price_min']) && $parsed['price_min'] !== null ? (float) $parsed['price_min'] : null,
            'label' => (string) ($parsed['label'] ?? 'căutare'),
            'user_meaning' => (string) ($parsed['user_meaning'] ?? $originalMessage),
            'confidence' => (float) ($parsed['confidence'] ?? 0.88),
            'source' => 'ai',
        ];
    }

    /** @return array<string, mixed>|null */
    private function decodeJson(string $content): ?array
    {
        $content = trim($content);
        if ($content === '') {
            return null;
        }
        if (preg_match('/\{[\s\S]*\}/', $content, $m)) {
            $content = $m[0];
        }
        $decoded = json_decode($content, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function normalizeAction(string $action, array $parsed): string
    {
        if ($action === 'refine') {
            return 'search';
        }

        return $action !== '' ? $action : 'search';
    }

    private function buildMeaningFromParts(
        string $label,
        string $brand,
        string $vehicle,
        ?float $priceMax,
        ?float $priceMin,
        string $fallback
    ): string {
        $bits = [];
        if ($label !== '' && $label !== 'căutare') {
            $bits[] = $label;
        }
        if ($brand !== '') {
            $bits[] = 'brand ' . $brand;
        }
        if ($vehicle !== '') {
            $bits[] = 'pentru ' . $vehicle;
        }
        if ($priceMax !== null) {
            $bits[] = 'sub ' . (int) $priceMax . ' RON';
        }
        if ($priceMin !== null) {
            $bits[] = 'peste ' . (int) $priceMin . ' RON';
        }

        return $bits !== [] ? implode(', ', $bits) : mb_substr(trim($fallback), 0, 120);
    }
}
