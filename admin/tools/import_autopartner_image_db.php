<?php

declare(strict_types=1);

/**
 * Construiește baza MySQL autopartner_images din folderul Autopartner (Desktop / env).
 *
 *   php admin/tools/import_autopartner_image_db.php
 */

set_time_limit(0);
ini_set('memory_limit', '1024M');

$root = dirname(__DIR__, 2);
$php = PHP_BINARY;
if ($php === '') {
    fwrite(STDERR, "PHP CLI indisponibil.\n");
    exit(1);
}

require_once $root . '/app/Legacy/product-code-normalize.php';

$env = ap_image_db_load_env($root);
$host = (string) ($env['DB_HOST'] ?? '127.0.0.1');
$user = (string) ($env['DB_USER'] ?? 'root');
$pass = (string) ($env['DB_PASS'] ?? '');
$dbName = (string) ($env['AUTOPARTNER_IMAGE_DB'] ?? 'autopartner_images');
$sourceDir = ap_image_db_resolve_source($env, $root);

if ($sourceDir === '') {
    fwrite(STDERR, "Nu găsesc folderul Autopartner. Setează AUTOPARTNER_IMAGE_DIR.\n");
    exit(1);
}

$keepImages = in_array('--keep-images', $argv ?? [], true);

echo "Sursă imagini: {$sourceDir}\n";
echo "Bază MySQL: {$dbName} @ {$host}\n";
if ($keepImages) {
    echo "Mod: păstrez imaginile existente, reimport catalog/lookup.\n";
}

ap_image_db_bootstrap($host, $user, $pass, $dbName);
$pdo = ap_image_db_connect($host, $user, $pass, $dbName);
if ($keepImages) {
    ap_image_db_reset_catalog($pdo);
    $imageStats = [
        'inserted' => (int) $pdo->query('SELECT COUNT(*) FROM images')->fetchColumn(),
        'skipped' => 0,
    ];
    echo 'Imagini păstrate: ' . $imageStats['inserted'] . "\n";
} else {
    ap_image_db_apply_schema($pdo, $root);
}

$started = microtime(true);
if (!$keepImages) {
    $imageStats = ap_image_db_import_images($pdo, $sourceDir);
}
$catalogStats = ap_image_db_import_catalog($pdo, $sourceDir);
$eanStats = ap_image_db_import_ean($pdo, $sourceDir);
$stockStats = ap_image_db_import_stock($pdo, $sourceDir);
$lookupStats = ap_image_db_build_lookups($pdo);
ap_image_db_refresh_image_counts($pdo);

$meta = [
    'source_dir' => $sourceDir,
    'imported_at' => date('c'),
    'images' => (string) $imageStats['inserted'],
    'products' => (string) $catalogStats['products'],
    'lookups' => (string) $lookupStats['inserted'],
    'elapsed_sec' => (string) round(microtime(true) - $started, 1),
];
$metaStmt = $pdo->prepare('REPLACE INTO meta (k, v) VALUES (:k, :v)');
foreach ($meta as $k => $v) {
    $metaStmt->execute([':k' => $k, ':v' => $v]);
}

echo "\nGata.\n";
echo '  imagini:  ' . $imageStats['inserted'] . ' (skip ' . $imageStats['skipped'] . ")\n";
echo '  produse:  ' . $catalogStats['products'] . "\n";
echo '  EAN:      ' . $eanStats['updated'] . "\n";
echo '  stoc:     ' . $stockStats['updated'] . "\n";
echo '  lookup:   ' . $lookupStats['inserted'] . "\n";
echo '  durată:   ' . $meta['elapsed_sec'] . "s\n";

/**
 * @return array<string, string>
 */
function ap_image_db_load_env(string $root): array
{
    $out = [];
    $paths = [
        $root . '/app/Config/.env',
        $root . '/.env',
        $root . '/admin/.env',
    ];
    foreach ($paths as $path) {
        if (!is_file($path)) {
            continue;
        }
        $lines = file($path, FILE_IGNORE_NEW_LINES) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }
            [$k, $v] = explode('=', $line, 2);
            $k = trim($k);
            $v = trim($v);
            $v = trim($v, "\"'");
            if ($k !== '' && !isset($out[$k])) {
                $out[$k] = $v;
            }
        }
    }

    return $out;
}

/**
 * @param array<string, string> $env
 */
