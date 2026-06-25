<?php
/**
 * robot/oem_lib.php — extragere/validare coduri OEM pentru robot Facebook (și genereaza_mesaj).
 */
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function fb_compact_code(string $s): string
{
    $s = mb_strtoupper(trim($s), 'UTF-8');
    return (string) preg_replace('/[\s\-\_\.]+/', '', $s);
}

/** @return string[] */
function fb_extract_oem_codes(string $text): array
{
    $text = trim($text);
    if ($text === '') {
        return [];
    }

    $found = [];

    if (preg_match_all('/\b(?:oem|om|cod)\s*[:#]?\s*([A-Za-z0-9\-\_\.]{4,25})\b/iu', $text, $m)) {
        foreach ($m[1] as $c) {
            $found[] = (string) $c;
        }
    }

    if (preg_match_all('/\b([A-Z]{0,4}[0-9][A-Z0-9\-\_]{3,18})\b/u', mb_strtoupper($text, 'UTF-8'), $m)) {
        $stopWords = ['PENTRU', 'FRANA', 'PLACUTE', 'PIESA', 'CAUT', 'AVEȚI', 'AVETI', 'MODEL', 'MARCA'];
        foreach ($m[1] as $c) {
            $compact = fb_compact_code($c);
            if (strlen($compact) < 5 || in_array($compact, $stopWords, true)) {
                continue;
            }
            if (preg_match('/[0-9]/', $compact)) {
                $found[] = (string) $c;
            }
        }
    }

    $norm = trim(preg_replace('/\s+/', ' ', $text) ?? '');
    if (preg_match('/^\s*([A-Za-z0-9\-\_]{6,25})\s*$/', $norm, $m)) {
        $found[] = (string) $m[1];
    }

    $uniq = [];
    foreach ($found as $code) {
        $k = fb_compact_code($code);
        if ($k === '' || strlen($k) < 4) {
            continue;
        }
        $uniq[$k] = $code;
    }

    return array_values($uniq);
}

function fb_is_parts_request(string $text): bool
{
    $t = mb_strtolower($text, 'UTF-8');
    if ($t === '') {
        return false;
    }
    if (str_contains($t, '?')) {
        return true;
    }
    foreach (['caut', 'cauta', 'căut', 'am nevoie', 'aveți', 'aveti', 'cine are', 'piesa', 'piesă', 'oem', 'cod ', 'disponibil', 'stoc'] as $kw) {
        if (str_contains($t, $kw)) {
            return true;
        }
    }
    return fb_extract_oem_codes($text) !== [];
}

