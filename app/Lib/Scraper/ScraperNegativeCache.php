<?php

declare(strict_types=1);

require_once __DIR__ . '/ScraperPaths.php';

/**
 * Cache negativ — reține codurile de produs pentru care ePiesa/Autodoc au fost
 * încercate deja și nu au găsit nimic. Fără el, o rescanare (accidentală sau
 * repetată) a aceluiași cod plătește din nou costul complet (minute) pentru
 * un rezultat deja cunoscut.
 *
 * Cheie = pCodeNorm + pBrandNorm. TTL implicit 24h (rezultatul poate deveni
 * valid mai târziu dacă ePiesa/Autodoc adaugă produsul între timp).
 */
final class ScraperNegativeCache
{
    private const TTL_SEC = 86400;
    private const MAX_ENTRIES = 5000;

    private static function path(): string
    {
        $dir = ScraperPaths::projectRoot() . '/storage/scraper';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        return $dir . '/negative_image_cache.json';
    }

    public static function keyFor(string $code, string $brand): string
    {
        $norm = static function (string $v): string {
            return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $v) ?? '');
        };

        return $norm($brand) . '|' . $norm($code);
    }

    /** @return array<string, string> key => ISO timestamp */
    private static function readAll(): array
    {
        $path = self::path();
        if (!is_file($path)) {
            return [];
        }
        $data = json_decode((string) @file_get_contents($path), true);

        return is_array($data) ? $data : [];
    }

    /** @param array<string, string> $entries */
    private static function writeAll(array $entries): void
    {
        if (count($entries) > self::MAX_ENTRIES) {
            arsort($entries);
            $entries = array_slice($entries, 0, self::MAX_ENTRIES, true);
        }
        @file_put_contents(self::path(), json_encode($entries, JSON_UNESCAPED_UNICODE), LOCK_EX);
    }

    public static function recordMiss(string $key): void
    {
        if ($key === '|') {
            return;
        }
        $entries = self::readAll();
        $entries[$key] = date('c');
        self::writeAll($entries);
    }

    public static function isRecentMiss(string $key, int $ttlSec = self::TTL_SEC): bool
    {
        if ($key === '|') {
            return false;
        }
        $entries = self::readAll();
        if (!isset($entries[$key])) {
            return false;
        }
        $at = strtotime((string) $entries[$key]);
        if ($at === false) {
            return false;
        }

        return (time() - $at) < $ttlSec;
    }

    public static function ageLabel(string $key): string
    {
        $entries = self::readAll();
        $at = isset($entries[$key]) ? strtotime((string) $entries[$key]) : false;
        if ($at === false) {
            return '';
        }
        $minutes = max(0, (int) round((time() - $at) / 60));
        if ($minutes < 60) {
            return $minutes . ' min';
        }

        return (int) round($minutes / 60) . 'h';
    }

    public static function clear(string $key): void
    {
        $entries = self::readAll();
        if (isset($entries[$key])) {
            unset($entries[$key]);
            self::writeAll($entries);
        }
    }
}
