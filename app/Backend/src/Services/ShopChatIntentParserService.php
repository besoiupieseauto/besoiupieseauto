<?php

declare(strict_types=1);

namespace Besoiu\Services;

/**
 * Transformă mesaj liber (sau chip) în plan de căutare/acțiune pentru chat magazin.
 * AI → query structurat; fallback reguli locale.
 */
final class ShopChatIntentParserService
{
    /** @var array<string, array<string, mixed>> */
    private const CHIP_PLANS = [
        '30204a' => [
            'action' => 'search', 'intent' => 'code', 'oem_codes' => ['30204A'],
            'search_terms' => ['30204A'], 'label' => 'cod 30204A',
        ],
        'filtru ulei' => [
            'action' => 'search', 'intent' => 'filter', 'search_terms' => ['filtru ulei'],
            'exclude' => ['combustibil', 'carburant'], 'label' => 'filtru ulei',
        ],
        'filtru' => [
            'action' => 'search', 'intent' => 'filter', 'search_terms' => ['filtru'],
            'exclude' => [], 'label' => 'filtre auto',
        ],
        'placute frana' => [
            'action' => 'search', 'intent' => 'brake', 'search_terms' => ['placute frana'],
            'label' => 'plăcuțe frână',
        ],
        'vreau sa comand' => ['action' => 'order', 'label' => 'comandă'],
        'whatsapp' => ['action' => 'whatsapp', 'label' => 'WhatsApp'],
        'status comanda mea' => ['action' => 'order_status', 'label' => 'status comandă'],
        'salveaza conversatia' => ['action' => 'consent', 'label' => 'GDPR'],
        'engine' => [
            'action' => 'search',
            'intent' => 'engine',
            'search_terms' => ['ulei motor', 'filtru ulei', 'piston', 'distributie'],
            'exclude' => ['combustibil', 'suspensie', 'roata', 'pivot', 'motoras'],
            'label' => 'piese de motor',
            'confidence' => 1.0,
        ],
        'ulei motor' => [
            'action' => 'search',
            'intent' => 'oil',
            'search_terms' => ['ulei motor', 'ulei', 'lubrifiant'],
            'exclude' => ['combustibil', 'filtru combustibil'],
            'label' => 'ulei motor',
            'confidence' => 1.0,
        ],
    ];

    public static function create(): self
    {
        return new self();
    }

