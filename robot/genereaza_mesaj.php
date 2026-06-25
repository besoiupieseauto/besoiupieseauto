<?php
/**
 * robot/genereaza_mesaj.php — generator comentariu Facebook cu validare OEM + Groq AI.
 */
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/oem_lib.php';

header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input') ?: '{}', true);
if (!is_array($input)) {
    $input = [];
}

$postText = trim((string) ($input['text'] ?? ''));
$oemCodes = isset($input['oem_codes']) && is_array($input['oem_codes'])
    ? array_map('strval', $input['oem_codes'])
    : fb_extract_oem_codes($postText);

if ($postText === '') {
    echo json_encode(['mesaj' => fb_fallback_reply('', []), 'oem_codes' => [], 'oem_found' => false]);
    exit;
}

$oemContext = fb_validate_oem_codes($oemCodes);
$mesaj = fb_generate_reply($postText, $oemContext);

echo json_encode([
    'mesaj' => $mesaj,
    'oem_codes' => $oemCodes,
    'oem_found' => (bool) ($oemContext['any_found'] ?? false),
    'oem_validation' => $oemContext['codes'] ?? [],
], JSON_UNESCAPED_UNICODE);
