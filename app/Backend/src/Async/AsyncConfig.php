<?php

declare(strict_types=1);

namespace Besoiu\Async;

final class AsyncConfig
{
    /** @var array<string, mixed> */
    private static array $config = [];

    /** @return array<string, mixed> */
    public static function all(): array
    {
        if (self::$config === []) {
            $path = dirname(__DIR__, 2) . '/config/async_jobs.php';
            self::$config = is_file($path) ? (require $path) : [];
        }

        return self::$config;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::all()[$key] ?? $default;
    }

    public static function storageDir(): string
    {
        $dir = (string) self::get('storage_dir', dirname(__DIR__, 2) . '/storage/async_jobs');
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        return $dir;
    }
}
