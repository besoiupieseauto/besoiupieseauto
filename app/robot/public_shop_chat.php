<?php

declare(strict_types=1);

/**
 * Chat magazin public — căutare stoc deterministă + flux comandă din widget.
 */

require_once __DIR__ . '/widget_catalog_search.php';

/** @return list<array{id:string,label:string}> */
function widget_chat_default_chips(array $products): array
{
    if (class_exists(\Besoiu\Services\ShopChatIntentParserService::class)) {
        return \Besoiu\Services\ShopChatIntentParserService::chipsFromCatalog($products);
    }

    return [
        ['id' => 'engine', 'label' => 'Piese motor'],
        ['id' => 'filtru', 'label' => 'Filtre auto'],
        ['id' => 'vreau sa comand', 'label' => 'Vreau să comand'],
        ['id' => 'whatsapp', 'label' => 'WhatsApp'],
    ];
}

/**
 * @param array<string, mixed> $shopHandled
 * @param array<string, mixed>|null $intentPlan
 * @return array<string, mixed>
 */
function widget_chat_search_meta(array $shopHandled, ?array $intentPlan, string $message): array
{
    if (is_array($shopHandled['search_meta'] ?? null)) {
        return $shopHandled['search_meta'];
    }
    if ($intentPlan !== null) {
        return public_shop_chat_plan_search_meta($intentPlan);
    }

    return widget_catalog_search_meta($message);
}

/** @param list<array<string,mixed>> $products @param array<string,mixed> $plan @return list<array<string,mixed>> */
function public_shop_chat_apply_plan_filters(array $products, array $plan): array
{
    $out = $products;
    $priceMax = isset($plan['price_max']) && $plan['price_max'] !== null ? (float) $plan['price_max'] : null;
    $priceMin = isset($plan['price_min']) && $plan['price_min'] !== null ? (float) $plan['price_min'] : null;
    $brand = strtoupper(trim((string) ($plan['brand_piece'] ?? '')));

    if ($priceMax !== null) {
        $out = array_values(array_filter($out, static function ($p) use ($priceMax) {
            $raw = (string) ($p['price'] ?? '');
            $num = (float) str_replace(',', '.', preg_replace('/[^\d.,]/', '', $raw) ?? '');

            return $num <= $priceMax;
        }));
    }
    if ($priceMin !== null) {
        $out = array_values(array_filter($out, static function ($p) use ($priceMin) {
            $raw = (string) ($p['price'] ?? '');
            $num = (float) str_replace(',', '.', preg_replace('/[^\d.,]/', '', $raw) ?? '');

            return $num >= $priceMin;
        }));
    }
    if ($brand !== '') {
        $out = array_values(array_filter($out, static function ($p) use ($brand) {
            $name = strtoupper((string) ($p['name'] ?? ''));

            return str_contains($name, $brand);
        }));
    }

    return $out !== [] ? $out : $products;
}

/** @param array<string,mixed> $plan */
function public_shop_chat_verified_reply_prefix(array $plan, string $originalMessage): string
{
    if (!class_exists(\Besoiu\Services\ShopChatMessageDecoderService::class)) {
        return '';
    }

    return \Besoiu\Services\ShopChatMessageDecoderService::create()->buildVerifiedPreamble($plan, $originalMessage);
}

/** @param array<string, mixed> $shopHandled */
function widget_chat_intent_decoded(array $shopHandled, ?array $intentPlan, string $message): array
{
    if (!is_array($intentPlan)) {
        return ['user_meaning' => '', 'source' => ''];
    }

    return [
        'user_meaning' => (string) ($intentPlan['user_meaning'] ?? $message),
        'label' => (string) ($intentPlan['label'] ?? ''),
        'intent' => (string) ($intentPlan['intent'] ?? ''),
        'action' => (string) ($intentPlan['action'] ?? ''),
        'search_terms' => is_array($intentPlan['search_terms'] ?? null) ? $intentPlan['search_terms'] : [],
        'source' => (string) ($intentPlan['source'] ?? ''),
        'confidence' => (float) ($intentPlan['confidence'] ?? 0),
    ];
}

