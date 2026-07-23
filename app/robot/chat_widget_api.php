<?php
/**
 * robot/chat_widget_api.php
 *
 * Endpoint AJAX pentru widgetul de chat de pe website.
 * - Primeste POST JSON: { message, session_id }
 * - Trimite mesajul prin Metro LLM: Ollama → Cursor → Groq (context stoc, companie)
 * - Returneaza JSON: { reply, session_id, sources? }
 *
 * Securitate: CORS restrictionat la domeniu propriu + rate-limit bazat pe sesiune.
 */

declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED & ~E_USER_DEPRECATED);

require_once __DIR__ . '/bootstrap.php';

$robotSiteRoot = robot_site_root();
$robotAppRoot = dirname(__DIR__);

/** @param array<string, mixed> $payload */
function widget_json_response(array $payload, int $status = 200): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    $flags = JSON_UNESCAPED_UNICODE;
    if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
        $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
    }
    $json = json_encode($payload, $flags);
    if ($json === false) {
        $json = json_encode([
            'ok' => false,
            'reply' => 'Eroare la generarea răspunsului. Spune cod OEM sau nume piesă.',
            'session_id' => (string) ($payload['session_id'] ?? ''),
            'error' => 'json_encode_failed',
        ], $flags) ?: '{"reply":"Eroare server.","session_id":""}';
    }
    echo $json;
    exit;
}

/* ────── CORS & headers ────── */
$origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');

$widgetOriginAllowed = static function (string $checkOrigin): bool {
    if ($checkOrigin === '') {
        return true;
    }
    $exact = [
        'https://besoiupieseauto.ro',
        'http://besoiupieseauto.ro',
        'https://www.besoiupieseauto.ro',
        'http://www.besoiupieseauto.ro',
    ];
    if (in_array($checkOrigin, $exact, true)) {
        return true;
    }
    $host = parse_url($checkOrigin, PHP_URL_HOST);
    $host = is_string($host) ? strtolower($host) : '';
    if ($host !== '' && str_ends_with($host, 'besoiupieseauto.ro')) {
        return true;
    }
    if (strtolower((string) env('APP_ENV', 'production')) !== 'development') {
        return false;
    }
    if (in_array($host, ['localhost', '127.0.0.1'], true)) {
        return true;
    }
    return str_ends_with($host, '.test') || str_ends_with($host, '.local');
};

if ($origin !== '') {
    if (!$widgetOriginAllowed($origin)) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Origin not allowed']);
        exit;
    }
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
}
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code($origin !== '' ? 204 : 405);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['error'=>'POST only']); exit; }

/* ────── Rate limit simplu (session-based) ────── */
require_once $robotSiteRoot . '/system/session-bridge.php';
$widgetRate = besoiu_session_peek('bpa_shop', static function (): array {
    $_SESSION['widget_req_count'] = (int) ($_SESSION['widget_req_count'] ?? 0) + 1;
    $_SESSION['widget_req_ts'] = (int) ($_SESSION['widget_req_ts'] ?? time());

    if ((time() - (int) $_SESSION['widget_req_ts']) > 60) {
        $_SESSION['widget_req_count'] = 1;
        $_SESSION['widget_req_ts'] = time();
    }

    return [
        'count' => (int) $_SESSION['widget_req_count'],
    ];
});
if (($widgetRate['count'] ?? 0) > 30) {
    http_response_code(429);
    echo json_encode(['reply' => 'Prea multe cereri. Va rugam asteptati un minut.']);
    exit;
}

/* ────── Input ────── */
$raw  = file_get_contents('php://input') ?: '{}';
$body = json_decode($raw, true) ?: [];
$action = trim((string) ($body['action'] ?? 'message'));
$message = trim((string) ($body['message'] ?? ''));
$session_id = preg_replace('/[^a-z0-9\-]/', '', strtolower((string) ($body['session_id'] ?? '')));
if ($session_id === '') {
    $session_id = bin2hex(random_bytes(8));
}

$pagePath = trim((string) ($body['page'] ?? ''));
if ($pagePath === '' && !empty($_SERVER['HTTP_REFERER'])) {
    $refPath = parse_url((string) $_SERVER['HTTP_REFERER'], PHP_URL_PATH);
    $pagePath = is_string($refPath) ? $refPath : '';
}
$apiInput = array_merge($body, [
    'session_id' => $session_id,
    'page' => $pagePath,
]);

