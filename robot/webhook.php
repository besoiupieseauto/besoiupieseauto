<?php
declare(strict_types=1);

/**
 * robot/webhook.php
 *
 * Portat 1:1 din C:\laragon\www\aibotpiese.online\webhook.php
 * Modificari fata de original:
 *  - require_once bootstrap.php (in loc de hardcoded keys in company.json)
 *  - $instanceId / $token / $webhookKey vin din .env, nu din company.json
 *  - rest = identic.
 */

require_once __DIR__ . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

const DATA_DIR   = __DIR__ . '/data';
const SESS_DIR   = __DIR__ . '/data/sessions';
const LOG_FILE   = __DIR__ . '/data/webhook.log';
const PHPERR_LOG = __DIR__ . '/data/php_error.log';

const DEDUP_FILE = __DIR__ . '/data/dedup.json';
const DEDUP_TTL  = 120;

ini_set('error_log', PHPERR_LOG);

function ensureDirs(): void {
    if (!is_dir(DATA_DIR)) @mkdir(DATA_DIR, 0775, true);
    if (!is_dir(SESS_DIR)) @mkdir(SESS_DIR, 0775, true);
}
ensureDirs();

function rid(): string {
    return date('YmdHis') . '-' . substr(bin2hex(random_bytes(6)), 0, 12);
}

function redactSecrets(string $s): string {
    $s = preg_replace('/Bearer\s+[A-Za-z0-9\-_\.]+/i', 'Bearer ***', $s);
    $s = preg_replace('/("token"\s*:\s*")[^"]+(")/i', '$1***$2', $s);
    $s = preg_replace('/(token=)[^&\s]+/i', '$1***', $s);
    $s = preg_replace('/("api_key"\s*:\s*")[^"]+(")/i', '$1***$2', $s);
    $s = preg_replace('/(api_key=)[^&\s]+/i', '$1***', $s);
    $s = preg_replace('/(\?key=)[^&\s]+/i', '$1***', $s);
    return $s;
}

function logLine(string $s): void {
    @file_put_contents(LOG_FILE, '[' . date('c') . '] ' . redactSecrets($s) . "\n", FILE_APPEND);
}

function loadJson(string $path): array {
    if (!is_file($path)) return [];
    $raw = file_get_contents($path);
    $j = json_decode((string)$raw, true);
    return is_array($j) ? $j : [];
}

function normalize(string $s): string {
    $s = mb_strtolower($s, 'UTF-8');
    $s = str_replace(["\r","\n","\t"], ' ', $s);
    $s = preg_replace('/\s+/', ' ', $s);
    return trim($s);
}

function compactCode(string $s): string {
    $s = mb_strtoupper(trim($s), 'UTF-8');
    $s = preg_replace('/[\s\-\_]+/', '', $s);
    return $s;
}

function containsLink(string $s): bool {
    return (bool)preg_match('~https?://~i', $s);
}

function isGreeting(string $s): bool {
    $s = normalize($s);
    foreach (['buna', 'bună', 'salut', 'hello', 'alo', 'servus', 'ziua buna', 'bună ziua'] as $g) {
        if ($s === $g || str_contains($s, $g)) return true;
    }
    return false;
}

/* -----------------------------
   Anti-duplicate
------------------------------ */
function dedupKey(array $event): string {
    $data = $event['data'] ?? [];
    $from = (string)($data['from'] ?? '');
    $to   = (string)($data['to'] ?? '');
    $body = (string)($data['body'] ?? '');
    $time = (string)($data['time'] ?? '');
    $type = (string)($event['event_type'] ?? '');
    return sha1($type . '|' . $from . '|' . $to . '|' . $time . '|' . $body);
}

function dedupSeen(string $key): bool {
    $cache = [];
    if (is_file(DEDUP_FILE)) {
        $cache = json_decode((string)file_get_contents(DEDUP_FILE), true);
        if (!is_array($cache)) $cache = [];
    }
    $now = time();
    foreach ($cache as $k => $ts) {
        if (!is_int($ts)) { unset($cache[$k]); continue; }
        if (($now - $ts) > DEDUP_TTL) unset($cache[$k]);
    }
    if (isset($cache[$key])) {
        file_put_contents(DEDUP_FILE, json_encode($cache));
        return true;
    }
    $cache[$key] = $now;
    file_put_contents(DEDUP_FILE, json_encode($cache));
    return false;
}