function fb_db_pdo(): ?PDO
{
    static $pdo = null;
    static $tried = false;
    if ($tried) {
        return $pdo;
    }
    $tried = true;

    $host = (string) env('DB_HOST', 'localhost');
    $name = (string) env('DB_NAME', '');
    $user = (string) env('DB_USER', '');
    $pass = (string) env('DB_PASS', '');
    if ($name === '' || $user === '') {
        return null;
    }

    try {
        $pdo = new PDO(
            "mysql:host={$host};dbname={$name};charset=utf8mb4",
            $user,
            $pass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
    } catch (Throwable $e) {
        $pdo = null;
    }

    return $pdo;
}

/** @return array<int, array<string, mixed>> */
function fb_search_db_by_oem(string $code, int $limit = 5): array
{
    $pdo = fb_db_pdo();
    if ($pdo === null) {
        return [];
    }

    $compact = fb_compact_code($code);
    if ($compact === '') {
        return [];
    }

    $like = '%' . $compact . '%';
    $sql = "SELECT pName, pCode, pOem, pPrice, pStock, pBrand
            FROM produse
            WHERE REPLACE(REPLACE(REPLACE(UPPER(COALESCE(pOem,'')),' ',''),'-',''),'.','') LIKE :q
               OR REPLACE(REPLACE(REPLACE(UPPER(COALESCE(pCode,'')),' ',''),'-',''),'.','') LIKE :q2
               OR REPLACE(REPLACE(REPLACE(UPPER(COALESCE(pCodeNorm,'')),' ',''),'-',''),'.','') LIKE :q3
            ORDER BY (pStock > 0) DESC, id DESC
            LIMIT " . (int) $limit;

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['q' => $like, 'q2' => $like, 'q3' => $like]);
        return $stmt->fetchAll() ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

/** @return array<int, array<string, mixed>> */
function fb_search_products_json(string $code): array
{
    $path = __DIR__ . '/products.json';
    if (!is_file($path)) {
        return [];
    }
    $raw = file_get_contents($path);
    $products = json_decode($raw ?: '[]', true);
    if (!is_array($products)) {
        return [];
    }

    $hits = [];
    $compact = fb_compact_code($code);
    foreach ($products as $p) {
        if (!is_array($p)) {
            continue;
        }
        $oem = fb_compact_code((string) ($p['oem'] ?? ''));
        $pc  = fb_compact_code((string) ($p['code'] ?? ''));
        if ($oem !== '' && ($oem === $compact || str_contains($oem, $compact))) {
            $hits[] = $p;
            continue;
        }
        if ($pc !== '' && ($pc === $compact || str_contains($pc, $compact))) {
            $hits[] = $p;
        }
    }

    return array_slice($hits, 0, 5);
}

/** @param string[] $codes */
function fb_validate_oem_codes(array $codes): array
{
    $validated = [];
    $allHits = [];

    foreach ($codes as $code) {
        $dbHits = fb_search_db_by_oem($code, 3);
        $jsonHits = fb_search_products_json($code);
        $hits = array_merge($dbHits, $jsonHits);
        $validated[] = [
            'code' => $code,
            'found' => $hits !== [],
            'hits_count' => count($hits),
            'hits' => array_slice($hits, 0, 3),
        ];
        $allHits = array_merge($allHits, $hits);
    }

    return [
        'codes' => $validated,
        'any_found' => array_reduce($validated, static fn ($c, $v) => $c || ($v['found'] ?? false), false),
        'hits' => array_slice($allHits, 0, 5),
    ];
}

/**
 * @param array<string, mixed> $oemContext rezultat fb_validate_oem_codes
 */
function fb_generate_reply(string $postText, array $oemContext = []): string
{
    $GROQ_API_KEY = (string) env('GROQ_KEY', '');
    $GROQ_MODEL   = (string) env('GROQ_MODEL', 'llama-3.3-70b-versatile');

    if ($GROQ_API_KEY === '') {
        return fb_fallback_reply($postText, $oemContext);
    }

    $oemLines = [];
    foreach ($oemContext['codes'] ?? [] as $row) {
        $flag = !empty($row['found']) ? 'DA in stoc/catalog' : 'nu gasit in catalog';
        $oemLines[] = ($row['code'] ?? '') . ' → ' . $flag;
    }
    $oemBlock = $oemLines !== [] ? implode('; ', $oemLines) : 'niciun cod OEM detectat';

    $prompt = "Ești consultant Besoiu Piese Auto (dezmembrări + piese noi).
Analizează postarea din grup Facebook: \"{$postText}\".
Validare coduri OEM în catalog: {$oemBlock}.
Scrie UN comentariu scurt (maxim 2 propoziții) în română, prietenos.
Dacă OEM-ul e în catalog: confirmă disponibilitatea și invită la mesaj privat cu codul.
Dacă nu e în catalog: spune că verifici la furnizori dacă îți lasă codul OEM exact.
Menționează Besoiu Piese Auto. Fără hashtag-uri.";

    $ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $GROQ_API_KEY,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'model' => $GROQ_MODEL,
            'messages' => [['role' => 'user', 'content' => $prompt]],
            'temperature' => 0.6,
        ]),
    ]);

    $response = curl_exec($ch);
    curl_close($ch);
    $data = json_decode((string) $response, true);
    $ai = trim((string) ($data['choices'][0]['message']['content'] ?? ''));

    return $ai !== '' ? $ai : fb_fallback_reply($postText, $oemContext);
}

/** @param array<string, mixed> $oemContext */
function fb_fallback_reply(string $postText, array $oemContext = []): string
{
    if (!empty($oemContext['any_found'])) {
        return 'Bună ziua! La Besoiu Piese Auto avem această piesă în catalog. Trimiteți codul OEM în mesaj privat și vă confirmăm prețul și livrarea.';
    }
    if (fb_extract_oem_codes($postText) !== []) {
        return 'Bună ziua! Besoiu Piese Auto — verificăm codul OEM la furnizori. Lăsați codul exact în privat și revenim cu disponibilitate și preț.';
    }
    return 'Bună ziua! Besoiu Piese Auto vă poate ajuta cu piesa cerută. Spuneți-ne codul OEM sau marca/model/motorizare în mesaj privat.';
}