/** @param array<string, mixed> $shopHandled */
function widget_chat_response_payload(
    array $shopHandled,
    string $sessionId,
    string $catalogSource,
    array $sessionState,
    ?array $intentPlan,
    string $message,
    string $csrfToken = '',
    array $catalogProducts = []
): array {
    $chips = is_array($shopHandled['chips'] ?? null) && $shopHandled['chips'] !== []
        ? $shopHandled['chips']
        : widget_chat_default_chips($catalogProducts);

    return array_merge([
        'reply' => (string) ($shopHandled['reply'] ?? ''),
        'session_id' => $sessionId,
        'sources' => is_array($shopHandled['sources'] ?? null) ? $shopHandled['sources'] : [],
        'products' => is_array($shopHandled['products'] ?? null) ? $shopHandled['products'] : [],
        'cart' => is_array($shopHandled['cart'] ?? null) ? $shopHandled['cart'] : ($sessionState['cart'] ?? []),
        'ui_action' => (string) ($shopHandled['ui_action'] ?? 'none'),
        'handled_by' => (string) ($shopHandled['handled_by'] ?? 'catalog'),
        'availability' => (string) ($shopHandled['availability'] ?? ''),
        'catalog_source' => $catalogSource,
        'orders_endpoint' => '/admin/api/comenzi_endpoint.php',
        'csrf_token' => $csrfToken,
        'price_tiers' => is_array($shopHandled['price_tiers'] ?? null) ? $shopHandled['price_tiers'] : [],
        'chips' => $chips,
        'profile' => is_array($shopHandled['profile'] ?? null) ? $shopHandled['profile'] : null,
        'whatsapp_url' => (string) ($shopHandled['whatsapp_url'] ?? ''),
        'upsell' => is_array($shopHandled['upsell'] ?? null) ? $shopHandled['upsell'] : [],
        'vehicle' => (string) ($shopHandled['vehicle'] ?? ''),
        'tone' => (string) ($shopHandled['tone'] ?? ''),
        'search_meta' => widget_chat_search_meta($shopHandled, $intentPlan, $message),
        'onboarding_step' => (string) ($sessionState['onboarding_step'] ?? ''),
        'vehicle' => is_array($shopHandled['vehicle'] ?? null) ? $shopHandled['vehicle'] : ($sessionState['vehicle'] ?? null),
        'quiz_step' => (string) ($shopHandled['quiz_step'] ?? ''),
        'quiz_options' => is_array($shopHandled['quiz_options'] ?? null) ? $shopHandled['quiz_options'] : [],
        'form_prefill' => is_array($shopHandled['form_prefill'] ?? null) ? $shopHandled['form_prefill'] : null,
        'intent_decoded' => widget_chat_intent_decoded($shopHandled, $intentPlan, $message),
    ]);
}

/** @return array{history:list<array{role:string,content:string}>,state:array<string,mixed>} */
function public_shop_chat_load_session(string $sessFile): array
{
    $baseState = class_exists(\Besoiu\Services\ShopChatSessionService::class)
        ? \Besoiu\Services\ShopChatSessionService::defaultState()
        : ['last_products' => [], 'cart' => [], 'onboarding_step' => 'welcome', 'client_name' => ''];
    $default = [
        'history' => [],
        'state' => $baseState,
    ];

    if (!is_file($sessFile)) {
        return $default;
    }

    $raw = json_decode((string) file_get_contents($sessFile), true);
    if (!is_array($raw)) {
        return $default;
    }

    if (isset($raw[0]['role']) || isset($raw[0]['content'])) {
        return [
            'history' => $raw,
            'state' => $default['state'],
        ];
    }

    $baseState = class_exists(\Besoiu\Services\ShopChatSessionService::class)
        ? \Besoiu\Services\ShopChatSessionService::defaultState()
        : ['last_products' => [], 'cart' => [], 'onboarding_step' => 'welcome', 'client_name' => ''];

    return [
        'history' => is_array($raw['history'] ?? null) ? $raw['history'] : [],
        'state' => is_array($raw['state'] ?? null)
            ? array_merge($baseState, $raw['state'])
            : $baseState,
    ];
}

/** @param array{history:list<array{role:string,content:string}>,state:array<string,mixed>} $session */
function public_shop_chat_save_session(string $sessFile, array $session): void
{
    $history = $session['history'] ?? [];
    if (count($history) > 20) {
        $history = array_slice($history, -20);
    }
    $session['history'] = $history;
    file_put_contents($sessFile, json_encode($session, JSON_UNESCAPED_UNICODE));
}

function public_shop_chat_detect_order_intent(string $message): bool
{
    $lower = mb_strtolower(trim($message), 'UTF-8');

    return (bool) preg_match(
        '/\b(vreau\s+(sa\s+|să\s+)?comand|as\s+vrea\s+sa\s+comand|plasez\s+comand|fac\s+comand|confirm\s+comand|trimite\s+comand|comand\s+acum|il\s+comand|o\s+comand)\b/u',
        $lower
    );
}