    /**
     * @param list<array{role:string,content:string}> $history
     * @param array<string, mixed> $sessionState
     * @return array<string, mixed>
     */
    public function parse(string $message, array $history = [], array $sessionState = [], ?string $chipId = null): array
    {
        $message = trim($message);
        if ($message === '' && ($chipId === null || trim($chipId) === '')) {
            return $this->plan(['action' => 'empty', 'label' => ''], 'rules');
        }

        if ($chipId !== null && trim($chipId) !== '') {
            $chip = $this->matchChip(trim($chipId));
            if ($chip !== null) {
                return $this->plan($chip, 'chip');
            }
        }

        $chip = $this->matchChip($message);
        if ($chip !== null) {
            return $this->plan($chip, 'chip');
        }

        require_once dirname(__DIR__, 3) . '/robot/widget_catalog_search.php';

        $decoder = ShopChatMessageDecoderService::create();

        $refinementService = new ShopChatRefinementService();
        if ($refinementService->isRefinementMessage($message, $sessionState)) {
            return $this->plan([
                'action' => 'refine',
                'intent' => 'refine',
                'label' => 'rafinare',
                'confidence' => 0.93,
            ], 'refine');
        }

        if (widget_catalog_is_new_search_message($message)) {
            $rules = $this->parseWithRules($message, $sessionState);
            if ($decoder->isMessyInput($message) || ($rules['confidence'] ?? 0) < 0.88) {
                $aiPlan = $decoder->decodeWithAi($message, $history, $sessionState);
                if ($aiPlan !== null) {
                    $aiPlan = $this->sanitizeAiPlan($aiPlan, $sessionState);
                    return $this->plan($aiPlan, 'ai');
                }
                $decoded = $decoder->decodeWithRules($message);

                return $this->plan($decoded, 'rules_decode');
            }

            return $this->plan($rules, 'rules');
        }

        $contextual = $this->resolveContextualMessage($message, $sessionState);
        if ($contextual !== null) {
            return $this->plan($contextual, 'context');
        }

        $rules = $this->parseWithRules($message, $sessionState);
        $fastActions = ['order', 'whatsapp', 'order_status', 'consent', 'greeting'];
        if (in_array((string) ($rules['action'] ?? ''), $fastActions, true) && ($rules['confidence'] ?? 0) >= 0.88) {
            return $this->plan($rules, 'rules');
        }
        if (($rules['action'] ?? '') === 'general') {
            return $this->plan($rules, 'rules');
        }

        if ($decoder->isMessyInput($message) || ($rules['confidence'] ?? 0) < 0.85) {
            $aiPlan = $decoder->decodeWithAi($message, $history, $sessionState);
            if ($aiPlan !== null) {
                require_once dirname(__DIR__, 3) . '/robot/widget_catalog_search.php';
                $aiPlan = $this->sanitizeAiPlan($aiPlan, $sessionState);
                if (($aiPlan['action'] ?? '') === 'clarify' && widget_catalog_is_new_search_message($message)) {
                    $aiPlan['action'] = 'search';
                }

                return $this->plan($aiPlan, 'ai');
            }
            $decoded = $decoder->decodeWithRules($message);
            if (($decoded['confidence'] ?? 0) >= ($rules['confidence'] ?? 0)) {
                return $this->plan($decoded, 'rules_decode');
            }
        }

        if (($rules['confidence'] ?? 0) >= 0.85) {
            return $this->plan($rules, 'rules');
        }

        $ai = $decoder->decodeWithAi($message, $history, $sessionState);
        if ($ai !== null) {
            require_once dirname(__DIR__, 3) . '/robot/widget_catalog_search.php';
            $ai = $this->sanitizeAiPlan($ai, $sessionState);
            if (($ai['action'] ?? '') === 'clarify' && widget_catalog_is_new_search_message($message)) {
                return $this->plan($this->parseWithRules($message, $sessionState), 'rules');
            }

            return $this->plan($ai, 'ai');
        }

        return $this->plan($rules, 'rules_fallback');
    }

    /** @return list<array{id:string,label:string}> */
    public static function defaultChips(): array
    {
        return self::chipsFromCatalog([]);
    }

    /**
     * Chip-uri din stoc real — nu promite „filtru ulei” dacă nu există în catalog.
     *
     * @param list<array<string, mixed>> $products
     * @return list<array{id:string,label:string}>
     */
    public static function chipsFromCatalog(array $products): array
    {
        $chips = [];
        $hasOilFilter = false;
        $hasAnyFilter = false;
        $sampleCode = null;

        foreach ($products as $p) {
            if (!is_array($p) || (int) ($p['stock'] ?? 0) <= 0) {
                continue;
            }
            $name = mb_strtolower((string) ($p['name'] ?? ''), 'UTF-8');
            $code = (string) ($p['code'] ?? '');
            if (preg_match('/\bfiltru\b/u', $name)) {
                $hasAnyFilter = true;
                if (str_contains($name, 'ulei')) {
                    $hasOilFilter = true;
                }
            }
            if ($sampleCode === null && $code !== '' && preg_match('/^[A-Z0-9]{4,8}$/i', $code)) {
                $sampleCode = strtoupper($code);
            }
        }

        if ($sampleCode !== null) {
            $chips[] = ['id' => mb_strtolower($sampleCode, 'UTF-8'), 'label' => $sampleCode];
        }
        $chips[] = ['id' => 'engine', 'label' => 'Piese motor'];
        if ($hasOilFilter) {
            $chips[] = ['id' => 'filtru ulei', 'label' => 'Filtru ulei'];
        } else        if ($hasAnyFilter) {
            $chips[] = ['id' => 'filtru', 'label' => 'Filtre auto'];
        }
        $hasOil = false;
        foreach ($products as $p) {
            if (!is_array($p) || (int) ($p['stock'] ?? 0) <= 0) {
                continue;
            }
            $n = mb_strtolower((string) ($p['name'] ?? ''), 'UTF-8');
            if ((str_contains($n, 'ulei') || str_contains($n, 'lubrif')) && !str_contains($n, 'combustibil')) {
                $hasOil = true;
                break;
            }
        }
        if ($hasOil) {
            $chips[] = ['id' => 'ulei motor', 'label' => 'Ulei motor'];
        }
        $chips[] = ['id' => 'vreau sa comand', 'label' => 'Vreau să comand'];
        $chips[] = ['id' => 'whatsapp', 'label' => 'WhatsApp'];

        return array_slice($chips, 0, 5);
    }

