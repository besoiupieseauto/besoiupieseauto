<?php

declare(strict_types=1);

namespace Besoiu\Services\AiSupervisor\Support;

use Config\Database;

/**
 * Inițializare mediu CLI/cron pentru module supervizor (DB + .env).
 */
final class AiSupervisorBootstrap
{
    private static bool $booted = false;

    public static function ensureDatabase(): void
    {
        if (self::$booted) {
            return;
        }

        try {
            Database::getDB();
            self::$booted = true;

            return;
        } catch (\Throwable) {
            // continue bootstrap
        }

        $adminDir = dirname(__DIR__, 4);
        $projectRoot = dirname($adminDir);

        if (!class_exists(\Dotenv\Dotenv::class)) {
            $autoload = $adminDir . '/vendor/autoload.php';
            if (is_file($autoload)) {
                require_once $autoload;
            }
        }

        if (class_exists(\Dotenv\Dotenv::class)) {
            $dotenv = \Dotenv\Dotenv::createImmutable($adminDir);
            $dotenv->safeLoad();
        }

        $configPath = $adminDir . '/config/config.php';
        if (!is_file($configPath)) {
            return;
        }

        $config = require $configPath;
        if (!is_array($config)) {
            return;
        }

        Database::getInstance(
            (string) ($config['db_host'] ?? 'localhost'),
            (string) ($config['db_name'] ?? ''),
            (string) ($config['db_user'] ?? ''),
            (string) ($config['db_pass'] ?? ''),
            'default'
        );

        self::$booted = true;
    }
}
