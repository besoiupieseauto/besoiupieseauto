<?php

declare(strict_types=1);

/** Chei LLM pentru agent scraper — Cursor Composer 2.5, Groq, OpenAI. */
final class ScraperLlmConfig
{
    public static function cursorKey(): string
    {
        return self::readEnv('CURSOR_API_KEY');
    }

    public static function cursorModel(): string
    {
        $m = self::readEnv('CURSOR_MODEL');

        return $m !== '' ? $m : 'composer-2.5';
    }

    public static function groqKey(): string
    {
        return self::readEnv('GROQ_KEY');
    }

    public static function openaiKey(): string
    {
        return self::readEnv('OPENAI_KEY');
    }

    public static function groqModel(): string
    {
        $m = self::readEnv('GROQ_MODEL');

        return $m !== '' ? $m : 'llama-3.3-70b-versatile';
    }

    public static function openaiModel(): string
    {
        $m = self::readEnv('OPENAI_MODEL');
        if ($m !== '') {
            return $m;
        }

        $audit = self::readEnv('IMAGE_AUDIT_MODEL');

        return $audit !== '' ? $audit : 'gpt-4o-mini';
    }

    public static function geminiModel(): string
    {
        $m = self::readEnv('GEMINI_MODEL');

        return $m !== '' ? $m : 'gemini-2.0-flash';
    }

    public static function grokModel(): string
    {
        $m = self::readEnv('GROK_MODEL');

        return $m !== '' ? $m : 'grok-2-1212';
    }

    public static function hasAnyKey(): bool
    {
        return self::cursorKey() !== '' || self::groqKey() !== '' || self::openaiKey() !== '';
    }

    public static function hasCursorKey(): bool
    {
        return self::cursorKey() !== '';
    }

    public static function readEnv(string $key): string
    {
        $fromEnv = trim((string) ($_ENV[$key] ?? $_SERVER[$key] ?? ''));
        if ($fromEnv !== '') {
            return $fromEnv;
        }

        $fromGetenv = trim((string) (getenv($key) ?: ''));
        if ($fromGetenv !== '') {
            return $fromGetenv;
        }

        foreach (self::envFileCandidates() as $envFile) {
            $val = self::parseEnvFileKey($envFile, $key);
            if ($val !== '') {
                return $val;
            }
        }

        return '';
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

    private static function parseEnvFileKey(string $envFile, string $wanted): string
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
            if ($key !== $wanted) {
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
