<?php

declare(strict_types=1);

/**
 * Citire/scriere chei permise în robot/.env (boți WhatsApp, FB, etc.).
 */

function besoiu_robot_env_file_path(): string
{
    return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'robot' . DIRECTORY_SEPARATOR . '.env';
}

/** @return array<string, string> */
function besoiu_robot_env_whatsapp_keys(): array
{
    return [
        'ULTRAMSG_INSTANCE' => 'Instance UltraMsg',
        'ULTRAMSG_TOKEN'    => 'Token UltraMsg',
        'WEBHOOK_KEY'       => 'Cheie webhook Besoiu',
    ];
}

/** @param list<string> $keys @return array<string, string> */
function besoiu_robot_env_read_keys(array $keys): array
{
    $out = array_fill_keys($keys, '');
    $file = besoiu_robot_env_file_path();
    if (!is_file($file)) {
        return $out;
    }

    $lines = @file($file, FILE_IGNORE_NEW_LINES);
    if (!is_array($lines)) {
        return $out;
    }

    foreach ($lines as $line) {
        $trim = ltrim((string) $line);
        if ($trim === '' || $trim[0] === '#' || $trim[0] === ';') {
            continue;
        }
        $eq = strpos($line, '=');
        if ($eq === false) {
            continue;
        }
        $key = trim(substr($line, 0, $eq));
        if (!array_key_exists($key, $out)) {
            continue;
        }
        $val = trim(substr($line, $eq + 1));
        if (strlen($val) >= 2) {
            $first = $val[0];
            $last = $val[strlen($val) - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $val = substr($val, 1, -1);
            }
        }
        $out[$key] = $val;
    }

    return $out;
}

/**
 * @param array<string, string> $input
 * @return array{ok: bool, message: string, updated: list<string>}
 */
function besoiu_robot_env_save_keys(array $input, array $allowedKeys): array
{
    $changes = [];
    foreach ($allowedKeys as $key => $label) {
        if (!array_key_exists($key, $input)) {
            continue;
        }
        $val = trim((string) $input[$key]);
        $val = preg_replace('/[\r\n]+/', '', $val) ?? $val;
        if ($val === '' || str_contains($val, '•')) {
            continue;
        }
        $changes[$key] = $val;
    }

    if ($changes === []) {
        return ['ok' => false, 'message' => 'Nicio modificare de salvat.', 'updated' => []];
    }

    $file = besoiu_robot_env_file_path();
    $lines = is_file($file) ? @file($file, FILE_IGNORE_NEW_LINES) : [];
    if (!is_array($lines)) {
        $lines = [];
    }

    $seen = [];
    foreach ($lines as $i => $line) {
        $trim = ltrim((string) $line);
        if ($trim === '' || $trim[0] === '#' || $trim[0] === ';') {
            continue;
        }
        $eq = strpos($line, '=');
        if ($eq === false) {
            continue;
        }
        $key = trim(substr($line, 0, $eq));
        if (array_key_exists($key, $changes)) {
            $lines[$i] = $key . '=' . $changes[$key];
            $seen[$key] = true;
        }
    }

    foreach ($changes as $key => $val) {
        if (!isset($seen[$key])) {
            $lines[] = $key . '=' . $val;
        }
    }

    $dir = dirname($file);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    if (@file_put_contents($file, implode(PHP_EOL, $lines) . PHP_EOL, LOCK_EX) === false) {
        return ['ok' => false, 'message' => 'Nu am putut scrie robot/.env', 'updated' => []];
    }

    return [
        'ok' => true,
        'message' => 'Setări salvate în robot/.env.',
        'updated' => array_keys($changes),
    ];
}

/** @return array{ok: bool, message: string, response?: mixed} */
function besoiu_whatsapp_send_message(string $instance, string $token, string $phone, string $body): array
{
    $instance = trim($instance);
    $token = trim($token);
    $body = trim($body);
    $phone = preg_replace('/[^0-9]/', '', $phone) ?? '';

    if ($instance === '' || $token === '') {
        return ['ok' => false, 'message' => 'Lipsește ULTRAMSG_INSTANCE sau ULTRAMSG_TOKEN.'];
    }
    if ($phone === '') {
        return ['ok' => false, 'message' => 'Număr telefon invalid.'];
    }
    if ($body === '') {
        return ['ok' => false, 'message' => 'Mesajul este gol.'];
    }

    $to = $phone . '@c.us';
    $url = 'https://api.ultramsg.com/' . rawurlencode($instance) . '/messages/chat';
    $ch = curl_init($url);
    if ($ch === false) {
        return ['ok' => false, 'message' => 'curl_init eșuat'];
    }

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'token' => $token,
            'to'    => $to,
            'body'  => $body,
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_CONNECTTIMEOUT => 8,
    ]);

    $raw = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if (!is_string($raw)) {
        return ['ok' => false, 'message' => 'Eroare rețea: ' . ($err ?: 'necunoscută')];
    }

    $json = json_decode($raw, true);
    if ($code >= 200 && $code < 300) {
        return ['ok' => true, 'message' => 'Mesaj trimis către +' . $phone, 'response' => $json];
    }

    $apiMsg = is_array($json) ? (string) ($json['error'] ?? $json['message'] ?? $raw) : $raw;

    return ['ok' => false, 'message' => 'UltraMsg: ' . mb_substr($apiMsg, 0, 200), 'response' => $json];
}

function besoiu_robot_mask_secret(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }
    if (strlen($value) <= 6) {
        return '••••••';
    }

    return substr($value, 0, 3) . '•••' . substr($value, -3);
}
