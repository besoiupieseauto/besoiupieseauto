<?php

declare(strict_types=1);

/**
 * Raportare centralizată erori AI/API — system_errors + evenimente AI Agent + health JSON.
 */

/** @return array<string, mixed> */
function ai_api_health_path(): string
{
    return dirname(__DIR__) . '/robot/data/ai_context/ai_api_health.json';
}

/** @return array<string, mixed> */
function ai_api_health_read(): array
{
    $path = ai_api_health_path();
    if (!is_file($path)) {
        return [];
    }
    $json = json_decode((string) file_get_contents($path), true);

    return is_array($json) ? $json : [];
}

/** @param array<string, mixed> $patch */
function ai_api_health_write(array $patch): void
{
    $dir = dirname(ai_api_health_path());
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $state = array_merge(ai_api_health_read(), $patch, ['updated_at' => date('c')]);
    @file_put_contents(
        ai_api_health_path(),
        json_encode($state, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        LOCK_EX
    );
}

function ai_api_env_get(string $key): string
{
    static $loaded = false;
    if (!$loaded) {
        $path = BESOIU_BACKEND . '/system/env_settings.php';
        if (is_file($path)) {
            require_once $path;
        }
        $loaded = true;
    }

    return function_exists('besoiu_env_get') ? besoiu_env_get($key) : trim((string) (getenv($key) ?: ''));
}

function ai_api_key_configured(): bool
{
    $llmConfig = BESOIU_LIB . '/Scraper/ScraperLlmConfig.php';
    if (is_file($llmConfig)) {
        require_once $llmConfig;
        if (\ScraperLlmConfig::hasAnyKey()) {
            return true;
        }
    }

    if (ai_api_env_get('GROQ_KEY') !== '') {
        return true;
    }

    return ai_api_env_get('OPENAI_KEY') !== '';
}

/** @return 'groq'|'openai'|'' */
function ai_api_preferred_provider(): string
{
    if (ai_api_env_get('GROQ_KEY') !== '') {
        return 'groq';
    }

    return ai_api_env_get('OPENAI_KEY') !== '' ? 'openai' : '';
}

function ai_api_human_message(string $raw, int $httpCode = 0): string
{
    $msg = trim($raw);
    if ($httpCode === 401 || $httpCode === 403) {
        return 'Cheie API invalidă sau lipsă (HTTP ' . $httpCode . ')';
    }
    if ($httpCode === 429) {
        return 'Limită tokeni/API depășită — verifică Groq/OpenAI (429)';
    }
    if ($httpCode === 402) {
        return 'Cont API fără credit / plată necesară (402)';
    }
    if ($httpCode >= 500) {
        return 'Server AI indisponibil (HTTP ' . $httpCode . ')';
    }
    if ($msg === '') {
        return $httpCode > 0 ? 'Eroare API AI (HTTP ' . $httpCode . ')' : 'Eroare API AI';
    }
    $lower = strtolower($msg);
    if (str_contains($lower, 'insufficient') || str_contains($lower, 'quota') || str_contains($lower, 'rate limit')) {
        return 'Limită tokeni/API: ' . mb_substr($msg, 0, 180);
    }

    return mb_substr($msg, 0, 220);
}

/** @param array<string, mixed> $context */
function ai_api_report_error(string $source, string $message, array $context = [], int $httpCode = 0): void
{
    $message = ai_api_human_message($message, $httpCode);
    if ($message === '') {
        return;
    }

    $hash = sha1($source . '|' . $message . '|' . $httpCode);
    $state = ai_api_health_read();
    $lastHash = (string) ($state['last_error_hash'] ?? '');
    $lastAt = (string) ($state['last_error_at'] ?? '');
    if ($lastHash === $hash && $lastAt !== '' && (time() - (int) strtotime($lastAt)) < 300) {
        return;
    }

    ai_api_health_write([
        'last_error' => $message,
        'last_error_at' => date('c'),
        'last_error_source' => $source,
        'last_error_http' => $httpCode,
        'last_error_hash' => $hash,
        'key_configured' => ai_api_key_configured(),
    ]);

    if (is_file(__DIR__ . '/system_errors.php')) {
        require_once __DIR__ . '/system_errors.php';
        besoiu_system_error_log('error', 'ai', '[' . $source . '] ' . $message, array_merge($context, [
            'http_code' => $httpCode,
            'source' => $source,
        ]));
    }

    if (is_file(__DIR__ . '/ai_action_events.php')) {
        require_once __DIR__ . '/ai_action_events.php';
        ai_action_event_record('system', 'ai_api_error', $source . ': ' . $message, [
            'http_code' => $httpCode,
            'source' => $source,
        ]);
    }
}

function ai_api_clear_last_error(): void
{
    ai_api_health_write([
        'last_error' => '',
        'last_error_at' => '',
        'last_error_source' => '',
        'last_error_http' => 0,
        'last_error_hash' => '',
    ]);
}

/** @return array<string, mixed> */
function ai_api_parse_response_error(string $raw, int $httpCode): array
{
    $detail = '';
    if ($raw !== '') {
        $json = json_decode($raw, true);
        if (is_array($json)) {
            $detail = trim((string) ($json['error']['message'] ?? $json['message'] ?? ''));
        }
        if ($detail === '') {
            $detail = mb_substr(trim($raw), 0, 200);
        }
    }

    return [
        'message' => ai_api_human_message($detail, $httpCode),
        'http_code' => $httpCode,
    ];
}