$adminAutoload = $robotSiteRoot . '/admin/vendor/autoload.php';
$orchestrator = null;
if (is_file($adminAutoload)) {
    require_once $robotSiteRoot . '/admin/bootstrap.php';
    require_once $adminAutoload;
    $shopOrderGuard = $robotSiteRoot . '/app/Legacy/shop-order-guard.php';
    if (is_file($shopOrderGuard)) {
        require_once $shopOrderGuard;
    }
    $shopAuth = $robotSiteRoot . '/system/shop-auth.php';
    if (!is_file($shopAuth)) {
        $shopAuth = $robotSiteRoot . '/app/Legacy/shop-auth.php';
    }
    if (is_file($shopAuth)) {
        require_once $shopAuth;
    }
    if (function_exists('shop_auth_load_env')) {
        shop_auth_load_env();
    }
    try {
        if (!\Config\Database::hasConnection()) {
            $config = require $robotSiteRoot . '/admin/config/config.php';
            \Config\Database::getInstance(
                (string) $config['db_host'],
                (string) $config['db_name'],
                (string) $config['db_user'],
                (string) $config['db_pass']
            );
        }
        $orchestrator = \Besoiu\Services\PublicShopChatOrchestrator::create();
    } catch (Throwable) {
        $orchestrator = null;
    }
}

if ($action === 'init' && $orchestrator !== null) {
    $init = $orchestrator->handleInit($apiInput);
    widget_json_response(array_merge($init, [
        'session_id' => $session_id,
        'orders_endpoint' => '/admin/api/comenzi_endpoint.php',
        'csrf_token' => shop_order_csrf_token(),
    ]));
}

if ($action === 'persist_cart' && $orchestrator !== null) {
    $cart = is_array($body['cart'] ?? null) ? $body['cart'] : [];
    $orchestrator->savePersistedCart($apiInput, $cart);
    widget_json_response(['ok' => true, 'session_id' => $session_id]);
}

if ($message === '' && $action === 'message') {
    widget_json_response(['reply' => 'Mesajul este gol.', 'session_id' => $session_id]);
}

/* ────── Incarcare date context ────── */
function widget_load_json(string $path): array {
    if (!is_file($path)) return [];
    $j = json_decode((string)file_get_contents($path), true);
    return is_array($j) ? $j : [];
}

require_once __DIR__ . '/products_catalog.php';
$products = robot_products_load_catalog();
$catalogSource = 'database';
$cacheFile = __DIR__ . '/data/products_catalog_cache.json';
if (is_file($cacheFile)) {
    $cacheMeta = json_decode((string) file_get_contents($cacheFile), true);
    if (is_array($cacheMeta) && !empty($cacheMeta['source'])) {
        $catalogSource = (string) $cacheMeta['source'];
    }
}
require_once __DIR__ . '/widget_catalog_search.php';
require_once __DIR__ . '/public_shop_chat.php';
$company = widget_load_json(__DIR__ . '/company.json');
$chipId = trim((string) ($body['chip_id'] ?? ''));

/* ────── Sesiune conversatie (fisier) ────── */
$sessDir = __DIR__ . '/data/widget_sessions';
if (!is_dir($sessDir)) {
    @mkdir($sessDir, 0775, true);
}
$sessFile = $sessDir . '/' . sha1($session_id) . '.json';
$sessionData = public_shop_chat_load_session($sessFile);
$history = $sessionData['history'];
$sessionState = &$sessionData['state'];

$intentPlan = null;
$shopHandled = ['handled' => false];
if (is_file($adminAutoload)) {
    try {
        $intentParser = \Besoiu\Services\ShopChatIntentParserService::create();
        $intentPlan = $intentParser->parse(
            $message,
            $history,
            $sessionState,
            $chipId !== '' ? $chipId : null
        );
        $shopHandled = public_shop_chat_execute_plan(
            $intentPlan,
            $message,
            $products,
            $sessionState,
            $orchestrator,
            $apiInput
        );
        if (!empty($shopHandled['handled']) && $orchestrator !== null) {
            try {
                $orchestrator->savePersistedCart($apiInput, is_array($sessionState['cart'] ?? null) ? $sessionState['cart'] : []);
                $orchestrator->scheduleFollowupIfNeeded(
                    $apiInput,
                    $sessionState,
                    is_array($sessionState['cart'] ?? null) ? $sessionState['cart'] : []
                );
            } catch (Throwable) {
                // non-blocking
            }
        }
    } catch (Throwable $intentErr) {
        error_log('chat_widget intent: ' . $intentErr->getMessage());
        $shopHandled = ['handled' => false];
    }
}

