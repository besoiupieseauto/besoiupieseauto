<?php
declare(strict_types=1);

/**
 * Creează CSV de test în folderul fiecărui furnizor activ (pentru scan Cron).
 *
 *   php admin/tools/seed_cron_test_csv.php
 */
require dirname(__DIR__) . '/vendor/autoload.php';
Dotenv\Dotenv::createImmutable(dirname(__DIR__))->safeLoad();
$config = require dirname(__DIR__) . '/config/config.php';
$pdo = new PDO(
    'mysql:host=' . $config['db_host'] . ';dbname=' . $config['db_name'] . ';charset=utf8mb4',
    $config['db_user'],
    $config['db_pass']
);

require_once dirname(__DIR__) . '/src/Controllers/Furnizori/SupplierFeedFolderService.php';

$feed = new Evasystem\Controllers\Furnizori\SupplierFeedFolderService();
$rows = $pdo->query("SELECT code, randomn_id FROM furnizori WHERE status = 'active' ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
if ($rows === []) {
    fwrite(STDERR, "Niciun furnizor activ.\n");
    exit(1);
}

$content = "Cod articol,Producator,Denumire,Pret,Stoc\n"
    . "04006,KROON OIL,\"Lichid de frana KROON OIL Drauliquid-S DOT 4 04006\",15.50,5\n"
    . "TEST001,TEST BRAND,\"Ulei motor test 5W30 1L\",45.00,3\n";

$created = 0;
foreach ($rows as $row) {
    $code = strtoupper(trim((string) ($row['code'] ?? '')));
    $randomnId = (int) ($row['randomn_id'] ?? 0);
    if ($code === '' || $randomnId <= 0) {
        continue;
    }

    $folder = $feed->ensureFolder($code, $randomnId);
    $csv = $folder['path'] . DIRECTORY_SEPARATOR . 'test_cron_import.csv';
    file_put_contents($csv, $content);
    echo "OK: {$csv} ({$code})\n";
    ++$created;
}

echo $created > 0 ? "Total: {$created} furnizori.\n" : "Nimic creat.\n";