function public_shop_chat_detect_greeting_only(string $message): bool
{
    $lower = mb_strtolower(trim($message), 'UTF-8');

    return (bool) preg_match(
        '/^(salut|bun[aă]|hey|hello|hi|servus|noroc|buna\s+ziua|multumesc|mulțumesc|mersi)[\s!.,?]*$/u',
        $lower
    );
}

function public_shop_chat_looks_like_product_query(string $message): bool
{
    if (public_shop_chat_detect_order_intent($message) || public_shop_chat_detect_greeting_only($message)) {
        return false;
    }

    $lower = mb_strtolower(trim($message), 'UTF-8');
    if ($lower === '') {
        return false;
    }

    if (fb_extract_oem_codes($message) !== []) {
        return true;
    }

    if (preg_match('/\b(vin\s*[:\s]?[A-HJ-NPR-Z0-9]{17})\b/i', $message)) {
        return true;
    }

    return (bool) preg_match(
        '/\b(rulment|filtru|disc|placute|plăcuțe|ambreiaj|baterie|ulei|piesa|piese|produse|motor|stoc|pret|preț|aveti|aveți|ai\s+stoc|caut|compatibil|oem|cod\s+articol|catalog)\b/u',
        $lower
    );
}

/** @param list<array<string,mixed>> $hits */
function public_shop_chat_map_products(array $hits): array
{
    $out = [];
    foreach ($hits as $p) {
        if (!is_array($p)) {
            continue;
        }
        $stock = (int) ($p['stock'] ?? 0);
        $priceRaw = (string) ($p['price'] ?? '');
        $priceNum = (float) str_replace(',', '.', preg_replace('/[^\d.,]/', '', $priceRaw) ?? '');
        $out[] = [
            'randomn_id' => (string) ($p['randomn_id'] ?? ''),
            'name' => (string) ($p['name'] ?? ''),
            'code' => (string) ($p['code'] ?? ''),
            'oem' => (string) ($p['oem'] ?? ''),
            'price' => $priceRaw,
            'price_num' => $priceNum,
            'stock' => $stock,
            'in_stock' => $stock > 0,
            'image' => (string) ($p['image'] ?? ''),
            'url' => !empty($p['randomn_id']) ? '/product.php?id=' . rawurlencode((string) $p['randomn_id']) : '',
        ];
    }

    return $out;
}

/** @param list<array<string,mixed>> $products @param list<array<string,mixed>> $catalogForHint */
function public_shop_chat_build_search_reply(
    array $products,
    string $queryLabel = '',
    bool $isBrowse = false,
    array $catalogForHint = [],
    string $originalMessage = ''
): array {
    if ($products === []) {
        $topic = $queryLabel !== '' ? $queryLabel : 'căutarea ta';
        $hint = match ($topic) {
            'piese de motor' => ' Momentan nu avem listate piese de motor potrivite. Încearcă cod OEM, VIN sau WhatsApp.',
            'ulei motor', 'ulei' => ' Momentan nu avem ulei motor listat. Avem lubrifiant/filtre — spune vizcositatea (ex. 5W30) sau cod OEM.',
            'filtre auto', 'filtru ulei', 'filtru aer' => widget_catalog_empty_filter_hint(
                $originalMessage !== '' ? $originalMessage : $topic,
                $catalogForHint
            ),
            default => ' Încearcă cod OEM (ex. 30204A) sau nume scurt (filtru, disc frână).',
        };
        return [
            'reply' => 'Nu am găsit potriviri clare pentru „' . $topic . '” în stocul online.' . $hint,
            'in_stock' => false,
            'availability' => 'not_found',
        ];
    }

    $inStock = array_values(array_filter($products, static fn ($p) => !empty($p['in_stock'])));
    if ($inStock === []) {
        $names = implode(', ', array_slice(array_map(static fn ($p) => (string) ($p['name'] ?? ''), $products), 0, 2));
        return [
            'reply' => 'Am identificat piesa (' . $names . '), dar momentan nu este în stoc. '
                . 'Te anunțăm când revine sau cautăm echivalent — vrei să lași date de contact?',
            'in_stock' => false,
            'availability' => 'out_of_stock',
            'products' => $products,
        ];
    }

    $lines = [];
    $show = array_slice($inStock, 0, $isBrowse ? 5 : 3);
    foreach ($show as $p) {
        $line = '✓ ' . (string) ($p['name'] ?? 'Produs');
        if (!empty($p['code'])) {
            $line .= ' · cod ' . $p['code'];
        }
        if (!empty($p['price'])) {
            $line .= ' · ' . $p['price'] . ' RON';
        }
        $line .= ' · stoc ' . (int) ($p['stock'] ?? 0) . ' buc.';
        $lines[] = $line;
    }

    $topic = $queryLabel !== '' ? $queryLabel : 'cerere';
    if ($isBrowse || count($inStock) > 1) {
        $reply = 'Am găsit piese pentru „' . $topic . '” în stoc (live din magazin):' . "\n" . implode("\n", $lines);
        if (count($inStock) > count($show)) {
            $reply .= "\n\n…și alte " . (count($inStock) - count($show)) . ' variante. Spune codul exact sau „vreau să comand”.';
        } else {
            $reply .= "\n\nSpune ce variantă vrei sau „vreau să comand”.";
        }
    } else {
        $reply = "Da, îl avem în stoc:\n" . implode("\n", $lines);
        $reply .= "\n\nSpune „vreau să comand” sau apasă butonul Comandă de mai jos.";
    }

    return [
        'reply' => $reply,
        'in_stock' => true,
        'availability' => 'in_stock',
        'products' => $inStock,
    ];
}