if (empty($shopHandled['handled']) && $orchestrator !== null) {
    try {
        $shopHandled = $orchestrator->handleMessage($message, $products, $sessionState, $apiInput);
        if (!empty($sessionState['cart'])) {
            $orchestrator->savePersistedCart($apiInput, $sessionState['cart']);
            $orchestrator->scheduleFollowupIfNeeded($apiInput, $sessionState, $sessionState['cart']);
        }
    } catch (Throwable $orchErr) {
        $shopHandled = public_shop_chat_handle($message, $products, $sessionState);
        if (empty($shopHandled['handled'])) {
            error_log('chat_widget orchestrator: ' . $orchErr->getMessage());
        }
    }
} elseif (empty($shopHandled['handled'])) {
    $shopHandled = public_shop_chat_handle($message, $products, $sessionState);
}

if ($intentPlan === null && empty($shopHandled['handled']) && public_shop_chat_looks_like_product_query($message)) {
    $shopHandled = public_shop_chat_handle($message, $products, $sessionState);
}

$messageHits = widget_catalog_search_message($message, $products, 5);
$searchContext = widget_catalog_hits_context($messageHits);

/* Construieste rezumatul stocului pentru context LLM */
$stockLines = [];
foreach ($products as $p) {
    if (!is_array($p)) continue;
    $name  = (string)($p['name'] ?? '');
    $code  = (string)($p['code'] ?? '');
    $oem   = (string)($p['oem'] ?? '');
    $price = (string)($p['price'] ?? '');
    $stock = (int)($p['stock'] ?? 0);
    if ($name === '') continue;
    $line = "- {$name}";
    if ($code)  $line .= " | Cod: {$code}";
    if ($oem)   $line .= " | OEM: {$oem}";
    if ($price) $line .= " | Pret: {$price}";
    $line .= " | Stoc: " . ($stock > 0 ? "{$stock} buc" : "INDISPONIBIL");
    $stockLines[] = $line;
}
$stockContext = implode("\n", array_slice($stockLines, 0, 60)); // max 60 produse in context

/* Info companie */
$companyName = (string)($company['company']['name'] ?? 'Besoiu Piese Auto');
$companyDesc = (string)($company['company']['description'] ?? 'Magazin de piese auto');
$terms = $company['terms'] ?? [];
$delivery = (string)($terms['delivery'] ?? '');
$returns  = (string)($terms['returns'] ?? '');
$payment  = (string)($terms['payment'] ?? '');

if (!empty($shopHandled['handled'])) {
    $history[] = ['role' => 'user', 'content' => $message];
    $history[] = ['role' => 'assistant', 'content' => (string) ($shopHandled['reply'] ?? '')];
    public_shop_chat_save_session($sessFile, $sessionData);

    $csrfToken = '';
    if (is_file($adminAutoload)) {
        try {
            require_once $robotSiteRoot . '/app/Legacy/shop-order-guard.php';
            $csrfToken = shop_order_csrf_token();
        } catch (Throwable) {
            $csrfToken = '';
        }
    }

    widget_json_response(widget_chat_response_payload(
        $shopHandled,
        $session_id,
        $catalogSource,
        $sessionState,
        $intentPlan,
        $message,
        $csrfToken,
        $products
    ));
}

