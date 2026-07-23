<?php
declare(strict_types=1);

/**
 * Test Import Pro + Scraper Web — 5 pași.
 * Rulează: php modules/import_pro/tools/test_import_scraper_5steps.php
 */

$autoRoot = dirname(__DIR__, 3);
define('BESOIU_ROOT', $autoRoot);
define('BESOIU_APP', $autoRoot . '/app');
define('BESOIU_IMPORT_PUBLIC_WRAPPER', true);
define('BESOIU_IMPORT_WEB_BASE', '/admin/public/import-pro/');

require_once BESOIU_APP . '/Import/bootstrap.php';
require_once $autoRoot . '/admin/bootstrap.php';
require_once $autoRoot . '/admin/vendor/autoload.php';

\Besoiu\Core\Module\ModuleRegistry::instance(
    \Besoiu\Core\Bootstrap\ApiBootstrap::adminRoot()
)->discover();

require_once BESOIU_APP . '/Import/MatchingPro/api/bootstrap.php';
require_once BESOIU_APP . '/Import/MatchingPro/api/lib/ImportScrapedImageStore.php';

spl_autoload_register(static function (string $class): void {
    foreach (['ImportPro', 'ScraperWeb'] as $module) {
        $prefix = 'Besoiu\\Modules\\' . $module . '\\';
        if (!str_starts_with($class, $prefix)) {
            continue;
        }
        $rel = str_replace('\\', '/', substr($class, strlen($prefix)));
        $folder = strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $module) ?? $module);
        $path = dirname(__DIR__, 2) . '/' . $folder . '/src/' . $rel . '.php';
        if (is_file($path)) {
            require_once $path;
        }
    }
});

use Besoiu\Core\Module\OptionalModuleBridge;
use Besoiu\Import\Support\ImportPathResolver;
use Besoiu\Modules\ImportPro\Support\ImportProBridge;
use Besoiu\Modules\ScraperWeb\Support\ScraperWebBridge;

ImportPathResolver::applyEnv();

$results = [];
$fail = 0;

function step_result(array &$results, int &$fail, int $step, string $label, bool $ok, string $detail = ''): void
{
    $results[] = [
        'step' => $step,
        'label' => $label,
        'ok' => $ok,
        'detail' => $detail,
    ];
    if (!$ok) {
        $fail++;
    }
}

echo "=== TEST IMPORT PRO + SCRAPER WEB (5 pasi) ===\n\n";

// Pas 1 — Module active
$importOk = OptionalModuleBridge::isOperational('import_pro');
$scraperOk = OptionalModuleBridge::isOperational('scraper_web');
step_result(
    $results,
    $fail,
    1,
    'Module import_pro + scraper_web active',
    $importOk && $scraperOk,
    'import_pro=' . ($importOk ? 'OK' : 'OFF') . ', scraper_web=' . ($scraperOk ? 'OK' : 'OFF')
);

// Pas 2 — Bridge-uri motor
$bridgeDetail = '';
try {
    ImportProBridge::boot();
    ScraperWebBridge::boot();
    $bridgeDetail = 'ImportPro=' . ImportProBridge::root()
        . ' | Scraper=' . ScraperWebBridge::root()
        . ' | Proxy=' . ImportProBridge::scraperProxyUrl();
    step_result($results, $fail, 2, 'Bridge-uri motor (ImportPro + ScraperWeb)', true, $bridgeDetail);
} catch (Throwable $e) {
    step_result($results, $fail, 2, 'Bridge-uri motor (ImportPro + ScraperWeb)', false, $e->getMessage());
}

// Pas 3 — Proxy scraper legat de scraper_web
$proxyFile = $autoRoot . '/admin/public/import-pro/_proxy/scraper-api.php';
$proxySrc = is_file($proxyFile) ? (string) file_get_contents($proxyFile) : '';
$proxyUsesModule = str_contains($proxySrc, 'ImportProScraperProxyHandler')
    && str_contains($proxySrc, 'ScraperWeb');
step_result(
    $results,
    $fail,
    3,
    'Proxy scraper-api.php → modul scraper_web',
    $proxyUsesModule && is_file($proxyFile),
    is_file($proxyFile) ? basename($proxyFile) : 'missing'
);

// Pas 4 — Salvare imagine in Poze/ImportPro/{produs}/{OEM}.jpg
$testCard = [
    'title' => 'Filtru ulei MANN TEST',
    'sku' => 'HU816X',
    'brand' => 'MANN',
    'scrapeQuery' => 'HU816X filtru ulei',
];
$png = base64_decode(
    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
    true
);
$tmpPublic = $autoRoot . '/assets/scraper/test-import-pro-pixel.png';
$tmpDir = dirname($tmpPublic);
if (!is_dir($tmpDir)) {
    mkdir($tmpDir, 0775, true);
}
file_put_contents($tmpPublic, $png !== false ? $png : '');

$saved = ImportScrapedImageStore::saveFromCard($testCard, '/assets/scraper/test-import-pro-pixel.png');
$absOk = !empty($saved['absolutePath']) && is_file((string) $saved['absolutePath']);
$folderOk = !empty($saved['productFolder']) && str_contains((string) ($saved['relativePath'] ?? ''), 'ImportPro/');
$oemOk = !empty($saved['oemCode']) && strtoupper((string) $saved['oemCode']) === 'HU816X';
$nameOk = !empty($saved['filename']) && str_starts_with((string) $saved['filename'], 'HU816X');
step_result(
    $results,
    $fail,
    4,
    'Salvare Poze/ImportPro/{produs}/{OEM}.jpg',
    !empty($saved['success']) && $absOk && $folderOk && $oemOk && $nameOk,
    ($saved['relativePath'] ?? $saved['error'] ?? 'unknown')
);

// Pas 5 — Servire imagine salvata (proxy scraped-image)
$serveOk = false;
$serveDetail = '';
if (!empty($saved['relativePath'])) {
    $resolved = ImportScrapedImageStore::resolveAbsolutePath((string) $saved['relativePath']);
    $serveFile = $autoRoot . '/admin/public/import-pro/_proxy/scraped-image.php';
    $serveOk = $resolved !== null && is_file($serveFile);
    $serveDetail = $serveOk
        ? 'resolve=' . $resolved . ' | proxy=' . basename($serveFile)
        : 'resolve failed';
}
step_result($results, $fail, 5, 'Servire imagine salvata (scraped-image proxy)', $serveOk, $serveDetail);

foreach ($results as $row) {
    echo sprintf(
        "[Pas %d] %s %s\n         %s\n",
        $row['step'],
        $row['ok'] ? 'OK' : 'FAIL',
        $row['label'],
        $row['detail']
    );
}

echo "\n=== VERDICT ===\n";
if ($fail === 0) {
    echo "TOTUL FUNCTIONEAZA — Import Pro foloseste scraper_web, imaginile se salveaza in Poze/ImportPro/{produs}/{OEM}.jpg\n";
    exit(0);
}

echo "ESUAT — {$fail} pasi cu probleme. Verifica detaliile de mai sus.\n";
exit(1);
