<?php

declare(strict_types=1);

namespace Besoiu\Services;

/**
 * Utilitare comune clienți LLM cloud (guard, env, jurnal tokeni).
 */
final class LlmClientSupport
{
    public static function loadEnv(string $projectRoot): void
    {
        static $loaded = false;
        if ($loaded) {
            return;
        }

        if (function_exists('shop_auth_load_env')) {
            shop_auth_load_env();
            $loaded = true;

            return;
        }

        if (function_exists('besoiu_api_automation_env_loaded')) {
            besoiu_api_automation_env_loaded();
            $loaded = true;

            return;
        }

        $adminRoot = rtrim($projectRoot, '/\\') . '/admin';
        $autoload = $adminRoot . '/vendor/autoload.php';
        if (is_file($autoload)) {
            require_once $autoload;
            if (class_exists(\Dotenv\Dotenv::class) && is_file($adminRoot . '/.env')) {
                \Dotenv\Dotenv::createImmutable($adminRoot)->safeLoad();
            }
        }

        $loaded = true;
    }

    public static function env(string $key, string $default = ''): string
    {
        if (function_exists('env')) {
            $v = trim((string) env($key, ''));
            if ($v !== '') {
                return $v;
            }
        }

        $v = trim((string) ($_ENV[$key] ?? getenv($key) ?: ''));

        return $v !== '' ? $v : $default;
    }

    public static function isRetryableCloudLlmError(string $error): bool
    {
        $e = strtolower($error);

        return str_contains($e, 'quota')
            || str_contains($e, 'rate limit')
            || str_contains($e, 'rate-limit')
            || str_contains($e, 'rate-limited')
            || str_contains($e, 'resource_exhausted')
            || str_contains($e, 'no endpoints found')
            || str_contains($e, 'unavailable for free')
            || str_contains($e, 'provider returned error')
            || str_contains($e, 'operation was aborted')
            || str_contains($e, 'not found')
            || str_contains($e, 'model is unavailable');
    }

    public static function shortenLlmError(string $error, string $provider = 'LLM'): string
    {
        $error = trim(preg_replace('/\s+/u', ' ', $error) ?? $error);
        if ($error === '') {
            return $provider . ' — eroare necunoscută.';
        }

        if (stripos($error, 'quota exceeded') !== false || stripos($error, 'exceeded your current quota') !== false) {
            return $provider . ' — cotă free epuizată pe modelul ales. Schimbă GEMINI_MODEL (ex. gemini-2.5-flash) sau așteaptă reset.';
        }
        if (stripos($error, 'no endpoints found') !== false || stripos($error, 'unavailable for free') !== false) {
            return $provider . ' — modelul :free nu mai există pe OpenRouter. Alege google/gemma-4-26b-a4b-it:free din Setări.';
        }
        if (stripos($error, 'provider returned error') !== false || stripos($error, 'rate-limited') !== false) {
            return $provider . ' — model free temporar limitat upstream. Reîncearcă sau schimbă OPENROUTER_MODEL (ex. Gemma 4).';
        }

        return mb_strlen($error) > 220 ? (mb_substr($error, 0, 217) . '…') : $error;
    }

    /** Mesaj eroare dacă apelul e blocat; null = permis. */
    public static function guardBlockMessage(
        string $projectRoot,
        string $providerLabel = 'LLM',
        string $providerKey = 'llm',
    ): ?string {
        $guardPath = rtrim($projectRoot, '/\\') . '/system/api_automation_guard.php';
        if (!is_file($guardPath)) {
            return null;
        }
        require_once $guardPath;
        if (!function_exists('besoiu_api_live_call_allowed')) {
            return null;
        }
        if (besoiu_api_live_call_allowed($providerKey)) {
            return null;
        }

        if (function_exists('besoiu_api_automation_block_message')) {
            $msg = besoiu_api_automation_block_message($providerKey);
            if ($msg !== '') {
                return $msg;
            }
        }

        return $providerLabel . ' blocat — orchestra free indisponibilă sau API plătit nearmat.';
    }

    public static function logUsage(string $projectRoot, string $provider, string $model, string $source, ?int $tokens): void
    {
        $budgetFile = rtrim($projectRoot, '/\\') . '/admin/system/api_token_budget.php';
        if (!is_file($budgetFile)) {
            return;
        }
        require_once $budgetFile;
        if (!function_exists('api_token_budget_log_llm_call')) {
            return;
        }

        try {
            api_token_budget_log_llm_call($provider, $model, $source, $tokens);
        } catch (\Throwable) {
            // jurnal opțional
        }
    }

    /**
     * @param list<array{role:string,content:string}> $messages
     * @return list<array{role:string,content:string}>
     */
    public static function messagesWithSystem(array $messages, string $systemPrompt): array
    {
        $out = [];
        if (trim($systemPrompt) !== '') {
            $out[] = ['role' => 'system', 'content' => $systemPrompt];
        }
        foreach ($messages as $msg) {
            if (!is_array($msg)) {
                continue;
            }
            $content = trim((string) ($msg['content'] ?? ''));
            if ($content === '') {
                continue;
            }
            $role = (string) ($msg['role'] ?? 'user');
            if (!in_array($role, ['system', 'user', 'assistant'], true)) {
                $role = 'user';
            }
            $out[] = ['role' => $role, 'content' => $content];
        }

        return $out;
    }

    /**
     * @return array{ok:bool,content?:string,error?:string,http?:int}
     */
    public static function postJson(string $url, array $headers, string $payload, int $timeoutSec): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => max(5, $timeoutSec),
        ]);
        $resp = curl_exec($ch);
        $curlErr = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($curlErr !== '') {
            return ['ok' => false, 'error' => $curlErr, 'http' => $httpCode];
        }

        return ['ok' => $httpCode >= 200 && $httpCode < 300, 'body' => is_string($resp) ? $resp : '', 'http' => $httpCode];
    }
}
