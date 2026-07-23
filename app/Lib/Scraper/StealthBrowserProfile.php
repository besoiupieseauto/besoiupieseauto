<?php

declare(strict_types=1);

require_once __DIR__ . '/ScraperPaths.php';

/**
 * Amprentă browser per sursă — UA/viewport/limbă diferite între scrapere.
 */
final class StealthBrowserProfile
{
    /** @var array<string, mixed>|null */
    private static ?array $config = null;

    /**
     * @return array{
     *   source: string,
     *   label: string,
     *   user_agent: string,
     *   viewport_width: int,
     *   viewport_height: int,
     *   accept_language: string
     * }
     */
    public static function resolve(?string $sourceId = null, ?string $host = null): array
    {
        $sourceKey = self::resolveSourceKey($sourceId, $host);
        $sourceConfig = self::loadConfig()[$sourceKey] ?? self::loadConfig()['default'] ?? [];
        $pool = is_array($sourceConfig['pool'] ?? null) ? $sourceConfig['pool'] : [];

        if ($pool === []) {
            return self::fallbackProfile($sourceKey);
        }

        $idx = self::shouldRotatePerRun()
            ? random_int(0, count($pool) - 1)
            : abs(crc32($sourceKey . date('Y-m-d'))) % count($pool);

        $pick = is_array($pool[$idx] ?? null) ? $pool[$idx] : $pool[0];
        $viewport = is_array($pick['viewport'] ?? null) ? $pick['viewport'] : [1920, 1080];
        $width = max(1024, min(2560, (int) ($viewport[0] ?? 1920)));
        $height = max(600, min(1440, (int) ($viewport[1] ?? 1080)));

        return [
            'source' => $sourceKey,
            'label' => trim((string) ($pick['label'] ?? 'Chrome')),
            'user_agent' => trim((string) ($pick['user_agent'] ?? self::fallbackProfile($sourceKey)['user_agent'])),
            'viewport_width' => $width,
            'viewport_height' => $height,
            'accept_language' => trim((string) ($sourceConfig['accept_language'] ?? 'ro-RO,ro;q=0.9,en-US;q=0.8,en;q=0.7')),
        ];
    }

    /** @return array<string, mixed> */
    public static function toJsonPayload(array $profile): array
    {
        return [
            'source' => (string) ($profile['source'] ?? 'default'),
            'label' => (string) ($profile['label'] ?? ''),
            'user_agent' => (string) ($profile['user_agent'] ?? ''),
            'viewport_width' => (int) ($profile['viewport_width'] ?? 1920),
            'viewport_height' => (int) ($profile['viewport_height'] ?? 1080),
            'accept_language' => (string) ($profile['accept_language'] ?? 'ro-RO,ro;q=0.9'),
        ];
    }

    private static function resolveSourceKey(?string $sourceId, ?string $host): string
    {
        $sourceId = strtolower(trim((string) $sourceId));
        if ($sourceId !== '') {
            return $sourceId;
        }

        $host = strtolower(trim((string) $host));
        foreach ([
            'autodoc' => 'autodoc',
            'epiesa' => 'epiesa',
            'emag' => 'emag',
            'pieseauto' => 'pieseauto',
        ] as $needle => $key) {
            if ($host !== '' && str_contains($host, $needle)) {
                return $key;
            }
        }

        return 'default';
    }

    /** @return array<string, mixed> */
    private static function loadConfig(): array
    {
        if (self::$config !== null) {
            return self::$config;
        }

        $path = ScraperPaths::projectRoot() . '/config/scraper-browser-profiles.php';
        $loaded = is_file($path) ? require $path : [];

        self::$config = is_array($loaded) ? $loaded : [];

        return self::$config;
    }

    private static function shouldRotatePerRun(): bool
    {
        $raw = strtolower(trim((string) (getenv('STEALTH_BROWSER_UA_ROTATE') ?: '')));

        return in_array($raw, ['1', 'true', 'yes', 'on'], true);
    }

    /** @return array{source: string, label: string, user_agent: string, viewport_width: int, viewport_height: int, accept_language: string} */
    private static function fallbackProfile(string $sourceKey): array
    {
        return [
            'source' => $sourceKey,
            'label' => 'Chrome fallback',
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
            'viewport_width' => 1920,
            'viewport_height' => 1080,
            'accept_language' => 'ro-RO,ro;q=0.9,en-US;q=0.8,en;q=0.7',
        ];
    }
}