/**
 * @param list<array<string,mixed>> $products
 * @param array<string,mixed> $state
 * @return array<string,mixed>
 */
function public_shop_chat_handle(string $message, array $products, array &$state): array
{
    $message = trim($message);
    if ($message === '') {
        return ['handled' => false];
    }

    if (public_shop_chat_detect_greeting_only($message)) {
        return [
            'handled' => true,
            'handled_by' => 'greeting',
            'reply' => 'Salut! Cu ce te pot ajuta? Spune ce piesă cauți — cod OEM, nume sau VIN.',
            'ui_action' => 'none',
            'products' => [],
            'sources' => [],
        ];
    }

    if (public_shop_chat_detect_order_intent($message)) {
        $cart = is_array($state['cart'] ?? null) ? $state['cart'] : [];
        $last = is_array($state['last_products'] ?? null) ? $state['last_products'] : [];
        if ($cart === [] && count($last) === 1 && !empty($last[0]['in_stock'])) {
            $cart = [$last[0]];
            $state['cart'] = $cart;
        }
        if ($cart === []) {
            $inStock = array_values(array_filter($last, static fn ($p) => !empty($p['in_stock'])));
            if (count($inStock) === 1) {
                $cart = [$inStock[0]];
                $state['cart'] = $cart;
            }
        }

        if ($cart === []) {
            return [
                'handled' => true,
                'handled_by' => 'order',
                'reply' => 'Cu plăcere! Spune mai întâi ce piesă cauți (cod sau nume) ca să verific stocul, apoi comanda.',
                'ui_action' => 'none',
                'products' => [],
                'sources' => [],
            ];
        }

        return [
            'handled' => true,
            'handled_by' => 'order',
            'reply' => 'Completează datele de mai jos — prețul este din catalog live și comanda ajunge imediat în magazin.',
            'ui_action' => 'order_form',
            'products' => $cart,
            'cart' => $cart,
            'sources' => array_map(static fn ($p) => [
                'name' => $p['name'] ?? '',
                'price' => $p['price'] ?? '',
                'stock' => (int) ($p['stock'] ?? 0),
                'randomn_id' => $p['randomn_id'] ?? '',
            ], $cart),
        ];
    }

    if (!public_shop_chat_looks_like_product_query($message)) {
        return ['handled' => false];
    }

    $hits = widget_catalog_search_message($message, $products, 8);
    $mapped = public_shop_chat_map_products($hits);
    $browse = widget_catalog_is_browse_query($message);
    $meta = widget_catalog_search_meta($message);
    $label = (string) ($meta['label'] ?? widget_catalog_query_label($message));
    $search = public_shop_chat_build_search_reply($mapped, $label, $browse, $products, $message);

    if (($meta['intent'] ?? '') === 'filter' && preg_match('/\b(dar|nu\s+sunt|corect|gre[sș]it)\b/ui', $message)) {
        if (!empty($search['products'])) {
            $lines = [];
            foreach (array_slice($search['products'], 0, 5) as $p) {
                $lines[] = '✓ ' . ($p['name'] ?? '') . ' · ' . ($p['price'] ?? '') . ' RON · stoc ' . (int) ($p['stock'] ?? 0);
            }
            $search['reply'] = 'Ai dreptate — filtrele nu sunt piese de motor. Iată filtre din stoc:' . "\n" . implode("\n", $lines)
                . "\n\nSpune tipul (filtru ulei / aer) sau „vreau să comand”.";
        }
    }

    $state['last_products'] = $mapped;
    if (!empty($search['in_stock']) && count($search['products'] ?? []) === 1) {
        $state['cart'] = [$search['products'][0]];
    }

    $sources = [];
    foreach ($search['products'] ?? $mapped as $p) {
        $sources[] = [
            'name' => $p['name'] ?? '',
            'price' => $p['price'] ?? '',
            'stock' => (int) ($p['stock'] ?? 0),
            'randomn_id' => $p['randomn_id'] ?? '',
            'image' => $p['image'] ?? '',
            'url' => $p['url'] ?? '',
            'in_stock' => !empty($p['in_stock']),
        ];
    }

    return [
        'handled' => true,
        'handled_by' => 'catalog',
        'reply' => (string) ($search['reply'] ?? ''),
        'ui_action' => !empty($search['in_stock']) ? 'products' : 'none',
        'availability' => (string) ($search['availability'] ?? 'unknown'),
        'products' => $search['products'] ?? $mapped,
        'sources' => $sources,
    ];
}