    /** @param array<string, mixed> $data */
    private function plan(array $data, string $source): array
    {
        return array_merge([
            'action' => 'search',
            'intent' => 'general',
            'search_terms' => [],
            'exclude' => [],
            'oem_codes' => [],
            'label' => 'căutare',
            'user_meaning' => '',
            'confidence' => 0.5,
            'source' => $source,
        ], $data);
    }

    /** @return array<string, mixed>|null */
    private function matchChip(string $message): ?array
    {
        $key = mb_strtolower(trim($message), 'UTF-8');
        if (isset(self::CHIP_PLANS[$key])) {
            return self::CHIP_PLANS[$key];
        }

        if ($key === 'piese motor' || $key === 'motor') {
            return [
                'action' => 'search',
                'intent' => 'engine',
                'search_terms' => ['ulei motor', 'filtru ulei', 'piston', 'distributie'],
                'exclude' => ['combustibil', 'suspensie', 'roata', 'pivot', 'motoras'],
                'label' => 'piese de motor',
                'confidence' => 1.0,
            ];
        }

        return null;
    }

    /**
     * Referințe scurte la ultima căutare: „da”, „pe ala”, „altul”.
     *
     * @param array<string, mixed> $sessionState
     * @return array<string, mixed>|null
     */
    private function resolveContextualMessage(string $message, array $sessionState): ?array
    {
        $lower = mb_strtolower(trim($message), 'UTF-8');
        if ($lower === '') {
            return null;
        }

        require_once dirname(__DIR__, 3) . '/robot/widget_catalog_search.php';
        if (widget_catalog_is_new_search_message($message)) {
            return null;
        }

        $last = is_array($sessionState['last_products'] ?? null) ? $sessionState['last_products'] : [];
        $inStock = array_values(array_filter($last, static fn ($p) => is_array($p) && !empty($p['in_stock'])));

        if (preg_match('/\b(vreau\s+sa\s+comand|vreau\s+să\s+comand|comand|comanda)\b/u', $lower) && $inStock !== []) {
            return ['action' => 'order', 'label' => 'comandă', 'confidence' => 0.95];
        }

        if (preg_match('/\b(da|ok|confirm|perfect|merge|aleg|pe\s+(ala|ăla|primul|prima|asta|aceasta|el|ea))\b/u', $lower)) {
            if ($inStock === []) {
                return null;
            }
            $p = $inStock[0];
            $code = (string) ($p['code'] ?? '');
            return [
                'action' => 'order',
                'label' => 'comandă',
                'confidence' => 0.92,
            ];
        }

        if (preg_match('/\b(altul|altceva|alta\s+varianta|gata\s+cu\s+asta|nu\s+pe\s+asta)\b/u', $lower) && count($inStock) > 1) {
            return [
                'action' => 'clarify',
                'intent' => 'general',
                'search_terms' => [],
                'label' => 'alt produs',
                'user_meaning' => 'Clientul vrea alt produs din lista anterioară.',
                'confidence' => 0.88,
            ];
        }

        return null;
    }