/* -----------------------------
   Session store
------------------------------ */
function sessionPath(string $from): string {
    $safe = preg_replace('/[^a-zA-Z0-9_\-\.@]/', '_', $from);
    return SESS_DIR . '/' . sha1($safe) . '.json';
}

function loadSession(string $from): array {
    $p = sessionPath($from);
    if (!is_file($p)) {
        return [
            'step' => 'new',
            'last' => time(),
            'history' => [],
            'last_catalog_page' => 1,
            'last_hits' => [],
            'selected_code' => null,
            'pending' => null
        ];
    }
    $j = json_decode((string)file_get_contents($p), true);
    if (!is_array($j)) return ['step'=>'new','last'=>time(),'history'=>[],'last_catalog_page'=>1,'last_hits'=>[],'selected_code'=>null,'pending'=>null];
    $j['history'] = isset($j['history']) && is_array($j['history']) ? $j['history'] : [];
    $j['last_catalog_page'] = (int)($j['last_catalog_page'] ?? 1);
    $j['last_hits'] = isset($j['last_hits']) && is_array($j['last_hits']) ? $j['last_hits'] : [];
    $j['selected_code'] = $j['selected_code'] ?? null;
    $j['pending'] = $j['pending'] ?? null;
    return $j;
}

function saveSession(string $from, array $sess): void {
    $sess['last'] = time();
    if (isset($sess['history']) && is_array($sess['history']) && count($sess['history']) > 12) {
        $sess['history'] = array_slice($sess['history'], -12);
    }
    file_put_contents(sessionPath($from), json_encode($sess, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

/* -----------------------------
   UltraMsg
------------------------------ */
function httpPostForm(string $url, array $fields, array $headers = [], int $timeout = 25): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($fields),
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => $timeout,
    ]);
    $resp = curl_exec($ch);
    $err  = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $json = json_decode((string)$resp, true);
    return ['http_code' => $code, 'error' => $err ?: null, 'raw' => $resp, 'json' => is_array($json) ? $json : null];
}

function sendTextUM(string $instanceId, string $token, string $to, string $text): void {
    $url = "https://api.ultramsg.com/{$instanceId}/messages/chat";
    $r = httpPostForm($url, ['token' => $token, 'to' => $to, 'body' => $text]);
    logLine("RID={$GLOBALS['RID']} OUT_CHAT HTTP={$r['http_code']} ERR=" . ($r['error'] ?? '') . " RAW=" . mb_substr((string)$r['raw'], 0, 400));
}

function sendImageCardUM(string $instanceId, string $token, string $to, string $imageUrl, string $caption): bool {
    $url = "https://api.ultramsg.com/{$instanceId}/messages/image";
    $r = httpPostForm($url, ['token' => $token, 'to' => $to, 'image' => $imageUrl, 'caption' => $caption]);
    logLine("RID={$GLOBALS['RID']} OUT_IMG HTTP={$r['http_code']} ERR=" . ($r['error'] ?? '') . " RAW=" . mb_substr((string)$r['raw'], 0, 400));
    return ($r['http_code'] >= 200 && $r['http_code'] < 300);
}

/* -----------------------------
   Intents / commands
------------------------------ */
function isCatalogCommand(string $s): bool {
    $s = normalize($s);
    foreach (['catalog', 'catalogul', 'lista produse', 'toate produsele', 'produse disponibile', 'arată produse', 'arata produse'] as $k) {
        if (str_contains($s, $k)) return true;
    }
    return false;
}

function parseCatalogPage(string $s): int {
    if (preg_match('/\b(?:catalog|catalogul|lista produse|toate produsele|produse)\s+(\d{1,3})\b/i', $s, $m)) {
        return max(1, (int)$m[1]);
    }
    return 1;
}

function isStockListCommand(string $s): bool {
    $s = normalize($s);
    return str_contains($s, 'in stoc') || str_contains($s, 'stoc') || str_contains($s, 'disponibile');
}

function isYesIntent(string $s): bool {
    $s = normalize($s);
    return in_array($s, ['da', 'ok', 'bine', 'vreau', 'comand', 'cumpar', 'iau'], true)
        || str_contains($s, 'vreau')
        || str_contains($s, 'comand')
        || str_contains($s, 'iau');
}

function isInfoCommand(string $s): bool {
    $s = normalize($s);
    foreach (['info','despre','livrare','retur','return','garantie','garanție','plata','plată','comanda','comandă','contact','program','termeni'] as $k) {
        if (str_contains($s, $k)) return true;
    }
    return false;
}

