<?php

declare(strict_types=1);

/**
 * Config Ollama local — metro LLM fără tokeni cloud.
 */

function besoiu_ollama_env_truthy(string $key, bool $default = false): bool
{
    $raw = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
    if ($raw === false || $raw === null || $raw === '') {
        return $default;
    }
    $v = strtolower(trim((string) $raw));

    return in_array($v, ['1', 'true', 'yes', 'on'], true);
}

function besoiu_ollama_enabled(): bool
{
    return besoiu_ollama_env_truthy('OLLAMA_ENABLED', true);
}

function besoiu_ollama_local_cycle_enabled(): bool
{
    return besoiu_ollama_enabled()
        && besoiu_ollama_env_truthy('OLLAMA_LOCAL_CYCLE', true);
}

function besoiu_ollama_base_url(): string
{
    $url = trim((string) ($_ENV['OLLAMA_BASE_URL'] ?? getenv('OLLAMA_BASE_URL') ?: 'http://127.0.0.1:11434'));
    if ($url === '') {
        return 'http://127.0.0.1:11434';
    }

    return rtrim($url, '/');
}

function besoiu_ollama_model(): string
{
    $m = trim((string) ($_ENV['OLLAMA_MODEL'] ?? getenv('OLLAMA_MODEL') ?: 'qwen2.5:7b'));

    return $m !== '' ? $m : 'qwen2.5:7b';
}

function besoiu_ollama_vision_model(): string
{
    $m = trim((string) ($_ENV['OLLAMA_VISION_MODEL'] ?? getenv('OLLAMA_VISION_MODEL') ?: 'llava:7b'));

    return $m !== '' ? $m : 'llava:7b';
}

/** Model Ollama per agent specializat (fallback la OLLAMA_MODEL). */
function besoiu_ollama_agent_model(string $agentSlug): string
{
    $map = [
        'agent-imagini' => besoiu_ollama_vision_model(),
        'agent-produse' => trim((string) ($_ENV['OLLAMA_MODEL_PRODUSE'] ?? getenv('OLLAMA_MODEL_PRODUSE') ?: '')),
        'agent-clienti' => trim((string) ($_ENV['OLLAMA_MODEL_CLIENTI'] ?? getenv('OLLAMA_MODEL_CLIENTI') ?: '')),
        'agent-statistici' => trim((string) ($_ENV['OLLAMA_MODEL_STATS'] ?? getenv('OLLAMA_MODEL_STATS') ?: 'qwen2.5:7b')),
    ];
    $chosen = trim((string) ($map[$agentSlug] ?? ''));

    return $chosen !== '' ? $chosen : besoiu_ollama_model();
}

/** @return 'ollama_first'|'ollama_only' */
function besoiu_llm_router_mode(): string
{
    $mode = strtolower(trim((string) ($_ENV['LLM_ROUTER_MODE'] ?? getenv('LLM_ROUTER_MODE') ?: 'ollama_first')));
    if ($mode === 'cursor_only') {
        $mode = 'ollama_first';
    }
    if (!in_array($mode, ['ollama_first', 'ollama_only'], true)) {
        return 'ollama_first';
    }

    return $mode;
}

/**
 * Profil task: local = doar Ollama; hybrid = Ollama apoi cloud free; cloud = doar cloud free.
 *
 * @return 'local'|'hybrid'|'cloud'
 */
function besoiu_llm_task_profile(string $context): string
{
    $map = [
        'supervisor_diagnostics' => 'hybrid',
        'supervisor_conversations' => 'hybrid',
        'supervisor_daily' => 'hybrid',
        'section_assistant' => 'hybrid',
        'section_assistant_intent' => 'local',
        'section_assistant_chat' => 'hybrid',
        'composer_repair' => 'hybrid',
        'context_brief' => 'hybrid',
        'ai_agent' => 'hybrid',
        'chat_widget' => 'hybrid',
        'chat_decode' => 'hybrid',
        'chat_widget_whatsapp' => 'hybrid',
        'comunicare_reply' => 'hybrid',
        'image_audit' => 'hybrid',
        'import_image_gate' => 'hybrid',
        'agent_imagini' => 'hybrid',
        'agent_produse' => 'local',
        'agent_clienti' => 'hybrid',
        'agent_statistici' => 'local',
        'import_pipeline' => 'hybrid',
        'import_quality_match' => 'hybrid',
        'scraper_analyze' => 'hybrid',
        'default' => 'hybrid',
    ];

    return $map[$context] ?? $map['default'];
}

function besoiu_ollama_embed_model(): string
{
    $m = trim((string) ($_ENV['OLLAMA_EMBED_MODEL'] ?? getenv('OLLAMA_EMBED_MODEL') ?: 'nomic-embed-text'));

    return $m !== '' ? $m : 'nomic-embed-text';
}

/** Recomandat pe serverul Ollama pentru context lung (export env înainte de `ollama serve`). */
function besoiu_ollama_kv_cache_type(): string
{
    $v = trim((string) ($_ENV['OLLAMA_KV_CACHE_TYPE'] ?? getenv('OLLAMA_KV_CACHE_TYPE') ?: 'q8_0'));

    return $v !== '' ? $v : 'q8_0';
}

/** @return list<string> */
function besoiu_ollama_runtime_env_hints(): array
{
    return [
        'Setează pe host: OLLAMA_KV_CACHE_TYPE=' . besoiu_ollama_kv_cache_type(),
        'Chat model (inferență): ' . besoiu_ollama_model(),
        'Embed model: ' . besoiu_ollama_embed_model(),
        'Fine-tuning live: NU — doar inferență + RAG din MySQL.',
    ];
}
