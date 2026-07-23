<?php
declare(strict_types=1);

/**
 * Regenerare descrieri în format Base.html (generateDescription / htmlviewer)
 * pentru rânduri din coadă cu format vechi „Compatibil cu:” plat.
 *
 * php app/Backend/tools/backfill_base_description_format.php [--limit=100] [--dry-run] [--code=13.0460-4804.2]
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

require_once $root . '/app/Import/MatchingPro/api/bootstrap.php';
require_once $root . '/app/Backend/src/Controllers/Produse/import_base_lib.php';
require_once $root . '/app/Import/MatchingPro/api/lib/ImportTecdocMysqlCardBuilder.php';
require_once $root . '/app/Import/MatchingPro/api/lib/ImportTecdocMysqlEnrichment.php';
require_once $root . '/app/Import/MatchingPro/api/lib/ImportCardStaging.php';

$limit = 100;
$dryRun = in_array('--dry-run', $argv ?? [], true);
$onlyCode = '';
foreach ($argv ?? [] as $arg) {
    if (str_starts_with((string) $arg, '--limit=')) {
        $limit = max(1, (int) substr((string) $arg, 8));
    }
    if (str_starts_with((string) $arg, '--code=')) {
        $onlyCode = trim(substr((string) $arg, 7));
    }
}

$pdo = \Config\Database::getDB();
if ($onlyCode !== '') {
    $stmt = $pdo->prepare(
        "SELECT id, pCode, pBrand, pNote, raw_json
         FROM import_produse
         WHERE pCode = :code
         ORDER BY id DESC
         LIMIT " . (int) $limit
    );
    $stmt->execute([':code' => $onlyCode]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} else {
    $rows = $pdo->query(
        "SELECT id, pCode, pBrand, pNote, raw_json
         FROM import_produse
         WHERE status = 'pending'
           AND pNote IS NOT NULL
           AND pNote LIKE '%Compatibil cu:%'
           AND pNote NOT LIKE '%Compatibil cu urmatoarele modele auto%'
         ORDER BY id DESC
         LIMIT " . (int) $limit
    )->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

$upd = $pdo->prepare(
    'UPDATE import_produse
     SET pNote = :note, pNoteWebsite = :web, pNoteMarketplace = :mp, raw_json = :raw
     WHERE id = :id'
);

$updated = 0;
$skipped = 0;
$errors = 0;

foreach ($rows as $row) {
    $id = (int) ($row['id'] ?? 0);
    $note = trim((string) ($row['pNote'] ?? ''));
    if (ImportCardStaging::isBaseDescriptionFormat($note) && $onlyCode === '') {
        ++$skipped;
        continue;
    }

    $raw = json_decode((string) ($row['raw_json'] ?? '{}'), true);
    if (!is_array($raw)) {
        $raw = [];
    }

    $entries = [];
    foreach (['source_rows', 'entries', 'tecdoc_entries'] as $key) {
        if (!empty($raw[$key]) && is_array($raw[$key])) {
            $entries = array_values(array_filter($raw[$key], 'is_array'));
            break;
        }
    }

    $brand = trim((string) ($row['pBrand'] ?? $raw['force_brand'] ?? $raw['tecdoc_brand'] ?? ''));
    $code = trim((string) ($row['pCode'] ?? ''));

    if ($entries === [] && $brand !== '' && $code !== '') {
        try {
            $entries = ImportTecdocMysqlEnrichment::lookupEntries($brand, $code) ?? [];
        } catch (Throwable $e) {
            echo "id={$id} lookup_error=" . $e->getMessage() . "\n";
            ++$errors;
            continue;
        }
    }

    if ($entries === []) {
        echo "id={$id} code={$code} SKIP no_entries\n";
        ++$skipped;
        continue;
    }

    $html = trim(ImportTecdocMysqlCardBuilder::descriptionFromEntries($entries));
    if ($html === '' || !ImportCardStaging::isBaseDescriptionFormat($html)) {
        echo "id={$id} code={$code} SKIP regen_failed\n";
        ++$skipped;
        continue;
    }

    $raw['source_rows'] = array_slice($entries, 0, 250);
    if (!isset($raw['product_summary']) || !is_array($raw['product_summary'])) {
        $raw['product_summary'] = [];
    }
    $htmlMp = $html;
    $htmlWeb = function_exists('import_base_generate_description_website')
        ? trim((string) import_base_generate_description_website($entries))
        : trim((string) import_base_description_website_from_marketplace($htmlMp));
    if ($htmlWeb === '') {
        $htmlWeb = trim((string) import_base_description_website_from_marketplace($htmlMp));
    }
    $raw['product_summary']['description'] = $htmlMp;
    $raw['product_summary']['description_marketplace'] = $htmlMp;
    $raw['product_summary']['description_website'] = $htmlWeb;

    echo "id={$id} code={$code} mp_len=" . strlen($htmlMp) . " web_len=" . strlen($htmlWeb)
        . " nested=" . (preg_match('/<li><b>[^<]+<\/b><ul>/', $htmlMp) ? 'YES' : 'NO')
        . ($dryRun ? " [dry-run]\n" : "\n");

    if (!$dryRun) {
        $upd->execute([
            ':note' => $htmlMp,
            ':web' => $htmlWeb,
            ':mp' => $htmlMp,
            ':raw' => json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ':id' => $id,
        ]);
    }
    ++$updated;
}

echo json_encode([
    'scanned' => count($rows),
    'updated' => $updated,
    'skipped' => $skipped,
    'errors' => $errors,
    'dry_run' => $dryRun,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
