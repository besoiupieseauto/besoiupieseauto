<?php

declare(strict_types=1);

set_time_limit(0);
ini_set('memory_limit', '2048M');

$project = dirname(__DIR__, 2);
$catalog = 'C:/Users/Radu/Desktop/autoparner/3208129.csv';
$imgDir = 'C:/Users/Radu/Desktop/autoparner/AP_ZDJECIA/AP_ZDJECIA';
$renDir = 'C:/Users/Radu/Desktop/autoparner/IMAGINI_RENUMITE';
$rejectDir = 'C:/Users/Radu/Desktop/autoparner/RESPINSE_ALGORITM';
$reportPath = 'C:/Users/Radu/Desktop/autoparner/RAPORT_ALGORITM_AUTOPARTNER.txt';
$mapOut = 'C:/Users/Radu/Desktop/autoparner/imagini_mapate_BRAND.csv';

function clean_tecdoc(string $c): string
{
    return strtoupper(str_replace([' ', '-', '.', '/'], '', trim($c)));
}

function clean_photo_a(string $c): string
{
    return strtoupper(str_replace([' ', '-', '.', '/', '_'], '', trim($c)));
}

function brand_token(string $b): string
{
    $b = strtoupper(trim($b));
    $b = str_replace([' ', '-', '.', '/', '_', ':'], '', $b);

    return preg_replace('/[^A-Z0-9]/', '', $b) ?? '';
}

function strip_ap_stem(string $filename): ?string
{
    if ($filename === '' || str_starts_with($filename, '._')) {
        return null;
    }
    $base = pathinfo($filename, PATHINFO_FILENAME);
    if (preg_match('/^(.*)_[1-6]_large$/i', $base, $m)) {
        return $m[1];
    }
    if (preg_match('/^(.*)_large$/i', $base, $m)) {
        return $m[1];
    }

    return null;
}

function quote_sql(PDO $pdo, mixed $v): string
{
    if ($v === null || $v === '') {
        return 'NULL';
    }

    return $pdo->quote((string) $v);
}

function file_ok(string $path): bool
{
    return is_file($path) && filesize($path) >= 512;
}

function to_utf8(string $s): string
{
    if ($s === '') {
        return '';
    }
    $c = @iconv('Windows-1250', 'UTF-8//IGNORE', $s);

    return $c !== false ? $c : $s;
}

$apBrand = [
    'MAX' => 'MAXGEAR', 'OPT' => 'OPTIMAL', 'BOS' => 'BOSCH', 'FEB' => 'FEBIBILSTEIN',
    'QBK' => 'QUICKBRAKE', 'MEY' => 'MEYLE', 'MAG' => 'MAGNETIMARELLI', 'VAL' => 'VALEO',
    'DAY' => 'DAYCO', 'SAC' => 'SACHS', 'MAN' => 'MANNFILTER', 'REI' => 'VICTORREINZ',
    'CON' => 'CONTINENTALCTAM', 'LMI' => 'LEMFORDER', 'BLP' => 'BLUEPRINT', 'JPA' => 'JPGROUP',
    'ORGVAG' => 'OEVAG', 'ORGBMW' => 'OEBMW', 'ORGREN' => 'OERENAULT', 'ORGPSA' => 'OEPSA',
    'ORGFORD' => 'OEFORD',
];
$aliasFile = $project . '/app/Import/MatchingPro/config/tecdoc_brand_aliases.json';
if (is_file($aliasFile)) {
    $json = json_decode((string) file_get_contents($aliasFile), true);
    foreach (($json['aliases'] ?? []) as $from => $to) {
        $fk = brand_token((string) $from);
        $tk = brand_token((string) $to);
        if ($fk !== '' && $tk !== '' && !isset($apBrand[$fk])) {
            $apBrand[$fk] = $tk;
        }
    }
}

