<?php
declare(strict_types=1);

/**
 * Repară coada importreview: titluri WEB/MP + lane no_image pentru pending.
 *
 * php app/Backend/tools/repair_importreview_titles_lanes.php [--limit=200] [--dry-run]
 */

$root = dirname(__DIR__, 3);
require $root . '/admin/bootstrap.php';
require BESOIU_BACKEND . '/vendor/autoload.php';

$envDir = is_file($root . '/.env') ? $root : BESOIU_CONFIG;
if (class_exists(\Dotenv\Dotenv::class) && is_file($envDir . '/.env')) {
    \Dotenv\Dotenv::createImmutable($envDir)->safeLoad();
}

$config = require BESOIU_CONFIG . '/config.php';
\Config\Database::getInstance(
    (string) ($config['db_host'] ?? '127.0.0.1'),
    (string) ($config['db_name'] ?? 'besoiupieseauto.ro'),
    (string) ($config['db_user'] ?? 'root'),
    (string) ($config['db_pass'] ?? '')
);

if (!defined('IMPORT_PRODUCE_SKIP_HTTP')) {
    define('IMPORT_PRODUCE_SKIP_HTTP', true);
}
if (!defined('IMPORT_ACTION_SKIP_HTTP')) {
    define('IMPORT_ACTION_SKIP_HTTP', true);
}

\Besoiu\Services\Import\ImportLibLoader::bootForQueueActions();
require_once dirname(__DIR__, 2) . '/Import/MatchingPro/api/lib/ImportCardStaging.php';
$pdo = \Config\Database::getDB();

use Besoiu\Services\ProductCardFormationService;

$limit = 200;
$dryRun = in_array('--dry-run', $argv ?? [], true);
foreach ($argv ?? [] as $arg) {
    if (str_starts_with((string) $arg, '--limit=')) {
        $limit = max(1, (int) substr((string) $arg, 8));
    }
}

$hasLaneCol = false;
try {
    $chk = $pdo->query(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'import_produse'
           AND COLUMN_NAME = 'import_lane'"
    );
    $hasLaneCol = ((int) $chk->fetchColumn()) > 0;
} catch (Throwable) {
    $hasLaneCol = false;
}

$sql = "SELECT id, pName, pNameMarketplace, pCode, pBrand, pMarca, pModel, pMotorizare,
               pCategory, pSubcategory, pCompatibilitati, pOem, pImages, pImageSource,
               pBasePrice, pStock, pNote, pNoteWebsite, pNoteMarketplace, raw_json, status"
    . ($hasLaneCol ? ', import_lane' : '')
    . " FROM import_produse
        WHERE status = 'pending'
        ORDER BY id DESC
        LIMIT " . (int) $limit;

$rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
$formation = new ProductCardFormationService();

$stats = [
    'scanned' => 0,
    'titles_updated' => 0,
    'vehicle_cleaned' => 0,
    'lane_no_image' => 0,
    'lane_restored' => 0,
    'skipped' => 0,
];