function buildInfoText(array $cfg, string $body): string {
    $c = $cfg['company'] ?? [];
    $t = $cfg['terms'] ?? [];
    $name = (string)($c['name'] ?? 'Companie Demo');
    $desc = (string)($c['description'] ?? '');
    $demo = (string)($cfg['demo_notice'] ?? 'DEMO');

    $b = normalize($body);
    $out = "ℹ️ *{$name}* ({$demo})\n";
    if ($desc) $out .= "📝 {$desc}\n\n";

    $sections = [];
    if (str_contains($b, 'livrare') && !empty($t['delivery'])) $sections[] = "🚚 Livrare: {$t['delivery']}";
    if ((str_contains($b, 'retur') || str_contains($b, 'return')) && !empty($t['returns'])) $sections[] = "↩️ Retur: {$t['returns']}";
    if ((str_contains($b, 'plata') || str_contains($b, 'plată')) && !empty($t['payment'])) $sections[] = "💳 Plată: {$t['payment']}";
    if (str_contains($b, 'garan') && !empty($t['warranty'])) $sections[] = "🛡️ Garanție: {$t['warranty']}";
    if (str_contains($b, 'contact') && !empty($t['contact'])) $sections[] = "📞 Contact: {$t['contact']}";
    if (str_contains($b, 'program') && !empty($t['hours'])) $sections[] = "🕒 Program: {$t['hours']}";
    if (str_contains($b, 'comand') && !empty($t['order'])) $sections[] = "🧾 Comandă: {$t['order']}";

    if (empty($sections)) {
        if (!empty($t['delivery'])) $sections[] = "🚚 Livrare: {$t['delivery']}";
        if (!empty($t['payment']))  $sections[] = "💳 Plată: {$t['payment']}";
        if (!empty($t['returns']))  $sections[] = "↩️ Retur: {$t['returns']}";
        if (!empty($t['warranty'])) $sections[] = "🛡️ Garanție: {$t['warranty']}";
        if (!empty($t['contact']))  $sections[] = "📞 Contact: {$t['contact']}";
    }

    $out .= implode("\n", $sections);
    $out .= "\n\n✅ Scrie: *catalog* (toate) sau *stoc* (doar în stoc).";
    return trim($out);
}

/* -----------------------------
   Parsing VIN/OEM/year/make-model
------------------------------ */
function extractVin(string $s): ?string {
    if (preg_match('/\b([A-HJ-NPR-Z0-9]{17})\b/i', $s, $m)) return strtoupper($m[1]);
    return null;
}

function extractOemOrCode(string $s): ?string {
    if (preg_match('/\b(?:oem|cod)\s*[:#]?\s*([A-Za-z0-9\-\_]{4,25})\b/i', $s, $m)) return (string)$m[1];
    $norm = trim(preg_replace('/\s+/', ' ', $s));
    if (preg_match('/^\s*([A-Za-z0-9\-\_]{6,25})\s*$/', $norm, $m)) return (string)$m[1];
    return null;
}

function extractYear(string $s): ?int {
    if (preg_match('/\b(19[7-9]\d|20[0-3]\d)\b/', $s, $m)) return (int)$m[1];
    return null;
}

function extractMakeModelLoose(string $s): array {
    $s = preg_replace('/[^A-Za-zĂÂÎȘȚăâîșț0-9 ]/u', ' ', $s);
    $s = trim(preg_replace('/\s+/', ' ', $s));
    $parts = explode(' ', $s);

    $words = [];
    foreach ($parts as $p) {
        if (mb_strlen($p, 'UTF-8') < 2) continue;
        if (preg_match('/^\d+$/', $p)) continue;
        $words[] = $p;
    }
    return ['make' => $words[0] ?? '', 'model' => $words[1] ?? ''];
}

/* -----------------------------
   Product matching
------------------------------ */
function matchByVin(array $p, string $vin): bool {
    $prefixes = $p['vin_prefixes'] ?? [];
    if (!is_array($prefixes) || $vin === '') return false;
    foreach ($prefixes as $pr) {
        $pr = strtoupper((string)$pr);
        if ($pr !== '' && str_starts_with($vin, $pr)) return true;
    }
    return false;
}

