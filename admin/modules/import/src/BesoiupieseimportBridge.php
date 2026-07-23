<?php
declare(strict_types=1);

namespace Besoiu\Modules\Import;

use Besoiu\Core\Module\ModulesPaths;

/**
 * Legătură între admin besoiupieseauto.ro și proiectul besoiupieseimport (Laragon).
 */
final class BesoiupieseimportBridge
{
    private static ?array $config = null;

    /** @return array<string, mixed> */
    public static function config(): array
    {
        if (self::$config !== null) {
            return self::$config;
        }

        $local = ModulesPaths::backendRoot() . '/config/besoiupieseimport_sync.php';
        if (is_file($local)) {
            $loaded = require $local;
            self::$config = is_array($loaded) ? $loaded : [];
        } else {
            self::$config = [];
        }

        return self::$config;
    }

    public static function importRoot(): string
    {
        $root = (string) (self::config()['import_root'] ?? 'F:/laragon/www/besoiupieseimport');
        $real = realpath($root);

        return $real !== false ? str_replace('\\', '/', $real) : rtrim(str_replace('\\', '/', $root), '/');
    }

    public static function publicBaseUrl(): string
    {
        $url = trim((string) (self::config()['public_url'] ?? ''));
        if ($url !== '') {
            return rtrim($url, '/');
        }

        return 'http://besoiupieseimport.test';
    }

    public static function isAvailable(): bool
    {
        $root = self::importRoot();

        return $root !== '' && is_dir($root);
    }

    public static function path(string $relative = ''): string
    {
        $base = self::importRoot();
        $rel = ltrim(str_replace('\\', '/', $relative), '/');

        return $rel === '' ? $base : $base . '/' . $rel;
    }

    /** @return array<string, string> */
    public static function urls(): array
    {
        $base = self::publicBaseUrl();

        return [
            'root' => $base,
            'bovsoft' => $base . '/Prelucrare%20fisiere%20bovsoft-base/Procesare%20fisier%20import%20Base.html',
            'fetch_product' => $base . '/fetch%20product/',
            'scraper' => $base . '/Scraper/',
            'scraper_api' => $base . '/Scraper/api/index.php',
            'product_image' => $base . '/Prelucrare%20fisiere%20bovsoft-base/api/product-image.php',
            'index_dashboard' => $base . '/Prelucrare%20fisiere%20bovsoft-base/api/index-dashboard.php',
        ];
    }

    /** @return array<string, mixed> */
    public static function status(): array
    {
        $root = self::importRoot();
        $matc = self::path('Fisierile Matc');
        $poze = self::path('Poze');
        $scraper = self::path('Scraper');
        $fetch = self::path('fetch product');

        return [
            'available' => self::isAvailable(),
            'import_root' => $root,
            'public_url' => self::publicBaseUrl(),
            'paths' => [
                'matc' => is_dir($matc),
                'poze' => is_dir($poze),
                'scraper' => is_dir($scraper),
                'fetch_product' => is_dir($fetch),
            ],
            'urls' => self::urls(),
            'sync_enabled' => (bool) (self::config()['enabled'] ?? false),
        ];
    }

    /**
     * Rulează un script PHP din besoiupieseimport (proxy intern).
     */
    public static function runScript(string $relativeScript): void
    {
        $script = self::path($relativeScript);
        if (!is_file($script)) {
            http_response_code(404);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'error' => 'Script negăsit: ' . $relativeScript,
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $dir = dirname($script);
        $prev = getcwd();
        if (is_string($prev)) {
            chdir($dir);
        }

        try {
            require $script;
        } finally {
            if (is_string($prev)) {
                chdir($prev);
            }
        }
    }
}
