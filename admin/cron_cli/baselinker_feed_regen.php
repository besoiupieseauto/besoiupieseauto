<?php

declare(strict_types=1);

/**
 * tm_108 — Regenerare feed BaseLinker (CLI / queue worker).
 */

function baselinker_feed_regen_job(PDO $pdo, array $payload = []): void
{
    unset($payload);

    $lib = dirname(__DIR__, 2) . '/system/baselinker-feed.php';
    if (!is_file($lib)) {
        throw new RuntimeException('baselinker-feed.php lipseste.');
    }

    require_once $lib;

    $result = baselinker_feed_regenerate($pdo);
    echo (string) ($result['message'] ?? 'Feed regenerat.') . "\n";
}

if (PHP_SAPI === 'cli' && basename((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === 'baselinker_feed_regen.php') {
    require_once __DIR__ . '/../vendor/autoload.php';

    $dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
    $dotenv->safeLoad();
    $config = require dirname(__DIR__) . '/config/config.php';
    \Config\Database::getInstance(
        $config['db_host'],
        $config['db_name'],
        $config['db_user'],
        $config['db_pass']
    );

    baselinker_feed_regen_job(\Config\Database::getDB());
}
