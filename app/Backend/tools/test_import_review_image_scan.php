<?php
declare(strict_types=1);

/**
 * Test scanare imagini coadă import — scraper_web + Ollama + batch paralel.
 * Usage: php app/Backend/tools/test_import_review_image_scan.php [import_id]
 */
$root = dirname(__DIR__, 3);
require $root . '/admin/bootstrap.php';
require BESOIU_BACKEND . '/vendor/autoload.php';

$envDir = is_file($root . '/.env') ? $root : BESOIU_CONFIG;
if (class_exists(\Dotenv\Dotenv::class) && is_file($envDir . '/.env')) {
    \Dotenv\Dotenv::createImmutable($envDir)->safeLoad();
}

require_once BESOIU_LEGACY . '/shop-db.php';

$config = require BESOIU_CONFIG . '/config.php';
\Config\Database::getInstance(
    (string) ($config['db_host'] ?? '127.0.0.1'),
    (string) ($config['db_name'] ?? 'besoiupieseauto.ro'),
    (string) ($config['db_user'] ?? 'root'),
    (string) ($config['db_pass'] ?? '')
);

use Besoiu\Core\Module\OptionalModuleBridge;
use Besoiu\Services\Import\ImportLibLoader;
use Config\Database;

ImportLibLoader::bootFull(true);

$pdo = Database::getDB();
$targetId = (int) ($argv[1] ?? 0);

echo "=== Test scanare imagini coadă import ===\n\n";

$scraperOk = OptionalModuleBridge::isOperational('scraper_web');
echo 'scraper_web operational: ' . ($scraperOk ? 'DA' : 'NU') . "\n";

$boot = import_image_pipeline_boot();
echo 'pipeline boot: ' . (!empty($boot['ok']) ? 'OK' : ('EȘUAT — ' . ($boot['message'] ?? ''))) . "\n";
echo 'batch size: ' . import_image_job_batch_size() . "\n";
echo 'proc parallel: ' . (import_image_job_proc_parallel_enabled() ? 'DA' : 'NU') . "\n";

if (class_exists('OrchestratorImageQualityGate', false)) {
    $ollama = OrchestratorImageQualityGate::status();
    echo 'Ollama: ' . ($ollama['ollama_available'] ? 'disponibil' : 'indisponibil');
    if (!empty($ollama['vision_model'])) {
        echo ' · vision=' . $ollama['vision_model'];
    }
    echo "\n";
} else {
    echo "Ollama gate: class not loaded (boot scraper_web first)\n";
}

$plans = class_exists('ImageSearchService', false) && \ImageSearchService::hasActiveImagePlans();
echo 'planuri imagini active: ' . ($plans ? 'DA' : 'NU') . "\n\n";

if (empty($boot['ok'])) {
    exit(1);
}

if ($targetId <= 0) {
    $stmt = $pdo->query("SELECT id, pCode, pName, pBrand, pImageSource FROM import_produse WHERE status='pending' ORDER BY id ASC LIMIT 5");
    $rows = $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    if ($rows === []) {
        echo "Nu există produse pending în coadă.\n";
        exit(0);
    }
    echo "Produse pending (primele 5):\n";
    foreach ($rows as $row) {
        echo '  #' . (int) $row['id'] . ' · ' . ($row['pCode'] ?? '—') . ' · ' . ($row['pBrand'] ?? '—') . ' · ' . ($row['pName'] ?? '—') . "\n";
    }
    $targetId = (int) ($rows[0]['id'] ?? 0);
    echo "\nTestez produs #" . $targetId . "...\n\n";
}

$start = import_image_job_start($pdo, [$targetId], '', true);
if (empty($start['ok'])) {
    echo 'START EȘUAT: ' . ($start['message'] ?? '') . "\n";
    exit(2);
}

echo 'Job pornit: ' . ($start['job_id'] ?? '') . ' · total=' . ($start['total'] ?? 0) . "\n";

$steps = 0;
while ($steps < 20) {
    $steps++;
    $step = import_image_job_step($pdo, (string) $start['job_id']);
    if (empty($step['ok'])) {
        echo 'STEP EȘUAT: ' . ($step['error'] ?? '') . "\n";
        exit(3);
    }
    $status = is_array($step['status'] ?? null) ? $step['status'] : [];
    echo '  pas ' . $steps . ': ' . ($status['message'] ?? '—') . ' · progress=' . ($status['progress'] ?? 0) . "%\n";
    if (!empty($status['done']) || !empty($status['failed'])) {
        $result = is_array($step['result'] ?? null) ? $step['result'] : [];
        echo "\nREZULTAT: updated=" . ($result['updated'] ?? 0)
            . ' failed=' . ($result['failed'] ?? 0)
            . ' kept=' . ($result['kept'] ?? 0)
            . ' scanned=' . ($result['scanned'] ?? 0) . "\n";
        if (!empty($result['errors'])) {
            echo "Erori:\n";
            foreach ((array) $result['errors'] as $err) {
                echo '  - ' . $err . "\n";
            }
        }
        $stmt = $pdo->prepare('SELECT pImages, pImageSource FROM import_produse WHERE id=? LIMIT 1');
        $stmt->execute([$targetId]);
        $after = $stmt->fetch(PDO::FETCH_ASSOC);
        if (is_array($after)) {
            echo "\nProdus după scan:\n";
            echo '  pImageSource: ' . ($after['pImageSource'] ?? '—') . "\n";
            echo '  pImages: ' . mb_substr((string) ($after['pImages'] ?? '[]'), 0, 200) . "\n";
        }
        exit((int) ($result['updated'] ?? 0) > 0 ? 0 : 4);
    }
}

echo "Limită pași atinsă.\n";
exit(5);