    /**
     * @param array<string, mixed> $sessionState
     * @return array<string, mixed>
     */
    private function parseWithRules(string $message, array $sessionState): array
    {
        require_once dirname(__DIR__, 3) . '/robot/public_shop_chat.php';

        $intent = widget_catalog_resolve_intent($message);
        $label = widget_catalog_query_label($message);
        $terms = widget_catalog_extract_terms($message);
        $exclude = [];

        if ($intent === 'oil') {
            $terms = ['ulei motor', 'ulei', 'lubrifiant'];
            $exclude = ['combustibil', 'filtru combustibil', 'carburant'];
            $label = 'ulei motor';

            return [
                'action' => 'search',
                'intent' => 'oil',
                'search_terms' => $terms,
                'exclude' => $exclude,
                'oem_codes' => [],
                'label' => $label,
                'user_meaning' => $message,
                'confidence' => 0.92,
            ];
        }

        if ($intent === 'engine') {
            $terms = ['ulei motor', 'filtru ulei', 'piston', 'distributie', 'segment'];
            $exclude = ['combustibil', 'suspensie', 'roata', 'pivot', 'motoras'];
        } elseif ($intent === 'filter' || preg_match('/\b(filtru|filtre)\b/ui', $message)) {
            $intent = 'filter';
            if (!preg_match('/\b(combustibil|carburant)\b/ui', $message)) {
                $exclude[] = 'combustibil';
                $exclude[] = 'carburant';
            }
            if ($terms === [] || $terms === ['filtru']) {
                $terms = ['filtru ulei', 'filtru aer', 'filtru'];
            }
        }

        if (public_shop_chat_detect_order_intent($message)) {
            return ['action' => 'order', 'label' => 'comandă', 'confidence' => 0.95];
        }
        if (preg_match('/\b(whatsapp|sunati|suna)\b/ui', $message)) {
            return ['action' => 'whatsapp', 'label' => 'WhatsApp', 'confidence' => 0.95];
        }
        if (preg_match('/\b(status|unde\s+e|comanda\s+mea)\b/ui', $message)) {
            return ['action' => 'order_status', 'label' => 'status comandă', 'confidence' => 0.9];
        }
        if (preg_match('/\b(salveaza\s+conversat|accept\s+gdpr)\b/ui', $message)) {
            return ['action' => 'consent', 'label' => 'GDPR', 'confidence' => 0.9];
        }
        if (preg_match('/^(salut|buna|bună|hey)\b/ui', trim($message)) && mb_strlen($message) < 40) {
            return ['action' => 'greeting', 'label' => 'salut', 'confidence' => 0.88];
        }

        if (!public_shop_chat_looks_like_product_query($message) && mb_strlen($message) > 3) {
            if (preg_match('/\b(multumesc|mulțumesc|mersi|ok\s+multumesc|ce\s+faci|buna\s+seara)\b/ui', $message)) {
                return ['action' => 'general', 'label' => 'conversație', 'confidence' => 0.3];
            }
            if (!preg_match('/\b(piesa|piese|produs|cod|oem|vin|stoc|pret|preț|filtru|motor|rulment)\b/ui', $message)) {
                return ['action' => 'general', 'label' => 'conversație', 'confidence' => 0.35];
            }
        }

        $codes = function_exists('fb_extract_oem_codes') ? fb_extract_oem_codes($message) : [];

        return [
            'action' => 'search',
            'intent' => $intent,
            'search_terms' => $terms,
            'exclude' => $exclude,
            'oem_codes' => $codes,
            'label' => $label,
            'user_meaning' => $message,
            'confidence' => $terms !== [] || $codes !== [] ? 0.75 : 0.4,
        ];
    }

    /**
     * Corectează plan AI înainte de query catalog (ex. refine fără listă anterioară).
     *
     * @param array<string, mixed> $plan
     * @param array<string, mixed> $sessionState
     * @return array<string, mixed>
     */
    private function sanitizeAiPlan(array $plan, array $sessionState): array
    {
        $last = is_array($sessionState['last_products'] ?? null) ? $sessionState['last_products'] : [];
        if (($plan['action'] ?? '') === 'refine' && count($last) < 2) {
            $plan['action'] = 'search';
        }

        $blob = mb_strtolower(
            implode(' ', is_array($plan['search_terms'] ?? null) ? $plan['search_terms'] : [])
            . ' ' . ($plan['label'] ?? '') . ' ' . ($plan['user_meaning'] ?? '') . ' ' . ($plan['category'] ?? ''),
            'UTF-8'
        );
        if (preg_match('/\b(frana|frână|frane|disc|placute|plăcuțe|tambur)\b/u', $blob)) {
            $plan['intent'] = 'brake';
            if (empty($plan['search_terms'])) {
                $plan['search_terms'] = ['disc frana', 'placute frana', 'tambur'];
            }
        }

        return $plan;
    }
}
