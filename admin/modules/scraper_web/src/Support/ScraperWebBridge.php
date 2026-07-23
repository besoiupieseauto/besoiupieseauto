<?php
declare(strict_types=1);



namespace Besoiu\Modules\ScraperWeb\Support;



use BesoiuImport\Scraper\Application;

use RuntimeException;



/**

 * Conectează modulul ERP la motorul Scraper din app/Import/Scraper.

 */

final class ScraperWebBridge

{

    private static bool $booted = false;



    private static string $root = '';



    public static function boot(): self

    {

        if (!self::$booted) {

            self::bootImportStack();

            self::$root = self::resolveRoot();

            if (!is_file(self::$root . '/bootstrap.php')) {

                throw new RuntimeException(

                    'Motor Scraper negăsit la: ' . self::$root

                    . ' — setează BESOIU_SCRAPER_ROOT sau verifică app/Import/Scraper.'

                );

            }

            require_once self::$root . '/bootstrap.php';

            self::applyErpWebBase();

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



    public static function publicUrl(string $suffix = ''): string

    {

        $configFile = dirname(__DIR__, 2) . '/config/scraper-root.php';

        $public = '/admin/scraper-web';

        if (is_file($configFile)) {

            $cfg = require $configFile;

            if (is_array($cfg) && !empty($cfg['public_url'])) {

                $public = (string) $cfg['public_url'];

            }

        }



        return rtrim($public, '/') . '/' . ltrim($suffix, '/');

    }



    public static function apiEndpointUrl(): string

    {

        return '/admin/public/api/scraper_web_endpoint.php';

    }



    public function application(): Application

    {

        self::boot();



        return Application::instance();

    }



    private static function bootImportStack(): void

    {

        $importBootstrap = self::importBootstrapPath();

        if ($importBootstrap !== '') {

            require_once $importBootstrap;

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

        $candidates[] = 'F:/laragon/www/besoiupieseauto.ro/app/Import/bootstrap.php';



        foreach ($candidates as $path) {

            if (is_file($path)) {

                return str_replace('\\', '/', $path);

            }

        }



        return '';

    }



    private static function resolveRoot(): string

    {

        $env = trim((string) (getenv('BESOIU_SCRAPER_ROOT') ?: ''));

        if ($env !== '' && is_dir($env)) {

            return rtrim(str_replace('\\', '/', $env), '/');

        }



        $configFile = dirname(__DIR__, 2) . '/config/scraper-root.php';

        $candidates = [];

        if (is_file($configFile)) {

            $cfg = require $configFile;

            if (is_array($cfg['candidates'] ?? null)) {

                $candidates = array_map('strval', $cfg['candidates']);

            }

        }



        if (defined('BESOIU_APP')) {

            $candidates[] = BESOIU_APP . '/Import/Scraper';

        }

        $candidates[] = dirname(__DIR__, 4) . '/app/Import/Scraper';

        $candidates[] = 'F:/laragon/www/besoiupieseauto.ro/app/Import/Scraper';

        $candidates[] = 'F:/laragon/www/besoiupieseimport/Scraper';



        foreach ($candidates as $path) {

            $path = rtrim(str_replace('\\', '/', trim($path)), '/');

            if ($path !== '' && is_file($path . '/bootstrap.php')) {

                return $path;

            }

        }



        return 'F:/laragon/www/besoiupieseauto.ro/app/Import/Scraper';

    }



    private static function applyErpWebBase(): void

    {

        $public = rtrim(self::publicUrl(''), '/');

        if ($public !== '') {

            putenv('SCRAPER_WEB_BASE=' . $public);

            $_ENV['SCRAPER_WEB_BASE'] = $public;

            $_SERVER['SCRAPER_WEB_BASE'] = $public;

        }



        $configFile = dirname(__DIR__, 2) . '/config/scraper-root.php';

        $assetsRoot = 'F:/laragon/www/besoiupieseauto.ro/assets/scraper';

        $assetsPublic = '/assets/scraper';

        if (is_file($configFile)) {

            $cfg = require $configFile;

            if (is_array($cfg)) {

                if (!empty($cfg['assets_root'])) {

                    $assetsRoot = (string) $cfg['assets_root'];

                }

                if (!empty($cfg['assets_public_url'])) {

                    $assetsPublic = (string) $cfg['assets_public_url'];

                }

            }

        }



        putenv('SCRAPER_ASSETS_ROOT=' . $assetsRoot);

        $_ENV['SCRAPER_ASSETS_ROOT'] = $assetsRoot;

        $_SERVER['SCRAPER_ASSETS_ROOT'] = $assetsRoot;

        putenv('SCRAPER_ASSETS_PUBLIC_URL=' . $assetsPublic);

        $_ENV['SCRAPER_ASSETS_PUBLIC_URL'] = $assetsPublic;

        $_SERVER['SCRAPER_ASSETS_PUBLIC_URL'] = $assetsPublic;

    }

}