function ap_image_db_resolve_source(array $env, string $root): string
{
    $candidates = [];
    $fromEnv = trim((string) ($env['AUTOPARTNER_IMAGE_DIR'] ?? getenv('AUTOPARTNER_IMAGE_DIR') ?: ''));
    if ($fromEnv !== '') {
        $candidates[] = $fromEnv;
    }
    $candidates[] = 'C:/Users/Radu/Desktop/autoparner';
    $candidates[] = 'C:/Users/Radu/Desktop/autopartner';
    $candidates[] = $root . '/autoparner';
    $candidates[] = $root . '/autopartner';

    foreach ($candidates as $path) {
        $path = rtrim(str_replace('\\', '/', $path), '/');
        if ($path !== '' && is_dir($path)) {
            return $path;
        }
    }

    return '';
}

function ap_image_db_connect(string $host, string $user, string $pass, ?string $dbName = null): PDO
{
    $dsn = 'mysql:host=' . $host . ';charset=utf8mb4';
    if ($dbName !== null && $dbName !== '') {
        $dsn .= ';dbname=' . $dbName;
    }
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $pdo->exec("SET NAMES utf8mb4");
    $pdo->exec('SET UNIQUE_CHECKS=0');
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');

    return $pdo;
}

function ap_image_db_bootstrap(string $host, string $user, string $pass, string $dbName): void
{
    $safeDb = str_replace('`', '', $dbName);
    $admin = null;
    try {
        $admin = ap_image_db_connect($host, 'root', '');
    } catch (PDOException) {
        $admin = ap_image_db_connect($host, $user, $pass);
    }
    $admin->exec('CREATE DATABASE IF NOT EXISTS `' . $safeDb . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $safeUser = str_replace("'", '', $user);
    if ($safeUser !== '' && $safeUser !== 'root') {
        try {
            $admin->exec("GRANT ALL PRIVILEGES ON `{$safeDb}`.* TO '{$safeUser}'@'localhost'");
            $admin->exec("GRANT ALL PRIVILEGES ON `{$safeDb}`.* TO '{$safeUser}'@'%'");
        } catch (PDOException) {
            // userul curent poate deja avea drepturi
        }
    }
}

function ap_image_db_reset_catalog(PDO $pdo): void
{
    $pdo->exec('DELETE FROM lookup WHERE source <> \'filename\'');
    $pdo->exec('TRUNCATE TABLE products');
}

function ap_image_db_to_utf8(string $value): string
{
    if ($value === '') {
        return '';
    }
    if (preg_match('//u', $value) === 1 && mb_check_encoding($value, 'UTF-8')) {
        return $value;
    }
    foreach (['CP1250', 'ISO-8859-2', 'CP1252'] as $from) {
        $converted = @iconv($from, 'UTF-8//IGNORE', $value);
        if (is_string($converted) && $converted !== '') {
            return $converted;
        }
    }

    return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value) ?? '';
}

function ap_image_db_apply_schema(PDO $pdo, string $root): void
{
    $sqlFile = $root . '/SQL/autopartner_images.sql';
    if (!is_file($sqlFile)) {
        throw new RuntimeException('Lipsește SQL/autopartner_images.sql');
    }
    $sql = (string) file_get_contents($sqlFile);
    $sql = preg_replace('/^--.*$/m', '', $sql) ?? $sql;
    foreach (preg_split('/;\s*\n/', $sql) ?: [] as $stmt) {
        $stmt = trim($stmt);
        if ($stmt === '' || preg_match('/^(CREATE DATABASE|USE)\b/i', $stmt)) {
            continue;
        }
        $pdo->exec($stmt);
    }
}

/**
 * @return array{inserted:int,skipped:int}
 */
