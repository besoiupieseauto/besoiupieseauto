<?php
declare(strict_types=1);

/**
 * CLI: php tools/scraper/epiesa_category.php [url]
 * Necesită SCRAPE_DO_TOKEN în mediu sau admin/.env (încărcat manual).
 */
$root = dirname(__DIR__, 2);
require_once $root . '/lib/Scraper/EpiesaScrapeJob.php';

$envFile = $root . '/admin/.env';
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$k, $v] = explode('=', $line, 2);
        $k = trim($k);
        if ($k !== '' && getenv($k) === false) {
            putenv($k . '=' . trim($v, " \t\"'"));
        }
    }
}

$url = $argv[1] ?? EpiesaScrapeJob::DEFAULT_CATEGORY_URL;

try {
    $result = EpiesaScrapeJob::run($url, 10);
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'Eroare: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
