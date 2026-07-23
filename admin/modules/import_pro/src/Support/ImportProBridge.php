<?php
declare(strict_types=1);

namespace Besoiu\Modules\ImportPro\Support;

use Besoiu\Import\Support\ImportPathResolver;
use RuntimeException;

/**
 * Conectează modulul ERP la motorul MatchingPro din app/Import/MatchingPro.
 */
final class ImportProBridge
{
    private static bool $booted = false;
    private static string $root = '';

    public static function boot(): self
    {
        if (!self::$booted) {
            self::bootImportStack();
            self::$root = self::resolveRoot();
            if (!is_file(self::$root . '/index.php')) {
                throw new RuntimeException(
                    'Motor Import Pro negasit la: ' . self::$root
                    . ' — verifica app/Import/MatchingPro.'
                );
            }
            self::$booted = true;
        }

        return new self();
    }

    public static function root(): string
    {
        if (self::$root === '') {
            self::$root = self::resolveRoot();
        }

        return self::$root;
    }

    public static function standaloneUrl(): string
    {
        return class_exists(ImportPathResolver::class)
            ? ImportPathResolver::matchingProPublicUrl()
            : '/admin/public/import-pro/';
    }

    public static function apiBaseUrl(): string
    {
        return rtrim(self::standaloneUrl(), '/') . '/api/';
    }

    /** URL proxy scraping (Import Pro → modul scraper_web). */
    public static function scraperProxyUrl(): string
    {
        return rtrim(self::standaloneUrl(), '/') . '/_proxy/scraper-api.php';
    }

    /** Endpoint ERP scraper_web (același motor, acces direct din admin API). */
    public static function scraperWebApiUrl(): string
    {
        return '/admin/public/api/scraper_web_endpoint.php';
    }

    public static function erpPublicReady(): bool
    {
        $erpPublic = dirname(__DIR__, 4) . '/admin/public/import-pro';
        return is_file($erpPublic . '/index.php')
            && (is_dir($erpPublic . '/api') || is_dir(self::root() . '/api'));
    }

    /** @return list<string> */
    public static function statusLines(): array
    {
        $lines = [
            'Motor: ' . self::root(),
            'Data: ' . (class_exists(ImportPathResolver::class) ? ImportPathResolver::dataRoot() : '-'),
            'Embed: ' . self::standaloneUrl(),
            'API: ' . self::apiBaseUrl(),
            'Scraper: ' . self::scraperProxyUrl() . ' → scraper_web',
        ];
        if (!self::erpPublicReady()) {
            $lines[] = 'Lipseste admin/public/import-pro';
        }
        return $lines;
    }

    private static function bootImportStack(): void
    {
        $importBootstrap = self::importBootstrapPath();
        if ($importBootstrap !== '') {
            require_once $importBootstrap;
            if (class_exists(ImportPathResolver::class)) {
                ImportPathResolver::applyEnv();
            }
        }
    }

    private static function importBootstrapPath(): string
    {
        $candidates = [];
        if (defined('BESOIU_APP')) {
            $candidates[] = BESOIU_APP . '/Import/bootstrap.php';
        }
        if (defined('BESOIU_ROOT')) {
            $candidates[] = BESOIU_ROOT . '/app/Import/bootstrap.php';
        }
        $candidates[] = dirname(__DIR__, 4) . '/app/Import/bootstrap.php';

        foreach ($candidates as $path) {
            if (is_file($path)) {
                return str_replace('\\', '/', $path);
            }
        }

        return '';
    }

    private static function resolveRoot(): string
    {
        if (class_exists(ImportPathResolver::class)) {
            return ImportPathResolver::matchingProRoot();
        }

        $configFile = dirname(__DIR__, 2) . '/config/import-root.php';
        $candidates = [];
        if (is_file($configFile)) {
            $cfg = require $configFile;
            if (is_array($cfg['candidates'] ?? null)) {
                $candidates = array_map('strval', $cfg['candidates']);
            }
        }

        if (defined('BESOIU_APP')) {
            $candidates[] = BESOIU_APP . '/Import/MatchingPro';
        }
        $candidates[] = dirname(__DIR__, 4) . '/app/Import/MatchingPro';

        foreach ($candidates as $path) {
            $path = rtrim(str_replace('\\', '/', trim($path)), '/');
            if ($path !== '' && is_file($path . '/index.php')) {
                return $path;
            }
        }

        return dirname(__DIR__, 4) . '/app/Import/MatchingPro';
    }
}