function matchByOemOrCode(array $p, string $code): bool {
    $codeC = compactCode($code);
    if ($codeC === '') return false;
    $oem = compactCode((string)($p['oem'] ?? ''));
    $pc  = compactCode((string)($p['code'] ?? ''));
    if ($oem !== '' && $oem === $codeC) return true;
    if ($pc  !== '' && $pc  === $codeC) return true;
    if ($oem !== '' && str_contains($oem, $codeC)) return true;
    if ($pc  !== '' && str_contains($pc,  $codeC)) return true;
    return false;
}

function searchProductsAdvanced(array $products, array $q, int $limit = 6): array {
    $hits = [];
    foreach ($products as $p) {
        if (!is_array($p)) continue;

        $ok = false;

        if (!empty($q['oem_code']) && matchByOemOrCode($p, (string)$q['oem_code'])) $ok = true;
        if (!$ok && !empty($q['vin']) && matchByVin($p, (string)$q['vin'])) $ok = true;

        if (!$ok && !empty($q['text'])) {
            $hay = normalize(
                (string)($p['name'] ?? '') . ' ' .
                (string)($p['brand'] ?? '') . ' ' .
                (string)($p['description'] ?? '') . ' ' .
                (string)($p['oem'] ?? '') . ' ' .
                (string)($p['code'] ?? '')
            );
            $txt = normalize((string)$q['text']);
            $tokens = array_filter(explode(' ', $txt), fn($t) => mb_strlen($t,'UTF-8') >= 3);
            foreach ($tokens as $t) {
                if (str_contains($hay, $t)) { $ok = true; break; }
            }
        }

        if ($ok && !empty($q['only_in_stock'])) {
            $stock = (int)($p['stock'] ?? 0);
            if ($stock <= 0) $ok = false;
        }

        if ($ok) $hits[] = $p;
        if (count($hits) >= $limit) break;
    }
    return $hits;
}

/* -----------------------------
   Catalog / stock listing
------------------------------ */
function sendOneProductCard(array $p, string $instanceId, string $token, string $to, int $index = 0): void {
    $title = (string)($p['name'] ?? 'Produs');
    $image = (string)($p['image'] ?? '');
    $price = (string)($p['price'] ?? '');
    $code  = (string)($p['code'] ?? '');
    $oem   = (string)($p['oem'] ?? '');
    $desc  = (string)($p['description'] ?? '');
    $stock = (int)($p['stock'] ?? 0);

    $nr = $index > 0 ? "({$index}) " : "";

    $cap = "🧩 {$nr}*{$title}*";
    if ($price !== '') $cap .= "\n💰 Preț: {$price}";
    if ($code !== '')  $cap .= "\n🔢 Cod: {$code}";
    if ($oem !== '')   $cap .= "\n🧾 OEM: {$oem}";
    $cap .= "\n📦 Stoc: " . ($stock > 0 ? $stock : "0");
    if ($desc !== '')  $cap .= "\n📝 {$desc}";
    $cap .= "\n\n👉 Răspunde cu *{$index}* ca să alegi produsul, sau trimite *VIN* pentru confirmare.";

    sendTextUM($instanceId, $token, $to, $cap);

    if ($image !== '' && preg_match('~^https?://~i', $image)) {
        $imgOk = sendImageCardUM($instanceId, $token, $to, $image, "📷 Imagine: {$title} (DEMO)");
        if (!$imgOk) {
            // do nothing – text card already sent
        }
    }
}

function sendCatalog(array $products, string $instanceId, string $token, string $to, int $page = 1, int $perPage = 6, bool $onlyStock = false): void {
    $list = $products;

    if ($onlyStock) {
        $list = array_values(array_filter($products, fn($p) => is_array($p) && (int)($p['stock'] ?? 0) > 0));
    }

    $total = count($list);
    if ($total === 0) {
        sendTextUM($instanceId, $token, $to, $onlyStock
            ? "⚠️ Nu sunt produse *în stoc* în acest moment (DEMO)."
            : "⚠️ Nu există produse în *products.json*.");
        return;
    }

    $page = max(1, $page);
    $pages = (int)ceil($total / $perPage);
    if ($page > $pages) $page = $pages;

    $start = ($page - 1) * $perPage;
    $slice = array_slice($list, $start, $perPage);

    $title = $onlyStock ? "📦 Produse *în stoc* (DEMO)" : "📦 Catalog produse (DEMO)";
    sendTextUM($instanceId, $token, $to,
        "{$title} — pagina {$page}/{$pages}\n" .
        "Scrie: *" . ($onlyStock ? "stoc " : "catalog ") . ($page < $pages ? ($page+1) : $pages) . "* pentru pagina următoare.\n" .
        "Caută: *OEM 52119-0D904* / *cod X* / *VIN*."
    );

    $i = 1;
    foreach ($slice as $p) {
        sendOneProductCard($p, $instanceId, $token, $to, $i);
        $i++;
        if ($i > $perPage) break;
    }
}

