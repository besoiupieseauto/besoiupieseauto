<?php
declare(strict_types=1);

final class ScraperPaths
{
    public static function projectRoot(): string
    {
        return dirname(__DIR__, 2);
    }

    public static function storageDir(): string
    {
        return self::projectRoot() . '/storage/scraper';
    }

    public static function rawDir(): string
    {
        return self::storageDir() . '/raw';
    }

    public static function jsonDir(): string
    {
        return self::storageDir() . '/json';
    }

    public static function logsDir(): string
    {
        return self::storageDir() . '/logs';
    }

    /** Imagini produse ePiesa — servite public din /assets/scraper/epiesa/ */
    public static function imagesDir(): string
    {
        return self::projectRoot() . '/assets/scraper/epiesa';
    }

    public static function imagesPublicUrl(): string
    {
        return '/assets/scraper/epiesa';
    }

    public static function ensureDirs(): void
    {
        foreach ([self::rawDir(), self::jsonDir(), self::logsDir(), self::imagesDir()] as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
        }
    }

    public static function latestJsonPath(): string
    {
        return self::jsonDir() . '/epiesa_latest.json';
    }

    public static function catalogJsonPath(): string
    {
        return self::jsonDir() . '/products_catalog.json';
    }

    public static function statusJsonPath(): string
    {
        return self::storageDir() . '/status.json';
    }

    public static function logFile(): string
    {
        return self::logsDir() . '/scraper.log';
    }
}