function ap_image_db_import_images(PDO $pdo, string $sourceDir): array
{
    $folders = [
        'zdjecia' => ap_image_db_first_existing([
            $sourceDir . '/AP_ZDJECIA/AP_ZDJECIA',
            $sourceDir . '/AP_ZDJECIA',
        ]),
        'miesieczne' => $sourceDir . '/AP_MIESIECZNE',
        'rooks' => $sourceDir . '/AP_ROOKS',
        'root' => $sourceDir,
    ];

    $inserted = 0;
    $skipped = 0;
    $seenRels = [];
    $imageRows = [];
    $lookupRows = [];

    $flush = static function () use ($pdo, &$imageRows, &$lookupRows, &$inserted): void {
        if ($imageRows === []) {
            return;
        }
        $inserted += ap_image_db_insert_image_batch($pdo, $imageRows);
        ap_image_db_insert_lookup_batch($pdo, $lookupRows);
        $imageRows = [];
        $lookupRows = [];
    };

    foreach ($folders as $folder => $absDir) {
        if ($absDir === '' || !is_dir($absDir)) {
            continue;
        }
        echo "Scan {$folder}: {$absDir}\n";
        $handle = opendir($absDir);
        if ($handle === false) {
            continue;
        }
        $seen = 0;
        while (($name = readdir($handle)) !== false) {
            if ($name === '.' || $name === '..') {
                continue;
            }
            $abs = $absDir . DIRECTORY_SEPARATOR . $name;
            if (!is_file($abs)) {
                continue;
            }
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                continue;
            }
            $parsed = ap_image_db_parse_filename($name);
            if ($parsed === null) {
                ++$skipped;
                continue;
            }
            $rel = ap_image_db_rel_path($sourceDir, $abs);
            if ($rel === '' || isset($seenRels[$rel])) {
                ++$skipped;
                continue;
            }
            $seenRels[$rel] = true;
            $size = (int) @filesize($abs);
            if ($size < 512) {
                ++$skipped;
                continue;
            }
            $imageRows[] = [
                $parsed['ap_index'],
                $parsed['norm'],
                $folder,
                $name,
                $rel,
                $parsed['seq'],
                $size,
            ];
            $lookupRows[] = [$parsed['norm'], $parsed['ap_index'], $parsed['norm']];
            if (count($imageRows) >= 400) {
                $flush();
            }
            ++$seen;
            if ($seen % 10000 === 0) {
                echo "  {$folder}: scanate {$seen}, inserate {$inserted}...\n";
            }
        }
        closedir($handle);
        $flush();
        echo "  {$folder}: gata ({$inserted} cumulative)\n";
    }

    unset($seenRels);

    return ['inserted' => $inserted, 'skipped' => $skipped];
}

/**
 * @param list<list<mixed>> $rows
 */
function ap_image_db_insert_image_batch(PDO $pdo, array $rows): int
{
    $placeholders = [];
    $params = [];
    foreach ($rows as $i => $row) {
        $placeholders[] = '(:a' . $i . ',:n' . $i . ',:f' . $i . ',:fn' . $i . ',:r' . $i . ',:s' . $i . ',:z' . $i . ')';
        $params[':a' . $i] = $row[0];
        $params[':n' . $i] = $row[1];
        $params[':f' . $i] = $row[2];
        $params[':fn' . $i] = $row[3];
        $params[':r' . $i] = $row[4];
        $params[':s' . $i] = $row[5];
        $params[':z' . $i] = $row[6];
    }
    $sql = 'INSERT IGNORE INTO images (ap_index, ap_index_norm, folder, file_name, rel_path, seq, file_size) VALUES '
        . implode(',', $placeholders);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->rowCount();
}

/**
 * @param list<list<string>> $rows
 */
function ap_image_db_insert_lookup_batch(PDO $pdo, array $rows): void
{
    if ($rows === []) {
        return;
    }
    $placeholders = [];
    $params = [];
    foreach ($rows as $i => $row) {
        $placeholders[] = '(:n' . $i . ',:a' . $i . ',:an' . $i . ',\'filename\')';
        $params[':n' . $i] = $row[0];
        $params[':a' . $i] = $row[1];
        $params[':an' . $i] = $row[2];
    }
    $sql = 'INSERT IGNORE INTO lookup (norm_code, ap_index, ap_index_norm, source) VALUES '
        . implode(',', $placeholders);
    $pdo->prepare($sql)->execute($params);
}

/**
 * @param list<string> $paths
 */
function ap_image_db_first_existing(array $paths): string
{
    foreach ($paths as $path) {
        if (is_dir($path)) {
            return $path;
        }
    }

    return '';
}

function ap_image_db_rel_path(string $root, string $abs): string
{
    $root = rtrim(str_replace('\\', '/', $root), '/');
    $abs = str_replace('\\', '/', $abs);
    if (str_starts_with($abs, $root . '/')) {
        return substr($abs, strlen($root) + 1);
    }

    return ltrim($abs, '/');
}

/**
 * @return array{ap_index:string,norm:string,seq:int}|null
 */