/* ────── Fallback catalog (fără LLM) pentru întrebări generale ────── */
if ($messageHits !== []) {
    $mappedHits = public_shop_chat_map_products($messageHits);
    $searchFb = public_shop_chat_build_search_reply(
        $mappedHits,
        widget_catalog_query_label($message),
        widget_catalog_is_browse_query($message),
        $products,
        $message
    );
    $csrfToken = '';
    if (is_file($adminAutoload)) {
        try {
            require_once $robotSiteRoot . '/app/Legacy/shop-order-guard.php';
            $csrfToken = shop_order_csrf_token();
        } catch (Throwable) {
            $csrfToken = '';
        }
    }
    widget_json_response([
        'reply' => (string) ($searchFb['reply'] ?? 'Am găsit produse în catalog.'),
        'session_id' => $session_id,
        'sources' => array_map(static fn ($p) => [
            'name' => $p['name'] ?? '',
            'price' => $p['price'] ?? '',
            'stock' => (int) ($p['stock'] ?? 0),
        ], $mappedHits),
        'products' => $searchFb['products'] ?? $mappedHits,
        'cart' => is_array($sessionState['cart'] ?? null) ? $sessionState['cart'] : [],
        'ui_action' => !empty($searchFb['in_stock']) ? 'products' : 'none',
        'handled_by' => 'catalog_fallback',
        'availability' => (string) ($searchFb['availability'] ?? ''),
        'catalog_source' => $catalogSource,
        'orders_endpoint' => '/admin/api/comenzi_endpoint.php',
        'csrf_token' => $csrfToken,
        'chips' => widget_chat_default_chips($products),
    ]);
}

if (preg_match('/\b(produse|aveti|aveți|catalog|ce\s+ai|list[aă])\b/ui', $message)) {
    widget_json_response([
        'reply' => 'Avem piese auto în stoc live. Spune liber ce cauți — cod OEM, nume piesă, VIN sau „piese motor”.',
        'session_id' => $session_id,
        'handled_by' => 'catalog_hint',
        'chips' => widget_chat_default_chips($products),
        'catalog_source' => $catalogSource,
    ]);
}

/* ────── System prompt ────── */
$systemPrompt = <<<PROMPT
Esti un asistent AI inteligent si prietenos pentru magazinul de piese auto "{$companyName}".
{$companyDesc}

INFORMATII COMPANIE:
- Livrare: {$delivery}
- Retur: {$returns}
- Plata: {$payment}

CATALOG PRODUSE (stoc actual — sursa: {$catalogSource}):
{$stockContext}

INSTRUCTIUNI:
1. Raspunzi DOAR in limba romana, clar si concis.
2. Cand clientul intreaba de un produs sau piesa, cauta in catalogul de mai sus si ofera detalii exacte (pret, stoc, cod OEM).
3. Daca produsul nu este in catalog sau stocul e 0, spune cinstit ca nu il avem momentan si sugereaza sa ne contacteze.
4. Daca clientul da un cod VIN (17 caractere alfanumerice), mentioneaza ca il poti ajuta sa identifice piesele compatibile.
5. Nu inventa preturi sau disponibilitate - foloseste doar datele din catalog.
6. Fii empatic, profesionist si orientat spre vanzare.
7. La intrebari despre comenzi, livrare, retur - foloseste informatiile companiei de mai sus.
8. Raspunsurile sa fie scurte (max 3-4 fraze) daca nu se cere ceva detaliat.
PROMPT;

if ($searchContext !== '') {
    $systemPrompt .= "\n\n" . $searchContext;
}

/* Context agent — runtime.md per agent + core.json */
require_once __DIR__ . '/ai_agent_context.php';
$pagePath = trim((string) ($body['page'] ?? ''));
if ($pagePath === '' && !empty($_SERVER['HTTP_REFERER'])) {
    $refPath = parse_url((string) $_SERVER['HTTP_REFERER'], PHP_URL_PATH);
    $pagePath = is_string($refPath) ? $refPath : '';
}

$agentSlug = trim((string) ($body['agent'] ?? ''));
$routeReason = '';

if ($agentSlug === '' || $pagePath !== '') {
    $autoload = $robotSiteRoot . '/admin/vendor/autoload.php';
    if (is_file($autoload)) {
        require_once $robotSiteRoot . '/admin/bootstrap.php';
        require_once $autoload;
        $router = new \Besoiu\Services\AiAgentRouterService();
        $route = $router->resolve(['path' => $pagePath]);
        $agentSlug = (string) ($route['slug'] ?? 'context-master');
        $routeReason = (string) ($route['reason'] ?? '');
        $agentContext = $router->buildBriefCompositeRuntimeMarkdown($agentSlug, 7500);
    } else {
        if ($agentSlug === '') {
            $agentSlug = robot_ai_default_agent_slug();
        }
        $agentContext = robot_ai_agent_runtime($agentSlug);
    }
} else {
    $agentContext = robot_ai_agent_runtime($agentSlug);
}