/** @return array{intent:string,label:string,version:int,source:string,action:string} */
function public_shop_chat_plan_search_meta(array $plan): array
{
    return [
        'intent' => (string) ($plan['intent'] ?? 'general'),
        'label' => (string) ($plan['label'] ?? 'căutare'),
        'version' => WIDGET_CATALOG_SEARCH_VERSION,
        'source' => (string) ($plan['source'] ?? 'rules'),
        'action' => (string) ($plan['action'] ?? 'search'),
    ];
}

/** @param array<string, mixed> $plan @param list<array<string, mixed>> $products */
function public_shop_chat_finish_handled(
    array $result,
    array $plan,
    array $products,
    array &$state,
    ?object $orchestrator,
    array $apiInput,
    string $message
): array {
    $meta = public_shop_chat_plan_search_meta($plan);
    $defaultChips = class_exists(\Besoiu\Services\ShopChatIntentParserService::class)
        ? \Besoiu\Services\ShopChatIntentParserService::chipsFromCatalog($products)
        : widget_chat_default_chips($products);

    if (empty($result['chips'])) {
        $result['chips'] = $defaultChips;
    }
    $result['search_meta'] = $meta;
    $vehicle = \Besoiu\Services\ShopChatSessionService::getVehicle($state);
    if ($vehicle !== null && empty($result['vehicle'])) {
        $result['vehicle'] = $vehicle;
    }

    if ($orchestrator instanceof \Besoiu\Services\PublicShopChatOrchestrator && !empty($result['handled'])) {
        try {
            $result = $orchestrator->enrichCatalogResponse($result, $state, $apiInput, $products);
        } catch (\Throwable) {
            // non-blocking
        }
    }

    return $result;
}

/**
 *
 * @param array<string, mixed> $plan
 * @param list<array<string, mixed>> $products
 * @param array<string, mixed> $state
 * @param array<string, mixed> $apiInput
 * @return array<string, mixed>
 */