/* -----------------------------
   Main
------------------------------ */
$cfg = loadJson(__DIR__ . '/company.json');
$products = loadJson(__DIR__ . '/products.json');

// --- MODIFICARE 1:1 fata de aibotpiese: secrete din .env, nu din company.json ---
$instanceId = (string) env('ULTRAMSG_INSTANCE', '');
$token      = (string) env('ULTRAMSG_TOKEN', '');
$webhookKey = (string) env('WEBHOOK_KEY', '');

if ($instanceId === '' || $token === '') {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Missing UltraMsg instance_id/token in robot/.env']);
    exit;
}

if ($webhookKey !== '') {
    $key = (string)($_GET['key'] ?? '');
    if (!hash_equals($webhookKey, $key)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Forbidden']);
        exit;
    }
}

if (isset($_GET['debug']) && $_GET['debug'] === '1') {
    header('Content-Type: text/plain; charset=utf-8');
    if (!is_file(LOG_FILE)) { echo "No log file.\n"; exit; }
    $lines = file(LOG_FILE, FILE_IGNORE_NEW_LINES);
    $last = array_slice($lines ?: [], -300);
    echo implode("\n", $last);
    exit;
}

$raw = file_get_contents('php://input') ?: '';
$event = json_decode($raw, true);

if (!is_array($event)) {
    echo json_encode(['ok' => true, 'note' => 'No JSON payload']);
    exit;
}

$RID = rid();
$GLOBALS['RID'] = $RID;
logLine("RID={$RID} IN_RAW=" . mb_substr(json_encode($event, JSON_UNESCAPED_UNICODE), 0, 2200));

$dk = dedupKey($event);
if (dedupSeen($dk)) {
    logLine("RID={$RID} DEDUP skip=1");
    echo json_encode(['ok' => true, 'dedup' => true]);
    exit;
}

$eventType = (string)($event['event_type'] ?? '');
$data      = (array)($event['data'] ?? []);

if ($eventType !== 'message_received') {
    echo json_encode(['ok' => true, 'ignored' => $eventType]);
    exit;
}

if ((bool)($data['fromMe'] ?? false)) {
    echo json_encode(['ok' => true, 'ignored' => 'fromMe']);
    exit;
}

$from = (string)($data['from'] ?? '');
$body = trim((string)($data['body'] ?? ''));

if ($from === '' || $body === '') {
    echo json_encode(['ok' => true, 'ignored' => 'empty from/body']);
    exit;
}

logLine("RID={$RID} FROM={$from} BODY=" . mb_substr($body, 0, 400));

$sess = loadSession($from);
$txtN = normalize($body);

/**
 * 0) Handle selection numbers: "1" "2" "3"
 */
if (preg_match('/^\s*(\d{1,2})\s*$/', $txtN, $m) && !empty($sess['last_hits'])) {
    $idx = (int)$m[1];
    if ($idx >= 1 && $idx <= count($sess['last_hits'])) {
        $chosen = $sess['last_hits'][$idx - 1] ?? null;
        if (is_array($chosen)) {
            $sess['selected_code'] = $chosen['code'] ?? ($chosen['oem'] ?? null);
            $sess['pending'] = 'confirm_vin';
            saveSession($from, $sess);

            sendTextUM($instanceId, $token, $from,
                "✅ Ai ales: *" . ($chosen['name'] ?? 'Produs') . "*.\n" .
                "Trimite te rog *VIN* (17 caractere) + poziția (față/spate, st/dr) ca să confirm compatibilitatea și facem comanda (DEMO)."
            );
            echo json_encode(['ok' => true, 'route' => 'select']);
            exit;
        }
    }
}

/**
 * 1) Greetings (do not loop)
 */
if (isGreeting($body) && ($sess['step'] ?? 'new') === 'new') {
    $sess['step'] = 'active';
    saveSession($from, $sess);
    sendTextUM($instanceId, $token, $from,
        "⚠️ *DEMO*: Bună ziua! Scrie:\n" .
        "• *catalog* (toate produsele)\n" .
        "• *stoc* (doar în stoc)\n" .
        "• *info* (livrare/retur/garanție)\n" .
        "Sau trimite: *OEM/Cod* / *VIN* / piesa + mașina."
    );
    echo json_encode(['ok' => true, 'route' => 'greet']);
    exit;
}

