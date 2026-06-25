<?php
/**
 * robot/chat_widget_api.php
 *
 * Endpoint AJAX pentru widgetul de chat de pe website.
 * - Primeste POST JSON: { message, session_id }
 * - Trimite mesajul la Groq LLM cu context complet (stoc, companie)
 * - Returneaza JSON: { reply, session_id, sources? }
 *
 * Securitate: CORS restrictionat la domeniu propriu + rate-limit bazat pe sesiune.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

/* ────── CORS & headers ────── */
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowed = ['https://besoiupieseauto.ro', 'http://besoiupieseauto.ro', 'http://localhost', 'http://127.0.0.1'];
if (in_array($origin, $allowed, true) || str_contains($origin, 'besoiupieseauto.ro')) {
    header("Access-Control-Allow-Origin: {$origin}");
} else {
    header('Access-Control-Allow-Origin: *'); // dev local
}
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['error'=>'POST only']); exit; }

/* ────── Rate limit simplu (session-based) ────── */
session_start();
$_SESSION['widget_req_count'] = ($_SESSION['widget_req_count'] ?? 0) + 1;
$_SESSION['widget_req_ts']    = $_SESSION['widget_req_ts'] ?? time();

if ((time() - $_SESSION['widget_req_ts']) > 60) {
    $_SESSION['widget_req_count'] = 1;
    $_SESSION['widget_req_ts']    = time();
}
if ($_SESSION['widget_req_count'] > 30) {
    http_response_code(429);
    echo json_encode(['reply' => 'Prea multe cereri. Va rugam asteptati un minut.']);
    exit;
}

/* ────── Input ────── */
$raw  = file_get_contents('php://input') ?: '{}';
$body = json_decode($raw, true) ?: [];
$message    = trim((string)($body['message'] ?? ''));
$session_id = preg_replace('/[^a-z0-9\-]/', '', strtolower((string)($body['session_id'] ?? '')));
if ($session_id === '') $session_id = bin2hex(random_bytes(8));
if ($message === '') {
    echo json_encode(['reply' => 'Mesajul este gol.', 'session_id' => $session_id]);
    exit;
}

/* ────── Incarcare date context ────── */
function widget_load_json(string $path): array {
    if (!is_file($path)) return [];
    $j = json_decode((string)file_get_contents($path), true);
    return is_array($j) ? $j : [];
}

$products = widget_load_json(__DIR__ . '/products.json');
$company  = widget_load_json(__DIR__ . '/company.json');

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

/* ────── Sesiune conversatie (fisier) ────── */
$sessDir = __DIR__ . '/data/widget_sessions';
if (!is_dir($sessDir)) @mkdir($sessDir, 0775, true);
$sessFile = $sessDir . '/' . sha1($session_id) . '.json';

$history = [];
if (is_file($sessFile)) {
    $h = json_decode((string)file_get_contents($sessFile), true);
    if (is_array($h)) $history = $h;
}

/* ────── System prompt ────── */
$systemPrompt = <<<PROMPT
Esti un asistent AI inteligent si prietenos pentru magazinul de piese auto "{$companyName}".
{$companyDesc}

INFORMATII COMPANIE:
- Livrare: {$delivery}
- Retur: {$returns}
- Plata: {$payment}

CATALOG PRODUSE (stoc actual):
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

/* ────── Apel Groq ────── */
$groqKey   = (string) env('GROQ_KEY', '');
$groqModel = (string) env('GROQ_MODEL', 'llama-3.3-70b-versatile');

if ($groqKey === '') {
    echo json_encode([
        'reply'      => 'Asistentul AI nu este configurat. Va rugam contactati-ne direct.',
        'session_id' => $session_id
    ]);
    exit;
}

/* Construieste mesajele pentru Groq */
$messages = [['role' => 'system', 'content' => $systemPrompt]];
foreach (array_slice($history, -10) as $h) { // max 10 mesaje din istoric
    $messages[] = $h;
}
$messages[] = ['role' => 'user', 'content' => $message];

$payload = json_encode([
    'model'       => $groqModel,
    'messages'    => $messages,
    'temperature' => 0.7,
    'max_tokens'  => 512,
], JSON_UNESCAPED_UNICODE);

$ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        "Authorization: Bearer {$groqKey}",
    ],
    CURLOPT_TIMEOUT        => 20,
]);
$resp = curl_exec($ch);
$curlErr = curl_error($ch);
$httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$reply = 'Eroare de comunicare cu asistentul AI. Incercati din nou.';
$sources = [];

if ($curlErr || $httpCode !== 200) {
    error_log("chat_widget_api.php Groq error: HTTP={$httpCode} CURL={$curlErr} RAW=" . substr((string)$resp, 0, 500));
} else {
    $json = json_decode((string)$resp, true);
    $reply = (string)($json['choices'][0]['message']['content'] ?? $reply);

    /* Salveaza in sesiune */
    $history[] = ['role' => 'user',      'content' => $message];
    $history[] = ['role' => 'assistant', 'content' => $reply];
    if (count($history) > 20) $history = array_slice($history, -20);
    file_put_contents($sessFile, json_encode($history, JSON_UNESCAPED_UNICODE));

    /* Detecteaza produse mentionate pentru "sources" */
    foreach ($products as $p) {
        if (!is_array($p)) continue;
        $nm = strtolower((string)($p['name'] ?? ''));
        if ($nm && str_contains(strtolower($reply), $nm)) {
            $sources[] = ['name' => $p['name'], 'price' => $p['price'] ?? '', 'stock' => (int)($p['stock'] ?? 0)];
            if (count($sources) >= 3) break;
        }
    }
}

echo json_encode([
    'reply'      => $reply,
    'session_id' => $session_id,
    'sources'    => $sources,
], JSON_UNESCAPED_UNICODE);