function ap_image_db_parse_filename(string $name): ?array
{
    $base = pathinfo($name, PATHINFO_FILENAME);
    if ($base === '') {
        return null;
    }
    $seq = 1;
    $index = $base;
    if (preg_match('/^(.*?)(?:_(\d+))?_large$/i', $base, $m)) {
        $index = trim((string) $m[1]);
        $seq = isset($m[2]) && $m[2] !== '' ? (int) $m[2] : 1;
    }
    $norm = besoiu_normalize_product_code($index);
    if ($index === '' || $norm === '') {
        return null;
    }

    return ['ap_index' => $index, 'norm' => $norm, 'seq' => $seq];
}

/**
 * @return array{products:int}
 */
function ap_image_db_import_catalog(PDO $pdo, string $sourceDir): array
{
    $csv = $sourceDir . '/3208129.csv';
    if (!is_file($csv)) {
        echo "Catalog 3208129.csv lipsește — skip produse.\n";
        return ['products' => 0];
    }
    echo "Import catalog: {$csv}\n";
    $fh = fopen($csv, 'rb');
    if ($fh === false) {
        return ['products' => 0];
    }
    $stmt = $pdo->prepare(
        'INSERT INTO products (ap_index, ap_index_norm, tecdoc_index, tecdoc_index_norm, name, brand_code, tecdoc_brand_code, price, currency)
         VALUES (:ap_index, :ap_index_norm, :tecdoc_index, :tecdoc_index_norm, :name, :brand_code, :tecdoc_brand_code, :price, :currency)
         ON DUPLICATE KEY UPDATE
           tecdoc_index=VALUES(tecdoc_index),
           tecdoc_index_norm=VALUES(tecdoc_index_norm),
           name=VALUES(name),
           brand_code=VALUES(brand_code),
           tecdoc_brand_code=VALUES(tecdoc_brand_code),
           price=VALUES(price),
           currency=VALUES(currency)'
    );
    $count = 0;
    while (($row = fgetcsv($fh, 0, ';')) !== false) {
        if (!is_array($row) || count($row) < 3) {
            continue;
        }
        $ap = ap_image_db_to_utf8(trim((string) ($row[0] ?? '')));
        $norm = besoiu_normalize_product_code($ap);
        if ($ap === '' || $norm === '') {
            continue;
        }
        $tecdoc = ap_image_db_to_utf8(trim((string) ($row[2] ?? '')));
        $priceRaw = str_replace(',', '.', trim((string) ($row[5] ?? '')));
        $name = ap_image_db_to_utf8(trim((string) ($row[1] ?? '')));
        try {
            $stmt->execute([
                ':ap_index' => $ap,
                ':ap_index_norm' => $norm,
                ':tecdoc_index' => $tecdoc !== '' ? $tecdoc : null,
                ':tecdoc_index_norm' => $tecdoc !== '' ? (besoiu_normalize_product_code($tecdoc) ?: null) : null,
                ':name' => $name !== '' ? $name : null,
                ':brand_code' => ap_image_db_to_utf8(trim((string) ($row[3] ?? ''))) ?: null,
                ':tecdoc_brand_code' => ap_image_db_to_utf8(trim((string) ($row[6] ?? ''))) ?: null,
                ':price' => is_numeric($priceRaw) ? $priceRaw : null,
                ':currency' => strtoupper(trim((string) ($row[7] ?? 'RON'))) ?: 'RON',
            ]);
        } catch (PDOException $e) {
            fwrite(STDERR, 'Skip catalog row ' . $ap . ': ' . $e->getMessage() . "\n");
            continue;
        }
        ++$count;
        if ($count % 20000 === 0) {
            echo "  catalog: {$count}...\n";
        }
    }
    fclose($fh);
    echo "  catalog: {$count} produse\n";

    return ['products' => $count];
}

/**
 * @return array{updated:int}
 */
