<?php

declare(strict_types=1);

/**
 * Încarcă context runtime pentru un agent (fără autoload Composer).
 */
function robot_ai_agent_runtime(string $slug): string
{
    $slug = strtolower(preg_replace('/[^a-z0-9\-_]+/', '-', trim($slug)) ?? '');
    if ($slug === '') {
        $slug = 'context-master';
    }
    $path = __DIR__ . '/data/ai_agents/' . $slug . '/runtime.md';
    if (is_file($path)) {
        return trim((string) file_get_contents($path));
    }
    $fallback = __DIR__ . '/data/ai_context/latest.md';

    return is_file($fallback) ? trim((string) file_get_contents($fallback)) : '';
}

function robot_ai_default_agent_slug(): string
{
    $path = __DIR__ . '/data/ai_agents/bot_links.json';
    if (!is_file($path)) {
        return 'context-master';
    }
    $json = json_decode((string) file_get_contents($path), true);
    if (!is_array($json)) {
        return 'context-master';
    }
    $slug = (string) ($json['_website_default'] ?? '');

    return $slug !== '' ? $slug : 'context-master';
}

function robot_ai_agent_temperature(string $slug): float
{
    $slug = strtolower(preg_replace('/[^a-z0-9\-_]+/', '-', trim($slug)) ?? '');
    if ($slug === '') {
        return 0.35;
    }
    $runtimePath = __DIR__ . '/data/ai_agents/' . $slug . '/runtime.json';
    if (is_file($runtimePath)) {
        $json = json_decode((string) file_get_contents($runtimePath), true);
        if (is_array($json) && isset($json['temperature'])) {
            return max(0.0, min(1.0, (float) $json['temperature']));
        }
    }
    $mdcPath = __DIR__ . '/data/ai_agents/' . $slug . '/agent.mdc';
    if (is_file($mdcPath)) {
        $content = (string) file_get_contents($mdcPath);
        if (preg_match('/^temperature:\s*([0-9.]+)/m', $content, $m)) {
            return max(0.0, min(1.0, (float) $m[1]));
        }
    }

    return 0.35;
}

function robot_ai_core_directives(): array
{
    $path = __DIR__ . '/data/ai_context/core.json';
    if (!is_file($path)) {
        return [];
    }
    $core = json_decode((string) file_get_contents($path), true);
    if (!is_array($core) || empty($core['robot_directives']) || !is_array($core['robot_directives'])) {
        return [];
    }

    return array_values(array_filter(array_map('strval', $core['robot_directives'])));
}