function public_shop_chat_execute_plan(
    array $plan,
    string $message,
    array $products,
    array &$state,
    ?object $orchestrator = null,
    array $apiInput = []
): array {
    \Besoiu\Services\ShopChatSessionService::normalize($state);

    $quizService = new \Besoiu\Services\ShopChatCheckoutQuizService();
    $quizActive = \Besoiu\Services\ShopChatSessionService::getQuiz($state);
    if ($quizActive !== null) {
        $quizResult = $quizService->handleMessage($message, $state, $apiInput);
        if ($quizResult !== null) {
            return public_shop_chat_finish_handled($quizResult, $plan, $products, $state, $orchestrator, $apiInput, $message);
        }
    }

    $onboardingService = new \Besoiu\Services\ShopChatOnboardingService();
    $profile = ['client_name' => \Besoiu\Services\ShopChatSessionService::clientName($state)];
    $onb = $onboardingService->tryHandleOnboardingMessage($message, $state, $profile);
    if ($onb !== null && ($plan['action'] ?? '') !== 'search') {
        return public_shop_chat_finish_handled($onb, $plan, $products, $state, $orchestrator, $apiInput, $message);
    }

    $vehicleService = new \Besoiu\Services\ShopChatVehicleService();
    $parsedVehicle = $vehicleService->parseFromMessage($message);
    if ($parsedVehicle !== null) {
        \Besoiu\Services\ShopChatSessionService::setVehicle($parsedVehicle, $state);
    }

    $refinementService = new \Besoiu\Services\ShopChatRefinementService();
    if (($plan['action'] ?? '') === 'refine' || $refinementService->isRefinementMessage($message, $state)) {
        $mapped = public_shop_chat_map_products(
            is_array($state['last_products'] ?? null) ? $state['last_products'] : []
        );
        $refined = $refinementService->refine($message, $mapped, $state);
        $lines = [];
        foreach (array_slice($refined['products'], 0, 5) as $p) {
            $lines[] = '✓ ' . ($p['name'] ?? '') . ' · ' . ($p['price'] ?? '') . ' RON';
        }
        $count = count($refined['products']);
        $reply = $count > 0
            ? 'Am filtrat lista (' . $count . ' produse):' . "\n" . implode("\n", $lines)
                . ($count > 5 ? "\n\n…și altele. Spune „vreau să comand” sau rafinează (preț, brand, nr. produs)." : "\n\nSpune nr. (1,2…) sau „vreau să comand”.")
            : 'Niciun produs nu corespunde filtrelor. Încearcă alt criteriu sau o căutare nouă.';
        if ($refined['reply_hint'] !== '') {
            $reply .= "\n\n" . $refined['reply_hint'];
        }
        $vehicle = \Besoiu\Services\ShopChatSessionService::getVehicle($state);
        $result = [
            'handled' => true,
            'handled_by' => 'refine',
            'reply' => $reply,
            'ui_action' => $count > 0 ? 'products' : 'none',
            'availability' => $count > 0 ? 'in_stock' : 'not_found',
            'products' => $refined['products'],
            'vehicle' => $vehicle,
            'in_stock' => $count > 0,
            'sources' => array_map(static fn ($p) => [
                'name' => $p['name'] ?? '',
                'price' => $p['price'] ?? '',
                'stock' => (int) ($p['stock'] ?? 0),
                'randomn_id' => $p['randomn_id'] ?? '',
            ], array_slice($refined['products'], 0, 5)),
        ];
        $state['last_products'] = $refined['products'];

        return public_shop_chat_finish_handled($result, $plan, $products, $state, $orchestrator, $apiInput, $message);
    }

    if ($onb !== null) {
        return public_shop_chat_finish_handled($onb, $plan, $products, $state, $orchestrator, $apiInput, $message);
    }

    $action = (string) ($plan['action'] ?? 'search');
    $defaultChips = class_exists(\Besoiu\Services\ShopChatIntentParserService::class)
        ? \Besoiu\Services\ShopChatIntentParserService::chipsFromCatalog($products)
        : [
            ['id' => '30204a', 'label' => '30204A'],
            ['id' => 'filtru ulei', 'label' => 'Filtru ulei'],
            ['id' => 'engine', 'label' => 'Piese motor'],
            ['id' => 'vreau sa comand', 'label' => 'Vreau să comand'],
            ['id' => 'whatsapp', 'label' => 'WhatsApp'],
        ];

    $meta = public_shop_chat_plan_search_meta($plan);
    $attachMeta = static function (array $out) use ($meta, $defaultChips): array {
        $out['search_meta'] = $meta;
        if (empty($out['chips'])) {
            $out['chips'] = $defaultChips;
        }

        return $out;
    };

    if (preg_match('/\b([A-HJ-NPR-Z0-9]{17})\b/i', $message)) {
        return ['handled' => false];
    }

    if (in_array($action, ['whatsapp', 'order_status', 'consent'], true) && $orchestrator !== null) {
        try {
            /** @var array<string, mixed> $orch */
            $orch = $orchestrator->handleMessage($message, $products, $state, $apiInput);
            if (!empty($orch['handled'])) {
                return $attachMeta($orch);
            }
        } catch (\Throwable) {
            // fallback mai jos
        }
    }

    if ($action === 'greeting') {
        return $attachMeta([
            'handled' => true,
            'handled_by' => 'greeting',
            'reply' => 'Salut! Cu ce te pot ajuta? Spune ce piesă cauți.',
            'ui_action' => 'none',
            'products' => [],
            'sources' => [],
        ]);
    }

    if ($action === 'order') {
        $cart = is_array($state['cart'] ?? null) ? $state['cart'] : [];
        $last = is_array($state['last_products'] ?? null) ? $state['last_products'] : [];
        if ($cart === [] && count($last) === 1 && !empty($last[0]['in_stock'])) {
            $cart = [public_shop_chat_map_products([$last[0]])[0] ?? $last[0]];
            $state['cart'] = $cart;
        }
        if ($cart !== []) {
            $quizStart = $quizService->start($cart, $state);

            return public_shop_chat_finish_handled($quizStart, $plan, $products, $state, $orchestrator, $apiInput, $message);
        }
        return $attachMeta([
            'handled' => true,
            'handled_by' => 'order',
            'reply' => 'Cu plăcere! Spune ce piesă cauți (cod sau categorie) ca să verific stocul, apoi începem comanda pas cu pas.',
            'ui_action' => 'none',
            'products' => [],
            'sources' => [],
        ]);
    }

    if ($action === 'general') {
        return ['handled' => false];
    }

    if ($action === 'whatsapp') {
        $waPhone = '40700000000';
        $companyFile = __DIR__ . '/company.json';
        if (is_file($companyFile)) {
            $co = json_decode((string) file_get_contents($companyFile), true);
            if (is_array($co) && !empty($co['company']['whatsapp'])) {
                $waPhone = preg_replace('/\D+/', '', (string) $co['company']['whatsapp']) ?: $waPhone;
            }
        }
        $text = rawurlencode('Bună, vă contactez din chat-ul de pe site. ' . trim($message));
        return $attachMeta([
            'handled' => true,
            'handled_by' => 'whatsapp',
            'reply' => 'Continuă pe WhatsApp — trimitem contextul conversației.',
            'ui_action' => 'whatsapp',
            'whatsapp_url' => 'https://wa.me/' . $waPhone . '?text=' . $text,
            'products' => [],
            'sources' => [],
        ]);
    }

    if ($action !== 'search' && $action !== 'clarify') {
        return ['handled' => false];
    }

    $lastInStock = array_values(array_filter(
        is_array($state['last_products'] ?? null) ? $state['last_products'] : [],
        static fn ($p) => is_array($p) && !empty($p['in_stock'])
    ));
    if ($action === 'clarify' && $lastInStock !== [] && ($plan['user_meaning'] ?? '') !== ''
        && !widget_catalog_is_new_search_message($message)) {
        $lines = [];
        foreach (array_slice($lastInStock, 0, 5) as $i => $p) {
            $lines[] = ($i + 1) . '. ' . ($p['name'] ?? '') . ' · ' . ($p['price'] ?? '') . ' RON';
        }
        return $attachMeta([
            'handled' => true,
            'handled_by' => 'intent_context',
            'reply' => 'Am înțeles — iată variantele din ultima căutare:' . "\n" . implode("\n", $lines)
                . "\n\nSpune numărul (1, 2…) sau codul exact, sau „vreau să comand”.",
            'ui_action' => 'products',
            'availability' => 'in_stock',
            'products' => array_slice($lastInStock, 0, 5),
            'sources' => array_map(static fn ($p) => [
                'name' => $p['name'] ?? '',
                'price' => $p['price'] ?? '',
                'stock' => (int) ($p['stock'] ?? 0),
                'randomn_id' => $p['randomn_id'] ?? '',
            ], array_slice($lastInStock, 0, 5)),
        ]);
    }

    $hits = widget_catalog_search_from_plan($plan, $products, 8);
    $mapped = public_shop_chat_map_products($hits);
    $mapped = public_shop_chat_apply_plan_filters($mapped, $plan);
    $label = (string) ($plan['label'] ?? 'căutare');
    $browse = count($mapped) > 1 || ($plan['intent'] ?? '') === 'engine';
    $search = public_shop_chat_build_search_reply($mapped, $label, $browse, $products, $message);

    $verified = public_shop_chat_verified_reply_prefix($plan, $message);
    if ($verified !== '' && !str_starts_with((string) ($search['reply'] ?? ''), 'Am înțeles')) {
        $search['reply'] = $verified . "\n\n" . (string) ($search['reply'] ?? '');
    }

    if (!empty($plan['vehicle_hint'])) {
        \Besoiu\Services\ShopChatSessionService::setVehicle([
            'label' => (string) $plan['vehicle_hint'],
            'tokens' => preg_split('/[\s,.-]+/u', mb_strtolower((string) $plan['vehicle_hint'], 'UTF-8')) ?: [],
        ], $state);
    }

    $intent = (string) ($plan['intent'] ?? 'general');
    $isCorrection = preg_match('/\b(dar|nu\s+sunt|corect|gre[sș]it|filtre)\b/ui', $message);
    if (($action === 'clarify' || ($intent === 'filter' && $isCorrection)) && !empty($search['products'])) {
        $lines = [];
        foreach (array_slice($search['products'], 0, 5) as $p) {
            $lines[] = '✓ ' . ($p['name'] ?? '') . ' · ' . ($p['price'] ?? '') . ' RON · stoc ' . (int) ($p['stock'] ?? 0);
        }
        $search['reply'] = 'Am înțeles — cauți filtre, nu piese de motor. Iată din stoc:' . "\n" . implode("\n", $lines)
            . "\n\nSpune tipul (filtru ulei / aer) sau apasă „Vreau să comand”.";
    }

    $productList = is_array($search['products'] ?? null) ? $search['products'] : $mapped;
    $vehicle = \Besoiu\Services\ShopChatSessionService::getVehicle($state);
    if ($vehicle !== null && count($productList) > 1) {
        $filtered = \Besoiu\Services\ShopChatVehicleService::filterProducts($productList, $vehicle);
        if ($filtered !== []) {
            $productList = $filtered;
            $search['reply'] = (string) ($search['reply'] ?? '') . "\n\n🚗 Filtrat pentru: " . ($vehicle['label'] ?? 'vehicul');
        }
    }

    \Besoiu\Services\ShopChatSessionService::storeSearchResults($productList, $label, $state);
    $state['last_plan'] = $plan;
    if (!empty($search['in_stock']) && count($productList) === 1) {
        $state['cart'] = [$productList[0]];
    }

    $sources = [];
    foreach ($productList as $p) {
        $sources[] = [
            'name' => $p['name'] ?? '',
            'price' => $p['price'] ?? '',
            'stock' => (int) ($p['stock'] ?? 0),
            'randomn_id' => $p['randomn_id'] ?? '',
            'image' => $p['image'] ?? '',
            'url' => $p['url'] ?? '',
            'in_stock' => !empty($p['in_stock']),
        ];
    }

    $result = [
        'handled' => true,
        'handled_by' => 'intent_' . ($meta['source'] ?? 'rules'),
        'reply' => (string) ($search['reply'] ?? ''),
        'ui_action' => !empty($search['in_stock']) ? 'products' : 'none',
        'availability' => (string) ($search['availability'] ?? 'unknown'),
        'products' => $productList,
        'sources' => $sources,
        'in_stock' => !empty($search['in_stock']),
        'vehicle' => $vehicle,
    ];

    if (count($productList) > 1) {
        $result['reply'] = (string) $result['reply'] . "\n\nPoți rafina: „Bosch”, „sub 100 RON”, „mai ieftin” sau nr. produs (1, 2…).";
    }

    return public_shop_chat_finish_handled($result, $plan, $products, $state, $orchestrator, $apiInput, $message);
}