function expand_brand(string $raw, array $map): string
{
    $tok = brand_token($raw);
    if ($tok === '') {
        return 'NECUNOSCUT';
    }

    return $map[$tok] ?? $tok;
}

echo "1) Catalog 3208129 + TecDoc...\n";
$byA = [];
$products = [];
$aDup = 0;
$cDup = 0;
$seenC = [];
$in = fopen($catalog, 'rb');
while (($row = fgetcsv($in, 0, ';')) !== false) {
    if (!is_array($row) || count($row) < 4) {
        continue;
    }
    $a = to_utf8(trim((string) $row[0]));
    $name = to_utf8(trim((string) ($row[1] ?? '')));
    $c = to_utf8(trim((string) ($row[2] ?? '')));
    $d = to_utf8(trim((string) ($row[3] ?? '')));
    $price = trim((string) ($row[5] ?? ''));
    if ($c === '' && $a === '') {
        continue;
    }
    $cNorm = clean_tecdoc($c !== '' ? $c : $a);
    $aNorm = clean_photo_a($a !== '' ? $a : $c);
    if ($cNorm === '') {
        continue;
    }
    $brand = expand_brand($d, $apBrand);
    $pkey = $brand . '|' . $cNorm;
    if (isset($products[$pkey])) {
        $cDup++;
        continue;
    }
    if (isset($seenC[$cNorm]) && $seenC[$cNorm] !== $brand) {
        $cDup++;
    }
    $seenC[$cNorm] = $brand;
    $products[$pkey] = [
        'brand' => $brand,
        'code_norm' => $cNorm,
        'code_a' => $a,
        'code_c' => $c,
        'a_norm' => $aNorm,
        'name' => $name,
        'price' => $price !== '' ? $price : null,
        'images' => [],
    ];
    if ($aNorm !== '') {
        if (!isset($byA[$aNorm])) {
            $byA[$aNorm] = [];
        } else {
            $aDup++;
        }
        $byA[$aNorm][] = $pkey;
    }
}
fclose($in);
echo '   produse catalog: ' . count($products) . ", index A: " . count($byA) . ", A partajat: {$aDup}, C/brand dublu: {$cDup}\n";