foreach ($rows as $row) {
    ++$stats['scanned'];
    $id = (int) ($row['id'] ?? 0);
    if ($id <= 0) {
        continue;
    }

    $raw = json_decode((string) ($row['raw_json'] ?? '{}'), true);
    if (!is_array($raw)) {
        $raw = [];
    }

    $beforeName = trim((string) ($row['pName'] ?? ''));
    $beforeMp = trim((string) ($row['pNameMarketplace'] ?? ''));
    $beforeMarca = trim((string) ($row['pMarca'] ?? ''));
    $beforeModel = trim((string) ($row['pModel'] ?? ''));
    $beforeMotor = trim((string) ($row['pMotorizare'] ?? ''));

    // 1) Re-extrage / curăță vehicul din compat (dump multi-marcă din coadă veche)
    $product = $row;
    $compat = trim((string) ($product['pCompatibilitati'] ?? ''));
    $marca = trim((string) ($product['pMarca'] ?? ''));
    $model = trim((string) ($product['pModel'] ?? ''));
    $motor = trim((string) ($product['pMotorizare'] ?? ''));

    $needsVehicleRepair = str_contains($marca, ',')
        || str_contains($model, ',')
        || str_contains($model, 'VOLVO')
        || str_starts_with(ltrim($motor), ':')
        || preg_match('/^(I{1,3}|IV|V)\b/u', $model);

    if ($needsVehicleRepair && $compat !== '') {
        $fromCompat = ImportCardStaging::primaryVehicleFromCompatText($compat);
        if (trim((string) ($fromCompat['marca'] ?? '')) !== '') {
            $marca = trim((string) $fromCompat['marca']);
        }
        if (trim((string) ($fromCompat['model'] ?? '')) !== '') {
            $model = trim((string) $fromCompat['model']);
        }
    } elseif (str_contains($marca, ',')) {
        $marcaParts = array_values(array_filter(array_map('trim', explode(',', $marca))));
        $marca = (string) ($marcaParts[0] ?? $marca);
        if (($model === '' || preg_match('/^(I{1,3}|IV|V|\d+)$/u', $model)) && isset($marcaParts[1])) {
            $model = trim($marcaParts[1] . (preg_match('/^(I{1,3}|IV|V|\d+)$/u', $model) ? ' ' . $model : ''));
        }
        if (str_contains($model, ',')) {
            $model = trim((string) explode(',', $model)[0]);
        }
    }
    if (str_contains($motor, "\n") || str_starts_with(ltrim($motor), ':')) {
        $motorLines = array_values(array_filter(array_map(
            static fn(string $l): string => ltrim(trim($l), ': '),
            preg_split('/\r?\n|;/u', $motor) ?: []
        )));
        $motor = '';
        foreach ($motorLines as $line) {
            if ($line === '' || preg_match('/^(I{1,3}|IV|V)\b/u', $line)) {
                continue;
            }
            $motor = $line;
            break;
        }
        if ($motor === '' && $motorLines !== []) {
            $motor = (string) $motorLines[0];
        }
    }
    if (function_exists('import_sanitize_motorizare_value')) {
        $motor = import_sanitize_motorizare_value($motor);
    }
    $product['pMarca'] = $marca;
    $product['pModel'] = $model;
    $product['pMotorizare'] = $motor;

    // 2) Reformare titlu pe datele curățate
    $applied = $formation->applyToImportProduct($product, $raw);
    $product = is_array($applied['product'] ?? null) ? $applied['product'] : $product;
    if (is_array($applied['product_formation'] ?? null)) {
        $raw['product_formation'] = $applied['product_formation'];
    }
    // Păstrează vehiculul curățat (formation nu trebuie să reintroducă dump-ul).
    $product['pMarca'] = $marca;
    $product['pModel'] = $model;
    $product['pMotorizare'] = $motor;

    // 3) Lane imagine
    $product['raw_json'] = json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $product = import_reconcile_import_lane($product);
    $raw = json_decode((string) ($product['raw_json'] ?? '{}'), true);
    if (!is_array($raw)) {
        $raw = [];
    }
    $lane = trim((string) ($product['import_lane'] ?? ($raw['import_lane'] ?? 'standard')));
    if (!in_array($lane, ['standard', 'showcase', 'no_image'], true)) {
        $lane = 'standard';
    }

    $afterName = trim((string) ($product['pName'] ?? ''));
    $afterMp = trim((string) ($product['pNameMarketplace'] ?? ''));
    $changedTitle = ($afterName !== '' && $afterName !== $beforeName)
        || ($afterMp !== '' && $afterMp !== $beforeMp);
    $changedVehicle = $marca !== $beforeMarca || $model !== $beforeModel || $motor !== $beforeMotor;
    $beforeLane = $hasLaneCol
        ? trim((string) ($row['import_lane'] ?? ''))
        : trim((string) (($raw['import_lane'] ?? '') ?: ''));
    // Pentru comparație: NULL/'' = standard
    $beforeLaneNorm = $beforeLane === '' ? 'standard' : $beforeLane;
    $changedLane = $lane !== $beforeLaneNorm;

    if (!$changedTitle && !$changedVehicle && !$changedLane) {
        ++$stats['skipped'];
        continue;
    }

    if ($changedTitle) {
        ++$stats['titles_updated'];
    }
    if ($changedVehicle) {
        ++$stats['vehicle_cleaned'];
    }
    if ($changedLane && $lane === 'no_image') {
        ++$stats['lane_no_image'];
    } elseif ($changedLane && $beforeLaneNorm === 'no_image') {
        ++$stats['lane_restored'];
    }

    echo sprintf(
        "#%d WEB %s → %s | MP %s → %s | lane %s→%s | %s\n",
        $id,
        mb_substr($beforeName, 0, 36),
        mb_substr($afterName, 0, 50),
        mb_substr($beforeMp, 0, 28),
        mb_substr($afterMp, 0, 40),
        $beforeLaneNorm,
        $lane,
        trim((string) ($row['pCode'] ?? ''))
    );

    if ($dryRun) {
        continue;
    }

    $product['raw_json'] = json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $product['import_lane'] = $lane;
    import_sync_prepared_row($pdo, $id, $product);
}

echo "\nDone: scanned={$stats['scanned']} titles={$stats['titles_updated']}"
    . " vehicle={$stats['vehicle_cleaned']} no_image={$stats['lane_no_image']}"
    . " restored={$stats['lane_restored']} skipped={$stats['skipped']}"
    . ($dryRun ? ' [DRY-RUN]' : '') . "\n";