/** @param list<array<string,mixed>> $cart @param array<string,mixed> $form */
function public_shop_chat_build_order_notes(array $cart, array $form): string
{
    $lines = ['Comandă plasată din chat widget site.'];
    $lines[] = 'Client: ' . trim((string) ($form['client_name'] ?? ''));
    $lines[] = 'Telefon: ' . trim((string) ($form['phone'] ?? ''));
    if (!empty($form['email'])) {
        $lines[] = 'Email: ' . trim((string) $form['email']);
    }
    $lines[] = 'Livrare: ' . trim((string) ($form['delivery_label'] ?? $form['delivery_method'] ?? ''));
    $lines[] = 'Plată: ' . trim((string) ($form['payment_label'] ?? $form['payment_method'] ?? ''));
    if (!empty($form['city'])) {
        $lines[] = 'Localitate: ' . trim((string) $form['city']);
    }
    if (!empty($form['address'])) {
        $lines[] = 'Adresă: ' . trim((string) $form['address']);
    }
    $lines[] = '';
    $lines[] = 'Produse:';
    foreach ($cart as $i => $item) {
        $qty = max(1, (int) ($item['quantity'] ?? 1));
        $lines[] = ($i + 1) . '. ' . ($item['name'] ?? 'Produs') . ' · ' . $qty . ' buc. · ' . ($item['price'] ?? '') . ' RON';
    }

    return implode("\n", $lines);
}