$td = [];
try {
    $tp = new PDO('mysql:host=127.0.0.1;dbname=besoiu_tecdoc_base;charset=utf8mb4', 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    $q = $tp->query('SELECT code_norm FROM product_codes');
    while ($r = $q->fetch(PDO::FETCH_ASSOC)) {
        $td[strtoupper((string) $r['code_norm'])] = true;
    }
} catch (PDOException $e) {
    echo '   TecDoc indisponibil: ' . $e->getMessage() . "\n";
}
$tdHit = 0;
foreach ($products as $p) {
    if (isset($td[$p['code_norm']])) {
        $tdHit++;
    }
}
echo "   TecDoc product_codes: " . count($td) . ", C gasit in TecDoc: {$tdHit}\n";

echo "2) Imagini din BD curenta + foldere...\n";
$old = new PDO('mysql:host=127.0.0.1;dbname=imagine_produse;charset=utf8mb4', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
$oldRows = $old->query('SELECT original_file, disk_name, rel_path, source, brand, code_norm, code_after_strip FROM images')->fetchAll(PDO::FETCH_ASSOC);

$seenOrig = [];
$candidates = [];
foreach ($oldRows as $r) {
    $orig = (string) $r['original_file'];
    $seenOrig[strtolower($orig)] = true;
    $disk = $renDir . DIRECTORY_SEPARATOR . $r['disk_name'];
    if (!file_ok($disk)) {
        $disk = $rejectDir . DIRECTORY_SEPARATOR . $orig;
    }
    if (!file_ok($disk)) {
        $disk = $imgDir . DIRECTORY_SEPARATOR . $orig;
    }
    $candidates[] = [
        'orig' => $orig,
        'abs' => file_ok($disk) ? $disk : '',
        'old_source' => (string) $r['source'],
        'old_brand' => (string) $r['brand'],
        'old_code' => (string) $r['code_norm'],
        'old_strip' => (string) $r['code_after_strip'],
    ];
}

$extraScan = 0;
if (is_dir($imgDir)) {
    $dh = opendir($imgDir);
    while (($fn = readdir($dh)) !== false) {
        if ($fn === '.' || $fn === '..' || str_starts_with($fn, '._')) {
            continue;
        }
        if (isset($seenOrig[strtolower($fn)])) {
            continue;
        }
        $abs = $imgDir . DIRECTORY_SEPARATOR . $fn;
        if (!is_file($abs)) {
            continue;
        }
        $candidates[] = ['orig' => $fn, 'abs' => $abs, 'old_source' => 'zdjecia', 'old_brand' => '', 'old_code' => '', 'old_strip' => ''];
        $extraScan++;
    }
    closedir($dh);
}
echo '   candidati: ' . count($candidates) . " (noi din ZDJECIA: {$extraScan})\n";

$stat = [
    'keep_a' => 0,
    'reject_no_stem' => 0,
    'reject_no_a' => 0,
    'reject_was_c' => 0,
    'reject_was_ean' => 0,
    'reject_was_indeks' => 0,
    'reject_was_kaucje' => 0,
    'new_from_zdjecia' => 0,
    'brand_wrong_old' => 0,
    'code_wrong_old' => 0,
];
$keep = [];
$reject = [];

foreach ($candidates as $cand) {
    $stem = strip_ap_stem($cand['orig']);
    if ($stem === null) {
        $stat['reject_no_stem']++;
        $reject[] = $cand + ['why' => 'fara_sufix_large'];
        continue;
    }
    $aNorm = clean_photo_a($stem);
    $pkeys = $byA[$aNorm] ?? [];
    if ($pkeys === []) {
        $src = $cand['old_source'];
        if ($src === 'catalog_C') {
            $stat['reject_was_c']++;
        } elseif ($src === 'ean') {
            $stat['reject_was_ean']++;
        } elseif ($src === 'indeks') {
            $stat['reject_was_indeks']++;
        } elseif ($src === 'kaucje') {
            $stat['reject_was_kaucje']++;
        }
        $stat['reject_no_a']++;
        if ($cand['old_source'] !== 'zdjecia') {
            $reject[] = $cand + ['why' => 'stem_nu_egaleaza_A', 'stem' => $stem, 'a_norm' => $aNorm];
        }
        continue;
    }
    foreach ($pkeys as $pkey) {
        $p = $products[$pkey];
        if ($cand['old_code'] !== '' && $cand['old_code'] !== $p['code_norm']) {
            $stat['code_wrong_old']++;
        }
        if ($cand['old_brand'] !== '' && $cand['old_brand'] !== $p['brand']) {
            $stat['brand_wrong_old']++;
        }
        if ($cand['old_source'] === 'zdjecia') {
            $stat['new_from_zdjecia']++;
        }
        $stat['keep_a']++;
        $keep[] = [
            'pkey' => $pkey,
            'orig' => $cand['orig'],
            'abs' => $cand['abs'],
            'stem' => $stem,
        ];
    }
}

echo "   pastrate prin A: {$stat['keep_a']}, respinse din BD: " . count($reject) . " (C={$stat['reject_was_c']} EAN={$stat['reject_was_ean']} INDEKS={$stat['reject_was_indeks']} KAUCJE={$stat['reject_was_kaucje']})\n";
echo "   poze noi din ZDJECIA prin A: {$stat['new_from_zdjecia']}, brand gresit in BD veche: {$stat['brand_wrong_old']}, cod gresit: {$stat['code_wrong_old']}\n";
if ($stat['keep_a'] < 200000) {
    fwrite(STDERR, "STOP: prea putine potriviri A ({$stat['keep_a']}). Nu mut fisiere.\n");
    exit(2);
}

echo "3) Mut respinse + redenumesc corecte...\n";
if (!is_dir($rejectDir)) {
    mkdir($rejectDir, 0777, true);
}
if (!is_dir($renDir)) {
    mkdir($renDir, 0777, true);
}

$movedReject = 0;
foreach ($reject as $r) {
    if ($r['abs'] === '' || !file_ok($r['abs'])) {
        continue;
    }
    $dest = $rejectDir . DIRECTORY_SEPARATOR . $r['orig'];
    if (!file_ok($dest)) {
        @rename($r['abs'], $dest) || @copy($r['abs'], $dest);
    }
    if (file_ok($dest) && realpath($r['abs']) !== realpath($dest)) {
        @unlink($r['abs']);
    }
    $movedReject++;
}

$byP = [];
foreach ($keep as $row) {
    $byP[$row['pkey']][] = $row;
}

$map = fopen($mapOut, 'wb');
fwrite($map, "fisier_original;cod_dupa_strip;a_norm;brand;cod_C_norm;nume_pe_disc;rel_path;status\n");
$finalImages = [];
$renamed = 0;
foreach ($byP as $pkey => $list) {
    $p = $products[$pkey];
    $n = count($list);
    $i = 1;
    foreach ($list as $row) {
        $base = $n === 1 ? ($p['brand'] . '-' . $p['code_norm']) : ($p['brand'] . '-' . $p['code_norm'] . '_' . $i);
        $disk = $base . '.jpg';
        $dest = $renDir . DIRECTORY_SEPARATOR . $disk;
        $src = $row['abs'];
        if ($src !== '' && file_ok($src)) {
            if (realpath($src) !== realpath($dest)) {
                if (file_ok($dest)) {
                    @unlink($dest);
                }
                if (@rename($src, $dest) || @link($src, $dest) || @copy($src, $dest)) {
                    $renamed++;
                    if (is_file($src) && realpath($src) !== realpath($dest)) {
                        @unlink($src);
                    }
                }
            }
        }
        $ok = file_ok($dest);
        $rel = 'IMAGINI_RENUMITE/' . $disk;
        $finalImages[] = [
            'brand' => $p['brand'],
            'code_norm' => $p['code_norm'],
            'code_a' => $p['code_a'],
            'stem' => $row['stem'],
            'orig' => $row['orig'],
            'disk' => $disk,
            'rel' => $rel,
            'ok' => $ok,
        ];
        $products[$pkey]['images'][] = $disk;
        fputcsv($map, [$row['orig'], $row['stem'], $p['a_norm'], $p['brand'], $p['code_norm'], $disk, $rel, $ok ? 'ok' : 'lipsa_fisier'], ';');
        $i++;
    }
}
fclose($map);
echo "   mutate respinse: {$movedReject}, redenumite/pastrate: {$renamed}, imagini finale: " . count($finalImages) . "\n";

echo "4) Recreez imagine_produse...\n";
$pdo = new PDO('mysql:host=127.0.0.1;charset=utf8mb4', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES => true,
]);
$pdo->exec('CREATE DATABASE IF NOT EXISTS `imagine_produse` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$pdo->exec('USE `imagine_produse`');
$pdo->exec('SET FOREIGN_KEY_CHECKS=0');
$pdo->exec('SET UNIQUE_CHECKS=0');
$pdo->exec('DROP TABLE IF EXISTS images');
$pdo->exec('DROP TABLE IF EXISTS products');
$pdo->exec('DROP TABLE IF EXISTS meta');
$pdo->exec("CREATE TABLE products (
  code_norm VARCHAR(128) NOT NULL,
  brand VARCHAR(64) NOT NULL DEFAULT '',
  code_a VARCHAR(160) DEFAULT NULL,
  code_c VARCHAR(160) DEFAULT NULL,
  a_norm VARCHAR(128) DEFAULT NULL,
  name VARCHAR(512) DEFAULT NULL,
  price DECIMAL(10,2) DEFAULT NULL,
  currency CHAR(3) DEFAULT 'RON',
  image_count INT UNSIGNED NOT NULL DEFAULT 0,
  tecdoc_match TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (brand, code_norm),
  KEY idx_code_norm (code_norm),
  KEY idx_a_norm (a_norm)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE images (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  brand VARCHAR(64) NOT NULL DEFAULT '',
  code_norm VARCHAR(128) NOT NULL,
  code_after_strip VARCHAR(160) DEFAULT NULL,
  original_file VARCHAR(255) NOT NULL,
  disk_name VARCHAR(255) NOT NULL,
  rel_path VARCHAR(512) NOT NULL,
  source VARCHAR(32) NOT NULL DEFAULT 'catalog_A',
  PRIMARY KEY (id),
  UNIQUE KEY uq_disk_name (disk_name),
  KEY idx_brand_code (brand, code_norm)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE meta (k VARCHAR(64) NOT NULL, v TEXT NOT NULL, PRIMARY KEY (k)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$bufP = [];
$flushP = static function () use ($pdo, &$bufP): void {
    if ($bufP === []) {
        return;
    }
    $parts = [];
    foreach ($bufP as $r) {
        $parts[] = '(' . quote_sql($pdo, $r[0]) . ',' . quote_sql($pdo, $r[1]) . ',' . quote_sql($pdo, $r[2]) . ',' . quote_sql($pdo, $r[3]) . ',' . quote_sql($pdo, $r[4]) . ',' . quote_sql($pdo, $r[5]) . ',' . quote_sql($pdo, $r[6]) . ',\'RON\',' . (int) $r[7] . ',' . (int) $r[8] . ')';
    }
    $pdo->exec('INSERT INTO products (code_norm,brand,code_a,code_c,a_norm,name,price,currency,image_count,tecdoc_match) VALUES ' . implode(',', $parts));
    $bufP = [];
};
$bufI = [];
$flushI = static function () use ($pdo, &$bufI): void {
    if ($bufI === []) {
        return;
    }
    $parts = [];
    foreach ($bufI as $r) {
        $parts[] = '(' . quote_sql($pdo, $r[0]) . ',' . quote_sql($pdo, $r[1]) . ',' . quote_sql($pdo, $r[2]) . ',' . quote_sql($pdo, $r[3]) . ',' . quote_sql($pdo, $r[4]) . ',' . quote_sql($pdo, $r[5]) . ',\'catalog_A\')';
    }
    $pdo->exec('INSERT IGNORE INTO images (brand,code_norm,code_after_strip,original_file,disk_name,rel_path,source) VALUES ' . implode(',', $parts));
    $bufI = [];
};

$prodWithImg = 0;
$pdo->beginTransaction();
foreach ($products as $p) {
    $ic = count($p['images']);
    if ($ic > 0) {
        $prodWithImg++;
    }
    $tdm = isset($td[$p['code_norm']]) ? 1 : 0;
    $bufP[] = [$p['code_norm'], $p['brand'], $p['code_a'], $p['code_c'], $p['a_norm'], $p['name'] ?: null, $p['price'], $ic, $tdm];
    if (count($bufP) >= 400) {
        $flushP();
    }
}
$flushP();
$okFiles = 0;
foreach ($finalImages as $im) {
    if (!$im['ok']) {
        continue;
    }
    $okFiles++;
    $bufI[] = [$im['brand'], $im['code_norm'], $im['stem'], $im['orig'], $im['disk'], $im['rel']];
    if (count($bufI) >= 400) {
        $flushI();
    }
}
$flushI();
$pdo->commit();

$rejCsv = 'C:/Users/Radu/Desktop/autoparner/RESPINSE_ALGORITM.csv';
$rf = fopen($rejCsv, 'wb');
fwrite($rf, "original;why;stem;a_norm;old_source;old_brand;old_code\n");
foreach ($reject as $r) {
    fputcsv($rf, [
        $r['orig'],
        $r['why'] ?? '',
        $r['stem'] ?? '',
        $r['a_norm'] ?? '',
        $r['old_source'] ?? '',
        $r['old_brand'] ?? '',
        $r['old_code'] ?? '',
    ], ';');
}
fclose($rf);

$meta = $pdo->prepare('REPLACE INTO meta (k,v) VALUES (?,?)');
foreach ([
    'imported_at' => date('c'),
    'algorithm' => 'TecDoc/C clean space-./ ; photo stem until _N_large/_large = A clean space-_./',
    'products' => (string) count($products),
    'products_with_image' => (string) $prodWithImg,
    'images' => (string) $okFiles,
    'tecdoc_hit' => (string) $tdHit,
    'rejected' => (string) count($reject),
    'source_dir' => $renDir,
] as $k => $v) {
    $meta->execute([$k, $v]);
}

$report = [];
$report[] = 'RAPORT VERIFICARE + CORECTARE imagine_produse';
$report[] = 'Algoritm: TecDoc curat (spatiu - . /) = coloana C curata; poza = coloana A pana la _1_large.._6_large / _large; A si stem curate (spatiu - . / _).';
$report[] = '';
$report[] = 'INAINTE (greseli gasite):';
$report[] = '- 9512 imagini mapate pe coloana C; din ele 7093 aveau A != C (ex. 0-127_large.jpg -> NGK 0127, dar A=SIFR6A11)';
$report[] = '- 15050 imagini mapate pe EAN (ex. 4038081981709_large.jpg) — in afara algoritmului';
$report[] = '- 3496 INDEKS + 919 KAUCJE — in afara algoritmului';
$report[] = '- 4550 imagini fara brand';
$report[] = '- brand gresit: NRF/HAN/ELS puse ca MAXGEAR; products.brand prescurtat (MAX) vs images.brand (MAXGEAR)';
$report[] = '- PK doar pe code_norm, fara brand';
$report[] = '';
$report[] = 'ACUM:';
$report[] = 'produse catalog: ' . count($products);
$report[] = 'produse cu imagine (doar A): ' . $prodWithImg;
$report[] = 'imagini pastrate: ' . $okFiles;
$report[] = 'C gasit in TecDoc product_codes: ' . $tdHit . ' / ' . count($products);
$report[] = 'respinse (nu A): ' . count($reject) . " (C={$stat['reject_was_c']} EAN={$stat['reject_was_ean']} INDEKS={$stat['reject_was_indeks']} KAUCJE={$stat['reject_was_kaucje']})";
$report[] = 'poze noi gasite in ZDJECIA prin A: ' . $stat['new_from_zdjecia'];
$report[] = 'brand gresit corectat: ' . $stat['brand_wrong_old'];
$report[] = 'cod gresit corectat: ' . $stat['code_wrong_old'];
$report[] = '';
$report[] = 'Folder pastrate: ' . $renDir;
$report[] = 'Folder respinse: ' . $rejectDir;
$report[] = 'CSV respinse: ' . $rejCsv;
$report[] = 'Mapare: ' . $mapOut;
$report[] = 'HeidiSQL: imagine_produse (images.source = catalog_A)';
file_put_contents($reportPath, implode("\n", $report) . "\n");
echo implode("\n", $report) . "\n";

$ex = $pdo->query("SELECT brand, code_a, code_c, code_norm, disk_name FROM images i JOIN products p USING (brand, code_norm) WHERE p.a_norm='MGA5562' LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
foreach ($ex as $r) {
    echo 'exemplu MGA-5562: ' . $r['brand'] . ' A=' . $r['code_a'] . ' C=' . $r['code_c'] . ' -> ' . $r['disk_name'] . "\n";
}
