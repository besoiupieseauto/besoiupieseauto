<?php

declare(strict_types=1);

namespace Besoiu\Core;

/**
 * Cache fișier simplu (TTL) — reduce query-uri repetate la bootstrap.
 */
final class AppCache
{
    private static function cacheDir(): string
    {
        $dir = dirname(__DIR__, 2) . '/storage/cache';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return $dir;
    }

    private static function path(string $key): string
    {
        return self::cacheDir() . '/' . hash('sha256', $key) . '.json';
    }

    /** @template T */
    public static function remember(string $key, int $ttlSeconds, callable $factory): mixed
    {
        $path = self::path($key);
        if (is_file($path)) {
            $raw = json_decode((string) file_get_contents($path), true);
            if (is_array($raw) && (int) ($raw['expires'] ?? 0) > time()) {
                return $raw['data'] ?? null;
            }
        }

        $data = $factory();
        file_put_contents($path, json_encode([
            'key' => $key,
            'expires' => time() + max(1, $ttlSeconds),
            'data' => $data,
        ], JSON_UNESCAPED_UNICODE));

        return $data;
    }

    public static function forget(string $key): void
    {
        $path = self::path($key);
        if (is_file($path)) {
            unlink($path);
        }
    }

    public static function flushPrefix(string $prefix): void
    {
        foreach (glob(self::cacheDir() . '/*.json') ?: [] as $file) {
            $raw = json_decode((string) file_get_contents($file), true);
            if (!is_array($raw)) {
                continue;
            }
            $storedKey = (string) ($raw['key'] ?? '');
            if ($storedKey !== '' && str_starts_with($storedKey, $prefix)) {
                unlink($file);
            }
        }
    }

    /** Șterge tot cache-ul fișier (folosit după TRUNCATE / mutări masive). */
    public static function flushAll(): void
    {
        foreach (glob(self::cacheDir() . '/*.json') ?: [] as $file) {
            unlink($file);
        }
    }
}