$agentTemperature = robot_ai_agent_temperature($agentSlug);
if ($agentContext === '' && $agentSlug !== '') {
    $agentContext = robot_ai_agent_runtime($agentSlug);
}
if ($agentContext !== '') {
    $systemPrompt .= "\n\nCONTEXT AGENT [{$agentSlug}] (rutare automată"
        . ($routeReason !== '' ? ': ' . $routeReason : '')
        . "):\n" . $agentContext;
}
$coreDirectives = robot_ai_core_directives();
if ($coreDirectives !== []) {
    $systemPrompt .= "\n\nDIRECTIVE CORE AI (din acțiuni reale proiect):\n- "
        . implode("\n- ", $coreDirectives);
}

/* ────── Apel AI — Ollama agenți specializați sau Metro LLM ────── */
$messages = [];
foreach (array_slice($history, -10) as $h) {
    $messages[] = $h;
}
$messages[] = ['role' => 'user', 'content' => $message];

$reply = 'Eroare de comunicare cu asistentul AI. Incercati din nou.';
$sources = [];
$aiOk = false;
$llmProvider = '';
$handledBy = 'llm';

$adminAutoload = $robotSiteRoot . '/admin/vendor/autoload.php';
if (is_file($adminAutoload)) {
    require_once $robotSiteRoot . '/admin/bootstrap.php';
    require_once $adminAutoload;
    $shopAuth = $robotSiteRoot . '/system/shop-auth.php';
    if (!is_file($shopAuth)) {
        $shopAuth = $robotSiteRoot . '/app/Legacy/shop-auth.php';
    }
    if (is_file($shopAuth)) {
        require_once $shopAuth;
    }
    if (function_exists('shop_auth_load_env')) {
        shop_auth_load_env();
    }

    $ollamaBridge = new \Besoiu\Services\ShopChatOllamaAgentBridge($robotAppRoot);
    $chatContext = [
        'channel' => 'shop_chat',
        'visitor_key' => trim((string) ($apiInput['visitor_key'] ?? $session_id)),
        'phone' => trim((string) ($sessionState['phone'] ?? $apiInput['phone'] ?? '')),
        'email' => trim((string) ($apiInput['email'] ?? '')),
    ];
    $agentReply = $ollamaBridge->tryReply($agentSlug, $message, $messages, $pagePath, $chatContext);
    if (is_array($agentReply) && !empty($agentReply['ok']) && trim((string) ($agentReply['content'] ?? '')) !== '') {
        $reply = trim((string) $agentReply['content']);
        $llmProvider = (string) ($agentReply['model'] ?? 'ollama');
        $handledBy = (string) ($agentReply['handled_by'] ?? 'ollama_agent');
        $aiOk = true;
    }

    if (!$aiOk) {
        $chatLlm = \Besoiu\Services\ChatLlmService::create($robotAppRoot);
        $taskContext = 'chat_widget';
        $coreAgent = $ollamaBridge->resolveCoreAgent($agentSlug, $message);
        if ($coreAgent === 'agent-produse') {
            $taskContext = 'agent_produse';
        } elseif ($coreAgent === 'agent-clienti') {
            $taskContext = 'agent_clienti';
        }
        $result = $chatLlm->chat($messages, $systemPrompt, $agentTemperature, 60, $taskContext);

        if (!empty($result['ok']) && trim((string) ($result['content'] ?? '')) !== '') {
            $reply = trim((string) $result['content']);
            $llmProvider = (string) ($result['routed_via'] ?? $result['provider'] ?? '');
            $aiOk = true;
        } elseif (!empty($result['error'])) {
            require_once $robotSiteRoot . '/app/Legacy/ai_api_errors.php';
            ai_api_report_error(
                'chat-widget',
                (string) $result['error'],
                ['provider' => (string) ($result['routed_via'] ?? 'llm')],
                0
            );
        }
    }
}