function ap_image_db_import_ean(PDO $pdo, string $sourceDir): array
{
    $csv = $sourceDir . '/INDEKS_PARAMETR.csv';
    if (!is_file($csv)) {
        return ['updated' => 0];
    }
    echo "Import EAN: {$csv}\n";
    $fh = fopen($csv, 'rb');
    if ($fh === false) {
        return ['updated' => 0];
    }
    $header = fgetcsv($fh, 0, ';');
    $eanIdx = 8;
    if (is_array($header)) {
        foreach ($header as $i => $col) {
            if (strtoupper(trim((string) $col)) === 'EAN') {
                $eanIdx = (int) $i;
                break;
            }
        }
    }
    $update = $pdo->prepare(
        'UPDATE products SET ean = :ean WHERE ap_index_norm = :norm AND (ean IS NULL OR ean = \'\')'
    );
    $insert = $pdo->prepare(
        'INSERT IGNORE INTO products (ap_index, ap_index_norm, ean) VALUES (:ap, :norm, :ean)'
    );
    $updated = 0;
    while (($row = fgetcsv($fh, 0, ';')) !== false) {
        if (!is_array($row)) {
            continue;
        }
        $ap = trim((string) ($row[0] ?? ''));
        $ean = preg_replace('/\D+/', '', (string) ($row[$eanIdx] ?? '')) ?? '';
        $norm = besoiu_normalize_product_code($ap);
        if ($norm === '' || strlen($ean) < 8) {
            continue;
        }
        $update->execute([':ean' => $ean, ':norm' => $norm]);
        if ($update->rowCount() > 0) {
            ++$updated;
            continue;
        }
        $insert->execute([':ap' => $ap, ':norm' => $norm, ':ean' => $ean]);
        if ($insert->rowCount() > 0) {
            ++$updated;
        }
    }
    fclose($fh);
    echo "  EAN: {$updated}\n";

    return ['updated' => $updated];
}

/**
 * @return array{updated:int}
 */
function ap_image_db_import_stock(PDO $pdo, string $sourceDir): array
{
    $csv = $sourceDir . '/STANY.csv';
    if (!is_file($csv)) {
        return ['updated' => 0];
    }
    echo "Import stoc: {$csv}\n";
    $fh = fopen($csv, 'rb');
    if ($fh === false) {
        return ['updated' => 0];
    }
    $sums = [];
    while (($row = fgetcsv($fh, 0, ';')) !== false) {
        if (!is_array($row) || count($row) < 2) {
            continue;
        }
        $norm = besoiu_normalize_product_code((string) ($row[0] ?? ''));
        if ($norm === '') {
            continue;
        }
        $qty = (float) str_replace(',', '.', (string) ($row[1] ?? '0'));
        $sums[$norm] = ($sums[$norm] ?? 0.0) + $qty;
    }
    fclose($fh);
    $stmt = $pdo->prepare('UPDATE products SET stock = :stock WHERE ap_index_norm = :norm');
    $updated = 0;
    foreach ($sums as $norm => $qty) {
        $stmt->execute([':stock' => $qty, ':norm' => $norm]);
        if ($stmt->rowCount() > 0) {
            ++$updated;
        }
    }
    echo "  stoc: {$updated} produse\n";

    return ['updated' => $updated];
}

/**
 * @return array{inserted:int}
 */
function ap_image_db_build_lookups(PDO $pdo): array
{
    echo "Construiesc lookup TecDoc / EAN / brand...\n";
    $pdo->exec(
        "INSERT IGNORE INTO lookup (norm_code, ap_index, ap_index_norm, source)
         SELECT ap_index_norm, ap_index, ap_index_norm, 'catalog_ap'
         FROM products
         WHERE ap_index_norm <> ''"
    );
    $pdo->exec(
        "INSERT IGNORE INTO lookup (norm_code, ap_index, ap_index_norm, source)
         SELECT tecdoc_index_norm, ap_index, ap_index_norm, 'catalog_tecdoc'
         FROM products
         WHERE tecdoc_index_norm IS NOT NULL AND tecdoc_index_norm <> ''"
    );
    $pdo->exec(
        "INSERT IGNORE INTO lookup (norm_code, ap_index, ap_index_norm, source)
         SELECT ean, ap_index, ap_index_norm, 'ean'
         FROM products
         WHERE ean IS NOT NULL AND ean <> ''"
    );
    $pdo->exec(
        "INSERT IGNORE INTO lookup (norm_code, ap_index, ap_index_norm, source)
         SELECT CONCAT(UPPER(brand_code), ap_index_norm), ap_index, ap_index_norm, 'brand_code'
         FROM products
         WHERE brand_code IS NOT NULL AND brand_code <> '' AND ap_index_norm <> ''"
    );
    $inserted = (int) $pdo->query('SELECT COUNT(*) FROM lookup')->fetchColumn();
    echo "  lookup total: {$inserted}\n";

    return ['inserted' => $inserted];
}

function ap_image_db_refresh_image_counts(PDO $pdo): void
{
    $pdo->exec(
        'UPDATE products p
         JOIN (
           SELECT ap_index_norm, COUNT(*) AS cnt
           FROM images
           GROUP BY ap_index_norm
         ) x ON x.ap_index_norm = p.ap_index_norm
         SET p.image_count = x.cnt'
    );
}
