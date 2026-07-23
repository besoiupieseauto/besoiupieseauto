<?php
declare(strict_types=1);

/**
 * Copiază descrierea Base din raw_json.product_summary.description → pNote*
 * pentru rândurile pending fără notă (previzualizare / editare).
 *
 * php app/Backend/tools/backfill_importreview_descriptions.php [--limit=500] [--dry-run]
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

$limit = 500;
$dryRun = in_array('--dry-run', $argv ?? [], true);
foreach ($argv ?? [] as $arg) {
    if (str_starts_with((string) $arg, '--limit=')) {
        $limit = max(1, (int) substr((string) $arg, 8));
    }
}

$pdo = \Config\Database::getDB();
$sql = "SELECT id, pNote, pNoteWebsite, pNoteMarketplace, raw_json
        FROM import_produse
        WHERE status = 'pending'
          AND (pNote IS NULL OR TRIM(pNote) = '')
        ORDER BY id DESC
        LIMIT " . (int) $limit;

$rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
$updated = 0;
$skipped = 0;

$upd = $pdo->prepare(
    'UPDATE import_produse
     SET pNote = :note, pNoteWebsite = :web, pNoteMarketplace = :mp
     WHERE id = :id'
);

foreach ($rows as $row) {
    $id = (int) ($row['id'] ?? 0);
    $raw = json_decode((string) ($row['raw_json'] ?? '{}'), true);
    if (!is_array($raw)) {
        ++$skipped;
        continue;
    }

    $desc = '';
    if (is_array($raw['product_summary'] ?? null)) {
        $desc = trim((string) ($raw['product_summary']['description'] ?? ''));
    }
    if ($desc === '') {
        $desc = trim((string) ($raw['description'] ?? ''));
    }
    if ($desc === '') {
        ++$skipped;
        continue;
    }

    $web = trim((string) ($row['pNoteWebsite'] ?? ''));
    $mp = trim((string) ($row['pNoteMarketplace'] ?? ''));
    if ($web === '') {
        $web = $desc;
    }
    if ($mp === '') {
        $mp = $desc;
    }

    echo "id={$id} note_len=" . strlen($desc) . ($dryRun ? " [dry-run]\n" : "\n");
    if (!$dryRun) {
        $upd->execute([
            ':note' => $desc,
            ':web' => $web,
            ':mp' => $mp,
            ':id' => $id,
        ]);
    }
    ++$updated;
}

echo json_encode([
    'scanned' => count($rows),
    'updated' => $updated,
    'skipped' => $skipped,
    'dry_run' => $dryRun,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