if (!$aiOk && !is_file($adminAutoload)) {
    require_once $robotSiteRoot . '/app/Legacy/ai_api_errors.php';
    ai_api_report_error('chat-widget', 'Composer autoload admin lipsește', [], 500);
    widget_json_response([
        'reply'      => 'Asistentul AI nu este configurat. Va rugam contactati-ne direct.',
        'session_id' => $session_id,
    ]);
}

if (!$aiOk) {
    $readiness = isset($chatLlm) ? $chatLlm->readiness() : ['ready' => false];
    if (is_file($adminAutoload)) {
        try {
            $escalation = new \Besoiu\Services\ShopChatEscalationService();
            if ($escalation->shouldEscalate(null, $message)) {
                $escalation->create([
                    'message' => $message,
                    'channel' => 'shop_chat',
                    'visitor_key' => trim((string) ($apiInput['visitor_key'] ?? $session_id)),
                    'phone' => trim((string) ($sessionState['phone'] ?? $apiInput['phone'] ?? '')),
                    'email' => trim((string) ($apiInput['email'] ?? '')),
                    'reason' => 'no_answer',
                    'meta' => ['handled_by' => 'fallback'],
                ]);
            }
        } catch (Throwable) {
            // non-blocking
        }
    }
    if (empty($readiness['ready'])) {
        widget_json_response([
            'reply'      => 'Asistentul AI nu este configurat. Spune cod OEM sau nume piesă — verific stocul live.',
            'session_id' => $session_id,
            'handled_by' => 'catalog_hint',
            'chips' => widget_chat_default_chips($products),
        ]);
    }
}

if ($aiOk) {
    $history[] = ['role' => 'user', 'content' => $message];
    $history[] = ['role' => 'assistant', 'content' => $reply];
    $sessionData['history'] = $history;
    public_shop_chat_save_session($sessFile, $sessionData);

    foreach ($products as $p) {
        if (!is_array($p)) {
            continue;
        }
        $nm = strtolower((string) ($p['name'] ?? ''));
        if ($nm && str_contains(strtolower($reply), $nm)) {
            $sources[] = ['name' => $p['name'], 'price' => $p['price'] ?? '', 'stock' => (int) ($p['stock'] ?? 0)];
            if (count($sources) >= 3) {
                break;
            }
        }
    }
    if ($sources === [] && $messageHits !== []) {
        foreach ($messageHits as $p) {
            $sources[] = [
                'name' => $p['name'] ?? '',
                'price' => $p['price'] ?? '',
                'stock' => (int) ($p['stock'] ?? 0),
                'randomn_id' => $p['randomn_id'] ?? '',
            ];
            if (count($sources) >= 3) {
                break;
            }
        }
    }
}

$csrfToken = '';
if (is_file($adminAutoload)) {
    try {
        require_once $robotSiteRoot . '/app/Legacy/shop-order-guard.php';
        $csrfToken = shop_order_csrf_token();
    } catch (Throwable) {
        $csrfToken = '';
    }
}

if (!$aiOk) {
    widget_json_response([
        'reply' => 'Pot verifica stocul dacă spui cod OEM sau nume piesă. '
            . 'Asistentul AI avansat nu răspunde momentan — catalogul live funcționează.',
        'session_id' => $session_id,
        'sources' => [],
        'products' => public_shop_chat_map_products($messageHits),
        'cart' => is_array($sessionState['cart'] ?? null) ? $sessionState['cart'] : [],
        'ui_action' => 'none',
        'handled_by' => 'catalog_fallback',
        'catalog_source' => $catalogSource,
        'orders_endpoint' => '/admin/api/comenzi_endpoint.php',
        'csrf_token' => $csrfToken,
        'chips' => widget_chat_default_chips($products),
    ]);
}

widget_json_response([
    'reply'      => $reply,
    'session_id' => $session_id,
    'sources'    => $sources,
    'products'   => public_shop_chat_map_products($messageHits),
    'cart'       => is_array($sessionState['cart'] ?? null) ? $sessionState['cart'] : [],
    'ui_action'  => 'none',
    'handled_by' => $handledBy,
    'catalog_source' => $catalogSource,
    'llm_provider' => $llmProvider,
    'orders_endpoint' => '/admin/api/comenzi_endpoint.php',
    'csrf_token' => $csrfToken,
    'chips' => widget_chat_default_chips($products),
]);
