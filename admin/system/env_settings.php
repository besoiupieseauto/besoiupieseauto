<?php

declare(strict_types=1);

/**
 * Scriere chei în admin/.env (parțial, chei permise).
 */

/** @return array<string, array{env_key: string, label: string, default: string, presets: array<string, string>, hint: string, allow_custom: bool}> */
function besoiu_env_model_catalog(): array
{
    return [
        'CURSOR_API_KEY' => [
            'env_key' => 'CURSOR_MODEL',
            'label' => 'Model / versiune',
            'default' => 'composer-2.5',
            'presets' => [
                'composer-2.5' => 'Composer 2.5 (recomandat)',
                'composer-1' => 'Composer 1',
                'gpt-4o' => 'GPT-4o',
                'gpt-4.1' => 'GPT-4.1',
                'claude-sonnet-4' => 'Claude Sonnet 4',
                'o3' => 'o3',
            ],
            'allow_custom' => true,
            'hint' => 'ID model Cursor Agent API — vezi cursor.com/docs pentru valori acceptate.',
        ],
        'OPENAI_KEY' => [
            'env_key' => 'OPENAI_MODEL',
            'label' => 'Model OpenAI',
            'default' => 'gpt-4o-mini',
            'presets' => [
                'gpt-4o-mini' => 'GPT-4o mini (recomandat)',
                'gpt-4o' => 'GPT-4o',
                'gpt-4.1' => 'GPT-4.1',
                'gpt-4.1-mini' => 'GPT-4.1 mini',
                'gpt-4.1-nano' => 'GPT-4.1 nano',
                'o3-mini' => 'o3-mini',
            ],
            'allow_custom' => true,
            'hint' => 'Folosit la chat, audit imagini (fallback) și scraper.',
        ],
        'GROQ_KEY' => [
            'env_key' => 'GROQ_MODEL',
            'label' => 'Model Groq',
            'default' => 'llama-3.3-70b-versatile',
            'presets' => [
                'llama-3.3-70b-versatile' => 'Llama 3.3 70B',
                'llama-3.1-8b-instant' => 'Llama 3.1 8B instant',
                'mixtral-8x7b-32768' => 'Mixtral 8x7B',
                'gemma2-9b-it' => 'Gemma 2 9B',
            ],
            'allow_custom' => true,
            'hint' => 'Robot WhatsApp / chat / scraper fallback.',
        ],
        'GEMINI_KEY' => [
            'env_key' => 'GEMINI_MODEL',
            'label' => 'Model Gemini',
            'default' => 'gemini-2.0-flash',
            'presets' => [
                'gemini-2.0-flash' => 'Gemini 2.0 Flash',
                'gemini-2.5-pro-preview-03-25' => 'Gemini 2.5 Pro',
                'gemini-1.5-pro' => 'Gemini 1.5 Pro',
                'gemini-1.5-flash' => 'Gemini 1.5 Flash',
            ],
            'allow_custom' => true,
            'hint' => 'Alternative AI — când modulul folosește Gemini.',
        ],
        'GROK_KEY' => [
            'env_key' => 'GROK_MODEL',
            'label' => 'Model Grok',
            'default' => 'grok-2-1212',
            'presets' => [
                'grok-2-1212' => 'Grok 2',
                'grok-2-vision-1212' => 'Grok 2 Vision',
                'grok-beta' => 'Grok beta',
            ],
            'allow_custom' => true,
            'hint' => 'Alternative AI xAI.',
        ],
    ];
}

function besoiu_env_model_value(string $envKey, string $default = ''): string
{
    $catalog = besoiu_env_model_catalog();
    foreach ($catalog as $cfg) {
        if (($cfg['env_key'] ?? '') === $envKey) {
            $default = $default !== '' ? $default : (string) ($cfg['default'] ?? '');
            break;
        }
    }

    $val = trim((string) ($_ENV[$envKey] ?? $_SERVER[$envKey] ?? getenv($envKey) ?: ''));
    if ($val !== '') {
        return $val;
    }

    $file = besoiu_env_file_path();
    if (is_file($file)) {
        $lines = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (is_array($lines)) {
            foreach ($lines as $line) {
                $line = trim((string) $line);
                if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                    continue;
                }
                [$key, $value] = array_map('trim', explode('=', $line, 2));
                if ($key === $envKey) {
                    $value = trim($value, "\"'");
                    if ($value !== '') {
                        return $value;
                    }
                }
            }
        }
    }

    return $default;
}

/** @return array<string, string> */
function besoiu_env_model_values(): array
{
    $out = [];
    foreach (besoiu_env_model_catalog() as $cfg) {
        $envKey = (string) ($cfg['env_key'] ?? '');
        if ($envKey === '') {
            continue;
        }
        $out[$envKey] = besoiu_env_model_value($envKey, (string) ($cfg['default'] ?? ''));
    }

    return $out;
}

