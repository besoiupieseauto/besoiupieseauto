<?php

declare(strict_types=1);

namespace Evasystem\Controllers\Dashboard;

/**
 * Snapshot JSON pentru dashboard — citire instant, refresh în fundal (cron/CLI).
 */
final class DashboardSnapshotService
{
    private const FILE_NAME = 'dashboard_snapshot.json';
    private const DEFAULT_TTL = 300;

    public static function path(): string
    {
        $dir = dirname(__DIR__, 3) . '/storage/cache';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return $dir . '/' . self::FILE_NAME;
    }

    /** @return array{generated_at:string,expires_at:int,data:array<string,mixed>}|null */
    public static function readEnvelope(): ?array
    {
        $path = self::path();
        if (!is_file($path)) {
            return null;
        }

        $raw = json_decode((string) file_get_contents($path), true);
        if (!is_array($raw) || !isset($raw['data']) || !is_array($raw['data'])) {
            return null;
        }

        return $raw;
    }

    /** @return array<string,mixed>|null */
    public static function readData(): ?array
    {
        $envelope = self::readEnvelope();
        return $envelope['data'] ?? null;
    }

    public static function isStale(?array $envelope = null, int $ttlSeconds = self::DEFAULT_TTL): bool
    {
        $envelope ??= self::readEnvelope();
        if ($envelope === null) {
            return true;
        }

        $expiresAt = (int) ($envelope['expires_at'] ?? 0);
        if ($expiresAt > 0 && $expiresAt > time()) {
            return false;
        }

        $generatedAt = strtotime((string) ($envelope['generated_at'] ?? ''));
        if ($generatedAt === false) {
            return true;
        }

        return (time() - $generatedAt) > max(30, $ttlSeconds);
    }

    /** @param array<string,mixed> $data */
    public static function write(array $data, int $ttlSeconds = self::DEFAULT_TTL): array
    {
        $generatedAt = date('Y-m-d H:i:s');
        $envelope = [
            'generated_at' => $generatedAt,
            'expires_at' => time() + max(30, $ttlSeconds),
            'data' => $data,
        ];

        file_put_contents(
            self::path(),
            json_encode($envelope, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );

        return $envelope;
    }

    /** @return array<string,mixed> */
    public static function refresh(int $ttlSeconds = self::DEFAULT_TTL, bool $forceRefresh = true): array
    {
        $data = (new DashboardService())->overview($forceRefresh);
        self::write($data, $ttlSeconds);
        return $data;
    }

    /**
     * Returnează date pentru API: din fișier dacă există, altfel generează.
     *
     * @return array{data:array<string,mixed>,source:string,stale:bool}
     */
    public static function resolve(bool $forceRefresh = false): array
    {
        if (!$forceRefresh) {
            $envelope = self::readEnvelope();
            if ($envelope !== null && isset($envelope['data']) && is_array($envelope['data'])) {
                return [
                    'data' => $envelope['data'],
                    'source' => 'snapshot',
                    'stale' => self::isStale($envelope),
                ];
            }
        }

        $data = self::refresh(self::DEFAULT_TTL, $forceRefresh);
        return [
            'data' => $data,
            'source' => 'live',
            'stale' => false,
        ];
    }
}
