<?php

declare(strict_types=1);

/**
 * Scriere chei în admin/.env (parțial, chei permise).
 */

/** @return array<string, array{env_key: string, label: string, default: string, presets: array<string, string>, hint: string, allow_custom: bool}> */
function besoiu_env_model_catalog(): array
{
    return [
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
            'hint' => 'Fallback Metro LLM după Ollama — chat, asistent, supervizor.',
        ],
        'GEMINI_KEY' => [
            'env_key' => 'GEMINI_MODEL',
            'label' => 'Model Gemini',
            'default' => 'gemini-2.5-flash',
            'presets' => [
                'gemini-2.5-flash' => 'Gemini 2.5 Flash (recomandat free)',
                'gemini-2.5-flash-lite' => 'Gemini 2.5 Flash-Lite',
                'gemini-1.5-flash' => 'Gemini 1.5 Flash',
                'gemini-2.0-flash' => 'Gemini 2.0 Flash (cotă separată)',
            ],
            'allow_custom' => true,
            'hint' => 'Dacă vezi „quota exceeded” pe 2.0 Flash, treci la 2.5 Flash — cotă free separată.',
        ],
        'OPENROUTER_API_KEY' => [
            'env_key' => 'OPENROUTER_MODEL',
            'label' => 'Model OpenRouter',
            'default' => 'google/gemma-4-26b-a4b-it:free',
            'presets' => [
                'google/gemma-4-26b-a4b-it:free' => 'Gemma 4 26B (free, recomandat)',
                'google/gemma-4-31b-it:free' => 'Gemma 4 31B (free)',
                'meta-llama/llama-3.3-70b-instruct:free' => 'Llama 3.3 70B (free, uneori limitat)',
                'nvidia/nemotron-nano-9b-v2:free' => 'Nemotron Nano 9B (free)',
                'qwen/qwen3-coder:free' => 'Qwen3 Coder (free)',
            ],
            'allow_custom' => true,
            'hint' => 'Modele :free pe openrouter.ai. Gemma 2 și Llama 3.1 8B au fost retrase — folosește Gemma 4.',
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

function besoiu_env_mirror_aliases(): array
{
    return [
        'RAPIDAPI_TECDOC_KEY' => 'RAPIDAPI_AUTOPARTS_KEY',
        'OPENROUTER_KEY' => 'OPENROUTER_API_KEY',
    ];
}

/**
 * Citește valoare din env / fișier .env fără a construi catalogul de chei editabile.
 */
function besoiu_env_read_direct(string $key, string $default = ''): string
{
    $key = trim($key);
    if ($key === '') {
        return $default;
    }

    foreach ([$_ENV[$key] ?? null, $_SERVER[$key] ?? null, getenv($key)] as $raw) {
        $val = trim((string) ($raw ?? ''));
        if ($val !== '') {
            return $val;
        }
    }

    $fileVals = besoiu_env_file_values();
    if (isset($fileVals[$key]) && $fileVals[$key] !== '') {
        return $fileVals[$key];
    }

    $primary = besoiu_env_mirror_aliases()[$key] ?? '';
    if ($primary !== '' && $primary !== $key) {
        return besoiu_env_read_direct($primary, $default);
    }

    return $default;
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

    $val = besoiu_env_read_direct($envKey, '');
    if ($val !== '') {
        return $val;
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
        'SCRAPE_DO_TOKEN' => [
            'label' => 'Scrape.do (fallback)',
            'test_module' => 'scrape_do',
            'hint' => 'Opțional — doar dacă SCRAPER_FALLBACK_SCRAPE_DO=1. Scraper admin folosește stealth-browser-mcp.',
            'consumption' => [
                'providers' => ['scrape_do'],
                'auto' => 'Fallback import imagini (doar dacă activat explicit)',
                'manual' => 'Nu se folosește la /admin/scraper (stealth implicit)',
            ],
        ],
        'RAPIDAPI_AUTOPARTS_KEY' => [
            'label' => 'RapidAPI TecDoc',
            'test_module' => 'rapidapi_tecdoc',
            'hint' => 'Catalog piese TecDoc — la token nou: 100 cereri, 1/căutare.',
            'mirror_to' => 'RAPIDAPI_TECDOC_KEY',
            'consumption' => [
                'providers' => ['rapidapi_tecdoc'],
                'auto' => 'Cron imagini, import enrichment, pagini produs, aibotpiese (dacă cheie partajată)',
                'manual' => 'Filterbar site, test admin, robot tecdoc_proxy',
            ],
        ],
        'GROQ_KEY' => [
            'label' => 'Groq (cloud free)',
            'test_module' => 'groq',
            'hint' => 'Tier gratuit Groq — fallback rapid după Ollama în cascada Metro LLM.',
            'consumption' => [
                'providers' => ['groq'],
                'auto' => 'Supervizor, briefing agenți, chat widget (când Ollama eșuează)',
                'manual' => 'Asistent secțiune, test Metro LLM',
            ],
        ],
        'GEMINI_KEY' => [
            'label' => 'Google Gemini (cloud free)',
            'test_module' => 'gemini',
            'hint' => 'Cheie din Google AI Studio — al doilea fallback cloud în Metro LLM.',
            'consumption' => [
                'providers' => ['gemini'],
                'auto' => 'Supervizor, briefing agenți (când Groq eșuează)',
                'manual' => 'Asistent secțiune, test Metro LLM',
            ],
        ],
        'OPENROUTER_API_KEY' => [
            'label' => 'OpenRouter (modele free)',
            'test_module' => 'openrouter',
            'hint' => 'openrouter.ai — modele cu sufix :free, fallback Metro LLM.',
            'mirror_to' => 'OPENROUTER_KEY',
            'consumption' => [
                'providers' => ['openai'],
                'auto' => 'Fallback Metro LLM (când Groq și Gemini eșuează)',
                'manual' => 'Test Metro LLM, asistent secțiune',
            ],
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
function besoiu_env_file_values(): array
{
    static $cache = null;
    if (is_array($cache)) {
        return $cache;
    }

    $cache = [];
    $file = besoiu_env_file_path();
    if (!is_file($file)) {
        return $cache;
    }

    $lines = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($lines)) {
        return $cache;
    }

    foreach ($lines as $line) {
        $line = trim((string) $line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        if ($key === '') {
            continue;
        }
        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"'))
            || (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $value = substr($value, 1, -1);
        }
        $cache[$key] = trim($value);
    }

    return $cache;
}

/**
 * Sursă unică pentru chei API — Setări admin (tab=keys) → admin/.env.
 * Ordine: $_ENV / $_SERVER / getenv → fișier admin/.env → alias mirror (ex. RAPIDAPI_TECDOC_KEY).
 */
function besoiu_env_get(string $key, string $default = ''): string
{
    $val = besoiu_env_read_direct($key, '');
    if ($val !== '') {
        return $val;
    }

    return $default;
}

function besoiu_env_has(string $key): bool
{
    return besoiu_env_get($key) !== '';
}

/** URL setări tokeni — singurul loc unde operatorul editează cheile. */
function besoiu_env_settings_keys_url(): string
{
    return '/admin/settings?tab=keys';
}

/** @return array<string, string> */
function besoiu_env_current_values(): array
{
    $out = [];
    foreach (besoiu_env_editable_keys() as $key => $meta) {
        $val = besoiu_env_get($key);
        if ($val === '') {
            $alias = trim((string) ($meta['mirror_to'] ?? ''));
            if ($alias !== '') {
                $val = besoiu_env_get($alias);
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
function besoiu_env_save_keys(array $input): array
{
    $allowed = besoiu_env_editable_keys();
    $file = besoiu_env_file_path();
    $changes = [];
    $tokenBefore = [
        'SCRAPE_DO_TOKEN' => besoiu_env_get('SCRAPE_DO_TOKEN'),
        'RAPIDAPI_AUTOPARTS_KEY' => besoiu_env_get('RAPIDAPI_AUTOPARTS_KEY'),
        'GROQ_KEY' => besoiu_env_get('GROQ_KEY'),
        'GEMINI_KEY' => besoiu_env_get('GEMINI_KEY'),
        'OPENROUTER_API_KEY' => besoiu_env_get('OPENROUTER_API_KEY'),
    ];

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

    $budgetNotes = [];
    $budgetPath = dirname(__DIR__) . '/system/api_token_budget.php';
    if (is_file($budgetPath)) {
        require_once $budgetPath;
        if (function_exists('api_token_budget_sync_env_token_changes')) {
            $budgetNotes = api_token_budget_sync_env_token_changes($changes, $tokenBefore);
        }
    }

    $message = 'Setări salvate în admin/.env (' . implode(', ', $parts) . ').';
    if ($budgetNotes !== []) {
        $message .= ' ' . implode(' ', $budgetNotes);
    }

    return [
        'ok' => true,
        'message' => $message,
        'updated' => array_keys($changes),
        'budget_resets' => $budgetNotes,
    ];
}

/**
 * Scrie valori arbitrare (non-secrete) în admin/.env — toggle-uri automatizare etc.
 *
 * @param array<string, string> $values
 * @return array{ok: bool, message: string, updated: list<string>}
 */
function besoiu_env_write_values(array $values): array
{
    if ($values === []) {
        return ['ok' => false, 'message' => 'Nicio valoare de scris.', 'updated' => []];
    }

    $file = besoiu_env_file_path();
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
        if (array_key_exists($key, $values)) {
            $lines[$i] = $key . '=' . $values[$key];
            $seen[$key] = true;
        }
    }

    foreach ($values as $key => $val) {
        if (!isset($seen[$key])) {
            $lines[] = $key . '=' . $val;
        }
    }

    $dir = dirname($file);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    if (@file_put_contents($file, implode(PHP_EOL, $lines) . PHP_EOL, LOCK_EX) === false) {
        return ['ok' => false, 'message' => 'Nu am putut scrie admin/.env', 'updated' => []];
    }

    foreach ($values as $key => $val) {
        $_ENV[$key] = $val;
        putenv($key . '=' . $val);
    }

    return [
        'ok' => true,
        'message' => 'Valorile .env au fost actualizate.',
        'updated' => array_keys($values),
    ];
}