/** @return array<string, array{label: string, hint: string, mirror_to?: string, model?: array<string, mixed>}> */
function besoiu_env_editable_keys(): array
{
    $keys = [
        'RAPIDAPI_AUTOPARTS_KEY' => [
            'label' => 'RapidAPI TecDoc',
            'hint' => 'Cheia x-rapidapi-key RapidAPI — catalog auto-parts (o singură cheie per cont RapidAPI)',
            'mirror_to' => 'RAPIDAPI_TECDOC_KEY',
        ],
        'SCRAPE_DO_TOKEN' => [
            'label' => 'Scrape.do',
            'hint' => 'Token scrape.do pentru Autodoc/eMAG',
        ],
        'OPENAI_KEY' => [
            'label' => 'OpenAI',
            'hint' => 'Chat / audit imagini AI (fallback)',
        ],
        'CURSOR_API_KEY' => [
            'label' => 'Cursor AI',
            'hint' => 'Audit imagini + scraper AI — cheie de la cursor.com/dashboard → API Keys',
        ],
        'GROQ_KEY' => [
            'label' => 'Groq',
            'hint' => 'Robot WhatsApp / chat',
        ],
        'GEMINI_KEY' => [
            'label' => 'Google Gemini',
            'hint' => 'Alternative AI',
        ],
        'GROK_KEY' => [
            'label' => 'Grok (xAI)',
            'hint' => 'Alternative AI',
        ],
    ];

    $models = besoiu_env_model_values();
    foreach (besoiu_env_model_catalog() as $parentKey => $cfg) {
        if (!isset($keys[$parentKey])) {
            continue;
        }
        $envKey = (string) ($cfg['env_key'] ?? '');
        $raw = trim((string) ($models[$envKey] ?? ''));
        $keys[$parentKey]['model'] = [
            'env_key' => $envKey,
            'label' => (string) ($cfg['label'] ?? 'Model'),
            'default' => (string) ($cfg['default'] ?? ''),
            'presets' => is_array($cfg['presets'] ?? null) ? $cfg['presets'] : [],
            'allow_custom' => !empty($cfg['allow_custom']),
            'value' => $raw !== '' ? $raw : (string) ($cfg['default'] ?? ''),
            'hint' => (string) ($cfg['hint'] ?? ''),
        ];
    }

    return $keys;
}

/** @return list<string> */
function besoiu_env_editable_model_env_keys(): array
{
    $keys = [];
    foreach (besoiu_env_model_catalog() as $cfg) {
        $envKey = (string) ($cfg['env_key'] ?? '');
        if ($envKey !== '') {
            $keys[] = $envKey;
        }
    }

    return $keys;
}

function besoiu_env_file_path(): string
{
    return dirname(__DIR__) . '/.env';
}

/** @return array<string, string> */
function besoiu_env_current_values(): array
{
    $out = [];
    foreach (besoiu_env_editable_keys() as $key => $meta) {
        $val = trim((string) ($_ENV[$key] ?? $_SERVER[$key] ?? getenv($key) ?: ''));
        if ($val === '' && !empty($meta['mirror_to'])) {
            $alias = (string) $meta['mirror_to'];
            $val = trim((string) ($_ENV[$alias] ?? $_SERVER[$alias] ?? getenv($alias) ?: ''));
        }
        $out[$key] = $val;
    }

    return $out;
}

/**
 * @param array<string, string> $input
 * @return array{ok: bool, message: string, updated: list<string>}
 */
function besoiu_env_save_keys(array $input): array
{
    $allowed = besoiu_env_editable_keys();
    $file = besoiu_env_file_path();
    $changes = [];

    foreach ($allowed as $key => $meta) {
        if (!array_key_exists($key, $input)) {
            continue;
        }
        $val = trim((string) $input[$key]);
        $val = preg_replace('/[\r\n]+/', '', $val) ?? $val;
        if ($val === '' || str_contains($val, '•')) {
            continue;
        }
        $changes[$key] = $val;
        if (!empty($meta['mirror_to'])) {
            $changes[(string) $meta['mirror_to']] = $val;
        }
    }

    foreach (besoiu_env_editable_model_env_keys() as $modelKey) {
        if (!array_key_exists($modelKey, $input)) {
            continue;
        }
        $val = trim((string) $input[$modelKey]);
        $val = preg_replace('/[\r\n]+/', '', $val) ?? $val;
        if ($val === '') {
            continue;
        }
        $changes[$modelKey] = $val;
    }

    if (isset($changes['OPENAI_MODEL'])) {
        $changes['IMAGE_AUDIT_MODEL'] = $changes['OPENAI_MODEL'];
    }

    if ($changes === []) {
        return ['ok' => false, 'message' => 'Nicio modificare de salvat (chei mascate neschimbate sau modele goale).', 'updated' => []];
    }

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

    $content = implode(PHP_EOL, $lines) . PHP_EOL;
    if (@file_put_contents($file, $content, LOCK_EX) === false) {
        return ['ok' => false, 'message' => 'Nu am putut scrie admin/.env', 'updated' => []];
    }

    foreach ($changes as $key => $val) {
        $_ENV[$key] = $val;
        putenv($key . '=' . $val);
    }

    $secretCount = count(array_intersect(array_keys($changes), array_keys($allowed)));
    $modelCount = count($changes) - $secretCount;
    $parts = [];
    if ($secretCount > 0) {
        $parts[] = $secretCount . ' chei';
    }
    if ($modelCount > 0) {
        $parts[] = $modelCount . ' modele';
    }

    return [
        'ok' => true,
        'message' => 'Setări salvate în admin/.env (' . implode(', ', $parts) . ').',
        'updated' => array_keys($changes),
    ];
}
