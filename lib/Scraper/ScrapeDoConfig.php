<?php

declare(strict_types=1);

/**
 * Token scrape.do — citește din $_ENV (Dotenv), getenv sau admin/.env direct.
 * Cota: prioritate hub Setări (api_token_budgets), apoi flag API scrape.do.
 */
final class ScrapeDoConfig
{
    public static function token(): string
    {
        $fromEnv = trim((string) ($_ENV['SCRAPE_DO_TOKEN'] ?? $_SERVER['SCRAPE_DO_TOKEN'] ?? ''));
        if ($fromEnv !== '') {
            return $fromEnv;
        }

        $fromGetenv = trim((string) (getenv('SCRAPE_DO_TOKEN') ?: ''));
        if ($fromGetenv !== '') {
            return $fromGetenv;
        }

        foreach (self::envFileCandidates() as $envFile) {
            $parsed = self::parseEnvFile($envFile);
            if ($parsed !== '') {
                return $parsed;
            }
        }

        return '';
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
            $queriesLeft = (int) ($usage['queries_left'] ?? 0);
            if ($queriesLeft > 0) {
                self::clearQuotaExceeded();

                return false;
            }

            return true;
        }

        return self::isQuotaFlagActive();
    }

    /** @return array{remaining_tokens:int,queries_left:int,used_tokens:int,monthly_quota:int}|null */
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
            'used_tokens' => (int) ($usage['used_tokens'] ?? 0),
            'monthly_quota' => (int) ($usage['monthly_quota'] ?? 0),
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

    /** @return array<int, string> */
    private static function envFileCandidates(): array
    {
        $paths = [];
        $add = static function (string $path) use (&$paths): void {
            $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
            if ($path !== '' && is_file($path)) {
                $paths[] = $path;
            }
        };

        $add(dirname(__DIR__, 2) . '/admin/.env');
        $add(dirname(__DIR__, 3) . '/admin/.env');

        $cwd = getcwd();
        if (is_string($cwd) && $cwd !== '') {
            $add($cwd . '/admin/.env');
            $add($cwd . '/.env');
        }

        return array_values(array_unique($paths));
    }

    private static function parseEnvFile(string $envFile): string
    {
        $lines = @file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines)) {
            return '';
        }

        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = array_map('trim', explode('=', $line, 2));
            if ($key !== 'SCRAPE_DO_TOKEN') {
                continue;
            }

            $value = trim($value);
            if (
                (str_starts_with($value, '"') && str_ends_with($value, '"'))
                || (str_starts_with($value, "'") && str_ends_with($value, "'"))
            ) {
                $value = substr($value, 1, -1);
            }

            return trim($value);
        }

        return '';
    }
}
