<?php

declare(strict_types=1);

/**
 * Token scrape.do — citește exclusiv via besoiu_env_get (Setări → Tokeni API → admin/.env).
 * Cota: prioritate hub Setări (api_token_budgets), apoi flag API scrape.do.
 */
final class ScrapeDoConfig
{
    private static function ensureEnvHelper(): void
    {
        if (function_exists('besoiu_env_get')) {
            return;
        }
        $path = dirname(__DIR__, 2) . '/admin/system/env_settings.php';
        if (is_file($path)) {
            require_once $path;
        }
    }

    public static function token(): string
    {
        self::ensureEnvHelper();

        return function_exists('besoiu_env_get')
            ? besoiu_env_get('SCRAPE_DO_TOKEN')
            : '';
    }

    public static function hasToken(): bool
    {
        return self::token() !== '';
    }

    public static function quotaExceededFlagPath(): string
    {
        return dirname(__DIR__, 2) . '/admin/storage/logs/.scrape_do_quota_exceeded';
    }

    public static function isQuotaExceeded(): bool
    {
        $usage = self::budgetUsage();
        if ($usage !== null) {
            $requestsLeft = (int) ($usage['requests_left'] ?? $usage['queries_left'] ?? 0);
            if ($requestsLeft > 0) {
                self::clearQuotaExceeded();

                return false;
            }

            // Buget local epuizat — respectăm și flag-ul API (poate confirma depășirea reală).
            if (self::isQuotaFlagActive()) {
                return true;
            }

            return $requestsLeft <= 0;
        }

        return self::isQuotaFlagActive();
    }

    /** @return array{remaining_tokens:int,queries_left:int,requests_left:int,used_tokens:int,monthly_quota:int,max_requests:int,month_requests:int,tokens_per_request:int,quota_inconsistent:bool}|null */
    public static function budgetUsage(): ?array
    {
        $budgetPath = dirname(__DIR__, 2) . '/admin/system/api_token_budget.php';
        if (!is_file($budgetPath)) {
            return null;
        }

        require_once $budgetPath;
        if (!function_exists('api_token_budget_provider_usage')) {
            return null;
        }

        $usage = api_token_budget_provider_usage('scrape_do');
        if (!is_array($usage)) {
            return null;
        }

        return [
            'remaining_tokens' => (int) ($usage['remaining_tokens'] ?? 0),
            'queries_left' => (int) ($usage['queries_left'] ?? 0),
            'requests_left' => (int) ($usage['requests_left'] ?? $usage['queries_left'] ?? 0),
            'used_tokens' => (int) ($usage['used_tokens'] ?? 0),
            'monthly_quota' => (int) ($usage['monthly_quota'] ?? 0),
            'max_requests' => (int) ($usage['max_requests'] ?? 0),
            'month_requests' => (int) ($usage['month_requests'] ?? 0),
            'tokens_per_request' => (int) ($usage['tokens_per_request'] ?? 1),
            'quota_inconsistent' => !empty($usage['quota_inconsistent']),
        ];
    }

    public static function clearQuotaExceeded(): void
    {
        $path = self::quotaExceededFlagPath();
        if (is_file($path)) {
            @unlink($path);
        }
    }

    public static function markQuotaExceeded(): void
    {
        $path = self::quotaExceededFlagPath();
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        @file_put_contents($path, (string) time());
    }

    public static function noteQuotaExceededFromMessage(string $message): void
    {
        $lower = strtolower($message);
        if (str_contains($lower, 'monthly request limit')
            || str_contains($lower, 'request limit exceeded')
            || str_contains($lower, 'quota exceeded')
            || (str_contains($lower, 'scrape.do http 401') && str_contains($lower, 'limit'))
            || (str_contains($lower, 'scrape.do http 429'))) {
            self::markQuotaExceeded();
        }
    }

    private static function isQuotaFlagActive(): bool
    {
        $path = self::quotaExceededFlagPath();
        if (!is_file($path)) {
            return false;
        }
        $ts = (int) trim((string) file_get_contents($path));
        if ($ts <= 0 || (time() - $ts) > 604800) {
            @unlink($path);

            return false;
        }

        return true;
    }
}
