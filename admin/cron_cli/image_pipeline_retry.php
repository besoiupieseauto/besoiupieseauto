<?php

declare(strict_types=1);

/**
 * Cron: produse live fără imagine validă — pipeline Scraper Plan 1→N.
 * Rulare: php admin/cron_cli/image_pipeline_retry.php [--limit=20]
 */

ini_set('display_errors', '1');
error_reporting(E_ALL);

$root = dirname(__DIR__, 2);
chdir($root);

require_once $root . '/admin/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable($root . '/admin');
$dotenv->safeLoad();

$config = require $root . '/admin/config/config.php';
\Config\Database::getInstance(
    $config['db_host'],
    $config['db_name'],
    $config['db_user'],
    $config['db_pass'],
    'default'
);

$pdo = \Config\Database::getDB('default');

$limit = 20;
foreach ($argv ?? [] as $arg) {
    if (preg_match('/^--limit=(\d+)$/', (string) $arg, $m)) {
        $limit = max(1, min(100, (int) $m[1]));
    }
}

define('IMPORT_PRODUCE_SKIP_HTTP', true);
require_once $root . '/lib/Scraper/ImageSearchService.php';
\ImageSearchService::boot();

$stmt = $pdo->prepare("
    SELECT id, randomn_id, pName, pCode, pBrand, pCategory, pSubcategory, pOem, pImages, pImageSource, raw_json
    FROM produse
    WHERE status <> '0'
      AND (pImages IS NULL OR pImages = '' OR pImages = '[]' OR pImageSource IN ('missing', 'csv', 'supplier', 'import'))
    ORDER BY id DESC
    LIMIT " . (int) $limit
);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$stats = ['scanned' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0];

foreach ($rows as $row) {
    ++$stats['scanned'];
    if (!import_should_fetch_tecdoc_image($row)) {
        ++$stats['skipped'];
        continue;
    }

    try {
        $found = \ImageSearchService::findImage($row, ['force' => true]);
        $url = trim((string) ($found['url'] ?? ''));
        if ($url === '') {
            ++$stats['skipped'];
            continue;
        }

        $updated = ImportImageBridge::applyHit($row, $found);
        $upd = $pdo->prepare('UPDATE produse SET pImages = ?, pImageSource = ?, raw_json = ? WHERE id = ?');
        $upd->execute([
            (string) ($updated['pImages'] ?? '[]'),
            (string) ($updated['pImageSource'] ?? 'tecdoc_api'),
            (string) ($updated['raw_json'] ?? '{}'),
            (int) $row['id'],
        ]);
        ++$stats['updated'];
        echo 'OK #' . $row['id'] . ' ' . ($row['pName'] ?? '') . ' → ' . $url . PHP_EOL;
    } catch (Throwable $e) {
        ++$stats['errors'];
        echo 'ERR #' . $row['id'] . ': ' . $e->getMessage() . PHP_EOL;
    }
}

echo json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
