<?php
declare(strict_types=1);

/**
 * Verifică migrarea Import + bridge scraper_web + Ollama.
 * Rulează: php modules/scraper_web/tools/test_module_bridge.php
 */

$autoRoot = dirname(__DIR__, 3);
define('BESOIU_ROOT', $autoRoot);
define('BESOIU_APP', $autoRoot . '/app');
define('BESOIU_BACKEND', BESOIU_APP . '/Backend');

$envFile = BESOIU_APP . '/Config/.env';
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        if ($key === '') {
            continue;
        }
        $value = trim($value, " \t\"'");
        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}

require_once BESOIU_APP . '/Import/bootstrap.php';

use Besoiu\Import\Support\ImportPathResolver;

$checks = [];

$scraper = ImportPathResolver::scraperRoot();
$checks['scraper_bootstrap'] = is_file($scraper . '/bootstrap.php') ? 'OK' : 'MISSING: ' . $scraper;

$orchestr = ImportPathResolver::orchestrRoot();
$checks['orchestr_bootstrap'] = is_file($orchestr . '/bootstrap.php') ? 'OK' : 'MISSING: ' . $orchestr;

$checks['data_root'] = ImportPathResolver::dataRoot();
$checks['metro_root'] = ImportPathResolver::metroRoot() ?: '(empty)';

$checks['ollama_enabled'] = getenv('OLLAMA_ENABLED') ?: '(not set)';
$checks['ollama_model'] = getenv('OLLAMA_MODEL') ?: '(not set)';
$checks['ollama_vision_model'] = getenv('OLLAMA_VISION_MODEL') ?: '(not set)';
$checks['ollama_use_besoiu'] = getenv('OLLAMA_USE_BESOIU_MODEL') ?: '(not set)';

require_once $scraper . '/bootstrap.php';
$checks['scraper_ollama_available'] = class_exists('ScraperOllamaClient', false)
    && ScraperOllamaClient::isAvailable()
    ? 'yes'
    : 'no (verifică ollama serve)';

require_once $orchestr . '/bootstrap.php';
$checks['orchestr_metro_bridge'] = OrchestrMetroBridge::isAvailable() ? 'yes' : 'no';

echo "Import migration bridge test\n";
echo str_repeat('-', 40) . "\n";
foreach ($checks as $key => $value) {
    echo sprintf("%-28s %s\n", $key . ':', (string) $value);
}

$failed = array_filter($checks, static fn ($v, $k) => str_contains((string) $v, 'MISSING'), ARRAY_FILTER_USE_BOTH);
exit($failed === [] ? 0 : 1);
