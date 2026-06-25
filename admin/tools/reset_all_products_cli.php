<?php
declare(strict_types=1);

/**
 * Șterge toate produsele din magazin + coada import/scan + vitrină + catalog parsat ePiesa.
 *
 *   cd admin
 *   php tools/reset_all_products_cli.php --dry-run
 *   php tools/reset_all_products_cli.php --confirm
 */
require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__, 2) . '/lib/Scraper/EpiesaCatalog.php';

Dotenv\Dotenv::createImmutable(dirname(__DIR__))->safeLoad();
$config = require dirname(__DIR__) . '/config/config.php';

$pdo = new PDO(
    'mysql:host=' . $config['db_host'] . ';dbname=' . $config['db_name'] . ';charset=utf8mb4',
    $config['db_user'],
    $config['db_pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$confirm = in_array('--confirm', $argv ?? [], true);
$dryRun = in_array('--dry-run', $argv ?? [], true) || !$confirm;

if (!$dryRun && !$confirm) {
    fwrite(STDERR, "ATENȚIE: această operație șterge TOATE produsele.\n");
    fwrite(STDERR, "Preview:  php tools/reset_all_products_cli.php --dry-run\n");
    fwrite(STDERR, "Execută:   php tools/reset_all_products_cli.php --confirm\n");
    exit(1);
}

function table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
    );
    $stmt->execute([$table]);

    return (int) $stmt->fetchColumn() > 0;
}

function count_table(PDO $pdo, string $table): int
{
    if (!table_exists($pdo, $table)) {
        return -1;
    }

    return (int) $pdo->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn();
}

/** @return list<string> */
function list_import_job_files(string $root): array
{
    if (!is_dir($root)) {
        return [];
    }

    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $files[] = $file->getPathname();
        }
    }

    return $files;
}

/** @return array{deleted:int,errors:int} */
function clear_directory_files(string $root, bool $execute): array
{
    $deleted = 0;
    $errors = 0;
    foreach (list_import_job_files($root) as $path) {
        if (!$execute) {
            $deleted++;
            continue;
        }
        if (@unlink($path)) {
            $deleted++;
        } else {
            $errors++;
        }
    }

    return ['deleted' => $deleted, 'errors' => $errors];
}

$tables = [
    'products_oem' => 'Coduri OEM asociate produselor',
    'import_produse' => 'Coadă import / scan furnizori (staging)',
    'cart_items' => 'Coș server-side (sesiuni)',
    'produse' => 'Catalog live magazin + vitrină',
];

echo ($dryRun ? "=== DRY RUN (fără modificări) ===\n" : "=== RESET PRODUSE — EXECUTARE ===\n");

$before = [];
foreach ($tables as $table => $label) {
    $count = count_table($pdo, $table);
    $before[$table] = $count;
    $display = $count < 0 ? 'lipsă' : (string) $count;
    echo sprintf("  %-18s %6s  — %s\n", $table . ':', $display, $label);
}

$vitrinaBefore = table_exists($pdo, 'produse')
    ? (int) $pdo->query("SELECT COUNT(*) FROM produse WHERE COALESCE(pVitrina, 0) = 1")->fetchColumn()
    : 0;
echo sprintf("  %-18s %6s  — %s\n", 'pVitrina:', (string) $vitrinaBefore, 'Produse afișate pe homepage');

$storageRoots = [
    dirname(__DIR__) . '/storage/imports' => 'Fișiere CSV/job import admin',
    dirname(__DIR__, 2) . '/uploads/products' => 'Imagini produse încărcate local',
];

$scraperBefore = 0;
try {
    $scraperBefore = count(EpiesaCatalog::listProducts());
} catch (Throwable $e) {
    $scraperBefore = -1;
}

foreach ($storageRoots as $path => $label) {
    $fileCount = count(list_import_job_files($path));
    echo sprintf("  %-18s %6d  — %s\n", basename($path) . ':', $fileCount, $label);
}

$scraperDisplay = $scraperBefore < 0 ? 'lipsă' : (string) $scraperBefore;
echo sprintf("  %-18s %6s  — %s\n", 'scraper_epiesa:', $scraperDisplay, 'Produse parsate vitrină / speciale homepage');

if ($dryRun) {
    echo "\nPentru ștergere reală: php tools/reset_all_products_cli.php --confirm\n";
    exit(0);
}

$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

try {
    foreach (array_keys($tables) as $table) {
        if (!table_exists($pdo, $table)) {
            continue;
        }
        $pdo->exec('TRUNCATE TABLE `' . $table . '`');
    }

    $pdo->exec('ALTER TABLE `produse` AUTO_INCREMENT = 1');
    if (table_exists($pdo, 'import_produse')) {
        $pdo->exec('ALTER TABLE `import_produse` AUTO_INCREMENT = 1');
    }
    if (table_exists($pdo, 'products_oem')) {
        $pdo->exec('ALTER TABLE `products_oem` AUTO_INCREMENT = 1');
    }
} catch (Throwable $e) {
    fwrite(STDERR, 'Eroare BD: ' . $e->getMessage() . PHP_EOL);
    exit(1);
} finally {
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
}

$fileStats = ['deleted' => 0, 'errors' => 0];
foreach (array_keys($storageRoots) as $path) {
    $result = clear_directory_files($path, true);
    $fileStats['deleted'] += $result['deleted'];
    $fileStats['errors'] += $result['errors'];
}

$scraperAfter = 0;
try {
    $scraperResult = EpiesaCatalog::clearAll();
    $fileStats['deleted'] += (int) ($scraperResult['files_deleted'] ?? 0);
    $fileStats['errors'] += (int) ($scraperResult['errors'] ?? 0);
    $scraperAfter = count(EpiesaCatalog::listProducts());
} catch (Throwable $e) {
    fwrite(STDERR, 'Eroare scraper: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

echo "\n=== REZULTAT ===\n";
foreach ($tables as $table => $label) {
    $after = count_table($pdo, $table);
    $was = $before[$table] ?? 0;
    echo sprintf("  %s: %d → %d\n", $table, max(0, $was), max(0, $after));
}

$vitrinaAfter = table_exists($pdo, 'produse')
    ? (int) $pdo->query("SELECT COUNT(*) FROM produse WHERE COALESCE(pVitrina, 0) = 1")->fetchColumn()
    : 0;
echo sprintf("  pVitrina: %d → %d\n", $vitrinaBefore, $vitrinaAfter);
echo sprintf(
    "  scraper_epiesa: %d → %d\n",
    max(0, $scraperBefore),
    max(0, $scraperAfter)
);
echo sprintf("  fișiere șterse: %d (erori: %d)\n", $fileStats['deleted'], $fileStats['errors']);
echo "Gata — catalogul este gol, poți reimporta de la zero.\n";