/**
 * 2) INFO
 */
if (isInfoCommand($body)) {
    $txt = buildInfoText($cfg, $body);
    sendTextUM($instanceId, $token, $from, $txt);
    $sess['history'][] = ['role'=>'user','content'=>$body];
    $sess['history'][] = ['role'=>'assistant','content'=>mb_substr($txt,0,800)];
    saveSession($from, $sess);
    echo json_encode(['ok' => true, 'route' => 'info']);
    exit;
}

/**
 * 3) STOCK LIST
 */
if (isStockListCommand($body) && !isCatalogCommand($body)) {
    $page = parseCatalogPage($body);
    $sess['last_catalog_page'] = $page;
    saveSession($from, $sess);
    sendCatalog($products, $instanceId, $token, $from, $page, 6, true);
    echo json_encode(['ok' => true, 'route' => 'stock', 'page' => $page]);
    exit;
}

/**
 * 4) CATALOG
 */
if (isCatalogCommand($body)) {
    $page = parseCatalogPage($body);
    $sess['last_catalog_page'] = $page;
    saveSession($from, $sess);
    sendCatalog($products, $instanceId, $token, $from, $page, 6, false);
    echo json_encode(['ok' => true, 'route' => 'catalog', 'page' => $page]);
    exit;
}

/**
 * 5) If user said YES after hits -> continue
 */
if (isYesIntent($body) && !empty($sess['last_hits'])) {
    $sess['pending'] = 'confirm_vin';
    saveSession($from, $sess);

    $chosenName = '';
    if (!empty($sess['selected_code'])) $chosenName = " (produs ales: {$sess['selected_code']})";

    sendTextUM($instanceId, $token, $from,
        "Perfect{$chosenName} ✅\n" .
        "Trimite te rog *VIN* (17 caractere) + poziția (față/spate, st/dr) ca să confirm compatibilitatea și facem comanda (DEMO)."
    );
    echo json_encode(['ok' => true, 'route' => 'yes_continue']);
    exit;
}

/**
 * 6) Search OEM/VIN/text
 */
$vin = extractVin($body);
$oemOrCode = extractOemOrCode($body);
$onlyInStock = str_contains($txtN, 'in stoc');

$q = [
    'vin' => $vin,
    'oem_code' => $oemOrCode,
    'text' => $body,
    'only_in_stock' => $onlyInStock
];

$hits = searchProductsAdvanced($products, $q, 6);

if (!empty($hits)) {
    $sess['last_hits'] = array_map(function($p) {
        return [
            'name' => $p['name'] ?? '',
            'code' => $p['code'] ?? '',
            'oem'  => $p['oem'] ?? '',
            'stock'=> (int)($p['stock'] ?? 0),
        ];
    }, $hits);
    $sess['selected_code'] = $sess['last_hits'][0]['code'] ?: ($sess['last_hits'][0]['oem'] ?? null);
    $sess['pending'] = 'confirm_vin';
    saveSession($from, $sess);

    $prefix = "✅ Am găsit " . count($hits) . " produs(e) (DEMO).";
    if ($oemOrCode) $prefix .= " După cod/OEM: *{$oemOrCode}*.";
    if ($vin) $prefix .= " După VIN: *{$vin}*.";
    $prefix .= "\n\n👉 Răspunde cu *1/2/3* ca să alegi produsul sau scrie *Da* și trimite VIN pentru confirmare.";

    sendTextUM($instanceId, $token, $from, $prefix);

    $i = 1;
    foreach ($hits as $p) {
        sendOneProductCard($p, $instanceId, $token, $from, $i);
        $i++;
        if ($i > 6) break;
    }

    echo json_encode(['ok' => true, 'route' => 'hits', 'count' => count($hits)]);
    exit;
}

/**
 * 7) Final fallback
 */
sendTextUM($instanceId, $token, $from,
    "⚠️ *DEMO*: Nu am găsit produs în catalog.\n" .
    "Trimite te rog:\n" .
    "• *OEM/Cod* (ex: OEM 52119-0D904)\n" .
    "• sau *VIN* (17 caractere)\n" .
    "• sau scrie *catalog* / *stoc* / *info*."
);

echo json_encode(['ok' => true, 'route' => 'fallback']);
